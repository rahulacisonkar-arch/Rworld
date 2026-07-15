FROM python:3.11-slim

WORKDIR /app

# Render's Docker build context = repo root
# so all paths are relative to the repo root
COPY rworld-intelligence/backend/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy backend app code
COPY rworld-intelligence/backend/app ./app

# Copy modules directory
COPY rworld-intelligence/modules ./modules

# Create database directory (DB_PATH env var points here)
RUN mkdir -p /app/database

ENV DB_PATH=/app/database/rworld.db

EXPOSE 8000
CMD ["sh", "-c", "uvicorn app.main:app --host 0.0.0.0 --port ${PORT:-8000}"]
