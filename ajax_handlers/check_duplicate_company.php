<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Ensure user is logged in
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

$company_name = trim($input['company_name'] ?? '');

if (empty($company_name)) {
    echo json_encode(['error' => 'Company name is required']);
    exit;
}

try {
    global $pdo;
    
    // Check if PDO connection exists
    if (!$pdo) {
        echo json_encode(['error' => 'Database connection not available']);
        exit;
    }
    
    // Check for duplicate company name
    $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE company_name = ?");
    $stmt->execute([$company_name]);
    $result = $stmt->fetch();
    
    echo json_encode([
        'exists' => $result !== false,
        'company_name' => $company_name
    ]);
    
} catch (Exception $e) {
    logError("Error checking duplicate company name: " . $e->getMessage());
    echo json_encode(['error' => 'An error occurred while checking company name']);
}
?>
