<?php
// Reports Tab Content for Admin Customer Management

// Handle report generation
$report_result = null;
$report_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $user_filter = $_POST['user_filter'] ?? '';
    $format = $_POST['format'] ?? 'view';
    
    if (!empty($report_type) && !empty($start_date) && !empty($end_date)) {
        $report_data = generateReport($report_type, $start_date, $end_date, $user_filter);
        
        if ($format === 'csv') {
            downloadReportAsCSV($report_data, $report_type, $start_date, $end_date);
            exit;
        } elseif ($format === 'excel') {
            downloadReportAsExcel($report_data, $report_type, $start_date, $end_date);
            exit;
        }
        
        $report_result = ['success' => true, 'message' => __('report_generated_successfully')];
    } else {
        $report_result = ['success' => false, 'message' => __('please_fill_required_fields')];
    }
}

/**
 * Generate report based on type and parameters
 */
function generateReport($type, $start_date, $end_date, $user_filter = '') {
    global $pdo;
    
    $user_condition = '';
    $user_params = [];
    
    if (!empty($user_filter)) {
        $user_condition = " AND u.user_id = ?";
        $user_params[] = $user_filter;
    }
    
    switch ($type) {
        case 'user_activity':
            $sql = "SELECT 
                        u.username,
                        COUNT(DISTINCT c.customer_id) as customers_managed,
                        COUNT(ah.activity_id) as total_activities,
                        COUNT(CASE WHEN ah.activity_type = 'email' THEN 1 END) as emails_sent,
                        COUNT(CASE WHEN ah.activity_type = 'call' THEN 1 END) as calls_made,
                        COUNT(CASE WHEN ah.activity_type = 'meeting' THEN 1 END) as meetings_held,
                        MIN(ah.action_datetime) as first_activity,
                        MAX(ah.action_datetime) as last_activity
                    FROM users u
                    LEFT JOIN customers c ON u.user_id = c.assigned_user_id
                    LEFT JOIN action_history ah ON c.customer_id = ah.customer_id 
                        AND ah.action_datetime BETWEEN ? AND ?
                    WHERE 1=1 $user_condition
                    GROUP BY u.user_id, u.username
                    ORDER BY total_activities DESC";
            break;
            
        case 'customer_status':
            $sql = "SELECT 
                        c.company_name,
                        c.contact_email,
                        c.status,
                        u.username as assigned_to,
                        c.created_at,
                        c.updated_at,
                        COUNT(ah.activity_id) as activity_count,
                        MAX(ah.action_datetime) as last_activity,
                        (SELECT follow_up_datetime FROM action_history 
                         WHERE customer_id = c.customer_id AND follow_up_datetime > NOW() 
                         ORDER BY follow_up_datetime ASC LIMIT 1) as next_followup
                    FROM customers c
                    LEFT JOIN users u ON c.assigned_user_id = u.user_id
                    LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
                        AND ah.action_datetime BETWEEN ? AND ?
                    WHERE c.created_at >= ? OR c.updated_at BETWEEN ? AND ?
                    $user_condition
                    GROUP BY c.customer_id
                    ORDER BY c.updated_at DESC";
            $user_params = array_merge([$start_date, $end_date, $start_date, $start_date, $end_date], $user_params);
            break;
            
        case 'activity_summary':
            $sql = "SELECT 
                        DATE(ah.action_datetime) as activity_date,
                        ah.activity_type,
                        COUNT(*) as count,
                        COUNT(DISTINCT ah.customer_id) as unique_customers,
                        COUNT(DISTINCT ah.user_id) as unique_users
                    FROM action_history ah
                    LEFT JOIN customers c ON ah.customer_id = c.customer_id
                    LEFT JOIN users u ON ah.user_id = u.user_id
                    WHERE ah.action_datetime BETWEEN ? AND ?
                    $user_condition
                    GROUP BY DATE(ah.action_datetime), ah.activity_type
                    ORDER BY activity_date DESC, ah.activity_type";
            break;
            
        case 'assignment_history':
            $sql = "SELECT 
                        c.company_name,
                        u_old.username as previous_user,
                        u_new.username as new_user,
                        ah.notes,
                        ah.action_datetime as assignment_date,
                        u_admin.username as assigned_by
                    FROM action_history ah
                    JOIN customers c ON ah.customer_id = c.customer_id
                    LEFT JOIN users u_old ON ah.notes LIKE CONCAT('%from ', u_old.username, '%')
                    LEFT JOIN users u_new ON ah.notes LIKE CONCAT('%to ', u_new.username, '%')
                    LEFT JOIN users u_admin ON ah.user_id = u_admin.user_id
                    WHERE ah.activity_type IN ('assignment', 'reassignment')
                        AND ah.action_datetime BETWEEN ? AND ?
                    $user_condition
                    ORDER BY ah.action_datetime DESC";
            break;
            
        case 'user_performance':
            $sql = "SELECT 
                        u.username,
                        COUNT(DISTINCT c.customer_id) as customers_assigned,
                        COUNT(ah.history_id) as total_activities,
                        COUNT(CASE WHEN ah.action LIKE '%email%' THEN 1 END) as emails_sent,
                        COUNT(CASE WHEN ah.action LIKE '%call%' THEN 1 END) as calls_made,
                        COUNT(CASE WHEN ah.follow_up_datetime IS NOT NULL THEN 1 END) as followups_scheduled,
                        COUNT(CASE WHEN ah.follow_up_datetime < NOW() AND ah.follow_up_datetime IS NOT NULL THEN 1 END) as overdue_followups,
                        AVG(CASE WHEN c.status = 'Active' THEN 1 ELSE 0 END) * 100 as conversion_rate,
                        ROUND(COUNT(ah.history_id) / GREATEST(COUNT(DISTINCT c.customer_id), 1), 2) as activity_per_customer,
                        MAX(ah.action_datetime) as last_activity_date
                    FROM users u
                    LEFT JOIN customers c ON u.user_id = c.assigned_user_id
                    LEFT JOIN action_history ah ON c.customer_id = ah.customer_id 
                        AND ah.action_datetime BETWEEN ? AND ?
                    WHERE u.role != 'admin' $user_condition
                    GROUP BY u.user_id, u.username
                    ORDER BY total_activities DESC";
            break;
            
        case 'follow_up_performance':
            $sql = "SELECT 
                        u.username,
                        COUNT(CASE WHEN ah.follow_up_datetime IS NOT NULL THEN 1 END) as total_followups,
                        COUNT(CASE WHEN ah.follow_up_datetime < NOW() AND ah.follow_up_datetime IS NOT NULL THEN 1 END) as overdue_followups,
                        COUNT(CASE WHEN ah.follow_up_datetime >= NOW() AND ah.follow_up_datetime IS NOT NULL THEN 1 END) as upcoming_followups,
                        AVG(TIMESTAMPDIFF(DAY, ah.action_datetime, ah.follow_up_datetime)) as avg_followup_interval,
                        COUNT(CASE WHEN ah.follow_up_datetime < NOW() AND ah.follow_up_datetime IS NOT NULL THEN 1 END) / 
                            GREATEST(COUNT(CASE WHEN ah.follow_up_datetime IS NOT NULL THEN 1 END), 1) * 100 as overdue_percentage
                    FROM users u
                    LEFT JOIN customers c ON u.user_id = c.assigned_user_id
                    LEFT JOIN action_history ah ON c.customer_id = ah.customer_id 
                        AND ah.action_datetime BETWEEN ? AND ?
                    WHERE u.role != 'admin' $user_condition
                    GROUP BY u.user_id, u.username
                    HAVING total_followups > 0
                    ORDER BY overdue_percentage DESC";
            break;
            
        case 'customer_conversion':
            $sql = "SELECT 
                        c.company_name,
                        c.contact_email,
                        u.username as assigned_to,
                        c.created_at,
                        c.status,
                        DATEDIFF(NOW(), c.created_at) as days_in_system,
                        COUNT(ah.history_id) as total_activities,
                        MIN(ah.action_datetime) as first_contact,
                        MAX(ah.action_datetime) as last_contact
                    FROM customers c
                    LEFT JOIN users u ON c.assigned_user_id = u.user_id
                    LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
                        AND ah.action_datetime BETWEEN ? AND ?
                    WHERE (c.created_at BETWEEN ? AND ? OR c.updated_at BETWEEN ? AND ?)
                    $user_condition
                    GROUP BY c.customer_id
                    ORDER BY days_in_system DESC";
            $user_params = array_merge([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date], $user_params);
            break;
            
        case 'inactive_customers':
            $sql = "SELECT 
                        c.company_name,
                        c.contact_email,
                        c.status,
                        u.username as assigned_to,
                        c.created_at,
                        last_activity.last_activity_date,
                        DATEDIFF(NOW(), COALESCE(last_activity.last_activity_date, c.created_at)) as days_since_activity,
                        COUNT(ah.history_id) as total_activities
                    FROM customers c
                    LEFT JOIN users u ON c.assigned_user_id = u.user_id
                    LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
                    LEFT JOIN (
                        SELECT customer_id, MAX(action_datetime) as last_activity_date
                        FROM action_history 
                        GROUP BY customer_id
                    ) last_activity ON c.customer_id = last_activity.customer_id
                    WHERE (last_activity.last_activity_date IS NULL OR last_activity.last_activity_date < DATE_SUB(NOW(), INTERVAL 30 DAY))
                    $user_condition
                    GROUP BY c.customer_id
                    ORDER BY days_since_activity DESC";
            break;
            
        case 'response_time':
            $sql = "SELECT 
                        c.company_name,
                        u.username as assigned_to,
                        c.created_at,
                        first_response.first_response_date,
                        TIMESTAMPDIFF(HOUR, c.created_at, first_response.first_response_date) as response_time_hours,
                        CASE 
                            WHEN TIMESTAMPDIFF(HOUR, c.created_at, first_response.first_response_date) <= 24 THEN 'Quick (≤24h)'
                            WHEN TIMESTAMPDIFF(HOUR, c.created_at, first_response.first_response_date) <= 72 THEN 'Standard (24-72h)'
                            ELSE 'Slow (>72h)'
                        END as response_category
                    FROM customers c
                    LEFT JOIN users u ON c.assigned_user_id = u.user_id
                    LEFT JOIN (
                        SELECT customer_id, MIN(action_datetime) as first_response_date
                        FROM action_history 
                        WHERE action_datetime BETWEEN ? AND ?
                        GROUP BY customer_id
                    ) first_response ON c.customer_id = first_response.customer_id
                    WHERE c.created_at BETWEEN ? AND ? 
                    AND first_response.first_response_date IS NOT NULL
                    $user_condition
                    ORDER BY response_time_hours ASC";
            $user_params = array_merge([$start_date, $end_date, $start_date, $end_date], $user_params);
            break;
            
        case 'communication_frequency':
            $sql = "SELECT 
                        c.company_name,
                        u.username as assigned_to,
                        COUNT(ah.history_id) as total_communications,
                        COUNT(CASE WHEN ah.action LIKE '%email%' THEN 1 END) as emails,
                        COUNT(CASE WHEN ah.action LIKE '%call%' THEN 1 END) as calls,
                        COUNT(CASE WHEN ah.action LIKE '%meeting%' THEN 1 END) as meetings,
                        ROUND(COUNT(ah.history_id) / GREATEST(DATEDIFF(?, ?), 1), 2) as avg_communications_per_day,
                        MIN(ah.action_datetime) as first_communication,
                        MAX(ah.action_datetime) as last_communication
                    FROM customers c
                    LEFT JOIN users u ON c.assigned_user_id = u.user_id
                    LEFT JOIN action_history ah ON c.customer_id = ah.customer_id 
                        AND ah.action_datetime BETWEEN ? AND ?
                    WHERE 1=1 $user_condition
                    GROUP BY c.customer_id
                    HAVING total_communications > 0
                    ORDER BY total_communications DESC";
            $user_params = array_merge([$end_date, $start_date, $start_date, $end_date], $user_params);
            break;
            
        case 'assignment_distribution':
            $sql = "SELECT 
                        u.username,
                        COUNT(c.customer_id) as total_customers,
                        COUNT(CASE WHEN c.status = 'Active' THEN 1 END) as active_customers,
                        COUNT(CASE WHEN c.status = 'Prospect' THEN 1 END) as prospect_customers,
                        COUNT(CASE WHEN c.status = 'Inactive' THEN 1 END) as inactive_customers,
                        ROUND(COUNT(c.customer_id) / (SELECT COUNT(*) FROM customers WHERE assigned_user_id IS NOT NULL) * 100, 2) as percentage_of_total,
                        COUNT(CASE WHEN c.created_at BETWEEN ? AND ? THEN 1 END) as new_assignments_period
                    FROM users u
                    LEFT JOIN customers c ON u.user_id = c.assigned_user_id
                    WHERE u.role != 'admin' $user_condition
                    GROUP BY u.user_id, u.username
                    ORDER BY total_customers DESC";
            break;
            
        case 'workload_balance':
            $sql = "SELECT 
                        u.username,
                        COUNT(c.customer_id) as customer_count,
                        COUNT(recent_activity.customer_id) as active_workload,
                        COUNT(upcoming_followups.customer_id) as pending_followups,
                        ROUND(COUNT(c.customer_id) / (SELECT AVG(customer_count) FROM (
                            SELECT COUNT(*) as customer_count 
                            FROM customers 
                            WHERE assigned_user_id IS NOT NULL 
                            GROUP BY assigned_user_id
                        ) avg_calc), 2) as workload_ratio,
                        CASE 
                            WHEN COUNT(c.customer_id) > (SELECT AVG(customer_count) * 1.2 FROM (
                                SELECT COUNT(*) as customer_count 
                                FROM customers 
                                WHERE assigned_user_id IS NOT NULL 
                                GROUP BY assigned_user_id
                            ) avg_calc) THEN 'Overloaded'
                            WHEN COUNT(c.customer_id) < (SELECT AVG(customer_count) * 0.8 FROM (
                                SELECT COUNT(*) as customer_count 
                                FROM customers 
                                WHERE assigned_user_id IS NOT NULL 
                                GROUP BY assigned_user_id
                            ) avg_calc) THEN 'Underutilized'
                            ELSE 'Balanced'
                        END as balance_status
                    FROM users u
                    LEFT JOIN customers c ON u.user_id = c.assigned_user_id
                    LEFT JOIN (
                        SELECT DISTINCT customer_id 
                        FROM action_history 
                        WHERE action_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ) recent_activity ON c.customer_id = recent_activity.customer_id
                    LEFT JOIN (
                        SELECT DISTINCT customer_id 
                        FROM action_history 
                        WHERE follow_up_datetime >= NOW()
                    ) upcoming_followups ON c.customer_id = upcoming_followups.customer_id
                    WHERE u.role != 'admin' $user_condition
                    GROUP BY u.user_id, u.username
                    ORDER BY workload_ratio DESC";
            break;
            
        default:
            return [];
    }
    
    $params = empty($user_params) ? [$start_date, $end_date] : array_merge([$start_date, $end_date], $user_params);
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return [
            'type' => $type,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'params' => ['start_date' => $start_date, 'end_date' => $end_date, 'user_filter' => $user_filter]
        ];
    } catch (PDOException $e) {
        logError("Error generating report: " . $e->getMessage());
        return [];
    }
}

