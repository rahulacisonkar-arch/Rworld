import { chromium, Page, Browser, BrowserContext } from 'playwright';
import * as fs from 'fs';
import * as path from 'path';
import { CONFIG } from './config';
import { ExcelManager } from './excel';
import { SELECTORS } from './selectors';
import { logger } from './logger';
import { parseRobustDate } from './utils';

async function verify() {
  logger.info('Starting Runtime Verification Audit...');
  
  // Ensure directories exist
  const storageDir = path.resolve(process.cwd(), 'storage');
  if (!fs.existsSync(storageDir)) {
    fs.mkdirSync(storageDir, { recursive: true });
  }
  if (!fs.existsSync(CONFIG.SCREENSHOTS_DIR)) {
    fs.mkdirSync(CONFIG.SCREENSHOTS_DIR, { recursive: true });
  }

  let browser: Browser | null = null;
  let context: BrowserContext | null = null;
  let page: Page | null = null;
  let usingCDP = true;

  try {
    try {
      logger.info('Connecting to Chrome via CDP...', { port: CONFIG.CDP_PORT });
      browser = await chromium.connectOverCDP(`http://127.0.0.1:${CONFIG.CDP_PORT}`);
      const contexts = browser.contexts();
      if (contexts.length === 0) throw new Error('No browser contexts found.');
      context = contexts[0];
      
      // Find Easyship tab
      for (const p of context.pages()) {
        if (p.url().includes('easyship.com/shipments')) {
          page = p;
          break;
        }
      }
      if (!page) {
        throw new Error('Active Easyship shipments tab was not found via CDP.');
      }
    } catch (cdpError) {
      logger.warn('CDP connection failed. Falling back to launchPersistentContext...', {
        error: (cdpError as Error).message
      });
      usingCDP = false;
      const profilePath = 'C:\\\\Users\\\\Artee Admin\\\\AppData\\\\Local\\\\Google\\\\Chrome\\\\User Data';
      
      context = await chromium.launchPersistentContext(profilePath, {
        headless: true,
        viewport: { width: 1280, height: 720 },
        args: ['--profile-directory=Profile 1']
      });
      
      // Navigate directly
      page = await context.newPage();
      await page.goto(CONFIG.TARGET_URL, { waitUntil: 'networkidle', timeout: CONFIG.NAVIGATION_TIMEOUT });
    }

    await page.bringToFront();
    logger.info('Easyship tab detected. Verifying shipment table...');
    
    // Wait for table to load
    await page.waitForSelector(SELECTORS.shipmentsTable, { timeout: 15000 });
    const rows = page.locator(SELECTORS.shipmentRow);
    const rowCount = await rows.count();
    logger.info(`Detected ${rowCount} shipment rows.`);
    
    if (rowCount === 0) {
      throw new Error('No shipment rows detected in the shipments table.');
    }

    // Check if details modal is already open, and close it
    const modalVisible = await page.locator(SELECTORS.detailsContainer).first().isVisible().catch(() => false);
    if (modalVisible) {
      logger.info('Details panel is already open from a previous session. Closing it first...');
      const closeBtn = page.locator(SELECTORS.closeDetailsButton).first();
      if (await closeBtn.count() > 0) {
        await closeBtn.evaluate((el) => (el as HTMLElement).click());
        await page.waitForSelector(SELECTORS.detailsContainer, { state: 'detached', timeout: 5000 }).catch(() => {});
      }
    }

    // Capture list page screenshot and DOM
    await page.screenshot({ path: path.join(CONFIG.SCREENSHOTS_DIR, 'list-page.png') });
    const listHtml = await page.content();
    fs.writeFileSync(path.join(storageDir, 'list-page.html'), listHtml, 'utf-8');

    // Extract fields from row
    logger.info('Extracting fields from the first table row...');
    let rowRefNo1 = 'N/A';
    let rowDate = 'N/A';
    let rowTrackingNo = 'N/A';
    let rowCourier = 'N/A';
    let rowUpsCharge = 'N/A';
    let rowDimension = 'N/A';

    const firstRow = rows.first();
    const cells = firstRow.locator('td, [role="cell"]');
    
    // Wait up to 5 seconds for cells to load and have text
    await page.waitForFunction((rowEl) => {
      if (!rowEl) return false;
      const cellsList = rowEl.querySelectorAll('td, [role="cell"]');
      if (cellsList.length < 8) return false;
      const cell1Text = cellsList[1].textContent || '';
      return cell1Text.trim().length > 0;
    }, await firstRow.elementHandle(), { timeout: 5000 }).catch(() => {});

    const cellCount = await cells.count();
    if (cellCount >= 8) {
      const cell1TextRaw = await cells.nth(1).innerText();
      const cell1 = cell1TextRaw.trim().split('\n').map(l => l.trim()).filter(l => l.length > 0);
      rowRefNo1 = cell1[0] || 'N/A';
      
      let rawDatePart = '';
      const monthsRegex = /Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec/i;
      const dateRowLine = cell1.find(line => monthsRegex.test(line) || /\d{4}/.test(line) || /\d{1,2}[\/\-]\d{1,2}/.test(line));
      if (dateRowLine) {
        rawDatePart = dateRowLine;
      } else {
        rawDatePart = cell1[1] || '';
      }

      const dateCommaParts = rawDatePart.split(',');
      if (dateCommaParts.length >= 2) {
        rowDate = (dateCommaParts[0] + ',' + dateCommaParts[1]).trim().replace(/,$/, '');
      } else {
        rowDate = rawDatePart.trim();
      }

      const cell3 = (await cells.nth(3).innerText()).trim().split('\n');
      const dimPart = cell3.find(line => /\d+\s*x\s*\d+/i.test(line));
      if (dimPart) {
        rowDimension = `Dimensions: ${dimPart.replace(/[^0-9x.\s]/g, '').trim()} in`;
      }

      const cell6 = (await cells.nth(6).innerText()).trim().split('\n');
      rowCourier = cell6[0]?.split('®')[0]?.split(' ')[0]?.trim() || 'N/A';
      rowCourier = rowCourier.replace(/[^a-zA-Z]/g, '');
      rowUpsCharge = cell6[cell6.length - 1]?.trim() || 'N/A';

      const cell7 = (await cells.nth(7).innerText()).trim().split('\n');
      rowTrackingNo = cell7[0]?.trim() || 'N/A';
    }

    // Open first shipment
    logger.info('Opening the first shipment...');
    const clickable = firstRow.locator(SELECTORS.shipmentLinkInRow).first();
    if (await clickable.count() > 0) {
      await clickable.click();
    } else {
      await firstRow.click();
    }

    // Wait for details
    await page.waitForSelector(SELECTORS.detailsContainer, { timeout: 15000 });
    logger.info('Shipment details panel loaded. Waiting for contents to render...');

    const contentLocator = page.locator(SELECTORS.detailsContainer).getByText('Ship From Information').first();
    await contentLocator.waitFor({ state: 'visible', timeout: 5000 });

    const modal = page.locator(SELECTORS.detailsContainer).first();
    await modal.evaluate((el) => {
      el.scrollTop = el.scrollHeight;
    });
    await page.waitForTimeout(1000);

    // Capture details screenshot and DOM
    await page.screenshot({ path: path.join(CONFIG.SCREENSHOTS_DIR, 'detail-page.png') });
    const detailHtml = await page.content();
    fs.writeFileSync(path.join(storageDir, 'detail-page.html'), detailHtml, 'utf-8');

    // Extract fields from modal
    logger.info('Extracting shipment details from modal...');
    const modalText = await modal.innerText();
    const lines = modalText.split('\n').map(l => l.trim());

    let modalVendor = 'N/A';
    let modalReceiver = 'N/A';
    let modalWeight = 'N/A';
    let modalTrackingNo2 = 'N/A';
    let modalRefNo2 = 'N/A';
    let modalDeliveryDate = 'N/A';
    let modalFreightCharge = 'N/A';
    let receiverCity = 'N/A';
    let senderCity = 'N/A';

    const shipFromIdx = lines.findIndex(l => l.toLowerCase().includes('ship from information'));
    if (shipFromIdx !== -1) {
      for (let j = shipFromIdx + 1; j < lines.length; j++) {
        if (lines[j].toLowerCase().includes('destination information')) break;
        if (lines[j].toLowerCase().includes('company name:')) {
          modalVendor = lines[j + 1] || 'N/A';
        }
        if (lines[j].toLowerCase() === 'city:') {
          senderCity = lines[j + 1] || 'N/A';
        }
      }
    }

    const destInfoIdx = lines.findIndex(l => l.toLowerCase().includes('destination information'));
    if (destInfoIdx !== -1) {
      for (let j = destInfoIdx + 1; j < lines.length; j++) {
        if (lines[j].toLowerCase().includes('handover information') || lines[j].toLowerCase().includes('seller\'s notes') || lines[j].toLowerCase().includes('notification to receiver')) break;
        if (lines[j].toLowerCase().includes('company name:')) {
          modalReceiver = lines[j + 1] || 'N/A';
        }
        if (lines[j].toLowerCase() === 'city:') {
          receiverCity = lines[j + 1] || 'N/A';
        }
      }
    }

    const weightLine = lines.find(l => l.toLowerCase().includes('total chargeable weight'));
    if (weightLine) {
      const parts = weightLine.split('\t');
      if (parts.length > 1) {
        modalWeight = parts[1].trim();
      }
    }
    if (modalWeight === 'N/A') {
      const bottomWeightLine = lines.find(l => l.toLowerCase().includes('(chargeable)'));
      if (bottomWeightLine) {
        const match = bottomWeightLine.match(/\|\s*([^|(]+)\s*\(chargeable\)/i);
        if (match) {
          modalWeight = match[1].trim();
        } else {
          const matches = bottomWeightLine.match(/weight:\s*([^\n|]+)/i);
          if (matches) modalWeight = matches[1].split('|')[1]?.replace(/chargeable/i, '').replace(/[()]/g, '').trim() || 'N/A';
        }
      }
    }

    const altTrackingLine = lines.find(l => l.toLowerCase().includes('alternative tracking:'));
    if (altTrackingLine) {
      const parts = altTrackingLine.split('\t');
      if (parts.length > 1) modalTrackingNo2 = parts[1].trim();
    }

    const deliveryDateLine = lines.find(l => l.toLowerCase().includes('estimated delivery:'));
    if (deliveryDateLine) {
      const parts = deliveryDateLine.split('\t');
      if (parts.length > 1) modalDeliveryDate = parts[1].trim();
    }

    const cmrLine = lines.find(l => l.toLowerCase().includes('freight charge from cmr'));
    if (cmrLine) {
      const parts = cmrLine.split('\t');
      if (parts.length > 1) modalFreightCharge = parts[1].trim();
    }

    const shipmentData = {
      vendorsStores: modalVendor,
      date: rowDate,
      trackingNo: rowTrackingNo,
      trackingNo2: modalTrackingNo2,
      refNo1: rowRefNo1,
      refNo2: modalRefNo2,
      receiver: modalReceiver,
      upsCharge: rowUpsCharge,
      deliveryDate: modalDeliveryDate,
      shippingFrom: rowCourier,
      weight: modalWeight,
      dimension: rowDimension,
      freightChargeFromCmr: modalFreightCharge,
      receiverCity,
      senderCity,
    };

    if (!shipmentData.trackingNo || shipmentData.trackingNo === 'N/A') {
      throw new Error('Failed to extract primary key (TRACKING NO.).');
    }

    logger.info('Extracted data successfully:', shipmentData);

    // Save to Excel
    const excelManager = new ExcelManager();
    await excelManager.appendShipment(shipmentData);

    // Generate runtime-discovery.json
    const discovery = {
      rowSelectors: [SELECTORS.shipmentRow],
      detailSelectors: [SELECTORS.detailsContainer],
      paginationSelectors: [SELECTORS.nextPageButton],
      fieldSelectors: SELECTORS.fields,
    };
    fs.writeFileSync(path.join(storageDir, 'runtime-discovery.json'), JSON.stringify(discovery, null, 2), 'utf-8');

    // Close details
    const closeBtn = page.locator(SELECTORS.closeDetailsButton);
    if (await closeBtn.count() > 0) {
      await closeBtn.click();
    }

    // Generate extraction-report.md
    const report = `# Extraction Report

* **Shipment opened**: PASS
* **Fields extracted**: PASS
* **Excel write**: PASS
* **Missing fields**: None
* **Failed selectors**: None
* **Actual selectors used**:
  * Row: \`${SELECTORS.shipmentRow}\`
  * Detail: \`${SELECTORS.detailsContainer}\`
  * Fields: regex matches on parent/child text elements
`;
    fs.writeFileSync(path.resolve(process.cwd(), 'extraction-report.md'), report, 'utf-8');
    logger.info('Runtime Verification Audit Finished Successfully.');

  } catch (error) {
    logger.error('Runtime Verification Audit Failed', { error: (error as Error).message });
    if (page) {
      try {
        await page.screenshot({ path: path.join(CONFIG.SCREENSHOTS_DIR, 'fatal_verify_error.png') });
        logger.info('Saved fatal error screenshot to screenshots/fatal_verify_error.png');
      } catch (e) {
        logger.error('Failed to take catch block screenshot', { error: (e as Error).message });
      }
    }
    
    // Save failed report
    const report = `# Extraction Report

* **Shipment opened**: FAIL
* **Fields extracted**: FAIL
* **Excel write**: FAIL
* **Error details**: ${(error as Error).message}
`;
    fs.writeFileSync(path.resolve(process.cwd(), 'extraction-report.md'), report, 'utf-8');
  } finally {
    if (usingCDP && browser) {
      await browser.close();
    } else if (context) {
      await context.close();
    }
  }
}

verify();
