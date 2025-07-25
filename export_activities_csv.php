<?php
require_once 'includes/functions.php';

requireLogin();

// Get the same filter parameters that were used in all_activities.php
$customer_id = $_GET['customer_id'] ?? '';
$customer_status = $_GET['customer_status'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime("-1 month"));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Handle show_only_mine checkbox logic properly
if (count($_GET) > 0) {
    $showOnlyMine = isset($_GET['show_only_mine']) && $_GET['show_only_mine'] == '1';
} else {
    $showOnlyMine = true;
}

// Validate dates
if (strtotime($date_from) > strtotime($date_to)) {
    $date_from = date('Y-m-d', strtotime("-1 month"));
    $date_to = date('Y-m-d');
}

$sort = $_GET['sort'] ?? 'action_datetime';
$order = $_GET['order'] ?? 'desc';

// Validate parameters
$validSorts = ['company_name', 'action_datetime', 'customer_status'];
$validOrders = ['asc', 'desc'];

$sort = in_array($sort, $validSorts) ? $sort : 'action_datetime';
$order = in_array($order, $validOrders) ? $order : 'desc';

// Get filtered activities using the same function as all_activities.php
$activities = getFilteredActivities($customer_id, $date_from, $date_to, $sort, $order, $customer_status, $showOnlyMine);

// Set headers for CSV download
$filename = 'activities_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Create file handle
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 encoding (helps with Excel)
fwrite($output, "\xEF\xBB\xBF");

// Write CSV headers
$headers = [
    __('customer_name'),
    __('location'),
    __('status'),
    __('action'),
    __('date_time'),
    __('response')
];
fputcsv($output, $headers);

// Write data rows
foreach ($activities as $activity) {
    $location = $activity['province'] ? htmlspecialchars($activity['province']) : '';
    
    $row = [
        htmlspecialchars($activity['company_name']),
        $location,
        htmlspecialchars(__($activity['customer_status'])),
        htmlspecialchars($activity['action']),
        formatDateTimeCompact($activity['action_datetime']),
        htmlspecialchars($activity['response'])
    ];
    
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
