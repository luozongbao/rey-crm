<?php 
require_once 'includes/functions.php';
session_start();

$action = $_GET['action'] ?? 'add';
$contact_id = $_GET['id'] ?? 0;
$customer_id = $_GET['customer_id'] ?? 0;
$source_action = $_GET['source_action'] ?? 'edit';
$isViewMode = $action === 'view';

if ($action == 'delete' && $contact_id) {
    // Get contact details before deletion
    $contact = getContactPersonById($contact_id);
    
    // Check if it's a Main Contact
    if ($contact && strtolower($contact['role']) === 'main contact') {
        die(__('error') . ': ' . __('cannot_delete_main_contact'));
    }
    
    deleteContactPerson($contact_id);
    header("Location: customer_form.php?action=$source_action&id=$customer_id");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$isViewMode) {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    // Validate customer access
    $customer_id = (int)$_POST['customer_id'];
    validateCustomerAccess($customer_id);
    
    // Validate required fields
    if (empty($_POST['name'])) {
        die(__('error') . ': ' . __('name_is_required'));
    }

    $data = [
        'customer_id' => $customer_id,
        'name' => sanitizeHtml(trim($_POST['name'])),
        'title' => !empty($_POST['title']) ? sanitizeHtml(trim($_POST['title'])) : null,
        'role' => !empty($_POST['role']) ? sanitizeHtml(trim($_POST['role'])) : null,
        'contact_number' => !empty($_POST['contact_number']) ? sanitizeHtml(trim($_POST['contact_number'])) : null,
        'contact_email' => !empty($_POST['contact_email']) ? sanitizeHtml(trim($_POST['contact_email'])) : null,
        'notes' => !empty($_POST['notes']) ? sanitizeHtml(trim($_POST['notes'])) : null
    ];
    
    // Validate email if provided
    if (!empty($data['contact_email'])) {
        $email_validation = validate_cc_emails($data['contact_email']);
        if (!$email_validation['valid']) {
            die(__('error') . ': ' . $email_validation['message']);
        }
    }
    
    global $pdo;
    
    try {
        if ($action == 'add') {
            $stmt = $pdo->prepare("INSERT INTO contact_persons 
                                  (customer_id, name, title, role, contact_number, contact_email, notes) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array_values($data));
        } else {
            $stmt = $pdo->prepare("UPDATE contact_persons SET 
                                  customer_id = ?, name = ?, title = ?, 
                                  role = ?, contact_number = ?, contact_email = ?, notes = ? 
                                  WHERE contact_id = ?");
            $data[] = $contact_id;
            $stmt->execute(array_values($data));
        }
        
        header("Location: customer_form.php?action=" . $source_action . "&id=" . $data['customer_id']);
        exit;
    } catch (PDOException $e) {
        die(__('database_error') . ': ' . $e->getMessage());
    }
}



$contact = $action == 'edit' ? getContactPersonById($contact_id) : null;
$customer_id = $contact ? $contact['customer_id'] : $customer_id;

require_once 'includes/header.php';

// Get customer information
$customer = getCustomerById($customer_id);
?>
    <div class="container">

        <div class="customer-info-panel">
            <div class="info-row">
                <div class="info-item company-name">
                    <label><?php echo __('company'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['company_name']); ?></span>
                </div>
                <div class="info-item status">
                    <label><?php echo __('status'); ?>:</label>
                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $customer['status'])); ?>"><?php echo __($customer['status']); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item half-width">
                    <label><?php echo __('province'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['province'] ?: __('not_available')); ?></span>
                </div>
                <div class="info-item half-width">
                    <label><?php echo __('country'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['country'] ?: __('not_available')); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item half-width">
                    <label><?php echo __('company_type'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['company_type'] ?: __('not_available')); ?></span>
                </div>
                <div class="info-item half-width">
                    <label><?php echo __('contact_phone'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['contact_phone'] ?: __('not_available')); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item half-width">
                    <label><?php echo __('contact_email'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['contact_email'] ?: __('not_available')); ?></span>
                </div>
                <div class="info-item half-width">
                    <label><?php echo __('website'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['website'] ?: __('not_available')); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item full-width">
                    <label><?php echo __('address'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['address'] ?: __('not_available')); ?></span>
                </div>
            </div>
        </div>


        <div class="header">
            <h1>
                <?php
                if ($action === 'add') echo __('add_contact_person');
                elseif ($action === 'edit') echo __('edit_contact_person');
                elseif ($action === 'view') echo __('view_contact_person');
                ?>
            </h1>
            <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn"><?php echo __('back'); ?></a>
        </div>

        <div class="form-container">
            <form method="post">
                <?php echo csrfTokenField(); ?>
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                
                <div class="form-group">
                    <label for="name"><?php echo __('name'); ?>:</label>
                    <input type="text" id="name" name="name" required 
                        value="<?php echo $contact ? htmlspecialchars($contact['name']) : ''; ?>"
                        <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label for="title"><?php echo __('title'); ?>:</label>
                    <input type="text" id="title" name="title" 
                        value="<?php echo $contact ? htmlspecialchars($contact['title']) : ''; ?>"
                        <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label for="role"><?php echo __('role'); ?>:</label>
                    <input type="text" id="role" name="role" 
                        value="<?php echo $contact ? htmlspecialchars($contact['role']) : ''; ?>"
                        <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label for="contact_number"><?php echo __('contact_number'); ?>:</label>
                    <input type="tel" id="contact_number" name="contact_number" 
                        value="<?php echo $contact ? htmlspecialchars($contact['contact_number']) : ''; ?>" 
                        <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>

                <div class="form-group">
                    <label for="contact_email"><?php echo __('contact_email'); ?>:</label>
                    <input type="text" id="contact_email" name="contact_email" 
                        value="<?php echo $contact ? htmlspecialchars($contact['contact_email']) : ''; ?>" 
                        placeholder="<?php echo __('cc_placeholder'); ?>"
                        <?php echo $isViewMode ? 'disabled' : ''; ?>>
                    <small class="form-text"><?php echo __('cc_help_text'); ?></small>
                </div>
                
                <div class="form-group">
                    <label for="notes"><?php echo __('notes'); ?>:</label>
                    <textarea id="notes" name="notes" 
                        <?php echo $isViewMode ? 'disabled' : ''; ?>><?php echo $contact ? htmlspecialchars($contact['notes']) : ''; ?></textarea>
                </div>
                <div class="form-actions">                    
                    <?php if ($action == 'add'): ?>
                        <div class="form-actions-row">
                            <div class="form-actions-main">
                                <button type="submit" class="btn"><?php echo __('save'); ?></button>
                                <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn"><?php echo __('cancel'); ?></a>
                            </div>
                        </div>
                    <?php elseif ($action == 'edit'): ?>
                        <div class="form-actions-row">
                            <div class="form-actions-main">
                                <button type="submit" class="btn"><?php echo __('save'); ?></button>
                                <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn"><?php echo __('cancel'); ?></a>
                            </div>
                            <?php if (!$isViewMode && (!$contact || strtolower($contact['role']) !== 'main contact')): ?>
                                <a href="contact_form.php?action=delete&id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>&source_action=<?php echo $source_action; ?>" 
                                   class="btn delete"><?php echo __('delete_contact'); ?></a>
                            <?php elseif (!$isViewMode): ?>
                                <button type="button" class="btn delete" disabled title="<?php echo __('main_contact_cannot_be_deleted'); ?>"><?php echo __('delete_contact'); ?></button>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($action == 'view'): ?>
                        <div class="form-actions-row">
                            <div class="form-actions-main">
                                <a href="contact_form.php?action=edit&id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>&source_action=<?php echo $source_action; ?>" class="btn"><?php echo __('edit'); ?></a>
                                <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn"><?php echo __('back'); ?></a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>  
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
