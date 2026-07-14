<?php
/**
 * QuickBill POS - Database Connection (PDO Singleton)
 * Compatible: PHP 7.0+ / MariaDB 10.x
 */

class Database {

    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = 'mysql:host=' . DB_HOST
             . ';port=' . DB_PORT
             . ';dbname=' . DB_NAME
             . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci,
                                              time_zone = '-05:00',
                                              sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE'"
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die('<pre style="background:#1e1e1e;color:#ff6b6b;padding:20px">
[QuickBill DB Error] Cannot connect to database.<br>
DSN  : ' . $dsn . '<br>
Error: ' . $e->getMessage() . '
</pre>');
            } else {
                die('Database connection error. Please contact administrator.');
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo() {
        return $this->pdo;
    }

    /**
     * Execute a prepared statement and return all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute and return single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Execute and return single column value
     */
    public function fetchColumn($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Execute INSERT/UPDATE/DELETE
     */
    public function execute($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Execute and return last inserted ID
     */
    public function insert($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }

    /**
     * Get the ID of the last inserted row
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }

    /**
     * Count rows
     */
    public function count($table, $where = '', $params = []) {
        $sql = "SELECT COUNT(*) FROM `$table`";
        if ($where) $sql .= " WHERE $where";
        return (int) $this->fetchColumn($sql, $params);
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {}
}
