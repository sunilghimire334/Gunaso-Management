<?php
/**
 * ============================================================
 * Gunaso REST API – Single Complaint Detail
 * बेसीशहर नगरपालिका
 * ============================================================
 * File    : api/complaint.php
 * Method  : GET
 * Endpoint: /api/complaint.php?id=123
 *           /api/complaint.php?tracking=GUN-26-0530-001
 *
 * Parameters (one required):
 *   id        int     Internal database ID
 *   tracking  string  Public tracking number (complaint_id)
 *
 * Response includes:
 *   - Full complaint detail
 *   - Status change history (complaint_logs)
 *   - Assigned employee name
 *   - Branch and type names
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

// ── Validate input parameters ─────────────────────────────────
$idRaw       = $_GET['id']       ?? null;
$trackingRaw = $_GET['tracking'] ?? null;

$useId       = false;
$useTracking = false;
$lookupId    = 0;
$lookupTrack = '';

if ($idRaw !== null && $idRaw !== '') {
    $intVal = filter_var($idRaw, FILTER_VALIDATE_INT);
    if ($intVal === false || $intVal <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameter: id must be a positive integer.']);
        ApiLogger::finish(400, $keyId);
        exit;
    }
    $useId    = true;
    $lookupId = (int) $intVal;
} elseif ($trackingRaw !== null && $trackingRaw !== '') {
    // Tracking number: alphanumeric + hyphens, 5-30 chars
    $clean = strip_tags(trim($trackingRaw));
    if (!preg_match('/^[A-Za-z0-9\-]{5,30}$/', $clean)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameter: tracking number format is incorrect.']);
        ApiLogger::finish(400, $keyId);
        exit;
    }
    $useTracking  = true;
    $lookupTrack  = strtoupper($clean);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing parameter. Provide either ?id=<int> or ?tracking=<tracking_no>.',
    ]);
    ApiLogger::finish(400, $keyId);
    exit;
}

// ── Query ─────────────────────────────────────────────────────
$httpCode = 200;
try {
    $pdo = ApiDatabase::getInstance();

    // Build lookup condition
    if ($useId) {
        $condition = 'c.id = :lookup';
        $bindVal   = $lookupId;
        $bindType  = PDO::PARAM_INT;
    } else {
        $condition = 'c.complaint_id = :lookup';
        $bindVal   = $lookupTrack;
        $bindType  = PDO::PARAM_STR;
    }

    // Main complaint query
    $stmt = $pdo->prepare(
        "SELECT
            c.id,
            c.complaint_id,
            c.name,
            c.address,
            c.contact,
            c.email,
            c.subject,
            c.description,
            c.file_path,
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
         WHERE $condition
         LIMIT 1"
    );
    $stmt->bindValue(':lookup', $bindVal, $bindType);
    $stmt->execute();

    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$complaint) {
        $httpCode = 404;
        $response = [
            'success' => false,
            'message' => 'Complaint not found.',
        ];
    } else {
        // Fetch status history from complaint_logs
        $stmtLogs = $pdo->prepare(
            "SELECT
                cl.id,
                cl.status,
                cl.remarks,
                cl.admin_reply,
                cl.created_at,
                u.name AS updated_by
             FROM complaint_logs cl
             LEFT JOIN users u ON cl.updated_by = u.id
             WHERE cl.complaint_id = :cid
             ORDER BY cl.created_at ASC"
        );
        $stmtLogs->execute([':cid' => (int) $complaint['id']]);
        $rawLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        $logs = array_map(function (array $log): array {
            return [
                'id'         => (int) $log['id'],
                'status'     => $log['status'],
                'remarks'    => htmlspecialchars($log['remarks'] ?? '',     ENT_QUOTES, 'UTF-8'),
                'reply'      => htmlspecialchars($log['admin_reply'] ?? '', ENT_QUOTES, 'UTF-8'),
                'updated_by' => htmlspecialchars($log['updated_by'] ?? '',  ENT_QUOTES, 'UTF-8'),
                'updated_at' => $log['created_at'],
            ];
        }, $rawLogs);

        // Sanitize complaint fields
        $response = [
            'success' => true,
            'message' => 'Data Retrieved Successfully',
            'data'    => [
                'id'           => (int) $complaint['id'],
                'complaint_id' => htmlspecialchars($complaint['complaint_id'],      ENT_QUOTES, 'UTF-8'),
                'name'         => htmlspecialchars($complaint['name'],              ENT_QUOTES, 'UTF-8'),
                'address'      => htmlspecialchars($complaint['address'] ?? '',     ENT_QUOTES, 'UTF-8'),
                'contact'      => htmlspecialchars($complaint['contact'] ?? '',     ENT_QUOTES, 'UTF-8'),
                'email'        => htmlspecialchars($complaint['email'] ?? '',       ENT_QUOTES, 'UTF-8'),
                'subject'      => htmlspecialchars($complaint['subject'],           ENT_QUOTES, 'UTF-8'),
                'description'  => htmlspecialchars($complaint['description'],       ENT_QUOTES, 'UTF-8'),
                'priority'     => $complaint['priority'],
                'status'       => $complaint['status'],
                'has_document' => !empty($complaint['file_path']),
                'document_url' => !empty($complaint['file_path'])
                                  ? API_SITE_URL . '/' . ltrim($complaint['file_path'], '/')
                                  : null,
                'department'   => htmlspecialchars($complaint['department'] ?? '',  ENT_QUOTES, 'UTF-8'),
                'category'     => htmlspecialchars($complaint['category'] ?? '',    ENT_QUOTES, 'UTF-8'),
                'assigned_to'  => htmlspecialchars($complaint['assigned_to'] ?? '', ENT_QUOTES, 'UTF-8'),
                'created_at'   => $complaint['created_at'],
                'updated_at'   => $complaint['updated_at'],
                'resolved_at'  => $complaint['resolved_at'],
                'history'      => $logs,
            ],
        ];
    }

} catch (Exception $e) {
    $httpCode = 500;
    ApiLogger::error('complaint.php', $e->getMessage());
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
