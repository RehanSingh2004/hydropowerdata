<?php
// db.php - Database connection and initialization using SQLite

$dbPath = getenv('POWERPULSE_DB_PATH');
if (!$dbPath) {
    $defaultPath = __DIR__ . '/powerpulse.db';
    $tmpPath = sys_get_temp_dir() . '/powerpulse.db';
    $dbPath = is_writable(dirname($defaultPath)) ? $defaultPath : $tmpPath;
}

$dbExists = file_exists($dbPath);

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS turbines (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            status TEXT NOT NULL CHECK(status IN ('Active', 'Maintenance', 'Offline')),
            flow_rate REAL NOT NULL,
            head REAL NOT NULL,
            efficiency REAL NOT NULL,
            max_capacity REAL NOT NULL,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS reservoir (
            id INTEGER PRIMARY KEY CHECK(id = 1),
            water_level REAL NOT NULL,
            min_head REAL NOT NULL,
            max_head REAL NOT NULL,
            current_volume REAL NOT NULL,
            max_volume REAL NOT NULL,
            inflow REAL NOT NULL,
            spillway_gate REAL NOT NULL,
            sludge_level REAL NOT NULL DEFAULT 0.0
        );

        CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            type TEXT NOT NULL CHECK(type IN ('Info', 'Maintenance', 'Warning', 'Alarm')),
            source TEXT NOT NULL,
            message TEXT NOT NULL
        );
    ");

    if (!$dbExists || isDbEmpty($pdo)) {
        seedDatabase($pdo);
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function isDbEmpty($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM turbines");
    return $stmt->fetchColumn() == 0;
}

function seedDatabase($pdo) {
    $turbines = [
        ['Turbine A', 'Active',       40.0, 85.0, 0.90, 50.0],
        ['Turbine B', 'Active',       35.0, 85.0, 0.88, 50.0],
        ['Turbine C', 'Active',       38.0, 85.0, 0.89, 50.0],
        ['Turbine D', 'Active',       42.0, 85.0, 0.91, 50.0],
        ['Turbine E', 'Maintenance',   0.0, 85.0, 0.87, 50.0],
        ['Turbine F', 'Offline',       0.0, 85.0, 0.88, 50.0],
    ];

    $stmt = $pdo->prepare("INSERT INTO turbines (name, status, flow_rate, head, efficiency, max_capacity) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($turbines as $t) {
        $stmt->execute($t);
    }

    // sludge_level seeded at 5.0% (some initial sediment)
    $pdo->exec("INSERT INTO reservoir (id, water_level, min_head, max_head, current_volume, max_volume, inflow, spillway_gate, sludge_level)
                VALUES (1, 85.0, 50.0, 100.0, 142.5, 200.0, 280.0, 0.0, 5.0)");

    $logs = [
        ['Info',        'System',    'PowerPulse monitoring system initialized.'],
        ['Info',        'Turbine A', 'Turbine A brought online after efficiency optimization.'],
        ['Maintenance', 'Turbine E', 'Scheduled maintenance: stator insulation check started.'],
        ['Warning',     'Reservoir', 'Reservoir inflow increased to 280 m³/s due to heavy precipitation.'],
        ['Info',        'Turbine F', 'Turbine F shut down to match grid demand load.'],
        ['Warning',     'Reservoir', 'Initial sludge level detected at 5.0%. Monitor accumulation.'],
    ];

    $stmt = $pdo->prepare("INSERT INTO logs (type, source, message) VALUES (?, ?, ?)");
    foreach ($logs as $l) {
        $stmt->execute($l);
    }
}
