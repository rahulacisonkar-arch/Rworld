<?php
/**
 * BackupController — Production database backup & restore utility
 */
class BackupController extends Controller {

    public function index() {
        $cid = $this->getCompanyId();

        // Load backup history — table may not exist yet, so handle gracefully
        try {
            $backups = $this->db->fetchAll(
                "SELECT * FROM backup_logs WHERE company_id=? ORDER BY id DESC LIMIT 50",
                [$cid]
            ) ?: [];
        } catch (Exception $e) {
            $backups = [];
        }

        $this->render('settings/backup', [
            'pageTitle' => 'Backup & Restore',
            'backups'   => $backups,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => APP_URL . '/dashboard'],
                ['label' => 'Administration', 'url' => '#'],
                ['label' => 'Backup & Restore', 'url' => '#'],
            ]
        ]);
    }

    /**
     * Create a SQL backup and download it (action: POST /backup/generate)
     */
    public function generate() {
        $this->validateCsrf();
        $cid = $this->getCompanyId();

        $filename = 'artee_erp_backup_' . date('Ymd_His') . '.sql';
        $contents = [];
        $contents[] = "-- Artee ERP Database Backup";
        $contents[] = "-- Generated: " . date('Y-m-d H:i:s');
        $contents[] = "-- Company ID: {$cid}";
        $contents[] = "SET FOREIGN_KEY_CHECKS=0;";
        $contents[] = "";

        try {
            $tables = $this->db->fetchAll("SHOW TABLES");
            foreach ($tables as $t) {
                $tableName = array_values($t)[0];
                $contents[] = "-- Table: `{$tableName}`";
                $createResult = $this->db->fetchOne("SHOW CREATE TABLE `{$tableName}`");
                $contents[] = "DROP TABLE IF EXISTS `{$tableName}`;";
                $contents[] = ($createResult['Create Table'] ?? '') . ";";
                $contents[] = "";

                // Dump rows
                $rows = $this->db->fetchAll("SELECT * FROM `{$tableName}`");
                foreach ($rows as $row) {
                    $vals = array_map(function($v) {
                        if ($v === null) return 'NULL';
                        return "'" . addslashes($v) . "'";
                    }, $row);
                    $contents[] = "INSERT INTO `{$tableName}` VALUES (" . implode(', ', $vals) . ");";
                }
                $contents[] = "";
            }
        } catch (Exception $e) {
            $this->flash('error', 'Backup failed: ' . $e->getMessage());
            $this->redirect('backup');
        }

        $contents[] = "SET FOREIGN_KEY_CHECKS=1;";
        $sqlBody = implode("\n", $contents);

        // Log backup record
        try {
            $this->db->execute(
                "INSERT INTO backup_logs (company_id, filename, file_size, created_by) VALUES (?, ?, ?, ?)",
                [$cid, $filename, strlen($sqlBody), $_SESSION['user_id'] ?? null]
            );
        } catch (Exception $e) {
            // Backup log table may not exist — ignore and still download
        }

        $this->auditLog('BACKUP', 'system');

        // Also save to backup_files directory for download history
        $backupDir = dirname(APP_PATH) . '/backup_files';
        if (!is_dir($backupDir)) { @mkdir($backupDir, 0755, true); }
        @file_put_contents($backupDir . '/' . $filename, $sqlBody);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sqlBody));
        echo $sqlBody;
        exit;
    }

    /**
     * Restore from uploaded SQL file (POST /backup/restore)
     */
    public function restore() {
        $this->validateCsrf();

        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'No backup file uploaded or upload error occurred.');
            $this->redirect('backup');
            return;
        }

        $tmpPath = $_FILES['backup_file']['tmp_name'];
        $sql = file_get_contents($tmpPath);

        if (!$sql) {
            $this->flash('error', 'Backup file is empty or unreadable.');
            $this->redirect('backup');
            return;
        }

        try {
            // Execute statements one by one
            $statements = array_filter(
                array_map('trim', explode(";\n", $sql)),
                fn($s) => strlen($s) > 5 && strpos($s, '--') !== 0
            );

            $this->db->execute("SET FOREIGN_KEY_CHECKS=0");
            foreach ($statements as $stmt) {
                if (trim($stmt)) {
                    $this->db->execute($stmt);
                }
            }
            $this->db->execute("SET FOREIGN_KEY_CHECKS=1");

            $this->auditLog('RESTORE', 'system');
            $this->flash('success', 'Database restored successfully from backup file.');
        } catch (Exception $e) {
            $this->flash('error', 'Restore failed: ' . $e->getMessage());
        }

        $this->redirect('backup');
    }

    /**
     * Download a previously logged backup by regenerating it (GET /backup/download/{id})
     */
    public function download($id = null) {
        if (!$id) { $this->redirect('backup'); }

        $cid = $this->getCompanyId();
        $log = $this->db->fetchOne(
            "SELECT * FROM backup_logs WHERE id=? AND company_id=?",
            [(int)$id, $cid]
        );

        if (!$log) {
            $this->flash('error', 'Backup record not found.');
            $this->redirect('backup');
        }

        // Re-generate the backup
        $this->generate();
    }
}
