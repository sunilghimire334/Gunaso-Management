<?php
/**
 * ============================================================
 * Gunaso REST API – Configuration
 * बेसीशहर नगरपालिका
 * ============================================================
 * File   : api/config.php
 * Purpose: Central configuration constants for the API module.
 *          Does NOT touch or modify any existing config.php.
 * ============================================================
 */

declare(strict_types=1);

// ── Prevent direct browser access ────────────────────────────
if (!defined('API_ENTRY')) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Direct access not allowed.']));
}

// ── Database (same credentials as the main system) ───────────
define('API_DB_HOST',    'localhost');
define('API_DB_NAME',    'techcraf_besigunaso');
define('API_DB_USER',    'techcraf_besigunaso');
define('API_DB_PASS',    'lamjungs123');
define('API_DB_CHARSET', 'utf8mb4');

// ── Site / API Identity ───────────────────────────────────────
define('API_SITE_URL',  'https://gunaso.besisharmun.gov.np');
define('API_VERSION',   'v1');
define('API_BASE_PATH', '/api');

// ── Security ──────────────────────────────────────────────────
/**
 * Force HTTPS in production.
 * Set to false ONLY in a local development environment.
 */
define('API_FORCE_HTTPS', true);

/**
 * Allowed CORS origins.
 * Add the Municipality Dashboard domain here.
 * Use ['*'] to allow any origin (NOT recommended for production).
 */
define('API_ALLOWED_ORIGINS', [
    'https://gunaso.besisharmun.gov.np',
    'https://dashboard.besisharmun.gov.np',   // Municipality main dashboard
]);

/**
 * Allowed HTTP methods for CORS pre-flight.
 */
define('API_ALLOWED_METHODS', 'GET, OPTIONS');

/**
 * Default rate-limit (requests per minute per API key).
 * Individual keys can override this in the api_keys table.
 */
define('API_RATE_LIMIT',        100);
define('API_RATE_WINDOW_SEC',    60);   // 1 minute sliding window

// ── Pagination ────────────────────────────────────────────────
define('API_DEFAULT_LIMIT', 50);
define('API_MAX_LIMIT',    200);

// ── Logging ───────────────────────────────────────────────────
/**
 * Log every request to the api_logs table.
 * Disable only if you experience extreme DB write pressure.
 */
define('API_ENABLE_DB_LOG', true);

/**
 * Also write PHP errors to a flat file (alongside DB logging).
 * Path is relative to this file's directory.
 */
define('API_ERROR_LOG_FILE', __DIR__ . '/logs/api_error.log');

// ── Response ──────────────────────────────────────────────────
define('API_TIMEZONE', 'Asia/Kathmandu');

// ── Sensitive field masking ───────────────────────────────────
/**
 * Fields that contain personal data and should be partially
 * masked in the list endpoints (full data still in single-
 * complaint endpoint for authorised consumers).
 */
define('API_MASK_PERSONAL_DATA', false);   // Set true to enable masking

// ── Apply timezone globally for this request ─────────────────
date_default_timezone_set(API_TIMEZONE);

// ── Ensure log directory exists ───────────────────────────────
if (!is_dir(__DIR__ . '/logs')) {
    @mkdir(__DIR__ . '/logs', 0750, true);

    // Drop a .htaccess so the logs directory is never web-accessible
    $htaccess = __DIR__ . '/logs/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
    }
}
