<?php 
require_once 'includes/functions.php';

$action = $_GET['action'] ?? 'add';
$contact_id = $_GET['id'] ?? 0;
$customer_id = $_GET['customer_id'] ?? 0;

if ($action == 'delete' && $contact_id) {
    deleteContactPerson($contact_id);
    header("Location: customer_form.php?action=edit&id=$customer_id");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$isViewMode) {
    // Validate required fields
    if (empty($_POST['name'])) {
        die("Error: Name is required");
    }

    $data = [
        'customer_id' => $_POST['customer_id'],
        'name' => $_POST['name'],
        'title' => $_POST['title'] ?? null,
        'role' => $_POST['role'] ?? null,
        'contact_number' => $_POST['contact_number'] ?? null,
        'contact_email' => $_POST['contact_email'] ?? null,
        'notes' => $_POST['notes'] ?? null
    ];
    
    global $pdo;
    
    try {
        if ($action == 'add') {
            $stmt = $pdo->prepare("INSERT INTO contact_persons 
                                  (customer_id, name, title, role, contact_number, contact_email, notes) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array_values($data));
        } else {
            $stmt = $pdo->prepare("UPDATE contact_persons SET 
                                  customer_id = ?, name = ?, title = ?, 
                                  role = ?, contact_number = ?, contact_email = ?, notes = ? 
                                  WHERE contact_id = ?");
            $data[] = $contact_id;
            $stmt->execute(array_values($data));
        }
        
        header("Location: customer_form.php?action=edit&id=" . $data['customer_id']);
        exit;
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}



$contact = $action == 'edit' ? getContactPersonById($contact_id) : null;
$customer_id = $contact ? $contact['customer_id'] : $customer_id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> Contact Person</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <h1><?php echo ucfirst($action); ?> Contact Person</h1>
        
        <form method="post">
            <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
            
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required 
                       value="<?php echo $contact ? htmlspecialchars($contact['name']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" id="title" name="title" 
                       value="<?php echo $contact ? htmlspecialchars($contact['title']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="role">Role:</label>
                <input type="text" id="role" name="role" 
                       value="<?php echo $contact ? htmlspecialchars($contact['role']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="contact_number">Contact Number:</label>
                <input type="tel" id="contact_number" name="contact_number" 
                    value="<?php echo $contact ? htmlspecialchars($contact['contact_number']) : ''; ?>" 
                    <?php echo $isViewMode ? 'disabled' : ''; ?>>
            </div>

            <div class="form-group">
                <label for="contact_email">Contact Email:</label>
                <input type="email" id="contact_email" name="contact_email" 
                    value="<?php echo $contact ? htmlspecialchars($contact['contact_email']) : ''; ?>" 
                    <?php echo $isViewMode ? 'disabled' : ''; ?>>
            </div>
            
            <?php if ($action == 'add'): ?>
                <button type="submit" class="btn">Save</button>
                <a href="customer_form.php?action=edit&id=<?php echo $customer_id; ?>" class="btn">Cancel</a>
            <?php elseif ($action == 'edit'): ?>
                <div class="form-actions-row">
                    <div class="form-actions-main">
                        <button type="submit" class="btn">Save</button>
                        <a href="customer_form.php?action=edit&id=<?php echo $customer_id; ?>" class="btn">Cancel</a>
                    </div>
                    <a href="contact_form.php?action=delete&id=<?php echo $contact_id; ?>&customer_id=<?php echo $customer_id; ?>" 
                       class="btn delete" onclick="return confirm('Are you sure you want to delete this contact?')">Delete Contact</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>