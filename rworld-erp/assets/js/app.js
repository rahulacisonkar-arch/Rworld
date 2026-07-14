// QuickBill POS — Main Application JavaScript
// app/assets/js/app.js

(function() {
    'use strict';

    // ── Live Clock (USA Eastern Time Zone) ──────────────────────────────────
    function updateClock() {
        const now = new Date();
        const options = {
            timeZone: 'America/New_York',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };
        const s = now.toLocaleString('en-US', options);
        const el = document.getElementById('clockDisplay');
        const ft = document.getElementById('footerTime');
        if (el) el.textContent = s;
        if (ft) ft.textContent = s;
    }
    updateClock();
    setInterval(updateClock, 1000);

    // ── Sidebar Toggle (desktop) ────────────────────────────────────────────
    const sidebarToggle  = document.getElementById('sidebarToggle');
    const sidebar        = document.getElementById('sidebar');
    const qbMain         = document.getElementById('qbMain');
    const toggleIcon     = document.getElementById('toggleIcon');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            if (qbMain) qbMain.classList.toggle('expanded');
            if (toggleIcon) {
                toggleIcon.className = sidebar.classList.contains('collapsed')
                    ? 'fas fa-chevron-right'
                    : 'fas fa-chevron-left';
            }
            localStorage.setItem('qb_sidebar_collapsed',
                sidebar.classList.contains('collapsed') ? '1' : '0');
        });

        // Restore sidebar state from localStorage
        if (localStorage.getItem('qb_sidebar_collapsed') === '1') {
            sidebar.classList.add('collapsed');
            if (qbMain) qbMain.classList.add('expanded');
            if (toggleIcon) toggleIcon.className = 'fas fa-chevron-right';
        }
    }

    // ── Mobile Sidebar Toggle ───────────────────────────────────────────────
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const overlay      = document.getElementById('sidebarOverlay');

    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-open');
            if (overlay) overlay.classList.toggle('active');
        });
    }
    if (overlay && sidebar) {
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
    }

    // ── Keyboard Shortcut: F2 = New Sale ────────────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.key === 'F2' && !e.ctrlKey && !e.altKey) {
            e.preventDefault();
            const appUrl = document.documentElement.dataset.appUrl || '';
            window.location.href = appUrl + '/sales/create';
        }
    });

    // ── Auto-dismiss Flash Messages ─────────────────────────────────────────
    document.querySelectorAll('.alert.alert-success').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 500);
        }, 4000);
    });

    // ── Confirm Delete ───────────────────────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

    // ── Table Row Click → Link ───────────────────────────────────────────────
    document.querySelectorAll('tr[data-href]').forEach(function (row) {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function () {
            window.location.href = row.dataset.href;
        });
    });

    // ── Sidebar Scroll Persistence ───────────────────────────────────────────
    const sidebarNav = document.getElementById('sidebarNav');
    if (sidebarNav) {
        const scrollPos = sessionStorage.getItem('qb_sidebar_scroll');
        if (scrollPos) {
            sidebarNav.scrollTop = parseInt(scrollPos, 10);
        }
        sidebarNav.addEventListener('scroll', function() {
            sessionStorage.setItem('qb_sidebar_scroll', sidebarNav.scrollTop);
        });
    }

})();
