import os
import sys
import json
from fastapi.testclient import TestClient

# Ensure backend directory is in python path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from backend.main import app, get_db, SessionLocal
from backend.models import init_db, DocumentHistory, DocumentMapping, AuditLog

client = TestClient(app)

def safe_print(msg):
    try:
        print(msg)
    except UnicodeEncodeError:
        print(str(msg).encode('ascii', errors='replace').decode('ascii'))

def run_tests():
    safe_print("=== Auditing Mworld Intellegence Endpoints ===")
    
    # 1. Test get mappings
    safe_print("\n[Test 1] GET /api/document/mappings")
    res = client.get("/api/document/mappings")
    assert res.status_code == 200
    safe_print(f"Success: {res.json()}")

    # 2. Test create mapping
    safe_print("\n[Test 2] POST /api/document/mappings")
    payload = {
        "target_system": "quickbill",
        "field_key": "supplier_inv_no",
        "selector": "input[name=supplier_inv_no]",
        "label": "Supplier Invoice Number Field"
    }
    res = client.post("/api/document/mappings", json=payload)
    assert res.status_code == 200
    safe_print(f"Success: {res.json()}")

    # 3. Test document upload
    safe_print("\n[Test 3] POST /api/document/upload")
    # Write a dummy txt file to simulate upload
    dummy_file_path = "test_invoice.txt"
    with open(dummy_file_path, "w") as f:
        f.write("Invoice INV-2026-SOLAR Canadian Solar Ltd Date: 2026-06-29 Net Amount: 2616.00")
        
    with open(dummy_file_path, "rb") as f:
        res = client.post("/api/document/upload", files={"file": (dummy_file_path, f, "text/plain")})
        
    os.remove(dummy_file_path)
    assert res.status_code == 200
    upload_data = res.json()
    doc_id = upload_data["document_id"]
    safe_print(f"Success (Doc ID: {doc_id}): {upload_data}")

    # 4. Test document correction
    safe_print("\n[Test 4] POST /api/document/correct/{document_id}")
    payload_correct = {
        "fields": {
            "vendor_name": "Canadian Solar Overridden",
            "invoice_number": "INV-2026-OVERRIDE"
        }
    }
    res = client.post(f"/api/document/correct/{doc_id}", json=payload_correct)
    assert res.status_code == 200
    safe_print(f"Success: {res.json()}")

    # 5. Test Excel automation
    safe_print("\n[Test 5] POST /api/document/automate (Excel)")
    payload_excel = {
        "document_id": doc_id,
        "target_system": "excel"
    }
    res = client.post("/api/document/automate", json=payload_excel)
    assert res.status_code == 200
    safe_print(f"Success: {res.json()}")

    # 6. Test ERP automation
    safe_print("\n[Test 6] POST /api/document/automate (ERP)")
    payload_erp = {
        "document_id": doc_id,
        "target_system": "erp"
    }
    res = client.post("/api/document/automate", json=payload_erp)
    assert res.status_code == 200
    safe_print(f"Success: {res.json()}")

    # 7. Test Govt Schemes automation
    safe_print("\n[Test 7] POST /api/document/automate (Govt Schemes)")
    payload_schemes = {
        "document_id": doc_id,
        "target_system": "govt_schemes"
    }
    res = client.post("/api/document/automate", json=payload_schemes)
    assert res.status_code == 200
    safe_print(f"Success: {res.json()}")

    # 7.5 Test NVIDIA AI-Q Blueprint Agent execution
    safe_print("\n[Test 7.5] POST /api/document/automate (NVIDIA AI-Q)")
    payload_aiq = {
        "document_id": doc_id,
        "target_system": "nvidia_aiq"
    }
    res = client.post("/api/document/automate", json=payload_aiq)
    assert res.status_code == 200
    safe_print(f"Success: {res.json()}")

    # 8. Test Batch automation
    safe_print("\n[Test 8] POST /api/document/automate/batch")
    payload_batch = {
        "document_ids": [doc_id],
        "target_system": "excel"
    }
    res = client.post("/api/document/automate/batch", json=payload_batch)
    assert res.status_code == 200
    safe_print(f"Success: {res.json()}")

    # 9. Test Crawling (Crawl4AI + ChromaDB Vector Ingestion)
    safe_print("\n[Test 9] POST /api/document/crawl")
    payload_crawl = {
        "url": "https://quotes.toscrape.com/"
    }
    res = client.post("/api/document/crawl", json=payload_crawl)
    assert res.status_code == 200
    safe_print(f"Success: {res.json()}")

    # 10. Test history, logs, stats
    safe_print("\n[Test 10] GET history, logs, stats")
    res = client.get("/api/document/history")
    assert res.status_code == 200
    safe_print(f"History count: {len(res.json())}")
    
    res = client.get("/api/document/logs")
    assert res.status_code == 200
    safe_print(f"Logs count: {len(res.json())}")
    
    res = client.get("/api/document/confidence")
    assert res.status_code == 200
    safe_print(f"Stats: {res.json()}")

    safe_print("\n=== All Endpoints Mapped & Audited successfully! ===")

if __name__ == "__main__":
    run_tests()
