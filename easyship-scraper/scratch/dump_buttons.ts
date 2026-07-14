import { chromium } from 'playwright';
import { CONFIG } from '../src/config';

async function dumpButtons() {
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

  console.log('Dumping all buttons on page...');
  const buttons = page.locator('button');
  const count = await buttons.count();
  for (let i = 0; i < count; i++) {
    const btn = buttons.nth(i);
    const text = (await btn.innerText().catch(() => '')).trim();
    const className = await btn.getAttribute('class').catch(() => '');
    const disabled = await btn.getAttribute('disabled').catch(() => null);
    const outerHTML = await btn.evaluate(el => el.outerHTML).catch(() => '');
    
    // Print all buttons that have an SVG inside or are enabled
    if (outerHTML.includes('<svg') || text.length > 0) {
      console.log(`Index ${i} | Text: "${text}" | Class: "${className}" | Disabled: ${disabled}`);
      if (outerHTML.includes('arrow') || outerHTML.includes('chevron') || outerHTML.includes('page') || outerHTML.includes('next') || outerHTML.includes('prev') || outerHTML.length < 500) {
        console.log(`  HTML: ${outerHTML}`);
      }
    }
  }

  await browser.close();
}

dumpButtons().catch(console.error);
