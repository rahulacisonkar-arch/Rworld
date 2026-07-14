<?php
/**
 * UserController — Production User Management controller
 */
class UserController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();
        $page = max(1, (int)$this->get('page', 1));

        $sql = "SELECT u.id, u.username, u.name, u.email, u.is_active,
                       r.name AS role_name, b.name AS branch_name
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
                LEFT JOIN branches b ON b.id = u.branch_id
                WHERE u.company_id=?
                ORDER BY u.username ASC";

        $paged = $this->paginate($sql, [$cid], $page);

        $this->render('settings/users/index', [
            'pageTitle' => 'User Management',
            'records' => $paged,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Administration', 'url' => '#'],
                ['label' => 'User Management', 'url' => APP_URL . '/user'],
            ]
        ]);
    }

    public function create() {
        $cid = $this->getCompanyId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $hash = password_hash($this->post('password'), PASSWORD_DEFAULT);

            $this->db->execute(
                "INSERT INTO users (company_id, branch_id, role_id, username, password_hash, name, email, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
                [
                    $cid,
                    $this->post('branch_id') ?: 1,
                    $this->post('role_id') ?: 1,
                    $this->post('username'),
                    $hash,
                    $this->post('name'),
                    $this->post('email'),
                ]
            );

            $id = $this->db->lastInsertId();
            $this->auditLog('CREATE', 'user', $id, $this->post('username'));
            $this->flash('success', "User '{$this->post('username')}' registered successfully.");
            $this->redirect('user');
        }

        $roles = $this->db->fetchAll("SELECT id, name FROM roles ORDER BY name");
        $branches = $this->db->fetchAll("SELECT id, name FROM branches WHERE company_id=? ORDER BY name", [$cid]);

        $this->render('settings/users/form', [
            'pageTitle' => 'New User',
            'roles' => $roles,
            'branches' => $branches,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'User Management', 'url' => APP_URL . '/user'],
                ['label' => 'New User', 'url' => '#'],
            ]
        ]);
    }
}
