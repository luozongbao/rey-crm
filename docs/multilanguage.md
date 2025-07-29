# Multi-language Support Implementation for Rey CRM

## Overview
This document outlines the implementation steps to add Simplified Chinese (简体中文) support to the Rey CRM application, along with a framework for future language additions.

## Current State Analysis
The Rey CRM application currently:
- Uses hardcoded English text throughout the interface
- Has UTF-8 database configuration (supports Chinese characters)
- Uses UTF-8 charset for email functionality
- Has no existing internationalization (i18n) framework

## Implementation Strategy

### Phase 1: Foundation Setup

#### 1.1 Create Language Infrastructure

**Create language directory structure:**
```
languages/
├── en/
│   └── messages.php
├── zh-cn/
│   └── messages.php
└── config.php
```

#### 1.2 Language Configuration File
Create `languages/config.php`:
```php
<?php
// Available languages
$available_languages = [
    'en' => [
        'name' => 'English',
        'native_name' => 'English',
        'flag' => '🇺🇸',
        'direction' => 'ltr'
    ],
    'zh-cn' => [
        'name' => 'Simplified Chinese',
        'native_name' => '简体中文',
        'flag' => '🇨🇳',
        'direction' => 'ltr'
    ]
];

// Default language
$default_language = 'en';
?>
```

#### 1.3 Language Message Files

**Create `languages/en/messages.php`:**
```php
<?php
return [
    // Navigation
    'dashboard' => 'Dashboard',
    'customers' => 'Customers',
    'all_activities' => 'All Activities',
    'email_projects' => 'Email Projects',
    'settings' => 'Settings',
    'profile' => 'Profile',
    'logout' => 'Logout',
    
    // Login page
    'login_title' => 'Welcome to Rey CRM',
    'login_subtitle' => 'Sign in to your account',
    'username' => 'Username',
    'password' => 'Password',
    'sign_in' => 'Sign In',
    'forgot_password' => 'Forgot Password?',
    
    // Dashboard
    'crm_dashboard' => 'CRM Dashboard',
    'total_customers' => 'Total Customers',
    'contact_status' => 'Contact Status',
    'contacted' => 'Contacted',
    'not_contacted' => 'Not Contacted',
    
    // Customer management
    'add_customer' => 'Add Customer',
    'edit_customer' => 'Edit Customer',
    'company_name' => 'Company Name',
    'address' => 'Address',
    'country' => 'Country',
    'province' => 'Province',
    'company_type' => 'Company Type',
    'contact_phone' => 'Contact Phone',
    'contact_email' => 'Contact Email',
    'website' => 'Website',
    'status' => 'Status',
    'notes' => 'Notes',
    
    // Status options
    'prospect' => 'Prospect',
    'qualified' => 'Qualified',
    'not_qualified' => 'Not Qualified',
    'new_customer' => 'New Customer',
    'active_customer' => 'Active Customer',
    'inactive_customer' => 'Inactive Customer',
    'lost_customer' => 'Lost Customer',
    'closed_lost' => 'Closed Lost',
    'closed_won' => 'Closed Won',
    
    // Actions
    'save' => 'Save',
    'cancel' => 'Cancel',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'view' => 'View',
    'export' => 'Export',
    'search' => 'Search',
    'add' => 'Add',
    
    // Common messages
    'success' => 'Success',
    'error' => 'Error',
    'warning' => 'Warning',
    'info' => 'Information',
    'loading' => 'Loading...',
    'no_data' => 'No data available',
    
    // Email functionality
    'send_email' => 'Send Email',
    'subject' => 'Subject',
    'message' => 'Message',
    'attachments' => 'Attachments',
    'send' => 'Send',
    
    // Date and time
    'created_at' => 'Created At',
    'updated_at' => 'Updated At',
    'last_contacted' => 'Last Contacted',
    'follow_up_date' => 'Follow Up Date'
];
?>
```

