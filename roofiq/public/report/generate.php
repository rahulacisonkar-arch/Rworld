<?php
/**
 * ROOFIQ AI — PDF Report Generator (TCPDF)
 * PHP 7.0.1 Compatible
 * TCPDF must be installed at: /vendor/tcpdf/tcpdf.php
 */
require_once dirname(dirname(__DIR__)) . '/src/config.php';
require_once dirname(dirname(__DIR__)) . '/src/db.php';
require_once dirname(dirname(__DIR__)) . '/src/functions.php';
session_start_safe();
require_login();

$prop_id     = intval(isset($_GET['property_id'])  ? $_GET['property_id']  : 0);
$analysis_id = intval(isset($_GET['analysis_id'])  ? $_GET['analysis_id']  : 0);
$report_type = isset($_GET['report_type'])          ? $_GET['report_type']  : 'Full Analysis Report';
$notes       = isset($_GET['notes'])                ? $_GET['notes']        : '';

if (!$prop_id) {
    die('Property ID required');
}

$property = db_fetch("SELECT * FROM properties WHERE id=?", array($prop_id));
if (!$property) {
    die('Property not found');
}

if (!$analysis_id) {
    $analysis = db_fetch("SELECT * FROM roof_analysis WHERE property_id=? ORDER BY id DESC LIMIT 1", array($prop_id));
} else {
    $analysis = db_fetch("SELECT * FROM roof_analysis WHERE id=? AND property_id=?", array($analysis_id, $prop_id));
}

$damage_items = array();
$census = null;
$weather = null;
if ($analysis) {
    $damage_items = db_fetch_all("SELECT * FROM damage_reports WHERE analysis_id=? ORDER BY repair_priority DESC", array($analysis['id']));
    $takeoff      = db_fetch_all("SELECT * FROM material_takeoffs WHERE analysis_id=? ORDER BY category", array($analysis['id']));
    if (!empty($analysis['ai_raw_json'])) {
        $raw_data = json_decode($analysis['ai_raw_json'], true);
        $census = isset($raw_data['census']) ? $raw_data['census'] : null;
        $weather = isset($raw_data['weather']) ? $raw_data['weather'] : null;
    }
}

if (!$census && $property) {
    $census = roofiq_get_census_data($property['latitude'], $property['longitude']);
}
if (!$weather && $property) {
    $weather = roofiq_get_historical_weather($property['latitude'], $property['longitude']);
}

if (empty($takeoff)) {
    $roof_data_local = array(
        'roof_area_sqft' => $analysis ? floatval($analysis['roof_area_sqft']) : 1850,
        'roof_squares'   => $analysis ? floatval($analysis['roof_squares'])   : 18.5,
        'perimeter_ft'   => $analysis ? floatval($analysis['perimeter_ft'])   : 160,
        'ridge_length_ft'=> $analysis ? floatval($analysis['ridge_length_ft']): 40,
    );
    $takeoff = generate_material_takeoff($roof_data_local, 'Residential');
    foreach ($takeoff as $k => $t) {
        $takeoff[$k]['material_name'] = $t['name'];
    }
}

$vendors  = db_fetch_all("SELECT * FROM vendors WHERE is_active=1 ORDER BY is_preferred DESC LIMIT 3");
$company  = roofiq_company_name();
$appName  = roofiq_app_name();
$user     = current_user();

// ---- Check for TCPDF ----
$tcpdf_path = ROOFIQ_ROOT . '/vendor/tcpdf/tcpdf.php';
if (!file_exists($tcpdf_path)) {
    // Fallback: simple HTML output
    output_html_report($property, $analysis, $damage_items, $takeoff, $vendors, $company, $appName, $notes, $report_type);
    exit;
}

// ---- TCPDF Generation ----
require_once $tcpdf_path;

class RoofIQReport extends TCPDF {
    public $company_name = '';
    public $report_title = '';

