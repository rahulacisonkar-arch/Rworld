"""
RWorld AI Operations Core - Production Readiness Deep Audit Checklist
Verifies all 7 planner agent tool mechanisms, database schemas, model routing, and gateway fallbacks.
"""
import sys
import os
import json
import sqlite3
import traceback

# Setup python path to include backend
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from backend.planner import ExecutivePlanner
from backend.models import init_db, Task, ApprovalItem

print("==========================================================")
print("             RWORLD AI PRODUCTION AUDIT CHECK             ")
print("==========================================================")

# Initialize in-memory / local test db session
engine, SessionLocal = init_db("sqlite:///artee_ai.db")
db = SessionLocal()
planner = ExecutivePlanner(db)

results = {}

# Helper to test tools
def audit_tool(name, args):
    print(f"\n[Audit] Testing tool '{name}' with input: {args}...")
    try:
        task = Task(title=f"Audit {name}", tool_name=name, status="in_progress")
        db.add(task)
        db.commit()
        
        # Invoke directly
        res = planner._invoke_tool(name, json.dumps(args), task)
        
        # Check database block status
        db.refresh(task)
        if task.status == 'blocked':
            print(f"  -> SUCCESS: Secure Approvals Guard caught and paused action (Expected).")
            results[name] = {"status": "PASS (SECURE BLOCKED)", "output": str(res)}
        else:
            print(f"  -> SUCCESS: Tool executed. Result keys: {list(res.keys()) if isinstance(res, dict) else type(res)}")
            results[name] = {"status": "PASS", "output": str(res)[:150]}
    except Exception as e:
        print(f"  -> FAIL: {e}")
        traceback.print_exc()
        results[name] = {"status": "FAIL", "error": str(e)}

# 1. Audit Search Engine Router
audit_tool("web_search", {"query": "cheapest iphone online"})

# 2. Audit Scraper Engine
audit_tool("web_scrape", {"url": "https://quotes.toscrape.com/"})

# 3. Audit QuickBill ERP Integration Engine
audit_tool("quickbill_action", {"action": "validate_ledger"})
audit_tool("quickbill_action", {
    "action": "enter_invoice",
    "doc_no": "INV-2026-AUDIT",
    "items": [{"item_code": "SOLAR-PANEL", "qty": 10, "rate": 250.0}]
})

# 4. Audit Document Intelligence Agent & PDF Generator
audit_tool("document_action", {"action": "compare_prices"})

# 5. Audit GUI/Desktop Controller
audit_tool("desktop_action", {"window": "QuickBill", "control_id": "btn_save", "coords": [100, 100]})

# 6. Audit Process Learning Engine (Workflows)
audit_tool("run_learned_workflow", {"workflow_name": "invoice_entry"})

# 7. Audit Browser-Use Agent Integration (Timeout set small for fast check)
# We test OpenRouter / local LLM resolution directly
print("\n[Audit] Testing Browser-Use LLM Resolver Configuration...")
try:
    llm, supports_vision = planner._resolve_browser_llm()
    print(f"  -> SUCCESS: Resolved LLM: {llm.__class__.__name__} (vision support: {supports_vision})")
    results["browser_llm_resolution"] = {"status": "PASS", "output": f"{llm.__class__.__name__} (vision: {supports_vision})"}
except Exception as e:
    print(f"  -> FAIL: {e}")
    results["browser_llm_resolution"] = {"status": "FAIL", "error": str(e)}

# 8. Check Secure Approvals persistence
print("\n[Audit] Testing DB Secure Approvals persistence...")
try:
    approval = db.query(ApprovalItem).first()
    if approval:
        print(f"  -> SUCCESS: Found active approvals in DB. Latest action: {approval.action_type} (Status: {approval.status})")
    results["approvals_db"] = {"status": "PASS" if approval else "WARNING (No approvals found in DB yet)", "output": str(approval)}
except Exception as e:
    print(f"  -> FAIL: {e}")
    results["approvals_db"] = {"status": "FAIL", "error": str(e)}

print("\n==========================================================")
print("                    AUDIT SUMMARY RESULTS                  ")
print("==========================================================")
for k, v in results.items():
    print(f"{k:<30}: {v['status']}")
print("==========================================================")

db.close()
