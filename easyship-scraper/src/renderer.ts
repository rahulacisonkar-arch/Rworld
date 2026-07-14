import { ipcRenderer } from 'electron';

// DOM Elements
const btnOpenChrome = document.getElementById('btn-open-chrome') as HTMLButtonElement;
const btnScrape = document.getElementById('btn-scrape') as HTMLButtonElement;
const btnPause = document.getElementById('btn-pause') as HTMLButtonElement;
const btnStop = document.getElementById('btn-stop') as HTMLButtonElement;
const btnExcel = document.getElementById('btn-excel') as HTMLButtonElement;
const btnClearLog = document.getElementById('btn-clear-log') as HTMLButtonElement;
const dateFromInput = document.getElementById('date-from') as HTMLInputElement;
const dateToInput = document.getElementById('date-to') as HTMLInputElement;
const toggleFastScrape = document.getElementById('toggle-fast-scrape') as HTMLInputElement;

const statStatus = document.getElementById('stat-status') as HTMLDivElement;
const statProcessed = document.getElementById('stat-processed') as HTMLDivElement;
const statFailed = document.getElementById('stat-failed') as HTMLDivElement;
const statPage = document.getElementById('stat-page') as HTMLDivElement;

const consoleBody = document.getElementById('console-body') as HTMLDivElement;
const indicatorDot = document.getElementById('indicator-dot') as HTMLDivElement;
const indicatorText = document.getElementById('indicator-text') as HTMLSpanElement;
const dataDirPath = document.getElementById('data-dir-path') as HTMLDivElement;

let totalProcessed = 0;
let totalFailed = 0;
let currentPage = 1;
const MAX_LOG_LINES = 500;

// Request data directory from main process
ipcRenderer.send('get-data-dir');
ipcRenderer.on('data-dir', (_event: any, dir: string) => {
  if (dataDirPath) dataDirPath.innerText = dir;
});

// Helper to write to log console
function appendLog(text: string, type: 'info' | 'success' | 'warning' | 'error' | 'dim' = 'info') {
  const line = document.createElement('div');
  line.className = `log-line log-${type}`;
  
  // Format timestamp prefix
  const now = new Date();
  const ts = `[${now.getHours().toString().padStart(2,'0')}:${now.getMinutes().toString().padStart(2,'0')}:${now.getSeconds().toString().padStart(2,'0')}]`;
  line.innerText = `${ts} ${text.trim()}`;
  
  consoleBody.appendChild(line);

  // Prune old lines to avoid memory blowup
  while (consoleBody.children.length > MAX_LOG_LINES) {
    consoleBody.removeChild(consoleBody.firstChild!);
  }

  consoleBody.scrollTop = consoleBody.scrollHeight;
}

// Parse structured logging strings to update dashboard statistics
function parseStatsFromLog(text: string) {
  try {
    if (text.includes('Progress saved')) {
      const matchProcessed = text.match(/"totalProcessed":\s*(\d+)/);
      if (matchProcessed) {
        totalProcessed = parseInt(matchProcessed[1], 10);
        statProcessed.innerText = totalProcessed.toString();
      }
      const matchFailed = text.match(/"totalFailed":\s*(\d+)/);
      if (matchFailed) {
        totalFailed = parseInt(matchFailed[1], 10);
        statFailed.innerText = totalFailed.toString();
      }
      const matchPage = text.match(/"currentPage":\s*(\d+)/);
      if (matchPage) {
        currentPage = parseInt(matchPage[1], 10);
        statPage.innerText = currentPage.toString();
      }
    } else if (text.includes('Resuming progress')) {
      const matchPage = text.match(/"currentPage":\s*(\d+)/);
      if (matchPage) {
        currentPage = parseInt(matchPage[1], 10);
        statPage.innerText = currentPage.toString();
      }
    } else if (text.includes('Scanning shipment rows on page')) {
      const matchPage = text.match(/"page":\s*(\d+)/);
      if (matchPage) {
        currentPage = parseInt(matchPage[1], 10);
        statPage.innerText = currentPage.toString();
      }
    }
  } catch (err) {
    // Fail silently
  }
}

