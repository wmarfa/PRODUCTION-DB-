<?php
/**
 * System Verification Script
 *
 * This script verifies that all components of the Production Management System
 * are properly installed and configured for offline LAN deployment.
 */

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
$requiredFiles = [
    'database_enhancements.php',
    'assets.php',
    'enhanced_dashboard_offline.php',
    'advanced_reports_offline.php',
    'quality_assurance_offline.php',
    'maintenance_manager_offline.php',
    'workflow_automation_offline.php',
    'scalability_optimizer_offline.php',
    'predictive_analytics_ai_offline.php',
    'digital_twin_simulator_offline.php',
    'iot_sensors_offline.php',
    'compliance_audit_offline.php',
    'api_rest_offline.php',
    'mobile_production_monitor.php',
    'user_management_offline.php',
    'system_diagnostics_offline.php',
    'enhanced_shift_handover.php',
    'notifications_center_offline.php'
];

// System requirements
$requirements = [
    'php_version' => '7.4.0',
    'mysql_version' => '5.7.0',
    'required_extensions' => [
        'pdo',
        'pdo_mysql',
        'json',
        'gd',
        'session',
        'mbstring'
    ],
    'php_settings' => [
        'memory_limit' => '256M',
        'max_execution_time' => '300',
        'post_max_size' => '32M',
        'upload_max_filesize' => '32M'
    ]
];

// Verification results
$verification = [
    'file_check' => [],
    'database_check' => false,
    'php_requirements' => [],
    'security_check' => [],
    'performance_check' => [],
    'overall_status' => 'pending'
];

