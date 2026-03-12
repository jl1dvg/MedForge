@extends('layouts.medforge')

@php
    $filters = is_array($filters ?? null) ? $filters : [];
    $catalogos = is_array($catalogos ?? null) ? $catalogos : ['afiliaciones' => [], 'tipos_atencion' => [], 'sedes' => [], 'categorias' => [], 'categorias_madre_referido' => []];
    $rows = is_array($rows ?? null) ? $rows : [];
    $summary = is_array($summary ?? null) ? $summary : [
        'total' => 0,
        'total_consultas' => 0,
        'total_protocolos' => 0,
        'economico' => [
            'total_produccion' => 0,
            'ticket_promedio_facturado' => 0,
            'produccion_promedio_por_atencion' => 0,
            'atenciones_facturadas' => 0,
            'atenciones_no_facturadas' => 0,
            'facturacion_rate' => 0,
            'procedimientos_facturados' => 0,
            'produccion_por_categoria' => ['particular' => 0, 'privado' => 0],
            'trend' => ['labels' => [], 'totals' => []],
        ],
        'pacientes_unicos' => 0,
        'categoria_counts' => ['particular' => 0, 'privado' => 0],
        'categoria_share' => ['particular' => 0, 'privado' => 0],
        'top_afiliaciones' => [],
        'referido_prefactura' => ['with_value' => 0, 'without_value' => 0, 'top_values' => [], 'values' => []],
        'referido_prefactura_pacientes_unicos' => ['with_value' => 0, 'without_value' => 0, 'top_values' => [], 'values' => []],
        'referido_prefactura_consulta_nuevo_paciente' => ['with_value' => 0, 'without_value' => 0, 'top_values' => [], 'values' => []],
        'especificar_referido_prefactura' => ['with_value' => 0, 'without_value' => 0, 'top_values' => [], 'values' => []],
        'hierarquia_referidos' => ['categorias' => [], 'pares' => []],
        'temporal' => [
            'current_month_label' => 'N/D',
            'current_month_count' => 0,
            'previous_month_label' => 'N/D',
            'previous_month_count' => 0,
            'same_month_last_year_label' => 'N/D',
            'same_month_last_year_count' => 0,
            'vs_previous_pct' => null,
            'vs_same_month_last_year_pct' => null,
            'trend' => ['labels' => [], 'counts' => []],
        ],
        'procedimientos_volumen' => [
            'top_10' => [],
            'concentracion' => ['top_3_pct' => 0, 'top_5_pct' => 0, 'top_3_count' => 0, 'top_5_count' => 0],
        ],
        'desglose_gerencial' => ['sedes' => [], 'doctores' => [], 'afiliaciones' => [], 'categorias' => []],
        'picos' => ['dias' => [], 'horas' => [], 'peak_day' => ['valor' => 'N/D', 'cantidad' => 0], 'peak_hour' => ['valor' => 'N/D', 'cantidad' => 0]],
        'pacientes_frecuencia' => ['nuevos' => 0, 'recurrentes' => 0, 'nuevos_pct' => 0, 'recurrentes_pct' => 0],
    ];

    $dateFromSeleccionado = trim((string) ($filters['date_from'] ?? ''));
    $dateToSeleccionado = trim((string) ($filters['date_to'] ?? ''));
    $afiliacionSeleccionada = strtolower(trim((string) ($filters['afiliacion'] ?? '')));
    $sedeSeleccionada = strtoupper(trim((string) ($filters['sede'] ?? '')));
    $categoriaClienteSeleccionada = strtolower(trim((string) ($filters['categoria_cliente'] ?? '')));
    $categoriaMadreReferidoSeleccionada = strtoupper(trim((string) ($filters['categoria_madre_referido'] ?? '')));
    $tipoSeleccionado = strtoupper(trim((string) ($filters['tipo'] ?? '')));
    $procedimientoSeleccionado = trim((string) ($filters['procedimiento'] ?? ''));
    $exportParticularesQuery = array_filter([
        'date_from' => $dateFromSeleccionado,
        'date_to' => $dateToSeleccionado,
        'categoria_cliente' => $categoriaClienteSeleccionada,
        'categoria_madre_referido' => $categoriaMadreReferidoSeleccionada,
        'tipo' => $tipoSeleccionado,
        'sede' => $sedeSeleccionada,
        'afiliacion' => $afiliacionSeleccionada,
        'procedimiento' => $procedimientoSeleccionado,
        'export' => 'excel',
    ], static fn($value): bool => trim((string) $value) !== '');
    $exportParticularesUrl = '/v2/informes/particulares?' . http_build_query($exportParticularesQuery);
    $exportParticularesPdfQuery = $exportParticularesQuery;
    $exportParticularesPdfQuery['export'] = 'pdf';
    $exportParticularesPdfUrl = '/v2/informes/particulares?' . http_build_query($exportParticularesPdfQuery);

    $procedimientoLegible = static function (string $texto): string {
        $texto = trim($texto);
        if ($texto === '') {
            return '—';
        }

        $partes = explode(' - ', $texto);
        $detalle = count($partes) > 2 ? trim(implode(' - ', array_slice($partes, 2))) : $texto;
        $detalle = preg_replace('/ - (AO|OD|OI|AMBOS OJOS|OJO DERECHO|OJO IZQUIERDO)$/i', '', $detalle) ?? $detalle;
        $detalle = trim($detalle);

        return $detalle !== '' ? ucfirst(strtolower($detalle)) : '—';
    };
