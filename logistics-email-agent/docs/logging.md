# Logging Strategy

The **Logistics Email Agent** adopts a detailed telemetry logging strategy to ensure full auditability of autonomous actions.

---

## 1. Local Database Logging

All agent operations are tracked inside the `audit_logs` table in `agent.db` with the following parameters:
- **`step_name`**: Logical step description (e.g., "Ingested mock email", "Manual Override Edit", "Label Created").
- **`step_status`**: Status outcome of the operation:
  - `success`: Execution completed successfully.
  - `failure`: Encountered exception/error.
  - `in_progress`: Dispatched asynchronously.
- **`details`**: Textual representation of parameters or exception messages.
- **`duration_sec`**: Precise time taken to parse, validate, or call APIs.
- **`executed_at`**: Timestamp.

---

## 2. Real-time Dashboard Logs Stream

The FastAPI server exposes a WebSocket channel (`/ws/logs`). Every time a new entry is added to `audit_logs`, the backend broadcasts it to all active connections. The React dashboard renders these messages live under the **Audit Logs** console.
