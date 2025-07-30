-- Create users table for user management first (referenced by other tables)
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    preferred_language VARCHAR(10) DEFAULT 'en',
    -- Personal email settings
    smtp_host VARCHAR(255) DEFAULT NULL,
    smtp_port INT DEFAULT NULL,
    smtp_username VARCHAR(255) DEFAULT NULL,
    smtp_password VARCHAR(255) DEFAULT NULL,
    smtp_from_email VARCHAR(255) DEFAULT NULL,
    smtp_from_name VARCHAR(255) DEFAULT NULL,
    smtp_encryption ENUM('tls', 'ssl', 'none') DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Create customer status tables first (referenced by customers table)
CREATE TABLE IF NOT EXISTS customer_statuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    status_key VARCHAR(50) UNIQUE NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create status translations table for internationalization
CREATE TABLE IF NOT EXISTS customer_status_translations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    status_id INT NOT NULL,
    locale VARCHAR(5) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (status_id) REFERENCES customer_statuses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_status_locale (status_id, locale)
);

-- Insert the new status definitions
INSERT IGNORE INTO customer_statuses (status_key, sort_order, is_active) VALUES
('prospect', 1, TRUE),
('qualified', 2, TRUE),
('not_qualified', 3, TRUE),
('new_customer', 4, TRUE),
('active_customer', 5, TRUE),
('lost_customer', 6, TRUE);

-- Insert English translations
INSERT IGNORE INTO customer_status_translations (status_id, locale, name, description) VALUES
((SELECT id FROM customer_statuses WHERE status_key = 'prospect'), 'en', 'Prospect', 'Potential customer showing initial interest'),
((SELECT id FROM customer_statuses WHERE status_key = 'qualified'), 'en', 'Qualified', 'Customer in active negotiation process'),
((SELECT id FROM customer_statuses WHERE status_key = 'not_qualified'), 'en', 'Not Qualified', 'Customer determined to not be a good fit'),
((SELECT id FROM customer_statuses WHERE status_key = 'new_customer'), 'en', 'New Customer', 'Customer who has made their first purchase'),
((SELECT id FROM customer_statuses WHERE status_key = 'active_customer'), 'en', 'Active Customer', 'Returning customer with ongoing business'),
((SELECT id FROM customer_statuses WHERE status_key = 'lost_customer'), 'en', 'Lost Customer', 'Customer who is no longer doing business with us');

-- Insert Chinese translations
INSERT IGNORE INTO customer_status_translations (status_id, locale, name, description) VALUES
((SELECT id FROM customer_statuses WHERE status_key = 'prospect'), 'zh-cn', '潜在客户', '显示初步兴趣的潜在客户'),
((SELECT id FROM customer_statuses WHERE status_key = 'qualified'), 'zh-cn', '洽谈客户', '正在积极谈判过程中的客户'),
((SELECT id FROM customer_statuses WHERE status_key = 'not_qualified'), 'zh-cn', '无效客户', '确定不适合的客户'),
((SELECT id FROM customer_statuses WHERE status_key = 'new_customer'), 'zh-cn', '成交客户', '已完成首次购买的客户'),
((SELECT id FROM customer_statuses WHERE status_key = 'active_customer'), 'zh-cn', '回头客户', '有持续业务往来的回头客户'),
((SELECT id FROM customer_statuses WHERE status_key = 'lost_customer'), 'zh-cn', '失去客户', '不再与我们有业务往来的客户');

-- Create customers table with new status system
CREATE TABLE IF NOT EXISTS customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(100) NOT NULL,
    address VARCHAR(200),
    country VARCHAR(100),
    province VARCHAR(100),
    company_type VARCHAR(50),
    contact_phone VARCHAR(50),
    contact_email VARCHAR(100),
    website VARCHAR(100),
    status_id INT NOT NULL DEFAULT 1,
    status_changed_at TIMESTAMP NULL,
    status_changed_by INT NULL,
    notes TEXT,
    last_contacted_date TIMESTAMP NULL,
    assigned_user_id INT NULL,
    created_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (status_id) REFERENCES customer_statuses(id) ON DELETE RESTRICT,
    FOREIGN KEY (status_changed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_customers_status_id (status_id),
    INDEX idx_customers_status_changed_at (status_changed_at)
);

-- Create customer status history table for timeline tracking
CREATE TABLE IF NOT EXISTS customer_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    from_status_id INT NULL,
    to_status_id INT NOT NULL,
    changed_by INT NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (from_status_id) REFERENCES customer_statuses(id) ON DELETE SET NULL,
    FOREIGN KEY (to_status_id) REFERENCES customer_statuses(id) ON DELETE RESTRICT,
    FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_customer_changed_at (customer_id, changed_at),
    INDEX idx_changed_at (changed_at)
);

-- Create contact_persons table
CREATE TABLE IF NOT EXISTS contact_persons (
    contact_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    title VARCHAR(50),
    role VARCHAR(50),
    contact_number VARCHAR(20),
    contact_email VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE
);

-- Create action_history table (now all referenced tables exist)
CREATE TABLE IF NOT EXISTS action_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    contact_id INT,
    user_id INT,
    action_datetime DATETIME NOT NULL,
    action TEXT NOT NULL,
    contact_channel ENUM(
        'Email', 
        'Phone Call', 
        'WhatsApp', 
        'SMS', 
        'In-Person Meeting', 
        'Video Call', 
        'LinkedIn', 
        'WeChat', 
        'Other'
    ) NOT NULL DEFAULT 'Other',
    response TEXT NOT NULL,
    next_step TEXT NOT NULL,
    follow_up_datetime DATETIME NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contact_persons(contact_id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Create settings table for system configuration
CREATE TABLE IF NOT EXISTS settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) NOT NULL UNIQUE,
    value TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
INSERT IGNORE INTO settings (setting_name, value) VALUES
('items_per_page', '10');
-- Create password_reset_tokens table
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expiry_date DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);


-- Table for Email Projects
CREATE TABLE IF NOT EXISTS email_projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    cc VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    message TEXT,
    attachments TEXT, -- JSON or comma-separated file locations
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table for Sent Email History
CREATE TABLE IF NOT EXISTS sent_email_history (
    email_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    sent_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    to_email VARCHAR(255) NOT NULL,
    cc VARCHAR(255),
    project_id INT,
    subject VARCHAR(255),
    attachments TEXT, -- JSON or comma-separated file locations
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    FOREIGN KEY (project_id) REFERENCES email_projects(project_id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Security tables for Phase 1 implementation
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(255),
    attempt_time DATETIME NOT NULL,
    success TINYINT(1) DEFAULT 0,
    INDEX idx_ip_time (ip_address, attempt_time),
    INDEX idx_username_time (username, attempt_time)
);

-- Security log table for Phase 2 implementation
CREATE TABLE IF NOT EXISTS security_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    details JSON,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME NOT NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Rate limiting table for Phase 3 implementation
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rate_key VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_rate_key (rate_key),
    INDEX idx_created_at (created_at)
);

-- Security configuration table for Phase 3
CREATE TABLE IF NOT EXISTS security_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    is_encrypted TINYINT(1) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
);