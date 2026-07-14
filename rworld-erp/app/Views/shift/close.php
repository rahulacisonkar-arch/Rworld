<?php
/**
 * Shift Close Register View
 */
?>
<div class="row justify-content-center">
    <div class="col-md-9 col-lg-8">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-lock mr-2 text-danger"></i>Reconcile &amp; Close Till Shift</h1>
            <div class="page-subtitle">End session for user <?= htmlspecialchars($_SESSION['user']['username'] ?? '') ?> and count register drawer cash.</div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <!-- Expected Summary Board -->
                <div class="qb-card h-100 shadow-md">
                    <div class="qb-card-header bg-gradient-dark text-white" style="background:#1e293b !important">
                        <span class="qb-card-title text-white"><i class="fas fa-calculator mr-1"></i>Expected Sales Summary</span>
                    </div>
                    <div class="qb-card-body p-0">
                        <table class="table table-hover table-striped mb-0 text-dark">
                            <tbody>
                                <tr>
                                    <td><i class="fas fa-wallet mr-2 text-muted"></i>Opening Cash Bank</td>
                                    <td class="text-right font-weight-bold">$<?= number_format($shift['opening_cash'], 2) ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-cash-register mr-2 text-success"></i>Cash Sales Collected</td>
                                    <td class="text-right font-weight-bold text-success">+$<?= number_format($expectedCashSales, 2) ?></td>
                                </tr>
                                <tr class="bg-light font-weight-bold">
                                    <td class="text-primary"><i class="fas fa-coins mr-2"></i>Expected Cash in Drawer</td>
                                    <td class="text-right text-primary">$<?= number_format($expectedCashDrawer, 2) ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-credit-card mr-2 text-info"></i>Card Payments Summary</td>
                                    <td class="text-right font-weight-bold text-info">$<?= number_format($expectedCardSales, 2) ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-mobile-alt mr-2 text-warning"></i>UPI / Digital Payments</td>
                                    <td class="text-right font-weight-bold text-warning">$<?= number_format($expectedUpiSales, 2) ?></td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-file-invoice-dollar mr-2 text-secondary"></i>Credit Sales / Balance Due</td>
                                    <td class="text-right font-weight-bold text-secondary">$<?= number_format($expectedCreditSales, 2) ?></td>
                                </tr>
                                <tr class="font-weight-bold" style="font-size:1.15rem">
                                    <td>Total Shift Sales Volume</td>
                                    <td class="text-right text-dark">$<?= number_format($totalSales, 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <!-- Cashier Count form -->
                <div class="qb-card shadow-md h-100">
                    <div class="qb-card-header bg-gradient-primary text-white" style="background: linear-gradient(135deg, #ef4444, #f87171) !important">
                        <span class="qb-card-title text-white"><i class="fas fa-balance-scale mr-1"></i>Actual Drawer Counts</span>
                    </div>
                    <div class="qb-card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">

                            <div class="form-group mb-3">
                                <label class="form-label font-weight-bold">Actual Drawer Cash Counted ($) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                                    <input type="number" name="actual_cash" id="actualCash" class="form-control form-control-lg font-weight-bold text-dark" style="font-size:1.3rem" value="<?= number_format($expectedCashDrawer, 2, '.', '') ?>" min="0" step="0.01" required oninput="calcVariance()">
                                </div>
                                <small class="form-text text-muted">Count all cash and coins inside the drawer (including opening bank).</small>
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Actual Cards Counted ($)</label>
                                <input type="number" name="actual_card" class="form-control" value="<?= number_format($expectedCardSales, 2, '.', '') ?>" min="0" step="0.01">
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Actual UPI Counted ($)</label>
                                <input type="number" name="actual_upi" class="form-control" value="<?= number_format($expectedUpiSales, 2, '.', '') ?>" min="0" step="0.01">
                            </div>

                            <div class="form-group mb-3">
                                <label class="form-label">Discrepancy / Variance</label>
                                <div class="alert alert-secondary py-2 px-3 mb-0 font-weight-bold text-center" id="varianceBox" style="font-size:1.2rem">
                                    $0.00 (Balanced)
                                </div>
                            </div>

                            <div class="form-group mb-4">
                                <label class="form-label">Shift Closure Notes / Discrepancy Remarks</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Explain any shortages or overages..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-danger btn-block btn-lg shadow-md" style="height:50px; font-weight:600; border-radius:8px">
                                <i class="fas fa-lock-open mr-2"></i> Reconcile &amp; Close Register
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const expectedCash = <?= (float)$expectedCashDrawer ?>;
function calcVariance() {
    const inputVal = parseFloat(document.getElementById('actualCash').value) || 0;
    const diff = inputVal - expectedCash;
    const box = document.getElementById('varianceBox');
    
    if (diff === 0) {
        box.className = 'alert alert-success py-2 px-3 mb-0 font-weight-bold text-center';
        box.textContent = '$0.00 (Balanced)';
    } else if (diff < 0) {
        box.className = 'alert alert-danger py-2 px-3 mb-0 font-weight-bold text-center';
        box.textContent = '-$' + Math.abs(diff).toFixed(2) + ' (Cash Short)';
    } else {
        box.className = 'alert alert-warning py-2 px-3 mb-0 font-weight-bold text-center';
        box.textContent = '+$' + diff.toFixed(2) + ' (Cash Over)';
    }
}
window.addEventListener('DOMContentLoaded', calcVariance);
</script>
