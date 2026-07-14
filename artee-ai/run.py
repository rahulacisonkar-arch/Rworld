"""
RWorld AI Backend Launcher
Injects artee-ai as first sys.path entry BEFORE uvicorn resolves modules,
bypassing the openhands-ai 'backend' namespace conflict in site-packages.
"""
import sys
import os

# Ensure artee-ai root is first in path to override site-packages 'backend' namespace
artee_root = os.path.dirname(os.path.abspath(__file__))
if artee_root not in sys.path:
    sys.path.insert(0, artee_root)

import uvicorn

if __name__ == "__main__":
    uvicorn.run(
        "backend.main:app",
        host="localhost",
        port=8000,
        reload=False
    )
