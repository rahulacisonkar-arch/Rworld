<?php
/**
 * ROOFIQ AI — PDF Report Generator
 * POST: JSON with address + roof data
 * Returns: PDF file download
 */

require_once dirname(__DIR__) . '/src/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'POST required';
    exit;
}

$body    = file_get_contents('php://input');
$payload = json_decode($body, true);

$address    = $payload['address'] ?? 'Unknown Address';
$lat        = floatval($payload['lat'] ?? 0);
$lng        = floatval($payload['lng'] ?? 0);
$roofData   = $payload['roof_data'] ?? [];
$reportDate = date('F j, Y');
$reportTime = date('g:i A');
$reportId   = 'RIQ-' . strtoupper(substr(md5($address . time()), 0, 8));

// ====================================================
// Build raw PDF without external library
// ====================================================

// Colors as RGB arrays
$colorNavy  = [10, 14, 26];
$colorCyan  = [0, 212, 255];
$colorWhite = [255, 255, 255];
$colorGray  = [180, 185, 210];
$colorGreen = [0, 230, 118];
$colorOrange= [255, 107, 53];

// Roof data extraction
$roofArea      = intval($roofData['roof_area_sqft'] ?? 1850);
$roofPitch     = intval($roofData['roof_pitch_deg'] ?? 22);
$ridgeLength   = intval($roofData['ridge_length_ft'] ?? 48);
$eaveLength    = intval($roofData['eave_length_ft'] ?? 68);
$condScore     = intval($roofData['condition_score'] ?? 78);
$condLabel     = $roofData['condition_label'] ?? 'Good';
$totalPanels   = intval($roofData['total_panels'] ?? 24);
$solarKwh      = intval($roofData['solar_kwh_year'] ?? 9600);
$annualSavings = intval($roofData['savings_usd_year'] ?? 1248);
$sections      = $roofData['sections'] ?? [];
$appName       = roofiq_app_name();

// --- Generate PDF bytes ---
$pdf = new SimplePDF();
$pdf->addPage();

// ---- HEADER ----
$pdf->setFillColor($colorNavy[0], $colorNavy[1], $colorNavy[2]);
$pdf->rect(0, 0, 595, 120, 'F');

$pdf->setFillColor($colorCyan[0], $colorCyan[1], $colorCyan[2]);
$pdf->rect(0, 115, 595, 5, 'F');

$pdf->setTextColor(255, 255, 255);
$pdf->setFont('Helvetica', 'B', 22);
$pdf->text(40, 45, $appName);

$pdf->setFont('Helvetica', '', 11);
$pdf->setTextColor($colorGray[0], $colorGray[1], $colorGray[2]);
$pdf->text(40, 62, 'Advanced 3D Roof Intelligence Report');

$pdf->setFont('Helvetica', '', 9);
$pdf->text(40, 80, 'Report ID: ' . $reportId . '   |   Generated: ' . $reportDate . ' at ' . $reportTime);

// Report for address
$pdf->setFont('Helvetica', 'B', 10);
$pdf->setTextColor($colorCyan[0], $colorCyan[1], $colorCyan[2]);
$pdf->text(40, 100, 'PROPERTY: ' . strtoupper($address));

// ---- SECTION: KEY METRICS ----
$y = 145;
$pdf->setFont('Helvetica', 'B', 13);
$pdf->setTextColor(10, 14, 26);
$pdf->text(40, $y, 'KEY ROOF METRICS');

$pdf->setFillColor(230, 232, 242);
$pdf->rect(40, $y + 8, 515, 1, 'F');

$y += 25;
$metricCards = [
    ['label' => 'Total Roof Area',    'value' => number_format($roofArea) . ' sq ft', 'x' => 40],
    ['label' => 'Average Pitch',      'value' => $roofPitch . '°',                     'x' => 180],
    ['label' => 'Ridge Length',       'value' => $ridgeLength . ' ft',                 'x' => 310],
    ['label' => 'Eave Length',        'value' => $eaveLength . ' ft',                  'x' => 430],
];

