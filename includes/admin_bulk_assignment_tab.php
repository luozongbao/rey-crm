<?php
// Bulk Assignment Tab Content for Admin Customer Management

// Handle bulk assignment form submission
$bulk_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_assign'])) {
    $customer_ids = $_POST['customer_ids'] ?? [];
    $assign_to_user = $_POST['assign_to_user'] ?? '';
    $assignment_reason = $_POST['assignment_reason'] ?? '';
    
    if (!empty($customer_ids) && !empty($assign_to_user)) {
        $bulk_result = bulkAssignCustomers($customer_ids, $assign_to_user, $assignment_reason);
    } else {
        $bulk_result = ['success' => false, 'message' => 'Please select customers and a user to assign to.'];
    }
}

// Get filter parameters
$filter_status = $_GET['filter_status'] ?? '';
$filter_location = $_GET['filter_location'] ?? '';
$filter_assigned = $_GET['filter'] ?? 'all'; // 'all', 'assigned', 'unassigned'

// Build filter conditions
$conditions = [];
$params = [];

if ($filter_assigned === 'unassigned') {
    $conditions[] = "c.assigned_user_id IS NULL";
} elseif ($filter_assigned === 'assigned') {
    $conditions[] = "c.assigned_user_id IS NOT NULL";
}

if (!empty($filter_status)) {
    $conditions[] = "c.status = :status";
    $params[':status'] = $filter_status;
}

if (!empty($filter_location)) {
    if ($filter_location === 'N/A') {
        $conditions[] = "(c.province IS NULL OR c.province = '') AND (c.country IS NULL OR c.country = '')";
    } else {
        $conditions[] = "CONCAT_WS(', ', NULLIF(TRIM(c.province), ''), NULLIF(TRIM(c.country), '')) = :location";
        $params[':location'] = $filter_location;
    }
}

// Get filtered customers
$sql = "SELECT c.*, u.username as assigned_to_username 
        FROM customers c 
        LEFT JOIN users u ON c.assigned_user_id = u.user_id";

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY c.created_at DESC LIMIT 500"; // Limit for performance

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for assignment dropdown
$users = getAllUsers();

// Get available statuses and locations for filters
$customer_statuses = getCustomerStatusOptions();
$locations = getAllLocations(); // Use existing function signature
?>

