<?php 
require_once 'includes/functions.php';

$action = $_GET['action'] ?? 'add';
$history_id = $_GET['id'] ?? 0;
$customer_id = $_GET['customer_id'] ?? 0;
$isViewMode = $action === 'view';

if ($action == 'delete' && $history_id) {
    deleteHistory($history_id);
    header("Location: customer_form.php?action=edit&id=$customer_id");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$isViewMode) {
    // Validate required fields first
    $required = ['action_datetime', 'action', 'response', 'next_step', 'follow_up_datetime'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            die("Error: $field is required");
        }
    }

    $data = [
        'customer_id' => $customer_id,
        'contact_id' => $_POST['contact_id'] ?? null, // Make contact_id optional
        'action_datetime' => $_POST['action_datetime'],
        'action' => $_POST['action'],
        'response' => $_POST['response'],
        'next_step' => $_POST['next_step'],
        'follow_up_datetime' => $_POST['follow_up_datetime'],
        'notes' => $_POST['notes'] ?? null
    ];
    
    global $pdo;
    
    try {
        if ($action == 'add') {
            $stmt = $pdo->prepare("INSERT INTO action_history 
                                 (customer_id, contact_id, action_datetime, action, response, next_step, follow_up_datetime, notes) 
                                 VALUES (:customer_id, :contact_id, :action_datetime, :action, :response, :next_step, :follow_up_datetime, :notes)");
            $stmt->execute($data);
            updateLastContactedDate($customer_id);
        } else {
            $stmt = $pdo->prepare("UPDATE action_history SET 
                                 customer_id = :customer_id, contact_id = :contact_id, action_datetime = :action_datetime, 
                                 action = :action, response = :response, next_step = :next_step, 
                                 follow_up_datetime = :follow_up_datetime, notes = :notes 
                                 WHERE history_id = :history_id");
            $data['history_id'] = $history_id;
            $stmt->execute($data);
        }
        
        header("Location: customer_form.php?action=edit&id=$customer_id");
        exit;
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

$history = ($action == 'edit' || $action == 'view') ? getHistoryById($history_id) : null;
$customer_id = $history ? $history['customer_id'] : $customer_id;
$contact_persons = $customer_id ? getContactPersons($customer_id) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> Action History</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js" defer></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo ucfirst($action); ?> Action History</h1>
            <a href="customer_form.php?action=edit&id=<?php echo $customer_id; ?>" class="btn">Back</a>
        </div>
        
        <form method="post">
            <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
            
            <div class="form-group">
                <label for="action_datetime">Date & Time:</label>
                <input type="datetime-local" id="action_datetime" name="action_datetime" required 
                       value="<?php echo $history ? date('Y-m-d\TH:i', strtotime($history['action_datetime'])) : date('Y-m-d\TH:i'); ?>" 
                       <?php echo $isViewMode ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="contact_id">Contact Person:</label>
                <select id="contact_id" name="contact_id" required <?php echo $isViewMode ? 'disabled' : ''; ?>>
                    <option value="">-- Select Contact --</option>
                    <?php foreach ($contact_persons as $contact): ?>
                    <option value="<?php echo $contact['contact_id']; ?>" 
                        <?php echo ($history && $history['contact_id'] == $contact['contact_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($contact['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="action">Action:</label>
                <textarea id="action" name="action" required <?php echo $isViewMode ? 'disabled' : ''; ?>><?php 
                    echo $history ? htmlspecialchars($history['action']) : ''; 
                ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="response">Response:</label>
                <textarea id="response" name="response" required <?php echo $isViewMode ? 'disabled' : ''; ?>><?php 
                    echo $history ? htmlspecialchars($history['response']) : ''; 
                ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="next_step">Next Step:</label>
                <textarea id="next_step" name="next_step" required <?php echo $isViewMode ? 'disabled' : ''; ?>><?php 
                    echo $history ? htmlspecialchars($history['next_step']) : ''; 
                ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="follow_up_datetime">Follow Up Date & Time:</label>
                <input type="datetime-local" id="follow_up_datetime" name="follow_up_datetime" required 
                       value="<?php echo $history ? date('Y-m-d\TH:i', strtotime($history['follow_up_datetime'])) : date('Y-m-d\TH:i', strtotime('+1 week')); ?>" 
                       <?php echo $isViewMode ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="notes">Notes:</label>
                <textarea id="notes" name="notes" <?php echo $isViewMode ? 'disabled' : ''; ?>><?php 
                    echo $history ? htmlspecialchars($history['notes']) : ''; 
                ?></textarea>
            </div>
            
            <div class="form-actions">
                <?php if ($action == 'add'): ?>
                <button type="submit" class="btn">Add Action</button>
                <a href="customer_form.php?action=edit&id=<?php echo $customer_id; ?>" class="btn">Cancel</a>
                <?php elseif ($action == 'edit'): ?>
                <button type="submit" class="btn">Save Action</button>
                <a href="history_form.php?action=view&id=<?php echo $history_id; ?>" class="btn">Cancel</a>
                <?php else: ?>
                <a href="history_form.php?action=edit&id=<?php echo $history_id; ?>" class="btn">Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</body>
</html>