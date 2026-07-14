<?php
/**
 * QuickBill POS - Router / Front Controller
 * index.php bootstraps everything and routes requests here.
 */

class App {

    private $controllerName = 'DashboardController';
    private $methodName     = 'index';
    private $params         = [];

    public function __construct() {
        $this->parseUrl();
    }

    private function parseUrl() {
        // Apache sets $_GET['url'] via .htaccess RewriteRule.
        // PHP built-in server doesn't process .htaccess, so fall back to REQUEST_URI.
        if (isset($_GET['url']) && $_GET['url'] !== '') {
            $url = trim($_GET['url'], '/');
        } else {
            // Strip the /quickbill/ base prefix from REQUEST_URI
            $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $base        = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $url         = trim(substr($requestUri, strlen($base)), '/');
        }

        // Allow only safe URL characters; strip everything else
        $url   = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $url);
        $parts = array_filter(explode('/', $url), 'strlen');
        $parts = array_values($parts);

        // Controller — default to Dashboard
        if (!empty($parts[0])) {
            $this->controllerName = ucfirst(strtolower($parts[0])) . 'Controller';
        }

        // Method — default to index; strip non-identifier chars
        if (!empty($parts[1])) {
            $this->methodName = lcfirst(preg_replace('/[^a-zA-Z0-9_]/', '', $parts[1]));
        }

        // URL parameters
        $this->params = array_slice($parts, 2);
    }

    public function run() {
        $controllerFile = APP_PATH . '/Controllers/' . $this->controllerName . '.php';

        if (!file_exists($controllerFile)) {
            $controllerFile = APP_PATH . '/Controllers/ErrorController.php';
            require_once $controllerFile;
            $controller = new ErrorController();
            $controller->notFound();
            return;
        }

        require_once $controllerFile;
        $controller = new $this->controllerName();

        if (!method_exists($controller, $this->methodName)) {
            $controllerFile = APP_PATH . '/Controllers/ErrorController.php';
            require_once $controllerFile;
            $controller = new ErrorController();
            $controller->notFound();
            return;
        }

        call_user_func_array([$controller, $this->methodName], $this->params);

    }
}
