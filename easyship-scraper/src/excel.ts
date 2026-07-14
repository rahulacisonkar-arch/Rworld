import * as ExcelJS from 'exceljs';
import * as fs from 'fs';
import * as path from 'path';
import * as os from 'os';
import { CONFIG } from './config';
import { ShipmentData } from './types';
import { logger } from './logger';
import { parseRobustDate } from './utils';

export class ExcelManager {
  private filePath: string;

  constructor() {
    this.filePath = CONFIG.EXCEL_PATH;
  }

  private getTimestamp(): string {
    const now = new Date();
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;
  }

  private getHeaders(): string[] {
    return [
      'VENDORS / STORES',
      'DATE',
      'TRACKING NO.',
      'TRACKING NO. 2',
      'REF NO.1',
      'REF NO.2',
      'RECEIVER',
      'UPS CHARGE',
      'DELIVERY DATE',
      'SHIPPING FROM',
      'WEIGHT',
      'DIMENSION',
      'FREIGHT CHARGE FROM CMR'
    ];
  }

  private normalizeText(text: string): string {
    if (!text) return '';
    // Remove hidden/control characters and trim
    return text.replace(/[\u0000-\u001F\u007F-\u009F]/g, '').trim();
  }

  private normalizeDate(dateStr: string): string {
    const cleaned = this.normalizeText(dateStr);
    if (!cleaned || cleaned === 'N/A') return 'N/A';
    
    try {
      const parsed = parseRobustDate(cleaned);
      if (parsed) {
        const year = parsed.getFullYear();
        const month = String(parsed.getMonth() + 1).padStart(2, '0');
        const day = String(parsed.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
      }
    } catch {
      // Return original cleaned if parser fails
    }
    return cleaned;
  }

  private normalizeCurrency(valStr: string): string {
    const cleaned = this.normalizeText(valStr);
    if (!cleaned || cleaned === 'N/A') return 'N/A';
    
    // Extract numeric values, matching decimals and digits
    const matched = cleaned.replace(/[^0-9.]/g, '');
    if (matched) {
      const parsed = parseFloat(matched);
      if (!isNaN(parsed)) {
        return parsed.toFixed(2);
      }
    }
    return cleaned;
  }

  async trackingNumberExists(trackingNo: string): Promise<boolean> {
    if (!trackingNo) return false;
    const normalized = trackingNo.trim().toUpperCase();
    if (normalized === 'N/A' || normalized === '') return false;
    if (!fs.existsSync(this.filePath)) return false;

    try {
      const workbook = new ExcelJS.Workbook();
      await workbook.xlsx.readFile(this.filePath);
      const worksheet = workbook.getWorksheet(1);
      if (!worksheet) return false;

      let exists = false;
      worksheet.eachRow((row, rowNumber) => {
        if (rowNumber > 1) {
          const cellValue = row.getCell(3).value;
          if (cellValue && cellValue.toString().trim().toUpperCase() === normalized) {
            exists = true;
          }
        }
      });
      return exists;
    } catch (error) {
      logger.error('Error checking duplicate tracking number', { error: (error as Error).message });
      return false;
    }
  }

  private applySpecialCompanyRules(shipment: ShipmentData): { receiver: string; shippingFrom: string; vendorsStores: string } {
    let receiver = this.normalizeText(shipment.receiver);
    let shippingFrom = this.normalizeText(shipment.shippingFrom);
    let vendorsStores = this.normalizeText(shipment.vendorsStores);

    const targetCompanies = [
      /ARTEE/i,
      /ARTI/i,
      /Good Goods/i,
      /Printers Alley/i
    ];

    const isTarget = (text: string) => targetCompanies.some(regex => regex.test(text));

    if (isTarget(receiver)) {
      if (shipment.receiverCity && shipment.receiverCity !== 'N/A') {
        receiver = this.normalizeText(shipment.receiverCity);
      }
    }

    if (isTarget(vendorsStores)) {
      if (shipment.senderCity && shipment.senderCity !== 'N/A') {
        vendorsStores = this.normalizeText(shipment.senderCity);
      }
    }

    return { receiver, shippingFrom, vendorsStores };
  }

  async appendShipment(shipment: ShipmentData): Promise<void> {
    const workbook = new ExcelJS.Workbook();
    let worksheet: ExcelJS.Worksheet;

    try {
      if (fs.existsSync(this.filePath)) {
        await workbook.xlsx.readFile(this.filePath);
        worksheet = workbook.getWorksheet(1) || workbook.addWorksheet('Shipments');
      } else {
        worksheet = workbook.addWorksheet('Shipments');
        worksheet.views = [{ state: 'frozen', ySplit: 1 }];
        const headerRow = worksheet.addRow(this.getHeaders());
        headerRow.font = { bold: true };
        worksheet.autoFilter = {
          from: { row: 1, column: 1 },
          to: { row: 1, column: this.getHeaders().length }
        };
      }

      const { receiver, shippingFrom, vendorsStores } = this.applySpecialCompanyRules(shipment);

      const rowData = [
        vendorsStores,
        this.normalizeDate(shipment.date),
        this.normalizeText(shipment.trackingNo),
        this.normalizeText(shipment.trackingNo2),
        this.normalizeText(shipment.refNo1),
        this.normalizeText(shipment.refNo2),
        receiver,
        this.normalizeCurrency(shipment.upsCharge),
        this.normalizeDate(shipment.deliveryDate),
        shippingFrom,
        this.normalizeText(shipment.weight),
        this.normalizeText(shipment.dimension),
        this.normalizeCurrency(shipment.freightChargeFromCmr)
      ];

      worksheet.addRow(rowData);

      // Auto-fit columns safely
      for (let colNum = 1; colNum <= this.getHeaders().length; colNum++) {
        const column = worksheet.getColumn(colNum);
        let maxLen = 0;
        column.eachCell({ includeEmpty: true }, (cell) => {
          const val = cell.value ? cell.value.toString() : '';
          if (val.length > maxLen) {
            maxLen = val.length;
          }
        });
        column.width = Math.max(maxLen + 3, 10);
      }

      let mainSaveError: Error | null = null;
      try {
        await workbook.xlsx.writeFile(this.filePath);
        logger.info('Excel updated successfully', { trackingNo: shipment.trackingNo });
      } catch (error) {
        mainSaveError = error as Error;
        logger.error('Error writing to main Excel file', { error: mainSaveError.message });
      }

      try {
        const desktopDir = path.join(os.homedir(), 'Desktop', 'Easyship Data');
        if (!fs.existsSync(desktopDir)) {
          fs.mkdirSync(desktopDir, { recursive: true });
        }
        const timestamp = this.getTimestamp();
        const backupPath = path.join(desktopDir, `Easyship_Data_${timestamp}.xlsx`);
        await workbook.xlsx.writeFile(backupPath);
        logger.info('Backup Excel saved to Desktop', { backupPath });
      } catch (backupError) {
        logger.error('Error saving backup Excel to Desktop', { error: (backupError as Error).message });
      }

      if (mainSaveError) {
        throw mainSaveError;
      }
    } catch (error) {
      throw error;
    }
  }
}
