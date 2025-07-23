<?php
// User Overview Tab Content for Admin Customer Management

// Get filter parameters
$selected_user = $_GET['user_id'] ?? '';
$view_mode = $_GET['view'] ?? 'summary'; // 'summary', 'customers'

// Get all users with their workload statistics
$all_users_stats = getUserWorkloadStats();

// If a specific user is selected, get detailed info
$user_details = null;
$user_customers = [];

if (!empty($selected_user)) {
    $user_details = getUserWorkloadStats($selected_user);
    
    // Get customers assigned to this user
    $stmt = $pdo->prepare("
        SELECT c.*, 
               MAX(ah.action_datetime) as last_activity,
               COUNT(ah.activity_id) as activity_count,
               (SELECT follow_up_datetime FROM action_history 
                WHERE customer_id = c.customer_id AND follow_up_datetime > NOW() 
                ORDER BY follow_up_datetime ASC LIMIT 1) as next_followup
        FROM customers c
        LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
        WHERE c.assigned_user_id = ?
        GROUP BY c.customer_id
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute([$selected_user]);
    $user_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle reassignment action
$reassignment_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reassign_customer'])) {
    $customer_id = $_POST['customer_id'] ?? '';
    $new_user_id = $_POST['new_user_id'] ?? '';
    $reason = $_POST['reassignment_reason'] ?? '';
    
    if (!empty($customer_id) && !empty($new_user_id)) {
        $reassignment_result = reassignCustomer($customer_id, $new_user_id, $reason);
        
        // Refresh user customers if successful
        if ($reassignment_result['success']) {
            header("Location: ?tab=user_overview&user_id=" . $selected_user . "&view=" . $view_mode);
            exit;
        }
    }
}

/**
 * Reassign a single customer to a different user
 */
function reassignCustomer($customer_id, $new_user_id, $reason = '') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get current assignment info
        $stmt = $pdo->prepare("SELECT c.company_name, c.assigned_user_id, u.username as current_user 
                              FROM customers c 
                              LEFT JOIN users u ON c.assigned_user_id = u.user_id 
                              WHERE c.customer_id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        if (!$customer) {
            throw new Exception("Customer not found");
        }
        
        // Get new user info
        $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
        $stmt->execute([$new_user_id]);
        $new_user = $stmt->fetch();
        
        if (!$new_user) {
            throw new Exception("New user not found");
        }
        
        // Update assignment
        $stmt = $pdo->prepare("UPDATE customers SET assigned_user_id = ?, updated_at = NOW() WHERE customer_id = ?");
        $stmt->execute([$new_user_id, $customer_id]);
        
        // Log the reassignment
        $log_note = "Customer reassigned from " . ($customer['current_user'] ?? 'unassigned') . " to " . $new_user['username'];
        if (!empty($reason)) {
            $log_note .= " (Reason: " . $reason . ")";
        }
        
        $stmt = $pdo->prepare("INSERT INTO activities (customer_id, user_id, activity_type, notes, created_at) VALUES (?, ?, 'reassignment', ?, NOW())");
        $stmt->execute([$customer_id, $_SESSION['user_id'], $log_note]);
        
        $pdo->commit();
        
        return ['success' => true, 'message' => "Customer successfully reassigned to " . $new_user['username']];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => 'Error during reassignment: ' . $e->getMessage()];
    }
}
?>

