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
        error_log('CSRF validation failed in iot_sensors.php');
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
    die('Access denied. You do not have permission to access IoT sensor system.');
}

/**
 * IoT Sensor Integration and Real-time Monitoring System
 * Simulates and manages sensor data for production monitoring
 */
class IoTSensorManager {
    private $conn;
    private $userRole;
    private $sensors = [];
    private $sensorData = [];
    private $alerts = [];

    public function __construct($conn, $userRole) {
        $this->conn = $conn;
        $this->userRole = $userRole;
        $this->initializeIoTDatabase();
        $this->loadActiveSensors();
    }

    /**
     * Initialize IoT sensor database tables
     */
    private function initializeIoTDatabase() {
        // Create sensors table
        $createSensorsTable = "CREATE TABLE IF NOT EXISTS iot_sensors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sensor_id VARCHAR(100) NOT NULL UNIQUE,
            sensor_name VARCHAR(255) NOT NULL,
            sensor_type ENUM('temperature', 'pressure', 'humidity', 'vibration', 'flow_rate', 'power_consumption', 'speed', 'position', 'voltage', 'current', 'quality_metric', 'production_count', 'downtime_detector') NOT NULL,
            line_shift VARCHAR(50),
            equipment_id VARCHAR(100),
            location_description TEXT,
            unit_of_measure VARCHAR(50),
            min_value DECIMAL(10,4),
            max_value DECIMAL(10,4),
            normal_min DECIMAL(10,4),
            normal_max DECIMAL(10,4),
            critical_threshold_min DECIMAL(10,4),
            critical_threshold_max DECIMAL(10,4),
            sampling_frequency INT DEFAULT 60, -- seconds
            is_active BOOLEAN DEFAULT TRUE,
            calibration_date DATE,
            next_calibration_date DATE,
            manufacturer VARCHAR(255),
            model_number VARCHAR(100),
            installation_date DATE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_sensor_type (sensor_type),
            INDEX idx_line_shift (line_shift),
            INDEX idx_equipment_id (equipment_id),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createSensorsTable);

        // Create sensor readings table
        $createReadingsTable = "CREATE TABLE IF NOT EXISTS sensor_readings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sensor_id VARCHAR(100) NOT NULL,
            reading_timestamp TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
            value DECIMAL(15,6) NOT NULL,
            unit VARCHAR(50),
            quality_indicator ENUM('good', 'uncertain', 'bad') DEFAULT 'good',
            status ENUM('normal', 'warning', 'critical', 'error') DEFAULT 'normal',
            metadata JSON,
            processing_status ENUM('pending', 'processed', 'error') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sensor_timestamp (sensor_id, reading_timestamp),
            INDEX idx_reading_timestamp (reading_timestamp),
            INDEX idx_status (status),
            INDEX idx_processing_status (processing_status),
            FOREIGN KEY (sensor_id) REFERENCES iot_sensors(sensor_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createReadingsTable);

