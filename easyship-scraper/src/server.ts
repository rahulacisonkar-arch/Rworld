import * as http from 'http';
import * as fs from 'fs';
import * as path from 'path';
import { spawn, ChildProcess } from 'child_process';
import { CONFIG } from './config';

const PORT = 3000;
let scraperProcess: ChildProcess | null = null;
const clients: http.ServerResponse[] = [];

// Determine user data directory
function getUserDataDir(): string {
  const home = process.env.APPDATA || (process.platform === 'darwin' ? process.env.HOME + '/Library/Application Support' : process.env.HOME + '/.config');
  return path.join(home, 'easyship-shipment-extraction-bot');
}

function getScraperWorkDir(): string {
  const dir = path.join(getUserDataDir(), 'scraper-data');
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
  return dir;
}

// Locate Chrome Executable on Windows
function getChromePath(): string {
  const commonPaths = [
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
  ];
  if (process.env.LOCALAPPDATA) {
    commonPaths.push(path.join(process.env.LOCALAPPDATA, 'Google\\Chrome\\Application\\chrome.exe'));
  }
  for (const p of commonPaths) {
    if (fs.existsSync(p)) return p;
  }
  return 'chrome.exe';
}

function sendSSE(channel: string, data: any) {
  const payload = JSON.stringify({ channel, data });
  for (const client of clients) {
    client.write(`data: ${payload}\n\n`);
  }
}

// Helper to check if Chrome remote debugging is active
function isChromeDebugging(): Promise<boolean> {
  return new Promise((resolve) => {
    const req = http.get(`http://127.0.0.1:9222/json/version`, (res) => {
      resolve(res.statusCode === 200);
    });
    req.on('error', () => resolve(false));
    req.setTimeout(1500, () => { req.destroy(); resolve(false); });
  });
}

