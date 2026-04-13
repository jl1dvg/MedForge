@extends('layouts.medforge')

@php
    $agendaRows = is_array($agendaRows ?? null) ? $agendaRows : [];
    $agendaMeta = is_array($agendaMeta ?? null) ? $agendaMeta : [];
    $filters = is_array($agendaMeta['filters'] ?? null) ? $agendaMeta['filters'] : [];
    $estados = array_values(array_filter((array) ($agendaMeta['estados_disponibles'] ?? []), static fn ($value) => trim((string) $value) !== ''));
    $sedes = array_values(array_filter((array) ($agendaMeta['sedes_disponibles'] ?? []), static fn ($value) => is_array($value) && trim((string) ($value['label'] ?? '')) !== ''));
    $doctores = array_values(array_filter(
        (array) ($agendaMeta['doctores_disponibles'] ?? []),
        static fn ($value) => is_array($value) && trim((string) ($value['label'] ?? '')) !== ''
    ));
    $tiposAtencion = array_values(array_filter((array) ($agendaMeta['tipos_atencion_disponibles'] ?? []), static fn ($value) => is_array($value)));
    $tiposAfiliacion = array_values(array_filter((array) ($agendaMeta['tipo_afiliacion_opciones'] ?? []), static fn ($value) => is_array($value)));
    $empresasAfiliacion = array_values(array_filter((array) ($agendaMeta['empresa_afiliacion_opciones'] ?? []), static fn ($value) => is_array($value)));
    $afiliaciones = array_values(array_filter((array) ($agendaMeta['afiliacion_opciones'] ?? []), static fn ($value) => is_array($value)));

    $fechaInicio = (string) ($filters['fecha_inicio'] ?? '');
    $fechaFin = (string) ($filters['fecha_fin'] ?? '');
    $tipoAtencionActual = (string) ($filters['tipo_atencion'] ?? '');
    $doctorActual = (string) ($filters['doctor'] ?? '');
    $estadoActual = (string) ($filters['estado'] ?? '');
    $sedeActual = (string) ($filters['sede'] ?? '');
    $tipoAfiliacionActual = (string) ($filters['tipo_afiliacion'] ?? '');
    $empresaAfiliacionActual = (string) ($filters['empresa_afiliacion'] ?? '');
    $afiliacionActual = (string) ($filters['afiliacion'] ?? '');
    $soloConVisita = (bool) ($filters['solo_con_visita'] ?? false);
    $total = (int) ($agendaMeta['count'] ?? count($agendaRows));
    $conConsulta = count(array_filter($agendaRows, static fn ($row) => (int) ($row->tiene_consulta ?? 0) === 1));
    $sinConsulta = max(0, $total - $conConsulta);

    $formatDate = static function (?string $value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    };

    $formatTime = static function (?string $value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        return strlen($value) >= 5 ? substr($value, 0, 5) : $value;
    };

    $statusBadgeClass = static function (?string $estado): string {
        $normalized = strtoupper(trim((string) $estado));

        return match ($normalized) {
            'AGENDADO' => 'agenda-status is-agendado',
            'CONFIRMADO' => 'agenda-status is-confirmado',
            'LLEGADO' => 'agenda-status is-llegado',
            'EN ATENCION', 'CONSULTA', 'OPTOMETRIA' => 'agenda-status is-en-atencion',
            'DILATAR' => 'agenda-status is-dilatar',
            'REALIZADO', 'ATENDIDO', 'CONSULTA_TERMINADO', 'OPTOMETRIA_TERMINADO' => 'agenda-status is-realizado',
            'AUSENTE', 'NO SHOW' => 'agenda-status is-ausente',
            'CANCELADO' => 'agenda-status is-cancelado',
            'REAGENDADO' => 'agenda-status is-reagendado',
            default => 'agenda-status is-default',
        };
    };

    $colorIndexFor = static function (?string $value, int $bucketCount): int {
        $normalized = mb_strtoupper(trim((string) $value), 'UTF-8');
        if ($normalized === '' || $bucketCount <= 0) {
            return 0;
        }

        return abs(crc32($normalized)) % $bucketCount;
    };

    $tipoAtencionBadgeClass = static function (?string $value) use ($colorIndexFor): string {
        $variants = [
            'agenda-tag agenda-tag-type-1',
            'agenda-tag agenda-tag-type-2',
            'agenda-tag agenda-tag-type-3',
            'agenda-tag agenda-tag-type-4',
        ];

        return $variants[$colorIndexFor($value, count($variants))];
    };

    $sedeBadgeClass = static function (?string $value) use ($colorIndexFor): string {
        $variants = [
            'agenda-tag agenda-tag-sede-1',
            'agenda-tag agenda-tag-sede-2',
        ];

        return $variants[$colorIndexFor($value, count($variants))];
    };

    $afiliacionBadgeClass = static function (?string $value) use ($colorIndexFor): string {
        $variants = [
            'agenda-tag agenda-tag-aff-1',
            'agenda-tag agenda-tag-aff-2',
            'agenda-tag agenda-tag-aff-3',
            'agenda-tag agenda-tag-aff-4',
            'agenda-tag agenda-tag-aff-5',
            'agenda-tag agenda-tag-aff-6',
        ];

        return $variants[$colorIndexFor($value, count($variants))];
    };
