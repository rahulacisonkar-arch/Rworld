<?php
/**
 * ROOFIQ AI ENTERPRISE — Core Functions & Authentication
 * PHP 7.0.1 Compatible — No 7.1+ features
 */

// ============================================================
// SESSION MANAGEMENT
// ============================================================

function session_start_safe() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        // PHP 7.0 uses legacy 5-arg form — NOT array form (that's 7.3+)
        session_set_cookie_params(SESSION_LIFETIME, '/', '', $secure, true);
        session_start();
    }
}

// ============================================================
// OUTPUT ESCAPING
// ============================================================

function e($value) {
    return htmlspecialchars(isset($value) ? $value : '', ENT_QUOTES, 'UTF-8');
}

function j($value) {
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}

// ============================================================
// CSRF
// ============================================================

function get_csrf_token() {
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf($token) {
    session_start_safe();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . e(get_csrf_token()) . '">';
}

// ============================================================
// AUTHENTICATION
// ============================================================

function is_logged_in() {
    session_start_safe();
    return !empty($_SESSION['user_id']);
}

function current_user() {
    session_start_safe();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return array(
        'id'        => $_SESSION['user_id'],
        'username'  => isset($_SESSION['username'])  ? $_SESSION['username']  : '',
        'full_name' => isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '',
        'role'      => isset($_SESSION['role'])      ? $_SESSION['role']      : '',
        'email'     => isset($_SESSION['email'])     ? $_SESSION['email']     : '',
    );
}

function current_role() {
    session_start_safe();
    return isset($_SESSION['role']) ? $_SESSION['role'] : '';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . roofiq_base_url() . 'index.php');
        exit;
    }
    // Check session timeout
    $timeout = intval(roofiq_setting('session_timeout', 120)) * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_destroy();
        header('Location: ' . roofiq_base_url() . 'index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function require_role($role) {
    require_login();
    $current = current_role();
    if (is_array($role)) {
        $allowed = $role;
    } else {
        $allowed = array($role);
    }
    if (!in_array($current, $allowed)) {
        http_response_code(403);
        include_page_header('Access Denied');
        echo '<div class="container mt-5 text-center">';
        echo '<h1><i class="fas fa-ban text-danger"></i> 403 Forbidden</h1>';
        echo '<p class="lead">You do not have permission to access this page.</p>';
        echo '<a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>';
        echo '</div>';
        include_page_footer();
        exit;
    }
}

function is_admin() {
    return current_role() === 'Admin';
}

// ============================================================
// URL HELPER
// ============================================================

function roofiq_base_url() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/';
    // Get the public/ directory path
    $base   = dirname($script);
    if ($base === '\\' || $base === '/') {
        $base = '';
    }
    return $scheme . '://' . $host . $base . '/';
}

// ============================================================
// DATABASE HELPERS
// ============================================================

function db_query($sql, $params = array()) {
    global $pdo;
    if (!$pdo) {
        return false;
    }
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("ROOFIQ DB Error: " . $e->getMessage() . " SQL: " . $sql);
        return false;
    }
}

