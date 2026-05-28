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
$dashboardCharts = is_array($dashboard['charts'] ?? null) ? $dashboard['charts'] : [];
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

$cardIndex = [];
foreach ($dashboardCards as $card) {
    $label = trim((string) ($card['label'] ?? ''));
    if ($label !== '') {
        $cardIndex[$label] = $card;
    }
}

$cardText = static function (string $label, string $key = 'value', string $default = '0') use ($cardIndex): string {
    return trim((string)($cardIndex[$label][$key] ?? $default));
};

$cardNumber = static function (string $label) use ($cardIndex): float {
    $raw = trim((string)($cardIndex[$label]['value'] ?? '0'));
    $normalized = preg_replace('/[^\d\.\-]/', '', str_replace(',', '', $raw));

    return is_string($normalized) && $normalized !== '' ? (float)$normalized : 0.0;
};

$money = static fn(float $value): string => '$' . number_format($value, 2);
$countText = static fn(int $value): string => number_format($value);
$percentText = static fn(?float $value): string => $value !== null ? number_format($value, 1) . '%' : '—';
$severityByValue = static function (float $value, float $warning, float $critical): string {
    if ($value >= $critical) {
        return 'critical';
    }
    if ($value >= $warning) {
        return 'warning';
    }

    return 'good';
};
$severityByPercent = static function (?float $value, float $warning, float $critical): string {
    if ($value === null) {
        return 'neutral';
    }
    if ($value < $critical) {
        return 'critical';
    }
    if ($value < $warning) {
        return 'warning';
    }

    return 'good';
};

$rangeSummary = trim((string)($filters['fecha_inicio'] ?? '')) !== '' && trim((string)($filters['fecha_fin'] ?? '')) !== ''
    ? trim((string)($filters['fecha_inicio'] ?? '')) . ' a ' . trim((string)($filters['fecha_fin'] ?? ''))
    : 'Rango abierto';
$filterPills = array_values(array_filter([
    'Periodo: ' . $rangeSummary,
    trim((string)($filters['sede'] ?? '')) !== '' ? 'Sede: ' . trim((string)$filters['sede']) : 'Todas las sedes',
    trim((string)($filters['afiliacion_categoria'] ?? '')) !== '' ? 'Categoría: ' . trim((string)$filters['afiliacion_categoria']) : 'Todas las categorías',
    trim((string)($filters['afiliacion'] ?? '')) !== '' ? 'Empresa: ' . trim((string)$filters['afiliacion']) : 'Todas las empresas',
    trim((string)($filters['seguro'] ?? '')) !== '' ? 'Plan: ' . trim((string)$filters['seguro']) : '',
    trim((string)($filters['tipo_examen'] ?? '')) !== '' ? 'Examen: ' . trim((string)$filters['tipo_examen']) : '',
    trim((string)($filters['paciente'] ?? '')) !== '' ? 'Paciente: ' . trim((string)$filters['paciente']) : '',
]));

$solicitudesTotal = (int)($dashboardMeta['solicitudes_total'] ?? $cardNumber('Solicitudes de exámenes'));
$solicitudesAgendadas = (int)($dashboardMeta['solicitudes_agendadas_al_corte'] ?? $cardNumber('Agendadas al corte'));
$solicitudesRealizadas = (int)($dashboardMeta['solicitudes_realizadas_al_corte'] ?? $cardNumber('Realizadas al corte'));
$solicitudesInformadas = (int)($dashboardMeta['solicitudes_informadas'] ?? $cardNumber('Informadas'));
$solicitudesFacturadas = (int)($dashboardMeta['solicitudes_facturadas'] ?? $cardNumber('Facturados'));
$solicitudesSinAgenda = (int)($dashboardMeta['solicitudes_sin_agenda'] ?? $cardNumber('Solicitudes sin agenda'));
$solicitudesPendientesVigentes = (int)($dashboardMeta['solicitudes_pendientes_vigentes'] ?? $cardNumber('Pendientes vigentes de cohorte'));
$solicitudesRealizadasPostCorte = (int)($dashboardMeta['solicitudes_realizadas_post_corte'] ?? $cardNumber('Realizadas posterior al corte'));
$solicitudesAusentes = (int)($dashboardMeta['solicitudes_ausentes'] ?? $cardNumber('Ausentes de cohorte'));
$solicitudesSinAgendaMonto = (float)($dashboardMeta['solicitudes_sin_agenda_monto_estimado'] ?? 0);
$solicitudesSinAgendaSinTarifa = (int)($dashboardMeta['solicitudes_sin_agenda_sin_tarifa'] ?? 0);
$cumplimientoAlCorte = ($dashboardMeta['cumplimiento_realizacion_al_corte_pct'] ?? null) !== null ? (float)$dashboardMeta['cumplimiento_realizacion_al_corte_pct'] : null;

