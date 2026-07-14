<?php
/**
 * QuickBill POS - User Model
 */

require_once APP_PATH . '/Models/UserModel.php';

class AuthController extends Controller {

    public function __construct() {
        parent::__construct();
    }

    protected function checkAuth() {
        // No-op for authentication controller to prevent redirect loop
    }

    public function login() {
        // Already logged in?
        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . APP_URL . '/dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processLogin();
            return;
        }

        $expired = isset($_GET['expired']);
        $this->view->render('auth/login', ['expired' => $expired], 'auth');
    }

    private function processLogin() {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            $this->view->render('auth/login', ['error' => 'Security token expired. Please try again.'], 'auth');
            return;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->view->render('auth/login', ['error' => 'Username and password are required.'], 'auth');
            return;
        }

        // Find user
        $user = $this->db->fetchOne(
            "SELECT u.*, r.name AS role_name, b.name AS branch_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN branches b ON b.id = u.branch_id
             WHERE u.username = ? AND u.company_id = 1",
            [$username]
        );

        if (!$user) {
            $this->logFailedAttempt($username);
            $this->view->render('auth/login', ['error' => 'Invalid username or password.'], 'auth');
            return;
        }

        // Check account status
        if (!$user['is_active']) {
            $this->view->render('auth/login', ['error' => 'Your account has been deactivated. Contact administrator.'], 'auth');
            return;
        }

        // Check lockout
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $minutes = ceil((strtotime($user['locked_until']) - time()) / 60);
            $this->view->render('auth/login', ['error' => "Account locked. Try again in {$minutes} minute(s)."], 'auth');
            return;
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $attempts = $user['login_attempts'] + 1;
            if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', strtotime('+' . LOCKOUT_MINUTES . ' minutes'));
                $this->db->execute(
                    "UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?",
                    [$attempts, $lockedUntil, $user['id']]
                );
                $this->view->render('auth/login', ['error' => "Too many failed attempts. Account locked for " . LOCKOUT_MINUTES . " minutes."], 'auth');
            } else {
                $this->db->execute("UPDATE users SET login_attempts = ? WHERE id = ?", [$attempts, $user['id']]);
                $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
                $this->view->render('auth/login', ['error' => "Invalid password. {$remaining} attempt(s) remaining."], 'auth');
            }
            return;
        }

        // Successful login
        $this->db->execute(
            "UPDATE users SET login_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
            [$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $user['id']]
        );

        // Load permissions
        $perms = $this->db->fetchAll(
            "SELECT module, sub_module, can_view, can_create, can_edit, can_delete, can_print, can_export, can_approve
             FROM permissions WHERE role_id = ?",
            [$user['role_id']]
        );

        $permMap = [];
        foreach ($perms as $p) {
            $key = $p['module'] . ($p['sub_module'] ? '.' . $p['sub_module'] : '');
            $permMap[$key] = $p;
        }

        // Regenerate session to prevent fixation
        session_regenerate_id(true);
        CSRF::refresh();

        $_SESSION['user_id']     = $user['id'];
        $_SESSION['company_id']  = $user['company_id'];
        $_SESSION['branch_id']   = $user['branch_id'] ?? 1;
        $_SESSION['role_id']     = $user['role_id'];
        $_SESSION['username']    = $user['username'];
        $_SESSION['user_name']   = $user['name'];
        $_SESSION['role_name']   = $user['role_name'];
        $_SESSION['branch_name'] = $user['branch_name'];
        $_SESSION['permissions'] = $permMap;
        $_SESSION['last_activity'] = time();

        // Audit log
        $this->db->execute(
            "INSERT INTO audit_logs (company_id, user_id, action, module, ip_address, user_agent, notes)
             VALUES (?, ?, 'LOGIN', 'auth', ?, ?, ?)",
            [
                $user['company_id'],
                $user['id'],
                $_SERVER['REMOTE_ADDR'] ?? '',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'Successful login'
            ]
        );

        header('Location: ' . APP_URL . '/dashboard');
        exit;
    }

    public function logout() {
        if (!empty($_SESSION['user_id'])) {
            $this->db->execute(
                "INSERT INTO audit_logs (company_id, user_id, action, module, ip_address, notes)
                 VALUES (?, ?, 'LOGOUT', 'auth', ?, 'User logout')",
                [$_SESSION['company_id'] ?? 1, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? '']
            );
        }
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/auth/login');
        exit;
    }

    private function logFailedAttempt($username) {
        $this->db->execute(
            "INSERT INTO audit_logs (company_id, user_id, action, module, ip_address, notes)
             VALUES (1, NULL, 'LOGIN_FAIL', 'auth', ?, ?)",
            [$_SERVER['REMOTE_ADDR'] ?? '', "Failed login: {$username}"]
        );
    }
}
