<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

requireLogin();

$page_title = __('email_history');
$current_page = 'email_history';

// Pagination settings
$items_per_page = getItemsPerPage();
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];

// ACCESS CONTROL: Users can only see their own email history, admins can see all
$user_condition = '';
$user_params = [];

if (!isAdmin()) {
    $user_condition = "AND h.user_id = ?";
    $user_params = [$_SESSION['user_id']];
}

if (!empty($search)) {
    $search_condition = "WHERE (h.to_email LIKE ? OR h.cc LIKE ? OR p.project_name LIKE ? OR h.subject LIKE ?)";
    $search_params = ["%$search%", "%$search%", "%$search%", "%$search%"];
    
    // If we have user condition, combine it with search
    if (!empty($user_condition)) {
        $search_condition = $search_condition . " " . $user_condition;
        $search_params = array_merge($search_params, $user_params);
    }
} else if (!empty($user_condition)) {
    // Only user condition, no search
    $search_condition = "WHERE " . substr($user_condition, 4); // Remove "AND " prefix
    $search_params = $user_params;
}

try {
    // Get total count for pagination
    $count_query = "
        SELECT COUNT(*) as total 
        FROM sent_email_history h 
        LEFT JOIN email_projects p ON h.project_id = p.project_id 
        LEFT JOIN users u ON h.user_id = u.user_id
        $search_condition
    ";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($search_params);
    $total_count = $stmt->fetch()['total'];
    $total_pages = ceil($total_count / $items_per_page);

    // Get email history with pagination
    $query = "
        SELECT h.*, p.project_name, u.username as sent_by_username
        FROM sent_email_history h 
        LEFT JOIN email_projects p ON h.project_id = p.project_id 
        LEFT JOIN users u ON h.user_id = u.user_id
        $search_condition
        ORDER BY h.sent_datetime DESC 
        LIMIT ? OFFSET ?
    ";
    $params = array_merge($search_params, [$items_per_page, $offset]);
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $email_history = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error fetching email history: " . $e->getMessage());
    $error = __('error_fetching_email_history');
    $email_history = [];
    $total_pages = 0;
}

include 'includes/header.php';
?>

