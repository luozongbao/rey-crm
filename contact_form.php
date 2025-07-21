<?php 
require_once 'includes/functions.php';
session_start();

$action = $_GET['action'] ?? 'add';
$contact_id = $_GET['id'] ?? 0;
$customer_id = $_GET['customer_id'] ?? 0;
$source_action = $_GET['source_action'] ?? 'edit';

if ($action == 'delete' && $contact_id) {
    // Get contact details before deletion
    $contact = getContactPersonById($contact_id);
    
    // Check if it's a Main Contact
    if ($contact && strtolower($contact['role']) === 'main contact') {
        die("Error: Cannot delete Main Contact");
    }
    
    deleteContactPerson($contact_id);
    header("Location: customer_form.php?action=$source_action&id=$customer_id");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$isViewMode) {
    // Validate required fields
    if (empty($_POST['name'])) {
        die("Error: Name is required");
    }

    $data = [
        'customer_id' => $_POST['customer_id'],
        'name' => $_POST['name'],
        'title' => $_POST['title'] ?? null,
        'role' => $_POST['role'] ?? null,
        'contact_number' => $_POST['contact_number'] ?? null,
        'contact_email' => $_POST['contact_email'] ?? null,
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
        die("Database error: " . $e->getMessage());
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
                    <label>Company:</label>
                    <span><?php echo htmlspecialchars($customer['company_name']); ?></span>
                </div>
                <div class="info-item status">
                    <label>Status:</label>
                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $customer['status'])); ?>"><?php echo htmlspecialchars($customer['status']); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item half-width">
                    <label>Province:</label>
                    <span><?php echo htmlspecialchars($customer['province'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-item half-width">
                    <label>Country:</label>
                    <span><?php echo htmlspecialchars($customer['country'] ?: 'N/A'); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item half-width">
                    <label>Company Type:</label>
                    <span><?php echo htmlspecialchars($customer['company_type'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-item half-width">
                    <label>Phone:</label>
                    <span><?php echo htmlspecialchars($customer['contact_phone'] ?: 'N/A'); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item half-width">
                    <label>Email:</label>
                    <span><?php echo htmlspecialchars($customer['contact_email'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-item half-width">
                    <label>Website:</label>
                    <span><?php echo htmlspecialchars($customer['website'] ?: 'N/A'); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item full-width">
                    <label>Address:</label>
                    <span><?php echo htmlspecialchars($customer['address'] ?: 'N/A'); ?></span>
                </div>
            </div>
        </div>


        <div class="header">
            <h1><?php echo ucfirst($action); ?> Contact Person</h1>
            <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn">Back</a>
        </div>

        <div class="form-container">
            <form method="post">
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" required 
                        value="<?php echo $contact ? htmlspecialchars($contact['name']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="title">Title:</label>
                    <input type="text" id="title" name="title" 
                        value="<?php echo $contact ? htmlspecialchars($contact['title']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="role">Role:</label>
                    <input type="text" id="role" name="role" 
                        value="<?php echo $contact ? htmlspecialchars($contact['role']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="contact_number">Contact Number:</label>
                    <input type="tel" id="contact_number" name="contact_number" 
                        value="<?php echo $contact ? htmlspecialchars($contact['contact_number']) : ''; ?>" 
                        <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>

                <div class="form-group">
                    <label for="contact_email">Contact Email:</label>
                    <input type="text" id="contact_email" name="contact_email" 
                        value="<?php echo $contact ? htmlspecialchars($contact['contact_email']) : ''; ?>" 
                        placeholder="Enter multiple emails separated by commas or semicolons"
                        <?php echo $isViewMode ? 'disabled' : ''; ?>>
                    <small class="form-text">Enter multiple email addresses separated by commas (,) or semicolons (;)</small>
                </div>
                <div class="form-actions">                    
                    <?php if ($action == 'add'): ?>
                        <div class="form-actions-row">
                            <div class="form-actions-main">
                                <button type="submit" class="btn">Save</button>
                                <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn">Cancel</a>
                            </div>
                        </div>
                    <?php elseif ($action == 'edit'): ?>
                        <div class="form-actions-row">
                            <div class="form-actions-main">
                                <button type="submit" class="btn">Save</button>
                                <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn">Cancel</a>
                            </div>
                            <?php if (!$isViewMode && (!$contact || strtolower($contact['role']) !== 'main contact')): ?>
                                <a href="contact_form.php?action=delete&id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>&source_action=<?php echo $source_action; ?>" 
                                   class="btn delete">Delete Contact</a>
                            <?php elseif (!$isViewMode): ?>
                                <button type="button" class="btn delete" disabled title="Main Contact cannot be deleted">Delete Contact</button>
                            <?php endif; ?>
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
        feedback.textContent = `Invalid email${invalidEmails.length > 1 ? 's' : ''}: ${invalidEmails.join(', ')}`;
    } else if (validEmails.length > 0) {
        input.classList.add('is-valid');
        input.classList.remove('is-invalid');
        feedback.className += ' valid-feedback';
        feedback.textContent = `${validEmails.length} valid email${validEmails.length > 1 ? 's' : ''} found`;
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
