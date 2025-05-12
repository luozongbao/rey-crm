<?php require_once 'includes/functions.php'; ?>
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
                <?php $customers = getAllCustomers(); ?>
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
    </div>
</body>
</html>