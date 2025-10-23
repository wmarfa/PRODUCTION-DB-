<?php
require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

// Initialize filter variables
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$line_shift_filter = $_GET['line_shift'] ?? '';

// Base query for all data with filters
$query = "
    SELECT 
        dp.*,
        (SELECT COALESCE(SUM(ap.assy_output * p.mhr), 0) 
         FROM assy_performance ap 
         JOIN products p ON ap.product_id = p.id 
         WHERE ap.daily_performance_id = dp.id) as total_assy_output_mhr,
        (SELECT COALESCE(SUM(pp.packing_output * p.mhr), 0) 
         FROM packing_performance pp 
         JOIN products p ON pp.product_id = p.id 
         WHERE pp.daily_performance_id = dp.id) as total_packing_output_mhr,
        (SELECT COALESCE(SUM(ap.assy_output), 0) 
         FROM assy_performance ap 
         WHERE ap.daily_performance_id = dp.id) as total_assy_output,
        (SELECT COALESCE(SUM(ap.assy_output * p.circuit), 0) 
         FROM assy_performance ap 
         JOIN products p ON ap.product_id = p.id 
         WHERE ap.daily_performance_id = dp.id) as total_circuit_output
    FROM daily_performance dp
    WHERE 1=1
";

$params = [];

// Apply date filters
if (!empty($date_from)) {
    $query .= " AND dp.date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND dp.date <= ?";
    $params[] = $date_to;
}

// Apply line/shift filter
if (!empty($line_shift_filter)) {
    $query .= " AND dp.line_shift = ?";
    $params[] = $line_shift_filter;
}

$query .= " ORDER BY dp.date DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique line/shift values for filter dropdown
$line_shift_query = "SELECT DISTINCT line_shift FROM daily_performance ORDER BY line_shift";
$line_shifts = $db->query($line_shift_query)->fetchAll(PDO::FETCH_COLUMN);

// Function to calculate performance metrics for a row
function calculatePerformanceMetrics(&$row) {
    // Calculate Used MHR with correct formula
    $used_mhr = PerformanceCalculator::calculateUsedMHR($row['no_ot_mp'], $row['ot_mp'], $row['ot_hours']);
    $total_output_mhr = $row['total_assy_output_mhr'] + $row['total_packing_output_mhr'];

    $row['assy_efficiency'] = round(PerformanceCalculator::calculateEfficiency($row['total_assy_output_mhr'], $used_mhr), 2);
    $row['packing_efficiency'] = round(PerformanceCalculator::calculateEfficiency($row['total_packing_output_mhr'], $used_mhr), 2);
    $row['plan_completion'] = round(PerformanceCalculator::calculatePlanCompletion($row['total_assy_output'], $row['plan']), 2);
    $row['cph'] = round(PerformanceCalculator::calculateCPH($row['total_circuit_output'], $used_mhr), 2);
    
    // Calculate rates
    $row['absent_rate'] = ($row['mp'] > 0) ? round(($row['absent'] / $row['mp']) * 100, 2) : 0;
    $row['separation_rate'] = ($row['mp'] > 0) ? round(($row['separated_mp'] / $row['mp']) * 100, 2) : 0;
    
    // Use new scoring methods
    $row['absent_rate_score'] = round(PerformanceCalculator::calculateAbsentRateScore($row['absent_rate']), 1);
    $row['separation_rate_score'] = round(PerformanceCalculator::calculateSeparationRateScore($row['separation_rate']), 1);
    $row['plan_completion_score'] = round(PerformanceCalculator::calculatePlanCompletionScore($row['plan_completion']), 1);

    // CPH Score
    global $db;
    $max_cph_query = "SELECT MAX(total_circuit_output/NULLIF(used_mhr, 0)) as max_cph FROM (
        SELECT (SELECT COALESCE(SUM(ap.assy_output * p.circuit), 0) FROM assy_performance ap JOIN products p ON ap.product_id = p.id WHERE ap.daily_performance_id = dp.id) as total_circuit_output,
               (dp.no_ot_mp * 7.66 + dp.ot_mp * dp.ot_hours) as used_mhr
        FROM daily_performance dp
        WHERE dp.date = ? AND (dp.no_ot_mp * 7.66 + dp.ot_mp * dp.ot_hours) > 0
    ) as calculations WHERE used_mhr > 0 AND total_circuit_output > 0";
    
    $max_cph_stmt = $db->prepare($max_cph_query);
    $max_cph_stmt->execute([$row['date']]);
    $max_cph_result = $max_cph_stmt->fetch(PDO::FETCH_ASSOC);
    $max_cph = $max_cph_result['max_cph'] ?? 0;

    // Ensure we don't divide by zero and handle cases where max_cph is 0
    if ($max_cph > 0 && $row['cph'] > 0) {
        $row['cph_score'] = round(PerformanceCalculator::calculateCPHScore($row['cph'], $max_cph), 1);
    } else {
        $row['cph_score'] = 0;
    }

    // Total Score
    $row['total_score'] = $row['absent_rate_score'] + $row['separation_rate_score'] + $row['plan_completion_score'] + $row['cph_score'];
    
    // Performance Rating - UPDATED TO MATCH SCORE INTERPRETATION GUIDE
    if ($row['total_score'] >= 90) {
        $row['performance_rating'] = 'Excellent';
        $row['rating_color'] = '#28a745';
    } elseif ($row['total_score'] >= 80) {
        $row['performance_rating'] = 'Good';
        $row['rating_color'] = '#17a2b8';
    } else {
        $row['performance_rating'] = 'Needs Improvement';
        $row['rating_color'] = '#dc3545';
    }
    
    return $row;
}

// Process all data
$processed_data = [];
foreach ($data as $row) {
    $processed_data[] = calculatePerformanceMetrics($row);
}

