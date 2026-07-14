from fastapi import APIRouter, Depends, HTTPException, status, UploadFile, File, Form
from pydantic import BaseModel
from typing import Optional, List
import sqlite3
from app.db import get_db_direct
from app.auth import get_current_user
from datetime import datetime

router = APIRouter(prefix="/api/utility", tags=["Utility Management"])

# Schemas
class ConnectionCreate(BaseModel):
    store_id: int
    utility_type: str # 'Telephone', 'Internet', 'Gas', 'Electricity', 'Sewer', 'Water'
    provider_name: str
    account_number: str
    notes: Optional[str] = None

class BillCreateRequest(BaseModel):
    connection_id: int
    store_id: int
    statement_date: str
    due_date: str
    amount: float
    notes: Optional[str] = None

# Routes
@router.get("/connections")
def get_connections(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT uc.*, s.store_name, s.city 
        FROM utility_connections uc 
        JOIN stores s ON uc.store_id = s.id 
        ORDER BY uc.created_at DESC
    """)
    connections = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return connections

@router.post("/connections")
def create_connection(conn_req: ConnectionCreate, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    try:
        cursor.execute("""
            INSERT INTO utility_connections (store_id, utility_type, provider_name, account_number, notes)
            VALUES (?, ?, ?, ?, ?)
        """, (conn_req.store_id, conn_req.utility_type, conn_req.provider_name, conn_req.account_number, conn_req.notes))
        conn.commit()
    except sqlite3.IntegrityError:
        conn.close()
        raise HTTPException(status_code=400, detail="A connection of this utility type already exists for the selected store.")
    except Exception as e:
        conn.close()
        raise HTTPException(status_code=500, detail=str(e))
    conn.close()
    return {"message": "Utility connection successfully registered."}

@router.get("/bills")
def get_bills(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT b.*, uc.utility_type, uc.provider_name, uc.account_number, s.store_name, s.city 
        FROM bills b 
        JOIN utility_connections uc ON b.connection_id = uc.id 
        JOIN stores s ON b.store_id = s.id 
        ORDER BY b.due_date DESC
    """)
    bills = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return bills

@router.post("/bills/upload")
def upload_bill(
    connection_id: int = Form(...),
    store_id: int = Form(...),
    statement_date: str = Form(...),
    due_date: str = Form(...),
    amount: float = Form(...),
    notes: Optional[str] = Form(None),
    file: UploadFile = File(...),
    current_user: dict = Depends(get_current_user)
):
    conn = get_db_direct()
    cursor = conn.cursor()
    file_path = f"/uploads/bills/{file.filename}"
    try:
        cursor.execute("""
            INSERT INTO bills (connection_id, store_id, statement_date, due_date, amount, bill_file_path, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)
        """, (connection_id, store_id, statement_date, due_date, amount, file_path, notes))
        conn.commit()
    except Exception as e:
        conn.close()
        raise HTTPException(status_code=500, detail=str(e))
    conn.close()
    return {"message": "Utility bill invoice uploaded successfully."}

@router.post("/bills/{id}/pay")
def pay_bill(id: int, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("SELECT id FROM bills WHERE id = ?", (id,))
    if not cursor.fetchone():
        conn.close()
        raise HTTPException(status_code=404, detail="Bill not found")
    try:
        paid_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        cursor.execute("""
            UPDATE bills 
            SET status = 'Paid', paid_at = ?, transaction_ref = ?
            WHERE id = ?
        """, (paid_at, f"TXN-REF-{id:05d}", id))
        conn.commit()
    except Exception as e:
        conn.close()
        raise HTTPException(status_code=500, detail=str(e))
    conn.close()
    return {"message": "Bill marked as Paid."}

@router.get("/reports/yoy")
def get_yoy_report(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("SELECT id FROM stores WHERE store_code = '78'")
    row = cursor.fetchone()
    if not row:
        conn.close()
        return {"electricity": {"2025": [], "2026": []}, "gas": {"2025": [], "2026": []}}
    sid_78 = row[0]
    
    def get_monthly_spend(conn_type: str, year: str) -> list:
        cursor.execute("""
            SELECT statement_date, amount 
            FROM bills b
            JOIN utility_connections uc ON b.connection_id = uc.id
            WHERE b.store_id = ? AND uc.utility_type = ? AND b.statement_date LIKE ?
            ORDER BY b.statement_date
        """, (sid_78, conn_type, f"{year}-%"))
        spend = [0.0] * 12
        for s_date, amt in cursor.fetchall():
            try:
                m_idx = int(s_date.split("-")[1]) - 1
                if 0 <= m_idx < 12:
                    spend[m_idx] = amt
            except Exception:
                pass
        return spend

    elec_2025 = get_monthly_spend("Electricity", "2025")
    elec_2026 = get_monthly_spend("Electricity", "2026")
    gas_2025 = get_monthly_spend("Gas", "2025")
    gas_2026 = get_monthly_spend("Gas", "2026")
    
    conn.close()
    return {
        "electricity": {
            "2025": elec_2025,
            "2026": elec_2026
        },
        "gas": {
            "2025": gas_2025,
            "2026": gas_2026
        }
    }
