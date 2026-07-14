<?php
// ============================================================
//  ARTEE FABRICS & HOME — PDF Credentials Directory Generator
//  Uses local FPDF library
// ============================================================

require_once 'C:/Users/Artee Admin/Desktop/browser-use-main/shipping-portal/src/fpdf.php';

class CredentialsPDF extends FPDF
{
    // Page header
    function Header()
    {
        // Colors
        $this->SetTextColor(17, 24, 39); // Slate 900
        $this->SetDrawColor(229, 231, 235); // Gray 200
        
        // Brand Title/Banner
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'ARTEE FABRICS & HOME', 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(107, 114, 128); // Gray 500
        $this->Cell(0, 5, 'SHIPPING PORTAL - SYSTEM CREDENTIALS DIRECTORY', 0, 1, 'L');
        
        // Horizontal rule
        $this->Ln(3);
        $this->Line(10, 28, 200, 28);
        $this->Ln(8);
    }

    // Page footer
    function Footer()
    {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(156, 163, 175); // Gray 400
        
        // Line
        $this->Line(10, 282, 200, 282);
        
        // Page number & Date
        $dateStr = date('F d, Y');
        $this->Cell(100, 10, 'Confidential - Internal Use Only - Generated: ' . $dateStr, 0, 0, 'L');
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    // Load data table
    function FancyTable($header, $data)
    {
        // Colors, line width and bold font
        $this->SetFillColor(59, 130, 246); // Accent blue header (#3B82F6)
        $this->SetTextColor(255);
        $this->SetDrawColor(209, 213, 219); // Gray 300
        $this->SetLineWidth(.3);
        $this->SetFont('Arial', 'B', 10);
        
        // Header widths
        $w = array(15, 115, 30, 30);
        for($i=0;$i<count($header);$i++) {
            $this->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        
        // Color and font restoration
        $this->SetTextColor(55, 65, 81); // Slate 700
        $this->SetFont('Arial', '', 9);
        
        // Data loop
        $fill = false;
        foreach($data as $row) {
            // Row styling
            if ($fill) {
                $this->SetFillColor(249, 250, 251); // Alternate light row (#F9FAFB)
            } else {
                $this->SetFillColor(255);
            }
            
            $this->Cell($w[0], 7, $row[0], 'LRBT', 0, 'C', true);
            $this->Cell($w[1], 7, '  ' . $row[1], 'LRBT', 0, 'L', true);
            $this->Cell($w[2], 7, $row[2], 'LRBT', 0, 'C', true);
            $this->Cell($w[3], 7, $row[3], 'LRBT', 0, 'C', true);
            
            $this->Ln();
            $fill = !$fill;
        }
        
        // Closing line
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

// Instantiate and build document
$pdf = new CredentialsPDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetMargins(10, 15, 10);

// Descriptive header text
$pdf->SetTextColor(75, 85, 99);
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 6, "This directory contains the default system login credentials mapped for all Artee Fabrics & Home store locations.", 0, 'L');
$pdf->Ln(3);

// Portal Link Section
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(17, 24, 39); // Slate 900
$pdf->Cell(35, 6, 'Portal Address: ', 0, 0, 'L');
$pdf->SetFont('Arial', 'U', 10);
$pdf->SetTextColor(37, 99, 235); // Underlined link blue
$pdf->Cell(0, 6, 'http://10.10.1.14/artee/shipping-portal/public/index.php', 0, 1, 'L');
$pdf->Ln(6);

// Table Data
$headers = array('Code', 'Store Name / Location', 'Username', 'Password');
$data = array(
    array('02', 'ARTEE FABRICS AND HOME (PAWTUCKET, RI)', 'store_02', 'store123'),
    array('03', "PRINTER'S ALLEY (BURLINGTON, NC)", 'store_03', 'store123'),
    array('62', 'ARTEE FABRICS & HOME (HENRICO, VA)', 'store_62', 'store123'),
    array('63', 'ARTEE FABRICS & HOME (WILMINGTON, NC)', 'store_63', 'store123'),
    array('64', 'ARTEE FABRICS & HOME (VIRGINIA BEACH, VA)', 'store_64', 'store123'),
    array('67', 'ARTEE FABRICS & HOME (METAIRIE, LA)', 'store_67', 'store123'),
    array('70', 'ARTEE FABRICS & HOME (LOVELAND, CO)', 'store_70', 'store123'),
    array('71', 'RAGS & RICHES (SHELBURNE, VT)', 'store_71', 'store123'),
    array('73', 'GOOD GOODS (DARIEN, CT)', 'store_73', 'store123'),
    array('78', 'ARTEE FABRICS & HOME (PORTSMOUTH, NH)', 'store_78', 'store123'),
    array('82', "PRINTER'S ALLEY (RALEIGH, NC)", 'store_82', 'store123')
);

$pdf->FancyTable($headers, $data);

// Save PDF
$destPath = 'C:/Users/Artee Admin/Desktop/Artee_Shipping_Portal_System_Credentials_Directory.pdf';
$pdf->Output('F', $destPath);
echo "Successfully generated PDF at: " . $destPath;
?>
