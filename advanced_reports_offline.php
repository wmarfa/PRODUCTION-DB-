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
        error_log('CSRF validation failed in advanced_reports.php');
        die('Security validation failed');
    }
}

// Authentication and Authorization
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    header('Location: index_lan.php');
    exit;
}

// Check permissions for report generation
$userRole = $_SESSION['user_role'];
$reportPermissions = [
    'production_summary' => ['operator', 'supervisor', 'manager', 'executive', 'admin'],
    'performance_analysis' => ['supervisor', 'manager', 'executive', 'admin'],
    'quality_reports' => ['supervisor', 'manager', 'executive', 'admin'],
    'maintenance_reports' => ['supervisor', 'manager', 'executive', 'admin'],
    'oee_reports' => ['manager', 'executive', 'admin'],
    'cost_analysis' => ['manager', 'executive', 'admin'],
    'bottleneck_reports' => ['manager', 'executive', 'admin'],
    'compliance_reports' => ['admin']
];

/**
 * Enhanced Report Generator with Export Capabilities
 * Provides comprehensive production reporting with offline export functionality
 */
class ReportGenerator {
    private $conn;
    private $userRole;
    private $cache = [];
    private $exportFormats = ['csv', 'excel', 'pdf', 'html'];

    public function __construct($conn, $userRole) {
        $this->conn = $conn;
        $this->userRole = $userRole;
    }

    /**
     * Generate Production Summary Report
     * Comprehensive overview of all production lines with key metrics
     */
    public function generateProductionSummary($dateRange, $filters = []) {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');
        $shifts = $filters['shifts'] ?? [];
        $lines = $filters['lines'] ?? [];

        $query = "SELECT
                    dp.line_shift,
                    dp.date,
                    dp.shift,
                    dp.actual_output,
                    dp.plan,
                    dp.no_ot_mp,
                    dp.ot_mp,
                    dp.ot_hours,
                    dp.machine_downtime,
                    dp.line_utilization,
                    dp.input_rate,
                    dp.plan_per_hundred,
                    dp.completed_time,
                    pl.line_name,
                    pl.daily_capacity,
                    pl.process_category,
                    pl.manning_level
                 FROM daily_performance dp
                 LEFT JOIN production_lines pl ON dp.line_shift = CONCAT(pl.line_number, '_', dp.shift)
                 WHERE dp.date BETWEEN ? AND ?";

        $params = [$startDate, $endDate];
        $types = "ss";

        if (!empty($shifts)) {
            $query .= " AND dp.shift IN (" . implode(',', array_fill(0, count($shifts), '?')) . ")";
            $params = array_merge($params, $shifts);
            $types .= str_repeat('s', count($shifts));
        }

        if (!empty($lines)) {
            $query .= " AND dp.line_shift IN (" . implode(',', array_fill(0, count($lines), '?')) . ")";
            $params = array_merge($params, $lines);
            $types .= str_repeat('s', count($lines));
        }

        $query .= " ORDER BY dp.date DESC, dp.line_shift";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $reportData = [];
        while ($row = $result->fetch_assoc()) {
            $row['efficiency'] = $this->calculateEfficiency($row);
            $row['plan_completion'] = $this->calculatePlanCompletion($row);
            $row['performance_trend'] = $this->calculatePerformanceTrend($row['line_shift'], $row['date']);
            $reportData[] = $row;
        }

        return [
            'title' => 'Production Summary Report',
            'subtitle' => "Date Range: $startDate to $endDate",
            'data' => $reportData,
            'summary' => $this->generateProductionSummary($reportData),
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['username']
        ];
    }

    /**
     * Generate Performance Analysis Report
     * Detailed performance metrics with trend analysis and variance analysis
     */
    public function generatePerformanceAnalysis($dateRange, $filters = []) {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');

        // Get historical performance data
        $query = "SELECT
                    line_shift,
                    date,
                    shift,
                    actual_output,
                    plan,
                    efficiency,
                    plan_completion,
                    input_rate,
                    line_utilization,
                    machine_downtime
                 FROM daily_performance
                 WHERE date BETWEEN ? AND ?
                 ORDER BY line_shift, date";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $performanceData = [];
        while ($row = $result->fetch_assoc()) {
            $performanceData[$row['line_shift']][] = $row;
        }

        $analysis = [];
        foreach ($performanceData as $lineShift => $data) {
            $analysis[] = [
                'line_shift' => $lineShift,
                'avg_efficiency' => $this->calculateAverage($data, 'efficiency'),
                'avg_completion' => $this->calculateAverage($data, 'plan_completion'),
                'efficiency_trend' => $this->calculateTrend($data, 'efficiency'),
                'completion_trend' => $this->calculateTrend($data, 'plan_completion'),
                'variance_from_plan' => $this->calculateVariance($data, 'actual_output', 'plan'),
                'best_performance_day' => $this->findBestPerformanceDay($data),
                'worst_performance_day' => $this->findWorstPerformanceDay($data),
                'improvement_areas' => $this->identifyImprovementAreas($data)
            ];
        }

        return [
            'title' => 'Performance Analysis Report',
            'subtitle' => "Date Range: $startDate to $endDate",
            'analysis' => $analysis,
            'recommendations' => $this->generatePerformanceRecommendations($analysis),
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['username']
        ];
    }

