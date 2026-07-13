<?php
// turbines.php - Turbine Control Room page

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

try {
    $stmt = $pdo->query("SELECT * FROM turbines");
    $turbines = $stmt->fetchAll();
    $sludgeLevel = (float)$pdo->query("SELECT sludge_level FROM reservoir WHERE id=1")->fetchColumn();
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PowerPulse - Turbine Control Room</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .telemetry-highlight {
            color: var(--color-accent) !important;
            text-shadow: 0 0 10px var(--color-accent-glow);
            transition: all 0.1s ease;
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Container -->
    <main>
        <header>
            <div>
                <h1>Turbine Control Room</h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">Manage hydraulic gate openings and generator status parameters</p>
            </div>
            <div class="timestamp" id="live-clock">--:--:--</div>
        </header>

        <div class="turbine-grid">
            <?php foreach ($turbines as $t): ?>
                <?php 
                $tPower = getPowerOutput($t['flow_rate'], $t['head'], $t['efficiency'], $sludgeLevel);
                // Gate opening percentage: current flow / max flow (45 m3/s)
                $maxFlow = 45.0;
                $gateOpening = ($t['status'] === 'Active') ? round(($t['flow_rate'] / $maxFlow) * 100) : 0;
                ?>
                <div class="card turbine-card" data-turbine-id="<?php echo $t['id']; ?>">
                    <div class="turbine-header">
                        <span class="turbine-name"><?php echo $t['name']; ?></span>
                        <div class="status-indicator status-<?php echo strtolower($t['status']); ?>">
                            <div class="status-dot"></div>
                            <span><?php echo $t['status']; ?></span>
                        </div>
                    </div>

                    <!-- Telemetry Metrics Grid -->
                    <div class="turbine-metrics">
                        <div class="metric-item">
                            <span class="metric-label">Flow Rate</span>
                            <span class="metric-val"><span class="flow-value"><?php echo number_format($t['flow_rate'], 1); ?></span> m³/s</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Effective Head</span>
                            <span class="metric-val"><span class="head-value" id="head-<?php echo $t['id']; ?>" data-head="<?php echo $t['head']; ?>"><?php echo number_format($t['head'], 1); ?></span> m</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Efficiency</span>
                            <span class="metric-val"><span class="efficiency-value" data-efficiency="<?php echo $t['efficiency']; ?>"><?php echo number_format($t['efficiency'] * 100, 1); ?></span>%</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Power Output</span>
                            <span class="metric-val" style="color: var(--color-active); font-weight: 700;"><span class="power-value"><?php echo number_format($tPower, 2); ?></span> MW</span>
                        </div>
                    </div>

                    <!-- Interactive Parameter Controls -->
                    <form class="turbine-control-form">
                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                        
                        <div class="turbine-controls">
                            <div class="control-group">
                                <label class="control-label">Generator Mode</label>
                                <select name="status" class="status-select">
                                    <option value="Active" <?php echo ($t['status'] == 'Active') ? 'selected' : ''; ?>>Active (Power Gen)</option>
                                    <option value="Maintenance" <?php echo ($t['status'] == 'Maintenance') ? 'selected' : ''; ?>>Maintenance (Diagnostics)</option>
                                    <option value="Offline" <?php echo ($t['status'] == 'Offline') ? 'selected' : ''; ?>>Offline (Shut Down)</option>
                                </select>
                            </div>

                            <div class="control-group">
                                <div class="control-label">
                                    <span>Gate Opening</span>
                                    <span class="gate-percentage-lbl"><span class="gate-percentage-val"><?php echo $gateOpening; ?></span>%</span>
                                </div>
                                <input type="range" 
                                       name="gate_opening" 
                                       class="flow-slider" 
                                       min="0" 
                                       max="100" 
                                       value="<?php echo $gateOpening; ?>" 
                                       data-max-flow="<?php echo $maxFlow; ?>"
                                       <?php echo ($t['status'] !== 'Active') ? 'disabled' : ''; ?>>
                            </div>

                            <button type="submit" class="btn" style="width: 100%; margin-top: 0.5rem;">
                                <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2.5; display: inline-block;">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                    <polyline points="17 21 17 13 7 13 7 21"/>
                                    <polyline points="7 3 7 8 15 8"/>
                                </svg>
                                Apply Parameters
                            </button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script src="app.js"></script>
    <script>
        // Inline script to update specific range labels for gate opening values in real-time
        document.querySelectorAll('.turbine-card').forEach(card => {
            const slider = card.querySelector('.flow-slider');
            const label = card.querySelector('.gate-percentage-val');
            const statusSelect = card.querySelector('.status-select');
            
            if (slider && label) {
                slider.addEventListener('input', () => {
                    label.textContent = slider.value;
                });
                
                statusSelect.addEventListener('change', () => {
                    if (statusSelect.value !== 'Active') {
                        label.textContent = '0';
                    } else {
                        label.textContent = slider.value;
                    }
                });
            }
        });
    </script>
</body>
</html>
