import { app, BrowserWindow, ipcMain, shell } from 'electron';
import * as path from 'path';
import * as fs from 'fs';
import * as http from 'http';
import { spawn, ChildProcess } from 'child_process';

// Disable hardware acceleration to prevent GPU crashes in VMs/sandboxes
app.disableHardwareAcceleration();

let mainWindow: BrowserWindow | null = null;
let scraperProcess: ChildProcess | null = null;
let chromeDebugPort = 9222;
let chromeAlreadyLaunched = false;

// Determine the user-writable data directory (works both in dev and packaged)
function getUserDataDir(): string {
  return app.getPath('userData');
}

// The scraper working directory where shipments.xlsx and progress.json live
function getScraperWorkDir(): string {
  const dir = path.join(getUserDataDir(), 'scraper-data');
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
  return dir;
}

// Resolve the path to the bundled index.js (scraper entrypoint)
function getScraperScriptPath(): string {
  // When packaged, __dirname points inside app.asar or app.asar.unpacked
  // We use extraResources to put the scraper outside asar
  if (app.isPackaged) {
    return path.join(process.resourcesPath, 'scraper', 'index.js');
  }
  return path.join(__dirname, 'index.js');
}

// Resolve node executable
function getNodeExecutable(): string {
  if (app.isPackaged) {
    // Bundled node inside resources
    const bundledNode = path.join(process.resourcesPath, 'node.exe');
    if (fs.existsSync(bundledNode)) return bundledNode;
  }
  return process.execPath; // Use Electron's node when in dev
}

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1100,
    height: 750,
    title: 'RWORLD SCRAPER™',
    frame: true,
    backgroundColor: '#0a0a16',
    webPreferences: {
      nodeIntegration: true,
      contextIsolation: false,
    },
  });

  mainWindow.loadFile(path.join(__dirname, 'renderer.html'));

  mainWindow.on('closed', () => {
    mainWindow = null;
    cleanup();
  });
}

function cleanup() {
  if (scraperProcess) {
    try { scraperProcess.kill('SIGTERM'); } catch (_) {}
    scraperProcess = null;
  }
}

app.on('ready', createWindow);

app.on('window-all-closed', () => {
  cleanup();
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('activate', () => {
  if (mainWindow === null) {
    createWindow();
  }
});

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
    if (fs.existsSync(p)) {
      return p;
    }
  }

  return 'chrome.exe'; // fallback to PATH
}

// Check if Chrome debug port is already responding
function isChromeDebugging(): Promise<boolean> {
  return new Promise((resolve) => {
    const req = http.get(`http://127.0.0.1:${chromeDebugPort}/json/version`, (res) => {
      resolve(res.statusCode === 200);
    });
    req.on('error', () => resolve(false));
    req.setTimeout(1500, () => { req.destroy(); resolve(false); });
  });
}

// IPC Handlers
ipcMain.on('launch-chrome', async (event) => {
  const chromePath = getChromePath();
  const profileDir = 'C:\\\\Users\\\\Artee Admin\\\\AppData\\\\Local\\\\Google\\\\Chrome\\\\User Data';

  // Check if Chrome debug session is already running
  const alreadyDebugging = await isChromeDebugging();
  if (alreadyDebugging) {
    event.reply('chrome-status', {
      status: 'success',
      message: 'Chrome debug session already active on port 9222. Ready to scrape!'
    });
    return;
  }

  const args = [
    `--remote-debugging-port=${chromeDebugPort}`,
    `--user-data-dir=${profileDir}`,
    '--profile-directory=Profile 1',
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-features=TranslateUI',
    'https://app.easyship.com/shipments?tab_id=purchased',
  ];

  let replied = false;
  const safeReply = (status: string, message: string) => {
    if (!replied) {
      replied = true;
      event.reply('chrome-status', { status, message });
    }
  };

  try {
    const proc = spawn(chromePath, args, {
      detached: true,
      stdio: 'ignore',
      windowsHide: false,
    });

    proc.on('error', (err: Error) => {
      safeReply('error', `Failed to launch Chrome: ${err.message}. Make sure Google Chrome is installed.`);
    });

    // Give Chrome 2 seconds to start, then check if it's responding
    setTimeout(async () => {
      const running = await isChromeDebugging();
      if (running) {
        safeReply('success', 'Google Chrome launched successfully. Log in to Easyship, then click Scrape Data.');
      } else {
        // Chrome might have opened but handed off to existing instance — still report success
        safeReply('success', 'Chrome opened. If already running, please go to Easyship manually. Then click Scrape Data.');
      }
    }, 2500);

    proc.unref();
  } catch (error: any) {
    safeReply('error', `Failed to launch Chrome: ${error.message}`);
  }
});

