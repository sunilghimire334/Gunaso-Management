<?php
// email_sms_handler.php - Email and SMS Notification Handler

// Include config for constants and database connection
require_once 'config.php';

// Email Configuration (Gmail SMTP) - Use values from config.php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'sanjeevaniitsolution@gmail.com'); // From config.php
define('SMTP_PASS', 'kfnosrnyezgnwtzb'); // From config.php
define('FROM_EMAIL', 'sanjeevaniitsolution@gmail.com'); // From config.php
define('FROM_NAME', 'बेसीशहर नगरपालिका'); // From config.php

/**
 * Send Email Notification
 */
function send_email_notification_full($to_email, $to_name, $subject, $message_html, $message_text = '') {
    
    // Check if email is enabled in settings
    global $conn;
    $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'email_notification'");
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['setting_value'] != '1') {
            return ['success' => false, 'message' => 'Email notification is disabled'];
        }
    }
    
    // Check if PHPMailer is available
    if (file_exists('phpmailer/PHPMailer.php') && 
        file_exists('phpmailer/SMTP.php') && 
        file_exists('phpmailer/Exception.php')) {
        
        // Use PHPMailer for better email handling
        require_once 'phpmailer/PHPMailer.php';
        require_once 'phpmailer/SMTP.php';
        require_once 'phpmailer/Exception.php';
        
        use PHPMailer\PHPMailer\PHPMailer;
        use PHPMailer\PHPMailer\Exception;
        
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            
            // Recipients
            $mail->setFrom(FROM_EMAIL, FROM_NAME);
            $mail->addAddress($to_email, $to_name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message_html;
            $mail->AltBody = $message_text ?: strip_tags($message_html);
            
            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $e) {
            error_log("Email Error: {$mail->ErrorInfo}");
            // Fallback to simple mail
            return send_simple_email($to_email, $to_name, $subject, $message_html);
        }
    } else {
        // Fallback to simple mail if PHPMailer not available
        return send_simple_email($to_email, $to_name, $subject, $message_html);
    }
}

/**
 * Simple PHP mail() function (if PHPMailer not available)
 */
function send_simple_email($to_email, $to_name, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";
    $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
    
    if (mail($to_email, $subject, $message, $headers)) {
        return ['success' => true, 'message' => 'Email sent successfully'];
    } else {
        return ['success' => false, 'message' => 'Email sending failed'];
    }
}

/**
 * Send SMS Notification - UPDATED for correct provider selection
 */
function send_sms_notification($phone, $message) {
    if (!ENABLE_SMS) {
        return ['success' => false, 'message' => 'SMS notifications are disabled'];
    }
    
    // Check SMS provider from config
    if (SMS_PROVIDER == 'sparrow') {
        return send_sms_sparrow($phone, $message);
    } elseif (SMS_PROVIDER == 'aakashsms') {
        return send_sms_aakash($phone, $message);
    } else {
        return ['success' => false, 'message' => 'SMS provider not configured'];
    }
}

/**
 * Sparrow SMS (Nepal) - Most Popular
 * Get API from: https://sparrowsms.com
 */
function send_sms_sparrow($phone, $message) {
    // Phone formatting for Sparrow SMS - with country code
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 3) !== '977') {
        $phone = '977' . ltrim($phone, '0');
    }
    
    $token = defined('SMS_API_TOKEN') ? SMS_API_TOKEN : '';
    $from = defined('SMS_FROM') ? SMS_FROM : 'InfoBesi';
    
    $url = 'https://api.sparrowsms.com/v2/sms/';
    
    $data = [
        'token' => $token,
        'from' => $from,
        'to' => $phone,
        'text' => $message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code == 200) {
        return ['success' => true, 'message' => 'SMS sent successfully via Sparrow'];
    } else {
        error_log("Sparrow SMS Error - HTTP: $http_code, Response: $response, Error: $curl_error");
        return ['success' => false, 'message' => "Sparrow SMS failed: $response"];
    }
}

/**
 * Aakash SMS (Nepal) - CORRECTED v3 API
 * Documentation: https://sms.aakashsms.com/sms/v3/send
 * Parameters: auth_token, to, text
 */
