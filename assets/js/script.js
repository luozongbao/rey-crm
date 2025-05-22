document.addEventListener('DOMContentLoaded', function() {
    // Initialize datetime pickers
    const datetimeInputs = document.querySelectorAll('input[type="datetime-local"]');
    datetimeInputs.forEach(input => {
        if (!input.value) {
            const now = new Date();
            input.value = now.toISOString().slice(0, 16);
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
    
    // Convert UTC dates to local timezone for display
    document.querySelectorAll('.datetime').forEach(element => {
        if (element.tagName === 'TD' || element.tagName === 'TH') {
            const utcDate = element.textContent.trim();
            if (utcDate && utcDate !== 'N/A') {
                const localDate = new Date(utcDate + 'Z'); // Append Z to treat as UTC
                element.textContent = localDate.toLocaleString();
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

    // Initialize datetime-local inputs to current local time
    document.querySelectorAll('input[type="datetime-local"]').forEach(input => {
        if (!input.value) {
            const now = new Date();
            input.value = now.toISOString().slice(0, 16);
        } else {
            // Convert any existing UTC values to local
            const utcDate = new Date(input.value + 'Z');
            input.value = utcDate.toISOString().slice(0, 16);
        }
    });
});