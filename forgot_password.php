<?php
// filepath: /home/zongbao/var/www/rey-crm/forgot_password.php
require_once 'includes/functions.php';
require_once 'vendor/autoload.php';
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';

// Always clean up expired tokens when someone uses the password reset page
cleanupExpiredTokens();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = __('please_enter_your_email_address');
    } else {
        try {
            // Check if the email exists in the database
            $stmt = $pdo->prepare("SELECT user_id, username, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Use centralized function to send password reset email
                $reset_result = sendPasswordResetEmail($email, $user['username'], $user['user_id']);
                
                if ($reset_result['success']) {
                    $message = __('password_reset_instructions_sent_to_email');
                } else {
                    $error = __('failed_to_send_reset_email') . ': ' . $reset_result['message'];
                }
            } else {
                // For security reasons, still display success message even if email doesn't exist
                $message = __('if_email_registered_reset_instructions_will_be_sent');
            }
        } catch (PDOException $e) {
            $error = __('error_occurred_try_again_later');
            logError("Password reset request failed: " . $e->getMessage());
        } catch (Exception $e) {
            $error = __('error_occurred_try_again_later');
            logError("Password reset request failed: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('forgot_password'); ?> - <?php echo __('rey_crm'); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title"><?php echo __('forgot_password'); ?></h1>
                <p class="auth-subtitle"><?php echo __('enter_email_to_receive_reset_link'); ?></p>
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

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email"><?php echo __('email_address'); ?></label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-input" 
                           required 
                           autofocus
                           placeholder="<?php echo __('enter_your_email'); ?>">
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <?php echo __('send_reset_link'); ?>
                </button>
                
                <div class="auth-links">
                    <a href="login.php" class="text-link"><?php echo __('back_to_login'); ?></a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