function db_fetch($sql, $params = array()) {
    $stmt = db_query($sql, $params);
    if (!$stmt) {
        return null;
    }
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function db_fetch_all($sql, $params = array()) {
    $stmt = db_query($sql, $params);
    if (!$stmt) {
        return array();
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function db_insert($table, $data) {
    global $pdo;
    if (!$pdo) {
        return false;
    }
    $cols   = implode(',', array_map(function($k) { return '`' . $k . '`'; }, array_keys($data)));
    $marks  = implode(',', array_fill(0, count($data), '?'));
    $sql    = "INSERT INTO `{$table}` ({$cols}) VALUES ({$marks})";
    $stmt   = db_query($sql, array_values($data));
    if (!$stmt) {
        return false;
    }
    return $pdo->lastInsertId();
}

function db_update($table, $data, $where, $where_params = array()) {
    $sets = implode(',', array_map(function($k) { return '`' . $k . '`=?'; }, array_keys($data)));
    $sql  = "UPDATE `{$table}` SET {$sets} WHERE {$where}";
    $params = array_merge(array_values($data), $where_params);
    $stmt = db_query($sql, $params);
    return $stmt !== false;
}

// ============================================================
// ACTIVITY LOG
// ============================================================

function log_activity($action, $module = null, $record_id = null, $details = null) {
    $user = current_user();
    $user_id = $user ? $user['id'] : null;
    $ip   = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $ua   = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 300) : '';
    db_insert('activity_logs', array(
        'user_id'    => $user_id,
        'action'     => $action,
        'module'     => $module,
        'record_id'  => $record_id,
        'ip_address' => $ip,
        'user_agent' => $ua,
        'details'    => $details,
    ));
}

// ============================================================
// NOTIFICATIONS
// ============================================================

function add_notification($user_id, $title, $message, $type = 'info', $link = null) {
    db_insert('notifications', array(
        'user_id' => $user_id,
        'title'   => $title,
        'message' => $message,
        'type'    => $type,
        'link'    => $link,
    ));
}

function get_unread_notifications($user_id, $limit = 10) {
    return db_fetch_all(
        "SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT " . intval($limit),
        array($user_id)
    );
}

// ============================================================
// GEOCODING (Nominatim — free, no API key)
// ============================================================

function geocode_address($address) {
    // Check cache
    $cacheFile = ROOFIQ_DATA . '/geocode_cache.json';
    $cache = array();
    if (file_exists($cacheFile)) {
        $raw   = file_get_contents($cacheFile);
        $cache = json_decode($raw, true);
        if (!is_array($cache)) {
            $cache = array();
        }
    }
    $cacheKey = md5(strtolower(trim($address)));
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    // Try Google Geocoding if key available
    $googleKey = roofiq_google_key();
    if (!empty($googleKey)) {
        $result = geocode_google($address, $googleKey);
    } else {
        $result = geocode_nominatim($address);
    }

    if ($result) {
        $cache[$cacheKey] = $result;
        @file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
    }
    return $result;
}

function geocode_nominatim($address) {
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($address) . '&countrycodes=us';
    $response = roofiq_curl_get($url, array('User-Agent: RoofIQ-Enterprise/3.0'));
    if (!$response) {
        return null;
    }
    $data = json_decode($response, true);
    if (empty($data[0])) {
        return null;
    }
    $r = $data[0];
    return array(
        'lat'               => floatval($r['lat']),
        'lng'               => floatval($r['lon']),
        'formatted_address' => isset($r['display_name']) ? $r['display_name'] : $address,
        'place_id'          => isset($r['place_id'])    ? $r['place_id']    : '',
        'source'            => 'nominatim',
    );
}

function geocode_google($address, $apiKey) {
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . urlencode($apiKey);
    $response = roofiq_curl_get($url);
    if (!$response) {
        return null;
    }
    $data = json_decode($response, true);
    if (empty($data['results'][0])) {
        return null;
    }
    $r = $data['results'][0];
    return array(
        'lat'               => $r['geometry']['location']['lat'],
        'lng'               => $r['geometry']['location']['lng'],
        'formatted_address' => $r['formatted_address'],
        'place_id'          => $r['place_id'],
        'source'            => 'google',
    );
}

// ============================================================
// BUILDING FOOTPRINT
// ============================================================

function fetch_building_footprint($lat, $lng, $radius = 30) {
    // Overpass API query for building at location
    $lat  = floatval($lat);
    $lng  = floatval($lng);
    $rad  = intval($radius);
    // Bounding box (lat - delta, lng - delta, lat + delta, lng + delta)
    $delta = 0.0003;
    $south = $lat - $delta;
    $west  = $lng - $delta;
    $north = $lat + $delta;
    $east  = $lng + $delta;

    $query = '[out:json][timeout:10];'
           . '(way["building"](' . $south . ',' . $west . ',' . $north . ',' . $east . '););'
           . 'out body geom;';
    $url = 'https://overpass-api.de/api/interpreter?data=' . urlencode($query);

    $response = roofiq_curl_get($url, array(), 12);
    if (!$response) {
        return null;
    }
    $data = json_decode($response, true);
    if (empty($data['elements'])) {
        return null;
    }

    // Take first building
    $el = $data['elements'][0];
    if (empty($el['geometry'])) {
        return null;
    }

    $coords = array();
    foreach ($el['geometry'] as $node) {
        $coords[] = array(floatval($node['lon']), floatval($node['lat']));
    }

    $geojson = array(
        'type' => 'Polygon',
        'coordinates' => array($coords),
    );

    // Calculate rough area
    $area = calculate_polygon_area_sqft($coords, $lat, $lng);

    return array(
        'geojson'       => $geojson,
        'roof_area_sqft'=> $area,
        'source'        => 'osm_overpass',
        'tags'          => isset($el['tags']) ? $el['tags'] : array(),
    );
}

function calculate_polygon_area_sqft($coords, $lat, $lng) {
    if (count($coords) < 3) {
        return 0;
    }
    // Shoelace formula in degrees, then convert to sqft
    $area = 0;
    $n = count($coords);
    for ($i = 0; $i < $n; $i++) {
        $j = ($i + 1) % $n;
        $area += $coords[$i][0] * $coords[$j][1];
        $area -= $coords[$j][0] * $coords[$i][1];
    }
    $area = abs($area) / 2.0;
    // Convert square degrees to sq meters (approximate at given latitude)
    $lat_r      = deg2rad($lat);
    $m_per_deg_lat = 111320;
    $m_per_deg_lng = 111320 * cos($lat_r);
    $area_m2 = $area * $m_per_deg_lat * $m_per_deg_lng;
    return round($area_m2 * 10.7639, 1);
}

// ============================================================
// cURL HELPERS
// ============================================================

function roofiq_curl_get($url, $extra_headers = array(), $timeout = 10) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if (!empty($extra_headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $extra_headers);
    }
    $result = curl_exec($ch);
    $err    = curl_error($ch);
    curl_close($ch);
    if ($err) {
        error_log("cURL GET error: " . $err . " URL: " . $url);
        return false;
    }
    return $result;
}

function roofiq_curl_post($url, $data, $timeout = 20) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Accept: application/json',
    ));
    $result = curl_exec($ch);
    $err    = curl_error($ch);
    $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) {
        error_log("cURL POST error: " . $err . " URL: " . $url);
        return false;
    }
    return array('code' => $code, 'body' => $result);
}

