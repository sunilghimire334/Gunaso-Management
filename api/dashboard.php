<?php
/**
 * ============================================================
 * Gunaso REST API – Dashboard Statistics
 * बेसीशहर नगरपालिका
 * ============================================================
 * File    : api/dashboard.php
 * Method  : GET
 * Endpoint: /api/dashboard.php
 *
 * Response:
 * {
 *   "success": true,
 *   "message": "Data Retrieved Successfully",
 *   "data": {
 *     "total": 1000,
 *     "pending": 100,
 *     "in_progress": 50,
 *     "resolved": 830,
 *     "rejected": 20,
 *     "today": 5,
 *     "this_month": 120,
 *     "resolution_rate": 83.00
 *   }
 * }
 * ============================================================
 */

declare(strict_types=1);
define('API_ENTRY', true);

// ── Bootstrap ─────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';

// ── HTTPS enforcement ─────────────────────────────────────────
if (API_FORCE_HTTPS && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'HTTPS is required.']));
}

// ── CORS headers ──────────────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = API_ALLOWED_ORIGINS;
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} elseif (in_array('*', $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: ' . API_ALLOWED_METHODS);
header('Access-Control-Allow-Headers: Authorization, X-API-KEY, Content-Type, Accept');
header('Access-Control-Max-Age: 86400');

// ── Pre-flight OPTIONS ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── JSON content type ─────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache, must-revalidate');

// ── Only GET allowed ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET, OPTIONS');
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use GET.']);
    exit;
}

// ── Start logging ─────────────────────────────────────────────
ApiLogger::start();

// ── Authenticate ──────────────────────────────────────────────
$keyRow = ApiAuth::authenticate();
$keyId  = (int) $keyRow['id'];

// ── Query ─────────────────────────────────────────────────────
$httpCode = 200;
try {
    $pdo = ApiDatabase::getInstance();

    // Single-pass status breakdown
    $stmt = $pdo->query(
        "SELECT
            status,
            COUNT(*) AS cnt
         FROM complaints
         GROUP BY status"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map into named counters
    $counts = [
        'pending'     => 0,
        'in_progress' => 0,
        'resolved'    => 0,
        'rejected'    => 0,
    ];

    foreach ($rows as $row) {
        // DB stores 'in-progress' with hyphen; API returns 'in_progress' with underscore
        $key = $row['status'] === 'in-progress' ? 'in_progress' : $row['status'];
        if (array_key_exists($key, $counts)) {
            $counts[$key] = (int) $row['cnt'];
        }
    }

    $total = array_sum($counts);

    // Today's complaints
    $stmtToday = $pdo->prepare(
        "SELECT COUNT(*) AS cnt
         FROM complaints
         WHERE DATE(created_at) = CURDATE()"
    );
    $stmtToday->execute();
    $today = (int) $stmtToday->fetchColumn();

    // This month's complaints
    $stmtMonth = $pdo->prepare(
        "SELECT COUNT(*) AS cnt
         FROM complaints
         WHERE YEAR(created_at) = YEAR(CURDATE())
           AND MONTH(created_at) = MONTH(CURDATE())"
    );
    $stmtMonth->execute();
    $thisMonth = (int) $stmtMonth->fetchColumn();

    // Resolution rate (%)
    $resolutionRate = $total > 0
        ? round(($counts['resolved'] / $total) * 100, 2)
        : 0.00;

    $data = [
        'total'           => $total,
        'pending'         => $counts['pending'],
        'in_progress'     => $counts['in_progress'],
        'resolved'        => $counts['resolved'],
        'rejected'        => $counts['rejected'],
        'today'           => $today,
        'this_month'      => $thisMonth,
        'resolution_rate' => $resolutionRate,
        'generated_at'    => date('Y-m-d H:i:s'),
    ];

    $response = [
        'success' => true,
        'message' => 'Data Retrieved Successfully',
        'data'    => $data,
    ];

} catch (Exception $e) {
    $httpCode = 500;
    ApiLogger::error('dashboard.php', $e->getMessage());
    $response = [
        'success' => false,
        'message' => 'An internal server error occurred. Please try again later.',
    ];
}

// ── Send response ─────────────────────────────────────────────
http_response_code($httpCode);
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ── Finish logging ────────────────────────────────────────────
ApiLogger::finish($httpCode, $keyId);
