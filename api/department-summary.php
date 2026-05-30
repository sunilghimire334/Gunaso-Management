<?php
/**
 * ============================================================
 * Gunaso REST API – Department (Branch) Summary
 * बेसीशहर नगरपालिका
 * ============================================================
 * File    : api/department-summary.php
 * Method  : GET
 * Endpoint: /api/department-summary.php
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
 *     "departments": [
 *       {
 *         "department_id": 1,
 *         "department":    "प्रशासन शाखा",
 *         "total":         120,
 *         "pending":       30,
 *         "in_progress":   10,
 *         "resolved":      75,
 *         "rejected":       5,
 *         "resolution_rate": 62.50
 *       },
 *       ...
 *     ],
 *     "category_summary": [...],
 *     "grand_total": 1000,
 *     "period": { "from": null, "to": null },
 *     "generated_at": "2026-05-30 10:00:00"
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

// ── Build date filter fragment ────────────────────────────────
$dateWhere  = [];
$dateParams = [];

if ($fromDate !== null) {
    $dateWhere[]              = 'DATE(c.created_at) >= :from_date';
    $dateParams[':from_date'] = $fromDate;
}
if ($toDate !== null) {
    $dateWhere[]            = 'DATE(c.created_at) <= :to_date';
    $dateParams[':to_date'] = $toDate;
}

$dateFilterSQL = count($dateWhere) > 0 ? 'AND ' . implode(' AND ', $dateWhere) : '';

// ── Query ─────────────────────────────────────────────────────
$httpCode = 200;
try {
    $pdo = ApiDatabase::getInstance();

    // ── 1. Department (Branch) breakdown ──────────────────────
    $deptSQL = "SELECT
                    b.id                                            AS department_id,
                    b.branch_name                                   AS department,
                    COUNT(c.id)                                     AS total,
                    SUM(c.status = 'pending')                       AS pending,
                    SUM(c.status = 'in-progress')                   AS in_progress,
                    SUM(c.status = 'resolved')                      AS resolved,
                    SUM(c.status = 'rejected')                      AS rejected
                FROM branches b
                LEFT JOIN complaints c
                    ON b.id = c.branch_id
                    $dateFilterSQL
                WHERE b.status = 'active'
                GROUP BY b.id, b.branch_name
                ORDER BY total DESC";

    $stmtDept = $pdo->prepare($deptSQL);
    $stmtDept->execute($dateParams);
    $deptRows = $stmtDept->fetchAll(PDO::FETCH_ASSOC);

    $grandTotal  = 0;
    $departments = [];

    foreach ($deptRows as $row) {
        $total          = (int) $row['total'];
        $resolved       = (int) $row['resolved'];
        $grandTotal    += $total;
        $resolutionRate = $total > 0 ? round(($resolved / $total) * 100, 2) : 0.00;

        $departments[] = [
            'department_id'   => (int) $row['department_id'],
            'department'      => htmlspecialchars($row['department'], ENT_QUOTES, 'UTF-8'),
            'total'           => $total,
            'pending'         => (int) $row['pending'],
            'in_progress'     => (int) $row['in_progress'],
            'resolved'        => $resolved,
            'rejected'        => (int) $row['rejected'],
            'resolution_rate' => $resolutionRate,
        ];
    }

    // ── 2. Category (Complaint Type) breakdown ─────────────────
    $catSQL = "SELECT
                   t.id                                             AS category_id,
                   t.type_name                                      AS category,
                   COUNT(c.id)                                      AS total,
                   SUM(c.status = 'pending')                        AS pending,
                   SUM(c.status = 'in-progress')                    AS in_progress,
                   SUM(c.status = 'resolved')                       AS resolved,
                   SUM(c.status = 'rejected')                       AS rejected
               FROM complaint_types t
               LEFT JOIN complaints c
                   ON t.id = c.type_id
                   $dateFilterSQL
               WHERE t.status = 'active'
               GROUP BY t.id, t.type_name
               ORDER BY total DESC";

    $stmtCat = $pdo->prepare($catSQL);
    $stmtCat->execute($dateParams);
    $catRows = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    $categories = [];
    foreach ($catRows as $row) {
        $total          = (int) $row['total'];
        $resolved       = (int) $row['resolved'];
        $resolutionRate = $total > 0 ? round(($resolved / $total) * 100, 2) : 0.00;

        $categories[] = [
            'category_id'     => (int) $row['category_id'],
            'category'        => htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8'),
            'total'           => $total,
            'pending'         => (int) $row['pending'],
            'in_progress'     => (int) $row['in_progress'],
            'resolved'        => $resolved,
            'rejected'        => (int) $row['rejected'],
            'resolution_rate' => $resolutionRate,
        ];
    }

    // ── 3. Top performing departments (resolved rate) ──────────
    $topPerforming = array_filter($departments, fn($d) => $d['total'] > 0);
    usort($topPerforming, fn($a, $b) => $b['resolution_rate'] <=> $a['resolution_rate']);
    $topPerforming = array_values(array_slice($topPerforming, 0, 5));

    $response = [
        'success' => true,
        'message' => 'Data Retrieved Successfully',
        'data'    => [
            'departments'       => $departments,
            'category_summary'  => $categories,
            'top_performing'    => $topPerforming,
            'grand_total'       => $grandTotal,
            'period'            => [
                'from' => $fromDate,
                'to'   => $toDate,
            ],
            'generated_at'      => date('Y-m-d H:i:s'),
        ],
    ];

} catch (Exception $e) {
    $httpCode = 500;
    ApiLogger::error('department-summary.php', $e->getMessage());
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