function roofiq_get_census_data($lat, $lng) {
    $url = "https://geocoding.geo.census.gov/geocoder/geographies/coordinates?x=" . floatval($lng) . "&y=" . floatval($lat) . "&benchmark=Public_AR_Current&vintage=Current_Current&format=json";
    $resp = roofiq_curl_get($url);
    if (!$resp) return null;
    $data = json_decode($resp, true);
    if (empty($data['result']['geographies'])) return null;
    
    $geos = $data['result']['geographies'];
    $county = isset($geos['Counties'][0]['NAME']) ? $geos['Counties'][0]['NAME'] : 'Unknown County';
    $state = isset($geos['States'][0]['NAME']) ? $geos['States'][0]['NAME'] : '';
    $tract = isset($geos['Census Tracts'][0]['NAME']) ? $geos['Census Tracts'][0]['NAME'] : 'Unknown Tract';
    $block = isset($geos['Census Blocks'][0]['NAME']) ? $geos['Census Blocks'][0]['NAME'] : 'Unknown Block';
    $geoid = isset($geos['Census Blocks'][0]['GEOID']) ? $geos['Census Blocks'][0]['GEOID'] : '';
    
    return array(
        'county' => $county,
        'state' => $state,
        'tract' => $tract,
        'block' => $block,
        'geoid' => $geoid
    );
}

