# Assign Customer to User Feature Implementation

## Overview
This feature allows administrators or the user who created a customer to assign/reassign customers to specific users. It also provides filtering options to show only customers assigned to the current user.

## Feature Requirements
1. **Customer Assignment**: Ability to assign customers to users (admin or creator only)
2. **Default Assignment**: New customers are automatically assigned to the user who created them
3. **Filtering**: Checkbox "show only my customers" in various pages (default: checked)
4. **Pages to Update**: Customer form, All Activities, All Follow-ups, Send Email

## Implementation Steps

### Step 1: Database Schema Changes

#### 1.1 Add user assignment columns to customers table
```sql
-- Add columns to track user assignment and creation
ALTER TABLE customers ADD COLUMN assigned_user_id INT NULL;
ALTER TABLE customers ADD COLUMN created_by_user_id INT NULL;

-- Add foreign key constraints
ALTER TABLE customers ADD CONSTRAINT fk_customers_assigned_user 
    FOREIGN KEY (assigned_user_id) REFERENCES users(user_id) ON DELETE SET NULL;
ALTER TABLE customers ADD CONSTRAINT fk_customers_created_by 
    FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL;

-- Update existing customers to be assigned to the first admin user (or create a migration script)
UPDATE customers SET assigned_user_id = (SELECT user_id FROM users WHERE role = 'admin' LIMIT 1),
                     created_by_user_id = (SELECT user_id FROM users WHERE role = 'admin' LIMIT 1)
WHERE assigned_user_id IS NULL;
```

#### 1.2 Update database.sql file
Update the main database schema file to include these new columns for fresh installations.

### Step 2: Core Functions Update (includes/functions.php)

#### 2.1 Add new utility functions
```php
/**
 * Get all users for assignment dropdown
 */
function getAllUsers() {
    global $pdo;
    $stmt = $pdo->query("SELECT user_id, username FROM users ORDER BY username");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if current user can assign customers (admin or creator)
 */
function canAssignCustomer($customer_id = null) {
    if (isAdmin()) {
        return true;
    }
    
    if ($customer_id) {
        $customer = getCustomerById($customer_id);
        return $customer && $customer['created_by_user_id'] == $_SESSION['user_id'];
    }
    
    return false;
}

/**
 * Get customers assigned to current user or all customers (for admin)
 */
function getMyCustomers($showOnlyMine = true) {
    global $pdo;
    
    if (!$showOnlyMine || isAdmin()) {
        return getAllCustomers();
    }
    
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE assigned_user_id = ? ORDER BY company_name");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update customer assignment
 */
function assignCustomerToUser($customer_id, $user_id) {
    global $pdo;
    
    if (!canAssignCustomer($customer_id)) {
        return false;
    }
    
    $stmt = $pdo->prepare("UPDATE customers SET assigned_user_id = ? WHERE customer_id = ?");
    return $stmt->execute([$user_id, $customer_id]);
}
```

#### 2.2 Update existing functions

**Update getPaginatedCustomers function:**
```php
// Add parameter for filtering by user assignment
function getPaginatedCustomers($page = 1, $perPage = 10, $search = '', $location = '', $sort = 'created_at', $order = 'desc', $showOnlyMine = true) {
    // Add JOIN with users table and WHERE clause for assignment
    // Modify the existing query to include user filtering logic
}
```

**Update getFilteredActivities function:**
```php
// Add parameter and logic for filtering by assigned customers
function getFilteredActivities($customer_id = '', $date_from = '', $date_to = '', $sort = 'action_datetime', $order = 'desc', $customer_status = '', $showOnlyMine = true) {
    // Add WHERE clause to filter by assigned_user_id when $showOnlyMine is true
}
```

**Update getFilteredFollowups function:**
```php
// Similar updates as getFilteredActivities
function getFilteredFollowups($customer_id = '', $date_from = '', $date_to = '', $sort = 'follow_up_datetime', $order = 'asc', $customer_status = '', $showOnlyMine = true) {
    // Add filtering logic for assigned customers
}
```

