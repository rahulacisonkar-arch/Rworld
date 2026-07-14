<?php
/**
 * ROOFIQ — Materials Management
 * PHP 7.0.1 Compatible
 */
require_once dirname(__DIR__) . '/src/config.php';
require_once dirname(__DIR__) . '/src/db.php';
require_once dirname(__DIR__) . '/src/functions.php';
session_start_safe();
require_login();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validate_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'save') {
        $id   = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $data = array(
            'sku'              => trim(isset($_POST['sku'])              ? $_POST['sku']              : ''),
            'name'             => trim(isset($_POST['name'])             ? $_POST['name']             : ''),
            'category'         => trim(isset($_POST['category'])         ? $_POST['category']         : ''),
            'subcategory'      => trim(isset($_POST['subcategory'])      ? $_POST['subcategory']      : ''),
            'roof_type'        => trim(isset($_POST['roof_type'])        ? $_POST['roof_type']        : 'Both'),
            'manufacturer'     => trim(isset($_POST['manufacturer'])     ? $_POST['manufacturer']     : ''),
            'unit'             => trim(isset($_POST['unit'])             ? $_POST['unit']             : 'SQ'),
            'unit_cost'        => floatval(isset($_POST['unit_cost'])    ? $_POST['unit_cost']        : 0),
            'coverage'         => floatval(isset($_POST['coverage'])     ? $_POST['coverage']         : 0),
            'coverage_unit'    => trim(isset($_POST['coverage_unit'])    ? $_POST['coverage_unit']    : 'sq ft'),
            'waste_factor_pct' => floatval(isset($_POST['waste_factor_pct']) ? $_POST['waste_factor_pct'] : 10),
            'description'      => trim(isset($_POST['description'])      ? $_POST['description']      : ''),
            'is_active'        => 1,
        );
        if ($data['name']) {
            if ($id) {
                db_update('materials', $data, 'id=?', array($id));
                $msg = 'Material updated.';
            } else {
                db_insert('materials', $data);
                $msg = 'Material added.';
            }
        }
    } elseif ($action === 'delete') {
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        if ($id) {
            db_update('materials', array('is_active' => 0), 'id=?', array($id));
            $msg = 'Material deactivated.';
        }
    }
}

// Filters
$category_filter  = isset($_GET['category']) ? trim($_GET['category']) : '';
$roof_type_filter = isset($_GET['roof_type']) ? trim($_GET['roof_type']) : '';
$search           = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = array('is_active=1');
$params = array();