@endphp

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title"><i class="mdi mdi-file-chart-outline"></i> Informe de Atenciones Particulares
                </h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Particulares</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="ms-auto text-end">
                <span class="badge bg-light text-primary">Fuente: LARAVEL V2</span>
                <div class="text-muted fs-12 mt-5">
                    Última actualización: {{ now()->setTimezone(config('app.timezone', 'America/Guayaquil'))->format('d/m/Y H:i') }}
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="box mb-20">
            <div class="box-body">
                <form method="GET" action="/v2/informes/particulares" class="row g-10 align-items-end">
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Desde</label>
                        <input type="date" name="date_from" id="date_from" class="form-control"
                               value="{{ $dateFromSeleccionado }}">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Hasta</label>
                        <input type="date" name="date_to" id="date_to" class="form-control"
                               value="{{ $dateToSeleccionado }}">
                    </div>
                    <div class="col-md-3">
                        <label for="categoria_cliente" class="form-label">Categoría cliente</label>
                        <select name="categoria_cliente" id="categoria_cliente" class="form-select">
                            <option value="">Todas</option>
                            @foreach(($catalogos['categorias'] ?? []) as $categoria)
                                @php
                                    $categoriaValue = strtolower(trim((string) ($categoria['value'] ?? '')));
                                    $categoriaLabel = trim((string) ($categoria['label'] ?? $categoriaValue));
                                @endphp
                                <option
                                    value="{{ $categoriaValue }}" {{ $categoriaClienteSeleccionada === $categoriaValue ? 'selected' : '' }}>
                                    {{ $categoriaLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="categoria_madre_referido" class="form-label">Categoría madre referido</label>
                        <select name="categoria_madre_referido" id="categoria_madre_referido" class="form-select">
                            <option value="">Todas</option>
                            @foreach(($catalogos['categorias_madre_referido'] ?? []) as $categoriaMadreReferido)
                                @php $categoriaMadreReferidoValue = strtoupper(trim((string) $categoriaMadreReferido)); @endphp
                                <option
                                    value="{{ $categoriaMadreReferidoValue }}" {{ $categoriaMadreReferidoSeleccionada === $categoriaMadreReferidoValue ? 'selected' : '' }}>
                                    {{ $categoriaMadreReferidoValue }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo de atención</label>
                        <select name="tipo" id="tipo" class="form-select">
                            <option value="">Todos</option>
                            @foreach(($catalogos['tipos_atencion'] ?? []) as $tipoAtencion)
                                @php $tipoAtencionValue = strtoupper(trim((string) $tipoAtencion)); @endphp
                                <option
                                    value="{{ $tipoAtencionValue }}" {{ $tipoSeleccionado === $tipoAtencionValue ? 'selected' : '' }}>
                                    {{ $tipoAtencionValue }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="sede" class="form-label">Sede</label>
                        <select name="sede" id="sede" class="form-select">
                            <option value="">Todas</option>
                            @foreach(($catalogos['sedes'] ?? []) as $sede)
                                @php $sedeValue = strtoupper(trim((string) $sede)); @endphp
                                <option
                                    value="{{ $sedeValue }}" {{ $sedeSeleccionada === $sedeValue ? 'selected' : '' }}>
                                    {{ $sedeValue }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="afiliacion" class="form-label">Afiliación</label>
                        <select name="afiliacion" id="afiliacion" class="form-select">
                            <option value="">Todas</option>
                            @foreach(($catalogos['afiliaciones'] ?? []) as $afiliacion)
                                @php $afiliacionValue = strtolower(trim((string) $afiliacion)); @endphp
                                <option
                                    value="{{ $afiliacionValue }}" {{ $afiliacionSeleccionada === $afiliacionValue ? 'selected' : '' }}>
                                    {{ strtoupper($afiliacionValue) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="procedimiento" class="form-label">Procedimiento</label>
                        <input
                            type="text"
                            id="procedimiento"
                            name="procedimiento"
                            class="form-control"
                            value="{{ $procedimientoSeleccionado }}"
                            placeholder="Ej: consulta oftalmologica"
                        >
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-magnify me-5"></i>
                        </button>
                        <a href="{{ $exportParticularesUrl }}" class="btn btn-success">
                            <i class="mdi mdi-file-excel me-5"></i>
                            Excel
                        </a>
                        <a href="{{ $exportParticularesPdfUrl }}" class="btn btn-danger">
                            <i class="mdi mdi-file-pdf-box me-5"></i>
                            PDF KPI
                        </a>
                        <a href="/v2/informes/particulares" class="btn btn-light">
                            <i class="mdi mdi-filter-remove me-5"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @php
            $totalAtenciones = (int) ($summary['total'] ?? 0);
            $totalConsultas = (int) ($summary['total_consultas'] ?? 0);
            $totalProtocolos = (int) ($summary['total_protocolos'] ?? 0);
            $economico = is_array($summary['economico'] ?? null) ? $summary['economico'] : [];
            $produccionTotal = (float) ($economico['total_produccion'] ?? 0);
            $ticketPromedioFacturado = (float) ($economico['ticket_promedio_facturado'] ?? 0);
            $produccionPromedioAtencion = (float) ($economico['produccion_promedio_por_atencion'] ?? 0);
            $atencionesFacturadas = (int) ($economico['atenciones_facturadas'] ?? 0);
            $atencionesNoFacturadas = (int) ($economico['atenciones_no_facturadas'] ?? 0);
            $facturacionRate = (float) ($economico['facturacion_rate'] ?? 0);
            $procedimientosFacturados = (int) ($economico['procedimientos_facturados'] ?? 0);
            $produccionPorCategoria = is_array($economico['produccion_por_categoria'] ?? null) ? $economico['produccion_por_categoria'] : ['particular' => 0, 'privado' => 0];
            $produccionParticular = (float) ($produccionPorCategoria['particular'] ?? 0);
            $produccionPrivado = (float) ($produccionPorCategoria['privado'] ?? 0);
            $economicoTrend = is_array($economico['trend'] ?? null) ? $economico['trend'] : ['labels' => [], 'totals' => []];
            $economicoTrendLabels = is_array($economicoTrend['labels'] ?? null) ? $economicoTrend['labels'] : [];
            $economicoTrendTotals = is_array($economicoTrend['totals'] ?? null) ? $economicoTrend['totals'] : [];
            $pacientesUnicos = (int) ($summary['pacientes_unicos'] ?? 0);
            $categoriaCounts = is_array($summary['categoria_counts'] ?? null) ? $summary['categoria_counts'] : ['particular' => 0, 'privado' => 0];
            $categoriaShare = is_array($summary['categoria_share'] ?? null) ? $summary['categoria_share'] : ['particular' => 0, 'privado' => 0];
            $topAfiliaciones = is_array($summary['top_afiliaciones'] ?? null) ? $summary['top_afiliaciones'] : [];
            $particularCount = (int) ($categoriaCounts['particular'] ?? 0);
            $privadoCount = (int) ($categoriaCounts['privado'] ?? 0);
            $particularShare = (float) ($categoriaShare['particular'] ?? 0);
            $privadoShare = (float) ($categoriaShare['privado'] ?? 0);

            $referidoSummary = is_array($summary['referido_prefactura'] ?? null) ? $summary['referido_prefactura'] : [];
            $referidoTop = is_array(($referidoSummary['top_values'] ?? null)) && isset($referidoSummary['top_values'][0])
                ? (array) $referidoSummary['top_values'][0]
                : [];
            $referidoValues = is_array($referidoSummary['values'] ?? null) ? $referidoSummary['values'] : [];
            $referidoWithValue = (int) ($referidoSummary['with_value'] ?? 0);
            $referidoWithoutValue = (int) ($referidoSummary['without_value'] ?? 0);
            $referidoTopLabel = trim((string) ($referidoTop['valor'] ?? ''));
            $referidoTopCount = (int) ($referidoTop['cantidad'] ?? 0);
            if ($referidoTopLabel === '') {
                $referidoTopLabel = 'Sin datos';
            }

            $referidoUniquePatientsSummary = is_array($summary['referido_prefactura_pacientes_unicos'] ?? null) ? $summary['referido_prefactura_pacientes_unicos'] : [];
            $referidoUniquePatientsValues = is_array($referidoUniquePatientsSummary['values'] ?? null) ? $referidoUniquePatientsSummary['values'] : [];
            $referidoUniquePatientsWithValue = (int) ($referidoUniquePatientsSummary['with_value'] ?? 0);
            $referidoUniquePatientsWithoutValue = (int) ($referidoUniquePatientsSummary['without_value'] ?? 0);

            $referidoNuevoPacienteSummary = is_array($summary['referido_prefactura_consulta_nuevo_paciente'] ?? null) ? $summary['referido_prefactura_consulta_nuevo_paciente'] : [];
            $referidoNuevoPacienteValues = is_array($referidoNuevoPacienteSummary['values'] ?? null) ? $referidoNuevoPacienteSummary['values'] : [];
            $referidoNuevoPacienteWithValue = (int) ($referidoNuevoPacienteSummary['with_value'] ?? 0);
            $referidoNuevoPacienteWithoutValue = (int) ($referidoNuevoPacienteSummary['without_value'] ?? 0);

            $especificarSummary = is_array($summary['especificar_referido_prefactura'] ?? null) ? $summary['especificar_referido_prefactura'] : [];
            $especificarTop = is_array(($especificarSummary['top_values'] ?? null)) && isset($especificarSummary['top_values'][0])
                ? (array) $especificarSummary['top_values'][0]
                : [];
            $especificarWithValue = (int) ($especificarSummary['with_value'] ?? 0);
            $especificarTopLabel = trim((string) ($especificarTop['valor'] ?? ''));
            $especificarTopCount = (int) ($especificarTop['cantidad'] ?? 0);
            if ($especificarTopLabel === '') {
                $especificarTopLabel = 'Sin datos';
            }

            $hierarquiaReferidos = is_array($summary['hierarquia_referidos'] ?? null) ? $summary['hierarquia_referidos'] : [];
            $hierarquiaCategorias = is_array($hierarquiaReferidos['categorias'] ?? null) ? $hierarquiaReferidos['categorias'] : [];
            $hierarquiaPares = is_array($hierarquiaReferidos['pares'] ?? null) ? $hierarquiaReferidos['pares'] : [];

            $hierarquiaCategoriasGraficas = array_values(array_filter($hierarquiaCategorias, static function ($item): bool {
                $categoria = strtoupper(trim((string) ($item['categoria'] ?? '')));
                return $categoria !== '' && $categoria !== 'SIN CATEGORIA';
            }));
            if (empty($hierarquiaCategoriasGraficas)) {
                $hierarquiaCategoriasGraficas = $hierarquiaCategorias;
            }

            $temporalSummary = is_array($summary['temporal'] ?? null) ? $summary['temporal'] : [];
            $currentMonthLabel = (string) ($temporalSummary['current_month_label'] ?? 'N/D');
            $currentMonthCount = (int) ($temporalSummary['current_month_count'] ?? 0);
            $previousMonthLabel = (string) ($temporalSummary['previous_month_label'] ?? 'N/D');
            $previousMonthCount = (int) ($temporalSummary['previous_month_count'] ?? 0);
            $sameMonthLastYearLabel = (string) ($temporalSummary['same_month_last_year_label'] ?? 'N/D');
            $sameMonthLastYearCount = (int) ($temporalSummary['same_month_last_year_count'] ?? 0);
            $vsPreviousPct = is_numeric($temporalSummary['vs_previous_pct'] ?? null) ? (float) $temporalSummary['vs_previous_pct'] : null;
            $vsLastYearPct = is_numeric($temporalSummary['vs_same_month_last_year_pct'] ?? null) ? (float) $temporalSummary['vs_same_month_last_year_pct'] : null;
            $temporalTrend = is_array($temporalSummary['trend'] ?? null) ? $temporalSummary['trend'] : ['labels' => [], 'counts' => []];
            $temporalTrendLabels = is_array($temporalTrend['labels'] ?? null) ? $temporalTrend['labels'] : [];
            $temporalTrendCounts = is_array($temporalTrend['counts'] ?? null) ? $temporalTrend['counts'] : [];

            $procedimientosVolumen = is_array($summary['procedimientos_volumen'] ?? null) ? $summary['procedimientos_volumen'] : [];
            $topProcedimientosVolumen = is_array($procedimientosVolumen['top_10'] ?? null) ? $procedimientosVolumen['top_10'] : [];
            $concentracionVolumen = is_array($procedimientosVolumen['concentracion'] ?? null) ? $procedimientosVolumen['concentracion'] : [];
            $top3ConcentracionPct = (float) ($concentracionVolumen['top_3_pct'] ?? 0);
            $top5ConcentracionPct = (float) ($concentracionVolumen['top_5_pct'] ?? 0);
            $top3ConcentracionCount = (int) ($concentracionVolumen['top_3_count'] ?? 0);
            $top5ConcentracionCount = (int) ($concentracionVolumen['top_5_count'] ?? 0);

            $desgloseGerencial = is_array($summary['desglose_gerencial'] ?? null) ? $summary['desglose_gerencial'] : [];
            $desgloseSedes = is_array($desgloseGerencial['sedes'] ?? null) ? $desgloseGerencial['sedes'] : [];
            $desgloseDoctores = is_array($desgloseGerencial['doctores'] ?? null) ? $desgloseGerencial['doctores'] : [];

            $picosSummary = is_array($summary['picos'] ?? null) ? $summary['picos'] : [];
            $picosDias = is_array($picosSummary['dias'] ?? null) ? $picosSummary['dias'] : [];
            $peakDay = is_array($picosSummary['peak_day'] ?? null) ? $picosSummary['peak_day'] : ['valor' => 'N/D', 'cantidad' => 0];

            $pacientesFrecuencia = is_array($summary['pacientes_frecuencia'] ?? null) ? $summary['pacientes_frecuencia'] : [];
            $pacientesNuevos = (int) ($pacientesFrecuencia['nuevos'] ?? 0);
            $pacientesRecurrentes = (int) ($pacientesFrecuencia['recurrentes'] ?? 0);
            $pacientesNuevosPct = (float) ($pacientesFrecuencia['nuevos_pct'] ?? 0);
            $pacientesRecurrentesPct = (float) ($pacientesFrecuencia['recurrentes_pct'] ?? 0);

            $categoriaLiderLabel = $particularCount >= $privadoCount ? 'PARTICULAR' : 'PRIVADO';
            $categoriaLiderCount = $particularCount >= $privadoCount ? $particularCount : $privadoCount;
            $categoriaLiderPct = $particularCount >= $privadoCount ? $particularShare : $privadoShare;

            $topProcedimientoLider = is_array($topProcedimientosVolumen[0] ?? null) ? $topProcedimientosVolumen[0] : [];
            $topProcedimientoLabel = strtoupper(trim((string) ($topProcedimientoLider['valor'] ?? '')));
            $topProcedimientoCount = (int) ($topProcedimientoLider['cantidad'] ?? 0);
            $topProcedimientoPct = (float) ($topProcedimientoLider['porcentaje'] ?? 0);

            $hallazgosClave = [];
            if ($totalAtenciones > 0) {
                $hallazgosClave[] = sprintf(
                    'La categoría líder fue %s con %d atenciones (%.2f%% del total).',
                    $categoriaLiderLabel,
                    $categoriaLiderCount,
                    $categoriaLiderPct
                );
            }
            if ($topProcedimientoLabel !== '' && $topProcedimientoCount > 0) {
                $hallazgosClave[] = sprintf(
                    'El procedimiento más frecuente fue %s con %d atenciones (%.2f%%).',
                    $topProcedimientoLabel,
                    $topProcedimientoCount,
                    $topProcedimientoPct
                );
            }
            if ($top3ConcentracionCount > 0 || $top5ConcentracionCount > 0) {
                $hallazgosClave[] = sprintf(
                    'Concentración de volumen: Top 3 = %.2f%% (%d), Top 5 = %.2f%% (%d).',
                    $top3ConcentracionPct,
                    $top3ConcentracionCount,
                    $top5ConcentracionPct,
                    $top5ConcentracionCount
                );
            }
            if (($peakDay['cantidad'] ?? 0) > 0) {
                $hallazgosClave[] = sprintf(
                    'Pico operativo por día: %s (%d).',
                    strtoupper((string) ($peakDay['valor'] ?? 'N/D')),
                    (int) ($peakDay['cantidad'] ?? 0)
                );
            }
            $hallazgosClave = array_slice($hallazgosClave, 0, 3);
        @endphp

        <div class="row">
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box box-inverse box-success">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Total</h6>
                        <div class="fs-32 fw-700">{{ $totalAtenciones }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Atenciones No Quirúrgicas</h6>
                        <div class="fs-30 fw-700 text-primary">{{ $totalConsultas }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Cirugías</h6>
                        <div class="fs-30 fw-700 text-info">{{ $totalProtocolos }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Pacientes únicos</h6>
                        <div class="fs-30 fw-700 text-warning">{{ $pacientesUnicos }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Atenciones Particularer</h6>
                        <div class="fs-30 fw-700 text-success">{{ $particularCount }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Atenciones Privadas</h6>
                        <div class="fs-30 fw-700 text-danger">{{ $privadoCount }}</div>
                    </div>
                </div>
            </div>
        </div>


        <div class="row">
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Tendencia mensual de producción (USD)</h5>
                        <span class="badge bg-success-light text-success">${{ number_format($produccionTotal, 2) }}</span>
                    </div>
                    <div class="box-body">
                        <div id="tendenciaProduccionChart" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Producción por categoría cliente (USD)</h5>
                        <span class="badge bg-primary-light text-primary">{{ $atencionesFacturadas }} facturadas / {{ $atencionesNoFacturadas }} pendientes</span>
                    </div>
                    <div class="box-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Categoría</th>
                                    <th class="text-end">Producción</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td>PARTICULAR</td>
                                    <td class="text-end">${{ number_format($produccionParticular, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>PRIVADO</td>
                                    <td class="text-end">${{ number_format($produccionPrivado, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-600">TOTAL</td>
                                    <td class="text-end fw-700">${{ number_format($produccionTotal, 2) }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h5 class="box-title mb-0">Tendencia mensual de volumen (últimos 12 meses)</h5>
                    </div>
                    <div class="box-body">
                        <div id="tendenciaVolumenChart" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Picos por día</h5>
                        <span class="badge bg-info-light text-info">Pico: {{ strtoupper((string) ($peakDay['valor'] ?? 'N/D')) }}
                            ({{ (int) ($peakDay['cantidad'] ?? 0) }})</span>
                    </div>
                    <div class="box-body">
                        <div id="picosDiasChart" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-xl-3 col-md-6 col-12">
                <div class="box">
                    <div class="box-body">
                        <h6 class="text-muted mb-5">Volumen mes actual</h6>
                        <div class="fs-24 fw-700 text-primary">{{ $currentMonthCount }}</div>
                        <small class="text-muted">{{ $currentMonthLabel }}</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="box">
                    <div class="box-body">
                        <h6 class="text-muted mb-5">Vs mes anterior</h6>
                        <div
                            class="fs-24 fw-700 {{ $vsPreviousPct !== null && $vsPreviousPct >= 0 ? 'text-success' : 'text-danger' }}">
                            @if($vsPreviousPct === null)
                                N/D
                            @else
                                {{ $vsPreviousPct >= 0 ? '↑' : '↓' }} {{ number_format(abs($vsPreviousPct), 2) }}%
                            @endif
                        </div>
                        <small class="text-muted">{{ $currentMonthCount }} vs {{ $previousMonthCount }}
                            ({{ $previousMonthLabel }})</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="box">
                    <div class="box-body">
                        <h6 class="text-muted mb-5">Vs mismo mes año pasado</h6>
                        <div
                            class="fs-24 fw-700 {{ $vsLastYearPct !== null && $vsLastYearPct >= 0 ? 'text-success' : 'text-danger' }}">
                            @if($vsLastYearPct === null)
                                N/D
                            @else
                                {{ $vsLastYearPct >= 0 ? '↑' : '↓' }} {{ number_format(abs($vsLastYearPct), 2) }}%
                            @endif
                        </div>
                        <small class="text-muted">{{ $currentMonthCount }} vs {{ $sameMonthLastYearCount }}
                            ({{ $sameMonthLastYearLabel }})</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="box">
                    <div class="box-body">
                        <h6 class="text-muted mb-5">Nuevos vs recurrentes</h6>
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="fs-24 fw-700 text-success">{{ $pacientesNuevos }}</div>
                                <small class="text-muted">Nuevos ({{ number_format($pacientesNuevosPct, 2) }}%)</small>
                            </div>
                            <div class="text-end">
                                <div class="fs-24 fw-700 text-warning">{{ $pacientesRecurrentes }}</div>
                                <small class="text-muted">Recurrentes ({{ number_format($pacientesRecurrentesPct, 2) }}
                                    %)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h5 class="box-title mb-0">Desglose por sede (Top)</h5>
                    </div>
                    <div class="box-body">
                        <div id="desgloseSedeChart" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h5 class="box-title mb-0">Desglose por médico (Top 10)</h5>
                    </div>
                    <div class="box-body">
                        <div id="desgloseDoctorChart" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Concentración de procedimientos</h5>
                        <span class="badge bg-primary-light text-primary">{{ count($topProcedimientosVolumen) }} valores</span>
                    </div>
                    <div class="box-body">
                        <div id="topProcedimientosVolumenChart" style="min-height: 260px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-3 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Producción facturada</h6>
                        <div class="fs-28 fw-700 text-success">${{ number_format($produccionTotal, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Ticket promedio facturado</h6>
                        <div class="fs-28 fw-700 text-primary">${{ number_format($ticketPromedioFacturado, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Atenciones facturadas</h6>
                        <div class="fs-28 fw-700 text-info">{{ $atencionesFacturadas }}</div>
                        <small class="text-muted">{{ number_format($facturacionRate, 2) }}% del total</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Procedimientos facturados</h6>
                        <div class="fs-28 fw-700 text-warning">{{ $procedimientosFacturados }}</div>
                        <small class="text-muted">${{ number_format($produccionPromedioAtencion, 2) }} promedio por atención</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Mix categoría cliente (Pie)</h5>
                        <span class="badge bg-success-light text-success">{{ $totalAtenciones }} registros</span>
                    </div>
                    <div class="box-body">
                        <div id="mixCategoriaClienteChart" style="min-height: 320px;"></div>
                        <div class="table-responsive mt-15">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Categoría</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">%</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td>PARTICULAR</td>
                                    <td class="text-end">{{ $particularCount }}</td>
                                    <td class="text-end">{{ number_format($particularShare, 2) }}%</td>
                                </tr>
                                <tr>
                                    <td>PRIVADO</td>
                                    <td class="text-end">{{ $privadoCount }}</td>
                                    <td class="text-end">{{ number_format($privadoShare, 2) }}%</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Top afiliaciones (Polar)</h5>
                        <span class="badge bg-primary-light text-primary">{{ count($topAfiliaciones) }} valores</span>
                    </div>
                    <div class="box-body">
                        <div id="topAfiliacionesChart" style="min-height: 320px;"></div>
                        <div class="table-responsive mt-15" style="max-height: 240px;">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Afiliación</th>
                                    <th class="text-end">Cantidad</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($topAfiliaciones as $item)
                                    @php
                                        $cantidad = (int) ($item['cantidad'] ?? 0);
                                        $afiliacion = strtoupper(trim((string) ($item['afiliacion'] ?? 'SIN AFILIACION')));
                                    @endphp
                                    <tr>
                                        <td>{{ $afiliacion !== '' ? $afiliacion : 'SIN AFILIACION' }}</td>
                                        <td class="text-end">{{ $cantidad }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">Sin datos disponibles.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Origen de referencia: Total de Atenciones</h5>
                        <span class="badge bg-primary-light text-primary">{{ count($referidoValues) }} valores</span>
                    </div>
                    <div class="box-body">
                        <div id="referidoPrefacturaChart" style="min-height: 300px;"></div>
                        <div class="table-responsive mt-15" style="max-height: 320px;">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Valor</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">%</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($referidoValues as $item)
                                    @php
                                        $valor = trim((string) ($item['valor'] ?? ''));
                                        if ($valor === '') {
                                            $valor = 'SIN DATO';
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ strtoupper($valor) }}</td>
                                        <td class="text-end">{{ (int) ($item['cantidad'] ?? 0) }}</td>
                                        <td class="text-end">{{ number_format((float) ($item['porcentaje'] ?? 0), 2) }}
                                            %
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Sin datos para el rango
                                            seleccionado.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Origen de referencia: Pacientes únicos</h5>
                        <span class="badge bg-primary-light text-primary">{{ count($referidoUniquePatientsValues) }} valores</span>
                    </div>
                    <div class="box-body">
                        <div id="referidoPrefacturaPacientesUnicosChart" style="min-height: 320px;"></div>
                        <div class="table-responsive mt-15" style="max-height: 320px;">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Valor</th>
                                    <th class="text-end">Pacientes únicos</th>
                                    <th class="text-end">%</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($referidoUniquePatientsValues as $item)
                                    @php
                                        $valor = trim((string) ($item['valor'] ?? ''));
                                        if ($valor === '') {
                                            $valor = 'SIN DATO';
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ strtoupper($valor) }}</td>
                                        <td class="text-end">{{ (int) ($item['cantidad'] ?? 0) }}</td>
                                        <td class="text-end">{{ number_format((float) ($item['porcentaje'] ?? 0), 2) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Sin datos para el rango seleccionado.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Origen de referencia: Nuevo paciente</h5>
                        <span class="badge bg-primary-light text-primary">{{ count($referidoNuevoPacienteValues) }} valores</span>
                    </div>
                    <div class="box-body">
                        <div id="referidoPrefacturaNuevoPacienteChart" style="min-height: 320px;"></div>
                        <div class="table-responsive mt-15" style="max-height: 320px;">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Valor</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">%</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($referidoNuevoPacienteValues as $item)
                                    @php
                                        $valor = trim((string) ($item['valor'] ?? ''));
                                        if ($valor === '') {
                                            $valor = 'SIN DATO';
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ strtoupper($valor) }}</td>
                                        <td class="text-end">{{ (int) ($item['cantidad'] ?? 0) }}</td>
                                        <td class="text-end">{{ number_format((float) ($item['porcentaje'] ?? 0), 2) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Sin datos para el rango seleccionado.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="box">
                    <div
                        class="box-header with-border d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h5 class="box-title mb-0">Jerarquía de referencias (% dentro de cada categoría
                            madre)</h5>
                        <span class="badge bg-dark-light text-dark">{{ count($hierarquiaCategoriasGraficas) }} categorías</span>
                    </div>
                    <div class="box-body">
                        <p class="text-muted mb-15">
                            `% en categoría` significa: de todos los casos de la categoría madre, qué porcentaje
                            representa esa subcategoría.
                        </p>
                        <div class="row g-15">
                            <div class="col-xl-4 col-12">
                                <div id="hierarquiaCategoriasChart" style="min-height: 320px;"></div>
                            </div>
                            <div class="col-xl-8 col-12">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-10">
                                    <label for="hierarquiaCategoriaSelect" class="form-label mb-0 fw-600">Categoría
                                        madre</label>
                                    <select id="hierarquiaCategoriaSelect" class="form-select"
                                            style="max-width: 320px;">
                                        @foreach($hierarquiaCategoriasGraficas as $categoria)
                                            @php $categoriaValue = (string) ($categoria['categoria'] ?? ''); @endphp
                                            <option value="{{ $categoriaValue }}">{{ $categoriaValue }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div id="hierarquiaSubcategoriasChart" style="min-height: 320px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12 col-12">
                <div class="box">
                    <div class="box-body">
                        <div class="table-responsive mb-20">
                            <table class="table table-striped table-hover mb-0" id="tablaAtencionesRango">
                                <thead class="bg-primary-light">
                                <tr>
                                    <th>#</th>
                                    <th>HC</th>
                                    <th>Nombre</th>
                                    <th>Afiliación</th>
                                    <th>Sede</th>
                                    <th>Categoría</th>
                                    <th>Estado encuentro</th>
                                    <th>Tipo atención</th>
                                    <th>Fecha</th>
                                    <th>Procedimiento</th>
                                    <th>Doctor</th>
                                    <th>Facturación</th>
                                    <th>Producción</th>
                                    <th>Proc. facturados</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($rows as $index => $row)
                                    @php
                                        $afiliacion = strtoupper(trim((string) ($row['afiliacion'] ?? '')));
                                        if ($afiliacion === '') {
                                            $afiliacion = '—';
                                        }
                                        $sede = strtoupper(trim((string) ($row['sede'] ?? '')));
                                        if ($sede === '') {
                                            $sede = '—';
                                        }
                                        $estadoEncuentro = trim((string) ($row['estado_encuentro'] ?? ''));
                                        if ($estadoEncuentro === '') {
                                            $estadoEncuentro = '—';
                                        }
                                        $categoriaClienteRaw = strtolower(trim((string) ($row['categoria_cliente'] ?? '')));
                                        $categoriaCliente = $categoriaClienteRaw !== '' ? ucfirst($categoriaClienteRaw) : '—';
                                        $badgeClass = match ($afiliacion) {
                                            'PARTICULAR' => 'bg-primary',
                                            'HUMANA - COPAGO' => 'bg-info',
                                            'BEST DOCTOR 100' => 'bg-success',
                                            'SALUD (REEMBOLSO) NIVEL 5' => 'bg-warning',
                                            'FUNDACIONES' => 'bg-danger',
                                            default => 'bg-secondary',
                                        };
                                        $tipoAtencion = strtoupper(trim((string) ($row['tipo_atencion'] ?? 'SIN TIPO')));
                                        $tipoAtencionBadge = match ($tipoAtencion) {
                                            'CIRUGIAS' => 'bg-danger',
                                            'IMAGENES' => 'bg-info',
                                            'SERVICIOS OFTALMOLOGICOS GENERALES' => 'bg-primary',
                                            default => 'bg-secondary',
                                        };
                                        $fecha = trim((string) ($row['fecha'] ?? ''));
                                        $fechaFmt = $fecha !== '' && strtotime($fecha) !== false ? date('d/m/Y', strtotime($fecha)) : '—';
                                        $facturado = (bool) ($row['facturado'] ?? false);
                                        $produccionRow = (float) ($row['total_produccion'] ?? 0);
                                        $procedimientosFacturadosRow = (int) ($row['procedimientos_facturados'] ?? 0);
                                    @endphp
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ (string) ($row['hc_number'] ?? '—') }}</td>
                                        <td>{{ ucwords(strtolower(trim((string) ($row['nombre_completo'] ?? '—')))) }}</td>
                                        <td><span class="badge {{ $badgeClass }}">{{ $afiliacion }}</span></td>
                                        <td>
                                                <span
                                                    class="badge {{ $sede === 'CEIBOS' ? 'bg-info' : ($sede === 'MATRIZ' ? 'bg-warning' : 'bg-secondary') }}">
                                                    {{ $sede }}
                                                </span>
                                        </td>
                                        <td>
                                                <span
                                                    class="badge {{ $categoriaClienteRaw === 'particular' ? 'bg-success' : ($categoriaClienteRaw === 'privado' ? 'bg-danger' : 'bg-secondary') }}">
                                                    {{ $categoriaCliente }}
                                                </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">{{ strtoupper($estadoEncuentro) }}</span>
                                        </td>
                                        <td>
                                                <span
                                                    class="badge {{ $tipoAtencionBadge }}">
                                                    {{ $tipoAtencion }}
                                                </span>
                                        </td>
                                        <td>{{ $fechaFmt }}</td>
                                        <td>{{ $procedimientoLegible((string) ($row['procedimiento_proyectado'] ?? '')) }}</td>
                                        <td>{{ trim((string) ($row['doctor'] ?? '')) !== '' ? ucwords(strtolower((string) $row['doctor'])) : '—' }}</td>
                                        <td>
                                                <span class="badge {{ $facturado ? 'bg-success' : 'bg-warning' }}">
                                                    {{ $facturado ? 'FACTURADO' : 'PENDIENTE' }}
                                                </span>
                                        </td>
                                        <td class="text-end">${{ number_format($produccionRow, 2) }}</td>
                                        <td class="text-end">{{ $procedimientosFacturadosRow }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="14" class="text-center text-muted py-30">No hay atenciones
                                            particulares para los filtros seleccionados.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="/assets/vendor_components/datatable/datatables.min.js"></script>
    <script src="/assets/vendor_components/apexcharts-bundle/dist/apexcharts.js"></script>
    <script>
        (function () {
            const referidoValues = @json($referidoValues);
            const referidoUniquePatientValues = @json($referidoUniquePatientsValues);
            const referidoNuevoPacienteValues = @json($referidoNuevoPacienteValues);
            const referidoWithValue = @json($referidoWithValue);
            const referidoWithoutValue = @json($referidoWithoutValue);
            const referidoUniquePatientsWithValue = @json($referidoUniquePatientsWithValue);
            const referidoUniquePatientsWithoutValue = @json($referidoUniquePatientsWithoutValue);
            const referidoNuevoPacienteWithValue = @json($referidoNuevoPacienteWithValue);
            const referidoNuevoPacienteWithoutValue = @json($referidoNuevoPacienteWithoutValue);
            const particularCount = @json($particularCount);
            const privadoCount = @json($privadoCount);
            const topAfiliaciones = @json($topAfiliaciones);
            const hierarquiaCategorias = @json($hierarquiaCategoriasGraficas);
            const temporalTrendLabels = @json($temporalTrendLabels);
            const temporalTrendCounts = @json($temporalTrendCounts);
            const economicoTrendLabels = @json($economicoTrendLabels);
            const economicoTrendTotals = @json($economicoTrendTotals);
            const topProcedimientosVolumen = @json($topProcedimientosVolumen);
            const desgloseSedes = @json($desgloseSedes);
            const desgloseDoctores = @json($desgloseDoctores);
            const picosDias = @json($picosDias);

            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const buildVerticalChart = function (selector, title, values, color) {
                const container = document.querySelector(selector);
                if (!container) {
                    return;
                }

                const categories = values.map(function (item) {
                    const raw = (item && item.valor ? String(item.valor) : '').trim();
                    return raw !== '' ? raw.toUpperCase() : 'SIN DATO';
                });
                const counts = values.map(function (item) {
                    const qty = Number(item && item.cantidad ? item.cantidad : 0);
                    return Number.isFinite(qty) ? qty : 0;
                });

                if (counts.length === 0) {
                    container.innerHTML = '<div class="text-muted text-center py-30">Sin datos para graficar.</div>';
                    return;
                }

                container.style.minHeight = '360px';

                const chart = new ApexCharts(container, {
                    chart: {
                        type: 'bar',
                        height: 360,
                        toolbar: {show: false},
                    },
                    series: [{
                        name: 'Cantidad',
                        data: counts,
                    }],
                    xaxis: {
                        categories: categories,
                        labels: {
                            rotate: -35,
                            hideOverlappingLabels: false,
                            trim: true,
                            formatter: function (value) {
                                const text = String(value || '');
                                return text.length > 18 ? text.slice(0, 18) + '...' : text;
                            },
                        },
                    },
                    yaxis: {
                        min: 0,
                        forceNiceScale: true,
                        title: {
                            text: 'Cantidad',
                        },
                    },
                    plotOptions: {
                        bar: {
                            horizontal: false,
                            borderRadius: 4,
                            columnWidth: '55%',
                        },
                    },
                    colors: [color],
                    title: {
                        text: title,
                        align: 'left',
                        style: {fontSize: '13px'},
                    },
                    dataLabels: {
                        enabled: true,
                        offsetY: -12,
                    },
                    tooltip: {
                        y: {
                            formatter: function (value) {
                                return value + ' registros';
                            }
                        }
                    },
                    grid: {
                        borderColor: '#eef1f4',
                    },
                });

                chart.render();
            };

            const buildHorizontalChart = function (selector, title, values, color) {
                const container = document.querySelector(selector);
                if (!container) {
                    return;
                }

                const categories = values.map(function (item) {
                    const raw = (item && item.valor ? String(item.valor) : '').trim();
                    return raw !== '' ? raw.toUpperCase() : 'SIN DATO';
                });
                const counts = values.map(function (item) {
                    const qty = Number(item && item.cantidad ? item.cantidad : 0);
                    return Number.isFinite(qty) ? qty : 0;
                });

                if (counts.length === 0) {
                    container.innerHTML = '<div class="text-muted text-center py-30">Sin datos para graficar.</div>';
                    return;
                }

                const dynamicHeight = Math.max(320, (counts.length * 28) + 90);
                container.style.minHeight = dynamicHeight + 'px';

                const chart = new ApexCharts(container, {
                    chart: {
                        type: 'bar',
                        height: dynamicHeight,
                        toolbar: {show: false},
                    },
                    series: [{
                        name: 'Cantidad',
                        data: counts,
                    }],
                    xaxis: {
                        categories: categories,
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 4,
                        },
                    },
                    colors: [color],
                    title: {
                        text: title,
                        align: 'left',
                        style: {fontSize: '13px'},
                    },
                    dataLabels: {
                        enabled: true,
                    },
                    tooltip: {
                        y: {
                            formatter: function (value) {
                                return value + ' registros';
                            }
                        }
                    },
                    grid: {
                        borderColor: '#eef1f4',
                    },
                });

                chart.render();
            };

            const tendenciaVolumenContainer = document.querySelector('#tendenciaVolumenChart');
            if (tendenciaVolumenContainer) {
                if (!Array.isArray(temporalTrendCounts) || temporalTrendCounts.length === 0) {
                    tendenciaVolumenContainer.innerHTML = '<div class="text-muted text-center py-30">Sin datos para graficar.</div>';
                } else {
                    const tendenciaVolumenChart = new ApexCharts(tendenciaVolumenContainer, {
                        chart: {
                            type: 'line',
                            height: 320,
                            toolbar: {show: false},
                        },
                        series: [{
                            name: 'Atenciones',
                            data: temporalTrendCounts.map(function (item) {
                                const value = Number(item);
                                return Number.isFinite(value) ? value : 0;
                            }),
                        }],
                        xaxis: {
                            categories: Array.isArray(temporalTrendLabels) ? temporalTrendLabels : [],
                        },
                        stroke: {
                            curve: 'smooth',
                            width: 3,
                        },
                        markers: {
                            size: 4,
                        },
                        colors: ['#2563eb'],
                        dataLabels: {
                            enabled: false,
                        },
                        grid: {
                            borderColor: '#eef1f4',
                        },
                    });

                    tendenciaVolumenChart.render();
                }
            }

            const tendenciaProduccionContainer = document.querySelector('#tendenciaProduccionChart');
            if (tendenciaProduccionContainer) {
                if (!Array.isArray(economicoTrendTotals) || economicoTrendTotals.length === 0) {
                    tendenciaProduccionContainer.innerHTML = '<div class="text-muted text-center py-30">Sin datos de producción para graficar.</div>';
                } else {
                    const tendenciaProduccionChart = new ApexCharts(tendenciaProduccionContainer, {
                        chart: {
                            type: 'area',
                            height: 320,
                            toolbar: {show: false},
                        },
                        series: [{
                            name: 'Producción USD',
                            data: economicoTrendTotals.map(function (item) {
                                const value = Number(item);
                                return Number.isFinite(value) ? Number(value.toFixed(2)) : 0;
                            }),
                        }],
                        xaxis: {
                            categories: Array.isArray(economicoTrendLabels) ? economicoTrendLabels : [],
                        },
                        yaxis: {
                            labels: {
                                formatter: function (value) {
                                    return '$' + Number(value || 0).toFixed(0);
                                }
                            }
                        },
                        stroke: {
                            curve: 'smooth',
                            width: 3,
                        },
                        markers: {
                            size: 4,
                        },
                        colors: ['#16a34a'],
                        dataLabels: {
                            enabled: false,
                        },
                        tooltip: {
                            y: {
                                formatter: function (value) {
                                    return '$' + Number(value || 0).toFixed(2);
                                },
                            },
                        },
                        grid: {
                            borderColor: '#eef1f4',
                        },
                    });

                    tendenciaProduccionChart.render();
                }
            }

            buildHorizontalChart(
                '#topProcedimientosVolumenChart',
                'Top 10 procedimientos por volumen',
                Array.isArray(topProcedimientosVolumen) ? topProcedimientosVolumen : [],
                '#0ea5e9'
            );

            buildHorizontalChart('#desgloseSedeChart', 'Participación por sede', Array.isArray(desgloseSedes) ? desgloseSedes : [], '#0891b2');
            buildHorizontalChart('#desgloseDoctorChart', 'Participación por médico', Array.isArray(desgloseDoctores) ? desgloseDoctores : [], '#3b82f6');

            buildVerticalChart('#picosDiasChart', 'Atenciones por día', Array.isArray(picosDias) ? picosDias : [], '#8b5cf6');

            const mixCategoriaContainer = document.querySelector('#mixCategoriaClienteChart');
            if (mixCategoriaContainer) {
                const mixSeries = [
                    Number.isFinite(Number(particularCount)) ? Number(particularCount) : 0,
                    Number.isFinite(Number(privadoCount)) ? Number(privadoCount) : 0,
                ];
                const mixTotal = mixSeries.reduce(function (acc, item) {
                    return acc + item;
                }, 0);

                if (mixTotal === 0) {
                    mixCategoriaContainer.innerHTML = '<div class="text-muted text-center py-30">Sin datos para graficar.</div>';
                } else {
                    const mixChart = new ApexCharts(mixCategoriaContainer, {
                        chart: {
                            type: 'pie',
                            height: 320,
                        },
                        labels: ['PARTICULAR', 'PRIVADO'],
                        series: mixSeries,
                        colors: ['#22c55e', '#ef4444'],
                        legend: {
                            position: 'bottom',
                        },
                        dataLabels: {
                            enabled: true,
                            formatter: function (value) {
                                return value.toFixed(1) + '%';
                            },
                        },
                        tooltip: {
                            y: {
                                formatter: function (value) {
                                    const percent = mixTotal > 0 ? ((value / mixTotal) * 100).toFixed(2) : '0.00';
                                    return value + ' registros (' + percent + '%)';
                                },
                            },
                        },
                    });

                    mixChart.render();
                }
            }

            const topAfiliacionesContainer = document.querySelector('#topAfiliacionesChart');
            if (topAfiliacionesContainer) {
                const afiliacionesRows = Array.isArray(topAfiliaciones) ? topAfiliaciones : [];
                const afiliacionesSeries = afiliacionesRows.map(function (item) {
                    const qty = Number(item && item.cantidad ? item.cantidad : 0);
                    return Number.isFinite(qty) ? qty : 0;
                });
                const afiliacionesLabels = afiliacionesRows.map(function (item) {
                    const raw = String(item && item.afiliacion ? item.afiliacion : 'SIN AFILIACION').toUpperCase();
                    return raw.length > 24 ? raw.slice(0, 24) + '...' : raw;
                });

                if (afiliacionesSeries.length === 0) {
                    topAfiliacionesContainer.innerHTML = '<div class="text-muted text-center py-30">Sin datos para graficar.</div>';
                } else {
                    const topAfiliacionesChart = new ApexCharts(topAfiliacionesContainer, {
                        chart: {
                            type: 'polarArea',
                            height: 320,
                        },
                        labels: afiliacionesLabels,
                        series: afiliacionesSeries,
                        colors: ['#3b82f6', '#0ea5e9', '#38bdf8', '#60a5fa', '#93c5fd', '#c4b5fd'],
                        stroke: {
                            colors: ['#ffffff'],
                        },
                        fill: {
                            opacity: 0.9,
                        },
                        legend: {
                            position: 'bottom',
                        },
                        yaxis: {
                            show: false,
                        },
                        tooltip: {
                            y: {
                                formatter: function (value) {
                                    return value + ' registros';
                                },
                            },
                        },
                    });

                    topAfiliacionesChart.render();
                }
            }

            buildVerticalChart(
                '#referidoPrefacturaChart',
                'Categorías madre (con valor: ' + referidoWithValue + ', sin valor: ' + referidoWithoutValue + ')',
                referidoValues,
                '#3b82f6'
            );

            buildVerticalChart(
                '#referidoPrefacturaPacientesUnicosChart',
                'Categorías madre por pacientes únicos (con valor: ' + referidoUniquePatientsWithValue + ', sin valor: ' + referidoUniquePatientsWithoutValue + ')',
                referidoUniquePatientValues,
                '#1d4ed8'
            );

            buildVerticalChart(
                '#referidoPrefacturaNuevoPacienteChart',
                'Categorías madre en consulta oftalmológica nuevo paciente (con valor: ' + referidoNuevoPacienteWithValue + ', sin valor: ' + referidoNuevoPacienteWithoutValue + ')',
                referidoNuevoPacienteValues,
                '#2563eb'
            );

            const hierarchyDonutContainer = document.querySelector('#hierarquiaCategoriasChart');
            const hierarchySelect = document.querySelector('#hierarquiaCategoriaSelect');
            const hierarchySubContainer = document.querySelector('#hierarquiaSubcategoriasChart');

            let hierarchyDonutChart = null;
            let hierarchySubChart = null;

            if (hierarchyDonutContainer) {
                const labels = hierarquiaCategorias.map(function (item) {
                    return String(item && item.categoria ? item.categoria : 'SIN CATEGORIA').toUpperCase();
                });
                const series = hierarquiaCategorias.map(function (item) {
                    const qty = Number(item && item.cantidad ? item.cantidad : 0);
                    return Number.isFinite(qty) ? qty : 0;
                });

                if (series.length === 0) {
                    hierarchyDonutContainer.innerHTML = '<div class="text-muted text-center py-30">Sin categorías para graficar.</div>';
                } else {
                    hierarchyDonutChart = new ApexCharts(hierarchyDonutContainer, {
                        chart: {
                            type: 'donut',
                            height: 320,
                        },
                        labels: labels,
                        series: series,
                        title: {
                            text: 'Participación de categorías madre',
                            align: 'left',
                            style: {fontSize: '13px'},
                        },
                        legend: {
                            position: 'bottom',
                        },
                        dataLabels: {
                            enabled: true,
                            formatter: function (val) {
                                return val.toFixed(1) + '%';
                            }
                        },
                        tooltip: {
                            y: {
                                formatter: function (value) {
                                    return value + ' registros';
                                }
                            }
                        }
                    });

                    hierarchyDonutChart.render();
                }
            }

            const renderHierarchySubcategories = function (selectedCategory) {
                if (!hierarchySubContainer) {
                    return;
                }

                const fallback = hierarquiaCategorias.length > 0 ? hierarquiaCategorias[0] : null;
                const selected = hierarquiaCategorias.find(function (item) {
                    return String(item && item.categoria ? item.categoria : '') === String(selectedCategory || '');
                }) || fallback;

                if (!selected || !Array.isArray(selected.subcategorias) || selected.subcategorias.length === 0) {
                    hierarchySubContainer.innerHTML = '<div class="text-muted text-center py-30">Sin subcategorías para graficar.</div>';
                    return;
                }

                const rows = selected.subcategorias;
                const categories = rows.map(function (item) {
                    return String(item && item.subcategoria ? item.subcategoria : 'SIN SUBCATEGORIA').toUpperCase();
                });
                const percents = rows.map(function (item) {
                    const pct = Number(item && item.porcentaje_en_categoria ? item.porcentaje_en_categoria : 0);
                    return Number.isFinite(pct) ? pct : 0;
                });

                const dynamicHeight = Math.max(320, (rows.length * 28) + 90);
                hierarchySubContainer.style.minHeight = dynamicHeight + 'px';

                if (hierarchySubChart) {
                    hierarchySubChart.destroy();
                    hierarchySubChart = null;
                }

                hierarchySubChart = new ApexCharts(hierarchySubContainer, {
                    chart: {
                        type: 'bar',
                        height: dynamicHeight,
                        toolbar: {show: false},
                    },
                    series: [{
                        name: '% en categoría',
                        data: percents,
                    }],
                    xaxis: {
                        categories: categories,
                        min: 0,
                        max: 100,
                        labels: {
                            formatter: function (value) {
                                return value + '%';
                            }
                        }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 4,
                        },
                    },
                    colors: ['#7c3aed'],
                    title: {
                        text: 'Subcategorías dentro de ' + String(selected.categoria || 'SIN CATEGORIA').toUpperCase(),
                        align: 'left',
                        style: {fontSize: '13px'},
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function (value) {
                            return value.toFixed(2) + '%';
                        }
                    },
                    tooltip: {
                        custom: function ({dataPointIndex}) {
                            const item = rows[dataPointIndex] || {};
                            const count = Number(item.cantidad || 0);
                            const pctInCategory = Number(item.porcentaje_en_categoria || 0);
                            const pctTotal = Number(item.porcentaje_total || 0);

                            return '<div class="px-10 py-5">' +
                                '<div><strong>' + String(item.subcategoria || 'SIN SUBCATEGORIA').toUpperCase() + '</strong></div>' +
                                '<div>Cantidad: ' + count + '</div>' +
                                '<div>% en categoría: ' + pctInCategory.toFixed(2) + '%</div>' +
                                '<div>% del total: ' + pctTotal.toFixed(2) + '%</div>' +
                                '</div>';
                        }
                    },
                    grid: {
                        borderColor: '#eef1f4',
                    },
                });

                hierarchySubChart.render();
            };

            if (hierarchySelect) {
                hierarchySelect.addEventListener('change', function (event) {
                    renderHierarchySubcategories(event.target.value);
                });
                renderHierarchySubcategories(hierarchySelect.value);
            } else {
                renderHierarchySubcategories('');
            }

            const initDataTable = function (selector, options) {
                if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.DataTable !== 'function') {
                    return;
                }

                const $table = window.jQuery(selector);
                if (!$table.length) {
                    return;
                }

                if (window.jQuery.fn.dataTable.isDataTable($table)) {
                    $table.DataTable().destroy();
                }

                $table.DataTable(Object.assign({
                    language: {url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'},
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                    deferRender: true,
                }, options || {}));
            };

            initDataTable('#tablaAtencionesRango', {
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[8, 'desc']],
            });

        })();
    </script>
@endpush
