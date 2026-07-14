$(document).ready(function() {

    // ============================================================
    // 1. Notification Polling & Triggering Reminders
    // ============================================================
    function pollNotifications() {
        if ($('#unread-count').length > 0) {
            $.ajax({
                url: 'notifications.php?action=count',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response && response.count !== undefined) {
                        const count = parseInt(response.count);
                        if (count > 0) {
                            $('#unread-count').text(count).show();
                        } else {
                            $('#unread-count').hide();
                        }
                    }
                },
                error: function() { /* Silent */ }
            });
        }
    }
    pollNotifications();
    setInterval(pollNotifications, 10000);

    // ============================================================
    // 2. Sidebar Toggle
    // ============================================================
    $('#sidebar-toggle-btn').on('click', function(e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-collapsed');
    });

    // ============================================================
    // 3. Right Notification Drawer
    // ============================================================
    $('#bell-toggle-btn').on('click', function(e) {
        e.preventDefault();
        $('#right-notification-drawer').addClass('open');
        loadDrawerNotifications();
    });

    $('#close-drawer-btn').on('click', function() {
        $('#right-notification-drawer').removeClass('open');
    });

    $('#clear-notifications-btn').on('click', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'notifications.php?action=clear',
            method: 'POST',
            success: function() {
                loadDrawerNotifications();
                pollNotifications();
            }
        });
    });

    function loadDrawerNotifications() {
        $.ajax({
            url: 'notifications.php?action=list',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response && response.notifications) {
                    const list = $('#drawer-notifications-list');
                    list.empty();
                    if (response.notifications.length === 0) {
                        list.html('<div class="text-center py-5 text-muted"><i class="bi bi-mailbox fs-3 mb-2 d-block"></i><span class="small">No new notifications.</span></div>');
                    } else {
                        response.notifications.forEach(function(n) {
                            const isUnread = parseInt(n.is_read) === 0;
                            const dateStr  = new Date(n.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
                            
                            const item = $(`
                                <div class="p-3 mb-2 rounded border position-relative" style="background: ${isUnread ? 'var(--primary-light)' : '#FAFBFC'}; border-color: ${isUnread ? '#93C5FD' : 'var(--border)'} !important;">
                                    ${isUnread ? '<span class="position-absolute top-50 translate-middle-y" style="left:8px; width:6px; height:6px; border-radius:50%; background: var(--primary); display:inline-block;"></span>' : ''}
                                    <div class="${isUnread ? 'ps-3' : ''}">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold" style="font-size: 0.8rem; color: var(--text-primary);">${n.title}</span>
                                            <span style="font-size: 0.65rem; color: var(--text-muted);">${dateStr}</span>
                                        </div>
                                        <p class="mb-0" style="font-size: 0.78rem; color: var(--text-secondary);">${n.message}</p>
                                    </div>
                                </div>
                            `);
                            list.append(item);
                        });
                    }
                }
            }
        });
    }

    // ============================================================
    // 4. Live Clock (US Eastern Time Zone)
    // ============================================================
    function updateClock() {
        const now  = new Date();
        const optionsTime = {
            timeZone: 'America/New_York',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };
        const optionsDate = {
            timeZone: 'America/New_York',
            weekday: 'short',
            month: 'short',
            day: 'numeric'
        };
        const time = now.toLocaleTimeString('en-US', optionsTime);
        const date = now.toLocaleDateString('en-US', optionsDate);
        $('#current-live-time').text(time);
        $('#current-live-date').text(date);
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ============================================================
    // 5. Drag & Drop File Upload Zone
    // ============================================================
    const dropZone = $('#bill-dropzone');
    const fileInput = $('#bill-file-input');
    const displayFileName = $('#selected-file-name');

    if (dropZone.length > 0) {
        dropZone.on('click', function() { fileInput.click(); });
        
        fileInput.on('click', function(e) {
            e.stopPropagation();
        });

        fileInput.on('change', function() {
            if (this.files && this.files[0]) {
                displayFileName.text('Selected: ' + this.files[0].name).removeClass('text-muted').addClass('fw-bold text-success');
                dropZone.addClass('border-success');
            }
        });

        dropZone.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });

        dropZone.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });

        dropZone.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            const files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {
                fileInput[0].files = files;
                displayFileName.text('Selected: ' + files[0].name).removeClass('text-muted').addClass('fw-bold text-success');
                dropZone.addClass('border-success');
            }
        });
    }

    // ============================================================
    // 6. Number counter animation
    // ============================================================
    function animateCounter(element) {
        const target = parseInt(element.text().replace(/,/g, '')) || 0;
        if (target === 0) return;
        let current  = 0;
        const step   = Math.max(1, Math.ceil(target / 40));
        const timer  = setInterval(function() {
            current = Math.min(current + step, target);
            element.text(current.toLocaleString());
            if (current >= target) clearInterval(timer);
        }, 25);
    }

    $('.kpi-value').each(function() {
        if (!$(this).text().startsWith('$') && !$(this).text().includes('%')) {
            animateCounter($(this));
        }
    });

});
