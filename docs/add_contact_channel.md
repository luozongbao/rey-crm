# Adding Contact Channel to Action History

## Overview
This document outlines the implementation plan for adding a contact channel field to the action_history table. This enhancement will allow users to specify how they contacted customers (email, phone, meeting, etc.) and will improve reporting capabilities in performance metrics.

## Database Changes

### 1. Modify action_history table structure

Add a new `contact_channel` field to the action_history table:

```sql
ALTER TABLE action_history 
ADD COLUMN contact_channel ENUM(
    'Email', 
    'Phone Call', 
    'WhatsApp', 
    'SMS', 
    'In-Person Meeting', 
    'Video Call', 
    'LinkedIn', 
    'WeChat', 
    'Other'
) NOT NULL DEFAULT 'Other' 
AFTER action;
```

**Rationale**: 
- Using ENUM for data consistency and better performance
- Placed after `action` field for logical grouping
- Default to 'Other' to handle existing records and ensure non-null values
- Covers common communication channels used in B2B sales

### 2. Update database.sql file

Add the contact_channel field to the CREATE TABLE statement for new installations:

```sql
CREATE TABLE IF NOT EXISTS action_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    contact_id INT,
    action_datetime DATETIME NOT NULL,
    action TEXT NOT NULL,
    contact_channel ENUM(
        'Email', 
        'Phone Call', 
        'WhatsApp', 
        'SMS', 
        'In-Person Meeting', 
        'Video Call', 
        'LinkedIn', 
        'WeChat', 
        'Other'
    ) NOT NULL DEFAULT 'Other',
    response TEXT NOT NULL,
    next_step TEXT NOT NULL,
    follow_up_datetime DATETIME NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contact_persons(contact_id) ON DELETE SET NULL
);
```

## Code Changes

### 3. Update history_form.php

#### 3.1 Add contact_channel to form HTML
In the form section (around line 120-150), add after the action field:

```php
<div class="form-group">
    <label for="contact_channel"><?php echo __('contact_channel'); ?> *</label>
    <select name="contact_channel" id="contact_channel" required>
        <option value=""><?php echo __('select_contact_channel'); ?></option>
        <option value="Email" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'Email') ? 'selected' : ''; ?>>
            <?php echo __('email'); ?>
        </option>
        <option value="Phone Call" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'Phone Call') ? 'selected' : ''; ?>>
            <?php echo __('phone_call'); ?>
        </option>
        <option value="WhatsApp" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'WhatsApp') ? 'selected' : ''; ?>>
            <?php echo __('whatsapp'); ?>
        </option>
        <option value="SMS" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'SMS') ? 'selected' : ''; ?>>
            <?php echo __('sms'); ?>
        </option>
        <option value="In-Person Meeting" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'In-Person Meeting') ? 'selected' : ''; ?>>
            <?php echo __('in_person_meeting'); ?>
        </option>
        <option value="Video Call" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'Video Call') ? 'selected' : ''; ?>>
            <?php echo __('video_call'); ?>
        </option>
        <option value="LinkedIn" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'LinkedIn') ? 'selected' : ''; ?>>
            <?php echo __('linkedin'); ?>
        </option>
        <option value="WeChat" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'WeChat') ? 'selected' : ''; ?>>
            <?php echo __('wechat'); ?>
        </option>
        <option value="Other" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'Other') ? 'selected' : ''; ?>>
            <?php echo __('other'); ?>
        </option>
    </select>
</div>
```

#### 3.2 Update form processing logic
In the POST processing section (around line 25-45), add contact_channel to the data array:

```php
$data = [
    'customer_id' => $customer_id,
    'contact_id' => !empty($_POST['contact_id']) ? $_POST['contact_id'] : null,
    'action_datetime' => $action_datetime,
    'action' => $_POST['action'],
    'contact_channel' => $_POST['contact_channel'],
    'response' => $_POST['response'],
    'next_step' => $_POST['next_step'],
    'follow_up_datetime' => $follow_up_datetime,
    'notes' => $_POST['notes'] ?? null
];
```

#### 3.3 Update SQL statements
Update INSERT statement (around line 55):
```php
$stmt = $pdo->prepare("INSERT INTO action_history 
                     (customer_id, contact_id, action_datetime, action, contact_channel, response, next_step, follow_up_datetime, notes) 
                     VALUES (:customer_id, :contact_id, :action_datetime, :action, :contact_channel, :response, :next_step, :follow_up_datetime, :notes)");
```

