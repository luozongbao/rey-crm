<?php
// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'database_name_here');
define('DB_USER', getenv('DB_USER') ?: 'datebase_user_here');
define('DB_PASS', getenv('DB_PASS') ?: 'your_password_here');

// Error logging configuration
define('LOG_DIR', dirname(__DIR__) . '/logs');
define('DEBUG_MODE', false);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Set security headers
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Content-Security-Policy: default-src 'self'");
    
} catch (PDOException $e) {
    // Log error safely without exposing sensitive information
    error_log($e->getMessage(), 3, LOG_DIR . '/error.log');
    die("A database error occurred. Please try again later.");
}

// Function to safely log errors
function logError($message) {
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, LOG_DIR . '/error.log');
}