**Create `languages/zh-cn/messages.php`:**
```php
<?php
return [
    // Navigation
    'dashboard' => '仪表板',
    'customers' => '客户管理',
    'all_activities' => '所有活动',
    'email_projects' => '邮件项目',
    'settings' => '设置',
    'profile' => '个人资料',
    'logout' => '退出登录',
    
    // Login page
    'login_title' => '欢迎使用 Rey CRM',
    'login_subtitle' => '登录您的账户',
    'username' => '用户名',
    'password' => '密码',
    'sign_in' => '登录',
    'forgot_password' => '忘记密码？',
    
    // Dashboard
    'crm_dashboard' => 'CRM 仪表板',
    'total_customers' => '客户总数',
    'contact_status' => '联系状态',
    'contacted' => '已联系',
    'not_contacted' => '未联系',
    
    // Customer management
    'add_customer' => '添加客户',
    'edit_customer' => '编辑客户',
    'company_name' => '公司名称',
    'address' => '地址',
    'country' => '国家',
    'province' => '省份',
    'company_type' => '公司类型',
    'contact_phone' => '联系电话',
    'contact_email' => '联系邮箱',
    'website' => '网站',
    'status' => '状态',
    'notes' => '备注',
    
    // Status options
    'prospect' => '潜在客户',
    'qualified' => '合格客户',
    'not_qualified' => '不合格客户',
    'new_customer' => '新客户',
    'active_customer' => '活跃客户',
    'inactive_customer' => '非活跃客户',
    'lost_customer' => '流失客户',
    'closed_lost' => '关闭失败',
    'closed_won' => '成功关闭',
    
    // Actions
    'save' => '保存',
    'cancel' => '取消',
    'edit' => '编辑',
    'delete' => '删除',
    'view' => '查看',
    'export' => '导出',
    'search' => '搜索',
    'add' => '添加',
    
    // Common messages
    'success' => '成功',
    'error' => '错误',
    'warning' => '警告',
    'info' => '信息',
    'loading' => '加载中...',
    'no_data' => '暂无数据',
    
    // Email functionality
    'send_email' => '发送邮件',
    'subject' => '主题',
    'message' => '消息',
    'attachments' => '附件',
    'send' => '发送',
    
    // Date and time
    'created_at' => '创建时间',
    'updated_at' => '更新时间',
    'last_contacted' => '最后联系时间',
    'follow_up_date' => '跟进日期'
];
?>
```

### Phase 2: Core Implementation

#### 2.1 Language Helper Functions
Add to `includes/functions.php`:

```php
/**
 * Initialize language system
 */
function initLanguage() {
    session_start();
    
    // Get language from various sources (priority order)
    $lang = $_GET['lang'] ?? $_SESSION['language'] ?? $_COOKIE['language'] ?? getDefaultLanguage();
    
    // Validate language
    if (!isLanguageAvailable($lang)) {
        $lang = getDefaultLanguage();
    }
    
    // Store in session and cookie
    $_SESSION['language'] = $lang;
    setcookie('language', $lang, time() + (86400 * 30), '/'); // 30 days
    
    return $lang;
}

/**
 * Get available languages
 */
function getAvailableLanguages() {
    require_once __DIR__ . '/../languages/config.php';
    return $available_languages;
}

/**
 * Get default language
 */
function getDefaultLanguage() {
    require_once __DIR__ . '/../languages/config.php';
    return $default_language;
}

/**
 * Check if language is available
 */
function isLanguageAvailable($lang) {
    $available = getAvailableLanguages();
    return isset($available[$lang]);
}

/**
 * Load language messages
 */
function loadLanguageMessages($lang) {
    $file = __DIR__ . "/../languages/{$lang}/messages.php";
    if (file_exists($file)) {
        return include $file;
    }
    // Fallback to default language
    $default = getDefaultLanguage();
    $fallbackFile = __DIR__ . "/../languages/{$default}/messages.php";
    return file_exists($fallbackFile) ? include $fallbackFile : [];
}

/**
 * Translate function
 */
function __($key, $params = []) {
    static $messages = null;
    
    if ($messages === null) {
        $lang = $_SESSION['language'] ?? getDefaultLanguage();
        $messages = loadLanguageMessages($lang);
    }
    
    $text = $messages[$key] ?? $key;
    
    // Replace parameters
    if (!empty($params)) {
        foreach ($params as $param => $value) {
            $text = str_replace("{{$param}}", $value, $text);
        }
    }
    
    return $text;
}

/**
 * Get current language
 */
function getCurrentLanguage() {
    return $_SESSION['language'] ?? getDefaultLanguage();
}

/**
 * Get current language info
 */
function getCurrentLanguageInfo() {
    $lang = getCurrentLanguage();
    $available = getAvailableLanguages();
    return $available[$lang] ?? $available[getDefaultLanguage()];
}
```

