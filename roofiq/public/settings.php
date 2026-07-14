<?php
/**
 * ROOFIQ — Settings Page (Admin only)
 * PHP 7.0.1 Compatible
 */
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
session_start_safe();
require_login();
require_role('Admin');

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $keys_to_save = array(
        'app_name','company_name','google_maps_api_key','cesium_ion_token',
        'maptiler_api_key','eagleview_api_key','nearmap_api_key','openai_api_key',
        'ai_service_url','waste_factor_residential','waste_factor_commercial',
        'default_markup_pct','session_timeout','reports_per_page',
    );
    foreach ($keys_to_save as $k) {
        $val = isset($_POST[$k]) ? trim($_POST[$k]) : '';
        roofiq_save_setting($k, $val);
    }
    $saved = true;
    log_activity('Settings updated', 'settings');
    global $_ROOFIQ_SETTINGS_CACHE;
    $_ROOFIQ_SETTINGS_CACHE = null; // reset cache
}

include_page_header('Settings');
page_content_header('System Settings', array('Settings' => ''));
?>

<?php if ($saved): ?>
  <div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i>Settings saved successfully.</div>
<?php endif; ?>

<form method="POST" action="settings.php">
  <?php csrf_field(); ?>

  <div class="row">
    <!-- General -->
    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header"><i class="fas fa-building mr-2" style="color:#00d4ff;"></i>General</div>
        <div class="card-body">
          <div class="form-group">
            <label>Application Name</label>
            <input type="text" name="app_name" class="form-control" value="<?php echo e(roofiq_setting('app_name','SHEKHAR ROOFIQ AI ENTERPRISE')); ?>">
          </div>
          <div class="form-group">
            <label>Company Name</label>
            <input type="text" name="company_name" class="form-control" value="<?php echo e(roofiq_setting('company_name','Shekhar Building Materials')); ?>">
          </div>
          <div class="form-group">
            <label>AI Service URL <small class="text-muted">(Python FastAPI)</small></label>
            <input type="text" name="ai_service_url" class="form-control" value="<?php echo e(roofiq_setting('ai_service_url','http://localhost:5001')); ?>">
            <small class="form-text text-muted">URL of the Python AI microservice (uvicorn main:app --port 5001)</small>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><i class="fas fa-sliders-h mr-2" style="color:#ffd600;"></i>Defaults</div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Waste Factor % (Residential)</label>
              <input type="number" name="waste_factor_residential" class="form-control" value="<?php echo e(roofiq_setting('waste_factor_residential','15')); ?>" min="0" max="50">
            </div>
            <div class="col-md-6 form-group">
              <label>Waste Factor % (Commercial)</label>
              <input type="number" name="waste_factor_commercial" class="form-control" value="<?php echo e(roofiq_setting('waste_factor_commercial','10')); ?>" min="0" max="50">
            </div>
            <div class="col-md-6 form-group">
              <label>Default Markup %</label>
              <input type="number" name="default_markup_pct" class="form-control" value="<?php echo e(roofiq_setting('default_markup_pct','20')); ?>" min="0" max="200">
            </div>
            <div class="col-md-6 form-group">
              <label>Session Timeout (minutes)</label>
              <input type="number" name="session_timeout" class="form-control" value="<?php echo e(roofiq_setting('session_timeout','120')); ?>" min="10" max="1440">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- API Keys -->
    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header"><i class="fas fa-key mr-2" style="color:#a78bfa;"></i>API Keys</div>
        <div class="card-body">
          <?php
            $api_fields = array(
              array('key'=>'google_maps_api_key', 'label'=>'Google Maps / Geocoding API Key', 'link'=>'https://console.cloud.google.com/'),
              array('key'=>'cesium_ion_token',    'label'=>'Cesium Ion Access Token (3D Viewer)', 'link'=>'https://ion.cesium.com/tokens'),
              array('key'=>'maptiler_api_key',    'label'=>'MapTiler API Key (2D Satellite)', 'link'=>'https://cloud.maptiler.com/account/keys/'),
              array('key'=>'eagleview_api_key',   'label'=>'EagleView API Key', 'link'=>'https://www.eagleview.com/'),
              array('key'=>'nearmap_api_key',     'label'=>'Nearmap API Key', 'link'=>'https://nearmap.com/'),
              array('key'=>'openai_api_key',      'label'=>'OpenAI API Key (AI Assistant)', 'link'=>'https://platform.openai.com/api-keys'),
            );
            foreach ($api_fields as $f):
              $val  = roofiq_setting($f['key'], '');
              $masked = $val ? '••••••••' . substr($val, -4) : '';
          ?>
            <div class="form-group">
              <label>
                <?php echo e($f['label']); ?>
                <a href="<?php echo e($f['link']); ?>" target="_blank" style="font-size:0.72rem;color:#00d4ff;margin-left:6px;">Get Key ↗</a>
              </label>
              <div class="input-group">
                <input type="password" name="<?php echo e($f['key']); ?>" class="form-control api-key-input"
                       value="<?php echo e($val); ?>"
                       placeholder="<?php echo $val ? e($masked) : 'Enter API key...'; ?>">
                <div class="input-group-append">
                  <button type="button" class="btn btn-outline-secondary" onclick="toggleKeyVis(this)"
                          style="border-color:rgba(255,255,255,0.12);color:#94a3b8;">
                    <i class="far fa-eye"></i>
                  </button>
                </div>
              </div>
              <?php if ($val): ?>
                <small class="form-text" style="color:#00e676;"><i class="fas fa-check mr-1"></i>Key configured</small>
              <?php else: ?>
                <small class="form-text text-muted">Not configured — feature may be limited</small>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- AI Service Status -->
  <div class="card mb-4">
    <div class="card-header"><i class="fas fa-brain mr-2" style="color:#00e676;"></i>AI Service Status</div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <div id="ai-status-display" style="font-size:0.88rem;color:#94a3b8;">
          <i class="fas fa-spinner fa-spin mr-2"></i>Checking AI service...
        </div>
        <button type="button" onclick="checkAIService()" class="btn btn-sm btn-outline-info">
          <i class="fas fa-sync mr-1"></i> Check Status
        </button>
        <div style="font-size:0.82rem;color:#94a3b8;">
          Start AI service: <code style="background:rgba(255,255,255,0.08);padding:2px 8px;border-radius:4px;color:#00d4ff;">
            cd roofiq/ai_service &amp;&amp; uvicorn main:app --host 0.0.0.0 --port 5001
          </code>
        </div>
      </div>
    </div>
  </div>

  <div class="text-right mb-4">
    <button type="submit" class="btn btn-roofiq btn-lg">
      <i class="fas fa-save mr-2"></i> Save All Settings
    </button>
  </div>
