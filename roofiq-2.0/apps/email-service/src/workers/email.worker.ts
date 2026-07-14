import { Worker, Job } from 'bullmq';
import { emailService } from '../services/email.service';

const connection = {
  host: process.env.REDIS_HOST || 'localhost',
  port: parseInt(process.env.REDIS_PORT || '6379', 10)
};

export const emailWorker = new Worker('transactional-emails', async (job: Job) => {
  const { to, template, payload } = job.data;
  console.log(`[Email Worker] Processing email dispatch to ${to} using template: ${template}`);
  await emailService.sendTemplateEmail(to, template, payload);
}, { connection });

console.log('Email Background Worker Initialized.');