if ($category_filter) {
    $where[] = 'category=?';
    $params[] = $category_filter;
}
if ($roof_type_filter) {
    $where[] = 'roof_type=?';
    $params[] = $roof_type_filter;
}
if ($search) {
    $where[] = '(name LIKE ? OR sku LIKE ? OR manufacturer LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$where_clause = implode(' AND ', $where);
$materials = db_fetch_all("SELECT * FROM materials WHERE {$where_clause} ORDER BY category, name", $params);

// Get unique categories for filter dropdown
$categories = db_fetch_all("SELECT DISTINCT category FROM materials WHERE is_active=1 AND category != '' ORDER BY category");

include_page_header('Materials');
page_content_header('Material Catalog', array('Materials' => ''));
?>

<?php if ($msg): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check mr-2"></i><?php echo e($msg); ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
<?php endif; ?>

<!-- Filters and Actions -->
<div class="card card-roofiq mb-4">
  <div class="card-body">
    <form method="GET" class="row align-items-end">
      <div class="col-md-3 form-group">
        <label style="color:#94a3b8;font-size:0.82rem;">Search Catalog</label>
        <input type="text" name="search" value="<?php echo e($search); ?>" class="form-control" placeholder="Search SKU, name, or manufacturer...">
      </div>
      <div class="col-md-3 form-group">
        <label style="color:#94a3b8;font-size:0.82rem;">Category</label>
        <select name="category" class="form-control">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?php echo e($cat['category']); ?>" <?php echo $category_filter === $cat['category'] ? 'selected' : ''; ?>><?php echo e($cat['category']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 form-group">
        <label style="color:#94a3b8;font-size:0.82rem;">Roof Type</label>
        <select name="roof_type" class="form-control">
          <option value="">All Types</option>
          <option value="Residential" <?php echo $roof_type_filter === 'Residential' ? 'selected' : ''; ?>>Residential</option>
          <option value="Commercial" <?php echo $roof_type_filter === 'Commercial' ? 'selected' : ''; ?>>Commercial</option>
          <option value="Both" <?php echo $roof_type_filter === 'Both' ? 'selected' : ''; ?>>Both</option>
        </select>
      </div>
      <div class="col-md-3 form-group" style="display:flex;align-items:flex-end;gap:8px;">
        <button type="submit" class="btn btn-outline-light" style="flex:1;"><i class="fas fa-filter mr-1"></i>Filter</button>
        <button type="button" class="btn btn-roofiq" style="flex:1.2;" data-toggle="modal" data-target="#modalMaterial" onclick="openMaterialModal(0)">
          <i class="fas fa-plus mr-1"></i>Add Material
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Materials Table -->
<div class="card card-roofiq">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-striped mb-0" style="color:#e2e8f0;">
        <thead style="background:rgba(0,0,0,0.2);color:#94a3b8;">
          <tr>
            <th>SKU</th>
            <th>Name</th>
            <th>Category</th>
            <th>Type</th>
            <th>Manufacturer</th>
            <th>Unit</th>
            <th class="text-right">Unit Cost</th>
            <th class="text-right">Coverage</th>
            <th class="text-right">Waste %</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($materials)): ?>
            <tr>
              <td colspan="10" class="text-center py-4 text-muted">No materials found in catalog matching filters.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($materials as $m): ?>
              <tr>
                <td><code style="color:#00d4ff;"><?php echo e($m['sku'] ? $m['sku'] : 'N/A'); ?></code></td>
                <td style="font-weight:600;"><?php echo e($m['name']); ?></td>
                <td><span class="badge badge-secondary"><?php echo e($m['category']); ?></span></td>
                <td>
                  <?php if ($m['roof_type'] === 'Residential'): ?>
                    <span class="badge badge-success"><i class="fas fa-home mr-1"></i>Res</span>
                  <?php elseif ($m['roof_type'] === 'Commercial'): ?>
                    <span class="badge badge-info"><i class="fas fa-building mr-1"></i>Comm</span>
                  <?php else: ?>
                    <span class="badge badge-warning"><i class="fas fa-border-all mr-1"></i>Both</span>
                  <?php endif; ?>
                </td>
                <td><?php echo e($m['manufacturer'] ? $m['manufacturer'] : '—'); ?></td>
                <td><?php echo e($m['unit']); ?></td>
                <td class="text-right">$<?php echo number_format($m['unit_cost'], 2); ?></td>
                <td class="text-right"><?php echo number_format($m['coverage'], 1); ?> <?php echo e($m['coverage_unit']); ?></td>
                <td class="text-right"><?php echo number_format($m['waste_factor_pct'], 1); ?>%</td>
                <td class="text-center">
                  <div style="display:flex;gap:6px;justify-content:center;">
                    <button onclick="openMaterialModal(<?php echo $m['id']; ?>,<?php echo j($m); ?>)"
                            class="btn btn-xs btn-outline-info">Edit</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this material?');">
                      <?php csrf_field(); ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?php echo e($m['id']); ?>">
                      <button type="submit" class="btn btn-xs btn-outline-danger">Remove</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Material Modal -->
