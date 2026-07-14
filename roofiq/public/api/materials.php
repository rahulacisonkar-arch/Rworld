<?php
/**
 * ROOFIQ — Materials Takeoff API
 * PHP 7.0.1 Compatible
 */
require_once dirname(dirname(__DIR__)) . '/src/config.php';
require_once dirname(dirname(__DIR__)) . '/src/db.php';
require_once dirname(dirname(__DIR__)) . '/src/functions.php';

session_start_safe();
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
    exit;
}

$action      = isset($_GET['action']) ? trim($_GET['action']) : '';
$property_id = intval(isset($_GET['property_id']) ? $_GET['property_id'] : 0);
$roof_type   = isset($_GET['roof_type']) ? trim($_GET['roof_type']) : 'Residential';

if ($action === 'takeoff' && $property_id) {
    // 1. Fetch analysis
    $analysis = db_fetch("SELECT * FROM roof_analysis WHERE property_id=? ORDER BY id DESC LIMIT 1", array($property_id));
    if (!$analysis) {
        // Mock analysis stats to generate mockup takeoff if not analyzed yet
        $squares = 25.0;
        $perimeter = 220;
        $complexity = 'Moderate';
    } else {
        $squares = floatval($analysis['roof_squares'] ? $analysis['roof_squares'] : 25.0);
        $perimeter = floatval($analysis['perimeter_ft'] ? $analysis['perimeter_ft'] : 220.0);
        $complexity = $analysis['complexity'] ? $analysis['complexity'] : 'Moderate';
    }

    // 2. Load materials catalog
    $catalog = db_fetch_all("SELECT * FROM materials WHERE is_active=1");
    $mat_by_name = array();
    foreach ($catalog as $m) {
        $mat_by_name[$m['name']] = $m;
    }

    $takeoff = array();
    $total_cost = 0;

    if (strpos(strtolower($roof_type), 'epdm') !== false) {
        // EPDM Commercial Takeoff
        $items = array(
            'EPDM 60 mil Black Membrane' => array('qty' => $squares, 'waste' => 10),
            'Polyiso 2" Insulation (4x8)' => array('qty' => ($squares * 100) / 32, 'waste' => 5),
            '1/2" Cover Board (4x8)'     => array('qty' => ($squares * 100) / 32, 'waste' => 5),
            'EPDM Bonding Adhesive'      => array('qty' => ($squares * 100) / 60, 'waste' => 5),
            'EPDM Seam Tape 3"'          => array('qty' => $perimeter / 100, 'waste' => 5),
            'Roofing Fasteners 3" (Box/250)' => array('qty' => ($squares * 100) / 250 * 6, 'waste' => 0),
        );
    } elseif (strpos(strtolower($roof_type), 'commercial') !== false || strpos(strtolower($roof_type), 'tpo') !== false) {
        // TPO Commercial Takeoff
        $items = array(
            'TPO 60 mil White Membrane' => array('qty' => $squares, 'waste' => 10),
            'Polyiso 2" Insulation (4x8)' => array('qty' => ($squares * 100) / 32, 'waste' => 5),
            '1/2" Cover Board (4x8)'     => array('qty' => ($squares * 100) / 32, 'waste' => 5),
            'TPO Seam Tape 2"'          => array('qty' => $perimeter / 100, 'waste' => 5),
            'Edge Metal Drip Edge'      => array('qty' => $perimeter, 'waste' => 5),
            'Roofing Fasteners 3" (Box/250)' => array('qty' => ($squares * 100) / 250 * 6, 'waste' => 0),
            'Walk Pad 3x5'              => array('qty' => 5, 'waste' => 0)
        );
    } else {
        // Residential Asphalt Shingles Takeoff
        $items = array(
            'Architectural Shingles 30yr' => array('qty' => $squares, 'waste' => 15),
            'Starter Strip Shingles'      => array('qty' => $perimeter / 105, 'waste' => 5),
            'Synthetic Underlayment 10sq'  => array('qty' => $squares / 10, 'waste' => 10),
            'Ice & Water Shield 2sq'      => array('qty' => 2, 'waste' => 5),
            'Ridge Cap Shingles'          => array('qty' => $perimeter * 0.15 / 33, 'waste' => 5),
            'Aluminum Drip Edge 1.5" 10ft'=> array('qty' => $perimeter / 10, 'waste' => 10),
            'Ridge Vent 10ft'             => array('qty' => $perimeter * 0.1 / 10, 'waste' => 0),
            'Roofing Nails 1.75" (5lb)'   => array('qty' => ceil($squares / 3), 'waste' => 0)
        );
    }

    $html = '<table class="table table-hover table-striped mb-0" style="color:#e2e8f0;font-size:0.85rem;">
                <thead style="background:rgba(0,0,0,0.3);color:#94a3b8;">
                    <tr>
                        <th>Material Item</th>
                        <th>Category</th>
                        <th class="text-right">Base Qty</th>
                        <th>Unit</th>
                        <th class="text-right">Waste</th>
                        <th class="text-right">Total Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total Cost</th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($items as $name => $spec) {
        $cat_item = isset($mat_by_name[$name]) ? $mat_by_name[$name] : null;
        if ($cat_item) {
            $base_qty = $spec['qty'];
            $waste_pct = $spec['waste'];
            $total_qty = $base_qty * (1 + $waste_pct / 100);
            
            // Round nicely based on unit
            if ($cat_item['unit'] === 'EA' || $cat_item['unit'] === 'BOX' || $cat_item['unit'] === 'ROLL' || $cat_item['unit'] === 'BDL' || $cat_item['unit'] === 'PK') {
                $total_qty = ceil($total_qty);
            } else {
                $total_qty = round($total_qty, 2);
            }
            
            $cost = $total_qty * floatval($cat_item['unit_cost']);
            $total_cost += $cost;

            $html .= '<tr>
                        <td style="font-weight:600;">' . e($cat_item['name']) . '<br><small class="text-muted">' . e($cat_item['manufacturer']) . '</small></td>
                        <td><span class="badge badge-secondary">' . e($cat_item['category']) . '</span></td>
                        <td class="text-right">' . number_format($base_qty, 2) . '</td>
                        <td>' . e($cat_item['unit']) . '</td>
                        <td class="text-right">' . $waste_pct . '%</td>
                        <td class="text-right" style="color:#00d4ff;font-weight:700;">' . number_format($total_qty, $cat_item['unit']==='EA'?0:2) . '</td>
                        <td class="text-right">$' . number_format(floatval($cat_item['unit_cost']), 2) . '</td>
                        <td class="text-right" style="font-weight:700;">$' . number_format($cost, 2) . '</td>
                      </tr>';
        }
    }

    $html .= '</tbody>
              <tfoot style="background:rgba(0,0,0,0.3);font-size:0.95rem;font-weight:700;">
                <tr>
                    <td colspan="7" class="text-right" style="color:#94a3b8;">Est. Material Subtotal:</td>
                    <td class="text-right" style="color:#00e676;font-size:1.1rem;">$' . number_format($total_cost, 2) . '</td>
                </tr>
              </tfoot>
            </table>';

    // Store in DB if analysis exists
    if ($analysis) {
        db_update('roof_analysis', array(
            'roof_material'  => $roof_type,
            'complexity'     => $complexity
        ), 'id=?', array($analysis['id']));
    }

    echo json_encode(array('success' => true, 'html' => $html, 'total' => $total_cost));
    exit;
}

echo json_encode(array('success' => false, 'error' => 'Invalid parameters'));
