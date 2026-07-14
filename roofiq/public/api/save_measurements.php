<?php
/**
 * ROOFIQ — Save Measurement Overrides API
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

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$analysis_id = intval(isset($body['analysis_id']) ? $body['analysis_id'] : 0);
$area        = floatval(isset($body['roof_area_sqft']) ? $body['roof_area_sqft'] : 0);
$pitch       = floatval(isset($body['roof_pitch_deg']) ? $body['roof_pitch_deg'] : 0);
$perimeter   = floatval(isset($body['perimeter_ft'])   ? $body['perimeter_ft']   : 0);
$ridge       = floatval(isset($body['ridge_length_ft']) ? $body['ridge_length_ft'] : 0);

if (!$analysis_id) {
    echo json_encode(array('success' => false, 'error' => 'Missing analysis ID'));
    exit;
}

$squares = round($area / 100, 2);

$data = array(
    'roof_area_sqft' => $area,
    'roof_squares'   => $squares,
    'roof_pitch_deg' => $pitch,
    'perimeter_ft'   => $perimeter,
    'ridge_length_ft'=> $ridge,
);

$ok = db_update('roof_analysis', $data, 'id=?', array($analysis_id));

if ($ok) {
    // Get property_id
    $analysis = db_fetch("SELECT property_id FROM roof_analysis WHERE id=?", array($analysis_id));
    $prop_id = $analysis ? intval($analysis['property_id']) : 0;

    // Delete existing takeoff
    db_query("DELETE FROM material_takeoffs WHERE analysis_id=?", array($analysis_id));
    
    // Regenerate
    $roof_data_local = array(
        'roof_area_sqft'  => $area,
        'roof_squares'    => ceil($area / 100),
        'perimeter_ft'    => $perimeter,
        'ridge_length_ft' => $ridge,
    );
    $takeoff_items = generate_material_takeoff($roof_data_local, 'Residential');
    $material_total = 0;
    foreach ($takeoff_items as $ti) {
        $ttl = floatval($ti['qty']) * floatval($ti['unit_cost']);
        $material_total += $ttl;
        db_insert('material_takeoffs', array(
            'analysis_id'   => $analysis_id,
            'property_id'   => $prop_id,
            'material_name' => $ti['name'],
            'manufacturer'  => $ti['manufacturer'],
            'category'      => $ti['category'],
            'quantity'      => $ti['qty'],
            'unit'          => $ti['unit'],
            'unit_cost'     => $ti['unit_cost'],
            'total_cost'    => round($ttl, 2),
        ));
    }
    
    echo json_encode(array(
        'success'        => true,
        'squares'        => $squares,
        'material_total' => $material_total
    ));
} else {
    echo json_encode(array('success' => false, 'error' => 'Database update failed'));
}
