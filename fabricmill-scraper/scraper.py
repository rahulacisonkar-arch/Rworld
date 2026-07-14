import os
import re
import csv
import sys
import time
import sqlite3
import argparse
from bs4 import BeautifulSoup
from playwright.async_api import async_playwright
import asyncio
from rich.console import Console
from rich.table import Table
from rich.progress import Progress, BarColumn, TextColumn, TimeRemainingColumn

# Configuration
DB_PATH = "scraper.db"
CSV_PATH = "fabricmill_products.csv"
USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"

console = Console()

def init_db():
    """Initializes SQLite database for crawler state persistence"""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    
    # Queue table
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS queue (
            url TEXT PRIMARY KEY,
            status TEXT DEFAULT 'pending',
            retries INTEGER DEFAULT 0
        )
    """)
    
    # Products table
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS products (
            url TEXT PRIMARY KEY,
            name TEXT,
            description TEXT,
            sku TEXT,
            manufacturer TEXT,
            short_description TEXT,
            width TEXT,
            type_of_fabric TEXT,
            fiber_content TEXT,
            retail_price TEXT,
            scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """)
    conn.commit()
    conn.close()

async def harvest_sitemap(page):
    """Fetches and parses sitemap.xml to populate queue"""
    console.print("[yellow]Attempting to harvest product URLs from sitemap...[/yellow]")
    sitemap_url = "https://www.fabricmill.com/media/sitemap/sitemap.xml"
    
    try:
        await page.goto(sitemap_url, timeout=60000, wait_until="networkidle")
        content = await page.content()
        
        # Extract URLs matching .html pattern
        urls = re.findall(r'href="([^"]+\.html)"', content) or re.findall(r'&lt;loc&gt;(https://www.fabricmill.com/[^&]+\.html)&lt;/loc&gt;', content) or re.findall(r'<loc>(https://www.fabricmill.com/[^<]+\.html)</loc>', content)
        
        # Clean URLs
        cleaned_urls = set()
        exclude_patterns = [
            "contacts", "about", "customer", "checkout", "cart", "sales", 
            "privacy", "terms", "sitemap", "fabrics.html", "upholstery-fabrics.html",
            "drapery-fabrics.html", "outdoor-fabrics.html"
        ]
        
        for url in urls:
            url = url.strip()
            if any(p in url for p in exclude_patterns):
                continue
            cleaned_urls.add(url)
            
        return list(cleaned_urls)
    except Exception as e:
        console.print(f"[red]Failed to fetch sitemap via Playwright: {e}[/red]")
        return []

async def harvest_categories(page):
    """Fallback method: crawls paginated category pages to collect product links"""
    console.print("[yellow]Sitemap unavailable. Crawling category pages...[/yellow]")
    base_category_url = "https://www.fabricmill.com/fabrics.html"
    product_urls = set()
    current_page = 1
    
    while True:
        url = f"{base_category_url}?p={current_page}"
        console.print(f"[blue]Crawling category page {current_page}: {url}[/blue]")
        
        try:
            await page.goto(url, timeout=45000, wait_until="load")
            html = await page.content()
            soup = BeautifulSoup(html, "html.parser")
            
            # Find product links
            links = soup.select(".product-item-link") or soup.select(".product-image-container a")
            page_urls = set()
            for link in links:
                href = link.get("href")
                if href and href.startswith("https://"):
                    page_urls.add(href)
                    
            if not page_urls:
                console.print("[yellow]No more product links found on page. Ending harvest.[/yellow]")
                break
                
            product_urls.update(page_urls)
            console.print(f"[green]Found {len(page_urls)} product links. Total collected: {len(product_urls)}[/green]")
            
            # Check for next page button
            next_btn = soup.select_one(".pages-item-next a") or soup.select_one("a.next")
            if not next_btn:
                break
                
            current_page += 1
            await asyncio.sleep(2) # rate limit politeness
        except Exception as e:
            console.print(f"[red]Error on category page {current_page}: {e}[/red]")
            break
            
    return list(product_urls)

def add_urls_to_queue(urls):
    """Inserts urls into sqlite database queue"""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    added_count = 0
    for url in urls:
        try:
            cursor.execute("INSERT INTO queue (url, status) VALUES (?, 'pending')", (url,))
            added_count += 1
        except sqlite3.IntegrityError:
            pass # already in queue
    conn.commit()
    conn.close()
    return added_count

def get_pending_urls(limit=None):
    """Retrieves list of pending URLs to process"""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    if limit:
        cursor.execute("SELECT url FROM queue WHERE status = 'pending' LIMIT ?", (limit,))
    else:
        cursor.execute("SELECT url FROM queue WHERE status = 'pending'")
    urls = [row[0] for row in cursor.fetchall()]
    conn.close()
    return urls

