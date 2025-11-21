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
        error_log('CSRF validation failed in predictive_analytics.php');
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
    die('Access denied. You do not have permission to access predictive analytics.');
}

/**
 * AI-Powered Predictive Analytics Engine
 * Advanced machine learning algorithms for production forecasting and optimization
 */
class PredictiveAnalyticsEngine {
    private $conn;
    private $userRole;
    private $models = [];
    private $predictions = [];
    private $accuracyMetrics = [];

    public function __construct($conn, $userRole) {
        $this->conn = $conn;
        $this->userRole = $userRole;
        $this->initializeAnalyticsDatabase();
        $this->loadPredictionModels();
    }

    /**
     * Initialize predictive analytics database tables
     */
    private function initializeAnalyticsDatabase() {
        // Create ML models table
        $createModelsTable = "CREATE TABLE IF NOT EXISTS ml_models (
            id INT AUTO_INCREMENT PRIMARY KEY,
            model_name VARCHAR(255) NOT NULL,
            model_type ENUM('production_forecast', 'equipment_failure', 'quality_prediction', 'demand_forecast', 'efficiency_optimization') NOT NULL,
            model_version VARCHAR(50) NOT NULL,
            algorithm ENUM('linear_regression', 'neural_network', 'random_forest', 'arima', 'lstm', 'gradient_boosting') NOT NULL,
            parameters JSON,
            training_data_start DATE NOT NULL,
            training_data_end DATE NOT NULL,
            accuracy_score DECIMAL(5,4),
            precision_score DECIMAL(5,4),
            recall_score DECIMAL(5,4),
            f1_score DECIMAL(5,4),
            mae DECIMAL(10,4), -- Mean Absolute Error
            rmse DECIMAL(10,4), -- Root Mean Square Error
            r2_score DECIMAL(5,4), -- R-squared
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_trained TIMESTAMP NULL,
            training_history JSON,
            INDEX idx_model_type (model_type),
            INDEX idx_algorithm (algorithm),
            INDEX idx_is_active (is_active),
            INDEX idx_accuracy_score (accuracy_score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createModelsTable);

        // Create predictions table
        $createPredictionsTable = "CREATE TABLE IF NOT EXISTS ml_predictions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            model_id INT NOT NULL,
            prediction_type ENUM('production_output', 'equipment_failure', 'quality_issue', 'demand_volume', 'efficiency_score') NOT NULL,
            target_entity VARCHAR(100) NOT NULL, -- line_shift, equipment_id, etc.
            prediction_date DATE NOT NULL,
            prediction_horizon_days INT NOT NULL,
            predicted_value DECIMAL(15,4),
            confidence_interval_lower DECIMAL(15,4),
            confidence_interval_upper DECIMAL(15,4),
            confidence_score DECIMAL(5,4),
            prediction_metadata JSON,
            actual_value DECIMAL(15,4) NULL,
            accuracy_measured BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (model_id) REFERENCES ml_models(id) ON DELETE CASCADE,
            INDEX idx_model_id (model_id),
            INDEX idx_prediction_type (prediction_type),
            INDEX idx_target_entity (target_entity),
            INDEX idx_prediction_date (prediction_date),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createPredictionsTable);

        // Create feature engineering table
        $createFeaturesTable = "CREATE TABLE IF NOT EXISTS feature_engineering (
            id INT AUTO_INCREMENT PRIMARY KEY,
            feature_name VARCHAR(255) NOT NULL,
            feature_type ENUM('numerical', 'categorical', 'temporal', 'derived') NOT NULL,
            data_source VARCHAR(255) NOT NULL,
            extraction_method VARCHAR(255) NOT NULL,
            description TEXT,
            importance_score DECIMAL(5,4),
            correlation_with_target DECIMAL(5,4),
            data_quality_score DECIMAL(5,4),
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_feature_type (feature_type),
            INDEX idx_importance_score (importance_score),
            UNIQUE KEY idx_feature_name (feature_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createFeaturesTable);

