<?php
// index.php - Main dashboard for PowerPulse Hydropower Performance Dashboard

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// Fetch current telemetry data from SQLite
// Total Active Power, Active Turbines count, Average Efficiency
try {
    $stmt = $pdo->query("SELECT * FROM turbines");
    $turbines = $stmt->fetchAll();

    // Fetch Reservoir status first (we need sludge level for power calc)
    $stmt = $pdo->query("SELECT * FROM reservoir WHERE id = 1");
    $reservoir = $stmt->fetch();
    $resPercent = ($reservoir['current_volume'] / $reservoir['max_volume']) * 100;
    $sludgeLevel = $reservoir['sludge_level'];
    $powerLossPct = round($sludgeLevel * 0.20, 1);

    $totalPower = 0.0;
    $totalCapacity = 0.0;
    $activeCount = 0;
    $efficiencySum = 0.0;

    foreach ($turbines as $t) {
        $totalCapacity += $t['max_capacity'];
        if ($t['status'] === 'Active') {
            $totalPower += getPowerOutput($t['flow_rate'], $t['head'], $t['efficiency'], $sludgeLevel);
            $activeCount++;
            $efficiencySum += $t['efficiency'];
        }
    }

    $avgEfficiency = $activeCount > 0 ? ($efficiencySum / $activeCount) * 100 : 0.0;

    // Fetch active alarms (recent Warnings/Alarms in last 6 hours)
    $stmt = $pdo->query("SELECT * FROM logs WHERE type IN ('Warning', 'Alarm') ORDER BY timestamp DESC LIMIT 3");
    $alarms = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}

// Generate dynamic SVG chart data points for daily generation curve (last 24 hours)
$loadProfile = [120, 115, 110, 105, 120, 140, 165, 180, 175, 170, 160, 155, 165, 175, 185, 190, 180, 170, 165, 155, 145, 135, 130, 125];
$targetProfile = [130, 130, 120, 110, 110, 130, 160, 180, 180, 170, 160, 160, 160, 170, 180, 190, 190, 180, 170, 160, 150, 140, 130, 130];

// Convert points to SVG coordinates (width=800, height=200)
$svgWidth = 800;
$svgHeight = 200;
$padding = 20;

$pointCount = count($loadProfile);
$xStep = ($svgWidth - 2 * $padding) / ($pointCount - 1);

// Math helper to scale value to height (min power=80MW, max power=210MW)
$minPower = 80;
$maxPower = 215;
function getSvgY($val, $minPower, $maxPower, $svgHeight, $padding) {
    $heightRange = $svgHeight - 2 * $padding;
    $valRange = $maxPower - $minPower;
    $ratio = ($val - $minPower) / $valRange;
    return $svgHeight - $padding - ($ratio * $heightRange);
}

// Build generation path (actual)
$actualPoints = [];
for ($i = 0; $i < $pointCount; $i++) {
    $x = $padding + ($i * $xStep);
    $y = getSvgY($loadProfile[$i], $minPower, $maxPower, $svgHeight, $padding);
    $actualPoints[] = "$x,$y";
}
$actualPath = "M " . implode(" L ", $actualPoints);

// Build target path
$targetPoints = [];
for ($i = 0; $i < $pointCount; $i++) {
    $x = $padding + ($i * $xStep);
    $y = getSvgY($targetProfile[$i], $minPower, $maxPower, $svgHeight, $padding);
    $targetPoints[] = "$x,$y";
}
$targetPath = "M " . implode(" L ", $targetPoints);

