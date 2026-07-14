# FabricMill Product Scraper

A robust, Cloudflare-resilient, and resume-capable web scraper built using Python, Playwright, and SQLite.

## Features
- **Cloudflare Bypass**: Runs via Playwright headless Chromium instances to parse sitemaps and category pagination.
- **SQLite Local Cache**: Tracks queue state and harvested records. If execution is interrupted, restarting the scraper will automatically skip completed URLs.
- **Export to CSV & Excel**: Automatically generates both a CSV file (`fabricmill_products.csv`) and a formatted Excel workbook (`fabricmill_products.xlsx`) containing all parsed products.

## Running the Scraper
Use the Python environment to execute the scraper script:

```bash
# Run the complete scrape
python scraper.py

# Run a limited test scrape of 5 items
python scraper.py --limit 5

# Export existing SQLite cache database to CSV and Excel directly
python scraper.py --export
```
