FROM python:3.11-slim
WORKDIR /app
COPY rworld-intelligence/backend/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY rworld-intelligence/backend/app ./app
COPY rworld-intelligence/modules /modules
EXPOSE 8000
CMD ["sh", "-c", "uvicorn app.main:app --host 0.0.0.0 --port ${PORT:-8000}"]
