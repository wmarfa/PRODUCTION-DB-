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
        error_log('CSRF validation failed in digital_twin_simulator.php');
        die('Security validation failed');
    }
}

// Authentication and Authorization
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    header('Location: index_lan.php');
    exit;
}

// Check permissions (Manager, Executive, and Admin roles)
if (!in_array($_SESSION['user_role'], ['manager', 'executive', 'admin'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied. You do not have permission to access digital twin simulator.');
}

/**
 * Digital Twin Simulation Engine
 * Creates virtual replicas of production lines for scenario testing and optimization
 */
class DigitalTwinSimulator {
    private $conn;
    private $userRole;
    private $simulationEngine;
    private $digitalTwins = [];
    private $scenarios = [];

    public function __construct($conn, $userRole) {
        $this->conn = $conn;
        $this->userRole = $userRole;
        $this->initializeDigitalTwinDatabase();
        $this->loadDigitalTwins();
        $this->simulationEngine = new SimulationEngine($conn);
    }

    /**
     * Initialize digital twin database tables
     */
    private function initializeDigitalTwinDatabase() {
        // Create digital twins table
        $createTwinsTable = "CREATE TABLE IF NOT EXISTS digital_twins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            twin_name VARCHAR(255) NOT NULL,
            description TEXT,
            line_shift VARCHAR(50) NOT NULL,
            twin_type ENUM('production_line', 'workstation', 'equipment', 'process_cell', 'entire_facility') NOT NULL,
            configuration JSON NOT NULL,
            parameters JSON NOT NULL,
            state_variables JSON,
            calibration_data JSON,
            sync_status ENUM('synced', 'pending', 'error') DEFAULT 'synced',
            last_sync TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_twin_type (twin_type),
            INDEX idx_line_shift (line_shift),
            INDEX idx_sync_status (sync_status),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createTwinsTable);

        // Create simulation scenarios table
        $createScenariosTable = "CREATE TABLE IF NOT EXISTS simulation_scenarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scenario_name VARCHAR(255) NOT NULL,
            description TEXT,
            scenario_type ENUM('what_if', 'optimization', 'risk_assessment', 'capacity_planning', 'process_improvement') NOT NULL,
            target_twins JSON NOT NULL,
            input_parameters JSON NOT NULL,
            expected_outcomes JSON,
            duration_hours DECIMAL(8,2),
            simulation_speed ENUM('real_time', 'accelerated', 'variable') DEFAULT 'real_time',
            status ENUM('draft', 'running', 'completed', 'failed', 'paused') DEFAULT 'draft',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_scenario_type (scenario_type),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createScenariosTable);

        // Create simulation runs table
        $createRunsTable = "CREATE TABLE IF NOT EXISTS simulation_runs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scenario_id INT NOT NULL,
            run_name VARCHAR(255) NOT NULL,
            run_parameters JSON NOT NULL,
            start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            end_time TIMESTAMP NULL,
            duration_seconds INT NULL,
            status ENUM('running', 'completed', 'failed', 'cancelled') NOT NULL,
            progress_percentage DECIMAL(5,2) DEFAULT 0,
            current_simulation_time TIMESTAMP NULL,
            results JSON,
            performance_metrics JSON,
            key_events JSON,
            created_by INT NOT NULL,
            FOREIGN KEY (scenario_id) REFERENCES simulation_scenarios(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_scenario_id (scenario_id),
            INDEX idx_status (status),
            INDEX idx_start_time (start_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createRunsTable);

        // Create simulation results table
        $createResultsTable = "CREATE TABLE IF NOT EXISTS simulation_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            run_id INT NOT NULL,
            timestamp_step INT NOT NULL,
            simulated_timestamp TIMESTAMP NOT NULL,
            twin_id INT NOT NULL,
            state_snapshot JSON NOT NULL,
            metrics JSON NOT NULL,
            events JSON,
            performance_data JSON,
            FOREIGN KEY (run_id) REFERENCES simulation_runs(id) ON DELETE CASCADE,
            FOREIGN KEY (twin_id) REFERENCES digital_twins(id) ON DELETE CASCADE,
            INDEX idx_run_id (run_id),
            INDEX idx_twin_id (twin_id),
            INDEX idx_timestamp_step (timestamp_step),
            INDEX idx_simulated_timestamp (simulated_timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createResultsTable);

        // Create digital twin calibration table
        $createCalibrationTable = "CREATE TABLE IF NOT EXISTS twin_calibration (
            id INT AUTO_INCREMENT PRIMARY KEY,
            twin_id INT NOT NULL,
            calibration_date DATE NOT NULL,
            actual_performance JSON NOT NULL,
            simulated_performance JSON NOT NULL,
            accuracy_score DECIMAL(5,4),
            calibration_adjustments JSON,
            next_calibration_date DATE,
            calibration_notes TEXT,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (twin_id) REFERENCES digital_twins(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_twin_id (twin_id),
            INDEX idx_calibration_date (calibration_date),
            INDEX idx_accuracy_score (accuracy_score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createCalibrationTable);

        // Create scenario templates table
        $createTemplatesTable = "CREATE TABLE IF NOT EXISTS scenario_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_name VARCHAR(255) NOT NULL,
            description TEXT,
            category ENUM('production', 'quality', 'maintenance', 'capacity', 'efficiency', 'risk') NOT NULL,
            template_parameters JSON NOT NULL,
            expected_inputs JSON,
            setup_instructions TEXT,
            is_public BOOLEAN DEFAULT TRUE,
            usage_count INT DEFAULT 0,
            rating DECIMAL(3,2) DEFAULT 0.00,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_category (category),
            INDEX idx_is_public (is_public),
            INDEX idx_usage_count (usage_count)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createTemplatesTable);
    }

    /**
     * Load existing digital twins
     */
    private function loadDigitalTwins() {
        $query = "SELECT * FROM digital_twins WHERE is_active = TRUE ORDER BY twin_name";
        $result = $this->conn->query($query);

        while ($row = $result->fetch_assoc()) {
            $this->digitalTwins[] = $row;
        }
    }

    /**
     * Create a new digital twin
     */
    public function createDigitalTwin($twinData) {
        // Validate required fields
        $requiredFields = ['twin_name', 'line_shift', 'twin_type', 'configuration', 'parameters'];
        foreach ($requiredFields as $field) {
            if (empty($twinData[$field])) {
                throw new Exception("Required field '$field' is missing");
            }
        }

        // Get actual production data for baseline
        $baselineData = $this->getBaselineProductionData($twinData['line_shift']);

        // Create comprehensive configuration
        $configuration = [
            'line_shift' => $twinData['line_shift'],
            'twin_type' => $twinData['twin_type'],
            'production_capacity' => $baselineData['capacity'] ?? 1000,
            'current_efficiency' => $baselineData['efficiency'] ?? 0.8,
            'manning_level' => $baselineData['manning_level'] ?? 10,
            'process_category' => $baselineData['process_category'] ?? 'general',
            'equipment_specs' => $baselineData['equipment_specs'] ?? [],
            'quality_parameters' => $baselineData['quality_parameters'] ?? []
        ];

        // Initialize state variables
        $stateVariables = [
            'current_output' => 0,
            'current_efficiency' => $configuration['current_efficiency'],
            'current_downtime' => 0,
            'quality_rate' => 0.95,
            'resource_utilization' => 0.8,
            'energy_consumption' => 0,
            'material_usage' => 0,
            'worker_fatigue' => 0,
            'equipment_wear' => 0
        ];

        $query = "INSERT INTO digital_twins
                  (twin_name, description, line_shift, twin_type, configuration, parameters, state_variables, created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sssssssi",
            $twinData['twin_name'],
            $twinData['description'] ?? '',
            $twinData['line_shift'],
            $twinData['twin_type'],
            json_encode($configuration),
            json_encode($twinData['parameters']),
            json_encode($stateVariables),
            $_SESSION['user_id']
        );

        if ($stmt->execute()) {
            $twinId = $this->conn->insert_id;

            // Create initial calibration record
            $this->createInitialCalibration($twinId, $baselineData);

            return [
                'success' => true,
                'twin_id' => $twinId,
                'message' => 'Digital twin created successfully'
            ];
        } else {
            throw new Exception("Failed to create digital twin: " . $stmt->error);
        }
    }

    /**
     * Run a simulation scenario
     */
    public function runSimulation($scenarioId, $runParameters = []) {
        // Get scenario details
        $query = "SELECT * FROM simulation_scenarios WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $scenarioId);
        $stmt->execute();
        $scenario = $stmt->get_result()->fetch_assoc();

        if (!$scenario) {
            throw new Exception("Scenario not found");
        }

        // Create simulation run
        $runQuery = "INSERT INTO simulation_runs
                     (scenario_id, run_name, run_parameters, status, created_by)
                     VALUES (?, ?, ?, 'running', ?)";

        $runName = $scenario['scenario_name'] . ' - Run ' . date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare($runQuery);
        $stmt->bind_param("issi", $scenarioId, $runName, json_encode($runParameters), $_SESSION['user_id']);
        $stmt->execute();

        $runId = $this->conn->insert_id;

        try {
            // Execute simulation
            $results = $this->simulationEngine->executeSimulation($scenario, $runParameters);

            // Update run with results
            $updateQuery = "UPDATE simulation_runs
                           SET status = 'completed', end_time = NOW(), duration_seconds = ?, results = ?
                           WHERE id = ?";

            $stmt = $this->conn->prepare($updateQuery);
            $stmt->bind_param("isi", $results['duration'], json_encode($results), $runId);
            $stmt->execute();

            // Store detailed results
            $this->storeSimulationResults($runId, $results);

            return [
                'success' => true,
                'run_id' => $runId,
                'results' => $results,
                'message' => 'Simulation completed successfully'
            ];

        } catch (Exception $e) {
            // Update run status to failed
            $updateQuery = "UPDATE simulation_runs SET status = 'failed', end_time = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($updateQuery);
            $stmt->bind_param("i", $runId);
            $stmt->execute();

            throw $e;
        }
    }

    /**
     * Create optimization scenario
     */
    public function createOptimizationScenario($twinId, $optimizationGoals) {
        $twinData = $this->getDigitalTwin($twinId);

        $scenario = [
            'scenario_name' => 'Optimization: ' . $twinData['twin_name'],
            'description' => 'Optimization scenario for ' . $twinData['twin_name'],
            'scenario_type' => 'optimization',
            'target_twins' => [$twinId],
            'input_parameters' => [
                'optimization_goals' => $optimizationGoals,
                'optimization_method' => 'genetic_algorithm',
                'iterations' => 100,
                'population_size' => 50,
                'mutation_rate' => 0.1,
                'crossover_rate' => 0.8
            ],
            'expected_outcomes' => [
                'improved_efficiency' => true,
                'reduced_costs' => true,
                'increased_throughput' => false
            ],
            'duration_hours' => 2
        ];

        return $this->createScenario($scenario);
    }

    /**
     * Create what-if scenario
     */
    public function createWhatIfScenario($twinIds, $whatIfConditions) {
        $scenario = [
            'scenario_name' => 'What-If Analysis',
            'description' => 'What-if scenario analysis for multiple production lines',
            'scenario_type' => 'what_if',
            'target_twins' => $twinIds,
            'input_parameters' => [
                'conditions' => $whatIfConditions,
                'simulation_period' => '7 days',
                'time_step' => '1 hour'
            ],
            'expected_outcomes' => [],
            'duration_hours' => 1
        ];

        return $this->createScenario($scenario);
    }

    /**
     * Get digital twin dashboard data
     */
    public function getDashboardData() {
        return [
            'digital_twins' => $this->getActiveTwins(),
            'recent_simulations' => $this->getRecentSimulations(),
            'simulation_templates' => $this->getScenarioTemplates(),
            'calibration_status' => $this->getCalibrationStatus(),
            'performance_metrics' => $this->getTwinPerformanceMetrics(),
            'optimization_opportunities' => $this->getOptimizationOpportunities()
        ];
    }

    /**
     * Helper Methods
     */

    private function getBaselineProductionData($lineShift) {
        $query = "SELECT
                     dp.actual_output,
                     dp.efficiency,
                     dp.machine_downtime,
                     dp.plan,
                     pl.daily_capacity,
                     pl.manning_level,
                     pl.process_category
                  FROM daily_performance dp
                  LEFT JOIN production_lines pl ON dp.line_shift LIKE CONCAT(pl.line_number, '_%')
                  WHERE dp.line_shift = ?
                  ORDER BY dp.date DESC
                  LIMIT 30";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $lineShift);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = $result->fetch_all(MYSQLI_ASSOC);

        if (empty($data)) {
            return [];
        }

        // Calculate averages
        $baseline = [
            'capacity' => $data[0]['daily_capacity'] ?? 1000,
            'efficiency' => array_sum(array_column($data, 'efficiency')) / count($data),
            'manning_level' => $data[0]['manning_level'] ?? 10,
            'process_category' => $data[0]['process_category'] ?? 'general',
            'avg_output' => array_sum(array_column($data, 'actual_output')) / count($data),
            'avg_plan' => array_sum(array_column($data, 'plan')) / count($data),
            'avg_downtime' => array_sum(array_column($data, 'machine_downtime')) / count($data)
        ];

        return $baseline;
    }

    private function createInitialCalibration($twinId, $baselineData) {
        $query = "INSERT INTO twin_calibration
                  (twin_id, calibration_date, actual_performance, simulated_performance, accuracy_score, next_calibration_date, created_by)
                  VALUES (?, CURDATE(), ?, ?, 1.0, DATE_ADD(CURDATE(), INTERVAL 1 MONTH), ?)";

        $actualPerformance = json_encode($baselineData);
        $simulatedPerformance = json_encode($baselineData); // Initially identical

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isssi", $twinId, $actualPerformance, $simulatedPerformance, 1.0, $_SESSION['user_id']);
        $stmt->execute();
    }

    private function createScenario($scenarioData) {
        $query = "INSERT INTO simulation_scenarios
                  (scenario_name, description, scenario_type, target_twins, input_parameters,
                   expected_outcomes, duration_hours, created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "ssssssdi",
            $scenarioData['scenario_name'],
            $scenarioData['description'],
            $scenarioData['scenario_type'],
            json_encode($scenarioData['target_twins']),
            json_encode($scenarioData['input_parameters']),
            json_encode($scenarioData['expected_outcomes']),
            $scenarioData['duration_hours'],
            $_SESSION['user_id']
        );

        if ($stmt->execute()) {
            return [
                'success' => true,
                'scenario_id' => $this->conn->insert_id,
                'message' => 'Scenario created successfully'
            ];
        } else {
            throw new Exception("Failed to create scenario: " . $stmt->error);
        }
    }

    private function getDigitalTwin($twinId) {
        $query = "SELECT * FROM digital_twins WHERE id = ? AND is_active = TRUE";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $twinId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function storeSimulationResults($runId, $results) {
        if (!isset($results['timeline']) || !isset($results['twins'])) {
            return;
        }

        // Store timeline results
        foreach ($results['timeline'] as $timeStep => $timeData) {
            foreach ($results['twins'] as $twinId => $twinData) {
                if (isset($timeData['twins'][$twinId])) {
                    $twinState = $timeData['twins'][$twinId];

                    $query = "INSERT INTO simulation_results
                              (run_id, timestamp_step, simulated_timestamp, twin_id, state_snapshot, metrics, events)
                              VALUES (?, ?, ?, ?, ?, ?, ?)";

                    $stmt = $this->conn->prepare($query);
                    $stmt->bind_param(
                        "iisisss",
                        $runId,
                        $timeStep,
                        $twinState['timestamp'],
                        $twinId,
                        json_encode($twinState['state'] ?? []),
                        json_encode($twinState['metrics'] ?? []),
                        json_encode($twinState['events'] ?? [])
                    );

                    $stmt->execute();
                }
            }
        }
    }

    private function getActiveTwins() {
        $query = "SELECT dt.*, COUNT(sr.id) as simulation_count
                  FROM digital_twins dt
                  LEFT JOIN simulation_scenarios sc ON JSON_CONTAINS(sc.target_twins, JSON_QUOTE(dt.id))
                  LEFT JOIN simulation_runs sr ON sc.id = sr.scenario_id
                  WHERE dt.is_active = TRUE
                  GROUP BY dt.id
                  ORDER BY dt.created_at DESC";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function getRecentSimulations() {
        $query = "SELECT sr.*, sc.scenario_name, sc.scenario_type, u.username
                  FROM simulation_runs sr
                  JOIN simulation_scenarios sc ON sr.scenario_id = sc.id
                  JOIN users u ON sr.created_by = u.id
                  ORDER BY sr.start_time DESC
                  LIMIT 10";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function getScenarioTemplates() {
        $query = "SELECT * FROM scenario_templates WHERE is_public = TRUE ORDER BY usage_count DESC, rating DESC LIMIT 10";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function getCalibrationStatus() {
        $query = "SELECT
                     dt.twin_name,
                     dt.sync_status,
                     tc.calibration_date,
                     tc.accuracy_score,
                     tc.next_calibration_date
                  FROM digital_twins dt
                  LEFT JOIN twin_calibration tc ON dt.id = tc.twin_id
                  LEFT JOIN (
                      SELECT twin_id, MAX(calibration_date) as max_date
                      FROM twin_calibration
                      GROUP BY twin_id
                  ) latest ON tc.twin_id = latest.twin_id AND tc.calibration_date = latest.max_date
                  WHERE dt.is_active = TRUE
                  ORDER BY dt.twin_name";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function getTwinPerformanceMetrics() {
        $query = "SELECT
                     dt.twin_name,
                     dt.line_shift,
                     sr.status,
                     COUNT(sr.id) as total_runs,
                     AVG(CASE WHEN sr.status = 'completed' THEN sr.duration_seconds END) as avg_duration,
                     MAX(sr.start_time) as last_run
                  FROM digital_twins dt
                  LEFT JOIN simulation_scenarios sc ON JSON_CONTAINS(sc.target_twins, JSON_QUOTE(dt.id))
                  LEFT JOIN simulation_runs sr ON sc.id = sr.scenario_id
                  WHERE dt.is_active = TRUE
                  GROUP BY dt.id
                  ORDER BY dt.twin_name";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function getOptimizationOpportunities() {
        $opportunities = [];

        // Get twins with low efficiency
        $query = "SELECT twin_name, configuration FROM digital_twins WHERE is_active = TRUE";
        $result = $this->conn->query($query);

        while ($twin = $result->fetch_assoc()) {
            $config = json_decode($twin['configuration'], true);

            if (isset($config['current_efficiency']) && $config['current_efficiency'] < 0.75) {
                $opportunities[] = [
                    'twin_name' => $twin['twin_name'],
                    'opportunity' => 'Low efficiency detected',
                    'current_value' => round($config['current_efficiency'] * 100, 1) . '%',
                    'potential_improvement' => '15-25%',
                    'recommendation' => 'Run optimization scenario to identify efficiency improvements'
                ];
            }
        }

        return $opportunities;
    }
}

/**
 * Simulation Engine - Core simulation logic
 */
class SimulationEngine {
    private $conn;
    private $timeStep = 3600; // 1 hour in seconds
    private $currentTime;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Execute simulation based on scenario parameters
     */
    public function executeSimulation($scenario, $runParameters = []) {
        $startTime = microtime(true);

        // Initialize simulation state
        $this->currentTime = new DateTime();
        $simulationDuration = isset($runParameters['duration_hours']) ?
            $runParameters['duration_hours'] : $scenario['duration_hours'];

        $targetTwins = json_decode($scenario['target_twins'], true);
        $inputParams = array_merge(json_decode($scenario['input_parameters'], true), $runParameters);

        // Load twin configurations
        $twins = [];
        foreach ($targetTwins as $twinId) {
            $twins[$twinId] = $this->loadTwinConfiguration($twinId);
        }

        // Initialize simulation results
        $results = [
            'scenario_id' => $scenario['id'],
            'scenario_name' => $scenario['scenario_name'],
            'scenario_type' => $scenario['scenario_type'],
            'start_time' => $this->currentTime->format('Y-m-d H:i:s'),
            'duration' => $simulationDuration,
            'twins' => $twins,
            'timeline' => [],
            'summary' => []
        ];

        // Run simulation based on scenario type
        switch ($scenario['scenario_type']) {
            case 'what_if':
                $results = $this->runWhatIfSimulation($results, $inputParams);
                break;
            case 'optimization':
                $results = $this->runOptimizationSimulation($results, $inputParams);
                break;
            case 'risk_assessment':
                $results = $this->runRiskAssessmentSimulation($results, $inputParams);
                break;
            case 'capacity_planning':
                $results = $this->runCapacityPlanningSimulation($results, $inputParams);
                break;
            default:
                $results = $this->runStandardSimulation($results, $inputParams);
        }

        $results['duration_seconds'] = round(microtime(true) - $startTime);
        $results['end_time'] = $this->currentTime->format('Y-m-d H:i:s');

        return $results;
    }

    /**
     * Run standard simulation
     */
    private function runStandardSimulation($results, $inputParams) {
        $totalSteps = $results['duration'] * 3600 / $this->timeStep;

        for ($step = 0; $step < $totalSteps; $step++) {
            $timeData = [
                'step' => $step,
                'timestamp' => $this->currentTime->format('Y-m-d H:i:s'),
                'twins' => []
            ];

            // Update each twin
            foreach ($results['twins'] as $twinId => &$twin) {
                $twinState = $this->updateTwinState($twin, $inputParams, $step);
                $timeData['twins'][$twinId] = $twinState;
                $twin = $twinState['state'];
            }

            $results['timeline'][] = $timeData;
            $this->currentTime->add(new DateInterval('PT' . ($this->timeStep / 3600) . 'H'));
        }

        // Generate summary
        $results['summary'] = $this->generateSimulationSummary($results);

        return $results;
    }

    /**
     * Run what-if simulation
     */
    private function runWhatIfSimulation($results, $inputParams) {
        if (!isset($inputParams['conditions'])) {
            return $this->runStandardSimulation($results, $inputParams);
        }

        $conditions = $inputParams['conditions'];
        $totalSteps = $results['duration'] * 3600 / $this->timeStep;

        // Apply what-if conditions at specified steps
        for ($step = 0; $step < $totalSteps; $step++) {
            $timeData = [
                'step' => $step,
                'timestamp' => $this->currentTime->format('Y-m-d H:i:s'),
                'twins' => [],
                'events' => []
            ];

            // Check for condition triggers
            $currentHour = $step * ($this->timeStep / 3600);
            foreach ($conditions as $condition) {
                if (isset($condition['trigger_time']) && $condition['trigger_time'] <= $currentHour) {
                    $this->applyCondition($condition, $results['twins'], $timeData);
                    $timeData['events'][] = [
                        'type' => 'condition_applied',
                        'condition' => $condition['name'] ?? 'Unknown',
                        'time' => $currentHour
                    ];
                }
            }

            // Update each twin
            foreach ($results['twins'] as $twinId => &$twin) {
                $twinState = $this->updateTwinState($twin, $inputParams, $step);
                $timeData['twins'][$twinId] = $twinState;
                $twin = $twinState['state'];
            }

            $results['timeline'][] = $timeData;
            $this->currentTime->add(new DateInterval('PT' . ($this->timeStep / 3600) . 'H'));
        }

        $results['summary'] = $this->generateSimulationSummary($results);
        return $results;
    }

    /**
     * Run optimization simulation using genetic algorithm
     */
    private function runOptimizationSimulation($results, $inputParams) {
        $optimizationGoals = $inputParams['optimization_goals'] ?? [];
        $populationSize = $inputParams['population_size'] ?? 50;
        $generations = $inputParams['iterations'] ?? 100;

        $bestSolution = null;
        $bestFitness = -999999;

        // Generate initial population
        $population = $this->generateInitialPopulation($populationSize, $results['twins']);

        // Run genetic algorithm
        for ($generation = 0; $generation < $generations; $generation++) {
            // Evaluate fitness
            foreach ($population as &$individual) {
                $fitness = $this->evaluateFitness($individual, $optimizationGoals);
                $individual['fitness'] = $fitness;

                if ($fitness > $bestFitness) {
                    $bestFitness = $fitness;
                    $bestSolution = $individual;
                }
            }

            // Selection, crossover, and mutation
            $population = $this->geneticAlgorithmStep($population, $inputParams);

            // Progress tracking
            if ($generation % 10 === 0) {
                $results['timeline'][] = [
                    'generation' => $generation,
                    'best_fitness' => $bestFitness,
                    'avg_fitness' => array_sum(array_column($population, 'fitness')) / count($population)
                ];
            }
        }

        $results['optimization_results'] = [
            'best_solution' => $bestSolution,
            'best_fitness' => $bestFitness,
            'generations' => $generations
        ];

        // Run final simulation with best parameters
        $finalParams = array_merge($inputParams, $bestSolution['parameters'] ?? []);
        $results = $this->runStandardSimulation($results, $finalParams);
        $results['optimization_applied'] = $bestSolution;

        return $results;
    }

    /**
     * Run risk assessment simulation
     */
    private function runRiskAssessmentSimulation($results, $inputParams) {
        $riskScenarios = $inputParams['risk_scenarios'] ?? [];
        $totalSteps = $results['duration'] * 3600 / $this->timeStep;

        for ($step = 0; $step < $totalSteps; $step++) {
            $timeData = [
                'step' => $step,
                'timestamp' => $this->currentTime->format('Y-m-d H:i:s'),
                'twins' => [],
                'risk_events' => []
            ];

            // Random risk events based on probability
            foreach ($riskScenarios as $risk) {
                if (rand(0, 100) < ($risk['probability'] ?? 10)) {
                    $this->applyRiskEvent($risk, $results['twins'], $timeData);
                    $timeData['risk_events'][] = [
                        'risk_type' => $risk['type'],
                        'impact' => $risk['impact'],
                        'timestamp' => $this->currentTime->format('Y-m-d H:i:s')
                    ];
                }
            }

            // Update each twin
            foreach ($results['twins'] as $twinId => &$twin) {
                $twinState = $this->updateTwinState($twin, $inputParams, $step);
                $timeData['twins'][$twinId] = $twinState;
                $twin = $twinState['state'];
            }

            $results['timeline'][] = $timeData;
            $this->currentTime->add(new DateInterval('PT' . ($this->timeStep / 3600) . 'H'));
        }

        $results['summary'] = $this->generateSimulationSummary($results);
        $results['risk_assessment'] = $this->generateRiskAssessment($results);

        return $results;
    }

    /**
     * Run capacity planning simulation
     */
    private function runCapacityPlanningSimulation($results, $inputParams) {
        $capacityScenarios = $inputParams['capacity_scenarios'] ?? [];
        $totalSteps = $results['duration'] * 3600 / $this->timeStep;

        for ($step = 0; $step < $totalSteps; $step++) {
            $timeData = [
                'step' => $step,
                'timestamp' => $this->currentTime->format('Y-m-d H:i:s'),
                'twins' => []
            ];

            // Apply capacity scenarios
            foreach ($capacityScenarios as $scenario) {
                if (isset($scenario['start_hour']) && $scenario['start_hour'] <= ($step * $this->timeStep / 3600)) {
                    $this->applyCapacityScenario($scenario, $results['twins'], $timeData);
                }
            }

            // Update each twin
            foreach ($results['twins'] as $twinId => &$twin) {
                $twinState = $this->updateTwinState($twin, $inputParams, $step);
                $timeData['twins'][$twinId] = $twinState;
                $twin = $twinState['state'];
            }

            $results['timeline'][] = $timeData;
            $this->currentTime->add(new DateInterval('PT' . ($this->timeStep / 3600) . 'H'));
        }

        $results['summary'] = $this->generateSimulationSummary($results);
        $results['capacity_analysis'] = $this->generateCapacityAnalysis($results);

        return $results;
    }

    /**
     * Update twin state for one simulation step
     */
    private function updateTwinState($twin, $inputParams, $step) {
        $state = $twin['state'];
        $config = json_decode($twin['configuration'], true);
        $params = json_decode($twin['parameters'], true);

        // Calculate production based on efficiency and capacity
        $baseEfficiency = $config['current_efficiency'] ?? 0.8;
        $capacity = $config['production_capacity'] ?? 1000;

        // Apply input parameters
        if (isset($inputParams['efficiency_modifier'])) {
            $baseEfficiency *= $inputParams['efficiency_modifier'];
        }

        if (isset($inputParams['capacity_modifier'])) {
            $capacity *= $inputParams['capacity_modifier'];
        }

        // Simulate production with some randomness
        $randomFactor = 0.95 + (rand(0, 100) / 1000); // 95-105%
        $outputRate = ($capacity / 8) * $baseEfficiency * $randomFactor; // Per hour
        $currentOutput = $state['current_output'] + $outputRate;

        // Update efficiency (simulate degradation over time)
        $efficiencyDrift = -0.001 + (rand(-50, 50) / 100000); // Small random change
        $currentEfficiency = $state['current_efficiency'] + $efficiencyDrift;
        $currentEfficiency = max(0.1, min(1.0, $currentEfficiency));

        // Update downtime (simulate random failures)
        $downtimeProbability = $state['equipment_wear'] * 0.01;
        if (rand(0, 1000) < ($downtimeProbability * 1000)) {
            $currentDowntime = $state['current_downtime'] + (rand(10, 60)); // 10-60 minutes
        } else {
            $currentDowntime = max(0, $state['current_downtime'] - 5); // Recovery
        }

        // Update equipment wear
        $equipmentWear = min(1.0, $state['equipment_wear'] + 0.0001);

        // Update worker fatigue (8-hour shift simulation)
        $shiftHour = ($step % 8);
        $workerFatigue = $shiftHour > 6 ? min(1.0, $state['worker_fatigue'] + 0.05) :
                         max(0, $state['worker_fatigue'] - 0.02);

        // Calculate metrics
        $metrics = [
            'output_rate' => $outputRate,
            'cumulative_output' => $currentOutput,
            'efficiency' => $currentEfficiency,
            'downtime_minutes' => $currentDowntime,
            'equipment_health' => (1 - $equipmentWear) * 100,
            'worker_fatigue' => $workerFatigue * 100,
            'quality_rate' => min(99, 95 + ($currentEfficiency * 4) - ($workerFatigue * 10)),
            'resource_utilization' => min(100, ($outputRate / ($capacity / 8)) * 100)
        ];

        // Update state
        $newState = array_merge($state, [
            'current_output' => $currentOutput,
            'current_efficiency' => $currentEfficiency,
            'current_downtime' => $currentDowntime,
            'equipment_wear' => $equipmentWear,
            'worker_fatigue' => $workerFatigue,
            'timestamp' => $this->currentTime->format('Y-m-d H:i:s')
        ]);

        return [
            'state' => $newState,
            'metrics' => $metrics,
            'events' => [] // Can be populated with specific events
        ];
    }

    /**
     * Apply what-if condition to twins
     */
    private function applyCondition($condition, &$twins, &$timeData) {
        foreach ($twins as $twinId => &$twin) {
            if (isset($condition['target_twins']) && !in_array($twinId, $condition['target_twins'])) {
                continue;
            }

            switch ($condition['type']) {
                case 'efficiency_change':
                    $twin['state']['current_efficiency'] *= ($condition['value'] ?? 1.0);
                    break;
                case 'capacity_change':
                    $config = json_decode($twin['configuration'], true);
                    $config['production_capacity'] *= ($condition['value'] ?? 1.0);
                    $twin['configuration'] = json_encode($config);
                    break;
                case 'downtime_event':
                    $twin['state']['current_downtime'] += ($condition['value'] ?? 30);
                    break;
                case 'resource_change':
                    $twin['state']['resource_utilization'] *= ($condition['value'] ?? 1.0);
                    break;
            }
        }
    }

    /**
     * Apply risk event to twins
     */
    private function applyRiskEvent($risk, &$twins, &$timeData) {
        foreach ($twins as $twinId => &$twin) {
            switch ($risk['type']) {
                case 'equipment_failure':
                    $twin['state']['current_downtime'] += ($risk['duration'] ?? 120);
                    $twin['state']['equipment_wear'] = min(1.0, $twin['state']['equipment_wear'] + 0.2);
                    break;
                case 'quality_issue':
                    $twin['state']['quality_rate'] *= 0.8;
                    break;
                case 'worker_shortage':
                    $twin['state']['resource_utilization'] *= 0.7;
                    $twin['state']['current_efficiency'] *= 0.8;
                    break;
                case 'supply_delay':
                    $twin['state']['current_efficiency'] *= 0.6;
                    break;
            }
        }
    }

    /**
     * Apply capacity scenario to twins
     */
    private function applyCapacityScenario($scenario, &$twins, &$timeData) {
        foreach ($twins as $twinId => &$twin) {
            $config = json_decode($twin['configuration'], true);

            switch ($scenario['action']) {
                case 'increase_capacity':
                    $config['production_capacity'] *= ($scenario['multiplier'] ?? 1.2);
                    break;
                case 'add_shift':
                    $config['production_capacity'] *= 1.5;
                    break;
                case 'upgrade_equipment':
                    $config['current_efficiency'] *= 1.1;
                    break;
                case 'reduce_downtime':
                    $twin['state']['current_downtime'] *= 0.5;
                    break;
            }

            $twin['configuration'] = json_encode($config);
        }
    }

    /**
     * Genetic Algorithm Methods
     */

    private function generateInitialPopulation($size, $twins) {
        $population = [];

        for ($i = 0; $i < $size; $i++) {
            $individual = [
                'parameters' => [
                    'efficiency_modifier' => 0.8 + (rand(0, 100) / 500), // 0.8 - 1.0
                    'capacity_modifier' => 0.9 + (rand(0, 100) / 1000), // 0.9 - 1.0
                    'quality_target' => 0.95 + (rand(0, 100) / 2000), // 0.95 - 1.0
                    'resource_optimization' => rand(0, 100) / 100 // 0.0 - 1.0
                ],
                'fitness' => 0
            ];
            $population[] = $individual;
        }

        return $population;
    }

    private function evaluateFitness($individual, $goals) {
        $fitness = 0;

        if (isset($goals['maximize_efficiency']) && $goals['maximize_efficiency']) {
            $fitness += $individual['parameters']['efficiency_modifier'] * 100;
        }

        if (isset($goals['maximize_capacity']) && $goals['maximize_capacity']) {
            $fitness += $individual['parameters']['capacity_modifier'] * 50;
        }

        if (isset($goals['maximize_quality']) && $goals['maximize_quality']) {
            $fitness += ($individual['parameters']['quality_target'] - 0.95) * 500;
        }

        if (isset($goals['optimize_resources']) && $goals['optimize_resources']) {
            $fitness += $individual['parameters']['resource_optimization'] * 30;
        }

        return $fitness;
    }

    private function geneticAlgorithmStep($population, $params) {
        $newPopulation = [];
        $populationSize = count($population);

        // Keep best individuals (elitism)
        usort($population, function($a, $b) {
            return $b['fitness'] <=> $a['fitness'];
        });

        $elitismCount = max(2, (int)($populationSize * 0.1));
        for ($i = 0; $i < $elitismCount; $i++) {
            $newPopulation[] = $population[$i];
        }

        // Generate new individuals
        while (count($newPopulation) < $populationSize) {
            // Selection (tournament)
            $parent1 = $this->tournamentSelection($population, 3);
            $parent2 = $this->tournamentSelection($population, 3);

            // Crossover
            $child = $this->crossover($parent1, $parent2);

            // Mutation
            $child = $this->mutate($child, $params['mutation_rate'] ?? 0.1);

            $newPopulation[] = $child;
        }

        return $newPopulation;
    }

    private function tournamentSelection($population, $k) {
        $selected = [];
        for ($i = 0; $i < $k; $i++) {
            $selected[] = $population[rand(0, count($population) - 1)];
        }

        usort($selected, function($a, $b) {
            return $b['fitness'] <=> $a['fitness'];
        });

        return $selected[0];
    }

    private function crossover($parent1, $parent2) {
        $child = [
            'parameters' => [],
            'fitness' => 0
        ];

        foreach ($parent1['parameters'] as $key => $value) {
            $child['parameters'][$key] = rand(0, 1) ? $value : $parent2['parameters'][$key];
        }

        return $child;
    }

    private function mutate($individual, $mutationRate) {
        foreach ($individual['parameters'] as $key => &$value) {
            if (rand(0, 100) < ($mutationRate * 100)) {
                $mutationAmount = (rand(-10, 10) / 100); // -10% to +10%
                $value *= (1 + $mutationAmount);
                $value = max(0.1, min(2.0, $value)); // Keep within bounds
            }
        }

        return $individual;
    }

    /**
     * Load twin configuration from database
     */
    private function loadTwinConfiguration($twinId) {
        $query = "SELECT * FROM digital_twins WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $twinId);
        $stmt->execute();
        $twin = $stmt->get_result()->fetch_assoc();

        if (!$twin) {
            throw new Exception("Digital twin not found: $twinId");
        }

        return $twin;
    }

    /**
     * Generate simulation summary
     */
    private function generateSimulationSummary($results) {
        $summary = [
            'total_simulation_time' => count($results['timeline']) . ' hours',
            'total_output' => 0,
            'average_efficiency' => 0,
            'total_downtime' => 0,
            'peak_performance' => 0,
            'quality_metrics' => []
        ];

        $efficiencySum = 0;
        $outputSum = 0;
        $downtimeSum = 0;
        $peakEfficiency = 0;

        foreach ($results['timeline'] as $timeStep) {
            foreach ($timeStep['twins'] as $twinState) {
                if (isset($twinState['metrics'])) {
                    $metrics = $twinState['metrics'];
                    $efficiencySum += $metrics['efficiency'] ?? 0;
                    $outputSum += $metrics['output_rate'] ?? 0;
                    $downtimeSum += $metrics['downtime_minutes'] ?? 0;
                    $peakEfficiency = max($peakEfficiency, $metrics['efficiency'] ?? 0);
                }
            }
        }

        $totalSteps = count($results['timeline']);
        $totalTwins = count($results['twins']);

        if ($totalSteps > 0 && $totalTwins > 0) {
            $summary['average_efficiency'] = ($efficiencySum / ($totalSteps * $totalTwins)) * 100;
            $summary['total_output'] = $outputSum;
            $summary['total_downtime'] = $downtimeSum;
            $summary['peak_performance'] = $peakEfficiency * 100;
        }

        return $summary;
    }

    /**
     * Generate risk assessment summary
     */
    private function generateRiskAssessment($results) {
        $riskEvents = [];
        $riskImpacts = [];

        foreach ($results['timeline'] as $timeStep) {
            if (isset($timeStep['risk_events'])) {
                foreach ($timeStep['risk_events'] as $event) {
                    $riskEvents[] = $event;
                    if (!isset($riskImpacts[$event['risk_type']])) {
                        $riskImpacts[$event['risk_type']] = ['count' => 0, 'total_impact' => 0];
                    }
                    $riskImpacts[$event['risk_type']]['count']++;
                    $riskImpacts[$event['risk_type']]['total_impact'] += $event['impact'] ?? 1;
                }
            }
        }

        return [
            'total_risk_events' => count($riskEvents),
            'risk_events' => $riskEvents,
            'risk_impacts' => $riskImpacts,
            'risk_score' => $this->calculateRiskScore($riskImpacts)
        ];
    }

    /**
     * Generate capacity analysis
     */
    private function generateCapacityAnalysis($results) {
        $capacityUtilization = [];
        $peakDemand = 0;
        $averageDemand = 0;

        foreach ($results['timeline'] as $timeStep) {
            foreach ($timeStep['twins'] as $twinState) {
                if (isset($twinState['metrics'])) {
                    $utilization = $twinState['metrics']['resource_utilization'] ?? 0;
                    $capacityUtilization[] = $utilization;
                    $peakDemand = max($peakDemand, $utilization);
                    $averageDemand += $utilization;
                }
            }
        }

        if (!empty($capacityUtilization)) {
            $averageDemand /= count($capacityUtilization);
        }

        return [
            'peak_utilization' => $peakDemand,
            'average_utilization' => $averageDemand,
            'utilization_variance' => $this->calculateVariance($capacityUtilization),
            'capacity_recommendations' => $this->generateCapacityRecommendations($peakDemand, $averageDemand)
        ];
    }

    /**
     * Helper calculation methods
     */

    private function calculateRiskScore($riskImpacts) {
        $totalImpact = 0;
        foreach ($riskImpacts as $impact) {
            $totalImpact += $impact['total_impact'];
        }
        return min(100, $totalImpact * 10); // Scale to 0-100
    }

    private function calculateVariance($values) {
        if (count($values) < 2) return 0;

        $mean = array_sum($values) / count($values);
        $variance = 0;

        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }

        return $variance / (count($values) - 1);
    }

    private function generateCapacityRecommendations($peak, $average) {
        $recommendations = [];

        if ($peak > 90) {
            $recommendations[] = 'Consider capacity expansion - peak utilization exceeds 90%';
        }

        if ($average < 50) {
            $recommendations[] = 'Underutilized capacity - consider load balancing or downsizing';
        }

        if (($peak - $average) > 30) {
            $recommendations[] = 'High variance in utilization - implement demand smoothing';
        }

        return $recommendations;
    }
}

// Page logic
$digitalTwinSimulator = new DigitalTwinSimulator($conn, $userRole);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_twin':
            $twinData = [
                'twin_name' => $_POST['twin_name'],
                'description' => $_POST['description'] ?? '',
                'line_shift' => $_POST['line_shift'],
                'twin_type' => $_POST['twin_type'],
                'configuration' => [],
                'parameters' => []
            ];
            $result = $digitalTwinSimulator->createDigitalTwin($twinData);
            $success = $result['success'];
            $message = $result['message'];
            break;

        case 'run_simulation':
            $scenarioId = $_POST['scenario_id'];
            $runParameters = $_POST['parameters'] ?? [];
            $result = $digitalTwinSimulator->runSimulation($scenarioId, $runParameters);
            $success = $result['success'];
            $message = $result['message'];
            break;

        default:
            $success = false;
            $message = 'Invalid action';
    }

    // Redirect with message
    header('Location: digital_twin_simulator_offline.php?success=' . ($success ? '1' : '0') . '&message=' . urlencode($message));
    exit;
}

