# Deployment Guide

This document describes the instructions to deploy the **Enterprise Logistics Email AI Agent** locally or using Docker.

---

## 1. Running Locally (Development Mode)

### Prerequisites
- Python 3.11+ (running in workspace `.venv` recommended)
- Node.js 18+

### Step 1: Backend Setup
1. Copy `.env.template` to `.env` and fill out necessary credentials.
2. Initialize and run the FastAPI server:
   ```bash
   cd logistics-email-agent/backend
   uvicorn main:app --reload --port 8001
   ```
3. The API docs will be available at `http://localhost:8001/docs`.

### Step 2: Frontend Setup
1. Install node dependencies:
   ```bash
   cd logistics-email-agent/frontend
   npm install
   ```
2. Launch Vite development server:
   ```bash
   npm run dev
   ```
3. Open `http://localhost:5173` to see the dashboard.

---

## 2. Docker Deployment (Production Mode)

We package both services as independent containers using Docker Compose.

### Build & Start Containers
Run from the `logistics-email-agent` directory:
```bash
docker-compose up --build -d
```

The services will bind to:
- **Frontend Dashboard**: `http://localhost:80`
- **FastAPI Backend Server**: `http://localhost:8001`
