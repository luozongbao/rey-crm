# Customer Information Dashboard Redesign Plan

## Current State Analysis

### Current Dashboard Features
- Shows aggregate statistics (total customers, status counts, location stats)
- Displays contact statistics (contacted vs not contacted)
- Shows last contacted customer
- Lists upcoming follow-ups and recent activities
- Exports functionality for history and follow-ups
- **Issue**: Does NOT show actual customer information lists or respect user role permissions

### Current User Role System
- **Admin users**: `$_SESSION['role'] === 'admin'` can see and manage all customers
- **Regular users**: Can only see customers assigned to them (`assigned_user_id = $_SESSION['user_id']`)
- Role checking: `isAdmin()` function in `includes/functions.php`
- Customer filtering: `$showOnlyMine` parameter in `getPaginatedCustomers()`

## Proposed Redesign: Role-Based Customer Information Dashboard

### 1. Dashboard Layout Redesign

#### For Regular Users (Non-Admin)
```
┌─────────────────────────────────────────────────────────────┐
│                     My Customer Dashboard                    │
├─────────────────────────────────────────────────────────────┤
│  My Stats    │  Contact Status  │  Recent Activity          │
│  Total: 12   │  Contacted: 75%  │  Last contacted:          │
│  Active: 8   │  Need Follow: 4  │  ABC Corp - 2 days ago    │
└─────────────────────────────────────────────────────────────┘
├─────────────────────────────────────────────────────────────┤
│                    My Customers List                        │
│  Search: [________] Status: [All▼] Location: [All▼]        │
│                                                             │
│  Company Name     │ Status      │ Last Contact │ Actions   │
│  ABC Corporation  │ Active      │ 2 days ago   │ [View]    │
│  XYZ Industries   │ Prospect    │ 1 week ago   │ [View]    │
│  ...              │ ...         │ ...          │ ...       │
└─────────────────────────────────────────────────────────────┘
├─────────────────────────────────────────────────────────────┤
│  My Follow-ups Due (5)         │  My Recent Activities (5) │
│  ABC Corp - Today              │  Called XYZ - 2h ago      │
│  DEF Ltd - Tomorrow            │  Emailed ABC - 4h ago     │
└─────────────────────────────────────────────────────────────┘
```

#### For Admin Users
```
┌─────────────────────────────────────────────────────────────┐
│                   Admin Customer Dashboard                   │
├─────────────────────────────────────────────────────────────┤
│  System Stats   │  User Performance  │  Recent Activity     │
│  Total: 150     │  Top Performer:    │  System-wide last:   │
│  Active: 95     │  John (25 active)  │  ABC Corp - 1h ago   │
└─────────────────────────────────────────────────────────────┘
├─────────────────────────────────────────────────────────────┤
│  View: [My Customers ▼] [All Customers] [Unassigned]       │
│                                                             │
│                   Customer List View                       │
│  Search: [________] User: [All▼] Status: [All▼] Loc: [All▼]│
│                                                             │
│  Company Name    │ Assigned To  │ Status     │ Last Contact│
│  ABC Corporation │ John Smith   │ Active     │ 2 days ago  │
│  XYZ Industries  │ Jane Doe     │ Prospect   │ 1 week ago  │
│  DEF Company     │ Unassigned   │ New        │ Never       │
└─────────────────────────────────────────────────────────────┘
├─────────────────────────────────────────────────────────────┤
│  System Follow-ups (15)        │  System Activities (10)   │
│  ABC Corp (John) - Today       │  John called XYZ - 2h ago │
│  DEF Ltd (Jane) - Tomorrow     │  Jane emailed ABC - 4h   │
└─────────────────────────────────────────────────────────────┘
```

### 2. Implementation Steps

#### Step 1: Create New Dashboard Data Functions
Create role-aware functions in `includes/functions.php`:

```php
// Get customer statistics based on user role
function getDashboardCustomerStats($user_id = null, $show_all = false) {
    // Returns customer counts filtered by user role
}

// Get customer list for dashboard (limited/summary view)
function getDashboardCustomers($limit = 10, $user_id = null, $show_all = false) {
    // Returns recent/important customers for dashboard
}

// Get user performance stats (admin only)
function getUserPerformanceStats() {
    // Returns stats per user for admin dashboard
}

// Get customers by assignment status (admin only)
function getCustomersByAssignment() {
    // Returns unassigned, assigned counts
}
```

#### Step 2: Modify Dashboard Layout Structure
Update `dashboard.php` to include:

1. **Role Detection Section**
   ```php
   $isAdmin = isAdmin();
   $currentUserId = $_SESSION['user_id'];
   ```

2. **Dynamic Content Loading**
   ```php
   if ($isAdmin) {
       // Load admin-specific data
       $viewMode = $_GET['view'] ?? 'my'; // my, all, unassigned
       $customerData = getDashboardCustomers(15, $currentUserId, $viewMode === 'all');
       $userStats = getUserPerformanceStats();
       $assignmentStats = getCustomersByAssignment();
   } else {
       // Load user-specific data
       $customerData = getDashboardCustomers(10, $currentUserId, false);
       $myStats = getDashboardCustomerStats($currentUserId, false);
   }
   ```

3. **Customer Information Section**
   - Replace or supplement existing cards with customer list
   - Add filters for admin view (user assignment, status)
   - Include pagination for customer list
   - Add quick action buttons (View, Edit, Assign)

#### Step 3: Create Customer List Component
Create reusable customer list component that can be included in dashboard:

```php
// includes/dashboard_customer_list.php
// Renders customer table with appropriate columns based on role
// Includes search, filter, and pagination
// Shows: Company, Contact, Status, Last Contact, Assigned User (admin only)
```

