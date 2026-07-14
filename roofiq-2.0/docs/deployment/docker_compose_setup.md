# Docker Compose Deployments

The platform organizes multi-container runtimes inside a unified local compose file.

---

## 1. Core Services

- **`db`**: PostgreSQL 15 image loaded with PostGIS GIS plugins (`postgis/postgis:15-3.3`).
- **`redis`**: Key-value data store managing BullMQ queues and jobs states.
- **`qdrant`**: High-performance vector database hosting building codes embeddings.
- **`minio`**: S3-compatible object store organizing blueprints and proposal PDF exports.
- **`backend-core`**: Core Node.js API Gateway.
- **`ai-service`**: Python FastAPI modeling service.
- **`email-service`**: Asynchronous transactional mail dispatcher.

---

## 2. Booting Services

To build and start the entire workspace architecture locally:
```bash
docker-compose up --build
```
This automatically runs database migrations, exposes port `3000` for core REST routes, and port `8000` for FastAPI documentations.
