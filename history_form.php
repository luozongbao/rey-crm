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
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    // Validate customer access
    validateCustomerAccess($customer_id);
    
    // Validate required fields
    $required = ['action_datetime', 'action', 'contact_channel', 'response', 'next_step', 'follow_up_datetime'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            die(__('error') . ': ' . $field . ' ' . __('is_required'));
        }
    }

    // Ensure we have a valid customer_id
    if (empty($customer_id)) {
        die(__('error') . ': ' . __('customer_id_missing'));
    }

    // Convert user's local datetime input to UTC for database storage
    $action_datetime = convertToUTC($_POST['action_datetime']);
    $follow_up_datetime = convertToUTC($_POST['follow_up_datetime']);

    $data = [
        'customer_id' => $customer_id,
        'contact_id' => !empty($_POST['contact_id']) ? $_POST['contact_id'] : null,
        'action_datetime' => $action_datetime,
        'action' => sanitizeHtml(trim($_POST['action'])),
        'contact_channel' => sanitizeHtml(trim($_POST['contact_channel'])),
        'response' => sanitizeHtml(trim($_POST['response'])),
        'next_step' => sanitizeHtml(trim($_POST['next_step'])),
        'follow_up_datetime' => $follow_up_datetime,
        'notes' => $_POST['notes'] ?? null
    ];
    
    global $pdo;
    
    try {
        if ($action == 'add') {
            // Use the new addCustomerHistory function
            $success = addCustomerHistory(
                $customer_id,
                $data['action'],
                $data['contact_channel'],
                $data['response'],
                $data['next_step'],
                $data['follow_up_datetime'],
                $data['contact_id'],
                $data['notes'],
                $_SESSION['user_id'] // Explicitly pass the session user_id
            );
            
            if (!$success) {
                throw new Exception(__('error_adding_activity'));
            }
        } else {
            // For edit, include history_id and verify the record exists
            $existing_history = getHistoryById($history_id);
            if (!$existing_history) {
                die(__('error') . ': ' . __('history_record_not_found'));
            }
            
            $data['history_id'] = $history_id;
            $data['user_id'] = $_SESSION['user_id']; // Track who edited the record
            $stmt = $pdo->prepare("UPDATE action_history SET 
                                 customer_id = :customer_id, 
                                 contact_id = :contact_id, 
                                 user_id = :user_id,
                                 action_datetime = :action_datetime, 
                                 action = :action, 
                                 contact_channel = :contact_channel,
                                 response = :response, 
                                 next_step = :next_step, 
                                 follow_up_datetime = :follow_up_datetime, 
                                 notes = :notes 
                                 WHERE history_id = :history_id");
            $stmt->execute($data);
        }
        
        header("Location: customer_form.php?action=" . $source_action . "&id=$customer_id");
        exit;
    } catch (Exception $e) {
        die(__('error') . ': ' . $e->getMessage());
    } catch (PDOException $e) {
        die(__('database_error') . ': ' . $e->getMessage());
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
                    <label><?php echo __('company'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['company_name']); ?></span>
                </div>
                <div class="info-item status">
                    <label><?php echo __('status'); ?>:</label>
                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $customer['status'])); ?>"><?php echo __($customer['status']); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item half-width">
                    <label><?php echo __('province'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['province'] ?: __('not_available')); ?></span>
                </div>
                <div class="info-item half-width">
                    <label><?php echo __('country'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['country'] ?: __('not_available')); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item half-width">
                    <label><?php echo __('company_type'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['company_type'] ?: __('not_available')); ?></span>
                </div>
                <div class="info-item half-width">
                    <label><?php echo __('contact_phone'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['contact_phone'] ?: __('not_available')); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item half-width">
                    <label><?php echo __('contact_email'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['contact_email'] ?: __('not_available')); ?></span>
                </div>
                <div class="info-item half-width">
                    <label><?php echo __('website'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['website'] ?: __('not_available')); ?></span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-item full-width">
                    <label><?php echo __('address'); ?>:</label>
                    <span><?php echo htmlspecialchars($customer['address'] ?: __('not_available')); ?></span>
                </div>
            </div>
        </div>

        <div class="header">
            <h1>
                <?php
                if ($action === 'add') echo __('add_action_history');
                elseif ($action === 'edit') echo __('edit_action_history');
                elseif ($action === 'view') echo __('view_action_history');
                ?>
            </h1>
            <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn"><?php echo __('back'); ?></a>
        </div>
        
        <div class="form-container">
            <form method="post">
                <?php echo csrfTokenField(); ?>
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                
                <div class="form-group">
                    <label for="action_datetime"><?php echo __('date_time'); ?>:</label>
                    <input type="datetime-local" id="action_datetime" name="action_datetime" required 
                        value="<?php echo $history ? formatDateTime($history['action_datetime'], 'Y-m-d\TH:i') : ''; ?>" 
                        <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label for="contact_id"><?php echo __('contact_person'); ?>:</label>
                    <select id="contact_id" name="contact_id" required <?php echo $isViewMode ? 'disabled' : ''; ?>>
                        <option value="">-- <?php echo __('select_contact'); ?> --</option>
                        <?php foreach ($contact_persons as $contact): ?>
                        <option value="<?php echo $contact['contact_id']; ?>" 
                                <?php echo ($history && $history['contact_id'] == $contact['contact_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($contact['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="action"><?php echo __('action'); ?>:</label>
                    <textarea id="action" name="action" required <?php echo $isViewMode ? 'disabled' : ''; ?>><?php 
                        echo $history ? htmlspecialchars($history['action']) : ''; 
                    ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="contact_channel"><?php echo __('contact_channel'); ?> *</label>
                    <select name="contact_channel" id="contact_channel" required <?php echo $isViewMode ? 'disabled' : ''; ?>>
                        <option value=""><?php echo __('select_contact_channel'); ?></option>
                        <option value="Email" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'Email') ? 'selected' : ''; ?>>
                            <?php echo __('email'); ?>
                        </option>
                        <option value="Phone Call" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'Phone Call') ? 'selected' : ''; ?>>
                            <?php echo __('phone_call'); ?>
                        </option>
                        <option value="WhatsApp" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'WhatsApp') ? 'selected' : ''; ?>>
                            <?php echo __('whatsapp'); ?>
                        </option>
                        <option value="SMS" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'SMS') ? 'selected' : ''; ?>>
                            <?php echo __('sms'); ?>
                        </option>
                        <option value="In-Person Meeting" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'In-Person Meeting') ? 'selected' : ''; ?>>
                            <?php echo __('in_person_meeting'); ?>
                        </option>
                        <option value="Video Call" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'Video Call') ? 'selected' : ''; ?>>
                            <?php echo __('video_call'); ?>
                        </option>
                        <option value="LinkedIn" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'LinkedIn') ? 'selected' : ''; ?>>
                            <?php echo __('linkedin'); ?>
                        </option>
                        <option value="WeChat" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'WeChat') ? 'selected' : ''; ?>>
                            <?php echo __('wechat'); ?>
                        </option>
                        <option value="Other" <?php echo (isset($history['contact_channel']) && $history['contact_channel'] == 'Other') ? 'selected' : ''; ?>>
                            <?php echo __('other'); ?>
                        </option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="response"><?php echo __('response'); ?>:</label>
                    <textarea id="response" name="response" required <?php echo $isViewMode ? 'disabled' : ''; ?>><?php 
                        echo $history ? htmlspecialchars($history['response']) : ''; 
                    ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="next_step"><?php echo __('next_step'); ?>:</label>
                    <textarea id="next_step" name="next_step" required <?php echo $isViewMode ? 'disabled' : ''; ?>><?php 
                        echo $history ? htmlspecialchars($history['next_step']) : ''; 
                    ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="follow_up_datetime"><?php echo __('follow_up_date_time'); ?>:</label>
                    <input type="datetime-local" id="follow_up_datetime" name="follow_up_datetime" required 
                        value="<?php echo $history ? formatDateTime($history['follow_up_datetime'], 'Y-m-d\TH:i') : ''; ?>" 
                        <?php echo $isViewMode ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label for="notes"><?php echo __('notes'); ?>:</label>
                    <textarea id="notes" name="notes" <?php echo $isViewMode ? 'disabled' : ''; ?>><?php 
                        echo $history ? htmlspecialchars($history['notes']) : ''; 
                    ?></textarea>
                </div>
                
                <div class="form-actions">
                    <?php if ($action == 'add'): ?>
                    <div class="form-actions-row">
                        <div class="form-actions-main">
                            <button type="submit" class="btn"><?php echo __('add_action'); ?></button>
                            <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn"><?php echo __('cancel'); ?></a>
                        </div>
                    </div>
                    <?php elseif ($action == 'edit'): ?>
                    <div class="form-actions-row">
                        <div class="form-actions-main">
                            <button type="submit" class="btn"><?php echo __('save_action'); ?></button>
                            <a href="customer_form.php?action=<?php echo $source_action; ?>&id=<?php echo $customer_id; ?>" class="btn"><?php echo __('cancel'); ?></a>
                        </div>
                        <a href="history_form.php?action=delete&id=<?php echo $history_id; ?>&customer_id=<?php echo $customer_id; ?>&source_action=<?php echo $source_action; ?>" 
                        class="btn delete"><?php echo __('delete_action'); ?></a>
                    </div>
                    <?php else: ?>
                    <div class="form-actions-row">
                        <div class="form-actions-main">
                            <a href="history_form.php?action=edit&id=<?php echo $history_id; ?>" class="btn"><?php echo __('edit'); ?></a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
<?php require_once 'includes/footer.php'; ?>
