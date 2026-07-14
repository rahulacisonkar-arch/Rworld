<!-- Ledger Account Create Form -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-book mr-2 text-primary"></i>New Ledger Account</h1>
    </div>
    <a href="<?= APP_URL ?>/ledger" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="qb-card">
            <div class="qb-card-body">
                <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="form-label">Account Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" required placeholder="e.g. 1001">
                        </div>
                        <div class="form-group col-md-8">
                            <label class="form-label">Account Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Cash in Hand">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Account Group</label>
                        <select name="ledger_group_id" class="form-control">
                            <option value="">-- No Group --</option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label class="form-label">Opening Balance ($)</label>
                            <input type="number" name="opening_balance" class="form-control" min="0" step="0.01" value="0.00">
                        </div>
                        <div class="form-group col-md-4">
                            <label class="form-label">Balance Type</label>
                            <select name="opening_bal_type" class="form-control">
                                <option value="Dr">Debit (Dr)</option>
                                <option value="Cr">Credit (Cr)</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save mr-1"></i> Create Account
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
