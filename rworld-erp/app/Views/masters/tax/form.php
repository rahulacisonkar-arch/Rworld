<!-- Tax Master Create Form -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-percent mr-2 text-primary"></i>New Tax Type</h1>
    </div>
    <a href="<?= APP_URL ?>/taxmaster" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="qb-card">
            <div class="qb-card-body">
                <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="form-label">Tax Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" required placeholder="e.g. TX8">
                        </div>
                        <div class="form-group col-md-8">
                            <label class="form-label">Tax Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Texas State Tax 8.25%">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tax Region</label>
                        <select name="tax_region" class="form-control">
                            <option value="US_SALES_TAX">US Sales Tax</option>
                            <option value="STATE_TAX">State Tax</option>
                            <option value="COUNTY_TAX">County Tax</option>
                            <option value="CITY_TAX">City Tax</option>
                            <option value="EXEMPT">Tax Exempt</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Price Inclusive</label>
                        <select name="is_inclusive" class="form-control">
                            <option value="0">Exclusive (added on top of price)</option>
                            <option value="1">Inclusive (tax included in price)</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save mr-1"></i> Create Tax Type
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
