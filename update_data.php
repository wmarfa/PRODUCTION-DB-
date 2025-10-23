<?php
require_once "config.php";

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view_data.php?error=No record ID specified");
    exit();
}

$record_id = intval($_GET['id']); // Sanitize the ID

$database = Database::getInstance();
$db = $database->getConnection();

// Get the main performance record
$query = "SELECT * FROM daily_performance WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$record_id]);
$main_record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$main_record) {
    header("Location: view_data.php?error=Record not found");
    exit();
}

// Get ASSY performance data for this record
$assy_query = "
    SELECT ap.*, p.product_code 
    FROM assy_performance ap 
    JOIN products p ON ap.product_id = p.id 
    WHERE ap.daily_performance_id = ?
";
$assy_stmt = $db->prepare($assy_query);
$assy_stmt->execute([$record_id]);
$assy_data = $assy_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get Packing performance data for this record
$packing_query = "
    SELECT pp.*, p.product_code 
    FROM packing_performance pp 
    JOIN products p ON pp.product_id = p.id 
    WHERE pp.daily_performance_id = ?
";
$packing_stmt = $db->prepare($packing_query);
$packing_stmt->execute([$record_id]);
$packing_data = $packing_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all products for dropdowns
$products_query = "SELECT * FROM products ORDER BY product_code";
$products = $db->query($products_query)->fetchAll(PDO::FETCH_ASSOC);

$error_message = "";
$success_message = "";

