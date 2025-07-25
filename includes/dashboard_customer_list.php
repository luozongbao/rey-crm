<?php
/**
 * Dashboard Customer List Component
 * Renders customer table with appropriate columns based on role
 */

if (!isset($customers) || !is_array($customers)) {
    $customers = [];
}

$isAdmin = isAdmin();
?>

<div class="dashboard-customers-section">
    <?php if ($isAdmin): ?>
    <!-- Admin View Controls -->
    <div class="dashboard-controls">
        <div class="view-toggle">
            <a href="?view=my" class="btn <?php echo ($viewMode === 'my') ? 'active' : ''; ?>">
                <?php echo __('my_customers'); ?>
            </a>
            <a href="?view=all" class="btn <?php echo ($viewMode === 'all') ? 'active' : ''; ?>">
                <?php echo __('all_customers'); ?>
            </a>
            <a href="?view=unassigned" class="btn <?php echo ($viewMode === 'unassigned') ? 'active' : ''; ?>">
                <?php echo __('unassigned'); ?>
            </a>
        </div>
        
        <?php if ($viewMode === 'all' || $viewMode === 'unassigned'): ?>
        <div class="filter-controls">
            <form method="GET" class="filter-form">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewMode); ?>">
                
                <select name="user_filter" onchange="this.form.submit()">
                    <option value=""><?php echo __('all_users'); ?></option>
                    <?php foreach ($allUsers as $user): ?>
                    <option value="<?php echo $user['user_id']; ?>" 
                            <?php echo (isset($_GET['user_filter']) && $_GET['user_filter'] == $user['user_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="status_filter" onchange="this.form.submit()">
                    <option value=""><?php echo __('all_statuses'); ?></option>
                    <?php foreach (getCustomerStatusOptions() as $status): ?>
                    <option value="<?php echo $status; ?>" 
                            <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == $status) ? 'selected' : ''; ?>>
                        <?php echo __($status); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Customer List -->
    <div class="customer-list-container">
        <?php if (empty($customers)): ?>
        <div class="no-customers">
            <p><?php echo $isAdmin && $viewMode === 'unassigned' ? __('no_unassigned_customers') : __('no_customers_found'); ?></p>
            <?php if (!$isAdmin || $viewMode === 'my'): ?>
            <a href="customer_form.php?action=add" class="btn btn-primary">
                <?php echo __('add_first_customer'); ?>
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <table class="dashboard-customer-table">
            <thead>
                <tr>
                    <th><?php echo __('company_name'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('last_contact'); ?></th>
                    <?php if ($isAdmin && $viewMode !== 'my'): ?>
                    <th><?php echo __('assigned_to'); ?></th>
                    <?php endif; ?>
                    <th><?php echo __('actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                <tr>
                    <td>
                        <div class="customer-info">
                            <strong><?php echo htmlspecialchars($customer['company_name']); ?></strong>
                            <?php if (!empty($customer['contact_email'])): ?>
                            <br><small><?php echo htmlspecialchars($customer['contact_email']); ?></small>
                            <?php endif; ?>
                            <?php if (!empty($customer['location'])): ?>
                            <br><small class="location"><?php echo htmlspecialchars($customer['location']); ?></small>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $customer['status'])); ?>">
                            <?php echo __($customer['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($customer['last_contact']): ?>
                            <span class="last-contact">
                                <?php echo formatDateTime($customer['last_contact']); ?>
                            </span>
                            <?php if ($customer['activity_count'] > 1): ?>
                            <br><small>(<?php echo $customer['activity_count']; ?> <?php echo __('activities'); ?>)</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="no-contact"><?php echo __('never_contacted'); ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if ($isAdmin && $viewMode !== 'my'): ?>
                    <td>
                        <?php if ($customer['assigned_username']): ?>
                            <span class="assigned-user"><?php echo htmlspecialchars($customer['assigned_username']); ?></span>
                        <?php else: ?>
                            <span class="unassigned"><?php echo __('unassigned'); ?></span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <div class="action-buttons">
                            <a href="customer_form.php?action=view&id=<?php echo $customer['customer_id']; ?>" 
                               class="btn btn-sm btn-view" title="<?php echo __('view_customer'); ?>">
                                <?php echo __('view'); ?>
                            </a>
                            <?php if (canEditCustomer($customer['customer_id'])): ?>
                            <a href="customer_form.php?action=edit&id=<?php echo $customer['customer_id']; ?>" 
                               class="btn btn-sm btn-edit" title="<?php echo __('edit_customer'); ?>">
                                <?php echo __('edit'); ?>
                            </a>
                            <?php endif; ?>
                            <a href="history_form.php?customer_id=<?php echo $customer['customer_id']; ?>" 
                               class="btn btn-sm btn-history" title="<?php echo __('add_history'); ?>">
                                <?php echo __('add_history'); ?>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="customer-list-footer">
            <div class="showing-count">
                <?php echo sprintf(__('showing_x_customers'), count($customers)); ?>
            </div>
            <a href="customers.php" class="btn btn-secondary">
                <?php echo __('view_all_customers'); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.dashboard-customers-section {
    margin: 20px 0;
}

.dashboard-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.view-toggle {
    display: flex;
    gap: 5px;
}

.view-toggle .btn {
    padding: 8px 16px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    color: #666;
    text-decoration: none;
    border-radius: 4px;
    transition: all 0.3s;
}

.view-toggle .btn:hover {
    background: #e9ecef;
}

.view-toggle .btn.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.filter-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.filter-form select {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
}

.customer-list-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.dashboard-customer-table {
    width: 100%;
    border-collapse: collapse;
}

.dashboard-customer-table th {
    background: #f8f9fa;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    color: #495057;
}

.dashboard-customer-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #dee2e6;
    vertical-align: top;
}

.dashboard-customer-table tr:hover {
    background: #f8f9fa;
}

.customer-info strong {
    color: #2c3e50;
    font-size: 14px;
}

.customer-info small {
    color: #6c757d;
    font-size: 12px;
}

.customer-info .location {
    color: #17a2b8;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    white-space: nowrap;
}

.status-prospect { background: #fff3cd; color: #856404; }
.status-qualified { background: #d4edda; color: #155724; }
.status-not-qualified { background: #f8d7da; color: #721c24; }
.status-new-customer { background: #d1ecf1; color: #0c5460; }
.status-active-customer { background: #d4edda; color: #155724; }
.status-inactive-customer { background: #e2e3e5; color: #383d41; }
.status-lost-customer { background: #f8d7da; color: #721c24; }
.status-closed-lost { background: #f8d7da; color: #721c24; }
.status-closed-won { background: #d4edda; color: #155724; }

.last-contact {
    color: #495057;
    font-size: 13px;
}

.no-contact {
    color: #dc3545;
    font-style: italic;
    font-size: 13px;
}

.assigned-user {
    color: #007bff;
    font-weight: 500;
}

.unassigned {
    color: #dc3545;
    font-style: italic;
}

.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 11px;
    text-decoration: none;
    border-radius: 4px;
    border: 1px solid transparent;
    display: inline-block;
    text-align: center;
    transition: all 0.3s;
}

.btn-view {
    background: #17a2b8;
    color: white;
    border-color: #17a2b8;
}

.btn-view:hover {
    background: #138496;
    border-color: #117a8b;
}

.btn-edit {
    background: #ffc107;
    color: #212529;
    border-color: #ffc107;
}

.btn-edit:hover {
    background: #e0a800;
    border-color: #d39e00;
}

.btn-history {
    background: #28a745;
    color: white;
    border-color: #28a745;
}

.btn-history:hover {
    background: #218838;
    border-color: #1e7e34;
}

.customer-list-footer {
    padding: 15px 20px;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #dee2e6;
}

.showing-count {
    color: #6c757d;
    font-size: 14px;
}

.no-customers {
    padding: 40px 20px;
    text-align: center;
    color: #6c757d;
}

.no-customers p {
    margin-bottom: 20px;
    font-size: 16px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .view-toggle {
        justify-content: center;
    }
    
    .filter-controls {
        justify-content: center;
    }
    
    .dashboard-customer-table {
        font-size: 12px;
    }
    
    .dashboard-customer-table th,
    .dashboard-customer-table td {
        padding: 8px 10px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .customer-list-footer {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .customer-list-container {
        background: #2c3e50;
        color: #ecf0f1;
    }
    
    .dashboard-customer-table th {
        background: #34495e;
        color: #ecf0f1;
        border-bottom-color: #4a5568;
    }
    
    .dashboard-customer-table td {
        border-bottom-color: #4a5568;
    }
    
    .dashboard-customer-table tr:hover {
        background: #34495e;
    }
    
    .customer-list-footer {
        background: #34495e;
        border-top-color: #4a5568;
    }
}
</style>
