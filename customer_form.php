<?php 
require_once 'includes/functions.php';
session_start();

$action = $_GET['action'] ?? 'add';
$customer_id = $_GET['id'] ?? 0;
$isViewMode = $action === 'view';
$isEditMode = $action === 'edit';

if ($action == 'delete' && $customer_id) {
    deleteCustomer($customer_id);
    header("Location: customers.php?restore=1");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($action == 'add' || $action == 'edit')) {
    $data = [
        'company_name' => $_POST['company_name'],
        'address' => $_POST['address'] ?? null,
        'country' => $_POST['country'] ?? null,
        'province' => $_POST['province'] ?? null,
        'company_type' => $_POST['company_type'] ?? null,
        'contact_phone' => $_POST['contact_phone'] ?? null,
        'contact_email' => $_POST['contact_email'] ?? null,
        'website' => $_POST['website'] ?? null,
        'status' => $_POST['status'] ?? 'Prospect',
        'notes' => $_POST['notes'] ?? null
    ];
    
    global $pdo;
    
    try {
        if ($action == 'add') {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert customer
            $stmt = $pdo->prepare("INSERT INTO customers 
                                  (company_name, address, country, province, company_type, contact_phone, contact_email, website, status, notes) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array_values($data));
            $customer_id = $pdo->lastInsertId();
            
            // Create main contact
            $mainContact = [
                'customer_id' => $customer_id,
                'name' => 'Company Main Contact',
                'title' => 'Primary Contact',
                'role' => 'Main Contact',
                'contact_number' => $_POST['contact_phone'] ?? null,
                'contact_email' => $_POST['contact_email'] ?? null,
                'notes' => 'Automatically created as main contact'
            ];
            
            $stmt = $pdo->prepare("INSERT INTO contact_persons 
                                  (customer_id, name, title, role, contact_number, contact_email, notes) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array_values($mainContact));
            
            // Commit transaction
            $pdo->commit();
            
            header("Location: customers.php?restore=1");
            exit;
        } else {
            $stmt = $pdo->prepare("UPDATE customers SET 
                                  company_name = ?, address = ?, country = ?, province = ?, 
                                  company_type = ?, contact_phone = ?, contact_email = ?, 
                                  website = ?, status = ?, notes = ? 
                                  WHERE customer_id = ?");
            $data[] = $customer_id;
            $stmt->execute(array_values($data));
            
            header("Location: customers.php?restore=1");
            exit;
        }
    } catch (PDOException $e) {
        if ($action == 'add' && isset($pdo)) {
            $pdo->rollBack();
        }
        die("Database error: " . $e->getMessage());
    }
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
            <a href="customers.php?restore=1" class="btn">Back</a>
        </div>
        
        <form method="post">
            <div class="form-row">
                <div class="form-group half-width">
                    <label for="company_name">Company Name:</label>
                    <input type="text" id="company_name" name="company_name" required 
                           value="<?php echo $customer ? htmlspecialchars($customer['company_name']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group half-width">
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="address"
                           value="<?php echo $customer ? htmlspecialchars($customer['address']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="country">Country:</label>
                    <input type="text" id="country" name="country"
                           value="<?php echo $customer ? htmlspecialchars($customer['country']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group half-width">
                    <label for="province">Province:</label>
                    <input type="text" id="province" name="province"
                           value="<?php echo $customer ? htmlspecialchars($customer['province']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group half-width">
                    <label for="company_type">Company Type:</label>
                    <input type="text" id="company_type" name="company_type" 
                           value="<?php echo $customer ? htmlspecialchars($customer['company_type']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group half-width">
                    <label for="contact_phone">Contact Phone:</label>
                    <input type="tel" id="contact_phone" name="contact_phone" 
                           value="<?php echo $customer ? htmlspecialchars($customer['contact_phone']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group half-width">
                    <label for="contact_email">Contact Email:</label>
                    <input type="email" id="contact_email" name="contact_email" 
                           value="<?php echo $customer ? htmlspecialchars($customer['contact_email']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group half-width">
                    <label for="website">Website:</label>
                    <input type="url" id="website" name="website" 
                           value="<?php echo $customer ? htmlspecialchars($customer['website']) : ''; ?>" 
                           <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
            </div>
            
            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status" <?php echo $isViewMode ? 'disabled' : ''; ?>>
                    <?php
                    $statusOptions = getCustomerStatusOptions();
                    foreach ($statusOptions as $statusOption):
                        $selected = ($customer && $customer['status'] == $statusOption) ? 'selected' : '';
                    ?>
                        <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($statusOption); ?>
                        </option>
                    <?php endforeach; ?>
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
                <a href="customers.php?restore=1" class="btn">Cancel</a>
                <?php elseif ($action == 'edit'): ?>
                <button type="submit" class="btn">Save Customer</button>
                <a href="customers.php?restore=1" class="btn">Cancel</a>
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
                        <th class="action-col">Action</th>
                        <th class="response-col">Response</th>
                        <th class="nextstep-col">Next Step</th>
                        <th class="datetime-col">Date/Time</th>
                        <th class="datetime-col">Follow Up</th>
                        <?php if (!$isViewMode): ?>
                        <th class="actions-col">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($action_history as $history): ?>
                    <tr>
                        <td class="action-col"><?php echo htmlspecialchars($history['action']); ?></td>
                        <td class="response-col"><?php echo htmlspecialchars($history['response']); ?></td>
                        <td class="nextstep-col"><?php echo htmlspecialchars($history['next_step']); ?></td>
                        <td class="datetime-col"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($history['action_datetime']))); ?></td>
                        <td class="datetime-col"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($history['follow_up_datetime']))); ?></td>
                        <?php if (!$isViewMode): ?>
                        <td class="actions-col">
                            <a href="history_form.php?action=edit&id=<?php echo $history['history_id']; ?>&customer_id=<?php echo $customer_id; ?>" class="btn">Edit</a>
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