<!-- Expense Create Form -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-wallet mr-2 text-primary"></i>Record Expense</h1>
        <div class="page-subtitle">Add a business operational expense</div>
    </div>
    <a href="<?= APP_URL ?>/expense" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="qb-card">
            <div class="qb-card-body">
                <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">

                    <div class="form-group">
                        <label class="form-label">Expense Category <span class="text-danger">*</span></label>
                        <select name="expense_category_id" class="form-control" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="form-label">Amount ($) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required placeholder="0.00">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="form-label">Expense Date</label>
                            <input type="date" name="doc_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Remarks / Description</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Explain the expense details here…"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save mr-1"></i> Save Expense
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
