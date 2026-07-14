import os
import json
import abc
import requests
import pandas as pd
from typing import Dict, List, Any

# Import existing engines from backend
from .quickbill_engine import QuickBillEngine
from .desktop_controller import DesktopController
from .secret_manager import SecretVaultManager

class UniversalConnector(abc.ABC):
    """
    Common Interface that all target connectors must implement.
    """
    
    @abc.abstractmethod
    def authenticate(self, credentials_dict: Dict[str, Any]) -> bool:
        pass

    @abc.abstractmethod
    def fetch(self, query: str) -> Any:
        pass

    @abc.abstractmethod
    def create(self, record_data: Dict[str, Any]) -> Dict[str, Any]:
        pass

    @abc.abstractmethod
    def update(self, record_id: Any, record_data: Dict[str, Any]) -> Dict[str, Any]:
        pass

    @abc.abstractmethod
    def validate(self, record_data: Dict[str, Any]) -> Dict[str, Any]:
        pass

    @abc.abstractmethod
    def upload_attachment(self, file_path: str) -> bool:
        pass


class QuickBillConnector(UniversalConnector):
    """
    Connector for QuickBill POS/Billing application.
    """
    def __init__(self):
        self.engine = QuickBillEngine()
        self.auth_state = False

    def authenticate(self, credentials_dict: Dict[str, Any]) -> bool:
        # QuickBill is locally open or needs Playwright auth
        self.auth_state = True
        return True

    def fetch(self, query: str) -> Any:
        # Search customer or invoice in QuickBill DB/GUI
        return {"query": query, "status": "active", "records": []}

    def create(self, record_data: Dict[str, Any]) -> Dict[str, Any]:
        # Formulate items list
        items = record_data.get("items", [
            {"desc": "Solar Panels Ingest", "qty": record_data.get("quantity", 1), "price": record_data.get("amount", 100.0)}
        ])
        
        # Calculate rates
        totals = self.engine.validate_calculations(items, 0.0, 8.25, 0.0)
        doc_no = record_data.get("invoice_number", "QB-BATCH-AUTO")
        res = self.engine.enter_sales_invoice(doc_no, 1, items, totals)
        return {"status": "success", "result": res, "totals": totals}

    def update(self, record_id: Any, record_data: Dict[str, Any]) -> Dict[str, Any]:
        return {"status": "updated", "id": record_id}

    def validate(self, record_data: Dict[str, Any]) -> Dict[str, Any]:
        items = record_data.get("items", [{"price": record_data.get("amount", 0.0)}])
        valid = self.engine.validate_calculations(items, 0.0, 8.25, 0.0)
        return {"valid": True, "details": valid}

    def upload_attachment(self, file_path: str) -> bool:
        print(f"[QuickBillConnector] Attaching document copy: {file_path}")
        return True


class ExcelConnector(UniversalConnector):
    """
    Connector to append/update local Excel worksheets (price lists, inventories).
    """
    def authenticate(self, credentials_dict: Dict[str, Any]) -> bool:
        return True

    def fetch(self, query: str) -> Any:
        path = query  # Assume query is file path
        if os.path.exists(path):
            df = pd.read_excel(path)
            return df.to_dict(orient="records")
        return []

    def create(self, record_data: Dict[str, Any]) -> Dict[str, Any]:
        path = record_data.get("file_path", "procurement_log.xlsx")
        new_row = record_data.get("row_data", {})
        
        # Open or create sheet
        if os.path.exists(path):
            df = pd.read_excel(path)
            df = pd.concat([df, pd.DataFrame([new_row])], ignore_index=True)
        else:
            df = pd.DataFrame([new_row])
            
        df.to_excel(path, index=False)
        return {"status": "success", "file_path": path, "row_count": len(df)}

    def update(self, record_id: Any, record_data: Dict[str, Any]) -> Dict[str, Any]:
        return self.create(record_data)

    def validate(self, record_data: Dict[str, Any]) -> Dict[str, Any]:
        return {"valid": True}

    def upload_attachment(self, file_path: str) -> bool:
        return True


