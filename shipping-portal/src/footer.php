        </div> <!-- Close container-fluid -->
    </main> <!-- Close main-content -->

    <!-- ============================================================
         RIGHT NOTIFICATION DRAWER
    ============================================================ -->
    <div class="notification-drawer" id="right-notification-drawer">
        <div class="drawer-header">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-bell-fill"></i>
                <h6 class="mb-0 fw-bold">Notifications</h6>
            </div>
            <button class="btn p-0 text-white border-0" id="close-drawer-btn" style="line-height:1; font-size: 1.3rem;">&times;</button>
        </div>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom" style="background: #F8FAFB;">
            <span style="font-size: 0.75rem; color: var(--text-muted);">Recent updates</span>
            <a href="notifications_center.php" style="font-size: 0.75rem; font-weight: 600; color: var(--primary);">Manage All</a>
        </div>
        <div class="overflow-y-auto flex-grow-1 p-3" id="drawer-notifications-list">
            <div class="text-center py-5 text-muted">
                <i class="bi bi-mailbox fs-3 mb-2 d-block" style="color: var(--text-muted);"></i>
                <span class="small">No new notifications.</span>
            </div>
        </div>
        <div class="p-3 border-top text-center">
            <a href="notifications_center.php" class="btn-command-secondary w-100 d-block text-center py-2" style="border-radius: 8px;">View All Notifications</a>
        </div>
    </div>

</div> <!-- Close app-container -->

<!-- ============================================================
     PORTAL FOOTER
============================================================ -->
<footer class="portal-footer">
    &copy; <?php echo date('Y'); ?> Artée Fabrics &amp; Home &mdash; Logistics Operations Portal &mdash; All rights reserved.
</footer>

<!-- Bootstrap 5 JS & Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom Application Script -->
<script src="js/app.js"></script>
</body>
</html>
