@extends('layouts.medforge')

@php
    $filters = is_array($filters ?? null) ? $filters : [];
    $catalogos = is_array($catalogos ?? null) ? $catalogos : ['afiliaciones' => [], 'empresas_seguro' => [], 'tipos_atencion' => [], 'sedes' => [], 'categorias' => [], 'categorias_madre_referido' => []];
    $rows = is_array($rows ?? null) ? $rows : [];
    $summary = is_array($summary ?? null) ? $summary : [
        'total' => 0,
        'total_consultas' => 0,
        'total_protocolos' => 0,
        'objetivo_1' => ['segments' => [], 'alerts' => []],
        'economico' => [
            'total_produccion' => 0,
            'total_honorario_real' => 0,
            'total_por_cobrar_estimado' => 0,
            'total_perdida_estimada' => 0,
            'potencial_capturable' => 0,
            'ticket_promedio_honorario' => 0,
            'ticket_promedio_facturado_real' => 0,
            'ticket_promedio_pendiente' => 0,
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
        'operativo' => [
            'evaluadas' => 0,
            'realizadas' => 0,
            'facturadas' => 0,
            'pendientes_facturar' => 0,
            'perdidas' => 0,
            'sin_cierre' => 0,
            'realizacion_rate' => 0,
            'facturacion_sobre_realizadas_rate' => 0,
            'pendiente_sobre_realizadas_rate' => 0,
            'perdida_rate' => 0,
            'honorario_real' => 0,
            'por_cobrar_estimado' => 0,
            'perdida_estimada' => 0,
            'potencial_capturable' => 0,
            'ticket_facturado_real' => 0,
            'ticket_pendiente' => 0,
        ],
        'pacientes_unicos' => 0,
        'pacientes_unicos_realizados' => 0,
        'pacientes_unicos_realizados_categoria' => ['particular' => 0, 'privado' => 0],
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
    $empresaSeguroSeleccionada = trim((string) ($filters['empresa_seguro'] ?? ''));
    $afiliacionSeleccionada = strtolower(trim((string) ($filters['afiliacion'] ?? '')));
    $sedeSeleccionada = strtoupper(trim((string) ($filters['sede'] ?? '')));
    $categoriaClienteSeleccionada = strtolower(trim((string) ($filters['categoria_cliente'] ?? '')));
    $categoriaMadreReferidoSeleccionada = strtoupper(trim((string) ($filters['categoria_madre_referido'] ?? '')));
    $tipoSeleccionado = strtoupper(trim((string) ($filters['tipo'] ?? '')));
    $procedimientoSeleccionado = trim((string) ($filters['procedimiento'] ?? ''));
    $exportParticularesQuery = array_filter([
        'date_from' => $dateFromSeleccionado,
        'date_to' => $dateToSeleccionado,
        'empresa_seguro' => $empresaSeguroSeleccionada,
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
    $referidoStrategicQuery = array_filter([
        'date_from' => $dateFromSeleccionado,
        'date_to' => $dateToSeleccionado,
        'empresa_seguro' => $empresaSeguroSeleccionada,
        'categoria_cliente' => $categoriaClienteSeleccionada,
        'tipo' => $tipoSeleccionado,
        'sede' => $sedeSeleccionada,
        'afiliacion' => $afiliacionSeleccionada,
        'procedimiento' => $procedimientoSeleccionado,
    ], static fn($value): bool => trim((string) $value) !== '');
    $referidoStrategicUrl = static function (string $category) use ($referidoStrategicQuery): string {
        $query = $referidoStrategicQuery;
        $query['categoria_referido'] = strtoupper(trim($category));
        return '/v2/informes/particulares/referidos?' . http_build_query($query);
    };
    $detalleBloqueSeleccionado = strtolower(trim((string) request()->query('detalle_bloque', '')));
    $detalleSegmentoSeleccionado = strtolower(trim((string) request()->query('detalle_segmento', '')));
    if ($detalleBloqueSeleccionado === '' && trim((string) request()->query('cirugias_segmento', '')) !== '') {
        $detalleBloqueSeleccionado = 'cirugias';
        $detalleSegmentoSeleccionado = strtolower(trim((string) request()->query('cirugias_segmento', '')));
    }
    $detailSegmentUrl = static function (string $block = '', string $segment = ''): string {
        $query = request()->query();
        unset($query['cirugias_segmento'], $query['detalle_bloque'], $query['detalle_segmento']);
        if (trim($block) !== '' && trim($segment) !== '') {
            $query['detalle_bloque'] = trim($block);
            $query['detalle_segmento'] = trim($segment);
        }

        $queryString = http_build_query($query);
        return $queryString !== '' ? (url()->current() . '?' . $queryString) : url()->current();
    };
    $patientDetailsUrl = static function (?string $hcNumber): string {
        $hc = trim((string) $hcNumber);
        if ($hc === '') {
            return '#';
        }

        return '/v2/pacientes/detalles?hc_number=' . urlencode($hc);
    };
    $operationalAlertLabel = static function (?string $alert): string {
        $normalized = strtoupper(trim((string) $alert));

        return match ($normalized) {
            'SIN_CIERRE' => 'Sin evidencia operativa suficiente para cerrar el caso.',
            'AGENDA_DESACTUALIZADA' => 'Existe evidencia clínica, pero la agenda quedó sin actualizar.',
            'PENDIENTE_FACTURAR' => 'El caso tiene respaldo clínico, pero aún no tiene billing real.',
            'FACTURADA_SIN_PROTOCOLO_LOCAL' => 'Se facturó, pero no existe protocolo local asociado.',
            'ARCHIVOS_SIN_INFORME' => 'Hay archivos técnicos, pero falta informe.',
            'INFORMADA_SIN_ARCHIVOS_NAS' => 'Existe informe, pero no se encontraron archivos en NAS.',
            'FACTURADA_SIN_ARCHIVOS_NI_INFORME' => 'Se facturó sin archivos ni informe disponibles.',
            'FACTURADA_SIN_FECHA_ATENCION' => 'Se facturó, pero falta registrar la fecha real de atención.',
            'FACTURADA_SIN_HONORARIO' => 'Se facturó, pero el honorario real quedó en 0.',
            'ATENCION_POSTERIOR_A_FECHA_PROGRAMADA' => 'La fecha de atención registrada es posterior a la programada.',
            default => 'Sin observación operativa adicional.',
        };
    };

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

@push('styles')
    <style>
        .kpi-filter-card {
            position: relative;
            cursor: pointer;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
            border: 1px solid transparent;
        }

        .kpi-filter-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }

        .kpi-filter-card.kpi-filter-card-active {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12), 0 10px 24px rgba(15, 23, 42, 0.10);
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.05), rgba(255, 255, 255, 0.96));
        }

        .kpi-filter-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 2;
            display: flex;
            gap: 6px;
        }

        .kpi-filter-actions .btn {
            padding: 0.25rem 0.45rem;
            line-height: 1;
        }

        .kpi-filter-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 2;
        }

        .kpi-filter-body {
            padding-top: 24px;
        }

        .kpi-inline-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .detail-segment-modal .modal-content {
            border: 0;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 28px 60px rgba(15, 23, 42, 0.22);
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 18%);
        }

        .detail-segment-modal .modal-header {
            border-bottom: 1px solid rgba(148, 163, 184, 0.22);
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.12), transparent 34%),
                linear-gradient(135deg, rgba(15, 23, 42, 0.98), rgba(30, 41, 59, 0.94));
            color: #f8fafc;
            padding: 20px 24px;
        }

        .detail-segment-modal .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .detail-segment-modal .btn-close {
            filter: invert(1) grayscale(1) brightness(2);
            opacity: 0.85;
        }

        .detail-segment-modal-subtitle {
            display: block;
            margin-top: 4px;
            color: rgba(226, 232, 240, 0.84) !important;
        }

        .detail-segment-modal-body {
            padding: 22px 24px 18px;
            background:
                linear-gradient(180deg, rgba(248, 250, 252, 0.9), rgba(255, 255, 255, 1));
        }

        .detail-segment-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .detail-segment-metric {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(148, 163, 184, 0.14);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
            background: #fff;
        }

        .detail-segment-metric-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.72;
        }

        .detail-segment-metric-value {
            font-size: 20px;
            font-weight: 800;
            line-height: 1.1;
        }

        .detail-segment-metric-success {
            background: linear-gradient(180deg, rgba(34, 197, 94, 0.09), rgba(255, 255, 255, 0.98));
        }

        .detail-segment-metric-success .detail-segment-metric-value,
        .detail-segment-metric-success .detail-segment-metric-label {
            color: #166534;
        }

        .detail-segment-metric-warning {
            background: linear-gradient(180deg, rgba(245, 158, 11, 0.09), rgba(255, 255, 255, 0.98));
        }

        .detail-segment-metric-warning .detail-segment-metric-value,
        .detail-segment-metric-warning .detail-segment-metric-label {
            color: #b45309;
        }

        .detail-segment-metric-danger {
            background: linear-gradient(180deg, rgba(239, 68, 68, 0.09), rgba(255, 255, 255, 0.98));
        }

        .detail-segment-metric-danger .detail-segment-metric-value,
        .detail-segment-metric-danger .detail-segment-metric-label {
            color: #b91c1c;
        }

        .detail-segment-table-wrap {
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 18px;
            overflow: auto;
            background: linear-gradient(180deg, rgba(255, 255, 255, 1), rgba(248, 250, 252, 0.92));
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
        }

        .detail-segment-table {
            margin-bottom: 0;
        }

        .detail-segment-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            padding-top: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(241, 245, 249, 0.95);
            backdrop-filter: blur(8px);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #475569;
        }

        .detail-segment-table tbody tr {
            transition: background-color 0.16s ease, transform 0.16s ease;
        }

        .detail-segment-table tbody tr:hover {
            background: rgba(37, 99, 235, 0.035);
        }

        .detail-segment-table tbody td {
            padding-top: 12px;
            padding-bottom: 12px;
            vertical-align: middle;
            border-color: rgba(226, 232, 240, 0.75);
        }

        .detail-cell-stack {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 120px;
        }

        .detail-cell-main {
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }

        .detail-cell-sub {
            font-size: 11px;
            color: #64748b;
            line-height: 1.2;
        }

        .detail-cell-sub-strong {
            font-size: 11px;
            color: #475569;
            line-height: 1.3;
        }

        .detail-code-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 10px;
            background: #e2e8f0;
            color: #0f172a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .detail-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .detail-chip-slate {
            background: #e2e8f0;
            color: #334155;
        }

        .detail-chip-sky {
            background: rgba(14, 165, 233, 0.12);
            color: #0369a1;
        }

        .detail-chip-amber {
            background: rgba(245, 158, 11, 0.13);
            color: #b45309;
        }

        .detail-chip-emerald {
            background: rgba(16, 185, 129, 0.12);
            color: #047857;
        }

        .detail-chip-rose {
            background: rgba(244, 63, 94, 0.11);
            color: #be123c;
        }

        .detail-chip-indigo {
            background: rgba(99, 102, 241, 0.12);
            color: #4338ca;
        }

        .detail-amount {
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .detail-amount-neutral {
            color: #0f172a;
        }

        .detail-amount-warning {
            color: #b45309;
        }

        .detail-amount-danger {
            color: #b91c1c;
        }

        .detail-patient-link {
            color: #2563eb;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.16s ease, opacity 0.16s ease;
        }

        .detail-patient-link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        .detail-segment-empty {
            padding: 28px 16px !important;
            color: #64748b;
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.9), rgba(255, 255, 255, 1));
        }

        @media (max-width: 991.98px) {
            .detail-segment-metrics {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

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
            <div class="box-header with-border d-flex justify-content-between align-items-center">
                <h4 class="box-title mb-0">Filtros</h4>
                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <a href="{{ $exportParticularesPdfUrl }}"
                       class="btn btn-outline-danger btn-sm">
                        <i class="mdi mdi-file-pdf-box me-1"></i> Descargar PDF
                    </a>
                    <a href="{{ $exportParticularesUrl }}"
                       class="btn btn-outline-success btn-sm">
                        <i class="mdi mdi-file-excel-box me-1"></i> Descargar Excel
                    </a>
                </div>
            </div>
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
                        <label for="empresa_seguro" class="form-label">Empresa de seguro</label>
                        <select name="empresa_seguro" id="empresa_seguro" class="form-select">
                            <option value="">Todas</option>
                            @foreach(($catalogos['empresas_seguro'] ?? []) as $empresaSeguro)
                                @php
                                    $empresaSeguroValue = trim((string) ($empresaSeguro['value'] ?? ''));
                                    $empresaSeguroLabel = trim((string) ($empresaSeguro['label'] ?? $empresaSeguroValue));
                                @endphp
                                <option
                                    value="{{ $empresaSeguroValue }}" {{ $empresaSeguroSeleccionada === $empresaSeguroValue ? 'selected' : '' }}>
                                    {{ strtoupper($empresaSeguroLabel) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="afiliacion" class="form-label">Seguro / plan</label>
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
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="mdi mdi-filter-variant"></i> Aplicar filtros
                        </button>
                        <a href="/v2/imagenes/particulares" class="btn btn-outline-secondary btn-sm">
                            <i class="mdi mdi-close-circle-outline"></i> Limpiar
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
            $operativo = is_array($summary['operativo'] ?? null) ? $summary['operativo'] : [];
            $operativoEvaluadas = (int) ($operativo['evaluadas'] ?? $totalAtenciones);
            $operativoRealizadas = (int) ($operativo['realizadas'] ?? 0);
            $operativoFacturadas = (int) ($operativo['facturadas'] ?? 0);
            $operativoPendientesFacturar = (int) ($operativo['pendientes_facturar'] ?? 0);
            $operativoPerdidas = (int) ($operativo['perdidas'] ?? 0);
            $operativoSinCierre = (int) ($operativo['sin_cierre'] ?? 0);
            $operativoRealizacionRate = (float) ($operativo['realizacion_rate'] ?? 0);
            $operativoFacturacionRate = (float) ($operativo['facturacion_sobre_realizadas_rate'] ?? 0);
            $operativoPendienteRate = (float) ($operativo['pendiente_sobre_realizadas_rate'] ?? 0);
            $operativoPerdidaRate = (float) ($operativo['perdida_rate'] ?? 0);
            $operativoHonorarioReal = (float) ($operativo['honorario_real'] ?? $totalHonorarioReal);
            $operativoPorCobrarEstimado = (float) ($operativo['por_cobrar_estimado'] ?? 0);
            $operativoPerdidaEstimada = (float) ($operativo['perdida_estimada'] ?? 0);
            $operativoPotencialCapturable = (float) ($operativo['potencial_capturable'] ?? ($operativoHonorarioReal + $operativoPorCobrarEstimado));
            $operativoTicketFacturadoReal = (float) ($operativo['ticket_facturado_real'] ?? 0);
            $operativoTicketPendiente = (float) ($operativo['ticket_pendiente'] ?? 0);
            $pniSummary = is_array($summary['pni'] ?? null) ? $summary['pni'] : [];
            $pniTotal = (int) ($pniSummary['total'] ?? 0);
            $pniRealizadas = (int) ($pniSummary['realizadas'] ?? 0);
            $pniFacturadas = (int) ($pniSummary['facturadas'] ?? 0);
            $pniRealizadaConsulta = (int) ($pniSummary['realizada_consulta'] ?? 0);
            $pniCanceladas = (int) ($pniSummary['canceladas'] ?? 0);
            $pniAusentes = (int) ($pniSummary['ausentes'] ?? 0);
            $pniPendientesFacturar = (int) ($pniSummary['pendientes_facturar'] ?? 0);
            $pniHonorarioReal = (float) ($pniSummary['honorario_real'] ?? 0);
            $pniPorCobrarEstimado = (float) ($pniSummary['por_cobrar_estimado'] ?? 0);
            $pniPerdidaEstimada = (float) ($pniSummary['perdida_estimada'] ?? 0);
            $pniSinTarifaEstimable = (int) ($pniSummary['sin_tarifa_estimable'] ?? 0);
            $pniSinCostoConfigurado = (int) ($pniSummary['sin_costo_configurado'] ?? 0);
            $pniEstados = is_array($pniSummary['estados'] ?? null) ? $pniSummary['estados'] : [];
            $pniDoctoresPorCobrar = is_array($pniSummary['doctores_por_cobrar'] ?? null) ? $pniSummary['doctores_por_cobrar'] : [];
            $pniDoctoresPerdida = is_array($pniSummary['doctores_perdida'] ?? null) ? $pniSummary['doctores_perdida'] : [];
            $imagenesSummary = is_array($summary['imagenes'] ?? null) ? $summary['imagenes'] : [];
            $imagenesTotal = (int) ($imagenesSummary['total'] ?? 0);
            $imagenesRealizadas = (int) ($imagenesSummary['realizadas'] ?? 0);
            $imagenesFacturadas = (int) ($imagenesSummary['facturadas'] ?? 0);
            $imagenesConArchivos = (int) ($imagenesSummary['realizada_con_archivos'] ?? 0);
            $imagenesRealizadaInformada = (int) ($imagenesSummary['realizada_informada'] ?? 0);
            $imagenesInformadas = (int) ($imagenesSummary['informadas'] ?? 0);
            $imagenesPendientesInformar = (int) ($imagenesSummary['pendientes_informar'] ?? 0);
            $imagenesCanceladas = (int) ($imagenesSummary['canceladas'] ?? 0);
            $imagenesAusentes = (int) ($imagenesSummary['ausentes'] ?? 0);
            $imagenesSinCierre = (int) ($imagenesSummary['sin_cierre'] ?? 0);
            $imagenesPendientesFacturar = (int) ($imagenesSummary['pendientes_facturar'] ?? 0);
            $imagenesHonorarioReal = (float) ($imagenesSummary['honorario_real'] ?? 0);
            $imagenesPorCobrarEstimado = (float) ($imagenesSummary['por_cobrar_estimado'] ?? 0);
            $imagenesPerdidaEstimada = (float) ($imagenesSummary['perdida_estimada'] ?? 0);
            $imagenesSinTarifaEstimable = (int) ($imagenesSummary['sin_tarifa_estimable'] ?? 0);
            $imagenesSinCostoConfigurado = (int) ($imagenesSummary['sin_costo_configurado'] ?? 0);
            $imagenesEstados = is_array($imagenesSummary['estados'] ?? null) ? $imagenesSummary['estados'] : [];
            $imagenesEstadosInforme = is_array($imagenesSummary['estados_informe'] ?? null) ? $imagenesSummary['estados_informe'] : [];
            $imagenesDoctoresPorCobrar = is_array($imagenesSummary['doctores_por_cobrar'] ?? null) ? $imagenesSummary['doctores_por_cobrar'] : [];
            $imagenesDoctoresPerdida = is_array($imagenesSummary['doctores_perdida'] ?? null) ? $imagenesSummary['doctores_perdida'] : [];
            $serviciosOftalmologicosSummary = is_array($summary['servicios_oftalmologicos'] ?? null) ? $summary['servicios_oftalmologicos'] : [];
            $serviciosOftalmologicosTotal = (int) ($serviciosOftalmologicosSummary['total'] ?? 0);
            $serviciosOftalmologicosRealizadas = (int) ($serviciosOftalmologicosSummary['realizadas'] ?? 0);
            $serviciosOftalmologicosFacturadas = (int) ($serviciosOftalmologicosSummary['facturadas'] ?? 0);
            $serviciosOftalmologicosRealizadaConsulta = (int) ($serviciosOftalmologicosSummary['realizada_consulta'] ?? 0);
            $serviciosOftalmologicosCanceladas = (int) ($serviciosOftalmologicosSummary['canceladas'] ?? 0);
            $serviciosOftalmologicosAusentes = (int) ($serviciosOftalmologicosSummary['ausentes'] ?? 0);
            $serviciosOftalmologicosPendientesFacturar = (int) ($serviciosOftalmologicosSummary['pendientes_facturar'] ?? 0);
            $serviciosOftalmologicosHonorarioReal = (float) ($serviciosOftalmologicosSummary['honorario_real'] ?? 0);
            $serviciosOftalmologicosPorCobrarEstimado = (float) ($serviciosOftalmologicosSummary['por_cobrar_estimado'] ?? 0);
            $serviciosOftalmologicosPerdidaEstimada = (float) ($serviciosOftalmologicosSummary['perdida_estimada'] ?? 0);
            $serviciosOftalmologicosSinTarifaEstimable = (int) ($serviciosOftalmologicosSummary['sin_tarifa_estimable'] ?? 0);
            $serviciosOftalmologicosSinCostoConfigurado = (int) ($serviciosOftalmologicosSummary['sin_costo_configurado'] ?? 0);
            $serviciosOftalmologicosEstados = is_array($serviciosOftalmologicosSummary['estados'] ?? null) ? $serviciosOftalmologicosSummary['estados'] : [];
            $serviciosOftalmologicosDoctoresPorCobrar = is_array($serviciosOftalmologicosSummary['doctores_por_cobrar'] ?? null) ? $serviciosOftalmologicosSummary['doctores_por_cobrar'] : [];
            $serviciosOftalmologicosDoctoresPerdida = is_array($serviciosOftalmologicosSummary['doctores_perdida'] ?? null) ? $serviciosOftalmologicosSummary['doctores_perdida'] : [];
            $cirugiasSummary = is_array($summary['cirugias'] ?? null) ? $summary['cirugias'] : [];
            $cirugiasTotal = (int) ($cirugiasSummary['total'] ?? 0);
            $cirugiasRealizadas = (int) ($cirugiasSummary['realizadas'] ?? 0);
            $cirugiasConfirmadas = (int) ($cirugiasSummary['operada_confirmada'] ?? 0);
            $cirugiasConProtocolo = (int) ($cirugiasSummary['operada_con_protocolo'] ?? 0);
            $cirugiasOtroCentro = (int) ($cirugiasSummary['operada_otro_centro'] ?? 0);
            $cirugiasCanceladas = (int) ($cirugiasSummary['canceladas'] ?? 0);
            $cirugiasSinCierre = (int) ($cirugiasSummary['sin_cierre'] ?? 0);
            $cirugiasPendientesFacturar = (int) ($cirugiasSummary['pendientes_facturar'] ?? 0);
            $cirugiasFacturadasLocales = (int) ($cirugiasSummary['facturadas_locales'] ?? 0);
            $cirugiasFacturadasExternas = (int) ($cirugiasSummary['facturadas_externas'] ?? 0);
            $cirugiasHonorarioReal = (float) ($cirugiasSummary['honorario_real'] ?? 0);
            $cirugiasPorCobrarEstimado = (float) ($cirugiasSummary['por_cobrar_estimado'] ?? 0);
            $cirugiasPerdidaEstimada = (float) ($cirugiasSummary['perdida_estimada'] ?? 0);
            $cirugiasSinTarifaEstimable = (int) ($cirugiasSummary['sin_tarifa_estimable'] ?? 0);
            $cirugiasSinCostoConfigurado = (int) ($cirugiasSummary['sin_costo_configurado'] ?? 0);
            $cirugiasEstados = is_array($cirugiasSummary['estados'] ?? null) ? $cirugiasSummary['estados'] : [];
            $cirugiasDoctoresPorCobrar = is_array($cirugiasSummary['doctores_por_cobrar'] ?? null) ? $cirugiasSummary['doctores_por_cobrar'] : [];
            $cirugiasDoctoresPerdida = is_array($cirugiasSummary['doctores_perdida'] ?? null) ? $cirugiasSummary['doctores_perdida'] : [];
            $pacientesUnicos = (int) ($summary['pacientes_unicos'] ?? 0);
            $pacientesUnicosRealizados = (int) ($summary['pacientes_unicos_realizados'] ?? $pacientesUnicos);
            $pacientesUnicosRealizadosCategoria = is_array($summary['pacientes_unicos_realizados_categoria'] ?? null)
                ? $summary['pacientes_unicos_realizados_categoria']
                : ['particular' => 0, 'privado' => 0];
            $categoriaCounts = is_array($summary['categoria_counts'] ?? null) ? $summary['categoria_counts'] : ['particular' => 0, 'privado' => 0];
            $categoriaShare = is_array($summary['categoria_share'] ?? null) ? $summary['categoria_share'] : ['particular' => 0, 'privado' => 0];
            $insuranceBreakdown = is_array($summary['insurance_breakdown'] ?? null) ? $summary['insurance_breakdown'] : [];
            $topAfiliaciones = is_array($summary['top_afiliaciones'] ?? null) ? $summary['top_afiliaciones'] : [];
            $insuranceBreakdownTitle = trim((string) ($insuranceBreakdown['title'] ?? 'Empresas de seguro'));
            $insuranceBreakdownItemLabel = trim((string) ($insuranceBreakdown['item_label'] ?? 'Empresa de seguro'));
            $particularCount = (int) ($categoriaCounts['particular'] ?? 0);
            $privadoCount = (int) ($categoriaCounts['privado'] ?? 0);
            $particularPacientesUnicosRealizados = (int) ($pacientesUnicosRealizadosCategoria['particular'] ?? 0);
            $privadoPacientesUnicosRealizados = (int) ($pacientesUnicosRealizadosCategoria['privado'] ?? 0);
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
            $ticketPromedioParticular = $particularCount > 0 ? round($honorarioParticular / $particularCount, 2) : 0.0;
            $ticketPromedioPrivado = $privadoCount > 0 ? round($honorarioPrivado / $privadoCount, 2) : 0.0;
            $ticketPromedioCategoriaTotal = $totalAtenciones > 0 ? round($totalHonorarioReal / $totalAtenciones, 2) : 0.0;

            $doctorTicketPromedioRows = is_array($economico['doctores_rendimiento_top'] ?? null) ? $economico['doctores_rendimiento_top'] : [];

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

            $objetivoUnoSummary = is_array($summary['objetivo_1'] ?? null) ? $summary['objetivo_1'] : ['segments' => [], 'alerts' => []];
            $objetivoUnoSegments = is_array($objetivoUnoSummary['segments'] ?? null) ? $objetivoUnoSummary['segments'] : [];
            $objetivoUnoAlerts = is_array($objetivoUnoSummary['alerts'] ?? null) ? $objetivoUnoSummary['alerts'] : [];
            $objetivoUnoParticular = null;
            $objetivoUnoPrivado = null;
            foreach ($objetivoUnoSegments as $segmentItem) {
                $segmentKey = strtolower(trim((string) ($segmentItem['key'] ?? '')));
                if ($segmentKey === 'particular') {
                    $objetivoUnoParticular = $segmentItem;
                } elseif ($segmentKey === 'privado') {
                    $objetivoUnoPrivado = $segmentItem;
                }
            }
            $objetivoUnoSignalClass = static function (?string $signal): string {
                return match (strtoupper(trim((string) $signal))) {
                    'CRECIENDO' => 'success',
                    'EN RETROCESO' => 'danger',
                    default => 'warning',
                };
            };

            $hallazgosClave = [];
            if ($operativoEvaluadas > 0) {
                $hallazgosClave[] = sprintf(
                    'Se realizaron %d de %d atenciones evaluadas (%.2f%%).',
                    $operativoRealizadas,
                    $operativoEvaluadas,
                    $operativoRealizacionRate
                );
                $hallazgosClave[] = sprintf(
                    'Se facturaron %d de las %d realizadas (%.2f%%) y %d quedaron pendientes de cobro (%.2f%%).',
                    $operativoFacturadas,
                    max($operativoRealizadas, 0),
                    $operativoFacturacionRate,
                    $operativoPendientesFacturar,
                    $operativoPendienteRate
                );
                $hallazgosClave[] = sprintf(
                    'La pérdida operativa fue de %d casos (%.2f%% del total) con una pérdida estimada de $%s.',
                    $operativoPerdidas,
                    $operativoPerdidaRate,
                    number_format($operativoPerdidaEstimada, 2)
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
            $hallazgosClave = array_slice($hallazgosClave, 0, 4);
        @endphp

        <div class="row">
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box box-inverse box-primary kpi-filter-card"
                     data-detail-block="operativo"
                     data-detail-segment="pipeline"
                     data-bs-toggle="tooltip"
                     title="Universo completo del informe dentro del filtro aplicado. Incluye realizadas, pendientes y casos no concretados.">
                    <div class="kpi-filter-actions">
                        <button type="button" class="btn btn-light btn-sm border" data-detail-modal="operativo:pipeline" data-bs-toggle="tooltip" title="Abrir resumen rápido del pipeline">
                            <i class="mdi mdi-magnify"></i>
                        </button>
                        <a href="{{ $detailSegmentUrl('operativo', 'pipeline') }}" class="btn btn-light btn-sm border" data-detail-link="operativo:pipeline" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                            <i class="mdi mdi-open-in-new"></i>
                        </a>
                    </div>
                    <div class="box-body text-center kpi-filter-body">
                        <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                        <h6 class="mb-5">Casos del pipeline</h6>
                        <div class="fs-32 fw-700">{{ $operativoEvaluadas }}</div>
                        <small class="text-white">Universo operativo total del informe</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box kpi-filter-card"
                     data-detail-block="operativo"
                     data-detail-segment="realizada"
                     data-bs-toggle="tooltip"
                     title="Atenciones con evidencia real de ejecución clínica, técnica o quirúrgica.">
                    <div class="kpi-filter-actions">
                        <button type="button" class="btn btn-light btn-sm border" data-detail-modal="operativo:realizada" data-bs-toggle="tooltip" title="Abrir resumen rápido de atenciones realizadas">
                            <i class="mdi mdi-magnify"></i>
                        </button>
                        <a href="{{ $detailSegmentUrl('operativo', 'realizada') }}" class="btn btn-light btn-sm border" data-detail-link="operativo:realizada" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                            <i class="mdi mdi-open-in-new"></i>
                        </a>
                    </div>
                    <div class="box-body text-center kpi-filter-body">
                        <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                        <h6 class="mb-5">Atenciones realizadas</h6>
                        <div class="fs-30 fw-700 text-success">{{ $operativoRealizadas }}</div>
                        <small class="text-muted">{{ number_format($operativoRealizacionRate, 2) }}% del total
                            del pipeline</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box kpi-filter-card"
                     data-detail-block="operativo"
                     data-detail-segment="facturada"
                     data-bs-toggle="tooltip"
                     title="Casos con facturación real local o externa ya registrada.">
                    <div class="kpi-filter-actions">
                        <button type="button" class="btn btn-light btn-sm border" data-detail-modal="operativo:facturada" data-bs-toggle="tooltip" title="Abrir resumen rápido de atenciones facturadas">
                            <i class="mdi mdi-magnify"></i>
                        </button>
                        <a href="{{ $detailSegmentUrl('operativo', 'facturada') }}" class="btn btn-light btn-sm border" data-detail-link="operativo:facturada" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                            <i class="mdi mdi-open-in-new"></i>
                        </a>
                    </div>
                    <div class="box-body text-center kpi-filter-body">
                        <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                        <h6 class="mb-5">Atenciones facturadas</h6>
                        <div class="fs-30 fw-700 text-info">{{ $operativoFacturadas }}</div>
                        <small class="text-muted">{{ number_format($operativoFacturacionRate, 2) }}% de las
                            realizadas</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box kpi-filter-card"
                     data-detail-block="operativo"
                     data-detail-segment="pendiente_facturar"
                     data-bs-toggle="tooltip"
                     title="Casos ya realizados que todavía no tienen billing real.">
                    <div class="kpi-filter-actions">
                        <button type="button" class="btn btn-light btn-sm border" data-detail-modal="operativo:pendiente_facturar" data-bs-toggle="tooltip" title="Abrir resumen rápido de pendientes de facturar">
                            <i class="mdi mdi-magnify"></i>
                        </button>
                        <a href="{{ $detailSegmentUrl('operativo', 'pendiente_facturar') }}" class="btn btn-light btn-sm border" data-detail-link="operativo:pendiente_facturar" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                            <i class="mdi mdi-open-in-new"></i>
                        </a>
                    </div>
                    <div class="box-body text-center kpi-filter-body">
                        <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                        <h6 class="mb-5">Pendientes de facturar</h6>
                        <div class="fs-30 fw-700 text-warning">{{ $operativoPendientesFacturar }}</div>
                        <small class="text-muted">{{ number_format($operativoPendienteRate, 2) }}% de las
                            realizadas</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box kpi-filter-card"
                     data-detail-block="operativo"
                     data-detail-segment="perdida"
                     data-bs-toggle="tooltip"
                     title="Casos que no se concretaron. Incluye canceladas, ausentes y cierres operativos perdidos según el tipo de atención.">
                    <div class="kpi-filter-actions">
                        <button type="button" class="btn btn-light btn-sm border" data-detail-modal="operativo:perdida" data-bs-toggle="tooltip" title="Abrir resumen rápido de casos no concretados">
                            <i class="mdi mdi-magnify"></i>
                        </button>
                        <a href="{{ $detailSegmentUrl('operativo', 'perdida') }}" class="btn btn-light btn-sm border" data-detail-link="operativo:perdida" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                            <i class="mdi mdi-open-in-new"></i>
                        </a>
                    </div>
                    <div class="box-body text-center kpi-filter-body">
                        <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                        <h6 class="mb-5">Casos no concretados</h6>
                        <div class="fs-30 fw-700 text-danger">{{ $operativoPerdidas }}</div>
                        <small class="text-muted">{{ number_format($operativoPerdidaRate, 2) }}% del total
                            del pipeline</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box kpi-filter-card"
                     data-detail-block="operativo"
                     data-detail-segment="pacientes_unicos_realizados"
                     data-bs-toggle="tooltip"
                     title="Pacientes únicos que sí tuvieron al menos una atención realizada dentro del período filtrado. El modal agrupa por HC para que el conteo coincida con el KPI.">
                    <div class="kpi-filter-actions">
                        <button type="button" class="btn btn-light btn-sm border" data-detail-modal="operativo:pacientes_unicos_realizados" data-bs-toggle="tooltip" title="Abrir resumen rápido de pacientes únicos atendidos">
                            <i class="mdi mdi-magnify"></i>
                        </button>
                        <a href="{{ $detailSegmentUrl('operativo', 'pacientes_unicos_realizados') }}" class="btn btn-light btn-sm border" data-detail-link="operativo:pacientes_unicos_realizados" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                            <i class="mdi mdi-open-in-new"></i>
                        </a>
                    </div>
                    <div class="box-body text-center kpi-filter-body">
                        <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                        <h6 class="mb-5">Pacientes únicos atendidos</h6>
                        <div class="fs-30 fw-700 text-primary">{{ $pacientesUnicosRealizados }}</div>
                        <small class="text-muted">{{ $pacientesUnicos }} únicos en el pipeline | {{ $particularPacientesUnicosRealizados }}
                            particular / {{ $privadoPacientesUnicosRealizados }} privado</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-12">
                <div class="d-flex justify-content-end">
                    <div class="kpi-inline-toolbar">
                        <span class="badge bg-primary-light text-primary d-none" data-detail-filter-badge="operativo"></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-detail-clear="operativo">
                            Limpiar filtro
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if($objetivoUnoParticular !== null || $objetivoUnoPrivado !== null)
            <div class="row">
                <div class="col-12">
                    <div class="box bg-lightest">
                        <div class="box-body py-15">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-10">
                                <div>
                                    <h5 class="mb-0">Objetivo 1: crecimiento de Particular y Privado</h5>
                                    <small class="text-muted">Señales ejecutivas con lo que ya podemos medir hoy: facturación, atenciones, ticket y promedio mensual por segmento.</small>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    @if($objetivoUnoParticular !== null)
                                        @php $particularSignalClass = $objetivoUnoSignalClass((string) ($objetivoUnoParticular['signal'] ?? '')); @endphp
                                        <span class="badge bg-{{ $particularSignalClass }}-light text-{{ $particularSignalClass }}">
                                            PARTICULAR: {{ $objetivoUnoParticular['signal'] ?? 'ESTABLE' }}
                                        </span>
                                    @endif
                                    @if($objetivoUnoPrivado !== null)
                                        @php $privadoSignalClass = $objetivoUnoSignalClass((string) ($objetivoUnoPrivado['signal'] ?? '')); @endphp
                                        <span class="badge bg-{{ $privadoSignalClass }}-light text-{{ $privadoSignalClass }}">
                                            PRIVADO: {{ $objetivoUnoPrivado['signal'] ?? 'ESTABLE' }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                @foreach($objetivoUnoSegments as $segment)
                    @php
                        $segmentSignalClass = $objetivoUnoSignalClass((string) ($segment['signal'] ?? ''));
                        $segmentLabel = (string) ($segment['label'] ?? 'SEGMENTO');
                        $segmentCurrentMonthLabel = (string) ($segment['current_month_label'] ?? 'N/D');
                        $segmentPreviousMonthLabel = (string) ($segment['previous_month_label'] ?? 'N/D');
                        $segmentLastYearMonthLabel = (string) ($segment['last_year_month_label'] ?? 'N/D');
                        $segmentFactVsPrev = is_numeric($segment['facturacion_vs_previous_pct'] ?? null) ? (float) $segment['facturacion_vs_previous_pct'] : null;
                        $segmentFactVsYear = is_numeric($segment['facturacion_vs_last_year_pct'] ?? null) ? (float) $segment['facturacion_vs_last_year_pct'] : null;
                        $segmentTicketVsPrev = is_numeric($segment['ticket_vs_previous_pct'] ?? null) ? (float) $segment['ticket_vs_previous_pct'] : null;
                    @endphp
                    <div class="col-xl-6 col-12">
                        <div class="box">
                            <div class="box-header with-border d-flex justify-content-between align-items-center">
                                <h5 class="box-title mb-0">{{ $segmentLabel }}</h5>
                                <span class="badge bg-{{ $segmentSignalClass }}-light text-{{ $segmentSignalClass }}">{{ $segment['signal'] ?? 'ESTABLE' }}</span>
                            </div>
                            <div class="box-body">
                                <div class="row">
                                    <div class="col-md-4 col-6">
                                        <div class="text-center mb-15">
                                            <h6 class="mb-5">Facturación total</h6>
                                            <div class="fs-24 fw-700 text-success">${{ number_format((float) ($segment['honorario_total'] ?? 0), 2) }}</div>
                                            <small class="text-muted">Prom. mensual ${{ number_format((float) ($segment['monthly_avg_honorario'] ?? 0), 2) }}</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <div class="text-center mb-15">
                                            <h6 class="mb-5">Atenciones</h6>
                                            <div class="fs-24 fw-700 text-primary">{{ number_format((float) ($segment['atenciones_total'] ?? 0), 0) }}</div>
                                            <small class="text-muted">Prom. mensual {{ number_format((float) ($segment['monthly_avg_atenciones'] ?? 0), 1) }}</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-12">
                                        <div class="text-center mb-15">
                                            <h6 class="mb-5">Ticket promedio</h6>
                                            <div class="fs-24 fw-700 text-dark">${{ number_format((float) ($segment['ticket_promedio_total'] ?? 0), 2) }}</div>
                                            <small class="text-muted">{{ number_format((float) ($segment['pacientes_unicos_total'] ?? 0), 0) }} pacientes únicos</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead class="table-light">
                                        <tr>
                                            <th>KR operativo</th>
                                            <th class="text-end">Actual</th>
                                            <th class="text-end">Anterior</th>
                                            <th class="text-end">Δ</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td>Facturación mensual</td>
                                            <td class="text-end">{{ $segmentCurrentMonthLabel }}: ${{ number_format((float) ($segment['current_month_honorario'] ?? 0), 2) }}</td>
                                            <td class="text-end">{{ $segmentPreviousMonthLabel }}: ${{ number_format((float) ($segment['previous_month_honorario'] ?? 0), 2) }}</td>
                                            <td class="text-end {{ $segmentFactVsPrev !== null && $segmentFactVsPrev < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ $segmentFactVsPrev !== null ? number_format($segmentFactVsPrev, 2) . '%' : 'N/D' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Atenciones mensuales</td>
                                            <td class="text-end">{{ $segmentCurrentMonthLabel }}: {{ number_format((float) ($segment['current_month_atenciones'] ?? 0), 0) }}</td>
                                            <td class="text-end">{{ $segmentPreviousMonthLabel }}: {{ number_format((float) ($segment['previous_month_atenciones'] ?? 0), 0) }}</td>
                                            <td class="text-end {{ is_numeric($segment['atenciones_vs_previous_pct'] ?? null) && (float) $segment['atenciones_vs_previous_pct'] < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ is_numeric($segment['atenciones_vs_previous_pct'] ?? null) ? number_format((float) $segment['atenciones_vs_previous_pct'], 2) . '%' : 'N/D' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Ticket mensual</td>
                                            <td class="text-end">{{ $segmentCurrentMonthLabel }}: ${{ number_format((float) ($segment['current_month_ticket'] ?? 0), 2) }}</td>
                                            <td class="text-end">{{ $segmentPreviousMonthLabel }}: ${{ number_format((float) ($segment['previous_month_ticket'] ?? 0), 2) }}</td>
                                            <td class="text-end {{ $segmentTicketVsPrev !== null && $segmentTicketVsPrev < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ $segmentTicketVsPrev !== null ? number_format($segmentTicketVsPrev, 2) . '%' : 'N/D' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Facturación vs mismo mes año anterior</td>
                                            <td class="text-end">{{ $segmentCurrentMonthLabel }}: ${{ number_format((float) ($segment['current_month_honorario'] ?? 0), 2) }}</td>
                                            <td class="text-end">{{ $segmentLastYearMonthLabel }}: ${{ number_format((float) ($segment['last_year_month_honorario'] ?? 0), 2) }}</td>
                                            <td class="text-end {{ $segmentFactVsYear !== null && $segmentFactVsYear < 0 ? 'text-danger' : 'text-success' }}">
                                                {{ $segmentFactVsYear !== null ? number_format($segmentFactVsYear, 2) . '%' : 'N/D' }}
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if(!empty($objetivoUnoAlerts))
                <div class="row">
                    <div class="col-12">
                        <div class="box">
                            <div class="box-header with-border">
                                <h5 class="box-title mb-0">Alertas automáticas del Objetivo 1</h5>
                            </div>
                            <div class="box-body">
                                <ul class="mb-0 ps-20">
                                    @foreach($objetivoUnoAlerts as $alerta)
                                        <li class="mb-10">{{ $alerta }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        @if($cirugiasTotal > 0)
            <div class="row">
                <div class="col-12">
                    <div class="box bg-lightest">
                        <div class="box-body py-15">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-10">
                                <div>
                                    <h5 class="mb-0">Cirugías: realizado, por cobrar y pérdida</h5>
                                    <small class="text-muted">Esta capa usa protocolo, billing real y fallback de
                                        tarifario solo para cirugías.</small>
                                </div>
                                <div class="kpi-inline-toolbar">
                                    <span class="badge bg-primary-light text-primary d-none" data-detail-filter-badge="cirugias"></span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-detail-clear="cirugias">
                                        Limpiar filtro
                                    </button>
                                    <span class="badge bg-success-light text-success">{{ $cirugiasRealizadas }} realizadas</span>
                                    <span class="badge bg-warning-light text-warning">{{ $cirugiasPendientesFacturar }} pendientes de facturar</span>
                                    <span class="badge bg-danger-light text-danger">{{ $cirugiasCanceladas + $cirugiasSinCierre }} pérdida / sin cierre</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="cirugias"
                         data-detail-segment="realizada"
                         data-bs-toggle="tooltip"
                         title="Filtra cirugías realizadas. Breakdown: {{ $cirugiasConfirmadas }} confirmadas, {{ $cirugiasConProtocolo }} con protocolo, {{ $cirugiasOtroCentro }} externas.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="cirugias:realizada" data-bs-toggle="tooltip" title="Abrir resumen rápido de cirugías realizadas">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('cirugias', 'realizada') }}" class="btn btn-light btn-sm border" data-detail-link="cirugias:realizada" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Cirugías realizadas</h6>
                            <div class="fs-28 fw-700 text-success">{{ $cirugiasRealizadas }}</div>
                            <small class="text-muted">{{ $cirugiasConfirmadas }}
                                confirmadas, {{ $cirugiasConProtocolo }} con protocolo, {{ $cirugiasOtroCentro }}
                                externas</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="cirugias"
                         data-detail-segment="pendiente_facturar"
                         data-bs-toggle="tooltip"
                         title="Filtra cirugías con estado de facturación pendiente.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="cirugias:pendiente_facturar" data-bs-toggle="tooltip" title="Abrir resumen rápido de cirugías pendientes de facturar">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('cirugias', 'pendiente_facturar') }}" class="btn btn-light btn-sm border" data-detail-link="cirugias:pendiente_facturar" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Pendiente de facturar</h6>
                            <div class="fs-28 fw-700 text-warning">{{ $cirugiasPendientesFacturar }}</div>
                            <small class="text-muted">{{ $cirugiasFacturadasLocales }} locales
                                facturadas, {{ $cirugiasFacturadasExternas }} externas</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="cirugias"
                         data-detail-segment="cancelada"
                         data-bs-toggle="tooltip"
                         title="Filtra cirugías canceladas y también las que quedaron en sin cierre operativo.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="cirugias:cancelada" data-bs-toggle="tooltip" title="Abrir resumen rápido de cirugías canceladas">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('cirugias', 'cancelada') }}" class="btn btn-light btn-sm border" data-detail-link="cirugias:cancelada" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Canceladas</h6>
                            <div class="fs-28 fw-700 text-danger">{{ $cirugiasCanceladas }}</div>
                            <small class="text-muted">{{ $cirugiasSinCierre }} sin cierre operativo</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="cirugias"
                         data-detail-segment="sin_tarifa_estimable"
                         data-bs-toggle="tooltip"
                         title="Filtra cirugías sin tarifa estimable. {{ $cirugiasSinCostoConfigurado }} con costo 0 configurado quedan fuera de este filtro.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="cirugias:sin_tarifa_estimable" data-bs-toggle="tooltip" title="Abrir resumen rápido de cirugías sin tarifa estimable">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('cirugias', 'sin_tarifa_estimable') }}" class="btn btn-light btn-sm border" data-detail-link="cirugias:sin_tarifa_estimable" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Sin tarifa estimable</h6>
                            <div class="fs-28 fw-700 text-dark">{{ $cirugiasSinTarifaEstimable }}</div>
                            <small class="text-muted">Solo faltantes reales de
                                pricing. {{ $cirugiasSinCostoConfigurado }} con precio 0 van aparte.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-4 col-md-6 col-12">
                    <div class="box">
                        <div class="box-body text-center">
                            <h6 class="mb-5">Honorario real cirugía</h6>
                            <div class="fs-28 fw-700 text-success">${{ number_format($cirugiasHonorarioReal, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 col-12">
                    <div class="box">
                        <div class="box-body text-center">
                            <h6 class="mb-5">Por cobrar estimado</h6>
                            <div class="fs-28 fw-700 text-warning">
                                ${{ number_format($cirugiasPorCobrarEstimado, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-12 col-12">
                    <div class="box">
                        <div class="box-body text-center">
                            <h6 class="mb-5">Pérdida estimada</h6>
                            <div class="fs-28 fw-700 text-danger">
                                ${{ number_format($cirugiasPerdidaEstimada, 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-4 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0">Estado real de cirugías</h5>
                        </div>
                        <div class="box-body">
                            <div id="cirugiasEstadoChart" style="min-height: 320px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0">Cirujanos con mayor por cobrar</h5>
                        </div>
                        <div class="box-body">
                            <div id="cirugiasPorCobrarDoctorChart" style="min-height: 320px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0">Cirujanos con mayor pérdida estimada</h5>
                        </div>
                        <div class="box-body">
                            <div id="cirugiasPerdidaDoctorChart" style="min-height: 320px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade detail-segment-modal" id="detailSegmentModal" tabindex="-1" aria-labelledby="detailSegmentModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title" id="detailSegmentModalLabel">Resumen del segmento</h5>
                                <small class="detail-segment-modal-subtitle" id="detailSegmentModalSubtitle">Casos filtrados del bloque seleccionado.</small>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body detail-segment-modal-body">
                            <div class="detail-segment-metrics">
                                <div class="detail-segment-metric detail-segment-metric-success">
                                    <span class="detail-segment-metric-label" id="detailSegmentModalCountLabel">Casos</span>
                                    <span class="detail-segment-metric-value" id="detailSegmentModalCount">0 casos</span>
                                </div>
                                <div class="detail-segment-metric detail-segment-metric-warning">
                                    <span class="detail-segment-metric-label">Por cobrar</span>
                                    <span class="detail-segment-metric-value" id="detailSegmentModalPorCobrar">$0.00 por cobrar</span>
                                </div>
                                <div class="detail-segment-metric detail-segment-metric-danger">
                                    <span class="detail-segment-metric-label">Pérdida</span>
                                    <span class="detail-segment-metric-value" id="detailSegmentModalPerdida">$0.00 pérdida</span>
                                </div>
                            </div>
                            <div class="table-responsive detail-segment-table-wrap">
                                <table class="table table-sm detail-segment-table">
                                    <thead id="detailSegmentModalHead">
                                    <tr>
                                        <th>HC</th>
                                        <th>Paciente</th>
                                        <th>Fecha</th>
                                        <th>Sede</th>
                                        <th>Categoría</th>
                                        <th>Afiliación</th>
                                        <th>Procedimiento</th>
                                        <th>Doctor</th>
                                        <th>Estado real</th>
                                        <th>Motivo operativo</th>
                                        <th>Facturación</th>
                                        <th class="text-end">Honorario</th>
                                        <th class="text-end">Por cobrar</th>
                                        <th class="text-end">Pérdida</th>
                                    </tr>
                                    </thead>
                                    <tbody id="detailSegmentModalBody">
                                    <tr>
                                        <td colspan="14" class="text-center detail-segment-empty">Sin datos disponibles.</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="button" class="btn btn-outline-success" id="detailSegmentModalExport">Exportar CSV</button>
                            <button type="button" class="btn btn-outline-primary" id="detailSegmentModalDetail">Ver detalle completo</button>
                            <a href="{{ $detailSegmentUrl() }}" class="btn btn-primary" id="detailSegmentModalDeepLink">Abrir URL filtrada</a>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($pniTotal > 0)
            <div class="row">
                <div class="col-12">
                    <div class="box bg-lightest">
                        <div class="box-body py-15">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-10">
                                <div>
                                    <h5 class="mb-0">PNI: realizado, por cobrar y pérdida</h5>
                                    <small class="text-muted">Esta capa usa consulta_data como evidencia clínica y
                                        billing real para cierres económicos.</small>
                                </div>
                                <div class="kpi-inline-toolbar">
                                    <span class="badge bg-primary-light text-primary d-none" data-detail-filter-badge="pni"></span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-detail-clear="pni">
                                        Limpiar filtro
                                    </button>
                                    <span
                                        class="badge bg-success-light text-success">{{ $pniRealizadas }} realizadas</span>
                                    <span class="badge bg-warning-light text-warning">{{ $pniPendientesFacturar }} pendientes de facturar</span>
                                    <span class="badge bg-danger-light text-danger">{{ $pniCanceladas + $pniAusentes }} pérdida</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="pni"
                         data-detail-segment="realizada"
                         data-bs-toggle="tooltip"
                         title="Filtra PNI realizadas. Breakdown: {{ $pniFacturadas }} facturadas, {{ $pniRealizadaConsulta }} con consulta.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="pni:realizada" data-bs-toggle="tooltip" title="Abrir resumen rápido de PNI realizadas">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('pni', 'realizada') }}" class="btn btn-light btn-sm border" data-detail-link="pni:realizada" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">PNI realizadas</h6>
                            <div class="fs-28 fw-700 text-success">{{ $pniRealizadas }}</div>
                            <small class="text-muted">{{ $pniFacturadas }} facturadas, {{ $pniRealizadaConsulta }} con
                                consulta</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="pni"
                         data-detail-segment="pendiente_facturar"
                         data-bs-toggle="tooltip"
                         title="Filtra PNI pendientes de facturar con respaldo clínico.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="pni:pendiente_facturar" data-bs-toggle="tooltip" title="Abrir resumen rápido de PNI pendientes de facturar">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('pni', 'pendiente_facturar') }}" class="btn btn-light btn-sm border" data-detail-link="pni:pendiente_facturar" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Pendiente de facturar</h6>
                            <div class="fs-28 fw-700 text-warning">{{ $pniPendientesFacturar }}</div>
                            <small class="text-muted">Atenciones PNI con respaldo clínico aún sin billing real</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="pni"
                         data-detail-segment="cancelada"
                         data-bs-toggle="tooltip"
                         title="Filtra PNI canceladas y ausentes.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="pni:cancelada" data-bs-toggle="tooltip" title="Abrir resumen rápido de PNI canceladas o ausentes">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('pni', 'cancelada') }}" class="btn btn-light btn-sm border" data-detail-link="pni:cancelada" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Canceladas</h6>
                            <div class="fs-28 fw-700 text-danger">{{ $pniCanceladas }}</div>
                            <small class="text-muted">{{ $pniAusentes }} ausentes / sin atención</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="pni"
                         data-detail-segment="sin_tarifa_estimable"
                         data-bs-toggle="tooltip"
                         title="Filtra PNI sin tarifa estimable. {{ $pniSinCostoConfigurado }} con precio 0 van aparte.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="pni:sin_tarifa_estimable" data-bs-toggle="tooltip" title="Abrir resumen rápido de PNI sin tarifa estimable">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('pni', 'sin_tarifa_estimable') }}" class="btn btn-light btn-sm border" data-detail-link="pni:sin_tarifa_estimable" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Sin tarifa estimable</h6>
                            <div class="fs-28 fw-700 text-dark">{{ $pniSinTarifaEstimable }}</div>
                            <small class="text-muted">Solo faltantes reales de pricing. {{ $pniSinCostoConfigurado }}
                                con precio 0 van aparte.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-4 col-md-6 col-12">
                    <div class="box">
                        <div class="box-body text-center">
                            <h6 class="mb-5">Honorario real PNI</h6>
                            <div class="fs-28 fw-700 text-success">${{ number_format($pniHonorarioReal, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 col-12">
                    <div class="box">
                        <div class="box-body text-center">
                            <h6 class="mb-5">Por cobrar estimado</h6>
                            <div class="fs-28 fw-700 text-warning">${{ number_format($pniPorCobrarEstimado, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-12 col-12">
                    <div class="box">
                        <div class="box-body text-center">
                            <h6 class="mb-5">Pérdida estimada</h6>
                            <div class="fs-28 fw-700 text-danger">${{ number_format($pniPerdidaEstimada, 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-4 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0">PNI por estado real</h5>
                        </div>
                        <div class="box-body">
                            <div id="pniEstadoChart" style="min-height: 320px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0">PNI con mayor por cobrar</h5>
                        </div>
                        <div class="box-body">
                            <div id="pniPorCobrarDoctorChart" style="min-height: 320px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0">PNI con mayor pérdida estimada</h5>
                        </div>
                        <div class="box-body">
                            <div id="pniPerdidaDoctorChart" style="min-height: 320px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($imagenesTotal > 0)
            <div class="row">
                <div class="col-12">
                    <div class="box bg-lightest">
                        <div class="box-body py-15">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-10">
                                <div>
                                    <h5 class="mb-0">Imágenes: realizado, por cobrar y pérdida</h5>
                                    <small class="text-muted">Esta capa usa NAS, informes de imágenes y billing real;
                                        el estado de agenda solo define canceladas, ausentes y sin cierre.</small>
                                </div>
                                <div class="kpi-inline-toolbar">
                                    <span class="badge bg-primary-light text-primary d-none" data-detail-filter-badge="imagenes"></span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-detail-clear="imagenes">
                                        Limpiar filtro
                                    </button>
                                    <span class="badge bg-success-light text-success">{{ $imagenesRealizadas }} realizadas</span>
                                    <span class="badge bg-warning-light text-warning">{{ $imagenesPendientesFacturar }} pendientes de facturar</span>
                                    <span class="badge bg-info-light text-info">{{ $imagenesPendientesInformar }} pendientes de informar</span>
                                    <span class="badge bg-danger-light text-danger">{{ $imagenesCanceladas + $imagenesAusentes }} pérdida</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="imagenes"
                         data-detail-segment="realizada"
                         data-bs-toggle="tooltip"
                         title="Filtra imágenes realizadas. Breakdown: {{ $imagenesFacturadas }} facturadas, {{ $imagenesConArchivos }} con archivos, {{ $imagenesRealizadaInformada }} informadas.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="imagenes:realizada" data-bs-toggle="tooltip" title="Abrir resumen rápido de imágenes realizadas">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('imagenes', 'realizada') }}" class="btn btn-light btn-sm border" data-detail-link="imagenes:realizada" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Imágenes realizadas</h6>
                            <div class="fs-28 fw-700 text-success">{{ $imagenesRealizadas }}</div>
                            <small class="text-muted">{{ $imagenesFacturadas }} facturadas, {{ $imagenesConArchivos }}
                                con archivos, {{ $imagenesRealizadaInformada }} informadas</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="imagenes"
                         data-detail-segment="pendiente_facturar"
                         data-bs-toggle="tooltip"
                         title="Filtra imágenes pendientes de facturar.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="imagenes:pendiente_facturar" data-bs-toggle="tooltip" title="Abrir resumen rápido de imágenes pendientes de facturar">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('imagenes', 'pendiente_facturar') }}" class="btn btn-light btn-sm border" data-detail-link="imagenes:pendiente_facturar" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Pendiente de facturar</h6>
                            <div class="fs-28 fw-700 text-warning">{{ $imagenesPendientesFacturar }}</div>
                            <small class="text-muted">Realizadas con evidencia técnica aún sin billing real</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="imagenes"
                         data-detail-segment="cancelada"
                         data-bs-toggle="tooltip"
                         title="Filtra imágenes en pérdida operativa: canceladas, ausentes y sin cierre.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="imagenes:cancelada" data-bs-toggle="tooltip" title="Abrir resumen rápido de imágenes en pérdida operativa">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('imagenes', 'cancelada') }}" class="btn btn-light btn-sm border" data-detail-link="imagenes:cancelada" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Pérdida</h6>
                            <div
                                class="fs-28 fw-700 text-danger">{{ $imagenesCanceladas + $imagenesAusentes + $imagenesSinCierre }}</div>
                            <small class="text-muted">{{ $imagenesCanceladas }} canceladas, {{ $imagenesAusentes }}
                                ausentes, {{ $imagenesSinCierre }} sin cierre</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="imagenes"
                         data-detail-segment="sin_tarifa_estimable"
                         data-bs-toggle="tooltip"
                         title="Filtra imágenes sin tarifa estimable. {{ $imagenesSinCostoConfigurado }} con precio 0 van aparte.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="imagenes:sin_tarifa_estimable" data-bs-toggle="tooltip" title="Abrir resumen rápido de imágenes sin tarifa estimable">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('imagenes', 'sin_tarifa_estimable') }}" class="btn btn-light btn-sm border" data-detail-link="imagenes:sin_tarifa_estimable" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Sin tarifa estimable</h6>
                            <div class="fs-28 fw-700 text-dark">{{ $imagenesSinTarifaEstimable }}</div>
                            <small class="text-muted">Solo faltantes reales de
                                pricing. {{ $imagenesSinCostoConfigurado }} con precio 0 van aparte.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-4 col-md-6 col-12">
                    <div class="box">
                        <div class="box-body text-center">
                            <h6 class="mb-5">Honorario real imágenes</h6>
                            <div class="fs-28 fw-700 text-success">${{ number_format($imagenesHonorarioReal, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 col-12">
                    <div class="box">
                        <div class="box-body text-center">
                            <h6 class="mb-5">Por cobrar estimado</h6>
                            <div class="fs-28 fw-700 text-warning">
                                ${{ number_format($imagenesPorCobrarEstimado, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 col-12">
                    <div class="box">
                        <div class="box-body text-center">
                            <h6 class="mb-5">Pérdida estimada</h6>
                            <div class="fs-28 fw-700 text-danger">
                                ${{ number_format($imagenesPerdidaEstimada, 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-4 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0">Imágenes por estado real</h5>
                        </div>
                        <div class="box-body">
                            <div id="imagenesEstadoChart" style="min-height: 320px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0">Imágenes con mayor por cobrar</h5>
                        </div>
                        <div class="box-body">
                            <div id="imagenesPorCobrarDoctorChart" style="min-height: 320px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0">Imágenes con mayor pérdida estimada</h5>
                        </div>
                        <div class="box-body">
                            <div id="imagenesPerdidaDoctorChart" style="min-height: 320px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($serviciosOftalmologicosTotal > 0)
            <div class="row">
                <div class="col-12">
                    <div class="box bg-lightest">
                        <div class="box-body py-15">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-10">
                                <div>
                                    <h5 class="mb-0">Servicios oftalmológicos: realizado, por cobrar y pérdida</h5>
                                    <small class="text-muted">Esta capa usa consulta_data como respaldo clínico,
                                        billing real para cierres y excluye optometría.</small>
                                </div>
                                <div class="kpi-inline-toolbar">
                                    <span class="badge bg-primary-light text-primary d-none" data-detail-filter-badge="servicios_oftalmologicos"></span>
                                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-detail-clear="servicios_oftalmologicos">
                                        Limpiar filtro
                                    </button>
                                    <span class="badge bg-success-light text-success">{{ $serviciosOftalmologicosRealizadas }} realizadas</span>
                                    <span class="badge bg-warning-light text-warning">{{ $serviciosOftalmologicosPendientesFacturar }} pendientes de facturar</span>
                                    <span class="badge bg-danger-light text-danger">{{ $serviciosOftalmologicosCanceladas + $serviciosOftalmologicosAusentes }} pérdida</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="servicios_oftalmologicos"
                         data-detail-segment="realizada"
                         data-bs-toggle="tooltip"
                         title="Filtra servicios oftalmológicos realizados. Breakdown: {{ $serviciosOftalmologicosFacturadas }} facturadas, {{ $serviciosOftalmologicosRealizadaConsulta }} con consulta.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="servicios_oftalmologicos:realizada" data-bs-toggle="tooltip" title="Abrir resumen rápido de servicios realizados">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('servicios_oftalmologicos', 'realizada') }}" class="btn btn-light btn-sm border" data-detail-link="servicios_oftalmologicos:realizada" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Servicios realizados</h6>
                            <div class="fs-28 fw-700 text-success">{{ $serviciosOftalmologicosRealizadas }}</div>
                            <small class="text-muted">{{ $serviciosOftalmologicosFacturadas }}
                                facturadas, {{ $serviciosOftalmologicosRealizadaConsulta }} con consulta</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="servicios_oftalmologicos"
                         data-detail-segment="pendiente_facturar"
                         data-bs-toggle="tooltip"
                         title="Filtra servicios oftalmológicos pendientes de facturar.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="servicios_oftalmologicos:pendiente_facturar" data-bs-toggle="tooltip" title="Abrir resumen rápido de servicios pendientes de facturar">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('servicios_oftalmologicos', 'pendiente_facturar') }}" class="btn btn-light btn-sm border" data-detail-link="servicios_oftalmologicos:pendiente_facturar" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Pendiente de facturar</h6>
                            <div
                                class="fs-28 fw-700 text-warning">{{ $serviciosOftalmologicosPendientesFacturar }}</div>
                            <small class="text-muted">Atenciones con respaldo clínico aún sin billing real</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="servicios_oftalmologicos"
                         data-detail-segment="cancelada"
                         data-bs-toggle="tooltip"
                         title="Filtra servicios oftalmológicos cancelados y ausentes.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="servicios_oftalmologicos:cancelada" data-bs-toggle="tooltip" title="Abrir resumen rápido de servicios cancelados o ausentes">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('servicios_oftalmologicos', 'cancelada') }}" class="btn btn-light btn-sm border" data-detail-link="servicios_oftalmologicos:cancelada" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Canceladas</h6>
                            <div class="fs-28 fw-700 text-danger">{{ $serviciosOftalmologicosCanceladas }}</div>
                            <small class="text-muted">{{ $serviciosOftalmologicosAusentes }} ausentes / sin
                                atención</small>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 col-12">
                    <div class="box kpi-filter-card"
                         data-detail-block="servicios_oftalmologicos"
                         data-detail-segment="sin_tarifa_estimable"
                         data-bs-toggle="tooltip"
                         title="Filtra servicios oftalmológicos sin tarifa estimable. {{ $serviciosOftalmologicosSinCostoConfigurado }} con precio 0 van aparte.">
                        <div class="kpi-filter-actions">
                            <button type="button" class="btn btn-light btn-sm border" data-detail-modal="servicios_oftalmologicos:sin_tarifa_estimable" data-bs-toggle="tooltip" title="Abrir resumen rápido de servicios sin tarifa estimable">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <a href="{{ $detailSegmentUrl('servicios_oftalmologicos', 'sin_tarifa_estimable') }}" class="btn btn-light btn-sm border" data-detail-link="servicios_oftalmologicos:sin_tarifa_estimable" data-bs-toggle="tooltip" title="Abrir esta página con el filtro persistido">
                                <i class="mdi mdi-open-in-new"></i>
                            </a>
                        </div>
                        <div class="box-body text-center kpi-filter-body">
                            <span class="badge bg-primary-light text-primary kpi-filter-badge d-none">Filtro activo</span>
                            <h6 class="mb-5">Sin tarifa estimable</h6>
                            <div class="fs-28 fw-700 text-dark">{{ $serviciosOftalmologicosSinTarifaEstimable }}</div>
                            <small class="text-muted">Solo faltantes reales de
                                pricing. {{ $serviciosOftalmologicosSinCostoConfigurado }} con precio 0 van
                                aparte.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-4 col-md-6 col-12">
                    <div class="box">
                        <div class="box-body text-center">
                            <h6 class="mb-5">Honorario real servicios</h6>
                            <div class="fs-28 fw-700 text-success">
                                ${{ number_format($serviciosOftalmologicosHonorarioReal, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6 col-12">
                    <div class="box">
                        <div class="box-body text-center">
                            <h6 class="mb-5">Por cobrar estimado</h6>
                            <div class="fs-28 fw-700 text-warning">
                                ${{ number_format($serviciosOftalmologicosPorCobrarEstimado, 2) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-12 col-12">
                    <div class="box">
                        <div class="box-body text-center">
                            <h6 class="mb-5">Pérdida estimada</h6>
                            <div class="fs-28 fw-700 text-danger">
                                ${{ number_format($serviciosOftalmologicosPerdidaEstimada, 2) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-4 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0">Servicios por estado real</h5>
                        </div>
                        <div class="box-body">
                            <div id="serviciosOftalmologicosEstadoChart" style="min-height: 320px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0">Servicios con mayor por cobrar</h5>
                        </div>
                        <div class="box-body">
                            <div id="serviciosOftalmologicosPorCobrarDoctorChart" style="min-height: 320px;"></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0">Servicios con mayor pérdida estimada</h5>
                        </div>
                        <div class="box-body">
                            <div id="serviciosOftalmologicosPerdidaDoctorChart" style="min-height: 320px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif


        <div class="row">
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Categoría cliente: volumen + honorario</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <span
                                class="badge bg-primary-light text-primary">{{ $operativoFacturadas }} facturadas</span>
                            <span class="badge bg-warning-light text-warning">{{ $operativoPendientesFacturar }} pendientes</span>
                            <span class="badge bg-danger-light text-danger">{{ $operativoPerdidas }} pérdida</span>
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
                                    <th class="text-end">Ticket prom.</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td>PARTICULAR</td>
                                    <td class="text-end">{{ $particularCount }}</td>
                                    <td class="text-end">{{ number_format($particularShare, 2) }}%</td>
                                    <td class="text-end">${{ number_format($honorarioParticular, 2) }}</td>
                                    <td class="text-end">${{ number_format($ticketPromedioParticular, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>PRIVADO</td>
                                    <td class="text-end">{{ $privadoCount }}</td>
                                    <td class="text-end">{{ number_format($privadoShare, 2) }}%</td>
                                    <td class="text-end">${{ number_format($honorarioPrivado, 2) }}</td>
                                    <td class="text-end">${{ number_format($ticketPromedioPrivado, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="fw-600">TOTAL</td>
                                    <td class="text-end fw-700">{{ $totalAtenciones }}</td>
                                    <td class="text-end fw-700">100.00%</td>
                                    <td class="text-end fw-700">${{ number_format($totalHonorarioReal, 2) }}</td>
                                    <td class="text-end fw-700">
                                        ${{ number_format($ticketPromedioCategoriaTotal, 2) }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">{{ $insuranceBreakdownTitle }} (Polar)</h5>
                        <span class="badge bg-primary-light text-primary">{{ count($topAfiliaciones) }} valores</span>
                    </div>
                    <div class="box-body">
                        <div id="topAfiliacionesChart" style="min-height: 320px;"></div>
                        <div class="table-responsive mt-15" style="max-height: 240px;">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>{{ $insuranceBreakdownItemLabel }}</th>
                                    <th class="text-end">Cantidad</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($topAfiliaciones as $item)
                                    @php
                                        $cantidad = (int) ($item['cantidad'] ?? 0);
                                        $afiliacion = strtoupper(trim((string) ($item['afiliacion'] ?? 'SIN DATO')));
                                    @endphp
                                    <tr>
                                        <td>{{ $afiliacion !== '' ? $afiliacion : 'SIN DATO' }}</td>
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
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Calificación de rendimiento médico</h5>
                        <span
                            class="badge bg-success-light text-success">{{ count($doctorTicketPromedioRows) }} valores</span>
                    </div>
                    <div class="box-body">
                        <div id="doctorTicketPromedioChart" style="min-height: 300px;"></div>
                        <div class="alert alert-info py-10 px-15 mt-15 mb-0">
                            <small>
                                Score compuesto: <strong>40% producción</strong> + <strong>30% ticket</strong> +
                                <strong>20% atenciones pagadas</strong> - <strong>10% tasa 0</strong>. El ranking se
                                ordena por ese score.
                            </small>
                        </div>
                        <div class="table-responsive" style="max-height: 320px;">
                            <table class="table table-sm table-striped mb-0 mt-15">
                                <thead class="table-light">
                                <tr>
                                    <th>Médico</th>
                                    <th class="text-end">Atenc.</th>
                                    <th class="text-end">C/Hon.</th>
                                    <th class="text-end">% 0</th>
                                    <th class="text-end">Honorario real</th>
                                    <th class="text-end">Ticket prom. real</th>
                                    <th class="text-end">Score</th>
                                    <th class="text-end">Nivel</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($doctorTicketPromedioRows as $item)
                                    <tr>
                                        <td>{{ (string) ($item['valor'] ?? 'SIN DOCTOR') }}</td>
                                        <td class="text-end">{{ (int) ($item['cantidad_total'] ?? 0) }}</td>
                                        <td class="text-end">{{ (int) ($item['cantidad_con_honorario'] ?? 0) }}</td>
                                        <td class="text-end">{{ number_format(((float) ($item['tasa_cero'] ?? 0)) * 100, 2) }}%</td>
                                        <td class="text-end">${{ number_format((float) ($item['monto'] ?? 0), 2) }}</td>
                                        <td class="text-end">
                                            ${{ number_format((float) ($item['ticket_promedio'] ?? 0), 2) }}</td>
                                        <td class="text-end">{{ number_format((float) ($item['score_rendimiento'] ?? 0), 2) }}</td>
                                        <td class="text-end">{{ (string) ($item['clasificacion'] ?? 'POR REVISAR') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">Sin datos disponibles.</td>
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
                        <div class="fs-28 fw-700 text-success">${{ number_format($operativoHonorarioReal, 2) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Por cobrar estimado</h6>
                        <div class="fs-28 fw-700 text-warning">
                            ${{ number_format($operativoPorCobrarEstimado, 2) }}</div>
                        <small class="text-muted">{{ $operativoPendientesFacturar }} casos pendientes</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Pérdida estimada</h6>
                        <div class="fs-28 fw-700 text-danger">${{ number_format($operativoPerdidaEstimada, 2) }}</div>
                        <small class="text-muted">{{ $operativoPerdidas }} casos perdidos</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Potencial capturable</h6>
                        <div class="fs-28 fw-700 text-dark">${{ number_format($operativoPotencialCapturable, 2) }}</div>
                        <small class="text-muted">Honorario real + por cobrar estimado</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Cobro sobre realizadas</h6>
                        <div class="fs-28 fw-700 text-info">{{ number_format($operativoFacturacionRate, 2) }}%</div>
                        <small class="text-muted">{{ $operativoFacturadas }} facturadas de {{ $operativoRealizadas }}
                            realizadas</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Ticket pendiente</h6>
                        <div class="fs-28 fw-700 text-secondary">
                            ${{ number_format($operativoTicketPendiente, 2) }}</div>
                        <small class="text-muted">${{ number_format($operativoTicketFacturadoReal, 2) }} ticket
                            facturado real</small>
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
                        <div class="alert alert-info py-10 px-15 mt-15 mb-0">
                            <small>
                                `Con categoría` / `Sin categoría` se basa solo en si `referido_prefactura_por` fue
                                llenado o quedó vacío. No distingue si el paciente asistió, faltó o no generó cobro.
                                `USD` suma el honorario de todas las atenciones del período en esa categoría.
                                `Ticket prom.` = `USD acumulado / total de atenciones` de la categoría.
                            </small>
                        </div>
                        <div class="table-responsive mt-15" style="max-height: 320px;">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Valor</th>
                                    <th class="text-end">Atenciones</th>
                                    <th class="text-end">%</th>
                                    <th class="text-end">USD</th>
                                    <th class="text-end">Ticket prom.</th>
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
                                        <td>
                                            @if ($valor !== 'SIN DATO')
                                                <a href="{{ $referidoStrategicUrl($valor) }}" class="text-primary fw-600">
                                                    {{ strtoupper($valor) }}
                                                </a>
                                            @else
                                                {{ strtoupper($valor) }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ (int) ($item['cantidad'] ?? 0) }}</td>
                                        <td class="text-end">{{ number_format((float) ($item['porcentaje'] ?? 0), 2) }}
                                            %
                                        </td>
                                        <td class="text-end">${{ number_format((float) ($item['monto'] ?? 0), 2) }}</td>
                                        <td class="text-end">
                                            ${{ number_format((float) ($item['ticket_promedio'] ?? 0), 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Sin datos para el rango
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
                        <div class="alert alert-info py-10 px-15 mt-15 mb-0">
                            <small>
                                `Con categoría` / `Sin categoría` se basa solo en si `referido_prefactura_por` fue
                                llenado o quedó vacío. No distingue si el paciente asistió, faltó o no generó cobro.
                                `USD` suma el honorario de todas las evaluaciones del período hechas por esos pacientes
                                únicos.
                                `Ticket prom.` = `USD acumulado / pacientes únicos` de la categoría.
                            </small>
                        </div>
                        <div class="table-responsive mt-15" style="max-height: 320px;">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Valor</th>
                                    <th class="text-end">Pacientes únicos</th>
                                    <th class="text-end">%</th>
                                    <th class="text-end">USD acumulado</th>
                                    <th class="text-end">Ticket prom. paciente</th>
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
                                        <td>
                                            @if ($valor !== 'SIN DATO')
                                                <a href="{{ $referidoStrategicUrl($valor) }}" class="text-primary fw-600">
                                                    {{ strtoupper($valor) }}
                                                </a>
                                            @else
                                                {{ strtoupper($valor) }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ (int) ($item['cantidad'] ?? 0) }}</td>
                                        <td class="text-end">{{ number_format((float) ($item['porcentaje'] ?? 0), 2) }}
                                            %
                                        </td>
                                        <td class="text-end">${{ number_format((float) ($item['monto'] ?? 0), 2) }}</td>
                                        <td class="text-end">
                                            ${{ number_format((float) ($item['ticket_promedio'] ?? 0), 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Sin datos para el rango
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
                        <div class="alert alert-info py-10 px-15 mt-15 mb-0">
                            <small>
                                `Con categoría` / `Sin categoría` se basa solo en si `referido_prefactura_por` fue
                                llenado o quedó vacío. No distingue si el paciente asistió, faltó o no generó cobro.
                                `USD` suma el honorario de todas las atenciones de nuevo paciente del período en esa
                                categoría.
                                `Ticket prom.` = `USD acumulado / número de pacientes nuevos únicos` de la categoría.
                            </small>
                        </div>
                        <div class="table-responsive mt-15" style="max-height: 320px;">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Valor</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">%</th>
                                    <th class="text-end">USD</th>
                                    <th class="text-end">Ticket prom.</th>
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
                                        <td>
                                            @if ($valor !== 'SIN DATO')
                                                <a href="{{ $referidoStrategicUrl($valor) }}" class="text-primary fw-600">
                                                    {{ strtoupper($valor) }}
                                                </a>
                                            @else
                                                {{ strtoupper($valor) }}
                                            @endif
                                        </td>
                                        <td class="text-end">{{ (int) ($item['cantidad'] ?? 0) }}</td>
                                        <td class="text-end">{{ number_format((float) ($item['porcentaje'] ?? 0), 2) }}
                                            %
                                        </td>
                                        <td class="text-end">${{ number_format((float) ($item['monto'] ?? 0), 2) }}</td>
                                        <td class="text-end">
                                            ${{ number_format((float) ($item['ticket_promedio'] ?? 0), 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Sin datos para el rango
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
                                    <th>Estado caso</th>
                                    <th>Tipo atención</th>
                                    <th>Fecha</th>
                                    <th>Procedimiento</th>
                                    <th>Doctor</th>
                                    <th>Facturación</th>
                                    <th>Tarifa</th>
                                    <th>Estimado</th>
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
                                        $estadoRealizacion = strtoupper(trim((string) ($row['estado_realizacion'] ?? '')));
                                        if ($estadoRealizacion === '') {
                                            $estadoRealizacion = '—';
                                        }
                                        $estadoRealizacionBadge = in_array($estadoRealizacion, ['FACTURADA', 'REALIZADA_CONSULTA', 'REALIZADA_CON_ARCHIVOS', 'REALIZADA_INFORMADA'], true)
                                            || str_contains($estadoRealizacion, 'OPERADA')
                                            ? 'bg-success'
                                            : (in_array($estadoRealizacion, ['CANCELADA', 'AUSENTE'], true)
                                                ? 'bg-danger'
                                                : ($estadoRealizacion === 'SIN_CIERRE_OPERATIVO' ? 'bg-warning' : 'bg-secondary'));
                                        $estadoFacturacionOperativa = strtoupper(trim((string) ($row['estado_facturacion_operativa'] ?? '')));
                                        if ($estadoFacturacionOperativa === '') {
                                            $estadoFacturacionOperativa = $facturado ? 'FACTURADA' : 'SIN FACTURACION';
                                        }
                                        $tarifaStatus = strtoupper(trim((string) ($row['tarifa_lookup_status'] ?? '')));
                                        $tarifaReason = trim((string) ($row['tarifa_lookup_reason'] ?? ''));
                                        $tarifaCodigo = trim((string) ($row['tarifa_codigo'] ?? ''));
                                        $tarifaLevelTitle = trim((string) ($row['tarifa_level_title'] ?? ''));
                                        $tarifaLevelKey = trim((string) ($row['tarifa_level_key'] ?? ''));
                                        $tarifaLevelLabel = $tarifaLevelTitle !== '' ? strtoupper($tarifaLevelTitle) : ($tarifaLevelKey !== '' ? strtoupper($tarifaLevelKey) : '—');
                                        $tarifaCodigoMatch = trim((string) ($row['tarifa_codigo_match'] ?? ''));
                                        $tarifaDescripcionMatch = trim((string) ($row['tarifa_descripcion_match'] ?? ''));
                                        $tarifaSinCostoConfigurado = (bool) ($row['tarifa_sin_costo_configurado'] ?? false);
                                        $sinTarifaEstimable = (bool) ($row['sin_tarifa_estimable'] ?? false);
                                        $tarifaClasificacion = $tarifaSinCostoConfigurado
                                            ? 'COSTO 0 CONFIGURADO'
                                            : ($sinTarifaEstimable ? 'SIN TARIFA ESTIMABLE' : '');
                                        $tarifaBadgeClass = $tarifaSinCostoConfigurado
                                            ? 'bg-info-light text-info'
                                            : ($sinTarifaEstimable ? 'bg-danger-light text-danger' : 'bg-success-light text-success');
                                        $montoEstimadoRow = (float) ($row['monto_por_cobrar_estimado'] ?? 0);
                                        if ($montoEstimadoRow <= 0) {
                                            $montoEstimadoRow = (float) ($row['monto_perdida_estimada'] ?? 0);
                                        }
                                        $formasPagoRow = trim((string) ($row['formas_pago'] ?? ''));
                                        $clienteFacturacionRow = trim((string) ($row['cliente_facturacion'] ?? ''));
                                        $areaFacturacionRow = trim((string) ($row['area_facturacion'] ?? ''));
                                        $estadoRealizacionData = strtoupper(trim((string) ($row['estado_realizacion'] ?? '')));
                                        $estadoFacturacionData = strtoupper(trim((string) ($row['estado_facturacion_operativa'] ?? '')));
                                        $sinTarifaEstimableData = (bool) ($row['sin_tarifa_estimable'] ?? false);
                                        $sinCostoConfiguradoData = (bool) ($row['tarifa_sin_costo_configurado'] ?? false);
                                        $alertaRevision = strtoupper(trim((string) ($row['alerta_revision'] ?? '')));
                                        $alertaRevisionLabel = $operationalAlertLabel($alertaRevision);
                                        $hasProtocolData = trim((string) ($row['protocolo_id'] ?? '')) !== ''
                                            || (int) ($row['protocolo_status_ok'] ?? 0) === 1
                                            || (int) ($row['protocolo_firmado'] ?? 0) === 1;
                                        $hasConsultaUtilData = trim((string) ($row['consulta_fecha'] ?? '')) !== ''
                                            || trim((string) ($row['consulta_diagnosticos'] ?? '')) !== '';
                                        $hasImageNasFilesData = (int) ($row['imagen_nas_has_files'] ?? 0) === 1
                                            || (int) ($row['imagen_nas_files_count'] ?? 0) > 0;
                                        $hasImageReportData = trim((string) ($row['imagen_informe_id'] ?? '')) !== ''
                                            || (int) ($row['imagen_informes_total'] ?? 0) > 0;
                                        $estadoEncuentroOperativo = $estadoEncuentro !== '—' ? strtoupper($estadoEncuentro) : 'SIN ESTADO DE AGENDA';
                                        $operationalReasonDetailed = $alertaRevisionLabel;

                                        if ($tipoAtencion === 'CIRUGIAS' && $estadoRealizacion === 'SIN_CIERRE_OPERATIVO') {
                                            $operationalReasonDetailed = 'Sin protocolo local y sin facturación registrada. La agenda quedó en ' . $estadoEncuentroOperativo . '.';
                                        } elseif ($tipoAtencion === 'IMAGENES' && $estadoRealizacion === 'SIN_CIERRE_OPERATIVO') {
                                            $operationalReasonDetailed = 'Sin archivos NAS, sin informe y sin facturación registrada. La agenda quedó en ' . $estadoEncuentroOperativo . '.';
                                        } elseif (in_array($tipoAtencion, ['PNI', 'SERVICIOS OFTALMOLOGICOS GENERALES'], true) && $alertaRevision === 'SIN_CIERRE') {
                                            $operationalReasonDetailed = 'No hay facturación registrada ni evidencia clínica suficiente de consulta. La agenda quedó en ' . $estadoEncuentroOperativo . '.';
                                        } elseif ($alertaRevision === 'AGENDA_DESACTUALIZADA') {
                                            $operationalReasonDetailed = 'Sí existe evidencia clínica o quirúrgica, pero la agenda siguió en ' . $estadoEncuentroOperativo . '.';
                                        } elseif ($alertaRevision === 'PENDIENTE_FACTURAR') {
                                            if ($tipoAtencion === 'CIRUGIAS') {
                                                $operationalReasonDetailed = 'La cirugía tiene respaldo clínico/protocolo, pero todavía no existe facturación real.';
                                            } elseif ($tipoAtencion === 'IMAGENES') {
                                                $operationalReasonDetailed = 'La imagen tiene evidencia técnica (' . ($hasImageReportData ? 'informe' : 'archivos NAS') . '), pero todavía no existe facturación real.';
                                            } else {
                                                $operationalReasonDetailed = 'La atención tiene respaldo clínico de consulta, pero todavía no existe facturación real.';
                                            }
                                        } elseif ($alertaRevision === 'ARCHIVOS_SIN_INFORME') {
                                            $operationalReasonDetailed = 'Hay archivos técnicos disponibles en NAS, pero todavía falta el informe.';
                                        } elseif ($alertaRevision === 'INFORMADA_SIN_ARCHIVOS_NAS') {
                                            $operationalReasonDetailed = 'Existe informe cargado, pero no se encontraron archivos técnicos en NAS.';
                                        } elseif ($alertaRevision === 'FACTURADA_SIN_ARCHIVOS_NI_INFORME') {
                                            $operationalReasonDetailed = 'Se encontró facturación, pero no hay ni archivos NAS ni informe disponibles.';
                                        } elseif ($alertaRevision === 'FACTURADA_SIN_PROTOCOLO_LOCAL') {
                                            $operationalReasonDetailed = 'Hay facturación real, pero no existe protocolo local asociado en CIVE.';
                                        } elseif ($alertaRevision === 'FACTURADA_SIN_FECHA_ATENCION') {
                                            $operationalReasonDetailed = 'Hay facturación real, pero falta registrar la fecha de atención.';
                                        } elseif ($alertaRevision === 'FACTURADA_SIN_HONORARIO') {
                                            $operationalReasonDetailed = 'Hay registro de facturación, pero el honorario real quedó en 0.';
                                        } elseif ($alertaRevision === 'ATENCION_POSTERIOR_A_FECHA_PROGRAMADA') {
                                            $operationalReasonDetailed = 'La fecha real de atención quedó posterior a la fecha programada.';
                                        }
                                    @endphp
                                    <tr
                                        data-tipo-atencion="{{ e($tipoAtencion) }}"
                                        data-estado-realizacion="{{ e($estadoRealizacionData) }}"
                                        data-estado-facturacion="{{ e($estadoFacturacionData) }}"
                                        data-sin-tarifa-estimable="{{ $sinTarifaEstimableData ? '1' : '0' }}"
                                        data-sin-costo-configurado="{{ $sinCostoConfiguradoData ? '1' : '0' }}"
                                        data-hc-number="{{ e((string) ($row['hc_number'] ?? '')) }}"
                                        data-paciente="{{ e(trim((string) ($row['nombre_completo'] ?? ''))) }}"
                                        data-fecha="{{ e($fechaFmt) }}"
                                        data-sede="{{ e($sede) }}"
                                        data-categoria-cliente="{{ e(strtoupper($categoriaCliente)) }}"
                                        data-afiliacion="{{ e($afiliacion) }}"
                                        data-procedimiento="{{ e($procedimientoLegible((string) ($row['procedimiento_proyectado'] ?? ''))) }}"
                                        data-doctor="{{ e(trim((string) ($row['doctor'] ?? '')) !== '' ? ucwords(strtolower((string) $row['doctor'])) : '—') }}"
                                        data-honorario-real="{{ e((string) round($honorarioRealRow, 2)) }}"
                                        data-por-cobrar="{{ e((string) round((float) ($row['monto_por_cobrar_estimado'] ?? 0), 2)) }}"
                                        data-perdida="{{ e((string) round((float) ($row['monto_perdida_estimada'] ?? 0), 2)) }}"
                                        data-fecha-raw="{{ e($fecha) }}"
                                        data-facturado="{{ $facturado ? '1' : '0' }}"
                                        data-patient-url="{{ e($patientDetailsUrl((string) ($row['hc_number'] ?? ''))) }}"
                                        data-alerta-revision="{{ e($alertaRevision) }}"
                                        data-alerta-revision-label="{{ e($alertaRevisionLabel) }}"
                                        data-operational-reason="{{ e($operationalReasonDetailed) }}"
                                        data-estado-encuentro="{{ e($estadoEncuentro) }}"
                                        data-estado-informe-operativo="{{ e(strtoupper(trim((string) ($row['estado_informe_operativo'] ?? '')))) }}"
                                    >
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ (string) ($row['hc_number'] ?? '—') }}</td>
                                        <td>
                                            @php
                                                $patientName = ucwords(strtolower(trim((string) ($row['nombre_completo'] ?? '—'))));
                                                $patientUrl = $patientDetailsUrl((string) ($row['hc_number'] ?? ''));
                                            @endphp
                                            @if($patientUrl !== '#')
                                                <a href="{{ $patientUrl }}" class="detail-patient-link" target="_blank" rel="noopener noreferrer">
                                                    {{ $patientName }}
                                                </a>
                                            @else
                                                {{ $patientName }}
                                            @endif
                                        </td>
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
                                                <span class="badge {{ $estadoRealizacionBadge }}">
                                                    {{ str_replace('_', ' ', $estadoRealizacion) }}
                                                </span>
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
                                                <span class="badge {{
                                                    str_contains($estadoFacturacionOperativa, 'FACTURADA') ? 'bg-success' :
                                                    ($estadoFacturacionOperativa === 'PENDIENTE_FACTURAR' ? 'bg-warning' : 'bg-secondary')
                                                }}">
                                                    {{ str_replace('_', ' ', $estadoFacturacionOperativa) }}
                                                </span>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="badge {{ $tarifaBadgeClass }}">
                                                    {{ $tarifaClasificacion !== '' ? $tarifaClasificacion : ($tarifaStatus !== '' ? $tarifaStatus : 'OK') }}
                                                </span>
                                            </div>
                                            <small class="d-block text-muted mt-5">
                                                {{ $tarifaCodigo !== '' ? $tarifaCodigo : '—' }}
                                                / {{ $tarifaLevelLabel }}
                                            </small>
                                            @if($tarifaStatus !== '' || $tarifaReason !== '' || $tarifaCodigoMatch !== '' || $tarifaDescripcionMatch !== '')
                                                <small class="d-block text-muted">
                                                    {{ $tarifaStatus !== '' ? $tarifaStatus : 'SIN_DIAGNOSTICO' }}
                                                    @if($tarifaReason !== '')
                                                        · {{ $tarifaReason }}
                                                    @endif
                                                    @if($tarifaCodigoMatch !== '' || $tarifaDescripcionMatch !== '')
                                                        ·
                                                        MATCH: {{ $tarifaCodigoMatch !== '' ? $tarifaCodigoMatch : '—' }}
                                                        @if($tarifaDescripcionMatch !== '')
                                                            {{ strtoupper($tarifaDescripcionMatch) }}
                                                        @endif
                                                    @endif
                                                </small>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $montoEstimadoRow > 0 ? '$' . number_format($montoEstimadoRow, 2) : '—' }}</td>
                                        <td class="text-end">${{ number_format($honorarioRealRow, 2) }}</td>
                                        <td>{{ $fechaFacturacionFmt }}</td>
                                        <td>{{ $facturaRef !== '' ? $facturaRef : '—' }}</td>
                                        <td>{{ $formasPagoRow !== '' ? strtoupper($formasPagoRow) : '—' }}</td>
                                        <td>{{ $clienteFacturacionRow !== '' ? strtoupper($clienteFacturacionRow) : '—' }}</td>
                                        <td>{{ $areaFacturacionRow !== '' ? strtoupper($areaFacturacionRow) : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="21" class="text-center text-muted py-30">No hay atenciones
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
    @if (\App\Modules\Shared\Support\MedforgeAssets::hasViteBuild())
        @vite('resources/js/v2/billing-informe-particulares.js')
    @else
        <script src="/assets/vendor_components/datatable/datatables.min.js"></script>
        <script src="/js/pages/shared/datatables-language-es.js"></script>
        <script src="/assets/vendor_components/apexcharts-bundle/dist/apexcharts.js"></script>
    @endif
    <script>
        (function () {
            let initialized = false;

            const dependenciesReady = function () {
                return typeof window.ApexCharts !== 'undefined'
                    && !!(window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.DataTable === 'function');
            };

            const boot = function () {
                if (initialized || !dependenciesReady()) {
                    return;
                }

                initialized = true;

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
                const doctorTicketPromedioRows = @json($doctorTicketPromedioRows);
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
                const pniEstados = @json($pniEstados);
                const pniDoctoresPorCobrar = @json($pniDoctoresPorCobrar);
                const pniDoctoresPerdida = @json($pniDoctoresPerdida);
                const imagenesEstados = @json($imagenesEstados);
                const imagenesEstadosInforme = @json($imagenesEstadosInforme);
                const imagenesDoctoresPorCobrar = @json($imagenesDoctoresPorCobrar);
                const imagenesDoctoresPerdida = @json($imagenesDoctoresPerdida);
                const serviciosOftalmologicosEstados = @json($serviciosOftalmologicosEstados);
                const serviciosOftalmologicosDoctoresPorCobrar = @json($serviciosOftalmologicosDoctoresPorCobrar);
                const serviciosOftalmologicosDoctoresPerdida = @json($serviciosOftalmologicosDoctoresPerdida);
                const cirugiasEstados = @json($cirugiasEstados);
                const cirugiasDoctoresPorCobrar = @json($cirugiasDoctoresPorCobrar);
                const cirugiasDoctoresPerdida = @json($cirugiasDoctoresPerdida);
                const initialDetailBlock = @json($detalleBloqueSeleccionado);
                const initialDetailSegment = @json($detalleSegmentoSeleccionado);

                const truncateLabel = function (value, maxLength) {
                    const text = String(value || '').trim();
                    if (text === '') {
                        return 'SIN DATO';
                    }

                    return text.length > maxLength ? text.slice(0, maxLength) + '...' : text;
                };

                const formatUsd = function (value) {
                    const amount = Number(value || 0);
                    const safeAmount = Number.isFinite(amount) ? amount : 0;
                    return '$' + safeAmount.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                };

                const escapeHtml = function (value) {
                    return String(value || '')
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');
                };

                const prettifyStatusLabel = function (value) {
                    return String(value || '—').replaceAll('_', ' ');
                };

                const buildDetailChip = function (label, tone) {
                    return '<span class="detail-chip detail-chip-' + tone + '">' + escapeHtml(label || '—') + '</span>';
                };

                const getSedeChipTone = function (value) {
                    const normalized = String(value || '').trim().toUpperCase();
                    if (normalized === 'CEIBOS') {
                        return 'sky';
                    }
                    if (normalized === 'MATRIZ') {
                        return 'amber';
                    }
                    return 'slate';
                };

                const getCategoriaChipTone = function (value) {
                    const normalized = String(value || '').trim().toUpperCase();
                    if (normalized === 'PARTICULAR') {
                        return 'emerald';
                    }
                    if (normalized === 'PRIVADO') {
                        return 'rose';
                    }
                    return 'slate';
                };

                const getEstadoRealChipTone = function (value) {
                    const normalized = String(value || '').trim().toUpperCase();
                    if (['FACTURADA', 'REALIZADA_CONSULTA', 'REALIZADA_CON_ARCHIVOS', 'REALIZADA_INFORMADA', 'OPERADA_CONFIRMADA', 'OPERADA_CON_PROTOCOLO', 'OPERADA_OTRO_CENTRO'].includes(normalized)) {
                        return 'emerald';
                    }
                    if (normalized === 'SIN_CIERRE_OPERATIVO' || normalized === 'PENDIENTE_FACTURAR') {
                        return 'amber';
                    }
                    if (normalized === 'CANCELADA' || normalized === 'AUSENTE') {
                        return 'rose';
                    }
                    return 'slate';
                };

                const getFacturacionChipTone = function (value) {
                    const normalized = String(value || '').trim().toUpperCase();
                    if (normalized.includes('FACTURADA')) {
                        return 'emerald';
                    }
                    if (normalized === 'PENDIENTE_FACTURAR') {
                        return 'amber';
                    }
                    return 'slate';
                };

                const buildAmountCell = function (value, toneClass) {
                    return '<span class="detail-amount ' + toneClass + '">' + formatUsd(value || 0) + '</span>';
                };

                const explainOperationalAlert = function (item) {
                    if (String(item.operationalReason || '').trim() !== '') {
                        return item.operationalReason;
                    }

                    const alert = String(item.alertaRevision || '').trim().toUpperCase();
                    if (alert !== '' && item.alertaRevisionLabel !== '') {
                        return item.alertaRevisionLabel;
                    }

                    if (item.estadoRealizacion === 'SIN_CIERRE_OPERATIVO') {
                        if (item.block === 'cirugias') {
                            return 'No hay protocolo, no hay facturación y la agenda no quedó marcada como cancelada.';
                        }
                        if (item.block === 'imagenes') {
                            return 'No hay archivos NAS, no hay informe, no hay facturación y la agenda no quedó como cancelada o ausente.';
                        }
                    }

                    return 'Sin observación operativa adicional.';
                };

                const buildVerticalChart = function (selector, title, values, color, config) {
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
                                formatter: function (value, opts) {
                                    const row = Array.isArray(values) ? (values[opts.dataPointIndex] || {}) : {};
                                    const amount = Number(row && row.monto ? row.monto : 0);
                                    const ticket = Number(row && row.ticket_promedio ? row.ticket_promedio : 0);
                                    const divisor = Number(row && row.divisor_ticket ? row.divisor_ticket : 0);
                                    const amountLabel = config && config.amountLabel ? config.amountLabel : 'Monto';
                                    const ticketLabel = config && config.ticketLabel ? config.ticketLabel : 'Ticket';
                                    const divisorLabel = config && config.divisorLabel ? config.divisorLabel : '';

                                    return value + ' registros | ' + amountLabel + ' ' + formatUsd(amount) + (divisorLabel !== '' ? ' | ' + divisorLabel + ' ' + divisor : '') + ' | ' + ticketLabel + ' ' + formatUsd(ticket);
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
                    const categoryTickets = [
                        {{ json_encode(round($ticketPromedioParticular, 2)) }},
                        {{ json_encode(round($ticketPromedioPrivado, 2)) }},
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
                                    formatter: function (value, opts) {
                                        const ticket = Number(categoryTickets[opts.dataPointIndex] || 0);
                                        return Number(value || 0) + ' atenciones | Ticket $' + ticket.toFixed(2);
                                    }
                                }, {
                                    formatter: function (value, opts) {
                                        const ticket = Number(categoryTickets[opts.dataPointIndex] || 0);
                                        return '$' + Number(value || 0).toFixed(2) + ' | Ticket $' + ticket.toFixed(2);
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
                buildHorizontalChart(
                    '#pniEstadoChart',
                    'PNI por estado real',
                    Array.isArray(pniEstados) ? pniEstados : [],
                    '#10b981'
                );
                buildHorizontalMoneyChart('#pniPorCobrarDoctorChart', pniDoctoresPorCobrar, {
                    seriesName: 'Por cobrar estimado',
                    xTitle: 'Por cobrar estimado',
                    color: '#f59e0b',
                });
                buildHorizontalMoneyChart('#pniPerdidaDoctorChart', pniDoctoresPerdida, {
                    seriesName: 'Pérdida estimada',
                    xTitle: 'Pérdida estimada',
                    color: '#dc2626',
                });
                buildHorizontalChart(
                    '#imagenesEstadoChart',
                    'Imágenes por estado real',
                    Array.isArray(imagenesEstados) ? imagenesEstados : [],
                    '#06b6d4'
                );
                buildHorizontalMoneyChart('#imagenesPorCobrarDoctorChart', imagenesDoctoresPorCobrar, {
                    seriesName: 'Por cobrar estimado',
                    xTitle: 'Por cobrar estimado',
                    color: '#f59e0b',
                });
                buildHorizontalMoneyChart('#imagenesPerdidaDoctorChart', imagenesDoctoresPerdida, {
                    seriesName: 'Pérdida estimada',
                    xTitle: 'Pérdida estimada',
                    color: '#dc2626',
                });
                buildHorizontalChart(
                    '#serviciosOftalmologicosEstadoChart',
                    'Servicios oftalmológicos por estado real',
                    Array.isArray(serviciosOftalmologicosEstados) ? serviciosOftalmologicosEstados : [],
                    '#2563eb'
                );
                buildHorizontalMoneyChart('#serviciosOftalmologicosPorCobrarDoctorChart', serviciosOftalmologicosDoctoresPorCobrar, {
                    seriesName: 'Por cobrar estimado',
                    xTitle: 'Por cobrar estimado',
                    color: '#f59e0b',
                });
                buildHorizontalMoneyChart('#serviciosOftalmologicosPerdidaDoctorChart', serviciosOftalmologicosDoctoresPerdida, {
                    seriesName: 'Pérdida estimada',
                    xTitle: 'Pérdida estimada',
                    color: '#dc2626',
                });
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

                const doctorTicketPromedioContainer = document.querySelector('#doctorTicketPromedioChart');
                if (doctorTicketPromedioContainer) {
                    const rows = Array.isArray(doctorTicketPromedioRows) ? doctorTicketPromedioRows : [];
                    if (rows.length === 0) {
                        doctorTicketPromedioContainer.innerHTML = '<div class="text-muted text-center py-30">Sin datos de médicos para graficar.</div>';
                    } else {
                        const dynamicHeight = Math.max(300, (rows.length * 34) + 80);
                        doctorTicketPromedioContainer.style.minHeight = dynamicHeight + 'px';

                        const doctorTicketChart = new ApexCharts(doctorTicketPromedioContainer, {
                            chart: {
                                type: 'bar',
                                height: dynamicHeight,
                                toolbar: {show: false},
                            },
                            series: [{
                                name: 'Score rendimiento',
                                data: rows.map(function (item) {
                                    return Number(item && item.score_rendimiento ? item.score_rendimiento : 0);
                                }),
                            }],
                            xaxis: {
                                categories: rows.map(function (item) {
                                    return truncateLabel(String(item && item.valor ? item.valor : 'SIN DOCTOR'), 26);
                                }),
                                labels: {
                                    formatter: function (value) {
                                        return Number(value || 0).toFixed(0);
                                    },
                                },
                                title: {
                                    text: 'Score de rendimiento',
                                },
                            },
                            yaxis: {
                                labels: {
                                    maxWidth: 220,
                                },
                            },
                            plotOptions: {
                                bar: {
                                    horizontal: true,
                                    borderRadius: 5,
                                    barHeight: '68%',
                                },
                            },
                            colors: ['#0f766e'],
                            dataLabels: {
                                enabled: true,
                                textAnchor: 'start',
                                offsetX: 6,
                                formatter: function (value, opts) {
                                    const row = rows[opts.dataPointIndex] || {};
                                    return Number(value || 0).toFixed(2) + ' | Tkt $' + Number(row && row.ticket_promedio ? row.ticket_promedio : 0).toFixed(2);
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
                                        const row = rows[opts.dataPointIndex] || {};
                                        const totalCount = Number(row && row.cantidad_total ? row.cantidad_total : 0);
                                        const billableCount = Number(row && row.cantidad_con_honorario ? row.cantidad_con_honorario : 0);
                                        const zeroRate = Number(row && row.tasa_cero ? row.tasa_cero : 0);
                                        const amount = Number(row && row.monto ? row.monto : 0);
                                        const ticket = Number(row && row.ticket_promedio ? row.ticket_promedio : 0);
                                        const level = String(row && row.clasificacion ? row.clasificacion : 'POR REVISAR');
                                        return 'Score ' + Number(value || 0).toFixed(2) + ' | ' + level + ' | Honorario ' + formatUsd(amount) + ' | Ticket ' + formatUsd(ticket) + ' | ' + billableCount + ' pagadas | ' + totalCount + ' totales | %0 ' + (zeroRate * 100).toFixed(2) + '%';
                                    },
                                },
                            },
                            grid: {
                                borderColor: '#eef1f4',
                            },
                        });

                        doctorTicketChart.render();
                    }
                }

                buildVerticalChart(
                    '#referidoPrefacturaChart',
                    'Atenciones (con categoría: ' + referidoWithValue + ', sin categoría: ' + referidoWithoutValue + ')',
                    referidoValues,
                    '#3b82f6',
                    {
                        amountLabel: 'USD acumulado del período:',
                        ticketLabel: 'Ticket promedio por atención:',
                    }
                );

                buildVerticalChart(
                    '#referidoPrefacturaPacientesUnicosChart',
                    'Pacientes únicos (con categoría: ' + referidoUniquePatientsWithValue + ', sin categoría: ' + referidoUniquePatientsWithoutValue + ')',
                    referidoUniquePatientValues,
                    '#1d4ed8',
                    {
                        amountLabel: 'USD acumulado del período:',
                        ticketLabel: 'Promedio por paciente único:',
                    }
                );

                buildVerticalChart(
                    '#referidoPrefacturaNuevoPacienteChart',
                    'Nuevos pacientes (con categoría: ' + referidoNuevoPacienteWithValue + ', sin categoría: ' + referidoNuevoPacienteWithoutValue + ')',
                    referidoNuevoPacienteValues,
                    '#2563eb',
                    {
                        amountLabel: 'USD acumulado del período:',
                        divisorLabel: 'Pacientes nuevos únicos:',
                        ticketLabel: 'Promedio por paciente nuevo único:',
                    }
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

                const initBootstrapTooltips = function () {
                    if (!window.bootstrap || typeof window.bootstrap.Tooltip !== 'function') {
                        return;
                    }

                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
                        if (!window.bootstrap.Tooltip.getInstance(element)) {
                            new window.bootstrap.Tooltip(element);
                        }
                    });
                };

                const detailBlockConfigs = {
                    operativo: {
                        label: 'Resumen operativo',
                        labelLower: 'resumen operativo',
                        type: '',
                        stateColor: '#2563eb',
                        moneyColors: {
                            porCobrar: '#f59e0b',
                            perdida: '#dc2626',
                        },
                        chartSelectors: {
                            state: '',
                            porCobrar: '',
                            perdida: '',
                        },
                        chartTitles: {
                            state: '',
                            porCobrar: '',
                            perdida: '',
                        },
                        segmentMeta: {
                            pipeline: {label: 'Casos del pipeline', chartLabel: 'pipeline'},
                            realizada: {label: 'Atenciones realizadas', chartLabel: 'realizadas'},
                            facturada: {label: 'Atenciones facturadas', chartLabel: 'facturadas'},
                            pendiente_facturar: {label: 'Pendientes de facturar', chartLabel: 'pendientes de facturar'},
                            perdida: {label: 'Casos no concretados', chartLabel: 'no concretados'},
                            pacientes_unicos_realizados: {label: 'Pacientes únicos atendidos', chartLabel: 'pacientes únicos atendidos'},
                        },
                    },
                    cirugias: {
                        label: 'Cirugías',
                        labelLower: 'cirugías',
                        type: 'CIRUGIAS',
                        stateColor: '#ef4444',
                        moneyColors: {
                            porCobrar: '#f59e0b',
                            perdida: '#dc2626',
                        },
                        chartSelectors: {
                            state: '#cirugiasEstadoChart',
                            porCobrar: '#cirugiasPorCobrarDoctorChart',
                            perdida: '#cirugiasPerdidaDoctorChart',
                        },
                        chartTitles: {
                            state: 'Cirugías por estado real',
                            porCobrar: 'Cirujanos con mayor por cobrar',
                            perdida: 'Cirujanos con mayor pérdida estimada',
                        },
                        segmentMeta: {
                            realizada: {label: 'Cirugías realizadas', chartLabel: 'realizadas'},
                            pendiente_facturar: {label: 'Pendientes de facturar', chartLabel: 'pendientes de facturar'},
                            cancelada: {label: 'Cirugías canceladas / sin cierre', chartLabel: 'canceladas / sin cierre'},
                            sin_tarifa_estimable: {label: 'Sin tarifa estimable', chartLabel: 'sin tarifa estimable'},
                        },
                    },
                    pni: {
                        label: 'PNI',
                        labelLower: 'PNI',
                        type: 'PNI',
                        stateColor: '#10b981',
                        moneyColors: {
                            porCobrar: '#f59e0b',
                            perdida: '#dc2626',
                        },
                        chartSelectors: {
                            state: '#pniEstadoChart',
                            porCobrar: '#pniPorCobrarDoctorChart',
                            perdida: '#pniPerdidaDoctorChart',
                        },
                        chartTitles: {
                            state: 'PNI por estado real',
                            porCobrar: 'PNI con mayor por cobrar',
                            perdida: 'PNI con mayor pérdida estimada',
                        },
                        segmentMeta: {
                            realizada: {label: 'PNI realizadas', chartLabel: 'realizadas'},
                            pendiente_facturar: {label: 'PNI pendientes de facturar', chartLabel: 'pendientes de facturar'},
                            cancelada: {label: 'PNI canceladas / ausentes', chartLabel: 'canceladas / ausentes'},
                            sin_tarifa_estimable: {label: 'PNI sin tarifa estimable', chartLabel: 'sin tarifa estimable'},
                        },
                    },
                    imagenes: {
                        label: 'Imágenes',
                        labelLower: 'imágenes',
                        type: 'IMAGENES',
                        stateColor: '#06b6d4',
                        moneyColors: {
                            porCobrar: '#f59e0b',
                            perdida: '#dc2626',
                        },
                        chartSelectors: {
                            state: '#imagenesEstadoChart',
                            porCobrar: '#imagenesPorCobrarDoctorChart',
                            perdida: '#imagenesPerdidaDoctorChart',
                        },
                        chartTitles: {
                            state: 'Imágenes por estado real',
                            porCobrar: 'Imágenes con mayor por cobrar',
                            perdida: 'Imágenes con mayor pérdida estimada',
                        },
                        segmentMeta: {
                            realizada: {label: 'Imágenes realizadas', chartLabel: 'realizadas'},
                            pendiente_facturar: {label: 'Imágenes pendientes de facturar', chartLabel: 'pendientes de facturar'},
                            cancelada: {label: 'Imágenes en pérdida operativa', chartLabel: 'pérdida operativa'},
                            sin_tarifa_estimable: {label: 'Imágenes sin tarifa estimable', chartLabel: 'sin tarifa estimable'},
                        },
                    },
                    servicios_oftalmologicos: {
                        label: 'Servicios oftalmológicos',
                        labelLower: 'servicios oftalmológicos',
                        type: 'SERVICIOS OFTALMOLOGICOS GENERALES',
                        stateColor: '#2563eb',
                        moneyColors: {
                            porCobrar: '#f59e0b',
                            perdida: '#dc2626',
                        },
                        chartSelectors: {
                            state: '#serviciosOftalmologicosEstadoChart',
                            porCobrar: '#serviciosOftalmologicosPorCobrarDoctorChart',
                            perdida: '#serviciosOftalmologicosPerdidaDoctorChart',
                        },
                        chartTitles: {
                            state: 'Servicios oftalmológicos por estado real',
                            porCobrar: 'Servicios con mayor por cobrar',
                            perdida: 'Servicios con mayor pérdida estimada',
                        },
                        segmentMeta: {
                            realizada: {label: 'Servicios realizados', chartLabel: 'realizados'},
                            pendiente_facturar: {label: 'Servicios pendientes de facturar', chartLabel: 'pendientes de facturar'},
                            cancelada: {label: 'Servicios cancelados / ausentes', chartLabel: 'cancelados / ausentes'},
                            sin_tarifa_estimable: {label: 'Servicios sin tarifa estimable', chartLabel: 'sin tarifa estimable'},
                        },
                    },
                };

                const normalizeDetailBlock = function (value) {
                    const normalized = String(value || '').trim().toLowerCase();
                    return Object.prototype.hasOwnProperty.call(detailBlockConfigs, normalized) ? normalized : '';
                };

                const normalizeDetailSegment = function (block, value) {
                    const normalizedBlock = normalizeDetailBlock(block);
                    if (normalizedBlock === '') {
                        return '';
                    }

                    const normalized = String(value || '').trim().toLowerCase();
                    return Object.prototype.hasOwnProperty.call(detailBlockConfigs[normalizedBlock].segmentMeta, normalized) ? normalized : '';
                };

                const rowDatasetToDetailItem = function (row) {
                    return {
                        tipoAtencion: String(row.dataset.tipoAtencion || '').trim().toUpperCase(),
                        estadoRealizacion: String(row.dataset.estadoRealizacion || '').trim().toUpperCase(),
                        estadoFacturacion: String(row.dataset.estadoFacturacion || '').trim().toUpperCase(),
                        sinTarifaEstimable: String(row.dataset.sinTarifaEstimable || '0') === '1',
                        sinCostoConfigurado: String(row.dataset.sinCostoConfigurado || '0') === '1',
                        hcNumber: String(row.dataset.hcNumber || '').trim(),
                        paciente: String(row.dataset.paciente || '').trim(),
                        fecha: String(row.dataset.fecha || '').trim(),
                        sede: String(row.dataset.sede || '').trim(),
                        categoriaCliente: String(row.dataset.categoriaCliente || '').trim(),
                        afiliacion: String(row.dataset.afiliacion || '').trim(),
                        procedimiento: String(row.dataset.procedimiento || '').trim(),
                        doctor: String(row.dataset.doctor || '').trim() || '—',
                        honorarioReal: Number(row.dataset.honorarioReal || 0),
                        porCobrar: Number(row.dataset.porCobrar || 0),
                        perdida: Number(row.dataset.perdida || 0),
                        fechaRaw: String(row.dataset.fechaRaw || '').trim(),
                        facturado: String(row.dataset.facturado || '0') === '1',
                        patientUrl: String(row.dataset.patientUrl || '').trim(),
                        alertaRevision: String(row.dataset.alertaRevision || '').trim().toUpperCase(),
                        alertaRevisionLabel: String(row.dataset.alertaRevisionLabel || '').trim(),
                        operationalReason: String(row.dataset.operationalReason || '').trim(),
                        estadoEncuentro: String(row.dataset.estadoEncuentro || '').trim().toUpperCase(),
                        estadoInformeOperativo: String(row.dataset.estadoInformeOperativo || '').trim().toUpperCase(),
                    };
                };

                const mapTipoAtencionToBlock = function (tipoAtencion) {
                    const normalized = String(tipoAtencion || '').trim().toUpperCase();
                    if (normalized === 'CIRUGIAS') {
                        return 'cirugias';
                    }
                    if (normalized === 'PNI') {
                        return 'pni';
                    }
                    if (normalized === 'IMAGENES') {
                        return 'imagenes';
                    }
                    if (normalized === 'SERVICIOS OFTALMOLOGICOS GENERALES') {
                        return 'servicios_oftalmologicos';
                    }

                    return '';
                };

                const allRenderedRows = Array.from(document.querySelectorAll('#tablaAtencionesRango tbody tr[data-tipo-atencion]'));
                const allDetailRows = allRenderedRows.map(function (row) {
                    const item = rowDatasetToDetailItem(row);
                    item.block = mapTipoAtencionToBlock(item.tipoAtencion);
                    return item;
                });
                const detailRows = allDetailRows.filter(function (item) {
                    return item.block !== '';
                });

                const attendedEncounterStates = ['ATENDIDO', 'ATENDIDA', 'REALIZADO', 'REALIZADA', 'COMPLETADO', 'COMPLETADA', 'ASISTIO', 'ASISTIÓ'];
                const cancelledEncounterStates = ['CANCELADO', 'CANCELADA'];
                const absentEncounterStates = ['AUSENTE', 'NO ASISTIO', 'NO ASISTIÓ', 'NO SHOW', 'NO-SHOW'];

                const isOperationalFactured = function (item) {
                    return item.facturado === true || ['FACTURADA', 'FACTURADA_EXTERNA'].includes(item.estadoFacturacion);
                };

                const isOperationalRealized = function (item) {
                    if (item.block === 'cirugias') {
                        return ['OPERADA_CONFIRMADA', 'OPERADA_CON_PROTOCOLO', 'OPERADA_OTRO_CENTRO'].includes(item.estadoRealizacion);
                    }
                    if (item.block === 'pni' || item.block === 'servicios_oftalmologicos') {
                        return ['FACTURADA', 'REALIZADA_CONSULTA'].includes(item.estadoRealizacion);
                    }
                    if (item.block === 'imagenes') {
                        return ['FACTURADA', 'REALIZADA_CON_ARCHIVOS', 'REALIZADA_INFORMADA'].includes(item.estadoRealizacion);
                    }

                    return attendedEncounterStates.includes(item.estadoEncuentro) || isOperationalFactured(item) || Number(item.honorarioReal || 0) > 0;
                };

                const isOperationalLost = function (item) {
                    if (item.block === 'cirugias') {
                        return ['CANCELADA', 'SIN_CIERRE_OPERATIVO'].includes(item.estadoRealizacion);
                    }
                    if (item.block === 'pni' || item.block === 'servicios_oftalmologicos') {
                        return ['CANCELADA', 'AUSENTE'].includes(item.estadoRealizacion);
                    }
                    if (item.block === 'imagenes') {
                        return ['CANCELADA', 'AUSENTE', 'SIN_CIERRE_OPERATIVO'].includes(item.estadoRealizacion);
                    }

                    return cancelledEncounterStates.includes(item.estadoEncuentro) || absentEncounterStates.includes(item.estadoEncuentro);
                };

                const rowMatchesDetailSegment = function (item, block, segment) {
                    const normalizedBlock = normalizeDetailBlock(block);
                    if (normalizedBlock === '') {
                        return false;
                    }

                    const normalizedSegment = normalizeDetailSegment(normalizedBlock, segment);

                    if (normalizedBlock === 'operativo') {
                        if (normalizedSegment === '') {
                            return true;
                        }
                        if (normalizedSegment === 'pipeline') {
                            return true;
                        }
                        if (normalizedSegment === 'realizada' || normalizedSegment === 'pacientes_unicos_realizados') {
                            return isOperationalRealized(item);
                        }
                        if (normalizedSegment === 'facturada') {
                            return isOperationalFactured(item);
                        }
                        if (normalizedSegment === 'pendiente_facturar') {
                            return item.estadoFacturacion === 'PENDIENTE_FACTURAR';
                        }
                        if (normalizedSegment === 'perdida') {
                            return isOperationalLost(item);
                        }

                        return true;
                    }

                    if (item.block !== normalizedBlock) {
                        return false;
                    }
                    if (normalizedSegment === '') {
                        return true;
                    }

                    if (normalizedBlock === 'cirugias') {
                        if (normalizedSegment === 'realizada') {
                            return ['OPERADA_CONFIRMADA', 'OPERADA_CON_PROTOCOLO', 'OPERADA_OTRO_CENTRO'].includes(item.estadoRealizacion);
                        }
                        if (normalizedSegment === 'pendiente_facturar') {
                            return item.estadoFacturacion === 'PENDIENTE_FACTURAR';
                        }
                        if (normalizedSegment === 'cancelada') {
                            return item.estadoRealizacion === 'CANCELADA' || item.estadoRealizacion === 'SIN_CIERRE_OPERATIVO';
                        }
                        if (normalizedSegment === 'sin_tarifa_estimable') {
                            return item.sinTarifaEstimable === true;
                        }
                    }

                    if (normalizedBlock === 'pni') {
                        if (normalizedSegment === 'realizada') {
                            return ['FACTURADA', 'REALIZADA_CONSULTA'].includes(item.estadoRealizacion);
                        }
                        if (normalizedSegment === 'pendiente_facturar') {
                            return item.estadoFacturacion === 'PENDIENTE_FACTURAR';
                        }
                        if (normalizedSegment === 'cancelada') {
                            return item.estadoRealizacion === 'CANCELADA' || item.estadoRealizacion === 'AUSENTE';
                        }
                        if (normalizedSegment === 'sin_tarifa_estimable') {
                            return item.sinTarifaEstimable === true;
                        }
                    }

                    if (normalizedBlock === 'imagenes') {
                        if (normalizedSegment === 'realizada') {
                            return ['FACTURADA', 'REALIZADA_CON_ARCHIVOS', 'REALIZADA_INFORMADA'].includes(item.estadoRealizacion);
                        }
                        if (normalizedSegment === 'pendiente_facturar') {
                            return item.estadoFacturacion === 'PENDIENTE_FACTURAR';
                        }
                        if (normalizedSegment === 'cancelada') {
                            return ['CANCELADA', 'AUSENTE', 'SIN_CIERRE_OPERATIVO'].includes(item.estadoRealizacion);
                        }
                        if (normalizedSegment === 'sin_tarifa_estimable') {
                            return item.sinTarifaEstimable === true;
                        }
                    }

                    if (normalizedBlock === 'servicios_oftalmologicos') {
                        if (normalizedSegment === 'realizada') {
                            return ['FACTURADA', 'REALIZADA_CONSULTA'].includes(item.estadoRealizacion);
                        }
                        if (normalizedSegment === 'pendiente_facturar') {
                            return item.estadoFacturacion === 'PENDIENTE_FACTURAR';
                        }
                        if (normalizedSegment === 'cancelada') {
                            return item.estadoRealizacion === 'CANCELADA' || item.estadoRealizacion === 'AUSENTE';
                        }
                        if (normalizedSegment === 'sin_tarifa_estimable') {
                            return item.sinTarifaEstimable === true;
                        }
                    }

                    return true;
                };

                const getFilteredDetailRows = function (block, segment) {
                    const normalizedBlock = normalizeDetailBlock(block);
                    const normalizedSegment = normalizeDetailSegment(normalizedBlock, segment);
                    if (normalizedBlock === '') {
                        return [];
                    }

                    const sourceRows = normalizedBlock === 'operativo' ? allDetailRows : detailRows;
                    return sourceRows.filter(function (item) {
                        return rowMatchesDetailSegment(item, normalizedBlock, normalizedSegment);
                    });
                };

                const summarizeUniqueValues = function (values, limit) {
                    const uniqueValues = Array.from(new Set(values.map(function (value) {
                        return String(value || '').trim();
                    }).filter(function (value) {
                        return value !== '' && value !== '—';
                    })));

                    if (uniqueValues.length === 0) {
                        return '—';
                    }

                    const maxItems = Number(limit || 2);
                    if (uniqueValues.length <= maxItems) {
                        return uniqueValues.join(', ');
                    }

                    return uniqueValues.slice(0, maxItems).join(', ') + ' +' + (uniqueValues.length - maxItems);
                };

                const buildUniquePatientRows = function (rows) {
                    const patientsMap = new Map();

                    rows.filter(function (item) {
                        return isOperationalRealized(item);
                    }).forEach(function (item) {
                        const key = String(item.hcNumber || '').trim();
                        if (key === '') {
                            return;
                        }

                        if (!patientsMap.has(key)) {
                            patientsMap.set(key, {
                                hcNumber: key,
                                paciente: item.paciente || '—',
                                patientUrl: item.patientUrl || '',
                                fechaRaw: item.fechaRaw || '',
                                fecha: item.fecha || '—',
                                sedes: [],
                                categorias: [],
                                afiliaciones: [],
                                procedimientos: [],
                                doctores: [],
                                atenciones: 0,
                                facturadas: 0,
                                honorarioReal: 0,
                                porCobrar: 0,
                                perdida: 0,
                            });
                        }

                        const patient = patientsMap.get(key);
                        patient.atenciones += 1;
                        patient.facturadas += isOperationalFactured(item) ? 1 : 0;
                        patient.honorarioReal += Number(item.honorarioReal || 0);
                        patient.porCobrar += Number(item.porCobrar || 0);
                        patient.perdida += Number(item.perdida || 0);
                        patient.sedes.push(item.sede || '');
                        patient.categorias.push(item.categoriaCliente || '');
                        patient.afiliaciones.push(item.afiliacion || '');
                        patient.procedimientos.push(item.procedimiento || '');
                        patient.doctores.push(item.doctor || '');

                        const currentDate = String(item.fechaRaw || '').trim();
                        if (currentDate !== '' && (patient.fechaRaw === '' || currentDate > patient.fechaRaw)) {
                            patient.fechaRaw = currentDate;
                            patient.fecha = item.fecha || patient.fecha;
                        }
                    });

                    return Array.from(patientsMap.values()).map(function (patient) {
                        return Object.assign({}, patient, {
                            sedeResumen: summarizeUniqueValues(patient.sedes, 2),
                            categoriaResumen: summarizeUniqueValues(patient.categorias, 2),
                            afiliacionResumen: summarizeUniqueValues(patient.afiliaciones, 2),
                            procedimientoResumen: summarizeUniqueValues(patient.procedimientos, 2),
                            doctorResumen: summarizeUniqueValues(patient.doctores, 2),
                        });
                    }).sort(function (a, b) {
                        return Number(b.honorarioReal || 0) - Number(a.honorarioReal || 0);
                    });
                };

                const mapCountsToMetricValues = function (countsMap) {
                    const entries = Array.from(countsMap.entries()).sort(function (a, b) {
                        return Number(b[1] || 0) - Number(a[1] || 0);
                    });
                    const total = entries.reduce(function (carry, entry) {
                        return carry + Number(entry[1] || 0);
                    }, 0);

                    return entries.map(function (entry) {
                        const label = String(entry[0] || 'SIN DATO').replaceAll('_', ' ');
                        const count = Number(entry[1] || 0);
                        return {
                            valor: label,
                            cantidad: count,
                            porcentaje: total > 0 ? Number(((count / total) * 100).toFixed(2)) : 0,
                        };
                    });
                };

                const mapAmountsToMetricValues = function (amountsMap) {
                    const entries = Array.from(amountsMap.entries()).sort(function (a, b) {
                        return Number(b[1] || 0) - Number(a[1] || 0);
                    });
                    const total = entries.reduce(function (carry, entry) {
                        return carry + Number(entry[1] || 0);
                    }, 0);

                    return entries.map(function (entry) {
                        const label = String(entry[0] || 'SIN DATO');
                        const amount = Number(entry[1] || 0);
                        return {
                            valor: label,
                            monto: Number(amount.toFixed(2)),
                            porcentaje: total > 0 ? Number(((amount / total) * 100).toFixed(2)) : 0,
                        };
                    });
                };

                const destroyChartIfNeeded = function (chart) {
                    if (chart && typeof chart.destroy === 'function') {
                        chart.destroy();
                    }
                };

                const detailCharts = Object.keys(detailBlockConfigs).reduce(function (carry, block) {
                    carry[block] = {state: null, porCobrar: null, perdida: null};
                    return carry;
                }, {});

                const renderDetailStateChart = function (block, rows, segment) {
                    const config = detailBlockConfigs[block];
                    const container = config ? document.querySelector(config.chartSelectors.state) : null;
                    if (!config || !container) {
                        return;
                    }

                    destroyChartIfNeeded(detailCharts[block].state);
                    detailCharts[block].state = null;
                    container.innerHTML = '';

                    const countsMap = new Map();
                    rows.forEach(function (item) {
                        const key = String(item.estadoRealizacion || 'SIN DATO').trim().toUpperCase() || 'SIN DATO';
                        countsMap.set(key, Number(countsMap.get(key) || 0) + 1);
                    });

                    const values = mapCountsToMetricValues(countsMap);
                    if (values.length === 0) {
                        container.innerHTML = '<div class="text-muted text-center py-30">Sin datos para graficar.</div>';
                        return;
                    }

                    const counts = values.map(function (item) {
                        return Number(item.cantidad || 0);
                    });
                    const dynamicHeight = Math.max(320, (counts.length * 28) + 90);
                    container.style.minHeight = dynamicHeight + 'px';

                    detailCharts[block].state = new ApexCharts(container, {
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
                            categories: values.map(function (item) {
                                return String(item.valor || 'SIN DATO').toUpperCase();
                            }),
                        },
                        plotOptions: {
                            bar: {
                                horizontal: true,
                                borderRadius: 4,
                            },
                        },
                        colors: [config.stateColor],
                        title: {
                            text: segment !== '' ? (config.chartTitles.state + ': ' + config.segmentMeta[segment].chartLabel) : config.chartTitles.state,
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
                                },
                            },
                        },
                        grid: {
                            borderColor: '#eef1f4',
                        },
                    });

                    detailCharts[block].state.render();
                };

                const renderDetailMoneyChart = function (block, rows, amountKey, segment) {
                    const config = detailBlockConfigs[block];
                    const selector = config ? config.chartSelectors[amountKey] : '';
                    const container = selector ? document.querySelector(selector) : null;
                    if (!config || !container) {
                        return;
                    }

                    destroyChartIfNeeded(detailCharts[block][amountKey]);
                    detailCharts[block][amountKey] = null;
                    container.innerHTML = '';

                    const amountsMap = new Map();
                    rows.forEach(function (item) {
                        const amount = Number(item[amountKey] || 0);
                        if (amount <= 0) {
                            return;
                        }
                        const doctor = String(item.doctor || 'SIN DOCTOR').trim() || 'SIN DOCTOR';
                        amountsMap.set(doctor, Number(amountsMap.get(doctor) || 0) + amount);
                    });

                    const values = mapAmountsToMetricValues(amountsMap);
                    if (values.length === 0) {
                        container.innerHTML = '<div class="text-muted text-center py-30">Sin datos para graficar.</div>';
                        return;
                    }

                    const dynamicHeight = Math.max(320, (values.length * 42) + 70);
                    container.style.minHeight = dynamicHeight + 'px';
                    const titleSuffix = segment !== '' ? (' (' + config.segmentMeta[segment].chartLabel + ')') : '';

                    detailCharts[block][amountKey] = new ApexCharts(container, {
                        chart: {
                            type: 'bar',
                            height: dynamicHeight,
                            toolbar: {show: false},
                        },
                        series: [{
                            name: amountKey === 'porCobrar' ? 'Por cobrar estimado' : 'Pérdida estimada',
                            data: values.map(function (item) {
                                return Number(item.monto || 0);
                            }),
                        }],
                        xaxis: {
                            categories: values.map(function (item) {
                                return truncateLabel(String(item.valor || 'SIN DOCTOR').toUpperCase(), 32);
                            }),
                            labels: {
                                formatter: function (value) {
                                    return '$' + Number(value || 0).toFixed(0);
                                },
                            },
                            title: {
                                text: amountKey === 'porCobrar' ? 'Por cobrar estimado' : 'Pérdida estimada',
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
                        colors: [config.moneyColors[amountKey]],
                        title: {
                            text: config.chartTitles[amountKey] + titleSuffix,
                            align: 'left',
                            style: {fontSize: '13px'},
                        },
                        dataLabels: {
                            enabled: true,
                            textAnchor: 'start',
                            offsetX: 6,
                            formatter: function (value, opts) {
                                const row = values[opts.dataPointIndex] || {porcentaje: 0};
                                return '$' + Number(value || 0).toFixed(2) + ' (' + Number(row.porcentaje || 0).toFixed(2) + '%)';
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
                                    const row = values[opts.dataPointIndex] || {porcentaje: 0};
                                    return '$' + Number(value || 0).toFixed(2) + ' | ' + Number(row.porcentaje || 0).toFixed(2) + '% del total';
                                },
                            },
                        },
                        grid: {
                            borderColor: '#eef1f4',
                        },
                    });

                    detailCharts[block][amountKey].render();
                };

                const renderBlockCharts = function (block, segment) {
                    const normalizedBlock = normalizeDetailBlock(block);
                    const normalizedSegment = normalizeDetailSegment(normalizedBlock, segment);
                    if (normalizedBlock === '') {
                        return;
                    }

                    const rows = getFilteredDetailRows(normalizedBlock, normalizedSegment);
                    renderDetailStateChart(normalizedBlock, rows, normalizedSegment);
                    renderDetailMoneyChart(normalizedBlock, rows, 'porCobrar', normalizedSegment);
                    renderDetailMoneyChart(normalizedBlock, rows, 'perdida', normalizedSegment);
                };

                const renderAllDetailCharts = function (activeBlock, activeSegment) {
                    Object.keys(detailBlockConfigs).forEach(function (block) {
                        const segment = activeBlock === block ? activeSegment : '';
                        renderBlockCharts(block, segment);
                    });
                };

                const buildDetailSegmentUrl = function (block, segment) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('cirugias_segmento');
                    url.searchParams.delete('detalle_bloque');
                    url.searchParams.delete('detalle_segmento');

                    const normalizedBlock = normalizeDetailBlock(block);
                    const normalizedSegment = normalizeDetailSegment(normalizedBlock, segment);
                    if (normalizedBlock !== '' && normalizedSegment !== '') {
                        url.searchParams.set('detalle_bloque', normalizedBlock);
                        url.searchParams.set('detalle_segmento', normalizedSegment);
                    }

                    return url.toString();
                };

                const updateDetailFilterUrl = function (block, segment) {
                    window.history.replaceState({}, '', buildDetailSegmentUrl(block, segment));
                };

                const detailCards = Array.from(document.querySelectorAll('[data-detail-block][data-detail-segment]'));
                const detailFilterBadges = Object.fromEntries(Object.keys(detailBlockConfigs).map(function (block) {
                    return [block, document.querySelector('[data-detail-filter-badge="' + block + '"]')];
                }));
                const detailClearButtons = Object.fromEntries(Object.keys(detailBlockConfigs).map(function (block) {
                    return [block, document.querySelector('[data-detail-clear="' + block + '"]')];
                }));

                const detailSegmentModalElement = document.getElementById('detailSegmentModal');
                const detailSegmentModal = window.bootstrap && detailSegmentModalElement ? new window.bootstrap.Modal(detailSegmentModalElement) : null;
                const detailSegmentModalTitle = document.getElementById('detailSegmentModalLabel');
                const detailSegmentModalSubtitle = document.getElementById('detailSegmentModalSubtitle');
                const detailSegmentModalHead = document.getElementById('detailSegmentModalHead');
                const detailSegmentModalBody = document.getElementById('detailSegmentModalBody');
                const detailSegmentModalCountLabel = document.getElementById('detailSegmentModalCountLabel');
                const detailSegmentModalCount = document.getElementById('detailSegmentModalCount');
                const detailSegmentModalPorCobrar = document.getElementById('detailSegmentModalPorCobrar');
                const detailSegmentModalPerdida = document.getElementById('detailSegmentModalPerdida');
                const detailSegmentModalDetail = document.getElementById('detailSegmentModalDetail');
                const detailSegmentModalDeepLink = document.getElementById('detailSegmentModalDeepLink');
                const detailSegmentModalExport = document.getElementById('detailSegmentModalExport');

                let currentDetailBlock = normalizeDetailBlock(initialDetailBlock);
                let currentDetailSegment = normalizeDetailSegment(currentDetailBlock, initialDetailSegment);
                let modalDetailBlock = '';
                let modalDetailSegment = '';
                let modalDetailRows = [];
                let modalDetailMode = 'rows';

                const updateDetailFilterUi = function (block, segment) {
                    detailCards.forEach(function (card) {
                        const cardBlock = normalizeDetailBlock(card.getAttribute('data-detail-block'));
                        const cardSegment = normalizeDetailSegment(cardBlock, card.getAttribute('data-detail-segment'));
                        const isActive = block !== '' && segment !== '' && cardBlock === block && cardSegment === segment;
                        card.classList.toggle('kpi-filter-card-active', isActive);
                        const badge = card.querySelector('.kpi-filter-badge');
                        if (badge) {
                            badge.classList.toggle('d-none', !isActive);
                        }
                    });

                    Object.keys(detailBlockConfigs).forEach(function (configBlock) {
                        const isActiveBlock = block === configBlock && segment !== '';
                        const badge = detailFilterBadges[configBlock];
                        const clearButton = detailClearButtons[configBlock];
                        if (badge) {
                            badge.classList.toggle('d-none', !isActiveBlock);
                            badge.textContent = isActiveBlock ? ('Filtro activo: ' + detailBlockConfigs[configBlock].segmentMeta[segment].label) : '';
                        }
                        if (clearButton) {
                            clearButton.classList.toggle('d-none', !isActiveBlock);
                        }
                    });
                };

                const renderDetailModal = function (block, segment) {
                    modalDetailBlock = normalizeDetailBlock(block);
                    modalDetailSegment = normalizeDetailSegment(modalDetailBlock, segment);
                    modalDetailRows = modalDetailBlock !== '' ? getFilteredDetailRows(modalDetailBlock, modalDetailSegment) : [];
                    modalDetailMode = modalDetailBlock === 'operativo' && modalDetailSegment === 'pacientes_unicos_realizados' ? 'patients' : 'rows';
                    if (!detailSegmentModalBody || !detailSegmentModalHead || modalDetailBlock === '') {
                        return;
                    }

                    const config = detailBlockConfigs[modalDetailBlock];
                    const label = modalDetailSegment !== '' ? config.segmentMeta[modalDetailSegment].label : ('Todos los casos de ' + config.labelLower);
                    const displayRows = modalDetailMode === 'patients' ? buildUniquePatientRows(modalDetailRows) : modalDetailRows;
                    const totalPorCobrar = displayRows.reduce(function (carry, item) { return carry + Number(item.porCobrar || 0); }, 0);
                    const totalPerdida = displayRows.reduce(function (carry, item) { return carry + Number(item.perdida || 0); }, 0);

                    if (detailSegmentModalTitle) {
                        detailSegmentModalTitle.textContent = label;
                    }
                    if (detailSegmentModalSubtitle) {
                        detailSegmentModalSubtitle.textContent = modalDetailMode === 'patients'
                            ? 'Resumen agrupado por HC para que el conteo coincida con el KPI de pacientes únicos atendidos.'
                            : 'Resumen rápido del subconjunto seleccionado dentro del bloque de ' + config.labelLower + '.';
                    }
                    if (detailSegmentModalCountLabel) {
                        detailSegmentModalCountLabel.textContent = modalDetailMode === 'patients' ? 'Pacientes' : 'Casos';
                    }
                    if (detailSegmentModalCount) {
                        detailSegmentModalCount.textContent = displayRows.length + (modalDetailMode === 'patients' ? ' pacientes' : ' casos');
                    }
                    if (detailSegmentModalPorCobrar) {
                        detailSegmentModalPorCobrar.textContent = formatUsd(totalPorCobrar) + ' por cobrar';
                    }
                    if (detailSegmentModalPerdida) {
                        detailSegmentModalPerdida.textContent = formatUsd(totalPerdida) + ' pérdida';
                    }
                    if (detailSegmentModalDeepLink) {
                        detailSegmentModalDeepLink.href = buildDetailSegmentUrl(modalDetailBlock, modalDetailSegment);
                    }

                    if (displayRows.length === 0) {
                        detailSegmentModalHead.innerHTML = modalDetailMode === 'patients'
                            ? '<tr><th>HC</th><th>Paciente</th><th>Última atención</th><th>Sede</th><th>Categoría</th><th>Afiliación</th><th>Procedimientos</th><th>Doctores</th><th class="text-end">Atenciones</th><th class="text-end">Facturadas</th><th class="text-end">Honorario</th><th class="text-end">Por cobrar</th><th class="text-end">Pérdida</th></tr>'
                            : '<tr><th>HC</th><th>Paciente</th><th>Fecha</th><th>Sede</th><th>Categoría</th><th>Afiliación</th><th>Procedimiento</th><th>Doctor</th><th>Estado real</th><th>Motivo operativo</th><th>Facturación</th><th class="text-end">Honorario</th><th class="text-end">Por cobrar</th><th class="text-end">Pérdida</th></tr>';
                        detailSegmentModalBody.innerHTML = '<tr><td colspan="' + (modalDetailMode === 'patients' ? '13' : '14') + '" class="text-center detail-segment-empty">Sin casos en este segmento.</td></tr>';
                        return;
                    }

                    if (modalDetailMode === 'patients') {
                        detailSegmentModalHead.innerHTML = '<tr><th>HC</th><th>Paciente</th><th>Última atención</th><th>Sede</th><th>Categoría</th><th>Afiliación</th><th>Procedimientos</th><th>Doctores</th><th class="text-end">Atenciones</th><th class="text-end">Facturadas</th><th class="text-end">Honorario</th><th class="text-end">Por cobrar</th><th class="text-end">Pérdida</th></tr>';
                        detailSegmentModalBody.innerHTML = displayRows.map(function (item) {
                            const patientLabel = escapeHtml(item.paciente || '—');
                            const patientContent = item.patientUrl !== ''
                                ? ('<a href="' + escapeHtml(item.patientUrl) + '" class="detail-patient-link" target="_blank" rel="noopener noreferrer">' + patientLabel + '</a>')
                                : patientLabel;

                            return '<tr>' +
                                '<td><span class="detail-code-pill">' + escapeHtml(item.hcNumber || '—') + '</span></td>' +
                                '<td><div class="detail-cell-stack"><span class="detail-cell-main">' + patientContent + '</span></div></td>' +
                                '<td><div class="detail-cell-stack"><span class="detail-cell-main">' + escapeHtml(item.fecha || '—') + '</span><span class="detail-cell-sub">Última atención registrada</span></div></td>' +
                                '<td>' + buildDetailChip(item.sedeResumen || '—', getSedeChipTone(item.sedeResumen)) + '</td>' +
                                '<td>' + buildDetailChip(item.categoriaResumen || '—', getCategoriaChipTone(item.categoriaResumen)) + '</td>' +
                                '<td><div class="detail-cell-stack"><span class="detail-cell-main">' + escapeHtml(item.afiliacionResumen || '—') + '</span></div></td>' +
                                '<td><div class="detail-cell-stack"><span class="detail-cell-main">' + escapeHtml(item.procedimientoResumen || '—') + '</span></div></td>' +
                                '<td><div class="detail-cell-stack"><span class="detail-cell-main">' + escapeHtml(item.doctorResumen || '—') + '</span></div></td>' +
                                '<td class="text-end"><span class="detail-code-pill">' + Number(item.atenciones || 0) + '</span></td>' +
                                '<td class="text-end"><span class="detail-code-pill">' + Number(item.facturadas || 0) + '</span></td>' +
                                '<td class="text-end">' + buildAmountCell(item.honorarioReal || 0, 'detail-amount-neutral') + '</td>' +
                                '<td class="text-end">' + buildAmountCell(item.porCobrar || 0, 'detail-amount-warning') + '</td>' +
                                '<td class="text-end">' + buildAmountCell(item.perdida || 0, 'detail-amount-danger') + '</td>' +
                                '</tr>';
                        }).join('');
                        return;
                    }

                    detailSegmentModalHead.innerHTML = '<tr><th>HC</th><th>Paciente</th><th>Fecha</th><th>Sede</th><th>Categoría</th><th>Afiliación</th><th>Procedimiento</th><th>Doctor</th><th>Estado real</th><th>Motivo operativo</th><th>Facturación</th><th class="text-end">Honorario</th><th class="text-end">Por cobrar</th><th class="text-end">Pérdida</th></tr>';
                    detailSegmentModalBody.innerHTML = displayRows.map(function (item) {
                        const patientLabel = escapeHtml(item.paciente || '—');
                        const patientContent = item.patientUrl !== ''
                            ? ('<a href="' + escapeHtml(item.patientUrl) + '" class="detail-patient-link" target="_blank" rel="noopener noreferrer">' + patientLabel + '</a>')
                            : patientLabel;
                        const operationalReason = explainOperationalAlert(item);
                        return '<tr>' +
                            '<td><span class="detail-code-pill">' + escapeHtml(item.hcNumber || '—') + '</span></td>' +
                            '<td><div class="detail-cell-stack"><span class="detail-cell-main">' + patientContent + '</span></div></td>' +
                            '<td><div class="detail-cell-stack"><span class="detail-cell-main">' + escapeHtml(item.fecha || '—') + '</span><span class="detail-cell-sub">Fecha de atención</span></div></td>' +
                            '<td>' + buildDetailChip(item.sede || '—', getSedeChipTone(item.sede)) + '</td>' +
                            '<td>' + buildDetailChip(item.categoriaCliente || '—', getCategoriaChipTone(item.categoriaCliente)) + '</td>' +
                            '<td><div class="detail-cell-stack"><span class="detail-cell-main">' + escapeHtml(item.afiliacion || '—') + '</span></div></td>' +
                            '<td><div class="detail-cell-stack"><span class="detail-cell-main">' + escapeHtml(item.procedimiento || '—') + '</span><span class="detail-cell-sub">' + escapeHtml(config.label) + '</span></div></td>' +
                            '<td><div class="detail-cell-stack"><span class="detail-cell-main">' + escapeHtml(item.doctor || '—') + '</span></div></td>' +
                            '<td>' + buildDetailChip(prettifyStatusLabel(item.estadoRealizacion || '—'), getEstadoRealChipTone(item.estadoRealizacion)) + '</td>' +
                            '<td><div class="detail-cell-stack"><span class="detail-cell-sub-strong">' + escapeHtml(operationalReason) + '</span></div></td>' +
                            '<td>' + buildDetailChip(prettifyStatusLabel(item.estadoFacturacion || '—'), getFacturacionChipTone(item.estadoFacturacion)) + '</td>' +
                            '<td class="text-end">' + buildAmountCell(item.honorarioReal || 0, 'detail-amount-neutral') + '</td>' +
                            '<td class="text-end">' + buildAmountCell(item.porCobrar || 0, 'detail-amount-warning') + '</td>' +
                            '<td class="text-end">' + buildAmountCell(item.perdida || 0, 'detail-amount-danger') + '</td>' +
                            '</tr>';
                    }).join('');
                };

                const exportDetailRowsToCsv = function (rows, block, segment) {
                    const isPatientMode = block === 'operativo' && segment === 'pacientes_unicos_realizados';
                    const exportRows = isPatientMode ? buildUniquePatientRows(rows) : rows;
                    const header = isPatientMode
                        ? ['HC', 'Paciente', 'Ultima atencion', 'Sede', 'Categoria', 'Afiliacion', 'Procedimientos', 'Doctores', 'Atenciones realizadas', 'Facturadas', 'Honorario', 'Por cobrar', 'Perdida']
                        : ['HC', 'Paciente', 'Fecha', 'Sede', 'Categoria', 'Afiliacion', 'Procedimiento', 'Doctor', 'Estado real', 'Motivo operativo', 'Estado facturacion', 'Honorario', 'Por cobrar', 'Perdida'];
                    const csvRows = [header].concat(rows.map(function (item) {
                        if (isPatientMode) {
                            return [];
                        }

                        return [
                            item.hcNumber || '',
                            item.paciente || '',
                            item.fecha || '',
                            item.sede || '',
                            item.categoriaCliente || '',
                            item.afiliacion || '',
                            item.procedimiento || '',
                            item.doctor || '',
                            String(item.estadoRealizacion || '').replaceAll('_', ' '),
                            explainOperationalAlert(item),
                            String(item.estadoFacturacion || '').replaceAll('_', ' '),
                            Number(item.honorarioReal || 0).toFixed(2),
                            Number(item.porCobrar || 0).toFixed(2),
                            Number(item.perdida || 0).toFixed(2),
                        ];
                    })).filter(function (row) {
                        return row.length > 0;
                    });

                    if (isPatientMode) {
                        exportRows.forEach(function (item) {
                            csvRows.push([
                                item.hcNumber || '',
                                item.paciente || '',
                                item.fecha || '',
                                item.sedeResumen || '',
                                item.categoriaResumen || '',
                                item.afiliacionResumen || '',
                                item.procedimientoResumen || '',
                                item.doctorResumen || '',
                                Number(item.atenciones || 0),
                                Number(item.facturadas || 0),
                                Number(item.honorarioReal || 0).toFixed(2),
                                Number(item.porCobrar || 0).toFixed(2),
                                Number(item.perdida || 0).toFixed(2),
                            ]);
                        });
                    }

                    const csvContent = csvRows.map(function (row) {
                        return row.map(function (value) {
                            const text = String(value || '');
                            return '"' + text.replaceAll('"', '""') + '"';
                        }).join(',');
                    }).join('\n');

                    const blob = new Blob([csvContent], {type: 'text/csv;charset=utf-8;'});
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = (block || 'detalle') + '-' + (segment || 'todos') + '.csv';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                };

                const initDataTable = function (selector, options) {
                    if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.DataTable !== 'function') {
                        return null;
                    }

                    const $table = window.jQuery(selector);
                    if (!$table.length) {
                        return null;
                    }

                    if (window.jQuery.fn.dataTable.isDataTable($table)) {
                        $table.DataTable().destroy();
                    }

                    return $table.DataTable(Object.assign({
                        language: window.medforgeDataTableLanguageEs ? window.medforgeDataTableLanguageEs() : {},
                        pageLength: 10,
                        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                        deferRender: true,
                    }, options || {}));
                };

                const tablaAtencionesRangoDataTable = initDataTable('#tablaAtencionesRango', {
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                    order: [[8, 'desc']],
                });

                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable && window.jQuery.fn.dataTable.ext && Array.isArray(window.jQuery.fn.dataTable.ext.search)) {
                    window.jQuery.fn.dataTable.ext.search.push(function (settings, searchData, dataIndex) {
                        if (!settings || !settings.nTable || settings.nTable.id !== 'tablaAtencionesRango') {
                            return true;
                        }
                        if (currentDetailBlock === '' || currentDetailSegment === '') {
                            return true;
                        }

                        const rowMeta = settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
                        if (!rowMeta || !rowMeta.dataset) {
                            return true;
                        }

                        const item = rowDatasetToDetailItem(rowMeta);
                        item.block = mapTipoAtencionToBlock(item.tipoAtencion);
                        return rowMatchesDetailSegment(item, currentDetailBlock, currentDetailSegment);
                    });
                }

                const applyDetailFilter = function (block, segment, syncUrl) {
                    currentDetailBlock = normalizeDetailBlock(block);
                    currentDetailSegment = normalizeDetailSegment(currentDetailBlock, segment);
                    updateDetailFilterUi(currentDetailBlock, currentDetailSegment);
                    renderAllDetailCharts(currentDetailBlock, currentDetailSegment);

                    if (tablaAtencionesRangoDataTable) {
                        tablaAtencionesRangoDataTable.draw();
                    } else {
                        allRenderedRows.forEach(function (row) {
                            if (currentDetailBlock === '' || currentDetailSegment === '') {
                                row.classList.remove('d-none');
                                return;
                            }
                            const item = rowDatasetToDetailItem(row);
                            item.block = mapTipoAtencionToBlock(item.tipoAtencion);
                            row.classList.toggle('d-none', !rowMatchesDetailSegment(item, currentDetailBlock, currentDetailSegment));
                        });
                    }

                    if (syncUrl === true) {
                        updateDetailFilterUrl(currentDetailBlock, currentDetailSegment);
                    }
                };

                detailCards.forEach(function (card) {
                    card.addEventListener('click', function (event) {
                        if (event.target.closest('[data-detail-modal]') || event.target.closest('[data-detail-link]')) {
                            return;
                        }

                        const block = normalizeDetailBlock(card.getAttribute('data-detail-block'));
                        const segment = normalizeDetailSegment(block, card.getAttribute('data-detail-segment'));
                        const isSameFilter = currentDetailBlock === block && currentDetailSegment === segment;
                        applyDetailFilter(isSameFilter ? '' : block, isSameFilter ? '' : segment, true);
                    });
                });

                Array.from(document.querySelectorAll('[data-detail-modal]')).forEach(function (button) {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        const target = String(button.getAttribute('data-detail-modal') || '');
                        const parts = target.split(':');
                        const block = normalizeDetailBlock(parts[0] || '');
                        const segment = normalizeDetailSegment(block, parts[1] || '');
                        renderDetailModal(block, segment);
                        if (detailSegmentModal) {
                            detailSegmentModal.show();
                        }
                    });
                });

                Object.keys(detailClearButtons).forEach(function (block) {
                    const button = detailClearButtons[block];
                    if (!button) {
                        return;
                    }

                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        applyDetailFilter('', '', true);
                    });
                });

                if (detailSegmentModalDetail) {
                    detailSegmentModalDetail.addEventListener('click', function () {
                        applyDetailFilter(modalDetailBlock, modalDetailSegment, true);
                        if (detailSegmentModal) {
                            detailSegmentModal.hide();
                        }
                        const detailTable = document.getElementById('tablaAtencionesRango');
                        if (detailTable && typeof detailTable.scrollIntoView === 'function') {
                            detailTable.scrollIntoView({behavior: 'smooth', block: 'start'});
                        }
                    });
                }

                if (detailSegmentModalExport) {
                    detailSegmentModalExport.addEventListener('click', function () {
                        exportDetailRowsToCsv(modalDetailRows, modalDetailBlock || 'detalle', modalDetailSegment || 'todos');
                    });
                }

                applyDetailFilter(currentDetailBlock, currentDetailSegment, false);
                initBootstrapTooltips();

            };

            if (window.__MEDFORGE_BILLING_PARTICULARES_READY__ === true) {
                boot();
                return;
            }

            window.addEventListener('medforge:billing-informe-particulares:ready', boot, {once: true});
            boot();
        })();
    </script>
@endpush
