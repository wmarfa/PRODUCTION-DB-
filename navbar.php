<?php
// navbar.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark modern-nav">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-chart-line me-2"></i>Performance Tracking
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_data.php">
                            <i class="fas fa-database me-1"></i>View Data
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="entry_form.php">
                            <i class="fas fa-plus-circle me-1"></i>Add Data
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-cubes me-1"></i>Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="FORMULAS.php">
                            <i class="fas fa-calculator me-1"></i>Formulas
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</body>
</html>