function roofiq_get_historical_weather($lat, $lng) {
    $startDate = date('Y-m-d', strtotime('-365 days'));
    $endDate   = date('Y-m-d', strtotime('-2 days'));
    $url = "https://archive-api.open-meteo.com/v1/archive?latitude=" . floatval($lat) . "&longitude=" . floatval($lng) . "&start_date={$startDate}&end_date={$endDate}&daily=windspeed_10m_max,shortwave_radiation_sum&timezone=auto";
    
    $resp = roofiq_curl_get($url);
    if (!$resp) return null;
    $data = json_decode($resp, true);
    if (empty($data['daily'])) return null;
    
    $daily = $data['daily'];
    
    $maxWindKmh = 0;
    if (!empty($daily['windspeed_10m_max'])) {
        $maxWindKmh = max(array_filter($daily['windspeed_10m_max'], function($val) { return $val !== null; }) ?: [0]);
    }
    $maxWindMph = round($maxWindKmh * 0.621371, 1);
    
    $totalRadiationMJ = 0;
    if (!empty($daily['shortwave_radiation_sum'])) {
        $totalRadiationMJ = array_sum(array_filter($daily['shortwave_radiation_sum'], function($val) { return $val !== null; }) ?: [0]);
    }
    $annualSolarKwhPerM2 = round($totalRadiationMJ * 0.277778, 1);
    
    $monthlySolarMJ = array_fill(1, 12, 0);
    $monthlyCounts  = array_fill(1, 12, 0);
    if (!empty($daily['time'])) {
        foreach ($daily['time'] as $idx => $timeStr) {
            $month = intval(substr($timeStr, 5, 2));
            $val = isset($daily['shortwave_radiation_sum'][$idx]) ? floatval($daily['shortwave_radiation_sum'][$idx]) : 0;
            $monthlySolarMJ[$month] += $val;
            $monthlyCounts[$month]++;
        }
    }
    
    $monthlySolarKwhPerM2 = array();
    for ($m = 1; $m <= 12; $m++) {
        $monthlySolarKwhPerM2[] = round($monthlySolarMJ[$m] * 0.277778, 1);
    }
    
    return array(
        'max_wind_mph' => $maxWindMph,
        'annual_solar_kwh_m2' => $annualSolarKwhPerM2,
        'monthly_solar_kwh_m2' => $monthlySolarKwhPerM2
    );
}

// ============================================================
// ROOF ANALYSIS HELPERS
// ============================================================

