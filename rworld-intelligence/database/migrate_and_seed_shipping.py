import sqlite3
import os

def main():
    db_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "rworld.db")
    print(f"Connecting to database: {db_path}")
    
    if not os.path.exists(db_path):
        print("Database file does not exist!")
        return
        
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    
    # 1. Create Tables
    print("Creating shipping portal tables...")
    
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS label_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        request_number TEXT NOT NULL UNIQUE,
        store_id INTEGER NOT NULL,
        ship_from_name TEXT NOT NULL,
        ship_from_company TEXT NOT NULL,
        ship_from_address1 TEXT NOT NULL,
        ship_from_address2 TEXT,
        ship_from_city TEXT NOT NULL,
        ship_from_state TEXT NOT NULL,
        ship_from_zip TEXT NOT NULL,
        ship_from_phone TEXT NOT NULL,
        ship_from_email TEXT,
        ship_to_name TEXT NOT NULL,
        ship_to_company TEXT NOT NULL,
        ship_to_address1 TEXT NOT NULL,
        ship_to_address2 TEXT,
        ship_to_city TEXT NOT NULL,
        ship_to_state TEXT NOT NULL,
        ship_to_zip TEXT NOT NULL,
        ship_to_phone TEXT NOT NULL,
        ship_to_email TEXT,
        sales_order_number TEXT NOT NULL,
        request_reference TEXT,
        length REAL NOT NULL,
        width REAL NOT NULL,
        height REAL NOT NULL,
        weight_lbs REAL NOT NULL,
        shipping_method TEXT NOT NULL,
        customer_freight_charge REAL NOT NULL,
        special_instructions TEXT,
        internal_notes TEXT,
        status TEXT DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE RESTRICT
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS request_labels (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        request_id INTEGER NOT NULL,
        label_file TEXT NOT NULL,
        tracking_number TEXT,
        carrier TEXT,
        estimated_delivery_date TEXT,
        actual_shipping_cost REAL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES label_requests(id) ON DELETE CASCADE
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS saved_addresses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_id INTEGER NOT NULL,
        address_type TEXT NOT NULL,
        name TEXT NOT NULL,
        company TEXT NOT NULL,
        address1 TEXT NOT NULL,
        address2 TEXT,
        city TEXT NOT NULL,
        state TEXT NOT NULL,
        zip TEXT NOT NULL,
        phone TEXT,
        email TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS shipping_system_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT NOT NULL UNIQUE,
        setting_value TEXT NOT NULL
    );
    """)

    print("Shipping tables created successfully.")
    
    # 2. Seed Settings
    print("Seeding settings...")
    try:
        cursor.execute("INSERT OR IGNORE INTO shipping_system_settings (setting_key, setting_value) VALUES ('minimum_freight_charge', '15.00')")
    except Exception as e:
        print(f"Error seeding settings: {e}")

    # 3. Seed Saved Addresses
    print("Seeding default saved addresses for stores...")
    cursor.execute("SELECT id FROM stores")
    store_ids = [row[0] for row in cursor.fetchall()]
    
    for sid in store_ids:
        # Check if already seeded
        cursor.execute("SELECT id FROM saved_addresses WHERE store_id = ? AND company = ?", (sid, 'PARLOR UPHOLSTERY'))
        if not cursor.fetchone():
            cursor.execute("""
                INSERT INTO saved_addresses (store_id, address_type, name, company, address1, city, state, zip, phone, email)
                VALUES (?, 'from', 'Staff', 'PARLOR UPHOLSTERY', '201 DEXTER AVENUE', 'WEST HARTFORD', 'CT', '06110', '860-555-0144', 'parlor@example.com')
            """, (sid,))
            cursor.execute("""
                INSERT INTO saved_addresses (store_id, address_type, name, company, address1, city, state, zip, phone, email)
                VALUES (?, 'to', 'Staff', 'PARLOR UPHOLSTERY', '201 DEXTER AVENUE', 'WEST HARTFORD', 'CT', '06110', '860-555-0144', 'parlor@example.com')
            """, (sid,))
            
        cursor.execute("SELECT id FROM saved_addresses WHERE store_id = ? AND company = ?", (sid, 'QUEYEN TRONG'))
        if not cursor.fetchone():
            cursor.execute("""
                INSERT INTO saved_addresses (store_id, address_type, name, company, address1, city, state, zip, phone, email)
                VALUES (?, 'from', 'Staff', 'QUEYEN TRONG', '184 FOREST LANE', 'CHESHIRE', 'CT', '06410', '203-555-0155', 'queyen@example.com')
            """, (sid,))
            cursor.execute("""
                INSERT INTO saved_addresses (store_id, address_type, name, company, address1, city, state, zip, phone, email)
                VALUES (?, 'to', 'Staff', 'QUEYEN TRONG', '184 FOREST LANE', 'CHESHIRE', 'CT', '06410', '203-555-0155', 'queyen@example.com')
            """, (sid,))

    # 4. Seed some sample label requests
    print("Seeding sample label requests...")
    if store_ids:
        sid = store_ids[0]
        requests = [
            ("REQ-2026-0001", sid, "Staff", "PARLOR UPHOLSTERY", "201 DEXTER AVENUE", "WEST HARTFORD", "CT", "06110", "860-555-0144", "parlor@example.com",
             "Alice Johnson", "Home Design Inc", "123 Maple St", "Boston", "MA", "02110", "617-555-9876", "alice@example.com",
             "SO-99210", "REF-9092", 12.0, 10.0, 8.0, 15.5, "UPS Ground", 25.00, "Leave at front door", "Pending"),
             
            ("REQ-2026-0002", sid, "Staff", "QUEYEN TRONG", "184 FOREST LANE", "CHESHIRE", "CT", "06410", "203-555-0155", "queyen@example.com",
             "Bob Smith", "Fabrics R Us", "456 Oak Rd", "New York", "NY", "10001", "212-555-8765", "bob@example.com",
             "SO-99211", "REF-9093", 24.0, 12.0, 12.0, 35.0, "FedEx 2Day", 45.00, "Signature required", "Label Created")
        ]
        
        for req in requests:
            cursor.execute("SELECT id FROM label_requests WHERE request_number = ?", (req[0],))
            if not cursor.fetchone():
                cursor.execute("""
                    INSERT INTO label_requests (
                        request_number, store_id, 
                        ship_from_name, ship_from_company, ship_from_address1, ship_from_city, ship_from_state, ship_from_zip, ship_from_phone, ship_from_email,
                        ship_to_name, ship_to_company, ship_to_address1, ship_to_city, ship_to_state, ship_to_zip, ship_to_phone, ship_to_email,
                        sales_order_number, request_reference, 
                        length, width, height, weight_lbs, 
                        shipping_method, customer_freight_charge, special_instructions, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """, req)
                
                req_id = cursor.lastrowid
                if req[27] == "Label Created":
                    cursor.execute("""
                        INSERT INTO request_labels (request_id, label_file, tracking_number, carrier, estimated_delivery_date, actual_shipping_cost)
                        VALUES (?, '/uploads/labels/label_0002.pdf', '1Z999AA10123456784', 'FedEx', '2026-07-16', 32.40)
                    """, (req_id,))
                    
    conn.commit()
    print("Database seeding for shipping completed.")
    conn.close()

if __name__ == "__main__":
    main()
