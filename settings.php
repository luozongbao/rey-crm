<?php

require_once 'includes/functions.php';
session_start();

requireAdmin(); // Only admins can access settings

$message = '';
$error = '';

// Handle form submission for pagination settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pagination'])) {
    $items_per_page = intval($_POST['items_per_page']);
    if ($items_per_page > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE setting_name = 'items_per_page'");
            $stmt->execute([$items_per_page]);
            $message = "Pagination settings updated successfully!";
        } catch (PDOException $e) {
            $error = "Failed to update settings: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle user management form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    
    try {
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $password, $role])) {
                $message = "User added successfully!";
            }
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            $error = "Username or email already exists.";
        } else {
            $error = "Failed to add user: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle delete user action
if (isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    
    // Prevent deleting your own account
    if ($user_id === $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            if ($stmt->execute([$user_id])) {
                $message = "User deleted successfully!";
            } else {
                $error = "Failed to delete user.";
            }
        } catch (PDOException $e) {
            $error = "Error deleting user: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Handle SMTP settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_smtp'])) {
    $smtp_host = trim($_POST['smtp_host']);
    $smtp_port = intval($_POST['smtp_port']);
    $smtp_username = trim($_POST['smtp_username']);
    $smtp_from_email = trim($_POST['smtp_from_email']);
    $smtp_from_name = trim($_POST['smtp_from_name']);
    $smtp_encryption = $_POST['smtp_encryption'];
    
    // Only update password if provided
    $smtp_password = !empty($_POST['smtp_password']) ? trim($_POST['smtp_password']) : '';
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update or insert each SMTP setting
        $fields = [
            'smtp_host' => $smtp_host,
            'smtp_port' => $smtp_port,
            'smtp_username' => $smtp_username,
            'smtp_from_email' => $smtp_from_email,
            'smtp_from_name' => $smtp_from_name,
            'smtp_encryption' => $smtp_encryption
        ];
        
        // Only update password if a new one was provided
        if (!empty($smtp_password)) {
            $fields['smtp_password'] = $smtp_password;
        }
        
        foreach ($fields as $setting_name => $value) {
            // Check if setting exists
            $stmt = $pdo->prepare("SELECT setting_id FROM settings WHERE setting_name = ?");
            $stmt->execute([$setting_name]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing setting
                $stmt = $pdo->prepare("UPDATE settings SET value = ?, updated_at = NOW() WHERE setting_name = ?");
                $stmt->execute([$value, $setting_name]);
            } else {
                // Insert new setting
                $stmt = $pdo->prepare("INSERT INTO settings (setting_name, value) VALUES (?, ?)");
                $stmt->execute([$setting_name, $value]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        $message = "SMTP settings updated successfully!";
    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        $error = "Failed to update SMTP settings: " . htmlspecialchars($e->getMessage());
    }
}

// Handle timezone settings form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_timezone'])) {
    $timezone = trim($_POST['timezone']);
    if (in_array($timezone, DateTimeZone::listIdentifiers())) {
        try {
            // Check if setting exists
            $stmt = $pdo->prepare("SELECT setting_id FROM settings WHERE setting_name = 'timezone'");
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Update existing setting
                $stmt = $pdo->prepare("UPDATE settings SET value = ?, updated_at = NOW() WHERE setting_name = 'timezone'");
                $stmt->execute([$timezone]);
            } else {
                // Insert new setting
                $stmt = $pdo->prepare("INSERT INTO settings (setting_name, value) VALUES ('timezone', ?)");
                $stmt->execute([$timezone]);
            }
            
            $message = "Timezone setting updated successfully!";
        } catch (PDOException $e) {
            $error = "Failed to update timezone setting: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = "Invalid timezone selected.";
    }
}

// Get current settings
try {
    // Get pagination settings
    $stmt = $pdo->query("SELECT value FROM settings WHERE setting_name = 'items_per_page'");
    $items_per_page = ($stmt && $stmt->rowCount() > 0) ? $stmt->fetch(PDO::FETCH_ASSOC)['value'] : 10;
    
    // Get SMTP settings
    $smtp_settings = [];
    $smtp_fields = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name', 'smtp_encryption'];
    
    foreach ($smtp_fields as $field) {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE setting_name = ?");
        $stmt->execute([$field]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $smtp_settings[$field] = ($result) ? $result['value'] : '';
    }
    
    // Set defaults if empty
    if (empty($smtp_settings['smtp_port'])) $smtp_settings['smtp_port'] = 587;
    if (empty($smtp_settings['smtp_encryption'])) $smtp_settings['smtp_encryption'] = 'tls';
    
    // Get timezone setting
    $stmt = $pdo->query("SELECT value FROM settings WHERE setting_name = 'timezone'");
    $current_timezone = ($stmt && $stmt->rowCount() > 0) ? $stmt->fetch(PDO::FETCH_ASSOC)['value'] : 'UTC';
    
    // Get users list
    $stmt = $pdo->query("SELECT user_id, username, email, role FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to fetch settings: " . htmlspecialchars($e->getMessage());
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="header">
        <h1>System Settings</h1>
        <p>Manage system settings and user accounts.</p>
    </div>


    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

<div class="settings-row" style="display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 20px;">
    <div class="settings-section" style="flex:1 1 320px; min-width:300px;">
        <h3>Pagination Settings</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label for="items_per_page">Items per page:</label>
                <input type="number" id="items_per_page" name="items_per_page" 
                       value="<?php echo htmlspecialchars(
                           $items_per_page); ?>" 
                       min="1" max="100" required>
            </div>
            <button type="submit" name="update_pagination" class="btn">Update Pagination</button>
        </form>
    </div>

    <div class="settings-section" style="flex:1 1 320px; min-width:300px;">
        <h3>Timezone Settings</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label for="timezone">System Timezone:</label>
                <select id="timezone" name="timezone" required>
                    <?php
                    $timezones = DateTimeZone::listIdentifiers();
                    foreach ($timezones as $tz) {
                        $selected = ($tz === $current_timezone) ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($tz) . "\" $selected>" . htmlspecialchars($tz) . "</option>";
                    }
                    ?>
                </select>
                <small class="help-text">This timezone will be used for displaying dates and times throughout the system.</small>
            </div>
            <button type="submit" name="update_timezone" class="btn">Update Timezone</button>
        </form>
    </div>
</div>

<div class="settings-section">
    <h3>Email Settings</h3>
    <form method="POST" action="">
        <div class="form-row">
            <div class="form-group half-width">
                <label for="smtp_host">SMTP Host:</label>
                <input type="text" id="smtp_host" name="smtp_host" 
                       value="<?php echo htmlspecialchars($smtp_settings['smtp_host']); ?>" 
                       placeholder="e.g., smtp.gmail.com" required>
            </div>
            
            <div class="form-group half-width">
                <label for="smtp_port">SMTP Port:</label>
                <input type="number" id="smtp_port" name="smtp_port" 
                       value="<?php echo htmlspecialchars($smtp_settings['smtp_port']); ?>" 
                       placeholder="e.g., 587" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group half-width">
                <label for="smtp_username">SMTP Username:</label>
                <input type="text" id="smtp_username" name="smtp_username" 
                       value="<?php echo htmlspecialchars($smtp_settings['smtp_username']); ?>" 
                       placeholder="Email address or username" required>
            </div>
            
            <div class="form-group half-width">
                <label for="smtp_password">SMTP Password:</label>
                <input type="password" id="smtp_password" name="smtp_password" 
                       placeholder="<?php echo !empty($smtp_settings['smtp_password']) ? 'Leave blank to keep current password' : 'Enter SMTP password'; ?>">
                <small>Leave blank to keep existing password</small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group half-width">
                <label for="smtp_from_email">From Email:</label>
                <input type="email" id="smtp_from_email" name="smtp_from_email" 
                       value="<?php echo htmlspecialchars($smtp_settings['smtp_from_email']); ?>" 
                       placeholder="noreply@yourcompany.com" required>
            </div>
            
            <div class="form-group half-width">
                <label for="smtp_from_name">From Name:</label>
                <input type="text" id="smtp_from_name" name="smtp_from_name" 
                       value="<?php echo htmlspecialchars($smtp_settings['smtp_from_name']); ?>" 
                       placeholder="Rey CRM" required>
            </div>
        </div>
        
        <div class="form-group">
            <label for="smtp_encryption">Encryption:</label>
            <select id="smtp_encryption" name="smtp_encryption" required>
                <option value="tls" <?php echo $smtp_settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                <option value="ssl" <?php echo $smtp_settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                <option value="none" <?php echo $smtp_settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
            </select>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="update_smtp" class="btn">Update SMTP Settings</button>
            <button type="button" id="test-email-btn" class="btn ghost">Test Email Settings</button>
        </div>
    </form>
    
    <!-- Test Email Modal -->
    <div id="test-email-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h4>Send Test Email</h4>
            <form id="test-email-form">
                <div class="form-group">
                    <label for="test-email">Recipient Email:</label>
                    <input type="email" id="test-email" name="test_email" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Send Test Email</button>
                    <div id="test-email-result"></div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="settings-section">
    <h3>User Management</h3>
    <form method="POST" action="" class="user-form">
        <input type="hidden" name="manage_user" value="1">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div class="form-group">
            <label for="role">Role:</label>
            <select id="role" name="role" required>
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <input type="hidden" name="action" value="add">
        <button type="submit" class="btn">Add User</button>
    </form>

    <?php if (!empty($users)): ?>
    <hr class="section-divider">
    <div class="users-list">
        <h4>Current Users</h4>
        <table class="users-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td>
                        <button class="btn btn-small" 
                                onclick="editUser(<?php echo $user['user_id']; ?>)">Edit</button>
                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                        <button class="btn btn-small btn-danger" 
                                onclick="deleteUser(<?php echo $user['user_id']; ?>)">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.settings-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.settings-row {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.btn {
    background: #007bff;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
}

.btn:hover {
    background: #0056b3;
}

.btn-danger {
    background: #dc3545;
}

.btn-danger:hover {
    background: #c82333;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.users-table th,
.users-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.users-table th {
    background: #f8f9fa;
    font-weight: bold;
}

.btn-small {
    padding: 4px 8px;
    margin-right: 4px;
}

.error-message {
    background: #fee;
    color: #c00;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.success-message {
    background: #efe;
    color: #0c0;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.section-divider {
    margin: 30px 0;
    border: 0;
    height: 1px;
    background-color: #ddd;
}

.users-list {
    margin-top: 30px;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.4);
}

.modal-content {
    position: relative;
    background-color: white;
    margin: 10% auto;
    padding: 20px;
    border-radius: var(--radius);
    box-shadow: var(--shadow-md);
    width: 500px;
    max-width: 90%;
    animation: modalFadeIn 0.3s;
}

@keyframes modalFadeIn {
    from {opacity: 0; transform: translateY(-20px);}
    to {opacity: 1; transform: translateY(0);}
}

.close-modal {
    position: absolute;
    right: 20px;
    top: 15px;
    color: var(--gray-500);
    font-size: 24px;
    cursor: pointer;
}

.close-modal:hover {
    color: var(--gray-700);
}

#test-email-result {
    margin-top: 15px;
    padding: 10px;
    border-radius: var(--radius-sm);
    display: none;
}

#test-email-result.success {
    background-color: var(--success-light);
    color: var(--success);
}

#test-email-result.error {
    background-color: var(--danger-light);
    color: var(--danger);
}
</style>

<script>
function deleteUser(userId) {
    // Don't allow deleting the current user
    if (userId == <?php echo $_SESSION['user_id']; ?>) {
        alert("You cannot delete your own account.");
        return;
    }
    
    if (confirm("Are you sure you want to delete this user?")) {
        // Redirect to a user delete handler
        window.location.href = "settings.php?action=delete_user&user_id=" + userId;
    }
}

function editUser(userId) {
    // Redirect to a user edit page or show modal
    window.location.href = "settings.php?action=edit_user&user_id=" + userId;
}

// Email testing functionality
document.addEventListener('DOMContentLoaded', function() {
    const testEmailBtn = document.getElementById('test-email-btn');
    const testEmailModal = document.getElementById('test-email-modal');
    const closeModalBtn = document.querySelector('.close-modal');
    const testEmailForm = document.getElementById('test-email-form');
    const testEmailResult = document.getElementById('test-email-result');
    
    // Open modal
    testEmailBtn.addEventListener('click', function() {
        testEmailModal.style.display = 'block';
    });
    
    // Close modal
    closeModalBtn.addEventListener('click', function() {
        testEmailModal.style.display = 'none';
        testEmailResult.style.display = 'none';
        testEmailForm.reset();
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === testEmailModal) {
            testEmailModal.style.display = 'none';
            testEmailResult.style.display = 'none';
            testEmailForm.reset();
        }
    });
    
    // Handle test email form submission
    testEmailForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const testEmail = document.getElementById('test-email').value;
        testEmailResult.className = '';
        testEmailResult.textContent = 'Sending test email...';
        testEmailResult.style.display = 'block';
        
        // Send AJAX request to test email
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'includes/email_test.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        testEmailResult.className = 'success';
                        testEmailResult.textContent = 'Test email sent successfully!';
                    } else {
                        testEmailResult.className = 'error';
                        testEmailResult.textContent = 'Error: ' + response.message;
                    }
                } catch (e) {
                    testEmailResult.className = 'error';
                    testEmailResult.textContent = 'Error: Invalid response from server';
                }
            } else {
                testEmailResult.className = 'error';
                testEmailResult.textContent = 'Error: Server returned status ' + xhr.status;
            }
        };
        
        xhr.onerror = function() {
            testEmailResult.className = 'error';
            testEmailResult.textContent = 'Error: Network error occurred';
        };
        
        xhr.send('test_email=' + encodeURIComponent(testEmail));
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
