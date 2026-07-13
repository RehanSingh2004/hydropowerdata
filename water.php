<?php
// water.php - Reservoir & Water Management (with Sludge Monitoring)

require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM reservoir WHERE id = 1");
    $reservoir = $stmt->fetch();

    $stmt = $pdo->query("SELECT SUM(flow_rate) as total_flow FROM turbines WHERE status = 'Active'");
    $turbineFlow = (float)($stmt->fetchColumn() ?: 0.0);

    $maxSpillwayFlow = 400.0;
    $spillwayFlow    = $maxSpillwayFlow * ($reservoir['spillway_gate'] / 100);
    $totalOutflow    = $turbineFlow + $spillwayFlow;
    $netChange       = $reservoir['inflow'] - $totalOutflow;
    $resPercent      = ($reservoir['current_volume'] / $reservoir['max_volume']) * 100;

    $sludgeLevel     = (float)$reservoir['sludge_level'];
    $powerLossPct    = round($sludgeLevel * 0.20, 1);

    // Determine sludge severity
    if ($sludgeLevel >= 60.0) {
        $sludgeStatus = 'alarm';
        $sludgeLabel  = 'CRITICAL — Immediate Cleaning Required';
        $sludgeColor  = 'var(--color-offline)';
    } elseif ($sludgeLevel >= 30.0) {
        $sludgeStatus = 'warning';
        $sludgeLabel  = 'Warning — Schedule Cleaning Soon';
        $sludgeColor  = 'var(--color-maintenance)';
    } else {
        $sludgeStatus = 'normal';
        $sludgeLabel  = 'Normal — Sediment within acceptable range';
        $sludgeColor  = 'var(--color-active)';
    }
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PowerPulse – Reservoir &amp; Water Management</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Sludge fill bar */
        .sludge-bar-track {
            width: 100%;
            height: 14px;
            background: rgba(255,255,255,0.06);
            border-radius: 7px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .sludge-bar-fill {
            height: 100%;
            border-radius: 7px;
            transition: width 1s ease, background 0.5s ease;
        }

        /* Pulsing clean button when alarm */
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 4px 15px rgba(255,77,109,0.25); }
            50%       { box-shadow: 0 4px 30px rgba(255,77,109,0.7); }
        }
        .btn-clean.urgent {
            background: linear-gradient(135deg, #ff4d6d, #c9184a);
            animation: pulse-glow 1.5s ease-in-out infinite;
        }
        .btn-clean.warning-lvl {
            background: linear-gradient(135deg, #ffb703, #e07c00);
        }

        /* Sludge visual in reservoir SVG */
        .sludge-fill-visual {
            fill: url(#sludge-gradient);
            transition: height 1s ease;
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/sidebar.php'; ?>

    <main>
        <header>
            <div>
                <h1>Reservoir &amp; Water Management</h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">Monitor hydraulics, flow balance, sludge accumulation, and spillway bypass</p>
            </div>
            <div class="timestamp" id="live-clock">--:--:--</div>
        </header>

        <!-- TOP ROW: Reservoir visual + Flow balance -->
        <div class="grid-2">
            <!-- Reservoir Level + Sludge Visual -->
            <div class="card">
                <h2>Reservoir Level &amp; Sludge Status</h2>

                <!-- Animated reservoir container -->
                <div class="reservoir-visual" id="reservoir-container">
                    <!-- Sludge sediment layer at bottom -->
                    <div id="sludge-layer" style="
                        position: absolute;
                        bottom: 0; left: 0; width: 100%;
                        background: linear-gradient(180deg, rgba(160,100,20,0.55) 0%, rgba(100,60,10,0.85) 100%);
                        height: <?php echo min(60, $sludgeLevel * 0.55); ?>%;
                        z-index: 3;
                        border-top: 2px solid rgba(200,140,40,0.5);
                        transition: height 1s ease;
                    ">
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); text-align: center; text-shadow: 0 1px 4px rgba(0,0,0,0.8);">
                            <div style="font-size: 0.7rem; color: rgba(255,220,120,0.9); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Sediment / Sludge</div>
                            <div id="sludge-pct-overlay" style="font-size: 1rem; font-weight: 800; color: #ffd166; font-family: var(--font-mono);"><?php echo number_format($sludgeLevel, 1); ?>%</div>
                        </div>
                    </div>

                    <!-- Water level above sludge -->
                    <div class="reservoir-water" id="reservoir-water-overlay"
                         style="height: <?php echo max(0, $resPercent - min(60, $sludgeLevel * 0.55)); ?>%;
                                bottom: <?php echo min(60, $sludgeLevel * 0.55); ?>%;
                                z-index: 2;">
                    </div>

                    <!-- Stats overlay -->
                    <div class="reservoir-stats-overlay" style="z-index: 5;">
                        <span class="reservoir-percentage" id="reservoir-percentage-lbl"><?php echo number_format($resPercent, 1); ?>%</span>
                        <span class="reservoir-head-lbl">Water Capacity</span>
                    </div>
                </div>

                <!-- Metric tiles -->
                <div class="turbine-metrics" style="grid-template-columns: repeat(3, 1fr);">
                    <div class="metric-item">
                        <span class="metric-label">Effective Head</span>
                        <span class="metric-val" style="color: var(--color-accent);"><?php echo number_format($reservoir['water_level'], 2); ?> m</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-label">Current Volume</span>
                        <span class="metric-val"><?php echo number_format($reservoir['current_volume'], 1); ?>M m³</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-label">Max Capacity</span>
                        <span class="metric-val"><?php echo number_format($reservoir['max_volume'], 1); ?>M m³</span>
                    </div>
                </div>
            </div>

            <!-- Flow balance + Spillway Control -->
            <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <h2>Water Flow Balance</h2>
                    <div class="turbine-metrics" style="margin-bottom: 1.5rem;">
                        <div class="metric-item">
                            <span class="metric-label">River Inflow</span>
                            <span class="metric-val" style="color: var(--color-active);"><?php echo number_format($reservoir['inflow'], 1); ?> m³/s</span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Total Outflow</span>
                            <span class="metric-val" style="color: var(--color-offline);"><?php echo number_format($totalOutflow, 1); ?> m³/s</span>
                        </div>
                    </div>

                    <div style="background:rgba(255,255,255,0.02); padding:1.2rem; border-radius:12px; border:1px solid rgba(255,255,255,0.04); margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase;">Net Volume Change</div>
                            <div style="font-size:1.25rem; font-weight:700; font-family:var(--font-mono); margin-top:0.25rem;">
                                <?php echo ($netChange >= 0 ? '+' : '') . number_format($netChange, 1); ?> m³/s
                            </div>
                        </div>
                        <?php if ($netChange > 5.0): ?>
                            <span class="status-indicator status-active" style="background:rgba(0,210,255,.08); color:var(--color-accent); border-color:rgba(0,210,255,.2);">
                                <div class="status-dot" style="background:var(--color-accent); box-shadow:0 0 8px var(--color-accent);"></div> Level Rising
                            </span>
                        <?php elseif ($netChange < -5.0): ?>
                            <span class="status-indicator status-maintenance">
                                <div class="status-dot"></div> Level Falling
                            </span>
                        <?php else: ?>
                            <span class="status-indicator status-active">
                                <div class="status-dot"></div> Stable Level
                            </span>
                        <?php endif; ?>
                    </div>

                    <div style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:1.5rem; display:flex; flex-direction:column; gap:0.5rem;">
                        <div style="display:flex; justify-content:space-between;">
                            <span>Turbine Generation Outflow:</span>
                            <span style="font-family:var(--font-mono);"><?php echo number_format($turbineFlow, 1); ?> m³/s</span>
                        </div>
                        <div style="display:flex; justify-content:space-between;">
                            <span>Spillway Discharge Bypass:</span>
                            <span style="font-family:var(--font-mono);"><?php echo number_format($spillwayFlow, 1); ?> m³/s</span>
                        </div>
                    </div>
                </div>

                <!-- Spillway Control -->
                <form id="spillway-control-form" style="border-top:1px solid rgba(255,255,255,0.06); padding-top:1.5rem;">
                    <div class="control-group" style="margin-bottom:1rem;">
                        <div class="control-label">
                            <span style="font-weight:600;">Spillway Bypass Gates</span>
                            <span id="spillway-gate-val" style="color:var(--color-accent); font-family:var(--font-mono); font-weight:600;"><?php echo round($reservoir['spillway_gate']); ?>%</span>
                        </div>
                        <p style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem;">Bypass excess water to lower river to prevent overflow.</p>
                        <input type="range" name="spillway_gate" id="spillway-gate-slider" min="0" max="100" value="<?php echo round($reservoir['spillway_gate']); ?>">
                    </div>
                    <button type="submit" class="btn" style="width:100%;">Apply Spillway Discharge</button>
                </form>
            </div>
        </div>

        <!-- SLUDGE MONITORING PANEL -->
        <div class="card" style="margin-bottom: 0;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
                <div>
                    <h2 style="margin-bottom:0.25rem;">Reservoir Sludge &amp; Sediment Monitor</h2>
                    <p style="font-size:0.85rem; color:var(--text-secondary);">
                        Silt and sediment deposited at the reservoir bed reduces the effective head and degrades turbine power output.
                        A sludge desilting operation restores full generation capacity.
                    </p>
                </div>

                <!-- Clean Reservoir Button -->
                <button id="clean-reservoir-btn"
                        class="btn btn-clean <?php echo $sludgeStatus === 'alarm' ? 'urgent' : ($sludgeStatus === 'warning' ? 'warning-lvl' : ''); ?>"
                        style="min-width:200px; flex-shrink:0;">
                    <svg viewBox="0 0 24 24" style="width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.5;display:inline-block;">
                        <path d="M3 6l3 13h12l3-13H3zM8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/>
                        <line x1="12" y1="11" x2="12" y2="17"/>
                        <line x1="9" y1="11" x2="9.5" y2="17"/>
                        <line x1="15" y1="11" x2="14.5" y2="17"/>
                    </svg>
                    <?php echo $sludgeStatus === 'alarm' ? 'URGENT: Clean Reservoir Now' : 'Clean Reservoir (Desilt)'; ?>
                </button>
            </div>

            <!-- Sludge Metrics Row -->
            <div class="grid-4" style="margin-bottom:1.5rem;">
                <div class="card" style="background:rgba(255,255,255,0.02); border-color:rgba(255,255,255,0.05); padding:1.2rem;">
                    <div class="metric-label" style="font-size:0.75rem; margin-bottom:0.5rem;">Sludge Accumulation</div>
                    <div style="font-size:1.6rem; font-weight:800; font-family:var(--font-mono); color:<?php echo $sludgeColor; ?>;" id="sludge-level-display">
                        <?php echo number_format($sludgeLevel, 1); ?><span style="font-size:1rem; font-weight:500;">%</span>
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;"><?php echo $sludgeLabel; ?></div>
                </div>

                <div class="card" style="background:rgba(255,255,255,0.02); border-color:rgba(255,255,255,0.05); padding:1.2rem;">
                    <div class="metric-label" style="font-size:0.75rem; margin-bottom:0.5rem;">Power Generation Loss</div>
                    <div style="font-size:1.6rem; font-weight:800; font-family:var(--font-mono); color:var(--color-offline);" id="power-loss-display">
                        <?php echo $powerLossPct; ?><span style="font-size:1rem; font-weight:500;">%</span>
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;">Due to reduced effective turbine head</div>
                </div>

                <div class="card" style="background:rgba(255,255,255,0.02); border-color:rgba(255,255,255,0.05); padding:1.2rem;">
                    <div class="metric-label" style="font-size:0.75rem; margin-bottom:0.5rem;">Warning Threshold</div>
                    <div style="font-size:1.6rem; font-weight:800; font-family:var(--font-mono); color:var(--color-maintenance);">
                        30<span style="font-size:1rem; font-weight:500;">%</span>
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;"><?php echo $sludgeLevel >= 30 ? '⚠️ Threshold Exceeded' : 'Not yet reached'; ?></div>
                </div>

                <div class="card" style="background:rgba(255,255,255,0.02); border-color:rgba(255,255,255,0.05); padding:1.2rem;">
                    <div class="metric-label" style="font-size:0.75rem; margin-bottom:0.5rem;">Critical Alarm Threshold</div>
                    <div style="font-size:1.6rem; font-weight:800; font-family:var(--font-mono); color:var(--color-offline);">
                        60<span style="font-size:1rem; font-weight:500;">%</span>
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.25rem;"><?php echo $sludgeLevel >= 60 ? '🚨 ALARM ACTIVE' : 'Not yet reached'; ?></div>
                </div>
            </div>

            <!-- Sludge Progress Bar -->
            <div style="margin-bottom:1.5rem;">
                <div style="display:flex; justify-content:space-between; font-size:0.8rem; color:var(--text-secondary); margin-bottom:0.5rem;">
                    <span>Sediment Fill Level</span>
                    <span style="font-family:var(--font-mono); color:<?php echo $sludgeColor; ?>;" id="sludge-bar-label"><?php echo number_format($sludgeLevel, 1); ?>%</span>
                </div>
                <div class="sludge-bar-track">
                    <div class="sludge-bar-fill" id="sludge-bar-fill" style="
                        width: <?php echo $sludgeLevel; ?>%;
                        background: <?php
                            if ($sludgeLevel >= 60) echo 'linear-gradient(90deg, #c9184a, #ff4d6d)';
                            elseif ($sludgeLevel >= 30) echo 'linear-gradient(90deg, #e07c00, #ffb703)';
                            else echo 'linear-gradient(90deg, #6c7a3a, #a8b400)';
                        ?>;
                    "></div>
                </div>

                <!-- Threshold markers -->
                <div style="position:relative; height:18px; margin-top:4px;">
                    <div style="position:absolute; left:30%; transform:translateX(-50%); font-size:0.7rem; color:var(--color-maintenance);">│ 30%</div>
                    <div style="position:absolute; left:60%; transform:translateX(-50%); font-size:0.7rem; color:var(--color-offline);">│ 60%</div>
                    <div style="position:absolute; right:0; font-size:0.7rem; color:var(--text-muted);">100%</div>
                </div>
            </div>

            <!-- Sludge impact explanation -->
            <div style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.05); border-radius:12px; padding:1.2rem;">
                <div style="font-size:0.85rem; font-weight:600; margin-bottom:0.75rem; color:var(--text-primary);">How Sludge Affects Power Generation</div>
                <div style="font-size:0.82rem; color:var(--text-secondary); line-height:1.7;">
                    As silt and sediment accumulate at the reservoir bed, the effective operating head (water pressure available to turbines) is progressively reduced.
                    The power loss follows the relationship:
                    <code style="background:rgba(255,255,255,0.06); padding:0.15rem 0.4rem; border-radius:4px; font-family:var(--font-mono); font-size:0.8rem;">
                        P<sub>actual</sub> = P<sub>nominal</sub> × (1 − 0.20 × Sludge%)
                    </code>.
                    At 50% sludge, the plant loses 10% of its rated capacity; at 100%, output drops by 20%.
                    A <strong>desilting/flushing operation</strong> clears the accumulated sediment, restoring the full hydraulic head and rated power output.
                </div>
            </div>
        </div>
    </main>

    <script src="app.js"></script>
    <script>
        // Clean Reservoir AJAX handler
        document.getElementById('clean-reservoir-btn').addEventListener('click', async (e) => {
            e.preventDefault();
            const btn = e.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Cleaning in progress...';
            btn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'clean_reservoir');
                const res  = await fetch('api.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    // Animate sludge down to 0
                    animateSludgeTo(data.sludge_level);
                    showToast(`✅ Reservoir cleaned! Sludge cleared from ${data.old_sludge}% → 0.0%. Full capacity restored.`);

                    // Update all sludge UI elements
                    document.getElementById('sludge-level-display').innerHTML = '0.0<span style="font-size:1rem;font-weight:500;">%</span>';
                    document.getElementById('power-loss-display').innerHTML  = '0.0<span style="font-size:1rem;font-weight:500;">%</span>';
                    document.getElementById('sludge-bar-label').textContent  = '0.0%';
                    document.getElementById('sludge-pct-overlay').textContent = '0.0%';

                    // Reset bar colour and width
                    const bar = document.getElementById('sludge-bar-fill');
                    bar.style.width = '0%';
                    bar.style.background = 'linear-gradient(90deg, #6c7a3a, #a8b400)';

                    // Reset sludge layer in reservoir graphic
                    document.getElementById('sludge-layer').style.height = '0%';

                    // Reset button to normal appearance
                    btn.className = 'btn btn-clean';
                    btn.disabled  = false;
                    btn.innerHTML = '✔ Reservoir Cleaned Successfully';
                } else {
                    showToast(data.error || 'Cleaning failed.', 'error');
                    btn.disabled  = false;
                    btn.innerHTML = originalText;
                }
            } catch (err) {
                showToast('Network error during cleaning operation.', 'error');
                btn.disabled  = false;
                btn.innerHTML = originalText;
            }
        });

        function animateSludgeTo(target) {
            const overlay = document.getElementById('sludge-pct-overlay');
            let current   = parseFloat(overlay.textContent) || 0;
            const step    = (current - target) / 30;
            const ticker  = setInterval(() => {
                current = Math.max(target, current - step);
                if (overlay) overlay.textContent = current.toFixed(1) + '%';
                if (current <= target) clearInterval(ticker);
            }, 30);
        }
    </script>
</body>
</html>
