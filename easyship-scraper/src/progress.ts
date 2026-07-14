import * as fs from 'fs';
import { CONFIG } from './config';
import { ProgressState } from './types';
import { logger } from './logger';

export function loadProgress(): ProgressState | null {
  try {
    if (fs.existsSync(CONFIG.PROGRESS_PATH)) {
      const data = fs.readFileSync(CONFIG.PROGRESS_PATH, 'utf-8');
      return JSON.parse(data) as ProgressState;
    }
  } catch (error) {
    logger.error('Failed to load progress state', { error: (error as Error).message });
  }
  return null;
}

export function saveProgress(state: ProgressState): void {
  try {
    fs.writeFileSync(CONFIG.PROGRESS_PATH, JSON.stringify(state, null, 2), 'utf-8');
    logger.info('Progress saved', {
      currentPage: state.currentPage,
      currentShipmentIndex: state.currentShipmentIndex,
      lastTrackingNumber: state.lastTrackingNumber,
      totalProcessed: state.totalProcessed,
      totalFailed: state.totalFailed
    });
  } catch (error) {
    logger.error('Failed to save progress state', { error: (error as Error).message });
  }
}

export function clearProgress(): void {
  try {
    if (fs.existsSync(CONFIG.PROGRESS_PATH)) {
      fs.unlinkSync(CONFIG.PROGRESS_PATH);
      logger.info('Progress state cleared');
    }
  } catch (error) {
    logger.error('Failed to clear progress state', { error: (error as Error).message });
  }
}
