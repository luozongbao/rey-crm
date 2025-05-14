<?php
require_once 'includes/functions.php';

// Get filter parameters
$customer_id = $_GET['customer_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'action_datetime';
$order = $_GET['order'] ?? 'desc';

// Validate parameters
$validSorts = ['company_name', 'action_datetime'];
$validOrders = ['asc', 'desc'];

$sort = in_array($sort, $validSorts) ? $sort : 'action_datetime';
$order = in_array($order, $validOrders) ? $order : 'desc';

// Get all customers for filter dropdown
$customers = getAllCustomers();

// Get filtered activities
$activities = getFilteredActivities($customer_id, $date_from, $date_to, $sort, $order);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Activities</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>All Activities</h1>
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </div>

        <!-- Filter Form -->
        <form method="get" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="customer_id">Customer:</label>
                    <select name="customer_id" id="customer_id">
                        <option value="">All Customers</option>
                        <?php foreach ($customers as $customer): ?>
                        <option value="<?= $customer['customer_id'] ?>" 
                            <?= $customer_id == $customer['customer_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($customer['company_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_from">From:</label>
                    <input type="date" name="date_from" id="date_from" 
                           value="<?= htmlspecialchars($date_from) ?>">
                </div>

                <div class="form-group">
                    <label for="date_to">To:</label>
                    <input type="date" name="date_to" id="date_to" 
                           value="<?= htmlspecialchars($date_to) ?>">
                </div>

                <button type="submit" class="btn">Apply Filters</button>
                <a href="all_activities.php" class="btn">Reset</a>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Sort By:</label>
                    <select name="sort" onchange="this.form.submit()">
                        <option value="action_datetime" <?= $sort == 'action_datetime' ? 'selected' : '' ?>>Activity Date</option>
                        <option value="company_name" <?= $sort == 'company_name' ? 'selected' : '' ?>>Customer Name</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Order:</label>
                    <select name="order" onchange="this.form.submit()">
                        <option value="desc" <?= $order == 'desc' ? 'selected' : '' ?>>Newest First</option>
                        <option value="asc" <?= $order == 'asc' ? 'selected' : '' ?>>Oldest First</option>
                    </select>
                </div>
            </div>
        </form>

        <!-- Activities Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Action</th>
                    <th class="datetime">Date/Time</th>
                    <th>Response</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                <tr>
                    <td><?= htmlspecialchars($activity['company_name']) ?></td>
                    <td><?= htmlspecialchars($activity['action']) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($activity['action_datetime'])) ?></td>
                    <td><?= htmlspecialchars($activity['response']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>