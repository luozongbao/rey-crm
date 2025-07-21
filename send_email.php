<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$page_title = 'Send Email';
$current_page = 'send_email';

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

// Get customers and contacts for recipient selection
try {
    $stmt = $pdo->prepare("
        SELECT c.customer_id, c.company_name, c.contact_email as customer_email,
               cp.contact_id, cp.name as contact_name, cp.contact_email
        FROM customers c
        LEFT JOIN contact_persons cp ON c.customer_id = cp.customer_id
        WHERE c.contact_email IS NOT NULL OR cp.contact_email IS NOT NULL
        ORDER BY c.company_name, cp.name
    ");
    $stmt->execute();
    $recipients = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching recipients: " . $e->getMessage());
    $recipients = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $to_email = trim($_POST['to_email'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($to_email)) {
        $errors[] = "Please select a recipient.";
    }
    if (!$project) {
        $errors[] = "Email project not found.";
    }
    
    if (empty($errors)) {
        try {
            // Get SMTP settings from the settings table
            $smtp_settings = [];
            $stmt = $pdo->prepare("SELECT setting_name, value FROM settings WHERE setting_name LIKE 'smtp_%'");
            $stmt->execute();
            while ($row = $stmt->fetch()) {
                $smtp_settings[$row['setting_name']] = $row['value'];
            }
            
            // Create PHPMailer instance
            $mail = new PHPMailer(true);
            
            // Disable SMTP debugging for production
            $mail->SMTPDebug = 0;
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $smtp_settings['smtp_host'] ?? 'localhost';
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_settings['smtp_username'] ?? '';
            $mail->Password = $smtp_settings['smtp_password'] ?? '';
            
            // Set encryption based on settings
            $encryption = $smtp_settings['smtp_encryption'] ?? 'tls';
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS/STARTTLS
            }
            
            $mail->Port = $smtp_settings['smtp_port'] ?? 587;
            
            // Additional SSL/TLS options for better compatibility
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Recipients
            $mail->setFrom($smtp_settings['smtp_from_email'] ?? 'noreply@example.com', 
                          $smtp_settings['smtp_from_name'] ?? 'Rey CRM');
            $mail->addAddress($to_email);
            
            // Add CC if specified
            if (!empty($project['cc'])) {
                $cc_emails = array_map('trim', explode(',', $project['cc']));
                foreach ($cc_emails as $cc_email) {
                    if (filter_var($cc_email, FILTER_VALIDATE_EMAIL)) {
                        $mail->addCC($cc_email);
                    }
                }
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $project['subject'];
            $mail->Body = $project['message'];
            $mail->AltBody = strip_tags($project['message']);
            
            // Add attachments if any
            $attachment_status = [];
            if (!empty($project['attachments'])) {
                $attachments = json_decode($project['attachments'], true);
                if (is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        // Convert relative path to absolute path
                        $attachment_path = $attachment;
                        if (!is_absolute_path($attachment)) {
                            $attachment_path = __DIR__ . '/' . $attachment;
                        }
                        
                        if (file_exists($attachment_path)) {
                            $mail->addAttachment($attachment_path, basename($attachment));
                            $attachment_status[] = "✓ Added: " . basename($attachment);
                            error_log("Added attachment: " . $attachment_path);
                        } else {
                            $attachment_status[] = "✗ Missing: " . basename($attachment);
                            error_log("Attachment file not found: " . $attachment_path);
                        }
                    }
                } else {
                    $attachment_status[] = "✗ Invalid attachment data format";
                    error_log("Invalid attachment JSON: " . $project['attachments']);
                }
            }
            
            // Send email
            $mail->send();
            
            // Save to email history
            $stmt = $pdo->prepare("
                INSERT INTO sent_email_history 
                (sent_datetime, to_email, cc, project_id, subject, attachments) 
                VALUES (NOW(), ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $to_email,
                $project['cc'],
                $project_id,
                $project['subject'],
                $project['attachments']
            ]);
            
            $success_message = 'Email sent successfully!';
            
            // Add attachment info if there were attachments
            if (!empty($attachment_status)) {
                $successful_attachments = array_filter($attachment_status, function($status) {
                    return strpos($status, '✓') === 0;
                });
                $failed_attachments = array_filter($attachment_status, function($status) {
                    return strpos($status, '✗') === 0;
                });
                
                if (count($successful_attachments) > 0) {
                    $success_message .= ' (' . count($successful_attachments) . ' attachment' . 
                                      (count($successful_attachments) > 1 ? 's' : '') . ' included)';
                }
                
                if (count($failed_attachments) > 0) {
                    $success_message .= '<br><small style="color: #856404;">Note: ' . 
                                      count($failed_attachments) . ' attachment' . 
                                      (count($failed_attachments) > 1 ? 's were' : ' was') . 
                                      ' not found and could not be attached.</small>';
                }
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
    <div class="page-header">
        <div class="page-title-container">
            <h1 class="page-title">Send Email</h1>
            <p class="page-subtitle">Send email using project template</p>
        </div>
        <div class="page-actions">
            <a href="email_projects.php" class="btn btn-secondary">
                Back to Projects
            </a>
        </div>
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
                                
                                if ($recipient['customer_email']) {
                                    $grouped_recipients[$company][] = [
                                        'email' => $recipient['customer_email'],
                                        'name' => $company . ' (Company)',
                                        'type' => 'customer'
                                    ];
                                }
                                
                                if ($recipient['contact_email'] && $recipient['contact_name']) {
                                    $grouped_recipients[$company][] = [
                                        'email' => $recipient['contact_email'],
                                        'name' => $recipient['contact_name'] . ' (' . $company . ')',
                                        'type' => 'contact'
                                    ];
                                }
                            }
                            
                            foreach ($grouped_recipients as $company => $company_recipients):
                            ?>
                                <optgroup label="<?php echo htmlspecialchars($company); ?>">
                                    <?php foreach ($company_recipients as $recipient): ?>
                                        <option value="<?php echo htmlspecialchars($recipient['email']); ?>">
                                            <?php echo htmlspecialchars($recipient['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="email-preview">
                        <h4>Email Preview</h4>
                        
                        <div class="preview-field">
                            <label>Subject:</label>
                            <div class="preview-content"><?php echo htmlspecialchars($project['subject']); ?></div>
                        </div>

                        <?php if (!empty($project['cc'])): ?>
                            <div class="preview-field">
                                <label>CC:</label>
                                <div class="preview-content"><?php echo htmlspecialchars($project['cc']); ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="preview-field">
                            <label>Message:</label>
                            <div class="preview-content message-preview">
                                <?php echo $project['message']; ?>
                            </div>
                        </div>

                        <?php if (!empty($project['attachments'])): ?>
                            <?php $attachments = json_decode($project['attachments'], true); ?>
                            <?php if (!empty($attachments)): ?>
                                <div class="preview-field">
                                    <label>Attachments:</label>
                                    <div class="preview-content">
                                        <ul class="attachment-list">
                                            <?php foreach ($attachments as $attachment): ?>
                                                <?php
                                                // Convert relative path to absolute path for file checking
                                                $attachment_path = $attachment;
                                                if (!is_absolute_path($attachment)) {
                                                    $attachment_path = __DIR__ . '/' . $attachment;
                                                }
                                                ?>
                                                <li>
                                                    <?php echo htmlspecialchars(basename($attachment)); ?>
                                                    <small>(<?php echo file_exists($attachment_path) ? number_format(filesize($attachment_path)/1024, 1) . ' KB' : 'File not found'; ?>)</small>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
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

    function toggleSendButton() {
        sendButton.disabled = !toEmailSelect.value;
    }

    toEmailSelect.addEventListener('change', toggleSendButton);
    
    // Initial check
    toggleSendButton();
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
</style>

<?php include 'includes/footer.php'; ?>
