# Easyship Shipment Extraction Bot

A complete, production-ready Playwright + TypeScript + ExcelJS application that automatically attaches to an existing Chrome session, extracts shipment information from Easyship, and writes all records to Excel.

## Prerequisites

- Node.js (v18 or higher recommended)
- npm

## Setup & Running Chrome

1. Fully close all existing Chrome browser instances.
2. Launch Google Chrome manually from command line with remote debugging enabled:
   * **Windows**:
     ```bash
     "C:\Program Files\Google\Chrome\Application\chrome.exe" --remote-debugging-port=9222 --user-data-dir="C:\ChromeDebugProfile"
     ```
   * **macOS**:
     ```bash
     /Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --remote-debugging-port=9222 --user-data-dir="/tmp/chrome_dev_test"
     ```
3. Open Chrome, log into Easyship manually, and navigate to:
   `https://app.easyship.com/shipments?tab_id=purchased`

## Scraper Execution

1. Clone or navigate to the scraper repository.
2. Install dependencies:
   ```bash
   npm install
   ```
3. Run the compiler and start the bot:
   ```bash
   npm run start
   ```

## Features

- **CDP Attachment**: Seamlessly reuses your already logged-in session on port 9222.
- **Duplicate Prevention**: Skips already processed shipments by tracking numbers.
- **Resumable Progress**: Saves state to `progress.json` and resumes automatically after interruption.
- **Robust Error Handling**: Screenshots on failure, retries operations up to 3 times.
- **Structured JSON Logging**: Detailed logs stored in `logs/easyship.log`.
- **Clean Output**: Formatted Excel spreadsheet `shipments.xlsx`.
