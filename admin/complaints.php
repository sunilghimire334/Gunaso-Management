<?php
require_once '../config.php';
check_login();

$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$branch_filter = isset($_GET['branch']) ? intval($_GET['branch']) : 0;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Handle export to Excel - this should be in a separate file ideally
if (isset($_POST['export_excel'])) {
    $where = [];
    if (!is_admin()) {
        $where[] = "c.assigned_to = {$_SESSION['user_id']}";
    }
    if (!empty($status_filter)) {
        $where[] = "c.status = '$status_filter'";
    }
    if ($branch_filter > 0) {
        $where[] = "c.branch_id = $branch_filter";
    }
    if (!empty($search)) {
        $where[] = "(c.complaint_id LIKE '%$search%' OR c.name LIKE '%$search%' OR c.subject LIKE '%$search%')";
    }
    
    // Add date range filter
    if (!empty($_POST['from_date'])) {
        $from_date = sanitize_input($_POST['from_date']);
        $where[] = "DATE(c.created_at) >= '$from_date'";
    }
    if (!empty($_POST['to_date'])) {
        $to_date = sanitize_input($_POST['to_date']);
        $where[] = "DATE(c.created_at) <= '$to_date'";
    }
    
    $where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $query = "SELECT c.*, b.branch_name, t.type_name, u.name as assigned_to_name 
              FROM complaints c 
              LEFT JOIN branches b ON c.branch_id = b.id 
              LEFT JOIN complaint_types t ON c.type_id = t.id 
              LEFT JOIN users u ON c.assigned_to = u.id 
              $where_clause 
              ORDER BY c.created_at DESC";
    
    $result = $conn->query($query);
    
    // Create Excel file
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="complaints_' . date('Y-m-d_H-i-s') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr style='background-color: #1e40af; color: white;'>";
    echo "<th>ट्र्याकिङ नं.</th>";
    echo "<th>नाम</th>";
    echo "<th>विषय</th>";
    echo "<th>शाखा</th>";
    echo "<th>प्रकार</th>";
    echo "<th>प्राथमिकता</th>";
    echo "<th>स्थिति</th>";
    if (is_admin()) {
        echo "<th>जिम्मेवारी</th>";
    }
    echo "<th>मिति</th>";
    echo "<th>विवरण</th>";
    echo "</tr>";
    
    while ($complaint = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($complaint['complaint_id']) . "</td>";
        echo "<td>" . htmlspecialchars($complaint['name']) . "</td>";
        echo "<td>" . htmlspecialchars($complaint['subject']) . "</td>";
        echo "<td>" . htmlspecialchars($complaint['branch_name']) . "</td>";
        echo "<td>" . htmlspecialchars($complaint['type_name']) . "</td>";
        echo "<td>" . htmlspecialchars($complaint['priority']) . "</td>";
        echo "<td>" . htmlspecialchars($complaint['status']) . "</td>";
        if (is_admin()) {
            echo "<td>" . ($complaint['assigned_to_name'] ? htmlspecialchars($complaint['assigned_to_name']) : 'असाइन नगरिएको') . "</td>";
        }
        echo "<td>" . date('Y-m-d', strtotime($complaint['created_at'])) . "</td>";
        echo "<td>" . htmlspecialchars($complaint['description']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
}

$where = [];
if (!is_admin()) {
    $where[] = "c.assigned_to = {$_SESSION['user_id']}";
}
if (!empty($status_filter)) {
    $where[] = "c.status = '$status_filter'";
}
if ($branch_filter > 0) {
    $where[] = "c.branch_id = $branch_filter";
}
if (!empty($search)) {
    $where[] = "(c.complaint_id LIKE '%$search%' OR c.name LIKE '%$search%' OR c.subject LIKE '%$search%')";
}

$where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_query = "SELECT COUNT(*) as total FROM complaints c $where_clause";
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

$query = "SELECT c.*, b.branch_name, t.type_name, u.name as assigned_to_name 
          FROM complaints c 
          LEFT JOIN branches b ON c.branch_id = b.id 
          LEFT JOIN complaint_types t ON c.type_id = t.id 
          LEFT JOIN users u ON c.assigned_to = u.id 
          $where_clause 
          ORDER BY c.created_at DESC 
          LIMIT $per_page OFFSET $offset";

$complaints = $conn->query($query);
$branches = $conn->query("SELECT * FROM branches WHERE status = 'active' ORDER BY branch_name");
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.png" type="image/png">
    <title>गुनासो व्यवस्थापन - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --primary-color: #1e40af; --sidebar-width: 250px; }
        body { font-family: 'Poppins', Arial, sans-serif; background-color: #f3f4f6; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: var(--sidebar-width); background: linear-gradient(180deg, var(--primary-color), #1e3a8a); color: white; padding: 1.5rem 0; overflow-y: auto; z-index: 1000; }
        .sidebar-brand { padding: 0 1.5rem; margin-bottom: 2rem; text-align: center; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li a { display: flex; align-items: center; padding: 0.75rem 1.5rem; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; }
        .sidebar-menu li a:hover, .sidebar-menu li a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-menu li a i { margin-right: 0.75rem; font-size: 1.2rem; }
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; }
        .top-navbar { background: white; padding: 1rem 2rem; margin: -2rem -2rem 2rem -2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .content-card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .filter-section { background: #f9fafb; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .table-hover tbody tr:hover { background-color: #f9fafb; cursor: pointer; }
        .pagination { margin-top: 1.5rem; }
        .action-buttons { margin-bottom: 1.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand"><h4>बेसीशहर नगरपालिका</h4><small>गुनासो व्यवस्थापन</small></div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> ड्यासबोर्ड</a></li>
            <li><a href="complaints.php" class="active"><i class="bi bi-file-earmark-text"></i> सबै गुनासो</a></li>
                        <li><a href="/submit.php"><i class="bi bi-plus-circle"></i><span>गुनासो दर्ता</span></a></li>
            <?php if (is_admin()): ?>
            <li><a href="employees.php"><i class="bi bi-people"></i> कर्मचारी व्यवस्थापन</a></li>
            <li><a href="branches.php"><i class="bi bi-building"></i> शाखा व्यवस्थापन</a></li>
            <li><a href="types.php"><i class="bi bi-tags"></i> गुनासो प्रकार</a></li>
            <li><a href="reports.php"><i class="bi bi-bar-chart"></i> रिपोर्ट</a></li>
            <li><a href="settings.php"><i class="bi bi-gear"></i> सेटिङ्ग</a></li>
            <?php endif; ?>
            <li><a href="profile.php"><i class="bi bi-person"></i> मेरो प्रोफाइल</a></li>
            <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> लगआउट</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <h4 class="mb-0">गुनासो व्यवस्थापन</h4>
            <small class="text-muted">कुल <?php echo $total_records; ?> गुनासो फेला पर्यो</small>
        </div>

        <div class="content-card">
            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="button" class="btn btn-success" id="exportExcelBtn">
                    <i class="bi bi-file-earmark-excel"></i> Excel मा निर्यात गर्नुहोस्
                </button>
            </div>

            <form method="GET" class="filter-section">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">स्थिति</label>
                        <select name="status" class="form-select">
                            <option value="">सबै</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>विचाराधीन</option>
                            <option value="in-progress" <?php echo $status_filter == 'in-progress' ? 'selected' : ''; ?>>प्रगतिमा</option>
                            <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>समाधान भएको</option>
                            <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>अस्वीकृत</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">शाखा</label>
                        <select name="branch" class="form-select">
                            <option value="0">सबै</option>
                            <?php while ($branch = $branches->fetch_assoc()): ?>
                                <option value="<?php echo $branch['id']; ?>" <?php echo $branch_filter == $branch['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">खोज्नुहोस्</label>
                        <input type="text" name="search" class="form-control" placeholder="ट्र्याकिङ नं., नाम, विषय..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> खोज्नुहोस्</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive mt-3">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ट्र्याकिङ नं.</th>
                            <th>नाम</th>
                            <th>विषय</th>
                            <th>शाखा</th>
                            <th>प्रकार</th>
                            <th>प्राथमिकता</th>
                            <th>स्थिति</th>
                            <?php if (is_admin()): ?><th>जिम्मेवारी</th><?php endif; ?>
                            <th>मिति</th>
                            <th>कार्य</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($complaints->num_rows > 0): ?>
                            <?php while ($complaint = $complaints->fetch_assoc()): ?>
                                <tr onclick="window.location='complaint_detail.php?id=<?php echo $complaint['id']; ?>'">
                                    <td><strong class="text-primary"><?php echo htmlspecialchars($complaint['complaint_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($complaint['name']); ?></td>
                                    <td><div style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($complaint['subject']); ?></div></td>
                                    <td><small><?php echo htmlspecialchars($complaint['branch_name']); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($complaint['type_name']); ?></small></td>
                                    <td><?php echo get_priority_badge($complaint['priority']); ?></td>
                                    <td><?php echo get_status_badge($complaint['status']); ?></td>
                                    <?php if (is_admin()): ?>
                                    <td><small><?php echo $complaint['assigned_to_name'] ? htmlspecialchars($complaint['assigned_to_name']) : '<span class="text-muted">असाइन नगरिएको</span>'; ?></small></td>
                                    <?php endif; ?>
                                    <td><small><?php echo date('Y/m/d', strtotime($complaint['created_at'])); ?></small></td>
                                    <td onclick="event.stopPropagation()">
                                        <a href="complaint_detail.php?id=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-outline-primary" title="विवरण"><i class="bi bi-eye"></i></a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="<?php echo is_admin() ? '10' : '9'; ?>" class="text-center text-muted py-4"><i class="bi bi-inbox" style="font-size: 3rem;"></i><p class="mt-2">कुनै गुनासो फेला परेन</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&branch=<?php echo $branch_filter; ?>&search=<?php echo urlencode($search); ?>">पहिलो</a>
                        </li>
                        
                        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&branch=<?php echo $branch_filter; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&branch=<?php echo $branch_filter; ?>&search=<?php echo urlencode($search); ?>">अर्को</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Date Range Modal -->
    <div class="modal fade" id="dateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">मिति चयन गर्नुहोस्</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="exportForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="fromDate" class="form-label">मिति देखि</label>
                            <input type="date" class="form-control" id="fromDate" name="from_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="toDate" class="form-label">मिति सम्म</label>
                            <input type="date" class="form-control" id="toDate" name="to_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द गर्नुहोस्</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> निर्यात गर्नुहोस्</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            
            document.getElementById('fromDate').value = thirtyDaysAgo;
            document.getElementById('toDate').value = today;

            document.getElementById('exportExcelBtn').addEventListener('click', function() {
                const modal = new bootstrap.Modal(document.getElementById('dateModal'));
                modal.show();
            });

            document.getElementById('exportForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fromDate = document.getElementById('fromDate').value;
                const toDate = document.getElementById('toDate').value;
                
                if (new Date(fromDate) > new Date(toDate)) {
                    alert('को तारिख सम्म को तारिख देखि भन्दा अगाडि हुन सक्दैन');
                    return;
                }

                const formData = new FormData();
                formData.append('export_excel', '1');
                formData.append('from_date', fromDate);
                formData.append('to_date', toDate);
                formData.append('status', '<?php echo $status_filter; ?>');
                formData.append('branch', '<?php echo $branch_filter; ?>');
                formData.append('search', '<?php echo $search; ?>');

                fetch('', {
                    method: 'POST',
                    body: formData
                }).then(response => response.blob()).then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'complaints_' + new Date().getTime() + '.xls';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    const modal = bootstrap.Modal.getInstance(document.getElementById('dateModal'));
                    modal.hide();
                });
            });
        });
    </script>
</body>
</html>