<?php
/**
 * QuickBill POS — 403 Forbidden View
 * app/Views/errors/403.php
 */
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
body{font-family:'Inter',sans-serif;background:#f1f5f9;color:#0f172a;min-height:100vh;
     display:flex;align-items:center;justify-content:center;text-align:center;padding:20px}
.box{background:#fff;border-radius:20px;padding:48px 40px;max-width:420px;
     box-shadow:0 4px 24px rgba(0,0,0,0.08)}
.code{font-size:80px;font-weight:800;color:#ef4444;line-height:1;margin-bottom:16px}
h2{font-size:22px;font-weight:700;margin-bottom:8px}
p{color:#64748b;font-size:14px;margin-bottom:28px}
a{display:inline-block;padding:11px 24px;background:#4f46e5;color:#fff;
  text-decoration:none;border-radius:10px;font-weight:600;font-size:14px;transition:opacity .2s}
a:hover{opacity:.85}
.icon{font-size:40px;margin-bottom:12px}
</style>
</head>
<body>
<div class="box">
    <div class="icon">🔒</div>
    <div class="code">403</div>
    <h2>Access Denied</h2>
    <p>You don't have permission to access this page.<br>
       Contact your administrator if you believe this is an error.</p>
    <a href="<?= APP_URL ?>/dashboard">← Back to Dashboard</a>
</div>
</body>
</html>
