<?php
require_once '../config.php';
check_login();

// Only admin can access
if (!is_admin()) {
    redirect('admin/dashboard.php');
}

// Date range filter
$start_date = isset($_GET['start_date']) ? sanitize_input($_GET['start_date']) : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? sanitize_input($_GET['end_date']) : date('Y-m-d');

// Overall Statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
$stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'pending' AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
$stats['in_progress'] = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'in-progress' AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
$stats['resolved'] = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'resolved' AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];
$stats['rejected'] = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'rejected' AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'")->fetch_assoc()['count'];

// Branch-wise Statistics
$branch_stats = $conn->query("SELECT b.branch_name, COUNT(c.id) as count 
                              FROM branches b 
                              LEFT JOIN complaints c ON b.id = c.branch_id 
                              AND DATE(c.created_at) BETWEEN '$start_date' AND '$end_date'
                              GROUP BY b.id 
                              ORDER BY count DESC");

// Type-wise Statistics
$type_stats = $conn->query("SELECT t.type_name, COUNT(c.id) as count 
                            FROM complaint_types t 
                            LEFT JOIN complaints c ON t.id = c.type_id 
                            AND DATE(c.created_at) BETWEEN '$start_date' AND '$end_date'
                            GROUP BY t.id 
                            ORDER BY count DESC");

// Employee Performance
$employee_stats = $conn->query("SELECT u.name, 
                                COUNT(c.id) as total_assigned,
                                SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                                SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending,
                                SUM(CASE WHEN c.status = 'in-progress' THEN 1 ELSE 0 END) as in_progress
                                FROM users u 
                                LEFT JOIN complaints c ON u.id = c.assigned_to 
                                AND DATE(c.created_at) BETWEEN '$start_date' AND '$end_date'
                                WHERE u.role = 'employee' AND u.status = 'active'
                                GROUP BY u.id 
                                ORDER BY total_assigned DESC");

// Daily Trend
$daily_trend = $conn->query("SELECT DATE(created_at) as date, COUNT(*) as count 
                             FROM complaints 
                             WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
                             GROUP BY DATE(created_at) 
                             ORDER BY date ASC");
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.png" type="image/png">
    <title>रिपोर्ट र विश्लेषण - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #1e40af;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f3f4f6;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), #1e3a8a);
            color: white;
            padding: 1.5rem 0;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-brand {
            padding: 0 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu li a:hover,
        .sidebar-menu li a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-menu li a i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }
        
        .content-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .stat-box {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 4px solid var(--primary-color);
        }
        
        .stat-box h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        @media print {
            .sidebar, .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <h4>बेसीशहर नगरपालिका</h4>
            <small>गुनासो व्यवस्थापन</small>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> ड्यासबोर्ड</a></li>
            <li><a href="complaints.php"><i class="bi bi-file-earmark-text"></i> सबै गुनासो</a></li>
                        <li><a href="/submit.php"><i class="bi bi-plus-circle"></i><span>गुनासो दर्ता</span></a></li>
            <li><a href="employees.php"><i class="bi bi-people"></i> कर्मचारी व्यवस्थापन</a></li>
            <li><a href="branches.php"><i class="bi bi-building"></i> शाखा व्यवस्थापन</a></li>
            <li><a href="types.php"><i class="bi bi-tags"></i> गुनासो प्रकार</a></li>
            <li><a href="reports.php" class="active"><i class="bi bi-bar-chart"></i> रिपोर्ट</a></li>
            <li><a href="settings.php"><i class="bi bi-gear"></i> सेटिङ्ग</a></li>
            <li><a href="profile.php"><i class="bi bi-person"></i> मेरो प्रोफाइल</a></li>
            <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> लगआउट</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h3 class="text-primary mb-1">रिपोर्ट र विश्लेषण</h3>
                <p class="text-muted mb-0">गुनासो तथ्याङ्क र विश्लेषण</p>
            </div>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> प्रिन्ट गर्नुहोस्
            </button>
        </div>

        <!-- Date Filter -->
        <div class="content-card no-print">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">शुरु मिति</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">अन्त्य मिति</label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter"></i> फिल्टर गर्नुहोस्
                    </button>
                </div>
            </form>
        </div>

        <!-- Report Header -->
        <div class="text-center mb-4">
            <h4>बेसीशहर नगरपालिका</h4>
            <h5>गुनासो व्यवस्थापन रिपोर्ट</h5>
            <p>अवधि: <?php echo date('Y/m/d', strtotime($start_date)); ?> देखि <?php echo date('Y/m/d', strtotime($end_date)); ?> सम्म</p>
        </div>

        <!-- Overall Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-box" style="border-top-color: #3b82f6;">
                    <i class="bi bi-file-earmark-text" style="font-size: 2rem; color: #3b82f6;"></i>
                    <h3><?php echo $stats['total']; ?></h3>
                    <p class="text-muted mb-0">कुल गुनासो</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box" style="border-top-color: #f59e0b;">
                    <i class="bi bi-hourglass-split" style="font-size: 2rem; color: #f59e0b;"></i>
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p class="text-muted mb-0">विचाराधीन</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box" style="border-top-color: #06b6d4;">
                    <i class="bi bi-arrow-repeat" style="font-size: 2rem; color: #06b6d4;"></i>
                    <h3><?php echo $stats['in_progress']; ?></h3>
                    <p class="text-muted mb-0">प्रगतिमा</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box" style="border-top-color: #10b981;">
                    <i class="bi bi-check-circle" style="font-size: 2rem; color: #10b981;"></i>
                    <h3><?php echo $stats['resolved']; ?></h3>
                    <p class="text-muted mb-0">समाधान भएको</p>
                </div>
            </div>
        </div>

        <!-- Resolution Rate -->
        <div class="content-card">
            <h5 class="text-primary mb-3">समाधान दर</h5>
            <?php 
            $resolution_rate = $stats['total'] > 0 ? round(($stats['resolved'] / $stats['total']) * 100, 2) : 0;
            ?>
            <div class="progress" style="height: 30px;">
                <div class="progress-bar bg-success" style="width: <?php echo $resolution_rate; ?>%;">
                    <?php echo $resolution_rate; ?>% (<?php echo $stats['resolved']; ?>/<?php echo $stats['total']; ?>)
                </div>
            </div>
        </div>

        <!-- Branch-wise Report -->
        <div class="content-card">
            <h5 class="text-primary mb-3">शाखा अनुसार गुनासो</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>शाखा</th>
                            <th class="text-center">गुनासो संख्या</th>
                            <th>प्रतिशत</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($branch = $branch_stats->fetch_assoc()): ?>
                            <?php $percentage = $stats['total'] > 0 ? round(($branch['count'] / $stats['total']) * 100, 2) : 0; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($branch['branch_name']); ?></td>
                                <td class="text-center"><strong><?php echo $branch['count']; ?></strong></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?php echo $percentage; ?>%;">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Type-wise Report -->
        <div class="content-card">
            <h5 class="text-primary mb-3">प्रकार अनुसार गुनासो</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>गुनासो प्रकार</th>
                            <th class="text-center">गुनासो संख्या</th>
                            <th>प्रतिशत</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($type = $type_stats->fetch_assoc()): ?>
                            <?php $percentage = $stats['total'] > 0 ? round(($type['count'] / $stats['total']) * 100, 2) : 0; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($type['type_name']); ?></td>
                                <td class="text-center"><strong><?php echo $type['count']; ?></strong></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-info" style="width: <?php echo $percentage; ?>%;">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Employee Performance -->
        <div class="content-card">
            <h5 class="text-primary mb-3">कर्मचारी कार्यसम्पादन</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>कर्मचारी</th>
                            <th class="text-center">कुल</th>
                            <th class="text-center">समाधान</th>
                            <th class="text-center">प्रगतिमा</th>
                            <th class="text-center">विचाराधीन</th>
                            <th class="text-center">समाधान दर</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($emp = $employee_stats->fetch_assoc()): ?>
                            <?php $emp_resolution = $emp['total_assigned'] > 0 ? round(($emp['resolved'] / $emp['total_assigned']) * 100, 2) : 0; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($emp['name']); ?></td>
                                <td class="text-center"><strong><?php echo $emp['total_assigned']; ?></strong></td>
                                <td class="text-center"><span class="badge bg-success"><?php echo $emp['resolved']; ?></span></td>
                                <td class="text-center"><span class="badge bg-info"><?php echo $emp['in_progress']; ?></span></td>
                                <td class="text-center"><span class="badge bg-warning"><?php echo $emp['pending']; ?></span></td>
                                <td class="text-center">
                                    <strong><?php echo $emp_resolution; ?>%</strong>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Daily Trend -->
        <div class="content-card">
            <h5 class="text-primary mb-3">दैनिक प्रवृत्ति</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>मिति</th>
                            <th class="text-center">गुनासो संख्या</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($day = $daily_trend->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('Y/m/d', strtotime($day['date'])); ?></td>
                                <td class="text-center">
                                    <strong><?php echo $day['count']; ?></strong>
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" style="width: <?php echo min(100, ($day['count'] / max(1, $stats['total'])) * 500); ?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4">
            <small class="text-muted">
                रिपोर्ट उत्पन्न मिति: <?php echo date('Y/m/d H:i:s'); ?>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>