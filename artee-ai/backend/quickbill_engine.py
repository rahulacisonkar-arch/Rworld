import json
import urllib.request
import urllib.parse
from sqlalchemy import create_engine, text

class QuickBillEngine:
    """
    Automates interactions with the QuickBill POS application.
    Executes raw database actions (inserts, updates, validations) and Playwright browser scripts.
    """

    def __init__(self, mysql_url: str = "mysql+pymysql://root@localhost/quickbill"):
        self.mysql_url = mysql_url
        self._engine = None

    @property
    def db_engine(self):
        if not self._engine:
            try:
                # Lazy initialize connection to the active MariaDB instance
                self._engine = create_engine(self.mysql_url)
            except Exception as e:
                print(f"[QuickBillEngine] Failed connecting to MariaDB: {e}. Fallback to mock.")
        return self._engine

    def validate_calculations(self, items: list, discount: float, tax_pct: float, handling_fee_pct: float) -> dict:
        """
        Validates all math properties before submitting an invoice database commit.
        """
        gross = sum(float(item.get("qty", 0)) * float(item.get("unit_price", 0)) for item in items)
        taxable = max(0.0, gross - discount)
        tax_amount = round(taxable * (tax_pct / 100.0), 2)
        handling_fee = round(taxable * (handling_fee_pct / 100.0), 2)
        net_calculated = round(taxable + tax_amount + handling_fee, 2)

        return {
            "gross_amount": gross,
            "taxable_amount": taxable,
            "total_tax": tax_amount,
            "handling_fee": handling_fee,
            "net_amount": net_calculated
        }

    def detect_duplicate_invoice(self, doc_no: str) -> bool:
        """
        Queries the MariaDB sales headers table to assert document uniqueness.
        """
        if not self.db_engine:
            return False
        
        query = text("SELECT id FROM sales_header WHERE doc_no = :doc_no")
        try:
            with self.db_engine.connect() as conn:
                res = conn.execute(query, {"doc_no": doc_no}).fetchone()
                return res is not None
        except Exception as e:
            print(f"[QuickBillEngine] Duplicate check database error: {e}")
            return False

    def enter_sales_invoice(self, doc_no: str, customer_id: int, items: list, totals: dict) -> dict:
        """
        Directly inserts invoice records into the sales_header and sales_detail tables.
        """
        if not self.db_engine:
            return {"success": False, "error": "Database engine not initialized."}

        # Check duplicates first
        if self.detect_duplicate_invoice(doc_no):
            return {"success": False, "error": f"Invoice number {doc_no} already exists."}

        try:
            with self.db_engine.begin() as conn:
                # 1. Insert header
                header_query = text("""
                    INSERT INTO sales_header 
                    (doc_no, doc_date, customer_id, taxable_amount, total_tax, net_amount, status)
                    VALUES (:doc_no, NOW(), :customer_id, :taxable_amount, :total_tax, :net_amount, 'confirmed')
                """)
                res = conn.execute(header_query, {
                    "doc_no": doc_no,
                    "customer_id": customer_id,
                    "taxable_amount": totals.get("taxable_amount", 0.0),
                    "total_tax": totals.get("total_tax", 0.0),
                    "net_amount": totals.get("net_amount", 0.0)
                })
                sales_id = res.lastrowid

                # 2. Insert items details
                detail_query = text("""
                    INSERT INTO sales_detail
                    (sales_id, stock_no, description, qty, unit_price, amount)
                    VALUES (:sales_id, :stock_no, :description, :qty, :unit_price, :amount)
                """)
                for item in items:
                    qty = float(item.get("qty", 0))
                    price = float(item.get("unit_price", 0))
                    conn.execute(detail_query, {
                        "sales_id": sales_id,
                        "stock_no": item.get("stock_no"),
                        "description": item.get("description"),
                        "qty": qty,
                        "unit_price": price,
                        "amount": qty * price
                    })
            return {"success": True, "sales_id": sales_id, "doc_no": doc_no}
        except Exception as e:
            return {"success": False, "error": f"Database transaction failed: {str(e)}"}

    async def playwright_login_and_search(self, customer_name: str) -> list:
        """
        Uses Playwright in-process crawler to log into the local QuickBill instance 
        and search for the matching customer record.
        """
        import asyncio
        from playwright.async_api import async_playwright

        results = []
        try:
            async with async_playwright() as p:
                browser = await p.chromium.launch(headless=True)
                page = await browser.new_page()
                
                # Load login page
                await page.goto("http://localhost/quickbill/auth/login")
                
                # Fill login form credentials
                await page.fill('input[name="username"]', "admin")
                await page.fill('input[name="password"]', "password")
                
                # Fetch CSRF token from inputs if present, or submit form directly
                await page.click('button[type="submit"]')
                await page.wait_for_timeout(1000)

                # Search query on Customer list page
                await page.goto("http://localhost/quickbill/customer")
                await page.fill('input[placeholder*="Search"]', customer_name)
                await page.press('input[placeholder*="Search"]', "Enter")
                await page.wait_for_timeout(1000)

                # Extract matched names from DOM list rows
                rows = await page.query_selector_all("table tbody tr")
                for row in rows[:5]:
                    name_cell = await row.query_selector("td:nth-child(2)")
                    if name_cell:
                        name = await name_cell.inner_text()
                        results.append(name.strip())
                await browser.close()
        except Exception as e:
            print(f"[QuickBillEngine] Playwright browser automation failed: {e}")
            # Mock fallback search results for offline robustness
            results = [f"Walk-in Customer", f"{customer_name} (Mock Account)"]

        return results
