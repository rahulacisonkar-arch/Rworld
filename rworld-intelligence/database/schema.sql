-- RWorld Intelligence Shared Database Schema

-- Core Tables
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT DEFAULT 'user', -- 'admin', 'user', 'manager'
    is_active INTEGER DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS modules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    description TEXT,
    is_active INTEGER DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

-- Artee ERP Tables
CREATE TABLE IF NOT EXISTS erp_inventory (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sku TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    category TEXT,
    quantity INTEGER DEFAULT 0,
    price REAL DEFAULT 0.0,
    location TEXT,
    description TEXT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS erp_customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT,
    phone TEXT,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS erp_orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER,
    total_amount REAL DEFAULT 0.0,
    status TEXT DEFAULT 'pending', -- 'pending', 'processing', 'completed', 'cancelled'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(customer_id) REFERENCES erp_customers(id)
);

CREATE TABLE IF NOT EXISTS erp_purchasing (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    supplier_name TEXT NOT NULL,
    item_sku TEXT NOT NULL,
    quantity INTEGER DEFAULT 0,
    cost REAL DEFAULT 0.0,
    status TEXT DEFAULT 'pending', -- 'pending', 'ordered', 'received'
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- AI Shipping Agent Tables
CREATE TABLE IF NOT EXISTS shipping_jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_subject TEXT,
    sender TEXT,
    raw_body TEXT,
    extracted_address TEXT,
    ocr_text TEXT,
    status TEXT DEFAULT 'pending', -- 'pending', 'extracted', 'completed', 'failed'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Fabric Scraper Tables
CREATE TABLE IF NOT EXISTS scraper_products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url TEXT UNIQUE NOT NULL,
    name TEXT,
    sku TEXT,
    manufacturer TEXT,
    description TEXT,
    width TEXT,
    type_of_fabric TEXT,
    fiber_content TEXT,
    retail_price TEXT,
    image_url TEXT,
    scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- RoofIQ AI Tables
CREATE TABLE IF NOT EXISTS roofiq_projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_name TEXT NOT NULL,
    address TEXT,
    roof_area REAL DEFAULT 0.0, -- sq ft
    estimated_bom TEXT, -- JSON format of materials
    total_price REAL DEFAULT 0.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
