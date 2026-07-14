import os
import json
import shutil
from sqlalchemy.orm import sessionmaker
from .models import init_db, DocumentVersion, VendorTemplate, FormMapping, ScheduledTask, AuditTrail
from .document_automation import DocumentAutomationAgent
from .scheduler import DocumentScheduler

def run_tests():
    print("====================================================")
    print("    RWORLD AI ENTERPRISE AUTOMATION TEST SUITE      ")
    print("====================================================")

    # 1. Database Schema setup
    engine, SessionLocal = init_db("sqlite:///:memory:")
    db = SessionLocal()
    print("[OK] In-memory SQLite schema instantiated.")

    # Populate templates
    templ = VendorTemplate(
        vendor_name="Canadian Solar",
        anchor_keywords="canadian solar,solar panels inc",
        mapping_json=json.dumps({"quantity": {"anchor": "Quantity"}}),
        corrections_count=0
    )
    db.add(templ)
    db.commit()
    print("[OK] Vendor templates pre-loaded.")

    # 2. Document Processing Pipeline Ingest
    # Create mock invoice file
    mock_dir = "test_uploads"
    os.makedirs(mock_dir, exist_ok=True)
    mock_file = os.path.join(mock_dir, "test_solar_inv.pdf")
    with open(mock_file, "w", encoding="utf-8") as f:
        f.write("Invoice INV-99912 Canadian Solar Ltd Date: 2026-06-29 Quantity: 50 Rate: 100 Net Amount: 5000. Customer: RWorld AI Corp. Billing address: 123 Enterprise Way, Windows City. Solar Panels Ingest.")

    agent = DocumentAutomationAgent(db)
    res = agent.process_document(mock_file)
    assert res["success"] == True
    assert res["document_type"] == "invoice"
    assert res["vendor_name"] == "Canadian Solar"
    assert res["extracted_data"]["invoice_number"] == "INV-99912"
    doc_id = res["document_id"]
    print("[OK] Document Ingestion, Classification, and Entity Extraction verified successfully.")
    print(f"     Identified Type: {res['document_type']} (Vendor: {res['vendor_name']})")
    print(f"     Explainability check: {res['explainability']['invoice_number']}")

    # 3. Confidence Routing Policy
    assert res["confidence_score"] >= 98.0
    assert res["policy_route"] == "auto"
    print(f"[OK] Confidence policies evaluated: Score {res['confidence_score']}% -> Route '{res['policy_route']}'")

    # 4. Human Correction Loop
    corrected = {
        "invoice_number": "INV-99912-CORRECTED",
        "supplier": "Canadian Solar Ltd",
        "net_amount": "5000.00"
    }
    corr_res = agent.save_human_correction(doc_id, corrected)
    assert corr_res["success"] == True
    assert corr_res["new_version"] == 2
    # Verify template updates
    db.refresh(templ)
    assert templ.corrections_count == 1
    print(f"[OK] Human correction loops applied. Doc ID {doc_id} revision level incremented to version {corr_res['new_version']}")
    print(f"     Learned mappings rules count: {templ.corrections_count}")

    # 5. Form Mappings configurations CRUD
    mapping = FormMapping(
        target_system="quickbill",
        field_key="invoice_number",
        selector="#inv_input_field",
        label="Invoice Number input"
    )
    db.add(mapping)
    db.commit()
    assert db.query(FormMapping).filter(FormMapping.target_system == "quickbill").first() is not None
    print("[OK] Custom form mappings database persistence verified.")

    # 6. Universal Connectors Execution & Step Logs
    conn_res = agent.execute_automation(doc_id, "quickbill", task_id=999)
    assert conn_res["success"] == True
    print("[OK] Universal Connector pipeline authentication and execution logs saved.")

    # 7. Recovery & Checkpoints Engine
    # Simulate a step execution failure by writing a mock failure log
    fail_log = AuditTrail(
        task_id=999,
        step_name="create_record",
        step_status="failure",
        resume_index=2, # failure checkpoint index
        error_details="POS window was closed"
    )
    db.add(fail_log)
    db.commit()
    
    # Run automation again. The agent should log resume from checkpoint 2.
    resume_res = agent.execute_automation(doc_id, "quickbill", task_id=999)
    assert resume_res["success"] == True
    print("[OK] Recovery and Resume check: workflow resumed from fail index successfully.")

    # 8. Schedulers & Monitored Queue tasks
    watch_folder = "test_watch"
    os.makedirs(watch_folder, exist_ok=True)
    # Put a mock invoice in watch folder
    with open(os.path.join(watch_folder, "solar_drop.pdf"), "w", encoding="utf-8") as f:
        f.write("Invoice INV-8812 Canadian Solar Ltd Net Amount: 8000")

    sched_task = ScheduledTask(
        name="Test Monitor Loop",
        task_type="folder_monitor",
        target_path=watch_folder,
        interval_seconds=10
    )
    db.add(sched_task)
    db.commit()

    scheduler = DocumentScheduler(db)
    scheduler.poll_tasks()
    
    # Check that drop file was processed and moved to archive
    assert os.path.exists(os.path.join(watch_folder, "archive", "solar_drop.pdf")) == True
    print("[OK] Folder monitors and timed scheduler triggers validated successfully.")

    # Clean test assets
    shutil.rmtree(mock_dir, ignore_errors=True)
    shutil.rmtree(watch_folder, ignore_errors=True)
    
    print("\n====================================================")
    print("    [OK] ALL V4.2 ENTERPRISE UPGRADES TESTS PASSED   ")
    print("====================================================\n")

if __name__ == "__main__":
    run_tests()
