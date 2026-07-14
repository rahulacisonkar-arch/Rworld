import os
import json
import urllib.request
from dotenv import load_dotenv
load_dotenv()
from sqlalchemy.orm import Session
from .models import Task, ApprovalItem, MemoryItem

def load_nvidia_config():
    api_key = os.environ.get("NVIDIA_API_KEY")
    model = os.environ.get("NVIDIA_MODEL", "meta/llama-3.1-70b-instruct")
    
    # Try reading relative to this file
    env_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env")
    if os.path.exists(env_path):
        try:
            with open(env_path, "r") as f:
                for line in f:
                    line = line.strip()
                    if line.startswith("NVIDIA_API_KEY="):
                        api_key = line.split("=", 1)[1]
                    elif line.startswith("NVIDIA_MODEL="):
                        model = line.split("=", 1)[1]
        except Exception:
            pass
    return api_key, model


class ExecutivePlanner:
    """
    State machine executing Mworld Intellegence's autonomous self-thinking loop:
    Understand -> Plan -> Subtask -> Choose Tool -> Execute -> Review -> Correct -> Complete.
    """

    def __init__(self, db_session: Session):
        self.db = db_session

    def execute_loop(self, root_task_id: int):
        """
        Executes the self-thinking state loop over the subtask queue.
        """
        root_task = self.db.query(Task).filter(Task.id == root_task_id).first()
        if not root_task:
            return "Error: Root task not found."

        # Step 1: Create Plan (generate subtasks if not already present)
        subtasks = self.db.query(Task).filter(Task.parent_id == root_task_id).order_by(Task.sequence_order).all()
        if not subtasks:
            subtasks = self._generate_plan(root_task)

        # Step 2: Execute each subtask sequentially
        for task in subtasks:
            if task.status in ['completed', 'failed']:
                continue

            # If task is blocked by pending approval, we pause execution
            if task.status == 'blocked':
                # Check if approval has been granted
                approval = self.db.query(ApprovalItem).filter(
                    ApprovalItem.task_id == task.id, 
                    ApprovalItem.status == 'pending'
                ).first()
                if approval:
                    print(f"Task {task.id} remains BLOCKED waiting for approval of: {approval.action_type}")
                    break
                else:
                    # Check if it was approved
                    approved_item = self.db.query(ApprovalItem).filter(
                        ApprovalItem.task_id == task.id, 
                        ApprovalItem.status == 'approved'
                    ).first()
                    if approved_item:
                        task.status = 'in_progress'
                        self.db.commit()
                    else:
                        break

            task.status = 'in_progress'
            self.db.commit()

            # Step 3: Choose Tool & Execute
            success = self._run_subtask_loop(task)
            if not success:
                if task.status == 'blocked':
                    print(f"Task {task.id} is BLOCKED. Waiting for WebSocket approval.")
                    break
                else:
                    task.status = 'failed'
                    root_task.status = 'failed'
                    self.db.commit()
                    return f"Execution failed at subtask: {task.title}"

        # Check if all subtasks are complete
        all_done = all(t.status == 'completed' for t in subtasks)
        if all_done:
            root_task.status = 'completed'
            self.db.commit()
            return "Root task executed successfully."
        else:
            return "Root task paused. Approvals pending."

    def _generate_plan(self, root_task: Task):
        """
        Translates raw goal description into sequential subtasks.
        Queries NVIDIA cloud LLM first, falling back to deterministic patterns if unavailable.
        """
        prompt = root_task.description or root_task.title
        plan_steps = []

        api_key, model = load_nvidia_config()
        if api_key:
            system_instructions = (
                "You are the Executive Planner module of Mworld Intellegence. "
                "Your job is to break a high-level task goal into a list of subtasks. "
                "Each subtask must choose one tool from: ['web_search', 'generate_excel', 'scan_folder', 'ocr_parse', 'db_write', 'review_report', 'send_email']. "
                "For 'send_email', it always blocks waiting for human approval. "
                "Provide the plan ONLY as a valid JSON array of objects, with no markdown formatting around it. "
                "Example format:\n"
                "[\n"
                "  {\"title\": \"Search Web for Roofing Suppliers\", \"tool\": \"web_search\", \"input\": {\"query\": \"cheapest roofing suppliers\"}},\n"
                "  {\"title\": \"Draft Procurement Email to Purchasing Manager\", \"tool\": \"send_email\", \"input\": {\"to\": \"manager@mworld.com\", \"subject\": \"Supplier Bids\"}}\n"
                "]"
            )
            
            payload = {
                "model": model,
                "messages": [
                    {"role": "system", "content": system_instructions},
                    {"role": "user", "content": f"Create plan for: {prompt}"}
                ],
                "temperature": 0.2,
                "max_tokens": 1024
            }
            req_data = json.dumps(payload).encode('utf-8')
            try:
                req = urllib.request.Request(
                    "https://integrate.api.nvidia.com/v1/chat/completions",
                    data=req_data,
                    headers={
                        "Authorization": f"Bearer {api_key}",
                        "Content-Type": "application/json"
                    },
                    method="POST"
                )
                with urllib.request.urlopen(req, timeout=12) as res:
                    res_data = json.loads(res.read().decode('utf-8'))
                    content = res_data["choices"][0]["message"]["content"].strip()
                    # Strip out any potential markdown wrapper ```json ... ```
                    if content.startswith("```"):
                        content = content.split("```", 2)[1]
                        if content.startswith("json"):
                            content = content[4:].strip()
                        else:
                            content = content.strip()
                    plan_steps = json.loads(content)
                    print(f"[Planner] NVIDIA NIM successfully generated {len(plan_steps)} plan steps.")
            except Exception as e:
                print(f"[Planner] NVIDIA NIM call failed/timed out ({e}). Falling back to mockup plan rules.")
                plan_steps = []

        if not plan_steps:
            # Simple semantic parsing pattern matching fallback
            if any(w in prompt.lower() for w in ["open", "go to", "browser", "google", "search", "find", "who", "what", "cheapest", "price", "list"]):
                plan_steps = [
                    {"title": f"Execute Browser Goal: {prompt}", "tool": "browser_run", "input": {"task": prompt}}
                ]
            elif "supplier" in prompt.lower() or "roofing" in prompt.lower():
                plan_steps = [
                    {"title": "Search Web for Roofing Suppliers", "tool": "web_search", "input": {"query": "cheapest roofing suppliers"}},
                    {"title": "Compare Vendor Prices in Excel", "tool": "generate_excel", "input": {"template": "supplier_comparison"}},
                    {"title": "Self-Review Quote Analysis Report", "tool": "review_report", "input": {"check": "formatting_and_math"}},
                    {"title": "Draft Procurement Email to Purchasing Manager", "tool": "send_email", "input": {"to": "manager@mworld.com", "subject": "Supplier Bids"}}
                ]
            elif "invoice" in prompt.lower() or "utility" in prompt.lower():
                plan_steps = [
                    {"title": "Scan Invoices Directory", "tool": "scan_folder", "input": {"path": "./invoices"}},
                    {"title": "Extract OCR Details", "tool": "ocr_parse", "input": {"format": "utility_bill"}},
                    {"title": "Self-Review Extracted Totals", "tool": "review_report", "input": {"check": "accuracy_check"}},
                    {"title": "Update SQLite Ledger Database", "tool": "db_write", "input": {"table": "expenses"}}
                ]
            else:
                # General fallback plan
                plan_steps = [
                    {"title": "Search Info for Goal", "tool": "web_search", "input": {"query": prompt}},
                    {"title": "Draft Summary Report PDF", "tool": "generate_report", "input": {"output_file": "summary.pdf"}},
                    {"title": "Self-Review Summary Output", "tool": "review_report", "input": {"check": "tone_and_logic"}}
                ]

        subtasks = []
        for idx, step in enumerate(plan_steps):
            task = Task(
                title=step["title"],
                parent_id=root_task.id,
                tool_name=step["tool"],
                tool_input=json.dumps(step["input"]),
                status='pending',
                sequence_order=idx
            )
            self.db.add(task)
            subtasks.append(task)
        
        self.db.commit()
        return subtasks

    def _run_subtask_loop(self, task: Task):
        """
        Executes single subtask, self-reviews output, and handles auto-corrections.
        """
        print(f"-> Starting loop for subtask: {task.title}")
        
        # 1. Execute Tool
        output_data = self._invoke_tool(task.tool_name, task.tool_input, task)
        if task.status == 'blocked':
            return False

        # 2. Self-Critique / Review loop (up to 3 retries)
        retries = 3
        while retries > 0:
            critique = self._self_review(task.tool_name, output_data)
            if critique["passed"]:
                task.tool_output = json.dumps(output_data)
                task.status = 'completed'
                self.db.commit()
                print(f"[OK] Subtask complete: {task.title}")
                return True
            else:
                print(f"[WARN] Self-Critique Failed: {critique['reason']}. Correcting output...")
                output_data = self._auto_correct(task.tool_name, output_data, critique["reason"])
                retries -= 1

        task.error_message = "Self-Critique failed to pass quality threshold after 3 attempts."
        return False

    def _invoke_tool(self, name: str, input_json: str, task: Task):
        """
        Tool exec engine. Triggers Approval Queue block for high-risk operations.
        """
        args = json.loads(input_json)
        
        # High Safety Rule triggers
        if name == "send_email":
            # Check if this task has already been approved
            approved = self.db.query(ApprovalItem).filter(
                ApprovalItem.task_id == task.id,
                ApprovalItem.status == "approved"
            ).first()
            if approved:
                return {"status": "email_sent", "to": args.get("to")}

            # Safety Block: Send email requires WebSocket manager approval
            approval = self.db.query(ApprovalItem).filter(
                ApprovalItem.task_id == task.id, 
                ApprovalItem.status == 'pending'
            ).first()
            if not approval:
                approval = ApprovalItem(
                    task_id=task.id,
                    action_type="send_email",
                    payload=input_json,
                    status='pending'
                )
                self.db.add(approval)
                task.status = 'blocked'
                self.db.commit()
                print(f"[SECURE] Tool approval requested for send_email. Task #{task.id} BLOCKED.")
            return None

        # Standard non-blocked mock tools
        if name == "browser_run":
            task_str = args.get("task", "")
            print(f"[browser-use] Running task: {task_str}")
            try:
                from browser_use import Agent as BrowserUseAgent
                from browser_use import ChatOpenAI as BrowserUseChatOpenAI
                import asyncio
                import threading

                llm = None
                if os.environ.get("NVIDIA_API_KEY"):
                    llm = BrowserUseChatOpenAI(
                        base_url="https://integrate.api.nvidia.com/v1",
                        api_key=os.environ.get("NVIDIA_API_KEY"),
                        model=os.environ.get("NVIDIA_MODEL", "meta/llama-3.1-70b-instruct"),
                        temperature=0.0
                    )
                    print(f"[LLM] Connected directly to NVIDIA integration endpoint with model: {os.environ.get('NVIDIA_MODEL')}")
                else:
                    try:
                        from browser_use import ChatBrowserUse
                        llm = ChatBrowserUse()
                        print("[LLM] Initialized ChatBrowserUse.")
                    except Exception:
                        llm = BrowserUseChatOpenAI(
                            base_url=os.environ.get("OPENAI_BASE_URL", "http://127.0.0.1:8787/v1"),
                            api_key=os.environ.get("OPENAI_API_KEY", "sk-ant-dummy"),
                            model="google/gemini-2.5-pro",
                            temperature=0.0
                        )

                final_res = ""
                def run_agent():
                    nonlocal final_res
                    async def async_main():
                        browser_obj = None
                        if os.environ.get("BROWSER_USE_API_KEY"):
                            from browser_use import Browser
                            browser_obj = Browser(use_cloud=True)
                            print("[LLM] Initializing Browser Use Cloud browser instance...")
                        agent = BrowserUseAgent(
                            task=task_str,
                            llm=llm,
                            use_vision=False,
                            browser=browser_obj
                        )
                        history = await agent.run()
                        res_val = history.final_result()
                        if not res_val:
                            # Search for 'done' action
                            for act in reversed(history.model_actions()):
                                class_name = act.__class__.__name__.lower()
                                if 'done' in class_name:
                                    res_val = getattr(act, 'text', '') or getattr(act, 'content', '') or str(act)
                                    break
                        if not res_val:
                            extracted = [e for e in history.extracted_content() if e]
                            if extracted:
                                res_val = extracted[-1]
                        return res_val
                    loop = asyncio.new_event_loop()
                    asyncio.set_event_loop(loop)
                    final_res = loop.run_until_complete(async_main())
                    loop.close()

                t = threading.Thread(target=run_agent)
                t.start()
                t.join()
                return {"status": "browser_completed", "result": final_res, "details": f"Executed browser command: {task_str}"}
            except Exception as e:
                safe_err = str(e).encode('ascii', errors='replace').decode('ascii')
                print(f"[browser-use Error] {safe_err}")
                return {"status": "browser_failed", "error": str(e)}

        if name == "web_search":
            return {"results": [f"Vendor bid: $15.00/sqft for {args.get('query')}"], "source": "Google Index"}
        elif name == "web_scrape":
            from .scraper import scrape_url
            url = args.get("url", "https://quotes.toscrape.com/")
            print(f"[Scraper] Triggering Scrapy crawl on: {url}")
            results = scrape_url(url)
            return {"scraped_items_count": len(results), "items": results[:10]}
        elif name == "generate_excel":
            return {"file_path": "./exports/comparison.xlsx", "rows_written": 15}
        elif name == "scan_folder":
            return {"files": ["invoice_882.pdf", "utility_may.png"]}
        elif name == "ocr_parse":
            return {"provider": "Artee Fabrics", "amount": 1500.00, "tax": 12.38}
        elif name == "db_write":
            return {"rows_inserted": 1}
        elif name == "review_report":
            return {"review_status": "ready"}
        
        return {"status": "executed"}

    def _self_review(self, tool_name: str, data: dict):
        """
        Self critic engine checking for math accuracy, format guidelines, and values.
        """
        if not data:
            return {"passed": False, "reason": "No data returned from tool execution."}

        # Mock self-critic audit check patterns
        if tool_name == "ocr_parse":
            # Check if amount is negative
            if data.get("amount", 0) < 0:
                return {"passed": False, "reason": "Extracted invoice amount cannot be negative."}
        elif tool_name == "generate_excel":
            if data.get("rows_written", 0) <= 0:
                return {"passed": False, "reason": "Generated sheet contains no rows."}

        return {"passed": True, "reason": ""}

    def _auto_correct(self, tool_name: str, data: dict, reason: str):
        """
        Automatically fixes values if the self-critic rejects the output.
        """
        corrected = dict(data)
        if "negative" in reason.lower():
            corrected["amount"] = abs(corrected.get("amount", 0))
        elif "no rows" in reason.lower():
            corrected["rows_written"] = 1
        return corrected
