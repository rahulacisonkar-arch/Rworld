import sqlite3
import os
from datetime import datetime

def main():
    db_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "rworld.db")
    print(f"Connecting to database: {db_path}")
    
    if not os.path.exists(db_path):
        print("Database file does not exist!")
        return
        
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    
    # 1. Create Tables
    print("Creating ERP extension tables...")
    
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS erp_shifts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cashier_name TEXT NOT NULL,
        open_time TEXT NOT NULL,
        close_time TEXT,
        open_balance REAL DEFAULT 0.0,
        close_balance REAL DEFAULT 0.0,
        status TEXT DEFAULT 'open' -- 'open', 'closed'
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS erp_sales_returns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        return_date TEXT NOT NULL,
        amount REAL NOT NULL,
        notes TEXT,
        FOREIGN KEY(order_id) REFERENCES erp_orders(id)
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS erp_purchase_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        po_number TEXT UNIQUE NOT NULL,
        supplier_name TEXT NOT NULL,
        item_sku TEXT NOT NULL,
        quantity INTEGER NOT NULL,
        unit_cost REAL NOT NULL,
        status TEXT DEFAULT 'pending', -- 'pending', 'received'
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS erp_grn (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        po_id INTEGER NOT NULL,
        received_date TEXT NOT NULL,
        received_quantity INTEGER NOT NULL,
        notes TEXT,
        FOREIGN KEY(po_id) REFERENCES erp_purchase_orders(id)
    );
    """)

    print("ERP extension tables created successfully.")
    
    # 2. Seed default ERP data if empty
    print("Seeding default ERP data...")
    
    # Verify inventory is seeded
    cursor.execute("SELECT COUNT(*) FROM erp_inventory")
    if cursor.fetchone()[0] == 0:
        inventory_items = [
            ('LINEN-WHITE-100', 'White Drapery Linen Fabric', 'Linen', 250, 18.50, 'A-01', 'Soft natural white draping linen.'),
            ('VELVET-NAVY-200', 'Navy Blue upholstery Velvet', 'Velvet', 120, 24.00, 'B-04', 'Heavy pile upholstery grade velvet.'),
            ('COTTON-BEIGE-300', 'Beige Cotton Duck Canvas', 'Cotton', 400, 12.00, 'C-12', 'Thick organic beige duck canvas.')
        ]
        cursor.executemany("""
            INSERT INTO erp_inventory (sku, name, category, quantity, price, location, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        """, inventory_items)
        
    # Verify customers is seeded
    cursor.execute("SELECT COUNT(*) FROM erp_customers")
    if cursor.fetchone()[0] == 0:
        customers = [
            ('Alice Smith', 'alice.s@example.com', '555-0101', '123 Pine St, Darien, CT'),
            ('Bob Jones', 'bob.j@example.com', '555-0102', '456 Oak Rd, Portsmouth, VA')
        ]
        cursor.executemany("""
            INSERT INTO erp_customers (name, email, phone, address)
            VALUES (?, ?, ?, ?)
        """, customers)

    # Verify orders is seeded
    cursor.execute("SELECT COUNT(*) FROM erp_orders")
    if cursor.fetchone()[0] == 0:
        orders = [
            (1, 148.00, 'completed'),
            (2, 74.00, 'completed')
        ]
        cursor.executemany("""
            INSERT INTO erp_orders (customer_id, total_amount, status)
            VALUES (?, ?, ?)
        """, orders)

    # Seed shift register
    cursor.execute("SELECT COUNT(*) FROM erp_shifts")
    if cursor.fetchone()[0] == 0:
        shifts = [
            ('Jane Cashier', '2026-07-14 09:00:00', '2026-07-14 17:00:00', 150.00, 850.00, 'closed'),
            ('Mark Cashier', datetime.now().strftime('%Y-%m-%d %H:%M:%S'), None, 200.00, 0.00, 'open')
        ]
        cursor.executemany("""
            INSERT INTO erp_shifts (cashier_name, open_time, close_time, open_balance, close_balance, status)
            VALUES (?, ?, ?, ?, ?, ?)
        """, shifts)

    # Seed Purchase orders
    cursor.execute("SELECT COUNT(*) FROM erp_purchase_orders")
    if cursor.fetchone()[0] == 0:
        po_orders = [
            ('PO-2026-0001', 'Loomcraft Textiles', 'LINEN-WHITE-100', 100, 12.00, 'received'),
            ('PO-2026-0002', 'Velvet Mills Inc', 'VELVET-NAVY-200', 50, 16.50, 'pending')
        ]
        cursor.executemany("""
            INSERT INTO erp_purchase_orders (po_number, supplier_name, item_sku, quantity, unit_cost, status)
            VALUES (?, ?, ?, ?, ?, ?)
        """, po_orders)
        
    # Seed GRN
    cursor.execute("SELECT COUNT(*) FROM erp_grn")
    if cursor.fetchone()[0] == 0:
        grns = [
            (1, '2026-07-10 11:30:00', 100, 'Linen shipment received in good condition. Shelved at A-01.')
        ]
        cursor.executemany("""
            INSERT INTO erp_grn (po_id, received_date, received_quantity, notes)
            VALUES (?, ?, ?, ?)
        """, grns)
        
    conn.commit()
    print("Database seeding for ERP extensions completed successfully.")
    conn.close()

if __name__ == "__main__":
    main()
