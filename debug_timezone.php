<?php
// Simple timezone debug page
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Start session if not already started  
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h2>Timezone Debug Information</h2>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not Active') . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>User Timezone:</strong> " . getUserTimezone() . "</p>";
echo "<p><strong>Default Timezone:</strong> " . getDefaultTimezone() . "</p>";

if (isset($_SESSION['user_timezone'])) {
    echo "<p><strong>Session Timezone:</strong> " . $_SESSION['user_timezone'] . "</p>";
} else {
    echo "<p><strong>Session Timezone:</strong> Not set</p>";
}

if (isset($_COOKIE['user_timezone'])) {
    echo "<p><strong>Cookie Timezone:</strong> " . $_COOKIE['user_timezone'] . "</p>";
} else {
    echo "<p><strong>Cookie Timezone:</strong> Not set</p>";
}

// Test datetime formatting
$test_utc = '2025-07-22 10:30:00'; // Sample UTC time
echo "<h3>Datetime Formatting Test</h3>";
echo "<p><strong>Sample UTC Time:</strong> " . $test_utc . "</p>";
echo "<p><strong>Formatted DateTime:</strong> " . formatDateTime($test_utc) . "</p>";
echo "<p><strong>Formatted Compact:</strong> " . formatDateTimeCompact($test_utc) . "</p>";

// Current time test
$current_utc = getCurrentUTCDateTime();
echo "<p><strong>Current UTC:</strong> " . $current_utc . "</p>";
echo "<p><strong>Current Formatted:</strong> " . formatDateTime($current_utc) . "</p>";
?>
