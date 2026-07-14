<?php
/**
 * QuickBill POS - User Model
 */

class UserModel {

    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findByUsername($username, $companyId = 1) {
        return $this->db->fetchOne(
            "SELECT u.*, r.name AS role_name, b.name AS branch_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN branches b ON b.id = u.branch_id
             WHERE u.username = ? AND u.company_id = ?",
            [$username, $companyId]
        );
    }

    public function findById($id) {
        return $this->db->fetchOne(
            "SELECT u.*, r.name AS role_name, b.name AS branch_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN branches b ON b.id = u.branch_id
             WHERE u.id = ?",
            [$id]
        );
    }

    public function updateLoginSuccess($userId, $ip) {
        $this->db->execute(
            "UPDATE users SET login_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
            [$ip, $userId]
        );
    }

    public function incrementLoginAttempts($userId, $attempts, $lockedUntil = null) {
        $this->db->execute(
            "UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?",
            [$attempts, $lockedUntil, $userId]
        );
    }
}
