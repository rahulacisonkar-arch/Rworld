import urllib.request
import ssl

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

api_key = "prod_IGyvnafAWObx9FPZ8aCbSCwKr/OBoqnSo+qkoH19uIo="
headers = {
    'Authorization': f'Bearer {api_key}',
    'User-Agent': 'Mozilla/5.0'
}

def download_and_save(url, filename):
    req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, context=ctx) as response:
            content = response.read()
            with open(filename, 'wb') as f:
                f.write(content)
            print(f"Successfully downloaded {filename} ({len(content)} bytes)")
    except Exception as e:
        print(f"Error downloading {filename}: {e}")

if __name__ == "__main__":
    # URL from ESUS339050060 response
    url_a4 = "https://api.easyship.com/shipment/v1/shipments/a6990bae-9322-4de6-977e-2b6b093a041d/shipping_documents/label?page_size=a4"
    url_4x6 = "https://api.easyship.com/shipment/v1/shipments/a6990bae-9322-4de6-977e-2b6b093a041d/shipping_documents/label?page_size=4x6"
    
    download_and_save(url_a4, "scratch/label_a4.pdf")
    download_and_save(url_4x6, "scratch/label_4x6.pdf")
