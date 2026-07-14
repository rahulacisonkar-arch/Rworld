import fs from 'fs';
import path from 'path';
import { smtpProvider } from '../providers/smtp';
import { emailAudit } from '../logs/email.audit';

export class EmailService {
  private templatesCache: Map<string, string> = new Map();

  async sendTemplateEmail(
    to: string, 
    templateName: 'welcome' | 'password-reset' | 'analysis-complete' | 'proposal-ready' | 'report-ready', 
    payload: Record<string, any>
  ): Promise<boolean> {
    try {
      const htmlTemplate = this.loadTemplate(templateName);
      const processedHtml = this.compileTemplate(htmlTemplate, payload);
      
      const subjectMap: Record<string, string> = {
        'welcome': 'Verify your RoofIQ AI Account',
        'password-reset': 'Reset Your RoofIQ AI Password',
        'analysis-complete': 'Roof Analysis Completed Successfully',
        'proposal-ready': 'Your Roofing Proposal is Ready',
        'report-ready': 'Your Technical PDF Reports are Ready'
      };

      const subject = subjectMap[templateName] || 'RoofIQ AI Notification';
      const success = await smtpProvider.sendMail(to, subject, processedHtml);
      
      emailAudit.log(to, templateName, success);
      return success;
    } catch (error: any) {
      emailAudit.log(to, templateName, false, error.message);
      throw error;
    }
  }

  private loadTemplate(name: string): string {
    if (this.templatesCache.has(name)) {
      return this.templatesCache.get(name)!;
    }

    const templatePath = path.join(__dirname, `../templates/${name}.html`);
    if (!fs.existsSync(templatePath)) {
      throw new Error(`Email template ${name} not found at ${templatePath}`);
    }

    const content = fs.readFileSync(templatePath, 'utf8');
    this.templatesCache.set(name, content);
    return content;
  }

  private compileTemplate(template: string, data: Record<string, any>): string {
    let result = template;
    for (const key of Object.keys(data)) {
      const placeholder = new RegExp(`{{${key}}}`, 'g');
      result = result.replace(placeholder, String(data[key]));
    }
    return result;
  }
}

export const emailService = new EmailService();
