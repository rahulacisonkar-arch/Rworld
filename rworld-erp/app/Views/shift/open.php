<?php
/**
 * Shift Open Register View
 */
?>
<div class="row justify-content-center align-items-center" style="min-height:70vh">
    <div class="col-md-5">
        <div class="qb-card shadow-lg border-0" style="border-radius:15px; overflow:hidden">
            <div class="qb-card-header bg-primary text-white text-center py-4" style="background: linear-gradient(135deg, #4f46e5, #6366f1) !important">
                <div class="mb-2"><i class="fas fa-cash-register fa-3x animate-bounce"></i></div>
                <h3 class="mb-0 font-weight-bold">Start Cash Register Shift</h3>
                <p class="text-white-50 small mb-0">Input starting drawer bank to access checkout features</p>
            </div>
            <div class="qb-card-body p-4 bg-light">
                <form method="POST" action="">
                    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">
                    
                    <div class="form-group text-center mb-4">
                        <label class="form-label font-weight-bold text-dark text-uppercase small tracking-wide">Opening Drawer Balance (USD)</label>
                        <div class="input-group input-group-lg justify-content-center">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white border-right-0 text-muted font-weight-bold">$</span>
                            </div>
                            <input type="number" name="opening_cash" class="form-control border-left-0 font-weight-bold text-center" style="max-width:200px; font-size:1.8rem; height:60px" value="150.00" min="0" step="0.01" required autofocus>
                        </div>
                        <small class="form-text text-muted mt-2">Standard register opening cash is typically $150.00</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg shadow-md" style="border-radius:8px; height:50px; background: linear-gradient(135deg, #4f46e5, #6366f1); border:none; font-weight:600">
                        <i class="fas fa-play mr-2"></i> Open Shift &amp; Start POS
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}
.animate-bounce {
    animation: bounce 2s infinite ease-in-out;
}
</style>
