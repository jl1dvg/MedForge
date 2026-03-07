@extends('layouts.medforge')

@php
    $filters = is_array($filters ?? null) ? $filters : [];
    $catalogos = is_array($catalogos ?? null) ? $catalogos : ['meses' => [], 'afiliaciones' => []];
    $groupedRows = is_array($groupedRows ?? null) ? $groupedRows : [];
    $summary = is_array($summary ?? null) ? $summary : ['total' => 0, 'top_afiliaciones' => []];

    $mesSeleccionado = trim((string) ($filters['mes'] ?? ''));
    $semanaSeleccionada = trim((string) ($filters['semana'] ?? ''));
    $afiliacionSeleccionada = strtolower(trim((string) ($filters['afiliacion'] ?? '')));
    $tipoSeleccionado = strtolower(trim((string) ($filters['tipo'] ?? '')));
    $procedimientoSeleccionado = trim((string) ($filters['procedimiento'] ?? ''));

    $monthLabels = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    $monthLabel = static function (string $yyyyMm) use ($monthLabels): string {
        if (preg_match('/^(\d{4})-(\d{2})$/', $yyyyMm, $parts) !== 1) {
            return $yyyyMm;
        }

        $year = (int) $parts[1];
        $month = (int) $parts[2];
        $label = $monthLabels[$month] ?? $yyyyMm;

        return $label . ' ' . $year;
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

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title"><i class="mdi mdi-file-chart-outline"></i> Informe de Atenciones Particulares</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
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
                        <label for="mes" class="form-label">Mes</label>
                        <select name="mes" id="mes" class="form-select">
                            <option value="">Todos los meses</option>
                            @foreach(($catalogos['meses'] ?? []) as $mesOption)
                                @php
                                    $mesValue = (string) ($mesOption['value'] ?? '');
                                    $mesLabel = (string) ($mesOption['label'] ?? $mesValue);
                                @endphp
                                <option value="{{ $mesValue }}" {{ $mesSeleccionado === $mesValue ? 'selected' : '' }}>
                                    {{ $mesLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="semana" class="form-label">Semana</label>
                        <select name="semana" id="semana" class="form-select">
                            <option value="">Todas</option>
                            <option value="1" {{ $semanaSeleccionada === '1' ? 'selected' : '' }}>Semana 1 (1-7)</option>
                            <option value="2" {{ $semanaSeleccionada === '2' ? 'selected' : '' }}>Semana 2 (8-14)</option>
                            <option value="3" {{ $semanaSeleccionada === '3' ? 'selected' : '' }}>Semana 3 (15-21)</option>
                            <option value="4" {{ $semanaSeleccionada === '4' ? 'selected' : '' }}>Semana 4 (22-28)</option>
                            <option value="5" {{ $semanaSeleccionada === '5' ? 'selected' : '' }}>Semana 5 (29+)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="afiliacion" class="form-label">Afiliación</label>
                        <select name="afiliacion" id="afiliacion" class="form-select">
                            <option value="">Todas</option>
                            @foreach(($catalogos['afiliaciones'] ?? []) as $afiliacion)
                                @php $afiliacionValue = strtolower(trim((string) $afiliacion)); @endphp
                                <option value="{{ $afiliacionValue }}" {{ $afiliacionSeleccionada === $afiliacionValue ? 'selected' : '' }}>
                                    {{ strtoupper($afiliacionValue) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select name="tipo" id="tipo" class="form-select">
                            <option value="">Todos</option>
                            <option value="consulta" {{ $tipoSeleccionado === 'consulta' ? 'selected' : '' }}>Consulta</option>
                            <option value="protocolo" {{ $tipoSeleccionado === 'protocolo' ? 'selected' : '' }}>Protocolo</option>
                        </select>
                    </div>
                    <div class="col-md-4">
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
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="mdi mdi-magnify me-5"></i>Aplicar filtros
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="/v2/informes/particulares" class="btn btn-light w-100">
                            <i class="mdi mdi-filter-remove me-5"></i>Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-9 col-12">
                <div class="box">
                    <div class="box-body">
                        @forelse($groupedRows as $mes => $rows)
                            <div class="d-flex align-items-center justify-content-between mb-15 mt-10">
                                <h4 class="mb-0">{{ $monthLabel((string) $mes) }}</h4>
                                <span class="badge bg-info-light text-primary fw-600">{{ count($rows) }} atenciones</span>
                            </div>
                            <div class="table-responsive mb-20">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="bg-primary-light">
                                    <tr>
                                        <th>#</th>
                                        <th>HC</th>
                                        <th>Nombre</th>
                                        <th>Afiliación</th>
                                        <th>Tipo</th>
                                        <th>Fecha</th>
                                        <th>Procedimiento</th>
                                        <th>Doctor</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($rows as $index => $row)
                                        @php
                                            $afiliacion = strtoupper(trim((string) ($row['afiliacion'] ?? '')));
                                            if ($afiliacion === '') {
                                                $afiliacion = '—';
                                            }
                                            $badgeClass = match ($afiliacion) {
                                                'PARTICULAR' => 'bg-primary',
                                                'HUMANA - COPAGO' => 'bg-info',
                                                'BEST DOCTOR 100' => 'bg-success',
                                                'SALUD (REEMBOLSO) NIVEL 5' => 'bg-warning',
                                                'FUNDACIONES' => 'bg-danger',
                                                default => 'bg-secondary',
                                            };
                                            $tipo = strtolower(trim((string) ($row['tipo'] ?? '')));
                                            $fecha = trim((string) ($row['fecha'] ?? ''));
                                            $fechaFmt = $fecha !== '' && strtotime($fecha) !== false ? date('d/m/Y', strtotime($fecha)) : '—';
                                        @endphp
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ (string) ($row['hc_number'] ?? '—') }}</td>
                                            <td>{{ ucwords(strtolower(trim((string) ($row['nombre_completo'] ?? '—')))) }}</td>
                                            <td><span class="badge {{ $badgeClass }}">{{ $afiliacion }}</span></td>
                                            <td>
                                                <span class="badge {{ $tipo === 'consulta' ? 'bg-primary' : 'bg-success' }}">
                                                    {{ $tipo === 'consulta' ? 'Consulta' : 'Protocolo' }}
                                                </span>
                                            </td>
                                            <td>{{ $fechaFmt }}</td>
                                            <td>{{ $procedimientoLegible((string) ($row['procedimiento_proyectado'] ?? '')) }}</td>
                                            <td>{{ trim((string) ($row['doctor'] ?? '')) !== '' ? ucwords(strtolower((string) $row['doctor'])) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @empty
                            <div class="text-center py-30 text-muted">
                                No hay atenciones particulares para los filtros seleccionados.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-12">
                <div class="box box-inverse box-success">
                    <div class="box-body text-center">
                        <h5 class="mb-5">Total de atenciones</h5>
                        <div class="fs-50 lh-1">{{ (int) ($summary['total'] ?? 0) }}</div>
                    </div>
                </div>

                <div class="box">
                    <div class="box-header with-border">
                        <h5 class="box-title mb-0">Top afiliaciones</h5>
                    </div>
                    <div class="box-body">
                        @php
                            $top = is_array($summary['top_afiliaciones'] ?? null) ? $summary['top_afiliaciones'] : [];
                            $maxTop = 0;
                            foreach ($top as $item) {
                                $maxTop = max($maxTop, (int) ($item['cantidad'] ?? 0));
                            }
                        @endphp

                        @forelse($top as $item)
                            @php
                                $cantidad = (int) ($item['cantidad'] ?? 0);
                                $af = (string) ($item['afiliacion'] ?? 'SIN AFILIACION');
                                $percent = $maxTop > 0 ? (int) round(($cantidad / $maxTop) * 100) : 0;
                            @endphp
                            <div class="mb-15">
                                <div class="d-flex justify-content-between mb-5">
                                    <span class="fw-600">{{ ucfirst(strtolower($af)) }}</span>
                                    <span class="text-primary">{{ $cantidad }}</span>
                                </div>
                                <div class="progress progress-sm mb-0">
                                    <div class="progress-bar bg-primary" style="width: {{ $percent }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Sin datos disponibles.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
