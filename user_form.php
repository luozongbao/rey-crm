<?php
require_once 'includes/functions.php';
session_start();

requireAdmin(); // Only admins can manage users

$page_title = 'User Management';
$current_page = 'settings';

$message = '';
$error = '';
$user = null;
$is_edit = false;

// Determine if we're editing or adding
$is_current_user = false;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $is_edit = true;
    
    // Check if editing current user
    $is_current_user = ($user_id === $_SESSION['user_id']);
    
    // Get user data
    try {
        $stmt = $pdo->prepare("SELECT user_id, username, email, role FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $error = "User not found.";
            $is_edit = false;
        }
    } catch (PDOException $e) {
        $error = "Error retrieving user: " . htmlspecialchars($e->getMessage());
        $is_edit = false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user']) && $is_edit && !$is_current_user) {
        // Handle delete user (only for other users)
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            if ($stmt->execute([$user_id])) {
                $_SESSION['message'] = "User deleted successfully!";
                header('Location: settings.php');
                exit;
            } else {
                $error = "Failed to delete user.";
            }
        } catch (PDOException $e) {
            $error = "Error deleting user: " . htmlspecialchars($e->getMessage());
        }
    } elseif (isset($_POST['send_reset'])) {
        // Handle send password reset using centralized function
        $reset_email = trim($_POST['reset_email']);
        
        if (!empty($reset_email)) {
            $reset_result = sendPasswordResetEmail($reset_email, $user['username'], $user_id);
            if ($reset_result['success']) {
                $message = "Password reset email sent to " . htmlspecialchars($reset_email);
            } else {
                $error = $reset_result['message'];
            }
        } else {
            $error = "Email address is required.";
        }
    } elseif (isset($_POST['save_user'])) {
        // Handle save user (add or edit)
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        
        // For current user editing, require password confirmation for email changes
        if ($is_current_user && $email !== $user['email']) {
            $current_password = $_POST['current_password'] ?? '';
            if (empty($current_password)) {
                $error = "Current password is required to change email address.";
            } else {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $stored_password = $stmt->fetchColumn();
                
                if (!password_verify($current_password, $stored_password)) {
                    $error = "Current password is incorrect.";
                }
            }
        }
        
        if (empty($error)) {
            if ($is_edit) {
                // Update existing user
                try {
                    // For current user, don't allow role changes
                    if ($is_current_user) {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
                        $success = $stmt->execute([$username, $email, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE user_id = ?");
                        $success = $stmt->execute([$username, $email, $role, $user_id]);
                    }
                    
                    if ($success) {
                        $message = "User updated successfully!";
                        // Refresh user data
                        $stmt = $pdo->prepare("SELECT user_id, username, email, role FROM users WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // If current user updated their own info, update session
                        if ($is_current_user) {
                            $_SESSION['username'] = $user['username'];
                        }
                    } else {
                        $error = "Failed to update user.";
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) { // Duplicate entry
                        $error = "Username or email already exists.";
                    } else {
                        $error = "Failed to update user: " . htmlspecialchars($e->getMessage());
                    }
                }
            } else {
                // Add new user
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                    if ($stmt->execute([$username, $email, $password, $role])) {
                        $_SESSION['message'] = "User added successfully!";
                        header('Location: settings.php');
                        exit;
                    } else {
                        $error = "Failed to add user.";
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) { // Duplicate entry
                        $error = "Username or email already exists.";
                    } else {
                        $error = "Failed to add user: " . htmlspecialchars($e->getMessage());
                    }
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="header">
        <h1><?php echo $is_edit ? 'Edit User' : 'Add User'; ?></h1>
        <a href="settings.php" class="btn btn-secondary">Back to Settings</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="" class="user-form">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           value="<?php echo $is_edit ? htmlspecialchars($user['username']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?php echo $is_edit ? htmlspecialchars($user['email']) : ''; ?>" 
                           required>
                </div>
                
                <?php if (!$is_edit): ?>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <?php endif; ?>
                
                <?php if ($is_current_user && $is_edit): ?>
                <div class="form-group">
                    <label for="current_password">Current Password (required for email changes):</label>
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           placeholder="Enter current password to change email">
                    <small class="form-note">Only required if you change your email address</small>
                </div>
                <?php endif; ?>
                
                <?php if (!$is_current_user): ?>
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="user" <?php echo ($is_edit && $user['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                        <option value="admin" <?php echo ($is_edit && $user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" name="save_user" class="btn btn-primary">
                        <?php echo $is_edit ? 'Update User' : 'Add User'; ?>
                    </button>
                </div>
            </form>

            <?php if ($is_edit): ?>
                <!-- Password Reset Section -->
                <div class="section-divider"></div>
                <div class="password-reset-section">
                    <h3>Password Reset</h3>
                    <p>Send a password reset link to <?php echo htmlspecialchars($user['email']); ?></p>
                    <form method="POST" action="" class="reset-form">
                        <input type="hidden" name="reset_email" value="<?php echo htmlspecialchars($user['email']); ?>">
                        <button type="submit" name="send_reset" class="btn btn-warning">
                            Send Password Reset Link
                        </button>
                    </form>
                </div>

                <!-- Delete User Section -->
                <?php if (!$is_current_user): ?>
                <div class="section-divider"></div>
                <div class="delete-user-section">
                    <h3>Delete User</h3>
                    <p class="delete-warning">This action cannot be undone. All user data will be permanently removed.</p>
                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                        <button type="submit" name="delete_user" class="btn btn-danger delete-user-btn">
                            Delete User
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.user-form .form-group {
    margin-bottom: 1.5rem;
}

.user-form label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.user-form input,
.user-form select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

.user-form input:focus,
.user-form select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.form-actions {
    margin-top: 2rem;
}

.section-divider {
    margin: 2rem 0;
    border: 0;
    border-top: 1px solid #eee;
}

.password-reset-section {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid #eee;
}

.password-reset-section h3 {
    margin-bottom: 0.5rem;
    color: #666;
}

.password-reset-section p {
    margin-bottom: 1rem;
    color: #666;
    font-size: 0.9rem;
}

.reset-form .form-group {
    margin-bottom: 1rem;
}

.delete-user-section {
    position: absolute;
    bottom: 2rem;
    left: 2rem;
}

.delete-user-btn {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
}

.delete-user-btn:hover {
    background-color: #c82333;
    border-color: #bd2130;
}

.card {
    position: relative;
    min-height: 500px;
}

/* Dark mode styles */
body.dark-mode .user-form input,
body.dark-mode .user-form select {
    background-color: var(--input-dark);
    border-color: var(--border-dark);
    color: var(--text-dark);
}

body.dark-mode .user-form input:focus,
body.dark-mode .user-form select:focus {
    border-color: var(--primary-light);
    box-shadow: 0 0 0 2px rgba(52, 144, 220, 0.25);
}

body.dark-mode .password-reset-section h3,
body.dark-mode .password-reset-section p {
    color: var(--text-dark-secondary);
}

body.dark-mode .section-divider {
    border-color: var(--border-dark);
}
</style>

<?php include 'includes/footer.php'; ?>
