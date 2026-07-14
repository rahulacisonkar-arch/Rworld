import * as winston from 'winston';
import * as path from 'path';
import * as fs from 'fs';
import { CONFIG } from './config';

const logDir = path.dirname(CONFIG.LOG_FILE_PATH);
if (!fs.existsSync(logDir)) {
  fs.mkdirSync(logDir, { recursive: true });
}

export const logger = winston.createLogger({
  level: 'info',
  format: winston.format.combine(
    winston.format.timestamp(),
    winston.format.json()
  ),
  transports: [
    new winston.transports.Console({
      format: winston.format.combine(
        winston.format.colorize(),
        winston.format.simple()
      )
    }),
    new winston.transports.File({ filename: CONFIG.LOG_FILE_PATH }),
  ],
});
