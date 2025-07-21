<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = 'Email History';
$current_page = 'email_history';

// Pagination settings
$items_per_page = getItemsPerPage();
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $items_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];

if (!empty($search)) {
    $search_condition = "WHERE (h.to_email LIKE ? OR h.cc LIKE ? OR p.project_name LIKE ? OR h.subject LIKE ?)";
    $search_params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

try {
    // Get total count for pagination
    $count_query = "
        SELECT COUNT(*) as total 
        FROM sent_email_history h 
        LEFT JOIN email_projects p ON h.project_id = p.project_id 
        $search_condition
    ";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($search_params);
    $total_count = $stmt->fetch()['total'];
    $total_pages = ceil($total_count / $items_per_page);

    // Get email history with pagination
    $query = "
        SELECT h.*, p.project_name
        FROM sent_email_history h 
        LEFT JOIN email_projects p ON h.project_id = p.project_id 
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
    $error = "Error fetching email history.";
    $email_history = [];
    $total_pages = 0;
}

include 'includes/header.php';
?>

<div class="container">
    <div class="header">
        <h1>Email History</h1>
        <a href="email_projects.php" class="btn btn-secondary">
            Back to Projects
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
                               placeholder="Search by recipient, CC, project name, or subject..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="email_history.php" class="btn btn-secondary">Clear</a>
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
                Found <?php echo $total_count; ?> result<?php echo $total_count !== 1 ? 's' : ''; ?> for "<?php echo htmlspecialchars($search); ?>"
            <?php else: ?>
                Showing <?php echo $total_count; ?> total email<?php echo $total_count !== 1 ? 's' : ''; ?>
            <?php endif; ?>
        </p>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($email_history)): ?>
                <div class="empty-state">
                    <?php if (!empty($search)): ?>
                        <h3>No Results Found</h3>
                        <p>No emails found matching your search criteria.</p>
                        <a href="email_history.php" class="btn btn-secondary">Clear Search</a>
                    <?php else: ?>
                        <h3>No Email History</h3>
                        <p>No emails have been sent yet.</p>
                        <a href="email_projects.php" class="btn btn-primary">Create Email Project</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="datetime-col">Date & Time</th>
                                <th>To</th>
                                <th>CC</th>
                                <th>Project Name</th>
                                <th>Attachments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($email_history as $email): ?>
                                <tr>
                                    <td class="datetime-col">
                                        <div class="datetime-compact">
                                            <?php echo date('m/d/y g:i A', strtotime($email['sent_datetime'])); ?>
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
                                            <span class="text-muted">Project deleted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($email['attachments'])): ?>
                                            <?php
                                            $attachments = json_decode($email['attachments'], true);
                                            if (is_array($attachments) && !empty($attachments)):
                                            ?>
                                                <div class="attachments">
                                                    <span class="attachment-count">
                                                        <?php echo count($attachments); ?> file<?php echo count($attachments) > 1 ? 's' : ''; ?>
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
                                <a href="?<?php echo buildQueryString(['page' => 1]); ?>" class="btn">First</a>
                                <a href="?<?php echo buildQueryString(['page' => $page - 1]); ?>" class="btn">Previous</a>
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
                                <a href="?<?php echo buildQueryString(['page' => $page + 1]); ?>" class="btn">Next</a>
                                <a href="?<?php echo buildQueryString(['page' => $total_pages]); ?>" class="btn">Last</a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="records-count">
                            <?php
                            // Calculate the current record range
                            $startRecord = ($page - 1) * $items_per_page + 1;
                            $endRecord = min($page * $items_per_page, $total_count);
                            echo "Showing $startRecord - $endRecord of $total_count records";
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
