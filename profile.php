<?php
// filepath: /home/zongbao/var/www/rey-crm/profile.php
require_once 'includes/functions.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get current user data
try {
    $stmt = $pdo->prepare("SELECT username, email, role, preferred_language, smtp_host, smtp_port, smtp_username, smtp_password, smtp_from_email, smtp_from_name, smtp_encryption FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // This shouldn't happen unless the user was deleted while logged in
        header('Location: logout.php');
        exit;
    }
} catch (PDOException $e) {
    $error = "Failed to load user data. Please try again.";
    logError("Profile load failed: " . $e->getMessage());
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stored_hash = $stmt->fetchColumn();
        
        if (!password_verify($current_password, $stored_hash)) {
            $error = "Current password is incorrect.";
        } elseif (empty($new_password)) {
            $error = "New password cannot be empty.";
        } elseif (strlen($new_password) < 8) {
            $error = "New password must be at least 8 characters.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            // Invalidate all existing password reset tokens for security
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Commit transaction
            $pdo->commit();
            
            $message = "Your password has been updated successfully.";
        }
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $error = "Failed to update password. Please try again.";
        logError("Password change failed: " . $e->getMessage());
    }
}

// Handle profile updates (email and language preference)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email'] ?? '');
    $preferred_language = $_POST['preferred_language'] ?? '';
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }
        
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception("This email address is already in use by another user.");
        }
        
        // Validate language preference
        if (!empty($preferred_language) && !isLanguageAvailable($preferred_language)) {
            throw new Exception("Selected language is not available.");
        }
        
        // Update user profile
        $stmt = $pdo->prepare("UPDATE users SET email = ?, preferred_language = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$email, $preferred_language, $user_id]);
        
        // Commit transaction
        $pdo->commit();
        
        // Update user data for display
        $user['email'] = $email;
        $user['preferred_language'] = $preferred_language;
        
        // Update session language if changed
        if (!empty($preferred_language)) {
            $_SESSION['language'] = $preferred_language;
            setcookie('language', $preferred_language, time() + (86400 * 30), '/'); // 30 days
        }
        
        $message = "Your profile has been updated successfully.";
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    } catch (PDOException $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Failed to update profile. Please try again.";
        logError("Profile update failed: " . $e->getMessage());
    }
}

// Handle test email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_email'])) {
    header('Content-Type: application/json');
    
    try {
        // Check if user has configured email settings
        if (empty($user['smtp_username']) || empty($user['smtp_password']) || 
            empty($user['smtp_from_email']) || empty($user['smtp_from_name'])) {
            echo json_encode(['success' => false, 'message' => 'Please configure your email settings first.']);
            exit;
        }
        
        // Validate the test email recipient
        $test_email = trim($_POST['test_email'] ?? '');
        if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }
        
        // Prepare test email content
        $subject = "Test Email from Rey CRM - " . $user['smtp_from_name'];
        $body = "<html><body>
                    <h2>Email Configuration Test</h2>
                    <p>Hello!</p>
                    <p>This is a test email from your Rey CRM system to verify that your personal email settings are configured correctly.</p>
                    <p><strong>Configuration Details:</strong></p>
                    <ul>
                        <li><strong>SMTP Server:</strong> " . htmlspecialchars($user['smtp_host'] ?: getSMTPSettings()['smtp_host']) . "</li>
                        <li><strong>Port:</strong> " . htmlspecialchars($user['smtp_port'] ?: getSMTPSettings()['smtp_port']) . "</li>
                        <li><strong>Encryption:</strong> " . htmlspecialchars(strtoupper($user['smtp_encryption'] ?: getSMTPSettings()['smtp_encryption'])) . "</li>
                        <li><strong>From Email:</strong> " . htmlspecialchars($user['smtp_from_email']) . "</li>
                        <li><strong>From Name:</strong> " . htmlspecialchars($user['smtp_from_name']) . "</li>
                    </ul>
                    <p><strong>Test sent at:</strong> " . date('Y-m-d H:i:s') . "</p>
                    <p>If you received this email, your settings are working correctly!</p>
                    <p><em>No action is required.</em></p>
                 </body></html>";
        
        // Send test email using user's SMTP settings
        $result = sendUserEmail($user_id, $test_email, $subject, $body);
        
        echo json_encode($result);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send test email: ' . $e->getMessage()]);
        exit;
    }
}

