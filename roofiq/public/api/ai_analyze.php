<?php
/**
 * ROOFIQ AI API — AI Analysis Proxy
 * Calls Python FastAPI AI service and saves results to DB
 * PHP 7.0.1 Compatible — cURL only
 */
require_once dirname(dirname(__DIR__)) . '/src/config.php';
require_once dirname(dirname(__DIR__)) . '/src/db.php';
require_once dirname(dirname(__DIR__)) . '/src/functions.php';
session_start_safe();
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'error' => 'POST required'));
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!$body) {
    echo json_encode(array('success' => false, 'error' => 'Invalid JSON'));
    exit;
}

$prop_id = intval(isset($body['property_id']) ? $body['property_id'] : 0);
$lat     = floatval(isset($body['lat'])         ? $body['lat']         : 0);
$lng     = floatval(isset($body['lng'])         ? $body['lng']         : 0);
$address = isset($body['address'])              ? $body['address']     : '';

if (!$lat || !$lng) {
    echo json_encode(array('success' => false, 'error' => 'Missing lat or lng coordinates'));
    exit;
}

if (!$prop_id) {
    // Generate a temporary address/name for map clicks
    if (empty($address)) {
        $address = "Map Coordinate " . round($lat, 5) . ", " . round($lng, 5);
    }
    // Attempt to reverse geocode or fetch coordinates
    $geo = geocode_address($address);
    $formatted_address = $geo ? $geo['formatted_address'] : $address;
    
    // Insert new property row
    $prop_id = db_insert('properties', array(
        'address'           => $address,
        'formatted_address' => $formatted_address,
        'latitude'          => $lat,
        'longitude'         => $lng,
        'created_by'        => current_user()['id']
    ));
}

// Check property
$property = db_fetch("SELECT * FROM properties WHERE id=?", array($prop_id));
if (!$property) {
    echo json_encode(array('success' => false, 'error' => 'Property registry record could not be resolved'));
    exit;
}

// ---- Try Python AI service ----
$ai_url    = roofiq_ai_service_url() . '/analyze';
$ai_result = null;
$processing_ms = 0;
$t_start   = microtime(true);

$ai_payload = array(
    'lat'       => $lat,
    'lng'       => $lng,
    'address'   => $address,
    'footprint' => isset($body['footprint']) ? $body['footprint'] : null,
    'pitch_deg' => isset($body['pitch_deg']) ? floatval($body['pitch_deg']) : 22.0,
);

$ai_resp = roofiq_curl_post($ai_url, $ai_payload, 30);

if ($ai_resp && $ai_resp['code'] === 200) {
    $ai_result     = json_decode($ai_resp['body'], true);
    $processing_ms = intval((microtime(true) - $t_start) * 1000);
}

if ($ai_result) {
    $ai_result['census'] = roofiq_get_census_data($lat, $lng);
    $ai_result['weather'] = roofiq_get_historical_weather($lat, $lng);
}

// ---- If AI service not available, use PHP fallback ----
if (!$ai_result) {
    // Get footprint for area estimate
    $fp = db_fetch("SELECT * FROM building_footprints WHERE property_id=? ORDER BY id DESC LIMIT 1", array($prop_id));
    $area = ($fp && $fp['base_area_sqft']) ? floatval($fp['base_area_sqft']) : 0;
    $roof_data     = estimate_roof_data($lat, $lng, $area);
    $processing_ms = intval((microtime(true) - $t_start) * 1000);

    $ai_result = array(
        'success'       => true,
        'condition'     => array(
            'score'         => $roof_data['condition_score'],
            'label'         => $roof_data['condition_label'],
            'color'         => $roof_data['condition_color'],
            'damage_count'  => 0,
        ),
        'measurements'  => array(
            'roof_area_sqft'  => $roof_data['roof_area_sqft'],
            'roof_squares'    => $roof_data['roof_squares'],
            'roof_pitch_deg'  => $roof_data['roof_pitch_deg'],
            'roof_pitch_ratio'=> $roof_data['roof_pitch_ratio'],
            'ridge_length_ft' => $roof_data['ridge_length_ft'],
            'eave_length_ft'  => $roof_data['eave_length_ft'],
            'perimeter_ft'    => $roof_data['perimeter_ft'],
        ),
        'detections'    => array(),
        'solar_estimate'=> array(
            'panel_count'     => $roof_data['solar_panels'],
            'kwh_per_year'    => $roof_data['solar_kwh_year'],
            'savings_per_year'=> $roof_data['solar_savings_year'],
        ),
        'roof_type'     => 'Asphalt Shingle',
        'roof_type_confidence' => 72,
        'source'        => 'php_fallback',
        'census'        => $roof_data['census'],
        'weather'       => $roof_data['weather'],
    );
}