const server = http.createServer(async (req, res) => {
  const url = req.url || '/';

  // Serve main GUI page
  if (url === '/' || url === '/index.html') {
    const htmlPath = path.join(__dirname, 'renderer.html');
    if (fs.existsSync(htmlPath)) {
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      res.end(fs.readFileSync(htmlPath));
    } else {
      res.writeHead(404);
      res.end('renderer.html not found. Run build first.');
    }
    return;
  }

  // Serve logo asset
  if (url === '/logo.png') {
    const logoPath = path.join(__dirname, 'logo.png');
    if (fs.existsSync(logoPath)) {
      res.writeHead(200, { 'Content-Type': 'image/png' });
      res.end(fs.readFileSync(logoPath));
    } else {
      res.writeHead(404);
      res.end();
    }
    return;
  }

  // Server-Sent Events (SSE) log and status stream
  if (url === '/api/logs/stream') {
    res.writeHead(200, {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache',
      'Connection': 'keep-alive',
    });
    clients.push(res);
    req.on('close', () => {
      const idx = clients.indexOf(res);
      if (idx !== -1) clients.splice(idx, 1);
    });
    return;
  }

  // API Endpoints
  if (req.method === 'POST') {
    let body = '';
    req.on('data', chunk => { body += chunk.toString(); });
    req.on('end', async () => {
      let params: any = {};
      try { if (body) params = JSON.parse(body); } catch {}

      if (url === '/api/launch-chrome') {
        const chromePath = getChromePath();
        const profileDir = 'C:\\\\Users\\\\Artee Admin\\\\AppData\\\\Local\\\\Google\\\\Chrome\\\\User Data';

        const alreadyDebugging = await isChromeDebugging();
        if (alreadyDebugging) {
          sendSSE('chrome-status', {
            status: 'success',
            message: 'Chrome debug session already active on port 9222. Ready to scrape!'
          });
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ success: true }));
          return;
        }

        const args = [
          `--remote-debugging-port=9222`,
          `--user-data-dir=${profileDir}`,
          '--profile-directory=Profile 1',
          '--no-first-run',
          '--no-default-browser-check',
          '--disable-features=TranslateUI',
          'https://app.easyship.com/shipments?tab_id=purchased',
        ];

        try {
          const proc = spawn(chromePath, args, {
            detached: true,
            stdio: 'ignore',
            windowsHide: false,
          });
          proc.unref();

          setTimeout(async () => {
            const running = await isChromeDebugging();
            if (running) {
              sendSSE('chrome-status', { status: 'success', message: 'Google Chrome launched successfully. Log in, then click Scrape Data.' });
            } else {
              sendSSE('chrome-status', { status: 'success', message: 'Chrome opened. If already running, please go to Easyship manually. Then click Scrape Data.' });
            }
          }, 2500);

          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ success: true }));
        } catch (error: any) {
          sendSSE('chrome-status', { status: 'error', message: `Failed to launch Chrome: ${error.message}` });
          res.writeHead(500);
          res.end(JSON.stringify({ error: error.message }));
        }
        return;
      }

      if (url === '/api/start-scrape') {
        if (scraperProcess && !scraperProcess.killed) {
          res.writeHead(400);
          res.end(JSON.stringify({ error: 'Scraper already running' }));
          return;
        }

        sendSSE('scrape-status', { status: 'running' });

        const workDir = getScraperWorkDir();
        const scriptPath = path.join(__dirname, 'index.js');

        if (!fs.existsSync(scriptPath)) {
          sendSSE('scrape-log', `ERROR: Scraper script not found at: ${scriptPath}\n`);
          sendSSE('scrape-status', { status: 'stopped', code: 1 });
          res.writeHead(500);
          res.end(JSON.stringify({ error: 'Script not found' }));
          return;
        }

        const nodeArgs = [scriptPath];
        const scraperEnv = {
          ...process.env,
          DATE_FROM: params.dateFrom || '',
          DATE_TO: params.dateTo || '',
          FAST_SCRAPE: params.fastScrape ? 'true' : 'false',
          EXCEL_PATH: path.join(workDir, 'shipments.xlsx'),
          PROGRESS_PATH: path.join(workDir, 'progress.json'),
          LOG_FILE_PATH: path.join(workDir, 'logs', 'easyship.log'),
          SCREENSHOTS_DIR: path.join(workDir, 'screenshots'),
        };

        scraperProcess = spawn(process.execPath, nodeArgs, {
          cwd: workDir,
          env: scraperEnv,
          stdio: ['ignore', 'pipe', 'pipe'],
        });

        scraperProcess.stdout?.on('data', (data) => {
          sendSSE('scrape-log', data.toString());
        });

        scraperProcess.stderr?.on('data', (data) => {
          sendSSE('scrape-log', `STDERR: ${data.toString()}`);
        });

        scraperProcess.on('error', (err) => {
          sendSSE('scrape-log', `ERROR: Failed to start scraper: ${err.message}\n`);
          sendSSE('scrape-status', { status: 'stopped', code: 1 });
        });

        scraperProcess.on('exit', (code) => {
          scraperProcess = null;
          sendSSE('scrape-status', { status: 'stopped', code: code || 0 });
        });

        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true }));
        return;
      }

      if (url === '/api/pause-scrape') {
        if (scraperProcess && scraperProcess.pid) {
          const cmd = `$member = '[DllImport("ntdll.dll")] public static extern int NtSuspendProcess(IntPtr handle); [DllImport("ntdll.dll")] public static extern int NtResumeProcess(IntPtr handle);'; $type = Add-Type -MemberDefinition $member -Name "Win32ProcessControl" -Namespace "Win32" -PassThru; $proc = Get-Process -Id ${scraperProcess.pid}; $type::NtSuspendProcess($proc.Handle)`;
          spawn('powershell', ['-Command', cmd]);
          sendSSE('scrape-log', '\n--- Scraper process PAUSED ---\n');
          sendSSE('scrape-status', { status: 'paused' });
        }
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true }));
        return;
      }

      if (url === '/api/resume-scrape') {
        if (scraperProcess && scraperProcess.pid) {
          const cmd = `$member = '[DllImport("ntdll.dll")] public static extern int NtSuspendProcess(IntPtr handle); [DllImport("ntdll.dll")] public static extern int NtResumeProcess(IntPtr handle);'; $type = Add-Type -MemberDefinition $member -Name "Win32ProcessControl" -Namespace "Win32" -PassThru; $proc = Get-Process -Id ${scraperProcess.pid}; $type::NtResumeProcess($proc.Handle)`;
          spawn('powershell', ['-Command', cmd]);
          sendSSE('scrape-log', '\n--- Scraper process RESUMED ---\n');
          sendSSE('scrape-status', { status: 'running' });
        }
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true }));
        return;
      }

      if (url === '/api/stop-scrape') {
        if (scraperProcess && !scraperProcess.killed) {
          scraperProcess.kill('SIGTERM');
          scraperProcess = null;
          sendSSE('scrape-log', '\n--- Scrape process stopped by user ---\n');
        }
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true }));
        return;
      }

      if (url === '/api/open-excel') {
        const excelPath = path.join(getScraperWorkDir(), 'shipments.xlsx');
        if (fs.existsSync(excelPath)) {
          // Open cross-platform
          const opener = process.platform === 'win32' ? 'start' : process.platform === 'darwin' ? 'open' : 'xdg-open';
          spawn(opener, [excelPath], { shell: true });
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify({ success: true }));
        } else {
          sendSSE('scrape-log', 'INFO: No Excel file found yet. Run a scrape first.\n');
          res.writeHead(404);
          res.end(JSON.stringify({ error: 'Excel file not found' }));
        }
        return;
      }

      if (url === '/api/get-data-dir') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ dir: getScraperWorkDir() }));
        return;
      }

      res.writeHead(404);
      res.end();
    });
    return;
  }

  res.writeHead(404);
  res.end();
});

server.listen(PORT, () => {
  console.log(`\n========================================================`);
  console.log(`RWORLD SCRAPER Web Server started on http://localhost:${PORT}`);
  console.log(`========================================================\n`);
});
