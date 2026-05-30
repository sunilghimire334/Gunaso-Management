<?php
require_once '../config.php';
check_login();

// Only admin can access
if (!is_admin()) {
    redirect('admin/dashboard.php');
}

$success = '';
$error = '';

// Handle Add Branch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_branch'])) {
    $branch_name = sanitize_input($_POST['branch_name']);
    $branch_code = sanitize_input($_POST['branch_code']);
    $description = sanitize_input($_POST['description']);
    
    // Check if branch code exists
    $check = $conn->query("SELECT id FROM branches WHERE branch_code = '$branch_code'");
    if ($check->num_rows > 0) {
        $error = 'यो शाखा कोड पहिले नै प्रयोगमा छ।';
    } else {
        $stmt = $conn->prepare("INSERT INTO branches (branch_name, branch_code, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $branch_name, $branch_code, $description);
        
        if ($stmt->execute()) {
            $success = 'शाखा सफलतापूर्वक थपियो।';
        } else {
            $error = 'शाखा थप्दा त्रुटि भयो।';
        }
    }
}

// Handle Edit Branch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_branch'])) {
    $id = intval($_POST['branch_id']);
    $branch_name = sanitize_input($_POST['branch_name']);
    $description = sanitize_input($_POST['description']);
    $status = sanitize_input($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE branches SET branch_name = ?, description = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssi", $branch_name, $description, $status, $id);
    
    if ($stmt->execute()) {
        $success = 'शाखा विवरण अद्यावधिक गरियो।';
    } else {
        $error = 'अद्यावधिक गर्दा त्रुटि भयो।';
    }
}

// Handle Delete Branch
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Check if branch has complaints
    $check = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE branch_id = $id");
    $row = $check->fetch_assoc();
    
    if ($row['count'] > 0) {
        $error = 'यो शाखालाई हटाउन सकिँदैन किनभने यससँग सम्बन्धित गुनासो छन्।';
    } else {
        $conn->query("DELETE FROM branches WHERE id = $id");
        $success = 'शाखा हटाइयो।';
    }
}

// Fetch all branches
$branches = $conn->query("SELECT b.*, COUNT(c.id) as complaint_count 
                         FROM branches b 
                         LEFT JOIN complaints c ON b.id = c.branch_id 
                         GROUP BY b.id 
                         ORDER BY b.branch_name");
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.png" type="image/png">
    <title>शाखा व्यवस्थापन - Admin Panel</title>
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
        
        .branch-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s;
        }
        
        .branch-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .branch-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
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
            <li><a href="branches.php" class="active"><i class="bi bi-building"></i> शाखा व्यवस्थापन</a></li>
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
                <h3 class="text-primary mb-1">शाखा व्यवस्थापन</h3>
                <p class="text-muted mb-0">नगरपालिकाका विभिन्न शाखा/विभागहरू व्यवस्थापन गर्नुहोस्</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                <i class="bi bi-plus-circle"></i> नयाँ शाखा थप्नुहोस्
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

        <!-- Branches List -->
        <div class="row">
            <?php while ($branch = $branches->fetch_assoc()): ?>
                <div class="col-md-6">
                    <div class="branch-card">
                        <div class="d-flex align-items-start">
                            <div class="branch-icon me-3">
                                <i class="bi bi-building"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($branch['branch_name']); ?></h5>
                                        <small class="text-muted">कोड: <?php echo htmlspecialchars($branch['branch_code']); ?></small>
                                    </div>
                                    <div>
                                        <?php if ($branch['status'] === 'active'): ?>
                                            <span class="badge bg-success">सक्रिय</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">निष्क्रिय</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($branch['description'])): ?>
                                <p class="text-muted mb-2 small"><?php echo htmlspecialchars($branch['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="bi bi-file-earmark-text"></i> 
                                        गुनासो: <strong><?php echo $branch['complaint_count']; ?></strong>
                                    </small>
                                    
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" 
                                                onclick="editBranch(<?php echo htmlspecialchars(json_encode($branch)); ?>)">
                                            <i class="bi bi-pencil"></i> सम्पादन
                                        </button>
                                        <button class="btn btn-outline-danger" 
                                                onclick="deleteBranch(<?php echo $branch['id']; ?>, '<?php echo htmlspecialchars($branch['branch_name']); ?>')">
                                            <i class="bi bi-trash"></i> हटाउनुहोस्
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Add Branch Modal -->
    <div class="modal fade" id="addBranchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">नयाँ शाखा थप्नुहोस्</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">शाखाको नाम *</label>
                            <input type="text" name="branch_name" class="form-control" required 
                                   placeholder="जस्तै: प्रशासन शाखा">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">शाखा कोड *</label>
                            <input type="text" name="branch_code" class="form-control" required 
                                   placeholder="जस्तै: ADMIN" style="text-transform: uppercase;">
                            <small class="text-muted">अद्वितीय कोड (अङ्ग्रेजी अक्षरमा)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">विवरण</label>
                            <textarea name="description" class="form-control" rows="3" 
                                      placeholder="शाखाको संक्षिप्त विवरण..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">रद्द गर्नुहोस्</button>
                        <button type="submit" name="add_branch" class="btn btn-primary">थप्नुहोस्</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Branch Modal -->
    <div class="modal fade" id="editBranchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">शाखा सम्पादन गर्नुहोस्</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="branch_id" id="edit_branch_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">शाखाको नाम *</label>
                            <input type="text" name="branch_name" id="edit_branch_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">शाखा कोड</label>
                            <input type="text" id="edit_branch_code" class="form-control" disabled>
                            <small class="text-muted">शाखा कोड परिवर्तन गर्न सकिँदैन</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">विवरण</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
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
                        <button type="submit" name="edit_branch" class="btn btn-primary">अद्यावधिक गर्नुहोस्</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editBranch(branch) {
            document.getElementById('edit_branch_id').value = branch.id;
            document.getElementById('edit_branch_name').value = branch.branch_name;
            document.getElementById('edit_branch_code').value = branch.branch_code;
            document.getElementById('edit_description').value = branch.description || '';
            document.getElementById('edit_status').value = branch.status;
            
            new bootstrap.Modal(document.getElementById('editBranchModal')).show();
        }
        
        function deleteBranch(id, name) {
            if (confirm('के तपाईं निश्चित हुनुहुन्छ कि तपाईं ' + name + ' लाई हटाउन चाहनुहुन्छ?')) {
                window.location.href = 'branches.php?delete=' + id;
            }
        }
    </script>
</body>
</html>