$agendasPeriodo = (int)$cardNumber('Agendas del periodo');
$atendidosPeriodo = (int)$cardNumber('Atendidos');
$informadasPeriodo = (int)$cardNumber('Informadas');
$facturadosPeriodo = (int)$cardNumber('Facturados');
$pendienteFacturar = (int)($dashboardMeta['pendientes_facturar'] ?? $cardNumber('Pendiente de facturar'));
$pendientePago = (int)($dashboardMeta['pendientes_pago'] ?? $cardNumber('Pendiente de pago'));
$pendientesOperativos = (int)($dashboardMeta['pendientes_operativos'] ?? $cardNumber('Pendientes operativos'));
$perdidaOperativa = (int)$cardNumber('Pérdida operativa');
$pendientesSinTarifa = (int)($dashboardMeta['pendientes_facturar_sin_tarifa'] ?? 0);
$pendientesPublico = (int)($dashboardMeta['pendientes_facturar_publico'] ?? 0);
$pendientesPrivado = (int)($dashboardMeta['pendientes_facturar_privado'] ?? 0);
$pendientesOtros = (int)($dashboardMeta['pendientes_facturar_otros'] ?? 0);
$facturadosPublico = (int)($dashboardMeta['facturados_publico'] ?? 0);
$facturadosPrivado = (int)($dashboardMeta['facturados_privado'] ?? 0);
$facturadosOtros = (int)($dashboardMeta['facturados_otros'] ?? 0);
$produccionFacturada = (float)($dashboardMeta['produccion_facturada'] ?? 0);
$pendienteEstimado = (float)($dashboardMeta['monto_pendiente_estimado'] ?? 0);
$pendienteEstimadoPublico = (float)($dashboardMeta['monto_pendiente_estimado_publico'] ?? 0);
$ticketPromedio = (float)($dashboardMeta['ticket_promedio_facturado'] ?? 0);
$procedimientosFacturados = (int)($dashboardMeta['procedimientos_facturados'] ?? 0);
$sla48 = ($dashboardMeta['sla48_pct'] ?? null) !== null ? (float)$dashboardMeta['sla48_pct'] : null;
$sla48Text = $cardText('SLA informe <= 48h', 'value', '—');
$tatPromedio = ($dashboardMeta['tat_promedio_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_promedio_horas'], 2) . ' h' : '—';
$tatP90 = ($dashboardMeta['tat_p90_horas'] ?? null) !== null ? number_format((float)$dashboardMeta['tat_p90_horas'], 2) . ' h' : '—';

$conversion = static function (int $from, int $to): ?float {
    return $from > 0 ? ($to * 100 / $from) : null;
};
$flowStages = [
    [
        'key' => 'solicitudes',
        'label' => 'Solicitudes',
        'value' => $solicitudesTotal,
        'context' => 'Demanda registrada',
        'class' => 'health',
    ],
    [
        'key' => 'agendadas',
        'label' => 'Agendadas',
        'value' => $solicitudesAgendadas,
        'context' => $percentText($conversion($solicitudesTotal, $solicitudesAgendadas)) . ' de solicitudes',
        'leak' => $solicitudesSinAgenda,
        'leakLabel' => 'Sin agenda',
        'leakAmount' => $solicitudesSinAgendaMonto,
        'class' => 'health',
    ],
    [
        'key' => 'realizadas',
        'label' => 'Realizadas',
        'value' => $solicitudesRealizadas,
        'context' => $percentText($cumplimientoAlCorte) . ' al corte',
        'leak' => $solicitudesAusentes + $solicitudesPendientesVigentes,
        'leakLabel' => 'Ausentes / abiertas',
        'leakAmount' => 0.0,
        'class' => 'good',
    ],
    [
        'key' => 'informadas',
        'label' => 'Informadas',
        'value' => $solicitudesInformadas,
        'context' => 'SLA <= 48h: ' . $sla48Text,
        'leak' => max(0, $solicitudesRealizadas - $solicitudesInformadas),
        'leakLabel' => 'Pend. informe',
        'leakAmount' => 0.0,
        'class' => 'warning',
    ],
    [
        'key' => 'facturadas',
        'label' => 'Facturadas',
        'value' => $solicitudesFacturadas > 0 ? $solicitudesFacturadas : $facturadosPeriodo,
        'context' => $money($produccionFacturada) . ' real',
        'leak' => $pendienteFacturar,
        'leakLabel' => 'Pend. facturar',
        'leakAmount' => $pendienteEstimado,
        'class' => 'good',
    ],
];

$executiveKpis = [
    [
        'label' => 'Facturado real',
        'value' => $money($produccionFacturada),
        'hint' => $countText($facturadosPeriodo) . ' estudios facturados',
        'source' => 'Facturadas',
        'class' => 'good',
        'severity' => $produccionFacturada > 0 ? 'good' : 'neutral',
    ],
    [
        'label' => 'Pendiente de facturar estimado',
        'value' => $money($pendienteEstimado),
        'hint' => $countText($pendienteFacturar) . ' realizados sin billing',
        'source' => 'Realizadas -> Facturadas',
        'class' => 'warning',
        'severity' => $severityByValue($pendienteEstimado, 1, 1),
    ],
    [
        'label' => 'Pérdida por no agendar',
        'value' => $money($solicitudesSinAgendaMonto),
        'hint' => $countText($solicitudesSinAgenda) . ' solicitudes sin agenda',
        'source' => 'Solicitudes -> Agendadas',
        'class' => 'danger',
        'severity' => $severityByValue($solicitudesSinAgendaMonto, 1, 1),
    ],
    [
        'label' => 'Pendiente de pago',
        'value' => $countText($pendientePago),
        'hint' => 'Billing emitido en cartera',
        'source' => 'Facturadas -> Cobro',
        'class' => 'amber',
        'severity' => $severityByValue($pendientePago, 1, 1),
    ],
    [
        'label' => 'Cumplimiento al corte',
        'value' => $percentText($cumplimientoAlCorte),
        'hint' => $countText($solicitudesRealizadas) . ' de ' . $countText($solicitudesTotal) . ' solicitudes',
        'source' => 'Solicitudes -> Realizadas',
        'class' => 'health',
        'severity' => $severityByPercent($cumplimientoAlCorte, 85, 70),
    ],
];

