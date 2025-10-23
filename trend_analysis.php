<?php
require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

// Initialize filter variables
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$line_shift_filter = $_GET['line_shift'] ?? '';

// Get all unique line/shifts
$line_shift_query = "SELECT DISTINCT line_shift FROM daily_performance ORDER BY line_shift";
$line_shifts = $db->query($line_shift_query)->fetchAll(PDO::FETCH_COLUMN);

// Base query for CPH data
$query = "
    SELECT 
        dp.date,
        dp.line_shift,
        dp.leader,
        (SELECT COALESCE(SUM(ap.assy_output * p.circuit), 0) 
         FROM assy_performance ap 
         JOIN products p ON ap.product_id = p.id 
         WHERE ap.daily_performance_id = dp.id) as total_circuit_output,
        (dp.no_ot_mp * 7.66 + dp.ot_mp * dp.ot_hours) as used_mhr,
        dp.mp,
        dp.absent,
        dp.plan,
        (SELECT COALESCE(SUM(ap.assy_output), 0) 
         FROM assy_performance ap 
         WHERE ap.daily_performance_id = dp.id) as total_assy_output
    FROM daily_performance dp
    WHERE dp.date BETWEEN ? AND ?
";

$params = [$date_from, $date_to];

// Apply line/shift filter
if (!empty($line_shift_filter)) {
    $query .= " AND dp.line_shift = ?";
    $params[] = $line_shift_filter;
}

$query .= " ORDER BY dp.date ASC, dp.line_shift ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process data for CPH calculation and organization
$processed_data = [];
$daily_totals = [];
$line_shift_stats = [];

foreach ($raw_data as $row) {
    $date = $row['date'];
    $line_shift = $row['line_shift'];
    
    // Calculate CPH
    $cph = $row['used_mhr'] > 0 ? $row['total_circuit_output'] / $row['used_mhr'] : 0;
    $cph = round($cph, 2);
    
    // Calculate plan completion
    $plan_completion = $row['plan'] > 0 ? ($row['total_assy_output'] / $row['plan']) * 100 : 0;
    $plan_completion = round($plan_completion, 1);
    
    // Store processed data
    $processed_data[$date][$line_shift] = [
        'cph' => $cph,
        'plan_completion' => $plan_completion,
        'total_circuit_output' => $row['total_circuit_output'],
        'used_mhr' => round($row['used_mhr'], 2),
        'mp' => $row['mp'],
        'absent' => $row['absent'],
        'leader' => $row['leader']
    ];
    
    // Initialize daily totals
    if (!isset($daily_totals[$date])) {
        $daily_totals[$date] = [
            'total_cph' => 0,
            'line_count' => 0,
            'total_plan_completion' => 0,
            'total_mp' => 0,
            'total_absent' => 0
        ];
    }
    
    // Update daily totals
    $daily_totals[$date]['total_cph'] += $cph;
    $daily_totals[$date]['line_count']++;
    $daily_totals[$date]['total_plan_completion'] += $plan_completion;
    $daily_totals[$date]['total_mp'] += $row['mp'];
    $daily_totals[$date]['total_absent'] += $row['absent'];
    
    // Initialize line shift stats
    if (!isset($line_shift_stats[$line_shift])) {
        $line_shift_stats[$line_shift] = [
            'total_cph' => 0,
            'record_count' => 0,
            'max_cph' => 0,
            'min_cph' => PHP_FLOAT_MAX,
            'total_plan_completion' => 0,
            'total_mp' => 0,
            'total_absent' => 0
        ];
    }
    
    // Update line shift stats
    $line_shift_stats[$line_shift]['total_cph'] += $cph;
    $line_shift_stats[$line_shift]['record_count']++;
    $line_shift_stats[$line_shift]['max_cph'] = max($line_shift_stats[$line_shift]['max_cph'], $cph);
    $line_shift_stats[$line_shift]['min_cph'] = min($line_shift_stats[$line_shift]['min_cph'], $cph);
    $line_shift_stats[$line_shift]['total_plan_completion'] += $plan_completion;
    $line_shift_stats[$line_shift]['total_mp'] += $row['mp'];
    $line_shift_stats[$line_shift]['total_absent'] += $row['absent'];
}

