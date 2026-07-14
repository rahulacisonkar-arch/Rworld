<?php
require_once __DIR__ . '/config.php';

function session_start_safe() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_lifetime', SESSION_LIFETIME);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        
        // Secure Session Cookie parameters
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        session_set_cookie_params(SESSION_LIFETIME, '/', '', $secure, true);
        
        session_start();
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token() {
    session_start_safe();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    session_start_safe();
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_input() {
    echo '<input type="hidden" name="csrf_token" value="' . e(generate_csrf_token()) . '">';
}

function redirect_unauthenticated() {
    session_start_safe();
    if (!is_logged_in()) {
        header("Location: index.php");
        exit;
    }
}

// Forgotten Clock-out Mitigation & Shift Calculator
function calculate_shift_hours($loginTime, $logoutTime, $date, $totalBreakSeconds) {
    $totalShiftSeconds = 0;
    if ($logoutTime) {
        $totalShiftSeconds = strtotime($logoutTime) - strtotime($loginTime);
    } else {
        // Active shift logic
        if ($date === date('Y-m-d')) {
            // Checked in today: calculate up to current time (max 12 hours)
            $elapsed = time() - strtotime($loginTime);
            $totalShiftSeconds = min($elapsed, 12 * 3600);
        } else {
            // Forgotten check-out from a previous day: cap shift duration at 8 hours standard
            $totalShiftSeconds = 8 * 3600 + $totalBreakSeconds;
        }
    }
    $netSeconds = max(0, $totalShiftSeconds - $totalBreakSeconds);
    return round($netSeconds / 3600, 2);
}

// Auto-Close active shifts exceeding max hours limit
function process_auto_close_shifts($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT l.id, l.login_time, l.date, s.max_shift_hours 
            FROM attendance_logs l 
            JOIN stores s ON l.store_id = s.id 
            WHERE l.logout_time IS NULL
        ");
        $openLogs = $stmt->fetchAll();
        
        $currentTime = time();
        foreach ($openLogs as $log) {
            $loginTs = strtotime($log['login_time']);
            $maxHours = intval($log['max_shift_hours'] ?: 14);
            $maxSeconds = $maxHours * 3600;
            
            if (($currentTime - $loginTs) > $maxSeconds) {
                $autoLogoutTime = date('Y-m-d H:i:s', $loginTs + $maxSeconds);
                
                // Fetch breaks
                $stmtBreaks = $pdo->prepare("SELECT * FROM attendance_breaks WHERE log_id = ?");
                $stmtBreaks->execute([$log['id']]);
                $breaks = $stmtBreaks->fetchAll();
                $totalBreakSecs = 0;
                foreach ($breaks as $b) {
                    if ($b['break_end']) {
                        $totalBreakSecs += strtotime($b['break_end']) - strtotime($b['break_start']);
                    } else {
                        $totalBreakSecs += strtotime($autoLogoutTime) - strtotime($b['break_start']);
                        $stmtEndBreak = $pdo->prepare("UPDATE attendance_breaks SET break_end = ? WHERE id = ?");
                        $stmtEndBreak->execute([$autoLogoutTime, $b['id']]);
                    }
                }
                
                // Metrics
                $netSeconds = max(0, $maxSeconds - $totalBreakSecs);
                $calculatedHours = round($netSeconds / 3600, 2);
                $overtime = max(0.00, $calculatedHours - 8.00);
                
                $stmtUpdate = $pdo->prepare("
                    UPDATE attendance_logs 
                    SET logout_time = ?, 
                        status = 'Completed', 
                        auto_closed = 1, 
                        manager_approved = 0,
                        calculated_hours = ?,
                        calculated_overtime = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$autoLogoutTime, $calculatedHours, $overtime, $log['id']]);
            }
        }
    } catch (Exception $e) {
        // Log or suppress
    }
}

// Calculate attendance metrics like lateness, early departure, regular hours, overtime
function calculate_attendance_metrics($loginTime, $logoutTime, $date, $totalBreakSeconds, $shiftStart = '09:00:00', $shiftEnd = '17:00:00') {
    $loginTimeOnly = date('H:i:s', strtotime($loginTime));
    $shiftStartSecs = strtotime("1970-01-01 $shiftStart");
    $graceSecs = $shiftStartSecs + (15 * 60); // 15-minute grace
    $loginSecs = strtotime("1970-01-01 $loginTimeOnly");
    $isLate = ($loginSecs > $graceSecs) ? 1 : 0;
    
    $isEarly = 0;
    $totalHours = 0.00;
    if ($logoutTime) {
        $logoutTimeOnly = date('H:i:s', strtotime($logoutTime));
        $logoutSecs = strtotime("1970-01-01 $logoutTimeOnly");
        $shiftEndSecs = strtotime("1970-01-01 $shiftEnd");
        if ($logoutSecs < $shiftEndSecs) {
            $isEarly = 1;
        }
        $totalShiftSecs = strtotime($logoutTime) - strtotime($loginTime);
        $netSecs = max(0, $totalShiftSecs - $totalBreakSeconds);
        $totalHours = round($netSecs / 3600, 2);
    } else {
        $totalShiftSecs = time() - strtotime($loginTime);
        $netSecs = max(0, $totalShiftSecs - $totalBreakSeconds);
        $totalHours = round($netSecs / 3600, 2);
    }
    
    $overtime = max(0.00, $totalHours - 8.00);
    $regularHours = min(8.00, $totalHours);
    
    return [
        'is_late' => $isLate,
        'is_early_departure' => $isEarly,
        'total_hours' => $totalHours,
        'regular_hours' => $regularHours,
        'overtime' => $overtime
    ];
}

function get_setting($pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

function set_setting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$key, $value, $value]);
}

