<?php
/** @var array $date_range */
/** @var int $total_cirugias */
/** @var int $cirugias_sin_facturar */
/** @var string $duracion_promedio */
/** @var array $estado_protocolos */
/** @var array $cirugias_por_mes */
/** @var array $top_procedimientos */
/** @var array $top_cirujanos */
/** @var array $top_doctores_solicitudes_realizadas */
/** @var array $cirugias_por_convenio */
/** @var array $programacion_kpis */
/** @var array $reingreso_mismo_diagnostico */
/** @var array $cirugias_sin_solicitud_previa */
/** @var array $kpi_cards */
/** @var string $afiliacion_filter */
/** @var array<int,array{value:string,label:string}> $afiliacion_options */
/** @var string $afiliacion_categoria_filter */
/** @var array<int,array{value:string,label:string}> $afiliacion_categoria_options */

$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/apexcharts-bundle/dist/apexcharts.js',
    'js/pages/cirugias_dashboard.js?v=' . time(),
]);

$dashboardPayload = json_encode([
    'cirugiasPorMes' => $cirugias_por_mes,
    'topProcedimientos' => $top_procedimientos,
    'topCirujanos' => $top_cirujanos,
    'topDoctoresSolicitudesRealizadas' => $top_doctores_solicitudes_realizadas,
    'estadoProtocolos' => $estado_protocolos,
    'cirugiasPorConvenio' => $cirugias_por_convenio,
], JSON_UNESCAPED_UNICODE);

$inlineScripts = array_merge($inlineScripts ?? [], [
    "window.cirugiasDashboardData = {$dashboardPayload};",
    "if (window.initCirugiasDashboard) { window.initCirugiasDashboard(window.cirugiasDashboardData); }",
]);

$exportQuery = http_build_query([
    'start_date' => (string)($date_range['start'] ?? ''),
    'end_date' => (string)($date_range['end'] ?? ''),
    'afiliacion' => (string)($afiliacion_filter ?? ''),
    'afiliacion_categoria' => (string)($afiliacion_categoria_filter ?? ''),
]);

if (!function_exists('cirugiasDashboardInlineFormat')) {
    function cirugiasDashboardInlineFormat(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped) ?? $escaped;

        return preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', static function (array $matches): string {
            $label = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $href = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');

            return '<a href="' . $href . '" target="_blank" rel="noopener">' . $label . '</a>';
        }, $escaped) ?? $escaped;
    }
}

if (!function_exists('cirugiasDashboardRenderMarkdown')) {
    function cirugiasDashboardRenderMarkdown(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $markdown);
        $html = [];
        $inUl = false;
        $inOl = false;

        $closeLists = static function () use (&$inUl, &$inOl, &$html): void {
            if ($inUl) {
                $html[] = '</ul>';
                $inUl = false;
            }
            if ($inOl) {
                $html[] = '</ol>';
                $inOl = false;
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $closeLists();
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/', $trimmed, $m) === 1) {
                $closeLists();
                $level = strlen($m[1]);
                $html[] = '<h' . $level . '>' . cirugiasDashboardInlineFormat($m[2]) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $m) === 1) {
                if ($inOl) {
                    $html[] = '</ol>';
                    $inOl = false;
                }
                if (!$inUl) {
                    $html[] = '<ul>';
                    $inUl = true;
                }
                $html[] = '<li>' . cirugiasDashboardInlineFormat($m[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m) === 1) {
                if ($inUl) {
                    $html[] = '</ul>';
                    $inUl = false;
                }
                if (!$inOl) {
                    $html[] = '<ol>';
                    $inOl = true;
                }
                $html[] = '<li>' . cirugiasDashboardInlineFormat($m[1]) . '</li>';
                continue;
            }

            $closeLists();
            $html[] = '<p>' . cirugiasDashboardInlineFormat($trimmed) . '</p>';
        }

        $closeLists();

        return implode("\n", $html);
    }
}

$helpMdPath = __DIR__ . '/../docs/dashboard_quirurgico_guia.md';
$helpMarkdown = is_file($helpMdPath)
    ? (string)file_get_contents($helpMdPath)
    : "# Guía no disponible\n\nNo se encontró el archivo de ayuda del dashboard.";
$helpHtml = cirugiasDashboardRenderMarkdown($helpMarkdown);
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Dashboard quirúrgico</h3>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                <li class="breadcrumb-item"><a href="/cirugias">Cirugías</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
            </ol>
        </div>
    </div>
</div>

