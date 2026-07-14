import os
import json
import asyncio
import concurrent.futures
from dotenv import load_dotenv

# Load .env from backend directory
_env_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".env")
load_dotenv(_env_path)
from sqlalchemy.orm import Session

from .models import Task, ApprovalItem, MemoryItem
from .scraper import scrape_url
from .quickbill_engine import QuickBillEngine
from .document_engine import DocumentIntelligenceAgent, ExcelAgent, OfficeAutomationAgent
from .process_learning import ProcessLearningEngine
from .desktop_controller import DesktopController
from .business_knowledge import BusinessKnowledgeEngine
from .business_rules import BusinessRulesEngine

# Architect Phase 3 additions
from .secret_manager import SecretVaultManager
from .ai_gateway import AIGateway
from .search_engine import SearchEngineRouter
from .tool_registry import ToolRegistry
from .document_intelligence import DocumentIntelligencePipeline

# Browser Use integrations
from browser_use import Agent, Browser, ChatBrowserUse, ChatOpenAI as BrowserUseChatOpenAI

# Persistent Agent Memory (mem0ai)
try:
    from mem0 import Memory as Mem0Memory
    _MEM0_AVAILABLE = True
except ImportError:
    _MEM0_AVAILABLE = False
    print("[Mem0] mem0ai not installed — persistent memory disabled.")

def load_nvidia_config():
    api_key = os.environ.get("NVIDIA_API_KEY")
    model = os.environ.get("NVIDIA_MODEL", "meta/llama-3.1-70b-instruct")
    
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

def route_model(prompt: str, context_len: int, requires_vision: bool, file_size_mb: float) -> tuple:
    """
    Model Router: Automatically switches from Local Ollama to Cloud NVIDIA NIM API
    based on context length, vision requirements, document size, or prompt complexity.
    """
    api_key, _ = load_nvidia_config()
    or_key = os.environ.get("OPENROUTER_API_KEY")
    has_cloud = bool(api_key or or_key)
    
    if not has_cloud:
        return "local", "No cloud API keys configured. Fallback to local."

    # Browser/search tasks always use cloud for best results
    browser_keywords = ["iphone", "search", "find", "browser", "google", "open", "buy", "price", "cheapest", "best"]
    if any(w in prompt.lower() for w in browser_keywords):
        return "cloud", "Browser/search task — routing to cloud LLM for best accuracy."

    # Routing rules
    if context_len > 6000:
        return "cloud", f"Context length ({context_len} chars) exceeds local model capability (>6000)"
    if requires_vision:
        return "cloud", "Vision reasoning required (screenshot / OCR parsing)"
    if file_size_mb > 1.5:
        return "cloud", f"Large document size ({file_size_mb}MB) requested (>1.5MB)"
    if "complex" in prompt.lower() or "deep planning" in prompt.lower():
        return "cloud", "Complex planning requested"

    return "local", "Default local routing"

