# Production, Troubleshooting, & Scaling Checklist

This guide covers operational guidelines for troubleshooting errors, scaling containers, and keeping the platform secure.

---

## 1. Local Development Cheat Sheet

Use these standard commands on your local host for testing:

| Objective | Command |
| :--- | :--- |
| **Start Services** | `docker compose up -d` |
| **Stop Services** | `docker compose down` |
| **View Logs** | `docker compose logs -f --tail=100` |
| **Rebuild Images** | `docker compose build --no-cache` |
| **Reset Database** | `npx prisma db push --force-reset` |
| **Seed Database** | `npx prisma db seed` |

---

## 2. Security Checklist

- [ ] **HTTPS Enforced**: Check that all HTTP traffic is redirected to HTTPS via Coolify's domain manager.
- [ ] **Production Database Credentials**: Never commit database passwords; double-check that `.env` contains secure hashes.
- [ ] **Non-Root Containers**: Ensure the Dockerfiles run using a non-privileged `node` or custom user instead of root permissions.
- [ ] **Secure Headers**: Verify that `helmet` is active inside the Express gateway.

---

## 3. Troubleshooting Guide

### Queue Workers Are Hanging / Jobs Freeze
- **Problem**: Jobs are stuck in the active state inside BullMQ.
- **Solution**: Check that the Redis server has not run out of memory. Flush stalled queues:
  ```bash
  redis-cli flushall
  ```
- **Check logs**: Check if the worker is throwing type errors:
  ```bash
  docker logs roofiq-backend
  ```

### Database Connection Refused
- **Problem**: Backend Core throws connection refused error `ECONNREFUSED 5432`.
- **Solution**: Confirm PostgreSQL container is healthy:
  ```bash
  docker ps -f name=roofiq-database
  ```
- Check that the PostGIS schema matches the prisma configuration.

---

## 4. Horizontal & Vertical Scaling

### Scaling the Express Gateway (`backend-core`)
- If API latency increases, scale the Express containers horizontally by updating the Coolify instances count or compose replicas count:
  ```yaml
  backend-core:
    deploy:
      replicas: 3
  ```

### Scaling the AI Service (`ai-service`)
- Deep learning inference (SAM 2, YOLO) is CPU/GPU bound. Move the Python microservice to a GPU-enabled VPS node and change `AI_SERVICE_URL` in the Express `.env` configurations to route requests to the new endpoint.

---

## 5. Production Environment Checklist

Before every deployment, verify the following checklist is completed:
- [ ] **✅ All unit tests pass**: Run the standalone test suite in each package directory.
- [ ] **✅ Integration tests pass**: Execute Jest contract checks.
- [ ] **✅ Database migrations applied**: Verify Prisma schema sync is completed.
- [ ] **✅ Backup completed**: Run `backup-db.sh` and `backup-minio.sh` script files.
- [ ] **✅ Environment variables validated**: Confirm `.env` fields contain secure hashes.
- [ ] **✅ Docker images built successfully**: Verify builds compile with zero errors.
- [ ] **✅ Health endpoints respond correctly**: Query `/health` and `/ready` routes.
- [ ] **✅ Rollback image available**: Ensure the previous working tag exists.
- [ ] **✅ SSL certificates valid**: Check the Let's Encrypt renewal loop inside Coolify.
- [ ] **✅ Disk space and memory sufficient**: Check node metrics via Prometheus/Grafana.

---

## 6. Release Workflow

We use a structured branch release pipeline:
```text
[develop] ➔ [Release Candidate] ➔ [Staging] ➔ [Smoke Tests] ➔ [Production] ➔ [Tag Release]
```
- **main**: Reserved exclusively as the production branch.
- **develop**: Standard integration branch where developers merge feature tasks.
- **Release Candidate (RC)**: Frozen build generated from `develop` for validation.
- **Staging & Smoke Tests**: RC build is deployed to staging. Automated integration tests verify API contracts.
- **Production Tagging**: The validated build is pushed to `main` and tagged (e.g. `v2.0.0`) inside Git.

