<?php

require_once 'includes/functions.php';

requireLogin();

// Get filter parameters
$customer_id = $_GET['customer_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d', strtotime('+1 month'));

// Validate dates - ensure date_from is not after date_to
if (strtotime($date_from) > strtotime($date_to)) {
    // If dates are invalid, reset to default values
    $date_from = date('Y-m-d');
    $date_to = date('Y-m-d', strtotime('+1 month'));
}

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

require_once 'includes/header.php';
?>
    <div class="container">
        <div class="header">
            <h1>All Follow-ups</h1>
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
                           value="<?= htmlspecialchars($date_from) ?>" onchange="validateDates()">
                </div>

                <div class="form-group">
                    <label for="date_to">To:</label>
                    <input type="date" name="date_to" id="date_to" 
                           value="<?= htmlspecialchars($date_to) ?>" onchange="validateDates()">
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn">Apply Filters</button>
                    <a href="all_followups.php" class="btn ghost">Reset</a>
                </div>
            </div>

            <!-- <div class="sort-section"> -->
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
            <!-- </div> -->
        </form>

        <!-- Follow-ups Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Action</th>
                    <th class="datetime">Follow-up Date</th>
                    <th>Next Step</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($followups as $followup): ?>
                <tr>
                    <td>
                            <a href="customer_form.php?action=view&id=<?= $followup['customer_id'] ?>" class="customer-link">
                            <?= htmlspecialchars($followup['company_name']) ?>
                        </a>
                    </td>                    
                    <td><?= htmlspecialchars($followup['action']) ?></td>
                    <td class="datetime"><?= $followup['follow_up_datetime'] ?></td>
                    <td><?= htmlspecialchars($followup['next_step']) ?></td>
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
