<?php
session_start();
require_once 'assets.php';
require_once 'config_simple.php';

// Initialize database connection
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    // Connection will be handled by config_simple.php error page
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in
$loggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userRole = $loggedIn ? $_SESSION['role'] : 'guest';
$userName = $loggedIn ? $_SESSION['username'] : 'Guest';

// Get system statistics for welcome page
try {
    // Production lines count
    $stmt = $pdo->query("SELECT COUNT(*) as total_lines FROM production_lines");
    $totalLines = $stmt->fetch()['total_lines'];

    // Today's production
    $stmt = $pdo->query("SELECT SUM(actual_output) as today_output FROM daily_performance WHERE date = CURDATE()");
    $todayOutput = $stmt->fetch()['today_output'] ?? 0;

    // Active alerts
    $stmt = $pdo->query("SELECT COUNT(*) as active_alerts FROM production_alerts WHERE status = 'active'");
    $activeAlerts = $stmt->fetch()['active_alerts'];

    // System uptime (simulated)
    $systemUptime = 99.8;

} catch (PDOException $e) {
    $totalLines = 0;
    $todayOutput = 0;
    $activeAlerts = 0;
    $systemUptime = 0;
}

$assets = new AssetsManager();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Management System - Main Portal</title>
    <?php echo $assets->getInlineCSS(); ?>
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
            margin-bottom: 3rem;
        }

        .module-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: 100%;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .module-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .stats-card {
            background: linear-gradient(45deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .feature-list li:before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 0.5rem;
        }

        .role-badge {
            background: #17a2b8;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
        }

        .alert-indicator {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .coming-soon {
            opacity: 0.7;
            position: relative;
        }

        .coming-soon::after {
            content: "Coming Soon";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-15deg);
            background: rgba(255, 193, 7, 0.9);
            color: #000;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: bold;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-industry"></i>
                Production Manager
            </a>

            <div class="navbar-nav ms-auto">
                <?php if ($loggedIn): ?>
                    <span class="navbar-text me-3">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($userName); ?>
                        <span class="role-badge ms-2"><?php echo htmlspecialchars(ucfirst($userRole)); ?></span>
                    </span>
                    <a class="nav-link" href="user_management_offline.php?action=logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a class="nav-link" href="user_management_offline.php">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-4">
                <i class="fas fa-cogs"></i>
                Production Management System
            </h1>
            <p class="lead mb-4">
                Comprehensive Offline Production Control for Manufacturing Excellence
            </p>
            <div class="row justify-content-center">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card text-center">
                        <h3><?php echo $totalLines; ?></h3>
                        <small>Production Lines</small>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card text-center">
                        <h3><?php echo number_format($todayOutput); ?></h3>
                        <small>Today's Output</small>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card text-center">
                        <h3><?php echo $activeAlerts; ?></h3>
                        <small>Active Alerts</small>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card text-center">
                        <h3><?php echo $systemUptime; ?>%</h3>
                        <small>System Uptime</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container mb-5">
        <div class="row">
            <!-- Core Production Modules -->
            <div class="col-lg-8">
                <h2 class="mb-4">
                    <i class="fas fa-cogs text-primary"></i>
                    Core Production Modules
                </h2>

                <div class="row">
                    <!-- Production Dashboard -->
                    <div class="col-md-6 mb-4">
                        <div class="card module-card">
                            <div class="card-body text-center">
                                <div class="module-icon text-primary">
                                    <i class="fas fa-tachometer-alt"></i>
                                </div>
                                <h5 class="card-title">Production Dashboard</h5>
                                <p class="card-text">Real-time monitoring of all production lines with efficiency metrics and OEE tracking.</p>
                                <ul class="feature-list text-start">
                                    <li>Live production data</li>
                                    <li>Auto-refresh every 30 seconds</li>
                                    <li>Efficiency calculations</li>
                                    <li>16+ line monitoring</li>
                                </ul>
                                <a href="enhanced_dashboard_offline.php" class="btn btn-primary">
                                    <i class="fas fa-chart-line"></i> Open Dashboard
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Quality Assurance -->
                    <div class="col-md-6 mb-4">
                        <div class="card module-card">
                            <div class="card-body text-center">
                                <div class="module-icon text-success">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <h5 class="card-title">Quality Assurance</h5>
                                <p class="card-text">Statistical Process Control, defect tracking, and quality metrics management.</p>
                                <ul class="feature-list text-start">
                                    <li>SPC charts & analysis</li>
                                    <li>Western Electric rules</li>
                                    <li>Defect tracking</li>
                                    <li>Capability analysis</li>
                                </ul>
                                <?php if (in_array($userRole, ['operator', 'supervisor', 'manager', 'admin'])): ?>
                                    <a href="quality_assurance_offline.php" class="btn btn-success">
                                        <i class="fas fa-search"></i> Quality Control
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>
                                        <i class="fas fa-lock"></i> Restricted Access
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Maintenance Manager -->
                    <div class="col-md-6 mb-4">
                        <div class="card module-card">
                            <div class="card-body text-center">
                                <div class="module-icon text-warning">
                                    <i class="fas fa-tools"></i>
                                </div>
                                <h5 class="card-title">Maintenance Manager</h5>
                                <p class="card-text">Preventive and corrective maintenance scheduling with resource allocation.</p>
                                <ul class="feature-list text-start">
                                    <li>Work order management</li>
                                    <li>Resource allocation</li>
                                    <li>MTBF tracking</li>
                                    <li>Maintenance history</li>
                                </ul>
                                <?php if (in_array($userRole, ['manager', 'admin'])): ?>
                                    <a href="maintenance_manager_offline.php" class="btn btn-warning">
                                        <i class="fas fa-wrench"></i> Maintenance
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>
                                        <i class="fas fa-lock"></i> Manager Access Required
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Reports -->
                    <div class="col-md-6 mb-4">
                        <div class="card module-card">
                            <div class="card-body text-center">
                                <div class="module-icon text-info">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <h5 class="card-title">Advanced Reports</h5>
                                <p class="card-text">Comprehensive reporting with multi-format export and statistical analysis.</p>
                                <ul class="feature-list text-start">
                                    <li>Production analytics</li>
                                    <li>Multi-format export</li>
                                    <li>Custom report builder</li>
                                    <li>Statistical analysis</li>
                                </ul>
                                <?php if (in_array($userRole, ['manager', 'executive', 'admin'])): ?>
                                    <a href="advanced_reports_offline.php" class="btn btn-info">
                                        <i class="fas fa-file-export"></i> Generate Reports
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>
                                        <i class="fas fa-lock"></i> Manager Access Required
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & Tools -->
            <div class="col-lg-4">
                <h2 class="mb-4">
                    <i class="fas fa-rocket text-secondary"></i>
                    Quick Actions
                </h2>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-bolt"></i>
                            System Tools
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if (in_array($userRole, ['admin'])): ?>
                                <a href="system_diagnostics_offline.php" class="btn btn-outline-primary">
                                    <i class="fas fa-stethoscope"></i> System Diagnostics
                                </a>
                                <a href="data_export_import_offline.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-database"></i> Data Management
                                </a>
                            <?php endif; ?>

                            <a href="mobile_production_monitor.php" class="btn btn-outline-success">
                                <i class="fas fa-mobile-alt"></i> Mobile Monitor
                            </a>

                            <a href="notifications_center_offline.php" class="btn btn-outline-warning">
                                <i class="fas fa-bell"></i>
                                Notifications
                                <?php if ($activeAlerts > 0): ?>
                                    <span class="alert-indicator"><?php echo $activeAlerts; ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-heartbeat"></i>
                            System Status
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Database Connection</small>
                            <div class="progress" style="height: 8px;">
                                <?php if ($pdo): ?>
                                    <div class="progress-bar bg-success" style="width: 100%"></div>
                                <?php else: ?>
                                    <div class="progress-bar bg-danger" style="width: 0%"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">System Performance</small>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-info" style="width: 95%"></div>
                            </div>
                        </div>
                        <div>
                            <small class="text-muted">Storage Usage</small>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-warning" style="width: 45%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Analytics Section -->
        <div class="row mt-5">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-brain text-purple"></i>
                    Advanced Analytics & AI
                </h2>
            </div>

            <!-- Predictive Analytics -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card module-card">
                    <div class="card-body text-center">
                        <div class="module-icon text-purple">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h5 class="card-title">Predictive Analytics</h5>
                        <p class="card-text">AI-powered production forecasting with multiple algorithms.</p>
                        <ul class="feature-list text-start">
                            <li>Linear regression</li>
                            <li>Exponential smoothing</li>
                            <li>Seasonal analysis</li>
                            <li>Ensemble predictions</li>
                        </ul>
                        <?php if (in_array($userRole, ['manager', 'executive', 'admin'])): ?>
                            <a href="predictive_analytics_ai_offline.php" class="btn btn-purple" style="background: #6f42c1; color: white;">
                                <i class="fas fa-chart-line"></i> AI Analytics
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-lock"></i> Manager Access
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Digital Twin -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card module-card">
                    <div class="card-body text-center">
                        <div class="module-icon text-danger">
                            <i class="fas fa-clone"></i>
                        </div>
                        <h5 class="card-title">Digital Twin</h5>
                        <p class="card-text">Virtual production simulation with genetic algorithm optimization.</p>
                        <ul class="feature-list text-start">
                            <li>Scenario testing</li>
                            <li>Process optimization</li>
                            <li>Equipment simulation</li>
                            <li>Performance testing</li>
                        </ul>
                        <?php if (in_array($userRole, ['manager', 'executive', 'admin'])): ?>
                            <a href="digital_twin_simulator_offline.php" class="btn btn-danger">
                                <i class="fas fa-flask"></i> Simulation
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-lock"></i> Manager Access
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- IoT Sensors -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card module-card">
                    <div class="card-body text-center">
                        <div class="module-icon text-primary">
                            <i class="fas fa-wifi"></i>
                        </div>
                        <h5 class="card-title">IoT Sensors</h5>
                        <p class="card-text">Real-time sensor data management with anomaly detection.</p>
                        <ul class="feature-list text-start">
                            <li>Real-time monitoring</li>
                            <li>Anomaly detection</li>
                            <li>Sensor network</li>
                            <li>Data visualization</li>
                        </ul>
                        <?php if (in_array($userRole, ['supervisor', 'manager', 'executive', 'admin'])): ?>
                            <a href="iot_sensors_offline.php" class="btn btn-primary">
                                <i class="fas fa-satellite-dish"></i> Sensor Hub
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-lock"></i> Supervisor Access
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Compliance & Audit -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card module-card">
                    <div class="card-body text-center">
                        <div class="module-icon text-success">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <h5 class="card-title">Compliance</h5>
                        <p class="card-text">Audit scheduling and regulatory compliance management.</p>
                        <ul class="feature-list text-start">
                            <li>Regulatory frameworks</li>
                            <li>Audit scheduling</li>
                            <li>Compliance tracking</li>
                            <li>Documentation</li>
                        </ul>
                        <?php if (in_array($userRole, ['manager', 'executive', 'admin'])): ?>
                            <a href="compliance_audit_offline.php" class="btn btn-success">
                                <i class="fas fa-shield-alt"></i> Compliance
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-lock"></i> Manager Access
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Tools -->
        <div class="row mt-5">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="fas fa-tools text-secondary"></i>
                    Additional Tools & Utilities
                </h2>
            </div>

            <!-- Workflow Automation -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card module-card">
                    <div class="card-body text-center">
                        <div class="module-icon text-secondary">
                            <i class="fas fa-robot"></i>
                        </div>
                        <h5 class="card-title">Workflow Automation</h5>
                        <p class="card-text">Intelligent automation with configurable triggers and actions.</p>
                        <?php if (in_array($userRole, ['admin'])): ?>
                            <a href="workflow_automation_offline.php" class="btn btn-secondary">
                                <i class="fas fa-cogs"></i> Automation
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-lock"></i> Admin Only
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Scalability Optimizer -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card module-card">
                    <div class="card-body text-center">
                        <div class="module-icon text-primary">
                            <i class="fas fa-expand-arrows-alt"></i>
                        </div>
                        <h5 class="card-title">Scalability</h5>
                        <p class="card-text">Capacity planning and resource optimization.</p>
                        <?php if (in_array($userRole, ['manager', 'executive', 'admin'])): ?>
                            <a href="scalability_optimizer_offline.php" class="btn btn-primary">
                                <i class="fas fa-chart-pie"></i> Optimizer
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-lock"></i> Manager Access
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- API Endpoints -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card module-card">
                    <div class="card-body text-center">
                        <div class="module-icon text-info">
                            <i class="fas fa-plug"></i>
                        </div>
                        <h5 class="card-title">API Access</h5>
                        <p class="card-text">RESTful API for mobile and third-party integration.</p>
                        <a href="api_rest_offline.php" class="btn btn-info">
                            <i class="fas fa-code"></i> API Docs
                        </a>
                    </div>
                </div>
            </div>

            <!-- Shift Handover -->
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="card module-card">
                    <div class="card-body text-center">
                        <div class="module-icon text-warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h5 class="card-title">Shift Handover</h5>
                        <p class="card-text">24/7 shift management and handover workflows.</p>
                        <?php if (in_array($userRole, ['supervisor', 'manager', 'admin'])): ?>
                            <a href="enhanced_shift_handover.php" class="btn btn-warning">
                                <i class="fas fa-exchange-alt"></i> Handover
                            </a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>
                                <i class="fas fa-lock"></i> Supervisor Access
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h6>Production Management System v2.0</h6>
                    <p class="small mb-0">Comprehensive offline production control for LAN deployment</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="small mb-0">
                        <i class="fas fa-server"></i> System Status: <span class="text-success">Operational</span> |
                        <i class="fas fa-database"></i> Database: Connected |
                        <i class="fas fa-users"></i> Users: <?php echo $loggedIn ? '1' : '0'; ?> Online
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Animate module cards on hover
        document.querySelectorAll('.module-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Auto-refresh stats every 60 seconds
        setInterval(() => {
            location.reload();
        }, 60000);

        // Show system notifications
        <?php if ($activeAlerts > 0): ?>
        if (confirm('You have <?php echo $activeAlerts; ?> active alerts. Would you like to view them now?')) {
            window.location.href = 'notifications_center_offline.php';
        }
        <?php endif; ?>
    </script>
</body>
</html>