<?php
// alert_system.php - Intelligent automated monitoring and notifications system

require_once "config.php";

class AlertSystem {
    private $db;
    private $alert_thresholds = [
        'oee_below_target' => ['threshold' => 85.0, 'operator' => '<', 'severity' => 'warning'],
        'plan_completion_low' => ['threshold' => 90.0, 'operator' => '<', 'severity' => 'critical'],
        'absenteeism_high' => ['threshold' => 5.0, 'operator' => '>', 'severity' => 'warning'],
        'quality_defect_rate' => ['threshold' => 2.0, 'operator' => '>', 'severity' => 'critical'],
        'equipment_downtime' => ['threshold' => 15, 'operator' => '>', 'severity' => 'critical'],
        'manpower_shortage' => ['threshold' => 0.8, 'operator' => '<', 'severity' => 'warning'],
        'production_bottleneck' => ['threshold' => 20, 'operator' => '>', 'severity' => 'high'],
        'maintenance_overdue' => ['threshold' => 1, 'operator' => '>', 'severity' => 'warning']
    ];

    public function __construct() {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }

    /**
     * Main monitoring function - checks all production metrics and generates alerts
     */
    public function monitorProductionMetrics() {
        $alerts_created = 0;

        // Check production line performance
        $alerts_created += $this->checkProductionLinePerformance();

        // Check quality metrics
        $alerts_created += $this->checkQualityMetrics();

        // Check equipment status
        $alerts_created += $this->checkEquipmentStatus();

        // Check maintenance schedules
        $alerts_created += $this->checkMaintenanceSchedules();

        // Check shift handovers
        $alerts_created += $this->checkShiftHandovers();

        // Process automatic escalations
        $this->processAlertEscalations();

        return $alerts_created;
    }

    /**
     * Check production line performance against thresholds
     */
    private function checkProductionLinePerformance() {
        $alerts_created = 0;

        try {
            // Get current shift production data
            $query = "
                SELECT
                    dp.id,
                    dp.line_shift,
                    dp.leader,
                    dp.mp,
                    dp.absent,
                    dp.separated_mp,
                    dp.plan,
                    (SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id) as actual_output,
                    (SELECT COALESCE(SUM(ap.assy_output * p.circuit), 0) FROM assy_performance ap JOIN products p ON ap.product_id = p.id WHERE ap.daily_performance_id = dp.id) as circuit_output,
                    dp.no_ot_mp,
                    dp.ot_mp,
                    dp.ot_hours
                FROM daily_performance dp
                WHERE dp.date = CURDATE()
                ORDER BY dp.line_shift
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $production_lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($production_lines as $line) {
                // Calculate performance metrics
                $used_mhr = PerformanceCalculator::calculateUsedMHR($line['no_ot_mp'], $line['ot_mp'], $line['ot_hours']);
                $plan_completion = PerformanceCalculator::calculatePlanCompletion($line['actual_output'], $line['plan']);
                $efficiency = PerformanceCalculator::calculateEfficiency($line['actual_output'], $used_mhr);
                $cph = PerformanceCalculator::calculateCPH($line['circuit_output'], $used_mhr);

                // Calculate manpower metrics
                $absent_rate = ($line['mp'] > 0) ? ($line['absent'] / $line['mp']) * 100 : 0;
                $effective_manning = ($line['mp'] - $line['absent'] - $line['separated_mp']) / $line['mp'];

                // Check plan completion
                if ($plan_completion < $this->alert_thresholds['plan_completion_low']['threshold']) {
                    $alerts_created += $this->createAlert(
                        'critical',
                        'performance',
                        'Low Plan Completion',
                        "Line {$line['line_shift']} has only achieved " . number_format($plan_completion, 1) . "% of plan target",
                        $line['line_shift'],
                        null,
                        $this->alert_thresholds['plan_completion_low']['threshold'],
                        $plan_completion,
                        3
                    );
                }

                // Check absenteeism
                if ($absent_rate > $this->alert_thresholds['absenteeism_high']['threshold']) {
                    $alerts_created += $this->createAlert(
                        'warning',
                        'manpower',
                        'High Absenteeism Rate',
                        "Line {$line['line_shift']} has " . number_format($absent_rate, 1) . "% absenteeism rate",
                        $line['line_shift'],
                        null,
                        $this->alert_thresholds['absenteeism_high']['threshold'],
                        $absent_rate,
                        2
                    );
                }

                // Check manpower shortage
                if ($effective_manning < $this->alert_thresholds['manpower_shortage']['threshold']) {
                    $alerts_created += $this->createAlert(
                        'warning',
                        'manpower',
                        'Manpower Shortage',
                        "Line {$line['line_shift']} has insufficient manpower coverage",
                        $line['line_shift'],
                        null,
                        $this->alert_thresholds['manpower_shortage']['threshold'],
                        $effective_manning,
                        2
                    );
                }

                // Check for potential bottlenecks (very low efficiency)
                if ($efficiency < 30 && $line['plan'] > 0) {
                    $alerts_created += $this->createAlert(
                        'critical',
                        'performance',
                        'Production Bottleneck Detected',
                        "Line {$line['line_shift']} showing very low efficiency (" . number_format($efficiency, 1) . "%)",
                        $line['line_shift'],
                        null,
                        50.0,
                        $efficiency,
                        4
                    );
                }
            }

        } catch(PDOException $e) {
            error_log("Production Performance Check Error: " . $e->getMessage());
        }

        return $alerts_created;
    }