// Handle form submission
if ($_POST) {
    try {
        $db->beginTransaction();

        // Validate required fields
        $required_fields = ['date', 'line_shift', 'leader', 'mp', 'absent', 'separated_mp', 'plan', 'no_ot_mp', 'ot_mp', 'assy_wt'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                throw new Exception("Required field '$field' is missing.");
            }
        }

        // Update main performance record
        $update_query = "
            UPDATE daily_performance 
            SET date = ?, line_shift = ?, leader = ?, mp = ?, qc = ?, absent = ?, 
                separated_mp = ?, no_ot_mp = ?, ot_mp = ?, ot_hours = ?, plan = ?, assy_wt = ?
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
            $_POST['no_ot_mp'],
            $_POST['ot_mp'],
            $_POST['ot_hours'] ?? 0,
            $_POST['plan'],
            $_POST['assy_wt'],
            $record_id
        ]);

        // Delete existing ASSY performance data
        $delete_assy = "DELETE FROM assy_performance WHERE daily_performance_id = ?";
        $stmt = $db->prepare($delete_assy);
        $stmt->execute([$record_id]);

        // Insert updated ASSY performance data
        if (isset($_POST['assy_product'])) {
            for ($i = 0; $i < count($_POST['assy_product']); $i++) {
                if (!empty($_POST['assy_product'][$i]) && !empty($_POST['assy_output'][$i])) {
                    $insert_assy = "INSERT INTO assy_performance (daily_performance_id, product_id, assy_output) VALUES (?, ?, ?)";
                    $stmt = $db->prepare($insert_assy);
                    $stmt->execute([$record_id, $_POST['assy_product'][$i], $_POST['assy_output'][$i]]);
                }
            }
        }

        // Delete existing Packing performance data
        $delete_packing = "DELETE FROM packing_performance WHERE daily_performance_id = ?";
        $stmt = $db->prepare($delete_packing);
        $stmt->execute([$record_id]);

        // Insert updated Packing performance data
        if (isset($_POST['packing_product'])) {
            for ($i = 0; $i < count($_POST['packing_product']); $i++) {
                if (!empty($_POST['packing_product'][$i]) && !empty($_POST['packing_output'][$i])) {
                    $insert_packing = "INSERT INTO packing_performance (daily_performance_id, product_id, packing_output) VALUES (?, ?, ?)";
                    $stmt = $db->prepare($insert_packing);
                    $stmt->execute([$record_id, $_POST['packing_product'][$i], $_POST['packing_output'][$i]]);
                }
            }
        }

        $db->commit();
        $success_message = "Record updated successfully!";
        
        // Refresh data
        $stmt = $db->prepare($query);
        $stmt->execute([$record_id]);
        $main_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $assy_stmt->execute([$record_id]);
        $assy_data = $assy_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $packing_stmt->execute([$record_id]);
        $packing_data = $packing_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $db->rollBack();
        $error_message = "Error updating record: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Performance Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .section-title {
            background-color: #f8f9fa;
            padding: 10px;
            margin-top: 20px;
            border-left: 4px solid #007bff;
        }
        .form-section {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .current-record {
            background-color: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php require_once "navbar.php"; ?>
    
    <div class="container mt-4">
        <div class="current-record">
            <h4><i class="fas fa-edit"></i> Update Performance Record</h4>
            <p class="mb-1"><strong>Record ID:</strong> <?= $record_id ?></p>
            <p class="mb-1"><strong>Date:</strong> <?= $main_record['date'] ?></p>
            <p class="mb-0"><strong>Line/Shift:</strong> <?= $main_record['line_shift'] ?></p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
        <?php endif; ?>

        <form method="POST">
            <!-- Daily Performance Summary -->
            <div class="form-section">
                <h4 class="section-title">Daily Performance Summary</h4>
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Date *</label>
                        <input type="date" name="date" class="form-control" value="<?= $main_record['date'] ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Line/Shift *</label>
                        <input type="text" name="line_shift" class="form-control" value="<?= $main_record['line_shift'] ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Leader *</label>
                        <input type="text" name="leader" class="form-control" value="<?= $main_record['leader'] ?>" required>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-3">
                        <label class="form-label">MP *</label>
                        <input type="number" name="mp" class="form-control" value="<?= $main_record['mp'] ?>" required min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">QC *</label>
                        <input type="number" name="qc" class="form-control" value="<?= $main_record['qc'] ?>" required min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Absent *</label>
                        <input type="number" name="absent" class="form-control" value="<?= $main_record['absent'] ?>" required min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Separated MP *</label>
                        <input type="number" name="separated_mp" class="form-control" value="<?= $main_record['separated_mp'] ?>" required min="0">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <label class="form-label">No OT MP *</label>
                        <input type="number" name="no_ot_mp" class="form-control" value="<?= $main_record['no_ot_mp'] ?>" required min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">OT MP *</label>
                        <input type="number" name="ot_mp" class="form-control" value="<?= $main_record['ot_mp'] ?>" required min="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">OT Hours *</label>
                        <input type="number" step="0.01" name="ot_hours" class="form-control" value="<?= $main_record['ot_hours'] ?>" required min="0">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="form-label">Plan *</label>
                        <input type="number" name="plan" class="form-control" value="<?= $main_record['plan'] ?>" required min="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ASSY Working Time *</label>
                        <input type="number" step="0.1" name="assy_wt" class="form-control" value="<?= $main_record['assy_wt'] ?>" required min="0">
                    </div>
                </div>
            </div>

            <!-- Assembly Performance -->
            <div class="form-section">
                <h4 class="section-title">ASSY PERFORMANCE</h4>
                <div id="assy-products">
                    <?php if (empty($assy_data)): ?>
                        <div class="assy-product row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Product</label>
                                <select name="assy_product[]" class="form-control">
                                    <option value="">Select Product</option>
                                    <?php foreach($products as $product): ?>
                                        <option value="<?= $product['id'] ?>"><?= $product['product_code'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ASSY Output</label>
                                <input type="number" name="assy_output[]" class="form-control" min="0">
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-danger mt-4 remove-assy">Remove</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach($assy_data as $index => $assy): ?>
                            <div class="assy-product row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Product</label>
                                    <select name="assy_product[]" class="form-control">
                                        <option value="">Select Product</option>
                                        <?php foreach($products as $product): ?>
                                            <option value="<?= $product['id'] ?>" <?= $product['id'] == $assy['product_id'] ? 'selected' : '' ?>>
                                                <?= $product['product_code'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ASSY Output</label>
                                    <input type="number" name="assy_output[]" class="form-control" value="<?= $assy['assy_output'] ?>" min="0">
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-danger mt-4 remove-assy">Remove</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-secondary" id="add-assy">
                    <i class="fas fa-plus"></i> Add ASSY Product
                </button>
            </div>

            <!-- Packing Performance -->
            <div class="form-section">
                <h4 class="section-title">PACKING PERFORMANCE</h4>
                <div id="packing-products">
                    <?php if (empty($packing_data)): ?>
                        <div class="packing-product row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Product</label>
                                <select name="packing_product[]" class="form-control">
                                    <option value="">Select Product</option>
                                    <?php foreach($products as $product): ?>
                                        <option value="<?= $product['id'] ?>"><?= $product['product_code'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Packing Output</label>
                                <input type="number" name="packing_output[]" class="form-control" min="0">
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-danger mt-4 remove-packing">Remove</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach($packing_data as $index => $packing): ?>
                            <div class="packing-product row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Product</label>
                                    <select name="packing_product[]" class="form-control">
                                        <option value="">Select Product</option>
                                        <?php foreach($products as $product): ?>
                                            <option value="<?= $product['id'] ?>" <?= $product['id'] == $packing['product_id'] ? 'selected' : '' ?>>
                                                <?= $product['product_code'] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Packing Output</label>
                                    <input type="number" name="packing_output[]" class="form-control" value="<?= $packing['packing_output'] ?>" min="0">
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-danger mt-4 remove-packing">Remove</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-secondary" id="add-packing">
                    <i class="fas fa-plus"></i> Add Packing Product
                </button>
            </div>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Update Performance Data
                </button>
                <a href="view_data.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <a href="view_details.php?id=<?= $record_id ?>" class="btn btn-info btn-lg">
                    <i class="fas fa-eye"></i> View Details
                </a>
            </div>
        </form>
    </div>

    <script>
        // Add ASSY product row
        document.getElementById('add-assy').addEventListener('click', function() {
            const newRow = document.querySelector('.assy-product').cloneNode(true);
            newRow.querySelectorAll('input').forEach(input => input.value = '');
            newRow.querySelector('select').selectedIndex = 0;
            document.getElementById('assy-products').appendChild(newRow);
        });

        // Add Packing product row
        document.getElementById('add-packing').addEventListener('click', function() {
            const newRow = document.querySelector('.packing-product').cloneNode(true);
            newRow.querySelectorAll('input').forEach(input => input.value = '');
            newRow.querySelector('select').selectedIndex = 0;
            document.getElementById('packing-products').appendChild(newRow);
        });

        // Remove buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-assy')) {
                const assyProducts = document.querySelectorAll('.assy-product');
                if (assyProducts.length > 1) {
                    e.target.closest('.assy-product').remove();
                } else {
                    alert('You need at least one ASSY product');
                }
            }
            if (e.target.classList.contains('remove-packing')) {
                const packingProducts = document.querySelectorAll('.packing-product');
                if (packingProducts.length > 1) {
                    e.target.closest('.packing-product').remove();
                }
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            let valid = true;
            const requiredFields = document.querySelectorAll('input[required], select[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'red';
                    valid = false;
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields (marked with *)');
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>