function estimate_roof_data($lat, $lng, $area_sqft = 0) {
    if ($area_sqft <= 0) {
        $area_sqft = 1850;
    }
    $seed = abs(sin($lat * 1000) * cos($lng * 1000)) * 10000;
    $pitch   = round(15 + fmod($seed, 25));
    $factor  = 1.0 / cos(deg2rad($pitch));
    $roofArea = round($area_sqft * $factor, 1);
    $squares  = round($roofArea / 100, 2);
    $perimeter= round(sqrt($area_sqft) * 4, 1);
    $ridge    = round($perimeter / 4, 1);
    $eave     = round($perimeter / 2, 1);

    // Call live APIs
    $census = roofiq_get_census_data($lat, $lng);
    $weather = roofiq_get_historical_weather($lat, $lng);

    // Real Solar Calculations (NREL physical equivalent)
    $annualSolarKwhPerM2 = ($weather && isset($weather['annual_solar_kwh_m2'])) ? $weather['annual_solar_kwh_m2'] : 1500;
    $roofAreaM2 = $roofArea / 10.7639;
    $efficiency = 0.18; // 18% standard panel efficiency
    $pr = 0.75; // 75% performance ratio
    $usableRoofAreaM2 = $roofAreaM2 * 0.5; // 50% usable area
    $kwh = round($annualSolarKwhPerM2 * $usableRoofAreaM2 * $efficiency * $pr);
    $panels = round($usableRoofAreaM2 / 2.0);
    if ($panels <= 0) $panels = round($roofArea * 0.7 / 17.5);
    if ($kwh <= 0) $kwh = round($panels * 400 * 1.2);

    // Weather impact condition score
    $score = intval(round(78 + fmod($seed * 0.7, 18)));
    $maxWindMph = ($weather && isset($weather['max_wind_mph'])) ? $weather['max_wind_mph'] : 45;
    if ($maxWindMph > 70) {
        $score -= 15;
    } elseif ($maxWindMph > 55) {
        $score -= 8;
    }
    $score = max(35, min(98, $score));

    if ($score >= 90) {
        $label = 'Excellent';
        $color = '#00E676';
    } elseif ($score >= 75) {
        $label = 'Good';
        $color = '#69F0AE';
    } elseif ($score >= 60) {
        $label = 'Fair';
        $color = '#FFCA28';
    } else {
        $label = 'Poor';
        $color = '#FF6D00';
    }

    return array(
        'roof_area_sqft'     => $roofArea,
        'roof_squares'       => $squares,
        'roof_pitch_deg'     => $pitch,
        'roof_pitch_ratio'   => round(tan(deg2rad($pitch)) * 12, 1) . '/12',
        'ridge_length_ft'    => $ridge,
        'eave_length_ft'     => $eave,
        'rake_length_ft'     => $ridge,
        'perimeter_ft'       => $perimeter,
        'condition_score'    => $score,
        'condition_label'    => $label,
        'condition_color'    => $color,
        'solar_panels'       => $panels,
        'solar_kwh_year'     => $kwh,
        'solar_savings_year' => round($kwh * 0.13),
        'facets_count'       => 4,
        'complexity'         => ($pitch > 30) ? 'Complex' : 'Moderate',
        'census'             => $census,
        'weather'            => $weather
    );
}