// ---- Save/update analysis ----
$meas = isset($ai_result['measurements']) ? $ai_result['measurements'] : array();
$cond = isset($ai_result['condition'])    ? $ai_result['condition']    : array();
$sol  = isset($ai_result['solar_estimate']) ? $ai_result['solar_estimate'] : array();

$analysis_data = array(
    'property_id'     => $prop_id,
    'analyzed_by'     => current_user()['id'],
    'roof_area_sqft'  => isset($meas['roof_area_sqft'])   ? $meas['roof_area_sqft']   : null,
    'roof_squares'    => isset($meas['roof_squares'])     ? $meas['roof_squares']     : null,
    'roof_pitch_deg'  => isset($meas['roof_pitch_deg'])   ? $meas['roof_pitch_deg']   : null,
    'roof_pitch_ratio'=> isset($meas['roof_pitch_ratio']) ? $meas['roof_pitch_ratio'] : null,
    'ridge_length_ft' => isset($meas['ridge_length_ft'])  ? $meas['ridge_length_ft']  : null,
    'eave_length_ft'  => isset($meas['eave_length_ft'])   ? $meas['eave_length_ft']   : null,
    'perimeter_ft'    => isset($meas['perimeter_ft'])     ? $meas['perimeter_ft']     : null,
    'facets_count'    => isset($meas['facets_count'])     ? $meas['facets_count']     : 4,
    'complexity'      => 'Moderate',
    'roof_type'       => isset($meas['roof_type']) ? $meas['roof_type'] : (isset($ai_result['roof_type']) ? $ai_result['roof_type'] : 'Asphalt Shingle'),
    'roof_type_confidence' => isset($ai_result['roof_type_confidence']) ? $ai_result['roof_type_confidence'] : null,
    'condition_score' => isset($cond['score'])            ? $cond['score']            : null,
    'condition_label' => isset($cond['label'])            ? $cond['label']            : null,
    'solar_panels'    => isset($sol['panel_count'])       ? $sol['panel_count']       : null,
    'solar_kwh_year'  => isset($sol['kwh_per_year'])      ? $sol['kwh_per_year']      : null,
    'solar_savings_year' => isset($sol['savings_per_year']) ? $sol['savings_per_year'] : null,
    'ai_raw_json'     => json_encode($ai_result),
    'ai_processing_ms'=> $processing_ms,
    'status'          => 'Complete',
);

$analysis_id = db_insert('roof_analysis', $analysis_data);

// ---- Save detections ----
$detections = isset($ai_result['detections']) ? $ai_result['detections'] : array();
if ($analysis_id && !empty($detections)) {
    foreach ($detections as $det) {
        $bp = isset($det['bbox_pct']) ? $det['bbox_pct'] : array();
        db_insert('damage_reports', array(
            'analysis_id'   => $analysis_id,
            'property_id'   => $prop_id,
            'damage_type'   => isset($det['class'])    ? $det['class']    : 'unknown',
            'damage_label'  => isset($det['label'])    ? $det['label']    : '',
            'severity'      => isset($det['severity']) ? ucfirst($det['severity']) : 'Medium',
            'confidence'    => isset($det['confidence']) ? $det['confidence'] : null,
            'bbox_x1'       => isset($bp['x1'])        ? $bp['x1']        : null,
            'bbox_y1'       => isset($bp['y1'])        ? $bp['y1']        : null,
            'bbox_x2'       => isset($bp['x2'])        ? $bp['x2']        : null,
            'bbox_y2'       => isset($bp['y2'])        ? $bp['y2']        : null,
            'repair_priority'=> 5,
        ));
    }
}

