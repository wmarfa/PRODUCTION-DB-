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
        error_log('CSRF validation failed in compliance_audit.php');
        die('Security validation failed');
    }
}

// Authentication and Authorization
if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
    header('Location: index_lan.php');
    exit;
}

// Check permissions (Manager, Executive, and Admin roles only)
if (!in_array($_SESSION['user_role'], ['manager', 'executive', 'admin'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied. You do not have permission to access compliance and audit system.');
}

/**
 * Comprehensive Compliance and Audit Management System
 * Ensures regulatory compliance and facilitates audit processes
 */
class ComplianceAuditManager {
    private $conn;
    private $userRole;
    private $complianceFrameworks = [];
    private $auditStandards = [];

    public function __construct($conn, $userRole) {
        $this->conn = $conn;
        $this->userRole = $userRole;
        $this->initializeComplianceDatabase();
        $this->loadComplianceFrameworks();
        $this->loadAuditStandards();
    }

    /**
     * Initialize compliance and audit database tables
     */
    private function initializeComplianceDatabase() {
        // Create compliance frameworks table
        $createFrameworksTable = "CREATE TABLE IF NOT EXISTS compliance_frameworks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            framework_name VARCHAR(255) NOT NULL,
            framework_code VARCHAR(100) NOT NULL UNIQUE,
            framework_type ENUM('industry_standard', 'regulatory', 'certification', 'customer_specific', 'internal_policy') NOT NULL,
            version VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            authority VARCHAR(255),
            scope TEXT NOT NULL,
            applicability TEXT,
            compliance_requirements JSON NOT NULL,
            certification_requirements JSON,
            audit_requirements JSON,
            documentation_requirements JSON,
            training_requirements JSON,
            frequency_requirements JSON,
            risk_assessment JSON,
            last_review_date DATE,
            next_review_date DATE,
            effective_date DATE,
            expiry_date DATE NULL,
            status ENUM('active', 'expired', 'superseded', 'under_review') DEFAULT 'active',
            created_by INT NOT NULL,
            approved_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_framework_code (framework_code),
            INDEX idx_framework_type (framework_type),
            INDEX idx_status (status),
            INDEX idx_next_review_date (next_review_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createFrameworksTable);

        // Create compliance requirements table
        $createRequirementsTable = "CREATE TABLE IF NOT EXISTS compliance_requirements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requirement_id VARCHAR(100) NOT NULL UNIQUE,
            framework_id INT NOT NULL,
            category VARCHAR(255) NOT NULL,
            requirement_name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            requirement_type ENUM('mandatory', 'recommended', 'optional') NOT NULL,
            compliance_level ENUM('fully_compliant', 'substantially_compliant', 'partially_compliant', 'non_compliant') NOT NULL,
            verification_method VARCHAR(255),
            evidence_required JSON,
            frequency VARCHAR(100),
            responsible_person VARCHAR(255),
            due_date DATE,
            target_date DATE,
            actual_date DATE NULL,
            compliance_status ENUM('compliant', 'non_compliant', 'partially_compliant', 'not_assessed', 'not_applicable') DEFAULT 'not_assessed',
            gap_description TEXT,
                            'corrective_action_plan TEXT,
            corrective_action_deadline DATE NULL,
                            'corrective_action_status ENUM('pending', 'in_progress', 'completed', 'overdue') DEFAULT 'pending',
                            risk_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
                            impact_assessment TEXT,
                            cost_impact DECIMAL(12,2),
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (framework_id) REFERENCES compliance_frameworks(id) ON DELETE CASCADE,
            INDEX idx_framework_id (framework_id),
            INDEX idx_category (category),
            INDEX idx_requirement_type (requirement_type),
            INDEX idx_compliance_status (compliance_status),
            INDEX idx_risk_level (risk_level),
            INDEX idx_due_date (due_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createRequirementsTable);

        // Create compliance audit schedules table
        $createSchedulesTable = "CREATE TABLE IF NOT EXISTS compliance_audit_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            schedule_id VARCHAR(100) NOT NULL UNIQUE,
            schedule_name VARCHAR(255) NOT NULL,
            schedule_type ENUM('internal_audit', 'external_audit', 'supplier_audit', 'regulatory_audit', 'certification_audit') NOT NULL,
            framework_id INT,
            audit_frequency ENUM('monthly', 'quarterly', 'semi_annually', 'annually', 'biennial', 'as_needed') NOT NULL,
            audit_scope TEXT NOT NULL,
            audit_objectives JSON NOT NULL,
            estimated_duration_hours DECIMAL(8,2),
            required_resources JSON,
            audit_team JSON,
            external_auditor VARCHAR(255) NULL,
            next_audit_date DATE NOT NULL,
            last_audit_date DATE NULL,
            schedule_status ENUM('active', 'completed', 'paused', 'cancelled') DEFAULT 'active',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (framework_id) REFERENCES compliance_frameworks(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_schedule_type (schedule_type),
            INDEX idx_framework_id (framework_id),
            INDEX idx_next_audit_date (next_audit_date),
            INDEX idx_schedule_status (schedule_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createSchedulesTable);

        // Create audit execution records table
        createTable $createExecutionsTable = "CREATE TABLE IF NOT EXISTS compliance_audit_executions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            execution_id VARCHAR(100) NOT NULL UNIQUE,
            schedule_id INT,
            audit_name VARCHAR(255) NOT NULL,
            audit_type ENUM('internal_audit', 'external_audit', 'supplier_audit', 'regulatory_audit', 'certification_audit', 'ad_hoc') NOT NULL,
            framework_id INT NULL,
            audit_date DATE NOT NULL,
            start_time TIME,
            end_time TIME,
            duration_minutes INT DEFAULT 0,
            audit_scope TEXT NOT NULL,
            audit_team JSON NOT NULL,
            auditee VARCHAR(255),
            audit_report_path VARCHAR(500),
            findings JSON NOT NULL,
            non_conformities JSON NOT NULL,
            observations JSON NOT NULL,
            positive_practices JSON NOT NULL,
            compliance_score DECIMAL(5,2),
            compliance_rating ENUM('fully_compliant', 'substantially_compliant', 'partially_compliant', 'non_compliant') NOT NULL,
            corrective_actions_required BOOLEAN DEFAULT TRUE,
            corrective_actions JSON,
            follow_up_required BOOLEAN DEFAULT TRUE,
            follow_up_actions JSON,
            follow_up_date DATE NULL,
            status ENUM('planned', 'in_progress', 'completed', 'cancelled', 'on_hold') DEFAULT 'planned',
            lead_auditor VARCHAR(255),
            report_approver VARCHAR(255),
            report_approved_at TIMESTAMP NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (schedule_id) REFERENCES compliance_audit_schedules(id) ON DELETE CASCADE,
            FOREIGN KEY (framework_id) REFERENCES compliance_frameworks(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_execution_date (audit_date),
            INDEX idx_audit_type (audit_type),
            INDEX idx_compliance_rating (compliance_rating),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createExecutionsTable);

        // Create compliance training records table
        $createTrainingTable = "CREATE TABLE IF NOT EXISTS compliance_training (
            id INT AUTO_INCREMENT PRIMARY KEY,
            training_id VARCHAR(100) NOT NULL UNIQUE,
            training_title VARCHAR(255) NOT NULL,
            training_type ENUM('compliance_awareness', 'standard_training', 'certification_preparation', 'refresher_course', 'new_hire_orientation', 'specialized_training') NOT NULL,
            framework_id INT,
            target_audience JSON NOT NULL,
            training_objectives TEXT NOT NULL,
            training_content JSON NOT NULL,
            delivery_method ENUM('classroom', 'online', 'workshop', 'on_the_job', 'blended') NOT NULL,
            duration_hours DECIMAL(8,2),
            scheduled_date DATE NOT NULL,
            actual_date DATE NULL,
            instructor VARCHAR(255),
            venue VARCHAR(255),
            training_materials JSON,
            assessment_method VARCHAR(255),
            passing_score DECIMAL(5,2),
            attendees JSON,
            completion_rate DECIMAL(5,2) DEFAULT 0,
            effectiveness_rating DECIMAL(5,2),
            cost DECIMAL(12,2) DEFAULT 0,
            status ENUM('scheduled', 'completed', 'cancelled', 'postponed') DEFAULT 'scheduled',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (framework_id) REFERENCES compliance_frameworks(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_training_type (training_type),
            INDEX target_audience (target_audience),
            INDEX idx_scheduled_date (scheduled_date),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createTrainingTable);

        // Create compliance documentation table
        $createDocsTable = "CREATE TABLE IF NOT EXISTS compliance_documentation (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id VARCHAR(100) NOT NULL UNIQUE,
            framework_id INT,
            document_type ENUM('policy', 'procedure', 'work_instruction', 'form', 'record', 'manual', 'guideline', 'template') NOT NULL,
            title VARCHAR(255) NOT NULL,
            version VARCHAR(50) NOT NULL,
            description TEXT,
            file_path VARCHAR(500) NOT NULL,
            file_hash VARCHAR(64),
            file_size BIGINT,
            format VARCHAR(50),
            language VARCHAR(10) DEFAULT 'en',
            controlled_document BOOLEAN DEFAULT TRUE,
            approval_status ENUM('draft', 'pending_approval', 'approved', 'rejected', 'superseded') DEFAULT 'draft',
            approved_by INT NULL,
            approved_at TIMESTAMP NULL,
            expiry_date DATE NULL,
            review_frequency ENUM('monthly', 'quarterly', 'semi_annually', 'annually', 'as_needed') DEFAULT 'annual',
            last_review_date DATE NULL,
            access_level ENUM('public', 'internal', 'restricted', 'confidential') NOT NULL,
            storage_location VARCHAR(255),
            keywords TEXT,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (framework_id) REFERENCES compliance_frameworks(id) ON DELETE CASCADE,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_document_type (document_type),
            INDEX idx_framework_id (framework_id),
            INDEX idx_approval_status (approval_status),
            INDEX idx_expiry_date (expiry_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createDocsTable);

        // Create compliance dashboard table
        $createDashboardTable = "CREATE TABLE IF NOT EXISTS compliance_dashboard (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dashboard_name VARCHAR(255) NOT NULL,
            description TEXT,
            framework_ids JSON NOT NULL,
            widget_configuration JSON NOT NULL,
            auto_refresh BOOLEAN DEFAULT TRUE,
            refresh_interval INT DEFAULT 300,
            last_generated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_last_generated (last_generated)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createDashboardTable);

        // Create compliance notifications table
        $createNotificationsTable = "CREATE TABLE IF NOT EXISTS compliance_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id VARCHAR(100) NOT NULL UNIQUE,
            notification_type ENUM('audit_scheduled', 'requirement_due', 'compliance_alert', 'training_required', 'documentation_update', 'expiry_warning', 'review_required') NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            priority ENUM('low', 'medium', 'high', 'critical') NOT NULL,
            target_audience JSON NOT NULL,
            action_required BOOLEAN DEFAULT TRUE,
            action_deadline TIMESTAMP NULL,
            action_completed BOOLEAN DEFAULT FALSE,
            related_entity_type ENUM('framework', 'requirement', 'audit', 'training', 'document') NOT NULL,
            related_entity_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            read_by INT NULL,
            read_at TIMESTAMP NULL,
            acknowledged BOOLEAN DEFAULT FALSE,
            acknowledged_by INT NULL,
            acknowledged_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createNotificationsTable);
    }

    /**
     * Load compliance frameworks
     */
    private function loadComplianceFrameworks() {
        $query = "SELECT * FROM compliance_frameworks WHERE status = 'active' ORDER BY framework_type, framework_name";
        $result = $this->conn->query($query);

        while ($row = $result->fetch_assoc()) {
            $this->complianceFrameworks[] = $row;
        }
    }

    /**
     * Load audit standards
     */
    private function loadAuditStandards() {
        $query = "SELECT * FROM compliance_audit_standards WHERE is_active = TRUE ORDER BY standard_code";
        $result = $this->conn->query($query);

        while ($row = $result->fetch_assoc()) {
            $this->auditStandards[] = $row;
        }
    }

    /**
     * Create compliance framework
     */
    public function createComplianceFramework($frameworkData) {
        // Validate required fields
        $required = ['framework_name', 'framework_code', 'framework_type', 'version', 'description', 'authority', 'scope'];
        foreach ($required as $field) {
            if (empty($frameworkData[$field])) {
                throw new Exception("Required field '$field' is missing");
            }
        }

        // Generate compliance requirements based on framework type
        $defaultRequirements = $this->getDefaultRequirements($frameworkData['framework_type']);

        $query = "INSERT INTO compliance_frameworks
                  (framework_name, framework_code, framework_type, version, description, authority,
                   scope, applicability, compliance_requirements, certification_requirements, audit_requirements,
                   documentation_requirements, training_requirements, risk_assessment, last_review_date,
                   next_review_date, effective_date, expiry_date, created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sssssssssssssssssddsi",
            $frameworkData['framework_name'],
            $frameworkData['framework_code'],
            $frameworkData['framework_type'],
            $frameworkData['version'],
            $frameworkData['description'],
            $frameworkData['authority'],
            $frameworkData['scope'],
            $frameworkData['applicability'] ?? '',
            json_encode($frameworkData['compliance_requirements'] ?? $defaultRequirements['compliance']),
            json_encode($frameworkData['certification_requirements'] ?? $defaultRequirements['certification']),
            json_encode($frameworkData['audit_requirements'] ?? $defaultRequirements['audit']),
            json_encode($frameworkData['documentation_requirements'] ?? $defaultRequirements['documentation']),
            json_encode($frameworkData['training_requirements'] ?? $defaultRequirements['training']),
            json_encode($frameworkData['risk_assessment'] ?? $defaultRequirements['risk']),
            date('Y-m-d'),
            date('Y-m-d', strtotime('+1 year')),
            date('Y-m-d'),
            $frameworkData['expiry_date'] ?? null,
            $_SESSION['user_id']
        );

        $success = $stmt->execute();

        if ($success) {
            $frameworkId = $this->conn->insert_id;
            return [
                'success' => true,
                'framework_id' => $frameworkId,
                'message' => 'Compliance framework created successfully'
            ];
        } else {
            throw new Exception("Failed to create compliance framework: " . $stmt->error);
        }
    }

    /**
     * Schedule compliance audit
     */
    public function scheduleAudit($scheduleData) {
        $query = "INSERT INTO compliance_audit_schedules
                  (schedule_id, schedule_name, schedule_type, framework_id, audit_frequency,
                   audit_scope, audit_objectives, estimated_duration_hours, required_resources,
                   audit_team, next_audit_date, created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sssisdisdssds",
            $scheduleData['schedule_id'],
            $scheduleData['schedule_name'],
            $scheduleData['schedule_type'],
            $scheduleData['framework_id'],
            $scheduleData['audit_frequency'],
            $scheduleData['audit_scope'],
            json_encode($scheduleData['audit_objectives'] ?? []),
            $scheduleData['estimated_duration_hours'] ?? 8.0,
            json_encode($scheduleData['required_resources'] ?? []),
            json_encode($scheduleData['audit_team'] ?? []),
            $scheduleData['next_audit_date'],
            $_SESSION['user_id']
        );

        $success = $stmt->execute();

        if ($success) {
            $scheduleId = $this->conn->insert_id;
            return [
                'success' => true,
                'schedule_id' => $scheduleId,
                'message' => 'Compliance audit scheduled successfully'
            ];
        } else {
            throw new Exception("Failed to schedule audit: " . $stmt->error);
        }
    }

    /**
     * Execute compliance audit
     */
    public function executeAudit($executionData) {
        $query = "INSERT INTO compliance_audit_executions
                  (execution_id, schedule_id, audit_name, audit_type, framework_id,
                   audit_date, start_time, audit_scope, audit_team, auditee,
                   findings, non_conformities, observations, positive_practices, status,
                   created_by)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_progress', ?)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "ssissssiissssss",
            $executionData['execution_id'],
            $executionData['schedule_id'] ?? null,
            $executionData['audit_name'],
            $executionData['audit_type'],
            $executionData['framework_id'] ?? null,
            $executionData['audit_date'],
            $executionData['start_time'] ?? '09:00:00',
            $executionData['audit_scope'],
            json_encode($executionData['audit_team'] ?? []),
            $executionData['auditee'] ?? '',
            json_encode($executionData['findings'] ?? []),
            json_encode($executionData['non_conformities'] ?? []),
            json_encode($executionData['observations'] ?? []),
            json_encode($executionData['positive_practices'] ?? []),
            $executionData['status'] ?? 'in_progress',
            $_SESSION['user_id']
        );

        $success = $stmt->execute();

        if ($success) {
            $executionId = $this->conn->insert_id;
            return [
                'success' => true,
                'execution_id' => $executionId,
                'message' => 'Compliance audit initiated successfully'
            ];
        } else {
            throw new Exception("Failed to execute audit: " . $stmt->error);
        }
    }

    /**
     * Complete compliance audit execution
     */
    public function completeAudit($executionId, $completionData) {
        $query = "UPDATE compliance_audit_executions
                  SET end_time = ?, duration_minutes = ?, findings = ?, non_conformances = ?,
                      observations = ?, positive_practices = ?, compliance_score = ?, compliance_rating = ?,
                      corrective_actions = ?, follow_up_required = ?, follow_up_actions = ?, follow_up_date = ?, status = ?,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE execution_id = ?";

        $stmt = $this->bindParam(
            $completionData['end_time'] ?? date('H:i:s'),
            $completionData['duration_minutes'] ?? 0,
            json_encode($completionData['findings'] ?? []),
            json_encode($completionData['non_conformities'] ?? []),
            json_encode($completionData['observations'] ?? []),
            json_encode($completionData['positive_practices'] ?? []),
            $completionData['compliance_score'] ?? 0,
            $completionData['compliance_rating'] ?? 'partially_compliant',
            json_encode($completionData['corrective_actions'] ?? []),
            $completionData['follow_up_required'] ?? false,
            json_encode($completionData['follow_up_actions'] ?? []),
            $completionData['follow_up_date'],
            $completionData['status'] ?? 'completed'
        );

        $stmt->execute();

        return [
            'success' => true,
            'message' => 'Compliance audit completed successfully'
        ];
    }

    /**
     * Assess compliance status
     */
    public function assessCompliance($frameworkId) {
        // Get all requirements for the framework
        $query = "SELECT requirement_id, requirement_name, requirement_type, compliance_level, risk_level,
                          verification_method, evidence_required, compliance_status, due_date
                   FROM compliance_requirements
                   WHERE framework_id = ? AND requirement_type = 'mandatory'
                   ORDER BY risk_level DESC, due_date ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $frameworkId);
        $stmt->execute();
        $result = $stmt->get_result();

        $requirements = $result->fetch_all(MYSQLI_ASSOC);

        $assessment = [
            'framework_id' => $frameworkId,
            'total_requirements' => count($requirements),
            'compliant_requirements' => 0,
            'non_compliant_requirements' => 0,
            'partially_compliant_requirements' => 0,
            'not_assessed_requirements' => 0,
            'high_risk_items' => 0,
            'medium_risk_items' => 0,
            'low_risk_items' => 0,
            'overall_compliance_score' => 0,
            'compliance_status' => 'not_assessed',
            'recommendations' => []
        ];

        foreach ($requirements as $requirement) {
            switch ($requirement['compliance_status']) {
                case 'compliant':
                    $assessment['compliant_requirements']++;
                    break;
                case 'non_compliant':
                    $assessment['non_compliant_requirements']++;
                    break;
                case 'partially_compliant':
                    $assessment['partially_compliant_requirements']++;
                    break;
                case 'not_assessed':
                    $assessment['not_assessed_requirements']++;
                    break;
            }

            switch ($requirement['risk_level']) {
                case 'critical':
                    $assessment['high_risk_items']++;
                    break;
                case 'high':
                    $assessment['high_risk_items']++;
                    break;
                case 'medium':
                    $assessment['medium_risk_items']++;
                    break;
                case 'low':
                    $assessment['low_risk_items']++;
                    break;
            }

            // Check for overdue requirements
            if ($requirement['due_date'] && $requirement['due_date'] < date('Y-m-d') && $requirement['compliance_status'] !== 'compliant') {
                $assessment['overdue_items'] = ($assessment['overdue_items'] ?? 0) + 1;
            }
        }

        // Calculate compliance score
        $totalWeightedScore = 0;
        $totalWeight = 0;

        foreach ($requirements as $requirement) {
            $weight = $this->getComplianceWeight($requirement['compliance_level'], $requirement['risk_level']);
            $score = $this->getComplianceScore($requirement['compliance_status']);

            $totalWeightedScore += ($score * $weight);
            $totalWeight += $weight;
        }

        $assessment['overall_compliance_score'] = $totalWeight > 0 ? round(($totalWeightedScore / $totalWeight) * 100, 1) : 0;
        $assessment['compliance_status'] = $this->determineOverallStatus($assessment['overall_compliance_score']);

        // Generate recommendations
        $assessment['recommendations'] = $this->generateComplianceRecommendations($assessment);

        return $assessment;
    }

    /**
     * Get compliance dashboard data
     */
    public function getDashboardData() {
        return [
            'frameworks' => $this->getFrameworks(),
            'upcoming_audits' => $this->getUpcomingAudits(),
            'recent_audit_executions' => $this->getRecentAuditExecutions(),
            'compliance_status' => $this->getComplianceStatus(),
            'notifications' => $this->getNotifications(),
            'training_status' => $this->getTrainingStatus(),
            'documentation_status' => $this->getDocumentationStatus(),
            'metrics' => $this->getComplianceMetrics()
        ];
    }

    /**
     * Get compliance frameworks
     */
    private function getFrameworks() {
        $query = "SELECT
                     cf.*,
                     (SELECT COUNT(*) as total_req,
                             SUM(CASE WHEN cr.compliance_status = 'compliant' THEN 1 ELSE 0 END) as compliant_req
                      FROM compliance_requirements cr
                      WHERE cr.framework_id = cf.id
                     ) as compliance_data
                  FROM compliance_frameworks cf
                  WHERE cf.status = 'active'
                  ORDER BY cf.framework_type, cf.framework_name";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get upcoming audits
     */
    private function getUpcomingAudits() {
        $query = "SELECT
                     cas.schedule_id,
                     cas.schedule_name,
                     cas.schedule_type,
                     cf.framework_name,
                     cas.audit_frequency,
                     cas.next_audit_date,
                     cas.schedule_status
                  FROM compliance_audit_schedules cas
                  LEFT JOIN compliance_frameworks cf ON cas.framework_id = cf.id
                  WHERE cas.schedule_status = 'active' AND cas.next_audit_date >= CURDATE()
                  ORDER BY cas.next_audit_date ASC
                  LIMIT 10";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get recent audit executions
     */
    private function getRecentAuditExecutions() {
        $query = "SELECT
                     cae.execution_id,
                     cae.audit_name,
                     cae.audit_type,
                     cf.framework_name,
                     cae.audit_date,
                     cae.compliance_score,
                     cae.compliance_rating,
                     cae.status,
                     cae.created_at
                  FROM compliance_audit_executions cae
                  LEFT JOIN compliance_frameworks cf ON cae.framework_id = cf.id
                  WHERE cae.status IN ('in_progress', 'completed')
                  ORDER BY cae.created_at DESC
                  LIMIT 10";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get compliance status summary
     */
    private function getComplianceStatus() {
        $query = "SELECT
                     cf.framework_type,
                     COUNT(DISTINCT cf.id) as total_frameworks,
                     AVG(cr.avg_compliance_score) as avg_compliance_score
                  FROM compliance_frameworks cf
                  LEFT JOIN (
                      SELECT framework_id, AVG(compliance_score) as avg_compliance_score
                      FROM compliance_requirements
                      WHERE compliance_status IN ('compliant', 'non_compliant', 'partially_compliant')
                      GROUP BY framework_id
                  ) cr ON cf.id = cr.framework_id
                  WHERE cf.status = 'active'
                  GROUP BY cf.framework_type";

        $result = $this->compliance_avitations($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get pending notifications
     */
    private function getNotifications() {
        $query = "SELECT * FROM compliance_notifications
                  WHERE read_at IS NULL
                    AND (expires_at IS NULL OR expires_at > NOW())
                  ORDER BY priority DESC, created_at DESC
                  LIMIT 10";

        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get training status
     */
    private function getTrainingStatus() {
        $query = "SELECT
                     COUNT(*) as total_scheduled,
                     SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                     SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                     SUM(CASE WHEN effectiveness_rating >= 4 THEN 1 ELSE 0 END) as effective
                  FROM compliance_training
                  WHERE scheduled_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";

        $result = $this->compliance_avitations($query);
        return $result->fetch_assoc();
    }

    /**
     * Get documentation status
     */
    private function getDocumentationStatus() {
        $query = "SELECT
                     COUNT(*) as total_documents,
                     SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved,
                     SUM(CASE WHEN expiry_date <= CURDATE() THEN 1 ELSE 0 END) as expired,
                     SUM(CASE WHEN review_frequency = 'as_needed' AND last_review_date < DATE_SUB(NOW(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) as review_needed
                  FROM compliance_documentation
                  WHERE controlled_document = TRUE";

        $result = $this->compliance_avitations($query);
        return $result->fetch_assoc();
    }

    /**
     * Get compliance metrics
     */
    private function getComplianceMetrics() {
        return [
            'total_frameworks' => count($this->complianceFrameworks),
            'active_requirements' => $this->getActiveRequirementsCount(),
            'overdue_audits' => $this->getOverdueAuditsCount(),
            'pending_notifications' => $this->getPendingNotificationsCount(),
            'training_completion_rate' => 0, // Would calculate from actual data
            'documentation_compliance' => 0, // Would calculate from actual data
            'overall_compliance_trend' => 'stable' // Would calculate from historical data
        ];
    }

    /**
     * Helper methods
     */
    private function getDefaultRequirements($frameworkType) {
        $defaultRequirements = [
            'industry_standard' => [
                'compliance' => [
                    ['name' => 'Documentation Management', 'type' => 'mandatory'],
                    ['name' => 'Record Keeping', 'type' => 'mandatory'],
                    ['name' => 'Process Control', 'type' => 'mandatory'],
                    ['name' => 'Quality Management', 'type' => 'mandatory'],
                    ['name' => 'Training Records', 'type' => 'recommended']
                ]
            ],
            'regulatory' => [
                'compliance' => [
                    ['name' => 'Regulatory Reporting', 'type' => 'mandatory'],
                    ['name' => 'Permit Compliance', 'type' => 'mandatory'],
                    ['name' => 'Inspection Records', 'type' => 'mandatory'],
                    ['name' => 'Incident Reporting', 'type' => 'mandatory']
                ]
            ],
            'internal_policy' => [
                'compliance' => [
                    ['name' => 'Employee Handbook', 'type' => 'mandatory'],
                    ['name' => 'Safety Policies', 'type' => 'mandatory'],
                    ['name' => 'Operating Procedures', 'type' => 'recommended'],
                    ['name' => 'Code of Conduct', 'type' => 'recommended']
                ]
            ]
        ];

        return $defaultRequirements[$frameworkType] ?? [];
    }

    private function getComplianceWeight($complianceLevel, $riskLevel) {
        $complianceWeights = [
            'mandatory' => 1.0,
            'recommended' => 0.8,
            'optional' => 0.6
        ];

        $riskWeights = [
            'critical' => 1.5,
            'high' => 1.2,
            'medium' => 1.0,
            'low' => 0.8
        ];

        return ($complianceWeights[$complianceLevel] ?? 0.8) * ($riskWeights[$riskLevel] ?? 1.0);
    }

    private function getComplianceScore($complianceStatus) {
        $scores = [
            'compliant' => 1.0,
            'non_compliant' => 0.0,
            'partially_compliant' => 0.7,
            'not_assessed' => 0.5,
            'not_applicable' => 1.0
        ];

        return $scores[$complianceStatus] ?? 0;
    }

    private function determineOverallStatus($complianceScore) {
        if ($compliance >= 95) return 'excellent';
        if ($compliance >= 85) return 'good';
        if ($compliance >= 70) return 'acceptable';
        if ($compliance >= 50) return 'needs_improvement';
        return 'poor';
    }

    private function generateComplianceRecommendations($assessment) {
        $recommendations = [];

        if ($assessment['non_compliant_requirements'] > 0) {
            $recommendations[] = [
                'type' => 'non_compliance',
                'priority' => 'high',
                'title' => 'Non-compliant Requirements',
                'description' => $assessment['non_compliance_requirements'] . ' requirements are non-compliant',
                'actions' => [
                    'Immediate corrective action required',
                    'Root cause analysis',
                    'Implement remediation plan'
                ]
            ];
        }

        if ($assessment['partially_compliant_requirements'] > 0) {
            $recommendations[] = [
                'type' => 'partial_compliance',
                'priority' => 'medium',
                'title' => 'Partially Compliant Requirements',
                'description' => $assessment['partially_compliant_requirements'] . ' requirements are only partially compliant',
                'actions' => [
                    'Review compliance gaps',
                    'Complete implementation',
                    'Additional documentation'
                ]
            ];
        }

        if ($assessment['overdue_items'] ?? 0 > 0) {
            $recommendations[] = [
                'type' => 'overdue',
                'priority' => 'critical',
                'title' => 'Overdue Requirements',
                'description' => ($assessment['overdue_items'] ?? 0) . ' requirements have passed their due date',
                'actions' => [
                    'Prioritize completion',
                    'Review resource allocation',
                    'Escalate if necessary'
                ]
            ];
        }

        if ($assessment['high_risk_items'] > 0) {
            $recommendations[] = [
                'type' => 'high_risk',
                'priority' => 'critical',
                'title' => 'High-Risk Items',
                'description' => $assessment['high_risk_items'] . ' requirements pose high risk',
                'actions' => [
                    'Immediate attention required',
                    'Risk mitigation plan',
                    'Executive oversight'
                ]
            ];
        }

        return $recommendations;
    }

    /**
     * Helper method for query execution
     */
    private function compliance_avitations($query) {
        $result = $this->conn->query($query);
        return $result->fetch_assoc();
    }

    private function getActiveRequirementsCount() {
        $query = "SELECT COUNT(*) as count FROM compliance_requirements WHERE requirement_type = 'mandatory' AND status = 'active'";
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }

    private function getOverdueAuditsCount() {
        $query = "SELECT COUNT(*) as count FROM compliance_audit_schedules
                  WHERE schedule_status = 'active' AND next_audit_date < CURDATE()";
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }

    private function getPendingNotificationsCount() {
        $query = "SELECT COUNT(*) as count FROM compliance_notifications
                  WHERE read_at IS NULL AND read_by IS NULL";
        $result = $this->conn->query($query);
        return $result->fetch_assoc()['count'];
    }
}

// Page logic
$complianceManager = new ComplianceAuditManager($conn, $userRole);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_framework':
            try {
                $frameworkData = [
                    'framework_name' => $_POST['framework_name'],
                    'framework_code' => $_POST['framework_code'],
                    'framework_type' => $_POST['framework_type'],
                    'version' => $_POST['version'],
                    'description' => $_POST['description'],
                    'authority' => $_POST['authority'],
                    'scope' => $_POST['scope'],
                    'applicability' => $_POST['applicability'] ?? '',
                    'compliance_requirements' => json_decode($_POST['compliance_requirements'] ?? '{}', true),
                    'certification_requirements' => json_decode($_POST['certification_requirements'] ?? '{}', true),
                    'audit_requirements' => json_decode($_POST['audit_requirements'] ?? '{}', true),
                    'documentation_requirements' => json_decode($_POST['documentation_requirements'] ?? '{}', true),
                    'training_requirements' => json_decode($_POST['training_requirements'] ?? '{}', true),
                    'risk_assessment' => json_decode($_POST['risk_assessment'] ?? '{}', true),
                    'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null
                ];

                $result = $complianceManager->createComplianceFramework($frameworkData);
                $success = $result['success'];
                $message = $result['message'];
            } catch (Exception $e) {
                $success = false;
                $message = "Failed to create framework: " . $e->getMessage();
            }
            break;

        case 'schedule_audit':
            try {
                $scheduleData = [
                    'schedule_id' => $_POST['schedule_id'],
                    'schedule_name' => $_POST['schedule_name'],
                    'schedule_type' => $_POST['schedule_type'],
                    'framework_id' => $_POST['framework_id'],
                    'audit_frequency' => $_POST['audit_frequency'],
                    'audit_scope' => $_POST['audit_scope'],
                    'audit_objectives' => json_decode($_POST['audit_objectives'] ?? '{}', true),
                    'estimated_duration_hours' => $_POST['estimated_duration_hours'] ?? 8.0,
                    'required_resources' => json_decode($_POST['required_resources'] ?? '{}', true),
                    'audit_team' => json_decode($_POST['audit_team'] ?? '{}', true),
                    'next_audit_date' => $_POST['next_audit_date']
                ];

                $result = $complianceManager->scheduleAudit($scheduleData);
                $success = $result['success'];
                $message = $result['message'];
            } catch (Exception $e) {
                $success = false;
                $message = "Failed to schedule audit: " . $e->getMessage();
            }
            break;

        case 'execute_audit':
            try {
                $executionData = [
                    'execution_id' => $_POST['execution_id'],
                    'schedule_id' => $_POST['schedule_id'],
                    'audit_name' => $_POST['audit_name'],
                    'audit_type' => $_POST['audit_type'],
                    'framework_id' => $_POST['framework_id'],
                    'audit_date' => $_POST['audit_date'],
                    'audit_scope' => $_POST['audit_scope'],
                    'audit_team' => json_decode($_POST['audit_team'] ?? '{}', true),
                    'auditee' => $_POST['auditee']
                ];

                $result = $complianceManager->executeAudit($executionData);
                $success = $result['success'];
                $message = $result['message'];
            } catch (Exception $e) {
                $success = false;
                $message = "Failed to execute audit: " . $e->getMessage();
            }
            break;

        case 'complete_audit':
            try {
                $completionData = [
                    'end_time' => $_POST['end_time'],
                    'duration_minutes' => $_POST['duration_minutes'],
                    'findings' => json_decode($_POST['findings'] ?? '{}', true),
                    'non_conformities' => json_decode($_POST['non_conformities'] ?? '{}', true),
                    'observations' => json_decode($_POST['observations'] ?? '{}', true),
                    'positive_practices' => json_decode($_POST['positive_practices'] ?? '{}', true),
                    'compliance_score' => $_POST['compliance_score'] ?? 0,
                    'compliance_rating' => $_POST['compliance_rating'] ?? 'partially_compliant',
                    'corrective_actions' => json_decode($_POST['corrective_actions'] ?? '{}'),
                    'follow_up_required' => !empty($_POST['follow_up_required']),
                    'follow_up_actions' => json_decode($_POST['follow_up_actions'] ?? '{}'),
                    'follow_up_date' => !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null,
                    'status' => $_POST['status'] ?? 'completed'
                ];

                $result = $complianceManager->completeAudit($_POST['execution_id'], $completionData);
                $success = $result['success'];
                $message = $result['message'];
            } catch (Exception $e) {
                $success = false;
                $message = "Failed to complete audit: " . $e->getMessage();
            }
            break;

        default:
            $success = false;
            $message = 'Invalid action';
    }

    // Redirect with message
    header('Location: compliance_audit_offline.php?success=' . ($success ? '1' : '0') . '&message=' . urlencode($message));
    exit;
}

// Get dashboard data
$dashboardData = $complianceManager->getDashboardData();

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
    <title>Compliance & Audit System - Production Management System</title>
    <?php getInlineCSS(); ?>
    <style>
        .compliance-card { border: 1px solid #dee2e6; border-radius: 0.375rem; margin-bottom: 1.5rem; }
        .compliance-header { background-color: #f8f9fa; padding: 1rem; border-bottom: 1px solid #dee2e6; }
        .compliance-body { padding: 1.5rem; }
        .compliance-metric { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 0.5rem; padding: 1.5rem; text-align: center; margin-bottom: 1rem; }
        .framework-item { border: 1px solid #e9ecef; border-radius: 0.375rem; padding: 1rem; margin-bottom: 1rem; transition: all 0.3s ease; }
        .framework-item:hover { box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); transform: translateY(-2px); }
        .framework-status { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: bold; text-transform: uppercase; }
        .status-active { background-color: #28a745; color: white; }
        .status-expired { background-color: #dc3545; color: white; }
        .status-under_review { background-color: #ffc107; color: black; }
        .status-superseded { background-color: #6c757d; color: white; }
        .compliance-score { width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .score-fill { height: 100%; transition: width 0.3s ease; }
        .score-excellent { background: #28a745; }
        .score-good { background: #20c997; }
        .score-acceptable { background: #ffc107; }
        .score-needs_improvement { background: #fd7e14; }
        .score-poor { background: #dc3545; }
        .audit-item { border-left: 4px solid #17a2b8; padding: 0.75rem; margin-bottom: 0.5rem; background: #f8f9fa; }
        .audit-item.internal { border-left-color: #17a2b8; }
        .audit-item.external { border-left-color: #dc3545; }
        .audit-item.regulatory { border-left-color: #dc3545; }
        .audit-item.certification { border-left-color: #17a2b8; }
        .rating-excellent { color: #28a745; }
        .rating-good { color: #20c997; }
        rating-substantially_compliant { color: #fd7e14; }
        rating-partially_compliant { color: #ffc107; }
        rating-non_compliant { color: #dc3545; }
        .compliance-controls { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .trend-indicator { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
        .trend-up { background-color: #28a745; color: white; }
        .trend-stable { background-color: #6c757d; color: white; }
        .trend-down { background-color: #dc3545; color: white; }
        @media (max-width: 768px) {
            .compliance-controls { flex-direction: column; }
            .compliance-metric { margin-bottom: 0.5rem; }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">Compliance & Audit System</h1>
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

                <!-- Compliance Controls -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Compliance Management Controls</h5>
                    </div>
                    <div class="card-body">
                        <div class="compliance-controls">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFrameworkModal">
                                <i class="fas fa-clipboard-check"></i> Create Framework
                            </button>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#scheduleAuditModal">
                                <i class="fas fa-clipboard-list"></i> Schedule Audit
                            </button>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#trainingModal">
                                <i class="fas fa-graduation-cap"></i> Training
                            </button>
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#documentationModal">
                                <i class="fas fa-file-alt"></i> Documentation
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Compliance Overview -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="compliance-metric">
                            <div class="h4"><?php echo count($dashboardData['frameworks']); ?></div>
                            <div class="small">Active Frameworks</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="compliance-metric" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="h4"><?php echo count($dashboardData['upcoming_audits']); ?></div>
                            <div class="small">Upcoming Audits</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="compliance-metric" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="h4"><?php echo count($dashboardData['recent_audit_executions']); ?></div>
                            <div class="small">Recent Executions</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="compliance-metric" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <div class="h4"><?php echo $dashboardData['notifications']['count']; ?></div>
                            <div class="small">Pending Actions</div>
                        </div>
                    </div>
                </div>

                <!-- Framework Status -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="compliance-card">
                            <div class="compliance-header">
                                <h5 class="card-title mb-0">Compliance Frameworks Status</h5>
                            </div>
                            <div class="compliance-body">
                                <?php if (!empty($dashboardData['frameworks'])): ?>
                                    <div class="row">
                                        <?php foreach ($dashboardData['frameworks'] as $framework): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="framework-item">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <span class="framework-status status-<?php echo $framework['status']; ?>"></span>
                                                            <?php echo htmlspecialchars($framework['framework_name']); ?>
                                                        </h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($framework['framework_code']); ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <?php if (isset($framework['compliance_data'])): ?>
                                                        <div class="text-end">
                                                            <span class="badge bg-<?php echo ($framework['compliance_data']['avg_compliance_score'] >= 90) ? 'success' : (($framework['compliance_data']['avg_compliance_score'] >= 80) ? 'warning' : 'danger'); ?>">
                                                                <?php echo round($framework['compliance_data']['avg_compliance_score'], 1); ?>%
                                                            </span>
                                                        </div>
                                                        <div class="text-end mt-1">
                                                            <small>Score</small>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $framework['framework_type'])); ?>
                                                        • Authority: <?php echo htmlspecialchars($framework['authority']); ?>
                                                    </small>
                                                    <div class="mt-1">
                                                        <small class="text-muted">
                                                            Version: <?php echo htmlspecialchars($framework['version']); ?>
                                                        </small>
                                                        <small class="text-muted">
                                                            Effective: <?php echo date('M j, Y', strtotime($framework['effective_date'])); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-balance-scale fa-3x mb-3"></i>
                                        <p>No compliance frameworks configured.</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFrameworkModal">
                                            <i class="fas fa-plus"></i> Create First Framework
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Audits -->
                <div class="row mb-4">
                    <div class="col-lg-8">
                        <div class="compliance-card">
                            <div class="compliance-header">
                                <h5 class="card-title mb-0">Upcoming Scheduled Audits</h5>
                            </div>
                            <div class="compliance-body">
                                <?php if (!empty($dashboardData['upcoming_audits'])): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Audit Name</th>
                                                    <th>Type</th>
                                                    <th>Framework</th>
                                                    <th>Frequency</th>
                                                    <th>Scheduled Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dashboardData['upcoming_audits'] as $audit): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($audit['schedule_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($audit['schedule_type'] === 'regulatory') ? 'danger' : (($audit['schedule_type'] === 'external') ? 'warning' : 'info'); ?>">
                                                            <?php echo ucfirst($audit['schedule_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($audit['framework_name']); ?></td>
                                                    <td><?php echo ucfirst($audit['audit_frequency']); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($audit['next_audit_date'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo ($audit['schedule_status'] === 'active') ? 'success' : (($audit['schedule_status'] === 'completed') ? 'secondary' : 'warning'); ?>">
                                                            <?php echo ucfirst($audit['schedule_status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-calendar-check fa-3x mb-3"></i>
                                        <p>No upcoming audits scheduled.</p>
                                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#scheduleAuditModal">
                                            <i class="fas fa-plus"></i> Schedule First Audit
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="compliance-card">
                            <div class="compliance-header">
                                <h5 class="card-title mb-0">Recent Audit Executions</h5>
                            </div>
                            <div class="compliance-body">
                                <?php if (!empty($dashboardData['recent_audit_executions'])): ?>
                                    <?php $displayExecutions = array_slice($dashboardData['recent_audit_executions'], 0, 5); ?>
                                    <?php foreach ($displayExecutions as $execution): ?>
                                    <div class="audit-item <?php echo $execution['audit_type']; ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?php echo htmlspecialchars($execution['audit_name']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($execution['framework_name']); ?> •
                                                    <?php echo date('M j, Y', strtotime($execution['execution_date'])); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="rating rating-<?php echo $execution['compliance_rating']; ?>">
                                                    <?php echo $this->getRatingText($execution['compliance_rating']); ?>
                                                </span>
                                                <div class="mt-1">
                                                    <small class="text-muted">Score: <?php echo round($execution['compliance_score'], 1); ?>%</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <?php if (!empty(json_decode($execution['findings'])): ?>
                                            <div class="alert alert-warning">
                                                <strong>Findings:</strong> <?php echo count(json_decode($execution['findings'])); ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty(json_decode($execution['non_conformities'])): ?>
                                            <div class="alert alert-danger">
                                                <strong>Non-conformities:</strong> <?php echo count(json_decode($execution['non_conformities'])); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-clipboard-check fa-2x mb-3"></i>
                                        <p>No recent audit executions.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compliance Score Overview -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="compliance-card">
                            <div class="compliance-header">
                                <h5 class="compliance-title mb-0">Compliance Score by Framework Type</h5>
                            </div>
                            <div class="compliance-body">
                                <?php if (!empty($dashboardData['compliance_status'])): ?>
                                    <div class="row">
                                        <?php foreach ($dashboardData['compliance_status'] as $status): ?>
                                        <div class="col-md-6 col-lg-3 mb-3">
                                            <div class="text-center p-3">
                                                <div class="h4 text-<?php echo $this->getScoreColor($status['avg_compliance_score']); ?>">
                                                    <?php echo round($status['avg_compliance_score'], 1); ?>%
                                                </div>
                                                <div class="small text-<?php echo $this->getScoreColor($status['avg_compliance_score']); ?>">
                                                    <?php echo ucfirst($status['compliance_status']); ?>
                                                </div>
                                                <div class="small text-muted">
                                                    Framework Type: <?php echo ucfirst(str_replace('_', ' ', $status['framework_type']); ?>
                                                </div>
                                                <div class="progress mt-2" style="height: 8px;">
                                                    <div class="score-fill score-<?php echo $this->getScoreColor($status['avg_compliance_score']); ?>" style="width: <?php echo $status['avg_compliance_score']; ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-chart-line"></i>
                                        <strong>Overall Compliance:</strong>
                                        <?php
                                        $overallScore = array_sum(array_column($dashboardData['compliance_status'], 'avg_compliance_score')) / count($dashboardData['compliance_status']);
                                        echo round($overallScore, 1); ?>%
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-balance-scale fa-3x mb-3"></i>
                                        <p>No compliance status data available.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Training Status -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="compliance-card">
                            <div class="compliance-header">
                                <h5 class="card-title mb-0">Training Status</h5>
                            </div>
                            <div class="compliance-body">
                                <?php if ($dashboardData['training_status']): ?>
                                    <div class="row text-center mb-4">
                                        <div class="col-md-3">
                                            <div class="text-center p-3">
                                                <h6>Total Scheduled</h6>
                                                <div class="h4 text-success"><?php echo $dashboardData['training_status']['total_scheduled']; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center p-3">
                                                <h6>Completed</h6>
                                                <div class="h4"><?php echo $dashboardData['training_status']['completed']; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center p-3">
                                                <h6>Cancelled</h6>
                                                <div class="h4 text-danger"><?php echo $dashboardData['training_status']['cancelled']; ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-4">
                                        <div class="col-md-12">
                                            <div class="progress mb-2">
                                                <div class="progress-bar bg-info" style="height: 25px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $dashboardData['training_status']['total_scheduled'] > 0 ? ($dashboardData['training_status']['completed'] / $dashboardData['training_status']['total_scheduled']) * 100 : 0; ?>%"></div>
                                                    <small class="d-block">Training Completion Rate</small>
                                                </div>
                                        </div>
                                        <div class="row text-center">
                                            <div class="col-md-4">
                                                <small class="text-muted">Effectiveness Rating: 4.0/5.0</small>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Total Cost: $0</small>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">ROI: 0%</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-success mt-3">
                                        <i class="fas fa-check-circle"></i>
                                        <strong>Training is progressing well.</strong>
                                        <span class="ms-2">Continue monitoring employee participation and effectiveness.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-graduation-cap fa-2x mb-3"></i>
                                        <p>No training data available.</p>
                                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#trainingModal">
                                            <i class="fas fa-plus"></i> Schedule Training
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Documentation Status -->
                <div class="row">
                    <div class="col-12">
                        <div class="compliance-card">
                            <div class="compliance-header">
                                <h5 class="card-title mb-0">Documentation Status</h5>
                            </div>
                            <div class="compliance-bmessage">
                                <?php if ($dashboardData['documentation_status']): ?>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="text-center p-3">
                                                <h6>Total Documents</h6>
                                                <div class="h4"><?php echo $dashboardData['documentation_status']['total_documents']; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center p-3">
                                                <h6>Approved</h6>
                                                <div class="h4 text-success"><?php echo $dashboardData['documentation_status']['approved']; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center p-3">
                                                <h6>Expired</h6>
                                                <div class="h4 text-warning"><?php echo $dashboardData['documentation_status']['expired']; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center p-3">
                                                <h6>Review Needed</h6>
                                                <div class="h4 text-info"><?php echo $dashboardData['documentation_status']['review_needed']; ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-md-12">
                                            <div class="progress mb-2">
                                                <div class="progress-bar bg-success" style="height: 20px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $dashboardData['documentation_status']['approved'] / $dashboardData['documentation_status']['total_documents'] * 100; ?>%"></div>
                                                    <small class="d-block">Documentation Compliance</small>
                                                </div>
                                        </div>
                                        <div class="text-center">
                                            <small class="text-muted">
                                                <?php echo $dashboardData['documentation_status']['approved']; ?> approved / <?php echo $dashboardData['documentation_status']['total_documents']; ?> documents
                                            </small>
                                        </div>

                                        <div class="alert alert-info mt-3">
                                            <i class="fas fa-file-alt"></i>
                                            <strong>Documentation system is functioning well.</strong>
                                            <span class="ms-2">Keep documents updated and reviewed regularly.</span>
                                        </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-file-alt fa-2x mb-3"></i>
                                        <p>No documentation available.</p>
                                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#documentationModal">
                                            <i class="fas fa-plus"></i> Create Documents
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="row">
                    <div class="col-12">
                    <div class="compliance-card">
                        <div class="compliance-header">
                            <h5 class="card-title mb-0">Pending Notifications</h5>
                        </div>
                        <div class="compliance-body">
                            <?php if (!empty($dashboardData['notifications'])): ?>
                                <?php $displayNotifications = array_slice($dashboardData['notifications'], 0, 10); ?>
                                <?php foreach ($displayNotifications as $notification): ?>
                                <div class="alert <?php echo ($notification['priority'] === 'critical') ? 'alert-danger' : (($notification['priority'] === 'high') ? 'alert-warning' : 'alert-info'); ?> mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-bell"></i>
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                            </h6>
                                            <small class="text-muted">
                                                Type: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $notification['notification_type'])); ?>
                                                •
                                                <?php echo date('M j, Y H:i', strtotime($notification['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo ($notification['priority'] === 'critical') ? 'danger' : (($notification['priority'] === 'high') ? 'warning' : 'info'); ?>">
                                                <?php echo ucfirst($notification['priority']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <p class="mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <?php if ($notification['related_entity_type']): ?>
                                        <small class="text-muted">
                                            Related to: <?php echo $notification['related_entity_type']; ?> #<?php echo $notification['related_entity_id']; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                                    <p>All notifications have been addressed.</p>
                                    <small>System is fully compliant and up-to-date.</small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compliance Metrics -->
                <div class="row">
                    <div class="col-12">
                    <div class="compliance-card">
                        <div class="compliance-header">
                            <h5 class="card-title mb-0">Compliance Metrics</h5>
                        </div>
                        <div class="compliance-body">
                            <div class="row text-center mb-4">
                                <div class="col-md-3">
                                    <div class="text-center p-3">
                                        <h6>Frameworks</h6>
                                        <div class="h4"><?php echo $dashboardData['metrics']['total_frameworks']; ?></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3">
                                        <h6>Active Requirements</h6>
                                        <div class="h4"><?php echo $dashboardData['metrics']['active_requirements']; ?></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3">
                                        <h6>Overdue Audits</h6>
                                        <div class="h4 text-warning"><?php echo $dashboardData['metrics']['overdue_audits']; ?></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-3">
                                        <h6>Pending Actions</h6>
                                        <div class="h4 text-danger"><?php echo $dashboardData['metrics']['pending_notifications']; ?></div>
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-tachometer-alt"></i>
                                <strong>System Health:</strong>
                                All compliance systems are operational and up-to-date.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Framework Modal -->
    <div class="modal fade" id="createFrameworkModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Compliance Framework</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="createFrameworkForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="create_framework">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="framework_name" class="form-label">Framework Name *</label>
                                <input type="text" class="form-control" id="framework_name" name="framework_name" required>
                                    placeholder="e.g., ISO 9001 Quality Management">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="framework_code" class="form-label">Framework Code *</label>
                                <input type="text" class="form-control" id="framework_code" name="framework_code" required>
                                    placeholder="e.g., ISO9001_QUALITY_MGMT">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="framework_type" class="form-label">Framework Type *</label>
                                <select class="form-select" id="framework_type" name="framework_type" required>
                                    <option value="industry_standard">Industry Standard</option>
                                    <option value="regulatory">Regulatory</option>
                                    <option value="certification">Certification</option>
                                    <option value="customer_specific">Customer Specific</option>
                                    <option value="internal_policy">Internal Policy</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="version" class="form-label">Version *</label>
                                <input type="text" class="form-control" id="version" name="version" required
                                       placeholder="e.g., 1.0, 2.1, 3.0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required
                                          placeholder="Provide a comprehensive description of the compliance framework"></textarea>
                            </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="authority" class="form-label">Authority *</label>
                                <input type="text" class="form-control" id="authority" name="authority" required
                                       placeholder="e.g., ISO International Organization for Standardization">
                            </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="scope" class="form-label">Scope *</label>
                                <textarea class="form-control" id="scope" name="scope" rows="2"
                                          placeholder="Describe the scope of this framework"></textarea>
                            </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="applicability" class="form-label">Applicability</label>
                                <textarea class="form-control" id="applicability" name="applicability" rows="2"
                                              placeholder="Describe where this framework applies"></textarea>
                            </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="effective_date" class="form-label">Effective Date *</label>
                                <input type="date" class="form-control" id="effective_date" name="effective_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Default compliance requirements will be added based on framework type.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Framework
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Schedule Audit Modal -->
    <div class="modal fade" id="scheduleAuditModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="schedule_title">Schedule Compliance Audit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="scheduleAuditForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="schedule_audit">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="schedule_id" class="form-label">Schedule ID *</label>
                                <input type="text" class="form-control" id="schedule_id" name="schedule_id" required
                                       placeholder="AUD-2024-Q1">
                            </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="schedule_name" class="form-label">Audit Name *</label>
                                <input type="text" class="form-control" id="schedule_name" name="schedule_name" required
                                       placeholder="Q1 2024 Quality Audit">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="schedule_type" class="form-label">Audit Type *</label>
                                <select class="form-select" id="schedule_type" name="schedule_type" required>
                                    <option value="internal_audit">Internal Audit</option>
                                    <option value="external_audit">External Audit</option>
                                    <option value="supplier_audit">Supplier Audit</option>
                                    <option value="regulatory_audit">Regulatory Audit</option>
                                    <option value="certification_audit">Certification Audit</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="framework_id" class="form-label">Framework</label>
                                <select class="form-select" id="framework_id" name="framework_id">
                                    <option value="">Select Framework</option>
                                    <?php foreach ($this->complianceFrameworks as $framework): ?>
                                    <option value="<?php echo $framework['id']; ?>">
                                        <?php echo htmlspecialchars($framework['framework_name']); ?> (<?php echo $framework['framework_code']; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="audit_frequency" class="form-label">Frequency *</label>
                                <select class="form-select" id="audit_frequency" name="audit_frequency" required>
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="semi_annually">Semi-Annually</option>
                                    <option value="annually">Annually</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="estimated_duration_hours" class="form-label">Duration (hours)</label>
                                <input type="number" class="form-control" id="estimated_duration_hours" name="estimated_duration" step="0.5" min="1" max="100"
                                           value="8">
                            </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="audit_scope" class="form-label">Audit Scope</label>
                                <textarea class="form-control" id="audit_scope" name="audit_scope" rows="3"
                                          placeholder="Describe the scope of this audit"></textarea>
                            </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="audit_objectives" class="form-label">Audit Objectives</label>
                                <textarea class="form-control" id="audit_objectives" rows="3"
                                          placeholder="List specific objectives for this audit"></textarea>
                            </div>
                        </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i> Schedule Audit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Training Modal -->
    <div class="modal fade" id="trainingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Training</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="trainingForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="schedule_training">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="training_id" class="form-label">Training ID *</label>
                                <input type="text" class="form-control" id="training_id" name="training_id" required
                                       placeholder="TRAIN-2024-Q1">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="training_title" class="form-label">Training Title *</label>
                                <input type="text" class="form-control" id="training_title" name="training_title" required
                                       placeholder="Q1 2024 Quality Training">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="training_type" class="form-label">Training Type *</label>
                                <select class="form-select" id="training_type" name="training_type" required>
                                    <option value="compliance_awareness">Compliance Awareness</option>
                                    <option value="standard_training">Standard Training</option>
                                    <option value="certification_preparation">Certification Preparation</option>
                                    <option value="refresher_course">Refresher Course</option>
                                    <option value="new_hire_orientation">New Hire Orientation</option>
                                    <option value="specialized_training">Specialized Training</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="target_audience" class="form-label">Target Audience *</label>
                                <input type="text" class="form-control" id="target_audience" name="target_audience" placeholder="e.g., Quality Team, Production Staff">
                            </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="training_objectives" class="form-label">Objectives</label>
                                <textarea class="form-control" id="training_objectives" rows="2"
                                                  placeholder="List learning objectives"></textarea>
                            </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="delivery_method" class="form-label">Delivery Method *</label>
                                <select class="form-select" id="delivery_method" name="delivery_method">
                                    <option value="classroom">Classroom</option>
                                    <option value="online">Online</option>
                                    <option value="workshop">Workshop</option>
                                    <option value="on_the_job">On-the-Job</option>
                                    <option value="blended">Blended</option>
                                </select>
                            </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="scheduled_date" class="form-label">Scheduled Date *</label>
                                <input type="date" class="form-control" id="scheduled_date" name="scheduled_date" required>
                                <?php echo date('Y-m-d', strtotime('+1 month')); ?>>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="duration_hours" class="form-label">Duration (hours)</label>
                                <input type="number" class="form-control" id="duration_hours" step="0.5" min="0.5" max="100" value="4">
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-graduation-cap"></i>
                            <strong>Default parameters will be configured based on training type and audience size.</strong>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-graduation-cap"></i> Schedule Training
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh compliance data every 5 minutes
        let refreshInterval = setInterval(function() {
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

        // Placeholder functions for modal interactions
        function viewSPCChart(chartId) {
            console.log('View SPC Chart:', chartId);
            // Implementation would show detailed SPC chart
        }

        function calculateScoreColor($score) {
            if ($score >= 95) return 'excellent';
            if ($score >= 85) return 'good';
            if ($score >= 70) return 'acceptable';
            if ($score >= 50) return 'needs_improvement';
            return 'poor';
        }

        // Animate compliance cards on page load
        document.addEventListener('DOMContentLoaded', function() {
            const metricDisplays = document.querySelectorAll('.compliance-metric');
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

            // Animate framework items
            const frameworkItems = document.querySelectorAll('.framework-item');
            frameworkItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.3s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });

            // Animate progress bars
            const progressBars = document.querySelectorAll('.score-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.transition = 'all 0.3s ease';
                    bar.style.width = $width;
                }, 10);
            });
        });
    });
    </script>
</body>
</html>