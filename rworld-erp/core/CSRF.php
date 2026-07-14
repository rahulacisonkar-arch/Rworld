<?php
/**
 * QuickBill POS - CSRF Token Handler
 */

class CSRF {

    const TOKEN_KEY = '_csrf_token';

    public static function generate() {
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    public static function validate($token) {
        if (empty($_SESSION[self::TOKEN_KEY]) || empty($token)) {
            return false;
        }
        $valid = hash_equals($_SESSION[self::TOKEN_KEY], $token);
        // Rotate token after each successful POST to prevent replay
        if ($valid && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            self::refresh();
        }
        return $valid;
    }

    public static function refresh() {
        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
    }
}
