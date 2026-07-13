<?php
// sidebar.php - Shared sidebar layout for PowerPulse navigation

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside>
    <div class="brand">
        <div class="brand-logo">
            <svg viewBox="0 0 24 24">
                <path d="M12 2L2 22h9l-3 9 14-16h-9l3-7z" />
            </svg>
        </div>
        <div class="brand-name">PowerPulse</div>
    </div>
    
    <ul class="nav-links">
        <li class="<?php echo ($currentPage == 'index.php' || $currentPage == '') ? 'active' : ''; ?>">
            <a href="index.php">
                <svg viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="9" rx="1" />
                    <rect x="14" y="3" width="7" height="5" rx="1" />
                    <rect x="14" y="12" width="7" height="9" rx="1" />
                    <rect x="3" y="16" width="7" height="5" rx="1" />
                </svg>
                Dashboard
            </a>
        </li>
        <li class="<?php echo ($currentPage == 'turbines.php') ? 'active' : ''; ?>">
            <a href="turbines.php">
                <svg viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 3v18M3 12h18" />
                    <path d="M18.36 5.64L5.64 18.36M18.36 18.36L5.64 5.64" />
                </svg>
                Turbine Control
            </a>
        </li>
        <li class="<?php echo ($currentPage == 'water.php') ? 'active' : ''; ?>">
            <a href="water.php">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                </svg>
                Reservoir &amp; Water
            </a>
        </li>
        <li class="<?php echo ($currentPage == 'logs.php') ? 'active' : ''; ?>">
            <a href="logs.php">
                <svg viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                    <line x1="16" y1="13" x2="8" y2="13" />
                    <line x1="16" y1="17" x2="8" y2="17" />
                    <polyline points="10 9 9 9 8 9" />
                </svg>
                Operations Log
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <span class="operator-label">Plant Operator</span>
        <span class="operator-name">Shift Control A</span>
        <span class="timestamp" id="live-clock">--:--:--</span>
    </div>
</aside>
