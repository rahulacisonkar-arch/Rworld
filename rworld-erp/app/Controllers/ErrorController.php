<?php
/**
 * QuickBill POS - Error Controller
 * Handles 404 Not Found and 403 Forbidden pages.
 */

class ErrorController {

    public function notFound() {
        http_response_code(404);
        // Minimal standalone error page — no layout needed
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>404 — Page Not Found | Rworld ERP</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#0f172a;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:20px}
.code{font-size:120px;font-weight:800;background:linear-gradient(135deg,#4f46e5,#06b6d4);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1}
h2{font-size:24px;margin:16px 0 8px;color:#e2e8f0}
p{color:#94a3b8;font-size:15px;max-width:400px;margin:0 auto 32px}
a{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#4f46e5,#3730a3);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;transition:opacity .2s}
a:hover{opacity:.85}
</style>
</head>
<body>
<div>
    <div class="code">404</div>
    <h2>Page Not Found</h2>
    <p>The page you are looking for does not exist or has been moved.</p>
    <a href="<?= APP_URL ?>/dashboard"><i class="fas fa-home"></i> Back to Dashboard</a>
</div>
</body>
</html>
        <?php
    }

    public function forbidden() {
        http_response_code(403);
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>403 — Access Denied | Rworld ERP</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#0f172a;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:20px}
.code{font-size:120px;font-weight:800;color:#ef4444;line-height:1}
h2{font-size:24px;margin:16px 0 8px;color:#e2e8f0}
p{color:#94a3b8;font-size:15px;max-width:400px;margin:0 auto 32px}
a{display:inline-block;padding:12px 28px;background:#374151;color:#fff;text-decoration:none;border-radius:10px;font-weight:600;transition:opacity .2s}
a:hover{opacity:.85}
</style>
</head>
<body>
<div>
    <div class="code">403</div>
    <h2>Access Denied</h2>
    <p>You do not have permission to access this page.</p>
    <a href="<?= APP_URL ?>/dashboard">Back to Dashboard</a>
</div>
</body>
</html>
        <?php
    }
}
