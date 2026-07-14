# Developer Guide

Welcome to RoofIQ AI 2.0. Follow these instructions to set up the development environment.

---

## 1. Setup Prerequisites

Ensure you have installed:
- **Node.js** (v20+)
- **Docker & Docker Compose**
- **Python** (3.11+)

---

## 2. Quickstart Execution

1. **Clone and Navigate**:
   ```bash
   cd roofiq-2.0
   ```
2. **Environment File**:
   Create a `.env` in `apps/backend-core/` specifying the database URL:
   ```bash
   DATABASE_URL="postgresql://postgres:postgres@localhost:5432/roofiq_db?schema=public"
   ```
3. **Run Multi-Containers**:
   ```bash
   docker-compose up --build
   ```
4. **Compile TypeScript Projects**:
   - For backend-core:
     ```bash
     cd apps/backend-core && npm run build
     ```
   - For measurement-engine:
     ```bash
     cd apps/measurement-engine && npm run build
     ```
