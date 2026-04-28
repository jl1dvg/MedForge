@extends('layouts.medforge')

@php
    $filters = is_array($filters ?? null) ? $filters : [];
    $moduleOptions = $moduleOptions ?? collect();
@endphp

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Sugerencias y errores</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Feedback</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        @if (($status ?? null) === 'resolved')
            <div class="alert alert-success">Issue marcada como resuelta.</div>
        @elseif (($status ?? null) === 'reopened')
            <div class="alert alert-warning">Issue reabierta.</div>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h4 class="mb-0">Bandeja de reportes</h4>
                        <p class="text-muted mb-0">Vista interna de sugerencias y errores enviados por usuarios.</p>
                    </div>
                    <div class="box-body">
                        <form method="get" action="/v2/feedback" class="card card-body mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label mb-0">Buscar</label>
                                    <input type="text" name="search" class="form-control form-control-sm" value="{{ (string) ($filters['search'] ?? '') }}" placeholder="Texto, módulo o usuario">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-0">Estado</label>
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="">Todos</option>
                                        <option value="nuevo" @selected((string) ($filters['status'] ?? '') === 'nuevo')>Abiertas</option>
                                        <option value="resuelto" @selected((string) ($filters['status'] ?? '') === 'resuelto')>Resueltas</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label mb-0">Tipo</label>
                                    <select name="report_type" class="form-select form-select-sm">
                                        <option value="">Todos</option>
                                        <option value="bug" @selected((string) ($filters['report_type'] ?? '') === 'bug')>Errores</option>
                                        <option value="suggestion" @selected((string) ($filters['report_type'] ?? '') === 'suggestion')>Sugerencias</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0">Módulo</label>
                                    <select name="module_key" class="form-select form-select-sm">
                                        <option value="">Todos</option>
                                        @foreach ($moduleOptions as $moduleOption)
                                            <option value="{{ (string) $moduleOption->module_key }}" @selected((string) ($filters['module_key'] ?? '') === (string) $moduleOption->module_key)>
                                                {{ (string) $moduleOption->module_label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-1 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
                                </div>
                                <div class="col-12">
                                    <a href="/v2/feedback" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive rounded card-table">
                            <table class="table table-striped table-hover align-middle">
                                <thead class="bg-light">
                                <tr>
                                    <th style="min-width: 120px;">Estado</th>
                                    <th style="min-width: 120px;">Tipo</th>
                                    <th style="min-width: 180px;">Módulo</th>
                                    <th style="min-width: 360px;">Detalle</th>
                                    <th style="min-width: 160px;">Usuario</th>
                                    <th style="min-width: 160px;">Adjunto</th>
                                    <th style="min-width: 160px;">Registrado</th>
                                    <th style="min-width: 180px;">Resolución</th>
                                    <th style="min-width: 150px;">Acciones</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse ($items as $item)
                                    <tr>
                                        <td>
                                            @if ((string) $item->status === 'resuelto')
                                                <span class="badge bg-success">Resuelta</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Abierta</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ((string) $item->report_type === 'bug')
                                                <span class="badge bg-danger">Error</span>
                                            @else
                                                <span class="badge bg-info">Sugerencia</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ (string) $item->module_label }}</div>
                                            @if ((string) ($item->current_path ?? '') !== '')
                                                <div class="text-muted small">{{ (string) $item->current_path }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div style="white-space: pre-wrap;">{{ (string) $item->message }}</div>
                                            @if ((string) ($item->page_title ?? '') !== '')
                                                <div class="text-muted small mt-1">Pantalla: {{ (string) $item->page_title }}</div>
                                            @endif
                                        </td>
                                        <td>{{ (string) $item->reporter_display }}</td>
                                        <td>
                                            @if ((string) ($item->attachment_url ?? '') !== '')
                                                <a href="{{ (string) $item->attachment_url }}" target="_blank" rel="noopener noreferrer">
                                                    {{ (string) ($item->attachment_original_name ?? 'Adjunto') }}
                                                </a>
                                                @if ((int) ($item->attachment_size ?? 0) > 0)
                                                    <div class="text-muted small">{{ number_format(((int) $item->attachment_size) / 1024, 1) }} KB</div>
                                                @endif
                                            @else
                                                <span class="text-muted">Sin adjunto</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div>{{ \Illuminate\Support\Carbon::parse($item->created_at)->format('Y-m-d H:i') }}</div>
                                            <div class="text-muted small">{{ \Illuminate\Support\Carbon::parse($item->created_at)->diffForHumans() }}</div>
                                        </td>
                                        <td>
                                            @if ((string) $item->status === 'resuelto')
                                                <div>{{ $item->resolved_at ? \Illuminate\Support\Carbon::parse($item->resolved_at)->format('Y-m-d H:i') : '—' }}</div>
                                                <div class="text-muted small">{{ (string) ($item->resolver_display !== '' ? $item->resolver_display : 'Sin usuario') }}</div>
                                            @else
                                                <span class="text-muted">Pendiente</span>
                                            @endif
                                        </td>
                                        <td>
                                            <form method="post" action="/v2/feedback/{{ (int) $item->id }}/status" class="d-inline">
                                                @csrf
                                                @if ((string) $item->status === 'resuelto')
                                                    <input type="hidden" name="status" value="nuevo">
                                                    <button type="submit" class="btn btn-outline-warning btn-sm">Reabrir</button>
                                                @else
                                                    <input type="hidden" name="status" value="resuelto">
                                                    <button type="submit" class="btn btn-outline-success btn-sm">Resolver</button>
                                                @endif
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">No hay reportes para los filtros actuales.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $items->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
