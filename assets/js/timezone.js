/**
 * Client-side timezone detection and setting
 */
(function() {
    'use strict';
    
    /**
     * Detect user's timezone and send to server
     */
    function detectAndSetTimezone() {
        try {
            // Get user's timezone from browser
            const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
            
            // Check if we already have this timezone set
            const currentTimezone = getCookie('user_timezone');
            if (currentTimezone === userTimezone) {
                return; // Already set correctly
            }
            
            // Send timezone to server via AJAX
            fetch('includes/set_timezone.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ timezone: userTimezone })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Timezone set to:', userTimezone);
                    // Set cookie client-side as backup
                    setCookie('user_timezone', userTimezone, 30);
                    
                    // Optionally reload page to refresh times if significant timezone change
                    const currentTimezone = getCookie('user_timezone_old');
                    if (currentTimezone && currentTimezone !== userTimezone) {
                        // Only reload if timezone actually changed significantly
                        const timezoneChanged = Math.abs(new Date().getTimezoneOffset()) !== Math.abs(new Date().getTimezoneOffset());
                        if (timezoneChanged) {
                            console.log('Significant timezone change detected, refreshing page...');
                            window.location.reload();
                        }
                    }
                    setCookie('user_timezone_old', userTimezone, 30);
                } else {
                    console.warn('Failed to set timezone:', data.error || 'Unknown error');
                }
            })
            .catch(error => {
                console.warn('Timezone detection error:', error);
                // Fallback: set cookie directly
                setCookie('user_timezone', userTimezone, 30);
            });
            
        } catch (error) {
            console.warn('Browser does not support timezone detection:', error);
        }
    }
    
    /**
     * Get cookie value by name
     */
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }
    
    /**
     * Set cookie
     */
    function setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
    }
    
    /**
     * Initialize timezone detection when DOM is ready
     */
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', detectAndSetTimezone);
        } else {
            detectAndSetTimezone();
        }
    }
    
    // Initialize
    init();
})();
