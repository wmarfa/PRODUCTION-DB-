<?php
// mobile_floor_manager_offline.php - Mobile-optimized offline production floor manager for LAN

require_once "config.php";
require_once "assets.php";

$database = Database::getInstance();
$db = $database->getConnection();

// Get current date and shift
$current_date = date('Y-m-d');
$current_shift = get_current_shift();

function get_current_shift() {
    $hour = (int)date('H');
    if ($hour >= 6 && $hour < 14) return 'DS';
    if ($hour >= 14 && $hour < 22) return 'NS';
    return 'LS';
}

// Get production data for current shift
try {
    $production_query = "
        SELECT
            dp.id,
            dp.line_shift,
            dp.leader,
            dp.mp,
            dp.absent,
            dp.separated_mp,
            dp.plan,
            dp.no_ot_mp,
            dp.ot_mp,
            dp.ot_hours,
            (SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id) as actual_output,
            dp.created_at as last_update
        FROM daily_performance dp
        WHERE dp.date = :current_date
        ORDER BY dp.line_shift
    ";

    $stmt = $db->prepare($production_query);
    $stmt->execute(['current_date' => $current_date]);
    $production_lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate metrics for each line
    foreach ($production_lines as &$line) {
        $used_mhr = PerformanceCalculator::calculateUsedMHR($line['no_ot_mp'], $line['ot_mp'], $line['ot_hours']);
        $line['efficiency'] = PerformanceCalculator::calculateEfficiency($line['actual_output'], $used_mhr);
        $line['plan_completion'] = PerformanceCalculator::calculatePlanCompletion($line['actual_output'], $line['plan']);
        $line['manning_rate'] = ($line['mp'] > 0) ? (($line['mp'] - $line['absent'] - $line['separated_mp']) / $line['mp']) * 100 : 0;
        $line['status'] = $line['plan_completion'] >= 90 ? 'good' : ($line['plan_completion'] >= 70 ? 'warning' : 'critical');
    }

    // Get active alerts for mobile
    $alerts_query = "
        SELECT alert_type, title, description, production_line, created_at, severity_score
        FROM production_alerts
        WHERE status IN ('active', 'escalated')
        ORDER BY severity_score DESC, created_at DESC
        LIMIT 5
    ";
    $active_alerts = $db->query($alerts_query)->fetchAll(PDO::FETCH_ASSOC);

    // Get quality checkpoints for quick access
    $checkpoints_query = "
        SELECT id, checkpoint_name, production_line, checkpoint_type, frequency
        FROM quality_checkpoints
        WHERE is_active = 1
        ORDER BY production_line, checkpoint_name
        LIMIT 10
    ";
    $quality_checkpoints = $db->query($checkpoints_query)->fetchAll(PDO::FETCH_ASSOC);

    // Get products for quick data entry
    $products_query = "
        SELECT id, product_code, circuit, mhr
        FROM products
        ORDER BY product_code
        LIMIT 20
    ";
    $products = $db->query($products_query)->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log("Mobile Floor Manager Error: " . $e->getMessage());
    $production_lines = [];
    $active_alerts = [];
    $quality_checkpoints = [];
    $products = [];
}

// Generate HTML with mobile-optimized assets
$asset_manager = $GLOBALS['asset_manager'];
echo $asset_manager->generateHTMLHeader("Mobile Floor Manager - Offline");
echo $asset_manager->getOfflineFontCSS();
?>

<style>
/* Mobile-First Styles */
.mobile-container {
    max-width: 100%;
    margin: 0 auto;
    padding: 10px;
}

.mobile-header {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    padding: 15px;
    text-align: center;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.mobile-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}

.mobile-subtitle {
    font-size: 0.8rem;
    opacity: 0.9;
    margin: 5px 0 0 0;
}

.mobile-nav {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.mobile-nav::-webkit-scrollbar {
    display: none;
}

.nav-tab {
    flex: 1;
    min-width: 120px;
    padding: 12px;
    text-align: center;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 500;
    color: #6c757d;
    transition: all 0.3s ease;
}

.nav-tab.active {
    color: #2c3e50;
    border-bottom-color: #2c3e50;
    background: white;
}

.nav-tab:hover {
    color: #2c3e50;
    background: rgba(255,255,255,0.5);
}

.mobile-content {
    padding: 15px 0;
}

.mobile-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.mobile-card:hover {
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}

.mobile-status-bar {
    background: rgba(255,255,255,0.1);
    padding: 8px;
    border-radius: 8px;
    margin-top: 10px;
    backdrop-filter: blur(10px);
    display: flex;
    justify-content: space-around;
    flex-wrap: wrap;
    gap: 10px;
}

.mobile-status-item {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
}

.mobile-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}

