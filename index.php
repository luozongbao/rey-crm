<?php 
require_once 'includes/functions.php';
session_start();

// Get status counts for dashboard summary
$statusCounts = getCustomerStatusCounts();

require_once 'includes/header.php';
?>

<div class="index-container">
    <div class="quick-stats">
        <h2>Customer Overview</h2>
        <div class="stats-grid">
            <?php foreach ($statusCounts as $status => $count): ?>
            <div class="stat-box">
                <div class="stat-title"><?php echo htmlspecialchars($status); ?></div>
                <div class="stat-value"><?php echo $count; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="action-buttons">
            <a href="customer_form.php?action=add" class="btn btn-large">Add New Customer</a>
            <a href="customers.php" class="btn btn-large">View Customer List</a>
            <a href="dashboard.php" class="btn btn-large">View Statistics</a>
        </div>
    </div>
</div>

<style>
    
    .quick-stats {
        background: #fff;
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .stat-box {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 6px;
        text-align: center;
    }

    .stat-title {
        font-size: 1.1rem;
        color: #666;
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: bold;
        color: #333;
    }

    .quick-actions {
        background: #fff;
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .action-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .btn.large {
        padding: 1rem 2rem;
        font-size: 1.1rem;
        text-align: center;
        display: block;
    }
</style>

<?php require_once 'includes/footer.php'; ?>