// Calculate averages
foreach ($daily_totals as $date => &$totals) {
    $totals['avg_cph'] = $totals['line_count'] > 0 ? round($totals['total_cph'] / $totals['line_count'], 2) : 0;
    $totals['avg_plan_completion'] = $totals['line_count'] > 0 ? round($totals['total_plan_completion'] / $totals['line_count'], 1) : 0;
    $totals['absent_rate'] = $totals['total_mp'] > 0 ? round(($totals['total_absent'] / $totals['total_mp']) * 100, 1) : 0;
}
unset($totals);

foreach ($line_shift_stats as $line_shift => &$stats) {
    $stats['avg_cph'] = $stats['record_count'] > 0 ? round($stats['total_cph'] / $stats['record_count'], 2) : 0;
    $stats['avg_plan_completion'] = $stats['record_count'] > 0 ? round($stats['total_plan_completion'] / $stats['record_count'], 1) : 0;
    $stats['absent_rate'] = $stats['total_mp'] > 0 ? round(($stats['total_absent'] / $stats['total_mp']) * 100, 1) : 0;
    
    // Handle case where min_cph wasn't updated (no records)
    if ($stats['min_cph'] === PHP_FLOAT_MAX) {
        $stats['min_cph'] = 0;
    }
}
unset($stats);

// Prepare data for charts
$chart_data = [];
$dates = array_keys($processed_data);
sort($dates);

foreach ($line_shifts as $line_shift) {
    $chart_data[$line_shift] = [
        'label' => $line_shift,
        'data' => [],
        'borderColor' => getLineColor($line_shift),
        'backgroundColor' => getLineColor($line_shift, 0.1),
        'borderWidth' => 2,
        'fill' => false
    ];
    
    foreach ($dates as $date) {
        $cph = isset($processed_data[$date][$line_shift]) ? $processed_data[$date][$line_shift]['cph'] : null;
        $chart_data[$line_shift]['data'][] = $cph;
    }
}

// Function to assign consistent colors to lines
function getLineColor($line_shift, $alpha = 1) {
    $colors = [
        'rgba(54, 162, 235, ' . $alpha . ')',  // Blue
        'rgba(255, 99, 132, ' . $alpha . ')',  // Red
        'rgba(75, 192, 192, ' . $alpha . ')',  // Green
        'rgba(255, 159, 64, ' . $alpha . ')',  // Orange
        'rgba(153, 102, 255, ' . $alpha . ')', // Purple
        'rgba(255, 205, 86, ' . $alpha . ')',  // Yellow
        'rgba(201, 203, 207, ' . $alpha . ')', // Gray
        'rgba(255, 99, 71, ' . $alpha . ')',   // Tomato
        'rgba(46, 139, 87, ' . $alpha . ')',   // Sea Green
        'rgba(106, 90, 205, ' . $alpha . ')'   // Slate Blue
    ];
    
    $hash = crc32($line_shift);
    return $colors[$hash % count($colors)];
}

