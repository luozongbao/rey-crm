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
        }
        
        $report_result = ['success' => true, 'message' => 'Report generated successfully'];
    } else {
        $report_result = ['success' => false, 'message' => 'Please fill in all required fields'];
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
        <h3>Reports & Export</h3>
        <p>Generate detailed reports on user performance, customer activities, and system usage</p>
    </div>

    <div class="reports-layout">
        <!-- Report Generation Form -->
        <div class="report-generator">
            <div class="generator-header">
                <h4>Generate Report</h4>
            </div>
            
            <form method="POST" class="report-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="report_type">Report Type:</label>
                        <select name="report_type" id="report_type" class="form-control" required>
                            <option value="">-- Select Report Type --</option>
                            <option value="user_activity">User Activity Report</option>
                            <option value="customer_status">Customer Status Report</option>
                            <option value="activity_summary">Activity Summary Report</option>
                            <option value="assignment_history">Assignment History Report</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" 
                               value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_filter">Filter by User (Optional):</label>
                        <select name="user_filter" id="user_filter" class="form-control">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="generate_report" value="view" class="btn btn-primary">
                        <i class="fas fa-chart-bar"></i> Generate Report
                    </button>
                    <button type="submit" name="generate_report" value="csv" formtarget="_blank" class="btn btn-success">
                        <i class="fas fa-download"></i> Download CSV
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
                            'user_activity' => 'User Activity Report',
                            'customer_status' => 'Customer Status Report',
                            'activity_summary' => 'Activity Summary Report',
                            'assignment_history' => 'Assignment History Report'
                        ];
                        echo $report_titles[$report_data['type']] ?? 'Report';
                        ?>
                    </h4>
                    <div class="report-meta">
                        <span>Period: <?php echo $report_data['params']['start_date']; ?> to <?php echo $report_data['params']['end_date']; ?></span>
                        <span>Records: <?php echo count($report_data['data']); ?></span>
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
                    <h4>No Data Found</h4>
                    <p>No data available for the selected criteria. Try adjusting the date range or filters.</p>
                </div>
                
            <?php else: ?>
                <div class="report-placeholder">
                    <i class="fas fa-file-chart-line"></i>
                    <h4>Generate a Report</h4>
                    <p>Select a report type and date range to generate detailed analytics and export data.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pre-defined Report Links -->
    <div class="quick-reports">
        <h4>Quick Reports</h4>
        <div class="quick-report-grid">
            <div class="quick-report-card">
                <div class="card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="card-content">
                    <h5>Monthly User Summary</h5>
                    <p>User activity and performance for the last 30 days</p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="report_type" value="user_activity">
                        <input type="hidden" name="start_date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        <input type="hidden" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                        <input type="hidden" name="user_filter" value="">
                        <input type="hidden" name="format" value="view">
                        <button type="submit" name="generate_report" class="btn btn-outline-primary btn-sm">Generate</button>
                    </form>
                </div>
            </div>
            
            <div class="quick-report-card">
                <div class="card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="card-content">
                    <h5>Weekly Activity Summary</h5>
                    <p>System-wide activity breakdown for the last 7 days</p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="report_type" value="activity_summary">
                        <input type="hidden" name="start_date" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                        <input type="hidden" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                        <input type="hidden" name="user_filter" value="">
                        <input type="hidden" name="format" value="view">
                        <button type="submit" name="generate_report" class="btn btn-outline-primary btn-sm">Generate</button>
                    </form>
                </div>
            </div>
            
            <div class="quick-report-card">
                <div class="card-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="card-content">
                    <h5>Customer Status Overview</h5>
                    <p>Current status of all customers with recent activities</p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="report_type" value="customer_status">
                        <input type="hidden" name="start_date" value="<?php echo date('Y-m-d', strtotime('-90 days')); ?>">
                        <input type="hidden" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                        <input type="hidden" name="user_filter" value="">
                        <input type="hidden" name="format" value="view">
                        <button type="submit" name="generate_report" class="btn btn-outline-primary btn-sm">Generate</button>
                    </form>
                </div>
            </div>
            
            <div class="quick-report-card">
                <div class="card-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <div class="card-content">
                    <h5>Assignment Changes</h5>
                    <p>Customer assignment history for the last 30 days</p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="report_type" value="assignment_history">
                        <input type="hidden" name="start_date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        <input type="hidden" name="end_date" value="<?php echo date('Y-m-d'); ?>">
                        <input type="hidden" name="user_filter" value="">
                        <input type="hidden" name="format" value="view">
                        <button type="submit" name="generate_report" class="btn btn-outline-primary btn-sm">Generate</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle format selection for form submission
    const generateBtn = document.querySelector('button[value="view"]');
    const downloadBtn = document.querySelector('button[value="csv"]');
    const formatInput = document.getElementById('format');
    
    if (generateBtn && downloadBtn && formatInput) {
        generateBtn.addEventListener('click', function() {
            formatInput.value = 'view';
        });
        
        downloadBtn.addEventListener('click', function() {
            formatInput.value = 'csv';
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

/* Responsive Design */
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
