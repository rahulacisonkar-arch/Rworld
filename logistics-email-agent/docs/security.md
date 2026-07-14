# Security Documentation & Best Practices

Security is critical when automating systems with financial or operational consequences (such as generating shipping labels and billing carrier accounts). This document outlines the security architecture and protections built into the **Enterprise Logistics Email AI Agent**.

---

## 1. Credentials & Secret Management

- **No Hard-Coded Secrets**: All API tokens, SMTP passwords, database keys, and IMAP authentication variables are loaded dynamically from the `.env` configuration file.
- **Environment Templates**: A `.env.template` is provided to ensure developers do not commit real secrets to source control.

---

## 2. Input Validation & Content Sanitization

- **Pydantic Validation**: All REST inputs are validated using Pydantic v2 schemas.
- **Strict Parsing**: Any extracted fields from the LLM (which may contain unstructured formatting) are parsed and forced into strict formats:
  - **ZIP Codes**: Filtered to match standard US 5-digit (`12345`) or 9-digit (`12345-6789`) formats.
  - **Numeric Fields**: Weights, lengths, widths, and heights are converted and validated as float/integer bounds.
- **XSS Prevention**: Frontend text elements are sanitized to prevent scripting injections when rendering raw email bodies.

---

## 3. Operations Guardrails & Approvals

- **Human-in-the-Loop Policies**: In production mode (`ENABLE_AUTO_LABEL=false`), the agent is restricted to generating **drafts**. No shipping labels can be purchased or generated without explicit human approval via the dashboard.
- **Audit Trails**: Every lifecycle action (ingestion, validation status changes, user overrides, and API completions) is recorded with timestamp and execution detail fields inside the `audit_logs` table.
