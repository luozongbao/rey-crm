<?php
require_once 'includes/functions.php';

requireLogin();

// Get filter parameters
$customer_id = $_GET['customer_id'] ?? '';
$customer_status = $_GET['customer_status'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime("-1 month"));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Validate dates - ensure date_from is not after date_to
if (strtotime($date_from) > strtotime($date_to)) {
    // If dates are invalid, reset to default values
    $date_from = date('Y-m-d', strtotime("-1 month"));
    $date_to = date('Y-m-d');
}

$sort = $_GET['sort'] ?? 'action_datetime';
$order = $_GET['order'] ?? 'desc';

// Validate parameters
$validSorts = ['company_name', 'action_datetime', 'customer_status'];
$validOrders = ['asc', 'desc'];

$sort = in_array($sort, $validSorts) ? $sort : 'action_datetime';
$order = in_array($order, $validOrders) ? $order : 'desc';

// Get all customers for filter dropdown
$customers = getAllCustomers();

// Get customer status options for filter dropdown
$customerStatusOptions = getCustomerStatusOptions();

// Get filtered activities
$activities = getFilteredActivities($customer_id, $date_from, $date_to, $sort, $order, $customer_status);

require_once 'includes/header.php';
?>
    <div class="container">
        <div class="header">
            <h1>All Activities</h1>
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
                    <label for="customer_status">Customer Status:</label>
                    <select name="customer_status" id="customer_status">
                        <option value="">All Status</option>
                        <option value="All Except Not Qualified" <?= $customer_status == 'All Except Not Qualified' ? 'selected' : '' ?>>All Except Not Qualified</option>
                        <?php foreach ($customerStatusOptions as $statusOption): ?>
                        <option value="<?= htmlspecialchars($statusOption) ?>" 
                            <?= $customer_status == $statusOption ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusOption) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_from">From:</label>
                    <input type="date" name="date_from" id="date_from" 
                           value="<?= htmlspecialchars($date_from) ?>" onchange="validateDates()">
                </div>

                <div class="form-group">
                    <label for="date_to">To:</label>
                    <input type="date" name="date_to" id="date_to" 
                           value="<?= htmlspecialchars($date_to) ?>" onchange="validateDates()">
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn">Apply Filters</button>
                    <a href="all_activities.php" class="btn ghost">Reset</a>
                </div>
            </div>

            <!-- <div class="sort-section"> -->
            <div class="form-row">
                <div class="form-group">
                    <label>Sort By:</label>
                    <select name="sort" onchange="this.form.submit()">
                        <option value="action_datetime" <?= $sort == 'action_datetime' ? 'selected' : '' ?>>Activity Date</option>
                        <option value="company_name" <?= $sort == 'company_name' ? 'selected' : '' ?>>Customer Name</option>
                        <option value="customer_status" <?= $sort == 'customer_status' ? 'selected' : '' ?>>Customer Status</option>
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
            <!-- </div> -->
        </form>

        <!-- Activities Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Action</th>
                    <th class="datetime">Date/Time</th>
                    <th>Response</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $activity): ?>
                <tr>
                    <td><?= htmlspecialchars($activity['company_name']) ?></td>
                    <td><span class="status-badge status-<?= strtolower(str_replace(' ', '-', $activity['customer_status'])) ?>"><?= htmlspecialchars($activity['customer_status']) ?></span></td>
                    <td><?= htmlspecialchars($activity['action']) ?></td>
                    <td class="datetime"><?= $activity['action_datetime'] ?></td>
                    <td><?= htmlspecialchars($activity['response']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <script>
        function validateDates() {
            const dateFromInput = document.getElementById('date_from');
            const dateToInput = document.getElementById('date_to');
            
            const dateFrom = new Date(dateFromInput.value);
            const dateTo = new Date(dateToInput.value);
            
            if (dateFrom > dateTo) {
                alert('Error: "From" date cannot be later than "To" date');
                // Reset to valid values
                if (this === dateFromInput) {
                    dateFromInput.value = dateToInput.value;
                } else {
                    dateToInput.value = dateFromInput.value;
                }
            }
            
            // Set max and min attributes to prevent invalid selections
            dateFromInput.max = dateToInput.value;
            dateToInput.min = dateFromInput.value;
        }
        
        // Initialize date constraints when page loads
        document.addEventListener('DOMContentLoaded', function() {
            validateDates();
        });
    </script>
<?php require_once 'includes/footer.php'; ?>
