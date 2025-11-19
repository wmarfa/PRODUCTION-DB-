<?php
/**
 * RESTful API for Production Management System
 * Provides JSON endpoints for mobile and external system integration
 * Version: 2.0
 */

require_once 'database_enhancements.php';
require_once 'user_management_offline.php';

// Enhanced security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Rate limiting configuration
define('RATE_LIMIT_WINDOW', 60); // 1 minute
define('RATE_LIMIT_REQUESTS', 100); // 100 requests per minute

/**
 * REST API Class with comprehensive endpoint management
 */
class ProductionRestAPI {
    private $conn;
    private $rateLimiter;
    private $authManager;
    private $version = 'v2';

    public function __construct($conn) {
        $this->conn = $conn;
        $this->rateLimiter = new RateLimiter($conn);
        $this->authManager = new APIAuthManager($conn);

        // Initialize rate limiter table if needed
        $this->initializeRateLimiter();
    }

    /**
     * Initialize rate limiter database table
     */
    private function initializeRateLimiter() {
        $createTable = "CREATE TABLE IF NOT EXISTS api_rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_ip VARCHAR(45) NOT NULL,
            endpoint VARCHAR(255) NOT NULL,
            request_count INT DEFAULT 1,
            window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_client_endpoint (client_ip, endpoint),
            INDEX idx_window_start (window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createTable);
    }

