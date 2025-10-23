<?php
// config.php - UPDATED VERSION

// Load environment configuration
if (file_exists('config/Environment.php')) {
    require_once 'config/Environment.php';
} else {
    // Fallback class if Environment doesn't exist
    class Environment {
        private static $config = [];
        public static function load($file = '.env') { }
        public static function get($key, $default = null) { return $default; }
    }
}

if (file_exists('config/Security.php')) {
    require_once 'config/Security.php';
} else {
    // Fallback Security class
    class Security {
        public static function sanitizeInput($data) { 
            if (is_array($data)) return array_map('trim', $data);
            return trim($data); 
        }
        public static function generateCSRFToken() { 
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }
        public static function verifyCSRFToken($token) {
            return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
        }
    }
}

// Session security
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

class Database {
    private $host = "localhost";
    private $db_name = "performance_tracking";
    private $username = "root";
    private $password = "";
    public $conn;
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection error. Please try again later.");
        }
        return $this->conn;
    }
}

class PerformanceCalculator {
    public static function calculateUsedMHR($no_ot_mp, $ot_mp, $ot_hours) {
        return ($no_ot_mp * 7.66) + ($ot_mp * $ot_hours);
    }
    
    public static function calculateEfficiency($output_mhr, $used_mhr) {
        return $used_mhr > 0 ? ($output_mhr / $used_mhr) * 100 : 0;
    }
    
    public static function calculatePlanCompletion($actual, $plan) {
        return $plan > 0 ? ($actual / $plan) * 100 : 0;
    }
    
    public static function calculateCPH($circuit_output, $used_mhr) {
        return $used_mhr > 0 ? $circuit_output / $used_mhr : 0;
    }
    
    public static function calculateAbsentRateScore($absent_rate) {
        $absent_rate_decimal = $absent_rate / 100;
        if ($absent_rate_decimal > 0.05) {
            return max(0, (0.7 - $absent_rate_decimal) * 30);
        } else {
            return (1 - $absent_rate_decimal) * 30;
        }
    }
    
    public static function calculateSeparationRateScore($separation_rate) {
        $separation_rate_decimal = $separation_rate / 100;
        if ($separation_rate_decimal > 0) {
            return max(0, (0.5 - $separation_rate_decimal) * 30);
        } else {
            return 30;
        }
    }
    
    public static function calculatePlanCompletionScore($plan_completion) {
        return ($plan_completion / 100) * 20;
    }
    
    public static function calculateCPHScore($current_cph, $max_cph) {
        return $max_cph > 0 ? ($current_cph / $max_cph) * 20 : 0;
    }
    
    public static function getScoreClass($score, $max_score = 100) {
        $percentage = ($score / $max_score) * 100;
        if ($percentage >= 90) return 'score-excellent';
        if ($percentage >= 80) return 'score-good';
        if ($percentage >= 70) return 'score-average';
        return 'score-poor';
    }
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>