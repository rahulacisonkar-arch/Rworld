import os
import time
import email
import asyncio
import datetime
import shutil
from email import policy
from config import config
from database import SessionLocal, EmailLog, AuditLog
from agent import process_email_agent

async def scan_mock_inbox(db):
    """Disabled mock inbox scanner"""
    return

    files = [f for f in os.listdir(config.MOCK_INBOX_DIR) if f.endswith(('.txt', '.eml'))]
    if not files:
        return

    print(f"[EmailMonitor] Found {len(files)} mock email files in {config.MOCK_INBOX_DIR}")

    for file_name in files:
        file_path = os.path.join(config.MOCK_INBOX_DIR, file_name)
        start_time = time.time()
        
        try:
            with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
                content = f.read()

            subject = "Mock Shipping Request"
            sender = "store_atl@arteefabrics.com"
            body = content
            attachments = []

            # Parse headers for .txt files
            if file_name.endswith('.txt'):
                body_lines = []
                for line in content.split("\n"):
                    line_stripped = line.strip()
                    if line_stripped.lower().startswith("from:"):
                        sender = line_stripped.split(":", 1)[1].strip()
                    elif line_stripped.lower().startswith("subject:"):
                        subject = line_stripped.split(":", 1)[1].strip()
                    elif line_stripped.lower().startswith("attachment:"):
                        pass
                    else:
                        body_lines.append(line)
                body = "\n".join(body_lines).strip()

            # Parse EML headers if EML
            elif file_name.endswith('.eml'):
                msg = email.message_from_string(content, policy=policy.default)
                subject = msg.get('subject', 'No Subject')
                sender = msg.get('from', 'unknown@example.com')
                body = ""
                if msg.is_multipart():
                    for part in msg.walk():
                        if part.get_content_type() == 'text/plain':
                            body += part.get_content()
                else:
                    body = msg.get_content()

            # Check for attachments described in txt (simulated attachments)
            # Or if EML has attachments, extract them. For mock purposes, if we find a pdf path in the content, we ingest it.
            # Example text line: Attachment: c:/path/to/test.pdf
            for line in content.split("\n"):
                if line.lower().startswith("attachment:"):
                    path = line.split(":", 1)[1].strip()
                    if os.path.exists(path):
                        attachments.append(path)

            # Check if this file was already processed (by checking file_name in database or just using the processed rename)
            # Create EmailLog entry
            email_id = str(hash(file_name) + hash(content))
            existing = db.query(EmailLog).filter(EmailLog.message_id == email_id).first()
            if existing:
                # Remove or move already processed mock email
                dest_path = os.path.join(config.MOCK_INBOX_DIR, "processed", file_name)
                if os.path.exists(dest_path):
                    os.remove(file_path)
                else:
                    shutil.move(file_path, dest_path)
                continue

            email_log = EmailLog(
                message_id=email_id,
                sender=sender,
                recipient="logistics@arteefabrics.com",
                subject=subject,
                body=body,
                attachments=repr(attachments),
                processed=False
            )
            db.add(email_log)
            db.commit()
            db.refresh(email_log)

            print(f"[EmailMonitor] Ingested mock email: {subject} from {sender}")
            
            # Process via AI agent
            await process_email_agent(email_log.id, db)

            # Move mock file to processed folder
            dest_path = os.path.join(config.MOCK_INBOX_DIR, "processed", file_name)
            if os.path.exists(dest_path):
                os.remove(file_path)
            else:
                shutil.move(file_path, dest_path)

            audit = AuditLog(
                step_name="Ingested and processed mock email",
                step_status="success",
                details=f"Email ID: {email_log.id} | Subject: {subject}",
                duration_sec=time.time() - start_time
            )
            db.add(audit)
            db.commit()

        except Exception as e:
            print(f"[EmailMonitor Error] Failed to process mock file {file_name}: {e}")
            audit = AuditLog(
                step_name="Process mock email failure",
                step_status="failure",
                details=f"File: {file_name} | Error: {str(e)}",
                duration_sec=time.time() - start_time
            )
            db.add(audit)
            db.commit()

async def poll_imap_inbox(db):
    """Production IMAP polling engine (placeholder/mocked fallback if credentials not set)"""
    if not config.EMAIL_USERNAME or not config.EMAIL_PASSWORD:
        # Silently skip if email account settings are not provided
        return

    import imaplib
    start_time = time.time()
    try:
        mail = imaplib.IMAP4_SSL(config.EMAIL_IMAP_SERVER, config.EMAIL_IMAP_PORT)
        mail.login(config.EMAIL_USERNAME, config.EMAIL_PASSWORD)
        mail.select('inbox')

        status, data = mail.search(None, 'UNSEEN')
        mail_ids = data[0].split()
        
        # Limit to the last 5 unread emails
        recent_ids = mail_ids[-5:]

        for block_id in recent_ids:
            _, msg_data = mail.fetch(block_id, '(BODY.PEEK[])')
            msg = email.message_from_bytes(msg_data[0][1], policy=policy.default)
            message_id = msg.get('Message-ID', str(block_id))
            
            # Check duplicates
            existing = db.query(EmailLog).filter(EmailLog.message_id == message_id).first()
            if existing:
                continue

            sender = msg.get('from', '')
            subject = msg.get('subject', '')
            body = ""
            if msg.is_multipart():
                for part in msg.walk():
                    if part.get_content_type() == 'text/plain':
                        body += part.get_content()
            else:
                body = msg.get_content()

            # Save attachments
            attachments = []
            for part in msg.walk():
                if part.get_content_maintype() == 'multipart':
                    continue
                if part.get('Content-Disposition') is None:
                    continue
                filename = part.get_filename()
                if filename:
                    os.makedirs("./attachments", exist_ok=True)
                    filepath = os.path.join("./attachments", filename)
                    with open(filepath, 'wb') as f:
                        f.write(part.get_payload(decode=True))
                    attachments.append(filepath)

            email_log = EmailLog(
                message_id=message_id,
                sender=sender,
                recipient=config.EMAIL_USERNAME,
                subject=subject,
                body=body,
                attachments=repr(attachments),
                processed=False
            )
            db.add(email_log)
            db.commit()
            db.refresh(email_log)

            await process_email_agent(email_log.id, db)

        mail.logout()

    except Exception as e:
        print(f"[IMAP Monitor Error] IMAP failure: {e}")

async def email_monitor_loop():
    """Background loop that runs indefinitely, calling IMAP poll"""
    print("[EmailMonitor] Starting background monitoring loop...")
    while True:
        db = SessionLocal()
        try:
            await poll_imap_inbox(db)
        except Exception as e:
            print(f"[EmailMonitor Loop Error] {e}")
        finally:
            db.close()
        # Sleep to avoid CPU hogging
        await asyncio.sleep(config.EMAIL_CHECK_INTERVAL_SEC)
