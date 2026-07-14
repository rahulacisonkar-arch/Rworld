<!-- Payment Create Form -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-hand-holding-usd mr-2 text-primary"></i>Record Customer Payment</h1>
    </div>
    <a href="<?= APP_URL ?>/payment" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="qb-card">
            <div class="qb-card-body">
                <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">

                    <div class="form-group">
                        <label class="form-label">Customer</label>
                        <select name="customer_id" class="form-control">
                            <option value="">-- Walk-in Customer --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= $c['code'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-7">
                            <label class="form-label">Amount Received ($) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required placeholder="0.00">
                        </div>
                        <div class="form-group col-md-5">
                            <label class="form-label">Payment Date</label>
                            <input type="date" name="doc_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Payment reference, cheque no, etc."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save mr-1"></i> Save Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
