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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'customer_id' => $_POST['customer_id'],
        'name' => $_POST['name'],
        'title' => $_POST['title'],
        'role' => $_POST['role'],
        'contact_number' => $_POST['contact_number'],
        'contact_email' => $_POST['contact_email']
    ];
    
    global $pdo;
    
    if ($action == 'add') {
        $stmt = $pdo->prepare("INSERT INTO contact_persons (customer_id, name, title, role, contact_number, contact_email) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(array_values($data));
    } else {
        $stmt = $pdo->prepare("UPDATE contact_persons SET customer_id = ?, name = ?, title = ?, 
                              role = ?, contact_number = ?, contact_email = ? WHERE contact_id = ?");
        $data[] = $contact_id;
        $stmt->execute(array_values($data));
    }
    
    header("Location: customer_form.php?action=edit&id=" . $data['customer_id']);
    exit;
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
                <input type="tel" id="contact_number" name="contact_number" required 
                       value="<?php echo $contact ? htmlspecialchars($contact['contact_number']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="contact_email">Contact Email:</label>
                <input type="email" id="contact_email" name="contact_email" required 
                       value="<?php echo $contact ? htmlspecialchars($contact['contact_email']) : ''; ?>">
            </div>
            
            <button type="submit" class="btn">Save</button>
            <a href="customer_form.php?action=edit&id=<?php echo $customer_id; ?>" class="btn">Cancel</a>
        </form>
    </div>
</body>
</html>