    /**
     * Check quality metrics and measurements
     */
    private function checkQualityMetrics() {
        $alerts_created = 0;

        try {
            // Get recent quality measurements
            $query = "
                SELECT
                    qm.id,
                    qm.checkpoint_id,
                    qc.checkpoint_name,
                    qc.production_line,
                    qm.measurement_status,
                    qm.defect_count,
                    dp.line_shift as shift_line,
                    qm.inspector
                FROM quality_measurements qm
                JOIN quality_checkpoints qc ON qm.checkpoint_id = qc.id
                LEFT JOIN daily_performance dp ON qm.daily_performance_id = dp.id
                WHERE DATE(qm.measurement_time) = CURDATE()
                AND qm.measurement_status IN ('fail', 'warning')
                ORDER BY qm.measurement_time DESC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $quality_issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($quality_issues as $issue) {
                $severity = $issue['measurement_status'] == 'fail' ? 'critical' : 'warning';
                $production_line = $issue['production_line'] ?: $issue['shift_line'];

                $alerts_created += $this->createAlert(
                    $severity,
                    'quality',
                    'Quality Issue Detected',
                    "Quality checkpoint '{$issue['checkpoint_name']}' failed with {$issue['defect_count']} defects",
                    $production_line,
                    null,
                    0,
                    $issue['defect_count'],
                    $severity == 'critical' ? 3 : 2
                );
            }

        } catch(PDOException $e) {
            error_log("Quality Metrics Check Error: " . $e->getMessage());
        }

        return $alerts_created;
    }

    /**
     * Check equipment status and utilization
     */
    private function checkEquipmentStatus() {
        $alerts_created = 0;

        try {
            // Check for equipment downtime (simplified check based on production data)
            $query = "
                SELECT line_shift, leader
                FROM daily_performance dp
                WHERE dp.date = CURDATE()
                AND dp.plan > 0
                AND (SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id) = 0
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $inactive_lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($inactive_lines as $line) {
                $alerts_created += $this->createAlert(
                    'critical',
                    'equipment',
                    'Equipment Production Failure',
                    "Line {$line['line_shift']} shows no production despite having plan",
                    $line['line_shift'],
                    null,
                    1,
                    0,
                    4
                );
            }

        } catch(PDOException $e) {
            error_log("Equipment Status Check Error: " . $e->getMessage());
        }

        return $alerts_created;
    }

    /**
     * Check maintenance schedules for overdue items
     */
    private function checkMaintenanceSchedules() {
        $alerts_created = 0;

        try {
            $query = "
                SELECT
                    id,
                    equipment_name,
                    equipment_id,
                    production_line,
                    maintenance_type,
                    priority_level,
                    next_maintenance
                FROM maintenance_schedules
                WHERE next_maintenance < CURDATE()
                AND status IN ('scheduled', 'overdue')
                ORDER BY priority_level DESC, next_maintenance ASC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $overdue_maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($overdue_maintenance as $maintenance) {
                $days_overdue = (strtotime('today') - strtotime($maintenance['next_maintenance'])) / (60 * 60 * 24);

                $alerts_created += $this->createAlert(
                    $maintenance['priority_level'] == 'critical' ? 'critical' : 'warning',
                    'equipment',
                    'Overdue Maintenance',
                    "Maintenance for {$maintenance['equipment_name']} is {$days_overdue} days overdue",
                    $maintenance['production_line'],
                    null,
                    0,
                    $days_overdue,
                    3
                );
            }

        } catch(PDOException $e) {
            error_log("Maintenance Schedule Check Error: " . $e->getMessage());
        }

        return $alerts_created;
    }

