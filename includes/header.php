<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize language
$current_language = initLanguage();
$lang_info = getCurrentLanguageInfo();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php' && basename($_SERVER['PHP_SELF']) !== 'install.php') {
    header('Location: /login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('rey_crm'); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/language.css">
    <script src="/assets/js/timezone.js?v=2.0" defer></script>
    <script src="/assets/js/language.js" defer></script>
    <!-- Language support CSS -->
    <?php if ($lang_info['direction'] === 'rtl'): ?>
    <link rel="stylesheet" href="/assets/css/rtl.css">
    <?php endif; ?>
</head>
<body <?php echo $lang_info['direction'] === 'rtl' ? 'class="rtl"' : ''; ?>>
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <h1 class="logo">
                    <a href="/index.php" title="<?php echo __('rey_crm_dashboard'); ?>">
                        <span><?php echo __('rey_crm'); ?></span>
                    </a>
                </h1>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Mobile menu button -->
                <button class="mobile-menu-button" aria-label="<?php echo __('open_menu'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                    </svg>
                </button>

                <nav class="main-nav">
                    <ul class="nav-list">
                        <li class="nav-item">
                            <a href="/dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                                <?php echo __('dashboard'); ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/customers.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
                                <?php echo __('customers'); ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/all_activities.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'all_activities.php' ? 'active' : ''; ?>">
                                <?php echo __('all_activities'); ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/all_followups.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'all_followups.php' ? 'active' : ''; ?>">
                                <?php echo __('followups'); ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/email_projects.php" class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['email_projects.php', 'email_project_form.php', 'send_email.php', 'email_history.php']) ? 'active' : ''; ?>">
                                <?php echo __('email_projects'); ?>
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a href="/settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                                <?php echo __('settings'); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <!-- Language switcher -->
                    <div class="language-switcher">
                        <select id="language-select" onchange="switchLanguage(this.value)" title="<?php echo __('select_language'); ?>">
                            <?php foreach (getAvailableLanguages() as $code => $info): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $code === $current_language ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($info['flag'] . ' ' . $info['native_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="user-menu">
                        <div class="user-info">
                            <div class="user-actions">
                                <a href="/profile.php" class="btn profile-btn" title="<?php echo __('my_profile'); ?>">
                                    <?php echo htmlspecialchars($_SESSION['username']); ?>
                                </a>
                                <a href="/logout.php" class="btn logout-btn">
                                    <?php echo __('logout'); ?>
                                </a>
                                <button id="dark-mode-toggle" class="btn icon-btn" title="<?php echo __('toggle_dark_mode'); ?>" aria-label="<?php echo __('toggle_dark_mode'); ?>">
                                    <span id="dark-mode-icon" aria-hidden="true">🌙</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </header>


    <script>
        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.querySelector('.mobile-menu-button');
            const mainNav = document.querySelector('.main-nav');
            const darkModeToggle = document.getElementById('dark-mode-toggle');
            const darkModeIcon = document.getElementById('dark-mode-icon');
            // Set initial mode from localStorage
            if (localStorage.getItem('darkMode') === 'enabled') {
                document.body.classList.add('dark-mode');
                if (darkModeIcon) darkModeIcon.textContent = '☀️';
            }
            if (mobileMenuButton && mainNav) {
                mobileMenuButton.addEventListener('click', function() {
                    mainNav.classList.toggle('show');
                    this.setAttribute('aria-expanded', 
                        this.getAttribute('aria-expanded') === 'true' ? 'false' : 'true'
                    );
                });
            }
            // Dark mode toggle logic
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', function() {
                    document.body.classList.toggle('dark-mode');
                    const enabled = document.body.classList.contains('dark-mode');
                    localStorage.setItem('darkMode', enabled ? 'enabled' : 'disabled');
                    darkModeIcon.textContent = enabled ? '☀️' : '🌙';
                });
            }
        });
    </script>
