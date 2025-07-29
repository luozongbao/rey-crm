<?php
// Add security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' fonts.googleapis.com cdn.jsdelivr.net cdnjs.cloudflare.com; font-src 'self' fonts.gstatic.com cdnjs.cloudflare.com; img-src 'self' data:; frame-ancestors 'none';");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session timeout
checkSessionTimeout();

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
                    <a href="<?php echo isset($_SESSION['user_id']) ? '/customer_dashboard.php' : '/index.php'; ?>" title="<?php echo __('rey_crm_dashboard'); ?>">
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
                        <!-- Customer Management Dropdown -->
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['customers.php', 'customer_form.php', 'customer_dashboard.php']) ? 'active' : ''; ?>">
                                <?php echo __('customers'); ?>
                                <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a href="/customer_dashboard.php" class="dropdown-link"><?php echo __('customer_dashboard'); ?></a></li>
                                <li><a href="/customers.php" class="dropdown-link"><?php echo __('all_customers'); ?></a></li>
                                <li><a href="/customer_form.php" class="dropdown-link"><?php echo __('add_customer'); ?></a></li>
                            </ul>
                        </li>
                        
                        <!-- Activities & Follow-ups Dropdown -->
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['activities_dashboard.php', 'all_activities.php', 'all_followups.php', 'history_form.php']) ? 'active' : ''; ?>">
                                <?php echo __('activities'); ?>
                                <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a href="/activities_dashboard.php" class="dropdown-link"><?php echo __('activities_dashboard'); ?></a></li>
                                <li><a href="/all_activities.php" class="dropdown-link"><?php echo __('all_activities'); ?></a></li>
                                <li><a href="/all_followups.php" class="dropdown-link"><?php echo __('followups'); ?></a></li>
                                <!-- <li><a href="/history_form.php" class="dropdown-link"><?php echo __('add_activity'); ?></a></li> -->
                            </ul>
                        </li>
                        
                        <!-- Email Management Dropdown -->
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['email_projects.php', 'email_project_form.php', 'send_email.php', 'email_history.php']) ? 'active' : ''; ?>">
                                <?php echo __('email_management'); ?>
                                <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a href="/email_projects.php" class="dropdown-link"><?php echo __('email_projects'); ?></a></li>
                                <!-- <li><a href="/send_email.php" class="dropdown-link"><?php echo __('send_email'); ?></a></li> -->
                                <li><a href="/email_history.php" class="dropdown-link"><?php echo __('email_history'); ?></a></li>
                            </ul>
                        </li>
                        
                        <?php if (isAdmin()): ?>
                        <!-- Admin Dashboard -->
                        <li class="nav-item">
                            <a href="/admin_customer_management.php" class="nav-link admin-link <?php echo basename($_SERVER['PHP_SELF']) === 'admin_customer_management.php' ? 'active' : ''; ?>">
                                <?php echo __('admin_dashboard'); ?>
                            </a>
                        </li>
                        
                        <!-- Settings Dropdown -->
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['settings.php', 'security_dashboard.php', 'security_logs.php']) ? 'active' : ''; ?>">
                                <?php echo __('settings'); ?>
                            </a>
                            <div class="dropdown-menu">
                                <a href="/settings.php" class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-cog"></i> <?php echo __('general_settings'); ?>
                                </a>
                                <a href="/security_dashboard.php" class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) === 'security_dashboard.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-shield-alt"></i> <?php echo __('security_dashboard'); ?>
                                </a>
                                <a href="/security_logs.php" class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) === 'security_logs.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-file-alt"></i> <?php echo __('security_logs'); ?>
                                </a>
                            </div>
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
            
            // Mobile menu toggle
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
            
            // Dropdown functionality
            const dropdowns = document.querySelectorAll('.nav-item.dropdown');
            console.log('Found dropdowns:', dropdowns.length);
            
            dropdowns.forEach((dropdown, index) => {
                const toggle = dropdown.querySelector('.dropdown-toggle');
                const menu = dropdown.querySelector('.dropdown-menu');
                
                console.log(`Dropdown ${index}:`, { dropdown, toggle, menu });
                
                if (toggle && menu) {
                    // Add hover listeners for desktop
                    dropdown.addEventListener('mouseenter', function() {
                        console.log('Mouse enter dropdown:', index);
                        if (window.innerWidth > 768) {
                            dropdown.classList.add('show');
                        }
                    });
                    
                    dropdown.addEventListener('mouseleave', function() {
                        console.log('Mouse leave dropdown:', index);
                        if (window.innerWidth > 768) {
                            dropdown.classList.remove('show');
                        }
                    });
                    
                    // Click toggle for mobile and desktop
                    toggle.addEventListener('click', function(e) {
                        e.preventDefault();
                        console.log('Dropdown clicked:', index, 'Width:', window.innerWidth);
                        
                        // Close other dropdowns
                        dropdowns.forEach(otherDropdown => {
                            if (otherDropdown !== dropdown) {
                                otherDropdown.classList.remove('show');
                            }
                        });
                        
                        // Toggle current dropdown
                        dropdown.classList.toggle('show');
                        console.log('Dropdown show class:', dropdown.classList.contains('show'));
                    });
                }
            });
            
            // Close dropdowns when clicking outside (mobile only)
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    if (!e.target.closest('.nav-item.dropdown')) {
                        dropdowns.forEach(dropdown => {
                            dropdown.classList.remove('show');
                        });
                    }
                }
            });
            
            // Close dropdowns on mobile when nav is closed
            if (mobileMenuButton) {
                mobileMenuButton.addEventListener('click', function() {
                    dropdowns.forEach(dropdown => {
                        dropdown.classList.remove('show');
                    });
                });
            }
        });
    </script>
