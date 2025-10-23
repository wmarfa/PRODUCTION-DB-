<?php
require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

$message = "";

if ($_POST && isset($_POST['product_data'])) {
    $product_data = trim($_POST['product_data']);
    $lines = explode("\n", $product_data);
    $success_count = 0;
    $error_count = 0;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $parts = explode(",", $line);
        if (count($parts) == 4) {
            $product_code = trim($parts[0]);
            $circuit = trim($parts[1]);
            $mhr = trim($parts[2]);
            $qty_sh_pack = trim($parts[3]);
            
            try {
                // Check if exists
                $check_query = "SELECT id FROM products WHERE product_code = ?";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->execute([$product_code]);
                
                if ($check_stmt->rowCount() == 0) {
                    $query = "INSERT INTO products (product_code, circuit, mhr, qty_sh_pack) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$product_code, $circuit, $mhr, $qty_sh_pack]);
                    $success_count++;
                } else {
                    $error_count++;
                }
            } catch (Exception $e) {
                $error_count++;
            }
        } else {
            $error_count++;
        }
    }
    $message = "Import complete. $success_count products added successfully. $error_count records skipped (already exist or invalid format).";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Products</title>
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
                    <h1 class="page-title">IMPORT PRODUCT CATALOG</h1>
                    <p class="lead text-muted">Bulk import products using CSV format</p>
                </div>

                <div class="card">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-upload me-2"></i> Bulk Product Import
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-info d-flex align-items-center" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <div><?= $message ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Product Data (CSV format)</label>
                                <textarea name="product_data" class="form-control" rows="10" 
                                          placeholder="ProductCode,Circuit,MHR,Qty_SH_Pack
91115-G9201,250,4.23,10
91115-G9301,260,5.01,10
..."></textarea>
                                <div class="form-text">
                                    <strong>Format:</strong> `ProductCode,Circuit,MHR,Qty_SH_Pack` (one product per line). Existing products will be skipped.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success me-2">
                                <i class="fas fa-upload"></i> Import Products
                            </button>
                            <a href="products.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Products
                            </a>
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