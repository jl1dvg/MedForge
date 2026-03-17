@extends('layouts.medforge')

@push('scripts')
    @if (\App\Modules\Shared\Support\MedforgeAssets::hasViteBuild())
        @vite('resources/js/v2/imagenes-dashboard.js')
    @else
        <script src="/assets/vendor_components/chart.js/chart.umd.js"></script>
    @endif
@endpush

@section('content')
<?php
/** @var array<string, string> $filters */
/** @var array<string, mixed> $dashboard */
/** @var array<int, array<string, mixed>> $rows */
/** @var array<int, array{value:string,label:string}> $afiliacionOptions */
/** @var array<int, array{value:string,label:string}> $afiliacionCategoriaOptions */
/** @var array<int, array{value:string,label:string}> $seguroOptions */
/** @var array<int, array{value:string,label:string}> $sedeOptions */

if (!isset($filters) || !is_array($filters)) {
    $filters = [
        'fecha_inicio' => '',
        'fecha_fin' => '',
        'afiliacion' => '',
        'afiliacion_categoria' => '',
        'seguro' => '',
        'sede' => '',
        'tipo_examen' => '',
        'paciente' => '',
        'estado_agenda' => '',
    ];
}

if (!isset($dashboard) || !is_array($dashboard)) {
    $dashboard = ['cards' => [], 'meta' => [], 'charts' => []];
}

$dashboardCards = is_array($dashboard['cards'] ?? null) ? $dashboard['cards'] : [];
$dashboardMeta = is_array($dashboard['meta'] ?? null) ? $dashboard['meta'] : [];
$insuranceBreakdown = is_array($dashboardMeta['insurance_breakdown'] ?? null) ? $dashboardMeta['insurance_breakdown'] : [];
$insuranceBreakdownTitle = trim((string) ($insuranceBreakdown['title'] ?? 'Empresas de seguro'));
$afiliacionOptions = is_array($afiliacionOptions ?? null) ? $afiliacionOptions : [['value' => '', 'label' => 'Todas las empresas']];
$afiliacionCategoriaOptions = is_array($afiliacionCategoriaOptions ?? null) ? $afiliacionCategoriaOptions : [['value' => '', 'label' => 'Todas las categorías'], ['value' => 'publico', 'label' => 'Pública'], ['value' => 'privado', 'label' => 'Privada']];
$seguroOptions = is_array($seguroOptions ?? null) ? $seguroOptions : [['value' => '', 'label' => 'Todos los seguros']];
$sedeOptions = is_array($sedeOptions ?? null) ? $sedeOptions : [['value' => '', 'label' => 'Todas las sedes'], ['value' => 'MATRIZ', 'label' => 'MATRIZ'], ['value' => 'CEIBOS', 'label' => 'CEIBOS']];
$exportQuery = http_build_query([
    'fecha_inicio' => (string)($filters['fecha_inicio'] ?? ''),
    'fecha_fin' => (string)($filters['fecha_fin'] ?? ''),
    'afiliacion' => (string)($filters['afiliacion'] ?? ''),
    'afiliacion_categoria' => (string)($filters['afiliacion_categoria'] ?? ''),
    'seguro' => (string)($filters['seguro'] ?? ''),
    'sede' => (string)($filters['sede'] ?? ''),
    'tipo_examen' => (string)($filters['tipo_examen'] ?? ''),
    'paciente' => (string)($filters['paciente'] ?? ''),
    'estado_agenda' => (string)($filters['estado_agenda'] ?? ''),
]);

$estadoOpciones = [];
foreach (($rows ?? []) as $row) {
    $estado = trim((string)($row['estado_agenda'] ?? ''));
    if ($estado !== '' && !in_array($estado, $estadoOpciones, true)) {
        $estadoOpciones[] = $estado;
    }
}
sort($estadoOpciones);

