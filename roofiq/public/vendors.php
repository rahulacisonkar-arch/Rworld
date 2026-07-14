<?php
/**
 * ROOFIQ — Vendors Management
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
            'name'         => trim(isset($_POST['name'])         ? $_POST['name']         : ''),
            'contact_name' => trim(isset($_POST['contact_name']) ? $_POST['contact_name'] : ''),
            'email'        => trim(isset($_POST['email'])        ? $_POST['email']        : ''),
            'phone'        => trim(isset($_POST['phone'])        ? $_POST['phone']        : ''),
            'website'      => trim(isset($_POST['website'])      ? $_POST['website']      : ''),
            'specialty'    => trim(isset($_POST['specialty'])    ? $_POST['specialty']    : ''),
            'is_preferred' => isset($_POST['is_preferred'])      ? 1 : 0,
            'is_active'    => 1,
        );
        if ($data['name']) {
            if ($id) {
                db_update('vendors', $data, 'id=?', array($id));
                $msg = 'Vendor updated.';
            } else {
                db_insert('vendors', $data);
                $msg = 'Vendor added.';
            }
        }
    } elseif ($action === 'delete') {
        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        if ($id) {
            db_update('vendors', array('is_active' => 0), 'id=?', array($id));
            $msg = 'Vendor deactivated.';
        }
    }
}

$vendors = db_fetch_all("SELECT * FROM vendors WHERE is_active=1 ORDER BY is_preferred DESC, name");

include_page_header('Vendors');
page_content_header('Vendor Management', array('Vendors' => ''));
?>

<?php if ($msg): ?>
  <div class="alert alert-success"><?php echo e($msg); ?></div>
<?php endif; ?>

<div class="row mb-3">
  <div class="col-12 text-right">
    <button class="btn btn-roofiq" data-toggle="modal" data-target="#modalVendor" onclick="openVendorModal(0)">
      <i class="fas fa-plus mr-1"></i>Add Vendor
    </button>
  </div>
</div>

<div class="row">
  <?php foreach ($vendors as $v): ?>
    <div class="col-md-4 mb-3">
      <div class="vendor-card" style="cursor:default;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
          <div style="font-weight:700;font-size:0.95rem;color:#fff;"><?php echo e($v['name']); ?></div>
          <?php if ($v['is_preferred']): ?>
            <span class="preferred-badge">⭐ Preferred</span>
          <?php endif; ?>
        </div>
        <?php if ($v['contact_name']): ?>
          <div style="font-size:0.8rem;color:#e2e8f0;margin-bottom:4px;"><i class="fas fa-user mr-1" style="color:#94a3b8;"></i><?php echo e($v['contact_name']); ?></div>
        <?php endif; ?>
        <?php if ($v['phone']): ?>
          <div style="font-size:0.8rem;color:#00d4ff;margin-bottom:4px;"><i class="fas fa-phone mr-1"></i><?php echo e($v['phone']); ?></div>
        <?php endif; ?>
        <?php if ($v['email']): ?>
          <div style="font-size:0.78rem;color:#94a3b8;margin-bottom:4px;"><i class="fas fa-envelope mr-1"></i><?php echo e($v['email']); ?></div>
        <?php endif; ?>
        <?php if ($v['specialty']): ?>
          <div style="font-size:0.75rem;color:#94a3b8;margin-top:8px;border-top:1px solid rgba(255,255,255,0.06);padding-top:8px;"><?php echo e($v['specialty']); ?></div>
        <?php endif; ?>
        <div style="display:flex;gap:6px;margin-top:10px;">
          <button onclick="openVendorModal(<?php echo $v['id']; ?>,<?php echo j($v); ?>)"
                  class="btn btn-xs btn-outline-info">Edit</button>
          <form method="POST" style="display:inline;">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?php echo e($v['id']); ?>">
            <button type="submit" class="btn btn-xs btn-outline-danger" onclick="return confirm('Deactivate this vendor?');">Remove</button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Vendor Modal -->
<div class="modal fade" id="modalVendor">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalVendorTitle"><i class="fas fa-truck mr-2" style="color:#a78bfa;"></i>Vendor</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form method="POST">
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" id="vendor-id" value="0">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 form-group">
              <label>Company Name *</label>
              <input type="text" name="name" id="v-name" class="form-control" required>
            </div>
            <div class="col-md-6 form-group">
              <label>Contact Name</label>
              <input type="text" name="contact_name" id="v-contact" class="form-control">
            </div>
            <div class="col-md-6 form-group">
              <label>Phone</label>
              <input type="text" name="phone" id="v-phone" class="form-control">
            </div>
            <div class="col-md-6 form-group">
              <label>Email</label>
              <input type="email" name="email" id="v-email" class="form-control">
            </div>
            <div class="col-md-6 form-group">
              <label>Website</label>
              <input type="text" name="website" id="v-website" class="form-control">
            </div>
            <div class="col-md-6 form-group">
              <label>Specialty</label>
              <input type="text" name="specialty" id="v-specialty" class="form-control" placeholder="e.g. Commercial TPO, Residential Shingles">
            </div>
            <div class="col-md-12 form-group">
              <div class="form-check">
                <input type="checkbox" name="is_preferred" id="v-preferred" class="form-check-input">
                <label class="form-check-label" for="v-preferred" style="color:#e2e8f0;">Mark as Preferred Vendor</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-roofiq"><i class="fas fa-save mr-1"></i>Save Vendor</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openVendorModal(id, data) {
  data = data || {};
  document.getElementById('vendor-id').value    = id || 0;
  document.getElementById('v-name').value       = data.name        || '';
  document.getElementById('v-contact').value    = data.contact_name|| '';
  document.getElementById('v-phone').value      = data.phone       || '';
  document.getElementById('v-email').value      = data.email       || '';
  document.getElementById('v-website').value    = data.website     || '';
  document.getElementById('v-specialty').value  = data.specialty   || '';
  document.getElementById('v-preferred').checked= data.is_preferred == 1;
  document.getElementById('modalVendorTitle').innerHTML =
    '<i class="fas fa-truck mr-2" style="color:#a78bfa;"></i>' + (id ? 'Edit Vendor' : 'Add Vendor');
  $('#modalVendor').modal('show');
}
</script>

<?php
page_content_footer();
include_page_footer();
?>
