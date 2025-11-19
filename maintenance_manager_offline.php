<?php
// maintenance_manager_offline.php - Preventive and corrective maintenance management for LAN deployment

require_once "config.php";
require_once "assets.php";

$database = Database::getInstance();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'schedule_maintenance':
                $result = scheduleMaintenance($db, $_POST);
                echo json_encode($result);
                exit;
            case 'update_maintenance':
                $result = updateMaintenance($db, $_POST);
                echo json_encode($result);
                exit;
            case 'complete_maintenance':
                $result = completeMaintenance($db, $_POST);
                echo json_encode($result);
                exit;
            case 'get_maintenance_data':
                $result = getMaintenanceData($db, $_GET);
                echo json_encode($result);
                exit;
        }
    }
}

function scheduleMaintenance($db, $data) {
    try {
        $stmt = $db->prepare("
            INSERT INTO maintenance_schedules (
                equipment_name, equipment_id, production_line, maintenance_type,
                frequency_type, frequency_interval, last_maintenance, next_maintenance,
                estimated_duration_hours, priority_level, maintenance_cost,
                spare_parts_required, maintenance_procedure, assigned_technician,
                status, description
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
        ");

        $result = $stmt->execute([
            $data['equipment_name'],
            $data['equipment_id'],
            $data['production_line'],
            $data['maintenance_type'],
            $data['frequency_type'],
            $data['frequency_interval'],
            $data['last_maintenance'] ?? null,
            $data['next_maintenance'],
            $data['estimated_duration_hours'],
            $data['priority_level'],
            $data['maintenance_cost'] ?? 0,
            $data['spare_parts_required'] ?? '',
            $data['maintenance_procedure'] ?? '',
            $data['assigned_technician'] ?? '',
            $data['description'] ?? ''
        ]);

        return ['success' => $result, 'message' => $result ? 'Maintenance scheduled successfully' : 'Failed to schedule maintenance'];
    } catch(PDOException $e) {
        error_log("Schedule Maintenance Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateMaintenance($db, $data) {
    try {
        $stmt = $db->prepare("
            UPDATE maintenance_schedules SET
                equipment_name = ?, equipment_id = ?, production_line = ?, maintenance_type = ?,
                frequency_type = ?, frequency_interval = ?, next_maintenance = ?,
                estimated_duration_hours = ?, priority_level = ?, maintenance_cost = ?,
                spare_parts_required = ?, maintenance_procedure = ?, assigned_technician = ?,
                status = ?, description = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $result = $stmt->execute([
            $data['equipment_name'],
            $data['equipment_id'],
            $data['production_line'],
            $data['maintenance_type'],
            $data['frequency_type'],
            $data['frequency_interval'],
            $data['next_maintenance'],
            $data['estimated_duration_hours'],
            $data['priority_level'],
            $data['maintenance_cost'],
            $data['spare_parts_required'],
            $data['maintenance_procedure'],
            $data['assigned_technician'],
            $data['status'],
            $data['description'] ?? '',
            $data['id']
        ]);

        return ['success' => $result, 'message' => $result ? 'Maintenance updated successfully' : 'Failed to update maintenance'];
    } catch(PDOException $e) {
        error_log("Update Maintenance Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function completeMaintenance($db, $data) {
    try {
        $stmt = $db->prepare("
            UPDATE maintenance_schedules SET
                status = 'completed',
                completion_notes = ?,
                actual_duration_hours = ?,
                actual_cost = ?,
                last_maintenance = CURRENT_DATE,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $result = $stmt->execute([
            $data['completion_notes'] ?? '',
            $data['actual_duration_hours'],
            $data['actual_cost'],
            $data['id']
        ]);

        // Calculate next maintenance date if it's a recurring maintenance
        if ($result && $data['reschedule']) {
            // This would calculate the next maintenance date based on frequency
            // For simplicity, we'll just add the interval to today
            $updateNext = $db->prepare("
                UPDATE maintenance_schedules SET
                    next_maintenance = DATE_ADD(CURRENT_DATE, INTERVAL ? ?),
                    status = 'scheduled'
                WHERE id = ?
            ");

            // Convert frequency interval to MySQL interval
            $interval = '';
            switch($data['frequency_type']) {
                case 'days':
                    $interval = $data['frequency_interval'] . ' DAY';
                    break;
                case 'weeks':
                    $interval = $data['frequency_interval'] . ' WEEK';
                    break;
                case 'months':
                    $interval = $data['frequency_interval'] . ' MONTH';
                    break;
                case 'hours':
                    $interval = $data['frequency_interval'] . ' HOUR';
                    break;
            }

            if ($interval) {
                $updateNext->execute([$interval, $data['id']]);
            }
        }

        return ['success' => $result, 'message' => $result ? 'Maintenance completed successfully' : 'Failed to complete maintenance'];
    } catch(PDOException $e) {
        error_log("Complete Maintenance Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getMaintenanceData($db, $params) {
    try {
        $filter = $params['filter'] ?? 'all';
        $line = $params['line'] ?? null;

        $query = "
            SELECT
                ms.*,
                DATEDIFF(ms.next_maintenance, CURDATE()) as days_until_due,
                CASE
                    WHEN ms.status = 'completed' THEN 'completed'
                    WHEN ms.next_maintenance < CURDATE() THEN 'overdue'
                    WHEN ms.next_maintenance <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'upcoming'
                    ELSE 'scheduled'
                end as urgency_status
            FROM maintenance_schedules ms
            WHERE 1=1
        ";

        $queryParams = [];

        if ($filter === 'active') {
            $query .= " AND ms.status IN ('scheduled', 'in_progress')";
        } elseif ($filter === 'overdue') {
            $query .= " AND ms.next_maintenance < CURDATE() AND ms.status != 'completed'";
        } elseif ($filter === 'upcoming') {
            $query .= " AND ms.next_maintenance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        }

        if ($line) {
            $query .= " AND ms.production_line = ?";
            $queryParams[] = $line;
        }

        $query .= " ORDER BY
            CASE
                WHEN ms.next_maintenance < CURDATE() THEN 1
                WHEN ms.next_maintenance <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 2
                ELSE 3
            END,
            ms.priority_level DESC,
            ms.next_maintenance ASC";

        $stmt = $db->prepare($query);
        $stmt->execute($queryParams);
        $maintenance_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'data' => $maintenance_items];
    } catch(PDOException $e) {
        error_log("Get Maintenance Data Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Get existing data for display
try {
    // Get maintenance schedules
    $maintenance_query = "
        SELECT
            ms.*,
            DATEDIFF(ms.next_maintenance, CURDATE()) as days_until_due,
            CASE
                WHEN ms.status = 'completed' THEN 'completed'
                WHEN ms.next_maintenance < CURDATE() THEN 'overdue'
                WHEN ms.next_maintenance <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'upcoming'
                ELSE 'scheduled'
            end as urgency_status
        FROM maintenance_schedules ms
        ORDER BY
            CASE
                WHEN ms.next_maintenance < CURDATE() THEN 1
                WHEN ms.next_maintenance <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 2
                ELSE 3
            END,
            ms.priority_level DESC,
            ms.next_maintenance ASC
    ";
    $maintenance_items = $db->query($maintenance_query)->fetchAll(PDO::FETCH_ASSOC);

    // Get production lines for dropdown
    $lines_query = "
        SELECT DISTINCT line_shift FROM daily_performance
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY line_shift
    ";
    $production_lines = $db->query($lines_query)->fetchAll(PDO::FETCH_COLUMN);

    // Get maintenance statistics
    $stats_query = "
        SELECT
            COUNT(*) as total_maintenance,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_count,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN next_maintenance < CURDATE() AND status != 'completed' THEN 1 END) as overdue_count,
            COUNT(CASE WHEN next_maintenance BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status != 'completed' THEN 1 END) as upcoming_count,
            AVG(estimated_duration_hours) as avg_duration,
            AVG(maintenance_cost) as avg_cost
        FROM maintenance_schedules
    ";
    $maintenance_stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);

    // Get maintenance by type
    $type_stats_query = "
        SELECT
            maintenance_type,
            COUNT(*) as count,
            AVG(estimated_duration_hours) as avg_duration,
            SUM(maintenance_cost) as total_cost
        FROM maintenance_schedules
        GROUP BY maintenance_type
        ORDER BY count DESC
    ";
    $type_stats = $db->query($type_stats_query)->fetchAll(PDO::FETCH_ASSOC);

    // Get today's maintenance activities
    $today_query = "
        SELECT COUNT(*) as today_activities
        FROM maintenance_schedules
        WHERE DATE(next_maintenance) = CURDATE() AND status != 'completed'
    ";
    $today_stats = $db->query($today_query)->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Maintenance Manager Data Error: " . $e->getMessage());
    $maintenance_items = [];
    $production_lines = [];
    $maintenance_stats = [];
    $type_stats = [];
    $today_stats = [];
}

// Calculate metrics
$total_maintenance = $maintenance_stats['total_maintenance'] ?? 0;
$completion_rate = $total_maintenance > 0 ? (($maintenance_stats['completed_count'] ?? 0) / $total_maintenance) * 100 : 0;
$overdue_rate = $total_maintenance > 0 ? (($maintenance_stats['overdue_count'] ?? 0) / $total_maintenance) * 100 : 0;

// Generate HTML with offline assets
$asset_manager = $GLOBALS['asset_manager'];
echo $asset_manager->generateHTMLHeader("Maintenance Manager - Offline");
echo $asset_manager->getOfflineFontCSS();
?>

<style>
/* Maintenance Manager Specific Styles */
.maintenance-header {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.maintenance-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.maintenance-stat-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.maintenance-stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.maintenance-stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.maintenance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.maintenance-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.maintenance-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.maintenance-card.preventive::before {
    background: linear-gradient(90deg, #3498db 0%, #2980b9 100%);
}

.maintenance-card.corrective::before {
    background: linear-gradient(90deg, #e74c3c 0%, #c0392b 100%);
}

.maintenance-card.predictive::before {
    background: linear-gradient(90deg, #9b59b6 0%, #8e44ad 100%);
}

.maintenance-card.emergency::before {
    background: linear-gradient(90deg, #e67e22 0%, #d35400 100%);
}

.maintenance-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.urgency-indicator {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.urgency-overdue {
    background: #e74c3c;
    color: white;
}

.urgency-upcoming {
    background: #f39c12;
    color: #000;
}

.urgidity-scheduled {
    background: #95a5a6;
    color: white;
}

.urgency-completed {
    background: #27ae60;
    color: white;
}

.maintenance-header-info {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
}

.equipment-name {
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.equipment-id {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 0;
}

.priority-badge {
    background: #f39c12;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.priority-critical {
    background: #e74c3c;
}

.priority-high {
    background: #e67e22;
}

.priority-medium {
    background: #f39c12;
}

.priority-low {
    background: #95a5a6;
}

.maintenance-details {
    margin-bottom: 1rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.detail-label {
    color: #6c757d;
}

.detail-value {
    font-weight: 500;
    color: #2c3e50;
}

.due-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.due-soon {
    color: #f39c12;
}

.due-overdue {
    color: #e74c3c;
}

.due-ok {
    color: #27ae60;
}

.maintenance-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #dee2e6;
}

.action-btn {
    flex: 1;
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

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
}

.btn-success {
    background: #27ae60;
    color: white;
}

.btn-success:hover {
    background: #229954;
}

.btn-warning {
    background: #f39c12;
    color: white;
}

.btn-warning:hover {
    background: #e67e22;
}

.btn-danger {
    background: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background: #c0392b;
}

.btn-outline {
    background: transparent;
    border: 1px solid #dee2e6;
    color: #6c757d;
}

.btn-outline:hover {
    background: #f8f9fa;
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
    margin-bottom: 1.5rem;
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
    border-color: #f39c12;
    box-shadow: 0 0 0 3px rgba(243, 156, 18, 0.1);
}

.filter-bar {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.filter-row {
    display: flex;
    gap: 1rem;
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.maintenance-type-chart {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.chart-container {
    height: 300px;
    display: flex;
    align-items: end;
    gap: 1rem;
    padding: 1rem 0;
}

.chart-bar {
    flex: 1;
    background: linear-gradient(180deg, #f39c12 0%, #e67e22 100%);
    border-radius: 4px 4px 0 0;
    position: relative;
    min-height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.85rem;
}

.chart-label {
    text-align: center;
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: #6c757d;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal-content {
    background: white;
    margin: 2% auto;
    padding: 2rem;
    border-radius: 10px;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}

.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6c757d;
}

.modal-close:hover {
    color: #2c3e50;
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
    color: #f39c12;
    border-bottom-color: #f39c12;
    background: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

@media (max-width: 768px) {
    .maintenance-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .maintenance-grid {
        grid-template-columns: 1fr;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-group {
        min-width: auto;
    }

    .maintenance-actions {
        flex-direction: column;
    }
}
</style>

<!-- Maintenance Header -->
<div class="maintenance-header">
    <div class="container-fluid">
        <h1 class="text-center mb-0">
            <span style="margin-right: 15px;">🔧</span>Maintenance Manager
        </h1>
        <p class="text-center mb-0" style="opacity: 0.9;">
            Preventive and Corrective Maintenance Management • Offline Mode
        </p>
    </div>
</div>

<!-- Maintenance Statistics -->
<div class="container-fluid">
    <div class="maintenance-stats-grid">
        <div class="maintenance-stat-card">
            <div class="maintenance-stat-value"><?= number_format($completion_rate, 1) ?>%</div>
            <div class="maintenance-stat-label">Completion Rate</div>
        </div>
        <div class="maintenance-stat-card">
            <div class="maintenance-stat-value"><?= $maintenance_stats['overdue_count'] ?? 0 ?></div>
            <div class="maintenance-stat-label">Overdue Tasks</div>
        </div>
        <div class="maintenance-stat-card">
            <div class="maintenance-stat-value"><?= $maintenance_stats['scheduled_count'] ?? 0 ?></div>
            <div class="maintenance-stat-label">Scheduled</div>
        </div>
        <div class="maintenance-stat-card">
            <div class="maintenance-stat-value"><?= $today_stats['today_activities'] ?? 0 ?></div>
            <div class="maintenance-stat-label">Due Today</div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <button class="tab-btn active" onclick="showTab('schedule')">
            📅 Maintenance Schedule
        </button>
        <button class="tab-btn" onclick="showTab('analytics')">
            📊 Maintenance Analytics
        </button>
        <button class="tab-btn" onclick="showTab('request')">
            ➕ New Request
        </button>
        <button class="tab-btn" onclick="showTab('history')">
            📋 Maintenance History
        </button>
    </div>

    <!-- Schedule Tab -->
    <div id="schedule-tab" class="tab-content active">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Maintenance Schedule</h2>
            <button class="btn btn-primary btn-sm" onclick="showScheduleModal()">
                ➕ Schedule Maintenance
            </button>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="form-label">Filter</label>
                    <select class="form-select" id="filterMaintenance" onchange="filterMaintenance()">
                        <option value="all">All Maintenance</option>
                        <option value="overdue">Overdue</option>
                        <option value="upcoming">Upcoming (7 days)</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="form-label">Production Line</label>
                    <select class="form-select" id="filterLine" onchange="filterMaintenance()">
                        <option value="">All Lines</option>
                        <?php foreach ($production_lines as $line): ?>
                            <option value="<?= htmlspecialchars($line) ?>"><?= htmlspecialchars($line) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button class="btn btn-outline btn-sm" onclick="refreshMaintenance()">
                        🔄 Refresh
                    </button>
                </div>
            </div>
        </div>

        <div class="maintenance-grid" id="maintenanceGrid">
            <?php if (empty($maintenance_items)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🔧</div>
                        <h4>No Maintenance Scheduled</h4>
                        <p class="text-muted">Click "Schedule Maintenance" to add your first maintenance task.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($maintenance_items as $maintenance): ?>
                    <div class="maintenance-card <?= $maintenance['maintenance_type'] ?> <?= $maintenance['urgency_status'] ?>">
                        <?php if ($maintenance['urgency_status'] !== 'scheduled'): ?>
                            <div class="urgency-indicator urgency-<?= $maintenance['urgency_status'] ?>">
                                <?= ucfirst($maintenance['urgency_status']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="maintenance-header-info">
                            <div>
                                <h5 class="equipment-name"><?= htmlspecialchars($maintenance['equipment_name']) ?></h5>
                                <p class="equipment-id">ID: <?= htmlspecialchars($maintenance['equipment_id']) ?></p>
                            </div>
                            <div class="priority-badge priority-<?= $maintenance['priority_level'] ?? 'medium' ?>">
                                <?= ucfirst($maintenance['priority_level'] ?? 'medium') ?>
                            </div>
                        </div>

                        <div class="maintenance-details">
                            <div class="detail-row">
                                <span class="detail-label">Type:</span>
                                <span class="detail-value"><?= ucfirst($maintenance['maintenance_type']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Production Line:</span>
                                <span class="detail-value"><?= htmlspecialchars($maintenance['production_line']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Next Due:</span>
                                <span class="detail-value">
                                    <?php
                                    $daysUntilDue = $maintenance['days_until_due'];
                                    if ($daysUntilDue < 0) {
                                        echo '<span class="due-indicator due-overdue">⚠️ ' . abs($daysUntilDue) . ' days overdue</span>';
                                    echo ' (' . date('M j, Y', strtotime($maintenance['next_maintenance'])) . ')';
                                    } elseif ($daysUntilDue <= 7) {
                                        echo '<span class="due-indicator due-soon">📅 ' . $daysUntilDue . ' days</span>';
                                        echo ' (' . date('M j, Y', strtotime($maintenance['next_maintenance'])) . ')';
                                    } else {
                                        echo '<span class="due-indicator due-ok">📅 ' . date('M j, Y', strtotime($maintenance['next_maintenance'])) . '</span>';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Duration:</span>
                                <span class="detail-value"><?= $maintenance['estimated_duration_hours'] ?> hours</span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Assigned:</span>
                                <span class="detail-value"><?= htmlspecialchars($maintenance['assigned_technician'] ?? 'Unassigned') ?></span>
                            </div>
                        </div>

                        <?php if ($maintenance['maintenance_procedure']): ?>
                            <div class="mb-3">
                                <small class="text-muted">Procedure:</small>
                                <p class="mb-0 text-truncate" title="<?= htmlspecialchars($maintenance['maintenance_procedure']) ?>">
                                    <?= htmlspecialchars($maintenance['maintenance_procedure']) ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="maintenance-actions">
                            <?php if ($maintenance['status'] === 'scheduled'): ?>
                                <button class="action-btn btn-primary" onclick="startMaintenance(<?= $maintenance['id'] ?>)">
                                    ▶️ Start
                                </button>
                                <button class="action-btn btn-warning" onclick="editMaintenance(<?= $maintenance['id'] ?>)">
                                    ✏️ Edit
                                </button>
                            <?php elseif ($maintenance['status'] === 'in_progress'): ?>
                                <button class="action-btn btn-success" onclick="completeMaintenanceForm(<?= $maintenance['id'] ?>)">
                                    ✅ Complete
                                </button>
                                <button class="action-btn btn-outline" onclick="pauseMaintenance(<?= $maintenance['id'] ?>)">
                                    ⏸️ Pause
                                </button>
                            <?php elseif ($maintenance['status'] === 'completed'): ?>
                                <button class="action-btn btn-outline" onclick="viewMaintenanceDetails(<?= $maintenance['id'] ?>)">
                                    👁️ View Details
                                </button>
                                <button class="action-btn btn-primary" onclick="rescheduleMaintenance(<?= $maintenance['id'] ?>)">
                                    🔄 Reschedule
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Analytics Tab -->
    <div id="analytics-tab" class="tab-content">
        <h2 class="mb-4">Maintenance Analytics</h2>

        <div class="row">
            <div class="col-md-8">
                <div class="form-section">
                    <h5 class="form-title">Maintenance by Type</h5>
                    <div class="chart-container">
                        <?php if (!empty($type_stats)): ?>
                            <?php $maxCount = max(array_column($type_stats, 'count')); ?>
                            <?php foreach ($type_stats as $type): ?>
                                <div class="chart-bar" style="height: <?= ($type['count'] / $maxCount) * 250 ?>px;">
                                    <?= $type['count'] ?>
                                </div>
                                <div class="chart-label">
                                    <?= ucfirst($type['maintenance_type']) ?><br>
                                    <small><?= number_format($type['avg_duration'], 1) }h avg</small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 50px 0; color: #6c757d;">
                                No maintenance data available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="form-section">
                    <h5 class="form-title">Maintenance Metrics</h5>
                    <div class="mb-3">
                        <div class="detail-row">
                            <span class="detail-label">Total Maintenance:</span>
                            <span class="detail-value"><?= $total_maintenance ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Completion Rate:</span>
                            <span class="detail-value"><?= number_format($completion_rate, 1) ?>%</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Avg Duration:</span>
                            <span class="detail-value"><?= number_format($maintenance_stats['avg_duration'] ?? 0, 1) ?> hours</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Avg Cost:</span>
                            <span class="detail-value">$<?= number_format($maintenance_stats['avg_cost'] ?? 0, 2) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Overdue Rate:</span>
                            <span class="detail-value"><?= number_format($overdue_rate, 1) ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="form-section">
                    <h5 class="form-title">Maintenance Performance</h5>
                    <canvas id="maintenanceChart" width="800" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- New Request Tab -->
    <div id="request-tab" class="tab-content">
        <h2 class="mb-4">Schedule New Maintenance</h2>

        <div class="form-section">
            <h5 class="form-title">Equipment Information</h5>

            <form id="scheduleForm" onsubmit="scheduleNewMaintenance(this); return false;">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Equipment Name *</label>
                        <input type="text" class="form-input" name="equipment_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Equipment ID *</label>
                        <input type="text" class="form-input" name="equipment_id" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Production Line *</label>
                        <input type="text" class="form-input" name="production_line" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Maintenance Type *</label>
                        <select class="form-select" name="maintenance_type" required>
                            <option value="preventive">Preventive</option>
                            <option value="corrective">Corrective</option>
                            <option value="predictive">Predictive</option>
                            <option value="emergency">Emergency</option>
                        </select>
                    </div>
                </div>

                <h5 class="form-title" style="margin-top: 2rem;">Scheduling Information</h5>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Frequency Type *</label>
                        <select class="form-select" name="frequency_type" id="frequencyType" onchange="updateFrequencyOptions()" required>
                            <option value="hours">Hours</option>
                            <option value="days">Days</option>
                            <option value="weeks">Weeks</option>
                            <option value="months">Months</option>
                            <option value="cycles">Production Cycles</option>
                            <option value="usage_based">Usage Based</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Frequency Interval *</label>
                        <input type="number" class="form-input" name="frequency_interval" id="frequencyInterval" required>
                        <small class="text-muted" id="frequencyHelp">Number of units for the selected frequency type</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority Level *</label>
                        <select class="form-select" name="priority_level" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Next Due Date *</label>
                        <input type="date" class="form-input" name="next_maintenance" required>
                    </div>
                </div>

                <h5 class="form-title" style="margin-top: 2rem;">Resource Requirements</h5>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Estimated Duration (hours)</label>
                        <input type="number" step="0.5" class="form-input" name="estimated_duration_hours">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estimated Cost ($)</label>
                        <input type="number" step="0.01" class="form-input" name="maintenance_cost">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assigned Technician</label>
                        <input type="text" class="form-input" name="assigned_technician">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 2rem;">
                    <label class="form-label">Spare Parts Required</label>
                    <textarea class="form-textarea" name="spare_parts_required" rows="2" placeholder="List required spare parts and quantities..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Maintenance Procedure</label>
                    <textarea class="form-textarea" name="maintenance_procedure" rows="3" placeholder="Step-by-step maintenance procedure..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Description/Notes</label>
                    <textarea class="form-textarea" name="description" rows="2" placeholder="Additional notes or special requirements..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-lg">
                    📅 Schedule Maintenance
                </button>
            </form>
        </div>
    </div>

    <!-- History Tab -->
    <div id="history-tab" class="tab-content">
        <h2 class="mb-4">Maintenance History</h2>

        <div class="filter-bar">
            <div class="filter-row">
                <div class="filter-group">
                    <label class="form-label">Date Range</label>
                    <select class="form-select" id="historyFilter">
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="365">Last year</option>
                        <option value="all">All time</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button class="btn btn-outline btn-sm" onclick="generateMaintenanceReport()">
                        📊 Generate Report
                    </button>
                </div>
            </div>
        </div>

        <div class="maintenance-history-list" id="historyList">
            <div class="text-center py-5">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                <h4>Maintenance History</h4>
                <p class="text-muted">View and analyze historical maintenance records and performance.</p>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('scheduleModal')">✕</button>
        <h3 class="mb-4">Schedule Maintenance</h3>

        <form id="quickScheduleForm" onsubmit="quickScheduleMaintenance(this); return false;">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Equipment</label>
                    <input type="text" class="form-input" name="equipment_name" placeholder="Equipment name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="maintenance_type" required>
                        <option value="preventive">Preventive</option>
                        <option value="corrective">Corrective</option>
                        <option value="predictive">Predictive</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Production Line</label>
                    <input type="text" class="form-input" name="production_line" placeholder="Production line">
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select class="form-select" name="priority_level">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Due Date</label>
                    <input type="date" class="form-input" name="next_maintenance" required>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">📅 Schedule</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('scheduleModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Complete Maintenance Modal -->
<div id="completeModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('completeModal')">✕</button>
        <h3 class="mb-4">Complete Maintenance</h3>

        <form id="completeForm" onsubmit="completeMaintenanceAction(this); return false;">
            <input type="hidden" name="maintenance_id" id="completeMaintenanceId">

            <div class="form-group">
                <label class="form-label">Completion Notes</label>
                <textarea class="form-textarea" name="completion_notes" rows="4" placeholder="Describe what was done, any issues found, and recommendations..." required></textarea>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Actual Duration (hours)</label>
                    <input type="number" step="0.5" class="form-input" name="actual_duration_hours" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Actual Cost ($)</label>
                    <input type="number" step="0.01" class="form-input" name="actual_cost">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" name="reschedule" checked>
                    Schedule next maintenance
                </label>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">✅ Complete</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('completeModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Tab switching
function showTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    event.target.classList.add('active');
    document.getElementById(tabName + '-tab').classList.add('active');

    if (tabName === 'analytics') {
        drawMaintenanceChart();
    }
}

// Modal functions
function showScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'block';
}

function showCompleteModal(maintenanceId) {
    document.getElementById('completeMaintenanceId').value = maintenanceId;
    document.getElementById('completeModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Maintenance action functions
function startMaintenance(maintenanceId) {
    if (confirm('Start this maintenance task?')) {
        // In a real implementation, this would update the database
        updateMaintenanceStatus(maintenanceId, 'in_progress');
    }
}

function editMaintenance(maintenanceId) {
    // In a real implementation, this would open an edit form
    alert('Edit maintenance functionality would be implemented here');
}

function completeMaintenanceForm(maintenanceId) {
    showCompleteModal(maintenanceId);
}

function pauseMaintenance(maintenanceId) {
    if (confirm('Pause this maintenance task?')) {
        // In a real implementation, this would update the database
        updateMaintenanceStatus(maintenanceId, 'scheduled');
    }
}

function viewMaintenanceDetails(maintenanceId) {
    // In a real implementation, this would show detailed information
    alert('View maintenance details functionality would be implemented here');
}

function rescheduleMaintenance(maintenanceId) {
    // In a real implementation, this would open a reschedule form
    showScheduleModal();
}

function completeMaintenanceAction(form) {
    const formData = new FormData(form);
    formData.append('action', 'complete_maintenance');

    fetch('maintenance_manager_offline.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            closeModal('completeModal');
            location.reload();
        } else {
            alert('❌ ' + (data.error || 'Failed to complete maintenance'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Network error. Please try again.');
    });
}

function scheduleNewMaintenance(form) {
    const formData = new FormData(form);
    formData.append('action', 'schedule_maintenance');

    fetch('maintenance_manager_offline.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            form.reset();
            location.reload();
        } else {
            alert('❌ ' + (data.error || 'Failed to schedule maintenance'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Network error. Please try again.');
    });
}

function quickScheduleMaintenance(form) {
    scheduleNewMaintenance(form);
}

function updateMaintenanceStatus(maintenanceId, status) {
    // In a real implementation, this would update the database
    console.log(`Updating maintenance ${maintenanceId} to status: ${status}`);
    setTimeout(() => location.reload(), 500);
}

function updateFrequencyOptions() {
    const frequencyType = document.getElementById('frequencyType').value;
    const helpText = document.getElementById('frequencyHelp');

    switch(frequencyType) {
        case 'hours':
            helpText.textContent = 'Number of hours between maintenance';
            break;
        case 'days':
            helpText.textContent = 'Number of days between maintenance';
            break;
        case 'weeks':
            helpText.textContent = 'Number of weeks between maintenance';
            break;
        case 'months':
            helpText.textContent = 'Number of months between maintenance';
            break;
        case 'cycles':
            helpText.textContent = 'Number of production cycles between maintenance';
            break;
        case 'usage_based':
            helpText.textContent = 'Usage metrics for triggering maintenance';
            break;
    }
}

function filterMaintenance() {
    const filter = document.getElementById('filterMaintenance').value;
    const line = document.getElementById('filterLine').value;

    const cards = document.querySelectorAll('.maintenance-card');
    cards.forEach(card => {
        let show = true;

        if (filter === 'overdue' && !card.classList.contains('overdue')) {
            show = false;
        } else if (filter === 'upcoming' && !card.classList.contains('upcoming')) {
            show = false;
        } else if (filter === 'completed' && !card.classList.contains('completed')) {
            show = false;
        } else if (filter === 'active' && (card.classList.contains('completed'))) {
            show = false;
        }

        card.style.display = show ? 'block' : 'none';
    });
}

function refreshMaintenance() {
    location.reload();
}

function generateMaintenanceReport() {
    alert('📊 Maintenance report generation would be implemented here with export to PDF/CSV options');
}

function drawMaintenanceChart() {
    const canvas = document.getElementById('maintenanceChart');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;

    // Clear canvas
    ctx.clearRect(0, 0, width, height);

    // Simple line chart for maintenance trends
    const data = [
        { month: 'Jan', completed: 12, scheduled: 8 },
        { month: 'Feb', completed: 15, scheduled: 6 },
        { month: 'Mar', completed: 18, scheduled: 10 },
        { month: 'Apr', completed: 14, scheduled: 9 },
        { month: 'May', completed: 20, scheduled: 7 },
        { month: 'Jun', completed: 16, scheduled: 11 }
    ];

    const maxValue = Math.max(...data.map(d => Math.max(d.completed, d.scheduled)));
    const chartHeight = height - 60;
    const chartWidth = width - 80;
    const barWidth = chartWidth / (data.length * 2 + 1);

    // Draw axes
    ctx.strokeStyle = '#dee2e6';
    ctx.beginPath();
    ctx.moveTo(40, 20);
    ctx.lineTo(40, height - 40);
    ctx.lineTo(width - 40, height - 40);
    ctx.stroke();

    // Draw data
    data.forEach((item, index) => {
        const x = 40 + (index * 2 + 0.5) * barWidth;
        const completedHeight = (item.completed / maxValue) * chartHeight;
        const scheduledHeight = (item.scheduled / maxValue) * chartHeight;

        // Completed bars
        ctx.fillStyle = '#27ae60';
        ctx.fillRect(x, height - 40 - completedHeight, barWidth * 0.8, completedHeight);

        // Scheduled bars
        ctx.fillStyle = '#3498db';
        ctx.fillRect(x + barWidth, height - 40 - scheduledHeight, barWidth * 0.8, scheduledHeight);

        // Month labels
        ctx.fillStyle = '#2c3e50';
        ctx.font = '12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(item.month, x + barWidth, height - 20);
    });

    // Legend
    ctx.fillStyle = '#27ae60';
    ctx.fillRect(width - 150, 10, 15, 15);
    ctx.fillStyle = '#2c3e50';
    ctx.font = '12px Arial';
    ctx.fillText('Completed', width - 130, 22);

    ctx.fillStyle = '#3498db';
    ctx.fillRect(width - 150, 30, 15, 15);
    ctx.fillText('Scheduled', width - 130, 42);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('Maintenance Manager initialized');

    // Auto-refresh every 10 minutes
    setInterval(() => {
        if (document.getElementById('schedule-tab').classList.contains('active')) {
            refreshMaintenance();
        }
    }, 600000);
});
</script>

<?php
echo $asset_manager->generateHTMLFooter();
?>