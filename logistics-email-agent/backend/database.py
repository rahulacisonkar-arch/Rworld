import datetime
import json
from sqlalchemy import create_engine, Column, Integer, String, Float, Boolean, DateTime, Text, ForeignKey
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import relationship, sessionmaker
from config import config

Base = declarative_base()

class EmailLog(Base):
    """Tracks raw incoming emails ingested by the system"""
    __tablename__ = 'email_logs'

    id = Column(Integer, primary_key=True, autoincrement=True)
    message_id = Column(String(255), unique=True, nullable=True)
    sender = Column(String(255), nullable=False)
    recipient = Column(String(255), nullable=True)
    subject = Column(String(255), nullable=True)
    body = Column(Text, nullable=True)
    received_at = Column(DateTime, default=datetime.datetime.utcnow)
    processed = Column(Boolean, default=False)
    processed_at = Column(DateTime, nullable=True)
    intent = Column(String(100), nullable=True) # Shipping Request, Return, Complaint, Ignore, etc.
    attachments = Column(Text, nullable=True) # JSON array of paths

    shipments = relationship('ShipmentDraft', back_populates='email')

class ShipmentDraft(Base):
    """Tracks extracted shipping data pending approval or completed"""
    __tablename__ = 'shipment_drafts'

    id = Column(Integer, primary_key=True, autoincrement=True)
    email_id = Column(Integer, ForeignKey('email_logs.id', ondelete='SET NULL'), nullable=True)
    
    # Ship To Address
    to_name = Column(String(255), nullable=True)
    to_company = Column(String(255), nullable=True)
    to_address1 = Column(String(255), nullable=True)
    to_address2 = Column(String(255), nullable=True)
    to_city = Column(String(255), nullable=True)
    to_state = Column(String(255), nullable=True)
    to_zip = Column(String(50), nullable=True)
    to_phone = Column(String(100), nullable=True)
    to_email = Column(String(255), nullable=True)

    # Ship From Address (usually a store, e.g. store_atl)
    from_name = Column(String(255), nullable=True)
    from_company = Column(String(255), nullable=True)
    from_address1 = Column(String(255), nullable=True)
    from_address2 = Column(String(255), nullable=True)
    from_city = Column(String(255), nullable=True)
    from_state = Column(String(255), nullable=True)
    from_zip = Column(String(50), nullable=True)
    from_phone = Column(String(100), nullable=True)
    from_email = Column(String(255), nullable=True)

    # Order Details
    sales_order_number = Column(String(100), nullable=True)
    purchase_order_number = Column(String(100), nullable=True)
    request_reference = Column(String(100), nullable=True)
    
    # Packages
    package_count = Column(Integer, default=1)
    weight_lbs = Column(Float, nullable=True)
    length_in = Column(Float, nullable=True)
    width_in = Column(Float, nullable=True)
    height_in = Column(Float, nullable=True)

    # Shipping options
    carrier_preference = Column(String(100), nullable=True)
    service_level = Column(String(100), nullable=True)
    special_instructions = Column(Text, nullable=True)
    special_flags = Column(Text, nullable=True) # JSON list (e.g. Danger Goods, Signature Required)
    
    # Validation & Metadata
    confidence_score = Column(Float, default=100.0)
    validation_status = Column(String(50), default='valid') # valid, invalid
    validation_errors = Column(Text, nullable=True) # JSON array of string error descriptions
    duplicate_flag = Column(Boolean, default=False)
    risk_score = Column(Float, default=0.0)
    reasoning_log = Column(Text, nullable=True)

    # Process Management
    status = Column(String(50), default='Pending Approval') # Pending Approval, Approved, Rejected, Completed
    portal_request_id = Column(Integer, nullable=True) # Links to label_requests in artee_shipping DB
    tracking_number = Column(String(255), nullable=True)
    shipping_cost = Column(Float, nullable=True)
    carrier_used = Column(String(100), nullable=True)
    created_at = Column(DateTime, default=datetime.datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.datetime.utcnow, onupdate=datetime.datetime.utcnow)

    email = relationship('EmailLog', back_populates='shipments')

class AuditLog(Base):
    """System activity logs for operations audit"""
    __tablename__ = 'audit_logs'

    id = Column(Integer, primary_key=True, autoincrement=True)
    step_name = Column(String(255), nullable=False)
    step_status = Column(String(50), default='success') # success, failure
    details = Column(Text, nullable=True)
    duration_sec = Column(Float, default=0.0)
    executed_at = Column(DateTime, default=datetime.datetime.utcnow)

class AgentMemory(Base):
    """Dynamic memory mapping preferences for stores and addresses"""
    __tablename__ = 'agent_memory'

    id = Column(Integer, primary_key=True, autoincrement=True)
    category = Column(String(100), nullable=False) # store_preference, carrier_preference, corrected_address
    key_name = Column(String(255), nullable=False, index=True)
    value_text = Column(Text, nullable=False)
    created_at = Column(DateTime, default=datetime.datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.datetime.utcnow, onupdate=datetime.datetime.utcnow)

# Engine & Session Makers
engine = create_engine(config.DB_URL, connect_args={"check_same_thread": False} if "sqlite" in config.DB_URL else {})
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

def init_agent_db():
    Base.metadata.create_all(bind=engine)
