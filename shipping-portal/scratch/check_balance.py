import urllib.request
import json
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

api_key = "prod_IGyvnafAWObx9FPZ8aCbSCwKr/OBoqnSo+qkoH19uIo="
headers = {
    'Authorization': f'Bearer {api_key}',
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'Accept': 'application/json'
}

def check_credit():
    req = urllib.request.Request(
        'https://public-api.easyship.com/2024-09/account/credit',
        headers=headers
    )
    try:
        with urllib.request.urlopen(req, context=ctx) as response:
            data = json.loads(response.read().decode())
            print("=== CREDIT DETAILS ===")
            print(json.dumps(data, indent=2))
    except Exception as e:
        print(f"Error checking credit: {e}")

def check_shipments():
    req = urllib.request.Request(
        'https://public-api.easyship.com/2024-09/shipments?limit=30',
        headers=headers
    )
    try:
        with urllib.request.urlopen(req, context=ctx) as response:
            data = json.loads(response.read().decode())
            print("\n=== RECENT SHIPMENTS ===")
            for s in data.get('shipments', []):
                shipment_id = s.get('easyship_shipment_id')
                label_state = s.get('label_state')
                delivery_state = s.get('delivery_state')
                created_at = s.get('created_at')
                
                # Check for label info
                label = s.get('label')
                label_info = "No Label"
                if label:
                    label_info = f"Tracking: {label.get('tracking_number')}, Cost: ${label.get('total_charge')}"
                
                print(f"ID: {shipment_id} | Label State: {label_state} | Delivery: {delivery_state} | Label Info: {label_info} | Created: {created_at}")
    except Exception as e:
        print(f"Error checking shipments: {e}")

if __name__ == "__main__":
    check_credit()
    check_shipments()