$priorityActions = [];
$addAction = static function (array &$actions, string $severity, string $title, string $metric, string $owner, string $action, string $link = '#diagnostico-financiero'): void {
    if (count($actions) >= 5) {
        return;
    }
    $actions[] = [
        'severity' => $severity,
        'title' => $title,
        'metric' => $metric,
        'owner' => $owner,
        'action' => $action,
        'link' => $link,
    ];
};
if ($pendientesSinTarifa > 0) {
    $addAction($priorityActions, 'critical', 'Tarifas faltantes bloquean facturación', $countText($pendientesSinTarifa) . ' casos', 'Facturación', 'Completar tarifa por código/categoría.', '#diagnostico-financiero');
}
if ($solicitudesSinAgendaMonto > 0 || $solicitudesSinAgenda > 0) {
    $addAction($priorityActions, 'critical', 'Solicitudes sin agenda generan pérdida', $money($solicitudesSinAgendaMonto), 'Operaciones', 'Agendar o cerrar causa de no conversión.', '#diagnostico-solicitudes');
}
if ($pendienteEstimado > 0 || $pendienteFacturar > 0) {
    $addAction($priorityActions, 'warning', 'Backlog realizado sin billing', $money($pendienteEstimado), 'Facturación', 'Priorizar emisión de billing real.', '#diagnostico-financiero');
}
if ($pendientePago > 0) {
    $addAction($priorityActions, 'warning', 'Cartera pendiente de cobro', $countText($pendientePago) . ' registros', 'Cobranzas', 'Revisar pagos/estados en cartera.', '#diagnostico-financiero');
}
if ($pendientesOperativos > 0) {
    $addAction($priorityActions, 'neutral', 'Agendas sin cierre operativo', $countText($pendientesOperativos) . ' abiertas', 'Operaciones', 'Cerrar evidencia o reprogramar.', '#diagnostico-operativo');
}
if ($priorityActions === []) {
    $addAction($priorityActions, 'good', 'Sin alertas críticas del rango', '0 bloqueos', 'Gerencia', 'Mantener seguimiento con los filtros actuales.', '#diagnostico-operativo');
}

$operationCoreCards = array_values(array_filter([
    $cardIndex['Agendas del periodo'] ?? null,
    $cardIndex['Atendidos'] ?? null,
    $cardIndex['Informadas'] ?? null,
    $cardIndex['Facturados'] ?? null,
    $cardIndex['Cumplimiento cita->realización'] ?? null,
    $cardIndex['SLA informe <= 48h'] ?? null,
    $cardIndex['Pérdida operativa'] ?? null,
    $cardIndex['Pendientes operativos'] ?? null,
]));
$requestCards = array_values(array_filter([
    $cardIndex['Solicitudes de exámenes'] ?? null,
    $cardIndex['Agendadas al corte'] ?? null,
    $cardIndex['Realizadas al corte'] ?? null,
    $cardIndex['Realizadas posterior al corte'] ?? null,
    $cardIndex['Solicitudes sin agenda'] ?? null,
    $cardIndex['Ausentes de cohorte'] ?? null,
    $cardIndex['Pendientes vigentes de cohorte'] ?? null,
    $cardIndex['Cumplimiento al corte'] ?? null,
]));
$financeRows = [
    ['Pública', $facturadosPublico, $pendientesPublico, $pendienteEstimadoPublico],
    ['Privada', $facturadosPrivado, $pendientesPrivado, 0],
    ['Particular / otros', $facturadosOtros, $pendientesOtros, 0],
];
?>

<div class="content-header imagenes-page-header">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div>
            <h3 class="page-title mb-1">Dashboard de Imágenes</h3>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                <li class="breadcrumb-item"><a href="/v2/imagenes/examenes-realizados">Imágenes</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dashboard ejecutivo</li>
            </ol>
        </div>
        <div class="imagenes-header-actions">
            <a href="/v2/imagenes/dashboard/export/pdf<?= $exportQuery !== '' ? ('?' . htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8')) : '' ?>" class="btn btn-outline-danger btn-sm">
                <i class="mdi mdi-file-pdf-box me-1"></i> PDF
            </a>
            <a href="/v2/imagenes/dashboard/export/excel<?= $exportQuery !== '' ? ('?' . htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8')) : '' ?>" class="btn btn-outline-success btn-sm">
                <i class="mdi mdi-file-excel-box me-1"></i> Excel
            </a>
            <a href="/v2/imagenes/examenes-realizados" class="btn btn-outline-primary btn-sm">
                <i class="mdi mdi-format-list-bulleted me-1"></i> Detalle
            </a>
        </div>
    </div>
</div>

