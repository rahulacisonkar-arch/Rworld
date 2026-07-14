from fastapi import APIRouter, Depends, HTTPException, status, UploadFile, File, Form
from pydantic import BaseModel
from typing import Optional, List
import sqlite3
from app.db import get_db_direct
from app.auth import get_current_user
import json
from datetime import datetime

router = APIRouter(prefix="/api/attendance", tags=["Attendance Operations"])

# Schemas
class EmployeeCreate(BaseModel):
    store_id: int
    name: str
    email: str
    phone: str
    designation: str
    hourly_rate: float
    employment_type: str = "Full-time"
    hire_date: Optional[str] = None
    salary_grade: str = "Grade A"

class ManualLogRequest(BaseModel):
    employee_id: int
    store_id: int
    date: str
    login_time: str
    logout_time: Optional[str] = None
    status: str = "Checked In"
    log_type: str = "Regular"

# Routes
@router.get("/stores")
def get_stores(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM stores ORDER BY id")
    stores = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return stores

@router.get("/employees")
def get_employees(store_id: Optional[int] = None, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    if store_id:
        cursor.execute("""
            SELECT e.*, s.store_name, s.city 
            FROM employees e 
            JOIN stores s ON e.store_id = s.id 
            WHERE e.store_id = ? AND e.deleted_at IS NULL
        """, (store_id,))
    else:
        cursor.execute("""
            SELECT e.*, s.store_name, s.city 
            FROM employees e 
            JOIN stores s ON e.store_id = s.id 
            WHERE e.deleted_at IS NULL
        """)
    employees = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return employees

@router.post("/employees")
def create_employee(emp: EmployeeCreate, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    try:
        cursor.execute("""
            INSERT INTO employees (store_id, name, email, phone, designation, hourly_rate, employment_type, hire_date, salary_grade)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (emp.store_id, emp.name, emp.email, emp.phone, emp.designation, emp.hourly_rate, emp.employment_type, emp.hire_date or datetime.now().strftime('%Y-%m-%d'), emp.salary_grade))
        conn.commit()
    except Exception as e:
        conn.close()
        raise HTTPException(status_code=500, detail=str(e))
    conn.close()
    return {"message": f"Employee '{emp.name}' successfully registered."}

@router.get("/logs")
def get_logs(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT l.*, e.name as employee_name, s.store_name, s.city 
        FROM attendance_logs l 
        JOIN employees e ON l.employee_id = e.id 
        JOIN stores s ON l.store_id = s.id 
        ORDER BY l.date DESC, l.login_time DESC
    """)
    logs = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return logs

@router.post("/logs/manual")
def create_manual_log(log: ManualLogRequest, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    # Calculate worked hours if logout_time is provided
    calc_hours = 0.0
    calc_ot = 0.0
    if log.logout_time:
        try:
            t1 = datetime.strptime(log.login_time, "%H:%M")
            t2 = datetime.strptime(log.logout_time, "%H:%M")
            diff = (t2 - t1).total_seconds() / 3600.0
            calc_hours = round(max(0.0, diff), 2)
            if calc_hours > 8.0:
                calc_ot = round(calc_hours - 8.0, 2)
                calc_hours = 8.0
        except Exception:
            pass
            
    try:
        cursor.execute("""
            INSERT INTO attendance_logs (employee_id, store_id, login_time, logout_time, date, status, log_type, calculated_hours, calculated_overtime)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, (log.employee_id, log.store_id, log.login_time, log.logout_time, log.date, log.status, log.log_type, calc_hours, calc_ot))
        conn.commit()
    except Exception as e:
        conn.close()
        raise HTTPException(status_code=500, detail=str(e))
    conn.close()
    return {"message": "Attendance log registered successfully."}

@router.post("/ocr")
async def process_timesheet_ocr(file: UploadFile = File(...), current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("SELECT id, name, hourly_rate FROM employees WHERE deleted_at IS NULL")
    employees = [dict(row) for row in cursor.fetchall()]
    conn.close()
    
    if not employees:
        raise HTTPException(status_code=400, detail="No active employees in database to match.")
        
    filename = file.filename
    mock_extracted_text = (
        f"ARTEE RETAIL TIMESHEET - WEEK ENDING 2026-07-12\n"
        f"-----------------------------------------------\n"
        f"John Doe       - 2026-07-06 - IN: 09:00 - OUT: 17:00 (8.00 hrs)\n"
        f"John Doe       - 2026-07-07 - IN: 08:45 - OUT: 17:15 (8.50 hrs)\n"
        f"Jane Smith     - 2026-07-06 - IN: 09:15 - OUT: 15:30 (6.25 hrs)\n"
        f"Bob Johnson    - 2026-07-08 - IN: 10:00 - OUT: 18:30 (8.50 hrs)\n"
    )
    
    mock_logs = []
    matching_info = [
        ("John Doe", "2026-07-06", "09:00", "17:00", 8.00, 0.00),
        ("John Doe", "2026-07-07", "08:45", "17:15", 8.00, 0.50),
        ("Jane Smith", "2026-07-06", "09:15", "15:30", 6.25, 0.00),
        ("Bob Johnson", "2026-07-08", "10:00", "18:30", 8.00, 0.50)
    ]
    
    for name, date, login, logout, reg, ot in matching_info:
        emp_match = next((e for e in employees if name.lower() in e["name"].lower()), None)
        if emp_match:
            mock_logs.append({
                "employee_id": emp_match["id"],
                "employee_name": emp_match["name"],
                "date": date,
                "login_time": login,
                "logout_time": logout,
                "calculated_hours": reg,
                "calculated_overtime": ot
            })
            
    return {
        "success": True,
        "file_name": filename,
        "raw_text": mock_extracted_text,
        "parsed_logs": mock_logs
    }

@router.get("/payroll")
def get_payroll_summary(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    cursor.execute("""
        SELECT 
            e.id as employee_id,
            e.name as employee_name,
            e.designation,
            e.hourly_rate,
            s.store_name,
            s.city,
            SUM(l.calculated_hours) as total_regular_hours,
            SUM(l.calculated_overtime) as total_overtime_hours
        FROM employees e
        JOIN stores s ON e.store_id = s.id
        LEFT JOIN attendance_logs l ON e.id = l.employee_id
        WHERE e.deleted_at IS NULL
        GROUP BY e.id
    """)
    
    rows = cursor.fetchall()
    payroll = []
    for row in rows:
        d = dict(row)
        reg_hours = d["total_regular_hours"] or 0.0
        ot_hours = d["total_overtime_hours"] or 0.0
        rate = d["hourly_rate"]
        
        est_pay = (reg_hours * rate) + (ot_hours * rate * 1.5)
        
        d["total_regular_hours"] = round(reg_hours, 2)
        d["total_overtime_hours"] = round(ot_hours, 2)
        d["estimated_payout"] = round(est_pay, 2)
        payroll.append(d)
        
    conn.close()
    return payroll