<div class="container">
    <div class="header">
        <h1><?php echo __('email_history'); ?></h1>
        <a href="email_projects.php" class="btn btn-secondary">
            <?php echo __('back_to_projects'); ?>
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Search Form -->
    <div class="card">
        <div class="card-body">
            <form method="GET" class="form search-form">
                <div class="form-row">
                    <div class="form-group flex-grow">
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="<?php echo __('search_by_recipient_cc_project_subject'); ?>" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary"><?php echo __('search'); ?></button>
                        <?php if (!empty($search)): ?>
                            <a href="email_history.php" class="btn btn-secondary"><?php echo __('clear'); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Summary -->
    <div class="results-info">
        <p>
            <?php if (!empty($search)): ?>
                <?php echo __('found'); ?> <?php echo $total_count; ?> <?php echo __('result'); ?><?php echo $total_count !== 1 ? __('s') : ''; ?> <?php echo __('for'); ?> "<?php echo htmlspecialchars($search); ?>"
            <?php else: ?>
                <?php echo __('showing'); ?> <?php echo $total_count; ?> <?php echo __('total'); ?> <?php echo __('email'); ?><?php echo $total_count !== 1 ? __('s') : ''; ?>
            <?php endif; ?>
        </p>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($email_history)): ?>
                <div class="empty-state">
                    <?php if (!empty($search)): ?>
                        <h3><?php echo __('no_results_found'); ?></h3>
                        <p><?php echo __('no_emails_found_matching_search'); ?></p>
                        <a href="email_history.php" class="btn btn-secondary"><?php echo __('clear_search'); ?></a>
                    <?php else: ?>
                        <h3><?php echo __('no_email_history'); ?></h3>
                        <p><?php echo __('no_emails_sent_yet'); ?></p>
                        <a href="email_projects.php" class="btn btn-primary"><?php echo __('create_email_project'); ?></a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                                                <thead>
                            <tr>
                                <th class="datetime-col"><?php echo __('date_time'); ?></th>
                                <th><?php echo __('to'); ?></th>
                                <th><?php echo __('cc'); ?></th>
                                <th><?php echo __('project_name'); ?></th>
                                <?php if (isAdmin()): ?>
                                <th><?php echo __('sent_by'); ?></th>
                                <?php endif; ?>
                                <th class="attachments-col"><?php echo __('attachments'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($email_history as $email): ?>
                                <tr>
                                    <td class="datetime-col">
                                        <div class="datetime-compact">
                                            <?php echo formatDateTimeCompact($email['sent_datetime']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="email-address">
                                            <?php echo htmlspecialchars($email['to_email']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($email['cc'])): ?>
                                            <div class="email-address cc-emails">
                                                <?php echo htmlspecialchars($email['cc']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($email['project_name']): ?>
                                            <a href="email_project_form.php?id=<?php echo $email['project_id']; ?>" class="project-link">
                                                <strong><?php echo htmlspecialchars($email['project_name']); ?></strong>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted"><?php echo __('project_deleted'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if (isAdmin()): ?>
                                    <td>
                                        <?php if ($email['sent_by_username']): ?>
                                            <span class="user-badge">
                                                <?php echo htmlspecialchars($email['sent_by_username']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted"><?php echo __('unknown_user'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if (!empty($email['attachments'])): ?>
                                            <?php
                                            $attachments = json_decode($email['attachments'], true);
                                            if (is_array($attachments) && !empty($attachments)):
                                            ?>
                                                <div class="attachments">
                                                    <span class="attachment-count">
                                                        <?php echo count($attachments); ?> <?php echo __('file'); ?><?php echo count($attachments) > 1 ? __('s') : ''; ?>
                                                    </span>
                                                    <div class="attachment-tooltip">
                                                        <ul>
                                                            <?php foreach ($attachments as $attachment): ?>
                                                                <li><?php echo htmlspecialchars(basename($attachment)); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo buildQueryString(['page' => 1]); ?>" class="btn"><?php echo __('first'); ?></a>
                                <a href="?<?php echo buildQueryString(['page' => $page - 1]); ?>" class="btn"><?php echo __('previous'); ?></a>
                            <?php endif; ?>
                            
                            <?php 
                            // Show page numbers
                            $startPage = max(1, $page - 2);
                            $endPage = min($total_pages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <a href="?<?php echo buildQueryString(['page' => $i]); ?>" 
                                   class="btn <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo buildQueryString(['page' => $page + 1]); ?>" class="btn"><?php echo __('next'); ?></a>
                                <a href="?<?php echo buildQueryString(['page' => $total_pages]); ?>" class="btn"><?php echo __('last'); ?></a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="records-count">
                            <?php
                            // Calculate the current record range
                            $startRecord = ($page - 1) * $items_per_page + 1;
                            $endRecord = min($page * $items_per_page, $total_count);
                            echo __('showing') . " $startRecord - $endRecord " . __('of') . " $total_count " . __('records');
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.search-form .form-row {
    display: flex;
    gap: 10px;
    align-items: end;
}

.search-form .flex-grow {
    flex-grow: 1;
}

.results-info {
    margin: 15px 0;
    color: #6c757d;
}

.datetime {
    white-space: nowrap;
}

.datetime .date {
    font-weight: 500;
}

.datetime .time {
    font-size: 0.85em;
    color: #6c757d;
}

.email-address {
    font-family: monospace;
    font-size: 0.9em;
    word-break: break-all;
}

.cc-emails {
    font-size: 0.8em;
    color: #6c757d;
}

.subject {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.attachments {
    position: relative;
    cursor: help;
}

.attachment-count {
    color: #007bff;
    text-decoration: underline;
    text-decoration-style: dotted;
}

.attachment-tooltip {
    display: none;
    position: absolute;
    top: -5px;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    white-space: nowrap;
    z-index: 1000;
    font-size: 0.85em;
}

.attachment-tooltip ul {
    margin: 0;
    padding: 0;
    list-style: none;
}

.attachments:hover .attachment-tooltip {
    display: block;
}

.text-muted {
    color: #6c757d !important;
}
</style>

<?php include 'includes/footer.php'; ?>
