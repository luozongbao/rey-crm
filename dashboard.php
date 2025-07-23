<?php
require_once 'includes/functions.php';

requireLogin();

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

require_once 'includes/header.php';
?>
        <div class="container">
        <div class="header">
            <h1><?php echo __('crm_dashboard'); ?></h1>
        </div>
        
        <div class="dashboard-grid">

            <div class="dashboard-card">
                <h2><?php echo __('total_customers'); ?></h2>
                <div class="stat-value"><?php echo $totalCustomers; ?></div>
                
                <div class="status-breakdown">
                    <?php foreach ($statusCounts as $status => $count): ?>
                    <?php $percentage = $totalCustomers > 0 ? round(($count / $totalCustomers) * 100) : 0; ?>
                    <div class="status-item" title="<?php echo $count; ?> <?php echo __('customers'); ?>">
                        <span class="status-label"><?php echo __($status); ?>:</span>
                        <span class="status-percent"><?php echo $percentage; ?>%</span>
                        <div class="status-bar" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="dashboard-card">
                <h2><?php echo __('contact_status'); ?></h2>
                <div class="contact-stats">
                    <div class="contact-item">
                        <span><?php echo __('contacted'); ?>:</span>
                        <span><?php echo $totalCustomers > 0 ? round(($contactStats['contacted'] / $contactStats['total']) * 100) : 0; ?>%</span>
                        <div class="contact-bar" style="width: <?php echo $totalCustomers > 0 ? ($contactStats['contacted'] / $contactStats['total']) * 100 : 0; ?>%"></div>
                    </div>
                    <div class="contact-item">
                        <span><?php echo __('not_contacted'); ?>:</span>
                        <span><?php echo $totalCustomers > 0 ? round(($contactStats['not_contacted'] / $contactStats['total']) * 100) : 0; ?>%</span>
                        <div class="contact-bar" style="width: <?php echo $totalCustomers > 0 ? ($contactStats['not_contacted'] / $contactStats['total']) * 100 : 0; ?>%"></div>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-card">
                <h2><?php echo __('last_contacted_customer'); ?></h2>
                <?php if ($lastContacted): ?>
                <div class="last-contacted">
                    <p><strong><?php echo htmlspecialchars($lastContacted['company_name']); ?></strong></p>
                    <p><?php 
                        $location = trim(implode(', ', array_filter([
                            $lastContacted['province'],
                            $lastContacted['country']
                        ])));
                        echo htmlspecialchars($location ? $location : __('no_data'));
                    ?></p>
                    <p><?php echo htmlspecialchars($lastContacted['contact_email'] ?? __('no_data')); ?></p>
                </div>
                <?php else: ?>
                <p><?php echo __('no_contact_history'); ?></p>
                <?php endif; ?>
            </div>

            <div class="dashboard-card">
                <h2><?php echo __('customer_locations'); ?></h2>
                <div class="location-stats">
                    <?php foreach ($locationStats as $location): ?>
                    <?php 
                    $percentage = $totalCustomers > 0 ? round(($location['count'] / $totalCustomers) * 100) : 0;
                    $locationName = htmlspecialchars($location['location']);
                    ?>
                    <div class="location-item" title="<?php echo $location['count']; ?> <?php echo __('customers_in_location', ['location' => $locationName]); ?>">
                        <span class="location-name"><?php echo $locationName; ?>:</span>
                        <span class="location-percent"><?php echo $percentage; ?>%</span>
                        <div class="location-bar" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if (!empty($upcomingFollowups)): ?>
            <div class="dashboard-card full-width">
                <h2><?php echo __('upcoming_followups'); ?> <a href="all_followups.php" class="btn"><?php echo __('view_all_followups'); ?></a></h2>
                <?php $upcoming = getUpcomingFollowups(5); ?>
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th><?php echo __('company_name'); ?></th>
                            <th><?php echo __('contact'); ?></th>
                            <th><?php echo __('follow_up_date'); ?></th>
                            <th><?php echo __('next_step'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingFollowups as $followup): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($followup['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($followup['contact_name'] ?? __('no_data')); ?></td>
                            <td><?php echo formatDateTime($followup['follow_up_datetime']); ?></td>
                            <td><?php echo htmlspecialchars($followup['next_step']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($recentActivities)): ?>
            <div class="dashboard-card full-width">
                <h2><?php echo __('recent_activities'); ?> <a href="all_activities.php" class="btn"><?php echo __('view_all_activities'); ?></a></h2>
                <?php $recent = getRecentActivities(5); ?>
                <div class="timeline">
                    <?php foreach ($recentActivities as $activity): ?>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <h4><?php echo htmlspecialchars($activity['company_name']); ?></h4>
                            <p><?php echo htmlspecialchars($activity['action']); ?></p>
                            <small><?php echo formatDateTime($activity['action_datetime']); ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
    
    <script>
        function validateHistoryDates() {
            const startInput = document.getElementById('history_start');
            const endInput = document.getElementById('history_end');
            
            const startDate = new Date(startInput.value);
            const endDate = new Date(endInput.value);
            
            if (startDate > endDate) {
                alert('Error: Start date cannot be later than end date');
                // Reset to valid values
                if (this === startInput) {
                    startInput.value = endInput.value;
                } else {
                    endInput.value = startInput.value;
                }
            }
            
            // Set max and min attributes to prevent invalid selections
            startInput.max = endInput.value;
            endInput.min = startInput.value;
        }
        
        function validateFollowupDates() {
            const startInput = document.getElementById('followup_start');
            const endInput = document.getElementById('followup_end');
            
            const startDate = new Date(startInput.value);
            const endDate = new Date(endInput.value);
            
            if (startDate > endDate) {
                alert('Error: Start date cannot be later than end date');
                // Reset to valid values
                if (this === startInput) {
                    startInput.value = endInput.value;
                } else {
                    endInput.value = startInput.value;
                }
            }
            
            // Set max and min attributes to prevent invalid selections
            startInput.max = endInput.value;
            endInput.min = startInput.value;
        }
        
        // Initialize date constraints when page loads
        document.addEventListener('DOMContentLoaded', function() {
            validateHistoryDates();
            validateFollowupDates();
        });
    </script>
<?php require_once 'includes/footer.php'; ?>
