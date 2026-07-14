import * as dotenv from 'dotenv';
import * as path from 'path';
import * as os from 'os';

dotenv.config();

// When running inside the packaged Electron app, main.ts passes these env vars
// pointing to the user's writable AppData directory.
// When running standalone (npm start), fall back to the current working directory.
function resolveWritablePath(envVar: string, fallback: string): string {
  if (process.env[envVar]) {
    return process.env[envVar]!;
  }
  return path.resolve(process.cwd(), fallback);
}

export const CONFIG = {
  CDP_PORT: parseInt(process.env.CDP_PORT || '9222', 10),
  MAX_RETRIES: parseInt(process.env.MAX_RETRIES || '3', 10),
  NAVIGATION_TIMEOUT: parseInt(process.env.NAVIGATION_TIMEOUT || '30000', 10),
  ACTION_TIMEOUT: parseInt(process.env.ACTION_TIMEOUT || '10000', 10),
  EXCEL_PATH: resolveWritablePath('EXCEL_PATH', 'shipments.xlsx'),
  PROGRESS_PATH: resolveWritablePath('PROGRESS_PATH', 'progress.json'),
  LOG_FILE_PATH: resolveWritablePath('LOG_FILE_PATH', 'logs/easyship.log'),
  SCREENSHOTS_DIR: resolveWritablePath('SCREENSHOTS_DIR', 'screenshots'),
  TARGET_URL: 'https://app.easyship.com/shipments?tab_id=purchased',
  DATE_FROM: process.env.DATE_FROM || '',
  DATE_TO: process.env.DATE_TO || '',
};
