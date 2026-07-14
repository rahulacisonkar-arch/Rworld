<?php $isEdit = !empty($record); ?>
<div class="page-header"><h1 class="page-title"><i class="fas fa-box mr-2 text-primary"></i><?= $isEdit ? 'Edit Item' : 'New Item' ?></h1></div>
<form method="POST">
    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">
    <div class="row">
        <div class="col-md-8">
            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title">Item Details</span></div>
                <div class="qb-card-body">
                    <div class="form-group">
                        <label class="form-label">Description / Name <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($record['description'] ?? '') ?>" required placeholder="Full item description">
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="form-label">Barcode</label>
                            <input type="text" name="barcode" class="form-control" value="<?= htmlspecialchars($record['barcode'] ?? '') ?>" id="barcodeInput" placeholder="Scan or enter barcode">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label">Category</label>
                            <select name="cat1_id" class="form-control">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($record['cat1_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="form-label">Unit of Measure</label>
                            <select name="unit_id" class="form-control">
                                <option value="">-- Select Unit --</option>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($record['unit_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label">Maintain Inventory</label>
                            <select name="maintain_inventory" class="form-control">
                                <option value="1" <?= ($record['maintain_inventory'] ?? 1) ? 'selected' : '' ?>>Yes</option>
                                <option value="0" <?= !($record['maintain_inventory'] ?? 1) ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title">Pricing</span></div>
                <div class="qb-card-body">
                    <div class="form-group">
                        <label class="form-label">Sale Price 1 ($) <span class="text-danger">*</span></label>
                        <input type="number" name="price1" class="form-control" min="0" step="0.01" value="<?= $record['price1'] ?? '0.00' ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Sale Price 2 ($)</label>
                        <input type="number" name="price2" class="form-control" min="0" step="0.01" value="<?= $record['price2'] ?? '0.00' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">MRP ($)</label>
                        <input type="number" name="mrp" class="form-control" min="0" step="0.01" value="<?= $record['mrp'] ?? '0.00' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cost Price ($)</label>
                        <input type="number" name="cost_price" class="form-control" min="0" step="0.01" value="<?= $record['cost_price'] ?? '0.00' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reorder Level</label>
                        <input type="number" name="reorder_level" class="form-control" min="0" step="0.01" value="<?= $record['reorder_level'] ?? '0' ?>">
                    </div>
                    <?php if ($isEdit): ?>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-control">
                            <option value="1" <?= ($record['is_active'] ?? 1) ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= !($record['is_active'] ?? 1) ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex" style="gap:8px">
                <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-save mr-1"></i> <?= $isEdit ? 'Save Changes' : 'Create Item' ?></button>
                <a href="<?= APP_URL ?>/item" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>