<section class="content">
    <div class="row g-3">
        <div class="col-12">
            <div class="box mb-3">
                <div class="box-header with-border d-flex justify-content-between align-items-center">
                    <h4 class="box-title mb-0">Filtros</h4>
                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                        <a href="/cirugias/dashboard/export/pdf<?= $exportQuery !== '' ? ('?' . htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8')) : '' ?>"
                           class="btn btn-outline-danger btn-sm">
                            <i class="mdi mdi-file-pdf-box me-1"></i> Descargar PDF
                        </a>
                        <a href="/cirugias/dashboard/export/excel<?= $exportQuery !== '' ? ('?' . htmlspecialchars($exportQuery, ENT_QUOTES, 'UTF-8')) : '' ?>"
                           class="btn btn-outline-success btn-sm">
                            <i class="mdi mdi-file-excel-box me-1"></i> Descargar Excel
                        </a>
                        <button type="button" class="btn btn-outline-info btn-sm"
                                data-bs-toggle="modal" data-bs-target="#cirugiasDashboardHelpModal">
                            <i class="mdi mdi-help-circle-outline me-1"></i> Guía de uso
                        </button>
                        <a href="/cirugias" class="btn btn-outline-primary btn-sm">
                            <i class="mdi mdi-format-list-bulleted me-1"></i> Ir a cirugías
                        </a>
                    </div>
                </div>
                <div class="box-body">
                    <form method="get" class="row g-2 align-items-end">
                        <div class="col-sm-6 col-md-3">
                            <label for="start_date" class="form-label">Desde</label>
                            <input type="date" id="start_date" name="start_date" class="form-control"
                                   value="<?= htmlspecialchars($date_range['start'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="end_date" class="form-label">Hasta</label>
                            <input type="date" id="end_date" name="end_date" class="form-control"
                                   value="<?= htmlspecialchars($date_range['end'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="afiliacion" class="form-label">Afiliación</label>
                            <select id="afiliacion" name="afiliacion" class="form-select">
                                <?php foreach (($afiliacion_options ?? []) as $option): ?>
                                    <?php $optionValue = (string)($option['value'] ?? ''); ?>
                                    <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($afiliacion_filter ?? '')) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="afiliacion_categoria" class="form-label">Categoría afiliación</label>
                            <select id="afiliacion_categoria" name="afiliacion_categoria" class="form-select">
                                <?php foreach (($afiliacion_categoria_options ?? []) as $option): ?>
                                    <?php $optionValue = (string)($option['value'] ?? ''); ?>
                                    <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($afiliacion_categoria_filter ?? '')) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="mdi mdi-filter-variant"></i> Aplicar filtros
                            </button>
                            <a href="/cirugias/dashboard" class="btn btn-outline-secondary btn-sm">
                                <i class="mdi mdi-close-circle-outline"></i> Limpiar
                            </a>
                        </div>
                    </form>
                    <p class="text-muted fs-12 mb-0 mt-10">
                        Mostrando datos del periodo: <strong><?= htmlspecialchars($date_range['label'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="cirugias-kpi-grid mb-3">
                <?php foreach (($kpi_cards ?? []) as $card): ?>
                    <article class="cirugias-kpi-card">
                        <p class="cirugias-kpi-label mb-1"><?= htmlspecialchars((string)($card['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <h4 class="cirugias-kpi-value mb-1"><?= htmlspecialchars((string)($card['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h4>
                        <p class="cirugias-kpi-hint mb-0"><?= htmlspecialchars((string)($card['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Cirugías por mes</h4>
                </div>
                <div class="box-body">
                    <div id="cirugias-por-mes" style="height: 300px;"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Estado de protocolos</h4>
                </div>
                <div class="box-body">
                    <div id="estado-protocolos" style="height: 300px;"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Top procedimientos</h4>
                </div>
                <div class="box-body">
                    <div id="top-procedimientos" style="height: 320px;"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Top cirujanos (realizadas)</h4>
                </div>
                <div class="box-body">
                    <div id="top-cirujanos" style="height: 320px;"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Top doctores solicitantes (realizadas)</h4>
                </div>
                <div class="box-body">
                    <div id="top-doctores-realizadas" style="height: 320px;"></div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Cirugías por convenio</h4>
                </div>
                <div class="box-body">
                    <div id="cirugias-por-convenio" style="height: 320px;"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="cirugiasDashboardHelpModal" tabindex="-1" aria-labelledby="cirugiasDashboardHelpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="cirugiasDashboardHelpModalLabel">Guía de uso del Dashboard Quirúrgico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="cirugias-help-markdown">
                    <?= $helpHtml ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style>
    .cirugias-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 0.75rem;
    }
    .cirugias-kpi-card {
        border: 1px solid #e7ebf0;
        border-radius: 0.75rem;
        padding: 0.75rem 0.85rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .cirugias-kpi-label {
        font-size: 0.78rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .cirugias-kpi-value {
        font-size: 1.4rem;
        font-weight: 700;
        color: #0b4f9c;
    }
    .cirugias-kpi-hint {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .cirugias-help-markdown h1,
    .cirugias-help-markdown h2,
    .cirugias-help-markdown h3 {
        margin-top: 0.85rem;
        margin-bottom: 0.45rem;
        color: #0b4f9c;
        font-weight: 700;
    }
    .cirugias-help-markdown p,
    .cirugias-help-markdown li {
        font-size: 0.92rem;
        line-height: 1.5;
        color: #334155;
    }
    .cirugias-help-markdown ul,
    .cirugias-help-markdown ol {
        padding-left: 1.1rem;
        margin-bottom: 0.6rem;
    }
    .cirugias-help-markdown code {
        background-color: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 0.25rem;
        padding: 0.05rem 0.3rem;
        font-size: 0.82rem;
        color: #1e293b;
    }
</style>
