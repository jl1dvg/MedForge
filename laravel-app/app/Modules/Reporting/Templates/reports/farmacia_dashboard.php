<?php
/**
 * @var string|null $titulo
 * @var string|null $generatedAt
 * @var string|null $rangeLabel
 * @var string|null $scopeNotice
 * @var array<int, array{label: string, value: string}> $filters
 * @var array<int, string> $methodology
 * @var array<int, string> $hallazgosClave
 * @var array<int, array{label:string,value:string,note:string}> $executiveKpis
 * @var array<int, array{label:string,value:string,note:string}> $operationalKpis
 * @var array<int, array{label:string,value:string,note:string}> $economicKpis
 * @var array<int, array{label:string,value:string,note:string}> $qualityKpis
 * @var int|null $total
 */

$title = $titulo ?? 'Dashboard de Farmacia - Resumen KPI';
$generatedAt = trim((string) ($generatedAt ?? ''));
$rangeLabel = trim((string) ($rangeLabel ?? ''));
$scopeNotice = trim((string) ($scopeNotice ?? ''));
$filters = is_array($filters ?? null) ? $filters : [];
$methodology = is_array($methodology ?? null) ? $methodology : [];
$hallazgosClave = is_array($hallazgosClave ?? null) ? $hallazgosClave : [];
$executiveKpis = is_array($executiveKpis ?? null) ? $executiveKpis : [];
$operationalKpis = is_array($operationalKpis ?? null) ? $operationalKpis : [];
$economicKpis = is_array($economicKpis ?? null) ? $economicKpis : [];
$qualityKpis = is_array($qualityKpis ?? null) ? $qualityKpis : [];
$total = (int) ($total ?? 0);

$renderKpiGrid = static function (array $items): void {
    if ($items === []) {
        echo '<p class="muted">Sin datos suficientes para esta sección.</p>';
        return;
    }

    echo '<table class="kpi-grid"><tr>';
    foreach (array_values($items) as $index => $item) {
        if ($index > 0 && $index % 2 === 0) {
            echo '</tr><tr>';
        }
        echo '<td class="kpi-card" style="width: 50%;">';
        echo '<span class="kpi-label">' . htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<span class="kpi-value">' . htmlspecialchars((string) ($item['value'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<span class="kpi-note">' . htmlspecialchars((string) ($item['note'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</td>';
    }
    if (count($items) % 2 !== 0) {
        echo '<td class="kpi-card" style="width: 50%; background: transparent; border: none;"></td>';
    }
    echo '</tr></table>';
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; line-height: 1.45; }
        h1 { margin: 0 0 4px 0; font-size: 20px; }
        .meta { margin: 0 0 10px 0; color: #4b5563; font-size: 10px; }
        .notice { border-radius: 6px; padding: 8px 10px; font-size: 11px; margin-bottom: 12px; }
        .scope { background: #eff6ff; border: 1px solid #bfdbfe; }
        .methodology { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .section-title { margin: 14px 0 8px 0; font-size: 14px; font-weight: 700; color: #0f172a; }
        .muted { color: #64748b; }
        .list { margin: 0; padding-left: 16px; }
        .list li { margin-bottom: 4px; }
        .kpi-grid { width: 100%; border-collapse: separate; border-spacing: 8px; margin-bottom: 12px; }
        .kpi-card { border: 1px solid #dbe4ea; border-radius: 8px; padding: 10px; background: #f8fafc; height: 86px; vertical-align: top; }
        .kpi-label { display: block; font-size: 10px; color: #475569; margin-bottom: 6px; }
        .kpi-value { display: block; font-size: 18px; font-weight: 700; color: #0f766e; margin-bottom: 5px; }
        .kpi-note { display: block; font-size: 10px; color: #64748b; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border: 1px solid #e5e7eb; padding: 7px; vertical-align: top; }
        .table th { background: #f3f4f6; text-align: left; font-size: 11px; }
        .subtitle { margin: -3px 0 8px 0; font-size: 10px; color: #64748b; }
        .section { page-break-inside: avoid; }
    </style>
</head>
<body>
<h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
<p class="meta">
    Generado: <?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?> | Registros analizados: <?= number_format($total) ?>
    <?php if ($rangeLabel !== ''): ?>
        | Periodo: <?= htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8') ?>
    <?php endif; ?>
</p>

<div class="notice scope">
    <?= htmlspecialchars($scopeNotice !== '' ? $scopeNotice : 'Este reporte resume producción clínica, conciliación y resultado económico del dashboard de farmacia.', ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="notice methodology">
    <strong>Cómo leer este reporte:</strong>
    <ul class="list">
        <?php foreach ($methodology as $item): ?>
            <li><?= htmlspecialchars(trim((string) $item), ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<h2 class="section-title">Filtros aplicados</h2>
<table class="table">
    <thead>
    <tr><th style="width: 35%;">Filtro</th><th>Valor</th></tr>
    </thead>
    <tbody>
    <?php if ($filters === []): ?>
        <tr><td>Filtro</td><td>Sin filtros específicos</td></tr>
    <?php else: ?>
        <?php foreach ($filters as $item): ?>
            <tr>
                <td><?= htmlspecialchars(trim((string) ($item['label'] ?? 'Filtro')), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars(trim((string) ($item['value'] ?? 'Todos')), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<div class="section">
    <h2 class="section-title">Hallazgos clave</h2>
    <?php if ($hallazgosClave !== []): ?>
        <ul class="list">
            <?php foreach ($hallazgosClave as $hallazgo): ?>
                <li><?= htmlspecialchars(trim((string) $hallazgo), ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="muted">No hubo suficientes datos para generar hallazgos destacados en el rango seleccionado.</p>
    <?php endif; ?>
</div>

<div class="section">
    <h2 class="section-title">Resumen Ejecutivo</h2>
    <p class="subtitle">Lectura rápida del volumen asistencial del periodo.</p>
    <?php $renderKpiGrid($executiveKpis); ?>
</div>

<div class="section">
    <h2 class="section-title">Lectura Operativa</h2>
    <p class="subtitle">Mide despacho, oportunidad y comportamiento operativo.</p>
    <?php $renderKpiGrid($operationalKpis); ?>
</div>

<div class="section">
    <h2 class="section-title">Lectura Económica</h2>
    <p class="subtitle">Resume neto conciliado, descuentos y ticket promedio.</p>
    <?php $renderKpiGrid($economicKpis); ?>
</div>

<div class="section">
    <h2 class="section-title">Calidad de Conciliación</h2>
    <p class="subtitle">Ayuda a entender la precisión del cruce receta-facturación y su riesgo operativo.</p>
    <?php $renderKpiGrid($qualityKpis); ?>
</div>
</body>
</html>