        // Create sensor alerts table
        $createAlertsTable = "CREATE TABLE IF NOT EXISTS sensor_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sensor_id VARCHAR(100) NOT NULL,
            alert_type ENUM('threshold_breach', 'sensor_offline', 'calibration_due', 'data_anomaly', 'communication_error') NOT NULL,
            severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            alert_message TEXT NOT NULL,
            trigger_value DECIMAL(15,6),
            threshold_value DECIMAL(15,6),
            reading_timestamp TIMESTAMP(3) NOT NULL,
            acknowledged BOOLEAN DEFAULT FALSE,
            acknowledged_by INT NULL,
            acknowledged_at TIMESTAMP NULL,
            resolved BOOLEAN DEFAULT FALSE,
            resolved_at TIMESTAMP NULL,
            resolution_method TEXT,
            notification_sent BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sensor_id) REFERENCES iot_sensors(sensor_id) ON DELETE CASCADE,
            FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_sensor_id (sensor_id),
            INDEX idx_alert_type (alert_type),
            INDEX idx_severity (severity),
            INDEX idx_acknowledged (acknowledged),
            INDEX idx_resolved (resolved),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createAlertsTable);

        // Create sensor analytics table
        $createAnalyticsTable = "CREATE TABLE IF NOT EXISTS sensor_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sensor_id VARCHAR(100) NOT NULL,
            analysis_period ENUM('hourly', 'daily', 'weekly', 'monthly') NOT NULL,
            period_start TIMESTAMP NOT NULL,
            period_end TIMESTAMP NOT NULL,
            avg_value DECIMAL(15,6),
            min_value DECIMAL(15,6),
            max_value DECIMAL(15,6),
            std_deviation DECIMAL(15,6),
            trend_direction ENUM('increasing', 'decreasing', 'stable') NOT NULL,
            trend_strength DECIMAL(5,4),
            anomaly_count INT DEFAULT 0,
            alert_count INT DEFAULT 0,
            uptime_percentage DECIMAL(5,2),
            data_quality_score DECIMAL(5,4),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sensor_id) REFERENCES iot_sensors(sensor_id) ON DELETE CASCADE,
            INDEX idx_sensor_period (sensor_id, analysis_period),
            INDEX idx_period_start (period_start),
            UNIQUE KEY idx_sensor_period_start (sensor_id, analysis_period, period_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createAnalyticsTable);

        // Create sensor maintenance table
        $createMaintenanceTable = "CREATE TABLE IF NOT EXISTS sensor_maintenance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sensor_id VARCHAR(100) NOT NULL,
            maintenance_type ENUM('calibration', 'cleaning', 'repair', 'replacement', 'inspection') NOT NULL,
            scheduled_date DATE NOT NULL,
            completed_date DATE NULL,
            technician VARCHAR(255),
            maintenance_notes TEXT,
            cost DECIMAL(10,2),
            parts_replaced TEXT,
            next_maintenance_date DATE NULL,
            status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sensor_id) REFERENCES iot_sensors(sensor_id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_sensor_id (sensor_id),
            INDEX idx_scheduled_date (scheduled_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createMaintenanceTable);

        // Create sensor dashboard configurations table
        $createDashboardsTable = "CREATE TABLE IF NOT EXISTS sensor_dashboards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dashboard_name VARCHAR(255) NOT NULL,
            description TEXT,
            configuration JSON NOT NULL,
            layout JSON,
            refresh_interval INT DEFAULT 30, -- seconds
            is_default BOOLEAN DEFAULT FALSE,
            is_public BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_is_default (is_default),
            INDEX idx_is_public (is_public)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createDashboardsTable);
    }

    /**
     * Load active sensors from database
     */
    private function loadActiveSensors() {
        $query = "SELECT * FROM iot_sensors WHERE is_active = TRUE ORDER BY sensor_type, line_shift";
        $result = $this->conn->query($query);

        while ($row = $result->fetch_assoc()) {
            $this->sensors[] = $row;
        }
    }

    /**
     * Simulate real-time sensor data generation
     */
    public function generateSensorData() {
        $generatedData = [];
        $currentTime = date('Y-m-d H:i:s');

        foreach ($this->sensors as $sensor) {
            $reading = $this->generateSensorReading($sensor, $currentTime);
            if ($reading) {
                $this->storeSensorReading($sensor['sensor_id'], $reading);
                $this->checkThresholds($sensor, $reading);
                $generatedData[] = [
                    'sensor_id' => $sensor['sensor_id'],
                    'sensor_name' => $sensor['sensor_name'],
                    'sensor_type' => $sensor['sensor_type'],
                    'value' => $reading['value'],
                    'unit' => $sensor['unit_of_measure'],
                    'status' => $reading['status'],
                    'timestamp' => $currentTime
                ];
            }
        }

        return $generatedData;
    }

    /**
     * Generate individual sensor reading
     */
    private function generateSensorReading($sensor, $timestamp) {
        // Get last reading for trend continuity
        $lastReading = $this->getLastReading($sensor['sensor_id']);
        $baseValue = $lastReading ? $lastReading['value'] : $this->getBaseValue($sensor);

        // Generate realistic sensor data based on sensor type
        $reading = $this->generateRealisticReading($sensor, $baseValue);

        // Determine status based on thresholds
        $status = $this->determineReadingStatus($sensor, $reading);

        // Add some realistic noise and potential anomalies
        $reading = $this->addRealisticVariation($reading, $sensor['sensor_type']);

        return [
            'value' => $reading,
            'unit' => $sensor['unit_of_measure'],
            'quality_indicator' => $this->determineQualityIndicator($reading, $sensor),
            'status' => $status,
            'metadata' => json_encode([
                'generated_at' => $timestamp,
                'base_value' => $baseValue,
                'variation_applied' => true
            ])
        ];
    }

    /**
     * Generate realistic reading based on sensor type
     */
    private function generateRealisticReading($sensor, $baseValue) {
        switch ($sensor['sensor_type']) {
            case 'temperature':
                // Temperature: gradual changes with daily patterns
                $hour = (int)date('H');
                $dailyVariation = sin(($hour - 6) * M_PI / 12) * 5; // ±5°C daily pattern
                $randomVariation = (rand(-100, 100) / 1000); // ±0.1°C random
                return $baseValue + $dailyVariation + $randomVariation;

            case 'pressure':
                // Pressure: stable with small fluctuations
                $randomVariation = (rand(-50, 50) / 10000); // ±0.005 units
                return $baseValue + $randomVariation;

            case 'humidity':
                // Humidity: changes with daily patterns
                $hour = (int)date('H');
                $dailyVariation = cos(($hour - 14) * M_PI / 12) * 10; // ±10% daily pattern
                $randomVariation = (rand(-200, 200) / 1000); // ±0.2% random
                return $baseValue + $dailyVariation + $randomVariation;

            case 'vibration':
                // Vibration: random spikes for equipment activity
                $baseVariation = (rand(-100, 100) / 10000); // Base variation
                $spike = (rand(0, 100) < 5) ? (rand(100, 500) / 1000) : 0; // 5% chance of spike
                return $baseValue + $baseVariation + $spike;

            case 'flow_rate':
                // Flow rate: depends on production activity
                $activityFactor = $this->getProductionActivityFactor();
                $randomVariation = (rand(-100, 100) / 10000);
                return ($baseValue * $activityFactor) + $randomVariation;

            case 'power_consumption':
                // Power consumption: correlates with activity
                $activityFactor = $this->getProductionActivityFactor();
                $randomVariation = (rand(-200, 200) / 10000);
                return ($baseValue * $activityFactor) + $randomVariation;

            case 'speed':
                // Speed: relatively stable with small variations
                $randomVariation = (rand(-50, 50) / 10000);
                return $baseValue + $randomVariation;

            case 'voltage':
                // Voltage: should be very stable
                $randomVariation = (rand(-10, 10) / 10000);
                return $baseValue + $randomVariation;

            case 'current':
                // Current: varies with load
                $loadFactor = $this->getProductionActivityFactor();
                $randomVariation = (rand(-50, 50) / 10000);
                return ($baseValue * $loadFactor) + $randomVariation;

            case 'production_count':
                // Production count: incremental
                $increment = rand(1, 10);
                return $baseValue + $increment;

            case 'quality_metric':
                // Quality metric: generally high with occasional issues
                $baseQuality = 95; // 95% base quality
                $qualityIssue = (rand(0, 100) < 3) ? (rand(5, 20) * -1) : 0; // 3% chance of quality issue
                $randomVariation = (rand(-100, 100) / 1000);
                return $baseQuality + $qualityIssue + $randomVariation;

            case 'downtime_detector':
                // Downtime detector: mostly zero with occasional downtime
                $downtimeEvent = (rand(0, 1000) < 5) ? 1 : 0; // 0.5% chance of downtime
                return $downtimeEvent;

            default:
                // Default: small random variation
                $randomVariation = (rand(-100, 100) / 10000);
                return $baseValue + $randomVariation;
        }
    }

    /**
     * Get base value for sensor type
     */
    private function getBaseValue($sensor) {
        $baseValues = [
            'temperature' => 25.0, // °C
            'pressure' => 101.325, // kPa
            'humidity' => 45.0, // %
            'vibration' => 0.5, // mm/s
            'flow_rate' => 100.0, // L/min
            'power_consumption' => 50.0, // kW
            'speed' => 1500.0, // RPM
            'voltage' => 230.0, // V
            'current' => 10.0, // A
            'position' => 0.0, // Relative position
            'production_count' => 0, // Count
            'quality_metric' => 95.0, // %
            'downtime_detector' => 0 // Binary
        ];

        return $baseValues[$sensor['sensor_type']] ?? 0.0;
    }

    /**
     * Get production activity factor (0.6 to 1.2)
     */
    private function getProductionActivityFactor() {
        $hour = (int)date('H');

        // Simulate shift patterns
        if ($hour >= 6 && $hour < 14) {
            return 0.8 + (rand(-100, 100) / 1000); // Morning shift
        } elseif ($hour >= 14 && $hour < 22) {
            return 0.9 + (rand(-100, 100) / 1000); // Afternoon shift
        } else {
            return 0.6 + (rand(-50, 50) / 1000); // Night shift
        }
    }

    /**
     * Add realistic variation to sensor reading
     */
    private function addRealisticVariation($value, $sensorType) {
        // Simulate sensor drift over time
        $driftFactor = 1.0 + (rand(-10, 10) / 10000);

        // Simulate occasional sensor noise
        if (rand(0, 100) < 2) { // 2% chance of noise
            $noise = (rand(-500, 500) / 1000);
            $value += $noise;
        }

        return round($value * $driftFactor, 6);
    }

    /**
     * Determine reading status based on thresholds
     */
    private function determineReadingStatus($sensor, $value) {
        if ($value < $sensor['critical_threshold_min'] || $value > $sensor['critical_threshold_max']) {
            return 'critical';
        } elseif ($value < $sensor['normal_min'] || $value > $sensor['normal_max']) {
            return 'warning';
        } else {
            return 'normal';
        }
    }

    /**
     * Determine quality indicator for reading
     */
    private function determineQualityIndicator($value, $sensor) {
        // Simulate occasional quality issues
        if (rand(0, 100) < 1) { // 1% chance of quality issue
            return 'bad';
        } elseif (rand(0, 100) < 5) { // 5% chance of uncertain reading
            return 'uncertain';
        } else {
            return 'good';
        }
    }

    /**
     * Get last reading for trend continuity
     */
    private function getLastReading($sensorId) {
        $query = "SELECT value FROM sensor_readings WHERE sensor_id = ? ORDER BY reading_timestamp DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $sensorId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * Store sensor reading in database
     */
    private function storeSensorReading($sensorId, $reading) {
        $query = "INSERT INTO sensor_readings
                  (sensor_id, reading_timestamp, value, unit, quality_indicator, status, metadata)
                  VALUES (?, NOW(6), ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sdssss",
            $sensorId,
            $reading['value'],
            $reading['unit'],
            $reading['quality_indicator'],
            $reading['status'],
            $reading['metadata']
        );

        return $stmt->execute();
    }

    /**
     * Check thresholds and generate alerts if needed
     */
    private function checkThresholds($sensor, $reading) {
        $status = $reading['status'];

        if ($status === 'critical' || $status === 'warning') {
            $alertType = 'threshold_breach';
            $severity = $status === 'critical' ? 'critical' : 'high';
            $message = $status === 'critical' ?
                "Critical threshold breached for {$sensor['sensor_name']}. Value: {$reading['value']} {$sensor['unit_of_measure']}" :
                "Warning threshold exceeded for {$sensor['sensor_name']}. Value: {$reading['value']} {$sensor['unit_of_measure']}";

            $this->createSensorAlert($sensor['sensor_id'], $alertType, $severity, $message, $reading['value']);
        }

        // Check for sensor offline (no recent readings)
        $this->checkSensorOffline($sensor);

        // Check for calibration due
        $this->checkCalibrationDue($sensor);
    }

    /**
     * Create sensor alert
     */
    private function createSensorAlert($sensorId, $alertType, $severity, $message, $triggerValue) {
        $query = "INSERT INTO sensor_alerts
                  (sensor_id, alert_type, severity, alert_message, trigger_value, reading_timestamp)
                  VALUES (?, ?, ?, ?, ?, NOW(6))";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssssds", $sensorId, $alertType, $severity, $message, $triggerValue);
        return $stmt->execute();
    }

    /**
     * Check if sensor is offline (no recent readings)
     */
    private function checkSensorOffline($sensor) {
        $query = "SELECT COUNT(*) as recent_readings FROM sensor_readings
                  WHERE sensor_id = ? AND reading_timestamp >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $sensor['sensor_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['recent_readings'] == 0) {
            // Check if we already have a recent offline alert for this sensor
            $recentAlertQuery = "SELECT COUNT(*) as recent_offline_alerts FROM sensor_alerts
                                 WHERE sensor_id = ? AND alert_type = 'sensor_offline'
                                 AND acknowledged = FALSE
                                 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";

            $stmt = $this->conn->prepare($recentAlertQuery);
            $stmt->bind_param("s", $sensor['sensor_id']);
            $stmt->execute();
            $alertResult = $stmt->get_result();
            $alertRow = $alertResult->fetch_assoc();

            if ($alertRow['recent_offline_alerts'] == 0) {
                $message = "Sensor {$sensor['sensor_name']} appears to be offline - no data received in 5 minutes";
                $this->createSensorAlert($sensor['sensor_id'], 'sensor_offline', 'high', $message, 0);
            }
        }
    }

    /**
     * Check if calibration is due
     */
    private function checkCalibrationDue($sensor) {
        if ($sensor['next_calibration_date'] && date('Y-m-d') >= $sensor['next_calibration_date']) {
            // Check if we already have a calibration alert
            $recentAlertQuery = "SELECT COUNT(*) as recent_calibration_alerts FROM sensor_alerts
                                 WHERE sensor_id = ? AND alert_type = 'calibration_due'
                                 AND acknowledged = FALSE";

            $stmt = $this->conn->prepare($recentAlertQuery);
            $stmt->bind_param("s", $sensor['sensor_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            if ($row['recent_calibration_alerts'] == 0) {
                $message = "Calibration due for sensor {$sensor['sensor_name']}. Due date: {$sensor['next_calibration_date']}";
                $this->createSensorAlert($sensor['sensor_id'], 'calibration_due', 'medium', $message, 0);
            }
        }
    }

    /**
     * Create default sensors for production lines
     */
    public function createDefaultSensors() {
        $defaultSensors = [];

        // Get production lines
        $query = "SELECT DISTINCT line_shift FROM daily_performance ORDER BY line_shift LIMIT 5";
        $result = $this->conn->query($query);
        $lines = $result->fetch_all(MYSQLI_ASSOC);

        foreach ($lines as $line) {
            $lineShift = $line['line_shift'];

            // Create standard sensors for each line
            $defaultSensors[] = [
                'sensor_id' => $lineShift . '_TEMP_01',
                'sensor_name' => $lineShift . ' Temperature Sensor 1',
                'sensor_type' => 'temperature',
                'line_shift' => $lineShift,
                'equipment_id' => 'MAIN_EQUIPMENT',
                'location_description' => 'Primary production area',
                'unit_of_measure' => '°C',
                'min_value' => 15.0,
                'max_value' => 40.0,
                'normal_min' => 18.0,
                'normal_max' => 30.0,
                'critical_threshold_min' => 10.0,
                'critical_threshold_max' => 45.0
            ];

            $defaultSensors[] = [
                'sensor_id' => $lineShift . '_POWER_01',
                'sensor_name' => $lineShift . ' Power Monitor',
                'sensor_type' => 'power_consumption',
                'line_shift' => $lineShift,
                'equipment_id' => 'MAIN_EQUIPMENT',
                'location_description' => 'Electrical panel',
                'unit_of_measure' => 'kW',
                'min_value' => 0.0,
                'max_value' => 100.0,
                'normal_min' => 20.0,
                'normal_max' => 80.0,
                'critical_threshold_min' => 0.0,
                'critical_threshold_max' => 95.0
            ];

            $defaultSensors[] = [
                'sensor_id' => $lineShift . '_PRODUCTION_01',
                'sensor_name' => $lineShift . ' Production Counter',
                'sensor_type' => 'production_count',
                'line_shift' => $lineShift,
                'equipment_id' => 'CONVEYOR_SYSTEM',
                'location_description' => 'Production line exit',
                'unit_of_measure' => 'units',
                'min_value' => 0.0,
                'max_value' => 999999.0,
                'normal_min' => 0.0,
                'normal_max' => 10000.0,
                'critical_threshold_min' => 0.0,
                'critical_threshold_max' => 999999.0
            ];

            $defaultSensors[] = [
                'sensor_id' => $lineShift . '_QUALITY_01',
                'sensor_name' => $lineShift . ' Quality Monitor',
                'sensor_type' => 'quality_metric',
                'line_shift' => $lineShift,
                'equipment_id' => 'QUALITY_STATION',
                'location_description' => 'Quality inspection point',
                'unit_of_measure' => '%',
                'min_value' => 0.0,
                'max_value' => 100.0,
                'normal_min' => 90.0,
                'normal_max' => 100.0,
                'critical_threshold_min' => 80.0,
                'critical_threshold_max' => 100.0
            ];
        }

        // Insert sensors into database
        $createdCount = 0;
        foreach ($defaultSensors as $sensor) {
            if ($this->createSensor($sensor)) {
                $createdCount++;
            }
        }

        return $createdCount;
    }

    /**
     * Create individual sensor
     */
    private function createSensor($sensorData) {
        // Check if sensor already exists
        $checkQuery = "SELECT id FROM iot_sensors WHERE sensor_id = ?";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bind_param("s", $sensorData['sensor_id']);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            return false; // Sensor already exists
        }

        $query = "INSERT INTO iot_sensors
                  (sensor_id, sensor_name, sensor_type, line_shift, equipment_id,
                   location_description, unit_of_measure, min_value, max_value,
                   normal_min, normal_max, critical_threshold_min, critical_threshold_max,
                   sampling_frequency, calibration_date, next_calibration_date, created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $calibrationDate = date('Y-m-d');
        $nextCalibrationDate = date('Y-m-d', strtotime('+6 months'));

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sssssssddddddddddsi",
            $sensorData['sensor_id'],
            $sensorData['sensor_name'],
            $sensorData['sensor_type'],
            $sensorData['line_shift'],
            $sensorData['equipment_id'],
            $sensorData['location_description'],
            $sensorData['unit_of_measure'],
            $sensorData['min_value'],
            $sensorData['max_value'],
            $sensorData['normal_min'],
            $sensorData['normal_max'],
            $sensorData['critical_threshold_min'],
            $sensorData['critical_threshold_max'],
            $sensorData['sampling_frequency'] ?? 60,
            $calibrationDate,
            $nextCalibrationDate,
            $_SESSION['user_id']
        );

        return $stmt->execute();
    }

    /**
     * Get sensor dashboard data
     */
    public function getDashboardData() {
        return [
            'active_sensors' => $this->getActiveSensorsCount(),
            'sensor_types' => $this->getSensorTypes(),
            'recent_alerts' => $this->getRecentAlerts(),
            'sensor_health' => $this->getSensorHealth(),
            'real_time_data' => $this->getLatestReadings(),
            'analytics_summary' => $this->getAnalyticsSummary(),
            'maintenance_schedule' => $this->getMaintenanceSchedule()
        ];
    }

    /**
     * Get active sensors count
     */
    private function getActiveSensorsCount() {
        $query = "SELECT COUNT(*) as count FROM iot_sensors WHERE is_active = TRUE";
        $result = $this->conn->query($query);
        $row = $result->fetch_assoc();
        return $row['count'];
    }

    /**
     * Get sensor types breakdown
     */
    private function getSensorTypes() {
        $query = "SELECT sensor_type, COUNT(*) as count FROM iot_sensors WHERE is_active = TRUE GROUP BY sensor_type ORDER BY count DESC";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get recent alerts
     */
    private function getRecentAlerts() {
        $query = "SELECT sa.*, s.sensor_name, s.line_shift
                  FROM sensor_alerts sa
                  JOIN iot_sensors s ON sa.sensor_id = s.sensor_id
                  WHERE sa.acknowledged = FALSE
                  ORDER BY sa.created_at DESC
                  LIMIT 10";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get sensor health summary
     */
    private function getSensorHealth() {
        $query = "SELECT
                     COUNT(*) as total_sensors,
                     SUM(CASE WHEN last_reading >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) as online_sensors,
                     SUM(CASE WHEN next_calibration_date <= CURDATE() THEN 1 ELSE 0 END) as calibration_due,
                     AVG(CASE WHEN last_reading >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN data_quality_score ELSE NULL END) as avg_quality
                  FROM iot_sensors s
                  LEFT JOIN (
                      SELECT sensor_id, MAX(reading_timestamp) as last_reading, AVG(CASE WHEN quality_indicator = 'good' THEN 1 ELSE 0 END) as data_quality_score
                      FROM sensor_readings
                      WHERE reading_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                      GROUP BY sensor_id
                  ) lr ON s.sensor_id = lr.sensor_id
                  WHERE s.is_active = TRUE";

        $result = $this->conn->query($query);
        return $result->fetch_assoc();
    }

    /**
     * Get latest sensor readings
     */
    private function getLatestReadings() {
        $query = "SELECT
                     sr.sensor_id,
                     s.sensor_name,
                     s.sensor_type,
                     s.line_shift,
                     s.unit_of_measure,
                     sr.value,
                     sr.status,
                     sr.reading_timestamp
                  FROM sensor_readings sr
                  JOIN iot_sensors s ON sr.sensor_id = s.sensor_id
                  JOIN (
                      SELECT sensor_id, MAX(reading_timestamp) as max_timestamp
                      FROM sensor_readings
                      WHERE reading_timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                      GROUP BY sensor_id
                  ) latest ON sr.sensor_id = latest.sensor_id AND sr.reading_timestamp = latest.max_timestamp
                  WHERE s.is_active = TRUE
                  ORDER BY sr.reading_timestamp DESC
                  LIMIT 20";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get analytics summary
     */
    private function getAnalyticsSummary() {
        $query = "SELECT
                     sensor_type,
                     COUNT(DISTINCT sensor_id) as sensor_count,
                     AVG(uptime_percentage) as avg_uptime,
                     AVG(data_quality_score) as avg_quality,
                     SUM(anomaly_count) as total_anomalies
                  FROM sensor_analytics
                  WHERE analysis_period = 'daily' AND period_start >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  GROUP BY sensor_type
                  ORDER BY sensor_type";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get maintenance schedule
     */
    private function getMaintenanceSchedule() {
        $query = "SELECT
                     sm.*,
                     s.sensor_name,
                     s.sensor_type,
                     s.line_shift,
                     DATEDIFF(sm.scheduled_date, CURDATE()) as days_until_maintenance
                  FROM sensor_maintenance sm
                  JOIN iot_sensors s ON sm.sensor_id = s.sensor_id
                  WHERE sm.status IN ('scheduled', 'in_progress')
                  ORDER BY sm.scheduled_date ASC
                  LIMIT 10";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Process sensor data and generate analytics
     */
    public function processAnalytics() {
        $this->generateHourlyAnalytics();
        $this->generateDailyAnalytics();
        $this->cleanupOldData();
    }

    /**
     * Generate hourly analytics
     */
    private function generateHourlyAnalytics() {
        $sensors = $this->getActiveSensors();

        foreach ($sensors as $sensor) {
            $this->calculateSensorAnalytics($sensor['sensor_id'], 'hourly');
        }
    }

    /**
     * Generate daily analytics
     */
    private function generateDailyAnalytics() {
        $sensors = $this->getActiveSensors();

        foreach ($sensors as $sensor) {
            $this->calculateSensorAnalytics($sensor['sensor_id'], 'daily');
        }
    }

    /**
     * Calculate analytics for a specific sensor
     */
    private function calculateSensorAnalytics($sensorId, $period) {
        $periodStart = $this->getPeriodStart($period);
        $periodEnd = date('Y-m-d H:i:s');

        // Get readings for the period
        $query = "SELECT
                     AVG(value) as avg_value,
                     MIN(value) as min_value,
                     MAX(value) as max_value,
                     STDDEV(value) as std_deviation,
                     COUNT(*) as reading_count,
                     SUM(CASE WHEN quality_indicator = 'good' THEN 1 ELSE 0 END) as good_readings
                  FROM sensor_readings
                  WHERE sensor_id = ? AND reading_timestamp BETWEEN ? AND ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $sensorId, $periodStart, $periodEnd);
        $stmt->execute();
        $result = $stmt->get_result();
        $analytics = $result->fetch_assoc();

        if ($analytics && $analytics['reading_count'] > 0) {
            // Calculate additional metrics
            $trendDirection = $this->calculateTrendDirection($sensorId, $period);
            $anomalyCount = $this->countAnomalies($sensorId, $periodStart, $periodEnd);
            $alertCount = $this->countAlerts($sensorId, $periodStart, $periodEnd);
            $uptimePercentage = ($analytics['reading_count'] / $this->getExpectedReadings($period)) * 100;
            $dataQualityScore = ($analytics['good_readings'] / $analytics['reading_count']) * 100;

            // Store analytics
            $insertQuery = "INSERT INTO sensor_analytics
                           (sensor_id, analysis_period, period_start, period_end, avg_value, min_value, max_value,
                            std_deviation, trend_direction, anomaly_count, alert_count, uptime_percentage, data_quality_score)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE
                           avg_value = VALUES(avg_value), min_value = VALUES(min_value), max_value = VALUES(max_value),
                           std_deviation = VALUES(std_deviation), trend_direction = VALUES(trend_direction),
                           anomaly_count = VALUES(anomaly_count), alert_count = VALUES(alert_count),
                           uptime_percentage = VALUES(uptime_percentage), data_quality_score = VALUES(data_quality_score)";

            $stmt = $this->conn->prepare($insertQuery);
            $stmt->bind_param(
                "ssssddssdddidd",
                $sensorId,
                $period,
                $periodStart,
                $periodEnd,
                $analytics['avg_value'],
                $analytics['min_value'],
                $analytics['max_value'],
                $analytics['std_deviation'],
                $trendDirection,
                $anomalyCount,
                $alertCount,
                $uptimePercentage,
                $dataQualityScore
            );

            $stmt->execute();
        }
    }

    /**
     * Get active sensors
     */
    private function getActiveSensors() {
        $query = "SELECT * FROM iot_sensors WHERE is_active = TRUE";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get period start time for analytics
     */
    private function getPeriodStart($period) {
        switch ($period) {
            case 'hourly':
                return date('Y-m-d H:00:00', strtotime('-1 hour'));
            case 'daily':
                return date('Y-m-d 00:00:00', strtotime('-1 day'));
            case 'weekly':
                return date('Y-m-d 00:00:00', strtotime('-1 week'));
            case 'monthly':
                return date('Y-m-01 00:00:00', strtotime('-1 month'));
            default:
                return date('Y-m-d H:00:00', strtotime('-1 hour'));
        }
    }

    /**
     * Calculate trend direction
     */
    private function calculateTrendDirection($sensorId, $period) {
        $periodStart = $this->getPeriodStart($period);
        $periodEnd = date('Y-m-d H:i:s');

        // Split period into two halves
        $midPoint = date('Y-m-d H:i:s', strtotime(($periodStart + $periodEnd) / 2));

        $query = "SELECT AVG(value) as avg_value FROM sensor_readings
                  WHERE sensor_id = ? AND reading_timestamp BETWEEN ? AND ?";

        // First half
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $sensorId, $periodStart, $midPoint);
        $stmt->execute();
        $firstHalf = $stmt->get_result()->fetch_assoc();

        // Second half
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $sensorId, $midPoint, $periodEnd);
        $stmt->execute();
        $secondHalf = $stmt->get_result()->fetch_assoc();

        if ($firstHalf && $secondHalf) {
            $difference = $secondHalf['avg_value'] - $firstHalf['avg_value'];
            $changePercent = ($difference / $firstHalf['avg_value']) * 100;

            if ($changePercent > 5) return 'increasing';
            if ($changePercent < -5) return 'decreasing';
            return 'stable';
        }

        return 'stable';
    }

    /**
     * Count anomalies in period
     */
    private function countAnomalies($sensorId, $periodStart, $periodEnd) {
        $query = "SELECT COUNT(*) as anomaly_count
                  FROM sensor_readings
                  WHERE sensor_id = ? AND reading_timestamp BETWEEN ? AND ?
                  AND status IN ('warning', 'critical')";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $sensorId, $periodStart, $periodEnd);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['anomaly_count'];
    }

    /**
     * Count alerts in period
     */
    private function countAlerts($sensorId, $periodStart, $periodEnd) {
        $query = "SELECT COUNT(*) as alert_count
                  FROM sensor_alerts
                  WHERE sensor_id = ? AND reading_timestamp BETWEEN ? AND ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("sss", $sensorId, $periodStart, $periodEnd);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['alert_count'];
    }

    /**
     * Get expected readings for period
     */
    private function getExpectedReadings($period) {
        switch ($period) {
            case 'hourly':
                return 60; // 1 reading per minute for 1 hour
            case 'daily':
                return 1440; // 1 reading per minute for 24 hours
            case 'weekly':
                return 10080; // 1 reading per minute for 7 days
            case 'monthly':
                return 43200; // Approximate for 30 days
            default:
                return 60;
        }
    }

    /**
     * Clean up old sensor data
     */
    private function cleanupOldData() {
        // Delete readings older than 30 days
        $query = "DELETE FROM sensor_readings WHERE reading_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $this->conn->query($query);

        // Delete old analytics (keep monthly data)
        $query = "DELETE FROM sensor_analytics
                  WHERE analysis_period IN ('hourly', 'daily') AND period_end < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        $this->conn->query($query);

        // Delete resolved alerts older than 7 days
        $query = "DELETE FROM sensor_alerts
                  WHERE resolved = TRUE AND resolved_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $this->conn->query($query);
    }
}

// Page logic
$iotManager = new IoTSensorManager($conn, $userRole);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'generate_data':
            $sensorData = $iotManager->generateSensorData();
            $success = true;
            $message = "Generated " . count($sensorData) . " sensor readings successfully.";
            break;

        case 'create_default_sensors':
            $createdCount = $iotManager->createDefaultSensors();
            $success = true;
            $message = "Created " . $createdCount . " default IoT sensors.";
            break;

        case 'process_analytics':
            $iotManager->processAnalytics();
            $success = true;
            $message = "Sensor analytics processed successfully.";
            break;

        default:
            $success = false;
            $message = 'Invalid action';
    }

    // Redirect with message
    header('Location: iot_sensors_offline.php?success=' . ($success ? '1' : '0') . '&message=' . urlencode($message));
    exit;
}

