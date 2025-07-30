<?php
// Analytics Tab Content for Admin Status Management

// Get date range for analytics
$date_range = $_GET['range'] ?? '30';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if (!empty($start_date) && !empty($end_date)) {
    $date_filter = "BETWEEN ? AND ?";
    $date_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    $period_label = "Custom Range ($start_date to $end_date)";
} else {
    $days = intval($date_range);
    $date_filter = ">= DATE_SUB(NOW(), INTERVAL $days DAY)";
    $date_params = [];
    $period_label = "Last $days Days";
}

// Get status distribution
$status_distribution = getCustomerStatusCounts();

// Get status change history
$status_changes_query = "
    SELECT 
        DATE(csh.changed_at) as change_date,
        cs_from.status_key as from_status,
        cs_to.status_key as to_status,
        COUNT(*) as change_count,
        cst_from.name as from_name,
        cst_to.name as to_name
    FROM customer_status_history csh
    LEFT JOIN customer_statuses cs_from ON csh.from_status_id = cs_from.id
    JOIN customer_statuses cs_to ON csh.to_status_id = cs_to.id
    LEFT JOIN customer_status_translations cst_from ON cs_from.id = cst_from.status_id AND cst_from.locale = ?
    LEFT JOIN customer_status_translations cst_to ON cs_to.id = cst_to.status_id AND cst_to.locale = ?
    WHERE csh.changed_at $date_filter
    GROUP BY DATE(csh.changed_at), cs_from.id, cs_to.id
    ORDER BY change_date DESC, change_count DESC
";

$current_locale = $_SESSION['language'] ?? 'en';
$stmt = $pdo->prepare($status_changes_query);
$stmt->execute(array_merge([$current_locale, $current_locale], $date_params));
$status_changes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get most active users in status changes
$active_users_query = "
    SELECT 
        u.username,
        COUNT(*) as change_count,
        COUNT(DISTINCT csh.customer_id) as customers_affected
    FROM customer_status_history csh
    JOIN users u ON csh.changed_by = u.user_id
    WHERE csh.changed_at $date_filter
    GROUP BY u.user_id, u.username
    ORDER BY change_count DESC
    LIMIT 10
";

$stmt = $pdo->prepare($active_users_query);
$stmt->execute($date_params);
$active_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get conversion funnel data
$conversion_funnel = [];
$funnel_statuses = ['prospect', 'qualified', 'new_customer', 'active_customer'];
foreach ($funnel_statuses as $status) {
    $count = 0;
    foreach ($status_distribution as $dist) {
        if ($dist['status_key'] === $status) {
            $count = $dist['count'];
            break;
        }
    }
    $conversion_funnel[] = [
        'status' => $status,
        'count' => $count,
        'name' => __($status)
    ];
}

// Calculate conversion rates
for ($i = 1; $i < count($conversion_funnel); $i++) {
    $previous_count = $conversion_funnel[$i - 1]['count'];
    $current_count = $conversion_funnel[$i]['count'];
    $conversion_funnel[$i]['conversion_rate'] = $previous_count > 0 ? round(($current_count / $previous_count) * 100, 1) : 0;
}

// Get timeline data for chart
$timeline_query = "
    SELECT 
        DATE(csh.changed_at) as date,
        cs.status_key,
        cst.name as status_name,
        COUNT(*) as count
    FROM customer_status_history csh
    JOIN customer_statuses cs ON csh.to_status_id = cs.id
    LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = ?
    WHERE csh.changed_at $date_filter
    GROUP BY DATE(csh.changed_at), cs.id
    ORDER BY date DESC
";

