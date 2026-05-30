<?php
/**
 * ============================================================
 * Gunaso REST API – Authentication Middleware
 * बेसीशहर नगरपालिका
 * ============================================================
 */

declare(strict_types=1);

if (!defined('API_ENTRY')) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Direct access not allowed.']));
}

class ApiAuth
{
    private static ?array $currentKey = null;

    public static function authenticate(): array
    {
        // Step 1 - Extract key from headers
        $rawKey = self::extractKey();
        if ($rawKey === null) {
            self::deny('API key missing. Provide it via Authorization: Bearer <key> or X-API-KEY header.');
        }

        // Step 2 - Basic format check
        $len = strlen($rawKey);
        if ($len < 6 || $len > 128) {
            self::deny('Invalid API key format.');
        }

        // Step 3 - Find key ID using SHA2 match (separate query to avoid column conflict)
        $keyId = self::findKeyId($rawKey);
        if ($keyId === null) {
            self::deny('Unauthorized Access');
        }

        // Step 4 - Fetch full row by ID (clean, no SHA2 expression in same query)
        $keyRow = self::fetchKeyById($keyId);
        if ($keyRow === null) {
            self::deny('Unauthorized Access');
        }

        // Step 5 - Check status
        if ((string)$keyRow['status'] !== 'active') {
            self::deny('API key is inactive or revoked. Status: ' . $keyRow['status']);
        }

        // Step 6 - IP whitelist check
        if (!empty($keyRow['allowed_ips'])) {
            self::checkIpWhitelist((string)$keyRow['allowed_ips']);
        }

        // Step 7 - Rate limiting
        self::checkRateLimit((int)$keyRow['id'], (int)$keyRow['rate_limit']);

        // Step 8 - Update last_used_at
        self::touchLastUsed((int)$keyRow['id']);

        self::$currentKey = $keyRow;
        return $keyRow;
    }

    public static function currentKey(): ?array
    {
        return self::$currentKey;
    }

    public static function currentKeyId(): ?int
    {
        return self::$currentKey ? (int)self::$currentKey['id'] : null;
    }

    // ── Extract key from request headers ─────────────────────

    private static function extractKey(): ?string
    {
        // Check Authorization: Bearer <key>
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if ($authHeader === null && function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            $authHeader = $allHeaders['Authorization'] ?? $allHeaders['authorization'] ?? null;
        }

        if ($authHeader !== null && stripos($authHeader, 'bearer ') === 0) {
            $key = trim(substr($authHeader, 7));
            return $key !== '' ? $key : null;
        }

        // Check X-API-KEY header
        $xKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

        if ($xKey === null && function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            $xKey = $allHeaders['X-API-KEY']
                 ?? $allHeaders['X-Api-Key']
                 ?? $allHeaders['x-api-key']
                 ?? null;
        }

        if ($xKey !== null) {
            $key = trim($xKey);
            return $key !== '' ? $key : null;
        }

        return null;
    }

    // ── Two-step DB lookup to avoid column name conflict ──────

    /**
     * Step 1: Get the ID of the row matching the key hash.
     * Only selects `id` — no other columns that could conflict.
     */
    private static function findKeyId(string $rawKey): ?int
    {
        try {
            $pdo  = ApiDatabase::getInstance();
            $stmt = $pdo->prepare(
                "SELECT id FROM api_keys WHERE api_key = SHA2(:key, 256) LIMIT 1"
            );
            $stmt->execute([':key' => $rawKey]);
            $id = $stmt->fetchColumn();
            return ($id !== false) ? (int)$id : null;
        } catch (Exception $e) {
            self::logError('findKeyId: ' . $e->getMessage());
            self::deny('Service temporarily unavailable.');
        }
    }

    /**
     * Step 2: Fetch the full row by ID.
     * Clean query — no SHA2 expression, no column name conflict.
     */
    private static function fetchKeyById(int $id): ?array
    {
        try {
            $pdo  = ApiDatabase::getInstance();
            $stmt = $pdo->prepare(
                "SELECT id, client_name, status, allowed_ips, rate_limit
                 FROM api_keys
                 WHERE id = :id
                 LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            self::logError('fetchKeyById: ' . $e->getMessage());
            self::deny('Service temporarily unavailable.');
        }
    }

    // ── Rate limiting ─────────────────────────────────────────

    private static function checkRateLimit(int $keyId, int $limit): void
    {
        try {
            $pdo = ApiDatabase::getInstance();
            $now = date('Y-m-d H:i:s');

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "SELECT window_start, hit_count FROM api_rate_limit
                 WHERE api_key_id = :id FOR UPDATE"
            );
            $stmt->execute([':id' => $keyId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                $ins = $pdo->prepare(
                    "INSERT INTO api_rate_limit (api_key_id, window_start, hit_count)
                     VALUES (:id, :now, 1)"
                );
                $ins->execute([':id' => $keyId, ':now' => $now]);
                $pdo->commit();
                return;
            }

            $elapsed = time() - strtotime($row['window_start']);

            if ($elapsed >= API_RATE_WINDOW_SEC) {
                $upd = $pdo->prepare(
                    "UPDATE api_rate_limit
                     SET window_start = :now, hit_count = 1
                     WHERE api_key_id = :id"
                );
                $upd->execute([':now' => $now, ':id' => $keyId]);
                $pdo->commit();
                return;
            }

            if ((int)$row['hit_count'] >= $limit) {
                $pdo->commit();
                $retryAfter = API_RATE_WINDOW_SEC - $elapsed;
                header('Retry-After: ' . $retryAfter);
                http_response_code(429);
                echo json_encode([
                    'success'     => false,
                    'message'     => 'Rate limit exceeded. Retry after ' . $retryAfter . ' seconds.',
                    'retry_after' => $retryAfter,
                ]);
                exit;
            }

            $upd = $pdo->prepare(
                "UPDATE api_rate_limit SET hit_count = hit_count + 1
                 WHERE api_key_id = :id"
            );
            $upd->execute([':id' => $keyId]);
            $pdo->commit();

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('checkRateLimit: ' . $e->getMessage());
        }
    }

    // ── IP whitelist ──────────────────────────────────────────

    private static function checkIpWhitelist(string $allowedIps): void
    {
        $clientIp  = self::getClientIp();
        $whitelist = array_map('trim', explode(',', $allowedIps));
        if (!in_array($clientIp, $whitelist, true)) {
            self::deny('Access denied: IP ' . $clientIp . ' is not whitelisted.');
        }
    }

    // ── Update last_used_at ───────────────────────────────────

    private static function touchLastUsed(int $keyId): void
    {
        try {
            $pdo  = ApiDatabase::getInstance();
            $stmt = $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $keyId]);
        } catch (Exception $e) {
            self::logError('touchLastUsed: ' . $e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────

    public static function getClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    private static function logError(string $msg): void
    {
        $entry = sprintf("[%s] AUTH_ERROR: %s | IP: %s\n",
            date('Y-m-d H:i:s'), $msg, self::getClientIp());
        @error_log($entry, 3, API_ERROR_LOG_FILE);
    }

    private static function deny(string $message = 'Unauthorized Access'): void
    {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}
