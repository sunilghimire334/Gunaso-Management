<?php
/**
 * ============================================================
 * Gunaso REST API – Authentication Middleware
 * बेसीशहर नगरपालिका
 * ============================================================
 * File   : api/auth.php
 * Purpose: Validates API key on every inbound request.
 *          Supports two header formats:
 *            Authorization: Bearer <key>
 *            X-API-KEY: <key>
 *          Enforces per-key rate limiting (sliding 1-min window).
 * ============================================================
 */

declare(strict_types=1);

if (!defined('API_ENTRY')) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Direct access not allowed.']));
}

class ApiAuth
{
    /** Holds the validated key row after authenticate() succeeds */
    private static ?array $currentKey = null;

    // ── Public API ────────────────────────────────────────────

    /**
     * Authenticate the current request.
     * Sends a 401 JSON response and exits on failure.
     * On success, returns the api_keys row array.
     *
     * @return array  Validated api_keys row
     */
    public static function authenticate(): array
    {
        // 1. Extract raw key from request headers
        $rawKey = self::extractKey();

        if ($rawKey === null) {
            self::deny('API key missing. Provide it via Authorization: Bearer <key> or X-API-KEY header.');
        }

        // 2. Validate format (64-char hex OR arbitrary string up to 128 chars)
        if (!self::isValidKeyFormat($rawKey)) {
            self::deny('Invalid API key format.');
        }

        // 3. Look up the key in the database
        $keyRow = self::lookupKey($rawKey);

        if ($keyRow === null) {
            self::deny('Unauthorized Access');
        }

        // 4. Check key status
        if ($keyRow['status'] !== 'active') {
            self::deny('API key is inactive or revoked.');
        }

        // 5. IP whitelist check (if configured)
        if (!empty($keyRow['allowed_ips'])) {
            self::checkIpWhitelist($keyRow['allowed_ips']);
        }

        // 6. Rate limiting
        self::checkRateLimit((int) $keyRow['id'], (int) $keyRow['rate_limit']);

        // 7. Update last_used_at timestamp (fire-and-forget)
        self::touchLastUsed((int) $keyRow['id']);

        self::$currentKey = $keyRow;
        return $keyRow;
    }

    /**
     * Return the current authenticated key row (after authenticate()).
     */
    public static function currentKey(): ?array
    {
        return self::$currentKey;
    }

    /**
     * Return the current key ID (or null if not authenticated yet).
     */
    public static function currentKeyId(): ?int
    {
        return self::$currentKey ? (int) self::$currentKey['id'] : null;
    }

    // ── Private Helpers ───────────────────────────────────────

    /**
     * Extract the raw API key string from request headers.
     * Checks Authorization: Bearer <token>  and  X-API-KEY: <token>.
     */
    private static function extractKey(): ?string
    {
        // Priority 1 – Authorization: Bearer <key>
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? getallheaders()['Authorization']
            ?? null;

        if ($authHeader !== null && stripos($authHeader, 'bearer ') === 0) {
            $key = trim(substr($authHeader, 7));
            return $key !== '' ? $key : null;
        }

        // Priority 2 – X-API-KEY: <key>
        $xApiKey = $_SERVER['HTTP_X_API_KEY']
            ?? getallheaders()['X-Api-Key']
            ?? getallheaders()['X-API-KEY']
            ?? null;

        if ($xApiKey !== null) {
            $key = trim($xApiKey);
            return $key !== '' ? $key : null;
        }

        return null;
    }

    /**
     * Basic format validation:
     * - Must be 32–128 printable ASCII characters
     * - No whitespace allowed
     */
    private static function isValidKeyFormat(string $key): bool
    {
        $len = strlen($key);
        if ($len < 32 || $len > 128) {
            return false;
        }
        // Only printable, non-whitespace characters
        return (bool) preg_match('/^[!-~]+$/', $key);
    }

