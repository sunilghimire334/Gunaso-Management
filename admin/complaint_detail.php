<?php
require_once '../config.php';
check_login();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success = '';
$error = '';

// Check for success/error messages from session
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = sanitize_input($_POST['status']);
    $remarks = sanitize_input($_POST['remarks']);
    $admin_reply = sanitize_input($_POST['admin_reply']);
    $priority = sanitize_input($_POST['priority']);
    
    $assigned_to = null;
    if (is_admin() && isset($_POST['assigned_to']) && !empty($_POST['assigned_to'])) {
        $assigned_to = intval($_POST['assigned_to']);
        
        $user_check = $conn->query("SELECT id FROM users WHERE id = $assigned_to AND status = 'active'");
        if ($user_check->num_rows === 0) {
            $assigned_to = null;
        }
    }
    
    if (is_admin()) {
        $stmt = $conn->prepare("UPDATE complaints SET status = ?, priority = ?, assigned_to = ?, updated_at = NOW(), resolved_at = CASE WHEN ? = 'resolved' THEN NOW() ELSE resolved_at END WHERE id = ?");
        $stmt->bind_param("ssisi", $new_status, $priority, $assigned_to, $new_status, $complaint_id);
    } else {
        $stmt = $conn->prepare("UPDATE complaints SET status = ?, priority = ?, updated_at = NOW(), resolved_at = CASE WHEN ? = 'resolved' THEN NOW() ELSE resolved_at END WHERE id = ? AND assigned_to = ?");
        $stmt->bind_param("sssii", $new_status, $priority, $new_status, $complaint_id, $_SESSION['user_id']);
    }
    
    if ($stmt->execute()) {
        $log_stmt = $conn->prepare("INSERT INTO complaint_logs (complaint_id, updated_by, status, remarks, admin_reply) VALUES (?, ?, ?, ?, ?)");
        $log_stmt->bind_param("iisss", $complaint_id, $_SESSION['user_id'], $new_status, $remarks, $admin_reply);
        $log_stmt->execute();
        
        $email_result = $conn->query("SELECT name, email, contact, complaint_id, subject FROM complaints WHERE id = $complaint_id");
        if ($email_row = $email_result->fetch_assoc()) {
            notify_status_update($email_row['complaint_id'], $email_row['name'], $email_row['email'], 
                               $email_row['contact'], $new_status, $admin_reply);
        }
        
        // Store success message in session and redirect
        $_SESSION['success_message'] = 'गुनासो सफलतापूर्वक अद्यावधिक गरियो।';
        header("Location: complaint_detail.php?id=" . $complaint_id);
        exit();
    } else {
        // Store error message in session and redirect
        $_SESSION['error_message'] = 'अद्यावधिक गर्दा त्रुटि भयो: ' . $conn->error;
        header("Location: complaint_detail.php?id=" . $complaint_id);
        exit();
    }
}