// Build shaded actual area (close path to bottom)
$areaPath = $actualPath . " L " . ($svgWidth - $padding) . "," . ($svgHeight - $padding) . " L " . $padding . "," . ($svgHeight - $padding) . " Z";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PowerPulse - Hydropower Performance Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body id="dashboard-view">
    
    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Container -->
    <main>
        <header>
            <div>
                <h1>PowerPulse System Overview</h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">Hydropower Station Telemetry &amp; Simulation Desk</p>
            </div>
            <div class="status-indicator status-active">
                <div class="status-dot"></div>
                <span>Plant Online</span>
            </div>
        </header>

        <!-- KPI Summary Cards -->
        <div class="grid-4">
            <div class="card kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Active Power Output</span>
                    <div class="kpi-icon">
                        <svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                    </div>
                </div>
                <div class="kpi-value" id="total-power"><?php echo number_format($totalPower, 2); ?><span>MW</span></div>
                <div class="kpi-footer">
                    <span>Max Capacity: <?php echo number_format($totalCapacity, 1); ?> MW</span>
                    <?php if ($powerLossPct > 0): ?>
                    <span class="kpi-trend-down" style="margin-left:0.5rem;">&#9660; <?php echo $powerLossPct; ?>% sludge loss</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Plant Efficiency</span>
                    <div class="kpi-icon">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                </div>
                <div class="kpi-value" id="avg-efficiency"><?php echo number_format($avgEfficiency, 1); ?><span>%</span></div>
                <div class="kpi-footer">
                    <span class="kpi-trend-up">Optimal Range: 85% - 92%</span>
                </div>
            </div>

            <div class="card kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Active Turbines</span>
                    <div class="kpi-icon">
                        <svg viewBox="0 0 24 24"><path d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </div>
                </div>
                <div class="kpi-value" id="active-turbines"><?php echo $activeCount; ?><span>/ 6</span></div>
                <div class="kpi-footer">
                    <span>1 Maintenance | 1 Offline</span>
                </div>
            </div>

            <div class="card kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Reservoir Head</span>
                    <div class="kpi-icon">
                        <svg viewBox="0 0 24 24"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>
                    </div>
                </div>
                <div class="kpi-value" id="water-level"><?php echo number_format($reservoir['water_level'], 2); ?><span>m</span></div>
                <div class="kpi-footer">
                    <span>Volume: <?php echo number_format($reservoir['current_volume'], 1); ?>M m³ (<?php echo number_format($resPercent, 1); ?>%)</span>
                </div>
            </div>
        </div>

        <!-- Telemetry Graphs and Alarms -->
        <div class="grid-2">
            <!-- 24 Hour Generation Curve -->
            <div class="card">
                <h2>Generation Profile (24 Hour Curve)</h2>
                <div class="chart-container">
                    <svg class="chart-svg" viewBox="0 0 800 200">
                        <defs>
                            <linearGradient id="chart-gradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="var(--color-accent)" stop-opacity="0.3"/>
                                <stop offset="100%" stop-color="var(--color-accent)" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        
                        <!-- Grid lines -->
                        <line x1="20" y1="20" x2="780" y2="20" class="chart-grid-line" />
                        <line x1="20" y1="65" x2="780" y2="65" class="chart-grid-line" />
                        <line x1="20" y1="110" x2="780" y2="110" class="chart-grid-line" />
                        <line x1="20" y1="155" x2="780" y2="155" class="chart-grid-line" />
                        <line x1="20" y1="180" x2="780" y2="180" class="chart-grid-line" style="stroke: rgba(255,255,255,0.15);" />
                        
                        <!-- Axis Labels -->
                        <text x="25" y="15" class="chart-axis-text">200 MW (Max)</text>
                        <text x="25" y="105" class="chart-axis-text">140 MW (Avg)</text>
                        <text x="25" y="175" class="chart-axis-text">80 MW (Min)</text>
                        
                        <!-- Area path -->
                        <path d="<?php echo $areaPath; ?>" class="chart-area"></path>
                        
                        <!-- Target line -->
                        <path d="<?php echo $targetPath; ?>" class="chart-path" style="stroke: #ff9f1c; stroke-dasharray: 5,5; stroke-width: 1.5; filter: none;"></path>
                        
                        <!-- Actual line -->
                        <path d="<?php echo $actualPath; ?>" class="chart-path"></path>
                        
                        <!-- Time labels along bottom -->
                        <text x="20" y="195" class="chart-axis-text">00:00</text>
                        <text x="210" y="195" class="chart-axis-text">06:00</text>
                        <text x="400" y="195" class="chart-axis-text">12:00</text>
                        <text x="590" y="195" class="chart-axis-text">18:00</text>
                        <text x="760" y="195" class="chart-axis-text">24:00</text>
                    </svg>
                </div>
                <div style="display: flex; gap: 1.5rem; margin-top: 1rem; font-size: 0.8rem; justify-content: center;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 12px; height: 3px; background: var(--color-accent);"></div>
                        <span>Actual Power Output (MW)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 12px; height: 3px; border-bottom: 2px dashed #ff9f1c;"></div>
                        <span>Target Grid Load (MW)</span>
                    </div>
                </div>
            </div>

            <!-- Active Alarms / Warnings -->
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h2>Active Alarms &amp; Notifications</h2>
                    <div class="alarm-list">
                        <?php if (count($alarms) == 0): ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                <svg viewBox="0 0 24 24" style="width: 48px; height: 48px; fill: none; stroke: var(--text-muted); stroke-width: 1.5; margin-bottom: 0.5rem;">
                                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
                                </svg>
                                <p>No warnings or alarms active.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($alarms as $a): ?>
                                <div class="alarm-item <?php echo strtolower($a['type']); ?>">
                                    <div class="alarm-icon">
                                        <?php if ($a['type'] == 'Alarm'): ?>
                                            🚨
                                        <?php else: ?>
                                            ⚠️
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex-grow: 1;">
                                        <div style="font-weight: 600; font-size: 0.85rem; text-transform: uppercase;">
                                            <?php echo $a['source']; ?> &bull; <?php echo $a['type']; ?>
                                        </div>
                                        <div style="margin-top: 0.15rem; font-size: 0.85rem; color: #fff;">
                                            <?php echo $a['message']; ?>
                                        </div>
                                    </div>
                                    <div style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-muted);">
                                        <?php echo date('H:i', strtotime($a['timestamp'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="border-top: 1px solid rgba(255,255,255,0.06); padding-top: 1rem; margin-top: 1rem; text-align: right;">
                    <a href="logs.php" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">View Detailed Logs</a>
                </div>
            </div>
        </div>

        <!-- Turbine Performance Quick Table -->
        <div class="card" style="margin-bottom: 0;">
            <h2>Active Turbines Status</h2>
            <div class="logs-table-container">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Unit Name</th>
                            <th>Operational Status</th>
                            <th>Water Flow</th>
                            <th>Effective Head</th>
                            <th>Unit Efficiency</th>
                            <th>Power Output</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($turbines as $t): ?>
                            <?php 
                            $tPower = getPowerOutput($t['flow_rate'], $t['head'], $t['efficiency'], $sludgeLevel);
                            ?>
                            <tr>
                                <td><strong><?php echo $t['name']; ?></strong></td>
                                <td>
                                    <div class="status-indicator status-<?php echo strtolower($t['status']); ?>">
                                        <div class="status-dot"></div>
                                        <span><?php echo $t['status']; ?></span>
                                    </div>
                                </td>
                                <td style="font-family: var(--font-mono);"><?php echo number_format($t['flow_rate'], 1); ?> m³/s</td>
                                <td style="font-family: var(--font-mono);"><?php echo number_format($t['head'], 1); ?> m</td>
                                <td style="font-family: var(--font-mono);"><?php echo number_format($t['efficiency'] * 100, 1); ?>%</td>
                                <td style="font-family: var(--font-mono); font-weight: 600; color: var(--color-active);"><?php echo number_format($tPower, 2); ?> MW</td>
                                <td style="text-align: right;">
                                    <a href="turbines.php" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.35rem 0.7rem;">Control Room</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="app.js"></script>
</body>
</html>
