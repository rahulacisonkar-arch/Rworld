<!-- User Create Form -->
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-user-plus mr-2 text-primary"></i>New User Account</h1>
    </div>
    <a href="<?= APP_URL ?>/user" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="qb-card">
            <div class="qb-card-body">
                <form method="POST">
                    <input type="hidden" name="_csrf" value="<?= CSRF::generate() ?>">

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="Employee full name">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required placeholder="login username" autocomplete="off">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="user@arteefabrics.com">
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="6" placeholder="Min 6 characters" autocomplete="new-password">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role_id" class="form-control">
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Branch</label>
                        <select name="branch_id" class="form-control">
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-user-plus mr-1"></i> Create User Account
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
