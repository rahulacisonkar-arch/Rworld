<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rworld ERP — Login</title>
<meta name="description" content="Rworld ERP — Secure Login">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
:root {
    --primary: #4f46e5;
    --primary-dark: #3730a3;
    --primary-light: #818cf8;
    --accent: #06b6d4;
    --danger: #ef4444;
    --success: #10b981;
    --bg-dark: #0f172a;
    --bg-card: rgba(255,255,255,0.05);
    --text: #f1f5f9;
    --text-muted: #94a3b8;
    --border: rgba(255,255,255,0.1);
}
body {
    font-family: 'Inter', sans-serif;
    background: var(--bg-dark);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
/* Animated gradient background */
body::before {
    content: '';
    position: absolute;
    width: 800px; height: 800px;
    background: radial-gradient(circle, rgba(79,70,229,0.3) 0%, transparent 70%);
    top: -200px; left: -200px;
    animation: pulse 8s ease-in-out infinite;
}
body::after {
    content: '';
    position: absolute;
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(6,182,212,0.2) 0%, transparent 70%);
    bottom: -100px; right: -100px;
    animation: pulse 10s ease-in-out infinite reverse;
}
@keyframes pulse {
    0%,100% { transform: scale(1); opacity:1; }
    50% { transform: scale(1.1); opacity:0.7; }
}
/* Floating particles */
.particles { position:absolute; width:100%; height:100%; overflow:hidden; }
.particle {
    position: absolute;
    width: 4px; height: 4px;
    background: rgba(79,70,229,0.5);
    border-radius: 50%;
    animation: float linear infinite;
}
@keyframes float {
    0% { transform: translateY(100vh) rotate(0deg); opacity:0; }
    10% { opacity:1; }
    90% { opacity:1; }
    100% { transform: translateY(-100px) rotate(720deg); opacity:0; }
}
/* Login Card */
.login-wrapper {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 440px;
    padding: 20px;
}
.login-card {
    background: rgba(15,23,42,0.8);
    backdrop-filter: blur(20px);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 48px 40px;
    box-shadow: 0 25px 50px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.05) inset;
    animation: slideUp 0.5s ease;
}
@keyframes slideUp {
    from { opacity:0; transform:translateY(30px); }
    to { opacity:1; transform:translateY(0); }
}
.logo-area {
    text-align: center;
    margin-bottom: 36px;
}
.logo-icon {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    margin-bottom: 16px;
    box-shadow: 0 8px 24px rgba(79,70,229,0.4);
}
.logo-area h1 {
    font-size: 26px;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -0.5px;
}
.logo-area p {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 4px;
}
/* Alert */
.alert {
    background: rgba(239,68,68,0.15);
    border: 1px solid rgba(239,68,68,0.3);
    border-left: 3px solid var(--danger);
    color: #fca5a5;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13.5px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: shake 0.4s ease;
}
.alert-expired {
    background: rgba(245,158,11,0.15);
    border-color: rgba(245,158,11,0.3);
    border-left-color: #f59e0b;
    color: #fcd34d;
}
@keyframes shake {
    0%,100% { transform:translateX(0); }
    25% { transform:translateX(-6px); }
    75% { transform:translateX(6px); }
}
/* Form */
.form-group {
    margin-bottom: 20px;
}
.form-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-muted);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}
.input-wrapper {
    position: relative;
}
.input-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 15px;
    transition: color 0.2s;
}
.form-control {
    width: 100%;
    background: rgba(255,255,255,0.06);
    border: 1.5px solid var(--border);
    border-radius: 12px;
    padding: 13px 16px 13px 44px;
    font-size: 15px;
    color: var(--text);
    font-family: 'Inter', sans-serif;
    transition: all 0.2s;
    outline: none;
}
.form-control:focus {
    border-color: var(--primary-light);
    background: rgba(79,70,229,0.08);
    box-shadow: 0 0 0 3px rgba(79,70,229,0.15);
}
.form-control:focus + .input-icon, .input-wrapper:focus-within .input-icon {
    color: var(--primary-light);
}
.form-control::placeholder { color: rgba(148,163,184,0.5); }
.toggle-password {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 14px;
    padding: 4px;
    transition: color 0.2s;
}
.toggle-password:hover { color: var(--primary-light); }
/* Password field needs right padding */
.form-control.has-toggle { padding-right: 44px; }
/* Submit */
.btn-login {
    width: 100%;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border: none;
    border-radius: 12px;
    padding: 14px;
    font-size: 15px;
    font-weight: 600;
    color: white;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 8px;
    box-shadow: 0 4px 15px rgba(79,70,229,0.4);
}
.btn-login:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 25px rgba(79,70,229,0.5);
    background: linear-gradient(135deg, #5b52f0, var(--primary));
}
.btn-login:active { transform: translateY(0); }
.btn-login.loading { opacity:0.8; pointer-events:none; }
/* Footer */
.login-footer {
    text-align: center;
    margin-top: 28px;
    font-size: 12px;
    color: rgba(148,163,184,0.6);
}
.version-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(79,70,229,0.15);
    border: 1px solid rgba(79,70,229,0.3);
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 11px;
    color: var(--primary-light);
    margin-bottom: 10px;
}
</style>
</head>
<body>

<div class="particles" id="particles"></div>

<div class="login-wrapper">
    <div class="login-card">
        <div class="logo-area">
            <div class="logo-icon">
                <i class="fas fa-cash-register"></i>
            </div>
            <h1>Rworld ERP</h1>
            <p>Enterprise Resource Planning System</p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($expired)): ?>
        <div class="alert alert-expired">
            <i class="fas fa-clock"></i>
            Session expired due to inactivity. Please login again.
        </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="<?= APP_URL ?>/auth/login" autocomplete="off">
            <?= View::csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-wrapper">
                    <input type="text" id="username" name="username" class="form-control"
                           placeholder="Enter your username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username" required autofocus>
                    <i class="fas fa-user input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password"
                           class="form-control has-toggle"
                           placeholder="Enter your password"
                           autocomplete="current-password" required>
                    <i class="fas fa-lock input-icon"></i>
                    <button type="button" class="toggle-password" id="togglePwd" title="Show/Hide password">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i>
                Sign In to Rworld ERP
            </button>
        </form>

        <div class="login-footer">
            <div class="version-badge">
                <i class="fas fa-shield-alt"></i>
                Rworld ERP v1.0 — Secure Session
            </div>
            <div>© <?= date('Y') ?> Rworld ERP</div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
document.getElementById('togglePwd').addEventListener('click', function() {
    const pwd = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'fas fa-eye';
    }
});

// Loading state on submit
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('loginBtn');
    btn.classList.add('loading');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
});

// Floating particles
(function() {
    const container = document.getElementById('particles');
    for (let i = 0; i < 20; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        p.style.left = Math.random() * 100 + '%';
        p.style.animationDuration = (8 + Math.random() * 12) + 's';
        p.style.animationDelay = (Math.random() * 8) + 's';
        p.style.width = p.style.height = (2 + Math.random() * 4) + 'px';
        container.appendChild(p);
    }
})();
</script>
</body>
</html>
