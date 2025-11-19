<?php
// enhanced_dashboard.php - Advanced production command center for 16+ lines, 24/7 operation

require_once "config.php";

$database = Database::getInstance();
$db = $database->getConnection();

// Get current date and time for real-time display
$current_datetime = date('Y-m-d H:i:s');
$current_date = date('Y-m-d');
$current_shift = get_current_shift();

function get_current_shift() {
    $hour = (int)date('H');
    if ($hour >= 6 && $hour < 14) return 'DS'; // Day Shift
    if ($hour >= 14 && $hour < 22) return 'NS'; // Night Shift
    return 'LS'; // Late Shift
}

// Get active production lines with real-time status
try {
    // Get today's production data for all lines
    $production_query = "
        SELECT
            dp.line_shift,
            dp.leader,
            dp.mp,
            dp.plan,
            dp.absent,
            dp.separated_mp,
            dp.no_ot_mp,
            dp.ot_mp,
            dp.ot_hours,
            (SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id) as actual_output,
            (SELECT COALESCE(SUM(ap.assy_output * p.circuit), 0) FROM assy_performance ap JOIN products p ON ap.product_id = p.id WHERE ap.daily_performance_id = dp.id) as circuit_output,
            dp.created_at as last_update
        FROM daily_performance dp
        WHERE dp.date = :current_date
        ORDER BY dp.line_shift
    ";

    $stmt = $db->prepare($production_query);
    $stmt->execute(['current_date' => $current_date]);
    $production_lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate performance metrics for each line
    foreach ($production_lines as &$line) {
        $used_mhr = PerformanceCalculator::calculateUsedMHR($line['no_ot_mp'], $line['ot_mp'], $line['ot_hours']);
        $line['efficiency'] = PerformanceCalculator::calculateEfficiency($line['actual_output'], $used_mhr);
        $line['plan_completion'] = PerformanceCalculator::calculatePlanCompletion($line['actual_output'], $line['plan']);
        $line['cph'] = PerformanceCalculator::calculateCPH($line['circuit_output'], $used_mhr);
        $line['absent_rate'] = ($line['mp'] > 0) ? ($line['absent'] / $line['mp']) * 100 : 0;
        $line['manning_rate'] = ($line['mp'] > 0) ? (($line['mp'] - $line['absent'] - $line['separated_mp']) / $line['mp']) * 100 : 0;

        // Determine line status
        if ($line['plan_completion'] >= 95) {
            $line['status'] = 'running';
            $line['status_color'] = '#28a745';
        } elseif ($line['plan_completion'] >= 70) {
            $line['status'] = 'running';
            $line['status_color'] = '#ffc107';
        } else {
            $line['status'] = 'idle';
            $line['status_color'] = '#dc3545';
        }
    }

    // Get active alerts
    $alerts_query = "
        SELECT alert_type, alert_category, title, description, production_line,
               severity_score, created_at, status
        FROM production_alerts
        WHERE status IN ('active', 'escalated')
        ORDER BY severity_score DESC, created_at DESC
        LIMIT 10
    ";
    $active_alerts = $db->query($alerts_query)->fetchAll(PDO::FETCH_ASSOC);

    // Get today's bottlenecks
    $bottlenecks_query = "
        SELECT bottleneck_type, affected_production_line, impact_level,
               bottleneck_description, detected_date, resolution_status
        FROM production_bottlenecks
        WHERE detected_date = :current_date AND resolution_status IN ('pending', 'in_progress')
        ORDER BY
            CASE impact_level
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                ELSE 4
            END
    ";
    $stmt = $db->prepare($bottlenecks_query);
    $stmt->execute(['current_date' => $current_date]);
    $active_bottlenecks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get maintenance schedule for today
    $maintenance_query = "
        SELECT equipment_name, equipment_id, production_line, maintenance_type,
               priority_level, status, estimated_duration_hours
        FROM maintenance_schedules
        WHERE next_maintenance <= :current_date AND status IN ('scheduled', 'overdue')
        ORDER BY priority_level DESC, next_maintenance ASC
    ";
    $stmt = $db->prepare($maintenance_query);
    $stmt->execute(['current_date' => $current_date]);
    $maintenance_scheduled = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get quality issues for today
    $quality_query = "
        SELECT qc.checkpoint_name, qm.measurement_status, qm.defect_count,
               qm.inspector, qm.measurement_time, dp.line_shift
        FROM quality_measurements qm
        JOIN quality_checkpoints qc ON qm.checkpoint_id = qc.id
        LEFT JOIN daily_performance dp ON qm.daily_performance_id = dp.id
        WHERE qm.measurement_status IN ('fail', 'warning')
        AND DATE(qm.measurement_time) = :current_date
        ORDER BY qm.measurement_time DESC
        LIMIT 5
    ";
    $stmt = $db->prepare($quality_query);
    $stmt->execute(['current_date' => $current_date]);
    $quality_issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get shift handover status
    $handover_query = "
        SELECT from_shift, to_shift, from_supervisor, to_supervisor,
               handover_status, plan_completion_rate
        FROM shift_handovers
        WHERE shift_date = :current_date
        ORDER BY handover_start_time DESC
        LIMIT 3
    ";
    $stmt = $db->prepare($handover_query);
    $stmt->execute(['current_date' => $current_date]);
    $shift_handovers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate overall metrics
    $total_lines = count($production_lines);
    $running_lines = count(array_filter($production_lines, fn($line) => $line['status'] == 'running'));
    $critical_alerts = count(array_filter($active_alerts, fn($alert) => $alert['alert_type'] == 'critical'));
    $total_bottlenecks = count($active_bottlenecks);

    $overall_efficiency = $total_lines > 0 ? array_sum(array_column($production_lines, 'efficiency')) / $total_lines : 0;
    $overall_plan_completion = $total_lines > 0 ? array_sum(array_column($production_lines, 'plan_completion')) / $total_lines : 0;

} catch(PDOException $e) {
    error_log("Enhanced Dashboard Error: " . $e->getMessage());
    $production_lines = [];
    $active_alerts = [];
    $active_bottlenecks = [];
    $maintenance_scheduled = [];
    $quality_issues = [];
    $shift_handovers = [];
    $total_lines = $running_lines = $critical_alerts = $total_bottlenecks = 0;
    $overall_efficiency = $overall_plan_completion = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Production Command Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --command-blue: #0d3b66;
            --status-green: #2d6a4f;
            --warning-orange: #f77f00;
            --critical-red: #d62828;
            --bg-light: #f8f9fa;
            --border-color: #dee2e6;
            --text-muted: #6c757d;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-light);
            margin: 0;
            padding: 0;
            font-size: 0.9rem;
        }

        /* Header Styles */
        .command-header {
            background: linear-gradient(135deg, var(--command-blue) 0%, #1a5490 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .header-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }

        /* Real-time Status Bar */
        .status-bar {
            background: rgba(255,255,255,0.1);
            padding: 0.5rem;
            border-radius: 0.5rem;
            margin-top: 0.5rem;
            backdrop-filter: blur(10px);
        }

        .status-item {
            display: flex;
            align-items: center;
            margin-right: 1.5rem;
            font-size: 0.85rem;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        /* Card Styles */
        .command-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            height: 100%;
        }

        .command-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }

        .card-header-enhanced {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 2px solid var(--command-blue);
            padding: 0.75rem 1.25rem;
            margin: -1.25rem -1.25rem 1rem -1.25rem;
            border-radius: 0.75rem 0.75rem 0 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Production Line Cards */
        .line-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .line-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--status-green);
        }

        .line-card.status-warning::before {
            background: var(--warning-orange);
        }

        .line-card.status-critical::before {
            background: var(--critical-red);
        }

        .line-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(4px);
        }

        .line-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .line-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--command-blue);
            margin: 0;
        }

        .line-status {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: white;
        }

        .status-running {
            background: var(--status-green);
        }

        .status-idle {
            background: var(--warning-orange);
        }

        .status-down {
            background: var(--critical-red);
        }

        /* Metric Displays */
        .metric-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
            margin-top: 0.75rem;
        }

        .metric-item {
            text-align: center;
            padding: 0.5rem;
            background: var(--bg-light);
            border-radius: 0.5rem;
        }

        .metric-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--command-blue);
            margin-bottom: 0.25rem;
        }

        .metric-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Alert Styles */
        .alert-item {
            background: white;
            border-left: 4px solid var(--critical-red);
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 0 0.5rem 0.5rem 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .alert-item.warning {
            border-left-color: var(--warning-orange);
        }

        .alert-item.info {
            border-left-color: var(--command-blue);
        }

        .alert-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .alert-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin: 0;
        }

        .alert-time {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Progress Bars */
        .progress-enhanced {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 0.25rem 0;
        }

        .progress-bar-enhanced {
            height: 100%;
            border-radius: 4px;
            transition: width 0.6s ease;
        }

        /* Responsive Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .main-content {
            grid-column: span 2;
        }

        .sidebar {
            grid-column: span 1;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            .main-content, .sidebar {
                grid-column: span 1;
            }
        }

        @media (max-width: 768px) {
            .header-title {
                font-size: 1.2rem;
            }

            .status-bar {
                flex-wrap: wrap;
            }

            .status-item {
                margin-right: 1rem;
                margin-bottom: 0.5rem;
            }

            .metric-row {
                grid-template-columns: repeat(2, 1fr);
            }

            .line-card {
                padding: 0.75rem;
            }

            .metric-value {
                font-size: 1rem;
            }
        }

        /* Loading Animation */
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--command-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Utility Classes */
        .text-command-blue { color: var(--command-blue); }
        .bg-command-blue { background-color: var(--command-blue); }
        .border-command-blue { border-color: var(--command-blue); }
        .text-status-green { color: var(--status-green); }
        .bg-status-green { background-color: var(--status-green); }
        .text-warning-orange { color: var(--warning-orange); }
        .bg-warning-orange { background-color: var(--warning-orange); }
        .text-critical-red { color: var(--critical-red); }
        .bg-critical-red { background-color: var(--critical-red); }
    </style>
</head>
<body>
    <!-- Command Header -->
    <div class="command-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="header-title">
                        <i class="fas fa-industry me-3"></i>
                        Production Command Center
                    </h1>
                    <p class="header-subtitle">Real-time monitoring for <?= $total_lines ?> production lines • 24/7 Operations</p>
                </div>
                <div class="col-md-4">
                    <div class="status-bar d-flex align-items-center justify-content-md-end">
                        <div class="status-item">
                            <div class="status-indicator bg-status-green"></div>
                            <span><?= $running_lines ?> / <?= $total_lines ?> Active</span>
                        </div>
                        <div class="status-item">
                            <div class="status-indicator bg-warning-orange"></div>
                            <span><?= count($active_alerts) ?> Alerts</span>
                        </div>
                        <div class="status-item">
                            <div class="status-indicator bg-critical-red"></div>
                            <span><?= $critical_alerts ?> Critical</span>
                        </div>
                        <div class="status-item">
                            <i class="fas fa-clock me-1"></i>
                            <span><?= date('H:i') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard -->
    <div class="container-fluid mt-4">
        <!-- Overall Metrics -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="command-card text-center">
                    <h3 class="metric-value text-status-green"><?= number_format($overall_efficiency, 1) ?>%</h3>
                    <div class="metric-label">Overall Efficiency</div>
                    <div class="progress-enhanced mt-2">
                        <div class="progress-bar-enhanced bg-status-green" style="width: <?= min($overall_efficiency, 100) ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="command-card text-center">
                    <h3 class="metric-value text-command-blue"><?= number_format($overall_plan_completion, 1) ?>%</h3>
                    <div class="metric-label">Plan Completion</div>
                    <div class="progress-enhanced mt-2">
                        <div class="progress-bar-enhanced bg-command-blue" style="width: <?= min($overall_plan_completion, 100) ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="command-card text-center">
                    <h3 class="metric-value text-warning-orange"><?= $total_bottlenecks ?></h3>
                    <div class="metric-label">Active Bottlenecks</div>
                    <small class="text-muted">Needs attention</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="command-card text-center">
                    <h3 class="metric-value text-critical-red"><?= count($maintenance_scheduled) ?></h3>
                    <div class="metric-label">Scheduled Maintenance</div>
                    <small class="text-muted">Next 24 hours</small>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Main Content Area -->
            <div class="main-content">
                <!-- Production Lines Status -->
                <div class="command-card">
                    <div class="card-header-enhanced">
                        <span><i class="fas fa-cogs me-2"></i>Production Lines Status</span>
                        <span class="badge bg-command-blue"><?= $current_shift ?> Shift</span>
                    </div>

                    <?php if (empty($production_lines)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-info-circle fa-3x mb-3"></i>
                            <h5>No Production Data Available</h5>
                            <p>No production data recorded for today. Check back after shift start.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($production_lines as $line): ?>
                            <div class="line-card <?= $line['plan_completion'] < 70 ? 'status-critical' : ($line['plan_completion'] < 95 ? 'status-warning' : '') ?>">
                                <div class="line-header">
                                    <div>
                                        <h5 class="line-name"><?= htmlspecialchars($line['line_shift']) ?></h5>
                                        <small class="text-muted">Leader: <?= htmlspecialchars($line['leader']) ?> • Last update: <?= date('H:i', strtotime($line['last_update'])) ?></small>
                                    </div>
                                    <span class="line-status status-<?= $line['status'] ?>"><?= $line['status'] ?></span>
                                </div>

                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="metric-row">
                                            <div class="metric-item">
                                                <div class="metric-value"><?= number_format($line['plan_completion'], 1) ?>%</div>
                                                <div class="metric-label">Plan Comp.</div>
                                            </div>
                                            <div class="metric-item">
                                                <div class="metric-value"><?= number_format($line['efficiency'], 1) ?>%</div>
                                                <div class="metric-label">Efficiency</div>
                                            </div>
                                            <div class="metric-item">
                                                <div class="metric-value"><?= number_format($line['cph'], 1) ?></div>
                                                <div class="metric-label">CPH</div>
                                            </div>
                                            <div class="metric-item">
                                                <div class="metric-value"><?= $line['mp'] - $line['absent'] ?>/<?= $line['mp'] ?></div>
                                                <div class="metric-label">Manning</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted small">Progress</span>
                                            <strong class="text-command-blue"><?= $line['actual_output'] ?>/<?= $line['plan'] ?></strong>
                                        </div>
                                        <div class="progress-enhanced">
                                            <div class="progress-bar-enhanced bg-command-blue" style="width: <?= min($line['plan_completion'], 100) ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Shift Handovers -->
                <?php if (!empty($shift_handovers)): ?>
                <div class="command-card">
                    <div class="card-header-enhanced">
                        <span><i class="fas fa-exchange-alt me-2"></i>Shift Handovers</span>
                        <small class="text-muted">Recent transitions</small>
                    </div>

                    <?php foreach ($shift_handovers as $handover): ?>
                        <div class="alert-item">
                            <div class="alert-header">
                                <h6 class="alert-title">
                                    <?= htmlspecialchars($handover['from_shift']) ?> → <?= htmlspecialchars($handover['to_shift']) ?>
                                </h6>
                                <span class="badge bg-<?= $handover['handover_status'] == 'completed' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($handover['handover_status']) ?>
                                </span>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">From: <?= htmlspecialchars($handover['from_supervisor']) ?> • To: <?= htmlspecialchars($handover['to_supervisor']) ?></small>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <small class="text-muted">Completion: <?= number_format($handover['plan_completion_rate'], 1) ?>%</small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Active Alerts -->
                <div class="command-card">
                    <div class="card-header-enhanced">
                        <span><i class="fas fa-bell me-2"></i>Active Alerts</span>
                        <span class="badge bg-critical-red"><?= count($active_alerts) ?></span>
                    </div>

                    <?php if (empty($active_alerts)): ?>
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                            <p class="mb-0">All systems normal</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($active_alerts as $alert): ?>
                            <div class="alert-item <?= $alert['alert_type'] ?>">
                                <div class="alert-header">
                                    <h6 class="alert-title"><?= htmlspecialchars($alert['title']) ?></h6>
                                    <small class="alert-time"><?= date('H:i', strtotime($alert['created_at'])) ?></small>
                                </div>
                                <?php if ($alert['description']): ?>
                                    <p class="mb-1 small text-muted"><?= htmlspecialchars($alert['description']) ?></p>
                                <?php endif; ?>
                                <?php if ($alert['production_line']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-industry me-1"></i><?= htmlspecialchars($alert['production_line']) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Quality Issues -->
                <div class="command-card">
                    <div class="card-header-enhanced">
                        <span><i class="fas fa-shield-alt me-2"></i>Quality Issues</span>
                        <span class="badge bg-warning"><?= count($quality_issues) ?></span>
                    </div>

                    <?php if (empty($quality_issues)): ?>
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                            <p class="mb-0">No quality issues</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($quality_issues as $issue): ?>
                            <div class="alert-item warning">
                                <div class="alert-header">
                                    <h6 class="alert-title"><?= htmlspecialchars($issue['checkpoint_name']) ?></h6>
                                    <span class="badge bg-warning"><?= ucfirst($issue['measurement_status']) ?></span>
                                </div>
                                <div class="row">
                                    <div class="col-8">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i><?= htmlspecialchars($issue['inspector']) ?>
                                        </small>
                                    </div>
                                    <div class="col-4 text-end">
                                        <small class="text-muted">
                                            <?= $issue['defect_count'] ?> defects
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Maintenance Schedule -->
                <div class="command-card">
                    <div class="card-header-enhanced">
                        <span><i class="fas fa-tools me-2"></i>Maintenance</span>
                        <span class="badge bg-info"><?= count($maintenance_scheduled) ?></span>
                    </div>

                    <?php if (empty($maintenance_scheduled)): ?>
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                            <p class="mb-0">No scheduled maintenance</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($maintenance_scheduled as $maintenance): ?>
                            <div class="alert-item info">
                                <div class="alert-header">
                                    <h6 class="alert-title"><?= htmlspecialchars($maintenance['equipment_name']) ?></h6>
                                    <span class="badge bg-<?= $maintenance['priority_level'] == 'critical' ? 'danger' : ($maintenance['priority_level'] == 'high' ? 'warning' : 'info') ?>">
                                        <?= ucfirst($maintenance['priority_level']) ?>
                                    </span>
                                </div>
                                <div class="row">
                                    <div class="col-8">
                                        <small class="text-muted">
                                            <i class="fas fa-industry me-1"></i><?= htmlspecialchars($maintenance['production_line']) ?>
                                        </small>
                                    </div>
                                    <div class="col-4 text-end">
                                        <small class="text-muted">
                                            <?= $maintenance['estimated_duration_hours'] ?>h
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh dashboard every 30 seconds
        function refreshDashboard() {
            console.log('Refreshing dashboard data...');
            // In production, this would make an AJAX call to refresh only the data portions
            // location.reload(); // Simple refresh for now
        }

        // Set up auto-refresh
        setInterval(refreshDashboard, 30000);

        // Update time every second
        function updateTime() {
            const timeElements = document.querySelectorAll('.status-item .fa-clock + span');
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit'
            });

            timeElements.forEach(element => {
                element.textContent = timeString;
            });
        }

        setInterval(updateTime, 1000);

        // Animate metric values on page load
        document.addEventListener('DOMContentLoaded', function() {
            const metricValues = document.querySelectorAll('.metric-value');
            metricValues.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '0';
                    element.style.transform = 'translateY(20px)';
                    element.style.transition = 'all 0.6s ease';

                    setTimeout(() => {
                        element.style.opacity = '1';
                        element.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });

        // Handle alert dismissal (placeholder functionality)
        document.querySelectorAll('.alert-item').forEach(item => {
            item.style.cursor = 'pointer';
            item.addEventListener('click', function() {
                // In a real implementation, this would mark the alert as acknowledged
                this.style.opacity = '0.6';
                setTimeout(() => {
                    this.style.opacity = '1';
                }, 2000);
            });
        });

        // Line card hover effects
        document.querySelectorAll('.line-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.zIndex = '10';
            });

            card.addEventListener('mouseleave', function() {
                this.style.zIndex = '1';
            });
        });
    </script>
</body>
</html>