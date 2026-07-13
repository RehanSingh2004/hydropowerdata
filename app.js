// app.js - Interactive dashboard calculations and AJAX operations

document.addEventListener('DOMContentLoaded', () => {
    // 1. Dynamic Clock / Timestamp Update
    updateClock();
    setInterval(updateClock, 1000);

    // 2. Turbine Interactive Parameter Calculations
    initTurbineSliders();

    // 3. Log Filters
    initLogFilters();

    // 4. Forms & AJAX Submissions
    initFormHandlers();

    // 5. Live Plant Telemetry Simulator (Every 5 seconds)
    if (document.getElementById('dashboard-view')) {
        setInterval(fetchTelemetryUpdate, 5000);
    }
});

function updateClock() {
    const clockEl = document.getElementById('live-clock');
    if (clockEl) {
        const now = new Date();
        clockEl.textContent = now.toLocaleDateString() + ' ' + now.toLocaleTimeString();
    }
}

// Live calculation formula: P = efficiency * 9.81 * flow * head / 1000
function calculateHydraulicPower(flow, head, efficiency) {
    if (flow <= 0) return 0;
    const power = efficiency * 9.81 * flow * head / 1000;
    return Math.min(50.0, parseFloat(power.toFixed(2))); // Cap at max capacity
}

function initTurbineSliders() {
    const turbineCards = document.querySelectorAll('.turbine-card');
    
    turbineCards.forEach(card => {
        const slider = card.querySelector('.flow-slider');
        const statusSelect = card.querySelector('.status-select');
        const flowValueEl = card.querySelector('.flow-value');
        const powerValueEl = card.querySelector('.power-value');
        const efficiencyEl = card.querySelector('.efficiency-value');
        const headEl = card.querySelector('.head-value');
        
        if (!slider) return;

        const maxFlow = parseFloat(slider.dataset.maxFlow || 45);
        const efficiency = parseFloat(efficiencyEl.dataset.efficiency || 0.88);
        const head = parseFloat(headEl.dataset.head || 85.0);

        // Update UI dynamically on slider input
        const updateCalculations = () => {
            const status = statusSelect.value;
            let flow = 0;
            let power = 0;

            if (status === 'Active') {
                const percentage = parseFloat(slider.value) / 100;
                flow = parseFloat((maxFlow * percentage).toFixed(1));
                power = calculateHydraulicPower(flow, head, efficiency);
                slider.disabled = false;
            } else {
                slider.value = 0;
                slider.disabled = true;
            }

            flowValueEl.textContent = flow.toFixed(1);
            powerValueEl.textContent = power.toFixed(2);
            
            // Highlight changes in telemetry
            powerValueEl.classList.add('telemetry-highlight');
            setTimeout(() => powerValueEl.classList.remove('telemetry-highlight'), 300);
        };

        slider.addEventListener('input', updateCalculations);
        statusSelect.addEventListener('change', () => {
            if (statusSelect.value !== 'Active') {
                slider.value = 0;
            } else {
                slider.value = 80; // default active gate
            }
            updateCalculations();
        });
    });
}

function showToast(message, type = 'success') {
    // Check if there is an existing banner
    let banner = document.getElementById('alert-toast');
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'alert-toast';
        banner.style.position = 'fixed';
        banner.style.bottom = '20px';
        banner.style.right = '20px';
        banner.style.zIndex = '1000';
        banner.style.minWidth = '300px';
        banner.style.transition = 'all 0.3s ease';
        document.body.appendChild(banner);
    }
    
    const alertColor = type === 'success' ? 'var(--color-active)' : 'var(--color-offline)';
    const bgColor = type === 'success' ? 'rgba(0, 255, 135, 0.1)' : 'rgba(255, 77, 109, 0.1)';
    
    banner.innerHTML = `
        <div style="background: ${bgColor}; border: 1px solid ${alertColor}; color: #fff; padding: 1rem; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
            <span>${message}</span>
            <button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer; font-size:1.1rem; margin-left:1rem;">&times;</button>
        </div>
    `;
    
    setTimeout(() => {
        if (banner) banner.innerHTML = '';
    }, 4000);
}

function initFormHandlers() {
    // Turbine Update Forms
    const turbineForms = document.querySelectorAll('.turbine-control-form');
    turbineForms.forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('action', 'update_turbine');

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showToast(`${result.name} parameters successfully updated.`);
                    // Update main badge
                    const card = form.closest('.turbine-card');
                    const badge = card.querySelector('.status-indicator');
                    
                    // Remove all status classes
                    badge.className = 'status-indicator';
                    badge.classList.add('status-' + result.status.toLowerCase());
                    badge.querySelector('span:last-child').textContent = result.status;
                } else {
                    showToast(result.error || 'Failed to update turbine.', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Network error updating turbine parameters.', 'error');
            }
        });
    });

    // Spillway Form
    const spillwayForm = document.getElementById('spillway-control-form');
    if (spillwayForm) {
        const slider = document.getElementById('spillway-gate-slider');
        const label = document.getElementById('spillway-gate-val');
        
        slider.addEventListener('input', () => {
            label.textContent = slider.value + '%';
        });

        spillwayForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(spillwayForm);
            formData.append('action', 'update_spillway');

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showToast(`Spillway gates set to ${result.spillway_gate}%.`);
                } else {
                    showToast(result.error || 'Failed to update spillway gates.', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Network error updating spillway parameters.', 'error');
            }
        });
    }

    // Add Log Form
    const logForm = document.getElementById('add-log-form');
    if (logForm) {
        logForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(logForm);
            formData.append('action', 'add_log');

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showToast('Operator shift log saved successfully.');
                    logForm.reset();
                    
                    // If log table exists on the page, prepend the new log row
                    const tbody = document.querySelector('.logs-table tbody');
                    if (tbody) {
                        const newRow = document.createElement('tr');
                        newRow.innerHTML = `
                            <td class="time">${result.log.timestamp}</td>
                            <td><span class="badge badge-${result.log.type.toLowerCase()}">${result.log.type}</span></td>
                            <td><strong>${result.log.source}</strong></td>
                            <td>${result.log.message}</td>
                        `;
                        tbody.insertBefore(newRow, tbody.firstChild);
                        // Limit rows to 25 if overflowing
                        if (tbody.children.length > 25) {
                            tbody.lastChild.remove();
                        }
                    }
                } else {
                    showToast(result.error || 'Failed to record log entry.', 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('Network error submitting log.', 'error');
            }
        });
    }
}

