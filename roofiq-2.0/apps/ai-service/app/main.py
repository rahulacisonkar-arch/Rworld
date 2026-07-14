from fastapi import FastAPI
from app.api.endpoints import router as api_router

app = FastAPI(
    title="Shekhar RoofIQ AI Engine Service",
    description="FastAPI service hosting YOLOv11 and SAM 2 model execution routines.",
    version="2.0.0"
)

# Bind the api router
app.include_router(api_router, prefix="/api/v1")

@app.get("/health")
def health_check():
    return {"status": "healthy", "service": "roofiq-ai-service"}

@app.get("/ready")
def readiness_check():
    # Can verify models loaded successfully
    return {"status": "ready", "models_loaded": True}

@app.get("/live")
def liveness_check():
    return {"status": "live"}

@app.get("/version")
def version_check():
    return {"version": "2.0.0", "service": "roofiq-ai-service"}
