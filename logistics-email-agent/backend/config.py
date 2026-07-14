import os
from dotenv import load_dotenv

# Load workspace and local env
load_dotenv()
load_dotenv(os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), ".env"))

class Config:
    LOG_LEVEL = os.getenv("LOG_LEVEL", "INFO")
    DEBUG = os.getenv("DEBUG", "true").lower() in ("true", "1", "yes")

    # Local SQLite DB
    DB_URL = os.getenv("DB_URL", "sqlite:///agent.db")

    # Portal DB (artee_shipping) MySQL
    PORTAL_DB_HOST = os.getenv("PORTAL_DB_HOST", "127.0.0.1")
    PORTAL_DB_PORT = int(os.getenv("PORTAL_DB_PORT", "3306"))
    PORTAL_DB_NAME = os.getenv("PORTAL_DB_NAME", "artee_shipping")
    PORTAL_DB_USER = os.getenv("PORTAL_DB_USER", "root")
    PORTAL_DB_PASS = os.getenv("PORTAL_DB_PASS", "")

    # LLM Settings
    OPENAI_API_KEY = os.getenv("OPENAI_API_KEY", "")
    OPENAI_BASE_URL = os.getenv("OPENAI_BASE_URL", "http://localhost:8787/v1")
    OPENAI_MODEL = os.getenv("OPENAI_MODEL", "google/gemini-2.5-pro")

    # IMAP Configuration
    EMAIL_IMAP_SERVER = os.getenv("EMAIL_IMAP_SERVER", "imap.gmail.com")
    EMAIL_IMAP_PORT = int(os.getenv("EMAIL_IMAP_PORT", "993"))
    EMAIL_USERNAME = os.getenv("EMAIL_USERNAME", "")
    EMAIL_PASSWORD = os.getenv("EMAIL_PASSWORD", "")
    EMAIL_CHECK_INTERVAL_SEC = int(os.getenv("EMAIL_CHECK_INTERVAL_SEC", "60"))

    # Mock Inbox Configuration
    MOCK_INBOX_DIR = os.getenv("MOCK_INBOX_DIR", "./mock_inbox")

    # SMTP Configuration
    SMTP_SERVER = os.getenv("SMTP_SERVER", "smtp.mailtrap.io")
    SMTP_PORT = int(os.getenv("SMTP_PORT", "587"))
    SMTP_USERNAME = os.getenv("SMTP_USERNAME", "")
    SMTP_PASSWORD = os.getenv("SMTP_PASSWORD", "")
    SMTP_FROM_EMAIL = os.getenv("SMTP_FROM_EMAIL", "shipping@arteefabrics.com")

    # Webhooks
    SLACK_WEBHOOK_URL = os.getenv("SLACK_WEBHOOK_URL", "")
    TEAMS_WEBHOOK_URL = os.getenv("TEAMS_WEBHOOK_URL", "")

    # Business Rules
    ENABLE_AUTO_LABEL = os.getenv("ENABLE_AUTO_LABEL", "false").lower() in ("true", "1", "yes")

config = Config()
