<?php
require_once 'includes/functions.php';

requireLogin();

// Get page state either from session (if restoring) or from GET parameters
if (isset($_GET['restore']) && isset($_SESSION['last_page_state'])) {
    $state = $_SESSION['last_page_state'];
    $search = $state['search'];
    $location = $state['location'];
    $sort = $state['sort'];
    $order = $state['order'];
    $page = $state['page'];
    
    // Clear the restore parameter by redirecting without it
    $params = $_GET;
    unset($params['restore']);
    $queryString = http_build_query($params);
    $redirectUrl = $_SERVER['PHP_SELF'] . ($queryString ? "?$queryString" : "");
    header("Location: $redirectUrl");
    exit;
} else {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $location = isset($_GET['location']) ? trim($_GET['location']) : '';
    $sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'created_at';
    $order = isset($_GET['order']) ? trim($_GET['order']) : 'desc';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $showOnlyMine = !isset($_GET['show_only_mine']) || $_GET['show_only_mine'] == '1';
}

$perPage = getItemsPerPage();

// Store state in session when navigating away
if (!isset($_GET['restore'])) {
    $_SESSION['last_page_state'] = [
        'search' => $search,
        'location' => $location,
        'sort' => $sort,
        'order' => $order,
        'page' => $page,
        'show_only_mine' => $showOnlyMine
    ];
}

// Get all unique locations for filter dropdown
$locations = getAllLocations();

// Get paginated and sorted customers
$result = getPaginatedCustomers($page, $perPage, $search, $location, $sort, $order, $showOnlyMine);
$customers = $result['data'];
$totalCustomers = $result['total'];
$totalPages = $result['pages'];

// Get status counts for dashboard
$statusCounts = getCustomerStatusCounts();

