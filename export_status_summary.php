<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure user is logged in and is admin
requireLogin();
requireAdmin();

// Get parameters
$view_mode = $_GET['view_mode'] ?? 'all_users';
$user_id = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Set content type for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="status_summary_' . date('Y-m-d') . '.csv"');

// Create file pointer
$output = fopen('php://output', 'w');

if ($view_mode === 'single_user' && !empty($user_id)) {
    // Single user export
    $user_info = getUserWorkloadStats($user_id);
    $status_summary = getCustomerStatusSummary($user_id, false, $date_from, $date_to);
    
    // CSV Headers
    fputcsv($output, [
        'Export Type', 'Single User Status Summary',
        'User', $user_info['username'] ?? 'Unknown',
        'Period', $date_from . ' to ' . $date_to,
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
    $all_users_summary = getAllUsersStatusSummary($date_from, $date_to);
    $all_statuses = getCustomerStatusOptions();
    
    // CSV Headers
    fputcsv($output, [
        'Export Type', 'All Users Status Summary',
        'Period', $date_from . ' to ' . $date_to,
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
