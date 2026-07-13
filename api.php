<?php
// api.php - Backend API: simulation, turbine control, reservoir, logs

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// -----------------------------------------------------------------------
// Physics Simulation Step
// -----------------------------------------------------------------------
function runSimulationStep($pdo) {
    $stmt = $pdo->query("SELECT * FROM reservoir WHERE id = 1");
    $res = $stmt->fetch();
    if (!$res) return;

    // Active turbine total outflow
    $stmt = $pdo->query("SELECT * FROM turbines WHERE status = 'Active'");
    $activeTurbines = $stmt->fetchAll();
    $turbineOutflow = 0.0;
    foreach ($activeTurbines as $t) {
        $turbineOutflow += $t['flow_rate'];
    }

    // Spillway outflow (max 400 m³/s)
    $spillwayOutflow = 400.0 * ($res['spillway_gate'] / 100);
    $totalOutflow = $turbineOutflow + $spillwayOutflow;

    // Inflow random walk (±5 m³/s), clamped 100–600
    $newInflow = max(100.0, min(600.0, $res['inflow'] + rand(-50, 50) / 10));

    // Volume change over 5s step
    $netFlow = $newInflow - $totalOutflow;
    $volumeChange = ($netFlow * 5) / 1_000_000;
    $newVolume = max(0.0, min($res['max_volume'], $res['current_volume'] + $volumeChange));

    // Head height proportional to volume
    $levelFraction = $newVolume / $res['max_volume'];
    $newLevel = $res['min_head'] + ($res['max_head'] - $res['min_head']) * $levelFraction;

    // ---- Sludge accumulation: +0.02% to +0.08% per simulation tick ----
    $sludgeIncrease = rand(2, 8) / 100;
    $newSludge = min(100.0, $res['sludge_level'] + $sludgeIncrease);

    // Update reservoir
    $stmt = $pdo->prepare("UPDATE reservoir SET water_level=?, current_volume=?, inflow=?, sludge_level=? WHERE id=1");
    $stmt->execute([$newLevel, $newVolume, $newInflow, $newSludge]);

    // Update active turbine heads
    $pdo->prepare("UPDATE turbines SET head=? WHERE status='Active'")->execute([$newLevel]);

    // Efficiency micro-fluctuations
    foreach ($activeTurbines as $t) {
        $newEff = max(0.85, min(0.95, $t['efficiency'] + rand(-5, 5) / 1000));
        $pdo->prepare("UPDATE turbines SET efficiency=? WHERE id=?")->execute([$newEff, $t['id']]);
    }

    // ---- Sludge threshold alarms (avoid spam: max 1 per 5 min) ----
    $recentCheck = "SELECT COUNT(*) FROM logs WHERE source='Reservoir' AND timestamp > datetime('now','-5 minutes')";

    if ($newSludge >= 60.0 && $newSludge - $sludgeIncrease < 60.0) {
        if ($pdo->query($recentCheck)->fetchColumn() == 0) {
            $msg = sprintf('CRITICAL: Reservoir sludge level has reached %.1f%%. Immediate desilting operation required! Power generation reduced by up to %.1f%%.', $newSludge, $newSludge * 0.20);
            $pdo->prepare("INSERT INTO logs (type, source, message) VALUES ('Alarm','Reservoir',?)")->execute([$msg]);
        }
    } elseif ($newSludge >= 30.0 && $newSludge - $sludgeIncrease < 30.0) {
        if ($pdo->query($recentCheck)->fetchColumn() == 0) {
            $msg = sprintf('Warning: Sludge accumulation detected at %.1f%%. Power output degraded by %.1f%%. Schedule reservoir cleaning.', $newSludge, $newSludge * 0.20);
            $pdo->prepare("INSERT INTO logs (type, source, message) VALUES ('Warning','Reservoir',?)")->execute([$msg]);
        }
    }

    // Reservoir water-level alarms
    $percentFull = ($newVolume / $res['max_volume']) * 100;
    if ($percentFull >= 95.0 && $percentFull - (($netFlow * 5 / 1_000_000) / $res['max_volume'] * 100) < 95.0) {
        $pdo->prepare("INSERT INTO logs (type, source, message) VALUES ('Alarm','Reservoir','CRITICAL: Reservoir water level exceeds 95% capacity. Spillway gate adjustment required!')")->execute();
    }
}

