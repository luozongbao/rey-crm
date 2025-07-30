<?php
// Check if config file exists, if not redirect to install
if (!file_exists(__DIR__ . '/config.php') && basename($_SERVER['PHP_SELF']) !== 'install.php') {
    if (!headers_sent()) {
        header('Location: includes/install.php');
        exit;
    } else {
        echo '<script>window.location.href = "/install.php";</script>';
        echo 'If you are not redirected, <a href="/install.php">click here</a>.';
        exit;
    }
}

require_once 'config.php';

// Load Composer autoloader for PHPMailer and other dependencies
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

function getAllCustomers() {
    global $pdo;
    $stmt = $pdo->query("SELECT c.*, 
                        CONCAT_WS(', ', 
                            c.address,
                            NULLIF(c.province, ''),
                            NULLIF(c.country, '')
                        ) as full_address,
                        (SELECT MAX(action_datetime) FROM action_history WHERE customer_id = c.customer_id) as last_contact
                        FROM customers c ORDER BY company_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCustomerById($id) {
    global $pdo;
    $current_locale = getCurrentLanguage();
    
    $stmt = $pdo->prepare("SELECT c.*, 
                          CONCAT_WS(', ', 
                              c.address,
                              NULLIF(c.province, ''),
                              NULLIF(c.country, '')
                          ) as full_address,
                          u.username as assigned_to_username,
                          cs.status_key,
                          cst.name as status_name
                          FROM customers c
                          LEFT JOIN users u ON c.assigned_user_id = u.user_id
                          LEFT JOIN customer_statuses cs ON c.status_id = cs.id
                          LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = ?
                          WHERE c.customer_id = ?");
    $stmt->execute([$current_locale, $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getContactPersons($customer_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM contact_persons WHERE customer_id = ? ORDER BY name");
    $stmt->execute([$customer_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getActionHistory($customer_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT ah.*, cp.name as contact_name 
                          FROM action_history ah
                          LEFT JOIN contact_persons cp ON ah.contact_id = cp.contact_id
                          WHERE ah.customer_id = ? 
                          ORDER BY ah.action_datetime DESC");
    $stmt->execute([$customer_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUpcomingFollowups($limit = 5) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT ah.*, c.company_name, cp.name as contact_name, u.username as created_by_username
                              FROM action_history ah
                              JOIN customers c ON ah.customer_id = c.customer_id
                              LEFT JOIN contact_persons cp ON ah.contact_id = cp.contact_id
                              LEFT JOIN users u ON ah.user_id = u.user_id
                              WHERE ah.follow_up_datetime >= :datetime
                              ORDER BY ah.follow_up_datetime ASC
                              LIMIT :limit");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':datetime', getCurrentUTCDateTime());
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting upcoming followups: " . $e->getMessage());
        return [];
    }
}

function getRecentActivities($limit = 10) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT ah.*, c.company_name, cp.name as contact_name, u.username as created_by_username
                              FROM action_history ah
                              JOIN customers c ON ah.customer_id = c.customer_id
                              LEFT JOIN contact_persons cp ON ah.contact_id = cp.contact_id
                              LEFT JOIN users u ON ah.user_id = u.user_id
                              ORDER BY ah.action_datetime DESC
                              LIMIT :limit");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting recent activities: " . $e->getMessage());
        return [];
    }
}

function getContactPersonById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM contact_persons WHERE contact_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getHistoryById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM action_history WHERE history_id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function deleteCustomer($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM customers WHERE customer_id = ?");
    return $stmt->execute([$id]);
}

function deleteContactPerson($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM contact_persons WHERE contact_id = ?");
    return $stmt->execute([$id]);
}

function deleteHistory($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM action_history WHERE history_id = ?");
    return $stmt->execute([$id]);
}

function getTotalCustomers() {
    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers");
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function getLocationStats() {
    global $pdo;
    $stmt = $pdo->query("SELECT 
                        CASE
                            WHEN NULLIF(TRIM(province), '') IS NULL AND NULLIF(TRIM(country), '') IS NULL THEN 'N/A'
                            WHEN NULLIF(TRIM(province), '') IS NULL THEN NULLIF(TRIM(country), '')
                            WHEN NULLIF(TRIM(country), '') IS NULL THEN NULLIF(TRIM(province), '')
                            ELSE CONCAT(TRIM(province), ', ', TRIM(country))
                        END as location,
                        COUNT(*) as count 
                        FROM customers 
                        GROUP BY 
                            CASE
                                WHEN NULLIF(TRIM(province), '') IS NULL AND NULLIF(TRIM(country), '') IS NULL THEN 'N/A'
                                WHEN NULLIF(TRIM(province), '') IS NULL THEN NULLIF(TRIM(country), '')
                                WHEN NULLIF(TRIM(country), '') IS NULL THEN NULLIF(TRIM(province), '')
                                ELSE CONCAT(TRIM(province), ', ', TRIM(country))
                            END
                        ORDER BY 
                            CASE WHEN location = 'N/A' THEN 1 ELSE 0 END,
                            location");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getLastContactedCustomer() {
    global $pdo;
    $stmt = $pdo->query("SELECT c.* FROM customers c 
                         JOIN action_history ah ON c.customer_id = ah.customer_id 
                         ORDER BY ah.created_at DESC LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getContactStats() {
    global $pdo;
    $total = getTotalCustomers();
    $contacted = $pdo->query("SELECT COUNT(DISTINCT customer_id) as count FROM action_history")->fetchColumn();
    return [
        'contacted' => $contacted,
        'not_contacted' => $total - $contacted,
        'total' => $total
    ];
}

function updateLastContactedDate($customer_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE customers SET last_contacted_date = ? WHERE customer_id = ?");
        return $stmt->execute([getCurrentUTCDateTime(), $customer_id]);
    } catch (PDOException $e) {
        // Log the error but don't stop execution
        error_log("Error updating last contacted date: " . $e->getMessage());
        return false;
    }
}

function getHistoryForExport($start_date, $end_date) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT ah.*, c.company_name, cp.name as contact_name
                          FROM action_history ah
                          JOIN customers c ON ah.customer_id = c.customer_id
                          LEFT JOIN contact_persons cp ON ah.contact_id = cp.contact_id
                          WHERE DATE(ah.action_datetime) BETWEEN :start_date AND :end_date
                          ORDER BY ah.action_datetime DESC");
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFollowupsForExport($start_date, $end_date) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT ah.*, c.company_name, cp.name as contact_name
                          FROM action_history ah
                          JOIN customers c ON ah.customer_id = c.customer_id
                          LEFT JOIN contact_persons cp ON ah.contact_id = cp.contact_id
                          WHERE DATE(ah.follow_up_datetime) BETWEEN :start_date AND :end_date
                          ORDER BY ah.follow_up_datetime ASC");
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllLocations($showOnlyMine = false) {
    global $pdo;
    try {
        $sql = "SELECT DISTINCT 
                    CASE
                        WHEN NULLIF(TRIM(province), '') IS NULL AND NULLIF(TRIM(country), '') IS NULL THEN 'N/A'
                        WHEN NULLIF(TRIM(province), '') IS NULL THEN TRIM(country)
                        WHEN NULLIF(TRIM(country), '') IS NULL THEN TRIM(province)
                        ELSE CONCAT(TRIM(province), ', ', TRIM(country))
                    END as location
                    FROM customers";
        
        if ($showOnlyMine) {
            $sql .= " WHERE assigned_user_id = ?";
            $stmt = $pdo->prepare($sql . " ORDER BY CASE WHEN location = 'N/A' THEN 1 ELSE 0 END, location");
            $stmt->execute([$_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare($sql . " ORDER BY CASE WHEN location = 'N/A' THEN 1 ELSE 0 END, location");
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error in getAllLocations: " . $e->getMessage());
        return [];
    }
}

function getFilteredCustomers($conditions = [], $params = []) {
    global $pdo;
    
    $language = getCurrentLanguage();
    
    $query = "SELECT c.*, 
             (SELECT MAX(action_datetime) FROM action_history WHERE customer_id = c.customer_id) as last_contact,
             cs.status_key,
             cst.status_name
             FROM customers c
             LEFT JOIN customer_statuses cs ON c.status_id = cs.id
             LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.language = ?";
    
    // Add language parameter
    if (empty($params)) {
        $params = [$language];
    } else {
        array_unshift($params, $language);
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY company_name";
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => &$val) {
        $stmt->bindParam($key, $val);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCustomerCount($conditions = [], $params = []) {
    global $pdo;
    
    $query = "SELECT COUNT(*) FROM customers";
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => &$val) {
        $stmt->bindParam($key, $val);
    }
    
    $stmt->execute();
    return $stmt->fetchColumn();
}

function getPaginatedCustomers($page = 1, $perPage = 10, $search = '', $location = '', $sort = 'created_at', $order = 'desc', $showOnlyMine = true) {
    global $pdo;
    
    // Validate sort/order
    $validSorts = ['company_name', 'address', 'status', 'created_at'];
    $validOrders = ['asc', 'desc'];
    $sort = in_array($sort, $validSorts) ? $sort : 'created_at';
    $order = in_array($order, $validOrders) ? $order : 'desc';
    
    // Calculate offset
    $offset = ($page - 1) * $perPage;
    
    // Build query with status joins
    $query = "SELECT c.*, 
             (SELECT MAX(action_datetime) FROM action_history WHERE customer_id = c.customer_id) as last_contact,
             CONCAT_WS(', ', 
                NULLIF(TRIM(c.province), ''),
                NULLIF(TRIM(c.country), '')
            ) as location,
             CONCAT_WS(', ', 
                c.address,
                NULLIF(c.province, ''),
                NULLIF(c.country, '')
            ) as full_address,
             u.username as assigned_to_username,
             cs.sort_order,
             cs.status_key,
             cst.name as status_name
             FROM customers c
             LEFT JOIN users u ON c.assigned_user_id = u.user_id
             LEFT JOIN customer_statuses cs ON c.status_id = cs.id
             LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = :locale";
    
    $countQuery = "SELECT COUNT(*) FROM customers c
                   LEFT JOIN customer_statuses cs ON c.status_id = cs.id";
    
    $conditions = [];
    $params = [];
    
    // Add locale parameter for status translations
    $params[':locale'] = getCurrentLanguage();
    
    // Add user assignment filter
    if ($showOnlyMine) {
        $conditions[] = "c.assigned_user_id = :current_user_id";
        $params[':current_user_id'] = $_SESSION['user_id'];
    }
    
    if (!empty($search)) {
        $searchPattern = "%$search%";
        $conditions[] = "(LOWER(c.company_name) LIKE LOWER(:search_name) OR c.contact_phone LIKE :search_phone)";
        $params[':search_name'] = $searchPattern;
        $params[':search_phone'] = $searchPattern;
    }
    
    if (!empty($location)) {
        if ($location === 'N/A') {
            $conditions[] = "(NULLIF(TRIM(c.province), '') IS NULL AND NULLIF(TRIM(c.country), '') IS NULL)";
        } else {
            $conditions[] = "(CONCAT_WS(', ', 
                NULLIF(TRIM(c.province), ''),
                NULLIF(TRIM(c.country), '')
            ) = :location)";
            $params[':location'] = $location;
        }
    }
    
    if (!empty($conditions)) {
        $whereClause = " WHERE " . implode(" AND ", $conditions);
        $query .= $whereClause;
        $countQuery .= $whereClause;
    }
    
    // Handle sorting - map 'status' to proper sort column
    $sortColumn = $sort;
    if ($sort === 'status') {
        $sortColumn = 'cs.sort_order'; // Sort by status order, not name
    }
    
    // Add sorting and pagination
    $query .= " ORDER BY $sortColumn $order LIMIT :limit OFFSET :offset";
    
    // Get total count
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $val) {
        if ($key !== ':locale') { // Don't bind locale for count query since it doesn't have the join
            $countStmt->bindValue($key, $val);
        }
    }
    $countStmt->execute();
    $total = $countStmt->fetchColumn();
    
    // Get paginated results
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return [
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total' => $total,
        'pages' => ceil($total / $perPage),
        'current_page' => $page
    ];
}

function buildQueryString($newParams = []) {
    $params = $_GET;
    foreach ($newParams as $key => $value) {
        $params[$key] = $value;
    }
    return http_build_query($params);
}

/**
 * Returns the current page state as a URL query string
 * @param array $additionalParams Additional parameters to include in the URL
 * @return string The URL with preserved state
 */
function buildUrlWithState($additionalParams = []) {
    if (isset($_SESSION['last_page_state'])) {
        return '?' . http_build_query(array_merge($_SESSION['last_page_state'], $additionalParams));
    }
    return '?' . http_build_query($additionalParams);
}

function addCustomer($data) {
    global $pdo;
    
    require_once __DIR__ . '/customer_status_functions.php';
    
    try {
        $pdo->beginTransaction();
        
        // Get status_id from status_key
        $status_key = $data['status_key'] ?? 'prospect';
        $status = getCustomerStatusByKey($status_key);
        if (!$status) {
            throw new Exception("Invalid status key: " . $status_key);
        }
        
        $stmt = $pdo->prepare("INSERT INTO customers 
                              (company_name, address, country, province, company_type, contact_phone, 
                               contact_email, website, status_id, status_changed_at, status_changed_by, 
                               notes, assigned_user_id, created_by_user_id) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)");
        
        // Set assignment fields - assign to current user by default
        $assigned_user_id = $data['assigned_user_id'] ?? $_SESSION['user_id'];
        $created_by_user_id = $_SESSION['user_id'];
        
        $success = $stmt->execute([
            $data['company_name'],
            $data['address'],
            $data['country'],
            $data['province'],
            $data['company_type'],
            $data['contact_phone'],
            $data['contact_email'],
            $data['website'],
            $status['id'],
            $created_by_user_id,
            $data['notes'],
            $assigned_user_id,
            $created_by_user_id
        ]);
        
        if ($success) {
            $customer_id = $pdo->lastInsertId();
            
            // Record initial status in history
            $stmt = $pdo->prepare("INSERT INTO customer_status_history 
                                  (customer_id, from_status_id, to_status_id, changed_by, changed_at, notes) 
                                  VALUES (?, NULL, ?, ?, NOW(), 'Initial customer creation')");
            $stmt->execute([$customer_id, $status['id'], $created_by_user_id]);
        }
        
        $pdo->commit();
        return $success;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error adding customer: " . $e->getMessage());
        return false;
    }
}

function updateCustomer($id, $data) {
    global $pdo;
    
    require_once __DIR__ . '/customer_status_functions.php';
    
    try {
        $pdo->beginTransaction();
        
        // Get current customer data
        $current_customer = getCustomerById($id);
        if (!$current_customer) {
            throw new Exception("Customer not found");
        }
        
        // Handle status change if provided
        $status_changed = false;
        $new_status_id = $current_customer['status_id'];
        
        if (isset($data['status_key'])) {
            $new_status = getCustomerStatusByKey($data['status_key']);
            if (!$new_status) {
                throw new Exception("Invalid status key: " . $data['status_key']);
            }
            
            if ($new_status['id'] != $current_customer['status_id']) {
                // Validate status transition
                if (!isValidStatusTransition($current_customer['status_key'], $data['status_key'])) {
                    throw new Exception("Invalid status transition");
                }
                
                $new_status_id = $new_status['id'];
                $status_changed = true;
            }
        }
        
        // Check if assigned_user_id is provided and user has permission to assign
        $updateAssignment = isset($data['assigned_user_id']) && canAssignCustomer($id);
        
        if ($updateAssignment) {
            // Convert empty string to NULL for unassignment
            $assigned_user_id = $data['assigned_user_id'];
            if ($assigned_user_id === '' || $assigned_user_id === null) {
                $assigned_user_id = null;
            }
            
            $stmt = $pdo->prepare("UPDATE customers SET 
                                  company_name = ?, address = ?, country = ?, province = ?,
                                  company_type = ?, contact_phone = ?, contact_email = ?, 
                                  website = ?, status_id = ?, notes = ?, assigned_user_id = ?,
                                  " . ($status_changed ? "status_changed_at = NOW(), status_changed_by = ?, " : "") . "
                                  updated_at = NOW()
                                  WHERE customer_id = ?");
            
            $params = [
                $data['company_name'],
                $data['address'],
                $data['country'],
                $data['province'],
                $data['company_type'],
                $data['contact_phone'],
                $data['contact_email'],
                $data['website'],
                $new_status_id,
                $data['notes'],
                $assigned_user_id
            ];
            
            if ($status_changed) {
                $params[] = $_SESSION['user_id'];
            }
            
            $params[] = $id;
            
        } else {
            $stmt = $pdo->prepare("UPDATE customers SET 
                                  company_name = ?, address = ?, country = ?, province = ?,
                                  company_type = ?, contact_phone = ?, contact_email = ?, 
                                  website = ?, status_id = ?, notes = ?,
                                  " . ($status_changed ? "status_changed_at = NOW(), status_changed_by = ?, " : "") . "
                                  updated_at = NOW()
                                  WHERE customer_id = ?");
            
            $params = [
                $data['company_name'],
                $data['address'],
                $data['country'],
                $data['province'],
                $data['company_type'],
                $data['contact_phone'],
                $data['contact_email'],
                $data['website'],
                $new_status_id,
                $data['notes']
            ];
            
            if ($status_changed) {
                $params[] = $_SESSION['user_id'];
            }
            
            $params[] = $id;
        }
        
        $success = $stmt->execute($params);
        
        // Record status change in history if status changed
        if ($success && $status_changed) {
            $stmt = $pdo->prepare("INSERT INTO customer_status_history 
                                  (customer_id, from_status_id, to_status_id, changed_by, changed_at, notes) 
                                  VALUES (?, ?, ?, ?, NOW(), 'Status updated via customer form')");
            $stmt->execute([$id, $current_customer['status_id'], $new_status_id, $_SESSION['user_id']]);
        }
        
        $pdo->commit();
        return $success;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating customer: " . $e->getMessage());
        return false;
    }
}

function getCustomerStatusCounts() {
    global $pdo;
    
    $current_locale = getCurrentLanguage();
    
    $stmt = $pdo->prepare("SELECT cs.status_key, cst.name, COUNT(c.customer_id) as count 
                          FROM customer_statuses cs
                          LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = ?
                          LEFT JOIN customers c ON cs.id = c.status_id
                          WHERE cs.is_active = TRUE
                          GROUP BY cs.id, cs.status_key, cst.name
                          ORDER BY cs.sort_order");
    $stmt->execute([$current_locale]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $counts = [];
    foreach ($results as $row) {
        $counts[$row['status_key']] = $row['count'];
    }
    
    return $counts;
}

function getCustomerStatusOptions($locale = null) {
    if ($locale === null) {
        $locale = getCurrentLanguage();
    }
    
    require_once __DIR__ . '/customer_status_functions.php';
    
    $statuses = getCustomerStatuses($locale);
    $options = [];
    
    foreach ($statuses as $status) {
        $options[$status['status_key']] = $status['name'];
    }
    
    return $options;
}

function getSortedCustomers($search = '', $location = '', $sort = 'created_at', $order = 'desc') {
    global $pdo;
    
    // Validate sort/order
    $validSorts = ['company_name', 'address', 'status', 'created_at'];
    $validOrders = ['asc', 'desc'];
    $sort = in_array($sort, $validSorts) ? $sort : 'created_at';
    $order = in_array($order, $validOrders) ? $order : 'desc';
    
    // Build query
    $query = "SELECT c.*, 
             (SELECT MAX(action_datetime) FROM action_history WHERE customer_id = c.customer_id) as last_contact
             FROM customers c";
    
    $conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $conditions[] = "(LOWER(company_name) LIKE LOWER(:search) OR contact_phone LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if (!empty($location)) {
        $conditions[] = "CASE
            WHEN NULLIF(TRIM(province), '') IS NULL AND NULLIF(TRIM(country), '') IS NULL THEN 'N/A'
            WHEN NULLIF(TRIM(province), '') IS NULL THEN TRIM(country)
            WHEN NULLIF(TRIM(country), '') IS NULL THEN TRIM(province)
            ELSE CONCAT(TRIM(province), ', ', TRIM(country))
        END = :location";
        $params[':location'] = $location;
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY $sort $order";
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFilteredFollowups($customer_id = '', $date_from = '', $date_to = '', $sort = 'follow_up_datetime', $order = 'asc', $customer_status = '', $showOnlyMine = true) {
    global $pdo;
    
    $current_locale = getCurrentLanguage();
    
    $query = "SELECT ah.*, c.company_name, cs.status_key, cst.name as customer_status, c.province, c.customer_id,
                     u.username as created_by_username, au.username as assigned_username
              FROM action_history ah
              JOIN customers c ON ah.customer_id = c.customer_id
              LEFT JOIN customer_statuses cs ON c.status_id = cs.id
              LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = :locale
              LEFT JOIN users u ON ah.user_id = u.user_id
              LEFT JOIN users au ON c.assigned_user_id = au.user_id
              WHERE 1=1";
    
    $params = [':locale' => $current_locale];
    
    // Add user activity filter - show followups created by current user
    if ($showOnlyMine) {
        $query .= " AND ah.user_id = :current_user_id";
        $params[':current_user_id'] = $_SESSION['user_id'];
    }
    
    if (!empty($customer_id)) {
        $query .= " AND ah.customer_id = :customer_id";
        $params[':customer_id'] = $customer_id;
    }
    
    if (!empty($date_from)) {
        $query .= " AND DATE(ah.follow_up_datetime) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND DATE(ah.follow_up_datetime) <= :date_to";
        $params[':date_to'] = $date_to;
    }
    
    if (!empty($customer_status)) {
        if ($customer_status === 'All Except Not Qualified') {
            $query .= " AND cs.status_key != 'not_qualified'";
        } else {
            // Convert old status names to status keys for backward compatibility
            $status_mapping = [
                'Prospect' => 'prospect',
                'Qualified' => 'qualified', 
                'Not Qualified' => 'not_qualified',
                'New Customer' => 'new_customer',
                'Active Customer' => 'active_customer',
                'Lost Customer' => 'lost_customer'
            ];
            $status_key = $status_mapping[$customer_status] ?? $customer_status;
            $query .= " AND cs.status_key = :customer_status";
            $params[':customer_status'] = $status_key;
        }
    }
    
    $validSorts = ['company_name', 'follow_up_datetime', 'action_datetime', 'customer_status', 'contact_channel'];
    $sort = in_array($sort, $validSorts) ? $sort : 'follow_up_datetime';
    $order = in_array($order, ['asc', 'desc']) ? $order : 'asc';
    
    // Handle sorting by customer_status (which is now cst.name in the query)
    if ($sort === 'customer_status') {
        $sort = 'cst.name';
    } elseif ($sort === 'contact_channel') {
        $sort = 'ah.contact_channel';
    }
    
    $query .= " ORDER BY $sort $order";
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFilteredActivities($customer_id = '', $date_from = '', $date_to = '', $sort = 'action_datetime', $order = 'desc', $customer_status = '', $showOnlyMine = true) {
    global $pdo;
    
    $current_locale = getCurrentLanguage();
    
    $query = "SELECT ah.*, c.company_name, cs.status_key, cst.name as customer_status, c.province, c.customer_id,
                     u.username as created_by_username, au.username as assigned_username
              FROM action_history ah
              JOIN customers c ON ah.customer_id = c.customer_id
              LEFT JOIN customer_statuses cs ON c.status_id = cs.id
              LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = :locale
              LEFT JOIN users u ON ah.user_id = u.user_id
              LEFT JOIN users au ON c.assigned_user_id = au.user_id
              WHERE 1=1";
    
    $params = [':locale' => $current_locale];
    
    // Add user activity filter - show activities created by current user
    if ($showOnlyMine) {
        $query .= " AND ah.user_id = :current_user_id";
        $params[':current_user_id'] = $_SESSION['user_id'];
    }
    
    if (!empty($customer_id)) {
        $query .= " AND ah.customer_id = :customer_id";
        $params[':customer_id'] = $customer_id;
    }
    
    if (!empty($date_from)) {
        $query .= " AND DATE(ah.action_datetime) >= :date_from";
        $params[':date_from'] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND DATE(ah.action_datetime) <= :date_to";
        $params[':date_to'] = $date_to;
    }
    
    if (!empty($customer_status)) {
        if ($customer_status === 'All Except Not Qualified') {
            $query .= " AND cs.status_key != 'not_qualified'";
        } else {
            // Convert old status names to status keys for backward compatibility
            $status_mapping = [
                'Prospect' => 'prospect',
                'Qualified' => 'qualified', 
                'Not Qualified' => 'not_qualified',
                'New Customer' => 'new_customer',
                'Active Customer' => 'active_customer',
                'Lost Customer' => 'lost_customer'
            ];
            $status_key = $status_mapping[$customer_status] ?? $customer_status;
            $query .= " AND cs.status_key = :customer_status";
            $params[':customer_status'] = $status_key;
        }
    }
    
    $validSorts = ['company_name', 'action_datetime', 'customer_status', 'contact_channel'];
    $sort = in_array($sort, $validSorts) ? $sort : 'action_datetime';
    $order = in_array($order, ['asc', 'desc']) ? $order : 'desc';
    
    // Handle sorting by customer_status (which is now cst.name in the query)
    if ($sort === 'customer_status') {
        $sort = 'cst.name';
    } elseif ($sort === 'contact_channel') {
        $sort = 'ah.contact_channel';
    }
    
    $query .= " ORDER BY $sort $order";
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatCustomerAddress($customer) {
    $addressParts = array_filter([
        $customer['address'],
        $customer['province'],
        $customer['country']
    ], function($part) {
        return !empty($part);
    });
    return implode(', ', $addressParts);
}

/**
 * Check if user is logged in, if not redirect to login page
 */
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require admin role, redirect to customer dashboard if not admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /customer_dashboard.php');
        exit;
    }
}

/**
 * Get SMTP settings from database
 */
function getSMTPSettings() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT setting_name, value FROM settings WHERE setting_name LIKE 'smtp_%'");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return [
            'smtp_host' => $settings['smtp_host'] ?? '',
            'smtp_port' => intval($settings['smtp_port'] ?? 587),
            'smtp_username' => $settings['smtp_username'] ?? '',
            'smtp_password' => $settings['smtp_password'] ?? '',
            'smtp_encryption' => $settings['smtp_encryption'] ?? 'tls',
            'smtp_from_email' => $settings['smtp_from_email'] ?? '',
            'smtp_from_name' => $settings['smtp_from_name'] ?? ''
        ];
    } catch (PDOException $e) {
        logError("Failed to get SMTP settings: " . $e->getMessage());
        return [
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'smtp_from_email' => '',
            'smtp_from_name' => ''
        ];
    }
}

/**
 * Get user-specific SMTP settings, falling back to system defaults
 * 
 * @param int $user_id User ID to get settings for
 * @return array SMTP settings array
 */
function getUserSMTPSettings($user_id) {
    global $pdo;
    try {
        // Get user's personal email settings
        $stmt = $pdo->prepare("SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_from_email, smtp_from_name, smtp_encryption FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get system default settings
        $system_settings = getSMTPSettings();
        
        // Build final settings using user preferences or system defaults
        return [
            'smtp_host' => !empty($user_settings['smtp_host']) ? $user_settings['smtp_host'] : $system_settings['smtp_host'],
            'smtp_port' => !empty($user_settings['smtp_port']) ? intval($user_settings['smtp_port']) : $system_settings['smtp_port'],
            'smtp_username' => !empty($user_settings['smtp_username']) ? $user_settings['smtp_username'] : $system_settings['smtp_username'],
            'smtp_password' => !empty($user_settings['smtp_password']) ? $user_settings['smtp_password'] : $system_settings['smtp_password'],
            'smtp_encryption' => !empty($user_settings['smtp_encryption']) ? $user_settings['smtp_encryption'] : $system_settings['smtp_encryption'],
            'smtp_from_email' => !empty($user_settings['smtp_from_email']) ? $user_settings['smtp_from_email'] : $system_settings['smtp_from_email'],
            'smtp_from_name' => !empty($user_settings['smtp_from_name']) ? $user_settings['smtp_from_name'] : $system_settings['smtp_from_name']
        ];
    } catch (PDOException $e) {
        logError("Failed to get user SMTP settings: " . $e->getMessage());
        // Fall back to system settings
        return getSMTPSettings();
    }
}

/**
 * Send an email using user-specific SMTP settings
 * 
 * @param int $user_id User ID to send email as
 * @param string|array $to Recipient email address(es)
 * @param string $subject Email subject
 * @param string $body Email body (HTML format)
 * @param string $altBody Plain text version of the email body
 * @param array $attachments Array of files to attach (optional)
 * @param array|null $cc CC recipients (optional)
 * @param array|null $bcc BCC recipients (optional)
 * @param array|null $replyTo Reply-To addresses (optional)
 * @return array Success status and message
 */
function sendUserEmail($user_id, $to, $subject, $body, $altBody = '', $attachments = [], $cc = null, $bcc = null, $replyTo = null) {
    try {
        // Check if PHPMailer is installed
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            throw new Exception('PHPMailer is not installed. Please run: composer require phpmailer/phpmailer');
        }
        
        // Get user-specific SMTP settings
        $smtp_settings = getUserSMTPSettings($user_id);
        
        // Check if required SMTP settings are configured
        if (empty($smtp_settings['smtp_host']) || empty($smtp_settings['smtp_port'])) {
            throw new Exception('SMTP settings are not fully configured.');
        }
        
        // Check authentication if required
        if (empty($smtp_settings['smtp_username']) || empty($smtp_settings['smtp_password'])) {
            throw new Exception('SMTP authentication credentials are required.');
        }
        
        // Create a new PHPMailer instance
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Disable SMTP debugging for production
        $mail->SMTPDebug = 0;
        
        // Configure SMTP
        $mail->isSMTP();
        $mail->Host = $smtp_settings['smtp_host'];
        $mail->Port = $smtp_settings['smtp_port'];
        
        // Additional SSL/TLS options for better compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Set encryption
        if ($smtp_settings['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtp_settings['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        // Set authentication
        if (!empty($smtp_settings['smtp_username']) && !empty($smtp_settings['smtp_password'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_settings['smtp_username'];
            $mail->Password = $smtp_settings['smtp_password'];
        }
        
        // Set sender
        $from_email = !empty($smtp_settings['smtp_from_email']) ? $smtp_settings['smtp_from_email'] : $smtp_settings['smtp_username'];
        $from_name = !empty($smtp_settings['smtp_from_name']) ? $smtp_settings['smtp_from_name'] : '';
        
        $mail->setFrom($from_email, $from_name);
        
        // Add recipients
        if (is_array($to)) {
            foreach ($to as $email) {
                $mail->addAddress($email);
            }
        } else {
            $mail->addAddress($to);
        }
        
        // Add CC recipients
        if ($cc) {
            if (is_array($cc)) {
                foreach ($cc as $email) {
                    $mail->addCC($email);
                }
            } else {
                $mail->addCC($cc);
            }
        }
        
        // Add BCC recipients
        if ($bcc) {
            if (is_array($bcc)) {
                foreach ($bcc as $email) {
                    $mail->addBCC($email);
                }
            } else {
                $mail->addBCC($bcc);
            }
        }
        
        // Add Reply-To addresses
        if ($replyTo) {
            if (is_array($replyTo)) {
                foreach ($replyTo as $email) {
                    $mail->addReplyTo($email);
                }
            } else {
                $mail->addReplyTo($replyTo);
            }
        }
        
        // Add attachments
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    // Extract original filename for display to email recipient
                    $originalName = getOriginalFileName(basename($attachment));
                    $mail->addAttachment($attachment, $originalName);
                }
            }
        }
        
        // Email content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';  // Ensure UTF-8 encoding for Asian languages
        $mail->Encoding = 'base64'; // Use base64 encoding for better compatibility
        $mail->Subject = $subject;
        $mail->Body = $body;
        if (!empty($altBody)) {
            $mail->AltBody = $altBody;
        }
        
        // Send email
        $result = $mail->send();
        
        return [
            'success' => true,
            'message' => 'Email sent successfully'
        ];
        
    } catch (Exception $e) {
        logError("Email sending failed: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to send email: ' . $e->getMessage()
        ];
    }
}

/**
 * Send an email using PHPMailer
 * 
 * @param string|array $to Recipient email address(es)
 * @param string $subject Email subject
 * @param string $body Email body (HTML format)
 * @param string $altBody Plain text version of the email body
 * @param array $attachments Array of files to attach (optional)
 * @param array|null $cc CC recipients (optional)
 * @param array|null $bcc BCC recipients (optional)
 * @param array|null $replyTo Reply-To addresses (optional)
 * @return array Success status and message
 */
function sendEmail($to, $subject, $body, $altBody = '', $attachments = [], $cc = null, $bcc = null, $replyTo = null) {
    try {
        // Check if PHPMailer is installed
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            throw new Exception('PHPMailer is not installed. Please run: composer require phpmailer/phpmailer');
        }
        
        // Get SMTP settings from database
        $smtp_settings = getSMTPSettings();
        
        // Check if required SMTP settings are configured
        if (empty($smtp_settings['smtp_host']) || empty($smtp_settings['smtp_port'])) {
            throw new Exception('SMTP settings are not fully configured.');
        }
        
        // Check if credentials are provided when authentication is needed
        if (empty($smtp_settings['smtp_username']) || empty($smtp_settings['smtp_password'])) {
            // Log a warning but don't throw an exception as some SMTP servers don't require auth
            logError("Warning: SMTP username or password is not configured");
        }
        
        // Initialize PHPMailer
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtp_settings['smtp_host'];
        $mail->Port = $smtp_settings['smtp_port'];
        
        // Set encryption type
        if ($smtp_settings['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($smtp_settings['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }
        
        // Set authentication if username and password are provided
        if (!empty($smtp_settings['smtp_username']) && !empty($smtp_settings['smtp_password'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_settings['smtp_username'];
            $mail->Password = $smtp_settings['smtp_password'];
        }
        
        // Set sender
        $mail->setFrom(
            !empty($smtp_settings['smtp_from_email']) ? $smtp_settings['smtp_from_email'] : 'noreply@reycrm.com',
            !empty($smtp_settings['smtp_from_name']) ? $smtp_settings['smtp_from_name'] : 'Rey CRM'
        );
        
        // Add recipients
        if (is_array($to)) {
            foreach ($to as $email => $name) {
                if (is_numeric($email)) {
                    // If key is numeric, it's just an email address without a name
                    $mail->addAddress($name);
                } else {
                    $mail->addAddress($email, $name);
                }
            }
        } else {
            $mail->addAddress($to);
        }
        
        // Add CC recipients if provided
        if (is_array($cc)) {
            foreach ($cc as $email => $name) {
                if (is_numeric($email)) {
                    $mail->addCC($name);
                } else {
                    $mail->addCC($email, $name);
                }
            }
        }
        
        // Add BCC recipients if provided
        if (is_array($bcc)) {
            foreach ($bcc as $email => $name) {
                if (is_numeric($email)) {
                    $mail->addBCC($name);
                } else {
                    $mail->addBCC($email, $name);
                }
            }
        }
        
        // Add Reply-To addresses if provided
        if (is_array($replyTo)) {
            foreach ($replyTo as $email => $name) {
                if (is_numeric($email)) {
                    $mail->addReplyTo($name);
                } else {
                    $mail->addReplyTo($email, $name);
                }
            }
        }
        
        // Add attachments if provided
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    // Extract original filename for display
                    $originalName = getOriginalFileName($attachment);
                    $mail->addAttachment($attachment, $originalName);
                }
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';  // Ensure UTF-8 encoding for Asian languages
        $mail->Encoding = 'base64'; // Use base64 encoding for better compatibility
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = !empty($altBody) ? $altBody : strip_tags(str_replace('<br>', "\n", $body));
        
        // Send email
        $mail->send();
        
        return ['success' => true, 'message' => 'Email sent successfully.'];
    } catch (Exception $e) {
        logError("Email sending failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Email error: ' . $e->getMessage()];
    }
}

/**
 * Clean up expired password reset tokens
 * This function deletes all expired password reset tokens from the database
 * Can be called periodically via cron job or during certain user actions
 * 
 * @return array Status information about the cleanup
 */
function cleanupExpiredTokens() {
    global $pdo;
    $deleted = 0;
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Count how many expired tokens we have
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_tokens WHERE expiry_date < ?");
        $countStmt->execute([getCurrentUTCDateTime()]);
        $expired = $countStmt->fetchColumn();
        
        if ($expired > 0) {
            // Delete expired tokens
            $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE expiry_date < ?");
            $stmt->execute([getCurrentUTCDateTime()]);
            $deleted = $stmt->rowCount();
            
            // Log the cleanup
            if ($deleted > 0) {
                logError("Cleanup: Removed {$deleted} expired password reset tokens");
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'success' => true, 
            'expired' => $expired,
            'deleted' => $deleted
        ];
    } catch (PDOException $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        logError("Failed to clean up expired tokens: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get items per page from settings
 */
function getItemsPerPage() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT value FROM settings WHERE setting_name = 'items_per_page'");
        return ($stmt && $stmt->rowCount() > 0) ? (int)$stmt->fetch(PDO::FETCH_ASSOC)['value'] : 10;
    } catch (PDOException $e) {
        logError("Failed to get items per page setting: " . $e->getMessage());
        return 10; // Default value if setting can't be fetched
    }
}

function redirectTo($path) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    // Remove leading slash if present
    $path = ltrim($path, '/');
    $url = "$protocol$host/$path";
    
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
        // If headers are sent, use JavaScript
        echo '<script>window.location.href = "' . $url . '";</script>';
        echo 'If you are not redirected, <a href="' . $url . '">click here</a>.';
        exit;
    }
}

/**
 * Check if a path is absolute
 */
function is_absolute_path($path) {
    // On Unix systems, absolute paths start with /
    // On Windows, absolute paths start with C:\ or similar
    return (isset($path[0]) && $path[0] === '/') || 
           (isset($path[1]) && $path[1] === ':');
}

/**
 * Parse CC email addresses supporting both comma and semicolon separators
 * @param string $cc_string The CC string with emails
 * @return array Array of valid email addresses
 */
function parse_cc_emails($cc_string) {
    if (empty($cc_string)) {
        return [];
    }
    
    // Split by both comma and semicolon
    $emails = preg_split('/[,;]/', $cc_string);
    $valid_emails = [];
    
    foreach ($emails as $email) {
        $email = trim($email);
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $valid_emails[] = $email;
        }
    }
    
    return $valid_emails;
}

/**
 * Validate CC email string and return validation results
 * @param string $cc_string The CC string with emails
 * @return array ['valid' => bool, 'emails' => array, 'invalid' => array, 'message' => string]
 */
function validate_cc_emails($cc_string) {
    if (empty($cc_string)) {
        return ['valid' => true, 'emails' => [], 'invalid' => [], 'message' => ''];
    }
    
    // Split by both comma and semicolon
    $emails = preg_split('/[,;]/', $cc_string);
    $valid_emails = [];
    $invalid_emails = [];
    
    foreach ($emails as $email) {
        $email = trim($email);
        if (!empty($email)) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid_emails[] = $email;
            } else {
                $invalid_emails[] = $email;
            }
        }
    }
    
    $result = [
        'valid' => empty($invalid_emails),
        'emails' => $valid_emails,
        'invalid' => $invalid_emails,
        'message' => ''
    ];
    
    if (!empty($invalid_emails)) {
        $result['message'] = 'Invalid email address' . (count($invalid_emails) > 1 ? 'es' : '') . ': ' . implode(', ', $invalid_emails);
    }
    
    return $result;
}

/**
 * Extract original filename from stored filename format
 * Stored format: {unique_id}_{original_name}
 * @param string $storedFileName The stored filename with unique prefix
 * @return string The original filename without unique prefix
 */
function getOriginalFileName($storedFileName) {
    // Extract just the filename without path
    $filename = basename($storedFileName);
    
    // Match pattern: {unique_id}_{original_name}
    // Unique ID is typically a hex string (letters and numbers)
    if (preg_match('/^[a-f0-9]+_(.+)$/', $filename, $matches)) {
        return $matches[1];
    }
    
    // If pattern doesn't match, return the original filename as fallback
    return $filename;
}

/**
 * Send password reset email to user
 * Centralized function used by both forgot password and user management
 * @param string $email User's email address
 * @param string $username User's username (optional for personalization)
 * @param int $user_id User ID (optional, will be looked up if not provided)
 * @return array ['success' => bool, 'message' => string]
 */
function sendPasswordResetEmail($email, $username = null, $user_id = null) {
    global $pdo;
    
    try {
        // If user_id not provided, look up the user
        if (!$user_id) {
            $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found.'];
            }
            
            $user_id = $user['user_id'];
            $username = $username ?: $user['username'];
        }
        
        // Generate a unique token
        $token = bin2hex(random_bytes(32));
        
        // Get token expiry time from config (default 24 hours)
        $token_expiry_hours = defined('PASSWORD_RESET_EXPIRY_HOURS') ? PASSWORD_RESET_EXPIRY_HOURS : 24;
        $expiry_date = date('Y-m-d H:i:s', strtotime("+{$token_expiry_hours} hours"));
        
        // Delete any existing tokens for the user
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Store the token in the database
        $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expiry_date) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $token, $expiry_date]);
        
        // Generate reset link
        $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                     "://{$_SERVER['HTTP_HOST']}/reset_password.php?token=" . urlencode($token);
        
        // Prepare email content
        $subject = "Reset Your Rey CRM Password";
        $body = "<html><body>
                <h2>Password Reset Request</h2>
                <p>Hello " . htmlspecialchars($username) . ",</p>
                <p>We received a request to reset your password for your Rey CRM account. Click the link below to set a new password:</p>
                <p><a href='{$reset_link}'>Reset Your Password</a></p>
                <p>If you did not request this password reset, please ignore this email. The link will expire in {$token_expiry_hours} hours.</p>
                <p>Thank you,<br>Rey CRM Team</p>
                </body></html>";
        $alt_body = "Hello {$username},\n\nWe received a request to reset your password for your Rey CRM account. Click the link below to set a new password:\n\n{$reset_link}\n\nIf you did not request this password reset, please ignore this email. The link will expire in {$token_expiry_hours} hours.\n\nThank you,\nRey CRM Team";
        
        // Send the email
        $email_result = sendEmail($email, $subject, $body, $alt_body);
        
        if ($email_result['success']) {
            return ['success' => true, 'message' => 'Password reset email sent successfully.'];
        } else {
            logError("Failed to send password reset email: " . $email_result['message']);
            return ['success' => false, 'message' => 'Failed to send reset email: ' . $email_result['message']];
        }
        
    } catch (Exception $e) {
        logError("Password reset email failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while sending the reset email.'];
    }
}

/**
 * Get user's preferred timezone from session, cookie, or default
 * @return string Timezone identifier
 */
function getUserTimezone() {
    // Try to get timezone from session first
    if (isset($_SESSION['user_timezone'])) {
        return $_SESSION['user_timezone'];
    }
    
    // Try to get timezone from cookie
    if (isset($_COOKIE['user_timezone'])) {
        $timezone = $_COOKIE['user_timezone'];
        // Validate timezone
        if (in_array($timezone, timezone_identifiers_list())) {
            $_SESSION['user_timezone'] = $timezone; // Cache in session
            return $timezone;
        }
    }
    
    // Default timezone - can be configured
    return getDefaultTimezone();
}

/**
 * Get default system timezone - can be configured in settings
 * @return string Default timezone identifier
 */
function getDefaultTimezone() {
    // Try to get from database settings first
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE setting_name = 'default_timezone' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result && in_array($result['value'], timezone_identifiers_list())) {
            return $result['value'];
        }
    } catch (Exception $e) {
        // Fall through to default
    }
    
    // Fallback to Asia/Bangkok for existing installations
    return 'Asia/Bangkok';
}

/**
 * Set user's timezone preference
 * @param string $timezone Timezone identifier
 * @return bool Success status
 */
function setUserTimezone($timezone) {
    // Validate timezone
    if (!in_array($timezone, timezone_identifiers_list())) {
        return false;
    }
    
    // Set in session
    $_SESSION['user_timezone'] = $timezone;
    
    // Set cookie for 30 days
    setcookie('user_timezone', $timezone, time() + (30 * 24 * 60 * 60), '/');
    
    return true;
}

/**
 * Get current UTC datetime for database storage
 * @return string UTC datetime in 'Y-m-d H:i:s' format
 */
function getCurrentUTCDateTime() {
    return gmdate('Y-m-d H:i:s');
}

/**
 * Convert local datetime to UTC for database storage
 * @param string $localDateTime Local datetime string
 * @param string $timezone Source timezone (if null, uses user's timezone)
 * @return string UTC datetime in 'Y-m-d H:i:s' format
 */
function convertToUTC($localDateTime, $timezone = null) {
    if (empty($localDateTime)) {
        return null;
    }
    
    // Use user's timezone if not specified
    if ($timezone === null) {
        $timezone = getUserTimezone();
    }
    
    try {
        // Create DateTime object with source timezone
        $dt = new DateTime($localDateTime, new DateTimeZone($timezone));
        
        // Convert to UTC
        $dt->setTimezone(new DateTimeZone('UTC'));
        
        return $dt->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        // Fallback: assume input is already UTC or use current UTC time
        return getCurrentUTCDateTime();
    }
}

/**
 * Format datetime for display with timezone conversion
 * @param string $datetime Database datetime string (UTC)
 * @param string $format Display format (default: 'Y-m-d H:i:s')
 * @param string $timezone Target timezone (if null, uses user's timezone)
 * @return string Formatted datetime string
 */
function formatDateTime($datetime, $format = 'Y-m-d H:i:s', $timezone = null) {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    
    // Use user's timezone if not specified
    if ($timezone === null) {
        $timezone = getUserTimezone();
    }
    
    try {
        // Create DateTime object from database datetime (assumed to be UTC)
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        
        // Convert to target timezone
        $dt->setTimezone(new DateTimeZone($timezone));
        
        return $dt->format($format);
    } catch (Exception $e) {
        // Fallback to original datetime if conversion fails
        return $datetime;
    }
}

/**
 * Format datetime for compact display (used in tables)
 * @param string $datetime Database datetime string (UTC)
 * @param string $timezone Target timezone (if null, uses user's timezone)
 * @return string Formatted datetime string (e.g., "07/22/25 9:16 AM")
 */
function formatDateTimeCompact($datetime, $timezone = null) {
    // Use user's timezone if not specified
    if ($timezone === null) {
        $timezone = getUserTimezone();
    }
    
    return formatDateTime($datetime, 'm/d/y g:i A', $timezone);
}

// ============================================================================
// LANGUAGE FUNCTIONS (Multilanguage Support)
// ============================================================================

/**
 * Initialize language system with user preference integration
 */
function initLanguage() {
    global $pdo;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Get language from various sources (priority order)
    // 1. GET parameter (highest priority - for manual switching)
    // 2. User database preference (if logged in)
    // 3. COOKIE (persistent preference)
    // 4. SESSION (current session)
    // 5. Default language (fallback)
    
    $lang = $_GET['lang'] ?? null;
    
    // If no GET parameter, try user preference from database
    if (!$lang && isset($_SESSION['user_id'])) {
        $lang = getUserLanguagePreference($_SESSION['user_id']);
    }
    
    // Fall back to cookie/session/default
    if (!$lang) {
        $lang = $_COOKIE['language'] ?? $_SESSION['language'] ?? getDefaultLanguage();
    }
    
    // Validate language
    if (!isLanguageAvailable($lang)) {
        $lang = getDefaultLanguage();
    }
    
    // Store in session
    $_SESSION['language'] = $lang;
    
    // Only set cookie if headers haven't been sent
    if (!headers_sent()) {
        setcookie('language', $lang, time() + (86400 * 30), '/'); // 30 days
    }
    
    // Update user preference if language was changed via GET parameter
    if (isset($_GET['lang']) && isset($_SESSION['user_id'])) {
        updateUserLanguagePreference($_SESSION['user_id'], $lang);
    }
    
    return $lang;
}

/**
 * Get available languages
 */
function getAvailableLanguages() {
    static $available_languages = null;
    
    if ($available_languages === null) {
        $config_file = __DIR__ . '/../languages/config.php';
        if (file_exists($config_file)) {
            include $config_file;
            // $available_languages is now set from the config file
        } else {
            // Fallback if config file doesn't exist
            $available_languages = [
                'en' => [
                    'name' => 'English',
                    'native_name' => 'English',
                    'flag' => '🇺🇸',
                    'direction' => 'ltr'
                ]
            ];
        }
    }
    
    return $available_languages;
}

/**
 * Get default language
 */
function getDefaultLanguage() {
    static $default_language = null;
    
    if ($default_language === null) {
        $config_file = __DIR__ . '/../languages/config.php';
        if (file_exists($config_file)) {
            include $config_file;
            // $default_language is now set from the config file
        } else {
            // Fallback if config file doesn't exist
            $default_language = 'en';
        }
    }
    
    return $default_language;
}

/**
 * Check if language is available
 */
function isLanguageAvailable($lang) {
    $available = getAvailableLanguages();
    return isset($available[$lang]);
}

/**
 * Load language messages
 */
function loadLanguageMessages($lang) {
    static $loaded_messages = [];
    
    // Return cached messages if already loaded
    if (isset($loaded_messages[$lang])) {
        return $loaded_messages[$lang];
    }
    
    $file = __DIR__ . "/../languages/{$lang}/messages.php";
    if (file_exists($file)) {
        $loaded_messages[$lang] = include $file;
        return $loaded_messages[$lang];
    }
    
    // Fallback to default language
    $default = getDefaultLanguage();
    if ($lang !== $default) {
        $fallbackFile = __DIR__ . "/../languages/{$default}/messages.php";
        if (file_exists($fallbackFile)) {
            $loaded_messages[$lang] = include $fallbackFile;
            return $loaded_messages[$lang];
        }
    }
    
    // Return empty array if no messages found
    $loaded_messages[$lang] = [];
    return $loaded_messages[$lang];
}

/**
 * Translate function
 * @param string $key Translation key
 * @param array $params Parameters to replace in translation (e.g., ['{name}' => 'John'])
 * @return string Translated text or original key if not found
 */
function __($key, $params = []) {
    static $messages = null;
    static $last_language = null;
    
    $current_lang = $_SESSION['language'] ?? getDefaultLanguage();
    
    // Reload messages if language changed or not loaded yet
    if ($messages === null || $last_language !== $current_lang) {
        $messages = loadLanguageMessages($current_lang);
        $last_language = $current_lang;
    }
    
    $text = $messages[$key] ?? $key;
    
    // Replace parameters
    if (!empty($params)) {
        foreach ($params as $param => $value) {
            $text = str_replace($param, $value, $text);
        }
    }
    
    return $text;
}

/**
 * Get current language
 */
function getCurrentLanguage() {
    return $_SESSION['language'] ?? getDefaultLanguage();
}

/**
 * Get current language info
 */
function getCurrentLanguageInfo() {
    $lang = getCurrentLanguage();
    $available = getAvailableLanguages();
    return $available[$lang] ?? $available[getDefaultLanguage()];
}

/**
 * Switch to a different language
 * @param string $lang Language code
 * @return bool Success status
 */
function switchLanguage($lang) {
    if (!isLanguageAvailable($lang)) {
        return false;
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['language'] = $lang;
    setcookie('language', $lang, time() + (86400 * 30), '/'); // 30 days
    
    return true;
}

/**
 * Get language-aware date format
 */
function getDateFormat($lang = null) {
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    switch ($lang) {
        case 'zh-cn':
            return 'Y年n月j日';
        default:
            return 'M j, Y';
    }
}

/**
 * Get language-aware datetime format
 */
function getDateTimeFormat($lang = null) {
    if ($lang === null) {
        $lang = getCurrentLanguage();
    }
    
    switch ($lang) {
        case 'zh-cn':
            return 'Y年n月j日 H:i';
        default:
            return 'M j, Y g:i A';
    }
}

// ============================================================================
// USER LANGUAGE PREFERENCE FUNCTIONS (Phase 3)
// ============================================================================

/**
 * Get user's language preference from database
 * @param int $user_id User ID
 * @return string|null Language code or null if not found
 */
function getUserLanguagePreference($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT preferred_language FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetchColumn();
        
        // Return the preference if it's a valid language
        if ($result && isLanguageAvailable($result)) {
            return $result;
        }
        
        return null;
    } catch (PDOException $e) {
        logError("Error getting user language preference: " . $e->getMessage());
        return null;
    }
}

/**
 * Update user's language preference in database
 * @param int $user_id User ID
 * @param string $lang Language code
 * @return bool Success status
 */
function updateUserLanguagePreference($user_id, $lang) {
    global $pdo;
    
    // Validate language before saving
    if (!isLanguageAvailable($lang)) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET preferred_language = ? WHERE user_id = ?");
        $result = $stmt->execute([$lang, $user_id]);
        
        if ($result) {
            logError("User language preference updated: User $user_id changed to $lang");
        }
        
        return $result;
    } catch (PDOException $e) {
        logError("Error updating user language preference: " . $e->getMessage());
        return false;
    }
}

/**
 * Get system default language from database settings
 * @return string Default language code
 */
function getSystemDefaultLanguage() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE setting_name = 'default_language'");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        
        if ($result && isLanguageAvailable($result)) {
            return $result;
        }
        
        // Fallback to hardcoded default
        return getDefaultLanguage();
    } catch (PDOException $e) {
        logError("Error getting system default language: " . $e->getMessage());
        return getDefaultLanguage();
    }
}

/**
 * Get available languages from database settings
 * @return array Available language codes
 */
function getSystemAvailableLanguages() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE setting_name = 'available_languages'");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        
        if ($result) {
            $languages = explode(',', $result);
            // Filter out invalid languages
            $validLanguages = [];
            foreach ($languages as $lang) {
                $lang = trim($lang);
                if (isLanguageAvailable($lang)) {
                    $validLanguages[] = $lang;
                }
            }
            return $validLanguages;
        }
        
        // Fallback to all available languages
        return array_keys(getAvailableLanguages());
    } catch (PDOException $e) {
        logError("Error getting system available languages: " . $e->getMessage());
        return array_keys(getAvailableLanguages());
    }
}

/**
 * Get language usage statistics
 * @return array Language usage counts
 */
function getLanguageUsageStats() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT preferred_language, COUNT(*) as user_count 
            FROM users 
            WHERE preferred_language IS NOT NULL 
            GROUP BY preferred_language 
            ORDER BY user_count DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting language usage statistics: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all users for assignment dropdown
 */
function getAllUsers() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT user_id, username FROM users ORDER BY username");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting all users: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if current user can assign customers (admin or creator)
 */
function canAssignCustomer($customer_id = null) {
    if (isAdmin()) {
        return true;
    }
    
    if ($customer_id) {
        $customer = getCustomerById($customer_id);
        return $customer && $customer['created_by_user_id'] == $_SESSION['user_id'];
    }
    
    return false;
}

/**
 * Get customers assigned to current user or all customers (for admin)
 */
function getMyCustomers($showOnlyMine = true) {
    global $pdo;
    
    if (!$showOnlyMine) {
        return getAllCustomers();
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE assigned_user_id = ? ORDER BY company_name");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting my customers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get customers and their contacts for email recipients (follows same pattern as getMyCustomers)
 */
function getMyCustomersContacts($showOnlyMine = true, $user_id = null) {
    global $pdo;
    
    // Use provided user_id or fall back to session
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    if (!$showOnlyMine) {
        // Get all customers with valid emails
        try {
            $stmt = $pdo->prepare("
                SELECT c.customer_id, c.company_name, c.contact_email as customer_email,
                       cp.contact_id, cp.name as contact_name, cp.contact_email
                FROM customers c
                LEFT JOIN contact_persons cp ON c.customer_id = cp.customer_id
                WHERE (c.contact_email IS NOT NULL AND TRIM(c.contact_email) != '')
                   OR (cp.contact_email IS NOT NULL AND TRIM(cp.contact_email) != '')
                ORDER BY c.company_name, cp.name
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            logError("Error getting all customers contacts: " . $e->getMessage());
            return [];
        }
    }
    
    // Check if we have a valid user_id
    if (!$user_id) {
        logError("getMyCustomersContacts: No user_id available (session: " . ($_SESSION['user_id'] ?? 'NOT SET') . ")");
        return [];
    }
    
    // Get only assigned customers with valid emails
    try {
        $stmt = $pdo->prepare("
            SELECT c.customer_id, c.company_name, c.contact_email as customer_email,
                   cp.contact_id, cp.name as contact_name, cp.contact_email
            FROM customers c
            LEFT JOIN contact_persons cp ON c.customer_id = cp.customer_id
            WHERE c.assigned_user_id = ?
              AND (
                (c.contact_email IS NOT NULL AND TRIM(c.contact_email) != '')
                OR (cp.contact_email IS NOT NULL AND TRIM(cp.contact_email) != '')
              )
            ORDER BY c.company_name, cp.name
        ");
        $stmt->execute([$user_id]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $results;
    } catch (PDOException $e) {
        logError("Error getting my customers contacts: " . $e->getMessage());
        return [];
    }
}

/**
 * Update customer assignment
 */
function assignCustomerToUser($customer_id, $user_id) {
    global $pdo;
    
    if (!canAssignCustomer($customer_id)) {
        return false;
    }
    
    // Convert empty string to NULL for unassignment
    if ($user_id === '' || $user_id === null) {
        $user_id = null;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE customers SET assigned_user_id = ? WHERE customer_id = ?");
        return $stmt->execute([$user_id, $customer_id]);
    } catch (PDOException $e) {
        logError("Error assigning customer to user: " . $e->getMessage());
        return false;
    }
}

/**
 * Get dashboard metrics for admin management page
 */
function getDashboardMetrics() {
    global $pdo;
    
    try {
        $metrics = [];
        
        // Total customers
        $stmt = $pdo->query("SELECT COUNT(*) FROM customers");
        $metrics['total_customers'] = $stmt->fetchColumn();
        
        // Unassigned customers
        $stmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE assigned_user_id IS NULL");
        $metrics['unassigned_customers'] = $stmt->fetchColumn();
        
        // Active users with assignments
        $stmt = $pdo->query("SELECT COUNT(DISTINCT assigned_user_id) FROM customers WHERE assigned_user_id IS NOT NULL");
        $metrics['active_users'] = $stmt->fetchColumn();
        
        // Total users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $metrics['total_users'] = $stmt->fetchColumn();
        
        // Recent activities (last 7 days)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM action_history WHERE action_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $metrics['recent_activities'] = $stmt->fetchColumn();
        
        // Overdue follow-ups
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM action_history WHERE follow_up_datetime < NOW() AND follow_up_datetime IS NOT NULL");
        $stmt->execute();
        $metrics['overdue_followups'] = $stmt->fetchColumn();
        
        // User assignments distribution
        $stmt = $pdo->query("
            SELECT u.username, u.user_id, COUNT(c.customer_id) as customer_count
            FROM users u
            LEFT JOIN customers c ON u.user_id = c.assigned_user_id
            GROUP BY u.user_id, u.username
            ORDER BY customer_count DESC
        ");
        $metrics['user_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent assignment activities
        $stmt = $pdo->query("
            SELECT c.company_name, u.username, c.assigned_user_id, c.created_at
            FROM customers c
            LEFT JOIN users u ON c.assigned_user_id = u.user_id
            WHERE c.assigned_user_id IS NOT NULL
            ORDER BY c.created_at DESC
            LIMIT 10
        ");
        $metrics['recent_assignments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $metrics;
        
    } catch (PDOException $e) {
        logError("Error getting dashboard metrics: " . $e->getMessage());
        return [
            'total_customers' => 0,
            'unassigned_customers' => 0,
            'active_users' => 0,
            'total_users' => 0,
            'recent_activities' => 0,
            'overdue_followups' => 0,
            'user_distribution' => [],
            'recent_assignments' => []
        ];
    }
}

/**
 * Get unassigned customers
 */
function getUnassignedCustomers() {
    global $pdo;
    
    try {
        $language = getCurrentLanguage();
        
        $stmt = $pdo->prepare("
            SELECT c.customer_id, c.company_name, c.contact_email, c.country, c.province, c.created_at,
                   cs.status_key,
                   cst.status_name
            FROM customers c
            LEFT JOIN customer_statuses cs ON c.status_id = cs.id
            LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.language = ?
            WHERE c.assigned_user_id IS NULL
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$language]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting unassigned customers: " . $e->getMessage());
        return [];
    }
}

/**
 * Bulk assign customers to users
 */
function bulkAssignCustomers($customer_ids, $user_id, $reason = '') {
    global $pdo;
    
    if (!isAdmin()) {
        return ['success' => false, 'message' => 'Insufficient permissions'];
    }
    
    if (empty($customer_ids) || !$user_id) {
        return ['success' => false, 'message' => 'Invalid parameters'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update customers
        $placeholders = str_repeat('?,', count($customer_ids) - 1) . '?';
        $sql = "UPDATE customers SET assigned_user_id = ? WHERE customer_id IN ($placeholders)";
        $params = array_merge([$user_id], $customer_ids);
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        $affected_rows = $stmt->rowCount();
        
        // Log the bulk assignment
        if ($reason) {
            logError("Bulk assignment: {$affected_rows} customers assigned to user {$user_id}. Reason: {$reason}");
        }
        
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => "Successfully assigned {$affected_rows} customers",
            'affected_rows' => $affected_rows
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        logError("Error in bulk assignment: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Get user workload statistics
 */
function getUserWorkloadStats($user_id = null) {
    global $pdo;
    
    try {
        if ($user_id) {
            // Single user stats
            $stmt = $pdo->prepare("
                SELECT 
                    u.user_id,
                    u.username,
                    COUNT(DISTINCT c.customer_id) as customer_count,
                    COUNT(DISTINCT CASE WHEN cs.status_key IN ('active_customer', 'new_customer') THEN c.customer_id END) as active_customers,
                    COUNT(DISTINCT CASE WHEN cs.status_key = 'prospect' THEN c.customer_id END) as prospect_customers,
                    COUNT(DISTINCT CASE WHEN ah.follow_up_datetime < NOW() AND ah.follow_up_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND ah.follow_up_datetime IS NOT NULL THEN c.customer_id END) as overdue_followups,
                    COUNT(CASE WHEN ah.action_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_activities,
                    COUNT(DISTINCT CASE WHEN c2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN c2.customer_id END) as new_customers_created
                FROM users u
                LEFT JOIN customers c ON u.user_id = c.assigned_user_id
                LEFT JOIN customer_statuses cs ON c.status_id = cs.id
                LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
                LEFT JOIN customers c2 ON u.user_id = c2.created_by_user_id
                WHERE u.user_id = ?
                GROUP BY u.user_id, u.username
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // All users stats
            $stmt = $pdo->query("
                SELECT 
                    u.user_id,
                    u.username,
                    COUNT(DISTINCT c.customer_id) as customer_count,
                    COUNT(DISTINCT CASE WHEN cs.status_key IN ('active_customer', 'new_customer') THEN c.customer_id END) as active_customers,
                    COUNT(DISTINCT CASE WHEN cs.status_key = 'prospect' THEN c.customer_id END) as prospect_customers,
                    MAX(ah.action_datetime) as last_activity,
                    COUNT(DISTINCT CASE WHEN c2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN c2.customer_id END) as new_customers_created
                FROM users u
                LEFT JOIN customers c ON u.user_id = c.assigned_user_id
                LEFT JOIN customer_statuses cs ON c.status_id = cs.id
                LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
                LEFT JOIN customers c2 ON u.user_id = c2.created_by_user_id
                GROUP BY u.user_id, u.username
                ORDER BY customer_count DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        logError("Error getting user workload stats: " . $e->getMessage());
        return $user_id ? null : [];
    }
}

/**
 * Bulk unassign customers from their current users
 */
function bulkUnassignCustomers($customer_ids, $reason = '') {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $success_count = 0;
        foreach ($customer_ids as $customer_id) {
            // Get current assignment
            $stmt = $pdo->prepare("SELECT assigned_user_id, company_name FROM customers WHERE customer_id = ?");
            $stmt->execute([$customer_id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer && $customer['assigned_user_id']) {
                // Unassign customer
                $stmt = $pdo->prepare("UPDATE customers SET assigned_user_id = NULL WHERE customer_id = ?");
                $stmt->execute([$customer_id]);
                
                // Log the action using the new system
                $action_text = "Customer unassigned from user";
                if ($reason) {
                    $action_text .= " (Reason: $reason)";
                }
                
                $success = addSystemAction($customer_id, $action_text, $_SESSION['user_id'], "Bulk unassignment by admin");
                
                if (!$success) {
                    error_log("Failed to log bulk unassignment for customer $customer_id");
                }
                
                $success_count++;
            }
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Successfully unassigned $success_count customer(s)."
        ];
        
    } catch (PDOException $e) {
        $pdo->rollback();
        logError("Error in bulk unassign: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred while unassigning customers.'
        ];
    }
}

/**
 * Auto distribute customers among available users
 */
function autoDistributeCustomers($customer_ids, $reason = '') {
    global $pdo;
    
    try {
        // Get all active users
        $stmt = $pdo->query("SELECT user_id, username FROM users WHERE active = 1 ORDER BY user_id");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($users)) {
            return [
                'success' => false,
                'message' => 'No active users available for assignment.'
            ];
        }
        
        $pdo->beginTransaction();
        
        $success_count = 0;
        $user_index = 0;
        
        foreach ($customer_ids as $customer_id) {
            $assigned_user = $users[$user_index % count($users)];
            
            // Assign customer
            $stmt = $pdo->prepare("UPDATE customers SET assigned_user_id = ? WHERE customer_id = ?");
            $stmt->execute([$assigned_user['user_id'], $customer_id]);
            
            // Log the action using the new system
            $action_text = "Customer auto-assigned to " . $assigned_user['username'];
            if ($reason) {
                $action_text .= " (Reason: $reason)";
            }
            
            $success = addSystemAction($customer_id, $action_text, $_SESSION['user_id'], "Auto-distribution by admin");
            
            if (!$success) {
                error_log("Failed to log auto-assignment for customer $customer_id");
            }
            
            $success_count++;
            $user_index++;
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Successfully auto-distributed $success_count customer(s) among " . count($users) . " user(s)."
        ];
        
    } catch (PDOException $e) {
        $pdo->rollback();
        logError("Error in auto distribute: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred while auto-distributing customers.'
        ];
    }
}

/**
 * Get user workload (customer count)
 */
function getUserWorkload($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE assigned_user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        logError("Error getting user workload: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get user activity statistics for a date range
 */
function getUserActivityStats($user_id, $date_from = null, $date_to = null) {
    global $pdo;
    
    try {
        $date_from = $date_from ?: date('Y-m-d', strtotime('-30 days'));
        $date_to = $date_to ?: date('Y-m-d');
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT ah.history_id) as total_activities,
                COUNT(DISTINCT ah.customer_id) as customers_contacted,
                COUNT(CASE WHEN ah.follow_up_datetime >= NOW() THEN 1 END) as scheduled_followups,
                COUNT(CASE WHEN ah.follow_up_datetime < NOW() THEN 1 END) as overdue_followups,
                AVG(TIMESTAMPDIFF(HOUR, ah.action_datetime, ah.follow_up_datetime)) as avg_followup_time
            FROM action_history ah
            WHERE ah.user_id = ?
            AND DATE(ah.action_datetime) BETWEEN ? AND ?
        ");
        $stmt->execute([$user_id, $date_from, $date_to]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting user activity stats: " . $e->getMessage());
        return [
            'total_activities' => 0,
            'customers_contacted' => 0,
            'scheduled_followups' => 0,
            'overdue_followups' => 0,
            'avg_followup_time' => 0
        ];
    }
}

/**
 * Get assignment history with filters
 */
function getAssignmentHistory($filters = []) {
    global $pdo;
    
    try {
        $conditions = [];
        $params = [];
        
        $query = "
            SELECT 
                ah.history_id,
                ah.customer_id,
                ah.action_datetime,
                ah.action,
                c.company_name,
                u_from.username as from_user,
                u_to.username as to_user
            FROM action_history ah
            JOIN customers c ON ah.customer_id = c.customer_id
            LEFT JOIN users u_from ON ah.notes LIKE CONCAT('%from ', u_from.username, '%')
            LEFT JOIN users u_to ON ah.notes LIKE CONCAT('%to ', u_to.username, '%')
            WHERE ah.action LIKE '%assigned%' OR ah.action LIKE '%reassigned%'
        ";
        
        if (!empty($filters['user_id'])) {
            $conditions[] = "c.assigned_user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $conditions[] = "DATE(ah.action_datetime) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = "DATE(ah.action_datetime) <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($conditions)) {
            $query .= " AND " . implode(" AND ", $conditions);
        }
        
        $query .= " ORDER BY ah.action_datetime DESC LIMIT 50";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting assignment history: " . $e->getMessage());
        return [];
    }
}

/**
 * Get performance metrics for users
 */
function getPerformanceMetrics($user_ids = [], $date_range = []) {
    global $pdo;
    
    try {
        $date_from = $date_range['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $date_to = $date_range['to'] ?? date('Y-m-d');
        
        $user_condition = '';
        $params = [$date_from, $date_to];
        
        if (!empty($user_ids)) {
            $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
            $user_condition = "AND u.user_id IN ($placeholders)";
            $params = array_merge($params, $user_ids);
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                u.user_id,
                u.username,
                COUNT(DISTINCT c.customer_id) as customer_count,
                COUNT(DISTINCT ah.history_id) as total_activities,
                COUNT(DISTINCT CASE WHEN ah.action_datetime BETWEEN ? AND ? THEN ah.history_id END) as recent_activities,
                COUNT(DISTINCT CASE WHEN ah.follow_up_datetime < NOW() AND ah.follow_up_datetime IS NOT NULL THEN c.customer_id END) as overdue_followups,
                COUNT(DISTINCT CASE WHEN cs.status_key IN ('active_customer', 'new_customer') THEN c.customer_id END) as active_customers,
                COUNT(DISTINCT CASE WHEN cs.status_key = 'prospect' THEN c.customer_id END) as prospect_customers,
                MAX(ah.action_datetime) as last_activity_date,
                AVG(CASE WHEN cs.status_key IN ('active_customer', 'new_customer') THEN 1 ELSE 0 END) * 100 as conversion_rate
            FROM users u
            LEFT JOIN customers c ON u.user_id = c.assigned_user_id
            LEFT JOIN customer_statuses cs ON c.status_id = cs.id
            LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
            WHERE 1=1 $user_condition
            GROUP BY u.user_id, u.username
            ORDER BY recent_activities DESC, customer_count DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting performance metrics: " . $e->getMessage());
        return [];
    }
}

/**
 * Get assignment distribution overview
 */
function getAssignmentDistribution() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                u.user_id,
                u.username,
                COUNT(c.customer_id) as customer_count,
                COUNT(CASE WHEN cs.status_key IN ('active_customer', 'new_customer') THEN 1 END) as active_count,
                COUNT(CASE WHEN cs.status_key = 'prospect' THEN 1 END) as prospect_count,
                COUNT(CASE WHEN cs.status_key = 'lost_customer' THEN 1 END) as inactive_count
            FROM users u
            LEFT JOIN customers c ON u.user_id = c.assigned_user_id
            LEFT JOIN customer_statuses cs ON c.status_id = cs.id
            GROUP BY u.user_id, u.username
            
            UNION ALL
            
            SELECT 
                NULL as user_id,
                'Unassigned' as username,
                COUNT(*) as customer_count,
                COUNT(CASE WHEN cs.status_key IN ('active_customer', 'new_customer') THEN 1 END) as active_count,
                COUNT(CASE WHEN cs.status_key = 'prospect' THEN 1 END) as prospect_count,
                COUNT(CASE WHEN cs.status_key = 'lost_customer' THEN 1 END) as inactive_count
            FROM customers c
            LEFT JOIN customer_statuses cs ON c.status_id = cs.id
            WHERE c.assigned_user_id IS NULL
            
            ORDER BY customer_count DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting assignment distribution: " . $e->getMessage());
        return [];
    }
}

// Dashboard redesign functions

/**
 * Get customer statistics based on user role
 */
function getDashboardCustomerStats($user_id = null, $show_all = false, $user_filter = null, $status_filter = null) {
    global $pdo;
    
    try {
        $whereClause = '';
        $params = [];
        $conditions = [];
        
        // Handle view mode filtering
        if (!$show_all && $user_id) {
            $conditions[] = 'c.assigned_user_id = :user_id';
            $params[':user_id'] = $user_id;
        }
        
        // Handle additional filters for admin
        if ($show_all) {
            if ($user_filter) {
                $conditions[] = 'c.assigned_user_id = :user_filter';
                $params[':user_filter'] = $user_filter;
            }
            if ($status_filter) {
                // Convert old status names to status keys for filtering
                $status_mapping = [
                    'Prospect' => 'prospect',
                    'Qualified' => 'qualified', 
                    'Not Qualified' => 'not_qualified',
                    'New Customer' => 'new_customer',
                    'Active Customer' => 'active_customer',
                    'Lost Customer' => 'lost_customer'
                ];
                $status_key = $status_mapping[$status_filter] ?? $status_filter;
                $conditions[] = 'cs.status_key = :status_filter';
                $params[':status_filter'] = $status_key;
            }
        }
        
        if (!empty($conditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        }
        
        // Get basic customer counts first
        $sql = "SELECT 
                    COUNT(*) as total_customers,
                    COUNT(CASE WHEN cs.status_key IN ('active_customer', 'new_customer') THEN 1 END) as active_customers,
                    COUNT(CASE WHEN cs.status_key = 'prospect' THEN 1 END) as prospects,
                    COUNT(CASE WHEN cs.status_key = 'qualified' THEN 1 END) as qualified,
                    COUNT(CASE WHEN cs.status_key = 'lost_customer' THEN 1 END) as lost_customers
                FROM customers c
                LEFT JOIN customer_statuses cs ON c.status_id = cs.id
                $whereClause";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get contacted customers count separately to avoid JOIN inflation
        $contactedSql = "SELECT COUNT(DISTINCT c.customer_id) as contacted_customers
                         FROM customers c
                         INNER JOIN action_history ah ON c.customer_id = ah.customer_id";
        
        // Add status table if status filtering is used
        if ($show_all && $status_filter) {
            $contactedSql .= " LEFT JOIN customer_statuses cs ON c.status_id = cs.id";
        }
        
        $contactedSql .= " $whereClause";
        
        $contactedStmt = $pdo->prepare($contactedSql);
        $contactedStmt->execute($params);
        $contactedResult = $contactedStmt->fetch(PDO::FETCH_ASSOC);
        
        $stats['contacted_customers'] = $contactedResult['contacted_customers'];
        $stats['not_contacted'] = $stats['total_customers'] - $stats['contacted_customers'];
        $stats['contact_rate'] = $stats['total_customers'] > 0 ? 
            round(($stats['contacted_customers'] / $stats['total_customers']) * 100) : 0;
        
        return $stats;
    } catch (PDOException $e) {
        logError("Error getting dashboard customer stats: " . $e->getMessage());
        return [
            'total_customers' => 0,
            'active_customers' => 0,
            'prospects' => 0,
            'qualified' => 0,
            'lost_customers' => 0,
            'contacted_customers' => 0,
            'not_contacted' => 0,
            'contact_rate' => 0
        ];
    }
}

/**
 * Get customer list for dashboard (limited/summary view)
 */
function getDashboardCustomers($limit = 10, $user_id = null, $show_all = false, $view_mode = 'recent', $user_filter = null, $status_filter = null) {
    global $pdo;
    
    try {
        $current_locale = getCurrentLanguage();
        $conditions = [];
        $params = [':locale' => $current_locale];
        
        // Handle view mode filtering
        if (!$show_all && $user_id) {
            $conditions[] = 'c.assigned_user_id = :user_id';
            $params[':user_id'] = $user_id;
        } elseif ($view_mode === 'unassigned') {
            $conditions[] = 'c.assigned_user_id IS NULL';
        }
        
        // Handle additional filters for admin
        if ($show_all) {
            if ($user_filter) {
                $conditions[] = 'c.assigned_user_id = :user_filter';
                $params[':user_filter'] = $user_filter;
            }
            if ($status_filter) {
                // Convert old status names to status keys for filtering
                $status_mapping = [
                    'Prospect' => 'prospect',
                    'Qualified' => 'qualified', 
                    'Not Qualified' => 'not_qualified',
                    'New Customer' => 'new_customer',
                    'Active Customer' => 'active_customer',
                    'Lost Customer' => 'lost_customer'
                ];
                $status_key = $status_mapping[$status_filter] ?? $status_filter;
                $conditions[] = 'cs.status_key = :status_filter';
                $params[':status_filter'] = $status_key;
            }
        }
        
        $whereClause = '';
        if (!empty($conditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        }
        
        $orderClause = '';
        switch ($view_mode) {
            case 'recent':
                $orderClause = 'ORDER BY c.created_at DESC';
                break;
            case 'last_contact':
                $orderClause = 'ORDER BY last_contact DESC, c.created_at DESC';
                break;
            case 'no_contact':
                $orderClause = 'ORDER BY CASE WHEN last_contact IS NULL THEN 0 ELSE 1 END, c.created_at DESC';
                break;
            default:
                $orderClause = 'ORDER BY c.created_at DESC';
        }
        
        $sql = "SELECT 
                    c.customer_id,
                    c.company_name,
                    cs.status_key,
                    cst.name as status,
                    c.contact_email,
                    c.contact_phone,
                    c.assigned_user_id,
                    c.created_at,
                    u.username as assigned_username,
                    MAX(ah.action_datetime) as last_contact,
                    COUNT(ah.history_id) as activity_count,
                    CONCAT_WS(', ', 
                        NULLIF(TRIM(c.province), ''),
                        NULLIF(TRIM(c.country), '')
                    ) as location
                FROM customers c
                LEFT JOIN customer_statuses cs ON c.status_id = cs.id
                LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = :locale
                LEFT JOIN users u ON c.assigned_user_id = u.user_id
                LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
                $whereClause
                GROUP BY c.customer_id, c.company_name, cs.status_key, cst.name, c.contact_email, 
                         c.contact_phone, c.assigned_user_id, c.created_at, u.username
                $orderClause
                LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting dashboard customers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user performance stats (admin only)
 */
function getUserPerformanceStats() {
    global $pdo;
    
    try {
        $sql = "SELECT 
                    u.user_id,
                    u.username,
                    COUNT(c.customer_id) as total_customers,
                    COUNT(CASE WHEN cs.status_key IN ('active_customer', 'new_customer') THEN 1 END) as active_customers,
                    COUNT(DISTINCT ah.customer_id) as contacted_customers,
                    COUNT(CASE WHEN ah.action_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_activities
                FROM users u
                LEFT JOIN customers c ON u.user_id = c.assigned_user_id
                LEFT JOIN customer_statuses cs ON c.status_id = cs.id
                LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
                WHERE u.role != 'admin'
                GROUP BY u.user_id, u.username
                HAVING total_customers > 0
                ORDER BY active_customers DESC, total_customers DESC
                LIMIT 5";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting user performance stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Get customers by assignment status (admin only)
 */
function getCustomersByAssignment() {
    global $pdo;
    
    try {
        $sql = "SELECT 
                    COUNT(*) as total_customers,
                    COUNT(CASE WHEN assigned_user_id IS NOT NULL THEN 1 END) as assigned_customers,
                    COUNT(CASE WHEN assigned_user_id IS NULL THEN 1 END) as unassigned_customers
                FROM customers";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting customers by assignment: " . $e->getMessage());
        return [
            'total_customers' => 0,
            'assigned_customers' => 0,
            'unassigned_customers' => 0
        ];
    }
}

/**
 * Get dashboard follow-ups (role-aware)
 */
function getDashboardFollowups($limit = 5, $user_id = null, $show_all = false) {
    global $pdo;
    
    try {
        $whereClause = 'WHERE ah.follow_up_datetime IS NOT NULL AND ah.follow_up_datetime >= NOW()';
        $params = [];
        
        if (!$show_all && $user_id) {
            $whereClause .= ' AND c.assigned_user_id = :user_id';
            $params[':user_id'] = $user_id;
        }
        
        $sql = "SELECT 
                    ah.history_id,
                    ah.follow_up_datetime,
                    ah.next_step,
                    c.company_name,
                    c.customer_id,
                    cp.name as contact_name,
                    u.username as created_by_username,
                    au.username as assigned_username
                FROM action_history ah
                JOIN customers c ON ah.customer_id = c.customer_id
                LEFT JOIN contact_persons cp ON ah.contact_id = cp.contact_id
                LEFT JOIN users u ON ah.user_id = u.user_id
                LEFT JOIN users au ON c.assigned_user_id = au.user_id
                $whereClause
                ORDER BY ah.follow_up_datetime ASC
                LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting dashboard followups: " . $e->getMessage());
        return [];
    }
}

/**
 * Get dashboard recent activities (role-aware)
 */
function getDashboardActivities($limit = 5, $user_id = null, $show_all = false) {
    global $pdo;
    
    try {
        $whereClause = '';
        $params = [];
        
        if (!$show_all && $user_id) {
            $whereClause = 'WHERE c.assigned_user_id = :user_id';
            $params[':user_id'] = $user_id;
        }
        
        $sql = "SELECT 
                    ah.history_id,
                    ah.action_datetime,
                    ah.action,
                    ah.contact_channel,
                    c.company_name,
                    c.customer_id,
                    cp.name as contact_name,
                    u.username as created_by_username,
                    au.username as assigned_username
                FROM action_history ah
                JOIN customers c ON ah.customer_id = c.customer_id
                LEFT JOIN contact_persons cp ON ah.contact_id = cp.contact_id
                LEFT JOIN users u ON ah.user_id = u.user_id
                LEFT JOIN users au ON c.assigned_user_id = au.user_id
                $whereClause
                ORDER BY ah.action_datetime DESC
                LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting dashboard activities: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if user can edit a specific customer
 */
function canEditCustomer($customer_id) {
    global $pdo;
    
    if (isAdmin()) {
        return true;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT assigned_user_id FROM customers WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $customer && $customer['assigned_user_id'] == $_SESSION['user_id'];
    } catch (PDOException $e) {
        logError("Error checking edit permission: " . $e->getMessage());
        return false;
    }
}

/**
 * Authentication and Authorization Functions
 */
function checkAuth() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function hasRole($required_role) {
    if (!checkAuth()) {
        return false;
    }
    
    $user_role = $_SESSION['role'] ?? '';
    
    // Admin has access to everything
    if ($user_role === 'admin') {
        return true;
    }
    
    // Check specific role
    return $user_role === $required_role;
}

/**
 * CSRF Protection Functions
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Enhanced Session Security Functions
 */
function regenerateSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function checkSessionTimeout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header('Location: /login.php?timeout=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Access Control Functions
 */
function canUserAccessCustomer($user_id, $customer_id) {
    global $pdo;
    
    // Admin can access all customers
    if (isAdmin()) {
        return true;
    }
    
    // Convert customer_id to integer to ensure proper comparison
    $customer_id = (int)$customer_id;
    $user_id = (int)$user_id;
    
    // Validate that customer_id is valid
    if ($customer_id <= 0) {
        return false;
    }
    
    // Regular users can only access assigned customers
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE customer_id = ? AND assigned_user_id = ?");
        $stmt->execute([$customer_id, $user_id]);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        logError("Error checking customer access: " . $e->getMessage());
        return false;
    }
}

function validateCustomerAccess($customer_id) {
    if (!canUserAccessCustomer($_SESSION['user_id'], $customer_id)) {
        http_response_code(403);
        header('Location: customers.php?error=access_denied');
        exit;
    }
}

/**
 * Login Attempt Tracking Functions
 */
function trackLoginAttempt($username, $ip_address, $success = false) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, attempt_time, success) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $ip_address, getCurrentUTCDateTime(), $success ? 1 : 0]);
    } catch (PDOException $e) {
        logError("Failed to track login attempt: " . $e->getMessage());
    }
}

function isAccountLocked($username, $ip_address) {
    global $pdo;
    $lockout_time = date('Y-m-d H:i:s', time() - LOCKOUT_DURATION);
    
    try {
        // Check failed attempts from this IP or username
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE (username = ? OR ip_address = ?) 
            AND attempt_time > ? 
            AND success = 0
        ");
        $stmt->execute([$username, $ip_address, $lockout_time]);
        
        return $stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS;
    } catch (PDOException $e) {
        logError("Failed to check account lockout: " . $e->getMessage());
        return false; // Default to not locked if we can't check
    }
}

/**
 * Enhanced Security Logging Functions
 */
function logSecurityEvent($event_type, $details, $user_id = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO security_log 
            (event_type, details, user_id, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $event_type,
            json_encode($details),
            $user_id ?? $_SESSION['user_id'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            getCurrentUTCDateTime()
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log security event: " . $e->getMessage());
    }
}

/**
 * Input Sanitization and XSS Protection Functions
 */
function sanitizeHtml($content, $allow_html = false) {
    if (!$allow_html) {
        return htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // For rich text content, use basic HTML filtering
    $allowed_tags = '<p><br><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6>';
    $clean_content = strip_tags($content, $allowed_tags);
    
    // Remove potentially dangerous attributes
    $clean_content = preg_replace('/\s*on\w+\s*=\s*["\'].*?["\']/i', '', $clean_content);
    $clean_content = preg_replace('/\s*javascript\s*:/i', '', $clean_content);
    
    return $clean_content;
}

function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function validateFileUpload($file, $allowed_types = [], $max_size = 10485760) { // 10MB default
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        $errors[] = 'No file uploaded';
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'File upload incomplete';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'No file selected';
                break;
            default:
                $errors[] = 'Upload error occurred';
        }
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File exceeds maximum size of ' . formatBytes($max_size);
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Check file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime_type = mime_content_type($file['tmp_name']);
    
    if (!empty($allowed_types)) {
        $allowed_extensions = array_keys($allowed_types);
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowed_extensions);
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Verify MIME type matches extension
        if (!in_array($mime_type, $allowed_types[$file_ext])) {
            $errors[] = 'File content does not match extension';
            return ['valid' => false, 'errors' => $errors];
        }
    }
    
    // Check for dangerous content
    $file_content = file_get_contents($file['tmp_name'], false, null, 0, 1024); // Read first 1KB
    if (preg_match('/<\?php|<script|javascript:|vbscript:/i', $file_content)) {
        $errors[] = 'File contains potentially dangerous content';
        return ['valid' => false, 'errors' => $errors];
    }
    
    return ['valid' => true, 'errors' => []];
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

function generateSecureFilename($original_filename) {
    // Get file extension
    $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    
    // Generate secure filename
    $secure_name = bin2hex(random_bytes(16)) . '_' . time();
    
    // Add extension if present
    if (!empty($extension)) {
        $secure_name .= '.' . $extension;
    }
    
    return $secure_name;
}

/**
 * Business Logic Validation Functions
 */
function validateBusinessRules($operation, $data) {
    switch ($operation) {
        case 'user_role_change':
            // Prevent self-privilege escalation
            if (isset($data['user_id']) && $data['user_id'] == $_SESSION['user_id'] && 
                isset($data['new_role']) && $data['new_role'] == 'admin') {
                logSecurityEvent('privilege_escalation_attempt', $data);
                throw new Exception('Cannot change own role to admin');
            }
            break;
            
        case 'customer_assignment':
            // Log customer assignment changes
            logSecurityEvent('customer_assignment_change', $data);
            break;
            
        case 'sensitive_data_access':
            // Log access to sensitive data
            logSecurityEvent('sensitive_data_access', $data);
            break;
    }
}

/**
 * Data Encryption Functions for Phase 3
 */
function getEncryptionKey() {
    // Ensure the ENCRYPTION_KEY environment variable is set
    if (empty($_ENV['ENCRYPTION_KEY'])) {
        throw new Exception('Encryption key is not set. Please configure the ENCRYPTION_KEY environment variable.');
    }
    
    // Ensure key is exactly 32 bytes for AES-256
    return hash('sha256', $_ENV['ENCRYPTION_KEY'], true);
}

function encryptData($data) {
    if (empty($data)) {
        return $data;
    }
    
    try {
        $key = getEncryptionKey();
        $iv = random_bytes(16); // AES block size
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }
        
        // Combine IV and encrypted data, then base64 encode
        return base64_encode($iv . $encrypted);
    } catch (Exception $e) {
        logError("Encryption failed: " . $e->getMessage());
        return $data; // Return original data if encryption fails
    }
}

function decryptData($encryptedData) {
    if (empty($encryptedData)) {
        return $encryptedData;
    }
    
    try {
        $key = getEncryptionKey();
        $data = base64_decode($encryptedData);
        
        if ($data === false || strlen($data) < 16) {
            return $encryptedData; // Return as-is if not properly encrypted
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        
        if ($decrypted === false) {
            return $encryptedData; // Return original if decryption fails
        }
        
        return $decrypted;
    } catch (Exception $e) {
        logError("Decryption failed: " . $e->getMessage());
        return $encryptedData; // Return original data if decryption fails
    }
}

/**
 * Rate Limiting Functions
 */
function getRateLimitKey($action, $identifier = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $identifier = $identifier ?? $ip;
    return "rate_limit:{$action}:{$identifier}";
}

function checkRateLimit($action, $limit = 10, $window = 300, $identifier = null) {
    global $pdo;
    
    $key = getRateLimitKey($action, $identifier);
    $window_start = date('Y-m-d H:i:s', time() - $window);
    
    try {
        // Clean up old entries
        $cleanup_stmt = $pdo->prepare("DELETE FROM rate_limits WHERE created_at < ?");
        $cleanup_stmt->execute([$window_start]);
        
        // Count current attempts
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE rate_key = ? AND created_at > ?");
        $count_stmt->execute([$key, $window_start]);
        $count = $count_stmt->fetchColumn();
        
        if ($count >= $limit) {
            logSecurityEvent('rate_limit_exceeded', [
                'action' => $action,
                'identifier' => $identifier,
                'count' => $count,
                'limit' => $limit
            ]);
            return false;
        }
        
        // Record this attempt
        $record_stmt = $pdo->prepare("INSERT INTO rate_limits (rate_key, created_at) VALUES (?, ?)");
        $record_stmt->execute([$key, getCurrentUTCDateTime()]);
        
        return true;
    } catch (PDOException $e) {
        logError("Rate limiting check failed: " . $e->getMessage());
        return true; // Allow if rate limiting fails
    }
}

/**
 * Enhanced Security Monitoring Functions
 */
function getSecurityMetrics($pdo) {
    try {
        $metrics = [];
        
        // Failed login attempts in last 24 hours
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
        $metrics['failed_logins_24h'] = $stmt->fetchColumn();
        
        // Successful logins in last 24 hours
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE success = 1 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
        $metrics['successful_logins_24h'] = $stmt->fetchColumn();
        
        // Security events in last 24 hours
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM security_log WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
        $metrics['security_events_24h'] = $stmt->fetchColumn();
        
        // Top event types in last 7 days
        $stmt = $pdo->prepare("SELECT event_type, COUNT(*) as count FROM security_log WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY event_type ORDER BY count DESC LIMIT 5");
        $stmt->execute();
        $metrics['top_events_7d'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Active users in last 24 hours
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM security_log WHERE user_id IS NOT NULL AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
        $metrics['active_users_24h'] = $stmt->fetchColumn();
        
        // File uploads in last 7 days
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM security_log WHERE event_type = 'file_upload' AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $metrics['file_uploads_7d'] = $stmt->fetchColumn();
        
        return $metrics;
    } catch (PDOException $e) {
        logError("Failed to get security metrics: " . $e->getMessage());
        return [];
    }
}

function getSecurityAlerts($pdo) {
    $alerts = [];
    
    try {
        // Check for suspicious activity patterns
        
        // 1. Multiple failed logins from same IP
        $stmt = $pdo->prepare("
            SELECT ip_address, COUNT(*) as attempts 
            FROM login_attempts 
            WHERE success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY ip_address 
            HAVING attempts >= 5
        ");
        $stmt->execute();
        $suspicious_ips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($suspicious_ips as $ip_data) {
            $alerts[] = [
                'type' => 'suspicious_login_attempts',
                'severity' => 'high',
                'message' => "Multiple failed login attempts from IP: {$ip_data['ip_address']} ({$ip_data['attempts']} attempts)",
                'data' => $ip_data
            ];
        }
        
        // 2. Check for unusual file upload activity
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as uploads 
            FROM security_log 
            WHERE event_type = 'file_upload' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute();
        $recent_uploads = $stmt->fetchColumn();
        
        if ($recent_uploads > 20) { // More than 20 uploads in an hour
            $alerts[] = [
                'type' => 'unusual_upload_activity',
                'severity' => 'medium',
                'message' => "Unusual file upload activity: {$recent_uploads} uploads in the last hour",
                'data' => ['upload_count' => $recent_uploads]
            ];
        }
        
        // 3. Check for privilege escalation attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM security_log 
            WHERE event_type = 'privilege_escalation_attempt' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $escalation_attempts = $stmt->fetchColumn();
        
        if ($escalation_attempts > 0) {
            $alerts[] = [
                'type' => 'privilege_escalation',
                'severity' => 'critical',
                'message' => "Privilege escalation attempts detected: {$escalation_attempts} attempts in the last 24 hours",
                'data' => ['attempt_count' => $escalation_attempts]
            ];
        }
        
        return $alerts;
    } catch (PDOException $e) {
        logError("Failed to get security alerts: " . $e->getMessage());
        return [];
    }
}

function getActivitiesDashboardData($date_from, $date_to, $showOnlyMine = true) {
    global $pdo;
    
    try {
        // Base parameters for all queries
        $baseParams = [
            ':date_from' => $date_from,
            ':date_to' => $date_to
        ];
        
        // User filter for activity queries
        $userFilter = "";
        $activityParams = $baseParams;
        if ($showOnlyMine) {
            $userFilter = " AND ah.user_id = :current_user_id";
            $activityParams[':current_user_id'] = $_SESSION['user_id'];
        }
        
        $data = [];
        
        // Total activities count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM action_history ah
            WHERE DATE(ah.action_datetime) BETWEEN :date_from AND :date_to
            $userFilter
        ");
        $stmt->execute($activityParams);
        $data['total_activities'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Total followups count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM action_history ah
            WHERE DATE(ah.follow_up_datetime) BETWEEN :date_from AND :date_to
            $userFilter
        ");
        $stmt->execute($activityParams);
        $data['total_followups'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Completed followups (activities where follow_up_datetime is in the past)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM action_history ah
            WHERE DATE(ah.follow_up_datetime) BETWEEN :date_from AND :date_to
            AND ah.follow_up_datetime < NOW()
            $userFilter
        ");
        $stmt->execute($activityParams);
        $data['completed_followups'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Overdue followups
        $overdueParams = [':date_from' => $date_from];
        if ($showOnlyMine) {
            $overdueParams[':current_user_id'] = $_SESSION['user_id'];
        }
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM action_history ah
            WHERE ah.follow_up_datetime < NOW()
            AND ah.follow_up_datetime >= :date_from
            $userFilter
        ");
        $stmt->execute($overdueParams);
        $data['overdue_followups'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        // Contact channel statistics
        $stmt = $pdo->prepare("
            SELECT ah.contact_channel, COUNT(*) as count
            FROM action_history ah
            WHERE DATE(ah.action_datetime) BETWEEN :date_from AND :date_to
            $userFilter
            GROUP BY ah.contact_channel
            ORDER BY count DESC
        ");
        $stmt->execute($activityParams);
        $data['contact_channel_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Timeline statistics (daily breakdown)
        $stmt = $pdo->prepare("
            SELECT 
                DATE(ah.action_datetime) as date,
                COUNT(CASE WHEN ah.action_datetime IS NOT NULL THEN 1 END) as activities_count,
                COUNT(CASE WHEN DATE(ah.follow_up_datetime) = DATE(ah.action_datetime) THEN 1 END) as followups_count
            FROM action_history ah
            WHERE DATE(ah.action_datetime) BETWEEN :date_from AND :date_to
            $userFilter
            GROUP BY DATE(ah.action_datetime)
            ORDER BY date ASC
        ");
        $stmt->execute($activityParams);
        $data['timeline_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // User performance (only for admin viewing all data)
        if (!$showOnlyMine) {
            $userPerfParams = [
                ':date_from' => $date_from,
                ':date_to' => $date_to,
                ':date_from2' => $date_from,
                ':date_to2' => $date_to,
                ':current_user_id' => $_SESSION['user_id']
            ];
            
            $stmt = $pdo->prepare("
                SELECT 
                    u.username,
                    u.user_id,
                    COUNT(DISTINCT ah.history_id) as activities_count,
                    COUNT(DISTINCT CASE WHEN ah.follow_up_datetime BETWEEN :date_from2 AND :date_to2 THEN ah.history_id END) as followups_count,
                    COALESCE(ROUND(
                        (COUNT(CASE WHEN ah.follow_up_datetime < NOW() THEN 1 END) * 100.0 / 
                         NULLIF(COUNT(CASE WHEN ah.follow_up_datetime IS NOT NULL THEN 1 END), 0)), 1
                    ), 0) as completion_rate
                FROM users u
                LEFT JOIN action_history ah ON ah.user_id = u.user_id 
                    AND DATE(ah.action_datetime) BETWEEN :date_from AND :date_to
                WHERE u.role != 'admin' OR u.user_id = :current_user_id
                GROUP BY u.user_id, u.username
                HAVING activities_count > 0 OR followups_count > 0
                ORDER BY activities_count DESC
            ");
            $stmt->execute($userPerfParams);
            $data['user_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $data['user_performance'] = [];
        }
        
        // Customer status performance
        $customerStatusParams = [
            ':date_from' => $date_from,
            ':date_to' => $date_to,
            ':date_from2' => $date_from,
            ':date_to2' => $date_to,
            ':locale' => getCurrentLanguage()
        ];
        $customerStatusFilter = "";
        if ($showOnlyMine) {
            $customerStatusFilter = " AND ah.user_id = :current_user_id";
            $customerStatusParams[':current_user_id'] = $_SESSION['user_id'];
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                cst.name as customer_status,
                COUNT(DISTINCT ah.history_id) as activities_count,
                COUNT(DISTINCT CASE WHEN DATE(ah.follow_up_datetime) BETWEEN :date_from2 AND :date_to2 THEN ah.history_id END) as followups_count,
                ROUND(AVG(CASE WHEN ah.action_datetime IS NOT NULL THEN DATEDIFF(ah.action_datetime, c.created_at) END), 1) as avg_response_days
            FROM customers c
            LEFT JOIN customer_statuses cs ON c.status_id = cs.id
            LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = :locale
            LEFT JOIN action_history ah ON ah.customer_id = c.customer_id 
                AND DATE(ah.action_datetime) BETWEEN :date_from AND :date_to
            WHERE 1=1 $customerStatusFilter
            GROUP BY cs.id, cst.name
            HAVING activities_count > 0 OR followups_count > 0
            ORDER BY activities_count DESC
        ");
        $stmt->execute($customerStatusParams);
        $data['status_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Recent activities
        $stmt = $pdo->prepare("
            SELECT 
                ah.*,
                c.company_name,
                cst.name as customer_status,
                u.username as created_by_username,
                au.username as assigned_username
            FROM action_history ah
            JOIN customers c ON ah.customer_id = c.customer_id
            LEFT JOIN customer_statuses cs ON c.status_id = cs.id
            LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id AND cst.locale = :locale
            LEFT JOIN users u ON ah.user_id = u.user_id
            LEFT JOIN users au ON c.assigned_user_id = au.user_id
            WHERE DATE(ah.action_datetime) BETWEEN :date_from AND :date_to
            $userFilter
            ORDER BY ah.action_datetime DESC
            LIMIT 10
        ");
        $stmt->execute($activityParams);
        $data['recent_activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        return $data;
        
    } catch (PDOException $e) {
        logError("Failed to get activities dashboard data: " . $e->getMessage() . " | Query details: " . ($e->errorInfo[2] ?? 'Unknown'));
        return [
            'total_activities' => 0,
            'total_followups' => 0,
            'completed_followups' => 0,
            'overdue_followups' => 0,
            'contact_channel_stats' => [],
            'timeline_stats' => [],
            'user_performance' => [],
            'status_performance' => [],
            'recent_activities' => []
        ];
    }
}

/**
 * Add customer activity/history with user tracking
 * 
 * @param int $customer_id Customer ID
 * @param string $action Action description
 * @param string $contact_channel Contact channel used
 * @param string $response Customer response
 * @param string $next_step Next step planned
 * @param string $follow_up_datetime Follow-up date and time
 * @param int|null $contact_id Contact person ID (optional)
 * @param string|null $notes Additional notes (optional)
 * @param int|null $user_id User who created the activity (defaults to session user)
 * @return bool Success status
 */
function addCustomerHistory($customer_id, $action, $contact_channel = 'Other', $response = '', $next_step = '', $follow_up_datetime = null, $contact_id = null, $notes = null, $user_id = null) {
    global $pdo;
    
    // Default user_id to current session user
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    // Validate required parameters
    if (empty($customer_id) || empty($action)) {
        error_log("addCustomerHistory: Missing required parameters");
        return false;
    }
    
    // Set default follow-up date if not provided (30 days from now)
    if ($follow_up_datetime === null) {
        $follow_up_datetime = date('Y-m-d H:i:s', strtotime('+30 days'));
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO action_history (
                customer_id, contact_id, user_id, action_datetime, action, 
                contact_channel, response, next_step, follow_up_datetime, notes
            ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $customer_id,
            $contact_id,
            $user_id,
            $action,
            $contact_channel,
            $response,
            $next_step,
            $follow_up_datetime,
            $notes
        ]);
        
        if ($result) {
            // Update last contacted date for customer
            updateLastContactedDate($customer_id);
        }
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("Error in addCustomerHistory: " . $e->getMessage());
        return false;
    }
}

/**
 * Add follow-up activity (alias for addCustomerHistory with focus on follow-up)
 * 
 * @param int $customer_id Customer ID
 * @param string $follow_up_action Follow-up action description
 * @param string $follow_up_datetime When to follow up
 * @param string $contact_channel Contact channel to use
 * @param string $notes Additional notes
 * @param int|null $contact_id Contact person ID (optional)
 * @param int|null $user_id User who created the follow-up (defaults to session user)
 * @return bool Success status
 */
function addFollowup($customer_id, $follow_up_action, $follow_up_datetime, $contact_channel = 'Other', $notes = null, $contact_id = null, $user_id = null) {
    return addCustomerHistory(
        $customer_id,
        "Follow-up scheduled: " . $follow_up_action,
        $contact_channel,
        '', // response (empty for new follow-ups)
        $follow_up_action, // next_step
        $follow_up_datetime,
        $contact_id,
        $notes,
        $user_id
    );
}

/**
 * Add system action to history (for automated actions like assignments)
 * 
 * @param int $customer_id Customer ID
 * @param string $action_description System action description
 * @param int|null $user_id User who triggered the action (defaults to session user)
 * @param string|null $notes Additional notes
 * @return bool Success status
 */
function addSystemAction($customer_id, $action_description, $user_id = null, $notes = null) {
    return addCustomerHistory(
        $customer_id,
        $action_description,
        'Other', // contact_channel
        'System Action', // response
        'Monitor for updates', // next_step
        date('Y-m-d H:i:s', strtotime('+30 days')), // follow_up_datetime
        null, // contact_id
        $notes,
        $user_id
    );
}

/**
 * Get customer status overview statistics for dashboard
 */
function getCustomerStatusOverview($user_id = null, $show_all = false) {
    global $pdo;
    
    try {
        $whereClause = '';
        $params = [];
        
        // Handle view mode filtering
        if (!$show_all && $user_id) {
            $whereClause = 'WHERE c.assigned_user_id = :user_id';
            $params[':user_id'] = $user_id;
        }
        
        $sql = "SELECT 
                    COALESCE(cs.status_key, 'unassigned') as status_key,
                    COALESCE(cst.name, 'Unassigned') as status_name,
                    COUNT(*) as count
                FROM customers c
                LEFT JOIN customer_statuses cs ON c.status_id = cs.id
                LEFT JOIN customer_status_translations cst ON cs.id = cst.status_id 
                    AND cst.locale = :language
                $whereClause
                GROUP BY cs.status_key, cst.name
                HAVING COUNT(*) > 0
                ORDER BY COUNT(*) DESC";
        
        $params[':language'] = getCurrentLanguage();
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting customer status overview: " . $e->getMessage());
        return [];
    }
}

?>