// Calculate averages per line/shift for filtered data
$line_shift_averages = [];
foreach ($processed_data as $row) {
    $line_shift = $row['line_shift'];
    
    if (!isset($line_shift_averages[$line_shift])) {
        $line_shift_averages[$line_shift] = [
            'count' => 0,
            'total_score_sum' => 0,
            'plan_completion_sum' => 0,
            'cph_sum' => 0,
            'absent_rate_sum' => 0,
            'separation_rate_sum' => 0,
            'assy_efficiency_sum' => 0,
            'packing_efficiency_sum' => 0,
            'absent_rate_score_sum' => 0,
            'separation_rate_score_sum' => 0,
            'plan_completion_score_sum' => 0,
            'cph_score_sum' => 0
        ];
    }
    
    $line_shift_averages[$line_shift]['count']++;
    $line_shift_averages[$line_shift]['total_score_sum'] += $row['total_score'];
    $line_shift_averages[$line_shift]['plan_completion_sum'] += $row['plan_completion'];
    $line_shift_averages[$line_shift]['cph_sum'] += $row['cph'];
    $line_shift_averages[$line_shift]['absent_rate_sum'] += $row['absent_rate'];
    $line_shift_averages[$line_shift]['separation_rate_sum'] += $row['separation_rate'];
    $line_shift_averages[$line_shift]['assy_efficiency_sum'] += $row['assy_efficiency'];
    $line_shift_averages[$line_shift]['packing_efficiency_sum'] += $row['packing_efficiency'];
    $line_shift_averages[$line_shift]['absent_rate_score_sum'] += $row['absent_rate_score'];
    $line_shift_averages[$line_shift]['separation_rate_score_sum'] += $row['separation_rate_score'];
    $line_shift_averages[$line_shift]['plan_completion_score_sum'] += $row['plan_completion_score'];
    $line_shift_averages[$line_shift]['cph_score_sum'] += $row['cph_score'];
}

// Calculate final averages
foreach ($line_shift_averages as $line_shift => &$averages) {
    $count = $averages['count'];
    if ($count > 0) {
        $averages['avg_total_score'] = round($averages['total_score_sum'] / $count, 1);
        $averages['avg_plan_completion'] = round($averages['plan_completion_sum'] / $count, 1);
        $averages['avg_cph'] = round($averages['cph_sum'] / $count, 1);
        $averages['avg_absent_rate'] = round($averages['absent_rate_sum'] / $count, 2);
        $averages['avg_separation_rate'] = round($averages['separation_rate_sum'] / $count, 2);
        $averages['avg_assy_efficiency'] = round($averages['assy_efficiency_sum'] / $count, 2);
        $averages['avg_packing_efficiency'] = round($averages['packing_efficiency_sum'] / $count, 2);
        $averages['avg_absent_rate_score'] = round($averages['absent_rate_score_sum'] / $count, 1);
        $averages['avg_separation_rate_score'] = round($averages['separation_rate_score_sum'] / $count, 1);
        $averages['avg_plan_completion_score'] = round($averages['plan_completion_score_sum'] / $count, 1);
        $averages['avg_cph_score'] = round($averages['cph_score_sum'] / $count, 1);
    }
}
unset($averages);

// Overall Ranking (All Filtered Data)
$ranked_data = $processed_data;
usort($ranked_data, function($a, $b) {
    return $b['total_score'] <=> $a['total_score'];
});
foreach ($ranked_data as $key => &$row) {
    $row['rank'] = $key + 1;
}
unset($row);

// Daily Ranking (Last 7 days from filtered data)
$seven_days_ago = date('Y-m-d', strtotime('-7 days'));
$daily_data = array_filter($processed_data, function($row) use ($seven_days_ago) {
    return $row['date'] >= $seven_days_ago;
});
usort($daily_data, function($a, $b) {
    return $b['total_score'] <=> $a['total_score'];
});
foreach ($daily_data as $key => &$row) {
    $row['daily_rank'] = $key + 1;
}
unset($row);

// Weekly Ranking (Current and previous week from filtered data)
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$last_week_start = date('Y-m-d', strtotime('monday last week'));
$last_week_end = date('Y-m-d', strtotime('sunday last week'));

$weekly_data = array_filter($processed_data, function($row) use ($current_week_start, $last_week_start, $last_week_end) {
    return $row['date'] >= $last_week_start && $row['date'] <= $last_week_end;
});
usort($weekly_data, function($a, $b) {
    return $b['total_score'] <=> $a['total_score'];
});
foreach ($weekly_data as $key => &$row) {
    $row['weekly_rank'] = $key + 1;
}
unset($row);

// Monthly Ranking (Current month from filtered data)
$current_month_start = date('Y-m-01');
$monthly_data = array_filter($processed_data, function($row) use ($current_month_start) {
    return $row['date'] >= $current_month_start;
});
usort($monthly_data, function($a, $b) {
    return $b['total_score'] <=> $a['total_score'];
});
foreach ($monthly_data as $key => &$row) {
    $row['monthly_rank'] = $key + 1;
}
unset($row);

// Calculate overall averages safely
$total_records = count($ranked_data);
$avg_total_score = $total_records > 0 ? round(array_sum(array_column($ranked_data, 'total_score')) / $total_records, 1) : 0;
$avg_plan_completion = $total_records > 0 ? round(array_sum(array_column($ranked_data, 'plan_completion')) / $total_records, 1) : 0;
$avg_cph = $total_records > 0 ? round(array_sum(array_column($ranked_data, 'cph')) / $total_records, 1) : 0;

// Get top performers from overall ranking
$top_performers = array_slice($ranked_data, 0, min(3, count($ranked_data)));