// HTML output styling
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Verification - Production Management System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; }
        .section { margin: 20px 0; padding: 15px; border-left: 4px solid #ddd; background: #f9f9f9; }
        .section h3 { margin-top: 0; color: #333; }
        .success { color: #28a745; border-left-color: #28a745; }
        .warning { color: #ffc107; border-left-color: #ffc107; }
        .error { color: #dc3545; border-left-color: #dc3545; }
        .status-pass { background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
        .status-fail { background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
        .status-warning { background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-size: 0.8em; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .final-result { text-align: center; margin: 30px 0; padding: 20px; border-radius: 8px; font-size: 1.2em; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .progress { width: 100%; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; }
        .progress-bar { height: 100%; background: #28a745; transition: width 0.3s ease; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 System Verification</h1>
            <p>Production Management System - Installation & Configuration Check</p>
            <small>Generated on: ' . date('Y-m-d H:i:s') . '</small>
        </div>
';

// 1. Check File Existence
echo '<div class="section">
    <h3>📁 File System Verification</h3>
    <table>
        <tr><th>File Name</th><th>Status</th><th>Size</th><th>Modified</th></tr>';

$fileCheckPassed = 0;
$totalFiles = count($requiredFiles);

foreach ($requiredFiles as $file) {
    $filePath = __DIR__ . '/' . $file;
    $exists = file_exists($filePath);
    $readable = $exists && is_readable($filePath);

    if ($exists && $readable) {
        $status = '<span class="status-pass">✓ EXISTS</span>';
        $fileCheckPassed++;
    } else {
        $status = '<span class="status-fail">✗ MISSING</span>';
    }

    $size = $exists ? filesize($filePath) : 0;
    $modified = $exists ? date('Y-m-d H:i:s', filemtime($filePath)) : 'N/A';

    echo "<tr>
        <td>{$file}</td>
        <td>{$status}</td>
        <td>" . number_format($size / 1024, 2) . " KB</td>
        <td>{$modified}</td>
    </tr>";

    $verification['file_check'][$file] = $exists && $readable;
}

$fileCheckPercentage = ($fileCheckPassed / $totalFiles) * 100;
echo '</table>
    <div class="progress">
        <div class="progress-bar" style="width: ' . $fileCheckPercentage . '%"></div>
    </div>
    <p><strong>File Integrity: ' . $fileCheckPassed . '/' . $totalFiles . ' files (' . round($fileCheckPercentage, 1) . '%)</strong></p>
</div>';

// 2. PHP Requirements Check
echo '<div class="section">
    <h3>🐘 PHP Environment Verification</h3>

    <h4>PHP Version</h4>';
$currentPHPVersion = PHP_VERSION;
$phpVersionOk = version_compare($currentPHPVersion, $requirements['php_version'], '>=');
echo '<p>Current: ' . $currentPHPVersion . ' | Required: ' . $requirements['php_version'] . ' | ' .
     ($phpVersionOk ? '<span class="status-pass">✓ OK</span>' : '<span class="status-fail">✗ UPGRADE REQUIRED</span>') . '</p>';

echo '<h4>Required Extensions</h4>
    <table>
        <tr><th>Extension</th><th>Status</th></tr>';

$extensionCheckPassed = 0;
foreach ($requirements['required_extensions'] as $ext) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? '<span class="status-pass">✓ LOADED</span>' : '<span class="status-fail">✗ MISSING</span>';
    echo "<tr><td>{$ext}</td><td>{$status}</td></tr>";
    if ($loaded) $extensionCheckPassed++;
    $verification['php_requirements']['extensions'][$ext] = $loaded;
}
echo '</table>';

echo '<h4>PHP Configuration</h4>
    <table>
        <tr><th>Setting</th><th>Current</th><th>Recommended</th><th>Status</th></tr>';

foreach ($requirements['php_settings'] as $setting => $recommended) {
    $current = ini_get($setting);
    $currentBytes = $current;
    $recommendedBytes = $recommended;

    // Convert memory and file size values to bytes for comparison
    if (preg_match('/^(\d+)([KMG]?)$/', $current, $matches)) {
        $value = (int)$matches[1];
        $unit = $matches[2];
        switch ($unit) {
            case 'G': $currentBytes = $value * 1024 * 1024 * 1024; break;
            case 'M': $currentBytes = $value * 1024 * 1024; break;
            case 'K': $currentBytes = $value * 1024; break;
            default: $currentBytes = $value;
        }
    }

    if (preg_match('/^(\d+)([KMG]?)$/', $recommended, $matches)) {
        $value = (int)$matches[1];
        $unit = $matches[2];
        switch ($unit) {
            case 'G': $recommendedBytes = $value * 1024 * 1024 * 1024; break;
            case 'M': $recommendedBytes = $value * 1024 * 1024; break;
            case 'K': $recommendedBytes = $value * 1024; break;
            default: $recommendedBytes = $value;
        }
    }

    $ok = $currentBytes >= $recommendedBytes;
    $status = $ok ? '<span class="status-pass">✓ OK</span>' : '<span class="status-warning">⚠ LOW</span>';

    echo "<tr><td>{$setting}</td><td>{$current}</td><td>{$recommended}</td><td>{$status}</td></tr>";
    $verification['php_requirements']['settings'][$setting] = $ok;
}
echo '</table>
</div>';

// 3. Database Connection Check
echo '<div class="section">
    <h3>🗄️ Database Connection Verification</h3>';

try {
    // Try to include database enhancement file
    if (file_exists(__DIR__ . '/database_enhancements.php')) {
        require_once __DIR__ . '/database_enhancements.php';

        // Test database connection
        $database = new DatabaseEnhancer();
        $pdo = $database->connect();

        if ($pdo) {
            echo '<p class="success"><span class="status-pass">✓ DATABASE CONNECTION SUCCESSFUL</span></p>';

            // Check MySQL version
            $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            echo "<p>MySQL Version: {$version}</p>";

            // Check if required tables exist
            $requiredTables = [
                'production_lines',
                'daily_performance',
                'production_alerts',
                'quality_checkpoints',
                'maintenance_schedules',
                'user_management'
            ];

            echo '<h4>Required Tables Check</h4>
                <table>
                <tr><th>Table Name</th><th>Status</th><th>Records</th></tr>';

            $tableCheckPassed = 0;
            foreach ($requiredTables as $table) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
                    $count = $stmt->fetch()['count'];
                    $status = '<span class="status-pass">✓ EXISTS</span>';
                    $tableCheckPassed++;
                } catch (PDOException $e) {
                    $count = 0;
                    $status = '<span class="status-fail">✗ MISSING</span>';
                }

                echo "<tr><td>{$table}</td><td>{$status}</td><td>{$count}</td></tr>";
                $verification['database_check']['tables'][$table] = ($count >= 0);
            }
            echo '</table>';

            $verification['database_check']['connection'] = true;

        } else {
            echo '<p class="error"><span class="status-fail">✗ DATABASE CONNECTION FAILED</span></p>';
            $verification['database_check']['connection'] = false;
        }
    } else {
        echo '<p class="error"><span class="status-fail">✗ DATABASE CONFIGURATION FILE MISSING</span></p>';
        $verification['database_check']['connection'] = false;
    }

} catch (Exception $e) {
    echo '<p class="error"><span class="status-fail">✗ DATABASE ERROR: ' . htmlspecialchars($e->getMessage()) . '</span></p>';
    $verification['database_check']['connection'] = false;
}

echo '</div>';

// 4. Security Check
echo '<div class="section">
    <h3>🔒 Security Configuration Check</h3>';

// Check if session is configured securely
$sessionConfig = [
    'session.cookie_httponly' => ini_get('session.cookie_httponly'),
    'session.cookie_secure' => ini_get('session.cookie_secure'),
    'session.use_only_cookies' => ini_get('session.use_only_cookies')
];

echo '<h4>Session Security</h4>
    <table>
    <tr><th>Setting</th><th>Value</th><th>Status</th></tr>';

foreach ($sessionConfig as $setting => $value) {
    $secure = in_array($setting, ['session.cookie_httponly', 'session.use_only_cookies']) ? ($value == '1') : ($value == '1');
    $status = $secure ? '<span class="status-pass">✓ SECURE</span>' : '<span class="status-warning">⚠ INSECURE</span>';
    echo "<tr><td>{$setting}</td><td>{$value}</td><td>{$status}</td></tr>";
    $verification['security_check']['session'][$setting] = $secure;
}

echo '</table>';

// Check file permissions
echo '<h4>File Permissions</h4>';
$sensitiveFiles = [
    'database_enhancements.php',
    'user_management_offline.php'
];

foreach ($sensitiveFiles as $file) {
    $filePath = __DIR__ . '/' . $file;
    if (file_exists($filePath)) {
        $perms = fileperms($filePath);
        $octal = substr(sprintf('%o', $perms), -4);
        $secure = !($perms & 0x0004); // Not readable by others
        $status = $secure ? '<span class="status-pass">✓ SECURE</span>' : '<span class="status-warning">⚠ WORLD-READABLE</span>';
        echo "<p>{$file}: {$octal} {$status}</p>";
        $verification['security_check']['permissions'][$file] = $secure;
    }
}

echo '</div>';

// 5. Performance Check
echo '<div class="section">
    <h3>⚡ Performance Configuration Check</h3>';

// Check memory limit
$memoryLimit = ini_get('memory_limit');
$memoryLimitBytes = return_bytes($memoryLimit);
$recommendedMemory = 256 * 1024 * 1024; // 256MB
$memoryOk = $memoryLimitBytes >= $recommendedMemory;

echo '<p>Memory Limit: ' . $memoryLimit . ' | ' .
     ($memoryOk ? '<span class="status-pass">✓ ADEQUATE</span>' : '<span class="status-warning">⚠ INSUFFICIENT</span>') . '</p>';

// Check execution time
$maxExecutionTime = ini_get('max_execution_time');
$executionTimeOk = $maxExecutionTime >= 300;

echo '<p>Max Execution Time: ' . $maxExecutionTime . 's | ' .
     ($executionTimeOk ? '<span class="status-pass">✓ ADEQUATE</span>' : '<span class="status-warning">⚠ TOO SHORT</span>') . '</p>';

$verification['performance_check']['memory'] = $memoryOk;
$verification['performance_check']['execution_time'] = $executionTimeOk;

echo '</div>';

// 6. Calculate Overall Status
$totalChecks = 0;
$passedChecks = 0;

// File system check
$totalChecks++;
if ($fileCheckPercentage >= 90) {
    $passedChecks++;
}

// PHP requirements check
$phpRequirementsOk = $phpVersionOk && ($extensionCheckPassed >= count($requirements['required_extensions']));
$totalChecks++;
if ($phpRequirementsOk) {
    $passedChecks++;
}

// Database check
$totalChecks++;
if (isset($verification['database_check']['connection']) && $verification['database_check']['connection']) {
    $passedChecks++;
}

// Security check (weighted)
$securityScore = 0;
$securityTotal = 0;
if (isset($verification['security_check']['session'])) {
    foreach ($verification['security_check']['session'] as $secure) {
        $securityTotal++;
        if ($secure) $securityScore++;
    }
}
if (isset($verification['security_check']['permissions'])) {
    foreach ($verification['security_check']['permissions'] as $secure) {
        $securityTotal++;
        if ($secure) $securityScore++;
    }
}

if ($securityTotal > 0) {
    $securityPercentage = ($securityScore / $securityTotal) * 100;
    $totalChecks++;
    if ($securityPercentage >= 80) {
        $passedChecks++;
    }
}

// Performance check
$totalChecks++;
if ($memoryOk && $executionTimeOk) {
    $passedChecks++;
}

$overallPercentage = ($passedChecks / $totalChecks) * 100;

// Determine final status
if ($overallPercentage >= 90) {
    $finalStatus = 'success';
    $finalMessage = '✅ SYSTEM VERIFICATION PASSED - Your system is ready for production!';
    $finalClass = 'success';
} elseif ($overallPercentage >= 75) {
    $finalStatus = 'warning';
    $finalMessage = '⚠️ SYSTEM VERIFICATION PASSED WITH WARNINGS - Review recommendations';
    $finalClass = 'warning';
} else {
    $finalStatus = 'error';
    $finalMessage = '❌ SYSTEM VERIFICATION FAILED - Address critical issues before deployment';
    $finalClass = 'error';
}

$verification['overall_status'] = $finalStatus;

// 7. Final Results
echo '<div class="final-result ' . $finalClass . '">
    ' . $finalMessage . '
    <br><small>Overall Score: ' . round($overallPercentage, 1) . '% (' . $passedChecks . '/' . $totalChecks . ' checks passed)</small>
</div>';

// 8. Recommendations
echo '<div class="section">
    <h3>📋 Recommendations</h3>';

if (!$phpVersionOk) {
    echo '<p class="error">⚠️ <strong>Upgrade PHP</strong> - Current version ' . $currentPHPVersion . ' is below required ' . $requirements['php_version'] . '</p>';
}

if ($fileCheckPercentage < 100) {
    echo '<p class="error">⚠️ <strong>Missing Files</strong> - Some required system files are missing. Please ensure all files are uploaded.</p>';
}

if (!isset($verification['database_check']['connection']) || !$verification['database_check']['connection']) {
    echo '<p class="error">⚠️ <strong>Database Connection</strong> - Verify database credentials and MySQL server is running.</p>';
}

if (!$memoryOk) {
    echo '<p class="warning">💡 <strong>Increase Memory Limit</strong> - Recommended minimum 256M for optimal performance.</p>';
}

if (!$executionTimeOk) {
    echo '<p class="warning">💡 <strong>Increase Execution Time</strong> - Recommended minimum 300s for report generation.</p>';
}

echo '<div class="grid">
    <div>
        <h4>Next Steps</h4>
        <ol>
            <li>Address any critical issues identified above</li>
            <li>Configure database connection in database_enhancements.php</li>
            <li>Create initial user accounts through user_management_offline.php</li>
            <li>Import existing production data if available</li>
            <li>Test system functionality with sample data</li>
            <li>Train users on the system interface</li>
        </ol>
    </div>
    <div>
        <h4>Quick Links</h4>
        <ul>
            <li><a href="index.php">🏠 Main Dashboard</a></li>
            <li><a href="user_management_offline.php">👥 User Management</a></li>
            <li><a href="system_diagnostics_offline.php">🔧 System Diagnostics</a></li>
            <li><a href="enhanced_dashboard_offline.php">📊 Production Dashboard</a></li>
            <li><a href="SYSTEM_INTEGRATION_GUIDE.md">📖 Integration Guide</a></li>
        </ul>
    </div>
</div>
</div>';

// 9. Technical Details (for advanced users)
echo '<div class="section">
    <h3>🔧 Technical Details</h3>
    <div class="grid">
        <div>
            <h4>Server Information</h4>
            <ul>
                <li>PHP Version: ' . PHP_VERSION . '</li>
                <li>Server Software: ' . $_SERVER['SERVER_SOFTWARE'] . '</li>
                <li>Document Root: ' . $_SERVER['DOCUMENT_ROOT'] . '</li>
                <li>Server Name: ' . $_SERVER['SERVER_NAME'] . '</li>
                <li>Server Port: ' . $_SERVER['SERVER_PORT'] . '</li>
            </ul>
        </div>
        <div>
            <h4>PHP Configuration</h4>
            <ul>
                <li>Memory Limit: ' . ini_get('memory_limit') . '</li>
                <li>Max Execution Time: ' . ini_get('max_execution_time') . 's</li>
                <li>Upload Max Filesize: ' . ini_get('upload_max_filesize') . '</li>
                <li>Post Max Size: ' . ini_get('post_max_size') . '</li>
                <li>Max Input Vars: ' . ini_get('max_input_vars') . '</li>
            </ul>
        </div>
    </div>
</div>';

// 10. Verification Summary
echo '<div class="section">
    <h3>📊 Verification Summary</h3>
    <table>
        <tr><th>Category</th><th>Status</th><th>Score</th></tr>
        <tr>
            <td>File System</td>
            <td>' . ($fileCheckPercentage >= 90 ? '<span class="status-pass">PASS</span>' : '<span class="status-fail">FAIL</span>') . '</td>
            <td>' . round($fileCheckPercentage, 1) . '%</td>
        </tr>
        <tr>
            <td>PHP Environment</td>
            <td>' . ($phpRequirementsOk ? '<span class="status-pass">PASS</span>' : '<span class="status-fail">FAIL</span>') . '</td>
            <td>' . ($phpRequirementsOk ? '100' : '0') . '%</td>
        </tr>
        <tr>
            <td>Database Connection</td>
            <td>' . (isset($verification['database_check']['connection']) && $verification['database_check']['connection'] ? '<span class="status-pass">PASS</span>' : '<span class="status-fail">FAIL</span>') . '</td>
            <td>' . (isset($verification['database_check']['connection']) && $verification['database_check']['connection'] ? '100' : '0') . '%</td>
        </tr>
        <tr>
            <td>Security Configuration</td>
            <td>' . ($securityPercentage >= 80 ? '<span class="status-pass">PASS</span>' : '<span class="status-warning">WARN</span>') . '</td>
            <td>' . round($securityPercentage, 1) . '%</td>
        </tr>
        <tr>
            <td>Performance Settings</td>
            <td>' . (($memoryOk && $executionTimeOk) ? '<span class="status-pass">PASS</span>' : '<span class="status-warning">WARN</span>') . '</td>
            <td>' . (($memoryOk && $executionTimeOk) ? '100' : '50') . '%</td>
        </tr>
        <tr class="total">
            <td><strong>OVERALL</strong></td>
            <td><strong>' . strtoupper($finalStatus) . '</strong></td>
            <td><strong>' . round($overallPercentage, 1) . '%</strong></td>
        </tr>
    </table>
</div>';

// Helper function to convert memory values to bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

echo '
    </div>

    <script>
        // Add interactive features
        document.addEventListener("DOMContentLoaded", function() {
            // Animate progress bars
            const progressBars = document.querySelectorAll(".progress-bar");
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = "0%";
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });

            // Highlight critical issues
            const errorElements = document.querySelectorAll(".status-fail");
            if (errorElements.length > 0) {
                errorElements.forEach(el => {
                    el.style.fontWeight = "bold";
                    el.parentElement.style.fontWeight = "bold";
                });
            }
        });
    </script>
</body>
</html>';

// Save verification results to log file
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'verification_results' => $verification,
    'overall_score' => $overallPercentage,
    'overall_status' => $finalStatus
];

file_put_contents(__DIR__ . '/verification_log.json', json_encode($logData, JSON_PRETTY_PRINT));
?>