<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'reycrmdb');
define('DB_USER', 'reycrmdbuser');
define('DB_PASS', 'P@ssw0rd4reycrmdbuser');

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
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
} catch (PDOException $e) {
    // Log the error but don't display details publicly
    error_log("Database connection failed: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}

// Function to safely log errors
function logError($message) {
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, LOG_DIR . '/error.log');
}