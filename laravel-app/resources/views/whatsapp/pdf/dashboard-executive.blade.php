<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Resumen ejecutivo WhatsApp</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 11px;
            line-height: 1.45;
        }
        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #14532d 100%);
            color: #fff;
            padding: 18px 20px;
            border-radius: 14px;
            margin-bottom: 16px;
        }
        .hero h1 {
            margin: 0 0 6px;
            font-size: 22px;
        }
        .hero p {
            margin: 0;
            color: #dbeafe;
        }
        .meta {
            margin-top: 10px;
            font-size: 10px;
            color: #e2e8f0;
        }
        .section {
            margin-bottom: 18px;
        }
        .section h2 {
            font-size: 15px;
            margin: 0 0 8px;
            color: #0f172a;
        }
        .grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin: 0 -8px;
        }
        .card {
            border: 1px solid #dbe2ea;
            border-radius: 12px;
            padding: 12px;
            background: #f8fafc;
            vertical-align: top;
        }
        .label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #64748b;
        }
        .value {
            font-size: 24px;
            font-weight: bold;
            margin: 4px 0 6px;
        }
        .sub {
            font-size: 10px;
            color: #475569;
        }
        table.report {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.report th,
        table.report td {
            border: 1px solid #dbe2ea;
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
        }
        table.report th {
            background: #e2e8f0;
            font-size: 10px;
        }
        .pill {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #0c4a6e;
            font-size: 9px;
            margin-right: 6px;
        }
        ul {
            margin: 6px 0 0 18px;
            padding: 0;
        }
        li {
            margin-bottom: 6px;
        }
        .two-col {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px;
        }
        .muted {
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="hero">
        <h1>Resumen Ejecutivo WhatsApp</h1>
        <p>Periodo {{ $period['date_from'] ?? '' }} a {{ $period['date_to'] ?? '' }} · {{ $period['days'] ?? 0 }} días</p>
        <div class="meta">
            Generado: {{ $generatedAt }} · SLA objetivo: {{ $filters['sla_target_minutes'] ?? 15 }} min
        </div>
    </div>

    <div class="section">
        <h2>KPIs clave</h2>
        <table class="grid">
            <tr>
                <td class="card" width="25%">
                    <div class="label">Conversaciones nuevas</div>
                    <div class="value">{{ $summary['conversations_new'] ?? 0 }}</div>
                    <div class="sub">Demanda total del periodo</div>
                </td>
                <td class="card" width="25%">
                    <div class="label">Personas inbound</div>
                    <div class="value">{{ $summary['people_inbound'] ?? 0 }}</div>
                    <div class="sub">Números únicos que escribieron</div>
                </td>
                <td class="card" width="25%">
                    <div class="label">Cobertura humana</div>
                    <div class="value">{{ $summary['attention_rate'] ?? 0 }}%</div>
                    <div class="sub">{{ $summary['conversations_attended_human'] ?? 0 }} conversaciones atendidas</div>
                </td>
                <td class="card" width="25%">
                    <div class="label">Citas humanas atribuibles</div>
                    <div class="value">{{ $summary['human_attributed_appointments_strong'] ?? 0 }}</div>
                    <div class="sub">{{ $summary['sigcenter_bookings_created'] ?? 0 }} bot/integración · 72h: {{ $summary['human_attributed_appointments_medium'] ?? 0 }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Lectura ejecutiva</h2>
        <p class="muted">
            Captación representa {{ $analyticsSummary['captacion_conversations'] ?? 0 }} conversaciones y
            {{ isset($analyticsSummary['total_conversations']) && (int) $analyticsSummary['total_conversations'] > 0 ? round(((int) ($analyticsSummary['captacion_conversations'] ?? 0) / (int) $analyticsSummary['total_conversations']) * 100, 1) : 0 }}%
            del canal. Operación ya absorbe {{ $analyticsSummary['operacion_conversations'] ?? 0 }} conversaciones,
            mientras que seguimiento clínico y reactivación convierten proporcionalmente mejor que la captación nueva.
        </p>
        <table class="report">
            <thead>
            <tr>
                <th>Categoría</th>
                <th>Total</th>
                <th>Participación</th>
                <th>Identificadas</th>
                <th>Citas</th>
                <th>Handoffs</th>
            </tr>
            </thead>
            <tbody>
            @foreach($lifecycle as $row)
                <tr>
                    <td>{{ $row['lifecycle_label'] ?? '' }}</td>
                    <td>{{ $row['total'] ?? 0 }}</td>
                    <td>{{ $row['share'] ?? 0 }}%</td>
                    <td>{{ $row['identified'] ?? 0 }}</td>
                    <td>{{ $row['bookings'] ?? 0 }} ({{ $row['booking_rate'] ?? 0 }}%)</td>
                    <td>{{ $row['handoffs'] ?? 0 }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Origen de demanda</h2>
        <table class="report">
            <thead>
            <tr>
                <th>Origen</th>
                <th>Total</th>
                <th>Participación</th>
                <th>Identificadas</th>
                <th>Citas</th>
                <th>Handoffs</th>
            </tr>
            </thead>
            <tbody>
            @foreach($sources as $row)
                <tr>
                    <td>{{ $row['source_label'] ?? '' }}</td>
                    <td>{{ $row['total'] ?? 0 }}</td>
                    <td>{{ $row['share'] ?? 0 }}%</td>
                    <td>{{ $row['identified'] ?? 0 }}</td>
                    <td>{{ $row['bookings'] ?? 0 }} ({{ $row['booking_rate'] ?? 0 }}%)</td>
                    <td>{{ $row['handoffs'] ?? 0 }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Embudo y fricciones</h2>
        <table class="two-col">
            <tr>
                <td width="58%" style="vertical-align:top;">
                    <table class="report">
                        <thead>
                        <tr>
                            <th>Paso</th>
                            <th>Valor</th>
                            <th>Desde inicio</th>
                            <th>Siguiente paso</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($funnel as $row)
                            <tr>
                                <td>{{ $row['label'] ?? '' }}</td>
                                <td>{{ $row['value'] ?? 0 }}</td>
                                <td>{{ $row['rate_from_start'] ?? 0 }}%</td>
                                <td>{{ $row['rate_to_next'] ?? 0 }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </td>
                <td width="42%" style="vertical-align:top;">
                    <table class="report">
                        <thead>
                        <tr>
                            <th>Fricción</th>
                            <th>Total</th>
                            <th>Participación</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($frictions as $row)
                            <tr>
                                <td>{{ $row['friction_label'] ?? '' }}</td>
                                <td>{{ $row['total'] ?? 0 }}</td>
                                <td>{{ $row['share'] ?? 0 }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Hallazgos clave</h2>
        @foreach($insights as $insight)
            <div class="pill">{{ $insight['title'] ?? 'Insight' }}</div>
        @endforeach
        <ul>
            @foreach($insights as $insight)
                <li>{{ $insight['body'] ?? '' }}</li>
            @endforeach
        </ul>
    </div>

    <div class="section">
        <h2>Acciones sugeridas</h2>
        <ul>
            @foreach($recommendations as $recommendation)
                <li>{{ $recommendation }}</li>
            @endforeach
        </ul>
    </div>
</body>
</html>
