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
    try {
        $stmt = $pdo->query("SELECT DISTINCT 
                        CASE
                            WHEN NULLIF(TRIM(province), '') IS NULL AND NULLIF(TRIM(country), '') IS NULL THEN 'N/A'
                            WHEN NULLIF(TRIM(province), '') IS NULL THEN TRIM(country)
                            WHEN NULLIF(TRIM(country), '') IS NULL THEN TRIM(province)
                            ELSE CONCAT(TRIM(province), ', ', TRIM(country))
                        END as location
                        FROM customers
                        ORDER BY CASE WHEN location = 'N/A' THEN 1 ELSE 0 END, 
                        location");
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

function getPaginatedCustomers($page = 1, $perPage = 10, $search = '', $location = '', $sort = 'created_at', $order = 'desc') {
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
            ) as full_address
             FROM customers c";
    
    $countQuery = "SELECT COUNT(*) FROM customers c";
    
    $conditions = [];
    $params = [];
    
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

function getFilteredFollowups($customer_id = '', $date_from = '', $date_to = '', $sort = 'follow_up_datetime', $order = 'asc', $customer_status = '') {
    global $pdo;
    
    $query = "SELECT ah.*, c.company_name, c.status as customer_status, c.province, c.customer_id
              FROM action_history ah
              JOIN customers c ON ah.customer_id = c.customer_id
              WHERE 1=1";
    
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

function getFilteredActivities($customer_id = '', $date_from = '', $date_to = '', $sort = 'action_datetime', $order = 'desc', $customer_status = '') {
    global $pdo;
    
    $query = "SELECT ah.*, c.company_name, c.status as customer_status, c.province, c.customer_id
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
        global $pdo;
        $smtp_settings = [];
        $smtp_fields = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name', 'smtp_encryption'];
        
        foreach ($smtp_fields as $field) {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE setting_name = ?");
            $stmt->execute([$field]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $smtp_settings[$field] = ($result) ? $result['value'] : '';
        }
        
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
                    $mail->addAttachment($attachment);
                }
            }
        }
        
        // Content
        $mail->isHTML(true);
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
        $countStmt = $pdo->query("SELECT COUNT(*) FROM password_reset_tokens WHERE expiry_date < NOW()");
        $expired = $countStmt->fetchColumn();
        
        if ($expired > 0) {
            // Delete expired tokens
            $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE expiry_date < NOW()");
            $stmt->execute();
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

?>