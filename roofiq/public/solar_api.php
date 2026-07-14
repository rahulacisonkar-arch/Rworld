<?php
/**
 * ROOFIQ AI — Solar API Proxy
 * POST: { lat: float, lng: float }
 * Returns solar potential + building insights or a calculated estimate
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__) . '/src/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST required']);
    exit;
}

$body    = file_get_contents('php://input');
$payload = json_decode($body, true);
$lat     = floatval($payload['lat'] ?? 0);
$lng     = floatval($payload['lng'] ?? 0);

if ($lat == 0 || $lng == 0) {
    echo json_encode(['error' => 'Valid lat/lng required']);
    exit;
}

// --- Helper: generate estimated roof data from lat/lng ---
function generate_roof_estimate($lat, $lng) {
    // Deterministic "AI" estimate based on coordinates
    $seed = abs(sin($lat * 1000) * cos($lng * 1000)) * 10000;
    $roofArea    = round(1200 + fmod($seed, 1800));   // 1200–3000 sq ft
    $pitch       = round(15 + fmod($seed * 1.3, 25)); // 15–40 degrees
    $ridgeLength = round($roofArea / 24);
    $panelCount  = round($roofArea * 0.7 / 17.5);
    $solarKwh    = round($panelCount * 400 * 1.2);    // ~400W panels, 1.2 efficiency factor
    $annualSavings = round($solarKwh * 0.13);          // ~$0.13/kWh avg US
    $conditionScore = round(65 + fmod($seed * 0.7, 30)); // 65–95

    return [
        'source'          => 'estimated',
        'roof_area_sqft'  => $roofArea,
        'roof_pitch_deg'  => $pitch,
        'ridge_length_ft' => $ridgeLength,
        'eave_length_ft'  => round($ridgeLength * 1.4),
        'total_panels'    => $panelCount,
        'solar_kwh_year'  => $solarKwh,
        'savings_usd_year'=> $annualSavings,
        'condition_score' => $conditionScore,
        'condition_label' => $conditionScore >= 85 ? 'Excellent' : ($conditionScore >= 75 ? 'Good' : ($conditionScore >= 65 ? 'Fair' : 'Needs Attention')),
        'sections' => [
            ['name' => 'Front Slope',  'area' => round($roofArea * 0.35), 'condition' => round($conditionScore + fmod($seed * 0.2, 10) - 5)],
            ['name' => 'Rear Slope',   'area' => round($roofArea * 0.35), 'condition' => round($conditionScore + fmod($seed * 0.3, 10) - 5)],
            ['name' => 'Left Gable',   'area' => round($roofArea * 0.15), 'condition' => round($conditionScore + fmod($seed * 0.4, 10) - 5)],
            ['name' => 'Right Gable',  'area' => round($roofArea * 0.15), 'condition' => round($conditionScore + fmod($seed * 0.5, 10) - 5)],
        ],
        'monthly_solar' => array_map(function($m) use ($solarKwh) {
            $factors = [0.7, 0.75, 0.85, 0.95, 1.1, 1.2, 1.2, 1.15, 1.0, 0.85, 0.72, 0.65];
            return round($solarKwh / 12 * $factors[$m]);
        }, range(0, 11)),
    ];
}

$apiKey = roofiq_solar_key();

if (empty($apiKey)) {
    echo json_encode(generate_roof_estimate($lat, $lng));
    exit;
}

// Call Google Solar API
$url = 'https://solar.googleapis.com/v1/buildingInsights:findClosest?'
     . 'location.latitude=' . $lat
     . '&location.longitude=' . $lng
     . '&requiredQuality=LOW'
     . '&key=' . urlencode($apiKey);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    // Fallback to estimate
    $est = generate_roof_estimate($lat, $lng);
    $est['api_fallback'] = true;
    echo json_encode($est);
    exit;
}

$data = json_decode($response, true);

// Parse real Solar API response
$solarPotential = $data['solarPotential'] ?? [];
$roofArea   = round(($solarPotential['wholeRoofStats']['areaMeters2'] ?? 150) * 10.7639); // m2 to sqft
$maxPanels  = $solarPotential['maxArrayPanelsCount'] ?? round($roofArea * 0.04);
$maxKwh     = $solarPotential['maxArrayAnnualEnergyKwh'] ?? round($maxPanels * 480);
$panelCapW  = $solarPotential['panelCapacityWatts'] ?? 400;

// Build monthly from panels (if available)
$segments = $solarPotential['solarPanels'] ?? [];
$monthlySolar = array_fill(0, 12, round($maxKwh / 12));

// Find roof pitch from segment tilt
$avgTilt = 25;
if (!empty($solarPotential['roofSegmentStats'])) {
    $tilts = array_column($solarPotential['roofSegmentStats'], 'pitchDegrees');
    if (!empty($tilts)) {
        $avgTilt = round(array_sum($tilts) / count($tilts));
    }
}

$condScore = 80; // Default when using real API

$result = [
    'source'           => 'google_solar_api',
    'roof_area_sqft'   => $roofArea,
    'roof_pitch_deg'   => $avgTilt,
    'ridge_length_ft'  => round(sqrt($roofArea) * 1.2),
    'eave_length_ft'   => round(sqrt($roofArea) * 1.8),
    'total_panels'     => $maxPanels,
    'solar_kwh_year'   => round($maxKwh),
    'savings_usd_year' => round($maxKwh * 0.13),
    'condition_score'  => $condScore,
    'condition_label'  => 'Good',
    'sections'         => [],
    'monthly_solar'    => $monthlySolar,
    'raw_api'          => [
        'max_panels'   => $maxPanels,
        'panel_watts'  => $panelCapW,
        'center'       => $data['center'] ?? null,
    ],
];

if (!empty($solarPotential['roofSegmentStats'])) {
    foreach ($solarPotential['roofSegmentStats'] as $i => $seg) {
        $result['sections'][] = [
            'name'      => 'Roof Section ' . ($i + 1),
            'area'      => round(($seg['stats']['areaMeters2'] ?? 20) * 10.7639),
            'condition' => $condScore,
        ];
    }
}

echo json_encode($result);
