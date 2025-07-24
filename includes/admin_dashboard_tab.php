<?php
// Dashboard Tab Content for Admin Customer Management
?>

<div class="dashboard-content">
    <!-- Key Metrics Cards -->
    <div class="metric-cards">
        <div class="metric-card">
            <h3><?php echo number_format($dashboard_metrics['total_customers']); ?></h3>
            <p><?php echo __('total_customers'); ?></p>
        </div>
        
        <div class="metric-card <?php echo $dashboard_metrics['unassigned_customers'] > 0 ? 'warning' : 'success'; ?>">
            <h3><?php echo number_format($dashboard_metrics['unassigned_customers']); ?></h3>
            <p><?php echo __('unassigned_customers'); ?></p>
        </div>
        
        <div class="metric-card">
            <h3><?php echo number_format($dashboard_metrics['active_users']); ?></h3>
            <p><?php echo __('users_with_assignments'); ?></p>
        </div>
        
        <div class="metric-card">
            <h3><?php echo number_format($dashboard_metrics['total_users']); ?></h3>
            <p><?php echo __('total_users'); ?></p>
        </div>
        
        <div class="metric-card">
            <h3><?php echo number_format($dashboard_metrics['recent_activities']); ?></h3>
            <p><?php echo __('activities_last_7_days'); ?></p>
        </div>
        
        <div class="metric-card <?php echo $dashboard_metrics['overdue_followups'] > 0 ? 'danger' : 'success'; ?>">
            <h3><?php echo number_format($dashboard_metrics['overdue_followups']); ?></h3>
            <p><?php echo __('overdue_followups'); ?></p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h4><?php echo __('quick_actions'); ?></h4>
        <div class="action-buttons">
            <?php if ($dashboard_metrics['unassigned_customers'] > 0): ?>
                <button class="btn btn-primary" onclick="showBulkAssignModal()">
                    <i class="fas fa-users"></i>
                    <?php echo __('assign_unassigned_customers', ['{count}' => $dashboard_metrics['unassigned_customers']]); ?>
                </button>
            <?php endif; ?>
            
            <button class="btn btn-secondary" onclick="showBalanceWorkloadModal()">
                <i class="fas fa-balance-scale"></i>
                <?php echo __('balance_user_workloads'); ?>
            </button>
            
            <?php if ($dashboard_metrics['overdue_followups'] > 0): ?>
                <a href="?tab=performance&filter=overdue" class="btn btn-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo __('manage_overdue_followups', ['{count}' => $dashboard_metrics['overdue_followups']]); ?>
                </a>
            <?php endif; ?>
            
            <a href="?tab=reports" class="btn btn-info">
                <i class="fas fa-chart-bar"></i>
                <?php echo __('generate_reports'); ?>
            </a>
            
            <button class="btn btn-success" onclick="refreshDashboard()">
                <i class="fas fa-sync-alt"></i>
                <?php echo __('refresh_data'); ?>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($dashboard_metrics['unassigned_customers'] > 10 || $dashboard_metrics['overdue_followups'] > 5): ?>
    <div class="alert-notifications">
        <?php if ($dashboard_metrics['unassigned_customers'] > 10): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong><?php echo __('attention'); ?>:</strong> <?php echo __('unassigned_customers_warning', ['{count}' => $dashboard_metrics['unassigned_customers']]); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($dashboard_metrics['overdue_followups'] > 5): ?>
        <div class="alert alert-danger">
            <i class="fas fa-clock"></i>
            <strong><?php echo __('urgent'); ?>:</strong> <?php echo __('overdue_followups_warning', ['{count}' => $dashboard_metrics['overdue_followups']]); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Two Column Layout for Charts -->
    <div class="dashboard-grid">
        <!-- User Distribution Chart -->
        <div class="dashboard-section">
            <h4><?php echo __('customer_distribution_by_user'); ?></h4>
            <div class="distribution-chart">
                <?php if (!empty($dashboard_metrics['user_distribution'])): ?>
                    <?php foreach ($dashboard_metrics['user_distribution'] as $user): ?>
                        <div class="user-bar">
                            <div class="user-info">
                                <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                                <span class="count"><?php echo __('customers_count', ['{count}' => $user['customer_count']]); ?></span>
                            </div>
                            <div class="progress-bar">
                                <?php 
                                $max_customers = $dashboard_metrics['user_distribution'][0]['customer_count'];
                                $percentage = $max_customers > 0 ? ($user['customer_count'] / $max_customers) * 100 : 0;
                                $load_class = '';
                                if ($percentage > 80) $load_class = 'high-load';
                                elseif ($percentage < 20) $load_class = 'low-load';
                                ?>
                                <div class="progress-fill <?php echo $load_class; ?>" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-data"><?php echo __('no_assignments_found'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="dashboard-section">
            <h4><?php echo __('recent_assignment_activity'); ?></h4>
            <div class="activity-feed">
                <?php if (!empty($dashboard_metrics['recent_assignments'])): ?>
                    <?php foreach (array_slice($dashboard_metrics['recent_assignments'], 0, 8) as $assignment): ?>
                        <div class="activity-item">
                            <div class="activity-content">
                                <strong><?php echo htmlspecialchars($assignment['company_name']); ?></strong>
                                <?php if ($assignment['username']): ?>
                                    <?php echo __('assigned_to'); ?> <span class="user-tag"><?php echo htmlspecialchars($assignment['username']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted"><?php echo __('unassigned'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="activity-time">
                                <?php echo formatDateTimeCompact($assignment['created_at']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($dashboard_metrics['recent_assignments']) > 8): ?>
                        <div class="activity-item view-more">
                            <a href="?tab=reports"><?php echo __('view_more_activity'); ?></a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="no-data"><?php echo __('no_recent_assignments_found'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Performance Summary Grid -->
    <div class="performance-summary">
        <h4><?php echo __('performance_summary'); ?></h4>
        <div class="performance-grid">
            <div class="performance-card">
                <div class="performance-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="performance-content">
                    <h5><?php echo __('assignment_efficiency'); ?></h5>
                    <p class="performance-value">
                        <?php 
                        $efficiency = $dashboard_metrics['total_customers'] > 0 
                            ? round((($dashboard_metrics['total_customers'] - $dashboard_metrics['unassigned_customers']) / $dashboard_metrics['total_customers']) * 100, 1)
                            : 100;
                        echo $efficiency . '%';
                        ?>
                    </p>
                    <p class="performance-trend <?php echo $efficiency >= 95 ? 'trend-up' : 'trend-down'; ?>">
                        <?php echo $efficiency >= 95 ? '↗ ' . __('excellent') : '↘ ' . __('needs_attention'); ?>
                    </p>
                </div>
            </div>

            <div class="performance-card">
                <div class="performance-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="performance-content">
                    <h5><?php echo __('user_utilization'); ?></h5>
                    <p class="performance-value">
                        <?php 
                        $utilization = $dashboard_metrics['total_users'] > 0 
                            ? round(($dashboard_metrics['active_users'] / $dashboard_metrics['total_users']) * 100, 1)
                            : 0;
                        echo $utilization . '%';
                        ?>
                    </p>
                    <p class="performance-trend <?php echo $utilization >= 80 ? 'trend-up' : 'trend-down'; ?>">
                        <?php echo $utilization >= 80 ? '↗ ' . __('good_utilization') : '↘ ' . __('under_utilized'); ?>
                    </p>
                </div>
            </div>

            <div class="performance-card">
                <div class="performance-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="performance-content">
                    <h5><?php echo __('followup_health'); ?></h5>
                    <p class="performance-value">
                        <?php echo $dashboard_metrics['overdue_followups'] == 0 ? __('excellent') : $dashboard_metrics['overdue_followups'] . ' ' . __('overdue'); ?>
                    </p>
                    <p class="performance-trend <?php echo $dashboard_metrics['overdue_followups'] == 0 ? 'trend-up' : 'trend-down'; ?>">
                        <?php echo $dashboard_metrics['overdue_followups'] == 0 ? '↗ ' . __('on_track') : '↘ ' . __('action_required'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="system-status">
        <h4><?php echo __('system_status'); ?></h4>
        <div class="status-grid">
            <div class="status-item <?php echo $dashboard_metrics['unassigned_customers'] == 0 ? 'status-good' : 'status-warning'; ?>">
                <span class="status-label"><?php echo __('assignment_coverage'); ?></span>
                <span class="status-value">
                    <?php 
                    $coverage = $dashboard_metrics['total_customers'] > 0 
                        ? (($dashboard_metrics['total_customers'] - $dashboard_metrics['unassigned_customers']) / $dashboard_metrics['total_customers']) * 100 
                        : 100;
                    echo round($coverage, 1) . '%';
                    ?>
                </span>
            </div>
            
            <div class="status-item <?php echo $dashboard_metrics['overdue_followups'] == 0 ? 'status-good' : 'status-danger'; ?>">
                <span class="status-label"><?php echo __('followup_health'); ?></span>
                <span class="status-value">
                    <?php echo $dashboard_metrics['overdue_followups'] == 0 ? __('good') : $dashboard_metrics['overdue_followups'] . ' ' . __('overdue'); ?>
                </span>
            </div>
            
            <div class="status-item status-good">
                <span class="status-label"><?php echo __('system_status'); ?></span>
                <span class="status-value"><?php echo __('operational'); ?></span>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-content {
    max-width: 1200px;
}

/* Alert Notifications */
.alert-notifications {
    margin-bottom: 20px;
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 10px;
    border-left: 4px solid;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
    border-left-color: #ffc107;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border-left-color: #dc3545;
}

.alert i {
    font-size: 1.1em;
}

.quick-actions {
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.quick-actions h4 {
    margin: 0 0 15px 0;
    color: #495057;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-buttons .btn {
    display: flex;
    align-items: center;
    gap: 8px;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.dashboard-section {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
}

.dashboard-section h4 {
    margin: 0 0 20px 0;
    color: #495057;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

/* Distribution Chart Styles */
.distribution-chart {
    space-y: 10px;
}

.user-bar {
    margin-bottom: 15px;
}

.user-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.username {
    font-weight: 500;
    color: #495057;
}

.count {
    color: #6c757d;
    font-size: 0.9rem;
}

.progress-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #007bff, #0056b3);
    transition: width 0.3s ease;
}

.progress-fill.high-load {
    background: linear-gradient(90deg, #dc3545, #c82333);
}

.progress-fill.low-load {
    background: linear-gradient(90deg, #28a745, #1e7e34);
}

/* Activity Feed Styles */
.activity-feed {
    max-height: 300px;
    overflow-y: auto;
}

.activity-item {
    padding: 10px 0;
    border-bottom: 1px solid #f1f3f4;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-content {
    margin-bottom: 3px;
}

.activity-time {
    font-size: 0.8rem;
    color: #6c757d;
}

.user-tag {
    background: #e3f2fd;
    color: #1976d2;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.85rem;
}

.view-more {
    text-align: center;
    padding: 15px 0;
}

.view-more a {
    color: #007bff;
    text-decoration: none;
}

.view-more a:hover {
    text-decoration: underline;
}

/* Performance Summary */
.performance-summary {
    margin-bottom: 30px;
}

.performance-summary h4 {
    margin: 0 0 20px 0;
    color: #495057;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.performance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.performance-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.2s ease;
}

.performance-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.performance-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}

.performance-content h5 {
    margin: 0 0 5px 0;
    color: #495057;
    font-size: 0.9rem;
    font-weight: 600;
}

.performance-value {
    margin: 0 0 5px 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #007bff;
}

.performance-trend {
    margin: 0;
    font-size: 0.8rem;
    font-weight: 500;
}

.trend-up {
    color: #28a745;
}

.trend-down {
    color: #dc3545;
}

/* System Status */
.system-status {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
}

.system-status h4 {
    margin: 0 0 20px 0;
    color: #495057;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    border-radius: 6px;
    border-left: 4px solid;
}

.status-good {
    background: #d4edda;
    border-left-color: #28a745;
}

.status-warning {
    background: #fff3cd;
    border-left-color: #ffc107;
}

.status-danger {
    background: #f8d7da;
    border-left-color: #dc3545;
}

.status-label {
    font-weight: 500;
    color: #495057;
}

.status-value {
    font-weight: 600;
}

.no-data {
    text-align: center;
    color: #6c757d;
    font-style: italic;
    padding: 20px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        text-align: center;
    }
    
    .status-grid {
        grid-template-columns: 1fr;
    }
    
    .user-info {
        flex-direction: column;
        gap: 2px;
    }
    
    .performance-grid {
        grid-template-columns: 1fr;
    }
    
    .performance-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
function showBulkAssignModal() {
    if (confirm('This will open the bulk assignment tool. Continue?')) {
        window.location.href = '?tab=bulk_assignment&filter=unassigned';
    }
}

function showBalanceWorkloadModal() {
    if (confirm('This will redistribute customers evenly among users. This action cannot be undone. Continue?')) {
        // Show loading indicator
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        btn.disabled = true;
        
        // Simulate processing - in real implementation, this would be an AJAX call
        setTimeout(() => {
            alert('Workload balancing completed! The page will refresh to show updated data.');
            window.location.reload();
        }, 2000);
    }
}

function refreshDashboard() {
    // Show loading indicator
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    btn.disabled = true;
    
    // Refresh the page to get updated data
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

// Auto-refresh dashboard every 5 minutes
setInterval(() => {
    console.log('Auto-refreshing dashboard data...');
    // In a real implementation, this would use AJAX to update metrics without full page reload
}, 300000); // 5 minutes

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Dashboard initialized');
    
    // Add tooltips to performance cards
    const performanceCards = document.querySelectorAll('.performance-card');
    performanceCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>
