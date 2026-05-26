<?php
/**
 * Input Sanitizer
 * Validates and sanitizes user input
 */

class Sanitizer {
    /**
     * Validate email address
     */
    public static function isValidEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number
     */
    public static function isValidPhone(string $phone): bool {
        return preg_match('/^[0-9\-\+\(\)\s]{10,20}$/', $phone) === 1;
    }

    /**
     * Validate URL
     */
    public static function isValidUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate password strength
     */
    public static function validatePassword(string $password): array {
        $minLength = 8;
        $hasUpper = preg_match('/[A-Z]/', $password);
        $hasLower = preg_match('/[a-z]/', $password);
        $hasNumber = preg_match('/[0-9]/', $password);
        $hasSpecial = preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\]/', $password);

        $errors = [];
        if (strlen($password) < $minLength) {
            $errors[] = 'Password must be at least ' . $minLength . ' characters';
        }
        if (!$hasUpper) {
            $errors[] = 'Password must contain uppercase letter';
        }
        if (!$hasLower) {
            $errors[] = 'Password must contain lowercase letter';
        }
        if (!$hasNumber) {
            $errors[] = 'Password must contain number';
        }

        return [
            'valid' => empty($errors),
            'message' => implode(', ', $errors),
            'errors' => $errors,
        ];
    }

    /**
     * Hash password
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Sanitize string input
     */
    public static function sanitizeString(string $input): string {
        return trim(stripslashes(htmlspecialchars($input, ENT_QUOTES, 'UTF-8')));
    }

    /**
     * Sanitize email
     */
    public static function sanitizeEmail(string $email): string {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitize integer
     */
    public static function sanitizeInt($value): int {
        return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Sanitize float
     */
    public static function sanitizeFloat($value): float {
        return (float)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Validate file upload
     */
    public static function validateFileUpload(array $file, array $allowedTypes = [], int $maxSize = 5242880): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error'];
        }

        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds maximum allowed'];
        }

        if (!empty($allowedTypes)) {
            $fileType = mime_content_type($file['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                return ['valid' => false, 'error' => 'File type not allowed'];
            }
        }

        return ['valid' => true];
    }

    /**
     * Generate safe filename
     */
    public static function safeFileName(string $filename): string {
        $filename = preg_replace('/[^a-z0-9_.-]/i', '', basename($filename));
        $filename = str_replace('..', '', $filename);
        return md5(time()) . '_' . $filename;
    }

    /**
     * Escape HTML for output
     */
    public static function escape(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Escape JSON
     */
    public static function escapeJson($data): string {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Generate random token
     */
    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }
}
