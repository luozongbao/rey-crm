<?php
/**
 * Simple script to add default timezone setting to database
 * Run once to initialize the timezone setting
 */
require_once 'includes/config.php';

try {
    // Add default timezone setting
    $stmt = $pdo->prepare('INSERT IGNORE INTO settings (setting_name, value) VALUES (?, ?)');
    $result = $stmt->execute(['default_timezone', 'Asia/Bangkok']);
    
    if ($result) {
        echo "✓ Default timezone setting added successfully.\n";
    } else {
        echo "• Default timezone setting already exists or failed to add.\n";
    }
    
    // Verify the setting
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE setting_name = ?');
    $stmt->execute(['default_timezone']);
    $result = $stmt->fetch();
    
    if ($result) {
        echo "✓ Current default timezone: " . $result['value'] . "\n";
    } else {
        echo "✗ Could not verify default timezone setting.\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
