<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>KPI Particulares</title>
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
    </style>
</head>
<body>
@php
    $generatedAt = trim((string) ($generatedAt ?? ''));
    $totalAtenciones = (int) ($totalAtenciones ?? 0);
    $filterSummary = is_array($filterSummary ?? null) ? $filterSummary : [];
    $hallazgosClave = is_array($hallazgosClave ?? null) ? $hallazgosClave : [];
    $methodology = is_array($methodology ?? null) ? $methodology : [];
    $generalKpis = is_array($generalKpis ?? null) ? $generalKpis : [];
    $temporalKpis = is_array($temporalKpis ?? null) ? $temporalKpis : [];
    $economicKpis = is_array($economicKpis ?? null) ? $economicKpis : [];
    $tables = is_array($tables ?? null) ? $tables : [];
@endphp

<h1>Informe de Atenciones Particulares - Resumen KPI</h1>
<p class="meta">Generado: {{ $generatedAt }} | Total de atenciones analizadas: {{ number_format($totalAtenciones) }}</p>

<div class="notice scope">
    Este reporte considera atenciones de categoría cliente <strong>Particular</strong> o <strong>Privado</strong>
    dentro del rango filtrado y aplica la lógica real por categoría de servicio para separar
    <strong>realizadas</strong>, <strong>pendientes de facturar</strong> y <strong>pérdidas</strong>.
</div>

<div class="notice methodology">
    <strong>Metodología económica:</strong>
    <ul class="list">
        @foreach($methodology as $item)
            <li>{{ trim((string) $item) }}</li>
        @endforeach
    </ul>
</div>

<h2 class="section-title">Filtros aplicados</h2>
<table class="table">
    <thead>
    <tr>
        <th style="width: 35%;">Filtro</th>
        <th>Valor</th>
    </tr>
    </thead>
    <tbody>
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

<div class="section">
    <h2 class="section-title">KPI Generales</h2>
    <table class="kpi-grid">
        @foreach(array_chunk($generalKpis, 3) as $chunk)
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

<div class="section">
    <h2 class="section-title">KPI Temporales</h2>
    <table class="kpi-grid">
        @foreach(array_chunk($temporalKpis, 3) as $chunk)
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

<div class="section">
<h2 class="section-title">KPI Económicos</h2>
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
</div>

@foreach($tables as $section)
    @php
        $title = trim((string) ($section['title'] ?? 'Resumen'));
        $subtitle = trim((string) ($section['subtitle'] ?? ''));
        $columns = is_array($section['columns'] ?? null) ? $section['columns'] : [];
        $rows = is_array($section['rows'] ?? null) ? $section['rows'] : [];
        $emptyMessage = trim((string) ($section['empty_message'] ?? 'Sin datos para esta sección.'));
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
            @forelse($rows as $row)
                <tr>
                    @foreach((array) $row as $cell)
                        <td>{{ trim((string) $cell) !== '' ? trim((string) $cell) : '—' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($columns), 1) }}" class="muted">{{ $emptyMessage }}</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endforeach
</body>
</html>
