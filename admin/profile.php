<?php
require_once '../config.php';
check_login();

$success = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("sssi", $name, $email, $phone, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $_SESSION['name'] = $name;
        $success = 'प्रोफाइल सफलतापूर्वक अद्यावधिक गरियो।';
    } else {
        $error = 'प्रोफाइल अद्यावधिक गर्दा त्रुटि भयो।';
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!password_verify($current_password, $user['password'])) {
        $error = 'हालको पासवर्ड गलत छ।';
    } elseif ($new_password !== $confirm_password) {
        $error = 'नयाँ पासवर्ड मिलेन।';
    } elseif (strlen($new_password) < 6) {
        $error = 'पासवर्ड कम्तिमा ६ अक्षरको हुनुपर्छ।';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $success = 'पासवर्ड सफलतापूर्वक परिवर्तन गरियो।';
        } else {
            $error = 'पासवर्ड परिवर्तन गर्दा त्रुटि भयो।';
        }
    }
}

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch user's complaint statistics (if employee)
$user_stats = [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'resolved' => 0
];

if (!is_admin()) {
    $id = $_SESSION['user_id'];
    $user_stats['total'] = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $id")->fetch_assoc()['count'];
    $user_stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $id AND status = 'pending'")->fetch_assoc()['count'];
    $user_stats['in_progress'] = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $id AND status = 'in-progress'")->fetch_assoc()['count'];
    $user_stats['resolved'] = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $id AND status = 'resolved'")->fetch_assoc()['count'];
}
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.png" type="image/png">
    <title>मेरो प्रोफाइल - Admin Panel</title>
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
        
        .profile-header {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: white;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden; /* hides overflow from rounded corners */
            margin: 0 auto 1rem;
            border: 5px solid rgba(255,255,255,0.3);
        }
                
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* keeps aspect ratio and fills the circle */
            display: block;
        }

        .stat-box {
            background: #f9fafb;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
        }
        
        .stat-box h4 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0.5rem 0;
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
            <?php if (is_admin()): ?>
            <li><a href="employees.php"><i class="bi bi-people"></i> कर्मचारी व्यवस्थापन</a></li>
            <li><a href="branches.php"><i class="bi bi-building"></i> शाखा व्यवस्थापन</a></li>
            <li><a href="types.php"><i class="bi bi-tags"></i> गुनासो प्रकार</a></li>
            <li><a href="reports.php"><i class="bi bi-bar-chart"></i> रिपोर्ट</a></li>
            <li><a href="settings.php"><i class="bi bi-gear"></i> सेटिङ्ग</a></li>
            <?php endif; ?>
            <li><a href="profile.php" class="active"><i class="bi bi-person"></i> मेरो प्रोफाइल</a></li>
            <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> लगआउट</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <img src="avatar.png" alt="Profile Photo">
                <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
            </div>
            <h3><?php echo htmlspecialchars($user['name']); ?></h3>
            <p class="mb-0">
                <?php if ($user['role'] === 'admin'): ?>
                    <span class="badge bg-light text-primary">प्रशासक</span>
                <?php else: ?>
                    <span class="badge bg-light text-primary">कर्मचारी</span>
                <?php endif; ?>
                
                <?php if ($user['status'] === 'active'): ?>
                    <span class="badge bg-light text-success">सक्रिय</span>
                <?php else: ?>
                    <span class="badge bg-light text-secondary">निष्क्रिय</span>
                <?php endif; ?>
            </p>
            <small>प्रयोगकर्ता नाम: @<?php echo htmlspecialchars($user['username']); ?></small>
        </div>

        <!-- Performance Stats (for employees) -->
        <?php if (!is_admin()): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-box">
                    <i class="bi bi-file-earmark-text text-primary" style="font-size: 2rem;"></i>
                    <h4><?php echo $user_stats['total']; ?></h4>
                    <small class="text-muted">कुल असाइन</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <i class="bi bi-hourglass-split text-warning" style="font-size: 2rem;"></i>
                    <h4><?php echo $user_stats['pending']; ?></h4>
                    <small class="text-muted">विचाराधीन</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <i class="bi bi-arrow-repeat text-info" style="font-size: 2rem;"></i>
                    <h4><?php echo $user_stats['in_progress']; ?></h4>
                    <small class="text-muted">प्रगतिमा</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-box">
                    <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                    <h4><?php echo $user_stats['resolved']; ?></h4>
                    <small class="text-muted">समाधान</small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Information -->
            <div class="col-md-6">
                <div class="content-card">
                    <h5 class="text-primary mb-3">
                        <i class="bi bi-person-circle"></i> प्रोफाइल जानकारी
                    </h5>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">पुरा नाम</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">प्रयोगकर्ता नाम</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small class="text-muted">प्रयोगकर्ता नाम परिवर्तन गर्न सकिँदैन</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">इमेल ठेगाना</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">फोन नम्बर</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">भूमिका</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo $user['role'] === 'admin' ? 'प्रशासक' : 'कर्मचारी'; ?>" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">खाता सिर्जना मिति</label>
                            <input type="text" class="form-control" 
                                   value="<?php echo date('Y/m/d H:i', strtotime($user['created_at'])); ?>" disabled>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary w-100">
                            <i class="bi bi-check-circle"></i> प्रोफाइल अद्यावधिक गर्नुहोस्
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="col-md-6">
                <div class="content-card">
                    <h5 class="text-primary mb-3">
                        <i class="bi bi-key"></i> पासवर्ड परिवर्तन गर्नुहोस्
                    </h5>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">हालको पासवर्ड</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">नयाँ पासवर्ड</label>
                            <input type="password" name="new_password" class="form-control" 
                                   required minlength="6" id="new_password">
                            <small class="text-muted">कम्तिमा ६ अक्षर</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">नयाँ पासवर्ड पुष्टि गर्नुहोस्</label>
                            <input type="password" name="confirm_password" class="form-control" 
                                   required minlength="6" id="confirm_password">
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>सावधान:</strong> पासवर्ड परिवर्तन गरेपछि पुनः लगइन गर्नुपर्नेछ।
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-warning w-100">
                            <i class="bi bi-shield-lock"></i> पासवर्ड परिवर्तन गर्नुहोस्
                        </button>
                    </form>
                </div>

                <!-- Account Information -->
                <div class="content-card">
                    <h5 class="text-primary mb-3">
                        <i class="bi bi-info-circle"></i> खाता जानकारी
                    </h5>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <strong>अन्तिम लगइन:</strong>
                            <span class="text-muted">
                                <?php echo isset($_SESSION['login_time']) ? date('Y/m/d H:i', $_SESSION['login_time']) : 'N/A'; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <strong>खाता स्थिति:</strong>
                            <span>
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="badge bg-success">सक्रिय</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">निष्क्रिय</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <strong>प्रयोगकर्ता ID:</strong>
                            <span class="text-muted">#<?php echo $user['id']; ?></span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-grid">
                        <a href="logout.php" class="btn btn-outline-danger">
                            <i class="bi bi-box-arrow-right"></i> लगआउट गर्नुहोस्
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity (for employees) -->
        <?php if (!is_admin()): ?>
        <div class="content-card">
            <h5 class="text-primary mb-3">
                <i class="bi bi-clock-history"></i> हालैको गतिविधि
            </h5>
            
            <?php
            $recent_activity = $conn->query("SELECT c.complaint_id, c.subject, c.status, c.updated_at 
                                            FROM complaints c 
                                            WHERE c.assigned_to = {$_SESSION['user_id']} 
                                            ORDER BY c.updated_at DESC 
                                            LIMIT 5");
            ?>
            
            <?php if ($recent_activity->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ट्र्याकिङ नं.</th>
                                <th>विषय</th>
                                <th>स्थिति</th>
                                <th>अद्यावधिक मिति</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                                <tr>
                                    <td><strong class="text-primary"><?php echo htmlspecialchars($activity['complaint_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($activity['subject']); ?></td>
                                    <td><?php echo get_status_badge($activity['status']); ?></td>
                                    <td><small><?php echo date('Y/m/d H:i', strtotime($activity['updated_at'])); ?></small></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> हालसम्म कुनै गतिविधि छैन।
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Security Tips -->
        <div class="content-card">
            <h5 class="text-primary mb-3">
                <i class="bi bi-shield-check"></i> सुरक्षा सुझाव
            </h5>
            
            <ul class="list-unstyled">
                <li class="mb-2">
                    <i class="bi bi-check-circle text-success"></i>
                    नियमित रूपमा आफ्नो पासवर्ड परिवर्तन गर्नुहोस्
                </li>
                <li class="mb-2">
                    <i class="bi bi-check-circle text-success"></i>
                    बलियो पासवर्ड प्रयोग गर्नुहोस् (अक्षर, नम्बर र विशेष चिन्ह)
                </li>
                <li class="mb-2">
                    <i class="bi bi-check-circle text-success"></i>
                    आफ्नो लगइन विवरण कसैसँग साझा नगर्नुहोस्
                </li>
                <li class="mb-2">
                    <i class="bi bi-check-circle text-success"></i>
                    सार्वजनिक कम्प्युटरबाट लगआउट गर्न नबिर्सनुहोस्
                </li>
                <li class="mb-2">
                    <i class="bi bi-check-circle text-success"></i>
                    संदिग्ध गतिविधि देखिएमा तुरुन्त रिपोर्ट गर्नुहोस्
                </li>
            </ul>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            var newPassword = document.getElementById('new_password').value;
            var confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('पासवर्ड मिलेन');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>