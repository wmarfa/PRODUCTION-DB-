<?php
// enhanced_dashboard_offline.php - Offline-ready production command center for LAN deployment

require_once "config.php";
require_once "assets.php";

$database = Database::getInstance();
$db = $database->getConnection();

// Get current date and time for real-time display
$current_datetime = date('Y-m-d H:i:s');
$current_date = date('Y-m-d');
$current_shift = get_current_shift();

function get_current_shift() {
    $hour = (int)date('H');
    if ($hour >= 6 && $hour < 14) return 'DS'; // Day Shift
    if ($hour >= 14 && $hour < 22) return 'NS'; // Night Shift
    return 'LS'; // Late Shift
}

// Get active production lines with real-time status
try {
    // Get today's production data for all lines
    $production_query = "
        SELECT
            dp.line_shift,
            dp.leader,
            dp.mp,
            dp.plan,
            dp.absent,
            dp.separated_mp,
            dp.no_ot_mp,
            dp.ot_mp,
            dp.ot_hours,
            (SELECT COALESCE(SUM(ap.assy_output), 0) FROM assy_performance ap WHERE ap.daily_performance_id = dp.id) as actual_output,
            (SELECT COALESCE(SUM(ap.assy_output * p.circuit), 0) FROM assy_performance ap JOIN products p ON ap.product_id = p.id WHERE ap.daily_performance_id = dp.id) as circuit_output,
            dp.created_at as last_update
        FROM daily_performance dp
        WHERE dp.date = :current_date
        ORDER BY dp.line_shift
    ";

    $stmt = $db->prepare($production_query);
    $stmt->execute(['current_date' => $current_date]);
    $production_lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate performance metrics for each line
    foreach ($production_lines as &$line) {
        $used_mhr = PerformanceCalculator::calculateUsedMHR($line['no_ot_mp'], $line['ot_mp'], $line['ot_hours']);
        $line['efficiency'] = PerformanceCalculator::calculateEfficiency($line['actual_output'], $used_mhr);
        $line['plan_completion'] = PerformanceCalculator::calculatePlanCompletion($line['actual_output'], $line['plan']);
        $line['cph'] = PerformanceCalculator::calculateCPH($line['circuit_output'], $used_mhr);
        $line['absent_rate'] = ($line['mp'] > 0) ? ($line['absent'] / $line['mp']) * 100 : 0;
        $line['manning_rate'] = ($line['mp'] > 0) ? (($line['mp'] - $line['absent'] - $line['separated_mp']) / $line['mp']) * 100 : 0;

        // Determine line status
        if ($line['plan_completion'] >= 95) {
            $line['status'] = 'running';
            $line['status_color'] = '#28a745';
        } elseif ($line['plan_completion'] >= 70) {
            $line['status'] = 'running';
            $line['status_color'] = '#ffc107';
        } else {
            $line['status'] = 'idle';
            $line['status_color'] = '#dc3545';
        }
    }

    // Get active alerts
    $alerts_query = "
        SELECT alert_type, alert_category, title, description, production_line,
               severity_score, created_at, status
        FROM production_alerts
        WHERE status IN ('active', 'escalated')
        ORDER BY severity_score DESC, created_at DESC
        LIMIT 10
    ";
    $active_alerts = $db->query($alerts_query)->fetchAll(PDO::FETCH_ASSOC);

    // Get today's bottlenecks
    $bottlenecks_query = "
        SELECT bottleneck_type, affected_production_line, impact_level,
               bottleneck_description, detected_date, resolution_status
        FROM production_bottlenecks
        WHERE detected_date = :current_date AND resolution_status IN ('pending', 'in_progress')
        ORDER BY
            CASE impact_level
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                ELSE 4
            END
    ";
    $stmt = $db->prepare($bottlenecks_query);
    $stmt->execute(['current_date' => $current_date]);
    $active_bottlenecks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate overall metrics
    $total_lines = count($production_lines);
    $running_lines = count(array_filter($production_lines, fn($line) => $line['status'] == 'running'));
    $critical_alerts = count(array_filter($active_alerts, fn($alert) => $alert['alert_type'] == 'critical'));
    $total_bottlenecks = count($active_bottlenecks);

    $overall_efficiency = $total_lines > 0 ? array_sum(array_column($production_lines, 'efficiency')) / $total_lines : 0;
    $overall_plan_completion = $total_lines > 0 ? array_sum(array_column($production_lines, 'plan_completion')) / $total_lines : 0;

} catch(PDOException $e) {
    error_log("Enhanced Dashboard Error: " . $e->getMessage());
    $production_lines = [];
    $active_alerts = [];
    $active_bottlenecks = [];
    $total_lines = $running_lines = $critical_alerts = $total_bottlenecks = 0;
    $overall_efficiency = $overall_plan_completion = 0;
}

