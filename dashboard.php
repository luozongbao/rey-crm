<?php 
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_history'])) {
    $start_date = $_POST['history_start'] ?? date('Y-m-d', strtotime('-1 week'));
    $end_date = $_POST['history_end'] ?? date('Y-m-d');
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="history_export_'.date('Ymd').'.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Company', 'Contact', 'Date', 'Action', 'Response', 'Next Step', 'Follow Up Date']);
    
    $history = getHistoryForExport($start_date, $end_date);
    foreach ($history as $row) {
        fputcsv($output, [
            $row['company_name'],
            $row['contact_name'] ?? 'N/A',
            $row['action_datetime'],
            $row['action'],
            $row['response'],
            $row['next_step'],
            $row['follow_up_datetime']
        ]);
    }
    fclose($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_followup'])) {
    $start_date = $_POST['followup_start'] ?? date('Y-m-d');
    $end_date = $_POST['followup_end'] ?? date('Y-m-d', strtotime('+1 week'));
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="followup_export_'.date('Ymd').'.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Company', 'Contact', 'Follow Up Date', 'Action', 'Response', 'Next Step']);
    
    $followups = getFollowupsForExport($start_date, $end_date);
    foreach ($followups as $row) {
        fputcsv($output, [
            $row['company_name'],
            $row['contact_name'] ?? 'N/A',
            $row['follow_up_datetime'],
            $row['action'],
            $row['response'],
            $row['next_step']
        ]);
    }
    fclose($output);
    exit;
}

// Load the dashboard data

try {
    $totalCustomers = getTotalCustomers();
    $statusCounts = getCustomerStatusCounts();
    $locationStats = getLocationStats();
    $lastContacted = getLastContactedCustomer();
    $contactStats = getContactStats();
    $upcomingFollowups = getUpcomingFollowups();
    $recentActivities = getRecentActivities();
} catch (Exception $e) {
    die("Error loading dashboard data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CRM Dashboard</h1>
            <a href="customers.php" class="btn">View All Customers</a>
        </div>
        
        <div class="dashboard-grid">

            <div class="dashboard-card">
                <h2>Total Customers</h2>
                <div class="stat-value"><?php echo $totalCustomers; ?></div>
                
                <div class="status-breakdown">
                    <?php foreach ($statusCounts as $status => $count): ?>
                    <?php $percentage = $totalCustomers > 0 ? round(($count / $totalCustomers) * 100) : 0; ?>
                    <div class="status-item" title="<?php echo $count; ?> customers">
                        <span class="status-label"><?php echo $status; ?>:</span>
                        <span class="status-percent"><?php echo $percentage; ?>%</span>
                        <div class="status-bar" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="dashboard-card">
                <h2>Customer Locations</h2>
                <div class="location-stats">
                    <?php foreach ($locationStats as $location): ?>
                    <?php 
                    $percentage = $totalCustomers > 0 ? round(($location['count'] / $totalCustomers) * 100) : 0;
                    $locationName = htmlspecialchars($location['location']);
                    ?>
                    <div class="location-item" title="<?php echo $location['count']; ?> customers in <?php echo $locationName; ?>">
                        <span class="location-name"><?php echo $locationName; ?>:</span>
                        <span class="location-percent"><?php echo $percentage; ?>%</span>
                        <div class="location-bar" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="dashboard-card">
                <h2>Last Contacted Customer</h2>
                <?php if ($lastContacted): ?>
                <div class="last-contacted">
                    <p><strong><?php echo htmlspecialchars($lastContacted['company_name']); ?></strong></p>
                    <p><?php 
                        $location = trim(implode(', ', array_filter([
                            $lastContacted['province'],
                            $lastContacted['country']
                        ])));
                        echo htmlspecialchars($location ? $location : 'N/A');
                    ?></p>
                    <p><?php echo htmlspecialchars($lastContacted['contact_email'] ?? 'N/A'); ?></p>
                </div>
                <?php else: ?>
                <p>No contact history found</p>
                <?php endif; ?>
            </div>
            
            <div class="dashboard-card">
                <h2>Contact Status</h2>
                <div class="contact-stats">
                    <div class="contact-item">
                        <span>Contacted:</span>
                        <span><?php echo $totalCustomers > 0 ? round(($contactStats['contacted'] / $contactStats['total']) * 100) : 0; ?>%</span>
                        <div class="contact-bar" style="width: <?php echo $totalCustomers > 0 ? ($contactStats['contacted'] / $contactStats['total']) * 100 : 0; ?>%"></div>
                    </div>
                    <div class="contact-item">
                        <span>Not Contacted:</span>
                        <span><?php echo $totalCustomers > 0 ? round(($contactStats['not_contacted'] / $contactStats['total']) * 100) : 0; ?>%</span>
                        <div class="contact-bar" style="width: <?php echo $totalCustomers > 0 ? ($contactStats['not_contacted'] / $contactStats['total']) * 100 : 0; ?>%"></div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($upcomingFollowups)): ?>
            <div class="dashboard-card full-width">
                <h2>Follow-ups <a href="all_followups.php" class="btn">View All Follow-ups</a></h2>
                <?php $upcoming = getUpcomingFollowups(5); ?>
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Contact</th>
                            <th>Follow Up Date</th>
                            <th>Next Step</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingFollowups as $followup): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($followup['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($followup['contact_name'] ?? 'N/A'); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($followup['follow_up_datetime'])); ?></td>
                            <td><?php echo htmlspecialchars($followup['next_step']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($recentActivities)): ?>
            <div class="dashboard-card full-width">
                <h2>Recent Activities <a href="all_activities.php" class="btn">View All Activities</a></h2>
                <?php $recent = getRecentActivities(5); ?>
                <div class="timeline">
                    <?php foreach ($recentActivities as $activity): ?>
                    <div class="timeline-item">
                        <div class="timeline-date">
                            <?php echo date('Y-m-d H:i', strtotime($activity['action_datetime'])); ?>
                        </div>
                        <div class="timeline-content">
                            <h3><?php echo htmlspecialchars($activity['company_name']); ?></h3>
                            <p><strong>Action:</strong> <?php echo htmlspecialchars($activity['action']); ?></p>
                            <p><strong>Contact:</strong> <?php echo htmlspecialchars($activity['contact_name'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="dashboard-card">
                <h2>Export History <button type="submit" form="export-history-form" name="export_history" class="btn">Export as CSV</button></h2>
                <form method="post" id="export-history-form" class="export-form">
                    <div class="form-group">
                        <label for="history_start">Start Date:</label>
                        <input type="date" id="history_start" name="history_start" value="<?php echo date('Y-m-d', strtotime('-1 week')); ?>">
                    </div>
                    <div class="form-group">
                        <label for="history_end">End Date:</label>
                        <input type="date" id="history_end" name="history_end" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </form>
            </div>

            <div class="dashboard-card">
                <h2>Export Followups <button type="submit" form="export-followups-form" name="export_followup" class="btn">Export as CSV</button></h2>
                <form method="post" id="export-followups-form" class="export-form">
                    <div class="form-group">
                        <label for="followup_start">Start Date:</label>
                        <input type="date" id="followup_start" name="followup_start" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="followup_end">End Date:</label>
                        <input type="date" id="followup_end" name="followup_end" value="<?php echo date('Y-m-d', strtotime('+1 week')); ?>">
                    </div>
                </form>
            </div>


        </div>
    </div>
</body>
</html>