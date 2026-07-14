import { spawn } from 'child_process';
import * as http from 'http';

function postJson(path: string, payload: any): Promise<any> {
  return new Promise((resolve, reject) => {
    const data = JSON.stringify(payload);
    const req = http.request({
      hostname: '127.0.0.1',
      port: 3000,
      path: path,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': data.length
      }
    }, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => resolve(body));
    });
    req.on('error', reject);
    req.write(data);
    req.end();
  });
}

async function testBackend() {
  console.log('1. Starting RWORLD SCRAPER Web Server...');
  const server = spawn('node', ['dist/server.js'], { stdio: 'inherit' });
  
  // Wait 2 seconds for server to start listening
  await new Promise(r => setTimeout(r, 2000));

  // Connect to SSE log stream in background to print logs
  console.log('2. Connecting to SSE Log Stream...');
  const sseReq = http.get('http://127.0.0.1:3000/api/logs/stream', (res) => {
    res.on('data', (chunk) => {
      const text = chunk.toString();
      if (text.includes('data:')) {
        console.log(`[SSE Stream] ${text.trim()}`);
      }
    });
  });

  console.log('3. Triggering Fast Scrape...');
  await postJson('/api/start-scrape', { dateFrom: '', dateTo: '', fastScrape: true });

  console.log('Waiting 5 seconds for scraping to run...');
  await new Promise(r => setTimeout(r, 5000));

  console.log('4. Triggering PAUSE...');
  await postJson('/api/pause-scrape', {});

  console.log('Waiting 5 seconds while paused (verify no new logs appear)...');
  await new Promise(r => setTimeout(r, 5000));

  console.log('5. Triggering RESUME...');
  await postJson('/api/resume-scrape', {});

  console.log('Waiting 5 seconds after resume...');
  await new Promise(r => setTimeout(r, 5000));

  console.log('6. Triggering STOP...');
  await postJson('/api/stop-scrape', {});

  console.log('Waiting 2 seconds to settle...');
  await new Promise(r => setTimeout(r, 2000));

  console.log('7. Cleaning up...');
  sseReq.destroy();
  server.kill();
  console.log('Test completed successfully!');
}

testBackend().catch(console.error);