$cardIndex = [];
foreach ($dashboardCards as $card) {
    $label = trim((string) ($card['label'] ?? ''));
    if ($label === '') {
        continue;
    }
    $cardIndex[$label] = $card;
}

$pickCards = static function (array $labels) use ($cardIndex): array {
    $picked = [];
    foreach ($labels as $label) {
        if (isset($cardIndex[$label])) {
            $picked[] = $cardIndex[$label];
        }
    }

    return $picked;
};

$cardNumber = static function (string $label) use ($cardIndex): float {
    $raw = trim((string) (($cardIndex[$label]['value'] ?? '0')));
    $normalized = preg_replace('/[^\d\.\-]/', '', str_replace(',', '', $raw));

    return is_string($normalized) && $normalized !== '' ? (float) $normalized : 0.0;
};

$summaryCards = $pickCards([
    'Total estudios',
    'Atendidos',
    'Informadas',
    'Facturados',
    'Pendiente de facturar',
    'Producción facturada',
]);

$operationCards = $pickCards([
    'Cumplimiento cita->realización',
    'SLA informe <= 48h',
    'Día pico de tráfico',
    'Pérdida',
    'Cancelados',
    'Pendiente de pago',
]);

$facturacionCards = $pickCards([
    'Atendidos pendientes facturar',
    'Pendiente facturar pública',
    'Pendiente facturar privada',
    'Facturación cancelada',
    'Pendiente estimado público',
    'Ticket promedio facturado',
    'Procedimientos facturados',
    'Facturados e informados',
    'Facturados sin informar',
    'Informados sin facturar',
]);

$rangeSummary = trim((string) ($filters['fecha_inicio'] ?? '')) !== '' && trim((string) ($filters['fecha_fin'] ?? '')) !== ''
    ? trim((string) ($filters['fecha_inicio'] ?? '')) . ' a ' . trim((string) ($filters['fecha_fin'] ?? ''))
    : 'Rango abierto';

$executiveHighlights = [];
$pendingBilling = (int) ($dashboardMeta['pendientes_facturar'] ?? $cardNumber('Pendiente de facturar'));
$pendingBillingPublic = (int) ($dashboardMeta['pendientes_facturar_publico'] ?? 0);
$pendingBillingPrivate = (int) ($dashboardMeta['pendientes_facturar_privado'] ?? 0);
$pendingAmountPublic = (float) ($dashboardMeta['monto_pendiente_estimado_publico'] ?? 0);
$pendingPublicWithoutRate = (int) ($dashboardMeta['pendientes_facturar_publico_sin_tarifa'] ?? 0);
$sla48Value = trim((string) (($cardIndex['SLA informe <= 48h']['value'] ?? '—')));
$trafficPeakLabel = trim((string) (($cardIndex['Día pico de tráfico']['value'] ?? '—')));
$trafficPeakHint = trim((string) (($cardIndex['Día pico de tráfico']['hint'] ?? '')));

if ($pendingBilling > 0) {
    $executiveHighlights[] = 'Backlog operativo: ' . number_format($pendingBilling) . ' casos atendidos siguen sin cierre de facturación (' . number_format($pendingBillingPublic) . ' públicos y ' . number_format($pendingBillingPrivate) . ' privados).';
}
if ($pendingAmountPublic > 0) {
    $executiveHighlights[] = 'Oportunidad pública abierta: $' . number_format($pendingAmountPublic, 2) . ' estimados por facturar con tarifario nivel 3.';
} elseif ($pendingPublicWithoutRate > 0) {
    $executiveHighlights[] = 'Existen ' . number_format($pendingPublicWithoutRate) . ' casos públicos pendientes sin tarifa nivel 3, por lo que el pendiente económico está subestimado.';
}
if ($sla48Value !== '' && $sla48Value !== '—') {
    $executiveHighlights[] = 'Cumplimiento temporal: SLA de informe <= 48h en ' . $sla48Value . ' para el rango actual.';
}
if ($trafficPeakLabel !== '' && $trafficPeakLabel !== '—') {
    $executiveHighlights[] = 'Carga operativa concentrada en ' . $trafficPeakLabel . ($trafficPeakHint !== '' ? (' (' . $trafficPeakHint . ').') : '.');
}
if ($executiveHighlights === []) {
    $executiveHighlights[] = 'No se detectaron alertas destacadas para el rango seleccionado. Usa los bloques de operación y facturación para revisar detalle.';
}
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Dashboard de Imágenes</h3>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                <li class="breadcrumb-item"><a href="/v2/imagenes/examenes-realizados">Imágenes</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
            </ol>
        </div>
    </div>
