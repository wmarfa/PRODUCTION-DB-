<?php
require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

$error_message = "";
$success_message = "";

if ($_POST) {
    $product_code = $_POST['product_code'] ?? '';
    $circuit = $_POST['circuit'] ?? 0;
    $mhr = $_POST['mhr'] ?? 0;
    $qty_sh_pack = $_POST['qty_sh_pack'] ?? 0;

    if (empty($product_code)) {
        $error_message = "Product code is required!";
    } else {
        try {
            // Check if product code already exists
            $check_query = "SELECT id FROM products WHERE product_code = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$product_code]);
            
            if ($check_stmt->rowCount() > 0) {
                $error_message = "Product code already exists!";
            } else {
                $query = "INSERT INTO products (product_code, circuit, mhr, qty_sh_pack) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$product_code, $circuit, $mhr, $qty_sh_pack])) {
                    $success_message = "Product added successfully!";
                    // Clear form
                    $_POST = array();
                } else {
                    $error_message = "Error adding product!";
                }
            }
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <?php require_once "navbar.php"; ?>
    
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="page-header mb-4">
                    <h1 class="page-title">ADD NEW PRODUCT</h1>
                    <p class="lead text-muted">Add new product to the catalog</p>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <div><?= $success_message ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="fas fa-times-circle me-2"></i>
                        <div><?= $error_message ?></div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-file-alt me-2"></i> Product Information Entry
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="product_code" class="form-label fw-bold">Product Code</label>
                                    <input type="text" class="form-control" id="product_code" name="product_code" 
                                           value="<?= htmlspecialchars($_POST['product_code'] ?? '') ?>" 
                                           placeholder="Enter Product Code (e.g., 91115-G9201)" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="circuit" class="form-label">Circuit Value</label>
                                    <input type="number" step="1" min="0" class="form-control" id="circuit" name="circuit" 
                                           value="<?= htmlspecialchars($_POST['circuit'] ?? 0) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="mhr" class="form-label">MHR (Man Hour Rate)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="mhr" name="mhr" 
                                           value="<?= htmlspecialchars($_POST['mhr'] ?? 0) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="qty_sh_pack" class="form-label">Qty/SH Pack</label>
                                    <input type="number" step="1" min="0" class="form-control" id="qty_sh_pack" name="qty_sh_pack" 
                                           value="<?= htmlspecialchars($_POST['qty_sh_pack'] ?? 0) ?>" required>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-plus-circle"></i> Add Product
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <a href="products.php" class="btn btn-secondary w-100">
                                        <i class="fas fa-arrow-left"></i> Back to Products
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="import_products.php" class="btn btn-success w-100">
                                        <i class="fas fa-upload"></i> Import List
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-info">
                        <h5 class="card-title mb-0 text-white"><i class="fas fa-info-circle"></i> About Product Fields</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h6>Circuit</h6>
                                <p class="small text-muted">The circuit value used for CPH calculations.</p>
                            </div>
                            <div class="col-md-4">
                                <h6>MHR (Man Hour Rate)</h6>
                                <p class="small text-muted">Time required to produce one unit (in hours).</p>
                            </div>
                            <div class="col-md-4">
                                <h6>Qty/SH Pack</h6>
                                <p class="small text-muted">Quantity per standard hour pack.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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