Update UPDATE statement (around line 70):
```php
$stmt = $pdo->prepare("UPDATE action_history SET 
                     customer_id = :customer_id, 
                     contact_id = :contact_id, 
                     action_datetime = :action_datetime, 
                     action = :action, 
                     contact_channel = :contact_channel,
                     response = :response, 
                     next_step = :next_step, 
                     follow_up_datetime = :follow_up_datetime, 
                     notes = :notes 
                     WHERE history_id = :history_id");
```

### 4. Update all_activities.php

#### 4.1 Add contact_channel to display table
In the table header section (around line 200), add:
```php
<th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'contact_channel', 'order' => $order == 'asc' ? 'desc' : 'asc'])); ?>"><?php echo __('contact_channel'); ?></a></th>
```

In the table body section (around line 230), add:
```php
<td><?php echo htmlspecialchars(__($activity['contact_channel'])); ?></td>
```

#### 4.2 Update CSV export
In the CSV export headers (around line 45):
```php
$headers = [
    __('customer_name'),
    __('location'),
    __('status'),
    __('action'),
    __('contact_channel'),
    __('date_time'),
    __('response')
];
```

In the CSV export data rows (around line 60):
```php
$row = [
    $activity['company_name'],
    $location,
    __($activity['customer_status']),
    $activity['action'],
    __($activity['contact_channel']),
    formatDateTimeCompact($activity['action_datetime']),
    $activity['response']
];
```

### 5. Update all_followups.php

#### 5.1 Add contact_channel to display table
Similar updates as in all_activities.php for table headers and data rows.

#### 5.2 Update CSV export
Add contact_channel to CSV headers and data rows similar to all_activities.php.

### 6. Update includes/functions.php

#### 6.1 Update getFilteredActivities function
Add contact_channel to SELECT statement (around line 630):
```php
$query = "SELECT ah.*, c.company_name, c.status as customer_status, c.province, c.customer_id
          FROM action_history ah
          JOIN customers c ON ah.customer_id = c.customer_id
          WHERE 1=1";
```
No changes needed as we're using `ah.*` which will include the new field.

#### 6.2 Update getFilteredFollowups function
Similar to getFilteredActivities - no changes needed if using `ah.*`.

#### 6.3 Add contact_channel sorting support
Update valid sorts array in getFilteredActivities and getFilteredFollowups:
```php
$validSorts = ['company_name', 'action_datetime', 'customer_status', 'contact_channel'];
```

### 7. Update admin_performance_tab.php

#### 7.1 Enhanced performance metrics
Replace the current contact method detection logic (around line 35-40) with proper contact_channel based counting:

```php
$query = "
    SELECT 
        u.username,
        u.user_id,
        COUNT(DISTINCT c.customer_id) as customers_assigned,
        COUNT(ah.history_id) as total_activities,
        COUNT(CASE WHEN ah.contact_channel = 'Email' THEN 1 END) as emails_sent,
        COUNT(CASE WHEN ah.contact_channel IN ('Phone Call', 'WhatsApp', 'SMS') THEN 1 END) as calls_made,
        COUNT(CASE WHEN ah.contact_channel IN ('In-Person Meeting', 'Video Call') THEN 1 END) as meetings_held,
        COUNT(CASE WHEN ah.contact_channel = 'LinkedIn' THEN 1 END) as linkedin_contacts,
        COUNT(CASE WHEN ah.contact_channel = 'WeChat' THEN 1 END) as wechat_contacts,
        COUNT(CASE WHEN ah.follow_up_datetime IS NOT NULL AND ah.action_datetime BETWEEN ? AND ? THEN 1 END) as followups_scheduled,
        MAX(ah.action_datetime) as last_activity_date,
        COALESCE(nc.new_customers_created, 0) as new_customers_created
    FROM users u
    LEFT JOIN customers c ON u.user_id = c.assigned_user_id
    LEFT JOIN action_history ah ON c.customer_id = ah.customer_id AND ah.action_datetime BETWEEN ? AND ?
    LEFT JOIN (
        SELECT created_by_user_id, COUNT(*) as new_customers_created
        FROM customers c2 
        WHERE created_by_user_id IS NOT NULL AND c2.created_at BETWEEN ? AND ?
        GROUP BY created_by_user_id
    ) nc ON u.user_id = nc.created_by_user_id
    GROUP BY u.user_id, u.username
    ORDER BY total_activities DESC
";
```

