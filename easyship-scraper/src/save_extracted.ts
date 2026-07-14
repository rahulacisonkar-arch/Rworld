import * as fs from 'fs';
import * as path from 'path';
import { ExcelManager } from './excel';
import { ShipmentData } from './types';
import { logger } from './logger';

async function main() {
  const jsonPath = path.resolve(process.cwd(), 'storage', 'extracted_shipment.json');
  if (!fs.existsSync(jsonPath)) {
    logger.error('Extracted shipment JSON file not found');
    process.exit(1);
  }

  try {
    const raw = fs.readFileSync(jsonPath, 'utf-8');
    const data = JSON.parse(raw);

    const shipment: ShipmentData = {
      vendorsStores: data['VENDORS / STORES'] || 'N/A',
      date: data['DATE'] || 'N/A',
      trackingNo: data['TRACKING NO.'] || 'N/A',
      trackingNo2: data['TRACKING NO. 2'] || 'N/A',
      refNo1: data['REF NO.1'] || 'N/A',
      refNo2: data['REF NO.2'] || 'N/A',
      receiver: data['RECEIVER'] || 'N/A',
      upsCharge: data['UPS CHARGE'] || 'N/A',
      deliveryDate: data['DELIVERY DATE'] || 'N/A',
      shippingFrom: data['SHIPPING FROM'] || 'N/A',
      weight: data['WEIGHT'] || 'N/A',
      dimension: data['DIMENSION'] || 'N/A',
      freightChargeFromCmr: data['FREIGHT CHARGE FROM CMR'] || 'N/A'
    };

    const excelManager = new ExcelManager();
    await excelManager.appendShipment(shipment);
    logger.info('Excel file generated successfully.');
  } catch (error) {
    logger.error('Failed to save extracted data to Excel', { error: (error as Error).message });
    process.exit(1);
  }
}

main();