$stmt = $conn->prepare("SELECT c.*, b.branch_name, t.type_name, u.name as assigned_to_name 
                       FROM complaints c 
                       LEFT JOIN branches b ON c.branch_id = b.id 
                       LEFT JOIN complaint_types t ON c.type_id = t.id 
                       LEFT JOIN users u ON c.assigned_to = u.id 
                       WHERE c.id = ?");
$stmt->bind_param("i", $complaint_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: complaints.php");
    exit();
}

$complaint = $result->fetch_assoc();

$logs = $conn->query("SELECT cl.*, u.name as updated_by_name 
                     FROM complaint_logs cl 
                     LEFT JOIN users u ON cl.updated_by = u.id 
                     WHERE cl.complaint_id = $complaint_id 
                     ORDER BY cl.created_at DESC");

$employees = null;
if (is_admin()) {
    $employees = $conn->query("SELECT id, name FROM users WHERE status = 'active' ORDER BY name");
}
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.png" type="image/png">
    <title>गुनासो विवरण - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-color: #2563eb; 
            --primary-dark: #1e40af;
            --primary-light: #dbeafe;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --sidebar-width: 260px;
            --border-color: #e5e7eb;
            --bg-light: #f8fafc;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Inter', 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
            color: #1f2937;
            line-height: 1.6;
        }
        
        .sidebar { 
            position: fixed; 
            top: 0; 
            left: 0; 
            height: 100vh; 
            width: var(--sidebar-width); 
            background: linear-gradient(180deg, #1e40af 0%, #1e3a8a 100%); 
            color: white; 
            padding: 0; 
            overflow-y: auto; 
            z-index: 1000;
            box-shadow: var(--shadow-lg);
        }
        
        .sidebar-brand { 
            padding: 2rem 1.5rem; 
            background: rgba(0, 0, 0, 0.1);
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-brand h4 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .sidebar-brand small {
            opacity: 0.8;
            font-size: 0.875rem;
        }
        
        .sidebar-menu { 
            list-style: none; 
            padding: 1rem 0; 
            margin: 0; 
        }
        
        .sidebar-menu li a { 
            display: flex; 
            align-items: center; 
            padding: 0.875rem 1.5rem; 
            color: rgba(255,255,255,0.85); 
            text-decoration: none; 
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.9375rem;
        }
        
        .sidebar-menu li a:hover { 
            background: rgba(255,255,255,0.15); 
            color: white;
            padding-left: 2rem;
        }
        
        .sidebar-menu li a i { 
            margin-right: 0.875rem; 
            font-size: 1.125rem;
            width: 20px;
            text-align: center;
        }
        
        .main-content { 
            margin-left: var(--sidebar-width); 
            padding: 2rem;
            min-height: 100vh;
        }
        
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }
        
        .content-card { 
            background: white; 
            padding: 2rem; 
            margin-bottom: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: box-shadow 0.3s ease;
        }
        
        .content-card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary-light);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i {
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        
        .complaint-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }
        
        .complaint-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }
        
        .tracking-number {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1.125rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .complaint-header h4 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 1rem 0;
            line-height: 1.4;
        }
        
        .complaint-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            opacity: 0.95;
            font-size: 0.9375rem;
        }
        
        .complaint-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .complaint-meta i {
            font-size: 1.125rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .info-item {
            background: var(--bg-light);
            padding: 1.25rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s ease;
        }
        
        .info-item:hover {
            transform: translateX(4px);
        }
        
        .info-item-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item-value {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .detail-box {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .detail-box p {
            line-height: 1.8;
            margin: 0;
            color: #374151;
        }
        
        .timeline-item { 
            position: relative; 
            padding: 1.5rem; 
            padding-left: 3rem;
            background: white;
            margin-bottom: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .timeline-item:hover {
            box-shadow: var(--shadow-md);
            transform: translateX(4px);
        }
        
        .timeline-item:before { 
            content: ''; 
            position: absolute; 
            left: -2px; 
            top: 1.5rem; 
            width: 12px; 
            height: 12px; 
            background: var(--primary-color); 
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        
        .timeline-user {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            color: #374151;
        }
        
        .timeline-date {
            color: #6b7280;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .timeline-content {
            margin-top: 1rem;
        }
        
        .timeline-content .label {
            font-weight: 700;
            color: #1f2937;
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9375rem;
        }
        
        .timeline-content p {
            margin: 0.5rem 0;
            line-height: 1.7;
            color: #4b5563;
        }
        
        .admin-reply-box {
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
            border-left: 4px solid #0284c7;
            border-radius: 8px;
            padding: 1.25rem;
            margin-top: 1rem;
        }
        
        .update-form { 
            background: var(--bg-light);
            padding: 2rem; 
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.625rem;
            font-size: 0.9375rem;
        }
        
        .form-control, .form-select {
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.9375rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
            outline: none;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.9375rem;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 10px -1px rgba(37, 99, 235, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: white;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }
        
        .alert {
            border: none;
            border-radius: 8px;
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }
        
        .alert-info {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border-left: 4px solid var(--primary-color);
        }
        
        .attachment-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px dashed #0ea5e9;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
            text-align: center;
        }
        
        .attachment-box h6 {
            margin-bottom: 1rem;
            font-weight: 700;
            color: #0c4a6e;
        }
        
        .badge {
            padding: 0.5rem 1rem;
            font-weight: 600;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-in-progress { background: #dbeafe; color: #1e40af; }
        .status-resolved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        
        .priority-low { background: #f3f4f6; color: #374151; }
        .priority-medium { background: #fef3c7; color: #92400e; }
        .priority-high { background: #fed7aa; color: #9a3412; }
        .priority-urgent { background: #fee2e2; color: #991b1b; }
        
        @media print {
            .sidebar, .btn, .update-form, .page-header .btn {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content-card {
                box-shadow: none;
                page-break-inside: avoid;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .complaint-meta {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .timeline-item {
                padding-left: 2rem;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }
        
        /* Smooth animations */
        .content-card, .timeline-item, .info-item, .btn {
            animation: fadeInUp 0.5s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand">
            <h4>बेसीशहर नगरपालिका</h4>
            <small>गुनासो व्यवस्थापन प्रणाली</small>
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
            <li><a href="profile.php"><i class="bi bi-person-circle"></i> मेरो प्रोफाइल</a></li>
            <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> लगआउट</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="page-header">
            <a href="complaints.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> फिर्ता जानुहोस्
            </a>
            <div class="d-flex gap-2">
                <?php echo get_status_badge($complaint['status']); ?>
                <?php echo get_priority_badge($complaint['priority']); ?>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> 
                <span><?php echo $success; ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill"></i> 
                <span><?php echo $error; ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="complaint-header">
            <span class="tracking-number">
                <i class="bi bi-upc-scan"></i> ट्र्याकिङ नं.: <?php echo htmlspecialchars($complaint['complaint_id']); ?>
            </span>
            <h4><?php echo htmlspecialchars($complaint['subject']); ?></h4>
            <div class="complaint-meta">
                <span><i class="bi bi-calendar-event"></i> <?php echo format_nepali_date($complaint['created_at']); ?></span>
                <span><i class="bi bi-building"></i> <?php echo htmlspecialchars($complaint['branch_name']); ?></span>
                <span><i class="bi bi-tag"></i> <?php echo htmlspecialchars($complaint['type_name']); ?></span>
            </div>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="bi bi-person-vcard"></i>
                गुनासोकर्ताको विवरण
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-item-label"><i class="bi bi-person"></i> नाम</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($complaint['name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label"><i class="bi bi-geo-alt"></i> ठेगाना</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($complaint['address']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label"><i class="bi bi-telephone"></i> सम्पर्क नम्बर</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($complaint['contact']); ?></div>
                </div>
                <?php if (!empty($complaint['email'])): ?>
                <div class="info-item">
                    <div class="info-item-label"><i class="bi bi-envelope"></i> इमेल</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($complaint['email']); ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($complaint['assigned_to_name'])): ?>
                <div class="info-item">
                    <div class="info-item-label"><i class="bi bi-person-check"></i> जिम्मेवार कर्मचारी</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($complaint['assigned_to_name']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="bi bi-file-text"></i>
                गुनासोको विस्तृत विवरण
            </div>
            
            <div class="detail-box">
                <p><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
            </div>
            
            <?php if (!empty($complaint['file_path'])): ?>
            <div class="attachment-box">
                <h6><i class="bi bi-paperclip"></i> संलग्न कागजात</h6>
                <a href="../<?php echo htmlspecialchars($complaint['file_path']); ?>" target="_blank" class="btn btn-primary">
                    <i class="bi bi-download"></i> डाउनलोड गर्नुहोस्
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="bi bi-pencil-square"></i>
                स्थिति अद्यावधिक गर्नुहोस्
            </div>
            
            <form method="POST" class="update-form">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-flag"></i> स्थिति</label>
                        <select name="status" class="form-select" required>
                            <option value="pending" <?php echo $complaint['status'] == 'pending' ? 'selected' : ''; ?>>विचाराधीन</option>
                            <option value="in-progress" <?php echo $complaint['status'] == 'in-progress' ? 'selected' : ''; ?>>पकृयामा</option>
                            <option value="resolved" <?php echo $complaint['status'] == 'resolved' ? 'selected' : ''; ?>>समाधान भयो</option>
                            <option value="rejected" <?php echo $complaint['status'] == 'rejected' ? 'selected' : ''; ?>>अस्वीकृत</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-exclamation-triangle"></i> प्राथमिकता</label>
                        <select name="priority" class="form-select" required>
                            <option value="low" <?php echo $complaint['priority'] == 'low' ? 'selected' : ''; ?>>न्यून</option>
                            <option value="medium" <?php echo $complaint['priority'] == 'medium' ? 'selected' : ''; ?>>मध्यम</option>
                            <option value="high" <?php echo $complaint['priority'] == 'high' ? 'selected' : ''; ?>>उच्च</option>
                            <option value="urgent" <?php echo $complaint['priority'] == 'urgent' ? 'selected' : ''; ?>>अति जरुरी</option>
                        </select>
                    </div>
                    
                    <?php if (is_admin() && $employees): ?>
                    <div class="col-md-12">
                        <label class="form-label"><i class="bi bi-person-plus"></i> कर्मचारी असाइन गर्नुहोस्</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">असाइन नगरिएको</option>
                            <?php while ($emp = $employees->fetch_assoc()): ?>
                                <option value="<?php echo $emp['id']; ?>" <?php echo $complaint['assigned_to'] == $emp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-12">
                        <label class="form-label"><i class="bi bi-clipboard"></i> आन्तरिक टिप्पणी</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="यहाँ आन्तरिक टिप्पणी लेख्नुहोस्..."></textarea>
                        <small class="text-muted"><i class="bi bi-info-circle"></i> यो टिप्पणी आन्तरिक रेकर्डको लागि मात्र हो</small>
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label"><i class="bi bi-reply"></i> नागरिकलाई जवाफ</label>
                        <textarea name="admin_reply" class="form-control" rows="4" placeholder="नागरिकलाई दिइने जवाफ यहाँ लेख्नुहोस्..."></textarea>
                        <small class="text-muted"><i class="bi bi-info-circle"></i> यो जवाफ नागरिकलाई देखिनेछ</small>
                    </div>
                    
                    <div class="col-md-12">
                        <button type="submit" name="update_status" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> अद्यावधिक गर्नुहोस्
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="bi bi-clock-history"></i>
                स्थिति इतिहास
            </div>
            
            <?php if ($logs->num_rows > 0): ?>
                <?php while ($log = $logs->fetch_assoc()): ?>
                    <div class="timeline-item">
                        <div class="timeline-header">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <?php echo get_status_badge($log['status']); ?>
                                <span class="timeline-user">
                                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($log['updated_by_name']); ?>
                                </span>
                            </div>
                            <span class="timeline-date">
                                <i class="bi bi-calendar3"></i> <?php echo format_nepali_date($log['created_at']); ?>
                            </span>
                        </div>
                        
                        <div class="timeline-content">
                            <?php if (!empty($log['remarks'])): ?>
                            <div class="mb-3">
                                <span class="label"><i class="bi bi-clipboard"></i> आन्तरिक टिप्पणी:</span>
                                <p><?php echo nl2br(htmlspecialchars($log['remarks'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($log['admin_reply'])): ?>
                            <div class="admin-reply-box">
                                <span class="label"><i class="bi bi-reply-fill"></i> प्रशासनको जवाफ:</span>
                                <p><?php echo nl2br(htmlspecialchars($log['admin_reply'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> <span>अहिलेसम्म कुनै स्थिति अद्यावधिक गरिएको छैन।</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>