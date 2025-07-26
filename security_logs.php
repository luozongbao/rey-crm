<?php
require_once 'includes/functions.php';
session_start();

requireAdmin(); // Only admins can view security logs

$page_title = __('security_logs');
$current_page = 'settings';

// Pagination settings
$items_per_page = 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Filter parameters
$event_type = $_GET['event_type'] ?? '';
$user_id = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($event_type)) {
    $where_conditions[] = "event_type = ?";
    $params[] = $event_type;
}

if (!empty($user_id)) {
    $where_conditions[] = "user_id = ?";
    $params[] = $user_id;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) FROM security_log sl $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_items = $count_stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

// Get logs with user information
$sql = "SELECT sl.*, u.username 
        FROM security_log sl 
        LEFT JOIN users u ON sl.user_id = u.user_id 
        $where_clause 
        ORDER BY sl.created_at DESC 
        LIMIT $items_per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get event types for filter
$event_types_stmt = $pdo->query("SELECT DISTINCT event_type FROM security_log ORDER BY event_type");
$event_types = $event_types_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$users_stmt = $pdo->query("SELECT user_id, username FROM users ORDER BY username");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="header">
        <h1><?php echo __('security_logs'); ?></h1>
        <a href="settings.php" class="btn btn-secondary"><?php echo __('back_to_settings'); ?></a>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="filters-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="event_type"><?php echo __('event_type'); ?>:</label>
                        <select id="event_type" name="event_type">
                            <option value=""><?php echo __('all_events'); ?></option>
                            <?php foreach ($event_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" 
                                        <?php echo $type === $event_type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_id"><?php echo __('user'); ?>:</label>
                        <select id="user_id" name="user_id">
                            <option value=""><?php echo __('all_users'); ?></option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>" 
                                        <?php echo $user['user_id'] == $user_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from"><?php echo __('date_from'); ?>:</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to"><?php echo __('date_to'); ?>:</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?php echo __('filter'); ?></button>
                    <a href="security_logs.php" class="btn btn-secondary"><?php echo __('clear_filters'); ?></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="card">
        <div class="card-header">
            <h3><?php echo __('security_events'); ?> (<?php echo number_format($total_items); ?> <?php echo __('total'); ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <p class="no-data"><?php echo __('no_security_events_found'); ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php echo __('date_time'); ?></th>
                                <th><?php echo __('event_type'); ?></th>
                                <th><?php echo __('user'); ?></th>
                                <th><?php echo __('ip_address'); ?></th>
                                <th><?php echo __('details'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <span title="<?php echo htmlspecialchars($log['created_at']); ?>">
                                            <?php echo formatDateTime($log['created_at']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo getBadgeClassForEvent($log['event_type']); ?>">
                                            <?php echo htmlspecialchars($log['event_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                    <td>
                                        <details>
                                            <summary><?php echo __('view_details'); ?></summary>
                                            <pre class="security-details"><?php echo htmlspecialchars(json_encode(json_decode($log['details'] ?? '{}'), JSON_PRETTY_PRINT)); ?></pre>
                                        </details>
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
                                <a href="?page=<?php echo $page-1; ?>&<?php echo http_build_query($_GET); ?>" class="page-link"><?php echo __('previous'); ?></a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>" 
                                   class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page+1; ?>&<?php echo http_build_query($_GET); ?>" class="page-link"><?php echo __('next'); ?></a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pagination-info">
                            <?php echo __('showing'); ?> <?php echo (($page-1) * $items_per_page) + 1; ?> - <?php echo min($page * $items_per_page, $total_items); ?> 
                            <?php echo __('of'); ?> <?php echo number_format($total_items); ?> <?php echo __('events'); ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.filters-form .form-row {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.filters-form .form-group {
    flex: 1;
    min-width: 150px;
}

.security-details {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    font-size: 12px;
    max-height: 200px;
    overflow-y: auto;
}

.badge-successful_login, .badge-user_created { background: #28a745; color: white; }
.badge-failed_login, .badge-user_deleted { background: #dc3545; color: white; }
.badge-password_reset_requested { background: #ffc107; color: black; }
.badge-file_upload { background: #17a2b8; color: white; }
.badge-customer_assignment_change { background: #6f42c1; color: white; }
.badge { padding: 0.25em 0.6em; border-radius: 0.25rem; font-size: 0.75em; }
</style>

<?php
function getBadgeClassForEvent($event_type) {
    $safe_classes = [
        'successful_login' => 'successful_login',
        'failed_login' => 'failed_login',
        'user_created' => 'user_created',
        'user_deleted' => 'user_deleted',
        'password_reset_requested' => 'password_reset_requested',
        'file_upload' => 'file_upload',
        'customer_assignment_change' => 'customer_assignment_change'
    ];
    
    return $safe_classes[$event_type] ?? 'secondary';
}

include 'includes/footer.php';
?>
