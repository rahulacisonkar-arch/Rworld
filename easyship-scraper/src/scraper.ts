import { chromium, Browser, BrowserContext, Page } from 'playwright';
import * as fs from 'fs';
import * as path from 'path';
import { CONFIG } from './config';
import { logger } from './logger';
import { ExcelManager } from './excel';
import { ProgressState, ShipmentData } from './types';
import { loadProgress, saveProgress, clearProgress } from './progress';
import { SELECTORS } from './selectors';
import { parseRobustDate } from './utils';

export class EasyshipScraper {
  private browser: Browser | null = null;
  private context: BrowserContext | null = null;
  private page: Page | null = null;
  private excelManager: ExcelManager;
  private progress: ProgressState;

  constructor() {
    this.excelManager = new ExcelManager();
    const saved = loadProgress();
    this.progress = saved || {
      currentPage: 1,
      currentShipmentIndex: 0,
      lastTrackingNumber: '',
      totalProcessed: 0,
      totalFailed: 0,
      timestamp: new Date().toISOString(),
    };
  }

  private formatDateString(date: Date): string {
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}_${pad(date.getHours())}-${pad(date.getMinutes())}-${pad(date.getSeconds())}`;
  }

  private async takeScreenshot(name: string): Promise<void> {
    if (!this.page) return;
    try {
      if (!fs.existsSync(CONFIG.SCREENSHOTS_DIR)) {
        fs.mkdirSync(CONFIG.SCREENSHOTS_DIR, { recursive: true });
      }
      const timestamp = this.formatDateString(new Date());
      const fileName = `${timestamp}_${name}_error.png`;
      const filePath = path.join(CONFIG.SCREENSHOTS_DIR, fileName);
      await this.page.screenshot({ path: filePath });
      logger.info('Screenshot captured on failure', { path: filePath });
    } catch (error) {
      logger.error('Failed to take screenshot', { error: (error as Error).message });
    }
  }

  private async retryAction<T>(action: () => Promise<T>, actionName: string): Promise<T> {
    let lastError: Error | null = null;
    let delay = 1000;
    
    for (let attempt = 1; attempt <= CONFIG.MAX_RETRIES; attempt++) {
      try {
        return await action();
      } catch (error) {
        lastError = error as Error;
        logger.warn('Action attempt failed', {
          action: actionName,
          attempt,
          error: lastError.message
        });
        
        if (this.page) {
          await this.takeScreenshot(`${actionName.replace(/\s+/g, '_')}_attempt_${attempt}`);
        }
        
        if (attempt < CONFIG.MAX_RETRIES) {
          // Exponential backoff
          await new Promise((resolve) => setTimeout(resolve, delay));
          delay *= 2;
        }
      }
    }
    throw new Error(`Action "${actionName}" failed after ${CONFIG.MAX_RETRIES} attempts. Last error: ${lastError?.message}`);
  }

  private usingCDP = true;

  private async attachToBrowser(): Promise<void> {
    logger.info('Attaching to existing browser via CDP...', { port: CONFIG.CDP_PORT });
    
    try {
      this.browser = await chromium.connectOverCDP(`http://127.0.0.1:${CONFIG.CDP_PORT}`);
      const contexts = this.browser.contexts();
      if (contexts.length === 0) {
        throw new Error('No browser context found on the connected browser.');
      }
      this.context = contexts[0];
      
      // Find open Easyship tab (any page under the domain)
      const pages = this.context.pages();
      for (const p of pages) {
        const url = p.url();
        if (url.includes('easyship.com')) {
          this.page = p;
          break;
        }
      }

      if (!this.page) {
        logger.warn('Easyship page tab not found. Attempting to open new tab...');
        this.page = await this.context.newPage();
        await this.page.goto('https://auth.easyship.com/login', { waitUntil: 'load', timeout: CONFIG.NAVIGATION_TIMEOUT });
      } else {
        logger.info('Easyship tab detected successfully');
        await this.page.bringToFront();
      }

      // Perform navigation/login sequence
      let currentUrl = this.page.url();
      if (currentUrl.includes('/login')) {
        logger.info('Detected login page. Waiting 2 seconds for password manager autofill...');
        await this.page.waitForTimeout(2000);
        
        const loginBtn = this.page.locator(SELECTORS.loginSubmitButton).first();
        if (await loginBtn.count() > 0 && await loginBtn.isVisible()) {
          logger.info('Clicking login button...');
          await loginBtn.click({ force: true });
        }
        
        logger.info('Waiting for dashboard load...');
        await this.page.waitForURL(url => !url.href.includes('/login'), { timeout: 30000 });
        currentUrl = this.page.url();
      }
      
