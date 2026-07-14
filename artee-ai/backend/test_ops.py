import os
import sys
import json

# Ensure backend directory is in python path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from backend.models import init_db, Task, ApprovalItem
from backend.vector_store import NumPyVectorStore
from backend.scraper import scrape_url
from backend.quickbill_engine import QuickBillEngine
from backend.document_engine import DocumentIntelligenceAgent, ExcelAgent, OfficeAutomationAgent
from backend.planner import ExecutivePlanner, route_model
from backend.process_learning import ProcessLearningEngine
from backend.desktop_controller import DesktopController
from backend.business_knowledge import BusinessKnowledgeEngine
from backend.business_rules import BusinessRulesEngine

# Architect Phase 3 additions
from backend.secret_manager import SecretVaultManager
from backend.ai_gateway import AIGateway
from backend.search_engine import SearchEngineRouter
from backend.tool_registry import ToolRegistry
from backend.document_intelligence import DocumentIntelligencePipeline

def test_ops_flow():
    # 1. Initialize SQLite Database (in-memory for test isolation)
    print("=== Testing Database Initialization ===")
    engine, SessionLocal = init_db("sqlite:///:memory:")
    db = SessionLocal()
    print("[OK] SQLite database initialized successfully in-memory.")

    # 2. Test NumPy Vector Store
    print("\n=== Testing Vector Storage & Cosine Search ===")
    store = NumPyVectorStore(db)
    d1 = store.add_document("Roofing shingles bid specifications and guidelines.", filename="shingles.txt")
    results = store.similarity_search("shingles specs", top_k=1)
    assert len(results) > 0, "Vector search returned empty results!"
    print(f"[OK] Vector Search Match: '{results[0]['text']}' (Score: {results[0]['score']:.4f})")

    # 3. Test Office Document Generators
    print("\n=== Testing Office Document Automation & PDF Generation ===")
    office = OfficeAutomationAgent()
    
    # Docx
    docx_ok = office.generate_word_report("test_report.docx", "Operations Audit", {"Scope": "Local ERP and pricing logs check."})
    assert docx_ok and os.path.exists("test_report.docx"), "Word docx generation failed!"
    print("[OK] Word Document created successfully (test_report.docx).")
    os.remove("test_report.docx")

    # Pptx
    pptx_ok = office.generate_powerpoint_presentation("test_pres.pptx", "Operations Analysis", [{"heading": "Slide 1", "body": "Summary of bids."}])
    assert pptx_ok and os.path.exists("test_pres.pptx"), "PowerPoint pptx generation failed!"
    print("[OK] PowerPoint presentation created successfully (test_pres.pptx).")
    os.remove("test_pres.pptx")

    # ReportLab PDF
    pdf_ok = office.generate_pdf_report("test_report.pdf", "Cheapest Suppliers Report", [{"item": "Shingles", "cheapest_price": 12.0, "vendor": "Burlington Suppliers"}])
    assert pdf_ok and os.path.exists("test_report.pdf"), "ReportLab PDF generation failed!"
    print("[OK] PDF Procurement Report created successfully (test_report.pdf).")
    # Leave test_report.pdf on disk temporarily for Document Intelligence Pipeline test step!

    # 4. Test Secret Vault Manager
    print("\n=== Testing Secret Vault Manager (Keyring) ===")
    vault = SecretVaultManager()
    vault.set_secret("TEST_API_KEY", "key_data_val_123")
    retrieved = vault.get_secret("TEST_API_KEY")
    print(f"Retrieved key: {retrieved}")
    assert retrieved == "key_data_val_123", "Secret Vault failed to cache API credentials!"
    print("[OK] Secret Vault Manager credential storage validated successfully.")

    # 5. Test AI Gateway completions fallback
    print("\n=== Testing AI Gateway completions ===")
    gateway = AIGateway()
    res_completions = gateway.get_completions([{"role": "user", "content": "Generate plan Roofing"}], model_route="local")
    print(f"Gateway Response length: {len(res_completions)}")
    assert "web_search" in res_completions, "AI Gateway failed to route completions fallback plan!"
    print("[OK] AI Gateway completion dispatcher validated successfully.")

    # 6. Test Search Engine Router
    print("\n=== Testing Search Engine Router (DDG) ===")
    search = SearchEngineRouter()
    search_res = search.query_search("Roofing suppliers", store, None)
    print(f"Search Web Hits Count: {len(search_res['web_hits'])}")
    print(f"Search Vector Hits Count: {len(search_res['local_document_hits'])}")
    assert len(search_res["local_document_hits"]) > 0, "Search engine router failed to query local vector store!"
    print("[OK] Search Engine Router aggregated channels validated successfully.")

    # 7. Test Document Intelligence Pipeline
    print("\n=== Testing Document Intelligence Pipeline ===")
    pipeline = DocumentIntelligencePipeline()
    doc_res = pipeline.process_file("test_report.pdf")
    print(f"Document Type classified: {doc_res['document_type']}")
    print(f"Confidence score: {doc_res['confidence']:.2f}")
    assert doc_res["success"], "Document Pipeline failed to process file!"
    print("[OK] Document Intelligence Pipeline processing validated successfully.")
    
    # Cleanup pdf
    if os.path.exists("test_report.pdf"):
        os.remove("test_report.pdf")

    # 8. Test Dynamic Tool Registry
    print("\n=== Testing Dynamic Tool Registry ===")
    registry = ToolRegistry()
    registry.register_tool("add_nums", lambda args: {"result": args.get("a", 0) + args.get("b", 0)})
    exec_res = registry.execute_tool("add_nums", json.dumps({"a": 10, "b": 20}))
    print(f"Executed Dynamic Tool Result: {exec_res}")
    assert exec_res.get("result") == 30, "Dynamic Tool execution calculations failed!"
    print("[OK] Dynamic Tool Registry validation passed.")

    # 9. Test Process Learning Engine
    print("\n=== Testing Process Learning Engine ===")
    ple = ProcessLearningEngine()
    ple.create_workflow("invoice_save")
    step = ple.record_step(
        name="invoice_save",
        window_title="QuickBill POS",
        control_id="txt_central_gst",
        ocr_text="CGST Amount",
        action_type="click",
        coords=(300, 200)
    )
    current_controls = [{"control_id": "txt_central_gst", "coordinates": (350, 210)}]
    adapted = ple.adapt_step_to_ui(step, current_controls, [])
    assert adapted["method"] == "pywinauto_control", "UI adaptation failed!"
    print("[OK] Adapted step successfully.")

    # 10. Test Desktop Controller Fallbacks
    print("\n=== Testing Desktop Controller Fallbacks ===")
    dc = DesktopController()
    click_res = dc.click_button("NonExistentWindow", "invalid_btn_id", "Save", (120, 120))
    assert click_res.get("success"), "Desktop controller fallback failed!"
    print("[OK] Desktop Controller fallback routed successfully.")

    # 11. Test Business Knowledge Engine
    print("\n=== Testing Business Knowledge Engine ===")
    bke = BusinessKnowledgeEngine()
    bke.add_entity("CUST01", "Customer", {"name": "Artee Fabrics"})
    bke.add_entity("INV99", "Invoice", {"amount": 1200.0})
    bke.add_entity("PAY30", "Payment", {"method": "central_bank"})
    bke.add_relationship("CUST01", "INV99", "has_issued_invoice")
    bke.add_relationship("INV99", "PAY30", "cleared_by_payment")
    path = bke.find_path("CUST01", "PAY30")
    assert len(path) == 3, "Knowledge graph BFS path failed!"
    print("[OK] Business Brain graph connections validated.")

    # 12. Test Business Rules Engine (GST/VAT checks)
    print("\n=== Testing Business Rules Engine ===")
    bre = BusinessRulesEngine()
    audit = bre.validate_totals(gross=100.0, discount=10.0, tax_pct=18.0, net_reported=106.20)
    assert audit["valid"], "Math validations check failed!"
    gst_split = bre.check_gst_rates(state_tax=4.50, central_tax=4.50)
    assert gst_split, "GST split validations check failed!"
    print("[OK] Business rules validator passed.")

    # 13. Test Scrapy Crawler
    print("\n=== Testing Scrapy Crawler Subprocess ===")
    items = scrape_url("https://quotes.toscrape.com/")
    assert isinstance(items, list), "Crawler failed to return list!"
    print("[OK] Scrapy crawler isolation validated successfully.")

    # 14. Test Executive Planner State Machine Loop
    print("\n=== Testing Executive Planner task execution loop ===")
    root_task = Task(title="Roofing Suppliers Bid Analysis", description="Find cheapest roofing suppliers and compare prices.")
    db.add(root_task)
    db.commit()

    planner = ExecutivePlanner(db)
    res = planner.execute_loop(root_task.id)
    print(f"Planner Loop Result: {res}")

    subtasks = db.query(Task).filter(Task.parent_id == root_task.id).all()
    assert len(subtasks) > 0, "Planner failed to generate subtasks!"
    blocked_task = next((t for t in subtasks if t.tool_name == "send_email"), None)
    if blocked_task:
        assert blocked_task.status == 'blocked', "Send email task should be blocked!"
        approval = db.query(ApprovalItem).filter(ApprovalItem.task_id == blocked_task.id).first()
        approval.status = 'approved'
        db.commit()
        res = planner.execute_loop(root_task.id)
        db.refresh(root_task)
        assert root_task.status == 'completed', "Root task failed to complete!"
        print("[OK] Resumed planner loop successfully completed task.")

    db.close()

if __name__ == "__main__":
    test_ops_flow()
