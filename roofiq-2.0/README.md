# Shekhar RoofIQ AI 2.0 (Open Source Edition)

RoofIQ AI 2.0 is an enterprise-grade, open-source AI-powered roofing platform for measurement, damage detection, permitting, solar potential, and procurement planning.

---

## 🛠 Data Flow & Service Calling Chain

```
[Frontend]
    │
    ▼
[API Gateway]
    │
    ▼
[Property Service]
    │
    ▼
[AI Service]
    │
    ▼
[GIS Service]
    │
    ▼
[Weather Service]
    │
    ▼
[Permit Service]
    │
    ▼
[Solar Service]
```

*   **Frontend:** Next.js (App Router), React, TypeScript, Tailwind CSS, shadcn/ui, MapLibre GL, Leaflet, OpenLayers.
*   **Backend Core:** Node.js (Express/NestJS concept), Prisma ORM, Better Auth, BullMQ.
*   **AI Microservice:** FastAPI, Python, PyTorch, YOLOv11 (OBB), Segment Anything 2 (SAM 2), transformers, llama.cpp.
*   **Databases & Stores:** PostgreSQL (with PostGIS extension), Qdrant Vector DB (embeddings), Redis (BullMQ queue), MinIO (S3-compatible asset store).
*   **Deployment:** Docker Compose, Kubernetes, GitHub Actions CI/CD.

---

## 📂 Project Directory Structure

```
roofiq-2.0/
├── .github/
│   └── workflows/
│       └── ci-cd.yml             # CI/CD Pipeline Configuration
├── apps/
│   ├── ai-service/               # FastAPI AI Microservice (Python)
│   │   ├── app/
│   │   │   ├── api/              # SAM / YOLO Inference Routes
│   │   │   ├── core/             # Pipeline Orchestrators
│   │   │   └── models/           # llama.cpp & YOLO model loaders
│   │   └── pyproject.toml        # Python Dependencies (uv)
│   ├── backend-core/             # Node.js Backend Core (TypeScript)
│   │   ├── prisma/
│   │   │   └── schema.prisma     # PostGIS database architecture
│   │   ├── src/
│   │   │   ├── controllers/      # Route logic
│   │   │   ├── jobs/             # BullMQ Workers
│   │   │   └── plugins/          # Extensible plugin interfaces
│   │   ├── package.json          # Core package definitions
│   │   └── openapi.yaml          # API Specifications
│   └── frontend/                 # Next.js Client (TypeScript)
│       ├── src/
│       │   ├── app/              # App Router
│       │   └── components/       # Map & Copilot UI
│       └── package.json          # Next.js config
├── docker/                       # Service Dockerfiles
│   ├── Dockerfile.frontend
│   ├── Dockerfile.backend
│   └── Dockerfile.ai
├── docker-compose.yml            # Multi-container local orchestration
└── CODING_STANDARDS.md           # Engineering guidelines
```

---

## 🚀 Getting Started

Ensure you have **Docker** and **Node.js** installed, then run:

```bash
# Build and start all services (PostGIS, Redis, Qdrant, MinIO, Frontend, Backend, AI)
docker-compose up --build
```
