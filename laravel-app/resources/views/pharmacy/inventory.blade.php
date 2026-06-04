@extends('layouts.medforge')

@section('content')
@php
    $filters  = $filters ?? [];
    $items    = $items ?? collect();
    $lowStock = $lowStock ?? collect();
    $categorias = ['colirios','unguentos','oral','inyectables','lagrimas','antiglaucomatosos','antibioticos','antiinflamatorios','otros'];
@endphp

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Farmacia Pro — Inventario</h3>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                <li class="breadcrumb-item"><a href="/v2/pharmacy/dashboard">Farmacia Pro</a></li>
                <li class="breadcrumb-item active" aria-current="page">Inventario</li>
            </ol>
        </div>
        <div class="ms-auto">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAddItem">
                <i class="mdi mdi-plus me-1"></i>Agregar medicamento
            </button>
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

    @if($lowStock->isNotEmpty())
    <div class="alert alert-warning">
        <i class="mdi mdi-alert me-2"></i>
        <strong>{{ $lowStock->count() }} medicamento(s)</strong> con stock bajo o agotado.
    </div>
    @endif

    <div class="box mb-3">
        <div class="box-header with-border">
            <h4 class="box-title">Filtros</h4>
        </div>
        <div class="box-body">
            <form class="row g-2 align-items-end" method="get">
                <div class="col-sm-6 col-md-3">
                    <label class="form-label">Categoría</label>
                    <select class="form-select" name="categoria">
                        <option value="">Todas</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat }}" @selected(($filters['categoria'] ?? '') === $cat)>{{ ucfirst($cat) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="estado">
                        <option value="">Todos</option>
                        <option value="activo" @selected(($filters['estado'] ?? '') === 'activo')>Activo</option>
                        <option value="inactivo" @selected(($filters['estado'] ?? '') === 'inactivo')>Inactivo</option>
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="col-sm-6 col-md-2">
                    <a href="/v2/pharmacy/inventory" class="btn btn-outline-secondary w-100">Limpiar</a>
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
                            <th>Nombre</th>
                            <th>Principio activo</th>
                            <th>Categoría</th>
                            <th>Presentación</th>
                            <th>Stock</th>
                            <th>Stock mín.</th>
                            <th>Precio</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            @php $isLow = $item->stock <= $item->stock_minimo; @endphp
                            <tr class="{{ $isLow ? 'table-danger' : '' }}">
                                <td>
                                    <div class="fw-semibold">{{ $item->nombre }}</div>
                                </td>
                                <td><small>{{ $item->principio_activo ?: '—' }}</small></td>
                                <td><span class="badge bg-light text-dark">{{ ucfirst($item->categoria) }}</span></td>
                                <td><small>{{ $item->presentacion ?: '—' }}</small></td>
                                <td>
                                    <span class="fw-bold {{ $isLow ? 'text-danger' : 'text-success' }}">
                                        {{ $item->stock }}
                                        @if($isLow) <i class="mdi mdi-alert-circle text-danger"></i> @endif
                                    </span>
                                </td>
                                <td>{{ $item->stock_minimo }}</td>
                                <td>{{ $item->precio !== null ? '$' . number_format($item->precio, 2) : '—' }}</td>
                                <td>
                                    <span class="badge {{ $item->estado === 'activo' ? 'bg-success' : 'bg-secondary' }}">
                                        {{ ucfirst($item->estado) }}
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-xs btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#modalEdit{{ $item->id }}">
                                        <i class="mdi mdi-pencil"></i>
                                    </button>
                                </td>
                            </tr>

                            {{-- Edit modal --}}
                            <div class="modal fade" id="modalEdit{{ $item->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Editar: {{ $item->nombre }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="post" action="/v2/pharmacy/inventory/{{ $item->id }}">
                                            @csrf
                                            @method('PATCH')
                                            <div class="modal-body">
                                                @include('pharmacy._inventory_form', ['inv' => $item, 'categorias' => $categorias])
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
                            <tr><td colspan="9" class="text-center text-muted py-4">No hay medicamentos en inventario.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($items->hasPages())
            <div class="box-footer">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</section>

{{-- Add modal --}}
<div class="modal fade" id="modalAddItem" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar medicamento al inventario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="/v2/pharmacy/inventory">
                @csrf
                <div class="modal-body">
                    @include('pharmacy._inventory_form', ['inv' => null, 'categorias' => $categorias])
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary btn-sm">Agregar</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
