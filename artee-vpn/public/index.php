<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Artee VPN — Enterprise Secure Access</title>
  <meta name="description" content="Artee VPN is a blazing-fast, enterprise-grade Zero Trust VPN built on WireGuard. Connect your team securely from anywhere." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body>

<!-- ── Navigation ─────────────────────────────────────────── -->
<nav class="nav">
  <div class="nav-inner">
    <a href="/" class="nav-logo">
      <span class="logo-icon">⬡</span>
      <span class="logo-text">Artee <strong>VPN</strong></span>
    </a>
    <div class="nav-links">
      <a href="#features">Features</a>
      <a href="#downloads">Downloads</a>
      <a href="#pricing">Pricing</a>
      <a href="login.php" class="btn btn-outline">Sign In</a>
    </div>
  </div>
</nav>

<!-- ── Hero ───────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-glow"></div>
  <div class="container">
    <div class="badge">⚡ Powered by WireGuard® Protocol</div>
    <h1 class="hero-title">
      Enterprise VPN<br/>
      <span class="gradient-text">Built for Speed &amp; Zero Trust</span>
    </h1>
    <p class="hero-sub">
      Connect your entire organization with military-grade encryption, peer-to-peer
      mesh networking, and a management dashboard you'll actually love using.
    </p>
    <div class="hero-actions">
      <a href="login.php" class="btn btn-primary btn-lg">
        <span>Get Started Free</span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
      <a href="#downloads" class="btn btn-ghost btn-lg">Download Client</a>
    </div>

    <!-- Status bar -->
    <div class="hero-stats">
      <div class="stat-item">
        <span class="stat-value" id="stat-peers">—</span>
        <span class="stat-label">Active Peers</span>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <span class="stat-value" id="stat-uptime">99.9%</span>
        <span class="stat-label">Uptime</span>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <span class="stat-value">WireGuard®</span>
        <span class="stat-label">Protocol</span>
      </div>
    </div>
  </div>
</section>

