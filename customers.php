<?php 
require_once 'includes/functions.php';
session_start();

// Get page state either from session (if restoring) or from GET parameters
if (isset($_GET['restore']) && isset($_SESSION['last_page_state'])) {
    $state = $_SESSION['last_page_state'];
    $search = $state['search'];
    $location = $state['location'];
    $sort = $state['sort'];
    $order = $state['order'];
    $page = $state['page'];
} else {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $location = isset($_GET['location']) ? trim($_GET['location']) : '';
    $sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'created_at';
    $order = isset($_GET['order']) ? trim($_GET['order']) : 'desc';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
}
$perPage = 20;

// Store state in session when navigating away
if (!isset($_GET['restore'])) {
    $_SESSION['last_page_state'] = [
        'search' => $search,
        'location' => $location,
        'sort' => $sort,
        'order' => $order,
        'page' => $page
    ];
}

// Validate sort parameters
$validSorts = ['company_name', 'address', 'status', 'created_at'];
$validOrders = ['asc', 'desc'];

$sort = in_array($sort, $validSorts) ? $sort : 'created_at';
$order = in_array($order, $validOrders) ? $order : 'desc';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(company_name LIKE :search OR contact_phone LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($location)) {
    $conditions[] = "CASE
        WHEN NULLIF(TRIM(province), '') IS NULL AND NULLIF(TRIM(country), '') IS NULL THEN 'N/A'
        WHEN NULLIF(TRIM(province), '') IS NULL THEN NULLIF(TRIM(country), '')
        WHEN NULLIF(TRIM(country), '') IS NULL THEN NULLIF(TRIM(province), '')
        ELSE CONCAT(TRIM(province), ', ', TRIM(country))
    END = :location";
    $params[':location'] = $location;
}

// Get all unique locations for filter dropdown
$locations = getAllLocations();

// Get paginated and sorted customers
$customers = getPaginatedCustomers($conditions, $params, $page, $perPage, $sort, $order);
$totalCustomers = getCustomerCount($conditions, $params);
$totalPages = ceil($totalCustomers / $perPage);

// Get status counts for dashboard
$statusCounts = getCustomerStatusCounts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer List - CRM</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Customer List</h1>
            <a href="customer_form.php?action=add" class="btn">Add New Customer</a>
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
                        placeholder="Search by company name or phone" class="search-input">
                    
                    <select name="location" class="location-select">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>" 
                                <?php echo $location === $loc ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" name="apply_filter" class="btn apply-btn">Search</button>
                    <a href="customers.php" class="btn reset-btn">Reset</a>
                </div>
                
                <!-- Sort Section -->
                <div class="sort-section">
                    <label class="sort-label">Sort by:</label>
                    <select name="sort" class="sort-select">
                        <option value="company_name" <?php echo $sort == 'company_name' ? 'selected' : ''; ?>>Name</option>
                        <option value="address" <?php echo $sort == 'address' ? 'selected' : ''; ?>>Address</option>
                        <option value="status" <?php echo $sort == 'status' ? 'selected' : ''; ?>>Status</option>
                        <option value="created_at" <?php echo $sort == 'created_at' ? 'selected' : ''; ?>>Created</option>
                    </select>
                    
                    <select name="order" class="sort-select">
                        <option value="asc" <?php echo $order == 'asc' ? 'selected' : ''; ?>>A-Z</option>
                        <option value="desc" <?php echo $order == 'desc' ? 'selected' : ''; ?>>Z-A</option>
                    </select>
                    
                    <button type="submit" name="apply_sort" class="btn sort-btn">Sort</button>
                </div>
            </form>
        </div>
        
           
        <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo buildQueryString(['page' => 1]); ?>" class="btn">First</a>
                    <a href="?<?php echo buildQueryString(['page' => $page - 1]); ?>" class="btn">Previous</a>
                <?php endif; ?>
                
                <?php 
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
                    <a href="?<?php echo buildQueryString(['page' => $page + 1]); ?>" class="btn">Next</a>
                    <a href="?<?php echo buildQueryString(['page' => $totalPages]); ?>" class="btn">Last</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <table class="compact-table">
            <thead>
                <tr>
                    <th>Company Name</th>
                    <th>Location</th>
                    <th>Last Contact</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><?php echo htmlspecialchars($customer['company_name']); ?></td>
                    <td><?php 
                        if (empty($customer['province']) && empty($customer['country'])) {
                            echo 'N/A';
                        } elseif (empty($customer['province'])) {
                            echo htmlspecialchars($customer['country']);
                        } elseif (empty($customer['country'])) {
                            echo htmlspecialchars($customer['province']);
                        } else {
                            echo htmlspecialchars($customer['province'] . ', ' . $customer['country']);
                        }
                    ?></td>
                    <td><?php echo $customer['last_contact'] ? date('Y-m-d H:i', strtotime($customer['last_contact'])) : 'Never'; ?></td>
                    <td><span class="status-badge status-<?php echo str_replace(' ', '', strtolower($customer['status'])); ?>">
                        <?php echo htmlspecialchars($customer['status']); ?>
                    </span></td>
                    <td>
                        <a href="customer_form.php?action=view&id=<?php echo $customer['customer_id']; ?>" class="btn">View</a>
                        <a href="customer_form.php?action=edit&id=<?php echo $customer['customer_id']; ?>" class="btn">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo buildQueryString(['page' => 1]); ?>" class="btn">First</a>
                <a href="?<?php echo buildQueryString(['page' => $page - 1]); ?>" class="btn">Previous</a>
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
                <a href="?<?php echo buildQueryString(['page' => $page + 1]); ?>" class="btn">Next</a>
                <a href="?<?php echo buildQueryString(['page' => $totalPages]); ?>" class="btn">Last</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
