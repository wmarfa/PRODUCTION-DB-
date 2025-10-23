<?php
require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

$message = "";
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}
if (isset($_GET['error'])) {
    $message = "Error: " . htmlspecialchars($_GET['error']);
}

// Fetch all products
$query = "SELECT * FROM products ORDER BY product_code ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Catalog Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <?php require_once "navbar.php"; ?>
    
    <div class="container-fluid mt-5 mb-5" style="max-width: 1400px;">
        <div class="page-header mb-4">
            <h1 class="page-title">PRODUCT CATALOG MANAGEMENT</h1>
            <p class="lead text-muted">Manage product codes, circuits, MHR, and specifications</p>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success' ?> d-flex align-items-center" role="alert">
                <i class="fas <?= strpos($message, 'Error') !== false ? 'fa-times-circle' : 'fa-check-circle' ?> me-2"></i>
                <div><?= $message ?></div>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <a href="add_product.php" class="btn btn-primary me-2">
                            <i class="fas fa-plus-circle me-2"></i> Add New Product
                        </a>
                        <a href="import_products.php" class="btn btn-success me-2">
                            <i class="fas fa-upload me-2"></i> Bulk Import Products
                        </a>
                    </div>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-home me-2"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-list-ul me-2"></i> Product List (<?= count($products) ?> Total)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 5%;">ID</th>
                                <th style="width: 30%;">Product Code</th>
                                <th class="text-end" style="width: 20%;">Circuit</th>
                                <th class="text-end" style="width: 20%;">MHR (Hours)</th>
                                <th class="text-end" style="width: 15%;">Qty/SH Pack</th>
                                <th style="width: 10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($products) > 0): ?>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['id']) ?></td>
                                    <td><?= htmlspecialchars($product['product_code']) ?></td>
                                    <td class="text-end"><?= number_format($product['circuit']) ?></td>
                                    <td class="text-end"><?= number_format($product['mhr'], 4) ?></td>
                                    <td class="text-end"><?= number_format($product['qty_sh_pack']) ?></td>
                                    <td>
                                        <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-danger btn-sm" title="Delete" 
                                                onclick="confirmDelete(<?= $product['id'] ?>, '<?= $product['product_code'] ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center p-4 text-muted">No products found. Please add a new product or use the bulk import feature.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
    <script>
        function confirmDelete(id, code) {
            if (confirm(`Are you sure you want to delete product "${code}" (ID: ${id})? This action cannot be undone.`)) {
                window.location.href = 'delete_product.php?id=' + id;
            }
        }
    </script>
</body>
</html>