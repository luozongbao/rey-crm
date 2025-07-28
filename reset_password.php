<?php
// filepath: /home/zongbao/var/www/rey-crm/reset_password.php
require_once 'includes/functions.php';

// If already logged in, redirect to customer dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: customer_dashboard.php');
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
        $error = __('too_many_invalid_token_attempts') . ' ' . ceil($wait_time / 60) . ' ' . __('minutes');
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
                WHERE t.token = ? AND t.used = 0 AND t.expiry_date > ?
            ");
            $stmt->execute([$token, getCurrentUTCDateTime()]);
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
                
                $error = __('invalid_or_expired_reset_link');
            }
        } catch (PDOException $e) {
            $error = __('error_occurred_try_again_later');
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
        $error = __('password_cannot_be_empty');
    } elseif (strlen($password) < 8) {
        $error = __('password_must_be_at_least_8_characters');
    } elseif ($password !== $confirm_password) {
        $error = __('passwords_do_not_match');
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
            
            $message = __('password_reset_successfully');
            
            // Redirect to login page after 3 seconds
            header("refresh:3;url=login.php");
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            
            $error = __('failed_to_reset_password');
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
    <title><?php echo __('reset_password'); ?> - <?php echo __('rey_crm'); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title"><?php echo __('reset_password'); ?></h1>
                <p class="auth-subtitle">
                    <?php echo $valid_token ? __('create_new_password_for_account') : __('password_reset'); ?>
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
                        <label for="password"><?php echo __('new_password'); ?></label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-input" 
                               required 
                               autofocus
                               minlength="8">
                        <small class="form-text text-muted"><?php echo __('password_must_be_at_least_8_characters'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password"><?php echo __('confirm_password'); ?></label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-input" 
                               required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <?php echo __('reset_password'); ?>
                    </button>
                </form>
            <?php elseif (!$valid_token && !$message): ?>
                <div class="auth-links">
                    <a href="forgot_password.php" class="btn btn-outline"><?php echo __('request_new_reset_link'); ?></a>
                    <div class="spacer-y-2"></div>
                    <a href="login.php" class="text-link"><?php echo __('back_to_login'); ?></a>
                </div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="auth-links">
                    <a href="login.php" class="text-link"><?php echo __('go_to_login'); ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
