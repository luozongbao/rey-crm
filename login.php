<?php
require_once 'includes/functions.php';

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    redirectTo('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Cleanup expired tokens occasionally (1% chance)
    if (mt_rand(1, 100) === 1) {
        cleanupExpiredTokens();
    }
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id, username, password, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Update last login time
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = ? WHERE user_id = ?");
                $updateStmt->execute([getCurrentUTCDateTime(), $user['user_id']]);
                
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "Login failed. Please try again.";
            logError($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Rey CRM</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title">Welcome to Rey CRM</h1>
                <p class="auth-subtitle">Sign in to your account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input" 
                           required 
                           autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Sign In
                </button>
                
                <div class="auth-links">
                    <a href="forgot_password.php" class="text-link">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
