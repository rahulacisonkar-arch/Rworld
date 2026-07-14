# API Portal & Documentation

This document describes REST and WebSocket endpoints exposed by the **Logistics Email Agent API**.

---

## 1. REST Endpoints

### Ingested Emails Log
- **`GET /api/emails`**
  - **Description**: Returns all processed and unprocessed email headers ingested via IMAP/Mock logs.
  - **Response Sample**:
    ```json
    [
      {
        "id": 1,
        "message_id": "msg-12345",
        "sender": "store_atl@arteefabrics.com",
        "recipient": "logistics@arteefabrics.com",
        "subject": "Inbound Shipment Request SO-89304",
        "received_at": "2026-07-10T02:53:15",
        "processed": true,
        "intent": "Shipping Request"
      }
    ]
    ```

### Extracted Shipment Drafts Queue
- **`GET /api/shipments`**
  - **Description**: Returns all extracted shipping request drafts.
- **`GET /api/shipment/{id}`**
  - **Description**: Fetch specific shipment detail fields.
- **`PUT /api/shipment/{id}`**
  - **Description**: Allows manual override edit updates to shipment fields. Triggers auto-recalculation of address/duplicate validations.
  - **Payload Schema**:
    ```json
    {
      "to_name": "Jane Doe",
      "to_address1": "456 Broadway",
      "weight_lbs": 10.5
    }
    ```

### Approvals
- **`POST /api/shipment/approve/{id}`**
  - **Description**: Approves a shipment, inserts the record as a portal draft, and generates a carrier shipping label via browser-use/easyship.
- **`POST /api/shipment/reject/{id}`**
  - **Description**: Marks the draft status as `Rejected`.

### Logs & Analytics
- **`GET /api/logs`**
  - **Description**: Returns execution audit logs.
- **`GET /api/analytics`**
  - **Description**: Returns processed counts, carrier distribution, average times, and billings.

---

## 2. WebSocket Channels

- **`WS /ws/logs`**
  - **Description**: Real-time broadcast channel delivering logs directly to the dashboard terminal.
