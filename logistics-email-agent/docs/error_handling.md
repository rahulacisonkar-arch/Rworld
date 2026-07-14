# Error Handling & Logging Strategy

This document outlines the logging structures, trace explainability, and recovery workflows for the **Logistics Email Agent**.

---

## 1. Error Handling & Recovery Workflows

- **Email Ingestion Failures**: If IMAP polling fails due to a network timeout, the thread sleeps and retries after the polling interval. In mock directory mode, if a file fails to parse, it is logged and the file is bypassed to prevent blocking the worker loop.
- **Attachment OCR Fallbacks**:
  - If PyMuPDF fails or encounters an encrypted PDF, the system falls back to the `pypdf` extraction parser.
  - For image files, if `RapidOCR` is missing or fails, it records empty text and logs the warning.
- **LLM Timeout Fallbacks**: If the structured extraction query times out, the shipment draft is generated with empty fields and flagged as `invalid` (Pending Approval), alerting the operator.
- **Logistics Portal Connectivity**: If the `shipping-portal` database connection fails, the agent retries writing drafts. When generating labels, if browser automation fails, it triggers the direct MySQL db insertion fallback to ensure operations are not blocked.

---

## 2. Structured Logging Strategy

- **Audit Logs Table**: All automation steps are logged to the local SQLite database.
- **WebSocket Broadcasts**: Events are broadcasted to the terminal console of the React dashboard in real-time, allowing users to watch IMAP and extraction jobs live.