// Calculate performance distribution - UPDATED TO MATCH NEW RANGES
$performance_distribution = [
    'Excellent' => 0,
    'Good' => 0,
    'Needs Improvement' => 0
];
foreach ($ranked_data as $row) {
    $performance_distribution[$row['performance_rating']]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Dashboard & Ranking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        :root {
            --industrial-blue: #2c3e50;
            --steel-gray: #34495e;
            --production-green: #27ae60;
            --efficiency-orange: #e67e22;
            --quality-gold: #f39c12;
            --alert-red: #e74c3c;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        .industrial-header {
            background: linear-gradient(135deg, var(--industrial-blue) 0%, var(--steel-gray) 100%);
            border-bottom: 3px solid var(--quality-gold);
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }

        .metric-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .metric-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .performance-badge {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-excellent { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .badge-good { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .badge-needs-improvement { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .rank-indicator {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            margin-right: 0.75rem;
            font-size: 0.9rem;
        }

        .rank-1 { background: linear-gradient(135deg, #ffd700, #ffed4e); color: #000; }
        .rank-2 { background: linear-gradient(135deg, #c0c0c0, #e8e8e8); color: #000; }
        .rank-3 { background: linear-gradient(135deg, #cd7f32, #b08d57); color: #000; }
        .rank-other { background: var(--steel-gray); color: white; }

        .performance-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .performance-table thead th {
            background: var(--industrial-blue);
            color: white;
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .performance-table tbody td {
            padding: 0.6rem;
            vertical-align: middle;
            border-color: #f8f9fa;
            font-size: 0.78rem;
        }

        .score-progress {
            height: 6px;
            background: #f8f9fa;
            border-radius: 3px;
            overflow: hidden;
        }

        .score-progress-bar {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .top-performer-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid var(--quality-gold);
            border-radius: 8px;
            padding: 1.25rem;
            text-align: center;
            position: relative;
            height: 100%;
        }

        .top-performer-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(135deg, var(--quality-gold), var(--efficiency-orange));
            border-radius: 10px;
            z-index: -1;
        }

        .nav-tabs.industrial-tabs {
            border-bottom: 2px solid var(--industrial-blue);
            margin-bottom: 1.5rem;
            flex-wrap: nowrap;
            overflow-x: auto;
        }

        .nav-tabs.industrial-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            color: var(--steel-gray);
            font-weight: 500;
            padding: 0.75rem 1rem;
            margin-right: 0.25rem;
            transition: all 0.3s ease;
            white-space: nowrap;
            font-size: 0.85rem;
        }

        .nav-tabs.industrial-tabs .nav-link.active {
            background: none;
            border-bottom: 3px solid var(--production-green);
            color: var(--industrial-blue);
        }

        .nav-tabs.industrial-tabs .nav-link:hover {
            border-bottom: 3px solid var(--efficiency-orange);
            color: var(--industrial-blue);
        }

        .distribution-chart {
            background: white;
            border-radius: 8px;
            padding: 1.25rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: 100%;
        }

        .distribution-item {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f8f9fa;
        }

        .industrial-icon {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.25rem;
        }

        .icon-efficiency { background: #e8f5e8; color: var(--production-green); }
        .icon-quality { background: #fff3cd; color: var(--quality-gold); }
        .icon-productivity { background: #e3f2fd; color: var(--industrial-blue); }
        .icon-workforce { background: #fce4ec; color: var(--alert-red); }

        .kpi-card {
            background: white;
            border-left: 4px solid var(--production-green);
            border-radius: 4px;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .kpi-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--industrial-blue);
        }

        .kpi-label {
            font-size: 0.75rem;
            color: var(--steel-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .period-badge {
            background: var(--industrial-blue);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .filter-section {
            background: white;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .line-average-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            height: 100%;
        }

        .line-average-header {
            background: var(--industrial-blue);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 6px 6px 0 0;
            margin: -0.75rem -0.75rem 0.75rem -0.75rem;
        }
        
        .table-compact th,
        .table-compact td {
            padding: 0.4rem;
            font-size: 0.75rem;
        }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .industrial-header {
                padding: 1rem 0;
                margin-bottom: 1rem;
            }
            
            .industrial-header h1 {
                font-size: 1.5rem;
            }
            
            .metric-card {
                padding: 1rem;
                margin-bottom: 0.75rem;
            }
            
            .metric-value {
                font-size: 1.5rem;
            }
            
            .top-performer-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .filter-section {
                padding: 1rem;
            }
            
            .nav-tabs.industrial-tabs .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }
            
            .performance-table thead th,
            .performance-table tbody td {
                padding: 0.5rem 0.3rem;
                font-size: 0.7rem;
            }
            
            .distribution-chart {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .kpi-card {
                padding: 0.5rem;
            }
            
            .kpi-value {
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .industrial-header h1 {
                font-size: 1.25rem;
            }
            
            .metric-card {
                padding: 0.75rem;
            }
            
            .metric-value {
                font-size: 1.25rem;
            }
            
            .rank-indicator {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
            
            .performance-badge {
                padding: 0.4rem 0.75rem;
                font-size: 0.75rem;
            }
            
            .nav-tabs.industrial-tabs .nav-link {
                padding: 0.4rem 0.6rem;
                font-size: 0.75rem;
            }
            
            .performance-table thead th,
            .performance-table tbody td {
                padding: 0.4rem 0.2rem;
                font-size: 0.65rem;
            }
            
            .table-responsive {
                font-size: 0.7rem;
            }
            
            .btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.875rem;
            }
            
            .form-control, .form-select {
                padding: 0.375rem 0.75rem;
                font-size: 0.875rem;
            }
        }
        
        /* Mobile-specific table improvements */
        @media (max-width: 768px) {
            .table-responsive {
                border: 1px solid #dee2e6;
                border-radius: 0.375rem;
            }
            
            .performance-table thead th {
                font-size: 0.7rem;
                padding: 0.5rem 0.3rem;
            }
            
            .performance-table tbody td {
                font-size: 0.65rem;
                padding: 0.4rem 0.2rem;
            }
        }
        
        /* Card improvements */
        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.25rem;
        }
        
        .card-header {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-weight: 600;
            padding: 1rem 1.25rem;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        /* Button improvements */
        .btn {
            border-radius: 6px;
            font-weight: 500;
        }
        
        .d-grid .btn {
            margin-bottom: 0.5rem;
        }
        
        /* Ensure proper spacing on mobile */
        .row {
            margin-bottom: 0.75rem;
        }
        
        /* Improve tab content visibility */
        .tab-content {
            min-height: 400px;
        }
        
        /* Mobile filter improvements */
        .filter-section .row {
            margin-bottom: 0;
        }
        
        .filter-section .col-md-3 {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php require_once "navbar.php"; ?>
    
    <!-- Industrial Header -->
    <div class="industrial-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="text-white mb-1">Performance Dashboard & Ranking</h1>
                    <p class="text-light mb-0 small">Comprehensive analysis of manufacturing line performance metrics</p>
                </div>
                <div class="col-md-4 text-center text-md-end">
                    <div class="text-white">
                        <div class="h4 fw-bold"><?= count($ranked_data) ?></div>
                        <small class="text-light">Filtered Records</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-4">
        <!-- Filter Section -->
        <div class="filter-section">
            <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Data</h5>
            <form method="GET" class="row g-2">
                <div class="col-md-3 col-sm-6">
                    <label for="date_from" class="form-label small">Date From</label>
                    <input type="date" class="form-control form-control-sm" id="date_from" name="date_from" value="<?= $date_from ?>">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label for="date_to" class="form-label small">Date To</label>
                    <input type="date" class="form-control form-control-sm" id="date_to" name="date_to" value="<?= $date_to ?>">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label for="line_shift" class="form-label small">Line/Shift</label>
                    <select class="form-select form-select-sm" id="line_shift" name="line_shift">
                        <option value="">All Lines/Shifts</option>
                        <?php foreach ($line_shifts as $line_shift): ?>
                            <option value="<?= $line_shift ?>" <?= $line_shift == $line_shift_filter ? 'selected' : '' ?>>
                                <?= $line_shift ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-search me-1"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Line/Shift Averages -->
        <?php if (!empty($line_shift_averages)): ?>
        <div class="card mb-3">
            <div class="card-header bg-info text-white py-2">
                <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Line/Shift Averages (Filtered Period)</h6>
            </div>
            <div class="card-body p-2">
                <div class="row g-2">
                    <?php foreach ($line_shift_averages as $line_shift => $averages): ?>
                    <div class="col-lg-4 col-md-6 col-sm-12">
                        <div class="line-average-card">
                            <div class="line-average-header py-1">
                                <h6 class="mb-0 small"><?= $line_shift ?></h6>
                                <small><?= $averages['count'] ?> records</small>
                            </div>
                            <div class="row text-center g-1">
                                <div class="col-6">
                                    <div class="kpi-value" style="color: <?= $averages['avg_total_score'] >= 90 ? '#28a745' : ($averages['avg_total_score'] >= 80 ? '#17a2b8' : '#dc3545') ?>">
                                        <?= $averages['avg_total_score'] ?>
                                    </div>
                                    <div class="kpi-label">Avg Score</div>
                                </div>
                                <div class="col-6">
                                    <div class="kpi-value"><?= $averages['avg_plan_completion'] ?>%</div>
                                    <div class="kpi-label">Plan Comp.</div>
                                </div>
                                <div class="col-6">
                                    <div class="kpi-value"><?= $averages['avg_cph'] ?></div>
                                    <div class="kpi-label">Avg CPH</div>
                                </div>
                                <div class="col-6">
                                    <div class="kpi-value"><?= $averages['avg_assy_efficiency'] ?>%</div>
                                    <div class="kpi-label">ASSY Eff.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Key Performance Indicators -->
        <div class="row mb-3 g-2">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="metric-card text-center">
                    <div class="industrial-icon icon-efficiency mx-auto mb-2">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="metric-value text-primary"><?= $avg_total_score ?></div>
                    <div class="text-muted small">Average Performance Score</div>
                    <div class="mt-2">
                        <span class="performance-badge badge-<?= strtolower(str_replace(' ', '-', $ranked_data[0]['performance_rating'] ?? 'Needs Improvement')) ?>">
                            <?= $ranked_data[0]['performance_rating'] ?? 'N/A' ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="metric-card text-center">
                    <div class="industrial-icon icon-quality mx-auto mb-2">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <div class="metric-value text-success"><?= $avg_plan_completion ?>%</div>
                    <div class="text-muted small">Average Plan Completion</div>
                    <div class="score-progress mx-auto mt-2" style="max-width: 100px;">
                        <div class="score-progress-bar bg-success" style="width: <?= $avg_plan_completion ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="metric-card text-center">
                    <div class="industrial-icon icon-productivity mx-auto mb-2">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="metric-value text-info"><?= $avg_cph ?></div>
                    <div class="text-muted small">Average CPH</div>
                    <small class="text-muted">Circuits Per Hour</small>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="metric-card text-center">
                    <div class="industrial-icon icon-workforce mx-auto mb-2">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="metric-value text-warning"><?= count($ranked_data) ?></div>
                    <div class="text-muted small">Active Production Lines</div>
                    <small class="text-muted">Across all shifts</small>
                </div>
            </div>
        </div>

        <!-- Top Performers Section -->
        <?php if (count($top_performers) > 0): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="text-dark mb-0"><i class="fas fa-trophy text-warning me-2"></i>Top Performing Lines</h5>
                    <small class="text-muted d-none d-md-block">Based on comprehensive performance scoring</small>
                </div>
                <div class="row g-2">
                    <?php foreach ($top_performers as $performer): ?>
                    <div class="col-lg-4 col-md-4 col-sm-12">
                        <div class="top-performer-card">
                            <div class="rank-indicator rank-<?= $performer['rank'] ?> mx-auto mb-2">
                                <?= $performer['rank'] ?>
                            </div>
                            <h6 class="mb-1"><?= htmlspecialchars($performer['line_shift']) ?></h6>
                            <p class="text-muted mb-1 small">Led by <?= htmlspecialchars($performer['leader']) ?></p>
                            <div class="metric-value mb-1" style="color: <?= $performer['rating_color'] ?>">
                                <?= $performer['total_score'] ?>
                            </div>
                            <span class="performance-badge badge-<?= strtolower(str_replace(' ', '-', $performer['performance_rating'])) ?>">
                                <?= $performer['performance_rating'] ?>
                            </span>
                            <div class="mt-2">
                                <small class="text-muted d-block">Plan: <?= $performer['plan_completion'] ?>%</small>
                                <small class="text-muted">CPH: <?= $performer['cph'] ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <ul class="nav nav-tabs industrial-tabs" id="performanceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overall-tab" data-bs-toggle="tab" data-bs-target="#overall" type="button" role="tab">
                    <i class="fas fa-list-ol me-1"></i>Overall
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily" type="button" role="tab">
                    <i class="fas fa-calendar-day me-1"></i>Daily
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="weekly-tab" data-bs-toggle="tab" data-bs-target="#weekly" type="button" role="tab">
                    <i class="fas fa-calendar-week me-1"></i>Weekly
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly" type="button" role="tab">
                    <i class="fas fa-calendar-alt me-1"></i>Monthly
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab">
                    <i class="fas fa-chart-bar me-1"></i>Analytics
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="metrics-tab" data-bs-toggle="tab" data-bs-target="#metrics" type="button" role="tab">
                    <i class="fas fa-calculator me-1"></i>Metrics
                </button>
            </li>
        </ul>

        <div class="tab-content" id="performanceTabsContent">
            <!-- Overall Ranking Tab -->
            <div class="tab-pane fade show active" id="overall" role="tabpanel" aria-labelledby="overall-tab">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-dark mb-0">Overall Performance Ranking</h6>
                    <span class="period-badge">Filtered Period</span>
                </div>
                <div class="performance-table">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-compact">
                            <thead>
                                <tr>
                                    <th style="width: 4%;" class="text-center">Rank</th>
                                    <th style="width: 8%;">Line/Shift</th>
                                    <th style="width: 8%;">Leader</th>
                                    <th style="width: 6%;" class="text-center">Plan</th>
                                    <th style="width: 6%;" class="text-center">Actual</th>
                                    <th style="width: 7%;" class="text-center">Comp'n Rate</th>
                                    <th style="width: 7%;" class="text-center">Compl'n Rate Score</th>
                                    <th style="width: 7%;" class="text-center">Circuit Output</th>
                                    <th style="width: 6%;" class="text-center">Used MHR</th>
                                    <th style="width: 7%;" class="text-center">Previous CPH</th>
                                    <th style="width: 6%;" class="text-center">CPH</th>
                                    <th style="width: 7%;" class="text-center">CPH Score</th>
                                    <th style="width: 5%;" class="text-center">MP</th>
                                    <th style="width: 5%;" class="text-center">Absent</th>
                                    <th style="width: 6%;" class="text-center">Absent Rate</th>
                                    <th style="width: 7%;" class="text-center">Absent Rate Score</th>
                                    <th style="width: 6%;" class="text-center">Sep. MP</th>
                                    <th style="width: 6%;" class="text-center">Sep Rate</th>
                                    <th style="width: 7%;" class="text-center">Sep Rate Score</th>
                                    <th style="width: 6%;" class="text-center">Total Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ranked_data as $row): ?>
                                <tr>
                                    <td class="text-center">
                                        <div class="rank-indicator rank-<?= $row['rank'] <= 3 ? $row['rank'] : 'other' ?> mx-auto">
                                            <?= $row['rank'] ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['line_shift']) ?></td>
                                    <td><?= htmlspecialchars($row['leader']) ?></td>
                                    <td class="text-center"><?= $row['plan'] ?></td>
                                    <td class="text-center"><?= $row['total_assy_output'] ?></td>
                                    <td class="text-center"><?= $row['plan_completion'] ?>%</td>
                                    <td class="text-center"><?= $row['plan_completion_score'] ?></td>
                                    <td class="text-center"><?= $row['total_circuit_output'] ?></td>
                                    <td class="text-center"><?= round(PerformanceCalculator::calculateUsedMHR($row['no_ot_mp'], $row['ot_mp'], $row['ot_hours']), 1) ?></td>
                                    <td class="text-center">N/A</td>
                                    <td class="text-center"><?= $row['cph'] ?></td>
                                    <td class="text-center"><?= $row['cph_score'] ?></td>
                                    <td class="text-center"><?= $row['mp'] ?></td>
                                    <td class="text-center"><?= $row['absent'] ?></td>
                                    <td class="text-center"><?= $row['absent_rate'] ?>%</td>
                                    <td class="text-center"><?= $row['absent_rate_score'] ?></td>
                                    <td class="text-center"><?= $row['separated_mp'] ?></td>
                                    <td class="text-center"><?= $row['separation_rate'] ?>%</td>
                                    <td class="text-center"><?= $row['separation_rate_score'] ?></td>
                                    <td class="text-center fw-bold" style="color: <?= $row['rating_color'] ?>">
                                        <?= $row['total_score'] ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Daily Ranking Tab -->
            <div class="tab-pane fade" id="daily" role="tabpanel" aria-labelledby="daily-tab">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-dark mb-0">Daily Performance Ranking (Last 7 Days)</h6>
                    <span class="period-badge">Daily View</span>
                </div>
                <?php if (count($daily_data) > 0): ?>
                <div class="performance-table">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-compact">
                            <thead>
                                <tr>
                                    <th style="width: 4%;" class="text-center">Rank</th>
                                    <th style="width: 8%;">Line/Shift</th>
                                    <th style="width: 8%;">Leader</th>
                                    <th style="width: 6%;" class="text-center">Plan</th>
                                    <th style="width: 6%;" class="text-center">Actual</th>
                                    <th style="width: 7%;" class="text-center">Comp'n Rate</th>
                                    <th style="width: 7%;" class="text-center">Compl'n Rate Score</th>
                                    <th style="width: 7%;" class="text-center">Circuit Output</th>
                                    <th style="width: 6%;" class="text-center">Used MHR</th>
                                    <th style="width: 7%;" class="text-center">Previous CPH</th>
                                    <th style="width: 6%;" class="text-center">CPH</th>
                                    <th style="width: 7%;" class="text-center">CPH Score</th>
                                    <th style="width: 5%;" class="text-center">MP</th>
                                    <th style="width: 5%;" class="text-center">Absent</th>
                                    <th style="width: 6%;" class="text-center">Absent Rate</th>
                                    <th style="width: 7%;" class="text-center">Absent Rate Score</th>
                                    <th style="width: 6%;" class="text-center">Sep. MP</th>
                                    <th style="width: 6%;" class="text-center">Sep Rate</th>
                                    <th style="width: 7%;" class="text-center">Sep Rate Score</th>
                                    <th style="width: 6%;" class="text-center">Total Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daily_data as $row): ?>
                                <tr>
                                    <td class="text-center">
                                        <div class="rank-indicator rank-<?= $row['daily_rank'] <= 3 ? $row['daily_rank'] : 'other' ?> mx-auto">
                                            <?= $row['daily_rank'] ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['line_shift']) ?></td>
                                    <td><?= htmlspecialchars($row['leader']) ?></td>
                                    <td class="text-center"><?= $row['plan'] ?></td>
                                    <td class="text-center"><?= $row['total_assy_output'] ?></td>
                                    <td class="text-center"><?= $row['plan_completion'] ?>%</td>
                                    <td class="text-center"><?= $row['plan_completion_score'] ?></td>
                                    <td class="text-center"><?= $row['total_circuit_output'] ?></td>
                                    <td class="text-center"><?= round(PerformanceCalculator::calculateUsedMHR($row['no_ot_mp'], $row['ot_mp'], $row['ot_hours']), 1) ?></td>
                                    <td class="text-center">N/A</td>
                                    <td class="text-center"><?= $row['cph'] ?></td>
                                    <td class="text-center"><?= $row['cph_score'] ?></td>
                                    <td class="text-center"><?= $row['mp'] ?></td>
                                    <td class="text-center"><?= $row['absent'] ?></td>
                                    <td class="text-center"><?= $row['absent_rate'] ?>%</td>
                                    <td class="text-center"><?= $row['absent_rate_score'] ?></td>
                                    <td class="text-center"><?= $row['separated_mp'] ?></td>
                                    <td class="text-center"><?= $row['separation_rate'] ?>%</td>
                                    <td class="text-center"><?= $row['separation_rate_score'] ?></td>
                                    <td class="text-center fw-bold" style="color: <?= $row['rating_color'] ?>">
                                        <?= $row['total_score'] ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info text-center py-2">
                    <i class="fas fa-info-circle me-2"></i>No daily data available for the last 7 days.
                </div>
                <?php endif; ?>
            </div>

            <!-- Weekly Ranking Tab -->
            <div class="tab-pane fade" id="weekly" role="tabpanel" aria-labelledby="weekly-tab">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-dark mb-0">Weekly Performance Ranking (Previous Week)</h6>
                    <span class="period-badge">Weekly View</span>
                </div>
                <?php if (count($weekly_data) > 0): ?>
                <div class="performance-table">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-compact">
                            <thead>
                                <tr>
                                    <th style="width: 4%;" class="text-center">Rank</th>
                                    <th style="width: 8%;">Line/Shift</th>
                                    <th style="width: 8%;">Leader</th>
                                    <th style="width: 6%;" class="text-center">Plan</th>
                                    <th style="width: 6%;" class="text-center">Actual</th>
                                    <th style="width: 7%;" class="text-center">Comp'n Rate</th>
                                    <th style="width: 7%;" class="text-center">Compl'n Rate Score</th>
                                    <th style="width: 7%;" class="text-center">Circuit Output</th>
                                    <th style="width: 6%;" class="text-center">Used MHR</th>
                                    <th style="width: 7%;" class="text-center">Previous CPH</th>
                                    <th style="width: 6%;" class="text-center">CPH</th>
                                    <th style="width: 7%;" class="text-center">CPH Score</th>
                                    <th style="width: 5%;" class="text-center">MP</th>
                                    <th style="width: 5%;" class="text-center">Absent</th>
                                    <th style="width: 6%;" class="text-center">Absent Rate</th>
                                    <th style="width: 7%;" class="text-center">Absent Rate Score</th>
                                    <th style="width: 6%;" class="text-center">Sep. MP</th>
                                    <th style="width: 6%;" class="text-center">Sep Rate</th>
                                    <th style="width: 7%;" class="text-center">Sep Rate Score</th>
                                    <th style="width: 6%;" class="text-center">Total Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($weekly_data as $row): ?>
                                <tr>
                                    <td class="text-center">
                                        <div class="rank-indicator rank-<?= $row['weekly_rank'] <= 3 ? $row['weekly_rank'] : 'other' ?> mx-auto">
                                            <?= $row['weekly_rank'] ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['line_shift']) ?></td>
                                    <td><?= htmlspecialchars($row['leader']) ?></td>
                                    <td class="text-center"><?= $row['plan'] ?></td>
                                    <td class="text-center"><?= $row['total_assy_output'] ?></td>
                                    <td class="text-center"><?= $row['plan_completion'] ?>%</td>
                                    <td class="text-center"><?= $row['plan_completion_score'] ?></td>
                                    <td class="text-center"><?= $row['total_circuit_output'] ?></td>
                                    <td class="text-center"><?= round(PerformanceCalculator::calculateUsedMHR($row['no_ot_mp'], $row['ot_mp'], $row['ot_hours']), 1) ?></td>
                                    <td class="text-center">N/A</td>
                                    <td class="text-center"><?= $row['cph'] ?></td>
                                    <td class="text-center"><?= $row['cph_score'] ?></td>
                                    <td class="text-center"><?= $row['mp'] ?></td>
                                    <td class="text-center"><?= $row['absent'] ?></td>
                                    <td class="text-center"><?= $row['absent_rate'] ?>%</td>
                                    <td class="text-center"><?= $row['absent_rate_score'] ?></td>
                                    <td class="text-center"><?= $row['separated_mp'] ?></td>
                                    <td class="text-center"><?= $row['separation_rate'] ?>%</td>
                                    <td class="text-center"><?= $row['separation_rate_score'] ?></td>
                                    <td class="text-center fw-bold" style="color: <?= $row['rating_color'] ?>">
                                        <?= $row['total_score'] ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info text-center py-2">
                    <i class="fas fa-info-circle me-2"></i>No weekly data available for the previous week.
                </div>
                <?php endif; ?>
            </div>

            <!-- Monthly Ranking Tab -->
            <div class="tab-pane fade" id="monthly" role="tabpanel" aria-labelledby="monthly-tab">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-dark mb-0">Monthly Performance Ranking (Current Month)</h6>
                    <span class="period-badge">Monthly View</span>
                </div>
                <?php if (count($monthly_data) > 0): ?>
                <div class="performance-table">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-compact">
                            <thead>
                                <tr>
                                    <th style="width: 4%;" class="text-center">Rank</th>
                                    <th style="width: 8%;">Line/Shift</th>
                                    <th style="width: 8%;">Leader</th>
                                    <th style="width: 6%;" class="text-center">Plan</th>
                                    <th style="width: 6%;" class="text-center">Actual</th>
                                    <th style="width: 7%;" class="text-center">Comp'n Rate</th>
                                    <th style="width: 7%;" class="text-center">Compl'n Rate Score</th>
                                    <th style="width: 7%;" class="text-center">Circuit Output</th>
                                    <th style="width: 6%;" class="text-center">Used MHR</th>
                                    <th style="width: 7%;" class="text-center">Previous CPH</th>
                                    <th style="width: 6%;" class="text-center">CPH</th>
                                    <th style="width: 7%;" class="text-center">CPH Score</th>
                                    <th style="width: 5%;" class="text-center">MP</th>
                                    <th style="width: 5%;" class="text-center">Absent</th>
                                    <th style="width: 6%;" class="text-center">Absent Rate</th>
                                    <th style="width: 7%;" class="text-center">Absent Rate Score</th>
                                    <th style="width: 6%;" class="text-center">Sep. MP</th>
                                    <th style="width: 6%;" class="text-center">Sep Rate</th>
                                    <th style="width: 7%;" class="text-center">Sep Rate Score</th>
                                    <th style="width: 6%;" class="text-center">Total Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_data as $row): ?>
                                <tr>
                                    <td class="text-center">
                                        <div class="rank-indicator rank-<?= $row['monthly_rank'] <= 3 ? $row['monthly_rank'] : 'other' ?> mx-auto">
                                            <?= $row['monthly_rank'] ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['line_shift']) ?></td>
                                    <td><?= htmlspecialchars($row['leader']) ?></td>
                                    <td class="text-center"><?= $row['plan'] ?></td>
                                    <td class="text-center"><?= $row['total_assy_output'] ?></td>
                                    <td class="text-center"><?= $row['plan_completion'] ?>%</td>
                                    <td class="text-center"><?= $row['plan_completion_score'] ?></td>
                                    <td class="text-center"><?= $row['total_circuit_output'] ?></td>
                                    <td class="text-center"><?= round(PerformanceCalculator::calculateUsedMHR($row['no_ot_mp'], $row['ot_mp'], $row['ot_hours']), 1) ?></td>
                                    <td class="text-center">N/A</td>
                                    <td class="text-center"><?= $row['cph'] ?></td>
                                    <td class="text-center"><?= $row['cph_score'] ?></td>
                                    <td class="text-center"><?= $row['mp'] ?></td>
                                    <td class="text-center"><?= $row['absent'] ?></td>
                                    <td class="text-center"><?= $row['absent_rate'] ?>%</td>
                                    <td class="text-center"><?= $row['absent_rate_score'] ?></td>
                                    <td class="text-center"><?= $row['separated_mp'] ?></td>
                                    <td class="text-center"><?= $row['separation_rate'] ?>%</td>
                                    <td class="text-center"><?= $row['separation_rate_score'] ?></td>
                                    <td class="text-center fw-bold" style="color: <?= $row['rating_color'] ?>">
                                        <?= $row['total_score'] ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info text-center py-2">
                    <i class="fas fa-info-circle me-2"></i>No monthly data available for the current month.
                </div>
                <?php endif; ?>
            </div>

            <!-- Performance Analytics Tab -->
            <div class="tab-pane fade" id="analytics" role="tabpanel" aria-labelledby="analytics-tab">
                <div class="row g-2">
                    <div class="col-lg-6 col-md-12">
                        <div class="distribution-chart">
                            <h6 class="mb-3">Performance Distribution</h6>
                            <?php foreach ($performance_distribution as $rating => $count): ?>
                            <div class="distribution-item">
                                <div class="d-flex align-items-center">
                                    <span class="performance-badge badge-<?= strtolower(str_replace(' ', '-', $rating)) ?> me-2">
                                        <?= $rating ?>
                                    </span>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-muted small"><?= $count ?> lines</span>
                                            <span class="fw-bold small"><?= $total_records > 0 ? round(($count / $total_records) * 100, 1) : 0 ?>%</span>
                                        </div>
                                        <div class="score-progress">
                                            <div class="score-progress-bar" style="width: <?= $total_records > 0 ? ($count / $total_records) * 100 : 0 ?>%; background-color: <?= $rating == 'Excellent' ? '#28a745' : ($rating == 'Good' ? '#17a2b8' : '#dc3545') ?>"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-12">
                        <div class="distribution-chart">
                            <h6 class="mb-3">Key Performance Metrics</h6>
                            <div class="row text-center g-1">
                                <div class="col-6">
                                    <div class="kpi-card">
                                        <div class="kpi-value"><?= $avg_plan_completion ?>%</div>
                                        <div class="kpi-label">Avg Plan Completion</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="kpi-card">
                                        <div class="kpi-value"><?= $avg_cph ?></div>
                                        <div class="kpi-label">Avg CPH</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="kpi-card">
                                        <div class="kpi-value"><?= $avg_total_score ?></div>
                                        <div class="kpi-label">Avg Total Score</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="kpi-card">
                                        <div class="kpi-value"><?= count($ranked_data) ?></div>
                                        <div class="kpi-label">Total Records</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Complete Metrics Tab -->
            <div class="tab-pane fade" id="metrics" role="tabpanel" aria-labelledby="metrics-tab">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-dark mb-0">Complete Performance Metrics</h6>
                    <span class="period-badge">All Data</span>
                </div>
                <div class="performance-table">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 table-compact">
                            <thead>
                                <tr>
                                    <th style="width: 4%;" class="text-center">Rank</th>
                                    <th style="width: 8%;">Line/Shift</th>
                                    <th style="width: 8%;">Leader</th>
                                    <th style="width: 6%;" class="text-center">Plan</th>
                                    <th style="width: 6%;" class="text-center">Actual</th>
                                    <th style="width: 7%;" class="text-center">Comp'n Rate</th>
                                    <th style="width: 7%;" class="text-center">Compl'n Rate Score</th>
                                    <th style="width: 7%;" class="text-center">Circuit Output</th>
                                    <th style="width: 6%;" class="text-center">Used MHR</th>
                                    <th style="width: 7%;" class="text-center">Previous CPH</th>
                                    <th style="width: 6%;" class="text-center">CPH</th>
                                    <th style="width: 7%;" class="text-center">CPH Score</th>
                                    <th style="width: 5%;" class="text-center">MP</th>
                                    <th style="width: 5%;" class="text-center">Absent</th>
                                    <th style="width: 6%;" class="text-center">Absent Rate</th>
                                    <th style="width: 7%;" class="text-center">Absent Rate Score</th>
                                    <th style="width: 6%;" class="text-center">Sep. MP</th>
                                    <th style="width: 6%;" class="text-center">Sep Rate</th>
                                    <th style="width: 7%;" class="text-center">Sep Rate Score</th>
                                    <th style="width: 6%;" class="text-center">Total Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($processed_data as $row): ?>
                                <tr>
                                    <td class="text-center">
                                        <div class="rank-indicator rank-<?= $row['rank'] <= 3 ? $row['rank'] : 'other' ?> mx-auto">
                                            <?= $row['rank'] ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['line_shift']) ?></td>
                                    <td><?= htmlspecialchars($row['leader']) ?></td>
                                    <td class="text-center"><?= $row['plan'] ?></td>
                                    <td class="text-center"><?= $row['total_assy_output'] ?></td>
                                    <td class="text-center"><?= $row['plan_completion'] ?>%</td>
                                    <td class="text-center"><?= $row['plan_completion_score'] ?></td>
                                    <td class="text-center"><?= $row['total_circuit_output'] ?></td>
                                    <td class="text-center"><?= round(PerformanceCalculator::calculateUsedMHR($row['no_ot_mp'], $row['ot_mp'], $row['ot_hours']), 1) ?></td>
                                    <td class="text-center">N/A</td>
                                    <td class="text-center"><?= $row['cph'] ?></td>
                                    <td class="text-center"><?= $row['cph_score'] ?></td>
                                    <td class="text-center"><?= $row['mp'] ?></td>
                                    <td class="text-center"><?= $row['absent'] ?></td>
                                    <td class="text-center"><?= $row['absent_rate'] ?>%</td>
                                    <td class="text-center"><?= $row['absent_rate_score'] ?></td>
                                    <td class="text-center"><?= $row['separated_mp'] ?></td>
                                    <td class="text-center"><?= $row['separation_rate'] ?>%</td>
                                    <td class="text-center"><?= $row['separation_rate_score'] ?></td>
                                    <td class="text-center fw-bold" style="color: <?= $row['rating_color'] ?>">
                                        <?= $row['total_score'] ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add active class to current page in navbar
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
            
            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
            
            // Improve tab navigation on mobile
            const tabLinks = document.querySelectorAll('.nav-tabs .nav-link');
            tabLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Scroll to top of tab content on mobile
                    if (window.innerWidth < 768) {
                        document.getElementById('performanceTabsContent').scrollIntoView({ 
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>