foreach ($metricCards as $card) {
    $pdf->setFillColor(240, 242, 250);
    $pdf->rect($card['x'], $y - 15, 125, 55, 'F');
    $pdf->setFillColor($colorNavy[0], $colorNavy[1], $colorNavy[2]);
    $pdf->rect($card['x'], $y - 15, 125, 4, 'F');

    $pdf->setFont('Helvetica', 'B', 16);
    $pdf->setTextColor(10, 14, 26);
    $pdf->text($card['x'] + 10, $y + 15, $card['value']);

    $pdf->setFont('Helvetica', '', 8);
    $pdf->setTextColor(100, 105, 130);
    $pdf->text($card['x'] + 10, $y + 28, $card['label']);
}

// ---- SECTION: CONDITION SCORE ----
$y += 80;
$pdf->setFont('Helvetica', 'B', 13);
$pdf->setTextColor(10, 14, 26);
$pdf->text(40, $y, 'ROOF CONDITION ANALYSIS');
$pdf->setFillColor(230, 232, 242);
$pdf->rect(40, $y + 8, 515, 1, 'F');

$y += 25;

// Score circle (drawn as rectangle)
$scoreColor = $condScore >= 85 ? $colorGreen : ($condScore >= 70 ? [255, 193, 7] : $colorOrange);
$pdf->setFillColor($scoreColor[0], $scoreColor[1], $scoreColor[2]);
$pdf->rect(40, $y, 80, 80, 'F');
$pdf->setFont('Helvetica', 'B', 28);
$pdf->setTextColor(255, 255, 255);
$pdf->text(55, $y + 48, $condScore);
$pdf->setFont('Helvetica', '', 8);
$pdf->text(50, $y + 65, 'OUT OF 100');

$pdf->setFont('Helvetica', 'B', 14);
$pdf->setTextColor(10, 14, 26);
$pdf->text(140, $y + 20, $condLabel . ' Condition');
$pdf->setFont('Helvetica', '', 10);
$pdf->setTextColor(80, 85, 110);
$pdf->text(140, $y + 36, 'Overall condition score based on satellite imagery AI analysis.');
$pdf->text(140, $y + 50, 'Score considers material integrity, drainage, and structural soundness.');

// Sections table
if (!empty($sections)) {
    $y += 100;
    $pdf->setFont('Helvetica', 'B', 10);
    $pdf->setTextColor(10, 14, 26);
    $pdf->setFillColor($colorNavy[0], $colorNavy[1], $colorNavy[2]);
    $pdf->rect(40, $y, 515, 20, 'F');

    $pdf->setTextColor(255, 255, 255);
    $pdf->text(50, $y + 14, 'Section');
    $pdf->text(230, $y + 14, 'Area (sq ft)');
    $pdf->text(380, $y + 14, 'Condition Score');

    foreach ($sections as $i => $sec) {
        $y += 22;
        $bg = $i % 2 === 0 ? [245, 246, 252] : [255, 255, 255];
        $pdf->setFillColor($bg[0], $bg[1], $bg[2]);
        $pdf->rect(40, $y - 6, 515, 20, 'F');

        $secScore = intval($sec['condition'] ?? $condScore);
        $secColor = $secScore >= 85 ? $colorGreen : ($secScore >= 70 ? [255, 193, 7] : $colorOrange);

        $pdf->setFont('Helvetica', '', 9);
        $pdf->setTextColor(10, 14, 26);
        $pdf->text(50, $y + 8, $sec['name']);
        $pdf->text(240, $y + 8, number_format($sec['area'] ?? 0));

        $pdf->setFillColor($secColor[0], $secColor[1], $secColor[2]);
        $pdf->rect(380, $y - 2, 45, 14, 'F');
        $pdf->setFont('Helvetica', 'B', 9);
        $pdf->setTextColor(255, 255, 255);
        $pdf->text(395, $y + 8, $secScore . '/100');
    }
}

// ---- NEW PAGE: SOLAR ----
$pdf->addPage();

// Header bar on page 2
$pdf->setFillColor($colorNavy[0], $colorNavy[1], $colorNavy[2]);
$pdf->rect(0, 0, 595, 50, 'F');
$pdf->setFillColor($colorCyan[0], $colorCyan[1], $colorCyan[2]);
$pdf->rect(0, 47, 595, 3, 'F');