      logger.info('Forcing navigation to Shipments section page 1...');
      await this.page.goto(CONFIG.TARGET_URL, { waitUntil: 'load', timeout: CONFIG.NAVIGATION_TIMEOUT });
      await this.page.waitForURL(url => url.href.includes('/shipments'), { timeout: 30000 });
      
      // Make sure we are on the Label Purchased tab
      logger.info('Making sure "Label Purchased" tab is active...');
      const labelPurchasedTab = this.page.locator('text="Label Purchased", text="Label purchased"').first();
      if (await labelPurchasedTab.count() > 0 && await labelPurchasedTab.isVisible()) {
        await labelPurchasedTab.click({ force: true });
      }

      // Verify page state
      await this.page.waitForSelector(SELECTORS.shipmentsTable, { timeout: CONFIG.NAVIGATION_TIMEOUT });
      logger.info('Verified shipment table is present');

    } catch (error) {
      logger.warn('CDP connection failed. Falling back to launchPersistentContext with Chrome profile...', {
        error: (error as Error).message
      });
      
      this.usingCDP = false;
      const profilePath = 'C:\\\\Users\\\\Artee Admin\\\\AppData\\\\Local\\\\Google\\\\Chrome\\\\User Data';
      
      this.context = await chromium.launchPersistentContext(profilePath, {
        headless: false,
        viewport: { width: 1280, height: 720 },
        args: ['--profile-directory=Profile 1']
      });
      
      const pages = this.context.pages();
      this.page = pages.length > 0 ? pages[0] : await this.context.newPage();
      await this.page.goto(CONFIG.TARGET_URL, { waitUntil: 'load', timeout: CONFIG.NAVIGATION_TIMEOUT });
      
      logger.info('Waiting for page load and verification...');
      await this.page.waitForSelector(SELECTORS.shipmentsTable, { timeout: 60000 });
      logger.info('Verified shipment table is present via persistent context');
    }
  }

  private async handlePagination(targetPage: number): Promise<boolean> {
    if (!this.page) return false;
    let currentPage = 1;

    while (currentPage < targetPage) {
      const nextBtn = this.page.locator(SELECTORS.nextPageButton).first();
      if (await nextBtn.count() > 0 && await nextBtn.isEnabled()) {
        logger.info('Navigating to next page', { currentPage, targetPage });
        await nextBtn.click();
        await this.page.waitForTimeout(2000);
        await this.page.waitForSelector(SELECTORS.shipmentsTable, { timeout: CONFIG.NAVIGATION_TIMEOUT });
        currentPage++;
      } else {
        logger.warn('Failed to reach target page', { currentPage, targetPage });
        return false;
      }
    }
    return true;
  }

  private async extractDetailField(labelRegex: RegExp): Promise<string> {
    if (!this.page) return '';
    try {
      const container = this.page.locator(SELECTORS.detailsContainer).first();
      if (await container.count() === 0 || !(await container.isVisible())) {
        return '';
      }
      
      const labelLocator = container.getByText(labelRegex).first();
      if (await labelLocator.count() > 0) {
        // 1. Check if the value is contained inside the label element itself (e.g. "Label: Value")
        const selfText = (await labelLocator.innerText()).trim();
        const selfCleaned = selfText.replace(labelRegex, '').replace(/[:\-\s]+/, '').trim();
        if (selfCleaned && selfCleaned.length > 0 && !selfCleaned.includes('\n')) {
          return selfCleaned;
        }
        
        // 2. Check the immediate sibling element in the DOM
        const sibling = labelLocator.locator('xpath=./following-sibling::*[1]');
        if (await sibling.count() > 0) {
          const siblingText = (await sibling.innerText()).trim();
          if (siblingText && !siblingText.includes('\n')) {
            return siblingText;
          }
        }
        
        // 3. Fallback: Parse parent text lines
        const parent = labelLocator.locator('xpath=..');
        const parentText = await parent.innerText();
        const lines = parentText.split('\n').map(l => l.trim()).filter(l => l.length > 0);
        const valLine = lines.find(line => !labelRegex.test(line));
        if (valLine) {
          return valLine.replace(/[:\-\s]+/, '').trim();
        }
      }
    } catch (e) {
      // Ignore extraction sub-failures
    }
    return '';
  }

  public async run(): Promise<void> {
    try {
      const today = new Date();
      const pad = (n: number) => n.toString().padStart(2, '0');
      const todayStr = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
      logger.info(`Checking today's date: ${todayStr}`);
      
      try {
        const workDir = path.dirname(CONFIG.PROGRESS_PATH);
        const memoryPath = path.join(workDir, 'company_memory.json');
        fs.writeFileSync(memoryPath, JSON.stringify({
          today: todayStr,
          lastScraped: new Date().toISOString()
        }, null, 2), 'utf-8');
        logger.info(`Saved today's date to company memory: ${memoryPath}`);
      } catch (err) {
        logger.warn('Failed to write to company memory file', { error: (err as Error).message });
      }

      await this.attachToBrowser();

      logger.info('Resuming progress from last saved state', {
        currentPage: this.progress.currentPage,
        currentShipmentIndex: this.progress.currentShipmentIndex
      });

      if (this.progress.currentPage > 1) {
        const restored = await this.handlePagination(this.progress.currentPage);
        if (!restored) {
          logger.error('Failed to restore pagination state. Resetting state to Page 1');
          this.progress.currentPage = 1;
          this.progress.currentShipmentIndex = 0;
        }
      }

      let hasMorePages = true;
      let processedThisRun = 0;
      let limitReached = false;
      while (hasMorePages && !limitReached) {
        const rows = this.page!.locator(SELECTORS.shipmentRow);
        const rowCount = await rows.count();
        logger.info('Scanning shipment rows on page', {
          page: this.progress.currentPage,
          count: rowCount
        });

        let allOlderThanFrom = true;
        let checkedCount = 0;

        for (let i = this.progress.currentShipmentIndex; i < rowCount; i++) {
          if (process.env.LIMIT && processedThisRun >= parseInt(process.env.LIMIT, 10)) {
            logger.info(`Limit of ${process.env.LIMIT} shipments reached for this run. Stopping.`);
            limitReached = true;
            break;
          }

          logger.info('Processing shipment row', {
            index: i + 1,
            total: rowCount,
            page: this.progress.currentPage
          });

          const row = rows.nth(i);
          
          let rowRefNo1 = 'N/A';
          let rowDate = 'N/A';
          let rowTrackingNo = 'N/A';
          let rowCourier = 'N/A';
          let rowUpsCharge = 'N/A';
          let rowDimension = 'N/A';

          try {
            await row.scrollIntoViewIfNeeded({ timeout: 5000 }).catch(() => {});
            const cells = row.locator('td, [role="cell"]');
            
            // Wait up to 5 seconds for cells to load and have text
            await this.page!.waitForFunction((rowEl) => {
              if (!rowEl) return false;
              const cellsList = rowEl.querySelectorAll('td, [role="cell"]');
              if (cellsList.length < 8) return false;
              const cell1Text = cellsList[1].textContent || '';
              return cell1Text.trim().length > 0;
            }, await row.elementHandle(), { timeout: 5000 }).catch(() => {});

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
          } catch (rowExtractErr) {
            logger.warn('Failed to extract direct row fields', { error: (rowExtractErr as Error).message });
          }

          // Date range checks
          const rowDateObj = parseRobustDate(rowDate);

          let isOlder = false;
          if (CONFIG.DATE_FROM && rowDateObj) {
            checkedCount++;
            const fromDateObj = parseRobustDate(CONFIG.DATE_FROM);
            if (fromDateObj && rowDateObj < fromDateObj) {
              logger.info(`Row date (${rowDate}) is older than Date From (${CONFIG.DATE_FROM}). Skipping row.`);
              isOlder = true;
            } else {
              allOlderThanFrom = false;
            }
          } else if (!CONFIG.DATE_FROM) {
            allOlderThanFrom = false;
          } else {
            // If rowDateObj is null/N/A, we don't know yet (if in FAST_SCRAPE, assume not older to be safe; if not, we check in modal)
            if (process.env.FAST_SCRAPE === 'true') {
              allOlderThanFrom = false;
            }
          }

          if (isOlder) {
            this.progress.currentShipmentIndex = i + 1;
            saveProgress(this.progress);
            processedThisRun++;
            continue;
          }

          if (CONFIG.DATE_TO && rowDateObj) {
            const toDateObj = parseRobustDate(CONFIG.DATE_TO);
            if (toDateObj && rowDateObj > toDateObj) {
              logger.info(`Row date (${rowDate}) is newer than Date To (${CONFIG.DATE_TO}). Skipping row.`);
              this.progress.currentShipmentIndex = i + 1;
              saveProgress(this.progress);
              processedThisRun++;
              continue;
            }
          }

          if (process.env.FAST_SCRAPE === 'true') {
            const shipmentData: ShipmentData = {
              vendorsStores: 'N/A',
              date: rowDate,
              trackingNo: rowTrackingNo,
              trackingNo2: 'N/A',
              refNo1: rowRefNo1,
              refNo2: 'N/A',
              receiver: 'N/A',
              upsCharge: rowUpsCharge,
              deliveryDate: 'N/A',
              shippingFrom: rowCourier,
              weight: 'N/A',
              dimension: rowDimension,
              freightChargeFromCmr: 'N/A',
              receiverCity: 'N/A',
              senderCity: 'N/A',
            };

            logger.info(`Scraping row (Fast Mode) - Date: ${shipmentData.date} | Tracking No: ${shipmentData.trackingNo} | Courier: ${shipmentData.shippingFrom} | Ref No 1: ${shipmentData.refNo1}`);

            const isDuplicate = await this.excelManager.trackingNumberExists(shipmentData.trackingNo);
            if (isDuplicate) {
              logger.info('Skipping duplicate tracking number', { trackingNo: shipmentData.trackingNo });
            } else {
              await this.excelManager.appendShipment(shipmentData);
            }

            this.progress.currentShipmentIndex = i + 1;
            this.progress.lastTrackingNumber = shipmentData.trackingNo;
            this.progress.totalProcessed += 1;
            this.progress.timestamp = new Date().toISOString();
            saveProgress(this.progress);
            processedThisRun++;
            continue;
          }

          let modalVendor = 'N/A';
          let modalReceiver = 'N/A';
          let modalWeight = 'N/A';
          let modalTrackingNo2 = 'N/A';
          let modalRefNo2 = 'N/A';
          let modalDeliveryDate = 'N/A';
          let modalFreightCharge = 'N/A';
          let receiverCity = 'N/A';
          let senderCity = 'N/A';
          let modalDate = 'N/A';

          try {
            await this.retryAction(async () => {
              const clickable = row.locator(SELECTORS.shipmentLinkInRow).first();
              if (await clickable.count() > 0) {
                await clickable.scrollIntoViewIfNeeded({ timeout: 5000 }).catch(() => {});
                await clickable.click({ force: true });
              } else {
                await row.click({ force: true });
              }
              await this.page!.waitForSelector(SELECTORS.detailsContainer, { timeout: CONFIG.ACTION_TIMEOUT });
              
              const contentLocator = this.page!.locator(SELECTORS.detailsContainer).getByText('Ship From Information').first();
              await contentLocator.waitFor({ state: 'visible', timeout: 5000 });
              
              const modal = this.page!.locator(SELECTORS.detailsContainer).first();
              await modal.evaluate((el) => {
                el.scrollTop = el.scrollHeight;
              });
              await this.page!.waitForTimeout(1000);

              const modalText = await modal.innerText();
              const lines = modalText.split('\n').map(l => l.trim());

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

              const dateLine = lines.find(l => l.toLowerCase().includes('created at') || l.toLowerCase().includes('date:'));
              if (dateLine) {
                const parts = dateLine.split(/[\t:]+/);
                if (parts.length > 1) {
                  modalDate = parts[1].trim();
                } else {
                  const idx = lines.indexOf(dateLine);
                  if (idx !== -1 && lines[idx + 1]) {
                    modalDate = lines[idx + 1].trim();
                  }
                }
              }

            }, `Open and parse shipment details at index ${i}`);

            if ((!rowDate || rowDate === 'N/A') && modalDate !== 'N/A') {
              rowDate = modalDate;
            }

            const modalDateObj = parseRobustDate(rowDate);

            let isOlderModal = false;
            if (CONFIG.DATE_FROM && modalDateObj) {
              checkedCount++;
              const fromDateObj = parseRobustDate(CONFIG.DATE_FROM);
              if (fromDateObj && modalDateObj < fromDateObj) {
                logger.info(`Confirmed modal date (${rowDate}) is older than Date From (${CONFIG.DATE_FROM}). Skipping.`);
                isOlderModal = true;
              } else {
                allOlderThanFrom = false;
              }
            } else if (CONFIG.DATE_FROM && !modalDateObj) {
              allOlderThanFrom = false;
            }

            let isNewerModal = false;
            if (CONFIG.DATE_TO && modalDateObj) {
              const toDateObj = parseRobustDate(CONFIG.DATE_TO);
              if (toDateObj && modalDateObj > toDateObj) {
                logger.info(`Confirmed modal date (${rowDate}) is newer than Date To (${CONFIG.DATE_TO}). Skipping.`);
                isNewerModal = true;
              }
            }

            if (isOlderModal || isNewerModal) {
              this.progress.currentShipmentIndex = i + 1;
              saveProgress(this.progress);
              processedThisRun++;
              continue;
            }

            const shipmentData: ShipmentData = {
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

            logger.info(`Scraping row - Date: ${shipmentData.date} | Tracking: ${shipmentData.trackingNo} | Vendor: ${shipmentData.vendorsStores} | Receiver: ${shipmentData.receiver} | Charge: ${shipmentData.upsCharge}`);

            const isDuplicate = await this.excelManager.trackingNumberExists(shipmentData.trackingNo);
            if (isDuplicate) {
              logger.info('Skipping duplicate tracking number', { trackingNo: shipmentData.trackingNo });
            } else {
              await this.excelManager.appendShipment(shipmentData);
            }

            this.progress.currentShipmentIndex = i + 1;
            this.progress.lastTrackingNumber = shipmentData.trackingNo;
            this.progress.totalProcessed += 1;
            this.progress.timestamp = new Date().toISOString();
            saveProgress(this.progress);
            processedThisRun++;

          } catch (extractionError) {
            this.progress.totalFailed += 1;
            saveProgress(this.progress);
            logger.error('Failed to process shipment at index', {
              index: i,
              error: (extractionError as Error).message
            });
          } finally {
            const closeBtn = this.page!.locator(SELECTORS.closeDetailsButton);
            if (await closeBtn.count() > 0 && await closeBtn.first().isVisible()) {
              await closeBtn.first().evaluate((el) => (el as HTMLElement).click());
              await this.page!.waitForSelector(SELECTORS.detailsContainer, { state: 'detached', timeout: CONFIG.ACTION_TIMEOUT });
            } else {
              await this.page!.goto(CONFIG.TARGET_URL, { waitUntil: 'networkidle', timeout: CONFIG.NAVIGATION_TIMEOUT });
              if (this.progress.currentPage > 1) {
                await this.handlePagination(this.progress.currentPage);
              }
            }
          }
        }

        if (limitReached) {
          break;
        }

        if (CONFIG.DATE_FROM && rowCount > 0 && allOlderThanFrom && checkedCount > 0) {
          logger.info(`All shipments on page ${this.progress.currentPage} are older than Date From (${CONFIG.DATE_FROM}). Terminating pagination.`);
          hasMorePages = false;
          break;
        }

        // Check for next page
        const nextBtn = this.page!.locator(SELECTORS.nextPageButton).first();
        if (await nextBtn.count() > 0 && await nextBtn.isEnabled()) {
          logger.info('Pagination next button detected. Moving to next page');
          await nextBtn.click();
          this.progress.currentPage++;
          this.progress.currentShipmentIndex = 0;
          saveProgress(this.progress);
          await this.page!.waitForTimeout(2000);
          await this.page!.waitForSelector(SELECTORS.shipmentsTable, { timeout: CONFIG.NAVIGATION_TIMEOUT });
        } else {
          logger.info('No more pages remaining. Run complete.');
          hasMorePages = false;
        }
      }

      clearProgress();
    } catch (error) {
      logger.error('Critical scraper flow error', { error: (error as Error).message });
      if (this.page) {
        await this.takeScreenshot('fatal_scraper_error');
      }
    } finally {
      if (this.usingCDP && this.browser) {
        logger.info('Closing CDP connection...');
        await this.browser.close();
      } else if (this.context) {
        logger.info('Closing persistent context...');
        await this.context.close();
      }
    }
  }
}
