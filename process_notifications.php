<?php
require_once 'config.php';

// Process pending notifications
$notification_files = glob('notifications/*.json');

if (empty($notification_files)) {
    exit("No pending notifications found.\n");
}

foreach ($notification_files as $file) {
    try {
        $data = json_decode(file_get_contents($file), true);
        
        if ($data && (time() - $data['timestamp']) < 3600) { // Process within 1 hour
            echo "Processing notification for complaint: " . $data['complaint_id'] . "\n";
            
            // Your original email sending function call
            if (function_exists('notify_complaint_registered')) {
                notify_complaint_registered(
                    $data['complaint_id'], 
                    $data['name'], 
                    $data['email'], 
                    $data['contact'], 
                    $data['subject']
                );
                echo "Notification sent successfully.\n";
            } else {
                echo "Notification function not found.\n";
            }
        } else {
            echo "Skipping expired notification: " . basename($file) . "\n";
        }
        
        // Delete processed file
        if (unlink($file)) {
            echo "Cleaned up: " . basename($file) . "\n";
        } else {
            echo "Failed to clean up: " . basename($file) . "\n";
        }
        
    } catch (Exception $e) {
        echo "Error processing " . basename($file) . ": " . $e->getMessage() . "\n";
    }
}

echo "Notification processing completed.\n";
?>