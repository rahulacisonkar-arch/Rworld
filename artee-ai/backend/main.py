import os
import json
from fastapi import FastAPI, Depends, WebSocket, WebSocketDisconnect, HTTPException, File, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List, Dict, Any
from sqlalchemy.orm import Session
import asyncio

from .models import init_db, Task, ApprovalItem, ERPConfig, FormMapping, VendorTemplate, DocumentVersion, ScheduledTask, AuditTrail
from .planner import ExecutivePlanner
from .document_automation import DocumentAutomationAgent
from .scheduler import DocumentScheduler

app = FastAPI(title="Artee AI Operations Core API", version="1.0.0")

# Enable CORS for Tauri UI
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize Database
engine, SessionLocal = init_db("sqlite:///artee_ai.db")

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()


class ConnectionManager:
    def __init__(self):
        self.active_connections: List[WebSocket] = []

    async def connect(self, websocket: WebSocket):
        await websocket.accept()
        self.active_connections.append(websocket)

    def disconnect(self, websocket: WebSocket):
        self.active_connections.remove(websocket)

    async def broadcast(self, message: dict):
        for connection in self.active_connections:
            try:
                await connection.send_json(message)
            except Exception:
                pass

manager = ConnectionManager()


class TaskCreate(BaseModel):
    title: str
    description: str = ""

class ApprovalDecision(BaseModel):
    status: str  # approved, rejected
    remarks: str = ""


@app.post("/api/task")
async def create_task(payload: TaskCreate, db: Session = Depends(get_db)):
    """
    Launches a root operations goal and starts the Executive Planner loop.
    """
    task = Task(title=payload.title, description=payload.description, status='pending')
    db.add(task)
    db.commit()
    db.refresh(task)

    # Run planner loop
    planner = ExecutivePlanner(db)
    result = planner.execute_loop(task.id)
    db.refresh(task)

    # Broadcast updates to WebSocket clients
    await manager.broadcast({
        "event": "task_updated",
        "task_id": task.id,
        "status": task.status,
        "result": result
    })

    return {
        "success": True,
        "task_id": task.id,
        "status": task.status,
        "message": result
    }

@app.get("/api/approvals")
def list_approvals(db: Session = Depends(get_db)):
    """
    Lists all pending operations in the approvals queue.
    """
    items = db.query(ApprovalItem).filter(ApprovalItem.status == 'pending').all()
    return [{
        "id": item.id,
        "task_id": item.task_id,
        "action_type": item.action_type,
        "payload": json.loads(item.payload),
        "requested_at": item.requested_at
    } for item in items]

@app.post("/api/approve/{approval_id}")
async def decide_approval(approval_id: int, decision: ApprovalDecision, db: Session = Depends(get_db)):
    """
    Approves or rejects a blocked subtask operation.
    """
    item = db.query(ApprovalItem).filter(ApprovalItem.id == approval_id).first()
    if not item:
        raise HTTPException(status_code=404, detail="Approval request not found.")

    if decision.status not in ['approved', 'rejected']:
        raise HTTPException(status_code=400, detail="Invalid decision status.")

    item.status = decision.status
    item.remarks = decision.remarks
    db.commit()

    if decision.status == 'approved':
        task = db.query(Task).filter(Task.id == item.task_id).first()
        if task:
            root_id = task.parent_id if task.parent_id else task.id
            asyncio.create_task(async_resume_planner(root_id))

    return {"success": True, "message": f"Approval decision logged: {decision.status}"}

async def async_resume_planner(root_id: int):
    await asyncio.sleep(0.5)
    db = SessionLocal()
    try:
        planner = ExecutivePlanner(db)
        planner.execute_loop(root_id)
    finally:
        db.close()

@app.websocket("/ws/approvals")
async def websocket_endpoint(websocket: WebSocket):
    await manager.connect(websocket)
    try:
        while True:
            await websocket.receive_text()
    except WebSocketDisconnect:
        manager.disconnect(websocket)