def update_url_status(url, status, retries=0):
    """Updates status for processed URL"""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("UPDATE queue SET status = ?, retries = retries + ? WHERE url = ?", (status, retries, url))
    conn.commit()
    conn.close()

def save_product(url, product_data):
    """Persists scraped product information"""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("""
        INSERT OR REPLACE INTO products 
        (url, name, description, sku, manufacturer, short_description, width, type_of_fabric, fiber_content, retail_price)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, (
        url,
        product_data.get("name"),
        product_data.get("description"),
        product_data.get("sku"),
        product_data.get("manufacturer"),
        product_data.get("short_description"),
        product_data.get("width"),
        product_data.get("type_of_fabric"),
        product_data.get("fiber_content"),
        product_data.get("retail_price")
    ))
    conn.commit()
    conn.close()

def parse_product_page(url, html):
    """Parses product page HTML to extract attributes"""
    soup = BeautifulSoup(html, "html.parser")
    
    # 1. Product Name
    name_tag = soup.find("h1") or soup.select_one(".page-title")
    name = name_tag.get_text(strip=True) if name_tag else ""
    
    # 2. Description
    desc_div = soup.find("div", class_="product attribute description") or soup.select_one("#description")
    description = desc_div.get_text(strip=True) if desc_div else ""
    
    # 3. Overview details table
    info = {}
    
    # Locate any table containing SKU or Short Description keys
    for table in soup.find_all("table"):
        for row in table.find_all("tr"):
            tds = row.find_all("td")
            th = row.find("th")
            k, v = "", ""
            if th and tds:
                k = th.get_text(strip=True).rstrip(":")
                v = tds[0].get_text(strip=True)
            elif len(tds) == 2:
                k = tds[0].get_text(strip=True).rstrip(":")
                v = tds[1].get_text(strip=True)
            
            if k:
                info[k.strip()] = v.strip()

    # Fallback specs attributes parsing from label/data classes
    specs = soup.select(".additional-attributes tr") or soup.select("tr")
    for s in specs:
        label = s.select_one(".label") or s.select_one("th")
        val = s.select_one(".data") or s.select_one("td")
        if label and val:
            k = label.get_text(strip=True).rstrip(":")
            v = val.get_text(strip=True)
            info[k.strip()] = v.strip()

    # 4. Price
    # Find special price first, then final price amount, then regular price
    price_tag = (
        soup.select_one("[data-price-type='finalPrice'] .price") or 
        soup.select_one(".product-info-main .price") or 
        soup.select_one(".special-price") or 
        soup.select_one(".price-box .price") or
        soup.select_one(".price")
    )
    price = price_tag.get_text(strip=True) if price_tag else ""
    
    return {
        "name": name,
        "description": description,
        "sku": info.get("SKU") or info.get("sku") or "",
        "manufacturer": info.get("Manufacturer") or info.get("manufacturer") or "",
        "short_description": info.get("Short Description") or info.get("short_description") or "",
        "width": info.get("Width") or info.get("width") or "",
        "type_of_fabric": info.get("Type of Fabric") or info.get("type_of_fabric") or "",
        "fiber_content": info.get("Fiber Content") or info.get("fiber_content") or "",
        "retail_price": price
    }

async def scrape_url(context, url):
    """Scrapes a single product URL using browser session"""
    page = await context.new_page()
    try:
        await page.goto(url, timeout=30000, wait_until="load")
        
        # Extra check: wait for body or main content
        await page.wait_for_selector("body", timeout=5000)
        
        html = await page.content()
        data = parse_product_page(url, html)
        
        if not data["name"]:
            raise ValueError("Parsed item name is empty, page may not have loaded fully.")
            
        save_product(url, data)
        update_url_status(url, "completed")
        return True
    except Exception as e:
        console.print(f"[red]Error scraping {url}: {e}[/red]")
        update_url_status(url, "failed", retries=1)
        return False
    finally:
        await page.close()

def export_to_csv():
    """Generates the CSV file from SQLite db results"""
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("""
        SELECT name, description, sku, manufacturer, short_description, width, type_of_fabric, fiber_content, retail_price
        FROM products
    """)
    rows = cursor.fetchall()
    conn.close()
    
    headers = ["Item Name", "Description", "SKU", "Manufacturer", "Short Description", 
               "Width", "Type of Fabric", "Fiber Content", "Retail Price"]
               
    with open(CSV_PATH, "w", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        writer.writerow(headers)
        writer.writerows(rows)
        
    console.print(f"[green]Successfully exported {len(rows)} records to {CSV_PATH}![/green]")

def export_to_excel():
    """Generates a beautiful Excel (.xlsx) file from SQLite db results using xlsxwriter"""
    import xlsxwriter
    excel_path = "fabricmill_products.xlsx"
    
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute("""
        SELECT name, description, sku, manufacturer, short_description, width, type_of_fabric, fiber_content, retail_price
        FROM products
    """)
    rows = cursor.fetchall()
    conn.close()
    
    headers = ["Item Name", "Description", "SKU", "Manufacturer", "Short Description", 
               "Width", "Type of Fabric", "Fiber Content", "Retail Price"]
               
    workbook = xlsxwriter.Workbook(excel_path)
    worksheet = workbook.add_worksheet("Products")
    
    # Enable text wrapping and formatting
    header_format = workbook.add_format({
        'bold': True,
        'bg_color': '#4F81BD',
        'font_color': 'white',
        'border': 1,
        'align': 'center',
        'valign': 'vcenter'
    })
    
    cell_format = workbook.add_format({
        'text_wrap': True,
        'valign': 'top',
        'border': 1
    })
    
    # Write headers
    worksheet.write_row('A1', headers, header_format)
    
    # Write rows
    for r_idx, row in enumerate(rows, start=1):
        worksheet.write_row(r_idx, 0, row, cell_format)
        
    # Auto-adjust column widths
    for col_idx, col_name in enumerate(headers):
        # Determine max length in column
        max_len = len(col_name)
        for row in rows:
            val = str(row[col_idx]) if row[col_idx] is not None else ""
            # Limit wrap check length so width doesn't get ridiculously wide for long descriptions
            max_len = max(max_len, min(len(val), 40))
        worksheet.set_column(col_idx, col_idx, max_len + 3)
        
    # Set freeze panes for headers
    worksheet.freeze_panes(1, 0)
    
    workbook.close()
    console.print(f"[green]Successfully exported {len(rows)} records to {excel_path}![/green]")

async def main():
    parser = argparse.ArgumentParser(description="FabricMill Product Scraper")
    parser.add_argument("--limit", type=int, help="Limit number of items to process")
    parser.add_argument("--export", action="store_true", help="Only run CSV/Excel export from local db cache")
    args = parser.parse_args()

    init_db()

    if args.export:
        export_to_csv()
        export_to_excel()
        return

    async with async_playwright() as p:
        cdp_url = "http://localhost:9222"
        connected_via_cdp = False
        browser = None
        context = None
        
        try:
            console.print(f"[yellow]Attempting to connect to existing Chrome instance on {cdp_url}...[/yellow]")
            browser = await p.chromium.connect_over_cdp(cdp_url, timeout=15000)
            context = browser.contexts[0] if browser.contexts else await browser.new_context()
            connected_via_cdp = True
            console.print("[green]Successfully connected to active Chrome instance via CDP![/green]")
        except Exception as ex:
            console.print(f"[yellow]Could not connect to CDP ({ex}). Launching new browser...[/yellow]")
            browser = await p.chromium.launch(
                headless=False,
                args=["--disable-blink-features=AutomationControlled"]
            )
            context = await browser.new_context(
                user_agent=USER_AGENT,
                viewport={"width": 1280, "height": 800}
            )
        
        # Step 1: Check queue population
        conn = sqlite3.connect(DB_PATH)
        c = conn.cursor()
        c.execute("SELECT count(*) FROM queue")
        queue_count = c.fetchone()[0]
        conn.close()
        
        if queue_count == 0:
            console.print("[yellow]Queue is empty. Running harvesting steps...[/yellow]")
            temp_page = await context.new_page()
            urls = await harvest_sitemap(temp_page)
            if not urls:
                urls = await harvest_categories(temp_page)
            await temp_page.close()
            
            if urls:
                added = add_urls_to_queue(urls)
                console.print(f"[green]Harvesting complete: Added {added} product URLs to queue![/green]")
            else:
                console.print("[red]Could not collect any product URLs. Exiting.[/red]")
                await browser.close()
                return

        # Step 2: Run processing worker loop
        pending_urls = get_pending_urls(limit=args.limit)
        if not pending_urls:
            console.print("[green]No pending URLs left in queue![/green]")
            export_to_csv()
            export_to_excel()
            await browser.close()
            return
            
        console.print(f"[bold green]Starting scraping session: {len(pending_urls)} pending URLs to process.[/bold green]")
        
        # Process sequential/concurrently using batch chunks to be polite
        batch_size = 5
        
        with Progress(
            TextColumn("[bold blue]{task.description}"),
            BarColumn(),
            TextColumn("[progress.percentage]{task.percentage:>3.0f}%"),
            TimeRemainingColumn(),
            console=console
        ) as progress:
            task = progress.add_task("[cyan]Scraping Products", total=len(pending_urls))
            
            for i in range(0, len(pending_urls), batch_size):
                chunk = pending_urls[i:i+batch_size]
                tasks = [scrape_url(context, url) for url in chunk]
                results = await asyncio.gather(*tasks)
                
                # Sleep briefly between requests
                await asyncio.sleep(1.0)
                progress.update(task, advance=len(chunk))

        export_to_csv()
        export_to_excel()
        await browser.close()

if __name__ == "__main__":
    asyncio.run(main())
