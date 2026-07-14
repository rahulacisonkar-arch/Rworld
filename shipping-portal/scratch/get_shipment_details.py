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

def get_shipment(shipment_id):
    url = f'https://public-api.easyship.com/2024-09/shipments/{shipment_id}'
    req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, context=ctx) as response:
            data = json.loads(response.read().decode())
            print(json.dumps(data, indent=2))
    except Exception as e:
        print(f"Error fetching shipment {shipment_id}: {e}")

if __name__ == "__main__":
    get_shipment("ESUS339050060")