/**
 * Download report as CSV
 */
function downloadReportAsCSV($report_data, $report_type, $start_date, $end_date) {
    if (empty($report_data['data'])) return;
    
    $filename = $report_type . "_report_" . $start_date . "_to_" . $end_date . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    if (!empty($report_data['data'])) {
        fputcsv($output, array_keys($report_data['data'][0]));
        
        // Write data
        foreach ($report_data['data'] as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

/**
 * Download report as Excel (simple HTML table format)
 */
function downloadReportAsExcel($report_data, $report_type, $start_date, $end_date) {
    if (empty($report_data['data'])) return;
    
    $filename = $report_type . "_report_" . $start_date . "_to_" . $end_date . ".xls";
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "<html>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<style>";
    echo "table { border-collapse: collapse; width: 100%; }";
    echo "th, td { border: 1px solid #000; padding: 8px; text-align: left; }";
    echo "th { background-color: #f2f2f2; font-weight: bold; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    $report_titles = [
        'user_activity' => 'User Activity Report',
        'user_performance' => 'User Performance Summary',
        'follow_up_performance' => 'Follow-up Performance Report',
        'customer_status' => 'Customer Status Report',
        'customer_conversion' => 'Customer Conversion Report',
        'inactive_customers' => 'Inactive Customers Report',
        'activity_summary' => 'Activity Summary Report',
        'response_time' => 'Response Time Analysis',
        'communication_frequency' => 'Communication Frequency Report',
        'assignment_history' => 'Assignment History Report',
        'assignment_distribution' => 'Assignment Distribution Report',
        'workload_balance' => 'Workload Balance Report'
    ];
    
    echo "<h2>" . ($report_titles[$report_type] ?? 'Report') . "</h2>";
    echo "<p>Period: " . $start_date . " to " . $end_date . "</p>";
    echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>";
    
    echo "<table>";
    
    // Headers
    if (!empty($report_data['data'])) {
        echo "<tr>";
        foreach (array_keys($report_data['data'][0]) as $header) {
            echo "<th>" . ucwords(str_replace('_', ' ', $header)) . "</th>";
        }
        echo "</tr>";
        
        // Data
        foreach ($report_data['data'] as $row) {
            echo "<tr>";
            foreach ($row as $key => $value) {
                echo "<td>";
                if (in_array($key, ['first_activity', 'last_activity', 'created_at', 'updated_at', 'assignment_date', 'next_followup', 'activity_date', 'first_response_date', 'first_contact', 'last_contact', 'first_communication', 'last_communication', 'last_activity_date'])) {
                    echo $value ? date('Y-m-d H:i', strtotime($value)) : 'N/A';
                } elseif (is_numeric($value)) {
                    echo number_format($value, 2);
                } else {
                    echo htmlspecialchars($value ?? 'N/A');
                }
                echo "</td>";
            }
            echo "</tr>";
        }
    }
    
    echo "</table>";
    echo "</body>";
    echo "</html>";
}

// Get users for filter dropdown
$users = getAllUsers();
?>

<div class="reports-content">
    <!-- Success/Error Messages -->
    <?php if ($report_result): ?>
        <div class="alert alert-<?php echo $report_result['success'] ? 'success' : 'danger'; ?>">
            <?php echo htmlspecialchars($report_result['message']); ?>
        </div>
    <?php endif; ?>

    <div class="reports-header">
        <h3><?php echo __('reports_export'); ?></h3>
        <p><?php echo __('generate_detailed_reports'); ?></p>
    </div>

    <div class="reports-layout">
        <!-- Report Generation Form -->
        <div class="report-generator">
            <div class="generator-header">
                <h4><?php echo __('generate_report'); ?></h4>
            </div>
            
            <form method="POST" class="report-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="report_type"><?php echo __('report_type'); ?>:</label>
                        <select name="report_type" id="report_type" class="form-control" required>
                            <option value=""><?php echo __('select_report_type'); ?></option>
                            <optgroup label="<?php echo __('user_performance_reports'); ?>">
                                <option value="user_activity"><?php echo __('user_activity_report'); ?></option>
                                <option value="user_performance"><?php echo __('user_performance_summary'); ?></option>
                                <option value="follow_up_performance"><?php echo __('follow_up_performance_report'); ?></option>
                            </optgroup>
                            <optgroup label="<?php echo __('customer_reports'); ?>">
                                <option value="customer_status"><?php echo __('customer_status_report'); ?></option>
                                <option value="customer_conversion"><?php echo __('customer_conversion_report'); ?></option>
                                <option value="inactive_customers"><?php echo __('inactive_customers_report'); ?></option>
                            </optgroup>
                            <optgroup label="<?php echo __('activity_reports'); ?>">
                                <option value="activity_summary"><?php echo __('activity_summary_report'); ?></option>
                                <option value="response_time"><?php echo __('response_time_analysis'); ?></option>
                                <option value="communication_frequency"><?php echo __('communication_frequency_report'); ?></option>
                            </optgroup>
                            <optgroup label="<?php echo __('management_reports'); ?>">
                                <option value="assignment_history"><?php echo __('assignment_history_report'); ?></option>
                                <option value="assignment_distribution"><?php echo __('assignment_distribution_report'); ?></option>
                                <option value="workload_balance"><?php echo __('workload_balance_report'); ?></option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date"><?php echo __('start_date'); ?>:</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" 
                               value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date"><?php echo __('end_date'); ?>:</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_filter"><?php echo __('filter_by_user_optional'); ?>:</label>
                        <select name="user_filter" id="user_filter" class="form-control">
                            <option value=""><?php echo __('all_users'); ?></option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status_filter"><?php echo __('filter_by_customer_status_optional'); ?>:</label>
                        <select name="status_filter" id="status_filter" class="form-control">
                            <option value=""><?php echo __('all_statuses'); ?></option>
                            <option value="Prospect"><?php echo __('prospect'); ?></option>
                            <option value="Active"><?php echo __('active'); ?></option>
                            <option value="Inactive"><?php echo __('inactive'); ?></option>
                            <option value="Lost Customer"><?php echo __('lost_customer'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="activity_filter"><?php echo __('filter_by_activity_type_optional'); ?>:</label>
                        <select name="activity_filter" id="activity_filter" class="form-control">
                            <option value=""><?php echo __('all_activities'); ?></option>
                            <option value="email"><?php echo __('email_only'); ?></option>
                            <option value="call"><?php echo __('call_only'); ?></option>
                            <option value="meeting"><?php echo __('meeting_only'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="generate_report" value="view" class="btn btn-primary">
                        <i class="fas fa-chart-bar"></i> <?php echo __('generate_report'); ?>
                    </button>
                    <button type="submit" name="generate_report" value="csv" formtarget="_blank" class="btn btn-success">
                        <i class="fas fa-file-csv"></i> <?php echo __('download_csv'); ?>
                    </button>
                    <button type="submit" name="generate_report" value="excel" formtarget="_blank" class="btn btn-info">
                        <i class="fas fa-file-excel"></i> <?php echo __('download_excel'); ?>
                    </button>
                    <input type="hidden" name="format" id="format" value="view">
                </div>
            </form>
        </div>

        <!-- Report Display -->
        <div class="report-display">
            <?php if ($report_data && !empty($report_data['data'])): ?>
                <div class="report-header">
                    <h4>
                        <?php
                        $report_titles = [
                            'user_activity' => __('user_activity_report'),
                            'user_performance' => __('user_performance_summary'),
                            'follow_up_performance' => __('follow_up_performance_report'),
                            'customer_status' => __('customer_status_report'),
                            'customer_conversion' => __('customer_conversion_report'),
                            'inactive_customers' => __('inactive_customers_report'),
                            'activity_summary' => __('activity_summary_report'),
                            'response_time' => __('response_time_analysis'),
                            'communication_frequency' => __('communication_frequency_report'),
                            'assignment_history' => __('assignment_history_report'),
                            'assignment_distribution' => __('assignment_distribution_report'),
                            'workload_balance' => __('workload_balance_report')
                        ];
                        echo $report_titles[$report_data['type']] ?? __('generate_report');
                        ?>
                    </h4>
                    <div class="report-meta">
                        <span><?php echo __('period'); ?>: <?php echo $report_data['params']['start_date']; ?> <?php echo __('to'); ?> <?php echo $report_data['params']['end_date']; ?></span>
                        <span><?php echo __('records'); ?>: <?php echo count($report_data['data']); ?></span>
                    </div>
                </div>
                
                <div class="report-table">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($report_data['data'][0]) as $header): ?>
                                        <th><?php echo ucwords(str_replace('_', ' ', $header)); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['data'] as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $key => $value): ?>
                                            <td>
                                                <?php 
                                                if (in_array($key, ['first_activity', 'last_activity', 'created_at', 'updated_at', 'assignment_date', 'next_followup', 'activity_date'])) {
                                                    echo $value ? formatDateTimeCompact($value) : 'N/A';
                                                } elseif (is_numeric($value)) {
                                                    echo number_format($value);
                                                } else {
                                                    echo htmlspecialchars($value ?? 'N/A');
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php elseif ($report_data): ?>
                <div class="no-data">
                    <i class="fas fa-chart-line"></i>
                    <h4><?php echo __('no_data_found'); ?></h4>
                    <p><?php echo __('no_data_available_for_criteria'); ?></p>
                </div>
                
            <?php else: ?>
                <div class="report-placeholder">
                    <i class="fas fa-file-chart-line"></i>
                    <h4><?php echo __('generate_a_report'); ?></h4>
                    <p><?php echo __('select_report_type_and_date_range'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pre-defined Report Links -->
    <div class="quick-reports">
        <h4><?php echo __('quick_reports'); ?></h4>
        <div class="quick-report-grid">
            <div class="quick-report-card">
                <div class="card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="card-content">
                    <h5><?php echo __('monthly_user_summary'); ?></h5>
                    <p><?php echo __('user_activity_performance_last_30_days'); ?></p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="report_type" value="user_activity">
                        <input type="hidden" name="start_date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        <input type="hidden" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                        <input type="hidden" name="user_filter" value="">
                        <input type="hidden" name="format" value="view">
                        <button type="submit" name="generate_report" class="btn btn-outline-primary btn-sm"><?php echo __('generate'); ?></button>
                    </form>
                </div>
            </div>
            
            <div class="quick-report-card">
                <div class="card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="card-content">
                    <h5><?php echo __('weekly_activity_summary'); ?></h5>
                    <p><?php echo __('system_wide_activity_breakdown_7_days'); ?></p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="report_type" value="activity_summary">
                        <input type="hidden" name="start_date" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                        <input type="hidden" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                        <input type="hidden" name="user_filter" value="">
                        <input type="hidden" name="format" value="view">
                        <button type="submit" name="generate_report" class="btn btn-outline-primary btn-sm"><?php echo __('generate'); ?></button>
                    </form>
                </div>
            </div>
            
            <div class="quick-report-card">
                <div class="card-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="card-content">
                    <h5><?php echo __('customer_status_overview'); ?></h5>
                    <p><?php echo __('current_status_all_customers_recent_activities'); ?></p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="report_type" value="customer_status">
                        <input type="hidden" name="start_date" value="<?php echo date('Y-m-d', strtotime('-90 days')); ?>">
                        <input type="hidden" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                        <input type="hidden" name="user_filter" value="">
                        <input type="hidden" name="format" value="view">
                        <button type="submit" name="generate_report" class="btn btn-outline-primary btn-sm"><?php echo __('generate'); ?></button>
                    </form>
                </div>
            </div>
            
            <div class="quick-report-card">
                <div class="card-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="card-content">
                    <h5><?php echo __('assignment_changes'); ?></h5>
                    <p><?php echo __('customer_assignment_history_30_days'); ?></p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="report_type" value="assignment_history">
                        <input type="hidden" name="start_date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        <input type="hidden" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                        <input type="hidden" name="user_filter" value="">
                        <input type="hidden" name="format" value="view">
                        <button type="submit" name="generate_report" class="btn btn-outline-primary btn-sm"><?php echo __('generate'); ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Scheduling Section -->
    <div class="report-scheduling">
        <h4><?php echo __('scheduled_reports'); ?></h4>
        <div class="scheduling-layout">
            <div class="schedule-form">
                <h5><?php echo __('schedule_new_report'); ?></h5>
                <form method="POST" class="schedule-report-form">
                    <div class="form-row-inline">
                        <div class="form-group">
                            <label><?php echo __('report_type'); ?>:</label>
                            <select name="schedule_report_type" class="form-control form-control-sm">
                                <option value="user_performance"><?php echo __('user_performance_summary'); ?></option>
                                <option value="activity_summary"><?php echo __('activity_summary_report'); ?></option>
                                <option value="customer_status"><?php echo __('customer_status_overview'); ?></option>
                                <option value="assignment_distribution"><?php echo __('assignment_distribution_report'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo __('frequency'); ?>:</label>
                            <select name="schedule_frequency" class="form-control form-control-sm">
                                <option value="weekly"><?php echo __('weekly'); ?></option>
                                <option value="monthly"><?php echo __('monthly'); ?></option>
                                <option value="quarterly"><?php echo __('quarterly'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo __('email_to'); ?>:</label>
                            <input type="email" name="schedule_email" class="form-control form-control-sm" 
                                   placeholder="admin@company.com" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="schedule_report" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-calendar-plus"></i> <?php echo __('schedule'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="scheduled-reports-list">
                <h5><?php echo __('active_schedules'); ?></h5>
                <div class="schedule-placeholder">
                    <i class="fas fa-calendar-alt"></i>
                    <p><?php echo __('no_scheduled_reports_yet'); ?></p>
                </div>
                <!-- Future: List of scheduled reports would appear here -->
            </div>
        </div>
    </div>

    <!-- Advanced Export Options -->
    <div class="advanced-export">
        <h4><?php echo __('advanced_export_options'); ?></h4>
        <div class="export-options-grid">
            <div class="export-option">
                <div class="option-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="option-content">
                    <h5><?php echo __('bulk_data_export'); ?></h5>
                    <p><?php echo __('export_complete_datasets_external_analysis'); ?></p>
                    <button class="btn btn-outline-secondary btn-sm" onclick="alert('<?php echo __('feature_coming_soon'); ?>')">
                        <i class="fas fa-download"></i> <?php echo __('export_all_data'); ?>
                    </button>
                </div>
            </div>
            
            <div class="export-option">
                <div class="option-icon">
                    <i class="fas fa-filter"></i>
                </div>
                <div class="option-content">
                    <h5><?php echo __('custom_filtered_export'); ?></h5>
                    <p><?php echo __('export_with_advanced_filtering'); ?></p>
                    <button class="btn btn-outline-secondary btn-sm" onclick="alert('<?php echo __('feature_coming_soon'); ?>')">
                        <i class="fas fa-sliders-h"></i> <?php echo __('custom_export'); ?>
                    </button>
                </div>
            </div>
            
            <div class="export-option">
                <div class="option-icon">
                    <i class="fas fa-code"></i>
                </div>
                <div class="option-content">
                    <h5><?php echo __('api_access'); ?></h5>
                    <p><?php echo __('programmatic_access_report_data'); ?></p>
                    <button class="btn btn-outline-secondary btn-sm" onclick="alert('<?php echo __('api_documentation_coming_soon'); ?>')">
                        <i class="fas fa-terminal"></i> <?php echo __('api_docs'); ?>
                    </button>
                </div>
            </div>
            
            <div class="export-option">
                <div class="option-icon">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="option-content">
                    <h5><?php echo __('pdf_reports'); ?></h5>
                    <p><?php echo __('generate_formatted_pdf_reports'); ?></p>
                    <button class="btn btn-outline-secondary btn-sm" onclick="alert('<?php echo __('pdf_export_coming_soon'); ?>')">
                        <i class="fas fa-file-export"></i> <?php echo __('generate_pdf'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle format selection for form submission
    const generateBtn = document.querySelector('button[value="view"]');
    const downloadCsvBtn = document.querySelector('button[value="csv"]');
    const downloadExcelBtn = document.querySelector('button[value="excel"]');
    const formatInput = document.getElementById('format');
    
    if (generateBtn && downloadCsvBtn && downloadExcelBtn && formatInput) {
        generateBtn.addEventListener('click', function() {
            formatInput.value = 'view';
        });
        
        downloadCsvBtn.addEventListener('click', function() {
            formatInput.value = 'csv';
        });
        
        downloadExcelBtn.addEventListener('click', function() {
            formatInput.value = 'excel';
        });
    }
    
    // Auto-set end date when start date changes
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function() {
            const startDate = new Date(this.value);
            const endDate = new Date(endDateInput.value);
            
            if (startDate > endDate) {
                endDateInput.value = this.value;
            }
        });
    }
});
</script>

<style>
.reports-content {
    max-width: 1400px;
}

.reports-header {
    margin-bottom: 30px;
}

.reports-header h3 {
    margin: 0;
    color: #495057;
}

.reports-header p {
    margin: 5px 0 0 0;
    color: #6c757d;
}

.reports-layout {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 30px;
    margin-bottom: 40px;
}

/* Report Generator */
.report-generator {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

.generator-header {
    background: #f8f9fa;
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
}

.generator-header h4 {
    margin: 0;
    color: #495057;
}

.report-form {
    padding: 25px;
}

.form-row {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 25px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-weight: 600;
    color: #495057;
}

.form-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.form-actions .btn {
    justify-content: center;
}

/* Report Display */
.report-display {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    min-height: 400px;
}

.report-header {
    background: #f8f9fa;
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
}

.report-header h4 {
    margin: 0 0 10px 0;
    color: #495057;
}

.report-meta {
    display: flex;
    gap: 20px;
    font-size: 0.9rem;
    color: #6c757d;
}

.report-table {
    max-height: 600px;
    overflow: auto;
}

.table {
    margin-bottom: 0;
    font-size: 0.9rem;
}

.table th {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table td {
    vertical-align: middle;
}

/* Placeholder States */
.report-placeholder,
.no-data {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 400px;
    text-align: center;
    color: #6c757d;
}

.report-placeholder i,
.no-data i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.report-placeholder h4,
.no-data h4 {
    margin-bottom: 10px;
    color: #495057;
}

/* Quick Reports */
.quick-reports {
    margin-top: 40px;
}

.quick-reports h4 {
    margin-bottom: 20px;
    color: #495057;
}

.quick-report-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.quick-report-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    gap: 15px;
    align-items: flex-start;
    transition: box-shadow 0.2s ease;
}

.quick-report-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.quick-report-card .card-icon {
    width: 50px;
    height: 50px;
    background: #007bff;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.quick-report-card .card-content {
    flex: 1;
}

.quick-report-card h5 {
    margin: 0 0 8px 0;
    color: #495057;
    font-size: 1rem;
}

.quick-report-card p {
    margin: 0 0 15px 0;
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Report Scheduling */
.report-scheduling {
    margin-top: 40px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 25px;
}

.report-scheduling h4 {
    margin: 0 0 20px 0;
    color: #495057;
}

.scheduling-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.schedule-form h5,
.scheduled-reports-list h5 {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 1rem;
    padding-bottom: 10px;
    border-bottom: 1px solid #e9ecef;
}

.form-row-inline {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    align-items: end;
}

.form-row-inline .form-group:last-child {
    grid-column: span 2;
}

.schedule-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px;
    text-align: center;
    color: #6c757d;
    background: #f8f9fa;
    border-radius: 6px;
}

.schedule-placeholder i {
    font-size: 2rem;
    margin-bottom: 10px;
    opacity: 0.5;
}

.schedule-placeholder p {
    margin: 0;
    font-size: 0.9rem;
}

/* Advanced Export Options */
.advanced-export {
    margin-top: 40px;
}

.advanced-export h4 {
    margin: 0 0 20px 0;
    color: #495057;
}

.export-options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.export-option {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    gap: 15px;
    align-items: flex-start;
    transition: box-shadow 0.2s ease;
}

.export-option:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.export-option .option-icon {
    width: 50px;
    height: 50px;
    background: #17a2b8;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.export-option .option-content {
    flex: 1;
}

.export-option h5 {
    margin: 0 0 8px 0;
    color: #495057;
    font-size: 1rem;
}

.export-option p {
    margin: 0 0 15px 0;
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Enhanced Form Actions */
.form-actions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.form-actions .btn {
    justify-content: center;
    padding: 12px 20px;
    font-weight: 500;
}

.btn-info {
    background-color: #17a2b8;
    border-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #138496;
    border-color: #117a8b;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .scheduling-layout {
        grid-template-columns: 1fr;
    }
    
    .export-options-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 1024px) {
    .reports-layout {
        grid-template-columns: 1fr;
    }
    
    .report-generator {
        order: 2;
    }
    
    .report-display {
        order: 1;
    }
    
    .quick-report-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .export-options-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row-inline {
        grid-template-columns: 1fr;
    }
    
    .form-row-inline .form-group:last-child {
        grid-column: span 1;
    }
}

@media (max-width: 768px) {
    .form-actions {
        gap: 15px;
    }
    
    .form-actions .btn {
        padding: 12px;
    }
    
    .report-meta {
        flex-direction: column;
        gap: 5px;
    }
    
    .quick-report-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-report-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>
