<?php
// user_management_offline.php - Role-based access control system for LAN deployment

require_once "config.php";
require_once "assets.php";

$database = Database::getInstance();
$db = $database->getConnection();

// Session security
session_start();

// Initialize session if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                $result = loginUser($db, $_POST);
                if ($result['success']) {
                    $_SESSION['user_id'] = $result['user']['id'];
                    $_SESSION['user_role'] = $result['user']['role'];
                    $_SESSION['username'] = $result['user']['username'];
                    header('Location: index_lan.php');
                    exit;
                } else {
                    $login_error = $result['error'];
                }
                break;
            case 'logout':
                logoutUser();
                break;
            case 'register':
                $result = registerUser($db, $_POST);
                if ($result['success']) {
                    $registration_success = 'User registered successfully! Default password: ' . $_POST['password'];
                } else {
                    $registration_error = $result['error'];
                }
                break;
            case 'add_user':
                $result = addUser($db, $_POST);
                echo json_encode($result);
                exit;
            case 'update_user':
                $result = updateUser($db, $_POST);
                echo json_encode($result);
                exit;
            case 'delete_user':
                $result = deleteUser($db, $_POST);
                echo json_encode($result);
                exit;
            case 'update_profile':
                $result = updateProfile($db, $_POST);
                echo json_encode($result);
                exit;
        }
    }
}

