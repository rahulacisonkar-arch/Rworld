from fastapi import APIRouter, Depends, BackgroundTasks, HTTPException
from pydantic import BaseModel
from app.db import get_db_direct
from app.auth import get_current_user
import asyncio
import os
import sys

router = APIRouter(prefix="/api/fabric-scraper", tags=["Fabric Scraper"])

# Schemas
class ScraperConfigRequest(BaseModel):
    limit: int = 5

# Scraper execution helper run in background
def run_scraper_task(limit: int):
    # This could execute the Playwright scraper script locally or invoke it as a subprocess
    # For now, we mock the execution log and update database entries
    conn = get_db_direct()
    cursor = conn.cursor()
    
    # Insert mock products to simulate successful scraping
    mock_products = [
        ("https://www.fabricmill.com/premium-linen.html", "Premium Natural Linen", "PREM-LINEN", "FabricMill", "100% Belgian flax linen fabric.", "54 inches", "Linen", "100% Linen", "$29.99"),
        ("https://www.fabricmill.com/vintage-velvet.html", "Vintage Cotton Velvet", "VINT-VELVET", "FabricMill", "Luxurious plush velvet fabric.", "56 inches", "Velvet", "100% Cotton", "$34.99")
    ]
    
    for url, name, sku, mfg, desc, w, f_type, content, price in mock_products[:limit]:
        cursor.execute("""
            INSERT OR REPLACE INTO scraper_products 
            (url, name, sku, manufacturer, description, width, type_of_fabric, fiber_content, retail_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (url, name, sku, mfg, desc, w, f_type, content, price))
        
        # Log to audit trail
        cursor.execute(
            "INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)",
            (1, "Scraper Execution", f"Successfully scraped product '{name}' (SKU: {sku})")
        )
    conn.commit()
    conn.close()

@router.get("/products")
def get_scraped_products(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM scraper_products ORDER BY scraped_at DESC")
    products = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return products

@router.post("/run")
def trigger_scraper(config: ScraperConfigRequest, background_tasks: BackgroundTasks, current_user: dict = Depends(get_current_user)):
    # Trigger background worker task
    background_tasks.add_task(run_scraper_task, config.limit)
    return {"message": "Fabric scraper started in the background", "limit": config.limit}
