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
    print("Creating tables...")
    
    cursor.execute("""
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
    """)

    cursor.execute("""
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
        FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS attendance_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        store_id INTEGER NOT NULL,
        login_time TEXT NOT NULL,
        logout_time TEXT,
        date TEXT NOT NULL,
        status TEXT DEFAULT 'Checked In',
        log_type TEXT DEFAULT 'Regular',
        auto_closed INTEGER DEFAULT 0,
        manager_approved INTEGER DEFAULT 1,
        is_late INTEGER DEFAULT 0,
        is_early_departure INTEGER DEFAULT 0,
        calculated_hours REAL DEFAULT 0.0,
        calculated_overtime REAL DEFAULT 0.0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE,
        FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS attendance_breaks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        log_id INTEGER NOT NULL,
        break_start TEXT NOT NULL,
        break_end TEXT,
        FOREIGN KEY(log_id) REFERENCES attendance_logs(id) ON DELETE CASCADE
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS employee_timesheets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        week_ending TEXT NOT NULL,
        regular_hours REAL DEFAULT 0.0,
        overtime_hours REAL DEFAULT 0.0,
        total_hours REAL DEFAULT 0.0,
        status TEXT DEFAULT 'Draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS payroll_summary (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        period_start TEXT NOT NULL,
        period_end TEXT NOT NULL,
        total_hours REAL DEFAULT 0.0,
        total_pay REAL DEFAULT 0.0,
        calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS ocr_audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        employee_id INTEGER,
        file_name TEXT NOT NULL,
        raw_ocr_text TEXT,
        extracted_data TEXT,
        modified_data TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS utility_connections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        store_id INTEGER NOT NULL,
        utility_type TEXT NOT NULL,
        provider_name TEXT NOT NULL,
        account_number TEXT NOT NULL,
        notes TEXT,
        status TEXT DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE,
        UNIQUE(store_id, utility_type)
    );
    """)

    cursor.execute("""
    CREATE TABLE IF NOT EXISTS bills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        connection_id INTEGER NOT NULL,
        store_id INTEGER NOT NULL,
        statement_date TEXT NOT NULL,
        due_date TEXT NOT NULL,
        amount REAL NOT NULL,
        bill_file_path TEXT NOT NULL,
        status TEXT DEFAULT 'Pending',
        paid_at TEXT,
        paid_by INTEGER,
        transaction_ref TEXT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(connection_id) REFERENCES utility_connections(id) ON DELETE CASCADE,
        FOREIGN KEY(store_id) REFERENCES stores(id) ON DELETE CASCADE
    );
    """)

    print("Tables created successfully.")
    
    # 2. Seed Stores
    print("Seeding stores...")
    stores = [
        ('78', 'ARTEE FABRICS & HOME', '600 HIGH ST', 'PORTSMOUTH', 'VA', '23704', '757-966-1808'),
        ('82', "PRINTER'S ALLEY", '5910-111 DURALEIGH ROAD', 'RALEIGH', 'NC', '27612', '919-781-1777'),
        ('63', 'ARTEE FABRICS & HOME', '7016 B MARKET STREET', 'WILMINGTON', 'NC', '28411', '910-686-2950'),
        ('64', 'ARTEE FABRICS & HOME', '1776 LASKIN ROAD SUITE 106', 'VIRGINIA BEACH', 'VA', '23454', '757-963-7820'),
        ('73', 'GOOD GOODS', '859 POST ROAD', 'DARIEN', 'CT', '06820', '203-655-8100'),
        ('71', 'RAGS & RICHES', '3762 SHELBURNE ROAD', 'SHELBURNE', 'VT', '05482', '802-862-3288'),
        ('62', 'ARTEE FABRICS & HOME', '8045 WEST BROAD STREET', 'HENRICO', 'VA', '23294', '804-285-9591'),
        ('70', 'ARTEE FABRICS & HOME', '9543 FIELDS ERTEL ROAD', 'LOVELAND', 'OH', '45140', '513-683-5400'),
        ('67', 'ARTEE FABRICS & HOME', '1801 AIRLINE DRIVE SUITE A', 'METAIRIE', 'LA', '70001', '504-302-2160'),
        ('02', 'ARTEE FABRICS AND HOME', '7 DUNNELL LANE EAST', 'PAWTUCKET', 'RI', '02860', '978-212-2683'),
        ('03', "PRINTER'S ALLEY", '736 S MAIN STREET', 'BURLINGTON', 'NC', '27215', '336-270-4812')
    ]
    
    for s in stores:
        try:
            cursor.execute("""
                INSERT INTO stores (store_code, store_name, address, city, state, zip, phone)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """, s)
        except sqlite3.IntegrityError:
            pass # Already exists
            
    # 3. Seed Employees
    print("Seeding employees...")
    cursor.execute("SELECT id FROM stores ORDER BY id")
    store_ids = [row[0] for row in cursor.fetchall()]
    
    employees = [
        (store_ids[0], 'John Doe', 'john.doe@artee.com', '757-555-0199', 'Store Manager', 25.00, 'Active', 'Full-time', '2022-01-15', 'Grade A'),
        (store_ids[0], 'Jane Smith', 'jane.smith@artee.com', '757-555-0188', 'Sales Associate', 15.00, 'Active', 'Full-time', '2023-04-10', 'Grade B'),
        (store_ids[0], 'Bob Johnson', 'bob.johnson@artee.com', '757-555-0177', 'Warehouse Clerk', 14.50, 'Active', 'Part-time', '2023-11-01', 'Grade C'),
        (store_ids[1], 'Alice Williams', 'alice.williams@artee.com', '919-555-0166', 'Store Manager', 24.50, 'Active', 'Full-time', '2021-08-01', 'Grade A'),
        (store_ids[1], 'Charlie Brown', 'charlie.brown@artee.com', '919-555-0155', 'Cashier', 14.00, 'Active', 'Part-time', '2024-02-15', 'Grade C'),
        (store_ids[2], 'David Davis', 'david.davis@artee.com', '910-555-0144', 'Store Manager', 26.00, 'Active', 'Full-time', '2020-05-10', 'Grade A')
    ]
    
    for emp in employees:
        cursor.execute("SELECT id FROM employees WHERE name = ?", (emp[1],))
        if not cursor.fetchone():
            cursor.execute("""
                INSERT INTO employees (store_id, name, email, phone, designation, hourly_rate, status, employment_type, hire_date, salary_grade)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, emp)

    # 4. Seed Utility Connections & Bills (YoY Gas and Electricity Spend)
    print("Seeding utility connections and bills...")
    cursor.execute("SELECT id, store_code FROM stores")
    store_map = {row[1]: row[0] for row in cursor.fetchall()}
    
    connections = [
        ('78', 'Electricity', 'Dominion Energy', 'E-80434-VA'),
        ('78', 'Gas', 'Virginia Natural Gas', 'G-12493-VA'),
        ('82', 'Electricity', 'Duke Energy', 'E-91043-NC'),
        ('82', 'Gas', 'Piedmont Natural Gas', 'G-82194-NC')
    ]
    
    conn_map = {}
    for scode, utype, provider, acct in connections:
        sid = store_map.get(scode)
        if sid:
            try:
                cursor.execute("""
                    INSERT INTO utility_connections (store_id, utility_type, provider_name, account_number, status)
                    VALUES (?, ?, ?, ?, 'Active')
                """, (sid, utype, provider, acct))
                conn_map[(sid, utype)] = cursor.lastrowid
            except sqlite3.IntegrityError:
                cursor.execute("SELECT id FROM utility_connections WHERE store_id = ? AND utility_type = ?", (sid, utype))
                conn_map[(sid, utype)] = cursor.fetchone()[0]

    # YoY Bills Data Seeding (2025 and 2026 Monthly bills)
    months_2025 = [
        ('01', 320.50, 180.40), ('02', 310.20, 175.60), ('03', 290.40, 150.20),
        ('04', 240.10, 110.30), ('05', 280.90, 80.50),  ('06', 390.80, 50.10),
        ('07', 440.30, 45.30),  ('08', 420.70, 48.20),  ('09', 350.20, 60.80),
        ('10', 270.40, 95.40),  ('11', 290.10, 140.20), ('12', 340.60, 190.50)
    ]
    months_2026 = [
        ('01', 340.20, 195.30), ('02', 325.80, 188.40), ('03', 305.40, 160.20),
        ('04', 255.30, 120.50), ('05', 295.10, 88.30),  ('06', 410.50, 55.40),
        ('07', 465.20, 48.00) # Only up to July 2026
    ]
    
    # Store 78 Database ID
    sid_78 = store_map.get('78')
    cid_elec_78 = conn_map.get((sid_78, 'Electricity'))
    cid_gas_78 = conn_map.get((sid_78, 'Gas'))
    
    # Delete existing seeded bills for Store 78 to prevent duplicates
    if cid_elec_78 and cid_gas_78:
        cursor.execute("DELETE FROM bills WHERE store_id = ?", (sid_78,))
        
        # Seed 2025
        for m, elec_val, gas_val in months_2025:
            # Electricity
            cursor.execute("""
                INSERT INTO bills (connection_id, store_id, statement_date, due_date, amount, bill_file_path, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Paid')
            """, (cid_elec_78, sid_78, f"2025-{m}-01", f"2025-{m}-20", elec_val, f"/uploads/bills/elec_78_2025{m}.pdf"))
            # Gas
            cursor.execute("""
                INSERT INTO bills (connection_id, store_id, statement_date, due_date, amount, bill_file_path, status)
                VALUES (?, ?, ?, ?, ?, ?, 'Paid')
            """, (cid_gas_78, sid_78, f"2025-{m}-01", f"2025-{m}-20", gas_val, f"/uploads/bills/gas_78_2025{m}.pdf"))
            
        # Seed 2026
        for m, elec_val, gas_val in months_2026:
            status = 'Paid' if int(m) < 7 else 'Pending'
            # Electricity
            cursor.execute("""
                INSERT INTO bills (connection_id, store_id, statement_date, due_date, amount, bill_file_path, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """, (cid_elec_78, sid_78, f"2026-{m}-01", f"2026-{m}-20", elec_val, f"/uploads/bills/elec_78_2026{m}.pdf", status))
            # Gas
            cursor.execute("""
                INSERT INTO bills (connection_id, store_id, statement_date, due_date, amount, bill_file_path, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """, (cid_gas_78, sid_78, f"2026-{m}-01", f"2026-{m}-20", gas_val, f"/uploads/bills/gas_78_2026{m}.pdf", status))
            
    conn.commit()
    print("Database seeding completed.")
    conn.close()

if __name__ == "__main__":
    main()
