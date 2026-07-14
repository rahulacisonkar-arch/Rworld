# User and Administrator Operations Guide

This guide describes operational manuals for using and configuring the **Enterprise Logistics Email AI Agent**.

---

## 1. User Guide (Operations Manual)

### Reviewing Ingested Emails
1. Click the **Emails Log** tab on the sidebar.
2. Select any incoming email block to inspect its raw subject, body context, and parsed intent.
3. Check the attachments list to verify the downloaded files.

### Approving Shipment Drafts
1. Go to the **Pending Approvals** tab.
2. Select an active draft card to view its extracted recipient addresses, order references, package parameters, and validation warnings.
3. If errors are present (e.g. highlighted invalid ZIP code format or duplicate order references), click **Modify Fields** to fix them.
4. Click **Approve & Create Label** to trigger the automation client. The system will interface with the Logistics Portal and generate the tracking labels.
5. Dismiss incorrect or mock entries by clicking **Reject**.

---

## 2. Administrator Guide (System Configuration)

### Setup Business Rules
- **Automatic Processing Thresholds**: Edit `.env` to set `ENABLE_AUTO_LABEL=true` to allow high-confidence, error-free shipping requests to bypass human queueing.
- **Mock Folder Testing**: Configure `MOCK_INBOX_DIR` path. Dropping any text file containing mail details (subject, body, and attachment paths) triggers immediate ingestion.

### Outgoing Alerts Configuration
- Add webhook endpoints under `SLACK_WEBHOOK_URL` and `TEAMS_WEBHOOK_URL` in the `.env` configuration file to receive real-time notifications for every incoming shipping request.
