<?php
// Check if config file exists, if not redirect to install
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: /includes/install.php');
    exit;
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
    $stmt = $pdo->prepare("SELECT *, 
                          CONCAT_WS(', ', 
                              address,
                              NULLIF(province, ''),
                              NULLIF(country, '')
                          ) as full_address 
                          FROM customers WHERE customer_id = ?");
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
                              WHERE ah.follow_up_datetime >= NOW()
                              ORDER BY ah.follow_up_datetime ASC
                              LIMIT :limit");
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
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
        $stmt = $pdo->prepare("UPDATE customers SET last_contacted_date = CURRENT_TIMESTAMP WHERE customer_id = ?");
        return $stmt->execute([$customer_id]);
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

function getAllLocations() {
    global $pdo;
    $stmt = $pdo->query("SELECT DISTINCT 
                        NULLIF(TRIM(province), '') as province,
                        NULLIF(TRIM(country), '') as country,
                        CASE
                            WHEN NULLIF(TRIM(province), '') IS NULL AND NULLIF(TRIM(country), '') IS NULL THEN 'N/A'
                            WHEN NULLIF(TRIM(province), '') IS NULL THEN NULLIF(TRIM(country), '')
                            WHEN NULLIF(TRIM(country), '') IS NULL THEN NULLIF(TRIM(province), '')
                            ELSE CONCAT(TRIM(province), ', ', TRIM(country))
                        END as location
                        FROM customers 
                        ORDER BY 
                            CASE WHEN NULLIF(TRIM(province), '') IS NULL AND NULLIF(TRIM(country), '') IS NULL THEN 1 ELSE 0 END,
                            location");
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 2);
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

function getPaginatedCustomers($conditions = [], $params = [], $page = 1, $perPage = 20, $sort = 'created_at', $order = 'desc') {
    global $pdo;
    
    $offset = ($page - 1) * $perPage;
    
    $query = "SELECT c.*, 
             (SELECT MAX(action_datetime) FROM action_history WHERE customer_id = c.customer_id) as last_contact
             FROM customers c";
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY $sort $order LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                           contact_email, website, status, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
        $data['notes']
    ]);
}

function updateCustomer($id, $data) {
    global $pdo;
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
    $order = in_array($validOrders, $order) ? $order : 'desc';
    
    // Build query
    $query = "SELECT c.*, 
             (SELECT MAX(action_datetime) FROM action_history WHERE customer_id = c.customer_id) as last_contact
             FROM customers c";
    
    $conditions = [];
    $params = [];
    
    if (!empty($search)) {
        $conditions[] = "company_name LIKE :search";
        $params[':search'] = "%$search%";
    }
    
    if (!empty($location)) {
        $conditions[] = "address = :location";
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

function getFilteredFollowups($customer_id = '', $date_from = '', $date_to = '', $sort = 'follow_up_datetime', $order = 'asc') {
    global $pdo;
    
    $query = "SELECT ah.*, c.company_name 
              FROM action_history ah
              JOIN customers c ON ah.customer_id = c.customer_id
              WHERE ah.follow_up_datetime >= NOW()";
    
    $params = [];
    
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
    
    $validSorts = ['company_name', 'follow_up_datetime', 'action_datetime'];
    $sort = in_array($sort, $validSorts) ? $sort : 'follow_up_datetime';
    $order = in_array($order, ['asc', 'desc']) ? $order : 'asc';
    
    $query .= " ORDER BY $sort $order";
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFilteredActivities($customer_id = '', $date_from = '', $date_to = '', $sort = 'action_datetime', $order = 'desc') {
    global $pdo;
    
    $query = "SELECT ah.*, c.company_name 
              FROM action_history ah
              JOIN customers c ON ah.customer_id = c.customer_id
              WHERE 1=1";
    
    $params = [];
    
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
    
    $validSorts = ['company_name', 'action_datetime'];
    $sort = in_array($sort, $validSorts) ? $sort : 'action_datetime';
    $order = in_array($order, ['asc', 'desc']) ? $order : 'desc';
    
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

?>