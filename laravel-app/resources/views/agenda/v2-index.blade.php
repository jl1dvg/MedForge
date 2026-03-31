@extends('layouts.medforge')

@php
    $agendaRows = is_array($agendaRows ?? null) ? $agendaRows : [];
    $agendaMeta = is_array($agendaMeta ?? null) ? $agendaMeta : [];
    $filters = is_array($agendaMeta['filters'] ?? null) ? $agendaMeta['filters'] : [];
    $estados = array_values(array_filter((array) ($agendaMeta['estados_disponibles'] ?? []), static fn ($value) => trim((string) $value) !== ''));
    $doctores = array_values(array_filter(
        (array) ($agendaMeta['doctores_disponibles'] ?? []),
        static fn ($value) => is_array($value) && trim((string) ($value['label'] ?? '')) !== ''
    ));

    $fechaInicio = (string) ($filters['fecha_inicio'] ?? '');
    $fechaFin = (string) ($filters['fecha_fin'] ?? '');
    $doctorActual = (string) ($filters['doctor'] ?? '');
    $estadoActual = (string) ($filters['estado'] ?? '');
    $sedeActual = (string) ($filters['sede'] ?? '');
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
            'AGENDADO', 'CONFIRMADO' => 'badge badge-primary',
            'LLEGADO', 'EN ATENCION' => 'badge badge-info',
            'REALIZADO', 'ATENDIDO' => 'badge badge-success',
            'AUSENTE', 'NO SHOW' => 'badge badge-warning',
            'CANCELADO' => 'badge badge-danger',
            default => 'badge badge-secondary',
        };
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
        white-space: nowrap;
    }

    .agenda-table .agenda-procedimiento {
        min-width: 320px;
        white-space: normal;
    }

    .agenda-table .agenda-paciente {
        min-width: 220px;
        white-space: normal;
    }

    .agenda-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
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
                                <input type="text" id="sede" name="sede" class="form-control" value="{{ $sedeActual }}" placeholder="ID o nombre">
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
                                    <th>Procedimiento</th>
                                    <th>Doctor</th>
                                    <th>Estado</th>
                                    <th>Sede</th>
                                    <th>Afiliación</th>
                                    <th>Visita</th>
                                    <th>Historia clínica</th>
                                    <th>Acciones</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($agendaRows as $row)
                                    @php
                                        $hora = trim((string) ($row->hora ?? ''));
                                        if ($hora === '') {
                                            $hora = trim((string) ($row->hora_llegada ?? ''));
                                        }

                                        $sede = trim((string) ($row->sede_departamento ?? ''));
                                        if ($sede === '') {
                                            $sede = trim((string) ($row->id_sede ?? ''));
                                        }

                                        $tieneConsulta = (int) ($row->tiene_consulta ?? 0) === 1;
                                    @endphp
                                    <tr>
                                        <td data-order="{{ (string) ($row->fecha_agenda ?? $row->fecha ?? '') }}">{{ $formatDate((string) ($row->fecha_agenda ?? $row->fecha ?? '')) }}</td>
                                        <td data-order="{{ $hora }}">{{ $formatTime($hora) }}</td>
                                        <td>{{ (string) ($row->form_id ?? '-') }}</td>
                                        <td>{{ (string) ($row->hc_number ?? '-') }}</td>
                                        <td class="agenda-paciente">{{ (string) ($row->paciente ?? '-') }}</td>
                                        <td class="agenda-procedimiento">{{ (string) ($row->procedimiento ?? '-') }}</td>
                                        <td>{{ (string) ($row->doctor_display ?? $row->doctor ?? '-') }}</td>
                                        <td>
                                            <span class="{{ $statusBadgeClass((string) ($row->estado_agenda ?? '')) }}">
                                                {{ (string) ($row->estado_agenda ?? 'SIN ESTADO') }}
                                            </span>
                                        </td>
                                        <td>{{ $sede !== '' ? $sede : '-' }}</td>
                                        <td>{{ (string) ($row->afiliacion ?? '-') }}</td>
                                        <td>{{ (string) ($row->visita_id ?? '-') }}</td>
                                        <td data-order="{{ $tieneConsulta ? 1 : 0 }}">
                                            <span class="badge {{ $tieneConsulta ? 'badge-success' : 'badge-warning' }}">
                                                {{ $tieneConsulta ? 'Con datos' : 'Sin datos' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="agenda-actions">
                                                @if(!empty($row->visita_id))
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-primary"
                                                        data-agenda-view-visita
                                                        data-visita-id="{{ (int) $row->visita_id }}"
                                                    >
                                                        Ver visita
                                                    </button>
                                                @endif

                                                @if(!empty($row->form_id) && !empty($row->hc_number))
                                                    <a
                                                        href="/v2/consultas?form_id={{ urlencode((string) $row->form_id) }}&hc_number={{ urlencode((string) $row->hc_number) }}"
                                                        class="btn btn-sm btn-outline-success"
                                                    >
                                                        {{ $tieneConsulta ? 'Editar consulta' : 'Crear consulta' }}
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="13" class="text-center text-muted py-30">
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
