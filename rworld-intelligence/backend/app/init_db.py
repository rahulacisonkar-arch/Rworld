"""
Auto-initializer for the RWorld SQLite database.
Runs on startup to create all tables and seed demo data if db doesn't exist.
"""
import sqlite3
import os
from passlib.context import CryptContext

pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_DIR = os.path.join(BASE_DIR, "database")
DB_PATH = os.path.join(DB_DIR, "rworld.db")


def init_db():
    """Create all tables and seed demo data if the database doesn't exist."""
    os.makedirs(DB_DIR, exist_ok=True)
    is_new = not os.path.exists(DB_PATH)

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    print(f"[DB] Initializing database at: {DB_PATH}")

    # ── Core Tables ──────────────────────────────────────────────────────────
    cursor.executescript("""
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT DEFAULT 'user',
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

    -- ERP Tables
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
        status TEXT DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(customer_id) REFERENCES erp_customers(id)
    );

    CREATE TABLE IF NOT EXISTS erp_returns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER,
        reason TEXT,
        status TEXT DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS erp_purchase_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        supplier_name TEXT NOT NULL,
        item_sku TEXT,
        quantity INTEGER DEFAULT 0,
        cost REAL DEFAULT 0.0,
        status TEXT DEFAULT 'pending',
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS erp_grn (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        po_id INTEGER,
        received_qty INTEGER DEFAULT 0,
        notes TEXT,
        received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS erp_shifts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        opened_by INTEGER,
        closed_by INTEGER,
        opening_balance REAL DEFAULT 0.0,
        closing_balance REAL DEFAULT 0.0,
        status TEXT DEFAULT 'open',
        opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        closed_at TIMESTAMP
    );

    -- Shipping Agent Tables
    CREATE TABLE IF NOT EXISTS stores (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_code TEXT UNIQUE NOT NULL,
        store_name TEXT NOT NULL,
        address TEXT NOT NULL,
        city TEXT NOT NULL,
        state TEXT NOT NULL,
        zip TEXT NOT NULL,
        phone TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS store_addresses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_id INTEGER NOT NULL,
        label TEXT,
        address_line TEXT NOT NULL,
        city TEXT NOT NULL,
        state TEXT NOT NULL,
        zip TEXT NOT NULL,
        is_default INTEGER DEFAULT 0,
        FOREIGN KEY(store_id) REFERENCES stores(id)
    );

    CREATE TABLE IF NOT EXISTS shipping_jobs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email_subject TEXT,
        sender TEXT,
        raw_body TEXT,
        extracted_address TEXT,
        ocr_text TEXT,
        status TEXT DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS label_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        request_number TEXT UNIQUE,
        store_id INTEGER,
        ship_to_name TEXT,
        ship_to_address TEXT,
        ship_to_city TEXT,
        ship_to_state TEXT,
        ship_to_zip TEXT,
        pkg_length REAL DEFAULT 0,
        pkg_width REAL DEFAULT 0,
        pkg_height REAL DEFAULT 0,
        weight_lbs REAL DEFAULT 0,
        status TEXT DEFAULT 'Pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(store_id) REFERENCES stores(id)
    );

    CREATE TABLE IF NOT EXISTS request_labels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        request_id INTEGER,
        label_file TEXT,
        tracking_number TEXT,
        carrier TEXT,
        estimated_delivery_date TEXT,
        actual_shipping_cost REAL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(request_id) REFERENCES label_requests(id)
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
        roof_area REAL DEFAULT 0.0,
        estimated_bom TEXT,
        total_price REAL DEFAULT 0.0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    -- Attendance Tables
    CREATE TABLE IF NOT EXISTS employees (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_id INTEGER,
        name TEXT NOT NULL,
        email TEXT,
        phone TEXT,
        designation TEXT,
        hourly_rate REAL DEFAULT 0.0,
        status TEXT DEFAULT 'Active',
        employment_type TEXT DEFAULT 'Full-time',
        hire_date TEXT,
        salary_grade TEXT DEFAULT 'Grade A',
        emergency_contacts TEXT,
        deleted_at TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(store_id) REFERENCES stores(id)
    );

    CREATE TABLE IF NOT EXISTS attendance_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        store_id INTEGER NOT NULL,
        login_time TEXT NOT NULL,
        logout_time TEXT,
        date TEXT NOT NULL,
        status TEXT DEFAULT 'Checked In',
        FOREIGN KEY(employee_id) REFERENCES employees(id),
        FOREIGN KEY(store_id) REFERENCES stores(id)
    );

    -- Utility Tables
    CREATE TABLE IF NOT EXISTS utility_connections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_id INTEGER NOT NULL,
        utility_type TEXT NOT NULL,
        provider_name TEXT NOT NULL,
        account_number TEXT NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(store_id) REFERENCES stores(id)
    );

    CREATE TABLE IF NOT EXISTS bills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        connection_id INTEGER NOT NULL,
        store_id INTEGER NOT NULL,
        statement_date TEXT,
        due_date TEXT,
        amount REAL DEFAULT 0.0,
        bill_file_path TEXT,
        status TEXT DEFAULT 'Pending',
        paid_at TEXT,
        transaction_ref TEXT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(connection_id) REFERENCES utility_connections(id),
        FOREIGN KEY(store_id) REFERENCES stores(id)
    );
    """)

    conn.commit()

    # ── Seed Demo Data (only if new database) ────────────────────────────────
    cursor.execute("SELECT COUNT(*) FROM users")
    if cursor.fetchone()[0] == 0:
        print("[DB] Seeding demo data...")

        # Admin user (password: admin123)
        pw_hash = pwd_context.hash("admin123")
        cursor.execute(
            "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)",
            ("admin", "admin@arteefabrics.com", pw_hash, "admin")
        )

        # Demo stores
        stores = [
            ("78", "Artee Fabrics - Store 78", "123 Main St", "Boston", "MA", "02101", "617-555-0001"),
            ("42", "Artee Fabrics - Store 42", "456 Oak Ave", "Chicago", "IL", "60601", "312-555-0002"),
            ("15", "Artee Fabrics - Store 15", "789 Pine Rd", "Houston", "TX", "77001", "713-555-0003"),
        ]
        cursor.executemany(
            "INSERT OR IGNORE INTO stores (store_code, store_name, address, city, state, zip, phone) VALUES (?,?,?,?,?,?,?)",
            stores
        )

        # Demo ERP inventory
        inventory = [
            ("LINEN-NAT-54", "Natural Linen 54\"", "Linen", 450, 12.99, "Aisle A"),
            ("COTTON-WHT-60", "White Cotton 60\"", "Cotton", 320, 8.49, "Aisle B"),
            ("VELVET-BLK-56", "Black Velvet 56\"", "Velvet", 85, 24.99, "Aisle C"),
            ("SILK-RED-45", "Red Silk 45\"", "Silk", 120, 34.99, "Aisle D"),
            ("DENIM-BLU-58", "Blue Denim 58\"", "Denim", 200, 14.99, "Aisle E"),
        ]
        cursor.executemany(
            "INSERT OR IGNORE INTO erp_inventory (sku, name, category, quantity, price, location) VALUES (?,?,?,?,?,?)",
            inventory
        )

        # Demo customers
        customers = [
            ("John Smith", "john@example.com", "617-555-1001", "10 Elm St, Boston MA 02101"),
            ("Sarah Johnson", "sarah@example.com", "312-555-2002", "20 Oak Ave, Chicago IL 60601"),
            ("Mike Davis", "mike@example.com", "713-555-3003", "30 Pine Rd, Houston TX 77001"),
        ]
        for name, email, phone, addr in customers:
            cursor.execute(
                "INSERT OR IGNORE INTO erp_customers (name, email, phone, address) VALUES (?,?,?,?)",
                (name, email, phone, addr)
            )

        # Demo employees
        employees = [
            (1, "Alice Brown", "alice@arteefabrics.com", "617-555-0010", "Store Manager", 22.50, "Full-time"),
            (1, "Bob Wilson", "bob@arteefabrics.com", "617-555-0011", "Sales Associate", 15.00, "Full-time"),
            (2, "Carol White", "carol@arteefabrics.com", "312-555-0020", "Store Manager", 22.50, "Full-time"),
        ]
        cursor.executemany(
            "INSERT OR IGNORE INTO employees (store_id, name, email, phone, designation, hourly_rate, employment_type) VALUES (?,?,?,?,?,?,?)",
            employees
        )

        # Demo scraper products
        products = [
            ("https://www.fabricmill.com/premium-linen.html", "Premium Natural Linen", "PREM-LINEN", "FabricMill", "100% Belgian flax linen", "54 inches", "Linen", "100% Linen", "$29.99"),
            ("https://www.fabricmill.com/vintage-velvet.html", "Vintage Cotton Velvet", "VINT-VELVET", "FabricMill", "Luxurious plush velvet", "56 inches", "Velvet", "100% Cotton", "$34.99"),
            ("https://www.fabricmill.com/silk-charmeuse.html", "Silk Charmeuse", "SILK-CHARM", "FabricMill", "Lustrous silk charmeuse", "45 inches", "Silk", "100% Silk", "$49.99"),
        ]
        cursor.executemany(
            "INSERT OR IGNORE INTO scraper_products (url, name, sku, manufacturer, description, width, type_of_fabric, fiber_content, retail_price) VALUES (?,?,?,?,?,?,?,?,?)",
            products
        )

        # Demo RoofIQ project
        cursor.execute(
            "INSERT OR IGNORE INTO roofiq_projects (project_name, address, roof_area, total_price) VALUES (?,?,?,?)",
            ("Johnson Residence", "10 Elm St, Boston MA 02101", 2400.0, 18750.0)
        )

        conn.commit()
        print("[DB] ✅ Demo data seeded successfully!")
    else:
        print("[DB] ✅ Database already initialized — skipping seed.")

    conn.close()
    print("[DB] ✅ Database ready.")
