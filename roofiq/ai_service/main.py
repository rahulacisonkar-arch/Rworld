"""
ROOFIQ AI — FastAPI Microservice
Provides roof analysis endpoints called by the PHP frontend.
Run: uvicorn main:app --host 0.0.0.0 --port 5001 --reload
"""
import logging
import asyncio
import time
from pathlib import Path
from typing import Optional

from fastapi import FastAPI, HTTPException, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from image_processor import (
    base64_to_numpy, numpy_to_base64, fetch_tile_image,
    preprocess_roof_image, detect_vegetation, detect_water_ponding,
    generate_damage_heatmap
)
from roof_detector import YOLORoofDetector, SAM2RoofSegmenter, calculate_condition_score
from geo_processor import (
    calculate_roof_measurements, split_roof_into_sections, calculate_polygon_area_sqft
)

# ---- Logging ----
logging.basicConfig(level=logging.INFO, format='%(asctime)s %(levelname)s %(message)s')
logger = logging.getLogger(__name__)

# ---- FastAPI App ----
app = FastAPI(
    title="SHEKHAR ROOFIQ AI Service",
    description="AI-powered roof analysis: YOLO damage detection, SAM2 segmentation, GeoPandas measurements",
    version="1.0.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

# ---- Global model instances (loaded once at startup) ----
yolo_detector: Optional[YOLORoofDetector] = None
sam2_segmenter: Optional[SAM2RoofSegmenter] = None


@app.on_event("startup")
async def startup_event():
    global yolo_detector, sam2_segmenter
    logger.info("Loading AI models...")
    # Load in executor to avoid blocking event loop
    loop = asyncio.get_event_loop()
    yolo_detector  = await loop.run_in_executor(None, YOLORoofDetector)
    sam2_segmenter = await loop.run_in_executor(None, SAM2RoofSegmenter)
    logger.info("AI models ready ✓")


# ============================================================
# Request / Response Models
# ============================================================

class AnalyzeRequest(BaseModel):
    lat:             float  = Field(..., description="Property latitude")
    lng:             float  = Field(..., description="Property longitude")
    address:         str    = Field(default="", description="Full property address")
    image_b64:       Optional[str] = Field(None, description="Base64 aerial image (optional)")
    footprint:       Optional[dict] = Field(None, description="GeoJSON polygon of building footprint")
    pitch_deg:       float  = Field(default=22.0, description="Estimated roof pitch in degrees")
    zoom_level:      int    = Field(default=19, description="Satellite zoom level for tile fetch")


class AnalyzeResponse(BaseModel):
    success:        bool
    address:        str
    lat:            float
    lng:            float
    condition:      dict
    measurements:   dict
    detections:     list
    sections:       list
    segmentation:   dict
    solar_estimate: dict
    processing_ms:  int


class HealthResponse(BaseModel):
    status:      str
    version:     str
    models:      dict
    geopandas:   bool


# ============================================================
# Endpoints
# ============================================================

@app.get("/health", response_model=HealthResponse)
async def health_check():
    """Health check — returns model availability status."""
    try:
        import geopandas
        has_geopandas = True
    except ImportError:
        has_geopandas = False

    return {
        "status":    "ok",
        "version":   "1.0.0",
        "models": {
            "yolo": yolo_detector is not None and yolo_detector.model is not None,
            "sam2": sam2_segmenter is not None and sam2_segmenter.predictor is not None,
        },
        "geopandas": has_geopandas,
    }


@app.post("/analyze", response_model=AnalyzeResponse)
async def analyze_roof(request: AnalyzeRequest):
    """
    Full roof analysis pipeline:
    1. Fetch or use provided satellite image
    2. OpenCV preprocess
    3. YOLO damage detection
    4. SAM2 roof segmentation
    5. GeoPandas measurements
    6. Return structured report
    """
    t_start = time.time()
    logger.info(f"Analyzing roof at {request.lat}, {request.lng} — {request.address}")

    # --- 1. Get Image ---
    if request.image_b64:
        img = base64_to_numpy(request.image_b64)
    else:
        img = fetch_tile_image(request.lat, request.lng, zoom=request.zoom_level)

    if img is None:
        raise HTTPException(status_code=422, detail="Could not obtain roof imagery")

    # --- 2. Preprocess ---
    processed_img = preprocess_roof_image(img)

    # --- 3. YOLO Detection ---
    detections = yolo_detector.detect(processed_img)
    logger.info(f"YOLO found {len(detections)} damage regions")

    # --- 4. Supplemental OpenCV Analysis ---
    veg_result    = detect_vegetation(processed_img)
    water_result  = detect_water_ponding(processed_img)

    # --- 5. Condition Score ---
    condition = calculate_condition_score(
        detections,
        vegetation_pct=veg_result['vegetation_pct'],
        ponding_pct=water_result['ponding_pct'],
    )

    # --- 6. SAM2 Segmentation ---
    h, w = img.shape[:2]
    segmentation = sam2_segmenter.segment_roof(
        processed_img, request.lat, request.lng,
        point_prompts=[[w // 2, h // 2]]
    )

    # --- 7. Measurements (GeoPandas) ---
    footprint = request.footprint or _estimate_footprint(request.lat, request.lng)
    measurements = calculate_roof_measurements(footprint, request.lat, request.lng, request.pitch_deg)
    sections     = split_roof_into_sections(footprint, request.lat, request.lng)

    # Add condition score to each section
    for sec in sections:
        sec['condition_score'] = max(40, condition['score'] + (hash(sec['name']) % 20) - 10)

    # --- 8. Solar Estimate ---
    import math
    roof_area  = measurements['roof_area_sqft']
    panel_count = round(roof_area * 0.7 / 17.5)
    kwh_year    = round(panel_count * 400 * 1.2)
    solar_estimate = {
        'panel_count':     panel_count,
        'kwh_per_year':    kwh_year,
        'savings_per_year': round(kwh_year * 0.13),
        'co2_offset_lbs':  round(kwh_year * 0.92),
    }

    processing_ms = int((time.time() - t_start) * 1000)
    logger.info(f"Analysis complete in {processing_ms}ms")

    return {
        "success":       True,
        "address":       request.address,
        "lat":           request.lat,
        "lng":           request.lng,
        "condition":     condition,
        "measurements":  measurements,
        "detections":    detections,
        "sections":      sections,
        "segmentation":  {k: v for k, v in segmentation.items() if k != 'mask'},
        "solar_estimate": solar_estimate,
        "processing_ms": processing_ms,
    }


@app.post("/segment")
async def segment_only(request: AnalyzeRequest):
    """SAM2 segmentation only — returns roof polygon."""
    if request.image_b64:
        img = base64_to_numpy(request.image_b64)
    else:
        img = fetch_tile_image(request.lat, request.lng, zoom=request.zoom_level)

    if img is None:
        raise HTTPException(status_code=422, detail="Could not obtain imagery")

    h, w = img.shape[:2]
    result = sam2_segmenter.segment_roof(img, request.lat, request.lng, [[w//2, h//2]])
    return {"success": True, "segmentation": {k: v for k, v in result.items() if k != 'mask'}}


@app.post("/detect")
async def detect_only(request: AnalyzeRequest):
    """YOLO detection only — returns damage bounding boxes."""
    if request.image_b64:
        img = base64_to_numpy(request.image_b64)
    else:
        img = fetch_tile_image(request.lat, request.lng, zoom=request.zoom_level)

    if img is None:
        raise HTTPException(status_code=422, detail="Could not obtain imagery")

    processed = preprocess_roof_image(img)
    detections = yolo_detector.detect(processed)
    condition  = calculate_condition_score(detections)
    return {"success": True, "detections": detections, "condition": condition}


# ============================================================
# Helper
# ============================================================

def _estimate_footprint(lat: float, lng: float, size_m: float = 15.0) -> dict:
    """Generate a bounding-box footprint estimate around a point."""
    deg_per_m_lat = 1 / 111320
    deg_per_m_lng = 1 / (111320 * __import__('math').cos(__import__('math').radians(lat)))
    d_lat = size_m * deg_per_m_lat
    d_lng = size_m * deg_per_m_lng

    return {
        "type": "Polygon",
        "coordinates": [[
            [lng - d_lng, lat - d_lat],
            [lng + d_lng, lat - d_lat],
            [lng + d_lng, lat + d_lat],
            [lng - d_lng, lat + d_lat],
            [lng - d_lng, lat - d_lat],
        ]]
    }


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=5001, reload=True)
