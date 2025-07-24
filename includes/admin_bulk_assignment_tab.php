<?php
// Bulk Assignment Tab Content for Admin Customer Management

// Handle bulk assignment form submission
$bulk_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $customer_ids = $_POST['customer_ids'] ?? [];
    $action_type = $_POST['action_type'] ?? '';
    $assign_to_user = $_POST['assign_to_user'] ?? '';
    $assignment_reason = $_POST['assignment_reason'] ?? '';
    
    if (!empty($customer_ids)) {
        if ($action_type === 'assign' && !empty($assign_to_user)) {
            $bulk_result = bulkAssignCustomers($customer_ids, $assign_to_user, $assignment_reason);
        } elseif ($action_type === 'unassign') {
            $bulk_result = bulkUnassignCustomers($customer_ids, $assignment_reason);
        } elseif ($action_type === 'auto_distribute') {
            $bulk_result = autoDistributeCustomers($customer_ids, $assignment_reason);
        } else {
            $bulk_result = ['success' => false, 'message' => 'Please select a valid action.'];
        }
    } else {
        $bulk_result = ['success' => false, 'message' => 'Please select at least one customer.'];
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
            <div class="action-tabs">
                <button type="button" class="action-tab active" data-action="assign">
                    <i class="fas fa-user-plus"></i> Assign to User
                </button>
                <button type="button" class="action-tab" data-action="unassign">
                    <i class="fas fa-user-minus"></i> Unassign Users
                </button>
                <button type="button" class="action-tab" data-action="auto_distribute">
                    <i class="fas fa-random"></i> Auto Distribute
                </button>
            </div>
            
            <input type="hidden" name="action_type" id="action_type" value="assign">
            
            <!-- Assign Action Panel -->
            <div class="action-panel" id="assign-panel">
                <div class="action-row">
                    <div class="action-group">
                        <label for="assign_to_user">Assign Selected To:</label>
                        <select name="assign_to_user" id="assign_to_user" class="form-control">
                            <option value="">-- Select User --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php 
                                    // Show current workload
                                    $workload = getUserWorkload($user['user_id']);
                                    echo " ({$workload} customers)";
                                    ?>
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
                        <button type="submit" name="bulk_action" class="btn btn-primary" id="assign-btn" disabled>
                            <i class="fas fa-user-plus"></i>
                            Assign Selected (<span class="selected-count">0</span>)
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Unassign Action Panel -->
            <div class="action-panel" id="unassign-panel" style="display: none;">
                <div class="action-row">
                    <div class="action-group">
                        <label for="unassign_reason">Reason for Unassignment:</label>
                        <input type="text" name="assignment_reason" class="form-control" 
                               placeholder="e.g., User unavailable, Reassignment needed">
                    </div>
                    
                    <div class="action-group">
                        <button type="submit" name="bulk_action" class="btn btn-warning" id="unassign-btn" disabled>
                            <i class="fas fa-user-minus"></i>
                            Unassign Selected (<span class="selected-count">0</span>)
                        </button>
                    </div>
                </div>
                <div class="action-info">
                    <i class="fas fa-info-circle"></i>
                    <small>This will remove user assignments from selected customers. They will become unassigned.</small>
                </div>
            </div>
            
            <!-- Auto Distribute Action Panel -->
            <div class="action-panel" id="auto_distribute-panel" style="display: none;">
                <div class="action-row">
                    <div class="action-group">
                        <label>Distribution Method:</label>
                        <div class="distribution-options">
                            <label class="radio-label">
                                <input type="radio" name="distribution_method" value="round_robin" checked>
                                Round Robin (Equal distribution)
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="distribution_method" value="workload_based">
                                Workload Based (Balance existing assignments)
                            </label>
                        </div>
                    </div>
                    
                    <div class="action-group">
                        <label for="auto_reason">Reason:</label>
                        <input type="text" name="assignment_reason" class="form-control" 
                               placeholder="e.g., Automatic load balancing">
                    </div>
                    
                    <div class="action-group">
                        <button type="submit" name="bulk_action" class="btn btn-success" id="auto-distribute-btn" disabled>
                            <i class="fas fa-random"></i>
                            Auto Distribute (<span class="selected-count">0</span>)
                        </button>
                    </div>
                </div>
                <div class="action-info">
                    <i class="fas fa-info-circle"></i>
                    <small>This will automatically distribute selected customers among available users.</small>
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
                                <th>Workload</th>
                                <th>Created</th>
                                <th>Actions</th>
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
                                        <?php if ($customer['assigned_user_id']): ?>
                                            <?php $userWorkload = getUserWorkload($customer['assigned_user_id']); ?>
                                            <span class="workload-indicator">
                                                <?php echo $userWorkload; ?> customers
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo formatDateTimeCompact($customer['created_at']); ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($customer['assigned_user_id']): ?>
                                                <button type="button" class="btn btn-sm btn-outline-warning quick-unassign" 
                                                        data-customer-id="<?php echo $customer['customer_id']; ?>"
                                                        data-customer-name="<?php echo htmlspecialchars($customer['company_name']); ?>">
                                                    <i class="fas fa-user-minus"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary quick-assign" 
                                                        data-customer-id="<?php echo $customer['customer_id']; ?>"
                                                        data-customer-name="<?php echo htmlspecialchars($customer['company_name']); ?>">
                                                    <i class="fas fa-user-plus"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="/customer_form.php?id=<?php echo $customer['customer_id']; ?>" 
                                               class="btn btn-sm btn-outline-info" target="_blank">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
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
    const selectedCountSpans = document.querySelectorAll('.selected-count');
    const actionButtons = document.querySelectorAll('#assign-btn, #unassign-btn, #auto-distribute-btn');
    const selectAllBtn = document.getElementById('select-all');
    const selectNoneBtn = document.getElementById('select-none');
    const selectUnassignedBtn = document.getElementById('select-unassigned');
    const actionTabs = document.querySelectorAll('.action-tab');
    const actionPanels = document.querySelectorAll('.action-panel');
    const actionTypeInput = document.getElementById('action_type');

    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.customer-checkbox:checked').length;
        selectedCountSpans.forEach(span => span.textContent = selectedCount);
        actionButtons.forEach(btn => btn.disabled = selectedCount === 0);
        
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

    // Action tab switching
    actionTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const action = this.dataset.action;
            
            // Update active tab
            actionTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Show corresponding panel
            actionPanels.forEach(panel => panel.style.display = 'none');
            document.getElementById(action + '-panel').style.display = 'block';
            
            // Update action type
            actionTypeInput.value = action;
        });
    });

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

    // Quick assign/unassign buttons
    document.querySelectorAll('.quick-assign').forEach(btn => {
        btn.addEventListener('click', function() {
            const customerId = this.dataset.customerId;
            const customerName = this.dataset.customerName;
            showQuickAssignModal(customerId, customerName);
        });
    });

    document.querySelectorAll('.quick-unassign').forEach(btn => {
        btn.addEventListener('click', function() {
            const customerId = this.dataset.customerId;
            const customerName = this.dataset.customerName;
            if (confirm(`Are you sure you want to unassign ${customerName}?`)) {
                quickUnassign(customerId);
            }
        });
    });

    // Form submission confirmation
    document.getElementById('bulk-assignment-form').addEventListener('submit', function(e) {
        const selectedCount = document.querySelectorAll('.customer-checkbox:checked').length;
        const actionType = actionTypeInput.value;
        
        if (selectedCount > 0) {
            let confirmMessage = '';
            
            if (actionType === 'assign') {
                const assignToUser = document.getElementById('assign_to_user').value;
                if (!assignToUser) {
                    e.preventDefault();
                    alert('Please select a user to assign to.');
                    return;
                }
                const userName = document.getElementById('assign_to_user').selectedOptions[0].text;
                confirmMessage = `Are you sure you want to assign ${selectedCount} customer(s) to ${userName}?`;
            } else if (actionType === 'unassign') {
                confirmMessage = `Are you sure you want to unassign ${selectedCount} customer(s)? They will become unassigned.`;
            } else if (actionType === 'auto_distribute') {
                confirmMessage = `Are you sure you want to auto-distribute ${selectedCount} customer(s) among available users?`;
            }
            
            if (confirmMessage && !confirm(confirmMessage)) {
                e.preventDefault();
            }
        }
    });

    // Initial count update
    updateSelectedCount();
});

