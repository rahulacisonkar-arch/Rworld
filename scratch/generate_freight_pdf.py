import os
from datetime import datetime
from reportlab.lib.pagesizes import letter
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import inch
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, HRFlowable

def generate_pdf():
    # File destination
    pdf_path = "C:/Users/Artee Admin/Desktop/Artee_Fabrics_and_Home_Freight_Charges.pdf"
    
    # Page setup - Letter size, 0.75 inch margins
    margin = 54  # 0.75 in * 72 points/in
    doc = SimpleDocTemplate(
        pdf_path,
        pagesize=letter,
        leftMargin=margin,
        rightMargin=margin,
        topMargin=margin,
        bottomMargin=margin
    )
    
    story = []
    
    # Styles
    styles = getSampleStyleSheet()
    
    # Custom Palette
    c_primary = colors.HexColor("#1E3A8A")  # Deep Navy Blue
    c_secondary = colors.HexColor("#3B82F6") # Accent Blue
    c_dark = colors.HexColor("#1F2937")      # Slate Dark
    c_light_bg = colors.HexColor("#F3F4F6")  # Light Gray Background
    c_border = colors.HexColor("#E5E7EB")    # Border Gray
    c_gold = colors.HexColor("#D97706")      # Gold Accent
    
    title_style = ParagraphStyle(
        'DocTitle',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=24,
        leading=28,
        textColor=c_primary
    )
    
    subtitle_style = ParagraphStyle(
        'DocSubtitle',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=10,
        leading=14,
        textColor=colors.HexColor("#4B5563"),
        spaceAfter=15
    )
    
    section_heading = ParagraphStyle(
        'SectionHeading',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=14,
        leading=18,
        textColor=c_primary,
        spaceBefore=15,
        spaceAfter=10
    )
    
    body_style = ParagraphStyle(
        'BodyTextCustom',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=10,
        leading=14,
        textColor=c_dark
    )
    
    body_bold = ParagraphStyle(
        'BodyBoldCustom',
        parent=body_style,
        fontName='Helvetica-Bold'
    )
    
    note_style = ParagraphStyle(
        'NoteText',
        parent=styles['Normal'],
        fontName='Helvetica-Oblique',
        fontSize=9,
        leading=13,
        textColor=colors.HexColor("#4B5563")
    )
    
    th_style = ParagraphStyle(
        'TableHeader',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=10,
        leading=12,
        textColor=colors.white,
        alignment=1 # Center
    )
    
    td_style = ParagraphStyle(
        'TableCell',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=10,
        leading=12,
        textColor=c_dark,
        alignment=1 # Center
    )
    
    td_style_bold = ParagraphStyle(
        'TableCellBold',
        parent=td_style,
        fontName='Helvetica-Bold'
    )

    # 1. Header Section
    story.append(Paragraph("ARTEE FABRICS AND HOME", title_style))
    story.append(Paragraph("FREIGHT & DELIVERY RATE SHEET", subtitle_style))
    
    # Gold decorative bar
    story.append(HRFlowable(width="100%", thickness=3, color=c_gold, spaceBefore=0, spaceAfter=15))
    
    # 2. Metadata details (Effective Date, Doc ID)
    meta_data = [
        [Paragraph("<b>Effective Date:</b> June 25, 2026", body_style), Paragraph("<b>Document ID:</b> AFH-FR-2026-V1", body_style)],
        [Paragraph("<b>Service Area:</b> Standard Ground Delivery", body_style), Paragraph("<b>Status:</b> Active & Approved", body_style)]
    ]
    meta_table = Table(meta_data, colWidths=[250, 254])
    meta_table.setStyle(TableStyle([
        ('ALIGN', (0,0), (-1,-1), 'LEFT'),
        ('VALIGN', (0,0), (-1,-1), 'MIDDLE'),
        ('BOTTOMPADDING', (0,0), (-1,-1), 4),
        ('TOPPADDING', (0,0), (-1,-1), 4),
    ]))
    story.append(meta_table)
    story.append(Spacer(1, 20))
    
    # 3. Overview Paragraph
    story.append(Paragraph("This schedule outlines the official freight rates for goods transported by Artee Fabrics and Home. Rates are structured based on the yardage of fabrics or goods shipped, with standard commercial delivery and residential home delivery tiers listed below.", body_style))
    story.append(Spacer(1, 15))
    
    # 4. Rates Table
    story.append(Paragraph("Freight Rate Schedule", section_heading))
    
    # Columns: Yardage Range, Standard Commercial Delivery, Residential Home Delivery (+$10)
    table_headers = [
        Paragraph("Yardage Range", th_style),
        Paragraph("Commercial/Business Delivery", th_style),
        Paragraph("Home Delivery Surcharge", th_style),
        Paragraph("Total Home Delivery Rate", th_style)
    ]
    
    rates_data = [
        ["2 - 5 yards", "$45.00", "+$10.00", "$55.00"],
        ["6 - 10 yards", "$55.00", "+$10.00", "$65.00"],
        ["11 - 20 yards", "$65.00", "+$10.00", "$75.00"],
        ["21 - 30 yards", "$75.00", "+$10.00", "$85.00"],
        ["31 - 40 yards", "$85.00", "+$10.00", "$95.00"],
        ["41 - 50 yards", "$105.00", "+$10.00", "$115.00"]
    ]
    
    table_content = [table_headers]
    for row in rates_data:
        table_content.append([
            Paragraph(row[0], td_style_bold),
            Paragraph(row[1], td_style),
            Paragraph(row[2], td_style),
            Paragraph(row[3], td_style_bold)
        ])
        
    rates_table = Table(table_content, colWidths=[126, 126, 126, 126])
    rates_table.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), c_primary),
        ('ALIGN', (0,0), (-1,-1), 'CENTER'),
        ('VALIGN', (0,0), (-1,-1), 'MIDDLE'),
        ('BOTTOMPADDING', (0,0), (-1,-1), 8),
        ('TOPPADDING', (0,0), (-1,-1), 8),
        ('GRID', (0,0), (-1,-1), 0.5, c_border),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, c_light_bg]),
    ]))
    
    story.append(rates_table)
    story.append(Spacer(1, 20))
    
    # 5. Important Terms and Notes
    story.append(Paragraph("Policy & Delivery Terms", section_heading))
    
    terms = [
        "<b>Home Delivery Surcharge:</b> An additional $10.00 surcharge is automatically applied to all home/residential deliveries, as reflected in the table above.",
        "<b>Minimum Order:</b> The standard rate sheet starts at a minimum of 2 yards. For shipments below 2 yards, standard ground carrier fees may apply.",
        "<b>Oversized Shipments:</b> For any shipments exceeding 50 yards, please contact the Artee Fabrics and Home Administrative Office directly to obtain a custom freight quote.",
        "<b>Delivery Constraints:</b> Deliveries are subject to standard carrier routes, weather conditions, and seasonal volume constraints. Standard transit times are 3-7 business days."
    ]
    
    for term in terms:
        story.append(Paragraph(f"&bull; {term}", body_style))
        story.append(Spacer(1, 6))
        
    story.append(Spacer(1, 15))
    
    # Build Document
    doc.build(story)
    print(f"Successfully generated freight rate PDF at: {pdf_path}")

if __name__ == "__main__":
    generate_pdf()
