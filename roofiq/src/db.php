<?php
/**
 * ROOFIQ AI ENTERPRISE — PDO Database Connection
 * PHP 7.0.1 Compatible
 */

require_once __DIR__ . '/config.php';

$pdo = null;

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Allow pages to handle missing DB gracefully
    error_log("ROOFIQ DB Connection Failed: " . $e->getMessage());
    $pdo = null;
}
