-- Fix the status_id column to be NOT NULL
-- This script completes the migration that failed

-- Update any remaining NULL status_id values
UPDATE customers SET status_id = (SELECT id FROM customer_statuses WHERE status_key = 'prospect') WHERE status_id IS NULL;

-- Drop the existing foreign key constraint
ALTER TABLE customers DROP FOREIGN KEY customers_ibfk_1;

-- Modify the column to NOT NULL
ALTER TABLE customers MODIFY COLUMN status_id INT NOT NULL;

-- Re-add the foreign key constraint with RESTRICT instead of SET NULL
ALTER TABLE customers ADD CONSTRAINT fk_customers_status 
    FOREIGN KEY (status_id) REFERENCES customer_statuses(id) ON DELETE RESTRICT;

-- Drop the old status ENUM column
ALTER TABLE customers DROP COLUMN status;

-- Create indexes for better performance
CREATE INDEX idx_customers_status_id ON customers(status_id);
CREATE INDEX idx_customers_status_changed_at ON customers(status_changed_at);
