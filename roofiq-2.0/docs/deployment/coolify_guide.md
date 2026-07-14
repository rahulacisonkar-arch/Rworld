# Coolify Platform Deployment Guide

Coolify serves as the primary orchestration plane to host RoofIQ AI 2.0 in a production cloud VPS.

---

## 1. Prerequisites & Engine Preparation

1. **VPS Configuration**: Standard Ubuntu 22.04 LTS instance with at least 8GB RAM (to comfortably support SAM 2 and YOLO python pipelines).
2. **Coolify Installation**: Run the standard self-hosted installer command on your server:
   ```bash
   curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
   ```
3. Access the dashboard via port `8000` (e.g. `http://<your-vps-ip>:8000`).

---

## 2. GitHub Connection & Deployment Hook

1. **GitHub App Connection**: Under Coolify **Keys & Sources**, select **GitHub App** and click **Install**. Match permissions for the `RoofIQ-AI` repository.
2. **Repository Project Setup**:
   - Create a new project inside Coolify.
   - Add a resource of type **Docker Compose** or **Application**.
   - Point to your repository and target the `main` branch.
3. **Webhook Setup**: Coolify generates a unique deploy webhook URL (e.g. `https://coolify.yourdomain.com/api/v1/deploy/webhook?uuid=...`).
   - Copy this URL and save it as `COOLIFY_WEBHOOK_URL` in your GitHub repository secrets.
   - Any commit pushed to `main` will build, test, and automatically trigger the Coolify deploy hook.

---

## 3. Environment Variables & Secrets Management

Separate sensitive parameters from non-secret configurations in Coolify's **Environment Variables** panel:

| Variable Name | Environment Type | Value |
| :--- | :--- | :--- |
| `DATABASE_URL` | Production Secret | `postgresql://postgres:<postgres-password>@database:5432/roofiq_db?schema=public` |
| `REDIS_URL` | Production Secret | `redis://redis:6379` |
| `MINIO_ROOT_USER` | Production Secret | `admin` |
| `MINIO_ROOT_PASSWORD` | Production Secret | `<minio-password>` |
| `SMTP_PASS` | Production Secret | `<smtp-secret-password>` |

---

## 4. HTTPS, Domains, & Automatic SSL

Coolify uses Traefik in the background to handle SSL certificates and domain mapping:
1. **Frontend Domain**:
   - In the frontend service panel, input your domain name in the **Domains** field: `https://roofiq.yourdomain.com`.
   - Coolify will automatically request a free Let's Encrypt certificate and route port `3000` traffic.
2. **Core API Gateway Domain**:
   - Set the domain: `https://api.roofiq.yourdomain.com`.
   - Point internal routing to port `5000`.

---

## 5. Automatic Rollbacks

If a build fails or a container's liveness check fails:
- Coolify's orchestration engine will keep the previous running container online.
- You will receive a Telegram or Discord webhook alert indicating a build error, preventing service downtime.
