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
        error_log('CSRF validation failed in quality_assurance.php');
        die('Security validation failed');
    }
}

// Authentication and Authorization
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    header('Location: index_lan.php');
    exit;
}

// Check permissions (Operator, Supervisor, Manager, Executive, Admin roles)
if (!in_array($_SESSION['user_role'], ['operator', 'supervisor', 'manager', 'executive', 'admin'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied. You do not have permission to access quality assurance system.');
}

/**
 * Advanced Quality Assurance Management System
 * Comprehensive quality control with SPC, defect tracking, and continuous improvement
 */
class QualityAssuranceManager {
    private $conn;
    private $userRole;
    private $qualityStandards = [];
    private $spcCharts = [];
    private $qualityMetrics = [];

    public function __construct($conn, $userRole) {
        $this->conn = $conn;
        $this->userRole = $userRole;
        $this->initializeQualityDatabase();
        $this->loadQualityStandards();
    }

    /**
     * Initialize quality assurance database tables
     */
    private function initializeQualityDatabase() {
        // Create quality standards table
        $createStandardsTable = "CREATE TABLE IF NOT EXISTS quality_standards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            standard_code VARCHAR(50) NOT NULL UNIQUE,
            standard_name VARCHAR(255) NOT NULL,
            description TEXT,
            standard_type ENUM('product_specification', 'process_parameter', 'safety_requirement', 'environmental', 'industry_standard') NOT NULL,
            category VARCHAR(100),
            version VARCHAR(20) NOT NULL,
            effective_date DATE NOT NULL,
            expiry_date DATE NULL,
            acceptance_criteria JSON NOT NULL,
            testing_methods JSON,
            inspection_frequency ENUM('every_unit', 'per_batch', 'per_shift', 'daily', 'weekly', 'monthly') NOT NULL,
            sampling_size_formula VARCHAR(255),
            tolerance_limits JSON,
            measurement_units VARCHAR(50),
            critical_parameters JSON,
            created_by INT NOT NULL,
            approved_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_standard_code (standard_code),
            INDEX idx_standard_type (standard_type),
            INDEX idx_category (category),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createStandardsTable);

        // Create SPC data table
        $createSPCTable = "CREATE TABLE IF NOT EXISTS spc_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parameter_id VARCHAR(100) NOT NULL,
            parameter_name VARCHAR(255) NOT NULL,
            measurement_value DECIMAL(15,6) NOT NULL,
            sample_size INT DEFAULT 1,
            subgroup_size INT DEFAULT 1,
            measurement_timestamp TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
            line_shift VARCHAR(50),
            equipment_id VARCHAR(100),
            operator_name VARCHAR(255),
            measurement_method VARCHAR(255),
            environmental_conditions JSON,
            control_limits JSON,
            specification_limits JSON,
            process_stage VARCHAR(100),
            batch_number VARCHAR(100),
            measurement_notes TEXT,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_parameter_timestamp (parameter_id, measurement_timestamp),
            INDEX idx_line_shift (line_shift),
            INDEX idx_measurement_timestamp (measurement_timestamp),
            INDEX idx_process_stage (process_stage),
            INDEX idx_batch_number (batch_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createSPCTable);

        // Create SPC control charts table
        $createChartsTable = "CREATE TABLE IF NOT EXISTS spc_control_charts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chart_name VARCHAR(255) NOT NULL,
            chart_type ENUM('xbar_r', 'individuals', 'p_chart', 'np_chart', 'c_chart', 'u_chart', 'attribute_chart') NOT NULL,
            parameter_id VARCHAR(100) NOT NULL,
            parameter_name VARCHAR(255) NOT NULL,
            control_limits JSON NOT NULL,
            specification_limits JSON,
            subgroup_size INT DEFAULT 1,
            data_points JSON NOT NULL,
            analysis_results JSON,
            out_of_control_points JSON,
            capability_analysis JSON,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_chart_type (chart_type),
            INDEX idx_parameter_id (parameter_id),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createChartsTable);

        // Create defect tracking table
        $createDefectsTable = "CREATE TABLE IF NOT EXISTS quality_defects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            defect_id VARCHAR(100) NOT NULL UNIQUE,
            defect_date DATE NOT NULL,
            defect_time TIME NOT NULL,
            line_shift VARCHAR(50) NOT NULL,
            equipment_id VARCHAR(100),
            product_id VARCHAR(100),
            batch_number VARCHAR(100),
            defect_type ENUM('dimensional', 'visual', 'functional', 'material', 'surface_finish', 'assembly', 'other') NOT NULL,
            severity ENUM('minor', 'major', 'critical', 'catastrophic') NOT NULL,
            defect_description TEXT NOT NULL,
            defect_location VARCHAR(255),
            measurement_data JSON,
            images JSON,
            root_cause_analysis JSON,
            corrective_action JSON,
            containment_action JSON,
            prevention_action JSON,
            cost_impact DECIMAL(10,2),
            responsible_department VARCHAR(255),
            status ENUM('open', 'investigation', 'corrected', 'closed', 'rejected') DEFAULT 'open',
            detected_by VARCHAR(255),
            investigation_started_by VARCHAR(255),
            investigation_completed_by VARCHAR(255),
            detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            investigation_started_at TIMESTAMP NULL,
            corrected_at TIMESTAMP NULL,
            closed_at TIMESTAMP NULL,
            created_by INT NOT NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_defect_date (defect_date),
            INDEX idx_line_shift (line_shift),
            INDEX idx_defect_type (defect_type),
            INDEX idx_severity (severity),
            INDEX idx_status (status),
            INDEX idx_detected_at (detected_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createDefectsTable);

        // Create quality inspections table
        $createInspectionsTable = "CREATE TABLE IF NOT EXISTS quality_inspections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            inspection_id VARCHAR(100) NOT NULL UNIQUE,
            inspection_type ENUM('incoming', 'in_process', 'final', 'outgoing', 'supplier_audit', 'internal_audit') NOT NULL,
            inspection_date DATE NOT NULL,
            line_shift VARCHAR(50),
            product_type VARCHAR(255),
            batch_number VARCHAR(100),
            purchase_order VARCHAR(100),
            supplier_name VARCHAR(255),
            inspection_standard VARCHAR(100),
            sample_size INT NOT NULL,
            inspected_quantity INT NOT NULL,
            passed_quantity INT NOT NULL DEFAULT 0,
            failed_quantity INT NOT NULL DEFAULT 0,
            rework_quantity INT NOT NULL DEFAULT 0,
            scrap_quantity INT NOT NULL DEFAULT 0,
            yield_rate DECIMAL(5,2) GENERATED ALWAYS AS ((passed_quantity / inspected_quantity) * 100) STORED,
            defect_rate DECIMAL(5,2) GENERATED ALWAYS AS ((failed_quantity / inspected_quantity) * 100) STORED,
            inspection_results JSON,
            non_conformances JSON,
            inspection_notes TEXT,
            inspector_name VARCHAR(255),
            status ENUM('pending', 'in_progress', 'completed', 'rejected') DEFAULT 'pending',
            disposition ENUM('accept', 'rework', 'scrap', 'return', 'use_as_is', 'segregate') NULL,
            approved_by INT NULL,
            approved_at TIMESTAMP NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_inspection_date (inspection_date),
            INDEX idx_inspection_type (inspection_type),
            INDEX idx_line_shift (line_shift),
            INDEX idx_status (status),
            INDEX idx_yield_rate (yield_rate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createInspectionsTable);

        // Create quality metrics table
        $createMetricsTable = "CREATE TABLE IF NOT EXISTS quality_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            metric_name VARCHAR(255) NOT NULL,
            metric_category ENUM('yield', 'defect_rate', 'first_pass_yield', 'rework_rate', 'scrap_rate', 'customer_returns', 'supplier_quality', 'internal_failure') NOT NULL,
            calculation_period ENUM('hourly', 'shift', 'daily', 'weekly', 'monthly', 'quarterly') NOT NULL,
            period_start TIMESTAMP NOT NULL,
            period_end TIMESTAMP NOT NULL,
            numerator_value DECIMAL(15,4) NOT NULL,
            denominator_value DECIMAL(15,4) NOT NULL,
            metric_value DECIMAL(10,6) NOT NULL,
            target_value DECIMAL(10,6),
            upper_control_limit DECIMAL(10,6),
            lower_control_limit DECIMAL(10,6),
            benchmark_value DECIMAL(10,6),
            trend_direction ENUM('improving', 'stable', 'declining') NOT NULL,
            trend_strength DECIMAL(5,4),
            contributing_factors JSON,
            action_items JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_metric_period (metric_name, calculation_period, period_start),
            INDEX idx_metric_category (metric_category),
            INDEX idx_period_start (period_start),
            INDEX idx_metric_value (metric_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createMetricsTable);

        // Create quality improvement projects table
        $createImprovementsTable = "CREATE TABLE IF NOT EXISTS quality_improvement_projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id VARCHAR(100) NOT NULL UNIQUE,
            project_name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            project_type ENUM('problem_solving', 'process_improvement', 'quality_initiative', 'cost_reduction', 'efficiency_improvement') NOT NULL,
            methodology ENUM('six_sigma', 'kaizen', 'pdca', 'fmea', '5s', 'lean', 'other') NOT NULL,
            priority ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            business_case TEXT,
            scope_and_boundaries JSON,
            team_members JSON,
            timeline_months INT NOT NULL,
            start_date DATE NOT NULL,
            target_end_date DATE NOT NULL,
            actual_end_date DATE NULL,
            status ENUM('proposed', 'approved', 'in_progress', 'on_hold', 'completed', 'cancelled') DEFAULT 'proposed',
            expected_benefits JSON,
            actual_benefits JSON,
            roi_calculated DECIMAL(10,2),
            lessons_learned TEXT,
            success_factors JSON,
            challenges JSON,
            project_manager VARCHAR(255),
            sponsor VARCHAR(255),
            budget_allocated DECIMAL(12,2),
            budget_spent DECIMAL(12,2),
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_project_type (project_type),
            INDEX idx_priority (priority),
            INDEX idx_status (status),
            INDEX idx_start_date (start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createImprovementsTable);

        // Create quality audit table
        $createAuditTable = "CREATE TABLE IF NOT EXISTS quality_audits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            audit_id VARCHAR(100) NOT NULL UNIQUE,
            audit_type ENUM('internal', 'external', 'supplier', 'process', 'product', 'system', 'compliance') NOT NULL,
            audit_date DATE NOT NULL,
            audit_scope TEXT NOT NULL,
            audit_criteria JSON NOT NULL,
            auditor_name VARCHAR(255),
            auditee VARCHAR(255),
            findings JSON NOT NULL,
            non_conformities JSON NOT NULL,
            observations JSON NOT NULL,
            positive_practices JSON,
            corrective_actions JSON,
            follow_up_required BOOLEAN DEFAULT TRUE,
            audit_score DECIMAL(5,2),
            compliance_rating ENUM('fully_compliant', 'substantially_compliant', 'partially_compliant', 'non_compliant') NOT NULL,
            report_generated BOOLEAN DEFAULT FALSE,
            report_path VARCHAR(500),
            status ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_audit_type (audit_type),
            INDEX idx_audit_date (audit_date),
            INDEX idx_compliance_rating (compliance_rating),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createAuditTable);
    }

    /**
     * Load quality standards from database
     */
    private function loadQualityStandards() {
        $query = "SELECT * FROM quality_standards WHERE is_active = TRUE ORDER BY standard_code";
        $result = $this->conn->query($query);

        while ($row = $result->fetch_assoc()) {
            $this->qualityStandards[] = $row;
        }
    }

    /**
     * Perform Statistical Process Control (SPC) analysis
     */
    public function performSPCAnalysis($parameterId, $startDate, $endDate) {
        // Get SPC data for the period
        $query = "SELECT measurement_value, measurement_timestamp, subgroup_size, line_shift
                  FROM spc_data
                  WHERE parameter_id = ? AND measurement_timestamp BETWEEN ? AND ?
                  ORDER BY measurement_timestamp";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $parameterId, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $dataPoints = $result->fetch_all(MYSQLI_ASSOC);

        if (count($dataPoints) < 10) {
            throw new Exception("Insufficient data points for SPC analysis. Need at least 10 measurements.");
        }

        // Perform SPC calculations
        $analysis = [
            'parameter_id' => $parameterId,
            'data_points' => count($dataPoints),
            'analysis_period' => $startDate . ' to ' . $endDate,
            'statistics' => $this->calculateSPCStatistics($dataPoints),
            'control_limits' => $this->calculateControlLimits($dataPoints),
            'capability_analysis' => $this->calculateProcessCapability($dataPoints),
            'control_chart_data' => $this->prepareControlChartData($dataPoints),
            'out_of_control_points' => $this->detectOutOfControlPoints($dataPoints),
            'trend_analysis' => $this->analyzeTrends($dataPoints),
            'recommendations' => []
        ];

        // Add recommendations based on analysis
        $analysis['recommendations'] = $this->generateSPCRecommendations($analysis);

        // Update or create SPC control chart
        $this->updateSPCControlChart($parameterId, $analysis);

        return $analysis;
    }

    /**
     * Calculate SPC statistics
     */
    private function calculateSPCStatistics($dataPoints) {
        $values = array_column($dataPoints, 'measurement_value');
        $subgroupSizes = array_column($dataPoints, 'subgroup_size');

        $mean = array_sum($values) / count($values);
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $stdDev = sqrt($variance / (count($values) - 1));

        // Calculate range statistics for subgroups
        $ranges = [];
        if (count($subgroupSizes) > 1) {
            // Group by subgroups
            $subgroupedData = [];
            foreach ($dataPoints as $point) {
                $subgroupedData[] = $point['measurement_value'];
                if (count($subgroupedData) >= $point['subgroup_size']) {
                    $range = max($subgroupedData) - min($subgroupedData);
                    $ranges[] = $range;
                    $subgroupedData = [];
                }
            }
        }

        $avgRange = !empty($ranges) ? array_sum($ranges) / count($ranges) : 0;

        return [
            'mean' => round($mean, 6),
            'standard_deviation' => round($stdDev, 6),
            'minimum' => min($values),
            'maximum' => max($values),
            'range' => round(max($values) - min($values), 6),
            'average_range' => round($avgRange, 6),
            'coefficient_of_variation' => round(($stdDev / $mean) * 100, 2),
            'sample_size' => count($values)
        ];
    }

    /**
     * Calculate control limits
     */
    private function calculateControlLimits($dataPoints) {
        $values = array_column($dataPoints, 'measurement_value');
        $subgroupSizes = array_column($dataPoints, 'subgroup_size');

        $mean = array_sum($values) / count($values);

        if (count($values) > 0) {
            $variance = 0;
            foreach ($values as $value) {
                $variance += pow($value - $mean, 2);
            }
            $stdDev = sqrt($variance / (count($values) - 1));

            // X-bar chart limits
            $ucl_xbar = $mean + (3 * $stdDev);
            $lcl_xbar = $mean - (3 * $stdDev);

            // R chart limits (if subgroups exist)
            $ucl_r = 0;
            $lcl_r = 0;
            if (count($subgroupSizes) > 1) {
                $avgRange = $this->calculateAverageRange($dataPoints);
                $d2 = $this->getD2Constant($subgroupSizes[0] ?? 1);
                $d3 = $this->getD3Constant($subgroupSizes[0] ?? 1);

                $ucl_r = $avgRange * ($d2 + 3 * $d3);
                $lcl_r = max(0, $avgRange * ($d2 - 3 * $d3));
            }

            return [
                'xbar_chart' => [
                    'center_line' => round($mean, 6),
                    'upper_control_limit' => round($ucl_xbar, 6),
                    'lower_control_limit' => round($lcl_xbar, 6),
                    'sigma' => round($stdDev, 6)
                ],
                'r_chart' => [
                    'center_line' => round($avgRange, 6),
                    'upper_control_limit' => round($ucl_r, 6),
                    'lower_control_limit' => round($lcl_r, 6),
                    'subgroup_size' => $subgroupSizes[0] ?? 1
                ]
            ];
        }

        return null;
    }

    /**
     * Calculate process capability indices
     */
    private function calculateProcessCapability($dataPoints) {
        $values = array_column($dataPoints, 'measurement_value');

        if (count($values) < 2) {
            return ['cp' => 0, 'cpk' => 0, 'pp' => 0, 'ppk' => 0];
        }

        $mean = array_sum($values) / count($values);
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $stdDev = sqrt($variance / (count($values) - 1));

        // These should be pulled from quality standards
        $usl = null; // Upper specification limit
        $lsl = null; // Lower specification limit

        // Try to get specification limits from standards
        $parameterId = $dataPoints[0]['parameter_id'];
        $specLimits = $this->getSpecificationLimits($parameterId);

        if ($specLimits) {
            $usl = $specLimits['upper_specification_limit'];
            $lsl = $specLimits['lower_specification_limit'];
        }

        if ($usl === null && $lsl === null) {
            // Default specification limits if not found
            $range = max($values) - min($values);
            $usl = $mean + ($range / 2);
            $lsl = $mean - ($range / 2);
        }

        // Calculate capability indices
        $processSpread = 6 * $stdDev;
        $specificationSpread = $usl - $lsl;

        $cp = $specificationSpread > 0 ? $specificationSpread / $processSpread : 0;

        if ($usl !== null && $lsl !== null) {
            $cpu = ($usl - $mean) / (3 * $stdDev);
            $cpl = ($mean - $lsl) / (3 * $stdDev);
            $cpk = min($cpu, $cpl);
        } else {
            $cpk = $cp;
        }

        // Overall capability (using long-term standard deviation)
        $pp = $specificationSpread > 0 ? $specificationSpread / (6 * $stdDev * 1.5) : 0; // Assuming 1.5 sigma shift

        if ($usl !== null && $lsl !== null) {
            $ppu = ($usl - $mean) / (3 * $stdDev * 1.5);
            $ppl = ($mean - $lsl) / (3 * $stdDev * 1.5);
            $ppk = min($ppu, $ppl);
        } else {
            $ppk = $pp;
        }

        return [
            'cp' => round($cp, 3),
            'cpk' => round($cpk, 3),
            'pp' => round($pp, 3),
            'ppk' => round($ppk, 3),
            'process_capability_rating' => $this->getCapabilityRating($cpk),
            'specification_limits' => [
                'upper_spec_limit' => $usl,
                'lower_spec_limit' => $lsl
            ]
        ];
    }

    /**
     * Get capability rating based on Cpk value
     */
    private function getCapabilityRating($cpk) {
        if ($cpk >= 2.0) return 'Six Sigma';
        if ($cpk >= 1.5) return 'Excellent';
        if ($cpk >= 1.33) return 'Very Good';
        if ($cpk >= 1.0) return 'Good';
        if ($cpk >= 0.67) return 'Marginal';
        return 'Poor';
    }

    /**
     * Prepare control chart data
     */
    private function prepareControlChartData($dataPoints) {
        $chartData = [];
        $subgroupNumber = 1;

        $subgroupedData = [];
        foreach ($dataPoints as $point) {
            $subgroupedData[] = $point['measurement_value'];

            if (count($subgroupedData) >= $point['subgroup_size']) {
                $subgroupMean = array_sum($subgroupedData) / count($subgroupedData);
                $subgroupRange = max($subgroupedData) - min($subgroupedData);

                $chartData[] = [
                    'subgroup' => $subgroupNumber++,
                    'timestamp' => $point['measurement_timestamp'],
                    'mean' => round($subgroupMean, 6),
                    'range' => round($subgroupRange, 6),
                    'values' => $subgroupedData,
                    'line_shift' => $point['line_shift']
                ];

                $subgroupedData = [];
            }
        }

        return $chartData;
    }

    /**
     * Detect out-of-control points
     */
    private function detectOutOfControlPoints($dataPoints) {
        $controlLimits = $this->calculateControlLimits($dataPoints);
        $outOfControlPoints = [];

        if (!$controlLimits) {
            return $outOfControlPoints;
        }

        $values = array_column($dataPoints, 'measurement_value');
        $ucl = $controlLimits['xbar_chart']['upper_control_limit'];
        $lcl = $controlLimits['xbar_chart']['lower_control_limit'];

        foreach ($dataPoints as $index => $point) {
            if ($point['measurement_value'] > $ucl || $point['measurement_value'] < $lcl) {
                $outOfControlPoints[] = [
                    'point_number' => $index + 1,
                    'value' => $point['measurement_value'],
                    'timestamp' => $point['measurement_timestamp'],
                    'violation_type' => $point['measurement_value'] > $ucl ? 'above_ucl' : 'below_lcl',
                    'severity' => 'out_of_control'
                ];
            }
        }

        // Check for runs and trends (Western Electric Rules)
        $outOfControlPoints = array_merge($outOfControlPoints, $this->checkWesternElectricRules($dataPoints, $controlLimits));

        return $outOfControlPoints;
    }

    /**
     * Check Western Electric Rules for out-of-control conditions
     */
    private function checkWesternElectricRules($dataPoints, $controlLimits) {
        $violations = [];
        $values = array_column($dataPoints, 'measurement_value');
        $centerLine = $controlLimits['xbar_chart']['center_line'];
        $oneSigma = $controlLimits['xbar_chart']['sigma'];

        // Rule 1: One point beyond 3 sigma
        $ucl = $centerLine + (3 * $oneSigma);
        $lcl = $centerLine - (3 * $oneSigma);

        foreach ($dataPoints as $index => $point) {
            if ($point['measurement_value'] > $ucl || $point['measurement_value'] < $lcl) {
                $violations[] = [
                    'point_number' => $index + 1,
                    'value' => $point['measurement_value'],
                    'rule' => 'Rule 1: Beyond 3 sigma',
                    'severity' => 'out_of_control'
                ];
            }
        }

        // Rule 2: Nine points in a row on one side of center line
        for ($i = 0; $i <= count($values) - 9; $i++) {
            $allAbove = true;
            $allBelow = true;

            for ($j = 0; $j < 9; $j++) {
                if ($values[$i + $j] <= $centerLine) $allAbove = false;
                if ($values[$i + $j] >= $centerLine) $allBelow = false;
            }

            if ($allAbove || $allBelow) {
                $violations[] = [
                    'point_range' => ($i + 1) . '-' . ($i + 9),
                    'rule' => 'Rule 2: Nine points in a row',
                    'severity' => 'warning'
                ];
            }
        }

        // Rule 3: Six points in a row, all increasing or decreasing
        for ($i = 0; $i <= count($values) - 6; $i++) {
            $increasing = true;
            $decreasing = true;

            for ($j = 0; $j < 5; $j++) {
                if ($values[$i + $j + 1] <= $values[$i + $j]) $increasing = false;
                if ($values[$i + $j + 1] >= $values[$i + $j]) $decreasing = false;
            }

            if ($increasing || $decreasing) {
                $violations[] = [
                    'point_range' => ($i + 1) . '-' . ($i + 6),
                    'rule' => 'Rule 3: Six points increasing/decreasing',
                    'severity' => 'warning'
                ];
            }
        }

        return $violations;
    }

    /**
     * Analyze trends in the data
     */
    private function analyzeTrends($dataPoints) {
        $values = array_column($dataPoints, 'measurement_value');

        if (count($values) < 2) {
            return ['trend' => 'insufficient_data', 'slope' => 0];
        }

        // Calculate trend slope using linear regression
        $n = count($values);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $values[$i];
            $sumXY += $i * $values[$i];
            $sumX2 += $i * $i;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);

        $trend = 'stable';
        $trendStrength = abs($slope);

        if ($slope > 0.01) {
            $trend = 'increasing';
        } elseif ($slope < -0.01) {
            $trend = 'decreasing';
        }

        return [
            'trend' => $trend,
            'slope' => round($slope, 6),
            'strength' => round($trendStrength, 6)
        ];
    }

    /**
     * Generate SPC recommendations
     */
    private function generateSPCRecommendations($analysis) {
        $recommendations = [];

        // Based on capability analysis
        $capability = $analysis['capability_analysis'];
        if ($capability['cpk'] < 1.0) {
            $recommendations[] = [
                'type' => 'process_capability',
                'priority' => 'high',
                'title' => 'Process Capability Below Acceptable Level',
                'description' => "Current Cpk is {$capability['cpk']}, which is below the minimum acceptable value of 1.0.",
                'actions' => [
                    'Investigate process centering and spread',
                    'Reduce variation through process optimization',
                    'Review and tighten process controls',
                    'Implement statistical process control'
                ]
            ];
        }

        // Based on out-of-control points
        if (!empty($analysis['out_of_control_points'])) {
            $recommendations[] = [
                'type' => 'out_of_control',
                'priority' => 'critical',
                'title' => 'Out-of-Control Conditions Detected',
                'description' => count($analysis['out_of_control_points']) . " points are out of statistical control.",
                'actions' => [
                    'Investigate and eliminate special causes',
                    'Implement immediate corrective actions',
                    'Review measurement system and procedures',
                    'Consider stabilizing the process'
                ]
            ];
        }

        // Based on trend analysis
        $trend = $analysis['trend_analysis'];
        if ($trend['trend'] !== 'stable' && $trend['strength'] > 0.1) {
            $recommendations[] = [
                'type' => 'trend_analysis',
                'priority' => 'medium',
                'title' => 'Significant Process Trend Detected',
                'description' => "Process is {$trend['trend']} with strength {$trend['strength']}.",
                'actions' => [
                    'Investigate root causes of trend',
                    'Determine if trend is beneficial or harmful',
                    'Implement controls if trend is undesirable',
                    'Monitor trend closely'
                ]
            ];
        }

        return $recommendations;
    }

    /**
     * Update SPC control chart in database
     */
    private function updateSPCControlChart($parameterId, $analysis) {
        $query = "INSERT INTO spc_control_charts
                  (chart_name, chart_type, parameter_id, parameter_name, control_limits,
                   specification_limits, data_points, analysis_results, out_of_control_points,
                   capability_analysis, created_by)
                  VALUES (?, 'xbar_r', ?, ?, ?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE
                  control_limits = VALUES(control_limits),
                  data_points = VALUES(data_points),
                  analysis_results = VALUES(analysis_results),
                  out_of_control_points = VALUES(out_of_control_points),
                  capability_analysis = VALUES(capability_analysis),
                  last_updated = CURRENT_TIMESTAMP";

        $chartName = 'X-bar R Chart for ' . $parameterId;
        $parameterName = $this->getParameterName($parameterId);

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "ssssssssssi",
            $chartName,
            $parameterId,
            $parameterName,
            json_encode($analysis['control_limits']),
            json_encode($analysis['capability_analysis']['specification_limits']),
            json_encode($analysis['control_chart_data']),
            json_encode($analysis),
            json_encode($analysis['out_of_control_points']),
            json_encode($analysis['capability_analysis']),
            $_SESSION['user_id']
        );

        return $stmt->execute();
    }

    /**
     * Get quality dashboard data
     */
    public function getDashboardData() {
        return [
            'quality_metrics' => $this->getQualityMetrics(),
            'recent_defects' => $this->getRecentDefects(),
            'active_inspections' => $this->getActiveInspections(),
            'spc_charts' => $this->getSPCCharts(),
            'quality_standards' => $this->getQualityStandards(),
            'improvement_projects' => $this->getImprovementProjects(),
            'upcoming_audits' => $this->getUpcomingAudits(),
            'quality_performance' => $this->getQualityPerformance()
        ];
    }

    /**
     * Get quality metrics
     */
    private function getQualityMetrics() {
        $query = "SELECT
                     metric_category,
                     AVG(metric_value) as avg_value,
                     metric_name,
                     period_start,
                     period_end
                  FROM quality_metrics
                  WHERE period_start >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  GROUP BY metric_category, metric_name
                  ORDER BY metric_category, period_start DESC";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get recent defects
     */
    private function getRecentDefects() {
        $query = "SELECT
                     qd.*,
                     u.username as created_by_name
                  FROM quality_defects qd
                  JOIN users u ON qd.created_by = u.id
                  WHERE qd.status IN ('open', 'investigation', 'corrected')
                  ORDER BY qd.detected_at DESC
                  LIMIT 10";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get active inspections
     */
    private function getActiveInspections() {
        $query = "SELECT
                     qi.*,
                     u.username as inspector_name
                  FROM quality_inspections qi
                  JOIN users u ON qi.created_by = u.id
                  WHERE qi.status IN ('pending', 'in_progress')
                  ORDER BY qi.inspection_date DESC, qi.created_at DESC
                  LIMIT 10";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get SPC charts
     */
    private function getSPCCharts() {
        $query = "SELECT * FROM spc_control_charts WHERE is_active = TRUE ORDER BY last_updated DESC LIMIT 5";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get quality standards
     */
    private function getQualityStandards() {
        return $this->qualityStandards;
    }

    /**
     * Get improvement projects
     */
    private function getImprovementProjects() {
        $query = "SELECT
                     qip.*,
                     u.username as project_manager_name
                  FROM quality_improvement_projects qip
                  JOIN users u ON qip.project_manager = u.id
                  WHERE qip.status IN ('approved', 'in_progress')
                  ORDER BY qip.priority DESC, qip.start_date ASC
                  LIMIT 10";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get upcoming audits
     */
    private function getUpcomingAudits() {
        $query = "SELECT * FROM quality_audits
                  WHERE audit_date >= CURDATE()
                    AND status IN ('planned', 'in_progress')
                  ORDER BY audit_date ASC
                  LIMIT 5";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get quality performance summary
     */
    private function getQualityPerformance() {
        // Get recent quality metrics
        $query = "SELECT
                     COUNT(*) as total_inspections,
                     AVG(yield_rate) as avg_yield_rate,
                     AVG(defect_rate) as avg_defect_rate,
                     SUM(passed_quantity) as total_passed,
                     SUM(failed_quantity) as total_failed,
                     SUM(rework_quantity) as total_rework,
                     SUM(scrap_quantity) as total_scrap
                  FROM quality_inspections
                  WHERE inspection_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

        $result = $this->conn->query($query);
        $inspectionData = $result->fetch_assoc();

        // Get defect statistics
        $defectQuery = "SELECT
                           defect_type,
                           severity,
                           COUNT(*) as count,
                           AVG(cost_impact) as avg_cost
                        FROM quality_defects
                        WHERE defect_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY defect_type, severity
                        ORDER BY count DESC";

        $defectResult = $this->conn->query($defectQuery);
        $defectData = $defectResult->fetch_all(MYSQLI_ASSOC);

        return [
            'inspections' => $inspectionData,
            'defects' => $defectData,
            'overall_quality_score' => $this->calculateOverallQualityScore($inspectionData, $defectData)
        ];
    }

    /**
     * Calculate overall quality score
     */
    private function calculateOverallQualityScore($inspectionData, $defectData) {
        $qualityScore = 0;
        $totalWeight = 0;

        // Yield rate weight: 40%
        if (isset($inspectionData['avg_yield_rate'])) {
            $qualityScore += ($inspectionData['avg_yield_rate'] / 100) * 40;
            $totalWeight += 40;
        }

        // Defect rate weight: 30% (inverted, lower is better)
        if (isset($inspectionData['avg_defect_rate'])) {
            $defectScore = max(0, (100 - $inspectionData['avg_defect_rate']) / 100);
            $qualityScore += $defectScore * 30;
            $totalWeight += 30;
        }

        // Rework rate weight: 15% (inverted, lower is better)
        if ($inspectionData['total_passed'] > 0) {
            $reworkRate = ($inspectionData['total_rework'] / $inspectionData['total_passed']) * 100;
            $reworkScore = max(0, (100 - $reworkRate) / 100);
            $qualityScore += $reworkScore * 15;
            $totalWeight += 15;
        }

        // Scrap rate weight: 15% (inverted, lower is better)
        if ($inspectionData['total_passed'] > 0) {
            $scrapRate = ($inspectionData['total_scrap'] / $inspectionData['total_passed']) * 100;
            $scrapScore = max(0, (100 - $scrapRate) / 100);
            $qualityScore += $scrapScore * 15;
            $totalWeight += 15;
        }

        return $totalWeight > 0 ? round($qualityScore, 1) : 0;
    }

    // Helper methods
    private function getParameterName($parameterId) {
        // This would typically query a parameter registry
        return $parameterId; // Simplified for now
    }

    private function getSpecificationLimits($parameterId) {
        // This would query quality standards for specification limits
        return null; // Simplified for now
    }

    private function calculateAverageRange($dataPoints) {
        $ranges = [];
        $subgroupedData = [];

        foreach ($dataPoints as $point) {
            $subgroupedData[] = $point['measurement_value'];

            if (count($subgroupedData) >= $point['subgroup_size']) {
                $ranges[] = max($subgroupedData) - min($subgroupedData);
                $subgroupedData = [];
            }
        }

        return !empty($ranges) ? array_sum($ranges) / count($ranges) : 0;
    }

    private function getD2Constant($n) {
        // D2 constants for control chart calculations
        $d2Constants = [
            2 => 1.128,
            3 => 1.693,
            4 => 2.059,
            5 => 2.326,
            6 => 2.534,
            7 => 2.704,
            8 => 2.847,
            9 => 2.970,
            10 => 3.078
        ];

        return $d2Constants[$n] ?? 2.059; // Default to n=4
    }

    private function getD3Constant($n) {
        // D3 constants for control chart calculations
        $d3Constants = [
            2 => 0.853,
            3 => 0.888,
            4 => 0.880,
            5 => 0.864,
            6 => 0.848,
            7 => 0.833,
            8 => 0.820,
            9 => 0.808,
            10 => 0.797
        ];

        return $d3Constants[$n] ?? 0.880; // Default to n=4
    }
}

// Page logic
$qualityManager = new QualityAssuranceManager($conn, $userRole);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'perform_spc_analysis':
            $parameterId = $_POST['parameter_id'];
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            try {
                $analysis = $qualityManager->performSPCAnalysis($parameterId, $startDate, $endDate);
                $success = true;
                $message = "SPC analysis completed for parameter: $parameterId";
            } catch (Exception $e) {
                $success = false;
                $message = "SPC analysis failed: " . $e->getMessage();
            }
            break;

        default:
            $success = false;
            $message = 'Invalid action';
    }

    // Redirect with message
    header('Location: quality_assurance_offline.php?success=' . ($success ? '1' : '0') . '&message=' . urlencode($message));
    exit;
}

// Get dashboard data
$dashboardData = $qualityManager->getDashboardData();

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
    <title>Quality Assurance System - Production Management System</title>
    <?php getInlineCSS(); ?>
    <style>
        .quality-card { border: 1px solid #dee2e6; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .quality-header { background-color: #f8f9fa; padding: 1rem; border-bottom: 1px solid #dee2e6; }
        .quality-body { padding: 1.5rem; }
        .metric-display { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 0.5rem; padding: 1.5rem; text-align: center; margin-bottom: 1rem; }
        .defect-item { border-left: 4px solid #dc3545; padding: 0.75rem; margin-bottom: 0.5rem; background: #f8f9fa; }
        .defect-item.minor { border-left-color: #ffc107; }
        .defect-item.major { border-left-color: #fd7e14; }
        .defect-item.critical { border-left-color: #dc3545; }
        .defect-item.catastrophic { border-left-color: #6f42c1; }
        .inspection-status { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: bold; }
        .status-pending { background-color: #6c757d; color: white; }
        .status-in_progress { background-color: #0d6efd; color: white; }
        .status-completed { background-color: #198754; color: white; }
        .capability-indicator { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 0.5rem; }
        .capability-excellent { background-color: #198754; }
        .capability-good { background-color: #20c997; }
        .capability-marginal { background-color: #ffc107; }
        .capability-poor { background-color: #dc3545; }
        .trend-indicator { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
        .trend-improving { background-color: #198754; color: white; }
        .trend-stable { background-color: #6c757d; color: white; }
        .trend-declining { background-color: #dc3545; color: white; }
        .quality-controls { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        @media (max-width: 768px) {
            .quality-controls { flex-direction: column; }
            .metric-display { margin-bottom: 0.5rem; }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">Quality Assurance System</h1>
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

                <!-- Quality Controls -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quality Management Controls</h5>
                    </div>
                    <div class="card-body">
                        <div class="quality-controls">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#spcAnalysisModal">
                                <i class="fas fa-chart-line"></i> SPC Analysis
                            </button>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#inspectionModal">
                                <i class="fas fa-check-circle"></i> Record Inspection
                            </button>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#defectModal">
                                <i class="fas fa-exclamation-triangle"></i> Report Defect
                            </button>
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#standardModal">
                                <i class="fas fa-clipboard-check"></i> Quality Standards
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quality Metrics Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="metric-display">
                            <div class="h4"><?php echo round($dashboardData['quality_performance']['overall_quality_score'], 1); ?>%</div>
                            <div class="small">Overall Quality Score</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-display" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="h4"><?php echo round($dashboardData['quality_performance']['inspections']['avg_yield_rate'] ?? 0, 1); ?>%</div>
                            <div class="small">Average Yield Rate</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-display" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="h4"><?php echo count($dashboardData['quality_standards']); ?></div>
                            <div class="small">Active Standards</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-display" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <div class="h4"><?php echo count($dashboardData['recent_defects']); ?></div>
                            <div class="small">Active Defects</div>
                        </div>
                    </div>
                </div>

                <!-- Quality Insights -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="quality-card">
                            <div class="quality-header">
                                <h5 class="card-title mb-0">Recent Defect Reports</h5>
                            </div>
                            <div class="quality-body">
                                <?php if (!empty($dashboardData['recent_defects'])): ?>
                                    <?php foreach ($dashboardData['recent_defects'] as $defect): ?>
                                    <div class="defect-item <?php echo $defect['severity']; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <span class="badge bg-<?php echo ($defect['severity'] === 'critical') ? 'danger' : (($defect['severity'] === 'major') ? 'warning' : (($defect['severity'] === 'minor') ? 'success' : 'secondary'); ?> me-2">
                                                        <?php echo ucfirst($defect['severity']); ?>
                                                    </span>
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $defect['defect_type']))); ?>
                                                </h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($defect['line_shift']); ?> • <?php echo date('M j, Y', strtotime($defect['defect_date'])); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <?php if ($defect['cost_impact']): ?>
                                                <small class="text-danger">$<?php echo number_format($defect['cost_impact'], 2); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <p class="mb-2 mt-2"><?php echo htmlspecialchars(substr($defect['defect_description'], 0, 150)); ?>...</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">Reported by: <?php echo htmlspecialchars($defect['created_by_name']); ?></small>
                                            <span class="badge bg-secondary"><?php echo ucfirst($defect['status']); ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                                        <p>No defects reported.</p>
                                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#defectModal">
                                            <i class="fas fa-plus"></i> Report First Defect
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="quality-card">
                            <div class="quality-header">
                                <h5 class="card-title mb-0">Active Inspections</h5>
                            </div>
                            <div class="quality-body">
                                <?php if (!empty($dashboardData['active_inspections'])): ?>
                                    <?php foreach ($dashboardData['active_inspections'] as $inspection): ?>
                                    <div class="p-3 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($inspection['inspection_id']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars(ucfirst($inspection['inspection_type'])); ?> •
                                                    <?php echo date('M j, Y', strtotime($inspection['inspection_date'])); ?>
                                                </small>
                                            </div>
                                            <div>
                                                <span class="inspection-status status-<?php echo $inspection['status']; ?>">
                                                    <?php echo str_replace('_', ' ', $inspection['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="row text-center">
                                            <div class="col-3">
                                                <small class="text-muted">Sample Size</small>
                                                <div class="fw-bold"><?php echo $inspection['sample_size']; ?></div>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-muted">Passed</small>
                                                <div class="fw-bold text-success"><?php echo $inspection['passed_quantity']; ?></div>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-muted">Failed</small>
                                                <div class="fw-bold text-danger"><?php echo $inspection['failed_quantity']; ?></div>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-muted">Yield</small>
                                                <div class="fw-bold"><?php echo round($inspection['yield_rate'], 1); ?>%</div>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <small class="text-muted">Inspector: <?php echo htmlspecialchars($inspection['inspector_name']); ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-clipboard-check fa-3x mb-3"></i>
                                        <p>No active inspections.</p>
                                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#inspectionModal">
                                            <i class="fas fa-plus"></i> Create Inspection
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SPC Control Charts -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="quality-card">
                            <div class="quality-header">
                                <h5 class="card-title mb-0">Statistical Process Control (SPC)</h5>
                            </div>
                            <div class="quality-body">
                                <?php if (!empty($dashboardData['spc_charts'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Chart Name</th>
                                                    <th>Parameter</th>
                                                    <th>Cpk</th>
                                                    <th>Capability</th>
                                                    <th>Status</th>
                                                    <th>Last Updated</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dashboardData['spc_charts'] as $chart): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($chart['chart_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($chart['parameter_name']); ?></td>
                                                    <td>
                                                        <?php
                                                        $analysis = json_decode($chart['capability_analysis'], true);
                                                        echo $analysis ? round($analysis['cpk'], 2) : 'N/A';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $capabilityRating = $analysis ? $analysis['process_capability_rating'] : 'Unknown';
                                                        ?>
                                                        <span class="capability-indicator capability-<?php echo strtolower(str_replace(' ', '-', $capabilityRating)); ?>"></span>
                                                        <?php echo ucfirst($capabilityRating); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success">Active</span>
                                                    </td>
                                                    <td><?php echo date('M j, Y H:i', strtotime($chart['last_updated'])); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewSPCChart(<?php echo $chart['id']; ?>)">
                                                            <i class="fas fa-chart-line"></i> View
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                                        <p>No SPC charts available.</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#spcAnalysisModal">
                                            <i class="fas fa-plus"></i> Create SPC Chart
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quality Standards -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="quality-card">
                            <div class="quality-header">
                                <h5 class="card-title mb-0">Quality Standards</h5>
                            </div>
                            <div class="quality-body">
                                <?php if (!empty($dashboardData['quality_standards'])): ?>
                                    <div class="row">
                                        <?php $displayStandards = array_slice($dashboardData['quality_standards'], 0, 6); ?>
                                        <?php foreach ($displayStandards as $standard): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                                        <i class="fas fa-clipboard-check text-primary"></i>
                                                        <?php echo htmlspecialchars($standard['standard_name']); ?>
                                                    </h6>
                                                    <p class="card-text small text-muted">
                                                        <?php echo htmlspecialchars($standard['description']); ?>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                                        <small class="text-muted"><?php echo $standard['standard_code']; ?></small>
                                                        <small class="badge bg-info"><?php echo ucfirst($standard['standard_type']); ?></small>
                                                    </div>
                                                    <div class="mt-2">
                                                        <small class="text-muted">Version: <?php echo $standard['version']; ?></small>
                                                        <small class="text-muted ms-2">Effective: <?php echo date('M j, Y', strtotime($standard['effective_date'])); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                                        <p>No quality standards defined.</p>
                                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#standardModal">
                                            <i class="fas fa-plus"></i> Create Standard
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="quality-card">
                            <div class="quality-header">
                                <h5 class="card-title mb-0">Quality Performance</h5>
                            </div>
                            <div class="quality-body">
                                <?php if ($dashboardData['quality_performance']['inspections']['total_inspections'] > 0): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Total Inspections</span>
                                            <span class="badge bg-primary"><?php echo $dashboardData['quality_performance']['inspections']['total_inspections']; ?></span>
                                        </div>
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar bg-success" style="width: 100%"></div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Yield Rate</span>
                                            <span class="badge bg-success"><?php echo round($dashboardData['quality_performance']['inspections']['avg_yield_rate'], 1); ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar bg-<?php echo ($dashboardData['quality_performance']['inspections']['avg_yield_rate'] >= 95) ? 'success' : (($dashboardData['quality_performance']['inspections']['avg_yield_rate'] >= 85) ? 'warning' : 'danger'); ?>" style="width: <?php echo $dashboardData['quality_performance']['inspections']['avg_yield_rate']; ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Defect Rate</span>
                                            <span class="badge bg-<?php echo ($dashboardData['quality_performance']['inspections']['avg_defect_rate'] <= 5) ? 'success' : (($dashboardData['quality_performance']['inspections']['avg_defect_rate'] <= 10) ? 'warning' : 'danger'); ?>">
                                                <?php echo round($dashboardData['quality_performance']['inspections']['avg_defect_rate'], 2); ?>%
                                            </span>
                                        </div>
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar bg-<?php echo ($dashboardData['quality_performance']['inspections']['avg_defect_rate'] <= 5) ? 'success' : (($dashboardData['quality_performance']['inspections']['avg_defect_rate'] <= 10) ? 'warning' : 'danger'); ?>" style="width: <?php echo $dashboardData['quality_performance']['inspections']['avg_defect_rate']; ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-chart-line"></i>
                                        <strong>Quality Score: <?php echo $dashboardData['quality_performance']['overall_quality_score']; ?>%</strong>
                                        <br>
                                        <small>Based on yield, defect rate, and processing efficiency</small>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-chart-bar fa-2x mb-3"></i>
                                        <p>No quality performance data available.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Improvement Projects -->
                <div class="row">
                    <div class="col-12">
                        <div class="quality-card">
                            <div class="quality-header">
                                <h5 class="card-title mb-0">Quality Improvement Projects</h5>
                            </div>
                            <div class="quality-body">
                                <?php if (!empty($dashboardData['improvement_projects'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Project Name</th>
                                                    <th>Type</th>
                    <th>Priority</th>
                                                    <th>Progress</th>
                                                    <th>Timeline</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dashboardData['improvement_projects'] as $project): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $project['methodology']))); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($project['priority'] === 'critical') ? 'danger' : (($project['priority'] === 'high') ? 'warning' : (($project['priority'] === 'medium') ? 'info' : 'secondary')); ?>">
                                                            <?php echo ucfirst($project['priority']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $progress = $this->calculateProjectProgress($project);
                                                        ?>
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-<?php echo ($progress >= 75) ? 'success' : (($progress >= 50) ? 'info' : 'warning'); ?>" style="width: <?php echo $progress; ?>%"></div>
                                                        </div>
                                                        <small class="d-block mt-1"><?php echo $progress; ?>%</small>
                                                    </td>
                                                    <td>
                                                        <small><?php echo date('M j', strtotime($project['start_date'])); ?></small>
                                                        <small class="text-muted"> → <?php echo date('M j', strtotime($project['target_end_date'])); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($project['status'] === 'completed') ? 'success' : (($project['status'] === 'in_progress') ? 'primary' : 'secondary'); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-project-diagram fa-2x mb-3"></i>
                                        <p>No active improvement projects.</p>
                                        <small>Start a continuous improvement initiative to enhance quality.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SPC Analysis Modal -->
    <div class="modal fade" id="spcAnalysisModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Statistical Process Control Analysis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="spcAnalysisForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="perform_spc_analysis">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="parameter_id" class="form-label">Parameter ID</label>
                            <input type="text" class="form-control" id="parameter_id" name="parameter_id" placeholder="e.g., TEMP_01, PRESSURE_01" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            SPC analysis requires at least 10 data points for reliable results. Make sure sufficient sensor data exists for the parameter.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-chart-line"></i> Perform Analysis
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh quality data every 2 minutes
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

        // Placeholder functions for modal interactions
        function viewSPCChart(chartId) {
            console.log('View SPC chart:', chartId);
            // Implementation would show detailed SPC chart
        }

        function calculateProjectProgress(project) {
            const start = new Date(project.start_date);
            const end = new Date(project.target_end_date);
            const today = new Date();

            const totalDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
            const elapsedDays = Math.ceil((today - start) / (1000 * 60 * 60 * 24));

            return Math.min(100, Math.max(0, (elapsedDays / totalDays) * 100));
        }

        // Animate quality cards on page load
        document.addEventListener('DOMContentLoaded', function() {
            const metricDisplays = document.querySelectorAll('.metric-display');
            metricDisplays.forEach((display, index) => {
                display.style.opacity = '0';
                display.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    display.style.transition = 'all 0.5s ease';
                    display.style.opacity = '1';
                    display.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>