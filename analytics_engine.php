<?php
// analytics_engine.php - Advanced analytics engine for production insights and bottleneck detection

require_once "config.php";

class ProductionAnalytics {
    private $db;

    public function __construct() {
        $database = Database::getInstance();
        $this->db = $database->getConnection();
    }

    /**
     * Main analytics processor - runs all analysis functions
     */
    public function runAnalytics() {
        $results = [];

        // Production performance analysis
        $results['performance'] = $this->analyzeProductionPerformance();

        // Bottleneck detection and analysis
        $results['bottlenecks'] = $this->detectBottlenecks();

        // OEE calculation and analysis
        $results['oee'] = $this->calculateOEE();

        // Trend analysis
        $results['trends'] = $this->analyzeTrends();

        // Predictive analytics
        $results['predictions'] = $this->generatePredictions();

        // Quality analysis
        $results['quality'] = $this->analyzeQualityMetrics();

        // Efficiency analysis
        $results['efficiency'] = $this->analyzeEfficiency();

        // Manpower analysis
        $results['manpower'] = $this->analyzeManpowerUtilization();

        return $results;
    }

    /**
     * Analyze overall production performance
     */
    public function analyzeProductionPerformance($period = 'today') {
        try {
            $date_filter = $this->getDateFilter($period);

            $query = "
                SELECT
                    dp.line_shift,
                    dp.leader,
                    dp.mp,
                    dp.absent,
                    dp.separated_mp,
                    dp.plan,
                    dp.no_ot_mp,
                    dp.ot_mp,
                    dp.ot_hours,
                    (SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id) as actual_output,
                    (SELECT COALESCE(SUM(ap.assy_output * p.circuit), 0) FROM assy_performance ap JOIN products p ON ap.product_id = p.id WHERE ap.daily_performance_id = dp.id) as circuit_output,
                    dp.date
                FROM daily_performance dp
                WHERE {$date_filter}
                ORDER BY dp.date DESC, dp.line_shift
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $production_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $analysis = [
                'summary' => [],
                'by_line' => [],
                'performance_metrics' => [],
                'recommendations' => []
            ];

            $total_lines = count($production_data);
            $total_plan = 0;
            $total_actual = 0;
            $total_mp = 0;
            $total_absent = 0;
            $low_performing_lines = [];

            foreach ($production_data as $record) {
                // Calculate performance metrics
                $used_mhr = PerformanceCalculator::calculateUsedMHR($record['no_ot_mp'], $record['ot_mp'], $record['ot_hours']);
                $plan_completion = PerformanceCalculator::calculatePlanCompletion($record['actual_output'], $record['plan']);
                $efficiency = PerformanceCalculator::calculateEfficiency($record['actual_output'], $used_mhr);
                $cph = PerformanceCalculator::calculateCPH($record['circuit_output'], $used_mhr);
                $absent_rate = ($record['mp'] > 0) ? ($record['absent'] / $record['mp']) * 100 : 0;

                // Accumulate totals
                $total_plan += $record['plan'];
                $total_actual += $record['actual_output'];
                $total_mp += $record['mp'];
                $total_absent += $record['absent'];

                // Identify low performing lines
                if ($plan_completion < 70 || $efficiency < 50) {
                    $low_performing_lines[] = [
                        'line' => $record['line_shift'],
                        'leader' => $record['leader'],
                        'plan_completion' => $plan_completion,
                        'efficiency' => $efficiency,
                        'issues' => $this->identifyPerformanceIssues($record, $plan_completion, $efficiency)
                    ];
                }

                $analysis['by_line'][] = [
                    'line_shift' => $record['line_shift'],
                    'leader' => $record['leader'],
                    'plan' => $record['plan'],
                    'actual' => $record['actual_output'],
                    'plan_completion' => round($plan_completion, 2),
                    'efficiency' => round($efficiency, 2),
                    'cph' => round($cph, 2),
                    'absent_rate' => round($absent_rate, 2),
                    'manning' => $record['mp'] - $record['absent'] - $record['separated_mp'],
                    'performance_rating' => $this->getPerformanceRating($plan_completion, $efficiency)
                ];
            }

            // Calculate overall metrics
            $overall_plan_completion = $total_plan > 0 ? ($total_actual / $total_plan) * 100 : 0;
            $overall_absent_rate = $total_mp > 0 ? ($total_absent / $total_mp) * 100 : 0;

            $analysis['summary'] = [
                'total_lines' => $total_lines,
                'overall_plan_completion' => round($overall_plan_completion, 2),
                'total_plan' => $total_plan,
                'total_actual' => $total_actual,
                'overall_absent_rate' => round($overall_absent_rate, 2),
                'low_performing_lines_count' => count($low_performing_lines)
            ];

            $analysis['performance_metrics'] = $low_performing_lines;
            $analysis['recommendations'] = $this->generatePerformanceRecommendations($low_performing_lines, $overall_plan_completion);

            return $analysis;

        } catch(PDOException $e) {
            error_log("Production Performance Analysis Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Detect and analyze production bottlenecks
     */
    public function detectBottlenecks() {
        try {
            $bottlenecks = [];
            $current_date = date('Y-m-d');

            // Check efficiency bottlenecks
            $efficiency_query = "
                SELECT
                    dp.line_shift,
                    dp.leader,
                    (SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id) as actual_output,
                    dp.no_ot_mp,
                    dp.ot_mp,
                    dp.ot_hours,
                    dp.plan
                FROM daily_performance dp
                WHERE dp.date = :current_date
                AND dp.plan > 0
            ";

            $stmt = $this->db->prepare($efficiency_query);
            $stmt->execute(['current_date' => $current_date]);
            $efficiency_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($efficiency_data as $record) {
                $used_mhr = PerformanceCalculator::calculateUsedMHR($record['no_ot_mp'], $record['ot_mp'], $record['ot_hours']);
                $efficiency = PerformanceCalculator::calculateEfficiency($record['actual_output'], $used_mhr);

                // Detect efficiency bottleneck
                if ($efficiency < 40) {
                    $bottlenecks[] = [
                        'type' => 'efficiency',
                        'line' => $record['line_shift'],
                        'leader' => $record['leader'],
                        'severity' => $efficiency < 25 ? 'critical' : 'high',
                        'description' => "Very low efficiency ({$efficiency}%) detected",
                        'impact' => [
                            'efficiency_percentage' => $efficiency,
                            'actual_output' => $record['actual_output'],
                            'target_output' => round($record['plan'] * ($efficiency / 100)),
                            'production_loss' => round($record['plan'] - ($record['plan'] * ($efficiency / 100)))
                        ],
                        'recommendations' => [
                            'Review manpower allocation',
                            'Check equipment performance',
                            'Analyze production process',
                            'Provide additional training'
                        ]
                    ];

                    // Log to bottlenecks table
                    $this->logBottleneck([
                        'bottleneck_type' => 'process',
                        'affected_production_line' => $record['line_shift'],
                        'bottleneck_description' => "Low efficiency: {$efficiency}%",
                        'impact_level' => $efficiency < 25 ? 'critical' : 'high',
                        'production_loss_units' => round($record['plan'] - ($record['plan'] * ($efficiency / 100))),
                        'root_cause_analysis' => 'Detected by automated monitoring system'
                    ]);
                }
            }

            // Check manpower bottlenecks
            $manpower_query = "
                SELECT
                    dp.line_shift,
                    dp.leader,
                    dp.mp,
                    dp.absent,
                    dp.separated_mp,
                    dp.plan
                FROM daily_performance dp
                WHERE dp.date = :current_date
            ";

            $stmt = $this->db->prepare($manpower_query);
            $stmt->execute(['current_date' => $current_date]);
            $manpower_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($manpower_data as $record) {
                $effective_manning = $record['mp'] - $record['absent'] - $record['separated_mp'];
                $manning_rate = ($record['mp'] > 0) ? ($effective_manning / $record['mp']) * 100 : 0;

                if ($manning_rate < 80) {
                    $bottlenecks[] = [
                        'type' => 'manpower',
                        'line' => $record['line_shift'],
                        'leader' => $record['leader'],
                        'severity' => $manning_rate < 60 ? 'critical' : 'high',
                        'description' => "Insufficient manpower coverage ({$manning_rate}%)",
                        'impact' => [
                            'total_mp' => $record['mp'],
                            'absent' => $record['absent'],
                            'separated' => $record['separated_mp'],
                            'effective_manning' => $effective_manning,
                            'manning_rate' => $manning_rate
                        ],
                        'recommendations' => [
                            'Review absenteeism patterns',
                            'Cross-train staff',
                            'Implement backup workforce',
                            'Review shift scheduling'
                        ]
                    ];

                    $this->logBottleneck([
                        'bottleneck_type' => 'manpower',
                        'affected_production_line' => $record['line_shift'],
                        'bottleneck_description' => "Manpower shortage: {$manning_rate}%",
                        'impact_level' => $manning_rate < 60 ? 'critical' : 'high'
                    ]);
                }
            }

            // Check quality bottlenecks
            $quality_query = "
                SELECT
                    qc.checkpoint_name,
                    qc.production_line,
                    COUNT(qm.id) as total_checks,
                    SUM(CASE WHEN qm.measurement_status = 'fail' THEN 1 ELSE 0 END) as failures,
                    SUM(qm.defect_count) as total_defects
                FROM quality_measurements qm
                JOIN quality_checkpoints qc ON qm.checkpoint_id = qc.id
                WHERE DATE(qm.measurement_time) = :current_date
                GROUP BY qc.checkpoint_name, qc.production_line
                HAVING (failures / total_checks) > 0.05 OR total_defects > 10
            ";

            $stmt = $this->db->prepare($quality_query);
            $stmt->execute(['current_date' => $current_date]);
            $quality_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($quality_data as $record) {
                $failure_rate = ($record['total_checks'] > 0) ? ($record['failures'] / $record['total_checks']) * 100 : 0;

                $bottlenecks[] = [
                    'type' => 'quality',
                    'line' => $record['production_line'],
                    'description' => "High failure rate at {$record['checkpoint_name']}",
                    'severity' => $failure_rate > 10 ? 'critical' : 'high',
                    'impact' => [
                        'checkpoint' => $record['checkpoint_name'],
                        'total_checks' => $record['total_checks'],
                        'failures' => $record['failures'],
                        'failure_rate' => $failure_rate,
                        'total_defects' => $record['total_defects']
                    ],
                    'recommendations' => [
                        'Review quality procedures',
                        'Provide additional training',
                        'Check equipment calibration',
                        'Analyze root causes'
                    ]
                ];

                $this->logBottleneck([
                    'bottleneck_type' => 'quality',
                    'affected_production_line' => $record['production_line'],
                    'bottleneck_description' => "Quality issues at {$record['checkpoint_name']}: {$failure_rate}% failure rate",
                    'impact_level' => $failure_rate > 10 ? 'critical' : 'high'
                ]);
            }

            return [
                'total_bottlenecks' => count($bottlenecks),
                'bottlenecks' => $bottlenecks,
                'critical_count' => count(array_filter($bottlenecks, fn($b) => $b['severity'] === 'critical')),
                'high_count' => count(array_filter($bottlenecks, fn($b) => $b['severity'] === 'high')),
                'by_type' => $this->groupBottlenecksByType($bottlenecks)
            ];

        } catch(PDOException $e) {
            error_log("Bottleneck Detection Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Calculate OEE (Overall Equipment Effectiveness)
     */
    public function calculateOEE($period = 'today') {
        try {
            $date_filter = $this->getDateFilter($period);

            $query = "
                SELECT
                    dp.line_shift,
                    dp.date,
                    dp.plan,
                    (SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id) as actual_output,
                    dp.no_ot_mp,
                    dp.ot_mp,
                    dp.ot_hours,
                    (SELECT COUNT(*) FROM maintenance_schedules ms WHERE ms.production_line = dp.line_shift AND ms.status = 'in_progress' AND DATE(ms.created_at) = dp.date) as maintenance_count
                FROM daily_performance dp
                WHERE {$date_filter}
                AND dp.plan > 0
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $oee_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $oee_results = [
                'overall_oee' => 0,
                'by_line' => [],
                'components' => [
                    'availability' => 0,
                    'performance' => 0,
                    'quality' => 0
                ],
                'targets' => [
                    'world_class' => 85,
                    'good' => 75,
                    'acceptable' => 65,
                    'needs_improvement' => 50
                ]
            ];

            $total_availability = 0;
            $total_performance = 0;
            $total_quality = 0;
            $count = 0;

            foreach ($oee_data as $record) {
                // Calculate Availability (assuming 480 minutes available per shift)
                $planned_time = 480; // 8 hours in minutes
                $maintenance_time = $record['maintenance_count'] * 60; // Estimate 1 hour per maintenance
                $running_time = $planned_time - $maintenance_time;
                $availability = ($planned_time > 0) ? ($running_time / $planned_time) * 100 : 0;

                // Calculate Performance
                $used_mhr = PerformanceCalculator::calculateUsedMHR($record['no_ot_mp'], $record['ot_mp'], $record['ot_hours']);
                $ideal_cycle_time = 1; // Assuming 1 minute per unit
                $actual_cycle_time = $used_mhr > 0 ? ($used_mhr * 60) / $record['actual_output'] : 0;
                $performance = ($actual_cycle_time > 0) ? ($ideal_cycle_time / $actual_cycle_time) * 100 : 0;
                $performance = min($performance, 100); // Cap at 100%

                // Calculate Quality (simplified - assume 95% quality rate)
                $quality = 95; // Would be calculated from actual quality data

                // Calculate OEE
                $oee = ($availability * $performance * $quality) / 10000;

                $oee_results['by_line'][] = [
                    'line_shift' => $record['line_shift'],
                    'date' => $record['date'],
                    'availability' => round($availability, 2),
                    'performance' => round($performance, 2),
                    'quality' => $quality,
                    'oee' => round($oee, 2),
                    'rating' => $this->getOEERating($oee),
                    'actual_output' => $record['actual_output'],
                    'target_output' => $record['plan']
                ];

                $total_availability += $availability;
                $total_performance += $performance;
                $total_quality += $quality;
                $count++;
            }

            if ($count > 0) {
                $avg_availability = $total_availability / $count;
                $avg_performance = $total_performance / $count;
                $avg_quality = $total_quality / $count;

                $oee_results['overall_oee'] = round(($avg_availability * $avg_performance * $avg_quality) / 10000, 2);
                $oee_results['components'] = [
                    'availability' => round($avg_availability, 2),
                    'performance' => round($avg_performance, 2),
                    'quality' => round($avg_quality, 2)
                ];
            }

            return $oee_results;

        } catch(PDOException $e) {
            error_log("OEE Calculation Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Analyze production trends
     */
    public function analyzeTrends($days = 30) {
        try {
            $query = "
                SELECT
                    dp.date,
                    COUNT(*) as total_lines,
                    SUM(dp.plan) as total_plan,
                    SUM((SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id)) as total_actual,
                    AVG(dp.mp) as avg_mp,
                    SUM(dp.absent) as total_absent
                FROM daily_performance dp
                WHERE dp.date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY dp.date
                ORDER BY dp.date ASC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            $trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $trends = [
                'plan_completion_trend' => [],
                'manpower_trend' => [],
                'efficiency_trend' => [],
                'insights' => [],
                'forecasts' => []
            ];

            foreach ($trend_data as $day) {
                $plan_completion = $day['total_plan'] > 0 ? ($day['total_actual'] / $day['total_plan']) * 100 : 0;
                $absenteeism_rate = $day['avg_mp'] > 0 ? ($day['total_absent'] / ($day['avg_mp'] * $day['total_lines'])) * 100 : 0;

                $trends['plan_completion_trend'][] = [
                    'date' => $day['date'],
                    'value' => round($plan_completion, 2),
                    'plan' => $day['total_plan'],
                    'actual' => $day['total_actual']
                ];

                $trends['manpower_trend'][] = [
                    'date' => $day['date'],
                    'avg_mp' => round($day['avg_mp'], 1),
                    'absenteeism_rate' => round($absenteeism_rate, 2)
                ];
            }

            // Generate trend insights
            $trends['insights'] = $this->generateTrendInsights($trends['plan_completion_trend'], $trends['manpower_trend']);

            // Generate simple forecasts
            $trends['forecasts'] = $this->generateSimpleForecast($trends['plan_completion_trend']);

            return $trends;

        } catch(PDOException $e) {
            error_log("Trend Analysis Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Generate predictive analytics
     */
    public function generatePredictions() {
        try {
            $predictions = [];

            // Predict next week's performance based on current trends
            $trend_analysis = $this->analyzeTrends(14);

            if (!empty($trend_analysis['plan_completion_trend'])) {
                $recent_performance = array_slice($trend_analysis['plan_completion_trend'], -7);
                $avg_completion = array_sum(array_column($recent_performance, 'value')) / count($recent_performance);

                // Simple linear regression for trend
                $trend = $this->calculateTrendDirection($recent_performance);

                $predictions['next_week_performance'] = [
                    'predicted_completion_rate' => round($avg_completion + ($trend * 7), 2),
                    'confidence' => 'medium',
                    'based_on_days' => count($recent_performance),
                    'trend_direction' => $trend > 0 ? 'improving' : ($trend < 0 ? 'declining' : 'stable')
                ];
            }

            // Predict potential bottlenecks
            $current_bottlenecks = $this->detectBottlenecks();
            $predictions['bottleneck_risks'] = $this->predictBottleneckRisks($current_bottlenecks);

            // Predict maintenance needs
            $predictions['maintenance_needs'] = $this->predictMaintenanceNeeds();

            return $predictions;

        } catch(PDOException $e) {
            error_log("Predictive Analytics Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Analyze quality metrics
     */
    public function analyzeQualityMetrics($days = 7) {
        try {
            $query = "
                SELECT
                    DATE(qm.measurement_time) as measurement_date,
                    qc.checkpoint_name,
                    qc.production_line,
                    COUNT(*) as total_checks,
                    SUM(CASE WHEN qm.measurement_status = 'pass' THEN 1 ELSE 0 END) as passes,
                    SUM(CASE WHEN qm.measurement_status = 'fail' THEN 1 ELSE 0 END) as failures,
                    SUM(CASE WHEN qm.measurement_status = 'warning' THEN 1 ELSE 0 END) as warnings,
                    SUM(qm.defect_count) as total_defects
                FROM quality_measurements qm
                JOIN quality_checkpoints qc ON qm.checkpoint_id = qc.id
                WHERE qm.measurement_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(qm.measurement_time), qc.checkpoint_name, qc.production_line
                ORDER BY measurement_date DESC, qc.checkpoint_name
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            $quality_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $analysis = [
                'overall_quality_rate' => 0,
                'defect_rate' => 0,
                'by_checkpoint' => [],
                'trends' => [],
                'problem_areas' => [],
                'recommendations' => []
            ];

            $total_checks = 0;
            $total_passes = 0;
            $total_failures = 0;
            $total_defects = 0;

            foreach ($quality_data as $record) {
                $checks = $record['total_checks'];
                $passes = $record['passes'];
                $failures = $record['failures'];
                $defects = $record['total_defects'];

                $total_checks += $checks;
                $total_passes += $passes;
                $total_failures += $failures;
                $total_defects += $defects;

                $quality_rate = $checks > 0 ? ($passes / $checks) * 100 : 0;
                $defect_rate = $checks > 0 ? ($defects / $checks) * 100 : 0;

                $analysis['by_checkpoint'][] = [
                    'checkpoint' => $record['checkpoint_name'],
                    'production_line' => $record['production_line'],
                    'date' => $record['measurement_date'],
                    'total_checks' => $checks,
                    'passes' => $passes,
                    'failures' => $failures,
                    'warnings' => $record['warnings'],
                    'quality_rate' => round($quality_rate, 2),
                    'defect_rate' => round($defect_rate, 2),
                    'status' => $quality_rate >= 95 ? 'excellent' : ($quality_rate >= 90 ? 'good' : 'needs_attention')
                ];

                // Identify problem areas
                if ($quality_rate < 90 || $defect_rate > 5) {
                    $analysis['problem_areas'][] = [
                        'checkpoint' => $record['checkpoint_name'],
                        'line' => $record['production_line'],
                        'issue' => $quality_rate < 90 ? 'Low quality rate' : 'High defect rate',
                        'severity' => $quality_rate < 80 || $defect_rate > 10 ? 'critical' : 'moderate',
                        'metrics' => [
                            'quality_rate' => $quality_rate,
                            'defect_rate' => $defect_rate
                        ]
                    ];
                }
            }

            if ($total_checks > 0) {
                $analysis['overall_quality_rate'] = round(($total_passes / $total_checks) * 100, 2);
                $analysis['defect_rate'] = round(($total_defects / $total_checks) * 100, 2);
            }

            $analysis['recommendations'] = $this->generateQualityRecommendations($analysis['problem_areas']);

            return $analysis;

        } catch(PDOException $e) {
            error_log("Quality Analysis Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Analyze efficiency metrics
     */
    public function analyzeEfficiency($days = 7) {
        try {
            $query = "
                SELECT
                    dp.line_shift,
                    dp.leader,
                    dp.date,
                    dp.no_ot_mp,
                    dp.ot_mp,
                    dp.ot_hours,
                    dp.plan,
                    (SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id) as actual_output,
                    (SELECT COALESCE(SUM(ap.assy_output * p.mhr), 0) FROM assy_performance ap JOIN products p ON ap.product_id = p.id WHERE ap.daily_performance_id = dp.id) as output_mhr
                FROM daily_performance dp
                WHERE dp.date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                AND dp.plan > 0
                ORDER BY dp.date DESC, dp.line_shift
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            $efficiency_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $analysis = [
                'overall_efficiency' => 0,
                'by_line' => [],
                'efficiency_distribution' => [],
                'inefficient_areas' => [],
                'recommendations' => []
            ];

            $total_efficiency = 0;
            $count = 0;

            foreach ($efficiency_data as $record) {
                $used_mhr = PerformanceCalculator::calculateUsedMHR($record['no_ot_mp'], $record['ot_mp'], $record['ot_hours']);
                $efficiency = PerformanceCalculator::calculateEfficiency($record['output_mhr'], $used_mhr);

                $total_efficiency += $efficiency;
                $count++;

                $analysis['by_line'][] = [
                    'line_shift' => $record['line_shift'],
                    'leader' => $record['leader'],
                    'date' => $record['date'],
                    'efficiency' => round($efficiency, 2),
                    'used_mhr' => round($used_mhr, 2),
                    'output_mhr' => round($record['output_mhr'], 2),
                    'rating' => $efficiency >= 100 ? 'excellent' : ($efficiency >= 80 ? 'good' : ($efficiency >= 60 ? 'acceptable' : 'poor'))
                ];

                // Identify inefficient areas
                if ($efficiency < 60) {
                    $analysis['inefficient_areas'][] = [
                        'line' => $record['line_shift'],
                        'leader' => $record['leader'],
                        'efficiency' => $efficiency,
                        'potential_improvement' => round((100 - $efficiency), 2),
                        'root_causes' => $this->identifyInefficiencyCauses($record, $efficiency)
                    ];
                }
            }

            if ($count > 0) {
                $analysis['overall_efficiency'] = round($total_efficiency / $count, 2);
            }

            $analysis['efficiency_distribution'] = $this->calculateEfficiencyDistribution($analysis['by_line']);
            $analysis['recommendations'] = $this->generateEfficiencyRecommendations($analysis['inefficient_areas']);

            return $analysis;

        } catch(PDOException $e) {
            error_log("Efficiency Analysis Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Analyze manpower utilization
     */
    public function analyzeManpowerUtilization($days = 7) {
        try {
            $query = "
                SELECT
                    dp.line_shift,
                    dp.leader,
                    dp.date,
                    dp.mp,
                    dp.absent,
                    dp.separated_mp,
                    dp.no_ot_mp,
                    dp.ot_mp,
                    dp.ot_hours,
                    (SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id) as actual_output
                FROM daily_performance dp
                WHERE dp.date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                ORDER BY dp.date DESC, dp.line_shift
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':days', $days, PDO::PARAM_INT);
            $stmt->execute();
            $manpower_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $analysis = [
                'overall_utilization' => 0,
                'absenteeism_rate' => 0,
                'separation_rate' => 0,
                'by_line' => [],
                'issues' => [],
                'recommendations' => []
            ];

            $total_mp = 0;
            $total_absent = 0;
            $total_separated = 0;
            $total_effective_mp = 0;

            foreach ($manpower_data as $record) {
                $effective_mp = $record['mp'] - $record['absent'] - $record['separated_mp'];
                $absenteeism_rate = $record['mp'] > 0 ? ($record['absent'] / $record['mp']) * 100 : 0;
                $separation_rate = $record['mp'] > 0 ? ($record['separated_mp'] / $record['mp']) * 100 : 0;
                $utilization_rate = $record['mp'] > 0 ? ($effective_mp / $record['mp']) * 100 : 0;

                $total_mp += $record['mp'];
                $total_absent += $record['absent'];
                $total_separated += $record['separated_mp'];
                $total_effective_mp += $effective_mp;

                $analysis['by_line'][] = [
                    'line_shift' => $record['line_shift'],
                    'leader' => $record['leader'],
                    'date' => $record['date'],
                    'total_mp' => $record['mp'],
                    'absent' => $record['absent'],
                    'separated' => $record['separated_mp'],
                    'effective_mp' => $effective_mp,
                    'absenteeism_rate' => round($absenteeism_rate, 2),
                    'separation_rate' => round($separation_rate, 2),
                    'utilization_rate' => round($utilization_rate, 2),
                    'output_per_mp' => $effective_mp > 0 ? round($record['actual_output'] / $effective_mp, 2) : 0
                ];

                // Identify manpower issues
                if ($absenteeism_rate > 10 || $utilization_rate < 80) {
                    $analysis['issues'][] = [
                        'line' => $record['line_shift'],
                        'leader' => $record['leader'],
                        'type' => $absenteeism_rate > 10 ? 'high_absenteeism' : 'low_utilization',
                        'value' => max($absenteeism_rate, 100 - $utilization_rate),
                        'severity' => $absenteeism_rate > 15 || $utilization_rate < 70 ? 'critical' : 'moderate'
                    ];
                }
            }

            if ($total_mp > 0) {
                $analysis['overall_utilization'] = round(($total_effective_mp / $total_mp) * 100, 2);
                $analysis['absenteeism_rate'] = round(($total_absent / $total_mp) * 100, 2);
                $analysis['separation_rate'] = round(($total_separated / $total_mp) * 100, 2);
            }

            $analysis['recommendations'] = $this->generateManpowerRecommendations($analysis['issues']);

            return $analysis;

        } catch(PDOException $e) {
            error_log("Manpower Analysis Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Helper methods
    private function getDateFilter($period) {
        switch ($period) {
            case 'today':
                return "dp.date = CURDATE()";
            case 'week':
                return "dp.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            case 'month':
                return "dp.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            default:
                return "dp.date = CURDATE()";
        }
    }

    private function getPerformanceRating($plan_completion, $efficiency) {
        $score = ($plan_completion * 0.6) + ($efficiency * 0.4);
        if ($score >= 90) return 'excellent';
        if ($score >= 80) return 'good';
        if ($score >= 70) return 'acceptable';
        return 'needs_improvement';
    }

    private function identifyPerformanceIssues($record, $plan_completion, $efficiency) {
        $issues = [];
        if ($plan_completion < 70) $issues[] = 'Low plan completion';
        if ($efficiency < 50) $issues[] = 'Poor efficiency';
        if ($record['absent'] > $record['mp'] * 0.05) $issues[] = 'High absenteeism';
        return $issues;
    }

    private function generatePerformanceRecommendations($low_performing_lines, $overall_completion) {
        $recommendations = [];

        if (count($low_performing_lines) > 3) {
            $recommendations[] = 'Multiple lines showing poor performance - requires management intervention';
        }

        if ($overall_completion < 80) {
            $recommendations[] = 'Overall production targets not being met - review capacity planning';
        }

        return $recommendations;
    }

    private function logBottleneck($bottleneck_data) {
        try {
            $query = "
                INSERT INTO production_bottlenecks (
                    bottleneck_type, affected_production_line, bottleneck_description,
                    impact_level, detected_date, resolution_status
                ) VALUES (
                    :bottleneck_type, :affected_production_line, :bottleneck_description,
                    :impact_level, CURDATE(), 'pending'
                )
                ON DUPLICATE KEY UPDATE
                    impact_level = VALUES(impact_level),
                    detected_date = VALUES(detected_date)
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute($bottleneck_data);

        } catch(PDOException $e) {
            error_log("Bottleneck Logging Error: " . $e->getMessage());
        }
    }

    private function groupBottlenecksByType($bottlenecks) {
        $grouped = [];
        foreach ($bottlenecks as $bottleneck) {
            $type = $bottleneck['type'];
            if (!isset($grouped[$type])) {
                $grouped[$type] = ['count' => 0, 'critical_count' => 0];
            }
            $grouped[$type]['count']++;
            if ($bottleneck['severity'] === 'critical') {
                $grouped[$type]['critical_count']++;
            }
        }
        return $grouped;
    }

    private function getOEERating($oee) {
        if ($oee >= 85) return 'world_class';
        if ($oee >= 75) return 'good';
        if ($oee >= 65) return 'acceptable';
        if ($oee >= 50) return 'needs_improvement';
        return 'critical';
    }

    private function generateTrendInsights($plan_trend, $manpower_trend) {
        $insights = [];

        if (!empty($plan_trend)) {
            $recent_avg = array_sum(array_column(array_slice($plan_trend, -3), 'value')) / 3;
            $previous_avg = array_sum(array_column(array_slice($plan_trend, -7, -3), 'value')) / 3;

            if ($recent_avg > $previous_avg + 5) {
                $insights[] = 'Production performance is improving';
            } elseif ($recent_avg < $previous_avg - 5) {
                $insights[] = 'Production performance is declining - requires attention';
            }
        }

        return $insights;
    }

    private function generateSimpleForecast($trend_data) {
        if (empty($trend_data)) return [];

        $recent_values = array_column(array_slice($trend_data, -5), 'value');
        $avg = array_sum($recent_values) / count($recent_values);

        return [
            'next_day' => round($avg, 2),
            'next_week' => round($avg, 2),
            'confidence' => 'medium'
        ];
    }

    private function calculateTrendDirection($data) {
        if (count($data) < 2) return 0;

        $first = $data[0]['value'];
        $last = end($data)['value'];

        return ($last - $first) / count($data);
    }

    private function predictBottleneckRisks($current_bottlenecks) {
        $risks = [];

        if (isset($current_bottlenecks['critical_count']) && $current_bottlenecks['critical_count'] > 0) {
            $risks[] = 'Critical bottlenecks require immediate attention to prevent production losses';
        }

        return $risks;
    }

    private function predictMaintenanceNeeds() {
        // Placeholder for maintenance prediction logic
        return ['needs_scheduled' => 0, 'urgent_needs' => 0];
    }

    private function generateQualityRecommendations($problem_areas) {
        $recommendations = [];

        if (count($problem_areas) > 0) {
            $recommendations[] = 'Review quality procedures for identified problem areas';
            $recommendations[] = 'Provide additional training for quality checkpoints';
        }

        return $recommendations;
    }

    private function identifyInefficiencyCauses($record, $efficiency) {
        $causes = [];

        if ($record['absent'] > $record['mp'] * 0.05) {
            $causes[] = 'High absenteeism affecting productivity';
        }

        if ($record['ot_hours'] > 4) {
            $causes[] = 'Excessive overtime indicating capacity issues';
        }

        return $causes;
    }

    private function calculateEfficiencyDistribution($efficiency_data) {
        $distribution = [
            'excellent' => 0,
            'good' => 0,
            'acceptable' => 0,
            'poor' => 0
        ];

        foreach ($efficiency_data as $data) {
            $distribution[$data['rating']]++;
        }

        return $distribution;
    }

    private function generateEfficiencyRecommendations($inefficient_areas) {
        $recommendations = [];

        if (count($inefficient_areas) > 2) {
            $recommendations[] = 'Multiple lines showing poor efficiency - requires systemic review';
        }

        $recommendations[] = 'Review production processes and equipment performance';

        return $recommendations;
    }

    private function generateManpowerRecommendations($issues) {
        $recommendations = [];

        $high_absenteeism = array_filter($issues, fn($i) => $i['type'] === 'high_absenteeism');
        $low_utilization = array_filter($issues, fn($i) => $i['type'] === 'low_utilization');

        if (count($high_absenteeism) > 0) {
            $recommendations[] = 'Review absenteeism patterns and implement attendance improvement programs';
        }

        if (count($low_utilization) > 0) {
            $recommendations[] = 'Optimize manpower allocation and shift scheduling';
        }

        return $recommendations;
    }
}

// API endpoint for analytics
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $analytics = new ProductionAnalytics();

    switch ($_GET['action']) {
        case 'run_analytics':
            $results = $analytics->runAnalytics();
            echo json_encode([
                'success' => true,
                'data' => $results,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        case 'performance':
            $period = $_GET['period'] ?? 'today';
            $results = $analytics->analyzeProductionPerformance($period);
            echo json_encode(['success' => true, 'data' => $results]);
            break;

        case 'bottlenecks':
            $results = $analytics->detectBottlenecks();
            echo json_encode(['success' => true, 'data' => $results]);
            break;

        case 'oee':
            $period = $_GET['period'] ?? 'today';
            $results = $analytics->calculateOEE($period);
            echo json_encode(['success' => true, 'data' => $results]);
            break;

        case 'trends':
            $days = $_GET['days'] ?? 30;
            $results = $analytics->analyzeTrends($days);
            echo json_encode(['success' => true, 'data' => $results]);
            break;

        case 'quality':
            $days = $_GET['days'] ?? 7;
            $results = $analytics->analyzeQualityMetrics($days);
            echo json_encode(['success' => true, 'data' => $results]);
            break;

        case 'efficiency':
            $days = $_GET['days'] ?? 7;
            $results = $analytics->analyzeEfficiency($days);
            echo json_encode(['success' => true, 'data' => $results]);
            break;

        case 'manpower':
            $days = $_GET['days'] ?? 7;
            $results = $analytics->analyzeManpowerUtilization($days);
            echo json_encode(['success' => true, 'data' => $results]);
            break;

        case 'predictions':
            $results = $analytics->generatePredictions();
            echo json_encode(['success' => true, 'data' => $results]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// Web interface for analytics dashboard
if (basename($_SERVER['PHP_SELF']) === 'analytics_engine.php' && !isset($_GET['action'])) {
    $analytics = new ProductionAnalytics();

    // Get initial analytics data
    $performance = $analytics->analyzeProductionPerformance();
    $bottlenecks = $analytics->detectBottlenecks();
    $oee = $analytics->calculateOEE();
    $trends = $analytics->analyzeTrends(7);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Analytics Engine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        .analytics-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .analytics-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .metric-highlight {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .status-excellent { background-color: #28a745; }
        .status-good { background-color: #17a2b8; }
        .status-acceptable { background-color: #ffc107; }
        .status-poor { background-color: #dc3545; }
        .status-critical { background-color: #721c24; }

        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-stable { color: #6c757d; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-chart-line me-3"></i>Production Analytics Engine</h1>
                    <div>
                        <button class="btn btn-primary" onclick="refreshAnalytics()">
                            <i class="fas fa-sync me-2"></i>Refresh Analytics
                        </button>
                        <a href="enhanced_dashboard.php" class="btn btn-success">
                            <i class="fas fa-tachometer-alt me-2"></i>Command Center
                        </a>
                    </div>
                </div>

                <!-- Key Metrics Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="analytics-card text-center">
                            <div class="metric-highlight text-primary"><?= number_format($performance['summary']['overall_plan_completion'] ?? 0, 1) ?>%</div>
                            <div class="text-muted">Plan Completion Rate</div>
                            <small>Overall performance</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="analytics-card text-center">
                            <div class="metric-highlight text-success"><?= $oee['overall_oee'] ?? 0 ?>%</div>
                            <div class="text-muted">Overall OEE</div>
                            <small class="<?= ($oee['overall_oee'] ?? 0) >= 85 ? 'text-success' : 'text-warning' ?>">
                                <?= ($oee['overall_oee'] ?? 0) >= 85 ? 'World Class' : 'Needs Improvement' ?>
                            </small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="analytics-card text-center">
                            <div class="metric-highlight text-warning"><?= $bottlenecks['total_bottlenecks'] ?? 0 ?></div>
                            <div class="text-muted">Active Bottlenecks</div>
                            <small><?= ($bottlenecks['critical_count'] ?? 0) ?> critical</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="analytics-card text-center">
                            <div class="metric-highlight text-info"><?= number_format($performance['summary']['overall_absent_rate'] ?? 0, 1) ?>%</div>
                            <div class="text-muted">Absenteeism Rate</div>
                            <small>Current period</small>
                        </div>
                    </div>
                </div>

                <!-- OEE Breakdown -->
                <div class="analytics-card">
                    <h5><i class="fas fa-tachometer-alt me-2"></i>OEE Components</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="text-primary"><?= $oee['components']['availability'] ?? 0 ?>%</h3>
                                <p class="mb-0">Availability</p>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" style="width: <?= $oee['components']['availability'] ?? 0 ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="text-success"><?= $oee['components']['performance'] ?? 0 ?>%</h3>
                                <p class="mb-0">Performance</p>
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: <?= $oee['components']['performance'] ?? 0 ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h3 class="text-info"><?= $oee['components']['quality'] ?? 0 ?>%</h3>
                                <p class="mb-0">Quality</p>
                                <div class="progress">
                                    <div class="progress-bar bg-info" style="width: <?= $oee['components']['quality'] ?? 0 ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Production Lines Performance -->
                <div class="analytics-card">
                    <h5><i class="fas fa-cogs me-2"></i>Production Lines Performance</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Line/Shift</th>
                                    <th>Leader</th>
                                    <th>Plan Completion</th>
                                    <th>Efficiency</th>
                                    <th>CPH</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performance['by_line'] ?? [] as $line): ?>
                                <tr>
                                    <td><?= htmlspecialchars($line['line_shift']) ?></td>
                                    <td><?= htmlspecialchars($line['leader']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?= number_format($line['plan_completion'], 1) ?>%
                                            <div class="progress ms-2" style="width: 100px; height: 8px;">
                                                <div class="progress-bar" style="width: <?= $line['plan_completion'] ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= number_format($line['efficiency'], 1) ?>%</td>
                                    <td><?= number_format($line['cph'], 1) ?></td>
                                    <td>
                                        <span class="status-indicator status-<?= str_replace(' ', '-', $line['performance_rating']) ?>"></span>
                                        <?= ucfirst($line['performance_rating']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Active Bottlenecks -->
                <?php if (!empty($bottlenecks['bottlenecks'])): ?>
                <div class="analytics-card">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Active Bottlenecks</h5>
                    <?php foreach ($bottlenecks['bottlenecks'] as $bottleneck): ?>
                        <div class="alert alert-<?= $bottleneck['severity'] === 'critical' ? 'danger' : 'warning' ?> mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($bottleneck['description']) ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-industry me-1"></i><?= htmlspecialchars($bottleneck['line']) ?>
                                        • Type: <?= ucfirst($bottleneck['type']) ?>
                                    </small>
                                </div>
                                <span class="badge bg-<?= $bottleneck['severity'] === 'critical' ? 'danger' : 'warning' ?>">
                                    <?= ucfirst($bottleneck['severity']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Recommendations -->
                <?php if (!empty($performance['recommendations'])): ?>
                <div class="analytics-card">
                    <h5><i class="fas fa-lightbulb me-2"></i>Recommendations</h5>
                    <ul class="mb-0">
                        <?php foreach ($performance['recommendations'] as $recommendation): ?>
                            <li><?= htmlspecialchars($recommendation) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshAnalytics() {
            fetch('analytics_engine.php?action=run_analytics')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Auto-refresh every 5 minutes
        setInterval(refreshAnalytics, 300000);
    </script>
</body>
</html>
<?php
}
?>