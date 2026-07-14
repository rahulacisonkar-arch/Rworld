import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
import urllib.request
import json
from config import config

def send_slack_webhook(message: str):
    """Sends a notification to Slack channel"""
    if not config.SLACK_WEBHOOK_URL:
        return
    
    payload = {"text": message}
    req_data = json.dumps(payload).encode('utf-8')
    try:
        req = urllib.request.Request(
            config.SLACK_WEBHOOK_URL,
            data=req_data,
            headers={"Content-Type": "application/json"},
            method="POST"
        )
        with urllib.request.urlopen(req, timeout=5) as res:
            pass
    except Exception as e:
        print(f"[Notifications] Slack dispatch failed: {e}")

def send_teams_webhook(message: str):
    """Sends a notification to Microsoft Teams channel"""
    if not config.TEAMS_WEBHOOK_URL:
        return

    payload = {
        "@type": "MessageCard",
        "@context": "http://schema.org/extensions",
        "themeColor": "0076D7",
        "summary": "Logistics AI Agent Alert",
        "sections": [{
            "activityTitle": "Logistics AI Agent Notification",
            "activitySubtitle": "Automated Shipping Ingestion",
            "text": message
        }]
    }
    req_data = json.dumps(payload).encode('utf-8')
    try:
        req = urllib.request.Request(
            config.TEAMS_WEBHOOK_URL,
            data=req_data,
            headers={"Content-Type": "application/json"},
            method="POST"
        )
        with urllib.request.urlopen(req, timeout=5) as res:
            pass
    except Exception as e:
        print(f"[Notifications] Teams dispatch failed: {e}")

def send_email_notification(to_email: str, subject: str, html_body: str):
    """Sends a notification email using SMTP client, redirecting to admin only"""
    original_recipient = to_email
    # Force redirect to user's configured Gmail account
    to_email = config.EMAIL_USERNAME or config.SMTP_FROM_EMAIL
    print(f"[Notifications] Redirected outgoing email from {original_recipient} to admin {to_email}")

    if not config.SMTP_SERVER or not config.SMTP_USERNAME or not config.SMTP_PASSWORD:
        return

    try:
        msg = MIMEMultipart('alternative')
        msg['Subject'] = subject
        msg['From'] = f"{config.SMTP_FROM_EMAIL}"
        msg['To'] = to_email

        part1 = MIMEText(html_body, 'html')
        msg.attach(part1)

        server = smtplib.SMTP(config.SMTP_SERVER, config.SMTP_PORT)
        if config.SMTP_PORT == 587:
            server.starttls()
        server.login(config.SMTP_USERNAME, config.SMTP_PASSWORD)
        server.sendmail(config.SMTP_FROM_EMAIL, to_email, msg.as_string())
        server.quit()
        print(f"[Notifications] Outgoing email sent to {to_email}")
    except Exception as e:
        print(f"[Notifications] Email dispatch failed: {e}")

def dispatch_notifications(draft, validation_errors: list):
    """Compiles status metrics and triggers Slack, Teams, and Email dispatches"""
    # 1. Format Slack/Teams alert message
    status_indicator = "🔴 INVALID" if draft.validation_status == "invalid" else "🟢 VALID"
    if draft.status == "Approved":
        status_indicator = "⚡ AUTO-APPROVED"
        
    msg = f"""
*Logistics Email Agent Ingestion Alert*
*Status*: {status_indicator} (Inbound ID: {draft.id})
*Recipient*: {draft.to_name} ({draft.to_company})
*Destination*: {draft.to_city}, {draft.to_state} {draft.to_zip}
*Sales Order*: {draft.sales_order_number}
*Cartons*: {draft.package_count} | *Weight*: {draft.weight_lbs} lbs
*Confidence*: {draft.confidence_score}%
*Risk Score*: {draft.risk_score}/100
"""
    if validation_errors:
        msg += "\n*Validation Warnings*:\n" + "\n".join([f"- {err}" for err in validation_errors])

    send_slack_webhook(msg)
    send_teams_webhook(msg)

    # 2. Email confirmation if approved/auto-labeled
    if draft.status == "Approved" or draft.status == "Completed":
        subject = f"Shipment Confirmed - Sales Order {draft.sales_order_number}"
        body = f"""
        <html>
            <body>
                <h2>Your shipment request has been ingested and confirmed!</h2>
                <p><strong>Tracking Number:</strong> {draft.tracking_number or 'Processing...'}</p>
                <p><strong>Origin:</strong> {draft.from_company}</p>
                <p><strong>Destination:</strong> {draft.to_name} - {draft.to_address1}, {draft.to_city}, {draft.to_state}</p>
                <br>
                <p>Thank you for using Artee Fabrics Logistics.</p>
            </body>
        </html>
        """
        if draft.to_email:
            send_email_notification(draft.to_email, subject, body)