// Get dashboard data
$dashboardData = $iotManager->getDashboardData();

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
    <title>IoT Sensor Integration - Production Management System</title>
    <?php getInlineCSS(); ?>
    <style>
        .sensor-card { border: 1px solid #dee2e6; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .sensor-header { background-color: #f8f9fa; padding: 1rem; border-bottom: 1px solid #dee2e6; }
        .sensor-body { padding: 1.5rem; }
        .sensor-metric { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 0.5rem; padding: 1.5rem; text-align: center; margin-bottom: 1rem; }
        .reading-item { border-left: 4px solid #28a745; padding: 0.75rem; margin-bottom: 0.5rem; background: #f8f9fa; }
        .reading-item.warning { border-left-color: #ffc107; }
        .reading-item.critical { border-left-color: #dc3545; }
        .alert-item { border-left: 4px solid #dc3545; padding: 0.75rem; margin-bottom: 0.5rem; background: #f8f9fa; }
        .sensor-status { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 0.5rem; }
        .status-online { background-color: #28a745; }
        .status-offline { background-color: #dc3545; }
        .status-warning { background-color: #ffc107; }
        .real-time-indicator { width: 8px; height: 8px; border-radius: 50%; background-color: #28a745; display: inline-block; margin-right: 0.5rem; animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
        .sensor-controls { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        @media (max-width: 768px) {
            .sensor-controls { flex-direction: column; }
            .sensor-metric { margin-bottom: 0.5rem; }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">
                        IoT Sensor Integration
                        <span class="real-time-indicator"></span>
                        <small class="text-muted">Real-time Monitoring</small>
                    </h1>
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

                <!-- IoT Controls -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">IoT System Controls</h5>
                    </div>
                    <div class="card-body">
                        <div class="sensor-controls">
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="generate_data">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-satellite-dish"></i> Generate Sensor Data
                                </button>
                            </form>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="create_default_sensors">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Create Default Sensors
                                </button>
                            </form>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="action" value="process_analytics">
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-chart-line"></i> Process Analytics
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- IoT System Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="sensor-metric">
                            <div class="h4"><?php echo $dashboardData['active_sensors']; ?></div>
                            <div class="small">Active Sensors</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="sensor-metric" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="h4"><?php echo count($dashboardData['sensor_types']); ?></div>
                            <div class="small">Sensor Types</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="sensor-metric" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="h4"><?php echo count($dashboardData['recent_alerts']); ?></div>
                            <div class="small">Active Alerts</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="sensor-metric" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <div class="h4"><?php echo round($dashboardData['sensor_health']['online_sensors'] ?? 0, 0); ?></div>
                            <div class="small">Online Sensors</div>
                        </div>
                    </div>
                </div>

                <!-- Sensor Types Distribution -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="sensor-card">
                            <div class="sensor-header">
                                <h5 class="card-title mb-0">Sensor Types Distribution</h5>
                            </div>
                            <div class="sensor-body">
                                <?php if (!empty($dashboardData['sensor_types'])): ?>
                                    <?php foreach ($dashboardData['sensor_types'] as $type): ?>
                                    <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                                        <div>
                                            <strong><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type['sensor_type']))); ?></strong>
                                            <div class="progress mt-1" style="height: 4px;">
                                                <div class="progress-bar bg-info" style="width: <?php echo ($type['count'] / $dashboardData['active_sensors']) * 100; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary"><?php echo $type['count']; ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-microchip fa-3x mb-3"></i>
                                        <p>No sensors configured.</p>
                                        <button class="btn btn-success" onclick="document.querySelector('form[action=\"?action=create_default_sensors\"]').submit()">
                                            <i class="fas fa-plus"></i> Create Default Sensors
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="sensor-card">
                            <div class="sensor-header">
                                <h5 class="card-title mb-0">System Health</h5>
                            </div>
                            <div class="sensor-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="sensor-status status-online d-inline-block"></div>
                                        <h5><?php echo round(($dashboardData['sensor_health']['online_sensors'] / $dashboardData['sensor_health']['total_sensors']) * 100, 1); ?>%</h5>
                                        <small class="text-muted">Online</small>
                                    </div>
                                    <div class="col-4">
                                        <h5><?php echo round($dashboardData['sensor_health']['avg_quality'] * 100, 1); ?>%</h5>
                                        <small class="text-muted">Data Quality</small>
                                    </div>
                                    <div class="col-4">
                                        <h5><?php echo $dashboardData['sensor_health']['calibration_due']; ?></h5>
                                        <small class="text-muted">Calibration Due</small>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-info-circle"></i>
                                        System is operating at optimal performance levels.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Real-time Sensor Readings -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="sensor-card">
                            <div class="sensor-header">
                                <h5 class="card-title mb-0">
                                    Real-time Sensor Readings
                                    <span class="real-time-indicator"></span>
                                </h5>
                            </div>
                            <div class="sensor-body">
                                <?php if (!empty($dashboardData['real_time_data'])): ?>
                                    <div class="row">
                                        <?php $displayReadings = array_slice($dashboardData['real_time_data'], 0, 8); ?>
                                        <?php foreach ($displayReadings as $reading): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="reading-item <?php echo $reading['status']; ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <span class="sensor-status status-online"></span>
                                                            <?php echo htmlspecialchars($reading['sensor_name']); ?>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($reading['line_shift']); ?> •
                                                            <?php echo htmlspecialchars($reading['sensor_type']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="text-end">
                                                        <strong><?php echo number_format($reading['value'], 2); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($reading['unit']); ?></small>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <small class="text-muted"><?php echo date('H:i:s', strtotime($reading['reading_timestamp'])); ?></small>
                                                    <span class="badge bg-<?php echo ($reading['status'] === 'normal') ? 'success' : (($reading['status'] === 'warning') ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($reading['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-satellite-dish fa-3x mb-3"></i>
                                        <p>No real-time data available.</p>
                                        <button class="btn btn-primary" onclick="document.querySelector('form[action=\"?action=generate_data\"]').submit()">
                                            <i class="fas fa-play"></i> Generate Sensor Data
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Alerts -->
                <div class="row mb-4">
                    <div class="col-lg-6">
                        <div class="sensor-card">
                            <div class="sensor-header">
                                <h5 class="card-title mb-0">Recent Sensor Alerts</h5>
                            </div>
                            <div class="sensor-body">
                                <?php if (!empty($dashboardData['recent_alerts'])): ?>
                                    <?php foreach ($dashboardData['recent_alerts'] as $alert): ?>
                                    <div class="alert-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">
                                                    <span class="sensor-status status-<?php echo ($alert['severity'] === 'critical') ? 'offline' : 'warning'; ?>"></span>
                                                    <?php echo htmlspecialchars($alert['sensor_name']); ?>
                                                </h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($alert['alert_type']); ?></small>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($alert['line_shift']); ?></small>
                                            </div>
                                            <div>
                                                <span class="badge bg-<?php echo ($alert['severity'] === 'critical') ? 'danger' : 'warning'; ?>">
                                                    <?php echo ucfirst($alert['severity']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <p class="mb-2 mt-2"><?php echo htmlspecialchars($alert['alert_message']); ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('M j, Y H:i', strtotime($alert['created_at'])); ?>
                                        </small>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                                        <p>No active sensor alerts.</p>
                                        <small>All sensors are operating within normal parameters.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="sensor-card">
                            <div class="sensor-header">
                                <h5 class="card-title mb-0">Analytics Summary</h5>
                            </div>
                            <div class="sensor-body">
                                <?php if (!empty($dashboardData['analytics_summary'])): ?>
                                    <?php foreach ($dashboardData['analytics_summary'] as $analytics): ?>
                                    <div class="p-3 border-bottom">
                                        <h6 class="mb-2"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $analytics['sensor_type']))); ?></h6>
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <small class="text-muted">Uptime</small>
                                                <div class="fw-bold"><?php echo round($analytics['avg_uptime'], 1); ?>%</div>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Quality</small>
                                                <div class="fw-bold"><?php echo round($analytics['avg_quality'], 1); ?>%</div>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Anomalies</small>
                                                <div class="fw-bold"><?php echo $analytics['total_anomalies']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-chart-bar fa-2x mb-3"></i>
                                        <p>No analytics data available.</p>
                                        <button class="btn btn-info" onclick="document.querySelector('form[action=\"?action=process_analytics\"]').submit()">
                                            <i class="fas fa-chart-line"></i> Process Analytics
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Schedule -->
                <div class="row">
                    <div class="col-12">
                        <div class="sensor-card">
                            <div class="sensor-header">
                                <h5 class="card-title mb-0">Upcoming Maintenance</h5>
                            </div>
                            <div class="sensor-body">
                                <?php if (!empty($dashboardData['maintenance_schedule'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Sensor</th>
                                                    <th>Type</th>
                                                    <th>Line</th>
                                                    <th>Maintenance Type</th>
                                                    <th>Scheduled Date</th>
                                                    <th>Days Until</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dashboardData['maintenance_schedule'] as $maintenance): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($maintenance['sensor_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($maintenance['sensor_type']); ?></td>
                                                    <td><?php echo htmlspecialchars($maintenance['line_shift']); ?></td>
                                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $maintenance['maintenance_type']))); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($maintenance['scheduled_date'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($maintenance['days_until_maintenance'] <= 7) ? 'warning' : 'info'; ?>">
                                                            <?php echo $maintenance['days_until_maintenance']; ?> days
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($maintenance['status'] === 'scheduled') ? 'secondary' : (($maintenance['status'] === 'in_progress') ? 'primary' : 'success'); ?>">
                                                            <?php echo ucfirst($maintenance['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-tools fa-2x mb-3"></i>
                                        <p>No scheduled maintenance.</p>
                                        <small>All sensors are up to date with maintenance schedules.</small>
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
        // Auto-refresh real-time data every 30 seconds
        let refreshInterval = setInterval(function() {
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

        // Animate sensor cards on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sensorMetrics = document.querySelectorAll('.sensor-metric');
            sensorMetrics.forEach((metric, index) => {
                metric.style.opacity = '0';
                metric.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    metric.style.transition = 'all 0.5s ease';
                    metric.style.opacity = '1';
                    metric.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add real-time indicator pulse animation
            const indicators = document.querySelectorAll('.real-time-indicator');
            indicators.forEach(indicator => {
                indicator.style.animation = 'pulse 2s infinite';
            });
        });

        // Clear refresh interval when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(refreshInterval);
            } else {
                refreshInterval = setInterval(function() {
                    window.location.reload();
                }, 30000);
            }
        });
    </script>
</body>
</html>