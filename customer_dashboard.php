<?php
require_once 'includes/functions.php';

requireLogin();

// Role and user detection
$isAdmin = isAdmin();
$currentUserId = $_SESSION['user_id'];

// Handle view mode for admin users
$viewMode = 'my'; // Default view mode
$userFilter = null;
$statusFilter = null;

if ($isAdmin && isset($_GET['view'])) {
    $validViews = ['my', 'all', 'unassigned'];
    if (in_array($_GET['view'], $validViews)) {
        $viewMode = $_GET['view'];
    }
}

// Handle filters for admin users
if ($isAdmin && ($viewMode === 'all' || $viewMode === 'unassigned')) {
    if (isset($_GET['user_filter']) && !empty($_GET['user_filter'])) {
        $userFilter = (int)$_GET['user_filter'];
    }
    if (isset($_GET['status_filter']) && !empty($_GET['status_filter'])) {
        $statusFilter = $_GET['status_filter'];
    }
}

// Handle status summary date filtering
$statusDateFrom = $_GET['status_date_from'] ?? null;
$statusDateTo = $_GET['status_date_to'] ?? null;

// Determine data scope based on role and view mode
$showAll = $isAdmin && ($viewMode === 'all' || $viewMode === 'unassigned');
$userId = ($showAll) ? null : $currentUserId;

