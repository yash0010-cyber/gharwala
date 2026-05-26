<?php
/**
 * CSRF Token Manager
 * Handles CSRF token generation and validation
 */

class CSRFToken {
    private const TOKEN_SESSION_KEY = '_csrf_token';
    private const TOKEN_NAME = '_token';

    /**
     * Generate CSRF token
     */
    public static function generate(): string {
        if (!isset($_SESSION[self::TOKEN_SESSION_KEY]) || empty($_SESSION[self::TOKEN_SESSION_KEY])) {
            $_SESSION[self::TOKEN_SESSION_KEY] = Sanitizer::generateToken();
        }

        return $_SESSION[self::TOKEN_SESSION_KEY];
    }

    /**
     * Get CSRF token
     */
    public static function getToken(): string {
        return self::generate();
    }

    /**
     * Verify CSRF token
     */
    public static function verify(string $token): bool {
        if (!isset($_SESSION[self::TOKEN_SESSION_KEY])) {
            return false;
        }

        $result = hash_equals($_SESSION[self::TOKEN_SESSION_KEY], $token);
        
        if ($result) {
            // Regenerate token after verification
            $_SESSION[self::TOKEN_SESSION_KEY] = Sanitizer::generateToken();
        }

        return $result;
    }

    /**
     * Get HTML input field for CSRF token
     */
    public static function getField(): string {
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . self::getToken() . '">';
    }

    /**
     * Verify from request
     */
    public static function verifyFromRequest(): bool {
        $token = $_POST[self::TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return $token !== null && self::verify($token);
    }
}
