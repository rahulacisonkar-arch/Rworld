// Artée Fabrics & Home — Logistics Portal Application JS
// Enterprise Light Theme Edition

$(document).ready(function() {

    // ============================================================
    // 1. Notification Polling (Every 10 seconds)
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
    // 2. Real-time Freight Charge Validation
    // ============================================================
    const freightInput = $('#customer_freight_charge');
    const submitBtn    = $('#submit-request-btn');

    if (freightInput.length > 0) {
        const minFreight = parseFloat(freightInput.data('min-charge') || 15.00);

        function validateFreight() {
            const rawVal = freightInput.val().trim();
            const val    = parseFloat(rawVal);
            const errorDiv = $('#freight-error-msg');

            if (rawVal === '') {
                freightInput.addClass('is-invalid').removeClass('is-valid');
                errorDiv.text("Freight charge is required.").show();
                submitBtn.prop('disabled', true);
                return false;
            } else if (isNaN(val) || val < minFreight) {
                freightInput.addClass('is-invalid').removeClass('is-valid');
                errorDiv.text(`Minimum freight charge is $${minFreight.toFixed(2)}.`).show();
                submitBtn.prop('disabled', true);
                return false;
            } else {
                freightInput.addClass('is-valid').removeClass('is-invalid');
                errorDiv.hide().text("");
                submitBtn.prop('disabled', false);
                return true;
            }
        }

        freightInput.on('input keyup blur change', validateFreight);
        if (freightInput.val()) validateFreight();

        $('#request-form').on('submit', function(e) {
            if (!validateFreight()) {
                e.preventDefault();
                alert("Please correct validation errors before submitting.");
            }
        });
    }

    // ============================================================
    // 3. Ship From Address Toggle (Default vs Alternative)
    // ============================================================
    $('input[name="ship_from_type"]').on('change', function() {
        const type = $(this).val();
        if (type === 'default') {
            const storeData = $('#store-data');
            $('#ship_from_name').val(storeData.data('name')).prop('readonly', true);
            $('#ship_from_company').val(storeData.data('company')).prop('readonly', true);
            $('#ship_from_address1').val(storeData.data('address')).prop('readonly', true);
            $('#ship_from_address2').val('').prop('readonly', true);
            $('#ship_from_city').val(storeData.data('city')).prop('readonly', true);
            $('#ship_from_state').val(storeData.data('state')).prop('readonly', true);
            $('#ship_from_zip').val(storeData.data('zip')).prop('readonly', true);
            $('#ship_from_phone').val(storeData.data('phone')).prop('readonly', true);
            $('#ship_from_email').val(storeData.data('email')).prop('readonly', true);
            $('#save-ship-from-wrapper').hide();
            $('#save_ship_from').prop('checked', false);
        } else {
            $('.ship-from-field').val('').prop('readonly', false);
            $('#save-ship-from-wrapper').show();
        }
    });

    // ============================================================
    // 4. Store Address Selector Dropdown
    // ============================================================
    $('#store_address_selector').on('change', function() {
        const option = $(this).find('option:selected');
        if (option.val() !== '') {
            $('#ship_from_alt').prop('checked', true).trigger('change');

            let phone = option.data('phone');
            let email = option.data('email') || '';

            if (!phone || phone.toString().trim() === '') phone = $('#store-data').data('phone');
            if (!email || email.toString().trim() === '')  email = $('#store-data').data('email');
            if (email && email.indexOf(',') !== -1) email = email.split(',')[0].trim();

            $('#ship_from_name').val(option.data('name') || $('#store-data').data('name') || 'Store Staff');
            $('#ship_from_company').val(option.data('company'));
            $('#ship_from_address1').val(option.data('address'));
            $('#ship_from_address2').val(option.data('address2') || '');
            $('#ship_from_city').val(option.data('city'));
            $('#ship_from_state').val(option.data('state'));
            $('#ship_from_zip').val(option.data('zip'));
            $('#ship_from_phone').val(phone);
            $('#ship_from_email').val(email);
        }
    });

    // ============================================================
    // 5. Ship To Saved Address Selector
    // ============================================================
    $('#ship_to_address_selector').on('change', function() {
        const option = $(this).find('option:selected');
        if (option.val() !== '') {
            let email = option.data('email') || '';
            if (email && email.indexOf(',') !== -1) email = email.split(',')[0].trim();

            $('#ship_to_name').val(option.data('name') || $('#store-data').data('name') || 'Store Staff');
            $('#ship_to_company').val(option.data('company'));
            $('#ship_to_address1').val(option.data('address1'));
            $('#ship_to_address2').val(option.data('address2') || '');
            $('#ship_to_city').val(option.data('city'));
            $('#ship_to_state').val(option.data('state'));
            $('#ship_to_zip').val(option.data('zip'));
            $('#ship_to_phone').val(option.data('phone') || '');
            $('#ship_to_email').val(email);
        }
    });

    // Initialize default address if selected on page load
    if ($('input[name="ship_from_type"]:checked').val() === 'default') {
        $('input[name="ship_from_type"]:checked').trigger('change');
    }

    // ============================================================
    // 6. Sidebar Toggle
    // ============================================================
    $('#sidebar-toggle-btn').on('click', function(e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-collapsed');
    });

    // ============================================================
    // 7. Right Notification Drawer
    // ============================================================
    $('#bell-toggle-btn').on('click', function(e) {
        e.preventDefault();
        $('#right-notification-drawer').addClass('open');
        loadDrawerNotifications();
    });

    $('#close-drawer-btn').on('click', function() {
        $('#right-notification-drawer').removeClass('open');
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
                        list.html('<div class="text-center py-5" style="color: var(--text-muted);"><i class="bi bi-mailbox fs-3 mb-2 d-block"></i><span class="small">No new notifications.</span></div>');
                    } else {
                        response.notifications.forEach(function(n) {
                            const isUnread = parseInt(n.is_read) === 0;
                            const dateStr  = new Date(n.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
                            
                            // Render label download buttons and view request button
                            let buttonsHtml = '';
                            if (n.enrichment && n.enrichment.labels && n.enrichment.labels.length > 0) {
                                buttonsHtml += '<div class="mt-2 d-flex flex-wrap gap-1">';
                                n.enrichment.labels.forEach(function(lbl) {
                                    const trk = lbl.tracking_number ? lbl.tracking_number : 'Label';
                                    buttonsHtml += `
                                        <a href="download_label.php?id=${lbl.id}" class="btn btn-sm btn-danger text-white py-1 px-2 text-decoration-none rounded d-inline-flex align-items-center" style="font-size: 0.72rem; font-weight: 500; gap: 4px; box-shadow: 0 1px 3px rgba(220,53,69,0.2);">
                                            <i class="bi bi-file-earmark-pdf-fill"></i> Download PDF (${trk})
                                        </a>
                                    `;
                                });
                                buttonsHtml += `
                                    <a href="request_view.php?id=${n.enrichment.request_id}" class="btn btn-sm btn-outline-secondary py-1 px-2 text-decoration-none rounded d-inline-flex align-items-center" style="font-size: 0.72rem; font-weight: 500; gap: 4px;">
                                        <i class="bi bi-eye"></i> View Request
                                    </a>
                                </div>`;
                            } else if (n.enrichment && n.enrichment.request_id) {
                                buttonsHtml += `
                                    <div class="mt-2">
                                        <a href="request_view.php?id=${n.enrichment.request_id}" class="btn btn-sm btn-outline-secondary py-1 px-2 text-decoration-none rounded d-inline-flex align-items-center" style="font-size: 0.72rem; font-weight: 500; gap: 4px;">
                                            <i class="bi bi-eye"></i> View Request
                                        </a>
                                    </div>
                                `;
                            }

                            const item = $(`
                                <div class="p-3 mb-2 rounded border position-relative" style="background: ${isUnread ? 'var(--primary-light)' : '#FAFBFC'}; border-color: ${isUnread ? '#93C5FD' : 'var(--border)'} !important;">
                                    ${isUnread ? '<span class="position-absolute top-50 translate-middle-y" style="left:8px; width:6px; height:6px; border-radius:50%; background: var(--primary); display:inline-block;"></span>' : ''}
                                    <div class="${isUnread ? 'ps-3' : ''}">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold" style="font-size: 0.8rem; color: var(--text-primary);">${n.title}</span>
                                            <span style="font-size: 0.65rem; color: var(--text-muted);">${dateStr}</span>
                                        </div>
                                        <p class="mb-0" style="font-size: 0.78rem; color: var(--text-secondary);">${n.message}</p>
                                        ${buttonsHtml}
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
    // 8. Live Clock (US Eastern Time Zone)
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
    // 9. Drag & Drop File Upload Zone (Auto Upload)
    // ============================================================
    const autoDropZone       = $('#auto-label-drag-drop-zone');
    const autoFileInput      = $('#auto-label-file-input');
    const autoFileNameDisplay= $('#auto-file-selected-name');
    const autoForm           = $('#form-auto-add-label');

    if (autoDropZone.length > 0) {
        autoDropZone.on('click', function() { autoFileInput.click(); });

        autoFileInput.on('change', function() {
            if (this.files && this.files[0]) {
                autoFileNameDisplay.text('Selected: ' + this.files[0].name).show();
                autoDropZone.css('border-color', 'var(--primary)');
                autoForm.submit();
            }
        });

        autoDropZone.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag-over');
        });

        autoDropZone.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
        });

        autoDropZone.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag-over');
            const files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {
                autoFileInput[0].files = files;
                autoFileNameDisplay.text('Selected: ' + files[0].name).show();
                autoForm.submit();
            }
        });
    }
    function animateCounter(element) {
        const target = parseInt(element.text().replace(/,/g, '')) || 0;
        if (target === 0) return;
        let current  = 0;
        const step   = Math.max(1, Math.ceil(target / 40));
        const timer  = setInterval(function() {
            current = Math.min(current + step, target);
            element.text(current.toLocaleString());
            if (current >= target) clearInterval(timer);
        }, 30);
    }

    // Animate all kpi-value elements on load
    $('.kpi-value').each(function() {
        // Only animate plain numbers (not $ prefix values)
        if (!$(this).text().startsWith('$')) {
            animateCounter($(this));
        }
    });

});
