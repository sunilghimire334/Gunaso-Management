<?php
/**
 * ============================================================
 * Gunaso REST API – Latest Complaints
 * बेसीशहर नगरपालिका
 * ============================================================
 * File    : api/latest.php
 * Method  : GET
 * Endpoint: /api/latest.php
 *
 * Query Parameters (all optional):
 *   limit   int     Number of complaints to return (default: 10, max: 100)
 *   status  string  Filter by status: pending | in-progress | resolved | rejected
 *
 * Response:
 * {
 *   "success": true,
 *   "message": "Data Retrieved Successfully",
 *   "count": 10,
 *   "data": [...]
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

// ── Validate parameters ───────────────────────────────────────
// limit: 1–100, default 10
$limitRaw = $_GET['limit'] ?? '';
$limit    = 10;
if ($limitRaw !== '') {
    $intVal = filter_var($limitRaw, FILTER_VALIDATE_INT);
    if ($intVal === false || $intVal < 1 || $intVal > 100) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid parameter: limit must be an integer between 1 and 100.',
        ]);
        ApiLogger::finish(400, $keyId);
        exit;
    }
    $limit = (int) $intVal;
}

// status filter
$statusAllowed = ['pending', 'in-progress', 'resolved', 'rejected'];
$statusRaw     = isset($_GET['status']) ? strip_tags(trim($_GET['status'])) : '';
$status        = ($statusRaw !== '' && in_array($statusRaw, $statusAllowed, true))
                 ? $statusRaw : null;

// ── Query ─────────────────────────────────────────────────────
$httpCode = 200;
try {
    $pdo = ApiDatabase::getInstance();

    $where  = [];
    $params = [];

    if ($status !== null) {
        $where[]           = 'c.status = :status';
        $params[':status'] = $status;
    }

    $whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT
                c.id,
                c.complaint_id,
                c.name,
                c.address,
                c.contact,
                c.email,
                c.subject,
                c.priority,
                c.status,
                c.created_at,
                c.updated_at,
                c.resolved_at,
                b.branch_name  AS department,
                t.type_name    AS category,
                u.name         AS assigned_to
            FROM complaints c
            LEFT JOIN branches        b ON c.branch_id   = b.id
            LEFT JOIN complaint_types t ON c.type_id     = t.id
            LEFT JOIN users           u ON c.assigned_to = u.id
            $whereSQL
            ORDER BY c.created_at DESC
            LIMIT " . (int)$limit;

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function (array $row): array {
        return [
            'id'           => (int) $row['id'],
            'complaint_id' => htmlspecialchars($row['complaint_id'],      ENT_QUOTES, 'UTF-8'),
            'name'         => htmlspecialchars($row['name'],              ENT_QUOTES, 'UTF-8'),
            'address'      => htmlspecialchars($row['address'] ?? '',     ENT_QUOTES, 'UTF-8'),
            'contact'      => htmlspecialchars($row['contact'] ?? '',     ENT_QUOTES, 'UTF-8'),
            'email'        => htmlspecialchars($row['email'] ?? '',       ENT_QUOTES, 'UTF-8'),
            'subject'      => htmlspecialchars($row['subject'],           ENT_QUOTES, 'UTF-8'),
            'priority'     => $row['priority'],
            'status'       => $row['status'],
            'department'   => htmlspecialchars($row['department'] ?? '',  ENT_QUOTES, 'UTF-8'),
            'category'     => htmlspecialchars($row['category'] ?? '',    ENT_QUOTES, 'UTF-8'),
            'assigned_to'  => htmlspecialchars($row['assigned_to'] ?? '', ENT_QUOTES, 'UTF-8'),
            'created_at'   => $row['created_at'],
            'updated_at'   => $row['updated_at'],
            'resolved_at'  => $row['resolved_at'],
        ];
    }, $rows);

    $response = [
        'success'      => true,
        'message'      => 'Data Retrieved Successfully',
        'count'        => count($data),
        'filters'      => [
            'status' => $status,
            'limit'  => $limit,
        ],
        'generated_at' => date('Y-m-d H:i:s'),
        'data'         => $data,
    ];

} catch (Exception $e) {
    $httpCode = 500;
    ApiLogger::error('latest.php', $e->getMessage());
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
