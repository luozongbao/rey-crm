<?php
// Performance Tab Content for Admin Customer Management

// Get date range parameters
$date_range = $_GET['range'] ?? '30'; // days
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Calculate date range
if (!empty($start_date) && !empty($end_date)) {
    $date_filter = "BETWEEN '$start_date' AND '$end_date'";
    $period_label = "Custom Range ($start_date to $end_date)";
} else {
    $days = intval($date_range);
    $date_filter = ">= DATE_SUB(NOW(), INTERVAL $days DAY)";
    $period_label = "Last $days Days";
}

// Get performance metrics
try {
    // Activity metrics by user
    $stmt = $pdo->query("
        SELECT 
            u.username,
            u.user_id,
            COUNT(DISTINCT c.customer_id) as customers_assigned,
            COUNT(ah.activity_id) as total_activities,
            COUNT(CASE WHEN ah.activity_type = 'email' THEN 1 END) as emails_sent,
            COUNT(CASE WHEN ah.activity_type = 'call' THEN 1 END) as calls_made,
            COUNT(CASE WHEN ah.activity_type = 'meeting' THEN 1 END) as meetings_held,
            COUNT(CASE WHEN ah.follow_up_datetime $date_filter THEN 1 END) as followups_completed,
            MAX(ah.action_datetime) as last_activity_date
        FROM users u
        LEFT JOIN customers c ON u.user_id = c.assigned_user_id
        LEFT JOIN action_history ah ON c.customer_id = ah.customer_id AND ah.action_datetime $date_filter
        GROUP BY u.user_id, u.username
        ORDER BY total_activities DESC
    ");
    $user_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top performers
    $top_performers = array_slice($user_performance, 0, 5);

    // System-wide metrics
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT customer_id) as active_customers,
            COUNT(*) as total_activities,
            COUNT(CASE WHEN activity_type = 'email' THEN 1 END) as total_emails,
            COUNT(CASE WHEN activity_type = 'call' THEN 1 END) as total_calls,
            AVG(CASE WHEN follow_up_datetime IS NOT NULL THEN 
                DATEDIFF(follow_up_datetime, action_datetime) END) as avg_followup_days
        FROM action_history 
        WHERE action_datetime $date_filter
    ");
    $system_metrics = $stmt->fetch(PDO::FETCH_ASSOC);

    // Daily activity trend
    $stmt = $pdo->query("
        SELECT 
            DATE(action_datetime) as activity_date,
            COUNT(*) as daily_activities,
            COUNT(DISTINCT customer_id) as customers_contacted,
            COUNT(DISTINCT user_id) as active_users
        FROM action_history 
        WHERE action_datetime $date_filter
        GROUP BY DATE(action_datetime)
        ORDER BY activity_date DESC
        LIMIT 30
    ");
    $daily_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Customer conversion funnel
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count,
            AVG(DATEDIFF(NOW(), created_at)) as avg_days_in_status
        FROM customers 
        WHERE created_at $date_filter OR updated_at $date_filter
        GROUP BY status
        ORDER BY 
            CASE status 
                WHEN 'Prospect' THEN 1 
                WHEN 'Active' THEN 2 
                WHEN 'Inactive' THEN 3 
                ELSE 4 
            END
    ");
    $conversion_funnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    logError("Error getting performance metrics: " . $e->getMessage());
    $user_performance = [];
    $system_metrics = ['active_customers' => 0, 'total_activities' => 0, 'total_emails' => 0, 'total_calls' => 0, 'avg_followup_days' => 0];
    $daily_trends = [];
    $conversion_funnel = [];
}
?>

<div class="performance-content">
    <!-- Header with Controls -->
    <div class="performance-header">
        <div class="header-content">
            <h3>Performance Analytics</h3>
            <p>Track team performance and system metrics for <?php echo $period_label; ?></p>
        </div>
        
        <div class="date-controls">
            <form method="GET" class="date-filter-form">
                <input type="hidden" name="tab" value="performance">
                
                <div class="form-group">
                    <label>Quick Range:</label>
                    <select name="range" class="form-select form-select-sm">
                        <option value="7" <?php echo $date_range === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30" <?php echo $date_range === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="90" <?php echo $date_range === '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                        <option value="365" <?php echo $date_range === '365' ? 'selected' : ''; ?>>Last Year</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Custom Range:</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-control form-control-sm">
                </div>
                
                <div class="form-group">
                    <label>to:</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-control form-control-sm">
                </div>
                
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
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
                <div class="metric-label">Total Activities</div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon customers">
                <i class="fas fa-users"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo number_format($system_metrics['active_customers']); ?></div>
                <div class="metric-label">Customers Contacted</div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon emails">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo number_format($system_metrics['total_emails']); ?></div>
                <div class="metric-label">Emails Sent</div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon calls">
                <i class="fas fa-phone"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo number_format($system_metrics['total_calls']); ?></div>
                <div class="metric-label">Calls Made</div>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon followups">
                <i class="fas fa-clock"></i>
            </div>
            <div class="metric-content">
                <div class="metric-value"><?php echo round($system_metrics['avg_followup_days'] ?? 0, 1); ?></div>
                <div class="metric-label">Avg Follow-up Days</div>
            </div>
        </div>
    </div>

    <div class="performance-layout">
        <!-- Left Column: Team Performance -->
        <div class="performance-section">
            <h4>Team Performance</h4>
            
            <!-- Top Performers -->
            <div class="top-performers">
                <h5>Top Performers</h5>
                <div class="performers-list">
                    <?php foreach ($top_performers as $index => $performer): ?>
                        <div class="performer-item">
                            <div class="performer-rank">#<?php echo $index + 1; ?></div>
                            <div class="performer-info">
                                <div class="performer-name"><?php echo htmlspecialchars($performer['username']); ?></div>
                                <div class="performer-stats">
                                    <?php echo $performer['total_activities']; ?> activities • 
                                    <?php echo $performer['customers_assigned']; ?> customers
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
                <h5>All Users Performance</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Customers</th>
                                <th>Activities</th>
                                <th>Emails</th>
                                <th>Calls</th>
                                <th>Meetings</th>
                                <th>Last Activity</th>
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
                                        <span class="activity-badge"><?php echo $user['total_activities']; ?></span>
                                    </td>
                                    <td><?php echo $user['emails_sent']; ?></td>
                                    <td><?php echo $user['calls_made']; ?></td>
                                    <td><?php echo $user['meetings_held']; ?></td>
                                    <td>
                                        <?php echo $user['last_activity_date'] ? formatDateTimeCompact($user['last_activity_date']) : 'No activity'; ?>
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
            <h4>Analytics & Trends</h4>
            
            <!-- Conversion Funnel -->
            <div class="conversion-funnel">
                <h5>Customer Status Distribution</h5>
                <div class="funnel-chart">
                    <?php 
                    $total_customers = array_sum(array_column($conversion_funnel, 'count'));
                    foreach ($conversion_funnel as $stage): 
                        $percentage = $total_customers > 0 ? ($stage['count'] / $total_customers) * 100 : 0;
                    ?>
                        <div class="funnel-stage">
                            <div class="stage-info">
                                <div class="stage-name"><?php echo $stage['status']; ?></div>
                                <div class="stage-count"><?php echo $stage['count']; ?> customers</div>
                                <div class="stage-percentage"><?php echo round($percentage, 1); ?>%</div>
                            </div>
                            <div class="stage-bar">
                                <div class="stage-fill stage-<?php echo strtolower($stage['status']); ?>" 
                                     style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Daily Activity Trend -->
            <div class="activity-trend">
                <h5>Daily Activity Trend</h5>
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
                            <strong>Peak Day:</strong> 
                            <?php 
                            $peak_day = array_reduce($daily_trends, function($carry, $day) {
                                return $carry === null || $day['daily_activities'] > $carry['daily_activities'] ? $day : $carry;
                            });
                            echo $peak_day['activity_date'] . ' (' . $peak_day['daily_activities'] . ' activities)';
                            ?>
                        </div>
                        <div class="summary-item">
                            <strong>Average Daily:</strong> 
                            <?php echo round(array_sum(array_column($daily_trends, 'daily_activities')) / count($daily_trends), 1); ?> activities
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No activity data available for the selected period.</p>
                <?php endif; ?>
            </div>

            <!-- Quick Insights -->
            <div class="quick-insights">
                <h5>Quick Insights</h5>
                <div class="insights-list">
                    <?php
                    // Calculate insights
                    $active_users = count(array_filter($user_performance, function($user) {
                        return $user['total_activities'] > 0;
                    }));
                    $total_users = count($user_performance);
                    $engagement_rate = $total_users > 0 ? ($active_users / $total_users) * 100 : 0;
                    
                    $avg_activities_per_user = $active_users > 0 ? $system_metrics['total_activities'] / $active_users : 0;
                    ?>
                    
                    <div class="insight-item">
                        <i class="fas fa-users text-primary"></i>
                        <span><strong><?php echo round($engagement_rate, 1); ?>%</strong> user engagement rate 
                        (<?php echo $active_users; ?> of <?php echo $total_users; ?> users active)</span>
                    </div>
                    
                    <div class="insight-item">
                        <i class="fas fa-chart-bar text-success"></i>
                        <span><strong><?php echo round($avg_activities_per_user, 1); ?></strong> average activities per active user</span>
                    </div>
                    
                    <?php if (!empty($daily_trends)): ?>
                        <div class="insight-item">
                            <i class="fas fa-calendar text-info"></i>
                            <span><strong><?php echo count($daily_trends); ?></strong> days with recorded activity</span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($system_metrics['total_emails'] > 0 && $system_metrics['total_activities'] > 0): ?>
                        <div class="insight-item">
                            <i class="fas fa-envelope text-warning"></i>
                            <span><strong><?php echo round(($system_metrics['total_emails'] / $system_metrics['total_activities']) * 100, 1); ?>%</strong> 
                            of activities are emails</span>
                        </div>
                    <?php endif; ?>
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
.metric-icon.followups { background: #20c997; }

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
}

.table th {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    font-size: 0.8rem;
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
.stage-active { background: #28a745; }
.stage-inactive { background: #6c757d; }

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

/* Responsive Design */
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
}

@media (max-width: 768px) {
    .performance-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .metrics-overview {
        grid-template-columns: repeat(2, 1fr);
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
</style>