try {
    // Load dashboard data based on role
    if ($isAdmin) {
        // Admin dashboard data
        $customerStats = getDashboardCustomerStats($userId, $showAll, $userFilter, $statusFilter);
        $customerData = getDashboardCustomers(15, $userId, $showAll, $viewMode, $userFilter, $statusFilter);
        $upcomingFollowups = getDashboardFollowups(5, $userId, $showAll);
        $recentActivities = getDashboardActivities(5, $userId, $showAll);
        $userPerformance = getUserPerformanceStats();
        $assignmentStats = getCustomersByAssignment();
        $allUsers = getAllUsers();
        $statusOverview = getCustomerStatusSummary($userId, $showAll, $statusDateFrom, $statusDateTo);
    } else {
        // Regular user dashboard data
        $customerStats = getDashboardCustomerStats($currentUserId, false);
        $customerData = getDashboardCustomers(10, $currentUserId, false, 'recent');
        $upcomingFollowups = getDashboardFollowups(5, $currentUserId, false);
        $recentActivities = getDashboardActivities(5, $currentUserId, false);
        $statusOverview = getCustomerStatusSummary($currentUserId, false, $statusDateFrom, $statusDateTo);
    }
} catch (Exception $e) {
    die("Error loading dashboard data: " . $e->getMessage());
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="header">
        <h1>
            <?php if ($isAdmin): ?>
                <?php if ($viewMode === 'all'): ?>
                    <?php echo __('admin_dashboard_all'); ?>
                <?php elseif ($viewMode === 'unassigned'): ?>
                    <?php echo __('admin_dashboard_unassigned'); ?>
                <?php else: ?>
                    <?php echo __('admin_dashboard_my'); ?>
                <?php endif; ?>
            <?php else: ?>
                <?php echo __('my_customer_dashboard'); ?>
            <?php endif; ?>
        </h1>
        <a href="customer_form.php?action=add" class="btn btn-primary">
            <?php echo __('add_new_customer'); ?>
        </a>
    </div>
    
    <div class="dashboard-grid">
        <!-- Statistics Cards Row -->
        <div class="stats-row">
            <!-- Primary Statistics Card -->
            <div class="dashboard-card stats-primary">
                <h2>
                    <?php if ($isAdmin && $showAll): ?>
                        <?php echo __('system_statistics'); ?>
                    <?php else: ?>
                        <?php echo __('my_statistics'); ?>
                    <?php endif; ?>
                </h2>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $customerStats['total_customers']; ?></div>
                        <div class="stat-label"><?php echo __('total_customers'); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $customerStats['active_customers']; ?></div>
                        <div class="stat-label"><?php echo __('active_customers'); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $customerStats['prospects']; ?></div>
                        <div class="stat-label"><?php echo __('prospects'); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $customerStats['contact_rate']; ?>%</div>
                        <div class="stat-label"><?php echo __('contact_rate'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Contact Status Card -->
            <div class="dashboard-card">
                <h2><?php echo __('contact_status'); ?></h2>
                <div class="contact-stats">
                    <div class="contact-item">
                        <span class="contact-label"><?php echo __('contacted'); ?>:</span>
                        <span class="contact-value"><?php echo $customerStats['contacted_customers']; ?></span>
                        <div class="contact-bar contacted" style="width: <?php echo $customerStats['contact_rate']; ?>%"></div>
                    </div>
                    <div class="contact-item">
                        <span class="contact-label"><?php echo __('not_contacted'); ?>:</span>
                        <span class="contact-value"><?php echo $customerStats['not_contacted']; ?></span>
                        <div class="contact-bar not-contacted" style="width: <?php echo (100 - $customerStats['contact_rate']); ?>%"></div>
                    </div>
                </div>
            </div>

            <?php if ($isAdmin && $showAll): ?>
            <!-- Admin-specific: Assignment Status Card -->
            <div class="dashboard-card">
                <h2><?php echo __('assignment_status'); ?></h2>
                <div class="assignment-stats">
                    <div class="assignment-item">
                        <span class="assignment-label"><?php echo __('assigned'); ?>:</span>
                        <span class="assignment-value"><?php echo $assignmentStats['assigned_customers']; ?></span>
                    </div>
                    <div class="assignment-item">
                        <span class="assignment-label"><?php echo __('unassigned'); ?>:</span>
                        <span class="assignment-value"><?php echo $assignmentStats['unassigned_customers']; ?></span>
                        <?php if ($assignmentStats['unassigned_customers'] > 0): ?>
                        <a href="?view=unassigned" class="btn btn-sm btn-warning">
                            <?php echo __('view_unassigned'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Customer Status Overview Card -->
        <div class="dashboard-card">
            <h2>
                <?php echo __('status_summary'); ?>
                <a href="#" class="btn btn-sm btn-outline-secondary" onclick="showStatusFilters()" title="<?php echo __('filter_by_time'); ?>">
                    <i class="fas fa-filter"></i>
                </a>
            </h2>
            
            <!-- Time Filter (Hidden by default) -->
            <div id="status-filters" class="status-filters" style="display: none;">
                <form method="GET" class="inline-filter-form">
                    <?php if ($isAdmin): ?>
                        <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
                        <?php if ($userFilter): ?>
                            <input type="hidden" name="user_filter" value="<?php echo htmlspecialchars($userFilter); ?>">
                        <?php endif; ?>
                        <?php if ($statusFilter): ?>
                            <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($statusFilter); ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="filter-row">
                        <label for="status_date_from"><?php echo __('from'); ?>:</label>
                        <input type="date" name="status_date_from" id="status_date_from" 
                               value="<?php echo $_GET['status_date_from'] ?? date('Y-m-d', strtotime('-30 days')); ?>" 
                               class="form-control form-control-sm">
                        
                        <label for="status_date_to"><?php echo __('to'); ?>:</label>
                        <input type="date" name="status_date_to" id="status_date_to" 
                               value="<?php echo $_GET['status_date_to'] ?? date('Y-m-d'); ?>" 
                               class="form-control form-control-sm">
                        
                        <button type="submit" class="btn btn-sm btn-primary"><?php echo __('apply'); ?></button>
                    </div>
                </form>
            </div>
            
            <div class="status-overview">
                <?php if (!empty($statusOverview)): ?>
                    <div class="status-summary-grid">
                        <?php foreach ($statusOverview as $status): ?>
                        <div class="status-summary-item">
                            <div class="status-header">
                                <span class="status-badge status-<?= str_replace(['_', '-'], '', $status['status_key']) ?>">
                                    <?= htmlspecialchars($status['status_name']) ?>
                                </span>
                                <span class="status-main-count"><?= number_format($status['count']) ?></span>
                            </div>
                            
                            <?php if (!$isAdmin || $viewMode === 'my'): ?>
                            <!-- Show detailed info for user's own customers -->
                            <div class="status-details">
                                <?php if ($status['new_this_week'] > 0): ?>
                                <div class="status-detail">
                                    <span class="detail-label"><?php echo __('new_this_week'); ?>:</span>
                                    <span class="detail-value"><?= $status['new_this_week'] ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($status['new_this_month'] > 0): ?>
                                <div class="status-detail">
                                    <span class="detail-label"><?php echo __('new_this_month'); ?>:</span>
                                    <span class="detail-value"><?= $status['new_this_month'] ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($status['avg_days_in_status'] > 0): ?>
                                <div class="status-detail">
                                    <span class="detail-label"><?php echo __('avg_days'); ?>:</span>
                                    <span class="detail-value"><?= $status['avg_days_in_status'] ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-data"><?php echo __('no_customers_found'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isAdmin && !empty($userPerformance) && $showAll): ?>
        <!-- Admin-specific: User Performance Card -->
        <div class="dashboard-card full-width">
            <h2><?php echo __('top_performers'); ?></h2>
            <div class="performance-list">
                <?php foreach ($userPerformance as $user): ?>
                <div class="performance-item">
                    <div class="user-info">
                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        <span class="user-stats">
                            <?php echo $user['total_customers']; ?> <?php echo __('customers'); ?> | 
                            <?php echo $user['active_customers']; ?> <?php echo __('active'); ?> |
                            <?php echo $user['recent_activities']; ?> <?php echo __('recent_activities'); ?>
                        </span>
                    </div>
                    <div class="performance-bar">
                        <div class="bar-fill" style="width: <?php echo min(100, ($user['active_customers'] / max(1, $user['total_customers'])) * 100); ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Customer List Section -->
        <div class="dashboard-card full-width customer-section">
            <h2>
                <?php if ($isAdmin): ?>
                    <?php if ($viewMode === 'all'): ?>
                        <?php echo __('all_customers'); ?>
                    <?php elseif ($viewMode === 'unassigned'): ?>
                        <?php echo __('unassigned_customers'); ?>
                    <?php else: ?>
                        <?php echo __('my_customers'); ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?php echo __('my_customers'); ?>
                <?php endif; ?>
                <span class="customer-count">(<?php echo count($customerData); ?>)</span>
            </h2>
            
            <?php 
            // Set variables for the customer list component
            $customers = $customerData;
            include 'includes/dashboard_customer_list.php'; 
            ?>
        </div>

        <!-- Follow-ups and Activities Row -->
        <div class="activities-row">
            <!-- Upcoming Follow-ups -->
            <?php if (!empty($upcomingFollowups)): ?>
            <div class="dashboard-card">
                <h2>
                    <?php echo __('upcoming_followups'); ?> 
                    <span class="count">(<?php echo count($upcomingFollowups); ?>)</span>
                    <a href="all_followups.php" class="btn btn-sm"><?php echo __('view_all'); ?></a>
                </h2>
                <div class="followup-list">
                    <?php foreach ($upcomingFollowups as $followup): ?>
                    <div class="followup-item">
                        <div class="followup-content">
                            <strong><?php echo htmlspecialchars($followup['company_name']); ?></strong>
                            <?php if ($isAdmin && $showAll && $followup['assigned_username']): ?>
                            <span class="assigned-to">(<?php echo htmlspecialchars($followup['assigned_username']); ?>)</span>
                            <?php endif; ?>
                            <div class="followup-details">
                                <span class="followup-date"><?php echo formatDateTime($followup['follow_up_datetime']); ?></span>
                                <?php if (!empty($followup['next_step'])): ?>
                                <span class="followup-step"><?php echo htmlspecialchars($followup['next_step']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activities -->
            <?php if (!empty($recentActivities)): ?>
            <div class="dashboard-card">
                <h2>
                    <?php echo __('recent_activities'); ?> 
                    <span class="count">(<?php echo count($recentActivities); ?>)</span>
                    <a href="all_activities.php" class="btn btn-sm"><?php echo __('view_all'); ?></a>
                </h2>
                <div class="activity-list">
                    <?php foreach ($recentActivities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-content">
                            <strong><?php echo htmlspecialchars($activity['company_name']); ?></strong>
                            <?php if ($isAdmin && $showAll && $activity['assigned_username']): ?>
                            <span class="assigned-to">(<?php echo htmlspecialchars($activity['assigned_username']); ?>)</span>
                            <?php endif; ?>
                            <div class="activity-details">
                                <span class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></span>
                                <?php if (!empty($activity['contact_channel'])): ?>
                                <span class="contact-channel channel-<?php echo strtolower($activity['contact_channel']); ?>">
                                    <?php echo __($activity['contact_channel']); ?>
                                </span>
                                <?php endif; ?>
                                <span class="activity-date"><?php echo formatDateTime($activity['action_datetime']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Empty State for Follow-ups and Activities -->
        <?php if (empty($upcomingFollowups) && empty($recentActivities)): ?>
        <div class="dashboard-card full-width empty-state">
            <div class="empty-content">
                <h3><?php echo __('no_recent_activity'); ?></h3>
                <p><?php echo __('start_by_adding_customer_activity'); ?></p>
                <a href="customer_form.php?action=add" class="btn btn-primary">
                    <?php echo __('add_customer'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.dashboard-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.stats-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 20px;
}

.stats-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.stats-primary h2 {
    color: white;
    margin-bottom: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.contact-stats {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.contact-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
}

.contact-bar {
    height: 8px;
    border-radius: 4px;
    margin-top: 5px;
    width: 100%;
    max-width: 100px;
}

.contact-bar.contacted {
    background: #28a745;
}

.contact-bar.not-contacted {
    background: #dc3545;
}

.assignment-stats {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.assignment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
}

.performance-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.performance-item {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.user-info strong {
    display: block;
    margin-bottom: 5px;
    color: #1a202c;
    font-weight: 600;
}

.user-stats {
    font-size: 0.9rem;
    color: #6c757d;
}

.performance-bar {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    margin-top: 10px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    transition: width 0.3s ease;
}

.customer-section {
    margin: 20px 0;
}

.customer-count {
    color: #6c757d;
    font-weight: normal;
    font-size: 0.9rem;
}

.activities-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.followup-list, .activity-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.followup-item, .activity-item {
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #007bff;
}

.followup-content strong, .activity-content strong {
    color: #1a202c;
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.assigned-to {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: normal;
}

.followup-details, .activity-details {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.followup-date, .activity-date {
    color: #17a2b8;
    font-size: 0.85rem;
    font-weight: 500;
}

.followup-step, .activity-action {
    color: #495057;
    font-size: 0.9rem;
}

.contact-channel {
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.channel-email { background: #e3f2fd; color: #1565c0; }
.channel-phone-call { background: #f3e5f5; color: #7b1fa2; }
.channel-whatsapp { background: #e8f5e8; color: #2e7d32; }
.channel-sms { background: #fff3e0; color: #ef6c00; }
.channel-in-person-meeting { background: #fce4ec; color: #c2185b; }
.channel-video-call { background: #e0f2f1; color: #00695c; }
.channel-linkedin { background: #e3f2fd; color: #1565c0; }
.channel-wechat { background: #e8f5e8; color: #2e7d32; }
.channel-other { background: #f5f5f5; color: #616161; }

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
}

.empty-content h3 {
    color: #6c757d;
    margin-bottom: 10px;
}

.empty-content p {
    color: #6c757d;
    margin-bottom: 20px;
}

.full-width {
    grid-column: 1 / -1;
}

.count {
    color: #6c757d;
    font-weight: normal;
    font-size: 0.9rem;
}

/* Enhanced Status Summary Styles */
.status-filters {
    margin: 15px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.inline-filter-form .filter-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.inline-filter-form label {
    font-size: 14px;
    font-weight: 500;
    color: #495057;
    margin: 0;
}

.inline-filter-form .form-control-sm {
    padding: 4px 8px;
    font-size: 13px;
    border-radius: 4px;
}

.status-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.status-summary-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    border: 1px solid #e9ecef;
    transition: box-shadow 0.2s ease;
}

.status-summary-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.status-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.status-main-count {
    font-size: 24px;
    font-weight: bold;
    color: #495057;
}

.status-details {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.status-detail {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
}

.detail-label {
    color: #6c757d;
}

.detail-value {
    font-weight: 600;
    color: #495057;
}

/* Status badge enhancements */
.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    white-space: nowrap;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .stats-row {
        grid-template-columns: 1fr 1fr;
    }
    
    .stats-primary {
        grid-column: 1 / -1;
    }
}

@media (max-width: 768px) {
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .activities-row {
        grid-template-columns: 1fr;
    }
    
    .dashboard-card {
        padding: 15px;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}

/* Dark mode improvements */
body.dark-mode .followup-content strong,
body.dark-mode .activity-content strong,
body.dark-mode .user-info strong {
    color: #f1f5f9 !important;
}

body.dark-mode .performance-item {
    background: #334155;
    border-left-color: #60a5fa;
}

body.dark-mode .followup-item,
body.dark-mode .activity-item {
    background: #334155;
    border-left-color: #60a5fa;
}
</style>

<script>
function showStatusFilters() {
    const filters = document.getElementById('status-filters');
    if (filters.style.display === 'none') {
        filters.style.display = 'block';
    } else {
        filters.style.display = 'none';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
