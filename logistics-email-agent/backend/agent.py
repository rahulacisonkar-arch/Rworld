import os
import datetime
import json
import re
import time
import ast
import pandas as pd
from typing import List, Tuple
from sqlalchemy.orm import Session
from langchain_openai import ChatOpenAI
from langchain_core.messages import SystemMessage, HumanMessage

from config import config
from database import EmailLog, ShipmentDraft, AuditLog, AgentMemory
from portal_client import save_draft_to_portal, get_existing_requests_by_so
from notifications import dispatch_notifications

def clean_json_markdown(text: str) -> str:
    """Strips markdown json wrappers and conversational text if present"""
    text = text.strip()
    first_brace = text.find('{')
    last_brace = text.rfind('}')
    if first_brace != -1 and last_brace != -1 and last_brace > first_brace:
        return text[first_brace:last_brace+1]
    return text

def extract_text_from_pdf(filepath: str) -> str:
    """Fast extraction of text from PDF using PyMuPDF"""
    try:
        import fitz # PyMuPDF
        doc = fitz.open(filepath)
        text = ""
        for page in doc:
            text += page.get_text()
        return text.strip()
    except Exception as e:
        print(f"[Agent OCR] PyMuPDF failed: {e}. Falling back...")
        try:
            from pypdf import PdfReader
            reader = PdfReader(filepath)
            text = ""
            for page in reader.pages:
                text += page.extract_text()
            return text.strip()
        except Exception as ex:
            print(f"[Agent OCR] pypdf failed too: {ex}")
            return ""

def extract_text_from_excel(filepath: str) -> str:
    """Converts Excel sheet contents to text for prompt inclusion"""
    try:
        df = pd.read_excel(filepath)
        return df.to_string()
    except Exception as e:
        print(f"[Agent Excel] Excel parse failed: {e}")
        return ""

def run_ocr_on_file(filepath: str) -> str:
    """Deduces file type and extracts textual context"""
    ext = os.path.splitext(filepath.lower())[1]
    if ext == '.pdf':
        return extract_text_from_pdf(filepath)
    elif ext in ['.xlsx', '.xls']:
        return extract_text_from_excel(filepath)
    elif ext in ['.csv', '.txt']:
        try:
            with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                return f.read()
        except Exception:
            return ""
    else:
        # For images (png, jpg), try RapidOCR if available
        try:
            from rapidocr import RapidOCR
            engine = RapidOCR()
            ocr_out = engine(filepath)
            if ocr_out and isinstance(ocr_out, (list, tuple)) and ocr_out[0]:
                return "\n".join([r[1] for r in ocr_out[0] if len(r) > 1]).strip()
        except Exception as e:
            print(f"[Agent OCR] RapidOCR failed for image: {e}")
        return ""

def validate_shipment(draft: ShipmentDraft, db: Session) -> Tuple[str, List[str]]:
    """Runs enterprise validation rules and flags warnings/errors"""
    errors = []
    
    # 1. Required Fields Check
    if not draft.to_name and not draft.to_company:
        errors.append("Missing recipient name/company")
    if not draft.to_address1:
        errors.append("Missing recipient address line 1")
    if not draft.to_city:
        errors.append("Missing recipient city")
    if not draft.to_state:
        errors.append("Missing recipient state")
    if not draft.to_zip:
        errors.append("Missing recipient ZIP code")
    if not draft.sales_order_number:
        errors.append("Missing Sales Order number reference")
        
    # 2. ZIP Code Format Check
    if draft.to_zip:
        zip_clean = re.sub(r'[^0-9-]', '', draft.to_zip)
        if not re.match(r'^\d{5}(-\d{4})?$', zip_clean):
            errors.append(f"Invalid US ZIP Code format: '{draft.to_zip}'")

    # 3. Duplicate Order Check
    if draft.sales_order_number:
        # Check in local agent DB
        existing_local = db.query(ShipmentDraft).filter(
            ShipmentDraft.sales_order_number == draft.sales_order_number,
            ShipmentDraft.id != draft.id,
            ShipmentDraft.status != 'Rejected'
        ).first()
        if existing_local:
            errors.append(f"Duplicate Sales Order in Agent queue (ID: {existing_local.id})")
        
        # Check in Logistics Portal DB
        existing_portal = get_existing_requests_by_so(draft.sales_order_number)
        if existing_portal:
            errors.append(f"Sales Order already exists in Logistics Portal (Request: {existing_portal[0]['request_number']})")

    # 4. Package Dimensions & Weight Check
    if not draft.weight_lbs or draft.weight_lbs <= 0:
        errors.append("Weight is missing or invalid")
    if not draft.length_in or not draft.width_in or not draft.height_in:
        errors.append("Dimensions (L, W, H) are missing")

    # 5. Check blocked destinations/customers (Mock check)
    if draft.to_city and draft.to_city.lower() in ["cuba", "iran", "north korea", "syria"]:
        errors.append(f"Destination '{draft.to_city}' is in a sanctioned trade region")

    status = "valid" if not errors else "invalid"
    return status, errors