    /**
     * Check shift handovers for delays or issues
     */
    private function checkShiftHandovers() {
        $alerts_created = 0;

        try {
            // Check for incomplete shift handovers from the last shift change
            $query = "
                SELECT
                    from_shift,
                    to_shift,
                    from_supervisor,
                    to_supervisor,
                    production_line,
                    handover_status,
                    handover_start_time
                FROM shift_handovers
                WHERE shift_date = CURDATE()
                AND handover_status IN ('pending', 'in_progress')
                AND handover_start_time < DATE_SUB(NOW(), INTERVAL 2 HOUR)
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $delayed_handovers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($delayed_handovers as $handover) {
                $alerts_created += $this->createAlert(
                    'warning',
                    'schedule',
                    'Delayed Shift Handover',
                    "Shift handover from {$handover['from_shift']} to {$handover['to_shift']} is delayed",
                    $handover['production_line'],
                    null,
                    120, // 2 minutes in minutes
                    0,
                    2
                );
            }

        } catch(PDOException $e) {
            error_log("Shift Handover Check Error: " . $e->getMessage());
        }

        return $alerts_created;
    }

    /**
     * Create a new alert in the database
     */
    private function createAlert($type, $category, $title, $description, $production_line, $shift, $threshold, $current_value, $severity_score) {
        try {
            // Check if similar alert already exists and is active
            $check_query = "
                SELECT id FROM production_alerts
                WHERE title = :title
                AND production_line = :production_line
                AND status IN ('active', 'escalated')
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                LIMIT 1
            ";

            $stmt = $this->db->prepare($check_query);
            $stmt->execute([
                'title' => $title,
                'production_line' => $production_line
            ]);

            if ($stmt->fetch()) {
                return 0; // Alert already exists
            }

            // Insert new alert
            $insert_query = "
                INSERT INTO production_alerts (
                    alert_type, alert_category, title, description,
                    production_line, shift, alert_threshold, current_value,
                    severity_score, status, auto_escalate, escalation_level
                ) VALUES (
                    :alert_type, :alert_category, :title, :description,
                    :production_line, :shift, :alert_threshold, :current_value,
                    :severity_score, 'active', TRUE, 1
                )
            ";

            $stmt = $this->db->prepare($insert_query);
            $result = $stmt->execute([
                'alert_type' => $type,
                'alert_category' => $category,
                'title' => $title,
                'description' => $description,
                'production_line' => $production_line,
                'shift' => $shift,
                'alert_threshold' => $threshold,
                'current_value' => $current_value,
                'severity_score' => $severity_score
            ]);

            if ($result) {
                $alert_id = $this->db->lastInsertId();
                $this->sendNotifications($alert_id, $type, $title, $description, $production_line);
                return 1;
            }

        } catch(PDOException $e) {
            error_log("Alert Creation Error: " . $e->getMessage());
        }

        return 0;
    }

