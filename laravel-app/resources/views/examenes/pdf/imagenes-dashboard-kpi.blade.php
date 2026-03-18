<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>KPI Imágenes</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 12px;
            line-height: 1.45;
        }

        h1 {
            margin: 0 0 4px 0;
            font-size: 20px;
        }

        .meta {
            margin: 0 0 10px 0;
            color: #4b5563;
            font-size: 10px;
        }

        .notice {
            border-radius: 6px;
            padding: 8px 10px;
            font-size: 11px;
            margin-bottom: 12px;
        }

        .scope {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .methodology {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            border: 1px solid #e5e7eb;
            padding: 7px;
            vertical-align: top;
        }

        .table th {
            background: #f3f4f6;
            text-align: left;
            font-size: 11px;
        }

        .section-title {
            margin: 14px 0 8px 0;
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }

        .value {
            font-weight: 700;
            color: #0f766e;
        }

        .muted {
            color: #64748b;
        }

        .list {
            margin: 0;
            padding-left: 16px;
        }

        .list li {
            margin-bottom: 4px;
        }

        .kpi-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
        }

        .kpi-card {
            border: 1px solid #dbe4ea;
            border-radius: 8px;
            padding: 10px;
            background: #f8fafc;
            height: 86px;
            vertical-align: top;
        }

        .kpi-label {
            display: block;
            font-size: 10px;
            color: #475569;
            margin-bottom: 6px;
        }

        .kpi-value {
            display: block;
            font-size: 18px;
            font-weight: 700;
            color: #0f766e;
            margin-bottom: 5px;
        }

        .kpi-note {
            display: block;
            font-size: 10px;
            color: #64748b;
        }

        .section {
            page-break-inside: avoid;
        }

        .subtitle {
            margin: -3px 0 8px 0;
            font-size: 10px;
            color: #64748b;
        }

        .dual-notes {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-bottom: 12px;
        }

        .note-box {
            border: 1px solid #dbe4ea;
            border-radius: 8px;
            padding: 9px 10px;
            background: #f8fafc;
            vertical-align: top;
        }

        .note-box h3 {
            margin: 0 0 4px 0;
            font-size: 11px;
            color: #0f172a;
        }

        .note-box p {
            margin: 0;
            font-size: 10px;
            color: #475569;
        }
    </style>
</head>
<body>
@php
    $generatedAt = trim((string) ($generatedAt ?? ''));
    $rangeLabel = trim((string) ($rangeLabel ?? ''));
    $scopeNotice = trim((string) ($scopeNotice ?? ''));
    $totalAtenciones = (int) ($totalAtenciones ?? 0);
    $filterSummary = is_array($filterSummary ?? null) ? $filterSummary : [];
    $hallazgosClave = is_array($hallazgosClave ?? null) ? $hallazgosClave : [];
    $methodology = is_array($methodology ?? null) ? $methodology : [];
    $executiveKpis = is_array($executiveKpis ?? null) ? $executiveKpis : [];
    $cohortKpis = is_array($cohortKpis ?? null) ? $cohortKpis : [];
    $operationalKpis = is_array($operationalKpis ?? null) ? $operationalKpis : [];
    $qualityKpis = is_array($qualityKpis ?? null) ? $qualityKpis : [];
    $generalKpis = is_array($generalKpis ?? null) ? $generalKpis : [];
    $temporalKpis = is_array($temporalKpis ?? null) ? $temporalKpis : [];
    $economicKpis = is_array($economicKpis ?? null) ? $economicKpis : [];
    $operationalTables = is_array($operationalTables ?? null) ? $operationalTables : [];
    $cohortTables = is_array($cohortTables ?? null) ? $cohortTables : [];
    $tables = is_array($tables ?? null) ? $tables : [];
@endphp

<h1>Dashboard de Imágenes - Resumen KPI</h1>
<p class="meta">
    Generado: {{ $generatedAt }} | Total de estudios analizados: {{ number_format($totalAtenciones) }}
    @if($rangeLabel !== '')
        | Periodo: {{ $rangeLabel }}
    @endif
</p>

<div class="notice scope">
    {{ $scopeNotice !== '' ? $scopeNotice : 'Este reporte consolida actividad operativa, evidencia técnica NAS/informe y cierre económico para estudios de imágenes.' }}
</div>

