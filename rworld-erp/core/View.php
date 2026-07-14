<?php
/**
 * QuickBill POS - View Renderer
 */

class View {

    /**
     * Render view with layout
     *
     * @param string $viewPath  Relative path like 'auth/login' → app/Views/auth/login.php
     * @param array  $data      Variables to extract into view scope
     * @param string $layout    Layout name: 'main' | 'auth' | 'minimal' | 'print'
     */
    public function render($viewPath, $data = [], $layout = 'main') {
        // Extract data into local scope
        if (!empty($data)) extract($data);

        // Capture view content
        ob_start();
        $viewFile = VIEW_PATH . '/' . str_replace('.', '/', $viewPath) . '.php';
        if (!file_exists($viewFile)) {
            echo '<div class="alert alert-danger">View not found: ' . htmlspecialchars($viewPath) . '</div>';
        } else {
            include $viewFile;
        }
        $content = ob_get_clean();

        // Render layout
        $layoutFile = VIEW_PATH . '/layouts/' . $layout . '.php';
        if (file_exists($layoutFile)) {
            include $layoutFile;
        } else {
            echo $content;
        }
    }

    /**
     * Render partial (no layout)
     */
    public function partial($viewPath, $data = []) {
        if (!empty($data)) extract($data);
        $viewFile = VIEW_PATH . '/' . str_replace('.', '/', $viewPath) . '.php';
        if (file_exists($viewFile)) include $viewFile;
    }

    /**
     * Escape HTML output
     */
    public static function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Format currency
     */
    public static function currency($amount, $decimals = 2) {
        return '$' . number_format((float)$amount, $decimals, '.', ',');
    }

    /**
     * Format number
     */
    public static function number($value, $decimals = 2) {
        return number_format((float)$value, $decimals, '.', ',');
    }

    /**
     * Format date
     */
    public static function date($dateStr) {
        if (empty($dateStr)) return '';
        return date(DATE_FORMAT, strtotime($dateStr));
    }

    /**
     * Format datetime
     */
    public static function datetime($dateStr) {
        if (empty($dateStr)) return '';
        return date(DATETIME_FORMAT, strtotime($dateStr));
    }

    /**
     * Flash message display
     */
    public static function flash() {
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            $type = $flash['type'] === 'error' ? 'danger' : htmlspecialchars($flash['type']);
            $msg  = htmlspecialchars($flash['message']);
            return "<div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">
                        {$msg}
                        <button type=\"button\" class=\"close\" data-dismiss=\"alert\">
                            <span>&times;</span>
                        </button>
                    </div>";
        }
        return '';
    }

    /**
     * CSRF hidden input
     */
    public static function csrfField() {
        $token = CSRF::generate();
        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }

    /**
     * Active nav link helper
     */
    public static function activeClass($path) {
        $current = $_SERVER['REQUEST_URI'] ?? '';
        return (strpos($current, $path) !== false) ? 'active' : '';
    }
}
