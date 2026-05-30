<?php
/**
 * ============================================================
 * Gunaso REST API – Complaint List (Paginated, Filterable)
 * बेसीशहर नगरपालिका
 * ============================================================
 * File    : api/complaints.php
 * Method  : GET
 * Endpoint: /api/complaints.php
 *
 * Query Parameters (all optional):
 *   page        int     Page number (default: 1)
 *   limit       int     Records per page (default: 50, max: 200)
 *   status      string  pending | in-progress | resolved | rejected
 *   category    string  Complaint type name (partial match)
 *   department  string  Branch name (partial match)
 *   priority    string  low | medium | high | urgent
 *   search      string  Keyword search across complaint_id, name, subject
 *   from_date   string  Y-m-d  (inclusive)
 *   to_date     string  Y-m-d  (inclusive)
 *
 * Response:
 * {
 *   "success": true,
 *   "message": "Data Retrieved Successfully",
 *   "page": 1,
 *   "limit": 50,
 *   "total_records": 1000,
 *   "total_pages": 20,
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

// ── CORS headers ──────────────────────────────────────────────
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

// ── Input validation helpers ──────────────────────────────────

/**
 * Return a sanitized string from $_GET or null.
 */
function apiGetStr(string $key, int $maxLen = 100): ?string
{
    if (!isset($_GET[$key]) || $_GET[$key] === '') {
        return null;
    }
    $val = strip_tags(trim($_GET[$key]));
    return substr($val, 0, $maxLen);
}

/**
 * Return a positive integer from $_GET or the given default.
 */
function apiGetInt(string $key, int $default = 1, int $min = 1, int $max = PHP_INT_MAX): int
{
    if (!isset($_GET[$key])) {
        return $default;
    }
    $val = filter_var($_GET[$key], FILTER_VALIDATE_INT);
    if ($val === false) {
        return $default;
    }
    return max($min, min($max, (int) $val));
}

/**
 * Return a validated Y-m-d date string or null.
 */
function apiGetDate(string $key): ?string
{
    $raw = apiGetStr($key, 10);
    if ($raw === null) {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $raw);
    return ($dt && $dt->format('Y-m-d') === $raw) ? $raw : null;
}

// ── Parse & validate query parameters ────────────────────────
$page  = apiGetInt('page',  1,  1, 10000);
$limit = apiGetInt('limit', API_DEFAULT_LIMIT, 1, API_MAX_LIMIT);

$statusAllowed = ['pending', 'in-progress', 'resolved', 'rejected'];
$statusRaw     = apiGetStr('status', 20);
$status        = ($statusRaw !== null && in_array($statusRaw, $statusAllowed, true))
                 ? $statusRaw : null;

$priorityAllowed = ['low', 'medium', 'high', 'urgent'];
$priorityRaw     = apiGetStr('priority', 10);
$priority        = ($priorityRaw !== null && in_array($priorityRaw, $priorityAllowed, true))
                   ? $priorityRaw : null;

$category   = apiGetStr('category',   100);
$department = apiGetStr('department', 100);
$search     = apiGetStr('search',     150);
$fromDate   = apiGetDate('from_date');
$toDate     = apiGetDate('to_date');

// Date range sanity: from_date must not be after to_date
if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date range: from_date cannot be after to_date.',
    ]);
    ApiLogger::finish(400, $keyId);
    exit;
}

// ── Build dynamic WHERE clause ────────────────────────────────
$where  = [];
$params = [];

if ($status !== null) {
    $where[]           = 'c.status = :status';
    $params[':status'] = $status;
}

if ($priority !== null) {
    $where[]             = 'c.priority = :priority';
    $params[':priority'] = $priority;
}

if ($category !== null) {
    $where[]              = 't.type_name LIKE :category';
    $params[':category']  = '%' . $category . '%';
}

if ($department !== null) {
    $where[]               = 'b.branch_name LIKE :department';
    $params[':department'] = '%' . $department . '%';
}

