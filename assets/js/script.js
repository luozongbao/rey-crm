document.addEventListener('DOMContentLoaded', function() {
    // Format date for datetime-local input (no timezone conversion needed)
    function formatDateForInput(date) {
        return date.getFullYear() + '-' +
            String(date.getMonth() + 1).padStart(2, '0') + '-' +
            String(date.getDate()).padStart(2, '0') + 'T' +
            String(date.getHours()).padStart(2, '0') + ':' +
            String(date.getMinutes()).padStart(2, '0');
    }

    // Initialize datetime-local inputs
    document.querySelectorAll('input[type="datetime-local"]').forEach(input => {
        if (!input.value) {
            // For new entries, set default values in local time
            const now = new Date();
            if (input.id === 'follow_up_datetime') {
                now.setDate(now.getDate() + 7); // One week from now
            }
            input.value = formatDateForInput(now);
        }
        // For existing values, leave them as-is since PHP now handles the conversion properly
    });
    
    // Add confirmation for delete actions
    const deleteButtons = document.querySelectorAll('.delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this record?')) {
                e.preventDefault();
            }
        });
    });
    
    // Set dark mode on page load if enabled
    if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark-mode');
    }

    // Remove the datetime display conversion - PHP now handles this properly
    // The .datetime elements are already formatted correctly by PHP formatDateTime functions

    // Remove the form submission timezone conversion since PHP handles it with convertToUTC
    // The datetime values will be processed correctly by PHP using the user's detected timezone
});