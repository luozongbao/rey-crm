<?php
/**
 * Migration script to add email settings columns to users table
 * This should be run once to add the new columns to existing databases
 */

require_once 'includes/functions.php';

try {
    // Check if the email settings columns already exist in users table
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'smtp_host'");
    
    if ($stmt->rowCount() == 0) {
        // Columns don't exist, add them
        $sql = "ALTER TABLE users ADD COLUMN (
            smtp_host VARCHAR(255),
            smtp_port INT,
            smtp_username VARCHAR(255),
            smtp_password VARCHAR(255),
            smtp_from_email VARCHAR(255),
            smtp_from_name VARCHAR(255),
            smtp_encryption ENUM('tls', 'ssl', 'none') DEFAULT 'tls'
        )";
        
        $pdo->exec($sql);
        echo "✓ Email settings columns added to users table successfully.\n";
    } else {
        echo "✓ Email settings columns already exist in users table.\n";
    }
    
    // Check if the old user_email_settings table exists and migrate data if it does
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_email_settings'");
    
    if ($stmt->rowCount() > 0) {
        echo "Found existing user_email_settings table. Migrating data...\n";
        
        // Migrate data from user_email_settings to users table
        $migrate_sql = "UPDATE users u 
                       JOIN user_email_settings ues ON u.user_id = ues.user_id 
                       SET u.smtp_host = ues.smtp_host,
                           u.smtp_port = ues.smtp_port,
                           u.smtp_username = ues.smtp_username,
                           u.smtp_password = ues.smtp_password,
                           u.smtp_from_email = ues.smtp_from_email,
                           u.smtp_from_name = ues.smtp_from_name,
                           u.smtp_encryption = ues.smtp_encryption";
        
        $pdo->exec($migrate_sql);
        
        // Drop the old table
        $pdo->exec("DROP TABLE user_email_settings");
        echo "✓ Data migrated from user_email_settings table and old table removed.\n";
    }
    
    echo "Migration completed successfully.\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>