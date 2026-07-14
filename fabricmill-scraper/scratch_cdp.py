import asyncio
from playwright.async_api import async_playwright

async def main():
    try:
        async with async_playwright() as p:
            print("Connecting to CDP...")
            browser = await p.chromium.connect_over_cdp("http://localhost:9222", timeout=5000)
            print("Successfully connected! Browser version:", browser.version)
            await browser.close()
    except Exception as e:
        print("CDP connection failed:", e)

asyncio.run(main())