    /**
     * Process automatic escalations for unacknowledged alerts
     */
    private function processAlertEscalations() {
        try {
            // Get alerts that need escalation
            $query = "
                SELECT
                    id, alert_type, title, production_line,
                    escalation_level, created_at, auto_escalate
                FROM production_alerts
                WHERE status = 'active'
                AND auto_escalate = TRUE
                AND escalation_level < 4
                AND (
                    (alert_type = 'critical' AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)) OR
                    (alert_type = 'warning' AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)) OR
                    (alert_type = 'info' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR))
                )
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $alerts_to_escalate = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($alerts_to_escalate as $alert) {
                $new_level = $alert['escalation_level'] + 1;

                // Update alert status to escalated
                $update_query = "
                    UPDATE production_alerts
                    SET status = 'escalated',
                        escalation_level = :new_level,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ";

                $stmt = $this->db->prepare($update_query);
                $stmt->execute([
                    'new_level' => $new_level,
                    'id' => $alert['id']
                ]);

                // Send escalation notification
                $this->sendEscalationNotification($alert, $new_level);
            }

        } catch(PDOException $e) {
            error_log("Alert Escalation Error: " . $e->getMessage());
        }
    }

    /**
     * Send notifications for new alerts (placeholder for actual notification system)
     */
    private function sendNotifications($alert_id, $type, $title, $description, $production_line) {
        // In a real implementation, this would integrate with:
        // - Email notification system
        // - SMS gateway
        // - Push notifications
        // - WebSocket real-time updates
        // - Third-party monitoring services

        $notification_data = [
            'alert_id' => $alert_id,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'production_line' => $production_line,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Log notification for debugging
        error_log("ALERT NOTIFICATION: " . json_encode($notification_data));

        // For demo purposes, we'll just count notifications
        // In production, implement actual notification channels
        return true;
    }

    /**
     * Send escalation notifications
     */
    private function sendEscalationNotification($alert, $escalation_level) {
        $escalation_channels = [
            2 => ['email'],          // Level 2: Email to supervisor
            3 => ['email', 'sms'],   // Level 3: Email + SMS to manager
            4 => ['email', 'sms', 'call'] // Level 4: Email + SMS + Phone call to executive
        ];

        $channels = $escalation_channels[$escalation_level] ?? ['email'];

        $notification_data = [
            'alert_id' => $alert['id'],
            'escalation_level' => $escalation_level,
            'channels' => $channels,
            'alert_details' => $alert,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        // Log escalation for debugging
        error_log("ALERT ESCALATION: " . json_encode($notification_data));

        return true;
    }

    /**
     * Get active alerts for display
     */
    public function getActiveAlerts($limit = 50) {
        try {
            $query = "
                SELECT
                    id, alert_type, alert_category, title, description,
                    production_line, shift, severity_score, status,
                    escalation_level, acknowledged_by, acknowledged_at,
                    created_at
                FROM production_alerts
                WHERE status IN ('active', 'escalated')
                ORDER BY
                    CASE alert_type
                        WHEN 'critical' THEN 1
                        WHEN 'warning' THEN 2
                        ELSE 3
                    END,
                    severity_score DESC,
                    created_at DESC
                LIMIT :limit
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch(PDOException $e) {
            error_log("Get Active Alerts Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Acknowledge an alert
     */
    public function acknowledgeAlert($alert_id, $user_name) {
        try {
            $query = "
                UPDATE production_alerts
                SET status = 'acknowledged',
                    acknowledged_by = :user_name,
                    acknowledged_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :alert_id
                    AND status IN ('active', 'escalated')
            ";

            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'alert_id' => $alert_id,
                'user_name' => $user_name
            ]);

        } catch(PDOException $e) {
            error_log("Acknowledge Alert Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolve an alert
     */
    public function resolveAlert($alert_id, $user_name, $resolution_notes = '') {
        try {
            $query = "
                UPDATE production_alerts
                SET status = 'resolved',
                    resolved_by = :user_name,
                    resolved_at = CURRENT_TIMESTAMP,
                    resolution_notes = :resolution_notes,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :alert_id
            ";

            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'alert_id' => $alert_id,
                'user_name' => $user_name,
                'resolution_notes' => $resolution_notes
            ]);

        } catch(PDOException $e) {
            error_log("Resolve Alert Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get alert statistics
     */
    public function getAlertStatistics($days = 7) {
        try {
            $query = "
                SELECT
                    alert_type,
                    alert_category,
                    status,
                    COUNT(*) as count,
                    AVG(escalation_level) as avg_escalation
                FROM production_alerts
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY alert_type, alert_category, status
                ORDER BY count DESC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch(PDOException $e) {
            error_log("Get Alert Statistics Error: " . $e->getMessage());
            return [];
        }
    }
}

// Web interface for alert management
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $alert_system = new AlertSystem();

    switch ($_GET['action']) {
        case 'monitor':
            $alerts_created = $alert_system->monitorProductionMetrics();
            echo json_encode([
                'success' => true,
                'alerts_created' => $alerts_created,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'get_alerts':
            $alerts = $alert_system->getActiveAlerts($_GET['limit'] ?? 20);
            echo json_encode([
                'success' => true,
                'alerts' => $alerts,
                'count' => count($alerts)
            ]);
            break;

        case 'acknowledge':
            if (isset($_GET['alert_id']) && isset($_GET['user_name'])) {
                $success = $alert_system->acknowledgeAlert($_GET['alert_id'], $_GET['user_name']);
                echo json_encode(['success' => $success]);
            }
            break;

        case 'resolve':
            if (isset($_GET['alert_id']) && isset($_GET['user_name'])) {
                $success = $alert_system->resolveAlert(
                    $_GET['alert_id'],
                    $_GET['user_name'],
                    $_GET['notes'] ?? ''
                );
                echo json_encode(['success' => $success]);
            }
            break;

        case 'statistics':
            $stats = $alert_system->getAlertStatistics($_GET['days'] ?? 7);
            echo json_encode([
                'success' => true,
                'statistics' => $stats
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// If accessing directly, show alert management interface
if (basename($_SERVER['PHP_SELF']) === 'alert_system.php' && !isset($_GET['action'])) {
    $alert_system = new AlertSystem();
    $active_alerts = $alert_system->getActiveAlerts();
    $alert_stats = $alert_system->getAlertStatistics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Alert System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        .alert-card {
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        .alert-critical { border-left-color: #dc3545; }
        .alert-warning { border-left-color: #ffc107; }
        .alert-info { border-left-color: #0dcaf0; }

        .severity-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .status-active { background-color: #dc3545; animation: pulse 2s infinite; }
        .status-acknowledged { background-color: #ffc107; }
        .status-resolved { background-color: #198754; }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-bell me-3"></i>Production Alert System</h1>
                    <div>
                        <button class="btn btn-primary" onclick="monitorAlerts()">
                            <i class="fas fa-sync me-2"></i>Monitor Now
                        </button>
                        <a href="enhanced_dashboard.php" class="btn btn-success">
                            <i class="fas fa-tachometer-alt me-2"></i>View Dashboard
                        </a>
                    </div>
                </div>

                <!-- Alert Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-danger"><?= count(array_filter($active_alerts, fn($a) => $a['alert_type'] == 'critical')) ?></h3>
                                <p class="mb-0">Critical Alerts</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning"><?= count(array_filter($active_alerts, fn($a) => $a['alert_type'] == 'warning')) ?></h3>
                                <p class="mb-0">Warning Alerts</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?= count(array_filter($active_alerts, fn($a) => $a['alert_type'] == 'info')) ?></h3>
                                <p class="mb-0">Info Alerts</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-muted"><?= count($active_alerts) ?></h3>
                                <p class="mb-0">Total Active</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Alerts -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Active Alerts</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($active_alerts)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                                <h5>No Active Alerts</h5>
                                <p>All systems are operating normally.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($active_alerts as $alert): ?>
                                <div class="alert-card card mb-3 alert-<?= $alert['alert_type'] ?>">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="status-indicator status-<?= $alert['status'] ?>"></span>
                                                    <h6 class="mb-0 me-3"><?= htmlspecialchars($alert['title']) ?></h6>
                                                    <span class="badge severity-badge bg-<?= $alert['alert_type'] == 'critical' ? 'danger' : ($alert['alert_type'] == 'warning' ? 'warning' : 'info') ?>">
                                                        <?= ucfirst($alert['alert_type']) ?>
                                                    </span>
                                                    <?php if ($alert['escalation_level'] > 1): ?>
                                                        <span class="badge severity-badge bg-secondary">Lvl <?= $alert['escalation_level'] ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($alert['description']): ?>
                                                    <p class="mb-2 text-muted"><?= htmlspecialchars($alert['description']) ?></p>
                                                <?php endif; ?>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <small class="text-muted">
                                                            <i class="fas fa-industry me-1"></i><?= htmlspecialchars($alert['production_line']) ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i><?= date('M j, H:i', strtotime($alert['created_at'])) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-md-4 text-end">
                                                <?php if ($alert['status'] === 'active' || $alert['status'] === 'escalated'): ?>
                                                    <button class="btn btn-sm btn-warning mb-2" onclick="acknowledgeAlert(<?= $alert['id'] ?>)">
                                                        <i class="fas fa-check me-1"></i>Acknowledge
                                                    </button>
                                                    <button class="btn btn-sm btn-success mb-2" onclick="resolveAlert(<?= $alert['id'] ?>)">
                                                        <i class="fas fa-check-double me-1"></i>Resolve
                                                    </button>
                                                <?php else: ?>
                                                    <small class="text-success">
                                                        <i class="fas fa-check-circle me-1"></i>
                                                        <?= $alert['status'] === 'acknowledged' ? 'Acknowledged' : 'Resolved' ?>
                                                        <?php if ($alert['acknowledged_by']): ?>
                                                            by <?= htmlspecialchars($alert['acknowledged_by']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function monitorAlerts() {
            fetch('alert_system.php?action=monitor')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function acknowledgeAlert(alertId) {
            const userName = prompt('Enter your name to acknowledge this alert:');
            if (userName) {
                fetch(`alert_system.php?action=acknowledge&alert_id=${alertId}&user_name=${encodeURIComponent(userName)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        }

        function resolveAlert(alertId) {
            const userName = prompt('Enter your name to resolve this alert:');
            if (userName) {
                const notes = prompt('Enter resolution notes (optional):');
                fetch(`alert_system.php?action=resolve&alert_id=${alertId}&user_name=${encodeURIComponent(userName)}&notes=${encodeURIComponent(notes || '')}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            monitorAlerts();
        }, 30000);
    </script>
</body>
</html>
<?php
}
?>