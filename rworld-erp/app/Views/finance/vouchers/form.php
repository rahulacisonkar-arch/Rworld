<!-- Voucher Create Form -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>New Journal Voucher</h1>
        <div class="page-subtitle">Post manual journal adjustments to general ledger accounts</div>
    </div>
    <a href="<?= APP_URL ?>/voucher" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="qb-card">
            <div class="qb-card-body">
                <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="form-label">Posting Date</label>
                            <input type="date" name="doc_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="form-label">Voucher Type</label>
                            <select name="voucher_type" class="form-control">
                                <option value="journal">Journal</option>
                                <option value="receipt">Receipt</option>
                                <option value="payment">Payment</option>
                                <option value="contra">Contra</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Total Amount ($) <span class="text-danger">*</span></label>
                        <input type="number" name="total_amount" class="form-control" min="0.01" step="0.01" required placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Narration / Remarks</label>
                        <textarea name="narration" class="form-control" rows="3" required placeholder="Describe the purpose of this manual entry…"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save mr-1"></i> Save Voucher
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
