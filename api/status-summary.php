<?php
/**
 * ============================================================
 * Gunaso REST API – Status Summary
 * बेसीशहर नगरपालिका
 * ============================================================
 * File    : api/status-summary.php
 * Method  : GET
 * Endpoint: /api/status-summary.php
 *
 * Optional parameters:
 *   from_date  string  Y-m-d  – filter complaints created on/after this date
 *   to_date    string  Y-m-d  – filter complaints created on/before this date
 *
 * Response:
 * {
 *   "success": true,
 *   "message": "Data Retrieved Successfully",
 *   "data": {
 *     "summary": [
 *       { "status": "pending",     "count": 100, "percentage": 10.00 },
 *       { "status": "in-progress", "count": 50,  "percentage": 5.00  },
 *       { "status": "resolved",    "count": 830, "percentage": 83.00 },
 *       { "status": "rejected",    "count": 20,  "percentage": 2.00  }
 *     ],
 *     "total": 1000,
 *     "resolution_rate": 83.00,
 *     "period": { "from": "2026-01-01", "to": "2026-12-31" }
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

// ── CORS ──────────────────────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, API_ALLOWED_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} elseif (in_array('*', API_ALLOWED_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: ' . API_ALLOWED_METHODS);
header('Access-Control-Allow-Headers: Authorization, X-API-KEY, Content-Type, Accept');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── JSON headers ──────────────────────────────────────────────
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

// ── Validate date parameters ──────────────────────────────────
function validateDate(string $key): ?string
{
    $raw = isset($_GET[$key]) ? strip_tags(trim($_GET[$key])) : '';
    if ($raw === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $raw);
    return ($dt && $dt->format('Y-m-d') === $raw) ? $raw : null;
}

$fromDate = validateDate('from_date');
$toDate   = validateDate('to_date');

// Validate from_date format if supplied but invalid
if (isset($_GET['from_date']) && $_GET['from_date'] !== '' && $fromDate === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid from_date format. Use Y-m-d.']);
    ApiLogger::finish(400, $keyId);
    exit;
}
if (isset($_GET['to_date']) && $_GET['to_date'] !== '' && $toDate === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid to_date format. Use Y-m-d.']);
    ApiLogger::finish(400, $keyId);
    exit;
}
if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date range: from_date cannot be after to_date.']);
    ApiLogger::finish(400, $keyId);
    exit;
}

// ── Query ─────────────────────────────────────────────────────
$httpCode = 200;
try {
    $pdo = ApiDatabase::getInstance();

    // Build optional date WHERE clause
    $where  = [];
    $params = [];

    if ($fromDate !== null) {
        $where[]              = 'DATE(created_at) >= :from_date';
        $params[':from_date'] = $fromDate;
    }
    if ($toDate !== null) {
        $where[]            = 'DATE(created_at) <= :to_date';
        $params[':to_date'] = $toDate;
    }

    $whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // Status breakdown
    $stmt = $pdo->prepare(
        "SELECT status, COUNT(*) AS cnt
         FROM complaints
         $whereSQL
         GROUP BY status
         ORDER BY FIELD(status, 'pending', 'in-progress', 'resolved', 'rejected')"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Seed all statuses so they always appear in response
    $statusMap = [
        'pending'     => 0,
        'in-progress' => 0,
        'resolved'    => 0,
        'rejected'    => 0,
    ];

    foreach ($rows as $row) {
        if (array_key_exists($row['status'], $statusMap)) {
            $statusMap[$row['status']] = (int) $row['cnt'];
        }
    }

    $total = array_sum($statusMap);

    $summary = [];
    foreach ($statusMap as $statusKey => $count) {
        $summary[] = [
            'status'     => $statusKey,
            'count'      => $count,
            'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0.00,
        ];
    }

    $resolutionRate = $total > 0
        ? round(($statusMap['resolved'] / $total) * 100, 2)
        : 0.00;

    // Priority breakdown (bonus insight for municipality dashboard)
    $stmtPri = $pdo->prepare(
        "SELECT priority, COUNT(*) AS cnt
         FROM complaints
         $whereSQL
         GROUP BY priority
         ORDER BY FIELD(priority, 'urgent', 'high', 'medium', 'low')"
    );
    $stmtPri->execute($params);
    $priorityRows = $stmtPri->fetchAll(PDO::FETCH_ASSOC);

    $priorityMap = ['urgent' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
    foreach ($priorityRows as $pr) {
        if (array_key_exists($pr['priority'], $priorityMap)) {
            $priorityMap[$pr['priority']] = (int) $pr['cnt'];
        }
    }

    $prioritySummary = [];
    foreach ($priorityMap as $priKey => $count) {
        $prioritySummary[] = [
            'priority'   => $priKey,
            'count'      => $count,
            'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0.00,
        ];
    }

    $response = [
        'success' => true,
        'message' => 'Data Retrieved Successfully',
        'data'    => [
            'summary'         => $summary,
            'priority_summary'=> $prioritySummary,
            'total'           => $total,
            'resolution_rate' => $resolutionRate,
            'period'          => [
                'from' => $fromDate,
                'to'   => $toDate,
            ],
            'generated_at'    => date('Y-m-d H:i:s'),
        ],
    ];

} catch (Exception $e) {
    $httpCode = 500;
    ApiLogger::error('status-summary.php', $e->getMessage());
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
