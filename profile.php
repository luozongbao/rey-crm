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
    $stmt = $pdo->prepare("SELECT username, email, role FROM users WHERE user_id = ?");
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

// Handle email/profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email'] ?? '');
    
    try {
        // Validate email format
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if email already exists for a different user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Email address is already in use by another account.";
            } else {
                // Update email
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE user_id = ?");
                $stmt->execute([$email, $user_id]);
                
                // Update session with new data if needed
                $user['email'] = $email;
                
                $message = "Your profile has been updated successfully.";
            }
        }
    } catch (PDOException $e) {
        $error = "Failed to update profile. Please try again.";
        logError("Profile update failed: " . $e->getMessage());
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="header">
        <h1>My Profile</h1>
        <p>Manage your account settings and change your password.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="settings-section">
        <h3>Account Information</h3>
        <form method="POST" action="" class="form">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly class="form-input readonly">
                <small class="form-text text-muted">Username cannot be changed</small>
            </div>

            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="form-input">
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <input type="text" id="role" value="<?php echo htmlspecialchars(ucfirst($user['role'])); ?>" readonly class="form-input readonly">
            </div>

            <button type="submit" name="update_profile" class="btn">Update Profile</button>
        </form>
    </div>

    <div class="settings-section">
        <h3>Change Password</h3>
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

            <button type="submit" name="change_password" class="btn">Change Password</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
