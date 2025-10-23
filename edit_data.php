<?php
require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

// Initialize variables
$id = isset($_GET['id']) ? intval($_GET['id']) : die('ERROR: Record ID not found.');
$line_shift = $leader = $date = '';
$mp = $absent = $separated_mp = $plan = $no_ot_mp = $ot_mp = $ot_hours = $assy_wt = $qc = 0;
$error = '';
$success = '';
$products = [];
$assy_records = [];
$packing_records = [];

// Get all products for dropdown
try {
    $product_query = "SELECT * FROM products ORDER BY product_code";
    $product_stmt = $db->prepare($product_query);
    $product_stmt->execute();
    $products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading products: " . $e->getMessage();
}

// Handle POST request for update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // Validate required fields
        $required_fields = ['date', 'line_shift', 'leader', 'mp', 'absent', 'separated_mp', 'plan', 'no_ot_mp', 'ot_mp', 'assy_wt'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                throw new Exception("Required field '$field' is missing.");
            }
        }

        // Update daily performance
        $update_query = "
            UPDATE daily_performance 
            SET date = ?, line_shift = ?, leader = ?, mp = ?, qc = ?, absent = ?, 
                separated_mp = ?, plan = ?, no_ot_mp = ?, ot_mp = ?, ot_hours = ?, assy_wt = ?
            WHERE id = ?
        ";
        
        $stmt = $db->prepare($update_query);
        $stmt->execute([
            $_POST['date'],
            $_POST['line_shift'],
            $_POST['leader'],
            $_POST['mp'],
            $_POST['qc'] ?? 0,
            $_POST['absent'],
            $_POST['separated_mp'],
            $_POST['plan'],
            $_POST['no_ot_mp'],
            $_POST['ot_mp'],
            $_POST['ot_hours'] ?? 0,
            $_POST['assy_wt'],
            $id
        ]);

        // Delete existing ASSY performance data
        $delete_assy = "DELETE FROM assy_performance WHERE daily_performance_id = ?";
        $stmt = $db->prepare($delete_assy);
        $stmt->execute([$id]);

        // Insert updated ASSY performance data
        if (isset($_POST['assy_product_id'])) {
            for ($i = 0; $i < count($_POST['assy_product_id']); $i++) {
                if (!empty($_POST['assy_product_id'][$i]) && !empty($_POST['assy_output'][$i])) {
                    $insert_assy = "INSERT INTO assy_performance (daily_performance_id, product_id, assy_output) VALUES (?, ?, ?)";
                    $stmt = $db->prepare($insert_assy);
                    $stmt->execute([$id, $_POST['assy_product_id'][$i], $_POST['assy_output'][$i]]);
                }
            }
        }

        // Delete existing Packing performance data
        $delete_packing = "DELETE FROM packing_performance WHERE daily_performance_id = ?";
        $stmt = $db->prepare($delete_packing);
        $stmt->execute([$id]);

        // Insert updated Packing performance data
        if (isset($_POST['packing_product_id'])) {
            for ($i = 0; $i < count($_POST['packing_product_id']); $i++) {
                if (!empty($_POST['packing_product_id'][$i]) && !empty($_POST['packing_output'][$i])) {
                    $insert_packing = "INSERT INTO packing_performance (daily_performance_id, product_id, packing_output) VALUES (?, ?, ?)";
                    $stmt = $db->prepare($insert_packing);
                    $stmt->execute([$id, $_POST['packing_product_id'][$i], $_POST['packing_output'][$i]]);
                }
            }
        }

        $db->commit();
        $success = "Record updated successfully!";
        
        // Refresh data after update
        $query = "SELECT * FROM daily_performance WHERE id = ? LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $line_shift = $row['line_shift'];
            $leader = $row['leader'];
            $date = $row['date'];
            $mp = $row['mp'];
            $absent = $row['absent'];
            $separated_mp = $row['separated_mp'];
            $plan = $row['plan'];
            $no_ot_mp = $row['no_ot_mp'];
            $ot_mp = $row['ot_mp'];
            $ot_hours = $row['ot_hours'];
            $assy_wt = $row['assy_wt'];
            $qc = $row['qc'];
        }
        
        // Refresh ASSY records
        $assy_query = "SELECT * FROM assy_performance WHERE daily_performance_id = ?";
        $assy_stmt = $db->prepare($assy_query);
        $assy_stmt->execute([$id]);
        $assy_records = $assy_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Refresh Packing records
        $packing_query = "SELECT * FROM packing_performance WHERE daily_performance_id = ?";
        $packing_stmt = $db->prepare($packing_query);
        $packing_stmt->execute([$id]);
        $packing_records = $packing_stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error updating record: " . $e->getMessage();
    }
} else {
    // GET request - load existing data
    try {
        // Fetch Daily Performance
        $query = "SELECT * FROM daily_performance WHERE id = ? LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Assign values to variables
            $line_shift = $row['line_shift'];
            $leader = $row['leader'];
            $date = $row['date'];
            $mp = $row['mp'];
            $absent = $row['absent'];
            $separated_mp = $row['separated_mp'];
            $plan = $row['plan'];
            $no_ot_mp = $row['no_ot_mp'];
            $ot_mp = $row['ot_mp'];
            $ot_hours = $row['ot_hours'];
            $assy_wt = $row['assy_wt'];
            $qc = $row['qc'];
            
            // Fetch ASSY Performance Records
            $assy_query = "SELECT * FROM assy_performance WHERE daily_performance_id = ?";
            $assy_stmt = $db->prepare($assy_query);
            $assy_stmt->execute([$id]);
            $assy_records = $assy_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch Packing Performance Records
            $packing_query = "SELECT * FROM packing_performance WHERE daily_performance_id = ?";
            $packing_stmt = $db->prepare($packing_query);
            $packing_stmt->execute([$id]);
            $packing_records = $packing_stmt->fetchAll(PDO::FETCH_ASSOC);

        } else {
            $error = "Record not found.";
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Performance Record</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        .container { max-width: 1000px; }
        .assy-product, .packing-product {
            border: 1px dashed #dee2e6;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 0.375rem;
            background-color: #f8f9fa;
        }
        .form-section {
            margin-bottom: 2rem;
        }
        .current-record {
            background-color: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <?php require_once "navbar.php"; ?>
    
    <div class="container mt-5 mb-5">
        <div class="page-header mb-4">
            <h1 class="page-title">EDIT PERFORMANCE RECORD</h1>
            <p class="lead text-muted">Update record #<?= htmlspecialchars($id) ?></p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <div><?= $success ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fas fa-times-circle me-2"></i>
                <div><?= $error ?></div>
            </div>
        <?php endif; ?>

        <div class="current-record">
            <h4><i class="fas fa-info-circle"></i> Current Record Information</h4>
            <p class="mb-1"><strong>Date:</strong> <?= htmlspecialchars($date) ?></p>
            <p class="mb-1"><strong>Line/Shift:</strong> <?= htmlspecialchars($line_shift) ?></p>
            <p class="mb-0"><strong>Leader:</strong> <?= htmlspecialchars($leader) ?></p>
        </div>

        <form method="POST" class="form-section-group" id="editForm">
            
            <!-- Daily Performance Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-2"></i> Operational Metrics
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="date" class="form-label fw-bold">Date *</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($date) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="line_shift" class="form-label fw-bold">Line / Shift *</label>
                            <input type="text" class="form-control" id="line_shift" name="line_shift" value="<?= htmlspecialchars($line_shift) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="leader" class="form-label fw-bold">Team Leader *</label>
                            <input type="text" class="form-control" id="leader" name="leader" value="<?= htmlspecialchars($leader) ?>" required>
                        </div>
                    </div>
                    
                    <h5 class="mt-4 pb-2 border-bottom text-secondary">Manpower & Attendance</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="mp" class="form-label">Total MP *</label>
                            <input type="number" step="1" min="0" class="form-control" id="mp" name="mp" value="<?= htmlspecialchars($mp) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="qc" class="form-label">QC MP</label>
                            <input type="number" step="1" min="0" class="form-control" id="qc" name="qc" value="<?= htmlspecialchars($qc) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="absent" class="form-label text-danger">Absent MP *</label>
                            <input type="number" step="1" min="0" class="form-control" id="absent" name="absent" value="<?= htmlspecialchars($absent) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="separated_mp" class="form-label text-danger">Separated MP *</label>
                            <input type="number" step="1" min="0" class="form-control" id="separated_mp" name="separated_mp" value="<?= htmlspecialchars($separated_mp) ?>" required>
                        </div>
                    </div>

                    <h5 class="mt-4 pb-2 border-bottom text-secondary">Work Hours & Plan</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="plan" class="form-label">Daily Production Plan (Pcs) *</label>
                            <input type="number" step="1" min="0" class="form-control" id="plan" name="plan" value="<?= htmlspecialchars($plan) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="assy_wt" class="form-label">ASSY Working Time (Hours) *</label>
                            <input type="number" step="0.1" min="0" class="form-control" id="assy_wt" name="assy_wt" value="<?= htmlspecialchars($assy_wt) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="ot_hours" class="form-label">Overtime Hours</label>
                            <input type="number" step="0.1" min="0" class="form-control" id="ot_hours" name="ot_hours" value="<?= htmlspecialchars($ot_hours) ?>">
                        </div>
                    </div>

                    <h5 class="mt-4 pb-2 border-bottom text-secondary">Manpower for MHR Calculation</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="no_ot_mp" class="form-label">MP (No OT) *</label>
                            <input type="number" step="1" min="0" class="form-control" id="no_ot_mp" name="no_ot_mp" value="<?= htmlspecialchars($no_ot_mp) ?>" required>
                            <div class="form-text">MP who worked standard hours (7.66 hours each).</div>
                        </div>
                        <div class="col-md-6">
                            <label for="ot_mp" class="form-label">MP (With OT) *</label>
                            <input type="number" step="1" min="0" class="form-control" id="ot_mp" name="ot_mp" value="<?= htmlspecialchars($ot_mp) ?>" required>
                            <div class="form-text">MP who worked overtime (hours specified above).</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Assembly Performance -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-cogs me-2"></i> Assembly Performance
                </div>
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No products found. Please <a href="add_product.php" class="alert-link">add products</a> first.
                        </div>
                    <?php else: ?>
                        <div id="assy-products">
                            <?php if (count($assy_records) > 0): ?>
                                <?php foreach ($assy_records as $record): ?>
                                    <div class="row g-3 align-items-end assy-product">
                                        <div class="col-md-6">
                                            <label class="form-label">Product Code</label>
                                            <select name="assy_product_id[]" class="form-select" required>
                                                <option value="">-- Select Product --</option>
                                                <?php foreach ($products as $product): ?>
                                                    <option value="<?= $product['id'] ?>" 
                                                            <?= ($record['product_id'] == $product['id']) ? 'selected' : '' ?>>
                                                        <?= $product['product_code'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Output (Pcs)</label>
                                            <input type="number" step="1" min="0" class="form-control" name="assy_output[]" 
                                                   value="<?= htmlspecialchars($record['assy_output']) ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-danger w-100 remove-assy">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="row g-3 align-items-end assy-product">
                                    <div class="col-md-6">
                                        <label class="form-label">Product Code</label>
                                        <select name="assy_product_id[]" class="form-select" required>
                                            <option value="">-- Select Product --</option>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?= $product['id'] ?>">
                                                    <?= $product['product_code'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Output (Pcs)</label>
                                        <input type="number" step="1" min="0" class="form-control" name="assy_output[]" value="0" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger w-100 remove-assy">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-3">
                            <button type="button" id="add-assy" class="btn btn-outline-info">
                                <i class="fas fa-plus me-1"></i> Add Assembly Product
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Packing Performance -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-box-open me-2"></i> Packing Performance
                </div>
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No products found. Please <a href="add_product.php" class="alert-link">add products</a> first.
                        </div>
                    <?php else: ?>
                        <div id="packing-products">
                            <?php if (count($packing_records) > 0): ?>
                                <?php foreach ($packing_records as $record): ?>
                                    <div class="row g-3 align-items-end packing-product">
                                        <div class="col-md-6">
                                            <label class="form-label">Product Code</label>
                                            <select name="packing_product_id[]" class="form-select" required>
                                                <option value="">-- Select Product --</option>
                                                <?php foreach ($products as $product): ?>
                                                    <option value="<?= $product['id'] ?>" 
                                                            <?= ($record['product_id'] == $product['id']) ? 'selected' : '' ?>>
                                                        <?= $product['product_code'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Output (Pcs)</label>
                                            <input type="number" step="1" min="0" class="form-control" name="packing_output[]" 
                                                   value="<?= htmlspecialchars($record['packing_output']) ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-danger w-100 remove-packing">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="row g-3 align-items-end packing-product">
                                    <div class="col-md-6">
                                        <label class="form-label">Product Code</label>
                                        <select name="packing_product_id[]" class="form-select" required>
                                            <option value="">-- Select Product --</option>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?= $product['id'] ?>">
                                                    <?= $product['product_code'] ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Output (Pcs)</label>
                                        <input type="number" step="1" min="0" class="form-control" name="packing_output[]" value="0" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger w-100 remove-packing">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-3">
                            <button type="button" id="add-packing" class="btn btn-outline-success">
                                <i class="fas fa-plus me-1"></i> Add Packing Product
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="button-group mt-5 text-center">
                <button type="submit" class="btn btn-warning btn-lg px-5 me-3">
                    <i class="fas fa-save me-2"></i> Update Record
                </button>
                <a href="view_data.php" class="btn btn-secondary btn-lg px-5 me-3">
                    <i class="fas fa-arrow-left me-2"></i> Back to Data View
                </a>
                <a href="view_details.php?id=<?= $id ?>" class="btn btn-info btn-lg px-5">
                    <i class="fas fa-eye me-2"></i> View Details
                </a>
            </div>
        </form>
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
    <script>
        // Product options template
        const productOptions = `<?php foreach ($products as $product): ?>
            <option value="<?= $product['id'] ?>"><?= $product['product_code'] ?></option>
        <?php endforeach; ?>`;

        // Add ASSY product row
        document.getElementById('add-assy').addEventListener('click', function() {
            const container = document.getElementById('assy-products');
            const newRow = document.createElement('div');
            newRow.className = 'row g-3 align-items-end assy-product';
            newRow.innerHTML = `
                <div class="col-md-6">
                    <label class="form-label">Product Code</label>
                    <select name="assy_product_id[]" class="form-select" required>
                        <option value="">-- Select Product --</option>
                        ${productOptions}
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Output (Pcs)</label>
                    <input type="number" step="1" min="0" class="form-control" name="assy_output[]" value="0" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger w-100 remove-assy">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
        });

        // Add Packing product row
        document.getElementById('add-packing').addEventListener('click', function() {
            const container = document.getElementById('packing-products');
            const newRow = document.createElement('div');
            newRow.className = 'row g-3 align-items-end packing-product';
            newRow.innerHTML = `
                <div class="col-md-6">
                    <label class="form-label">Product Code</label>
                    <select name="packing_product_id[]" class="form-select" required>
                        <option value="">-- Select Product --</option>
                        ${productOptions}
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Output (Pcs)</label>
                    <input type="number" step="1" min="0" class="form-control" name="packing_output[]" value="0" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger w-100 remove-packing">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
        });

        // Remove buttons
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-assy')) {
                const parentRow = e.target.closest('.assy-product');
                if (document.querySelectorAll('#assy-products .assy-product').length > 1) {
                    parentRow.remove();
                } else {
                    alert('You need at least one ASSY product row.');
                }
            }
            
            if (e.target.closest('.remove-packing')) {
                const parentRow = e.target.closest('.packing-product');
                if (document.querySelectorAll('#packing-products .packing-product').length > 1) {
                    parentRow.remove();
                } else {
                    alert('You need at least one Packing product row.');
                }
            }
        });

        // Form validation
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const mp = parseInt(document.getElementById('mp').value) || 0;
            const absent = parseInt(document.getElementById('absent').value) || 0;
            const separated = parseInt(document.getElementById('separated_mp').value) || 0;
            
            // Validate manpower numbers
            if (absent + separated > mp) {
                e.preventDefault();
                alert('Error: Absent + Separated MP cannot exceed Total MP.');
                return false;
            }
            
            // Validate at least one product exists
            const assyProducts = document.querySelectorAll('#assy-products .assy-product');
            const packingProducts = document.querySelectorAll('#packing-products .packing-product');
            
            if (assyProducts.length === 0 && packingProducts.length === 0) {
                e.preventDefault();
                alert('Error: You must have at least one ASSY or Packing product.');
                return false;
            }
            
            return true;
        });

        // Real-time validation
        document.addEventListener('input', function(e) {
            if (e.target.id === 'mp' || e.target.id === 'absent' || e.target.id === 'separated_mp') {
                const mp = parseInt(document.getElementById('mp').value) || 0;
                const absent = parseInt(document.getElementById('absent').value) || 0;
                const separated = parseInt(document.getElementById('separated_mp').value) || 0;
                
                if (absent + separated > mp) {
                    e.target.classList.add('is-invalid');
                } else {
                    e.target.classList.remove('is-invalid');
                }
            }
        });
    </script>
</body>
</html>