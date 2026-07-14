import os
from fpdf import FPDF

class PDF(FPDF):
    def header(self):
        # Draw top accent bar
        self.set_fill_color(30, 90, 168)  # Primary Blue
        self.rect(0, 0, 210, 12, "F")
        self.set_fill_color(212, 175, 55)  # Gold Accent
        self.rect(0, 12, 210, 2, "F")
        self.ln(6)

    def footer(self):
        # Draw bottom accent bar
        self.set_y(-18)
        self.set_fill_color(30, 90, 168)
        self.rect(0, 285, 210, 12, "F")
        self.set_font("Helvetica", "I", 8)
        self.set_text_color(255, 255, 255)
        self.cell(0, 10, f"Page {self.page_no()} of {{nb}}  |  Artée Fabrics & Home", align="C")

def generate_pdf():
    pdf = PDF()
    pdf.alias_nb_pages()
    pdf.add_page()
    pdf.set_auto_page_break(auto=True, margin=20)
    
    # ----------------------------------------------------
    # DOCUMENT TITLE
    # ----------------------------------------------------
    pdf.set_font("Helvetica", "B", 22)
    pdf.set_text_color(30, 90, 168)
    pdf.cell(0, 15, "Utility Operations Portal", ln=1, align="L")
    
    pdf.set_font("Helvetica", "B", 13)
    pdf.set_text_color(212, 175, 55)
    pdf.cell(0, 5, "DEPLOYMENT & LOGIN ID GUIDE", ln=1, align="L")
    
    pdf.set_draw_color(220, 220, 220)
    pdf.line(10, 36, 200, 36)
    pdf.ln(10)
    
    # ----------------------------------------------------
    # SECTION 1: SYSTEM ROLES & LOGIN CREDENTIALS
    # ----------------------------------------------------
    pdf.set_font("Helvetica", "B", 14)
    pdf.set_text_color(30, 90, 168)
    pdf.cell(0, 10, "1. Portal Roles & Login Credentials", ln=1)
    
    pdf.set_font("Helvetica", "", 10)
    pdf.set_text_color(80, 80, 80)
    pdf.multi_cell(0, 5, "The utility portal operates on a role-based access system with two distinct user levels designed for different operational workflows:")
    pdf.ln(4)
    
    # Login Table Headers
    pdf.set_font("Helvetica", "B", 10)
    pdf.set_text_color(255, 255, 255)
    pdf.set_fill_color(30, 90, 168)
    pdf.cell(35, 8, "Role / Level", border=1, fill=True, align="C")
    pdf.cell(35, 8, "Username", border=1, fill=True, align="C")
    pdf.cell(35, 8, "Password", border=1, fill=True, align="C")
    pdf.cell(85, 8, "Description / Privileges", border=1, fill=True, align="C")
    pdf.ln(8)
    
    # Row 1: Admin
    pdf.set_font("Helvetica", "", 9.5)
    pdf.set_text_color(50, 50, 50)
    pdf.cell(35, 12, " Admin", border=1, align="L")
    pdf.set_font("Helvetica", "B", 9.5)
    pdf.cell(35, 12, " admin", border=1, align="C")
    pdf.cell(35, 12, " admin123", border=1, align="C")
    pdf.set_font("Helvetica", "", 9)
    pdf.cell(85, 12, " Full read/write connection directory access, delete bills,\n register store meters, and view operations logs.", border=1, align="L")
    pdf.ln(12)
    
    # Row 2: Payments
    pdf.set_font("Helvetica", "", 9.5)
    pdf.cell(35, 12, " Payments", border=1, align="L")
    pdf.set_font("Helvetica", "B", 9.5)
    pdf.cell(35, 12, " payments", border=1, align="C")
    pdf.cell(35, 12, " payments123", border=1, align="C")
    pdf.set_font("Helvetica", "", 9)
    pdf.cell(85, 12, " Access bills list ledger, process payments, and upload\n transaction receipt verification records.", border=1, align="L")
    pdf.ln(18)

    # ----------------------------------------------------
    # SECTION 2: DEPLOYMENT GUIDE
    # ----------------------------------------------------
    pdf.set_font("Helvetica", "B", 14)
    pdf.set_text_color(30, 90, 168)
    pdf.cell(0, 10, "2. System Deployment Guide", ln=1)
    
    pdf.set_font("Helvetica", "", 10)
    pdf.set_text_color(50, 50, 50)
    
    # Steps
    steps = [
        ("Step 1: Database Initialization", 
         "The database is powered by MariaDB/MySQL. Ensure your database server is running on localhost:3306. Run the seeder.php script (located in the project folder root) to automatically create the 'artee_utility' database schema and pre-populate retail store records, connection mapped data, and default accounts."),
        ("Step 2: Start the Web Server", 
         "Launch the local server using PHP's built-in web server. Execute the following command in your terminal from the project folder root directory:\nphp -S localhost:80 -t public/\n(Or map the directory to an Apache/IIS server target path)."),
        ("Step 3: Access the Interface", 
         "Open your web browser and navigate to:\nhttp://localhost/utility-portal/public/\nUse one of the credentials above to sign in and begin operations."),
        ("Step 4: YoY Expenses Python Prerequisites", 
         "The Year-over-Year (YoY) combined reports and styled Excel chart generators require Python with dependencies. Ensure 'pandas' and 'openpyxl' are installed, or invoke script runners using 'uv run' inside the project directory.")
    ]
    
    for title, desc in steps:
        pdf.set_font("Helvetica", "B", 10.5)
        pdf.set_text_color(30, 90, 168)
        pdf.cell(0, 6, title, ln=1)
        pdf.set_font("Helvetica", "", 9.5)
        pdf.set_text_color(80, 80, 80)
        pdf.multi_cell(0, 5, desc)
        pdf.ln(4)
        
    pdf.ln(6)
    
    # ----------------------------------------------------
    # SECTION 3: KEY SYSTEM FEATURES
    # ----------------------------------------------------
    pdf.set_font("Helvetica", "B", 14)
    pdf.set_text_color(30, 90, 168)
    pdf.cell(0, 10, "3. Key Integrated Features", ln=1)
    
    features = [
        "1. Dynamic Scheduling Calendar: Browse any week, manage job tasks, and map real-time payment due indicators.",
        "2. Bulk Excel Data Importer: Pre-styled workbook templates supporting automated store code mapping.",
        "3. Tesseract.js OCR Reader: Scans and extracts bill details in the browser to offload server processing."
    ]
    
    pdf.set_font("Helvetica", "", 9.5)
    pdf.set_text_color(80, 80, 80)
    for feat in features:
        pdf.cell(0, 5, feat, ln=1)
        
    # Save PDF
    desktop_path = r"C:\Users\Artee Admin\Desktop"
    output_pdf = os.path.join(desktop_path, "utility-portal_deployment_and_login_guide.pdf")
    pdf.output(output_pdf)
    print(f"PDF successfully generated at: {output_pdf}")

if __name__ == "__main__":
    generate_pdf()
