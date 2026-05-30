<?php
/**
 * ============================================================
 * Gunaso REST API – Database Connection (PDO)
 * बेसीशहर नगरपालिका
 * ============================================================
 * File   : api/database.php
 * Purpose: Provides a singleton PDO connection.
 *          Uses PDO exclusively – does NOT use mysqli.
 * ============================================================
 */

declare(strict_types=1);

if (!defined('API_ENTRY')) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Direct access not allowed.']));
}

class ApiDatabase
{
    /** @var PDO|null Singleton instance */
    private static ?PDO $instance = null;

    /**
     * Private constructor – use getInstance().
     */
    private function __construct() {}

    /**
     * Return the singleton PDO connection.
     * Creates the connection on first call.
     *
     * @throws RuntimeException if connection fails
     * @return PDO
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }
        return self::$instance;
    }

    /**
     * Build and configure the PDO connection.
     */
    private static function createConnection(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            API_DB_HOST,
            API_DB_NAME,
            API_DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,           // true native prepared statements
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'",
            PDO::ATTR_PERSISTENT         => false,           // no persistent connections in API context
            PDO::ATTR_TIMEOUT            => 5,               // 5-second connection timeout
        ];

        try {
            $pdo = new PDO($dsn, API_DB_USER, API_DB_PASS, $options);
            return $pdo;
        } catch (PDOException $e) {
            // Log the real error but never expose credentials/details to the client
            self::logConnectionError($e->getMessage());
            throw new RuntimeException('Database connection failed. Please try again later.');
        }
    }

    /**
     * Write a connection error to the error log file.
     */
    private static function logConnectionError(string $message): void
    {
        $entry = sprintf(
            "[%s] DB_CONNECT_ERROR: %s | IP: %s\n",
            date('Y-m-d H:i:s'),
            $message,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );

        @error_log($entry, 3, API_ERROR_LOG_FILE);
    }

    /**
     * Prevent cloning of the singleton.
     */
    private function __clone() {}
}