    public function Header() {
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor(0, 212, 255);
        $this->Cell(0, 10, $this->company_name, 0, 1, 'L');
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 5, $this->report_title, 0, 1, 'L');
        $this->Line(10, 22, 200, 22);
        $this->Ln(3);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 10, 'SHEKHAR ROOFIQ AI Enterprise | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages() . ' | Generated: ' . date('m/d/Y g:ia'), 0, false, 'C');
    }
}

$pdf = new RoofIQReport(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->company_name = $company;
$pdf->report_title = $report_type . ' — ' . ($property['formatted_address'] ? $property['formatted_address'] : $property['address']);

$pdf->SetCreator($appName);
$pdf->SetAuthor($user['full_name']);
$pdf->SetTitle($report_type . ' — RoofIQ AI');
$pdf->SetSubject('Roof Analysis Report');
$pdf->SetKeywords('roofiq, roof analysis, estimation, damage');

$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(10, 28, 10);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->SetFont('helvetica', '', 10);

$pdf->AddPage();

// ---- Property Info ----
$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(0, 212, 255);
$pdf->Cell(0, 8, 'PROPERTY INFORMATION', 0, 1);
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);

$addr = $property['formatted_address'] ? $property['formatted_address'] : $property['address'];
$rows = array(
    array('Property Address', $addr),
    array('Coordinates',      number_format(floatval($property['latitude']), 5) . ', ' . number_format(floatval($property['longitude']), 5)),
    array('Property Type',    $property['property_type'] ? $property['property_type'] : 'Residential'),
    array('Analysis Date',    date('m/d/Y', strtotime($analysis ? $analysis['created_at'] : date('Y-m-d')))),
    array('Estimator',        $user['full_name']),
    array('Report Type',      $report_type),
);
if ($census) {
    $rows[] = array('Assessor Jurisdiction', ($census['county'] ?? 'Unknown County') . ', ' . ($census['state'] ?? ''));
    $rows[] = array('Parcel ID / GEOID',      $census['geoid'] ?? '—');
    $rows[] = array('Census Tract',           $census['tract'] ?? '—');
}
foreach ($rows as $r) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, 6, $r[0] . ':', 1, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, $r[1], 1, 1);
}
$pdf->Ln(6);

