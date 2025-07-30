<?php
/**
 * Customer Status Change Form Component
 * Provides a form to change customer status with validation
 */

// This file should be included where needed, with $customer_id and $messages already available
if (!isset($customer_id) || !isset($messages)) {
    die('Required variables not set');
}

require_once __DIR__ . '/customer_status_functions.php';

// Get valid status transitions for this customer
$valid_transitions = getValidStatusTransitions($customer_id, $current_locale ?? 'en');
$current_customer_status = null;

// Get current customer status
try {
    global $pdo;
    $stmt = $pdo->prepare("SELECT cs.status_key, cst.name as status_name
                          FROM customers c 
                          JOIN customer_statuses cs ON c.status_id = cs.id 
                          LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = ?
                          WHERE c.customer_id = ?");
    $stmt->execute([$current_locale ?? 'en', $customer_id]);
    $current_customer_status = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error getting current customer status: " . $e->getMessage());
}
?>

<div class="status-change-container">
    <h4><?= htmlspecialchars($messages['customer_status']) ?></h4>
    
    <?php if ($current_customer_status): ?>
        <div class="current-status mb-3">
            <span class="text-muted"><?= htmlspecialchars($messages['status']) ?>:</span>
            <span class="status-badge status-<?= htmlspecialchars($current_customer_status['status_key']) ?>">
                <?= htmlspecialchars($current_customer_status['status_name']) ?>
            </span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($valid_transitions)): ?>
        <form id="statusChangeForm" class="status-change-form">
            <input type="hidden" name="customer_id" value="<?= htmlspecialchars($customer_id) ?>">
            <input type="hidden" name="action" value="change_status">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="new_status"><?= htmlspecialchars($messages['status_changed_to']) ?>:</label>
                        <select name="new_status" id="new_status" class="form-control" required>
                            <option value="">-- <?= htmlspecialchars($messages['customer_status']) ?> --</option>
                            <?php foreach ($valid_transitions as $status): ?>
                                <option value="<?= htmlspecialchars($status['status_key']) ?>">
                                    <?= htmlspecialchars($status['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="status_notes"><?= htmlspecialchars($messages['status_change_notes']) ?> (<?= htmlspecialchars($messages['optional'] ?? 'Optional') ?>):</label>
                        <textarea name="notes" id="status_notes" class="form-control" rows="2" 
                                placeholder="<?= htmlspecialchars($messages['notes'] ?? 'Notes') ?>..."></textarea>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i>
                    <?= htmlspecialchars($messages['change_status'] ?? 'Change Status') ?>
                </button>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <?= htmlspecialchars($messages['no_status_transitions'] ?? 'No status changes available') ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusForm = document.getElementById('statusChangeForm');
    if (statusForm) {
        statusForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(statusForm);
            const newStatus = formData.get('new_status');
            
            if (!newStatus) {
                alert('<?= addslashes($messages['select_status'] ?? 'Please select a status') ?>');
                return;
            }
            
            // Confirm the status change
            const statusText = document.querySelector(`#new_status option[value="${newStatus}"]`).textContent;
            const confirmMessage = '<?= addslashes($messages['confirm_status_change'] ?? 'Are you sure you want to change the status to') ?>' + ' ' + statusText + '?';
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            // Show loading state
            const submitBtn = statusForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= addslashes($messages['changing'] ?? 'Changing') ?>...';
            submitBtn.disabled = true;
            
            // Submit via AJAX
            fetch('ajax_handlers/change_customer_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showAlert('success', data.message || '<?= addslashes($messages['status_changed_successfully'] ?? 'Status changed successfully') ?>');
                    
                    // Reload page to show updated status and timeline
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('error', data.message || '<?= addslashes($messages['error_changing_status'] ?? 'Error changing status') ?>');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', '<?= addslashes($messages['error_occurred'] ?? 'An error occurred') ?>');
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

function showAlert(type, message) {
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'}"></i>
        ${message}
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    `;
    
    // Insert at the top of the status change container
    const container = document.querySelector('.status-change-container');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

<style>
.status-change-container {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.current-status {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid #007bff;
}

.status-change-form .form-group {
    margin-bottom: 15px;
}

.form-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

.btn-primary {
    background-color: #007bff;
    border-color: #007bff;
}

.btn-primary:hover {
    background-color: #0056b3;
    border-color: #0056b3;
}

/* Status badge styles (inherited from timeline component) */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 500;
    margin: 0 2px;
}

.status-prospect {
    background-color: #e3f2fd;
    color: #1976d2;
    border: 1px solid #bbdefb;
}

.status-qualified {
    background-color: #fff3e0;
    color: #f57c00;
    border: 1px solid #ffcc02;
}

.status-not_qualified {
    background-color: #ffebee;
    color: #d32f2f;
    border: 1px solid #ffcdd2;
}

.status-new_customer {
    background-color: #e8f5e8;
    color: #2e7d32;
    border: 1px solid #c8e6c8;
}

.status-active_customer {
    background-color: #e0f2f1;
    color: #00695c;
    border: 1px solid #b2dfdb;
}

.status-lost_customer {
    background-color: #f3e5f5;
    color: #7b1fa2;
    border: 1px solid #e1bee7;
}
</style>