<div class="bulk-assignment-content">
    <!-- Success/Error Messages -->
    <?php if ($bulk_result): ?>
        <div class="alert alert-<?php echo $bulk_result['success'] ? 'success' : 'danger'; ?>">
            <?php echo htmlspecialchars($bulk_result['message']); ?>
        </div>
    <?php endif; ?>

    <!-- Filters Section -->
    <div class="filters-section">
        <h4>Filter Customers</h4>
        <form method="GET" class="filter-form">
            <input type="hidden" name="tab" value="bulk_assignment">
            
            <div class="filter-row">
                <div class="filter-group">
                    <label for="filter">Assignment Status:</label>
                    <select name="filter" id="filter" class="form-control">
                        <option value="all" <?php echo $filter_assigned === 'all' ? 'selected' : ''; ?>>All Customers</option>
                        <option value="unassigned" <?php echo $filter_assigned === 'unassigned' ? 'selected' : ''; ?>>Unassigned Only</option>
                        <option value="assigned" <?php echo $filter_assigned === 'assigned' ? 'selected' : ''; ?>>Assigned Only</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_status">Customer Status:</label>
                    <select name="filter_status" id="filter_status" class="form-control">
                        <option value="">All Statuses</option>
                        <?php foreach ($customer_statuses as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $filter_status === $status ? 'selected' : ''; ?>>
                                <?php echo $status; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="filter_location">Location:</label>
                    <select name="filter_location" id="filter_location" class="form-control">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo $location; ?>" <?php echo $filter_location === $location ? 'selected' : ''; ?>>
                                <?php echo $location; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="?tab=bulk_assignment" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Bulk Assignment Form -->
    <form method="POST" id="bulk-assignment-form">
        <div class="bulk-actions">
            <h4>Bulk Assignment Actions</h4>
            <div class="action-row">
                <div class="action-group">
                    <label for="assign_to_user">Assign Selected To:</label>
                    <select name="assign_to_user" id="assign_to_user" class="form-control" required>
                        <option value="">-- Select User --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="action-group">
                    <label for="assignment_reason">Reason (Optional):</label>
                    <input type="text" name="assignment_reason" id="assignment_reason" 
                           class="form-control" placeholder="e.g., Load balancing, Geographic assignment">
                </div>
                
                <div class="action-group">
                    <button type="submit" name="bulk_assign" class="btn btn-primary" id="bulk-assign-btn" disabled>
                        Assign Selected (<span id="selected-count">0</span>)
                    </button>
                </div>
            </div>
        </div>

        <!-- Customer Selection Table -->
        <div class="customer-table-section">
            <div class="table-header">
                <h4>Customers (<?php echo count($customers); ?> found)</h4>
                <div class="selection-controls">
                    <button type="button" id="select-all" class="btn btn-sm btn-outline-primary">Select All</button>
                    <button type="button" id="select-none" class="btn btn-sm btn-outline-secondary">Select None</button>
                    <button type="button" id="select-unassigned" class="btn btn-sm btn-outline-warning">Select Unassigned</button>
                </div>
            </div>
            
            <?php if (!empty($customers)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="select-all-checkbox" class="form-check-input">
                                </th>
                                <th>Company Name</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Currently Assigned To</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr class="customer-row <?php echo !$customer['assigned_user_id'] ? 'unassigned-row' : ''; ?>">
                                    <td>
                                        <input type="checkbox" name="customer_ids[]" 
                                               value="<?php echo $customer['customer_id']; ?>" 
                                               class="form-check-input customer-checkbox">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($customer['company_name']); ?></strong>
                                        <?php if ($customer['contact_email']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($customer['contact_email']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $location_parts = array_filter([$customer['province'], $customer['country']]);
                                        echo !empty($location_parts) ? htmlspecialchars(implode(', ', $location_parts)) : 'N/A';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($customer['status']); ?>">
                                            <?php echo $customer['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($customer['assigned_to_username']): ?>
                                            <span class="user-badge"><?php echo htmlspecialchars($customer['assigned_to_username']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo formatDateTimeCompact($customer['created_at']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-customers">
                    <p>No customers found matching the selected filters.</p>
                    <a href="?tab=bulk_assignment" class="btn btn-primary">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const customerCheckboxes = document.querySelectorAll('.customer-checkbox');
    const selectedCountSpan = document.getElementById('selected-count');
    const bulkAssignBtn = document.getElementById('bulk-assign-btn');
    const selectAllBtn = document.getElementById('select-all');
    const selectNoneBtn = document.getElementById('select-none');
    const selectUnassignedBtn = document.getElementById('select-unassigned');

    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.customer-checkbox:checked').length;
        selectedCountSpan.textContent = selectedCount;
        bulkAssignBtn.disabled = selectedCount === 0;
        
        // Update select all checkbox state
        const totalCheckboxes = customerCheckboxes.length;
        if (selectedCount === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (selectedCount === totalCheckboxes) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
    }

    // Individual checkbox change
    customerCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    // Select all checkbox
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        customerCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
        });
        updateSelectedCount();
    });

    // Select all button
    selectAllBtn.addEventListener('click', function() {
        customerCheckboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSelectedCount();
    });

    // Select none button
    selectNoneBtn.addEventListener('click', function() {
        customerCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelectedCount();
    });

    // Select unassigned button
    selectUnassignedBtn.addEventListener('click', function() {
        customerCheckboxes.forEach(checkbox => {
            const row = checkbox.closest('tr');
            checkbox.checked = row.classList.contains('unassigned-row');
        });
        updateSelectedCount();
    });

    // Form submission confirmation
    document.getElementById('bulk-assignment-form').addEventListener('submit', function(e) {
        const selectedCount = document.querySelectorAll('.customer-checkbox:checked').length;
        const assignToUser = document.getElementById('assign_to_user').value;
        
        if (selectedCount > 0 && assignToUser) {
            const userName = document.getElementById('assign_to_user').selectedOptions[0].text;
            if (!confirm(`Are you sure you want to assign ${selectedCount} customer(s) to ${userName}?`)) {
                e.preventDefault();
            }
        }
    });

    // Initial count update
    updateSelectedCount();
});
</script>

<style>
.bulk-assignment-content {
    max-width: 1400px;
}

.filters-section {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.filters-section h4 {
    margin: 0 0 15px 0;
    color: #495057;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #495057;
}

.bulk-actions {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.bulk-actions h4 {
    margin: 0 0 15px 0;
    color: #495057;
}

.action-row {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
    gap: 15px;
    align-items: end;
}

.action-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #495057;
}

.customer-table-section {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.table-header h4 {
    margin: 0;
    color: #495057;
}

.selection-controls {
    display: flex;
    gap: 10px;
}

.table-responsive {
    max-height: 600px;
    overflow-y: auto;
}

.table {
    margin-bottom: 0;
}

.table th {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    position: sticky;
    top: 0;
    z-index: 10;
}

.unassigned-row {
    background-color: #fff3cd !important;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.status-prospect {
    background: #d1ecf1;
    color: #0c5460;
}

.user-badge {
    background: #e3f2fd;
    color: #1976d2;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.no-customers {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

#bulk-assign-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Responsive Design */
@media (max-width: 768px) {
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .action-row {
        grid-template-columns: 1fr;
    }
    
    .table-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .selection-controls {
        justify-content: center;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
}
</style>