// ---- Condition Score ----
if ($analysis) {
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetTextColor(0, 212, 255);
    $pdf->Cell(0, 8, 'ROOF CONDITION ASSESSMENT', 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 24);
    $score = $analysis['condition_score'];
    $pdf->Cell(0, 14, ($score ? $score : '—') . '/100  ' . ($analysis['condition_label'] ? $analysis['condition_label'] : ''), 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Ln(3);
}

// ---- Roof Measurements ----
$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(0, 212, 255);
$pdf->Cell(0, 8, 'ROOF MEASUREMENTS', 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', '', 9);

$meas_rows = array(
    array('Total Roof Area',    $analysis ? number_format(floatval($analysis['roof_area_sqft']),1) . ' sq ft' : '—'),
    array('Roof Squares',       $analysis ? number_format(floatval($analysis['roof_squares']),2)   : '—'),
    array('Pitch',              $analysis ? ($analysis['roof_pitch_ratio'] . ' (' . number_format(floatval($analysis['roof_pitch_deg']),1) . '°)') : '—'),
    array('Ridge Length',       $analysis ? number_format(floatval($analysis['ridge_length_ft']),1) . ' ft' : '—'),
    array('Eave Length',        $analysis ? number_format(floatval($analysis['eave_length_ft']),1)  . ' ft' : '—'),
    array('Perimeter',          $analysis ? number_format(floatval($analysis['perimeter_ft']),1)    . ' ft' : '—'),
    array('Roof Type',          $analysis ? ($analysis['roof_type'] ? $analysis['roof_type'] : 'Asphalt Shingle') : 'Asphalt Shingle'),
);
foreach ($meas_rows as $r) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(70, 6, $r[0] . ':', 1, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, $r[1], 1, 1);
}
$pdf->Ln(5);

// ---- Damage Report ----
if (!empty($damage_items)) {
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetTextColor(255, 107, 53);
    $pdf->Cell(0, 8, 'DAMAGE ANALYSIS', 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(75, 6, 'Damage Type', 1, 0, 'C');
    $pdf->Cell(30, 6, 'Severity',    1, 0, 'C');
    $pdf->Cell(25, 6, 'Confidence',  1, 0, 'C');
    $pdf->Cell(30, 6, 'Priority',    1, 0, 'C');
    $pdf->Cell(0, 6, 'Est. Cost',   1, 1, 'C');
    foreach ($damage_items as $d) {
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(75, 5, $d['damage_label'] ? $d['damage_label'] : $d['damage_type'], 1, 0);
        $pdf->Cell(30, 5, $d['severity'], 1, 0, 'C');
        $pdf->Cell(25, 5, ($d['confidence'] ? round($d['confidence']*100) . '%' : '—'), 1, 0, 'C');
        $pdf->Cell(30, 5, $d['repair_priority'] . '/10', 1, 0, 'C');
        $pdf->Cell(0, 5, ($d['estimated_cost'] ? '$' . number_format($d['estimated_cost'],2) : '—'), 1, 1, 'C');
    }
    $pdf->Ln(5);
}

// ---- Material Takeoff ----
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(0, 230, 118);
$pdf->Cell(0, 8, 'MATERIAL TAKEOFF — BILL OF MATERIALS', 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(70, 5, 'Material', 1, 0, 'C');
$pdf->Cell(40, 5, 'Manufacturer', 1, 0, 'C');
$pdf->Cell(15, 5, 'Qty', 1, 0, 'C');
$pdf->Cell(12, 5, 'Unit', 1, 0, 'C');
$pdf->Cell(22, 5, 'Unit Cost', 1, 0, 'C');
$pdf->Cell(0, 5, 'Total', 1, 1, 'C');

$grand_total = 0;
foreach ($takeoff as $ti) {
    $name  = isset($ti['material_name']) ? $ti['material_name'] : (isset($ti['name']) ? $ti['name'] : '');
    $mfr   = isset($ti['manufacturer']) ? $ti['manufacturer'] : '';
    $qty   = floatval(isset($ti['quantity']) ? $ti['quantity'] : (isset($ti['qty']) ? $ti['qty'] : 0));
    $unit  = isset($ti['unit']) ? $ti['unit'] : '';
    $uc    = floatval(isset($ti['unit_cost']) ? $ti['unit_cost'] : 0);
    $total = floatval(isset($ti['total_cost']) ? $ti['total_cost'] : ($qty * $uc));
    $grand_total += $total;
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(70, 5, $name, 1, 0);
    $pdf->Cell(40, 5, $mfr, 1, 0);
    $pdf->Cell(15, 5, number_format($qty, 2), 1, 0, 'R');
    $pdf->Cell(12, 5, $unit, 1, 0, 'C');
    $pdf->Cell(22, 5, '$' . number_format($uc, 2), 1, 0, 'R');
    $pdf->Cell(0, 5, '$' . number_format($total, 2), 1, 1, 'R');
}
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(159, 6, 'TOTAL ESTIMATED MATERIAL COST', 1, 0, 'R');
$pdf->Cell(0, 6, '$' . number_format($grand_total, 2), 1, 1, 'R');
$pdf->Ln(5);

// ---- Solar ----
$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(255, 214, 0);
$pdf->Cell(0, 8, 'SOLAR FEASIBILITY ANALYSIS', 0, 1);
$pdf->SetTextColor(0, 0, 0);
$sol_rows = array(
    array('Recommended Panels',    $analysis ? number_format(intval($analysis['solar_panels'])) : '—'),
    array('Estimated kWh/Year',    $analysis ? number_format(floatval($analysis['solar_kwh_year'])) : '—'),
    array('Annual Savings Estimate','$' . number_format(floatval($analysis ? $analysis['solar_savings_year'] : 0))),
    array('25-Year Savings',       '$' . number_format(floatval($analysis ? $analysis['solar_savings_year'] : 0) * 25)),
);
foreach ($sol_rows as $r) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(70, 6, $r[0] . ':', 1, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, $r[1], 1, 1);
}
$pdf->Ln(5);

// ---- Weather & Storm Hazards ----
if ($weather) {
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetTextColor(0, 212, 255);
    $pdf->Cell(0, 8, 'HISTORICAL WEATHER & STORM HAZARDS', 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    
    $wind = isset($weather['max_wind_mph']) ? $weather['max_wind_mph'] : 0;
    $risk = 'Low Risk';
    if ($wind > 70) $risk = 'Critical Risk';
    elseif ($wind > 55) $risk = 'Elevated Risk';
    
    $weather_rows = array(
        array('12-Month Peak Wind Gust',   $wind . ' mph'),
        array('Annual Solar Radiation',    number_format($weather['annual_solar_kwh_m2'] ?? 0) . ' kWh/m²'),
        array('Wind Damage Risk Level',    $risk),
        array('Weather Data Source',       'Open-Meteo Archive API'),
    );
    foreach ($weather_rows as $r) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(70, 6, $r[0] . ':', 1, 0);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, $r[1], 1, 1);
    }
    $pdf->Ln(5);
}

// ---- Vendor Summary ----
if (!empty($vendors)) {
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetTextColor(167, 139, 250);
    $pdf->Cell(0, 8, 'RECOMMENDED VENDORS', 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    foreach ($vendors as $v) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 6, $v['name'] . ($v['is_preferred'] ? ' ★ Preferred' : ''), 0, 1);
        $pdf->SetFont('helvetica', '', 8);
        if ($v['phone'])  { $pdf->Cell(0, 5, 'Phone: ' . $v['phone'], 0, 1); }
        if ($v['email'])  { $pdf->Cell(0, 5, 'Email: ' . $v['email'], 0, 1); }
        $pdf->Ln(2);
    }
}

