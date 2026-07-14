import os
import sys
import importlib.util
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from app.auth import router as auth_router

app = FastAPI(
    title="RWorld Intelligence Platform API Gateway",
    version="1.0.0",
    description="Plugin-based modular API gateway orchestrating Artee ERP, AI Shipping, Fabric Scraper, and RoofIQ AI"
)

# CORS middleware configuration
# FRONTEND_URL can be set as an environment variable in production
FRONTEND_URL = os.environ.get("FRONTEND_URL", "https://rahultejdeepa.dpdns.org")

app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://localhost:3000",
        "http://127.0.0.1:3000",
        FRONTEND_URL,
        "https://rahultejdeepa.dpdns.org",
        "https://rworld-intelligence.vercel.app",
    ],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Register base authentication routes
app.include_router(auth_router)

# Dynamic Plugin Loader
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
MODULES_DIR = os.path.join(BASE_DIR, "modules")

def load_dynamic_routers():
    if not os.path.exists(MODULES_DIR):
        print(f"Modules directory not found at: {MODULES_DIR}")
        return
        
    for item in os.listdir(MODULES_DIR):
        item_path = os.path.join(MODULES_DIR, item)
        if os.path.isdir(item_path):
            router_file = os.path.join(item_path, "router.py")
            if os.path.exists(router_file):
                module_name = f"modules_{item.replace('-', '_')}"
                try:
                    # Dynamically load the module using importlib
                    spec = importlib.util.spec_from_file_location(module_name, router_file)
                    module = importlib.util.module_from_spec(spec)
                    sys.modules[module_name] = module
                    spec.loader.exec_module(module)
                    
                    # Register router if it exists in the module
                    if hasattr(module, "router"):
                        app.include_router(module.router)
                        print(f"Successfully registered module router: [green]{item}[/green]")
                except Exception as e:
                    print(f"Failed to load module router '{item}': {e}", file=sys.stderr)

# Load plugins on startup
load_dynamic_routers()

@app.get("/")
def index():
    return {
        "platform": "RWorld Intelligence Platform",
        "status": "Online",
        "api_gateway": "Active",
        "dynamic_modules_loaded": [
            name for name in os.listdir(MODULES_DIR) 
            if os.path.isdir(os.path.join(MODULES_DIR, name)) and os.path.exists(os.path.join(MODULES_DIR, name, "router.py"))
        ]
    }
