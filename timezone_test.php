<?php
/**
 * Timezone functions test page
 * Tests the new timezone detection and conversion functions
 */
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Start session
session_start();

echo "<!DOCTYPE html><html><head><title>Timezone Test</title></head><body>";
echo "<h1>Timezone Functions Test</h1>";

// Test default timezone
echo "<h2>Default Timezone</h2>";
echo "Default timezone: " . getDefaultTimezone() . "<br>";

// Test user timezone (without browser detection)
echo "<h2>User Timezone (before detection)</h2>";
echo "User timezone: " . getUserTimezone() . "<br>";

// Test current UTC datetime
echo "<h2>Current Times</h2>";
$utc_now = getCurrentUTCDateTime();
echo "Current UTC: " . $utc_now . "<br>";
echo "Formatted for user: " . formatDateTime($utc_now) . "<br>";
echo "Compact format: " . formatDateTimeCompact($utc_now) . "<br>";

// Test timezone conversion
echo "<h2>Timezone Conversion Test</h2>";
$test_local = '2025-07-22 15:30:00';
echo "Local time (user tz): " . $test_local . "<br>";
echo "Converted to UTC: " . convertToUTC($test_local) . "<br>";
echo "UTC back to user: " . formatDateTime(convertToUTC($test_local)) . "<br>";

// Test with different timezones
echo "<h2>Different Timezone Tests</h2>";
echo "UTC time in New York: " . formatDateTime($utc_now, 'Y-m-d H:i:s', 'America/New_York') . "<br>";
echo "UTC time in Tokyo: " . formatDateTime($utc_now, 'Y-m-d H:i:s', 'Asia/Tokyo') . "<br>";
echo "UTC time in London: " . formatDateTime($utc_now, 'Y-m-d H:i:s', 'Europe/London') . "<br>";

echo "</body></html>";
?>
