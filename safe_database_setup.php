<?php
/**
 * Safe Database Setup Script
 *
 * This script safely creates the database structure by handling foreign key constraints properly.
 * It checks existing tables and creates them without conflicts.
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
    <title>Safe Database Setup - Production Management System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border-radius: 4px; margin: 10px 0; }
        .info { color: #17a2b8; padding: 10px; background: #d1ecf1; border-radius: 4px; margin: 10px 0; }
        h1 { color: #333; text-align: center; }
        .step { margin: 15px 0; padding: 15px; border-left: 4px solid #007bff; background: #f8f9fa; border-radius: 0 5px 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .progress { width: 100%; height: 25px; background: #e9ecef; border-radius: 12px; overflow: hidden; margin: 15px 0; }
        .progress-bar { height: 100%; background: linear-gradient(45deg, #28a745, #20c997); transition: width 0.3s ease; line-height: 25px; text-align: center; color: white; font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Safe Database Setup</h1>
        <p>Creating database structure for Production Management System with constraint handling...</p>";

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<div class='success'>✅ Connected to MySQL server</div>";

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<div class='success'>✅ Database '$database' ready</div>";

    // Select the database
    $pdo->exec("USE `$database`");

    echo "<div class='info'>📋 Step 1: Checking existing tables...</div>";

    // Get existing tables
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<table>
        <tr><th>Table</th><th>Status</th><th>Action</th></tr>";

    $requiredTables = [
        'users' => 'user_management',
        'production_lines' => 'line_management',
        'daily_performance' => 'performance_data',
        'production_alerts' => 'alert_system',
        'quality_checkpoints' => 'quality_system',
        'maintenance_schedules' => 'maintenance_system'
    ];

    $tablesCreated = 0;
    $totalTables = count($requiredTables);

    foreach ($requiredTables as $tableName => $purpose) {
        $exists = in_array($tableName, $existingTables);
        if ($exists) {
            echo "<tr><td>$tableName</td><td><span style='color: #28a745;'>✓ EXISTS</span></td><td>Keeping existing table</td></tr>";
        } else {
            echo "<tr><td>$tableName</td><td><span style='color: #dc3545;'>✗ MISSING</span></td><td>Will be created</td></tr>";
        }
    }
    echo "</table>";

    echo "<div class='info'>📋 Step 2: Creating missing tables safely...</div>";

    // Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Create users table (if not exists)
    if (!in_array('users', $existingTables)) {
        try {
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
            $tablesCreated++;

        } catch (PDOException $e) {
            echo "<div class='error'>✗ Failed to create users table: " . $e->getMessage() . "</div>";
        }
    } else {
        // Check if users table has email column
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
            if ($stmt->rowCount() == 0) {
                // Add email column if missing
                $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) UNIQUE AFTER username");
                echo "<div class='warning'>⚠️ Added missing email column to users table</div>";
            }
        } catch (PDOException $e) {
            echo "<div class='warning'>⚠️ Could not check users table structure</div>";
        }
        $tablesCreated++;
    }

    // 2. Create production_lines table (if not exists)
    if (!in_array('production_lines', $existingTables)) {
        try {
            $pdo->exec("CREATE TABLE production_lines (
                line_name VARCHAR(50) PRIMARY KEY,
                line_type VARCHAR(100),
                target_efficiency DECIMAL(5,2) DEFAULT 80.0,
                status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            echo "<div class='success'>✅ Production lines table created</div>";
            $tablesCreated++;

        } catch (PDOException $e) {
            echo "<div class='error'>✗ Failed to create production_lines table: " . $e->getMessage() . "</div>";
        }
    } else {
        $tablesCreated++;
    }

    // 3. Create daily_performance table (if not exists)
    if (!in_array('daily_performance', $existingTables)) {
        try {
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
            $tablesCreated++;

        } catch (PDOException $e) {
            echo "<div class='error'>✗ Failed to create daily_performance table: " . $e->getMessage() . "</div>";
        }
    } else {
        $tablesCreated++;
    }

    // 4. Create production_alerts table (if not exists)
    if (!in_array('production_alerts', $existingTables)) {
        try {
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
            $tablesCreated++;

        } catch (PDOException $e) {
            echo "<div class='error'>✗ Failed to create production_alerts table: " . $e->getMessage() . "</div>";
        }
    } else {
        $tablesCreated++;
    }

    // 5. Create quality_checkpoints table (if not exists)
    if (!in_array('quality_checkpoints', $existingTables)) {
        try {
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
            $tablesCreated++;

        } catch (PDOException $e) {
            echo "<div class='error'>✗ Failed to create quality_checkpoints table: " . $e->getMessage() . "</div>";
        }
    } else {
        $tablesCreated++;
    }

    // 6. Create maintenance_schedules table (if not exists)
    if (!in_array('maintenance_schedules', $existingTables)) {
        try {
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
            $tablesCreated++;

        } catch (PDOException $e) {
            echo "<div class='error'>✗ Failed to create maintenance_schedules table: " . $e->getMessage() . "</div>";
        }
    } else {
        $tablesCreated++;
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Progress bar
    $progress = ($tablesCreated / $totalTables) * 100;
    echo "<div class='progress'>
        <div class='progress-bar' style='width: $progress%'>$tablesCreated/$totalTables Tables Ready</div>
    </div>";

    echo "<div class='info'>📋 Step 3: Adding default data...</div>";

    // Insert default admin user if not exists
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
        $adminCount = $stmt->fetch()['count'];

        if ($adminCount == 0) {
            $default_password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, role, department) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['admin', 'admin@production.com', $default_password, 'System Administrator', 'admin', 'IT']);
            echo "<div class='success'>✅ Default admin user created</div>";
        } else {
            echo "<div class='info'>✓ Admin user already exists</div>";
        }

    } catch (PDOException $e) {
        echo "<div class='warning'>⚠️ Could not create admin user: " . $e->getMessage() . "</div>";
    }

    // Insert sample production lines if none exist
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM production_lines");
        $lineCount = $stmt->fetch()['count'];

        if ($lineCount == 0) {
            $lines = [
                ['Line 1', 'Assembly', 85.0],
                ['Line 2', 'Packaging', 90.0],
                ['Line 3', 'Quality Control', 95.0],
                ['Line 4', 'Testing', 88.0],
                ['Line 5', 'Shipping', 92.0]
            ];

            foreach ($lines as $line) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO production_lines (line_name, line_type, target_efficiency) VALUES (?, ?, ?)");
                $stmt->execute($line);
            }
            echo "<div class='success'>✅ Sample production lines inserted</div>";
        } else {
            echo "<div class='info'>✓ Production lines already exist ($lineCount records)</div>";
        }

    } catch (PDOException $e) {
        echo "<div class='warning'>⚠️ Could not insert production lines: " . $e->getMessage() . "</div>";
    }

    // Final verification
    echo "<div class='info'>📋 Step 4: Final verification...</div>";

    $stmt = $pdo->query("SHOW TABLES");
    $finalTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $verificationSuccess = 0;
    foreach ($requiredTables as $tableName => $purpose) {
        if (in_array($tableName, $finalTables)) {
            $verificationSuccess++;
        }
    }

    $finalProgress = ($verificationSuccess / $totalTables) * 100;

    if ($finalProgress >= 100) {
        echo "<div class='success' style='margin-top: 20px; padding: 20px;'>
            <h2>🎉 Database Setup Successful!</h2>
            <p>Your Production Management System database has been successfully created and configured.</p>

            <div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 15px 0;'>
                <h3>Default Login Information:</h3>
                <table style='max-width: 400px; margin: 10px auto;'>
                    <tr><td><strong>Username:</strong></td><td>admin</td></tr>
                    <tr><td><strong>Password:</strong></td><td>admin123</td></tr>
                    <tr><td><strong>Role:</strong></td><td>Administrator</td></tr>
                </table>
            </div>

            <div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #17a2b8;'>
                <h4>⚠️ Important Security Note:</h4>
                <ul style='margin: 0; padding-left: 20px;'>
                    <li>Change the default admin password immediately</li>
                    <li>Create separate accounts for production personnel</li>
                    <li>Use strong passwords for all user accounts</li>
                </ul>
            </div>

            <h3>Quick Access Links:</h3>
            <div style='text-align: center; margin: 20px 0;'>
                <a href='index.php' class='btn' style='background: #28a745; font-size: 16px; padding: 12px 25px;'>🏠 Main Dashboard</a>
                <a href='user_management_offline.php' class='btn' style='background: #17a2b8; font-size: 16px; padding: 12px 25px;'>👥 User Management</a>
                <a href='enhanced_dashboard_offline.php' class='btn' style='background: #007bff; font-size: 16px; padding: 12px 25px;'>📊 Production Dashboard</a>
            </div>
        </div>";
    } else {
        echo "<div class='error' style='margin-top: 20px; padding: 20px;'>
            <h2>⚠️ Database Setup Incomplete</h2>
            <p>Some tables could not be created. Please review the errors above.</p>
            <p><strong>Progress:</strong> $verificationSuccess/$totalTables tables created</p>
        </div>";
    }

} catch(PDOException $e) {
    echo "<div class='error'>
        <h2>❌ Database Setup Failed</h2>
        <p><strong>Error:</strong> " . $e->getMessage() . "</p>

        <h3>Troubleshooting Steps:</h3>
        <div class='step'>
            <strong>1. MySQL Server Status:</strong> Ensure MySQL is running<br>
            <code>sudo service mysql status</code> (Linux) or <code>brew services list | grep mysql</code> (macOS)
        </div>

        <div class='step'>
            <strong>2. Database Permissions:</strong> Verify user has CREATE, INSERT, UPDATE, DELETE privileges
        </div>

        <div class='step'>
            <strong>3. Manual Database Creation:</strong>
            <pre style='background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto;'>mysql -u $username -p
CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SHOW DATABASES;</pre>
        </div>

        <div class='step'>
            <strong>4. Configuration Check:</strong> Update database settings in this script
            <pre style='background: #f8f9fa; padding: 10px; border-radius: 4px;'>Host: $host
Username: $username
Database: $database
Password: " . (empty($password) ? '(empty)' : '***') . "</pre>
        </div>
    </div>";
} catch(Exception $e) {
    echo "<div class='error'>
        <h2>❌ System Error</h2>
        <p><strong>Error:</strong> " . $e->getMessage() . "</p>
        <p>Please check your PHP configuration and ensure the MySQL extension is enabled.</p>
    </div>";
}

echo "</div></body></html>";
?>