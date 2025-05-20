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
});