// ---- Notes ----
if ($notes) {
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(0, 212, 255);
    $pdf->Cell(0, 7, 'ESTIMATOR NOTES', 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 5, $notes, 1);
}

// ---- Output ----
$filename = 'RoofIQ_Report_' . preg_replace('/[^A-Za-z0-9]/', '_', substr($addr, 0, 40)) . '_' . date('Ymd') . '.pdf';
$pdf->Output($filename, 'D');

// ---- HTML Fallback (no TCPDF) ----
function output_html_report($property, $analysis, $damage_items, $takeoff, $vendors, $company, $appName, $notes, $report_type) {
    $addr  = $property['formatted_address'] ? $property['formatted_address'] : $property['address'];
    $score = $analysis ? $analysis['condition_score'] : null;
    $total = 0;
    foreach ($takeoff as $t) {
        $total += floatval(isset($t['total_cost']) ? $t['total_cost'] : (floatval(isset($t['qty']) ? $t['qty'] : 0) * floatval(isset($t['unit_cost']) ? $t['unit_cost'] : 0)));
    }

    echo '<!DOCTYPE html><html><head>
    <meta charset="UTF-8">
    <title>RoofIQ Report — ' . htmlspecialchars($addr) . '</title>
    <style>
      body{font-family:Arial,sans-serif;margin:30px;color:#1a202c;font-size:11pt;}
      h1{color:#0066cc;border-bottom:2px solid #0066cc;padding-bottom:6px;}
      h2{color:#0044aa;border-bottom:1px solid #ccd;padding-bottom:4px;margin-top:24px;}
      table{width:100%;border-collapse:collapse;margin:10px 0;}
      th{background:#0066cc;color:#fff;padding:6px 10px;text-align:left;font-size:9pt;}
      td{padding:5px 10px;border-bottom:1px solid #e2e8f0;}
      tr:nth-child(even) td{background:#f7fafc;}
      .score{font-size:48pt;font-weight:700;color:#0066cc;text-align:center;}
      .total{background:#e8f5e9;font-weight:700;}
      @media print{button{display:none;}}
    </style>
    </head><body>
    <h1>SHEKHAR ROOFIQ AI ENTERPRISE — ' . htmlspecialchars($report_type) . '</h1>
    <p><strong>Company:</strong> ' . htmlspecialchars($company) . ' &nbsp;|&nbsp; <strong>Date:</strong> ' . date('m/d/Y g:ia') . '</p>
    <h2>Property Information</h2>
    <table><tr><th>Field</th><th>Value</th></tr>
    <tr><td>Address</td><td>' . htmlspecialchars($addr) . '</td></tr>
    <tr><td>Type</td><td>' . htmlspecialchars($property['property_type'] ? $property['property_type'] : 'Residential') . '</td></tr>
    </table>';

    if ($analysis) {
        echo '<h2>Roof Condition</h2><div class="score">' . ($score ? $score : '—') . '/100 &nbsp; ' . htmlspecialchars($analysis['condition_label'] ? $analysis['condition_label'] : '') . '</div>';
        echo '<h2>Measurements</h2><table><tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Roof Area</td><td>' . number_format(floatval($analysis['roof_area_sqft']),1) . ' sq ft</td></tr>
        <tr><td>Roof Squares</td><td>' . number_format(floatval($analysis['roof_squares']),2) . '</td></tr>
        <tr><td>Pitch</td><td>' . htmlspecialchars($analysis['roof_pitch_ratio'] ? $analysis['roof_pitch_ratio'] : '—') . '</td></tr>
        <tr><td>Ridge</td><td>' . number_format(floatval($analysis['ridge_length_ft']),1) . ' ft</td></tr>
        </table>';
    }

    if (!empty($damage_items)) {
        echo '<h2>Damage Report</h2><table><tr><th>Type</th><th>Severity</th><th>Priority</th></tr>';
        foreach ($damage_items as $d) {
            echo '<tr><td>' . htmlspecialchars($d['damage_label'] ? $d['damage_label'] : $d['damage_type']) . '</td><td>' . htmlspecialchars($d['severity']) . '</td><td>' . htmlspecialchars($d['repair_priority']) . '/10</td></tr>';
        }
        echo '</table>';
    }

    echo '<h2>Material Takeoff</h2><table><tr><th>Material</th><th>Qty</th><th>Unit</th><th>Unit Cost</th><th>Total</th></tr>';
    $runTotal = 0;
    foreach ($takeoff as $ti) {
        $qty = floatval(isset($ti['quantity']) ? $ti['quantity'] : (isset($ti['qty']) ? $ti['qty'] : 0));
        $uc  = floatval(isset($ti['unit_cost']) ? $ti['unit_cost'] : 0);
        $tc  = floatval(isset($ti['total_cost']) ? $ti['total_cost'] : ($qty * $uc));
        $runTotal += $tc;
        $mname = isset($ti['material_name']) ? $ti['material_name'] : (isset($ti['name']) ? $ti['name'] : '');
        echo '<tr><td>' . htmlspecialchars($mname) . '</td><td>' . number_format($qty,2) . '</td><td>' . htmlspecialchars($ti['unit'] ? $ti['unit'] : '') . '</td><td>$' . number_format($uc,2) . '</td><td>$' . number_format($tc,2) . '</td></tr>';
    }
    echo '<tr class="total"><td colspan="4"><strong>TOTAL</strong></td><td><strong>$' . number_format($runTotal,2) . '</strong></td></tr></table>';

    if ($notes) {
        echo '<h2>Estimator Notes</h2><p>' . nl2br(htmlspecialchars($notes)) . '</p>';
    }

    echo '<p style="margin-top:30px;color:#666;font-size:9pt;text-align:center;">
    INSTALL TCPDF in /vendor/tcpdf/ for full professional PDF output.<br>
    Report generated by ' . htmlspecialchars($appName) . ' &copy; ' . date('Y') . ' ' . htmlspecialchars($company) . '
    </p>
    <button onclick="window.print()">🖨️ Print this Report</button>
    </body></html>';
}
