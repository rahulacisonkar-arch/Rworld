<?php
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/db.php';

echo "<h2>Running OCR Module Database Migrations...</h2>\n";

try {
    // Helper function to check if a table exists
    function tableExists($pdo, $table) {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'artee_attendance' AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetch();
    }

    // 1. Create employee_timesheets table
    if (!tableExists($pdo, 'employee_timesheets')) {
        $pdo->exec("CREATE TABLE employee_timesheets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            week_ending DATE NOT NULL,
            regular_hours DECIMAL(10,2) DEFAULT 0.00,
            overtime_hours DECIMAL(10,2) DEFAULT 0.00,
            total_hours DECIMAL(10,2) DEFAULT 0.00,
            status VARCHAR(50) DEFAULT 'Draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✓ Created table 'employee_timesheets'.<br>\n";
    } else {
        echo "✓ Table 'employee_timesheets' already exists.<br>\n";
    }

    // 2. Create payroll_summary table
    if (!tableExists($pdo, 'payroll_summary')) {
        $pdo->exec("CREATE TABLE payroll_summary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            period_start DATE NOT NULL,
            period_end DATE NOT NULL,
            total_hours DECIMAL(10,2) DEFAULT 0.00,
            total_pay DECIMAL(10,2) DEFAULT 0.00,
            calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✓ Created table 'payroll_summary'.<br>\n";
    } else {
        echo "✓ Table 'payroll_summary' already exists.<br>\n";
    }

    // 3. Create ocr_audit_logs table
    if (!tableExists($pdo, 'ocr_audit_logs')) {
        $pdo->exec("CREATE TABLE ocr_audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            employee_id INT NULL,
            file_name VARCHAR(255) NOT NULL,
            raw_ocr_text LONGTEXT NULL,
            extracted_data LONGTEXT NULL,
            modified_data LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✓ Created table 'ocr_audit_logs'.<br>\n";
    } else {
        echo "✓ Table 'ocr_audit_logs' already exists.<br>\n";
    }

    echo "<h3>OCR Module Migrations Completed Successfully!</h3>\n";

} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "<br>\n";
}
