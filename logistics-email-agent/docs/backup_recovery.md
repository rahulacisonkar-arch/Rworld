# Backup and Recovery Plan

This document outlines the backup processes and recovery actions for the **Logistics Email Agent**.

---

## 1. Backup Strategy

### Local Database Backup (`agent.db`)
- The SQLite file contains local audit logs, shipment draft queue states, and configuration overrides.
- **Action**: Periodically run a cron job or scheduled script to snapshot `agent.db` to a safe location:
  ```bash
  sqlite3 agent.db ".backup 'backup/agent_backup_$(date +%F).db'"
  ```

### Logistics Portal DB Backup (`artee_shipping`)
- The MySQL database maps tracking states and label details.
- **Action**: Back up the tables nightly using `mysqldump`:
  ```bash
  mysqldump -u root artee_shipping > backup/portal_backup_$(date +%F).sql
  ```

---

## 2. Disaster Recovery Actions

1. **System Reinstallation**: Re-deploy using Docker Compose as documented in the deployment guide.
2. **Database Restoration**:
   - Restore SQLite database by copying the snapshot back to `logistics-email-agent/backend/agent.db`.
   - Restore MySQL database:
     ```bash
     mysql -u root artee_shipping < backup/portal_backup_date.sql
     ```
3. **Queue Resync**: Bypassing or restarting the container scans any un-processed mock emails located in the mock directory.