function showQuickAssignModal(customerId, customerName) {
    const users = <?php echo json_encode($users); ?>;
    let optionsHtml = '<option value="">-- Select User --</option>';
    users.forEach(user => {
        optionsHtml += `<option value="${user.user_id}">${user.username}</option>`;
    });
    
    const modalHtml = `
        <div class="modal fade" id="quickAssignModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Quick Assign: ${customerName}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="quick-assign-form">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="quick-assign-user" class="form-label">Assign to User:</label>
                                <select id="quick-assign-user" class="form-control" required>
                                    ${optionsHtml}
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="quick-assign-reason" class="form-label">Reason (Optional):</label>
                                <input type="text" id="quick-assign-reason" class="form-control" 
                                       placeholder="Assignment reason">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Assign Customer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal
    const existingModal = document.getElementById('quickAssignModal');
    if (existingModal) existingModal.remove();
    
    // Add modal to DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('quickAssignModal'));
    modal.show();
    
    // Handle form submission
    document.getElementById('quick-assign-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const userId = document.getElementById('quick-assign-user').value;
        const reason = document.getElementById('quick-assign-reason').value;
        
        if (userId) {
            quickAssign(customerId, userId, reason);
            modal.hide();
        }
    });
}

function quickAssign(customerId, userId, reason = '') {
    fetch('ajax_handlers/quick_assign.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            customer_id: customerId,
            user_id: userId,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload(); // Refresh to show updated assignment
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while assigning the customer.');
    });
}

function quickUnassign(customerId) {
    fetch('ajax_handlers/quick_unassign.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            customer_id: customerId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload(); // Refresh to show updated assignment
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while unassigning the customer.');
    });
}
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

/* Action Tabs */
.action-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    border-bottom: 1px solid #dee2e6;
}

.action-tab {
    background: none;
    border: none;
    padding: 12px 20px;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    font-weight: 500;
    transition: all 0.2s;
}

.action-tab:hover {
    color: #495057;
    background: #f8f9fa;
}

.action-tab.active {
    color: #007bff;
    border-bottom-color: #007bff;
    background: #f8f9fa;
}

.action-tab i {
    margin-right: 8px;
}

/* Action Panels */
.action-panel {
    padding: 15px 0;
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

.action-info {
    margin-top: 10px;
    padding: 10px;
    background: #e9ecef;
    border-radius: 4px;
    font-size: 0.9rem;
    color: #495057;
}

.action-info i {
    color: #17a2b8;
    margin-right: 5px;
}

.distribution-options {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.radio-label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 0;
    cursor: pointer;
}

.radio-label input[type="radio"] {
    margin: 0;
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

.workload-indicator {
    background: #e9ecef;
    color: #495057;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.75rem;
}

.action-buttons {
    display: flex;
    gap: 5px;
    align-items: center;
}

.action-buttons .btn {
    padding: 4px 8px;
    font-size: 0.8rem;
}

.quick-assign {
    color: #007bff;
    border-color: #007bff;
}

.quick-unassign {
    color: #ffc107;
    border-color: #ffc107;
}

.no-customers {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

/* Button states */
button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Modal styling */
.modal-content {
    border-radius: 8px;
}

.modal-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.modal-footer {
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
}

/* Responsive Design */
@media (max-width: 768px) {
    .filter-row {
        grid-template-columns: 1fr;
    }
    
    .action-row {
        grid-template-columns: 1fr;
    }
    
    .action-tabs {
        flex-direction: column;
    }
    
    .table-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .selection-controls {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
