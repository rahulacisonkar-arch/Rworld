import { chromium } from 'playwright';
import { SELECTORS } from '../src/selectors';
import { CONFIG } from '../src/config';

async function inspect() {
  console.log('Connecting to Chrome via CDP...');
  const browser = await chromium.connectOverCDP(`http://127.0.0.1:${CONFIG.CDP_PORT}`);
  const contexts = browser.contexts();
  if (contexts.length === 0) {
    console.error('No contexts found');
    await browser.close();
    return;
  }
  const context = contexts[0];
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
  if (await container.count() === 0) {
    console.error('Details container not found on active page.');
    await browser.close();
    return;
  }

  console.log('--- MODAL INNER TEXT ---');
  const innerText = await container.innerText();
  console.log(innerText);
  console.log('------------------------');

  // Let's also print all elements inside it to see structure
  const lines = innerText.split('\n').map(l => l.trim()).filter(l => l.length > 0);
  console.log('Parsed Lines:', lines);

  await browser.close();
}

inspect().catch(console.error);
