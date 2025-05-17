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

// Get current settings
try {
    $stmt = $pdo->query("SELECT value FROM settings WHERE setting_name = 'items_per_page'");
    $items_per_page = ($stmt && $stmt->rowCount() > 0) ? $stmt->fetch(PDO::FETCH_ASSOC)['value'] : 10;
    
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

<div class="settings-section">
    <h3>Pagination Settings</h3>
    <form method="POST" action="">
        <div class="form-group">
            <label for="items_per_page">Items per page:</label>
            <input type="number" id="items_per_page" name="items_per_page" 
                   value="<?php echo htmlspecialchars($items_per_page); ?>" 
                   min="1" max="100" required>
        </div>
        <button type="submit" name="update_pagination" class="btn">Update Pagination</button>
    </form>
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
                        <button class="btn btn-small btn-danger" 
                                onclick="deleteUser(<?php echo $user['user_id']; ?>)">Delete</button>
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
</style>

<?php require_once 'includes/footer.php'; ?>
