import { chromium } from 'playwright';
import { CONFIG } from '../src/config';

async function findPagination() {
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

  // Find elements matching text "of" or containing page numbers or svgs near the bottom
  console.log('Finding elements containing numbers or dropdowns near the bottom...');
  const elements = page.locator('div, span, button');
  const count = await elements.count();
  for (let i = 0; i < count; i++) {
    const el = elements.nth(i);
    const html = await el.evaluate(el => el.outerHTML).catch(() => '');
    if (html.includes('pagination') || html.includes('Page 1') || (html.includes('1-50') && html.includes('of'))) {
      console.log(`\nFound element index ${i}:`);
      console.log(html.substring(0, 500));
      // Stop after printing a few to avoid spam
    }
  }

  await browser.close();
}

findPagination().catch(console.error);