**Update addCustomer function:**
```php
function addCustomer($data) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO customers 
                          (company_name, address, country, province, company_type, contact_phone, 
                           contact_email, website, status, notes, assigned_user_id, created_by_user_id) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([
        $data['company_name'],
        $data['address'],
        $data['country'],
        $data['province'],
        $data['company_type'],
        $data['contact_phone'],
        $data['contact_email'],
        $data['website'],
        $data['status'],
        $data['notes'],
        $_SESSION['user_id'], // assigned_user_id - assign to current user by default
        $_SESSION['user_id']  // created_by_user_id
    ]);
}
```

### Step 3: Customer Form Updates (customer_form.php)

#### 3.1 Add assignment functionality
- Add dropdown to select assigned user (only if user can assign)
- Add hidden field for created_by_user_id on new customers
- Update form submission logic to handle assignment

#### 3.2 Form HTML additions
```php
<?php if (canAssignCustomer($customer_id)): ?>
<div class="form-group">
    <label for="assigned_user_id"><?= __('assigned_to') ?>:</label>
    <select name="assigned_user_id" id="assigned_user_id" class="form-control">
        <?php foreach (getAllUsers() as $user): ?>
            <option value="<?= $user['user_id'] ?>" 
                    <?= ($customer['assigned_user_id'] ?? $_SESSION['user_id']) == $user['user_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($user['username']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>
```

### Step 4: Customer List Updates (customers.php)

#### 4.1 Add "Show Only My Customers" checkbox
```php
<!-- Add to filter form -->
<div class="form-group">
    <label>
        <input type="checkbox" name="show_only_mine" value="1" 
               <?= (!isset($_GET['show_only_mine']) || $_GET['show_only_mine']) ? 'checked' : '' ?>>
        <?= __('show_only_my_customers') ?>
    </label>
</div>
```

#### 4.2 Update customer retrieval logic
```php
$showOnlyMine = !isset($_GET['show_only_mine']) || $_GET['show_only_mine'];
$result = getPaginatedCustomers($page, $perPage, $search, $location, $sort, $order, $showOnlyMine);
```

### Step 5: All Activities Updates (all_activities.php)

#### 5.1 Add checkbox filter
Similar to customers.php, add the "Show Only My Customers" checkbox to the filter form.

#### 5.2 Update activities retrieval
```php
$showOnlyMine = !isset($_GET['show_only_mine']) || $_GET['show_only_mine'];
$activities = getFilteredActivities($customer_id, $date_from, $date_to, $sort, $order, $customer_status, $showOnlyMine);
```

#### 5.3 Update customer dropdown
Populate the customer dropdown with only assigned customers when "show only mine" is checked.

### Step 6: All Follow-ups Updates (all_followups.php)

#### 6.1 Add checkbox filter
Add the same "Show Only My Customers" checkbox.

#### 6.2 Update follow-ups retrieval
```php
$showOnlyMine = !isset($_GET['show_only_mine']) || $_GET['show_only_mine'];
$followups = getFilteredFollowups($customer_id, $date_from, $date_to, $sort, $order, $customer_status, $showOnlyMine);
```

### Step 7: Send Email Updates (send_email.php)

#### 7.1 Add checkbox filter
Add "Show Only My Customers" checkbox to the recipient selection area.

#### 7.2 Update recipient list
```php
// Modify the recipients query to filter by assigned customers
$showOnlyMine = !isset($_GET['show_only_mine']) || $_GET['show_only_mine'];

if ($showOnlyMine && !isAdmin()) {
    $stmt = $pdo->prepare("
        SELECT c.customer_id, c.company_name, c.contact_email as customer_email,
               cp.contact_id, cp.name as contact_name, cp.contact_email
        FROM customers c
        LEFT JOIN contact_persons cp ON c.customer_id = cp.customer_id
        WHERE (c.contact_email IS NOT NULL OR cp.contact_email IS NOT NULL)
        AND c.assigned_user_id = ?
        ORDER BY c.company_name, cp.name
    ");
    $stmt->execute([$_SESSION['user_id']]);
} else {
    // Existing query for all customers
}
```