function initLogFilters() {
    const searchInput = document.getElementById('log-search');
    const typeSelect = document.getElementById('log-type-filter');
    const tableBody = document.querySelector('.logs-table tbody');

    if (!searchInput || !tableBody) return;

    const filterRows = () => {
        const query = searchInput.value.toLowerCase();
        const selectedType = typeSelect.value;
        const rows = tableBody.querySelectorAll('tr');

        rows.forEach(row => {
            const badge = row.querySelector('.badge');
            const source = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const message = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            
            const matchesQuery = source.includes(query) || message.includes(query);
            const matchesType = !selectedType || (badge && badge.textContent === selectedType);

            if (matchesQuery && matchesType) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    };

    searchInput.addEventListener('input', filterRows);
    typeSelect.addEventListener('change', filterRows);
}

async function fetchTelemetryUpdate() {
    try {
        const response = await fetch('api.php?action=get_telemetry');
        const data = await response.json();
        
        if (!data.success) return;

        // Smoothly update KPI cards
        animateKpiValue('total-power', data.total_power, 2, ' MW');
        animateKpiValue('avg-efficiency', data.avg_efficiency, 1, '%');
        animateKpiValue('active-turbines', data.active_turbines, 0, ' / 6');
        animateKpiValue('water-level', data.water_level, 2, 'm');

        // Update Reservoir height overlay
        const heightPercent = data.reservoir_percent;
        const waterVisualEl = document.getElementById('reservoir-water-overlay');
        const waterPercentageEl = document.getElementById('reservoir-percentage-lbl');
        
        if (waterVisualEl) {
            waterVisualEl.style.height = heightPercent + '%';
        }
        if (waterPercentageEl) {
            waterPercentageEl.textContent = heightPercent.toFixed(1) + '%';
        }

        // ---- Update Sludge indicators (water.php) ----
        if (data.sludge_level !== undefined) {
            const sludge = data.sludge_level;
            const powerLoss = data.power_loss_pct;

            const sludgeLevelEl = document.getElementById('sludge-level-display');
            const powerLossEl   = document.getElementById('power-loss-display');
            const sludgeBarFill = document.getElementById('sludge-bar-fill');
            const sludgeBarLbl  = document.getElementById('sludge-bar-label');
            const sludgeOverlay = document.getElementById('sludge-pct-overlay');
            const sludgeLayer   = document.getElementById('sludge-layer');

            if (sludgeLevelEl) sludgeLevelEl.innerHTML = sludge.toFixed(1) + '<span style="font-size:1rem;font-weight:500;">%</span>';
            if (powerLossEl)   powerLossEl.innerHTML   = powerLoss.toFixed(1) + '<span style="font-size:1rem;font-weight:500;">%</span>';
            if (sludgeBarLbl)  sludgeBarLbl.textContent = sludge.toFixed(1) + '%';
            if (sludgeOverlay) sludgeOverlay.textContent = sludge.toFixed(1) + '%';

            if (sludgeBarFill) {
                sludgeBarFill.style.width = sludge + '%';
                if (sludge >= 60) {
                    sludgeBarFill.style.background = 'linear-gradient(90deg, #c9184a, #ff4d6d)';
                } else if (sludge >= 30) {
                    sludgeBarFill.style.background = 'linear-gradient(90deg, #e07c00, #ffb703)';
                } else {
                    sludgeBarFill.style.background = 'linear-gradient(90deg, #6c7a3a, #a8b400)';
                }
            }

            if (sludgeLayer) {
                sludgeLayer.style.height = Math.min(60, sludge * 0.55) + '%';
            }
        }

        // Add telemetry highlight class for dynamic flare
        document.querySelectorAll('.kpi-value').forEach(el => {
            el.classList.add('telemetry-highlight');
            setTimeout(() => el.classList.remove('telemetry-highlight'), 300);
        });

    } catch (err) {
        console.warn('Failed to fetch live telemetry updates: ', err);
    }
}

function animateKpiValue(id, targetValue, decimals = 2, suffix = '') {
    const el = document.getElementById(id);
    if (!el) return;

    // Use current value as starting point, stripping formatting
    const currentText = el.textContent.replace(suffix, '').trim();
    let startValue = parseFloat(currentText) || 0;
    if (id === 'active-turbines') {
        startValue = parseInt(currentText.split('/')[0]) || 0;
    }

    const duration = 1000;
    const startTime = performance.now();

    function updateAnimation(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function
        const easeProgress = 1 - Math.pow(1 - progress, 3);
        const currentValue = startValue + (targetValue - startValue) * easeProgress;

        if (id === 'active-turbines') {
            el.innerHTML = `${Math.round(currentValue)}<span>${suffix}</span>`;
        } else {
            el.innerHTML = `${currentValue.toFixed(decimals)}<span>${suffix}</span>`;
        }

        if (progress < 1) {
            requestAnimationFrame(updateAnimation);
        }
    }

    requestAnimationFrame(updateAnimation);
}
