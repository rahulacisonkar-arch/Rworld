import { Queue } from 'bullmq';

const connection = {
  host: process.env.REDIS_HOST || 'localhost',
  port: parseInt(process.env.REDIS_PORT || '6379', 10)
};

export const samQueue = new Queue('sam-segmentation', { connection });
export const yoloQueue = new Queue('yolo-detection', { connection });
export const ocrQueue = new Queue('ocr-processing', { connection });
export const llmQueue = new Queue('llm-inference', { connection });
export const weatherQueue = new Queue('weather-update', { connection });
export const permitQueue = new Queue('permit-lookup', { connection });
export const solarQueue = new Queue('solar-recalculation', { connection });
export const reportQueue = new Queue('report-generation', { connection });
export const auditQueue = new Queue('audit-logging', { connection });

export const allQueues = {
  samQueue,
  yoloQueue,
  ocrQueue,
  llmQueue,
  weatherQueue,
  permitQueue,
  solarQueue,
  reportQueue,
  auditQueue
};
