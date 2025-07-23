<?php
// Dashboard Tab Content for Admin Customer Management
?>

<div class="dashboard-content">
    <!-- Key Metrics Cards -->
    <div class="metric-cards">
        <div class="metric-card">
            <h3><?php echo number_format($dashboard_metrics['total_customers']); ?></h3>
            <p>Total Customers</p>
        </div>
        
        <div class="metric-card <?php echo $dashboard_metrics['unassigned_customers'] > 0 ? 'warning' : 'success'; ?>">
            <h3><?php echo number_format($dashboard_metrics['unassigned_customers']); ?></h3>
            <p>Unassigned Customers</p>
        </div>
        
        <div class="metric-card">
            <h3><?php echo number_format($dashboard_metrics['active_users']); ?></h3>
            <p>Users with Assignments</p>
        </div>
        
        <div class="metric-card">
            <h3><?php echo number_format($dashboard_metrics['total_users']); ?></h3>
            <p>Total Users</p>
        </div>
        
        <div class="metric-card">
            <h3><?php echo number_format($dashboard_metrics['recent_activities']); ?></h3>
            <p>Activities (Last 7 Days)</p>
        </div>
        
        <div class="metric-card <?php echo $dashboard_metrics['overdue_followups'] > 0 ? 'danger' : 'success'; ?>">
            <h3><?php echo number_format($dashboard_metrics['overdue_followups']); ?></h3>
            <p>Overdue Follow-ups</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h4>Quick Actions</h4>
        <div class="action-buttons">
            <?php if ($dashboard_metrics['unassigned_customers'] > 0): ?>
                <a href="?tab=bulk_assignment&filter=unassigned" class="btn btn-primary">
                    Assign Unassigned Customers (<?php echo $dashboard_metrics['unassigned_customers']; ?>)
                </a>
            <?php endif; ?>
            
            <a href="?tab=user_overview" class="btn btn-secondary">
                View User Workloads
            </a>
            
            <?php if ($dashboard_metrics['overdue_followups'] > 0): ?>
                <a href="?tab=performance&filter=overdue" class="btn btn-warning">
                    Manage Overdue Follow-ups
                </a>
            <?php endif; ?>
            
            <a href="?tab=reports" class="btn btn-info">
                Generate Reports
            </a>
        </div>
    </div>

    <!-- Two Column Layout for Charts -->
    <div class="dashboard-grid">
        <!-- User Distribution Chart -->
        <div class="dashboard-section">
            <h4>Customer Distribution by User</h4>
            <div class="distribution-chart">
                <?php if (!empty($dashboard_metrics['user_distribution'])): ?>
                    <?php foreach ($dashboard_metrics['user_distribution'] as $user): ?>
                        <div class="user-bar">
                            <div class="user-info">
                                <span class="username"><?php echo htmlspecialchars($user['username']); ?></span>
                                <span class="count"><?php echo $user['customer_count']; ?> customers</span>
                            </div>
                            <div class="progress-bar">
                                <?php 
                                $max_customers = $dashboard_metrics['user_distribution'][0]['customer_count'];
                                $percentage = $max_customers > 0 ? ($user['customer_count'] / $max_customers) * 100 : 0;
                                ?>
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-data">No assignments found.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="dashboard-section">
            <h4>Recent Assignment Activity</h4>
            <div class="activity-feed">
                <?php if (!empty($dashboard_metrics['recent_assignments'])): ?>
                    <?php foreach (array_slice($dashboard_metrics['recent_assignments'], 0, 8) as $assignment): ?>
                        <div class="activity-item">
                            <div class="activity-content">
                                <strong><?php echo htmlspecialchars($assignment['company_name']); ?></strong>
                                <?php if ($assignment['username']): ?>
                                    assigned to <span class="user-tag"><?php echo htmlspecialchars($assignment['username']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">unassigned</span>
                                <?php endif; ?>
                            </div>
                            <div class="activity-time">
                                <?php echo formatDateTimeCompact($assignment['created_at']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (count($dashboard_metrics['recent_assignments']) > 8): ?>
                        <div class="activity-item view-more">
                            <a href="?tab=reports">View more activity...</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="no-data">No recent assignments found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- System Status -->
    <div class="system-status">
        <h4>System Status</h4>
        <div class="status-grid">
            <div class="status-item <?php echo $dashboard_metrics['unassigned_customers'] == 0 ? 'status-good' : 'status-warning'; ?>">
                <span class="status-label">Assignment Coverage</span>
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
                <span class="status-label">Follow-up Health</span>
                <span class="status-value">
                    <?php echo $dashboard_metrics['overdue_followups'] == 0 ? 'Good' : $dashboard_metrics['overdue_followups'] . ' overdue'; ?>
                </span>
            </div>
            
            <div class="status-item status-good">
                <span class="status-label">System Status</span>
                <span class="status-value">Operational</span>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-content {
    max-width: 1200px;
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
}
</style>
