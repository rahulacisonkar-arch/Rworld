<!-- Settings Page -->
<div class="page-header">
    <h1 class="page-title"><i class="fas fa-cog mr-2 text-primary"></i>System Settings</h1>
    <div class="page-subtitle">Configure global company, fiscal, and operational preferences</div>
</div>

<form method="POST">
    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">

    <div class="row">
        <div class="col-md-6">
            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-building mr-1"></i>Company Information</span></div>
                <div class="qb-card-body">
                    <div class="form-group">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name'] ?? 'Artee Fabrics and Home') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="company_address" class="form-control" rows="2"><?= htmlspecialchars($settings['company_address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="company_phone" class="form-control" value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Website</label>
                        <input type="text" name="company_website" class="form-control" value="<?= htmlspecialchars($settings['company_website'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-dollar-sign mr-1"></i>Financial Settings</span></div>
                <div class="qb-card-body">
                    <div class="form-group">
                        <label class="form-label">Currency</label>
                        <select name="currency" class="form-control">
                            <option value="USD" <?= ($settings['currency'] ?? 'USD') === 'USD' ? 'selected' : '' ?>>USD — US Dollar</option>
                            <option value="EUR" <?= ($settings['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR — Euro</option>
                            <option value="GBP" <?= ($settings['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP — British Pound</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Default Tax Rate (%)</label>
                        <input type="number" name="default_tax_rate" class="form-control" min="0" max="100" step="0.001" value="<?= $settings['default_tax_rate'] ?? '8.25' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Financial Year Start</label>
                        <input type="text" name="fy_start" class="form-control" value="<?= htmlspecialchars($settings['fy_start'] ?? '01-01') ?>" placeholder="MM-DD">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-control">
                            <option value="America/New_York" <?= ($settings['timezone'] ?? 'America/New_York') === 'America/New_York' ? 'selected' : '' ?>>Eastern Time (ET)</option>
                            <option value="America/Chicago" <?= ($settings['timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>Central Time (CT)</option>
                            <option value="America/Denver" <?= ($settings['timezone'] ?? '') === 'America/Denver' ? 'selected' : '' ?>>Mountain Time (MT)</option>
                            <option value="America/Los_Angeles" <?= ($settings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time (PT)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="qb-card mb-4">
                <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-receipt mr-1"></i>Receipt & Print</span></div>
                <div class="qb-card-body">
                    <div class="form-group">
                        <label class="form-label">Receipt Footer</label>
                        <textarea name="receipt_footer" class="form-control" rows="2" placeholder="Thank you for shopping with us!"><?= htmlspecialchars($settings['receipt_footer'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Items Per Page</label>
                        <input type="number" name="items_per_page" class="form-control" min="5" max="100" value="<?= $settings['items_per_page'] ?? '25' ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-right">
        <button type="submit" class="btn btn-primary btn-lg px-5">
            <i class="fas fa-save mr-2"></i> Save Settings
        </button>
    </div>
</form>