function loginUser($db, $data) {
    try {
        $stmt = $db->prepare("
            SELECT id, username, password_hash, full_name, role, department,
               is_active, last_login, failed_login_attempts, account_locked_until,
               production_lines, timezone, language
            FROM users
            WHERE username = :username AND is_active = 1
        ");

        $stmt->execute(['username' => $data['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'error' => 'Invalid username or password'];
        }

        // Check if account is locked
        if ($user['account_locked_until'] && strtotime($user['account_locked_until']) > time()) {
            return ['success' => false, 'error' => 'Account is temporarily locked. Please try again later.'];
        }

        // Verify password
        if (!password_verify($data['password'], $user['password_hash'])) {
            // Increment failed login attempts
            $updateStmt = $db->prepare("
                UPDATE users SET
                    failed_login_attempts = failed_login_attempts + 1,
                    account_locked_until = CASE
                        WHEN failed_login_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                        ELSE NULL
                    END
                WHERE id = :id
            ");
            $updateStmt->execute(['id' => $user['id']]);

            return ['success' => false, 'error' => 'Invalid username or password'];
        }

        // Reset failed attempts on successful login
        if ($user['failed_login_attempts'] > 0) {
            $resetStmt = $db->prepare("
                UPDATE users SET
                    failed_login_attempts = 0,
                    account_locked_until = NULL,
                    last_login = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $resetStmt->execute(['id' => $user['id']]);
        } else {
            // Update last login
            $loginStmt = $db->prepare("
                UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id
            ");
            $loginStmt->execute(['id' => $user['id']]);
        }

        // Log activity
        logActivity($db, $user['id'], 'login', 'User logged in', 'system', null, null, $_SERVER['REMOTE_ADDR']);

        return [
            'success' => true,
            'user' => $user,
            'message' => 'Login successful'
        ];
    } catch(PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Login system error. Please try again.'];
    }
}

function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        // Log activity
        try {
            $database = Database::getInstance();
            $db = $database->getConnection();
            logActivity($db, $_SESSION['user_id'], 'logout', 'User logged out', 'system', null, null, $_SERVER['REMOTE_ADDR']);
        } catch(Exception $e) {
            error_log("Logout Activity Logging Error: " . $e->getMessage());
        }
    }

    // Destroy session
    session_destroy();

    header('Location: index_lan.php');
    exit;
}

function registerUser($db, $data) {
    try {
        // Validate password strength
        if (strlen($data['password']) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters long'];
        }

        // Check if username already exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$data['username']]);
        if ($checkStmt->fetch()) {
            return ['success' => false, 'error' => 'Username already exists'];
        }

        // Check if email already exists
        if (!empty($data['email'])) {
            $emailStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $emailStmt->execute([$data['email']]);
            if ($emailStmt->fetch()) {
                return ['success' => false, 'error' => 'Email already exists'];
            }
        }

        // Validate role
        $validRoles = ['operator', 'supervisor', 'manager', 'executive', 'admin'];
        if (!in_array($data['role'], $validRoles)) {
            $data['role'] = 'operator';
        }

        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        // Insert new user
        $stmt = $db->prepare("
            INSERT INTO users (
                username, email, password_hash, full_name, employee_id,
                role, department, production_lines, is_active,
                timezone, language, phone, alternate_email,
                emergency_contact_name, emergency_contact_phone
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['username'],
            $data['email'],
            $passwordHash,
            $data['full_name'],
            $data['employee_id'] ?? '',
            $data['role'],
            $data['department'] ?? '',
            json_encode([]), // Empty production_lines array
            $data['timezone'] ?? 'UTC',
            $data['language'] ?? 'en',
            $data['phone'] ?? '',
            $data['alternate_email'] ?? '',
            $data['emergency_contact_name'] ?? '',
            $data['emergency_contact_phone'] ?? ''
        ]);

        if ($result) {
            $userId = $db->lastInsertId();
            logActivity($db, $userId, 'register', 'New user registered', 'users', $userId, null, $_SERVER['REMOTE_ADDR']);
            return ['success' => true, 'message' => 'User registered successfully'];
        } else {
            return ['success' => false, 'error' => 'Failed to register user'];
        }
    } catch(PDOException $e) {
        error_log("Registration Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Registration system error. Please try again.'];
    }
}

function addUser($db, $data) {
    try {
        // Validate required fields
        if (empty($data['username']) || empty($data['full_name']) || empty($data['role'])) {
            return ['success' => false, 'error' => 'Required fields missing'];
        }

        // Check if username already exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$data['username']]);
        if ($checkStmt->fetch()) {
            return ['success' => false, 'error' => 'Username already exists'];
        }

        // Validate role
        $validRoles = ['operator', 'supervisor', 'manager', 'executive', 'admin'];
        if (!in_array($data['role'], $validRoles)) {
            $data['role'] = 'operator';
        }

        // Generate password if not provided
        if (empty($data['password'])) {
            $data['password'] = generateRandomPassword();
            $isNewUser = true;
        } else {
            $isNewUser = false;
        }

        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        // Insert user
        $stmt = $db->prepare("
            INSERT INTO users (
                username, email, password_hash, full_name, employee_id,
                role, department, production_lines, is_active,
                timezone, language, phone, alternate_email,
                emergency_contact_name, emergency_contact_phone
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['username'],
            $data['email'],
            $passwordHash,
            $data['full_name'],
            $data['employee_id'] ?? '',
            $data['role'],
            $data['department'] ?? '',
            json_encode($data['production_lines'] ?? []),
            $data['is_active'] ?? true,
            $data['timezone'] ?? 'UTC',
            $data['language'] ?? 'en',
            $data['phone'] ?? '',
            $data['alternate_email'] ?? '',
            $data['emergency_contact_name'] ?? '',
            $data['emergency_contact_phone'] ?? ''
        ]);

        if ($result) {
            $userId = $db->lastInsertId();
            logActivity($db, $_SESSION['user_id'] ?? 1, 'create_user', 'Created user: ' . $data['username'], 'users', $userId, null, $_SERVER['REMOTE_ADDR']);

            $message = $isNewUser ?
                'User created successfully. Password: ' . $data['password'] :
                'User created successfully';

            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'error' => 'Failed to create user'];
        }
    } catch(PDOException $e) {
        error_log("Add User Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'User creation error'];
    }
}

function updateUser($db, $data) {
    try {
        $userId = $data['id'];
        if (empty($userId)) {
            return ['success' => false, 'error' => 'User ID required'];
        }

        // Build update query dynamically
        $updateFields = [];
        $updateValues = [];

        $allowedFields = ['full_name', 'email', 'role', 'department', 'production_lines', 'is_active', 'timezone', 'language', 'phone', 'alternate_email', 'emergency_contact_name', 'emergency_contact_phone'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'production_lines') {
                    $updateFields[] = "$field = ?";
                    $updateValues[] = json_encode($data[$field]);
                } elseif ($field === 'is_active') {
                    $updateFields[] = "$field = ?";
                    $updateValues[] = ($data[$field] === 'true' || $data[$field] === true) ? 1 : 0;
                } else {
                    $updateFields[] = "$field = ?";
                    $updateValues[] = $data[$field];
                }
            }
        }

        if (empty($updateFields)) {
            return ['success' => false, 'error' => 'No fields to update'];
        }

        $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
        $updateValues[] = $userId;

        $updateSql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";

        $stmt = $db->prepare($updateSql);
        $result = $stmt->execute($updateValues);

        if ($result) {
            logActivity($db, $_SESSION['user_id'] ?? 1, 'update_user', 'Updated user ID: ' . $userId, 'users', $userId, null, $_SERVER['REMOTE_ADDR']);
            return ['success' => true, 'message' => 'User updated successfully'];
        } else {
            return ['success' => false, 'error' => 'Failed to update user'];
        }
    } catch(PDOException $e) {
        error_log("Update User Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'User update error'];
    }
}

function deleteUser($db, $data) {
    try {
        $userId = $data['id'];
        if (empty($userId)) {
            return ['success' => false, 'error' => 'User ID required'];
        }

        // Check if user is trying to delete themselves
        if ($userId == $_SESSION['user_id']) {
            return ['success' => false, 'error' => 'You cannot delete your own account'];
        }

        // Check if user is admin
        $userStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['role'] === 'admin') {
            $adminCount = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            if ($adminCount <= 1) {
                return ['success' => false, 'error' => 'Cannot delete the last admin user'];
            }
        }

        // Soft delete by setting is_active = false
        $stmt = $db->prepare("UPDATE users SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $stmt->execute([$userId]);

        if ($result) {
            logActivity($db, $_SESSION['user_id'], 'delete_user', 'Deactivated user ID: ' . $userId, 'users', $userId, null, $_SERVER['REMOTE_ADDR']);
            return ['success' => true, 'message' => 'User deactivated successfully'];
        } else {
            return ['success' => false, 'error' => 'Failed to deactivate user'];
        }
    } catch(PDOException $e) {
        error_log("Delete User Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'User deletion error'];
    }
}

function updateProfile($db, $data) {
    try {
        $userId = $_SESSION['user_id'] ?? 0;
        if (!$userId) {
            return ['success' => false, 'error' => 'Not logged in'];
        }

        // Update password if provided
        if (!empty($data['current_password']) && !empty($data['new_password'])) {
            // Verify current password
            $currentStmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $currentStmt->execute([$userId]);
            $currentUser = $currentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentUser || !password_verify($data['current_password'], $currentUser['password_hash'])) {
                return ['success' => false, 'error' => 'Current password is incorrect'];
            }

            // Validate new password
            if (strlen($data['new_password']) < 8) {
                return ['success' => false, 'error' => 'New password must be at least 8 characters long'];
            }

            // Update password
            $passwordHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE users SET password_hash = ?, password_changed_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$passwordHash, $userId]);
        }

        // Update other profile fields
        $allowedFields = ['full_name', 'email', 'phone', 'alternate_email', 'emergency_contact_name', 'emergency_contact_phone', 'timezone', 'language'];
        $updateFields = [];
        $updateValues = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = ?";
                $updateValues[] = $data[$field];
            }
        }

        if (!empty($updateFields)) {
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
            $updateValues[] = $userId;

            $updateSql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute($updateValues);
        }

        logActivity($db, $userId, 'update_profile', 'Updated profile', 'users', $userId, null, $_SERVER['REMOTE_ADDR']);

        return ['success' => true, 'message' => 'Profile updated successfully'];
    } catch(PDOException $e) {
        error_log("Update Profile Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Profile update error'];
    }
}

function logActivity($db, $userId, $activityType, $description, $table, $recordId, $oldValues, $ipAddress) {
    try {
        $stmt = $db->prepare("
            INSERT INTO user_activity_log (
                user_id, activity_type, activity_description, table_name,
                record_id, old_values, new_values, ip_address,
                activity_timestamp
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            $userId,
            $activityType,
            $description,
            $table,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            null, // new values would be the updated data
            $ipAddress
        ]);
    } catch(PDOException $e) {
        error_log("Activity Logging Error: " . $e->getMessage());
    }
}

function generateRandomPassword($length = 10) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function hasPermission($requiredRole) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $userRole = $_SESSION['user_role'];

    $roleHierarchy = [
        'operator' => 1,
        'supervisor' => 2,
        'manager' => 3,
        'executive' => 4,
        'admin' => 5
    ];

    return isset($roleHierarchy[$userRole]) && $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
}

