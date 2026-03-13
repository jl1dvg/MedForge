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
            'total_honorario_real' => 0,
            'ticket_promedio_honorario' => 0,
            'produccion_promedio_por_atencion' => 0,
            'atenciones_facturadas' => 0,
            'atenciones_con_honorario' => 0,
            'atenciones_no_facturadas' => 0,
            'facturacion_rate' => 0,
            'cobertura_honorario_rate' => 0,
            'procedimientos_facturados' => 0,
            'facturas_emitidas' => 0,
            'produccion_por_categoria' => ['particular' => 0, 'privado' => 0],
            'honorario_por_categoria' => ['particular' => 0, 'privado' => 0],
            'formas_pago' => ['values' => []],
            'doctores_top' => [],
            'areas_top' => [],
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
                    Última
                    actualización: {{ now()->setTimezone(config('app.timezone', 'America/Guayaquil'))->format('d/m/Y H:i') }}
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
            $totalHonorarioReal = (float) ($economico['total_honorario_real'] ?? $produccionTotal);
            $ticketPromedioHonorario = (float) ($economico['ticket_promedio_honorario'] ?? $economico['produccion_promedio_por_atencion'] ?? 0);
            $produccionPromedioAtencion = (float) ($economico['produccion_promedio_por_atencion'] ?? 0);
            $atencionesFacturadas = (int) ($economico['atenciones_facturadas'] ?? 0);
            $atencionesConHonorario = (int) ($economico['atenciones_con_honorario'] ?? 0);
            $atencionesNoFacturadas = (int) ($economico['atenciones_no_facturadas'] ?? 0);
            $facturacionRate = (float) ($economico['facturacion_rate'] ?? 0);
            $coberturaHonorarioRate = (float) ($economico['cobertura_honorario_rate'] ?? 0);
            $procedimientosFacturados = (int) ($economico['procedimientos_facturados'] ?? 0);
            $facturasEmitidas = (int) ($economico['facturas_emitidas'] ?? 0);
            $honorarioPorCategoria = is_array($economico['honorario_por_categoria'] ?? null) ? $economico['honorario_por_categoria'] : (is_array($economico['produccion_por_categoria'] ?? null) ? $economico['produccion_por_categoria'] : ['particular' => 0, 'privado' => 0]);
            $honorarioParticular = (float) ($honorarioPorCategoria['particular'] ?? 0);
            $honorarioPrivado = (float) ($honorarioPorCategoria['privado'] ?? 0);
            $formasPagoSummary = is_array($economico['formas_pago'] ?? null) ? $economico['formas_pago'] : ['values' => []];
            $formasPagoValues = is_array($formasPagoSummary['values'] ?? null) ? $formasPagoSummary['values'] : [];
            $doctoresHonorarioTop = is_array($economico['doctores_top'] ?? null) ? $economico['doctores_top'] : [];
            $areasTop = is_array($economico['areas_top'] ?? null) ? $economico['areas_top'] : [];
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
                        <h6 class="mb-5">Atenciones Particulares</h6>
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
                        <h5 class="box-title mb-0">Categoría cliente: volumen + honorario</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <span
                                class="badge bg-primary-light text-primary">{{ $atencionesFacturadas }} con factura</span>
                            <span
                                class="badge bg-warning-light text-warning">{{ $atencionesNoFacturadas }} sin factura</span>
                        </div>
                    </div>
                    <div class="box-body">
                        <div id="categoriaClienteResumenChart" style="min-height: 320px;"></div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 mt-15">
                                <thead class="table-light">
                                <tr>
                                    <th>Categoría</th>
                                    <th class="text-end">Atenciones</th>
                                    <th class="text-end">% mix</th>
                                    <th class="text-end">Honorario real</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td>PARTICULAR</td>
                                    <td class="text-end">{{ $particularCount }}</td>
                                    <td class="text-end">{{ number_format($particularShare, 2) }}%</td>
                                    <td class="text-end">${{ number_format($honorarioParticular, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>PRIVADO</td>
                                    <td class="text-end">{{ $privadoCount }}</td>
                                    <td class="text-end">{{ number_format($privadoShare, 2) }}%</td>
                                    <td class="text-end">${{ number_format($honorarioPrivado, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-600">TOTAL</td>
                                    <td class="text-end fw-700">{{ $totalAtenciones }}</td>
                                    <td class="text-end fw-700">100.00%</td>
                                    <td class="text-end fw-700">${{ number_format($totalHonorarioReal, 2) }}</td>
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
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Top procedimientos por volumen</h5>
                        <span
                            class="badge bg-primary-light text-primary">{{ count($topProcedimientosVolumen) }} valores</span>
                    </div>
                    <div class="box-body">
                        <div id="topProcedimientosVolumenChart" style="min-height: 260px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Médicos: volumen + honorario</h5>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge bg-success-light text-success">${{ number_format($totalHonorarioReal, 2) }} honorario total</span>
                            <div class="btn-group btn-group-sm" role="group" aria-label="Modo médico">
                                <button type="button" class="btn btn-outline-primary active" data-doctor-mode="ambos">
                                    Ambos
                                </button>
                                <button type="button" class="btn btn-outline-primary" data-doctor-mode="volumen">
                                    Volumen
                                </button>
                                <button type="button" class="btn btn-outline-primary" data-doctor-mode="honorario">
                                    Honorario
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="box-body">
                        <div id="doctorPerformanceChart" style="min-height: 360px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Formas de pago (Top)</h5>
                        <span class="badge bg-success-light text-success">{{ count($formasPagoValues) }} valores</span>
                    </div>
                    <div class="box-body">
                        <div id="formasPagoChart" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Áreas con mayor honorario real</h5>
                        <span class="badge bg-info-light text-info">{{ count($areasTop) }} valores</span>
                    </div>
                    <div class="box-body">
                        <div id="areasHonorarioChart" style="min-height: 320px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-2 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Honorario real acumulado</h6>
                        <div class="fs-28 fw-700 text-success">${{ number_format($totalHonorarioReal, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Ticket promedio honorario</h6>
                        <div class="fs-28 fw-700 text-primary">${{ number_format($ticketPromedioHonorario, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Atenciones con factura</h6>
                        <div class="fs-28 fw-700 text-info">{{ $atencionesFacturadas }}</div>
                        <small class="text-muted">{{ number_format($facturacionRate, 2) }}% con factura
                            registrada</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Facturas emitidas</h6>
                        <div class="fs-28 fw-700 text-dark">{{ $facturasEmitidas }}</div>
                        <small class="text-muted">{{ $procedimientosFacturados }} registros económicos asociados</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Atenciones con valor real</h6>
                        <div class="fs-28 fw-700 text-warning">{{ $atencionesConHonorario }}</div>
                        <small class="text-muted">{{ number_format($coberturaHonorarioRate, 2) }}% con
                            monto_honorario</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Procedimientos facturados</h6>
                        <div class="fs-28 fw-700 text-secondary">{{ $procedimientosFacturados }}</div>
                        <small class="text-muted">${{ number_format($produccionPromedioAtencion, 2) }} promedio por
                            atención total</small>
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
                                    <th>Honorario real</th>
                                    <th>Fecha fact.</th>
                                    <th>Factura</th>
                                    <th>Forma pago</th>
                                    <th>Cliente</th>
                                    <th>Área</th>
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
                                        $fechaFacturacion = trim((string) ($row['fecha_facturacion'] ?? ''));
                                        $fechaFacturacionFmt = $fechaFacturacion !== '' && strtotime($fechaFacturacion) !== false ? date('d/m/Y', strtotime($fechaFacturacion)) : '—';
                                        $facturado = (bool) ($row['facturado'] ?? false);
                                        $honorarioRealRow = (float) ($row['monto_honorario_real'] ?? $row['total_produccion'] ?? 0);
                                        $facturaRef = trim((string) ($row['numero_factura'] ?? ''));
                                        if ($facturaRef === '') {
                                            $facturaRef = trim((string) ($row['factura_id'] ?? ''));
                                        }
                                        $formasPagoRow = trim((string) ($row['formas_pago'] ?? ''));
                                        $clienteFacturacionRow = trim((string) ($row['cliente_facturacion'] ?? ''));
                                        $areaFacturacionRow = trim((string) ($row['area_facturacion'] ?? ''));
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
                                        <td class="text-end">${{ number_format($honorarioRealRow, 2) }}</td>
                                        <td>{{ $fechaFacturacionFmt }}</td>
                                        <td>{{ $facturaRef !== '' ? $facturaRef : '—' }}</td>
                                        <td>{{ $formasPagoRow !== '' ? strtoupper($formasPagoRow) : '—' }}</td>
                                        <td>{{ $clienteFacturacionRow !== '' ? strtoupper($clienteFacturacionRow) : '—' }}</td>
                                        <td>{{ $areaFacturacionRow !== '' ? strtoupper($areaFacturacionRow) : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="18" class="text-center text-muted py-30">No hay atenciones
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
            const topProcedimientosVolumen = @json($topProcedimientosVolumen);
            const desgloseSedes = @json($desgloseSedes);
            const desgloseDoctores = @json($desgloseDoctores);
            const doctoresHonorarioTop = @json($doctoresHonorarioTop);
            const formasPagoValues = @json($formasPagoValues);
            const areasTop = @json($areasTop);
            const picosDias = @json($picosDias);

            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const truncateLabel = function (value, maxLength) {
                const text = String(value || '').trim();
                if (text === '') {
                    return 'SIN DATO';
                }

                return text.length > maxLength ? text.slice(0, maxLength) + '...' : text;
            };

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

            const buildHorizontalPercentageChart = function (selector, values, config) {
                const container = document.querySelector(selector);
                if (!container) {
                    return;
                }

                const rows = (Array.isArray(values) ? values : []).map(function (item) {
                    const label = String(item && item.valor ? item.valor : 'SIN DATO').trim().toUpperCase() || 'SIN DATO';
                    const count = Number(item && item.cantidad ? item.cantidad : 0);
                    const percent = Number(item && item.porcentaje ? item.porcentaje : 0);

                    return {
                        label: label,
                        count: Number.isFinite(count) ? count : 0,
                        percent: Number.isFinite(percent) ? Number(percent.toFixed(2)) : 0,
                    };
                }).filter(function (item) {
                    return item.count > 0;
                });

                if (rows.length === 0) {
                    container.innerHTML = '<div class="text-muted text-center py-30">Sin datos para graficar.</div>';
                    return;
                }

                const dynamicHeight = Math.max(320, (rows.length * 42) + 70);
                container.style.minHeight = dynamicHeight + 'px';

                const chart = new ApexCharts(container, {
                    chart: {
                        type: 'bar',
                        height: dynamicHeight,
                        toolbar: {show: false},
                    },
                    series: [{
                        name: config && config.seriesName ? config.seriesName : 'Cantidad',
                        data: rows.map(function (item) {
                            return item.count;
                        }),
                    }],
                    xaxis: {
                        categories: rows.map(function (item) {
                            return truncateLabel(item.label, 34);
                        }),
                        title: {
                            text: config && config.xTitle ? config.xTitle : '',
                        },
                    },
                    yaxis: {
                        labels: {
                            maxWidth: 260,
                        },
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 5,
                            barHeight: '68%',
                            distributed: false,
                        },
                    },
                    colors: [config && config.color ? config.color : '#16a34a'],
                    dataLabels: {
                        enabled: true,
                        textAnchor: 'start',
                        offsetX: 6,
                        formatter: function (value, opts) {
                            const row = rows[opts.dataPointIndex] || {percent: 0};
                            return value + ' (' + row.percent.toFixed(2) + '%)';
                        },
                        style: {
                            fontSize: '11px',
                            fontWeight: 600,
                            colors: ['#334155'],
                        },
                    },
                    tooltip: {
                        y: {
                            formatter: function (value, opts) {
                                const row = rows[opts.dataPointIndex] || {percent: 0};
                                return value + ' atenciones | ' + row.percent.toFixed(2) + '%';
                            },
                        },
                    },
                    grid: {
                        borderColor: '#eef1f4',
                    },
                });

                chart.render();
            };

            const buildHorizontalMoneyChart = function (selector, values, config) {
                const container = document.querySelector(selector);
                if (!container) {
                    return;
                }

                const rows = (Array.isArray(values) ? values : []).map(function (item) {
                    const label = String(item && item.valor ? item.valor : 'SIN DATO').trim().toUpperCase() || 'SIN DATO';
                    const amount = Number(item && item.monto ? item.monto : 0);
                    const percent = Number(item && item.porcentaje ? item.porcentaje : 0);

                    return {
                        label: label,
                        amount: Number.isFinite(amount) ? Number(amount.toFixed(2)) : 0,
                        percent: Number.isFinite(percent) ? Number(percent.toFixed(2)) : 0,
                    };
                }).filter(function (item) {
                    return item.amount > 0;
                });

                if (rows.length === 0) {
                    container.innerHTML = '<div class="text-muted text-center py-30">Sin datos para graficar.</div>';
                    return;
                }

                const dynamicHeight = Math.max(320, (rows.length * 42) + 70);
                container.style.minHeight = dynamicHeight + 'px';

                const chart = new ApexCharts(container, {
                    chart: {
                        type: 'bar',
                        height: dynamicHeight,
                        toolbar: {show: false},
                    },
                    series: [{
                        name: config && config.seriesName ? config.seriesName : 'Honorario real',
                        data: rows.map(function (item) {
                            return item.amount;
                        }),
                    }],
                    xaxis: {
                        categories: rows.map(function (item) {
                            return truncateLabel(item.label, 32);
                        }),
                        labels: {
                            formatter: function (value) {
                                return '$' + Number(value || 0).toFixed(0);
                            },
                        },
                        title: {
                            text: config && config.xTitle ? config.xTitle : '',
                        },
                    },
                    yaxis: {
                        labels: {
                            maxWidth: 260,
                        },
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 5,
                            barHeight: '68%',
                        },
                    },
                    colors: [config && config.color ? config.color : '#0f766e'],
                    dataLabels: {
                        enabled: true,
                        textAnchor: 'start',
                        offsetX: 6,
                        formatter: function (value, opts) {
                            const row = rows[opts.dataPointIndex] || {percent: 0};
                            return '$' + Number(value || 0).toFixed(2) + ' (' + row.percent.toFixed(2) + '%)';
                        },
                        style: {
                            fontSize: '11px',
                            fontWeight: 600,
                            colors: ['#334155'],
                        },
                    },
                    tooltip: {
                        y: {
                            formatter: function (value, opts) {
                                const row = rows[opts.dataPointIndex] || {percent: 0};
                                return '$' + Number(value || 0).toFixed(2) + ' | ' + row.percent.toFixed(2) + '% del honorario';
                            },
                        },
                    },
                    grid: {
                        borderColor: '#eef1f4',
                    },
                });

                chart.render();
            };

            const buildDoctorPerformanceRows = function () {
                const order = [];
                const countsMap = new Map();
                const moneyMap = new Map();

                (Array.isArray(doctoresHonorarioTop) ? doctoresHonorarioTop : []).forEach(function (item) {
                    const label = String(item && item.valor ? item.valor : 'SIN DOCTOR').trim().toUpperCase() || 'SIN DOCTOR';
                    if (!order.includes(label)) {
                        order.push(label);
                    }
                    const amount = Number(item && item.monto ? item.monto : 0);
                    const percent = Number(item && item.porcentaje ? item.porcentaje : 0);
                    moneyMap.set(label, {
                        amount: Number.isFinite(amount) ? Number(amount.toFixed(2)) : 0,
                        percent: Number.isFinite(percent) ? Number(percent.toFixed(2)) : 0,
                    });
                });

                (Array.isArray(desgloseDoctores) ? desgloseDoctores : []).forEach(function (item) {
                    const label = String(item && item.valor ? item.valor : 'SIN DOCTOR').trim().toUpperCase() || 'SIN DOCTOR';
                    if (!order.includes(label)) {
                        order.push(label);
                    }
                    const count = Number(item && item.cantidad ? item.cantidad : 0);
                    const percent = Number(item && item.porcentaje ? item.porcentaje : 0);
                    countsMap.set(label, {
                        count: Number.isFinite(count) ? count : 0,
                        percent: Number.isFinite(percent) ? Number(percent.toFixed(2)) : 0,
                    });
                });

                return order.slice(0, 10).map(function (label) {
                    const countMeta = countsMap.get(label) || {count: 0, percent: 0};
                    const moneyMeta = moneyMap.get(label) || {amount: 0, percent: 0};

                    return {
                        label: label,
                        count: countMeta.count,
                        countPercent: countMeta.percent,
                        amount: moneyMeta.amount,
                        amountPercent: moneyMeta.percent,
                    };
                });
            };

            const doctorChartContainer = document.querySelector('#doctorPerformanceChart');
            const doctorModeButtons = Array.from(document.querySelectorAll('[data-doctor-mode]'));
            const doctorPerformanceRows = buildDoctorPerformanceRows();
            let doctorPerformanceChart = null;

            const renderDoctorPerformanceChart = function (mode) {
                if (!doctorChartContainer) {
                    return;
                }

                if (!Array.isArray(doctorPerformanceRows) || doctorPerformanceRows.length === 0) {
                    doctorChartContainer.innerHTML = '<div class="text-muted text-center py-30">Sin datos de médicos para graficar.</div>';
                    return;
                }

                const categories = doctorPerformanceRows.map(function (item) {
                    const text = String(item.label || 'SIN DOCTOR');
                    return text.length > 18 ? text.slice(0, 18) + '...' : text;
                });
                const counts = doctorPerformanceRows.map(function (item) {
                    return Number(item.count || 0);
                });
                const amounts = doctorPerformanceRows.map(function (item) {
                    return Number(item.amount || 0);
                });

                if (doctorPerformanceChart) {
                    doctorPerformanceChart.destroy();
                    doctorPerformanceChart = null;
                }

                const options = {
                    chart: {
                        height: 360,
                        toolbar: {show: false},
                    },
                    xaxis: {
                        categories: categories,
                        labels: {
                            rotate: -35,
                            hideOverlappingLabels: false,
                            trim: true,
                        },
                    },
                    grid: {
                        borderColor: '#eef1f4',
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        shared: true,
                        intersect: false,
                    },
                };

                if (mode === 'volumen') {
                    doctorPerformanceChart = new ApexCharts(doctorChartContainer, Object.assign({}, options, {
                        chart: Object.assign({}, options.chart, {type: 'bar'}),
                        series: [{
                            name: 'Atenciones',
                            data: counts,
                        }],
                        colors: ['#2563eb'],
                        yaxis: {
                            title: {text: 'Atenciones'},
                        },
                        plotOptions: {
                            bar: {
                                borderRadius: 4,
                                columnWidth: '55%',
                            },
                        },
                    }));
                } else if (mode === 'honorario') {
                    doctorPerformanceChart = new ApexCharts(doctorChartContainer, Object.assign({}, options, {
                        chart: Object.assign({}, options.chart, {type: 'bar'}),
                        series: [{
                            name: 'Honorario real',
                            data: amounts,
                        }],
                        colors: ['#0f766e'],
                        yaxis: {
                            title: {text: 'Honorario real'},
                            labels: {
                                formatter: function (value) {
                                    return '$' + Number(value || 0).toFixed(0);
                                }
                            }
                        },
                        plotOptions: {
                            bar: {
                                borderRadius: 4,
                                columnWidth: '55%',
                            },
                        },
                        tooltip: {
                            y: {
                                formatter: function (value) {
                                    return '$' + Number(value || 0).toFixed(2);
                                }
                            }
                        },
                    }));
                } else {
                    doctorPerformanceChart = new ApexCharts(doctorChartContainer, Object.assign({}, options, {
                        chart: Object.assign({}, options.chart, {type: 'line'}),
                        series: [{
                            name: 'Atenciones',
                            type: 'column',
                            data: counts,
                        }, {
                            name: 'Honorario real',
                            type: 'line',
                            data: amounts,
                        }],
                        colors: ['#2563eb', '#0f766e'],
                        stroke: {
                            width: [0, 3],
                            curve: 'smooth',
                        },
                        markers: {
                            size: [0, 4],
                        },
                        plotOptions: {
                            bar: {
                                borderRadius: 4,
                                columnWidth: '52%',
                            },
                        },
                        yaxis: [{
                            title: {text: 'Atenciones'},
                        }, {
                            opposite: true,
                            title: {text: 'Honorario real'},
                            labels: {
                                formatter: function (value) {
                                    return '$' + Number(value || 0).toFixed(0);
                                }
                            }
                        }],
                        tooltip: {
                            shared: true,
                            intersect: false,
                            y: [{
                                formatter: function (value) {
                                    return Number(value || 0) + ' atenciones';
                                }
                            }, {
                                formatter: function (value) {
                                    return '$' + Number(value || 0).toFixed(2);
                                }
                            }]
                        },
                    }));
                }

                doctorPerformanceChart.render();
            };

            doctorModeButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    doctorModeButtons.forEach(function (item) {
                        item.classList.remove('active');
                    });
                    button.classList.add('active');
                    renderDoctorPerformanceChart(String(button.getAttribute('data-doctor-mode') || 'ambos'));
                });
            });

            renderDoctorPerformanceChart('ambos');

            const categoriaClienteResumenContainer = document.querySelector('#categoriaClienteResumenChart');
            if (categoriaClienteResumenContainer) {
                const categoryCounts = [
                    Number.isFinite(Number(particularCount)) ? Number(particularCount) : 0,
                    Number.isFinite(Number(privadoCount)) ? Number(privadoCount) : 0,
                ];
                const categoryHonorarios = [
                    {{ json_encode(round($honorarioParticular, 2)) }},
                    {{ json_encode(round($honorarioPrivado, 2)) }},
                ].map(function (item) {
                    const value = Number(item);
                    return Number.isFinite(value) ? Number(value.toFixed(2)) : 0;
                });

                if ((categoryCounts[0] + categoryCounts[1]) === 0) {
                    categoriaClienteResumenContainer.innerHTML = '<div class="text-muted text-center py-30">Sin datos de categoría para graficar.</div>';
                } else {
                    const categoriaClienteResumenChart = new ApexCharts(categoriaClienteResumenContainer, {
                        chart: {
                            type: 'line',
                            height: 320,
                            toolbar: {show: false},
                        },
                        series: [{
                            name: 'Atenciones',
                            type: 'column',
                            data: categoryCounts,
                        }, {
                            name: 'Honorario real',
                            type: 'line',
                            data: categoryHonorarios,
                        }],
                        xaxis: {
                            categories: ['PARTICULAR', 'PRIVADO'],
                        },
                        colors: ['#2563eb', '#0f766e'],
                        stroke: {
                            width: [0, 3],
                            curve: 'smooth',
                        },
                        markers: {
                            size: [0, 4],
                        },
                        plotOptions: {
                            bar: {
                                borderRadius: 4,
                                columnWidth: '45%',
                            },
                        },
                        yaxis: [{
                            title: {text: 'Atenciones'},
                        }, {
                            opposite: true,
                            title: {text: 'Honorario real'},
                            labels: {
                                formatter: function (value) {
                                    return '$' + Number(value || 0).toFixed(0);
                                }
                            }
                        }],
                        tooltip: {
                            shared: true,
                            intersect: false,
                            y: [{
                                formatter: function (value) {
                                    return Number(value || 0) + ' atenciones';
                                }
                            }, {
                                formatter: function (value) {
                                    return '$' + Number(value || 0).toFixed(2);
                                }
                            }]
                        },
                        grid: {
                            borderColor: '#eef1f4',
                        },
                        dataLabels: {
                            enabled: false,
                        },
                        legend: {
                            position: 'top',
                        },
                    });

                    categoriaClienteResumenChart.render();
                }
            }

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

            buildHorizontalChart(
                '#topProcedimientosVolumenChart',
                'Top 10 procedimientos por volumen',
                Array.isArray(topProcedimientosVolumen) ? topProcedimientosVolumen : [],
                '#0ea5e9'
            );

            buildHorizontalChart('#desgloseSedeChart', 'Participación por sede', Array.isArray(desgloseSedes) ? desgloseSedes : [], '#0891b2');
            buildHorizontalPercentageChart('#formasPagoChart', formasPagoValues, {
                seriesName: 'Atenciones',
                xTitle: 'Atenciones',
                color: '#16a34a',
            });
            buildHorizontalMoneyChart('#areasHonorarioChart', areasTop, {
                seriesName: 'Honorario real',
                xTitle: 'Honorario real',
                color: '#f8d830',
            });

            buildVerticalChart('#picosDiasChart', 'Atenciones por día', Array.isArray(picosDias) ? picosDias : [], '#8b5cf6');

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