// ---- Save material takeoff ----
if ($analysis_id) {
    $roof_area = isset($meas['roof_area_sqft']) ? floatval($meas['roof_area_sqft']) : 1850;
    $roof_data_local = array(
        'roof_area_sqft'  => $roof_area,
        'roof_squares'    => ceil($roof_area / 100),
        'perimeter_ft'    => isset($meas['perimeter_ft'])    ? floatval($meas['perimeter_ft'])    : 160,
        'ridge_length_ft' => isset($meas['ridge_length_ft']) ? floatval($meas['ridge_length_ft']) : 40,
    );
    $takeoff_items = generate_material_takeoff($roof_data_local, 'Residential');
    $material_total = 0;
    foreach ($takeoff_items as $ti) {
        $total = floatval($ti['qty']) * floatval($ti['unit_cost']);
        $material_total += $total;
        db_insert('material_takeoffs', array(
            'analysis_id'   => $analysis_id,
            'property_id'   => $prop_id,
            'material_name' => $ti['name'],
            'manufacturer'  => $ti['manufacturer'],
            'category'      => $ti['category'],
            'quantity'       => $ti['qty'],
            'unit'          => $ti['unit'],
            'unit_cost'     => $ti['unit_cost'],
            'total_cost'    => round($total, 2),
        ));
    }
}

log_activity('AI analysis completed for property #' . $prop_id, 'analysis', $prop_id);

// Build damage_counts
$damage_counts = array('critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0);
foreach ($detections as $det) {
    $sev = strtolower(isset($det['severity']) ? $det['severity'] : 'low');
    if (isset($damage_counts[$sev])) {
        $damage_counts[$sev]++;
    }
}

echo json_encode(array(
    'success'          => true,
    'analysis_id'      => $analysis_id,
    'condition_score'  => isset($cond['score']) ? $cond['score'] : 0,
    'condition_label'  => isset($cond['label']) ? $cond['label'] : '',
    'condition_color'  => isset($cond['color']) ? $cond['color'] : '#00e676',
    'roof_type'        => isset($meas['roof_type']) ? $meas['roof_type'] : (isset($ai_result['roof_type']) ? $ai_result['roof_type'] : 'Asphalt Shingle'),
    'roof_type_confidence' => isset($ai_result['roof_type_confidence']) ? $ai_result['roof_type_confidence'] : null,
    'detections'       => $detections,
    'damage_counts'    => $damage_counts,
    'material_total'   => isset($material_total) ? round($material_total, 2) : 0,
    'processing_ms'    => $processing_ms,
    'source'           => isset($ai_result['source']) ? $ai_result['source'] : 'ai_service',
    'property_id'      => $prop_id,
    'roof_area_sqft'   => isset($meas['roof_area_sqft'])  ? $meas['roof_area_sqft']  : null,
    'roof_squares'     => isset($meas['roof_squares'])    ? $meas['roof_squares']    : null,
    'roof_pitch_deg'   => isset($meas['roof_pitch_deg'])  ? $meas['roof_pitch_deg']  : null,
    'ridge_length_ft'  => isset($meas['ridge_length_ft']) ? $meas['ridge_length_ft'] : null,
    'perimeter_ft'     => isset($meas['perimeter_ft'])    ? $meas['perimeter_ft']    : null,
    'solar_panels'     => isset($sol['panel_count'])      ? $sol['panel_count']      : null,
    'solar_kwh_year'   => isset($sol['kwh_per_year'])     ? $sol['kwh_per_year']     : null,
    'solar_savings_year' => isset($sol['savings_per_year']) ? $sol['savings_per_year'] : null,
    'sections'         => isset($ai_result['sections'])   ? $ai_result['sections']   : null,
));
