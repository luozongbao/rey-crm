<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure user is logged in and is admin
requireLogin();
requireAdmin();

$page_title = __('customer_user_management');
$current_page = 'admin_customer_management';

// Get current tab
$active_tab = $_GET['tab'] ?? 'dashboard';
$valid_tabs = ['dashboard', 'bulk_assignment', 'user_overview', 'performance', 'reports'];
if (!in_array($active_tab, $valid_tabs)) {
    $active_tab = 'dashboard';
}

// Get dashboard metrics
$dashboard_metrics = getDashboardMetrics();

include 'includes/header.php';
?>

<div class="container">
    <div class="header">
        <h1 class="page-title"><?php echo __('customer_user_management'); ?></h1>
        <div class="header-actions">
            <a href="customer_dashboard.php" class="btn btn-secondary"><?php echo __('back_to_dashboard'); ?></a>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-navigation">
        <nav class="nav nav-tabs">
            <a class="nav-link <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>" 
               href="?tab=dashboard"><?php echo __('dashboard'); ?></a>
            <a class="nav-link <?php echo $active_tab === 'bulk_assignment' ? 'active' : ''; ?>" 
               href="?tab=bulk_assignment"><?php echo __('bulk_assignment'); ?></a>
            <a class="nav-link <?php echo $active_tab === 'user_overview' ? 'active' : ''; ?>" 
               href="?tab=user_overview"><?php echo __('user_overview'); ?></a>
            <a class="nav-link <?php echo $active_tab === 'performance' ? 'active' : ''; ?>" 
               href="?tab=performance"><?php echo __('performance'); ?></a>
            <a class="nav-link <?php echo $active_tab === 'reports' ? 'active' : ''; ?>" 
               href="?tab=reports"><?php echo __('reports'); ?></a>
        </nav>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        <?php if ($active_tab === 'dashboard'): ?>
            <?php include 'includes/admin_dashboard_tab.php'; ?>
        <?php elseif ($active_tab === 'bulk_assignment'): ?>
            <?php include 'includes/admin_bulk_assignment_tab.php'; ?>
        <?php elseif ($active_tab === 'user_overview'): ?>
            <?php include 'includes/admin_user_overview_tab.php'; ?>
        <?php elseif ($active_tab === 'performance'): ?>
            <?php include 'includes/admin_performance_tab.php'; ?>
        <?php elseif ($active_tab === 'reports'): ?>
            <?php include 'includes/admin_reports_tab.php'; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.tab-navigation {
    margin-bottom: 30px;
    border-bottom: 1px solid #dee2e6;
}

.nav-tabs {
    border-bottom: none;
    display: flex;
    gap: 0;
}

.nav-link {
    display: block;
    padding: 12px 20px;
    text-decoration: none;
    color: #495057;
    border: 1px solid transparent;
    border-bottom: none;
    background: #f8f9fa;
    transition: all 0.2s;
}

.nav-link:hover {
    color: #007bff;
    background: #e9ecef;
}

.nav-link.active {
    color: #007bff;
    background: white;
    border-color: #dee2e6;
    border-bottom-color: white;
    position: relative;
    z-index: 1;
}

.tab-content {
    min-height: 500px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0 6px 6px 6px;
    padding: 30px;
}

.metric-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.metric-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.metric-card h3 {
    margin: 0 0 10px 0;
    font-size: 2rem;
    color: #007bff;
}

.metric-card p {
    margin: 0;
    color: #6c757d;
    font-weight: 500;
}

.metric-card.warning h3 {
    color: #ffc107;
}

.metric-card.danger h3 {
    color: #dc3545;
}

.metric-card.success h3 {
    color: #28a745;
}

@media (max-width: 768px) {
    .header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .nav-tabs {
        flex-wrap: wrap;
    }
    
    .nav-link {
        padding: 8px 12px;
        font-size: 0.9rem;
    }
    
    .tab-content {
        padding: 20px;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