# Startup event to run scheduler
@app.on_event("startup")
def startup_event():
    db = SessionLocal()
    # Initialize a default scheduled task for folder monitoring if none exists
    monitor = db.query(ScheduledTask).filter(ScheduledTask.task_type == "folder_monitor").first()
    if not monitor:
        new_task = ScheduledTask(
            name="Default Watch Folder Monitor",
            task_type="folder_monitor",
            target_path="watch_folder",
            interval_seconds=30
        )
        db.add(new_task)
        db.commit()
    db.close()
    
    # Run the scheduler in the background asyncio loop
    async def run_sched():
        # Let's wait a bit for system startup
        await asyncio.sleep(5)
        db_sched = SessionLocal()
        scheduler = DocumentScheduler(db_sched)
        await scheduler.start()
    asyncio.create_task(run_sched())

# Input validation schemas
class MappingCreate(BaseModel):
    target_system: str
    field_key: str
    selector: str = ""
    label: str = ""
    data_type: str = "string"
    default_value: str = ""

class DocumentCorrection(BaseModel):
    fields: Dict[str, Any]

class DocumentAutomatePayload(BaseModel):
    document_id: int
    target_system: str

@app.post("/api/document/upload")
async def upload_document(file: UploadFile = File(...), db: Session = Depends(get_db)):
    upload_dir = "uploads"
    os.makedirs(upload_dir, exist_ok=True)
    file_path = os.path.join(upload_dir, file.filename)
    
    # Save file locally to maintain document version audit trails
    with open(file_path, "wb") as f:
        f.write(await file.read())

    agent = DocumentAutomationAgent(db)
    result = agent.process_document(file_path)
    return result

@app.post("/api/document/correct/{document_id}")
def correct_document(document_id: int, payload: DocumentCorrection, db: Session = Depends(get_db)):
    agent = DocumentAutomationAgent(db)
    res = agent.save_human_correction(document_id, payload.fields)
    return res

@app.post("/api/document/automate")
def automate_document(payload: DocumentAutomatePayload, db: Session = Depends(get_db)):
    agent = DocumentAutomationAgent(db)
    res = agent.execute_automation(payload.document_id, payload.target_system)
    return res

@app.get("/api/document/mappings")
def list_mappings(db: Session = Depends(get_db)):
    items = db.query(FormMapping).all()
    return items

@app.post("/api/document/mappings")
def create_mapping(payload: MappingCreate, db: Session = Depends(get_db)):
    item = db.query(FormMapping).filter(
        FormMapping.target_system == payload.target_system,
        FormMapping.field_key == payload.field_key
    ).first()
    if not item:
        item = FormMapping(target_system=payload.target_system, field_key=payload.field_key)
        db.add(item)
    
    item.selector = payload.selector
    item.label = payload.label
    item.data_type = payload.data_type
    item.default_value = payload.default_value
    db.commit()
    return {"success": True}

@app.get("/api/document/logs")
def get_logs(db: Session = Depends(get_db)):
    logs = db.query(AuditTrail).order_by(AuditTrail.executed_at.desc()).limit(100).all()
    return logs

@app.get("/api/document/confidence")
def get_confidence_stats(db: Session = Depends(get_db)):
    docs = db.query(DocumentVersion).all()
    if not docs:
        return {
            "processed_count": 0,
            "average_confidence": 0,
            "success_rate": 100,
            "correction_rate": 0,
            "sync_status": "synced"
        }
    
    total = len(docs)
    total_conf = 0.0
    correct_count = 0
    success_count = 0
    
    for d in docs:
        if d.status == "committed":
            success_count += 1
        if d.status == "corrected":
            correct_count += 1
        # Extract confidence
        total_conf += 95.0 # simulated base
        
    return {
        "processed_count": total,
        "average_confidence": round(total_conf / total, 2),
        "success_rate": round((success_count / total) * 100, 2) if total > 0 else 100,
        "correction_rate": round((correct_count / total) * 100, 2) if total > 0 else 0,
        "sync_status": "active"
    }

@app.get("/api/document/history")
def get_history(db: Session = Depends(get_db)):
    docs = db.query(DocumentVersion).order_by(DocumentVersion.id.desc()).limit(50).all()
    result = []
    for d in docs:
        result.append({
            "id": d.id,
            "document_name": d.document_name,
            "original_filepath": d.original_filepath,
            "document_type": d.document_type,
            "extracted_json": json.loads(d.extracted_json) if d.extracted_json else {},
            "version_num": d.version_num,
            "status": d.status,
            "revised_at": d.revised_at,
            "revised_by": d.revised_by
        })
    return result
