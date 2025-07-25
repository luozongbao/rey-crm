<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$customer_id = $input['customer_id'] ?? '';

if (!$customer_id) {
    echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
    exit;
}

// Check if user can unassign this customer
if (!canAssignCustomer($customer_id)) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to unassign this customer']);
    exit;
}

try {
    global $pdo;
    
    // Get current assignment info
    $stmt = $pdo->prepare("
        SELECT c.company_name, c.assigned_user_id, u.username 
        FROM customers c 
        LEFT JOIN users u ON c.assigned_user_id = u.user_id 
        WHERE c.customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit;
    }
    
    if (!$customer['assigned_user_id']) {
        echo json_encode(['success' => false, 'message' => 'Customer is not currently assigned']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Unassign customer
    $stmt = $pdo->prepare("UPDATE customers SET assigned_user_id = NULL WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    
    // Log the action in action_history table
    $action_text = "Customer unassigned from " . $customer['username'] . " via customer form";
    
    $stmt = $pdo->prepare("
        INSERT INTO action_history (customer_id, action_datetime, action, response, next_step, follow_up_datetime, notes) 
        VALUES (?, NOW(), ?, '', '', DATE_ADD(NOW(), INTERVAL 30 DAY), ?)
    ");
    $stmt->execute([$customer_id, $action_text, "Customer form unassignment"]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Customer '{$customer['company_name']}' successfully unassigned from {$customer['username']}"
    ]);
    
} catch (PDOException $e) {
    $pdo->rollback();
    logError("Error in customer form unassign: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while unassigning the customer']);
}
?>
