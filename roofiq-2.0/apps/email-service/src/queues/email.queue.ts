import { Queue } from 'bullmq';

const connection = {
  host: process.env.REDIS_HOST || 'localhost',
  port: parseInt(process.env.REDIS_PORT || '6379', 10)
};

export const emailQueue = new Queue('transactional-emails', { connection });

export async function queueEmail(
  to: string, 
  template: 'welcome' | 'password-reset' | 'analysis-complete' | 'proposal-ready' | 'report-ready', 
  payload: Record<string, any>
) {
  await emailQueue.add('send-email', { to, template, payload });
  console.log(`[Email Queue] Queued transactional mail to ${to} using template ${template}`);
}