    /**
     * Main router for API requests
     */
    public function handleRequest() {
        try {
            // Extract request components
            $method = $_SERVER['REQUEST_METHOD'];
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $pathParts = explode('/', trim($path, '/'));

            // Get API version (default to v2)
            $apiVersion = 'v2';
            if (!empty($pathParts[0]) && in_array($pathParts[0], ['v1', 'v2'])) {
                $apiVersion = array_shift($pathParts);
            }

            // Get resource and ID
            $resource = $pathParts[0] ?? '';
            $resourceId = $pathParts[1] ?? null;

            // Rate limiting check
            $clientIP = $this->getClientIP();
            if (!$this->rateLimiter->checkLimit($clientIP, $resource)) {
                $this->sendResponse(429, [
                    'error' => 'Too many requests',
                    'message' => 'Rate limit exceeded. Please try again later.'
                ]);
                return;
            }

            // Authentication check (except for health endpoint)
            if ($resource !== 'health' && !$this->authManager->authenticate()) {
                $this->sendResponse(401, [
                    'error' => 'Authentication required',
                    'message' => 'Please provide valid API credentials'
                ]);
                return;
            }

            // Route to appropriate handler
            switch ($resource) {
                case 'health':
                    $this->handleHealthCheck();
                    break;

                case 'auth':
                    $this->handleAuth();
                    break;

                case 'production':
                    $this->handleProduction($method, $resourceId);
                    break;

                case 'performance':
                    $this->handlePerformance($method, $resourceId);
                    break;

                case 'quality':
                    $this->handleQuality($method, $resourceId);
                    break;

                case 'maintenance':
                    $this->handleMaintenance($method, $resourceId);
                    break;

                case 'alerts':
                    $this->handleAlerts($method, $resourceId);
                    break;

                case 'reports':
                    $this->handleReports($method, $resourceId);
                    break;

                case 'analytics':
                    $this->handleAnalytics($method, $resourceId);
                    break;

                case 'users':
                    $this->handleUsers($method, $resourceId);
                    break;

                case 'lines':
                    $this->handleLines($method, $resourceId);
                    break;

                case 'settings':
                    $this->handleSettings($method);
                    break;

                default:
                    $this->sendResponse(404, [
                        'error' => 'Not Found',
                        'message' => "Resource '$resource' not found"
                    ]);
            }

        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage());
            $this->sendResponse(500, [
                'error' => 'Internal Server Error',
                'message' => 'An unexpected error occurred'
            ]);
        }
    }

    /**
     * Handle health check endpoint
     * GET /api/v2/health
     */
    private function handleHealthCheck() {
        $healthStatus = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'api_version' => $this->version,
            'database' => $this->checkDatabaseHealth(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'uptime' => $this->getSystemUptime()
        ];

        $this->sendResponse(200, $healthStatus);
    }

    /**
     * Handle authentication endpoints
     */
    private function handleAuth() {
        $method = $_SERVER['REQUEST_METHOD'];

        switch ($method) {
            case 'POST':
                $this->handleLogin();
                break;

            case 'DELETE':
                $this->handleLogout();
                break;

            default:
                $this->sendResponse(405, [
                    'error' => 'Method Not Allowed',
                    'message' => 'Only POST and DELETE methods are supported for auth'
                ]);
        }
    }

    /**
     * Handle user login
     * POST /api/v2/auth
     */
    private function handleLogin() {
        $input = $this->getInput();

        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Username and password are required'
            ]);
            return;
        }

        // Validate credentials
        $query = "SELECT id, username, full_name, user_role, last_login, is_active, failed_login_attempts
                 FROM users WHERE username = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $this->sendResponse(401, [
                'error' => 'Authentication Failed',
                'message' => 'Invalid username or password'
            ]);
            return;
        }

        $user = $result->fetch_assoc();

        // Check if account is active
        if (!$user['is_active']) {
            $this->sendResponse(403, [
                'error' => 'Account Disabled',
                'message' => 'Your account has been disabled. Please contact administrator.'
            ]);
            return;
        }

        // Check if account is locked
        if ($user['failed_login_attempts'] >= 5) {
            $this->sendResponse(423, [
                'error' => 'Account Locked',
                'message' => 'Account locked due to multiple failed attempts. Please contact administrator.'
            ]);
            return;
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Increment failed attempts
            $updateQuery = "UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?";
            $stmt = $this->conn->prepare($updateQuery);
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();

            $this->sendResponse(401, [
                'error' => 'Authentication Failed',
                'message' => 'Invalid username or password'
            ]);
            return;
        }

        // Generate API token
        $apiToken = $this->generateApiToken();
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Store API token
        $tokenQuery = "INSERT INTO api_tokens (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $this->conn->prepare($tokenQuery);
        $stmt->bind_param("iss", $user['id'], $apiToken, $tokenExpiry);
        $stmt->execute();

        // Reset failed attempts and update last login
        $updateQuery = "UPDATE users SET failed_login_attempts = 0, last_login = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();

        // Log the login
        $this->logActivity($user['id'], 'api_login', 'User authenticated via API');

        $this->sendResponse(200, [
            'success' => true,
            'message' => 'Authentication successful',
            'data' => [
                'token' => $apiToken,
                'expires_at' => $tokenExpiry,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'role' => $user['user_role'],
                    'last_login' => $user['last_login']
                ]
            ]
        ]);
    }

    /**
     * Handle user logout
     * DELETE /api/v2/auth
     */
    private function handleLogout() {
        $token = $this->getBearerToken();

        if ($token) {
            // Invalidate the token
            $query = "DELETE FROM api_tokens WHERE token = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $token);
            $stmt->execute();
        }

        $this->sendResponse(200, [
            'success' => true,
            'message' => 'Logout successful'
        ]);
    }

    /**
     * Handle production data endpoints
     */
    private function handleProduction($method, $resourceId) {
        switch ($method) {
            case 'GET':
                if ($resourceId) {
                    $this->getProductionLine($resourceId);
                } else {
                    $this->getProductionLines();
                }
                break;

            case 'POST':
                $this->createProductionRecord();
                break;

            case 'PUT':
                if ($resourceId) {
                    $this->updateProductionRecord($resourceId);
                } else {
                    $this->sendResponse(400, [
                        'error' => 'Bad Request',
                        'message' => 'Production record ID is required for updates'
                    ]);
                }
                break;

            default:
                $this->sendResponse(405, [
                    'error' => 'Method Not Allowed',
                    'message' => 'Method not supported for production endpoint'
                ]);
        }
    }

    /**
     * Get production lines
     * GET /api/v2/production
     */
    private function getProductionLines() {
        $query = "SELECT
                    line_number,
                    line_name,
                    process_category,
                    daily_capacity,
                    manning_level,
                    current_status,
                    last_update
                 FROM production_lines
                 ORDER BY line_number";

        $result = $this->conn->query($query);
        $lines = [];

        while ($row = $result->fetch_assoc()) {
            $lines[] = $row;
        }

        $this->sendResponse(200, [
            'success' => true,
            'data' => $lines,
            'count' => count($lines)
        ]);
    }

    /**
     * Get specific production line
     * GET /api/v2/production/{lineId}
     */
    private function getProductionLine($lineId) {
        $query = "SELECT
                    line_number,
                    line_name,
                    process_category,
                    daily_capacity,
                    manning_level,
                    current_status,
                    last_update
                 FROM production_lines
                 WHERE line_number = ? OR line_name = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $lineId, $lineId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $this->sendResponse(404, [
                'error' => 'Not Found',
                'message' => 'Production line not found'
            ]);
            return;
        }

        $line = $result->fetch_assoc();
        $line['shift'] = $this->getCurrentShift();

        // Get current performance data
        $performanceQuery = "SELECT
                               actual_output,
                               plan,
                               efficiency,
                               plan_completion,
                               input_rate,
                               line_utilization,
                               machine_downtime,
                               completed_time
                            FROM daily_performance
                            WHERE line_shift = CONCAT(?, '_', ?) AND date = CURDATE()";

        $stmt = $this->conn->prepare($performanceQuery);
        $stmt->bind_param("ss", $line['line_number'], $line['shift']);
        $stmt->execute();
        $perfResult = $stmt->get_result();

        if ($perfResult->num_rows > 0) {
            $performance = $perfResult->fetch_assoc();
            $line['performance'] = $performance;
        } else {
            $line['performance'] = [
                'actual_output' => 0,
                'plan' => 0,
                'efficiency' => 0,
                'plan_completion' => 0,
                'input_rate' => 0,
                'line_utilization' => 100,
                'machine_downtime' => 0,
                'completed_time' => null
            ];
        }

        $this->sendResponse(200, [
            'success' => true,
            'data' => $line
        ]);
    }

    /**
     * Create production record
     * POST /api/v2/production
     */
    private function createProductionRecord() {
        $input = $this->getInput();

        $required = ['line_shift', 'date', 'shift', 'actual_output', 'plan', 'no_ot_mp', 'ot_mp', 'ot_hours'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                $this->sendResponse(400, [
                    'error' => 'Bad Request',
                    'message' => "Required field '$field' is missing"
                ]);
                return;
            }
        }

        // Check if record already exists
        $checkQuery = "SELECT id FROM daily_performance
                      WHERE line_shift = ? AND date = ? AND shift = ?";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bind_param("sss", $input['line_shift'], $input['date'], $input['shift']);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            $this->sendResponse(409, [
                'error' => 'Conflict',
                'message' => 'Production record already exists for this line, date, and shift'
            ]);
            return;
        }

        // Insert new record
        $insertQuery = "INSERT INTO daily_performance
                        (line_shift, date, shift, actual_output, plan, no_ot_mp, ot_mp, ot_hours,
                         input_rate, line_utilization, machine_downtime, completed_time, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($insertQuery);
        $stmt->bind_param(
            "ssiiiddiddss",
            $input['line_shift'],
            $input['date'],
            $input['shift'],
            $input['actual_output'],
            $input['plan'],
            $input['no_ot_mp'],
            $input['ot_mp'],
            $input['ot_hours'],
            $input['input_rate'] ?? 0,
            $input['line_utilization'] ?? 100,
            $input['machine_downtime'] ?? 0,
            $input['completed_time'] ?? null
        );

        if ($stmt->execute()) {
            $this->sendResponse(201, [
                'success' => true,
                'message' => 'Production record created successfully',
                'data' => [
                    'id' => $this->conn->insert_id,
                    'line_shift' => $input['line_shift'],
                    'date' => $input['date'],
                    'shift' => $input['shift']
                ]
            ]);
        } else {
            $this->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Failed to create production record'
            ]);
        }
    }

    /**
     * Update production record
     * PUT /api/v2/production/{recordId}
     */
    private function updateProductionRecord($recordId) {
        $input = $this->getInput();

        if (empty($input)) {
            $this->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'No data provided for update'
            ]);
            return;
        }

        // Build dynamic update query
        $updateFields = [];
        $params = [];
        $types = '';

        $allowedFields = [
            'actual_output' => 'i',
            'plan' => 'i',
            'no_ot_mp' => 'i',
            'ot_mp' => 'i',
            'ot_hours' => 'd',
            'input_rate' => 'd',
            'line_utilization' => 'd',
            'machine_downtime' => 'd',
            'completed_time' => 's'
        ];

        foreach ($allowedFields as $field => $type) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                $params[] = $input[$field];
                $types .= $type;
            }
        }

        if (empty($updateFields)) {
            $this->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'No valid fields provided for update'
            ]);
            return;
        }

        $updateFields[] = 'updated_at = NOW()';
        $params[] = $recordId;
        $types .= 'i';

        $updateQuery = "UPDATE daily_performance SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $this->sendResponse(200, [
                    'success' => true,
                    'message' => 'Production record updated successfully',
                    'data' => ['id' => $recordId]
                ]);
            } else {
                $this->sendResponse(404, [
                    'error' => 'Not Found',
                    'message' => 'Production record not found or no changes made'
                ]);
            }
        } else {
            $this->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Failed to update production record'
            ]);
        }
    }

    /**
     * Handle performance data endpoints
     */
    private function handlePerformance($method, $resourceId) {
        switch ($method) {
            case 'GET':
                if ($resourceId) {
                    $this->getPerformanceMetrics($resourceId);
                } else {
                    $this->getAllPerformanceMetrics();
                }
                break;

            default:
                $this->sendResponse(405, [
                    'error' => 'Method Not Allowed',
                    'message' => 'Only GET method is supported for performance endpoint'
                ]);
        }
    }

    /**
     * Get all performance metrics
     * GET /api/v2/performance
     */
    private function getAllPerformanceMetrics() {
        $filters = $this->getFilters();

        $query = "SELECT
                    dp.line_shift,
                    dp.date,
                    dp.shift,
                    dp.actual_output,
                    dp.plan,
                    dp.efficiency,
                    dp.plan_completion,
                    dp.input_rate,
                    dp.line_utilization,
                    dp.machine_downtime,
                    pl.line_name,
                    pl.process_category
                 FROM daily_performance dp
                 LEFT JOIN production_lines pl ON dp.line_shift LIKE CONCAT(pl.line_number, '_%')
                 WHERE 1=1";

        $params = [];
        $types = '';

        if (!empty($filters['start_date'])) {
            $query .= " AND dp.date >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        }

        if (!empty($filters['end_date'])) {
            $query .= " AND dp.date <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }

        if (!empty($filters['line_shift'])) {
            $query .= " AND dp.line_shift = ?";
            $params[] = $filters['line_shift'];
            $types .= 's';
        }

        if (!empty($filters['shift'])) {
            $query .= " AND dp.shift = ?";
            $params[] = $filters['shift'];
            $types .= 's';
        }

        $query .= " ORDER BY dp.date DESC, dp.line_shift";

        if (!empty($filters['limit'])) {
            $query .= " LIMIT ?";
            $params[] = $filters['limit'];
            $types .= 'i';
        }

        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $metrics = [];
        while ($row = $result->fetch_assoc()) {
            $metrics[] = $row;
        }

        $this->sendResponse(200, [
            'success' => true,
            'data' => $metrics,
            'count' => count($metrics),
            'filters' => $filters
        ]);
    }

    /**
     * Get performance metrics for specific line
     * GET /api/v2/performance/{lineShift}
     */
    private function getPerformanceMetrics($lineShift) {
        $filters = $this->getFilters();

        $query = "SELECT
                    date,
                    shift,
                    actual_output,
                    plan,
                    efficiency,
                    plan_completion,
                    input_rate,
                    line_utilization,
                    machine_downtime,
                    completed_time
                 FROM daily_performance
                 WHERE line_shift = ?";

        $params = [$lineShift];
        $types = 's';

        if (!empty($filters['start_date'])) {
            $query .= " AND date >= ?";
            $params[] = $filters['start_date'];
            $types .= 's';
        }

        if (!empty($filters['end_date'])) {
            $query .= " AND date <= ?";
            $params[] = $filters['end_date'];
            $types .= 's';
        }

        $query .= " ORDER BY date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $metrics = [];
        while ($row = $result->fetch_assoc()) {
            $metrics[] = $row;
        }

        $this->sendResponse(200, [
            'success' => true,
            'data' => $metrics,
            'count' => count($metrics),
            'line_shift' => $lineShift
        ]);
    }

    /**
     * Handle alerts endpoints
     */
    private function handleAlerts($method, $resourceId) {
        switch ($method) {
            case 'GET':
                if ($resourceId) {
                    $this->getAlert($resourceId);
                } else {
                    $this->getAlerts();
                }
                break;

            case 'POST':
                $this->createAlert();
                break;

            case 'PUT':
                if ($resourceId) {
                    $this->updateAlert($resourceId);
                } else {
                    $this->sendResponse(400, [
                        'error' => 'Bad Request',
                        'message' => 'Alert ID is required for updates'
                    ]);
                }
                break;

            default:
                $this->sendResponse(405, [
                    'error' => 'Method Not Allowed',
                    'message' => 'Method not supported for alerts endpoint'
                ]);
        }
    }

    /**
     * Get alerts
     * GET /api/v2/alerts
     */
    private function getAlerts() {
        $filters = $this->getFilters();

        $query = "SELECT
                    id,
                    alert_type,
                    severity,
                    title,
                    message,
                    line_shift,
                    status,
                    acknowledged_by,
                    acknowledged_at,
                    created_at,
                    updated_at
                 FROM production_alerts
                 WHERE 1=1";

        $params = [];
        $types = '';

        if (!empty($filters['severity'])) {
            $query .= " AND severity = ?";
            $params[] = $filters['severity'];
            $types .= 's';
        }

        if (!empty($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['line_shift'])) {
            $query .= " AND line_shift = ?";
            $params[] = $filters['line_shift'];
            $types .= 's';
        }

        if (!empty($filters['alert_type'])) {
            $query .= " AND alert_type = ?";
            $params[] = $filters['alert_type'];
            $types .= 's';
        }

        $query .= " ORDER BY created_at DESC";

        if (!empty($filters['limit'])) {
            $query .= " LIMIT ?";
            $params[] = $filters['limit'];
            $types .= 'i';
        }

        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $alerts = [];
        while ($row = $result->fetch_assoc()) {
            $alerts[] = $row;
        }

        $this->sendResponse(200, [
            'success' => true,
            'data' => $alerts,
            'count' => count($alerts)
        ]);
    }

    /**
     * Get specific alert
     * GET /api/v2/alerts/{alertId}
     */
    private function getAlert($alertId) {
        $query = "SELECT
                    id,
                    alert_type,
                    severity,
                    title,
                    message,
                    line_shift,
                    status,
                    acknowledged_by,
                    acknowledged_at,
                    created_at,
                    updated_at
                 FROM production_alerts
                 WHERE id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $alertId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $this->sendResponse(404, [
                'error' => 'Not Found',
                'message' => 'Alert not found'
            ]);
            return;
        }

        $alert = $result->fetch_assoc();
        $this->sendResponse(200, [
            'success' => true,
            'data' => $alert
        ]);
    }

    /**
     * Create new alert
     * POST /api/v2/alerts
     */
    private function createAlert() {
        $input = $this->getInput();

        $required = ['alert_type', 'severity', 'title', 'message'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || empty($input[$field])) {
                $this->sendResponse(400, [
                    'error' => 'Bad Request',
                    'message' => "Required field '$field' is missing or empty"
                ]);
                return;
            }
        }

        $query = "INSERT INTO production_alerts
                  (alert_type, severity, title, message, line_shift, status, created_at)
                  VALUES (?, ?, ?, ?, ?, 'active', NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "sssss",
            $input['alert_type'],
            $input['severity'],
            $input['title'],
            $input['message'],
            $input['line_shift'] ?? null
        );

        if ($stmt->execute()) {
            $alertId = $this->conn->insert_id;
            $this->sendResponse(201, [
                'success' => true,
                'message' => 'Alert created successfully',
                'data' => [
                    'id' => $alertId,
                    'alert_type' => $input['alert_type'],
                    'severity' => $input['severity'],
                    'title' => $input['title']
                ]
            ]);
        } else {
            $this->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Failed to create alert'
            ]);
        }
    }

    /**
     * Update alert (typically for acknowledgment)
     * PUT /api/v2/alerts/{alertId}
     */
    private function updateAlert($alertId) {
        $input = $this->getInput();

        if (empty($input['status'])) {
            $this->sendResponse(400, [
                'error' => 'Bad Request',
                'message' => 'Status is required for alert updates'
            ]);
            return;
        }

        // Verify alert exists
        $checkQuery = "SELECT id FROM production_alerts WHERE id = ?";
        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bind_param("i", $alertId);
        $stmt->execute();

        if ($stmt->get_result()->num_rows === 0) {
            $this->sendResponse(404, [
                'error' => 'Not Found',
                'message' => 'Alert not found'
            ]);
            return;
        }

        $query = "UPDATE production_alerts
                  SET status = ?, acknowledged_by = ?, acknowledged_at = NOW(), updated_at = NOW()
                  WHERE id = ?";

        $user = $this->getCurrentUser();
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ssi", $input['status'], $user, $alertId);

        if ($stmt->execute()) {
            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Alert updated successfully',
                'data' => [
                    'id' => $alertId,
                    'status' => $input['status'],
                    'acknowledged_by' => $user,
                    'acknowledged_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            $this->sendResponse(500, [
                'error' => 'Database Error',
                'message' => 'Failed to update alert'
            ]);
        }
    }

    /**
     * Handle analytics endpoints
     */
    private function handleAnalytics($method, $resourceId) {
        if ($method !== 'GET') {
            $this->sendResponse(405, [
                'error' => 'Method Not Allowed',
                'message' => 'Only GET method is supported for analytics endpoint'
            ]);
            return;
        }

        switch ($resourceId) {
            case 'oee':
                $this->getOEEAnalytics();
                break;

            case 'trends':
                $this->getTrendAnalytics();
                break;

            case 'bottlenecks':
                $this->getBottleneckAnalytics();
                break;

            case 'efficiency':
                $this->getEfficiencyAnalytics();
                break;

            default:
                $this->getGeneralAnalytics();
        }
    }

    /**
     * Get general analytics
     * GET /api/v2/analytics
     */
    private function getGeneralAnalytics() {
        $filters = $this->getFilters();

        // Get performance summary
        $summaryQuery = "SELECT
                            COUNT(*) as total_records,
                            SUM(actual_output) as total_output,
                            SUM(plan) as total_plan,
                            AVG(efficiency) as avg_efficiency,
                            AVG(plan_completion) as avg_completion,
                            SUM(machine_downtime) as total_downtime
                         FROM daily_performance
                         WHERE date BETWEEN ? AND ?";

        $params = [
            $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
            $filters['end_date'] ?? date('Y-m-d')
        ];
        $types = "ss";

        $stmt = $this->conn->prepare($summaryQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc();

        // Get line performance comparison
        $lineQuery = "SELECT
                        line_shift,
                        AVG(efficiency) as avg_efficiency,
                        AVG(plan_completion) as avg_completion,
                        COUNT(*) as records_count
                      FROM daily_performance
                      WHERE date BETWEEN ? AND ?
                      GROUP BY line_shift
                      ORDER BY avg_efficiency DESC";

        $stmt = $this->conn->prepare($lineQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $linePerformance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $this->sendResponse(200, [
            'success' => true,
            'data' => [
                'summary' => $summary,
                'line_performance' => $linePerformance,
                'generated_at' => date('c')
            ]
        ]);
    }

    /**
     * Get OEE analytics
     * GET /api/v2/analytics/oee
     */
    private function getOEEAnalytics() {
        $filters = $this->getFilters();

        // Calculate OEE components
        $query = "SELECT
                    line_shift,
                    AVG(actual_output) as avg_output,
                    AVG(plan) as avg_plan,
                    AVG(machine_downtime) as avg_downtime,
                    COUNT(*) as days_analyzed
                 FROM daily_performance
                 WHERE date BETWEEN ? AND ?";

        $params = [
            $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
            $filters['end_date'] ?? date('Y-m-d')
        ];

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $oeeData = [];
        while ($row = $result->fetch_assoc()) {
            $scheduledTime = 480; // 8 hours
            $downtime = $row['avg_downtime'];
            $availability = $scheduledTime > 0 ? (($scheduledTime - $downtime) / $scheduledTime) * 100 : 0;

            $idealOutput = $row['avg_plan'];
            $actualOutput = $row['avg_output'];
            $performance = $idealOutput > 0 ? ($actualOutput / $idealOutput) * 100 : 0;

            $quality = 95; // Default quality rate (should be calculated from quality data)

            $oee = ($availability * $performance * $quality) / 10000;

            $oeeData[] = [
                'line_shift' => $row['line_shift'],
                'availability' => round($availability, 2),
                'performance' => round($performance, 2),
                'quality' => $quality,
                'oee' => round($oee, 2),
                'days_analyzed' => $row['days_analyzed']
            ];
        }

        // Calculate overall OEE
        $overallOEE = array_sum(array_column($oeeData, 'oee')) / count($oeeData) if !empty($oeeData) else 0;

        $this->sendResponse(200, [
            'success' => true,
            'data' => [
                'line_oee' => $oeeData,
                'overall_oee' => round($overallOEE, 2),
                'generated_at' => date('c')
            ]
        ]);
    }

    /**
     * Utility Methods
     */

    private function getBearerToken() {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (strpos($authHeader, 'Bearer ') === 0) {
                return substr($authHeader, 7);
            }
        }
        return null;
    }

    private function getClientIP() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } else if (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } else if (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }
        return $ipaddress;
    }

    private function getInput() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') !== false) {
            return json_decode(file_get_contents('php://input'), true) ?: [];
        } else {
            return $_POST;
        }
    }

    private function getFilters() {
        $filters = [];

        if (isset($_GET['start_date'])) {
            $filters['start_date'] = $_GET['start_date'];
        }

        if (isset($_GET['end_date'])) {
            $filters['end_date'] = $_GET['end_date'];
        }

        if (isset($_GET['line_shift'])) {
            $filters['line_shift'] = $_GET['line_shift'];
        }

        if (isset($_GET['shift'])) {
            $filters['shift'] = $_GET['shift'];
        }

        if (isset($_GET['severity'])) {
            $filters['severity'] = $_GET['severity'];
        }

        if (isset($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (isset($_GET['alert_type'])) {
            $filters['alert_type'] = $_GET['alert_type'];
        }

        if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
            $filters['limit'] = (int)$_GET['limit'];
        }

        return $filters;
    }

    private function getCurrentUser() {
        // Get user from API token
        $token = $this->getBearerToken();
        if (!$token) return null;

        $query = "SELECT u.username FROM api_tokens t JOIN users u ON t.user_id = u.id WHERE t.token = ? AND t.expires_at > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['username'];
        }

        return null;
    }

    private function getCurrentShift() {
        $hour = (int)date('H');

        if ($hour >= 6 && $hour < 14) {
            return 'Shift-A';
        } elseif ($hour >= 14 && $hour < 22) {
            return 'Shift-B';
        } else {
            return 'Shift-C';
        }
    }

    private function generateApiToken() {
        return bin2hex(random_bytes(32));
    }

    private function logActivity($userId, $action, $description) {
        $query = "INSERT INTO user_activity (user_id, action, description, ip_address, created_at)
                  VALUES (?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isss", $userId, $action, $description, $this->getClientIP());
        $stmt->execute();
    }

    private function checkDatabaseHealth() {
        try {
            $result = $this->conn->query("SELECT 1");
            return $result ? 'connected' : 'disconnected';
        } catch (Exception $e) {
            return 'error';
        }
    }

    private function getSystemUptime() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] . ' (1 min)';
        }
        return 'N/A';
    }

    private function sendResponse($statusCode, $data) {
        http_response_code($statusCode);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}

