<?php
require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

$message = "";
$error = "";

// Handle Backup Request
if (isset($_POST['backup'])) {
    try {
        // Get all data from all tables
        $tables = ['products', 'daily_performance', 'assy_performance', 'packing_performance'];
        $backup_data = [];
        
        foreach ($tables as $table) {
            $query = "SELECT * FROM $table";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $backup_data[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Create backup file
        $backup_content = json_encode($backup_data, JSON_PRETTY_PRINT);
        $filename = "performance_backup_" . date('Y-m-d_H-i-s') . ".json";
        
        // Force download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($backup_content));
        echo $backup_content;
        exit();
        
    } catch (Exception $e) {
        $error = "Backup failed: " . $e->getMessage();
    }
}

// Handle Restore Request
if (isset($_POST['restore']) && isset($_FILES['backup_file'])) {
    try {
        if ($_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $_FILES['backup_file']['error']);
        }
        
        $backup_content = file_get_contents($_FILES['backup_file']['tmp_name']);
        $backup_data = json_decode($backup_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid backup file format");
        }
        
        $db->beginTransaction();
        
        // Truncate all tables (clear existing data)
        $tables = ['packing_performance', 'assy_performance', 'daily_performance', 'products'];
        foreach ($tables as $table) {
            $db->exec("DELETE FROM $table");
        }
        
        // Restore products first (due to foreign key constraints)
        if (isset($backup_data['products'])) {
            foreach ($backup_data['products'] as $product) {
                $query = "INSERT INTO products (id, product_code, circuit, mhr, qty_sh_pack, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $product['id'],
                    $product['product_code'],
                    $product['circuit'],
                    $product['mhr'],
                    $product['qty_sh_pack'],
                    $product['created_at'],
                    $product['updated_at']
                ]);
            }
        }
        
        // Restore daily_performance
        if (isset($backup_data['daily_performance'])) {
            foreach ($backup_data['daily_performance'] as $performance) {
                $query = "INSERT INTO daily_performance (id, date, line_shift, leader, mp, absent, separated_mp, plan, no_ot_mp, ot_mp, ot_hours, assy_wt, qc, total_assy_output, created_at, updated_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $performance['id'],
                    $performance['date'],
                    $performance['line_shift'],
                    $performance['leader'],
                    $performance['mp'],
                    $performance['absent'],
                    $performance['separated_mp'],
                    $performance['plan'],
                    $performance['no_ot_mp'],
                    $performance['ot_mp'],
                    $performance['ot_hours'],
                    $performance['assy_wt'],
                    $performance['qc'],
                    $performance['total_assy_output'],
                    $performance['created_at'],
                    $performance['updated_at']
                ]);
            }
        }
        
        // Restore assy_performance
        if (isset($backup_data['assy_performance'])) {
            foreach ($backup_data['assy_performance'] as $assy) {
                $query = "INSERT INTO assy_performance (id, daily_performance_id, product_id, assy_output, created_at) 
                         VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $assy['id'],
                    $assy['daily_performance_id'],
                    $assy['product_id'],
                    $assy['assy_output'],
                    $assy['created_at']
                ]);
            }
        }
        
        // Restore packing_performance
        if (isset($backup_data['packing_performance'])) {
            foreach ($backup_data['packing_performance'] as $packing) {
                $query = "INSERT INTO packing_performance (id, daily_performance_id, product_id, packing_output, created_at) 
                         VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $packing['id'],
                    $packing['daily_performance_id'],
                    $packing['product_id'],
                    $packing['packing_output'],
                    $packing['created_at']
                ]);
            }
        }
        
        $db->commit();
        $message = "Data restored successfully!";
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Restore failed: " . $e->getMessage();
    }
}

// Get database statistics
$stats = [];
$tables = ['products', 'daily_performance', 'assy_performance', 'packing_performance'];
foreach ($tables as $table) {
    $query = "SELECT COUNT(*) as count FROM $table";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats[$table] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

$total_records = array_sum($stats);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        .backup-card {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        .restore-card {
            border-left: 4px solid #28a745;
            background: #f8f9fa;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <?php require_once "navbar.php"; ?>
    
    <div class="container mt-5 mb-5">
        <div class="page-header mb-4">
            <h1 class="page-title">BACKUP & RESTORE DATA</h1>
            <p class="lead text-muted">Secure your performance tracking data with backup and restore functionality</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <div><?= $message ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fas fa-times-circle me-2"></i>
                <div><?= $error ?></div>
            </div>
        <?php endif; ?>

        <!-- Database Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card p-3 text-center">
                    <div class="stats-value"><?= $stats['products'] ?></div>
                    <div class="stats-label">Products</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card p-3 text-center">
                    <div class="stats-value"><?= $stats['daily_performance'] ?></div>
                    <div class="stats-label">Performance Records</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card p-3 text-center">
                    <div class="stats-value"><?= $stats['assy_performance'] ?></div>
                    <div class="stats-label">ASSY Records</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card p-3 text-center">
                    <div class="stats-value"><?= $stats['packing_performance'] ?></div>
                    <div class="stats-label">Packing Records</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Backup Section -->
            <div class="col-md-6 mb-4">
                <div class="card backup-card h-100">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-download me-2"></i> Backup Data</h4>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Create a complete backup of all your performance tracking data including:</p>
                        <ul>
                            <li>Product catalog</li>
                            <li>Daily performance records</li>
                            <li>ASSY performance data</li>
                            <li>Packing performance data</li>
                        </ul>
                        <p class="text-muted"><small>The backup will be downloaded as a JSON file that you can save securely.</small></p>
                        
                        <form method="POST">
                            <button type="submit" name="backup" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-file-download me-2"></i> Download Backup
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Restore Section -->
            <div class="col-md-6 mb-4">
                <div class="card restore-card h-100">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="fas fa-upload me-2"></i> Restore Data</h4>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Restore your data from a previously created backup file.</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will replace all existing data with the backup data. This action cannot be undone.
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="backup_file" class="form-label">Select Backup File</label>
                                <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".json" required>
                                <div class="form-text">Select the JSON backup file you previously downloaded.</div>
                            </div>
                            <button type="submit" name="restore" class="btn btn-success btn-lg w-100" onclick="return confirm('WARNING: This will replace ALL existing data. Are you sure you want to continue?')">
                                <i class="fas fa-file-upload me-2"></i> Restore from Backup
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0"><i class="fas fa-info-circle me-2"></i> Backup & Restore Instructions</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Creating Backups</h5>
                        <ul>
                            <li>Click "Download Backup" to create a complete backup</li>
                            <li>Save the downloaded file in a secure location</li>
                            <li>Create regular backups (weekly recommended)</li>
                            <li>Backup before making major system changes</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5>Restoring Data</h5>
                        <ul>
                            <li>Select your backup JSON file</li>
                            <li>Confirm the restore action</li>
                            <li>All existing data will be replaced</li>
                            <li>This process cannot be undone</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-primary me-3">
                <i class="fas fa-home me-2"></i> Back to Home
            </a>
            <a href="dashboard.php" class="btn btn-info me-3">
                <i class="fas fa-tachometer-alt me-2"></i> Performance Dashboard
            </a>
            <a href="view_data.php" class="btn btn-secondary">
                <i class="fas fa-database me-2"></i> View Data
            </a>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3">Performance Tracking System</h5>
                    <p class="text-light">Operational oversight and data management solution for manufacturing performance tracking.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Performance Tracking System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>