</form>

<script>
function toggleKeyVis(btn) {
  var input = btn.closest('.input-group').querySelector('input');
  input.type = input.type === 'password' ? 'text' : 'password';
  btn.querySelector('i').className = input.type === 'password' ? 'far fa-eye' : 'far fa-eye-slash';
}

function checkAIService() {
  var el = document.getElementById('ai-status-display');
  el.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Checking...';

  var xhr = new XMLHttpRequest();
  xhr.open('GET', '<?php echo e(roofiq_ai_service_url()); ?>/health', true);
  xhr.timeout = 5000;

  xhr.onreadystatechange = function() {
    if (xhr.readyState !== 4) return;
    if (xhr.status === 200) {
      try {
        var d = JSON.parse(xhr.responseText);
        el.innerHTML = '<span style="color:#00e676;"><i class="fas fa-check-circle mr-1"></i>AI Service Online</span>'
          + ' &nbsp;|&nbsp; YOLO: ' + (d.models && d.models.yolo ? '<span style="color:#00e676;">✓</span>' : '<span style="color:#ff6b35;">✗</span>')
          + ' &nbsp; SAM2: ' + (d.models && d.models.sam2 ? '<span style="color:#00e676;">✓</span>' : '<span style="color:#ff6b35;">✗</span>')
          + ' &nbsp; GeoPandas: ' + (d.geopandas ? '<span style="color:#00e676;">✓</span>' : '<span style="color:#ff6b35;">✗</span>');
      } catch(e) {
        el.innerHTML = '<span style="color:#00e676;"><i class="fas fa-check-circle mr-1"></i>AI Service Reachable</span>';
      }
    } else {
      el.innerHTML = '<span style="color:#ff6b35;"><i class="fas fa-times-circle mr-1"></i>AI Service Offline (using PHP fallback)</span>';
    }
  };

  xhr.onerror = function() {
    el.innerHTML = '<span style="color:#ff6b35;"><i class="fas fa-times-circle mr-1"></i>AI Service Unreachable — using PHP fallback calculations</span>';
  };

  xhr.send();
}

document.addEventListener('DOMContentLoaded', function() {
  setTimeout(checkAIService, 800);
});
</script>

<?php
page_content_footer();
include_page_footer();
?>
