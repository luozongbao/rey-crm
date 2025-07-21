<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = 'Email Projects';
$current_page = 'email_projects';

// Handle delete action
if (isset($_POST['delete_id'])) {
    $project_id = (int)$_POST['delete_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM email_projects WHERE project_id = ?");
        $stmt->execute([$project_id]);
        
        header('Location: email_projects.php?message=' . urlencode('Email project deleted successfully'));
        exit;
    } catch (PDOException $e) {
        error_log("Error deleting email project: " . $e->getMessage());
        $error = "Error deleting email project.";
    }
}

// Get all email projects
try {
    $stmt = $pdo->prepare("SELECT * FROM email_projects ORDER BY created_at DESC");
    $stmt->execute();
    $email_projects = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching email projects: " . $e->getMessage());
    $error = "Error fetching email projects.";
}

include 'includes/header.php';
?>

<div class="container">
    <div class="header">
        <h1>Email Projects</h1>
        <div class="header-actions">
            <a href="email_project_form.php" class="btn btn-primary">
                <span class="btn-icon">+</span>
                Create Project
            </a>
            <a href="email_history.php" class="btn btn-secondary">
                <span class="btn-icon">📧</span>
                Email History
            </a>
        </div>
    </div>

    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_GET['message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if (empty($email_projects)): ?>
                <div class="empty-state">
                    <h3>No Email Projects Found</h3>
                    <p>Create your first email project template to get started.</p>
                    <a href="email_project_form.php" class="btn btn-primary">Create Project</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Subject</th>
                                <th>CC</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($email_projects as $project): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($project['project_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($project['cc'] ?: '-'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($project['created_at'])); ?></td>
                                    <td class="actions">
                                        <a href="email_project_form.php?id=<?php echo $project['project_id']; ?>" 
                                           class="btn btn-sm btn-secondary" title="Edit">
                                            Edit
                                        </a>
                                        <a href="send_email.php?project_id=<?php echo $project['project_id']; ?>" 
                                           class="btn btn-sm btn-primary" title="Send Email">
                                            Send
                                        </a>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this email project?')">
                                            <input type="hidden" name="delete_id" value="<?php echo $project['project_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
