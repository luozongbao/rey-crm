<?php
session_start();
$message = '';
$error = '';

// Reset installation if requested
if (isset($_GET['reset_install'])) {
    // Clear all installation-related session variables
    unset($_SESSION['db_configured']);
    unset($_SESSION['install_phase']);
    session_regenerate_id(true);
    
    // Redirect back to the installation page
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// If config file already exists and setup is complete, redirect to home page
if (file_exists(__DIR__ . '/config.php') && !isset($_SESSION['db_configured']) && !isset($_SESSION['install_phase'])) {
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['install_phase'])) {
    // Only process DB configuration if we're in the first phase
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = trim($_POST['db_pass'] ?? '');
    
    // Validate input
    if (empty($dbHost) || empty($dbName) || empty($dbUser)) {
        $error = "All fields except password are required.";
    } else {
        try {
            // Test database connection
            $testPdo = new PDO(
                "mysql:host=" . $dbHost . ";dbname=" . $dbName . ";charset=utf8mb4",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Import database schema
            try {
                $sqlContent = file_get_contents(__DIR__ . '/../database/database.sql');
                if ($sqlContent === false) {
                    throw new Exception("Could not read database.sql file");
                }
                
                // Execute each SQL statement
                $queries = array_filter(array_map('trim', explode(';', $sqlContent)));
                foreach ($queries as $query) {
                    if (!empty($query)) {
                        $testPdo->exec($query);
                    }
                }
                
                // Connection successful, create config file
                $configContent = <<<EOT
<?php
// Database configuration
define('DB_HOST', '{$dbHost}');
define('DB_NAME', '{$dbName}');
define('DB_USER', '{$dbUser}');
define('DB_PASS', '{$dbPass}');

// Error logging configuration
define('LOG_DIR', dirname(__DIR__) . '/logs');
define('DEBUG_MODE', false);

// Password reset configuration
define('PASSWORD_RESET_EXPIRY_HOURS', 24); // Token validity in hours

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

try {
    \$pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
} catch (PDOException \$e) {
    // Log the error but don't display details publicly
    error_log("Database connection failed: " . \$e->getMessage());
    die("A database error occurred. Please try again later.");
}

// Function to safely log errors
function logError(\$message) {
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    error_log(date('Y-m-d H:i:s') . " - " . \$message . "\\n", 3, LOG_DIR . '/error.log');
}
EOT;

                // Write the config file
                if (file_put_contents(__DIR__ . '/config.php', $configContent)) {
                    // Check if there are any admin users
                    $checkAdmin = $testPdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
                    $adminCount = $checkAdmin->fetch()['count'];

                    if ($adminCount === 0) {
                        $_SESSION['db_configured'] = true;
                        $_SESSION['install_phase'] = 'admin_creation';
                        $message = "Database configuration successful! Please create an admin user.";
                        // Force redirect to admin creation form
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    } else {
                        $message = "Installation completed successfully! Database initialized and configuration file created. <a href='/' class='btn'>Go to Homepage</a>";
                    }
                } else {
                    $error = "Failed to write configuration file. Please check file permissions.";
                }
                
            } catch (Exception $e) {
                $error = "Database import failed: " . htmlspecialchars($e->getMessage());
            }
            
        } catch (PDOException $e) {
            $error = "Database connection failed: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle admin user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required for admin user creation.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        try {
            require_once __DIR__ . '/config.php';
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
            if ($stmt->execute([$username, $email, $hashedPassword])) {
                // Clear all installation session variables
                unset($_SESSION['db_configured']);
                unset($_SESSION['install_phase']);
                session_write_close();
                
                $message = "Admin user created successfully! <a href='/' class='btn'>Go to Homepage</a>";
            } else {
                $error = "Failed to create admin user.";
            }
        } catch (PDOException $e) {
            $error = "Error creating admin user: " . ($e->getCode() === '23000' ? "Username or email already exists." : "An error occurred.");
        }
    }
}

// Rest of the installation form
if (!isset($_SESSION['db_configured']) && !isset($_SESSION['install_phase'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Installation</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .install-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .error-message {
            background: #fee;
            color: #c00;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .success-message {
            background: #efe;
            color: #0c0;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <h1>CRM System Installation</h1>
        
        <?php if ($error): ?>
        <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
        <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (!$message || (strpos($message, "Please create an admin user") !== false)): ?>
        <form method="post" action="">
            <div class="form-group">
                <label for="db_host">Database Host:</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_name">Database Name:</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_user">Database Username:</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_pass">Database Password:</label>
                <input type="password" id="db_pass" name="db_pass">
            </div>
            
            <button type="submit" class="btn">Install</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
} else if (isset($_SESSION['db_configured']) || isset($_SESSION['install_phase'])) {
    // Admin user creation form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Create Admin User - Rey CRM Installation</title>
        <link rel="stylesheet" href="/assets/css/style.css">
        <style>
            .install-container {
                max-width: 600px;
                margin: 2rem auto;
                padding: 2rem;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .form-group {
                margin-bottom: 1.5rem;
            }
            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: bold;
            }
            .form-group input {
                width: 100%;
                padding: 0.5rem;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .error-message {
                background: #fee;
                color: #c00;
                padding: 1rem;
                border-radius: 4px;
                margin-bottom: 1rem;
            }
            .success-message {
                background: #efe;
                color: #0c0;
                padding: 1rem;
                border-radius: 4px;
                margin-bottom: 1rem;
            }
            .btn {
                background: #007bff;
                color: white;
                padding: 0.5rem 1rem;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                width: 100%;
            }
            .btn:hover {
                background: #0056b3;
            }
        </style>
    </head>
    <body>
        <div class="install-container">
            <h1>Create Admin User</h1>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="success-message"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!$message || strpos($message, "Admin user created successfully") === false): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required 
                               minlength="8" placeholder="Minimum 8 characters">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               required minlength="8" placeholder="Repeat password">
                    </div>
                    
                    <button type="submit" name="create_admin" class="btn">
                        Create Admin User
                    </button>
                </form>
                
                <div style="margin-top: 20px; text-align: center;">
                    <a href="?reset_install=1" style="color: #666; font-size: 0.9em;">Reset installation</a>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>