@endphp

@push('styles')
<style>
    .agenda-summary-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: #f3f6fb;
        color: #44546f;
        font-size: 12px;
        font-weight: 600;
    }

    .agenda-table td,
    .agenda-table th {
        vertical-align: middle;
        white-space: normal;
    }

    .agenda-table {
        width: 100% !important;
        font-size: 11px;
        line-height: 1.2;
    }

    .agenda-table td {
        padding: .4rem .45rem;
    }

    .agenda-table th {
        padding: .55rem .45rem;
        font-size: 10.5px;
        letter-spacing: .02em;
    }

    .agenda-table .agenda-procedimiento {
        min-width: 180px;
    }

    .agenda-table .agenda-detalle {
        min-width: 250px;
    }

    .agenda-table .agenda-paciente {
        min-width: 180px;
    }

    .agenda-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 110px;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .02em;
        text-transform: uppercase;
        border: 1px solid transparent;
    }

    .agenda-status.is-agendado {
        background: #e0f2fe;
        color: #075985;
        border-color: #7dd3fc;
    }

    .agenda-status.is-confirmado {
        background: #ede9fe;
        color: #5b21b6;
        border-color: #c4b5fd;
    }

    .agenda-status.is-llegado {
        background: #dcfce7;
        color: #166534;
        border-color: #86efac;
    }

    .agenda-status.is-en-atencion {
        background: #fef3c7;
        color: #92400e;
        border-color: #fcd34d;
    }

    .agenda-status.is-dilatar {
        background: #fce7f3;
        color: #9d174d;
        border-color: #f9a8d4;
    }

    .agenda-status.is-realizado {
        background: #dcfce7;
        color: #166534;
        border-color: #4ade80;
    }

    .agenda-status.is-ausente {
        background: #fff7ed;
        color: #9a3412;
        border-color: #fdba74;
    }

    .agenda-status.is-cancelado {
        background: #fee2e2;
        color: #b91c1c;
        border-color: #fca5a5;
    }

    .agenda-status.is-reagendado {
        background: #ecfccb;
        color: #3f6212;
        border-color: #bef264;
    }

    .agenda-status.is-default {
        background: #e5e7eb;
        color: #374151;
        border-color: #cbd5e1;
    }

    .agenda-tag {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        padding: 4px 8px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 700;
        line-height: 1.15;
        border: 1px solid transparent;
    }

    .agenda-tag-type-1 {
        background: #ecfeff;
        color: #155e75;
        border-color: #67e8f9;
    }

    .agenda-tag-type-2 {
        background: #eff6ff;
        color: #1d4ed8;
        border-color: #93c5fd;
    }

    .agenda-tag-type-3 {
        background: #f5f3ff;
        color: #6d28d9;
        border-color: #c4b5fd;
    }

    .agenda-tag-type-4 {
        background: #fff7ed;
        color: #c2410c;
        border-color: #fdba74;
    }

    .agenda-tag-sede-1 {
        background: #ecfccb;
        color: #3f6212;
        border-color: #a3e635;
    }

    .agenda-tag-sede-2 {
        background: #ffe4e6;
        color: #9f1239;
        border-color: #fda4af;
    }

    .agenda-tag-aff-1 {
        background: #fef3c7;
        color: #92400e;
        border-color: #fcd34d;
    }

    .agenda-tag-aff-2 {
        background: #dcfce7;
        color: #166534;
        border-color: #86efac;
    }

    .agenda-tag-aff-3 {
        background: #dbeafe;
        color: #1d4ed8;
        border-color: #93c5fd;
    }

    .agenda-tag-aff-4 {
        background: #fce7f3;
        color: #9d174d;
        border-color: #f9a8d4;
    }

    .agenda-tag-aff-5 {
        background: #ede9fe;
        color: #5b21b6;
        border-color: #c4b5fd;
    }

    .agenda-tag-aff-6 {
        background: #e2e8f0;
        color: #334155;
        border-color: #cbd5e1;
    }

    .agenda-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .agenda-icon-button {
        width: 28px;
        height: 28px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
    }

    .agenda-visit-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1200;
        padding: 20px;
    }

    .agenda-visit-modal {
        width: min(960px, 100%);
        max-height: 90vh;
        overflow: auto;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
    }

    .agenda-visit-header,
    .agenda-visit-footer {
        padding: 16px 20px;
        border-bottom: 1px solid #edf1f7;
    }

    .agenda-visit-footer {
        border-top: 1px solid #edf1f7;
        border-bottom: 0;
        display: flex;
        justify-content: flex-end;
    }

    .agenda-visit-body {
        padding: 20px;
    }

    .agenda-visit-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 18px;
    }

    .agenda-visit-card {
        background: #f8fafc;
        border: 1px solid #edf1f7;
        border-radius: 12px;
        padding: 12px 14px;
    }

    .agenda-visit-card .label {
        display: block;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #7a8699;
        margin-bottom: 5px;
        font-weight: 700;
    }

    .agenda-autorefresh {
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
    }
