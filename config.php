<?php

// config.php - Database Configuration with Email & SMS

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'techcraf_besigunaso');
define('DB_PASS', 'lamjungs123');
define('DB_NAME', 'techcraf_besigunaso');

// System Configuration
define('SITE_URL', 'https://gunaso.besishaharmun.gov.np');
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 2242880); // 2MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour

// Email Configuration (Gmail SMTP)
define('ENABLE_EMAIL', true); // Set to true to enable emails with PHPMailer
define('SMTP_USER', 'besishaharmunicipality@gmail.com');
define('SMTP_PASS', 'zdfhrcnjhupnufgg');
define('FROM_EMAIL', 'besishaharmunicipality@gmail.com');
define('FROM_NAME', 'बेसीशहर नगरपालिका');

// SMS Configuration - Aakash SMS v3 API
define('ENABLE_SMS', true); // Set to true to enable SMS
define('SMS_PROVIDER', 'aakashsms'); // Options: 'sparrow', 'aakashsms'

// Aakash SMS v3 API Configuration
define('AAKASH_AUTH_TOKEN', 'c4e1e8fb2247ec330685bab605e985008800c943f654c4d5b480515d0a124d0c');
define('AAKASH_API_URL', 'https://sms.aakashsms.com/sms/v3/send');

// Timezone
date_default_timezone_set('Asia/Kathmandu');

// Database Connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");
    
    if ($conn->connect_error) {
        die("डेटाबेस जडान असफल: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("डेटाबेस त्रुटि: " . $e->getMessage());
}

// Helper Functions
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $conn->real_escape_string($data);
}

function generate_complaint_id() {
    global $conn;
    
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'complaint_id_prefix'");
    $prefix = 'GUN';
    if ($result && $row = $result->fetch_assoc()) {
        $prefix = $row['setting_value'];
    }
    
    $year = date('Y');
    $yearShort = substr($year, -2);
    
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE DATE(created_at) = '$today'");
    $row = $result->fetch_assoc();
    $count = $row['count'] + 1;
    
    $complaint_id = sprintf("%s-%s-%s-%03d", 
        $prefix, 
        $yearShort, 
        date('md'), 
        $count
    );
    
    $check = $conn->query("SELECT id FROM complaints WHERE complaint_id = '$complaint_id'");
    if ($check && $check->num_rows > 0) {
        $complaint_id .= '-' . rand(100, 999);
    }
    
    return $complaint_id;
}

function redirect($url) {
    header("Location: " . SITE_URL . "/" . $url);
    exit();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function check_login() {
    if (!is_logged_in()) {
        redirect('admin/login.php');
    }
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Get Nepali status name
 */
function get_status_nepali($status) {
    $status_map = [
        'pending' => 'विचाराधीन',
        'in-progress' => 'प्रगतिमा', 
        'resolved' => 'समाधान भयो',
        'rejected' => 'अस्वीकृत'
    ];
    return $status_map[$status] ?? $status;
}

function get_status_badge($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning text-dark">विचाराधीन</span>',
        'in-progress' => '<span class="badge bg-info">प्रगतिमा</span>',
        'resolved' => '<span class="badge bg-success">समाधान भयो</span>',
        'rejected' => '<span class="badge bg-danger">अस्वीकृत</span>'
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">अज्ञात</span>';
}

function get_priority_badge($priority) {
    $badges = [
        'low' => '<span class="badge bg-secondary">न्यून</span>',
        'medium' => '<span class="badge bg-primary">मध्यम</span>',
        'high' => '<span class="badge bg-warning text-dark">उच्च</span>',
        'urgent' => '<span class="badge bg-danger">अति जरुरी</span>'
    ];
    return $badges[$priority] ?? '<span class="badge bg-secondary">मध्यम</span>';
}

function format_nepali_date($date) {
    return date('Y/m/d', strtotime($date));
}

function upload_file($file) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'फाइल अपलोड गर्दा त्रुटि भयो।'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'फाइल साइज धेरै ठूलो छ। अधिकतम 5MB मात्र अनुमति छ।'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['error' => 'अमान्य फाइल प्रकार। JPG, PNG, PDF, DOC, DOCX मात्र अनुमति छ।'];
    }
    
    if (!file_exists(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = UPLOAD_DIR . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filepath' => $filepath];
    }
    
    return ['error' => 'फाइल सेभ गर्दा त्रुटि भयो।'];
}

/**
 * Send Email using PHPMailer with Gmail SMTP
 */
function send_email_phpmailer($to_email, $to_name, $subject, $message_html) {
    if (!ENABLE_EMAIL) {
        return ['success' => false, 'message' => 'Email notifications are disabled'];
    }
    
    // Check if PHPMailer files exist
    $phpmailer_path = 'phpmailer/';
    if (!file_exists($phpmailer_path . 'PHPMailer.php')) {
        return ['success' => false, 'message' => 'PHPMailer not found - please upload PHPMailer files'];
    }
    
    require_once $phpmailer_path . 'PHPMailer.php';
    require_once $phpmailer_path . 'SMTP.php';
    require_once $phpmailer_path . 'Exception.php';
    
    // Use the correct namespace for PHPMailer
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Gmail SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = 'tls'; // Use string instead of constant for compatibility
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message_html;
        $mail->AltBody = strip_tags($message_html);
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully via Gmail SMTP'];
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => "Email failed: {$mail->ErrorInfo}"];
    }
}

