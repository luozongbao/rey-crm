document.addEventListener('DOMContentLoaded', function() {
    // Format date to 24-hour format
    function formatDateTo24Hour(date) {
        return date.toLocaleString('en-US', { 
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        }).replace(',', '');
    }

    // Format date for datetime-local input
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
        } else {
            // Convert existing UTC values to local
            const utcDate = new Date(input.value + 'Z');
            const localDate = new Date(utcDate.getTime() + (utcDate.getTimezoneOffset() * 60000));
            input.value = formatDateForInput(localDate);
        }
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
    
    // Convert UTC dates to local timezone for display (24-hour format)
    document.querySelectorAll('.datetime').forEach(element => {
        if (element.tagName === 'TD' || element.tagName === 'TH') {
            const utcDate = element.textContent.trim();
            if (utcDate && utcDate !== 'N/A') {
                const localDate = new Date(utcDate + 'Z'); // Append Z to treat as UTC
                element.textContent = formatDateTo24Hour(localDate);
            }
        }
    });

    // Convert local dates to UTC when submitting forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            this.querySelectorAll('input[type="datetime-local"]').forEach(input => {
                if (input.value) {
                    const localDate = new Date(input.value);
                    const utcDate = new Date(localDate.getTime() - localDate.getTimezoneOffset() * 60000);
                    input.value = utcDate.toISOString().slice(0, 16);
                }
            });
        });
    });
});