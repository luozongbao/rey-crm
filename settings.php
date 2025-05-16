<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

requireAdmin(); // Only admins can access settings

require_once 'includes/header.php';

// Handle form submission for pagination settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pagination'])) {
    $items_per_page = intval($_POST['items_per_page']);
    if ($items_per_page > 0) {
        $sql = "UPDATE settings SET value = ? WHERE setting_name = 'items_per_page'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $items_per_page);
        $stmt->execute();
        $message = "Pagination settings updated successfully!";
    }
}

// Handle user management form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_user'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    
    if ($_POST['action'] === 'add') {
        $sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssss', $username, $email, $password, $role);
        $stmt->execute();
        $message = "User added successfully!";
    }
}

// Get current settings
$sql = "SELECT value FROM settings WHERE setting_name = 'items_per_page'";
$result = $conn->query($sql);
$items_per_page = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['value'] : 10;

// Get users list
$sql = "SELECT user_id, username, email, role FROM users ORDER BY username";
$users = $conn->query($sql);
?>

<h2>System Settings</h2>

<?php if (isset($message)): ?>
    <div class="message success"><?php echo htmlspecialchars($message); ?></div>
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
        <button type="submit" name="update_pagination" class="btn btn-primary">Update Pagination</button>
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
        <button type="submit" class="btn btn-primary">Add User</button>
    </form>

    <?php if ($users && $users->num_rows > 0): ?>
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
            <?php while ($user = $users->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['role']); ?></td>
                <td>
                    <button class="btn btn-small btn-edit" 
                            data-id="<?php echo $user['user_id']; ?>">Edit</button>
                    <button class="btn btn-small btn-delete" 
                            data-id="<?php echo $user['user_id']; ?>">Delete</button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