<div class="notice methodology">
    <strong>Metodología:</strong>
    <ul class="list">
        @foreach($methodology as $item)
            <li>{{ trim((string) $item) }}</li>
        @endforeach
    </ul>
</div>

<table class="dual-notes">
    <tr>
        <td class="note-box" style="width: 50%;">
            <h3>Bloque 1: Operación del periodo</h3>
            <p>Mide agenda, realización, informe, facturación, cumplimiento de citas y pérdida operativa dentro del rango, aunque la solicitud original sea anterior.</p>
        </td>
        <td class="note-box" style="width: 50%;">
            <h3>Bloque 2: Cohorte de solicitudes</h3>
            <p>Mide lo que se pidió en el rango seleccionado y qué parte quedó agendada, realizada al corte, realizada después del corte, se perdió o sigue pendiente.</p>
        </td>
    </tr>
</table>

<h2 class="section-title">Filtros aplicados</h2>
<table class="table">
    <thead>
    <tr>
        <th style="width: 35%;">Filtro</th>
        <th>Valor</th>
    </tr>
    </thead>
    <tbody>
    @if(empty($filterSummary))
        <tr>
            <td>Filtro</td>
            <td>Sin filtros específicos</td>
        </tr>
    @else
        @foreach($filterSummary as $item)
            @php
                $label = trim((string) ($item['label'] ?? 'Filtro'));
                $value = trim((string) ($item['value'] ?? 'Todos'));
            @endphp
            <tr>
                <td>{{ $label }}</td>
                <td>{{ $value !== '' ? $value : 'Todos' }}</td>
            </tr>
        @endforeach
    @endif
    </tbody>
</table>

<div class="section">
    <h2 class="section-title">Hallazgos clave</h2>
    @if(!empty($hallazgosClave))
        <ul class="list">
            @foreach($hallazgosClave as $hallazgo)
                <li>{{ trim((string) $hallazgo) }}</li>
            @endforeach
        </ul>
    @else
        <p class="muted">No hubo suficientes datos para generar hallazgos destacados en el rango seleccionado.</p>
    @endif
</div>

