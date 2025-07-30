<?php
// Status List Tab Content for Admin Status Management
?>

<div class="status-list-content">
    <!-- Header with Add Button -->
    <div class="section-header">
        <h3><?php echo __('customer_status_management'); ?></h3>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStatusModal">
            <i class="fas fa-plus"></i> <?php echo __('add_new_status'); ?>
        </button>
    </div>

    <!-- Current Status Flow -->
    <div class="status-flow-section">
        <h4><?php echo __('current_status_flow'); ?></h4>
        <div class="status-flow">
            <div class="flow-path">
                <div class="status-node prospect">
                    <span class="status-name"><?php echo __('prospect'); ?></span>
                    <span class="status-chinese">潜在客户</span>
                </div>
                <div class="flow-arrow">→</div>
                <div class="status-node qualified">
                    <span class="status-name"><?php echo __('qualified'); ?></span>
                    <span class="status-chinese">洽谈客户</span>
                </div>
                <div class="flow-arrow">→</div>
                <div class="status-node new-customer">
                    <span class="status-name"><?php echo __('new_customer'); ?></span>
                    <span class="status-chinese">成交客户</span>
                </div>
                <div class="flow-arrow">→</div>
                <div class="status-node active-customer">
                    <span class="status-name"><?php echo __('active_customer'); ?></span>
                    <span class="status-chinese">回头客户</span>
                </div>
            </div>
            <div class="flow-branches">
                <div class="branch-from-prospect">
                    <div class="status-node not-qualified">
                        <span class="status-name"><?php echo __('not_qualified'); ?></span>
                        <span class="status-chinese">无效客户</span>
                    </div>
                </div>
                <div class="branch-from-qualified">
                    <div class="status-node lost-customer">
                        <span class="status-name"><?php echo __('lost_customer'); ?></span>
                        <span class="status-chinese">失去客户</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status List Table -->
    <div class="status-table-section">
        <h4><?php echo __('all_statuses'); ?></h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th><?php echo __('sort_order'); ?></th>
                        <th><?php echo __('status_key'); ?></th>
                        <th><?php echo __('english_name'); ?></th>
                        <th><?php echo __('chinese_name'); ?></th>
                        <th><?php echo __('customer_count'); ?></th>
                        <th><?php echo __('status'); ?></th>
                        <th><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statuses as $status): ?>
                        <tr>
                            <td>
                                <span class="sort-order"><?php echo $status['sort_order']; ?></span>
                            </td>
                            <td>
                                <code><?php echo htmlspecialchars($status['status_key']); ?></code>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($status['en_name'] ?? 'Not Set'); ?></strong>
                                <?php if ($status['en_description']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($status['en_description']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($status['zh_name'] ?? 'Not Set'); ?></strong>
                                <?php if ($status['zh_description']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($status['zh_description']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $count = 0;
                                foreach ($status_stats as $stat) {
                                    if ($stat['status_key'] === $status['status_key']) {
                                        $count = $stat['count'];
                                        break;
                                    }
                                }
                                ?>
                                <span class="customer-count <?php echo $count > 0 ? 'has-customers' : 'no-customers'; ?>">
                                    <?php echo $count; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $status['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                    <?php echo $status['is_active'] ? __('active') : __('inactive'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-sm btn-outline-primary edit-status" 
                                            data-status-id="<?php echo $status['id']; ?>"
                                            data-status-key="<?php echo htmlspecialchars($status['status_key']); ?>"
                                            data-sort-order="<?php echo $status['sort_order']; ?>"
                                            data-is-active="<?php echo $status['is_active']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-info view-translations" 
                                            data-status-id="<?php echo $status['id']; ?>"
                                            data-status-key="<?php echo htmlspecialchars($status['status_key']); ?>">
                                        <i class="fas fa-language"></i>
                                    </button>
                                    <?php if ($count == 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-status" 
                                                data-status-id="<?php echo $status['id']; ?>"
                                                data-status-key="<?php echo htmlspecialchars($status['status_key']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Status Modal -->
<div class="modal fade" id="addStatusModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('add_new_status'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_status">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status_key"><?php echo __('status_key'); ?> *</label>
                                <input type="text" class="form-control" name="status_key" required 
                                       placeholder="e.g., warm_lead" pattern="[a-z_]+"
                                       title="Only lowercase letters and underscores allowed">
                                <small class="form-text text-muted"><?php echo __('status_key_help'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sort_order"><?php echo __('sort_order'); ?></label>
                                <input type="number" class="form-control" name="sort_order" value="10" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" checked>
                            <label class="form-check-label"><?php echo __('status_active'); ?></label>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6><?php echo __('english_translation'); ?></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="english_name"><?php echo __('english_name'); ?> *</label>
                                <input type="text" class="form-control" name="english_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="english_description"><?php echo __('english_description'); ?></label>
                                <input type="text" class="form-control" name="english_description">
                            </div>
                        </div>
                    </div>
                    
                    <h6><?php echo __('chinese_translation'); ?></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="chinese_name"><?php echo __('chinese_name'); ?></label>
                                <input type="text" class="form-control" name="chinese_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="chinese_description"><?php echo __('chinese_description'); ?></label>
                                <input type="text" class="form-control" name="chinese_description">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo __('add_status'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Status Modal -->
<div class="modal fade" id="editStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('edit_status'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="status_id" id="edit_status_id">
                    
                    <div class="form-group">
                        <label><?php echo __('status_key'); ?></label>
                        <input type="text" class="form-control" id="edit_status_key" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_sort_order"><?php echo __('sort_order'); ?></label>
                        <input type="number" class="form-control" name="sort_order" id="edit_sort_order" min="0">
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                            <label class="form-check-label"><?php echo __('status_active'); ?></label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo __('update_status'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.status-flow-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.status-flow {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}

.flow-path {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    justify-content: center;
}

.status-node {
    background: white;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
    min-width: 120px;
    border: 2px solid #dee2e6;
}

.status-node.prospect { border-color: #17a2b8; }
.status-node.qualified { border-color: #28a745; }
.status-node.new-customer { border-color: #007bff; }
.status-node.active-customer { border-color: #20c997; }
.status-node.not-qualified { border-color: #6c757d; }
.status-node.lost-customer { border-color: #dc3545; }

.status-name {
    display: block;
    font-weight: 600;
    color: #495057;
    font-size: 14px;
    text-transform: capitalize;
}

.status-chinese {
    display: block;
    color: #6c757d;
    font-size: 12px;
    margin-top: 5px;
}

.flow-arrow {
    font-size: 20px;
    color: #6c757d;
    font-weight: bold;
}

.flow-branches {
    display: flex;
    gap: 40px;
    margin-top: 10px;
}

.sort-order {
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 600;
}

.customer-count.has-customers {
    color: #28a745;
    font-weight: 600;
}

.customer-count.no-customers {
    color: #6c757d;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 5px;
    display: block;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
}

code {
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 13px;
}

@media (max-width: 768px) {
    .flow-path {
        flex-direction: column;
    }
    
    .flow-arrow {
        transform: rotate(90deg);
    }
    
    .flow-branches {
        flex-direction: column;
        gap: 20px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit status functionality
    document.querySelectorAll('.edit-status').forEach(button => {
        button.addEventListener('click', function() {
            const statusId = this.dataset.statusId;
            const statusKey = this.dataset.statusKey;
            const sortOrder = this.dataset.sortOrder;
            const isActive = this.dataset.isActive === '1';
            
            document.getElementById('edit_status_id').value = statusId;
            document.getElementById('edit_status_key').value = statusKey;
            document.getElementById('edit_sort_order').value = sortOrder;
            document.getElementById('edit_is_active').checked = isActive;
            
            const modal = new bootstrap.Modal(document.getElementById('editStatusModal'));
            modal.show();
        });
    });
    
    // View translations functionality
    document.querySelectorAll('.view-translations').forEach(button => {
        button.addEventListener('click', function() {
            const statusKey = this.dataset.statusKey;
            window.location.href = '?tab=translations&status=' + statusKey;
        });
    });
    
    // Delete status functionality
    document.querySelectorAll('.delete-status').forEach(button => {
        button.addEventListener('click', function() {
            const statusKey = this.dataset.statusKey;
            if (confirm('Are you sure you want to delete the status "' + statusKey + '"? This action cannot be undone.')) {
                // Implementation for delete functionality
                alert('Delete functionality would be implemented here');
            }
        });
    });
});
</script>
