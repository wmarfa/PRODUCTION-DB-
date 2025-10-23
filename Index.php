<?php
require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

// Get recent statistics for the dashboard
$total_products = $db->query("SELECT COUNT(*) as count FROM products")->fetch(PDO::FETCH_ASSOC)['count'];
$total_performance_records = $db->query("SELECT COUNT(*) as count FROM daily_performance")->fetch(PDO::FETCH_ASSOC)['count'];
$total_assy_records = $db->query("SELECT COUNT(*) as count FROM assy_performance")->fetch(PDO::FETCH_ASSOC)['count'];
$total_packing_records = $db->query("SELECT COUNT(*) as count FROM packing_performance")->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent performance data
$recent_query = "
    SELECT 
        dp.*,
        (SELECT COALESCE(SUM(ap.assy_output), 0) 
         FROM assy_performance ap 
         WHERE ap.daily_performance_id = dp.id) as total_assy_output
    FROM daily_performance dp
    ORDER BY dp.date DESC, dp.created_at DESC
    LIMIT 5
";

$recent_data = $db->query($recent_query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate some basic metrics from recent data
if (!empty($recent_data)) {
    $total_mp = 0;
    $total_absent = 0;
    $total_plan = 0;
    $total_output = 0;
    
    foreach ($recent_data as $row) {
        $total_mp += $row['mp'];
        $total_absent += $row['absent'];
        $total_plan += $row['plan'];
        $total_output += $row['total_assy_output'];
    }
    
    $avg_absent_rate = $total_mp > 0 ? round(($total_absent / $total_mp) * 100, 1) : 0;
    $avg_plan_completion = $total_plan > 0 ? round(($total_output / $total_plan) * 100, 1) : 0;
} else {
    $avg_absent_rate = 0;
    $avg_plan_completion = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Tracking System - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --purple-color: #6f42c1;
            --pink-color: #e83e8c;
            --orange-color: #fd7e14;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        .system-health {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.25rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .stat-number {
            font-size: 1.75rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.85rem;
        }
        
        .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .access-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .access-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        }
        
        .access-card.data-entry { border-left-color: var(--success-color); }
        .access-card.data-view { border-left-color: var(--info-color); }
        .access-card.products { border-left-color: var(--warning-color); }
        .access-card.analysis { border-left-color: var(--purple-color); }
        .access-card.backup { border-left-color: var(--pink-color); }
        .access-card.system { border-left-color: var(--orange-color); }
        
        .access-icon {
            font-size: 1.75rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .access-card.data-entry .access-icon { color: var(--success-color); }
        .access-card.data-view .access-icon { color: var(--info-color); }
        .access-card.products .access-icon { color: var(--warning-color); }
        .access-card.analysis .access-icon { color: var(--purple-color); }
        .access-card.backup .access-icon { color: var(--pink-color); }
        .access-card.system .access-icon { color: var(--orange-color); }
        
        .access-links {
            list-style: none;
            padding: 0;
            margin: 1rem 0 0 0;
            flex-grow: 1;
        }
        
        .access-links li {
            margin-bottom: 0.5rem;
        }
        
        .access-links a {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            border-radius: 5px;
            text-decoration: none;
            color: #495057;
            transition: all 0.2s ease;
        }
        
        .access-links a:hover {
            background: #f8f9fa;
            color: var(--primary-color);
            transform: translateX(5px);
        }
        
        .access-links .fa-chevron-right {
            margin-right: 0.5rem;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .recent-activity {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-top: 2rem;
        }
        
        .footer {
            background-color: #343a40;
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .system-health .row {
                text-align: center;
            }
            
            .system-health .col-md-4 {
                margin-top: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 0.75rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .quick-access-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .access-card {
                padding: 1.25rem;
            }
            
            .recent-activity {
                padding: 1rem;
            }
            
            .table-responsive {
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            .system-health {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .card-body .row .col-6 {
                margin-bottom: 1rem;
            }
            
            .btn-lg {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
        }
        
        /* Card improvements */
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
        }
        
        /* Table improvements */
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .table-responsive {
            border-radius: 8px;
        }
        
        /* Button improvements */
        .btn {
            border-radius: 6px;
            font-weight: 500;
        }
        
        .d-grid .btn {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <?php require_once "navbar.php"; ?>
    
    <div class="container mt-4 mb-5">
        <!-- System Health Overview -->
        <div class="system-health">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h3 mb-2">PERFORMANCE TRACKING SYSTEM</h1>
                    <p class="lead mb-0">Complete Operational Oversight and Data Management Solution</p>
                </div>
                <div class="col-md-4 text-center text-md-end">
                    <div class="row justify-content-center justify-content-md-end">
                        <div class="col-4 col-md-4">
                            <div class="h4 fw-bold"><?= $total_products ?></div>
                            <small>Products</small>
                        </div>
                        <div class="col-4 col-md-4">
                            <div class="h4 fw-bold"><?= $total_performance_records ?></div>
                            <small>Records</small>
                        </div>
                        <div class="col-4 col-md-4">
                            <div class="h4 fw-bold"><?= ($total_assy_records + $total_packing_records) ?></div>
                            <small>Outputs</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_products ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_performance_records ?></div>
                <div class="stat-label">Performance Records</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_assy_records ?></div>
                <div class="stat-label">ASSY Records</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_packing_records ?></div>
                <div class="stat-label">Packing Records</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $avg_absent_rate ?>%</div>
                <div class="stat-label">Avg Absent Rate</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $avg_plan_completion ?>%</div>
                <div class="stat-label">Avg Plan Completion</div>
            </div>
        </div>

        <!-- Quick Access Grid -->
        <h2 class="h4 mb-3"><i class="fas fa-th-large me-2"></i>Quick Access</h2>
        <div class="quick-access-grid">
            
            <!-- Data Entry -->
            <div class="access-card data-entry">
                <div class="access-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <h4 class="h5">Data Entry</h4>
                <p class="text-muted small">Submit and manage performance data</p>
                <ul class="access-links">
                    <li>
                        <a href="entry_form.php">
                            <i class="fas fa-chevron-right"></i>
                            Enter Daily Performance Data
                        </a>
                    </li>
                    <li>
                        <a href="add_product.php">
                            <i class="fas fa-chevron-right"></i>
                            Add New Product
                        </a>
                    </li>
                    <li>
                        <a href="import_products.php">
                            <i class="fas fa-chevron-right"></i>
                            Bulk Import Products
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Data View & Management -->
            <div class="access-card data-view">
                <div class="access-icon">
                    <i class="fas fa-database"></i>
                </div>
                <h4 class="h5">Data View & Management</h4>
                <p class="text-muted small">View, edit, and manage existing data</p>
                <ul class="access-links">
                    <li>
                        <a href="view_data.php">
                            <i class="fas fa-chevron-right"></i>
                            View All Performance Data
                        </a>
                    </li>
                    <li>
                        <a href="products.php">
                            <i class="fas fa-chevron-right"></i>
                            Manage Product Catalog
                        </a>
                    </li>
                    <li>
                        <a href="export_data.php">
                            <i class="fas fa-chevron-right"></i>
                            Export Data to CSV
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Performance Analysis -->
            <div class="access-card analysis">
                <div class="access-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h4 class="h5">Performance Analysis</h4>
                <p class="text-muted small">Analyze and visualize performance metrics</p>
                <ul class="access-links">
                    <li>
                        <a href="dashboard.php">
                            <i class="fas fa-chevron-right"></i>
                            Performance Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="trend_analysis.php">
                            <i class="fas fa-chevron-right"></i>
                            CPH Trend Analysis
                        </a>
                    </li>
                    <li>
                        <a href="view_details.php">
                            <i class="fas fa-chevron-right"></i>
                            View Record Details
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Products Management -->
            <div class="access-card products">
                <div class="access-icon">
                    <i class="fas fa-cubes"></i>
                </div>
                <h4 class="h5">Products Management</h4>
                <p class="text-muted small">Manage product catalog and specifications</p>
                <ul class="access-links">
                    <li>
                        <a href="products.php">
                            <i class="fas fa-chevron-right"></i>
                            View All Products
                        </a>
                    </li>
                    <li>
                        <a href="add_product.php">
                            <i class="fas fa-chevron-right"></i>
                            Add New Product
                        </a>
                    </li>
                    <li>
                        <a href="import_products.php">
                            <i class="fas fa-chevron-right"></i>
                            Bulk Import Products
                        </a>
                    </li>
                    <li>
                        <a href="edit_product.php">
                            <i class="fas fa-chevron-right"></i>
                            Edit Products
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Data Protection -->
            <div class="access-card backup">
                <div class="access-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h4 class="h5">Data Protection</h4>
                <p class="text-muted small">Backup and restore your data</p>
                <ul class="access-links">
                    <li>
                        <a href="backup_restore.php">
                            <i class="fas fa-chevron-right"></i>
                            Backup & Restore Data
                        </a>
                    </li>
                    <li>
                        <a href="export_data.php">
                            <i class="fas fa-chevron-right"></i>
                            Export to CSV
                        </a>
                    </li>
                </ul>
            </div>

            <!-- System & Reference -->
            <div class="access-card system">
                <div class="access-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <h4 class="h5">System & Reference</h4>
                <p class="text-muted small">System tools and documentation</p>
                <ul class="access-links">
                    <li>
                        <a href="FORMULAS.php">
                            <i class="fas fa-chevron-right"></i>
                            Performance Formulas
                        </a>
                    </li>
                    <li>
                        <a href="config.php">
                            <i class="fas fa-chevron-right"></i>
                            System Configuration
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Recent Activity -->
        <?php if (!empty($recent_data)): ?>
        <div class="recent-activity">
            <h4 class="h5"><i class="fas fa-clock me-2"></i>Recent Activity</h4>
            <p class="text-muted small">Latest performance records added to the system</p>
            
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Line/Shift</th>
                            <th>Leader</th>
                            <th class="text-end">MP</th>
                            <th class="text-end">Plan</th>
                            <th class="text-end">Output</th>
                            <th class="text-end">Completion</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_data as $record): ?>
                        <tr>
                            <td><?= htmlspecialchars($record['date']) ?></td>
                            <td><?= htmlspecialchars($record['line_shift']) ?></td>
                            <td><?= htmlspecialchars($record['leader']) ?></td>
                            <td class="text-end"><?= $record['mp'] ?></td>
                            <td class="text-end"><?= $record['plan'] ?></td>
                            <td class="text-end"><?= $record['total_assy_output'] ?></td>
                            <td class="text-end">
                                <?php 
                                $completion = $record['plan'] > 0 ? round(($record['total_assy_output'] / $record['plan']) * 100, 1) : 0;
                                $badge_class = $completion >= 100 ? 'bg-success' : ($completion >= 80 ? 'bg-warning' : 'bg-danger');
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= $completion ?>%</span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="view_details.php?id=<?= $record['id'] ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_data.php?id=<?= $record['id'] ?>" class="btn btn-outline-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="text-center mt-3">
                <a href="view_data.php" class="btn btn-primary">
                    <i class="fas fa-list me-2"></i>View All Records
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- System Overview -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-rocket me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="entry_form.php" class="btn btn-success btn-lg">
                                <i class="fas fa-plus-circle me-2"></i>Enter New Performance Data
                            </a>
                            <a href="dashboard.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-tachometer-alt me-2"></i>View Performance Dashboard
                            </a>
                            <a href="trend_analysis.php" class="btn btn-warning btn-lg">
                                <i class="fas fa-chart-line me-2"></i>Analyze CPH Trends
                            </a>
                            <a href="backup_restore.php" class="btn btn-danger btn-lg">
                                <i class="fas fa-database me-2"></i>Backup System Data
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>System Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 col-sm-3 mb-3">
                                <div class="fw-bold h5 text-primary"><?= count(glob('*.php')) ?></div>
                                <small class="text-muted">System Modules</small>
                            </div>
                            <div class="col-6 col-sm-3 mb-3">
                                <div class="fw-bold h5 text-success"><?= $total_performance_records ?></div>
                                <small class="text-muted">Total Records</small>
                            </div>
                            <div class="col-6 col-sm-3 mb-3">
                                <div class="fw-bold h5 text-warning"><?= $total_products ?></div>
                                <small class="text-muted">Products</small>
                            </div>
                            <div class="col-6 col-sm-3 mb-3">
                                <div class="fw-bold h5 text-info"><?= date('Y-m-d') ?></div>
                                <small class="text-muted">Current Date</small>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <small class="text-muted">
                                Performance Tracking System v1.0<br>
                                Last updated: <?= date('F j, Y') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="h6 mb-3">Performance Tracking System</h5>
                    <p class="text-light small">Operational oversight and data management solution for manufacturing performance tracking.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 small">&copy; <?php echo date('Y'); ?> Performance Tracking System. All rights reserved.</p>
                    <small class="text-light">All PHP modules accessible from this dashboard</small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>