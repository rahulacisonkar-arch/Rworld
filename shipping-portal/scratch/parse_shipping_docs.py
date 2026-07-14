import json

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
    schemas = d.get('components', {}).get('schemas', {})
    
    # Print the properties of ShippingDocument or similar schema if found
    for sname in sorted(schemas.keys()):
        if 'document' in sname.lower() or 'label' in sname.lower():
            print(f"=== Schema: {sname} ===")
            props = schemas[sname].get('properties', {})
            for k, v in sorted(props.items()):
                print(f"  - {k}: {v.get('type')}")
                
except Exception as e:
    print(f"Error: {e}")