$pdf->setFont('Helvetica', 'B', 14);
$pdf->setTextColor(255, 255, 255);
$pdf->text(40, 32, $appName . ' — Solar & Coordinates Report');

// Solar section
$y = 80;
$pdf->setFont('Helvetica', 'B', 13);
$pdf->setTextColor(10, 14, 26);
$pdf->text(40, $y, 'SOLAR POTENTIAL ANALYSIS');
$pdf->setFillColor(230, 232, 242);
$pdf->rect(40, $y + 8, 515, 1, 'F');

$y += 28;
$solarCards = [
    ['label' => 'Solar Panels',       'value' => $totalPanels . ' panels',          'x' => 40,  'color' => $colorGreen],
    ['label' => 'Annual Energy',      'value' => number_format($solarKwh) . ' kWh', 'x' => 210, 'color' => $colorGreen],
    ['label' => 'Annual Savings',     'value' => '$' . number_format($annualSavings),'x' => 380, 'color' => $colorGreen],
];

foreach ($solarCards as $card) {
    $pdf->setFillColor(230, 248, 237);
    $pdf->rect($card['x'], $y - 15, 150, 60, 'F');
    $pdf->setFillColor($card['color'][0], $card['color'][1], $card['color'][2]);
    $pdf->rect($card['x'], $y - 15, 150, 4, 'F');

    $pdf->setFont('Helvetica', 'B', 18);
    $pdf->setTextColor(10, 14, 26);
    $pdf->text($card['x'] + 12, $y + 18, $card['value']);
    $pdf->setFont('Helvetica', '', 8);
    $pdf->setTextColor(80, 100, 90);
    $pdf->text($card['x'] + 12, $y + 32, $card['label']);
}

// Property coordinates
$y += 100;
$pdf->setFont('Helvetica', 'B', 13);
$pdf->setTextColor(10, 14, 26);
$pdf->text(40, $y, 'PROPERTY COORDINATES');
$pdf->setFillColor(230, 232, 242);
$pdf->rect(40, $y + 8, 515, 1, 'F');

$y += 30;
$pdf->setFillColor(240, 242, 250);
$pdf->rect(40, $y - 12, 515, 55, 'F');
$pdf->setFont('Helvetica', 'B', 10);
$pdf->setTextColor(10, 14, 26);
$pdf->text(55, $y + 5, 'Full Address:');
$pdf->setFont('Helvetica', '', 10);
$pdf->text(180, $y + 5, $address);
$pdf->setFont('Helvetica', 'B', 10);
$pdf->text(55, $y + 22, 'Latitude:');
$pdf->setFont('Helvetica', '', 10);
$pdf->text(180, $y + 22, number_format($lat, 6));
$pdf->setFont('Helvetica', 'B', 10);
$pdf->text(55, $y + 38, 'Longitude:');
$pdf->setFont('Helvetica', '', 10);
$pdf->text(180, $y + 38, number_format($lng, 6));

// ---- FOOTER ----
$pdf->setFillColor($colorNavy[0], $colorNavy[1], $colorNavy[2]);
$pdf->rect(0, 790, 595, 52, 'F');
$pdf->setFont('Helvetica', '', 8);
$pdf->setTextColor($colorGray[0], $colorGray[1], $colorGray[2]);
$pdf->text(40, 812, 'CONFIDENTIAL — ' . $appName . ' | Report ID: ' . $reportId);
$pdf->text(40, 825, 'Analysis data is AI-generated and should be verified by a licensed roofing professional before any purchasing decisions.');
$pdf->setTextColor($colorCyan[0], $colorCyan[1], $colorCyan[2]);
$pdf->text(450, 812, 'Page 2 of 2');

// Output PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="RoofIQ_Report_' . $reportId . '.pdf"');
header('Cache-Control: no-cache, no-store, must-revalidate');
echo $pdf->output();
exit;