// Get current user information
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    try {
        $database = Database::getInstance();
        $db = $database->getConnection();

        $stmt = $db->prepare("
            SELECT id, username, full_name, email, role, department,
                   is_active, last_login, production_lines, timezone, language
            FROM users
            WHERE id = ? AND is_active = 1
        ");

        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        return null;
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Require login for protected pages
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index_lan.php?action=login');
        exit;
    }
}

// Generate HTML with offline assets
$asset_manager = $GLOBALS['asset_manager'];
echo $asset_manager->generateHTMLHeader("User Management System - Offline");
echo $asset_manager->getOfflineFontCSS();

// Handle login/logout redirect
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'logout') {
        logoutUser();
    } elseif ($_GET['action'] === 'login' && !isLoggedIn()) {
        // Show login form
    }
}
?>

<style>
/* User Management Specific Styles */
.auth-header {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    padding: 3rem 0;
    margin-bottom: 2rem;
    text-align: center;
}

.auth-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.auth-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
}

.auth-form-container {
    max-width: 400px;
    margin: 0 auto;
    padding: 2rem;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.auth-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.auth-group {
    display: flex;
    flex-direction: column;
}

.auth-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.auth-input {
    padding: 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.auth-input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.auth-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0.5rem 0;
}

.auth-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #3498db;
}

