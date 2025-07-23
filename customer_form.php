<?php 
require_once 'includes/functions.php';
session_start();

$action = $_GET['action'] ?? 'add';
$customer_id = $_GET['id'] ?? 0;
$isViewMode = $action === 'view';
$isEditMode = $action === 'edit';

if ($action == 'delete' && $customer_id) {
    deleteCustomer($customer_id);
    header("Location: customers.php?restore=1");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($action == 'add' || $action == 'edit')) {
    $data = [
        'company_name' => $_POST['company_name'],
        'address' => $_POST['address'] ?? null,
        'country' => $_POST['country'] ?? null,
        'province' => $_POST['province'] ?? null,
        'company_type' => $_POST['company_type'] ?? null,
        'contact_phone' => $_POST['contact_phone'] ?? null,
        'contact_email' => $_POST['contact_email'] ?? null,
        'website' => $_POST['website'] ?? null,
        'status' => $_POST['status'] ?? 'Prospect',
        'notes' => $_POST['notes'] ?? null
    ];
    
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
            
            // Insert customer
            $stmt = $pdo->prepare("INSERT INTO customers 
                                  (company_name, address, country, province, company_type, contact_phone, contact_email, website, status, notes) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array_values($data));
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
            $stmt = $pdo->prepare("UPDATE customers SET 
                                  company_name = ?, address = ?, country = ?, province = ?, 
                                  company_type = ?, contact_phone = ?, contact_email = ?, 
                                  website = ?, status = ?, notes = ? 
                                  WHERE customer_id = ?");
            $data[] = $customer_id;
            $stmt->execute(array_values($data));
            
            header("Location: customers.php?restore=1");
            exit;
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
$action_history = $customer_id ? getActionHistory($customer_id) : [];

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
                        foreach ($statusOptions as $statusOption):
                            $selected = ($customer && $customer['status'] == $statusOption) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $selected; ?>>
                                <?php echo __($statusOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
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

        
        <?php if ($customer_id): ?>
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
        
        <div class="section">
            <div class="section-header">
                <h2><?php echo __('action_history'); ?></h2>
                <a href="history_form.php?action=add&customer_id=<?php echo $customer_id; ?>&source_action=<?php echo $action; ?>" class="btn"><?php echo __('add_action'); ?></a>
            </div>
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
</style>

<?php require_once 'includes/footer.php'; ?>
