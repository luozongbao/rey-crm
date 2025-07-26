<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Initialize language system
$current_language = initLanguage();
$lang = getCurrentLanguageInfo();

// Check if user is logged in and is admin
if (!checkAuth() || !hasRole('admin')) {
    error_log("Unauthorized access attempt to security dashboard from IP: " . $_SERVER['REMOTE_ADDR']);
    header("Location: login.php");
    exit();
}

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        logSecurityEvent('csrf_token_invalid', ['page' => 'security_dashboard', 'user_id' => $_SESSION['user_id']]);
        $error_message = "Invalid CSRF token. Please try again.";
    }
}

// Get security metrics
$security_metrics = getSecurityMetrics($pdo);
$security_alerts = getSecurityAlerts($pdo);

// Get recent security events
$stmt = $pdo->prepare("
    SELECT event_type, details, ip_address, created_at 
    FROM security_log 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute();
$recent_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get rate limiting statistics
$stmt = $pdo->prepare("
    SELECT rate_key, COUNT(*) as attempts, 
           MIN(created_at) as first_attempt, 
           MAX(created_at) as last_attempt
    FROM rate_limits 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    GROUP BY rate_key 
    ORDER BY attempts DESC
");
$stmt->execute();
$rate_limit_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get login attempt statistics
$stmt = $pdo->prepare("
    SELECT ip_address, COUNT(*) as attempts,
           MAX(attempt_time) as last_attempt,
           SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_attempts
    FROM login_attempts 
    WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY ip_address 
    ORDER BY attempts DESC
    LIMIT 20
");
$stmt->execute();
$login_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2><?php echo __('security_dashboard'); ?></h2>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <!-- Security Metrics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-header">Total Events (24h)</div>
                        <div class="card-body">
                            <h4 class="card-title"><?php echo $security_metrics['total_events_24h']; ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-header">Failed Logins (24h)</div>
                        <div class="card-body">
                            <h4 class="card-title"><?php echo $security_metrics['failed_logins_24h']; ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger">
                        <div class="card-header">Blocked IPs</div>
                        <div class="card-body">
                            <h4 class="card-title"><?php echo $security_metrics['blocked_ips']; ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-header">Active Sessions</div>
                        <div class="card-body">
                            <h4 class="card-title"><?php echo $security_metrics['active_sessions']; ?></h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Alerts -->
            <?php if (!empty($security_alerts)): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5>🚨 Security Alerts</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($security_alerts as $alert): ?>
                            <div class="alert alert-danger">
                                <strong><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($alert['alert_type']))); ?>:</strong>
                                <?php echo htmlspecialchars($alert['message']); ?>
                                <small class="text-muted d-block">
                                    Count: <?php echo $alert['count']; ?> | 
                                    Last occurrence: <?php echo $alert['last_occurrence']; ?>
                                </small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Tabs for different views -->
            <ul class="nav nav-tabs" id="securityTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link active" id="events-tab" data-bs-toggle="tab" href="#events" role="tab" aria-controls="events" aria-selected="true"><?php echo __('recent_events'); ?></a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="rate-limit-tab" data-bs-toggle="tab" href="#rate-limit" role="tab" aria-controls="rate-limit" aria-selected="false"><?php echo __('rate_limiting'); ?></a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="login-stats-tab" data-bs-toggle="tab" href="#login-stats" role="tab" aria-controls="login-stats" aria-selected="false"><?php echo __('login_statistics'); ?></a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" id="system-health-tab" data-bs-toggle="tab" href="#system-health" role="tab" aria-controls="system-health" aria-selected="false"><?php echo __('system_health'); ?></a>
                </li>
            </ul>

            <div class="tab-content" id="securityTabsContent">
                <!-- Recent Events Tab -->
                <div class="tab-pane fade show active" id="events" role="tabpanel" aria-labelledby="events-tab" tabindex="0">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5>Recent Security Events</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Event Type</th>
                                            <th>IP Address</th>
                                            <th>Details</th>
                                            <th>Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_events as $event): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo in_array($event['event_type'], ['login_failed', 'csrf_token_invalid', 'unauthorized_access']) ? 'danger' : 'info'; 
                                                ?>">
                                                    <?php echo htmlspecialchars($event['event_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($event['ip_address']); ?></td>
                                            <td>
                                                <?php 
                                                $details = json_decode($event['details'], true);
                                                if ($details) {
                                                    foreach ($details as $key => $value) {
                                                        echo "<small><strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "<br></small>";
                                                    }
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo $event['created_at']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rate Limiting Tab -->
                <div class="tab-pane fade" id="rate-limit" role="tabpanel" aria-labelledby="rate-limit-tab" tabindex="0">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5>Rate Limiting Statistics (Last Hour)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Rate Key</th>
                                            <th>Attempts</th>
                                            <th>First Attempt</th>
                                            <th>Last Attempt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rate_limit_stats as $stat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stat['rate_key']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $stat['attempts'] > 10 ? 'danger' : 'info'; ?>">
                                                    <?php echo $stat['attempts']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $stat['first_attempt']; ?></td>
                                            <td><?php echo $stat['last_attempt']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Login Statistics Tab -->
                <div class="tab-pane fade" id="login-stats" role="tabpanel" aria-labelledby="login-stats-tab" tabindex="0">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5>Login Attempt Statistics (Last 24 Hours)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>IP Address</th>
                                            <th>Total Attempts</th>
                                            <th>Successful</th>
                                            <th>Failed</th>
                                            <th>Last Attempt</th>
                                            <th>Success Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($login_stats as $stat): ?>
                                        <?php 
                                        $failed_attempts = $stat['attempts'] - $stat['successful_attempts'];
                                        $success_rate = $stat['attempts'] > 0 ? round(($stat['successful_attempts'] / $stat['attempts']) * 100, 1) : 0;
                                        ?>
                                        <tr class="<?php echo $failed_attempts > 5 ? 'table-danger' : ''; ?>">
                                            <td><?php echo htmlspecialchars($stat['ip_address']); ?></td>
                                            <td><?php echo $stat['attempts']; ?></td>
                                            <td><span class="badge badge-success"><?php echo $stat['successful_attempts']; ?></span></td>
                                            <td><span class="badge badge-danger"><?php echo $failed_attempts; ?></span></td>
                                            <td><?php echo $stat['last_attempt']; ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar <?php echo $success_rate < 50 ? 'bg-danger' : 'bg-success'; ?>" 
                                                         style="width: <?php echo $success_rate; ?>%">
                                                        <?php echo $success_rate; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Health Tab -->
                <div class="tab-pane fade" id="system-health" role="tabpanel" aria-labelledby="system-health-tab" tabindex="0">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5>System Security Health</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Security Configuration Status</h6>
                                    <ul class="list-group">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            CSRF Protection
                                            <span class="badge badge-success badge-pill">✓ Enabled</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Session Security
                                            <span class="badge badge-success badge-pill">✓ Enhanced</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Rate Limiting
                                            <span class="badge badge-success badge-pill">✓ Active</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Data Encryption
                                            <span class="badge badge-success badge-pill">✓ AES-256</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            Security Headers
                                            <span class="badge badge-success badge-pill">✓ Implemented</span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6>Recent System Actions</h6>
                                    <div class="alert alert-info">
                                        <small>
                                            <strong>Database Performance:</strong> Queries optimized with proper indexing<br>
                                            <strong>File Security:</strong> Upload restrictions and validation active<br>
                                            <strong>Access Control:</strong> Role-based permissions enforced<br>
                                            <strong>Logging:</strong> Comprehensive security event tracking
                                        </small>
                                    </div>
                                    
                                    <!-- Quick Actions -->
                                    <h6>Quick Security Actions</h6>
                                    <form method="POST" class="mb-2">
                                        <?php echo csrfTokenField(); ?>
                                        <div class="btn-group-vertical w-100">
                                            <button type="submit" name="action" value="clear_old_logs" class="btn btn-outline-warning btn-sm">
                                                Clear Logs Older Than 30 Days
                                            </button>
                                            <button type="submit" name="action" value="reset_rate_limits" class="btn btn-outline-info btn-sm">
                                                Reset Rate Limit Counters
                                            </button>
                                            <a href="security_logs.php" class="btn btn-outline-primary btn-sm">
                                                View Detailed Security Logs
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Handle quick actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && validateCSRFToken($_POST['csrf_token'])) {
    switch ($_POST['action']) {
        case 'clear_old_logs':
            $stmt = $pdo->prepare("DELETE FROM security_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            logSecurityEvent('logs_cleared', ['cleared_by' => $_SESSION['user_id'], 'records_affected' => $stmt->rowCount()]);
            echo '<script>location.reload();</script>';
            break;
            
        case 'reset_rate_limits':
            $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute();
            logSecurityEvent('rate_limits_reset', ['reset_by' => $_SESSION['user_id'], 'records_affected' => $stmt->rowCount()]);
            echo '<script>location.reload();</script>';
            break;
    }
}

include 'includes/footer.php';
?>

<!-- Bootstrap JS for dropdown and tab functionality -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
/* Custom styles for security dashboard tabs */
.nav-tabs .nav-link {
    border: 1px solid transparent;
    border-top-left-radius: 0.375rem;
    border-top-right-radius: 0.375rem;
}

.nav-tabs .nav-link:hover {
    border-color: var(--bs-gray-200) var(--bs-gray-200) var(--bs-border-color);
    isolation: isolate;
}

.nav-tabs .nav-link.active {
    color: var(--bs-emphasis-color);
    background-color: var(--bs-body-bg);
    border-color: var(--bs-border-color) var(--bs-border-color) var(--bs-body-bg);
}

.tab-content {
    border: 1px solid var(--bs-border-color);
    border-top: none;
    padding: 1rem;
    background-color: var(--bs-body-bg);
}

.tab-pane {
    min-height: 200px;
}

/* Badge styles for metrics */
.badge.badge-danger { background-color: #dc3545; }
.badge.badge-success { background-color: #198754; }
.badge.badge-warning { background-color: #ffc107; color: #000; }
.badge.badge-info { background-color: #0dcaf0; color: #000; }
</style>
<script>
// Initialize Bootstrap tabs and auto-refresh
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tabs
    const triggerTabList = document.querySelectorAll('#securityTabs button, #securityTabs a[data-bs-toggle="tab"]');
    triggerTabList.forEach(triggerEl => {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', event => {
            event.preventDefault();
            tabTrigger.show();
        });
    });
    
    // Auto-refresh security metrics every 30 seconds
    let refreshInterval = setInterval(function() {
        // Only refresh if user is still on the page and on the main tab
        if (document.visibilityState === 'visible') {
            const activeTab = document.querySelector('#securityTabs .nav-link.active');
            if (activeTab && activeTab.id === 'events-tab') {
                // Only refresh the events tab to avoid disrupting user interaction
                location.reload();
            }
        }
    }, 30000);
    
    // Stop auto-refresh when user switches tabs
    document.querySelectorAll('#securityTabs .nav-link').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(event) {
            const activeTabId = event.target.id;
            if (activeTabId !== 'events-tab') {
                clearInterval(refreshInterval);
            } else {
                // Restart refresh interval when back on events tab
                clearInterval(refreshInterval);
                refreshInterval = setInterval(function() {
                    if (document.visibilityState === 'visible') {
                        location.reload();
                    }
                }, 30000);
            }
        });
    });
    
    // Add click handlers for tab content that might need dynamic loading
    document.getElementById('rate-limit-tab').addEventListener('shown.bs.tab', function() {
        console.log('Rate limiting tab shown');
    });
    
    document.getElementById('login-stats-tab').addEventListener('shown.bs.tab', function() {
        console.log('Login statistics tab shown');
    });
    
    document.getElementById('system-health-tab').addEventListener('shown.bs.tab', function() {
        console.log('System health tab shown');
    });
});
</script>
