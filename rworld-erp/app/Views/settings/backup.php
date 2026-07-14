<!-- Backup & Restore Page -->
<div class="page-header">
    <h1 class="page-title"><i class="fas fa-database mr-2 text-primary"></i>Backup & Restore</h1>
    <div class="page-subtitle">Protect your data with database snapshots and point-in-time recovery</div>
</div>

<div class="row">
    <div class="col-md-5">
        <div class="qb-card mb-4">
            <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-download mr-1"></i>Create Backup</span></div>
            <div class="qb-card-body">
                <p class="text-muted small">Create a full SQL snapshot of the database right now.</p>
                <form method="POST" action="<?= APP_URL ?>/backup/generate">
                    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="fas fa-download mr-1"></i> Download Backup Now
                    </button>
                </form>
            </div>
        </div>

        <div class="qb-card mb-4">
            <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-upload mr-1"></i>Restore from File</span></div>
            <div class="qb-card-body">
                <p class="text-danger small font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i>Warning: This will overwrite existing data.</p>
                <form method="POST" action="<?= APP_URL ?>/backup/restore" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">
                    <div class="form-group">
                        <label class="form-label">Select SQL Backup File</label>
                        <input type="file" name="backup_file" class="form-control-file" accept=".sql,.gz" required>
                    </div>
                    <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('CAUTION: Restoring will replace all current data. Are you absolutely sure?')">
                        <i class="fas fa-upload mr-1"></i> Restore Database
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="qb-card">
            <div class="qb-card-header"><span class="qb-card-title"><i class="fas fa-history mr-1"></i>Backup History</span></div>
            <div class="qb-card-body p-0">
                <table class="qb-table table-sm">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Created At</th>
                            <th class="text-center">Download</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($backups)): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">
                            <i class="fas fa-archive fa-2x mb-2 d-block"></i>No backups on record.
                        </td></tr>
                    <?php else: foreach ($backups as $bk): ?>
                        <tr>
                            <td><?= htmlspecialchars(basename($bk['filename'])) ?></td>
                            <td><?= number_format($bk['file_size'] / 1024, 1) ?> KB</td>
                            <td><?= date('m/d/Y H:i', strtotime($bk['created_at'])) ?></td>
                            <td class="text-center">
                                <a href="<?= APP_URL ?>/backup/download/<?= $bk['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
