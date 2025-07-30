<?php
/**
 * AJAX Handler for Customer Status Changes
 * Processes status change requests and returns JSON response
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/customer_status_functions.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate input
$customer_id = (int)($_POST['customer_id'] ?? 0);
$new_status = trim($_POST['new_status'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$action = $_POST['action'] ?? '';

if ($action !== 'change_status') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

if (!$customer_id || !$new_status) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get current locale for messages
$current_locale = $_SESSION['locale'] ?? 'en';
$lang_file = "../languages/{$current_locale}/messages.php";
$messages = file_exists($lang_file) ? include $lang_file : include '../languages/en/messages.php';

try {
    // Check if user has permission to modify this customer
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'user';
    
    // Get customer details
    $stmt = $pdo->prepare("SELECT customer_id, assigned_user_id, created_by_user_id 
                          FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => $messages['customer_not_found'] ?? 'Customer not found']);
        exit;
    }
    
    // Check permissions: admin can modify any customer, user can only modify assigned customers
    if ($user_role !== 'admin' && 
        $customer['assigned_user_id'] != $user_id && 
        $customer['created_by_user_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => $messages['no_permission'] ?? 'No permission to modify this customer']);
        exit;
    }
    
    // Get current status for validation
    $stmt = $pdo->prepare("SELECT cs.status_key 
                          FROM customers c 
                          JOIN customer_statuses cs ON c.status_id = cs.id 
                          WHERE c.customer_id = ?");
    $stmt->execute([$customer_id]);
    $current_status = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_status) {
        echo json_encode(['success' => false, 'message' => $messages['invalid_customer'] ?? 'Invalid customer']);
        exit;
    }
    
    // Validate status transition
    if (!isValidStatusTransition($current_status['status_key'], $new_status)) {
        echo json_encode(['success' => false, 'message' => $messages['invalid_status_transition'] ?? 'Invalid status transition']);
        exit;
    }
    
    // Change the status
    $success = changeCustomerStatus($customer_id, $new_status, $user_id, $notes);
    
    if ($success) {
        // Get the new status name for the response
        $new_status_info = getCustomerStatusByKey($new_status, $current_locale);
        $status_name = $new_status_info['name'] ?? $new_status;
        
        echo json_encode([
            'success' => true, 
            'message' => sprintf(
                $messages['status_changed_to'] ?? 'Status changed to %s',
                $status_name
            )
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $messages['error_changing_status'] ?? 'Error changing status']);
    }
    
} catch (Exception $e) {
    error_log("Status change error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $messages['error_occurred'] ?? 'An error occurred']);
}
?>
