-- Create the database
CREATE DATABASE IF NOT EXISTS personal_crm;
USE personal_crm;

-- Create customers table
CREATE TABLE IF NOT EXISTS customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(100) NOT NULL,
    location VARCHAR(50) NOT NULL,
    company_type VARCHAR(50),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    status ENUM('Active', 'Inactive', 'Prospect') DEFAULT 'Prospect',
    notes TEXT,
    last_contacted_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

-- Create action_history table
CREATE TABLE IF NOT EXISTS action_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    contact_id INT,
    action_datetime DATETIME NOT NULL,
    action TEXT NOT NULL,
    response TEXT NOT NULL,
    next_step TEXT NOT NULL,
    follow_up_datetime DATETIME NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contact_persons(contact_id) ON DELETE SET NULL
);