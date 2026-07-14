import { chromium } from 'playwright';
import { CONFIG } from '../src/config';

async function inspectPagination() {
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

  // Find all buttons or elements that could be pagination buttons
  console.log('Searching for buttons with SVG or ARIA-label or text...');
  const buttons = page.locator('button');
  const count = await buttons.count();
  console.log(`Found ${count} buttons on the page.`);
  for (let i = 0; i < count; i++) {
    const btn = buttons.nth(i);
    const text = await btn.innerText().catch(() => '');
    const ariaLabel = await btn.getAttribute('aria-label').catch(() => '');
    const html = await btn.evaluate(el => el.outerHTML).catch(() => '');
    
    // We care about next/prev/numbers
    if (ariaLabel?.toLowerCase().includes('page') || text?.toLowerCase().includes('next') || text?.toLowerCase().includes('prev') || html.includes('pagination') || html.includes('M6.653')) {
      console.log(`\nButton ${i}:`);
      console.log(`Text: ${JSON.stringify(text)}`);
      console.log(`Aria-label: ${JSON.stringify(ariaLabel)}`);
      console.log(`HTML: ${html.substring(0, 300)}`);
    }
  }

  await browser.close();
}

inspectPagination().catch(console.error);
