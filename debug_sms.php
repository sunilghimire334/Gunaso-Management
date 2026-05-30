<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>🔍 Aakash SMS Debug Tool</h2>";
echo "<hr>";

// Step 1: Check Configuration
echo "<h3>Step 1: Configuration Check</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$checks = [
    ['SMS Enabled', ENABLE_SMS ? 'YES' : 'NO', ENABLE_SMS ? '✅' : '❌'],
    ['Provider', SMS_PROVIDER, SMS_PROVIDER == 'aakashsms' ? '✅' : '⚠️'],
    ['Sender ID', SMS_FROM, !empty(SMS_FROM) ? '✅' : '❌'],
    ['Auth ID', AAKASH_AUTH_ID, !empty(AAKASH_AUTH_ID) && AAKASH_AUTH_ID != 'your-auth-id-here' ? '✅' : '❌'],
    ['Auth Token', substr(AAKASH_AUTH_TOKEN, 0, 10) . '...', !empty(AAKASH_AUTH_TOKEN) && AAKASH_AUTH_TOKEN != 'your-auth-token-here' ? '✅' : '❌']
];

foreach ($checks as $check) {
    echo "<tr>";
    echo "<td><strong>{$check[0]}</strong></td>";
    echo "<td>{$check[1]}</td>";
    echo "<td style='font-size: 20px;'>{$check[2]}</td>";
    echo "</tr>";
}
echo "</table>";

// Check if configuration is valid
$config_valid = ENABLE_SMS && 
                SMS_PROVIDER == 'aakashsms' && 
                !empty(SMS_FROM) && 
                AAKASH_AUTH_ID != 'your-auth-id-here' && 
                AAKASH_AUTH_TOKEN != 'your-auth-token-here';

if (!$config_valid) {
    echo "<div style='background: #fee; border: 2px solid red; padding: 15px; margin: 20px 0;'>";
    echo "<h3 style='color: red;'>❌ Configuration Error!</h3>";
    echo "<p><strong>Please update config.php with your Aakash SMS credentials:</strong></p>";
    echo "<ol>";
    if (!ENABLE_SMS) echo "<li>Set ENABLE_SMS to true</li>";
    if (SMS_PROVIDER != 'aakashsms') echo "<li>Set SMS_PROVIDER to 'aakashsms'</li>";
    if (empty(SMS_FROM)) echo "<li>Add your Sender ID (SMS_FROM)</li>";
    if (AAKASH_AUTH_ID == 'your-auth-id-here') echo "<li>Replace AAKASH_AUTH_ID with your actual auth_id</li>";
    if (AAKASH_AUTH_TOKEN == 'your-auth-token-here') echo "<li>Replace AAKASH_AUTH_TOKEN with your actual auth_token</li>";
    echo "</ol>";
    echo "</div>";
    exit;
}

echo "<hr>";

// Step 2: Check PHP Extensions
echo "<h3>Step 2: PHP Extensions Check</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Extension</th><th>Status</th></tr>";

$extensions = [
    'curl' => extension_loaded('curl'),
    'json' => extension_loaded('json'),
    'openssl' => extension_loaded('openssl')
];

foreach ($extensions as $ext => $loaded) {
    echo "<tr>";
    echo "<td><strong>$ext</strong></td>";
    echo "<td style='font-size: 20px;'>" . ($loaded ? '✅ Loaded' : '❌ Not Loaded') . "</td>";
    echo "</tr>";
}
echo "</table>";

if (!$extensions['curl']) {
    echo "<div style='background: #fee; border: 2px solid red; padding: 15px; margin: 20px 0;'>";
    echo "<h3 style='color: red;'>❌ CURL Extension Missing!</h3>";
    echo "<p>Install CURL: <code>sudo apt-get install php-curl</code> (Linux) or enable it in php.ini (Windows)</p>";
    echo "</div>";
    exit;
}

echo "<hr>";

// Step 3: Test Phone Number Format
echo "<h3>Step 3: Phone Number Format Test</h3>";
$test_phone = '9862382481'; // Change this to your phone number
echo "<p><strong>Test Phone:</strong> <input type='text' id='phone' value='$test_phone' size='15'> ";
echo "<button onclick='updatePhone()'>Update</button></p>";

$formatted_phone = preg_replace('/^0/', '977', $test_phone);
echo "<p><strong>Original:</strong> $test_phone</p>";
echo "<p><strong>Formatted:</strong> $formatted_phone</p>";
echo "<p style='color: green;'>✅ Format is correct</p>";

echo "<hr>";

// Step 4: Direct API Test
echo "<h3>Step 4: Direct API Test</h3>";
echo "<p>Sending test SMS to: <strong>$formatted_phone</strong></p>";

