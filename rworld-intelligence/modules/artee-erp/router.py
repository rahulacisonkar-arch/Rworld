from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel
from typing import Optional, List
import sqlite3
from app.db import get_db_direct
from app.auth import get_current_user
from datetime import datetime

router = APIRouter(prefix="/api/erp", tags=["Artee ERP"])

# Schemas
class InventoryItem(BaseModel):
    sku: str
    name: str
    category: str
    quantity: int
    price: float
    location: str
    description: str

class CustomerCreate(BaseModel):
    name: str
    email: str
    phone: str
    address: str

class OrderCreate(BaseModel):
    customer_id: int
    total_amount: float
    status: str = "pending"

class SalesReturnCreate(BaseModel):
    order_id: int
    amount: float
    notes: Optional[str] = None

class PurchaseOrderCreate(BaseModel):
    supplier_name: str
    item_sku: str
    quantity: int
    unit_cost: float

class GRNCreate(BaseModel):
    po_id: int
    received_quantity: int
    notes: Optional[str] = None

class ShiftOpenRequest(BaseModel):
    cashier_name: str
    open_balance: float

class ShiftCloseRequest(BaseModel):
    close_balance: float

# Routes - Inventory & Customers
@router.get("/inventory")
def get_inventory(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM erp_inventory")
    items = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return items

@router.post("/inventory")
def add_inventory_item(item: InventoryItem, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    try:
        cursor.execute("""
            INSERT INTO erp_inventory (sku, name, category, quantity, price, location, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        """, (item.sku, item.name, item.category, item.quantity, item.price, item.location, item.description))
        conn.commit()
    except sqlite3.IntegrityError:
        conn.close()
        raise HTTPException(status_code=400, detail="SKU already exists")
    finally:
        conn.close()
    return {"message": f"Inventory item '{item.name}' added successfully"}

@router.get("/customers")
def get_customers(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM erp_customers")
    customers = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return customers

@router.post("/customers")
def create_customer(customer: CustomerCreate, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("""
        INSERT INTO erp_customers (name, email, phone, address)
        VALUES (?, ?, ?, ?)
    """, (customer.name, customer.email, customer.phone, customer.address))
    conn.commit()
    conn.close()
    return {"message": f"Customer '{customer.name}' created"}


# Routes - Sales Orders & Sales Returns
@router.get("/orders")
def get_orders(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT o.*, c.name as customer_name 
        FROM erp_orders o 
        LEFT JOIN erp_customers c ON o.customer_id = c.id
        ORDER BY o.created_at DESC
    """)
    orders = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return orders

@router.post("/orders")
def create_order(order: OrderCreate, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("""
        INSERT INTO erp_orders (customer_id, total_amount, status)
        VALUES (?, ?, ?)
    """, (order.customer_id, order.total_amount, order.status))
    conn.commit()
    conn.close()
    return {"message": "Order created successfully"}

@router.get("/returns")
def get_sales_returns(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT r.*, o.customer_id, c.name as customer_name 
        FROM erp_sales_returns r
        JOIN erp_orders o ON r.order_id = o.id
        JOIN erp_customers c ON o.customer_id = c.id
        ORDER BY r.return_date DESC
    """)
    returns = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return returns

@router.post("/returns")
def create_sales_return(ret: SalesReturnCreate, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    # Check if order exists
    cursor.execute("SELECT id FROM erp_orders WHERE id = ?", (ret.order_id,))
    if not cursor.fetchone():
        conn.close()
        raise HTTPException(status_code=404, detail="Order ID not found")
        
    try:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        cursor.execute("""
            INSERT INTO erp_sales_returns (order_id, return_date, amount, notes)
            VALUES (?, ?, ?, ?)
        """, (ret.order_id, now_str, ret.amount, ret.notes))
        conn.commit()
    except Exception as e:
        conn.close()
        raise HTTPException(status_code=500, detail=str(e))
    conn.close()
    return {"message": "Sales return processed successfully."}


# Routes - Purchasing & GRN
@router.get("/purchase-orders")
def get_purchase_orders(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM erp_purchase_orders ORDER BY created_at DESC")
    orders = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return orders

@router.post("/purchase-orders")
def create_purchase_order(po: PurchaseOrderCreate, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    # 1. Generate PO Number
    cursor.execute("SELECT COUNT(*) FROM erp_purchase_orders")
    count = cursor.fetchone()[0]
    po_number = f"PO-2026-{count + 1:04d}"
    
    try:
        cursor.execute("""
            INSERT INTO erp_purchase_orders (po_number, supplier_name, item_sku, quantity, unit_cost, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        """, (po_number, po.supplier_name, po.item_sku, po.quantity, po.unit_cost))
        conn.commit()
    except Exception as e:
        conn.close()
        raise HTTPException(status_code=500, detail=str(e))
    conn.close()
    return {"message": f"Purchase Order '{po_number}' created successfully."}

@router.get("/grn")
def get_grn_logs(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("""
        SELECT g.*, p.po_number, p.supplier_name, p.item_sku 
        FROM erp_grn g
        JOIN erp_purchase_orders p ON g.po_id = p.id
        ORDER BY g.received_date DESC
    """)
    grns = [dict(row) for row in cursor.fetchall()]
    conn.close()
    return grns

@router.post("/grn")
def create_grn(grn: GRNCreate, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    # Find PO details
    cursor.execute("SELECT id, item_sku, status FROM erp_purchase_orders WHERE id = ?", (grn.po_id,))
    po_row = cursor.fetchone()
    if not po_row:
        conn.close()
        raise HTTPException(status_code=404, detail="Purchase Order not found")
        
    item_sku = po_row[1]
    po_status = po_row[2]
    
    if po_status == "received":
        conn.close()
        raise HTTPException(status_code=400, detail="This PO has already been received.")
        
    try:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        
        # 1. Insert GRN record
        cursor.execute("""
            INSERT INTO erp_grn (po_id, received_date, received_quantity, notes)
            VALUES (?, ?, ?, ?)
        """, (grn.po_id, now_str, grn.received_quantity, grn.notes))
        
        # 2. Update PO status to received
        cursor.execute("UPDATE erp_purchase_orders SET status = 'received' WHERE id = ?", (grn.po_id,))
        
        # 3. Update inventory item quantity count
        cursor.execute("""
            UPDATE erp_inventory 
            SET quantity = quantity + ? 
            WHERE sku = ?
        """, (grn.received_quantity, item_sku))
        
        conn.commit()
    except Exception as e:
        conn.close()
        raise HTTPException(status_code=500, detail=str(e))
    conn.close()
    return {"message": "GRN logged successfully. Inventory quantity incremented."}


# Routes - Cashier Shifts Register
@router.get("/shifts/active")
def get_active_shift(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM erp_shifts WHERE status = 'open' ORDER BY open_time DESC LIMIT 1")
    row = cursor.fetchone()
    conn.close()
    return dict(row) if row else None

@router.post("/shifts/open")
def open_shift(req: ShiftOpenRequest, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    # Check if there is already an open shift
    cursor.execute("SELECT id FROM erp_shifts WHERE status = 'open'")
    if cursor.fetchone():
        conn.close()
        raise HTTPException(status_code=400, detail="A register shift is already open. Close it first.")
        
    try:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        cursor.execute("""
            INSERT INTO erp_shifts (cashier_name, open_time, open_balance, status)
            VALUES (?, ?, ?, 'open')
        """, (req.cashier_name, now_str, req.open_balance))
        conn.commit()
    except Exception as e:
        conn.close()
        raise HTTPException(status_code=500, detail=str(e))
    conn.close()
    return {"message": f"Register shift opened successfully for {req.cashier_name}."}

@router.post("/shifts/close")
def close_shift(req: ShiftCloseRequest, current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    # Find active shift
    cursor.execute("SELECT id FROM erp_shifts WHERE status = 'open'")
    row = cursor.fetchone()
    if not row:
        conn.close()
        raise HTTPException(status_code=404, detail="No active open shift found.")
        
    shift_id = row[0]
    
    try:
        now_str = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        cursor.execute("""
            UPDATE erp_shifts 
            SET close_time = ?, close_balance = ?, status = 'closed'
            WHERE id = ?
        """, (now_str, req.close_balance, shift_id))
        conn.commit()
    except Exception as e:
        conn.close()
        raise HTTPException(status_code=500, detail=str(e))
    conn.close()
    return {"message": "Shift successfully closed and register locked."}


# Routes - Detailed Reports Hub
@router.get("/reports/pl")
def get_profit_and_loss_summary(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    # Calculate P&L metrics
    cursor.execute("SELECT SUM(total_amount) FROM erp_orders WHERE status = 'completed'")
    sales_total = cursor.fetchone()[0] or 0.0
    
    cursor.execute("SELECT SUM(quantity * unit_cost) FROM erp_purchase_orders WHERE status = 'received'")
    cogs_total = cursor.fetchone()[0] or 0.0
    
    cursor.execute("SELECT SUM(amount) FROM erp_sales_returns")
    returns_total = cursor.fetchone()[0] or 0.0
    
    net_sales = sales_total - returns_total
    gross_profit = net_sales - cogs_total
    
    conn.close()
    return {
        "revenue": round(sales_total, 2),
        "returns": round(returns_total, 2),
        "net_sales": round(net_sales, 2),
        "cost_of_goods": round(cogs_total, 2),
        "gross_profit": round(gross_profit, 2)
    }

@router.get("/reports/gst")
def get_gst_tax_report(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    # Calculate CGST/SGST/IGST tax breakdown from completed orders
    # We simulate 18% total tax (9% CGST + 9% SGST) for in-state or 18% IGST for out-of-state
    cursor.execute("SELECT SUM(total_amount) FROM erp_orders WHERE status = 'completed'")
    sales_total = cursor.fetchone()[0] or 0.0
    
    total_tax = sales_total * 0.18
    cgst = total_tax * 0.5
    sgst = total_tax * 0.5
    
    conn.close()
    return {
        "taxable_turnover": round(sales_total, 2),
        "total_tax_liability": round(total_tax, 2),
        "cgst": round(cgst, 2),
        "sgst": round(sgst, 2),
        "igst": 0.00
    }

@router.get("/reports/stock")
def get_stock_report_alerts(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    # Stock valuation
    cursor.execute("SELECT SUM(quantity * price) FROM erp_inventory")
    valuation = cursor.fetchone()[0] or 0.0
    
    # Alerts for items with stock count < 150
    cursor.execute("SELECT * FROM erp_inventory WHERE quantity < 150")
    alerts = [dict(row) for row in cursor.fetchall()]
    
    conn.close()
    return {
        "total_stock_valuation": round(valuation, 2),
        "low_stock_alerts": alerts
    }

@router.get("/reports/daybook")
def get_daybook_records(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    # Compile daybook of recent transactions
    daybook = []
    
    # 1. Customer orders
    cursor.execute("""
        SELECT 'Sales Order' as tx_type, id as tx_id, created_at as date, total_amount as amount, status as details 
        FROM erp_orders 
        ORDER BY created_at DESC LIMIT 20
    """)
    for row in cursor.fetchall():
        daybook.append(dict(row))
        
    # 2. Purchase orders
    cursor.execute("""
        SELECT 'Purchase Order' as tx_type, id as tx_id, created_at as date, (quantity * unit_cost) as amount, status as details 
        FROM erp_purchase_orders 
        ORDER BY created_at DESC LIMIT 20
    """)
    for row in cursor.fetchall():
        daybook.append(dict(row))
        
    # 3. Returns
    cursor.execute("""
        SELECT 'Sales Return' as tx_type, id as tx_id, return_date as date, amount, notes as details 
        FROM erp_sales_returns 
        ORDER BY return_date DESC LIMIT 20
    """)
    for row in cursor.fetchall():
        daybook.append(dict(row))
        
    # Sort all by date descending
    daybook.sort(key=lambda x: x["date"], reverse=True)
    
    conn.close()
    return daybook[:30]

@router.get("/reports/summary")
def get_reports_summary(current_user: dict = Depends(get_current_user)):
    conn = get_db_direct()
    cursor = conn.cursor()
    
    cursor.execute("SELECT COUNT(*) FROM erp_orders")
    total_orders = cursor.fetchone()[0]
    
    cursor.execute("SELECT SUM(total_amount) FROM erp_orders WHERE status = 'completed'")
    revenue_row = cursor.fetchone()[0]
    total_revenue = revenue_row if revenue_row else 0.0
    
    cursor.execute("SELECT SUM(quantity) FROM erp_inventory")
    total_stock_row = cursor.fetchone()[0]
    total_stock = total_stock_row if total_stock_row else 0
    
    conn.close()
    return {
        "total_orders": total_orders,
        "total_revenue": total_revenue,
        "total_stock": total_stock
    }
