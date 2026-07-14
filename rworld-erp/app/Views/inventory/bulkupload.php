<?php
/**
 * QuickBill POS - Bulk Inventory Upload View
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-file-excel mr-2 text-primary"></i>Bulk Inventory Upload</h1>
        <div class="page-subtitle">Import fabric catalogs, batches, categories, and opening store stock balances</div>
    </div>
</div>

<div class="row">
    <!-- Upload Panel -->
    <div class="col-md-5">
        <div class="qb-card mb-4">
            <div class="qb-card-header">
                <span class="qb-card-title"><i class="fas fa-upload text-primary mr-1"></i>Upload Spreadsheet (CSV)</span>
            </div>
            <div class="qb-card-body">
                <form action="<?= APP_URL ?>/inventory/processUpload" method="POST" enctype="multipart/form-data">
                    <?= View::csrfField() ?>

                    <div class="form-group mb-4">
                        <label class="form-label font-weight-600">Choose CSV File</label>
                        <input type="file" class="form-control-file p-2" name="csv_file" accept=".csv" required style="border:1px dashed #ced4da; border-radius:8px; width:100%">
                        <small class="form-text text-muted mt-2">Make sure your file is saved as a <strong>CSV (Comma Delimited)</strong> spreadsheet.</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg" style="border-radius:10px; font-weight:700">
                        <i class="fas fa-upload mr-1"></i>Process &amp; Import Catalog
                    </button>
                </form>
            </div>
        </div>

        <div class="qb-card">
            <div class="qb-card-header">
                <span class="qb-card-title"><i class="fas fa-bolt text-warning mr-1"></i>Intelligent Auto-Mapping</span>
            </div>
            <div class="qb-card-body" style="font-size:13px; line-height:1.6">
                <p class="mb-0 text-muted">The system automatically matches headers in your file. If no headers are found, it maps directly to standard order: <em>Store Name, Stock No, Description, Batch No, Category, Cost Price, Qty</em>.</p>
            </div>
        </div>
    </div>

    <!-- Instructions / Format Template Panel -->
    <div class="col-md-7">
        <div class="qb-card mb-4">
            <div class="qb-card-header">
                <span class="qb-card-title"><i class="fas fa-info-circle text-primary mr-1"></i>CSV Spreadsheet Format template</span>
            </div>
            <div class="qb-card-body">
                <p class="text-muted" style="font-size:13.5px">Ensure your columns map exactly to the structure shown below:</p>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-striped font-monospace text-center mb-3" style="font-size:11px; white-space:nowrap">
                        <thead class="bg-light text-muted">
                            <tr>
                                <th>Store Names</th>
                                <th>Stock No</th>
                                <th>Item Description</th>
                                <th>Batch No.</th>
                                <th>Category</th>
                                <th>Cost Price</th>
                                <th>Closing Bal.Qty</th>
                                <th>Closing Bal.Val</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-left text-primary">artee Burlington 803</td>
                                <td class="font-weight-600">SDM-ANT1-STN</td>
                                <td class="text-left">Pannel 52 X 96 Sto Silk Dupio</td>
                                <td>NO BATCH</td>
                                <td>PANL</td>
                                <td class="text-right">$0.10</td>
                                <td>1.00</td>
                                <td class="text-right">$0.10</td>
                            </tr>
                            <tr>
                                <td class="text-left text-primary">artee Burlington 803</td>
                                <td class="font-weight-600">PPC-MINI-UCR#</td>
                                <td class="text-left"># Mini Checks #21 Blue/Cream P</td>
                                <td>08TY021-031002</td>
                                <td>POLY</td>
                                <td class="text-right">$0.10</td>
                                <td>1.00</td>
                                <td class="text-right">$0.10</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h6 class="font-weight-700 mt-4 text-dark" style="font-size:14px">Dynamic Import Logic:</h6>
                <ul class="text-muted pl-3" style="font-size:13px; line-height:1.6">
                    <li><strong>Store Names</strong>: Automatically matches or generates a store branch (e.g. creating `Burlington 803` branch and logging stock there).</li>
                    <li><strong>Batch No.</strong>: Automatically activates batch-tracking for the product and records opening batch quantities in `batch_master`.</li>
                    <li><strong>Category</strong>: Automatically creates missing category groups dynamically (e.g. `PANL`, `POLY`, `XFAB`).</li>
                    <li><strong>Stock Ledger</strong>: Automatically logs opening quantities directly under the resolved store branch.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.font-monospace { font-family: 'Courier New', monospace; }
.font-weight-600 { font-weight: 600; }
.font-weight-700 { font-weight: 700; }
</style>
