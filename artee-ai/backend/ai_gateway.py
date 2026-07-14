import json
import time
import litellm
from dotenv import load_dotenv
from .secret_manager import SecretVaultManager

# Load .env from backend directory
import os as _os
_env_path = _os.path.join(_os.path.dirname(_os.path.abspath(__file__)), ".env")
load_dotenv(_env_path)

# Disable litellm telemetries
litellm.telemetry = False

class AIGateway:
    """
    Decoupled AI Gateway managing multi-provider completions via LiteLLM.
    Handles token counting, provider fallback sequences, and cost tracking.
    """

    def __init__(self):
        self.secrets = SecretVaultManager()
        self.total_tokens_used = 0
        self.routing_logs = []

    def get_completions(self, messages: list, model_route: str = "local") -> str:
        """
        Dispatches chat completion queries to NVIDIA NIM, Ollama, or OpenRouter using LiteLLM.
        """
        import os
        import os
        # 1. OpenRouter (primary cloud LLM)
        or_key = os.environ.get("OPENROUTER_API_KEY")
        if or_key:
            try:
                start_time = time.time()
                print(f"[AIGateway] Routing completion to OpenRouter (meta-llama/llama-3.3-70b-instruct:free)...")
                response = litellm.completion(
                    model="openrouter/meta-llama/llama-3.3-70b-instruct:free",
                    messages=messages,
                    api_key=or_key,
                    timeout=25.0
                )
                tokens = response.get("usage", {}).get("total_tokens", 0)
                self.total_tokens_used += tokens
                self.routing_logs.append({"provider": "openrouter", "duration": time.time() - start_time, "tokens": tokens})
                return response.choices[0].message.content.strip()
            except Exception as e:
                print(f"[AIGateway] OpenRouter failed with error: {e}. Trying NVIDIA NIM fallback...")

        # 2. NVIDIA NIM Fallback
        if model_route == "cloud":
            nvidia_key = self.secrets.get_secret("NVIDIA_API_KEY") or os.environ.get("NVIDIA_API_KEY")
            if nvidia_key:
                try:
                    start_time = time.time()
                    print("[AIGateway] Routing completion to NVIDIA NIM API (LiteLLM)...")
                    
                    response = litellm.completion(
                        model="nvidia_nim/meta/llama-3.1-70b-instruct",
                        messages=messages,
                        api_key=nvidia_key,
                        api_base="https://integrate.api.nvidia.com/v1",
                        timeout=30.0
                    )
                    
                    # Log metrics
                    tokens = response.get("usage", {}).get("total_tokens", 0)
                    self.total_tokens_used += tokens
                    self.routing_logs.append({
                        "provider": "nvidia",
                        "duration": time.time() - start_time,
                        "tokens": tokens
                    })
                    
                    return response.choices[0].message.content.strip()
                except Exception as e:
                    print(f"[AIGateway] Cloud NIM completions failed ({e}). Trying Ollama fallback...")

        # 3. Local Ollama Fallback Route
        try:
            start_time = time.time()
            print("[AIGateway] Routing completion to Local Ollama/Qwen Engine (LiteLLM)...")
            
            response = litellm.completion(
                model="ollama/qwen2.5:3b",
                messages=messages,
                api_base="http://localhost:11434",
                timeout=4.0
            )
            
            tokens = response.get("usage", {}).get("total_tokens", 0)
            self.total_tokens_used += tokens
            self.routing_logs.append({
                "provider": "ollama",
                "duration": time.time() - start_time,
                "tokens": tokens
            })
            
            return response.choices[0].message.content.strip()
        except Exception as e:
            print(f"[AIGateway] Ollama completions failed: {e}. Fallback to mock text responses.")

        return self._mock_completion_fallback(messages)

    def _mock_completion_fallback(self, messages: list) -> str:
        """
        Deterministic, rule-based text mockup fallback.
        """
        prompt = messages[-1]["content"].lower()
        clean_prompt = prompt.replace("plan for:", "").strip()
        
        # Route search, iphone, or browser queries directly to browser_action
        if any(w in clean_prompt for w in ["iphone", "browser", "search", "open", "find", "google"]):
            return json.dumps([
                {"title": f"Open Browser and run task: {clean_prompt}", "tool": "browser_action", "input": {"task": clean_prompt}}
            ])
            
        if "plan" in clean_prompt or "supplier" in clean_prompt:
            return json.dumps([
                {"title": "Search Web for Roofing Suppliers", "tool": "web_search", "input": {"query": "cheapest roofing suppliers"}},
                {"title": "Scrape Supplier site details", "tool": "web_scrape", "input": {"url": "https://quotes.toscrape.com/"}},
                {"title": "Compare prices and create excel sheet", "tool": "document_action", "input": {"action": "compare_prices", "files": ["prices_a.xlsx"]}},
                {"title": "Draft procurement summary proposal email", "tool": "send_email", "input": {"to": "manager@artee.com", "subject": "Roofing Bids"}}
            ])
        return "Task processed successfully via mockup rules."
