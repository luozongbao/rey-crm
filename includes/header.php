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
            <h1 class="logo"><a href="/index.php">Rey CRM</a></h1>
            <?php if (isset($_SESSION['user_id'])): ?>
            <nav class="main-nav">
                <ul>
                    <li><a href="/dashboard.php">Dashboard</a></li>
                    <li><a href="/customers.php">Customer List</a></li>
                    <li><a href="/all_activities.php">All Activities</a></li>
                    <li><a href="/all_followups.php">All Followups</a></li>
                    <?php if (isAdmin()): ?>
                    <li><a href="/settings.php">Settings</a></li>
                    <?php endif; ?>
                    <li class="user-menu">
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <a href="/logout.php" class="logout-btn">Logout</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </header>

    <style>
    .user-menu {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .user-menu span {
        color: #666;
    }
    .logout-btn {
        background: #dc3545;
        color: white !important;
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        text-decoration: none;
    }
    .logout-btn:hover {
        background: #c82333;
    }
    </style>
    <main class="container">
