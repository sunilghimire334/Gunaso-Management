<?php
/**
 * ============================================================
 * Gunaso REST API – Request Logger
 * बेसीशहर नगरपालिका
 * ============================================================
 * File   : api/logger.php
 * Purpose: Logs every API request to the api_logs table and
 *          optionally to a flat error-log file.
 *          Call ApiLogger::start() at the top of each endpoint
 *          (before authenticate()) and ApiLogger::finish() at
 *          the very end after sending the response.
 * ============================================================
 */

declare(strict_types=1);

if (!defined('API_ENTRY')) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Direct access not allowed.']));
}

class ApiLogger
{
    /** Unix timestamp (float) of when the request started */
    private static float $startTime = 0.0;

    /** HTTP response code that will be logged */
    private static int $responseCode = 200;

    /** api_key_id resolved after authentication (nullable) */
    private static ?int $keyId = null;

    // ── Public API ────────────────────────────────────────────

    /**
     * Call at the very start of each endpoint (before auth).
     * Records the request start time.
     */
    public static function start(): void
    {
        self::$startTime  = microtime(true);
        self::$responseCode = 200;   // default; overridden by finish()
        self::$keyId       = null;
    }

    /**
     * Call after sending the JSON response body.
     * Writes one row to api_logs.
     *
     * @param int       $httpCode    Final HTTP status code sent to client
     * @param int|null  $apiKeyId    Resolved api_keys.id (null on auth failure)
     */
    public static function finish(int $httpCode = 200, ?int $apiKeyId = null): void
    {
        if (!API_ENABLE_DB_LOG) {
            return;
        }

        self::$responseCode = $httpCode;
        self::$keyId        = $apiKeyId;

        $executionMs = (int) round((microtime(true) - self::$startTime) * 1000);

        $endpoint    = self::sanitizeEndpoint($_SERVER['REQUEST_URI'] ?? '');
        $queryString = self::sanitizeQueryString($_SERVER['QUERY_STRING'] ?? '');
        $method      = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $ip          = ApiAuth::getClientIp();

        try {
            $pdo  = ApiDatabase::getInstance();
            $stmt = $pdo->prepare(
                "INSERT INTO api_logs
                    (api_key_id, endpoint, ip_address, request_method,
                     query_string, response_code, execution_ms, created_at)
                 VALUES
                    (:key_id, :endpoint, :ip, :method,
                     :qs, :code, :ms, NOW())"
            );

            $stmt->execute([
                ':key_id'   => $apiKeyId,
                ':endpoint' => $endpoint,
                ':ip'       => $ip,
                ':method'   => $method,
                ':qs'       => $queryString ?: null,
                ':code'     => $httpCode,
                ':ms'       => $executionMs,
            ]);
        } catch (Exception $e) {
            // DB unavailable – fall back to flat-file log
            self::writeFileLog(
                $ip, $method, $endpoint, $httpCode, $executionMs,
                'DB_LOG_FAILED: ' . $e->getMessage()
            );
        }
    }

    /**
     * Log an application-level error (non-fatal) to the error log file.
     *
     * @param string $context  Short label, e.g. 'complaints.php'
     * @param string $message  Error detail
     */
    public static function error(string $context, string $message): void
    {
        $entry = sprintf(
            "[%s] ERROR [%s] IP:%s | %s\n",
            date('Y-m-d H:i:s'),
            $context,
            ApiAuth::getClientIp(),
            $message
        );
        @error_log($entry, 3, API_ERROR_LOG_FILE);
    }

    /**
     * Log a warning (non-fatal) to the error log file.
     */
    public static function warning(string $context, string $message): void
    {
        $entry = sprintf(
            "[%s] WARN  [%s] IP:%s | %s\n",
            date('Y-m-d H:i:s'),
            $context,
            ApiAuth::getClientIp(),
            $message
        );
        @error_log($entry, 3, API_ERROR_LOG_FILE);
    }

    // ── Private Helpers ───────────────────────────────────────

    /**
     * Strip query string from the URI and truncate to 255 chars.
     */
    private static function sanitizeEndpoint(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;
        return substr(strip_tags($path), 0, 255);
    }

    /**
     * Sanitize query string: remove any value that looks like a key/token
     * to avoid storing sensitive data in the log.
     */
    private static function sanitizeQueryString(string $qs): string
    {
        if ($qs === '') {
            return '';
        }

        // Redact parameters commonly used for tokens/keys
        $redactParams = ['api_key', 'apikey', 'token', 'key', 'secret', 'password', 'pass'];
        parse_str($qs, $params);

        foreach ($params as $k => $v) {
            if (in_array(strtolower($k), $redactParams, true)) {
                $params[$k] = '[REDACTED]';
            }
        }

        return substr(http_build_query($params), 0, 2000);
    }

    /**
     * Fallback flat-file log writer.
     */
    private static function writeFileLog(
        string $ip,
        string $method,
        string $endpoint,
        int    $code,
        int    $ms,
        string $note = ''
    ): void {
        $entry = sprintf(
            "[%s] %s %s %d %dms IP:%s %s\n",
            date('Y-m-d H:i:s'),
            $method,
            $endpoint,
            $code,
            $ms,
            $ip,
            $note
        );
        @error_log($entry, 3, API_ERROR_LOG_FILE);
    }
}