function send_sms_aakash($phone, $message) {
    $auth_token = defined('AAKASH_AUTH_TOKEN') ? AAKASH_AUTH_TOKEN : '';
    $url = defined('AAKASH_API_URL') ? AAKASH_API_URL : 'https://sms.aakashsms.com/sms/v3/send';
    
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
        'auth_token' => $auth_token,  // Only auth_token required
        'to' => $phone,               // 10-digit number without country code
        'text' => $message            // Message content
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Form data, not JSON
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // v3 API uses form-urlencoded content type
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        'User-Agent: Besishahar-Municipality/1.0'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Log for debugging
    error_log("Aakash SMS v3 - Phone: $phone, HTTP: $http_code, Response: $response");
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        // v3 API success response
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
 * Custom SMS Provider
 */
function send_sms_custom($phone, $message) {
    // Implement your own SMS provider here
    // Example: Twilio, Nexmo, etc.
    
    return ['success' => false, 'message' => 'SMS provider not configured'];
}

/**
 * Send Complaint Registration Notification
 */
function send_complaint_registered_notification($complaint_id, $name, $email, $phone) {
    global $conn;
    
    // Get complaint details
    $stmt = $conn->query("SELECT * FROM complaints WHERE complaint_id = '$complaint_id'");
    $complaint = $stmt->fetch_assoc();
    
    $results = [];
    
    // Email Notification
    if (!empty($email)) {
        $subject = "गुनासो दर्ता सफल - $complaint_id";
        
        $message_html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1e40af; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9fafb; }
                .tracking-id { background: white; padding: 15px; border: 2px dashed #1e40af; font-size: 24px; font-weight: bold; color: #1e40af; text-align: center; margin: 20px 0; }
                .footer { padding: 10px; text-align: center; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>बेसीशहर नगरपालिका</h2>
                    <p>गुनासो व्यवस्थापन प्रणाली</p>
                </div>
                <div class='content'>
                    <p>प्रिय <strong>$name</strong>,</p>
                    <p>तपाईंको गुनासो सफलतापूर्वक दर्ता भएको छ।</p>
                    
                    <div class='tracking-id'>$complaint_id</div>
                    
                    <p><strong>कृपया यो ट्र्याकिङ नम्बर सुरक्षित राख्नुहोस्।</strong></p>
                    
                    <p><strong>गुनासो विवरण:</strong></p>
                    <ul>
                        <li>विषय: {$complaint['subject']}</li>
                        <li>दर्ता मिति: " . date('Y/m/d H:i', strtotime($complaint['created_at'])) . "</li>
                        <li>स्थिति: विचाराधीन</li>
                    </ul>
                    
                    <p>तपाईं आफ्नो गुनासोको स्थिति जाँच गर्न: <a href='" . SITE_URL . "/track.php'>यहाँ क्लिक गर्नुहोस्</a></p>
                    
                    <p>धन्यवाद,<br>बेसीशहर नगरपालिका</p>
                </div>
                <div class='footer'>
                    <p>यो स्वचालित सन्देश हो। कृपया जवाफ नदिनुहोस्।</p>
                <p>सम्पर्क: ०६६-५२०१५० | besishaharmunicipality@gmail.com</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $email_result = send_simple_email($email, $name, $subject, $message_html);
        $results['email'] = $email_result;
    }
    
    // SMS Notification
    if (!empty($phone)) {
        $sms_message = "Besishahar Nagarpalika: Tapaaiko gunaso darta bhayo. Tracking No: $complaint_id. Status herna: " . SITE_URL . "/track.php";
        $sms_result = send_sms_notification($phone, $sms_message);
        $results['sms'] = $sms_result;
        
        // Log SMS result
        if (!$sms_result['success']) {
            error_log("SMS Notification Failed for $complaint_id: " . $sms_result['message']);
        }
    }
    
    return $results;
}

/**
 * Send Status Update Notification
 */
function send_status_update_notification($complaint_id, $new_status, $admin_reply = '') {
    global $conn;
    
    // Get complaint details
    $stmt = $conn->query("SELECT * FROM complaints WHERE complaint_id = '$complaint_id'");
    $complaint = $stmt->fetch_assoc();
    
    if (!$complaint) {
        return ['success' => false, 'message' => 'Complaint not found'];
    }
    
    $status_nepali = [
        'pending' => 'विचाराधीन',
        'in-progress' => 'प्रगतिमा',
        'resolved' => 'समाधान भयो',
        'rejected' => 'अस्वीकृत'
    ];
    
    $results = [];
    
    // Email Notification
    if (!empty($complaint['email'])) {
        $subject = "गुनासो स्थिति अद्यावधिक - $complaint_id";
        
        $reply_section = '';
        if (!empty($admin_reply)) {
            $reply_section = "
            <div style='background: #e0f2fe; padding: 15px; border-left: 4px solid #0891b2; margin: 20px 0;'>
                <strong>प्रशासनको जवाफ:</strong>
                <p>" . nl2br(htmlspecialchars($admin_reply)) . "</p>
            </div>
            ";
        }
        
        $status_color = '#10b981'; // Default green
        if ($new_status == 'rejected') $status_color = '#ef4444';
        if ($new_status == 'pending') $status_color = '#f59e0b';
        if ($new_status == 'in-progress') $status_color = '#3b82f6';
        
        $message_html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1e40af; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9fafb; }
                .status-badge { display: inline-block; padding: 8px 16px; border-radius: 5px; font-weight: bold; color: white; }
                .footer { padding: 10px; text-align: center; color: #6b7280; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>गुनासो स्थिति अद्यावधिक</h2>
                </div>
                <div class='content'>
                    <p>प्रिय <strong>{$complaint['name']}</strong>,</p>
                    <p>तपाईंको गुनासो नम्बर <strong>$complaint_id</strong> को स्थिति परिवर्तन भएको छ।</p>
                    
                    <p>नयाँ स्थिति: <span class='status-badge' style='background: $status_color;'>{$status_nepali[$new_status]}</span></p>
                    
                    $reply_section
                    
                    <p>थप जानकारीको लागि: <a href='" . SITE_URL . "/track.php'>यहाँ क्लिक गर्नुहोस्</a></p>
                    
                    <p>धन्यवाद,<br>बेसीशहर नगरपालिका</p>
                </div>
                <div class='footer'>
                    <p>यो स्वचालित सन्देश हो। कृपया जवाफ नदिनुहोस्।</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $email_result = send_simple_email($complaint['email'], $complaint['name'], $subject, $message_html);
        $results['email'] = $email_result;
    }
    
    // SMS Notification
    if (!empty($complaint['contact'])) {
        $sms_message = "Besishahar Mun: Gunaso $complaint_id status: {$status_nepali[$new_status]}. Details: " . SITE_URL . "/track.php";
        $sms_result = send_sms_notification($complaint['contact'], $sms_message);
        $results['sms'] = $sms_result;
        
        // Log SMS result
        if (!$sms_result['success']) {
            error_log("SMS Status Update Failed for $complaint_id: " . $sms_result['message']);
        }
    }
    
    return $results;
}

/**
 * Test Email Configuration
 */
function test_email_config($test_email) {
    $subject = "Test Email - बेसीशहर नगरपालिका";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1e40af; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9fafb; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>बेसीशहर नगरपालिका</h2>
                <p>ईमेल परीक्षण</p>
            </div>
            <div class='content'>
                <h3>ईमेल कन्फिगरेसन परीक्षण सफल भयो!</h3>
                <p>यदि तपाईंले यो ईमेल प्राप्त गर्नुभएको छ भने, तपाईंको ईमेल कन्फिगरेसन सही रूपमा काम गर्दैछ।</p>
                <p>मिति: " . date('Y-m-d H:i:s') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return send_simple_email($test_email, 'Test User', $subject, $message);
}

/**
 * Test SMS Configuration
 */
function test_sms_config($test_phone) {
    $message = "Test SMS from Besishahar Municipality. Your SMS configuration is working! Time: " . date('H:i:s');
    
    return send_sms_notification($test_phone, $message);
}

/**
 * Debug SMS Function
 */
function debug_sms_function() {
    $test_phone = "9862382481";
    $test_message = "Debug test SMS from Besishahar Municipality System";
    
    echo "<h3>Testing SMS Function</h3>";
    echo "Phone: $test_phone<br>";
    echo "Message: $test_message<br>";
    echo "Provider: " . SMS_PROVIDER . "<br>";
    
    $result = send_sms_notification($test_phone, $test_message);
    
    echo "<h4>Result:</h4>";
    echo "<pre>" . print_r($result, true) . "</pre>";
    
    return $result;
}

// Uncomment to test SMS
// debug_sms_function();
?>