#### 7.2 Add contact channel breakdown section
Add a new section for contact channel analytics (around line 200):

```php
// Contact Channel Breakdown
$channel_query = "
    SELECT 
        ah.contact_channel,
        COUNT(*) as count,
        COUNT(DISTINCT ah.customer_id) as unique_customers,
        AVG(CASE WHEN c.status IN ('New Customer', 'Active Customer', 'Closed Won') THEN 1 ELSE 0 END) * 100 as success_rate
    FROM action_history ah
    JOIN customers c ON ah.customer_id = c.customer_id
    WHERE ah.action_datetime $date_filter
    GROUP BY ah.contact_channel
    ORDER BY count DESC
";

$stmt = $pdo->prepare($channel_query);
if (!empty($start_date) && !empty($end_date)) {
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
} else {
    $stmt->execute();
}
$channel_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

#### 7.3 Update performance table display
Add new columns to the performance table for LinkedIn and WeChat contacts:
```php
<th><?php echo __('linkedin_contacts'); ?></th>
<th><?php echo __('wechat_contacts'); ?></th>
```

And in the data rows:
```php
<td><?php echo $user['linkedin_contacts']; ?></td>
<td><?php echo $user['wechat_contacts']; ?></td>
```

## Language Support

### 8. Update language files

#### 8.1 English messages (languages/en/messages.php)
Add new translation keys:
```php
'contact_channel' => 'Contact Channel',
'select_contact_channel' => 'Select Contact Channel',
'phone_call' => 'Phone Call',
'whatsapp' => 'WhatsApp',
'sms' => 'SMS',
'in_person_meeting' => 'In-Person Meeting',
'video_call' => 'Video Call',
'linkedin' => 'LinkedIn',
'wechat' => 'WeChat',
'other' => 'Other',
'linkedin_contacts' => 'LinkedIn Contacts',
'wechat_contacts' => 'WeChat Contacts',
'contact_channel_breakdown' => 'Contact Channel Breakdown',
'success_rate' => 'Success Rate',
'unique_customers' => 'Unique Customers',
```

#### 8.2 Chinese messages (languages/zh-cn/messages.php)
Add corresponding Chinese translations:
```php
'contact_channel' => '联系渠道',
'select_contact_channel' => '选择联系渠道',
'phone_call' => '电话',
'whatsapp' => 'WhatsApp',
'sms' => '短信',
'in_person_meeting' => '面谈',
'video_call' => '视频通话',
'linkedin' => 'LinkedIn',
'wechat' => '微信',
'other' => '其他',
'linkedin_contacts' => 'LinkedIn联系',
'wechat_contacts' => '微信联系',
'contact_channel_breakdown' => '联系渠道分析',
'success_rate' => '成功率',
'unique_customers' => '独特客户',
```

## Migration Script

### 9. Create migration script

Create a new file `maintenance/migrate_add_contact_channel.php`:

```php
<?php
require_once '../includes/config.php';

