<?php
/**
 * SettingsController — System and Company Settings management
 * Table: settings — real columns: setting_key, setting_val
 */
class SettingsController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            foreach ($_POST as $key => $value) {
                if ($key === '_csrf') continue;
                $exists = $this->db->fetchColumn(
                    "SELECT COUNT(*) FROM settings WHERE company_id=? AND setting_key=?",
                    [$cid, $key]
                );
                if ($exists) {
                    $this->db->execute(
                        "UPDATE settings SET setting_val=? WHERE company_id=? AND setting_key=?",
                        [$value, $cid, $key]
                    );
                } else {
                    $this->db->execute(
                        "INSERT INTO settings (company_id, setting_key, setting_val) VALUES (?, ?, ?)",
                        [$cid, $key, $value]
                    );
                }
            }

            $this->auditLog('UPDATE', 'settings');
            $this->flash('success', 'System settings updated successfully.');
            $this->redirect('settings');
        }

        $rows     = $this->db->fetchAll("SELECT setting_key, setting_val FROM settings WHERE company_id=?", [$cid]) ?: [];
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r['setting_val'];
        }

        $this->render('settings/index', [
            'pageTitle' => 'Settings',
            'settings'  => $settings,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Administration', 'url' => '#'],
                ['label' => 'Settings', 'url' => '#'],
            ]
        ]);
    }

    public function switchBranch() {
        if ($_SESSION['role_id'] != 1) {
            $this->flash('error', 'Only administrators can switch locations.');
            $this->redirect('dashboard');
            return;
        }
        $cid = $this->getCompanyId();
        $branchId = (int)$this->get('id');
        if ($branchId) {
            $branch = $this->db->fetchOne(
                "SELECT id, name FROM branches WHERE id=? AND company_id=?",
                [$branchId, $cid]
            );
            if ($branch) {
                $_SESSION['branch_id'] = $branch['id'];
                $_SESSION['branch_name'] = $branch['name'];
                $this->flash('success', "Switched location to: {$branch['name']}");
            }
        }
        $ref = $_SERVER['HTTP_REFERER'] ?? APP_URL . '/dashboard';
        header("Location: $ref");
        exit;
    }
}
