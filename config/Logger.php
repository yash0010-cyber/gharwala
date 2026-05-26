<?php
/**
 * Logger
 * Handles application logging
 */

class Logger {
    private static string $logDir;
    private const LOG_LEVELS = [
        'error' => 'ERROR',
        'warning' => 'WARNING',
        'info' => 'INFO',
        'debug' => 'DEBUG',
        'security' => 'SECURITY',
    ];

    /**
     * Initialize logger
     */
    public static function init(): void {
        self::$logDir = dirname(dirname(__FILE__)) . '/' . (Config::get('LOG_PATH', 'logs'));
        
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }

    /**
     * Log error message
     */
    public static function error(string $message, array $context = []): void {
        self::log('error', $message, $context);
    }

    /**
     * Log warning message
     */
    public static function warning(string $message, array $context = []): void {
        self::log('warning', $message, $context);
    }

    /**
     * Log info message
     */
    public static function info(string $message, array $context = []): void {
        self::log('info', $message, $context);
    }

    /**
     * Log debug message
     */
    public static function debug(string $message, array $context = []): void {
        if (Config::isDebug()) {
            self::log('debug', $message, $context);
        }
    }

    /**
     * Log security event
     */
    public static function security(string $message, array $context = []): void {
        self::log('security', $message, $context);
    }

    /**
     * Main logging method
     */
    private static function log(string $level, string $message, array $context = []): void {
        if (!isset(self::$logDir)) {
            self::init();
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelName = self::LOG_LEVELS[$level] ?? 'INFO';
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[$timestamp] [$levelName] $message$contextStr\n";
        
        $logFile = self::$logDir . '/app-' . date('Y-m-d') . '.log';
        
        try {
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            error_log($logMessage);
        }
    }
}

// Initialize logger
Logger::init();
