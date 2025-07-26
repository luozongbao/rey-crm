<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is admin
requireLogin();
requireAdmin();

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
$user_id = $input['user_id'] ?? '';
$reason = $input['reason'] ?? '';

if (!$customer_id || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Customer ID and User ID are required']);
    exit;
}

try {
    global $pdo;
    
    // Verify customer exists
    $stmt = $pdo->prepare("SELECT company_name FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit;
    }
    
    // Verify user exists
    $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ? AND active = 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found or inactive']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // Assign customer
    $stmt = $pdo->prepare("UPDATE customers SET assigned_user_id = ? WHERE customer_id = ?");
    $stmt->execute([$user_id, $customer_id]);
    
    // Log the action
    $action_text = "Customer assigned to " . $user['username'];
    if ($reason) {
        $action_text .= " (Reason: $reason)";
    }
    
    // Use the new addSystemAction function
    $success = addSystemAction($customer_id, $action_text, $_SESSION['user_id'], "Quick assignment from dashboard");
    
    if (!$success) {
        throw new Exception("Failed to log assignment action");
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Customer '{$customer['company_name']}' successfully assigned to {$user['username']}"
    ]);
    
} catch (PDOException $e) {
    $pdo->rollback();
    logError("Error in quick assign: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while assigning the customer']);
}
?>
