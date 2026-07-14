<?php
/**
 * ROOFIQ AI ENTERPRISE — Dashboard
 * PHP 7.0.1 Compatible
 */
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
session_start_safe();
require_login();

$user = current_user();

// ---- KPI Counts ----
$total_properties = 0;
$total_analyses   = 0;
$total_reports    = 0;
$total_projects   = 0;
$total_area       = 0;
$total_materials  = 0;

if ($pdo) {
    $r = db_fetch("SELECT COUNT(*) as cnt FROM properties");
    $total_properties = $r ? intval($r['cnt']) : 0;

    $r = db_fetch("SELECT COUNT(*) as cnt FROM roof_analysis WHERE status='Complete'");
    $total_analyses = $r ? intval($r['cnt']) : 0;

    $r = db_fetch("SELECT COUNT(*) as cnt FROM reports");
    $total_reports = $r ? intval($r['cnt']) : 0;

    $r = db_fetch("SELECT COUNT(*) as cnt FROM projects WHERE status NOT IN ('Closed','Cancelled')");
    $total_projects = $r ? intval($r['cnt']) : 0;

    $r = db_fetch("SELECT COALESCE(SUM(roof_area_sqft),0) as total FROM roof_analysis WHERE status='Complete'");
    $total_area = $r ? floatval($r['total']) : 0;

    $r = db_fetch("SELECT COALESCE(SUM(total_cost),0) as total FROM material_takeoffs");
    $total_materials = $r ? floatval($r['total']) : 0;
}

// ---- Recent Activity ----
$recent_analyses = db_fetch_all(
    "SELECT ra.*, p.address FROM roof_analysis ra
     LEFT JOIN properties p ON p.id=ra.property_id
     ORDER BY ra.created_at DESC LIMIT 8"
);

// ---- Recent Projects ----
$recent_projects = db_fetch_all(
    "SELECT pj.*, p.address, u.full_name as estimator_name
     FROM projects pj
     LEFT JOIN properties p ON p.id=pj.property_id
     LEFT JOIN users u ON u.id=pj.estimator_id
     ORDER BY pj.updated_at DESC LIMIT 6"
);

// ---- Chart data: last 7 days analyses ----
$daily_labels  = array();
$daily_counts  = array();
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime('-' . $i . ' days'));
    $daily_labels[] = date('M j', strtotime($date));
    $r = db_fetch("SELECT COUNT(*) as cnt FROM roof_analysis WHERE DATE(created_at)=?", array($date));
    $daily_counts[] = $r ? intval($r['cnt']) : 0;
}

// ---- Project status distribution ----
$proj_statuses = db_fetch_all("SELECT status, COUNT(*) as cnt FROM projects GROUP BY status");
$proj_labels  = array();
$proj_vals    = array();
foreach ($proj_statuses as $ps) {
    $proj_labels[] = $ps['status'];
    $proj_vals[]   = intval($ps['cnt']);
}

include_page_header('Dashboard');
?>

<div class="content-header">
  <div class="container-fluid">
    <div class="row align-items-center mb-2">
      <div class="col-sm-6">
        <h1 class="m-0 roofiq-page-title">
          <i class="fas fa-tachometer-alt mr-2" style="color:#00d4ff;"></i>Dashboard
        </h1>
      </div>
      <div class="col-sm-6 text-sm-right">
        <a href="analysis.php" class="btn btn-roofiq btn-sm">
          <i class="fas fa-plus mr-1"></i> New Analysis
        </a>
      </div>
    </div>
  </div>
</div>

<section class="content">
<div class="container-fluid">

<!-- KPI Row -->
<div class="row">
  <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
    <div class="info-box">
      <span class="info-box-icon" style="background:rgba(0,212,255,0.12);">
        <i class="fas fa-home" style="color:#00d4ff;font-size:1.8rem;"></i>
      </span>
      <div class="info-box-content">
        <span class="info-box-text">Properties</span>
        <span class="info-box-number"><?php echo number_format($total_properties); ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
    <div class="info-box">
      <span class="info-box-icon" style="background:rgba(0,230,118,0.12);">
        <i class="fas fa-satellite-dish" style="color:#00e676;font-size:1.8rem;"></i>
      </span>
      <div class="info-box-content">
        <span class="info-box-text">Analyses</span>
        <span class="info-box-number" style="color:#00e676;"><?php echo number_format($total_analyses); ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
    <div class="info-box">
      <span class="info-box-icon" style="background:rgba(255,107,53,0.12);">
        <i class="fas fa-file-pdf" style="color:#ff6b35;font-size:1.8rem;"></i>
      </span>
      <div class="info-box-content">
        <span class="info-box-text">Reports</span>
        <span class="info-box-number" style="color:#ff6b35;"><?php echo number_format($total_reports); ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
    <div class="info-box">
      <span class="info-box-icon" style="background:rgba(255,214,0,0.12);">
        <i class="fas fa-project-diagram" style="color:#ffd600;font-size:1.8rem;"></i>
      </span>
      <div class="info-box-content">
        <span class="info-box-text">Active Projects</span>
        <span class="info-box-number" style="color:#ffd600;"><?php echo number_format($total_projects); ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
    <div class="info-box">
      <span class="info-box-icon" style="background:rgba(0,212,255,0.08);">
        <i class="fas fa-ruler-combined" style="color:#00d4ff;font-size:1.8rem;"></i>
      </span>
      <div class="info-box-content">
        <span class="info-box-text">Total Area</span>
        <span class="info-box-number" style="font-size:0.95rem;"><?php echo number_format($total_area/1000, 1); ?>K sq</span>
      </div>
    </div>
  </div>
  <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
    <div class="info-box">
      <span class="info-box-icon" style="background:rgba(0,230,118,0.08);">
        <i class="fas fa-boxes" style="color:#00e676;font-size:1.8rem;"></i>
      </span>
      <div class="info-box-content">
        <span class="info-box-text">Material Est.</span>
        <span class="info-box-number" style="font-size:0.95rem;color:#00e676;">$<?php echo number_format($total_materials/1000, 1); ?>K</span>
      </div>
    </div>
  </div>
