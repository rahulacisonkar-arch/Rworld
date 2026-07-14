<?php
/**
 * User Management — Production List View
 */
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="page-title"><i class="fas fa-user-shield mr-2 text-primary"></i>User Management</h1>
        <div class="page-subtitle">Control system access, roles, and branch assignments</div>
    </div>
    <a href="<?= APP_URL ?>/user/create" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> New User</a>
</div>

<div class="qb-card">
    <div class="qb-card-body p-0">
        <div class="table-responsive">
            <table class="qb-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records['data'])): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">
                        <i class="fas fa-user-shield fa-2x mb-2 d-block"></i>
                        No users found. <a href="<?= APP_URL ?>/user/create">Create first user.</a>
                    </td></tr>
                <?php else: foreach ($records['data'] as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['username']) ?></strong></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['email'] ?? '—') ?></td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($row['role_name'] ?? 'Staff') ?></span></td>
                        <td><?= htmlspecialchars($row['branch_name'] ?? 'Head Office') ?></td>
                        <td><?= $row['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                        <td class="text-center">
                            <a href="<?= APP_URL ?>/user/edit/<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
