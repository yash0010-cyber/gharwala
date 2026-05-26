<?php
/**
 * Authentication Service
 * Handles user registration, login, session management, and authorization
 */

class AuthService {
    private const SESSION_TIMEOUT = 3600; // 1 hour
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_TIME = 900; // 15 minutes

    /**
     * Initialize authentication
     */
    public static function init(): void {
        session_start();
        self::checkSessionTimeout();
    }

    /**
     * Register new user
     */
    public static function register(string $email, string $password, string $firstName, string $lastName, string $role = 'guest'): array {
        // Validate email
        if (!Sanitizer::isValidEmail($email)) {
            return ['success' => false, 'error' => 'Invalid email address'];
        }

        // Check if email already exists
        $existing = Database::queryOne(
            'SELECT id FROM users WHERE email = ?',
            [$email]
        );

        if ($existing) {
            Logger::security('Registration attempt with existing email: ' . $email);
            return ['success' => false, 'error' => 'Email already registered'];
        }

        // Validate password
        $passwordValidation = Sanitizer::validatePassword($password);
        if (!$passwordValidation['valid']) {
            return ['success' => false, 'error' => $passwordValidation['message']];
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        try {
            Database::execute(
                'INSERT INTO users (email, password, first_name, last_name, role, status) VALUES (?, ?, ?, ?, ?, ?)',
                [$email, $hashedPassword, $firstName, $lastName, $role, 'active']
            );

            $userId = Database::lastInsertId();
            Logger::info('New user registered: ' . $email);
            
            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
        } catch (Exception $e) {
            Logger::error('Registration error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Registration failed'];
        }
    }

    /**
     * Authenticate user
     */
    public static function login(string $email, string $password): array {
        // Check login attempts
        $attempts = self::getLoginAttempts($email);
        if ($attempts['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            if (time() - $attempts['last_attempt'] < self::LOCKOUT_TIME) {
                Logger::security('Login attempt during lockout: ' . $email);
                return ['success' => false, 'error' => 'Too many login attempts. Please try again later.'];
            }
            self::resetLoginAttempts($email);
        }

        // Find user
        $user = Database::queryOne(
            'SELECT id, email, password, first_name, role, status FROM users WHERE email = ?',
            [$email]
        );

        if (!$user) {
            self::recordLoginAttempt($email);
            Logger::security('Failed login attempt - user not found: ' . $email);
            return ['success' => false, 'error' => 'Invalid email or password'];
        }

        // Check user status
        if ($user['status'] !== 'active') {
            Logger::security('Login attempt with inactive account: ' . $email);
            return ['success' => false, 'error' => 'Account is not active'];
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            self::recordLoginAttempt($email);
            Logger::security('Failed login attempt - invalid password: ' . $email);
            return ['success' => false, 'error' => 'Invalid email or password'];
        }

        // Login successful
        self::resetLoginAttempts($email);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();

        // Update last login
        Database::execute(
            'UPDATE users SET last_login = NOW() WHERE id = ?',
            [$user['id']]
        );

        Logger::info('User logged in: ' . $email);
        
        return ['success' => true, 'message' => 'Login successful', 'user' => $user];
    }

    /**
     * Logout user
     */
    public static function logout(): void {
        if (isset($_SESSION['email'])) {
            Logger::info('User logged out: ' . $_SESSION['email']);
        }
        
        session_destroy();
    }

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Get current user
     */
    public static function getCurrentUser(): ?array {
        if (!self::isAuthenticated()) {
            return null;
        }

        return Database::queryOne(
            'SELECT id, email, first_name, last_name, role, phone, profile_image, created_at FROM users WHERE id = ?',
            [$_SESSION['user_id']]
        );
    }

    /**
     * Check if user has role
     */
    public static function hasRole(string $role): bool {
        return self::isAuthenticated() && $_SESSION['role'] === $role;
    }

    /**
     * Check if user has any of the roles
     */
    public static function hasAnyRole(array $roles): bool {
        if (!self::isAuthenticated()) {
            return false;
        }
        return in_array($_SESSION['role'], $roles);
    }

    /**
     * Require role
     */
    public static function requireRole(string $role): void {
        if (!self::hasRole($role)) {
            http_response_code(403);
            ApiResponse::error('Access denied', 403);
        }
    }

    /**
     * Require authentication
     */
    public static function requireAuth(): void {
        if (!self::isAuthenticated()) {
            http_response_code(401);
            ApiResponse::error('Authentication required', 401);
        }
    }

    /**
     * Check session timeout
     */
    private static function checkSessionTimeout(): void {
        if (self::isAuthenticated()) {
            $loginTime = $_SESSION['login_time'] ?? 0;
            if (time() - $loginTime > self::SESSION_TIMEOUT) {
                self::logout();
            } else {
                $_SESSION['login_time'] = time();
            }
        }
    }

    /**
     * Get login attempts
     */
    private static function getLoginAttempts(string $email): array {
        $file = sys_get_temp_dir() . '/login_attempts_' . md5($email) . '.txt';
        
        if (!file_exists($file)) {
            return ['count' => 0, 'last_attempt' => 0];
        }

        $data = json_decode(file_get_contents($file), true);
        return $data ?? ['count' => 0, 'last_attempt' => 0];
    }

    /**
     * Record login attempt
     */
    private static function recordLoginAttempt(string $email): void {
        $file = sys_get_temp_dir() . '/login_attempts_' . md5($email) . '.txt';
        $attempts = self::getLoginAttempts($email);
        $attempts['count']++;
        $attempts['last_attempt'] = time();
        file_put_contents($file, json_encode($attempts));
    }

    /**
     * Reset login attempts
     */
    private static function resetLoginAttempts(string $email): void {
        $file = sys_get_temp_dir() . '/login_attempts_' . md5($email) . '.txt';
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
