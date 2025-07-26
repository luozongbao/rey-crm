-- Create customers table with website field
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
    status ENUM('Prospect', 
                'Qualified', 
                'Not Qualified', 
                'New Customer', 
                'Active Customer', 
                'Inactive Customer',
                'Lost Customer',
                'Closed Lost',
                'Closed Won'
                 ) DEFAULT 'Prospect',
    notes TEXT,
    last_contacted_date TIMESTAMP NULL,
    assigned_user_id INT NULL,
    created_by_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Rest of the tables remain the same
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

-- Create users table for user management
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

-- Add foreign key constraints for customer assignment
ALTER TABLE customers ADD CONSTRAINT fk_customers_assigned_user 
    FOREIGN KEY (assigned_user_id) REFERENCES users(user_id) ON DELETE SET NULL;
ALTER TABLE customers ADD CONSTRAINT fk_customers_created_by 
    FOREIGN KEY (created_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL;


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
    sent_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    to_email VARCHAR(255) NOT NULL,
    cc VARCHAR(255),
    project_id INT,
    subject VARCHAR(255),
    attachments TEXT, -- JSON or comma-separated file locations
    FOREIGN KEY (project_id) REFERENCES email_projects(project_id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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