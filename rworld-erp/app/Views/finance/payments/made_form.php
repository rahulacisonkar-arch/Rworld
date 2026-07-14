<!-- Supplier Payment Create Form -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-money-bill-wave mr-2 text-danger"></i>Pay Supplier</h1>
    </div>
    <a href="<?= APP_URL ?>/receipt" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="qb-card">
            <div class="qb-card-body">
                <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">

                    <div class="form-group">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-control" required>
                            <option value="">-- Select Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-7">
                            <label class="form-label">Amount Paid ($) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required placeholder="0.00">
                        </div>
                        <div class="form-group col-md-5">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="doc_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Ref invoice no, cheque, wire details…"></textarea>
                    </div>

                    <button type="submit" class="btn btn-danger btn-block">
                        <i class="fas fa-save mr-1"></i> Record Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
