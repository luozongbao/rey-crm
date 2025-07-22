<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = 'Email Project Form';
$current_page = 'email_project_form';
$is_edit = isset($_GET['id']) && !empty($_GET['id']);
$project_id = $is_edit ? (int)$_GET['id'] : null;
$project = null;

// Get project data if editing
if ($is_edit) {
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_name = trim($_POST['project_name'] ?? '');
    $cc = trim($_POST['cc'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = $_POST['message'] ?? '';
    $attachments = '';
    
    // Handle file uploads
    if (!empty($_FILES['attachments']['name'][0])) {
        $upload_dir = 'uploads/email_attachments/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $uploaded_files = [];
        for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                $file_name = $_FILES['attachments']['name'][$i];
                $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Generate unique filename
                $new_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $uploaded_files[] = $upload_path;
                }
            }
        }
        $attachments = json_encode($uploaded_files);
    } elseif ($is_edit && $project) {
        // Keep existing attachments if no new ones uploaded
        $attachments = $project['attachments'];
    }
    
    // Validation
    $errors = [];
    if (empty($project_name)) {
        $errors[] = "Project name is required.";
    }
    if (empty($subject)) {
        $errors[] = "Subject is required.";
    }
    
    // Validate CC emails if provided
    if (!empty($cc)) {
        $cc_validation = validate_cc_emails($cc);
        if (!$cc_validation['valid']) {
            $errors[] = $cc_validation['message'];
        }
    }
    
    if (empty($errors)) {
        try {
            if ($is_edit) {
                // Update existing project
                $stmt = $pdo->prepare("UPDATE email_projects SET project_name = ?, cc = ?, subject = ?, message = ?, attachments = ?, updated_at = ? WHERE project_id = ?");
                $stmt->execute([$project_name, $cc, $subject, $message, $attachments, getCurrentUTCDateTime(), $project_id]);
                $success_message = 'Email project updated successfully';
            } else {
                // Create new project
                $stmt = $pdo->prepare("INSERT INTO email_projects (project_name, cc, subject, message, attachments) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$project_name, $cc, $subject, $message, $attachments]);
                $success_message = 'Email project created successfully';
            }
            
            header('Location: email_projects.php?message=' . urlencode($success_message));
            exit;
        } catch (PDOException $e) {
            error_log("Error saving project: " . $e->getMessage());
            $error = "Error saving project.";
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div class="page-title-container">
            <h1 class="page-title"><?php echo $is_edit ? 'Edit Email Project' : 'Create Email Project'; ?></h1>
            <p class="page-subtitle">Create reusable email templates for your campaigns</p>
        </div>
        <div class="page-actions">
            <a href="email_projects.php" class="btn btn-secondary">
                Back to Projects
            </a>
        </div>
    </div>

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

    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="form">
                <div class="form-group">
                    <label for="project_name" class="form-label">Project Name <span class="required">*</span></label>
                    <input type="text" 
                           id="project_name" 
                           name="project_name" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($project['project_name'] ?? $_POST['project_name'] ?? ''); ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="subject" class="form-label">Subject <span class="required">*</span></label>
                    <input type="text" 
                           id="subject" 
                           name="subject" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($project['subject'] ?? $_POST['subject'] ?? ''); ?>" 
                           required>
                </div>

                <div class="form-group">
                    <label for="cc" class="form-label">CC (optional)</label>
                    <input type="text" 
                           id="cc" 
                           name="cc" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($project['cc'] ?? $_POST['cc'] ?? ''); ?>" 
                           placeholder="Enter multiple emails separated by commas or semicolons">
                    <small class="form-text">Enter multiple email addresses separated by commas (,) or semicolons (;)</small>
                </div>

                <div class="form-group">
                    <label for="message" class="form-label">Message</label>
                    <div class="wysiwyg-container">
                        <div class="wysiwyg-toolbar">
                            <button type="button" onclick="execCommand('bold')" title="Bold"><b>B</b></button>
                            <button type="button" onclick="execCommand('italic')" title="Italic"><i>I</i></button>
                            <button type="button" onclick="execCommand('underline')" title="Underline"><u>U</u></button>
                            <button type="button" onclick="execCommand('insertOrderedList')" title="Numbered List">1.</button>
                            <button type="button" onclick="execCommand('insertUnorderedList')" title="Bullet List">•</button>
                            <button type="button" onclick="execCommand('createLink')" title="Insert Link">🔗</button>
                        </div>
                        <div id="message-editor" 
                             class="wysiwyg-editor" 
                             contenteditable="true" 
                             style="min-height: 200px; border: 1px solid #ddd; padding: 10px;">
                            <?php echo $project['message'] ?? $_POST['message'] ?? ''; ?>
                        </div>
                        <textarea name="message" id="message" style="display: none;"></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label for="attachments" class="form-label">Attachments</label>
                    <input type="file" 
                           id="attachments" 
                           name="attachments[]" 
                           class="form-control" 
                           multiple
                           accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif">
                    <small class="form-text">You can select multiple files. Supported formats: PDF, DOC, DOCX, TXT, JPG, JPEG, PNG, GIF</small>
                    
                    <?php if ($is_edit && $project && !empty($project['attachments'])): ?>
                        <?php $existing_attachments = json_decode($project['attachments'], true); ?>
                        <?php if (!empty($existing_attachments)): ?>
                            <div class="existing-attachments">
                                <h4>Current Attachments:</h4>
                                <ul>
                                    <?php foreach ($existing_attachments as $attachment): ?>
                                        <li><?php echo htmlspecialchars(basename($attachment)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <small class="form-text">Upload new files to replace current attachments</small>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $is_edit ? 'Update Project' : 'Create Project'; ?>
                    </button>
                    <a href="email_projects.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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
document.querySelector('form').addEventListener('submit', function() {
    const editor = document.getElementById('message-editor');
    const textarea = document.getElementById('message');
    textarea.value = editor.innerHTML;
});

// Auto-save editor content periodically
setInterval(function() {
    const editor = document.getElementById('message-editor');
    const textarea = document.getElementById('message');
    textarea.value = editor.innerHTML;
}, 1000);

// CC email validation
document.getElementById('cc').addEventListener('blur', function() {
    validateCCEmails(this);
});

document.getElementById('cc').addEventListener('input', function() {
    // Clear any existing validation styles when user starts typing
    this.classList.remove('is-invalid', 'is-valid');
    const feedback = document.getElementById('cc-feedback');
    if (feedback) {
        feedback.remove();
    }
});

function validateCCEmails(input) {
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
</script>

<style>
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

/* CC validation styles */
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

.wysiwyg-editor {
    min-height: 200px;
    padding: 10px;
    border: none;
    outline: none;
}

.wysiwyg-editor:focus {
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.existing-attachments {
    margin-top: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

.existing-attachments ul {
    margin: 5px 0;
    padding-left: 20px;
}
</style>

<?php include 'includes/footer.php'; ?>
