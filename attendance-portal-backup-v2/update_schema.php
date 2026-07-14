<?php
require_once __DIR__ . '/src/config.php';
require_once __DIR__ . '/src/db.php';

echo "<h2>Running Database Migrations...</h2>\n";

try {
    // Helper function to check if a column exists using information_schema
    function columnExists($pdo, $table, $column) {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'artee_attendance' AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetch();
    }

    // Helper function to check if a table exists using information_schema
    function tableExists($pdo, $table) {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'artee_attendance' AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetch();
    }

    // 1. Alter employees table
    if (!columnExists($pdo, 'employees', 'employment_type')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN employment_type VARCHAR(50) DEFAULT 'Full-time'");
        echo "✓ Added column 'employment_type' to 'employees'.\n";
    }
    if (!columnExists($pdo, 'employees', 'hire_date')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN hire_date DATE NULL");
        echo "✓ Added column 'hire_date' to 'employees'.\n";
    }
    if (!columnExists($pdo, 'employees', 'salary_grade')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN salary_grade VARCHAR(50) DEFAULT 'Grade A'");
        echo "✓ Added column 'salary_grade' to 'employees'.\n";
    }
    if (!columnExists($pdo, 'employees', 'emergency_contacts')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN emergency_contacts TEXT NULL");
        echo "✓ Added column 'emergency_contacts' to 'employees'.\n";
    }
    if (!columnExists($pdo, 'employees', 'deleted_at')) {
        $pdo->exec("ALTER TABLE employees ADD COLUMN deleted_at DATETIME NULL");
        echo "✓ Added column 'deleted_at' to 'employees'.\n";
    }

    // 2. Alter attendance_logs table
    if (!columnExists($pdo, 'attendance_logs', 'log_type')) {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN log_type VARCHAR(50) DEFAULT 'Regular'");
        echo "✓ Added column 'log_type' to 'attendance_logs'.\n";
    }
    if (!columnExists($pdo, 'attendance_logs', 'auto_closed')) {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN auto_closed TINYINT(1) DEFAULT 0");
        echo "✓ Added column 'auto_closed' to 'attendance_logs'.\n";
    }
    if (!columnExists($pdo, 'attendance_logs', 'manager_approved')) {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN manager_approved TINYINT(1) DEFAULT 1");
        echo "✓ Added column 'manager_approved' to 'attendance_logs'.\n";
    }
    if (!columnExists($pdo, 'attendance_logs', 'is_late')) {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN is_late TINYINT(1) DEFAULT 0");
        echo "✓ Added column 'is_late' to 'attendance_logs'.\n";
    }
    if (!columnExists($pdo, 'attendance_logs', 'is_early_departure')) {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN is_early_departure TINYINT(1) DEFAULT 0");
        echo "✓ Added column 'is_early_departure' to 'attendance_logs'.\n";
    }
    if (!columnExists($pdo, 'attendance_logs', 'calculated_hours')) {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN calculated_hours DECIMAL(10,2) DEFAULT 0.00");
        echo "✓ Added column 'calculated_hours' to 'attendance_logs'.\n";
    }
    if (!columnExists($pdo, 'attendance_logs', 'calculated_overtime')) {
        $pdo->exec("ALTER TABLE attendance_logs ADD COLUMN calculated_overtime DECIMAL(10,2) DEFAULT 0.00");
        echo "✓ Added column 'calculated_overtime' to 'attendance_logs'.\n";
    }

    // 3. Alter stores table
    if (!columnExists($pdo, 'stores', 'shift_start')) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN shift_start TIME DEFAULT '09:00:00'");
        echo "✓ Added column 'shift_start' to 'stores'.\n";
    }
    if (!columnExists($pdo, 'stores', 'shift_end')) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN shift_end TIME DEFAULT '17:00:00'");
        echo "✓ Added column 'shift_end' to 'stores'.\n";
    }
    if (!columnExists($pdo, 'stores', 'max_shift_hours')) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN max_shift_hours INT DEFAULT 14");
        echo "✓ Added column 'max_shift_hours' to 'stores'.\n";
    }
    if (!columnExists($pdo, 'stores', 'region')) {
        $pdo->exec("ALTER TABLE stores ADD COLUMN region VARCHAR(100) DEFAULT 'East Coast'");
        echo "✓ Added column 'region' to 'stores'.\n";
    }

    // 4. Create store_holidays table
    if (!tableExists($pdo, 'store_holidays')) {
        $pdo->exec("CREATE TABLE store_holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            store_id INT NOT NULL,
            holiday_date DATE NOT NULL,
            holiday_name VARCHAR(150) NOT NULL,
            FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✓ Created table 'store_holidays'.\n";
    }

    // 5. Create employee_documents table
    if (!tableExists($pdo, 'employee_documents')) {
        $pdo->exec("CREATE TABLE employee_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL,
            document_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        echo "✓ Created table 'employee_documents'.\n";
    }

    echo "Migrations Completed Successfully!\n";

} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}