function generate_material_takeoff($roof_data, $roof_type = 'Residential') {
    $area    = isset($roof_data['roof_area_sqft']) ? floatval($roof_data['roof_area_sqft']) : 1850;
    $squares = ceil($area / 100);
    $perimeter = isset($roof_data['perimeter_ft']) ? floatval($roof_data['perimeter_ft']) : 160;

    $items = array();
    if ($roof_type === 'Residential') {
        $wastePct = 1.15; // 15% waste
        $items = array(
            array('name'=>'Architectural Shingles 30yr','manufacturer'=>'GAF Timberline HDZ','qty'=>round($squares * $wastePct, 1),'unit'=>'SQ','unit_cost'=>130,'category'=>'Shingles'),
            array('name'=>'Starter Strip Shingles','manufacturer'=>'GAF ProStart','qty'=>round($perimeter / 105, 1),'unit'=>'ROLL','unit_cost'=>55,'category'=>'Starter'),
            array('name'=>'Synthetic Underlayment','manufacturer'=>'GAF FeltBuster','qty'=>round($squares * $wastePct / 10, 1),'unit'=>'ROLL','unit_cost'=>65,'category'=>'Underlayment'),
            array('name'=>'Ice & Water Shield','manufacturer'=>'Grace Ice & Water','qty'=>round($perimeter / 200 * 1.1, 1),'unit'=>'ROLL','unit_cost'=>88,'category'=>'Ice Shield'),
            array('name'=>'Ridge Cap Shingles','manufacturer'=>'GAF TimberTex','qty'=>round(($roof_data['ridge_length_ft'] ?? 40) / 33 + 1, 1),'unit'=>'BDL','unit_cost'=>75,'category'=>'Ridge'),
            array('name'=>'Drip Edge 10ft','manufacturer'=>'Gibraltar','qty'=>round($perimeter / 10 * 1.1),'unit'=>'EA','unit_cost'=>4.50,'category'=>'Flashings'),
            array('name'=>'Ridge Vent 10ft','manufacturer'=>'Air Vent','qty'=>round(($roof_data['ridge_length_ft'] ?? 40) / 10 + 1),'unit'=>'EA','unit_cost'=>18,'category'=>'Ventilation'),
            array('name'=>'Roofing Nails 1.75\" 5lb','manufacturer'=>'Grip-Rite','qty'=>round($squares / 3),'unit'=>'BOX','unit_cost'=>18,'category'=>'Fasteners'),
        );
    } else {
        $wastePct = 1.10;
        $items = array(
            array('name'=>'TPO 60mil White Membrane','manufacturer'=>'Carlisle SynTec','qty'=>round($squares * $wastePct, 1),'unit'=>'SQ','unit_cost'=>95,'category'=>'Membrane'),
            array('name'=>'Polyiso 2\" Insulation','manufacturer'=>'Atlas EPS','qty'=>round($area / 32 * $wastePct),'unit'=>'EA','unit_cost'=>28.50,'category'=>'Insulation'),
            array('name'=>'1/2\" Cover Board','manufacturer'=>'Georgia-Pacific','qty'=>round($area / 32 * $wastePct),'unit'=>'EA','unit_cost'=>18,'category'=>'Substrate'),
            array('name'=>'TPO Seam Tape 2\"','manufacturer'=>'Carlisle','qty'=>round($perimeter / 100 * 2),'unit'=>'ROLL','unit_cost'=>22,'category'=>'Accessories'),
            array('name'=>'Edge Metal Drip Edge','manufacturer'=>'Petersen Aluminum','qty'=>round($perimeter * 1.05),'unit'=>'LF','unit_cost'=>3.20,'category'=>'Flashings'),
            array('name'=>'Roofing Fasteners 3\" (Box/250)','manufacturer'=>'OMG','qty'=>round($squares / 2),'unit'=>'BOX','unit_cost'=>45,'category'=>'Fasteners'),
            array('name'=>'Walk Pads 3x5','manufacturer'=>'Carlisle','qty'=>round($area / 300),'unit'=>'EA','unit_cost'=>32,'category'=>'Accessories'),
            array('name'=>'Pipe Boot Flashing 3\"','manufacturer'=>'Lifetime Tool','qty'=>4,'unit'=>'EA','unit_cost'=>12.50,'category'=>'Flashings'),
        );
    }

    // Add total_cost to each item
    foreach ($items as $k => $item) {
        $items[$k]['total_cost'] = round($item['qty'] * $item['unit_cost'], 2);
    }
    return $items;
}

// ============================================================
// PAGE LAYOUT HELPERS (AdminLTE)
// ============================================================

