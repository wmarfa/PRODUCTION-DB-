<?php
require_once "config.php";

if (!isset($_GET['id'])) {
    die("Product ID not specified");
}

$database = Database::getInstance();
$db = $database->getConnection();

$query = "SELECT * FROM products WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_GET['id']]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Product not found");
}

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
            // Check if product code already exists for another ID
            $check_query = "SELECT id FROM products WHERE product_code = ? AND id != ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([$product_code, $_GET['id']]);
            
            if ($check_stmt->rowCount() > 0) {
                $error_message = "Product code already exists for another product!";
            } else {
                $query = "UPDATE products SET product_code = ?, circuit = ?, mhr = ?, qty_sh_pack = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$product_code, $circuit, $mhr, $qty_sh_pack, $_GET['id']])) {
                    $success_message = "Product updated successfully!";
                    // Fetch updated data to display immediately
                    $query = "SELECT * FROM products WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$_GET['id']]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error_message = "Error updating product!";
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
    <title>Edit Product: <?= htmlspecialchars($product['product_code']) ?></title>
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
                    <h1 class="page-title">EDIT PRODUCT</h1>
                    <p class="lead text-muted">Update product details</p>
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
                    <div class="card-header bg-warning text-white">
                        <i class="fas fa-edit me-2"></i> Editing: <?= htmlspecialchars($product['product_code']) ?>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="product_code" class="form-label fw-bold">Product Code</label>
                                    <input type="text" class="form-control" id="product_code" name="product_code" 
                                           value="<?= htmlspecialchars($product['product_code']) ?>" 
                                           placeholder="Enter Product Code" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="circuit" class="form-label">Circuit Value</label>
                                    <input type="number" step="1" min="0" class="form-control" id="circuit" name="circuit" 
                                           value="<?= htmlspecialchars($product['circuit']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="mhr" class="form-label">MHR (Man Hour Rate)</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="mhr" name="mhr" 
                                           value="<?= htmlspecialchars($product['mhr']) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="qty_sh_pack" class="form-label">Qty/SH Pack</label>
                                    <input type="number" step="1" min="0" class="form-control" id="qty_sh_pack" name="qty_sh_pack" 
                                           value="<?= htmlspecialchars($product['qty_sh_pack']) ?>" required>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-save"></i> Update Product
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <a href="products.php" class="btn btn-secondary w-100">
                                        <i class="fas fa-arrow-left"></i> Back to Products
                                    </a>
                                </div>
                                <div class="col-md-4">
                                    <a href="add_product.php" class="btn btn-success w-100">
                                        <i class="fas fa-plus"></i> Add New
                                    </a>
                                </div>
                            </div>
                        </form>
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