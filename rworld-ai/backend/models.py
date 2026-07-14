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
    Approval queue for high-safety actions (e.g., sending emails, deleting files).
    """
    __tablename__ = 'approval_items'

    id = Column(Integer, primary_key=True, autoincrement=True)
    task_id = Column(Integer, ForeignKey('tasks.id', ondelete='CASCADE'), nullable=False)
    action_type = Column(String(100), nullable=False)  # email_send, file_delete, db_overwrite
    payload = Column(Text, nullable=False)  # JSON formatted parameters for target action
    status = Column(String(50), default='pending')  # pending, approved, rejected
    requested_at = Column(DateTime, default=datetime.datetime.utcnow)
    decided_at = Column(DateTime, nullable=True)
    remarks = Column(Text, nullable=True)

class MemoryItem(Base):
    """
    Long-term and short-term semantic memory elements.
    """
    __tablename__ = 'memory_items'

    id = Column(Integer, primary_key=True, autoincrement=True)
    category = Column(String(100), nullable=False)  # vendor, preference, template, conversation
    key_name = Column(String(150), nullable=False, index=True)
    value_text = Column(Text, nullable=False)
    created_at = Column(DateTime, default=datetime.datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.datetime.utcnow, onupdate=datetime.datetime.utcnow)

class DocumentEmbedding(Base):
    """
    Knowledge Base document chunks and local binary vector embeddings.
    """
    __tablename__ = 'document_embeddings'

    id = Column(Integer, primary_key=True, autoincrement=True)
    filename = Column(String(255), nullable=True)
    chunk_index = Column(Integer, default=0)
    text_content = Column(Text, nullable=False)
    embedding_blob = Column(LargeBinary, nullable=False)  # Serialized float32 NumPy array
    created_at = Column(DateTime, default=datetime.datetime.utcnow)

class DocumentMapping(Base):
    """
    Field selectors taught by the user for dynamic form mapping on web sites / ERP.
    """
    __tablename__ = 'document_mappings'

    id = Column(Integer, primary_key=True, autoincrement=True)
    target_system = Column(String(100), nullable=False)
    field_key = Column(String(100), nullable=False)
    selector = Column(String(255), nullable=False)
    label = Column(String(255), nullable=True)

class DocumentHistory(Base):
    """
    History of uploaded files, OCR confidences, explainability metadata, and extracted data.
    """
    __tablename__ = 'document_histories'

    id = Column(Integer, primary_key=True, autoincrement=True)
    filename = Column(String(255), nullable=False)
    vendor_name = Column(String(200), nullable=True)
    template_matched = Column(String(200), nullable=True)
    confidence_score = Column(Float, default=100.0)
    extracted_data = Column(Text, nullable=True)  # JSON-encoded dict of fields
    explainability = Column(Text, nullable=True)    # JSON-encoded dict of explanations
    status = Column(String(50), default='uploaded') # uploaded, corrected, automated
    created_at = Column(DateTime, default=datetime.datetime.utcnow)

class AuditLog(Base):
    """
    Step-by-step logs for jobs / automation pipelines running under RWorld.
    """
    __tablename__ = 'audit_logs'

    id = Column(Integer, primary_key=True, autoincrement=True)
    task_id = Column(Integer, nullable=True)
    step_name = Column(String(200), nullable=False)
    executed_at = Column(DateTime, default=datetime.datetime.utcnow)
    duration_sec = Column(Float, default=0.0)
    step_status = Column(String(50), default='success') # success, failure
    error_details = Column(Text, nullable=True)

# Database Engine Initialization Helper
def init_db(database_url="sqlite:///rworld_ai.db"):
    engine = create_engine(database_url, connect_args={"check_same_thread": False})
    Base.metadata.create_all(engine)
    SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
    return engine, SessionLocal

