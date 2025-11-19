<?php
/**
 * Database Diagnostic Tool
 *
 * This script helps diagnose database issues by showing the current state
 * and identifying problems with foreign key constraints.
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
    <title>Database Diagnostic - Production Management System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; padding: 10px; background: #d4edda; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border-radius: 4px; margin: 10px 0; }
        .info { color: #17a2b8; padding: 10px; background: #d1ecf1; border-radius: 4px; margin: 10px 0; }
        h1 { color: #333; text-align: center; }
        h2 { color: #495057; border-bottom: 2px solid #dee2e6; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .btn { display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 2px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Database Diagnostic Tool</h1>
        <p>Analyzing the current state of your Production Management System database...</p>";

try {
    // Test basic MySQL connection
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<div class='success'>✅ MySQL Connection: Successful</div>";

    // Check if database exists
    $stmt = $pdo->query("SHOW DATABASES LIKE '$database'");
    $dbExists = $stmt->rowCount() > 0;

    if ($dbExists) {
        echo "<div class='success'>✅ Database '$database': Exists</div>";

        // Connect to the specific database
        $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        echo "<div class='info'>📊 Database Analysis</div>";

        // Show all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo "<h2>📋 Current Tables</h2>";
        echo "<table>
            <tr><th>Table Name</th><th>Records</th><th>Size</th><th>Status</th></tr>";

        $totalTables = 0;
        $totalRecords = 0;

        foreach ($tables as $table) {
            // Get record count
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
                $count = $stmt->fetch()['count'];
            } catch (PDOException $e) {
                $count = 'Error';
            }

            // Get table size
            try {
                $stmt = $pdo->query("SHOW TABLE STATUS LIKE '$table'");
                $status = $stmt->fetch();
                $size = round($status['Data_length'] / 1024, 2) . ' KB';
            } catch (PDOException $e) {
                $size = 'Unknown';
            }

            $statusIcon = $count !== 'Error' ? '<span style="color: #28a745;">✓</span>' : '<span style="color: #dc3545;">✗</span>';

            echo "<tr>
                <td><code>$table</code></td>
                <td>$count</td>
                <td>$size</td>
                <td>$statusIcon</td>
            </tr>";

            if ($count !== 'Error') {
                $totalTables++;
                $totalRecords += is_numeric($count) ? $count : 0;
            }
        }

        echo "</table>";
        echo "<div class='info'>Summary: $totalTables tables, $totalRecords total records</div>";

        // Check specific required tables
        echo "<h2>🎯 Required Tables Check</h2>";
        $requiredTables = [
            'users' => 'User management and authentication',
            'production_lines' => 'Production line configuration',
            'daily_performance' => 'Daily production data',
            'production_alerts' => 'Alert and notification system',
            'quality_checkpoints' => 'Quality control checkpoints',
            'maintenance_schedules' => 'Maintenance planning'
        ];

        echo "<table>
            <tr><th>Table</th><th>Purpose</th><th>Status</th></tr>";

        $missingTables = [];
        foreach ($requiredTables as $table => $purpose) {
            $exists = in_array($table, $tables);
            $status = $exists ? '<span style="color: #28a745;">✓ EXISTS</span>' : '<span style="color: #dc3545;">✗ MISSING</span>';

            if (!$exists) {
                $missingTables[] = $table;
            }

            echo "<tr>
                <td><code>$table</code></td>
                <td>$purpose</td>
                <td>$status</td>
            </tr>";
        }
        echo "</table>";

        // Check foreign key constraints
        echo "<h2>🔗 Foreign Key Constraints</h2>";
        $stmt = $pdo->query("
            SELECT
                TABLE_NAME,
                COLUMN_NAME,
                CONSTRAINT_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE
                REFERENCED_TABLE_SCHEMA = '$database' AND
                REFERENCED_TABLE_NAME IS NOT NULL
        ");

        $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($constraints) > 0) {
            echo "<table>
                <tr><th>Table</th><th>Column</th><th>Constraint</th><th>References</th></tr>";

            foreach ($constraints as $constraint) {
                echo "<tr>
                    <td><code>{$constraint['TABLE_NAME']}</code></td>
                    <td>{$constraint['COLUMN_NAME']}</td>
                    <td>{$constraint['CONSTRAINT_NAME']}</td>
                    <td><code>{$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}</code></td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='info'>No foreign key constraints found</div>";
        }

        // Check users table structure specifically
        if (in_array('users', $tables)) {
            echo "<h2>👤 Users Table Structure</h2>";
            $stmt = $pdo->query("DESCRIBE users");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<table>
                <tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

            foreach ($columns as $column) {
                $nullStatus = $column['Null'] === 'YES' ? '✓' : '✗';
                echo "<tr>
                    <td><code>{$column['Field']}</code></td>
                    <td>{$column['Type']}</td>
                    <td>$nullStatus</td>
                    <td>{$column['Key']}</td>
                    <td>{$column['Default']}</td>
                </tr>";
            }
            echo "</table>";

            // Check for email column specifically
            $hasEmail = false;
            foreach ($columns as $column) {
                if ($column['Field'] === 'email') {
                    $hasEmail = true;
                    break;
                }
            }

            if (!$hasEmail) {
                echo "<div class='warning'>⚠️ Email column is missing from users table</div>";
            }
        }

        // Show sample data from key tables
        echo "<div class='grid'>";

        if (in_array('users', $tables)) {
            echo "<div>
                <h2>👥 Sample Users</h2>";
            try {
                $stmt = $pdo->query("SELECT id, username, full_name, role, is_active FROM users LIMIT 5");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($users) > 0) {
                    echo "<table>
                        <tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Active</th></tr>";
                    foreach ($users as $user) {
                        echo "<tr>
                            <td>{$user['id']}</td>
                            <td>{$user['username']}</td>
                            <td>{$user['full_name']}</td>
                            <td>{$user['role']}</td>
                            <td>{$user['is_active']}</td>
                        </tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<div class='warning'>⚠️ No users found in database</div>";
                }
            } catch (PDOException $e) {
                echo "<div class='error'>✗ Error reading users: " . $e->getMessage() . "</div>";
            }
            echo "</div>";
        }

        if (in_array('production_lines', $tables)) {
            echo "<div>
                <h2>🏭 Production Lines</h2>";
            try {
                $stmt = $pdo->query("SELECT * FROM production_lines LIMIT 5");
                $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($lines) > 0) {
                    echo "<table>
                        <tr><th>Line Name</th><th>Type</th><th>Target Efficiency</th><th>Status</th></tr>";
                    foreach ($lines as $line) {
                        echo "<tr>
                            <td>{$line['line_name']}</td>
                            <td>{$line['line_type']}</td>
                            <td>{$line['target_efficiency']}%</td>
                            <td>{$line['status']}</td>
                        </tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<div class='warning'>⚠️ No production lines found</div>";
                }
            } catch (PDOException $e) {
                echo "<div class='error'>✗ Error reading production lines: " . $e->getMessage() . "</div>";
            }
            echo "</div>";
        }

        echo "</div>";

        // Recommendations
        echo "<h2>💡 Recommendations</h2>";

        if (count($missingTables) > 0) {
            echo "<div class='warning'>
                <h4>Missing Tables Found:</h4>
                <ul>";
            foreach ($missingTables as $table) {
                echo "<li><code>$table</code> - This table is required for system operation</li>";
            }
            echo "</ul>
                <p><strong>Solution:</strong> Run the Safe Database Setup script to create missing tables.</p>
            </div>";
        }

        if (!in_array('users', $tables)) {
            echo "<div class='warning'>
                <h4>No Users Table:</h4>
                <p>The users table is essential for authentication and user management.</p>
            </div>";
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $userCount = $stmt->fetch()['count'];

            if ($userCount == 0) {
                echo "<div class='warning'>
                    <h4>No Users Found:</h4>
                    <p>The users table exists but contains no user accounts. A default admin account should be created.</p>
                </div>";
            }
        }

        if (in_array('production_lines', $tables)) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM production_lines");
            $lineCount = $stmt->fetch()['count'];

            if ($lineCount == 0) {
                echo "<div class='warning'>
                    <h4>No Production Lines:</h4>
                    <p>Production lines should be configured for the system to track manufacturing data.</p>
                </div>";
            }
        }

        echo "<div class='success' style='margin-top: 20px;'>
            <h3>🔧 Quick Actions</h3>
            <div style='text-align: center;'>
                <a href='safe_database_setup.php' class='btn' style='background: #28a745;'>🔧 Safe Database Setup</a>
                <a href='create_database.php' class='btn' style='background: #17a2b8;'>🆕 Fresh Database</a>
                <a href='index.php' class='btn' style='background: #007bff;'>🏠 Return to Main</a>
                <a href='system_verification.php' class='btn' style='background: #6c757d;'>🔍 System Check</a>
            </div>
        </div>";

    } else {
        echo "<div class='error'>
            <h2>❌ Database Not Found</h2>
            <p>The database '$database' does not exist. It needs to be created first.</p>
            <div style='text-align: center; margin: 20px 0;'>
                <a href='safe_database_setup.php' class='btn' style='background: #28a745;'>🔧 Create Database</a>
            </div>
        </div>";
    }

} catch(PDOException $e) {
    echo "<div class='error'>
        <h2>❌ Database Connection Failed</h2>
        <p><strong>Error:</strong> " . $e->getMessage() . "</p>

        <h3>Troubleshooting:</h3>
        <div class='warning'>
            <h4>1. Check MySQL Server:</h4>
            <pre>sudo service mysql status</pre>
        </div>

        <div class='warning'>
            <h4>2. Verify Credentials:</h4>
            <pre>Host: $host
Username: $username
Database: $database</pre>
        </div>

        <div class='warning'>
            <h4>3. Test Connection:</h4>
            <pre>mysql -h $host -u $username -p</pre>
        </div>
    </div>";
}

echo "</div></body></html>";
?>