### Step 8: Language Support

#### 8.1 Add new language keys
Add to `languages/en/messages.php`:
```php
'assigned_to' => 'Assigned To',
'show_only_my_customers' => 'Show Only My Customers',
'assign_customer' => 'Assign Customer',
'customer_assigned_successfully' => 'Customer assigned successfully',
```

Add to `languages/zh-cn/messages.php`:
```php
'assigned_to' => '分配给',
'show_only_my_customers' => '仅显示我的客户',
'assign_customer' => '分配客户',
'customer_assigned_successfully' => '客户分配成功',
```

### Step 9: Database Migration Script

#### 9.1 Create migration script (maintenance/migrate_customer_assignment.php)
```php
<?php
require_once '../includes/config.php';

try {
    $pdo->beginTransaction();
    
    // Add columns if they don't exist
    $pdo->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS assigned_user_id INT NULL");
    $pdo->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS created_by_user_id INT NULL");
    
    // Add foreign key constraints
    $pdo->exec("ALTER TABLE customers ADD CONSTRAINT IF NOT EXISTS fk_customers_assigned_user 
                FOREIGN KEY (assigned_user_id) REFERENCES users(user_id) ON DELETE SET NULL");
    $pdo->exec("ALTER TABLE customers ADD CONSTRAINT IF NOT EXISTS fk_customers_created_by 
                FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL");
    
    // Assign existing customers to first admin
    $adminUser = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    if ($adminUser) {
        $pdo->prepare("UPDATE customers SET assigned_user_id = ?, created_by_user_id = ? 
                       WHERE assigned_user_id IS NULL")
            ->execute([$adminUser['user_id'], $adminUser['user_id']]);
    }
    
    $pdo->commit();
    echo "Migration completed successfully\n";
} catch (Exception $e) {
    $pdo->rollback();
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
```

### Step 10: Testing Checklist

#### 10.1 Functionality Testing
- [ ] New customers are automatically assigned to the creator
- [ ] Admin can reassign any customer
- [ ] Regular users can only reassign customers they created
- [ ] "Show only my customers" filter works correctly (default: checked)
- [ ] Customer dropdown in activities/follow-ups respects the filter
- [ ] Send email recipient list respects the filter
- [ ] All pages maintain filter state when navigating

#### 10.2 Permission Testing
- [ ] Regular users cannot see assignment dropdown for customers they didn't create
- [ ] Regular users can only see their assigned customers when filter is on
- [ ] Admin users can see all customers and assignment options
- [ ] Database constraints prevent invalid assignments

#### 10.3 UI/UX Testing
- [ ] All new UI elements are properly styled
- [ ] Language switching works for new text
- [ ] Checkboxes maintain state during page navigation
- [ ] Form submissions work correctly with new fields

### Step 11: Documentation Updates

#### 11.1 Update README.md
Add section about customer assignment feature and user roles.

#### 11.2 Update requirements.md
Document the new permission requirements and user roles.

## Implementation Priority

1. **High Priority**: Database changes, core functions, customer form
2. **Medium Priority**: Customer list, activities, follow-ups filtering
3. **Low Priority**: Send email filtering, language support, documentation

## Security Considerations

- Always validate user permissions before allowing assignment changes
- Use prepared statements for all database queries
- Sanitize all user inputs
- Implement proper session management for user context

## Performance Considerations

- Add database indexes on `assigned_user_id` and `created_by_user_id` columns
- Consider pagination impact when filtering by user assignment
- Cache user lists for assignment dropdowns if needed

## Rollback Plan

If issues arise, the feature can be rolled back by:
1. Removing the assignment-related WHERE clauses from queries
2. Setting all `assigned_user_id` to NULL to show all customers to all users
3. Hiding the assignment UI elements via CSS or PHP conditionals
