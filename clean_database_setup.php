<?php
/**
 * Clean Database Setup Script
 *
 * This script completely removes all tables and constraints, then creates a fresh database structure.
 * Use this if you're getting foreign key constraint errors and want to start fresh.
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
    <title>Clean Database Setup - Production Management System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border-radius: 4px; margin: 10px 0; }
        .info { color: #17a2b8; padding: 10px; background: #d1ecf1; border-radius: 4px; margin: 10px 0; }
        h1 { color: #333; text-align: center; }
        .step { margin: 15px 0; padding: 15px; border-left: 4px solid #dc3545; background: #f8f9fa; border-radius: 0 5px 5px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
        .progress { width: 100%; height: 30px; background: #e9ecef; border-radius: 15px; overflow: hidden; margin: 15px 0; }
        .progress-bar { height: 100%; background: linear-gradient(45deg, #dc3545, #28a745); transition: width 0.5s ease; line-height: 30px; text-align: center; color: white; font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧹 Clean Database Setup</h1>
        <p><strong>⚠️ WARNING:</strong> This will completely remove all existing tables and data, then create a fresh database structure.</p>";

// Check if this is a confirmation or actual execution
$confirmed = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';

if (!$confirmed) {
    echo "
    <div class='error step'>
        <h3>⚠️ DESTRUCTIVE OPERATION WARNING</h3>
        <p>This operation will:</p>
        <ul>
            <li><strong>DELETE ALL EXISTING TABLES</strong> in the '$database' database</li>
            <li><strong>REMOVE ALL DATA</strong> permanently</li>
            <li><strong>RESET FOREIGN KEY CONSTRAINTS</strong></li>
            <li><strong>CREATE FRESH DATABASE STRUCTURE</strong></li>
        </ul>
        <p><strong>This cannot be undone!</strong></p>
    </div>

    <form method='POST' style='text-align: center; margin: 30px 0;'>
        <input type='hidden' name='confirm' value='yes'>
        <button type='submit' class='btn btn-danger' style='font-size: 18px; padding: 15px 30px;'>
            🗑️ YES - Delete All and Create Fresh Database
        </button>
    </form>

    <div class='info'>
        <h4>Alternative Options:</h4>
        <p>If you don't want to lose existing data, try these alternatives:</p>
        <div style='text-align: center; margin: 20px 0;'>
            <a href='database_diagnostic.php' class='btn'>🔍 Diagnose Current Database</a>
            <a href='safe_database_setup.php' class='btn'>🔧 Safe Setup (Preserves Data)</a>
            <a href='index.php' class='btn'>🏠 Return to Main</a>
        </div>
    </div>
    </div></body></html>";
    exit;
}

echo "<div class='step'>
    <h3>🗑️ Step 1: Cleaning Database (Destructive Operation)</h3>
    <p>Removing all existing tables and constraints...</p>
</div>";

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<div class='success'>✅ Connected to MySQL server</div>";

    // Drop and recreate database completely
    echo "<div class='warning'>⚠️ Dropping existing database...</div>";
    $pdo->exec("DROP DATABASE IF EXISTS `$database`");
    echo "<div class='success'>✅ Old database dropped</div>";

    echo "<div class='info'>📋 Creating fresh database...</div>";
    $pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$database`");
    echo "<div class='success'>✅ Fresh database created</div>";

    echo "<div class='step'>
        <h3>🏗️ Step 2: Creating Database Structure</h3>
        <p>Setting up all required tables for the Production Management System...</p>
    </div>";

    $progress = 0;
    $totalSteps = 6;

    // 1. Create users table
    echo "<div class='info'>👥 Creating users table...</div>";
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
    $progress++;
    echo "<div class='progress'><div class='progress-bar' style='width: " . ($progress/$totalSteps*100) . "%'>$progress/$totalSteps</div></div>";

    // 2. Create production_lines table
    echo "<div class='info'>🏭 Creating production_lines table...</div>";
    $pdo->exec("CREATE TABLE production_lines (
        line_name VARCHAR(50) PRIMARY KEY,
        line_type VARCHAR(100),
        target_efficiency DECIMAL(5,2) DEFAULT 80.0,
        status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<div class='success'>✅ Production lines table created</div>";
    $progress++;
    echo "<div class='progress'><div class='progress-bar' style='width: " . ($progress/$totalSteps*100) . "%'>$progress/$totalSteps</div></div>";

    // 3. Create daily_performance table
    echo "<div class='info'>📊 Creating daily_performance table...</div>";
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        INDEX idx_date_line (date, production_line),
        INDEX idx_production_line (production_line)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<div class='success'>✅ Daily performance table created</div>";
    $progress++;
    echo "<div class='progress'><div class='progress-bar' style='width: " . ($progress/$totalSteps*100) . "%'>$progress/$totalSteps</div></div>";

    // 4. Create production_alerts table
    echo "<div class='info'>🚨 Creating production_alerts table...</div>";
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
    $progress++;
    echo "<div class='progress'><div class='progress-bar' style='width: " . ($progress/$totalSteps*100) . "%'>$progress/$totalSteps</div></div>";

    // 5. Create quality_checkpoints table
    echo "<div class='info'>✅ Creating quality_checkpoints table...</div>";
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
    $progress++;
    echo "<div class='progress'><div class='progress-bar' style='width: " . ($progress/$totalSteps*100) . "%'>$progress/$totalSteps</div></div>";

    // 6. Create maintenance_schedules table
    echo "<div class='info'>🔧 Creating maintenance_schedules table...</div>";
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
    $progress++;
    echo "<div class='progress'><div class='progress-bar' style='width: " . ($progress/$totalSteps*100) . "%'>$progress/$totalSteps</div></div>";

    echo "<div class='step'>
        <h3>📋 Step 3: Adding Default Data</h3>
        <p>Creating default admin user and sample production data...</p>
    </div>";

    // Insert default admin user
    echo "<div class='info'>👤 Creating default admin user...</div>";
    $default_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, department) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@production.com', $default_password, 'System Administrator', 'admin', 'IT']);
    echo "<div class='success'>✅ Default admin user created</div>";

    // Insert sample production lines
    echo "<div class='info'>🏭 Creating sample production lines...</div>";
    $lines = [
        ['Line 1', 'Assembly', 85.0],
        ['Line 2', 'Packaging', 90.0],
        ['Line 3', 'Quality Control', 95.0],
        ['Line 4', 'Testing', 88.0],
        ['Line 5', 'Shipping', 92.0]
    ];

    foreach ($lines as $line) {
        $stmt = $pdo->prepare("INSERT INTO production_lines (line_name, line_type, target_efficiency) VALUES (?, ?, ?)");
        $stmt->execute($line);
    }
    echo "<div class='success'>✅ Sample production lines created</div>";

    // Insert some quality checkpoints
    echo "<div class='info'>✅ Creating quality checkpoints...</div>";
    $checkpoints = [
        ['Visual Inspection', 'ALL', 'in_process', 95.0],
        ['Dimension Check', 'ALL', 'in_process', 98.0],
        ['Functional Test', 'ALL', 'final', 100.0],
        ['Final Inspection', 'ALL', 'final', 100.0]
    ];

    foreach ($checkpoints as $checkpoint) {
        $stmt = $pdo->prepare("INSERT INTO quality_checkpoints (checkpoint_name, production_line, checkpoint_type, target_value) VALUES (?, ?, ?, ?)");
        $stmt->execute($checkpoint);
    }
    echo "<div class='success'>✅ Quality checkpoints created</div>";

    // Final verification
    echo "<div class='step'>
        <h3>🔍 Step 4: Final Verification</h3>
        <p>Verifying that all tables were created successfully...</p>
    </div>";

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $requiredTables = ['users', 'production_lines', 'daily_performance', 'production_alerts', 'quality_checkpoints', 'maintenance_schedules'];
    $allTablesCreated = true;

    foreach ($requiredTables as $table) {
        if (!in_array($table, $tables)) {
            $allTablesCreated = false;
            echo "<div class='error'>✗ Missing table: $table</div>";
        } else {
            echo "<div class='success'>✓ Table verified: $table</div>";
        }
    }

    echo "<div class='progress'><div class='progress-bar' style='width: 100%; background: #28a745;'>100% Complete</div></div>";

    if ($allTablesCreated) {
        echo "<div class='success' style='margin-top: 20px; padding: 20px; text-align: center;'>
            <h2>🎉 Clean Database Setup Complete!</h2>
            <p>Your Production Management System database has been successfully created from scratch.</p>

            <div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px auto; max-width: 400px; text-align: left;'>
                <h3>🔑 Login Information:</h3>
                <table style='width: 100%;'>
                    <tr><td><strong>Username:</strong></td><td>admin</td></tr>
                    <tr><td><strong>Password:</strong></td><td>admin123</td></tr>
                    <tr><td><strong>Role:</strong></td><td>Administrator</td></tr>
                </table>
            </div>

            <div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px auto; max-width: 500px; text-align: left; border-left: 4px solid #ffc107;'>
                <h4>⚠️ Security Reminder:</h4>
                <ul style='margin: 0; padding-left: 20px;'>
                    <li>Change the default password immediately</li>
                    <li>Create accounts for your team members</li>
                    <li>Assign appropriate roles to each user</li>
                </ul>
            </div>

            <div style='margin-top: 30px;'>
                <a href='index.php' class='btn btn-success' style='font-size: 18px; padding: 15px 30px;'>
                    🏠 Go to Production Dashboard
                </a>
                <a href='user_management_offline.php' class='btn' style='font-size: 18px; padding: 15px 30px; margin-left: 10px;'>
                    👥 Manage Users
                </a>
            </div>
        </div>";
    } else {
        echo "<div class='error' style='margin-top: 20px; padding: 20px;'>
            <h2>❌ Setup Incomplete</h2>
            <p>Some tables could not be created. Please review the errors above.</p>
        </div>";
    }

} catch(PDOException $e) {
    echo "<div class='error'>
        <h2>❌ Database Setup Failed</h2>
        <p><strong>Error:</strong> " . $e->getMessage() . "</p>

        <h3>Troubleshooting:</h3>
        <div class='step'>
            <strong>1. MySQL Server Status:</strong><br>
            <code>sudo service mysql status</code>
        </div>

        <div class='step'>
            <strong>2. Database Permissions:</strong><br>
            Ensure the MySQL user has DROP, CREATE, INSERT privileges
        </div>

        <div class='step'>
            <strong>3. Connection Test:</strong><br>
            <code>mysql -h $host -u $username -p</code><br>
            <code>SHOW DATABASES;</code>
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