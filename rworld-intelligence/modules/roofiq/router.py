from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from app.db import get_db_direct
from app.auth import get_current_user
import json

router = APIRouter(prefix="/api/roofiq", tags=["RoofIQ AI"])

# Schemas
class ProjectCreate(BaseModel):
    project_name: str
    address: str
    roof_area: float # sq ft

class BOMCalculateRequest(BaseModel):
    roof_area: float
    shingle_type: str = "architectural" # 'architectural', '3-tab', 'metal'

# Routes
@router.get("/projects")
def get_projects(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM roofiq_projects ORDER BY created_at DESC")
    projects = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return projects

@router.post("/projects")
def create_project(project: ProjectCreate, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    # Calculate initial estimation (BOM)
    # 1 bundle of shingles covers ~33.3 sq ft. 3 bundles per square (100 sq ft).
    bundles_needed = int(project.roof_area / 33.3) + 1
    price_per_bundle = 35.0 # average shingle price
    total_price = bundles_needed * price_per_bundle
    
    bom_data = {
        "shingles_bundles": bundles_needed,
        "underlayment_rolls": int(project.roof_area / 1000) + 1,
        "nails_boxes": int(project.roof_area / 1000) + 1,
        "labor_hours_estimate": round(project.roof_area / 100, 1)
    }
    
    bom_json = json.dumps(bom_data)
    
    cursor.execute("""
        INSERT INTO roofiq_projects (project_name, address, roof_area, estimated_bom, total_price)
        VALUES (?, ?, ?, ?, ?)
    """, (project.project_name, project.address, project.roof_area, bom_json, total_price))
    conn.commit()
    conn.close()
    return {"message": "Project created with initial estimates", "total_price": total_price}

@router.post("/calculate-bom")
def calculate_bom(req: BOMCalculateRequest, current_user: dict = Depends(get_current_user)):
    bundles_needed = int(req.roof_area / 33.3) + 1
    shingle_prices = {
        "architectural": 38.0,
        "3-tab": 28.0,
        "metal": 120.0
    }
    price = shingle_prices.get(req.shingle_type.lower(), 35.0)
    total_price = bundles_needed * price
    
    return {
        "roof_area": req.roof_area,
        "shingle_type": req.shingle_type,
        "bundles_needed": bundles_needed,
        "estimated_material_cost": total_price,
        "suggested_solar_panels": int(req.roof_area * 0.05) # simple thumb rule calculation
    }
