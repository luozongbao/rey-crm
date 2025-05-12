<?php 
require_once 'includes/functions.php';

// Get search, filter, and pagination parameters
$search = $_GET['search'] ?? '';
$location = $_GET['location'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "company_name LIKE :search";
    $params[':search'] = "%$search%";
}

if (!empty($location)) {
    $conditions[] = "location = :location";
    $params[':location'] = $location;
}

// Get all unique locations for filter dropdown
$locations = getAllLocations();

// Get paginated customers
$customers = getPaginatedCustomers($conditions, $params, $page, $perPage);
$totalCustomers = getCustomerCount($conditions, $params);
$totalPages = ceil($totalCustomers / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Relationship Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Customer List</h1>
            <a href="dashboard.php" class="btn dashboard-btn">Dashboard</a>
        </div>
        
        <!-- Search and Filter Form - Now in single line -->
        <form method="get" action="" class="search-filter-form">
            <input type="hidden" name="page" value="1">
            
            <div class="search-filter-group">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search company name" class="search-input">
                
                <select name="location" class="location-select">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo htmlspecialchars($loc); ?>" 
                            <?php echo $location === $loc ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($loc); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn apply-btn">Apply</button>
                <a href="index.php" class="btn reset-btn">Reset</a>
            </div>
        </form>
        
        <a href="customer_form.php?action=add" class="btn">Create New Customer</a>
        
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
                    <td><?php echo htmlspecialchars($customer['location']); ?></td>
                    <td><?php echo $customer['last_contact'] ? date('Y-m-d H:i', strtotime($customer['last_contact'])) : 'Never'; ?></td>
                    <td><span class="status-badge status-<?php echo strtolower($customer['status']); ?>">
                        <?php echo htmlspecialchars($customer['status']); ?>
                    </span></td>
                    <td>
                        <a href="customer_form.php?action=view&id=<?php echo $customer['customer_id']; ?>" class="btn">View</a>
                        <a href="customer_form.php?action=edit&id=<?php echo $customer['customer_id']; ?>" class="btn">Edit</a>
                        <a href="customer_form.php?action=delete&id=<?php echo $customer['customer_id']; ?>" 
                           class="btn delete" onclick="return confirm('Are you sure you want to delete this customer and all related data?')">Delete</a>
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