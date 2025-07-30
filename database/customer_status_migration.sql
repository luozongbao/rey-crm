-- Customer Status System Migration
-- This script migrates the current ENUM-based status system to a proper 
-- internationalized status system with timeline tracking

-- Step 1: Create customer status tables
CREATE TABLE IF NOT EXISTS customer_statuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    status_key VARCHAR(50) UNIQUE NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Step 2: Create status translations table
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

-- Step 3: Create customer status history table for timeline tracking
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

-- Step 4: Insert the new status definitions
INSERT INTO customer_statuses (status_key, sort_order, is_active) VALUES
('lead', 1, TRUE),
('prospect', 2, TRUE),
('qualified', 3, TRUE),
('not_qualified', 4, TRUE),
('new_customer', 5, TRUE),
('active_customer', 6, TRUE),
('lost_customer', 7, TRUE);

-- Step 5: Insert English translations
INSERT INTO customer_status_translations (status_id, locale, name, description) VALUES
((SELECT id FROM customer_statuses WHERE status_key = 'lead'), 'en', 'Lead', 'New Contact Information'),
((SELECT id FROM customer_statuses WHERE status_key = 'prospect'), 'en', 'Prospect', 'Potential customer showing initial interest'),
((SELECT id FROM customer_statuses WHERE status_key = 'qualified'), 'en', 'Qualified', 'Customer in active negotiation process'),
((SELECT id FROM customer_statuses WHERE status_key = 'not_qualified'), 'en', 'Not Qualified', 'Customer determined to not be a good fit'),
((SELECT id FROM customer_statuses WHERE status_key = 'new_customer'), 'en', 'New Customer', 'Customer who has made their first purchase'),
((SELECT id FROM customer_statuses WHERE status_key = 'active_customer'), 'en', 'Active Customer', 'Returning customer with ongoing business'),
((SELECT id FROM customer_statuses WHERE status_key = 'lost_customer'), 'en', 'Lost Customer', 'Customer who is no longer doing business with us');

-- Step 6: Insert Chinese translations
INSERT INTO customer_status_translations (status_id, locale, name, description) VALUES
((SELECT id FROM customer_statuses WHERE status_key = 'lead'), 'zh-cn', '潜在客户', '对产品或服务表现出初步兴趣的个人或公司，但尚未经过深入评估'),
((SELECT id FROM customer_statuses WHERE status_key = 'prospect'), 'zh-cn', '意向客户', '显示初步兴趣的潜在客户'),
((SELECT id FROM customer_statuses WHERE status_key = 'qualified'), 'zh-cn', '洽谈客户', '正在积极谈判过程中的客户'),
((SELECT id FROM customer_statuses WHERE status_key = 'not_qualified'), 'zh-cn', '无效客户', '确定不适合的客户'),
((SELECT id FROM customer_statuses WHERE status_key = 'new_customer'), 'zh-cn', '成交客户', '已完成首次购买的客户'),
((SELECT id FROM customer_statuses WHERE status_key = 'active_customer'), 'zh-cn', '回头客户', '有持续业务往来的回头客户'),
((SELECT id FROM customer_statuses WHERE status_key = 'lost_customer'), 'zh-cn', '失去客户', '不再与我们有业务往来的客户');

-- Step 7: Add new status_id column to customers table
ALTER TABLE customers ADD COLUMN status_id INT NULL AFTER status;
ALTER TABLE customers ADD COLUMN status_changed_at TIMESTAMP NULL AFTER status_id;
ALTER TABLE customers ADD COLUMN status_changed_by INT NULL AFTER status_changed_at;

-- Step 8: Create foreign key constraints for new columns
ALTER TABLE customers ADD FOREIGN KEY (status_id) REFERENCES customer_statuses(id) ON DELETE SET NULL;
ALTER TABLE customers ADD FOREIGN KEY (status_changed_by) REFERENCES users(user_id) ON DELETE SET NULL;

-- Step 9: Migrate existing status data
UPDATE customers SET 
    status_id = (SELECT id FROM customer_statuses WHERE status_key = 'prospect'),
    status_changed_at = created_at,
    status_changed_by = created_by_user_id
WHERE status = 'Prospect';

UPDATE customers SET 
    status_id = (SELECT id FROM customer_statuses WHERE status_key = 'qualified'),
    status_changed_at = created_at,
    status_changed_by = created_by_user_id
WHERE status = 'Qualified';

UPDATE customers SET 
    status_id = (SELECT id FROM customer_statuses WHERE status_key = 'not_qualified'),
    status_changed_at = created_at,
    status_changed_by = created_by_user_id
WHERE status = 'Not Qualified';

UPDATE customers SET 
    status_id = (SELECT id FROM customer_statuses WHERE status_key = 'new_customer'),
    status_changed_at = created_at,
    status_changed_by = created_by_user_id
WHERE status IN ('New Customer', 'Closed Won');

UPDATE customers SET 
    status_id = (SELECT id FROM customer_statuses WHERE status_key = 'active_customer'),
    status_changed_at = created_at,
    status_changed_by = created_by_user_id
WHERE status IN ('Active Customer', 'Inactive Customer');

UPDATE customers SET 
    status_id = (SELECT id FROM customer_statuses WHERE status_key = 'lost_customer'),
    status_changed_at = created_at,
    status_changed_by = created_by_user_id
WHERE status IN ('Lost Customer', 'Closed Lost');

-- Step 10: Create initial status history entries for existing customers
INSERT INTO customer_status_history (customer_id, from_status_id, to_status_id, changed_by, changed_at, notes)
SELECT 
    customer_id,
    NULL as from_status_id,
    status_id as to_status_id,
    COALESCE(status_changed_by, created_by_user_id, 1) as changed_by,
    COALESCE(status_changed_at, created_at) as changed_at,
    'Initial status migration' as notes
FROM customers 
WHERE status_id IS NOT NULL;

-- Step 11: Make status_id NOT NULL after migration
UPDATE customers SET status_id = (SELECT id FROM customer_statuses WHERE status_key = 'prospect') WHERE status_id IS NULL;

-- Drop the foreign key constraint temporarily
ALTER TABLE customers DROP FOREIGN KEY customers_ibfk_4;

-- Modify the column to NOT NULL
ALTER TABLE customers MODIFY COLUMN status_id INT NOT NULL;

-- Re-add the foreign key constraint with RESTRICT instead of SET NULL for status_id
ALTER TABLE customers ADD CONSTRAINT fk_customers_status 
    FOREIGN KEY (status_id) REFERENCES customer_statuses(id) ON DELETE RESTRICT;

-- Step 12: Drop the old status ENUM column (uncomment when ready to complete migration)
-- ALTER TABLE customers DROP COLUMN status;

-- Step 13: Create indexes for better performance
CREATE INDEX idx_customers_status_id ON customers(status_id);
CREATE INDEX idx_customers_status_changed_at ON customers(status_changed_at);
