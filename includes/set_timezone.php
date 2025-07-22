<?php
/**
 * Server-side timezone setting endpoint
 * Handles AJAX requests to set user's timezone preference
 */

require_once 'config.php';
require_once 'functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['timezone'])) {
        throw new Exception('Timezone not provided');
    }
    
    $timezone = $input['timezone'];
    
    // Validate timezone
    if (!in_array($timezone, timezone_identifiers_list())) {
        throw new Exception('Invalid timezone: ' . $timezone);
    }
    
    // Set user's timezone using our function
    if (setUserTimezone($timezone)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Timezone set successfully',
            'timezone' => $timezone
        ]);
    } else {
        throw new Exception('Failed to set timezone');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>