#### Step 4: Add Dashboard Filters and Controls
For Admin:
- View toggle: "My Customers" | "All Customers" | "Unassigned"
- User filter dropdown
- Status filter dropdown
- Quick assignment actions

For Regular Users:
- Status filter dropdown
- Location filter dropdown
- Search functionality

#### Step 5: Enhance Statistics Cards

**For Regular Users:**
- My customer count by status
- My contact completion rate
- My overdue follow-ups
- My activity summary

**For Admin:**
- System-wide statistics
- User performance comparison
- Unassigned customer alerts
- System activity overview

#### Step 6: Integrate with Existing Features
- Maintain export functionality with role-based filtering
- Keep existing follow-up and activity sections
- Preserve current styling and responsive design
- Maintain language support

### 3. Database Query Optimization

#### New Efficient Queries Needed:
```sql
-- Dashboard customer summary (role-aware)
SELECT c.customer_id, c.company_name, c.status, c.contact_email,
       c.assigned_user_id, u.username as assigned_username,
       MAX(ah.action_datetime) as last_contact,
       COUNT(ah.history_id) as activity_count
FROM customers c
LEFT JOIN users u ON c.assigned_user_id = u.user_id
LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
WHERE (c.assigned_user_id = ? OR ? = 1) -- user filter or admin
GROUP BY c.customer_id
ORDER BY last_contact DESC
LIMIT ?;

-- User performance stats (admin only)
SELECT u.user_id, u.username,
       COUNT(c.customer_id) as total_customers,
       COUNT(CASE WHEN c.status IN ('Active Customer', 'New Customer') THEN 1 END) as active_customers,
       COUNT(DISTINCT ah.customer_id) as contacted_customers
FROM users u
LEFT JOIN customers c ON u.user_id = c.assigned_user_id
LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
WHERE u.role != 'admin'
GROUP BY u.user_id, u.username
ORDER BY active_customers DESC;
```

### 4. UI/UX Enhancements

#### Visual Improvements:
1. **Customer Cards/Rows**: Clean, scannable layout with status badges
2. **Role Indicators**: Clear visual distinction between admin and user views
3. **Quick Actions**: Hover actions for View/Edit/Assign
4. **Filter Bar**: Intuitive filter controls at top of customer list
5. **Responsive Design**: Mobile-friendly customer list view

#### Interactive Features:
1. **Search Autocomplete**: Real-time customer search
2. **Bulk Actions**: Admin bulk assignment capabilities
3. **Sort Controls**: Click-to-sort column headers
4. **Pagination**: Smooth pagination for large customer lists
5. **View Toggles**: Easy switching between view modes (admin)

### 5. Security Considerations

#### Access Control:
- Verify user permissions on every data request
- Sanitize all user inputs for filters and search
- Prevent data leakage between user scopes
- Log admin actions on customer data access

#### SQL Injection Prevention:
- Use prepared statements for all dynamic queries
- Validate filter parameters against allowed values
- Escape user inputs in search functionality

### 6. Performance Considerations

#### Optimization Strategies:
1. **Pagination**: Limit dashboard customer list to 10-15 entries
2. **Caching**: Cache user statistics for admin dashboard
3. **Indexing**: Ensure proper database indexes on:
   - `customers.assigned_user_id`
   - `customers.status`
   - `customers.created_at`
   - `action_history.customer_id`
   - `action_history.action_datetime`

#### Database Query Limits:
- Dashboard customer list: 15 records max
- Recent activities: 5 records max
- Upcoming follow-ups: 5 records max
- Statistics: Cached for 5 minutes

### 7. Testing Requirements

#### Functional Testing:
1. **Role-Based Access**: Verify admin sees all, users see only assigned
2. **Filter Functionality**: Test all filter combinations
3. **Search Features**: Validate search across customer fields
4. **Data Accuracy**: Confirm statistics match actual data
5. **Export Features**: Test CSV exports with role filtering

#### User Experience Testing:
1. **Page Load Speed**: Measure dashboard load times
2. **Mobile Responsiveness**: Test on various screen sizes
3. **Filter Performance**: Test filter response times
4. **Pagination Smoothness**: Test large dataset navigation

### 8. Migration Strategy

#### Phase 1: Backend Preparation
- Add new database functions
- Create role-based data retrieval methods
- Test data accuracy with existing system

#### Phase 2: UI Development
- Create customer list component
- Design admin/user specific layouts
- Implement filtering and search

#### Phase 3: Integration
- Replace dashboard sections gradually
- Maintain backward compatibility during transition
- Test thoroughly with real data

#### Phase 4: Deployment
- Deploy with feature flags for rollback capability
- Monitor performance metrics
- Gather user feedback for improvements

### 9. Future Enhancements

#### Advanced Features (Phase 2):
1. **Customer Health Scoring**: Color-coded customer status indicators
2. **AI-Powered Insights**: Suggest follow-up actions based on activity patterns
3. **Customer Journey Visualization**: Timeline view of customer interactions
4. **Advanced Analytics**: Customer conversion funnel, retention rates
5. **Mobile App Integration**: Mobile-optimized dashboard views

#### Reporting Features:
1. **Custom Dashboard Widgets**: User-configurable dashboard sections
2. **Scheduled Reports**: Email dashboard summaries
3. **KPI Tracking**: Goal setting and progress tracking
4. **Team Collaboration**: Shared customer notes and handoff workflows

## Conclusion

This redesign transforms the dashboard from a basic statistics view into a comprehensive customer information hub that respects user roles and provides actionable customer data. The implementation maintains the existing system architecture while significantly enhancing user productivity and data accessibility.

The role-based approach ensures that users see relevant information while giving admins the oversight capabilities they need. The phased implementation allows for careful testing and gradual rollout to minimize risk.