<div class="modal fade" id="modalMaterial">
  <div class="modal-dialog modal-lg">
    <div class="modal-content" style="background:#111827;border:1px solid rgba(0,212,255,0.15);color:#e2e8f0;">
      <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,0.08);">
        <h5 class="modal-title" id="modalMaterialTitle" style="color:#fff;"><i class="fas fa-boxes mr-2" style="color:#00d4ff;"></i>Material</h5>
        <button type="button" class="close" data-dismiss="modal" style="color:#fff;"><span>&times;</span></button>
      </div>
      <form method="POST">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="material-id" value="0">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-4 form-group">
              <label style="color:#94a3b8;">SKU / Code</label>
              <input type="text" name="sku" id="m-sku" class="form-control bg-dark text-white border-secondary">
            </div>
            <div class="col-md-8 form-group">
              <label style="color:#94a3b8;">Material Name *</label>
              <input type="text" name="name" id="m-name" class="form-control bg-dark text-white border-secondary" required>
            </div>
            <div class="col-md-6 form-group">
              <label style="color:#94a3b8;">Category *</label>
              <input type="text" name="category" id="m-category" class="form-control bg-dark text-white border-secondary" list="categories-list" required>
              <datalist id="categories-list">
                <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo e($cat['category']); ?>">
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-md-6 form-group">
              <label style="color:#94a3b8;">Subcategory</label>
              <input type="text" name="subcategory" id="m-subcategory" class="form-control bg-dark text-white border-secondary">
            </div>
            <div class="col-md-4 form-group">
              <label style="color:#94a3b8;">Roof Type Suitability</label>
              <select name="roof_type" id="m-rooftype" class="form-control bg-dark text-white border-secondary">
                <option value="Both">Both / Universal</option>
                <option value="Residential">Residential Only</option>
                <option value="Commercial">Commercial Only</option>
              </select>
            </div>
            <div class="col-md-4 form-group">
              <label style="color:#94a3b8;">Manufacturer</label>
              <input type="text" name="manufacturer" id="m-manufacturer" class="form-control bg-dark text-white border-secondary">
            </div>
            <div class="col-md-4 form-group">
              <label style="color:#94a3b8;">Pricing Unit</label>
              <input type="text" name="unit" id="m-unit" class="form-control bg-dark text-white border-secondary" placeholder="e.g. SQ, EA, ROLL, LF" required>
            </div>
            <div class="col-md-4 form-group">
              <label style="color:#94a3b8;">Unit Cost ($) *</label>
              <input type="number" step="0.01" name="unit_cost" id="m-cost" class="form-control bg-dark text-white border-secondary" required>
            </div>
            <div class="col-md-4 form-group">
              <label style="color:#94a3b8;">Coverage Area</label>
              <input type="number" step="0.1" name="coverage" id="m-coverage" class="form-control bg-dark text-white border-secondary" placeholder="e.g. 100">
            </div>
            <div class="col-md-4 form-group">
              <label style="color:#94a3b8;">Coverage Unit</label>
              <input type="text" name="coverage_unit" id="m-coverage-unit" class="form-control bg-dark text-white border-secondary" placeholder="e.g. sq ft">
            </div>
            <div class="col-md-4 form-group">
              <label style="color:#94a3b8;">Default Waste Factor %</label>
              <input type="number" step="0.1" name="waste_factor_pct" id="m-waste" class="form-control bg-dark text-white border-secondary" value="10">
            </div>
            <div class="col-md-8 form-group">
              <label style="color:#94a3b8;">Description</label>
              <textarea name="description" id="m-description" rows="2" class="form-control bg-dark text-white border-secondary"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,0.08);">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-roofiq"><i class="fas fa-save mr-1"></i>Save Material</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openMaterialModal(id, data) {
  data = data || {};
  document.getElementById('material-id').value    = id || 0;
  document.getElementById('m-sku').value          = data.sku         || '';
  document.getElementById('m-name').value         = data.name        || '';
  document.getElementById('m-category').value     = data.category    || '';
  document.getElementById('m-subcategory').value  = data.subcategory || '';
  document.getElementById('m-rooftype').value     = data.roof_type   || 'Both';
  document.getElementById('m-manufacturer').value = data.manufacturer|| '';
  document.getElementById('m-unit').value         = data.unit         || 'SQ';
  document.getElementById('m-cost').value         = data.unit_cost   || '';
  document.getElementById('m-coverage').value     = data.coverage     || '';
  document.getElementById('m-coverage-unit').value= data.coverage_unit|| 'sq ft';
  document.getElementById('m-waste').value        = data.waste_factor_pct !== undefined ? data.waste_factor_pct : 10;
  document.getElementById('m-description').value  = data.description  || '';

  document.getElementById('modalMaterialTitle').innerHTML =
    '<i class="fas fa-boxes mr-2" style="color:#00d4ff;"></i>' + (id ? 'Edit Material' : 'Add Material');
  $('#modalMaterial').modal('show');
}
</script>

<?php
page_content_footer();
include_page_footer();
?>
