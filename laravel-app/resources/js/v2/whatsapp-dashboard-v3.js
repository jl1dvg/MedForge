import Chart from 'chart.js/auto';

if (!window.Chart) {
    window.Chart = Chart;
}

Chart.defaults.font.family = '"IBM Plex Sans", system-ui, sans-serif';
Chart.defaults.color = '#7e8299';

const PALETTE = ['#5156be', '#3596f7', '#05825f', '#ffa800', '#ee3158', '#7479d4'];

function buildChart(id, config) {
    const el = document.getElementById(id);
    if (!el) return null;
    return new Chart(el, config);
}

// ── Embudo de servicio (vertical bar) ──────────────────────────────────────
(function () {
    const el = document.getElementById('wad-chart-embudo');
    if (!el) return;
    const labels = JSON.parse(el.dataset.labels || '[]');
    const values = JSON.parse(el.dataset.values || '[]');
    const colors = ['#5156be', '#3596f7', '#0863be', '#05825f'];

    new Chart(el, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderRadius: 6,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y.toLocaleString()} conversaciones`,
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: {
                    grid: { color: '#ebedf3' },
                    border: { display: false },
                    ticks: { precision: 0 },
                },
            },
        },
    });
}());

// ── Handoffs por equipo (horizontal stacked bar) ───────────────────────────
(function () {
    const el = document.getElementById('wad-chart-handoffs');
    if (!el) return;
    const labels  = JSON.parse(el.dataset.labels  || '[]');
    const queued   = JSON.parse(el.dataset.queued  || '[]');
    const assigned = JSON.parse(el.dataset.assigned|| '[]');
    const resolved = JSON.parse(el.dataset.resolved|| '[]');

    new Chart(el, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'En cola',    data: queued,   backgroundColor: '#ffa800', borderRadius: 0 },
                { label: 'Asignadas',  data: assigned, backgroundColor: '#3596f7', borderRadius: 0 },
                { label: 'Resueltas',  data: resolved, backgroundColor: '#05825f', borderRadius: 0 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, boxHeight: 10, padding: 10, font: { size: 11 } },
                },
                tooltip: { mode: 'index' },
            },
            scales: {
                x: { stacked: true, grid: { color: '#ebedf3' }, border: { display: false }, ticks: { precision: 0 } },
                y: { stacked: true, grid: { display: false }, border: { display: false } },
            },
        },
    });
}());

// ── Origen de demanda (donut) ──────────────────────────────────────────────
(function () {
    const el = document.getElementById('wad-chart-origen');
    if (!el) return;
    const labels = JSON.parse(el.dataset.labels || '[]');
    const values = JSON.parse(el.dataset.values || '[]');

    new Chart(el, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: PALETTE,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, boxHeight: 10, padding: 10, font: { size: 11 } },
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.parsed.toLocaleString()} (${ctx.dataset.data.reduce((a,b)=>a+b,0) > 0 ? Math.round(ctx.parsed / ctx.dataset.data.reduce((a,b)=>a+b,0) * 100) : 0}%)`,
                    },
                },
            },
        },
    });
}());

// ── Bot & Flujo (donut contenido vs escalado) ──────────────────────────────
(function () {
    const el = document.getElementById('wad-chart-bot');
    if (!el) return;
    const contain  = parseFloat(el.dataset.contain  || '0');
    const escalado = parseFloat(el.dataset.escalado || '0');

    new Chart(el, {
        type: 'doughnut',
        data: {
            labels: ['Contenido por bot', 'Escalado a humano'],
            datasets: [{
                data: [contain, escalado],
                backgroundColor: ['#05825f', '#ee3158'],
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, boxHeight: 10, padding: 8, font: { size: 11 } },
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.parsed}%`,
                    },
                },
            },
        },
    });
}());
