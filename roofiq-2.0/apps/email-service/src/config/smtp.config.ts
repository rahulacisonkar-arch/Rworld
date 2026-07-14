import dotenv from 'dotenv';
dotenv.config();

export const smtpConfig = {
  host: process.env.SMTP_HOST || 'smtp.mailtrap.io',
  port: parseInt(process.env.SMTP_PORT || '587', 10),
  secure: process.env.SMTP_SECURE === 'true', // true for 465, false for other ports
  auth: {
    user: process.env.SMTP_USER || 'mock_user',
    pass: process.env.SMTP_PASS || 'mock_pass'
  },
  from: process.env.SMTP_FROM || '"RoofIQ AI Notification" <no-reply@roofiq-ai.com>'
};
