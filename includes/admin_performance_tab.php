<?php
// Performance Tab Content for Admin Customer Management

// Get date range parameters
$date_range = $_GET['range'] ?? '30'; // days
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Calculate date range based on parameters
if (!empty($start_date) && !empty($end_date)) {
    // Custom date range
    $date_filter = "BETWEEN :start_date AND :end_date";
    $period_label = "Custom Range ($start_date to $end_date)";
    $filter_start = $start_date;
    $filter_end = $end_date;
} else {
    // Quick range selection
    $days = intval($date_range);
    $filter_start = date('Y-m-d', strtotime("-$days days"));
    $filter_end = date('Y-m-d');
    $date_filter = ">= DATE_SUB(NOW(), INTERVAL $days DAY)";
    $period_label = "Last $days Days";
    
    // Set the date inputs to reflect the quick range
    $start_date = $filter_start;
    $end_date = $filter_end;
}

// Get performance metrics
try {
    // Activity metrics by user - using direct user_id relationship
    if (!empty($start_date) && !empty($end_date)) {
        // Custom date range - use prepared statements
        $query = "
            SELECT 
                u.username,
                u.user_id,
                COALESCE(ca.customers_assigned, 0) as customers_assigned,
                COALESCE(ah_stats.total_activities, 0) as total_activities,
                COALESCE(ah_stats.emails_sent, 0) as emails_sent,
                COALESCE(ah_stats.calls_made, 0) as calls_made,
                COALESCE(ah_stats.meetings_held, 0) as meetings_held,
                COALESCE(ah_stats.linkedin_contacts, 0) as linkedin_contacts,
                COALESCE(ah_stats.wechat_contacts, 0) as wechat_contacts,
                COALESCE(ah_stats.followups_scheduled, 0) as followups_scheduled,
                ah_stats.last_activity_date,
                COALESCE(nc.new_customers_created, 0) as new_customers_created
            FROM users u
            LEFT JOIN (
                SELECT assigned_user_id, COUNT(DISTINCT customer_id) as customers_assigned
                FROM customers 
                WHERE assigned_user_id IS NOT NULL
                GROUP BY assigned_user_id
            ) ca ON u.user_id = ca.assigned_user_id
            LEFT JOIN (
                SELECT 
                    user_id,
                    COUNT(history_id) as total_activities,
                    COUNT(CASE WHEN contact_channel = 'Email' THEN 1 END) as emails_sent,
                    COUNT(CASE WHEN contact_channel IN ('Phone Call', 'WhatsApp', 'SMS') THEN 1 END) as calls_made,
                    COUNT(CASE WHEN contact_channel IN ('In-Person Meeting', 'Video Call') THEN 1 END) as meetings_held,
                    COUNT(CASE WHEN contact_channel = 'LinkedIn' THEN 1 END) as linkedin_contacts,
                    COUNT(CASE WHEN contact_channel = 'WeChat' THEN 1 END) as wechat_contacts,
                    COUNT(CASE WHEN follow_up_datetime IS NOT NULL AND action_datetime BETWEEN ? AND ? THEN 1 END) as followups_scheduled,
                    MAX(action_datetime) as last_activity_date
                FROM action_history 
                WHERE action_datetime BETWEEN ? AND ?
                GROUP BY user_id
            ) ah_stats ON u.user_id = ah_stats.user_id
            LEFT JOIN (
                SELECT created_by_user_id, COUNT(*) as new_customers_created
                FROM customers c2 
                WHERE created_by_user_id IS NOT NULL AND c2.created_at BETWEEN ? AND ?
                GROUP BY created_by_user_id
            ) nc ON u.user_id = nc.created_by_user_id
            GROUP BY u.user_id, u.username
            ORDER BY total_activities DESC
        ";
        $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59', $start_date . ' 00:00:00', $end_date . ' 23:59:59', $start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    } else {
        // Quick range selection
        $days = intval($date_range);
        $query = "
            SELECT 
                u.username,
                u.user_id,
                COALESCE(ca.customers_assigned, 0) as customers_assigned,
                COALESCE(ah_stats.total_activities, 0) as total_activities,
                COALESCE(ah_stats.emails_sent, 0) as emails_sent,
                COALESCE(ah_stats.calls_made, 0) as calls_made,
                COALESCE(ah_stats.meetings_held, 0) as meetings_held,
                COALESCE(ah_stats.linkedin_contacts, 0) as linkedin_contacts,
                COALESCE(ah_stats.wechat_contacts, 0) as wechat_contacts,
                COALESCE(ah_stats.followups_scheduled, 0) as followups_scheduled,
                ah_stats.last_activity_date,
                COALESCE(nc.new_customers_created, 0) as new_customers_created
            FROM users u
            LEFT JOIN (
                SELECT assigned_user_id, COUNT(DISTINCT customer_id) as customers_assigned
                FROM customers 
                WHERE assigned_user_id IS NOT NULL
                GROUP BY assigned_user_id
            ) ca ON u.user_id = ca.assigned_user_id
            LEFT JOIN (
                SELECT 
                    user_id,
                    COUNT(history_id) as total_activities,
                    COUNT(CASE WHEN contact_channel = 'Email' THEN 1 END) as emails_sent,
                    COUNT(CASE WHEN contact_channel IN ('Phone Call', 'WhatsApp', 'SMS') THEN 1 END) as calls_made,
                    COUNT(CASE WHEN contact_channel IN ('In-Person Meeting', 'Video Call') THEN 1 END) as meetings_held,
                    COUNT(CASE WHEN contact_channel = 'LinkedIn' THEN 1 END) as linkedin_contacts,
                    COUNT(CASE WHEN contact_channel = 'WeChat' THEN 1 END) as wechat_contacts,
                    COUNT(CASE WHEN follow_up_datetime IS NOT NULL AND action_datetime >= DATE_SUB(NOW(), INTERVAL $days DAY) THEN 1 END) as followups_scheduled,
                    MAX(action_datetime) as last_activity_date
                FROM action_history 
                WHERE action_datetime >= DATE_SUB(NOW(), INTERVAL $days DAY)
                GROUP BY user_id
            ) ah_stats ON u.user_id = ah_stats.user_id
            LEFT JOIN (
                SELECT created_by_user_id, COUNT(*) as new_customers_created
                FROM customers c2 
                WHERE created_by_user_id IS NOT NULL AND c2.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                GROUP BY created_by_user_id
            ) nc ON u.user_id = nc.created_by_user_id
            GROUP BY u.user_id, u.username
            ORDER BY total_activities DESC
        ";
        $params = [];
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $user_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top performers (users with most activities)
    $top_performers = array_slice($user_performance, 0, 5);

    // System-wide metrics
    if (!empty($start_date) && !empty($end_date)) {
        $system_query = "
            SELECT 
                COUNT(DISTINCT customer_id) as active_customers,
                COUNT(*) as total_activities,
                COUNT(CASE WHEN contact_channel = 'Email' THEN 1 END) as total_emails,
                COUNT(CASE WHEN contact_channel IN ('Phone Call', 'WhatsApp', 'SMS') THEN 1 END) as total_calls,
                COUNT(CASE WHEN contact_channel IN ('In-Person Meeting', 'Video Call') THEN 1 END) as total_meetings,
                COUNT(CASE WHEN contact_channel = 'LinkedIn' THEN 1 END) as total_linkedin,
                COUNT(CASE WHEN contact_channel = 'WeChat' THEN 1 END) as total_wechat,
                AVG(CASE WHEN follow_up_datetime IS NOT NULL THEN 
                    DATEDIFF(follow_up_datetime, action_datetime) END) as avg_followup_days
            FROM action_history 
            WHERE action_datetime BETWEEN ? AND ?
        ";
        $system_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    } else {
        $days = intval($date_range);
        $system_query = "
            SELECT 
                COUNT(DISTINCT customer_id) as active_customers,
                COUNT(*) as total_activities,
                COUNT(CASE WHEN contact_channel = 'Email' THEN 1 END) as total_emails,
                COUNT(CASE WHEN contact_channel IN ('Phone Call', 'WhatsApp', 'SMS') THEN 1 END) as total_calls,
                COUNT(CASE WHEN contact_channel IN ('In-Person Meeting', 'Video Call') THEN 1 END) as total_meetings,
                COUNT(CASE WHEN contact_channel = 'LinkedIn' THEN 1 END) as total_linkedin,
                COUNT(CASE WHEN contact_channel = 'WeChat' THEN 1 END) as total_wechat,
                AVG(CASE WHEN follow_up_datetime IS NOT NULL THEN 
                    DATEDIFF(follow_up_datetime, action_datetime) END) as avg_followup_days
            FROM action_history 
            WHERE action_datetime >= DATE_SUB(NOW(), INTERVAL $days DAY)
        ";
        $system_params = [];
    }
    
    $stmt = $pdo->prepare($system_query);
    $stmt->execute($system_params);
    $system_metrics = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get total new customers created in the period
    if (!empty($start_date) && !empty($end_date)) {
        $new_customers_query = "SELECT COUNT(*) as total_new_customers FROM customers WHERE created_at BETWEEN ? AND ?";
        $new_customers_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    } else {
        $days = intval($date_range);
        $new_customers_query = "SELECT COUNT(*) as total_new_customers FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
        $new_customers_params = [];
    }
    
    $stmt = $pdo->prepare($new_customers_query);
    $stmt->execute($new_customers_params);
    $new_customers_total = $stmt->fetch(PDO::FETCH_ASSOC);
    $system_metrics['total_new_customers'] = $new_customers_total['total_new_customers'] ?? 0;

    // Daily activity trend
    if (!empty($start_date) && !empty($end_date)) {
        $daily_query = "
            SELECT 
                DATE(ah.action_datetime) as activity_date,
                COUNT(*) as daily_activities,
                COUNT(DISTINCT ah.customer_id) as customers_contacted,
                COUNT(DISTINCT ah.user_id) as active_users
            FROM action_history ah
            WHERE ah.action_datetime BETWEEN ? AND ?
            GROUP BY DATE(ah.action_datetime)
            ORDER BY activity_date DESC
            LIMIT 30
        ";
        $daily_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    } else {
        $days = intval($date_range);
        $daily_query = "
            SELECT 
                DATE(ah.action_datetime) as activity_date,
                COUNT(*) as daily_activities,
                COUNT(DISTINCT ah.customer_id) as customers_contacted,
                COUNT(DISTINCT ah.user_id) as active_users
            FROM action_history ah
            WHERE ah.action_datetime >= DATE_SUB(NOW(), INTERVAL $days DAY)
            GROUP BY DATE(ah.action_datetime)
            ORDER BY activity_date DESC
            LIMIT 30
        ";
        $daily_params = [];
    }
    
    $stmt = $pdo->prepare($daily_query);
    $stmt->execute($daily_params);
    $daily_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Customer conversion funnel
    if (!empty($start_date) && !empty($end_date)) {
        $funnel_query = "
            SELECT 
                cs.status_key,
                cst.name as status_name,
                COUNT(*) as count,
                AVG(DATEDIFF(NOW(), c.created_at)) as avg_days_in_status
            FROM customers c
            LEFT JOIN customer_statuses cs ON c.status_id = cs.id
            LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = ?
            WHERE (c.created_at BETWEEN ? AND ? OR c.updated_at BETWEEN ? AND ?)
            GROUP BY cs.status_key, cst.name
            ORDER BY 
                CASE cs.status_key 
                    WHEN 'prospect' THEN 1 
                    WHEN 'qualified' THEN 2
                    WHEN 'new_customer' THEN 3
                    WHEN 'active_customer' THEN 4 
                    WHEN 'not_qualified' THEN 5
                    WHEN 'lost_customer' THEN 6
                    ELSE 7 
                END
        ";
        $funnel_params = [getCurrentLanguage(), $start_date . ' 00:00:00', $end_date . ' 23:59:59', $start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    } else {
        $days = intval($date_range);
        $funnel_query = "
            SELECT 
                cs.status_key,
                cst.name as status_name,
                COUNT(*) as count,
                AVG(DATEDIFF(NOW(), c.created_at)) as avg_days_in_status
            FROM customers c
            LEFT JOIN customer_statuses cs ON c.status_id = cs.id
            LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = ?
            WHERE (c.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY) OR c.updated_at >= DATE_SUB(NOW(), INTERVAL $days DAY))
            GROUP BY cs.status_key, cst.name
            ORDER BY 
                CASE cs.status_key 
                    WHEN 'prospect' THEN 1 
                    WHEN 'qualified' THEN 2
                    WHEN 'new_customer' THEN 3
                    WHEN 'active_customer' THEN 4 
                    WHEN 'not_qualified' THEN 5
                    WHEN 'lost_customer' THEN 6
                    ELSE 7 
                END
        ";
        $funnel_params = [getCurrentLanguage()];
    }
    
    $stmt = $pdo->prepare($funnel_query);
    $stmt->execute($funnel_params);
    $conversion_funnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Response time analytics
    if (!empty($start_date) && !empty($end_date)) {
        $response_query = "
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, c.created_at, first_activity.action_datetime)) as avg_first_response_hours,
                COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, c.created_at, first_activity.action_datetime) <= 24 THEN 1 END) as quick_responses_24h,
                COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, c.created_at, first_activity.action_datetime) > 72 THEN 1 END) as slow_responses_72h,
                COUNT(DISTINCT c.customer_id) as total_customers_with_activity
            FROM customers c
            LEFT JOIN (
                SELECT customer_id, MIN(action_datetime) as action_datetime
                FROM action_history 
                WHERE action_datetime BETWEEN ? AND ?
                GROUP BY customer_id
            ) first_activity ON c.customer_id = first_activity.customer_id
            WHERE c.created_at BETWEEN ? AND ? AND first_activity.action_datetime IS NOT NULL
        ";
        $response_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59', $start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    } else {
        $days = intval($date_range);
        $response_query = "
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, c.created_at, first_activity.action_datetime)) as avg_first_response_hours,
                COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, c.created_at, first_activity.action_datetime) <= 24 THEN 1 END) as quick_responses_24h,
                COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, c.created_at, first_activity.action_datetime) > 72 THEN 1 END) as slow_responses_72h,
                COUNT(DISTINCT c.customer_id) as total_customers_with_activity
            FROM customers c
            LEFT JOIN (
                SELECT customer_id, MIN(action_datetime) as action_datetime
                FROM action_history 
                WHERE action_datetime >= DATE_SUB(NOW(), INTERVAL $days DAY)
                GROUP BY customer_id
            ) first_activity ON c.customer_id = first_activity.customer_id
            WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY) AND first_activity.action_datetime IS NOT NULL
        ";
        $response_params = [];
    }
    
    $stmt = $pdo->prepare($response_query);
    $stmt->execute($response_params);
    $response_metrics = $stmt->fetch(PDO::FETCH_ASSOC);

    // Follow-up management metrics
    if (!empty($start_date) && !empty($end_date)) {
        $followup_query = "
            SELECT 
                u.username,
                u.user_id,
                COUNT(CASE WHEN ah.follow_up_datetime < NOW() AND ah.follow_up_datetime IS NOT NULL THEN 1 END) as user_overdue_count,
                COUNT(CASE WHEN ah.follow_up_datetime >= NOW() AND ah.follow_up_datetime IS NOT NULL THEN 1 END) as user_upcoming_count,
                COUNT(CASE WHEN ah.follow_up_datetime IS NOT NULL THEN 1 END) as user_total_followups,
                AVG(TIMESTAMPDIFF(DAY, ah.action_datetime, ah.follow_up_datetime)) as user_avg_followup_interval
            FROM action_history ah
            JOIN users u ON ah.user_id = u.user_id
            WHERE ah.action_datetime BETWEEN ? AND ?
            GROUP BY u.user_id, u.username
            ORDER BY user_overdue_count DESC
        ";
        $followup_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    } else {
        $days = intval($date_range);
        $followup_query = "
            SELECT 
                u.username,
                u.user_id,
                COUNT(CASE WHEN ah.follow_up_datetime < NOW() AND ah.follow_up_datetime IS NOT NULL THEN 1 END) as user_overdue_count,
                COUNT(CASE WHEN ah.follow_up_datetime >= NOW() AND ah.follow_up_datetime IS NOT NULL THEN 1 END) as user_upcoming_count,
                COUNT(CASE WHEN ah.follow_up_datetime IS NOT NULL THEN 1 END) as user_total_followups,
                AVG(TIMESTAMPDIFF(DAY, ah.action_datetime, ah.follow_up_datetime)) as user_avg_followup_interval
            FROM action_history ah
            JOIN users u ON ah.user_id = u.user_id
            WHERE ah.action_datetime >= DATE_SUB(NOW(), INTERVAL $days DAY)
            GROUP BY u.user_id, u.username
            ORDER BY user_overdue_count DESC
        ";
        $followup_params = [];
    }
    
    $stmt = $pdo->prepare($followup_query);
    $stmt->execute($followup_params);
    $followup_metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // System-wide follow-up summary
    if (!empty($start_date) && !empty($end_date)) {
        $summary_query = "
            SELECT 
                COUNT(CASE WHEN follow_up_datetime < NOW() AND follow_up_datetime IS NOT NULL THEN 1 END) as total_overdue,
                COUNT(CASE WHEN follow_up_datetime >= NOW() AND follow_up_datetime IS NOT NULL THEN 1 END) as total_upcoming,
                COUNT(CASE WHEN follow_up_datetime IS NOT NULL THEN 1 END) as total_scheduled
            FROM action_history 
            WHERE action_datetime BETWEEN ? AND ?
        ";
        $summary_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    } else {
        $days = intval($date_range);
        $summary_query = "
            SELECT 
                COUNT(CASE WHEN follow_up_datetime < NOW() AND follow_up_datetime IS NOT NULL THEN 1 END) as total_overdue,
                COUNT(CASE WHEN follow_up_datetime >= NOW() AND follow_up_datetime IS NOT NULL THEN 1 END) as total_upcoming,
                COUNT(CASE WHEN follow_up_datetime IS NOT NULL THEN 1 END) as total_scheduled
            FROM action_history 
            WHERE action_datetime >= DATE_SUB(NOW(), INTERVAL $days DAY)
        ";
        $summary_params = [];
    }
    
    $stmt = $pdo->prepare($summary_query);
    $stmt->execute($summary_params);
    $followup_summary = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_overdue = $followup_summary['total_overdue'] ?? 0;
    $total_upcoming = $followup_summary['total_upcoming'] ?? 0;
    $total_scheduled = $followup_summary['total_scheduled'] ?? 0;

} catch (PDOException $e) {
    logError("Error getting performance metrics: " . $e->getMessage());
    $user_performance = [];
    $system_metrics = ['active_customers' => 0, 'total_activities' => 0, 'total_emails' => 0, 'total_calls' => 0, 'total_meetings' => 0, 'total_linkedin' => 0, 'total_wechat' => 0, 'avg_followup_days' => 0];
    $daily_trends = [];
    $conversion_funnel = [];
    $response_metrics = ['avg_first_response_hours' => 0, 'quick_responses_24h' => 0, 'slow_responses_72h' => 0, 'total_customers_with_activity' => 0];
    $followup_metrics = [];
    $total_overdue = 0;
    $total_upcoming = 0;
    $total_scheduled = 0;
}
?>