if ($search !== null) {
    $where[]           = '(c.complaint_id LIKE :search
                           OR c.name        LIKE :search
                           OR c.subject     LIKE :search
                           OR c.address     LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($fromDate !== null) {
    $where[]              = 'DATE(c.created_at) >= :from_date';
    $params[':from_date'] = $fromDate;
}

if ($toDate !== null) {
    $where[]            = 'DATE(c.created_at) <= :to_date';
    $params[':to_date'] = $toDate;
}

$whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Execute queries ───────────────────────────────────────────
$httpCode = 200;
try {
    $pdo = ApiDatabase::getInstance();

    // Total record count (for pagination meta)
    $countSQL  = "SELECT COUNT(*) AS total
                  FROM complaints c
                  LEFT JOIN branches       b ON c.branch_id = b.id
                  LEFT JOIN complaint_types t ON c.type_id  = t.id
                  $whereSQL";
    $stmtCount = $pdo->prepare($countSQL);
    $stmtCount->execute($params);
    $totalRecords = (int) $stmtCount->fetchColumn();
    $totalPages   = (int) ceil($totalRecords / $limit);

    // Data query — LIMIT/OFFSET injected as integers after validation
    $offset  = ($page - 1) * $limit;
    $dataSQL = "SELECT
                    c.id,
                    c.complaint_id,
                    c.name,
                    c.address,
                    c.contact,
                    c.email,
                    c.subject,
                    c.description,
                    c.priority,
                    c.status,
                    c.file_path,
                    c.created_at,
                    c.updated_at,
                    c.resolved_at,
                    b.branch_name  AS department,
                    t.type_name    AS category,
                    u.name         AS assigned_to
                FROM complaints c
                LEFT JOIN branches        b ON c.branch_id  = b.id
                LEFT JOIN complaint_types t ON c.type_id    = t.id
                LEFT JOIN users           u ON c.assigned_to = u.id
                $whereSQL
                ORDER BY c.created_at DESC
                LIMIT :limit OFFSET :offset";

    // With EMULATE_PREPARES=true, inject LIMIT/OFFSET directly as integers (safe — already validated)
    $dataSQL  = str_replace(':limit',  (int) $limit,  $dataSQL);
    $dataSQL  = str_replace(':offset', (int) $offset, $dataSQL);

    $stmtData = $pdo->prepare($dataSQL);

    // Bind named params from filters
    foreach ($params as $key => $val) {
        $stmtData->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmtData->execute();

    $complaints = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    // Sanitize output
    $data = array_map(function (array $row): array {
        return [
            'id'           => (int) $row['id'],
            'complaint_id' => htmlspecialchars($row['complaint_id'],      ENT_QUOTES, 'UTF-8'),
            'name'         => htmlspecialchars($row['name'],              ENT_QUOTES, 'UTF-8'),
            'address'      => htmlspecialchars($row['address'] ?? '',     ENT_QUOTES, 'UTF-8'),
            'contact'      => htmlspecialchars($row['contact'] ?? '',     ENT_QUOTES, 'UTF-8'),
            'email'        => htmlspecialchars($row['email'] ?? '',       ENT_QUOTES, 'UTF-8'),
            'subject'      => htmlspecialchars($row['subject'],           ENT_QUOTES, 'UTF-8'),
            'description'  => htmlspecialchars($row['description'],       ENT_QUOTES, 'UTF-8'),
            'priority'     => $row['priority'],
            'status'       => $row['status'],
            'has_document' => !empty($row['file_path']),
            'department'   => htmlspecialchars($row['department'] ?? '',  ENT_QUOTES, 'UTF-8'),
            'category'     => htmlspecialchars($row['category'] ?? '',    ENT_QUOTES, 'UTF-8'),
            'assigned_to'  => htmlspecialchars($row['assigned_to'] ?? '', ENT_QUOTES, 'UTF-8'),
            'created_at'   => $row['created_at'],
            'updated_at'   => $row['updated_at'],
            'resolved_at'  => $row['resolved_at'],
        ];
    }, $complaints);

    $response = [
        'success'       => true,
        'message'       => 'Data Retrieved Successfully',
        'page'          => $page,
        'limit'         => $limit,
        'total_records' => $totalRecords,
        'total_pages'   => $totalPages,
        'filters'       => [
            'status'     => $status,
            'priority'   => $priority,
            'category'   => $category,
            'department' => $department,
            'search'     => $search,
            'from_date'  => $fromDate,
            'to_date'    => $toDate,
        ],
        'data' => $data,
    ];

} catch (Exception $e) {
    $httpCode = 500;
    ApiLogger::error('complaints.php', $e->getMessage());
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
