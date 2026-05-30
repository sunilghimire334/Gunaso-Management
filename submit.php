<?php
require_once 'config.php';

$success = false;
$error = '';
$complaint_id = '';

// Create notifications directory if it doesn't exist
if (!file_exists('notifications') && is_writable('.')) {
    mkdir('notifications', 0755, true);
}

// Async notification function
function async_notify_complaint_registered($complaint_id, $name, $email, $contact, $subject) {
    // Store notification data for background processing
    $notification_data = [
        'complaint_id' => $complaint_id,
        'name' => $name,
        'email' => $email,
        'contact' => $contact,
        'subject' => $subject,
        'timestamp' => time()
    ];
    
    // Save to file for background processing
    $filename = 'notifications/'.md5($complaint_id.time().rand()).'.json';
    file_put_contents($filename, json_encode($notification_data));
    
    // Try to trigger background process without waiting
    if (function_exists('exec')) {
        // Non-blocking execution - try different methods
        $script_path = __DIR__ . '/process_notifications.php';
        if (file_exists($script_path)) {
            @exec("php \"$script_path\" > /dev/null 2>&1 &");
        }
    }
    
    // Alternative method using HTTP request (if exec is disabled)
    if (!function_exists('exec')) {
        $background_url = "http://" . $_SERVER['HTTP_HOST'] . "/process_notifications.php";
        @file_get_contents($background_url, false, stream_context_create([
            'http' => ['timeout' => 1] // 1 second timeout - don't wait
        ]));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
    $address = isset($_POST['address']) ? sanitize_input($_POST['address']) : '';
    $contact = isset($_POST['contact']) ? sanitize_input($_POST['contact']) : '';
    $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
    $branch_id = intval($_POST['branch_id']);
    $type_id = intval($_POST['type_id']);
    $subject = sanitize_input($_POST['subject']);
    $description = sanitize_input($_POST['description']);
    
    if (empty($subject) || empty($description)) {
        $error = 'कृपया सबै आवश्यक फिल्ड भर्नुहोस्।';
    } else {
        $file_path = null;
        if (isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = upload_file($_FILES['document']);
            if (isset($upload_result['error'])) {
                $error = $upload_result['error'];
            } else {
                $file_path = $upload_result['filepath'];
            }
        }
        
        if (empty($error)) {
            $complaint_id = generate_complaint_id();
            
            $stmt = $conn->prepare("INSERT INTO complaints (complaint_id, name, address, contact, email, branch_id, type_id, subject, description, file_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->bind_param("ssssssisss", $complaint_id, $name, $address, $contact, $email, $branch_id, $type_id, $subject, $description, $file_path);
            
            if ($stmt->execute()) {
                $inserted_id = $conn->insert_id;
                $conn->query("INSERT INTO complaint_logs (complaint_id, updated_by, status, remarks) VALUES ($inserted_id, 1, 'pending', 'गुनासो दर्ता भयो')");
                
                // Send Email & SMS Notifications - ASYNC (Non-blocking)
                if (ENABLE_EMAIL || ENABLE_SMS) {
                    async_notify_complaint_registered($complaint_id, $name, $email, $contact, $subject);
                }
                
                $success = true;
            } else {
                $error = 'गुनासो दर्ता गर्दा त्रुटि भयो। कृपया पुनः प्रयास गर्नुहोस्।';
            }
        }
    }
}

$branches = $conn->query("SELECT * FROM branches WHERE status = 'active' ORDER BY id");
$types = $conn->query("SELECT * FROM complaint_types WHERE status = 'active' ORDER BY type_name");
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/favicon.png" type="image/png">
    <title>गुनासो दर्ता गर्नुहोस् - बेसीशहर नगरपालिका</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
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
        
        .form-container { 
            background: white; 
            border-radius: 15px; 
            padding: 2rem; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            margin: 2rem 0; 
        }
        
        .form-label { 
            font-weight: 600; 
            color: #374151; 
        }
        
        .required::after { 
            content: " *"; 
            color: #dc2626; 
        }
        
        .btn-primary { 
            background: var(--primary-color); 
            border: none; 
            padding: 0.75rem 2rem; 
            font-weight: 600; 
        }
        
        .btn-primary:hover { 
            background: #1e3a8a; 
        }
        
        .notification-info { 
            background: #dbeafe; 
            border-left: 4px solid #3b82f6; 
            padding: 1rem; 
            margin: 1rem 0; 
            border-radius: 5px; 
        }
        
        /* Modal Styles */
        .modal.show { 
            display: block !important; 
            background: rgba(0,0,0,0.5); 
        }
        
        .success-icon { 
            animation: bounceIn 0.6s ease-in-out; 
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .complaint-id-box { 
            border: 2px dashed #1e40af !important; 
            background: linear-gradient(135deg, #f8fafc, #e0f2fe) !important;
        }
        
        /* Loading overlay */
        #loadingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
        }
        
        /* Select2 Custom Styling */
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
            border-color: #dee2e6;
        }
        
        .select2-container--bootstrap-5 .select2-selection--single {
            padding: 0.375rem 0.75rem;
        }
        
        .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        .select2-container--bootstrap-5.select2-container--open .select2-selection {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .select2-container--bootstrap-5 .select2-dropdown {
            border-color: #dee2e6;
        }
        
        .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        
        .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="header-top">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-file-earmark-plus"></i> गुनासो दर्ता गर्नुहोस्</h2>
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

    <!-- Loading Overlay -->
    <div id="loadingOverlay">
        <div class="text-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
            <p class="mt-3 fs-5">गुनासो पेश गर्दै... कृपया प्रतीक्षा गर्नुहोस्</p>
            <small class="text-muted">यसले केही सेकेन्ड लिन सक्छ</small>
        </div>
    </div>

    <div class="container">
        <?php if ($success): ?>
            <!-- Success Modal -->
            <div class="modal fade show" id="successModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" style="display: block;">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="bi bi-check-circle-fill"></i> गुनासो सफलतापूर्वक दर्ता भयो!
                            </h5>
                        </div>
                        <div class="modal-body text-center">
                            <div class="success-icon mb-3">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            </div>
                            
                            <p class="mb-3">तपाईंको गुनासो ट्र्याकिङ नम्बर:</p>
                            <div class="complaint-id-box p-3 mb-3">
                                <h4 class="text-primary mb-0"><?php echo htmlspecialchars($complaint_id); ?></h4>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>महत्त्वपूर्ण:</strong> कृपया यो नम्बर सुरक्षित राख्नुहोस्। 
                                यो नम्बर प्रयोग गरी तपाईं आफ्नो गुनासोको स्थिति जाँच गर्न सक्नुहुन्छ।
                            </div>
                            
                            <div class="redirect-info alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                तपाईंलाई <strong id="countdown">20</strong> सेकेन्डमा गृहपृष्ठमा पठाइनेछ...
                            </div>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <a href="track.php" class="btn btn-primary me-2">
                                <i class="bi bi-search"></i> अहिले नै ट्र्याक गर्नुहोस्
                            </a>
                            <a href="index.php" class="btn btn-success">
                                <i class="bi bi-house"></i> गृहपृष्ठमा जानुहोस्
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            // Auto redirect after 20 seconds
            let seconds = 20;
            const countdownElement = document.getElementById('countdown');
            const countdownInterval = setInterval(function() {
                seconds--;
                countdownElement.textContent = seconds;
                
                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = 'index.php';
                }
            }, 1000);

            // Prevent form resubmission on page refresh
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }

            // Close modal on escape key or backdrop click - redirect to home
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = 'index.php';
                }
            });

            // Redirect to home if modal is closed via backdrop click
            const modal = document.getElementById('successModal');
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    window.location.href = 'index.php';
                }
            });
            </script>
        <?php else: ?>
            <div class="form-container">
                <div class="text-center mb-4">
                    <h3 class="text-primary">गुनासो दर्ता फारम</h3>
                    <p class="text-muted">कृपया तलको फारम भर्नुहोस्। सबै <span class="text-danger">*</span> चिन्ह भएका फिल्ड अनिवार्य छन्।</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (ENABLE_EMAIL || ENABLE_SMS): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>स्वचालित सूचना:</strong> गुनासो दर्ता भएपछि र स्थिति परिवर्तन हुँदा तपाईंलाई 
                    <?php if (ENABLE_EMAIL && ENABLE_SMS): ?>
                        इमेल र SMS द्वारा
                    <?php elseif (ENABLE_EMAIL): ?>
                        इमेल द्वारा
                    <?php else: ?>
                        SMS द्वारा
                    <?php endif; ?>
                    सूचित गरिनेछ।
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="complaintForm">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <h5 class="text-primary border-bottom pb-2"><i class="bi bi-person"></i> व्यक्तिगत विवरण</h5>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">पुरा नाम</label>
                            <input type="text" name="name" class="form-control" placeholder="तपाईंको पुरा नाम" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ठेगाना</label>
                            <input type="text" name="address" class="form-control" placeholder="वडा नं., टोल" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">सम्पर्क नम्बर</label>
                            <input type="tel" name="contact" class="form-control" placeholder="९८XXXXXXXX" pattern="[0-9]{10}" value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>">
                            <small class="text-muted">
                                <i class="bi bi-phone"></i> १० अंकको मोबाइल नम्बर
                                <?php if (ENABLE_SMS): ?>
                                (SMS सूचना पाइनेछ)
                                <?php endif; ?>
                            </small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">इमेल ठेगाना <?php echo ENABLE_EMAIL ? '(सिफारिस गरिएको)' : '(वैकल्पिक)'; ?></label>
                            <input type="email" name="email" class="form-control" placeholder="example@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <small class="text-muted">
                                <i class="bi bi-envelope"></i> 
                                <?php if (ENABLE_EMAIL): ?>
                                स्थिति अपडेट पाउन इमेल अनिवार्य छ
                                <?php else: ?>
                                स्थिति अपडेट पाउन इमेल दिनुहोस्
                                <?php endif; ?>
                            </small>
                        </div>

                        <div class="col-md-12 mb-3 mt-3">
                            <h5 class="text-primary border-bottom pb-2"><i class="bi bi-file-earmark-text"></i> गुनासो विवरण</h5>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">शाखा/विभाग</label>
                            <select name="branch_id" id="branchSelect" class="form-select" required>
                                <option value="">शाखा छान्नुहोस्</option>
                                <?php while ($branch = $branches->fetch_assoc()): ?>
                                    <option value="<?php echo $branch['id']; ?>" <?php echo (isset($_POST['branch_id']) && $_POST['branch_id'] == $branch['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($branch['branch_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted"><i class="bi bi-search"></i> खोज्न टाइप गर्नुहोस्</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">गुनासो प्रकार</label>
                            <select name="type_id" class="form-select" required>
                                <option value="">प्रकार छान्नुहोस्</option>
                                <?php while ($type = $types->fetch_assoc()): ?>
                                    <option value="<?php echo $type['id']; ?>" <?php echo (isset($_POST['type_id']) && $_POST['type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['type_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label required">गुनासोको विषय</label>
                            <input type="text" name="subject" class="form-control" required placeholder="संक्षिप्त विषय" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label required">विस्तृत विवरण</label>
                            <textarea name="description" class="form-control" rows="5" required placeholder="तपाईंको गुनासोको विस्तृत विवरण यहाँ लेख्नुहोस्..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            <small class="text-muted">कृपया सम्भव भएसम्म विस्तृत विवरण दिनुहोस्</small>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="form-label">सम्बन्धित कागजात (वैकल्पिक)</label>
                            <input type="file" name="document" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                            <small class="text-muted"><i class="bi bi-info-circle"></i> अनुमति: JPG, PNG, PDF, DOC, DOCX (अधिकतम 5MB)</small>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="alert alert-success">
                                <i class="bi bi-shield-check"></i>
                                <strong>गोपनीयता सूचना:</strong> तपाईंको सबै जानकारी गोप्य राखिनेछ र केवल गुनासो समाधानको लागि मात्र प्रयोग गरिनेछ।
                            </div>
                        </div>
                        
                        <div class="col-md-12 text-center mt-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="bi bi-send"></i> गुनासो पेश गर्नुहोस्
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2">
                                <i class="bi bi-x-circle"></i> रद्द गर्नुहोस्
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
    // Initialize Select2 for branch dropdown with auto-focus on search
    $(document).ready(function() {
        $('#branchSelect').select2({
            theme: 'bootstrap-5',
            placeholder: 'शाखा छान्नुहोस्',
            allowClear: true,
            language: {
                noResults: function() {
                    return "कुनै परिणाम भेटिएन";
                },
                searching: function() {
                    return "खोज्दै...";
                }
            }
        });
        
        // Auto-focus search input when opening the dropdown
        $('#branchSelect').on('select2:open', function() {
            // Small delay to ensure the dropdown is fully opened
            setTimeout(function() {
                // Find the search input field and focus it
                const searchField = document.querySelector('.select2-search__field');
                if (searchField) {
                    searchField.focus();
                    // Optional: highlight effect for better UX
                    searchField.style.backgroundColor = '#fff8e7';
                    setTimeout(function() {
                        searchField.style.backgroundColor = '';
                    }, 200);
                }
            }, 100);
        });
        
        // Also focus when clicking on the select container for better user experience
        $(document).on('click', '.select2-selection', function() {
            setTimeout(function() {
                const searchField = document.querySelector('.select2-search__field');
                if (searchField && document.querySelector('.select2-container--open')) {
                    searchField.focus();
                }
            }, 50);
        });
    });
    
    // Prevent multiple form submissions and show loading
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('complaintForm');
        const submitBtn = document.getElementById('submitBtn');
        const loadingOverlay = document.getElementById('loadingOverlay');
        let submitted = false;
        
        if (form) {
            form.addEventListener('submit', function(e) {
                if (submitted) {
                    e.preventDefault();
                    return false;
                }
                
                // Basic validation
                const requiredFields = form.querySelectorAll('[required]');
                let valid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        valid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    alert('कृपया सबै आवश्यक फिल्डहरू भर्नुहोस्।');
                    return false;
                }
                
                if (submitted) {
                    e.preventDefault();
                    return false;
                }
                submitted = true;
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> पेश गर्दै... कृपया प्रतीक्षा गर्नुहोस्';
                loadingOverlay.style.display = 'flex';
            });
        }
        
        // Remove loading if user goes back
        window.addEventListener('pageshow', function(e) {
            if (e.persisted) {
                loadingOverlay.style.display = 'none';
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-send"></i> गुनासो पेश गर्नुहोस्';
                }
            }
        });
    });
    </script>
    
    <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').then(reg => {
        reg.onupdatefound = () => {
          const newWorker = reg.installing;
          newWorker.onstatechange = () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              newWorker.postMessage({ type: 'SKIP_WAITING' });
              window.location.reload(); // auto reload with latest files
            }
          };
        };
      });
    }
    </script>
</body>
</html>