        // Create anomaly detection table
        $createAnomaliesTable = "CREATE TABLE IF NOT EXISTS anomaly_detection (
            id INT AUTO_INCREMENT PRIMARY KEY,
            anomaly_type ENUM('performance_drop', 'equipment_anomaly', 'quality_spike', 'demand_variation', 'efficiency_anomaly') NOT NULL,
            line_shift VARCHAR(50),
            equipment_id VARCHAR(100),
            detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            anomaly_score DECIMAL(5,4) NOT NULL,
            threshold_score DECIMAL(5,4) NOT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            description TEXT NOT NULL,
            contributing_factors JSON,
            recommended_actions JSON,
            acknowledged BOOLEAN DEFAULT FALSE,
            acknowledged_by INT NULL,
            acknowledged_at TIMESTAMP NULL,
            resolved BOOLEAN DEFAULT FALSE,
            resolution_method TEXT,
            INDEX idx_anomaly_type (anomaly_type),
            INDEX idx_line_shift (line_shift),
            INDEX idx_detected_at (detected_at),
            INDEX idx_severity (severity),
            INDEX idx_acknowledged (acknowledged)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createAnomaliesTable);

        // Create prediction accuracy tracking table
        $createAccuracyTable = "CREATE TABLE IF NOT EXISTS prediction_accuracy (
            id INT AUTO_INCREMENT PRIMARY KEY,
            prediction_id INT NOT NULL,
            actual_value DECIMAL(15,4) NOT NULL,
            predicted_value DECIMAL(15,4) NOT NULL,
            absolute_error DECIMAL(15,4),
            percentage_error DECIMAL(5,4),
            was_accurate BOOLEAN DEFAULT FALSE,
            accuracy_threshold DECIMAL(5,4) DEFAULT 0.10, -- 10% threshold
            measured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (prediction_id) REFERENCES ml_predictions(id) ON DELETE CASCADE,
            INDEX idx_prediction_id (prediction_id),
            INDEX idx_measured_at (measured_at),
            INDEX idx_was_accurate (was_accurate)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createAccuracyTable);

        // Create automated insights table
        $createInsightsTable = "CREATE TABLE IF NOT EXISTS automated_insights (
            id INT AUTO_INCREMENT PRIMARY KEY,
            insight_type ENUM('trend_analysis', 'correlation', 'optimization', 'anomaly_explanation', 'recommendation') NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            confidence_score DECIMAL(5,4),
            business_impact ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            supporting_data JSON,
            recommendations JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE,
            INDEX idx_insight_type (insight_type),
            INDEX idx_business_impact (business_impact),
            INDEX idx_confidence_score (confidence_score),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createInsightsTable);
    }

    /**
     * Load active prediction models
     */
    private function loadPredictionModels() {
        $query = "SELECT * FROM ml_models WHERE is_active = TRUE ORDER BY accuracy_score DESC";
        $result = $this->conn->query($query);

        while ($row = $result->fetch_assoc()) {
            $this->models[] = $row;
        }
    }

    /**
     * Generate comprehensive production forecasts
     */
    public function generateProductionForecasts($horizonDays = 30) {
        $forecasts = [];

        // Get all production lines
        $linesQuery = "SELECT DISTINCT line_shift FROM daily_performance ORDER BY line_shift";
        $result = $this->conn->query($linesQuery);
        $lines = $result->fetch_all(MYSQLI_ASSOC);

        foreach ($lines as $line) {
            $lineShift = $line['line_shift'];

            // Get historical data for the line
            $historicalData = $this->getHistoricalProductionData($lineShift, 90); // 90 days of data

            if (count($historicalData) < 30) {
                continue; // Skip if insufficient data
            }

            // Generate forecasts using multiple algorithms
            $lineForecasts = [
                'line_shift' => $lineShift,
                'forecasts' => []
            ];

            // Linear Regression Forecast
            $linearForecast = $this->linearRegressionForecast($historicalData, $horizonDays);
            $lineForecasts['forecasts'][] = $linearForecast;

            // Moving Average Forecast
            $maForecast = $this->movingAverageForecast($historicalData, $horizonDays);
            $lineForecasts['forecasts'][] = $maForecast;

            // Exponential Smoothing Forecast
            $expForecast = $this->exponentialSmoothingForecast($historicalData, $horizonDays);
            $lineForecasts['forecasts'][] = $expForecast;

            // Seasonal Decomposition Forecast
            $seasonalForecast = $this->seasonalForecast($historicalData, $horizonDays);
            $lineForecasts['forecasts'][] = $seasonalForecast;

            // Ensemble forecast (weighted average)
            $ensembleForecast = $this->ensembleForecast($lineForecasts['forecasts']);
            $lineForecasts['ensemble_forecast'] = $ensembleForecast;

            // Store predictions in database
            $this->storeForecasts($lineShift, $ensembleForecast, 'production_output', $horizonDays);

            $forecasts[] = $lineForecasts;
        }

        return $forecasts;
    }

    /**
     * Predict equipment failures using machine learning
     */
    public function predictEquipmentFailures() {
        $failurePredictions = [];

        // Get equipment maintenance data
        $maintenanceQuery = "SELECT
                              line_shift,
                              maintenance_type,
                              scheduled_date,
                              completion_date,
                              downtime_hours,
                              failure_mode
                           FROM maintenance_schedules
                           WHERE scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                           ORDER BY line_shift, scheduled_date";

        $result = $this->conn->query($maintenanceQuery);
        $maintenanceData = $result->fetch_all(MYSQLI_ASSOC);

        // Group by line_shift
        $equipmentData = [];
        foreach ($maintenanceData as $maintenance) {
            $lineShift = $maintenance['line_shift'];
            if (!isset($equipmentData[$lineShift])) {
                $equipmentData[$lineShift] = [];
            }
            $equipmentData[$lineShift][] = $maintenance;
        }

        foreach ($equipmentData as $lineShift => $data) {
            if (count($data) < 5) continue; // Need sufficient history

            // Calculate failure risk factors
            $riskFactors = $this->calculateFailureRiskFactors($data);

            // Generate failure probability predictions
            $failurePrediction = $this->predictFailureProbability($lineShift, $riskFactors);

            if ($failurePrediction['probability'] > 0.1) { // Only store if >10% risk
                $failurePredictions[] = $failurePrediction;
                $this->storeFailurePrediction($failurePrediction);
            }
        }

        return $failurePredictions;
    }

    /**
     * Predict quality issues and quality trends
     */
    public function predictQualityIssues() {
        $qualityPredictions = [];

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
                        WHERE qm.date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                        ORDER BY qm.line_shift, qm.checkpoint_id, qm.date";

        $result = $this->conn->query($qualityQuery);
        $qualityData = $result->fetch_all(MYSQLI_ASSOC);

        // Group by checkpoint and line
        $checkpointData = [];
        foreach ($qualityData as $measurement) {
            $key = $measurement['line_shift'] . '_' . $measurement['checkpoint_id'];
            if (!isset($checkpointData[$key])) {
                $checkpointData[$key] = [];
            }
            $checkpointData[$key][] = $measurement;
        }

        foreach ($checkpointData as $key => $data) {
            if (count($data) < 10) continue; // Need sufficient data

            // Calculate quality trends
            $qualityTrend = $this->calculateQualityTrend($data);

            // Predict future quality performance
            $qualityPrediction = $this->predictQualityPerformance($key, $data, $qualityTrend);

            if ($qualityPrediction['risk_level'] !== 'low') {
                $qualityPredictions[] = $qualityPrediction;
                $this->storeQualityPrediction($qualityPrediction);
            }
        }

        return $qualityPredictions;
    }

    /**
     * Generate demand forecasts based on historical patterns
     */
    public function generateDemandForecasts($horizonDays = 90) {
        $demandForecasts = [];

        // Get historical production and plan data
        $demandQuery = "SELECT
                           date,
                           SUM(actual_output) as total_output,
                           SUM(plan) as total_plan,
                           COUNT(DISTINCT line_shift) as active_lines
                        FROM daily_performance
                        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
                        GROUP BY date
                        ORDER BY date";

        $result = $this->conn->query($demandQuery);
        $demandData = $result->fetch_all(MYSQLI_ASSOC);

        if (count($demandData) < 60) return $demandForecasts; // Need sufficient data

        // Analyze demand patterns
        $demandPatterns = $this->analyzeDemandPatterns($demandData);

        // Generate forecast using time series analysis
        $forecast = $this->timeSeriesDemandForecast($demandData, $horizonDays);

        $demandForecasts = [
            'historical_patterns' => $demandPatterns,
            'forecast' => $forecast,
            'confidence_intervals' => $this->calculateForecastConfidence($demandData, $forecast),
            'seasonal_factors' => $this->extractSeasonalFactors($demandData)
        ];

        // Store demand forecast
        $this->storeDemandForecast($demandForecasts);

        return $demandForecasts;
    }

    /**
     * Detect anomalies in production data
     */
    public function detectAnomalies() {
        $anomalies = [];

        // Get recent production data
        $productionQuery = "SELECT
                              line_shift,
                              date,
                              actual_output,
                              plan,
                              efficiency,
                              machine_downtime,
                              input_rate,
                              line_utilization
                           FROM daily_performance
                           WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                           ORDER BY line_shift, date";

        $result = $this->conn->query($productionQuery);
        $productionData = $result->fetch_all(MYSQLI_ASSOC);

        // Group by line
        $lineData = [];
        foreach ($productionData as $record) {
            $lineData[$record['line_shift']][] = $record;
        }

        foreach ($lineData as $lineShift => $data) {
            if (count($data) < 10) continue;

            // Detect anomalies using statistical methods
            $lineAnomalies = $this->detectLineAnomalies($lineShift, $data);
            $anomalies = array_merge($anomalies, $lineAnomalies);
        }

        // Store detected anomalies
        foreach ($anomalies as $anomaly) {
            $this->storeAnomaly($anomaly);
        }

        return $anomalies;
    }

    /**
     * Generate automated insights from data analysis
     */
    public function generateAutomatedInsights() {
        $insights = [];

        // Production trend insights
        $productionInsights = $this->analyzeProductionTrends();
        $insights = array_merge($insights, $productionInsights);

        // Efficiency optimization insights
        $efficiencyInsights = $this->analyzeEfficiencyPatterns();
        $insights = array_merge($insights, $efficiencyInsights);

        // Quality correlation insights
        $qualityInsights = $this->analyzeQualityCorrelations();
        $insights = array_merge($insights, $qualityInsights);

        // Resource utilization insights
        $resourceInsights = $this->analyzeResourceUtilization();
        $insights = array_merge($insights, $resourceInsights);

        // Store insights in database
        foreach ($insights as $insight) {
            $this->storeInsight($insight);
        }

        return $insights;
    }

    /**
     * Machine Learning Implementation Methods
     */

    private function linearRegressionForecast($data, $horizonDays) {
        if (count($data) < 2) return null;

        $n = count($data);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $data[$i]['actual_output'];
            $sumXY += $i * $data[$i]['actual_output'];
            $sumX2 += $i * $i;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        $forecasts = [];
        for ($i = 1; $i <= $horizonDays; $i++) {
            $predictedValue = $intercept + $slope * ($n + $i);
            $forecasts[] = [
                'date' => date('Y-m-d', strtotime('+' . $i . ' days')),
                'predicted_value' => max(0, $predictedValue),
                'confidence' => max(0.1, 1 - ($i / $horizonDays) * 0.5) // Decreasing confidence
            ];
        }

        return [
            'algorithm' => 'linear_regression',
            'forecasts' => $forecasts,
            'model_metrics' => $this->calculateLinearRegressionMetrics($data, $slope, $intercept)
        ];
    }

    private function movingAverageForecast($data, $horizonDays) {
        $windowSize = min(14, count($data) / 3); // 14-day window or 1/3 of data
        $forecasts = [];

        // Calculate moving average
        $recentData = array_slice($data, -$windowSize);
        $movingAverage = array_sum(array_column($recentData, 'actual_output')) / count($recentData);

        // Add trend component
        $trend = $this->calculateTrend(array_column($recentData, 'actual_output'));

        for ($i = 1; $i <= $horizonDays; $i++) {
            $predictedValue = $movingAverage + ($trend * $i);
            $forecasts[] = [
                'date' => date('Y-m-d', strtotime('+' . $i . ' days')),
                'predicted_value' => max(0, $predictedValue),
                'confidence' => max(0.2, 1 - ($i / $horizonDays) * 0.4)
            ];
        }

        return [
            'algorithm' => 'moving_average',
            'window_size' => $windowSize,
            'forecasts' => $forecasts,
            'model_metrics' => ['trend' => $trend, 'base_average' => $movingAverage]
        ];
    }

    private function exponentialSmoothingForecast($data, $horizonDays, $alpha = 0.3) {
        $values = array_column($data, 'actual_output');

        // Initialize
        $smoothed = [$values[0]];

        // Apply exponential smoothing
        for ($i = 1; $i < count($values); $i++) {
            $smoothed[] = $alpha * $values[$i] + (1 - $alpha) * $smoothed[$i - 1];
        }

        $forecasts = [];
        $lastValue = end($smoothed);

        for ($i = 1; $i <= $horizonDays; $i++) {
            // Simple exponential smoothing projection
            $forecasts[] = [
                'date' => date('Y-m-d', strtotime('+' . $i . ' days')),
                'predicted_value' => max(0, $lastValue),
                'confidence' => max(0.3, 1 - ($i / $horizonDays) * 0.3)
            ];
        }

        return [
            'algorithm' => 'exponential_smoothing',
            'alpha' => $alpha,
            'forecasts' => $forecasts,
            'model_metrics' => ['final_smoothed_value' => $lastValue]
        ];
    }

    private function seasonalForecast($data, $horizonDays) {
        // Simple seasonal decomposition (additive model)
        $values = array_column($data, 'actual_output');

        // Calculate weekly seasonality (7-day pattern)
        $weeklyPattern = $this->extractWeeklyPattern($data);

        // Calculate trend
        $trend = $this->calculateTrend($values);

        // Base value (recent average)
        $baseValue = array_sum(array_slice($values, -7)) / 7;

        $forecasts = [];
        for ($i = 1; $i <= $horizonDays; $i++) {
            $dayOfWeek = (date('w', strtotime('+' . $i . 'days')) + 6) % 7; // Adjust for Monday=0
            $seasonalFactor = $weeklyPattern[$dayOfWeek] ?? 1.0;

            $predictedValue = ($baseValue + ($trend * $i)) * $seasonalFactor;

            $forecasts[] = [
                'date' => date('Y-m-d', strtotime('+' . $i . ' days')),
                'predicted_value' => max(0, $predictedValue),
                'confidence' => max(0.4, 1 - ($i / $horizonDays) * 0.2),
                'seasonal_factor' => $seasonalFactor
            ];
        }

        return [
            'algorithm' => 'seasonal_decomposition',
            'weekly_pattern' => $weeklyPattern,
            'forecasts' => $forecasts,
            'model_metrics' => ['base_value' => $baseValue, 'trend' => $trend]
        ];
    }

    private function ensembleForecast($forecasts) {
        if (empty($forecasts)) return null;

        $ensembleForecasts = [];
        $algorithms = [];

        // Collect all forecast dates
        $dates = [];
        foreach ($forecasts[0]['forecasts'] as $forecast) {
            $dates[] = $forecast['date'];
        }

        // Create ensemble by averaging all forecasts
        foreach ($dates as $date) {
            $values = [];
            $confidences = [];
            $algorithmWeights = [];

            foreach ($forecasts as $forecast) {
                $matchingForecast = null;
                foreach ($forecast['forecasts'] as $f) {
                    if ($f['date'] === $date) {
                        $matchingForecast = $f;
                        break;
                    }
                }

                if ($matchingForecast) {
                    // Weight by algorithm performance (simplified)
                    $weight = $this->getAlgorithmWeight($forecast['algorithm']);
                    $values[] = $matchingForecast['predicted_value'] * $weight;
                    $confidences[] = $matchingForecast['confidence'];
                    $algorithmWeights[] = $weight;
                }
            }

            if (!empty($values)) {
                $totalWeight = array_sum($algorithmWeights);
                $ensembleValue = array_sum($values) / $totalWeight;
                $avgConfidence = array_sum($confidences) / count($confidences);

                $ensembleForecasts[] = [
                    'date' => $date,
                    'predicted_value' => $ensembleValue,
                    'confidence' => $avgConfidence,
                    'component_forecasts' => count($values)
                ];
            }
        }

        return [
            'algorithm' => 'ensemble',
            'component_algorithms' => array_column($forecasts, 'algorithm'),
            'forecasts' => $ensembleForecasts
        ];
    }

    /**
     * Helper Methods for Analysis
     */

    private function getHistoricalProductionData($lineShift, $days) {
        $query = "SELECT
                     date,
                     actual_output,
                     plan,
                     efficiency,
                     machine_downtime,
                     input_rate
                  FROM daily_performance
                  WHERE line_shift = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                  ORDER BY date";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $lineShift, $days);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function calculateLinearRegressionMetrics($data, $slope, $intercept) {
        $values = array_column($data, 'actual_output');
        $n = count($values);

        // Calculate R-squared
        $meanY = array_sum($values) / $n;
        $totalSumSquares = 0;
        $residualSumSquares = 0;

        for ($i = 0; $i < $n; $i++) {
            $predicted = $intercept + $slope * $i;
            $totalSumSquares += pow($values[$i] - $meanY, 2);
            $residualSumSquares += pow($values[$i] - $predicted, 2);
        }

        $r2 = $totalSumSquares > 0 ? 1 - ($residualSumSquares / $totalSumSquares) : 0;

        // Calculate MAE and RMSE
        $mae = 0;
        $rmse = 0;

        for ($i = 0; $i < $n; $i++) {
            $predicted = $intercept + $slope * $i;
            $error = abs($values[$i] - $predicted);
            $mae += $error;
            $rmse += $error * $error;
        }

        $mae = $mae / $n;
        $rmse = sqrt($rmse / $n);

        return [
            'r2_score' => $r2,
            'mae' => $mae,
            'rmse' => $rmse,
            'slope' => $slope,
            'intercept' => $intercept
        ];
    }

    private function calculateTrend($values) {
        if (count($values) < 2) return 0;

        $n = count($values);
        $firstHalf = array_slice($values, 0, floor($n / 2));
        $secondHalf = array_slice($values, floor($n / 2));

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        return ($secondAvg - $firstAvg) / count($secondHalf);
    }

    private function extractWeeklyPattern($data) {
        // Extract day-of-week patterns
        $dayPatterns = array_fill(0, 7, []);

        foreach ($data as $record) {
            $dayOfWeek = (date('w', strtotime($record['date'])) + 6) % 7; // Monday=0
            $dayPatterns[$dayOfWeek][] = $record['actual_output'];
        }

        // Calculate average for each day of week
        $weeklyPattern = [];
        $overallAverage = 0;
        $validDays = 0;

        for ($i = 0; $i < 7; $i++) {
            if (!empty($dayPatterns[$i])) {
                $weeklyPattern[$i] = array_sum($dayPatterns[$i]) / count($dayPatterns[$i]);
                $overallAverage += $weeklyPattern[$i];
                $validDays++;
            } else {
                $weeklyPattern[$i] = 0;
            }
        }

        // Normalize (make average day = 1.0)
        if ($validDays > 0) {
            $overallAverage /= $validDays;
            for ($i = 0; $i < 7; $i++) {
                $weeklyPattern[$i] = $overallAverage > 0 ? $weeklyPattern[$i] / $overallAverage : 1.0;
            }
        }

        return $weeklyPattern;
    }

    private function getAlgorithmWeight($algorithm) {
        // Simplified weight assignment based on algorithm performance
        $weights = [
            'linear_regression' => 0.8,
            'moving_average' => 0.7,
            'exponential_smoothing' => 0.9,
            'seasonal_decomposition' => 0.85
        ];

        return $weights[$algorithm] ?? 0.5;
    }

    private function calculateFailureRiskFactors($maintenanceData) {
        $totalDowntime = array_sum(array_column($maintenanceData, 'downtime_hours'));
        $avgDowntime = $totalDowntime / count($maintenanceData);
        $maxDowntime = max(array_column($maintenanceData, 'downtime_hours'));

        // Calculate days since last maintenance
        $lastMaintenance = max(array_column($maintenanceData, 'completion_date'));
        $daysSinceMaintenance = $lastMaintenance ? (strtotime('now') - strtotime($lastMaintenance)) / 86400 : 365;

        return [
            'avg_downtime' => $avgDowntime,
            'max_downtime' => $maxDowntime,
            'maintenance_frequency' => count($maintenanceData) / 180, // per 6 months
            'days_since_maintenance' => $daysSinceMaintenance,
            'failure_history' => $this->countFailures($maintenanceData)
        ];
    }

    private function countFailures($maintenanceData) {
        $failureCount = 0;
        foreach ($maintenanceData as $maintenance) {
            if (strpos(strtolower($maintenance['maintenance_type']), 'emergency') !== false ||
                strpos(strtolower($maintenance['failure_mode']), 'failure') !== false) {
                $failureCount++;
            }
        }
        return $failureCount;
    }

    private function predictFailureProbability($lineShift, $riskFactors) {
        // Simplified probability calculation
        $probability = 0;

        // Base probability
        $probability += 0.05;

        // Add risk factors
        if ($riskFactors['avg_downtime'] > 4) {
            $probability += 0.15;
        }

        if ($riskFactors['days_since_maintenance'] > 30) {
            $probability += ($riskFactors['days_since_maintenance'] - 30) * 0.002;
        }

        if ($riskFactors['failure_history'] > 0) {
            $probability += $riskFactors['failure_history'] * 0.1;
        }

        // Cap at reasonable maximum
        $probability = min(0.95, max(0.05, $probability));

        $severity = 'low';
        if ($probability > 0.5) $severity = 'critical';
        elseif ($probability > 0.3) $severity = 'high';
        elseif ($probability > 0.15) $severity = 'medium';

        return [
            'line_shift' => $lineShift,
            'probability' => round($probability, 3),
            'severity' => $severity,
            'risk_factors' => $riskFactors,
            'recommendation' => $this->generateMaintenanceRecommendation($probability, $severity)
        ];
    }

    private function generateMaintenanceRecommendation($probability, $severity) {
        switch ($severity) {
            case 'critical':
                return 'Immediate inspection required. Schedule preventive maintenance within 24 hours.';
            case 'high':
                return 'Schedule maintenance within 3 days. Increase monitoring frequency.';
            case 'medium':
                return 'Plan maintenance within 1 week. Monitor equipment closely.';
            default:
                return 'Continue routine maintenance schedule. Monitor for changes.';
        }
    }

    private function calculateQualityTrend($data) {
        $conformingRates = [];
        foreach ($data as $measurement) {
            // Group by date and calculate daily conformity rate
            $date = $measurement['date'];
            if (!isset($conformingRates[$date])) {
                $conformingRates[$date] = ['total' => 0, 'conforming' => 0];
            }
            $conformingRates[$date]['total']++;
            if ($measurement['is_conforming']) {
                $conformingRates[$date]['conforming']++;
            }
        }

        $rates = [];
        foreach ($conformingRates as $date => $data) {
            $rates[] = ($data['conforming'] / $data['total']) * 100;
        }

        return $this->calculateTrend($rates);
    }

    private function predictQualityPerformance($key, $data, $trend) {
        $recentData = array_slice($data, -10);
        $conformingCount = 0;
        $totalCount = count($recentData);

        foreach ($recentData as $measurement) {
            if ($measurement['is_conforming']) {
                $conformingCount++;
            }
        }

        $currentYield = ($conformingCount / $totalCount) * 100;
        $predictedYield = $currentYield + ($trend * 7); // Project 7 days forward

        $riskLevel = 'low';
        if ($predictedYield < 85) $riskLevel = 'critical';
        elseif ($predictedYield < 92) $riskLevel = 'high';
        elseif ($predictedYield < 95) $riskLevel = 'medium';

        $parts = explode('_', $key);

        return [
            'checkpoint_key' => $key,
            'line_shift' => $parts[0],
            'checkpoint_id' => $parts[1] ?? 'unknown',
            'current_yield' => round($currentYield, 2),
            'predicted_yield' => round(max(0, min(100, $predictedYield)), 2),
            'trend' => $trend,
            'risk_level' => $riskLevel,
            'confidence' => max(0.3, min(0.9, $totalCount / 20))
        ];
    }

    private function analyzeDemandPatterns($data) {
        $patterns = [];

        // Calculate overall trend
        $outputs = array_column($data, 'total_output');
        $patterns['overall_trend'] = $this->calculateTrend($outputs);

        // Calculate monthly patterns
        $monthlyPatterns = [];
        foreach ($data as $record) {
            $month = date('Y-m', strtotime($record['date']));
            if (!isset($monthlyPatterns[$month])) {
                $monthlyPatterns[$month] = ['total' => 0, 'count' => 0];
            }
            $monthlyPatterns[$month]['total'] += $record['total_output'];
            $monthlyPatterns[$month]['count']++;
        }

        $patterns['monthly_averages'] = [];
        foreach ($monthlyPatterns as $month => $data) {
            $patterns['monthly_averages'][$month] = $data['total'] / $data['count'];
        }

        // Calculate volatility (coefficient of variation)
        $mean = array_sum($outputs) / count($outputs);
        $stdDev = $this->calculateStandardDeviation($outputs);
        $patterns['volatility'] = $mean > 0 ? ($stdDev / $mean) * 100 : 0;

        return $patterns;
    }

    private function timeSeriesDemandForecast($data, $horizonDays) {
        // Use the best performing algorithm for demand forecasting
        $values = array_column($data, 'total_output');

        // Simple exponential smoothing with trend (Holt's method)
        $alpha = 0.3; // Level smoothing
        $beta = 0.1;  // Trend smoothing

        // Initialize
        $level = $values[0];
        $trend = $this->calculateTrend(array_slice($values, 0, min(7, count($values))));

        // Apply Holt's method
        for ($i = 1; $i < count($values); $i++) {
            $prevLevel = $level;
            $level = $alpha * $values[$i] + (1 - $alpha) * ($prevLevel + $trend);
            $trend = $beta * ($level - $prevLevel) + (1 - $beta) * $trend;
        }

        // Generate forecasts
        $forecasts = [];
        for ($i = 1; $i <= $horizonDays; $i++) {
            $forecast = $level + ($trend * $i);
            $forecasts[] = [
                'date' => date('Y-m-d', strtotime('+' . $i . ' days')),
                'predicted_demand' => max(0, round($forecast)),
                'confidence' => max(0.2, 1 - ($i / $horizonDays) * 0.6)
            ];
        }

        return $forecasts;
    }

    private function calculateForecastConfidence($historicalData, $forecast) {
        // Calculate prediction intervals based on historical error patterns
        $errors = [];

        // Use simple out-of-sample validation concept
        $trainingData = array_slice($historicalData, 0, -7);
        $validationData = array_slice($historicalData, -7);

        if (count($trainingData) < 30 || count($validationData) < 7) {
            return ['lower' => 0.8, 'upper' => 1.2]; // Default wide intervals
        }

        // Calculate forecast errors
        foreach ($validationData as $i => $actual) {
            // Simple forecast using recent average
            $recentAvg = array_sum(array_column(array_slice($trainingData, -7), 'total_output')) / 7;
            $error = abs($actual['total_output'] - $recentAvg);
            $errors[] = $error / $actual['total_output']; // Percentage error
        }

        $meanError = array_sum($errors) / count($errors);
        $stdError = $this->calculateStandardDeviation($errors);

        return [
            'lower' => 1 - ($meanError + $stdError),
            'upper' => 1 + ($meanError + $stdError),
            'mean_error' => $meanError,
            'std_error' => $stdError
        ];
    }

    private function extractSeasonalFactors($data) {
        // Extract seasonal patterns (weekly, monthly)
        $factors = [
            'weekly' => [],
            'monthly' => []
        ];

        // Weekly patterns
        $weeklyTotals = array_fill(0, 7, 0);
        $weeklyCounts = array_fill(0, 7, 0);

        foreach ($data as $record) {
            $dayOfWeek = (date('w', strtotime($record['date'])) + 6) % 7; // Monday=0
            $weeklyTotals[$dayOfWeek] += $record['total_output'];
            $weeklyCounts[$dayOfWeek]++;
        }

        $weeklyAverage = array_sum($weeklyTotals) / array_sum($weeklyCounts);
        for ($i = 0; $i < 7; $i++) {
            if ($weeklyCounts[$i] > 0) {
                $factors['weekly'][$i] = ($weeklyTotals[$i] / $weeklyCounts[$i]) / $weeklyAverage;
            }
        }

        return $factors;
    }

    private function detectLineAnomalies($lineShift, $data) {
        $anomalies = [];

        // Detect efficiency anomalies
        $efficiencies = array_column($data, 'efficiency');
        $efficiencyMean = array_sum($efficiencies) / count($efficiencies);
        $efficiencyStd = $this->calculateStandardDeviation($efficiencies);

        foreach ($data as $record) {
            $zScore = ($record['efficiency'] - $efficiencyMean) / $efficiencyStd;

            if (abs($zScore) > 2.5) { // More than 2.5 standard deviations
                $anomalies[] = [
                    'type' => 'efficiency_anomaly',
                    'line_shift' => $lineShift,
                    'date' => $record['date'],
                    'value' => $record['efficiency'],
                    'expected_value' => $efficiencyMean,
                    'z_score' => $zScore,
                    'severity' => abs($zScore) > 3.5 ? 'critical' : 'high',
                    'description' => 'Efficiency significantly ' . ($zScore < 0 ? 'below' : 'above') . ' expected levels'
                ];
            }
        }

        // Detect downtime anomalies
        $downtimes = array_column($data, 'machine_downtime');
        $downtimeMean = array_sum($downtimes) / count($downtimes);
        $downtimeStd = $this->calculateStandardDeviation($downtimes);

        foreach ($data as $record) {
            if ($record['machine_downtime'] > $downtimeMean + (2 * $downtimeStd)) {
                $anomalies[] = [
                    'type' => 'equipment_anomaly',
                    'line_shift' => $lineShift,
                    'date' => $record['date'],
                    'value' => $record['machine_downtime'],
                    'expected_value' => $downtimeMean,
                    'severity' => $record['machine_downtime'] > $downtimeMean + (3 * $downtimeStd) ? 'critical' : 'high',
                    'description' => 'Unusually high equipment downtime detected'
                ];
            }
        }

        return $anomalies;
    }

    private function analyzeProductionTrends() {
        $insights = [];

        // Get production trends
        $query = "SELECT
                     date,
                     SUM(actual_output) as total_output,
                     AVG(efficiency) as avg_efficiency
                  FROM daily_performance
                  WHERE date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                  GROUP BY date
                  ORDER BY date";

        $result = $this->conn->query($query);
        $trendData = $result->fetch_all(MYSQLI_ASSOC);

        if (count($trendData) < 30) return $insights;

        $outputs = array_column($trendData, 'total_output');
        $efficiencies = array_column($trendData, 'avg_efficiency');

        $outputTrend = $this->calculateTrend($outputs);
        $efficiencyTrend = $this->calculateTrend($efficiencies);

        if ($outputTrend > 100) {
            $insights[] = [
                'type' => 'trend_analysis',
                'title' => 'Strong Production Growth Trend',
                'description' => 'Production output has been increasing by an average of ' . round($outputTrend, 1) . ' units per day.',
                'business_impact' => 'high',
                'confidence_score' => 0.85,
                'recommendations' => [
                    'Consider capacity expansion to maintain growth',
                    'Ensure resource availability matches increased demand',
                    'Monitor quality metrics alongside volume growth'
                ]
            ];
        }

        if ($efficiencyTrend < -1) {
            $insights[] = [
                'type' => 'trend_analysis',
                'title' => 'Declining Efficiency Trend',
                'description' => 'System efficiency has been declining by ' . abs(round($efficiencyTrend, 1)) . '% per day.',
                'business_impact' => 'critical',
                'confidence_score' => 0.9,
                'recommendations' => [
                    'Immediate investigation into efficiency decline causes',
                    'Review equipment maintenance schedules',
                    'Assess operator training needs',
                    'Analyze process workflow for bottlenecks'
                ]
            ];
        }

        return $insights;
    }

    private function analyzeEfficiencyPatterns() {
        $insights = [];

        // Get efficiency data by line
        $query = "SELECT
                     line_shift,
                     AVG(efficiency) as avg_efficiency,
                     STDDEV(efficiency) as efficiency_std,
                     COUNT(*) as data_points
                  FROM daily_performance
                  WHERE date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                  GROUP BY line_shift
                  HAVING data_points >= 20";

        $result = $this->conn->query($query);
        $efficiencyData = $result->fetch_all(MYSQLI_ASSOC);

        foreach ($efficiencyData as $line) {
            if ($line['efficiency_std'] > 15) { // High variability
                $insights[] = [
                    'type' => 'optimization',
                    'title' => 'High Efficiency Variability on ' . $line['line_shift'],
                    'description' => $line['line_shift'] . ' shows inconsistent performance with efficiency varying by ±' . round($line['efficiency_std'], 1) . '%.',
                    'business_impact' => 'medium',
                    'confidence_score' => 0.8,
                    'recommendations' => [
                        'Standardize operating procedures',
                        'Provide additional operator training',
                        'Investigate equipment consistency issues',
                        'Implement performance monitoring'
                    ]
                ];
            }

            if ($line['avg_efficiency'] < 70) {
                $insights[] = [
                    'type' => 'optimization',
                    'title' => 'Low Average Efficiency on ' . $line['line_shift'],
                    'description' => $line['line_shift'] . ' has an average efficiency of only ' . round($line['avg_efficiency'], 1) . '%.',
                    'business_impact' => 'high',
                    'confidence_score' => 0.95,
                    'recommendations' => [
                        'Comprehensive process review',
                        'Equipment performance assessment',
                        'Optimize production parameters',
                        'Consider technology upgrades'
                    ]
                ];
            }
        }

        return $insights;
    }

    private function analyzeQualityCorrelations() {
        $insights = [];

        // Get quality vs efficiency correlation
        $query = "SELECT
                     dp.line_shift,
                     dp.efficiency,
                     dp.date,
                     qm.is_conforming
                  FROM daily_performance dp
                  JOIN quality_measurements qm ON dp.line_shift = qm.line_shift AND dp.date = qm.date
                  WHERE dp.date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                  LIMIT 1000";

        $result = $this->conn->query($query);
        $correlationData = $result->fetch_all(MYSQLI_ASSOC);

        if (count($correlationData) < 50) return $insights;

        // Group by efficiency ranges
        $efficiencyRanges = [
            'low' => ['min' => 0, 'max' => 70, 'conforming' => 0, 'total' => 0],
            'medium' => ['min' => 70, 'max' => 85, 'conforming' => 0, 'total' => 0],
            'high' => ['min' => 85, 'max' => 100, 'conforming' => 0, 'total' => 0]
        ];

        foreach ($correlationData as $record) {
            $efficiency = $record['efficiency'];
            $range = 'low';
            if ($efficiency >= 85) $range = 'high';
            elseif ($efficiency >= 70) $range = 'medium';

            $efficiencyRanges[$range]['total']++;
            if ($record['is_conforming']) {
                $efficiencyRanges[$range]['conforming']++;
            }
        }

        // Analyze correlation
        $lowQuality = $efficiencyRanges['low']['total'] > 0 ?
            ($efficiencyRanges['low']['conforming'] / $efficiencyRanges['low']['total']) * 100 : 0;
        $highQuality = $efficiencyRanges['high']['total'] > 0 ?
            ($efficiencyRanges['high']['conforming'] / $efficiencyRanges['high']['total']) * 100 : 0;

        if ($highQuality - $lowQuality > 15) {
            $insights[] = [
                'type' => 'correlation',
                'title' => 'Strong Efficiency-Quality Correlation',
                'description' => 'High efficiency operations (' . round($highQuality, 1) . '% quality rate) significantly outperform low efficiency operations (' . round($lowQuality, 1) . '% quality rate).',
                'business_impact' => 'high',
                'confidence_score' => 0.9,
                'recommendations' => [
                    'Focus on improving operational efficiency to boost quality',
                    'Investigate root causes of efficiency-quality relationship',
                    'Use efficiency metrics as quality predictors'
                ]
            ];
        }

        return $insights;
    }

    private function analyzeResourceUtilization() {
        $insights = [];

        // Get utilization patterns
        $query = "SELECT
                     DATE(date) as production_date,
                     SUM(no_ot_mp * 8 + ot_mp * 8) as total_mhr,
                     SUM(actual_output) as total_output,
                     COUNT(DISTINCT line_shift) as active_lines
                  FROM daily_performance
                  WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  GROUP BY DATE(date)";

        $result = $this->conn->query($query);
        $utilizationData = $result->fetch_all(MYSQLI_ASSOC);

        if (count($utilizationData) < 10) return $insights;

        // Calculate productivity metrics
        $productivityData = [];
        foreach ($utilizationData as $day) {
            $productivity = $day['total_mhr'] > 0 ? ($day['total_output'] / $day['total_mhr']) : 0;
            $productivityData[] = $productivity;
        }

        $avgProductivity = array_sum($productivityData) / count($productivityData);
        $productivityStd = $this->calculateStandardDeviation($productivityData);

        // Find best and worst days
        $bestDay = max($utilizationData, function($a, $b) {
            $productivityA = $a['total_mhr'] > 0 ? ($a['total_output'] / $a['total_mhr']) : 0;
            $productivityB = $b['total_mhr'] > 0 ? ($b['total_output'] / $b['total_mhr']) : 0;
            return $productivityA <=> $productivityB;
        });

        $worstDay = min($utilizationData, function($a, $b) {
            $productivityA = $a['total_mhr'] > 0 ? ($a['total_output'] / $a['total_mhr']) : 0;
            $productivityB = $b['total_mhr'] > 0 ? ($b['total_output'] / $b['total_mhr']) : 0;
            return $productivityA <=> $productivityB;
        });

        $insights[] = [
            'type' => 'optimization',
            'title' => 'Resource Utilization Analysis',
            'description' => 'Average productivity is ' . round($avgProductivity, 2) . ' units per MHR with ' . round($productivityStd, 2) . ' standard deviation.',
            'business_impact' => 'medium',
            'confidence_score' => 0.85,
            'recommendations' => [
                'Study best day (' . $bestDay['production_date'] . ') for productivity patterns',
                'Investigate causes of poor performance on worst day',
                'Standardize resource allocation based on best practices'
            ],
            'supporting_data' => [
                'avg_productivity' => round($avgProductivity, 2),
                'best_productivity' => round($bestDay['total_mhr'] > 0 ? ($bestDay['total_output'] / $bestDay['total_mhr']) : 0, 2),
                'worst_productivity' => round($worstDay['total_mhr'] > 0 ? ($worstDay['total_output'] / $worstDay['total_mhr']) : 0, 2)
            ]
        ];

        return $insights;
    }

    private function calculateStandardDeviation($values) {
        if (count($values) < 2) return 0;

        $mean = array_sum($values) / count($values);
        $variance = 0;

        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }

        return sqrt($variance / (count($values) - 1));
    }

    /**
     * Database Storage Methods
     */

    private function storeForecasts($lineShift, $forecast, $predictionType, $horizonDays) {
        // Store forecast as a model prediction record
        $query = "INSERT INTO ml_predictions
                  (model_id, prediction_type, target_entity, prediction_date, predicted_value,
                   confidence_interval_lower, confidence_interval_upper, confidence_score, prediction_metadata)
                  VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)";

        foreach ($forecast['forecasts'] as $dayForecast) {
            $confidenceLower = $dayForecast['predicted_value'] * 0.9; // ±10%
            $confidenceUpper = $dayForecast['predicted_value'] * 1.1;
            $metadata = json_encode([
                'algorithm' => $forecast['algorithm'],
                'horizon_days' => $horizonDays,
                'component_forecasts' => $forecast['component_algorithms'] ?? []
            ]);

            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                "issdddds",
                $predictionType,
                $lineShift,
                $dayForecast['date'],
                $dayForecast['predicted_value'],
                $confidenceLower,
                $confidenceUpper,
                $dayForecast['confidence'],
                $metadata
            );

            $stmt->execute();
        }
    }

    private function storeFailurePrediction($prediction) {
        $query = "INSERT INTO ml_predictions
                  (model_id, prediction_type, target_entity, prediction_date, predicted_value,
                   confidence_score, prediction_metadata)
                  VALUES (2, 'equipment_failure', ?, CURDATE(), ?, ?, ?)";

        $metadata = json_encode([
            'severity' => $prediction['severity'],
            'risk_factors' => $prediction['risk_factors'],
            'recommendation' => $prediction['recommendation']
        ]);

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sdds",
            $prediction['line_shift'],
            $prediction['probability'],
            $prediction['probability'], // Use probability as confidence
            $metadata
        );

        $stmt->execute();
    }

    private function storeQualityPrediction($prediction) {
        $query = "INSERT INTO ml_predictions
                  (model_id, prediction_type, target_entity, prediction_date, predicted_value,
                   confidence_score, prediction_metadata)
                  VALUES (3, 'quality_issue', ?, CURDATE(), ?, ?, ?)";

        $metadata = json_encode([
            'current_yield' => $prediction['current_yield'],
            'trend' => $prediction['trend'],
            'risk_level' => $prediction['risk_level'],
            'line_shift' => $prediction['line_shift'],
            'checkpoint_id' => $prediction['checkpoint_id']
        ]);

        // Risk score (inverse of yield)
        $riskScore = 1 - ($prediction['predicted_yield'] / 100);

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sdds",
            $prediction['checkpoint_key'],
            $riskScore,
            $prediction['confidence'],
            $metadata
        );

        $stmt->execute();
    }

    private function storeDemandForecast($forecast) {
        $query = "INSERT INTO ml_predictions
                  (model_id, prediction_type, target_entity, prediction_date, predicted_value,
                   confidence_interval_lower, confidence_interval_upper, confidence_score, prediction_metadata)
                  VALUES (4, 'demand_volume', 'system_total', ?, ?, ?, ?, ?, ?)";

        $metadata = json_encode([
            'forecast_type' => 'time_series',
            'historical_patterns' => $forecast['historical_patterns'],
            'seasonal_factors' => $forecast['seasonal_factors']
        ]);

        foreach ($forecast['forecast'] as $dayForecast) {
            $confidenceLower = $dayForecast['predicted_demand'] * ($forecast['confidence_intervals']['lower'] ?? 0.9);
            $confidenceUpper = $dayForecast['predicted_demand'] * ($forecast['confidence_intervals']['upper'] ?? 1.1);

            $stmt = $this->conn->prepare($query);
            $stmt->bind_param(
                "sddddds",
                $dayForecast['date'],
                $dayForecast['predicted_demand'],
                $confidenceLower,
                $confidenceUpper,
                $dayForecast['confidence'],
                $metadata
            );

            $stmt->execute();
        }
    }

    private function storeAnomaly($anomaly) {
        $query = "INSERT INTO anomaly_detection
                  (anomaly_type, line_shift, detected_at, anomaly_score, threshold_score, severity, description, contributing_factors, recommended_actions)
                  VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "ssddssss",
            $anomaly['type'],
            $anomaly['line_shift'],
            abs($anomaly['z_score'] ?? $anomaly['value']),
            2.5, // Standard threshold
            $anomaly['severity'],
            $anomaly['description'],
            json_encode(['z_score' => $anomaly['z_score'] ?? 0, 'value' => $anomaly['value']]),
            json_encode(['Investigate anomaly causes', 'Review process parameters', 'Check equipment status'])
        );

        $stmt->execute();
    }

    private function storeInsight($insight) {
        $query = "INSERT INTO automated_insights
                  (insight_type, title, description, confidence_score, business_impact, supporting_data, recommendations)
                  VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sssdsss",
            $insight['type'],
            $insight['title'],
            $insight['description'],
            $insight['confidence_score'],
            $insight['business_impact'],
            json_encode($insight['supporting_data'] ?? []),
            json_encode($insight['recommendations'])
        );

        $stmt->execute();
    }

    /**
     * Public Methods for Dashboard Integration
     */

    public function getDashboardData() {
        return [
            'production_forecasts' => $this->getLatestForecasts('production_output'),
            'failure_predictions' => $this->getLatestPredictions('equipment_failure'),
            'quality_predictions' => $this->getLatestPredictions('quality_issue'),
            'demand_forecasts' => $this->getLatestForecasts('demand_volume'),
            'recent_anomalies' => $this->getRecentAnomalies(),
            'automated_insights' => $this->getLatestInsights(),
            'model_performance' => $this->getModelPerformanceMetrics()
        ];
    }

    private function getLatestForecasts($predictionType) {
        $query = "SELECT
                     target_entity,
                     prediction_date,
                     predicted_value,
                     confidence_interval_lower,
                     confidence_interval_upper,
                     confidence_score
                  FROM ml_predictions
                  WHERE prediction_type = ? AND prediction_date >= CURDATE()
                  ORDER BY prediction_date, target_entity
                  LIMIT 50";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $predictionType);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function getLatestPredictions($predictionType) {
        $query = "SELECT
                     target_entity,
                     prediction_date,
                     predicted_value,
                     confidence_score,
                     prediction_metadata
                  FROM ml_predictions
                  WHERE prediction_type = ? AND prediction_date >= CURDATE() - INTERVAL 7 DAY
                  ORDER BY prediction_date DESC
                  LIMIT 20";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $predictionType);
        $stmt->execute();
        $result = $stmt->get_result();

        $predictions = [];
        while ($row = $result->fetch_assoc()) {
            $metadata = json_decode($row['prediction_metadata'], true);
            $row['metadata'] = $metadata;
            $predictions[] = $row;
        }

        return $predictions;
    }

    private function getRecentAnomalies() {
        $query = "SELECT
                     anomaly_type,
                     line_shift,
                     detected_at,
                     severity,
                     description
                  FROM anomaly_detection
                  WHERE detected_at >= NOW() - INTERVAL 7 DAY AND acknowledged = FALSE
                  ORDER BY detected_at DESC
                  LIMIT 20";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function getLatestInsights() {
        $query = "SELECT
                     insight_type,
                     title,
                     description,
                     confidence_score,
                     business_impact,
                     created_at
                  FROM automated_insights
                  WHERE is_active = TRUE AND (expires_at IS NULL OR expires_at > NOW())
                  ORDER BY business_impact DESC, confidence_score DESC, created_at DESC
                  LIMIT 10";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function getModelPerformanceMetrics() {
        $query = "SELECT
                     model_type,
                     algorithm,
                     accuracy_score,
                     precision_score,
                     recall_score,
                     f1_score,
                     COUNT(*) as model_count
                  FROM ml_models
                  WHERE is_active = TRUE
                  GROUP BY model_type, algorithm
                  ORDER BY accuracy_score DESC";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Page logic
$analyticsEngine = new PredictiveAnalyticsEngine($conn, $userRole);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'generate_forecasts':
            $forecasts = $analyticsEngine->generateProductionForecasts(30);
            $failures = $analyticsEngine->predictEquipmentFailures();
            $quality = $analyticsEngine->predictQualityIssues();
            $success = true;
            $message = "Generated AI-powered predictions for production, equipment, and quality.";
            break;

        case 'detect_anomalies':
            $anomalies = $analyticsEngine->detectAnomalies();
            $success = true;
            $message = "Detected " . count($anomalies) . " production anomalies requiring attention.";
            break;

        case 'generate_insights':
            $insights = $analyticsEngine->generateAutomatedInsights();
            $success = true;
            $message = "Generated " . count($insights) . " automated insights from data analysis.";
            break;

        default:
            $success = false;
            $message = 'Invalid action';
    }

    // Redirect with message
    header('Location: predictive_analytics_ai_offline.php?success=' . ($success ? '1' : '0') . '&message=' . urlencode($message));
    exit;
}

