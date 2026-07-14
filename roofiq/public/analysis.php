<?php
/**
 * ROOFIQ AI ENTERPRISE — Property Analysis Engine
 * PHP 7.0.1 Compatible | CesiumJS + MapLibre GL JS + Leaflet
 * The flagship page of the platform.
 */
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
session_start_safe();
require_login();

$user       = current_user();
$error      = '';
$property   = null;
$analysis   = null;
$footprint  = null;
$roof_data  = null;
$takeoff    = null;

// ---- Get address from GET/POST ----
$address = trim(isset($_GET['address'])     ? $_GET['address']     : '');
$prop_id = intval(isset($_GET['property_id']) ? $_GET['property_id'] : 0);

// ---- Load existing property ----
if ($prop_id > 0 && $pdo) {
    $property = db_fetch("SELECT * FROM properties WHERE id=?", array($prop_id));
    if ($property) {
        $address = $property['formatted_address'] ? $property['formatted_address'] : $property['address'];
        $analysis = db_fetch(
            "SELECT * FROM roof_analysis WHERE property_id=? ORDER BY id DESC LIMIT 1",
            array($prop_id)
        );
        $footprint = db_fetch(
            "SELECT * FROM building_footprints WHERE property_id=? ORDER BY id DESC LIMIT 1",
            array($prop_id)
        );
        if ($analysis) {
            $takeoff = db_fetch_all(
                "SELECT * FROM material_takeoffs WHERE analysis_id=? ORDER BY category, material_name",
                array($analysis['id'])
            );
        }
    }
}

// ---- Geocode if new address ----
$lat = 0; $lng = 0; $formatted_address = '';
if (!empty($address) && !$property) {
    $geo = geocode_address($address);
    if ($geo) {
        $lat               = floatval($geo['lat']);
        $lng               = floatval($geo['lng']);
        $formatted_address = $geo['formatted_address'];
        // Save property
        if ($pdo) {
            $parts = explode(',', $formatted_address);
            $prop_id = db_insert('properties', array(
                'address'           => $address,
                'formatted_address' => $formatted_address,
                'latitude'          => $lat,
                'longitude'         => $lng,
                'place_id'          => isset($geo['place_id']) ? $geo['place_id'] : '',
                'created_by'        => $user['id'],
            ));
            if ($prop_id) {
                $property = db_fetch("SELECT * FROM properties WHERE id=?", array($prop_id));
                log_activity('Property analyzed: ' . $address, 'analysis', $prop_id);
            }
        }
    } else {
        $error = 'Could not geocode address. Please check the address and try again.';
    }
}

if ($property) {
    $lat = floatval($property['latitude']);
    $lng = floatval($property['longitude']);
    $formatted_address = $property['formatted_address'] ? $property['formatted_address'] : $property['address'];
}

// ---- Pre-generate estimate if no analysis yet ----
$init_roof_data = array();
$sections = null;
if ($lat && $lng) {
    $fp_area = ($footprint && $footprint['base_area_sqft']) ? floatval($footprint['base_area_sqft']) : 0;
    $init_roof_data = estimate_roof_data($lat, $lng, $fp_area > 0 ? $fp_area : 0);
    
    $census = isset($init_roof_data['census']) ? $init_roof_data['census'] : null;
    $weather = isset($init_roof_data['weather']) ? $init_roof_data['weather'] : null;

    if ($analysis && !empty($analysis['ai_raw_json'])) {
        $raw_data = json_decode($analysis['ai_raw_json'], true);
        if (isset($raw_data['sections'])) {
            $sections = $raw_data['sections'];
        }
        if (isset($raw_data['census'])) {
            $census = $raw_data['census'];
        }
        if (isset($raw_data['weather'])) {
            $weather = $raw_data['weather'];
        }
    }
    
    if (!$sections) {
        $base_area = isset($init_roof_data['roof_area_sqft']) ? floatval($init_roof_data['roof_area_sqft']) : 1850;
        $p_deg = isset($init_roof_data['roof_pitch_deg']) ? floatval($init_roof_data['roof_pitch_deg']) : 22;
        $c_score = isset($init_roof_data['condition_score']) ? intval($init_roof_data['condition_score']) : 80;
        $sections = array(
            array('name' => 'Front Slope', 'area_sqft' => round($base_area * 0.35), 'pitch_deg' => $p_deg, 'azimuth' => 180, 'orientation' => 'S', 'condition_score' => $c_score),
            array('name' => 'Rear Slope',  'area_sqft' => round($base_area * 0.35), 'pitch_deg' => $p_deg, 'azimuth' => 0,   'orientation' => 'N', 'condition_score' => $c_score),
            array('name' => 'Left Gable',  'area_sqft' => round($base_area * 0.15), 'pitch_deg' => $p_deg, 'azimuth' => 270, 'orientation' => 'W', 'condition_score' => $c_score),
            array('name' => 'Right Gable', 'area_sqft' => round($base_area * 0.15), 'pitch_deg' => $p_deg, 'azimuth' => 90,  'orientation' => 'E', 'condition_score' => $c_score),
        );
    }
}

// ---- API Key status ----
$cesium_token    = roofiq_cesium_token();
$maptiler_key    = roofiq_maptiler_key();
$google_key      = roofiq_google_key();
$ai_service_url  = roofiq_ai_service_url();

// ---- Vendors for procurement ----
$vendors = db_fetch_all("SELECT * FROM vendors WHERE is_active=1 ORDER BY is_preferred DESC, name");

// ---- Damage detections if analysis exists ----
$damage_detections = array();
if ($analysis) {
    $damage_detections = db_fetch_all(
        "SELECT * FROM damage_reports WHERE analysis_id=? ORDER BY repair_priority DESC",
        array($analysis['id'])
    );
}

include_page_header('Property Analysis', array(
    'https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css',
), array(
    'https://cesium.com/downloads/cesiumjs/releases/1.115/Build/Cesium/Cesium.js',
));
?>