// Handle Export
if (isset($_GET['export'])) {
    $filename = "cph_trend_analysis_" . date('Y-m-d_H-i-s') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write header
    $header = ['Date'];
    foreach ($line_shifts as $line_shift) {
        $header[] = $line_shift . ' CPH';
        $header[] = $line_shift . ' Plan %';
    }
    $header[] = 'Daily Avg CPH';
    $header[] = 'Daily Avg Plan %';
    
    fputcsv($output, $header);
    
    // Write data
    foreach ($dates as $date) {
        $row = [$date];
        foreach ($line_shifts as $line_shift) {
            if (isset($processed_data[$date][$line_shift])) {
                $row[] = $processed_data[$date][$line_shift]['cph'];
                $row[] = $processed_data[$date][$line_shift]['plan_completion'];
            } else {
                $row[] = '';
                $row[] = '';
            }
        }
        $row[] = $daily_totals[$date]['avg_cph'] ?? '';
        $row[] = $daily_totals[$date]['avg_plan_completion'] ?? '';
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPH Trend Analysis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="styles.css" rel="stylesheet">
    <style>
        .trend-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .trend-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px 8px 0 0;
        }
        .cph-table th {
            background: #f8f9fa;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        .cph-value {
            font-weight: 600;
            text-align: center;
        }
        .cph-high { background: #d4edda; color: #155724; }
        .cph-medium { background: #fff3cd; color: #856404; }
        .cph-low { background: #f8d7da; color: #721c24; }
        .stat-card {
            background: white;
            border-left: 4px solid #007bff;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 2rem;
        }
        .line-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .line-color {
            width: 20px;
            height: 20px;
            border-radius: 3px;
            display: inline-block;
            margin-right: 5px;
        }
        .trend-indicator {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
        .trend-up { background: #d4edda; color: #155724; }
        .trend-down { background: #f8d7da; color: #721c24; }
        .trend-stable { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <?php require_once "navbar.php"; ?>
    
    <div class="container-fluid mt-5 mb-5" style="max-width: 1800px;">
        <!-- Page Header -->
        <div class="trend-card">
            <div class="trend-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2"><i class="fas fa-chart-line me-3"></i>CPH TREND ANALYSIS</h1>
                        <p class="lead mb-0">Daily Circuits Per Hour Performance Tracking by Production Line</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="text-white">
                            <div class="fs-4 fw-bold"><?= count($dates) ?></div>
                            <small>Days Analyzed</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Navigation -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <div>
                                <a href="index.php" class="btn btn-primary me-2">
                                    <i class="fas fa-home me-2"></i>Back to Home
                                </a>
                                <a href="dashboard.php" class="btn btn-info me-2">
                                    <i class="fas fa-tachometer-alt me-2"></i>Performance Dashboard
                                </a>
                            </div>
                            <div>
                                <a href="?<?= http_build_query($_GET) ?>&export=1" class="btn btn-success">
                                    <i class="fas fa-download me-2"></i>Export CPH Data
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Analysis Filters</h5>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label for="date_from" class="form-label">Date From</label>
                                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $date_from ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="date_to" class="form-label">Date To</label>
                                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $date_to ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="line_shift" class="form-label">Line/Shift (Optional)</label>
                                        <select class="form-select" id="line_shift" name="line_shift">
                                            <option value="">All Lines/Shifts</option>
                                            <?php foreach ($line_shifts as $line_shift): ?>
                                                <option value="<?= $line_shift ?>" <?= $line_shift == $line_shift_filter ? 'selected' : '' ?>>
                                                    <?= $line_shift ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-chart-line me-1"></i> Update Analysis
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line Statistics -->
                <?php if (!empty($line_shift_stats)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Line Performance Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($line_shift_stats as $line_shift => $stats): ?>
                                    <div class="col-md-4 col-lg-3 mb-3">
                                        <div class="stat-card">
                                            <h6 class="text-primary"><?= $line_shift ?></h6>
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <div class="fw-bold fs-5"><?= $stats['avg_cph'] ?></div>
                                                    <small class="text-muted">Avg CPH</small>
                                                </div>
                                                <div class="col-6">
                                                    <div class="fw-bold fs-5"><?= $stats['max_cph'] ?></div>
                                                    <small class="text-muted">Max CPH</small>
                                                </div>
                                            </div>
                                            <div class="row text-center mt-2">
                                                <div class="col-6">
                                                    <div class="fw-bold"><?= $stats['record_count'] ?></div>
                                                    <small class="text-muted">Records</small>
                                                </div>
                                                <div class="col-6">
                                                    <div class="fw-bold"><?= $stats['avg_plan_completion'] ?>%</div>
                                                    <small class="text-muted">Plan Comp.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- CPH Trend Chart -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>CPH Trend Over Time</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="cphTrendChart"></canvas>
                                </div>
                                <div class="line-legend" id="chartLegend"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily CPH Performance Table -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-table me-2"></i>Daily CPH Performance by Line</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height: 600px;">
                                    <table class="table table-sm table-striped table-hover mb-0 cph-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 100px; position: sticky; left: 0; background: #f8f9fa; z-index: 10;">Date</th>
                                                <?php foreach ($line_shifts as $line_shift): ?>
                                                    <th class="text-center" style="min-width: 120px;">
                                                        <?= $line_shift ?>
                                                        <br>
                                                        <small class="text-muted">CPH / Plan %</small>
                                                    </th>
                                                <?php endforeach; ?>
                                                <th class="text-center" style="min-width: 120px;">
                                                    Daily Average
                                                    <br>
                                                    <small class="text-muted">CPH / Plan %</small>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dates as $date): ?>
                                            <tr>
                                                <td style="position: sticky; left: 0; background: white; z-index: 5;">
                                                    <strong><?= $date ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= date('D', strtotime($date)) ?>
                                                    </small>
                                                </td>
                                                <?php 
                                                $daily_cph_values = [];
                                                foreach ($line_shifts as $line_shift): 
                                                    $data = $processed_data[$date][$line_shift] ?? null;
                                                    $cph = $data ? $data['cph'] : '-';
                                                    $plan_completion = $data ? $data['plan_completion'] : '-';
                                                    
                                                    if ($data) {
                                                        $daily_cph_values[] = $cph;
                                                    }
                                                    
                                                    // Determine CPH class
                                                    $cph_class = '';
                                                    if ($cph !== '-') {
                                                        if ($cph >= 50) $cph_class = 'cph-high';
                                                        elseif ($cph >= 30) $cph_class = 'cph-medium';
                                                        else $cph_class = 'cph-low';
                                                    }
                                                ?>
                                                <td class="text-center">
                                                    <?php if ($data): ?>
                                                        <div class="cph-value <?= $cph_class ?> p-1 rounded">
                                                            <?= $cph ?>
                                                        </div>
                                                        <div class="text-muted small">
                                                            <?= $plan_completion ?>%
                                                        </div>
                                                        <?php if ($data['leader']): ?>
                                                            <div class="text-muted very-small" title="Leader">
                                                                <i class="fas fa-user"></i> <?= $data['leader'] ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endforeach; ?>
                                                <td class="text-center bg-light">
                                                    <?php if (!empty($daily_cph_values)): 
                                                        $avg_cph = round(array_sum($daily_cph_values) / count($daily_cph_values), 2);
                                                        $avg_plan = $daily_totals[$date]['avg_plan_completion'] ?? '-';
                                                    ?>
                                                        <div class="fw-bold text-primary p-1 rounded">
                                                            <?= $avg_cph ?>
                                                        </div>
                                                        <div class="text-muted small">
                                                            <?= $avg_plan ?>%
                                                        </div>
                                                        <div class="text-muted very-small">
                                                            <?= count($daily_cph_values) ?> lines
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
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
    <script>
        // CPH Trend Chart
        const chartData = {
            labels: <?= json_encode($dates) ?>,
            datasets: [
                <?php foreach ($chart_data as $line_data): ?>
                {
                    label: '<?= $line_data['label'] ?>',
                    data: <?= json_encode($line_data['data']) ?>,
                    borderColor: '<?= $line_data['borderColor'] ?>',
                    backgroundColor: '<?= $line_data['backgroundColor'] ?>',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.1
                },
                <?php endforeach; ?>
            ]
        };

        const config = {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily CPH Performance Trend by Production Line'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    },
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'CPH (Circuits Per Hour)'
                        },
                        beginAtZero: true
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'nearest'
                }
            }
        };

        // Initialize chart
        const ctx = document.getElementById('cphTrendChart').getContext('2d');
        const cphTrendChart = new Chart(ctx, config);

        // Create custom legend
        const legendContainer = document.getElementById('chartLegend');
        chartData.datasets.forEach((dataset, index) => {
            const legendItem = document.createElement('div');
            legendItem.className = 'form-check form-check-inline';
            legendItem.innerHTML = `
                <input class="form-check-input" type="checkbox" checked 
                       onchange="toggleDataset(${index})" 
                       style="background-color: ${dataset.borderColor}; border-color: ${dataset.borderColor}">
                <label class="form-check-label small">
                    <span class="line-color" style="background-color: ${dataset.borderColor}"></span>
                    ${dataset.label}
                </label>
            `;
            legendContainer.appendChild(legendItem);
        });

        // Toggle dataset visibility
        function toggleDataset(index) {
            const meta = cphTrendChart.getDatasetMeta(index);
            meta.hidden = !meta.hidden;
            cphTrendChart.update();
        }

        // Add active class to current page in navbar
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
            
            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>