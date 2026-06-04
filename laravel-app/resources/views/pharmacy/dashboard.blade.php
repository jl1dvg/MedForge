@extends('layouts.medforge')

@section('content')
@php
    $recetasPendientes     = $recetasPendientes ?? 0;
    $procesadasEsteMes     = $procesadasEsteMes ?? 0;
    $stockBajoCount        = $stockBajoCount ?? 0;
    $entregasActivas       = $entregasActivas ?? 0;
    $recordatoriosProximos = $recordatoriosProximos ?? 0;
    $topMedicamentos       = $topMedicamentos ?? collect();
@endphp

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Farmacia Pro</h3>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                <li class="breadcrumb-item active" aria-current="page">Farmacia Pro</li>
            </ol>
        </div>
        <div class="ms-auto">
            <a href="/v2/pharmacy" class="btn btn-outline-secondary btn-sm me-2">
                <i class="mdi mdi-format-list-bulleted me-1"></i> Recetas
            </a>
            <a href="/v2/pharmacy/inventory" class="btn btn-outline-secondary btn-sm">
                <i class="mdi mdi-package-variant-closed me-1"></i> Inventario
            </a>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-sm-6 col-lg-4 col-xl">
            <div class="box bg-primary-light">
                <div class="box-body p-3 d-flex align-items-center">
                    <i class="mdi mdi-file-document-outline mdi-48px text-primary me-3"></i>
                    <div>
                        <div class="fs-4 fw-bold">{{ $recetasPendientes }}</div>
                        <div class="text-muted small">Recetas pendientes</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl">
            <div class="box bg-success-light">
                <div class="box-body p-3 d-flex align-items-center">
                    <i class="mdi mdi-check-circle-outline mdi-48px text-success me-3"></i>
                    <div>
                        <div class="fs-4 fw-bold">{{ $procesadasEsteMes }}</div>
                        <div class="text-muted small">Procesadas este mes</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl">
            <div class="box {{ $stockBajoCount > 0 ? 'bg-danger-light' : 'bg-light' }}">
                <div class="box-body p-3 d-flex align-items-center">
                    <i class="mdi mdi-alert-circle-outline mdi-48px {{ $stockBajoCount > 0 ? 'text-danger' : 'text-muted' }} me-3"></i>
                    <div>
                        <div class="fs-4 fw-bold">{{ $stockBajoCount }}</div>
                        <div class="text-muted small">Stock bajo</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl">
            <div class="box bg-warning-light">
                <div class="box-body p-3 d-flex align-items-center">
                    <i class="mdi mdi-truck-delivery-outline mdi-48px text-warning me-3"></i>
                    <div>
                        <div class="fs-4 fw-bold">{{ $entregasActivas }}</div>
                        <div class="text-muted small">Entregas activas</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl">
            <div class="box bg-info-light">
                <div class="box-body p-3 d-flex align-items-center">
                    <i class="mdi mdi-bell-ring-outline mdi-48px text-info me-3"></i>
                    <div>
                        <div class="fs-4 fw-bold">{{ $recordatoriosProximos }}</div>
                        <div class="text-muted small">Recordatorios próximos (7d)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Top 5 Medicamentos más solicitados</h4>
                </div>
                <div class="box-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Medicamento</th>
                                <th class="text-end">Solicitudes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topMedicamentos as $idx => $med)
                                <tr>
                                    <td>{{ $idx + 1 }}</td>
                                    <td>{{ $med->nombre_medicamento }}</td>
                                    <td class="text-end"><span class="badge bg-primary">{{ $med->total }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">Sin datos</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Accesos rápidos</h4>
                </div>
                <div class="box-body">
                    <div class="d-grid gap-2">
                        <a href="/v2/pharmacy?estado=pendiente" class="btn btn-outline-primary">
                            <i class="mdi mdi-file-clock-outline me-2"></i>Ver recetas pendientes
                        </a>
                        <a href="/v2/pharmacy/inventory" class="btn btn-outline-secondary">
                            <i class="mdi mdi-package-variant-closed me-2"></i>Gestionar inventario
                        </a>
                        @if($stockBajoCount > 0)
                        <a href="/v2/pharmacy/inventory?estado=activo" class="btn btn-outline-danger">
                            <i class="mdi mdi-alert me-2"></i>{{ $stockBajoCount }} medicamento(s) con stock bajo
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
