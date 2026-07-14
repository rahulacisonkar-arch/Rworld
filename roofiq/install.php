<?php
/**
 * ROOFIQ AI ENTERPRISE — Auto Installer
 * Runs schema.sql and creates admin user
 * PHP 7.0.1 Compatible — Run ONCE then delete for security
 */
define('ROOFIQ_ROOT', __DIR__);

// ============================================================
// DB Config — edit before running
// ============================================================
$db_host = 'localhost';
$db_name = 'roofiq';
$db_user = 'root';
$db_pass = '';

$step    = isset($_GET['step']) ? intval($_GET['step']) : 1;
$errors  = array();
$success = array();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>SHEKHAR ROOFIQ AI — Installer</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Outfit:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    body { margin:0; padding:40px 20px; background:#0a0e1a; font-family:'Inter',sans-serif; color:#e2e8f0; min-height:100vh; }
    .container { max-width:760px; margin:0 auto; }
    h1 { font-family:'Outfit',sans-serif; font-weight:800; font-size:2rem; color:#fff; }
    h1 span { color:#00d4ff; }
    h2 { font-family:'Outfit',sans-serif; font-weight:700; font-size:1.2rem; color:#fff; margin-top:24px; border-bottom:1px solid rgba(0,212,255,0.2); padding-bottom:8px; }
    .card { background:#111827; border:1px solid rgba(0,212,255,0.15); border-radius:12px; padding:24px; margin-bottom:20px; }
    .check { color:#00e676; } .warn { color:#ffd600; } .err { color:#ff6b35; }
    .btn { background:linear-gradient(135deg,#00d4ff,#0099cc); color:#0a0e1a; font-weight:700; border:none; border-radius:8px; padding:12px 28px; font-size:0.95rem; cursor:pointer; font-family:'Inter',sans-serif; text-decoration:none; display:inline-block; }
    .btn:hover { opacity:0.9; }
    .step-item { padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.04); font-size:0.88rem; display:flex; align-items:center; gap:10px; }
    code { background:rgba(255,255,255,0.08); padding:2px 8px; border-radius:4px; color:#00d4ff; font-size:0.85rem; }
    input[type=text],input[type=password] { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:8px; padding:10px 14px; color:#e2e8f0; width:100%; font-size:0.9rem; box-sizing:border-box; font-family:'Inter',sans-serif; }
    input:focus { outline:none; border-color:#00d4ff; }
    label { color:#94a3b8; font-size:0.82rem; margin-bottom:4px; display:block; }
    .form-group { margin-bottom:16px; }
    .alert-success { background:rgba(0,230,118,0.1); border:1px solid rgba(0,230,118,0.3); border-radius:8px; padding:12px 16px; color:#69f0ae; margin-bottom:12px; }
    .alert-error   { background:rgba(255,23,68,0.1);  border:1px solid rgba(255,23,68,0.3);  border-radius:8px; padding:12px 16px; color:#ff6b6b; margin-bottom:12px; }
  </style>
</head>
<body>
<div class="container">
  <div style="text-align:center;margin-bottom:32px;">
    <h1>SHEKHAR ROOFIQ <span>AI</span> ENTERPRISE</h1>
    <div style="color:#94a3b8;font-size:0.9rem;">Installation Wizard v3.0 — PHP 7.0.1+ | MySQL 5.7+</div>
  </div>

<?php
// ---- Step 1: Check Requirements ----
if ($step === 1) {
    echo '<div class="card">';
    echo '<h2><i class="fas fa-list-check mr-2" style="color:#00d4ff;"></i>System Requirements Check</h2>';

    $checks = array();

    // PHP version
    $phpv = phpversion();
    $php_ok = version_compare($phpv, '7.0.0', '>=');
    $checks[] = array($php_ok, 'PHP Version: ' . $phpv . ($php_ok ? ' ✓' : ' — Need 7.0+'));

    // Extensions
    $exts = array('pdo','pdo_mysql','json','curl','session','mbstring','openssl');
    foreach ($exts as $ext) {
        $ok = extension_loaded($ext);
        $checks[] = array($ok, 'Extension: ' . $ext . ($ok ? ' ✓' : ' — MISSING'));
    }

    // Data directory writable
    $data_dir = __DIR__ . '/data';
    if (!is_dir($data_dir)) {
        @mkdir($data_dir, 0755, true);
    }
    $data_writable = is_writable($data_dir);
    $checks[] = array($data_writable, 'Data directory writable: ' . ($data_writable ? '✓' : 'FAILED — chmod 755 data'));

    // Public directory
    $pub_dir = __DIR__ . '/public';
    $pub_ok  = is_dir($pub_dir);
    $checks[] = array($pub_ok, 'Public directory exists: ' . ($pub_ok ? '✓' : '✗ — Run file creation steps'));

    foreach ($checks as $c) {
        $icon = $c[0] ? '<i class="fas fa-check-circle check"></i>' : '<i class="fas fa-times-circle err"></i>';
        echo '<div class="step-item">' . $icon . '<span style="color:' . ($c[0]?'#e2e8f0':'#ff6b6b') . ';">' . htmlspecialchars($c[1]) . '</span></div>';
        if (!$c[0]) { $errors[] = $c[1]; }
    }

    echo '</div>';

    if (!empty($errors)) {
        echo '<div class="alert-error"><i class="fas fa-exclamation-triangle mr-2"></i>Please fix the above issues before continuing.</div>';
    } else {
        echo '<div class="alert-success"><i class="fas fa-check-circle mr-2"></i>All requirements met! Ready to install.</div>';
        echo '<a href="install.php?step=2" class="btn"><i class="fas fa-arrow-right mr-2"></i>Continue to Database Setup</a>';
    }

} elseif ($step === 2) {
    // ---- Step 2: Database Configuration ----
    echo '<div class="card">';
    echo '<h2><i class="fas fa-database mr-2" style="color:#00d4ff;"></i>Database Configuration</h2>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db_host = trim($_POST['db_host'] ?? 'localhost');
        $db_name = trim($_POST['db_name'] ?? 'roofiq');
        $db_user = trim($_POST['db_user'] ?? 'root');
        $db_pass = trim($_POST['db_pass'] ?? '');

        // Test connection
        try {
            $dsn = 'mysql:host=' . $db_host . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $db_user, $db_pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $db_name . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . $db_name . "`");

            // Run schema
            $sql_file = __DIR__ . '/schema.sql';
            if (!file_exists($sql_file)) {
                $errors[] = 'schema.sql not found at: ' . $sql_file;
            } else {
                $sql = file_get_contents($sql_file);
                // Split and execute statements
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                $executed   = 0;
                foreach ($statements as $stmt) {
                    if (empty($stmt) || strpos($stmt, '--') === 0) continue;
                    try {
                        $pdo->exec($stmt);
                        $executed++;
                    } catch (PDOException $e) {
                        // Ignore duplicate table errors
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            $errors[] = $e->getMessage();
                        }
                    }
                }
                $success[] = 'Database schema installed: ' . $executed . ' statements executed.';

                // Save config
                $config_content = "<?php\n"
                    . "define('DB_HOST', '" . addslashes($db_host) . "');\n"
                    . "define('DB_NAME', '" . addslashes($db_name) . "');\n"
                    . "define('DB_USER', '" . addslashes($db_user) . "');\n"
                    . "define('DB_PASS', '" . addslashes($db_pass) . "');\n";
                // Append to config (or write db_local.php)
                file_put_contents(__DIR__ . '/data/db_config.php', $config_content);
                $success[] = 'Database config saved.';
            }
        } catch (PDOException $e) {
            $errors[] = 'DB Connection failed: ' . $e->getMessage();
        }

        foreach ($errors as $e) {
            echo '<div class="alert-error"><i class="fas fa-times mr-2"></i>' . htmlspecialchars($e) . '</div>';
        }
        foreach ($success as $s) {
            echo '<div class="alert-success"><i class="fas fa-check mr-2"></i>' . htmlspecialchars($s) . '</div>';
        }

        if (empty($errors)) {
            echo '<div class="alert-success"><strong>Database installed successfully!</strong></div>';
            echo '<a href="install.php?step=3" class="btn">Continue to Final Setup →</a>';
            echo '</div>';
            goto end_output;
        }
    }

    // Show form
    echo '<form method="POST">';
    echo '<div class="form-group"><label>Database Host</label><input type="text" name="db_host" value="' . htmlspecialchars($db_host) . '"></div>';
    echo '<div class="form-group"><label>Database Name</label><input type="text" name="db_name" value="' . htmlspecialchars($db_name) . '"></div>';
    echo '<div class="form-group"><label>Database Username</label><input type="text" name="db_user" value="' . htmlspecialchars($db_user) . '"></div>';
    echo '<div class="form-group"><label>Database Password</label><input type="password" name="db_pass" value=""></div>';
    echo '<button type="submit" class="btn"><i class="fas fa-database mr-2"></i>Install Database</button>';
    echo '</form>';
    echo '</div>';

} elseif ($step === 3) {
    // ---- Step 3: Final Setup ----
    echo '<div class="card">';
    echo '<h2><i class="fas fa-check-double mr-2" style="color:#00e676;"></i>Installation Complete!</h2>';

    $admin_pass = 'Admin@RoofIQ1';
    echo '<div class="alert-success" style="margin-bottom:20px;">';
    echo '<div style="font-size:1.1rem;font-weight:700;margin-bottom:12px;">🎉 SHEKHAR ROOFIQ AI ENTERPRISE is ready!</div>';
    echo '<div style="margin-bottom:6px;"><strong>Admin Login:</strong></div>';
    echo '<div>Username: <code>admin</code></div>';
    echo '<div>Password: <code>' . htmlspecialchars($admin_pass) . '</code></div>';
    echo '</div>';

    echo '<h2>Next Steps</h2>';
    $steps_list = array(
        'Change the default admin password immediately after first login',
        'Add your API keys in Settings → API Keys (Google Maps, Cesium Ion, MapTiler, OpenAI)',
        'Install TCPDF for PDF generation: download from https://tcpdf.org and place in /vendor/tcpdf/',
        'Start the Python AI service: cd roofiq/ai_service && uv sync && uvicorn main:app --host 0.0.0.0 --port 5001',
        'Delete install.php from your server for security',
        'Set up a cron job or task scheduler for scheduled reports',
    );
    foreach ($steps_list as $i => $s) {
        echo '<div class="step-item"><span style="background:#00d4ff;color:#0a0e1a;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0;">' . ($i+1) . '</span>' . htmlspecialchars($s) . '</div>';
    }

    echo '<div style="margin-top:24px;display:flex;gap:12px;">';
    echo '<a href="public/index.php" class="btn"><i class="fas fa-sign-in-alt mr-2"></i>Go to Login</a>';
    echo '</div>';
    echo '</div>';
}

end_output:
?>

</div>
</body>
</html>
