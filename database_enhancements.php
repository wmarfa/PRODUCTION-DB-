<?php
// database_enhancements.php - Enhanced database schema for systematic production management

require_once "config_simple.php";

$database = Database::getInstance();
$db = $database->getConnection();

try {
    // Enable error reporting for debugging
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h3>🚀 Enhancing Database Schema for Systematic Production Management</h3>";

    // 1. Production Alerts Table
    echo "<h5>📊 Creating production_alerts table...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS production_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        alert_type ENUM('info', 'warning', 'critical') NOT NULL,
        alert_category ENUM('performance', 'quality', 'equipment', 'manpower', 'schedule') NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        production_line VARCHAR(50),
        shift VARCHAR(20),
        alert_threshold DECIMAL(10,2),
        current_value DECIMAL(10,2),
        severity_score INT DEFAULT 1,
        status ENUM('active', 'acknowledged', 'resolved', 'escalated') DEFAULT 'active',
        acknowledged_by VARCHAR(100),
        acknowledged_at TIMESTAMP NULL,
        resolved_by VARCHAR(100),
        resolved_at TIMESTAMP NULL,
        resolution_notes TEXT,
        auto_escalate BOOLEAN DEFAULT TRUE,
        escalation_level INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_alert_status (status),
        INDEX idx_production_line (production_line),
        INDEX idx_alert_type (alert_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ production_alerts table created successfully<br>";

    // 2. Quality Checkpoints Table
    echo "<h5>🔍 Creating quality_checkpoints table...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS quality_checkpoints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        checkpoint_name VARCHAR(255) NOT NULL,
        production_line VARCHAR(50),
        checkpoint_type ENUM('incoming', 'in_process', 'final', 'outgoing') NOT NULL,
        quality_standard VARCHAR(255),
        tolerance_level DECIMAL(5,2),
        measurement_unit VARCHAR(50),
        frequency ENUM('hourly', 'shift', 'daily', 'batch') DEFAULT 'shift',
        target_value DECIMAL(10,2),
        min_acceptable DECIMAL(10,2),
        max_acceptable DECIMAL(10,2),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_production_line (production_line),
        INDEX idx_checkpoint_type (checkpoint_type),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ quality_checkpoints table created successfully<br>";

    // 3. Quality Measurements Table
    echo "<h5>📈 Creating quality_measurements table...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS quality_measurements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        checkpoint_id INT,
        daily_performance_id INT,
        measurement_value DECIMAL(10,4) NOT NULL,
        measurement_status ENUM('pass', 'fail', 'warning') NOT NULL,
        defect_count INT DEFAULT 0,
        defect_type VARCHAR(255),
        corrective_action TEXT,
        operator VARCHAR(100),
        inspector VARCHAR(100),
        measurement_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        batch_number VARCHAR(100),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (checkpoint_id) REFERENCES quality_checkpoints(id) ON DELETE CASCADE,
        FOREIGN KEY (daily_performance_id) REFERENCES daily_performance(id) ON DELETE CASCADE,
        INDEX idx_checkpoint_id (checkpoint_id),
        INDEX idx_daily_performance_id (daily_performance_id),
        INDEX idx_measurement_status (measurement_status),
        INDEX idx_measurement_time (measurement_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ quality_measurements table created successfully<br>";

    // 4. Maintenance Schedules Table
    echo "<h5>🔧 Creating maintenance_schedules table...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS maintenance_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        equipment_name VARCHAR(255) NOT NULL,
        equipment_id VARCHAR(100),
        production_line VARCHAR(50),
        maintenance_type ENUM('preventive', 'corrective', 'predictive', 'emergency') NOT NULL,
        frequency_type ENUM('hours', 'days', 'weeks', 'months', 'cycles', 'usage_based') NOT NULL,
        frequency_interval INT NOT NULL,
        last_maintenance DATE,
        next_maintenance DATE,
        estimated_duration_hours DECIMAL(5,2),
        priority_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
        maintenance_cost DECIMAL(10,2),
        spare_parts_required TEXT,
        maintenance_procedure TEXT,
        assigned_technician VARCHAR(100),
        status ENUM('scheduled', 'in_progress', 'completed', 'overdue', 'cancelled') DEFAULT 'scheduled',
        completion_notes TEXT,
        actual_duration_hours DECIMAL(5,2),
        actual_cost DECIMAL(10,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_equipment_id (equipment_id),
        INDEX idx_production_line (production_line),
        INDEX idx_next_maintenance (next_maintenance),
        INDEX idx_status (status),
        INDEX idx_priority_level (priority_level)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ maintenance_schedules table created successfully<br>";

    // 5. Production Bottlenecks Table
    echo "<h5>⚠️ Creating production_bottlenecks table...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS production_bottlenecks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bottleneck_type ENUM('equipment', 'manpower', 'material', 'quality', 'schedule', 'process') NOT NULL,
        affected_production_line VARCHAR(50),
        bottleneck_description TEXT,
        impact_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
        production_loss_units INT,
        production_loss_hours DECIMAL(5,2),
        cost_impact DECIMAL(10,2),
        root_cause_analysis TEXT,
        resolution_actions TEXT,
        prevention_measures TEXT,
        detected_date DATE,
        resolved_date DATE,
        resolution_status ENUM('pending', 'in_progress', 'resolved', 'monitored') DEFAULT 'pending',
        reported_by VARCHAR(100),
        resolved_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_production_line (affected_production_line),
        INDEX idx_bottleneck_type (bottleneck_type),
        INDEX idx_impact_level (impact_level),
        INDEX idx_resolution_status (resolution_status),
        INDEX idx_detected_date (detected_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ production_bottlenecks table created successfully<br>";

    // 6. Shift Handovers Table
    echo "<h5>🔄 Creating shift_handovers table...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS shift_handovers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shift_date DATE NOT NULL,
        from_shift VARCHAR(20) NOT NULL,
        to_shift VARCHAR(20) NOT NULL,
        from_supervisor VARCHAR(100) NOT NULL,
        to_supervisor VARCHAR(100) NOT NULL,
        production_line VARCHAR(50),
        handover_status ENUM('pending', 'in_progress', 'completed', 'delayed') DEFAULT 'pending',

        plan_achievement INT,
        actual_achievement INT,
        plan_completion_rate DECIMAL(5,2),
        quality_issues_count INT,
        equipment_downtime_minutes INT,

        ongoing_issues TEXT,
        priority_tasks TEXT,
        material_status TEXT,
        equipment_status TEXT,
        safety_incidents TEXT,
        special_instructions TEXT,

        quality_concerns TEXT,
        maintenance_activities TEXT,

        manning_level INT DEFAULT 0,
        absenteeism_count INT DEFAULT 0,
        new_assignees TEXT,

        handover_start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        handover_completion_time TIMESTAMP NULL,

        from_supervisor_acknowledged BOOLEAN DEFAULT FALSE,
        to_supervisor_acknowledged BOOLEAN DEFAULT FALSE,

        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_shift_date (shift_date),
        INDEX idx_production_line (production_line),
        INDEX idx_from_shift (from_shift),
        INDEX idx_to_shift (to_shift),
        INDEX idx_handover_status (handover_status),
        UNIQUE KEY unique_shift_handover (shift_date, production_line, from_shift, to_shift)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ shift_handovers table created successfully<br>";

    // 7. Production Forecasts Table
    echo "<h5>📅 Creating production_forecasts table...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS production_forecasts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        forecast_date DATE NOT NULL,
        production_line VARCHAR(50),
        shift VARCHAR(20),
        forecast_type ENUM('daily', 'weekly', 'monthly', 'quarterly') NOT NULL,

        demand_units INT NOT NULL,
        demand_confidence ENUM('low', 'medium', 'high') DEFAULT 'medium',
        demand_source VARCHAR(255),

        capacity_units INT,
        capacity_utilization DECIMAL(5,2),
        efficiency_forecast DECIMAL(5,2),

        manpower_required INT,
        overtime_hours_forecast DECIMAL(5,2),
        material_requirements TEXT,

        constraints TEXT,
        assumptions TEXT,
        risk_factors TEXT,

        target_completion_rate DECIMAL(5,2),
        target_quality_rate DECIMAL(5,2),
        target_efficiency DECIMAL(5,2),

        forecast_model VARCHAR(255),
        accuracy_history DECIMAL(5,2),
        created_by VARCHAR(100),
        approved_by VARCHAR(100),
        approved_at TIMESTAMP NULL,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_forecast_date (forecast_date),
        INDEX idx_production_line (production_line),
        INDEX idx_forecast_type (forecast_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ production_forecasts table created successfully<br>";

    // 8. Equipment Utilization Table
    echo "<h5>⚙️ Creating equipment_utilization table...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS equipment_utilization (
        id INT AUTO_INCREMENT PRIMARY KEY,
        equipment_name VARCHAR(255) NOT NULL,
        equipment_id VARCHAR(100),
        production_line VARCHAR(50),
        utilization_date DATE NOT NULL,
        shift VARCHAR(20),

        scheduled_minutes INT DEFAULT 0,
        running_minutes INT DEFAULT 0,
        idle_minutes INT DEFAULT 0,
        maintenance_minutes INT DEFAULT 0,
        changeover_minutes INT DEFAULT 0,
        breakdown_minutes INT DEFAULT 0,

        units_produced INT DEFAULT 0,
        target_units INT DEFAULT 0,
        cycle_time_actual DECIMAL(8,2),
        cycle_time_standard DECIMAL(8,2),

        oee_percentage DECIMAL(5,2),
        availability_percentage DECIMAL(5,2),
        performance_percentage DECIMAL(5,2),
        quality_percentage DECIMAL(5,2),

        operator_name VARCHAR(100),
        setup_time_minutes INT DEFAULT 0,
        no_load_time_minutes INT DEFAULT 0,
        minor_stoppages_minutes INT DEFAULT 0,

        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        INDEX idx_equipment_id (equipment_id),
        INDEX idx_production_line (production_line),
        INDEX idx_utilization_date (utilization_date),
        INDEX idx_shift (shift),
        UNIQUE KEY unique_equipment_utilization (equipment_id, utilization_date, shift)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ equipment_utilization table created successfully<br>";

    // 9. Supplier Quality Table
    echo "<h5>🏭 Creating supplier_quality table...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS supplier_quality (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_name VARCHAR(255) NOT NULL,
        supplier_code VARCHAR(100),
        material_type VARCHAR(255),
        product_id INT,

        inspection_date DATE,
        batch_number VARCHAR(100),
        quantity_inspected INT DEFAULT 0,
        defects_found INT DEFAULT 0,
        defect_rate DECIMAL(5,2),

        quality_rating ENUM('excellent', 'good', 'acceptable', 'marginal', 'unacceptable') NOT NULL,
        conformance_rating DECIMAL(5,2),
        on_time_delivery_rating DECIMAL(5,2),

        defect_types TEXT,
        corrective_actions_required TEXT,
        return_to_supplier BOOLEAN DEFAULT FALSE,

        cumulative_quality_score DECIMAL(5,2),
        total_inspections INT DEFAULT 0,
        total_defects INT DEFAULT 0,

        inspector_name VARCHAR(100),
        certification_required BOOLEAN DEFAULT FALSE,
        certification_expiry DATE,

        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
        INDEX idx_supplier_name (supplier_name),
        INDEX idx_supplier_code (supplier_code),
        INDEX idx_material_type (material_type),
        INDEX idx_inspection_date (inspection_date),
        INDEX idx_quality_rating (quality_rating)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ supplier_quality table created successfully<br>";

    // 10. Enhanced User Management Table
    echo "<h5>👥 Creating users table for role-based access control...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        employee_id VARCHAR(100),

        role ENUM('operator', 'supervisor', 'manager', 'executive', 'admin') NOT NULL DEFAULT 'operator',
        department VARCHAR(100),
        production_lines TEXT,

        is_active BOOLEAN DEFAULT TRUE,
        last_login TIMESTAMP NULL,
        password_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        failed_login_attempts INT DEFAULT 0,
        account_locked_until TIMESTAMP NULL,

        two_factor_enabled BOOLEAN DEFAULT FALSE,
        two_factor_secret VARCHAR(255),

        timezone VARCHAR(50) DEFAULT 'UTC',
        date_format VARCHAR(20) DEFAULT 'Y-m-d',
        time_format VARCHAR(10) DEFAULT 'H:i:s',
        language VARCHAR(10) DEFAULT 'en',

        phone VARCHAR(50),
        alternate_email VARCHAR(255),

        emergency_contact_name VARCHAR(255),
        emergency_contact_phone VARCHAR(50),

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_role (role),
        INDEX idx_is_active (is_active),
        INDEX idx_employee_id (employee_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ users table created successfully<br>";

    // 11. User Activity Log Table
    echo "<h5>📝 Creating user_activity_log table...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS user_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        activity_type VARCHAR(100) NOT NULL,
        activity_description TEXT,
        activity_category ENUM('data_entry', 'view', 'modify', 'delete', 'login', 'logout', 'system') NOT NULL,

        table_name VARCHAR(100),
        record_id INT,
        old_values JSON,
        new_values JSON,

        ip_address VARCHAR(45),
        user_agent TEXT,
        session_id VARCHAR(255),

        activity_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_activity_type (activity_type),
        INDEX idx_activity_category (activity_category),
        INDEX idx_activity_timestamp (activity_timestamp),
        INDEX idx_table_record (table_name, record_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ user_activity_log table created successfully<br>";

    // 12. KPI Definitions Table
    echo "<h5>📊 Creating kpi_definitions table...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS kpi_definitions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kpi_name VARCHAR(255) NOT NULL,
        kpi_code VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        category ENUM('production', 'quality', 'efficiency', 'safety', 'cost', 'maintenance') NOT NULL,

        unit_of_measure VARCHAR(50),
        target_value DECIMAL(10,2),
        minimum_acceptable DECIMAL(10,2),
        maximum_acceptable DECIMAL(10,2),

        formula TEXT,
        data_source VARCHAR(255),
        calculation_frequency ENUM('real_time', 'hourly', 'shift', 'daily', 'weekly') DEFAULT 'daily',

        display_format ENUM('number', 'percentage', 'currency', 'time') DEFAULT 'number',
        decimal_places INT DEFAULT 2,
        trend_direction ENUM('up', 'down', 'neutral') DEFAULT 'up',

        is_active BOOLEAN DEFAULT TRUE,
        department_responsible VARCHAR(100),
        owner_role ENUM('operator', 'supervisor', 'manager', 'executive') DEFAULT 'supervisor',

        alert_threshold_low DECIMAL(10,2),
        alert_threshold_high DECIMAL(10,2),
        alert_enabled BOOLEAN DEFAULT TRUE,

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_kpi_code (kpi_code),
        INDEX idx_category (category),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ kpi_definitions table created successfully<br>";

    // 13. KPI History Table
    echo "<h5>📈 Creating kpi_history table...</h5>";
    $db->exec("CREATE TABLE IF NOT EXISTS kpi_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kpi_id INT,
        measurement_date DATE NOT NULL,
        measurement_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        production_line VARCHAR(50),
        shift VARCHAR(20),

        actual_value DECIMAL(10,4) NOT NULL,
        target_value DECIMAL(10,2),
        variance_value DECIMAL(10,4),
        variance_percentage DECIMAL(5,2),

        performance_rating ENUM('excellent', 'good', 'acceptable', 'poor', 'critical') NOT NULL,
        trend_direction ENUM('improving', 'stable', 'declining') DEFAULT 'stable',

        notes TEXT,
        recorded_by VARCHAR(100),

        FOREIGN KEY (kpi_id) REFERENCES kpi_definitions(id) ON DELETE CASCADE,
        INDEX idx_kpi_id (kpi_id),
        INDEX idx_measurement_date (measurement_date),
        INDEX idx_production_line (production_line),
        INDEX idx_performance_rating (performance_rating),
        UNIQUE KEY unique_kpi_measurement (kpi_id, measurement_date, production_line, shift)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ kpi_history table created successfully<br>";

    echo "<div class='alert alert-success mt-4'>";
    echo "<h4>✅ Database Enhancement Complete!</h4>";
    echo "<p>All 13 enhanced tables have been successfully created for systematic production management:</p>";
    echo "<ul class='mb-0'>";
    echo "<li>📊 Production Alerts - Real-time alert management</li>";
    echo "<li>🔍 Quality Control - Quality checkpoints and measurements</li>";
    echo "<li>🔧 Maintenance Management - Preventive and corrective maintenance</li>";
    echo "<li>⚠️ Bottleneck Tracking - Production constraint identification</li>";
    echo "<li>🔄 Shift Handovers - Smooth 24/7 shift transitions</li>";
    echo "<li>📅 Production Forecasts - Demand and capacity planning</li>";
    echo "<li>⚙️ Equipment Utilization - OEE and performance tracking</li>";
    echo "<li>🏭 Supplier Quality - Vendor performance management</li>";
    echo "<li>👥 User Management - Role-based access control</li>";
    echo "<li>📝 Activity Logging - Comprehensive audit trail</li>";
    echo "<li>📈 KPI Tracking - Performance monitoring system</li>";
    echo "</ul>";
    echo "</div>";

    // Insert default admin user and basic data
    echo "<h5>🔧 Setting up default system data...</h5>";

    // Insert default admin user (password: admin123)
    $default_admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $db->exec("INSERT IGNORE INTO users (username, email, password_hash, full_name, role, department)
               VALUES ('admin', 'admin@production.com', '$default_admin_password', 'System Administrator', 'admin', 'IT')");
    echo "✅ Default admin user created (username: admin, password: admin123)<br>";

    // Insert basic KPI definitions
    $db->exec("INSERT IGNORE INTO kpi_definitions (kpi_code, kpi_name, description, category, unit_of_measure, target_value, display_format, trend_direction) VALUES
        ('OEE', 'Overall Equipment Effectiveness', 'Composite measure of equipment productivity', 'production', 'percentage', 85.00, 'percentage', 'up'),
        ('FIRST_PASS_YIELD', 'First Pass Yield', 'Percentage of products that pass quality inspection on first attempt', 'quality', 'percentage', 98.00, 'percentage', 'up'),
        ('PLAN_ADHERENCE', 'Plan Adherence', 'Percentage of production plan completed as scheduled', 'production', 'percentage', 95.00, 'percentage', 'up'),
        ('CYCLE_TIME', 'Cycle Time', 'Time taken to complete one production cycle', 'efficiency', 'minutes', 45.00, 'number', 'down'),
        ('DOWNTIME', 'Equipment Downtime', 'Total time equipment is not available for production', 'maintenance', 'minutes', 60.00, 'number', 'down'),
        ('SAFETY_INCIDENTS', 'Safety Incidents', 'Number of safety incidents reported', 'safety', 'count', 0.00, 'number', 'down')
    ");
    echo "✅ Basic KPI definitions inserted<br>";

    // Insert sample quality checkpoints
    $db->exec("INSERT IGNORE INTO quality_checkpoints (checkpoint_name, production_line, checkpoint_type, quality_standard, target_value, tolerance_level, measurement_unit, frequency) VALUES
        ('Visual Inspection - Assembly', 'ALL', 'in_process', 'No visual defects', 100, 2.00, 'percentage', 'hourly'),
        ('Dimension Check - Critical', 'ALL', 'in_process', 'Within tolerance ±0.1mm', 100, 0.10, 'mm', 'batch'),
        ('Functional Test', 'ALL', 'final', '100% functional', 100, 0.00, 'percentage', 'shift'),
        ('Final Packaging Inspection', 'ALL', 'outgoing', 'Package integrity', 100, 1.00, 'percentage', 'hourly')
    ");
    echo "✅ Sample quality checkpoints created<br>";

    echo "<div class='alert alert-info mt-3'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. Update config.php with new system settings<br>";
    echo "2. Create enhanced dashboard with real-time monitoring<br>";
    echo "3. Implement intelligent alert system<br>";
    echo "4. Build analytics engine for bottleneck detection<br>";
    echo "</div>";

} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ Database Enhancement Failed</h4>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Please check your database connection and permissions.";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Enhancement - Systematic Production Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .enhancement-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }
        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            background: #3498db;
            color: white;
        }
        .success-check {
            color: #27ae60;
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="enhancement-header text-center">
            <h1><i class="fas fa-database me-3"></i>Production Management System Enhancement</h1>
            <p class="lead mb-0">Systematic Database Schema Implementation</p>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-cogs me-2"></i>Enhanced Production Management Features</h4>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="feature-icon">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <h5>Real-Time Alerts</h5>
                                <p class="text-muted">Intelligent monitoring with configurable thresholds and automatic escalation</p>
                            </div>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="feature-icon" style="background: #e74c3c;">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <h5>Quality Control</h5>
                                <p class="text-muted">Comprehensive quality checkpoints and measurement tracking</p>
                            </div>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="feature-icon" style="background: #f39c12;">
                                    <i class="fas fa-tools"></i>
                                </div>
                                <h5>Maintenance</h5>
                                <p class="text-muted">Preventive maintenance scheduling and downtime tracking</p>
                            </div>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="feature-icon" style="background: #27ae60;">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <h5>Shift Management</h5>
                                <p class="text-muted">24/7 shift handovers and production continuity</p>
                            </div>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="feature-icon" style="background: #9b59b6;">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h5>Analytics</h5>
                                <p class="text-muted">Bottleneck detection and performance optimization</p>
                            </div>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="feature-icon" style="background: #34495e;">
                                    <i class="fas fa-users-cog"></i>
                                </div>
                                <h5>User Management</h5>
                                <p class="text-muted">Role-based access control and activity logging</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="Index.php" class="btn btn-primary btn-lg me-3">
                <i class="fas fa-home me-2"></i>Return to Dashboard
            </a>
            <a href="enhanced_dashboard.php" class="btn btn-success btn-lg">
                <i class="fas fa-tachometer-alt me-2"></i>View Enhanced Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>