<?php
/**
 * Simple Database Creation Script
 *
 * This script creates the basic database structure needed for the Production Management System.
 * Run this script first before accessing any other part of the system.
 */

// Database configuration - update these values
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'production_db';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Setup - Production Management System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { color: #17a2b8; padding: 10px; background: #d1ecf1; border-radius: 4px; margin: 10px 0; }
        h1 { color: #333; text-align: center; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🗄️ Database Setup</h1>
        <p>Creating database structure for Production Management System...</p>";

try {
    // First connect without selecting database
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<div class='success'>✅ Connected to MySQL server</div>";

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<div class='success'>✅ Database '$database' created or already exists</div>";

    // Select the database
    $pdo->exec("USE `$database`");

    echo "<div class='info'>📋 Creating tables...</div>";

    // 1. Create users table first
    $pdo->exec("DROP TABLE IF EXISTS users");
    $pdo->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        employee_id VARCHAR(100),
        role ENUM('operator', 'supervisor', 'manager', 'executive', 'admin') NOT NULL DEFAULT 'operator',
        department VARCHAR(100),
        is_active BOOLEAN DEFAULT TRUE,
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_role (role),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "<div class='success'>✅ Users table created</div>";

    // 2. Create production_lines table
    $pdo->exec("DROP TABLE IF EXISTS production_lines");
    $pdo->exec("CREATE TABLE production_lines (
        line_name VARCHAR(50) PRIMARY KEY,
        line_type VARCHAR(100),
        target_efficiency DECIMAL(5,2) DEFAULT 80.0,
        status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "<div class='success'>✅ Production lines table created</div>";

    // 3. Create daily_performance table
    $pdo->exec("DROP TABLE IF EXISTS daily_performance");
    $pdo->exec("CREATE TABLE daily_performance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        production_line VARCHAR(50) NOT NULL,
        shift VARCHAR(20),
        plan INT DEFAULT 0,
        actual_output INT DEFAULT 0,
        no_ot_mp INT DEFAULT 0,
        ot_mp INT DEFAULT 0,
        ot_hours DECIMAL(5,2) DEFAULT 0.0,

        INDEX idx_date_line (date, production_line),
        INDEX idx_production_line (production_line),
        FOREIGN KEY (production_line) REFERENCES production_lines(line_name) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "<div class='success'>✅ Daily performance table created</div>";

    // 4. Create production_alerts table
    $pdo->exec("DROP TABLE IF EXISTS production_alerts");
    $pdo->exec("CREATE TABLE production_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alert_type ENUM('info', 'warning', 'critical') NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        production_line VARCHAR(50),
        status ENUM('active', 'acknowledged', 'resolved') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        INDEX idx_status (status),
        INDEX idx_production_line (production_line)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "<div class='success'>✅ Production alerts table created</div>";

    // 5. Create quality_checkpoints table
    $pdo->exec("DROP TABLE IF EXISTS quality_checkpoints");
    $pdo->exec("CREATE TABLE quality_checkpoints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        checkpoint_name VARCHAR(255) NOT NULL,
        production_line VARCHAR(50),
        checkpoint_type ENUM('incoming', 'in_process', 'final') NOT NULL,
        target_value DECIMAL(10,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        INDEX idx_production_line (production_line),
        INDEX idx_checkpoint_type (checkpoint_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "<div class='success'>✅ Quality checkpoints table created</div>";

    // 6. Create maintenance_schedules table
    $pdo->exec("DROP TABLE IF EXISTS maintenance_schedules");
    $pdo->exec("CREATE TABLE maintenance_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        equipment_name VARCHAR(255) NOT NULL,
        production_line VARCHAR(50),
        maintenance_type ENUM('preventive', 'corrective') NOT NULL,
        next_maintenance DATE,
        status ENUM('scheduled', 'completed') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        INDEX idx_production_line (production_line),
        INDEX idx_next_maintenance (next_maintenance)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "<div class='success'>✅ Maintenance schedules table created</div>";

    // Insert default admin user
    $default_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, full_name, role, department) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@production.com', $default_password, 'System Administrator', 'admin', 'IT']);
    echo "<div class='success'>✅ Default admin user created</div>";

    // Insert sample production lines
    $lines = [
        ['Line 1', 'Assembly', 85.0],
        ['Line 2', 'Packaging', 90.0],
        ['Line 3', 'Quality Control', 95.0]
    ];

    foreach ($lines as $line) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO production_lines (line_name, line_type, target_efficiency) VALUES (?, ?, ?)");
        $stmt->execute($line);
    }
    echo "<div class='success'>✅ Sample production lines inserted</div>";

    echo "<div class='success' style='margin-top: 20px; padding: 20px;'>
        <h2>🎉 Database Setup Complete!</h2>
        <p>Your Production Management System database has been successfully created.</p>

        <h3>Default Login Information:</h3>
        <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>
            <strong>Username:</strong> admin<br>
            <strong>Password:</strong> admin123
        </div>

        <h3>Next Steps:</h3>
        <ol>
            <li><a href='index.php' class='btn'>🏠 Go to Main Dashboard</a></li>
            <li><a href='user_management_offline.php' class='btn'>👥 Manage Users</a></li>
            <li><a href='enhanced_dashboard_offline.php' class='btn'>📊 View Production Dashboard</a></li>
        </ol>

        <div class='step'>
            <strong>⚠️ Security Note:</strong> Please change the default admin password and create separate user accounts for production personnel.
        </div>
    </div>";

} catch(PDOException $e) {
    echo "<div class='error'>
        <h2>❌ Database Setup Failed</h2>
        <p><strong>Error:</strong> " . $e->getMessage() . "</p>

        <h3>Troubleshooting:</h3>
        <div class='step'>
            <strong>1. Check MySQL Server:</strong> Ensure MySQL is running.
            <pre>sudo service mysql start  # Linux
brew services start mysql     # macOS</pre>
        </div>

        <div class='step'>
            <strong>2. Check Credentials:</strong> Update database settings in this script.
            <pre>Current settings:
Host: $host
Username: $username
Database: $database</pre>
        </div>

        <div class='step'>
            <strong>3. Create Database Manually:</strong>
            <pre>mysql -u root -p
CREATE DATABASE $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</pre>
        </div>
    </div>";
} catch(Exception $e) {
    echo "<div class='error'>
        <h2>❌ System Error</h2>
        <p><strong>Error:</strong> " . $e->getMessage() . "</p>
    </div>";
}

echo "</div></body></html>";
?>