// Get dashboard data
$dashboardData = $analyticsEngine->getDashboardData();

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
    <title>Predictive Analytics AI - Production Management System</title>
    <?php getInlineCSS(); ?>
    <style>
        .analytics-card { border: 1px solid #dee2e6; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .analytics-header { background-color: #f8f9fa; padding: 1rem; border-bottom: 1px solid #dee2e6; }
        .analytics-body { padding: 1.5rem; }
        .prediction-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 0.5rem; padding: 1.5rem; text-align: center; margin-bottom: 1rem; }
        .forecast-chart { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 0.375rem; padding: 1rem; height: 300px; position: relative; }
        .anomaly-item { border-left: 4px solid #dc3545; padding: 0.75rem; margin-bottom: 0.5rem; background: #f8f9fa; }
        .insight-item { border: 1px solid #e9ecef; border-radius: 0.375rem; padding: 1rem; margin-bottom: 1rem; }
        .confidence-bar { width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .confidence-fill { height: 100%; background: linear-gradient(90deg, #dc3545, #ffc107, #28a745); transition: width 0.3s ease; }
        .model-metric { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid #e9ecef; }
        .ai-controls { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        @media (max-width: 768px) {
            .ai-controls { flex-direction: column; }
            .prediction-card { margin-bottom: 0.5rem; }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">AI-Powered Predictive Analytics</h1>
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

                <!-- AI Analytics Controls -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">AI Model Controls</h5>
                    </div>
                    <div class="card-body">
                        <div class="ai-controls">
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="generate_forecasts">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-brain"></i> Generate AI Forecasts
                                </button>
                            </form>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="detect_anomalies">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-search"></i> Detect Anomalies
                                </button>
                            </form>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="generate_insights">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-lightbulb"></i> Generate Insights
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- AI Predictions Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="prediction-card">
                            <div class="h4">Production</div>
                            <div class="h2"><?php echo count($dashboardData['production_forecasts']); ?></div>
                            <div class="small">Active Forecasts</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="prediction-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="h4">Equipment</div>
                            <div class="h2"><?php echo count($dashboardData['failure_predictions']); ?></div>
                            <div class="small">Risk Predictions</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="prediction-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="h4">Quality</div>
                            <div class="h2"><?php echo count($dashboardData['quality_predictions']); ?></div>
                            <div class="small">Quality Forecasts</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="prediction-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <div class="h4">Anomalies</div>
                            <div class="h2"><?php echo count($dashboardData['recent_anomalies']); ?></div>
                            <div class="small">Detected Issues</div>
                        </div>
                    </div>
                </div>

                <!-- Production Forecasts -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="analytics-card">
                            <div class="analytics-header">
                                <h5 class="card-title mb-0">Production Forecasts</h5>
                            </div>
                            <div class="analytics-body">
                                <?php if (!empty($dashboardData['production_forecasts'])): ?>
                                    <div class="forecast-chart">
                                        <div class="text-center text-muted">
                                            <i class="fas fa-chart-line fa-3x mb-3"></i>
                                            <p>Production forecast chart visualization</p>
                                            <small>Next 30 days predictions using AI algorithms</small>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="list-group">
                                            <?php $displayForecasts = array_slice($dashboardData['production_forecasts'], 0, 5); ?>
                                            <?php foreach ($displayForecasts as $forecast): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($forecast['target_entity']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo date('M j, Y', strtotime($forecast['prediction_date'])); ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <strong><?php echo number_format($forecast['predicted_value']); ?></strong>
                                                        <br>
                                                        <small>Conf: <?php echo number_format($forecast['confidence_score'] * 100, 1); ?>%</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                                        <p>No production forecasts available.</p>
                                        <small>Generate forecasts to see AI predictions.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="analytics-card">
                            <div class="analytics-header">
                                <h5 class="card-title mb-0">Equipment Failure Predictions</h5>
                            </div>
                            <div class="analytics-body">
                                <?php if (!empty($dashboardData['failure_predictions'])): ?>
                                    <div class="list-group">
                                        <?php $displayFailures = array_slice($dashboardData['failure_predictions'], 0, 5); ?>
                                        <?php foreach ($displayFailures as $failure): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($failure['target_entity']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">Failure Risk</small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-<?php echo ($failure['predicted_value'] > 0.3) ? 'danger' : (($failure['predicted_value'] > 0.1) ? 'warning' : 'success'); ?>">
                                                        <?php echo number_format($failure['predicted_value'] * 100, 1); ?>%
                                                    </span>
                                                </div>
                                            </div>
                                            <?php if (!empty($failure['metadata'])): ?>
                                                <div class="progress mb-2" style="height: 6px;">
                                                    <div class="progress-bar bg-<?php echo ($failure['predicted_value'] > 0.3) ? 'danger' : 'warning'; ?>"
                                                         style="width: <?php echo $failure['predicted_value'] * 100; ?>%"></div>
                                                </div>
                                                <?php if (!empty($failure['metadata']['recommendation'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($failure['metadata']['recommendation']); ?></small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-tools fa-3x mb-3"></i>
                                        <p>No equipment failure predictions available.</p>
                                        <small>Run AI analysis to predict equipment failures.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quality Predictions and Anomalies -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="analytics-card">
                            <div class="analytics-header">
                                <h5 class="card-title mb-0">Quality Predictions</h5>
                            </div>
                            <div class="analytics-body">
                                <?php if (!empty($dashboardData['quality_predictions'])): ?>
                                    <div class="list-group">
                                        <?php $displayQuality = array_slice($dashboardData['quality_predictions'], 0, 5); ?>
                                        <?php foreach ($displayQuality as $quality): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($quality['target_entity']); ?></strong>
                                                    <br>
                                                    <?php if (!empty($quality['metadata']['risk_level'])): ?>
                                                    <span class="badge bg-<?php echo ($quality['metadata']['risk_level'] === 'critical') ? 'danger' : (($quality['metadata']['risk_level'] === 'high') ? 'warning' : 'info'); ?>">
                                                        <?php echo ucfirst($quality['metadata']['risk_level']); ?> Risk
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted">Confidence</small>
                                                    <br>
                                                    <strong><?php echo number_format($quality['confidence_score'] * 100, 1); ?>%</strong>
                                                </div>
                                            </div>
                                            <?php if (!empty($quality['metadata'])): ?>
                                                <div class="progress mb-2" style="height: 4px;">
                                                    <div class="progress-bar"
                                                         style="width: <?php echo $quality['confidence_score'] * 100; ?>%"></div>
                                                </div>
                                                <small class="text-muted">
                                                    <?php if (isset($quality['metadata']['current_yield'])): ?>
                                                    Current: <?php echo number_format($quality['metadata']['current_yield'], 1); ?>%
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-clipboard-check fa-3x mb-3"></i>
                                        <p>No quality predictions available.</p>
                                        <small>Generate predictions to see AI quality forecasts.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="analytics-card">
                            <div class="analytics-header">
                                <h5 class="card-title mb-0">Recent Anomalies</h5>
                            </div>
                            <div class="analytics-body">
                                <?php if (!empty($dashboardData['recent_anomalies'])): ?>
                                    <?php $displayAnomalies = array_slice($dashboardData['recent_anomalies'], 0, 5); ?>
                                    <?php foreach ($displayAnomalies as $anomaly): ?>
                                    <div class="anomaly-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo htmlspecialchars($anomaly['line_shift'] ?? 'System'); ?></strong>
                                                <br>
                                                <small><?php echo htmlspecialchars($anomaly['description']); ?></small>
                                            </div>
                                            <div>
                                                <span class="badge bg-<?php echo ($anomaly['severity'] === 'critical') ? 'danger' : (($anomaly['severity'] === 'high') ? 'warning' : 'info'); ?>">
                                                    <?php echo ucfirst($anomaly['severity']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y H:i', strtotime($anomaly['detected_at'])); ?>
                                        </small>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                                        <p>No anomalies detected.</p>
                                        <small>System is performing within expected parameters.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Insights -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="analytics-card">
                            <div class="analytics-header">
                                <h5 class="card-title mb-0">AI-Generated Insights</h5>
                            </div>
                            <div class="analytics-body">
                                <?php if (!empty($dashboardData['automated_insights'])): ?>
                                    <?php foreach ($dashboardData['automated_insights'] as $insight): ?>
                                    <div class="insight-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1">
                                                    <i class="fas fa-lightbulb text-warning"></i>
                                                    <?php echo htmlspecialchars($insight['title']); ?>
                                                </h6>
                                                <span class="badge bg-<?php echo ($insight['business_impact'] === 'critical') ? 'danger' : (($insight['business_impact'] === 'high') ? 'warning' : 'info'); ?> me-1">
                                                    <?php echo ucfirst($insight['business_impact']); ?>
                                                </span>
                                                <span class="badge bg-secondary">
                                                    <?php echo number_format($insight['confidence_score'] * 100, 1); ?>% confidence
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($insight['created_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-2"><?php echo htmlspecialchars($insight['description']); ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-brain fa-3x mb-3"></i>
                                        <p>No AI insights available.</p>
                                        <small>Generate insights to see AI-powered recommendations.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Model Performance -->
                <div class="row">
                    <div class="col-12">
                        <div class="analytics-card">
                            <div class="analytics-header">
                                <h5 class="card-title mb-0">AI Model Performance</h5>
                            </div>
                            <div class="analytics-body">
                                <?php if (!empty($dashboardData['model_performance'])): ?>
                                    <div class="row">
                                        <?php foreach ($dashboardData['model_performance'] as $model): ?>
                                        <div class="col-md-4 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($model['model_type']); ?></h6>
                                                    <div class="model-metric">
                                                        <span>Algorithm:</span>
                                                        <strong><?php echo htmlspecialchars($model['algorithm']); ?></strong>
                                                    </div>
                                                    <div class="model-metric">
                                                        <span>Accuracy:</span>
                                                        <strong><?php echo number_format($model['accuracy_score'] * 100, 1); ?>%</strong>
                                                    </div>
                                                    <?php if ($model['precision_score']): ?>
                                                    <div class="model-metric">
                                                        <span>Precision:</span>
                                                        <strong><?php echo number_format($model['precision_score'] * 100, 1); ?>%</strong>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div class="model-metric">
                                                        <span>Models:</span>
                                                        <strong><?php echo $model['model_count']; ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-chart-bar fa-2x mb-3"></i>
                                        <p>No model performance data available.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh AI data every 10 minutes
        setTimeout(function() {
            window.location.reload();
        }, 600000);

        // Handle form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing AI Analysis...';
                }
            });
        });

        // Animate prediction cards on page load
        document.addEventListener('DOMContentLoaded', function() {
            const predictionCards = document.querySelectorAll('.prediction-card');
            predictionCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>