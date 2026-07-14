import os
import sys
import pandas as pd
import mysql.connector

def main():
    db_config = {
        'host': '127.0.0.1',
        'port': 3306,
        'user': 'root',
        'password': '',
        'database': 'rworld_erp'
    }
    
    print("Connecting to database...")
    conn = mysql.connector.connect(**db_config)
    cursor = conn.cursor()
    
    # 1. Load Exel.xlsx
    excel_path = r"C:\Users\Artee Admin\Desktop\Exel.xlsx"
    print("Reading Excel file...")
    df = pd.read_excel(excel_path)
    print(f"Loaded {len(df)} rows.")
    
    # Fill NaN values
    df['Store Names'] = df['Store Names'].fillna('Head Office').astype(str).str.strip()
    df['Stock No'] = df['Stock No'].fillna('').astype(str).str.strip()
    df['Item Description'] = df['Item Description'].fillna('').astype(str).str.strip()
    df['Batch No.'] = df['Batch No.'].fillna('NO BATCH').astype(str).str.strip()
    df['Category'] = df['Category'].fillna('').astype(str).str.strip()
    df['Cost Price'] = df['Cost Price'].fillna(0.0).astype(float)
    df['Closing Bal.Qty'] = df['Closing Bal.Qty'].fillna(0.0).astype(float)
    df['Closing Bal.Val'] = df['Closing Bal.Val'].fillna(0.0).astype(float)
    
    # Filter out empty stock number or description
    df = df[(df['Stock No'] != '') & (df['Item Description'] != '')]
    print(f"Filtered to {len(df)} valid inventory rows.")
    
    # Cache dictionaries
    branch_cache = {} # name.lower() -> id
    category_cache = {} # name.lower() -> id
    item_cache = {} # stock_no.lower() -> id
    
    # Load existing branches
    cursor.execute("SELECT id, name FROM branches WHERE company_id = 1")
    for bid, name in cursor.fetchall():
        branch_cache[name.lower()] = bid
        
    # Load existing categories
    cursor.execute("SELECT id, name FROM categories WHERE company_id = 1")
    for cid, name in cursor.fetchall():
        category_cache[name.lower()] = cid
        
    # Load existing items
    cursor.execute("SELECT id, stock_no FROM items WHERE company_id = 1")
    for itid, stock_no in cursor.fetchall():
        item_cache[stock_no.lower()] = itid
        
    batches_to_insert = {} # (item_id, branch_id, batch_no) -> [cost_price, mrp, qty_in]
    ledger_to_insert = []
    
    print("Processing branches, categories and items...")
    
    # Insert missing branches
    unique_stores = df['Store Names'].unique()
    used_codes = set()
    cursor.execute("SELECT code FROM branches WHERE company_id = 1")
    for (existing_code,) in cursor.fetchall():
        used_codes.add(existing_code.upper())

    for store in unique_stores:
        s_lower = store.lower()
        if s_lower not in branch_cache:
            clean_code = ''.join(c for c in store if c.isalnum()).upper()[:10]
            if not clean_code:
                clean_code = "BR"
            base_code = clean_code[:7]
            counter = 1
            while clean_code in used_codes:
                clean_code = f"{base_code}{counter}"
                counter += 1
            used_codes.add(clean_code)

            cursor.execute(
                "INSERT INTO branches (company_id, code, name, is_active, is_warehouse, is_head_office) VALUES (1, %s, %s, 1, 0, 0)",
                (clean_code, store)
            )
            branch_cache[s_lower] = cursor.lastrowid
            
    # Insert missing categories
    unique_cats = df['Category'].unique()
    for cat in unique_cats:
        c_lower = cat.lower()
        if cat and c_lower not in category_cache:
            cursor.execute(
                "INSERT INTO categories (company_id, code, name, level_no, sort_order, is_active) VALUES (1, %s, %s, 1, 0, 1)",
                (cat[:30], cat)
            )
            category_cache[c_lower] = cursor.lastrowid
            
    # Drop duplicate items so we only insert each stock_no once
    unique_items_df = df.drop_duplicates(subset=['Stock No'])
    print(f"Found {len(unique_items_df)} unique items to insert/update...")
    
    items_data = []
    for idx, row in unique_items_df.iterrows():
        stock_no = row['Stock No']
        s_lower = stock_no.lower()
        if s_lower not in item_cache:
            desc = row['Item Description']
            cost = float(row['Cost Price'])
            cat_name = row['Category']
            cat_id = category_cache[cat_name.lower()] if cat_name else None
            has_batch = 1 if (row['Batch No.'] and row['Batch No.'].upper() != 'NO BATCH') else 0
            
            items_data.append((1, stock_no, desc, cost, 0.0, 0.0, 0.0, 0.0, 0.0, cat_id, has_batch))
            
    if items_data:
        print(f"Bulk inserting {len(items_data)} items...")
        chunk_size = 5000
        for i in range(0, len(items_data), chunk_size):
            chunk = items_data[i:i+chunk_size]
            cursor.executemany(
                "INSERT INTO items (company_id, stock_no, description, cost_price, price1, price2, price3, price4, price5, cat1_id, has_batch, maintain_inventory, is_active) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 1, 1)",
                chunk
            )
            
    # Refresh item cache
    cursor.execute("SELECT id, stock_no FROM items WHERE company_id = 1")
    for itid, stock_no in cursor.fetchall():
        item_cache[stock_no.lower()] = itid
        
    print("Preparing batch master and stock ledger records...")
    
    # Running balances per item + branch
    running_balance = {}
    
    for idx, row in df.iterrows():
        store = row['Store Names']
        stock_no = row['Stock No']
        batch_no = row['Batch No.']
        qty = float(row['Closing Bal.Qty'])
        cost = float(row['Cost Price'])
        
        branch_id = branch_cache[store.lower()]
        item_id = item_cache[stock_no.lower()]
        
        has_batch = 1 if (batch_no and batch_no.upper() != 'NO BATCH') else 0
        
        # Accumulate batch qty
        if has_batch:
            key = (item_id, branch_id, batch_no)
            if key not in batches_to_insert:
                batches_to_insert[key] = [cost, 0.0, 0.0]
            batches_to_insert[key][2] += qty
            
        # Update running balance for ledger
        if qty > 0:
            bal_key = (item_id, branch_id)
            curr_bal = running_balance.get(bal_key, 0.0)
            new_bal = curr_bal + qty
            running_balance[bal_key] = new_bal
            
            ledger_to_insert.append((
                1, branch_id, item_id, 'BULK_UPLOAD', 'BULK', qty, cost, qty * cost, new_bal
            ))
            
    # Insert batches
    if batches_to_insert:
        batches_data = []
        for (item_id, branch_id, batch_no), (cost, mrp, qty_in) in batches_to_insert.items():
            batches_data.append((item_id, branch_id, batch_no, cost, mrp, qty_in))
            
        print(f"Inserting {len(batches_data)} batch master records...")
        chunk_size = 5000
        for i in range(0, len(batches_data), chunk_size):
            chunk = batches_data[i:i+chunk_size]
            cursor.executemany(
                "INSERT INTO batch_master (item_id, branch_id, batch_no, cost_price, mrp, qty_in, qty_out, is_active) VALUES (%s, %s, %s, %s, %s, %s, 0.0000, 1)",
                chunk
            )
            
    # Insert ledger records
    if ledger_to_insert:
        print(f"Inserting {len(ledger_to_insert)} stock ledger records...")
        chunk_size = 5000
        for i in range(0, len(ledger_to_insert), chunk_size):
            chunk = ledger_to_insert[i:i+chunk_size]
            cursor.executemany(
                "INSERT INTO stock_ledger (company_id, branch_id, item_id, txn_date, txn_type, doc_no, qty_in, qty_out, rate, value, balance_qty) VALUES (%s, %s, %s, CURDATE(), %s, %s, %s, 0.0000, %s, %s, %s)",
                chunk
            )
            
    # Log the bulk import activity
    cursor.execute(
        "INSERT INTO import_logs (company_id, branch_id, filename, import_type, total_rows, success_rows, error_rows, error_details, status) VALUES (1, 1, 'Exel.xlsx', 'ITEMS', %s, %s, 0, '[]', 'completed')",
        (len(df), len(df))
    )
    
    conn.commit()
    print("Import completed successfully!")
    cursor.close()
    conn.close()

if __name__ == '__main__':
    main()