<div class="user-overview-content">
    <!-- Success/Error Messages -->
    <?php if ($reassignment_result): ?>
        <div class="alert alert-<?php echo $reassignment_result['success'] ? 'success' : 'danger'; ?>">
            <?php echo htmlspecialchars($reassignment_result['message']); ?>
        </div>
    <?php endif; ?>

    <div class="overview-header">
        <h3>User Management Overview</h3>
        <p>Monitor and manage user assignments and workloads</p>
    </div>

    <div class="user-overview-layout">
        <!-- Users List Panel -->
        <div class="users-panel">
            <div class="panel-header">
                <h4>All Users</h4>
                <span class="user-count"><?php echo count($all_users_stats); ?> users</span>
            </div>
            
            <div class="users-list">
                <?php foreach ($all_users_stats as $user): ?>
                    <div class="user-card <?php echo $selected_user == $user['user_id'] ? 'selected' : ''; ?>">
                        <a href="?tab=user_overview&user_id=<?php echo $user['user_id']; ?>&view=summary" class="user-link">
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($user['username']); ?></div>
                                <div class="user-stats">
                                    <span class="stat-item">
                                        <i class="fas fa-users"></i>
                                        <?php echo $user['customer_count']; ?> customers
                                    </span>
                                    <span class="stat-item">
                                        <i class="fas fa-chart-line"></i>
                                        <?php echo $user['active_customers']; ?> active
                                    </span>
                                </div>
                            </div>
                            <div class="workload-indicator">
                                <?php
                                $workload = $user['customer_count'];
                                $workload_class = 'low';
                                if ($workload > 50) $workload_class = 'high';
                                elseif ($workload > 20) $workload_class = 'medium';
                                ?>
                                <div class="workload-bar workload-<?php echo $workload_class; ?>">
                                    <div class="workload-fill" style="width: <?php echo min(100, ($workload / 100) * 100); ?>%"></div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- User Details Panel -->
        <div class="details-panel">
            <?php if ($user_details): ?>
                <!-- User Header -->
                <div class="user-header">
                    <h4><?php echo htmlspecialchars($user_details['username']); ?></h4>
                    <div class="view-toggle">
                        <a href="?tab=user_overview&user_id=<?php echo $selected_user; ?>&view=summary" 
                           class="btn btn-sm <?php echo $view_mode === 'summary' ? 'btn-primary' : 'btn-outline-primary'; ?>">Summary</a>
                        <a href="?tab=user_overview&user_id=<?php echo $selected_user; ?>&view=customers" 
                           class="btn btn-sm <?php echo $view_mode === 'customers' ? 'btn-primary' : 'btn-outline-primary'; ?>">Customers</a>
                    </div>
                </div>

                <?php if ($view_mode === 'summary'): ?>
                    <!-- Summary View -->
                    <div class="user-summary">
                        <div class="summary-cards">
                            <div class="summary-card">
                                <div class="card-icon customers">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="card-content">
                                    <div class="card-value"><?php echo $user_details['customer_count']; ?></div>
                                    <div class="card-label">Total Customers</div>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-icon active">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="card-content">
                                    <div class="card-value"><?php echo $user_details['active_customers']; ?></div>
                                    <div class="card-label">Active Customers</div>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-icon prospects">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="card-content">
                                    <div class="card-value"><?php echo $user_details['prospect_customers']; ?></div>
                                    <div class="card-label">Prospects</div>
                                </div>
                            </div>
                            
                            <div class="summary-card">
                                <div class="card-icon activities">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="card-content">
                                    <div class="card-value"><?php echo $user_details['recent_activities'] ?? 0; ?></div>
                                    <div class="card-label">Recent Activities</div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="quick-actions">
                            <h5>Quick Actions</h5>
                            <div class="action-buttons">
                                <a href="?tab=bulk_assignment&filter=unassigned" class="btn btn-outline-primary">
                                    <i class="fas fa-plus"></i> Assign New Customers
                                </a>
                                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#bulkReassignModal">
                                    <i class="fas fa-exchange-alt"></i> Bulk Reassign
                                </button>
                                <a href="customers.php?filter=assigned&user_id=<?php echo $selected_user; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-list"></i> View All Customers
                                </a>
                            </div>
                        </div>
                    </div>

                <?php else: // customers view ?>
                    <!-- Customers View -->
                    <div class="user-customers">
                        <div class="customers-header">
                            <h5>Assigned Customers (<?php echo count($user_customers); ?>)</h5>
                            <div class="filter-options">
                                <select class="form-select form-select-sm" id="customer-filter">
                                    <option value="">All Customers</option>
                                    <option value="active">Active Only</option>
                                    <option value="prospects">Prospects Only</option>
                                    <option value="overdue">Overdue Follow-ups</option>
                                </select>
                            </div>
                        </div>

                        <?php if (!empty($user_customers)): ?>
                            <div class="customers-table">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Company</th>
                                            <th>Status</th>
                                            <th>Last Activity</th>
                                            <th>Next Follow-up</th>
                                            <th>Activities</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_customers as $customer): ?>
                                            <tr class="customer-row" data-status="<?php echo strtolower($customer['status']); ?>">
                                                <td>
                                                    <strong><?php echo htmlspecialchars($customer['company_name']); ?></strong>
                                                    <?php if ($customer['contact_email']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($customer['contact_email']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($customer['status']); ?>">
                                                        <?php echo $customer['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $customer['last_activity'] ? formatDateTimeCompact($customer['last_activity']) : 'No activity'; ?>
                                                </td>
                                                <td>
                                                    <?php if ($customer['next_followup']): ?>
                                                        <?php
                                                        $followup_date = new DateTime($customer['next_followup']);
                                                        $now = new DateTime();
                                                        $is_overdue = $followup_date < $now;
                                                        ?>
                                                        <span class="followup-date <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                                            <?php echo formatDateTimeCompact($customer['next_followup']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">None scheduled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="activity-count"><?php echo $customer['activity_count']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="customer_form.php?customer_id=<?php echo $customer['customer_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">View</a>
                                                        <button type="button" class="btn btn-sm btn-outline-warning reassign-btn" 
                                                                data-customer-id="<?php echo $customer['customer_id']; ?>"
                                                                data-customer-name="<?php echo htmlspecialchars($customer['company_name']); ?>">
                                                            Reassign
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-customers">
                                <p>This user has no assigned customers.</p>
                                <a href="?tab=bulk_assignment" class="btn btn-primary">Assign Customers</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- No User Selected -->
                <div class="no-selection">
                    <div class="no-selection-content">
                        <i class="fas fa-user-circle"></i>
                        <h4>Select a User</h4>
                        <p>Choose a user from the list to view their assignments and manage their workload.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Reassignment Modal -->
<div class="modal fade" id="reassignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Reassign Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="reassign-customer-id">
                    
                    <div class="mb-3">
                        <label class="form-label">Customer:</label>
                        <div id="reassign-customer-name" class="fw-bold"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_user_id" class="form-label">Reassign to:</label>
                        <select name="new_user_id" id="new_user_id" class="form-select" required>
                            <option value="">-- Select User --</option>
                            <?php foreach ($all_users_stats as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?> (<?php echo $user['customer_count']; ?> customers)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reassignment_reason" class="form-label">Reason (Optional):</label>
                        <input type="text" name="reassignment_reason" id="reassignment_reason" 
                               class="form-control" placeholder="e.g., Workload balancing">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reassign_customer" class="btn btn-primary">Reassign Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Customer filter functionality
    const customerFilter = document.getElementById('customer-filter');
    const customerRows = document.querySelectorAll('.customer-row');
    
    if (customerFilter) {
        customerFilter.addEventListener('change', function() {
            const filterValue = this.value.toLowerCase();
            
            customerRows.forEach(row => {
                const status = row.dataset.status;
                const isOverdue = row.querySelector('.followup-date.overdue');
                
                let show = false;
                
                if (filterValue === '') {
                    show = true;
                } else if (filterValue === 'active' && status === 'active') {
                    show = true;
                } else if (filterValue === 'prospects' && status === 'prospect') {
                    show = true;
                } else if (filterValue === 'overdue' && isOverdue) {
                    show = true;
                }
                
                row.style.display = show ? '' : 'none';
            });
        });
    }
    
    // Reassignment modal functionality
    const reassignButtons = document.querySelectorAll('.reassign-btn');
    const reassignModal = new bootstrap.Modal(document.getElementById('reassignModal'));
    
    reassignButtons.forEach(button => {
        button.addEventListener('click', function() {
            const customerId = this.dataset.customerId;
            const customerName = this.dataset.customerName;
            
            document.getElementById('reassign-customer-id').value = customerId;
            document.getElementById('reassign-customer-name').textContent = customerName;
            
            reassignModal.show();
        });
    });
});
</script>

<style>
.user-overview-content {
    max-width: 1400px;
}

.overview-header {
    margin-bottom: 30px;
}

.overview-header h3 {
    margin: 0;
    color: #495057;
}

.overview-header p {
    margin: 5px 0 0 0;
    color: #6c757d;
}

.user-overview-layout {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 30px;
    min-height: 600px;
}

/* Users Panel */
.users-panel {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.panel-header h4 {
    margin: 0;
    color: #495057;
}

.user-count {
    background: #e9ecef;
    color: #495057;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.users-list {
    max-height: 600px;
    overflow-y: auto;
}

.user-card {
    border-bottom: 1px solid #f1f3f4;
    transition: background-color 0.2s;
}

.user-card:hover {
    background: #f8f9fa;
}

.user-card.selected {
    background: #e3f2fd;
    border-left: 4px solid #1976d2;
}

.user-link {
    display: block;
    padding: 15px 20px;
    text-decoration: none;
    color: inherit;
}

.user-info {
    margin-bottom: 10px;
}

.user-name {
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
}

.user-stats {
    display: flex;
    gap: 15px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.8rem;
    color: #6c757d;
}

.stat-item i {
    font-size: 0.7rem;
}

.workload-indicator {
    margin-top: 8px;
}

.workload-bar {
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
}

.workload-fill {
    height: 100%;
    transition: width 0.3s ease;
}

.workload-low .workload-fill { background: #28a745; }
.workload-medium .workload-fill { background: #ffc107; }
.workload-high .workload-fill { background: #dc3545; }

/* Details Panel */
.details-panel {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

.user-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.user-header h4 {
    margin: 0;
    color: #495057;
}

.view-toggle {
    display: flex;
    gap: 5px;
}

/* Summary View */
.user-summary {
    padding: 30px;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #dee2e6;
}

.card-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.card-icon.customers { background: #007bff; }
.card-icon.active { background: #28a745; }
.card-icon.prospects { background: #ffc107; }
.card-icon.activities { background: #6f42c1; }

.card-content {
    flex: 1;
}

.card-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #495057;
    line-height: 1;
}

.card-label {
    font-size: 0.9rem;
    color: #6c757d;
    margin-top: 5px;
}

.quick-actions h5 {
    margin-bottom: 15px;
    color: #495057;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Customers View */
.user-customers {
    padding: 20px;
}

.customers-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.customers-header h5 {
    margin: 0;
    color: #495057;
}

.customers-table {
    overflow-x: auto;
}

.table {
    margin-bottom: 0;
}

.table th {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }
.status-prospect { background: #d1ecf1; color: #0c5460; }

.followup-date.overdue {
    color: #dc3545;
    font-weight: 600;
}

.activity-count {
    background: #e9ecef;
    color: #495057;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

/* No Selection State */
.no-selection {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    min-height: 400px;
}

.no-selection-content {
    text-align: center;
    color: #6c757d;
}

.no-selection-content i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

.no-selection-content h4 {
    margin-bottom: 10px;
}

.no-customers {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .user-overview-layout {
        grid-template-columns: 1fr;
    }
    
    .users-panel {
        max-height: 300px;
    }
    
    .summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-buttons {
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .user-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .customers-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>
