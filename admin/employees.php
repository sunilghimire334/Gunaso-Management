<?php
require_once '../config.php';
check_login();

// Only admin can access
if (!is_admin()) {
    redirect('admin/dashboard.php');
}

$success = '';
$error = '';

// Handle Add Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $name = sanitize_input($_POST['name']);
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = sanitize_input($_POST['role']);
    
    // Check if username exists
    $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
    if ($check->num_rows > 0) {
        $error = 'यो प्रयोगकर्ता नाम पहिले नै प्रयोगमा छ।';
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, username, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $name, $username, $email, $phone, $password, $role);
        
        if ($stmt->execute()) {
            $success = 'कर्मचारी सफलतापूर्वक थपियो।';
        } else {
            $error = 'कर्मचारी थप्दा त्रुटि भयो।';
        }
    }
}

// Handle Edit Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_employee'])) {
    $id = intval($_POST['employee_id']);
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $role = sanitize_input($_POST['role']);
    $status = sanitize_input($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $name, $email, $phone, $role, $status, $id);
    
    if ($stmt->execute()) {
        $success = 'कर्मचारी विवरण अद्यावधिक गरियो।';
    } else {
        $error = 'अद्यावधिक गर्दा त्रुटि भयो।';
    }
}

// Handle Delete Employee
if (isset($_GET['delete']) && is_admin()) {
    $id = intval($_GET['delete']);
    
    // Check if employee has assigned complaints
    $check = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = $id");
    $row = $check->fetch_assoc();
    
    if ($row['count'] > 0) {
        $error = 'यो कर्मचारीलाई हटाउन सकिँदैन किनभने उनीहरूसँग असाइन गरिएका गुनासो छन्।';
    } else {
        $conn->query("DELETE FROM users WHERE id = $id");
        $success = 'कर्मचारी हटाइयो।';
    }
}

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $id = intval($_POST['employee_id']);
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $new_password, $id);
    
    if ($stmt->execute()) {
        $success = 'पासवर्ड रिसेट गरियो।';
    } else {
        $error = 'पासवर्ड रिसेट गर्दा त्रुटि भयो।';
    }
}

