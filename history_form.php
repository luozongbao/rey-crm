<?php 
require_once 'includes/functions.php';
session_start();

$action = $_GET['action'] ?? 'add';
$history_id = $_GET['id'] ?? 0;
$customer_id = $_GET['customer_id'] ?? 0;
$source_action = $_GET['source_action'] ?? 'edit';
$isViewMode = $action === 'view';

// Get customer_id from history record if editing/viewing
if (($action == 'edit' || $action == 'view') && $history_id) {
    $history = getHistoryById($history_id);
    if ($history) {
        $customer_id = $history['customer_id'];
    }
}

if ($action == 'delete' && $history_id) {
    deleteHistory($history_id);
    header("Location: customer_form.php?action=$source_action&id=$customer_id");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$isViewMode) {
    // Validate required fields
    $required = ['action_datetime', 'action', 'response', 'next_step', 'follow_up_datetime'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            die("Error: $field is required");
        }
    }

    // Ensure we have a valid customer_id
    if (empty($customer_id)) {
        die("Error: Customer ID is missing");
    }

    // Convert local datetime to UTC for storage
    $action_datetime = localToUtc($_POST['action_datetime']);
    $follow_up_datetime = localToUtc($_POST['follow_up_datetime']);

    $data = [
        'customer_id' => $customer_id,
        'contact_id' => !empty($_POST['contact_id']) ? $_POST['contact_id'] : null,
        'action_datetime' => $action_datetime,
        'action' => $_POST['action'],
        'response' => $_POST['response'],
        'next_step' => $_POST['next_step'],
        'follow_up_datetime' => $follow_up_datetime,
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
            // For edit, include history_id and verify the record exists
            $existing_history = getHistoryById($history_id);
            if (!$existing_history) {
                die("Error: History record not found");
            }
            
            $data['history_id'] = $history_id;
            $stmt = $pdo->prepare("UPDATE action_history SET 
                                 customer_id = :customer_id, 
                                 contact_id = :contact_id, 
                                 action_datetime = :action_datetime, 
                                 action = :action, 
                                 response = :response, 
                                 next_step = :next_step, 
                                 follow_up_datetime = :follow_up_datetime, 
                                 notes = :notes 
                                 WHERE history_id = :history_id");
            $stmt->execute($data);
        }
        
        header("Location: customer_form.php?action=" . $source_action . "&id=$customer_id");
        exit;
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

$history = ($action == 'edit' || $action == 'view') ? getHistoryById($history_id) : null;
$contact_persons = $customer_id ? getContactPersons($customer_id) : [];

require_once 'includes/header.php';

// Get customer information
$customer = getCustomerById($customer_id);
?>
    <div class="container">
        <div class="customer-info-panel">
            <div class="info-row">
                <div class="info-item company-name">
                    <label>Company:</label>
                    <span><?php echo htmlspecialchars($customer['company_name']); ?></span>
                </div>
                <div class="info-item status">
                    <label>Status:</label>
                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $customer['status'])); ?>"><?php echo htmlspecialchars($customer['status']); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item half-width">
                    <label>Province:</label>
                    <span><?php echo htmlspecialchars($customer['province'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-item half-width">
                    <label>Country:</label>
                    <span><?php echo htmlspecialchars($customer['country'] ?: 'N/A'); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item half-width">
                    <label>Company Type:</label>
                    <span><?php echo htmlspecialchars($customer['company_type'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-item half-width">
                    <label>Phone:</label>
                    <span><?php echo htmlspecialchars($customer['contact_phone'] ?: 'N/A'); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item half-width">
                    <label>Email:</label>
                    <span><?php echo htmlspecialchars($customer['contact_email'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-item half-width">
                    <label>Website:</label>
                    <span><?php echo htmlspecialchars($customer['website'] ?: 'N/A'); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item full-width">
                    <label>Address:</label>
                    <span><?php echo htmlspecialchars($customer['address'] ?: 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <div class="header">
            <h1><?php echo ucfirst($action); ?> Action History</h1>
            <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn">Back</a>
        </div>
        
        <div class="form-container">
            <form method="post">
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                
                <div class="form-group">
                    <label for="action_datetime">Date & Time:</label>
                    <input type="datetime-local" id="action_datetime" name="action_datetime" required 
                        value="<?php echo $history ? utcToLocal($history['action_datetime'], 'Y-m-d\TH:i') : date('Y-m-d\TH:i'); ?>" 
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
                    <div class="form-actions-row">
                        <div class="form-actions-main">
                            <button type="submit" class="btn">Add Action</button>
                            <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn">Cancel</a>
                        </div>
                    </div>
                    <?php elseif ($action == 'edit'): ?>
                    <div class="form-actions-row">
                        <div class="form-actions-main">
                            <button type="submit" class="btn">Save Action</button>
                            <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn">Cancel</a>
                        </div>
                        <a href="history_form.php?action=delete&id=<?php echo $history_id; ?>&customer_id=<?php echo $customer_id; ?>&source_action=<?php echo $source_action; ?>" 
                        class="btn delete">Delete Action</a>
                    </div>
                    <?php else: ?>
                    <div class="form-actions-row">
                        <div class="form-actions-main">
                            <a href="history_form.php?action=edit&id=<?php echo $history_id; ?>" class="btn">Edit</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
<?php require_once 'includes/footer.php'; ?>