async def process_email_agent(email_id: int, db: Session):
    """Executes AI classification, extraction, and validation pipeline"""
    start_time = time.time()
    email_log = db.query(EmailLog).filter(EmailLog.id == email_id).first()
    if not email_log:
        return

    print(f"[AI Agent] Starting analysis for Email ID {email_id}...")
    
    # 1. Compile email text & run attachment OCR
    email_text = f"Subject: {email_log.subject}\nFrom: {email_log.sender}\nBody:\n{email_log.body}\n"
    
    attachment_paths = []
    if email_log.attachments:
        try:
            attachment_paths = ast.literal_eval(email_log.attachments)
        except Exception:
            attachment_paths = []

    ocr_contents = []
    for filepath in attachment_paths:
        if os.path.exists(filepath):
            text = run_ocr_on_file(filepath)
            if text:
                ocr_contents.append(f"--- Attachment: {os.path.basename(filepath)} ---\n{text}")

    if ocr_contents:
        email_text += "\n=== Extracted Attachment OCR Contents ===\n" + "\n\n".join(ocr_contents)

    # 2. Query LLM to Classify and Extract Details
    extracted_data = {}
    reasoning = "Extracted using gemini structured output"
    confidence = 100.0
    intent = "Ignore"

    try:
        llm = ChatOpenAI(
            base_url=config.OPENAI_BASE_URL,
            api_key=config.OPENAI_API_KEY,
            model=config.OPENAI_MODEL,
            temperature=0.0,
            max_tokens=1000
        )

        system_instruction = (
            "You are Artee Shipping AI. Your only job is to determine whether an email requires a shipment label.\n\n"
            "If YES, you must extract the following fields. If any required field is missing, "
            "you must generate a reply message asking ONLY for the missing information. Never guess.\n\n"
            "Return valid JSON matching the following schema:\n"
            "{\n"
            "  \"requires_label\": true, // Set to true if email requires a shipment label, else false\n"
            "  \"sales_order\": \"...\", // Required. Sales Order number reference\n"
            "  \"po_number\": \"...\", // Purchase Order (PO) number reference if available\n"
            "  \"req_number\": \"...\", // Requisition (REQ#) number reference if available\n"
            "  \"customer\": \"...\", // Required. Customer/recipient company or name\n"
            "  \"store\": \"...\", // Required. Store code or origin store identifier (e.g., ATL, CHI, BOS)\n"
            "  \"ship_to\": {\n"
            "    \"name\": \"...\", // Required. Recipient name\n"
            "    \"company\": \"...\",\n"
            "    \"address1\": \"...\", // Required. Address line 1\n"
            "    \"address2\": \"...\",\n"
            "    \"city\": \"...\", // Required. City\n"
            "    \"state\": \"...\", // Required. 2-letter state abbreviation\n"
            "    \"zip\": \"...\", // Required. 5-digit zip code\n"
            "    \"phone\": \"...\",\n"
            "    \"email\": \"...\"\n"
            "  },\n"
            "  \"shipping_method\": {\n"
            "    \"carrier\": \"...\", // e.g. UPS, FedEx, DHL\n"
            "    \"service_level\": \"...\" // e.g. Ground, Next Day Air, 2nd Day Air\n"
            "  },\n"
            "  \"weight_lbs\": 0.0, // Package weight in lbs\n"
            "  \"cartons\": 1, // Number of cartons/packages (integer)\n"
            "  \"dimensions\": {\n"
            "    \"length\": 0.0,\n"
            "    \"width\": 0.0,\n"
            "    \"height\": 0.0\n"
            "  },\n"
            "  \"special_instructions\": \"...\",\n"
            "  \"missing_fields_reply\": \"...\" // Generate a polite email reply asking ONLY for the missing required fields (sales_order, customer, store, ship_to.name, ship_to.address1, ship_to.city, ship_to.state, ship_to.zip) if any are missing. If none are missing, keep this empty.\n"
            "}"
        )

        response = llm.invoke([
            SystemMessage(content=system_instruction),
            HumanMessage(content=f"Email Text:\n{email_text}")
        ])

        raw_content = clean_json_markdown(response.content.strip())
        extracted_data = json.loads(raw_content)
        
        # Standarize LLM output keys to database schema fields
        ship_to = extracted_data.get("ship_to") or {}
        shipping_method = extracted_data.get("shipping_method") or {}
        dims = extracted_data.get("dimensions") or {}
        
        if "requires_label" in extracted_data:
            intent = "Shipping Request" if extracted_data.get("requires_label") else "Ignore"
        else:
            intent = extracted_data.get("intent", "Ignore")
            
        sales_order_number = extracted_data.get("sales_order") or extracted_data.get("sales_order_number")
        purchase_order_number = extracted_data.get("po_number") or extracted_data.get("purchase_order_number")
        request_reference = extracted_data.get("req_number") or extracted_data.get("request_reference")
        
        to_name = ship_to.get("name") or extracted_data.get("to_name") or extracted_data.get("customer")
        to_company = ship_to.get("company") or extracted_data.get("to_company") or extracted_data.get("customer")
        to_address1 = ship_to.get("address1") or extracted_data.get("to_address1")
        to_address2 = ship_to.get("address2") or extracted_data.get("to_address2")
        to_city = ship_to.get("city") or extracted_data.get("to_city")
        to_state = ship_to.get("state") or extracted_data.get("to_state")
        to_zip = ship_to.get("zip") or extracted_data.get("to_zip")
        to_phone = ship_to.get("phone") or extracted_data.get("to_phone")
        to_email = ship_to.get("email") or extracted_data.get("to_email")
        
        store_code = extracted_data.get("store") or extracted_data.get("from_store_code")
        
        carrier_preference = shipping_method.get("carrier") or extracted_data.get("carrier_preference")
        service_level = shipping_method.get("service_level") or extracted_data.get("service_level")
        
        weight_lbs = extracted_data.get("weight") or extracted_data.get("weight_lbs")
        package_count = extracted_data.get("cartons") or extracted_data.get("package_count") or 1
        
        length_in = dims.get("length") or extracted_data.get("length_in")
        width_in = dims.get("width") or extracted_data.get("width_in")
        height_in = dims.get("height") or extracted_data.get("height_in")
        
        special_instructions = extracted_data.get("special_instructions")
        
        # Simple heuristic confidence score calculation
        missing_count = 0
        important_keys = [to_name, to_address1, to_city, to_state, to_zip, sales_order_number]
        for v in important_keys:
            if not v:
                missing_count += 1
        confidence = max(50.0, 100.0 - (missing_count * 8.0))
        reasoning = f"Parsed successfully using model {config.OPENAI_MODEL} with confidence {confidence}%."

    except Exception as e:
        print(f"[Agent LLM Error] Failed parsing: {e}")
        intent = "Ignore"
        reasoning = f"LLM parsing failed: {str(e)}"
        confidence = 50.0
        sales_order_number = None
        purchase_order_number = None
        request_reference = None
        to_name = None
        to_company = None
        to_address1 = None
        to_address2 = None
        to_city = None
        to_state = None
        to_zip = None
        to_phone = None
        to_email = None
        store_code = None
        carrier_preference = None
        service_level = None
        weight_lbs = None
        package_count = 1
        length_in = None
        width_in = None
        height_in = None
        special_instructions = None

    email_log.intent = intent
    email_log.processed = True
    email_log.processed_at = datetime.datetime.utcnow()
    db.commit()

    if intent in ["Ignore"]:
        print(f"[AI Agent] Intent classified as IGNORE for Email ID {email_id}. Process complete.")
        return

    # 3. Handle origin address fallback using memory or store codes
    # If store code is supplied, look up the address in the portal's `stores` table
    from_company = "Artee Fabrics"
    from_name = "Logistics Headquarters"
    from_address1 = ""
    from_city = ""
    from_state = ""
    from_zip = ""
    from_phone = ""

    # Look up in memory if we have store preferences
    if store_code:
        # Check local agent memory first
        mem = db.query(AgentMemory).filter(AgentMemory.category == "store_preference", AgentMemory.key_name == store_code.lower()).first()
        if mem:
            try:
                mem_data = json.loads(mem.value_text)
                from_company = mem_data.get("store_name", from_company)
                from_address1 = mem_data.get("address", from_address1)
                from_city = mem_data.get("city", from_city)
                from_state = mem_data.get("state", from_state)
                from_zip = mem_data.get("zip", from_zip)
                from_phone = mem_data.get("phone", from_phone)
            except Exception:
                pass

    # Create ShipmentDraft
    special_flags = []
    if extracted_data and (extracted_data.get("signature_required") or "signature" in (special_instructions or "").lower()):
        special_flags.append("Signature Required")
    if extracted_data and (extracted_data.get("dangerous_goods") or "dangerous" in (special_instructions or "").lower()):
        special_flags.append("Dangerous Goods")

    draft = ShipmentDraft(
        email_id=email_id,
        to_name=to_name,
        to_company=to_company,
        to_address1=to_address1,
        to_address2=to_address2,
        to_city=to_city,
        to_state=to_state,
        to_zip=to_zip,
        to_phone=to_phone,
        to_email=to_email,
        
        from_name=from_name,
        from_company=from_company,
        from_address1=from_address1,
        from_city=from_city,
        from_state=from_state,
        from_zip=from_zip,
        from_phone=from_phone,
        from_email=config.SMTP_FROM_EMAIL,

        sales_order_number=sales_order_number,
        purchase_order_number=purchase_order_number,
        request_reference=request_reference,
        
        package_count=int(package_count) if package_count is not None else 1,
        weight_lbs=float(weight_lbs) if weight_lbs is not None else None,
        length_in=float(length_in) if length_in is not None else None,
        width_in=float(width_in) if width_in is not None else None,
        height_in=float(height_in) if height_in is not None else None,
        
        carrier_preference=carrier_preference,
        service_level=service_level,
        special_instructions=special_instructions,
        special_flags=json.dumps(special_flags),
        
        confidence_score=confidence,
        reasoning_log=reasoning,
        duplicate_flag=False,
        status='Pending Approval'
    )
    db.add(draft)
    db.commit()
    db.refresh(draft)

    # 4. Perform Validations & Duplicates Check
    val_status, val_errors = validate_shipment(draft, db)
    draft.validation_status = val_status
    draft.validation_errors = json.dumps(val_errors)
    
    # Calculate duplicate status flag
    if any("Duplicate" in err for err in val_errors):
        draft.duplicate_flag = True
    
    # Risk Score calculation
    risk = 0.0
    if val_status == "invalid":
        risk += len(val_errors) * 20.0
    if confidence < 80:
        risk += (80 - confidence) * 2.0
    if draft.duplicate_flag:
        risk += 50.0
    draft.risk_score = min(100.0, risk)

    # 5. Check if eligible for Auto-Approval / Auto-Label creation
    # Rules: ENABLE_AUTO_LABEL is True, confidence >= 95.0, validation_status is 'valid', no duplicate order
    should_auto = (
        config.ENABLE_AUTO_LABEL and 
        confidence >= 95.0 and 
        val_status == "valid" and 
        not draft.duplicate_flag
    )
    
    if should_auto:
        draft.status = 'Approved'
        print(f"[AI Agent] Draft ID {draft.id} eligible for Auto-Labeling. Request Approved.")
    
    db.commit()

    # 6. Save shipment draft directly into the Logistics Command Center DB
    try:
        portal_id = save_draft_to_portal(draft)
        draft.portal_request_id = portal_id
        db.commit()
        print(f"[AI Agent] Saved draft to Portal DB as Request ID: {portal_id}")
    except Exception as e:
        print(f"[AI Agent Portal Save Error] {e}")

    # 7. Dispatch Slack / Teams / Email notifications
    dispatch_notifications(draft, val_errors)

    # 8. Send reply if required fields are missing
    if intent == "Shipping Request" and val_status == "invalid":
        reply_body = extracted_data.get("missing_fields_reply") if extracted_data else None
        if reply_body and email_log.sender:
            try:
                from notifications import send_email_notification
                subject = f"Re: {email_log.subject or 'Shipping Request'}"
                send_email_notification(email_log.sender, subject, f"<p>{reply_body}</p>")
                print(f"[AI Agent] Sent missing info request reply to {email_log.sender}.")
            except Exception as ex:
                print(f"[AI Agent Reply Error] Failed to send reply: {ex}")

    log_step = AuditLog(
        step_name="AI Extraction & Sync",
        step_status="success",
        details=f"Draft ID: {draft.id} | Intent: {intent} | Status: {draft.status} | Validation: {val_status}",
        duration_sec=time.time() - start_time
    )
    db.add(log_step)
    db.commit()
    print(f"[AI Agent] Ingestion complete. Inbound ID: {draft.id} | Status: {draft.status}")
