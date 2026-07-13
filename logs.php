<?php
// logs.php - Operations Logs & Maintenance records

require_once __DIR__ . '/db.php';

try {
    // Fetch logs from database
    $stmt = $pdo->query("SELECT * FROM logs ORDER BY timestamp DESC LIMIT 50");
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PowerPulse - Operations Log &amp; Maintenance Records</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Sidebar Navigation -->
    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Container -->
    <main>
        <header>
            <div>
                <h1>Operations Shift Log</h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">Record operator observations, maintenance tasks, and view historical system alarms</p>
            </div>
            <div class="timestamp" id="live-clock">--:--:--</div>
        </header>

        <div class="grid-2" style="grid-template-columns: 1fr 2fr;">
            <!-- Left Side: Log Entry Form -->
            <div class="card" style="height: fit-content;">
                <h2>Record Shift Activity</h2>
                <form id="add-log-form" style="display: flex; flex-direction: column; gap: 1.2rem;">
                    
                    <div class="control-group">
                        <label class="control-label">Log Level / Category</label>
                        <select name="type" required>
                            <option value="Info">Info (Standard shift update)</option>
                            <option value="Maintenance">Maintenance (Turbine service notes)</option>
                            <option value="Warning">Warning (Operational threshold check)</option>
                            <option value="Alarm">Alarm (Emergency plant events)</option>
                        </select>
                    </div>

                    <div class="control-group">
                        <label class="control-label">System Source / Asset</label>
                        <input type="text" name="source" placeholder="e.g. Turbine B, Spillway, Shift Operator" required>
                    </div>

                    <div class="control-group">
                        <label class="control-label">Observation Details</label>
                        <textarea name="message" rows="5" placeholder="Describe the event, parameters adjusted, or maintenance actions taken..." required></textarea>
                    </div>

                    <button type="submit" class="btn">
                        <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2.5; display: inline-block;">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Log Event
                    </button>
                </form>
            </div>

            <!-- Right Side: Historical Logs Table -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
                    <h2 style="margin-bottom: 0;">Log Records (Last 50 Events)</h2>
                    
                    <div class="filter-bar" style="margin-bottom: 0;">
                        <input type="text" id="log-search" class="filter-input" placeholder="Search by source/message..." style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                        <select id="log-type-filter" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                            <option value="">All Levels</option>
                            <option value="INFO">Info</option>
                            <option value="MAINTENANCE">Maintenance</option>
                            <option value="WARNING">Warning</option>
                            <option value="ALARM">Alarm</option>
                        </select>
                    </div>
                </div>

                <div class="logs-table-container" style="max-height: 520px; overflow-y: auto;">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Category</th>
                                <th>Source</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $l): ?>
                                <tr>
                                    <td class="time"><?php echo date('Y-m-d H:i:s', strtotime($l['timestamp'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($l['type']); ?>">
                                            <?php echo $l['type']; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($l['source']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($l['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="app.js"></script>
</body>
</html>