$stmt = $pdo->prepare($timeline_query);
$stmt->execute(array_merge([$current_locale], $date_params));
$timeline_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="analytics-content">
    <!-- Header with Date Range Controls -->
    <div class="section-header">
        <h3><?php echo __('status_analytics'); ?></h3>
        <div class="date-controls">
            <form method="GET" class="date-form">
                <input type="hidden" name="tab" value="analytics">
                <div class="date-range-selector">
                    <select name="range" class="form-select" onchange="toggleCustomDates(this.value)">
                        <option value="7" <?php echo $date_range === '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30" <?php echo $date_range === '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="90" <?php echo $date_range === '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                        <option value="365" <?php echo $date_range === '365' ? 'selected' : ''; ?>>Last Year</option>
                        <option value="custom" <?php echo !empty($start_date) ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                <div class="custom-dates" style="<?php echo empty($start_date) ? 'display: none;' : ''; ?>">
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="form-control">
                    <span>to</span>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary">Update</button>
            </form>
        </div>
    </div>

    <div class="analytics-period">
        <p class="text-muted"><?php echo __('analytics_for_period'); ?>: <strong><?php echo $period_label; ?></strong></p>
    </div>

    <!-- Status Distribution -->
    <div class="analytics-section">
        <h4><?php echo __('current_status_distribution'); ?></h4>
        <div class="distribution-grid">
            <?php foreach ($status_distribution as $status): ?>
                <div class="status-card">
                    <div class="status-header">
                        <span class="status-name"><?php echo htmlspecialchars($status['name']); ?></span>
                        <span class="status-key"><?php echo $status['status_key']; ?></span>
                    </div>
                    <div class="status-count"><?php echo number_format($status['count']); ?></div>
                    <div class="status-percentage">
                        <?php 
                        $total_customers = array_sum(array_column($status_distribution, 'count'));
                        $percentage = $total_customers > 0 ? round(($status['count'] / $total_customers) * 100, 1) : 0;
                        echo $percentage; ?>%
                    </div>
                    <div class="status-bar">
                        <div class="status-bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Conversion Funnel -->
    <div class="analytics-section">
        <h4><?php echo __('conversion_funnel'); ?></h4>
        <div class="funnel-container">
            <?php foreach ($conversion_funnel as $index => $stage): ?>
                <div class="funnel-stage">
                    <div class="funnel-box">
                        <div class="funnel-name"><?php echo htmlspecialchars($stage['name']); ?></div>
                        <div class="funnel-count"><?php echo number_format($stage['count']); ?></div>
                        <?php if (isset($stage['conversion_rate'])): ?>
                            <div class="funnel-rate"><?php echo $stage['conversion_rate']; ?>% conversion</div>
                        <?php endif; ?>
                    </div>
                    <?php if ($index < count($conversion_funnel) - 1): ?>
                        <div class="funnel-arrow">
                            <i class="fas fa-arrow-down"></i>
                            <?php if (isset($conversion_funnel[$index + 1]['conversion_rate'])): ?>
                                <span class="conversion-rate"><?php echo $conversion_funnel[$index + 1]['conversion_rate']; ?>%</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recent Status Changes -->
    <div class="analytics-section">
        <h4><?php echo __('recent_status_changes'); ?></h4>
        <?php if (empty($status_changes)): ?>
            <p class="text-muted"><?php echo __('no_status_changes_in_period'); ?></p>
        <?php else: ?>
            <div class="changes-table-wrapper">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th><?php echo __('date'); ?></th>
                            <th><?php echo __('from_status'); ?></th>
                            <th><?php echo __('to_status'); ?></th>
                            <th><?php echo __('count'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($status_changes, 0, 20) as $change): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($change['change_date'])); ?></td>
                                <td>
                                    <?php if ($change['from_status']): ?>
                                        <span class="status-badge from-status">
                                            <?php echo htmlspecialchars($change['from_name'] ?: $change['from_status']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted"><?php echo __('initial_status'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge to-status">
                                        <?php echo htmlspecialchars($change['to_name'] ?: $change['to_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="change-count"><?php echo $change['change_count']; ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Most Active Users -->
    <div class="analytics-section">
        <h4><?php echo __('most_active_users'); ?></h4>
        <?php if (empty($active_users)): ?>
            <p class="text-muted"><?php echo __('no_user_activity_in_period'); ?></p>
        <?php else: ?>
            <div class="users-grid">
                <?php foreach ($active_users as $user): ?>
                    <div class="user-activity-card">
                        <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                        <div class="user-stats">
                            <div class="stat">
                                <span class="stat-value"><?php echo $user['change_count']; ?></span>
                                <span class="stat-label"><?php echo __('changes_made'); ?></span>
                            </div>
                            <div class="stat">
                                <span class="stat-value"><?php echo $user['customers_affected']; ?></span>
                                <span class="stat-label"><?php echo __('customers_affected'); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Status Timeline Chart Area -->
    <div class="analytics-section">
        <h4><?php echo __('status_change_timeline'); ?></h4>
        <div class="chart-container">
            <canvas id="statusTimelineChart" height="300"></canvas>
        </div>
    </div>
</div>

<style>
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.date-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.date-form {
    display: flex;
    align-items: center;
    gap: 10px;
}

.custom-dates {
    display: flex;
    align-items: center;
    gap: 10px;
}

.analytics-period {
    margin-bottom: 30px;
}

.analytics-section {
    margin-bottom: 40px;
    padding: 20px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
}

.distribution-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.status-card {
    padding: 20px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #f8f9fa;
}

.status-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.status-name {
    font-weight: 600;
    color: #495057;
}

.status-key {
    font-size: 12px;
    color: #6c757d;
    font-family: monospace;
}

.status-count {
    font-size: 2rem;
    font-weight: bold;
    color: #007bff;
    margin-bottom: 5px;
}

.status-percentage {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 10px;
}

.status-bar {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}

.status-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #007bff, #0056b3);
    transition: width 0.3s ease;
}

.funnel-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    margin-top: 20px;
}

.funnel-stage {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.funnel-box {
    background: #007bff;
    color: white;
    padding: 20px 40px;
    border-radius: 8px;
    text-align: center;
    min-width: 200px;
}

.funnel-name {
    font-weight: 600;
    margin-bottom: 5px;
}

.funnel-count {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.funnel-rate {
    font-size: 12px;
    opacity: 0.9;
}

.funnel-arrow {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin: 10px 0;
    color: #6c757d;
}

.conversion-rate {
    font-size: 12px;
    font-weight: 600;
    margin-top: 5px;
}

.changes-table-wrapper {
    max-height: 400px;
    overflow-y: auto;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.status-badge.from-status {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.to-status {
    background: #d4edda;
    color: #155724;
}

.change-count {
    background: #e9ecef;
    color: #495057;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.user-activity-card {
    padding: 15px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    background: #f8f9fa;
}

.user-name {
    font-weight: 600;
    color: #495057;
    margin-bottom: 10px;
}

.user-stats {
    display: flex;
    justify-content: space-between;
}

.stat {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 1.2rem;
    font-weight: bold;
    color: #007bff;
}

.stat-label {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
}

.chart-container {
    height: 300px;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .date-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .custom-dates {
        flex-direction: column;
    }
    
    .funnel-container {
        transform: scale(0.8);
    }
}
</style>

<script>
function toggleCustomDates(value) {
    const customDates = document.querySelector('.custom-dates');
    if (value === 'custom') {
        customDates.style.display = 'flex';
    } else {
        customDates.style.display = 'none';
    }
}

// Chart.js implementation for timeline chart
document.addEventListener('DOMContentLoaded', function() {
    const timelineData = <?php echo json_encode($timeline_data); ?>;
    
    // Process data for chart
    const dates = [...new Set(timelineData.map(item => item.date))].sort();
    const statuses = [...new Set(timelineData.map(item => item.status_key))];
    
    const datasets = statuses.map((status, index) => {
        const data = dates.map(date => {
            const item = timelineData.find(d => d.date === date && d.status_key === status);
            return item ? item.count : 0;
        });
        
        const colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#20c997'];
        
        return {
            label: status,
            data: data,
            borderColor: colors[index % colors.length],
            backgroundColor: colors[index % colors.length] + '20',
            fill: false,
            tension: 0.1
        };
    });
    
    const ctx = document.getElementById('statusTimelineChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates.map(date => new Date(date).toLocaleDateString()),
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Status Changes Over Time'
                },
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>