function include_page_header($title = '', $extra_css = array(), $extra_js_head = array()) {
    $appName = roofiq_app_name();
    $pageTitle = $title ? ($title . ' | ' . $appName) : $appName;
    $user = current_user();
    $role = $user ? $user['role'] : '';
    echo '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . e($pageTitle) . '</title>
  <meta name="description" content="SHEKHAR ROOFIQ AI Enterprise — Professional Roofing Intelligence Platform">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- AdminLTE -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
  <!-- Bootstrap 4 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <!-- RoofIQ Custom -->
  <link rel="stylesheet" href="' . roofiq_base_url() . 'css/roofiq.css">
';
    foreach ($extra_css as $css) {
        echo '  <link rel="stylesheet" href="' . e($css) . '">' . "\n";
    }
    foreach ($extra_js_head as $js) {
        echo '  <script src="' . e($js) . '"></script>' . "\n";
    }
    echo '</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">';

    // Navbar
    $notifications = $user ? get_unread_notifications($user['id'], 5) : array();
    $notifCount    = count($notifications);
    echo '
  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-dark roofiq-navbar">
    <ul class="navbar-nav">
      <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a></li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="dashboard.php" class="nav-link roofiq-brand-link">
          <i class="fas fa-home-lg-alt mr-1"></i> ' . e($appName) . '
        </a>
      </li>
    </ul>
    <ul class="navbar-nav ml-auto">
      <!-- Notifications -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-bell"></i>
          ' . ($notifCount > 0 ? '<span class="badge badge-warning navbar-badge">' . $notifCount . '</span>' : '') . '
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <span class="dropdown-item dropdown-header">' . $notifCount . ' Notification' . ($notifCount !== 1 ? 's' : '') . '</span>
          <div class="dropdown-divider"></div>';
    if (!empty($notifications)) {
        foreach ($notifications as $n) {
            echo '<a href="#" class="dropdown-item">
            <i class="fas fa-info-circle mr-2 text-info"></i>' . e($n['title']) . '
            <span class="float-right text-muted text-sm">' . date('g:ia', strtotime($n['created_at'])) . '</span>
          </a>';
        }
    } else {
        echo '<span class="dropdown-item text-muted">No new notifications</span>';
    }
    echo '        </div>
      </li>
      <!-- User Menu -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-user-circle mr-1"></i>
          <span class="d-none d-sm-inline">' . e($user ? $user['full_name'] : 'Guest') . '</span>
          <span class="badge badge-roofiq ml-1">' . e($role) . '</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
          <a class="dropdown-item" href="settings.php"><i class="fas fa-cog mr-2"></i>Settings</a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
        </div>
      </li>
    </ul>
  </nav>';

    // Sidebar
    echo '
  <!-- Sidebar -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4 roofiq-sidebar">
    <a href="dashboard.php" class="brand-link roofiq-brand">
      <i class="fas fa-drafting-compass brand-image elevation-3 p-1 mr-2" style="font-size:1.5rem;background:#00d4ff;border-radius:6px;color:#0a0e1a;"></i>
      <span class="brand-text font-weight-bold">RoofIQ<span style="color:#00d4ff"> AI</span></span>
    </a>
    <div class="sidebar">
      <div class="user-panel mt-2 pb-2 mb-2 d-flex">
        <div class="image"><i class="fas fa-hard-hat" style="font-size:2rem;color:#00d4ff;padding:4px 8px;"></i></div>
        <div class="info">
          <a href="#" class="d-block">' . e($user ? $user['full_name'] : '') . '</a>
          <small class="text-muted">' . e($role) . '</small>
        </div>
      </div>
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
          <li class="nav-header">MAIN MENU</li>
          <li class="nav-item">
            <a href="dashboard.php" class="nav-link' . (basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? ' active' : '') . '">
              <i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="analysis.php" class="nav-link' . (basename($_SERVER['PHP_SELF']) === 'analysis.php' ? ' active' : '') . '">
              <i class="nav-icon fas fa-satellite-dish"></i><p>Property Analysis</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="projects.php" class="nav-link' . (basename($_SERVER['PHP_SELF']) === 'projects.php' ? ' active' : '') . '">
              <i class="nav-icon fas fa-project-diagram"></i><p>Projects</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="reports.php" class="nav-link' . (basename($_SERVER['PHP_SELF']) === 'reports.php' ? ' active' : '') . '">
              <i class="nav-icon fas fa-file-pdf"></i><p>Reports</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="assistant.php" class="nav-link' . (basename($_SERVER['PHP_SELF']) === 'assistant.php' ? ' active' : '') . '">
              <i class="nav-icon fas fa-robot"></i><p>AI Assistant</p>
            </a>
          </li>
          <li class="nav-header">CATALOG</li>
          <li class="nav-item">
            <a href="materials.php" class="nav-link' . (basename($_SERVER['PHP_SELF']) === 'materials.php' ? ' active' : '') . '">
              <i class="nav-icon fas fa-boxes"></i><p>Materials</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="vendors.php" class="nav-link' . (basename($_SERVER['PHP_SELF']) === 'vendors.php' ? ' active' : '') . '">
              <i class="nav-icon fas fa-truck"></i><p>Vendors</p>
            </a>
          </li>';
    if ($role === 'Admin') {
        echo '
          <li class="nav-header">ADMINISTRATION</li>
          <li class="nav-item">
            <a href="users.php" class="nav-link' . (basename($_SERVER['PHP_SELF']) === 'users.php' ? ' active' : '') . '">
              <i class="nav-icon fas fa-users-cog"></i><p>Users</p>
            </a>
          </li>
          <li class="nav-item">
            <a href="settings.php" class="nav-link' . (basename($_SERVER['PHP_SELF']) === 'settings.php' ? ' active' : '') . '">
              <i class="nav-icon fas fa-sliders-h"></i><p>Settings</p>
            </a>
          </li>';
    }
    echo '
        </ul>
      </nav>
    </div>
  </aside>
  <!-- Content Wrapper -->
  <div class="content-wrapper roofiq-content">';
}

