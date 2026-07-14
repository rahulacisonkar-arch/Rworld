import sqlite3
import os

def main():
    # Source and destination database paths
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    workspace_root = os.path.dirname(base_dir)
    
    src_db_path = os.path.join(workspace_root, "fabricmill-scraper", "scraper.db")
    dest_db_path = os.path.join(base_dir, "database", "rworld.db")
    
    print(f"Source DB: {src_db_path}")
    print(f"Destination DB: {dest_db_path}")
    
    if not os.path.exists(src_db_path):
        print("Error: Source database does not exist!")
        return
        
    if not os.path.exists(dest_db_path):
        print("Error: Destination database does not exist!")
        return
        
    src_conn = sqlite3.connect(src_db_path)
    src_cursor = src_conn.cursor()
    
    dest_conn = sqlite3.connect(dest_db_path)
    dest_cursor = dest_conn.cursor()
    
    # Verify the table schema of scraper_products
    dest_cursor.execute("PRAGMA table_info(scraper_products)")
    dest_cols = [row[1] for row in dest_cursor.fetchall()]
    print(f"Destination table columns: {dest_cols}")
    
    # Read from source
    src_cursor.execute("""
        SELECT url, name, sku, manufacturer, description, width, type_of_fabric, fiber_content, retail_price, scraped_at
        FROM products
    """)
    rows = src_cursor.fetchall()
    print(f"Read {len(rows)} products from source database.")
    
    inserted_count = 0
    for row in rows:
        url, name, sku, manufacturer, description, width, type_of_fabric, fiber_content, retail_price, scraped_at = row
        try:
            dest_cursor.execute("""
                INSERT OR REPLACE INTO scraper_products 
                (url, name, sku, manufacturer, description, width, type_of_fabric, fiber_content, retail_price, scraped_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """, (url, name, sku, manufacturer, description, width, type_of_fabric, fiber_content, retail_price, scraped_at))
            inserted_count += 1
        except Exception as e:
            print(f"Failed to insert {url}: {e}")
            
    dest_conn.commit()
    
    # Verify final count
    dest_cursor.execute("SELECT COUNT(*) FROM scraper_products")
    final_count = dest_cursor.fetchone()[0]
    
    print(f"Successfully processed {inserted_count} / {len(rows)} products.")
    print(f"Total products in destination database: {final_count}")
    
    src_conn.close()
    dest_conn.close()

if __name__ == "__main__":
    main()
