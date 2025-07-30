<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure user is logged in and is admin
requireLogin();
requireAdmin();

// Get parameters
$view_mode = $_GET['view_mode'] ?? 'all_users';
$user_id = $_GET['user_id'] ?? '';
$as_of_datetime = $_GET['as_of_datetime'] ?? date('Y-m-d H:i:s');

// Set content type for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="status_summary_' . date('Y-m-d_H-i') . '.csv"');

// Create file pointer
$output = fopen('php://output', 'w');

if ($view_mode === 'single_user' && !empty($user_id)) {
    // Single user export
    $user_info = getUserWorkloadStats($user_id);
    $status_summary = getCustomerStatusSummary($user_id, false, $as_of_datetime);
    
    // CSV Headers
    fputcsv($output, [
        'Export Type', 'Single User Status Summary',
        'User', $user_info['username'] ?? 'Unknown',
        'As of Time', $as_of_datetime,
        'Generated', date('Y-m-d H:i:s')
    ]);
    
    fputcsv($output, []); // Empty row
    
    // Data headers
    fputcsv($output, [
        'Status',
        'Total Count',
        'New This Week',
        'New This Month',
        'Average Days in Status'
    ]);
    
    // Data rows
    foreach ($status_summary as $status) {
        fputcsv($output, [
            $status['status_name'],
            $status['count'],
            $status['new_this_week'],
            $status['new_this_month'],
            $status['avg_days_in_status']
        ]);
    }
    
} else {
    // All users export
    $all_users_summary = getAllUsersStatusSummary($as_of_datetime);
    $all_statuses = getCustomerStatusOptions();
    
    // CSV Headers
    fputcsv($output, [
        'Export Type', 'All Users Status Summary',
        'As of Time', $as_of_datetime,
        'Generated', date('Y-m-d H:i:s')
    ]);
    
    fputcsv($output, []); // Empty row
    
    // Build headers
    $headers = ['User', 'Total Customers'];
    foreach ($all_statuses as $status) {
        $headers[] = ucfirst(str_replace('_', ' ', $status));
    }
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($all_users_summary as $user_summary) {
        $row = [
            $user_summary['username'],
            $user_summary['total_customers']
        ];
        
        // Create status lookup
        $user_statuses = [];
        foreach ($user_summary['statuses'] as $status) {
            $user_statuses[$status['status_key']] = $status['count'];
        }
        
        // Add status counts
        foreach ($all_statuses as $status_key) {
            $row[] = $user_statuses[$status_key] ?? 0;
        }
        
        fputcsv($output, $row);
    }
}

fclose($output);
exit;
?>
