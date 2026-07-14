import fs from 'fs';
import path from 'path';

export class EmailAuditLogger {
  private logPath: string;

  constructor() {
    this.logPath = path.join(__dirname, '../../email_delivery_audit.log');
  }

  log(recipient: string, template: string, success: boolean, details?: string) {
    const logRecord = {
      timestamp: new Date().toISOString(),
      recipient,
      template,
      success,
      details: details || ''
    };
    
    const logLine = `${JSON.stringify(logRecord)}\n`;
    fs.appendFileSync(this.logPath, logLine, 'utf8');
    console.log(`[Email Audit] ${success ? 'SUCCESS' : 'FAILED'} to ${recipient} using template ${template}`);
  }
}

export const emailAudit = new EmailAuditLogger();
