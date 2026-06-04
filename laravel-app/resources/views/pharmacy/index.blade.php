@extends('layouts.medforge')

@section('content')
@php
    $filters = $filters ?? [];
    $prescriptions = $prescriptions ?? collect();
    $estadoOptions = ['pendiente','procesada','parcial','entregada','cancelada'];
    $estadoBadge = [
        'pendiente'  => 'bg-warning text-dark',
        'procesada'  => 'bg-info',
        'parcial'    => 'bg-secondary',
        'entregada'  => 'bg-success',
        'cancelada'  => 'bg-danger',
    ];
@endphp

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Farmacia Pro — Recetas</h3>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                <li class="breadcrumb-item"><a href="/v2/pharmacy/dashboard">Farmacia Pro</a></li>
                <li class="breadcrumb-item active" aria-current="page">Recetas</li>
            </ol>
        </div>
    </div>
</div>

<section class="content">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            {{ session('success') }}
        </div>
    @endif

    <div class="box mb-3">
        <div class="box-header with-border">
            <h4 class="box-title">Filtros</h4>
        </div>
        <div class="box-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado">
                        <option value="">Todos</option>
                        @foreach($estadoOptions as $opt)
                            <option value="{{ $opt }}" @selected(($filters['estado'] ?? '') === $opt)>{{ ucfirst($opt) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Clínica</label>
                    <input type="text" class="form-control" name="clinica" value="{{ $filters['clinica'] ?? '' }}" placeholder="Clínica...">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" class="form-control" name="fecha_desde" value="{{ $filters['fecha_desde'] ?? '' }}">
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" class="form-control" name="fecha_hasta" value="{{ $filters['fecha_hasta'] ?? '' }}">
                </div>
                <div class="col-sm-6 col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="col-sm-6 col-md-2">
                    <a href="/v2/pharmacy" class="btn btn-outline-secondary w-100">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="box">
        <div class="box-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Paciente</th>
                            <th>Medicamentos</th>
                            <th>Clínica</th>
                            <th>Médico</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($prescriptions as $rx)
                            @php
                                $patient = $rx->patient;
                                $nombrePaciente = $patient ? ($patient->nombres . ' ' . $patient->apellidos) : '—';
                                $meds = $rx->items->pluck('nombre_medicamento')->take(3)->implode(', ');
                                if ($rx->items->count() > 3) {
                                    $meds .= ' (+' . ($rx->items->count() - 3) . ')';
                                }
                                $badgeClass = $estadoBadge[$rx->estado] ?? 'bg-secondary';
                            @endphp
                            <tr>
                                <td>{{ $rx->id }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $nombrePaciente }}</div>
                                    @if($patient)
                                        <small class="text-muted">{{ $patient->identificacion }}</small>
                                    @endif
                                </td>
                                <td><small>{{ $meds ?: '—' }}</small></td>
                                <td><small>{{ $rx->clinica ?: '—' }}</small></td>
                                <td><small>{{ $rx->medico ?: '—' }}</small></td>
                                <td>
                                    <span class="badge {{ $badgeClass }}">{{ ucfirst($rx->estado) }}</span>
                                </td>
                                <td>
                                    <small>{{ $rx->fecha_prescripcion ? $rx->fecha_prescripcion->format('d/m/Y') : '—' }}</small>
                                </td>
                                <td>
                                    <a href="/v2/pharmacy/prescriptions/{{ $rx->id }}" class="btn btn-xs btn-outline-primary">
                                        <i class="mdi mdi-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-xs btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#modalEstado{{ $rx->id }}">
                                        <i class="mdi mdi-pencil"></i>
                                    </button>
                                </td>
                            </tr>

                            {{-- Modal cambio de estado --}}
                            <div class="modal fade" id="modalEstado{{ $rx->id }}" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Cambiar estado — Receta #{{ $rx->id }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="post" action="/v2/pharmacy/prescriptions/{{ $rx->id }}/estado">
                                            @csrf
                                            @method('PATCH')
                                            <div class="modal-body">
                                                <select class="form-select" name="estado">
                                                    @foreach($estadoOptions as $opt)
                                                        <option value="{{ $opt }}" @selected($rx->estado === $opt)>{{ ucfirst($opt) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No se encontraron recetas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($prescriptions->hasPages())
            <div class="box-footer">
                {{ $prescriptions->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