    /**
     * Generate Quality Report with Defect Analysis
     * Comprehensive quality metrics with root cause analysis
     */
    public function generateQualityReport($dateRange, $filters = []) {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');

        // Get quality checkpoints data
        $query = "SELECT
                    qc.checkpoint_id,
                    qc.checkpoint_name,
                    qc.process_category,
                    qc.specification_limit,
                    qc.control_limit,
                    qm.checkpoint_id,
                    qm.line_shift,
                    qm.date,
                    qm.shift,
                    qm.measure_value,
                    qm.is_conforming,
                    qm.defect_description,
                    qm.corrective_action,
                    qm.operator_name
                 FROM quality_checkpoints qc
                 LEFT JOIN quality_measurements qm ON qc.checkpoint_id = qm.checkpoint_id
                 WHERE qm.date BETWEEN ? AND ?";

        $params = [$startDate, $endDate];
        $types = "ss";

        if (!empty($filters['categories'])) {
            $query .= " AND qc.process_category IN (" . implode(',', array_fill(0, count($filters['categories']), '?')) . ")";
            $params = array_merge($params, $filters['categories']);
            $types .= str_repeat('s', count($filters['categories']));
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $qualityData = [];
        while ($row = $result->fetch_assoc()) {
            $qualityData[] = $row;
        }

        $report = [
            'title' => 'Quality Report',
            'subtitle' => "Date Range: $startDate to $endDate",
            'quality_metrics' => $this->calculateQualityMetrics($qualityData),
            'defect_analysis' => $this->analyzeDefects($qualityData),
            'checkpoint_performance' => $this->analyzeCheckpointPerformance($qualityData),
            'root_cause_analysis' => $this->performRootCauseAnalysis($qualityData),
            'corrective_actions' => $this->analyzeCorrectiveActions($qualityData),
            'quality_trends' => $this->analyzeQualityTrends($qualityData),
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['username']
        ];

        return $report;
    }

    /**
     * Generate OEE (Overall Equipment Effectiveness) Report
     * Comprehensive OEE analysis with availability, performance, and quality metrics
     */
    public function generateOEEReport($dateRange, $filters = []) {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');

        // Get OEE calculation data
        $query = "SELECT
                    line_shift,
                    date,
                    shift,
                    actual_output,
                    plan,
                    machine_downtime,
                    input_rate,
                    line_utilization
                 FROM daily_performance
                 WHERE date BETWEEN ? AND ?";

        $params = [$startDate, $endDate];
        $types = "ss";

        if (!empty($filters['lines'])) {
            $query .= " AND line_shift IN (" . implode(',', array_fill(0, count($filters['lines']), '?')) . ")";
            $params = array_merge($params, $filters['lines']);
            $types .= str_repeat('s', count($filters['lines']));
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $oeeData = [];
        while ($row = $result->fetch_assoc()) {
            $oeeData[] = array_merge($row, $this->calculateOEEComponents($row));
        }

        return [
            'title' => 'OEE Analysis Report',
            'subtitle' => "Date Range: $startDate to $endDate",
            'oee_data' => $oeeData,
            'summary_metrics' => $this->calculateOEESummary($oeeData),
            'line_performance' => $this->analyzeLineOEEPerformance($oeeData),
            'improvement_recommendations' => $this->generateOEERecommendations($oeeData),
            'trend_analysis' => $this->analyzeOEETrends($oeeData),
            'benchmark_comparison' => $this->performOEEBenchmarking($oeeData),
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['username']
        ];
    }

    /**
     * Generate Cost Analysis Report
     * Detailed cost analysis including labor, maintenance, and operational costs
     */
    public function generateCostAnalysisReport($dateRange, $filters = []) {
        $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $filters['end_date'] ?? date('Y-m-d');

        // Get cost data
        $query = "SELECT
                    dp.line_shift,
                    dp.date,
                    dp.shift,
                    dp.actual_output,
                    dp.no_ot_mp,
                    dp.ot_mp,
                    dp.ot_hours,
                    dp.machine_downtime,
                    ms.maintenance_cost,
                    ms.material_cost,
                    ms.downtime_cost
                 FROM daily_performance dp
                 LEFT JOIN maintenance_summary ms ON dp.line_shift = ms.line_shift AND dp.date = ms.date
                 WHERE dp.date BETWEEN ? AND ?";

        $params = [$startDate, $endDate];
        $types = "ss";

        if (!empty($filters['lines'])) {
            $query .= " AND dp.line_shift IN (" . implode(',', array_fill(0, count($filters['lines']), '?')) . ")";
            $params = array_merge($params, $filters['lines']);
            $types .= str_repeat('s', count($filters['lines']));
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $costData = [];
        while ($row = $result->fetch_assoc()) {
            $costData[] = array_merge($row, $this->calculateCostMetrics($row));
        }

        return [
            'title' => 'Cost Analysis Report',
            'subtitle' => "Date Range: $startDate to $endDate",
            'cost_data' => $costData,
            'cost_summary' => $this->calculateCostSummary($costData),
            'cost_per_unit' => $this->calculateCostPerUnit($costData),
            'variance_analysis' => $this->performCostVarianceAnalysis($costData),
            'optimization_opportunities' => $this->identifyCostOptimizationOpportunities($costData),
            'budget_compliance' => $this->analyzeBudgetCompliance($costData),
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['username']
        ];
    }

    /**
     * Export Report to Various Formats
     * Supports CSV, Excel (HTML format), PDF (HTML format), and JSON
     */
    public function exportReport($reportData, $format = 'csv', $filename = null) {
        if (!in_array($format, $this->exportFormats)) {
            throw new Exception("Unsupported export format: $format");
        }

        $filename = $filename ?? 'report_' . date('Y-m-d_His');

        switch ($format) {
            case 'csv':
                return $this->exportToCSV($reportData, $filename);
            case 'excel':
                return $this->exportToExcel($reportData, $filename);
            case 'pdf':
                return $this->exportToPDF($reportData, $filename);
            case 'json':
                return $this->exportToJSON($reportData, $filename);
            default:
                throw new Exception("Export format not implemented: $format");
        }
    }

    /**
     * Export to CSV format
     */
    private function exportToCSV($reportData, $filename) {
        $csv = '';

        // Add header
        $csv .= $reportData['title'] . "\n";
        $csv .= $reportData['subtitle'] . "\n";
        $csv .= "Generated: " . $reportData['generated_at'] . " by " . $reportData['generated_by'] . "\n\n";

        // Add data headers
        if (!empty($reportData['data'])) {
            $headers = array_keys($reportData['data'][0]);
            $csv .= implode(',', $headers) . "\n";

            // Add data rows
            foreach ($reportData['data'] as $row) {
                $csvRow = [];
                foreach ($row as $value) {
                    $csvRow[] = is_numeric($value) ? $value : '"' . str_replace('"', '""', $value) . '"';
                }
                $csv .= implode(',', $csvRow) . "\n";
            }
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Content-Length: ' . strlen($csv));
        echo $csv;
        exit;
    }

    /**
     * Export to Excel (HTML format)
     */
    private function exportToExcel($reportData, $filename) {
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta charset="utf-8"><meta name=ProgId content=Excel.Sheet><meta name=Generator content="Microsoft Excel">';
        $html .= '<style>
            table { border-collapse: collapse; border: 1px solid #ccc; }
            th, td { border: 1px solid #ccc; padding: 5px; text-align: left; }
            th { background-color: #f0f0f0; font-weight: bold; }
            .header { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
            .subtitle { font-size: 14px; margin-bottom: 20px; }
        </style>';
        $html .= '</head><body>';

        $html .= '<div class="header">' . $reportData['title'] . '</div>';
        $html .= '<div class="subtitle">' . $reportData['subtitle'] . '</div>';
        $html .= '<div>Generated: ' . $reportData['generated_at'] . ' by ' . $reportData['generated_by'] . '</div>';
        $html .= '<br>';

        if (!empty($reportData['data'])) {
            $html .= '<table>';
            $html .= '<tr>';
            foreach (array_keys($reportData['data'][0]) as $header) {
                $html .= '<th>' . htmlspecialchars($header) . '</th>';
            }
            $html .= '</tr>';

            foreach ($reportData['data'] as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $html .= '<td>' . htmlspecialchars($value) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        $html .= '</body></html>';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Content-Length: ' . strlen($html));
        echo $html;
        exit;
    }

    /**
     * Export to PDF (HTML format for printing)
     */
    private function exportToPDF($reportData, $filename) {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>';
        $html .= '@media print { body { font-family: Arial, sans-serif; margin: 20px; } }';
        $html .= 'body { font-family: Arial, sans-serif; margin: 20px; }';
        $html .= '.header { font-size: 18px; font-weight: bold; margin-bottom: 10px; }';
        $html .= '.subtitle { font-size: 14px; margin-bottom: 20px; color: #666; }';
        $html .= 'table { border-collapse: collapse; width: 100%; margin-top: 20px; }';
        $html .= 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
        $html .= 'th { background-color: #f5f5f5; }';
        $html .= '.summary { background-color: #f9f9f9; padding: 15px; margin: 20px 0; border: 1px solid #ddd; }';
        $html .= '</style></head><body>';

        $html .= '<div class="header">' . $reportData['title'] . '</div>';
        $html .= '<div class="subtitle">' . $reportData['subtitle'] . '</div>';
        $html .= '<div>Generated: ' . $reportData['generated_at'] . ' by ' . $reportData['generated_by'] . '</div>';

        if (!empty($reportData['summary'])) {
            $html .= '<div class="summary">';
            $html .= '<h3>Summary</h3>';
            foreach ($reportData['summary'] as $key => $value) {
                $html .= '<p><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</p>';
            }
            $html .= '</div>';
        }

        if (!empty($reportData['data'])) {
            $html .= '<table>';
            $html .= '<tr>';
            foreach (array_keys($reportData['data'][0]) as $header) {
                $html .= '<th>' . htmlspecialchars($header) . '</th>';
            }
            $html .= '</tr>';

            foreach ($reportData['data'] as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $html .= '<td>' . htmlspecialchars($value) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        $html .= '</body></html>';

        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . $filename . '.html"');
        echo $html;
        exit;
    }

    /**
     * Export to JSON format
     */
    private function exportToJSON($reportData, $filename) {
        $json = json_encode($reportData, JSON_PRETTY_PRINT);

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    // Helper methods for calculations and analysis
    private function calculateEfficiency($row) {
        $used_mhr = $this->calculateUsedMHR($row['no_ot_mp'], $row['ot_mp'], $row['ot_hours']);
        return $used_mhr > 0 ? ($row['actual_output'] / $used_mhr) : 0;
    }

    private function calculateUsedMHR($no_ot_mp, $ot_mp, $ot_hours) {
        $reg_mhr = ($no_ot_mp * 8) + ($ot_mp * 8);
        $ot_mhr = ($ot_mp * $ot_hours) / 1.5;
        return $reg_mhr + $ot_mhr;
    }

    private function calculatePlanCompletion($row) {
        return $row['plan'] > 0 ? ($row['actual_output'] / $row['plan']) * 100 : 0;
    }

    private function calculatePerformanceTrend($lineShift, $date) {
        // Get last 7 days for trend analysis
        $trendQuery = "SELECT actual_output, plan
                      FROM daily_performance
                      WHERE line_shift = ? AND date < ?
                      ORDER BY date DESC LIMIT 7";
        $stmt = $this->conn->prepare($trendQuery);
        $stmt->bind_param("ss", $lineShift, $date);
        $stmt->execute();
        $result = $stmt->get_result();

        $trendData = [];
        while ($row = $result->fetch_assoc()) {
            $trendData[] = $row;
        }

        if (count($trendData) < 2) return 'stable';

        $completions = [];
        foreach ($trendData as $data) {
            $completions[] = $data['plan'] > 0 ? ($data['actual_output'] / $data['plan']) * 100 : 0;
        }

        $recent = array_slice($completions, 0, 3);
        $older = array_slice($completions, 3);

        $recentAvg = array_sum($recent) / count($recent);
        $olderAvg = count($older) > 0 ? array_sum($older) / count($older) : $recentAvg;

        if ($recentAvg > $olderAvg + 5) return 'improving';
        if ($recentAvg < $olderAvg - 5) return 'declining';
        return 'stable';
    }

    private function calculateAverage($data, $field) {
        $values = array_column($data, $field);
        return count($values) > 0 ? array_sum($values) / count($values) : 0;
    }

    private function calculateTrend($data, $field) {
        if (count($data) < 2) return 'stable';

        $firstHalf = array_slice($data, 0, floor(count($data) / 2));
        $secondHalf = array_slice($data, floor(count($data) / 2));

        $firstAvg = $this->calculateAverage($firstHalf, $field);
        $secondAvg = $this->calculateAverage($secondHalf, $field);

        if ($secondAvg > $firstAvg + 5) return 'improving';
        if ($secondAvg < $firstAvg - 5) return 'declining';
        return 'stable';
    }

    private function calculateVariance($data, $actualField, $planField) {
        $variances = [];
        foreach ($data as $row) {
            $variance = $row[$planField] - $row[$actualField];
            $variances[] = $variance;
        }
        return count($variances) > 0 ? array_sum($variances) / count($variances) : 0;
    }

    private function findBestPerformanceDay($data) {
        if (empty($data)) return null;

        $bestDay = $data[0];
        foreach ($data as $day) {
            if ($day['efficiency'] > $bestDay['efficiency']) {
                $bestDay = $day;
            }
        }
        return $bestDay;
    }

    private function findWorstPerformanceDay($data) {
        if (empty($data)) return null;

        $worstDay = $data[0];
        foreach ($data as $day) {
            if ($day['efficiency'] < $worstDay['efficiency']) {
                $worstDay = $day;
            }
        }
        return $worstDay;
    }

    private function identifyImprovementAreas($data) {
        $areas = [];

        // Check for low efficiency trends
        $avgEfficiency = $this->calculateAverage($data, 'efficiency');
        if ($avgEfficiency < 0.8) {
            $areas[] = 'Low overall efficiency - consider training or process optimization';
        }

        // Check for high downtime
        $avgDowntime = $this->calculateAverage($data, 'machine_downtime');
        if ($avgDowntime > 60) {
            $areas[] = 'High machine downtime - preventive maintenance recommended';
        }

        // Check for inconsistent performance
        $efficiencies = array_column($data, 'efficiency');
        $efficiencyStdDev = $this->calculateStandardDeviation($efficiencies);
        if ($efficiencyStdDev > 0.2) {
            $areas[] = 'Inconsistent performance - standardize procedures';
        }

        return $areas;
    }

    private function calculateStandardDeviation($values) {
        if (count($values) < 2) return 0;

        $avg = array_sum($values) / count($values);
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $avg, 2);
        }
        return sqrt($variance / (count($values) - 1));
    }

    private function generatePerformanceRecommendations($analysis) {
        $recommendations = [];

        foreach ($analysis as $lineAnalysis) {
            if ($lineAnalysis['avg_efficiency'] < 0.7) {
                $recommendations[] = [
                    'line' => $lineAnalysis['line_shift'],
                    'issue' => 'Low efficiency',
                    'recommendation' => 'Conduct root cause analysis and operator training'
                ];
            }

            if ($lineAnalysis['avg_completion'] < 80) {
                $recommendations[] = [
                    'line' => $lineAnalysis['line_shift'],
                    'issue' => 'Poor plan completion',
                    'recommendation' => 'Review production planning and resource allocation'
                ];
            }

            if ($lineAnalysis['efficiency_trend'] === 'declining') {
                $recommendations[] = [
                    'line' => $lineAnalysis['line_shift'],
                    'issue' => 'Declining performance trend',
                    'recommendation' => 'Implement daily performance monitoring and intervention'
                ];
            }
        }

        return $recommendations;
    }

    private function calculateQualityMetrics($qualityData) {
        if (empty($qualityData)) return [];

        $totalMeasurements = count($qualityData);
        $conformingMeasurements = count(array_filter($qualityData, function($item) {
            return $item['is_conforming'] === 1;
        }));

        return [
            'total_measurements' => $totalMeasurements,
            'conforming_measurements' => $conformingMeasurements,
            'yield_rate' => $totalMeasurements > 0 ? ($conformingMeasurements / $totalMeasurements) * 100 : 0,
            'defect_rate' => $totalMeasurements > 0 ? (($totalMeasurements - $conformingMeasurements) / $totalMeasurements) * 100 : 0
        ];
    }

    private function analyzeDefects($qualityData) {
        $defects = [];
        foreach ($qualityData as $measurement) {
            if ($measurement['is_conforming'] === 0 && !empty($measurement['defect_description'])) {
                $defects[] = $measurement['defect_description'];
            }
        }

        $defectCounts = array_count_values($defects);
        arsort($defectCounts);

        return $defectCounts;
    }

    private function analyzeCheckpointPerformance($qualityData) {
        $checkpointData = [];
        foreach ($qualityData as $measurement) {
            if (!isset($checkpointData[$measurement['checkpoint_id']])) {
                $checkpointData[$measurement['checkpoint_id']] = [
                    'name' => $measurement['checkpoint_name'],
                    'total' => 0,
                    'conforming' => 0,
                    'process_category' => $measurement['process_category']
                ];
            }

            $checkpointData[$measurement['checkpoint_id']]['total']++;
            if ($measurement['is_conforming'] === 1) {
                $checkpointData[$measurement['checkpoint_id']]['conforming']++;
            }
        }

        foreach ($checkpointData as &$checkpoint) {
            $checkpoint['yield'] = $checkpoint['total'] > 0 ? ($checkpoint['conforming'] / $checkpoint['total']) * 100 : 0;
        }

        return $checkpointData;
    }

    private function performRootCauseAnalysis($qualityData) {
        $rootCauses = [];

        foreach ($qualityData as $measurement) {
            if ($measurement['is_conforming'] === 0) {
                $category = $measurement['process_category'];
                $checkpoint = $measurement['checkpoint_name'];
                $defect = $measurement['defect_description'];

                $rootCauses[] = [
                    'process_category' => $category,
                    'checkpoint' => $checkpoint,
                    'defect' => $defect,
                    'frequency' => $this->calculateDefectFrequency($qualityData, $checkpoint, $defect)
                ];
            }
        }

        // Group by root cause patterns
        $causePatterns = [];
        foreach ($rootCauses as $cause) {
            $key = $cause['process_category'] . '_' . $cause['defect'];
            if (!isset($causePatterns[$key])) {
                $causePatterns[$key] = [
                    'process_category' => $cause['process_category'],
                    'defect' => $cause['defect'],
                    'frequency' => 0,
                    'affected_checkpoints' => []
                ];
            }
            $causePatterns[$key]['frequency'] += $cause['frequency'];
            $causePatterns[$key]['affected_checkpoints'][] = $cause['checkpoint'];
        }

        usort($causePatterns, function($a, $b) {
            return $b['frequency'] <=> $a['frequency'];
        });

        return $causePatterns;
    }

    private function calculateDefectFrequency($qualityData, $checkpoint, $defect) {
        $count = 0;
        foreach ($qualityData as $measurement) {
            if ($measurement['checkpoint_name'] === $checkpoint &&
                $measurement['defect_description'] === $defect &&
                $measurement['is_conforming'] === 0) {
                $count++;
            }
        }
        return $count;
    }

    private function analyzeCorrectiveActions($qualityData) {
        $actions = [];

        foreach ($qualityData as $measurement) {
            if (!empty($measurement['corrective_action'])) {
                $actions[] = [
                    'action' => $measurement['corrective_action'],
                    'effectiveness' => $this->calculateActionEffectiveness($qualityData, $measurement['corrective_action'])
                ];
            }
        }

        return $actions;
    }

    private function calculateActionEffectiveness($qualityData, $action) {
        // Calculate effectiveness by checking if similar defects decreased after action implementation
        $beforeAction = 0;
        $afterAction = 0;
        $actionFound = false;

        foreach ($qualityData as $measurement) {
            if ($measurement['corrective_action'] === $action) {
                $actionFound = true;
                continue;
            }

            if (!$actionFound) {
                $beforeAction += $measurement['is_conforming'] === 0 ? 1 : 0;
            } else {
                $afterAction += $measurement['is_conforming'] === 0 ? 1 : 0;
            }
        }

        if ($beforeAction === 0) return 100;

        return (($beforeAction - $afterAction) / $beforeAction) * 100;
    }

    private function analyzeQualityTrends($qualityData) {
        if (empty($qualityData)) return [];

        // Group data by date for trend analysis
        $dateData = [];
        foreach ($qualityData as $measurement) {
            if (!isset($dateData[$measurement['date']])) {
                $dateData[$measurement['date']] = [
                    'total' => 0,
                    'conforming' => 0,
                    'date' => $measurement['date']
                ];
            }

            $dateData[$measurement['date']]['total']++;
            if ($measurement['is_conforming'] === 1) {
                $dateData[$measurement['date']]['conforming']++;
            }
        }

        ksort($dateData);

        $trends = [];
        foreach ($dateData as $date => $data) {
            $trends[] = [
                'date' => $date,
                'yield_rate' => $data['total'] > 0 ? ($data['conforming'] / $data['total']) * 100 : 0,
                'defect_rate' => $data['total'] > 0 ? (($data['total'] - $data['conforming']) / $data['total']) * 100 : 0
            ];
        }

        return $trends;
    }

    private function calculateOEEComponents($row) {
        // Availability = (Scheduled Time - Downtime) / Scheduled Time
        $scheduledTime = 480; // 8 hours in minutes (assuming 8-hour shift)
        $downtime = $row['machine_downtime'] ?? 0;
        $availability = $scheduledTime > 0 ? (($scheduledTime - $downtime) / $scheduledTime) * 100 : 0;

        // Performance = (Actual Output / Ideal Output) * 100
        $idealOutput = $row['plan'] ?? $row['actual_output'];
        $performance = $idealOutput > 0 ? ($row['actual_output'] / $idealOutput) * 100 : 0;

        // Quality = (Good Units / Total Units) * 100
        // For now, using line utilization as quality proxy
        $quality = $row['line_utilization'] ?? 100;

        // OEE = Availability * Performance * Quality / 10000
        $oee = ($availability * $performance * $quality) / 10000;

        return [
            'availability' => $availability,
            'performance' => $performance,
            'quality' => $quality,
            'oee' => $oee
        ];
    }

    private function calculateOEESummary($oeeData) {
        if (empty($oeeData)) return [];

        $oeeValues = array_column($oeeData, 'oee');
        $availabilityValues = array_column($oeeData, 'availability');
        $performanceValues = array_column($oeeData, 'performance');
        $qualityValues = array_column($oeeData, 'quality');

        return [
            'avg_oee' => array_sum($oeeValues) / count($oeeValues),
            'avg_availability' => array_sum($availabilityValues) / count($availabilityValues),
            'avg_performance' => array_sum($performanceValues) / count($performanceValues),
            'avg_quality' => array_sum($qualityValues) / count($qualityValues),
            'best_oee' => max($oeeValues),
            'worst_oee' => min($oeeValues),
            'oee_std_dev' => $this->calculateStandardDeviation($oeeValues)
        ];
    }

    private function analyzeLineOEEPerformance($oeeData) {
        $linePerformance = [];

        foreach ($oeeData as $data) {
            $lineShift = $data['line_shift'];

            if (!isset($linePerformance[$lineShift])) {
                $linePerformance[$lineShift] = [
                    'line_shift' => $lineShift,
                    'oee_values' => [],
                    'availability_values' => [],
                    'performance_values' => [],
                    'quality_values' => []
                ];
            }

            $linePerformance[$lineShift]['oee_values'][] = $data['oee'];
            $linePerformance[$lineShift]['availability_values'][] = $data['availability'];
            $linePerformance[$lineShift]['performance_values'][] = $data['performance'];
            $linePerformance[$lineShift]['quality_values'][] = $data['quality'];
        }

        foreach ($linePerformance as &$line) {
            $line['avg_oee'] = array_sum($line['oee_values']) / count($line['oee_values']);
            $line['avg_availability'] = array_sum($line['availability_values']) / count($line['availability_values']);
            $line['avg_performance'] = array_sum($line['performance_values']) / count($line['performance_values']);
            $line['avg_quality'] = array_sum($line['quality_values']) / count($line['quality_values']);
            $line['oee_trend'] = $this->calculateTrendFromValues($line['oee_values']);
            $line['performance_trend'] = $this->calculateTrendFromValues($line['performance_values']);
        }

        return array_values($linePerformance);
    }

    private function calculateTrendFromValues($values) {
        if (count($values) < 2) return 'stable';

        $firstHalf = array_slice($values, 0, floor(count($values) / 2));
        $secondHalf = array_slice($values, floor(count($values) / 2));

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        if ($secondAvg > $firstAvg + 5) return 'improving';
        if ($secondAvg < $firstAvg - 5) return 'declining';
        return 'stable';
    }

    private function generateOEERecommendations($oeeData) {
        $recommendations = [];

        foreach ($oeeData as $data) {
            if ($data['availability'] < 85) {
                $recommendations[] = [
                    'line' => $data['line_shift'],
                    'component' => 'Availability',
                    'issue' => 'Low availability (' . round($data['availability'], 1) . '%)',
                    'recommendation' => 'Reduce unplanned downtime through preventive maintenance'
                ];
            }

            if ($data['performance'] < 85) {
                $recommendations[] = [
                    'line' => $data['line_shift'],
                    'component' => 'Performance',
                    'issue' => 'Low performance (' . round($data['performance'], 1) . '%)',
                    'recommendation' => 'Optimize production speed and reduce minor stops'
                ];
            }

            if ($data['quality'] < 95) {
                $recommendations[] = [
                    'line' => $data['line_shift'],
                    'component' => 'Quality',
                    'issue' => 'Low quality rate (' . round($data['quality'], 1) . '%)',
                    'recommendation' => 'Improve quality control processes and reduce defects'
                ];
            }

            if ($data['oee'] < 65) {
                $recommendations[] = [
                    'line' => $data['line_shift'],
                    'component' => 'Overall OEE',
                    'issue' => 'Low OEE (' . round($data['oee'], 1) . '%)',
                    'recommendation' => 'Comprehensive improvement initiative required'
                ];
            }
        }

        return $recommendations;
    }

    private function analyzeOEETrends($oeeData) {
        if (empty($oeeData)) return [];

        // Group by date for trend analysis
        $dateData = [];
        foreach ($oeeData as $data) {
            if (!isset($dateData[$data['date']])) {
                $dateData[$data['date']] = [
                    'date' => $data['date'],
                    'oee_values' => [],
                    'availability_values' => [],
                    'performance_values' => [],
                    'quality_values' => []
                ];
            }

            $dateData[$data['date']]['oee_values'][] = $data['oee'];
            $dateData[$data['date']]['availability_values'][] = $data['availability'];
            $dateData[$data['date']]['performance_values'][] = $data['performance'];
            $dateData[$data['date']]['quality_values'][] = $data['quality'];
        }

        $trends = [];
        foreach ($dateData as $date => $data) {
            $trends[] = [
                'date' => $date,
                'avg_oee' => array_sum($data['oee_values']) / count($data['oee_values']),
                'avg_availability' => array_sum($data['availability_values']) / count($data['availability_values']),
                'avg_performance' => array_sum($data['performance_values']) / count($data['performance_values']),
                'avg_quality' => array_sum($data['quality_values']) / count($data['quality_values'])
            ];
        }

        usort($trends, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $trends;
    }

    private function performOEEBenchmarking($oeeData) {
        // Industry benchmarks for comparison
        $benchmarks = [
            'world_class' => 85,
            'excellent' => 75,
            'good' => 65,
            'average' => 55,
            'poor' => 45
        ];

        $benchmarking = [];

        foreach ($oeeData as $data) {
            $oee = $data['oee'];
            $category = 'poor';

            foreach ($benchmarks as $benchmarkCategory => $benchmarkValue) {
                if ($oee >= $benchmarkValue) {
                    $category = $benchmarkCategory;
                    break;
                }
            }

            $benchmarking[] = [
                'line_shift' => $data['line_shift'],
                'oee' => $oee,
                'category' => $category,
                'gap_to_world_class' => $benchmarks['world_class'] - $oee,
                'gap_to_excellent' => $benchmarks['excellent'] - $oee
            ];
        }

        return $benchmarking;
    }

    private function calculateCostMetrics($row) {
        $regularLaborCost = ($row['no_ot_mp'] * 8 * 100); // Assume $100/day regular rate
        $otLaborCost = ($row['ot_mp'] * $row['ot_hours'] * 150); // Assume $150/hour OT rate

        $totalLaborCost = $regularLaborCost + $otLaborCost;
        $maintenanceCost = $row['maintenance_cost'] ?? 0;
        $materialCost = $row['material_cost'] ?? 0;
        $downtimeCost = $row['downtime_cost'] ?? 0;

        $totalCost = $totalLaborCost + $maintenanceCost + $materialCost + $downtimeCost;
        $costPerUnit = $row['actual_output'] > 0 ? $totalCost / $row['actual_output'] : 0;

        return [
            'regular_labor_cost' => $regularLaborCost,
            'ot_labor_cost' => $otLaborCost,
            'total_labor_cost' => $totalLaborCost,
            'maintenance_cost' => $maintenanceCost,
            'material_cost' => $materialCost,
            'downtime_cost' => $downtimeCost,
            'total_cost' => $totalCost,
            'cost_per_unit' => $costPerUnit
        ];
    }

    private function calculateCostSummary($costData) {
        if (empty($costData)) return [];

        $totalCosts = array_sum(array_column($costData, 'total_cost'));
        $totalLaborCosts = array_sum(array_column($costData, 'total_labor_cost'));
        $totalMaintenanceCosts = array_sum(array_column($costData, 'maintenance_cost'));
        $totalMaterialCosts = array_sum(array_column($costData, 'material_cost'));
        $totalDowntimeCosts = array_sum(array_column($costData, 'downtime_cost'));
        $totalOutput = array_sum(array_column($costData, 'actual_output'));
        $avgCostPerUnit = $totalOutput > 0 ? $totalCosts / $totalOutput : 0;

        return [
            'total_costs' => $totalCosts,
            'total_labor_costs' => $totalLaborCosts,
            'total_maintenance_costs' => $totalMaintenanceCosts,
            'total_material_costs' => $totalMaterialCosts,
            'total_downtime_costs' => $totalDowntimeCosts,
            'total_output' => $totalOutput,
            'average_cost_per_unit' => $avgCostPerUnit,
            'labor_cost_percentage' => $totalCosts > 0 ? ($totalLaborCosts / $totalCosts) * 100 : 0,
            'maintenance_cost_percentage' => $totalCosts > 0 ? ($totalMaintenanceCosts / $totalCosts) * 100 : 0,
            'material_cost_percentage' => $totalCosts > 0 ? ($totalMaterialCosts / $totalCosts) * 100 : 0,
            'downtime_cost_percentage' => $totalCosts > 0 ? ($totalDowntimeCosts / $totalCosts) * 100 : 0
        ];
    }

    private function calculateCostPerUnit($costData) {
        $costPerUnit = [];

        foreach ($costData as $data) {
            $lineShift = $data['line_shift'];

            if (!isset($costPerUnit[$lineShift])) {
                $costPerUnit[$lineShift] = [
                    'line_shift' => $lineShift,
                    'cost_per_unit_values' => [],
                    'total_costs' => 0,
                    'total_output' => 0
                ];
            }

            $costPerUnit[$lineShift]['cost_per_unit_values'][] = $data['cost_per_unit'];
            $costPerUnit[$lineShift]['total_costs'] += $data['total_cost'];
            $costPerUnit[$lineShift]['total_output'] += $data['actual_output'];
        }

        foreach ($costPerUnit as &$line) {
            $line['avg_cost_per_unit'] = $line['total_output'] > 0 ? $line['total_costs'] / $line['total_output'] : 0;
            $line['min_cost_per_unit'] = min($line['cost_per_unit_values']);
            $line['max_cost_per_unit'] = max($line['cost_per_unit_values']);
            $line['cost_per_unit_trend'] = $this->calculateTrendFromValues($line['cost_per_unit_values']);
        }

        return array_values($costPerUnit);
    }

    private function performCostVarianceAnalysis($costData) {
        // Calculate variances against budget/standard costs
        $variances = [];

        foreach ($costData as $data) {
            $standardCostPerUnit = 50; // Example standard cost
            $variance = $data['cost_per_unit'] - $standardCostPerUnit;
            $variancePercentage = $standardCostPerUnit > 0 ? ($variance / $standardCostPerUnit) * 100 : 0;

            $variances[] = [
                'line_shift' => $data['line_shift'],
                'date' => $data['date'],
                'actual_cost_per_unit' => $data['cost_per_unit'],
                'standard_cost_per_unit' => $standardCostPerUnit,
                'variance' => $variance,
                'variance_percentage' => $variancePercentage,
                'variance_type' => $variance > 0 ? 'unfavorable' : 'favorable'
            ];
        }

        return $variances;
    }

    private function identifyCostOptimizationOpportunities($costData) {
        $opportunities = [];

        // High overtime costs
        $highOtLines = array_filter($costData, function($data) {
            return ($data['ot_labor_cost'] / $data['total_labor_cost']) > 0.3; // > 30% OT
        });

        if (!empty($highOtLines)) {
            $opportunities[] = [
                'area' => 'Overtime Management',
                'potential_savings' => 'Reduce OT dependency',
                'recommendation' => 'Optimize staffing levels to reduce overtime requirements',
                'affected_lines' => array_column($highOtLines, 'line_shift')
            ];
        }

        // High downtime costs
        $highDowntimeLines = array_filter($costData, function($data) {
            return $data['downtime_cost'] > ($data['total_cost'] * 0.1); // > 10% of total cost
        });

        if (!empty($highDowntimeLines)) {
            $opportunities[] = [
                'area' => 'Downtime Reduction',
                'potential_savings' => 'Reduce unplanned downtime',
                'recommendation' => 'Implement preventive maintenance program',
                'affected_lines' => array_column($highDowntimeLines, 'line_shift')
            ];
        }

        // High cost per unit
        $highCostLines = array_filter($costData, function($data) {
            return $data['cost_per_unit'] > 60; // Above threshold
        });

        if (!empty($highCostLines)) {
            $opportunities[] = [
                'area' => 'Cost Per Unit',
                'potential_savings' => 'Optimize production efficiency',
                'recommendation' => 'Review process efficiency and material usage',
                'affected_lines' => array_column($highCostLines, 'line_shift')
            ];
        }

        return $opportunities;
    }

    private function analyzeBudgetCompliance($costData) {
        $budgetCompliance = [];

        // Calculate daily budget vs actual
        foreach ($costData as $data) {
            $dailyBudget = 5000; // Example daily budget
            $budgetVariance = $data['total_cost'] - $dailyBudget;
            $budgetVariancePercentage = $dailyBudget > 0 ? ($budgetVariance / $dailyBudget) * 100 : 0;

            $budgetCompliance[] = [
                'line_shift' => $data['line_shift'],
                'date' => $data['date'],
                'daily_budget' => $dailyBudget,
                'actual_cost' => $data['total_cost'],
                'budget_variance' => $budgetVariance,
                'budget_variance_percentage' => $budgetVariancePercentage,
                'is_over_budget' => $budgetVariance > 0
            ];
        }

        return $budgetCompliance;
    }

    private function generateProductionSummary($reportData) {
        if (empty($reportData)) return [];

        $totalOutput = array_sum(array_column($reportData, 'actual_output'));
        $totalPlan = array_sum(array_column($reportData, 'plan'));
        $avgEfficiency = array_sum(array_column($reportData, 'efficiency')) / count($reportData);
        $avgPlanCompletion = array_sum(array_column($reportData, 'plan_completion')) / count($reportData);

        return [
            'total_lines' => count(array_unique(array_column($reportData, 'line_shift'))),
            'total_output' => $totalOutput,
            'total_plan' => $totalPlan,
            'overall_plan_completion' => $totalPlan > 0 ? ($totalOutput / $totalPlan) * 100 : 0,
            'average_efficiency' => $avgEfficiency,
            'average_plan_completion' => $avgPlanCompletion,
            'total_mhr_used' => array_sum(array_column($reportData, 'no_ot_mp')) * 8 + array_sum(array_column($reportData, 'ot_mp')) * 8,
            'total_ot_hours' => array_sum(array_column($reportData, 'ot_hours'))
        ];
    }
}

// Page logic
$reportGenerator = new ReportGenerator($conn, $userRole);
$reportType = $_GET['report_type'] ?? 'production_summary';
$dateRange = $_GET['date_range'] ?? 'last_7_days';
$exportFormat = $_GET['export'] ?? null;

// Handle date range
switch ($dateRange) {
    case 'today':
        $dateFilters = ['start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d')];
        break;
    case 'last_7_days':
        $dateFilters = ['start_date' => date('Y-m-d', strtotime('-6 days')), 'end_date' => date('Y-m-d')];
        break;
    case 'last_30_days':
        $dateFilters = ['start_date' => date('Y-m-d', strtotime('-29 days')), 'end_date' => date('Y-m-d')];
        break;
    case 'this_month':
        $dateFilters = ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-d')];
        break;
    case 'last_month':
        $dateFilters = ['start_date' => date('Y-m-01', strtotime('first day of last month')),
                       'end_date' => date('Y-m-t', strtotime('last day of last month'))];
        break;
    case 'custom':
        $dateFilters = [
            'start_date' => $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 days')),
            'end_date' => $_GET['end_date'] ?? date('Y-m-d')
        ];
        break;
    default:
        $dateFilters = ['start_date' => date('Y-m-d', strtotime('-6 days')), 'end_date' => date('Y-m-d')];
}

// Apply additional filters
$additionalFilters = [];
if (!empty($_GET['shifts'])) {
    $additionalFilters['shifts'] = $_GET['shifts'];
}
if (!empty($_GET['lines'])) {
    $additionalFilters['lines'] = $_GET['lines'];
}
if (!empty($_GET['categories'])) {
    $additionalFilters['categories'] = $_GET['categories'];
}

// Generate report data
try {
    switch ($reportType) {
        case 'production_summary':
            $reportData = $reportGenerator->generateProductionSummary($dateFilters, $additionalFilters);
            break;
        case 'performance_analysis':
            $reportData = $reportGenerator->generatePerformanceAnalysis($dateFilters, $additionalFilters);
            break;
        case 'quality_report':
            $reportData = $reportGenerator->generateQualityReport($dateFilters, $additionalFilters);
            break;
        case 'oee_report':
            $reportData = $reportGenerator->generateOEEReport($dateFilters, $additionalFilters);
            break;
        case 'cost_analysis':
            $reportData = $reportGenerator->generateCostAnalysisReport($dateFilters, $additionalFilters);
            break;
        default:
            $reportData = $reportGenerator->generateProductionSummary($dateFilters, $additionalFilters);
    }

    // Handle export requests
    if ($exportFormat) {
        $reportGenerator->exportReport($reportData, $exportFormat, $reportType . '_' . $dateRange);
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Get available lines and shifts for filters
$linesQuery = "SELECT DISTINCT CONCAT(line_number, '_', shift) as line_shift, line_name, process_category
               FROM production_lines
               ORDER BY line_number, shift";
$linesResult = $conn->query($linesQuery);
$availableLines = [];
while ($row = $linesResult->fetch_assoc()) {
    $availableLines[] = $row;
}

$shiftsQuery = "SELECT DISTINCT shift FROM daily_performance ORDER BY shift";
$shiftsResult = $conn->query($shiftsQuery);
$availableShifts = [];
while ($row = $shiftsResult->fetch_assoc()) {
    $availableShifts[] = $row['shift'];
}

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
    <title>Advanced Reports - Production Management System</title>
    <?php getInlineCSS(); ?>
    <style>
        .report-card { border: 1px solid #dee2e6; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .report-header { background-color: #f8f9fa; padding: 1rem; border-bottom: 1px solid #dee2e6; }
        .report-body { padding: 1.5rem; }
        .report-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .metric-card { background: #f8f9fa; padding: 1rem; border-radius: 0.375rem; text-align: center; }
        .metric-value { font-size: 2rem; font-weight: bold; color: #0d6efd; }
        .metric-label { font-size: 0.875rem; color: #6c757d; margin-top: 0.25rem; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th, .report-table td { border: 1px solid #dee2e6; padding: 0.75rem; }
        .report-table th { background-color: #f8f9fa; font-weight: 600; }
        .report-table tbody tr:nth-child(even) { background-color: #f8f9fa; }
        .report-table tbody tr:hover { background-color: #e9ecef; }
        .chart-container { position: relative; height: 300px; margin: 1rem 0; }
        .trend-indicator { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: bold; }
        .trend-up { background-color: #d4edda; color: #155724; }
        .trend-down { background-color: #f8d7da; color: #721c24; }
        .trend-stable { background-color: #fff3cd; color: #856404; }
        .recommendation-card { background-color: #f8f9fa; border-left: 4px solid #0d6efd; padding: 1rem; margin-bottom: 1rem; }
        .export-btn { margin-right: 0.5rem; }
        @media (max-width: 768px) {
            .report-metrics { grid-template-columns: 1fr; }
            .metric-value { font-size: 1.5rem; }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">Advanced Reports</h1>
                    <div class="d-flex gap-2">
                        <a href="enhanced_dashboard_offline.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="index_lan.php" class="btn btn-primary">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </div>
                </div>

                <!-- Report Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Report Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="col-md-3">
                                <label for="report_type" class="form-label">Report Type</label>
                                <select class="form-select" id="report_type" name="report_type" onchange="this.form.submit()">
                                    <option value="production_summary" <?php echo $reportType === 'production_summary' ? 'selected' : ''; ?>>Production Summary</option>
                                    <option value="performance_analysis" <?php echo $reportType === 'performance_analysis' ? 'selected' : ''; ?>>Performance Analysis</option>
                                    <?php if (in_array($userRole, $reportPermissions['quality_reports'])): ?>
                                    <option value="quality_report" <?php echo $reportType === 'quality_report' ? 'selected' : ''; ?>>Quality Report</option>
                                    <?php endif; ?>
                                    <?php if (in_array($userRole, $reportPermissions['oee_reports'])): ?>
                                    <option value="oee_report" <?php echo $reportType === 'oee_report' ? 'selected' : ''; ?>>OEE Analysis</option>
                                    <?php endif; ?>
                                    <?php if (in_array($userRole, $reportPermissions['cost_analysis'])): ?>
                                    <option value="cost_analysis" <?php echo $reportType === 'cost_analysis' ? 'selected' : ''; ?>>Cost Analysis</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="date_range" class="form-label">Date Range</label>
                                <select class="form-select" id="date_range" name="date_range" onchange="handleDateRangeChange()">
                                    <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="last_7_days" <?php echo $dateRange === 'last_7_days' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="last_30_days" <?php echo $dateRange === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                    <option value="this_month" <?php echo $dateRange === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                    <option value="last_month" <?php echo $dateRange === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                                    <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                                </select>
                            </div>

                            <div id="custom_date_range" class="col-md-6" style="display: <?php echo $dateRange === 'custom' ? 'flex' : 'none'; ?>; gap: 1rem;">
                                <div class="col">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $dateFilters['start_date']; ?>">
                                </div>
                                <div class="col">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $dateFilters['end_date']; ?>">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label for="shifts" class="form-label">Shifts</label>
                                <select class="form-select" id="shifts" name="shifts[]" multiple>
                                    <?php foreach ($availableShifts as $shift): ?>
                                    <option value="<?php echo $shift; ?>" <?php echo in_array($shift, $additionalFilters['shifts'] ?? []) ? 'selected' : ''; ?>>
                                        <?php echo $shift; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="lines" class="form-label">Production Lines</label>
                                <select class="form-select" id="lines" name="lines[]" multiple>
                                    <?php foreach ($availableLines as $line): ?>
                                    <option value="<?php echo $line['line_shift']; ?>" <?php echo in_array($line['line_shift'], $additionalFilters['lines'] ?? []) ? 'selected' : ''; ?>>
                                        <?php echo $line['line_shift'] . ' - ' . $line['line_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Export Options -->
                <?php if (!empty($reportData)): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title"><?php echo htmlspecialchars($reportData['title']); ?></h5>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($reportData['subtitle']); ?></p>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-success export-btn" onclick="exportReport('csv')">
                                    <i class="fas fa-file-csv"></i> Export CSV
                                </button>
                                <button class="btn btn-success export-btn" onclick="exportReport('excel')">
                                    <i class="fas fa-file-excel"></i> Export Excel
                                </button>
                                <button class="btn btn-success export-btn" onclick="exportReport('pdf')">
                                    <i class="fas fa-file-pdf"></i> Export PDF
                                </button>
                                <button class="btn btn-success export-btn" onclick="exportReport('json')">
                                    <i class="fas fa-file-code"></i> Export JSON
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Report Content -->
                <div class="row">
                    <!-- Summary Metrics -->
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Summary Metrics</h5>
                            </div>
                            <div class="card-body">
                                <div class="report-metrics">
                                    <?php if (isset($reportData['summary'])): ?>
                                        <?php foreach ($reportData['summary'] as $key => $value): ?>
                                        <div class="metric-card">
                                            <div class="metric-value">
                                                <?php
                                                if (strpos($key, 'completion') !== false || strpos($key, 'efficiency') !== false) {
                                                    echo number_format($value, 1) . '%';
                                                } elseif (strpos($key, 'cost') !== false || strpos($key, 'plan') !== false || strpos($key, 'output') !== false) {
                                                    echo number_format($value, 0);
                                                } else {
                                                    echo htmlspecialchars($value);
                                                }
                                                ?>
                                            </div>
                                            <div class="metric-label"><?php echo ucwords(str_replace('_', ' ', $key)); ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <?php if (isset($reportData['quality_metrics'])): ?>
                                        <?php foreach ($reportData['quality_metrics'] as $key => $value): ?>
                                        <div class="metric-card">
                                            <div class="metric-value">
                                                <?php
                                                if (strpos($key, 'rate') !== false || strpos($key, 'yield') !== false) {
                                                    echo number_format($value, 1) . '%';
                                                } else {
                                                    echo number_format($value, 0);
                                                }
                                                ?>
                                            </div>
                                            <div class="metric-label"><?php echo ucwords(str_replace('_', ' ', $key)); ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Report Data -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Detailed Report</h5>
                                <small class="text-muted">
                                    Generated: <?php echo $reportData['generated_at']; ?> by <?php echo htmlspecialchars($reportData['generated_by']); ?>
                                </small>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($reportData['data'])): ?>
                                <div class="table-responsive">
                                    <table class="report-table">
                                        <thead>
                                            <tr>
                                                <?php foreach (array_keys($reportData['data'][0]) as $header): ?>
                                                <th><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $header))); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['data'] as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $key => $value): ?>
                                                <td>
                                                    <?php
                                                    if (strpos($key, 'efficiency') !== false || strpos($key, 'completion') !== false || strpos($key, 'utilization') !== false || strpos($key, 'oee') !== false || strpos($key, 'availability') !== false || strpos($key, 'performance') !== false || strpos($key, 'quality') !== false) {
                                                        echo number_format($value, 2) . '%';
                                                    } elseif (strpos($key, 'cost') !== false || strpos($key, 'plan') !== false || strpos($key, 'output') !== false) {
                                                        echo number_format($value, 2);
                                                    } elseif ($key === 'performance_trend' || $key === 'efficiency_trend' || $key === 'completion_trend' || $key === 'cost_per_unit_trend') {
                                                        $trendClass = 'trend-stable';
                                                        if ($value === 'improving') $trendClass = 'trend-up';
                                                        if ($value === 'declining') $trendClass = 'trend-down';
                                                        echo '<span class="trend-indicator ' . $trendClass . '">' . ucfirst($value) . '</span>';
                                                    } else {
                                                        echo htmlspecialchars($value);
                                                    }
                                                    ?>
                                                </td>
                                                <?php endforeach; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No data available for the selected criteria.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recommendations and Analysis -->
                    <?php if (!empty($reportData['recommendations']) || !empty($reportData['improvement_recommendations']) || !empty($reportData['optimization_opportunities'])): ?>
                    <div class="col-12 mt-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Recommendations & Analysis</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($reportData['recommendations'])): ?>
                                    <?php foreach ($reportData['recommendations'] as $rec): ?>
                                    <div class="recommendation-card">
                                        <h6><?php echo htmlspecialchars($rec['line'] ?? ''); ?> - <?php echo htmlspecialchars($rec['issue'] ?? ''); ?></h6>
                                        <p class="mb-0"><?php echo htmlspecialchars($rec['recommendation'] ?? ''); ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (!empty($reportData['improvement_recommendations'])): ?>
                                    <?php foreach ($reportData['improvement_recommendations'] as $rec): ?>
                                    <div class="recommendation-card">
                                        <h6><?php echo htmlspecialchars($rec['line'] ?? ''); ?> - <?php echo htmlspecialchars($rec['component'] ?? ''); ?></h6>
                                        <p class="mb-0"><?php echo htmlspecialchars($rec['issue'] ?? ''); ?></p>
                                        <p class="mb-0"><strong>Recommendation:</strong> <?php echo htmlspecialchars($rec['recommendation'] ?? ''); ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <?php if (!empty($reportData['optimization_opportunities'])): ?>
                                    <?php foreach ($reportData['optimization_opportunities'] as $opp): ?>
                                    <div class="recommendation-card">
                                        <h6><?php echo htmlspecialchars($opp['area']); ?></h6>
                                        <p class="mb-0"><strong>Potential Savings:</strong> <?php echo htmlspecialchars($opp['potential_savings']); ?></p>
                                        <p class="mb-0"><?php echo htmlspecialchars($opp['recommendation']); ?></p>
                                        <?php if (!empty($opp['affected_lines'])): ?>
                                        <p class="mb-0"><small class="text-muted">Affected Lines: <?php echo implode(', ', $opp['affected_lines']); ?></small></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php elseif (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Select report filters and click "Apply Filters" to generate a report.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function handleDateRangeChange() {
            const dateRange = document.getElementById('date_range').value;
            const customDateRange = document.getElementById('custom_date_range');

            if (dateRange === 'custom') {
                customDateRange.style.display = 'flex';
            } else {
                customDateRange.style.display = 'none';
            }
        }

        function clearFilters() {
            window.location.href = 'advanced_reports_offline.php';
        }

        function exportReport(format) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('export', format);
            window.location.href = currentUrl.toString();
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Handle multi-select styling
            const multiSelects = document.querySelectorAll('select[multiple]');
            multiSelects.forEach(select => {
                select.size = Math.min(5, select.options.length);
            });
        });
    </script>
</body>
</html>