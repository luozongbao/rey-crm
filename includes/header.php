<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rey CRM</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Redirect to login if not logged in
    if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php' && basename($_SERVER['PHP_SELF']) !== 'install.php') {
        header('Location: /login.php');
        exit;
    }
    ?>
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <h1 class="logo">
                    <a href="/index.php" title="Rey CRM Dashboard">
                        <span>Rey CRM</span>
                    </a>
                </h1>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Mobile menu button -->
                <button class="mobile-menu-button" aria-label="Open menu">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                    </svg>
                </button>

                <nav class="main-nav">
                    <ul class="nav-list">
                        <li class="nav-item">
                            <a href="/dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/customers.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
                                Customers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/all_activities.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'all_activities.php' ? 'active' : ''; ?>">
                                Activities
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/all_followups.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'all_followups.php' ? 'active' : ''; ?>">
                                Follow-ups
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                        <li class="nav-item">
                            <a href="/settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                                Settings
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>

                    <div class="user-menu">
                        <div class="user-info">
                            <span class="username">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <div class="user-actions">
                                <a href="/profile.php" class="btn profile-btn" title="My Profile">
                                    My Profile
                                </a>
                                <a href="/logout.php" class="btn logout-btn">
                                    Logout
                                </a>
                                <button id="dark-mode-toggle" class="btn icon-btn" title="Toggle dark mode" aria-label="Toggle dark mode">
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
