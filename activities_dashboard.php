<?php
require_once 'includes/functions.php';

requireLogin();

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$user_filter = $_GET['user_filter'] ?? (isAdmin() ? 'all' : 'mine');

// Validate dates
if (strtotime($date_from) > strtotime($date_to)) {
    $date_from = date('Y-m-d', strtotime('-30 days'));
    $date_to = date('Y-m-d');
}

// Admin can view all data, regular users only their own
if (!isAdmin()) {
    $user_filter = 'mine';
}

$showOnlyMine = ($user_filter === 'mine');

// Get dashboard data
$dashboardData = getActivitiesDashboardData($date_from, $date_to, $showOnlyMine);

include 'includes/header.php';
?>

<div class="container-fluid mt-4 activities-dashboard">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?php echo __('activities_dashboard'); ?></h2>
            </div>
            
            <!-- Filter Form -->
            <div class="filter-form">
                <form method="GET">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_from"><?php echo __('from'); ?>:</label>
                            <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_to"><?php echo __('to'); ?>:</label>
                            <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        
                        <?php if (isAdmin()): ?>
                        <div class="form-group">
                            <label for="user_filter"><?php echo __('view'); ?>:</label>
                            <select name="user_filter" id="user_filter" class="form-control">
                                <option value="all" <?php echo $user_filter === 'all' ? 'selected' : ''; ?>><?php echo __('all_users'); ?></option>
                                <option value="mine" <?php echo $user_filter === 'mine' ? 'selected' : ''; ?>><?php echo __('my_data_only'); ?></option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary"><?php echo __('filter'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100 border-primary">
                <div class="card-body text-center">
                    <i class="fas fa-tasks fa-2x text-primary mb-2"></i>
                    <h3 class="card-title text-primary"><?php echo number_format($dashboardData['total_activities']); ?></h3>
                    <p class="card-text"><?php echo __('total_activities'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100 border-warning">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                    <h3 class="card-title text-warning"><?php echo number_format($dashboardData['total_followups']); ?></h3>
                    <p class="card-text"><?php echo __('total_followups'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100 border-success">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                    <h3 class="card-title text-success"><?php echo number_format($dashboardData['completed_followups']); ?></h3>
                    <p class="card-text"><?php echo __('completed_followups'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100 border-danger">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                    <h3 class="card-title text-danger"><?php echo number_format($dashboardData['overdue_followups']); ?></h3>
                    <p class="card-text"><?php echo __('overdue_followups'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Activities by Contact Channel -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5><?php echo __('activities_by_contact_channel'); ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($dashboardData['contact_channel_stats'])): ?>
                    <canvas id="contactChannelChart"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-chart-pie fa-3x mb-3"></i>
                        <p><?php echo __('no_data_available'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Activities Timeline -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5><?php echo __('activities_timeline'); ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($dashboardData['timeline_stats'])): ?>
                    <canvas id="timelineChart"></canvas>
                    <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                        <p><?php echo __('no_data_available'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Tables -->
    <div class="row mb-4">
        <?php if (isAdmin() && $user_filter === 'all'): ?>
        <!-- User Performance Table -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5><?php echo __('user_performance'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?php echo __('user'); ?></th>
                                    <th><?php echo __('activities'); ?></th>
                                    <th><?php echo __('followups'); ?></th>
                                    <th><?php echo __('completion_rate'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboardData['user_performance'] as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo number_format($user['activities_count']); ?></td>
                                    <td><?php echo number_format($user['followups_count']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['completion_rate'] >= 80 ? 'success' : ($user['completion_rate'] >= 60 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($user['completion_rate'], 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Customer Status Performance -->
        <div class="col-md-<?php echo (isAdmin() && $user_filter === 'all') ? '6' : '12'; ?> mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5><?php echo __('customer_status_performance'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?php echo __('customer_status'); ?></th>
                                    <th><?php echo __('activities'); ?></th>
                                    <th><?php echo __('followups'); ?></th>
                                    <th><?php echo __('avg_response_time'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboardData['status_performance'] as $status): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($status['customer_status']); ?></span>
                                    </td>
                                    <td><?php echo number_format($status['activities_count']); ?></td>
                                    <td><?php echo number_format($status['followups_count']); ?></td>
                                    <td><?php echo $status['avg_response_days'] ? number_format($status['avg_response_days'], 1) . ' ' . __('days') : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><?php echo __('recent_activities'); ?></h5>
                    <div>
                        <a href="/all_activities.php" class="btn btn-sm btn-outline-primary"><?php echo __('view_all_activities'); ?></a>
                        <a href="/all_followups.php" class="btn btn-sm btn-outline-warning"><?php echo __('view_all_followups'); ?></a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?php echo __('date'); ?></th>
                                    <th><?php echo __('customer'); ?></th>
                                    <th><?php echo __('channel'); ?></th>
                                    <th><?php echo __('action'); ?></th>
                                    <th><?php echo __('next_followup'); ?></th>
                                    <?php if (isAdmin() && $user_filter === 'all'): ?>
                                    <th><?php echo __('user'); ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboardData['recent_activities'] as $activity): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($activity['action_datetime'])); ?></td>
                                    <td>
                                        <a href="/customers.php?customer_id=<?php echo $activity['customer_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($activity['company_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($activity['contact_channel']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($activity['action'], 0, 50)) . (strlen($activity['action']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <?php if ($activity['follow_up_datetime']): ?>
                                            <span class="<?php echo strtotime($activity['follow_up_datetime']) < time() ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo date('M d, Y', strtotime($activity['follow_up_datetime'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if (isAdmin() && $user_filter === 'all'): ?>
                                    <td><?php echo htmlspecialchars($activity['username'] ?? '-'); ?></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Contact Channel Chart
const contactChannelData = <?php echo json_encode($dashboardData['contact_channel_stats']); ?>;
if (contactChannelData && contactChannelData.length > 0) {
    const contactChannelCtx = document.getElementById('contactChannelChart');
    if (contactChannelCtx) {
        new Chart(contactChannelCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: contactChannelData.map(item => item.contact_channel),
                datasets: [{
                    data: contactChannelData.map(item => item.count),
                    backgroundColor: [
                        '#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1',
                        '#fd7e14', '#20c997', '#6c757d', '#e83e8c'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Timeline Chart
const timelineData = <?php echo json_encode($dashboardData['timeline_stats']); ?>;
if (timelineData && timelineData.length > 0) {
    const timelineCtx = document.getElementById('timelineChart');
    if (timelineCtx) {
        new Chart(timelineCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: timelineData.map(item => item.date),
                datasets: [{
                    label: '<?php echo __('activities'); ?>',
                    data: timelineData.map(item => item.activities_count),
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                }, {
                    label: '<?php echo __('followups'); ?>',
                    data: timelineData.map(item => item.followups_count),
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    }
}
</script>

<?php include 'includes/footer.php'; ?>
