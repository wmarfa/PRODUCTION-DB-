<?php
require_once "config.php";
$database = Database::getInstance();
$db = $database->getConnection();
$products = [];
$query = "SELECT * FROM products";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Check if products exist
if (empty($products)) {
    $error_message = "No products found in database. Please add products first.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Performance Entry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        .container { max-width: 1000px; }
        .form-section-group .card { margin-bottom: 2rem; }
        .assy-product, .packing-product {
            border: 1px dashed #dee2e6;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 0.375rem;
            background-color: #fcfcfd;
        }
        .product-search-container {
            position: relative;
        }
        .product-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .product-search-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f8f9fa;
        }
        .product-search-item:hover {
            background: #f8f9fa;
        }
        .selected-product {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            border-radius: 0.375rem;
            padding: 0.5rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <?php require_once "navbar.php"; ?>
    
    <div class="container mt-5 mb-5">
        <div class="page-header mb-4">
            <h1 class="page-title">DAILY PERFORMANCE ENTRY</h1>
            <p class="lead text-muted">Submit daily performance and production data</p>
        </div>

        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <div>Data saved successfully!</div>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fas fa-times-circle me-2"></i>
                <div>Error: <?= htmlspecialchars($_GET['error']) ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-warning d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <div><?= $error_message ?></div>
            </div>
        <?php endif; ?>

        <form action="save_performance.php" method="POST" class="form-section-group" id="performanceForm">
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-2"></i> Operational Metrics
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="date" class="form-label fw-bold">Date</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="line_shift" class="form-label fw-bold">Line / Shift</label>
                            <input type="text" class="form-control" id="line_shift" name="line_shift" placeholder="e.g., A-Line, Shift 1" required>
                        </div>
                        <div class="col-md-4">
                            <label for="leader" class="form-label fw-bold">Team Leader</label>
                            <input type="text" class="form-control" id="leader" name="leader" placeholder="Enter Leader Name" required>
                        </div>
                    </div>
                    
                    <h5 class="mt-4 pb-2 border-bottom text-secondary">Manpower & Attendance</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="mp" class="form-label">Total MP (Planned)</label>
                            <input type="number" step="1" min="0" class="form-control" id="mp" name="mp" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="qc" class="form-label">QC MP (Support)</label>
                            <input type="number" step="1" min="0" class="form-control" id="qc" name="qc" value="0">
                        </div>
                        <div class="col-md-3">
                            <label for="absent" class="form-label text-danger">Absent MP</label>
                            <input type="number" step="1" min="0" class="form-control" id="absent" name="absent" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label for="separated_mp" class="form-label text-danger">Separated MP</label>
                            <input type="number" step="1" min="0" class="form-control" id="separated_mp" name="separated_mp" value="0" required>
                        </div>
                    </div>

                    <h5 class="mt-4 pb-2 border-bottom text-secondary">Work Hours & Plan</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="plan" class="form-label">Daily Production Plan (Pcs)</label>
                            <input type="number" step="1" min="0" class="form-control" id="plan" name="plan" value="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="ot_hours" class="form-label">Overtime Hours</label>
                            <input type="number" step="0.1" min="0" class="form-control" id="ot_hours" name="ot_hours" value="0.0">
                        </div>
                    </div>

                    <h5 class="mt-4 pb-2 border-bottom text-secondary">Manpower for MHR Calculation</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="no_ot_mp" class="form-label">MP (No OT)</label>
                            <input type="number" step="1" min="0" class="form-control" id="no_ot_mp" name="no_ot_mp" value="0" required>
                            <div class="form-text">MP who worked standard hours (7.66 hours each).</div>
                        </div>
                        <div class="col-md-6">
                            <label for="ot_mp" class="form-label">MP (With OT)</label>
                            <input type="number" step="1" min="0" class="form-control" id="ot_mp" name="ot_mp" value="0" required>
                            <div class="form-text">MP who worked overtime (hours specified above).</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-info">
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
                            <div class="row g-3 align-items-end assy-product">
                                <div class="col-md-6">
                                    <label class="form-label">Product Code</label>
                                    <div class="product-search-container">
                                        <input type="text" class="form-control assy-product-search" placeholder="Search for product..." data-product-type="assy">
                                        <div class="product-search-results" id="assy-search-results"></div>
                                    </div>
                                    <input type="hidden" name="assy_product_id[]" class="assy-product-id">
                                    <div class="selected-product d-none">
                                        <small><strong>Selected:</strong> <span class="selected-product-name"></span></small>
                                        <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-selected-product">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Output (Pcs)</label>
                                    <input type="number" step="1" min="0" class="form-control" name="assy_output[]" value="0" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-danger w-100 remove-assy"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="button" id="add-assy" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Product
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                 <div class="card-header bg-success">
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
                            <div class="row g-3 align-items-end packing-product">
                                <div class="col-md-6">
                                    <label class="form-label">Product Code</label>
                                    <div class="product-search-container">
                                        <input type="text" class="form-control packing-product-search" placeholder="Search for product..." data-product-type="packing">
                                        <div class="product-search-results" id="packing-search-results"></div>
                                    </div>
                                    <input type="hidden" name="packing_product_id[]" class="packing-product-id">
                                    <div class="selected-product d-none">
                                        <small><strong>Selected:</strong> <span class="selected-product-name"></span></small>
                                        <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-selected-product">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Output (Pcs)</label>
                                    <input type="number" step="1" min="0" class="form-control" name="packing_output[]" value="0" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-danger w-100 remove-packing"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="button" id="add-packing" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-plus"></i> Add Product
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="button-group mt-5 text-center">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-save me-2"></i> Save Performance Data
                </button>
                <a href="index.php" class="btn btn-secondary btn-lg px-5">
                    <i class="fas fa-arrow-left me-2"></i> Back to Home
                </a>
                <!-- Debug button -->
                <button type="button" id="debugBtn" class="btn btn-warning btn-lg px-5">
                    <i class="fas fa-bug me-2"></i> Debug Form
                </button>
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
        // Product data for search
        const products = <?= json_encode($products) ?>;

        // Debug function to show form data
        document.getElementById('debugBtn').addEventListener('click', function() {
            const formData = new FormData(document.getElementById('performanceForm'));
            console.log('Form Data:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ': ' + value);
            }
            alert('Check browser console for form data');
        });

        // Product search functionality
        function initializeProductSearch() {
            document.querySelectorAll('.assy-product-search, .packing-product-search').forEach(searchInput => {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const resultsContainer = this.parentNode.querySelector('.product-search-results');
                    
                    if (searchTerm.length < 2) {
                        resultsContainer.style.display = 'none';
                        return;
                    }
                    
                    const filteredProducts = products.filter(product => 
                        product.product_code.toLowerCase().includes(searchTerm)
                    );
                    
                    displaySearchResults(filteredProducts, resultsContainer, this);
                });
                
                // Hide results when clicking outside
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.product-search-container')) {
                        document.querySelectorAll('.product-search-results').forEach(container => {
                            container.style.display = 'none';
                        });
                    }
                });
            });
        }

        function displaySearchResults(products, container, searchInput) {
            container.innerHTML = '';
            
            if (products.length === 0) {
                container.innerHTML = '<div class="product-search-item text-muted">No products found</div>';
            } else {
                products.forEach(product => {
                    const item = document.createElement('div');
                    item.className = 'product-search-item';
                    item.textContent = product.product_code;
                    item.addEventListener('click', function() {
                        selectProduct(product, searchInput);
                    });
                    container.appendChild(item);
                });
            }
            
            container.style.display = 'block';
        }

        function selectProduct(product, searchInput) {
            const productContainer = searchInput.closest('.col-md-6');
            const hiddenInput = productContainer.querySelector('.assy-product-id, .packing-product-id');
            const selectedProductDiv = productContainer.querySelector('.selected-product');
            const productNameSpan = selectedProductDiv.querySelector('.selected-product-name');
            
            // Set values
            hiddenInput.value = product.id;
            productNameSpan.textContent = product.product_code;
            searchInput.value = '';
            
            // Show selected product
            selectedProductDiv.classList.remove('d-none');
            
            // Hide search results
            productContainer.querySelector('.product-search-results').style.display = 'none';
        }

        // Remove selected product
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-selected-product')) {
                const selectedProductDiv = e.target.closest('.selected-product');
                const hiddenInput = selectedProductDiv.parentNode.querySelector('.assy-product-id, .packing-product-id');
                const searchInput = selectedProductDiv.parentNode.querySelector('.assy-product-search, .packing-product-search');
                
                // Clear values
                hiddenInput.value = '';
                searchInput.value = '';
                selectedProductDiv.classList.add('d-none');
            }
        });

        // Add ASSY product row
        document.getElementById('add-assy').addEventListener('click', function() {
            const container = document.getElementById('assy-products');
            const newRowHtml = `
                <div class="row g-3 align-items-end assy-product">
                    <div class="col-md-6">
                        <label class="form-label">Product Code</label>
                        <div class="product-search-container">
                            <input type="text" class="form-control assy-product-search" placeholder="Search for product..." data-product-type="assy">
                            <div class="product-search-results"></div>
                        </div>
                        <input type="hidden" name="assy_product_id[]" class="assy-product-id">
                        <div class="selected-product d-none">
                            <small><strong>Selected:</strong> <span class="selected-product-name"></span></small>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-selected-product">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Output (Pcs)</label>
                        <input type="number" step="1" min="0" class="form-control" name="assy_output[]" value="0" required>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger w-100 remove-assy"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', newRowHtml);
            initializeProductSearch();
        });

        // Add Packing product row
        document.getElementById('add-packing').addEventListener('click', function() {
            const container = document.getElementById('packing-products');
            const newRowHtml = `
                <div class="row g-3 align-items-end packing-product">
                    <div class="col-md-6">
                        <label class="form-label">Product Code</label>
                        <div class="product-search-container">
                            <input type="text" class="form-control packing-product-search" placeholder="Search for product..." data-product-type="packing">
                            <div class="product-search-results"></div>
                        </div>
                        <input type="hidden" name="packing_product_id[]" class="packing-product-id">
                        <div class="selected-product d-none">
                            <small><strong>Selected:</strong> <span class="selected-product-name"></span></small>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-selected-product">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Output (Pcs)</label>
                        <input type="number" step="1" min="0" class="form-control" name="packing_output[]" value="0" required>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger w-100 remove-packing"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', newRowHtml);
            initializeProductSearch();
        });

        // Remove buttons
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-assy')) {
                const parentRow = e.target.closest('.assy-product');
                if (document.querySelectorAll('#assy-products .assy-product').length > 1) {
                    parentRow.remove();
                }
            }
            if (e.target.closest('.remove-packing')) {
                const parentRow = e.target.closest('.packing-product');
                if (document.querySelectorAll('#packing-products .packing-product').length > 1) {
                    parentRow.remove();
                }
            }
        });

        // Form validation
        document.getElementById('performanceForm').addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            // Validate that at least one product is selected
            const assyProductIds = document.querySelectorAll('input[name="assy_product_id[]"]');
            const packingProductIds = document.querySelectorAll('input[name="packing_product_id[]"]');
            let hasProducts = false;

            assyProductIds.forEach(input => {
                if (input.value) hasProducts = true;
            });
            packingProductIds.forEach(input => {
                if (input.value) hasProducts = true;
            });

            if (!hasProducts) {
                alert('Please select at least one product for ASSY or Packing performance.');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields and select at least one product.');
            }
        });

        // Initialize product search on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeProductSearch();
        });
    </script>
</body>
</html>