    /**
     * Fetch the api_keys row matching the given key.
     * The key is stored as SHA-256 hex, so we hash the input.
     */
    private static function lookupKey(string $rawKey): ?array
    {
        try {
            $pdo  = ApiDatabase::getInstance();
            $hash = hash('sha256', $rawKey);

            $stmt = $pdo->prepare(
                "SELECT id, client_name, api_key, status, allowed_ips, rate_limit
                 FROM api_keys
                 WHERE api_key = :key
                 LIMIT 1"
            );
            $stmt->execute([':key' => $hash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (Exception $e) {
            self::logError('lookupKey: ' . $e->getMessage());
            // Fail safe – deny access if DB is unavailable
            self::deny('Service temporarily unavailable. Please try again later.');
        }
    }

    /**
     * Enforce per-key sliding-window rate limiting.
     * Uses the api_rate_limit table for persistent counters.
     */
    private static function checkRateLimit(int $keyId, int $limit): void
    {
        try {
            $pdo = ApiDatabase::getInstance();
            $now = date('Y-m-d H:i:s');

            // Fetch current window row
            $stmt = $pdo->prepare(
                "SELECT window_start, hit_count
                 FROM api_rate_limit
                 WHERE api_key_id = :id
                 FOR UPDATE"
            );

            $pdo->beginTransaction();
            $stmt->execute([':id' => $keyId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                // First request from this key — insert new window
                $ins = $pdo->prepare(
                    "INSERT INTO api_rate_limit (api_key_id, window_start, hit_count)
                     VALUES (:id, :now, 1)"
                );
                $ins->execute([':id' => $keyId, ':now' => $now]);
                $pdo->commit();
                return;
            }

            $windowStart = strtotime($row['window_start']);
            $elapsed     = time() - $windowStart;

            if ($elapsed >= API_RATE_WINDOW_SEC) {
                // Window expired – reset counter
                $upd = $pdo->prepare(
                    "UPDATE api_rate_limit
                     SET window_start = :now, hit_count = 1
                     WHERE api_key_id = :id"
                );
                $upd->execute([':now' => $now, ':id' => $keyId]);
                $pdo->commit();
                return;
            }

            // Still within window
            if ((int) $row['hit_count'] >= $limit) {
                $pdo->commit();
                $retryAfter = API_RATE_WINDOW_SEC - $elapsed;
                header('Retry-After: ' . $retryAfter);
                http_response_code(429);
                echo json_encode([
                    'success'     => false,
                    'message'     => 'Rate limit exceeded. Maximum ' . $limit
                                     . ' requests per minute. Retry after '
                                     . $retryAfter . ' seconds.',
                    'retry_after' => $retryAfter,
                ]);
                exit;
            }

            // Increment counter
            $upd = $pdo->prepare(
                "UPDATE api_rate_limit
                 SET hit_count = hit_count + 1
                 WHERE api_key_id = :id"
            );
            $upd->execute([':id' => $keyId]);
            $pdo->commit();

        } catch (Exception $e) {
            // If rate-limit table is unavailable, log and allow (fail open)
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::logError('checkRateLimit: ' . $e->getMessage());
        }
    }

    /**
     * Check that the request IP is in the key's allowed_ips list.
     */
    private static function checkIpWhitelist(string $allowedIps): void
    {
        $clientIp  = self::getClientIp();
        $whitelist = array_map('trim', explode(',', $allowedIps));

        if (!in_array($clientIp, $whitelist, true)) {
            self::deny('Access denied: your IP address (' . $clientIp . ') is not whitelisted for this key.');
        }
    }

    /**
     * Update the last_used_at timestamp for the given key.
     */
    private static function touchLastUsed(int $keyId): void
    {
        try {
            $pdo  = ApiDatabase::getInstance();
            $stmt = $pdo->prepare(
                "UPDATE api_keys SET last_used_at = NOW() WHERE id = :id"
            );
            $stmt->execute([':id' => $keyId]);
        } catch (Exception $e) {
            self::logError('touchLastUsed: ' . $e->getMessage());
        }
    }

    /**
     * Get the real client IP, respecting common proxy headers.
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For may contain a chain; take the first
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Write an error entry to the flat-file error log.
     */
    private static function logError(string $msg): void
    {
        $entry = sprintf("[%s] AUTH_ERROR: %s | IP: %s\n",
            date('Y-m-d H:i:s'), $msg, self::getClientIp());
        @error_log($entry, 3, API_ERROR_LOG_FILE);
    }

    /**
     * Emit a 401 JSON response and terminate execution.
     *
     * @param  string $message  Public-safe error message
     * @return never
     */
    private static function deny(string $message = 'Unauthorized Access'): void
    {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => $message,
        ]);
        exit;
    }
}
