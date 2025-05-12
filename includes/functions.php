<?php
require_once 'config.php';

function getAllCustomers() {
    global $pdo;
    $stmt = $pdo->query("SELECT c.*, 
                        (SELECT MAX(action_datetime) FROM action_history WHERE customer_id = c.customer_id) as last_contact,
                        (SELECT status FROM customers WHERE customer_id = c.customer_id) as status
                        FROM customers c ORDER BY company_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCustomerById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
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
    $stmt = $pdo->query("SELECT location, COUNT(*) as count FROM customers GROUP BY location");
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

?>