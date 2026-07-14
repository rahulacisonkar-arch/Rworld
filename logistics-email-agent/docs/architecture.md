# System Architecture & Data Flow

This document details the system design, data schema, and workflow sequencing for the **Enterprise Logistics Email AI Agent**.

---

## 1. System Components

The application is structured around a decoupled model containing:
- **FastAPI Backend Services**: Serves REST and WebSocket connections, and runs asynchronous background worker loops.
- **Vite/React Dashboard**: Slick administrative interface using Tailwind CSS and Lucide icons.
- **SQL Databases**: Local agent SQLite state DB (`agent.db`) and MySQL Portal DB (`artee_shipping`).

---

## 2. Dynamic Data Flow

```
[Inbound Email / EML File]
       │
       ▼
[email_monitor.py] ─── (Attachment Files) ───► [OCR Parser (PyMuPDF / RapidOCR)]
       │                                                      │
       ▼                                                      ▼
[Compiled Ingestion Payload (Plain Text)] ◄────────────────────┘
       │
       ▼
[agent.py Inference] ───► [Gemini / Llama API (Structured Extraction)]
       │
       ▼
[Shipment Draft Object (Pydantic validated)]
       │
       ├─► [validate_shipment() Check Address, Duplicates, and ZIP]
       │
       ▼
[Write records to SQLite (agent.db) & MySQL (artee_shipping)]
       │
       ├─► [notifications.py Dispatch Webhooks to Slack/Teams]
       │
       ▼
[Approvals Dashboard / Human Verification] ───► [Approved Action]
       │
       ▼
[execute_draft_approval() Portal Automation / DB Fallback]
       │
       ▼
[Label PDF placed in secure_uploads/ & Tracking updated]
       │
       ▼
[Outgoing Confirmation Email sent via SMTP to Recipient]
```

---

## 3. Database Schema Mapping

### Local SQLite Database (`agent.db`)
- **`email_logs`**: Tracks received headers, sender emails, and processed timestamps.
- **`shipment_drafts`**: Stores address elements, SO references, weight, and risk scores.
- **`audit_logs`**: Tracks step names, success rates, durations, and error details.
- **`agent_memory`**: Stores preferences.

### Portal MySQL Database (`artee_shipping`)
- **`label_requests`**: Stores the shipment draft parameters as drafts.
- **`request_labels`**: Holds generated labels, costs, carriers, and tracking numbers.
