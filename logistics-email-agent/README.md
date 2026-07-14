# Enterprise Logistics Email AI Agent

The **Enterprise Logistics Email AI Agent** monitors inboxes, extracts shipping details from messages/attachments, validates formats, screens for duplicates, writes drafts into the Logistics Command Center (`shipping-portal`), and alerts administrators on Slack/Teams/Email.

---

## 📂 Project Directory Structure

```
logistics-email-agent/
├── backend/               # FastAPI Python Backend
│   ├── main.py            # API Server & WebSocket
│   ├── config.py          # Configuration Loader
│   ├── database.py        # SQLAlchemy SQLite Models
│   ├── agent.py           # LLM extraction & validation logic
│   ├── portal_client.py   # Database client & browser-use automation
│   ├── notifications.py   # Webhooks & SMTP email dispatchers
│   ├── requirements.txt   # Python Dependencies
│   └── Dockerfile         # Backend container file
│
├── frontend/              # Vite / React Dashboard
│   ├── src/
│   │   ├── App.tsx        # UI Dashboard Component
│   │   └── main.tsx       # React mounts
│   ├── index.html         # Page markup template
│   ├── tailwind.config.js # Styling directives
│   └── Dockerfile         # Nginx container file
│
├── docs/                  # System Documentation Guides
│   ├── architecture.md    # Data flows and schema maps
│   ├── deployment.md      # Docker Compose & local startups
│   ├── guides.md          # User & Administrator manuals
│   ├── api.md             # Endpoints references
│   ├── security.md        # Credentials & data sanitization
│   ├── error_handling.md  # Recovery strategies
│   ├── logging.md         # Activity logs stream
│   └── backup_recovery.md # Backup restore steps
│
├── tests/                 # Unit & Integration Tests
│   ├── test_agent.py      # Validation & duplicate rules tests
│   └── test_email_monitor.py # Ingestion loop & folder scanning tests
│
├── mock_inbox/            # Scan directory for email simulation files
└── docker-compose.yml     # Production orchestration compose configuration
```

---

## ⚡ Quickstart Setup

Please refer to the following documentation files for detailed instructions:
1. **Local Setup & Containerization**: Read the [Deployment Guide](docs/deployment.md).
2. **Usage & Configuration**: Read the [User and Administrator Operations Guide](docs/guides.md).
3. **Architecture details**: Read the [Architecture & Flow Guide](docs/architecture.md).
