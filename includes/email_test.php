<?php
// Test email functionality
require_once 'functions.php';
session_start();

// Only admins can access this
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if test_email parameter exists
if (!isset($_POST['test_email']) || empty($_POST['test_email'])) {
    echo json_encode(['success' => false, 'message' => 'No recipient email provided']);
    exit;
}

$test_email = filter_var($_POST['test_email'], FILTER_SANITIZE_EMAIL);
if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Get SMTP settings from database
try {
    $smtp_settings = getSMTPSettings();
    
    // Check if required SMTP settings are configured
    if (empty($smtp_settings['smtp_host']) || empty($smtp_settings['smtp_port']) || 
        empty($smtp_settings['smtp_username']) || empty($smtp_settings['smtp_password'])) {
        echo json_encode(['success' => false, 'message' => 'SMTP settings are not fully configured']);
        exit;
    }
    
    // Try sending a test email
    $subject = "Test Email from Rey CRM";
    $body = "<html><body>
                <h2>Email Test</h2>
                <p>This is a test email from your Rey CRM system. If you received this email, your SMTP settings are configured correctly.</p>
                <p>Time sent: " . date('Y-m-d H:i:s') . "</p>
                <p><strong>No action is required.</strong></p>
             </body></html>";
    
    // Use the new sendEmail function
    require_once __DIR__ . '/../vendor/autoload.php';
    $result = sendEmail($test_email, $subject, $body);
    
    // Return response
    echo json_encode($result);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
