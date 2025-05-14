<?php
require_once 'includes/functions.php';

// Get filter parameters
$customer_id = $_GET['customer_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'follow_up_datetime';
$order = $_GET['order'] ?? 'asc';

// Validate parameters
$validSorts = ['company_name', 'follow_up_datetime', 'action_datetime'];
$validOrders = ['asc', 'desc'];

$sort = in_array($sort, $validSorts) ? $sort : 'follow_up_datetime';
$order = in_array($order, $validOrders) ? $order : 'asc';

// Get all customers for filter dropdown
$customers = getAllCustomers();

// Get filtered follow-ups
$followups = getFilteredFollowups($customer_id, $date_from, $date_to, $sort, $order);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Follow-ups</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>All Follow-ups</h1>
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
                <a href="all_followups.php" class="btn">Reset</a>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Sort By:</label>
                    <select name="sort" onchange="this.form.submit()">
                        <option value="follow_up_datetime" <?= $sort == 'follow_up_datetime' ? 'selected' : '' ?>>Follow-up Date</option>
                        <option value="company_name" <?= $sort == 'company_name' ? 'selected' : '' ?>>Customer Name</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Order:</label>
                    <select name="order" onchange="this.form.submit()">
                        <option value="asc" <?= $order == 'asc' ? 'selected' : '' ?>>Ascending</option>
                        <option value="desc" <?= $order == 'desc' ? 'selected' : '' ?>>Descending</option>
                    </select>
                </div>
            </div>
        </form>

        <!-- Follow-ups Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Action</th>
                    <th>Follow-up Date</th>
                    <th>Next Step</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($followups as $followup): ?>
                <tr>
                    <td><?= htmlspecialchars($followup['company_name']) ?></td>
                    <td><?= htmlspecialchars($followup['action']) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($followup['follow_up_datetime'])) ?></td>
                    <td><?= htmlspecialchars($followup['next_step']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>