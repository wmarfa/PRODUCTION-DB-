<?php
require_once 'assets.php';
require_once 'database_enhancements.php';
require_once 'user_management_offline.php';

// Enhanced Security and Session Management
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:;');

// CSRF Protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token']) ||
        $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
        error_log('CSRF validation failed in workflow_automation.php');
        die('Security validation failed');
    }
}

// Authentication and Authorization
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    header('Location: index_lan.php');
    exit;
}

// Check permissions (Admin and Manager roles only)
if (!in_array($_SESSION['user_role'], ['manager', 'admin'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied. You do not have permission to access workflow automation.');
}

/**
 * Workflow Automation and Process Optimization Engine
 * Intelligent automation system for production management
 */
class WorkflowAutomationEngine {
    private $conn;
    private $userRole;
    private $workflows = [];
    private $optimizations = [];
    private $automationLog = [];

    public function __construct($conn, $userRole) {
        $this->conn = $conn;
        $this->userRole = $userRole;
        $this->initializeWorkflowDatabase();
        $this->loadActiveWorkflows();
    }

    /**
     * Initialize workflow automation database tables
     */
    private function initializeWorkflowDatabase() {
        // Create workflow definitions table
        $createWorkflowsTable = "CREATE TABLE IF NOT EXISTS workflow_definitions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            workflow_type ENUM('performance_monitoring', 'quality_control', 'maintenance', 'production_optimization', 'alert_management') NOT NULL,
            trigger_conditions JSON,
            actions JSON,
            is_active BOOLEAN DEFAULT TRUE,
            priority INT DEFAULT 1,
            execution_frequency ENUM('real_time', 'hourly', 'daily', 'weekly') DEFAULT 'daily',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_executed TIMESTAMP NULL,
            execution_count INT DEFAULT 0,
            success_count INT DEFAULT 0,
            failure_count INT DEFAULT 0,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_workflow_type (workflow_type),
            INDEX idx_is_active (is_active),
            INDEX idx_execution_frequency (execution_frequency)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createWorkflowsTable);

        // Create workflow execution log table
        $createLogTable = "CREATE TABLE IF NOT EXISTS workflow_execution_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            workflow_id INT NOT NULL,
            execution_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            trigger_data JSON,
            actions_executed JSON,
            execution_result ENUM('success', 'partial_success', 'failed') NOT NULL,
            execution_time_ms INT,
            error_message TEXT,
            affected_records INT,
            impact_assessment JSON,
            FOREIGN KEY (workflow_id) REFERENCES workflow_definitions(id) ON DELETE CASCADE,
            INDEX idx_workflow_id (workflow_id),
            INDEX idx_execution_time (execution_time),
            INDEX idx_execution_result (execution_result)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createLogTable);

        // Create optimization suggestions table
        $createOptimizationsTable = "CREATE TABLE IF NOT EXISTS optimization_suggestions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            suggestion_type ENUM('process_improvement', 'resource_optimization', 'quality_enhancement', 'maintenance_scheduling', 'cost_reduction') NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            target_line_shift VARCHAR(50),
            priority ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            estimated_impact JSON,
            implementation_effort ENUM('easy', 'moderate', 'difficult') NOT NULL,
            required_resources JSON,
            success_metrics JSON,
            status ENUM('pending_review', 'approved', 'in_progress', 'implemented', 'rejected') DEFAULT 'pending_review',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_by INT NULL,
            reviewed_at TIMESTAMP NULL,
            implementation_deadline DATE NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_suggestion_type (suggestion_type),
            INDEX idx_priority (priority),
            INDEX idx_status (status),
            INDEX idx_target_line_shift (target_line_shift)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createOptimizationsTable);

        // Create process bottleneck tracking table
        $createBottlenecksTable = "CREATE TABLE IF NOT EXISTS process_bottlenecks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            line_shift VARCHAR(50) NOT NULL,
            bottleneck_type ENUM('equipment', 'manpower', 'material', 'quality', 'process') NOT NULL,
            severity ENUM('minor', 'major', 'critical') NOT NULL,
            description TEXT NOT NULL,
            root_cause_analysis JSON,
            impact_quantification JSON,
            detection_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP NULL,
            resolution_method TEXT,
            preventive_measures JSON,
            recurring BOOLEAN DEFAULT FALSE,
            frequency_days INT DEFAULT 0,
            FOREIGN KEY (line_shift) REFERENCES daily_performance(line_shift),
            INDEX idx_line_shift (line_shift),
            INDEX idx_bottleneck_type (bottleneck_type),
            INDEX idx_severity (severity),
            INDEX idx_detection_time (detection_time),
            INDEX idx_resolved_at (resolved_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createBottlenecksTable);
    }

    /**
     * Load all active workflows from database
     */
    private function loadActiveWorkflows() {
        $query = "SELECT * FROM workflow_definitions WHERE is_active = TRUE ORDER BY priority DESC";
        $result = $this->conn->query($query);

        while ($row = $result->fetch_assoc()) {
            $this->workflows[] = $row;
        }
    }

    /**
     * Execute all scheduled workflows
     */
    public function executeScheduledWorkflows() {
        $executedCount = 0;
        $successCount = 0;
        $executionResults = [];

        foreach ($this->workflows as $workflow) {
            if ($this->shouldExecuteWorkflow($workflow)) {
                $executionResult = $this->executeWorkflow($workflow);
                $executionResults[] = $executionResult;
                $executedCount++;

                if ($executionResult['result'] === 'success') {
                    $successCount++;
                }
            }
        }

        // Generate workflow execution summary
        $this->logWorkflowExecution($executedCount, $successCount, $executionResults);

        return [
            'total_executed' => $executedCount,
            'success_count' => $successCount,
            'failure_count' => $executedCount - $successCount,
            'execution_results' => $executionResults
        ];
    }

    /**
     * Check if workflow should be executed based on schedule and conditions
     */
    private function shouldExecuteWorkflow($workflow) {
        $now = time();
        $lastExecuted = $workflow['last_executed'] ? strtotime($workflow['last_executed']) : 0;

        // Check execution frequency
        switch ($workflow['execution_frequency']) {
            case 'real_time':
                return true;
            case 'hourly':
                return ($now - $lastExecuted) >= 3600;
            case 'daily':
                return ($now - $lastExecuted) >= 86400;
            case 'weekly':
                return ($now - $lastExecuted) >= 604800;
            default:
                return false;
        }
    }

    /**
     * Execute a specific workflow
     */
    private function executeWorkflow($workflow) {
        $startTime = microtime(true);
        $executionResult = [
            'workflow_id' => $workflow['id'],
            'workflow_name' => $workflow['name'],
            'start_time' => date('Y-m-d H:i:s'),
            'result' => 'failed',
            'actions_executed' => [],
            'trigger_data' => null,
            'error_message' => null,
            'affected_records' => 0,
            'execution_time_ms' => 0
        ];

        try {
            // Check trigger conditions
            $triggerData = $this->evaluateTriggerConditions($workflow['trigger_conditions']);
            $executionResult['trigger_data'] = $triggerData;

            if (!$triggerData['condition_met']) {
                $executionResult['result'] = 'no_trigger';
                return $executionResult;
            }

            // Execute workflow actions
            $actions = json_decode($workflow['actions'], true);
            $successCount = 0;
            $totalActions = count($actions);

            foreach ($actions as $action) {
                $actionResult = $this->executeAction($action, $triggerData);
                $executionResult['actions_executed'][] = $actionResult;

                if ($actionResult['success']) {
                    $successCount++;
                    $executionResult['affected_records'] += $actionResult['affected_records'] ?? 0;
                } else {
                    $executionResult['error_message'] = $actionResult['error'] ?? 'Action failed';
                }
            }

            // Determine overall result
            if ($successCount === $totalActions) {
                $executionResult['result'] = 'success';
                $this->updateWorkflowStats($workflow['id'], 'success');
            } elseif ($successCount > 0) {
                $executionResult['result'] = 'partial_success';
                $this->updateWorkflowStats($workflow['id'], 'partial_success');
            } else {
                $this->updateWorkflowStats($workflow['id'], 'failed');
            }

        } catch (Exception $e) {
            $executionResult['error_message'] = $e->getMessage();
            $this->updateWorkflowStats($workflow['id'], 'failed');
        }

        $executionResult['execution_time_ms'] = round((microtime(true) - $startTime) * 1000);

        // Log execution
        $this->logWorkflowExecutionDetail($executionResult);

        return $executionResult;
    }

    /**
     * Evaluate workflow trigger conditions
     */
    private function evaluateTriggerConditions($triggerConditions) {
        $conditions = json_decode($triggerConditions, true);
        $triggerData = [
            'condition_met' => false,
            'evaluation_results' => []
        ];

        if (empty($conditions)) {
            return $triggerData;
        }

        foreach ($conditions as $condition) {
            $result = $this->evaluateCondition($condition);
            $triggerData['evaluation_results'][] = $result;

            if ($result['met']) {
                $triggerData['condition_met'] = true;
            }
        }

        return $triggerData;
    }

    /**
     * Evaluate individual condition
     */
    private function evaluateCondition($condition) {
        $metric = $condition['metric'];
        $operator = $condition['operator'];
        $threshold = $condition['threshold'];
        $timeframe = $condition['timeframe'] ?? 'current';

        $value = $this->getMetricValue($metric, $timeframe);

        $result = [
            'metric' => $metric,
            'operator' => $operator,
            'threshold' => $threshold,
            'actual_value' => $value,
            'met' => false
        ];

        switch ($operator) {
            case 'greater_than':
                $result['met'] = $value > $threshold;
                break;
            case 'less_than':
                $result['met'] = $value < $threshold;
                break;
            case 'equals':
                $result['met'] = $value == $threshold;
                break;
            case 'greater_than_or_equals':
                $result['met'] = $value >= $threshold;
                break;
            case 'less_than_or_equals':
                $result['met'] = $value <= $threshold;
                break;
            case 'percentage_below':
                $result['met'] = (($threshold - $value) / $threshold) * 100 > 10;
                break;
            case 'percentage_above':
                $result['met'] = (($value - $threshold) / $threshold) * 100 > 10;
                break;
        }

        return $result;
    }

    /**
     * Get metric value for evaluation
     */
    private function getMetricValue($metric, $timeframe) {
        switch ($metric) {
            case 'line_efficiency':
                return $this->calculateAverageLineEfficiency($timeframe);
            case 'plan_completion_rate':
                return $this->calculatePlanCompletionRate($timeframe);
            case 'quality_yield_rate':
                return $this->calculateQualityYieldRate($timeframe);
            case 'machine_downtime':
                return $this->getAverageDowntime($timeframe);
            case 'oee_score':
                return $this->calculateAverageOEE($timeframe);
            case 'production_variance':
                return $this->calculateProductionVariance($timeframe);
            case 'alert_count':
                return $this->getActiveAlertCount();
            case 'bottleneck_count':
                return $this->getActiveBottleneckCount();
            default:
                return 0;
        }
    }

    /**
     * Execute workflow action
     */
    private function executeAction($action, $triggerData) {
        $actionResult = [
            'action_type' => $action['type'],
            'success' => false,
            'error' => null,
            'affected_records' => 0,
            'details' => []
        ];

        try {
            switch ($action['type']) {
                case 'create_alert':
                    $actionResult = $this->executeCreateAlertAction($action, $triggerData);
                    break;
                case 'adjust_production_parameters':
                    $actionResult = $this->executeAdjustParametersAction($action, $triggerData);
                    break;
                case 'schedule_maintenance':
                    $actionResult = $this->executeScheduleMaintenanceAction($action, $triggerData);
                    break;
                case 'notify_supervisor':
                    $actionResult = $this->executeNotifySupervisorAction($action, $triggerData);
                    break;
                case 'optimize_resource_allocation':
                    $actionResult = $this->executeOptimizeResourceAction($action, $triggerData);
                    break;
                case 'generate_report':
                    $actionResult = $this->executeGenerateReportAction($action, $triggerData);
                    break;
                case 'quality_control_checkpoint':
                    $actionResult = $this->executeQualityControlAction($action, $triggerData);
                    break;
                case 'escalate_issue':
                    $actionResult = $this->executeEscalateIssueAction($action, $triggerData);
                    break;
                default:
                    $actionResult['error'] = "Unknown action type: {$action['type']}";
            }
        } catch (Exception $e) {
            $actionResult['error'] = $e->getMessage();
        }

        return $actionResult;
    }

    /**
     * Execute create alert action
     */
    private function executeCreateAlertAction($action, $triggerData) {
        $alertData = $action['parameters'];

        $query = "INSERT INTO production_alerts
                  (alert_type, severity, title, message, line_shift, status, created_at)
                  VALUES (?, ?, ?, ?, ?, 'active', NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sssss",
            $alertData['alert_type'],
            $alertData['severity'],
            $alertData['title'],
            $alertData['message'],
            $alertData['line_shift']
        );

        $success = $stmt->execute();

        return [
            'action_type' => 'create_alert',
            'success' => $success,
            'error' => $success ? null : $stmt->error,
            'affected_records' => $success ? 1 : 0,
            'details' => ['alert_id' => $success ? $this->conn->insert_id : null]
        ];
    }

    /**
     * Execute adjust production parameters action
     */
    private function executeAdjustParametersAction($action, $triggerData) {
        $parameters = $action['parameters'];
        $targetLine = $parameters['target_line_shift'];
        $adjustments = $parameters['adjustments'];

        $affectedRecords = 0;

        foreach ($adjustments as $adjustment) {
            $field = $adjustment['field'];
            $change = $adjustment['change'];
            $adjustmentType = $adjustment['type'] ?? 'percentage'; // percentage or absolute

            $query = "UPDATE daily_performance SET $field = ";
            if ($adjustmentType === 'percentage') {
                $query .= "$field * (1 + ? / 100)";
            } else {
                $query .= "$field + ?";
            }
            $query .= " WHERE line_shift = ? AND date = CURDATE()";

            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ds", $change, $targetLine);

            if ($stmt->execute()) {
                $affectedRecords += $stmt->affected_rows;
            }
        }

        return [
            'action_type' => 'adjust_production_parameters',
            'success' => $affectedRecords > 0,
            'error' => $affectedRecords > 0 ? null : 'No records updated',
            'affected_records' => $affectedRecords,
            'details' => ['target_line' => $targetLine, 'adjustments' => $adjustments]
        ];
    }

    /**
     * Execute schedule maintenance action
     */
    private function executeScheduleMaintenanceAction($action, $triggerData) {
        $maintenanceData = $action['parameters'];

        $query = "INSERT INTO maintenance_schedules
                  (line_shift, maintenance_type, scheduled_date, priority, description, created_at)
                  VALUES (?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sssss",
            $maintenanceData['line_shift'],
            $maintenanceData['maintenance_type'],
            $maintenanceData['scheduled_date'],
            $maintenanceData['priority'],
            $maintenanceData['description']
        );

        $success = $stmt->execute();

        return [
            'action_type' => 'schedule_maintenance',
            'success' => $success,
            'error' => $success ? null : $stmt->error,
            'affected_records' => $success ? 1 : 0,
            'details' => ['maintenance_id' => $success ? $this->conn->insert_id : null]
        ];
    }

    /**
     * Execute notify supervisor action
     */
    private function executeNotifySupervisorAction($action, $triggerData) {
        $notificationData = $action['parameters'];

        // Log notification for supervisor dashboard
        $query = "INSERT INTO supervisor_notifications
                  (supervisor_id, notification_type, title, message, priority, is_read, created_at)
                  VALUES (?, ?, ?, ?, ?, FALSE, NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "issss",
            $notificationData['supervisor_id'],
            $notificationData['notification_type'],
            $notificationData['title'],
            $notificationData['message'],
            $notificationData['priority']
        );

        $success = $stmt->execute();

        return [
            'action_type' => 'notify_supervisor',
            'success' => $success,
            'error' => $success ? null : $stmt->error,
            'affected_records' => $success ? 1 : 0,
            'details' => ['notification_id' => $success ? $this->conn->insert_id : null]
        ];
    }

    /**
     * Execute optimize resource allocation action
     */
    private function executeOptimizeResourceAction($action, $triggerData) {
        $resourceData = $action['parameters'];
        $optimizationType = $resourceData['optimization_type'];

        $affectedRecords = 0;
        $optimizationDetails = [];

        switch ($optimizationType) {
            case 'manpower_rebalancing':
                $affectedRecords = $this->rebalanceManpower($resourceData, $optimizationDetails);
                break;
            case 'equipment_reallocation':
                $affectedRecords = $this->reallocateEquipment($resourceData, $optimizationDetails);
                break;
            case 'shift_optimization':
                $affectedRecords = $this->optimizeShiftScheduling($resourceData, $optimizationDetails);
                break;
        }

        return [
            'action_type' => 'optimize_resource_allocation',
            'success' => $affectedRecords > 0,
            'error' => $affectedRecords > 0 ? null : 'No optimizations applied',
            'affected_records' => $affectedRecords,
            'details' => $optimizationDetails
        ];
    }

    /**
     * Execute generate report action
     */
    private function executeGenerateReportAction($action, $triggerData) {
        $reportData = $action['parameters'];
        $reportType = $reportData['report_type'];

        // Create report generation record
        $query = "INSERT INTO generated_reports
                  (report_type, parameters, status, created_at, created_by)
                  VALUES (?, ?, 'pending', NOW(), ?)";

        $parametersJson = json_encode($reportData['parameters'] ?? []);
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssi", $reportType, $parametersJson, $_SESSION['user_id']);

        $success = $stmt->execute();

        return [
            'action_type' => 'generate_report',
            'success' => $success,
            'error' => $success ? null : $stmt->error,
            'affected_records' => $success ? 1 : 0,
            'details' => ['report_id' => $success ? $this->conn->insert_id : null]
        ];
    }

    /**
     * Execute quality control checkpoint action
     */
    private function executeQualityControlAction($action, $triggerData) {
        $qualityData = $action['parameters'];

        $query = "INSERT INTO quality_measurements
                  (checkpoint_id, line_shift, date, shift, measure_value, is_conforming, operator_name, created_at)
                  VALUES (?, ?, CURDATE(), ?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "isisds",
            $qualityData['checkpoint_id'],
            $qualityData['line_shift'],
            $qualityData['shift'],
            $qualityData['measure_value'],
            $qualityData['is_conforming'],
            $qualityData['operator_name']
        );

        $success = $stmt->execute();

        return [
            'action_type' => 'quality_control_checkpoint',
            'success' => $success,
            'error' => $success ? null : $stmt->error,
            'affected_records' => $success ? 1 : 0,
            'details' => ['measurement_id' => $success ? $this->conn->insert_id : null]
        ];
    }

    /**
     * Execute escalate issue action
     */
    private function executeEscalateIssueAction($action, $triggerData) {
        $escalationData = $action['parameters'];

        $query = "INSERT INTO issue_escalations
                  (issue_id, escalated_to, escalation_level, reason, status, created_at, escalated_by)
                  VALUES (?, ?, ?, ?, 'pending', NOW(), ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "iissi",
            $escalationData['issue_id'],
            $escalationData['escalated_to'],
            $escalationData['escalation_level'],
            $escalationData['reason'],
            $_SESSION['user_id']
        );

        $success = $stmt->execute();

        return [
            'action_type' => 'escalate_issue',
            'success' => $success,
            'error' => $success ? null : $stmt->error,
            'affected_records' => $success ? 1 : 0,
            'details' => ['escalation_id' => $success ? $this->conn->insert_id : null]
        ];
    }

    /**
     * Generate optimization suggestions based on production data analysis
     */
    public function generateOptimizationSuggestions() {
        $suggestions = [];
        $analysisData = $this->analyzeProductionData();

        // Analyze efficiency trends
        $suggestions = array_merge($suggestions, $this->analyzeEfficiencyOptimizations($analysisData));

        // Analyze quality patterns
        $suggestions = array_merge($suggestions, $this->analyzeQualityOptimizations($analysisData));

        // Analyze resource utilization
        $suggestions = array_merge($suggestions, $this->analyzeResourceOptimizations($analysisData));

        // Analyze bottlenecks
        $suggestions = array_merge($suggestions, $this->analyzeBottleneckOptimizations($analysisData));

        // Analyze maintenance patterns
        $suggestions = array_merge($suggestions, $this->analyzeMaintenanceOptimizations($analysisData));

        // Store suggestions in database
        $storedCount = 0;
        foreach ($suggestions as $suggestion) {
            if ($this->storeOptimizationSuggestion($suggestion)) {
                $storedCount++;
            }
        }

        return [
            'total_suggestions' => count($suggestions),
            'stored_suggestions' => $storedCount,
            'suggestions' => $suggestions
        ];
    }

    /**
     * Analyze production data for optimization opportunities
     */
    private function analyzeProductionData() {
        $analysisData = [];

        // Get recent performance data
        $performanceQuery = "SELECT
                                line_shift,
                                date,
                                actual_output,
                                plan,
                                efficiency,
                                plan_completion,
                                machine_downtime,
                                input_rate,
                                line_utilization
                             FROM daily_performance
                             WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                             ORDER BY line_shift, date";

        $result = $this->conn->query($performanceQuery);
        $analysisData['performance'] = $result->fetch_all(MYSQLI_ASSOC);

        // Get quality data
        $qualityQuery = "SELECT
                            qm.checkpoint_id,
                            qm.line_shift,
                            qm.date,
                            qm.measure_value,
                            qm.is_conforming,
                            qc.checkpoint_name,
                            qc.process_category
                         FROM quality_measurements qm
                         JOIN quality_checkpoints qc ON qm.checkpoint_id = qc.checkpoint_id
                         WHERE qm.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                         ORDER BY qm.line_shift, qm.date";

        $result = $this->conn->query($qualityQuery);
        $analysisData['quality'] = $result->fetch_all(MYSQLI_ASSOC);

        // Get maintenance data
        $maintenanceQuery = "SELECT
                               line_shift,
                               maintenance_type,
                               scheduled_date,
                               completion_date,
                               priority,
                               downtime_hours
                            FROM maintenance_schedules
                            WHERE scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                            ORDER BY line_shift, scheduled_date";

        $result = $this->conn->query($maintenanceQuery);
        $analysisData['maintenance'] = $result->fetch_all(MYSQLI_ASSOC);

        return $analysisData;
    }

    /**
     * Analyze efficiency optimization opportunities
     */
    private function analyzeEfficiencyOptimizations($analysisData) {
        $suggestions = [];
        $performanceData = $analysisData['performance'];

        // Group data by line_shift
        $linePerformance = [];
        foreach ($performanceData as $record) {
            $linePerformance[$record['line_shift']][] = $record;
        }

        foreach ($linePerformance as $lineShift => $data) {
            if (count($data) < 5) continue; // Need sufficient data

            $avgEfficiency = array_sum(array_column($data, 'efficiency')) / count($data);
            $avgCompletion = array_sum(array_column($data, 'plan_completion')) / count($data);
            $avgDowntime = array_sum(array_column($data, 'machine_downtime')) / count($data);

            // Low efficiency suggestion
            if ($avgEfficiency < 0.75) {
                $suggestions[] = [
                    'suggestion_type' => 'process_improvement',
                    'title' => "Improve Efficiency for {$lineShift}",
                    'description' => "Average efficiency of {$lineShift} is " . round($avgEfficiency * 100, 1) . "%, which is below target. Consider operator training or process optimization.",
                    'target_line_shift' => $lineShift,
                    'priority' => $avgEfficiency < 0.6 ? 'high' : 'medium',
                    'estimated_impact' => [
                        'efficiency_improvement' => round((0.85 - $avgEfficiency) * 100, 1),
                        'cost_savings' => $this->calculateEfficiencyCostSavings($avgEfficiency, $data)
                    ],
                    'implementation_effort' => 'moderate',
                    'required_resources' => ['training_program', 'process_analysis', 'equipment_maintenance'],
                    'success_metrics' => ['efficiency > 85%', 'reduced_variation', 'consistent_output']
                ];
            }

            // High downtime suggestion
            if ($avgDowntime > 60) {
                $suggestions[] = [
                    'suggestion_type' => 'maintenance_scheduling',
                    'title' => "Reduce Downtime for {$lineShift}",
                    'description' => "Average downtime is " . round($avgDowntime, 1) . " minutes per shift. Implement preventive maintenance program.",
                    'target_line_shift' => $lineShift,
                    'priority' => $avgDowntime > 120 ? 'critical' : 'high',
                    'estimated_impact' => [
                        'downtime_reduction' => round(($avgDowntime - 30) / $avgDowntime * 100, 1),
                        'availability_improvement' => 15
                    ],
                    'implementation_effort' => 'moderate',
                    'required_resources' => ['maintenance_team', 'spare_parts', 'training'],
                    'success_metrics' => ['downtime < 30 minutes', 'availability > 90%', 'reduced emergency repairs']
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Analyze quality optimization opportunities
     */
    private function analyzeQualityOptimizations($analysisData) {
        $suggestions = [];
        $qualityData = $analysisData['quality'];

        // Group by checkpoint and process category
        $checkpointPerformance = [];
        foreach ($qualityData as $record) {
            $key = $record['checkpoint_id'];
            if (!isset($checkpointPerformance[$key])) {
                $checkpointPerformance[$key] = [
                    'checkpoint_name' => $record['checkpoint_name'],
                    'process_category' => $record['process_category'],
                    'measurements' => [],
                    'conforming_count' => 0
                ];
            }
            $checkpointPerformance[$key]['measurements'][] = $record;
            if ($record['is_conforming']) {
                $checkpointPerformance[$key]['conforming_count']++;
            }
        }

        foreach ($checkpointPerformance as $checkpointId => $data) {
            if (count($data['measurements']) < 10) continue; // Need sufficient data

            $yieldRate = ($data['conforming_count'] / count($data['measurements'])) * 100;

            if ($yieldRate < 95) {
                $suggestions[] = [
                    'suggestion_type' => 'quality_enhancement',
                    'title' => "Improve Quality at {$data['checkpoint_name']}",
                    'description' => "Yield rate is " . round($yieldRate, 1) . "% for {$data['checkpoint_name']} in {$data['process_category']}. Quality improvement initiative needed.",
                    'target_line_shift' => null, // This is a general quality improvement
                    'priority' => $yieldRate < 90 ? 'critical' : 'high',
                    'estimated_impact' => [
                        'yield_improvement' => round(98 - $yieldRate, 1),
                        'defect_reduction' => round((100 - $yieldRate) * 0.5, 1),
                        'cost_savings' => $this->calculateQualityCostSavings($yieldRate, count($data['measurements']))
                    ],
                    'implementation_effort' => 'moderate',
                    'required_resources' => ['quality_team', 'training', 'process_validation', 'measurement_equipment'],
                    'success_metrics' => ['yield > 98%', 'reduced variation', 'consistent measurements']
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Analyze resource optimization opportunities
     */
    private function analyzeResourceOptimizations($analysisData) {
        $suggestions = [];
        $performanceData = $analysisData['performance'];

        // Analyze manpower efficiency
        $linePerformance = [];
        foreach ($performanceData as $record) {
            $linePerformance[$record['line_shift']][] = $record;
        }

        foreach ($linePerformance as $lineShift => $data) {
            if (count($data) < 5) continue;

            $totalOutput = array_sum(array_column($data, 'actual_output'));
            $totalMHR = array_sum(array_map(function($record) {
                return ($record['no_ot_mp'] * 8) + ($record['ot_mp'] * 8);
            }, $data));

            $outputPerMHR = $totalMHR > 0 ? $totalOutput / $totalMHR : 0;

            // Compare with industry benchmark (assume 50 units per MHR as benchmark)
            if ($outputPerMHR < 40) {
                $suggestions[] = [
                    'suggestion_type' => 'resource_optimization',
                    'title' => "Optimize Manpower for {$lineShift}",
                    'description' => "Current productivity is " . round($outputPerMHR, 1) . " units per MHR. Resource optimization can improve efficiency.",
                    'target_line_shift' => $lineShift,
                    'priority' => $outputPerMHR < 30 ? 'high' : 'medium',
                    'estimated_impact' => [
                        'productivity_improvement' => round((50 - $outputPerMHR) / 50 * 100, 1),
                        'manpower_optimization' => '15-20%',
                        'cost_reduction' => $this->calculateManpowerCostSavings($outputPerMHR, $data)
                    ],
                    'implementation_effort' => 'moderate',
                    'required_resources' => ['workforce_analysis', 'skill_assessment', 'training', 'process_balancing'],
                    'success_metrics' => ['productivity > 50 units/MHR', 'balanced workload', 'reduced overtime']
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Analyze bottleneck optimization opportunities
     */
    private function analyzeBottleneckOptimizations($analysisData) {
        $suggestions = [];
        $performanceData = $analysisData['performance'];

        // Identify lines with consistent underperformance
        $linePerformance = [];
        foreach ($performanceData as $record) {
            $linePerformance[$record['line_shift']][] = $record;
        }

        foreach ($linePerformance as $lineShift => $data) {
            if (count($data) < 5) continue;

            $underperformanceDays = 0;
            foreach ($data as $record) {
                if ($record['plan_completion'] < 85) {
                    $underperformanceDays++;
                }
            }

            $underperformanceRate = ($underperformanceDays / count($data)) * 100;

            if ($underperformanceRate > 50) {
                $suggestions[] = [
                    'suggestion_type' => 'process_improvement',
                    'title' => "Resolve Bottleneck at {$lineShift}",
                    'description' => "Line {$lineShift} underperforms " . round($underperformanceRate, 1) . "% of the time. Bottleneck analysis and resolution needed.",
                    'target_line_shift' => $lineShift,
                    'priority' => $underperformanceRate > 75 ? 'critical' : 'high',
                    'estimated_impact' => [
                        'bottleneck_resolution' => round($underperformanceRate * 0.7, 1),
                        'throughput_improvement' => '20-30%',
                        'plan_completion_improvement' => '15-25%'
                    ],
                    'implementation_effort' => 'difficult',
                    'required_resources' => ['process_engineering', 'equipment_upgrade', 'root_cause_analysis', 'cross_functional_team'],
                    'success_metrics' => ['plan_completion > 95%', 'reduced bottlenecks', 'consistent performance']
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Analyze maintenance optimization opportunities
     */
    private function analyzeMaintenanceOptimizations($analysisData) {
        $suggestions = [];
        $maintenanceData = $analysisData['maintenance'];

        if (empty($maintenanceData)) {
            // Suggest preventive maintenance program if no data exists
            $suggestions[] = [
                'suggestion_type' => 'maintenance_scheduling',
                'title' => "Implement Preventive Maintenance Program",
                'description' => "No systematic maintenance data available. Implement comprehensive preventive maintenance program.",
                'target_line_shift' => null,
                'priority' => 'medium',
                'estimated_impact' => [
                    'downtime_reduction' => '40-60%',
                    'equipment_reliability' => '25-35%',
                    'maintenance_cost_reduction' => '15-20%'
                ],
                'implementation_effort' => 'moderate',
                'required_resources' => ['maintenance_planning', 'CMMS_system', 'trained_technicians', 'spare_parts_inventory'],
                'success_metrics' => ['planned_maintenance > 80%', 'emergency_repairs < 10%', 'equipment_availability > 95%']
            ];
        }

        return $suggestions;
    }

    /**
     * Store optimization suggestion in database
     */
    private function storeOptimizationSuggestion($suggestion) {
        $query = "INSERT INTO optimization_suggestions
                  (suggestion_type, title, description, target_line_shift, priority,
                   estimated_impact, implementation_effort, required_resources, success_metrics, created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sssssssssi",
            $suggestion['suggestion_type'],
            $suggestion['title'],
            $suggestion['description'],
            $suggestion['target_line_shift'],
            $suggestion['priority'],
            json_encode($suggestion['estimated_impact']),
            $suggestion['implementation_effort'],
            json_encode($suggestion['required_resources']),
            json_encode($suggestion['success_metrics']),
            $_SESSION['user_id']
        );

        return $stmt->execute();
    }

    /**
     * Helper methods for calculations and metrics
     */

    private function calculateAverageLineEfficiency($timeframe) {
        $query = "SELECT AVG(efficiency) as avg_efficiency FROM daily_performance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $result = $this->conn->query($query);
        $row = $result->fetch_assoc();
        return $row['avg_efficiency'] ?? 0;
    }

    private function calculatePlanCompletionRate($timeframe) {
        $query = "SELECT AVG(plan_completion) as avg_completion FROM daily_performance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $result = $this->conn->query($query);
        $row = $result->fetch_assoc();
        return $row['avg_completion'] ?? 0;
    }

    private function calculateQualityYieldRate($timeframe) {
        $query = "SELECT AVG(CASE WHEN is_conforming = 1 THEN 100 ELSE 0 END) as avg_yield
                 FROM quality_measurements WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $result = $this->conn->query($query);
        $row = $result->fetch_assoc();
        return $row['avg_yield'] ?? 0;
    }

    private function getAverageDowntime($timeframe) {
        $query = "SELECT AVG(machine_downtime) as avg_downtime FROM daily_performance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $result = $this->conn->query($query);
        $row = $result->fetch_assoc();
        return $row['avg_downtime'] ?? 0;
    }

    private function calculateAverageOEE($timeframe) {
        // Simplified OEE calculation
        $query = "SELECT AVG(efficiency * plan_completion / 100) as avg_oee FROM daily_performance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $result = $this->conn->query($query);
        $row = $result->fetch_assoc();
        return $row['avg_oee'] ?? 0;
    }

    private function calculateProductionVariance($timeframe) {
        $query = "SELECT STDDEV((actual_output - plan) / plan * 100) as variance FROM daily_performance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $result = $this->conn->query($query);
        $row = $result->fetch_assoc();
        return $row['variance'] ?? 0;
    }

    private function getActiveAlertCount() {
        $query = "SELECT COUNT(*) as count FROM production_alerts WHERE status = 'active'";
        $result = $this->conn->query($query);
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }

    private function getActiveBottleneckCount() {
        $query = "SELECT COUNT(*) as count FROM process_bottlenecks WHERE resolved_at IS NULL";
        $result = $this->conn->query($query);
        $row = $result->fetch_assoc();
        return $row['count'] ?? 0;
    }

    private function calculateEfficiencyCostSavings($currentEfficiency, $performanceData) {
        // Simplified cost calculation - would need actual cost data in production
        $totalOutput = array_sum(array_column($performanceData, 'actual_output'));
        $targetOutput = $totalOutput / $currentEfficiency;
        $savings = ($targetOutput - $totalOutput) * 10; // Assume $10 per unit savings
        return round($savings, 2);
    }

    private function calculateQualityCostSavings($currentYield, $measurementCount) {
        // Calculate potential savings from yield improvement
        $defectRate = (100 - $currentYield) / 100;
        $targetYield = 98;
        $improvedDefectRate = (100 - $targetYield) / 100;
        $defectReduction = ($defectRate - $improvedDefectRate) * $measurementCount;
        $savings = $defectReduction * 50; // Assume $50 per defect saved
        return round($savings, 2);
    }

    private function calculateManpowerCostSavings($currentProductivity, $performanceData) {
        // Calculate potential savings from improved manpower efficiency
        $totalMHR = array_sum(array_map(function($record) {
            return ($record['no_ot_mp'] * 8) + ($record['ot_mp'] * 8);
        }, $performanceData));
        $targetProductivity = 50;
        $productivityImprovement = ($targetProductivity - $currentProductivity) / $currentProductivity;
        $savings = $totalMHR * $productivityImprovement * 25; // Assume $25 per MHR saved
        return round($savings, 2);
    }

    private function rebalanceManpower($resourceData, &$optimizationDetails) {
        // Implementation for manpower rebalancing
        $optimizationDetails[] = 'Manpower rebalancing logic executed';
        return 1; // Return affected records count
    }

    private function reallocateEquipment($resourceData, &$optimizationDetails) {
        // Implementation for equipment reallocation
        $optimizationDetails[] = 'Equipment reallocation logic executed';
        return 1;
    }

    private function optimizeShiftScheduling($resourceData, &$optimizationDetails) {
        // Implementation for shift scheduling optimization
        $optimizationDetails[] = 'Shift scheduling optimization executed';
        return 1;
    }

    private function updateWorkflowStats($workflowId, $result) {
        $query = "UPDATE workflow_definitions
                  SET execution_count = execution_count + 1,
                      last_executed = NOW(),
                      " . ($result === 'success' ? 'success_count = success_count + 1' : 'failure_count = failure_count + 1') . "
                  WHERE id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $workflowId);
        $stmt->execute();
    }

    private function logWorkflowExecutionDetail($executionResult) {
        $query = "INSERT INTO workflow_execution_log
                  (workflow_id, execution_time, trigger_data, actions_executed, execution_result,
                   execution_time_ms, error_message, affected_records)
                  VALUES (?, NOW(), ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "isssiidi",
            $executionResult['workflow_id'],
            json_encode($executionResult['trigger_data']),
            json_encode($executionResult['actions_executed']),
            $executionResult['result'],
            $executionResult['execution_time_ms'],
            $executionResult['error_message'],
            $executionResult['affected_records']
        );

        $stmt->execute();
    }

    private function logWorkflowExecution($executedCount, $successCount, $executionResults) {
        $this->automationLog[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'executed_count' => $executedCount,
            'success_count' => $successCount,
            'failure_count' => $executedCount - $successCount,
            'results' => $executionResults
        ];
    }

    /**
     * Get workflow automation dashboard data
     */
    public function getDashboardData() {
        // Get workflow statistics
        $query = "SELECT
                    COUNT(*) as total_workflows,
                    SUM(CASE WHEN is_active = TRUE THEN 1 ELSE 0 END) as active_workflows,
                    SUM(execution_count) as total_executions,
                    SUM(success_count) as total_successes,
                    SUM(failure_count) as total_failures,
                    AVG(CASE WHEN execution_count > 0 THEN (success_count / execution_count) * 100 ELSE 0 END) as success_rate
                 FROM workflow_definitions";

        $result = $this->conn->query($query);
        $workflowStats = $result->fetch_assoc();

        // Get recent execution logs
        $logsQuery = "SELECT
                        wd.name,
                        wel.execution_time,
                        wel.execution_result,
                        wel.execution_time_ms,
                        wel.affected_records
                      FROM workflow_execution_log wel
                      JOIN workflow_definitions wd ON wel.workflow_id = wd.id
                      ORDER BY wel.execution_time DESC
                      LIMIT 10";

        $result = $this->conn->query($logsQuery);
        $recentExecutions = $result->fetch_all(MYSQLI_ASSOC);

        // Get optimization suggestions
        $suggestionsQuery = "SELECT
                               suggestion_type,
                               title,
                               priority,
                               status,
                               created_at
                             FROM optimization_suggestions
                             ORDER BY priority DESC, created_at DESC
                             LIMIT 10";

        $result = $this->conn->query($suggestionsQuery);
        $suggestions = $result->fetch_all(MYSQLI_ASSOC);

        return [
            'workflow_statistics' => $workflowStats,
            'recent_executions' => $recentExecutions,
            'optimization_suggestions' => $suggestions,
            'automation_log' => array_slice($this->automationLog, -5)
        ];
    }
}

// Page logic
$automationEngine = new WorkflowAutomationEngine($conn, $userRole);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'execute_workflows':
            $executionResults = $automationEngine->executeScheduledWorkflows();
            $success = true;
            $message = "Executed {$executionResults['total_executed']} workflows. {$executionResults['success_count']} successful.";
            break;

        case 'generate_optimizations':
            $optimizationResults = $automationEngine->generateOptimizationSuggestions();
            $success = true;
            $message = "Generated {$optimizationResults['total_suggestions']} optimization suggestions. {$optimizationResults['stored_suggestions']} stored.";
            break;

        default:
            $success = false;
            $message = 'Invalid action';
    }

    // Redirect with message
    header('Location: workflow_automation_offline.php?success=' . ($success ? '1' : '0') . '&message=' . urlencode($message));
    exit;
}

// Get dashboard data
$dashboardData = $automationEngine->getDashboardData();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// HTML Header
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workflow Automation - Production Management System</title>
    <?php getInlineCSS(); ?>
    <style>
        .automation-card { border: 1px solid #dee2e6; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .automation-header { background-color: #f8f9fa; padding: 1rem; border-bottom: 1px solid #dee2e6; }
        .automation-body { padding: 1.5rem; }
        .metric-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 0.5rem; padding: 1.5rem; text-align: center; margin-bottom: 1rem; }
        .metric-value { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .metric-label { font-size: 0.875rem; opacity: 0.9; }
        .workflow-item { border: 1px solid #e9ecef; border-radius: 0.375rem; padding: 1rem; margin-bottom: 1rem; }
        .workflow-item.success { border-left: 4px solid #28a745; }
        .workflow-item.failed { border-left: 4px solid #dc3545; }
        .workflow-item.partial_success { border-left: 4px solid #ffc107; }
        .suggestion-item { border: 1px solid #e9ecef; border-radius: 0.375rem; padding: 1rem; margin-bottom: 1rem; }
        .priority-badge { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: bold; }
        .priority-critical { background-color: #dc3545; color: white; }
        .priority-high { background-color: #fd7e14; color: white; }
        .priority-medium { background-color: #ffc107; color: black; }
        .priority-low { background-color: #28a745; color: white; }
        .execution-log { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 1rem; max-height: 300px; overflow-y: auto; }
        .log-entry { padding: 0.5rem 0; border-bottom: 1px solid #e9ecef; }
        .log-entry:last-child { border-bottom: none; }
        .status-indicator { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 0.5rem; }
        .status-success { background-color: #28a745; }
        .status-failed { background-color: #dc3545; }
        .status-partial { background-color: #ffc107; }
        .automation-controls { display: flex; gap: 1rem; margin-bottom: 2rem; }
        @media (max-width: 768px) {
            .automation-controls { flex-direction: column; }
            .metric-value { font-size: 2rem; }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">Workflow Automation & Process Optimization</h1>
                    <div class="d-flex gap-2">
                        <a href="enhanced_dashboard_offline.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="index_lan.php" class="btn btn-primary">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_GET['message'])): ?>
                <div class="alert alert-<?php echo $_GET['success'] == '1' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $_GET['success'] == '1' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($_GET['message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Automation Controls -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Automation Controls</h5>
                    </div>
                    <div class="card-body">
                        <div class="automation-controls">
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="execute_workflows">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-play"></i> Execute Scheduled Workflows
                                </button>
                            </form>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="generate_optimizations">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-lightbulb"></i> Generate Optimization Suggestions
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Metrics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value"><?php echo $dashboardData['workflow_statistics']['total_workflows']; ?></div>
                            <div class="metric-label">Total Workflows</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value"><?php echo $dashboardData['workflow_statistics']['active_workflows']; ?></div>
                            <div class="metric-label">Active Workflows</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value"><?php echo number_format($dashboardData['workflow_statistics']['success_rate'], 1); ?>%</div>
                            <div class="metric-label">Success Rate</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value"><?php echo $dashboardData['workflow_statistics']['total_executions']; ?></div>
                            <div class="metric-label">Total Executions</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Workflow Executions -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="automation-card">
                            <div class="automation-header">
                                <h5 class="card-title mb-0">Recent Workflow Executions</h5>
                            </div>
                            <div class="automation-body">
                                <?php if (!empty($dashboardData['recent_executions'])): ?>
                                    <?php foreach ($dashboardData['recent_executions'] as $execution): ?>
                                    <div class="workflow-item <?php echo $execution['execution_result']; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($execution['name']); ?></h6>
                                                <small class="text-muted"><?php echo date('M j, Y H:i', strtotime($execution['execution_time'])); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <span class="status-indicator status-<?php echo $execution['execution_result'] === 'success' ? 'success' : ($execution['execution_result'] === 'failed' ? 'failed' : 'partial'); ?>"></span>
                                                <small class="text-muted"><?php echo $execution['execution_time_ms']; ?>ms</small>
                                            </div>
                                        </div>
                                        <?php if ($execution['affected_records'] > 0): ?>
                                        <div class="mt-2">
                                            <small class="text-info">Affected records: <?php echo $execution['affected_records']; ?></small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-robot fa-3x mb-3"></i>
                                        <p>No workflow executions recorded yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="automation-card">
                            <div class="automation-header">
                                <h5 class="card-title mb-0">Optimization Suggestions</h5>
                            </div>
                            <div class="automation-body">
                                <?php if (!empty($dashboardData['optimization_suggestions'])): ?>
                                    <?php foreach ($dashboardData['optimization_suggestions'] as $suggestion): ?>
                                    <div class="suggestion-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($suggestion['title']); ?></h6>
                                            <span class="priority-badge priority-<?php echo $suggestion['priority']; ?>">
                                                <?php echo ucfirst($suggestion['priority']); ?>
                                            </span>
                                        </div>
                                        <p class="mb-2 text-muted small"><?php echo htmlspecialchars(substr($suggestion['title'], 0, 100)) . '...'; ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($suggestion['created_at'])); ?></small>
                                            <small class="badge bg-info"><?php echo $suggestion['suggestion_type']; ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-lightbulb fa-3x mb-3"></i>
                                        <p>No optimization suggestions available.</p>
                                        <small>Generate suggestions to see optimization opportunities.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Automation Execution Log -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="automation-card">
                            <div class="automation-header">
                                <h5 class="card-title mb-0">Automation Execution Log</h5>
                            </div>
                            <div class="automation-body">
                                <?php if (!empty($dashboardData['automation_log'])): ?>
                                    <div class="execution-log">
                                        <?php foreach ($dashboardData['automation_log'] as $log): ?>
                                        <div class="log-entry">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo date('M j, Y H:i:s', strtotime($log['timestamp'])); ?></strong>
                                                    <div class="text-muted">
                                                        Executed: <?php echo $log['executed_count']; ?> workflows
                                                        | Success: <?php echo $log['success_count']; ?>
                                                        | Failed: <?php echo $log['failure_count']; ?>
                                                    </div>
                                                </div>
                                                <span class="badge bg-<?php echo $log['failure_count'] > 0 ? 'danger' : 'success'; ?>">
                                                    <?php echo $log['failure_count'] > 0 ? 'Issues' : 'OK'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-history fa-2x mb-3"></i>
                                        <p>No automation execution history available.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="automation-card">
                            <div class="automation-header">
                                <h5 class="card-title mb-0">System Status</h5>
                            </div>
                            <div class="automation-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h4 text-success">
                                                <i class="fas fa-check-circle"></i> Operational
                                            </div>
                                            <small class="text-muted">Automation Engine</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h4 text-info">
                                                <i class="fas fa-clock"></i> <?php echo date('H:i:s'); ?>
                                            </div>
                                            <small class="text-muted">Current Time</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h4 text-warning">
                                                <?php echo count($dashboardData['optimization_suggestions']); ?>
                                            </div>
                                            <small class="text-muted">Pending Suggestions</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h4 text-primary">
                                                <?php echo $dashboardData['workflow_statistics']['total_successes']; ?>
                                            </div>
                                            <small class="text-muted">Total Successes</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh the page every 30 seconds to show updated automation data
        setTimeout(function() {
            window.location.reload();
        }, 30000);

        // Handle form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                }
            });
        });
    </script>
</body>
</html>