require_once 'includes/header.php';
?>
    <div class="container">
        <div class="header">
            <h1><?php echo __('customer_list'); ?></h1>
            <a href="customer_form.php?action=add" class="btn"><?php echo __('add_new_customer'); ?></a>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Handle sort form submission
                const sortForm = document.querySelector('.sort-form');
                if (sortForm) {
                    sortForm.addEventListener('submit', function(e) {
                        // Prevent default form submission
                        e.preventDefault();
                        
                        // Get all form data
                        const formData = new FormData(sortForm);
                        const params = new URLSearchParams(formData);
                        
                        // Navigate with updated parameters
                        window.location.href = window.location.pathname + '?' + params.toString();
                    });
                }

                // Alternative: Auto-submit when sort options change
                document.querySelectorAll('.sort-select').forEach(select => {
                    select.addEventListener('change', function() {
                        this.form.submit();
                    });
                });
            });
        </script>
        
        <div class="search-sort-container">
            <!-- Combined Search and Sort Form -->
            <form method="get" action="" class="combined-form">
                <!-- Search Section -->
                <div class="search-section">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                        placeholder="<?php echo __('search_placeholder'); ?>" class="search-input">
                    
                    <select name="location" class="location-select">
                        <option value=""><?php echo __('all_locations'); ?></option>
                        <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>" 
                                <?php echo $location === $loc ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" name="apply_filter" class="btn apply-btn"><?php echo __('search'); ?></button>
                    <a href="customers.php" class="btn reset-btn"><?php echo __('reset'); ?></a>
                </div>
                
                <!-- Sort Section -->
                <div class="sort-section">
                    <label class="sort-label"><?php echo __('sort_by'); ?>:</label>
                    <select name="sort" class="sort-select">
                        <option value="company_name" <?php echo $sort == 'company_name' ? 'selected' : ''; ?>><?php echo __('name'); ?></option>
                        <option value="address" <?php echo $sort == 'address' ? 'selected' : ''; ?>><?php echo __('address'); ?></option>
                        <option value="status" <?php echo $sort == 'status' ? 'selected' : ''; ?>><?php echo __('status'); ?></option>
                        <option value="created_at" <?php echo $sort == 'created_at' ? 'selected' : ''; ?>><?php echo __('created_at'); ?></option>
                    </select>
                    
                    <select name="order" class="sort-select">
                        <option value="asc" <?php echo $order == 'asc' ? 'selected' : ''; ?>><?php echo __('asc'); ?></option>
                        <option value="desc" <?php echo $order == 'desc' ? 'selected' : ''; ?>><?php echo __('desc'); ?></option>
                    </select>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <label class="checkbox-label">
                        <input type="checkbox" name="show_only_mine" value="1" 
                               <?php echo $showOnlyMine ? 'checked' : ''; ?>
                               onchange="this.form.submit()">
                        <?php echo __('show_only_my_customers'); ?>
                    </label>
                </div>
            </form>
        </div>
        
        <!-- Pagination (top) -->
        <div class="pagination-container">
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo buildQueryString(['page' => 1]); ?>" class="btn"><?php echo __('first'); ?></a>
                    <a href="?<?php echo buildQueryString(['page' => $page - 1]); ?>" class="btn"><?php echo __('previous'); ?></a>
                <?php endif; ?>
                
                <?php 
                // Show page numbers
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++): 
                ?>
                    <a href="?<?php echo buildQueryString(['page' => $i]); ?>" 
                       class="btn <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo buildQueryString(['page' => $page + 1]); ?>" class="btn"><?php echo __('next'); ?></a>
                    <a href="?<?php echo buildQueryString(['page' => $totalPages]); ?>" class="btn"><?php echo __('last'); ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="records-count">
                <?php
                // Calculate the current record range
                $startRecord = ($page - 1) * $perPage + 1;
                $endRecord = min($page * $perPage, $totalCustomers);
                echo sprintf(__('showing_records'), $startRecord, $endRecord, $totalCustomers);
                ?>
            </div>
        </div>
        
        <table class="compact-table">
            <thead>
                <tr>
                    <th><?php echo __('company_name'); ?></th>
                    <th><?php echo __('location'); ?></th>
                    <th><?php echo __('last_contact'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><?php echo htmlspecialchars($customer['company_name']); ?></td>
                    <td><?php 
                        if (empty($customer['province']) && empty($customer['country'])) {
                            echo __('not_available');
                        } elseif (empty($customer['province'])) {
                            echo htmlspecialchars($customer['country']);
                        } elseif (empty($customer['country'])) {
                            echo htmlspecialchars($customer['province']);
                        } else {
                            echo htmlspecialchars($customer['province'] . ', ' . $customer['country']);
                        }
                    ?></td>
                    <td><?php echo $customer['last_contact'] ? formatDateTimeCompact($customer['last_contact']) : __('never'); ?></td>
                    <td><span class="status-badge status-<?php echo str_replace(' ', '', strtolower($customer['status'])); ?>">
                        <?php echo htmlspecialchars(__($customer['status'])); ?>
                    </span></td>
                    <td>
                        <a href="customer_form.php?action=view&id=<?php echo $customer['customer_id']; ?>" class="btn"><?php echo __('view'); ?></a>
                        <a href="customer_form.php?action=edit&id=<?php echo $customer['customer_id']; ?>" class="btn"><?php echo __('edit'); ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination (bottom) -->
        <div class="pagination-container">
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo buildQueryString(['page' => 1]); ?>" class="btn"><?php echo __('first'); ?></a>
                    <a href="?<?php echo buildQueryString(['page' => $page - 1]); ?>" class="btn"><?php echo __('previous'); ?></a>
                <?php endif; ?>
                
                <?php 
                // Show page numbers
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++): 
                ?>
                    <a href="?<?php echo buildQueryString(['page' => $i]); ?>" 
                       class="btn <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo buildQueryString(['page' => $page + 1]); ?>" class="btn"><?php echo __('next'); ?></a>
                    <a href="?<?php echo buildQueryString(['page' => $totalPages]); ?>" class="btn"><?php echo __('last'); ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="records-count">
                <?php
                // Calculate the current record range
                $startRecord = ($page - 1) * $perPage + 1;
                $endRecord = min($page * $perPage, $totalCustomers);
                echo sprintf(__('showing_records'), $startRecord, $endRecord, $totalCustomers);
                ?>
            </div>
        </div>
    </div>
<?php require_once 'includes/footer.php'; ?>
