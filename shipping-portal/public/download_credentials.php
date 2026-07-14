<?php
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
require_once dirname(__DIR__) . '/src/fpdf.php';

session_start_safe();
require_login();
require_role(['Super Admin', 'Logistics Admin']);

// Initialize FPDF
class CredentialsPDF extends FPDF {
    // Page Header
    function Header() {
        // Logo / Title Text
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(11, 37, 69); // Brand navy blue #0B2545
        $this->Cell(0, 10, 'ARTEE FABRICS & HOME', 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'SHIPPING PORTAL - SYSTEM CREDENTIALS DIRECTORY', 0, 1, 'C');
        
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, 28, 200, 28);
        $this->Ln(10);
    }

    // Page Footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        // Print page number and confidentiality note
        $this->Cell(0, 10, 'CONFIDENTIAL - FOR INTERNAL HEADQUARTERS USE ONLY | Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

// Fetch all stores and their associated store user accounts
try {
    $stmtStores = $pdo->query("SELECT s.store_code, s.store_name, s.city, s.state, u.username, s.notification_emails 
                               FROM stores s 
                               LEFT JOIN users u ON s.id = u.store_id AND u.role = 'Store User'
                               ORDER BY s.store_code ASC");
    $storesList = $stmtStores->fetchAll();
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

$pdf = new CredentialsPDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Add General Admin logins
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(11, 37, 69);
$pdf->Cell(0, 8, '1. Administrative & Logistics Control Panels', 0, 1, 'L');
$pdf->Ln(2);

// Table Header for Admins
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(50, 50, 50);
$pdf->Cell(60, 7, 'Role', 1, 0, 'L', true);
$pdf->Cell(60, 7, 'Username', 1, 0, 'L', true);
$pdf->Cell(70, 7, 'Default Password', 1, 1, 'L', true);

// Table Data for Admins
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(60, 7, 'Super Admin Panel', 1, 0, 'L');
$pdf->Cell(60, 7, 'admin', 1, 0, 'L');
$pdf->Cell(70, 7, 'admin123', 1, 1, 'L');

$pdf->Cell(60, 7, 'Logistics Manager', 1, 0, 'L');
$pdf->Cell(60, 7, 'logistics', 1, 0, 'L');
$pdf->Cell(70, 7, 'logistics123', 1, 1, 'L');

$pdf->Ln(10);

// Add Store logins
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(11, 37, 69);
$pdf->Cell(0, 8, '2. Store User Logins Directory', 0, 1, 'L');
$pdf->Ln(2);

// Table Header for Stores
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(11, 37, 69);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(20, 7, 'Code', 1, 0, 'C', true);
$pdf->Cell(60, 7, 'Store Name / Location', 1, 0, 'L', true);
$pdf->Cell(50, 7, 'Username', 1, 0, 'L', true);
$pdf->Cell(60, 7, 'Password', 1, 1, 'L', true);

// Table Data for Stores
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);

$fill = false;
foreach ($storesList as $s) {
    // Alternating rows fill
    if ($fill) {
        $pdf->SetFillColor(245, 247, 250);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    $storeLoc = $s['store_name'] . ' (' . $s['city'] . ', ' . $s['state'] . ')';
    
    // Shorten long names to fit FPDF width cleanly
    if (strlen($storeLoc) > 32) {
        $storeLoc = substr($storeLoc, 0, 30) . '...';
    }
    
    $pdf->Cell(20, 7, $s['store_code'], 1, 0, 'C', true);
    $pdf->Cell(60, 7, $storeLoc, 1, 0, 'L', true);
    $pdf->Cell(50, 7, $s['username'] ?: 'N/A', 1, 0, 'L', true);
    $pdf->Cell(60, 7, 'store123', 1, 1, 'L', true);
    
    $fill = !$fill;
}

$pdf->Ln(8);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(0, 4, 'Instructions: To access the shipping portal, navigate to the login page and enter the credentials provided above. Store users will automatically inherit their default coordinates when creating shipping requests.', 0, 'L');

// Serve PDF with correct attachment headers
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="artee_shipping_portal_credentials.pdf"');
header('Pragma: no-cache');
header('Expires: 0');

$pdf->Output('I'); // Output directly
exit;
