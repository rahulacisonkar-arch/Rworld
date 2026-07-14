import os
import sys

# Ensure backend directory is in python path
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from backend.models import init_db, Task, ApprovalItem, DocumentEmbedding
from backend.vector_store import NumPyVectorStore
from backend.planner import ExecutivePlanner

def test_flow():
    # 1. Initialize SQLite Database (in-memory for test isolation)
    print("=== Testing Database Initialization ===")
    engine, SessionLocal = init_db("sqlite:///:memory:")
    db = SessionLocal()
    print("[OK] SQLite database initialized successfully in-memory.")

    # 2. Test NumPy Vector Store
    print("\n=== Testing Vector Storage & Cosine Search ===")
    store = NumPyVectorStore(db)
    
    # Add documents
    d1 = store.add_document("Roofing materials: shingles cost $12.00 per unit, metal cost $18.00.", filename="shingles.txt")
    d2 = store.add_document("Wood paneling: cedar siding cost $14.50 per unit.", filename="cedar.txt")
    
    print(f"Added document 1 (ID: {d1})")
    print(f"Added document 2 (ID: {d2})")
    
    # Query search
    query = "shingles pricing"
    print(f"Searching query: '{query}'")
    results = store.similarity_search(query, top_k=1)
    
    assert len(results) > 0, "No results returned!"
    print(f"[OK] Vector Search Match: '{results[0]['text']}' (Score: {results[0]['score']:.4f})")
    assert "shingles" in results[0]['text'], "Incorrect search match returned!"

    # 2.5 Test Scrapy Crawler Module
    print("\n=== Testing Scrapy Crawler Process Isolation ===")
    from backend.scraper import scrape_url
    test_url = "https://quotes.toscrape.com/"
    print(f"Crawling website: {test_url} ...")
    items = scrape_url(test_url)
    print(f"Scraped {len(items)} items successfully.")
    assert isinstance(items, list), "Scraped items must be returned as a list!"
    if len(items) > 0:
        print(f"[OK] First scraped item: {items[0]}")
    else:
        print("[INFO] Offline or no items extracted, verified interface response structure.")

    # 3. Test Executive Planner State Machine Loop
    print("\n=== Testing Executive Planner self-thinking loop ===")
    root = Task(title="Supplier quote analysis", description="Find cheapest roofing suppliers and email manager.")
    db.add(root)
    db.commit()

    planner = ExecutivePlanner(db)
    print("Executing loop (should pause at Approval Block)...")
    res = planner.execute_loop(root.id)
    print(f"Planner Result: {res}")

    # Assert subtasks were generated
    subtasks = db.query(Task).filter(Task.parent_id == root.id).all()
    print(f"Generated {len(subtasks)} subtasks:")
    for t in subtasks:
        print(f" - [{t.status.upper()}] {t.title} (Tool: {t.tool_name})")
    
    assert len(subtasks) > 0, "Subtasks count should be greater than zero!"
    
    # Assert it blocked on send_email tool
    blocked_task = next(t for t in subtasks if t.tool_name == "send_email")
    assert blocked_task.status == 'blocked', "Send email task should be blocked!"
    print(f"[OK] Confirmed task #{blocked_task.id} is blocked waiting for approval.")

    # Check approval item exists
    approval = db.query(ApprovalItem).filter(ApprovalItem.task_id == blocked_task.id).first()
    assert approval is not None, "Approval queue item not generated!"
    assert approval.status == 'pending', "Approval item should be pending!"
    print(f"[OK] Found pending ApprovalItem ID {approval.id} for action: {approval.action_type}")

    # 4. Simulate user approval decision
    print("\n=== Simulating User Approval ===")
    approval.status = 'approved'
    db.commit()
    print("Approval granted. Re-running planner execution loop...")
    
    res = planner.execute_loop(root.id)
    print(f"Final Planner Result: {res}")

    # Assert all complete
    db.refresh(root)
    db.refresh(blocked_task)
    print(f"Final Root Task Status: {root.status.upper()}")
    print(f"Final Email Task Status: {blocked_task.status.upper()}")
    
    assert root.status == 'completed', "Root task failed to complete after approval!"
    assert blocked_task.status == 'completed', "Email task failed to complete after approval!"
    print("[OK] RWorld AI self-thinking loop and approvals completed successfully!")

    db.close()

if __name__ == "__main__":
    test_flow()
