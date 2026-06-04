@extends('layouts.medforge')

@section('content')
@php
    $prescription = $prescription ?? null;
    $patient = $prescription?->patient;
    $estadoBadge = [
        'pendiente'  => 'bg-warning text-dark',
        'procesada'  => 'bg-info',
        'parcial'    => 'bg-secondary',
        'entregada'  => 'bg-success',
        'cancelada'  => 'bg-danger',
    ];
    $dispBadge = [
        'disponible'    => 'bg-success',
        'parcial'       => 'bg-warning text-dark',
        'no_disponible' => 'bg-danger',
    ];
@endphp

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Receta #{{ $prescription?->id }}</h3>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                <li class="breadcrumb-item"><a href="/v2/pharmacy/dashboard">Farmacia Pro</a></li>
                <li class="breadcrumb-item"><a href="/v2/pharmacy">Recetas</a></li>
                <li class="breadcrumb-item active" aria-current="page">#{{ $prescription?->id }}</li>
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

    @if(!$prescription)
        <div class="alert alert-danger">Receta no encontrada.</div>
    @else
    <div class="row">
        {{-- Patient info --}}
        <div class="col-md-4">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title"><i class="mdi mdi-account-outline me-2"></i>Paciente</h4>
                </div>
                <div class="box-body">
                    @if($patient)
                        <dl class="row mb-0">
                            <dt class="col-5">Nombre</dt>
                            <dd class="col-7">{{ $patient->nombres }} {{ $patient->apellidos }}</dd>
                            <dt class="col-5">Cédula</dt>
                            <dd class="col-7">{{ $patient->identificacion }}</dd>
                            <dt class="col-5">Teléfono</dt>
                            <dd class="col-7">{{ $patient->telefono ?: '—' }}</dd>
                            <dt class="col-5">WhatsApp</dt>
                            <dd class="col-7">{{ $patient->whatsapp ?: '—' }}</dd>
                            <dt class="col-5">Email</dt>
                            <dd class="col-7">{{ $patient->email ?: '—' }}</dd>
                            <dt class="col-5">Clínica</dt>
                            <dd class="col-7">{{ $patient->clinica ?: '—' }}</dd>
                        </dl>
                    @else
                        <p class="text-muted">Sin datos de paciente.</p>
                    @endif
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title"><i class="mdi mdi-file-document-outline me-2"></i>Receta</h4>
                </div>
                <div class="box-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Estado</dt>
                        <dd class="col-7">
                            <span class="badge {{ $estadoBadge[$prescription->estado] ?? 'bg-secondary' }}">{{ ucfirst($prescription->estado) }}</span>
                        </dd>
                        <dt class="col-5">Médico</dt>
                        <dd class="col-7">{{ $prescription->medico ?: '—' }}</dd>
                        <dt class="col-5">Clínica</dt>
                        <dd class="col-7">{{ $prescription->clinica ?: '—' }}</dd>
                        <dt class="col-5">Fecha</dt>
                        <dd class="col-7">{{ $prescription->fecha_prescripcion?->format('d/m/Y') ?? '—' }}</dd>
                        @if($prescription->external_id)
                        <dt class="col-5">ID Externo</dt>
                        <dd class="col-7">{{ $prescription->external_id }}</dd>
                        @endif
                        @if($prescription->notas)
                        <dt class="col-5">Notas</dt>
                        <dd class="col-7">{{ $prescription->notas }}</dd>
                        @endif
                    </dl>
                    <div class="mt-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEstado">
                            <i class="mdi mdi-pencil me-1"></i>Cambiar estado
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            {{-- Items --}}
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title"><i class="mdi mdi-pill me-2"></i>Medicamentos prescritos</h4>
                </div>
                <div class="box-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Medicamento</th>
                                    <th>Dosis</th>
                                    <th>Frecuencia</th>
                                    <th>Días</th>
                                    <th>Disponibilidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($prescription->items as $item)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $item->nombre_medicamento }}</div>
                                            @if($item->principio_activo)
                                                <small class="text-muted">{{ $item->principio_activo }}</small>
                                            @endif
                                            @if($item->presentacion)
                                                <br><small class="text-muted">{{ $item->presentacion }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $item->dosis ?: '—' }}</td>
                                        <td>{{ $item->frecuencia ?: '—' }}</td>
                                        <td>{{ $item->duracion_dias ?? '—' }}</td>
                                        <td>
                                            <span class="badge {{ $dispBadge[$item->disponibilidad] ?? 'bg-secondary' }}">
                                                {{ ucfirst(str_replace('_', ' ', $item->disponibilidad)) }}
                                            </span>
                                            @if($item->inventory)
                                                <br><small class="text-muted">Stock: {{ $item->inventory->stock }}</small>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">Sin ítems</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Delivery --}}
            <div class="box">
                <div class="box-header with-border d-flex justify-content-between align-items-center">
                    <h4 class="box-title mb-0"><i class="mdi mdi-truck-delivery-outline me-2"></i>Entrega</h4>
                </div>
                <div class="box-body">
                    @if($prescription->delivery)
                        @php $delivery = $prescription->delivery; @endphp
                        <dl class="row mb-0">
                            <dt class="col-4">Estado</dt>
                            <dd class="col-8"><span class="badge bg-info">{{ ucfirst($delivery->estado) }}</span></dd>
                            <dt class="col-4">Dirección</dt>
                            <dd class="col-8">{{ $delivery->direccion ?: '—' }}</dd>
                            <dt class="col-4">Programada</dt>
                            <dd class="col-8">{{ $delivery->fecha_programada?->format('d/m/Y') ?? '—' }}</dd>
                            <dt class="col-4">Entregada</dt>
                            <dd class="col-8">{{ $delivery->fecha_entrega?->format('d/m/Y H:i') ?? '—' }}</dd>
                            @if($delivery->responsable)
                            <dt class="col-4">Responsable</dt>
                            <dd class="col-8">{{ $delivery->responsable }}</dd>
                            @endif
                        </dl>
                    @else
                        <p class="text-muted mb-0">No hay entrega registrada para esta receta.</p>
                    @endif
                </div>
            </div>

            {{-- Reminders --}}
            @if($prescription->reminders->isNotEmpty())
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title"><i class="mdi mdi-bell-outline me-2"></i>Recordatorios</h4>
                </div>
                <div class="box-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr><th>Descripción</th><th>Fecha</th><th>Estado</th></tr>
                            </thead>
                            <tbody>
                                @foreach($prescription->reminders as $reminder)
                                    <tr>
                                        <td>{{ $reminder->descripcion }}</td>
                                        <td>{{ $reminder->fecha_recordatorio?->format('d/m/Y') ?? '—' }}</td>
                                        <td><span class="badge bg-secondary">{{ ucfirst($reminder->estado) }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- WhatsApp logs --}}
            @if($prescription->whatsappLogs->isNotEmpty())
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title"><i class="mdi mdi-whatsapp me-2"></i>Historial WhatsApp</h4>
                </div>
                <div class="box-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr><th>Tipo</th><th>Número</th><th>Estado</th><th>Fecha</th></tr>
                            </thead>
                            <tbody>
                                @foreach($prescription->whatsappLogs as $log)
                                    <tr>
                                        <td>{{ str_replace('_', ' ', $log->tipo) }}</td>
                                        <td>{{ $log->numero_destino }}</td>
                                        <td><span class="badge bg-secondary">{{ $log->estado }}</span></td>
                                        <td><small>{{ $log->created_at?->format('d/m/Y H:i') }}</small></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif
</section>

{{-- Modal estado --}}
@if($prescription)
<div class="modal fade" id="modalEstado" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar estado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/v2/pharmacy/prescriptions/{{ $prescription->id }}/estado">
                @csrf
                @method('PATCH')
                <div class="modal-body">
                    <select class="form-select" name="estado">
                        @foreach(['pendiente','procesada','parcial','entregada','cancelada'] as $opt)
                            <option value="{{ $opt }}" @selected($prescription->estado === $opt)>{{ ucfirst($opt) }}</option>
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
@endif
@endsection
