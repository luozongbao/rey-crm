<?php
// Admin Status Summary Tab Content for Admin Customer Management

// Get filter parameters
$selected_user = $_GET['user_id'] ?? '';
$as_of_datetime = $_GET['as_of_datetime'] ?? '';
$view_mode = $_GET['view_mode'] ?? 'all_users'; // 'all_users' or 'single_user'

// Default to current time if not provided
if (!$as_of_datetime) {
    $as_of_datetime = date('Y-m-d H:i:s');
}

// Get all users for the dropdown
$all_users = getAllUsers();

// Get status summary data based on view mode
if ($view_mode === 'single_user' && !empty($selected_user)) {
    $status_summary = getCustomerStatusSummary($selected_user, false, $as_of_datetime);
    $user_info = getUserWorkloadStats($selected_user);
} else {
    $all_users_summary = getAllUsersStatusSummary($as_of_datetime);
}

// Get all possible statuses for consistent display
$all_statuses = getCustomerStatusOptions();
?>

<div class="status-summary-content">
    <!-- Header with Controls -->
    <div class="section-header">
        <h3><?php echo __('customer_status_summary'); ?></h3>
        <div class="header-actions">
            <button type="button" class="btn btn-outline-secondary" onclick="exportStatusSummary()">
                <i class="fas fa-download"></i> <?php echo __('export_summary'); ?>
            </button>
        </div>
    </div>

    <!-- Filter Controls -->
    <div class="filter-section">
        <form method="GET" class="filter-form">
            <input type="hidden" name="tab" value="status_summary">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="view_mode"><?php echo __('view_mode'); ?>:</label>
                    <select name="view_mode" id="view_mode" class="form-control" onchange="toggleUserFilter()">
                        <option value="all_users" <?php echo $view_mode === 'all_users' ? 'selected' : ''; ?>>
                            <?php echo __('all_users_overview'); ?>
                        </option>
                        <option value="single_user" <?php echo $view_mode === 'single_user' ? 'selected' : ''; ?>>
                            <?php echo __('single_user_detailed'); ?>
                        </option>
                    </select>
                </div>
                
                <div class="form-group" id="user-filter-group" style="<?php echo $view_mode === 'all_users' ? 'display: none;' : ''; ?>">
                    <label for="user_id"><?php echo __('select_user'); ?>:</label>
                    <select name="user_id" id="user_id" class="form-control">
                        <option value=""><?php echo __('select_user'); ?></option>
                        <?php foreach ($all_users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>" 
                                <?php echo $selected_user == $user['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="as_of_datetime"><?php echo __('as_of_time'); ?>:</label>
                    <input type="datetime-local" name="as_of_datetime" id="as_of_datetime" 
                           value="<?php echo date('Y-m-d\TH:i', strtotime($as_of_datetime)); ?>" 
                           class="form-control">
                </div>
                
                <div class="form-group button-group">
                    <label>&nbsp;</label> <!-- Empty label for alignment -->
                    <div class="button-container">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> <?php echo __('apply_filters'); ?>
                        </button>
                        <a href="?tab=status_summary" class="btn btn-secondary">
                            <i class="fas fa-times"></i> <?php echo __('clear_filters'); ?>
                        </a>
                        <button type="button" class="btn btn-info" onclick="setCurrentTime()">
                            <i class="fas fa-clock"></i> <?php echo __('set_to_now'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Status Summary Content -->
    <?php if ($view_mode === 'single_user' && !empty($selected_user)): ?>
        <!-- Single User Detailed View -->
        <div class="single-user-summary">
            <?php if ($user_info): ?>
            <div class="user-header">
                <h4><?php echo htmlspecialchars($user_info['username']); ?>'s <?php echo __('customer_status_summary'); ?></h4>
                <div class="date-range">
                    <?php echo __('as_of_time'); ?>: <?php echo formatDateTime($as_of_datetime); ?>
                </div>
            </div>

            <div class="status-summary-grid">
                <?php if (!empty($status_summary)): ?>
                    <?php foreach ($status_summary as $status): ?>
                    <div class="status-card">
                        <div class="status-header">
                            <span class="status-badge status-<?php echo str_replace(['_', '-'], '', $status['status_key']); ?>">
                                <?php echo htmlspecialchars($status['status_name']); ?>
                            </span>
                            <span class="status-count"><?php echo number_format($status['count']); ?></span>
                        </div>
                        <div class="status-details">
                            <div class="detail-item">
                                <span class="detail-label"><?php echo __('new_this_week'); ?>:</span>
                                <span class="detail-value"><?php echo $status['new_this_week']; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><?php echo __('new_this_month'); ?>:</span>
                                <span class="detail-value"><?php echo $status['new_this_month']; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"><?php echo __('avg_days_in_status'); ?>:</span>
                                <span class="detail-value"><?php echo $status['avg_days_in_status']; ?> <?php echo __('days'); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <p><?php echo __('no_customers_found_at_time'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <?php echo __('user_not_found'); ?>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- All Users Overview -->
        <div class="all-users-summary">
            <div class="summary-header">
                <h4><?php echo __('all_users_status_overview'); ?></h4>
                <div class="date-range">
                    <?php echo __('as_of_time'); ?>: <?php echo formatDateTime($as_of_datetime); ?>
                </div>
            </div>

            <?php if (!empty($all_users_summary)): ?>
            <div class="users-summary-table">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?php echo __('user'); ?></th>
                            <th><?php echo __('total_customers'); ?></th>
                            <?php foreach ($all_statuses as $status_key): ?>
                            <th class="status-column"><?php echo __($status_key); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users_summary as $user_summary): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($user_summary['username']); ?></strong>
                                <div class="user-actions">
                                    <a href="?tab=status_summary&view_mode=single_user&user_id=<?php echo $user_summary['user_id']; ?>&as_of_datetime=<?php echo urlencode($as_of_datetime); ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <?php echo __('view_details'); ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <span class="total-count"><?php echo number_format($user_summary['total_customers']); ?></span>
                            </td>
                            <?php 
                            // Create status lookup array
                            $user_statuses = [];
                            foreach ($user_summary['statuses'] as $status) {
                                $user_statuses[$status['status_key']] = $status['count'];
                            }
                            ?>
                            <?php foreach ($all_statuses as $status_key): ?>
                            <td class="status-cell">
                                <?php 
                                $count = $user_statuses[$status_key] ?? 0;
                                if ($count > 0): 
                                ?>
                                    <span class="status-count-badge status-<?php echo str_replace(['_', '-'], '', $status_key); ?>">
                                        <?php echo number_format($count); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="zero-count">0</span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="no-data">
                    <p><?php echo __('no_data_found_at_time'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.status-summary-content {
    padding: 20px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.section-header h3 {
    margin: 0;
    color: #495057;
}

.filter-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.filter-form .form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: end;
}

.filter-form .form-row .form-group {
    flex: 0 0 auto;
    min-width: 180px;
}

.filter-form .form-row .form-group:nth-child(1) {
    min-width: 200px;
}

.filter-form .form-row .form-group:nth-child(2) {
    min-width: 200px;
}

.filter-form .form-row .form-group:nth-child(3) {
    min-width: 250px;
}

.filter-form .form-row .button-group {
    display: flex;
    flex-direction: column;
    justify-content: end;
    flex: 1 1 auto;
    min-width: 300px;
    margin-top: 15px;
}

.filter-form .form-row .button-group label {
    visibility: hidden; /* Keeps space for label alignment */
    margin-bottom: 5px;
    height: 20px;
}

.filter-form .form-row .button-container {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    width: 100%;
}

@media (max-width: 1200px) {
    .filter-form .form-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .filter-form .form-row .form-group {
        width: 100%;
        min-width: unset;
    }
    
    .filter-form .form-row .button-group {
        min-width: unset;
        margin-top: 0;
    }
    
    .filter-form .form-row .button-container {
        justify-content: flex-start;
        margin-top: 10px;
    }
}

.form-group {
    display: flex;
    flex-direction: column;
    min-width: 0; /* Prevent flex items from overflowing */
}

.form-group label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
}

.form-control {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    width: 100%;
    box-sizing: border-box;
}

/* Single User Summary Styles */
.user-header {
    margin-bottom: 20px;
}

.user-header h4 {
    margin: 0 0 5px 0;
    color: #343a40;
}

.date-range {
    color: #6c757d;
    font-size: 14px;
}

.status-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.status-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.status-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-count {
    font-size: 24px;
    font-weight: bold;
    color: #495057;
}

.status-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
}

.detail-label {
    color: #6c757d;
}

.detail-value {
    font-weight: 600;
    color: #495057;
}

/* All Users Table Styles */
.users-summary-table {
    overflow-x: auto;
}

.users-summary-table table {
    width: 100%;
    margin-bottom: 0;
}

.users-summary-table th {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 12px 8px;
    text-align: center;
}

.users-summary-table td {
    padding: 12px 8px;
    border-bottom: 1px solid #dee2e6;
    vertical-align: middle;
}

.users-summary-table td:first-child {
    text-align: left;
}

.users-summary-table td:not(:first-child) {
    text-align: center;
}

.user-actions {
    margin-top: 5px;
}

.total-count {
    font-size: 16px;
    font-weight: bold;
    color: #007bff;
}

.status-count-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    color: white;
    min-width: 24px;
    text-align: center;
}

.zero-count {
    color: #adb5bd;
    font-style: italic;
}

/* Status Color Schemes */
.status-prospect { background: #fff3cd; color: #856404; }
.status-qualified { background: #cce5ff; color: #004085; }
.status-notqualified { background: #f8d7da; color: #721c24; }
.status-newcustomer { background: #d1ecf1; color: #0c5460; }
.status-activecustomer { background: #d4edda; color: #155724; }
.status-inactivecustomer { background: #e2e3e5; color: #383d41; }
.status-lostcustomer { background: #f8d7da; color: #721c24; }
.status-unassigned { background: #f8f9fa; color: #6c757d; }

.status-count-badge.status-prospect { background: #856404; }
.status-count-badge.status-qualified { background: #004085; }
.status-count-badge.status-notqualified { background: #721c24; }
.status-count-badge.status-newcustomer { background: #0c5460; }
.status-count-badge.status-activecustomer { background: #155724; }
.status-count-badge.status-inactivecustomer { background: #383d41; }
.status-count-badge.status-lostcustomer { background: #721c24; }
.status-count-badge.status-unassigned { background: #6c757d; }

.no-data {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

/* Responsive Design */
@media (max-width: 768px) {
    .filter-form .form-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .filter-form .form-row .form-group {
        width: 100%;
        min-width: unset;
    }
    
    .filter-form .form-row .button-group {
        min-width: unset;
        margin-top: 0;
        order: 4; /* Place buttons last */
    }
    
    .status-summary-grid {
        grid-template-columns: 1fr;
    }
    
    .users-summary-table {
        font-size: 12px;
    }
    
    .users-summary-table th,
    .users-summary-table td {
        padding: 8px 4px;
    }
    
    .filter-form .form-row .button-container {
        flex-direction: column;
        width: 100%;
        gap: 8px;
    }
    
    .filter-form .form-row .button-container .btn {
        width: 100%;
        margin-bottom: 0;
    }
}

@media (max-width: 992px) and (min-width: 769px) {
    .filter-form .form-row {
        flex-wrap: wrap;
    }
    
    .filter-form .form-row .button-group {
        flex-basis: 100%;
        margin-top: 15px;
        order: 4;
    }
}
</style>

<script>
function toggleUserFilter() {
    const viewMode = document.getElementById('view_mode').value;
    const userFilterGroup = document.getElementById('user-filter-group');
    
    if (viewMode === 'single_user') {
        userFilterGroup.style.display = 'block';
    } else {
        userFilterGroup.style.display = 'none';
        document.getElementById('user_id').value = '';
    }
}

function exportStatusSummary() {
    // Get current filter parameters
    const viewMode = document.getElementById('view_mode').value;
    const userId = document.getElementById('user_id').value;
    const asOfDatetime = document.getElementById('as_of_datetime').value;
    
    // Build export URL
    let exportUrl = 'export_status_summary.php?';
    const params = new URLSearchParams({
        view_mode: viewMode,
        as_of_datetime: asOfDatetime
    });
    
    if (viewMode === 'single_user' && userId) {
        params.append('user_id', userId);
    }
    
    exportUrl += params.toString();
    window.open(exportUrl, '_blank');
}

function setCurrentTime() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    
    const currentDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
    document.getElementById('as_of_datetime').value = currentDateTime;
}
</script>
