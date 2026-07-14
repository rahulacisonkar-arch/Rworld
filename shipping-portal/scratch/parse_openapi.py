import json
import re

doc_path = r"C:\Users\Artee Admin\.gemini\antigravity-ide\brain\5516a81b-ca8c-4a98-9f07-8ed27e991e7c\.system_generated\steps\1616\content.md"

with open(doc_path, 'r', encoding='utf-8') as f:
    text = f.read()

start_idx = text.find('{\n  "openapi":')
if start_idx == -1:
    start_idx = text.find('{\r\n  "openapi":')
    
end_idx = text.find('```', start_idx)
openapi_json = text[start_idx:end_idx].strip()

try:
    d = json.loads(openapi_json)
    
    # Print the properties of Shipment
    schemas = d.get('components', {}).get('schemas', {})
    shipment_schema = schemas.get('Shipment', {})
    properties = shipment_schema.get('properties', {})
    
    print("=== Shipment properties ===")
    for k, v in sorted(properties.items()):
        print(f"- {k}: {v.get('type')} {v.get('$ref') or ''}")
        
except Exception as e:
    print(f"Error parsing JSON: {e}")
