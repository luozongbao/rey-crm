<?php 
require_once 'includes/functions.php';

requireLogin();

$action = $_GET['action'] ?? 'add';
$customer_id = (int)($_GET['id'] ?? 0);
$isViewMode = $action === 'view';
$isEditMode = $action === 'edit';

// Validate customer access for view/edit operations
if (($action === 'view' || $action === 'edit') && $customer_id) {
    validateCustomerAccess($customer_id);
}

if ($action == 'delete' && $customer_id) {
    // Validate access before deletion
    validateCustomerAccess($customer_id);
    deleteCustomer($customer_id);
    header("Location: customers.php?restore=1");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($action == 'add' || $action == 'edit')) {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    // Validate customer access for edit
    if ($action == 'edit' && $customer_id) {
        validateCustomerAccess($customer_id);
    }
    
    $data = [
        'company_name' => $_POST['company_name'],
        'address' => $_POST['address'] ?? null,
        'country' => $_POST['country'] ?? null,
        'province' => $_POST['province'] ?? null,
        'company_type' => $_POST['company_type'] ?? null,
        'contact_phone' => $_POST['contact_phone'] ?? null,
        'contact_email' => $_POST['contact_email'] ?? null,
        'website' => $_POST['website'] ?? null,
        'status_key' => $_POST['status'] ?? 'lead', // Use status_key instead of status
        'notes' => $_POST['notes'] ?? null
    ];
    
    // Add assignment field if user can assign and field is present
    if (isset($_POST['assigned_user_id']) && canAssignCustomer($customer_id)) {
        // Convert empty string to NULL for database
        $assigned_user_id = $_POST['assigned_user_id'];
        $data['assigned_user_id'] = ($assigned_user_id === '' || $assigned_user_id === null) ? null : $assigned_user_id;
    }
    
    // Validate email if provided
    if (!empty($data['contact_email'])) {
        $email_validation = validate_cc_emails($data['contact_email']);
        if (!$email_validation['valid']) {
            die("Error: " . $email_validation['message']);
        }
    }
    
    global $pdo;
    
    try {
        if ($action == 'add') {
            // Start transaction
            $pdo->beginTransaction();
            
            // Use the addCustomer function which handles assignment
            if (addCustomer($data)) {
                $customer_id = $pdo->lastInsertId();
                
                // Create main contact
                $mainContact = [
                    'customer_id' => $customer_id,
                    'name' => 'Company Main Contact',
                    'title' => 'Primary Contact',
                    'role' => 'Main Contact',
                    'contact_number' => $_POST['contact_phone'] ?? null,
                    'contact_email' => $_POST['contact_email'] ?? null,
                    'notes' => 'Automatically created as main contact'
                ];
                
                $stmt = $pdo->prepare("INSERT INTO contact_persons 
                                      (customer_id, name, title, role, contact_number, contact_email, notes) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute(array_values($mainContact));
                
                // Commit transaction
                $pdo->commit();
                
                header("Location: customers.php?restore=1");
                exit;
            } else {
                $pdo->rollBack();
                die("Error adding customer");
            }
        } else {
            // Use the updateCustomer function which handles assignment
            if (updateCustomer($customer_id, $data)) {
                header("Location: customers.php?restore=1");
                exit;
            } else {
                die("Error updating customer");
            }
        }
    } catch (PDOException $e) {
        if ($action == 'add' && isset($pdo)) {
            $pdo->rollBack();
        }
        die("Database error: " . $e->getMessage());
    }
}

$customer = ($action == 'edit' || $action == 'view') ? getCustomerById($customer_id) : null;
$contact_persons = $customer_id ? getContactPersons($customer_id) : [];

// Get action history with error handling
$action_history = [];
if ($customer_id) {
    try {
        $action_history = getActionHistory($customer_id);
    } catch (Exception $e) {
        error_log("Error getting action history: " . $e->getMessage());
        $action_history = [];
    }
}

require_once 'includes/header.php';
?>
    <div class="container">
        <div class="header">
            <h1>
                <?php
                if ($action === 'add') echo __('add_customer');
                elseif ($action === 'edit') echo __('edit_customer');
                elseif ($action === 'view') echo __('view_customer');
                ?>
            </h1>
            <?php
            $backUrl = 'customers.php?restore=1';
            if (isset($_SERVER['HTTP_REFERER'])) {
                $referer = $_SERVER['HTTP_REFERER'];
                // Only use referer if it's not from contact_form or history_form
                if (!strpos($referer, 'contact_form.php') && !strpos($referer, 'history_form.php')) {
                    $backUrl = $referer;
                }
            }
            ?>
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn"><?php echo __('back'); ?></a>
        </div>
        
        <form method="post">
            <?php echo csrfTokenField(); ?>
            <!-- Row 1: Company Name and Status -->
            <div class="form-row">
                <div class="form-group company-name">
                    <label for="company_name"><?php echo __('company_name'); ?>:</label>
                    <input type="text" id="company_name" name="company_name" required 
                           value="<?php echo $customer ? htmlspecialchars($customer['company_name']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
                <div class="form-group status">
                    <label for="status"><?php echo __('status'); ?>:</label>
                    <select id="status" name="status" <?php echo $isViewMode ? 'disabled' : ''; ?>>
                        <?php
                        $statusOptions = getCustomerStatusOptions();
                        $currentStatusKey = $customer ? ($customer['status_key'] ?? 'lead') : 'lead';
                        foreach ($statusOptions as $statusKey => $statusName):
                            $selected = ($currentStatusKey == $statusKey) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($statusKey); ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($statusName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (canAssignCustomer($customer_id)): ?>
                <div class="form-group">
                    <label for="assigned_user_id"><?php echo __('assigned_to'); ?>:</label>
                    <div class="assignment-controls">
                        <select id="assigned_user_id" name="assigned_user_id" <?php echo $isViewMode ? 'disabled' : ''; ?>>
                            <option value=""><?php echo __('unassigned'); ?></option>
                            <?php
                            $users = getAllUsers();
                            foreach ($users as $user):
                                $selected = '';
                                if ($customer) {
                                    $selected = ($customer['assigned_user_id'] == $user['user_id']) ? 'selected' : '';
                                } else {
                                    // For new customers, default to current user
                                    $selected = ($_SESSION['user_id'] == $user['user_id']) ? 'selected' : '';
                                }
                            ?>
                                <option value="<?php echo $user['user_id']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php 
                                    // Show current workload
                                    $workload = getUserWorkload($user['user_id']);
                                    echo " ({$workload} customers)";
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if ($customer && $customer['assigned_user_id'] && !$isViewMode): ?>
                            <button type="button" class="btn btn-warning btn-sm" id="unassign-btn" 
                                    title="<?php echo __('unassign_customer'); ?>">
                                <i class="fas fa-user-minus"></i> <?php echo __('unassign'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($customer && $customer['assigned_user_id']): ?>
                        <div class="assignment-info">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                Currently assigned to: <strong><?php echo htmlspecialchars($customer['assigned_username'] ?? 'Unknown'); ?></strong>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
                <?php elseif ($customer): ?>
                <div class="form-group">
                    <label><?php echo __('assigned_to'); ?>:</label>
                    <p class="read-only"><?php echo htmlspecialchars($customer['assigned_to_username'] ?? __('unassigned')); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Row 2: Province and Country -->
            <div class="form-row">
                <div class="form-group half-width">
                    <label for="province"><?php echo __('province'); ?>:</label>
                    <input type="text" id="province" name="province"
                           value="<?php echo $customer ? htmlspecialchars($customer['province']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
                <div class="form-group half-width">
                    <label for="country"><?php echo __('country'); ?>:</label>
                    <input type="text" id="country" name="country"
                           value="<?php echo $customer ? htmlspecialchars($customer['country']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
            </div>

            <!-- Row 3: Company Type and Contact Phone -->
            <div class="form-row">
                <div class="form-group half-width">
                    <label for="company_type"><?php echo __('company_type'); ?>:</label>
                    <input type="text" id="company_type" name="company_type" 
                           value="<?php echo $customer ? htmlspecialchars($customer['company_type']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
                <div class="form-group half-width">
                    <label for="contact_phone"><?php echo __('contact_phone'); ?>:</label>
                    <input type="tel" id="contact_phone" name="contact_phone" 
                           value="<?php echo $customer ? htmlspecialchars($customer['contact_phone']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
            </div>

            <!-- Row 4: Contact Email and Website -->
            <div class="form-row">
                <div class="form-group half-width">
                    <label for="contact_email"><?php echo __('contact_email'); ?>:</label>
                    <input type="text" id="contact_email" name="contact_email" 
                           value="<?php echo $customer ? htmlspecialchars($customer['contact_email']) : ''; ?>"
                           placeholder="<?php echo __('cc_placeholder'); ?>"
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                    <small class="form-text"><?php echo __('cc_help_text'); ?></small>
                </div>
                <div class="form-group half-width">
                    <label for="website"><?php echo __('website'); ?>:</label>
                    <input type="url" id="website" name="website" 
                           value="<?php echo $customer ? htmlspecialchars($customer['website']) : ''; ?>"
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
            </div>

            <!-- Row 5: Address (Full width) -->
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="address"><?php echo __('address'); ?>:</label>
                    <textarea id="address" name="address" rows="3" 
                              <?php echo $isViewMode ? 'disabled' : ''; ?>><?php echo $customer ? htmlspecialchars($customer['address']) : ''; ?></textarea>
                </div>
            </div>

            <!-- Row 6: Notes (Full width) -->
            <div class="form-row">
                <div class="form-group full-width">
                    <label for="notes"><?php echo __('notes'); ?>:</label>
                    <textarea id="notes" name="notes" rows="4" 
                              <?php echo $isViewMode ? 'disabled' : ''; ?>><?php echo $customer ? htmlspecialchars($customer['notes']) : ''; ?></textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <?php if ($action == 'add'): ?>
                <div class="form-actions-row">
                    <div class="form-actions-main">
                        <button type="submit" class="btn"><?php echo __('add_customer'); ?></button>
                        <a href="customers.php?restore=1" class="btn"><?php echo __('cancel'); ?></a>
                    </div>
                </div>
                <?php elseif ($action == 'edit'): ?>
                <div class="form-actions-row">
                    <div class="form-actions-main">
                        <button type="submit" class="btn"><?php echo __('save_customer'); ?></button>
                        <a href="customers.php?restore=1" class="btn"><?php echo __('cancel'); ?></a>
                    </div>
                    <a href="customer_form.php?action=delete&id=<?php echo $customer_id; ?>" 
                       class="btn delete"><?php echo __('delete_customer'); ?></a>
                </div>
                <?php endif; ?>
            </div>
        </form>

        
        <?php if ($customer_id && ($action === 'edit' || $action === 'view')): ?>
        <div class="section">
            <div class="section-header">
                <h2><?php echo __('contact_persons'); ?></h2>
                <a href="contact_form.php?action=add&customer_id=<?php echo $customer_id; ?>&source_action=<?php echo $action; ?>" class="btn"><?php echo __('add_contact_person'); ?></a>
            </div>
            
            <table class="compact-table contact-persons-table">
                <thead>
                    <tr>
                        <th><?php echo __('name'); ?></th>
                        <th><?php echo __('contact_number'); ?></th>
                        <th><?php echo __('contact_email'); ?></th>
                        <th><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contact_persons as $contact): ?>
                    <tr>
                        <td>
                            <?php 
                            echo htmlspecialchars($contact['name']);
                            if (!empty($contact['role'])) {
                                echo '<span class="contact-person-role">(' . htmlspecialchars($contact['role']) . ')</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($contact['contact_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($contact['contact_email'] ?? 'N/A'); ?></td>
                        <td>
                            <a href="contact_form.php?action=edit&id=<?php echo $contact['contact_id']; ?>&source_action=<?php echo $action; ?>" class="btn"><?php echo __('edit'); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Status Timeline Section (View Only) -->
        <?php if ($customer_id && ($action === 'edit' || $action === 'view')): ?>
        <div class="section">
            <div class="section-header">
                <h2><?php echo __('status_timeline'); ?></h2>
            </div>
            
            <div class="status-timeline-wrapper">
                <?php 
                // Set required variables for status timeline
                $current_locale = getCurrentLanguage();
                $messages = [
                    'status_timeline' => __('status_timeline'),
                    'no_status_history' => __('no_status_history'),
                    'status_changed_from_to' => __('status_changed_from_to'),
                    'status_changed_by' => __('changed_by'),
                    'status_changed_from' => __('from'),
                    'status_changed_to' => __('to'),
                    'by_user' => __('by'),
                    'reason' => __('reason')
                ];
                // Include status timeline (view only)
                if ($customer_id) {
                    include 'includes/customer_status_timeline.php'; 
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($customer_id && ($action === 'edit' || $action === 'view')): ?>
        <div class="section">
            <div class="section-header">
                <h2><?php echo __('action_history'); ?></h2>
                <a href="history_form.php?action=add&customer_id=<?php echo $customer_id; ?>&source_action=<?php echo $action; ?>" class="btn"><?php echo __('add_action'); ?></a>
            </div>
            
            <?php if (!empty($action_history)): ?>
            <table class="compact-table">
                <thead>
                    <tr>
                        <th class="action-col"><?php echo __('action'); ?></th>
                        <th class="response-col"><?php echo __('response'); ?></th>
                        <th class="nextstep-col"><?php echo __('next_step'); ?></th>
                        <th class="datetime-col"><?php echo __('date_time'); ?></th>
                        <th class="datetime-col"><?php echo __('follow_up_date'); ?></th>
                        <th class="actions-col"><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($action_history as $history): ?>
                    <tr>
                        <td class="action-col"><?php echo htmlspecialchars($history['action']); ?></td>
                        <td class="response-col"><?php echo htmlspecialchars($history['response']); ?></td>
                        <td class="nextstep-col"><?php echo htmlspecialchars($history['next_step']); ?></td>
                        <td class="datetime-col datetime"><?php echo formatDateTime($history['action_datetime']); ?></td>
                        <td class="datetime-col datetime"><?php echo formatDateTime($history['follow_up_datetime']); ?></td>
                        <td class="actions-col">
                            <a href="history_form.php?action=edit&id=<?php echo $history['history_id']; ?>&customer_id=<?php echo $customer_id; ?>&source_action=<?php echo $action; ?>" class="btn"><?php echo __('edit'); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <p><?php echo __('no_activity'); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

<script>
// Email validation for contact_email field
document.addEventListener('DOMContentLoaded', function() {
    const contactEmailInput = document.getElementById('contact_email');
    
    if (contactEmailInput && !contactEmailInput.disabled) {
        contactEmailInput.addEventListener('blur', function() {
            validateEmailField(this);
        });

        contactEmailInput.addEventListener('input', function() {
            // Clear any existing validation styles when user starts typing
            this.classList.remove('is-invalid', 'is-valid');
            const feedback = document.getElementById('contact-email-feedback');
            if (feedback) {
                feedback.remove();
            }
        });
    }
});

function validateEmailField(input) {
    const emailValue = input.value.trim();
    
    // Remove existing feedback
    const existingFeedback = document.getElementById('contact-email-feedback');
    if (existingFeedback) {
        existingFeedback.remove();
    }
    
    if (emailValue === '') {
        input.classList.remove('is-invalid', 'is-valid');
        return;
    }
    
    // Split by both comma and semicolon
    const emails = emailValue.split(/[,;]/).map(email => email.trim()).filter(email => email !== '');
    const validEmails = [];
    const invalidEmails = [];
    
    emails.forEach(email => {
        if (isValidEmail(email)) {
            validEmails.push(email);
        } else {
            invalidEmails.push(email);
        }
    });
    
    // Create feedback element
    const feedback = document.createElement('div');
    feedback.id = 'contact-email-feedback';
    feedback.className = 'form-feedback';
    
    if (invalidEmails.length > 0) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');
        feedback.className += ' invalid-feedback';
        feedback.textContent = invalidEmails.length > 1
            ? __('invalid_emails') + ': ' + invalidEmails.join(', ')
            : __('invalid_email') + ': ' + invalidEmails.join(', ');
    } else if (validEmails.length > 0) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
        feedback.className += ' valid-feedback';
        feedback.textContent = validEmails.length > 1
            ? validEmails.length + ' ' + __('valid_emails_found')
            : validEmails.length + ' ' + __('valid_email_found');
    }
    
    input.parentNode.appendChild(feedback);
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Unassign functionality
const unassignBtn = document.getElementById('unassign-btn');
if (unassignBtn) {
    unassignBtn.addEventListener('click', function() {
        if (confirm('<?php echo addslashes(__('confirm_unassign_customer')); ?>')) {
            // Immediate AJAX unassign
            const customerId = <?php echo $customer_id; ?>;
            
            // Disable button and show loading
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo addslashes(__('unassigning')); ?>...';
            
            fetch('ajax_handlers/customer_unassign.php', {
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
                    // Update the UI immediately
                    const assignmentSelect = document.getElementById('assigned_user_id');
                    assignmentSelect.value = '';
                    
                    // Hide the unassign button
                    this.style.display = 'none';
                    
                    // Update the assignment info
                    const assignmentInfo = document.querySelector('.assignment-info');
                    if (assignmentInfo) {
                        assignmentInfo.innerHTML = '<small class="text-success"><i class="fas fa-check-circle"></i> Customer successfully unassigned.</small>';
                    }
                    
                    // Show success message
                    alert('<?php echo addslashes(__('success')); ?>: ' + data.message);
                } else {
                    // Re-enable button on error
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-user-minus"></i> <?php echo addslashes(__('unassign')); ?>';
                    alert('<?php echo addslashes(__('error')); ?>: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Re-enable button on error
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-user-minus"></i> <?php echo addslashes(__('unassign')); ?>';
                alert('<?php echo addslashes(__('error')); ?>: An error occurred while unassigning the customer.');
            });
        }
    });
}

// Show/hide unassign button based on selection
const assignmentSelect = document.getElementById('assigned_user_id');
if (assignmentSelect && unassignBtn) {
    assignmentSelect.addEventListener('change', function() {
        if (this.value === '') {
            unassignBtn.style.display = 'none';
        } else if (this.value !== '<?php echo $customer['assigned_user_id'] ?? ''; ?>') {
            unassignBtn.style.display = 'none';
        }
    });
}
</script>

<style>
/* Email validation styles */
.form-control.is-valid {
    border-color: #28a745;
}

.form-control.is-invalid {
    border-color: #dc3545;
}

.valid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #28a745;
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875em;
    color: #dc3545;
}

.form-text {
    font-size: 0.875em;
    color: #6c757d;
    margin-top: 0.25rem;
}

/* Assignment controls styles */
.assignment-controls {
    display: flex;
    gap: 10px;
    align-items: flex-start;
}

.assignment-controls select {
    flex: 1;
}

.assignment-controls .btn {
    white-space: nowrap;
}

.assignment-info {
    margin-top: 5px;
}

.assignment-info small {
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Status timeline wrapper styles */
.status-timeline-wrapper {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-top: 0.5rem;
}

.status-timeline-wrapper h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #495057;
}

@media (max-width: 768px) {
    .assignment-controls {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
