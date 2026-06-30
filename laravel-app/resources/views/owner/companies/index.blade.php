@extends('layouts.medforge')

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Panel de Propietario</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Empresas</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                {{ session('success') }}
            </div>
        @endif

        <div class="box">
            <div class="box-header with-border">
                <h4 class="box-title mb-0">Empresas licenciatarias</h4>
                <p class="text-muted mt-1 mb-0" style="font-size:13px">
                    Controla el estado del servicio por empresa. Solo visible para el propietario de la plataforma.
                </p>
            </div>
            <div class="box-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Empresa</th>
                            <th>Servicio</th>
                            <th>Modo solo lectura</th>
                            <th>Ventana activa</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($companies as $company)
                            @php
                                if (!$company->is_active) {
                                    $badge = '<span class="badge bg-secondary">Desactivado</span>';
                                } elseif ($company->service_mode === 'on') {
                                    $badge = '<span class="badge bg-danger">Solo lectura (forzado)</span>';
                                } elseif ($company->service_mode === 'off') {
                                    $badge = '<span class="badge bg-success">Activo (forzado)</span>';
                                } else {
                                    $badge = '<span class="badge bg-info text-dark">Automático</span>';
                                }
                            @endphp
                            <tr>
                                <td class="align-middle fw-600">{{ $company->name }}</td>
                                <td class="align-middle">{!! $badge !!}</td>
                                <td class="align-middle">
                                    @if ($company->service_mode === 'auto')
                                        Automático (por fechas)
                                    @elseif ($company->service_mode === 'on')
                                        <span class="text-danger">Forzado ON</span>
                                    @else
                                        <span class="text-success">Forzado OFF</span>
                                    @endif
                                </td>
                                <td class="align-middle" style="font-size:13px">
                                    @if ($company->readonly_start && $company->readonly_end)
                                        {{ $company->readonly_start->format('d M Y') }}
                                        — {{ $company->readonly_end->format('d M Y') }}
                                    @else
                                        <span class="text-muted">Sin configurar</span>
                                    @endif
                                </td>
                                <td class="align-middle text-end">
                                    <a href="/owner/companies/{{ $company->id }}/edit"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="mdi mdi-pencil me-1"></i>Editar
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No hay empresas registradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
