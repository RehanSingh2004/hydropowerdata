<?php
// helpers.php - Shared helper functions across all pages and api.php

// Power Output (MW) = efficiency * 9.81 * flow * head / 1000
// Sludge reduces power by up to 20% at 100% sludge level
function getPowerOutput($flow, $head, $efficiency, $sludgeLevel = 0.0) {
    if ($flow <= 0) return 0.0;
    $rawPower = $efficiency * 9.81 * $flow * $head / 1000;
    $sludgeFactor = 1.0 - (0.20 * ($sludgeLevel / 100.0));
    return min(50.0, round($rawPower * $sludgeFactor, 2));
}