</style>
@endpush

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Agenda</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Agenda</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="row">
            <div class="col-12">
                <div class="box">
                    <div class="box-body">
                        <form method="get" action="/v2/agenda" class="row g-3 align-items-end">
                            <div class="col-xl-2 col-md-4">
                                <label for="fecha_inicio" class="form-label">Desde</label>
                                <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" value="{{ $fechaInicio }}">
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label for="fecha_fin" class="form-label">Hasta</label>
                                <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" value="{{ $fechaFin }}">
                            </div>
                            <div class="col-xl-3 col-md-4">
                                <label for="tipo_atencion" class="form-label">Tipo atención</label>
                                <select id="tipo_atencion" name="tipo_atencion" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach($tiposAtencion as $option)
                                        <option value="{{ (string) ($option['value'] ?? '') }}" @selected($tipoAtencionActual === (string) ($option['value'] ?? ''))>
                                            {{ (string) ($option['label'] ?? '') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-3 col-md-4">
                                <label for="doctor" class="form-label">Doctor</label>
                                <select id="doctor" name="doctor" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach($doctores as $doctor)
                                        <option value="{{ (string) ($doctor['value'] ?? '') }}" @selected($doctorActual === (string) ($doctor['value'] ?? ''))>
                                            {{ (string) ($doctor['label'] ?? '') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label for="estado" class="form-label">Estado</label>
                                <select id="estado" name="estado" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach($estados as $estado)
                                        <option value="{{ $estado }}" @selected($estadoActual === $estado)>{{ $estado }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label for="sede" class="form-label">Sede</label>
                                <select id="sede" name="sede" class="form-select">
                                    <option value="">Todas</option>
                                    @foreach($sedes as $sede)
                                        <option value="{{ (string) ($sede['value'] ?? '') }}" @selected($sedeActual === (string) ($sede['value'] ?? ''))>
                                            {{ (string) ($sede['label'] ?? '') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label for="tipo_afiliacion" class="form-label">Tipo afiliación</label>
                                <select id="tipo_afiliacion" name="tipo_afiliacion" class="form-select">
                                    @foreach($tiposAfiliacion as $option)
                                        <option value="{{ (string) ($option['value'] ?? '') }}" @selected($tipoAfiliacionActual === (string) ($option['value'] ?? ''))>
                                            {{ (string) ($option['label'] ?? '') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label for="empresa_afiliacion" class="form-label">Empresa aseguradora</label>
                                <select id="empresa_afiliacion" name="empresa_afiliacion" class="form-select">
                                    @foreach($empresasAfiliacion as $option)
                                        <option value="{{ (string) ($option['value'] ?? '') }}" @selected($empresaAfiliacionActual === (string) ($option['value'] ?? ''))>
                                            {{ (string) ($option['label'] ?? '') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <label for="afiliacion" class="form-label">Afiliación</label>
                                <select id="afiliacion" name="afiliacion" class="form-select">
                                    @foreach($afiliaciones as $option)
                                        <option value="{{ (string) ($option['value'] ?? '') }}" @selected($afiliacionActual === (string) ($option['value'] ?? ''))>
                                            {{ (string) ($option['label'] ?? '') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-xl-1 col-md-4">
                                <div class="form-check mt-4 pt-2">
                                    <input type="checkbox" id="solo_con_visita" name="solo_con_visita" value="1" class="form-check-input" @checked($soloConVisita)>
                                    <label for="solo_con_visita" class="form-check-label">Visita</label>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="mdi mdi-filter-outline me-5"></i>Filtrar
                                </button>
                            </div>
                            <div class="col-xl-2 col-md-4">
                                <a href="/v2/agenda" class="btn btn-light w-100">
                                    <i class="mdi mdi-filter-remove-outline me-5"></i>Limpiar
                                </a>
                            </div>
                        </form>

                        <div class="d-flex flex-wrap gap-10 align-items-center mt-20">
                            <span class="agenda-summary-chip">
                                <i class="mdi mdi-calendar-range"></i>
                                {{ $fechaInicio !== '' || $fechaFin !== '' ? ($formatDate($fechaInicio) . ' al ' . $formatDate($fechaFin)) : 'Sin rango de fecha' }}
                            </span>
                            <span class="agenda-summary-chip">
                                <i class="mdi mdi-format-list-bulleted"></i>
                                {{ number_format($total) }} registros
                            </span>
                            <span class="agenda-summary-chip">
                                <i class="mdi mdi-file-document-edit-outline"></i>
                                {{ number_format($sinConsulta) }} sin consulta
                            </span>
                            @if($soloConVisita)
                                <span class="agenda-summary-chip">
                                    <i class="mdi mdi-account-check-outline"></i>
                                    Solo con visita
                                </span>
                            @endif
                            <span class="agenda-autorefresh" data-agenda-refresh-label>
                                Actualización automática cada 60 segundos
                            </span>
                        </div>

                        @if(!empty($loadError))
                            <div class="alert alert-danger mt-20 mb-0">
                                {{ $loadError }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="box">
                    <div class="box-body">
                        <div class="table-responsive rounded card-table">
                            <table id="agenda-table" class="table table-striped table-hover table-sm agenda-table mb-0">
                                <thead class="bg-primary">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th>Form ID</th>
                                    <th>Historia</th>
                                    <th>Paciente</th>
                                    <th>Tipo atención</th>
                                    <th>Código atención</th>
                                    <th>Detalle atención</th>
                                    <th>Doctor</th>
                                    <th>Estado</th>
                                    <th>Sede</th>
                                    <th>Afiliación</th>
                                    <th class="not-export no-colvis">Visita</th>
                                    <th>Historia clínica</th>
                                    <th class="not-export no-colvis">Acciones</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($agendaRows as $row)
                                    @php
                                        $hora = trim((string) ($row->hora ?? ''));
                                        if ($hora === '') {
                                            $hora = trim((string) ($row->hora_llegada ?? ''));
                                        }

                                        $sede = trim((string) ($row->sede ?? ''));
                                        $tipoAtencion = trim((string) ($row->atencion_tipo ?? ''));
                                        $codigoAtencion = trim((string) ($row->atencion_codigo ?? ''));
                                        $detalleAtencion = trim((string) ($row->atencion_detalle ?? ''));
                                        $procedimientoCompleto = trim((string) ($row->procedimiento ?? ''));

                                        $tieneConsulta = (int) ($row->tiene_consulta ?? 0) === 1;
                                    @endphp
                                    <tr>
                                        <td data-order="{{ (string) ($row->fecha_agenda ?? $row->fecha ?? '') }}">{{ $formatDate((string) ($row->fecha_agenda ?? $row->fecha ?? '')) }}</td>
                                        <td data-order="{{ $hora }}">{{ $formatTime($hora) }}</td>
                                        <td>{{ (string) ($row->form_id ?? '-') }}</td>
                                        <td>{{ (string) ($row->hc_number ?? '-') }}</td>
                                        <td class="agenda-paciente">{{ (string) ($row->paciente ?? '-') }}</td>
                                        <td class="agenda-procedimiento">
                                            @if($tipoAtencion !== '')
                                                <span class="{{ $tipoAtencionBadgeClass($tipoAtencion) }}">{{ $tipoAtencion }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $codigoAtencion !== '' ? $codigoAtencion : '-' }}</td>
                                        <td class="agenda-detalle">{{ $detalleAtencion !== '' ? $detalleAtencion : ($procedimientoCompleto !== '' ? $procedimientoCompleto : '-') }}</td>
                                        <td>{{ (string) ($row->doctor_display ?? $row->doctor ?? '-') }}</td>
                                        <td>
                                            <span class="{{ $statusBadgeClass((string) ($row->estado_agenda ?? '')) }}">
                                                {{ (string) ($row->estado_agenda ?? 'SIN ESTADO') }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($sede !== '')
                                                <span class="{{ $sedeBadgeClass($sede) }}">{{ $sede }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $afiliacionLabel = trim((string) ($row->afiliacion_label ?? $row->afiliacion ?? ''));
                                            @endphp
                                            @if($afiliacionLabel !== '')
                                                <span class="{{ $afiliacionBadgeClass($afiliacionLabel) }}">{{ $afiliacionLabel }}</span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if(!empty($row->visita_id))
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-primary agenda-icon-button"
                                                    data-agenda-view-visita
                                                    data-visita-id="{{ (int) $row->visita_id }}"
                                                    title="Ver visita"
                                                    aria-label="Ver visita"
                                                >
                                                    <i class="mdi mdi-eye-outline"></i>
                                                </button>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td data-order="{{ $tieneConsulta ? 1 : 0 }}">
                                            <span class="badge {{ $tieneConsulta ? 'badge-success' : 'badge-warning' }}">
                                                {{ $tieneConsulta ? 'Con datos' : 'Sin datos' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="agenda-actions">
                                                @if(!empty($row->form_id) && !empty($row->hc_number))
                                                    <a
                                                        href="/v2/consultas?form_id={{ urlencode((string) $row->form_id) }}&hc_number={{ urlencode((string) $row->hc_number) }}"
                                                        class="btn btn-sm btn-outline-success agenda-icon-button"
                                                        title="{{ $tieneConsulta ? 'Editar consulta' : 'Crear consulta' }}"
                                                        aria-label="{{ $tieneConsulta ? 'Editar consulta' : 'Crear consulta' }}"
                                                    >
                                                        <i class="mdi {{ $tieneConsulta ? 'mdi-file-document-edit-outline' : 'mdi-file-document-plus-outline' }}"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="15" class="text-center text-muted py-30">
                                            No hay resultados para los filtros seleccionados.
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

    <div class="agenda-visit-backdrop" id="agenda-visit-modal" aria-hidden="true">
        <div class="agenda-visit-modal">
            <div class="agenda-visit-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">Detalle de visita</h4>
                    <small class="text-muted" id="agenda-visit-subtitle">Cargando...</small>
                </div>
                <button type="button" class="btn btn-light btn-sm" data-agenda-close-visita>Cerrar</button>
            </div>
            <div class="agenda-visit-body">
                <div id="agenda-visit-loading" class="text-muted">Cargando visita...</div>
                <div id="agenda-visit-error" class="alert alert-danger d-none mb-0"></div>
                <div id="agenda-visit-content" class="d-none">
                    <div class="agenda-visit-grid" id="agenda-visit-grid"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Form ID</th>
                                <th>Procedimiento</th>
                                <th>Doctor</th>
                                <th>Estado</th>
                            </tr>
                            </thead>
                            <tbody id="agenda-visit-procedimientos"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="agenda-visit-footer">
                <button type="button" class="btn btn-light" data-agenda-close-visita>Cerrar</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="/assets/vendor_components/datatable/datatables.min.js"></script>
    <script src="/js/pages/shared/datatables-language-es.js"></script>
    <script src="/js/pages/agenda-v2.js"></script>
@endpush
