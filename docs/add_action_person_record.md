# Add Action Person Record Implementation

## Overview
Add user tracking to action_history table to record who created each activity/followup, replacing the current system that infers user through customer assignments.

## Database Changes

### 1. Update action_history table structure
```sql
ALTER TABLE action_history ADD COLUMN user_id INT;
ALTER TABLE action_history ADD FOREIGN KEY (user_id) REFERENCES users(id);
```

### 2. Update existing records
```sql
-- Set all existing activities to user_id = 2
UPDATE action_history SET user_id = 2 WHERE user_id IS NULL;
```

## Code Changes

### 1. Update Activity Creation Functions
- **File**: `includes/functions.php`
- **Functions to modify**:
  - `addCustomerHistory()` - Add user_id parameter and include in INSERT
  - `addFollowup()` - Add user_id parameter and include in INSERT
  - Any other functions that create action_history records

**Changes needed**:
```php
// Before
function addCustomerHistory($customer_id, $action, $details = null) {
    // INSERT without user_id
}

// After  
function addCustomerHistory($customer_id, $action, $details = null, $user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'];
    }
    // INSERT including user_id
}
```

### 2. Update Activity Creation Pages
- **Files to update**:
  - `history_form.php` - Pass session user_id to addCustomerHistory()
  - `all_followups.php` - Pass session user_id when creating followups
  - Any other pages that create activities

### 3. Update Dashboard Queries

#### Customer Dashboard (`customer_dashboard.php`)
- Remove JOINs with users table through customer assignments
- Add direct JOIN: `LEFT JOIN users u ON ah.user_id = u.id`
- Update SELECT to get username from direct relationship

#### Activities Dashboard (`activities_dashboard.php`)
- Update activity queries to use `ah.user_id` instead of assignment-based user lookup
- Modify filters to work with direct user relationship

#### All Activities (`all_activities.php`)
- Update main query to use direct user relationship
- Modify any user-based filtering logic

#### All Followups (`all_followups.php`) 
- Update queries to use `ah.user_id`
- Update display logic for showing who created followups

#### Admin Dashboards
- **File**: `admin_customer_management.php`
- Update performance metrics and activity lists to use direct user relationship
- Modify any reports that show activity creators

### 4. Update Include Files

#### Dashboard Customer List (`includes/dashboard_customer_list.php`)
- Update recent activities query to use direct user relationship
- Modify any user display logic

#### Admin Tabs
- Update queries in admin tab files to use `ah.user_id`

## Implementation Steps

### Phase 1: Database Migration
1. Create migration script to add user_id column
2. Update all existing records to user_id = 2
3. Test database changes

### Phase 2: Core Functions
1. Update `addCustomerHistory()` function
2. Update `addFollowup()` function  
3. Update any other activity creation functions
4. Test activity creation still works

### Phase 3: Creation Pages
1. Update `history_form.php`
2. Update followup creation in `all_followups.php`
3. Update any other pages that create activities
4. Test end-to-end activity creation

### Phase 4: Dashboard Updates
1. Update `customer_dashboard.php` queries
2. Update `activities_dashboard.php` queries  
3. Update `all_activities.php` queries
4. Update `all_followups.php` queries
5. Test all dashboards show correct user information

### Phase 5: Admin Updates
1. Update `admin_customer_management.php`
2. Update admin tab include files
3. Update any reports or analytics
4. Test admin functionality

### Phase 6: Testing & Validation
1. Test activity creation from all entry points
2. Verify all dashboards show correct user information
3. Test admin reports and analytics
4. Verify no broken queries or missing user information

## Benefits
- Direct relationship between activities and users
- More accurate activity tracking
- Better performance (fewer JOINs)
- Clearer data model
- Support for activities not tied to customer assignments

## Considerations
- Ensure all activity creation points are updated
- Maintain backward compatibility during transition
- Update any API endpoints that return activity data
- Consider adding user_id as required field for new activities
