@extends('layouts.medforge')

@push('scripts')
    @if (\App\Modules\Shared\Support\MedforgeAssets::hasViteBuild())
        @vite('resources/js/v2/imagenes-realizadas.js')
    @else
        <script src="/assets/vendor_components/datatable/datatables.min.js"></script>
        <script src="/js/pages/shared/datatables-language-es.js"></script>
    @endif
@endpush

@section('content')
    <?php
    /** @var array<int, array<string,mixed>> $imagenesRealizadas */
    /** @var array<string, string> $filters */
    /** @var array<int, array{value:string,label:string}> $afiliacionOptions */
    /** @var array<int, array{value:string,label:string}> $afiliacionCategoriaOptions */
    /** @var array<int, array{value:string,label:string}> $seguroOptions */

    if (!isset($filters) || !is_array($filters)) {
        $filters = [
            'fecha_inicio' => '',
            'fecha_fin' => '',
            'afiliacion_categoria' => '',
            'afiliacion' => '',
            'seguro' => '',
            'sede' => '',
            'tipo_examen' => '',
            'paciente' => '',
            'estado_agenda' => '',
            'hc_number' => '',
            'form_id' => '',
        ];
    }

    $afiliacionOptions = isset($afiliacionOptions) && is_array($afiliacionOptions) ? $afiliacionOptions : [['value' => '', 'label' => 'Todas las empresas']];
    $afiliacionCategoriaOptions = isset($afiliacionCategoriaOptions) && is_array($afiliacionCategoriaOptions)
        ? $afiliacionCategoriaOptions
        : [
            ['value' => '', 'label' => 'Todos los tipos'],
            ['value' => 'publico', 'label' => 'Pública'],
            ['value' => 'privado', 'label' => 'Privada'],
            ['value' => 'particular', 'label' => 'Particular'],
            ['value' => 'fundacional', 'label' => 'Fundacional'],
            ['value' => 'otros', 'label' => 'Otros'],
        ];
    $seguroOptions = isset($seguroOptions) && is_array($seguroOptions) ? $seguroOptions : [['value' => '', 'label' => 'Todos los seguros']];

    $sedeOptions = [
        ['value' => '', 'label' => 'Todas las sedes'],
        ['value' => 'MATRIZ', 'label' => 'MATRIZ'],
        ['value' => 'CEIBOS', 'label' => 'CEIBOS'],
    ];

    $parseProcedimiento = static function (string $raw): array {
        $texto = trim($raw);
        $ojo = '';

        if ($texto !== '' && preg_match('/\s-\s(AMBOS OJOS|IZQUIERDO|DERECHO|OD|OI|AO)\s*$/i', $texto, $match)) {
            $ojo = strtoupper(trim($match[1]));
            $texto = trim(substr($texto, 0, -strlen($match[0])));
        }

        if ($texto !== '') {
            $partes = preg_split('/\s-\s/', $texto) ?: [];
            if (isset($partes[0]) && strcasecmp(trim($partes[0]), 'IMAGENES') === 0) {
                array_shift($partes);
            }
            if (isset($partes[0]) && preg_match('/^IMA[-_]/i', trim($partes[0]))) {
                array_shift($partes);
            }
            $texto = trim(implode(' - ', array_map('trim', $partes)));
        }

        $ojoMap = [
            'OD' => 'Derecho',
            'OI' => 'Izquierdo',
            'AO' => 'Ambos ojos',
            'DERECHO' => 'Derecho',
            'IZQUIERDO' => 'Izquierdo',
            'AMBOS OJOS' => 'Ambos ojos',
        ];

        return [
            'texto' => $texto,
            'ojo' => $ojoMap[$ojo] ?? $ojo,
        ];
    };

    $badgePalette = [
        'badge-primary-light',
        'badge-success-light',
        'badge-info-light',
        'badge-warning-light',
        'badge-danger-light',
        'badge-secondary-light',
    ];

    $resolveBadgeClass = static function (string $value) use ($badgePalette): string {
        $normalized = trim(mb_strtolower($value, 'UTF-8'));
        if ($normalized === '') {
            return 'badge-secondary-light';
        }
        $hash = 0;
        $len = mb_strlen($normalized, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($normalized, $i, 1, 'UTF-8');
            $hash = ($hash * 31 + ord($char)) % 997;
        }
        $index = $hash % count($badgePalette);
        return $badgePalette[$index];
    };

    $estadoOpciones = [];
    foreach ($imagenesRealizadas as $row) {
        $estado = trim((string)($row['estado_agenda'] ?? ''));
        if ($estado !== '' && !in_array($estado, $estadoOpciones, true)) {
            $estadoOpciones[] = $estado;
        }
    }
    sort($estadoOpciones);
    ?>

    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Imágenes · Procedimientos proyectados</h3>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Imágenes</li>
                </ol>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="/v2/imagenes/dashboard" class="btn btn-outline-info btn-sm">
                    <i class="mdi mdi-chart-line"></i> Dashboard
                </a>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnPrintTable">
                    <i class="mdi mdi-printer"></i> Imprimir lista
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnHelpOpen" title="Cómo funciona esta vista">
                    <i class="mdi mdi-help-circle-outline"></i> Ayuda
                </button>
            </div>
        </div>
    </div>

    <?php
    // KPI counts
    $kpiPorInformar = 0; $kpiSinNas = 0; $kpiInformados = 0;
    foreach ($imagenesRealizadas as $_r) {
        $_inf = !empty($_r['informe_id']);
        $_nasHas = (int)($_r['nas_has_files'] ?? 0) === 1 || (int)($_r['nas_files_count'] ?? 0) > 0;
        $_nasSt = trim((string)($_r['nas_scan_status'] ?? ''));
        $_nasStatus = $_nasHas ? 'con-archivos' : (in_array($_nasSt, ['empty', 'missing_dir', 'no_mapping'], true) ? 'sin-archivos' : 'pendiente');
        if ($_inf) { $kpiInformados++; }
        elseif ($_nasStatus === 'sin-archivos') { $kpiSinNas++; }
        else { $kpiPorInformar++; }
    }
    ?>

    <div class="er-kpi-row mb-3">
        <button class="er-kpi er-kpi-c-primary" data-kpi-action="por-informar" type="button">
            <div class="er-kpi-top"><span class="er-kpi-ico"><i class="mdi mdi-file-document-edit-outline"></i></span></div>
            <div class="er-kpi-val"><?= (int)$kpiPorInformar ?></div>
            <div class="er-kpi-lbl">Por informar</div>
        </button>
        <button class="er-kpi er-kpi-c-danger" data-kpi-action="bandeja" type="button">
            <div class="er-kpi-top"><span class="er-kpi-ico"><i class="mdi mdi-bell-alert-outline"></i></span></div>
            <div class="er-kpi-val" id="kpiBandejaVal">0</div>
            <div class="er-kpi-lbl">Bandeja prioritaria</div>
        </button>
        <button class="er-kpi er-kpi-c-warning" data-kpi-action="sin-nas" type="button">
            <div class="er-kpi-top"><span class="er-kpi-ico"><i class="mdi mdi-folder-alert-outline"></i></span></div>
            <div class="er-kpi-val"><?= (int)$kpiSinNas ?></div>
            <div class="er-kpi-lbl">Sin archivos</div>
        </button>
        <button class="er-kpi er-kpi-c-success" data-kpi-action="informados" type="button">
            <div class="er-kpi-top"><span class="er-kpi-ico"><i class="mdi mdi-file-check-outline"></i></span></div>
            <div class="er-kpi-val"><?= (int)$kpiInformados ?></div>
            <div class="er-kpi-lbl">Informados</div>
        </button>
    </div>

    <section class="content">
        <div class="box">
            <div class="box-header with-border d-flex justify-content-between align-items-center">
                <h4 class="box-title mb-0">Listado por fecha, afiliación y paciente</h4>
            </div>
            <div class="box-body">
                <?php
                $totalInformados = 0;
                $totalNoInformados = 0;
                $totalSinNas = 0;
                foreach ($imagenesRealizadas as $row) {
                    $informado = !empty($row['informe_id']);
                    $nasHasFiles = (int)($row['nas_has_files'] ?? 0) === 1 || (int)($row['nas_files_count'] ?? 0) > 0;
                    $nasScanStatus = trim((string)($row['nas_scan_status'] ?? ''));
                    $nasStatus = $nasHasFiles
                        ? 'con-archivos'
                        : (in_array($nasScanStatus, ['empty', 'missing_dir', 'no_mapping'], true) ? 'sin-archivos' : 'pendiente');
                    if ($informado) {
                        $totalInformados++;
                    } elseif ($nasStatus === 'sin-archivos') {
                        $totalSinNas++;
                    } else {
                        $totalNoInformados++;
                    }
                }
                ?>
                <ul class="nav nav-tabs mb-0" id="tabInformes">
                    <li class="nav-item">
                        <button class="nav-link active" type="button" data-tab="no-informados" title="Exámenes pendientes de informe con archivos disponibles en el NAS">
                            <i class="mdi mdi-file-document-edit-outline me-1"></i>Por informar
                            <span class="badge badge-secondary-light ms-1"><?= (int)$totalNoInformados ?></span>
                            <span class="er-tab-help" data-tab-help="no-informados" title="¿Para qué sirve esta pestaña?"><i class="mdi mdi-information-outline"></i></span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link er-tab-bandeja" type="button" data-tab="bandeja" title="Exámenes marcados como urgentes o que requieren informe pronto">
                            <i class="mdi mdi-bell-alert-outline me-1"></i>Bandeja prioritaria
                            <span class="badge badge-danger-light ms-1" id="tabBandejaCount">0</span>
                            <span class="er-tab-help" data-tab-help="bandeja" title="¿Para qué sirve esta pestaña?"><i class="mdi mdi-information-outline"></i></span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" type="button" data-tab="informados" title="Exámenes que ya tienen informe generado">
                            <i class="mdi mdi-file-check-outline me-1"></i>Informados
                            <span class="badge badge-success-light ms-1"><?= (int)$totalInformados ?></span>
                            <span class="er-tab-help" data-tab-help="informados" title="¿Para qué sirve esta pestaña?"><i class="mdi mdi-information-outline"></i></span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" type="button" data-tab="sin-nas" title="Procedimientos sin archivos de imágenes en el NAS">
                            <i class="mdi mdi-folder-alert-outline me-1"></i>Sin archivos
                            <span class="badge badge-warning-light ms-1"><?= (int)$totalSinNas ?></span>
                            <span class="er-tab-help" data-tab-help="sin-nas" title="¿Para qué sirve esta pestaña?"><i class="mdi mdi-information-outline"></i></span>
                        </button>
                    </li>
                </ul>
                <div id="erTabDesc" class="er-tab-desc d-none" aria-live="polite"></div>
                <div id="erBulkBar" class="er-bulk-bar d-none" role="region" aria-label="Acciones masivas">
                    <i class="mdi mdi-checkbox-multiple-marked-outline" style="color:var(--primary);font-size:18px"></i>
                    <span class="er-bulk-count"></span>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-outline-danger btn-sm er-bulk-bandeja-btn">
                            <i class="mdi mdi-bell-plus-outline"></i> Enviar a bandeja prioritaria
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm er-bulk-clear-btn">
                            <i class="mdi mdi-close"></i> Quitar selección
                        </button>
                    </div>
                </div>
                <form class="row g-2 align-items-end mb-3" method="get" id="filtrosImagenes">
                    <input type="hidden" name="hc_number" value="<?= htmlspecialchars($filters['hc_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="form_id" value="<?= htmlspecialchars($filters['form_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
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
                        <label class="form-label">Tipo de afiliación</label>
                        <select class="form-select" name="afiliacion_categoria" id="filtroTipoAfiliacion">
                            <?php foreach ($afiliacionCategoriaOptions as $option): ?>
                                <?php $optionValue = trim((string)($option['value'] ?? '')); ?>
                                <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($filters['afiliacion_categoria'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)($option['label'] ?? $optionValue), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">Empresa de seguro</label>
                        <select class="form-select" name="afiliacion" id="filtroAfiliacion">
                            <?php foreach ($afiliacionOptions as $option): ?>
                                <?php $optionValue = trim((string)($option['value'] ?? '')); ?>
                                <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($filters['afiliacion'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)($option['label'] ?? $optionValue), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">Seguro / plan</label>
                        <select class="form-select" name="seguro" id="filtroSeguro">
                            <?php foreach ($seguroOptions as $option): ?>
                                <?php $optionValue = trim((string)($option['value'] ?? '')); ?>
                                <option value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($filters['seguro'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)($option['label'] ?? $optionValue), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">Sede</label>
                        <select class="form-select" name="sede">
                            <?php foreach ($sedeOptions as $option): ?>
                                <?php $optionValue = (string)($option['value'] ?? ''); ?>
                                <option
                                    value="<?= htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($optionValue === (string)($filters['sede'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado_agenda">
                            <option value="">Todos</option>
                            <?php foreach ($estadoOpciones as $estado): ?>
                            <option
                                value="<?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['estado_agenda'] ?? '') === $estado ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">Tipo examen</label>
                        <select class="form-select" name="tipo_examen" id="filtroTipoExamen"
                                data-current="<?= htmlspecialchars($filters['tipo_examen'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-md-2">
                        <label class="form-label">Paciente/Cédula</label>
                        <input type="text" class="form-control" name="paciente" placeholder="Nombre o ID"
                               value="<?= htmlspecialchars($filters['paciente'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-filter-variant"></i> Aplicar filtros
                        </button>
                        <a href="/v2/imagenes/examenes-realizados" class="btn btn-outline-secondary btn-sm">
                            <i class="mdi mdi-close-circle-outline"></i> Limpiar
                        </a>
                    </div>
                </form>
                <?php if (($filters['hc_number'] ?? '') !== '' || ($filters['form_id'] ?? '') !== ''): ?>
                    <div class="alert alert-info py-10 px-15 mb-3">
                        Contexto directo
                        <?php if (($filters['hc_number'] ?? '') !== ''): ?>
                            | HC: <strong><?= htmlspecialchars((string) $filters['hc_number'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php endif; ?>
                        <?php if (($filters['form_id'] ?? '') !== ''): ?>
                            | Formulario: <strong><?= htmlspecialchars((string) $filters['form_id'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table id="tablaImagenesRealizadas" class="table table-lg invoice-archive">
                        <thead>
                        <tr>
                            <th class="text-center" style="width:42px">
                                <input type="checkbox" class="form-check-input" id="selectAllInformados"
                                       aria-label="Seleccionar todos">
                            </th>
                            <th>Fecha</th>
                            <th>Afiliación</th>
                            <th>Paciente</th>
                            <th>Cédula</th>
                            <th>Imagen</th>
                            <th>Procedimiento</th>
                            <th>Ojo</th>
                            <th>Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($imagenesRealizadas as $row): ?>
                            <?php
                            $fechaRaw = (string)($row['fecha_examen'] ?? '');
                            $fechaUi = $fechaRaw !== '' ? date('d-m-Y', strtotime($fechaRaw)) : '—';
                            $imagenRuta = trim((string)($row['imagen_ruta'] ?? ''));
                            $imagenNombre = trim((string)($row['imagen_nombre'] ?? ''));
                            $tipoExamenRaw = trim((string)($row['tipo_examen'] ?? $row['examen_nombre'] ?? ''));
                            $tipoParsed = $parseProcedimiento($tipoExamenRaw);
                            $tipoExamen = $tipoParsed['texto'];
                            $ojoExamen = $tipoParsed['ojo'];
                            $nasHasFiles = (int)($row['nas_has_files'] ?? 0) === 1 || (int)($row['nas_files_count'] ?? 0) > 0;
                            $nasFilesCount = (int)($row['nas_files_count'] ?? 0);
                            $nasScanStatus = trim((string)($row['nas_scan_status'] ?? ''));
                            $nasStatus = $nasHasFiles
                                ? 'con-archivos'
                                : (in_array($nasScanStatus, ['empty', 'missing_dir', 'no_mapping'], true) ? 'sin-archivos' : 'pendiente');
                            ?>
                            <?php $informado = !empty($row['informe_id']); ?>
                        <tr data-id="<?= (int)($row['id'] ?? 0) ?>"
                            data-form-id="<?= htmlspecialchars((string)($row['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-hc-number="<?= htmlspecialchars((string)($row['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-fecha-examen="<?= htmlspecialchars((string)($row['fecha_examen'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-estado-agenda="<?= htmlspecialchars((string)($row['estado_agenda'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-afiliacion="<?= htmlspecialchars((string)($row['afiliacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-paciente="<?= htmlspecialchars((string)($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-examen="<?= htmlspecialchars($tipoExamen, ENT_QUOTES, 'UTF-8') ?>"
                            data-ojo="<?= htmlspecialchars($ojoExamen, ENT_QUOTES, 'UTF-8') ?>"
                            data-tipo-raw="<?= htmlspecialchars($tipoExamenRaw, ENT_QUOTES, 'UTF-8') ?>"
                            data-nas-status="<?= htmlspecialchars($nasStatus, ENT_QUOTES, 'UTF-8') ?>"
                            data-pendiente-informar="<?= (!$informado && $nasHasFiles) ? '1' : '0' ?>"
                            data-informado="<?= $informado ? '1' : '0' ?>"
                            data-prioridad=""
                            data-fecha-limite=""
                            data-responsable=""
                            data-motivo="">
                            <td class="text-center select-cell">
                                <input type="checkbox" class="form-check-input row-select"
                                       value="<?= htmlspecialchars((string)($row['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $informado ? '' : 'disabled' ?>>
                            </td>
                            <td><?= htmlspecialchars($fechaUi, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                    <?php
                                    $afiliacionText = (string)($row['afiliacion'] ?? 'Sin afiliación');
                                    $afiliacionBadge = $resolveBadgeClass($afiliacionText);
                                    ?>
                                <span class="badge <?= htmlspecialchars($afiliacionBadge, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($afiliacionText, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td class="er-cell-paciente">
                                <span class="er-paciente-nombre"><?= htmlspecialchars((string)($row['full_name'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="er-paciente-meta">CC <?= htmlspecialchars((string)($row['cedula'] ?? '—'), ENT_QUOTES, 'UTF-8') ?> · HC <?= htmlspecialchars((string)($row['hc_number'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-info btn-view-nas">
                                    <i class="mdi mdi-folder-image"></i> Ver imágenes
                                </button>
                                <div class="small mt-5">
                                        <?php if ($nasHasFiles): ?>
                                    <span class="badge badge-success-light">Con archivos (<?= $nasFilesCount ?>)</span>
                                    <?php elseif ($nasStatus === 'sin-archivos'): ?>
                                    <span class="badge badge-warning-light">Sin archivos</span>
                                    <?php else: ?>
                                    <span class="badge badge-secondary-light">Archivos pendientes</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                    <?php
                                    $examenBadge = $resolveBadgeClass($tipoExamen);
                                    ?>
                                <span
                                    class="badge <?= htmlspecialchars($examenBadge, ENT_QUOTES, 'UTF-8') ?> tipo-examen-label">
                                    <?= htmlspecialchars($tipoExamen !== '' ? $tipoExamen : 'No definido', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($ojoExamen !== '' ? $ojoExamen : '—', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="d-flex gap-1 align-items-center">
                                    <span class="er-prio-pill d-none"></span>
                                    <button type="button"
                                            class="btn btn-sm btn-success btn-print-item" <?= $informado ? '' : 'disabled' ?>>
                                        <i class="mdi mdi-printer"></i>
                                    </button>
                                    <?php if (!$informado): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-marcar-urgente"
                                            title="Marcar como urgente / pronto" aria-label="Marcar urgente">
                                        <i class="mdi mdi-bell-plus-outline"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <style>
            /* ---- KPI row ---- */
            .er-kpi-row { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
            .er-kpi {
                background:#fff; border:1px solid #e9ecef; border-radius:12px;
                padding:14px 16px; cursor:pointer; text-align:left; position:relative; overflow:hidden;
                transition:all .15s ease; border-left:4px solid var(--er-kpi-c,#5156be);
            }
            .er-kpi:hover { border-color:var(--er-kpi-c,#5156be); box-shadow:0 4px 12px rgba(0,0,0,.08); transform:translateY(-1px); }
            .er-kpi.active { box-shadow:0 0 0 3px color-mix(in srgb, var(--er-kpi-c,#5156be) 18%, transparent); }
            .er-kpi-top { display:flex; align-items:center; justify-content:space-between; }
            .er-kpi-ico { width:30px; height:30px; border-radius:8px; display:grid; place-items:center; font-size:18px; background:color-mix(in srgb,var(--er-kpi-c,#5156be) 14%,#fff); color:var(--er-kpi-c,#5156be); }
            .er-kpi-val { font-size:28px; font-weight:700; line-height:1; margin-top:8px; letter-spacing:-.02em; }
            .er-kpi-lbl { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:#6c757d; font-weight:600; margin-top:4px; }
            .er-kpi-c-primary { --er-kpi-c:#5156be; }
            .er-kpi-c-danger  { --er-kpi-c:#e84c5b; }
            .er-kpi-c-warning { --er-kpi-c:#d59623; }
            .er-kpi-c-success { --er-kpi-c:#1f9d7a; }
            @media(max-width:768px){ .er-kpi-row { grid-template-columns:repeat(2,1fr); } }

            /* ---- Tab description strip ---- */
            .er-tab-desc {
                display:flex; align-items:center; gap:10px; padding:10px 15px;
                font-size:13px; color:#495057; border-bottom:1px solid #e9ecef;
                background:var(--er-desc-bg,#f0f0ff);
            }
            .er-tab-desc .mdi { font-size:17px; color:var(--er-desc-c,#5156be); flex:none; }
            .er-tab-desc b { color:#212529; }
            .er-tab-desc .er-td-more { margin-left:auto; font-size:12px; font-weight:600; color:var(--er-desc-c,#5156be); background:none; border:0; cursor:pointer; white-space:nowrap; padding:0; }
            .er-tab-desc .er-td-more:hover { text-decoration:underline; }

            /* ---- Bandeja tab color ---- */
            .er-tab-bandeja.active { color:#e84c5b; }
            .er-tab-bandeja.active::after { background:#e84c5b; }
            #tabInformes .nav-link .er-tab-help {
                display:inline-flex; align-items:center; margin-left:4px;
                width:16px; height:16px; border-radius:50%; color:#adb5bd; font-size:14px;
                vertical-align:middle; cursor:pointer; transition:color .15s;
            }
            #tabInformes .nav-link .er-tab-help:hover { color:#5156be; }

            /* ---- Bulk bar ---- */
            .er-bulk-bar {
                display:flex; align-items:center; gap:10px; padding:9px 15px;
                background:#f0f0ff; border-bottom:1px solid #e9ecef; font-size:13px;
                animation:erSlideDown .15s ease;
            }
            .er-bulk-count { font-weight:600; color:#5156be; }
            @keyframes erSlideDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }

            /* ---- Patient cell ---- */
            .er-cell-paciente { min-width:160px; }
            .er-paciente-nombre { display:block; font-weight:600; color:#212529; }
            .er-paciente-meta { display:block; font-size:11.5px; color:#6c757d; font-family:monospace; margin-top:1px; }

            /* ---- Priority pills ---- */
            .er-prio-pill { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; }
            .er-prio-urgente { background:#e84c5b; color:#fff; }
            .er-prio-pronto { background:#fff0d1; color:#8a5d0a; border:1px solid #f0cd84; }
            .er-prio-vencido { background:#4a0d18; color:#ffd9e0; }
            tr.er-row-urgente { background:linear-gradient(90deg,#fdf1f3,transparent 60%); }
            tr.er-row-urgente:hover { background:linear-gradient(90deg,#fbe7eb,transparent 60%); }

            /* ---- Marcar urgente modal ---- */
            #modalMarcarUrgente .er-seg-row { display:flex; gap:8px; margin-top:4px; }
            #modalMarcarUrgente .er-seg-opt {
                flex:1; border:1.5px solid #dee2e6; border-radius:10px; padding:12px; cursor:pointer;
                background:#fff; text-align:left; transition:all .15s; display:flex; gap:10px; align-items:flex-start;
            }
            #modalMarcarUrgente .er-seg-opt .mdi { font-size:20px; color:#adb5bd; margin-top:1px; }
            #modalMarcarUrgente .er-seg-opt b { display:block; font-size:13.5px; }
            #modalMarcarUrgente .er-seg-opt small { color:#6c757d; font-size:11.5px; }
            #modalMarcarUrgente .er-seg-opt.sel-urgente { border-color:#e84c5b; background:#fdecef; }
            #modalMarcarUrgente .er-seg-opt.sel-urgente .mdi { color:#e84c5b; }
            #modalMarcarUrgente .er-seg-opt.sel-pronto { border-color:#d59623; background:#fff6e3; }
            #modalMarcarUrgente .er-seg-opt.sel-pronto .mdi { color:#b9760f; }
            .er-quick-tag { display:inline-flex; align-items:center; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:600; background:#fff; border:1px solid #dee2e6; color:#495057; cursor:pointer; transition:all .1s; }
            .er-quick-tag:hover { border-color:#5156be; color:#5156be; }

            /* ---- Help modal flow cards ---- */
            .er-flow-card { display:flex; gap:13px; padding:14px; border-radius:12px; border:1px solid #e9ecef; margin-bottom:10px; background:#fff; }
            .er-flow-ico { width:38px; height:38px; border-radius:10px; display:grid; place-items:center; font-size:20px; flex:none; }
            .er-flow-ico-primary { background:#f0f0ff; color:#5156be; }
            .er-flow-ico-danger  { background:#fdecef; color:#e84c5b; }
            .er-flow-ico-success { background:#e3f5ee; color:#1f9d7a; }
            .er-flow-ico-warning { background:#fff0d1; color:#8a5d0a; }
            .er-flow-card h5 { font-size:14px; margin:0 0 3px; font-weight:600; }
            .er-flow-card p { font-size:12.5px; color:#6c757d; margin:0; line-height:1.5; }

            /* ---- Toast ---- */
            .er-toast-wrap { position:fixed; bottom:24px; left:50%; transform:translateX(-50%); z-index:2000; pointer-events:none; }
            .er-toast {
                display:flex; align-items:center; gap:9px; background:#212529; color:#fff;
                padding:11px 18px; border-radius:10px; font-size:13.5px; font-weight:500;
                box-shadow:0 8px 24px rgba(0,0,0,.22); animation:erToastIn .2s ease;
                white-space:nowrap;
            }
            .er-toast .mdi { font-size:18px; }
            .er-toast.ok .mdi { color:#1f9d7a; }
            .er-toast.warn .mdi { color:#f0cd84; }
            @keyframes erToastIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:none} }

            .table-group-row td {
                background-color: #f8f9fa;
            }

            #tablaImagenesRealizadas th:first-child,
            #tablaImagenesRealizadas td:first-child {
                width: 44px;
                min-width: 44px;
            }

            #tablaImagenesRealizadas .row-select,
            #tablaImagenesRealizadas .form-check-input {
                appearance: auto;
                -webkit-appearance: checkbox;
                width: 18px;
                height: 18px;
                opacity: 1;
                visibility: visible;
                display: inline-block;
                position: static;
                margin: 0;
                cursor: pointer;
            }

            #tablaImagenesRealizadas .select-cell {
                cursor: pointer;
                vertical-align: middle;
            }

            .nas-slider-stage {
                height: clamp(500px, 58vh, 760px);
                min-height: 500px;
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 0.5rem;
                overflow: hidden;
            }

            .nas-slider-stage:focus {
                outline: 0;
                box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2);
                border-color: #0d6efd;
            }

            .nas-slider-preview-wrap {
                width: 100%;
                height: 100%;
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }

            .nas-slider-preview-img {
                width: auto;
                height: auto;
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
                display: block;
                background: #fff;
            }

            .nas-slider-preview-pdf {
                width: 100%;
                height: 100%;
                min-height: 0;
                border: 0;
                background: #fff;
            }

            .nas-slider-thumbs {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.5rem;
                max-height: 170px;
                overflow: auto;
            }

            .nas-nav-btn {
                min-width: 108px;
                font-weight: 600;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.35rem;
            }

            .nas-nav-btn .nav-arrow {
                font-size: 1rem;
                line-height: 1;
            }

            .nas-nav-btn:disabled {
                opacity: 0.5;
            }

            .nas-thumb-item {
                text-align: left;
                border: 1px solid #dee2e6;
                background: #fff;
                border-radius: 0.4rem;
                padding: 0.4rem 0.5rem;
                font-size: 0.8rem;
                line-height: 1.2;
            }

            .nas-thumb-item.active {
                border-color: #0d6efd;
                box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.15);
            }

            .nas-file-type-badge {
                font-size: 0.7rem;
            }

            @media (max-width: 1199.98px) {
                .nas-slider-stage {
                    height: clamp(280px, 44vh, 560px);
                    min-height: 280px;
                }

                .nas-slider-preview-pdf {
                    min-height: 0;
                }
            }
        </style>
    </section>

    <div class="modal fade" id="modalInformeImagen" tabindex="-1" aria-hidden="true"
         aria-labelledby="modalInformeImagenLabel">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalInformeImagenLabel">Informar examen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body" style="min-height: 70vh;">
                    <div class="row g-3 h-100">
                        <div class="col-12 col-xl-6 order-2 order-xl-1">
                            <div id="informeLoader" class="d-none text-center text-muted small py-2">
                                <span class="spinner-border spinner-border-sm me-2" role="status"
                                      aria-hidden="true"></span>
                                Cargando informe...
                            </div>
                            <div id="informeTemplateContainer"></div>
                        </div>
                        <div class="col-12 col-xl-6 order-1 order-xl-2">
                            <div class="border rounded p-3 h-100 d-flex flex-column">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                    <h6 class="mb-0">Archivos del examen</h6>
                                    <span class="text-muted small" id="informeImagenesStatus"></span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary nas-nav-btn"
                                            id="btnNasPrev" aria-label="Archivo anterior">
                                        <span class="nav-arrow" aria-hidden="true">&larr;</span>
                                        <span>Anterior</span>
                                    </button>
                                    <span class="small text-muted" id="informeNasCounter">0/0</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary nas-nav-btn"
                                            id="btnNasNext" aria-label="Archivo siguiente">
                                        <span>Siguiente</span>
                                        <span class="nav-arrow" aria-hidden="true">&rarr;</span>
                                    </button>
                                </div>
                                <div id="informeImagenesContainer"
                                     class="nas-slider-stage flex-grow-1 d-flex align-items-center justify-content-center"
                                     tabindex="0"
                                     role="region"
                                     aria-label="Visor de archivos del examen"></div>
                                <div class="d-flex justify-content-end mt-2">
                                    <a href="#" target="_blank" rel="noopener"
                                       class="btn btn-sm btn-outline-primary disabled" id="btnNasOpenCurrent">
                                        <i class="mdi mdi-open-in-new"></i> Abrir archivo
                                    </a>
                                </div>
                                <div id="informeNasThumbs" class="nas-slider-thumbs mt-3"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <span class="text-muted small me-auto" id="informeEstado"></span>
                    <button type="button" class="btn btn-outline-dark" id="btnAutoInforme" aria-pressed="false">Auto: OFF</button>
                    <button type="button" class="btn btn-outline-primary d-none" id="btnAutollenarMicroespecular">Autollenar desde imagen</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarInforme">Guardar informe</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ====== Modal: Marcar urgente / bandeja prioritaria ====== --}}
    <div class="modal fade" id="modalMarcarUrgente" tabindex="-1" aria-hidden="true" aria-labelledby="modalMarcarUrgenteLabel">
        <div class="modal-dialog modal-dialog-centered" style="max-width:520px">
            <div class="modal-content">
                <div class="modal-header" style="border-bottom:1px solid #e9ecef">
                    <div class="d-flex align-items-center gap-2">
                        <span style="width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:20px;background:#fdecef;color:#e84c5b;flex:none">
                            <i class="mdi mdi-bell-alert-outline"></i>
                        </span>
                        <div>
                            <h5 class="modal-title mb-0" id="modalMarcarUrgenteLabel">Marcar para informe prioritario</h5>
                            <div class="text-muted small" id="modalUrgenteSubtitle"></div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    {{-- Patient strip --}}
                    <div id="modalUrgentePtStrip" class="p-3 mb-3 rounded-3" style="background:#f8f9fa;border:1px solid #e9ecef;display:none">
                        <div class="d-flex flex-wrap gap-3">
                            <div><div class="text-uppercase" style="font-size:10.5px;font-weight:600;color:#6c757d;letter-spacing:.04em">Paciente</div><div style="font-size:13.5px;font-weight:600" id="urgentePaciente"></div></div>
                            <div><div class="text-uppercase" style="font-size:10.5px;font-weight:600;color:#6c757d;letter-spacing:.04em">Examen</div><div style="font-size:13.5px;font-weight:600" id="urgenteExamen"></div></div>
                            <div><div class="text-uppercase" style="font-size:10.5px;font-weight:600;color:#6c757d;letter-spacing:.04em">Ojo</div><div style="font-size:13.5px;font-weight:600" id="urgenteOjo"></div></div>
                        </div>
                    </div>
                    {{-- Priority segmented --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="font-size:12.5px">Prioridad</label>
                        <div class="er-seg-row">
                            <button type="button" class="er-seg-opt sel-urgente" id="segUrgente" data-prio="urgente">
                                <i class="mdi mdi-fire"></i>
                                <span><b>Urgente</b><small>Informar hoy mismo</small></span>
                            </button>
                            <button type="button" class="er-seg-opt" id="segPronto" data-prio="pronto">
                                <i class="mdi mdi-clock-fast"></i>
                                <span><b>Pronto</b><small>En los próximos días</small></span>
                            </button>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:12.5px">Informar antes de</label>
                            <input type="date" class="form-control" id="urgenteFechaLimite">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="font-size:12.5px">Médico responsable</label>
                            <input type="text" class="form-control" id="urgenteResponsable" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold" style="font-size:12.5px">
                            Motivo / nota <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="urgenteMotivo" rows="3"
                                  placeholder="¿Por qué requiere informe prioritario?"></textarea>
                        <div class="d-flex flex-wrap gap-1 mt-2" id="urgenteQuickTags">
                            <button type="button" class="er-quick-tag" data-motivo="Cirugía programada en 48 h, falta informe para protocolo">Cirugía en 48 h</button>
                            <button type="button" class="er-quick-tag" data-motivo="Paciente foráneo, viaja mañana — necesita informe hoy">Paciente foráneo</button>
                            <button type="button" class="er-quick-tag" data-motivo="Sospecha de glaucoma avanzado, requiere lectura prioritaria">Sospecha glaucoma</button>
                            <button type="button" class="er-quick-tag" data-motivo="Pre-quirúrgico de catarata, junta médica próximamente">Pre-quirúrgico</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa;border-top:1px solid #e9ecef">
                    <span class="text-muted small me-auto" id="urgenteFootNote"></span>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmarUrgente">
                        <i class="mdi mdi-bell-plus-outline me-1"></i>Enviar a bandeja
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ====== Modal: Ayuda / Cómo funciona ====== --}}
    <div class="modal fade" id="modalHelp" tabindex="-1" aria-hidden="true" aria-labelledby="modalHelpLabel">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:640px">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <span style="width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:20px;background:#f0f0ff;color:#5156be;flex:none">
                            <i class="mdi mdi-help-circle-outline"></i>
                        </span>
                        <div>
                            <h5 class="modal-title mb-0" id="modalHelpLabel">Cómo funciona Exámenes realizados</h5>
                            <div class="text-muted small">El recorrido de una imagen, de la captura al informe</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Cada examen de imagen recorre cuatro estados. Las pestañas son esos estados — úsalas como bandejas de trabajo:</p>
                    <div class="er-flow-card">
                        <span class="er-flow-ico er-flow-ico-primary"><i class="mdi mdi-file-document-edit-outline"></i></span>
                        <div><h5>1. Por informar</h5><p>Exámenes con archivos disponibles en el NAS que aún no tienen informe generado. Aquí está el trabajo principal del técnico/médico de imágenes. Abre el modal de «Informar» para cargar la plantilla y guardar el informe.</p></div>
                    </div>
                    <div class="er-flow-card">
                        <span class="er-flow-ico er-flow-ico-danger"><i class="mdi mdi-bell-alert-outline"></i></span>
                        <div><h5>2. Bandeja prioritaria</h5><p>Exámenes no informados que alguien marcó como <b>Urgente</b> (informar hoy) o <b>Pronto</b> (en los próximos días). Se ordenan por prioridad y fecha límite. Los casos con plazo vencido se resaltan en rojo.</p></div>
                    </div>
                    <div class="er-flow-card">
                        <span class="er-flow-ico er-flow-ico-success"><i class="mdi mdi-file-check-outline"></i></span>
                        <div><h5>3. Informados</h5><p>Exámenes con informe firmado y guardado. Desde aquí puedes imprimirlos o descargarlos en paquete. El paciente recibe un aviso automático por WhatsApp al guardar.</p></div>
                    </div>
                    <div class="er-flow-card">
                        <span class="er-flow-ico er-flow-ico-warning"><i class="mdi mdi-folder-alert-outline"></i></span>
                        <div><h5>4. Sin archivos</h5><p>Procedimientos proyectados sin archivos de imágenes en el NAS. Pueden ser exámenes que no se realizaron, o que el equipo aún no los transfirió. Usa «Reclamar» para notificar al área técnica.</p></div>
                    </div>
                    <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:12.5px">
                        <i class="mdi mdi-bell-plus-outline me-1"></i>
                        <b>Bandeja prioritaria:</b> desde «Por informar», pulsa el botón <i class="mdi mdi-bell-plus-outline"></i> en una fila, o selecciona varias y usa «Enviar a bandeja prioritaria». Puedes definir prioridad, fecha límite, médico responsable y motivo.
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8f9fa">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ====== Modal: Ayuda de una pestaña ====== --}}
    <div class="modal fade" id="modalTabHelp" tabindex="-1" aria-hidden="true" aria-labelledby="modalTabHelpLabel">
        <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="er-flow-ico" id="tabHelpIco" style="width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:20px;flex:none"></span>
                        <div>
                            <h5 class="modal-title mb-0" id="modalTabHelpLabel"></h5>
                            <div class="text-muted small" id="tabHelpSub"></div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p id="tabHelpBody" style="line-height:1.6;margin:0"></p>
                </div>
                <div class="modal-footer" style="background:#f8f9fa">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ====== Toast container ====== --}}
    <div class="er-toast-wrap" id="erToastWrap" aria-live="polite" aria-atomic="true"></div>

    <script>
        (function () {
            function postJson(url, body) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || window.csrfToken
                    || '';

                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(csrfToken ? {'X-CSRF-TOKEN': csrfToken} : {})
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body || {})
                }).then(function (response) {
                    if (!response.ok) {
                        return response.json()
                            .catch(function () {
                                return {};
                            })
                            .then(function (data) {
                                throw new Error(data && data.error ? data.error : ('HTTP ' + response.status));
                            });
                    }

                    return response.json();
                });
            }

            window.initImagenesRealizadasPage = function initImagenesRealizadasPage() {
                if (window.__imagenesRealizadasPageInitialized) {
                    return;
                }
                if (typeof window.createImagenesRealizadasTable !== 'function') {
                    return;
                }

                window.__imagenesRealizadasPageInitialized = true;

                function uniqueSorted(values) {
                    return Array.from(new Set(values.filter(Boolean))).sort(function (a, b) {
                        return a.localeCompare(b, 'es', {sensitivity: 'base'});
                    });
                }

                function populateSelect(selectEl, values) {
                    if (!selectEl) return;
                    const current = selectEl.getAttribute('data-current') || '';
                    const currentTrim = current.trim();
                    const options = uniqueSorted(values);

                    options.forEach(function (value) {
                        const opt = document.createElement('option');
                        opt.value = value;
                        opt.textContent = value;
                        selectEl.appendChild(opt);
                    });

                    if (currentTrim !== '') {
                        const exists = Array.from(selectEl.options).some(function (opt) {
                            return opt.value === currentTrim;
                        });
                        if (!exists) {
                            const opt = document.createElement('option');
                            opt.value = currentTrim;
                            opt.textContent = currentTrim;
                            selectEl.appendChild(opt);
                        }
                        selectEl.value = currentTrim;
                    }
                }

                const rows = Array.from(document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-id]'));
                const examenes = rows.map(function (row) {
                    return (row.dataset.examen || '').trim();
                });
                populateSelect(document.getElementById('filtroTipoExamen'), examenes);

                let activeTab = 'no-informados';
                const selectAllInformados = document.getElementById('selectAllInformados');
                let dataTable = null;
                const nasStatusCache = new Map();
                const nasListCache = new Map();
                const nasStatusPending = new Map();
                let nasStatusRefreshTimer = null;
                const nasWarmCasePending = new Set();
                const nasWarmCaseDone = new Set();
                const nasWarmRequestQueue = [];
                const nasWarmRequestSet = new Set();
                let nasWarmRequestInFlight = false;
                const NAS_WARM_CASES_PER_PASS = 4;
                const NAS_STATUS_CASES_PER_PASS = 6;
                const SEQUENTIAL_WARM_NEXT_ROWS = 5;

                function rowIsInformado(row) {
                    return (row && row.dataset && row.dataset.informado === '1');
                }

                function rowIsSinNas(row) {
                    return (row && row.dataset && row.dataset.nasStatus === 'sin-archivos');
                }

                function rowInActiveTab(row) {
                    if (!row || !row.dataset || !row.dataset.id) {
                        return true;
                    }
                    const informado = rowIsInformado(row);
                    const sinNas = rowIsSinNas(row);
                    if (activeTab === 'informados') {
                        return informado;
                    }
                    if (activeTab === 'sin-nas') {
                        return !informado && sinNas;
                    }
                    return !informado && !sinNas;
                }

                function getDataRows() {
                    return Array.from(document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-id]'));
                }

                function getCurrentPageRows() {
                    if (dataTable) {
                        return dataTable
                            .rows({page: 'current', search: 'applied'})
                            .nodes()
                            .toArray()
                            .filter(function (row) {
                                return !!(row && row.dataset && row.dataset.id);
                            });
                    }
                    return getDataRows().filter(function (row) {
                        return row.style.display !== 'none';
                    });
                }

                function getOrderedFilteredRows() {
                    if (dataTable) {
                        return dataTable
                            .rows({search: 'applied', order: 'current'})
                            .nodes()
                            .toArray()
                            .filter(function (row) {
                                return !!(row && row.dataset && row.dataset.id);
                            });
                    }
                    return getDataRows().filter(function (row) {
                        return row.style.display !== 'none';
                    });
                }

                function getVisibleSelectableRows() {
                    return getCurrentPageRows().filter(function (row) {
                        return rowIsInformado(row);
                    });
                }

                function updateSelectAllState() {
                    if (!selectAllInformados) return;
                    const rowsVisible = getVisibleSelectableRows();
                    if (!rowsVisible.length) {
                        selectAllInformados.checked = false;
                        selectAllInformados.indeterminate = false;
                        selectAllInformados.disabled = activeTab !== 'informados';
                        return;
                    }
                    const total = rowsVisible.length;
                    const checked = rowsVisible.filter(function (row) {
                        const checkbox = row.querySelector('.row-select');
                        return checkbox && checkbox.checked;
                    }).length;
                    selectAllInformados.checked = checked > 0 && checked === total;
                    selectAllInformados.indeterminate = checked > 0 && checked < total;
                    selectAllInformados.disabled = activeTab !== 'informados';
                }

                function refreshTabCounts() {
                    const totalRows = rows.filter(function (row) {
                        return !!(row && row.dataset && row.dataset.id);
                    });
                    const totalInformados = totalRows.filter(function (row) {
                        return rowIsInformado(row);
                    }).length;
                    const totalSinNas = totalRows.filter(function (row) {
                        return !rowIsInformado(row) && rowIsSinNas(row);
                    }).length;
                    const totalNoInformados = totalRows.length - totalInformados - totalSinNas;
                    const badgeNo = document.querySelector('#tabInformes [data-tab="no-informados"] .badge');
                    const badgeSi = document.querySelector('#tabInformes [data-tab="informados"] .badge');
                    const badgeSinNas = document.querySelector('#tabInformes [data-tab="sin-nas"] .badge');
                    if (badgeNo) badgeNo.textContent = String(totalNoInformados);
                    if (badgeSi) badgeSi.textContent = String(totalInformados);
                    if (badgeSinNas) badgeSinNas.textContent = String(totalSinNas);
                }

                function applyTabFilter(tab) {
                    activeTab = tab;
                    if (dataTable) {
                        dataTable.draw(false);
                        dataTable.columns.adjust();
                    } else {
                        getDataRows().forEach(function (row) {
                            const visible = rowInActiveTab(row);
                            row.style.display = visible ? '' : 'none';
                        });
                        applyPatientGrouping();
                    }
                    refreshTabCounts();
                    updateSelectAllState();
                    runActiveTabBackgroundTasks();
                }

                document.querySelectorAll('#tabInformes [data-tab]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        document.querySelectorAll('#tabInformes .nav-link').forEach(function (item) {
                            item.classList.remove('active');
                        });
                        btn.classList.add('active');
                        applyTabFilter(btn.getAttribute('data-tab'));
                    });
                });

                if (selectAllInformados) {
                    selectAllInformados.addEventListener('change', function () {
                        const shouldSelect = !!selectAllInformados.checked;
                        getVisibleSelectableRows().forEach(function (row) {
                            const checkbox = row.querySelector('.row-select');
                            if (!checkbox || checkbox.disabled) return;
                            checkbox.checked = shouldSelect;
                        });
                        updateSelectAllState();
                    });
                }

                const tbody = document.querySelector('#tablaImagenesRealizadas tbody');
                if (tbody) {
                    tbody.addEventListener('click', function (event) {
                        const cell = event.target.closest && event.target.closest('.select-cell');
                        if (!cell || !tbody.contains(cell)) return;
                        const checkbox = cell.querySelector('.row-select');
                        if (!checkbox || checkbox.disabled) return;
                        if (event.target && event.target.classList && event.target.classList.contains('row-select')) {
                            event.stopPropagation();
                            setTimeout(updateSelectAllState, 0);
                            return;
                        }
                        event.preventDefault();
                        event.stopPropagation();
                        checkbox.checked = !checkbox.checked;
                        updateSelectAllState();
                    });
                    tbody.addEventListener('change', function (event) {
                        if (event.target && event.target.classList && event.target.classList.contains('row-select')) {
                            updateSelectAllState();
                        }
                    });
                }

                const modalEl = document.getElementById('modalInformeImagen');
                const modalTitleEl = document.getElementById('modalInformeImagenLabel');
                const templateContainer = document.getElementById('informeTemplateContainer');
                const btnGuardarInforme = document.getElementById('btnGuardarInforme');
                const btnAutoInforme = document.getElementById('btnAutoInforme');
                const btnAutollenarMicroespecular = document.getElementById('btnAutollenarMicroespecular');
                const estadoInforme = document.getElementById('informeEstado');
                const imagenesContainer = document.getElementById('informeImagenesContainer');
                const imagenesStatus = document.getElementById('informeImagenesStatus');
                const imagenesThumbs = document.getElementById('informeNasThumbs');
                const nasCounter = document.getElementById('informeNasCounter');
                const btnNasPrev = document.getElementById('btnNasPrev');
                const btnNasNext = document.getElementById('btnNasNext');
                const btnNasOpenCurrent = document.getElementById('btnNasOpenCurrent');
                const informeLoader = document.getElementById('informeLoader');
                const modalInstance = window.bootstrap && modalEl ? new bootstrap.Modal(modalEl) : null;
                let informeContext = null;
                let nasFiles = [];
                let nasCurrentIndex = 0;
                let nasLoadToken = 0;
                const nasPreviewNodes = new Map();
                const templateHtmlCache = new Map();
                let pendingInformeFocusLateralidad = '';
                let autoInformeEnabled = false;
                let pendingAutoAdvanceRow = null;

                function setEstado(texto) {
                    if (estadoInforme) {
                        estadoInforme.textContent = texto || '';
                    }
                }

                function setImagenesStatus(texto) {
                    if (imagenesStatus) {
                        imagenesStatus.textContent = texto || '';
                    }
                }

                function setInformeLoading(loading) {
                    if (!informeLoader) return;
                    informeLoader.classList.toggle('d-none', !loading);
                }

                function updateAutoInformeButton() {
                    if (!btnAutoInforme) return;
                    btnAutoInforme.textContent = autoInformeEnabled ? 'Auto: ON' : 'Auto: OFF';
                    btnAutoInforme.classList.toggle('btn-outline-dark', !autoInformeEnabled);
                    btnAutoInforme.classList.toggle('btn-dark', autoInformeEnabled);
                    btnAutoInforme.setAttribute('aria-pressed', autoInformeEnabled ? 'true' : 'false');
                }

                function normalizarLateralidad(raw) {
                    const value = String(raw || '').trim().toUpperCase();
                    if (!value) return '';
                    if (['OD', 'DERECHO'].includes(value)) return 'OD';
                    if (['OI', 'IZQUIERDO'].includes(value)) return 'OI';
                    if (['AO', 'AMBOS OJOS', 'AMBOS'].includes(value)) return 'AO';
                    return '';
                }

                function inferLateralidad(row, tipoRaw) {
                    const rowEye = row && row.dataset ? normalizarLateralidad(row.dataset.ojo || '') : '';
                    if (rowEye) return rowEye;
                    const text = String(tipoRaw || '').toUpperCase();
                    const match = text.match(/\s-\s(AMBOS OJOS|IZQUIERDO|DERECHO|OD|OI|AO)\s*$/);
                    return match ? normalizarLateralidad(match[1]) : '';
                }

                function lateralidadLabel(code) {
                    if (code === 'OD') return 'Derecho';
                    if (code === 'OI') return 'Izquierdo';
                    if (code === 'AO') return 'Ambos ojos';
                    return '';
                }

                function updateInformeModalTitle(examName, lateralidad) {
                    if (!modalTitleEl) return;
                    const base = 'Informar examen';
                    const parts = [];
                    const exam = String(examName || '').trim();
                    const side = lateralidadLabel(lateralidad);
                    if (exam) parts.push(exam);
                    if (side) parts.push(side);
                    modalTitleEl.textContent = parts.length ? (base + ' · ' + parts.join(' · ')) : base;
                }

                function updateAutofillButtonState() {
                    if (!btnAutollenarMicroespecular) return;
                    const isMicro = !!(informeContext && informeContext.plantilla === 'microespecular');
                    const hasImages = nasFiles.some(function (file) {
                        return isImage(file);
                    });
                    btnAutollenarMicroespecular.classList.toggle('d-none', !isMicro);
                    btnAutollenarMicroespecular.disabled = !isMicro || !hasImages;
                }

                function isPdf(file) {
                    const ext = String(file && file.ext ? file.ext : '').toLowerCase();
                    const type = String(file && file.type ? file.type : '').toLowerCase();
                    return ext === 'pdf' || type === 'application/pdf';
                }

                function isImage(file) {
                    const ext = String(file && file.ext ? file.ext : '').toLowerCase();
                    const type = String(file && file.type ? file.type : '').toLowerCase();
                    return ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'].indexOf(ext) !== -1 || type.indexOf('image/') === 0;
                }

                function getNasFileKey(file) {
                    if (file && file.url) {
                        return String(file.url);
                    }
                    return String(file && file.name ? file.name : '');
                }

                function getNasDisplayUrl(file) {
                    if (file && file._cachedUrl) {
                        return String(file._cachedUrl);
                    }
                    return String(file && file.url ? file.url : '#');
                }

                function releaseNasFileCaches(list) {
                    (Array.isArray(list) ? list : []).forEach(function (file) {
                        if (file && file._cachedUrl) {
                            try {
                                URL.revokeObjectURL(file._cachedUrl);
                            } catch (e) {
                            }
                            file._cachedUrl = '';
                        }
                        if (file) {
                            file._preloadPromise = null;
                        }
                    });
                }

                function extractNasSuffixToken(name) {
                    const raw = String(name || '');
                    const stem = raw.replace(/\.[^.]+$/, '');
                    const idMatch = stem.match(/,\s*\d{6,}$/);
                    if (!idMatch) return '';
                    const left = stem.slice(0, idMatch.index).trim();
                    const tokenMatch = left.match(/([A-Za-z]{1,5})$/);
                    if (!tokenMatch) return '';
                    const token = tokenMatch[1];
                    if (token !== token.toLowerCase()) {
                        return '';
                    }
                    return token;
                }

                function rankNasFileName(name) {
                    const token = extractNasSuffixToken(name);
                    if (!token) return 0;
                    const map = {
                        gy: 1,
                        gc: 1,
                        gcl: 1,
                        hno: 2,
                        onh: 2,
                        rnfl: 2
                    };
                    return map[token] || 3;
                }

                function orderNasFiles(files) {
                    return (Array.isArray(files) ? files.slice() : []).sort(function (a, b) {
                        const rankA = rankNasFileName(a && a.name ? a.name : '');
                        const rankB = rankNasFileName(b && b.name ? b.name : '');
                        if (rankA !== rankB) {
                            return rankA - rankB;
                        }
                        const mtimeA = Number(a && a.mtime ? a.mtime : 0);
                        const mtimeB = Number(b && b.mtime ? b.mtime : 0);
                        if (mtimeA !== mtimeB) {
                            return mtimeB - mtimeA;
                        }
                        return String(a && a.name ? a.name : '').localeCompare(String(b && b.name ? b.name : ''), 'es', {sensitivity: 'base'});
                    });
                }

                function preloadNasFile(file, token) {
                    if (!file || !file.url) {
                        return Promise.resolve();
                    }
                    if (file._cachedUrl) {
                        return Promise.resolve();
                    }
                    if (file._preloadPromise) {
                        return file._preloadPromise;
                    }
                    const isPdfFile = isPdf(file);
                    file._preloadPromise = fetch(file.url, {credentials: 'same-origin'})
                        .then(function (r) {
                            if (!r.ok) {
                                return r.text().then(function (text) {
                                    throw new Error(String(text || '').trim() || 'No se pudo abrir el archivo.');
                                });
                            }
                            return r.blob();
                        })
                        .then(function (blob) {
                            if (!blob) return;
                            if (isPdfFile) {
                                return blob.arrayBuffer().then(function (buffer) {
                                    const bytes = new Uint8Array(buffer.slice(0, 5));
                                    const header = String.fromCharCode.apply(null, Array.from(bytes));
                                    if (header !== '%PDF-') {
                                        file._preloadFailed = true;
                                        return;
                                    }
                                    file._preloadFailed = false;
                                    const objectUrl = URL.createObjectURL(new Blob([buffer], {type: 'application/pdf'}));
                                    if (token !== nasLoadToken) {
                                        try {
                                            URL.revokeObjectURL(objectUrl);
                                        } catch (e) {
                                        }
                                        return;
                                    }
                                    file._cachedUrl = objectUrl;
                                });
                            }
                            if (blob.type && !String(blob.type).startsWith('image/')) {
                                file._preloadFailed = true;
                                return;
                            }
                            file._preloadFailed = false;
                            const objectUrl = URL.createObjectURL(blob);
                            if (token !== nasLoadToken) {
                                try {
                                    URL.revokeObjectURL(objectUrl);
                                } catch (e) {
                                }
                                return;
                            }
                            file._cachedUrl = objectUrl;
                        })
                        .catch(function () {
                            const error = arguments.length ? arguments[0] : null;
                            file._preloadFailed = true;
                            file._preloadError = error && error.message
                                ? String(error.message)
                                : 'No se pudo abrir el archivo.';
                        })
                        .finally(function () {
                            file._preloadPromise = null;
                        });
                    return file._preloadPromise;
                }

                function preloadNasFiles(files, token) {
                    const queue = Array.isArray(files) ? files.slice() : [];
                    if (!queue.length) return;
                    const workers = Math.min(6, queue.length);
                    for (let i = 0; i < workers; i++) {
                        const run = function () {
                            if (!queue.length) return Promise.resolve();
                            const file = queue.shift();
                            return preloadNasFile(file, token).finally(run);
                        };
                        run();
                    }
                }

                function clearNasPreviewStage() {
                    nasPreviewNodes.clear();
                    if (imagenesContainer) {
                        imagenesContainer.innerHTML = '';
                    }
                }

                function buildNasPreviewNode(file) {
                    const current = file || {};
                    const name = current.name ? String(current.name) : 'Archivo';
                    const url = getNasDisplayUrl(current);

                    const wrapper = document.createElement('div');
                    wrapper.className = 'nas-slider-preview-wrap d-none';
                    wrapper.dataset.nasKey = getNasFileKey(current);
                    wrapper.dataset.displayUrl = url;

                    const typeBadge = document.createElement('div');
                    typeBadge.className = 'position-absolute top-0 end-0 m-2';
                    typeBadge.innerHTML = isPdf(current)
                        ? '<span class="badge bg-danger">PDF</span>'
                        : '<span class="badge bg-info text-dark">Imagen</span>';
                    wrapper.appendChild(typeBadge);

                    if (current._preloadFailed) {
                        const fallback = document.createElement('div');
                        fallback.className = 'w-100 h-100 d-flex flex-column align-items-center justify-content-center text-center text-muted p-4';
                        const detail = current._preloadError ? String(current._preloadError) : 'No se pudo abrir el archivo.';
                        fallback.innerHTML = '<i class="mdi mdi-alert-circle-outline fs-1 mb-2 text-warning"></i>'
                            + '<div class="fw-semibold text-dark mb-1">Imagen no disponible</div>'
                            + '<div class="small"></div>';
                        const detailNode = fallback.querySelector('.small');
                        if (detailNode) {
                            detailNode.textContent = detail;
                        }
                        wrapper.appendChild(fallback);
                    } else if (isImage(current)) {
                        const img = document.createElement('img');
                        img.src = url;
                        img.alt = name;
                        img.className = 'nas-slider-preview-img';
                        wrapper.appendChild(img);
                    } else if (isPdf(current)) {
                        const iframe = document.createElement('iframe');
                        iframe.src = url;
                        iframe.className = 'nas-slider-preview-pdf';
                        iframe.title = name;
                        wrapper.appendChild(iframe);
                    } else {
                        const fallback = document.createElement('div');
                        fallback.className = 'w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted';
                        fallback.innerHTML = '<i class="mdi mdi-file-outline fs-1 mb-2"></i><div class="small">Archivo no previsualizable</div>';
                        wrapper.appendChild(fallback);
                    }

                    return wrapper;
                }

                function updateNasControls() {
                    const total = nasFiles.length;
                    if (nasCounter) {
                        nasCounter.textContent = total ? (String(nasCurrentIndex + 1) + '/' + String(total)) : '0/0';
                    }
                    if (btnNasPrev) {
                        btnNasPrev.disabled = total === 0 || nasCurrentIndex <= 0;
                    }
                    if (btnNasNext) {
                        btnNasNext.disabled = total === 0 || nasCurrentIndex >= total - 1;
                    }
                    if (btnNasOpenCurrent) {
                        const current = total ? nasFiles[nasCurrentIndex] : null;
                        if (current && current.url) {
                            btnNasOpenCurrent.classList.remove('disabled');
                            btnNasOpenCurrent.href = current.url;
                        } else {
                            btnNasOpenCurrent.classList.add('disabled');
                            btnNasOpenCurrent.href = '#';
                        }
                    }
                }

                function renderNasThumbs() {
                    if (!imagenesThumbs) return;
                    imagenesThumbs.innerHTML = '';
                    if (!nasFiles.length) return;
                    nasFiles.forEach(function (file, index) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'nas-thumb-item' + (index === nasCurrentIndex ? ' active' : '');

                        const top = document.createElement('div');
                        top.className = 'd-flex align-items-center justify-content-between gap-2 mb-1';
                        const order = document.createElement('strong');
                        order.textContent = '#' + String(index + 1);
                        const badge = document.createElement('span');
                        badge.className = 'badge nas-file-type-badge ' + (isPdf(file) ? 'bg-danger' : 'bg-info');
                        badge.textContent = isPdf(file) ? 'PDF' : 'Imagen';
                        top.appendChild(order);
                        top.appendChild(badge);

                        const name = document.createElement('div');
                        name.className = 'text-truncate';
                        name.title = file && file.name ? file.name : 'Archivo';
                        name.textContent = file && file.name ? file.name : 'Archivo';

                        btn.appendChild(top);
                        btn.appendChild(name);
                        btn.addEventListener('click', function () {
                            nasCurrentIndex = index;
                            renderImagenesNas();
                        });
                        imagenesThumbs.appendChild(btn);
                    });
                }

                function renderImagenesNas(files) {
                    if (!imagenesContainer) return;
                    if (Array.isArray(files)) {
                        releaseNasFileCaches(nasFiles);
                        nasFiles = files.slice();
                        nasCurrentIndex = 0;
                        clearNasPreviewStage();
                    }
                    if (!nasFiles.length) {
                        imagenesContainer.innerHTML = '<div class="text-muted small px-3">No se encontraron archivos asociados al examen.</div>';
                        renderNasThumbs();
                        updateNasControls();
                        updateAutofillButtonState();
                        return;
                    }
                    if (nasCurrentIndex >= nasFiles.length) {
                        nasCurrentIndex = nasFiles.length - 1;
                    }
                    if (nasCurrentIndex < 0) {
                        nasCurrentIndex = 0;
                    }

                    const current = nasFiles[nasCurrentIndex];
                    if ((isPdf(current) || isImage(current)) && !current._cachedUrl && !current._preloadPromise && !current._preloadFailed) {
                        const renderToken = nasLoadToken;
                        preloadNasFile(current, renderToken).finally(function () {
                            if (renderToken !== nasLoadToken) {
                                return;
                            }
                            if (nasFiles[nasCurrentIndex] === current) {
                                const currentKey = getNasFileKey(current);
                                const staleNode = nasPreviewNodes.get(currentKey);
                                if (staleNode && staleNode.parentNode) {
                                    staleNode.parentNode.removeChild(staleNode);
                                }
                                nasPreviewNodes.delete(currentKey);
                                renderImagenesNas();
                            }
                        });
                    }

                    const key = getNasFileKey(current);
                    const existingNode = nasPreviewNodes.get(key);
                    const currentUrl = getNasDisplayUrl(current);
                    if (existingNode && existingNode.dataset.displayUrl !== currentUrl) {
                        if (existingNode.parentNode) {
                            existingNode.parentNode.removeChild(existingNode);
                        }
                        nasPreviewNodes.delete(key);
                    }
                    if (!nasPreviewNodes.has(key)) {
                        nasPreviewNodes.set(key, buildNasPreviewNode(current));
                    }

                    nasPreviewNodes.forEach(function (node) {
                        if (!imagenesContainer.contains(node)) {
                            imagenesContainer.appendChild(node);
                        }
                        node.classList.add('d-none');
                    });

                    const currentNode = nasPreviewNodes.get(key);
                    if (currentNode) {
                        currentNode.classList.remove('d-none');
                    }

                    renderNasThumbs();
                    updateNasControls();
                    updateAutofillButtonState();
                }

                function navigateNasPreview(step) {
                    const delta = Number(step || 0);
                    if (!delta || !nasFiles.length) {
                        return;
                    }
                    const nextIndex = nasCurrentIndex + delta;
                    if (nextIndex < 0 || nextIndex >= nasFiles.length) {
                        return;
                    }
                    nasCurrentIndex = nextIndex;
                    renderImagenesNas();
                }

                function shouldHandleNasNavigationKey(target, allowWhileEditing) {
                    if (!(target instanceof HTMLElement)) {
                        return true;
                    }
                    const tag = target.tagName.toLowerCase();
                    if (!allowWhileEditing && (tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable)) {
                        return false;
                    }
                    return true;
                }

                function focusNasViewer() {
                    if (!imagenesContainer) {
                        return;
                    }
                    if (typeof imagenesContainer.focus === 'function') {
                        imagenesContainer.focus({preventScroll: true});
                    }
                }

                function scheduleNasStatusRefresh() {
                    if (nasStatusRefreshTimer !== null) {
                        clearTimeout(nasStatusRefreshTimer);
                    }
                    nasStatusRefreshTimer = setTimeout(function () {
                        nasStatusRefreshTimer = null;
                        if (dataTable) {
                            dataTable.draw(false);
                        } else {
                            getDataRows().forEach(function (row) {
                                row.style.display = rowInActiveTab(row) ? '' : 'none';
                            });
                            applyPatientGrouping();
                            refreshTabCounts();
                            updateSelectAllState();
                        }
                    }, 120);
                }

                function resolveNasStatus(formId, hcNumber) {
                    const form = String(formId || '').trim();
                    const hc = String(hcNumber || '').trim();
                    if (!form || !hc) {
                        return Promise.resolve('error');
                    }
                    const key = form + '|' + hc;
                    if (nasStatusCache.has(key)) {
                        return Promise.resolve(nasStatusCache.get(key));
                    }
                    if (nasStatusPending.has(key)) {
                        return nasStatusPending.get(key);
                    }
                    const promise = fetch('/v2/imagenes/examenes-realizados/nas/list?hc_number=' + encodeURIComponent(hc) + '&form_id=' + encodeURIComponent(form))
                        .then(function (r) {
                            return r.json();
                        })
                        .then(function (res) {
                            if (!res || !res.success) {
                                return 'error';
                            }
                            const files = orderNasFiles(Array.isArray(res.files) ? res.files : []);
                            nasListCache.set(key, files);
                            return files.length > 0
                                ? 'con-archivos'
                                : 'sin-archivos';
                        })
                        .catch(function () {
                            return 'error';
                        })
                        .then(function (status) {
                            if (status === 'con-archivos' || status === 'sin-archivos') {
                                nasStatusCache.set(key, status);
                            }
                            return status;
                        })
                        .finally(function () {
                            nasStatusPending.delete(key);
                        });
                    nasStatusPending.set(key, promise);
                    return promise;
                }

                function setRowNasStatus(row, status, shouldRefresh) {
                    if (!row || !row.dataset || !row.dataset.id) return;
                    if (status !== 'con-archivos' && status !== 'sin-archivos' && status !== 'pendiente') return;
                    if ((row.dataset.nasStatus || 'pendiente') === status) return;
                    row.dataset.nasStatus = status;
                    const form = String(row.dataset.formId || '').trim();
                    const hc = String(row.dataset.hcNumber || '').trim();
                    if (form && hc && (status === 'con-archivos' || status === 'sin-archivos')) {
                        nasStatusCache.set(form + '|' + hc, status);
                    }
                    if (shouldRefresh !== false) {
                        scheduleNasStatusRefresh();
                    }
                }

                function scanNasStatusForVisibleRows() {
                    const shouldWarmModal = activeTab === 'no-informados';
                    const candidates = getCurrentPageRows()
                        .filter(function (row) {
                            return !rowIsInformado(row);
                        })
                        .filter(function (row) {
                            const status = String(row.dataset.nasStatus || 'pendiente');
                            return status === 'pendiente';
                        })
                        .slice(0, NAS_STATUS_CASES_PER_PASS);
                    if (!candidates.length) {
                        return;
                    }
                    const queue = candidates.slice();
                    const workers = Math.min(3, queue.length);
                    for (let i = 0; i < workers; i++) {
                        const run = function () {
                            if (!queue.length) {
                                return Promise.resolve();
                            }
                            const row = queue.shift();
                            const form = String(row.dataset.formId || '').trim();
                            const hc = String(row.dataset.hcNumber || '').trim();
                            if (!form || !hc) {
                                return Promise.resolve().finally(run);
                            }
                            return resolveNasStatus(form, hc)
                                .then(function (status) {
                                    if (status === 'con-archivos' || status === 'sin-archivos') {
                                        setRowNasStatus(row, status, true);
                                    }
                                    if (shouldWarmModal && status === 'con-archivos') {
                                        warmFirstNasFileForCase(form, hc);
                                    }
                                })
                                .finally(run);
                        };
                        run();
                    }
                }

                function enqueueNasWarmCase(formId, hcNumber) {
                    const form = String(formId || '').trim();
                    const hc = String(hcNumber || '').trim();
                    if (!form || !hc) return;
                    const caseKey = form + '|' + hc;
                    if (nasWarmRequestSet.has(caseKey)) return;
                    nasWarmRequestSet.add(caseKey);
                    nasWarmRequestQueue.push({form_id: form, hc_number: hc, key: caseKey});
                    pumpNasWarmCaseQueue();
                }

                function pumpNasWarmCaseQueue() {
                    if (nasWarmRequestInFlight) return;
                    if (!nasWarmRequestQueue.length) return;

                    const batch = nasWarmRequestQueue.splice(0, 4);
                    const items = batch.map(function (it) {
                        return {form_id: it.form_id, hc_number: it.hc_number};
                    });
                    nasWarmRequestInFlight = true;

                    postJson('/v2/imagenes/examenes-realizados/nas/warm', {items: items})
                        .catch(function () {
                        })
                        .finally(function () {
                            batch.forEach(function (it) {
                                nasWarmRequestSet.delete(it.key);
                            });
                            nasWarmRequestInFlight = false;
                            if (nasWarmRequestQueue.length) {
                                setTimeout(pumpNasWarmCaseQueue, 80);
                            }
                        });
                }

                function warmFirstNasFileForCase(formId, hcNumber) {
                    const form = String(formId || '').trim();
                    const hc = String(hcNumber || '').trim();
                    if (!form || !hc) return;
                    const caseKey = form + '|' + hc;
                    if (nasWarmCaseDone.has(caseKey) || nasWarmCasePending.has(caseKey)) {
                        return;
                    }
                    nasWarmCasePending.add(caseKey);
                    resolveNasStatus(form, hc)
                        .then(function (status) {
                            if (status !== 'con-archivos') return;
                            const files = nasListCache.get(caseKey) || [];
                            if (!files.length) return;
                            const first = files[0];
                            if (!first || !first.name) return;
                            enqueueNasWarmCase(form, hc);
                        })
                        .finally(function () {
                            nasWarmCasePending.delete(caseKey);
                            nasWarmCaseDone.add(caseKey);
                        });
                }

                function getNextAutoInformeRow(currentRow) {
                    if (!currentRow || !currentRow.dataset || !currentRow.dataset.id) {
                        return null;
                    }
                    const orderedRows = getOrderedFilteredRows().filter(function (row) {
                        return !rowIsInformado(row) && !rowIsSinNas(row);
                    });
                    if (!orderedRows.length) {
                        return null;
                    }
                    const currentIndex = orderedRows.findIndex(function (row) {
                        return row === currentRow;
                    });
                    if (currentIndex === -1) {
                        return orderedRows[0] || null;
                    }
                    return orderedRows[currentIndex + 1] || null;
                }

                function warmSequentialNextRows(currentRow, limit) {
                    if (!currentRow) return;
                    const currentRows = getCurrentPageRows().filter(function (row) {
                        return !!(row && row.dataset && row.dataset.id) && rowInActiveTab(row);
                    });
                    const currentIndex = currentRows.indexOf(currentRow);
                    if (currentIndex === -1) {
                        return;
                    }

                    currentRows
                        .slice(currentIndex + 1, currentIndex + 1 + (limit || SEQUENTIAL_WARM_NEXT_ROWS))
                        .forEach(function (row) {
                            const form = String(row.dataset.formId || '').trim();
                            const hc = String(row.dataset.hcNumber || '').trim();
                            if (!form || !hc) {
                                return;
                            }

                            resolveNasStatus(form, hc)
                                .then(function (status) {
                                    if (status === 'con-archivos') {
                                        warmFirstNasFileForCase(form, hc);
                                    } else if (status === 'sin-archivos') {
                                        setRowNasStatus(row, 'sin-archivos', true);
                                    }
                                })
                                .catch(function () {
                                });
                        });
                }

                function warmInformadosVisibleRows() {
                    if (activeTab !== 'informados') return;
                    const visibleRows = getCurrentPageRows().filter(function (row) {
                        return rowIsInformado(row);
                    });
                    if (!visibleRows.length) return;

                    let processed = 0;
                    visibleRows.some(function (row) {
                        if (processed >= NAS_WARM_CASES_PER_PASS) return true;
                        const form = String(row.dataset.formId || '').trim();
                        const hc = String(row.dataset.hcNumber || '').trim();
                        if (!form || !hc) return false;
                        processed += 1;
                        warmFirstNasFileForCase(form, hc);
                        return false;
                    });
                }

                function runActiveTabBackgroundTasks() {
                    if (activeTab === 'informados') {
                        warmInformadosVisibleRows();
                        return;
                    }
                    scanNasStatusForVisibleRows();
                }

                function cargarImagenesNas(formId, hcNumber, row) {
                    if (!imagenesContainer) return;
                    nasLoadToken += 1;
                    const token = nasLoadToken;
                    const cacheKey = String(formId).trim() + '|' + String(hcNumber).trim();
                    releaseNasFileCaches(nasFiles);
                    nasFiles = [];
                    nasCurrentIndex = 0;
                    renderImagenesNas([]);
                    setImagenesStatus('Cargando imágenes...');
                    const cachedFiles = nasListCache.get(cacheKey);
                    if (Array.isArray(cachedFiles)) {
                        renderImagenesNas(cachedFiles);
                        preloadNasFiles(cachedFiles, token);
                        if (row) {
                            setRowNasStatus(row, cachedFiles.length ? 'con-archivos' : 'sin-archivos', false);
                        }
                        setImagenesStatus(cachedFiles.length
                            ? (cachedFiles.length + ' archivo(s) en caché...')
                            : 'Sin archivos en caché');
                    }
                    fetch('/v2/imagenes/examenes-realizados/nas/list?hc_number=' + encodeURIComponent(hcNumber) + '&form_id=' + encodeURIComponent(formId))
                        .then(function (r) {
                            return r.json();
                        })
                        .then(function (res) {
                            if (!res || !res.success) {
                                renderImagenesNas([]);
                                setImagenesStatus(res && res.error ? res.error : 'No se pudieron cargar las imágenes.');
                                if (row) {
                                    setRowNasStatus(row, 'pendiente', false);
                                }
                                return;
                            }
                            const files = orderNasFiles(res.files || []);
                            nasListCache.set(cacheKey, files);
                            renderImagenesNas(files);
                            preloadNasFiles(files, token);
                            if (row) {
                                setRowNasStatus(row, files.length ? 'con-archivos' : 'sin-archivos', true);
                            }
                            setImagenesStatus(files.length
                                ? (files.length + ' archivo(s) encontrado(s)')
                                : (res.message || 'Sin archivos asociados'));
                        })
                        .catch(function () {
                            renderImagenesNas([]);
                            setImagenesStatus('Error al conectar con el repositorio de archivos.');
                            if (row) {
                                setRowNasStatus(row, 'pendiente', false);
                            }
                        });
                }

                if (btnNasPrev) {
                    btnNasPrev.addEventListener('click', function () {
                        navigateNasPreview(-1);
                        focusNasViewer();
                    });
                }
                if (btnNasNext) {
                    btnNasNext.addEventListener('click', function () {
                        navigateNasPreview(1);
                        focusNasViewer();
                    });
                }
                if (imagenesContainer) {
                    imagenesContainer.addEventListener('keydown', function (event) {
                        const key = String(event.key || '').toLowerCase();
                        if (key === 'arrowleft' || key === 'a' || key === 'j') {
                            event.preventDefault();
                            navigateNasPreview(-1);
                        } else if (key === 'arrowright' || key === 'd' || key === 'k') {
                            event.preventDefault();
                            navigateNasPreview(1);
                        }
                    });
                    imagenesContainer.addEventListener('click', function () {
                        focusNasViewer();
                    });
                }

                function cssEscape(value) {
                    if (window.CSS && typeof window.CSS.escape === 'function') {
                        return window.CSS.escape(value);
                    }
                    return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
                }

                function escapeRegex(value) {
                    return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                }

                function updateTargetValue(target, text, checked) {
                    if (!target || !text) return;
                    const current = target.value || '';
                    if (checked) {
                        if (current.trim().length > 0) {
                            target.value = current + ', ' + text;
                        } else {
                            target.value = text;
                        }
                        return;
                    }

                    const regex = new RegExp('(?:^|,\\s*)' + escapeRegex(text) + '(?=,|$)', 'g');
                    const cleaned = current.replace(regex, '').replace(/\\s*,\\s*/g, ', ').replace(/^,\\s*|,\\s*$/g, '').trim();
                    target.value = cleaned;
                }

                function initChecklist(container) {
                    if (!container) return;
                    container.querySelectorAll('.informe-checkbox').forEach(function (checkbox) {
                        checkbox.addEventListener('change', function () {
                            const targetId = checkbox.getAttribute('data-target') || '';
                            const text = checkbox.getAttribute('data-text') || '';
                            if (!targetId || !text) return;
                            const target = container.querySelector('#' + cssEscape(targetId));
                            if (!target) return;
                            updateTargetValue(target, text, checkbox.checked);
                        });
                    });
                }

                function rebuildChecklistTargets(container) {
                    if (!container) return;
                    container.querySelectorAll('.informe-checkbox').forEach(function (checkbox) {
                        if (!checkbox.checked) return;
                        const targetId = checkbox.getAttribute('data-target') || '';
                        const text = checkbox.getAttribute('data-text') || '';
                        if (!targetId || !text) return;
                        const target = container.querySelector('#' + cssEscape(targetId));
                        if (!target) return;
                        if ((target.value || '').indexOf(text) === -1) {
                            updateTargetValue(target, text, true);
                        }
                    });
                }

                function updateCvTextarea(container, eye, checkbox) {
                    if (!container || !eye || !checkbox) return;
                    const textarea = container.querySelector('#input' + eye);
                    if (!textarea) return;

                    const itemId = (checkbox.getAttribute('data-item-id') || '').toLowerCase();
                    if (itemId === 'dln') {
                        const dlnText = 'DENTRO DE LIMITES NORMALES';
                        if (checkbox.checked) {
                            container.querySelectorAll('.informe-checkbox-cv[data-eye="' + eye + '"]').forEach(function (peer) {
                                if (peer === checkbox) return;
                                peer.checked = false;
                            });
                            textarea.value = dlnText;
                        } else if ((textarea.value || '').trim() === dlnText) {
                            textarea.value = '';
                        }
                        return;
                    }

                    if (itemId === 'amaurosis' && checkbox.checked) {
                        container.querySelectorAll('.informe-checkbox-cv[data-eye="' + eye + '"]').forEach(function (peer) {
                            const peerId = (peer.getAttribute('data-item-id') || '').toLowerCase();
                            if (peer === checkbox) return;
                            if (peerId === 'dln') {
                                peer.checked = false;
                            }
                        });
                        textarea.value = 'AMAUROSIS';
                        return;
                    }

                    if (itemId === 'amaurosis' && !checkbox.checked) {
                        if ((textarea.value || '').trim() === 'AMAUROSIS') {
                            textarea.value = '';
                        }
                        return;
                    } else if (checkbox.checked) {
                        container.querySelectorAll('.informe-checkbox-cv[data-eye="' + eye + '"]').forEach(function (peer) {
                            const peerId = (peer.getAttribute('data-item-id') || '').toLowerCase();
                            if (peer === checkbox) return;
                            if (peerId === 'dln') {
                                peer.checked = false;
                            }
                        });
                        if ((textarea.value || '').trim() === 'DENTRO DE LIMITES NORMALES') {
                            textarea.value = '';
                        }
                    }

                    const text = checkbox.getAttribute('data-text') || '';
                    if (!text) return;

                    const currentValues = (textarea.value || '')
                        .split(',')
                        .map(function (item) { return item.trim(); })
                        .filter(Boolean);

                    if (checkbox.checked) {
                        if (currentValues.length === 0) {
                            textarea.value = 'SE APRECIAN PUNTOS DE DISMINUCION DE LA SENSIBILIDAD RETINIANA DE BAJA, MEDIA Y ALTA SIGNIFICANCIA QUE CONFORMAN ESCOTOMA CON PATRON ' + text;
                        } else {
                            currentValues.push(text);
                            textarea.value = currentValues.join(', ');
                        }
                        return;
                    }

                    const index = currentValues.indexOf(text);
                    if (index > -1) {
                        currentValues.splice(index, 1);
                        textarea.value = currentValues.join(', ');
                    }
                }

                function initCvTemplate(container) {
                    if (!container) return;
                    const template = container.querySelector('[data-informe-template="cv"]');
                    if (!template) return;

                    template.querySelectorAll('.informe-checkbox-cv').forEach(function (checkbox) {
                        if (checkbox.dataset.cvBound === '1') {
                            return;
                        }
                        checkbox.addEventListener('change', function () {
                            const eye = checkbox.getAttribute('data-eye') || '';
                            updateCvTextarea(container, eye, checkbox);
                        });
                        checkbox.dataset.cvBound = '1';
                    });
                }

                function rebuildCvTargets(container) {
                    if (!container) return;
                    const template = container.querySelector('[data-informe-template="cv"]');
                    if (!template) return;

                    ['OD', 'OI'].forEach(function (eye) {
                        const textarea = container.querySelector('#input' + eye);
                        if (!textarea || (textarea.value || '').trim() !== '') {
                            return;
                        }
                        template.querySelectorAll('.informe-checkbox-cv[data-eye="' + eye + '"]').forEach(function (checkbox) {
                            if (!checkbox.checked) return;
                            updateCvTextarea(container, eye, checkbox);
                        });
                    });
                }

                function collectPayload(container) {
                    const payload = {};
                    if (!container) return payload;
                    container.querySelectorAll('input, textarea, select').forEach(function (el) {
                        const key = el.id || el.name;
                        if (!key) return;
                        if (el.type === 'checkbox') {
                            payload[key] = !!el.checked;
                            return;
                        }
                        if (el.type === 'radio') {
                            if (el.checked) payload[key] = el.value;
                            return;
                        }
                        payload[key] = el.value;
                    });
                    return payload;
                }

                function applyPayload(container, payload) {
                    if (!container || !payload || typeof payload !== 'object') return;
                    Object.keys(payload).forEach(function (key) {
                        const selector = '#' + cssEscape(key);
                        let el = container.querySelector(selector);
                        if (!el) {
                            el = container.querySelector('[name="' + key + '"]');
                        }
                        if (!el) return;
                        if (el.type === 'checkbox') {
                            el.checked = !!payload[key];
                            return;
                        }
                        if (el.type === 'radio') {
                            if (el.value === payload[key]) {
                                el.checked = true;
                            }
                            return;
                        }
                        el.value = payload[key] ?? '';
                    });
                }

                function focusFirstInformeField(container, lateralidad) {
                    if (!container) return;
                    const lateral = normalizarLateralidad(lateralidad);
                    let firstField = null;

                    if (lateral === 'OD' || lateral === 'OI') {
                        firstField = container.querySelector(
                            'input[id$="' + lateral + '"]:not([type="hidden"]):not([disabled]), ' +
                            'textarea[id$="' + lateral + '"]:not([disabled]), ' +
                            'select[id$="' + lateral + '"]:not([disabled])'
                        );
                    } else if (lateral === 'AO') {
                        firstField = container.querySelector(
                            'input[id$="OD"]:not([type="hidden"]):not([disabled]), textarea[id$="OD"]:not([disabled]), select[id$="OD"]:not([disabled])'
                        ) || container.querySelector(
                            'input[id$="OI"]:not([type="hidden"]):not([disabled]), textarea[id$="OI"]:not([disabled]), select[id$="OI"]:not([disabled])'
                        );
                    }

                    if (!(firstField instanceof HTMLElement)) {
                        firstField = container.querySelector(
                            'input:not([type="hidden"]):not([disabled]), textarea:not([disabled])'
                        ) || container.querySelector('select:not([disabled])');
                    }
                    if (!(firstField instanceof HTMLElement)) {
                        return;
                    }
                    window.requestAnimationFrame(function () {
                        firstField.focus();
                        if (typeof firstField.select === 'function' && firstField.tagName.toLowerCase() !== 'select') {
                            firstField.select();
                        }
                    });
                }

                function requestInformeFocus(lateralidad) {
                    pendingInformeFocusLateralidad = normalizarLateralidad(lateralidad);
                }

                function flushInformeFocus() {
                    if (!templateContainer) {
                        return;
                    }
                    focusFirstInformeField(templateContainer, pendingInformeFocusLateralidad);
                }

                function normalizarPayload(payload, plantilla) {
                    if (!payload || !plantilla) return payload;
                    if (plantilla === 'octm') {
                        const defecto = 'Arquitectura retiniana bien definida, fóvea con depresión central bien delineada, epitelio pigmentario continuo y uniforme, membrana limitante interna es hiporreflectiva y continua, células de Müller están bien alineadas sin signos de edema o tracción.';
                        const ctmod = (payload.inputOD || '').trim();
                        const textOD = (payload.textOD || '').trim();
                        if (ctmod !== '' && textOD === '') {
                            payload.textOD = defecto;
                        }
                        const ctmoi = (payload.inputOI || '').trim();
                        const textOI = (payload.textOI || '').trim();
                        if (ctmoi !== '' && textOI === '') {
                            payload.textOI = defecto;
                        }
                    }
                    if (plantilla === 'piocompensada') {
                        ['OD', 'OI'].forEach(function (eye) {
                            const calculado = computePioCompensadaPayload({
                                paquimetria: payload['paquimetria' + eye],
                                pioMedida: payload['pioMedida' + eye]
                            });
                            payload['compensacion' + eye] = calculado.compensacion;
                            payload['ajuste' + eye] = calculado.ajuste;
                            payload['pioCompensada' + eye] = calculado.pioCompensada;
                        });
                    }
                    return payload;
                }

                function toNumber(value) {
                    const normalized = (value || '').toString().trim().replace(',', '.');
                    if (!normalized) return null;
                    const n = Number(normalized);
                    return Number.isFinite(n) ? n : null;
                }

                function formatPioNumber(value, withSign) {
                    if (value === null || !Number.isFinite(value)) {
                        return '';
                    }
                    const sign = withSign && value > 0 ? '+' : '';
                    return sign + value.toFixed(2);
                }

                function computePioCompensadaPayload(source) {
                    const paquimetria = toNumber(source && source.paquimetria);
                    const pioMedida = toNumber(source && source.pioMedida);
                    if (paquimetria === null) {
                        return {compensacion: '', ajuste: '', pioCompensada: ''};
                    }

                    const delta = -(((paquimetria - 540) / 10) * 0.7);
                    let ajuste = 'Sin ajuste';
                    if (delta > 0.0001) {
                        ajuste = 'Aumentar ' + delta.toFixed(2) + ' mmHg';
                    } else if (delta < -0.0001) {
                        ajuste = 'Disminuir ' + Math.abs(delta).toFixed(2) + ' mmHg';
                    }

                    return {
                        compensacion: formatPioNumber(delta, true),
                        ajuste: ajuste,
                        pioCompensada: pioMedida === null ? '' : formatPioNumber(pioMedida + delta, false)
                    };
                }

                function initPioCompensadaTemplate(container) {
                    if (!container) return;
                    const template = container.querySelector('[data-informe-template="piocompensada"]');
                    if (!template) return;

                    ['OD', 'OI'].forEach(function (eye) {
                        const paquimetriaInput = template.querySelector('#paquimetria' + eye);
                        const pioInput = template.querySelector('#pioMedida' + eye);
                        const compensacionInput = template.querySelector('#compensacion' + eye);
                        const ajusteInput = template.querySelector('#ajuste' + eye);
                        const corregidaInput = template.querySelector('#pioCompensada' + eye);

                        const update = function () {
                            const calculado = computePioCompensadaPayload({
                                paquimetria: paquimetriaInput ? paquimetriaInput.value : '',
                                pioMedida: pioInput ? pioInput.value : ''
                            });
                            if (compensacionInput) compensacionInput.value = calculado.compensacion;
                            if (ajusteInput) ajusteInput.value = calculado.ajuste;
                            if (corregidaInput) corregidaInput.value = calculado.pioCompensada;
                        };

                        if (paquimetriaInput && !paquimetriaInput.dataset.pioCompBound) {
                            paquimetriaInput.addEventListener('input', update);
                            paquimetriaInput.dataset.pioCompBound = '1';
                        }
                        if (pioInput && !pioInput.dataset.pioCompBound) {
                            pioInput.addEventListener('input', update);
                            pioInput.dataset.pioCompBound = '1';
                        }
                        update();
                    });
                }

                function wrapAxis(axis) {
                    let a = Math.round(axis);
                    while (a <= 0) a += 180;
                    while (a > 180) a -= 180;
                    return a;
                }

                function initCorneaTopografiaTemplate(container) {
                    if (!container) return;
                    const template = container.querySelector('[data-informe-template="cornea"]');
                    if (!template) return;

                    function recalcEye(prefix) {
                        const kFlatEl = container.querySelector('#kFlat' + prefix);
                        const axisFlatEl = container.querySelector('#axisFlat' + prefix);
                        const kSteepEl = container.querySelector('#kSteep' + prefix);
                        const axisSteepEl = container.querySelector('#axisSteep' + prefix);
                        const cilindroEl = container.querySelector('#cilindro' + prefix);
                        const kPromedioEl = container.querySelector('#kPromedio' + prefix);
                        if (!kFlatEl || !axisFlatEl || !kSteepEl || !axisSteepEl || !cilindroEl || !kPromedioEl) {
                            return;
                        }

                        const kFlat = toNumber(kFlatEl.value);
                        const axisFlat = toNumber(axisFlatEl.value);
                        const kSteep = toNumber(kSteepEl.value);

                        if (axisFlat !== null) {
                            const steepAxis = wrapAxis(axisFlat + 90);
                            axisSteepEl.value = String(steepAxis);
                        } else {
                            axisSteepEl.value = '';
                        }

                        if (kFlat !== null && kSteep !== null) {
                            cilindroEl.value = Math.abs(kSteep - kFlat).toFixed(2);
                            kPromedioEl.value = ((kSteep + kFlat) / 2).toFixed(2);
                        } else {
                            cilindroEl.value = '';
                            kPromedioEl.value = '';
                        }
                    }

                    ['OD', 'OI'].forEach(function (prefix) {
                        ['kFlat', 'axisFlat', 'kSteep'].forEach(function (field) {
                            const input = container.querySelector('#' + field + prefix);
                            if (!input) return;
                            if (!input.dataset.corneaBound) {
                                input.addEventListener('input', function () {
                                    recalcEye(prefix);
                                });
                                input.dataset.corneaBound = '1';
                            }
                        });
                        recalcEye(prefix);
                    });
                }

                function abrirInformeModal(row) {
                    if (!row) return;
                    const formId = (row.dataset.formId || '').trim();
                    const hcNumber = (row.dataset.hcNumber || '').trim();
                    const tipoRaw = (row.dataset.tipoRaw || '').trim();
                    const examName = (row.dataset.examen || '').trim();
                    const lateralidad = inferLateralidad(row, tipoRaw);

                    if (!formId || !tipoRaw) {
                        alert('Faltan datos del examen para informar.');
                        return;
                    }

                    updateInformeModalTitle(examName, lateralidad);
                    requestInformeFocus(lateralidad);

                    if (modalInstance) {
                        modalInstance.show();
                    }

                    if (templateContainer) {
                        templateContainer.innerHTML = '';
                    }
                    setInformeLoading(true);
                    setEstado('Cargando plantilla...');
                    if (btnGuardarInforme) {
                        btnGuardarInforme.disabled = true;
                    }

                    if (formId && hcNumber) {
                        cargarImagenesNas(formId, hcNumber, row);
                        warmSequentialNextRows(row, SEQUENTIAL_WARM_NEXT_ROWS);
                    }

                    fetch('/v2/imagenes/informes/datos?form_id=' + encodeURIComponent(formId) + '&tipo_examen=' + encodeURIComponent(tipoRaw))
                        .then(function (r) {
                            return r.json();
                        })
                        .then(function (res) {
                            if (!res || !res.success) {
                                alert((res && res.error) ? res.error : 'No se pudo cargar la plantilla.');
                                setEstado('');
                                setInformeLoading(false);
                                return;
                            }

                            informeContext = {
                                formId: formId,
                                hcNumber: hcNumber,
                                tipoRaw: tipoRaw,
                                examName: examName,
                                lateralidad: lateralidad,
                                plantilla: res.plantilla,
                                payload: res.payload || null,
                                row: row
                            };

                            if (templateHtmlCache.has(res.plantilla)) {
                                return templateHtmlCache.get(res.plantilla);
                            }

                            return fetch('/v2/imagenes/informes/plantilla?plantilla=' + encodeURIComponent(res.plantilla))
                                .then(function (response) {
                                    if (!response.ok) {
                                        throw new Error('No se pudo cargar la plantilla');
                                    }
                                    return response.text();
                                })
                                .then(function (html) {
                                    templateHtmlCache.set(res.plantilla, html);
                                    return html;
                                });
                        })
                        .then(function (html) {
                            if (!html || !templateContainer) return;
                            templateContainer.innerHTML = html;
                            initChecklist(templateContainer);
                            initCvTemplate(templateContainer);
                            initCorneaTopografiaTemplate(templateContainer);
                            initPioCompensadaTemplate(templateContainer);
                            if (informeContext && informeContext.payload) {
                                applyPayload(templateContainer, informeContext.payload);
                                initCvTemplate(templateContainer);
                                initCorneaTopografiaTemplate(templateContainer);
                                initPioCompensadaTemplate(templateContainer);
                                rebuildChecklistTargets(templateContainer);
                                rebuildCvTargets(templateContainer);
                                setEstado('Informe existente cargado.');
                            } else {
                                setEstado('Informe nuevo.');
                            }
                            if (btnGuardarInforme) {
                                btnGuardarInforme.disabled = false;
                            }
                            setInformeLoading(false);
                            requestInformeFocus(informeContext ? informeContext.lateralidad : '');
                            flushInformeFocus();
                            updateAutofillButtonState();
                        })
                        .catch(function () {
                            setEstado('');
                            setInformeLoading(false);
                            if (btnGuardarInforme) {
                                btnGuardarInforme.disabled = false;
                            }
                            updateAutofillButtonState();
                            alert('Error de red al cargar la plantilla.');
                        });
                }

                document.querySelectorAll('#tablaImagenesRealizadas tbody tr').forEach(function (row) {
                    row.addEventListener('click', function (event) {
                        if (event.target.closest('button, a, input, select, textarea, label')) {
                            return;
                        }
                        abrirInformeModal(row);
                    });
                });

                document.querySelectorAll('.btn-view-nas').forEach(function (btn) {
                    btn.addEventListener('click', function (event) {
                        event.preventDefault();
                        const row = btn.closest('tr');
                        if (!row) return;
                        abrirInformeModal(row);
                    });
                });

                function guardarInformeActual() {
                    if (!informeContext || !templateContainer || (btnGuardarInforme && btnGuardarInforme.disabled)) {
                        return;
                    }
                    const payload = normalizarPayload(collectPayload(templateContainer), informeContext.plantilla);
                    const currentRow = informeContext && informeContext.row ? informeContext.row : null;
                    pendingAutoAdvanceRow = autoInformeEnabled ? getNextAutoInformeRow(currentRow) : null;
                    setEstado('Guardando informe...');
                    if (btnGuardarInforme) {
                        btnGuardarInforme.disabled = true;
                    }
                    postJson('/v2/imagenes/informes/guardar', {
                        form_id: informeContext.formId,
                        hc_number: informeContext.hcNumber,
                        tipo_examen: informeContext.tipoRaw,
                        plantilla: informeContext.plantilla,
                        payload: payload,
                        firmante_id: payload.firmante_id || ''
                    }).then(function (res) {
                        if (!res || !res.success) {
                            pendingAutoAdvanceRow = null;
                            setEstado('No se pudo guardar el informe.');
                            return;
                        }
                        setEstado('Informe guardado.');
                        if (currentRow) {
                            currentRow.dataset.informado = '1';
                            const checkbox = currentRow.querySelector('.row-select');
                            if (checkbox) {
                                checkbox.disabled = false;
                            }
                            const printBtn = currentRow.querySelector('.btn-print-item');
                            if (printBtn) {
                                printBtn.disabled = false;
                            }
                            applyTabFilter(activeTab);
                        }
                        if (autoInformeEnabled && !pendingAutoAdvanceRow) {
                            setEstado('Informe guardado. No quedan más exámenes no informados en la tabla.');
                        }
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }).catch(function () {
                        pendingAutoAdvanceRow = null;
                        setEstado('Error de red al guardar.');
                    }).finally(function () {
                        if (btnGuardarInforme && modalEl && modalEl.classList.contains('show')) {
                            btnGuardarInforme.disabled = false;
                        }
                    });
                }

                if (btnGuardarInforme) {
                    btnGuardarInforme.addEventListener('click', function () {
                        guardarInformeActual();
                    });
                }

                if (btnAutoInforme) {
                    updateAutoInformeButton();
                    btnAutoInforme.addEventListener('click', function () {
                        autoInformeEnabled = !autoInformeEnabled;
                        updateAutoInformeButton();
                        setEstado(autoInformeEnabled ? 'Modo auto activado.' : 'Modo auto desactivado.');
                    });
                }

                if (templateContainer) {
                    templateContainer.addEventListener('keydown', function (event) {
                        if (event.key !== 'Enter' || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) {
                            return;
                        }
                        const target = event.target;
                        if (!(target instanceof HTMLElement)) {
                            return;
                        }
                        const tag = target.tagName.toLowerCase();
                        if (tag === 'textarea' || target.isContentEditable) {
                            return;
                        }
                        event.preventDefault();
                        guardarInformeActual();
                    });
                }

                if (btnAutollenarMicroespecular) {
                    btnAutollenarMicroespecular.addEventListener('click', function () {
                        if (!informeContext || !templateContainer || informeContext.plantilla !== 'microespecular') {
                            return;
                        }

                        btnAutollenarMicroespecular.disabled = true;
                        setEstado('Leyendo imagen de microscopía especular...');

                        postJson('/v2/imagenes/informes/autofill', {
                            form_id: informeContext.formId,
                            hc_number: informeContext.hcNumber,
                            tipo_examen: informeContext.tipoRaw,
                            plantilla: informeContext.plantilla
                        }).then(function (res) {
                            if (!res || !res.success || !res.payload) {
                                setEstado((res && res.error) ? res.error : 'No se pudo autollenar el informe.');
                                return;
                            }

                            applyPayload(templateContainer, res.payload);

                            const filesUsed = Array.isArray(res.files_used) && res.files_used.length
                                ? ' ' + res.files_used.join(', ')
                                : '';
                            const warnings = Array.isArray(res.warnings) ? res.warnings.filter(Boolean) : [];

                            if (warnings.length) {
                                setEstado('Autollenado parcial.' + filesUsed + ' ' + warnings.join(' '));
                            } else {
                                setEstado('Autollenado completado.' + filesUsed);
                            }
                        }).catch(function () {
                            setEstado('Error de red al intentar autollenar.');
                        }).finally(function () {
                            updateAutofillButtonState();
                        });
                    });
                }

                if (modalEl) {
                    modalEl.addEventListener('keydown', function (event) {
                        if (!modalEl.classList.contains('show')) {
                            return;
                        }
                        if (event.key === 'Escape') {
                            event.preventDefault();
                            if (modalInstance) {
                                modalInstance.hide();
                            }
                        }
                    });
                    modalEl.addEventListener('shown.bs.modal', function () {
                        setTimeout(flushInformeFocus, 30);
                    });
                    modalEl.addEventListener('hidden.bs.modal', function () {
                        const nextRowToOpen = pendingAutoAdvanceRow;
                        pendingAutoAdvanceRow = null;
                        nasLoadToken += 1;
                        if (templateContainer) {
                            templateContainer.innerHTML = '';
                        }
                        releaseNasFileCaches(nasFiles);
                        clearNasPreviewStage();
                        if (imagenesThumbs) {
                            imagenesThumbs.innerHTML = '';
                        }
                        nasFiles = [];
                        nasCurrentIndex = 0;
                        updateNasControls();
                        setImagenesStatus('');
                        setInformeLoading(false);
                        if (btnGuardarInforme) {
                            btnGuardarInforme.disabled = false;
                        }
                        if (btnAutollenarMicroespecular) {
                            btnAutollenarMicroespecular.disabled = false;
                            btnAutollenarMicroespecular.classList.add('d-none');
                        }
                        pendingInformeFocusLateralidad = '';
                        updateInformeModalTitle('', '');
                        informeContext = null;
                        setEstado('');
                        if (autoInformeEnabled && nextRowToOpen && document.body.contains(nextRowToOpen)) {
                            setTimeout(function () {
                                abrirInformeModal(nextRowToOpen);
                            }, 120);
                        }
                    });
                }

                function escapeHtml(value) {
                    return String(value ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                function getPacienteGroup(row) {
                    const hc = (row.dataset.hcNumber || '').trim();
                    const paciente = (row.dataset.paciente || '').trim();
                    if (hc) {
                        return {
                            key: 'HC:' + hc,
                            label: paciente ? (paciente + ' · HC ' + hc) : ('HC ' + hc)
                        };
                    }
                    if (paciente) {
                        return {
                            key: 'PAC:' + paciente,
                            label: paciente
                        };
                    }
                    return {
                        key: 'SIN',
                        label: 'Paciente sin identificar'
                    };
                }

                function getSelectedRowsByGroup(groupKey) {
                    return Array.from(document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-group-key]'))
                        .filter(function (row) {
                            return row.dataset.groupKey === groupKey;
                        })
                        .filter(function (row) {
                            const checkbox = row.querySelector('.row-select');
                            return checkbox && checkbox.checked;
                        });
                }

                function getGroupRows(groupKey) {
                    return Array.from(document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-group-key]'))
                        .filter(function (row) {
                            return row.dataset.groupKey === groupKey;
                        });
                }

                function toggleGroupSelection(groupKey, shouldSelect) {
                    const rows = getGroupRows(groupKey);
                    rows.forEach(function (row) {
                        const checkbox = row.querySelector('.row-select');
                        if (!checkbox || checkbox.disabled) return;
                        checkbox.checked = shouldSelect;
                    });
                }

                function allGroupSelected(groupKey) {
                    const rows = getGroupRows(groupKey);
                    if (!rows.length) return false;
                    return rows.every(function (row) {
                        const checkbox = row.querySelector('.row-select');
                        return checkbox && checkbox.checked;
                    });
                }

                function buildItemsPayload(rows) {
                    return rows.map(function (row) {
                        return {
                            id: parseInt((row.dataset.id || '0').trim(), 10) || null,
                            form_id: (row.dataset.formId || '').trim(),
                            hc_number: (row.dataset.hcNumber || '').trim(),
                            fecha_examen: (row.dataset.fechaExamen || '').trim(),
                            estado_agenda: (row.dataset.estadoAgenda || '').trim(),
                            tipo_examen: (row.dataset.tipoRaw || row.dataset.examen || '').trim()
                        };
                    }).filter(function (item) {
                        return item.form_id && item.hc_number;
                    });
                }

                function setButtonLoading(btn, loading, label) {
                    if (!btn) return;
                    if (!btn.dataset.originalHtml) {
                        btn.dataset.originalHtml = btn.innerHTML;
                    }
                    if (loading) {
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
                            (label || 'Generando...');
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = btn.dataset.originalHtml;
                    }
                }

                function solicitarFechaDocumento() {
                    const today = new Date().toISOString().slice(0, 10);
                    if (window.Swal && typeof window.Swal.fire === 'function') {
                        return window.Swal.fire({
                            title: 'Fecha para 12A y 12B',
                            text: 'Ingresa la fecha que debe imprimirse en los documentos.',
                            input: 'date',
                            inputValue: today,
                            showCancelButton: true,
                            confirmButtonText: 'Descargar',
                            cancelButtonText: 'Cancelar',
                            inputValidator: function (value) {
                                if (!value) {
                                    return 'Debes seleccionar una fecha.';
                                }
                                return null;
                            }
                        }).then(function (result) {
                            return result && result.isConfirmed ? (result.value || '') : '';
                        });
                    }

                    const value = window.prompt('Ingresa la fecha del documento (YYYY-MM-DD):', today);
                    return Promise.resolve((value || '').trim());
                }

                function descargarPaquete(items, triggerBtn, fechaDocumento) {
                    if (!items.length) {
                        alert('Selecciona al menos un examen informado.');
                        return;
                    }
                    let filename = 'paquete.pdf';
                    const payload = {items: items};
                    if (fechaDocumento) {
                        payload.fecha_documento = fechaDocumento;
                    }
                    setButtonLoading(triggerBtn, true);
                    fetch('/v2/reports/imagenes/012b/paquete/seleccion', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(payload)
                    })
                        .then(function (res) {
                            if (!res.ok) {
                                return res.json().then(function (data) {
                                    throw new Error(data && data.error ? data.error : 'No se pudo generar el paquete.');
                                });
                            }
                            const disposition = res.headers.get('Content-Disposition') || '';
                            const match = disposition.match(/filename=\"?([^\";]+)\"?/i);
                            if (match && match[1]) {
                                filename = match[1];
                            }
                            return res.blob();
                        })
                        .then(function (blob) {
                            const url = window.URL.createObjectURL(blob);
                            const link = document.createElement('a');
                            link.href = url;
                            link.download = filename;
                            document.body.appendChild(link);
                            link.click();
                            link.remove();
                            window.URL.revokeObjectURL(url);
                        })
                        .catch(function (err) {
                            alert(err.message || 'No se pudo generar el paquete.');
                        })
                        .finally(function () {
                            setButtonLoading(triggerBtn, false);
                        });
                }

                function applyPatientGrouping() {
                    const tbody = document.querySelector('#tablaImagenesRealizadas tbody');
                    if (!tbody) return;
                    tbody.querySelectorAll('tr.table-group-row').forEach(function (row) {
                        row.remove();
                    });

                    const rows = getCurrentPageRows();
                    if (!rows.length) {
                        return;
                    }

                    const counts = new Map();
                    rows.forEach(function (row) {
                        const group = getPacienteGroup(row);
                        row.dataset.groupKey = group.key;
                        counts.set(group.key, (counts.get(group.key) || 0) + 1);
                    });

                    let lastKey = null;
                    rows.forEach(function (row) {
                        const group = getPacienteGroup(row);
                        if (group.key === lastKey) return;
                        lastKey = group.key;
                        const tr = document.createElement('tr');
                        tr.className = 'table-group-row';
                        const td = document.createElement('td');
                        td.colSpan = row.children.length;
                        const count = counts.get(group.key) || 0;
                        let extra = '<span class="text-muted small ms-2">' + count + ' exámenes en esta página</span>';
                        if (activeTab === 'informados') {
                            extra += '<button type="button" class="btn btn-sm btn-outline-secondary ms-3 btn-seleccionar-grupo" data-group-key="' +
                                escapeHtml(group.key) + '">Seleccionar todo</button>';
                            extra += '<button type="button" class="btn btn-sm btn-outline-primary ms-2 btn-descargar-grupo" data-group-key="' +
                                escapeHtml(group.key) + '">Descargar PDF paciente</button>';
                            extra += '<button type="button" class="btn btn-sm btn-outline-warning ms-2 btn-descargar-grupo-fecha" data-group-key="' +
                                escapeHtml(group.key) + '">Descargar PDF con cambio de fecha</button>';
                        }
                        td.innerHTML = '<strong>' + escapeHtml(group.label) + '</strong>' + extra;
                        tr.appendChild(td);
                        row.parentNode.insertBefore(tr, row);
                    });

                    tbody.querySelectorAll('.btn-seleccionar-grupo').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            const groupKey = btn.getAttribute('data-group-key') || '';
                            const selectAll = !allGroupSelected(groupKey);
                            toggleGroupSelection(groupKey, selectAll);
                            btn.textContent = selectAll ? 'Quitar selección' : 'Seleccionar todo';
                            updateSelectAllState();
                        });
                    });

                    tbody.querySelectorAll('.btn-descargar-grupo').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            const groupKey = btn.getAttribute('data-group-key') || '';
                            const selectedRows = getSelectedRowsByGroup(groupKey);
                            const items = buildItemsPayload(selectedRows);
                            if (!items.length) {
                                alert('Selecciona los exámenes informados que deseas descargar.');
                                return;
                            }
                            descargarPaquete(items, btn, null);
                        });
                    });

                    tbody.querySelectorAll('.btn-descargar-grupo-fecha').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            const groupKey = btn.getAttribute('data-group-key') || '';
                            const selectedRows = getSelectedRowsByGroup(groupKey);
                            const items = buildItemsPayload(selectedRows);
                            if (!items.length) {
                                alert('Selecciona los exámenes informados que deseas descargar.');
                                return;
                            }
                            solicitarFechaDocumento().then(function (fechaDocumento) {
                                if (!fechaDocumento) {
                                    return;
                                }
                                descargarPaquete(items, btn, fechaDocumento);
                            });
                        });
                    });
                }

                dataTable = window.createImagenesRealizadasTable(
                    document.getElementById('tablaImagenesRealizadas'),
                    {
                        pageLength: 25,
                        order: [[1, 'desc']],
                    }
                );
                dataTable.setExternalFilter(rowInActiveTab);
                dataTable.onDraw(function () {
                    applyPatientGrouping();
                    refreshTabCounts();
                    updateSelectAllState();
                    runActiveTabBackgroundTasks();
                });

                applyTabFilter('no-informados');
                applyPatientGrouping();

                function ajustarTabla() {
                    if (dataTable) {
                        dataTable.columns.adjust();
                    }
                }

                document.querySelectorAll('[data-toggle="push-menu"]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        setTimeout(ajustarTabla, 350);
                    });
                });

                window.addEventListener('resize', function () {
                    setTimeout(ajustarTabla, 150);
                });

                document.getElementById('btnPrintTable')?.addEventListener('click', function () {
                    window.print();
                });

                document.querySelectorAll('.btn-print-item').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const row = btn.closest('tr');
                        if (!row) return;
                        if ((row.dataset.informado || '0') !== '1') {
                            alert('El examen todavía no está informado.');
                            return;
                        }
                        const formId = (row.dataset.formId || '').trim();
                        const hcNumber = (row.dataset.hcNumber || '').trim();
                        if (!formId || !hcNumber) return;
                        descargarPaquete([{
                            id: parseInt((row.dataset.id || '0').trim(), 10) || null,
                            form_id: formId,
                            hc_number: hcNumber,
                            fecha_examen: (row.dataset.fechaExamen || '').trim(),
                            estado_agenda: (row.dataset.estadoAgenda || '').trim(),
                            tipo_examen: (row.dataset.tipoRaw || row.dataset.examen || '').trim()
                        }], btn);
                    });
                });

                function reconstruirTipoExamen(raw, nuevoTexto) {
                    const limpio = (nuevoTexto || '').trim();
                    if (!limpio) return raw;
                    if (!raw) return limpio;
                    const partes = raw.split(' - ').map(function (p) {
                        return p.trim();
                    }).filter(Boolean);
                    if (partes.length >= 2 && partes[0].toUpperCase() === 'IMAGENES' && /^IMA[-_]/i.test(partes[1])) {
                        return partes[0] + ' - ' + partes[1] + ' - ' + limpio;
                    }
                    return limpio;
                }

                document.querySelectorAll('.btn-edit').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const row = btn.closest('tr');
                        const id = row ? parseInt(row.getAttribute('data-id') || '0', 10) : 0;
                        if (!id) return;

                        const currentType = btn.getAttribute('data-tipo') || '';
                        const nuevoTipo = window.prompt('Editar tipo de examen:', currentType);
                        if (nuevoTipo === null) return;
                        const rawActual = row ? (row.dataset.tipoRaw || '') : '';
                        const rawFinal = reconstruirTipoExamen(rawActual, nuevoTipo.trim());

                        postJson('/v2/imagenes/examenes-realizados/actualizar', {
                            id: id,
                            tipo_examen: rawFinal
                        }).then(function (res) {
                            if (!res || !res.success) {
                                alert('No se pudo actualizar el examen.');
                                return;
                            }

                            btn.setAttribute('data-tipo', nuevoTipo.trim());
                            if (row) {
                                row.dataset.tipoRaw = rawFinal;
                                row.dataset.examen = nuevoTipo.trim();
                            }
                            const badge = row.querySelector('.tipo-examen-label');
                            if (badge) badge.textContent = nuevoTipo.trim() || 'No definido';
                        }).catch(function () {
                            alert('Error de red al actualizar.');
                        });
                    });
                });

                document.querySelectorAll('.btn-delete').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const row = btn.closest('tr');
                        const id = row ? parseInt(row.getAttribute('data-id') || '0', 10) : 0;
                        if (!id) return;

                        if (!window.confirm('¿Eliminar este procedimiento proyectado? Esta acción no se puede deshacer.')) {
                            return;
                        }

                        postJson('/v2/imagenes/examenes-realizados/eliminar', {id: id})
                            .then(function (res) {
                                if (!res || !res.success) {
                                    alert('No se pudo eliminar el examen.');
                                    return;
                                }
                                row.remove();
                            })
                            .catch(function () {
                                alert('Error de red al eliminar.');
                        });
                    });
                });
            };

            document.addEventListener('DOMContentLoaded', window.initImagenesRealizadasPage);
            window.addEventListener('medforge:imagenes-realizadas-module-ready', window.initImagenesRealizadasPage);
            if (typeof window.createImagenesRealizadasTable === 'function') {
                window.initImagenesRealizadasPage();
            }
        })();
    </script>

    <script>
    // =====================================================================
    // Exámenes realizados — bandeja prioritaria, urgente modal, help, KPIs
    // =====================================================================
    (function () {
        // ---- Storage key ----
        var STORAGE_KEY = 'er_bandeja_v1';
        var today = new Date().toISOString().slice(0, 10);

        // ---- State: { rowId: {prioridad, fecha_limite, responsable, motivo} } ----
        var bandejaState = {};
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (raw) bandejaState = JSON.parse(raw) || {};
        } catch (e) { bandejaState = {}; }

        function saveBandeja() {
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(bandejaState)); } catch(e){}
        }

        // ---- Toast ----
        var toastTimer = null;
        function showToast(msg, icon, tone) {
            var wrap = document.getElementById('erToastWrap');
            if (!wrap) return;
            var el = document.createElement('div');
            el.className = 'er-toast ' + (tone || 'ok');
            el.innerHTML = '<i class="mdi ' + (icon || 'mdi-check-circle') + '"></i>' + htmlEsc(msg);
            wrap.innerHTML = '';
            wrap.appendChild(el);
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function(){ wrap.innerHTML = ''; }, 3000);
        }
        function htmlEsc(str) {
            return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        // ---- Apply bandeja state to all rows ----
        function applyBandejaToRows() {
            document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-id]').forEach(function(tr) {
                var id = tr.getAttribute('data-id') || '';
                var entry = bandejaState[id];
                if (entry) {
                    tr.dataset.prioridad = entry.prioridad || '';
                    tr.dataset.fechaLimite = entry.fecha_limite || '';
                    tr.dataset.responsable = entry.responsable || '';
                    tr.dataset.motivo = entry.motivo || '';
                    updateRowPrioBadge(tr, entry.prioridad, entry.fecha_limite);
                    // Update urgente button to reflect "in bandeja"
                    var btn = tr.querySelector('.btn-marcar-urgente');
                    if (btn) {
                        btn.title = 'En bandeja — editar prioridad';
                        btn.classList.remove('btn-outline-danger');
                        btn.classList.add('btn-outline-warning');
                        btn.innerHTML = '<i class="mdi mdi-bell-check-outline"></i>';
                    }
                } else {
                    tr.dataset.prioridad = '';
                    tr.dataset.fechaLimite = '';
                    var btn = tr.querySelector('.btn-marcar-urgente');
                    if (btn) {
                        btn.title = 'Marcar como urgente / pronto';
                        btn.classList.remove('btn-outline-warning');
                        btn.classList.add('btn-outline-danger');
                        btn.innerHTML = '<i class="mdi mdi-bell-plus-outline"></i>';
                    }
                    var pill = tr.querySelector('.er-prio-pill');
                    if (pill) { pill.className = 'er-prio-pill d-none'; pill.textContent = ''; }
                }
            });
            updateBandejaTabCount();
            updateKpiBandejaVal();
        }

        function updateRowPrioBadge(tr, prioridad, fechaLimite) {
            var pill = tr.querySelector('.er-prio-pill');
            if (!pill) return;
            var overdue = fechaLimite && fechaLimite < today;
            if (overdue) {
                pill.className = 'er-prio-pill er-prio-vencido';
                pill.innerHTML = '<i class="mdi mdi-clock-alert"></i> Vencido';
            } else if (prioridad === 'urgente') {
                pill.className = 'er-prio-pill er-prio-urgente';
                pill.innerHTML = '<i class="mdi mdi-fire"></i> Urgente';
            } else if (prioridad === 'pronto') {
                pill.className = 'er-prio-pill er-prio-pronto';
                pill.innerHTML = '<i class="mdi mdi-clock-fast"></i> Pronto';
            } else {
                pill.className = 'er-prio-pill d-none';
                pill.textContent = '';
                return;
            }
            // Highlight row
            tr.classList.toggle('er-row-urgente', !overdue);
            tr.classList.toggle('table-danger', !!overdue);
        }

        function updateBandejaTabCount() {
            var count = 0;
            document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-id]').forEach(function(tr){
                if ((tr.dataset.prioridad || '') && !(tr.dataset.informado === '1')) count++;
            });
            var badge = document.getElementById('tabBandejaCount');
            if (badge) badge.textContent = count;
        }

        function updateKpiBandejaVal() {
            var el = document.getElementById('kpiBandejaVal');
            if (!el) return;
            var count = 0;
            document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-id]').forEach(function(tr){
                if ((tr.dataset.prioridad || '') && !(tr.dataset.informado === '1')) count++;
            });
            el.textContent = count;
        }

        // ---- Extend rowInActiveTab to support "bandeja" tab ----
        document.addEventListener('DOMContentLoaded', function () {
            // Patch: when activeTab becomes 'bandeja', filter rows that have prioridad
            // We intercept the tab click to inject the bandeja filter logic
            var origTabBtns = document.querySelectorAll('#tabInformes [data-tab="bandeja"]');
            origTabBtns.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    // Deactivate all tabs, activate bandeja
                    document.querySelectorAll('#tabInformes .nav-link').forEach(function(b){ b.classList.remove('active'); });
                    btn.classList.add('active');
                    // Manually filter: show only rows with prioridad and not informado
                    var dtInstance = window.__erDataTableInstance;
                    document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-id]').forEach(function(tr){
                        var hasPrio = !!(tr.dataset.prioridad || '');
                        var isInformado = tr.dataset.informado === '1';
                        tr.style.display = (hasPrio && !isInformado) ? '' : 'none';
                    });
                    updateTabDesc('bandeja');
                    updateBulkBarVisibility();
                    // Also tell the existing dataTable about the active tab
                    if (window.__erSetActiveTab) window.__erSetActiveTab('bandeja');
                });
            });

            // Hook into existing tab clicks to update tab descriptions
            document.querySelectorAll('#tabInformes [data-tab]').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var tab = btn.getAttribute('data-tab');
                    if (tab !== 'bandeja') {
                        setTimeout(function(){ updateTabDesc(tab); }, 50);
                    }
                });
            });

            // Tab help icons
            document.querySelectorAll('.er-tab-help').forEach(function(span){
                span.addEventListener('click', function(e){
                    e.stopPropagation();
                    var tabKey = span.getAttribute('data-tab-help');
                    openTabHelp(tabKey);
                });
            });

            // Apply bandeja state after DOM ready
            applyBandejaToRows();
            updateTabDesc('no-informados');
        });

        // ---- Tab descriptions ----
        var TAB_DESCS = {
            'no-informados': {
                bg: '#f0f0ff', c: '#5156be',
                icon: 'mdi-file-document-edit-outline',
                label: 'Por informar',
                desc: 'Exámenes con archivos en el NAS listos para informar. Abre el modal de «Informar» para cargar la plantilla.'
            },
            'bandeja': {
                bg: '#fdecef', c: '#e84c5b',
                icon: 'mdi-bell-alert-outline',
                label: 'Bandeja prioritaria',
                desc: 'Exámenes marcados como <b>Urgente</b> o <b>Pronto</b> por el equipo. Ordénate aquí para los casos con plazo vencido.'
            },
            'informados': {
                bg: '#e3f5ee', c: '#1f9d7a',
                icon: 'mdi-file-check-outline',
                label: 'Informados',
                desc: 'Exámenes con informe firmado. Puedes imprimirlos o descargarlos. El paciente recibió aviso por WhatsApp.'
            },
            'sin-nas': {
                bg: '#fff6e3', c: '#b9760f',
                icon: 'mdi-folder-alert-outline',
                label: 'Sin archivos',
                desc: 'Procedimientos proyectados sin archivos en el NAS. Usa «Reclamar» para notificar al área técnica.'
            }
        };

        function updateTabDesc(tabKey) {
            var strip = document.getElementById('erTabDesc');
            if (!strip) return;
            var cfg = TAB_DESCS[tabKey];
            if (!cfg) { strip.classList.add('d-none'); return; }
            strip.classList.remove('d-none');
            strip.style.cssText = '--er-desc-bg:' + cfg.bg + ';--er-desc-c:' + cfg.c;
            strip.innerHTML = '<i class="mdi ' + cfg.icon + '"></i>'
                + '<span>' + cfg.desc + '</span>'
                + '<button class="er-td-more" onclick="document.getElementById(\'btnHelpOpen\').click()">Saber más</button>';
        }

        // ---- Tab help modal ----
        var TAB_HELP = {
            'no-informados': {
                title: 'Pestaña «Por informar»',
                sub: 'Exámenes pendientes con archivos disponibles',
                body: 'Esta pestaña muestra los exámenes que ya tienen archivos en el NAS pero aún no tienen informe generado. Es el trabajo principal del técnico o médico de imágenes. Haz clic en «Informar» en cada fila para cargar la plantilla correspondiente al tipo de examen, completarla y guardarla. Al guardar, el paciente recibe un aviso automático por WhatsApp.',
                ico: 'mdi-file-document-edit-outline', bg: '#f0f0ff', c: '#5156be'
            },
            'bandeja': {
                title: 'Pestaña «Bandeja prioritaria»',
                sub: 'Casos urgentes o que requieren informe pronto',
                body: 'Exámenes no informados que alguien del equipo marcó como Urgente (informar hoy mismo) o Pronto (en los próximos días). Se ordenan por prioridad y fecha límite. Los casos con plazo vencido se resaltan en rojo. Para agregar un examen a la bandeja, usa el botón 🔔 en la fila desde «Por informar».',
                ico: 'mdi-bell-alert-outline', bg: '#fdecef', c: '#e84c5b'
            },
            'informados': {
                title: 'Pestaña «Informados»',
                sub: 'Exámenes con informe firmado y guardado',
                body: 'Aquí aparecen los exámenes que ya tienen informe guardado en el sistema. Puedes ver el informe, imprimirlo o descargarlo en paquete. El paciente ya recibió (o recibirá) un aviso por WhatsApp con la disponibilidad del resultado.',
                ico: 'mdi-file-check-outline', bg: '#e3f5ee', c: '#1f9d7a'
            },
            'sin-nas': {
                title: 'Pestaña «Sin archivos»',
                sub: 'Procedimientos sin imágenes en el NAS',
                body: 'Procedimientos proyectados para los cuales el sistema no encontró archivos de imágenes en el NAS. Puede ser que el examen no se realizó, o que el equipo médico aún no los transfirió al servidor. Usa «Reclamar» para registrar el faltante y notificar al área técnica.',
                ico: 'mdi-folder-alert-outline', bg: '#fff6e3', c: '#b9760f'
            }
        };

        function openTabHelp(tabKey) {
            var cfg = TAB_HELP[tabKey];
            if (!cfg) return;
            document.getElementById('modalTabHelpLabel').textContent = cfg.title;
            document.getElementById('tabHelpSub').textContent = cfg.sub;
            document.getElementById('tabHelpBody').textContent = cfg.body;
            var ico = document.getElementById('tabHelpIco');
            ico.style.background = cfg.bg;
            ico.style.color = cfg.c;
            ico.innerHTML = '<i class="mdi ' + cfg.ico + '"></i>';
            var bsModal = new bootstrap.Modal(document.getElementById('modalTabHelp'));
            bsModal.show();
        }

        // ---- Help modal ----
        document.addEventListener('DOMContentLoaded', function(){
            var btnHelp = document.getElementById('btnHelpOpen');
            if (btnHelp) {
                btnHelp.addEventListener('click', function(){
                    new bootstrap.Modal(document.getElementById('modalHelp')).show();
                });
            }
        });

        // ---- KPI click shortcuts ----
        document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('.er-kpi[data-kpi-action]').forEach(function(kpi){
                kpi.addEventListener('click', function(){
                    var action = kpi.getAttribute('data-kpi-action');
                    var tabMap = {
                        'por-informar': 'no-informados',
                        'bandeja': 'bandeja',
                        'sin-nas': 'sin-nas',
                        'informados': 'informados'
                    };
                    var tabKey = tabMap[action];
                    if (!tabKey) return;
                    var btn = document.querySelector('#tabInformes [data-tab="' + tabKey + '"]');
                    if (btn) btn.click();
                    // Toggle active style on KPI
                    document.querySelectorAll('.er-kpi').forEach(function(k){ k.classList.remove('active'); });
                    kpi.classList.add('active');
                });
            });
        });

        // ---- Marcar urgente modal ----
        var urgenteTargetRows = [];
        var urgentePrioridad = 'urgente';

        function openUrgenteModal(rows) {
            urgenteTargetRows = rows;
            urgentePrioridad = 'urgente';

            var multi = rows.length > 1;
            var base = rows[0];

            document.getElementById('modalMarcarUrgenteLabel').textContent =
                (base && base.dataset.prioridad) ? 'Editar prioridad' : 'Marcar para informe prioritario';
            document.getElementById('modalUrgenteSubtitle').textContent =
                multi ? (rows.length + ' exámenes seleccionados') : '';

            var ptStrip = document.getElementById('modalUrgentePtStrip');
            if (!multi && base) {
                ptStrip.style.display = '';
                document.getElementById('urgentePaciente').textContent = base.dataset.paciente || '';
                document.getElementById('urgenteExamen').textContent = base.dataset.examen || '';
                document.getElementById('urgenteOjo').textContent = base.dataset.ojo || '—';
            } else {
                ptStrip.style.display = 'none';
            }

            // Restore previous values if editing
            var existing = base ? bandejaState[base.getAttribute('data-id') || ''] : null;
            urgentePrioridad = (existing && existing.prioridad) || 'urgente';
            document.getElementById('urgenteFechaLimite').value = (existing && existing.fecha_limite) || today;
            document.getElementById('urgenteResponsable').value = (existing && existing.responsable) || '';
            document.getElementById('urgenteMotivo').value = (existing && existing.motivo) || '';
            document.getElementById('urgenteFootNote').textContent = '';

            updateSegButtons(urgentePrioridad);

            var btnConfirm = document.getElementById('btnConfirmarUrgente');
            btnConfirm.textContent = '';
            btnConfirm.innerHTML = '<i class="mdi mdi-bell-plus-outline me-1"></i>' +
                (existing && existing.prioridad ? 'Actualizar prioridad' : 'Enviar a bandeja');

            new bootstrap.Modal(document.getElementById('modalMarcarUrgente')).show();
        }

        function updateSegButtons(prio) {
            document.getElementById('segUrgente').classList.toggle('sel-urgente', prio === 'urgente');
            document.getElementById('segPronto').classList.toggle('sel-pronto', prio === 'pronto');
        }

        document.addEventListener('DOMContentLoaded', function(){
            document.getElementById('segUrgente').addEventListener('click', function(){
                urgentePrioridad = 'urgente';
                updateSegButtons('urgente');
            });
            document.getElementById('segPronto').addEventListener('click', function(){
                urgentePrioridad = 'pronto';
                updateSegButtons('pronto');
            });

            // Quick tags
            document.querySelectorAll('#urgenteQuickTags .er-quick-tag').forEach(function(tag){
                tag.addEventListener('click', function(){
                    document.getElementById('urgenteMotivo').value = tag.getAttribute('data-motivo') || '';
                });
            });

            // Confirm
            document.getElementById('btnConfirmarUrgente').addEventListener('click', function(){
                var motivo = (document.getElementById('urgenteMotivo').value || '').trim();
                if (!motivo) {
                    document.getElementById('urgenteMotivo').focus();
                    document.getElementById('urgenteMotivo').classList.add('is-invalid');
                    return;
                }
                document.getElementById('urgenteMotivo').classList.remove('is-invalid');

                var data = {
                    prioridad: urgentePrioridad,
                    fecha_limite: document.getElementById('urgenteFechaLimite').value || today,
                    responsable: document.getElementById('urgenteResponsable').value.trim(),
                    motivo: motivo
                };

                urgenteTargetRows.forEach(function(tr){
                    var id = tr.getAttribute('data-id') || '';
                    if (!id) return;
                    bandejaState[id] = data;
                    tr.dataset.prioridad = data.prioridad;
                    tr.dataset.fechaLimite = data.fecha_limite;
                    tr.dataset.responsable = data.responsable;
                    tr.dataset.motivo = data.motivo;
                });
                saveBandeja();
                applyBandejaToRows();

                bootstrap.Modal.getInstance(document.getElementById('modalMarcarUrgente')).hide();
                showToast(
                    urgenteTargetRows.length > 1
                        ? (urgenteTargetRows.length + ' exámenes enviados a la bandeja prioritaria')
                        : 'Examen en la bandeja prioritaria',
                    'mdi-bell-check'
                );
            });

            // Row buttons: marcar urgente
            document.querySelector('#tablaImagenesRealizadas tbody').addEventListener('click', function(e){
                var btn = e.target.closest('.btn-marcar-urgente');
                if (!btn) return;
                var tr = btn.closest('tr[data-id]');
                if (!tr) return;
                openUrgenteModal([tr]);
            });
        });

        // ---- Bulk bar ----
        function updateBulkBarVisibility() {
            var bar = document.getElementById('erBulkBar');
            if (!bar) return;
            var checked = document.querySelectorAll('#tablaImagenesRealizadas tbody .row-select:checked');
            if (checked.length > 0) {
                bar.classList.remove('d-none');
                bar.querySelector('.er-bulk-count').textContent = checked.length + ' seleccionado' + (checked.length !== 1 ? 's' : '');
            } else {
                bar.classList.add('d-none');
            }
        }

        document.addEventListener('DOMContentLoaded', function(){
            // Track checkbox changes for bulk bar
            document.querySelector('#tablaImagenesRealizadas tbody').addEventListener('change', function(e){
                if (e.target && e.target.classList.contains('row-select')) {
                    updateBulkBarVisibility();
                }
            });
            var selectAll = document.getElementById('selectAllInformados');
            if (selectAll) {
                selectAll.addEventListener('change', function(){ setTimeout(updateBulkBarVisibility, 0); });
            }

            // Bulk: enviar a bandeja
            var bulkBandejaBtn = document.querySelector('.er-bulk-bandeja-btn');
            if (bulkBandejaBtn) {
                bulkBandejaBtn.addEventListener('click', function(){
                    var selected = Array.from(document.querySelectorAll('#tablaImagenesRealizadas tbody tr[data-id]')).filter(function(tr){
                        var cb = tr.querySelector('.row-select');
                        return cb && cb.checked && !cb.disabled;
                    });
                    if (!selected.length) return;
                    openUrgenteModal(selected);
                });
            }

            // Bulk: clear
            var bulkClearBtn = document.querySelector('.er-bulk-clear-btn');
            if (bulkClearBtn) {
                bulkClearBtn.addEventListener('click', function(){
                    document.querySelectorAll('#tablaImagenesRealizadas tbody .row-select:checked').forEach(function(cb){ cb.checked = false; });
                    var sa = document.getElementById('selectAllInformados');
                    if (sa) { sa.checked = false; sa.indeterminate = false; }
                    updateBulkBarVisibility();
                });
            }

            // Also restore bandeja state on page load
            setTimeout(applyBandejaToRows, 200);
        });

    })();
    </script>

@endsection