#### 2.2 Update Configuration
Add to `includes/config.php`:

```php
// Initialize language system
$current_language = initLanguage();
```

#### 2.3 Update Header Template
Modify `includes/header.php`:

```php
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize language
$current_language = initLanguage();
$lang_info = getCurrentLanguageInfo();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php' && basename($_SERVER['PHP_SELF']) !== 'install.php') {
    header('Location: /login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('rey_crm'); ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/timezone.js?v=2.0" defer></script>
    <!-- Language support CSS -->
    <?php if ($lang_info['direction'] === 'rtl'): ?>
    <link rel="stylesheet" href="/assets/css/rtl.css">
    <?php endif; ?>
</head>
<body <?php echo $lang_info['direction'] === 'rtl' ? 'class="rtl"' : ''; ?>>
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <h1 class="logo">
                    <a href="/index.php" title="<?php echo __('rey_crm_dashboard'); ?>">
                        <span><?php echo __('rey_crm'); ?></span>
                    </a>
                </h1>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Mobile menu button -->
                <button class="mobile-menu-button" aria-label="<?php echo __('open_menu'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                    </svg>
                </button>

                <nav class="main-nav">
                    <ul class="nav-list">
                        <li class="nav-item">
                            <a href="/dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                                <?php echo __('dashboard'); ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/customers.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
                                <?php echo __('customers'); ?>
                            </a>
                        </li>
                        <!-- Continue with other navigation items... -->
                    </ul>
                    
                    <!-- Language switcher -->
                    <div class="language-switcher">
                        <select id="language-select" onchange="switchLanguage(this.value)">
                            <?php foreach (getAvailableLanguages() as $code => $info): ?>
                                <option value="<?php echo $code; ?>" <?php echo $code === $current_language ? 'selected' : ''; ?>>
                                    <?php echo $info['flag'] . ' ' . $info['native_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </header>
```

### Phase 3: Database Enhancement

#### 3.1 Add Language Settings to Database
Add to database migration:

```sql
-- Add language preference to users table
ALTER TABLE users ADD COLUMN preferred_language VARCHAR(10) DEFAULT 'en' AFTER role;

-- Add language settings
INSERT IGNORE INTO settings (setting_name, value) VALUES
('default_language', 'en'),
('available_languages', 'en,zh-cn');

-- Update existing users with default language
UPDATE users SET preferred_language = 'en' WHERE preferred_language IS NULL;
```

#### 3.2 Multilingual Content Support
For future multilingual content storage:

```sql
-- Create translations table for dynamic content
CREATE TABLE IF NOT EXISTS translations (
    translation_id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    language_code VARCHAR(10) NOT NULL,
    translated_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_translation (table_name, field_name, record_id, language_code)
);
```

### Phase 4: Frontend Implementation

#### 4.1 JavaScript Language Support
Create `assets/js/language.js`:

```javascript
/**
 * Language switching functionality
 */
function switchLanguage(langCode) {
    // Set language preference
    document.cookie = `language=${langCode}; path=/; max-age=${30 * 24 * 60 * 60}`;
    
    // Reload page to apply new language
    window.location.reload();
}

/**
 * Get current language from cookie or default
 */
function getCurrentLanguage() {
    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === 'language') {
            return value;
        }
    }
    return 'en'; // default
}

/**
 * Format date based on language
 */
function formatDate(date, lang = null) {
    if (!lang) lang = getCurrentLanguage();
    
    const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    };
    
    return new Intl.DateTimeFormat(lang === 'zh-cn' ? 'zh-CN' : 'en-US', options).format(new Date(date));
}

/**
 * Format numbers based on language
 */
function formatNumber(number, lang = null) {
    if (!lang) lang = getCurrentLanguage();
    
    return new Intl.NumberFormat(lang === 'zh-cn' ? 'zh-CN' : 'en-US').format(number);
}
```

#### 4.2 CSS Language Support
Create `assets/css/language.css`:

