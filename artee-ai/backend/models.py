import datetime
from sqlalchemy import create_engine, Column, Integer, String, Float, Boolean, DateTime, Text, LargeBinary, ForeignKey
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import relationship, sessionmaker

Base = declarative_base()

class Task(Base):
    """
    Task model tracking root goals and subtask execution steps.
    """
    __tablename__ = 'tasks'

    id = Column(Integer, primary_key=True, autoincrement=True)
    title = Column(String(200), nullable=False)
    description = Column(Text, nullable=True)
    parent_id = Column(Integer, ForeignKey('tasks.id', ondelete='CASCADE'), nullable=True)
    status = Column(String(50), default='pending')  # pending, in_progress, completed, failed, blocked
    tool_name = Column(String(100), nullable=True)
    tool_input = Column(Text, nullable=True)
    tool_output = Column(Text, nullable=True)
    error_message = Column(Text, nullable=True)
    sequence_order = Column(Integer, default=0)
    created_at = Column(DateTime, default=datetime.datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.datetime.utcnow, onupdate=datetime.datetime.utcnow)

    # Relationships
    parent = relationship('Task', remote_side=[id], backref='subtasks')

class ApprovalItem(Base):
    """
    Approval queue for high-safety operations (e.g. SMTP emails, QuickBill deletes).
    """
    __tablename__ = 'approval_items'

    id = Column(Integer, primary_key=True, autoincrement=True)
    task_id = Column(Integer, ForeignKey('tasks.id', ondelete='CASCADE'), nullable=False)
    action_type = Column(String(100), nullable=False)  # email_send, file_delete, erp_commit
    payload = Column(Text, nullable=False)
    status = Column(String(50), default='pending')  # pending, approved, rejected
    requested_at = Column(DateTime, default=datetime.datetime.utcnow)
    decided_at = Column(DateTime, nullable=True)
    remarks = Column(Text, nullable=True)

class MemoryItem(Base):
    """
    Long-term conversational and preference cache items.
    """
    __tablename__ = 'memory_items'

    id = Column(Integer, primary_key=True, autoincrement=True)
    category = Column(String(100), nullable=False, index=True)  # vendor, customer, config
    key_name = Column(String(150), nullable=False, index=True)
    value_text = Column(Text, nullable=False)
    created_at = Column(DateTime, default=datetime.datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.datetime.utcnow, onupdate=datetime.datetime.utcnow)

class DocumentEmbedding(Base):
    """
    Semantic knowledge files chunk embeddings.
    """
    __tablename__ = 'document_embeddings'

    id = Column(Integer, primary_key=True, autoincrement=True)
    filename = Column(String(255), nullable=True)
    chunk_index = Column(Integer, default=0)
    text_content = Column(Text, nullable=False)
    embedding_blob = Column(LargeBinary, nullable=False)  # Serialized float32 NumPy array
    created_at = Column(DateTime, default=datetime.datetime.utcnow)

class ERPConfig(Base):
    """
    Vendor mappings, account structures, and ERP connections settings.
    """
    __tablename__ = 'erp_configs'

    id = Column(Integer, primary_key=True, autoincrement=True)
    system_name = Column(String(100), nullable=False)  # quickbill, sap, netsuite
    mapping_key = Column(String(150), nullable=False, index=True)
    mapping_value = Column(Text, nullable=False)
    created_at = Column(DateTime, default=datetime.datetime.utcnow)

class FormMapping(Base):
    """
    Custom field mapping configurations for form automation destinations.
    """
    __tablename__ = 'form_mappings'

    id = Column(Integer, primary_key=True, autoincrement=True)
    target_system = Column(String(100), nullable=False)  # quickbill, sap, oracle, web_form
    field_key = Column(String(150), nullable=False)      # invoice_number, date
    selector = Column(String(255), nullable=True)        # CSS selector, Control ID, or Coordinates
    label = Column(String(150), nullable=True)           # Human-readable UI label
    data_type = Column(String(50), default='string')      # string, float, integer, date, boolean
    default_value = Column(Text, nullable=True)
    is_active = Column(Boolean, default=True)

class VendorTemplate(Base):
    """
    Vendor-specific templates mapping key-word anchors to structured fields.
    """
    __tablename__ = 'vendor_templates'

    id = Column(Integer, primary_key=True, autoincrement=True)
    vendor_name = Column(String(150), nullable=False, unique=True)
    anchor_keywords = Column(Text, nullable=False)       # comma-separated list of match terms
    mapping_json = Column(Text, nullable=False)          # Serialized JSON of field extractor rules
    corrections_count = Column(Integer, default=0)
    created_at = Column(DateTime, default=datetime.datetime.utcnow)

class DocumentVersion(Base):
    """
    Versioning audit trail for uploaded documents.
    """
    __tablename__ = 'document_versions'

    id = Column(Integer, primary_key=True, autoincrement=True)
    document_name = Column(String(255), nullable=False)
    original_filepath = Column(String(255), nullable=False)
    content_type = Column(String(100), nullable=True)     # pdf, png, docx
    document_type = Column(String(100), default='unknown') # invoice, PO, GST form
    extracted_json = Column(Text, nullable=True)          # parsed entity values
    version_num = Column(Integer, default=1)
    revised_at = Column(DateTime, default=datetime.datetime.utcnow)
    revised_by = Column(String(100), default='system')
    status = Column(String(50), default='pending')        # pending, approved, committed, corrected
    checksum = Column(String(64), nullable=True)          # sha256 of file

class ScheduledTask(Base):
    """
    Background queue automation schedulers configuration.
    """
    __tablename__ = 'scheduled_tasks'

    id = Column(Integer, primary_key=True, autoincrement=True)
    name = Column(String(150), nullable=False)
    task_type = Column(String(100), nullable=False)      # folder_monitor, email_poll, nightly_ocr
    target_path = Column(String(255), nullable=True)     # directory path or email folder
    interval_seconds = Column(Integer, default=3600)
    last_run = Column(DateTime, nullable=True)
    next_run = Column(DateTime, nullable=True)
    is_active = Column(Boolean, default=True)

class AuditTrail(Base):
    """
    Step-by-step audit logs of autonomous automation workflows.
    """
    __tablename__ = 'audit_trails'

    id = Column(Integer, primary_key=True, autoincrement=True)
    task_id = Column(Integer, nullable=True)
    step_name = Column(String(150), nullable=False)
    step_status = Column(String(50), default='success')   # success, warning, failure, blocked
    screenshot_path = Column(String(255), nullable=True)
    error_details = Column(Text, nullable=True)
    resume_index = Column(Integer, default=0)             # last successful step index
    executed_at = Column(DateTime, default=datetime.datetime.utcnow)
    duration_sec = Column(Float, default=0.0)

# Database Engine Initialization Helper
def init_db(database_url="sqlite:///artee_ai.db"):
    engine = create_engine(database_url, connect_args={"check_same_thread": False})
    Base.metadata.create_all(engine)
    SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
    return engine, SessionLocal
