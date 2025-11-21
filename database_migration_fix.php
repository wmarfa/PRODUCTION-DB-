<?php
/**
 * Database Migration Fix Script
 *
 * This script fixes database issues and ensures all required tables and columns exist.
 * It handles migration from existing database structure to the enhanced version.
 */

// Database configuration - update these values
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'production_db';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Migration Fix</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .info { color: #17a2b8; padding: 10px; background: #d1ecf1; border-radius: 4px; margin: 10px 0; }
        .progress { width: 100%; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-bar { height: 100%; background: #28a745; transition: width 0.3s ease; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Database Migration & Fix Script</h1>
        <p>This script will fix database issues and ensure all required tables and columns exist.</p>";

    echo "<div class='info'>
        <strong>Database Connection:</strong> Connected to $database on $host
    </div>";

    // Step 1: Check existing tables
    echo "<h2>📋 Step 1: Checking Existing Tables</h2>";
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<table>
        <tr><th>Table Name</th><th>Status</th></tr>";

    $requiredTables = [
        'production_lines',
        'daily_performance',
        'production_alerts',
        'quality_checkpoints',
        'maintenance_schedules',
        'user_management',
        'users',
        'quality_measurements',
        'production_bottlenecks',
        'shift_handovers',
        'production_forecasts',
        'equipment_utilization',
        'supplier_quality',
        'user_activity_log',
        'kpi_definitions',
        'kpi_history'
    ];

    foreach ($requiredTables as $table) {
        $exists = in_array($table, $existingTables);
        $status = $exists ? "<span style='color: #28a745;'>✓ EXISTS</span>" : "<span style='color: #dc3545;'>✗ MISSING</span>";
        echo "<tr><td>$table</td><td>$status</td></tr>";
    }
    echo "</table>";

    // Step 2: Fix user_management table issues
    echo "<h2>👥 Step 2: Fixing User Management Tables</h2>";

    // Check if user_management table exists and what columns it has
    if (in_array('user_management', $existingTables)) {
        echo "<div class='info'>Found existing user_management table</div>";

        $stmt = $pdo->query("DESCRIBE user_management");
        $userManagementColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Add missing columns to user_management table
        $columnsToAdd = [
            'email' => "ADD COLUMN email VARCHAR(255) UNIQUE AFTER username",
            'password_hash' => "ADD COLUMN password_hash VARCHAR(255) AFTER email",
            'full_name' => "ADD COLUMN full_name VARCHAR(255) AFTER password_hash",
            'role' => "ADD COLUMN role ENUM('operator', 'supervisor', 'manager', 'executive', 'admin') DEFAULT 'operator' AFTER full_name",
            'is_active' => "ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER role",
            'last_login' => "ADD COLUMN last_login TIMESTAMP NULL AFTER is_active",
            'created_at' => "ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER last_login"
        ];

        foreach ($columnsToAdd as $column => $sql) {
            if (!in_array($column, $userManagementColumns)) {
                try {
                    $pdo->exec("ALTER TABLE user_management $sql");
                    echo "<div class='success'>✓ Added $column column to user_management table</div>";
                } catch (PDOException $e) {
                    echo "<div class='error'>✗ Failed to add $column: " . $e->getMessage() . "</div>";
                }
            } else {
                echo "<div class='info'>✓ $column column already exists in user_management</div>";
            }
        }
    }

    // Step 3: Create users table if it doesn't exist
    echo "<h2>🆔 Step 3: Creating Users Table</h2>";

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
                production_lines TEXT,

                is_active BOOLEAN DEFAULT TRUE,
                last_login TIMESTAMP NULL,
                password_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                failed_login_attempts INT DEFAULT 0,
                account_locked_until TIMESTAMP NULL,

                two_factor_enabled BOOLEAN DEFAULT FALSE,
                two_factor_secret VARCHAR(255),

                timezone VARCHAR(50) DEFAULT 'UTC',
                date_format VARCHAR(20) DEFAULT 'Y-m-d',
                time_format VARCHAR(10) DEFAULT 'H:i:s',
                language VARCHAR(10) DEFAULT 'en',

                phone VARCHAR(50),
                alternate_email VARCHAR(255),

                emergency_contact_name VARCHAR(255),
                emergency_contact_phone VARCHAR(50),

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_role (role),
                INDEX idx_is_active (is_active),
                INDEX idx_employee_id (employee_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            echo "<div class='success'>✅ Users table created successfully</div>";

            // Insert default admin user
            $default_admin_password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, full_name, role, department)
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['admin', 'admin@production.com', $default_admin_password, 'System Administrator', 'admin', 'IT']);
            echo "<div class='success'>✅ Default admin user created (username: admin, password: admin123)</div>";

        } catch (PDOException $e) {
            echo "<div class='error'>✗ Failed to create users table: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='info'>✓ Users table already exists</div>";
    }

    // Step 4: Create missing tables
    echo "<h2>🏗️ Step 4: Creating Missing Tables</h2>";

    $tableDefinitions = [
        'production_alerts' => "
            CREATE TABLE IF NOT EXISTS production_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                alert_type ENUM('info', 'warning', 'critical') NOT NULL,
                alert_category ENUM('performance', 'quality', 'equipment', 'manpower', 'schedule') NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                production_line VARCHAR(50),
                shift VARCHAR(20),
                alert_threshold DECIMAL(10,2),
                current_value DECIMAL(10,2),
                severity_score INT DEFAULT 1,
                status ENUM('active', 'acknowledged', 'resolved', 'escalated') DEFAULT 'active',
                acknowledged_by VARCHAR(100),
                acknowledged_at TIMESTAMP NULL,
                resolved_by VARCHAR(100),
                resolved_at TIMESTAMP NULL,
                resolution_notes TEXT,
                auto_escalate BOOLEAN DEFAULT TRUE,
                escalation_level INT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_alert_status (status),
                INDEX idx_production_line (production_line),
                INDEX idx_alert_type (alert_type),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'quality_checkpoints' => "
            CREATE TABLE IF NOT EXISTS quality_checkpoints (
                id INT AUTO_INCREMENT PRIMARY KEY,
                checkpoint_name VARCHAR(255) NOT NULL,
                production_line VARCHAR(50),
                checkpoint_type ENUM('incoming', 'in_process', 'final', 'outgoing') NOT NULL,
                quality_standard VARCHAR(255),
                tolerance_level DECIMAL(5,2),
                measurement_unit VARCHAR(50),
                frequency ENUM('hourly', 'shift', 'daily', 'batch') DEFAULT 'shift',
                target_value DECIMAL(10,2),
                min_acceptable DECIMAL(10,2),
                max_acceptable DECIMAL(10,2),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_production_line (production_line),
                INDEX idx_checkpoint_type (checkpoint_type),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'maintenance_schedules' => "
            CREATE TABLE IF NOT EXISTS maintenance_schedules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                equipment_name VARCHAR(255) NOT NULL,
                equipment_id VARCHAR(100),
                production_line VARCHAR(50),
                maintenance_type ENUM('preventive', 'corrective', 'predictive', 'emergency') NOT NULL,
                frequency_type ENUM('hours', 'days', 'weeks', 'months', 'cycles', 'usage_based') NOT NULL,
                frequency_interval INT NOT NULL,
                last_maintenance DATE,
                next_maintenance DATE,
                estimated_duration_hours DECIMAL(5,2),
                priority_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                maintenance_cost DECIMAL(10,2),
                spare_parts_required TEXT,
                maintenance_procedure TEXT,
                assigned_technician VARCHAR(100),
                status ENUM('scheduled', 'in_progress', 'completed', 'overdue', 'cancelled') DEFAULT 'scheduled',
                completion_notes TEXT,
                actual_duration_hours DECIMAL(5,2),
                actual_cost DECIMAL(10,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_equipment_id (equipment_id),
                INDEX idx_production_line (production_line),
                INDEX idx_next_maintenance (next_maintenance),
                INDEX idx_status (status),
                INDEX idx_priority_level (priority_level)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($tableDefinitions as $tableName => $sql) {
        if (!in_array($tableName, $existingTables)) {
            try {
                $pdo->exec($sql);
                echo "<div class='success'>✅ $tableName table created successfully</div>";
            } catch (PDOException $e) {
                echo "<div class='error'>✗ Failed to create $tableName table: " . $e->getMessage() . "</div>";
            }
        } else {
            echo "<div class='info'>✓ $tableName table already exists</div>";
        }
    }

    // Step 5: Insert basic data
    echo "<h2>📊 Step 5: Inserting Basic Data</h2>";

    // Insert sample production lines if they don't exist
    if (in_array('production_lines', $existingTables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM production_lines");
        $count = $stmt->fetch()['count'];

        if ($count == 0) {
            $sampleLines = [
                ['Line 1', 'Assembly', 85.0],
                ['Line 2', 'Packaging', 90.0],
                ['Line 3', 'Quality Control', 95.0]
            ];

            foreach ($sampleLines as $line) {
                $stmt = $pdo->prepare("INSERT INTO production_lines (line_name, line_type, target_efficiency) VALUES (?, ?, ?)");
                $stmt->execute($line);
            }
            echo "<div class='success'>✅ Sample production lines inserted</div>";
        } else {
            echo "<div class='info'>✓ Production lines already exist ($count records)</div>";
        }
    }

    // Insert quality checkpoints if they don't exist
    if (in_array('quality_checkpoints', $existingTables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM quality_checkpoints");
        $count = $stmt->fetch()['count'];

        if ($count == 0) {
            $checkpoints = [
                ['Visual Inspection', 'ALL', 'in_process', 'No visual defects', 100, 2.00, 'percentage', 'hourly'],
                ['Dimension Check', 'ALL', 'in_process', 'Within tolerance ±0.1mm', 100, 0.10, 'mm', 'batch'],
                ['Functional Test', 'ALL', 'final', '100% functional', 100, 0.00, 'percentage', 'shift'],
                ['Final Inspection', 'ALL', 'outgoing', 'Package integrity', 100, 1.00, 'percentage', 'hourly']
            ];

            foreach ($checkpoints as $checkpoint) {
                $stmt = $pdo->prepare("INSERT INTO quality_checkpoints (checkpoint_name, production_line, checkpoint_type, quality_standard, target_value, tolerance_level, measurement_unit, frequency) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute($checkpoint);
            }
            echo "<div class='success'>✅ Sample quality checkpoints inserted</div>";
        } else {
            echo "<div class='info'>✓ Quality checkpoints already exist ($count records)</div>";
        }
    }

    // Step 6: Final verification
    echo "<h2>✅ Step 6: Final Verification</h2>";

    $stmt = $pdo->query("SHOW TABLES");
    $finalTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $successCount = 0;
    $totalRequired = count($requiredTables);

    foreach ($requiredTables as $table) {
        if (in_array($table, $finalTables)) {
            $successCount++;
        }
    }

    $successPercentage = ($successCount / $totalRequired) * 100;

    echo "<div class='progress'>
        <div class='progress-bar' style='width: $successPercentage%'></div>
    </div>";

    echo "<div class='success'>
        <strong>Migration Status: $successCount/$totalRequired tables ready (" . round($successPercentage, 1) . "%)</strong>
    </div>";

    if ($successPercentage >= 90) {
        echo "<div class='success'>
            <h3>🎉 Migration Successful!</h3>
            <p>Your database has been successfully updated and is ready for use with the Production Management System.</p>
            <p><strong>Default Login:</strong><br>
            Username: <strong>admin</strong><br>
            Password: <strong>admin123</strong></p>
        </div>";
    } else {
        echo "<div class='error'>
            <h3>⚠️ Migration Incomplete</h3>
            <p>Some tables could not be created. Please check the error messages above and fix any issues.</p>
        </div>";
    }

    echo "<h2>🔗 Quick Links</h2>";
    echo "<p>
        <a href='index.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>🏠 Main Dashboard</a>
        <a href='system_verification.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;'>🔧 System Verification</a>
        <a href='user_management_offline.php' style='display: inline-block; padding: 10px 20px; background: #17a2b8; color: white; text-decoration: none; border-radius: 4px;'>👥 User Management</a>
    </p>";

    echo "</div></body></html>";

} catch(PDOException $e) {
    echo "<div style='max-width: 800px; margin: 20px auto; padding: 20px; background: #f8d7da; border-radius: 8px;'>
        <h2>❌ Database Connection Failed</h2>
        <p><strong>Error:</strong> " . $e->getMessage() . "</p>
        <p><strong>Solutions:</strong></p>
        <ul>
            <li>Check that MySQL server is running</li>
            <li>Verify database connection details in this script</li>
            <li>Ensure database '$database' exists</li>
            <li>Check user permissions for the database</li>
        </ul>
        <p><strong>Connection Details Used:</strong><br>
        Host: $host<br>
        Database: $database<br>
        Username: $username</p>
    </div>";
}
?>