/**
 * Language switching functionality for Rey CRM
 */

/**
 * Switch language and reload page
 * @param {string} langCode - Language code (e.g., 'en', 'zh-cn')
 */
function switchLanguage(langCode) {
    // Set language preference in cookie
    document.cookie = `language=${langCode}; path=/; max-age=${30 * 24 * 60 * 60}`;
    
    // Redirect with language parameter to ensure immediate effect
    window.location.href = window.location.pathname + '?lang=' + langCode;
}

/**
 * Get current language from cookie or return default
 * @returns {string} Current language code
 */
function getCurrentLanguage() {
    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === 'language') {
            return value;
        }
    }
    return 'en'; // default language
}

/**
 * Format date based on current language
 * @param {Date|string} date - Date to format
 * @param {string|null} lang - Language code (optional, uses current if not provided)
 * @returns {string} Formatted date string
 */
function formatDate(date, lang = null) {
    if (!lang) lang = getCurrentLanguage();
    
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    };
    
    const locale = lang === 'zh-cn' ? 'zh-CN' : 'en-US';
    return new Intl.DateTimeFormat(locale, options).format(new Date(date));
}

/**
 * Format date and time based on current language
 * @param {Date|string} date - Date to format
 * @param {string|null} lang - Language code (optional, uses current if not provided)
 * @returns {string} Formatted datetime string
 */
function formatDateTime(date, lang = null) {
    if (!lang) lang = getCurrentLanguage();
    
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    };
    
    const locale = lang === 'zh-cn' ? 'zh-CN' : 'en-US';
    return new Intl.DateTimeFormat(locale, options).format(new Date(date));
}

/**
 * Format numbers based on current language
 * @param {number} number - Number to format
 * @param {string|null} lang - Language code (optional, uses current if not provided)
 * @returns {string} Formatted number string
 */
function formatNumber(number, lang = null) {
    if (!lang) lang = getCurrentLanguage();
    
    const locale = lang === 'zh-cn' ? 'zh-CN' : 'en-US';
    return new Intl.NumberFormat(locale).format(number);
}

/**
 * Initialize language-related functionality when DOM is ready
 */
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for language switcher if it exists
    const languageSelect = document.getElementById('language-select');
    if (languageSelect) {
        languageSelect.addEventListener('change', function() {
            switchLanguage(this.value);
        });
    }
    
    // Format any existing dates/numbers based on current language
    const currentLang = getCurrentLanguage();
    
    // Format dates with data-format-date attribute
    document.querySelectorAll('[data-format-date]').forEach(element => {
        const dateValue = element.getAttribute('data-format-date');
        if (dateValue) {
            element.textContent = formatDate(dateValue, currentLang);
        }
    });
    
    // Format datetimes with data-format-datetime attribute
    document.querySelectorAll('[data-format-datetime]').forEach(element => {
        const datetimeValue = element.getAttribute('data-format-datetime');
        if (datetimeValue) {
            element.textContent = formatDateTime(datetimeValue, currentLang);
        }
    });
    
    // Format numbers with data-format-number attribute
    document.querySelectorAll('[data-format-number]').forEach(element => {
        const numberValue = element.getAttribute('data-format-number');
        if (numberValue && !isNaN(numberValue)) {
            element.textContent = formatNumber(parseFloat(numberValue), currentLang);
        }
    });
});
