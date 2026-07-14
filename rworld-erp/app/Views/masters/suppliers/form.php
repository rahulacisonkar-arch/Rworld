<?php $isEdit = !empty($record); ?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-industry mr-2 text-warning"></i><?= $isEdit ? 'Edit Supplier' : 'New Supplier' ?></h1>
        <div class="page-subtitle"><?= $isEdit ? 'Update supplier details' : 'Register a new supplier' ?></div>
    </div>
</div>
<form method="POST">
    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">
    <div class="row">
        <div class="col-md-8">
            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title">Supplier Information</span></div>
                <div class="qb-card-body">
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label class="form-label">Supplier Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($record['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Alias</label>
                            <input type="text" name="alias" class="form-control" value="<?= htmlspecialchars($record['alias'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone1" class="form-control" value="<?= htmlspecialchars($record['phone1'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Phone 2</label>
                            <input type="text" name="phone2" class="form-control" value="<?= htmlspecialchars($record['phone2'] ?? '') ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($record['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="form-label">Address</label>
                            <input type="text" name="address1" class="form-control" value="<?= htmlspecialchars($record['address1'] ?? '') ?>">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($record['city'] ?? '') ?>">
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($record['state'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($record['country'] ?? 'USA') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title">Credit Terms</span></div>
                <div class="qb-card-body">
                    <div class="form-group">
                        <label class="form-label">Credit Limit ($)</label>
                        <input type="number" name="credit_limit" class="form-control" min="0" step="0.01" value="<?= $record['credit_limit'] ?? '0.00' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Credit Days</label>
                        <input type="number" name="credit_days" class="form-control" min="0" value="<?= $record['credit_days'] ?? '30' ?>">
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
                <button type="submit" class="btn btn-warning flex-fill text-white"><i class="fas fa-save mr-1"></i> <?= $isEdit ? 'Save Changes' : 'Create Supplier' ?></button>
                <a href="<?= APP_URL ?>/supplier" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>
