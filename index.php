<?php 
require_once 'includes/functions.php';
session_start();

// Get status counts for dashboard summary
$statusCounts = getCustomerStatusCounts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Customer Relationship Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to CRM</h1>
            <div class="header-buttons">
                <a href="customers.php" class="btn">View All Customers</a>
                <a href="all_followups.php" class="btn">View All Follow-ups</a>
                <a href="all_activities.php" class="btn">View All Activities</a>
                <a href="dashboard.php" class="btn dashboard-btn">Dashboard</a>
            </div>
        </div>

        <div class="welcome-section">
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
                    <a href="customer_form.php?action=add" class="btn large">Add New Customer</a>
                    <a href="customers.php" class="btn large">View Customer List</a>
                    <a href="dashboard.php" class="btn large">View Statistics</a>
                </div>
            </div>
        </div>
    </div>

    <style>
        .welcome-section {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
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
</body>
</html>