// -----------------------------------------------------------------------
// Request Handling
// -----------------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    // ---- GET: Live telemetry ----
    if ($method === 'GET' && $action === 'get_telemetry') {
        runSimulationStep($pdo);

        $stmt = $pdo->query("SELECT * FROM turbines");
        $turbines = $stmt->fetchAll();
        $stmt = $pdo->query("SELECT * FROM reservoir WHERE id=1");
        $res = $stmt->fetch();

        $totalPower   = 0.0;
        $activeCount  = 0;
        $effSum       = 0.0;
        $sludge       = $res['sludge_level'];

        foreach ($turbines as $t) {
            if ($t['status'] === 'Active') {
                $totalPower += getPowerOutput($t['flow_rate'], $t['head'], $t['efficiency'], $sludge);
                $activeCount++;
                $effSum += $t['efficiency'];
            }
        }

        $avgEfficiency = $activeCount > 0 ? ($effSum / $activeCount) * 100 : 0.0;

        echo json_encode([
            'success'           => true,
            'total_power'       => round($totalPower, 2),
            'avg_efficiency'    => round($avgEfficiency, 1),
            'active_turbines'   => $activeCount,
            'water_level'       => round($res['water_level'], 2),
            'reservoir_percent' => round(($res['current_volume'] / $res['max_volume']) * 100, 2),
            'sludge_level'      => round($sludge, 2),
            'power_loss_pct'    => round($sludge * 0.20, 1),
        ]);
        exit;
    }

    // ---- POST: Clean reservoir ----
    if ($method === 'POST' && $action === 'clean_reservoir') {
        $stmt = $pdo->query("SELECT sludge_level FROM reservoir WHERE id=1");
        $oldSludge = round($stmt->fetchColumn(), 1);

        $pdo->prepare("UPDATE reservoir SET sludge_level=0.0 WHERE id=1")->execute();

        $msg = "Reservoir desilting and cleaning operation completed. Sludge cleared from {$oldSludge}% to 0.0%. Full generation capacity restored.";
        $pdo->prepare("INSERT INTO logs (type, source, message) VALUES ('Maintenance','Reservoir',?)")->execute([$msg]);

        echo json_encode([
            'success'     => true,
            'old_sludge'  => $oldSludge,
            'sludge_level'=> 0.0,
            'message'     => $msg,
        ]);
        exit;
    }

    // ---- POST: Update turbine ----
    if ($method === 'POST' && $action === 'update_turbine') {
        $id         = filter_input(INPUT_POST, 'id',           FILTER_VALIDATE_INT);
        $status     = filter_input(INPUT_POST, 'status',       FILTER_DEFAULT);
        $flowGate   = filter_input(INPUT_POST, 'gate_opening', FILTER_VALIDATE_FLOAT);

        if ($id === false || !$status || $flowGate === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
            exit;
        }
        if (!in_array($status, ['Active', 'Maintenance', 'Offline'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid status.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM turbines WHERE id=?");
        $stmt->execute([$id]);
        $t = $stmt->fetch();
        if (!$t) { echo json_encode(['success' => false, 'error' => 'Turbine not found.']); exit; }

        $head    = $pdo->query("SELECT water_level FROM reservoir WHERE id=1")->fetchColumn();
        $sludge  = $pdo->query("SELECT sludge_level FROM reservoir WHERE id=1")->fetchColumn();
        $maxFlow = 45.0;
        $newFlow = ($status === 'Active') ? round($maxFlow * ($flowGate / 100), 1) : 0.0;

        $pdo->prepare("UPDATE turbines SET status=?, flow_rate=?, head=? WHERE id=?")->execute([$status, $newFlow, $head, $id]);

        $logMsg  = "{$t['name']} set to {$status}";
        $logMsg .= ($status === 'Active') ? " with gate opening {$flowGate}%." : ". Flow halted.";
        $logType = ($status === 'Maintenance') ? 'Maintenance' : 'Info';
        $pdo->prepare("INSERT INTO logs (type, source, message) VALUES (?,?,?)")->execute([$logType, $t['name'], $logMsg]);

        echo json_encode([
            'success'    => true,
            'name'       => $t['name'],
            'status'     => $status,
            'flow_rate'  => $newFlow,
            'power'      => getPowerOutput($newFlow, $head, $t['efficiency'], $sludge),
        ]);
        exit;
    }

    // ---- POST: Update spillway ----
    if ($method === 'POST' && $action === 'update_spillway') {
        $gate = filter_input(INPUT_POST, 'spillway_gate', FILTER_VALIDATE_FLOAT);
        if ($gate === false || $gate < 0 || $gate > 100) {
            echo json_encode(['success' => false, 'error' => 'Invalid gate value.']);
            exit;
        }
        $old = $pdo->query("SELECT spillway_gate FROM reservoir WHERE id=1")->fetchColumn();
        $pdo->prepare("UPDATE reservoir SET spillway_gate=? WHERE id=1")->execute([$gate]);

        if (abs($gate - $old) > 0.01) {
            $logType = $gate > 50 ? 'Warning' : 'Info';
            $pdo->prepare("INSERT INTO logs (type, source, message) VALUES (?,'Reservoir',?)")
                ->execute([$logType, "Spillway bypass gates set to {$gate}% opening."]);
        }

        echo json_encode(['success' => true, 'spillway_gate' => $gate]);
        exit;
    }

    // ---- POST: Add log ----
    if ($method === 'POST' && $action === 'add_log') {
        $type    = filter_input(INPUT_POST, 'type',    FILTER_DEFAULT);
        $source  = filter_input(INPUT_POST, 'source',  FILTER_DEFAULT);
        $message = filter_input(INPUT_POST, 'message', FILTER_DEFAULT);

        if (!$type || !$source || !$message) {
            echo json_encode(['success' => false, 'error' => 'All fields required.']);
            exit;
        }
        if (!in_array($type, ['Info', 'Maintenance', 'Warning', 'Alarm'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid log type.']);
            exit;
        }

        $pdo->prepare("INSERT INTO logs (type, source, message) VALUES (?,?,?)")->execute([$type, $source, $message]);
        $logId = $pdo->lastInsertId();
        $stmt  = $pdo->prepare("SELECT * FROM logs WHERE id=?");
        $stmt->execute([$logId]);
        $newLog = $stmt->fetch();
        $newLog['timestamp'] = date('Y-m-d H:i:s', strtotime($newLog['timestamp']));

        echo json_encode(['success' => true, 'log' => $newLog]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'API Error: ' . $e->getMessage()]);
}
