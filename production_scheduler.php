<?php
// production_scheduler.php - Automated production scheduling and planning system

require_once "config.php";

class ProductionScheduler {
    private $db;

    public function __construct() {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }

    /**
     * Generate optimized production schedule
     */
    public function generateSchedule($start_date, $days = 7, $demand_forecast = []) {
        try {
            $schedule = [];
            $current_date = new DateTime($start_date);

            // Get production lines and their capabilities
            $production_lines = $this->getProductionLineCapabilities();

            // Get historical performance data
            $historical_performance = $this->getHistoricalPerformance(30);

            // Get current workload and constraints
            $current_workload = $this->getCurrentWorkload();

            // Generate daily schedules
            for ($i = 0; $i < $days; $i++) {
                $date_str = $current_date->format('Y-m-d');
                $day_schedule = $this->generateDaySchedule(
                    $date_str,
                    $production_lines,
                    $historical_performance,
                    $current_workload,
                    $demand_forecast[$date_str] ?? []
                );

                $schedule[$date_str] = $day_schedule;
                $current_date->add(new DateInterval('P1D'));
            }

            return [
                'success' => true,
                'schedule' => $schedule,
                'summary' => $this->generateScheduleSummary($schedule),
                'recommendations' => $this->generateSchedulingRecommendations($schedule)
            ];

        } catch(Exception $e) {
            error_log("Production Scheduling Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get production line capabilities and constraints
     */
    private function getProductionLineCapabilities() {
        $query = "
            SELECT DISTINCT
                dp.line_shift,
                AVG(dp.mp) as avg_manpower,
                AVG(dp.no_ot_mp) as avg_no_ot_mp,
                AVG(dp.plan) as avg_daily_plan,
                AVG((SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id)) as avg_actual_output,
                COUNT(*) as data_points
            FROM daily_performance dp
            WHERE dp.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY dp.line_shift
            ORDER BY dp.line_shift
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enhance with calculated capabilities
        foreach ($lines as &$line) {
            $line['efficiency_rate'] = $line['avg_daily_plan'] > 0 ?
                ($line['avg_actual_output'] / $line['avg_daily_plan']) * 100 : 0;
            $line['capacity_per_shift'] = $line['avg_actual_output'] * 1.1; // 10% buffer
            $line['min_manpower'] = max($line['avg_manpower'] * 0.8, 5);
            $line['max_manpower'] = $line['avg_manpower'] * 1.2;
        }

        return $lines;
    }

    /**
     * Get historical performance data for optimization
     */
    private function getHistoricalPerformance($days = 30) {
        $query = "
            SELECT
                dp.date,
                dp.line_shift,
                dp.plan,
                (SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id) as actual_output,
                dp.mp,
                dp.absent,
                dp.no_ot_mp,
                dp.ot_mp,
                dp.ot_hours,
                DAYOFWEEK(dp.date) as day_of_week
            FROM daily_performance dp
            WHERE dp.date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            ORDER BY dp.date DESC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        $performance_data = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $day_of_week = $row['day_of_week'];
            $line_shift = $row['line_shift'];

            // Calculate performance metrics
            $used_mhr = PerformanceCalculator::calculateUsedMHR($row['no_ot_mp'], $row['ot_mp'], $row['ot_hours']);
            $efficiency = PerformanceCalculator::calculateEfficiency($row['actual_output'], $used_mhr);
            $plan_completion = PerformanceCalculator::calculatePlanCompletion($row['actual_output'], $row['plan']);
            $absenteeism_rate = ($row['mp'] > 0) ? ($row['absent'] / $row['mp']) * 100 : 0;

            if (!isset($performance_data[$day_of_week])) {
                $performance_data[$day_of_week] = [];
            }
            if (!isset($performance_data[$day_of_week][$line_shift])) {
                $performance_data[$day_of_week][$line_shift] = [
                    'samples' => 0,
                    'total_efficiency' => 0,
                    'total_completion' => 0,
                    'total_absenteeism' => 0,
                    'total_output' => 0,
                    'total_plan' => 0
                ];
            }

            $performance_data[$day_of_week][$line_shift]['samples']++;
            $performance_data[$day_of_week][$line_shift]['total_efficiency'] += $efficiency;
            $performance_data[$day_of_week][$line_shift]['total_completion'] += $plan_completion;
            $performance_data[$day_of_week][$line_shift]['total_absenteeism'] += $absenteeism_rate;
            $performance_data[$day_of_week][$line_shift]['total_output'] += $row['actual_output'];
            $performance_data[$day_of_week][$line_shift]['total_plan'] += $row['plan'];
        }

        // Calculate averages
        foreach ($performance_data as $day_of_week => &$day_data) {
            foreach ($day_data as $line_shift => &$data) {
                if ($data['samples'] > 0) {
                    $data['avg_efficiency'] = $data['total_efficiency'] / $data['samples'];
                    $data['avg_completion'] = $data['total_completion'] / $data['samples'];
                    $data['avg_absenteeism'] = $data['total_absenteeism'] / $data['samples'];
                    $data['avg_output'] = $data['total_output'] / $data['samples'];
                    $data['avg_plan'] = $data['total_plan'] / $data['samples'];
                    $data['reliability'] = $data['avg_completion'] / 100;
                }
            }
        }

        return $performance_data;
    }

    /**
     * Get current workload and constraints
     */
    private function getCurrentWorkload() {
        // Get pending maintenance
        $maintenance_query = "
            SELECT production_line,
                   SUM(CASE WHEN status = 'scheduled' AND next_maintenance <= CURDATE() THEN 1 ELSE 0 END) as pending_count,
                   SUM(estimated_duration_hours) as total_hours
            FROM maintenance_schedules
            WHERE status IN ('scheduled', 'overdue')
            GROUP BY production_line
        ";

        $stmt = $this->db->prepare($maintenance_query);
        $stmt->execute();
        $maintenance_constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get active bottlenecks
        $bottleneck_query = "
            SELECT affected_production_line as production_line,
                   COUNT(*) as bottleneck_count,
                   SUM(CASE WHEN impact_level = 'critical' THEN 1 ELSE 0 END) as critical_count
            FROM production_bottlenecks
            WHERE resolution_status IN ('pending', 'in_progress')
            GROUP BY affected_production_line
        ";

        $stmt = $this->db->prepare($bottleneck_query);
        $stmt->execute();
        $bottleneck_constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'maintenance' => $maintenance_constraints,
            'bottlenecks' => $bottleneck_constraints
        ];
    }

    /**
     * Generate schedule for a specific day
     */
    private function generateDaySchedule($date, $production_lines, $historical_performance, $current_workload, $day_demand) {
        $day_schedule = [
            'date' => $date,
            'day_of_week' => date('N', strtotime($date)),
            'shifts' => ['DS' => [], 'NS' => [], 'LS' => []],
            'total_capacity' => 0,
            'allocated_demand' => 0,
            'constraints' => []
        ];

        // Apply historical performance adjustments
        $day_of_week = date('N', strtotime($date));
        $day_performance = $historical_performance[$day_of_week] ?? [];

        foreach ($production_lines as $line) {
            $line_shift = $line['line_shift'];
            $shift_type = $this->extractShiftType($line_shift);

            // Calculate adjusted capacity based on historical performance
            $historical_factor = 1.0;
            if (isset($day_performance[$line_shift])) {
                $hist_data = $day_performance[$line_shift];
                $historical_factor = $hist_data['reliability'] * 0.8 + 0.2; // Weight historical reliability
            }

            // Apply constraints
            $constraint_factor = 1.0;
            $line_constraints = [];

            // Maintenance constraints
            foreach ($current_workload['maintenance'] as $maintenance) {
                if ($maintenance['production_line'] === $line_shift && $maintenance['pending_count'] > 0) {
                    $constraint_factor *= 0.7; // Reduce capacity by 30%
                    $line_constraints[] = "Maintenance scheduled ({$maintenance['pending_count']} items)";
                }
            }

            // Bottleneck constraints
            foreach ($current_workload['bottlenecks'] as $bottleneck) {
                if ($bottleneck['production_line'] === $line_shift) {
                    if ($bottleneck['critical_count'] > 0) {
                        $constraint_factor *= 0.5; // Reduce capacity by 50% for critical bottlenecks
                        $line_constraints[] = "Critical bottlenecks active ({$bottleneck['critical_count']})";
                    } else {
                        $constraint_factor *= 0.8; // Reduce capacity by 20% for other bottlenecks
                        $line_constraints[] = "Bottlenecks active ({$bottleneck['bottleneck_count']})";
                    }
                }
            }

            // Calculate final capacity
            $base_capacity = $line['capacity_per_shift'];
            $adjusted_capacity = $base_capacity * $historical_factor * $constraint_factor;

            $shift_schedule = [
                'line_shift' => $line_shift,
                'base_capacity' => round($base_capacity),
                'adjusted_capacity' => round($adjusted_capacity),
                'planned_output' => round($adjusted_capacity),
                'recommended_manpower' => $line['avg_manpower'],
                'efficiency_target' => min($line['efficiency_rate'] * 1.05, 100), // 5% improvement target
                'constraints' => $line_constraints,
                'priority_tasks' => $this->generatePriorityTasks($line_shift, $current_workload),
                'risk_factors' => $this->identifyRiskFactors($line_shift, $constraint_factor, $historical_factor)
            ];

            $day_schedule['shifts'][$shift_type][] = $shift_schedule;
            $day_schedule['total_capacity'] += $adjusted_capacity;
        }

        // Allocate demand to shifts
        $total_demand = array_sum($day_demand);
        $day_schedule['allocated_demand'] = min($total_demand, $day_schedule['total_capacity']);

        if ($total_demand > 0) {
            $demand_factor = min($day_schedule['total_capacity'] / $total_demand, 1.0);

            foreach ($day_schedule['shifts'] as $shift_type => &$shifts) {
                foreach ($shifts as &$shift) {
                    $shift['planned_output'] = round($shift['adjusted_capacity'] * $demand_factor);
                }
            }
        }

        return $day_schedule;
    }

    /**
     * Generate schedule summary
     */
    private function generateScheduleSummary($schedule) {
        $summary = [
            'total_days' => count($schedule),
            'total_capacity' => 0,
            'total_allocated' => 0,
            'utilization_rate' => 0,
            'constraint_count' => 0,
            'high_risk_shifts' => 0,
            'efficiency_improvements' => []
        ];

        foreach ($schedule as $day) {
            $summary['total_capacity'] += $day['total_capacity'];
            $summary['total_allocated'] += $day['allocated_demand'];

            foreach ($day['shifts'] as $shift_type => $shifts) {
                foreach ($shifts as $shift) {
                    if (!empty($shift['constraints'])) {
                        $summary['constraint_count'] += count($shift['constraints']);
                    }
                    if (!empty($shift['risk_factors'])) {
                        $summary['high_risk_shifts']++;
                    }

                    // Calculate efficiency improvement opportunities
                    if ($shift['efficiency_target'] > $shift['efficiency_rate'] * 1.02) {
                        $improvement = ($shift['efficiency_target'] - ($shift['efficiency_rate'] * 1.02));
                        $summary['efficiency_improvements'][] = [
                            'line' => $shift['line_shift'],
                            'current' => round($shift['efficiency_rate'] * 1.02, 1),
                            'target' => round($shift['efficiency_target'], 1),
                            'improvement' => round($improvement, 1)
                        ];
                    }
                }
            }
        }

        if ($summary['total_capacity'] > 0) {
            $summary['utilization_rate'] = ($summary['total_allocated'] / $summary['total_capacity']) * 100;
        }

        return $summary;
    }

    /**
     * Generate scheduling recommendations
     */
    private function generateSchedulingRecommendations($schedule) {
        $recommendations = [];

        // Check utilization rate
        $total_capacity = 0;
        $total_allocated = 0;
        $constraint_heavy_shifts = 0;
        $underutilized_lines = [];

        foreach ($schedule as $day) {
            $total_capacity += $day['total_capacity'];
            $total_allocated += $day['allocated_demand'];

            foreach ($day['shifts'] as $shift_type => $shifts) {
                foreach ($shifts as $shift) {
                    if (count($shift['constraints']) > 2) {
                        $constraint_heavy_shifts++;
                    }

                    $utilization = ($shift['planned_output'] / $shift['adjusted_capacity']) * 100;
                    if ($utilization < 60) {
                        $underutilized_lines[] = $shift['line_shift'];
                    }
                }
            }
        }

        if ($total_capacity > 0) {
            $overall_utilization = ($total_allocated / $total_capacity) * 100;

            if ($overall_utilization < 70) {
                $recommendations[] = 'Low overall utilization detected. Consider increasing production targets or optimizing manpower allocation.';
            } elseif ($overall_utilization > 95) {
                $recommendations[] = 'High utilization rate. Consider adding buffer capacity or backup plans for contingencies.';
            }
        }

        if ($constraint_heavy_shifts > 5) {
            $recommendations[] = 'Multiple shifts facing constraints. Review maintenance scheduling and bottleneck resolution priorities.';
        }

        if (count($underutilized_lines) > 0) {
            $unique_lines = array_unique($underutilized_lines);
            $recommendations[] = 'Lines with low utilization detected: ' . implode(', ', $unique_lines) . '. Consider reallocating resources.';
        }

        return $recommendations;
    }

    /**
     * Generate priority tasks for each shift
     */
    private function generatePriorityTasks($line_shift, $current_workload) {
        $tasks = [];

        // Check for pending maintenance
        foreach ($current_workload['maintenance'] as $maintenance) {
            if ($maintenance['production_line'] === $line_shift && $maintenance['pending_count'] > 0) {
                $tasks[] = [
                    'type' => 'maintenance',
                    'description' => 'Complete pending maintenance activities',
                    'priority' => 'high',
                    'estimated_time' => $maintenance['total_hours'] ?? 0
                ];
            }
        }

        // Check for active bottlenecks
        foreach ($current_workload['bottlenecks'] as $bottleneck) {
            if ($bottleneck['production_line'] === $line_shift) {
                if ($bottleneck['critical_count'] > 0) {
                    $tasks[] = [
                        'type' => 'bottleneck',
                        'description' => 'Resolve critical bottlenecks',
                        'priority' => 'critical',
                        'impact' => 'Production capacity severely reduced'
                    ];
                } else {
                    $tasks[] = [
                        'type' => 'bottleneck',
                        'description' => 'Address ongoing bottlenecks',
                        'priority' => 'high',
                        'impact' => 'Production capacity reduced'
                    ];
                }
            }
        }

        // Add routine tasks
        $tasks[] = [
            'type' => 'quality',
            'description' => 'Conduct quality checks at all checkpoints',
            'priority' => 'medium',
            'frequency' => 'per shift'
        ];

        $tasks[] = [
            'type' => 'safety',
            'description' => 'Perform safety briefings and equipment checks',
            'priority' => 'high',
            'frequency' => 'per shift'
        ];

        return $tasks;
    }

    /**
     * Identify risk factors for each shift
     */
    private function identifyRiskFactors($line_shift, $constraint_factor, $historical_factor) {
        $risk_factors = [];

        if ($constraint_factor < 0.8) {
            $risk_factors[] = [
                'type' => 'constraint',
                'description' => 'High constraint load affecting capacity',
                'severity' => $constraint_factor < 0.6 ? 'high' : 'medium'
            ];
        }

        if ($historical_factor < 0.85) {
            $risk_factors[] = [
                'type' => 'performance',
                'description' => 'Historical performance indicates reliability concerns',
                'severity' => $historical_factor < 0.75 ? 'high' : 'medium'
            ];
        }

        // Check for weekend effects
        $day_of_week = date('N');
        if ($day_of_week >= 6) { // Saturday or Sunday
            $risk_factors[] = [
                'type' => 'availability',
                'description' => 'Weekend operations may have reduced support',
                'severity' => 'medium'
            ];
        }

        return $risk_factors;
    }

    /**
     * Extract shift type from line_shift
     */
    private function extractShiftType($line_shift) {
        if (strpos($line_shift, 'DS') !== false) return 'DS';
        if (strpos($line_shift, 'NS') !== false) return 'NS';
        if (strpos($line_shift, 'LS') !== false) return 'LS';
        return 'DS'; // Default
    }

    /**
     * Save generated schedule to database
     */
    public function saveSchedule($schedule_data, $created_by) {
        try {
            $this->db->beginTransaction();

            foreach ($schedule_data['schedule'] as $date => $day_data) {
                foreach ($day_data['shifts'] as $shift_type => $shifts) {
                    foreach ($shifts as $shift) {
                        // Check if record exists
                        $check_query = "
                            SELECT id FROM production_forecasts
                            WHERE forecast_date = :date
                            AND production_line = :production_line
                            AND forecast_type = 'daily'
                        ";

                        $stmt = $this->db->prepare($check_query);
                        $stmt->execute([
                            'date' => $date,
                            'production_line' => $shift['line_shift']
                        ]);

                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($existing) {
                            // Update existing record
                            $update_query = "
                                UPDATE production_forecasts SET
                                    capacity_units = :capacity,
                                    manpower_required = :manpower,
                                    target_efficiency = :efficiency,
                                    constraints = :constraints,
                                    priority_tasks = :priority_tasks,
                                    risk_factors = :risk_factors,
                                    updated_at = CURRENT_TIMESTAMP
                                WHERE id = :id
                            ";

                            $stmt = $this->db->prepare($update_query);
                            $stmt->execute([
                                'capacity' => $shift['planned_output'],
                                'manpower' => $shift['recommended_manpower'],
                                'efficiency' => $shift['efficiency_target'],
                                'constraints' => json_encode($shift['constraints']),
                                'priority_tasks' => json_encode($shift['priority_tasks']),
                                'risk_factors' => json_encode($shift['risk_factors']),
                                'id' => $existing['id']
                            ]);
                        } else {
                            // Insert new record
                            $insert_query = "
                                INSERT INTO production_forecasts (
                                    forecast_date, production_line, forecast_type,
                                    capacity_units, manpower_required, target_efficiency,
                                    constraints, priority_tasks, risk_factors, created_by
                                ) VALUES (
                                    :date, :production_line, 'daily',
                                    :capacity, :manpower, :efficiency,
                                    :constraints, :priority_tasks, :risk_factors, :created_by
                                )
                            ";

                            $stmt = $this->db->prepare($insert_query);
                            $stmt->execute([
                                'date' => $date,
                                'production_line' => $shift['line_shift'],
                                'capacity' => $shift['planned_output'],
                                'manpower' => $shift['recommended_manpower'],
                                'efficiency' => $shift['efficiency_target'],
                                'constraints' => json_encode($shift['constraints']),
                                'priority_tasks' => json_encode($shift['priority_tasks']),
                                'risk_factors' => json_encode($shift['risk_factors']),
                                'created_by' => $created_by
                            ]);
                        }
                    }
                }
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Schedule saved successfully'];

        } catch(PDOException $e) {
            $this->db->rollback();
            error_log("Save Schedule Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get current active schedules
     */
    public function getActiveSchedules($days = 7) {
        try {
            $query = "
                SELECT
                    forecast_date,
                    production_line,
                    capacity_units,
                    manpower_required,
                    target_efficiency,
                    constraints,
                    priority_tasks,
                    risk_factors,
                    created_at,
                    created_by
                FROM production_forecasts
                WHERE forecast_date >= CURDATE()
                AND forecast_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
                AND forecast_type = 'daily'
                ORDER BY forecast_date, production_line
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();

            $schedules = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['constraints'] = json_decode($row['constraints'], true) ?: [];
                $row['priority_tasks'] = json_decode($row['priority_tasks'], true) ?: [];
                $row['risk_factors'] = json_decode($row['risk_factors'], true) ?: [];
                $schedules[] = $row;
            }

            return ['success' => true, 'schedules' => $schedules];

        } catch(PDOException $e) {
            error_log("Get Active Schedules Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

// API endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $scheduler = new ProductionScheduler();

    switch ($_POST['action']) {
        case 'generate_schedule':
            $start_date = $_POST['start_date'] ?? date('Y-m-d');
            $days = intval($_POST['days'] ?? 7);
            $demand_forecast = $_POST['demand_forecast'] ?? [];

            $result = $scheduler->generateSchedule($start_date, $days, $demand_forecast);
            echo json_encode($result);
            break;

        case 'save_schedule':
            $schedule_data = json_decode($_POST['schedule_data'], true);
            $created_by = $_POST['created_by'] ?? 'System';

            $result = $scheduler->saveSchedule($schedule_data, $created_by);
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $scheduler = new ProductionScheduler();

    switch ($_GET['action']) {
        case 'get_schedules':
            $days = intval($_GET['days'] ?? 7);
            $result = $scheduler->getActiveSchedules($days);
            echo json_encode($result);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Web interface
if (basename($_SERVER['PHP_SELF']) === 'production_scheduler.php' && !isset($_REQUEST['action'])) {
    $scheduler = new ProductionScheduler();
    $active_schedules = $scheduler->getActiveSchedules(7);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Scheduler</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        .schedule-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .capacity-bar {
            height: 24px;
            background: #e9ecef;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }

        .capacity-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
            transition: width 0.6s ease;
        }

        .constraint-tag {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: #fff3cd;
            color: #856404;
            border-radius: 1rem;
            font-size: 0.75rem;
            margin: 0.25rem;
        }

        .risk-high {
            background: #f8d7da;
            color: #721c24;
        }

        .risk-medium {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-calendar-alt me-3"></i>Production Scheduler</h1>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                            <i class="fas fa-plus me-2"></i>Generate New Schedule
                        </button>
                        <a href="enhanced_dashboard.php" class="btn btn-success">
                            <i class="fas fa-tachometer-alt me-2"></i>Command Center
                        </a>
                    </div>
                </div>

                <!-- Schedule Generation Form -->
                <div class="modal fade" id="scheduleModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Generate Production Schedule</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="scheduleForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="startDate" class="form-label">Start Date</label>
                                                <input type="date" class="form-control" id="startDate" value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="scheduleDays" class="form-label">Number of Days</label>
                                                <input type="number" class="form-control" id="scheduleDays" value="7" min="1" max="30" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="createdBy" class="form-label">Created By</label>
                                        <input type="text" class="form-control" id="createdBy" value="Production Manager" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Demand Forecast (Optional)</label>
                                        <textarea class="form-control" id="demandForecast" rows="3" placeholder="Enter demand forecast per day (JSON format or simple text)"></textarea>
                                        <small class="text-muted">Leave empty to use historical averages</small>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="generateSchedule()">Generate Schedule</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results Modal -->
                <div class="modal fade" id="resultsModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Generated Schedule Results</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div id="scheduleResults"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-success" onclick="saveSchedule()">Save Schedule</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Schedules -->
                <div class="schedule-card">
                    <h5><i class="fas fa-list me-2"></i>Active Production Schedules</h5>

                    <?php if ($active_schedules['success'] && !empty($active_schedules['schedules'])): ?>
                        <?php
                        $grouped_schedules = [];
                        foreach ($active_schedules['schedules'] as $schedule) {
                            $grouped_schedules[$schedule['forecast_date']][] = $schedule;
                        }
                        ?>

                        <?php foreach ($grouped_schedules as $date => $day_schedules): ?>
                        <div class="mb-4">
                            <h6 class="text-primary"><?= date('F j, Y', strtotime($date)) ?></h6>

                            <?php foreach ($day_schedules as $schedule): ?>
                            <div class="schedule-item border rounded p-3 mb-2">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <strong><?= htmlspecialchars($schedule['production_line']) ?></strong>
                                        <div class="text-muted small">Target: <?= number_format($schedule['target_efficiency'], 1) ?>% efficiency</div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <div class="h5 mb-1"><?= number_format($schedule['capacity_units']) ?></div>
                                            <div class="text-muted small">Planned Units</div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="text-center">
                                            <div class="h5 mb-1"><?= $schedule['manpower_required'] ?></div>
                                            <div class="text-muted small">Manpower</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <?php if (!empty($schedule['constraints'])): ?>
                                            <div class="mb-1">
                                                <small class="text-muted">Constraints:</small><br>
                                                <?php foreach (array_slice($schedule['constraints'], 0, 2) as $constraint): ?>
                                                    <span class="constraint-tag"><?= htmlspecialchars($constraint) ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($schedule['constraints']) > 2): ?>
                                                    <span class="text-muted small">+<?= count($schedule['constraints']) - 2 ?> more</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($schedule['risk_factors'])): ?>
                                            <div>
                                                <small class="text-muted">Risks:</small><br>
                                                <?php foreach ($schedule['risk_factors'] as $risk): ?>
                                                    <span class="constraint-tag risk-<?= $risk['severity'] ?>">
                                                        <?= htmlspecialchars($risk['description']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <small class="text-muted">
                                            <?= date('M j', strtotime($schedule['created_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-calendar-times fa-3x mb-3"></i>
                            <h5>No Active Schedules</h5>
                            <p>Generate a new production schedule to get started.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentScheduleData = null;

        function generateSchedule() {
            const startDate = document.getElementById('startDate').value;
            const days = document.getElementById('scheduleDays').value;
            const createdBy = document.getElementById('createdBy').value;
            const demandForecast = document.getElementById('demandForecast').value;

            const formData = new FormData();
            formData.append('action', 'generate_schedule');
            formData.append('start_date', startDate);
            formData.append('days', days);
            formData.append('created_by', createdBy);
            formData.append('demand_forecast', demandForecast);

            fetch('production_scheduler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentScheduleData = data;
                    displayScheduleResults(data);
                    $('#scheduleModal').modal('hide');
                    $('#resultsModal').modal('show');
                } else {
                    alert('Error generating schedule: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error generating schedule');
            });
        }

        function displayScheduleResults(data) {
            let html = '';

            // Summary
            if (data.summary) {
                html += `
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4>${data.summary.total_days}</h4>
                                <p class="text-muted">Days Scheduled</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4>${Math.round(data.summary.utilization_rate)}%</h4>
                                <p class="text-muted">Utilization Rate</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4>${data.summary.constraint_count}</h4>
                                <p class="text-muted">Constraints</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h4>${data.summary.high_risk_shifts}</h4>
                                <p class="text-muted">High Risk Shifts</p>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Schedule details
            if (data.schedule) {
                html += '<div class="schedule-details">';

                Object.keys(data.schedule).forEach(date => {
                    const dayData = data.schedule[date];
                    html += `
                        <div class="mb-4">
                            <h6>${new Date(date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</h6>
                            <div class="capacity-bar mb-2">
                                <div class="capacity-fill" style="width: ${(dayData.allocated_demand / dayData.total_capacity) * 100}%"></div>
                            </div>
                            <small class="text-muted">${dayData.allocated_demand} / ${dayData.total_capacity} units allocated</small>
                        </div>
                    `;
                });

                html += '</div>';
            }

            // Recommendations
            if (data.recommendations && data.recommendations.length > 0) {
                html += `
                    <div class="alert alert-info">
                        <h6><i class="fas fa-lightbulb me-2"></i>Recommendations</h6>
                        <ul class="mb-0">
                            ${data.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }

            document.getElementById('scheduleResults').innerHTML = html;
        }

        function saveSchedule() {
            if (!currentScheduleData) return;

            const formData = new FormData();
            formData.append('action', 'save_schedule');
            formData.append('schedule_data', JSON.stringify(currentScheduleData));
            formData.append('created_by', document.getElementById('createdBy').value);

            fetch('production_scheduler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Schedule saved successfully!');
                    location.reload();
                } else {
                    alert('Error saving schedule: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving schedule');
            });
        }
    </script>
</body>
</html>
<?php
}
?>