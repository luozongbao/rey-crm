<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = __('email_projects');
$current_page = 'email_projects';

// Handle delete action
if (isset($_POST['delete_id'])) {
    $project_id = (int)$_POST['delete_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM email_projects WHERE project_id = ?");
        $stmt->execute([$project_id]);
        
        header('Location: email_projects.php?message=' . urlencode(__('email_project_deleted_successfully')));
        exit;
    } catch (PDOException $e) {
        error_log("Error deleting email project: " . $e->getMessage());
        $error = __('error_deleting_email_project');
    }
}

// Get all email projects
try {
    $stmt = $pdo->prepare("SELECT * FROM email_projects ORDER BY created_at DESC");
    $stmt->execute();
    $email_projects = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching email projects: " . $e->getMessage());
    $error = __('error_fetching_email_projects');
}

include 'includes/header.php';
?>

<div class="container">
    <div class="header">
        <h1><?= __('email_projects') ?></h1>
        <div class="header-actions">
            <a href="email_project_form.php" class="btn btn-primary">
                <span class="btn-icon">+</span>
                <?= __('create_project') ?>
            </a>
            <a href="email_history.php" class="btn btn-secondary">
                <span class="btn-icon">📧</span>
                <?= __('email_history') ?>
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
                    <h3><?= __('no_email_projects_found') ?></h3>
                    <p><?= __('create_first_email_project') ?></p>
                    <a href="email_project_form.php" class="btn btn-primary"><?= __('create_project') ?></a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th><?= __('project_name') ?></th>
                                <th><?= __('subject') ?></th>
                                <th><?= __('cc') ?></th>
                                <th><?= __('created') ?></th>
                                <th><?= __('actions') ?></th>
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
                                    <td><?php echo formatDateTimeCompact($project['created_at']); ?></td>
                                    <td class="actions">
                                        <a href="email_project_form.php?id=<?php echo $project['project_id']; ?>" 
                                           class="btn btn-sm btn-secondary" title="<?= __('edit') ?>">
                                            <?= __('edit') ?>
                                        </a>
                                        <a href="send_email.php?project_id=<?php echo $project['project_id']; ?>" 
                                           class="btn btn-sm btn-primary" title="<?= __('send_email') ?>">
                                            <?= __('send') ?>
                                        </a>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('<?= __('confirm_delete_email_project') ?>')">
                                            <input type="hidden" name="delete_id" value="<?php echo $project['project_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="<?= __('delete') ?>">
                                                <?= __('delete') ?>
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
