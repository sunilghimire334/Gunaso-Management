<?php
require_once '../config.php';
check_login();

$stats = [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'today' => 0
];

// Check if user is admin or employee
if (is_admin()) {
    // Admin sees all complaints
    $result = $conn->query("SELECT status, COUNT(*) as count FROM complaints GROUP BY status");
    while ($row = $result->fetch_assoc()) {
        $key = $row['status'] == 'in-progress' ? 'in_progress' : $row['status'];
        $stats[$key] = $row['count'];
    }
    $stats['total'] = array_sum(array_diff_key($stats, ['today' => 0]));

    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE DATE(created_at) = '$today'");
    $row = $result->fetch_assoc();
    $stats['today'] = $row['count'];
} else {
    // Employee sees only assigned complaints
    $user_id = $_SESSION['user_id'];
    
    $result = $conn->query("SELECT status, COUNT(*) as count FROM complaints WHERE assigned_to = $user_id GROUP BY status");
    while ($row = $result->fetch_assoc()) {
        $key = $row['status'] == 'in-progress' ? 'in_progress' : $row['status'];
        $stats[$key] = $row['count'];
    }
    $stats['total'] = array_sum(array_diff_key($stats, ['today' => 0]));

    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $user_id AND DATE(created_at) = '$today'");
    $row = $result->fetch_assoc();
    $stats['today'] = $row['count'];
}

$recent_query = is_admin() 
    ? "SELECT c.*, b.branch_name, t.type_name FROM complaints c 
       LEFT JOIN branches b ON c.branch_id = b.id 
       LEFT JOIN complaint_types t ON c.type_id = t.id 
       ORDER BY c.created_at DESC LIMIT 10"
    : "SELECT c.*, b.branch_name, t.type_name FROM complaints c 
       LEFT JOIN branches b ON c.branch_id = b.id 
       LEFT JOIN complaint_types t ON c.type_id = t.id 
       WHERE c.assigned_to = {$_SESSION['user_id']}
       ORDER BY c.created_at DESC LIMIT 10";