<!-- ── Features ───────────────────────────────────────────── -->
<section class="section" id="features">
  <div class="container">
    <div class="section-header">
      <h2>Everything your team needs</h2>
      <p>Enterprise-grade features without the enterprise complexity.</p>
    </div>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">🔐</div>
        <h3>Zero Trust Access</h3>
        <p>Verify every device, every connection. No implicit trust — just granular access policies.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background: linear-gradient(135deg, #06b6d4, #3b82f6);">⚡</div>
        <h3>Blazing Fast WireGuard®</h3>
        <p>Peer-to-peer direct connections mean no central bottleneck. 4-6× faster than legacy VPNs.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background: linear-gradient(135deg, #10b981, #059669);">🌐</div>
        <h3>Mesh Networking</h3>
        <p>Devices connect directly to each other — not via a relay. Lower latency, higher bandwidth.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background: linear-gradient(135deg, #f59e0b, #ef4444);">🔑</div>
        <h3>SSO &amp; MFA Ready</h3>
        <p>Plug in your existing identity provider — Google, Okta, Azure AD, or Keycloak.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background: linear-gradient(135deg, #ec4899, #8b5cf6);">📊</div>
        <h3>Admin Dashboard</h3>
        <p>Manage peers, policies, and routes from a clean MySQL-backed PHP dashboard.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon" style="background: linear-gradient(135deg, #14b8a6, #06b6d4);">🛡️</div>
        <h3>Self-Hosted &amp; Private</h3>
        <p>Your data never leaves your infrastructure. Full control, full compliance.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── Downloads ──────────────────────────────────────────── -->
<section class="section section-dark" id="downloads">
  <div class="container">
    <div class="section-header">
      <h2>Download Artee VPN Client</h2>
      <p>Available on all major platforms. Free to download.</p>
    </div>
    <div class="downloads-grid">
      <a href="https://pkgs.netbird.io/windows/amd64" target="_blank" class="download-card">
        <div class="dl-icon">🪟</div>
        <div class="dl-info">
          <span class="dl-platform">Windows (.exe)</span>
          <span class="dl-version">Direct installer for Windows 10/11</span>
        </div>
        <svg class="dl-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15V3m0 12l-4-4m4 4l4-4M2 17l.621 2.485A2 2 0 004.561 21h14.878a2 2 0 001.94-1.515L22 17"/></svg>
      </a>
      <div class="download-card" style="display: flex; flex-direction: column; align-items: stretch; gap: 8px;">
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="dl-icon">🍎</div>
          <div class="dl-info">
            <span class="dl-platform">macOS (.pkg)</span>
            <span class="dl-version">Installers for macOS 11+</span>
          </div>
        </div>
        <div style="display: flex; gap: 14px; font-size: 0.8rem; margin-top: 4px; padding-left: 56px;">
          <a href="https://pkgs.netbird.io/macos/amd64" target="_blank" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.75rem;">Intel Mac</a>
          <a href="https://pkgs.netbird.io/macos/arm64" target="_blank" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.75rem;">Apple Silicon</a>
        </div>
      </div>
      <a href="https://pkgs.netbird.io/install.sh" target="_blank" class="download-card">
        <div class="dl-icon">🐧</div>
        <div class="dl-info">
          <span class="dl-platform">Linux Script</span>
          <span class="dl-version">curl -fsSL https://pkgs.netbird.io/install.sh | sh</span>
        </div>
        <svg class="dl-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15V3m0 12l-4-4m4 4l4-4M2 17l.621 2.485A2 2 0 004.561 21h14.878a2 2 0 001.94-1.515L22 17"/></svg>
      </a>
      <a href="https://apps.apple.com/us/app/netbird/id1659403261" target="_blank" class="download-card">
        <div class="dl-icon">📱</div>
        <div class="dl-info">
          <span class="dl-platform">iOS</span>
          <span class="dl-version">iPhone &amp; iPad (iOS 14+)</span>
        </div>
        <svg class="dl-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15V3m0 12l-4-4m4 4l4-4M2 17l.621 2.485A2 2 0 004.561 21h14.878a2 2 0 001.94-1.515L22 17"/></svg>
      </a>
      <a href="https://play.google.com/store/apps/details?id=io.netbird.client" target="_blank" class="download-card">
        <div class="dl-icon">🤖</div>
        <div class="dl-info">
          <span class="dl-platform">Android</span>
          <span class="dl-version">Android 8.0 (Oreo) or later</span>
        </div>
        <svg class="dl-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15V3m0 12l-4-4m4 4l4-4M2 17l.621 2.485A2 2 0 004.561 21h14.878a2 2 0 001.94-1.515L22 17"/></svg>
      </a>
      <a href="https://docs.netbird.io/how-to/installation#docker" target="_blank" class="download-card download-card-docker">
        <div class="dl-icon">🐳</div>
        <div class="dl-info">
          <span class="dl-platform">Docker Pull</span>
          <span class="dl-version">docker run netbirdio/netbird</span>
        </div>
        <svg class="dl-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15V3m0 12l-4-4m4 4l4-4M2 17l.621 2.485A2 2 0 004.561 21h14.878a2 2 0 001.94-1.515L22 17"/></svg>
      </a>
    </div>
  </div>
</section>

<!-- ── Footer ─────────────────────────────────────────────── -->
<footer class="footer">
  <div class="container">
    <div class="footer-top">
      <div class="footer-brand">
        <a href="/" class="nav-logo">
          <span class="logo-icon">⬡</span>
          <span class="logo-text">Artee <strong>VPN</strong></span>
        </a>
        <p>Enterprise-grade Zero Trust VPN.<br/>Secured by WireGuard®.</p>
      </div>
      <div class="footer-links">
        <div class="footer-col">
          <h4>Product</h4>
          <a href="#features">Features</a>
          <a href="#downloads">Downloads</a>
          <a href="login.php">Dashboard</a>
        </div>
        <div class="footer-col">
          <h4>Legal</h4>
          <a href="#">Privacy Policy</a>
          <a href="#">Terms of Service</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <p>© <?php echo date('Y'); ?> Artee VPN. Built on <a href="https://github.com/netbirdio/netbird" target="_blank">NetBird</a> open-source technology.</p>
      <p>WireGuard® is a registered trademark of Jason A. Donenfeld.</p>
    </div>
  </div>
</footer>

<script>
// Fetch live peer count from API
fetch('api/stats.php')
  .then(function(r){ return r.json(); })
  .then(function(d){
    if (d.peers !== undefined) {
      document.getElementById('stat-peers').textContent = d.peers;
    }
  })
  .catch(function(){});
</script>

</body>
</html>
