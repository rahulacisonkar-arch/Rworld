import nodemailer from 'nodemailer';
import { smtpConfig } from '../config/smtp.config';

export class SmtpProvider {
  private transporter: nodemailer.Transporter;

  constructor() {
    this.transporter = nodemailer.createTransport({
      host: smtpConfig.host,
      port: smtpConfig.port,
      secure: smtpConfig.secure,
      auth: smtpConfig.auth,
      tls: {
        rejectUnauthorized: false
      }
    });
  }

  async sendMail(to: string, subject: string, htmlContent: string): Promise<boolean> {
    try {
      const info = await this.transporter.sendMail({
        from: smtpConfig.from,
        to,
        subject,
        html: htmlContent
      });
      console.log(`[SMTP Provider] Email successfully dispatched to ${to}. MessageId: ${info.messageId}`);
      return true;
    } catch (error) {
      console.error(`[SMTP Provider] Error sending email to ${to}:`, error);
      throw error;
    }
  }
}

export const smtpProvider = new SmtpProvider();