</div>

<!-- Quick Action Banner -->
<div class="row mb-3">
  <div class="col-12">
    <div class="card" style="background:linear-gradient(135deg,rgba(0,212,255,0.08),rgba(0,153,204,0.05));border:1px solid rgba(0,212,255,0.2);">
      <div class="card-body py-3">
        <div class="row align-items-center">
          <div class="col-md-6">
            <h5 class="mb-1" style="font-family:'Outfit',sans-serif;font-weight:700;color:#fff;">
              <i class="fas fa-search-location mr-2" style="color:#00d4ff;"></i>Analyze a New Property
            </h5>
            <p class="mb-0 text-muted" style="font-size:0.85rem;">Enter any US address to get full 3D roof analysis, damage detection, materials &amp; solar estimate.</p>
          </div>
          <div class="col-md-6">
            <form action="analysis.php" method="GET" class="d-flex" style="gap:10px;align-items:center;">
              <input type="text" name="address" class="form-control" placeholder="Enter property address (e.g. 123 Main St, Providence RI)" style="flex:1;">
              <button type="submit" class="btn btn-roofiq" style="white-space:nowrap;">
                <i class="fas fa-satellite-dish mr-1"></i> Analyze
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row">
  <div class="col-md-8 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-line mr-2" style="color:#00d4ff;"></i>Daily Analyses (Last 7 Days)</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
        </div>
      </div>
      <div class="card-body">
        <div class="chart-container">
          <canvas id="dailyChart"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-pie mr-2" style="color:#ffd600;"></i>Project Status</h3>
        <div class="card-tools">
          <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
        </div>
      </div>
      <div class="card-body">
        <div class="chart-container">
          <canvas id="projectChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Analyses + Projects -->
<div class="row">
  <div class="col-md-7 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history mr-2" style="color:#00e676;"></i>Recent Analyses</h3>
        <div class="card-tools">
          <a href="reports.php" class="btn btn-sm btn-roofiq">View All</a>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>Address</th>
              <th>Area</th>
              <th>Score</th>
              <th>Status</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent_analyses)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No analyses yet. <a href="analysis.php">Start one</a></td></tr>
            <?php else: ?>
              <?php foreach ($recent_analyses as $ra): ?>
                <tr>
                  <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <a href="analysis.php?property_id=<?php echo e($ra['property_id']); ?>" style="color:#00d4ff;">
                      <?php echo e($ra['address']); ?>
                    </a>
                  </td>
                  <td><?php echo $ra['roof_area_sqft'] ? number_format($ra['roof_area_sqft']) . ' sf' : '—'; ?></td>
                  <td>
                    <?php if ($ra['condition_score']): ?>
                      <span style="color:<?php echo $ra['condition_score'] >= 75 ? '#00e676' : ($ra['condition_score'] >= 60 ? '#ffd600' : '#ff6b35'); ?>">
                        <?php echo $ra['condition_score']; ?>/100
                      </span>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td><?php echo status_badge($ra['status']); ?></td>
                  <td style="font-size:0.78rem;color:#94a3b8;"><?php echo fmt_ago($ra['created_at']); ?></td>
                  <td>
                    <a href="analysis.php?property_id=<?php echo e($ra['property_id']); ?>" class="btn btn-xs btn-outline-info">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-5 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-project-diagram mr-2" style="color:#ffd600;"></i>Active Projects</h3>
        <div class="card-tools">
          <a href="projects.php" class="btn btn-sm btn-roofiq">View All</a>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr><th>Project</th><th>Status</th><th>Estimator</th></tr>
          </thead>
          <tbody>
            <?php if (empty($recent_projects)): ?>
              <tr><td colspan="3" class="text-center text-muted py-4">No projects yet. <a href="projects.php">Create one</a></td></tr>
            <?php else: ?>
              <?php foreach ($recent_projects as $pj): ?>
                <tr>
                  <td>
                    <a href="projects.php?id=<?php echo e($pj['id']); ?>" style="color:#00d4ff;font-size:0.85rem;">
                      <?php echo e($pj['project_name']); ?>
                    </a>
                    <div style="font-size:0.72rem;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;">
                      <?php echo e($pj['address']); ?>
                    </div>
                  </td>
                  <td><?php echo status_badge($pj['status']); ?></td>
                  <td style="font-size:0.78rem;color:#94a3b8;"><?php echo e($pj['estimator_name'] ? $pj['estimator_name'] : '—'); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        </div><!-- /table-responsive -->
      </div>
    </div>
  </div>
</div>

</div>
</section>

<script>
var dailyLabels = <?php echo json_encode($daily_labels); ?>;
var dailyCounts = <?php echo json_encode($daily_counts); ?>;
var projLabels  = <?php echo json_encode($proj_labels); ?>;
var projVals    = <?php echo json_encode($proj_vals); ?>;
</script>

<?php
include_page_footer(array(roofiq_base_url() . 'js/roofiq-charts.js'));
?>
