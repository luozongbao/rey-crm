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
    $stmt = $pdo->prepare("SELECT c.*, 
                          CONCAT_WS(', ', 
                              c.address,
                              NULLIF(c.province, ''),
                              NULLIF(c.country, '')
                          ) as full_address,
                          u.username as assigned_to_username
                          FROM customers c
                          LEFT JOIN users u ON c.assigned_user_id = u.user_id
                          WHERE c.customer_id = ?");
    $stmt->execute([$id]);
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
        $stmt = $pdo->prepare("SELECT ah.*, c.company_name, cp.name as contact_name
                              FROM action_history ah
                              JOIN customers c ON ah.customer_id = c.customer_id
                              LEFT JOIN contact_persons cp ON ah.contact_id = cp.contact_id
                              WHERE ah.follow_up_datetime >= ?
                              ORDER BY ah.follow_up_datetime ASC
                              LIMIT :limit");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute([getCurrentUTCDateTime()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting upcoming followups: " . $e->getMessage());
        return [];
    }
}

function getRecentActivities($limit = 10) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT ah.*, c.company_name, cp.name as contact_name
                              FROM action_history ah
                              JOIN customers c ON ah.customer_id = c.customer_id
                              LEFT JOIN contact_persons cp ON ah.contact_id = cp.contact_id
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
    
    $query = "SELECT c.*, 
             (SELECT MAX(action_datetime) FROM action_history WHERE customer_id = c.customer_id) as last_contact,
             (SELECT status FROM customers WHERE customer_id = c.customer_id) as status
             FROM customers c";
    
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
    
    // Build query
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
             u.username as assigned_to_username
             FROM customers c
             LEFT JOIN users u ON c.assigned_user_id = u.user_id";
    
    $countQuery = "SELECT COUNT(*) FROM customers c";
    
    $conditions = [];
    $params = [];
    
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
    
    // Add sorting and pagination
    $query .= " ORDER BY $sort $order LIMIT :limit OFFSET :offset";
    
    // Get total count
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $val) {
        $countStmt->bindValue($key, $val);
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
    $stmt = $pdo->prepare("INSERT INTO customers 
                          (company_name, address, country, province, company_type, contact_phone, 
                           contact_email, website, status, notes, assigned_user_id, created_by_user_id) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Set assignment fields - assign to current user by default
    $assigned_user_id = $data['assigned_user_id'] ?? $_SESSION['user_id'];
    $created_by_user_id = $_SESSION['user_id'];
    
    return $stmt->execute([
        $data['company_name'],
        $data['address'],
        $data['country'],
        $data['province'],
        $data['company_type'],
        $data['contact_phone'],
        $data['contact_email'],
        $data['website'],
        $data['status'],
        $data['notes'],
        $assigned_user_id,
        $created_by_user_id
    ]);
}

function updateCustomer($id, $data) {
    global $pdo;
    
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
                              website = ?, status = ?, notes = ?, assigned_user_id = ?
                              WHERE customer_id = ?");
        return $stmt->execute([
            $data['company_name'],
            $data['address'],
            $data['country'],
            $data['province'],
            $data['company_type'],
            $data['contact_phone'],
            $data['contact_email'],
            $data['website'],
            $data['status'],
            $data['notes'],
            $assigned_user_id,
            $id
        ]);
    } else {
        $stmt = $pdo->prepare("UPDATE customers SET 
                              company_name = ?, address = ?, country = ?, province = ?,
                              company_type = ?, contact_phone = ?, contact_email = ?, 
                              website = ?, status = ?, notes = ? 
                              WHERE customer_id = ?");
        return $stmt->execute([
            $data['company_name'],
            $data['address'],
            $data['country'],
            $data['province'],
            $data['company_type'],
            $data['contact_phone'],
            $data['contact_email'],
            $data['website'],
            $data['status'],
            $data['notes'],
            $id
        ]);
    }
}

function getCustomerStatusCounts() {
    global $pdo;
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM customers GROUP BY status");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure all statuses are represented
    $counts = [
        'Active' => 0,
        'Inactive' => 0,
        'Prospect' => 0
    ];
    
    foreach ($results as $row) {
        $counts[$row['status']] = $row['count'];
    }
    
    return $counts;
}

function getCustomerStatusOptions() {
    global $pdo;
    $sql = "SHOW COLUMNS FROM customers WHERE Field = 'status'";
    $stmt = $pdo->query($sql);
    $row = $stmt->fetch();
    
    if ($row && preg_match("/^enum\((.*)\)$/", $row['Type'], $matches)) {
        $values = str_getcsv(str_replace("'", '', $matches[1]));
        return $values;
    }
    
    return [];
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
    
    $query = "SELECT ah.*, c.company_name, c.status as customer_status, c.province, c.customer_id
              FROM action_history ah
              JOIN customers c ON ah.customer_id = c.customer_id
              WHERE 1=1";
    
    $params = [];
    
    // Add user assignment filter
    if ($showOnlyMine) {
        $query .= " AND c.assigned_user_id = :current_user_id";
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
            $query .= " AND c.status != 'Not Qualified'";
        } else {
            $query .= " AND c.status = :customer_status";
            $params[':customer_status'] = $customer_status;
        }
    }
    
    $validSorts = ['company_name', 'follow_up_datetime', 'action_datetime', 'customer_status'];
    $sort = in_array($sort, $validSorts) ? $sort : 'follow_up_datetime';
    $order = in_array($order, ['asc', 'desc']) ? $order : 'asc';
    
    // Handle sorting by customer_status (which is actually c.status in the query)
    if ($sort === 'customer_status') {
        $sort = 'c.status';
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
    
    $query = "SELECT ah.*, c.company_name, c.status as customer_status, c.province, c.customer_id
              FROM action_history ah
              JOIN customers c ON ah.customer_id = c.customer_id
              WHERE 1=1";
    
    $params = [];
    
    // Add user assignment filter
    if ($showOnlyMine) {
        $query .= " AND c.assigned_user_id = :current_user_id";
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
            $query .= " AND c.status != 'Not Qualified'";
        } else {
            $query .= " AND c.status = :customer_status";
            $params[':customer_status'] = $customer_status;
        }
    }
    
    $validSorts = ['company_name', 'action_datetime', 'customer_status'];
    $sort = in_array($sort, $validSorts) ? $sort : 'action_datetime';
    $order = in_array($order, ['asc', 'desc']) ? $order : 'desc';
    
    // Handle sorting by customer_status (which is actually c.status in the query)
    if ($sort === 'customer_status') {
        $sort = 'c.status';
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
 * Require admin role, redirect to dashboard if not admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /dashboard.php');
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
        $stmt = $pdo->query("
            SELECT customer_id, company_name, contact_email, country, province, status, created_at
            FROM customers 
            WHERE assigned_user_id IS NULL
            ORDER BY created_at DESC
        ");
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
                    COUNT(c.customer_id) as customer_count,
                    COUNT(CASE WHEN c.status = 'Active' THEN 1 END) as active_customers,
                    COUNT(CASE WHEN c.status = 'Prospect' THEN 1 END) as prospect_customers,
                    COUNT(CASE WHEN ah.follow_up_datetime < NOW() AND ah.follow_up_datetime IS NOT NULL THEN 1 END) as overdue_followups,
                    COUNT(CASE WHEN ah.action_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_activities
                FROM users u
                LEFT JOIN customers c ON u.user_id = c.assigned_user_id
                LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
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
                    COUNT(c.customer_id) as customer_count,
                    COUNT(CASE WHEN c.status = 'Active' THEN 1 END) as active_customers,
                    COUNT(CASE WHEN c.status = 'Prospect' THEN 1 END) as prospect_customers,
                    MAX(ah.action_datetime) as last_activity
                FROM users u
                LEFT JOIN customers c ON u.user_id = c.assigned_user_id
                LEFT JOIN action_history ah ON c.customer_id = ah.customer_id
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
                
                // Log the action in action_history table
                $action_text = "Customer unassigned from user";
                if ($reason) {
                    $action_text .= " (Reason: $reason)";
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO action_history (customer_id, action_datetime, action, response, next_step, follow_up_datetime, notes) 
                    VALUES (?, NOW(), ?, '', '', DATE_ADD(NOW(), INTERVAL 30 DAY), ?)
                ");
                $stmt->execute([$customer_id, $action_text, "Bulk unassignment by admin"]);
                
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
            
            // Log the action in action_history table
            $action_text = "Customer auto-assigned to " . $assigned_user['username'];
            if ($reason) {
                $action_text .= " (Reason: $reason)";
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO action_history (customer_id, action_datetime, action, response, next_step, follow_up_datetime, notes) 
                VALUES (?, NOW(), ?, '', '', DATE_ADD(NOW(), INTERVAL 30 DAY), ?)
            ");
            $stmt->execute([$customer_id, $action_text, "Auto-distribution by admin"]);
            
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
            JOIN customers c ON ah.customer_id = c.customer_id
            WHERE c.assigned_user_id = ?
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
                COUNT(CASE WHEN ah.follow_up_datetime < NOW() AND ah.follow_up_datetime IS NOT NULL THEN 1 END) as overdue_followups,
                COUNT(CASE WHEN c.status = 'Active' THEN 1 END) as active_customers,
                COUNT(CASE WHEN c.status = 'Prospect' THEN 1 END) as prospect_customers,
                MAX(ah.action_datetime) as last_activity_date,
                AVG(CASE WHEN c.status = 'Active' THEN 1 ELSE 0 END) * 100 as conversion_rate
            FROM users u
            LEFT JOIN customers c ON u.user_id = c.assigned_user_id
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
                COUNT(CASE WHEN c.status = 'Active' THEN 1 END) as active_count,
                COUNT(CASE WHEN c.status = 'Prospect' THEN 1 END) as prospect_count,
                COUNT(CASE WHEN c.status IN ('Inactive', 'Lost Customer') THEN 1 END) as inactive_count
            FROM users u
            LEFT JOIN customers c ON u.user_id = c.assigned_user_id
            GROUP BY u.user_id, u.username
            
            UNION ALL
            
            SELECT 
                NULL as user_id,
                'Unassigned' as username,
                COUNT(*) as customer_count,
                COUNT(CASE WHEN status = 'Active' THEN 1 END) as active_count,
                COUNT(CASE WHEN status = 'Prospect' THEN 1 END) as prospect_count,
                COUNT(CASE WHEN status IN ('Inactive', 'Lost Customer') THEN 1 END) as inactive_count
            FROM customers 
            WHERE assigned_user_id IS NULL
            
            ORDER BY customer_count DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Error getting assignment distribution: " . $e->getMessage());
        return [];
    }
}

?>