<div class="performance-content">
    <!-- Header with Controls -->
    <div class="performance-header">
        <div class="header-content">
            <h3><?php echo __('performance_analytics'); ?></h3>
            <p><?php echo __('track_team_performance_for', ['period' => $period_label]); ?></p>
        </div>
        
        <div class="date-controls">
            <form method="GET" class="date-filter-form">
                <input type="hidden" name="tab" value="performance">
                
                <div class="form-group">
                    <label><?php echo __('quick_range'); ?>:</label>
                    <select name="range" class="form-select form-select-sm">
                        <option value="7" <?php echo $date_range === '7' ? 'selected' : ''; ?>><?php echo __('last_7_days'); ?></option>
                        <option value="30" <?php echo $date_range === '30' ? 'selected' : ''; ?>><?php echo __('last_30_days'); ?></option>
                        <option value="90" <?php echo $date_range === '90' ? 'selected' : ''; ?>><?php echo __('last_90_days'); ?></option>
                        <option value="365" <?php echo $date_range === '365' ? 'selected' : ''; ?>><?php echo __('last_year'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><?php echo __('custom_range'); ?>:</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-control form-control-sm">
                </div>
                
                <div class="form-group">
                    <label><?php echo __('to'); ?>:</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-control form-control-sm">
                </div>
                
                <button type="submit" class="btn btn-primary btn-sm"><?php echo __('apply'); ?></button>
            </form>
        </div>
    </div>

    <!-- System Overview Cards -->
    <div class="metrics-overview">
        <div class="metric-card">
            <div class="metric-icon activities">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo number_format($system_metrics['total_activities']); ?></div>
                <div class="metric-label"><?php echo __('total_activities'); ?></div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon customers">
                <i class="fas fa-users"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo number_format($system_metrics['active_customers']); ?></div>
                <div class="metric-label"><?php echo __('customers_contacted'); ?></div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon emails">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo number_format($system_metrics['total_emails']); ?></div>
                <div class="metric-label"><?php echo __('emails_sent'); ?></div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon calls">
                <i class="fas fa-phone"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo number_format($system_metrics['total_calls']); ?></div>
                <div class="metric-label"><?php echo __('calls_made'); ?></div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon meetings">
                <i class="fas fa-handshake"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo number_format($system_metrics['total_meetings']); ?></div>
                <div class="metric-label"><?php echo __('meetings'); ?></div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon linkedin">
                <i class="fab fa-linkedin"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo number_format($system_metrics['total_linkedin']); ?></div>
                <div class="metric-label"><?php echo __('linkedin_contacts'); ?></div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon wechat">
                <i class="fab fa-weixin"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo number_format($system_metrics['total_wechat']); ?></div>
                <div class="metric-label"><?php echo __('wechat_contacts'); ?></div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon followups">
                <i class="fas fa-clock"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo round($system_metrics['avg_followup_days'] ?? 0, 1); ?></div>
                <div class="metric-label"><?php echo __('avg_followup_days'); ?></div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon response">
                <i class="fas fa-bolt"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo round($response_metrics['avg_first_response_hours'] ?? 0, 1); ?>h</div>
                <div class="metric-label"><?php echo __('avg_response_time'); ?></div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon new-customers">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo number_format($system_metrics['total_new_customers']); ?></div>
                <div class="metric-label"><?php echo __('new_customers_created'); ?></div>
            </div>
        </div>
    </div>

    <div class="performance-layout">
        <!-- Left Column: Team Performance -->
        <div class="performance-section">
            <h4><?php echo __('team_performance'); ?></h4>
            
            <!-- Top Performers -->
            <div class="top-performers">
                <h5><?php echo __('best_performers'); ?></h5>
                <div class="performers-list">
                    <?php foreach ($top_performers as $index => $performer): ?>
                        <div class="performer-item">
                            <div class="performer-rank">#<?php echo $index + 1; ?></div>
                            <div class="performer-info">
                                <div class="performer-name"><?php echo htmlspecialchars($performer['username']); ?></div>
                                <div class="performer-stats">
                                    <?php echo $performer['total_activities']; ?> <?php echo __('activities'); ?> • 
                                    <?php echo $performer['customers_assigned']; ?> <?php echo __('customers'); ?> •
                                    <?php echo $performer['new_customers_created']; ?> <?php echo __('new_customers_created'); ?>
                                </div>
                            </div>
                            <div class="performer-score">
                                <?php echo $performer['total_activities']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- All Users Performance Table -->
            <div class="users-performance">
                <h5><?php echo __('user_performance'); ?></h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?php echo __('user'); ?></th>
                                <th><?php echo __('customers'); ?></th>
                                <th><?php echo __('new_customers_created'); ?></th>
                                <th><?php echo __('activities'); ?></th>
                                <th><?php echo __('emails_sent'); ?></th>
                                <th><?php echo __('calls_made'); ?></th>
                                <th><?php echo __('meetings'); ?></th>
                                <th><?php echo __('linkedin_contacts'); ?></th>
                                <th><?php echo __('wechat_contacts'); ?></th>
                                <th><?php echo __('efficiency'); ?></th>
                                <th><?php echo __('last_activity'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_performance as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    </td>
                                    <td><?php echo $user['customers_assigned']; ?></td>
                                    <td>
                                        <span class="new-customers-badge"><?php echo $user['new_customers_created']; ?></span>
                                    </td>
                                    <td>
                                        <span class="activity-badge"><?php echo $user['total_activities']; ?></span>
                                    </td>
                                    <td><?php echo $user['emails_sent']; ?></td>
                                    <td><?php echo $user['calls_made']; ?></td>
                                    <td><?php echo $user['meetings_held']; ?></td>
                                    <td><?php echo $user['linkedin_contacts']; ?></td>
                                    <td><?php echo $user['wechat_contacts']; ?></td>
                                    <td>
                                        <?php 
                                        $efficiency = $user['customers_assigned'] > 0 ? 
                                            round($user['total_activities'] / $user['customers_assigned'], 1) : 0;
                                        $efficiency_class = $efficiency >= 3 ? 'text-success' : ($efficiency >= 1.5 ? 'text-warning' : 'text-danger');
                                        ?>
                                        <span class="<?php echo $efficiency_class; ?>"><?php echo $efficiency; ?></span>
                                        <small class="text-muted"> <?php echo __('act_per_cust'); ?></small>
                                    </td>
                                    <td>
                                        <?php echo $user['last_activity_date'] ? formatDateTimeCompact($user['last_activity_date']) : __('no_activity'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column: Trends and Analysis -->
        <div class="analytics-section">
            <h4><?php echo __('trends_overview'); ?></h4>
            
            <!-- Contact Channel Breakdown -->
            <div class="contact-channel-breakdown">
                <h5><?php echo __('contact_channel_breakdown'); ?></h5>
                <?php
                // Contact Channel Analytics Query
                if (!empty($start_date) && !empty($end_date)) {
                    $channel_query = "
                        SELECT 
                            ah.contact_channel,
                            COUNT(*) as count,
                            COUNT(DISTINCT ah.customer_id) as unique_customers,
                            ROUND(AVG(CASE WHEN cs.status_key IN ('new_customer', 'active_customer') THEN 1 ELSE 0 END) * 100, 1) as success_rate
                        FROM action_history ah
                        JOIN customers c ON ah.customer_id = c.customer_id
                        LEFT JOIN customer_statuses cs ON c.status_id = cs.id
                        WHERE ah.action_datetime BETWEEN ? AND ?
                        GROUP BY ah.contact_channel
                        ORDER BY count DESC
                    ";
                    $channel_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
                } else {
                    $days = intval($date_range);
                    $channel_query = "
                        SELECT 
                            ah.contact_channel,
                            COUNT(*) as count,
                            COUNT(DISTINCT ah.customer_id) as unique_customers,
                            ROUND(AVG(CASE WHEN cs.status_key IN ('new_customer', 'active_customer') THEN 1 ELSE 0 END) * 100, 1) as success_rate
                        FROM action_history ah
                        JOIN customers c ON ah.customer_id = c.customer_id
                        LEFT JOIN customer_statuses cs ON c.status_id = cs.id
                        WHERE ah.action_datetime >= DATE_SUB(NOW(), INTERVAL $days DAY)
                        GROUP BY ah.contact_channel
                        ORDER BY count DESC
                    ";
                    $channel_params = [];
                }
                
                $stmt = $pdo->prepare($channel_query);
                $stmt->execute($channel_params);
                $channel_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <div class="channel-stats">
                    <?php foreach ($channel_breakdown as $channel): ?>
                        <div class="channel-stat-card">
                            <h6><?php 
                                // Convert contact channel to translation key
                                $channel_key = strtolower(str_replace([' ', '-'], '_', $channel['contact_channel']));
                                if ($channel_key === 'phone_call') $channel_key = 'phone_call';
                                elseif ($channel_key === 'in_person_meeting') $channel_key = 'in_person_meeting';
                                elseif ($channel_key === 'video_call') $channel_key = 'video_call';
                                echo __($channel_key); 
                            ?></h6>
                            <div class="stat-number"><?php echo $channel['count']; ?></div>
                            <div class="stat-subtitle">
                                <?php echo $channel['unique_customers']; ?> <?php echo __('unique_customers'); ?><br>
                                <?php echo $channel['success_rate']; ?>% <?php echo __('success_rate'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Conversion Funnel -->
            <div class="conversion-funnel">
                <h5><?php echo __('customer_status_distribution'); ?></h5>
                <div class="funnel-chart">
                    <?php 
                    $total_customers = array_sum(array_column($conversion_funnel, 'count'));
                    foreach ($conversion_funnel as $stage): 
                        $percentage = $total_customers > 0 ? ($stage['count'] / $total_customers) * 100 : 0;
                    ?>
                        <div class="funnel-stage">
                            <div class="stage-info">
                                <div class="stage-name"><?php echo $stage['status_name']; ?></div>
                                <div class="stage-count"><?php echo $stage['count']; ?> <?php echo __('customers'); ?></div>
                                <div class="stage-percentage"><?php echo round($percentage, 1); ?>%</div>
                            </div>
                            <div class="stage-bar">
                                <div class="stage-fill stage-<?php echo str_replace(['_', '-'], '', $stage['status_key']); ?>" 
                                     style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Response Time Analytics -->
            <div class="response-analytics">
                <h5><?php echo __('response_time_performance'); ?></h5>
                <div class="response-metrics">
                    <div class="response-metric">
                        <div class="metric-icon-small quick">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <div class="metric-details">
                            <div class="metric-number"><?php echo $response_metrics['quick_responses_24h'] ?? 0; ?></div>
                            <div class="metric-desc"><?php echo __('quick_responses_24h'); ?></div>
                        </div>
                    </div>
                    
                    <div class="response-metric">
                        <div class="metric-icon-small slow">
                            <i class="fas fa-turtle"></i>
                        </div>
                        <div class="metric-details">
                            <div class="metric-number"><?php echo $response_metrics['slow_responses_72h'] ?? 0; ?></div>
                            <div class="metric-desc"><?php echo __('slow_responses_72h'); ?></div>
                        </div>
                    </div>
                    
                    <div class="response-metric">
                        <div class="metric-icon-small average">
                            <i class="fas fa-stopwatch"></i>
                        </div>
                        <div class="metric-details">
                            <div class="metric-number"><?php echo round($response_metrics['avg_first_response_hours'] ?? 0, 1); ?>h</div>
                            <div class="metric-desc"><?php echo __('average_response_time'); ?></div>
                        </div>
                    </div>
                </div>
                
                <?php if ($response_metrics['total_customers_with_activity'] > 0): ?>
                    <div class="response-summary">
                        <div class="summary-stat">
                            <strong><?php echo __('response_rate'); ?>:</strong> 
                            <?php 
                            $quick_rate = round(($response_metrics['quick_responses_24h'] / $response_metrics['total_customers_with_activity']) * 100, 1);
                            $rate_class = $quick_rate >= 70 ? 'text-success' : ($quick_rate >= 50 ? 'text-warning' : 'text-danger');
                            ?>
                            <span class="<?php echo $rate_class; ?>"><?php echo $quick_rate; ?>%</span> <?php echo __('within_24_hours'); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Follow-up Management -->
            <div class="followup-management">
                <h5><?php echo __('followup_management'); ?></h5>
                <!-- <div class="followup-overview"> -->
                    <div class="followup-summary">
                        <div class="followup-stat overdue">
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $total_overdue; ?></div>
                                <div class="stat-label"><?php echo __('overdue_followups'); ?></div>
                            </div>
                        </div>
                        
                        <div class="followup-stat upcoming">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $total_upcoming; ?></div>
                                <div class="stat-label"><?php echo __('upcoming_followups'); ?></div>
                            </div>
                        </div>
                        
                        <div class="followup-stat total">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-number"><?php echo $total_scheduled; ?></div>
                                <div class="stat-label"><?php echo __('total_scheduled'); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($followup_metrics) && $total_overdue > 0): ?>
                        <div class="overdue-alerts">
                            <h6><?php echo __('users_with_overdue_followups'); ?></h6>
                            <div class="alert-list">
                                <?php foreach ($followup_metrics as $user): ?>
                                    <?php if ($user['user_overdue_count'] > 0): ?>
                                        <div class="alert-item">
                                            <div class="alert-user">
                                                <i class="fas fa-user"></i>
                                                <?php echo htmlspecialchars($user['username']); ?>
                                            </div>
                                            <div class="alert-count">
                                                <span class="badge badge-danger"><?php echo $user['user_overdue_count']; ?></span>
                                                <?php echo __('overdue'); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <!-- </div> -->
            </div>

            <!-- Daily Activity Trend -->
            <div class="activity-trend">
                <h5><?php echo __('daily_activity_trend'); ?></h5>
                <?php if (!empty($daily_trends)): ?>
                    <div class="trend-chart">
                        <?php 
                        $max_activities = max(array_column($daily_trends, 'daily_activities'));
                        foreach (array_reverse($daily_trends) as $day): 
                            $height = $max_activities > 0 ? ($day['daily_activities'] / $max_activities) * 100 : 0;
                        ?>
                            <div class="trend-day" title="<?php echo $day['activity_date']; ?>: <?php echo $day['daily_activities']; ?> activities">
                                <div class="trend-bar" style="height: <?php echo $height; ?>%"></div>
                                <div class="trend-label"><?php echo date('j', strtotime($day['activity_date'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="trend-summary">
                        <div class="summary-item">
                            <strong><?php echo __('peak_day'); ?>:</strong> 
                            <?php 
                            $peak_day = array_reduce($daily_trends, function($carry, $day) {
                                return $carry === null || $day['daily_activities'] > $carry['daily_activities'] ? $day : $carry;
                            });
                            echo $peak_day['activity_date'] . ' (' . $peak_day['daily_activities'] . ' ' . __('activities') . ')';
                            ?>
                        </div>
                        <div class="summary-item">
                            <strong><?php echo __('average_daily'); ?>:</strong> 
                            <?php echo round(array_sum(array_column($daily_trends, 'daily_activities')) / count($daily_trends), 1); ?> <?php echo __('activities'); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted"><?php echo __('no_activity_data_available'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Quick Insights -->
            <div class="quick-insights">
                <h5><?php echo __('quick_insights'); ?></h5>
                <div class="insights-list">
                    <?php
                    // Calculate insights
                    $active_users = count(array_filter($user_performance, function($user) {
                        return $user['total_activities'] > 0;
                    }));
                    $total_users = count($user_performance);
                    $engagement_rate = $total_users > 0 ? ($active_users / $total_users) * 100 : 0;
                    
                    $avg_activities_per_user = $active_users > 0 ? $system_metrics['total_activities'] / $active_users : 0;
                    
                    // Workload balance insight
                    $user_customer_counts = array_column($user_performance, 'customers_assigned');
                    $max_customers = max($user_customer_counts ?: [0]);
                    $min_customers = min(array_filter($user_customer_counts) ?: [0]);
                    $workload_imbalance = $max_customers - $min_customers;
                    ?>
                    
                    <div class="insight-item">
                        <i class="fas fa-users text-primary"></i>
                        <span><strong><?php echo round($engagement_rate, 1); ?>%</strong> <?php echo __('user_engagement_rate'); ?> 
                        (<?php echo $active_users; ?> <?php echo __('of'); ?> <?php echo $total_users; ?> <?php echo __('users_active'); ?>)</span>
                    </div>
                    
                    <div class="insight-item">
                        <i class="fas fa-chart-bar text-success"></i>
                        <span><strong><?php echo round($avg_activities_per_user, 1); ?></strong> <?php echo __('average_activities_per_active_user'); ?></span>
                    </div>
                    
                    <div class="insight-item">
                        <i class="fas fa-balance-scale <?php echo $workload_imbalance <= 5 ? 'text-success' : ($workload_imbalance <= 15 ? 'text-warning' : 'text-danger'); ?>"></i>
                        <span><strong><?php echo $workload_imbalance; ?></strong> <?php echo __('customer_difference_between_workloads'); ?></span>
                    </div>
                    
                    <?php if (!empty($daily_trends)): ?>
                        <div class="insight-item">
                            <i class="fas fa-calendar text-info"></i>
                            <span><strong><?php echo count($daily_trends); ?></strong> <?php echo __('days_with_recorded_activity'); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($system_metrics['total_emails'] > 0 && $system_metrics['total_activities'] > 0): ?>
                        <div class="insight-item">
                            <i class="fas fa-envelope text-warning"></i>
                            <span><strong><?php echo round(($system_metrics['total_emails'] / $system_metrics['total_activities']) * 100, 1); ?>%</strong> 
                            <?php echo __('of_activities_are_emails'); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (($response_metrics['quick_responses_24h'] ?? 0) > 0): ?>
                        <div class="insight-item">
                            <i class="fas fa-bolt text-success"></i>
                            <span><strong><?php echo $response_metrics['quick_responses_24h']; ?></strong> <?php echo __('customers_received_quick_responses'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Performance Summary -->
    <div class="team-summary-section">
        <h4><?php echo __('team_summary'); ?></h4>
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-header">
                    <h5><?php echo __('activity_distribution'); ?></h5>
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-stat">
                        <span class="stat-label"><?php echo __('email_activities'); ?>:</span>
                        <span class="stat-value"><?php echo number_format($system_metrics['total_emails']); ?> 
                        (<?php echo $system_metrics['total_activities'] > 0 ? round(($system_metrics['total_emails'] / $system_metrics['total_activities']) * 100, 1) : 0; ?>%)</span>
                    </div>
                    <div class="summary-stat">
                        <span class="stat-label"><?php echo __('call_activities'); ?>:</span>
                        <span class="stat-value"><?php echo number_format($system_metrics['total_calls']); ?> 
                        (<?php echo $system_metrics['total_activities'] > 0 ? round(($system_metrics['total_calls'] / $system_metrics['total_activities']) * 100, 1) : 0; ?>%)</span>
                    </div>
                    <div class="summary-stat">
                        <span class="stat-label"><?php echo __('active_users'); ?>:</span>
                        <span class="stat-value"><?php echo $active_users; ?> <?php echo __('of'); ?> <?php echo $total_users; ?> <?php echo __('users'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-header">
                    <h5><?php echo __('performance_trends'); ?></h5>
                    <i class="fas fa-trending-up"></i>
                </div>
                <div class="summary-content">
                    <?php if (!empty($daily_trends)): ?>
                        <?php 
                        $recent_days = array_slice($daily_trends, 0, 7);
                        $avg_recent = array_sum(array_column($recent_days, 'daily_activities')) / count($recent_days);
                        $older_days = array_slice($daily_trends, 7, 7);
                        $avg_older = !empty($older_days) ? array_sum(array_column($older_days, 'daily_activities')) / count($older_days) : $avg_recent;
                        $trend_direction = $avg_recent > $avg_older ? 'up' : ($avg_recent < $avg_older ? 'down' : 'stable');
                        $trend_class = $trend_direction === 'up' ? 'text-success' : ($trend_direction === 'down' ? 'text-danger' : 'text-muted');
                        $trend_icon = $trend_direction === 'up' ? 'fa-arrow-up' : ($trend_direction === 'down' ? 'fa-arrow-down' : 'fa-minus');
                        ?>
                        <div class="summary-stat">
                            <span class="stat-label"><?php echo __('daily_average_last_7_days'); ?>:</span>
                            <span class="stat-value"><?php echo round($avg_recent, 1); ?> <?php echo __('activities'); ?></span>
                        </div>
                        <div class="summary-stat">
                            <span class="stat-label"><?php echo __('trend'); ?>:</span>
                            <span class="stat-value <?php echo $trend_class; ?>">
                                <i class="fas <?php echo $trend_icon; ?>"></i>
                                <?php echo __(strtolower($trend_direction)); ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="summary-stat">
                            <span class="stat-label"><?php echo __('no_trend_data_available'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-header">
                    <h5><?php echo __('response_quality'); ?></h5>
                    <i class="fas fa-stopwatch"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-stat">
                        <span class="stat-label"><?php echo __('avg_response_time'); ?>:</span>
                        <span class="stat-value"><?php echo round($response_metrics['avg_first_response_hours'] ?? 0, 1); ?> <?php echo __('hours'); ?></span>
                    </div>
                    <div class="summary-stat">
                        <span class="stat-label"><?php echo __('quick_responses'); ?></span>
                        <span class="stat-value text-success"><?php echo $response_metrics['quick_responses_24h'] ?? 0; ?> (≤24<?php echo __('hours'); ?>)</span>
                    </div>
                    <div class="summary-stat">
                        <span class="stat-label"><?php echo __('slow_responses'); ?></span>
                        <span class="stat-value text-warning"><?php echo $response_metrics['slow_responses_72h'] ?? 0; ?> (>72<?php echo __('hours'); ?>)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.performance-content {
    max-width: 1400px;
}

.performance-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.header-content h3 {
    margin: 0;
    color: #495057;
}

.header-content p {
    margin: 5px 0 0 0;
    color: #6c757d;
}

.date-filter-form {
    display: flex;
    align-items: end;
    gap: 15px;
    flex-wrap: wrap;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group label {
    font-size: 0.8rem;
    color: #6c757d;
    font-weight: 500;
}

/* Form Controls */
.form-control, .form-select {
    padding: 0.375rem 0.75rem;
    margin-bottom: 0;
    font-size: 0.875rem;
    font-weight: 400;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus, .form-select:focus {
    color: #495057;
    background-color: #fff;
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.form-control-sm, .form-select-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8125rem;
    border-radius: 0.2rem;
}

.btn {
    display: inline-block;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    text-align: center;
    text-decoration: none;
    vertical-align: middle;
    cursor: pointer;
    user-select: none;
    background-color: transparent;
    border: 1px solid transparent;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 0.25rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.btn-primary {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}

.btn-primary:hover {
    color: #fff;
    background-color: #0056b3;
    border-color: #004085;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8125rem;
    border-radius: 0.2rem;
}

/* Metrics Overview */
.metrics-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.metric-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.metric-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.metric-icon.activities { background: #007bff; }
.metric-icon.customers { background: #28a745; }
.metric-icon.emails { background: #6f42c1; }
.metric-icon.calls { background: #fd7e14; }
.metric-icon.meetings { background: #e83e8c; }
.metric-icon.linkedin { background: #0077b5; }
.metric-icon.wechat { background: #07c160; }
.metric-icon.followups { background: #20c997; }
.metric-icon.response { background: #6610f2; }
.metric-icon.new-customers { background: #17a2b8; }

.metric-content {
    flex: 1;
}

.metric-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #495057;
    line-height: 1;
}

.metric-label {
    font-size: 0.9rem;
    color: #6c757d;
    margin-top: 5px;
}

/* Performance Layout */
.performance-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
}

.performance-section,
.analytics-section {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 25px;
}

.performance-section h4,
.analytics-section h4 {
    margin: 0 0 25px 0;
    color: #495057;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 10px;
}

.performance-section h5,
.analytics-section h5 {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 1.1rem;
}

/* Top Performers */
.top-performers {
    margin-bottom: 30px;
}

.performers-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.performer-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid #007bff;
}

.performer-rank {
    background: #007bff;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
}

.performer-info {
    flex: 1;
}

.performer-name {
    font-weight: 600;
    color: #495057;
}

.performer-stats {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 2px;
}

.performer-score {
    background: #e9ecef;
    color: #495057;
    padding: 8px 12px;
    border-radius: 15px;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Users Performance Table */
.users-performance {
    margin-top: 20px;
}

.table {
    margin-bottom: 0;
    font-size: 0.9rem;
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 0.75rem;
    vertical-align: top;
    border-top: 1px solid #dee2e6;
}

.table th {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    font-size: 0.8rem;
    border-top: none;
}

.table tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.activity-badge {
    background: #007bff;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Conversion Funnel */
.conversion-funnel {
    margin-bottom: 30px;
}

/* Response Analytics */
.response-analytics {
    margin-bottom: 30px;
}

.response-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.response-metric {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.metric-icon-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1rem;
}

.metric-icon-small.quick { background: #28a745; }
.metric-icon-small.slow { background: #dc3545; }
.metric-icon-small.average { background: #6c757d; }

.metric-details {
    flex: 1;
}

.metric-number {
    font-size: 1.4rem;
    font-weight: 700;
    color: #495057;
    line-height: 1;
}

.metric-desc {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 2px;
}

.response-summary {
    padding: 12px;
    background: #e9ecef;
    border-radius: 6px;
    text-align: center;
}

.summary-stat {
    font-size: 0.9rem;
    color: #495057;
}

/* Follow-up Management */
.followup-management {
    margin-bottom: 30px;
}

.followup-overview {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
}

.followup-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.followup-stat {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: white;
    border-radius: 6px;
    border-left: 4px solid #007bff;
}

.followup-stat.overdue {
    border-left-color: #dc3545;
}

.followup-stat.upcoming {
    border-left-color: #ffc107;
}

.followup-stat.total {
    border-left-color: #28a745;
}

.stat-icon {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
}

.followup-stat.overdue .stat-icon {
    background: #dc3545;
}

.followup-stat.upcoming .stat-icon {
    background: #ffc107;
}

.followup-stat.total .stat-icon {
    background: #28a745;
}

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 1.3rem;
    font-weight: 700;
    color: #495057;
    line-height: 1;
}

.stat-label {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 2px;
}

.overdue-alerts {
    margin-top: 20px;
}

.overdue-alerts h6 {
    margin: 0 0 12px 0;
    color: #495057;
    font-size: 0.9rem;
    font-weight: 600;
}

.alert-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.alert-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background: white;
    border-radius: 4px;
    border-left: 3px solid #dc3545;
}

.alert-user {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #495057;
    font-weight: 500;
}

.alert-user i {
    color: #6c757d;
}

.alert-count {
    font-size: 0.8rem;
    color: #6c757d;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
    color: white;
    margin-right: 5px;
}

.badge-danger {
    background: #dc3545;
}

/* Conversion Funnel Styles */
.conversion-funnel {
    margin-bottom: 30px;
}

.funnel-chart {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.funnel-stage {
    display: flex;
    align-items: center;
    gap: 15px;
}

.stage-info {
    flex: 0 0 140px;
    text-align: right;
}

.stage-name {
    font-weight: 600;
    color: #495057;
}

.stage-count {
    font-size: 0.8rem;
    color: #6c757d;
}

.stage-percentage {
    font-size: 0.75rem;
    color: #6c757d;
}

.stage-bar {
    flex: 1;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.stage-fill {
    height: 100%;
    transition: width 0.3s ease;
}

.stage-prospect { background: #ffc107; }
.stage-qualified { background: #17a2b8; }
.stage-notqualified { background: #dc3545; }
.stage-newcustomer { background: #28a745; }
.stage-activecustomer { background: #007bff; }
.stage-lostcustomer { background: #6c757d; }

/* Activity Trend */
.activity-trend {
    margin-bottom: 30px;
}

.trend-chart {
    display: flex;
    align-items: end;
    gap: 3px;
    height: 150px;
    padding: 20px 0;
    margin-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.trend-day {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    cursor: pointer;
}

.trend-bar {
    background: #007bff;
    width: 100%;
    min-height: 2px;
    border-radius: 2px;
    transition: all 0.2s ease;
}

.trend-day:hover .trend-bar {
    background: #0056b3;
}

.trend-label {
    font-size: 0.7rem;
    color: #6c757d;
}

.trend-summary {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.summary-item {
    font-size: 0.9rem;
    color: #495057;
}

/* Quick Insights */
.insights-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.insight-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
}

.insight-item i {
    font-size: 1.2rem;
}

.insight-item span {
    font-size: 0.9rem;
    color: #495057;
}

/* Team Performance Summary */
.team-summary-section {
    margin-top: 40px;
    padding: 25px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
}

.team-summary-section h4 {
    margin: 0 0 25px 0;
    color: #495057;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 10px;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.summary-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e9ecef;
}

.summary-header h5 {
    margin: 0;
    color: #495057;
    font-size: 1rem;
}

.summary-header i {
    color: #6c757d;
    font-size: 1.2rem;
}

.summary-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.summary-stat {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.summary-stat:last-child {
    border-bottom: none;
}

.stat-label {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 500;
}

.stat-value {
    font-size: 0.9rem;
    color: #495057;
    font-weight: 600;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 1024px) {
    .performance-layout {
        grid-template-columns: 1fr;
    }
    
    .metrics-overview {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .date-filter-form {
        justify-content: center;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .response-metrics {
        grid-template-columns: 1fr;
    }
    
    .followup-summary {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .performance-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .metrics-overview {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .metric-card {
        padding: 15px;
        gap: 12px;
    }
    
    .metric-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .metric-value {
        font-size: 1.5rem;
    }
    
    .date-filter-form {
        flex-direction: column;
        gap: 10px;
    }
    
    .funnel-stage {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    
    .stage-info {
        flex: none;
        text-align: left;
    }
    
    .trend-chart {
        height: 100px;
    }
}

@media (max-width: 480px) {
    .metrics-overview {
        grid-template-columns: 1fr;
    }
    
    .metric-card {
        padding: 12px;
        gap: 10px;
    }
    
    .performance-section,
    .analytics-section {
        padding: 15px;
    }
    
    .response-metrics {
        grid-template-columns: 1fr;
    }
    
    .followup-summary {
        grid-template-columns: 1fr;
    }
}

/* Dark Mode Support */
body.dark-mode .performance-content {
    background: transparent;
}

body.dark-mode .metric-card {
    background: #2d3748;
    border-color: #4a5568;
    color: #e2e8f0;
}

body.dark-mode .metric-value {
    color: #e2e8f0;
}

body.dark-mode .metric-label {
    color: #a0aec0;
}

body.dark-mode .performance-section,
body.dark-mode .analytics-section {
    background: #2d3748;
    border-color: #4a5568;
    color: #e2e8f0;
}

body.dark-mode .performance-section h4,
body.dark-mode .analytics-section h4,
body.dark-mode .performance-section h5,
body.dark-mode .analytics-section h5 {
    color: #e2e8f0;
    border-color: #4a5568;
}

body.dark-mode .performer-item {
    background: #4a5568;
    color: #e2e8f0;
}

body.dark-mode .performer-name {
    color: #e2e8f0;
}

body.dark-mode .performer-stats {
    color: #a0aec0;
}

body.dark-mode .table th {
    background: #4a5568;
    color: #e2e8f0;
    border-color: #718096;
}

body.dark-mode .table td {
    color: #e2e8f0;
    border-color: #4a5568;
}

body.dark-mode .table tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

body.dark-mode .form-control,
body.dark-mode .form-select {
    background-color: #4a5568;
    border-color: #718096;
    color: #e2e8f0;
}

body.dark-mode .form-control:focus,
body.dark-mode .form-select:focus {
    background-color: #4a5568;
    border-color: #80bdff;
    color: #e2e8f0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle quick range selection to update date inputs
    const rangeSelect = document.querySelector('select[name="range"]');
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');
    
    if (rangeSelect && startDateInput && endDateInput) {
        rangeSelect.addEventListener('change', function() {
            const days = parseInt(this.value);
            if (days > 0) {
                const endDate = new Date();
                const startDate = new Date();
                startDate.setDate(endDate.getDate() - days);
                
                // Format dates as YYYY-MM-DD
                const formatDate = (date) => {
                    return date.getFullYear() + '-' + 
                           String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                           String(date.getDate()).padStart(2, '0');
                };
                
                startDateInput.value = formatDate(startDate);
                endDateInput.value = formatDate(endDate);
            }
        });
        
        // Clear range selection when custom dates are changed
        startDateInput.addEventListener('change', function() {
            if (this.value) {
                rangeSelect.value = '';
            }
        });
        
        endDateInput.addEventListener('change', function() {
            if (this.value) {
                rangeSelect.value = '';
            }
        });
    }
});
</script>
