from pydantic import BaseModel, Field
from typing import List, Optional, Dict, Any
from datetime import datetime

class EmailLogResponse(BaseModel):
    id: int
    message_id: Optional[str]
    sender: str
    recipient: Optional[str]
    subject: Optional[str]
    body: Optional[str]
    received_at: datetime
    processed: bool
    processed_at: Optional[datetime]
    intent: Optional[str]
    attachments: Optional[str]

    class Config:
        from_attributes = True

class ShipmentDraftResponse(BaseModel):
    id: int
    email_id: Optional[int]
    to_name: Optional[str]
    to_company: Optional[str]
    to_address1: Optional[str]
    to_address2: Optional[str]
    to_city: Optional[str]
    to_state: Optional[str]
    to_zip: Optional[str]
    to_phone: Optional[str]
    to_email: Optional[str]
    from_name: Optional[str]
    from_company: Optional[str]
    from_address1: Optional[str]
    from_address2: Optional[str]
    from_city: Optional[str]
    from_state: Optional[str]
    from_zip: Optional[str]
    from_phone: Optional[str]
    from_email: Optional[str]
    sales_order_number: Optional[str]
    purchase_order_number: Optional[str]
    request_reference: Optional[str]
    package_count: int
    weight_lbs: Optional[float]
    length_in: Optional[float]
    width_in: Optional[float]
    height_in: Optional[float]
    carrier_preference: Optional[str]
    service_level: Optional[str]
    special_instructions: Optional[str]
    special_flags: Optional[str]
    confidence_score: float
    validation_status: str
    validation_errors: Optional[str]
    duplicate_flag: bool
    risk_score: float
    reasoning_log: Optional[str]
    status: str
    portal_request_id: Optional[int]
    tracking_number: Optional[str]
    shipping_cost: Optional[float]
    carrier_used: Optional[str]
    created_at: datetime

    class Config:
        from_attributes = True

class ShipmentExtractSchema(BaseModel):
    """Schema used for structured LLM extraction of shipment details"""
    intent: str = Field(description="Classified intent. Must be one of: 'Shipping Request', 'Store Transfer', 'Return Request', 'Ignore'")
    
    # Recipient Details
    to_name: Optional[str] = Field(None, description="Full name of recipient customer or employee")
    to_company: Optional[str] = Field(None, description="Company name of recipient if business location")
    to_address1: Optional[str] = Field(None, description="Primary street address line")
    to_address2: Optional[str] = Field(None, description="Suite, Apt, or secondary address details")
    to_city: Optional[str] = Field(None, description="Recipient address city name")
    to_state: Optional[str] = Field(None, description="Recipient state (e.g. GA, NY, CA)")
    to_zip: Optional[str] = Field(None, description="Recipient address ZIP or postal code")
    to_phone: Optional[str] = Field(None, description="Recipient contact phone number")
    to_email: Optional[str] = Field(None, description="Recipient email address")

    # Shipper / Origin Details
    from_store_code: Optional[str] = Field(None, description="Origin store code if transfer (e.g. atl, chi, bos)")
    from_name: Optional[str] = Field(None, description="Full name of shipper/origin point contact")
    from_company: Optional[str] = Field(None, description="Shipper/origin company name")
    from_address1: Optional[str] = Field(None, description="Origin street address")
    from_city: Optional[str] = Field(None, description="Origin city")
    from_state: Optional[str] = Field(None, description="Origin state")
    from_zip: Optional[str] = Field(None, description="Origin ZIP code")
    from_phone: Optional[str] = Field(None, description="Origin phone number")

    # Reference Numbers
    sales_order_number: Optional[str] = Field(None, description="Sales Order (SO) identifier if customer order")
    purchase_order_number: Optional[str] = Field(None, description="Purchase Order (PO) identifier if vendor receipt")
    request_reference: Optional[str] = Field(None, description="General request reference number")

    # Product / Carton specs
    package_count: int = Field(1, description="Total number of cartons/packages in shipment")
    weight_lbs: Optional[float] = Field(None, description="Total weight of shipment in lbs")
    length_in: Optional[float] = Field(None, description="Length of package in inches")
    width_in: Optional[float] = Field(None, description="Width of package in inches")
    height_in: Optional[float] = Field(None, description="Height of package in inches")

    # Delivery preferences
    carrier_preference: Optional[str] = Field(None, description="Preferred carrier (e.g. FedEx, UPS, USPS, DHL)")
    service_level: Optional[str] = Field(None, description="Delivery speed preference (e.g. Ground, Overnight, Second Day)")
    special_instructions: Optional[str] = Field(None, description="Handling instructions or delivery notes")
    signature_required: bool = Field(False, description="Is signature required upon receipt?")
    dangerous_goods: bool = Field(False, description="Contains dangerous goods, chemicals, or lithium batteries?")

class ShipmentUpdateSchema(BaseModel):
    to_name: Optional[str] = None
    to_company: Optional[str] = None
    to_address1: Optional[str] = None
    to_address2: Optional[str] = None
    to_city: Optional[str] = None
    to_state: Optional[str] = None
    to_zip: Optional[str] = None
    to_phone: Optional[str] = None
    to_email: Optional[str] = None
    package_count: Optional[int] = None
    weight_lbs: Optional[float] = None
    length_in: Optional[float] = None
    width_in: Optional[float] = None
    height_in: Optional[float] = None
    carrier_preference: Optional[str] = None
    service_level: Optional[str] = None
    special_instructions: Optional[str] = None

class ApprovalDecision(BaseModel):
    status: str # Approved, Rejected
    remarks: Optional[str] = ""

class AuditLogResponse(BaseModel):
    id: int
    step_name: str
    step_status: str
    details: Optional[str]
    duration_sec: float
    executed_at: datetime

    class Config:
        from_attributes = True
