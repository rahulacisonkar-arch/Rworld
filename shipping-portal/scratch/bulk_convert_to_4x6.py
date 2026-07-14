import urllib.request
import json
import ssl
import os
import mysql.connector

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

api_key = "prod_IGyvnafAWObx9FPZ8aCbSCwKr/OBoqnSo+qkoH19uIo="
headers = {
    'Authorization': f'Bearer {api_key}',
    'User-Agent': 'Mozilla/5.0',
    'Accept': 'application/json'
}

def get_db_connection():
    return mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="artee_shipping"
    )

def fetch_local_labels():
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id, request_id, label_file, tracking_number FROM request_labels")
    rows = cursor.fetchall()
    cursor.close()
    conn.close()
    return rows

def fetch_easyship_shipments():
    url = 'https://public-api.easyship.com/2024-09/shipments?limit=100'
    req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, context=ctx) as response:
            data = json.loads(response.read().decode())
            return data.get('shipments', [])
    except Exception as e:
        print(f"Error fetching shipments from Easyship: {e}")
        return []

def download_label(url, dest_path):
    req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, context=ctx) as response:
            content = response.read()
            with open(dest_path, 'wb') as f:
                f.write(content)
            print(f"-> Downloaded 4x6 PDF ({len(content)} bytes) to {dest_path}")
            return True
    except Exception as e:
        print(f"-> Error downloading PDF: {e}")
        return False

def main():
    local_labels = fetch_local_labels()
    if not local_labels:
        print("No local labels found in database.")
        return
        
    print(f"Found {len(local_labels)} local labels in database.")
    
    # Map tracking number -> local label row
    local_map = {}
    for l in local_labels:
        if l['tracking_number']:
            local_map[l['tracking_number'].strip()] = l

    print("Fetching recent shipments from Easyship...")
    shipments = fetch_easyship_shipments()
    print(f"Retrieved {len(shipments)} shipments from Easyship API.")
    
    upload_dir = "secure_uploads/"
    
    matches_found = 0
    for s in shipments:
        easyship_id = s.get('easyship_shipment_id')
        
        # Extract tracking number from trackings array
        tracking_no = None
        for t in s.get('trackings', []):
            if t.get('tracking_number'):
                tracking_no = t['tracking_number'].strip()
                break
        
        if not tracking_no:
            # Fallback to label object
            lbl_obj = s.get('label')
            if lbl_obj and lbl_obj.get('tracking_number'):
                tracking_no = lbl_obj['tracking_number'].strip()
                
        if not tracking_no:
            continue
            
        if tracking_no in local_map:
            local_row = local_map[tracking_no]
            dest_file = os.path.join(upload_dir, local_row['label_file'])
            
            print(f"Match found! Tracking: {tracking_no} | Request ID: {local_row['request_id']} | File: {local_row['label_file']}")
            
            # Find label URL
            label_url = None
            for doc in s.get('shipping_documents', []):
                if doc.get('category') == 'label':
                    label_url = doc.get('url')
                    break
            
            if not label_url:
                lbl_obj = s.get('label')
                if lbl_obj:
                    label_url = lbl_obj.get('label_raw_url') or lbl_obj.get('label_url')
                    
            if label_url:
                # Force 4x6 page size
                if 'page_size=' in label_url:
                    import re
                    label_url = re.sub(r'page_size=[a-zA-Z0-9_]+', 'page_size=4x6', label_url)
                else:
                    separator = '&' if '?' in label_url else '?'
                    label_url += f"{separator}page_size=4x6"
                
                print(f"-> Downloading from: {label_url}")
                download_label(label_url, dest_file)
                matches_found += 1
            else:
                print("-> No label URL found for this shipment.")
                
    print(f"Done. Re-downloaded {matches_found} matching labels.")

if __name__ == "__main__":
    main()