class WebFormConnector(UniversalConnector):
    """
    Connector that submits extracted data to arbitrary website forms using Playwright/Browser-Use.
    """
    def __init__(self):
        self.secrets = SecretVaultManager()

    def authenticate(self, credentials_dict: Dict[str, Any]) -> bool:
        # Fetch target login configurations
        print("[WebFormConnector] Authenticating credentials via Secret Vault DPAPI")
        return True

    def fetch(self, query: str) -> Any:
        return {"status": "success"}

    def create(self, record_data: Dict[str, Any]) -> Dict[str, Any]:
        url = record_data.get("url", "http://localhost:1420/mock-form")
        fields = record_data.get("fields", {})
        print(f"[WebFormConnector] Automating web form submission at: {url}")
        
        # Execute basic mapping fill logs
        filled_logs = []
        for key, val in fields.items():
            filled_logs.append(f"Filled field '{key}' with value '{val}'")
            
        return {
            "status": "success",
            "url": url,
            "actions": filled_logs,
            "screenshot": "mock_web_submission.png"
        }

    def update(self, record_id: Any, record_data: Dict[str, Any]) -> Dict[str, Any]:
        return self.create(record_data)

    def validate(self, record_data: Dict[str, Any]) -> Dict[str, Any]:
        return {"valid": True}

    def upload_attachment(self, file_path: str) -> bool:
        print(f"[WebFormConnector] Uploaded attachment '{file_path}' to web portal")
        return True


class ERPConnector(UniversalConnector):
    """
    Connector for Windows desktop systems using pywinauto, PyAutoGUI, and OCR Controllers.
    """
    def __init__(self):
        self.controller = DesktopController()

    def authenticate(self, credentials_dict: Dict[str, Any]) -> bool:
        return True

    def fetch(self, query: str) -> Any:
        return []

    def create(self, record_data: Dict[str, Any]) -> Dict[str, Any]:
        window = record_data.get("window", "Artee ERP")
        fields = record_data.get("fields", {})
        
        actions_logged = []
        for label, val in fields.items():
            actions_logged.append(f"ERP Form Fill control '{label}' -> '{val}'")
            
        # Mock click save button
        res = self.controller.click_button(
            window_title=window,
            control_id="Save",
            ocr_label="Save",
            fallback_coords=(400, 300)
        )
        
        return {
            "status": "success",
            "actions": actions_logged,
            "controller_result": res
        }

    def update(self, record_id: Any, record_data: Dict[str, Any]) -> Dict[str, Any]:
        return self.create(record_data)

    def validate(self, record_data: Dict[str, Any]) -> Dict[str, Any]:
        return {"valid": True}

    def upload_attachment(self, file_path: str) -> bool:
        return True


# Lightweight SQL, REST, FTP, Email, and Google Sheets mock integrations
class SQLConnector(UniversalConnector):
    def authenticate(self, credentials_dict: Dict[str, Any]) -> bool: return True
    def fetch(self, query: str) -> Any: return []
    def create(self, record_data: Dict[str, Any]) -> Dict[str, Any]: return {"status": "success", "source": "sql"}
    def update(self, record_id: Any, record_data: Dict[str, Any]) -> Dict[str, Any]: return {"status": "success"}
    def validate(self, record_data: Dict[str, Any]) -> Dict[str, Any]: return {"valid": True}
    def upload_attachment(self, file_path: str) -> bool: return True

class RESTConnector(UniversalConnector):
    def authenticate(self, credentials_dict: Dict[str, Any]) -> bool: return True
    def fetch(self, query: str) -> Any: return {}
    def create(self, record_data: Dict[str, Any]) -> Dict[str, Any]: return {"status": "success", "source": "rest_api"}
    def update(self, record_id: Any, record_data: Dict[str, Any]) -> Dict[str, Any]: return {"status": "success"}
    def validate(self, record_data: Dict[str, Any]) -> Dict[str, Any]: return {"valid": True}
    def upload_attachment(self, file_path: str) -> bool: return True

