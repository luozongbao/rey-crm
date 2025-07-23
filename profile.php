<?php
// filepath: /home/zongbao/var/www/rey-crm/profile.php
require_once 'includes/functions.php';
session_start();

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get current user data
try {
    $stmt = $pdo->prepare("SELECT username, email, role, preferred_language FROM users WHERE user_id = ?");
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
    });
</script>
</body>
</html>