class ExecutivePlanner:
    """
    State machine executing RWorld AI's autonomous self-thinking loop:
    Understand -> Route Model -> Plan -> Dynamic Tool Selection -> Execute -> Review -> Correct -> Complete.
    """

    def __init__(self, db_session: Session):
        self.db = db_session
        
        # Enterprise Infrastructure Modules
        self.secrets = SecretVaultManager()
        self.ai_gateway = AIGateway()
        self.search_engine = SearchEngineRouter()
        self.doc_pipeline = DocumentIntelligencePipeline()
        self.registry = ToolRegistry()
        
        # Local engines
        self.qb_engine = QuickBillEngine()
        self.doc_intel = DocumentIntelligenceAgent()
        self.excel_agent = ExcelAgent()
        self.office_agent = OfficeAutomationAgent()
        self.process_learning = ProcessLearningEngine()
        self.desktop_controller = DesktopController()
        self.knowledge_engine = BusinessKnowledgeEngine()
        self.rules_engine = BusinessRulesEngine()

        # Persistent Memory Layer (mem0ai)
        # Recalls past task decisions, user corrections and supplier contacts across sessions.
        self._mem0_user_id = "rworld_executive_planner"
        if _MEM0_AVAILABLE:
            try:
                self.memory = Mem0Memory()
                print("[Mem0] Persistent agent memory active.")
            except Exception as e:
                self.memory = None
                print(f"[Mem0] Memory init failed (non-fatal): {e}")
        else:
            self.memory = None
        
        # Dynamic Tool Registrations
        self.registry.register_tool("web_search", self._tool_web_search)
        self.registry.register_tool("web_scrape", self._tool_web_scrape)
        self.registry.register_tool("quickbill_action", self._tool_quickbill_action)
        self.registry.register_tool("document_action", self._tool_document_action)
        self.registry.register_tool("desktop_action", self._tool_desktop_action)
        self.registry.register_tool("run_learned_workflow", self._tool_run_learned_workflow)
        self.registry.register_tool("browser_action", self._tool_browser_action)
        self.registry.register_tool("send_email", self._tool_send_email)

    def execute_loop(self, root_task_id: int):
        root_task = self.db.query(Task).filter(Task.id == root_task_id).first()
        if not root_task:
            return "Error: Root task not found."

        # Check subtasks status
        subtasks = self.db.query(Task).filter(Task.parent_id == root_task_id).order_by(Task.sequence_order).all()
        if not subtasks:
            # 1. Plan & Route Model
            subtasks = self._generate_plan(root_task)

        # 2. Sequential execution
        for task in subtasks:
            if task.status in ['completed', 'failed']:
                continue

            if task.status == 'blocked':
                # Check for approval queue status
                approval = self.db.query(ApprovalItem).filter(
                    ApprovalItem.task_id == task.id, 
                    ApprovalItem.status == 'pending'
                ).first()
                if approval:
                    print(f"Task #{task.id} remains BLOCKED waiting for approvals.")
                    break
                else:
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

            # Execute & Review loop
            success = self._run_subtask_loop(task)
            if not success:
                if task.status == 'blocked':
                    break
                else:
                    task.status = 'failed'
                    root_task.status = 'failed'
                    self.db.commit()
                    return f"Execution failed at subtask: {task.title}"

        # If all complete
        all_done = all(t.status == 'completed' for t in subtasks)
        if all_done:
            root_task.status = 'completed'
            self.db.commit()
            return "Root task executed successfully."
        else:
            return "Root task paused. Approvals pending."

    def _generate_plan(self, root_task: Task):
        prompt = root_task.description or root_task.title
        plan_steps = []

        # Model Router logic inside Gateway
        from .planner import route_model
        context_len = len(prompt)
        requires_vision = "ocr" in prompt.lower() or "image" in prompt.lower()
        file_size_mb = 2.0 if "large_file" in prompt.lower() else 0.1
        
        engine_route, reason = route_model(prompt, context_len, requires_vision, file_size_mb)
        print(f"[ModelRouter] Gateway Decision: {engine_route.upper()} ({reason})")

        # Retrieve relevant past task contexts from persistent memory to steer planning
        past_context = ""
        if self.memory:
            try:
                memories = self.memory.search(prompt, limit=3, filters={"user_id": self._mem0_user_id})
                recalled = [
                    m.get("memory", "") for m in memories.get("results", []) if m.get("memory")
                ]
                if recalled:
                    past_context = "\nRelevant past memory/context for this task:\n" + "\n".join(f"- {m}" for m in recalled)
                    print(f"[Planner] Injected {len(recalled)} items of persistent context into planner.")
            except Exception as e:
                print(f"[Planner] Memory lookup failed during planning: {e}")

        # Call AI Gateway instead of direct API
        system_instructions = (
            "You are the Executive Planner of RWorld AI. "
            "Break the user goal into sequential subtasks. "
            "Each subtask must choose one tool from: ['web_search', 'web_scrape', 'quickbill_action', 'document_action', 'send_email', 'browser_action']. "
            "Provide plan ONLY as valid JSON array of objects. No markdown wraps. "
            "Schema: Each object in the array MUST have three keys: "
            "1. 'title': string description of the step. "
            "2. 'tool': string name of the tool. "
            "3. 'input': object dictionary of tool arguments."
        )
        
        user_message = f"Plan for: {prompt}"
        if past_context:
            user_message += past_context

        messages = [
            {"role": "system", "content": system_instructions},
            {"role": "user", "content": user_message}
        ]
        
        try:
            content = self.ai_gateway.get_completions(messages, model_route=engine_route)
            if content.startswith("```"):
                content = content.split("```", 2)[1]
                if content.startswith("json"):
                    content = content[4:].strip()
            plan_steps = json.loads(content)
            print(f"[Planner] Gateway generated {len(plan_steps)} subtasks.")
        except Exception as e:
            print(f"[Planner] Gateway parsing failed ({e}). Falling back to local rules.")
            plan_steps = []

        if not plan_steps:
            # Check if task is about a browser / searching web directly on browser
            if "browser" in prompt.lower() or "iphone" in prompt.lower() or "search" in prompt.lower() or "open" in prompt.lower():
                plan_steps = [
                    {"title": f"Open Browser and run task: {prompt}", "tool": "browser_action", "input": {"task": prompt}}
                ]
            else:
                # Fallback mockup planner rules
                plan_steps = [
                    {"title": "Search Web for Roofing Suppliers", "tool": "web_search", "input": {"query": "cheapest roofing suppliers"}},
                    {"title": "Scrape Supplier site details", "tool": "web_scrape", "input": {"url": "https://quotes.toscrape.com/"}},
                    {"title": "Compare prices and create excel sheet", "tool": "document_action", "input": {"action": "compare_prices", "files": ["prices_a.xlsx"]}},
                    {"title": "Draft procurement summary proposal email", "tool": "send_email", "input": {"to": "manager@artee.com", "subject": "Roofing Bids"}}
                ]

        subtasks = []
        for idx, step in enumerate(plan_steps):
            if not isinstance(step, dict):
                continue
                
            title = step.get("title") or step.get("task") or step.get("description") or f"Subtask {idx + 1}"
            tool = step.get("tool") or step.get("tool_name") or step.get("action") or "browser_action"
            tool_input = step.get("input") or step.get("arguments") or step.get("args") or {}
            
            if not isinstance(tool_input, dict):
                tool_input = {"task": str(tool_input)}

            task = Task(
                title=title,
                parent_id=root_task.id,
                tool_name=tool,
                tool_input=json.dumps(tool_input),
                status='pending',
                sequence_order=idx
            )
            self.db.add(task)
            subtasks.append(task)
        
        self.db.commit()
        return subtasks

    def _run_subtask_loop(self, task: Task):
        print(f"-> Starting loop for subtask: {task.title}")
        
        # 1. Invoke tool dynamically via registry
        output_data = self._invoke_tool(task.tool_name, task.tool_input, task)
        if task.status == 'blocked':
            return False

        # 2. Self Review Loop (Quality Critique check)
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

        task.error_message = "Self-Critique quality review failed after 3 attempts."
        return False

    def _invoke_tool(self, name: str, input_json: str, task: Task):
        args = json.loads(input_json)

        # Safety approval gate for high-risk tools requiring human sign-off
        HIGH_RISK_TOOLS = {"send_email"}
        if name in HIGH_RISK_TOOLS:
            approved = self.db.query(ApprovalItem).filter(
                ApprovalItem.task_id == task.id,
                ApprovalItem.status == 'approved'
            ).first()
            if not approved:
                pending = self.db.query(ApprovalItem).filter(
                    ApprovalItem.task_id == task.id,
                    ApprovalItem.status == 'pending'
                ).first()
                if not pending:
                    approval = ApprovalItem(
                        task_id=task.id,
                        action_type=name,
                        payload=input_json,
                        status='pending'
                    )
                    self.db.add(approval)
                    task.status = 'blocked'
                    self.db.commit()
                    print(f"[SECURE] Approval requested for '{name}'. Task #{task.id} BLOCKED.")
                return None

        # Delegate execution to the Tool Registry dynamically
        return self.registry.execute_tool(name, input_json, task)

    # Core registered tool callbacks
    def _tool_web_search(self, args: dict, task: Task = None) -> dict:
        q = args.get("query", "")
        deep = args.get("deep", False)  # Set to True for comprehensive multi-source research

        # --- Deep Research via gpt-researcher (for complex procurement / supplier queries) ---
        research_keywords = ["supplier", "vendor", "price", "compare", "best", "cheapest",
                             "procurement", "report", "analysis", "research", "market"]
        should_deep_research = deep or any(kw in q.lower() for kw in research_keywords)

        if should_deep_research:
            try:
                from gpt_researcher import GPTResearcher
                import asyncio

                # Configure GPTResearcher to route requests through OpenRouter
                or_key = self.secrets.get_secret("OPENROUTER_API_KEY") or os.environ.get("OPENROUTER_API_KEY")
                if or_key:
                    os.environ["OPENAI_API_KEY"] = or_key
                    os.environ["OPENAI_API_BASE"] = "https://openrouter.ai/api/v1"
                    # Route LLM models to Gemini or llama on OpenRouter
                    os.environ["FAST_LLM"] = "openai/google/gemini-2.5-flash"
                    os.environ["SMART_LLM"] = "openai/google/gemini-2.5-pro"
                    os.environ["STRATEGIC_LLM"] = "openai/google/gemini-2.5-pro"

                # DuckDuckGo search API key fallback check
                if not os.environ.get("TAVILY_API_KEY"):
                    os.environ["RETRIEVER"] = "duckduckgo" # Fallback to DuckDuckGo search scraper

                async def _deep_research():
                    researcher = GPTResearcher(query=q, report_type="research_report")
                    await researcher.conduct_research()
                    return await researcher.write_report()

                def _run_research():
                    return asyncio.run(_deep_research())

                import concurrent.futures
                with concurrent.futures.ThreadPoolExecutor(max_workers=1) as ex:
                    report = ex.submit(_run_research).result(timeout=120)

                # Also store finding in mem0 for future recall
                if self.memory and report:
                    try:
                        self.memory.add(
                            f"Research on '{q}': {report[:500]}",
                            user_id=self._mem0_user_id
                        )
                    except Exception:
                        pass

                print(f"[GPTResearcher] Research complete. Report length: {len(report)} chars.")
                return {
                    "query": q,
                    "engine": "gpt-researcher (deep multi-source)",
                    "report": report,
                    "results": [{"title": "Deep Research Report", "snippet": report[:500], "source": "gpt-researcher"}]
                }
            except concurrent.futures.TimeoutError:
                print("[GPTResearcher] Deep research timed out. Falling back to DuckDuckGo.")
            except Exception as e:
                print(f"[GPTResearcher] Deep research failed ({e}). Falling back to DuckDuckGo.")

        # --- Fast search via DuckDuckGo (for simple lookups) ---
        res = self.search_engine.query_search(q, None, self.knowledge_engine)

        # Recall relevant past memories
        memory_context = []
        if self.memory:
            try:
                memories = self.memory.search(q, limit=3, filters={"user_id": self._mem0_user_id})
                memory_context = [
                    m.get("memory", "") for m in memories.get("results", []) if m.get("memory")
                ]
                if memory_context:
                    print(f"[Mem0] Recalled {len(memory_context)} relevant past memory item(s).")
            except Exception:
                pass

        return {
            "query": q,
            "engine": "DuckDuckGo Search Router",
            "results": res.get("web_hits", []),
            "past_memory": memory_context
        }

    def _tool_web_scrape(self, args: dict, task: Task = None) -> dict:
        url = args.get("url", "https://quotes.toscrape.com/")
        print(f"[Scraper] Running Scrapy subprocess crawl on: {url}")
        results = scrape_url(url)
        return {"scraped_count": len(results), "items": results[:10]}

    def _tool_quickbill_action(self, args: dict, task: Task = None) -> dict:
        action = args.get("action")
        if action == "validate_ledger":
            duplicates = self.qb_engine.detect_duplicate_invoice("INV-2026-MOCK")
            return {"duplicates_found": duplicates, "status": "validated"}
        elif action == "enter_invoice":
            totals = self.qb_engine.validate_calculations(args.get("items"), 0.0, 8.25, 3.0)
            res = self.qb_engine.enter_sales_invoice(args.get("doc_no"), 1, args.get("items"), totals)
            return res
        return {"status": "executed"}

    def _tool_document_action(self, args: dict, task: Task = None) -> dict:
        action = args.get("action")
        if action == "compare_prices":
            recs = [{"item": "Roofing Shingles", "cheapest_price": 12.0, "vendor": "Burlington Suppliers"}]
            self.office_agent.generate_pdf_report("procurement_report.pdf", "Cheapest Suppliers Report", recs)
            return {"report_created": "procurement_report.pdf", "vendor_recs": recs}
        elif action == "generate_report":
            recs = [{"item": "Generic Task Item", "cheapest_price": 5.0, "vendor": "Local Depot"}]
            self.office_agent.generate_pdf_report("general_report.pdf", args.get("title", "General Report"), recs)
            return {"report_created": "general_report.pdf"}
        else:
            # Fallback: route unknown document actions through browser_action
            fallback_task = args.get("task") or f"Perform document action: {action} — {args}"
            print(f"[DocumentAction] Unknown action '{action}', routing to browser_action as fallback.")
            return self._tool_browser_action({"task": fallback_task}, task)

    def _tool_desktop_action(self, args: dict, task: Task = None) -> dict:
        return self.desktop_controller.click_button(
            window_title=args.get("window", "QuickBill"),
            control_id=args.get("control_id", "btn_save"),
            ocr_label=args.get("ocr_label", "Save"),
            fallback_coords=tuple(args.get("coords", (100, 100)))
        )

    def _tool_send_email(self, args: dict, task: Task = None) -> dict:
        """Real email sending via SMTP — credentials from SecretVaultManager or .env."""
        from .connectors import EmailConnector
        connector = EmailConnector()
        return connector.create(args)

    def _tool_run_learned_workflow(self, args: dict, task: Task = None) -> dict:
        workflow_name = args.get("workflow_name", "invoice_entry")
        steps = self.process_learning.get_workflow(workflow_name)
        executions = []
        for step in steps:
            adapted = self.process_learning.adapt_step_to_ui(step, [], [])
            executions.append({"step_id": step["step_id"], "adapted_action": adapted})
        return {"success": True, "executed_steps_count": len(steps), "logs": executions}

    def _self_review(self, tool_name: str, data: dict):
        if not data:
            return {"passed": False, "reason": "Empty execution payload data returned."}
        if isinstance(data, dict):
            if "error" in data:
                return {"passed": False, "reason": f"Tool returned error: {data['error']}"}
            if data.get("success") is False:
                return {"passed": False, "reason": f"Tool execution failed: {data.get('error', 'Unknown failure')}"}
        if tool_name == "quickbill_action" and data.get("error"):
            return {"passed": False, "reason": f"QuickBill database validation failed: {data.get('error')}"}
        return {"passed": True, "reason": ""}

    def _auto_correct(self, tool_name: str, data: dict, reason: str):
        return data

    def _tool_browser_action(self, args: dict, task: Task = None) -> dict:
        action_task = args.get("task", "")
        if not action_task:
            # Fallback parsing for structured planning outputs
            if "url" in args:
                action_task = f"Navigate to {args['url']}"
                if args.get("action"):
                    action_task += f" and perform action: {args['action']}"
            elif task and task.title:
                action_task = task.title
            else:
                return {"error": "No browser task description provided."}

        print(f"[BrowserUse] Launching browser automation for task: '{action_task}'")
        
        # Determine best available LLM for BrowserUse
        # Priority: BROWSER_USE_API_KEY > NVIDIA NIM > OpenRouter > local Ollama
        llm, supports_vision = self._resolve_browser_llm()
        
        # Run the browser agent in a separate thread so asyncio.run() creates
        # a clean event loop — avoids conflicts with FastAPI's running loop.
        def run_agent_in_thread():
            async def _run():
                # Check for cloud browser key
                bu_key = self.secrets.get_secret("BROWSER_USE_API_KEY") or os.environ.get("BROWSER_USE_API_KEY")
                browser_args = {
                    "headless": False,
                    "minimum_wait_page_load_time": 0.2,
                    "wait_between_actions": 0.1
                }
                if bu_key:
                    browser_args["use_cloud"] = True
                    browser_args["headless"] = True
                    print("[BrowserUse] Spawning optimized remote browser on Browser Use Cloud...")
                else:
                    print("[BrowserUse] Spawning local headful browser (low latency settings active)...")

                browser = Browser(**browser_args)
                agent = Agent(
                    task=action_task,
                    llm=llm,
                    browser=browser,
                    use_vision=supports_vision,  # False for text-only models like Llama
                    max_history_items=10,
                    max_actions_per_step=5,
                    flash_mode=True
                )
                history = await agent.run(max_steps=20)
                return history.extracted_content()
            return asyncio.run(_run())

        try:
            with concurrent.futures.ThreadPoolExecutor(max_workers=1) as executor:
                future = executor.submit(run_agent_in_thread)
                results = future.result(timeout=300)  # 5 min timeout
            safe_extracted = str(results)[:200].encode('ascii', 'ignore').decode('ascii')
            print(f"[BrowserUse] Task completed. Extracted: {safe_extracted}")
            return {"success": True, "results": results}
        except concurrent.futures.TimeoutError:
            print("[BrowserUse] Task timed out after 5 minutes.")
            return {"success": False, "error": "Browser task timed out."}
        except Exception as e:
            import traceback
            safe_err = str(e).encode('ascii', 'ignore').decode('ascii')
            print(f"[BrowserUse] Execution failed: {safe_err}")
            print(traceback.format_exc().encode('ascii', 'ignore').decode('ascii'))
            return {"success": False, "error": str(e)}

    def _resolve_browser_llm(self):
        """Resolves the best available LLM for Browser-Use.
        Returns (llm, supports_vision) tuple.
        """
        # 1. Browser-Use Cloud API (fastest & most accurate, supports vision)
        bu_key = self.secrets.get_secret("BROWSER_USE_API_KEY") or os.environ.get("BROWSER_USE_API_KEY")
        if bu_key:
            os.environ["BROWSER_USE_API_KEY"] = bu_key
            print("[LLMResolver] Using ChatBrowserUse (cloud-optimized, vision=True)")
            return ChatBrowserUse(), True

        # 2. OpenRouter Gemini model (highly stable, vision-enabled, ultra-fast)
        or_key = self.secrets.get_secret("OPENROUTER_API_KEY") or os.environ.get("OPENROUTER_API_KEY")
        if or_key:
            print("[LLMResolver] Using OpenRouter Gemini (google/gemini-2.5-flash, vision=True)")
            return BrowserUseChatOpenAI(
                model="google/gemini-2.5-flash",
                api_key=or_key,
                base_url="https://openrouter.ai/api/v1",
                default_headers={"HTTP-Referer": "http://localhost:1420", "X-Title": "RWorld AI"}
            ), True

        # 3. NVIDIA NIM API — text-only (Llama is not multimodal, extremely fast response)
        nvidia_key = self.secrets.get_secret("NVIDIA_API_KEY") or os.environ.get("NVIDIA_API_KEY")
        if nvidia_key:
            print("[LLMResolver] Using NVIDIA NIM (meta/llama-3.1-70b-instruct, vision=False)")
            return BrowserUseChatOpenAI(
                model="meta/llama-3.1-70b-instruct",
                api_key=nvidia_key,
                base_url="https://integrate.api.nvidia.com/v1"
            ), False

        # 4. Local Ollama (last resort, text-only)
        print("[LLMResolver] Falling back to local Ollama (vision=False)")
        return BrowserUseChatOpenAI(
            model="qwen2.5:3b",
            api_key="ollama",
            base_url="http://localhost:11434/v1"
        ), False