// Fetch all employees
$employees = $conn->query("SELECT u.*, COUNT(c.id) as complaint_count 
                          FROM users u 
                          LEFT JOIN complaints c ON u.id = c.assigned_to 
                          GROUP BY u.id 
                          ORDER BY u.created_at DESC");
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.png" type="image/png">
    <title>कर्मचारी व्यवस्थापन - Admin Panel</title>
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
        
        .employee-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: transform 0.3s;
        }
        
        .employee-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .employee-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
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
            <li><a href="employees.php" class="active"><i class="bi bi-people"></i> कर्मचारी व्यवस्थापन</a></li>
            <li><a href="branches.php"><i class="bi bi-building"></i> शाखा व्यवस्थापन</a></li>
            <li><a href="types.php"><i class="bi bi-tags"></i> गुनासो प्रकार</a></li>
            <li><a href="reports.php"><i class="bi bi-bar-chart"></i> रिपोर्ट</a></li>
            <li><a href="settings.php"><i class="bi bi-gear"></i> सेटिङ्ग</a></li>
            <li><a href="profile.php"><i class="bi bi-person"></i> मेरो प्रोफाइल</a></li>
            <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> लगआउट</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="text-primary mb-1">कर्मचारी व्यवस्थापन</h3>
                <p class="text-muted mb-0">कर्मचारी थप्नुहोस्, सम्पादन गर्नुहोस् र व्यवस्थापन गर्नुहोस्</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                <i class="bi bi-plus-circle"></i> नयाँ कर्मचारी थप्नुहोस्
            </button>
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

        <!-- Employees List -->
        <div class="row">
            <?php while ($emp = $employees->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="employee-card">
                        <div class="d-flex align-items-start mb-3">
                            <div class="employee-avatar me-3">
                                <?php echo strtoupper(substr($emp['name'], 0, 2)); ?>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?php echo htmlspecialchars($emp['name']); ?></h6>
                                <small class="text-muted">@<?php echo htmlspecialchars($emp['username']); ?></small>
                                <div class="mt-1">
                                    <?php if ($emp['role'] === 'admin'): ?>
                                        <span class="badge bg-danger">प्रशासक</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">कर्मचारी</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($emp['status'] === 'active'): ?>
                                        <span class="badge bg-success">सक्रिय</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">निष्क्रिय</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($emp['email'] ?? 'N/A'); ?>
                            </small>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($emp['phone'] ?? 'N/A'); ?>
                            </small>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="bi bi-file-earmark-text"></i> असाइन गुनासो: 
                                <strong><?php echo $emp['complaint_count']; ?></strong>
                            </small>
                        </div>
                        
                        <div class="btn-group btn-group-sm w-100">
                            <button class="btn btn-outline-primary" 
                                    onclick="editEmployee(<?php echo htmlspecialchars(json_encode($emp)); ?>)">
                                <i class="bi bi-pencil"></i> सम्पादन
                            </button>
                            <button class="btn btn-outline-warning" 
                                    onclick="resetPassword(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['name']); ?>')">
                                <i class="bi bi-key"></i> पासवर्ड
                            </button>
                            <?php if ($emp['id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-outline-danger" 
                                    onclick="deleteEmployee(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['name']); ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">नयाँ कर्मचारी थप्नुहोस्</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">पुरा नाम *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">प्रयोगकर्ता नाम *</label>
                            <input type="text" name="username" class="form-control" required>
                            <small class="text-muted">यो लगइनको लागि प्रयोग गरिनेछ</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">इमेल</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">फोन नम्बर</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">पासवर्ड *</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <small class="text-muted">कम्तिमा ६ अक्षर</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">भूमिका *</label>
                            <select name="role" class="form-select" required>
                                <option value="employee">कर्मचारी</option>
                                <option value="admin">प्रशासक</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द गर्नुहोस्</button>
                        <button type="submit" name="add_employee" class="btn btn-primary">थप्नुहोस्</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">कर्मचारी सम्पादन गर्नुहोस्</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="employee_id" id="edit_employee_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">पुरा नाम *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">इमेल</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">फोन नम्बर</label>
                            <input type="tel" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">भूमिका *</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="employee">कर्मचारी</option>
                                <option value="admin">प्रशासक</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">स्थिति *</label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="active">सक्रिय</option>
                                <option value="inactive">निष्क्रिय</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द गर्नुहोस्</button>
                        <button type="submit" name="edit_employee" class="btn btn-primary">अद्यावधिक गर्नुहोस्</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">पासवर्ड रिसेट गर्नुहोस्</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="employee_id" id="reset_employee_id">
                    <div class="modal-body">
                        <p>कर्मचारी: <strong id="reset_employee_name"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">नयाँ पासवर्ड *</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                            <small class="text-muted">कम्तिमा ६ अक्षर</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द गर्नुहोस्</button>
                        <button type="submit" name="reset_password" class="btn btn-warning">पासवर्ड रिसेट गर्नुहोस्</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editEmployee(emp) {
            document.getElementById('edit_employee_id').value = emp.id;
            document.getElementById('edit_name').value = emp.name;
            document.getElementById('edit_email').value = emp.email || '';
            document.getElementById('edit_phone').value = emp.phone || '';
            document.getElementById('edit_role').value = emp.role;
            document.getElementById('edit_status').value = emp.status;
            
            new bootstrap.Modal(document.getElementById('editEmployeeModal')).show();
        }
        
        function resetPassword(id, name) {
            document.getElementById('reset_employee_id').value = id;
            document.getElementById('reset_employee_name').textContent = name;
            
            new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
        }
        
        function deleteEmployee(id, name) {
            if (confirm('के तपाईं निश्चित हुनुहुन्छ कि तपाईं ' + name + ' लाई हटाउन चाहनुहुन्छ?')) {
                window.location.href = 'employees.php?delete=' + id;
            }
        }
    </script>
</body>
</html>