/**
 * Send SMS Notification - CORRECTED v3 API
 */
function send_sms_notification($phone, $message) {
    if (!ENABLE_SMS) {
        return ['success' => false, 'message' => 'SMS notifications are disabled'];
    }
    
    if (SMS_PROVIDER == 'aakashsms') {
        return send_sms_aakash($phone, $message);
    }
    
    return ['success' => false, 'message' => 'SMS provider not configured'];
}

/**
 * Aakash SMS Integration - CORRECTED v3 API
 */
function send_sms_aakash($phone, $message) {
    $auth_token = AAKASH_AUTH_TOKEN;
    $url = AAKASH_API_URL;
    
    // Phone number formatting for v3 API - 10 digits without country code
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 3) === '977') {
        $phone = substr($phone, 3);
    }
    if (substr($phone, 0, 1) === '0') {
        $phone = substr($phone, 1);
    }
    
    // Prepare data according to v3 API specification
    $data = [
        'auth_token' => $auth_token,
        'to' => $phone,
        'text' => $message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        'User-Agent: Besishahar-Municipality/1.0'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("Aakash SMS v3 - Phone: $phone, HTTP: $http_code, Response: $response");
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        if (isset($result['error']) && $result['error'] == 0) {
            return ['success' => true, 'message' => 'SMS sent successfully via Aakash'];
        } else {
            $error_msg = $result['message'] ?? 'Unknown API error';
            return ['success' => false, 'message' => "Aakash SMS Error: $error_msg"];
        }
    } else {
        return ['success' => false, 'message' => "Aakash SMS HTTP Error: $http_code - $response"];
    }
}

/**
 * Send Complaint Registration Notifications
 */
