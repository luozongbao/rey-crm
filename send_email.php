<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'vendor/autoload.php';

// Ensure user is logged in
requireLogin();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$page_title = 'Send Email';
$current_page = 'send_email';

// Get filter parameters
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$project = null;

// Get project data
if ($project_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_projects WHERE project_id = ?");
        $stmt->execute([$project_id]);
        $project = $stmt->fetch();
        
        if (!$project) {
            header('Location: email_projects.php?error=' . urlencode('Project not found'));
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error fetching project: " . $e->getMessage());
        $error = "Error fetching project data.";
    }
}

// Handle show_only_mine checkbox logic properly
if (count($_GET) > 0) {
    // This is a form submission - respect the checkbox state
    $showOnlyMine = isset($_GET['show_only_mine']) && $_GET['show_only_mine'] == '1';
} else {
    // This is a fresh page load - default to checked
    $showOnlyMine = true;
}

// Get customers and contacts for recipient selection using the new function
$recipients = getMyCustomersContacts($showOnlyMine, $_SESSION['user_id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $to_email = trim($_POST['to_email'] ?? '');
    $email_subject = trim($_POST['email_subject'] ?? '');
    $email_cc = trim($_POST['email_cc'] ?? '');
    $email_message = $_POST['email_message'] ?? '';
    
    // Handle attachments
    $final_attachments = [];
    
    // Include selected existing attachments
    if (!empty($_POST['keep_attachments'])) {
        foreach ($_POST['keep_attachments'] as $attachment) {
            $final_attachments[] = $attachment;
        }
    }
    
    // Handle new file uploads
    if (!empty($_FILES['additional_attachments']['name'][0])) {
        $upload_dir = 'uploads/email_attachments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        for ($i = 0; $i < count($_FILES['additional_attachments']['name']); $i++) {
            if ($_FILES['additional_attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['additional_attachments']['name'][$i];
                $file_tmp = $_FILES['additional_attachments']['tmp_name'][$i];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Generate unique filename
                $unique_name = uniqid() . '_' . $file_name;
                $upload_path = $upload_dir . $unique_name;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $final_attachments[] = $upload_path;
                    error_log("New attachment uploaded: " . $upload_path);
                } else {
                    error_log("Failed to upload attachment: " . $file_name);
                }
            }
        }
    }
    
    // Validation
    $errors = [];
    if (empty($to_email)) {
        $errors[] = "Please select a recipient.";
    }
    if (empty($email_subject)) {
        $errors[] = "Subject is required.";
    }
    if (empty($email_message)) {
        $errors[] = "Message is required.";
    }
    if (!empty($email_cc)) {
        $cc_validation = validate_cc_emails($email_cc);
        if (!$cc_validation['valid']) {
            $errors[] = "CC: " . $cc_validation['message'];
        }
    }
    if (!$project) {
        $errors[] = "Email project not found.";
    }
    
    if (empty($errors)) {
        try {
            // Get user-specific SMTP settings
            $user_id = $_SESSION['user_id'];
            
            // Prepare attachments array
            $attachments_paths = [];
            if (!empty($final_attachments)) {
                foreach ($final_attachments as $attachment) {
                    // Convert relative path to absolute path
                    $attachment_path = $attachment;
                    if (!is_absolute_path($attachment)) {
                        $attachment_path = __DIR__ . '/' . $attachment;
                    }
                    if (file_exists($attachment_path)) {
                        $attachments_paths[] = $attachment_path;
                    }
                }
            }
            
            // Parse recipient emails
            $to_emails = parse_cc_emails($to_email);
            
            // Parse CC emails
            $cc_emails = [];
            if (!empty($email_cc)) {
                $cc_emails = parse_cc_emails($email_cc);
            }
            
            // Send email using user's personal SMTP settings
            $email_result = sendUserEmail(
                $user_id,
                $to_emails,
                $email_subject,
                $email_message,
                strip_tags($email_message),
                $attachments_paths,
                $cc_emails
            );
            
            if (!$email_result['success']) {
                throw new Exception($email_result['message']);
            }
            
            // Save to email history
            $stmt = $pdo->prepare("
                INSERT INTO sent_email_history 
                (sent_datetime, to_email, cc, project_id, subject, attachments, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                getCurrentUTCDateTime(),  // Use UTC timestamp instead of NOW()
                implode(', ', $to_emails),
                implode(', ', $cc_emails),
                $project_id,
                $email_subject,
                json_encode(array_map('basename', $attachments_paths)),
                $_SESSION['user_id'] ?? null  // Track who sent the email
            ]);
            
            $success_message = 'Email sent successfully!';
            
            // Add recipient info
            if (count($to_emails) > 1) {
                $success_message .= ' (Sent to ' . count($to_emails) . ' recipients)';
            }
            
            // Add attachment info if there were attachments
            if (!empty($attachments_paths)) {
                $success_message .= ' (' . count($attachments_paths) . ' attachment' . 
                                  (count($attachments_paths) > 1 ? 's' : '') . ' included)';
            }
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            $error = "Email sending failed: " . $e->getMessage();
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $error = "Error saving email history.";
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="header">
            <h1 class="page-title">Send Email</h1>
            <a href="email_projects.php" class="btn btn-secondary">
                Back to Projects
            </a>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
            <div style="margin-top: 10px;">
                <a href="email_projects.php" class="btn btn-sm btn-secondary">Back to Projects</a>
                <a href="email_history.php" class="btn btn-sm btn-primary">View Email History</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($project && !isset($success_message)): ?>
        <!-- Filter Section -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-body">
                <form method="GET" class="form">
                    <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="show_only_mine" value="1" 
                                   <?php echo $showOnlyMine ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                            <?php echo __('show_only_my_customers'); ?>
                        </label>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Project: <?php echo htmlspecialchars($project['project_name']); ?></h3>
            </div>
            <div class="card-body">
                <form method="POST" class="form" id="send-email-form">
                    <div class="form-group">
                        <label for="to_email" class="form-label">Send To <span class="required">*</span></label>
                        <select id="to_email" name="to_email" class="form-control" required>
                            <option value="">-- Select Recipient --</option>
                            <?php
                            $grouped_recipients = [];
                            foreach ($recipients as $recipient) {
                                $company = $recipient['company_name'];
                                if (!isset($grouped_recipients[$company])) {
                                    $grouped_recipients[$company] = [];
                                }
                                
                                // Handle customer emails (may contain multiple emails)
                                if ($recipient['customer_email']) {
                                    $customer_emails = parse_cc_emails($recipient['customer_email']);
                                    if (!empty($customer_emails)) {
                                        $grouped_recipients[$company][] = [
                                            'email' => $recipient['customer_email'], // Keep original for backend processing
                                            'display_emails' => $customer_emails, // Parsed for display
                                            'name' => $company . ' (Company)',
                                            'type' => 'customer'
                                        ];
                                    }
                                }
                                
                                // Handle contact emails (may contain multiple emails)
                                if ($recipient['contact_email'] && $recipient['contact_name']) {
                                    $contact_emails = parse_cc_emails($recipient['contact_email']);
                                    if (!empty($contact_emails)) {
                                        $grouped_recipients[$company][] = [
                                            'email' => $recipient['contact_email'], // Keep original for backend processing
                                            'display_emails' => $contact_emails, // Parsed for display
                                            'name' => $recipient['contact_name'] . ' (' . $company . ')',
                                            'type' => 'contact'
                                        ];
                                    }
                                }
                            }
                            
                            foreach ($grouped_recipients as $company => $company_recipients):
                            ?>
                                <optgroup label="<?php echo htmlspecialchars($company); ?>">
                                    <?php foreach ($company_recipients as $recipient): ?>
                                        <option value="<?php echo htmlspecialchars($recipient['email']); ?>"
                                                data-emails="<?php echo htmlspecialchars(implode(', ', $recipient['display_emails'])); ?>"
                                                data-type="<?php echo htmlspecialchars($recipient['type']); ?>"
                                                data-name="<?php echo htmlspecialchars($recipient['name']); ?>">
                                            <?php echo htmlspecialchars($recipient['name']); ?>
                                            <?php if (count($recipient['display_emails']) > 1): ?>
                                                (<?php echo count($recipient['display_emails']); ?> emails)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Recipient Information Display -->
                    <div id="recipient-info" class="recipient-info" style="display: none;">
                        <h4>Recipient Information</h4>
                        <div class="recipient-details">
                            <div class="recipient-field">
                                <label>Selected:</label>
                                <div id="selected-name"></div>
                            </div>
                            <div class="recipient-field">
                                <label>Email(s) to send to:</label>
                                <div id="selected-emails"></div>
                            </div>
                            <div class="recipient-field">
                                <label>Type:</label>
                                <div id="selected-type"></div>
                            </div>
                        </div>
                    </div>

                    <div class="email-preview">
                        <h4>Email Content <small>(You can edit before sending)</small></h4>
                        
                        <div class="preview-field">
                            <label for="email_subject">Subject: <span class="required">*</span></label>
                            <input type="text" id="email_subject" name="email_subject" class="form-control" 
                                   value="<?php echo htmlspecialchars($project['subject']); ?>" required>
                        </div>

                        <div class="preview-field">
                            <label for="email_cc">CC (optional):</label>
                            <input type="text" id="email_cc" name="email_cc" class="form-control" 
                                   value="<?php echo htmlspecialchars($project['cc'] ?? ''); ?>" 
                                   placeholder="Enter multiple emails separated by commas or semicolons">
                            <small class="form-text">Enter multiple email addresses separated by commas (,) or semicolons (;)</small>
                        </div>

                        <div class="preview-field">
                            <label for="email_message">Message: <span class="required">*</span></label>
                            <div class="wysiwyg-container">
                                <div class="wysiwyg-toolbar">
                                    <button type="button" onclick="execCommand('bold')" title="Bold"><b>B</b></button>
                                    <button type="button" onclick="execCommand('italic')" title="Italic"><i>I</i></button>
                                    <button type="button" onclick="execCommand('underline')" title="Underline"><u>U</u></button>
                                    <button type="button" onclick="execCommand('insertOrderedList')" title="Numbered List">1.</button>
                                    <button type="button" onclick="execCommand('insertUnorderedList')" title="Bulleted List">•</button>
                                    <button type="button" onclick="execCommand('createLink')" title="Insert Link">🔗</button>
                                </div>
                                <div id="message-editor" class="wysiwyg-editor" contenteditable="true">
                                    <?php echo $project['message']; ?>
                                </div>
                                <textarea id="email_message" name="email_message" style="display: none;" required>
                                    <?php echo htmlspecialchars($project['message']); ?>
                                </textarea>
                            </div>
                        </div>

                        <?php if (!empty($project['attachments'])): ?>
                            <?php $attachments = json_decode($project['attachments'], true); ?>
                            <?php if (!empty($attachments)): ?>
                                <div class="preview-field">
                                    <label>Current Attachments:</label>
                                    <div class="current-attachments">
                                        <?php foreach ($attachments as $index => $attachment): ?>
                                            <?php
                                            // Convert relative path to absolute path for file checking
                                            $attachment_path = $attachment;
                                            if (!is_absolute_path($attachment)) {
                                                $attachment_path = __DIR__ . '/' . $attachment;
                                            }
                                            ?>
                                            <div class="attachment-item">
                                                <input type="checkbox" id="keep_attachment_<?php echo $index; ?>" 
                                                       name="keep_attachments[]" value="<?php echo htmlspecialchars($attachment); ?>" checked>
                                                <label for="keep_attachment_<?php echo $index; ?>">
                                                    <?php echo htmlspecialchars(basename($attachment)); ?>
                                                    <small>(<?php echo file_exists($attachment_path) ? number_format(filesize($attachment_path)/1024, 1) . ' KB' : 'File not found'; ?>)</small>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="form-text">Uncheck attachments you don't want to include</small>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="preview-field">
                            <label for="additional_attachments">Add More Attachments (optional):</label>
                            <input type="file" id="additional_attachments" name="additional_attachments[]" 
                                   class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif">
                            <small class="form-text">You can select multiple files. Max 50MB per file.</small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="send_email" id="send-button" class="btn btn-primary" disabled>
                            Send Email
                        </button>
                        <a href="email_projects.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toEmailSelect = document.getElementById('to_email');
    const sendButton = document.getElementById('send-button');
    const recipientInfo = document.getElementById('recipient-info');
    const selectedName = document.getElementById('selected-name');
    const selectedEmails = document.getElementById('selected-emails');
    const selectedType = document.getElementById('selected-type');
    const ccInput = document.getElementById('email_cc');

    function updateRecipientInfo() {
        const selectedOption = toEmailSelect.selectedOptions[0];
        
        if (!selectedOption || !selectedOption.value) {
            // No recipient selected
            recipientInfo.style.display = 'none';
            sendButton.disabled = true;
            return;
        }

        // Show recipient information
        recipientInfo.style.display = 'block';
        sendButton.disabled = false;

        // Get data from the selected option
        const name = selectedOption.dataset.name || selectedOption.textContent;
        const emails = selectedOption.dataset.emails || selectedOption.value;
        const type = selectedOption.dataset.type || 'unknown';

        // Update display
        selectedName.textContent = name;
        selectedEmails.innerHTML = emails.split(', ').map(email => 
            `<span class="email-badge">${email.trim()}</span>`
        ).join(' ');
        selectedType.textContent = type === 'customer' ? 'Company Contact' : 'Person Contact';
    }

    // CC email validation
    if (ccInput) {
        ccInput.addEventListener('blur', function() {
            validateCCField(this);
        });

        ccInput.addEventListener('input', function() {
            // Clear any existing validation styles when user starts typing
            this.classList.remove('is-invalid', 'is-valid');
            const feedback = document.getElementById('cc-feedback');
            if (feedback) {
                feedback.remove();
            }
        });
    }

    function validateCCField(input) {
        const ccValue = input.value.trim();
        
        // Remove existing feedback
        const existingFeedback = document.getElementById('cc-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        if (ccValue === '') {
            input.classList.remove('is-invalid', 'is-valid');
            return;
        }
        
        // Split by both comma and semicolon
        const emails = ccValue.split(/[,;]/).map(email => email.trim()).filter(email => email !== '');
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
        feedback.id = 'cc-feedback';
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

    toEmailSelect.addEventListener('change', updateRecipientInfo);
    
    // Initial check
    updateRecipientInfo();
});

// WYSIWYG Editor functionality
function execCommand(command) {
    document.execCommand(command, false, null);
    if (command === 'createLink') {
        const url = prompt('Enter URL:');
        if (url) {
            document.execCommand('createLink', false, url);
        }
    }
}

// Sync editor content with hidden textarea on form submit
document.querySelector('#send-email-form').addEventListener('submit', function() {
    const editor = document.getElementById('message-editor');
    const textarea = document.getElementById('email_message');
    if (editor && textarea) {
        textarea.value = editor.innerHTML;
    }
});
</script>

<style>
.email-preview {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
}

.preview-field {
    margin-bottom: 15px;
}

.preview-field label {
    font-weight: bold;
    display: block;
    margin-bottom: 5px;
    color: #495057;
}

.preview-content {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px;
    min-height: 40px;
}

.message-preview {
    min-height: 100px;
    max-height: 300px;
    overflow-y: auto;
}

.attachment-list {
    margin: 0;
    padding-left: 20px;
}

.attachment-list li {
    margin-bottom: 5px;
}

.attachment-list small {
    color: #6c757d;
    margin-left: 10px;
}

#send-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Recipient Information Styles */
.recipient-info {
    background: #e3f2fd;
    border: 1px solid #bbdefb;
    border-radius: 6px;
    padding: 15px;
    margin: 15px 0;
}

.recipient-info h4 {
    margin: 0 0 10px 0;
    color: #1976d2;
    font-size: 1.1em;
}

.recipient-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.recipient-field {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.recipient-field label {
    font-weight: bold;
    min-width: 120px;
    color: #555;
    font-size: 0.9em;
}

.recipient-field div {
    flex: 1;
    color: #333;
}

.email-badge {
    display: inline-block;
    background: #2196f3;
    color: white;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.85em;
    margin-right: 5px;
    margin-bottom: 3px;
}

/* WYSIWYG Editor Styles */
.wysiwyg-container {
    border: 1px solid #ddd;
    border-radius: 4px;
}

.wysiwyg-toolbar {
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
    padding: 8px;
}

.wysiwyg-toolbar button {
    background: none;
    border: 1px solid #ddd;
    margin-right: 4px;
    padding: 4px 8px;
    cursor: pointer;
    border-radius: 3px;
}

.wysiwyg-toolbar button:hover {
    background: #e9ecef;
}

.wysiwyg-editor {
    min-height: 150px;
    padding: 10px;
    background: white;
    border-radius: 0 0 4px 4px;
    outline: none;
}

.wysiwyg-editor:focus {
    border: 2px solid #007bff;
    margin: -1px;
}

/* Attachment Styles */
.current-attachments {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px;
    margin-bottom: 10px;
}

.attachment-item {
    display: flex;
    align-items: center;
    margin-bottom: 5px;
}

.attachment-item input[type="checkbox"] {
    margin-right: 8px;
}

.attachment-item label {
    margin: 0;
    cursor: pointer;
    flex: 1;
}

.attachment-item small {
    color: #6c757d;
    margin-left: 10px;
}

/* Form validation styles */
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

.required {
    color: #dc3545;
}

@media (max-width: 768px) {
    .recipient-field {
        flex-direction: column;
        gap: 2px;
    }
    
    .recipient-field label {
        min-width: auto;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