// Generate HTML with inline assets
$asset_manager = $GLOBALS['asset_manager'];
echo $asset_manager->generateHTMLHeader("Production Command Center - Offline Mode");
echo $asset_manager->getOfflineFontCSS();
?>

<style>
/* Additional offline-specific styles */
.offline-banner {
    background: linear-gradient(135deg, #198754 0%, #20c997 100%);
    color: white;
    padding: 1rem;
    text-align: center;
    font-weight: 500;
}

.command-header-offline {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    color: white;
    padding: 2rem 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.header-title-offline {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
}

.line-card-offline {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    padding: 1rem;
    margin-bottom: 0.75rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.line-card-offline::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #28a745;
}

.line-card-offline.status-warning::before {
    background: #ffc107;
}

.line-card-offline.status-critical::before {
    background: #dc3545;
}

.line-card-offline:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateX(4px);
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.75rem;
    margin-top: 0.75rem;
}

.metric-item-offline {
    text-align: center;
    padding: 0.5rem;
    background: #f8f9fa;
    border-radius: 0.5rem;
}

.metric-value-offline {
    font-size: 1.1rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.metric-label-offline {
    font-size: 0.7rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.alert-item-offline {
    background: white;
    border-left: 4px solid #dc3545;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 0 0.5rem 0.5rem 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.alert-item-offline.warning {
    border-left-color: #ffc107;
}

.alert-item-offline.info {
    border-left-color: #0dcaf0;
}

.status-bar-offline {
    background: rgba(255,255,255,0.1);
    padding: 0.5rem;
    border-radius: 0.5rem;
    margin-top: 0.5rem;
    backdrop-filter: blur(10px);
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 1rem;
}

.status-item-offline {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
}

.indicator-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 0.5rem;
    animation: pulse 2s infinite;
}

.indicator-green { background-color: #28a745; }
.indicator-yellow { background-color: #ffc107; }
.indicator-red { background-color: #dc3545; }

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.6; }
    100% { opacity: 1; }
}

.dashboard-grid-offline {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 1rem;
    margin-bottom: 2rem;
}

@media (max-width: 992px) {
    .dashboard-grid-offline {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Offline Banner -->
<div class="offline-banner">
    <strong>🟢 OFFLINE MODE</strong> - Production Management System running on Local Area Network
    <span class="ms-3">Server: <?php echo $_SERVER['SERVER_ADDR'] ?? 'Localhost'; ?></span>
</div>

<!-- Command Header -->
<div class="command-header-offline">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="header-title-offline">
                    <span style="margin-right: 0.75rem;">🏭</span>
                    Production Command Center
                </h1>
                <p class="mb-0" style="opacity: 0.9; font-size: 1rem;">
                    Real-time monitoring for <?= $total_lines ?> production lines • 24/7 Operations • Offline Mode
                </p>
            </div>
            <div class="col-md-4">
                <div class="status-bar-offline">
                    <div class="status-item-offline">
                        <span class="indicator-dot indicator-green"></span>
                        <span><?= $running_lines ?> / <?= $total_lines ?> Active</span>
                    </div>
                    <div class="status-item-offline">
                        <span class="indicator-dot indicator-yellow"></span>
                        <span><?= count($active_alerts) ?> Alerts</span>
                    </div>
                    <div class="status-item-offline">
                        <span class="indicator-dot indicator-red"></span>
                        <span><?= $critical_alerts ?> Critical</span>
                    </div>
                    <div class="status-item-offline">
                        <span style="margin-right: 0.5rem;">🕐</span>
                        <span class="current-time"><?= date('H:i') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Dashboard -->
<div class="container-fluid mt-4">
    <!-- Overall Metrics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="metric-card text-center">
                <h3 class="metric-value text-success"><?= number_format($overall_efficiency, 1) ?>%</h3>
                <div class="text-muted">Overall Efficiency</div>
                <div class="progress mt-2" style="height: 8px;">
                    <div class="progress-bar bg-success" style="width: <?= min($overall_efficiency, 100) ?>%"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="metric-card text-center">
                <h3 class="metric-value" style="color: #2c3e50;"><?= number_format($overall_plan_completion, 1) ?>%</h3>
                <div class="text-muted">Plan Completion</div>
                <div class="progress mt-2" style="height: 8px;">
                    <div class="progress-bar" style="background-color: #2c3e50; width: <?= min($overall_plan_completion, 100) ?>%"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="metric-card text-center">
                <h3 class="metric-value text-warning"><?= $total_bottlenecks ?></h3>
                <div class="text-muted">Active Bottlenecks</div>
                <small class="text-muted">Needs attention</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="metric-card text-center">
                <h3 class="metric-value text-info"><?= count($active_alerts) ?></h3>
                <div class="text-muted">System Alerts</div>
                <small class="text-muted">Real-time monitoring</small>
            </div>
        </div>
    </div>

    <div class="dashboard-grid-offline">
        <!-- Main Content Area -->
        <div>
            <!-- Production Lines Status -->
            <div class="metric-card">
                <div style="border-bottom: 2px solid #2c3e50; padding-bottom: 1rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600; font-size: 1.1rem;">
                        <span style="margin-right: 0.5rem;">⚙️</span>Production Lines Status
                    </span>
                    <span style="background: #2c3e50; color: white; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.85rem; font-weight: 600;">
                        <?= $current_shift ?> Shift
                    </span>
                </div>

                <?php if (empty($production_lines)): ?>
                    <div class="text-center py-4 text-muted">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                        <h5>No Production Data Available</h5>
                        <p>No production data recorded for today. Check back after shift start.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($production_lines as $line): ?>
                        <div class="line-card-offline <?= $line['plan_completion'] < 70 ? 'status-critical' : ($line['plan_completion'] < 95 ? 'status-warning' : '') ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                <div>
                                    <h5 style="margin: 0; color: #2c3e50; font-weight: 600;"><?= htmlspecialchars($line['line_shift']) ?></h5>
                                    <small style="color: #6c757d;">Leader: <?= htmlspecialchars($line['leader']) ?> • Last update: <?= date('H:i', strtotime($line['last_update'])) ?></small>
                                </div>
                                <span style="padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: white; background-color: <?= $line['status_color'] ?>;">
                                    <?= $line['status'] ?>
                                </span>
                            </div>

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="metric-grid">
                                        <div class="metric-item-offline">
                                            <div class="metric-value-offline"><?= number_format($line['plan_completion'], 1) ?>%</div>
                                            <div class="metric-label-offline">Plan Comp.</div>
                                        </div>
                                        <div class="metric-item-offline">
                                            <div class="metric-value-offline"><?= number_format($line['efficiency'], 1) ?>%</div>
                                            <div class="metric-label-offline">Efficiency</div>
                                        </div>
                                        <div class="metric-item-offline">
                                            <div class="metric-value-offline"><?= number_format($line['cph'], 1) ?></div>
                                            <div class="metric-label-offline">CPH</div>
                                        </div>
                                        <div class="metric-item-offline">
                                            <div class="metric-value-offline"><?= $line['mp'] - $line['absent'] ?>/<?= $line['mp'] ?></div>
                                            <div class="metric-label-offline">Manning</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <small style="color: #6c757d;">Progress</small>
                                        <strong style="color: #2c3e50;"><?= $line['actual_output'] ?>/<?= $line['plan'] ?></strong>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar" style="background-color: #2c3e50; width: <?= min($line['plan_completion'], 100) ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Active Alerts -->
            <div class="metric-card">
                <div style="border-bottom: 2px solid #dc3545; padding-bottom: 1rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600; font-size: 1.1rem;">
                        <span style="margin-right: 0.5rem;">🔔</span>Active Alerts
                    </span>
                    <span style="background: #dc3545; color: white; padding: 0.25rem 0.5rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600;">
                        <?= count($active_alerts) ?>
                    </span>
                </div>

                <?php if (empty($active_alerts)): ?>
                    <div class="text-center py-3 text-muted">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">✅</div>
                        <p class="mb-0">All systems normal</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($active_alerts as $alert): ?>
                        <div class="alert-item-offline <?= $alert['alert_type'] ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <h6 style="margin: 0; font-weight: 600; font-size: 0.9rem;"><?= htmlspecialchars($alert['title']) ?></h6>
                                <small style="color: #6c757d;"><?= date('H:i', strtotime($alert['created_at'])) ?></small>
                            </div>
                            <?php if ($alert['description']): ?>
                                <p style="margin-bottom: 0.5rem; font-size: 0.85rem; color: #6c757d;"><?= htmlspecialchars($alert['description']) ?></p>
                            <?php endif; ?>
                            <?php if ($alert['production_line']): ?>
                                <small style="color: #6c757d;">
                                    <span style="margin-right: 0.25rem;">🏭</span><?= htmlspecialchars($alert['production_line']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div style="margin-top: 1rem; text-align: center;">
                    <a href="alert_system.php" class="btn btn-sm btn-primary">View All Alerts</a>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="metric-card">
                <div style="border-bottom: 2px solid #198754; padding-bottom: 1rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600; font-size: 1.1rem;">
                        <span style="margin-right: 0.5rem;">⚡</span>Quick Actions
                    </span>
                </div>

                <div style="display: grid; gap: 0.5rem;">
                    <a href="entry_form.php" class="btn btn-success w-100">
                        <span style="margin-right: 0.5rem;">📝</span>Enter Production Data
                    </a>
                    <a href="analytics_engine.php" class="btn btn-info w-100">
                        <span style="margin-right: 0.5rem;">📊</span>View Analytics
                    </a>
                    <a href="production_scheduler.php" class="btn btn-warning w-100">
                        <span style="margin-right: 0.5rem;">📅</span>Production Schedule
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary w-100">
                        <span style="margin-right: 0.5rem;">📈</span>Classic Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="container-fluid mt-5">
    <div style="text-align: center; padding: 2rem; border-top: 1px solid #dee2e6; color: #6c757d;">
        <p class="mb-1">Production Management System - Offline Mode v1.0</p>
        <p class="mb-0">Running on Local Area Network • Server Time: <?= date('Y-m-d H:i:s') ?></p>
    </div>
</div>

<script>
// Offline-specific functionality
document.addEventListener('DOMContentLoaded', function() {
    // Update offline indicator
    const updateOfflineIndicator = function() {
        const indicator = document.querySelector('.offline-banner');
        if (navigator.onLine) {
            indicator.innerHTML = '<strong>🟢 OFFLINE MODE</strong> - Production Management System running on Local Area Network';
            indicator.style.background = 'linear-gradient(135deg, #198754 0%, #20c997 100%)';
        } else {
            indicator.innerHTML = '<strong>🟡 LIMITED CONNECTIVITY</strong> - Some features may be unavailable';
            indicator.style.background = 'linear-gradient(135deg, #ffc107 0%, #fd7e14 100%)';
        }
    };

    updateOfflineIndicator();
    window.addEventListener('online', updateOfflineIndicator);
    window.addEventListener('offline', updateOfflineIndicator);

    // Auto-refresh functionality for offline mode
    const refreshData = function() {
        console.log('Refreshing production data...');
        // In offline mode, we'll just reload the page
        // In a real implementation, this would make local AJAX calls
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    };

    // Set up auto-refresh every 30 seconds
    setInterval(refreshData, 30000);

    // Show refresh button
    const refreshBtn = document.createElement('button');
    refreshBtn.className = 'btn btn-sm btn-primary';
    refreshBtn.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 1000;';
    refreshBtn.innerHTML = '<span style="margin-right: 0.5rem;">🔄</span>Refresh';
    refreshBtn.onclick = refreshData;
    document.body.appendChild(refreshBtn);

    console.log('Production Command Center initialized in offline mode');
});
</script>

<?php
echo $asset_manager->generateHTMLFooter();
?>