</div>

<section class="content">
    <div class="box mb-3">
        <div class="box-header with-border d-flex justify-content-between align-items-center">
            <h4 class="box-title mb-0">Filtros</h4>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a href="/v2/imagenes/dashboard/export/pdf<?= $exportQuery !== '' ? ('?' . htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8')) : '' ?>"
                   class="btn btn-outline-danger btn-sm">
                    <i class="mdi mdi-file-pdf-box me-1"></i> Descargar PDF
                </a>
                <a href="/v2/imagenes/dashboard/export/excel<?= $exportQuery !== '' ? ('?' . htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8')) : '' ?>"
                   class="btn btn-outline-success btn-sm">
                    <i class="mdi mdi-file-excel-box me-1"></i> Descargar Excel
                </a>
                <a href="/v2/imagenes/examenes-realizados" class="btn btn-outline-primary btn-sm">
                    <i class="mdi mdi-format-list-bulleted me-1"></i> Ir a imágenes realizadas
                </a>
            </div>
        </div>
        <div class="box-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" class="form-control" name="fecha_inicio"
                           value="<?= htmlspecialchars($filters['fecha_inicio'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" class="form-control" name="fecha_fin"
                           value="<?= htmlspecialchars($filters['fecha_fin'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Empresa de seguro</label>
                    <select class="form-select" name="afiliacion">
                        <?php foreach ($afiliacionOptions as $option): ?>
                            <?php $optionValue = (string)($option['value'] ?? ''); ?>
                            <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($filters['afiliacion'] ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Categoría de seguro</label>
                    <select class="form-select" name="afiliacion_categoria">
                        <?php foreach ($afiliacionCategoriaOptions as $option): ?>
                            <?php $optionValue = (string)($option['value'] ?? ''); ?>
                            <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($filters['afiliacion_categoria'] ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Seguro / plan</label>
                    <select class="form-select" name="seguro">
                        <?php foreach ($seguroOptions as $option): ?>
                            <?php $optionValue = (string)($option['value'] ?? ''); ?>
                            <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($filters['seguro'] ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Sede</label>
                    <select class="form-select" name="sede">
                        <?php foreach ($sedeOptions as $option): ?>
                            <?php $optionValue = (string)($option['value'] ?? ''); ?>
                            <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($filters['sede'] ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Tipo examen</label>
                    <input type="text" class="form-control" name="tipo_examen"
                           value="<?= htmlspecialchars($filters['tipo_examen'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="281032 / OCT / ...">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Paciente/Cédula</label>
                    <input type="text" class="form-control" name="paciente"
                           value="<?= htmlspecialchars($filters['paciente'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Nombre o ID">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Estado agenda</label>
                    <select class="form-select" name="estado_agenda">
                        <option value="">Todos</option>
                        <?php foreach ($estadoOpciones as $estado): ?>
                            <option value="<?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['estado_agenda'] ?? '') === $estado ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-filter-variant"></i> Aplicar filtros
                    </button>
                    <a href="/v2/imagenes/dashboard" class="btn btn-outline-secondary btn-sm">
                        <i class="mdi mdi-close-circle-outline"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <nav class="imagenes-section-nav mb-3">
        <a href="#imagenes-resumen">Resumen</a>
        <a href="#imagenes-operacion">Operación</a>
        <a href="#imagenes-facturacion">Facturación</a>
        <a href="#imagenes-demanda">Demanda</a>
    </nav>

    <section id="imagenes-resumen" class="imagenes-section-card mb-3">
        <div class="imagenes-section-head">
            <div>
                <p class="imagenes-section-kicker mb-1">Resumen ejecutivo</p>
                <h4 class="imagenes-section-title mb-1">Lectura gerencial del periodo</h4>
                <p class="imagenes-section-copy mb-0">Consolida producción, cierre operativo, facturación y presión de backlog para el rango <strong><?= htmlspecialchars($rangeSummary, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
            </div>
            <div class="imagenes-section-badges">
                <span class="badge bg-light text-primary">Fuente: Laravel V2</span>
                <span class="badge bg-light text-dark">Registros: <?= htmlspecialchars((string) ($cardIndex['Total estudios']['value'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="imagenes-kpi-grid">
                    <?php foreach ($summaryCards as $card): ?>
                        <article class="imagenes-kpi-card imagenes-kpi-card--summary">
                            <p class="imagenes-kpi-label mb-1"><?= htmlspecialchars((string)($card['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <h4 class="imagenes-kpi-value mb-1"><?= htmlspecialchars((string)($card['value'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></h4>
                            <p class="imagenes-kpi-hint mb-0"><?= htmlspecialchars((string)($card['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="imagenes-insight-card">
                    <h6 class="imagenes-chart-title">Hallazgos del periodo</h6>
                    <ul class="imagenes-insight-list mb-0">
                        <?php foreach ($executiveHighlights as $highlight): ?>
                            <li><?= htmlspecialchars($highlight, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section id="imagenes-operacion" class="imagenes-section-card mb-3">
        <div class="imagenes-section-head">
            <div>
                <p class="imagenes-section-kicker mb-1">Sección 1</p>
                <h4 class="imagenes-section-title mb-1">Operación y tiempos</h4>
                <p class="imagenes-section-copy mb-0">Este bloque deja claro volumen, conversión, tráfico y presión de no informados.</p>
            </div>
        </div>
        <div class="imagenes-kpi-grid imagenes-kpi-grid--compact mb-3">
            <?php foreach ($operationCards as $card): ?>
                <article class="imagenes-kpi-card">
                    <p class="imagenes-kpi-label mb-1"><?= htmlspecialchars((string)($card['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <h4 class="imagenes-kpi-value mb-1"><?= htmlspecialchars((string)($card['value'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="imagenes-kpi-hint mb-0"><?= htmlspecialchars((string)($card['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="imagenes-stat-strip mb-3">
            <div class="imagenes-stat-pill">
                <span>TAT promedio</span>
                <strong><?= htmlspecialchars(($dashboardMeta['tat_promedio_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_promedio_horas'], 2) . ' h' : '—', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="imagenes-stat-pill">
                <span>TAT mediana</span>
                <strong><?= htmlspecialchars(($dashboardMeta['tat_mediana_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_mediana_horas'], 2) . ' h' : '—', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="imagenes-stat-pill">
                <span>TAT P90</span>
                <strong><?= htmlspecialchars(($dashboardMeta['tat_p90_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_p90_horas'], 2) . ' h' : '—', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <div class="imagenes-chart-card">
                    <h6 class="imagenes-chart-title">Embudo operativo</h6>
                    <div class="imagenes-chart-wrap">
                        <canvas id="chartImagenesEmbudoOperativo"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="imagenes-chart-card">
                    <h6 class="imagenes-chart-title">Serie diaria (realizados vs informados)</h6>
                    <div class="imagenes-chart-wrap">
                        <canvas id="chartImagenesSerieDiaria"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="imagenes-chart-card">
                    <h6 class="imagenes-chart-title">Citas generadas vs exámenes realizados</h6>
                    <div class="imagenes-chart-wrap">
                        <canvas id="chartImagenesCitasRealizados"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="imagenes-chart-card">
                    <h6 class="imagenes-chart-title">Tráfico por día de semana</h6>
                    <div class="imagenes-chart-wrap">
                        <canvas id="chartImagenesTraficoSemana"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="imagenes-chart-card">
                    <h6 class="imagenes-chart-title">Aging de no informados</h6>
                    <div class="imagenes-chart-wrap imagenes-chart-wrap--short">
                        <canvas id="chartImagenesAging"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="imagenes-facturacion" class="imagenes-section-card mb-3">
        <div class="imagenes-section-head">
            <div>
                <p class="imagenes-section-kicker mb-1">Sección 2</p>
                <h4 class="imagenes-section-title mb-1">Facturación y cierre</h4>
                <p class="imagenes-section-copy mb-0">Aquí se concentra el backlog atendido, el cierre por categoría y la oportunidad económica pública.</p>
            </div>
        </div>
        <div class="imagenes-kpi-grid imagenes-kpi-grid--compact mb-3">
            <?php foreach ($facturacionCards as $card): ?>
                <article class="imagenes-kpi-card">
                    <p class="imagenes-kpi-label mb-1"><?= htmlspecialchars((string)($card['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <h4 class="imagenes-kpi-value mb-1"><?= htmlspecialchars((string)($card['value'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="imagenes-kpi-hint mb-0"><?= htmlspecialchars((string)($card['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="row g-3">
            <div class="col-12 col-xl-4">
                <div class="imagenes-chart-card">
                    <h6 class="imagenes-chart-title">Trazabilidad facturación</h6>
                    <div class="imagenes-chart-wrap">
                        <canvas id="chartImagenesTrazabilidad"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="imagenes-chart-card">
                    <h6 class="imagenes-chart-title">Backlog de facturación por categoría</h6>
                    <div class="imagenes-chart-wrap">
                        <canvas id="chartImagenesBacklogCategoria"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="imagenes-chart-card">
                    <h6 class="imagenes-chart-title">Rendimiento económico</h6>
                    <div class="imagenes-chart-wrap">
                        <canvas id="chartImagenesRendimientoEconomico"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="imagenes-demanda" class="imagenes-section-card mb-3">
        <div class="imagenes-section-head">
            <div>
                <p class="imagenes-section-kicker mb-1">Sección 3</p>
                <h4 class="imagenes-section-title mb-1">Demanda y mezcla</h4>
                <p class="imagenes-section-copy mb-0">Este bloque ordena quién solicita, qué estudios pesan más y cómo se distribuye el volumen por seguro o plan.</p>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12 col-xl-4">
                <div class="imagenes-chart-card">
                    <h6 class="imagenes-chart-title">Top códigos de imágenes</h6>
                    <div class="imagenes-chart-wrap">
                        <canvas id="chartImagenesMixCodigos"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="imagenes-chart-card">
                    <h6 class="imagenes-chart-title">Top 10 doctores solicitantes</h6>
                    <div class="imagenes-chart-wrap">
                        <canvas id="chartImagenesTopDoctoresSolicitantes"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="imagenes-chart-card">
                    <h6 class="imagenes-chart-title"><?= htmlspecialchars($insuranceBreakdownTitle !== '' ? $insuranceBreakdownTitle : 'Empresas de seguro', ENT_QUOTES, 'UTF-8') ?></h6>
                    <div class="imagenes-chart-wrap">
                        <canvas id="chartImagenesAnalisisSeguro"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </section>
</section>

<style>
    html {
        scroll-behavior: smooth;
    }
    .imagenes-section-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
    }
    .imagenes-section-nav a {
        display: inline-flex;
        align-items: center;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
        background: #eef5ff;
        color: #0b4f9c;
        font-size: 0.82rem;
        font-weight: 600;
        text-decoration: none;
    }
    .imagenes-section-card {
        border: 1px solid #e7ebf0;
        border-radius: 1rem;
        background: #ffffff;
        padding: 1rem;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
    }
    .imagenes-section-head {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    .imagenes-section-kicker {
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #6c757d;
    }
    .imagenes-section-title {
        font-size: 1.05rem;
        color: #16324f;
    }
    .imagenes-section-copy {
        font-size: 0.88rem;
        color: #64748b;
        max-width: 780px;
    }
    .imagenes-section-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        justify-content: flex-end;
    }
    .imagenes-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 0.75rem;
    }
    .imagenes-kpi-grid--compact {
        grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
    }
    .imagenes-kpi-card {
        border: 1px solid #e7ebf0;
        border-radius: 0.75rem;
        padding: 0.75rem 0.85rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .imagenes-kpi-card--summary {
        border-color: #d7e6fb;
        background: linear-gradient(180deg, #ffffff 0%, #eef6ff 100%);
    }
    .imagenes-kpi-label {
        font-size: 0.78rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .imagenes-kpi-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: #0b4f9c;
    }
    .imagenes-kpi-hint {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .imagenes-chart-card {
        border: 1px solid #e7ebf0;
        border-radius: 0.75rem;
        padding: 0.75rem 0.9rem;
        background: #fff;
    }
    .imagenes-chart-title {
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
        color: #34495e;
    }
    .imagenes-chart-wrap {
        position: relative;
        height: 290px;
        max-height: 290px;
    }
    .imagenes-chart-wrap--short {
        height: 240px;
        max-height: 240px;
    }
    .imagenes-chart-wrap canvas {
        width: 100% !important;
        height: 100% !important;
        display: block;
    }
    .imagenes-insight-card {
        border: 1px solid #dbeafe;
        border-radius: 0.9rem;
        background: linear-gradient(180deg, #f8fbff 0%, #eff6ff 100%);
        padding: 0.95rem 1rem;
    }
    .imagenes-insight-list {
        padding-left: 1rem;
        color: #334155;
    }
    .imagenes-insight-list li + li {
        margin-top: 0.55rem;
    }
    .imagenes-stat-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.75rem;
    }
    .imagenes-stat-pill {
        border: 1px dashed #cbd5e1;
        border-radius: 0.85rem;
        padding: 0.7rem 0.85rem;
        background: #f8fafc;
    }
    .imagenes-stat-pill span {
        display: block;
        font-size: 0.78rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: 0.2rem;
    }
    .imagenes-stat-pill strong {
        font-size: 1rem;
        color: #0f172a;
    }
    @media (max-width: 991.98px) {
        .imagenes-section-head {
            flex-direction: column;
        }
        .imagenes-section-badges {
            justify-content: flex-start;
        }
        .imagenes-chart-wrap {
            height: 240px;
            max-height: 240px;
        }
    }
</style>

<script>
    (function () {
        const dashboardData = <?= json_encode($dashboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"cards":[],"meta":[],"charts":[]}' ?>;

        function drawChart(targetId, configBuilder, hasData) {
            const canvas = document.getElementById(targetId);
            if (!canvas) return;
            if (!hasData) {
                const wrapper = canvas.parentNode;
                if (wrapper) {
                    wrapper.innerHTML = '<p class="text-muted small mb-0">Sin datos para el rango seleccionado.</p>';
                }
                return;
            }
            if (typeof Chart === 'undefined') return;
            new Chart(canvas.getContext('2d'), configBuilder());
        }

        document.addEventListener('DOMContentLoaded', function () {
            const charts = (dashboardData && dashboardData.charts) ? dashboardData.charts : {};
            const cards = Array.isArray(dashboardData.cards) ? dashboardData.cards : [];
            const serie = charts.serie_diaria || {};
            const trazabilidad = charts.trazabilidad || {};
            const citasRealizados = charts.citas_vs_realizados || {};
            const traficoSemana = charts.trafico_dia_semana || {};
            const mix = charts.mix_codigos || {};
            const topDoctoresSolicitantes = charts.top_doctores_solicitantes || {};
            const analisisSeguro = charts.analisis_seguro || {};
            const aging = charts.aging_backlog || {};
            const backlogCategoria = charts.backlog_facturacion_categoria || {};
            const rendimientoEconomico = charts.rendimiento_economico || {};
            const cardValueMap = cards.reduce(function (acc, card) {
                const label = String((card && card.label) || '').trim();
                const value = Number(String((card && card.value) || '0').replace(/[^0-9.\-]/g, '')) || 0;
                if (label !== '') {
                    acc[label] = value;
                }
                return acc;
            }, {});

            drawChart(
                'chartImagenesEmbudoOperativo',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: ['Total estudios', 'Atendidos', 'Informadas', 'Facturados'],
                            datasets: [
                                {
                                    label: 'Casos',
                                    data: [
                                        Number(cardValueMap['Total estudios'] || 0),
                                        Number(cardValueMap['Atendidos'] || 0),
                                        Number(cardValueMap['Informadas'] || 0),
                                        Number(cardValueMap['Facturados'] || 0)
                                    ],
                                    backgroundColor: ['#cfe2ff', '#7cc6ff', '#45b36b', '#0b8f55'],
                                    borderRadius: 10
                                }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {x: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                ['Total estudios', 'Atendidos', 'Informadas', 'Facturados'].some(function (label) {
                    return Number(cardValueMap[label] || 0) > 0;
                })
            );

            drawChart(
                'chartImagenesSerieDiaria',
                function () {
                    return {
                        type: 'line',
                        data: {
                            labels: Array.isArray(serie.labels) ? serie.labels : [],
                            datasets: [
                                {
                                    label: 'Realizados',
                                    data: Array.isArray(serie.realizados) ? serie.realizados : [],
                                    borderColor: '#0d6efd',
                                    backgroundColor: 'rgba(13,110,253,.15)',
                                    borderWidth: 2,
                                    tension: 0.25
                                },
                                {
                                    label: 'Informados',
                                    data: Array.isArray(serie.informados) ? serie.informados : [],
                                    borderColor: '#198754',
                                    backgroundColor: 'rgba(25,135,84,.15)',
                                    borderWidth: 2,
                                    tension: 0.25
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {position: 'top'}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                Array.isArray(serie.labels) && serie.labels.length > 0
            );

            drawChart(
                'chartImagenesTrazabilidad',
                function () {
                    return {
                        type: 'doughnut',
                        data: {
                            labels: Array.isArray(trazabilidad.labels) ? trazabilidad.labels : [],
                            datasets: [
                                {
                                    data: Array.isArray(trazabilidad.values) ? trazabilidad.values : [],
                                    backgroundColor: ['#198754', '#dc3545', '#fd7e14', '#6c757d']
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {position: 'bottom'}}
                        }
                    };
                },
                Array.isArray(trazabilidad.values) && trazabilidad.values.some(function (value) { return Number(value || 0) > 0; })
            );

            drawChart(
                'chartImagenesMixCodigos',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(mix.labels) ? mix.labels : [],
                            datasets: [
                                {
                                    label: 'Estudios',
                                    data: Array.isArray(mix.values) ? mix.values : [],
                                    backgroundColor: '#0dcaf0'
                                }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {x: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                Array.isArray(mix.labels) && mix.labels.length > 0
            );

            drawChart(
                'chartImagenesTopDoctoresSolicitantes',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(topDoctoresSolicitantes.labels) ? topDoctoresSolicitantes.labels : [],
                            datasets: [
                                {
                                    label: 'Solicitudes',
                                    data: Array.isArray(topDoctoresSolicitantes.values) ? topDoctoresSolicitantes.values : [],
                                    backgroundColor: '#f59e0b'
                                }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {x: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                Array.isArray(topDoctoresSolicitantes.labels) && topDoctoresSolicitantes.labels.length > 0
            );

            drawChart(
                'chartImagenesAnalisisSeguro',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(analisisSeguro.labels) ? analisisSeguro.labels : [],
                            datasets: [
                                {
                                    label: 'Estudios',
                                    data: Array.isArray(analisisSeguro.values) ? analisisSeguro.values : [],
                                    backgroundColor: '#198754'
                                }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {x: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                Array.isArray(analisisSeguro.labels) && analisisSeguro.labels.length > 0
            );

            drawChart(
                'chartImagenesAging',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(aging.labels) ? aging.labels : [],
                            datasets: [
                                {
                                    label: 'No informados',
                                    data: Array.isArray(aging.values) ? aging.values : [],
                                    backgroundColor: ['#20c997', '#ffc107', '#fd7e14', '#dc3545']
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                Array.isArray(aging.values) && aging.values.some(function (value) { return Number(value || 0) > 0; })
            );

            drawChart(
                'chartImagenesCitasRealizados',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(citasRealizados.labels) ? citasRealizados.labels : [],
                            datasets: [
                                {
                                    label: 'Casos',
                                    data: Array.isArray(citasRealizados.values) ? citasRealizados.values : [],
                                    backgroundColor: ['#0d6efd', '#198754', '#fd7e14']
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                Array.isArray(citasRealizados.values) && citasRealizados.values.some(function (value) { return Number(value || 0) > 0; })
            );

            drawChart(
                'chartImagenesTraficoSemana',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(traficoSemana.labels) ? traficoSemana.labels : [],
                            datasets: [
                                {
                                    label: 'Estudios',
                                    data: Array.isArray(traficoSemana.values) ? traficoSemana.values : [],
                                    backgroundColor: '#6f42c1'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                Array.isArray(traficoSemana.values) && traficoSemana.values.some(function (value) { return Number(value || 0) > 0; })
            );

            drawChart(
                'chartImagenesBacklogCategoria',
                function () {
                    const datasets = Array.isArray(backlogCategoria.datasets) ? backlogCategoria.datasets : [];
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(backlogCategoria.labels) ? backlogCategoria.labels : [],
                            datasets: datasets.map(function (dataset, index) {
                                const palette = [
                                    {backgroundColor: '#0d6efd', borderColor: '#0a58ca'},
                                    {backgroundColor: '#6f42c1', borderColor: '#59359c'},
                                    {backgroundColor: '#20c997', borderColor: '#198754'}
                                ];
                                const colors = palette[index] || palette[palette.length - 1];
                                return {
                                    label: dataset.label || ('Serie ' + (index + 1)),
                                    data: Array.isArray(dataset.values) ? dataset.values : [],
                                    backgroundColor: colors.backgroundColor,
                                    borderColor: colors.borderColor,
                                    borderWidth: 1
                                };
                            })
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {position: 'top'}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
                        }
                    };
                },
                Array.isArray(backlogCategoria.datasets)
                    && backlogCategoria.datasets.some(function (dataset) {
                        return Array.isArray(dataset.values) && dataset.values.some(function (value) { return Number(value || 0) > 0; });
                    })
            );

            drawChart(
                'chartImagenesRendimientoEconomico',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(rendimientoEconomico.labels) ? rendimientoEconomico.labels : [],
                            datasets: [
                                {
                                    label: 'Monto',
                                    data: Array.isArray(rendimientoEconomico.values) ? rendimientoEconomico.values : [],
                                    backgroundColor: ['#198754', '#fd7e14']
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {display: false},
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            return '$' + Number(context.parsed.y || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function (value) {
                                            return '$' + Number(value || 0).toLocaleString('en-US');
                                        }
                                    }
                                }
                            }
                        }
                    };
                },
                Array.isArray(rendimientoEconomico.values) && rendimientoEconomico.values.some(function (value) { return Number(value || 0) > 0; })
            );
        });
    })();
</script>

@endsection
