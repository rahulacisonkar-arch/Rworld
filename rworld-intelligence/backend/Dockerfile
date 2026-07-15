FROM python:3.11-slim

# Set working directory to /app/backend so relative paths work
WORKDIR /app/backend

# Copy requirements and install
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy the entire rworld-intelligence project structure
# so database/ directory is at /app/database/ (2 levels up from app/)
COPY . /app/backend/
# Create the database directory so _find_db_path() can locate it
RUN mkdir -p /app/database

# Set env var so db.py always uses the right path
ENV DB_PATH=/app/database/rworld.db

EXPOSE 8000
CMD ["sh", "-c", "uvicorn app.main:app --host 0.0.0.0 --port ${PORT:-8000}"]
