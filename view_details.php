<?php
require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

if (!isset($_GET['id'])) {
    die("Record ID not specified");
}

$id = $_GET['id'];

// Get main performance record
$query = "SELECT * FROM daily_performance WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die("Record not found");
}

// Get ASSY performance data
$assy_query = "
    SELECT ap.*, p.product_code, p.circuit, p.mhr 
    FROM assy_performance ap 
    JOIN products p ON ap.product_id = p.id 
    WHERE ap.daily_performance_id = ?
";
$assy_stmt = $db->prepare($assy_query);
$assy_stmt->execute([$id]);
$assy_data = $assy_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get Packing performance data
$packing_query = "
    SELECT pp.*, p.product_code, p.mhr 
    FROM packing_performance pp 
    JOIN products p ON pp.product_id = p.id 
    WHERE pp.daily_performance_id = ?
";
$packing_stmt = $db->prepare($packing_query);
$packing_stmt->execute([$id]);
$packing_data = $packing_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate metrics
$total_mp = $record['mp'];
$absent_rate = $total_mp > 0 ? ($record['absent'] / $total_mp) * 100 : 0;
$separation_rate = $total_mp > 0 ? ($record['separated_mp'] / $total_mp) * 100 : 0;
$effective_mp = $total_mp - $record['absent'];
$available_mp = $effective_mp - $record['separated_mp'];

// Calculate ASSY totals
$total_assy_output = 0;
$total_assy_mhr = 0;
$total_circuit_output = 0;

foreach ($assy_data as $assy) {
    $total_assy_output += $assy['assy_output'];
    $total_assy_mhr += $assy['assy_output'] * $assy['mhr'];
    $total_circuit_output += $assy['assy_output'] * $assy['circuit'];
}

// Calculate Packing totals
$total_packing_output = 0;
$total_packing_mhr = 0;

foreach ($packing_data as $packing) {
    $total_packing_output += $packing['packing_output'];
    $total_packing_mhr += $packing['packing_output'] * $packing['mhr'];
}

// Calculate efficiencies with correct Used MHR
$used_mhr = PerformanceCalculator::calculateUsedMHR(
    $record['no_ot_mp'], 
    $record['ot_mp'], 
    $record['ot_hours']
);

$assy_efficiency = PerformanceCalculator::calculateEfficiency($total_assy_mhr, $used_mhr);
$packing_efficiency = PerformanceCalculator::calculateEfficiency($total_packing_mhr, $used_mhr);
$plan_completion = PerformanceCalculator::calculatePlanCompletion($total_assy_output, $record['plan']);
$cph = PerformanceCalculator::calculateCPH($total_circuit_output, $used_mhr);

// Calculate scores using PerformanceCalculator methods
$absent_rate_score = PerformanceCalculator::calculateAbsentRateScore($absent_rate);
$separation_rate_score = PerformanceCalculator::calculateSeparationRateScore($separation_rate);
$plan_completion_score = PerformanceCalculator::calculatePlanCompletionScore($plan_completion);

// CPH Score
$max_cph_query = "SELECT MAX(total_circuit_output/NULLIF(used_mhr, 0)) as max_cph FROM (
    SELECT (SELECT COALESCE(SUM(ap.assy_output * p.circuit), 0) FROM assy_performance ap JOIN products p ON ap.product_id = p.id WHERE ap.daily_performance_id = dp.id) as total_circuit_output,
           (dp.no_ot_mp * 7.66 + dp.ot_mp * dp.ot_hours) as used_mhr
    FROM daily_performance dp
    WHERE dp.date = ?
) as calculations";
$max_cph_stmt = $db->prepare($max_cph_query);
$max_cph_stmt->execute([$record['date']]);
$max_cph_result = $max_cph_stmt->fetch(PDO::FETCH_ASSOC);
$max_cph = $max_cph_result['max_cph'] ?? $cph;

$cph_score = PerformanceCalculator::calculateCPHScore($cph, $max_cph);

