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

        .scope {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 12px;
            font-size: 11px;
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
        }

        .value {
            font-weight: 700;
            color: #0f766e;
        }
    </style>
</head>
<body>
@php
    $generatedAt = trim((string) ($generatedAt ?? ''));
    $totalAtenciones = (int) ($totalAtenciones ?? 0);
    $filterSummary = is_array($filterSummary ?? null) ? $filterSummary : [];
    $kpis = is_array($kpis ?? null) ? $kpis : [];
@endphp

<h1>Informe de Atenciones Particulares - KPI Económicos</h1>
<p class="meta">Generado: {{ $generatedAt }} | Total de atenciones analizadas: {{ number_format($totalAtenciones) }}</p>

<div class="scope">
    Este reporte considera únicamente atenciones con <strong>estado de encuentro atendido</strong> y categoría cliente
    <strong>Particular</strong> o <strong>Privado</strong>.
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

<h2 class="section-title">Definición de KPI</h2>
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
    @foreach($kpis as $kpi)
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
</body>
</html>
