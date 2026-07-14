import { auditQueue } from '../queues/QueueManager';

export interface AuditRecord {
  eventType: 'User Login' | 'Measurement Edited' | 'Proposal Generated' | 'Permit Retrieved' | 'AI Used' | 'PDF Exported';
  userId: string;
  tenantId: string;
  details: Record<string, any>;
  ipAddress?: string;
  timestamp: Date;
}

export class AuditLogger {
  async log(record: Omit<AuditRecord, 'timestamp'>): Promise<void> {
    try {
      const payload: AuditRecord = {
        ...record,
        timestamp: new Date()
      };
      
      // Dispatch structured log event to BullMQ
      await auditQueue.add('persist-audit-log', payload);
      
      // Print in structured JSON log format for OpenTelemetry / Prometheus scraping
      console.log(JSON.stringify({
        level: 'INFO',
        message: `[Audit] Event registered: ${payload.eventType}`,
        ...payload
      }));
    } catch (error) {
      console.error('AuditLogger error:', error);
    }
  }
}

export const auditLogger = new AuditLogger();