// IPC Receivers
ipcRenderer.on('chrome-status', (_event: any, { status, message }: { status: string; message: string }) => {
  if (status === 'success' || status === 'running') {
    appendLog(message, 'success');
    btnScrape.removeAttribute('disabled');
    statStatus.innerText = 'CHROME ACTIVE';
  } else {
    appendLog(message, 'error');
    statStatus.innerText = 'CHROME ERROR';
  }
});

ipcRenderer.on('scrape-log', (_event: any, text: string) => {
  let type: 'info' | 'success' | 'warning' | 'error' | 'dim' = 'info';
  const lower = text.toLowerCase();
  if (lower.includes('error') || lower.includes('fail') || lower.includes('critical')) type = 'error';
  else if (lower.includes('warn')) type = 'warning';
  else if (lower.includes('success') || lower.includes('complete') || lower.includes('excel updated')) type = 'success';
  else if (lower.includes('skipping duplicate') || lower.includes('stderr:')) type = 'dim';

  appendLog(text.trim(), type);
  parseStatsFromLog(text);
});

ipcRenderer.on('scrape-status', (_event: any, { status, code }: { status: string; code?: number }) => {
  if (status === 'running') {
    btnScrape.style.display = 'none';
    btnPause.style.display = 'flex';
    btnPause.innerText = 'Pause Scraper';
    btnStop.style.display = 'flex';
    indicatorDot.className = 'indicator-dot active';
    indicatorText.innerText = 'ACTIVE';
    statStatus.innerText = 'EXTRACTING';

    // Reset stats for new session
    if (totalProcessed === 0 && totalFailed === 0) {
      statProcessed.innerText = '0';
      statFailed.innerText = '0';
      statPage.innerText = '1';
    }
  } else if (status === 'paused') {
    btnScrape.style.display = 'none';
    btnPause.style.display = 'flex';
    btnPause.innerText = 'Resume Scraper';
    btnStop.style.display = 'flex';
    indicatorDot.className = 'indicator-dot';
    indicatorText.innerText = 'PAUSED';
    statStatus.innerText = 'PAUSED';
  } else {
    btnScrape.style.display = 'flex';
    btnPause.style.display = 'none';
    btnStop.style.display = 'none';
    indicatorDot.className = 'indicator-dot';
    indicatorText.innerText = 'IDLE';

    if (code === 0) {
      statStatus.innerText = 'COMPLETE';
      appendLog('✅ Scraping complete! Click "Open Excel File" to view results.', 'success');
    } else {
      statStatus.innerText = 'STOPPED';
      appendLog(`⚠ Scraper exited with code ${code}`, code === 0 ? 'success' : 'warning');
    }
  }
});

// Button click handlers
btnOpenChrome.addEventListener('click', () => {
  appendLog('Launching Chrome with Easyship debug session...', 'info');
  ipcRenderer.send('launch-chrome');
});

btnScrape.addEventListener('click', () => {
  const dateFrom = dateFromInput.value; // YYYY-MM-DD
  const dateTo = dateToInput.value;     // YYYY-MM-DD
  const fastScrape = toggleFastScrape.checked;

  appendLog(`Starting extraction job... [Date Range: ${dateFrom || 'ANY'} → ${dateTo || 'ANY'} | Fast Scrape: ${fastScrape}]`, 'info');
  ipcRenderer.send('start-scrape', { dateFrom, dateTo, fastScrape });
});

btnPause.addEventListener('click', () => {
  if (statStatus.innerText === 'PAUSED') {
    appendLog('▶ Sending resume signal to scraper...', 'info');
    ipcRenderer.send('resume-scrape');
  } else {
    appendLog('⏸ Sending pause signal to scraper...', 'warning');
    ipcRenderer.send('pause-scrape');
  }
});

btnStop.addEventListener('click', () => {
  appendLog('⏹ Sending stop signal to scraper...', 'warning');
  ipcRenderer.send('stop-scrape');
});

btnExcel.addEventListener('click', () => {
  appendLog('Opening Excel file...', 'info');
  ipcRenderer.send('open-excel');
});

btnClearLog.addEventListener('click', () => {
  consoleBody.innerHTML = '';
  appendLog('Log cleared.', 'dim');
});
