<?php
/**
 * Customer Status Management Functions
 * Handles customer status changes, translations, and timeline tracking
 */

require_once 'config.php';

/**
 * Get all customer statuses with translations
 * @param string $locale Language locale (en, zh-cn)
 * @return array Array of status objects
 */
function getCustomerStatuses($locale = 'en') {
    global $pdo;
    
    $sql = "SELECT 
                cs.id,
                cs.status_key,
                cs.sort_order,
                cst.name,
                cst.description
            FROM customer_statuses cs
            LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = ?
            WHERE cs.is_active = TRUE
            ORDER BY cs.sort_order";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$locale]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all customer statuses with all translations (for admin management)
 * @return array Array of status objects with all translations
 */
function getAllCustomerStatusesWithTranslations() {
    global $pdo;
    
    $sql = "SELECT 
                cs.id,
                cs.status_key,
                cs.sort_order,
                cs.is_active,
                cs.created_at,
                cs.updated_at,
                en_trans.name as en_name,
                en_trans.description as en_description,
                zh_trans.name as zh_name,
                zh_trans.description as zh_description
            FROM customer_statuses cs
            LEFT JOIN customer_status_translations en_trans ON cs.id = en_trans.status_id AND en_trans.locale = 'en'
            LEFT JOIN customer_status_translations zh_trans ON cs.id = zh_trans.status_id AND zh_trans.locale = 'zh-cn'
            ORDER BY cs.sort_order";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get customer status by key with translation
 * @param string $status_key Status key (prospect, qualified, etc.)
 * @param string $locale Language locale
 * @return array|null Status object or null if not found
 */
function getCustomerStatusByKey($status_key, $locale = 'en') {
    global $pdo;
    
    $sql = "SELECT 
                cs.id,
                cs.status_key,
                cs.sort_order,
                cst.name,
                cst.description
            FROM customer_statuses cs
            LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = ?
            WHERE cs.status_key = ? AND cs.is_active = TRUE";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$locale, $status_key]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get customer status by ID with translation
 * @param int $status_id Status ID
 * @param string $locale Language locale
 * @return array|null Status object or null if not found
 */
function getCustomerStatusById($status_id, $locale = 'en') {
    global $pdo;
    
    $sql = "SELECT 
                cs.id,
                cs.status_key,
                cs.sort_order,
                cst.name,
                cst.description
            FROM customer_statuses cs
            LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = ?
            WHERE cs.id = ? AND cs.is_active = TRUE";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$locale, $status_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Change customer status and record in timeline
 * @param int $customer_id Customer ID
 * @param string $new_status_key New status key
 * @param int $changed_by User ID who made the change
 * @param string $notes Optional notes about the change
 * @return bool True if successful, false otherwise
 */
function changeCustomerStatus($customer_id, $new_status_key, $changed_by, $notes = '') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get current customer status
        $stmt = $pdo->prepare("SELECT status_id FROM customers WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $current_status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current_status) {
            throw new Exception("Customer not found");
        }
        
        // Get new status ID
        $stmt = $pdo->prepare("SELECT id FROM customer_statuses WHERE status_key = ? AND is_active = TRUE");
        $stmt->execute([$new_status_key]);
        $new_status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$new_status) {
            throw new Exception("Invalid status key: " . $new_status_key);
        }
        
        $new_status_id = $new_status['id'];
        $current_status_id = $current_status['status_id'];
        
        // Don't change if it's the same status
        if ($current_status_id == $new_status_id) {
            $pdo->rollBack();
            return true;
        }
        
        // Update customer status
        $stmt = $pdo->prepare("UPDATE customers SET 
                                status_id = ?, 
                                status_changed_at = NOW(), 
                                status_changed_by = ?,
                                updated_at = NOW()
                              WHERE customer_id = ?");
        $stmt->execute([$new_status_id, $changed_by, $customer_id]);
        
        // Record status change in history
        $stmt = $pdo->prepare("INSERT INTO customer_status_history 
                              (customer_id, from_status_id, to_status_id, changed_by, changed_at, notes) 
                              VALUES (?, ?, ?, ?, NOW(), ?)");
        $stmt->execute([$customer_id, $current_status_id, $new_status_id, $changed_by, $notes]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error changing customer status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get customer status timeline
 * @param int $customer_id Customer ID
 * @param string $locale Language locale for translations
 * @return array Array of status change records
 */
function getCustomerStatusTimeline($customer_id, $locale = 'en') {
    global $pdo;
    
    $sql = "SELECT 
                csh.id,
                csh.changed_at,
                csh.notes,
                u.username as changed_by_username,
                from_status.name as from_status_name,
                cs_from.status_key as from_status_key,
                to_status.name as to_status_name,
                cs_to.status_key as to_status_key
            FROM customer_status_history csh
            LEFT JOIN users u ON csh.changed_by = u.user_id
            LEFT JOIN customer_statuses cs_from ON csh.from_status_id = cs_from.id
            LEFT JOIN customer_status_translations from_status ON cs_from.id = from_status.status_id AND from_status.locale = ?
            LEFT JOIN customer_statuses cs_to ON csh.to_status_id = cs_to.id
            LEFT JOIN customer_status_translations to_status ON cs_to.id = to_status.status_id AND to_status.locale = ?
            WHERE csh.customer_id = ?
            ORDER BY csh.changed_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$locale, $locale, $customer_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get customer status statistics
 * @param string $locale Language locale
 * @param int $assigned_user_id Optional user ID to filter by assigned user
 * @return array Status distribution statistics
 */
function getCustomerStatusStats($locale = 'en', $assigned_user_id = null) {
    global $pdo;
    
    $where_clause = "";
    $params = [$locale];
    
    if ($assigned_user_id) {
        $where_clause = "WHERE c.assigned_user_id = ?";
        $params[] = $assigned_user_id;
    }
    
    $sql = "SELECT 
                cs.status_key,
                cst.name as status_name,
                COUNT(c.customer_id) as count
            FROM customer_statuses cs
            LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = ?
            LEFT JOIN customers c ON cs.id = c.status_id
            $where_clause
            WHERE cs.is_active = TRUE
            GROUP BY cs.id, cs.status_key, cst.name
            ORDER BY cs.sort_order";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get customers by status
 * @param string $status_key Status key to filter by
 * @param int $assigned_user_id Optional user ID to filter by assigned user
 * @param int $limit Optional limit for results
 * @param int $offset Optional offset for pagination
 * @return array Array of customers with the specified status
 */
function getCustomersByStatus($status_key, $assigned_user_id = null, $limit = null, $offset = 0) {
    global $pdo;
    
    $where_clauses = ["cs.status_key = ?"];
    $params = [$status_key];
    
    if ($assigned_user_id) {
        $where_clauses[] = "c.assigned_user_id = ?";
        $params[] = $assigned_user_id;
    }
    
    $limit_clause = "";
    if ($limit) {
        $limit_clause = "LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
    }
    
    $sql = "SELECT 
                c.*,
                cs.status_key,
                u.username as assigned_username
            FROM customers c
            JOIN customer_statuses cs ON c.status_id = cs.id
            LEFT JOIN users u ON c.assigned_user_id = u.user_id
            WHERE " . implode(" AND ", $where_clauses) . "
            ORDER BY c.updated_at DESC
            $limit_clause";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if status transition is valid
 * @param string $from_status_key Current status key
 * @param string $to_status_key Target status key
 * @return bool True if transition is allowed
 */
function isValidStatusTransition($from_status_key, $to_status_key) {
    // Allow same status (no change)
    if ($from_status_key === $to_status_key) {
        return true;
    }
    
    // Define more flexible status transitions based on business rules
    $allowed_transitions = [
        'lead' => ['prospect', 'qualified', 'not_qualified', 'new_customer', 'active_customer', 'lost_customer'],
        'prospect' => ['qualified', 'not_qualified', 'lead', 'new_customer', 'active_customer', 'lost_customer'], 
        'qualified' => ['new_customer', 'active_customer', 'lost_customer', 'not_qualified', 'prospect', 'lead'],
        'not_qualified' => ['prospect', 'qualified', 'lead', 'new_customer', 'active_customer', 'lost_customer'],
        'new_customer' => ['active_customer', 'lost_customer', 'prospect', 'qualified', 'lead'],
        'active_customer' => ['lost_customer', 'new_customer', 'prospect', 'qualified', 'lead'],
        'lost_customer' => ['lead', 'prospect', 'qualified', 'new_customer', 'active_customer']
    ];
    
    // If no restrictions defined for the status, allow any transition
    if (!isset($allowed_transitions[$from_status_key])) {
        return true;
    }
    
    return in_array($to_status_key, $allowed_transitions[$from_status_key]);
}

/**
 * Get status transition options for a customer
 * @param int $customer_id Customer ID
 * @param string $locale Language locale
 * @return array Array of valid status transitions
 */
function getValidStatusTransitions($customer_id, $locale = 'en') {
    global $pdo;
    
    // Get current status
    $stmt = $pdo->prepare("SELECT cs.status_key 
                          FROM customers c 
                          JOIN customer_statuses cs ON c.status_id = cs.id 
                          WHERE c.customer_id = ?");
    $stmt->execute([$customer_id]);
    $current_status = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_status) {
        return [];
    }
    
    // Get all available statuses
    $all_statuses = getCustomerStatuses($locale);
    
    // Filter by valid transitions
    $valid_transitions = [];
    foreach ($all_statuses as $status) {
        if ($status['status_key'] !== $current_status['status_key'] && 
            isValidStatusTransition($current_status['status_key'], $status['status_key'])) {
            $valid_transitions[] = $status;
        }
    }
    
    return $valid_transitions;
}
?>
