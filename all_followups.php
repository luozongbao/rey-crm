<?php

require_once 'includes/functions.php';

requireLogin();

// Get filter parameters
$customer_id = $_GET['customer_id'] ?? '';
$customer_status = $_GET['customer_status'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime("-1 month"));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Handle show_only_mine checkbox logic properly
if (count($_GET) > 0) {
    // This is a form submission - respect the checkbox state
    $showOnlyMine = isset($_GET['show_only_mine']) && $_GET['show_only_mine'] == '1';
} else {
    // This is a fresh page load - default to checked
    $showOnlyMine = true;
}

// Validate dates - ensure date_from is not after date_to
if (strtotime($date_from) > strtotime($date_to)) {
    // If dates are invalid, reset to default values
    $date_from = date('Y-m-d');
    $date_to = date('Y-m-d', strtotime('+1 month'));
}

$sort = $_GET['sort'] ?? 'follow_up_datetime';
$order = $_GET['order'] ?? 'asc';

// Validate parameters
$validSorts = ['company_name', 'follow_up_datetime', 'action_datetime', 'customer_status'];
$validOrders = ['asc', 'desc'];

$sort = in_array($sort, $validSorts) ? $sort : 'follow_up_datetime';
$order = in_array($order, $validOrders) ? $order : 'asc';

// Get customers for filter dropdown (respecting the filter)
$customers = getMyCustomers($showOnlyMine);

// Get customer status options for filter dropdown
$customerStatusOptions = getCustomerStatusOptions();

// Get filtered follow-ups
$followups = getFilteredFollowups($customer_id, $date_from, $date_to, $sort, $order, $customer_status, $showOnlyMine);

require_once 'includes/header.php';
?>
    <div class="container">
        <div class="header">
            <h1><?= __('all_followups') ?></h1>
        </div>

        <!-- Filter Form -->
        <form method="get" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="customer_id"><?= __('customer') ?>:</label>
                    <select name="customer_id" id="customer_id">
                        <option value=""><?= __('all_customers') ?></option>
                        <?php foreach ($customers as $customer): ?>
                        <option value="<?= $customer['customer_id'] ?>" 
                            <?= $customer_id == $customer['customer_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($customer['company_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="customer_status"><?= __('customer_status') ?>:</label>
                    <select name="customer_status" id="customer_status">
                        <option value=""><?= __('all_status') ?></option>
                        <option value="All Except Not Qualified" <?= $customer_status == 'All Except Not Qualified' ? 'selected' : '' ?>><?= __('all_except_not_qualified') ?></option>
                        <?php foreach ($customerStatusOptions as $statusOption): ?>
                        <option value="<?= htmlspecialchars($statusOption) ?>" 
                            <?= $customer_status == $statusOption ? 'selected' : '' ?>>
                            <?= htmlspecialchars(__($statusOption)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_from"><?= __('date_from') ?>:</label>
                    <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($date_from) ?>" onchange="validateDates()">
                </div>

                <div class="form-group">
                    <label for="date_to"><?= __('date_to') ?>:</label>
                    <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($date_to) ?>" onchange="validateDates()">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn"><?= __('apply_filters') ?></button>
                    <button type="button" class="btn ghost" onclick="resetFilters()"><?= __('reset') ?></button>
                </div>
            </div>

            <!-- Sort Options -->
            <div class="form-row">
                <div class="form-group">
                    <label for="sort_by"><?= __('sort_by') ?>:</label>
                    <select name="sort_by" id="sort_by" onchange="this.form.submit()">
                        <option value="followup_date" <?= $sort_by == 'followup_date' ? 'selected' : '' ?>><?= __('followup_date') ?></option>
                        <option value="company_name" <?= $sort_by == 'company_name' ? 'selected' : '' ?>><?= __('customer_name') ?></option>
                        <option value="customer_status" <?= $sort_by == 'customer_status' ? 'selected' : '' ?>><?= __('customer_status') ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="order"><?= __('order') ?>:</label>
                    <select name="order" id="order" onchange="this.form.submit()">
                        <option value="ASC" <?= $order == 'ASC' ? 'selected' : '' ?>><?= __('ascending') ?></option>
                        <option value="DESC" <?= $order == 'DESC' ? 'selected' : '' ?>><?= __('descending') ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="show_only_mine" value="1" 
                               <?= $showOnlyMine ? 'checked' : '' ?>
                               onchange="this.form.submit()">
                        <?= __('show_only_my_customers') ?>
                    </label>
                </div>                
            </div>
            

        </form>

        <!-- Follow-ups Table -->
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= __('customer') ?></th>
                    <th><?= __('status') ?></th>
                    <th><?= __('action') ?></th>
                    <th class="datetime"><?= __('followup_date') ?></th>
                    <th><?= __('next_step') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($followups as $followup): ?>
                <tr>
                    <td>
                        <a href="customer_form.php?action=view&id=<?= $followup['customer_id'] ?>" class="customer-link">
                            <?= htmlspecialchars($followup['company_name']) ?>
                            <?php if (!empty($followup['province'])): ?>
                                <span class="province">(<?= htmlspecialchars($followup['province']) ?>)</span>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td>
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $followup['customer_status'])) ?>">
                            <?= htmlspecialchars(__($followup['customer_status'])) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($followup['action']) ?></td>
                    <td class="datetime"><?= formatDateTimeCompact($followup['follow_up_datetime']) ?></td>
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
                alert('<?= __('date_range_error') ?>');
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