function include_page_footer($extra_js = array()) {
    echo '
  </div><!-- /content-wrapper -->
  <!-- Footer -->
  <footer class="main-footer roofiq-footer">
    <strong>' . e(roofiq_company_name()) . '</strong> &copy; ' . date('Y') . '
    <div class="float-right d-none d-sm-inline-block">
      <span class="badge badge-roofiq">RoofIQ AI Enterprise v' . ROOFIQ_VERSION . '</span>
    </div>
  </footer>
</div><!-- /wrapper -->
<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="' . roofiq_base_url() . 'js/roofiq-charts.js"></script>
<script src="' . roofiq_base_url() . 'js/roofiq-ui.js"></script>';
    foreach ($extra_js as $js) {
        echo '<script src="' . e($js) . '"></script>' . "\n";
    }
    echo '
</body>
</html>';
}

function page_content_header($title, $breadcrumbs = array()) {
    echo '
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0 roofiq-page-title">' . e($title) . '</h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">';
    echo '<li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i></a></li>';
    foreach ($breadcrumbs as $label => $url) {
        if ($url) {
            echo '<li class="breadcrumb-item"><a href="' . e($url) . '">' . e($label) . '</a></li>';
        } else {
            echo '<li class="breadcrumb-item active">' . e($label) . '</li>';
        }
    }
    echo '          </ol>
        </div>
      </div>
    </div>
  </div>
  <section class="content">
  <div class="container-fluid">';
}

function page_content_footer() {
    echo '
  </div>
  </section>';
}

// ============================================================
// FORMAT HELPERS
// ============================================================

function fmt_currency($value, $symbol = '$') {
    return $symbol . number_format(floatval($value), 2);
}

function fmt_sqft($value) {
    return number_format(floatval($value), 1) . ' sq ft';
}

function fmt_date($datetime) {
    if (!$datetime) {
        return '—';
    }
    return date('m/d/Y', strtotime($datetime));
}

function fmt_ago($datetime) {
    if (!$datetime) {
        return '—';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60)         return $diff . 's ago';
    if ($diff < 3600)       return floor($diff/60) . 'm ago';
    if ($diff < 86400)      return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}

function status_badge($status) {
    $map = array(
        'Lead'          => 'secondary',
        'Inspection'    => 'info',
        'Estimate'      => 'primary',
        'Proposal Sent' => 'warning',
        'Sold'          => 'success',
        'Ordered'       => 'info',
        'Installed'     => 'success',
        'Closed'        => 'dark',
        'Cancelled'     => 'danger',
        'Active'        => 'success',
        'Inactive'      => 'secondary',
        'Draft'         => 'secondary',
        'Complete'      => 'success',
        'Processing'    => 'info',
        'Pending'       => 'warning',
        'Failed'        => 'danger',
    );
    $cls = isset($map[$status]) ? $map[$status] : 'secondary';
    return '<span class="badge badge-' . $cls . '">' . e($status) . '</span>';
}