$recent_complaints = $conn->query($recent_query);
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.png" type="image/png">
    <title>ड्यासबोर्ड - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --primary-color: #1e40af; --sidebar-width: 250px; }
        body { font-family: 'Poppins', Arial, sans-serif; background-color: #f3f4f6; }
        .sidebar {
            position: fixed; top: 0; left: 0; height: 100vh; width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), #1e3a8a);
            color: white; padding: 1.5rem 0; overflow-y: auto; z-index: 1000;
        }
        .sidebar-brand { padding: 0 1.5rem; margin-bottom: 2rem; text-align: center; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li a {
            display: flex; align-items: center; padding: 0.75rem 1.5rem;
            color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s;
        }
        .sidebar-menu li a:hover, .sidebar-menu li a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-menu li a i { margin-right: 0.75rem; font-size: 1.2rem; }
        .main-content { margin-left: var(--sidebar-width); padding: 2rem; }
        .top-navbar { background: white; padding: 1rem 2rem; margin: -2rem -2rem 2rem -2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .stat-card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card i { font-size: 2.5rem; opacity: 0.7; }
        .stat-card h3 { font-size: 2rem; font-weight: 700; margin: 0.5rem 0; }
        .stat-card.blue { border-left: 4px solid #3b82f6; }
        .stat-card.blue i { color: #3b82f6; }
        .stat-card.yellow { border-left: 4px solid #f59e0b; }
        .stat-card.yellow i { color: #f59e0b; }
        .stat-card.cyan { border-left: 4px solid #06b6d4; }
        .stat-card.cyan i { color: #06b6d4; }
        .stat-card.green { border-left: 4px solid #10b981; }
        .stat-card.green i { color: #10b981; }
        .stat-card.purple { border-left: 4px solid #8b5cf6; }
        .stat-card.purple i { color: #8b5cf6; }
        .content-card { background: white; border-radius: 10px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table-hover tbody tr:hover { background-color: #f9fafb; }
        .user-info { display: flex; align-items: center; gap: 0.5rem; }
        .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        overflow: hidden; /* ensures image fits the circle */
        text-transform: uppercase; /* keeps initials uppercase */
    }

    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover; /* fills circle perfectly without distortion */
        display: block;
    }

    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand"><h4>बेसीशहर नगरपालिका</h4><small>गुनासो व्यवस्थापन</small></div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i><span>ड्यासबोर्ड</span></a></li>
            <li><a href="complaints.php"><i class="bi bi-file-earmark-text"></i><span>सबै गुनासो</span></a></li>
            <li><a href="/submit.php"><i class="bi bi-plus-circle"></i><span>गुनासो दर्ता</span></a></li>
            <?php if (is_admin()): ?>
            <li><a href="employees.php"><i class="bi bi-people"></i><span>कर्मचारी व्यवस्थापन</span></a></li>
            <li><a href="branches.php"><i class="bi bi-building"></i><span>शाखा व्यवस्थापन</span></a></li>
            <li><a href="types.php"><i class="bi bi-tags"></i><span>गुनासो प्रकार</span></a></li>
            <li><a href="reports.php"><i class="bi bi-bar-chart"></i><span>रिपोर्ट</span></a></li>
            <li><a href="settings.php"><i class="bi bi-gear"></i><span>सेटिङ्ग</span></a></li>
            <?php endif; ?>
            <li><a href="profile.php"><i class="bi bi-person"></i><span>मेरो प्रोफाइल</span></a></li>
            <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i><span>लगआउट</span></a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-navbar">
            <div><h4 class="mb-0">ड्यासबोर्ड</h4><small class="text-muted">स्वागत छ, <?php echo htmlspecialchars($_SESSION['name']); ?></small></div>
            <div class="user-info">
                <div class="user-avatar">
                <?php if (file_exists('avatar.png')): ?>
                    <img src="avatar.png" alt="User Avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($_SESSION['name'], 0, 2)); ?>
                <?php endif; ?>
            </div>

                <div class="position-relative">
    <div class="d-flex align-items-center" style="cursor: pointer;" data-bs-toggle="dropdown" aria-expanded="false">
        <div>
            <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
            <small class="text-muted"><?php echo $_SESSION['role'] === 'admin' ? 'प्रशासक' : 'कर्मचारी'; ?></small>
        </div>
        <i class="fas fa-chevron-down ms-2"></i>
    </div>
    
    <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>प्रोफाइल</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>लगआउट</a></li>
    </ul>
</div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card blue">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">कुल गुनासो</small>
                            <h3><?php echo $stats['total']; ?></h3>
                        </div>
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card yellow">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">विचाराधीन</small>
                            <h3><?php echo $stats['pending']; ?></h3>
                        </div>
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card cyan">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">प्रकृयामा</small>
                            <h3><?php echo $stats['in_progress']; ?></h3>
                        </div>
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card green">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">समाधान भएको</small>
                            <h3><?php echo $stats['resolved']; ?></h3>
                        </div>
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card purple">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">आजको गुनासो</small>
                            <h3><?php echo $stats['today']; ?></h3>
                        </div>
                        <i class="bi bi-calendar-day"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-clock-history text-primary"></i> हालसालैका गुनासो</h5>
                <a href="complaints.php" class="btn btn-sm btn-primary">सबै हेर्नुहोस् <i class="bi bi-arrow-right"></i></a>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ट्र्याकिङ नं.</th>
                            <th>नाम</th>
                            <th>विषय</th>
                            <th>शाखा</th>
                            <th>प्रकार</th>
                            <th>स्थिति</th>
                            <th>मिति</th>
                            <th>कार्य</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_complaints->num_rows > 0): ?>
                            <?php while ($complaint = $recent_complaints->fetch_assoc()): ?>
                                <tr>
                                    <td><strong class="text-primary"><?php echo htmlspecialchars($complaint['complaint_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($complaint['name']); ?></td>
                                    <td><div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($complaint['subject']); ?></div></td>
                                    <td><small><?php echo htmlspecialchars($complaint['branch_name']); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($complaint['type_name']); ?></small></td>
                                    <td><?php echo get_status_badge($complaint['status']); ?></td>
                                    <td><small><?php echo date('Y/m/d', strtotime($complaint['created_at'])); ?></small></td>
                                    <td><a href="complaint_detail.php?id=<?php echo $complaint['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-muted"><i class="bi bi-inbox"></i> कुनै गुनासो उपलब्ध छैन</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (is_admin()): ?>
        <div class="row g-4 mt-3">
            <div class="col-md-4">
                <div class="content-card text-center">
                    <i class="bi bi-person-plus" style="font-size: 3rem; color: var(--primary-color);"></i>
                    <h6 class="mt-2">नयाँ कर्मचारी थप्नुहोस्</h6>
                    <a href="employees.php?action=add" class="btn btn-primary btn-sm mt-2"><i class="bi bi-plus-circle"></i> थप्नुहोस्</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="content-card text-center">
                    <i class="bi bi-building" style="font-size: 3rem; color: var(--primary-color);"></i>
                    <h6 class="mt-2">शाखा व्यवस्थापन</h6>
                    <a href="branches.php" class="btn btn-primary btn-sm mt-2"><i class="bi bi-gear"></i> व्यवस्थापन गर्नुहोस्</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="content-card text-center">
                    <i class="bi bi-bar-chart" style="font-size: 3rem; color: var(--primary-color);"></i>
                    <h6 class="mt-2">रिपोर्ट हेर्नुहोस्</h6>
                    <a href="reports.php" class="btn btn-primary btn-sm mt-2"><i class="bi bi-file-earmark-bar-graph"></i> रिपोर्ट हेर्नुहोस्</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>