// ============================================================
// Minimal PDF Writer (no external library required)
// ============================================================
class SimplePDF {
    private $pages     = [];
    private $objects   = [];
    private $currentPage = 0;
    private $pageWidth  = 595;
    private $pageHeight = 842;
    private $fillColor  = [255, 255, 255];
    private $textColor  = [0, 0, 0];
    private $font       = 'Helvetica';
    private $fontSize   = 10;
    private $fontStyle  = '';
    private $streams    = [];

    public function addPage() {
        $this->pages[] = ['content' => '', 'height' => $this->pageHeight];
        $this->currentPage = count($this->pages) - 1;
    }

    public function setFillColor($r, $g, $b) {
        $this->fillColor = [$r, $g, $b];
    }

    public function setTextColor($r, $g, $b) {
        $this->textColor = [$r, $g, $b];
    }

    public function setFont($family, $style = '', $size = 10) {
        $this->font      = $family;
        $this->fontStyle = $style;
        $this->fontSize  = $size;
    }

    public function rect($x, $y, $w, $h, $style = 'F') {
        $r = $this->fillColor[0] / 255;
        $g = $this->fillColor[1] / 255;
        $b = $this->fillColor[2] / 255;
        $py = $this->pageHeight - $y - $h;
        $this->pages[$this->currentPage]['content'] .=
            sprintf("%.4f %.4f %.4f rg\n", $r, $g, $b) .
            sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $py, $w, $h);
    }

    public function text($x, $y, $text) {
        $r = $this->textColor[0] / 255;
        $g = $this->textColor[1] / 255;
        $b = $this->textColor[2] / 255;
        $py = $this->pageHeight - $y;
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $fontName = $this->font;
        if ($this->fontStyle === 'B') $fontName .= '-Bold';
        elseif ($this->fontStyle === 'I') $fontName .= '-Oblique';
        elseif ($this->fontStyle === 'BI') $fontName .= '-BoldOblique';

        $this->pages[$this->currentPage]['content'] .=
            sprintf("BT\n%.4f %.4f %.4f rg\n/%s %d Tf\n%.2f %.2f Td\n(%s) Tj\nET\n",
                $r, $g, $b, $fontName, $this->fontSize, $x, $py, $text);
    }

    public function output() {
        $fonts = ['Helvetica', 'Helvetica-Bold', 'Helvetica-Oblique', 'Helvetica-BoldOblique'];
        $out   = "%PDF-1.4\n";
        $objects = [];
        $offsets = [];
        $objNum = 1;

        // Font objects
        $fontObjNums = [];
        foreach ($fonts as $fName) {
            $fKey = str_replace('-', '', $fName);
            $objects[$objNum] = "$objNum 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /$fName /Encoding /WinAnsiEncoding >>\nendobj\n";
            $fontObjNums[$fKey] = $objNum;
            $objNum++;
        }

        // Build font resource dict
        $fontDict = '';
        foreach ($fontObjNums as $k => $n) {
            $fontDict .= "/$k $n 0 R ";
        }

        // Page content streams + page objects
        $pageObjNums = [];
        foreach ($this->pages as $page) {
            $stream = $page['content'];
            $streamLen = strlen($stream);
            $objects[$objNum] = "$objNum 0 obj\n<< /Length $streamLen >>\nstream\n$stream\nendstream\nendobj\n";
            $contentObjNum = $objNum;
            $objNum++;

            $objects[$objNum] = "$objNum 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] /Contents $contentObjNum 0 R /Resources << /Font << $fontDict>> >> >>\nendobj\n";
            $pageObjNums[] = $objNum;
            $objNum++;
        }

        // Pages object
        $pageRefs = implode(' 0 R ', $pageObjNums) . ' 0 R';
        $pageCount = count($this->pages);
        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [$pageRefs] /Count $pageCount >>\nendobj\n";

        // Catalog (obj 1 placeholder)
        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // Write objects in order
        ksort($objects);
        foreach ($objects as $num => $content) {
            $offsets[$num] = strlen($out);
            $out .= $content;
        }

        // Cross-reference table
        $xrefOffset = strlen($out);
        $out .= "xref\n0 " . ($objNum) . "\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i < $objNum; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $out .= "trailer\n<< /Size $objNum /Root 1 0 R >>\n";
        $out .= "startxref\n$xrefOffset\n%%EOF";

        return $out;
    }
}