ipcMain.on('start-scrape', (event, { dateFrom, dateTo, fastScrape }) => {
  if (scraperProcess && !scraperProcess.killed) {
    event.reply('scrape-log', 'ERROR: Scraper is already running!\n');
    return;
  }

  event.reply('scrape-status', { status: 'running' });

  const workDir = getScraperWorkDir();
  const scriptPath = getScraperScriptPath();

  if (!fs.existsSync(scriptPath)) {
    event.reply('scrape-log', `ERROR: Scraper script not found at: ${scriptPath}\n`);
    event.reply('scrape-status', { status: 'stopped', code: 1 });
    return;
  }

  // Clear progress state to force starting fresh from Page 1, Row 1
  const progressPath = path.join(workDir, 'progress.json');
  if (fs.existsSync(progressPath)) {
    try {
      fs.unlinkSync(progressPath);
    } catch (e) {
      // ignore
    }
  }

  // Generate unique dynamic filename containing date range and exact timestamp
  const pad = (n: number) => n.toString().padStart(2, '0');
  const now = new Date();
  const dateStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;
  const fromStr = dateFrom ? dateFrom : 'ANY';
  const toStr = dateTo ? dateTo : 'ANY';
  const excelName = `Easyship_Data_${fromStr}_to_${toStr}_${dateStr}.xlsx`;
  const excelPath = path.join(workDir, excelName);

  // Save the reference to the newest Excel file
  try {
    fs.writeFileSync(path.join(workDir, 'last_excel.json'), JSON.stringify({ path: excelPath }), 'utf-8');
  } catch (err) {
    // Ignore error
  }

  // Use Electron's built-in node (process.execPath) to run the scraper
  // In packaged mode we use --no-app flag to run as plain node
  const nodeArgs = [scriptPath];
  
  const scraperEnv: NodeJS.ProcessEnv = {
    ...process.env,
    DATE_FROM: dateFrom || '',
    DATE_TO: dateTo || '',
    FAST_SCRAPE: fastScrape ? 'true' : 'false',
    EXCEL_PATH: excelPath,
    PROGRESS_PATH: path.join(workDir, 'progress.json'),
    LOG_FILE_PATH: path.join(workDir, 'logs', 'easyship.log'),
    SCREENSHOTS_DIR: path.join(workDir, 'screenshots'),
    ELECTRON_RUN_AS_NODE: '1', // Makes Electron run as plain Node
  };

  // Launch scraper using Electron as Node runtime
  scraperProcess = spawn(process.execPath, nodeArgs, {
    cwd: workDir,
    env: scraperEnv,
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  if (scraperProcess.stdout) {
    scraperProcess.stdout.on('data', (data) => {
      const text = data.toString();
      if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.webContents.send('scrape-log', text);
      }
    });
  }

  if (scraperProcess.stderr) {
    scraperProcess.stderr.on('data', (data) => {
      const text = data.toString();
      if (mainWindow && !mainWindow.isDestroyed()) {
        mainWindow.webContents.send('scrape-log', `STDERR: ${text}`);
      }
    });
  }

  scraperProcess.on('error', (err) => {
    if (mainWindow && !mainWindow.isDestroyed()) {
      mainWindow.webContents.send('scrape-log', `ERROR: Failed to start scraper: ${err.message}\n`);
      mainWindow.webContents.send('scrape-status', { status: 'stopped', code: 1 });
    }
    scraperProcess = null;
  });

  scraperProcess.on('exit', (code) => {
    scraperProcess = null;
    if (mainWindow && !mainWindow.isDestroyed()) {
      mainWindow.webContents.send('scrape-status', { status: 'stopped', code });
    }
  });
});

ipcMain.on('pause-scrape', (event) => {
  if (scraperProcess && scraperProcess.pid) {
    const cmd = `$member = '[DllImport("ntdll.dll")] public static extern int NtSuspendProcess(IntPtr handle); [DllImport("ntdll.dll")] public static extern int NtResumeProcess(IntPtr handle);'; $type = Add-Type -MemberDefinition $member -Name "Win32ProcessControl" -Namespace "Win32" -PassThru; $proc = Get-Process -Id ${scraperProcess.pid}; $type::NtSuspendProcess($proc.Handle)`;
    spawn('powershell', ['-Command', cmd]);
    event.reply('scrape-log', '\n--- Scraper process PAUSED ---\n');
    event.reply('scrape-status', { status: 'paused' });
  }
});

ipcMain.on('resume-scrape', (event) => {
  if (scraperProcess && scraperProcess.pid) {
    const cmd = `$member = '[DllImport("ntdll.dll")] public static extern int NtSuspendProcess(IntPtr handle); [DllImport("ntdll.dll")] public static extern int NtResumeProcess(IntPtr handle);'; $type = Add-Type -MemberDefinition $member -Name "Win32ProcessControl" -Namespace "Win32" -PassThru; $proc = Get-Process -Id ${scraperProcess.pid}; $type::NtResumeProcess($proc.Handle)`;
    spawn('powershell', ['-Command', cmd]);
    event.reply('scrape-log', '\n--- Scraper process RESUMED ---\n');
    event.reply('scrape-status', { status: 'running' });
  }
});

ipcMain.on('stop-scrape', (event) => {
  if (scraperProcess && !scraperProcess.killed) {
    scraperProcess.kill('SIGTERM');
    scraperProcess = null;
    event.reply('scrape-log', '\n--- Scrape process stopped by user ---\n');
  }
});

ipcMain.on('open-excel', (event) => {
  const workDir = getScraperWorkDir();
  let excelPath = '';

  const lastExcelInfoPath = path.join(workDir, 'last_excel.json');
  if (fs.existsSync(lastExcelInfoPath)) {
    try {
      const info = JSON.parse(fs.readFileSync(lastExcelInfoPath, 'utf-8'));
      if (info && info.path && fs.existsSync(info.path)) {
        excelPath = info.path;
      }
    } catch (e) {
      // ignore
    }
  }

  if (!excelPath) {
    try {
      const files = fs.readdirSync(workDir)
        .filter(f => f.startsWith('Easyship_Data_') && f.endsWith('.xlsx'))
        .map(f => ({ name: f, time: fs.statSync(path.join(workDir, f)).mtime.getTime() }))
        .sort((a, b) => b.time - a.time);
      if (files.length > 0) {
        excelPath = path.join(workDir, files[0].name);
      }
    } catch (e) {
      // ignore
    }
  }

  if (excelPath && fs.existsSync(excelPath)) {
    shell.openPath(excelPath);
  } else {
    event.reply('scrape-log', 'INFO: No dynamic Excel file found yet. Run a scrape first.\n');
  }
});

ipcMain.on('get-data-dir', (event) => {
  event.reply('data-dir', getScraperWorkDir());
});
