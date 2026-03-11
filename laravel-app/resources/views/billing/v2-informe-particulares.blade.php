@extends('layouts.medforge')

@php
    $filters = is_array($filters ?? null) ? $filters : [];
    $catalogos = is_array($catalogos ?? null) ? $catalogos : ['afiliaciones' => [], 'tipos_atencion' => [], 'sedes' => [], 'categorias' => []];
    $rows = is_array($rows ?? null) ? $rows : [];
    $summary = is_array($summary ?? null) ? $summary : [
        'total' => 0,
        'total_consultas' => 0,
        'total_protocolos' => 0,
        'pacientes_unicos' => 0,
        'categoria_counts' => ['particular' => 0, 'privado' => 0],
        'categoria_share' => ['particular' => 0, 'privado' => 0],
        'top_afiliaciones' => [],
        'referido_prefactura' => ['with_value' => 0, 'without_value' => 0, 'top_values' => [], 'values' => []],
        'especificar_referido_prefactura' => ['with_value' => 0, 'without_value' => 0, 'top_values' => [], 'values' => []],
        'hierarquia_referidos' => ['categorias' => [], 'pares' => []],
    ];

    $dateFromSeleccionado = trim((string) ($filters['date_from'] ?? ''));
    $dateToSeleccionado = trim((string) ($filters['date_to'] ?? ''));
    $afiliacionSeleccionada = strtolower(trim((string) ($filters['afiliacion'] ?? '')));
    $sedeSeleccionada = strtoupper(trim((string) ($filters['sede'] ?? '')));
    $categoriaClienteSeleccionada = strtolower(trim((string) ($filters['categoria_cliente'] ?? '')));
    $tipoSeleccionado = strtoupper(trim((string) ($filters['tipo'] ?? '')));
    $procedimientoSeleccionado = trim((string) ($filters['procedimiento'] ?? ''));

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
            <div class="ms-auto">
                <span class="badge bg-light text-primary">Fuente: LARAVEL V2</span>
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
                    <div class="col-md-2">
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

            $especificarSummary = is_array($summary['especificar_referido_prefactura'] ?? null) ? $summary['especificar_referido_prefactura'] : [];
            $especificarTop = is_array(($especificarSummary['top_values'] ?? null)) && isset($especificarSummary['top_values'][0])
                ? (array) $especificarSummary['top_values'][0]
                : [];
            $especificarValues = is_array($especificarSummary['values'] ?? null) ? $especificarSummary['values'] : [];
            $especificarWithValue = (int) ($especificarSummary['with_value'] ?? 0);
            $especificarWithoutValue = (int) ($especificarSummary['without_value'] ?? 0);
            $especificarTopLabel = trim((string) ($especificarTop['valor'] ?? ''));
            $especificarTopCount = (int) ($especificarTop['cantidad'] ?? 0);
            if ($especificarTopLabel === '') {
                $especificarTopLabel = 'Sin datos';
            }

            $hierarquiaReferidos = is_array($summary['hierarquia_referidos'] ?? null) ? $summary['hierarquia_referidos'] : [];
            $hierarquiaCategorias = is_array($hierarquiaReferidos['categorias'] ?? null) ? $hierarquiaReferidos['categorias'] : [];
            $hierarquiaPares = is_array($hierarquiaReferidos['pares'] ?? null) ? $hierarquiaReferidos['pares'] : [];

            $especificarTopTenValues = array_values(array_filter($especificarValues, static function ($item): bool {
                $valor = strtoupper(trim((string) ($item['valor'] ?? '')));
                return $valor !== '' && $valor !== 'SIN SUBCATEGORIA';
            }));
            $especificarTopTenValues = array_slice($especificarTopTenValues, 0, 10);
            if (empty($especificarTopTenValues)) {
                $especificarTopTenValues = array_slice($especificarValues, 0, 10);
            }

            $hierarquiaCategoriasGraficas = array_values(array_filter($hierarquiaCategorias, static function ($item): bool {
                $categoria = strtoupper(trim((string) ($item['categoria'] ?? '')));
                return $categoria !== '' && $categoria !== 'SIN CATEGORIA';
            }));
            if (empty($hierarquiaCategoriasGraficas)) {
                $hierarquiaCategoriasGraficas = $hierarquiaCategorias;
            }
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
                        <h6 class="mb-5">Origen: Consulta</h6>
                        <div class="fs-30 fw-700 text-primary">{{ $totalConsultas }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Origen: Protocolo</h6>
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
                        <h6 class="mb-5">Particular</h6>
                        <div class="fs-30 fw-700 text-success">{{ $particularCount }}</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-md-4 col-6">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Privado</h6>
                        <div class="fs-30 fw-700 text-danger">{{ $privadoCount }}</div>
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
            <div class="col-xl-6 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Categorías madre de referencia</h6>
                        <div class="fs-30 fw-700 text-primary">{{ $referidoWithValue }}</div>
                        <small class="text-muted">Principal: {{ strtoupper($referidoTopLabel) }}({{ $referidoTopCount }}
                            )</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-md-6 col-12">
                <div class="box">
                    <div class="box-body text-center">
                        <h6 class="mb-5">Subcategorías de referencia</h6>
                        <div class="fs-30 fw-700 text-info">{{ $especificarWithValue }}</div>
                        <small class="text-muted">Principal: {{ strtoupper($especificarTopLabel) }}
                            ({{ $especificarTopCount }})</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Distribución completa de categorías madre</h5>
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
            <div class="col-xl-6 col-12">
                <div class="box">
                    <div class="box-header with-border d-flex justify-content-between align-items-center">
                        <h5 class="box-title mb-0">Distribución completa de subcategorías</h5>
                        <span
                            class="badge bg-info-light text-info">Top 10 de {{ count($especificarValues) }} valores</span>
                    </div>
                    <div class="box-body">
                        <div id="especificarReferidoChart" style="min-height: 300px;"></div>
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
                                @forelse($especificarTopTenValues as $item)
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
                        <h5 class="box-title mb-0">Relación madre -> subcategoría (% dentro de cada categoría
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
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted py-30">No hay atenciones
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
            const especificarValues = @json($especificarTopTenValues);
            const referidoWithValue = @json($referidoWithValue);
            const referidoWithoutValue = @json($referidoWithoutValue);
            const especificarWithValue = @json($especificarWithValue);
            const especificarWithoutValue = @json($especificarWithoutValue);
            const particularCount = @json($particularCount);
            const privadoCount = @json($privadoCount);
            const topAfiliaciones = @json($topAfiliaciones);
            const hierarquiaCategorias = @json($hierarquiaCategoriasGraficas);

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
                '#especificarReferidoChart',
                'Subcategorías globales (con valor: ' + especificarWithValue + ', sin valor: ' + especificarWithoutValue + ')',
                especificarValues,
                '#06b6d4'
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
