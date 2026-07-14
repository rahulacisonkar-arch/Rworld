<?php
/**
 * QuickBill POS - Base Controller
 */

abstract class Controller {

    protected $db;
    protected $view;

    public function __construct() {
        $this->db   = Database::getInstance();
        $this->view = new View();
        $this->checkAuth();
    }

    /**
     * Enforce authentication on all controllers (override in AuthController)
     */
    protected function checkAuth() {
        if (empty($_SESSION['user_id'])) {
            $this->redirect('auth/login');
        }
    }

    /**
     * Render a view with data
     */
    protected function render($viewPath, $data = [], $layout = 'main') {
        $this->view->render($viewPath, $data, $layout);
    }

    /**
     * Return JSON response (for AJAX)
     */
    protected function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Redirect to a URL
     */
    protected function redirect($path) {
        header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
        exit;
    }

    /**
     * Flash message (store in session)
     */
    protected function flash($type, $message) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Get current user data
     */
    protected function getUser() {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Check if current user has permission
     */
    protected function can($module, $action = 'can_view') {
        $permissions = $_SESSION['permissions'] ?? [];
        $key = $module . '.' . $action;
        return isset($permissions[$key]) && $permissions[$key];
    }

    /**
     * Require permission or abort with 403
     */
    protected function requirePermission($module, $action = 'can_view') {
        if (!$this->can($module, $action)) {
            $this->render('errors/403', [], 'minimal');
            exit;
        }
    }

    /**
     * Validate CSRF token
     */
    protected function validateCsrf() {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($token)) {
            $raw = json_decode(file_get_contents('php://input'), true);
            $token = $raw['_csrf'] ?? '';
        }
        if (!CSRF::validate($token)) {
            $this->json(['success' => false, 'message' => 'Invalid security token'], 403);
        }
    }

    /**
     * Get POST data with optional default
     */
    protected function post($key, $default = null) {
        return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
    }

    /**
     * Get GET data
     */
    protected function get($key, $default = null) {
        return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
    }

    /**
     * Get current branch ID from session
     */
    protected function getBranchId() {
        return (int)($_SESSION['branch_id'] ?? 1);
    }

    /**
     * Get current company ID from session
     */
    protected function getCompanyId() {
        return (int)($_SESSION['company_id'] ?? 1);
    }

    /**
     * Write audit log entry
     */
    protected function auditLog($action, $module, $recordId = null, $docNo = null, $oldVal = null, $newVal = null) {
        $this->db->execute(
            "INSERT INTO audit_logs (company_id, user_id, action, module, record_id, doc_no, old_values, new_values, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->getCompanyId(),
                $_SESSION['user_id'] ?? null,
                $action,
                $module,
                $recordId,
                $docNo,
                $oldVal ? json_encode($oldVal) : null,
                $newVal ? json_encode($newVal) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            ]
        );
    }

    /**
     * Paginate a query
     */
    protected function paginate($sql, $params, $page, $perPage = ITEMS_PER_PAGE) {
        $countSql = "SELECT COUNT(*) FROM ({$sql}) AS cnt";
        $total    = (int)$this->db->fetchColumn($countSql, $params);

        $offset = ($page - 1) * $perPage;
        $rows   = $this->db->fetchAll($sql . " LIMIT $perPage OFFSET $offset", $params);

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => max(1, ceil($total / $perPage)),
            'from'         => $offset + 1,
            'to'           => min($offset + $perPage, $total),
        ];
    }
}
