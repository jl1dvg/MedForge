@extends('layouts.medforge')

@php
    $facturasList = is_array($facturas ?? null) ? $facturas : [];
    $mes = trim((string) ($mesSeleccionado ?? ''));
@endphp

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Billing v2</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Facturas</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="ms-auto d-flex gap-10">
                <a href="/v2/billing/no-facturados" class="btn btn-outline-primary">
                    <i class="mdi mdi-file-document-outline me-5"></i>No facturados
                </a>
                <span class="badge bg-light text-primary align-self-center">Fuente: LARAVEL V2</span>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="row">
            <div class="col-12">
                <div class="box mb-15">
                    <div class="box-body">
                        <form method="get" action="/v2/billing" class="row g-3 align-items-end">
                            <div class="col-md-4 col-lg-3">
                                <label class="form-label" for="mes">Mes</label>
                                <input type="month" id="mes" name="mes" class="form-control" value="{{ $mes }}">
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="mdi mdi-magnify me-5"></i>Filtrar
                                </button>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <a href="/v2/billing" class="btn btn-light w-100">
                                    <i class="mdi mdi-filter-remove me-5"></i>Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h4 class="box-title mb-0">Facturas registradas</h4>
                    </div>
                    <div class="box-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="bg-primary-light">
                                <tr>
                                    <th>Form ID</th>
                                    <th>HC</th>
                                    <th>Paciente</th>
                                    <th>Afiliación</th>
                                    <th>Fecha</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($facturasList as $row)
                                    <tr>
                                        <td>
                                            <span class="badge bg-info-light text-primary fw-600">{{ (string) ($row['form_id'] ?? '') }}</span>
                                        </td>
                                        <td>{{ (string) ($row['hc_number'] ?? '') }}</td>
                                        <td>{{ (string) ($row['paciente'] ?? 'Paciente sin nombre') }}</td>
                                        <td>{{ (string) ($row['afiliacion'] ?? '-') }}</td>
                                        <td>
                                            @php
                                                $fecha = trim((string) ($row['fecha'] ?? ''));
                                                $fechaFormateada = '-';
                                                if ($fecha !== '' && strtotime($fecha) !== false) {
                                                    $fechaFormateada = date('d/m/Y H:i', strtotime($fecha));
                                                }
                                            @endphp
                                            {{ $fechaFormateada }}
                                        </td>
                                        <td class="text-end">
                                            <a href="/v2/billing/detalle?form_id={{ urlencode((string) ($row['form_id'] ?? '')) }}" class="btn btn-sm btn-primary">
                                                <i class="mdi mdi-eye-outline me-5"></i>Ver
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-25">
                                            No hay facturas para los filtros seleccionados.
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
