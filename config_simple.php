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

// Auto-load configuration check
if (!testDatabaseConnection()) {
    $errorPage = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-icon { font-size: 48px; color: #dc3545; text-align: center; margin-bottom: 20px; }
        h1 { color: #dc3545; text-align: center; }
        .config-box { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .config-box code { background: #e9ecef; padding: 2px 5px; border-radius: 3px; }
        .step { margin: 15px 0; padding: 15px; background: #d1ecf1; border-left: 4px solid #17a2b8; border-radius: 0 5px 5px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">⚠️</div>
        <h1>Database Connection Error</h1>
        <p>The Production Management System cannot connect to the database. Please check your configuration.</p>

        <div class="config-box">
            <h3>Current Configuration:</h3>
            <p><strong>Host:</strong> <code>' . DB_HOST . '</code></p>
            <p><strong>Database:</strong> <code>' . DB_NAME . '</code></p>
            <p><strong>Username:</strong> <code>' . DB_USER . '</code></p>
            <p><strong>Password:</strong> <code>' . (empty(DB_PASS) ? '(empty)' : '***') . '</code></p>
        </div>

        <h3>Troubleshooting Steps:</h3>
        <div class="step">
            <strong>1. Check MySQL Server:</strong> Ensure MySQL is running and accessible.
        </div>
        <div class="step">
            <strong>2. Verify Database Exists:</strong> Create the database if it doesn\'t exist:
            <code>CREATE DATABASE ' . DB_NAME . ';</code>
        </div>
        <div class="step">
            <strong>3. Check Credentials:</strong> Verify the username and password are correct.
        </div>
        <div class="step">
            <strong>4. Run Migration:</strong> Execute the database migration script:
            <a href="database_migration_fix.php" class="btn">Run Migration Fix</a>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="javascript:location.reload()" class="btn">Refresh Page</a>
        </div>
    </div>
</body>
</html>';

    die($errorPage);
}
?>