// Handle email settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email_settings'])) {
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = intval($_POST['smtp_port'] ?? 0);
    $smtp_username = trim($_POST['smtp_username'] ?? '');
    $smtp_password = trim($_POST['smtp_password'] ?? '');
    $smtp_from_email = trim($_POST['smtp_from_email'] ?? '');
    $smtp_from_name = trim($_POST['smtp_from_name'] ?? '');
    $smtp_encryption = $_POST['smtp_encryption'] ?? '';
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Validate required fields
        if (empty($smtp_username) || empty($smtp_password) || empty($smtp_from_email) || empty($smtp_from_name)) {
            throw new Exception("SMTP Username, Password, From Email, and From Name are required.");
        }
        
        // Validate from email format
        if (!filter_var($smtp_from_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid From Email address.");
        }
        
        // If host and port are empty, get defaults from system settings
        $system_smtp = getSMTPSettings();
        if (empty($smtp_host)) {
            $smtp_host = $system_smtp['smtp_host'];
        }
        if ($smtp_port <= 0) {
            $smtp_port = $system_smtp['smtp_port'];
        }
        if (empty($smtp_encryption)) {
            $smtp_encryption = $system_smtp['smtp_encryption'];
        }
        
        // Update user email settings
        $stmt = $pdo->prepare("UPDATE users SET smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?, smtp_from_email = ?, smtp_from_name = ?, smtp_encryption = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$smtp_host, $smtp_port, $smtp_username, $smtp_password, $smtp_from_email, $smtp_from_name, $smtp_encryption, $user_id]);
        
        // Commit transaction
        $pdo->commit();
        
        // Update user data for display
        $user['smtp_host'] = $smtp_host;
        $user['smtp_port'] = $smtp_port;
        $user['smtp_username'] = $smtp_username;
        $user['smtp_password'] = $smtp_password;
        $user['smtp_from_email'] = $smtp_from_email;
        $user['smtp_from_name'] = $smtp_from_name;
        $user['smtp_encryption'] = $smtp_encryption;
        
        $message = "Your email settings have been updated successfully.";
        
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    } catch (PDOException $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Failed to update email settings. Please try again.";
        logError("Email settings update failed: " . $e->getMessage());
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="header">
        <h1><?php echo __('my_profile'); ?></h1>
        <p><?php echo __('manage_account_settings'); ?></p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="settings-container">
        <div class="settings-section profile-box">
            <div class="settings-header">
                <h3><?php echo __('account_information'); ?></h3>
            </div>
            <div class="settings-content">
                <form method="POST" action="" class="form">
                    <div class="form-group">
                        <label for="username"><?php echo __('username'); ?>:</label>
                        <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly class="form-input readonly">
                        <small class="form-text text-muted"><?php echo __('username_readonly'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="email"><?php echo __('email_address'); ?>:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="role"><?php echo __('role'); ?>:</label>
                        <input type="text" id="role" value="<?php echo htmlspecialchars(ucfirst($user['role'])); ?>" readonly class="form-input readonly">
                    </div>

                    <div class="form-group">
                        <label for="preferred_language"><?php echo __('preferred_language'); ?>:</label>
                        <select id="preferred_language" name="preferred_language" class="form-input">
                            <?php foreach (getAvailableLanguages() as $code => $info): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" 
                                        <?php echo ($user['preferred_language'] ?? getDefaultLanguage()) === $code ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($info['flag'] . ' ' . $info['native_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted"><?php echo __('preferred_language_help'); ?></small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn btn-primary"><?php echo __('update_profile'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="settings-section email-settings-box">
            <div class="settings-header">
                <h3><?php echo __('email_settings'); ?></h3>
            </div>
            <div class="settings-content">
                <form method="POST" action="" class="form">
                    <div class="form-group">
                        <label for="smtp_host"><?php echo __('smtp_server'); ?>:</label>
                        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($user['smtp_host'] ?? ''); ?>" class="form-input" placeholder="<?php echo htmlspecialchars(getSMTPSettings()['smtp_host'] ?? 'smtp.gmail.com'); ?>">
                        <small class="form-text text-muted"><?php echo __('smtp_server_help'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="smtp_port"><?php echo __('smtp_port'); ?>:</label>
                        <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($user['smtp_port'] ?? ''); ?>" class="form-input" placeholder="<?php echo htmlspecialchars(getSMTPSettings()['smtp_port'] ?? '587'); ?>" min="1" max="65535">
                        <small class="form-text text-muted"><?php echo __('smtp_port_help'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="smtp_username"><?php echo __('smtp_username'); ?> *:</label>
                        <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($user['smtp_username'] ?? ''); ?>" required class="form-input">
                        <small class="form-text text-muted"><?php echo __('smtp_username_help'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="smtp_password"><?php echo __('smtp_password'); ?> *:</label>
                        <input type="password" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($user['smtp_password'] ?? ''); ?>" required class="form-input">
                        <small class="form-text text-muted"><?php echo __('smtp_password_help'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="smtp_from_email"><?php echo __('from_email'); ?> *:</label>
                        <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($user['smtp_from_email'] ?? ''); ?>" required class="form-input">
                        <small class="form-text text-muted"><?php echo __('from_email_help'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="smtp_from_name"><?php echo __('from_name'); ?> *:</label>
                        <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($user['smtp_from_name'] ?? ''); ?>" required class="form-input">
                        <small class="form-text text-muted"><?php echo __('from_name_help'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="smtp_encryption"><?php echo __('encryption'); ?>:</label>
                        <select id="smtp_encryption" name="smtp_encryption" class="form-input">
                            <option value="" <?php echo empty($user['smtp_encryption']) ? 'selected' : ''; ?>><?php echo __('use_system_default'); ?> (<?php echo strtoupper(getSMTPSettings()['smtp_encryption'] ?? 'TLS'); ?>)</option>
                            <option value="tls" <?php echo ($user['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo ($user['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo ($user['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>><?php echo __('none'); ?></option>
                        </select>
                        <small class="form-text text-muted"><?php echo __('encryption_help'); ?></small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_email_settings" class="btn btn-primary"><?php echo __('update_email_settings'); ?></button>
                    </div>
                </form>
                
                <!-- Test Email Section -->
                <div class="test-email-section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                    <h4 style="margin-bottom: 15px; color: #333;"><?php echo __('test_email_settings'); ?></h4>
                    <p class="form-text text-muted" style="margin-bottom: 15px;">Send a test email to verify your email settings are working correctly.</p>
                    
                    <div class="test-email-form">
                        <div class="form-group">
                            <label for="test_email_recipient"><?php echo __('recipient_email'); ?>:</label>
                            <input type="email" id="test_email_recipient" class="form-input" 
                                   placeholder="<?php echo htmlspecialchars($user['email']); ?>" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   style="margin-bottom: 10px;">
                        </div>
                        <div class="form-actions">
                            <button type="button" id="send_test_email_btn" class="btn btn-secondary">
                                <span class="btn-text"><?php echo __('send_test_email'); ?></span>
                                <span class="btn-spinner" style="display: none;">Sending...</span>
                            </button>
                        </div>
                        <div id="test_email_result" style="margin-top: 10px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="settings-section password-box">
            <div class="settings-header">
                <h3>Change Password</h3>
            </div>
            <div class="settings-content">
                <form method="POST" action="" class="form">
                    <div class="form-group">
                        <label for="current_password">Current Password:</label>
                        <input type="password" id="current_password" name="current_password" required class="form-input">
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8" class="form-input">
                        <small class="form-text text-muted">Password must be at least 8 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required class="form-input">
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
    // Password confirmation validation
    document.addEventListener('DOMContentLoaded', function() {
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordForm = document.querySelector('.password-box form');
        
        function validatePassword() {
            if(confirmPassword.value !== newPassword.value) {
                confirmPassword.setCustomValidity("Passwords don't match");
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        newPassword.addEventListener('change', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
        
        passwordForm.addEventListener('submit', function(event) {
            validatePassword();
            if (!passwordForm.checkValidity()) {
                event.preventDefault();
                return false;
            }
            return true;
        });
        
        // Test email functionality
        const sendTestEmailBtn = document.getElementById('send_test_email_btn');
        const testEmailInput = document.getElementById('test_email_recipient');
        const testEmailResult = document.getElementById('test_email_result');
        const btnText = sendTestEmailBtn.querySelector('.btn-text');
        const btnSpinner = sendTestEmailBtn.querySelector('.btn-spinner');
        
        sendTestEmailBtn.addEventListener('click', function() {
            const testEmail = testEmailInput.value.trim();
            
            // Clear previous results
            testEmailResult.innerHTML = '';
            
            // Validate email input
            if (!testEmail) {
                showTestResult('Please enter an email address.', 'error');
                return;
            }
            
            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(testEmail)) {
                showTestResult('Please enter a valid email address.', 'error');
                return;
            }
            
            // Show loading state
            sendTestEmailBtn.disabled = true;
            btnText.style.display = 'none';
            btnSpinner.style.display = 'inline';
            
            // Send AJAX request
            const formData = new FormData();
            formData.append('send_test_email', '1');
            formData.append('test_email', testEmail);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showTestResult('Test email sent successfully! Check your inbox.', 'success');
                } else {
                    showTestResult(data.message || 'Failed to send test email.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showTestResult('An error occurred while sending the test email.', 'error');
            })
            .finally(() => {
                // Reset button state
                sendTestEmailBtn.disabled = false;
                btnText.style.display = 'inline';
                btnSpinner.style.display = 'none';
            });
        });
        
        function showTestResult(message, type) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            testEmailResult.innerHTML = '<div class="alert ' + alertClass + '">' + message + '</div>';
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    testEmailResult.innerHTML = '';
                }, 5000);
            }
        }
        
        // Allow Enter key to trigger test email
        testEmailInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendTestEmailBtn.click();
            }
        });
    });
</script>
</body>
</html>
