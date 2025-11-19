<?php
// quality_control_offline.php - Integrated quality management system for LAN deployment

require_once "config.php";
require_once "assets.php";

$database = Database::getInstance();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'record_measurement':
                $result = recordQualityMeasurement($db, $_POST);
                echo json_encode($result);
                exit;
            case 'add_checkpoint':
                $result = addQualityCheckpoint($db, $_POST);
                echo json_encode($result);
                exit;
            case 'update_checkpoint':
                $result = updateQualityCheckpoint($db, $_POST);
                echo json_encode($result);
                exit;
            case 'get_quality_data':
                $result = getQualityData($db, $_GET);
                echo json_encode($result);
                exit;
        }
    }
}

function recordQualityMeasurement($db, $data) {
    try {
        $stmt = $db->prepare("
            INSERT INTO quality_measurements (
                checkpoint_id, daily_performance_id, measurement_value,
                measurement_status, defect_count, defect_type, operator,
                inspector, measurement_time, batch_number, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['checkpoint_id'],
            $data['daily_performance_id'] ?? null,
            $data['measurement_value'],
            $data['measurement_status'],
            $data['defect_count'] ?? 0,
            $data['defect_type'] ?? '',
            $data['operator'],
            $data['inspector'],
            date('Y-m-d H:i:s'),
            $data['batch_number'] ?? '',
            $data['notes'] ?? ''
        ]);

        return ['success' => $result, 'message' => $result ? 'Quality measurement recorded successfully' : 'Failed to record measurement'];
    } catch(PDOException $e) {
        error_log("Quality Measurement Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function addQualityCheckpoint($db, $data) {
    try {
        $stmt = $db->prepare("
            INSERT INTO quality_checkpoints (
                checkpoint_name, production_line, checkpoint_type, quality_standard,
                tolerance_level, measurement_unit, frequency, target_value,
                min_acceptable, max_acceptable, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([
            $data['checkpoint_name'],
            $data['production_line'],
            $data['checkpoint_type'],
            $data['quality_standard'],
            $data['tolerance_level'],
            $data['measurement_unit'],
            $data['frequency'],
            $data['target_value'],
            $data['min_acceptable'],
            $data['max_acceptable'],
            $data['is_active'] ?? true
        ]);

        return ['success' => $result, 'message' => $result ? 'Quality checkpoint added successfully' : 'Failed to add checkpoint'];
    } catch(PDOException $e) {
        error_log("Add Quality Checkpoint Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function updateQualityCheckpoint($db, $data) {
    try {
        $stmt = $db->prepare("
            UPDATE quality_checkpoints SET
                checkpoint_name = ?, production_line = ?, checkpoint_type = ?, quality_standard = ?,
                tolerance_level = ?, measurement_unit = ?, frequency = ?, target_value = ?,
                min_acceptable = ?, max_acceptable = ?, is_active = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $result = $stmt->execute([
            $data['checkpoint_name'],
            $data['production_line'],
            $data['checkpoint_type'],
            $data['quality_standard'],
            $data['tolerance_level'],
            $data['measurement_unit'],
            $data['frequency'],
            $data['target_value'],
            $data['min_acceptable'],
            $data['max_acceptable'],
            $data['is_active'] ?? true,
            $data['id']
        ]);

        return ['success' => $result, 'message' => $result ? 'Quality checkpoint updated successfully' : 'Failed to update checkpoint'];
    } catch(PDOException $e) {
        error_log("Update Quality Checkpoint Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function getQualityData($db, $params) {
    try {
        $days = $params['days'] ?? 7;
        $line = $params['line'] ?? null;

        $query = "
            SELECT
                qc.checkpoint_name,
                qc.production_line,
                qc.checkpoint_type,
                qm.measurement_value,
                qm.measurement_status,
                qm.defect_count,
                qm.inspector,
                qm.measurement_time,
                dp.line_shift as production_line_shift
            FROM quality_measurements qm
            JOIN quality_checkpoints qc ON qm.checkpoint_id = qc.id
            LEFT JOIN daily_performance dp ON qm.daily_performance_id = dp.id
            WHERE qm.measurement_time >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $queryParams = ['days' => $days];

        if ($line) {
            $query .= " AND qc.production_line = :line";
            $queryParams['line'] = $line;
        }

        $query .= " ORDER BY qm.measurement_time DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($queryParams);
        $measurements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'data' => $measurements];
    } catch(PDOException $e) {
        error_log("Get Quality Data Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Get existing data for display
try {
    // Get quality checkpoints
    $checkpoints_query = "
        SELECT qc.*,
               COUNT(qm.id) as measurement_count,
               AVG(qm.measurement_value) as avg_measurement,
               COUNT(CASE WHEN qm.measurement_status = 'fail' THEN 1 END) as failure_count
        FROM quality_checkpoints qc
        LEFT JOIN quality_measurements qm ON qc.id = qm.checkpoint_id
            AND qm.measurement_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY qc.id
        ORDER BY qc.production_line, qc.checkpoint_name
    ";
    $checkpoints = $db->query($checkpoints_query)->fetchAll(PDO::FETCH_ASSOC);

    // Get production lines for dropdown
    $lines_query = "
        SELECT DISTINCT line_shift FROM daily_performance
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY line_shift
    ";
    $production_lines = $db->query($lines_query)->fetchAll(PDO::FETCH_COLUMN);

    // Get recent quality measurements
    $recent_measurements_query = "
        SELECT
            qm.*,
            qc.checkpoint_name,
            qc.production_line,
            dp.line_shift
        FROM quality_measurements qm
        JOIN quality_checkpoints qc ON qm.checkpoint_id = qc.id
        LEFT JOIN daily_performance dp ON qm.daily_performance_id = dp.id
        ORDER BY qm.measurement_time DESC
        LIMIT 10
    ";
    $recent_measurements = $db->query($recent_measurements_query)->fetchAll(PDO::FETCH_ASSOC);

    // Calculate quality statistics
    $stats_query = "
        SELECT
            COUNT(*) as total_measurements,
            COUNT(CASE WHEN measurement_status = 'pass' THEN 1 END) as passes,
            COUNT(CASE WHEN measurement_status = 'fail' THEN 1 END) as failures,
            COUNT(CASE WHEN measurement_status = 'warning' THEN 1 END) as warnings,
            SUM(defect_count) as total_defects,
            AVG(measurement_value) as avg_measurement
        FROM quality_measurements
        WHERE measurement_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";
    $quality_stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);

    // Get today's quality data
    $today_stats_query = "
        SELECT
            COUNT(*) as today_measurements,
            COUNT(CASE WHEN measurement_status = 'fail' THEN 1 END) as today_failures,
            SUM(defect_count) as today_defects
        FROM quality_measurements
        WHERE DATE(measurement_time) = CURDATE()
    ";
    $today_stats = $db->query($today_stats_query)->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Quality Control Data Error: " . $e->getMessage());
    $checkpoints = [];
    $production_lines = [];
    $recent_measurements = [];
    $quality_stats = [];
    $today_stats = [];
}

// Calculate quality metrics
$total_measurements = $quality_stats['total_measurements'] ?? 0;
$pass_rate = $total_measurements > 0 ? (($quality_stats['passes'] ?? 0) / $total_measurements) * 100 : 0;
$defect_rate = $total_measurements > 0 ? (($quality_stats['total_defects'] ?? 0) / $total_measurements) : 0;
$today_quality_rate = ($today_stats['today_measurements'] ?? 0) > 0 ?
    ((($today_stats['today_measurements'] - ($today_stats['today_failures'] ?? 0)) / $today_stats['today_measurements']) * 100) : 100;

// Generate HTML with offline assets
$asset_manager = $GLOBALS['asset_manager'];
echo $asset_manager->generateHTMLHeader("Quality Control System - Offline");
echo $asset_manager->getOfflineFontCSS();
?>

<style>
/* Quality Control Specific Styles */
.quality-header {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.quality-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.quality-stat-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.quality-stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.quality-stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.checkpoint-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.checkpoint-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.checkpoint-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #e74c3c 0%, #c0392b 100%);
}

.checkpoint-card.incoming::before {
    background: linear-gradient(90deg, #3498db 0%, #2980b9 100%);
}

.checkpoint-card.in_process::before {
    background: linear-gradient(90deg, #f39c12 0%, #e67e22 100%);
}

.checkpoint-card.final::before {
    background: linear-gradient(90deg, #27ae60 0%, #229954 100%);
}

.checkpoint-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.checkpoint-name {
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.checkpoint-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.checkpoint-line {
    background: #f8f9fa;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.85rem;
    color: #6c757d;
}

.checkpoint-type {
    background: #e74c3c;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.measurement-form {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
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
    border-color: #e74c3c;
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
}

.status-pass { color: #27ae60; font-weight: 600; }
.status-fail { color: #e74c3c; font-weight: 600; }
.status-warning { color: #f39c12; font-weight: 600; }

.measurement-list {
    max-height: 500px;
    overflow-y: auto;
}

.measurement-item {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.measurement-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.measurement-info {
    flex: 1;
}

.measurement-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.measurement-checkpoint {
    font-weight: 600;
    color: #2c3e50;
}

.measurement-time {
    font-size: 0.85rem;
    color: #6c757d;
}

.measurement-details {
    display: flex;
    gap: 1rem;
    font-size: 0.9rem;
    color: #6c757d;
}

.measurement-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: #e74c3c;
    color: white;
}

.btn-primary:hover {
    background: #c0392b;
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

.btn-info {
    background: #3498db;
    color: white;
}

.btn-info:hover {
    background: #2980b9;
}

.btn-outline {
    background: transparent;
    border: 1px solid #dee2e6;
    color: #6c757d;
}

.btn-outline:hover {
    background: #f8f9fa;
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
    margin: 5% auto;
    padding: 2rem;
    border-radius: 10px;
    max-width: 600px;
    max-height: 80vh;
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
    color: #e74c3c;
    border-bottom-color: #e74c3c;
    background: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

@media (max-width: 768px) {
    .quality-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .checkpoint-grid {
        grid-template-columns: 1fr;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .measurement-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .measurement-details {
        flex-direction: column;
        gap: 0.25rem;
    }
}
</style>

<!-- Quality Header -->
<div class="quality-header">
    <div class="container-fluid">
        <h1 class="text-center mb-0">
            <span style="margin-right: 15px;">🔍</span>Quality Control System
        </h1>
        <p class="text-center mb-0" style="opacity: 0.9;">
            Integrated Quality Management for Production Lines • Offline Mode
        </p>
    </div>
</div>

<!-- Quality Statistics -->
<div class="container-fluid">
    <div class="quality-stats-grid">
        <div class="quality-stat-card">
            <div class="quality-stat-value"><?= number_format($pass_rate, 1) ?>%</div>
            <div class="quality-stat-label">Pass Rate (30 Days)</div>
        </div>
        <div class="quality-stat-card">
            <div class="quality-stat-value"><?= number_format($defect_rate, 2) ?></div>
            <div class="quality-stat-label">Avg Defects/Check</div>
        </div>
        <div class="quality-stat-card">
            <div class="quality-stat-value"><?= number_format($today_quality_rate, 1) ?>%</div>
            <div class="quality-stat-label">Today's Quality Rate</div>
        </div>
        <div class="quality-stat-card">
            <div class="quality-stat-value"><?= $today_stats['today_defects'] ?? 0 ?></div>
            <div class="quality-stat-label">Today's Defects</div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <button class="tab-btn active" onclick="showTab('checkpoints')">
            📋 Quality Checkpoints
        </button>
        <button class="tab-btn" onclick="showTab('measurements')">
            📊 Recent Measurements
        </button>
        <button class="tab-btn" onclick="showTab('record')">
            ✏️ Record Measurement
        </button>
        <button class="tab-btn" onclick="showTab('analytics')">
            📈 Quality Analytics
        </button>
    </div>

    <!-- Checkpoints Tab -->
    <div id="checkpoints-tab" class="tab-content active">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Quality Checkpoints</h2>
            <button class="btn btn-primary btn-sm" onclick="showAddCheckpointModal()">
                ➕ Add Checkpoint
            </button>
        </div>

        <div class="checkpoint-grid">
            <?php if (empty($checkpoints)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                        <h4>No Quality Checkpoints Configured</h4>
                        <p class="text-muted">Click "Add Checkpoint" to create your first quality checkpoint.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($checkpoints as $checkpoint): ?>
                    <div class="checkpoint-card <?= $checkpoint['checkpoint_type'] ?>">
                        <h5 class="checkpoint-name"><?= htmlspecialchars($checkpoint['checkpoint_name']) ?></h5>

                        <div class="checkpoint-meta">
                            <span class="checkpoint-line"><?= htmlspecialchars($checkpoint['production_line']) ?></span>
                            <span class="checkpoint-type"><?= htmlspecialchars($checkpoint['checkpoint_type']) ?></span>
                        </div>

                        <?php if ($checkpoint['measurement_count'] > 0): ?>
                            <div class="mb-3">
                                <small class="text-muted">
                                    <?= $checkpoint['measurement_count'] ?> measurements •
                                    Pass rate: <?= number_format((($checkpoint['measurement_count'] - $checkpoint['failure_count']) / $checkpoint['measurement_count']) * 100, 1) ?>%
                                </small>
                            </div>
                        <?php endif; ?>

                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">Target:</small>
                                <div><?= htmlspecialchars($checkpoint['target_value']) ?> <?= htmlspecialchars($checkpoint['measurement_unit']) ?></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Frequency:</small>
                                <div><?= htmlspecialchars($checkpoint['frequency']) ?></div>
                            </div>
                        </div>

                        <div class="measurement-form">
                            <form onsubmit="recordMeasurement(<?= $checkpoint['id'] ?>, this); return false;">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Measurement Value</label>
                                        <input type="number" step="0.01" class="form-input" name="measurement_value" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="measurement_status" required>
                                            <option value="pass">✅ Pass</option>
                                            <option value="fail">❌ Fail</option>
                                            <option value="warning">⚠️ Warning</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Defects</label>
                                        <input type="number" class="form-input" name="defect_count" value="0" min="0">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Operator</label>
                                        <input type="text" class="form-input" name="operator" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Inspector</label>
                                        <input type="text" class="form-input" name="inspector" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">📝 Record Measurement</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Measurements Tab -->
    <div id="measurements-tab" class="tab-content">
        <h2 class="mb-4">Recent Quality Measurements</h2>

        <div class="mb-4">
            <div class="row">
                <div class="col-md-4">
                    <select class="form-select" id="filterLine" onchange="filterMeasurements()">
                        <option value="">All Production Lines</option>
                        <?php foreach ($production_lines as $line): ?>
                            <option value="<?= htmlspecialchars($line) ?>"><?= htmlspecialchars($line) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="filterStatus" onchange="filterMeasurements()">
                        <option value="">All Status</option>
                        <option value="pass">Pass</option>
                        <option value="fail">Fail</option>
                        <option value="warning">Warning</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-info btn-sm w-100" onclick="refreshMeasurements()">
                        🔄 Refresh
                    </button>
                </div>
            </div>
        </div>

        <div class="measurement-list" id="measurementsList">
            <?php if (empty($recent_measurements)): ?>
                <div class="text-center py-5">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📊</div>
                    <h4>No Quality Measurements Yet</h4>
                    <p class="text-muted">Start recording measurements from the checkpoints tab.</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_measurements as $measurement): ?>
                    <div class="measurement-item" data-line="<?= htmlspecialchars($measurement['production_line'] ?? '') ?>" data-status="<?= $measurement['measurement_status'] ?>">
                        <div class="measurement-info">
                            <div class="measurement-header">
                                <span class="measurement-checkpoint"><?= htmlspecialchars($measurement['checkpoint_name']) ?></span>
                                <span class="measurement-time"><?= date('M j, H:i', strtotime($measurement['measurement_time'])) ?></span>
                            </div>
                            <div class="measurement-details">
                                <span>📏 <?= $measurement['measurement_value'] ?> units</span>
                                <span>👤 <?= htmlspecialchars($measurement['inspector']) ?></span>
                                <span>🏭 <?= htmlspecialchars($measurement['production_line'] ?? 'N/A') ?></span>
                                <?php if ($measurement['defect_count'] > 0): ?>
                                    <span>⚠️ <?= $measurement['defect_count'] ?> defects</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="measurement-actions">
                            <span class="status-<?= $measurement['measurement_status'] ?>">
                                <?= ucfirst($measurement['measurement_status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Record Measurement Tab -->
    <div id="record-tab" class="tab-content">
        <h2 class="mb-4">Quick Measurement Recording</h2>

        <div class="row">
            <div class="col-md-8">
                <div class="metric-card">
                    <h5 class="mb-4">Record New Measurement</h5>

                    <form id="quickMeasurementForm" onsubmit="recordQuickMeasurement(this); return false;">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Checkpoint</label>
                                <select class="form-select" name="checkpoint_id" required>
                                    <option value="">Select Checkpoint...</option>
                                    <?php foreach ($checkpoints as $checkpoint): ?>
                                        <option value="<?= $checkpoint['id'] ?>">
                                            <?= htmlspecialchars($checkpoint['checkpoint_name']) ?> - <?= htmlspecialchars($checkpoint['production_line']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Measurement Value</label>
                                <input type="number" step="0.01" class="form-input" name="measurement_value" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="measurement_status" required>
                                    <option value="pass">✅ Pass</option>
                                    <option value="fail">❌ Fail</option>
                                    <option value="warning">⚠️ Warning</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Operator</label>
                                <input type="text" class="form-input" name="operator" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Inspector</label>
                                <input type="text" class="form-input" name="inspector" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Defect Count</label>
                                <input type="number" class="form-input" name="defect_count" value="0" min="0">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Batch Number</label>
                                <input type="text" class="form-input" name="batch_number">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Defect Type</label>
                                <input type="text" class="form-input" name="defect_type">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Notes</label>
                            <textarea class="form-textarea" name="notes" rows="3"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            📝 Record Measurement
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-4">
                <div class="metric-card">
                    <h5 class="mb-3">Quick Stats</h5>
                    <div class="mb-3">
                        <small class="text-muted">Today's Measurements</small>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #e74c3c;">
                            <?= $today_stats['today_measurements'] ?? 0 ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Today's Failures</small>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #e74c3c;">
                            <?= $today_stats['today_failures'] ?? 0 ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Defects Found</small>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #f39c12;">
                            <?= $today_stats['today_defects'] ?? 0 ?>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button class="btn btn-info btn-sm" onclick="generateQualityReport()">
                            📊 Generate Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quality Analytics Tab -->
    <div id="analytics-tab" class="tab-content">
        <h2 class="mb-4">Quality Analytics</h2>

        <div class="row">
            <div class="col-md-6">
                <div class="metric-card">
                    <h5 class="mb-3">Quality Performance</h5>
                    <canvas id="qualityChart" width="400" height="300"></canvas>
                    <div class="mt-3 text-center">
                        <small class="text-muted">Pass/Fail ratio over last 30 days</small>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="metric-card">
                    <h5 class="mb-3">Defect Trends</h5>
                    <canvas id="defectChart" width="400" height="300"></canvas>
                    <div class="mt-3 text-center">
                        <small class="text-muted">Defect count trends over time</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="metric-card">
                    <h5 class="mb-3">Quality Metrics Summary</h5>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-success"><?= number_format($pass_rate, 1) ?>%</h3>
                                <small class="text-muted">Overall Pass Rate</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-warning"><?= number_format($defect_rate, 2) ?></h3>
                                <small class="text-muted">Avg Defects per Check</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-info"><?= count($checkpoints) ?></h3>
                                <small class="text-muted">Active Checkpoints</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-primary"><?= $total_measurements ?></h3>
                                <small class="text-muted">Total Measurements</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Checkpoint Modal -->
<div id="addCheckpointModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal('addCheckpointModal')">✕</button>
        <h3 class="mb-4">Add Quality Checkpoint</h3>

        <form id="checkpointForm" onsubmit="addCheckpoint(this); return false;">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Checkpoint Name</label>
                    <input type="text" class="form-input" name="checkpoint_name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Production Line</label>
                    <input type="text" class="form-input" name="production_line" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Checkpoint Type</label>
                    <select class="form-select" name="checkpoint_type" required>
                        <option value="incoming">Incoming</option>
                        <option value="in_process">In Process</option>
                        <option value="final">Final</option>
                        <option value="outgoing">Outgoing</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Frequency</label>
                    <select class="form-select" name="frequency" required>
                        <option value="hourly">Hourly</option>
                        <option value="shift">Per Shift</option>
                        <option value="daily">Daily</option>
                        <option value="batch">Per Batch</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Quality Standard</label>
                    <input type="text" class="form-input" name="quality_standard">
                </div>
                <div class="form-group">
                    <label class="form-label">Measurement Unit</label>
                    <input type="text" class="form-input" name="measurement_unit" placeholder="mm, %, units">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Target Value</label>
                    <input type="number" step="0.01" class="form-input" name="target_value">
                </div>
                <div class="form-group">
                    <label class="form-label">Tolerance Level</label>
                    <input type="number" step="0.01" class="form-input" name="tolerance_level">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Min Acceptable</label>
                    <input type="number" step="0.01" class="form-input" name="min_acceptable">
                </div>
                <div class="form-group">
                    <label class="form-label">Max Acceptable</label>
                    <input type="number" step="0.01" class="form-input" name="max_acceptable">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" name="is_active" checked>
                    Active
                </label>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">➕ Add Checkpoint</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('addCheckpointModal')">Cancel</button>
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
        drawQualityCharts();
    }
}

// Modal functions
function showAddCheckpointModal() {
    document.getElementById('addCheckpointModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Quality measurement functions
function recordMeasurement(checkpointId, form) {
    const formData = new FormData(form);
    formData.append('action', 'record_measurement');
    formData.append('checkpoint_id', checkpointId);

    fetch('quality_control_offline.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Measurement recorded successfully!');
            form.reset();
            refreshMeasurements();
        } else {
            alert('❌ ' + (data.error || 'Failed to record measurement'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Network error. Please try again.');
    });
}

function recordQuickMeasurement(form) {
    const formData = new FormData(form);
    formData.append('action', 'record_measurement');

    fetch('quality_control_offline.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Measurement recorded successfully!');
            form.reset();
            location.reload();
        } else {
            alert('❌ ' + (data.error || 'Failed to record measurement'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Network error. Please try again.');
    });
}

function addCheckpoint(form) {
    const formData = new FormData(form);
    formData.append('action', 'add_checkpoint');

    fetch('quality_control_offline.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Checkpoint added successfully!');
            closeModal('addCheckpointModal');
            form.reset();
            location.reload();
        } else {
            alert('❌ ' + (data.error || 'Failed to add checkpoint'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Network error. Please try again.');
    });
}

function filterMeasurements() {
    const line = document.getElementById('filterLine').value;
    const status = document.getElementById('filterStatus').value;

    const items = document.querySelectorAll('.measurement-item');
    items.forEach(item => {
        const itemLine = item.dataset.line.toLowerCase();
        const itemStatus = item.dataset.status;

        let show = true;

        if (line && itemLine !== line.toLowerCase()) {
            show = false;
        }

        if (status && itemStatus !== status) {
            show = false;
        }

        item.style.display = show ? 'flex' : 'none';
    });
}

function refreshMeasurements() {
    location.reload();
}

function generateQualityReport() {
    alert('📊 Quality report generation would be implemented here with export to PDF/CSV options');
}

function drawQualityCharts() {
    // Simple chart implementation using canvas
    const qualityCanvas = document.getElementById('qualityChart');
    const defectCanvas = document.getElementById('defectChart');

    if (qualityCanvas && defectCanvas) {
        drawSimpleChart(qualityCanvas, 'Pass/Fail Ratio', [
            { label: 'Pass', value: <?= $quality_stats['passes'] ?? 0 ?>, color: '#27ae60' },
            { label: 'Fail', value: <?= $quality_stats['failures'] ?? 0 ?>, color: '#e74c3c' }
        ]);

        drawSimpleChart(defectCanvas, 'Defect Analysis', [
            { label: 'Today', value: <?= $today_stats['today_defects'] ?? 0 ?>, color: '#f39c12' },
            { label: 'This Week', value: <?= ($quality_stats['total_defects'] ?? 0) * 0.25, color: '#e67e22' },
            { label: 'This Month', value: <?= $quality_stats['total_defects'] ?? 0 ?>, color: '#d35400' }
        ]);
    }
}

function drawSimpleChart(canvas, title, data) {
    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;

    // Clear canvas
    ctx.clearRect(0, 0, width, height);

    // Calculate totals
    const total = data.reduce((sum, item) => sum + item.value, 0);

    if (total === 0) {
        ctx.fillStyle = '#6c757d';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('No data available', width / 2, height / 2);
        return;
    }

    // Draw pie chart
    let currentAngle = -Math.PI / 2;
    const centerX = width / 2;
    const centerY = height / 2;
    const radius = Math.min(width, height) / 3;

    data.forEach(item => {
        const sliceAngle = (item.value / total) * 2 * Math.PI;

        // Draw slice
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
        ctx.lineTo(centerX, centerY);
        ctx.fillStyle = item.color;
        ctx.fill();

        // Draw label
        const labelAngle = currentAngle + sliceAngle / 2;
        const labelX = centerX + Math.cos(labelAngle) * (radius * 0.7);
        const labelY = centerY + Math.sin(labelAngle) * (radius * 0.7);

        ctx.fillStyle = 'white';
        ctx.font = 'bold 12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(`${Math.round((item.value / total) * 100)}%`, labelX, labelY);

        currentAngle += sliceAngle;
    });

    // Draw title
    ctx.fillStyle = '#2c3e50';
    ctx.font = 'bold 16px Arial';
    ctx.textAlign = 'center';
    ctx.fillText(title, width / 2, 20);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('Quality Control System initialized');

    // Auto-refresh every 5 minutes
    setInterval(() => {
        if (document.getElementById('measurements-tab').classList.contains('active')) {
            refreshMeasurements();
        }
    }, 300000);
});
</script>

<?php
echo $asset_manager->generateHTMLFooter();
?>