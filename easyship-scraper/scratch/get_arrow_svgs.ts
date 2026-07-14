import { chromium } from 'playwright';
import { CONFIG } from '../src/config';

async function getArrowSvgs() {
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
    console.error('No page');
    await browser.close();
    return;
  }

  const buttons = page.locator('button');
  console.log('Button 140 HTML:', await buttons.nth(140).evaluate(el => el.outerHTML));
  console.log('Button 142 HTML:', await buttons.nth(142).evaluate(el => el.outerHTML));
  console.log('Button 143 HTML:', await buttons.nth(143).evaluate(el => el.outerHTML));

  await browser.close();
}

getArrowSvgs().catch(console.error);
