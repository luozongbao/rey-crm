<?php 
require_once 'includes/functions.php';

$action = $_GET['action'] ?? 'add';
$customer_id = $_GET['id'] ?? 0;
$isViewMode = $action === 'view';
$isEditMode = $action === 'edit';

if ($action == 'delete' && $customer_id) {
    deleteCustomer($customer_id);
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($action == 'add' || $action == 'edit')) {
    $data = [
        'company_name' => $_POST['company_name'],
        'location' => $_POST['location'],
        'company_type' => $_POST['company_type'] ?? null,
        'contact_phone' => $_POST['contact_phone'] ?? null,
        'contact_email' => $_POST['contact_email'] ?? null,
        'status' => $_POST['status'] ?? 'Prospect',
        'notes' => $_POST['notes'] ?? null
    ];
    
    global $pdo;
    
    if ($action == 'add') {
        $stmt = $pdo->prepare("INSERT INTO customers (company_name, location, company_type, contact_phone, contact_email, status, notes) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(array_values($data));
        $customer_id = $pdo->lastInsertId();
    } else {
        $stmt = $pdo->prepare("UPDATE customers SET company_name = ?, location = ?, company_type = ?, 
                              contact_phone = ?, contact_email = ?, status = ?, notes = ? WHERE customer_id = ?");
        $data[] = $customer_id;
        $stmt->execute(array_values($data));
    }
    
    header("Location: customer_form.php?action=edit&id=$customer_id");
    exit;
}

if (isset($_GET['delete_contact']) && $customer_id) {
    deleteContactPerson($_GET['delete_contact']);
    header("Location: customer_form.php?action=edit&id=$customer_id");
    exit;
}

if (isset($_GET['delete_history']) && $customer_id) {
    deleteHistory($_GET['delete_history']);
    header("Location: customer_form.php?action=edit&id=$customer_id");
    exit;
}

$customer = ($action == 'edit' || $action == 'view') ? getCustomerById($customer_id) : null;
$contact_persons = $customer_id ? getContactPersons($customer_id) : [];
$action_history = $customer_id ? getActionHistory($customer_id) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> Customer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo ucfirst($action); ?> Customer</h1>
            <?php if ($isViewMode || $isEditMode): ?>
            <a href="index.php" class="btn">Back</a>
            <?php endif; ?>
        </div>
        
        <form method="post">
            <div class="form-group">
                <label for="company_name">Company Name:</label>
                <input type="text" id="company_name" name="company_name" required 
                       value="<?php echo $customer ? htmlspecialchars($customer['company_name']) : ''; ?>" 
                       <?php echo $isViewMode ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="location">Location:</label>
                <input type="text" id="location" name="location" required 
                       value="<?php echo $customer ? htmlspecialchars($customer['location']) : ''; ?>" 
                       <?php echo $isViewMode ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="company_type">Company Type:</label>
                <input type="text" id="company_type" name="company_type" 
                       value="<?php echo $customer ? htmlspecialchars($customer['company_type']) : ''; ?>" 
                       <?php echo $isViewMode ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="contact_phone">Contact Phone:</label>
                <input type="tel" id="contact_phone" name="contact_phone" 
                       value="<?php echo $customer ? htmlspecialchars($customer['contact_phone']) : ''; ?>" 
                       <?php echo $isViewMode ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="contact_email">Contact Email:</label>
                <input type="email" id="contact_email" name="contact_email" 
                       value="<?php echo $customer ? htmlspecialchars($customer['contact_email']) : ''; ?>" 
                       <?php echo $isViewMode ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status" <?php echo $isViewMode ? 'disabled' : ''; ?>>
                    <option value="Active" <?php echo ($customer && $customer['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo ($customer && $customer['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    <option value="Prospect" <?php echo (!$customer || $customer['status'] == 'Prospect') ? 'selected' : ''; ?>>Prospect</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="notes">Notes:</label>
                <textarea id="notes" name="notes" <?php echo $isViewMode ? 'disabled' : ''; ?>><?php 
                    echo $customer ? htmlspecialchars($customer['notes']) : ''; 
                ?></textarea>
            </div>
            
            <div class="form-actions">
                <?php if ($action == 'add'): ?>
                <button type="submit" class="btn">Add Customer</button>
                <a href="index.php" class="btn">Cancel</a>
                <?php elseif ($action == 'edit'): ?>
                <button type="submit" class="btn">Save Customer</button>
                <a href="customer_form.php?action=view&id=<?php echo $customer_id; ?>" class="btn">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if ($customer_id): ?>
        <div class="section">
            <h2>Contact Persons</h2>
            <?php if (!$isViewMode): ?>
            <a href="contact_form.php?action=add&customer_id=<?php echo $customer_id; ?>" class="btn">Add Contact Person</a>
            <?php endif; ?>
            
            <table class="compact-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact Number</th>
                        <th>Contact Email</th>
                        <?php if (!$isViewMode): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contact_persons as $contact): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($contact['name']); ?></td>
                        <td><?php echo htmlspecialchars($contact['contact_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($contact['contact_email'] ?? 'N/A'); ?></td>
                        <?php if (!$isViewMode): ?>
                        <td>
                            <a href="contact_form.php?action=edit&id=<?php echo $contact['contact_id']; ?>" class="btn">Edit</a>
                            <a href="customer_form.php?action=edit&id=<?php echo $customer_id; ?>&delete_contact=<?php echo $contact['contact_id']; ?>" 
                            class="btn delete" onclick="return confirm('Are you sure you want to delete this contact?')">Delete</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2>Action History</h2>
            <?php if (!$isViewMode): ?>
            <a href="history_form.php?action=add&customer_id=<?php echo $customer_id; ?>" class="btn">Add Action</a>
            <?php endif; ?>
            
            <table class="compact-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Response</th>
                        <th>Next Step</th>
                        <th>Date</th>
                        <?php if (!$isViewMode): ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($action_history as $history): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($history['action']); ?></td>
                        <td><?php echo htmlspecialchars($history['response']); ?></td>
                        <td><?php echo htmlspecialchars($history['next_step']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($history['created_at'])); ?></td>
                        <?php if (!$isViewMode): ?>
                        <td>
                            <a href="history_form.php?action=edit&id=<?php echo $history['history_id']; ?>" class="btn">Edit</a>
                            <a href="customer_form.php?action=edit&id=<?php echo $customer_id; ?>&delete_history=<?php echo $history['history_id']; ?>" 
                            class="btn delete" onclick="return confirm('Are you sure you want to delete this history?')">Delete</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>