.mobile-dot-green { background-color: #28a745; }
.mobile-dot-yellow { background-color: #ffc107; }
.mobile-dot-red { background-color: #dc3545; }

.line-card-mobile {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.line-card-mobile::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
}

.line-card-mobile.status-good::before {
    background-color: #28a745;
}

.line-card-mobile.status-warning::before {
    background-color: #ffc107;
}

.line-card-mobile.status-critical::before {
    background-color: #dc3545;
}

.line-header-mobile {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.line-name-mobile {
    font-weight: 600;
    font-size: 1.1rem;
    color: #2c3e50;
    margin: 0;
}

.line-status-mobile {
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: white;
}

.status-good { background-color: #28a745; }
.status-warning { background-color: #ffc107; color: #000; }
.status-critical { background-color: #dc3545; }

.mobile-metrics {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 12px;
}

.mobile-metric {
    text-align: center;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 8px;
}

.mobile-metric-value {
    font-size: 1rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 4px;
}

.mobile-metric-label {
    font-size: 0.7rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.mobile-progress {
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.mobile-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #2c3e50 0%, #34495e 100%);
    border-radius: 10px;
    transition: width 0.6s ease;
}

.mobile-alert {
    background: white;
    border-left: 4px solid #dc3545;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 0 8px 8px 0;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.mobile-alert.warning {
    border-left-color: #ffc107;
}

.mobile-alert.info {
    border-left-color: #0dcaf0;
}

.mobile-alert-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.mobile-alert-title {
    font-weight: 600;
    font-size: 0.9rem;
    margin: 0;
}

.mobile-alert-time {
    font-size: 0.75rem;
    color: #6c757d;
}

.mobile-btn {
    display: block;
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    transition: all 0.3s ease;
    margin-bottom: 10px;
}

.mobile-btn-primary {
    background: #2c3e50;
    color: white;
}

.mobile-btn-success {
    background: #28a745;
    color: white;
}

.mobile-btn-warning {
    background: #ffc107;
    color: #000;
}

.mobile-btn-info {
    background: #0dcaf0;
    color: #000;
}

.mobile-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

.mobile-btn:active {
    transform: translateY(0);
}

.quick-action-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}

.floating-refresh {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #2c3e50;
    color: white;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    z-index: 1000;
    transition: all 0.3s ease;
}

.floating-refresh:hover {
    background: #34495e;
    transform: scale(1.1);
}

.offline-banner-mobile {
    background: #198754;
    color: white;
    padding: 10px;
    text-align: center;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Larger screens */
@media (min-width: 768px) {
    .mobile-container {
        max-width: 768px;
    }

    .quick-action-grid {
        grid-template-columns: repeat(4, 1fr);
    }

    .mobile-metrics {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* Very small screens */
@media (max-width: 360px) {
    .mobile-title {
        font-size: 1.1rem;
    }

    .nav-tab {
        min-width: 100px;
        padding: 10px 8px;
        font-size: 0.85rem;
    }
}

/* Touch-friendly interactions */
.mobile-card, .mobile-alert, .line-card-mobile {
    -webkit-tap-highlight-color: rgba(44, 62, 80, 0.1);
}

/* Loading animation */
.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #2c3e50;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Pull to refresh indicator */
.pull-to-refresh {
    position: absolute;
    top: -60px;
    left: 0;
    right: 0;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    transition: transform 0.3s ease;
}

.pull-to-refresh.active {
    transform: translateY(60px);
}
</style>

<!-- Pull to Refresh Indicator -->
<div class="pull-to-refresh" id="pullToRefresh">
    <div style="display: flex; align-items: center;">
        <div class="loading-spinner" style="margin-right: 10px;"></div>
        <span>Refreshing...</span>
    </div>
</div>

<!-- Offline Banner -->
<div class="offline-banner-mobile">
    🟢 OFFLINE MODE • Local Network • <span id="currentTime"></span>
</div>

<!-- Mobile Header -->
<div class="mobile-header">
    <h1 class="mobile-title">
        <span style="margin-right: 8px;">🏭</span>Production Floor
    </h1>
    <p class="mobile-subtitle">Mobile Manager • <?= $current_shift ?> Shift</p>

    <div class="mobile-status-bar">
        <div class="mobile-status-item">
            <span class="mobile-dot mobile-dot-green"></span>
            <span><?= count(array_filter($production_lines, fn($l) => $l['status'] == 'good')) ?> Good</span>
        </div>
        <div class="mobile-status-item">
            <span class="mobile-dot mobile-dot-yellow"></span>
            <span><?= count(array_filter($production_lines, fn($l) => $l['status'] == 'warning')) ?> Warning</span>
        </div>
        <div class="mobile-status-item">
            <span class="mobile-dot mobile-dot-red"></span>
            <span><?= count(array_filter($production_lines, fn($l) => $l['status'] == 'critical')) ?> Critical</span>
        </div>
    </div>
</div>

<!-- Mobile Navigation -->
<div class="mobile-nav">
    <button class="nav-tab active" onclick="showTab('production')">
        <div>📊</div>
        <div>Production</div>
    </button>
    <button class="nav-tab" onclick="showTab('alerts')">
        <div>🔔</div>
        <div>Alerts</div>
        <span style="background: #dc3545; color: white; border-radius: 10px; padding: 2px 6px; font-size: 0.7rem; position: absolute; top: 5px; right: 10px;"><?= count($active_alerts) ?></span>
    </button>
    <button class="nav-tab" onclick="showTab('quality')">
        <div>✅</div>
        <div>Quality</div>
    </button>
    <button class="nav-tab" onclick="showTab('actions')">
        <div>⚡</div>
        <div>Actions</div>
    </button>
</div>

<!-- Mobile Content -->
<div class="mobile-container">
    <div class="mobile-content">

        <!-- Production Tab -->
        <div id="production-tab" class="tab-content">
            <?php if (empty($production_lines)): ?>
                <div class="mobile-card">
                    <div style="text-align: center; padding: 30px 20px;">
                        <div style="font-size: 3rem; margin-bottom: 15px;">📋</div>
                        <h5>No Production Data</h5>
                        <p style="color: #6c757d; margin: 10px 0;">No production data recorded for today.</p>
                        <a href="entry_form.php" class="mobile-btn mobile-btn-primary">Enter Production Data</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($production_lines as $line): ?>
                    <div class="line-card-mobile status-<?= $line['status'] ?>">
                        <div class="line-header-mobile">
                            <h5 class="line-name-mobile"><?= htmlspecialchars($line['line_shift']) ?></h5>
                            <span class="line-status-mobile status-<?= $line['status'] ?>">
                                <?= ucfirst($line['status']) ?>
                            </span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <small style="color: #6c757d;">Leader: <?= htmlspecialchars($line['leader']) ?></small>
                            <small style="color: #6c757d;"><?= date('H:i', strtotime($line['last_update'])) ?></small>
                        </div>

                        <div class="mobile-progress">
                            <div class="mobile-progress-bar" style="width: <?= min($line['plan_completion'], 100) ?>%"></div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <small style="color: #6c757d;">Progress</small>
                            <strong style="color: #2c3e50;"><?= $line['actual_output'] ?>/<?= $line['plan'] ?></strong>
                        </div>

                        <div class="mobile-metrics">
                            <div class="mobile-metric">
                                <div class="mobile-metric-value"><?= number_format($line['plan_completion'], 0) ?>%</div>
                                <div class="mobile-metric-label">Plan Comp.</div>
                            </div>
                            <div class="mobile-metric">
                                <div class="mobile-metric-value"><?= number_format($line['efficiency'], 0) ?>%</div>
                                <div class="mobile-metric-label">Efficiency</div>
                            </div>
                            <div class="mobile-metric">
                                <div class="mobile-metric-value"><?= $line['mp'] - $line['absent'] ?></div>
                                <div class="mobile-metric-label">Manning</div>
                            </div>
                            <div class="mobile-metric">
                                <div class="mobile-metric-value"><?= number_format($line['manning_rate'], 0) ?>%</div>
                                <div class="mobile-metric-label">Coverage</div>
                            </div>
                        </div>

                        <div style="margin-top: 12px; display: flex; gap: 8px;">
                            <button class="mobile-btn mobile-btn-primary" style="margin: 0; padding: 8px;" onclick="updateProduction(<?= $line['id'] ?>)">
                                📝 Update
                            </button>
                            <button class="mobile-btn mobile-btn-warning" style="margin: 0; padding: 8px;" onclick="reportIssue('<?= htmlspecialchars($line['line_shift']) ?>')">
                                ⚠️ Report Issue
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Alerts Tab -->
        <div id="alerts-tab" class="tab-content" style="display: none;">
            <?php if (empty($active_alerts)): ?>
                <div class="mobile-card">
                    <div style="text-align: center; padding: 30px 20px;">
                        <div style="font-size: 3rem; margin-bottom: 15px;">✅</div>
                        <h5>All Clear</h5>
                        <p style="color: #6c757d;">No active alerts at this time.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($active_alerts as $alert): ?>
                    <div class="mobile-alert <?= $alert['alert_type'] ?>">
                        <div class="mobile-alert-header">
                            <h6 class="mobile-alert-title"><?= htmlspecialchars($alert['title']) ?></h6>
                            <small class="mobile-alert-time"><?= date('H:i', strtotime($alert['created_at'])) ?></small>
                        </div>
                        <?php if ($alert['description']): ?>
                            <p style="margin-bottom: 8px; font-size: 0.85rem; color: #6c757d;"><?= htmlspecialchars($alert['description']) ?></p>
                        <?php endif; ?>
                        <?php if ($alert['production_line']): ?>
                            <small style="color: #6c757d;">
                                <span style="margin-right: 5px;">🏭</span><?= htmlspecialchars($alert['production_line']) ?>
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <a href="alert_system.php" class="mobile-btn mobile-btn-info">📋 View All Alerts</a>
        </div>

        <!-- Quality Tab -->
        <div id="quality-tab" class="tab-content" style="display: none;">
            <div class="mobile-card">
                <h5 style="margin-bottom: 15px;">📊 Quick Quality Checks</h5>

                <?php if (empty($quality_checkpoints)): ?>
                    <p style="color: #6c757d; text-align: center;">No quality checkpoints configured.</p>
                <?php else: ?>
                    <?php foreach ($quality_checkpoints as $checkpoint): ?>
                        <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong><?= htmlspecialchars($checkpoint['checkpoint_name']) ?></strong>
                                    <br>
                                    <small style="color: #6c757d;"><?= htmlspecialchars($checkpoint['production_line']) ?> • <?= htmlspecialchars($checkpoint['checkpoint_type']) ?></small>
                                </div>
                                <button class="mobile-btn mobile-btn-success" style="margin: 0; padding: 8px 16px;" onclick="recordQuality(<?= $checkpoint['id'] ?>)">
                                    ✓ Record
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mobile-card">
                <h5 style="margin-bottom: 15px;">🏭 Quick Product Entry</h5>
                <select id="quickProductSelect" style="width: 100%; padding: 10px; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 10px;">
                    <option value="">Select Product...</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['product_code']) ?> - <?= $product['circuit'] ?> circuits</option>
                    <?php endforeach; ?>
                </select>
                <input type="number" id="quickQuantity" placeholder="Quantity" style="width: 100%; padding: 10px; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 10px;">
                <button class="mobile-btn mobile-btn-primary" onclick="quickProductionEntry()">
                    📊 Quick Entry
                </button>
            </div>
        </div>

        <!-- Actions Tab -->
        <div id="actions-tab" class="tab-content" style="display: none;">
            <div class="quick-action-grid">
                <a href="entry_form.php" class="mobile-btn mobile-btn-primary">
                    📝<br>Production Data
                </a>
                <a href="analytics_engine.php" class="mobile-btn mobile-btn-info">
                    📊<br>Analytics
                </a>
                <a href="production_scheduler.php" class="mobile-btn mobile-btn-warning">
                    📅<br>Schedule
                </a>
                <button class="mobile-btn mobile-btn-success" onclick="startMeeting()">
                    👥<br>Shift Meeting
                </button>
                <button class="mobile-btn mobile-btn-primary" onclick="requestSupport()">
                    🆘<br>Request Support
                </button>
                <button class="mobile-btn mobile-btn-warning" onclick="emergencyStop()">
                    🛑<br>Emergency Stop
                </button>
            </div>

            <div class="mobile-card">
                <h5 style="margin-bottom: 15px;">⚡ Quick Actions</h5>
                <div style="display: grid; gap: 10px;">
                    <button class="mobile-btn mobile-btn-info" onclick="refreshData()">🔄 Refresh Data</button>
                    <button class="mobile-btn mobile-btn-primary" onclick="showCamera()">📷 Photo Report</button>
                    <button class="mobile-btn mobile-btn-warning" onclick="voiceNote()">🎤 Voice Note</button>
                    <a href="enhanced_dashboard_offline.php" class="mobile-btn mobile-btn-success">🖥️ Full Dashboard</a>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Floating Refresh Button -->
<button class="floating-refresh" onclick="refreshData()">🔄</button>

<script>
// Mobile-specific JavaScript
let currentTab = 'production';
let pullStartY = 0;
let isPulling = false;

function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });

    // Remove active class from all nav tabs
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
    });

    // Show selected tab
    document.getElementById(tabName + '-tab').style.display = 'block';

    // Add active class to clicked tab
    event.target.closest('.nav-tab').classList.add('active');

    currentTab = tabName;
}

function updateProduction(lineId) {
    const quantity = prompt('Enter production quantity:');
    if (quantity && !isNaN(quantity)) {
        // In a real implementation, this would submit to the server
        alert(`Production data updated for line ${lineId}: ${quantity} units`);
        setTimeout(() => location.reload(), 1000);
    }
}

function reportIssue(lineName) {
    const issue = prompt('Report issue for ' + lineName + ':');
    if (issue) {
        // In a real implementation, this would create an alert
        alert(`Issue reported for ${lineName}: ${issue}`);
        setTimeout(() => location.reload(), 1000);
    }
}

function recordQuality(checkpointId) {
    const result = confirm('Quality check result: PASS?');
    if (result !== null) {
        const status = result ? 'PASS' : 'FAIL';
        const notes = prompt('Add notes (optional):');

        // In a real implementation, this would submit to the server
        alert(`Quality check recorded: ${status}${notes ? ' - ' + notes : ''}`);
        setTimeout(() => location.reload(), 1000);
    }
}

function quickProductionEntry() {
    const productId = document.getElementById('quickProductSelect').value;
    const quantity = document.getElementById('quickQuantity').value;

    if (!productId || !quantity) {
        alert('Please select a product and enter quantity');
        return;
    }

    if (isNaN(quantity) || quantity <= 0) {
        alert('Please enter a valid quantity');
        return;
    }

    // In a real implementation, this would submit to the server
    alert(`Production entry recorded: ${quantity} units`);
    document.getElementById('quickProductSelect').value = '';
    document.getElementById('quickQuantity').value = '';
    setTimeout(() => location.reload(), 1000);
}

function startMeeting() {
    alert('Shift meeting check-in recorded at ' + new Date().toLocaleTimeString());
}

function requestSupport() {
    const type = prompt('Support needed: 1=Technical, 2=Quality, 3=Manpower');
    const description = prompt('Describe the issue:');

    if (type && description) {
        alert(`Support request sent: ${type} - ${description}`);
    }
}

function emergencyStop() {
    const confirm = window.confirm('EMERGENCY STOP: Are you sure? This will notify all supervisors.');
    if (confirm) {
        alert('🚨 EMERGENCY STOP ACTIVATED - All supervisors notified!');
    }
}

function showCamera() {
    alert('Camera feature would open here for photo documentation');
}

function voiceNote() {
    alert('Voice recording feature would be activated here');
}

function refreshData() {
    // Show loading state
    const refreshBtn = document.querySelector('.floating-refresh');
    refreshBtn.innerHTML = '<div class="loading-spinner"></div>';

    // Simulate data refresh
    setTimeout(() => {
        location.reload();
    }, 1000);
}

function updateTime() {
    document.getElementById('currentTime').textContent = new Date().toLocaleTimeString();
}

// Pull to refresh functionality
document.addEventListener('touchstart', function(e) {
    if (window.scrollY === 0) {
        pullStartY = e.touches[0].clientY;
        isPulling = true;
    }
});

document.addEventListener('touchmove', function(e) {
    if (isPulling) {
        const currentY = e.touches[0].clientY;
        const pullDistance = currentY - pullStartY;

        if (pullDistance > 0 && pullDistance < 100) {
            const pullToRefresh = document.getElementById('pullToRefresh');
            pullToRefresh.style.transform = `translateY(${pullDistance}px)`;
        }
    }
});

document.addEventListener('touchend', function(e) {
    if (isPulling) {
        const pullToRefresh = document.getElementById('pullToRefresh');
        const currentTransform = pullToRefresh.style.transform;
        const pullDistance = parseInt(currentTransform.replace(/[^\d-]/g, '')) || 0;

        if (pullDistance > 60) {
            // Trigger refresh
            pullToRefresh.classList.add('active');
            setTimeout(() => {
                refreshData();
                pullToRefresh.classList.remove('active');
                pullToRefresh.style.transform = 'translateY(0)';
            }, 1000);
        } else {
            // Reset position
            pullToRefresh.style.transform = 'translateY(0)';
        }

        isPulling = false;
    }
});

// Auto-refresh every 30 seconds
setInterval(updateTime, 1000);
setInterval(() => {
    if (document.visibilityState === 'visible') {
        console.log('Auto-refreshing data...');
        refreshData();
    }
}, 30000);

// Initialize
updateTime();
console.log('Mobile Floor Manager initialized');

// Service Worker registration for offline functionality
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function(e) {
        console.log('Service Worker registration failed:', e);
    });
}
</script>

<?php
echo $asset_manager->generateHTMLFooter();
?>