@if(!empty($executiveKpis))
    <div class="section">
        <h2 class="section-title">Resumen Ejecutivo</h2>
        <p class="subtitle">Lectura corta del periodo para revisar demanda, operación y caja.</p>
        <table class="kpi-grid">
            @foreach(array_chunk($executiveKpis, 3) as $chunk)
                <tr>
                    @foreach($chunk as $kpi)
                        <td class="kpi-card" style="width: 33.33%;">
                            <span class="kpi-label">{{ trim((string) ($kpi['label'] ?? 'KPI')) }}</span>
                            <span class="kpi-value">{{ trim((string) ($kpi['value'] ?? '0')) }}</span>
                            <span class="kpi-note">{{ trim((string) ($kpi['note'] ?? '')) }}</span>
                        </td>
                    @endforeach
                    @for($index = count($chunk); $index < 3; $index++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    </div>
@endif

<div class="section">
    <h2 class="section-title">Bloque 1 - Operación del periodo</h2>
    <p class="subtitle">Fecha base: agenda y realización del rango, aunque la solicitud original sea anterior.</p>
    @if(!empty($operationalKpis))
        <table class="kpi-grid">
            @foreach(array_chunk($operationalKpis, 3) as $chunk)
                <tr>
                    @foreach($chunk as $kpi)
                        <td class="kpi-card" style="width: 33.33%;">
                            <span class="kpi-label">{{ trim((string) ($kpi['label'] ?? 'KPI')) }}</span>
                            <span class="kpi-value">{{ trim((string) ($kpi['value'] ?? '0')) }}</span>
                            <span class="kpi-note">{{ trim((string) ($kpi['note'] ?? '')) }}</span>
                        </td>
                    @endforeach
                    @for($index = count($chunk); $index < 3; $index++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @else
        <p class="muted">Sin KPI operativos para el rango seleccionado.</p>
    @endif

    @if(!empty($qualityKpis))
        <h3 class="section-title">Cumplimiento y Oportunidad</h3>
        <table class="kpi-grid">
            @foreach(array_chunk($qualityKpis, 3) as $chunk)
                <tr>
                    @foreach($chunk as $kpi)
                        <td class="kpi-card" style="width: 33.33%;">
                            <span class="kpi-label">{{ trim((string) ($kpi['label'] ?? 'KPI')) }}</span>
                            <span class="kpi-value">{{ trim((string) ($kpi['value'] ?? '0')) }}</span>
                            <span class="kpi-note">{{ trim((string) ($kpi['note'] ?? '')) }}</span>
                        </td>
                    @endforeach
                    @for($index = count($chunk); $index < 3; $index++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @endif

    @if(!empty($economicKpis))
        <h3 class="section-title">Economía y Facturación</h3>
        <table class="table">
            <thead>
            <tr>
                <th style="width: 20%;">KPI</th>
                <th style="width: 16%;">Valor</th>
                <th style="width: 32%;">Qué significa</th>
                <th style="width: 32%;">Cómo se calcula</th>
            </tr>
            </thead>
            <tbody>
            @foreach($economicKpis as $kpi)
                @php
                    $label = trim((string) ($kpi['label'] ?? 'KPI'));
                    $value = trim((string) ($kpi['value'] ?? '0'));
                    $meaning = trim((string) ($kpi['meaning'] ?? ''));
                    $formula = trim((string) ($kpi['formula'] ?? ''));
                @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td class="value">{{ $value }}</td>
                    <td>{{ $meaning }}</td>
                    <td>{{ $formula }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

@foreach($operationalTables as $table)
    @php
        $title = trim((string) ($table['title'] ?? 'Tabla'));
        $subtitle = trim((string) ($table['subtitle'] ?? ''));
        $columns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
        $rows = is_array($table['rows'] ?? null) ? $table['rows'] : [];
        $emptyMessage = trim((string) ($table['empty_message'] ?? 'Sin datos.'));
    @endphp
    <div class="section">
        <h2 class="section-title">{{ $title }}</h2>
        @if($subtitle !== '')
            <p class="subtitle">{{ $subtitle }}</p>
        @endif
        <table class="table">
            <thead>
            <tr>
                @foreach($columns as $column)
                    <th>{{ trim((string) $column) }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @if(empty($rows))
                <tr>
                    <td colspan="{{ max(count($columns), 1) }}" class="muted">{{ $emptyMessage }}</td>
                </tr>
            @else
                @foreach($rows as $tableRow)
                    <tr>
                        @foreach((array) $tableRow as $cell)
                            <td>{{ trim((string) $cell) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
    </div>
@endforeach

<div class="section">
    <h2 class="section-title">Bloque 2 - Cohorte de Solicitudes</h2>
    <p class="subtitle">Fecha base: solicitud registrada en consulta_examenes dentro del rango.</p>
    @if(!empty($cohortKpis))
        <table class="kpi-grid">
            @foreach(array_chunk($cohortKpis, 3) as $chunk)
                <tr>
                    @foreach($chunk as $kpi)
                        <td class="kpi-card" style="width: 33.33%;">
                            <span class="kpi-label">{{ trim((string) ($kpi['label'] ?? 'KPI')) }}</span>
                            <span class="kpi-value">{{ trim((string) ($kpi['value'] ?? '0')) }}</span>
                            <span class="kpi-note">{{ trim((string) ($kpi['note'] ?? '')) }}</span>
                        </td>
                    @endforeach
                    @for($index = count($chunk); $index < 3; $index++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @else
        <p class="muted">Sin KPI de solicitudes para el rango seleccionado.</p>
    @endif
</div>

@foreach($cohortTables as $table)
    @php
        $title = trim((string) ($table['title'] ?? 'Tabla'));
        $subtitle = trim((string) ($table['subtitle'] ?? ''));
        $columns = is_array($table['columns'] ?? null) ? $table['columns'] : [];
        $rows = is_array($table['rows'] ?? null) ? $table['rows'] : [];
        $emptyMessage = trim((string) ($table['empty_message'] ?? 'Sin datos.'));
    @endphp
    <div class="section">
        <h2 class="section-title">{{ $title }}</h2>
        @if($subtitle !== '')
            <p class="subtitle">{{ $subtitle }}</p>
        @endif
        <table class="table">
            <thead>
            <tr>
                @foreach($columns as $column)
                    <th>{{ trim((string) $column) }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @if(empty($rows))
                <tr>
                    <td colspan="{{ max(count($columns), 1) }}" class="muted">{{ $emptyMessage }}</td>
                </tr>
            @else
                @foreach($rows as $tableRow)
                    <tr>
                        @foreach((array) $tableRow as $cell)
                            <td>{{ trim((string) $cell) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
    </div>
@endforeach
</body>
</html>
