<?php
/**
 * Database Manager
 * Handles database connections and queries using PDO
 */

class Database {
    private static ?PDO $connection = null;
    private static string $host;
    private static string $dbname;
    private static string $user;
    private static string $password;

    /**
     * Initialize database configuration
     */
    public static function init(): void {
        self::$host = Config::get('DB_HOST', 'localhost');
        self::$dbname = Config::get('DB_NAME', 'gharwala_db');
        self::$user = Config::get('DB_USER', 'root');
        self::$password = Config::get('DB_PASSWORD', '');
    }

    /**
     * Get database connection
     */
    public static function getConnection(): PDO {
        if (self::$connection === null) {
            self::init();
            
            try {
                $dsn = 'mysql:host=' . self::$host . ';dbname=' . self::$dbname . ';charset=utf8mb4';
                self::$connection = new PDO(
                    $dsn,
                    self::$user,
                    self::$password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                Logger::error('Database connection failed: ' . $e->getMessage());
                throw new Exception('Database connection error');
            }
        }

        return self::$connection;
    }

    /**
     * Execute query and return results
     */
    public static function query(string $sql, array $params = []): array {
        try {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            Logger::error('Database query error: ' . $e->getMessage());
            throw new Exception('Database query error');
        }
    }

    /**
     * Execute query and return single result
     */
    public static function queryOne(string $sql, array $params = []): ?array {
        $results = self::query($sql, $params);
        return $results[0] ?? null;
    }

    /**
     * Execute insert/update/delete query
     */
    public static function execute(string $sql, array $params = []): int {
        try {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            Logger::error('Database execute error: ' . $e->getMessage());
            throw new Exception('Database execute error');
        }
    }

    /**
     * Get last insert ID
     */
    public static function lastInsertId(): string {
        return self::getConnection()->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction(): void {
        self::getConnection()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit(): void {
        self::getConnection()->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollback(): void {
        self::getConnection()->rollBack();
    }
}