.auth-btn {
    padding: 0.75rem 2rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    text-align: center;
}

.auth-btn-primary {
    background: #3498db;
    color: white;
}

.auth-btn-primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
}

.auth-btn-secondary {
    background: #6c757d;
    color: white;
}

.auth-btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    border: 1px solid #f5c2c7;
    text-align: center;
}

.success-message {
    background: #d4edda;
    color: #155724;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    border: 1px solid #c3e6cb;
    text-align: center;
}

.user-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.user-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.user-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.user-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #dee2e6;
}

.user-info {
    flex: 1;
}

.user-name {
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.user-details {
    font-size: 0.9rem;
    color: #6c757d;
}

.user-role {
    background: #3498db;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.role-operator { background: #95a5a6; }
.role-supervisor { background: #f39c12; }
.role-manager { background: #e67e22; }
.role-executive { background: #d35400; }
.role-admin { background: #e74c3c; }

.user-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.status-active { background: #28a745; }
.status-inactive { background: #dc3545; }

.user-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #dee2e6;
}

.user-action {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    text-align: center;
}

.action-edit {
    background: #3498db;
    color: white;
}

.action-delete {
    background: #e74c3c;
    color: white;
}

.action-activate {
    background: #27ae60;
    color: white;
}

.action-deactivate {
    background: #f39c12;
    color: white;
}

.action-logout {
    background: #6c757d;
    color: white;
}

.action-view {
    background: #17a2b8;
    color: white;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.dashboard-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    text-align: center;
    transition: all 0.3s ease;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.dashboard-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem auto;
    font-size: 1.5rem;
    color: white;
}

.dashboard-value {
    font-size: 2rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.dashboard-label {
    color: #6c757d;
    font-size: 0.9rem;
}

.dashboard-actions {
    margin-top: 1rem;
}

.dashboard-link {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: #3498db;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.dashboard-link:hover {
    background: #2980b9;
}

.tab-nav {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    margin-bottom: 2rem;
}

.tab-btn {
    padding: 1rem 2rem;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 500;
    color: #6c757d;
    transition: all 0.3s ease;
    border-bottom: 3px solid transparent;
}

.tab-btn.active {
    color: #3498db;
    border-bottom-color: #3498db;
    background: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.form-section {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.form-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 1.5rem;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.form-input, .form-select, .form-textarea {
    padding: 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.form-row {
    display: flex;
    gap: 1rem;
    align-items: end;
}

.form-col {
    flex: 1;
}

.activity-log {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    max-height: 500px;
    overflow-y: auto;
}

.activity-item {
    padding: 1rem;
    border-bottom: 1px solid #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background-color 0.3s ease;
}

.activity-item:hover {
    background-color: #f8f9fa;
}

.activity-info {
    flex: 1;
}

.activity-time {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.activity-user {
    font-weight: 500;
    color: #2c33e50;
    margin-bottom: 0.25rem;
}

.activity-description {
    font-size: 0.9rem;
    color: #6c757d;
}

.activity-meta {
    font-size: 0.75rem;
    color: #6c757d;
}

.user-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.user-stat {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
}

.permissions-list {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1rem;
}

.permission-item {
    display: flex;
    align-items: center;
    padding: 0.5rem 0;
    margin-bottom: 0.5rem;
    background: white;
    border-radius: 6px;
}

.permission-name {
    flex: 1;
    font-weight: 500;
    color: #2c3e50;
}

.permission-status {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    margin-right: 0.5rem;
}

.permission-granted { background: #28a745; }
.permission-denied { background: #dc3545; }

@media (max-width: 768px) {
    .user-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-row {
        flex-direction: column;
        align-items: stretch;
    }

    .auth-form-container {
        margin: 1rem;
        padding: 1.5rem;
    }
}
</style>

<?php if (isset($_GET['action']) && $_GET['action'] === 'login' && !isLoggedIn()): ?>
<div class="auth-header">
    <div class="container">
        <h1 class="auth-title">
            <span style="margin-right: 15px;">🔐</span>User Login
        </h1>
        <p class="auth-subtitle">Production Management System</p>
    </div>
</div>

<div class="container">
    <div class="auth-form-container">
        <?php if (isset($login_error)): ?>
            <div class="error-message">
                <?= htmlspecialchars($login_error) ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="post">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="auth-group">
                <label class="auth-label">Username</label>
                <input type="text" class="auth-input" name="username" required autofocus>
            </div>

            <div class="auth-group">
                <label class="auth-label">Password</label>
                <input type="password" class="auth-input" name="password" required>
            </div>

            <div class="auth-checkbox">
                <input type="checkbox" name="remember" id="remember">
                <label for="remember">Remember me</label>
            </div>

            <button type="submit" class="auth-btn auth-btn-primary">Login</button>
        </form>

        <div class="text-center mt-3">
            <small class="text-muted">
                Don't have an account? <a href="?action=register">Register here</a>
            </small>
        </div>
    </div>
</div>

<?php else: ?>

<!-- User Dashboard -->
<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>User Management</h2>
        </div>
        <div class="col-md-4 text-end">
            <div class="d-flex gap-2 align-items-center">
                <span class="text-muted">Welcome, <?= getCurrentUser()['full_name'] ?? 'User' ?>!</span>
                <a href="?action=logout" class="auth-btn auth-btn-secondary">Logout</a>
            </div>
        </div>
    </div>

    <!-- User Statistics -->
    <div class="user-stats">
        <div class="user-stat">
            <div class="stat-value"><?= isset($maintenance_stats['total_maintenance']) ? $maintenance_stats['total_maintenance'] : 0 ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="user-stat">
            <div class="stat-value"><?= isset($maintenance_stats['completed_count']) ? $maintenance_stats['completed_count'] : 0 ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="user-stat">
            <div class="stat-value"><?= isset($maintenance_stats['scheduled_count']) ? $maintenance_stats['scheduled_count'] : 0 ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="user-stat">
            <div class="stat-value"><?= isset($maintenance_stats['overdue_count']) ? $maintenance_stats['overdue_count'] : 0 ?></div>
            <div class="stat-label">Overdue</div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <button class="tab-btn active" onclick="showTab('users')">
            👥 Users
        </button>
        <button class="tab-btn" onclick="showTab('register')">
            ➕ Add User
        </button>
        <button class="tab-btn" onclick="showTab('activity')">
            📋 Activity Log
        </button>
        <button class="tab-btn" onclick="showTab('permissions')">
            🔐 Permissions
        </button>
    </div>

    <!-- Users Tab -->
    <div id="users-tab" class="tab-content active">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>System Users</h2>
            <button class="btn btn-primary btn-sm" onclick="showAddUserModal()">
                ➕ Add User
            </button>
        </div>

        <div class="user-grid" id="usersList">
            <?php
            // Get all users
            try {
                $users_query = "
                    SELECT
                        id, username, full_name, email, role, department,
                        is_active, last_login, production_lines, timezone,
                        DATE(created_at) as created_date
                    FROM users
                    ORDER BY created_at DESC
                ";
                $users_result = $db->query($users_query);
                $users = $users_result->fetchAll(PDO::FETCH_ASSOC);

                foreach ($users as $user) {
                    $production_lines = json_decode($user['production_lines'] ?? '[]');
                    $line_list = is_array($production_lines) ? implode(', ', $production_lines) : 'All lines';

                    $lastLogin = $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'Never';
            ?>
            <div class="user-card">
                <div class="user-header">
                    <div class="user-info">
                        <h5 class="user-name"><?= htmlspecialchars($user['full_name']) ?></h5>
                        <p class="user-details">
                            <?= htmlspecialchars($user['username']) ?> • <?= htmlspecialchars($user['department'] ?? 'No department') ?>
                        </p>
                    </div>
                    <div class="user-role role-<?= $user['role'] ?>">
                        <?= ucfirst($user['role']) ?>
                    </div>
                </div>

                <div class="user-status">
                    <span class="status-dot status-<?= $user['is_active'] ? 'active' : 'inactive' ?>"></span>
                    <small><?= $user['is_active'] ? 'Active' : 'Inactive' ?></small>
                </div>

                <div class="mb-3">
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?= htmlspecialchars($user['email'] ?? 'No email') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Lines:</span>
                        <span class="detail-value"><?= htmlspecialchars($line_list) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Last Login:</span>
                        <span class="detail-value"><?= $lastLogin ?></span>
                    </div>
                </div>

                <div class="user-actions">
                    <?php if (hasPermission('manager')): ?>
                        <button class="user-action action-edit" onclick="editUser(<?= $user['id'] ?>)">
                            ✏️ Edit
                        </button>
                    <?php endif; ?>

                    <?php if (hasPermission('admin') && $user['id'] != $_SESSION['user_id']): ?>
                        <button class="user-action action-delete" onclick="deleteUser(<?= $user['id'] ?>)">
                            🗑️ Deactivate
                        </button>
                    <?php endif; ?>

                    <?php if (!$user['is_active']): ?>
                        <button class="user-action action-activate" onclick="activateUser(<?= $user['id'] ?>)">
                            ✅ Activate
                        </button>
                    <?php endif; ?>

                    <button class="user-action action-view" onclick="viewUserDetails(<?= $user['id'] ?>)">
                        👁️ Details
                    </button>
                </div>
            </div>
            <?php
                }
            if (!empty($users)) {
            ?>
                <div class="text-center py-5">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                    <h4>No Users Found</h4>
                    <p class="text-muted">Click "Add User" to create your first user account.</p>
                </div>
            <?php
            }
            ?>
        </div>
    </div>

    <!-- Register Tab -->
    <div id="register-tab" class="tab-content">
        <div class="form-section">
            <h5 class="form-title">Register New User</h5>

            <form id="registerForm" onsubmit="registerNewUser(this); return false;">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-input" name="username" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" name="email">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-input" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Employee ID</label>
                        <input type="text" class="form-input" name="employee_id">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Role *</label>
                        <select class="form-select" name="role" required>
                            <option value="operator">Operator</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="manager">Manager</option>
                            <option value="executive">Executive</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <input type="text" class="form-input" name="department">
                    </div>
                </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-input" name="phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alternate Email</label>
                        <input type="email" class="form-input" name="alternate_email">
                    </div>
                </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Emergency Contact Name</label>
                        <input type="text" class="form-input" name="emergency_contact_name">
                    </div>
                    <div class="form-col">
                        <div class="form-col">
                            <label class="form-label">Emergency Contact Phone</label>
                            <input type="tel" class="form-input" name="emergency_contact_phone">
                        </div>
                    </div>
                </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Timezone</label>
                    <select class="form-select" name="timezone">
                        <option value="UTC">UTC</option>
                        <option value="America/New_York">America/New_York</option>
                        <option value="America/Chicago">America/Chicago</option>
                        <option value="America/Los_Angeles">America/Los_Angeles</option>
                        <option value="Europe/London">Europe/London</option>
                        <option value="Asia/Tokyo">Asia/Tokyo</option>
                        <option value="Asia/Shanghai">Asia/Shanghai</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="is_active" checked>
                        Active
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">
                    📝 Register User
                </button>
            </form>
        </div>

        <?php if (isset($registration_success)): ?>
            <div class="success-message">
                <?= htmlspecialchars($registration_success) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($registration_error)): ?>
            <div class="error-message">
                <?= htmlspecialchars($registration_error) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Activity Log Tab -->
    <div id="activity-tab" class="tab-content">
        <h2 class="mb-4">Activity Log</h2>

        <div class="activity-log" id="activityList">
            <?php
            // Get recent activity log entries
            try {
                $activity_query = "
                    SELECT
                        ua.*, u.full_name as user_name,
                        u.username as username
                    FROM user_activity_log ua
                    LEFT JOIN users u ON ua.user_id = u.id
                    ORDER BY ua.activity_timestamp DESC
                    LIMIT 50
                ";

                $activity_result = $db->query($activity_query);
                $activities = $activity_result->fetchAll(PDO::FETCH_ASSOC);

                foreach ($activities as $activity) {
                    $activity_time = date('M j, Y H:i:s', strtotime($activity['activity_timestamp']));
            ?>
                <div class="activity-item">
                    <div class="activity-info">
                        <div class="activity-time"><?= $activity_time ?></div>
                        <div class="activity-user"><?= htmlspecialchars($activity['user_name'] ?? 'Unknown') ?></div>
                        <div class="activity-description"><?= htmlspecialchars($activity['activity_description']) ?></div>
                    </div>
                    <div class="activity-meta">
                        <span class="activity-meta">
                            <?= ucfirst($activity['activity_category']) ?>
                            <?php if ($activity['table_name']): ?>
                                • <?= htmlspecialchars($activity['table_name']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php
                }
                } else {
                ?>
                <div class="text-center py-5">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                    <h4>No Activity Recorded</h4>
                    <p class="text-muted">System activity will appear here once users start using the system.</p>
                </div>
                <?php
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Permissions Tab -->
    <div id="permissions-tab" class="tab-content">
        <h2 class="mb-4">Role Permissions</h2>

        <div class="permissions-list">
            <h5>Operator Role</h5>
            <div class="permission-item">
                <div class="permission-status permission-granted"></div>
                <div class="permission-name">View Dashboard</div>
                <div class="permission-name">Enter Production Data</div>
                <div class="permission-name">View Reports</div>
                <div class="permission-name">View Own Profile</div>
            </div>

            <h5>Supervisor Role</h5>
            <div class="permission-item">
                <div class="permission-status permission-granted"></div>
                <div class="permission-name">All Operator Permissions</div>
                <div class="permission-name">Manage Team Performance</div>
                <div class="permission-name">View Analytics</div>
                <div class="permission-name">Generate Basic Reports</div>
            </div>

            <h5>Manager Role</h5>
            <div class="permission-item">
                <div class="permission-status permission-granted"></div>
                <div class="permission-name">All Supervisor Permissions</div>
                <div class="permission-name">User Management</div>
                <div class="permission-name">Advanced Analytics</div>
                <div class="permission-name">System Configuration</div>
            </div>

            <h5>Executive Role</h5>
            <div class="permission-item">
                <div class="permission-status permission-granted"></div>
                <div class="permission-name">All Manager Permissions</div>
                <div class="permission-name">Executive Dashboard</div>
                <div class="permission-name">Strategic Reports</div>
                <div class="permission-name">System Overview</div>
            </div>

            <h5>Admin Role</h5>
            <div class="permission-item">
                <div class="permission-status permission-granted"></div>
                <div class="permission-name">All Executive Permissions</div>
                <div class="permission-name">System Administration</div>
                <div class="permission-name">User Administration</div>
                <div class="permission-name">System Configuration</div>
                <div class="permission-name">Full System Control</div>
            </div>
        </div>
    </div>

</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('addUserModal')">✕</button>
        <h3 class="mb-4">Add New User</h3>

        <form id="addUserForm" onsubmit="addNewUser(this); return false;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" class="form-input" name="username" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-input" name="full_name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" name="email">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select class="form-select" name="role" required>
                        <option value="operator">Operator</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="manager">Manager</option>
                        <option value="executive">Executive</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input type="text" class="form-input" name="department">
                </div>
                <div class="form-group">
                    <label class="form-label">Production Lines</label>
                    <textarea class="form-textarea" name="production_lines" rows="2" placeholder="Enter production lines this user can access, one per line (e.g., S101 DS, S102 NS)"></textarea>
                    <small class="text-muted">Enter each production line on a new line</small>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    ➕ Add User
                </button>
                <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('editUserModal')">✕</button>
        <h3 class="mb-4">Edit User</h3>

        <form id="editUserForm" onsubmit="updateExistingUser(this); return false;">
            <input type="hidden" name="id" id="editUserId">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-input" name="username" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-input" name="email">
                </div>
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-input" name="full_name" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                        <option value="operator">Operator</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="manager">Manager</option>
                        <option value="executive">Executive</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input type="text" class="form-input" name="department">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    💾 Update User
                </button>
                <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- User Details Modal -->
<div id="userDetailsModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('userDetailsModal')">✕</button>
        <h3 class="mb-4">User Details</h3>

        <div id="userDetailsContent">
            <!-- User details will be loaded here via JavaScript -->
        </div>
    </div>
</div>

<script>
// Tab switching
function showTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    event.target.classList.add('active');
    document.getElementById(tabName + '-tab').classList.add('active');
}

// Modal functions
function showAddUserModal() {
    document.getElementById('addUserModal').style.display = 'block';
    document.getElementById('addUserForm').reset();
}

function showEditUserModal(userId) {
    // Load user data into edit form
    fetch('user_management_offline.php?action=get_user&id=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const form = document.getElementById('editUserForm');
                form.querySelector('#editUserId').value = data.data.id;
                form.querySelector('input[name="username"]').value = data.data.username;
                form.querySelector('input[name="full_name"]').value = data.data.full_name;
                form.querySelector('select[name="role"]').value = data.data.role;
                form.querySelector('input[name="email"]').value = data.data.email || '';
                form.querySelector('input[name="department"]').value = data.data.department || '';
                form.querySelector('select[name="is_active"]').value = data.data.is_active ? '1' : '0';
                document.getElementById('editUserModal').style.display = 'block';
            } else {
                alert('Error loading user data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading user data');
        });
}

function showUserDetailsModal(userId) {
    // Load user details
    fetch('user_management_offline.php?action=get_user_details&id=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('userDetailsContent').innerHTML = formatUserDetails(data.data);
                document.getElementById('userDetailsModal').style.display = 'block';
            } else {
                alert('Error loading user details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading user details');
        });
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// User action functions
function editUser(userId) {
    showEditUserModal(userId);
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to deactivate this user? This can be undone from the users tab.')) {
        fetch('user_management_offline.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_user&id=' + userId + '&csrf_token=' + encodeURIComponent($_SESSION['csrf_token'])
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                refreshUsers();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deactivating user');
        });
    }
}

function activateUser(userId) {
    if (confirm('Are you sure you want to activate this user?')) {
        fetch('user_management_offline.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=activate_user&id=' + userId + '&csrf_token=' + encodeURIComponent($_SESSION['csrf_token'])
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                refreshUsers();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error activating user');
        });
    }
}

function viewUserDetails(userId) {
    showUserDetailsModal(userId);
}

function addNewUser(form) {
    const formData = new FormData(form);
    fetch('user_management_offline.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeModal('addUserModal');
            refreshUsers();
            document.getElementById('registerForm').reset();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Registration error. Please try again.');
    });
}

function updateExistingUser(form) {
    const formData = new FormData(form);
    fetch('user_management_offline.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeModal('editUserModal');
            refreshUsers();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Update error. Please try again.');
    });
}

function registerNewUser(form) {
    const formData = new FormData(form);
    fetch('user_management_offline.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('registerForm').reset();
            // Registration success handled by redirect in PHP
        } else {
            console.error('Registration error:', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function refreshUsers() {
    // Reload the page
    location.reload();
}

function formatUserDetails(user) {
    let html = '<div class="mb-3">';
    html += '<h4>' + htmlspecialchars($user['full_name']) + '</h4>';
    html += '<p><strong>Username:</strong> ' . htmlspecialchars($user['username']) + '</p>';
    html += '<p><strong>Email:</strong> ' . ($user['email'] ?? 'Not provided') . '</p>';
    html += '<p><strong>Role:</strong> ' . ucfirst($user['role']) . '</p>';
    html += '<p><strong>Department:</strong> ' . ($user['department'] ?? 'Not assigned') . '</p>';
    html += '<p><strong>Status:</strong> ' . ($user['is_active'] ? 'Active' : 'Inactive') . '</p>';
    html += '<p><strong>Member Since:</strong> ' . ($user['created_date'] ? date('F j, Y', strtotime($user['created_date'])) : 'Unknown') . '</p>';

    if ($user['last_login']) {
        $html .= '<p><strong>Last Login:</strong> ' . date('F j, Y H:i:s', strtotime($user['last_login'])) . '</p>';
    }

    if ($user['production_lines']) {
        $production_lines = json_decode($user['production_lines']);
        $line_list = is_array($production_lines) ? implode(', ', $production_lines) : 'All lines';
        $html .= '<p><strong>Production Lines:</strong> ' . htmlspecialchars($line_list) . '</p>';
    }

    if ($user['timezone']) {
        $html += '<p><strong>Timezone:</strong> ' . htmlspecialchars($user['timezone']) . '</p>';
    }

    $html += '</div>';

    return html;
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('User Management System initialized');

    // Check for URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action')) {
        if (urlParams.get('action') === 'register' && !isLoggedIn()) {
            showTab('register');
        }
    }

    // Auto-refresh every 5 minutes for active tabs
    setInterval(() => {
        const activeTab = document.querySelector('.tab-content.active');
        if (activeTab && activeTab.id === 'activity-tab') {
            // Refresh activity log
            loadActivityLog();
        }
    }, 300000);
});

function loadActivityLog() {
    // Implement activity log refresh
    console.log('Refreshing activity log...');
    // This would fetch fresh activity data from the server
}

// Initialize the tab system
if (document.getElementById('users-tab')) {
    document.getElementById('users-tab').classList.add('active');
}
if (document.getElementById('dashboard-grid')) {
    showTab('dashboard');
}

function showTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    event.target.classList.add('active');
    document.getElementById(tabName + '-tab').classList.add('active');

    if (tabName === 'dashboard') {
        showTab('dashboard');
    }
}

function showTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    event.target.classList.add('active');
    document.getElementById(tabName + '-tab').classList.add('active');

    if (tabName === 'dashboard') {
        showDashboard();
    }
}

function showDashboard() {
    // Implementation would show user dashboard based on role
    const userRole = getCurrentUser();
    if (!userRole) return;

    const dashboardContent = document.getElementById('dashboardContent');
    if (dashboardContent) {
        // Display dashboard content based on user role
        dashboardContent.innerHTML = 'Your personalized dashboard content would appear here';
    }
}
</script>

<?php
echo $asset_manager->generateHTMLFooter();
?>