function notify_complaint_registered($complaint_id, $name, $email, $phone, $subject) {
    $results = [];
    
    // Email Notification
    if (!empty($email) && ENABLE_EMAIL) {
        $email_subject = "गुनासो दर्ता सफल - $complaint_id";
        
        $message_html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #1e40af; color: white; padding: 20px; text-align: center;'>
                <h2>बेसीशहर नगरपालिका</h2>
                <p>गुनासो व्यवस्थापन प्रणाली</p>
            </div>
            <div style='padding: 20px; background: #f9fafb;'>
                <p>प्रिय <strong>$name</strong>,</p>
                <p>तपाईंको गुनासो सफलतापूर्वक दर्ता भएको छ।</p>
                
                <div style='background: white; padding: 15px; border: 2px dashed #1e40af; font-size: 24px; font-weight: bold; color: #1e40af; text-align: center; margin: 20px 0;'>
                    $complaint_id
                </div>
                
                <p><strong>कृपया यो ट्र्याकिङ नम्बर सुरक्षित राख्नुहोस्।</strong></p>
                
                <p><strong>गुनासो विषय:</strong> $subject</p>
                <p><strong>स्थिति:</strong> विचाराधीन</p>
                
                <p>तपाईं आफ्नो गुनासोको स्थिति जाँच गर्न: <a href='" . SITE_URL . "/track.php'>यहाँ क्लिक गर्नुहोस्</a></p>
                
                <p>धन्यवाद,<br>बेसीशहर नगरपालिका</p>
            </div>
            <div style='padding: 10px; text-align: center; color: #6b7280; font-size: 12px;'>
                <p>सम्पर्क: ०६६-५२०१५० | besishaharmunicipality@gmail.com</p>
            </div>
        </div>
        ";
        
        $email_result = send_email_phpmailer($email, $name, $email_subject, $message_html);
        $results['email'] = $email_result;
        
        if (!$email_result['success']) {
            error_log("Email notification failed for $complaint_id: " . $email_result['message']);
        }
    }
    
    // SMS Notification
    if (!empty($phone) && ENABLE_SMS) {
        $sms_message = "बेसीशहर नगरपालिका: तपाईको गुनासो सफलतापुर्वक दर्ता भयो। ट्र्याकिङ नंः $complaint_id. Status हेर्न: " . SITE_URL . "/track.php";
        $sms_result = send_sms_notification($phone, $sms_message);
        $results['sms'] = $sms_result;
        
        if (!$sms_result['success']) {
            error_log("SMS notification failed for $complaint_id: " . $sms_result['message']);
        }
    }
    
    return $results;
}

/**
 * Send Status Update Notifications
 */
function notify_status_update($complaint_id, $name, $email, $phone, $new_status, $admin_reply = '') {
    $status_nepali = [
        'pending' => 'विचाराधीन',
        'in-progress' => 'प्रगतिमा',
        'resolved' => 'समाधान भयो',
        'rejected' => 'अस्वीकृत'
    ];
    
    $results = [];
    
    // Email Notification
    if (!empty($email) && ENABLE_EMAIL) {
        $email_subject = "गुनासो स्थिति अद्यावधिक - $complaint_id";
        
        $reply_section = '';
        if (!empty($admin_reply)) {
            $reply_section = "
            <div style='background: #e0f2fe; padding: 15px; border-left: 4px solid #0891b2; margin: 20px 0;'>
                <strong>प्रशासनको जवाफ:</strong>
                <p>" . nl2br(htmlspecialchars($admin_reply)) . "</p>
            </div>
            ";
        }
        
        $message_html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #1e40af; color: white; padding: 20px; text-align: center;'>
                <h2>गुनासो स्थिति अद्यावधिक</h2>
            </div>
            <div style='padding: 20px; background: #f9fafb;'>
                <p>प्रिय <strong>$name</strong>,</p>
                <p>तपाईंको गुनासो नम्बर <strong>$complaint_id</strong> को स्थिति परिवर्तन भएको छ।</p>
                
                <p><strong>नयाँ स्थिति:</strong> <span style='display: inline-block; padding: 8px 16px; border-radius: 5px; font-weight: bold; background: #10b981; color: white;'>{$status_nepali[$new_status]}</span></p>
                
                $reply_section
                
                <p>थप जानकारीको लागि: <a href='" . SITE_URL . "/track.php'>यहाँ क्लिक गर्नुहोस्</a></p>
                
                <p>धन्यवाद,<br>बेसीशहर नगरपालिका</p>
            </div>
            <div style='padding: 10px; text-align: center; color: #6b7280; font-size: 12px;'>
                <p>सम्पर्क: ०६६-५२०१५० | besishaharmunicipality@gmail.com</p>
            </div>
        </div>
        ";
        
        $email_result = send_email_phpmailer($email, $name, $email_subject, $message_html);
        $results['email'] = $email_result;
    }
    
    // SMS Notification
    if (!empty($phone) && ENABLE_SMS) {
        $sms_message = "Besishahar Mun: Gunaso $complaint_id status: {$status_nepali[$new_status]}. Details: " . SITE_URL . "/track.php";
        $sms_result = send_sms_notification($phone, $sms_message);
        $results['sms'] = $sms_result;
    }
    
    return $results;
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>