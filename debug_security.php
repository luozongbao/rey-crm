<?php
// Debug script for security dashboard
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing security dashboard components...\n";

try {
    // Test 1: Include config and functions
    echo "1. Including config.php... ";
    require_once 'includes/config.php';
    echo "OK\n";
    
    echo "2. Including functions.php... ";
    require_once 'includes/functions.php';
    echo "OK\n";
    
    // Test 2: Check database connection
    echo "3. Testing database connection... ";
    $stmt = $pdo->query("SELECT 1");
    echo "OK\n";
    
    // Test 3: Test language initialization
    echo "4. Testing language initialization... ";
    session_start();
    $_SESSION['user_id'] = 2; // Set a test user
    $_SESSION['role'] = 'admin';
    $current_language = initLanguage();
    echo "OK (Language: $current_language)\n";
    
    // Test 4: Test authentication functions
    echo "5. Testing authentication functions... ";
    $auth_result = checkAuth();
    $admin_result = hasRole('admin');
    echo "checkAuth: " . ($auth_result ? 'true' : 'false') . ", hasRole(admin): " . ($admin_result ? 'true' : 'false') . "\n";
    
    // Test 5: Test security metrics function
    echo "6. Testing getSecurityMetrics... ";
    $metrics = getSecurityMetrics($pdo);
    echo "OK - " . count($metrics) . " metrics retrieved\n";
    
    // Test 6: Test security alerts function
    echo "7. Testing getSecurityAlerts... ";
    $alerts = getSecurityAlerts($pdo);
    echo "OK - " . count($alerts) . " alerts retrieved\n";
    
    echo "\nAll tests passed! Security dashboard should work.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
