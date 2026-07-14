-- Artee Fabrics & Home Shipping Portal Schema

CREATE DATABASE IF NOT EXISTS artee_shipping;
USE artee_shipping;

-- Drop tables if they exist to allow clean reseeding (foreign keys check disabled)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS request_labels;
DROP TABLE IF EXISTS label_requests;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS stores;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS saved_addresses;
SET FOREIGN_KEY_CHECKS = 1;

-- Stores Table
CREATE TABLE stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_code VARCHAR(20) NOT NULL UNIQUE,
    store_name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(50) NOT NULL,
    zip VARCHAR(20) NOT NULL,
    phone VARCHAR(100) NOT NULL,
    notification_emails TEXT NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Super Admin', 'Logistics Admin', 'Store User') NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    remember_token VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Label Requests Table
CREATE TABLE label_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) NOT NULL UNIQUE,
    store_id INT NOT NULL,
    
    -- Ship From Address
    ship_from_name VARCHAR(100) NOT NULL,
    ship_from_company VARCHAR(100) NOT NULL,
    ship_from_address1 VARCHAR(255) NOT NULL,
    ship_from_address2 VARCHAR(255) NULL,
    ship_from_city VARCHAR(100) NOT NULL,
    ship_from_state VARCHAR(50) NOT NULL,
    ship_from_zip VARCHAR(20) NOT NULL,
    ship_from_phone VARCHAR(100) NOT NULL,
    ship_from_email VARCHAR(255) NULL,
    
    -- Ship To Address
    ship_to_name VARCHAR(100) NOT NULL,
    ship_to_company VARCHAR(100) NOT NULL,
    ship_to_address1 VARCHAR(255) NOT NULL,
    ship_to_address2 VARCHAR(255) NULL,
    ship_to_city VARCHAR(100) NOT NULL,
    ship_to_state VARCHAR(50) NOT NULL,
    ship_to_zip VARCHAR(20) NOT NULL,
    ship_to_phone VARCHAR(100) NOT NULL,
    ship_to_email VARCHAR(255) NULL,
    
    -- Order Details
    sales_order_number VARCHAR(50) NOT NULL,
    request_reference VARCHAR(50) NULL,
    
    -- Package Details
    length DECIMAL(10,2) NOT NULL,
    width DECIMAL(10,2) NOT NULL,
    height DECIMAL(10,2) NOT NULL,
    weight_lbs DECIMAL(10,2) NOT NULL,
    
    -- Shipping Details
    shipping_method VARCHAR(50) NOT NULL,
    customer_freight_charge DECIMAL(10,2) NOT NULL,
    special_instructions TEXT NULL,
    internal_notes TEXT NULL,
    status ENUM('Pending', 'Processing', 'Label Created', 'Label Sent', 'Completed', 'Cancelled') DEFAULT 'Pending',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_requests_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Request Labels Table (Supports multiple cartons/labels per request)
CREATE TABLE request_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    label_file VARCHAR(255) NOT NULL,
    tracking_number VARCHAR(100) NULL,
    carrier VARCHAR(50) NULL,
    estimated_delivery_date DATE NULL,
    actual_shipping_cost DECIMAL(10,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_labels_request FOREIGN KEY (request_id) REFERENCES label_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System Settings Table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications Table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs Table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    request_id INT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_logs_request FOREIGN KEY (request_id) REFERENCES label_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saved Addresses Table
CREATE TABLE saved_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    address_type ENUM('from', 'to') NOT NULL,
    name VARCHAR(100) NOT NULL,
    company VARCHAR(100) NOT NULL,
    address1 VARCHAR(255) NOT NULL,
    address2 VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(50) NOT NULL,
    zip VARCHAR(20) NOT NULL,
    phone VARCHAR(100) NOT NULL,
    email VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_saved_addresses_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
