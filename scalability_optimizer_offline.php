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
        error_log('CSRF validation failed in scalability_optimizer.php');
        die('Security validation failed');
    }
}

// Authentication and Authorization
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    header('Location: index_lan.php');
    exit;
}

// Check permissions (Admin and Executive roles only)
if (!in_array($_SESSION['user_role'], ['executive', 'admin'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied. You do not have permission to access scalability optimization.');
}

/**
 * Scalability Optimization Engine for Large-Scale Production
 * Handles 16+ production lines with optimal performance and resource allocation
 */
class ScalabilityOptimizer {
    private $conn;
    private $userRole;
    private $maxLines = 50; // Maximum supported lines
    private $performanceMetrics = [];
    private $resourceConstraints = [];
    private $optimizationStrategies = [];

    public function __construct($conn, $userRole) {
        $this->conn = $conn;
        $this->userRole = $userRole;
        $this->initializeScalabilityDatabase();
        $this->loadCurrentConfiguration();
        $this->defineOptimizationStrategies();
    }

    /**
     * Initialize scalability optimization database tables
     */
    private function initializeScalabilityDatabase() {
        // Create line capacity planning table
        $createCapacityTable = "CREATE TABLE IF NOT EXISTS line_capacity_planning (
            id INT AUTO_INCREMENT PRIMARY KEY,
            line_number INT NOT NULL,
            line_shift VARCHAR(50) NOT NULL,
            theoretical_capacity DECIMAL(10,2) NOT NULL,
            practical_capacity DECIMAL(10,2) NOT NULL,
            current_utilization DECIMAL(5,2) DEFAULT 0,
            optimal_utilization DECIMAL(5,2) DEFAULT 85.0,
            capacity_buffer DECIMAL(5,2) DEFAULT 15.0,
            bottleneck_risk ENUM('low', 'medium', 'high') DEFAULT 'medium',
            expansion_readiness ENUM('ready', 'partial', 'not_ready') DEFAULT 'partial',
            last_assessment TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            next_assessment TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 1 MONTH),
            FOREIGN KEY (line_shift) REFERENCES daily_performance(line_shift),
            INDEX idx_line_number (line_number),
            INDEX idx_utilization (current_utilization),
            INDEX idx_bottleneck_risk (bottleneck_risk)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createCapacityTable);

        // Create resource pool management table
        $createResourcePoolTable = "CREATE TABLE IF NOT EXISTS resource_pools (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pool_type ENUM('manpower', 'equipment', 'materials', 'space') NOT NULL,
            pool_name VARCHAR(255) NOT NULL,
            total_capacity DECIMAL(10,2) NOT NULL,
            allocated_capacity DECIMAL(10.2) DEFAULT 0,
            available_capacity DECIMAL(10,2) GENERATED ALWAYS AS (total_capacity - allocated_capacity) STORED,
            utilization_rate DECIMAL(5,2) GENERATED ALWAYS AS ((allocated_capacity / total_capacity) * 100) STORED,
            critical_threshold DECIMAL(5,2) DEFAULT 85.0,
            allocation_strategy ENUM('fixed', 'dynamic', 'priority_based') DEFAULT 'dynamic',
            cross_line_sharing BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pool_type (pool_type),
            INDEX idx_utilization_rate (utilization_rate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createResourcePoolTable);

        // Create line performance scaling table
        $createScalingTable = "CREATE TABLE IF NOT EXISTS line_performance_scaling (
            id INT AUTO_INCREMENT PRIMARY KEY,
            line_shift VARCHAR(50) NOT NULL,
            scaling_factor DECIMAL(5,3) DEFAULT 1.0,
            base_efficiency DECIMAL(5,2) DEFAULT 100.0,
            current_efficiency DECIMAL(5,2) DEFAULT 100.0,
            efficiency_decay_rate DECIMAL(5,3) DEFAULT 0.001,
            optimal_batch_size INT DEFAULT 1000,
            max_batch_size INT DEFAULT 5000,
            batch_sizing_strategy ENUM('fixed', 'variable', 'demand_based') DEFAULT 'variable',
            multi_shift_coordination BOOLEAN DEFAULT TRUE,
            handover_efficiency DECIMAL(5,2) DEFAULT 95.0,
            last_optimized TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            optimization_frequency ENUM('daily', 'weekly', 'monthly') DEFAULT 'weekly',
            FOREIGN KEY (line_shift) REFERENCES daily_performance(line_shift),
            INDEX idx_scaling_factor (scaling_factor),
            INDEX idx_efficiency (current_efficiency)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createScalingTable);

        // Create system load monitoring table
        $createLoadTable = "CREATE TABLE IF NOT EXISTS system_load_monitoring (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            total_active_lines INT NOT NULL,
            total_production_rate DECIMAL(10,2) NOT NULL,
            system_efficiency DECIMAL(5,2) NOT NULL,
            resource_utilization JSON,
            bottlenecks_detected JSON,
            load_distribution JSON,
            system_health_score DECIMAL(5,2) NOT NULL,
            performance_baseline DECIMAL(5,2),
            variance_from_baseline DECIMAL(5,2),
            alerts_generated JSON,
            response_time_ms INT DEFAULT 0,
            data_throughput_mb DECIMAL(10,2) DEFAULT 0,
            INDEX idx_timestamp (timestamp),
            INDEX idx_system_health_score (system_health_score),
            INDEX idx_total_active_lines (total_active_lines)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createLoadTable);

        // Create scaling recommendations table
        $createRecommendationsTable = "CREATE TABLE IF NOT EXISTS scaling_recommendations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recommendation_type ENUM('capacity_expansion', 'resource_reallocation', 'process_optimization', 'technology_upgrade', 'organizational_change') NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            affected_lines JSON,
            impact_assessment JSON,
            implementation_cost JSON,
            timeline_months INT,
            priority ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            roi_estimate DECIMAL(5,2),
            risk_assessment JSON,
            dependencies JSON,
            status ENUM('pending', 'approved', 'in_progress', 'completed', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT NOT NULL,
            reviewed_by INT NULL,
            reviewed_at TIMESTAMP NULL,
            implementation_start TIMESTAMP NULL,
            expected_completion TIMESTAMP NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_recommendation_type (recommendation_type),
            INDEX idx_priority (priority),
            INDEX idx_status (status),
            INDEX idx_timeline_months (timeline_months)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createRecommendationsTable);

        // Create multi-shift coordination table
        $createCoordinationTable = "CREATE TABLE IF NOT EXISTS multi_shift_coordination (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coordination_date DATE NOT NULL,
            shift_sequence JSON NOT NULL,
            handover_plans JSON,
            resource_transfer JSON,
            production_handover JSON,
            quality_handover JSON,
            maintenance_coordination JSON,
            bottleneck_handover JSON,
            coordination_efficiency DECIMAL(5,2),
            handover_issues JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY idx_coordination_date (coordination_date),
            INDEX idx_coordination_efficiency (coordination_efficiency)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createCoordinationTable);
    }

    /**
     * Load current system configuration and performance data
     */
    private function loadCurrentConfiguration() {
        // Get current production lines configuration
        $linesQuery = "SELECT
                         line_number,
                         line_name,
                         process_category,
                         daily_capacity,
                         manning_level,
                         current_status
                       FROM production_lines
                       ORDER BY line_number";

        $result = $this->conn->query($linesQuery);
        $this->currentLines = $result->fetch_all(MYSQLI_ASSOC);

        // Get current performance metrics
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
                            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                            ORDER BY line_shift, date";

        $result = $this->conn->query($performanceQuery);
        $this->performanceMetrics = $result->fetch_all(MYSQLI_ASSOC);

        // Get resource pool data
        $resourceQuery = "SELECT
                            pool_type,
                            pool_name,
                            total_capacity,
                            allocated_capacity,
                            available_capacity,
                            utilization_rate,
                            critical_threshold
                          FROM resource_pools";

        $result = $this->conn->query($resourceQuery);
        $this->resourcePools = $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Define optimization strategies for large-scale operations
     */
    private function defineOptimizationStrategies() {
        $this->optimizationStrategies = [
            'dynamic_resource_allocation' => [
                'name' => 'Dynamic Resource Allocation',
                'description' => 'Automatically allocate resources based on real-time demand',
                'priority' => 'high',
                'complexity' => 'medium',
                'expected_improvement' => '15-25%'
            ],
            'intelligent_batch_sizing' => [
                'name' => 'Intelligent Batch Sizing',
                'description' => 'Optimize batch sizes based on line efficiency and capacity',
                'priority' => 'high',
                'complexity' => 'medium',
                'expected_improvement' => '10-20%'
            ],
            'predictive_maintenance' => [
                'name' => 'Predictive Maintenance Scheduling',
                'description' => 'Schedule maintenance based on equipment usage and failure patterns',
                'priority' => 'medium',
                'complexity' => 'high',
                'expected_improvement' => '20-30%'
            ],
            'load_balancing' => [
                'name' => 'Load Balancing Across Lines',
                'description' => 'Distribute production load optimally across available lines',
                'priority' => 'critical',
                'complexity' => 'high',
                'expected_improvement' => '25-35%'
            ],
            'multi_shift_optimization' => [
                'name' => 'Multi-Shift Coordination',
                'description' => 'Optimize handovers and resource sharing between shifts',
                'priority' => 'medium',
                'complexity' => 'medium',
                'expected_improvement' => '10-15%'
            ],
            'bottleneck_elimination' => [
                'name' => 'Bottleneck Elimination',
                'description' => 'Identify and eliminate production bottlenecks proactively',
                'priority' => 'critical',
                'complexity' => 'high',
                'expected_improvement' => '30-40%'
            ]
        ];
    }

    /**
     * Perform comprehensive scalability analysis
     */
    public function performScalabilityAnalysis() {
        $analysis = [
            'current_capacity' => $this->analyzeCurrentCapacity(),
            'performance_scaling' => $this->analyzePerformanceScaling(),
            'resource_utilization' => $this->analyzeResourceUtilization(),
            'bottleneck_analysis' => $this->identifyBottlenecks(),
            'growth_readiness' => $this->assessGrowthReadiness(),
            'optimization_opportunities' => $this->identifyOptimizationOpportunities(),
            'system_health' => $this->calculateSystemHealth(),
            'scaling_recommendations' => []
        ];

        // Generate specific recommendations based on analysis
        $analysis['scaling_recommendations'] = $this->generateScalingRecommendations($analysis);

        // Store analysis results for historical tracking
        $this->storeScalabilityAnalysis($analysis);

        return $analysis;
    }

    /**
     * Analyze current system capacity and utilization
     */
    private function analyzeCurrentCapacity() {
        $capacityAnalysis = [
            'total_lines' => count($this->currentLines),
            'active_lines' => 0,
            'total_theoretical_capacity' => 0,
            'total_practical_capacity' => 0,
            'current_utilization' => 0,
            'capacity_utilization_by_category' => [],
            'expansion_headroom' => 0,
            'capacity_distribution' => []
        ];

        // Calculate capacity metrics
        foreach ($this->currentLines as $line) {
            if ($line['current_status'] === 'active') {
                $capacityAnalysis['active_lines']++;
                $capacityAnalysis['total_theoretical_capacity'] += $line['daily_capacity'];
                $capacityAnalysis['total_practical_capacity'] += $line['daily_capacity'] * 0.85; // 85% practical capacity
            }

            // Group by process category
            $category = $line['process_category'];
            if (!isset($capacityAnalysis['capacity_utilization_by_category'][$category])) {
                $capacityAnalysis['capacity_utilization_by_category'][$category] = [
                    'lines' => 0,
                    'capacity' => 0,
                    'current_output' => 0
                ];
            }
            $capacityAnalysis['capacity_utilization_by_category'][$category]['lines']++;
            $capacityAnalysis['capacity_utilization_by_category'][$category]['capacity'] += $line['daily_capacity'];
        }

        // Calculate current utilization from performance data
        $totalCurrentOutput = array_sum(array_column($this->performanceMetrics, 'actual_output'));
        $totalPlan = array_sum(array_column($this->performanceMetrics, 'plan'));

        if ($capacityAnalysis['total_practical_capacity'] > 0) {
            $capacityAnalysis['current_utilization'] = ($totalCurrentOutput / $capacityAnalysis['total_practical_capacity']) * 100;
        }

        $capacityAnalysis['expansion_headroom'] = max(0, $capacityAnalysis['total_practical_capacity'] - $totalCurrentOutput);
        $capacityAnalysis['utilization_efficiency'] = $totalPlan > 0 ? ($totalCurrentOutput / $totalPlan) * 100 : 0;

        return $capacityAnalysis;
    }

    /**
     * Analyze performance scaling patterns
     */
    private function analyzePerformanceScaling() {
        $scalingAnalysis = [
            'efficiency_trends' => [],
            'scaling_factors' => [],
            'performance_degradation' => [],
            'optimal_line_count' => 0,
            'scaling_efficiency' => 0,
            'batch_size_optimization' => [],
            'multi_shift_performance' => []
        ];

        // Group performance data by line
        $linePerformance = [];
        foreach ($this->performanceMetrics as $metric) {
            $linePerformance[$metric['line_shift']][] = $metric;
        }

        // Analyze scaling patterns for each line
        foreach ($linePerformance as $lineShift => $data) {
            if (count($data) < 3) continue; // Need sufficient data points

            $efficiencies = array_column($data, 'efficiency');
            $outputs = array_column($data, 'actual_output');

            // Calculate efficiency trend (simplified linear regression)
            $scalingAnalysis['efficiency_trends'][$lineShift] = $this->calculateTrend($efficiencies);

            // Determine scaling factor based on output variance
            $outputVariance = $this->calculateVariance($outputs);
            $meanOutput = array_sum($outputs) / count($outputs);
            $scalingFactor = $meanOutput > 0 ? 1 / (1 + ($outputVariance / ($meanOutput * $meanOutput))) : 1.0;

            $scalingAnalysis['scaling_factors'][$lineShift] = $scalingFactor;

            // Check for performance degradation at higher loads
            $maxOutput = max($outputs);
            $maxEfficiencyIndex = array_search(max($efficiencies), $efficiencies);
            $optimalOutput = $outputs[$maxEfficiencyIndex];

            if ($maxOutput > 0 && $optimalOutput > 0) {
                $degradation = (($maxOutput - $optimalOutput) / $maxOutput) * 100;
                $scalingAnalysis['performance_degradation'][$lineShift] = $degradation;
            }
        }

        // Calculate overall scaling efficiency
        if (!empty($scalingAnalysis['scaling_factors'])) {
            $scalingAnalysis['scaling_efficiency'] = array_sum($scalingAnalysis['scaling_factors']) / count($scalingAnalysis['scaling_factors']);
        }

        // Determine optimal number of active lines based on efficiency
        $optimalLines = 0;
        $maxSystemEfficiency = 0;

        for ($i = 1; $i <= min(16, count($this->currentLines)); $i++) {
            $systemEfficiency = $this->estimateSystemEfficiency($i);
            if ($systemEfficiency > $maxSystemEfficiency) {
                $maxSystemEfficiency = $systemEfficiency;
                $optimalLines = $i;
            }
        }

        $scalingAnalysis['optimal_line_count'] = $optimalLines;

        return $scalingAnalysis;
    }

    /**
     * Analyze resource utilization and allocation
     */
    private function analyzeResourceUtilization() {
        $resourceAnalysis = [
            'overall_utilization' => 0,
            'resource_constraints' => [],
            'allocation_efficiency' => 0,
            'resource_pool_analysis' => [],
            'critical_resources' => [],
            'optimization_potential' => []
        ];

        if (empty($this->resourcePools)) {
            // Initialize default resource pools if they don't exist
            $this->initializeDefaultResourcePools();
            $this->loadCurrentConfiguration(); // Reload to get the pools
        }

        $totalUtilization = 0;
        $poolCount = 0;

        foreach ($this->resourcePools as $pool) {
            $utilizationRate = $pool['utilization_rate'];
            $totalUtilization += $utilizationRate;
            $poolCount++;

            // Identify resource constraints
            if ($utilizationRate > $pool['critical_threshold']) {
                $resourceAnalysis['resource_constraints'][] = [
                    'pool_type' => $pool['pool_type'],
                    'pool_name' => $pool['pool_name'],
                    'utilization_rate' => $utilizationRate,
                    'critical_threshold' => $pool['critical_threshold'],
                    'severity' => $utilizationRate > 95 ? 'critical' : 'high'
                ];
            }

            $resourceAnalysis['resource_pool_analysis'][] = [
                'pool_type' => $pool['pool_type'],
                'pool_name' => $pool['pool_name'],
                'total_capacity' => $pool['total_capacity'],
                'allocated_capacity' => $pool['allocated_capacity'],
                'available_capacity' => $pool['available_capacity'],
                'utilization_rate' => $utilizationRate,
                'headroom_percentage' => (($pool['available_capacity'] / $pool['total_capacity']) * 100)
            ];

            // Identify critical resources (high utilization + low availability)
            if ($utilizationRate > 80 && $pool['available_capacity'] < ($pool['total_capacity'] * 0.2)) {
                $resourceAnalysis['critical_resources'][] = [
                    'pool_type' => $pool['pool_type'],
                    'pool_name' => $pool['pool_name'],
                    'utilization_rate' => $utilizationRate,
                    'available_capacity' => $pool['available_capacity'],
                    'impact_level' => 'high'
                ];
            }
        }

        $resourceAnalysis['overall_utilization'] = $poolCount > 0 ? $totalUtilization / $poolCount : 0;
        $resourceAnalysis['allocation_efficiency'] = $this->calculateAllocationEfficiency();

        return $resourceAnalysis;
    }

    /**
     * Identify system bottlenecks affecting scalability
     */
    private function identifyBottlenecks() {
        $bottleneckAnalysis = [
            'current_bottlenecks' => [],
            'potential_bottlenecks' => [],
            'bottleneck_impact' => [],
            'resolution_priority' => []
        ];

        // Analyze performance data for bottleneck patterns
        $linePerformance = [];
        foreach ($this->performanceMetrics as $metric) {
            $linePerformance[$metric['line_shift']][] = $metric;
        }

        foreach ($linePerformance as $lineShift => $data) {
            if (count($data) < 5) continue; // Need sufficient data

            // Calculate metrics
            $avgEfficiency = array_sum(array_column($data, 'efficiency')) / count($data);
            $avgCompletion = array_sum(array_column($data, 'plan_completion')) / count($data);
            $avgDowntime = array_sum(array_column($data, 'machine_downtime')) / count($data);
            $efficiencyStdDev = $this->calculateStandardDeviation(array_column($data, 'efficiency'));

            // Identify bottleneck indicators
            $bottlenecks = [];

            if ($avgEfficiency < 75) {
                $bottlenecks[] = [
                    'type' => 'efficiency',
                    'severity' => $avgEfficiency < 60 ? 'critical' : 'high',
                    'value' => $avgEfficiency,
                    'impact' => 'Directly affecting production output'
                ];
            }

            if ($avgDowntime > 60) {
                $bottlenecks[] = [
                    'type' => 'downtime',
                    'severity' => $avgDowntime > 120 ? 'critical' : 'high',
                    'value' => $avgDowntime,
                    'impact' => 'Reducing available production time'
                ];
            }

            if ($efficiencyStdDev > 20) {
                $bottlenecks[] = [
                    'type' => 'inconsistency',
                    'severity' => $efficiencyStdDev > 30 ? 'critical' : 'high',
                    'value' => $efficiencyStdDev,
                    'impact' => 'Unpredictable performance affecting planning'
                ];
            }

            if ($avgCompletion < 85) {
                $bottlenecks[] = [
                    'type' => 'plan_completion',
                    'severity' => $avgCompletion < 70 ? 'critical' : 'high',
                    'value' => $avgCompletion,
                    'impact' => 'Missing production targets'
                ];
            }

            if (!empty($bottlenecks)) {
                $bottleneckAnalysis['current_bottlenecks'][$lineShift] = $bottlenecks;
            }
        }

        // Identify potential bottlenecks as system scales
        $bottleneckAnalysis['potential_bottlenecks'] = $this->predictScalingBottlenecks();

        // Calculate bottleneck impact on overall system
        $totalBottleneckImpact = 0;
        $bottleneckCount = 0;

        foreach ($bottleneckAnalysis['current_bottlenecks'] as $lineBottlenecks) {
            foreach ($lineBottlenecks as $bottleneck) {
                $impact = $this->calculateBottleneckImpact($bottleneck['type'], $bottleneck['value']);
                $bottleneckAnalysis['bottleneck_impact'][] = [
                    'line_shift' => key($bottleneckAnalysis['current_bottlenecks']),
                    'type' => $bottleneck['type'],
                    'severity' => $bottleneck['severity'],
                    'impact_score' => $impact,
                    'estimated_production_loss' => $this->estimateProductionLoss($bottleneck['type'], $bottleneck['value'])
                ];
                $totalBottleneckImpact += $impact;
                $bottleneckCount++;
            }
        }

        // Set resolution priority based on impact
        $bottleneckAnalysis['resolution_priority'] = $this->prioritizeBottleneckResolution($bottleneckAnalysis['bottleneck_impact']);

        return $bottleneckAnalysis;
    }

    /**
     * Assess system readiness for growth and expansion
     */
    private function assessGrowthReadiness() {
        $growthReadiness = [
            'current_scale_score' => 0,
            'expansion_capacity' => 0,
            'growth_limitations' => [],
            'readiness_factors' => [],
            'expansion_timeline' => [],
            'investment_requirements' => []
        ];

        // Calculate current scale score (0-100)
        $currentLines = count($this->currentLines);
        $activeLines = array_filter($this->currentLines, function($line) {
            return $line['current_status'] === 'active';
        });
        $activeLinesCount = count($activeLines);

        $scaleScore = min(100, ($activeLinesCount / $this->maxLines) * 100);
        $growthReadiness['current_scale_score'] = $scaleScore;

        // Assess expansion capacity
        $totalCapacity = array_sum(array_column($this->currentLines, 'daily_capacity'));
        $currentUtilization = $this->calculateCurrentSystemUtilization();
        $availableCapacity = $totalCapacity * (1 - $currentUtilization / 100);

        $growthReadiness['expansion_capacity'] = ($availableCapacity / $totalCapacity) * 100;

        // Identify growth limitations
        if ($currentUtilization > 85) {
            $growthReadiness['growth_limitations'][] = [
                'factor' => 'High Current Utilization',
                'impact' => 'Limited capacity for growth',
                'severity' => 'high'
            ];
        }

        if ($activeLinesCount > ($this->maxLines * 0.8)) {
            $growthReadiness['growth_limitations'][] = [
                'factor' => 'Approaching Line Limit',
                'impact' => 'Near maximum line capacity',
                'severity' => 'critical'
            ];
        }

        // Assess readiness factors
        $readinessFactors = [
            'resource_availability' => $this->assessResourceAvailability(),
            'system_performance' => $this->assessSystemPerformance(),
            'management_readiness' => $this->assessManagementReadiness(),
            'technology_readiness' => $this->assessTechnologyReadiness(),
            'quality_system_readiness' => $this->assessQualitySystemReadiness()
        ];

        $growthReadiness['readiness_factors'] = $readinessFactors;

        // Calculate overall readiness score
        $readinessScore = array_sum(array_column($readinessFactors, 'score')) / count($readinessFactors);
        $growthReadiness['overall_readiness_score'] = $readinessScore;

        // Generate expansion timeline based on readiness
        $growthReadiness['expansion_timeline'] = $this->generateExpansionTimeline($readinessScore);

        // Estimate investment requirements
        $growthReadiness['investment_requirements'] = $this->estimateInvestmentRequirements($activeLinesCount, $this->maxLines);

        return $growthReadiness;
    }

    /**
     * Identify optimization opportunities for scalability
     */
    private function identifyOptimizationOpportunities() {
        $opportunities = [
            'immediate_opportunities' => [],
            'medium_term_opportunities' => [],
            'long_term_opportunities' => [],
            'cost_benefit_analysis' => [],
            'implementation_roadmap' => []
        ];

        // Identify immediate opportunities (can be implemented within 1 month)
        $opportunities['immediate_opportunities'] = [
            [
                'opportunity' => 'Load Balancing',
                'description' => 'Redistribute production load across underutilized lines',
                'expected_improvement' => '10-15%',
                'implementation_complexity' => 'low',
                'estimated_cost' => 'Low',
                'timeline' => '2-4 weeks'
            ],
            [
                'opportunity' => 'Batch Size Optimization',
                'description' => 'Optimize batch sizes based on current line efficiency',
                'expected_improvement' => '8-12%',
                'implementation_complexity' => 'low',
                'estimated_cost' => 'Low',
                'timeline' => '1-2 weeks'
            ],
            [
                'opportunity' => 'Resource Pool Sharing',
                'description' => 'Enable cross-line resource sharing for manpower and equipment',
                'expected_improvement' => '5-10%',
                'implementation_complexity' => 'medium',
                'estimated_cost' => 'Medium',
                'timeline' => '3-4 weeks'
            ]
        ];

        // Identify medium-term opportunities (1-6 months)
        $opportunities['medium_term_opportunities'] = [
            [
                'opportunity' => 'Predictive Maintenance Implementation',
                'description' => 'Implement data-driven maintenance scheduling',
                'expected_improvement' => '15-20%',
                'implementation_complexity' => 'high',
                'estimated_cost' => 'High',
                'timeline' => '3-6 months'
            ],
            [
                'opportunity' => 'Multi-Shift Optimization',
                'description' => 'Optimize shift coordination and handover processes',
                'expected_improvement' => '8-15%',
                'implementation_complexity' => 'medium',
                'estimated_cost' => 'Medium',
                'timeline' => '2-4 months'
            ],
            [
                'opportunity' => 'Real-time Monitoring System',
                'description' => 'Implement comprehensive real-time production monitoring',
                'expected_improvement' => '12-18%',
                'implementation_complexity' => 'high',
                'estimated_cost' => 'High',
                'timeline' => '4-6 months'
            ]
        ];

        // Identify long-term opportunities (6+ months)
        $opportunities['long_term_opportunities'] = [
            [
                'opportunity' => 'Line Automation Upgrades',
                'description' => 'Upgrade production lines with advanced automation',
                'expected_improvement' => '25-40%',
                'implementation_complexity' => 'very_high',
                'estimated_cost' => 'Very High',
                'timeline' => '12-24 months'
            ],
            [
                'opportunity' => 'Production Line Expansion',
                'description' => 'Add new production lines to increase capacity',
                'expected_improvement' => '50-100%',
                'implementation_complexity' => 'very_high',
                'estimated_cost' => 'Very High',
                'timeline' => '18-36 months'
            ],
            [
                'opportunity' => 'Integrated Manufacturing System',
                'description' => 'Implement fully integrated manufacturing execution system',
                'expected_improvement' => '20-35%',
                'implementation_complexity' => 'very_high',
                'estimated_cost' => 'Very High',
                'timeline' => '12-18 months'
            ]
        ];

        // Generate cost-benefit analysis
        $opportunities['cost_benefit_analysis'] = $this->performCostBenefitAnalysis($opportunities);

        // Create implementation roadmap
        $opportunities['implementation_roadmap'] = $this->createImplementationRoadmap($opportunities);

        return $opportunities;
    }

    /**
     * Calculate overall system health score
     */
    private function calculateSystemHealth() {
        $healthFactors = [
            'efficiency_score' => $this->calculateEfficiencyScore(),
            'utilization_score' => $this->calculateUtilizationScore(),
            'reliability_score' => $this->calculateReliabilityScore(),
            'scalability_score' => $this->calculateScalabilityScore(),
            'resource_score' => $this->calculateResourceScore()
        ];

        $totalScore = array_sum($healthFactors);
        $overallHealth = $totalScore / count($healthFactors);

        return [
            'overall_health_score' => $overallHealth,
            'health_factors' => $healthFactors,
            'health_grade' => $this->getHealthGrade($overallHealth),
            'critical_issues' => $this->identifyCriticalHealthIssues($healthFactors),
            'improvement_priorities' => $this->prioritizeHealthImprovements($healthFactors)
        ];
    }

    /**
     * Generate scaling recommendations based on analysis
     */
    private function generateScalingRecommendations($analysis) {
        $recommendations = [];

        // Capacity expansion recommendations
        if ($analysis['current_capacity']['current_utilization'] > 85) {
            $recommendations[] = [
                'recommendation_type' => 'capacity_expansion',
                'title' => 'Expand Production Capacity',
                'description' => 'Current utilization is high (' . round($analysis['current_capacity']['current_utilization'], 1) . '%). Consider capacity expansion.',
                'priority' => 'high',
                'timeline_months' => 6,
                'roi_estimate' => 25.0,
                'affected_lines' => array_column($this->currentLines, 'line_shift')
            ];
        }

        // Resource optimization recommendations
        if (!empty($analysis['resource_utilization']['resource_constraints'])) {
            $recommendations[] = [
                'recommendation_type' => 'resource_reallocation',
                'title' => 'Optimize Resource Allocation',
                'description' => 'Multiple resource constraints identified. Implement dynamic resource allocation.',
                'priority' => 'critical',
                'timeline_months' => 3,
                'roi_estimate' => 35.0,
                'impact_assessment' => $analysis['resource_utilization']['resource_constraints']
            ];
        }

        // Bottleneck elimination recommendations
        if (!empty($analysis['bottleneck_analysis']['current_bottlenecks'])) {
            $recommendations[] = [
                'recommendation_type' => 'process_optimization',
                'title' => 'Eliminate Production Bottlenecks',
                'description' => 'Critical bottlenecks identified. Immediate attention required.',
                'priority' => 'critical',
                'timeline_months' => 2,
                'roi_estimate' => 40.0,
                'impact_assessment' => $analysis['bottleneck_analysis']['bottleneck_impact']
            ];
        }

        // Technology upgrade recommendations
        if ($analysis['system_health']['overall_health_score'] < 70) {
            $recommendations[] = [
                'recommendation_type' => 'technology_upgrade',
                'title' => 'System Technology Upgrade',
                'description' => 'System health score is low. Technology upgrades needed.',
                'priority' => 'medium',
                'timeline_months' => 12,
                'roi_estimate' => 30.0,
                'impact_assessment' => ['current_health' => $analysis['system_health']['overall_health_score']]
            ];
        }

        // Store recommendations in database
        foreach ($recommendations as $recommendation) {
            $this->storeScalingRecommendation($recommendation);
        }

        return $recommendations;
    }

    /**
     * Helper methods for calculations and analysis
     */

    private function calculateTrend($values) {
        if (count($values) < 2) return 0;

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
        return $slope;
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

    private function calculateStandardDeviation($values) {
        return sqrt($this->calculateVariance($values));
    }

    private function estimateSystemEfficiency($activeLines) {
        // Simplified efficiency model
        $baseEfficiency = 100;
        $complexityPenalty = pow($activeLines / 10, 1.2) * 5; // Complexity increases with more lines
        $coordinationBenefit = min(15, $activeLines * 0.5); // Coordination benefits up to a point

        return max(60, $baseEfficiency - $complexityPenalty + $coordinationBenefit);
    }

    private function initializeDefaultResourcePools() {
        $defaultPools = [
            ['pool_type' => 'manpower', 'pool_name' => 'Operators', 'total_capacity' => 500, 'critical_threshold' => 85],
            ['pool_type' => 'equipment', 'pool_name' => 'Machinery', 'total_capacity' => 100, 'critical_threshold' => 90],
            ['pool_type' => 'materials', 'pool_name' => 'Raw Materials', 'total_capacity' => 10000, 'critical_threshold' => 80],
            ['pool_type' => 'space', 'pool_name' => 'Production Space', 'total_capacity' => 50000, 'critical_threshold' => 85]
        ];

        foreach ($defaultPools as $pool) {
            $query = "INSERT INTO resource_pools (pool_type, pool_name, total_capacity, critical_threshold)
                      VALUES (?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("ssii", $pool['pool_type'], $pool['pool_name'], $pool['total_capacity'], $pool['critical_threshold']);
            $stmt->execute();
        }
    }

    private function calculateAllocationEfficiency() {
        // Simplified allocation efficiency calculation
        $totalCapacity = array_sum(array_column($this->resourcePools, 'total_capacity'));
        $totalAllocated = array_sum(array_column($this->resourcePools, 'allocated_capacity'));

        if ($totalCapacity > 0) {
            return ($totalAllocated / $totalCapacity) * 100;
        }
        return 0;
    }

    private function predictScalingBottlenecks() {
        // Simplified bottleneck prediction
        $predictedBottlenecks = [];

        if (count($this->currentLines) > 12) {
            $predictedBottlenecks[] = [
                'type' => 'coordination_complexity',
                'predicted_impact' => 'high',
                'trigger_point' => '15+ active lines',
                'mitigation' => 'Implement advanced coordination systems'
            ];
        }

        if ($this->calculateCurrentSystemUtilization() > 75) {
            $predictedBottlenecks[] = [
                'type' => 'resource_exhaustion',
                'predicted_impact' => 'critical',
                'trigger_point' => '85%+ system utilization',
                'mitigation' => 'Expand resource pools or optimize allocation'
            ];
        }

        return $predictedBottlenecks;
    }

    private function calculateBottleneckImpact($type, $value) {
        // Impact calculation based on type and severity
        switch ($type) {
            case 'efficiency':
                return (100 - $value) * 1.5;
            case 'downtime':
                return min(100, $value / 2);
            case 'inconsistency':
                return $value * 0.8;
            case 'plan_completion':
                return (100 - $value) * 1.2;
            default:
                return 50;
        }
    }

    private function estimateProductionLoss($type, $value) {
        // Simplified production loss estimation
        $baseCapacity = array_sum(array_column($this->currentLines, 'daily_capacity'));

        switch ($type) {
            case 'efficiency':
                return $baseCapacity * ((100 - $value) / 100) * 0.5;
            case 'downtime':
                return $baseCapacity * ($value / 480) * 0.8; // Assuming 8-hour shifts
            case 'plan_completion':
                return $baseCapacity * ((100 - $value) / 100);
            default:
                return $baseCapacity * 0.1;
        }
    }

    private function prioritizeBottleneckResolution($impacts) {
        // Sort bottlenecks by impact score
        usort($impacts, function($a, $b) {
            return $b['impact_score'] <=> $a['impact_score'];
        });

        return array_slice($impacts, 0, 10); // Return top 10 priority bottlenecks
    }

    private function calculateCurrentSystemUtilization() {
        $totalOutput = array_sum(array_column($this->performanceMetrics, 'actual_output'));
        $totalCapacity = array_sum(array_column($this->currentLines, 'daily_capacity'));

        return $totalCapacity > 0 ? ($totalOutput / $totalCapacity) * 100 : 0;
    }

    private function assessResourceAvailability() {
        $availableResources = array_sum(array_column($this->resourcePools, 'available_capacity'));
        $totalResources = array_sum(array_column($this->resourcePools, 'total_capacity'));

        $availabilityScore = $totalResources > 0 ? ($availableResources / $totalResources) * 100 : 0;

        return [
            'score' => $availabilityScore,
            'assessment' => $availabilityScore > 70 ? 'good' : ($availabilityScore > 40 ? 'moderate' : 'poor'),
            'details' => 'Resource availability for expansion'
        ];
    }

    private function assessSystemPerformance() {
        $efficiencies = array_column($this->performanceMetrics, 'efficiency');
        $avgEfficiency = count($efficiencies) > 0 ? array_sum($efficiencies) / count($efficiencies) : 0;

        return [
            'score' => $avgEfficiency,
            'assessment' => $avgEfficiency > 80 ? 'excellent' : ($avgEfficiency > 60 ? 'good' : 'poor'),
            'details' => 'Current system efficiency performance'
        ];
    }

    private function assessManagementReadiness() {
        // Simplified management readiness assessment
        return [
            'score' => 75, // Placeholder - would be based on actual assessment
            'assessment' => 'good',
            'details' => 'Management capability for scaled operations'
        ];
    }

    private function assessTechnologyReadiness() {
        // Simplified technology readiness assessment
        return [
            'score' => 70, // Placeholder - would be based on actual assessment
            'assessment' => 'good',
            'details' => 'Technology infrastructure readiness for scaling'
        ];
    }

    private function assessQualitySystemReadiness() {
        // Simplified quality system readiness assessment
        return [
            'score' => 80, // Placeholder - would be based on actual assessment
            'assessment' => 'excellent',
            'details' => 'Quality management system readiness for increased scale'
        ];
    }

    private function generateExpansionTimeline($readinessScore) {
        if ($readinessScore > 80) {
            return [
                'immediate' => 'Can add 1-2 lines immediately',
                'short_term' => 'Can expand by 25% in 6 months',
                'long_term' => 'Can double capacity in 2 years'
            ];
        } elseif ($readinessScore > 60) {
            return [
                'immediate' => 'Focus on optimization first',
                'short_term' => 'Can add 1 line in 6 months',
                'long_term' => 'Can expand by 50% in 2 years'
            ];
        } else {
            return [
                'immediate' => 'Address critical issues first',
                'short_term' => 'Stabilize current operations',
                'long_term' => 'Consider expansion after improvements'
            ];
        }
    }

    private function estimateInvestmentRequirements($currentLines, $maxLines) {
        $lineCapacity = 1000000; // $1M per line - placeholder
        $infrastructureCost = 500000; // $500K for infrastructure
        $trainingCost = $currentLines * 10000; // $10K per existing line for training

        return [
            'per_line_expansion' => $lineCapacity,
            'infrastructure_upgrade' => $infrastructureCost,
            'training_programs' => $trainingCost,
            'total_estimated' => ($lineCapacity * 2) + $infrastructureCost + $trainingCost
        ];
    }

    private function calculateEfficiencyScore() {
        $efficiencies = array_column($this->performanceMetrics, 'efficiency');
        return count($efficiencies) > 0 ? min(100, array_sum($efficiencies) / count($efficiencies)) : 0;
    }

    private function calculateUtilizationScore() {
        $utilization = $this->calculateCurrentSystemUtilization();
        // Optimal utilization is around 75-85%
        if ($utilization >= 75 && $utilization <= 85) {
            return 100;
        } elseif ($utilization < 75) {
            return ($utilization / 75) * 100;
        } else {
            return max(0, 100 - (($utilization - 85) * 2));
        }
    }

    private function calculateReliabilityScore() {
        // Simplified reliability based on downtime
        $downtimes = array_column($this->performanceMetrics, 'machine_downtime');
        $avgDowntime = count($downtimes) > 0 ? array_sum($downtimes) / count($downtimes) : 0;
        return max(0, 100 - ($avgDowntime / 4)); // 4 hours of downtime = 0 score
    }

    private function calculateScalabilityScore() {
        $activeLines = array_filter($this->currentLines, function($line) {
            return $line['current_status'] === 'active';
        });
        $activeLineCount = count($activeLines);

        // Score based on current scale vs. optimal scale
        $optimalRange = [8, 16];
        if ($activeLineCount >= $optimalRange[0] && $activeLineCount <= $optimalRange[1]) {
            return 100;
        } else {
            $distance = min(abs($activeLineCount - $optimalRange[0]), abs($activeLineCount - $optimalRange[1]));
            return max(0, 100 - ($distance * 10));
        }
    }

    private function calculateResourceScore() {
        if (empty($this->resourcePools)) return 50;

        $totalUtilization = array_sum(array_column($this->resourcePools, 'utilization_rate'));
        $avgUtilization = $totalUtilization / count($this->resourcePools);

        // Optimal resource utilization is around 70-80%
        if ($avgUtilization >= 70 && $avgUtilization <= 80) {
            return 100;
        } elseif ($avgUtilization < 70) {
            return ($avgUtilization / 70) * 100;
        } else {
            return max(0, 100 - (($avgUtilization - 80) * 2));
        }
    }

    private function getHealthGrade($score) {
        if ($score >= 90) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 80) return 'B+';
        if ($score >= 75) return 'B';
        if ($score >= 70) return 'C+';
        if ($score >= 65) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }

    private function identifyCriticalHealthIssues($healthFactors) {
        $criticalIssues = [];

        foreach ($healthFactors as $factor => $score) {
            if ($score < 60) {
                $criticalIssues[] = [
                    'factor' => $factor,
                    'score' => $score,
                    'severity' => $score < 40 ? 'critical' : 'high'
                ];
            }
        }

        return $criticalIssues;
    }

    private function prioritizeHealthImprovements($healthFactors) {
        // Sort factors by score (lowest first)
        asort($healthFactors);
        return array_keys($healthFactors);
    }

    private function performCostBenefitAnalysis($opportunities) {
        // Simplified cost-benefit analysis
        return [
            'highest_roi' => 'Load Balancing (immediate opportunity)',
            'fastest_implementation' => 'Batch Size Optimization',
            'greatest_impact' => 'Line Automation Upgrades (long-term)',
            'best_value' => 'Resource Pool Sharing'
        ];
    }

    private function createImplementationRoadmap($opportunities) {
        return [
            'phase_1' => [
                'timeline' => '0-3 months',
                'opportunities' => $opportunities['immediate_opportunities'],
                'focus' => 'Quick wins and optimization'
            ],
            'phase_2' => [
                'timeline' => '3-12 months',
                'opportunities' => $opportunities['medium_term_opportunities'],
                'focus' => 'System improvements and technology upgrades'
            ],
            'phase_3' => [
                'timeline' => '12+ months',
                'opportunities' => $opportunities['long_term_opportunities'],
                'focus' => 'Major expansion and transformation'
            ]
        ];
    }

    private function storeScalabilityAnalysis($analysis) {
        $query = "INSERT INTO system_load_monitoring
                  (total_active_lines, total_production_rate, system_efficiency, resource_utilization,
                   bottlenecks_detected, load_distribution, system_health_score, performance_baseline,
                   variance_from_baseline)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $activeLines = count(array_filter($this->currentLines, function($line) {
            return $line['current_status'] === 'active';
        }));

        $productionRate = array_sum(array_column($this->performanceMetrics, 'actual_output'));
        $systemEfficiency = $analysis['system_health']['overall_health_score'];
        $resourceUtilization = json_encode($analysis['resource_utilization']);
        $bottlenecks = json_encode($analysis['bottleneck_analysis']['current_bottlenecks']);
        $loadDistribution = json_encode($analysis['current_capacity']['capacity_distribution']);
        $baseline = 85; // Target baseline
        $variance = $systemEfficiency - $baseline;

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "iddssssdd",
            $activeLines,
            $productionRate,
            $systemEfficiency,
            $resourceUtilization,
            $bottlenecks,
            $loadDistribution,
            $systemEfficiency,
            $baseline,
            $variance
        );

        $stmt->execute();
    }

    private function storeScalingRecommendation($recommendation) {
        $query = "INSERT INTO scaling_recommendations
                  (recommendation_type, title, description, affected_lines, impact_assessment,
                   implementation_cost, timeline_months, priority, roi_estimate, created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sssssdsidi",
            $recommendation['recommendation_type'],
            $recommendation['title'],
            $recommendation['description'],
            json_encode($recommendation['affected_lines'] ?? []),
            json_encode($recommendation['impact_assessment'] ?? []),
            json_encode($recommendation['implementation_cost'] ?? 'medium'),
            $recommendation['timeline_months'],
            $recommendation['priority'],
            $recommendation['roi_estimate'],
            $_SESSION['user_id']
        );

        $stmt->execute();
    }

    /**
     * Get scalability dashboard data
     */
    public function getDashboardData() {
        $analysis = $this->performScalabilityAnalysis();

        return [
            'scalability_analysis' => $analysis,
            'system_metrics' => [
                'total_lines' => count($this->currentLines),
                'active_lines' => count(array_filter($this->currentLines, function($line) {
                    return $line['current_status'] === 'active';
                })),
                'system_health_score' => $analysis['system_health']['overall_health_score'],
                'current_utilization' => $this->calculateCurrentSystemUtilization(),
                'expansion_readiness' => $analysis['growth_readiness']['overall_readiness_score'] ?? 0
            ],
            'optimization_strategies' => $this->optimizationStrategies,
            'performance_trends' => $this->getPerformanceTrends(),
            'recommendations_summary' => $this->getRecommendationsSummary()
        ];
    }

    private function getPerformanceTrends() {
        // Get recent performance trends
        $trendsQuery = "SELECT
                           DATE(created_at) as date,
                           AVG(system_health_score) as avg_health,
                           AVG(total_active_lines) as avg_lines,
                           AVG(total_production_rate) as avg_production
                        FROM system_load_monitoring
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        GROUP BY DATE(created_at)
                        ORDER BY date DESC
                        LIMIT 10";

        $result = $this->conn->query($trendsQuery);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function getRecommendationsSummary() {
        $query = "SELECT
                    recommendation_type,
                    COUNT(*) as count,
                    AVG(roi_estimate) as avg_roi,
                    priority
                  FROM scaling_recommendations
                  WHERE status = 'pending'
                  GROUP BY recommendation_type, priority
                  ORDER BY priority DESC, avg_roi DESC";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Page logic
$scalabilityOptimizer = new ScalabilityOptimizer($conn, $userRole);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'run_analysis':
            $dashboardData = $scalabilityOptimizer->getDashboardData();
            $success = true;
            $message = "Scalability analysis completed successfully.";
            break;

        case 'generate_recommendations':
            $analysis = $scalabilityOptimizer->performScalabilityAnalysis();
            $recommendationsCount = count($analysis['scaling_recommendations']);
            $success = true;
            $message = "Generated {$recommendationsCount} scaling recommendations.";
            break;

        default:
            $success = false;
            $message = 'Invalid action';
    }

    // Redirect with message
    header('Location: scalability_optimizer_offline.php?success=' . ($success ? '1' : '0') . '&message=' . urlencode($message));
    exit;
}

// Get dashboard data
$dashboardData = $scalabilityOptimizer->getDashboardData();

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
    <title>Scalability Optimizer - Production Management System</title>
    <?php getInlineCSS(); ?>
    <style>
        .scalability-card { border: 1px solid #dee2e6; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .scalability-header { background-color: #f8f9fa; padding: 1rem; border-bottom: 1px solid #dee2e6; }
        .scalability-body { padding: 1.5rem; }
        .health-meter { width: 100%; height: 30px; background: #e9ecef; border-radius: 15px; overflow: hidden; }
        .health-meter-fill { height: 100%; transition: width 0.3s ease; }
        .health-excellent { background: linear-gradient(90deg, #28a745, #20c997); }
        .health-good { background: linear-gradient(90deg, #17a2b8, #28a745); }
        .health-fair { background: linear-gradient(90deg, #ffc107, #fd7e14); }
        .health-poor { background: linear-gradient(90deg, #dc3545, #fd7e14); }
        .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .metric-card { background: white; border: 1px solid #e9ecef; border-radius: 0.5rem; padding: 1.5rem; text-align: center; }
        .metric-value { font-size: 2.5rem; font-weight: bold; margin-bottom: 0.5rem; }
        .metric-label { font-size: 0.875rem; color: #6c757d; margin-bottom: 1rem; }
        .metric-change { font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; }
        .change-positive { background: #d4edda; color: #155724; }
        .change-negative { background: #f8d7da; color: #721c24; }
        .opportunity-card { border: 1px solid #e9ecef; border-radius: 0.375rem; padding: 1.25rem; margin-bottom: 1rem; }
        .opportunity-card:hover { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); }
        .timeline-item { position: relative; padding-left: 2rem; padding-bottom: 1.5rem; }
        .timeline-item::before { content: ''; position: absolute; left: 0; top: 0.5rem; width: 12px; height: 12px; border-radius: 50%; background: #0d6efd; }
        .timeline-item::after { content: ''; position: absolute; left: 5px; top: 1.5rem; width: 2px; height: calc(100% - 0.5rem); background: #e9ecef; }
        .timeline-item:last-child::after { display: none; }
        .recommendation-priority { padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .priority-critical { background: #dc3545; color: white; }
        .priority-high { background: #fd7e14; color: white; }
        .priority-medium { background: #ffc107; color: black; }
        .priority-low { background: #28a745; color: white; }
        .scalability-controls { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        @media (max-width: 768px) {
            .scalability-controls { flex-direction: column; }
            .metric-grid { grid-template-columns: 1fr; }
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
                    <h1 class="h3">Scalability Optimizer</h1>
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

                <!-- Scalability Controls -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Analysis Controls</h5>
                    </div>
                    <div class="card-body">
                        <div class="scalability-controls">
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="run_analysis">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-chart-line"></i> Run Scalability Analysis
                                </button>
                            </form>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="generate_recommendations">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-lightbulb"></i> Generate Recommendations
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- System Health Overview -->
                <div class="row mb-4">
                    <div class="col-lg-4">
                        <div class="scalability-card">
                            <div class="scalability-header">
                                <h5 class="card-title mb-0">System Health Score</h5>
                            </div>
                            <div class="scalability-body text-center">
                                <div class="metric-value mb-3"><?php echo number_format($dashboardData['system_metrics']['system_health_score'], 1); ?>%</div>
                                <div class="health-meter mb-3">
                                    <div class="health-meter-fill <?php echo $this->getHealthClass($dashboardData['system_metrics']['system_health_score']); ?>"
                                         style="width: <?php echo $dashboardData['system_metrics']['system_health_score']; ?>%"></div>
                                </div>
                                <h6>Health Grade: <?php echo $this->getHealthGrade($dashboardData['system_metrics']['system_health_score']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="scalability-card">
                            <div class="scalability-header">
                                <h5 class="card-title mb-0">System Metrics</h5>
                            </div>
                            <div class="scalability-body">
                                <div class="metric-grid">
                                    <div class="metric-card">
                                        <div class="metric-value"><?php echo $dashboardData['system_metrics']['total_lines']; ?></div>
                                        <div class="metric-label">Total Lines</div>
                                        <div class="metric-change change-positive"><?php echo $dashboardData['system_metrics']['active_lines']; ?> Active</div>
                                    </div>
                                    <div class="metric-card">
                                        <div class="metric-value"><?php echo number_format($dashboardData['system_metrics']['current_utilization'], 1); ?>%</div>
                                        <div class="metric-label">Current Utilization</div>
                                        <div class="metric-change <?php echo $dashboardData['system_metrics']['current_utilization'] > 85 ? 'change-negative' : 'change-positive'; ?>">
                                            <?php echo $dashboardData['system_metrics']['current_utilization'] > 85 ? 'High Usage' : 'Optimal'; ?>
                                        </div>
                                    </div>
                                    <div class="metric-card">
                                        <div class="metric-value"><?php echo number_format($dashboardData['system_metrics']['expansion_readiness'], 1); ?>%</div>
                                        <div class="metric-label">Expansion Readiness</div>
                                        <div class="metric-change <?php echo $dashboardData['system_metrics']['expansion_readiness'] > 70 ? 'change-positive' : 'change-negative'; ?>">
                                            <?php echo $dashboardData['system_metrics']['expansion_readiness'] > 70 ? 'Ready' : 'Needs Work'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Capacity Analysis -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="scalability-card">
                            <div class="scalability-header">
                                <h5 class="card-title mb-0">Current Capacity Analysis</h5>
                            </div>
                            <div class="scalability-body">
                                <?php if (!empty($dashboardData['scalability_analysis']['current_capacity'])): ?>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <strong>Total Lines:</strong> <?php echo $dashboardData['scalability_analysis']['current_capacity']['total_lines']; ?>
                                        </div>
                                        <div class="col-6">
                                            <strong>Active Lines:</strong> <?php echo $dashboardData['scalability_analysis']['current_capacity']['active_lines']; ?>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <strong>Theoretical Capacity:</strong> <?php echo number_format($dashboardData['scalability_analysis']['current_capacity']['total_theoretical_capacity']); ?>
                                        </div>
                                        <div class="col-6">
                                            <strong>Practical Capacity:</strong> <?php echo number_format($dashboardData['scalability_analysis']['current_capacity']['total_practical_capacity']); ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <strong>Current Utilization:</strong> <?php echo number_format($dashboardData['scalability_analysis']['current_capacity']['current_utilization'], 1); ?>%
                                        </div>
                                        <div class="col-6">
                                            <strong>Expansion Headroom:</strong> <?php echo number_format($dashboardData['scalability_analysis']['current_capacity']['expansion_headroom']); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Run analysis to see capacity details.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="scalability-card">
                            <div class="scalability-header">
                                <h5 class="card-title mb-0">Bottleneck Analysis</h5>
                            </div>
                            <div class="scalability-body">
                                <?php if (!empty($dashboardData['scalability_analysis']['bottleneck_analysis']['current_bottlenecks'])): ?>
                                    <?php
                                    $bottleneckCount = 0;
                                    foreach ($dashboardData['scalability_analysis']['bottleneck_analysis']['current_bottlenecks'] as $line => $bottlenecks) {
                                        $bottleneckCount += count($bottlenecks);
                                    }
                                    ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <strong><?php echo $bottleneckCount; ?> bottlenecks identified</strong> requiring attention
                                    </div>
                                    <div class="list-group">
                                        <?php
                                        $displayCount = 0;
                                        foreach ($dashboardData['scalability_analysis']['bottleneck_analysis']['current_bottlenecks'] as $line => $bottlenecks) {
                                            if ($displayCount >= 5) break;
                                            foreach ($bottlenecks as $bottleneck) {
                                                if ($displayCount >= 5) break;
                                                echo '<div class="list-group-item">';
                                                echo '<strong>' . htmlspecialchars($line) . '</strong> - ' . ucfirst($bottleneck['type']);
                                                echo '<span class="badge bg-' . ($bottleneck['severity'] === 'critical' ? 'danger' : 'warning') . ' ms-2">' . $bottleneck['severity'] . '</span>';
                                                echo '</div>';
                                                $displayCount++;
                                            }
                                        }
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No bottlenecks detected or analysis not run.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Optimization Opportunities -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="scalability-card">
                            <div class="scalability-header">
                                <h5 class="card-title mb-0">Optimization Strategies</h5>
                            </div>
                            <div class="scalability-body">
                                <div class="row">
                                    <?php foreach ($dashboardData['optimization_strategies'] as $strategy): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="opportunity-card">
                                            <h6><?php echo htmlspecialchars($strategy['name']); ?></h6>
                                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($strategy['description']); ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge bg-primary"><?php echo $strategy['expected_improvement']; ?></span>
                                                <span class="badge bg-secondary"><?php echo ucfirst($strategy['complexity']); ?></span>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">Priority: </small>
                                                <span class="recommendation-priority priority-<?php echo $strategy['priority']; ?>"><?php echo $strategy['priority']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scaling Recommendations -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="scalability-card">
                            <div class="scalability-header">
                                <h5 class="card-title mb-0">Scaling Recommendations</h5>
                            </div>
                            <div class="scalability-body">
                                <?php if (!empty($dashboardData['scalability_analysis']['scaling_recommendations'])): ?>
                                    <div class="row">
                                        <?php foreach (array_slice($dashboardData['scalability_analysis']['scaling_recommendations'], 0, 6) as $recommendation): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title"><?php echo htmlspecialchars($recommendation['title']); ?></h6>
                                                        <span class="recommendation-priority priority-<?php echo $recommendation['priority']; ?>">
                                                            <?php echo $recommendation['priority']; ?>
                                                        </span>
                                                    </div>
                                                    <p class="card-text small text-muted">
                                                        <?php echo htmlspecialchars(substr($recommendation['description'], 0, 100)) . '...'; ?>
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">ROI: <?php echo $recommendation['roi_estimate']; ?>%</small>
                                                        <small class="text-muted"><?php echo $recommendation['timeline_months']; ?> months</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No scaling recommendations available. Run analysis to generate recommendations.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Implementation Roadmap -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="scalability-card">
                            <div class="scalability-header">
                                <h5 class="card-title mb-0">Implementation Roadmap</h5>
                            </div>
                            <div class="scalability-body">
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <h6>Phase 1: Quick Wins (0-3 months)</h6>
                                        <p class="text-muted mb-2">Focus on immediate improvements and optimizations</p>
                                        <ul class="small mb-0">
                                            <li>Load balancing across production lines</li>
                                            <li>Batch size optimization</li>
                                            <li>Resource pool sharing implementation</li>
                                        </ul>
                                    </div>
                                    <div class="timeline-item">
                                        <h6>Phase 2: System Improvements (3-12 months)</h6>
                                        <p class="text-muted mb-2">Enhance systems and technology capabilities</p>
                                        <ul class="small mb-0">
                                            <li>Predictive maintenance implementation</li>
                                            <li>Multi-shift optimization</li>
                                            <li>Real-time monitoring system</li>
                                        </ul>
                                    </div>
                                    <div class="timeline-item">
                                        <h6>Phase 3: Major Expansion (12+ months)</h6>
                                        <p class="text-muted mb-2">Transformative changes and capacity expansion</p>
                                        <ul class="small mb-0">
                                            <li>Line automation upgrades</li>
                                            <li>Production line expansion</li>
                                            <li>Integrated manufacturing system</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="scalability-card">
                            <div class="scalability-header">
                                <h5 class="card-title mb-0">Performance Trends</h5>
                            </div>
                            <div class="scalability-body">
                                <?php if (!empty($dashboardData['performance_trends'])): ?>
                                    <div class="list-group">
                                        <?php foreach (array_slice($dashboardData['performance_trends'], 0, 5) as $trend): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo date('M j', strtotime($trend['date'])); ?></strong>
                                                    <br>
                                                    <small class="text-muted">Health: <?php echo number_format($trend['avg_health'], 1); ?>%</small>
                                                </div>
                                                <div class="text-end">
                                                    <small><?php echo number_format($trend['avg_lines']); ?> lines</small>
                                                    <br>
                                                    <small><?php echo number_format($trend['avg_production']); ?> units</small>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No trend data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Status Summary -->
                <div class="row">
                    <div class="col-12">
                        <div class="scalability-card">
                            <div class="scalability-header">
                                <h5 class="card-title mb-0">System Status Summary</h5>
                            </div>
                            <div class="scalability-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="<?php echo $dashboardData['system_metrics']['system_health_score'] > 70 ? 'text-success' : ($dashboardData['system_metrics']['system_health_score'] > 50 ? 'text-warning' : 'text-danger'); ?>">
                                                <?php echo $this->getHealthGrade($dashboardData['system_metrics']['system_health_score']); ?>
                                            </h4>
                                            <small class="text-muted">Overall Health</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="<?php echo $dashboardData['system_metrics']['current_utilization'] < 85 ? 'text-success' : 'text-warning'; ?>">
                                                <?php echo $dashboardData['system_metrics']['current_utilization'] > 85 ? 'High Load' : 'Optimal'; ?>
                                            </h4>
                                            <small class="text-muted">Current Load</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="<?php echo $dashboardData['system_metrics']['expansion_readiness'] > 70 ? 'text-success' : 'text-warning'; ?>">
                                                <?php echo $dashboardData['system_metrics']['expansion_readiness'] > 70 ? 'Ready' : 'Not Ready'; ?>
                                            </h4>
                                            <small class="text-muted">Growth Ready</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h4 class="text-info">
                                                <?php echo $dashboardData['system_metrics']['active_lines']; ?>/<?php echo $this->maxLines; ?>
                                            </h4>
                                            <small class="text-muted">Lines Active</small>
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
        // Auto-refresh page every 5 minutes for updated scalability data
        setTimeout(function() {
            window.location.reload();
        }, 300000);

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

        // Animate health meter on page load
        document.addEventListener('DOMContentLoaded', function() {
            const healthMeters = document.querySelectorAll('.health-meter-fill');
            healthMeters.forEach(meter => {
                const width = meter.style.width;
                meter.style.width = '0%';
                setTimeout(() => {
                    meter.style.width = width;
                }, 100);
            });
        });
    </script>
</body>
</html>

<?php
// Helper method for health class (moved outside class definition for use in view)
function getHealthClass($score) {
    if ($score >= 85) return 'health-excellent';
    if ($score >= 70) return 'health-good';
    if ($score >= 55) return 'health-fair';
    return 'health-poor';
}

function getHealthGrade($score) {
    if ($score >= 90) return 'A+';
    if ($score >= 85) return 'A';
    if ($score >= 80) return 'B+';
    if ($score >= 75) return 'B';
    if ($score >= 70) return 'C+';
    if ($score >= 65) return 'C';
    if ($score >= 60) return 'D';
    return 'F';
}
?>