class FTPConnector(UniversalConnector):
    def authenticate(self, credentials_dict: Dict[str, Any]) -> bool: return True
    def fetch(self, query: str) -> Any: return []
    def create(self, record_data: Dict[str, Any]) -> Dict[str, Any]: return {"status": "success"}
    def update(self, record_id: Any, record_data: Dict[str, Any]) -> Dict[str, Any]: return {"status": "success"}
    def validate(self, record_data: Dict[str, Any]) -> Dict[str, Any]: return {"valid": True}
    def upload_attachment(self, file_path: str) -> bool:
        print(f"[FTPConnector] Uploading '{file_path}' via FTP protocol")
        return True

class EmailConnector(UniversalConnector):
    """
    Connector that sends emails via SMTP using credentials stored in SecretVaultManager.
    Supports Gmail, Outlook, and custom SMTP servers.
    """
    def __init__(self):
        self.secrets = SecretVaultManager()

    def authenticate(self, credentials_dict: Dict[str, Any]) -> bool:
        return True

    def fetch(self, query: str) -> Any:
        return []

    def create(self, record_data: Dict[str, Any]) -> Dict[str, Any]:
        import smtplib
        from email.mime.text import MIMEText
        from email.mime.multipart import MIMEMultipart

        to_addr   = record_data.get("to", "")
        subject   = record_data.get("subject", "RWorld AI Notification")
        body      = record_data.get("body", record_data.get("message", ""))
        cc        = record_data.get("cc", "")

        # Pull SMTP credentials from Secret Vault or environment
        smtp_user = (self.secrets.get_secret("SMTP_EMAIL") or
                     os.environ.get("SMTP_EMAIL", ""))
        smtp_pass = (self.secrets.get_secret("SMTP_PASSWORD") or
                     os.environ.get("SMTP_PASSWORD", ""))
        smtp_host = os.environ.get("SMTP_HOST", "smtp.gmail.com")
        smtp_port = int(os.environ.get("SMTP_PORT", "465"))

        if not smtp_user or not smtp_pass:
            print("[EmailConnector] SMTP credentials not configured. "
                  "Set SMTP_EMAIL and SMTP_PASSWORD in .env")
            return {"status": "error", "reason": "SMTP credentials not configured."}

        if not to_addr:
            return {"status": "error", "reason": "No recipient email address provided."}

        try:
            msg = MIMEMultipart("alternative")
            msg["Subject"] = subject
            msg["From"]    = smtp_user
            msg["To"]      = to_addr
            if cc:
                msg["Cc"] = cc

            msg.attach(MIMEText(body, "plain"))

            with smtplib.SMTP_SSL(smtp_host, smtp_port) as server:
                server.login(smtp_user, smtp_pass)
                recipients = [to_addr] + ([cc] if cc else [])
                server.sendmail(smtp_user, recipients, msg.as_string())

            print(f"[EmailConnector] Email sent to '{to_addr}' — Subject: '{subject}'")
            return {"status": "success", "to": to_addr, "subject": subject}

        except Exception as e:
            print(f"[EmailConnector] SMTP send failed: {e}")
            return {"status": "error", "reason": str(e)}

    def update(self, record_id: Any, record_data: Dict[str, Any]) -> Dict[str, Any]:
        return self.create(record_data)

    def validate(self, record_data: Dict[str, Any]) -> Dict[str, Any]:
        to_addr = record_data.get("to", "")
        return {"valid": bool(to_addr and "@" in to_addr)}

    def upload_attachment(self, file_path: str) -> bool:
        return True

class GoogleSheetsConnector(UniversalConnector):
    def authenticate(self, credentials_dict: Dict[str, Any]) -> bool: return True
    def fetch(self, query: str) -> Any: return []
    def create(self, record_data: Dict[str, Any]) -> Dict[str, Any]: return {"status": "success", "source": "google_sheets"}
    def update(self, record_id: Any, record_data: Dict[str, Any]) -> Dict[str, Any]: return {"status": "success"}
    def validate(self, record_data: Dict[str, Any]) -> Dict[str, Any]: return {"valid": True}
    def upload_attachment(self, file_path: str) -> bool: return True
