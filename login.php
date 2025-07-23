<?php
require_once 'includes/functions.php';

// Initialize language system
$current_language = initLanguage();
$lang_info = getCurrentLanguageInfo();

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
        $error = __('login_required');
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
                $error = __('invalid_credentials');
            }
        } catch (PDOException $e) {
            $error = __('login_failed');
            logError($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('login'); ?> - <?php echo __('rey_crm'); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/language.css">
    <script src="/assets/js/language.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-page" <?php echo $lang_info['direction'] === 'rtl' ? 'class="rtl"' : ''; ?>>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title"><?php echo __('login_title'); ?></h1>
                <p class="auth-subtitle"><?php echo __('login_subtitle'); ?></p>
                
                <!-- Language switcher for login page -->
                <div class="language-switcher" style="margin-top: 1rem; text-align: center;">
                    <select id="language-select" onchange="switchLanguage(this.value)" title="<?php echo __('select_language'); ?>">
                        <?php foreach (getAvailableLanguages() as $code => $info): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $code === $current_language ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($info['flag'] . ' ' . $info['native_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="username"><?php echo __('username'); ?></label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input" 
                           required 
                           autofocus>
                </div>

                <div class="form-group">
                    <label for="password"><?php echo __('password'); ?></label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <?php echo __('sign_in'); ?>
                </button>
                
                <div class="auth-links">
                    <a href="forgot_password.php" class="text-link"><?php echo __('forgot_password'); ?></a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