/**
 * Rate Limiter Class
 */
class RateLimiter {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function checkLimit($clientIP, $endpoint) {
        $currentTime = time();
        $windowStart = date('Y-m-d H:i:s', $currentTime - RATE_LIMIT_WINDOW);

        // Clean old records
        $cleanupQuery = "DELETE FROM api_rate_limits WHERE window_start < ?";
        $stmt = $this->conn->prepare($cleanupQuery);
        $stmt->bind_param("s", $windowStart);
        $stmt->execute();

        // Check current count
        $checkQuery = "SELECT request_count FROM api_rate_limits
                       WHERE client_ip = ? AND endpoint = ? AND window_start > ?
                       LIMIT 1";

        $stmt = $this->conn->prepare($checkQuery);
        $stmt->bind_param("sss", $clientIP, $endpoint, $windowStart);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['request_count'] >= RATE_LIMIT_REQUESTS) {
                return false;
            }

            // Increment count
            $updateQuery = "UPDATE api_rate_limits
                           SET request_count = request_count + 1, updated_at = NOW()
                           WHERE client_ip = ? AND endpoint = ? AND window_start > ?";

            $stmt = $this->conn->prepare($updateQuery);
            $stmt->bind_param("sss", $clientIP, $endpoint, $windowStart);
            $stmt->execute();
        } else {
            // Insert new record
            $insertQuery = "INSERT INTO api_rate_limits (client_ip, endpoint, request_count, window_start)
                           VALUES (?, ?, 1, NOW())";

            $stmt = $this->conn->prepare($insertQuery);
            $stmt->bind_param("ss", $clientIP, $endpoint);
            $stmt->execute();
        }

        return true;
    }
}