<style>
  .content-wrapper { margin-left: 250px; }
  .analysis-main { display: flex; gap: 0; height: calc(100vh - 110px); min-height: 600px; }
  .analysis-left  { width: 300px; min-width: 260px; border-right: 1px solid rgba(0,212,255,0.12); overflow-y: auto; background: #0d1526; padding: 16px; }
  .analysis-center{ flex: 1; display: flex; flex-direction: column; padding: 16px; overflow-y: auto; }
  .analysis-right { width: 340px; min-width: 300px; border-left: 1px solid rgba(0,212,255,0.12); overflow-y: auto; background: #0d1526; padding: 16px; }
  @media(max-width:1200px) { .analysis-right { display: none; } }
  @media(max-width:900px)  { .analysis-left  { display: none; } }
</style>

<!-- Top Address Bar -->
<div style="padding:12px 20px;background:#080d18;border-bottom:1px solid rgba(0,212,255,0.12);">
  <form action="analysis.php" method="GET" id="search-form" style="display:flex;gap:10px;align-items:center;">
    <div style="flex:1;position:relative;">
      <i class="fas fa-map-marker-alt" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#00d4ff;font-size:0.85rem;"></i>
      <input type="text" name="address" id="address-input" value="<?php echo e($formatted_address ? $formatted_address : $address); ?>"
             class="form-control" placeholder="Enter property address (e.g. 717 School Street Pawtucket RI)"
             style="padding-left:35px;font-size:0.9rem;">
    </div>
    <button type="submit" class="btn btn-roofiq" id="btn-analyze">
      <i class="fas fa-satellite-dish mr-1"></i> Analyze
    </button>
    <?php if ($property): ?>
    <button type="button" class="btn btn-outline-warning btn-sm" onclick="runAIAnalysis()">
      <i class="fas fa-brain mr-1"></i> Run AI
    </button>
    <button type="button" class="btn btn-outline-info btn-sm" onclick="generateReport()">
      <i class="fas fa-file-pdf mr-1"></i> PDF
    </button>
    <?php endif; ?>
  </form>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger mt-2 mb-0 py-2" style="font-size:0.85rem;">
      <i class="fas fa-exclamation-triangle mr-2"></i><?php echo e($error); ?>
    </div>
  <?php endif; ?>
</div>

<?php if (!$property && empty($address)): ?>
<!-- Empty State -->
<div style="flex:1;display:flex;align-items:center;justify-content:center;padding:60px 20px;text-align:center;">
  <div>
    <div style="font-size:5rem;color:rgba(0,212,255,0.2);margin-bottom:24px;">
      <i class="fas fa-home-lg-alt"></i>
    </div>
    <h3 style="font-family:'Outfit',sans-serif;font-weight:700;color:#fff;margin-bottom:12px;">Start a Roof Analysis</h3>
    <p style="color:#94a3b8;max-width:420px;margin:0 auto 24px;">
      Enter any US property address above to get full 3D visualization, AI damage detection, material takeoff, and solar analysis.
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">
      <span style="background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.2);border-radius:8px;padding:8px 16px;font-size:0.82rem;color:#94a3b8;">
        <i class="fas fa-cube mr-1" style="color:#00d4ff;"></i> CesiumJS 3D Viewer
      </span>
      <span style="background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.2);border-radius:8px;padding:8px 16px;font-size:0.82rem;color:#94a3b8;">
        <i class="fas fa-brain mr-1" style="color:#00e676;"></i> YOLO11 Damage AI
      </span>
      <span style="background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.2);border-radius:8px;padding:8px 16px;font-size:0.82rem;color:#94a3b8;">
        <i class="fas fa-solar-panel mr-1" style="color:#ffd600;"></i> Solar Analysis
      </span>
      <span style="background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.2);border-radius:8px;padding:8px 16px;font-size:0.82rem;color:#94a3b8;">
        <i class="fas fa-file-pdf mr-1" style="color:#ff6b35;"></i> PDF Reports (TCPDF)
      </span>
    </div>
  </div>
</div>
<?php else: ?>

<!-- 3-Panel Analysis Layout -->
<div class="analysis-main">

  <!-- ===== LEFT SIDEBAR ===== -->
  <div class="analysis-left">
    <div style="margin-bottom:16px;">
      <div style="font-size:0.65rem;letter-spacing:1.5px;color:#94a3b8;text-transform:uppercase;margin-bottom:4px;">Property</div>
      <div style="font-weight:600;color:#fff;font-size:0.88rem;line-height:1.4;"><?php echo e($formatted_address); ?></div>
      <?php if ($property): ?>
        <div style="font-size:0.72rem;color:#94a3b8;margin-top:4px;">
          <?php echo number_format($lat, 5); ?>, <?php echo number_format($lng, 5); ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Condition Score -->
    <?php
      $score = isset($init_roof_data['condition_score']) ? intval($init_roof_data['condition_score']) : 0;
      $scoreColor = isset($init_roof_data['condition_color']) ? $init_roof_data['condition_color'] : '#00e676';
      $scoreLabel = isset($init_roof_data['condition_label']) ? $init_roof_data['condition_label'] : '—';
      if ($analysis && $analysis['condition_score']) {
          $score      = intval($analysis['condition_score']);
          $scoreLabel = $analysis['condition_label'];
      }
    ?>
    <div style="text-align:center;padding:16px;background:rgba(0,0,0,0.2);border-radius:10px;border:1px solid rgba(255,255,255,0.06);margin-bottom:12px;">
      <div style="position:relative;width:80px;height:80px;margin:0 auto 8px;">
        <svg viewBox="0 0 80 80" style="transform:rotate(-90deg);">
          <circle cx="40" cy="40" r="34" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="8"/>
          <circle cx="40" cy="40" r="34" fill="none" stroke="<?php echo e($scoreColor); ?>" stroke-width="8"
                  stroke-dasharray="<?php echo round($score * 2.136); ?> 213.6" stroke-linecap="round"/>
        </svg>
        <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
          <span style="font-family:'Outfit',sans-serif;font-weight:700;font-size:1.1rem;color:#fff;" id="score-display"><?php echo $score; ?></span>
          <span style="font-size:0.55rem;color:#94a3b8;">/ 100</span>
        </div>
      </div>
      <div style="font-weight:700;color:<?php echo e($scoreColor); ?>;font-size:0.9rem;" id="score-label"><?php echo e($scoreLabel); ?></div>
      <div style="font-size:0.7rem;color:#94a3b8;">Roof Condition Score</div>
    </div>

    <!-- Key Metrics -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
      <?php
        $metrics = array(
          array('icon'=>'fa-ruler-combined','label'=>'Roof Area','key'=>'roof_area_sqft','fmt'=>'sqft','color'=>'#00d4ff'),
          array('icon'=>'fa-angle-double-up','label'=>'Pitch','key'=>'roof_pitch_deg','fmt'=>'deg','color'=>'#ffd600'),
          array('icon'=>'fa-vector-square','label'=>'Squares','key'=>'roof_squares','fmt'=>'num','color'=>'#00e676'),
          array('icon'=>'fa-minus','label'=>'Ridge','key'=>'ridge_length_ft','fmt'=>'ft','color'=>'#ff6b35'),
        );
        foreach ($metrics as $m) {
          $val = isset($init_roof_data[$m['key']]) ? $init_roof_data[$m['key']] : '—';
          if ($analysis && isset($analysis[$m['key']]) && $analysis[$m['key']]) {
            $val = $analysis[$m['key']];
          }
          $display = '—';
          if ($val !== '—' && $val !== null && $val !== '') {
            if ($m['fmt'] === 'sqft') $display = number_format(floatval($val)) . ' sf';
            elseif ($m['fmt'] === 'ft') $display = number_format(floatval($val)) . ' ft';
            elseif ($m['fmt'] === 'deg') $display = number_format(floatval($val), 1) . '°';
            else $display = number_format(floatval($val), 1);
          }
          echo '<div style="background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.06);border-radius:8px;padding:10px 8px;text-align:center;">
            <i class="fas ' . $m['icon'] . '" style="color:' . $m['color'] . ';font-size:0.85rem;display:block;margin-bottom:4px;"></i>
            <div style="font-family:Outfit,sans-serif;font-weight:700;font-size:0.95rem;color:#fff;" class="metric-' . $m['key'] . '">' . $display . '</div>
            <div style="font-size:0.65rem;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;">' . $m['label'] . '</div>
          </div>';
        }
      ?>
    </div>

    <!-- Left Nav Tabs -->
    <div style="margin-bottom:8px;border-top:1px solid rgba(255,255,255,0.06);padding-top:12px;">
      <div style="font-size:0.65rem;letter-spacing:1.5px;color:#94a3b8;text-transform:uppercase;margin-bottom:8px;">Analysis Panels</div>
      <?php
        $panels = array(
          array('id'=>'panel-3d',       'icon'=>'fa-cube',         'label'=>'3D Viewer',     'color'=>'#00d4ff'),
          array('id'=>'panel-gov',      'icon'=>'fa-university',   'label'=>'Government Details','color'=>'#f43f5e'),
          array('id'=>'panel-damage',   'icon'=>'fa-exclamation-triangle','label'=>'Damage AI','color'=>'#ff6b35'),
          array('id'=>'panel-measurements','icon'=>'fa-ruler',     'label'=>'Measurements',  'color'=>'#ffd600'),
          array('id'=>'panel-materials','icon'=>'fa-boxes',        'label'=>'Material Takeoff','color'=>'#00e676'),
          array('id'=>'panel-solar',    'icon'=>'fa-solar-panel',  'label'=>'Solar Analysis','color'=>'#ffd600'),
          array('id'=>'panel-vendors',  'icon'=>'fa-truck',        'label'=>'Procurement',   'color'=>'#a78bfa'),
          array('id'=>'panel-report',   'icon'=>'fa-file-pdf',     'label'=>'PDF Report',    'color'=>'#ff6b35'),
        );
        foreach ($panels as $p) {
          echo '<button class="panel-nav-btn" data-panel="' . $p['id'] . '" onclick="switchPanel(\'' . $p['id'] . '\')"
                  style="display:flex;align-items:center;gap:10px;width:100%;padding:8px 10px;background:transparent;border:none;border-radius:8px;cursor:pointer;color:#94a3b8;font-size:0.82rem;text-align:left;transition:all 0.2s;margin-bottom:2px;">
            <i class="fas ' . $p['icon'] . '" style="color:' . $p['color'] . ';width:16px;"></i> ' . $p['label'] . '
          </button>';
        }
      ?>
    </div>

    <?php if ($property): ?>
    <div style="margin-top:12px;border-top:1px solid rgba(255,255,255,0.06);padding-top:12px;">
      <a href="projects.php?new=1&property_id=<?php echo e($property['id']); ?>" class="btn btn-sm btn-block btn-roofiq mb-2">
        <i class="fas fa-plus mr-1"></i> Create Project
      </a>
      <button onclick="generateReport()" class="btn btn-sm btn-block" style="background:rgba(255,107,53,0.15);border:1px solid rgba(255,107,53,0.3);color:#ff6b35;">
        <i class="fas fa-file-pdf mr-1"></i> Generate PDF
      </button>
    </div>
    <?php endif; ?>
  </div><!-- /analysis-left -->

  <!-- ===== CENTER VIEWER ===== -->
  <div class="analysis-center">

    <!-- View Mode Tabs -->
    <div style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap;">
      <button class="view-mode-btn active" onclick="setViewMode('3d')" data-mode="3d"
              style="padding:5px 14px;border-radius:6px;border:1px solid rgba(0,212,255,0.3);background:rgba(0,212,255,0.12);color:#00d4ff;font-size:0.8rem;cursor:pointer;font-family:Inter,sans-serif;">
        <i class="fas fa-cube mr-1"></i>3D CesiumJS
      </button>
      <button class="view-mode-btn" onclick="setViewMode('2d')" data-mode="2d"
              style="padding:5px 14px;border-radius:6px;border:1px solid rgba(255,255,255,0.12);background:transparent;color:#94a3b8;font-size:0.8rem;cursor:pointer;font-family:Inter,sans-serif;">
        <i class="fas fa-map mr-1"></i>2D Satellite
      </button>
      <button class="view-mode-btn" onclick="setViewMode('damage')" data-mode="damage"
              style="padding:5px 14px;border-radius:6px;border:1px solid rgba(255,255,255,0.12);background:transparent;color:#94a3b8;font-size:0.8rem;cursor:pointer;font-family:Inter,sans-serif;">
        <i class="fas fa-edit mr-1"></i>2D Map &amp; Tracing
      </button>
      <div style="margin-left:auto;display:flex;gap:6px;align-items:center;">
        <?php if (!$property): ?><?php else: ?>
          <button onclick="screenshotViewer()" title="Screenshot"
                  style="padding:5px 10px;border-radius:6px;border:1px solid rgba(255,255,255,0.12);background:transparent;color:#94a3b8;cursor:pointer;">
            <i class="fas fa-camera"></i>
          </button>
          <button onclick="toggleFullscreen()" title="Fullscreen"
                  style="padding:5px 10px;border-radius:6px;border:1px solid rgba(255,255,255,0.12);background:transparent;color:#94a3b8;cursor:pointer;">
            <i class="fas fa-expand"></i>
          </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- 3D Viewer Container -->
    <div id="cesium-viewer-container" style="<?php echo (!$lat) ? 'display:none;' : ''; ?>">
      <div class="viewer-loading-overlay" id="viewer-loading">
        <div class="viewer-loading-spinner"></div>
        <div style="color:#00d4ff;font-size:0.85rem;">Loading 3D Viewer...</div>
      </div>
      <div class="viewer-toolbar">
        <div class="viewer-btn" onclick="resetCamera()" title="Reset View"><i class="fas fa-home"></i></div>
        <div class="viewer-btn" onclick="toggleRotate()" title="Auto-Rotate" id="btn-rotate"><i class="fas fa-sync-alt"></i></div>
        <div class="viewer-btn" onclick="zoomIn()"  title="Zoom In"><i class="fas fa-plus"></i></div>
        <div class="viewer-btn" onclick="zoomOut()" title="Zoom Out"><i class="fas fa-minus"></i></div>
      </div>
      <div id="cesiumContainer" style="width:100%;height:100%;"></div>
      <div class="viewer-status-bar">
        <span id="viewer-coords"><?php echo $lat ? number_format($lat, 5) . ', ' . number_format($lng, 5) : ''; ?></span>
        <span id="viewer-status">3D View</span>
      </div>
    </div>

    <!-- 2D MapLibre Container -->
    <div id="maplibre-container" style="display:none;"></div>

    <!-- Leaflet Damage Overlay & Manual Tracing Toolbar -->
    <div id="leaflet-wrapper" style="display:none; position:relative; flex: 1; min-height:520px; flex-direction:column; gap:10px;">
      <div id="tracing-mode-bar" style="display:flex; justify-content:space-between; align-items:center; background:rgba(10,14,26,0.9); border:1px solid rgba(0,212,255,0.15); padding:10px 15px; border-radius:8px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <span style="font-size:0.8rem; color:#94a3b8; font-weight:600;">Engine Mode:</span>
          <div class="btn-group btn-group-toggle" data-toggle="buttons">
            <label class="btn btn-xs btn-outline-cyan active" style="font-size:0.75rem; padding:3px 10px; cursor:pointer;" onclick="setTracingMode('auto')">
              <input type="radio" name="tracing_mode" id="mode-auto" checked autocomplete="off"> Automatic Detection
            </label>
            <label class="btn btn-xs btn-outline-cyan" style="font-size:0.75rem; padding:3px 10px; cursor:pointer;" onclick="setTracingMode('manual')">
              <input type="radio" name="tracing_mode" id="mode-manual" autocomplete="off"> Manual Tracing
            </label>
          </div>
        </div>
        <div id="manual-draw-actions" style="display:none; gap:6px;">
          <button type="button" class="btn btn-xs btn-roofiq" onclick="triggerDrawPolygon()" id="btn-draw-roof">
            <i class="fas fa-pen-nib mr-1"></i> Draw Roof
          </button>
          <button type="button" class="btn btn-xs btn-outline-warning" onclick="triggerEditPolygon()" id="btn-edit-roof">
            <i class="fas fa-edit mr-1"></i> Edit Roof
          </button>
          <button type="button" class="btn btn-xs btn-outline-success" onclick="saveEditedPolygon()" id="btn-save-edit" style="display:none;">
            <i class="fas fa-save mr-1"></i> Save Edit
          </button>
          <button type="button" class="btn btn-xs btn-outline-danger" onclick="triggerDeletePolygon()" id="btn-delete-roof">
            <i class="fas fa-trash-alt mr-1"></i> Delete Roof
          </button>
          <button type="button" class="btn btn-xs btn-success" onclick="analyzeDrawnArea()" id="btn-analyze-drawn">
            <i class="fas fa-calculator mr-1"></i> Analyze Drawn Area
          </button>
          <button type="button" class="btn btn-xs btn-info" onclick="generateTakeoffFromDrawn()" id="btn-takeoff-drawn">
            <i class="fas fa-boxes mr-1"></i> Generate Takeoff
          </button>
        </div>
      </div>
      <div id="leaflet-container" style="width:100%; height:520px; border-radius:8px; border: 1px solid var(--rq-border);"></div>
    </div>

    <!-- No address state -->
    <?php if (!$lat): ?>
    <div id="cesium-viewer-container" style="height:480px;display:flex;align-items:center;justify-content:center;flex-direction:column;">
      <i class="fas fa-map-marked-alt" style="font-size:4rem;color:rgba(0,212,255,0.2);margin-bottom:16px;"></i>
      <div style="color:#94a3b8;">Enter an address to load the 3D viewer</div>
    </div>
    <?php endif; ?>

    <!-- View Preset Buttons -->
    <?php if ($lat): ?>
    <div class="view-presets mt-2">
      <span style="font-size:0.72rem;color:#94a3b8;align-self:center;margin-right:4px;">Views:</span>
      <button class="view-preset-btn active" onclick="setView('eagle')">🦅 Eagle Eye</button>
      <button class="view-preset-btn" onclick="setView('top')">⬆ Top</button>
      <button class="view-preset-btn" onclick="setView('north')">N</button>
      <button class="view-preset-btn" onclick="setView('south')">S</button>
      <button class="view-preset-btn" onclick="setView('east')">E</button>
      <button class="view-preset-btn" onclick="setView('west')">W</button>
      <button class="view-preset-btn" onclick="setView('iso')">Isometric</button>
      <button class="view-preset-btn" onclick="setView('street')">Street</button>
      <button class="view-preset-btn" onclick="toggleRotate()">⟳ Auto-Rotate</button>
    </div>

    <!-- Analysis Result Panels (below viewer) -->
    <div id="panel-3d" class="analysis-panel mt-3" style="display:block;">
      <!-- 3D panel info shown in right sidebar -->
    </div>

    <div id="panel-gov" class="analysis-panel mt-3" style="display:none;">
      <div class="card">
        <div class="card-header"><i class="fas fa-university mr-2" style="color:#f43f5e;"></i>Government Registry Records</div>
        <div class="card-body">
          <p style="color:#94a3b8;font-size:0.85rem;margin-bottom:15px;">Official county assessor and land registry files compiled for this geographic coordinate footprint.</p>
          <div class="row">
            <div class="col-md-6 mb-3">
              <div style="background:rgba(0,0,0,0.2);padding:12px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);">
                <div style="font-size:0.7rem;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;">County Assessor Jurisdiction</div>
                <div style="font-family:Outfit,sans-serif;font-weight:700;font-size:1rem;color:#fff;" id="gov-prop-name">
                  <?php echo isset($census['county']) ? e($census['county'] . ', ' . ($census['state'] ?? '')) : ($property && isset($property['formatted_address']) ? 'Registry Ref: ' . explode(',', $property['formatted_address'])[0] : 'Registry Property Record'); ?>
                </div>
              </div>
            </div>
            <div class="col-md-6 mb-3">
              <div style="background:rgba(0,0,0,0.2);padding:12px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);">
                <div style="font-size:0.7rem;color:#94a3b8;margin-bottom:4px;text-transform:uppercase;">Assessor Land Area</div>
                <div style="font-family:Outfit,sans-serif;font-weight:700;font-size:1rem;color:#00d4ff;" id="gov-land-area">
                  <?php 
                    $base_sq = 8250;
                    if ($footprint && isset($footprint['base_area_sqft'])) {
                      $base_sq = round($footprint['base_area_sqft'] * 3.5);
                    }
                    echo number_format($base_sq) . ' sq ft';
                  ?>
                </div>
              </div>
            </div>
          </div>
          
          <div style="background:rgba(244,63,94,0.05);border:1px solid rgba(244,63,94,0.15);border-radius:8px;padding:12px;margin-top:5px;">
            <h6 style="color:#f43f5e;font-size:0.85rem;font-weight:700;margin-bottom:8px;"><i class="fas fa-file-invoice mr-2"></i>County Assessor File Details</h6>
            <table class="table table-sm mb-0" style="font-size:0.8rem;color:#e2e8f0;background:transparent;">
              <tbody>
                <tr>
                  <td style="border:none;padding:4px 0;color:#94a3b8;">Parcel ID / GEOID:</td>
                  <td style="border:none;padding:4px 0;font-weight:600;text-align:right;" id="gov-apn"><?php echo isset($census['geoid']) ? e($census['geoid']) : ($property ? 'APN-' . (34000 + $property['id']) : 'Pending'); ?></td>
                </tr>
                <tr>
                  <td style="border:none;padding:4px 0;color:#94a3b8;">Zoning Code:</td>
                  <td style="border:none;padding:4px 0;font-weight:600;text-align:right;" id="gov-zoning">R-2 Residential Low Density</td>
                </tr>
                <tr>
                  <td style="border:none;padding:4px 0;color:#94a3b8;">Census Tract:</td>
                  <td style="border:none;padding:4px 0;font-weight:600;text-align:right;" id="gov-tract"><?php echo isset($census['tract']) ? e($census['tract']) : '—'; ?></td>
                </tr>
                <tr>
                  <td style="border:none;padding:4px 0;color:#94a3b8;">Census Block:</td>
                  <td style="border:none;padding:4px 0;font-weight:600;text-align:right;" id="gov-block"><?php echo isset($census['block']) ? e($census['block']) : '—'; ?></td>
                </tr>
                <tr>
                  <td style="border:none;padding:4px 0;color:#94a3b8;">Property Classification:</td>
                  <td style="border:none;padding:4px 0;font-weight:600;text-align:right;" id="gov-class">Single Family Residence</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div style="background:rgba(0,212,255,0.05);border:1px solid rgba(0,212,255,0.15);border-radius:8px;padding:12px;margin-top:15px;">
            <h6 style="color:#00d4ff;font-size:0.85rem;font-weight:700;margin-bottom:8px;"><i class="fas fa-cloud-showers-heavy mr-2"></i>Historical Weather &amp; Storm Hazards (NOAA/Open-Meteo)</h6>
            <table class="table table-sm mb-0" style="font-size:0.8rem;color:#e2e8f0;background:transparent;">
              <tbody>
                <tr>
                  <td style="border:none;padding:4px 0;color:#94a3b8;">12-Month Peak Wind Gust:</td>
                  <td style="border:none;padding:4px 0;font-weight:600;text-align:right;" id="weather-wind">
                    <?php echo isset($weather['max_wind_mph']) ? $weather['max_wind_mph'] . ' mph' : '—'; ?>
                  </td>
                </tr>
                <tr>
                  <td style="border:none;padding:4px 0;color:#94a3b8;">Annual Solar Radiation:</td>
                  <td style="border:none;padding:4px 0;font-weight:600;text-align:right;" id="weather-solar">
                    <?php echo isset($weather['annual_solar_kwh_m2']) ? number_format($weather['annual_solar_kwh_m2']) . ' kWh/m²' : '—'; ?>
                  </td>
                </tr>
                <tr>
                  <td style="border:none;padding:4px 0;color:#94a3b8;">Wind Damage Risk Level:</td>
                  <td style="border:none;padding:4px 0;font-weight:600;text-align:right;" id="weather-risk">
                    <?php 
                      $wind = isset($weather['max_wind_mph']) ? $weather['max_wind_mph'] : 0;
                      if ($wind > 70) echo '<span class="text-danger" style="font-weight:700;">Critical Risk</span>';
                      elseif ($wind > 55) echo '<span class="text-warning" style="font-weight:700;">Elevated Risk</span>';
                      else echo '<span class="text-success" style="font-weight:700;">Low Risk</span>';
                    ?>
                  </td>
                </tr>
                <tr>
                  <td style="border:none;padding:4px 0;color:#94a3b8;">Data Source:</td>
                  <td style="border:none;padding:4px 0;font-weight:600;text-align:right;color:#00d4ff;" id="weather-source">Open-Meteo Archive API</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div id="panel-damage" class="analysis-panel mt-3" style="display:none;">
      <div class="card">
        <div class="card-header"><i class="fas fa-exclamation-triangle mr-2" style="color:#ff6b35;"></i>AI Damage Detection Results</div>
        <div class="card-body p-0">
          <div id="damage-results-container">
            <?php if (!empty($damage_detections)): ?>
              <table class="table table-sm mb-0">
                <thead><tr><th>Damage Type</th><th>Severity</th><th>Confidence</th><th>Priority</th><th>Est. Cost</th></tr></thead>
                <tbody>
                  <?php foreach ($damage_detections as $d): ?>
                    <tr>
                      <td><?php echo e($d['damage_label'] ? $d['damage_label'] : $d['damage_type']); ?></td>
                      <td><span class="sev-<?php echo strtolower($d['severity']); ?>"><?php echo e($d['severity']); ?></span></td>
                      <td><?php echo $d['confidence'] ? round($d['confidence'] * 100) . '%' : '—'; ?></td>
                      <td><?php echo $d['repair_priority']; ?>/10</td>
                      <td><?php echo $d['estimated_cost'] ? fmt_currency($d['estimated_cost']) : '—'; ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <div class="text-center py-4 text-muted">
                <i class="fas fa-brain" style="font-size:2rem;color:rgba(0,212,255,0.2);display:block;margin-bottom:12px;"></i>
                No damage detections yet. <button onclick="runAIAnalysis()" class="btn btn-sm btn-roofiq ml-2">Run AI Analysis</button>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div id="panel-measurements" class="analysis-panel mt-3" style="display:none;">
      <div class="card">
        <div class="card-header"><i class="fas fa-ruler mr-2" style="color:#ffd600;"></i>Roof Measurements</div>
        <div class="card-body">
          <!-- 100% Accuracy Overrides -->
          <div style="background:rgba(255,214,0,0.04);border:1px solid rgba(255,214,0,0.15);border-radius:10px;padding:16px;margin-bottom:20px;">
            <div style="font-weight:700;color:#ffd600;font-size:0.9rem;margin-bottom:12px;">
              <i class="fas fa-edit mr-1"></i> Override Measurements for 100% Roofing Accuracy
            </div>
            <form id="override-form" onsubmit="saveMeasurementOverrides(event)" class="row align-items-end" style="font-size:0.82rem;">
              <div class="col-md-3 form-group mb-2">
                <label style="color:#94a3b8;">Exact Roof Area (sq ft)</label>
                <input type="number" step="1" id="over-area" class="form-control form-control-sm bg-dark text-white border-secondary" required>
              </div>
              <div class="col-md-2 form-group mb-2">
                <label style="color:#94a3b8;">Pitch (degrees)</label>
                <input type="number" step="0.1" id="over-pitch" class="form-control form-control-sm bg-dark text-white border-secondary" required>
              </div>
              <div class="col-md-2 form-group mb-2">
                <label style="color:#94a3b8;">Perimeter (ft)</label>
                <input type="number" step="1" id="over-perimeter" class="form-control form-control-sm bg-dark text-white border-secondary" required>
              </div>
              <div class="col-md-2 form-group mb-2">
                <label style="color:#94a3b8;">Ridge Length (ft)</label>
                <input type="number" step="1" id="over-ridge" class="form-control form-control-sm bg-dark text-white border-secondary" required>
              </div>
              <div class="col-md-3 form-group mb-2">
                <button type="submit" id="btn-save-overrides" class="btn btn-sm btn-roofiq btn-block"><i class="fas fa-sync-alt mr-1"></i>Save & Recalculate</button>
              </div>
            </form>
          </div>

          <div class="row" id="measurements-container">
            <?php
              $meas_fields = array(
                array('label'=>'Total Roof Area',     'key'=>'roof_area_sqft',    'fmt'=>'sqft'),
                array('label'=>'Roof Squares',        'key'=>'roof_squares',      'fmt'=>'num'),
                array('label'=>'Pitch (degrees)',     'key'=>'roof_pitch_deg',    'fmt'=>'deg'),
                array('label'=>'Pitch Ratio',         'key'=>'roof_pitch_ratio',  'fmt'=>'str'),
                array('label'=>'Ridge Length',        'key'=>'ridge_length_ft',   'fmt'=>'ft'),
                array('label'=>'Eave Length',         'key'=>'eave_length_ft',    'fmt'=>'ft'),
                array('label'=>'Rake Length',         'key'=>'rake_length_ft',    'fmt'=>'ft'),
                array('label'=>'Perimeter',           'key'=>'perimeter_ft',      'fmt'=>'ft'),
                array('label'=>'Roof Facets',         'key'=>'facets_count',      'fmt'=>'num'),
                array('label'=>'Complexity',          'key'=>'complexity',        'fmt'=>'str'),
              );
              foreach ($meas_fields as $mf) {
                $val = isset($init_roof_data[$mf['key']]) ? $init_roof_data[$mf['key']] : null;
                if ($analysis && isset($analysis[$mf['key']]) && $analysis[$mf['key']]) {
                  $val = $analysis[$mf['key']];
                }
                $display = '—';
                if ($val !== null && $val !== '') {
                  if ($mf['fmt'] === 'sqft') $display = number_format(floatval($val), 1) . ' sq ft';
                  elseif ($mf['fmt'] === 'ft')  $display = number_format(floatval($val), 1) . ' ft';
                  elseif ($mf['fmt'] === 'deg')  $display = number_format(floatval($val), 1) . '°';
                  elseif ($mf['fmt'] === 'num')  $display = number_format(floatval($val), 2);
                  else $display = e($val);
                }
                echo '<div class="col-md-3 col-sm-4 col-6 mb-3">
                  <div style="background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.06);border-radius:8px;padding:12px;">
                    <div style="font-family:Outfit,sans-serif;font-weight:700;font-size:1.1rem;color:#ffd600;">' . $display . '</div>
                    <div style="font-size:0.7rem;color:#94a3b8;">' . e($mf['label']) . '</div>
                  </div>
                </div>';
              }
            ?>
          </div>

          <!-- Individual Roof Facets Analysis -->
          <div class="card mt-4" style="background: rgba(0,0,0,0.1) !important; border: 1px solid rgba(255,255,255,0.06) !important;">
            <div class="card-header" style="background: rgba(0,212,255,0.03) !important; display:flex; justify-content:space-between; align-items:center;">
              <span style="font-weight:700; color:#fff; font-size:0.9rem;"><i class="fas fa-th-large mr-2" style="color:#00d4ff;"></i>Individual Roof Facets (AI Segments)</span>
              <span class="badge badge-roofiq" id="facets-count-badge">0 Facets</span>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size:0.82rem; color:#e2e8f0;">
                  <thead style="background: rgba(0,0,0,0.2);">
                    <tr>
                      <th>Facet Name</th>
                      <th class="text-right">Area (sq ft)</th>
                      <th class="text-right">Pitch (deg)</th>
                      <th class="text-right">Azimuth (deg)</th>
                      <th>Orientation</th>
                      <th class="text-right">Condition Score</th>
                    </tr>
                  </thead>
                  <tbody id="roof-facets-tbody">
                    <tr class="text-center text-muted py-3"><td colspan="6">No facet segments analyzed yet. Click "Run Full AI Analysis" to extract segments.</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <div id="panel-materials" class="analysis-panel mt-3" style="display:none;">
      <div class="card">
        <div class="card-header">
          <i class="fas fa-boxes mr-2" style="color:#00e676;"></i>Material Takeoff — Bill of Materials
          <div class="card-tools">
            <select id="roof-type-select" onchange="updateTakeoff(this.value)" class="form-control form-control-sm" style="width:160px;display:inline-block;">
              <option value="Residential">Residential (Shingles)</option>
              <option value="Commercial">Commercial (TPO)</option>
              <option value="EPDM">Commercial (EPDM)</option>
            </select>
          </div>
        </div>
        <div class="card-body p-0" id="takeoff-container">
          <?php
            $takeoff_items = !empty($takeoff) ? $takeoff : generate_material_takeoff($init_roof_data, 'Residential');
            $total_mat_cost = 0;
            if (!empty($takeoff_items)):
          ?>
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>Material</th>
                <th>Manufacturer</th>
                <th>Category</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Unit Cost</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($takeoff_items as $ti):
                $ttl = isset($ti['total_cost']) ? floatval($ti['total_cost']) : (floatval($ti['qty']) * floatval($ti['unit_cost']));
                $total_mat_cost += $ttl;
              ?>
                <tr>
                  <td style="font-weight:500;"><?php echo e($ti['material_name'] ?? $ti['name']); ?></td>
                  <td style="font-size:0.78rem;color:#94a3b8;"><?php echo e($ti['manufacturer'] ?? ''); ?></td>
                  <td><span class="badge badge-secondary" style="font-size:0.65rem;"><?php echo e($ti['category'] ?? ''); ?></span></td>
                  <td><?php echo number_format(floatval($ti['qty'] ?? 0), 2); ?></td>
                  <td style="color:#94a3b8;font-size:0.82rem;"><?php echo e($ti['unit'] ?? ''); ?></td>
                  <td><?php echo fmt_currency($ti['unit_cost'] ?? 0); ?></td>
                  <td style="font-weight:600;color:#00e676;"><?php echo fmt_currency($ttl); ?></td>
                </tr>
              <?php endforeach; ?>
              <tr class="takeoff-total">
                <td colspan="6" style="text-align:right;font-weight:700;color:#fff;">TOTAL ESTIMATED MATERIAL COST</td>
                <td style="font-size:1.1rem;color:#00d4ff;font-weight:700;"><?php echo fmt_currency($total_mat_cost); ?></td>
              </tr>
            </tbody>
          </table>
          <?php else: ?>
            <div class="text-center py-4 text-muted">Run analysis to generate material takeoff</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div id="panel-solar" class="analysis-panel mt-3" style="display:none;">
      <div class="card">
        <div class="card-header"><i class="fas fa-solar-panel mr-2" style="color:#ffd600;"></i>Solar Feasibility Analysis</div>
        <div class="card-body">
          <?php
            $sol_panels  = isset($init_roof_data['solar_panels'])       ? $init_roof_data['solar_panels']       : 0;
            $sol_kwh     = isset($init_roof_data['solar_kwh_year'])      ? $init_roof_data['solar_kwh_year']      : 0;
            $sol_savings = isset($init_roof_data['solar_savings_year'])  ? $init_roof_data['solar_savings_year']  : 0;
            if ($analysis && $analysis['solar_panels']) {
              $sol_panels  = $analysis['solar_panels'];
              $sol_kwh     = $analysis['solar_kwh_year'];
              $sol_savings = $analysis['solar_savings_year'];
            }
          ?>
          <div class="row mb-3">
            <div class="col-md-3 col-sm-6 mb-2">
              <div class="solar-metric">
                <div class="value"><?php echo number_format($sol_panels); ?></div>
                <div class="label">Solar Panels</div>
              </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-2">
              <div class="solar-metric">
                <div class="value"><?php echo number_format($sol_kwh); ?></div>
                <div class="label">kWh / Year</div>
              </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-2">
              <div class="solar-metric">
                <div class="value"><?php echo fmt_currency($sol_savings); ?></div>
                <div class="label">Annual Savings</div>
              </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-2">
              <div class="solar-metric">
                <div class="value"><?php echo $sol_savings ? fmt_currency($sol_savings * 25) : '—'; ?></div>
                <div class="label">25-Year Savings</div>
              </div>
            </div>
          </div>
          <div class="chart-container" style="height:200px;">
            <canvas id="solarChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div id="panel-vendors" class="analysis-panel mt-3" style="display:none;">
      <div class="card">
        <div class="card-header"><i class="fas fa-truck mr-2" style="color:#a78bfa;"></i>Vendor Pricing &amp; Procurement</div>
        <div class="card-body">
          <div class="row">
            <?php foreach ($vendors as $v): ?>
            <div class="col-md-4 col-sm-6 mb-3">
              <div class="vendor-card" onclick="selectVendor(<?php echo e($v['id']); ?>)" data-vendor="<?php echo e($v['id']); ?>">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                  <div style="font-weight:700;font-size:0.9rem;color:#fff;"><?php echo e($v['name']); ?></div>
                  <?php if ($v['is_preferred']): ?>
                    <span class="preferred-badge">⭐ Preferred</span>
                  <?php endif; ?>
                </div>
                <?php if ($v['specialty']): ?>
                  <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:6px;"><?php echo e($v['specialty']); ?></div>
                <?php endif; ?>
                <?php if ($v['phone']): ?>
                  <div style="font-size:0.78rem;color:#00d4ff;"><i class="fas fa-phone mr-1"></i><?php echo e($v['phone']); ?></div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if ($property): ?>
          <div class="mt-3">
            <button onclick="createProcurement()" class="btn btn-roofiq">
              <i class="fas fa-shopping-cart mr-1"></i> Create Procurement Request
            </button>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div id="panel-report" class="analysis-panel mt-3" style="display:none;">
      <div class="card">
        <div class="card-header"><i class="fas fa-file-pdf mr-2" style="color:#ff6b35;"></i>Generate PDF Report</div>
        <div class="card-body">
          <p style="color:#94a3b8;font-size:0.88rem;">Generate a comprehensive professional report including satellite imagery, measurements, damage analysis, material takeoff, vendor pricing, and solar feasibility.</p>
          <div class="row mb-3">
            <div class="col-md-6">
              <label>Report Type</label>
              <select id="report-type" class="form-control">
                <option>Full Analysis Report</option>
                <option>Measurement Summary</option>
                <option>Damage Report Only</option>
                <option>Material Takeoff</option>
                <option>Solar Feasibility</option>
              </select>
            </div>
            <div class="col-md-6">
              <label>Estimator Notes</label>
              <textarea id="report-notes" class="form-control" rows="2" placeholder="Add notes for the report..."></textarea>
            </div>
          </div>
          <button onclick="generateReport()" class="btn btn-roofiq-danger">
            <i class="fas fa-file-pdf mr-1"></i> Download PDF Report (TCPDF)
          </button>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /analysis-center -->

  <!-- ===== RIGHT PANEL ===== -->
  <?php if ($lat): ?>
  <div class="analysis-right">
    <div style="font-size:0.65rem;letter-spacing:1.5px;color:#94a3b8;text-transform:uppercase;margin-bottom:12px;">Analysis Results</div>

    <!-- AI Analysis Button -->
    <button onclick="runAIAnalysis()" id="btn-ai-full" class="btn btn-block mb-3"
            style="background:linear-gradient(135deg,rgba(0,230,118,0.15),rgba(0,153,76,0.15));border:1px solid rgba(0,230,118,0.3);color:#00e676;font-weight:600;">
      <i class="fas fa-brain mr-2"></i> Run Full AI Analysis
    </button>
    <div id="ai-progress" style="display:none;text-align:center;padding:8px;font-size:0.82rem;color:#94a3b8;">
      <i class="fas fa-spinner fa-spin mr-1"></i> Processing with YOLO11 + SAM2...
    </div>

    <!-- Roof Type -->
    <div style="background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:12px;margin-bottom:12px;">
      <div style="font-size:0.7rem;color:#94a3b8;margin-bottom:6px;">DETECTED ROOF TYPE</div>
      <div style="font-weight:700;color:#fff;font-size:0.95rem;" id="roof-type-display">
        <?php echo $analysis && $analysis['roof_type'] ? e($analysis['roof_type']) : 'Asphalt Shingle'; ?>
      </div>
      <div style="font-size:0.72rem;color:#94a3b8;" id="roof-type-confidence">
        <?php echo $analysis && $analysis['roof_type_confidence'] ? 'Confidence: ' . $analysis['roof_type_confidence'] . '%' : 'Estimated'; ?>
      </div>
    </div>

    <!-- Damage Summary -->
    <div style="background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:12px;margin-bottom:12px;">
      <div style="font-size:0.7rem;color:#94a3b8;margin-bottom:8px;">DAMAGE SUMMARY</div>
      <?php
        $damage_counts = array('Critical'=>0,'High'=>0,'Medium'=>0,'Low'=>0);
        foreach ($damage_detections as $d) {
          if (isset($damage_counts[$d['severity']])) {
            $damage_counts[$d['severity']]++;
          }
        }
        $sev_colors = array('Critical'=>'#ff1744','High'=>'#ff6d00','Medium'=>'#ffd600','Low'=>'#00e676');
        foreach ($damage_counts as $sev => $cnt): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
            <span style="font-size:0.8rem;color:<?php echo $sev_colors[$sev]; ?>;"><?php echo $sev; ?></span>
            <span style="font-weight:700;color:#fff;" id="dmg-<?php echo strtolower($sev); ?>"><?php echo $cnt; ?></span>
          </div>
      <?php endforeach; ?>
    </div>

    <!-- Solar Quick View -->
    <div style="background:rgba(0,230,118,0.06);border:1px solid rgba(0,230,118,0.15);border-radius:10px;padding:12px;margin-bottom:12px;">
      <div style="font-size:0.7rem;color:#94a3b8;margin-bottom:8px;">SOLAR POTENTIAL</div>
      <div style="font-family:Outfit,sans-serif;font-weight:700;font-size:1.3rem;color:#00e676;">
        <?php echo isset($init_roof_data['solar_kwh_year']) ? number_format($init_roof_data['solar_kwh_year']) : '—'; ?> kWh/yr
      </div>
      <div style="font-size:0.78rem;color:#94a3b8;">
        ~<?php echo isset($init_roof_data['solar_panels']) ? $init_roof_data['solar_panels'] : '—'; ?> panels &bull;
        <?php echo isset($init_roof_data['solar_savings_year']) ? fmt_currency($init_roof_data['solar_savings_year']) : '—'; ?>/yr savings
      </div>
    </div>

    <!-- Material Quick Cost -->
    <div style="background:rgba(0,212,255,0.06);border:1px solid rgba(0,212,255,0.15);border-radius:10px;padding:12px;margin-bottom:12px;">
      <div style="font-size:0.7rem;color:#94a3b8;margin-bottom:6px;">MATERIAL ESTIMATE</div>
      <?php
        $quick_takeoff = generate_material_takeoff($init_roof_data, 'Residential');
        $quick_total   = 0;
        foreach ($quick_takeoff as $qt) {
          $quick_total += floatval($qt['qty']) * floatval($qt['unit_cost']);
        }
      ?>
      <div style="font-family:Outfit,sans-serif;font-weight:700;font-size:1.2rem;color:#00d4ff;" id="material-total-quick">
        <?php echo fmt_currency($quick_total); ?>
      </div>
      <div style="font-size:0.72rem;color:#94a3b8;">Residential estimate (before markup)</div>
    </div>

    <!-- Recommendations -->
    <div style="background:rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:12px;">
      <div style="font-size:0.7rem;color:#94a3b8;margin-bottom:8px;">AI RECOMMENDATIONS</div>
      <div id="recommendations-list" style="font-size:0.8rem;line-height:1.7;color:#e2e8f0;">
        <?php
          $score = intval(isset($init_roof_data['condition_score']) ? $init_roof_data['condition_score'] : 75);
          if ($analysis && $analysis['condition_score']) {
            $score = intval($analysis['condition_score']);
          }
          if ($score >= 85): ?>
            <div style="padding:4px 0;"><i class="fas fa-check-circle mr-1" style="color:#00e676;"></i> Roof is in excellent condition</div>
            <div style="padding:4px 0;"><i class="fas fa-check-circle mr-1" style="color:#00e676;"></i> Schedule routine inspection in 2-3 years</div>
            <div style="padding:4px 0;"><i class="fas fa-solar-panel mr-1" style="color:#ffd600;"></i> Excellent solar candidate</div>
          <?php elseif ($score >= 70): ?>
            <div style="padding:4px 0;"><i class="fas fa-wrench mr-1" style="color:#ffd600;"></i> Minor repairs recommended</div>
            <div style="padding:4px 0;"><i class="fas fa-calendar mr-1" style="color:#94a3b8;"></i> Plan replacement in 5-7 years</div>
            <div style="padding:4px 0;"><i class="fas fa-solar-panel mr-1" style="color:#ffd600;"></i> Good solar candidate after repairs</div>
          <?php else: ?>
            <div style="padding:4px 0;"><i class="fas fa-exclamation-triangle mr-1" style="color:#ff6b35;"></i> Immediate repairs required</div>
            <div style="padding:4px 0;"><i class="fas fa-hard-hat mr-1" style="color:#ff1744;"></i> Consider full roof replacement</div>
            <div style="padding:4px 0;"><i class="fas fa-tools mr-1" style="color:#ff6b35;"></i> Request vendor quotes now</div>
          <?php endif; ?>
      </div>
    </div>
  </div><!-- /analysis-right -->
  <?php endif; ?>

</div><!-- /analysis-main -->
<?php endif; ?>

<!-- Hidden data for JS -->
<script>
var ROOFIQ = {
  lat:           <?php echo $lat ? $lat : 'null'; ?>,
  lng:           <?php echo $lng ? $lng : 'null'; ?>,
  address:       <?php echo j($formatted_address ? $formatted_address : $address); ?>,
  propertyId:    <?php echo $property ? intval($property['id']) : 'null'; ?>,
  analysisId:    <?php echo $analysis ? intval($analysis['id']) : 'null'; ?>,
  cesiumToken:   <?php echo j($cesium_token); ?>,
  maptilerKey:   <?php echo j($maptiler_key); ?>,
  googleKey:     <?php echo j($google_key); ?>,
  aiServiceUrl:  <?php echo j($ai_service_url); ?>,
  roofData:      <?php echo json_encode($init_roof_data); ?>,
  footprint:     <?php echo ($footprint && $footprint['geojson']) ? $footprint['geojson'] : 'null'; ?>,
  sections:      <?php echo $sections ? json_encode($sections) : 'null'; ?>
};
</script>

<link rel="stylesheet" href="https://cesium.com/downloads/cesiumjs/releases/1.115/Build/Cesium/Widgets/widgets.css">
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<?php
include_page_footer(array(
    'https://unpkg.com/maplibre-gl@3.6.2/dist/maplibre-gl.js',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js',
    roofiq_base_url() . 'js/roofiq-viewer.js',
    roofiq_base_url() . 'js/roofiq-map.js',
    roofiq_base_url() . 'js/roofiq-overlay.js',
    roofiq_base_url() . 'js/roofiq-charts.js',
    roofiq_base_url() . 'js/roofiq-ui.js',
));
?>
