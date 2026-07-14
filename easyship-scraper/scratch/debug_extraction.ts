import { chromium } from 'playwright';
import { SELECTORS } from '../src/selectors';
import { CONFIG } from '../src/config';

async function testExtraction() {
  console.log('Connecting to Chrome via CDP...');
  const browser = await chromium.connectOverCDP(`http://127.0.0.1:${CONFIG.CDP_PORT}`);
  const context = browser.contexts()[0];
  let page;
  for (const p of context.pages()) {
    if (p.url().includes('easyship.com/shipments')) {
      page = p;
      break;
    }
  }
  if (!page) {
    console.error('No shipments page found');
    await browser.close();
    return;
  }

  const container = page.locator(SELECTORS.detailsContainer).first();
  
  const extractFieldDebug = async (name: string, regex: RegExp): Promise<string> => {
    console.log(`\n--- Extracting ${name} with regex: ${regex} ---`);
    const labelLocator = container.getByText(regex).first();
    const count = await labelLocator.count();
    console.log(`labelLocator count: ${count}`);
    if (count > 0) {
      const isVisible = await labelLocator.isVisible();
      console.log(`labelLocator isVisible: ${isVisible}`);
      const text = await labelLocator.innerText();
      console.log(`labelLocator innerText: ${JSON.stringify(text)}`);

      const parent = labelLocator.locator('xpath=..');
      const parentText = await parent.innerText();
      console.log(`parent innerText:\n${parentText}`);

      const lines = parentText.split('\n').map(l => l.trim()).filter(l => l.length > 0);
      console.log(`Split lines:`, lines);

      const foundLine = lines.find(line => !regex.test(line));
      console.log(`foundLine: ${JSON.stringify(foundLine)}`);
      
      if (lines.length > 1) {
        const result = foundLine?.trim() || '';
        console.log(`Result (lines.length > 1): ${JSON.stringify(result)}`);
        return result;
      }
      
      const result = parentText.replace(regex, '').replace(/[:\-\s]+/, '').trim();
      console.log(`Result (single line): ${JSON.stringify(result)}`);
      return result;
    }
    return '';
  };

  await extractFieldDebug('trackingNo', /Tracking Number|Tracking No/i);
  await extractFieldDebug('vendorsStores', /Store|Vendor|Shop/i);

  await browser.close();
}

testExtraction().catch(console.error);
