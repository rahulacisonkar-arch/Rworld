<?php
require_once __DIR__ . '/db.php';

// Safe session startup
function session_start_safe() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name('ARTEE_UTILITY_SESSID');
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

// Escaping shorthand for XSS prevention
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Generate CSRF Token
function get_csrf_token() {
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF Token
function validate_csrf_token($token) {
    session_start_safe();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Print CSRF hidden input
function csrf_input() {
    echo '<input type="hidden" name="csrf_token" value="' . e(get_csrf_token()) . '">';
}

// Check logged in
function is_logged_in() {
    session_start_safe();
    return isset($_SESSION['user_id']);
}

// Require login
function require_login() {
    if (!is_logged_in()) {
        header("Location: index.php");
        exit;
    }
}

// Check role authorization
function require_role($allowedRoles) {
    require_login();
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
    if (!in_array($role, $allowedRoles)) {
        http_response_code(403);
        echo "<h1>403 Forbidden</h1><p>You do not have permission to access this resource.</p>";
        echo "<p><a href='logout.php' style='display: inline-block; padding: 8px 16px; background-color: #1E5AA8; color: white; text-decoration: none; border-radius: 4px; font-family: sans-serif; font-size: 0.9rem;'>Log Out / Switch Accounts</a></p>";
        exit;
    }
}

// Log Activity helper
function log_activity($action, $details = '') {
    global $pdo;
    session_start_safe();
    $userId = $_SESSION['user_id'] ?? 0;
    if ($userId > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $action, $details]);
        } catch (PDOException $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}

// Add notification
function add_notification($store_id, $title, $message) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (store_id, title, message) VALUES (?, ?, ?)");
        $stmt->execute([$store_id, $title, $message]);
    } catch (PDOException $e) {
        error_log("Failed to add notification: " . $e->getMessage());
    }
}

// Fetch unread notifications count
function get_unread_notifications_count() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// Update bill states to overdue if past due date
function check_overdue_bills() {
    global $pdo;
    try {
        $today = date('Y-m-d');
        // Find pending bills past due date
        $stmt = $pdo->prepare("SELECT b.id, b.store_id, b.due_date, b.amount, s.store_name, uc.utility_type 
                               FROM bills b 
                               JOIN stores s ON b.store_id = s.id 
                               JOIN utility_connections uc ON b.connection_id = uc.id
                               WHERE b.status = 'Pending' AND b.due_date < ?");
        $stmt->execute([$today]);
        $overdueBills = $stmt->fetchAll();

        if (!empty($overdueBills)) {
            $updateStmt = $pdo->prepare("UPDATE bills SET status = 'Overdue' WHERE id = ?");
            foreach ($overdueBills as $bill) {
                $updateStmt->execute([$bill['id']]);
                
                // Add system notification
                $title = "Overdue Bill Alert - " . $bill['store_name'];
                $msg = "The " . $bill['utility_type'] . " bill of $" . number_format($bill['amount'], 2) . " was due on " . $bill['due_date'] . " and is now OVERDUE.";
                add_notification($bill['store_id'], $title, $msg);
            }
        }
    } catch (PDOException $e) {
        error_log("Error checking overdue bills: " . $e->getMessage());
    }
}

// Get bills due in <= 3 days that are unpaid (fires alerts)
function get_due_bills_alerts() {
    global $pdo;
    try {
        $today = date('Y-m-d');
        $threeDaysLater = date('Y-m-d', strtotime('+3 days'));
        
        $stmt = $pdo->prepare("SELECT b.*, s.store_name, uc.utility_type, uc.provider_name, uc.account_number 
                               FROM bills b 
                               JOIN stores s ON b.store_id = s.id 
                               JOIN utility_connections uc ON b.connection_id = uc.id
                               WHERE b.status IN ('Pending', 'Overdue') AND b.due_date <= ?
                               ORDER BY b.due_date ASC");
        $stmt->execute([$threeDaysLater]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Simulated OCR Bill Parser
function parse_uploaded_bill($filePath, $fileName) {
    // Read the file name or basic content to mock OCR
    $fileNameLower = strtolower($fileName);
    
    // Default mock data
    $amount = 145.20;
    $dueDate = date('Y-m-d', strtotime('+7 days'));
    $statementDate = date('Y-m-d', strtotime('-23 days'));
    $utilityType = 'Electricity';
    $accountNumber = '';
    
    // Check filename for utility type keywords
    if (strpos($fileNameLower, 'water') !== false) {
        $utilityType = 'Water';
        $amount = 68.45;
    } elseif (strpos($fileNameLower, 'gas') !== false) {
        $utilityType = 'Gas';
        $amount = 112.30;
    } elseif (strpos($fileNameLower, 'phone') !== false || strpos($fileNameLower, 'telephone') !== false) {
        $utilityType = 'Telephone';
        $amount = 89.99;
    } elseif (strpos($fileNameLower, 'internet') !== false || strpos($fileNameLower, 'comcast') !== false || strpos($fileNameLower, 'spectrum') !== false) {
        $utilityType = 'Internet';
        $amount = 159.95;
    } elseif (strpos($fileNameLower, 'sewer') !== false) {
        $utilityType = 'Sewer';
        $amount = 45.00;
    }
    
    // Try to parse amount from filename if it has $123 or 123.45 pattern
    if (preg_match('/_(\d+(?:\.\d{2})?)/', $fileName, $matches)) {
        $amount = floatval($matches[1]);
    }
    
    // Try to parse date from filename if it has YYYY-MM-DD pattern
    if (preg_match('/(\d{4}-\d{2}-\d{2})/', $fileName, $matches)) {
        $dueDate = $matches[1];
        $statementDate = date('Y-m-d', strtotime($dueDate . ' -30 days'));
    }
    
    // If it's a PDF, read raw text streams to simulate extracting real content
    if (pathinfo($fileName, PATHINFO_EXTENSION) === 'pdf' && file_exists($filePath)) {
        $content = file_get_contents($filePath);
        // Find strings resembling account numbers, invoice dates, or total due
        if (preg_match('/Account[:\s]+([A-Z0-9\-]{5,15})/i', $content, $matches)) {
            $accountNumber = $matches[1];
        }
        if (preg_match('/Due\s+Date[:\s]+(\d{2}\/\d{2}\/\d{4})/i', $content, $matches)) {
            $dueDate = date('Y-m-d', strtotime($matches[1]));
            $statementDate = date('Y-m-d', strtotime($dueDate . ' -30 days'));
        }
        if (preg_match('/Total\s+Due[:\s]+\$?(\d+(?:\.\d{2})?)/i', $content, $matches)) {
            $amount = floatval($matches[1]);
        }
    }
    
    return [
        'utility_type' => $utilityType,
        'amount' => $amount,
        'due_date' => $dueDate,
        'statement_date' => $statementDate,
        'account_number' => $accountNumber,
        'is_ocr_parsed' => true
    ];
}