try {
    // Add contact_channel column
    $pdo->exec("ALTER TABLE action_history 
                ADD COLUMN contact_channel ENUM(
                    'Email', 
                    'Phone Call', 
                    'WhatsApp', 
                    'SMS', 
                    'In-Person Meeting', 
                    'Video Call', 
                    'LinkedIn', 
                    'WeChat', 
                    'Other'
                ) NOT NULL DEFAULT 'Other' 
                AFTER action");
    
    // Optionally, try to intelligently set contact_channel based on existing action text
    $pdo->exec("UPDATE action_history SET contact_channel = 'Email' 
                WHERE LOWER(action) LIKE '%email%' OR LOWER(action) LIKE '%邮件%'");
    
    $pdo->exec("UPDATE action_history SET contact_channel = 'Phone Call' 
                WHERE LOWER(action) LIKE '%call%' OR LOWER(action) LIKE '%phone%' OR LOWER(action) LIKE '%电话%'");
    
    $pdo->exec("UPDATE action_history SET contact_channel = 'In-Person Meeting' 
                WHERE LOWER(action) LIKE '%meeting%' OR LOWER(action) LIKE '%visit%' OR LOWER(action) LIKE '%会议%' OR LOWER(action) LIKE '%拜访%'");
    
    $pdo->exec("UPDATE action_history SET contact_channel = 'WhatsApp' 
                WHERE LOWER(action) LIKE '%whatsapp%'");
    
    $pdo->exec("UPDATE action_history SET contact_channel = 'WeChat' 
                WHERE LOWER(action) LIKE '%wechat%' OR LOWER(action) LIKE '%微信%'");
    
    $pdo->exec("UPDATE action_history SET contact_channel = 'LinkedIn' 
                WHERE LOWER(action) LIKE '%linkedin%'");
    
    echo "Migration completed successfully!\n";
    echo "Added contact_channel field to action_history table.\n";
    echo "Attempted to categorize existing records based on action text.\n";
    
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
```

## CSS Styling

### 10. Update style.css

Add styling for the new contact channel field and performance metrics:

```css
/* Contact channel styling */
.contact-channel-breakdown {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 5px;
}

.contact-channel-breakdown h4 {
    margin-bottom: 15px;
    color: #333;
}

.channel-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.channel-stat-card {
    background: white;
    padding: 15px;
    border-radius: 5px;
    border-left: 4px solid #007bff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.channel-stat-card h5 {
    margin: 0 0 10px 0;
    color: #555;
    font-size: 14px;
}

.channel-stat-card .stat-number {
    font-size: 24px;
    font-weight: bold;
    color: #007bff;
}

.channel-stat-card .stat-subtitle {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}
```

## Testing Plan

### 11. Testing procedures

#### 11.1 Database Testing
- [ ] Run migration script on test database
- [ ] Verify contact_channel field is added correctly
- [ ] Check existing records have 'Other' as default
- [ ] Test ENUM constraint by trying invalid values

#### 11.2 Form Testing
- [ ] Test adding new action history with contact channel
- [ ] Test editing existing action history
- [ ] Verify contact channel is saved correctly
- [ ] Test form validation for required contact channel

#### 11.3 Display Testing
- [ ] Check all_activities.php shows contact channel column
- [ ] Check all_followups.php shows contact channel column
- [ ] Verify sorting by contact channel works
- [ ] Test CSV export includes contact channel

#### 11.4 Performance Reports Testing
- [ ] Verify admin performance tab shows new metrics
- [ ] Check contact channel breakdown statistics
- [ ] Test date range filtering with new metrics
- [ ] Verify performance calculations are accurate

#### 11.5 Language Testing
- [ ] Test English interface with new fields
- [ ] Test Chinese interface with new fields
- [ ] Verify all new translation keys work correctly

## Implementation Order

### 12. Recommended implementation sequence

1. **Database Changes**
   - Update database.sql file
   - Create and run migration script

2. **Core Functionality**
   - Update history_form.php (form and processing)
   - Update includes/functions.php if needed

3. **Display Updates**
   - Update all_activities.php
   - Update all_followups.php

4. **Performance Metrics**
   - Update admin_performance_tab.php
   - Add contact channel breakdown

5. **Language Support**
   - Update language files (English and Chinese)

6. **Styling and Polish**
   - Update CSS
   - Test responsive design

7. **Testing and Validation**
   - Run all tests
   - Validate data integrity
   - Performance testing

## Rollback Plan

### 13. Rollback procedures

If issues are encountered, rollback can be performed by:

1. **Database Rollback**
   ```sql
   ALTER TABLE action_history DROP COLUMN contact_channel;
   ```

2. **Code Rollback**
   - Revert all modified files to previous versions
   - Remove new language translations
   - Restore original CSS

3. **Data Verification**
   - Verify all existing functionality works
   - Check no data was lost during rollback

## Benefits

### 14. Expected improvements

1. **Better Analytics**: Precise tracking of communication channels
2. **Performance Insights**: Understanding which channels are most effective
3. **User Behavior**: Analysis of user preferences in communication methods
4. **Strategic Planning**: Data-driven decisions on communication strategies
5. **Compliance**: Better record keeping for audit purposes
6. **ROI Analysis**: Channel-specific success rate tracking

## Future Enhancements

### 15. Potential future improvements

1. **Channel Effectiveness Scoring**: Automatic scoring based on conversion rates
2. **Channel Recommendations**: AI-powered suggestions for best contact methods
3. **Integration Tracking**: Link with email systems, CRM tools
4. **Custom Channel Types**: Allow admin to add custom channel types
5. **Time-based Analysis**: Peak hours analysis by channel
6. **Customer Preferences**: Track and remember customer preferred channels
