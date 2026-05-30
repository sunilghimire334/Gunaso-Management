<?php
require_once '../config.php';
check_login();

// Only admin can access
if (!is_admin()) {
    redirect('admin/dashboard.php');
}

$success = '';
$error = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settings = [
        'office_name' => sanitize_input($_POST['office_name']),
        'office_address' => sanitize_input($_POST['office_address']),
        'office_phone' => sanitize_input($_POST['office_phone']),
        'office_email' => sanitize_input($_POST['office_email']),
        'email_notification' => isset($_POST['email_notification']) ? '1' : '0',
        'complaint_id_prefix' => sanitize_input($_POST['complaint_id_prefix']),
        'max_file_size' => intval($_POST['max_file_size'])
    ];
    
    $updated = 0;
    foreach ($settings as $key => $value) {
        $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        if ($stmt->execute()) {
            $updated++;
        }
    }
    
    if ($updated > 0) {
        $success = 'सेटिङ्ग सफलतापूर्वक अद्यावधिक गरियो।';
    } else {
        $error = 'सेटिङ्ग अद्यावधिक गर्दा त्रुटि भयो।';
    }
}

// Fetch current settings
$settings_query = $conn->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settings_query->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.png" type="image/png">
    <title>प्रणाली सेटिङ्ग - Admin Panel</title>
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
        
        .setting-section {
            border-left: 4px solid var(--primary-color);
            padding-left: 1rem;
            margin-bottom: 2rem;
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
            <li><a href="reports.php"><i class="bi bi-bar-chart"></i> रिपोर्ट</a></li>
            <li><a href="settings.php" class="active"><i class="bi bi-gear"></i> सेटिङ्ग</a></li>
            <li><a href="profile.php"><i class="bi bi-person"></i> मेरो प्रोफाइल</a></li>
            <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> लगआउट</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="mb-4">
            <h3 class="text-primary mb-1">प्रणाली सेटिङ्ग</h3>
            <p class="text-muted mb-0">प्रणालीको विन्यास र सेटिङ्ग परिवर्तन गर्नुहोस्</p>
        </div>

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

        <form method="POST">
            <!-- Office Information -->
            <div class="content-card">
                <div class="setting-section">
                    <h5 class="text-primary mb-3">
                        <i class="bi bi-building"></i> कार्यालय जानकारी
                    </h5>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">कार्यालयको नाम</label>
                        <input type="text" name="office_name" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['office_name'] ?? 'बेसीशहर नगरपालिका'); ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">ठेगाना</label>
                        <input type="text" name="office_address" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['office_address'] ?? 'बेसीशहर, लमजुङ'); ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">फोन नम्बर</label>
                        <input type="text" name="office_phone" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['office_phone'] ?? '065-560322'); ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">इमेल ठेगाना</label>
                        <input type="email" name="office_email" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['office_email'] ?? 'info@besishahar.gov.np'); ?>">
                    </div>
                </div>
            </div>

            <!-- System Configuration -->
            <div class="content-card">
                <div class="setting-section">
                    <h5 class="text-primary mb-3">
                        <i class="bi bi-sliders"></i> प्रणाली विन्यास
                    </h5>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">गुनासो ID उपसर्ग</label>
                        <input type="text" name="complaint_id_prefix" class="form-control" 
                               value="<?php echo htmlspecialchars($settings['complaint_id_prefix'] ?? 'GUN'); ?>"
                               maxlength="10">
                        <small class="text-muted">जस्तै: GUN-25-1008-001</small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">अधिकतम फाइल साइज (MB)</label>
                        <input type="number" name="max_file_size" class="form-control" 
                               value="<?php echo ($settings['max_file_size'] ?? 5242880) / 1048576; ?>"
                               min="1" max="10">
                        <small class="text-muted">१ MB देखि १० MB सम्म</small>
                    </div>
                    
                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="email_notification" 
                                   id="email_notification" 
                                   <?php echo ($settings['email_notification'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="email_notification">
                                इमेल सूचना सक्षम गर्नुहोस्
                            </label>
                        </div>
                        <small class="text-muted">गुनासो स्थिति परिवर्तन हुँदा नागरिकलाई इमेल पठाइनेछ</small>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="content-card">
                <div class="setting-section">
                    <h5 class="text-primary mb-3">
                        <i class="bi bi-info-circle"></i> प्रणाली जानकारी
                    </h5>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>प्रणाली संस्करण:</strong>
                            <span class="text-muted">१.०</span>
                        </div>
                        <div class="mb-3">
                            <strong>PHP संस्करण:</strong>
                            <span class="text-muted"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>MySQL संस्करण:</strong>
                            <span class="text-muted"><?php echo $conn->server_info; ?></span>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong>डेटाबेस नाम:</strong>
                            <span class="text-muted"><?php echo DB_NAME; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>अपलोड डाइरेक्टरी:</strong>
                            <span class="text-muted"><?php echo UPLOAD_DIR; ?></span>
                        </div>
                        <div class="mb-3">
                            <strong>समय क्षेत्र:</strong>
                            <span class="text-muted">Asia/Kathmandu</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Statistics -->
            <div class="content-card">
                <div class="setting-section">
                    <h5 class="text-primary mb-3">
                        <i class="bi bi-database"></i> डेटाबेस तथ्याङ्क
                    </h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>तालिका</th>
                                <th class="text-center">रेकर्ड संख्या</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $tables = ['complaints' => 'गुनासो', 'users' => 'प्रयोगकर्ता', 'branches' => 'शाखा', 
                                      'complaint_types' => 'गुनासो प्रकार', 'complaint_logs' => 'लग रेकर्ड'];
                            foreach ($tables as $table => $label):
                                $count = $conn->query("SELECT COUNT(*) as count FROM $table")->fetch_assoc()['count'];
                            ?>
                            <tr>
                                <td><?php echo $label; ?></td>
                                <td class="text-center"><strong><?php echo $count; ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Maintenance -->
            <div class="content-card">
                <div class="setting-section">
                    <h5 class="text-primary mb-3">
                        <i class="bi bi-tools"></i> मर्मत सम्भार
                    </h5>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>सुझाव:</strong> नियमित रूपमा डेटाबेस ब्याकअप लिनुहोस् र प्रणाली लग जाँच गर्नुहोस्।
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary" onclick="alert('डेटाबेस ब्याकअप सुविधा आउँदै छ।')">
                        <i class="bi bi-download"></i> डेटाबेस ब्याकअप
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="if(confirm('के तपाईं पुरानो लगहरू हटाउन चाहनुहुन्छ?')) alert('लग सफा गर्ने सुविधा आउँदै छ।')">
                        <i class="bi bi-trash"></i> पुरानो लग सफा गर्नुहोस्
                    </button>
                </div>
            </div>

            <!-- Save Button -->
            <div class="text-center">
                <button type="submit" name="update_settings" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-check-circle"></i> सेटिङ्ग सुरक्षित गर्नुहोस्
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Convert MB to bytes before submit
        document.querySelector('form').addEventListener('submit', function(e) {
            var fileSize = document.querySelector('input[name="max_file_size"]');
            fileSize.value = fileSize.value * 1048576; // Convert MB to bytes
        });
    </script>
</body>
</html>