<?php
// filepath: /home/zongbao/var/www/rey-crm/reset_password.php
require_once 'includes/functions.php';
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';
$token = $_GET['token'] ?? '';
$valid_token = false;
$user_id = null;

// Validate token
if (!empty($token)) {
    // Clean up any expired tokens
    cleanupExpiredTokens();
    
    // Add rate limiting - store failed attempts in session
    if (!isset($_SESSION['token_attempts'])) {
        $_SESSION['token_attempts'] = 0;
        $_SESSION['token_last_attempt'] = 0;
    }
    
    // If too many failed attempts, enforce a timeout
    $max_attempts = 5;
    $timeout_seconds = 300; // 5 minutes
    $current_time = time();
    
    if ($_SESSION['token_attempts'] >= $max_attempts && 
        ($current_time - $_SESSION['token_last_attempt']) < $timeout_seconds) {
        $wait_time = $timeout_seconds - ($current_time - $_SESSION['token_last_attempt']);
        $error = "Too many invalid token attempts. Please try again in " . ceil($wait_time / 60) . " minutes.";
    } else {
        try {
            // Reset counter if timeout has passed
            if ($_SESSION['token_attempts'] >= $max_attempts && 
                ($current_time - $_SESSION['token_last_attempt']) >= $timeout_seconds) {
                $_SESSION['token_attempts'] = 0;
            }
            
            // Check if token exists, is not used, and not expired
            $stmt = $pdo->prepare("
                SELECT t.id, t.user_id, u.username, u.email 
                FROM password_reset_tokens t
                JOIN users u ON t.user_id = u.user_id
                WHERE t.token = ? AND t.used = 0 AND t.expiry_date > NOW()
            ");
            $stmt->execute([$token]);
            $token_data = $stmt->fetch();
            
            if ($token_data) {
                $valid_token = true;
                $user_id = $token_data['user_id'];
                $username = $token_data['username'];
                $email = $token_data['email'];
                
                // Reset attempt counter on successful token validation
                $_SESSION['token_attempts'] = 0;
            } else {
                // Record this failed attempt
                $_SESSION['token_attempts']++;
                $_SESSION['token_last_attempt'] = $current_time;
                
                $error = "Invalid or expired password reset link. Please request a new one.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again later.";
            logError("Token validation failed: " . $e->getMessage());
        }
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate password
    if (empty($password)) {
        $error = "Password cannot be empty.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update user password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update_stmt->execute([$hashed_password, $user_id]);
            
            // Mark token as used
            $token_stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $token_stmt->execute([$token]);
            
            // Commit transaction
            $pdo->commit();
            
            $message = "Password has been reset successfully. You can now login with your new password.";
            
            // Redirect to login page after 3 seconds
            header("refresh:3;url=login.php");
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            
            $error = "Failed to reset password. Please try again.";
            logError("Password reset failed: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Rey CRM</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title">Reset Password</h1>
                <p class="auth-subtitle">
                    <?php echo $valid_token ? "Create a new password for your account" : "Password Reset"; ?>
                </p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($valid_token): ?>
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input" 
                               required 
                               autofocus
                               minlength="8">
                        <small class="form-text text-muted">Password must be at least 8 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-input" 
                               required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        Reset Password
                    </button>
                </form>
            <?php elseif (!$valid_token && !$message): ?>
                <div class="auth-links">
                    <a href="forgot_password.php" class="btn btn-outline">Request New Reset Link</a>
                    <div class="spacer-y-2"></div>
                    <a href="login.php" class="text-link">Back to Login</a>
                </div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="auth-links">
                    <a href="login.php" class="text-link">Go to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
