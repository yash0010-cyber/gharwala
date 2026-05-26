<?php
/**
 * Main Application Entry Point
 * Includes bootstrap code and routing logic
 */

// Define application root
define('APP_ROOT', dirname(__FILE__));

// Include configuration
require_once APP_ROOT . '/config/Config.php';
require_once APP_ROOT . '/config/Database.php';
require_once APP_ROOT . '/config/Logger.php';
require_once APP_ROOT . '/includes/AuthService.php';
require_once APP_ROOT . '/includes/Sanitizer.php';
require_once APP_ROOT . '/includes/CSRFToken.php';
require_once APP_ROOT . '/includes/ApiResponse.php';

// Initialize application
AuthService::init();

// Set error handling
if (!Config::isProduction()) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Set timezone
date_default_timezone_set(Config::get('APP_TIMEZONE', 'UTC'));

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// CORS headers (adjust domain in production)
header('Access-Control-Allow-Origin: ' . (Config::isProduction() ? Config::get('APP_URL') : '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Simple router
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '';

// Route requests
if (strpos($path, '/api/') === 0) {
    // API routing
    handleApiRoute($path);
} else {
    // Frontend routing
    handleWebRoute($path);
}

/**
 * Handle API routes
 */
function handleApiRoute(string $path): void {
    $parts = explode('/', trim($path, '/'));
    
    if ($parts[0] !== 'api') {
        http_response_code(404);
        ApiResponse::notFound('API endpoint not found');
    }

    $endpoint = $parts[1] ?? null;
    $action = $parts[2] ?? null;

    switch ($endpoint) {
        case 'auth':
            include APP_ROOT . '/api/auth/index.php';
            break;

        case 'properties':
            include APP_ROOT . '/api/properties/index.php';
            break;

        case 'bookings':
            include APP_ROOT . '/api/bookings/index.php';
            break;

        case 'reviews':
            include APP_ROOT . '/api/reviews/index.php';
            break;

        case 'admin':
            include APP_ROOT . '/api/admin/index.php';
            break;

        default:
            http_response_code(404);
            ApiResponse::notFound('API endpoint not found');
    }
}

/**
 * Handle web routes
 */
function handleWebRoute(string $path): void {
    // Remove trailing slash
    $path = rtrim($path, '/');
    if (empty($path)) $path = '/';

    $pageMap = [
        '/' => '/pages/index.php',
        '/properties' => '/pages/properties.php',
        '/properties/' => '/pages/properties.php',
        '/login' => '/pages/login.php',
        '/register' => '/pages/register.php',
        '/dashboard' => '/pages/dashboard.php',
        '/admin' => '/admin/dashboard.php',
        '/tenant' => '/tenant/dashboard.php',
    ];

    // Check for direct page match
    if (isset($pageMap[$path])) {
        $filePath = APP_ROOT . $pageMap[$path];
        if (file_exists($filePath)) {
            include $filePath;
            return;
        }
    }

    // Try to find matching file
    $filePath = APP_ROOT . $path . '.php';
    if (file_exists($filePath)) {
        include $filePath;
        return;
    }

    // 404 - Not found
    http_response_code(404);
    include APP_ROOT . '/pages/404.php';
}
