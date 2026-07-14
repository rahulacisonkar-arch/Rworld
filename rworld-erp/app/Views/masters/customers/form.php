<?php
/**
 * Customer — Production Create/Edit Form View
 */
$isEdit = !empty($record);
?>
<div class="page-header">
    <h1 class="page-title"><i class="fas fa-user-plus mr-2 text-primary"></i><?= $isEdit ? 'Edit Customer' : 'New Customer' ?></h1>
    <div class="page-subtitle"><?= $isEdit ? 'Update customer details and credit settings' : 'Register a new customer account' ?></div>
</div>

<form method="POST" action="">
    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">

    <div class="row">
        <div class="col-md-8">
            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-info-circle mr-1"></i>Basic Information</span></div>
                <div class="qb-card-body">
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($record['name'] ?? '') ?>" required placeholder="Customer full name">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Alias / Short Name</label>
                            <input type="text" name="alias" class="form-control" value="<?= htmlspecialchars($record['alias'] ?? '') ?>" placeholder="Optional">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="form-label">Phone 1</label>
                            <input type="text" name="phone1" class="form-control" value="<?= htmlspecialchars($record['phone1'] ?? '') ?>" placeholder="Primary phone">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Phone 2</label>
                            <input type="text" name="phone2" class="form-control" value="<?= htmlspecialchars($record['phone2'] ?? '') ?>" placeholder="Secondary phone">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($record['email'] ?? '') ?>" placeholder="customer@email.com">
                        </div>
                    </div>
                </div>
            </div>

            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-map-marker-alt mr-1"></i>Address</span></div>
                <div class="qb-card-body">
                    <div class="form-group">
                        <label class="form-label">Street Address</label>
                        <input type="text" name="address1" class="form-control" value="<?= htmlspecialchars($record['address1'] ?? '') ?>" placeholder="Street address">
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($record['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($record['state'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">ZIP Code</label>
                            <input type="text" name="pin" class="form-control" value="<?= htmlspecialchars($record['pin'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($record['country'] ?? 'USA') ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-percent mr-1"></i>Tax &amp; Exemption</span></div>
                <div class="qb-card-body">
                    <div class="form-group mb-3">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="is_tax_exempt" class="custom-control-input" id="isTaxExemptChk" value="1" <?= ($record['is_tax_exempt'] ?? 0) ? 'checked' : '' ?>>
                            <label class="custom-control-label font-weight-600" for="isTaxExemptChk">Tax Exempt Customer</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Resale Certificate No</label>
                        <input type="text" name="resale_certificate_no" class="form-control" value="<?= htmlspecialchars($record['resale_certificate_no'] ?? '') ?>" placeholder="e.g. ST-120-XXXX">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Exemption Reason</label>
                        <select name="tax_exempt_reason" class="form-control">
                            <option value="">-- Select Reason --</option>
                            <option value="resale" <?= ($record['tax_exempt_reason'] ?? '') === 'resale' ? 'selected' : '' ?>>Resale / Wholesale</option>
                            <option value="government" <?= ($record['tax_exempt_reason'] ?? '') === 'government' ? 'selected' : '' ?>>Government Agency</option>
                            <option value="nonprofit" <?= ($record['tax_exempt_reason'] ?? '') === 'nonprofit' ? 'selected' : '' ?>>Non-Profit Organization</option>
                            <option value="other" <?= ($record['tax_exempt_reason'] ?? '') === 'other' ? 'selected' : '' ?>>Other Exempt Entity</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cert Expiration Date</label>
                        <input type="date" name="resale_cert_expiry" class="form-control" value="<?= htmlspecialchars($record['resale_cert_expiry'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-credit-card mr-1"></i>Credit Settings</span></div>
                <div class="qb-card-body">
                    <div class="form-group">
                        <label class="form-label">Credit Limit ($)</label>
                        <input type="number" name="credit_limit" class="form-control" min="0" step="0.01" value="<?= $record['credit_limit'] ?? '0.00' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Credit Days</label>
                        <input type="number" name="credit_days" class="form-control" min="0" value="<?= $record['credit_days'] ?? '0' ?>">
                    </div>
                    <?php if ($isEdit): ?>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-control">
                            <option value="1" <?= ($record['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= ($record['is_active'] ?? 1) == 0 ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex" style="gap:8px">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="fas fa-save mr-1"></i> <?= $isEdit ? 'Save Changes' : 'Create Customer' ?>
                </button>
                <a href="<?= APP_URL ?>/customer" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>
