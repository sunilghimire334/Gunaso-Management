<?php
require_once 'config.php';

$complaint = null;
$logs = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['id'])) {
    $search_id = isset($_POST['complaint_id']) ? sanitize_input($_POST['complaint_id']) : sanitize_input($_GET['id']);
    
    if (!empty($search_id)) {
        $stmt = $conn->prepare("SELECT c.*, b.branch_name, t.type_name FROM complaints c 
                               LEFT JOIN branches b ON c.branch_id = b.id 
                               LEFT JOIN complaint_types t ON c.type_id = t.id 
                               WHERE c.complaint_id = ?");
        $stmt->bind_param("s", $search_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $complaint = $result->fetch_assoc();
            
            $log_stmt = $conn->prepare("SELECT cl.*, u.name as updated_by_name 
                                       FROM complaint_logs cl 
                                       LEFT JOIN users u ON cl.updated_by = u.id 
                                       WHERE cl.complaint_id = ? 
                                       ORDER BY cl.created_at ASC"); // Changed to ASC to get oldest first
            $log_stmt->bind_param("i", $complaint['id']);
            $log_stmt->execute();
            $log_result = $log_stmt->get_result();
            while ($row = $log_result->fetch_assoc()) {
                $logs[] = $row;
            }
        } else {
            $error = 'यो ट्र्याकिङ नम्बर फेला परेन। कृपया सही नम्बर प्रविष्ट गर्नुहोस्।';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/favicon.png" type="image/png">
    <title>गुनासो ट्र्याक गर्नुहोस् - बेसीशहर नगरपालिका</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/mobile-nav.css">
    <style>
        :root { 
            --primary-color: #1e40af; 
            --secondary-color: #dc2626;
        }
        
        body { 
            font-family: 'Poppins', Arial, sans-serif; 
            background-color: #f3f4f6;
        }
        
        .header-top { 
            background: linear-gradient(135deg, var(--primary-color), #3b82f6);
            color: white;
            padding: 1rem 0; 
        }
        
        .header-top h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }
        
        .btn-home {
            background: white;
            color: var(--primary-color);
            border: none;
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-home:hover {
            background: #f3f4f6;
            color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .search-box {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin: 2rem 0;
        }
        
        .search-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .search-header i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .search-header h3 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .search-header p {
            color: #6b7280;
            margin: 0;
        }
        
        .search-input {
            font-size: 1.1rem;
            padding: 0.875rem 1.25rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(30, 64, 175, 0.15);
            outline: none;
        }
        
        .btn-search {
            background: var(--primary-color);
            border: none;
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            color: white;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            background: #1e3a8a;
            transform: translateY(-2px);
        }
        
        .info-alert {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border-left: 4px solid #3b82f6;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-top: 1.5rem;
        }
        
        .info-alert i {
            color: #3b82f6;
            font-size: 1.1rem;
        }
        
        .complaint-detail-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin: 2rem 0;
        }
        
        .complaint-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #f3f4f6;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .tracking-info h4 {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .tracking-info h5 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }
        
        .status-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: flex-end;
        }
        
        .detail-section {
            margin-bottom: 2rem;
        }
        
        .detail-row {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #6b7280;
            min-width: 150px;
        }
        
        .detail-value {
            color: #1f2937;
            flex: 1;
        }
        
        .content-box {
            background: #f9fafb;
            padding: 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .content-box h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .content-box p {
            margin: 0;
            line-height: 1.6;
        }
        
        .timeline-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin: 2rem 0;
        }
        
        .timeline-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-timeline {
            position: relative;
            padding-left: 2.5rem;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 0.75rem;
            width: 2px;
            height: calc(100% - 0.75rem);
            background: #e5e7eb;
        }
        
        .timeline-item:last-child:before {
            display: none;
        }
        
        .timeline-dot {
            position: absolute;
            left: -2.5rem;
            top: 0;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid white;
            box-shadow: 0 0 0 2px var(--primary-color);
        }
        
        .timeline-dot.success {
            background: #10b981;
            box-shadow: 0 0 0 2px #10b981;
        }
        
        .timeline-dot.warning {
            background: #f59e0b;
            box-shadow: 0 0 0 2px #f59e0b;
        }
        
        .timeline-dot.info {
            background: #06b6d4;
            box-shadow: 0 0 0 2px #06b6d4;
        }
        
        .timeline-content {
            background: #f9fafb;
            border-radius: 10px;
            padding: 1.25rem;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .timeline-content:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .admin-reply {
            background: white;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .admin-reply strong {
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .action-buttons {
            text-align: center;
            margin: 2rem 0;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #1e3a8a;
            transform: translateY(-2px);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            background: white;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: none;
            border-left: 4px solid #dc2626;
            color: #991b1b;
            border-radius: 10px;
            padding: 1rem 1.25rem;
        }
        
        .alert-danger i {
            font-size: 1.25rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }
        
        @media print {
            .header-top, .btn, .action-buttons, .mobile-bottom-nav {
                display: none !important;
            }
            body {
                background: white;
            }
            .complaint-detail-card, .timeline-card {
                box-shadow: none;
            }
        }
        
        @media (max-width: 767.98px) {
            .header-top {
                padding: 0.75rem 0;
            }
            
            .header-top h2 {
                font-size: 1.2rem;
            }
            
            .search-box, .complaint-detail-card, .timeline-card {
                padding: 1.5rem;
                margin: 1rem 0;
            }
            
            .search-header i {
                font-size: 2.5rem;
            }
            
            .complaint-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .status-info {
                align-items: flex-start;
                width: 100%;
            }
            
            .tracking-info h5 {
                font-size: 1.2rem;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .detail-label {
                min-width: auto;
            }
            
            .timeline-title {
                font-size: 1.2rem;
            }
            
            .status-timeline {
                padding-left: 2rem;
            }
            
            .timeline-dot {
                left: -2.25rem;
            }
            
            .timeline-item:before {
                left: -1.75rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
            }
        }
        
        @media (max-width: 575.98px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            .search-box, .complaint-detail-card, .timeline-card {
                padding: 1rem;
            }
            
            .search-input {
                font-size: 1rem;
                padding: 0.75rem 1rem;
            }
            
            .btn-search {
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header-top">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-search"></i> गुनासो ट्र्याक गर्नुहोस्</h2>
                <a href="index.php" class="btn btn-home"><i class="bi bi-house"></i> गृहपृष्ठ</a>
            </div>
        </div>
    </div>
    
    <!-- Mobile Bottom Navigation -->
    <div class="mobile-bottom-nav">
        <div class="nav-container">
            <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <i class="bi bi-house-door-fill"></i>
                <span>गृहपृष्ठ</span>
            </a>
            <a href="submit.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'submit.php' ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-plus-fill"></i>
                <span>दर्ता</span>
            </a>
            <a href="track.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'track.php' ? 'active' : ''; ?>">
                <i class="bi bi-search"></i>
                <span>ट्र्याक</span>
            </a>
            <a href="faq.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'faq.php' ? 'active' : ''; ?>">
                <i class="bi bi-question-circle-fill"></i>
                <span>प्रश्नहरू</span>
            </a>
        </div>
    </div>

    <div class="container">
        <div class="search-box">
            <div class="search-header">
                <i class="bi bi-search"></i>
                <h3>तपाईंको गुनासो खोज्नुहोस्</h3>
                <p>आफ्नो गुनासो ट्र्याकिङ नम्बर प्रविष्ट गर्नुहोस्</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <strong>त्रुटि!</strong> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" class="row g-3">
                <div class="col-md-9">
                    <input type="text" 
                           name="complaint_id" 
                           class="form-control search-input" 
                           placeholder="GUN-XX-XXXX-XXX" 
                           required 
                           value="<?php echo isset($_POST['complaint_id']) ? htmlspecialchars($_POST['complaint_id']) : ''; ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-search w-100">
                        <i class="bi bi-search"></i> खोज्नुहोस्
                    </button>
                </div>
            </form>

            <div class="info-alert">
                <i class="bi bi-info-circle"></i>
                <strong>सूचना:</strong> तपाईंको ट्र्याकिङ नम्बर गुनासो दर्ता गर्दा प्रदान गरिएको थियो। यदि तपाईंसँग नम्बर छैन भने कार्यालयमा सम्पर्क गर्नुहोस्।
            </div>
        </div>

        <?php if ($complaint): ?>
            <div class="complaint-detail-card">
                <div class="complaint-header">
                    <div class="tracking-info">
                        <h4>गुनासो विवरण</h4>
                        <h5>ट्र्याकिङ नम्बर: <?php echo htmlspecialchars($complaint['complaint_id']); ?></h5>
                    </div>
                    <div class="status-info">
                        <div>स्थिति: <?php echo get_status_badge($complaint['status']); ?></div>
                        <div>प्राथमिकता: <?php echo get_priority_badge($complaint['priority']); ?></div>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="detail-row">
                                <span class="detail-label">नाम:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($complaint['name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">सम्पर्क:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($complaint['contact']); ?></span>
                            </div>
                            <?php if (!empty($complaint['email'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">इमेल:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($complaint['email']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <span class="detail-label">शाखा:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($complaint['branch_name']); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-row">
                                <span class="detail-label">गुनासो प्रकार:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($complaint['type_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">दर्ता मिति:</span>
                                <span class="detail-value"><?php echo format_nepali_date($complaint['created_at']); ?></span>
                            </div>
                            <?php if ($complaint['status'] == 'resolved' && $complaint['resolved_at']): ?>
                            <div class="detail-row">
                                <span class="detail-label">समाधान मिति:</span>
                                <span class="detail-value"><?php echo format_nepali_date($complaint['resolved_at']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="content-box">
                    <h6><i class="bi bi-file-text"></i> विषय:</h6>
                    <p><?php echo htmlspecialchars($complaint['subject']); ?></p>
                </div>
                
                <div class="content-box">
                    <h6><i class="bi bi-card-text"></i> विस्तृत विवरण:</h6>
                    <p><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
                </div>
                
                <?php if (!empty($complaint['file_path'])): ?>
                <div class="content-box">
                    <h6><i class="bi bi-paperclip"></i> संलग्न कागजात:</h6>
                    <a href="<?php echo htmlspecialchars($complaint['file_path']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-arrow-down"></i> डाउनलोड गर्नुहोस्
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <div class="timeline-card">
                <h4 class="timeline-title"><i class="bi bi-clock-history"></i> स्थिति इतिहास</h4>

                <?php if (count($logs) > 0): ?>
                    <div class="status-timeline">
                        <?php 
                        // Reverse the logs array to show latest first in the timeline
                        $reversed_logs = array_reverse($logs);
                        $log_count = count($reversed_logs);
                        
                        foreach ($reversed_logs as $index => $log): 
                            $is_first_log = ($index === $log_count - 1); // Last in reversed array = first in original
                        ?>
                            <div class="timeline-item">
                                <div class="timeline-dot <?php echo $log['status'] == 'resolved' ? 'success' : ($log['status'] == 'in-progress' ? 'info' : ($log['status'] == 'pending' ? 'warning' : '')); ?>"></div>
                                
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <div>
                                            <h6 class="mb-1"><?php echo get_status_badge($log['status']); ?></h6>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> <?php echo format_nepali_date($log['created_at']); ?>
                                            </small>
                                        </div>
                                        <?php if (!empty($log['updated_by_name'])): ?>
                                        <small class="text-muted"><i class="bi bi-person"></i> <?php echo htmlspecialchars($log['updated_by_name']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($is_first_log): ?>
                                        <!-- Show default comment only for the first log (complaint registration) -->
                                        <div class="mt-2">
                                            <strong>टिप्पणी:</strong>
                                            <p class="mb-0 mt-1">गुनासो दर्ता भयो</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($log['admin_reply'])): ?>
                                    <div class="admin-reply">
                                        <strong><i class="bi bi-chat-left-quote"></i>जवाफ:</strong>
                                        <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($log['admin_reply'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>कुनै स्थिति अद्यावधिक उपलब्ध छैन।</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="action-buttons">
                <a href="track.php" class="btn btn-primary"><i class="bi bi-arrow-left"></i> फेरि खोज्नुहोस्</a>
                <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> प्रिन्ट गर्नुहोस्</button>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.onbeforeprint = function() {
            document.querySelector('.header-top').style.display = 'none';
            var buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => btn.style.display = 'none');
        };
        
        window.onafterprint = function() {
            document.querySelector('.header-top').style.display = 'block';
            var buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => btn.style.display = 'inline-block');
        };
    </script>
    
    <script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').then(reg => {
    reg.onupdatefound = () => {
      const newWorker = reg.installing;
      newWorker.onstatechange = () => {
        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
          newWorker.postMessage({ type: 'SKIP_WAITING' });
          window.location.reload();
        }
      };
    };
  });
}
</script>
</body>
</html>