// Total Score
$total_score = $absent_rate_score + $separation_rate_score + $plan_completion_score + $cph_score;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Details | <?php echo htmlspecialchars($record['line_shift']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <?php require_once "navbar.php"; ?>
    
    <div class="container py-5">
        <!-- Page Header -->
        <div class="page-header mb-5">
            <h1 class="page-title">
                <i class="fas fa-chart-bar me-3"></i>Performance Details
            </h1>
            <p class="lead text-muted">Detailed analysis for <?php echo htmlspecialchars($record['line_shift']); ?> on <?php echo $record['date']; ?></p>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between">
                    <div>
                        <a href="view_data.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-left me-2"></i>Back to All Data
                        </a>
                        <a href="edit_data.php?id=<?php echo $record['id']; ?>" class="btn btn-warning me-2">
                            <i class="fas fa-edit me-2"></i>Edit Record
                        </a>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-info me-2">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Performance Stats -->
        <div class="row mb-5">
            <div class="col-md-3 mb-4">
                <div class="stats-card">
                    <div class="stats-value"><?php echo $total_mp; ?></div>
                    <div class="stats-label">TOTAL MP</div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stats-card" style="background: var(--gradient-success);">
                    <div class="stats-value"><?php echo $effective_mp; ?></div>
                    <div class="stats-label">EFFECTIVE MP</div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stats-card" style="background: var(--gradient-warning);">
                    <div class="stats-value"><?php echo $total_assy_output; ?></div>
                    <div class="stats-label">TOTAL OUTPUT</div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="stats-card" style="background: var(--gradient-danger);">
                    <div class="stats-value"><?php echo round($total_score, 1); ?></div>
                    <div class="stats-label">TOTAL SCORE</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column - Basic Info & Scores -->
            <div class="col-lg-4 mb-4">
                <!-- Basic Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0 text-white">
                            <i class="fas fa-info-circle me-2"></i>Basic Information
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>Date:</strong><br>
                                <span class="text-primary"><?php echo $record['date']; ?></span>
                            </div>
                            <div class="col-6">
                                <strong>Line/Shift:</strong><br>
                                <span class="text-primary"><?php echo htmlspecialchars($record['line_shift']); ?></span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>Leader:</strong><br>
                                <span class="text-primary"><?php echo htmlspecialchars($record['leader']); ?></span>
                            </div>
                            <div class="col-6">
                                <strong>Plan:</strong><br>
                                <span class="text-primary"><?php echo $record['plan']; ?></span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>Used MHR:</strong><br>
                                <span class="text-primary"><?php echo round($used_mhr, 2); ?> hours</span>
                            </div>
                            <div class="col-6">
                                <strong>Standard Hours:</strong><br>
                                <span class="text-primary">7.66 hours per MP</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Scores -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0 text-white">
                            <i class="fas fa-star me-2"></i>Performance Scoring
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="score-card">
                            <div class="score-value"><?php echo round($absent_rate_score, 1); ?>/30</div>
                            <div class="score-label">Absent Rate Score</div>
                            <small class="text-muted">Rate: <?php echo round($absent_rate, 2); ?>%</small>
                        </div>
                        
                        <div class="score-card" style="border-left-color: var(--success);">
                            <div class="score-value" style="color: var(--success);"><?php echo round($separation_rate_score, 1); ?>/30</div>
                            <div class="score-label">Separation Rate Score</div>
                            <small class="text-muted">Rate: <?php echo round($separation_rate, 2); ?>%</small>
                        </div>
                        
                        <div class="score-card" style="border-left-color: var(--warning);">
                            <div class="score-value" style="color: var(--warning);"><?php echo round($plan_completion_score, 1); ?>/20</div>
                            <div class="score-label">Plan Completion Score</div>
                            <small class="text-muted">Completion: <?php echo round($plan_completion, 2); ?>%</small>
                        </div>
                        
                        <div class="score-card" style="border-left-color: var(--secondary);">
                            <div class="score-value" style="color: var(--secondary);"><?php echo round($cph_score, 1); ?>/20</div>
                            <div class="score-label">CPH Score</div>
                            <small class="text-muted">CPH: <?php echo round($cph, 2); ?></small>
                        </div>
                        
                        <div class="text-center mt-4 p-3" style="background: var(--gradient); border-radius: var(--border-radius-sm); color: white;">
                            <h3 class="mb-1"><?php echo round($total_score, 1); ?>/100</h3>
                            <strong>TOTAL SCORE</strong>
                            <div class="mt-2">
                                <span class="badge <?php echo PerformanceCalculator::getScoreClass($total_score); ?>">
                                    <?php
                                    if ($total_score >= 90) echo 'EXCELLENT';
                                    elseif ($total_score >= 80) echo 'GOOD';
                                    elseif ($total_score >= 70) echo 'AVERAGE';
                                    else echo 'NEEDS IMPROVEMENT';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Detailed Data -->
            <div class="col-lg-8">
                <!-- MP Distribution -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0 text-white">
                            <i class="fas fa-users me-2"></i>Manpower Distribution
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-3">
                                    <h5 class="text-primary"><?php echo $record['mp']; ?></h5>
                                    <small class="text-muted">Total MP</small>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-3 <?php echo $record['absent'] > 0 ? 'bg-danger text-white' : ''; ?>">
                                    <h5><?php echo $record['absent']; ?></h5>
                                    <small>Absent</small>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-3 <?php echo $record['separated_mp'] > 0 ? 'bg-warning' : ''; ?>">
                                    <h5><?php echo $record['separated_mp']; ?></h5>
                                    <small>Separated MP</small>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="border rounded p-3 bg-success text-white">
                                    <h5><?php echo $available_mp; ?></h5>
                                    <small>Available MP</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <strong>Work Time Details:</strong><br>
                                <small class="text-muted">
                                    No OT MP: <?php echo $record['no_ot_mp']; ?> | 
                                    OT MP: <?php echo $record['ot_mp']; ?> | 
                                    OT Hours: <?php echo $record['ot_hours']; ?>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <strong>Work Configuration:</strong><br>
                                <small class="text-muted">
                                    ASSY WT: <?php echo $record['assy_wt']; ?> | 
                                    QC: <?php echo $record['qc']; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ASSY Output Data -->
                <div class="card mb-4">
                    <div class="card-header bg-info">
                        <h4 class="mb-0 text-white">
                            <i class="fas fa-industry me-2"></i>ASSY Output Details
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($assy_data)): ?>
                            <div class="table-responsive">
                                <table class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>Product Code</th>
                                            <th>Output</th>
                                            <th>MHR</th>
                                            <th>Circuit</th>
                                            <th>Total MHR</th>
                                            <th>Total Circuit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assy_data as $assy): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($assy['product_code']); ?></strong>
                                                </td>
                                                <td class="fw-bold"><?php echo $assy['assy_output']; ?></td>
                                                <td><?php echo $assy['mhr']; ?></td>
                                                <td><?php echo $assy['circuit']; ?></td>
                                                <td><?php echo number_format($assy['assy_output'] * $assy['mhr'], 2); ?></td>
                                                <td><?php echo number_format($assy['assy_output'] * $assy['circuit'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-secondary fw-bold">
                                            <td>TOTAL</td>
                                            <td><?php echo $total_assy_output; ?></td>
                                            <td colspan="2"></td>
                                            <td><?php echo number_format($total_assy_mhr, 2); ?></td>
                                            <td><?php echo number_format($total_circuit_output, 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="row mt-3 text-center">
                                <div class="col-md-4">
                                    <div class="border rounded p-2">
                                        <strong class="<?php echo $assy_efficiency >= 100 ? 'efficiency-high' : ($assy_efficiency >= 80 ? 'efficiency-medium' : 'efficiency-low'); ?>">
                                            <?php echo round($assy_efficiency, 2); ?>%
                                        </strong><br>
                                        <small class="text-muted">ASSY Efficiency</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-2">
                                        <strong class="<?php echo $plan_completion >= 100 ? 'efficiency-high' : ($plan_completion >= 80 ? 'efficiency-medium' : 'efficiency-low'); ?>">
                                            <?php echo round($plan_completion, 2); ?>%
                                        </strong><br>
                                        <small class="text-muted">Plan Completion</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-2">
                                        <strong class="text-primary"><?php echo round($cph, 2); ?></strong><br>
                                        <small class="text-muted">CPH</small>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-3"></i>
                                <p>No ASSY output data found for this record.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Packing Output Data -->
                <div class="card">
                    <div class="card-header bg-success">
                        <h4 class="mb-0 text-white">
                            <i class="fas fa-box me-2"></i>Packing Output Details
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($packing_data)): ?>
                            <div class="table-responsive">
                                <table class="table table-modern">
                                    <thead>
                                        <tr>
                                            <th>Product Code</th>
                                            <th>Output</th>
                                            <th>MHR</th>
                                            <th>Total MHR</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($packing_data as $packing): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($packing['product_code']); ?></strong>
                                                </td>
                                                <td class="fw-bold"><?php echo $packing['packing_output']; ?></td>
                                                <td><?php echo $packing['mhr']; ?></td>
                                                <td><?php echo number_format($packing['packing_output'] * $packing['mhr'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-secondary fw-bold">
                                            <td>TOTAL</td>
                                            <td><?php echo $total_packing_output; ?></td>
                                            <td></td>
                                            <td><?php echo number_format($total_packing_mhr, 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="text-center mt-3">
                                <div class="border rounded p-3 d-inline-block">
                                    <strong class="<?php echo $packing_efficiency >= 100 ? 'efficiency-high' : ($packing_efficiency >= 80 ? 'efficiency-medium' : 'efficiency-low'); ?>">
                                        <?php echo round($packing_efficiency, 2); ?>%
                                    </strong><br>
                                    <small class="text-muted">Packing Efficiency</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-3"></i>
                                <p>No packing output data found for this record.</p>
                            </div>
                        <?php endif; ?>
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
    <script>
        // Add print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body { background: white !important; }
                .card { background: white !important; box-shadow: none !important; }
                .btn { display: none !important; }
                .page-header { color: black !important; }
                .stats-card { background: #f8f9fa !important; color: black !important; }
                .footer { display: none !important; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>