$url = 'https://api.aakashsms.com/sms/v3/send';
$message = 'Test SMS from Besishahar Municipality. Time: ' . date('H:i:s');

$data = [
    'auth_id' => AAKASH_AUTH_ID,
    'auth_token' => AAKASH_AUTH_TOKEN,
    'to' => $formatted_phone,
    'text' => $message
];

echo "<h4>Request Details:</h4>";
echo "<pre>";
echo "URL: $url\n";
echo "Data: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
// FIX: Disable SSL verification for localhost
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<h4>Response:</h4>";
echo "<p><strong>HTTP Code:</strong> ";
if ($http_code == 200) {
    echo "<span style='color: green; font-size: 20px;'>$http_code ✅</span>";
} else {
    echo "<span style='color: red; font-size: 20px;'>$http_code ❌</span>";
}
echo "</p>";

if (!empty($curl_error)) {
    echo "<div style='background: #fee; padding: 10px; border: 1px solid red;'>";
    echo "<strong>CURL Error:</strong> $curl_error";
    echo "</div>";
}

echo "<h4>API Response:</h4>";
echo "<pre style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
$response_data = json_decode($response, true);
if ($response_data) {
    echo json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo $response;
}
echo "</pre>";

// Analyze response
if ($http_code == 200) {
    echo "<div style='background: #dfd; border: 2px solid green; padding: 15px; margin: 20px 0;'>";
    echo "<h3 style='color: green;'>✅ SUCCESS!</h3>";
    echo "<p>SMS has been sent successfully. Check your phone!</p>";
    echo "</div>";
} else {
    echo "<div style='background: #fee; border: 2px solid red; padding: 15px; margin: 20px 0;'>";
    echo "<h3 style='color: red;'>❌ FAILED!</h3>";
    echo "<p><strong>Common Issues:</strong></p>";
    echo "<ul>";
    
    // Analyze error
    if ($http_code == 401) {
        echo "<li><strong>Authentication Failed:</strong> Check your auth_id and auth_token</li>";
        echo "<li>Login to Aakash SMS dashboard and verify credentials</li>";
    } elseif ($http_code == 400) {
        echo "<li><strong>Bad Request:</strong> Check phone number format or message content</li>";
    } elseif ($http_code == 402) {
        echo "<li><strong>Insufficient Balance:</strong> Recharge your Aakash SMS account</li>";
    } elseif ($http_code == 0) {
        echo "<li><strong>Connection Failed:</strong> Check internet connection or firewall</li>";
    } else {
        echo "<li>Check the API response above for details</li>";
    }
    
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";

// Step 5: Function Test
echo "<h3>Step 5: Using System Function</h3>";
$function_result = send_sms_notification($test_phone, $message);
echo "<p><strong>Result:</strong> " . ($function_result ? '✅ Success' : '❌ Failed') . "</p>";

echo "<hr>";

// Troubleshooting Guide
echo "<h3>📋 Troubleshooting Checklist</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Check</th><th>Action</th></tr>";
echo "<tr><td>Auth ID correct?</td><td>Login to Aakash dashboard → API Settings</td></tr>";
echo "<tr><td>Auth Token correct?</td><td>Verify no extra spaces or characters</td></tr>";
echo "<tr><td>Sender ID approved?</td><td>Check Mask Management section</td></tr>";
echo "<tr><td>Sufficient balance?</td><td>Check account balance (min Rs. 50)</td></tr>";
echo "<tr><td>Phone format correct?</td><td>Should be 977XXXXXXXXX</td></tr>";
echo "<tr><td>Internet working?</td><td>Test: ping api.aakashsms.com</td></tr>";
echo "</table>";

echo "<hr>";
echo "<h3>🔗 Useful Links</h3>";
echo "<ul>";
echo "<li><a href='https://www.aakashsms.com' target='_blank'>Aakash SMS Dashboard</a></li>";
echo "<li><a href='https://docs.aakashsms.com' target='_blank'>API Documentation</a></li>";
echo "<li><a href='https://www.aakashsms.com/contact' target='_blank'>Contact Support</a></li>";
echo "</ul>";

echo "<script>
function updatePhone() {
    var phone = document.getElementById('phone').value;
    window.location.href = '?phone=' + phone;
}
</script>";
?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        background: #f5f5f5;
    }
    h2, h3 {
        color: #333;
    }
    table {
        background: white;
        width: 100%;
        margin: 10px 0;
    }
    th {
        background: #1e40af;
        color: white;
        padding: 10px;
    }
    pre {
        overflow-x: auto;
    }
</style>