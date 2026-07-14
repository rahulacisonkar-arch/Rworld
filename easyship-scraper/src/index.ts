import { EasyshipScraper } from './scraper';
import { logger } from './logger';

async function main() {
  logger.info('Starting Easyship shipment extraction bot...');
  const scraper = new EasyshipScraper();
  
  try {
    await scraper.run();
    logger.info('Easyship shipment extraction bot execution finished successfully.');
  } catch (error) {
    logger.error('Critical failure running Easyship shipment extraction bot', {
      error: (error as Error).message
    });
    process.exit(1);
  }
}

main();