<section class="content imagenes-dashboard">
    <details class="imagenes-filter-panel mb-2">
        <summary>
            <span><i class="mdi mdi-filter-variant me-1"></i> Filtros y contexto</span>
            <small><?= htmlspecialchars(implode(' · ', array_slice($filterPills, 0, 3)), ENT_QUOTES, 'UTF-8') ?></small>
        </summary>
        <form class="row g-2 align-items-end mt-2" method="get">
            <div class="col-sm-6 col-lg-2">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control form-control-sm" name="fecha_inicio" value="<?= htmlspecialchars($filters['fecha_inicio'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control form-control-sm" name="fecha_fin" value="<?= htmlspecialchars($filters['fecha_fin'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label">Sede</label>
                <select class="form-select form-select-sm" name="sede">
                    <?php foreach ($sedeOptions as $option): ?>
                        <?php $optionValue = (string)($option['value'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($filters['sede'] ?? '')) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label">Categoría</label>
                <select class="form-select form-select-sm" name="afiliacion_categoria">
                    <?php foreach ($afiliacionCategoriaOptions as $option): ?>
                        <?php $optionValue = (string)($option['value'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($filters['afiliacion_categoria'] ?? '')) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label">Empresa</label>
                <select class="form-select form-select-sm" name="afiliacion">
                    <?php foreach ($afiliacionOptions as $option): ?>
                        <?php $optionValue = (string)($option['value'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($filters['afiliacion'] ?? '')) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label">Seguro / plan</label>
                <select class="form-select form-select-sm" name="seguro">
                    <?php foreach ($seguroOptions as $option): ?>
                        <?php $optionValue = (string)($option['value'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($filters['seguro'] ?? '')) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label">Tipo examen</label>
                <input type="text" class="form-control form-control-sm" name="tipo_examen" value="<?= htmlspecialchars($filters['tipo_examen'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="281032 / OCT / ...">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label">Paciente/Cédula</label>
                <input type="text" class="form-control form-control-sm" name="paciente" value="<?= htmlspecialchars($filters['paciente'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Nombre o ID">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="mdi mdi-filter-variant"></i> Aplicar filtros</button>
                <a href="/v2/imagenes/dashboard" class="btn btn-outline-secondary btn-sm"><i class="mdi mdi-close-circle-outline"></i> Limpiar</a>
            </div>
        </form>
    </details>

    <section class="imagenes-executive-shell" aria-label="Mapa ejecutivo financiero de imágenes">
        <div class="imagenes-executive-head">
            <div>
                <p class="imagenes-kicker mb-1">Mapa ejecutivo financiero</p>
                <h4 class="mb-1">De la solicitud al cobro: dónde se gana, se bloquea o se pierde</h4>
                <p class="mb-0">Cada KPI está conectado con una etapa del flujo para diferenciar facturado real, oportunidad estimada, pendiente de pago y pérdida.</p>
            </div>
            <div class="imagenes-context-pills">
                <?php foreach (array_slice($filterPills, 0, 4) as $pill): ?>
                    <span><?= htmlspecialchars($pill, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="imagenes-executive-kpis">
            <?php foreach ($executiveKpis as $kpi): ?>
                <article class="imagenes-exec-kpi is-<?= htmlspecialchars($kpi['class'], ENT_QUOTES, 'UTF-8') ?> severity-<?= htmlspecialchars($kpi['severity'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="imagenes-kpi-topline">
                        <span><?= htmlspecialchars($kpi['source'], ENT_QUOTES, 'UTF-8') ?></span>
                        <i class="mdi mdi-checkbox-blank-circle"></i>
                    </div>
                    <p><?= htmlspecialchars($kpi['label'], ENT_QUOTES, 'UTF-8') ?></p>
                    <strong><?= htmlspecialchars($kpi['value'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars($kpi['hint'], ENT_QUOTES, 'UTF-8') ?></small>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="imagenes-executive-grid">
            <div class="imagenes-flow-card">
                <div class="imagenes-card-head">
                    <div>
                        <h5>Flujo conectado</h5>
                        <p>Las fugas debajo de cada etapa explican por qué el dinero no llega a facturación/cobro.</p>
                    </div>
                    <span class="imagenes-badge">Vista principal sin scroll 1080p</span>
                </div>
                <div class="imagenes-flow">
                    <?php foreach ($flowStages as $index => $stage): ?>
                        <div class="imagenes-flow-stage is-<?= htmlspecialchars($stage['class'], ENT_QUOTES, 'UTF-8') ?>">
                            <span><?= htmlspecialchars($stage['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <strong><?= htmlspecialchars($countText((int)$stage['value']), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars($stage['context'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php if (($stage['leak'] ?? 0) > 0 || ($stage['leakAmount'] ?? 0) > 0): ?>
                                <div class="imagenes-flow-leak">
                                    <b><?= htmlspecialchars((string)($stage['leakLabel'] ?? 'Fuga'), ENT_QUOTES, 'UTF-8') ?></b>
                                    <em>
                                        <?= htmlspecialchars($countText((int)($stage['leak'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (($stage['leakAmount'] ?? 0) > 0): ?>
                                            · <?= htmlspecialchars($money((float)$stage['leakAmount']), ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </em>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($index < count($flowStages) - 1): ?>
                            <div class="imagenes-flow-link" aria-hidden="true">
                                <i class="mdi mdi-arrow-right-thin"></i>
                                <span><?= htmlspecialchars($percentText($conversion((int)$stage['value'], (int)$flowStages[$index + 1]['value'])), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="imagenes-flow-summary">
                    <span><b>Oportunidad estimada:</b> <?= htmlspecialchars($money($pendienteEstimado + $solicitudesSinAgendaMonto), ENT_QUOTES, 'UTF-8') ?></span>
                    <span><b>Arrastre post corte:</b> <?= htmlspecialchars($countText($solicitudesRealizadasPostCorte), ENT_QUOTES, 'UTF-8') ?> realizadas después del rango</span>
                    <span><b>SLA informes:</b> <?= htmlspecialchars($sla48Text, ENT_QUOTES, 'UTF-8') ?> · TAT P90 <?= htmlspecialchars($tatP90, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>

            <aside class="imagenes-actions-card">
                <div class="imagenes-card-head compact">
                    <div>
                        <h5>Acciones prioritarias</h5>
                        <p>Máximo 5 frentes accionables vinculados al flujo.</p>
                    </div>
                </div>
                <div class="imagenes-action-list">
                    <?php foreach ($priorityActions as $action): ?>
                        <a class="imagenes-action-item severity-<?= htmlspecialchars($action['severity'], ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($action['link'], ENT_QUOTES, 'UTF-8') ?>">
                            <span class="imagenes-action-dot"></span>
                            <span>
                                <strong><?= htmlspecialchars($action['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= htmlspecialchars($action['metric'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($action['owner'], ENT_QUOTES, 'UTF-8') ?></small>
                                <em><?= htmlspecialchars($action['action'], ENT_QUOTES, 'UTF-8') ?></em>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="imagenes-mini-ledger">
                    <div><span>Facturado</span><strong><?= htmlspecialchars($money($produccionFacturada), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div><span>Sin billing</span><strong><?= htmlspecialchars($money($pendienteEstimado), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div><span>No agendado</span><strong><?= htmlspecialchars($money($solicitudesSinAgendaMonto), ENT_QUOTES, 'UTF-8') ?></strong></div>
                </div>
            </aside>
        </div>
    </section>

    <section class="imagenes-diagnostics mt-3">
        <ul class="nav nav-pills imagenes-tabs" id="imagenesDashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="fin-tab" data-bs-toggle="pill" data-bs-target="#diagnostico-financiero" type="button" role="tab">Financiero</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="op-tab" data-bs-toggle="pill" data-bs-target="#diagnostico-operativo" type="button" role="tab">Operación</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sol-tab" data-bs-toggle="pill" data-bs-target="#diagnostico-solicitudes" type="button" role="tab">Solicitudes</button>
            </li>
        </ul>
        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="diagnostico-financiero" role="tabpanel" aria-labelledby="fin-tab">
                <div class="row g-3">
                    <div class="col-12 col-xl-4">
                        <div class="imagenes-chart-card">
                            <h6 class="imagenes-chart-title">Rendimiento económico</h6>
                            <div class="imagenes-chart-wrap"><canvas id="chartImagenesRendimientoEconomico"></canvas></div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="imagenes-chart-card">
                            <h6 class="imagenes-chart-title">Backlog por categoría</h6>
                            <div class="imagenes-chart-wrap"><canvas id="chartImagenesBacklogCategoria"></canvas></div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="imagenes-table-card">
                            <h6 class="imagenes-chart-title">Reconciliación financiera</h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Categoría</th>
                                            <th>Fact.</th>
                                            <th>Pend.</th>
                                            <th>Estimado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($financeRows as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string)$row[0], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($countText((int)$row[1]), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($countText((int)$row[2]), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= ((float)$row[3] > 0) ? htmlspecialchars($money((float)$row[3]), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="imagenes-table-note">Pendientes sin tarifa resoluble: <?= htmlspecialchars($countText($pendientesSinTarifa), ENT_QUOTES, 'UTF-8') ?>.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="diagnostico-operativo" role="tabpanel" aria-labelledby="op-tab">
                <div class="imagenes-compact-kpis mb-3">
                    <?php foreach ($operationCoreCards as $card): ?>
                        <article>
                            <span><?= htmlspecialchars((string)($card['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <strong><?= htmlspecialchars((string)($card['value'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars((string)($card['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-xl-4">
                        <div class="imagenes-chart-card">
                            <h6 class="imagenes-chart-title">Agenda vs cierre</h6>
                            <div class="imagenes-chart-wrap"><canvas id="chartImagenesCitasRealizados"></canvas></div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="imagenes-chart-card">
                            <h6 class="imagenes-chart-title">Serie diaria</h6>
                            <div class="imagenes-chart-wrap"><canvas id="chartImagenesSerieDiaria"></canvas></div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="imagenes-chart-card">
                            <h6 class="imagenes-chart-title">Top exámenes realizados</h6>
                            <div class="imagenes-chart-wrap"><canvas id="chartImagenesMixCodigos"></canvas></div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="imagenes-chart-card">
                            <h6 class="imagenes-chart-title">Tráfico por día</h6>
                            <div class="imagenes-chart-wrap"><canvas id="chartImagenesTraficoSemana"></canvas></div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="imagenes-chart-card">
                            <h6 class="imagenes-chart-title">Trazabilidad facturación</h6>
                            <div class="imagenes-chart-wrap"><canvas id="chartImagenesTrazabilidad"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="diagnostico-solicitudes" role="tabpanel" aria-labelledby="sol-tab">
                <div class="imagenes-compact-kpis mb-3">
                    <?php foreach ($requestCards as $card): ?>
                        <article>
                            <span><?= htmlspecialchars((string)($card['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <strong><?= htmlspecialchars((string)($card['value'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars((string)($card['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-xl-4">
                        <div class="imagenes-chart-card">
                            <h6 class="imagenes-chart-title">Embudo de solicitudes</h6>
                            <div class="imagenes-chart-wrap"><canvas id="chartImagenesEmbudoOperativo"></canvas></div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="imagenes-chart-card">
                            <h6 class="imagenes-chart-title">Top médicos solicitantes</h6>
                            <div class="imagenes-chart-wrap"><canvas id="chartImagenesTopDoctoresSolicitantes"></canvas></div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="imagenes-chart-card">
                            <h6 class="imagenes-chart-title">Top exámenes solicitados</h6>
                            <div class="imagenes-chart-wrap"><canvas id="chartImagenesTopExamenesSolicitados"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</section>

<style>
    :root {
        --img-health: #0891b2;
        --img-health-soft: #ecfeff;
        --img-good: #059669;
        --img-good-soft: #ecfdf5;
        --img-warning: #d97706;
        --img-warning-soft: #fffbeb;
        --img-danger: #dc2626;
        --img-danger-soft: #fef2f2;
        --img-ink: #164e63;
        --img-muted: #64748b;
        --img-line: #dbeafe;
        --img-surface: #ffffff;
    }
    .imagenes-page-header {
        padding-bottom: 0.45rem;
    }
    .imagenes-header-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        justify-content: flex-end;
    }
    .imagenes-dashboard {
        --section-gap: 0.75rem;
    }
    .imagenes-filter-panel {
        border: 1px solid #dbeafe;
        border-radius: 8px;
        background: #fff;
        padding: 0.55rem 0.75rem;
    }
    .imagenes-filter-panel summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        cursor: pointer;
        color: var(--img-ink);
        font-weight: 700;
        list-style: none;
    }
    .imagenes-filter-panel summary::-webkit-details-marker {
        display: none;
    }
    .imagenes-filter-panel small {
        color: var(--img-muted);
        font-weight: 500;
        text-align: right;
    }
    .imagenes-executive-shell {
        display: grid;
        grid-template-rows: auto auto minmax(0, 1fr);
        gap: var(--section-gap);
        min-height: 660px;
        max-height: calc(100vh - 178px);
        border: 1px solid #bae6fd;
        border-radius: 10px;
        background: linear-gradient(180deg, #f8feff 0%, #ffffff 52%, #f8fafc 100%);
        padding: 0.85rem;
        overflow: hidden;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
    }
    .imagenes-executive-head {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: flex-start;
    }
    .imagenes-executive-head h4 {
        color: #0f3f4a;
        font-size: 1.05rem;
        font-weight: 800;
    }
    .imagenes-executive-head p {
        color: var(--img-muted);
        font-size: 0.82rem;
        max-width: 760px;
    }
    .imagenes-kicker {
        color: var(--img-health);
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0;
        text-transform: uppercase;
    }
    .imagenes-context-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        justify-content: flex-end;
        max-width: 480px;
    }
    .imagenes-context-pills span,
    .imagenes-badge {
        border: 1px solid #cffafe;
        border-radius: 999px;
        background: #ecfeff;
        color: #155e75;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 0.25rem 0.5rem;
    }
    .imagenes-executive-kpis {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.6rem;
    }
    .imagenes-exec-kpi {
        border: 1px solid #e2e8f0;
        border-left-width: 4px;
        border-radius: 8px;
        background: #fff;
        padding: 0.55rem 0.65rem;
        min-width: 0;
    }
    .imagenes-exec-kpi p,
    .imagenes-exec-kpi small,
    .imagenes-exec-kpi strong {
        display: block;
    }
    .imagenes-exec-kpi p {
        margin: 0 0 0.15rem;
        color: #475569;
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    .imagenes-exec-kpi strong {
        color: #0f172a;
        font-size: clamp(1rem, 1.2vw, 1.45rem);
        line-height: 1.1;
        white-space: nowrap;
    }
    .imagenes-exec-kpi small {
        margin-top: 0.15rem;
        color: var(--img-muted);
        font-size: 0.73rem;
        line-height: 1.2;
    }
    .imagenes-kpi-topline {
        display: flex;
        justify-content: space-between;
        gap: 0.4rem;
        margin-bottom: 0.2rem;
        color: #64748b;
        font-size: 0.68rem;
        font-weight: 700;
    }
    .imagenes-exec-kpi.is-health { border-left-color: var(--img-health); }
    .imagenes-exec-kpi.is-good { border-left-color: var(--img-good); }
    .imagenes-exec-kpi.is-warning,
    .imagenes-exec-kpi.is-amber { border-left-color: var(--img-warning); }
    .imagenes-exec-kpi.is-danger { border-left-color: var(--img-danger); }
    .imagenes-executive-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 330px;
        gap: 0.75rem;
        min-height: 0;
    }
    .imagenes-flow-card,
    .imagenes-actions-card,
    .imagenes-chart-card,
    .imagenes-table-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: var(--img-surface);
        padding: 0.75rem;
    }
    .imagenes-flow-card,
    .imagenes-actions-card {
        min-height: 0;
        overflow: hidden;
    }
    .imagenes-card-head {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: flex-start;
        margin-bottom: 0.7rem;
    }
    .imagenes-card-head.compact {
        margin-bottom: 0.55rem;
    }
    .imagenes-card-head h5 {
        color: #0f3f4a;
        font-size: 0.95rem;
        font-weight: 800;
        margin: 0;
    }
    .imagenes-card-head p {
        color: var(--img-muted);
        font-size: 0.75rem;
        line-height: 1.25;
        margin: 0.15rem 0 0;
    }
    .imagenes-flow {
        display: grid;
        grid-template-columns: minmax(110px, 1fr) 44px minmax(110px, 1fr) 44px minmax(110px, 1fr) 44px minmax(110px, 1fr) 44px minmax(110px, 1fr);
        gap: 0.45rem;
        align-items: stretch;
    }
    .imagenes-flow-stage {
        position: relative;
        min-width: 0;
        border: 1px solid #dbeafe;
        border-radius: 8px;
        background: #f8fafc;
        padding: 0.65rem 0.55rem;
        text-align: center;
    }
    .imagenes-flow-stage span,
    .imagenes-flow-stage strong,
    .imagenes-flow-stage small {
        display: block;
    }
    .imagenes-flow-stage span {
        color: #475569;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    .imagenes-flow-stage strong {
        color: #0f172a;
        font-size: clamp(1.15rem, 1.65vw, 1.8rem);
        line-height: 1.1;
        margin-top: 0.15rem;
    }
    .imagenes-flow-stage small {
        color: var(--img-muted);
        font-size: 0.72rem;
        line-height: 1.2;
        min-height: 1.7em;
    }
    .imagenes-flow-stage.is-health { background: var(--img-health-soft); border-color: #a5f3fc; }
    .imagenes-flow-stage.is-good { background: var(--img-good-soft); border-color: #bbf7d0; }
    .imagenes-flow-stage.is-warning { background: var(--img-warning-soft); border-color: #fde68a; }
    .imagenes-flow-leak {
        margin-top: 0.45rem;
        border-top: 1px dashed #cbd5e1;
        padding-top: 0.35rem;
        color: #92400e;
    }
    .imagenes-flow-leak b,
    .imagenes-flow-leak em {
        display: block;
        font-size: 0.68rem;
        font-style: normal;
        line-height: 1.15;
    }
    .imagenes-flow-link {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: var(--img-health);
        font-weight: 800;
        font-size: 0.72rem;
        min-width: 0;
    }
    .imagenes-flow-link i {
        font-size: 1.35rem;
        line-height: 1;
    }
    .imagenes-flow-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.5rem;
        margin-top: 0.7rem;
    }
    .imagenes-flow-summary span {
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        background: #f8fafc;
        color: #334155;
        font-size: 0.74rem;
        line-height: 1.25;
        padding: 0.45rem 0.55rem;
    }
    .imagenes-action-list {
        display: grid;
        gap: 0.45rem;
    }
    .imagenes-action-item {
        display: grid;
        grid-template-columns: 10px minmax(0, 1fr);
        gap: 0.5rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        color: #334155;
        padding: 0.48rem;
        text-decoration: none;
    }
    .imagenes-action-item:hover {
        color: #0f172a;
        border-color: #67e8f9;
    }
    .imagenes-action-dot {
        width: 9px;
        height: 9px;
        border-radius: 999px;
        margin-top: 0.18rem;
        background: #94a3b8;
    }
    .imagenes-action-item strong,
    .imagenes-action-item small,
    .imagenes-action-item em {
        display: block;
    }
    .imagenes-action-item strong {
        font-size: 0.78rem;
        line-height: 1.15;
    }
    .imagenes-action-item small {
        color: var(--img-muted);
        font-size: 0.72rem;
        line-height: 1.15;
        margin-top: 0.12rem;
    }
    .imagenes-action-item em {
        color: #475569;
        font-size: 0.7rem;
        font-style: normal;
        line-height: 1.15;
        margin-top: 0.18rem;
    }
    .imagenes-action-item.severity-critical .imagenes-action-dot { background: var(--img-danger); }
    .imagenes-action-item.severity-warning .imagenes-action-dot { background: var(--img-warning); }
    .imagenes-action-item.severity-good .imagenes-action-dot { background: var(--img-good); }
    .imagenes-mini-ledger {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.4rem;
        margin-top: 0.55rem;
    }
    .imagenes-mini-ledger div {
        border-radius: 8px;
        background: #f8fafc;
        padding: 0.45rem;
    }
    .imagenes-mini-ledger span,
    .imagenes-mini-ledger strong {
        display: block;
    }
    .imagenes-mini-ledger span {
        color: var(--img-muted);
        font-size: 0.65rem;
        font-weight: 700;
    }
    .imagenes-mini-ledger strong {
        color: #0f172a;
        font-size: 0.78rem;
        white-space: nowrap;
    }
    .imagenes-tabs {
        gap: 0.45rem;
    }
    .imagenes-tabs .nav-link {
        border: 1px solid #dbeafe;
        border-radius: 8px;
        color: #155e75;
        font-weight: 800;
        padding: 0.38rem 0.75rem;
    }
    .imagenes-tabs .nav-link.active {
        background: var(--img-health);
        border-color: var(--img-health);
        color: #fff;
    }
    .imagenes-compact-kpis {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 0.55rem;
    }
    .imagenes-compact-kpis article {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        padding: 0.55rem;
    }
    .imagenes-compact-kpis span,
    .imagenes-compact-kpis strong,
    .imagenes-compact-kpis small {
        display: block;
    }
    .imagenes-compact-kpis span {
        color: var(--img-muted);
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    .imagenes-compact-kpis strong {
        color: #0f172a;
        font-size: 1.05rem;
    }
    .imagenes-compact-kpis small {
        color: var(--img-muted);
        font-size: 0.72rem;
        line-height: 1.2;
    }
    .imagenes-chart-title {
        color: #0f3f4a;
        font-size: 0.86rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }
    .imagenes-chart-wrap {
        position: relative;
        height: 260px;
        max-height: 260px;
    }
    .imagenes-chart-wrap canvas {
        display: block;
        width: 100% !important;
        height: 100% !important;
    }
    .imagenes-table-card .table {
        color: #334155;
        font-size: 0.82rem;
    }
    .imagenes-table-note {
        color: var(--img-muted);
        font-size: 0.74rem;
        margin: 0.55rem 0 0;
    }
    @media (max-width: 1399.98px) {
        .imagenes-executive-shell {
            max-height: none;
        }
        .imagenes-executive-grid {
            grid-template-columns: minmax(0, 1fr) 300px;
        }
        .imagenes-flow {
            grid-template-columns: repeat(5, minmax(120px, 1fr));
        }
        .imagenes-flow-link {
            display: none;
        }
    }
    @media (max-width: 991.98px) {
        .imagenes-executive-shell {
            max-height: none;
            overflow: visible;
        }
        .imagenes-executive-head,
        .imagenes-card-head {
            flex-direction: column;
        }
        .imagenes-context-pills {
            justify-content: flex-start;
            max-width: none;
        }
        .imagenes-executive-kpis,
        .imagenes-flow-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .imagenes-executive-grid {
            grid-template-columns: 1fr;
        }
        .imagenes-flow {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 575.98px) {
        .imagenes-executive-kpis,
        .imagenes-flow-summary,
        .imagenes-mini-ledger {
            grid-template-columns: 1fr;
        }
        .imagenes-filter-panel summary {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<script>
    (function () {
        const dashboardData = <?= json_encode($dashboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"cards":[],"meta":[],"charts":[]}' ?>;

        const renderedCharts = [];

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
            renderedCharts.push(new Chart(canvas.getContext('2d'), configBuilder()));
        }

        function moneyTick(value) {
            return '$' + Number(value || 0).toLocaleString('en-US');
        }

        document.addEventListener('DOMContentLoaded', function () {
            const charts = (dashboardData && dashboardData.charts) ? dashboardData.charts : {};
            const solicitudesPipeline = charts.solicitudes_pipeline || {};
            const serie = charts.serie_diaria || {};
            const trazabilidad = charts.trazabilidad || {};
            const citasRealizados = charts.citas_vs_realizados || {};
            const traficoSemana = charts.trafico_dia_semana || {};
            const mix = charts.mix_codigos || {};
            const topDoctoresSolicitantes = charts.top_doctores_solicitantes || {};
            const topExamenesSolicitados = charts.top_examenes_solicitados || {};
            const backlogCategoria = charts.backlog_facturacion_categoria || {};
            const rendimientoEconomico = charts.rendimiento_economico || {};
            const chartTextColor = '#334155';
            const chartGridColor = '#e2e8f0';

            drawChart(
                'chartImagenesEmbudoOperativo',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(solicitudesPipeline.labels) ? solicitudesPipeline.labels : [],
                            datasets: [{
                                label: 'Casos',
                                data: Array.isArray(solicitudesPipeline.values) ? solicitudesPipeline.values : [],
                                backgroundColor: ['#0891b2', '#06b6d4', '#059669', '#16a34a', '#d97706'],
                                borderRadius: 8
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {
                                x: {beginAtZero: true, ticks: {precision: 0, color: chartTextColor}, grid: {color: chartGridColor}},
                                y: {ticks: {color: chartTextColor}, grid: {display: false}}
                            }
                        }
                    };
                },
                Array.isArray(solicitudesPipeline.values) && solicitudesPipeline.values.some(function (value) { return Number(value || 0) > 0; })
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
                                    borderColor: '#0891b2',
                                    backgroundColor: 'rgba(8,145,178,.12)',
                                    borderWidth: 2,
                                    tension: 0.25
                                },
                                {
                                    label: 'Informados',
                                    data: Array.isArray(serie.informados) ? serie.informados : [],
                                    borderColor: '#059669',
                                    backgroundColor: 'rgba(5,150,105,.12)',
                                    borderWidth: 2,
                                    tension: 0.25
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {position: 'top'}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0, color: chartTextColor}, grid: {color: chartGridColor}}, x: {ticks: {color: chartTextColor}, grid: {display: false}}}
                        }
                    };
                },
                Array.isArray(serie.labels) && serie.labels.length > 0
            );

            drawChart(
                'chartImagenesTrazabilidad',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(trazabilidad.labels) ? trazabilidad.labels : [],
                            datasets: [{
                                label: 'Casos',
                                data: Array.isArray(trazabilidad.values) ? trazabilidad.values : [],
                                backgroundColor: ['#0891b2', '#059669', '#d97706', '#dc2626'],
                                borderRadius: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0, color: chartTextColor}, grid: {color: chartGridColor}}, x: {ticks: {color: chartTextColor}, grid: {display: false}}}
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
                            datasets: [{label: 'Estudios', data: Array.isArray(mix.values) ? mix.values : [], backgroundColor: '#0891b2', borderRadius: 8}]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {x: {beginAtZero: true, ticks: {precision: 0, color: chartTextColor}, grid: {color: chartGridColor}}, y: {ticks: {color: chartTextColor}, grid: {display: false}}}
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
                            datasets: [{label: 'Solicitudes', data: Array.isArray(topDoctoresSolicitantes.values) ? topDoctoresSolicitantes.values : [], backgroundColor: '#d97706', borderRadius: 8}]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {x: {beginAtZero: true, ticks: {precision: 0, color: chartTextColor}, grid: {color: chartGridColor}}, y: {ticks: {color: chartTextColor}, grid: {display: false}}}
                        }
                    };
                },
                Array.isArray(topDoctoresSolicitantes.labels) && topDoctoresSolicitantes.labels.length > 0
            );

            drawChart(
                'chartImagenesTopExamenesSolicitados',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(topExamenesSolicitados.labels) ? topExamenesSolicitados.labels : [],
                            datasets: [{label: 'Solicitudes', data: Array.isArray(topExamenesSolicitados.values) ? topExamenesSolicitados.values : [], backgroundColor: '#0f766e', borderRadius: 8}]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {x: {beginAtZero: true, ticks: {precision: 0, color: chartTextColor}, grid: {color: chartGridColor}}, y: {ticks: {color: chartTextColor}, grid: {display: false}}}
                        }
                    };
                },
                Array.isArray(topExamenesSolicitados.labels) && topExamenesSolicitados.labels.length > 0
            );

            drawChart(
                'chartImagenesCitasRealizados',
                function () {
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(citasRealizados.labels) ? citasRealizados.labels : [],
                            datasets: [{label: 'Casos', data: Array.isArray(citasRealizados.values) ? citasRealizados.values : [], backgroundColor: ['#0891b2', '#059669', '#dc2626', '#64748b'], borderRadius: 8}]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0, color: chartTextColor}, grid: {color: chartGridColor}}, x: {ticks: {color: chartTextColor}, grid: {display: false}}}
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
                            datasets: [{label: 'Estudios', data: Array.isArray(traficoSemana.values) ? traficoSemana.values : [], backgroundColor: '#0891b2', borderRadius: 8}]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {display: false}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0, color: chartTextColor}, grid: {color: chartGridColor}}, x: {ticks: {color: chartTextColor}, grid: {display: false}}}
                        }
                    };
                },
                Array.isArray(traficoSemana.values) && traficoSemana.values.some(function (value) { return Number(value || 0) > 0; })
            );

            drawChart(
                'chartImagenesBacklogCategoria',
                function () {
                    const datasets = Array.isArray(backlogCategoria.datasets) ? backlogCategoria.datasets : [];
                    const palette = [
                        {backgroundColor: '#0891b2', borderColor: '#0e7490'},
                        {backgroundColor: '#d97706', borderColor: '#b45309'},
                        {backgroundColor: '#64748b', borderColor: '#475569'}
                    ];
                    return {
                        type: 'bar',
                        data: {
                            labels: Array.isArray(backlogCategoria.labels) ? backlogCategoria.labels : [],
                            datasets: datasets.map(function (dataset, index) {
                                const colors = palette[index] || palette[palette.length - 1];
                                return {
                                    label: dataset.label || ('Serie ' + (index + 1)),
                                    data: Array.isArray(dataset.values) ? dataset.values : [],
                                    backgroundColor: colors.backgroundColor,
                                    borderColor: colors.borderColor,
                                    borderWidth: 1,
                                    borderRadius: 8
                                };
                            })
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {legend: {position: 'top'}},
                            scales: {y: {beginAtZero: true, ticks: {precision: 0, color: chartTextColor}, grid: {color: chartGridColor}}, x: {ticks: {color: chartTextColor}, grid: {display: false}}}
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
                            datasets: [{label: 'Monto', data: Array.isArray(rendimientoEconomico.values) ? rendimientoEconomico.values : [], backgroundColor: ['#059669', '#d97706'], borderRadius: 8}]
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
                                y: {beginAtZero: true, ticks: {callback: moneyTick, color: chartTextColor}, grid: {color: chartGridColor}},
                                x: {ticks: {color: chartTextColor}, grid: {display: false}}
                            }
                        }
                    };
                },
                Array.isArray(rendimientoEconomico.values) && rendimientoEconomico.values.some(function (value) { return Number(value || 0) > 0; })
            );

            document.querySelectorAll('#imagenesDashboardTabs [data-bs-toggle="pill"]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const target = document.querySelector(button.getAttribute('data-bs-target') || '');
                    if (!target) return;
                    document.querySelectorAll('#imagenesDashboardTabs .nav-link').forEach(function (item) {
                        item.classList.remove('active');
                        item.setAttribute('aria-selected', 'false');
                    });
                    document.querySelectorAll('.imagenes-diagnostics .tab-pane').forEach(function (pane) {
                        pane.classList.remove('show', 'active');
                    });
                    button.classList.add('active');
                    button.setAttribute('aria-selected', 'true');
                    target.classList.add('show', 'active');
                    window.setTimeout(function () {
                        renderedCharts.forEach(function (chart) {
                            chart.resize();
                        });
                    }, 50);
                });
            });
        });
    })();
</script>

@endsection
