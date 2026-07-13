# PowerPulse – Hydropower Performance Dashboard

A **PHP + SQLite** web application for monitoring and simulating a hydropower plant's real-time performance. Built with a premium dark-mode glassmorphism UI.

## Features

- **Live Dashboard** — Real-time KPI cards (Power Output, Plant Efficiency, Active Turbines, Reservoir Head) with animated telemetry and SVG generation trend charts
- **Turbine Control Room** — Manage 6 generator units: adjust hydraulic gate openings (flow rate %), toggle operational status (Active / Maintenance / Offline), and see live power output calculated using the hydraulic formula:
  > P = η × ρg × Q × H / 1000 (MW)
- **Reservoir & Water Management** — Visual reservoir fill level, inflow/outflow balance, spillway bypass gate controls
- **Sludge Monitoring & Desilting** — Sediment accumulates in the reservoir over time, reducing turbine head and power output (up to −20% at full sludge). Visual sludge layer in the reservoir graphic, colour-coded progress bar, threshold alarms (30% Warning / 60% Critical), and a **Clean Reservoir** button to restore full capacity
- **Operations Shift Log** — Operators can record shift events, maintenance notes, and alarms with category filters and live search

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3 |
| Database | SQLite (via PHP PDO) — zero config, self-contained |
| Frontend | Vanilla HTML/CSS/JavaScript |
| Styling | Custom glassmorphism dark-mode CSS |
| Fonts | Google Fonts (Outfit + JetBrains Mono) |

## Project Structure

```
phpproject/
├── index.php       # Main dashboard page
├── turbines.php    # Turbine control room
├── water.php       # Reservoir & sludge management
├── logs.php        # Operations shift logbook
├── sidebar.php     # Shared navigation component
├── api.php         # Backend REST-like API + physics simulator
├── db.php          # SQLite database initialization & seeding
├── helpers.php     # Shared helper functions (power formula)
├── style.css       # Full design system & glassmorphism styles
└── app.js          # Frontend interactivity, AJAX, telemetry tickers
```

> **Note:** `powerpulse.db` is excluded from version control. It is created and seeded automatically on the first run.

## Running Locally

### Requirements
- PHP 8.x with the `pdo_sqlite` and `sqlite3` extensions enabled

### Steps

```bash
# Clone the repository
git clone https://github.com/RehanSingh2004/phpproject.git
cd phpproject

# Start the PHP built-in development server
php -S localhost:8000
```

Then open your browser at **http://localhost:8000**

The SQLite database (`powerpulse.db`) will be created and seeded automatically on the first page load.

## Physics Engine

The backend simulates live plant behaviour on every telemetry poll:

- **Power Output**: `P = η × 9.81 × Q × H / 1000 MW`, reduced by sludge factor
- **Sludge Degradation**: `P_actual = P_nominal × (1 − 0.20 × SludgeLevel%)`
- **Reservoir Volume**: Net flow (Inflow − Turbine Outflow − Spillway Outflow) integrated over 5-second ticks
- **Head Height**: Linearly interpolated from current reservoir volume
- **Efficiency Drift**: ±0.5% random walk per tick, bounded 85%–95%
- **Sludge Growth**: +0.02% to +0.08% per tick; triggers Warning at 30%, Alarm at 60%