// Get dashboard data
$dashboardData = $digitalTwinSimulator->getDashboardData();

// Get available production lines for twin creation
$linesQuery = "SELECT DISTINCT CONCAT(line_number, '_', shift) as line_shift, line_name FROM production_lines ORDER BY line_number, shift";
$linesResult = $conn->query($linesQuery);
$availableLines = $linesResult->fetch_all(MYSQLI_ASSOC);

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
    <title>Digital Twin Simulator - Production Management System</title>
    <?php getInlineCSS(); ?>
    <style>
        .twin-card { border: 1px solid #dee2e6; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .twin-header { background-color: #f8f9fa; padding: 1rem; border-bottom: 1px solid #dee2e6; }
        .twin-body { padding: 1.5rem; }
        .simulation-control { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 0.5rem; padding: 1.5rem; text-align: center; margin-bottom: 1rem; }
        .timeline-item { position: relative; padding-left: 2rem; padding-bottom: 1rem; }
        .timeline-item::before { content: ''; position: absolute; left: 0; top: 0.5rem; width: 10px; height: 10px; border-radius: 50%; background: #0d6efd; }
        .timeline-item::after { content: ''; position: absolute; left: 4px; top: 1rem; width: 2px; height: calc(100% - 0.5rem); background: #e9ecef; }
        .timeline-item:last-child::after { display: none; }
        .metric-display { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 0.375rem; padding: 1rem; text-align: center; }
        .metric-value { font-size: 1.5rem; font-weight: bold; color: #0d6efd; }
        .metric-label { font-size: 0.875rem; color: #6c757d; }
        .scenario-template { border: 1px solid #e9ecef; border-radius: 0.375rem; padding: 1rem; margin-bottom: 0.5rem; cursor: pointer; transition: all 0.2s; }
        .scenario-template:hover { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); transform: translateY(-1px); }
        .sync-indicator { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 0.5rem; }
        .sync-synced { background-color: #28a745; }
        .sync-pending { background-color: #ffc107; }
        .sync-error { background-color: #dc3545; }
        .simulation-controls { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        @media (max-width: 768px) {
            .simulation-controls { flex-direction: column; }
            .metric-value { font-size: 1.25rem; }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">Digital Twin Simulator</h1>
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

                <!-- Digital Twin Controls -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Digital Twin Controls</h5>
                    </div>
                    <div class="card-body">
                        <div class="simulation-controls">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTwinModal">
                                <i class="fas fa-plus"></i> Create Digital Twin
                            </button>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#runSimulationModal">
                                <i class="fas fa-play"></i> Run Simulation
                            </button>
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#optimizationModal">
                                <i class="fas fa-chart-line"></i> Optimization
                            </button>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#whatIfModal">
                                <i class="fas fa-question-circle"></i> What-If Analysis
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Digital Twins Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="simulation-control">
                            <div class="h4"><?php echo count($dashboardData['digital_twins']); ?></div>
                            <div class="small">Active Digital Twins</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="simulation-control" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="h4"><?php echo count($dashboardData['recent_simulations']); ?></div>
                            <div class="small">Simulation Runs</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="simulation-control" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="h4"><?php echo count($dashboardData['simulation_templates']); ?></div>
                            <div class="small">Scenario Templates</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="simulation-control" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <div class="h4"><?php echo count($dashboardData['optimization_opportunities']); ?></div>
                            <div class="small">Optimizations</div>
                        </div>
                    </div>
                </div>

                <!-- Digital Twins List -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="twin-card">
                            <div class="twin-header">
                                <h5 class="card-title mb-0">Active Digital Twins</h5>
                            </div>
                            <div class="twin-body">
                                <?php if (!empty($dashboardData['digital_twins'])): ?>
                                    <?php foreach ($dashboardData['digital_twins'] as $twin): ?>
                                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                                        <div>
                                            <h6 class="mb-1">
                                                <span class="sync-indicator sync-<?php echo $twin['sync_status']; ?>"></span>
                                                <?php echo htmlspecialchars($twin['twin_name']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($twin['line_shift']); ?> •
                                                <?php echo htmlspecialchars($twin['twin_type']); ?> •
                                                <?php echo $twin['simulation_count']; ?> simulations
                                            </small>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewTwin(<?php echo $twin['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="calibrateTwin(<?php echo $twin['id']; ?>)">
                                                <i class="fas fa-sync"></i> Calibrate
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-cube fa-3x mb-3"></i>
                                        <p>No digital twins created yet.</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTwinModal">
                                            <i class="fas fa-plus"></i> Create Your First Digital Twin
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="twin-card">
                            <div class="twin-header">
                                <h5 class="card-title mb-0">Twin Performance</h5>
                            </div>
                            <div class="twin-body">
                                <?php if (!empty($dashboardData['twin_performance_metrics'])): ?>
                                    <?php foreach ($dashboardData['twin_performance_metrics'] as $metric): ?>
                                    <div class="metric-display mb-3">
                                        <div class="metric-value"><?php echo $metric['total_runs']; ?></div>
                                        <div class="metric-label"><?php echo htmlspecialchars($metric['twin_name']); ?> Runs</div>
                                        <div class="progress mt-2" style="height: 4px;">
                                            <div class="progress-bar" style="width: <?php echo min(100, $metric['total_runs'] * 10); ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-chart-bar fa-2x mb-3"></i>
                                        <p>No performance data available.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Simulations -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="twin-card">
                            <div class="twin-header">
                                <h5 class="card-title mb-0">Recent Simulations</h5>
                            </div>
                            <div class="twin-body">
                                <?php if (!empty($dashboardData['recent_simulations'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Run Name</th>
                                                    <th>Scenario</th>
                                                    <th>Type</th>
                                                    <th>Status</th>
                                                    <th>Duration</th>
                                                    <th>Started By</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dashboardData['recent_simulations'] as $simulation): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($simulation['run_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($simulation['scenario_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($simulation['scenario_type']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($simulation['status'] === 'completed') ? 'success' : (($simulation['status'] === 'running') ? 'primary' : 'warning'); ?>">
                                                            <?php echo ucfirst($simulation['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $simulation['duration_seconds'] ? round($simulation['duration_seconds'] / 60, 1) . ' min' : 'N/A'; ?></td>
                                                    <td><?php echo htmlspecialchars($simulation['username']); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info" onclick="viewResults(<?php echo $simulation['id']; ?>)">
                                                            <i class="fas fa-chart-line"></i> Results
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-play-circle fa-3x mb-3"></i>
                                        <p>No simulations run yet.</p>
                                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#runSimulationModal">
                                            <i class="fas fa-play"></i> Run Your First Simulation
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scenario Templates -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="twin-card">
                            <div class="twin-header">
                                <h5 class="card-title mb-0">Scenario Templates</h5>
                            </div>
                            <div class="twin-body">
                                <?php if (!empty($dashboardData['simulation_templates'])): ?>
                                    <?php foreach ($dashboardData['simulation_templates'] as $template): ?>
                                    <div class="scenario-template" onclick="useTemplate(<?php echo $template['id']; ?>)">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($template['template_name']); ?></h6>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($template['category']); ?></span>
                                        </div>
                                        <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($template['description'], 0, 100)) . '...'; ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Used <?php echo $template['usage_count']; ?> times</small>
                                            <div>
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star text-<?php echo ($i <= round($template['rating'])) ? 'warning' : 'secondary'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-clone fa-2x mb-3"></i>
                                        <p>No scenario templates available.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="twin-card">
                            <div class="twin-header">
                                <h5 class="card-title mb-0">Calibration Status</h5>
                            </div>
                            <div class="twin-body">
                                <?php if (!empty($dashboardData['calibration_status'])): ?>
                                    <?php foreach ($dashboardData['calibration_status'] as $calibration): ?>
                                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                                        <div>
                                            <strong><?php echo htmlspecialchars($calibration['twin_name']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo $calibration['calibration_date'] ? date('M j, Y', strtotime($calibration['calibration_date'])) : 'Not calibrated'; ?>
                                                <?php if ($calibration['accuracy_score']): ?>
                                                • Accuracy: <?php echo round($calibration['accuracy_score'] * 100, 1); ?>%
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div>
                                            <span class="sync-indicator sync-<?php echo $calibration['sync_status']; ?>"></span>
                                            <?php if ($calibration['next_calibration_date']): ?>
                                            <small class="text-muted d-block">Due: <?php echo date('M j', strtotime($calibration['next_calibration_date'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-sync fa-2x mb-3"></i>
                                        <p>No calibration data available.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Optimization Opportunities -->
                <div class="row">
                    <div class="col-12">
                        <div class="twin-card">
                            <div class="twin-header">
                                <h5 class="card-title mb-0">Optimization Opportunities</h5>
                            </div>
                            <div class="twin-body">
                                <?php if (!empty($dashboardData['optimization_opportunities'])): ?>
                                    <div class="row">
                                        <?php foreach ($dashboardData['optimization_opportunities'] as $opportunity): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="alert alert-info mb-0">
                                                <h6 class="alert-heading">
                                                    <i class="fas fa-lightbulb"></i>
                                                    <?php echo htmlspecialchars($opportunity['twin_name']); ?>
                                                </h6>
                                                <p class="mb-2"><?php echo htmlspecialchars($opportunity['opportunity']); ?></p>
                                                <hr>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">Current: <?php echo $opportunity['current_value']; ?></small>
                                                    <small class="text-success">Potential: +<?php echo $opportunity['potential_improvement']; ?></small>
                                                </div>
                                                <div class="mt-2">
                                                    <small class="text-muted"><?php echo htmlspecialchars($opportunity['recommendation']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-chart-line fa-2x mb-3"></i>
                                        <p>No optimization opportunities detected.</p>
                                        <small>All digital twins are performing optimally.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Twin Modal -->
    <div class="modal fade" id="createTwinModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Digital Twin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="createTwinForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="create_twin">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="twin_name" class="form-label">Twin Name</label>
                            <input type="text" class="form-control" id="twin_name" name="twin_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="line_shift" class="form-label">Production Line</label>
                            <select class="form-select" id="line_shift" name="line_shift" required>
                                <option value="">Select a line...</option>
                                <?php foreach ($availableLines as $line): ?>
                                <option value="<?php echo $line['line_shift']; ?>">
                                    <?php echo htmlspecialchars($line['line_shift'] . ' - ' . $line['line_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="twin_type" class="form-label">Twin Type</label>
                            <select class="form-select" id="twin_type" name="twin_type" required>
                                <option value="production_line">Production Line</option>
                                <option value="workstation">Workstation</option>
                                <option value="equipment">Equipment</option>
                                <option value="process_cell">Process Cell</option>
                                <option value="entire_facility">Entire Facility</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Digital Twin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh simulation data every 2 minutes
        setTimeout(function() {
            window.location.reload();
        }, 120000);

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

        // Modal handlers
        function viewTwin(twinId) {
            // Placeholder for twin viewing functionality
            console.log('View twin:', twinId);
        }

        function calibrateTwin(twinId) {
            // Placeholder for twin calibration functionality
            console.log('Calibrate twin:', twinId);
        }

        function viewResults(simulationId) {
            // Placeholder for viewing simulation results
            console.log('View results:', simulationId);
        }

        function useTemplate(templateId) {
            // Placeholder for using scenario template
            console.log('Use template:', templateId);
        }

        // Animate simulation controls on page load
        document.addEventListener('DOMContentLoaded', function() {
            const controls = document.querySelectorAll('.simulation-control');
            controls.forEach((control, index) => {
                control.style.opacity = '0';
                control.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    control.style.transition = 'all 0.5s ease';
                    control.style.opacity = '1';
                    control.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>