```css
/* Language-specific styling */
.language-switcher {
    margin-left: 1rem;
}

.language-switcher select {
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    font-size: 14px;
}

/* Chinese font support */
[lang="zh-cn"] {
    font-family: "Microsoft YaHei", "PingFang SC", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
}

/* RTL support for future languages */
.rtl {
    direction: rtl;
}

.rtl .nav-list {
    flex-direction: row-reverse;
}

.rtl .language-switcher {
    margin-left: 0;
    margin-right: 1rem;
}
```

### Phase 5: Page-by-Page Implementation

#### 5.1 Login Page Updates
Update `login.php` to use translation functions:

```php
// Replace hardcoded text with translation calls
<title><?php echo __('login'); ?> - <?php echo __('rey_crm'); ?></title>

<h1 class="auth-title"><?php echo __('login_title'); ?></h1>
<p class="auth-subtitle"><?php echo __('login_subtitle'); ?></p>

<label for="username"><?php echo __('username'); ?></label>
<label for="password"><?php echo __('password'); ?></label>

<button type="submit" class="btn btn-primary btn-block">
    <?php echo __('sign_in'); ?>
</button>

<a href="forgot_password.php" class="text-link"><?php echo __('forgot_password'); ?></a>
```

#### 5.2 Dashboard Updates
Update `dashboard.php`:

```php
<h1><?php echo __('crm_dashboard'); ?></h1>

<h2><?php echo __('total_customers'); ?></h2>
<h2><?php echo __('contact_status'); ?></h2>
<span><?php echo __('contacted'); ?>:</span>
<span><?php echo __('not_contacted'); ?>:</span>
```

#### 5.3 Customer Management Updates
Update all customer-related pages with appropriate translation calls.

### Phase 6: Testing and Validation

#### 6.1 Language Switching Test
Create test cases to verify:
- Language switching works correctly
- User preferences are saved
- Fallback to default language works
- Database queries work with UTF-8 content

#### 6.2 Chinese Input Testing
Test scenarios:
- Chinese company names
- Chinese addresses and notes
- Chinese email content
- Search functionality with Chinese characters

#### 6.3 Browser Compatibility
Test on:
- Chrome/Chromium
- Firefox
- Safari
- Edge
- Mobile browsers

### Phase 7: Deployment Guidelines

#### 7.1 Environment Preparation
- Ensure MySQL/MariaDB supports utf8mb4
- Verify PHP mbstring extension is enabled
- Set proper charset headers

#### 7.2 Database Migration
1. Backup existing database
2. Run migration scripts
3. Verify data integrity
4. Test with sample Chinese data

#### 7.3 File Deployment
1. Upload language files
2. Update existing PHP files
3. Add new CSS/JS files
4. Clear any caches

### Phase 8: Future Enhancements

#### 8.1 Additional Languages
Framework supports easy addition of:
- Traditional Chinese (繁體中文)
- Japanese (日本語)
- Korean (한국어)
- Spanish (Español)
- Other languages

#### 8.2 Advanced Features
- Date/time localization
- Currency formatting
- Number formatting
- Timezone handling per language
- Email templates in multiple languages

#### 8.3 Content Management
- Admin interface for translation management
- Export/import translation files
- Translation status tracking
- Professional translation integration

### Phase 9: Maintenance

#### 9.1 Translation Updates
- Regular review of translations
- User feedback collection
- Professional translation review
- Version control for language files

#### 9.2 Performance Considerations
- Language file caching
- Lazy loading of translations
- CDN for language assets
- Database query optimization

## Implementation Timeline

**Week 1-2:** Foundation Setup (Phases 1-2)
- Create language infrastructure
- Implement core functions
- Basic translation system

**Week 3-4:** Database and Backend (Phase 3)
- Database enhancements
- Backend integration
- Testing framework

**Week 5-6:** Frontend Implementation (Phases 4-5)
- JavaScript language support
- CSS updates
- Page-by-page translation

**Week 7:** Testing and Deployment (Phases 6-7)
- Comprehensive testing
- Production deployment
- User training

**Ongoing:** Maintenance and Enhancement (Phases 8-9)
- Feature additions
- Translation improvements
- Performance optimization

## Conclusion

This implementation provides a robust foundation for multilingual support in Rey CRM, starting with Simplified Chinese and allowing for easy expansion to additional languages in the future. The modular approach ensures maintainability and scalability while preserving existing functionality.