/**
 * API Authentication Manager
 */
class APIAuthManager {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;

        // Create API tokens table if it doesn't exist
        $this->createTokensTable();
    }

    private function createTokensTable() {
        $createTable = "CREATE TABLE IF NOT EXISTS api_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->query($createTable);
    }

    public function authenticate() {
        // Skip authentication for health endpoint (handled in router)
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (strpos($path, '/health') !== false) {
            return true;
        }

        $token = $this->extractBearerToken();

        if (!$token) {
            return false;
        }

        $query = "SELECT t.user_id, t.expires_at, u.username, u.user_role, u.is_active
                 FROM api_tokens t
                 JOIN users u ON t.user_id = u.id
                 WHERE t.token = ? AND t.expires_at > NOW()";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return false;
        }

        $user = $result->fetch_assoc();

        if (!$user['is_active']) {
            return false;
        }

        // Update last used timestamp
        $updateQuery = "UPDATE api_tokens SET last_used_at = NOW() WHERE token = ?";
        $stmt = $this->conn->prepare($updateQuery);
        $stmt->bind_param("s", $token);
        $stmt->execute();

        return true;
    }

    private function extractBearerToken() {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (strpos($authHeader, 'Bearer ') === 0) {
                return substr($authHeader, 7);
            }
        }
        return null;
    }
}

// Main execution
try {
    // Initialize database connection
    $conn = new mysqli("localhost", "root", "", "production_db");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Create API instance and handle request
    $api = new ProductionRestAPI($conn);
    $api->handleRequest();

} catch (Exception $e) {
    error_log("API Fatal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => 'API service temporarily unavailable'
    ], JSON_PRETTY_PRINT);
}
?>