# 🌊 PowerPulse – Hydropower Performance Dashboard

A **PHP + SQLite** web application for real-time monitoring and simulation of hydropower plant performance. Built with a premium dark-mode glassmorphism UI and physics-based simulation engine.

<div align="center">

**[Features](#features) • [Quick Start](#quick-start) • [Architecture](#architecture) • [API](#api-endpoints) • [Contributing](#contributing)**

</div>

---

## ✨ Features

### 📊 **Live Dashboard**
Real-time KPI monitoring with animated telemetry updates:
- **Power Output** — Current generation in MW with live trend
- **Plant Efficiency** — ±0.5% dynamic efficiency modeling
- **Active Turbines** — Count of operational units
- **Reservoir Head** — Water pressure in meters
- Animated SVG trend charts and color-coded status indicators

### 🎮 **Turbine Control Room**
Manage 6 independent generator units with real-time physics simulation:
- **Flow Rate Control** — Adjust hydraulic gate openings (0–100%)
- **Operational Status** — Toggle between Active / Maintenance / Offline
- **Live Power Calculation** — Uses hydropower formula: `P = η × ρg × Q × H / 1000 (MW)`
- Individual unit monitoring and performance metrics

### 💧 **Reservoir & Water Management**
Visual water level management and sludge tracking:
- **Reservoir Fill Level** — Dynamic visualization with inflow/outflow balance
- **Sludge Monitoring** — Accumulating sediment reduces power output by up to 20%
- **Spillway Controls** — Bypass gate management
- **Visual Sludge Layer** — Color-coded progress bar (Green → Yellow → Red)
- **Desilting Action** — One-click reservoir cleaning

### ⚠️ **Sludge Monitoring & Alerts**
Intelligent degradation model:
- Automatic sludge accumulation (0.02–0.08% per tick)
- **30% Threshold** — Warning state activated
- **60% Threshold** — Critical alarm triggered
- Real-time impact on power output reduction
- **Clean Reservoir** button to restore full capacity

### 📋 **Operations Shift Log**
Professional event tracking and audit trail:
- Record shift events, maintenance notes, and alarms
- Category filters (Maintenance, Alarm, Info)
- Live search and filtering
- Timestamped entries with operator attribution

---

## 🛠️ Tech Stack

| Layer | Technology | Purpose |
|---|---|---|
| **Backend** | PHP 8.3+ | REST API & physics simulation |
| **Database** | SQLite (PDO) | Zero-config, self-contained data store |
| **Frontend** | Vanilla JS | Interactivity & AJAX |
| **Styling** | Custom CSS | Glassmorphism dark-mode design |
| **Fonts** | Google Fonts | Outfit + JetBrains Mono |

---

## 📁 Project Structure

```
phpproject/
├── index.php           # Dashboard (KPIs, trends, alerts)
├── turbines.php        # Turbine control interface
├── water.php           # Reservoir & sludge management UI
├── logs.php            # Operations shift logbook
├── sidebar.php         # Shared navigation component
├── api.php             # REST API + physics simulation engine
├── db.php              # SQLite init & data seeding
├── helpers.php         # Utility functions (power formula, etc.)
├── style.css           # Complete design system
├── app.js              # Frontend state & telemetry management
├── README.md           # This file
└── .gitignore          # Excludes powerpulse.db
```

**Database:**
- `powerpulse.db` — Created automatically on first run (excluded from VCS)

---

## 🚀 Quick Start

### Prerequisites
- **PHP 8.0+** with `pdo_sqlite` and `sqlite3` extensions
- Bash or PowerShell terminal

### Installation

```bash
# 1. Clone repository
git clone https://github.com/RehanSingh2004/phpproject.git
cd phpproject

# 2. Start PHP development server
php -S localhost:8000

# 3. Open in browser
# → http://localhost:8000
```

✅ **The database will initialize automatically on first page load.**

### Deployment

Vercel does not provide a maintained PHP runtime, so this application cannot be deployed there with `@vercel/php`. A Render Blueprint is included in `render.yaml`; create a Render Blueprint from this GitHub repository to deploy the Docker image with persistent SQLite storage. The app listens on Apache's port 80 and initializes SQLite automatically.

For production use, set `POWERPULSE_DB_PATH` to a persistent mounted volume. Without persistent storage, the SQLite database is reset when the container is replaced.

---

## ⚙️ Physics Simulation Engine

The backend implements real-time hydropower plant physics on every 5-second telemetry tick:

### Power Output Formula
```
P = η × ρ × g × Q × H / 1000  [MW]

where:
  η = Efficiency (85–95%, ±0.5% drift per tick)
  ρ = Water density (1000 kg/m³)
  g = Gravitational acceleration (9.81 m/s²)
  Q = Turbine flow rate (m³/s)
  H = Reservoir head (meters)
```

### Sludge Degradation Model
```
P_actual = P_nominal × (1 − 0.20 × SludgeLevel%)
```
- Sludge growth: +0.02% to +0.08% per 5-second tick
- Max impact: −20% power reduction at 100% sludge
- Warning threshold: 30% | Critical threshold: 60%

### Reservoir Dynamics
```
dVolume/dt = (Inflow − Turbine_Outflow − Spillway_Outflow)
```
- Inflow: 12–18 m³/s (configurable)
- Turbine outflow: Scaled by gate opening (%)
- Spillway: Overflow protection
- Head interpolated from current volume

### Efficiency Drift
- Random walk model: `η_new = η_old + δ` (where δ ∈ [−0.5%, +0.5%])
- Bounds: [85%, 95%] (saturating)
- Simulates real-world turbulence and friction losses

---

## 🔌 API Endpoints

All endpoints use JSON request/response. No authentication layer (demo app).

### GET `/api.php?action=telemetry`
Returns real-time plant telemetry:
```json
{
  "status": "ok",
  "timestamp": 1234567890,
  "power_output": 45.2,
  "efficiency": 91.3,
  "active_turbines": 4,
  "reservoir_level": 87.5,
  "sludge_level": 28.4,
  "turbines": [
    { "id": 1, "gate_opening": 75, "power": 12.4, "status": "active" },
    ...
  ]
}
```

### POST `/api.php?action=adjust_turbine`
Adjust a turbine's gate opening:
```json
{ "turbine_id": 1, "gate_opening": 80 }
```

### POST `/api.php?action=toggle_turbine`
Change turbine operational status:
```json
{ "turbine_id": 1, "status": "maintenance" }
```

### POST `/api.php?action=adjust_spillway`
Control spillway bypass gate:
```json
{ "spillway_gate": 25 }
```

### POST `/api.php?action=clean_reservoir`
Desilting operation (resets sludge to 0%):
```json
{ "action": "clean_reservoir" }
```

### POST `/api.php?action=log_event`
Record shift event:
```json
{
  "message": "Generator 3 offline for maintenance",
  "category": "maintenance"
}
```

---

## 🎨 Design System

### Color Palette (Dark Mode)
- **Background**: `#0a0e27` (deep navy)
- **Surface**: `#141b35` (darker blue)
- **Accent**: `#00d9ff` (cyan)
- **Success**: `#00ff88` (neon green)
- **Warning**: `#ffaa00` (golden orange)
- **Critical**: `#ff0055` (hot pink)

### Components
- **Glassmorphism Cards** — Frosted glass effect with backdrop blur
- **Animated Metrics** — Smooth transitions and live tickers
- **Responsive Grid** — Mobile-friendly layout
- **SVG Trends** — Real-time chart generation

---

## 📖 Usage Guide

### Dashboard
1. Open **http://localhost:8000**
2. View live KPIs and plant status
3. Monitor reservoir level and sludge accumulation

### Turbine Control
1. Navigate to **Turbines** in sidebar
2. Adjust flow rate (0–100%) for each unit
3. Toggle operational status (Active → Maintenance → Offline)
4. View real-time power output per turbine

### Water Management
1. Go to **Water** page
2. Monitor spillway gate and reservoir balance
3. When sludge reaches 30%, a warning appears
4. Click **Clean Reservoir** to reset sludge to 0%

### Shift Logs
1. Open **Logs** section
2. Filter by category (Maintenance, Alarm, Info)
3. Use search bar to find specific events
4. Add new entries via the log form

---

## 🐛 Troubleshooting

### Database not created
- **Cause**: Missing `pdo_sqlite` extension
- **Fix**: Enable SQLite extension in `php.ini` and restart server

### "Cannot write database" error
- **Cause**: Permission issue on project directory
- **Fix**: Run with proper permissions or use a different directory

### Telemetry not updating
- **Cause**: Network issue or API endpoint unreachable
- **Fix**: Check browser console (F12 → Console) for AJAX errors

---

## 🤝 Contributing

Contributions are welcome! To contribute:

1. **Fork** the repository
2. **Create a branch** (`git checkout -b feature/amazing-feature`)
3. **Make changes** and test thoroughly
4. **Commit** with clear messages (`git commit -m 'Add amazing feature'`)
5. **Push** to your fork (`git push origin feature/amazing-feature`)
6. **Open a Pull Request**

### Development Notes
- Keep physics equations validated against real-world hydropower systems
- Maintain glassmorphism design consistency
- Test on both desktop and mobile viewports
- Update API documentation if changing endpoints

---

## 📄 License

This project is licensed under the **MIT License** — see [LICENSE](LICENSE) file for details.

---

## 👨‍💻 Author

**Rehan Singh**
- GitHub: [@RehanSingh2004](https://github.com/RehanSingh2004)
- Repository: [phpproject](https://github.com/RehanSingh2004/phpproject)

---

## 📞 Support

For bugs, feature requests, or questions:
- **Issues**: [GitHub Issues](https://github.com/RehanSingh2004/phpproject/issues)
- **Discussions**: [GitHub Discussions](https://github.com/RehanSingh2004/phpproject/discussions)

---

<div align="center">

**⭐ If you find this project useful, please consider giving it a star!**

Made with ❤️ by Rehan Singh

</div>
