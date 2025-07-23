<?php
/**
 * Migration script for Customer Assignment Feature
 * This script adds the necessary columns and constraints to support customer assignment to users
 */

require_once '../includes/config.php';

echo "Starting Customer Assignment Migration...\n";

try {
    $pdo->beginTransaction();
    
    echo "1. Adding assigned_user_id column...\n";
    try {
        $pdo->exec("ALTER TABLE customers ADD COLUMN assigned_user_id INT NULL");
        echo "   ✓ assigned_user_id column added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "   ✓ assigned_user_id column already exists\n";
        } else {
            throw $e;
        }
    }
    
    echo "2. Adding created_by_user_id column...\n";
    try {
        $pdo->exec("ALTER TABLE customers ADD COLUMN created_by_user_id INT NULL");
        echo "   ✓ created_by_user_id column added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "   ✓ created_by_user_id column already exists\n";
        } else {
            throw $e;
        }
    }
    
    echo "3. Adding foreign key constraints...\n";
    try {
        $pdo->exec("ALTER TABLE customers ADD CONSTRAINT fk_customers_assigned_user 
                    FOREIGN KEY (assigned_user_id) REFERENCES users(user_id) ON DELETE SET NULL");
        echo "   ✓ fk_customers_assigned_user constraint added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "   ✓ fk_customers_assigned_user constraint already exists\n";
        } else {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE customers ADD CONSTRAINT fk_customers_created_by 
                    FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL");
        echo "   ✓ fk_customers_created_by constraint added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "   ✓ fk_customers_created_by constraint already exists\n";
        } else {
            throw $e;
        }
    }
    
    echo "4. Assigning existing customers to admin users...\n";
    
    // Find the first admin user
    $adminUser = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' ORDER BY user_id LIMIT 1")->fetch();
    
    if ($adminUser) {
        $stmt = $pdo->prepare("UPDATE customers SET assigned_user_id = ?, created_by_user_id = ? 
                               WHERE assigned_user_id IS NULL");
        $affected = $stmt->execute([$adminUser['user_id'], $adminUser['user_id']]);
        $count = $stmt->rowCount();
        echo "   ✓ Assigned $count existing customers to admin user (ID: {$adminUser['user_id']})\n";
    } else {
        echo "   ⚠ No admin user found. Existing customers will remain unassigned.\n";
        echo "     Please ensure at least one admin user exists and run this migration again.\n";
    }
    
    echo "5. Adding database indexes for performance...\n";
    try {
        $pdo->exec("CREATE INDEX idx_customers_assigned_user ON customers(assigned_user_id)");
        echo "   ✓ Index on assigned_user_id created\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "   ✓ Index on assigned_user_id already exists\n";
        } else {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_customers_created_by ON customers(created_by_user_id)");
        echo "   ✓ Index on created_by_user_id created\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "   ✓ Index on created_by_user_id already exists\n";
        } else {
            throw $e;
        }
    }
    
    $pdo->commit();
    echo "\n✅ Migration completed successfully!\n";
    echo "\nNext steps:\n";
    echo "- Update your PHP code to use the new customer assignment features\n";
    echo "- Test the assignment functionality with different user roles\n";
    echo "- Verify that filters work correctly\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "\nPlease check the error above and try again.\n";
    exit(1);
}
?>
