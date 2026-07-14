# Database Backups & Disaster Recovery Guide

This guide details the strategy to perform daily backup routines and restore states in the event of a cluster crash.

---

## 1. Backup Strategy & Schedules

To ensure data integrity, we schedule backups for two primary data stores:
1. **PostgreSQL Database**: Holds parcel entries and coordinates geometries.
2. **MinIO Object Store**: Houses user blueprints uploads, imagery, and versioned PDF reports.

---

## 2. Automated Backup Scripts

### PostgreSQL Backup Script (`scripts/backup-db.sh`)
Save the script on your host machine to run daily backups:
```bash
#!/bin/bash
BACKUP_DIR="/opt/roofiq/backups/db"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
FILENAME="roofiq_db_${TIMESTAMP}.sql"

mkdir -p "$BACKUP_DIR"

# Run PostgreSQL pg_dump inside the docker database container
docker exec roofiq-database pg_dump -U postgres -d roofiq_db > "${BACKUP_DIR}/${FILENAME}"

# Compress the backup file
gzip "${BACKUP_DIR}/${FILENAME}"

# Retain only the last 30 days of backups
find "$BACKUP_DIR" -type f -mtime +30 -name "*.sql.gz" -delete

echo "[Backup Completed] ${FILENAME}.gz generated."
```

### MinIO/Uploads Backup Script (`scripts/backup-minio.sh`)
Synchronizes S3 bucket items to an offline archive:
```bash
#!/bin/bash
BACKUP_DIR="/opt/roofiq/backups/minio"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
FILENAME="roofiq_assets_${TIMESTAMP}.tar.gz"

mkdir -p "$BACKUP_DIR"

# Archive the MinIO persistent volume path
tar -czf "${BACKUP_DIR}/${FILENAME}" -C /var/lib/docker/volumes/roofiq-2_miniodata/_data .

# Retain the last 14 days of assets
find "$BACKUP_DIR" -type f -mtime +14 -name "*.tar.gz" -delete

echo "[Backup Completed] Assets archive ${FILENAME} generated."
```

---

## 3. Cron Schedules Configuration

To run backups automatically, add these records to your VPS crontab (`crontab -e`):
```text
# Run database backups every day at 1:00 AM
0 1 * * * /opt/roofiq/scripts/backup-db.sh >> /var/log/roofiq-backup.log 2>&1

# Run MinIO assets sync every day at 2:00 AM
0 2 * * * /opt/roofiq/scripts/backup-minio.sh >> /var/log/roofiq-backup.log 2>&1
```

---

## 4. Disaster Recovery & Restores

### Restore PostgreSQL Database
To restore your database from a backup file:
```bash
# Decompress the backup
gunzip /opt/roofiq/backups/db/roofiq_db_20260625_010000.sql.gz

# Inject back into the active database container
cat /opt/roofiq/backups/db/roofiq_db_20260625_010000.sql | docker exec -i roofiq-database psql -U postgres -d roofiq_db
```

### Restore MinIO Assets
To restore assets files:
```bash
# Extract the archive back to the docker volume data folder
tar -xzf /opt/roofiq/backups/minio/roofiq_assets_20260625_020000.tar.gz -C /var/lib/docker/volumes/roofiq-2_miniodata/_data/
```
