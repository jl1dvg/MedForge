<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MedForge v2 Dashboard</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background: #f5f7fb;
            color: #1f2937;
        }

        .wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .title {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }

        .chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .chip {
            background: #e2e8f0;
            color: #334155;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .logout-link {
            display: inline-block;
            text-decoration: none;
            background: #fee2e2;
            color: #9f1239;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        }

        .label {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .value {
            font-size: 26px;
            font-weight: 700;
            line-height: 1.1;
        }

        .panel {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 12px;
        }

        .panel h3 {
            margin: 0 0 8px;
            font-size: 14px;
            color: #334155;
        }

        pre {
            margin: 0;
            font-size: 12px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 12px;
            display: none;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <h1 class="title">Dashboard v2</h1>
        <div class="chips">
            <span class="chip">Strangler</span>
            <span class="chip">Laravel /v2</span>
            <span class="chip" id="dateRangeChip">Rango: --</span>
            <a href="/v2/auth/logout" class="logout-link">Cerrar sesión</a>
        </div>
    </div>

    <div id="errorBox" class="error"></div>

    <section class="grid">
        <div class="card">
            <div class="label">Pacientes</div>
            <div class="value" id="patientsTotal">0</div>
        </div>
        <div class="card">
            <div class="label">Usuarios</div>
            <div class="value" id="usersTotal">0</div>
        </div>
        <div class="card">
            <div class="label">Protocolos</div>
            <div class="value" id="protocolsTotal">0</div>
        </div>
        <div class="card">
            <div class="label">Cirugías (periodo)</div>
            <div class="value" id="cirugiasPeriodo">0</div>
        </div>
    </section>

    <section class="panel">
        <h3>Revisión de protocolos</h3>
        <pre id="revisionJson">{}</pre>
    </section>

    <section class="panel">
        <h3>Solicitudes funnel</h3>
        <pre id="funnelJson">{}</pre>
    </section>

    <section class="panel">
        <h3>CRM backlog</h3>
        <pre id="crmJson">{}</pre>
    </section>
</div>

<script>
    (function () {
        const summaryEndpoint = @json($summaryEndpoint);
        const startDate = @json($startDate);
        const endDate = @json($endDate);

        const query = new URLSearchParams();
        if (startDate) {
            query.set('start_date', startDate);
        }
        if (endDate) {
            query.set('end_date', endDate);
        }

        const url = query.toString() ? `${summaryEndpoint}?${query.toString()}` : summaryEndpoint;
        const errorBox = document.getElementById('errorBox');
        const dateRangeChip = document.getElementById('dateRangeChip');

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Request-Id': 'ui-' + Math.random().toString(16).slice(2)
            }
        })
            .then(async (response) => {
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(payload.error || `Error HTTP ${response.status}`);
                }
                return payload;
            })
            .then((payload) => {
                const data = payload.data || {};
                const meta = payload.meta || {};
                const range = (meta.date_range && meta.date_range.label) ? meta.date_range.label : '--';

                dateRangeChip.textContent = `Rango: ${range}`;
                document.getElementById('patientsTotal').textContent = String(data.patients_total ?? 0);
                document.getElementById('usersTotal').textContent = String(data.users_total ?? 0);
                document.getElementById('protocolsTotal').textContent = String(data.protocols_total ?? 0);
                document.getElementById('cirugiasPeriodo').textContent = String(data.total_cirugias_periodo ?? 0);

                document.getElementById('revisionJson').textContent = JSON.stringify(data.revision_estados || {}, null, 2);
                document.getElementById('funnelJson').textContent = JSON.stringify(data.solicitudes_funnel || {}, null, 2);
                document.getElementById('crmJson').textContent = JSON.stringify(data.crm_backlog || {}, null, 2);
            })
            .catch((error) => {
                errorBox.style.display = 'block';
                errorBox.textContent = `No se pudo cargar dashboard v2: ${error.message || error}`;
            });
    })();
</script>
</body>
</html>
