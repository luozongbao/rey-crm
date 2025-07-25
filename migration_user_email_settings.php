<?php
/**
 * Migration script to add user_email_settings table
 * This should be run once to add the new table to existing databases
 */

require_once 'includes/functions.php';

try {
    // Check if table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_email_settings'");
    
    if ($stmt->rowCount() == 0) {
        // Table doesn't exist, create it
        $sql = "CREATE TABLE user_email_settings (
            user_email_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            smtp_host VARCHAR(255),
            smtp_port INT,
            smtp_username VARCHAR(255),
            smtp_password VARCHAR(255),
            smtp_from_email VARCHAR(255),
            smtp_from_name VARCHAR(255),
            smtp_encryption ENUM('tls', 'ssl', 'none') DEFAULT 'tls',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_email_settings (user_id)
        )";
        
        $pdo->exec($sql);
        echo "✓ user_email_settings table created successfully.\n";
    } else {
        echo "✓ user_email_settings table already exists.\n";
    }
    
    echo "Migration completed successfully.\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>