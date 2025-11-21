<?php
/**
 * Simple Database Configuration for Production Management System
 *
 * Update these settings to match your MySQL database configuration.
 */

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'production_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Alternative: You can also use these variables if preferred
$db_host = 'localhost';
$db_name = 'production_db';
$db_user = 'root';
$db_password = '';

// System settings
define('SYSTEM_NAME', 'Production Management System');
define('SYSTEM_VERSION', '2.0.0');
define('TIMEZONE', 'UTC');

// Security settings
define('JWT_SECRET', 'your-secret-key-change-this-in-production');
define('SESSION_LIFETIME', 7200); // 2 hours
define('MAX_LOGIN_ATTEMPTS', 5);

// Performance settings
define('DASHBOARD_REFRESH_INTERVAL', 30); // seconds
define('EXPORT_BATCH_SIZE', 1000);

// Error reporting (disable in production)
if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Simple database connection function
function getDatabaseConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // Log error and show user-friendly message
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Database connection failed. Please check your configuration.");
    }
}

// Alternative Database class for compatibility
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }
}

// Helper function for backwards compatibility
if (!function_exists('getPDO')) {
    function getPDO() {
        return getDatabaseConnection();
    }
}

// Test connection function
function testDatabaseConnection() {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->query("SELECT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Note: Database connection testing is done in individual files to allow for database setup
?>