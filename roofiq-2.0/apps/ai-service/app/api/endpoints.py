from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, HttpUrl
from typing import List, Optional
from app.core.ai_pipeline import RoofEstimationPipeline

router = APIRouter()

# Instantiate the pipeline orchestrator with dummy model checkpoints
pipeline = RoofEstimationPipeline(
    sam_weights="checkpoints/sam2_hiera_large.pt",
    yolo_weights="checkpoints/yolov11_obb.pt"
)

class InferenceRequest(BaseModel):
    image_url: Optional[str] = None
    bbox: Optional[List[float]] = None

class OcrRequest(BaseModel):
    document_url: str

@router.post("/inference")
def run_inference(request: InferenceRequest):
    try:
        # Runs SAM 2 segmentation and YOLOv11 detectors
        result = pipeline.run_inference(b"", request.bbox or [0.0, 0.0, 0.0, 0.0])
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/ocr")
def run_ocr(request: OcrRequest):
    try:
        # Mock OCR shingle count scanner result
        return {
            "success": True,
            "text": "Detected 30 squares of laminated shingles and 4 skylight fixtures."
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
