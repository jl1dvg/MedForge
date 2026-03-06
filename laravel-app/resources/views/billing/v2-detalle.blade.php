@extends('layouts.medforge')

@php
    $data = is_array($detalle ?? null) ? $detalle : [];
    $billing = is_array($data['billing'] ?? null) ? $data['billing'] : [];
    $paciente = is_array($data['paciente'] ?? null) ? $data['paciente'] : [];
    $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
    $grupos = is_array($data['grupos'] ?? null) ? $data['grupos'] : [];
    $subtotales = is_array($data['subtotales'] ?? null) ? $data['subtotales'] : [];

    $nombreCompleto = trim(implode(' ', array_filter([
        (string) ($paciente['lname'] ?? ''),
        (string) ($paciente['lname2'] ?? ''),
        (string) ($paciente['fname'] ?? ''),
        (string) ($paciente['mname'] ?? ''),
    ])));

    $formId = (string) ($billing['form_id'] ?? '');
    $hcNumber = (string) ($billing['hc_number'] ?? '');

    $fechaFactura = trim((string) ($billing['fecha'] ?? ''));
    $fechaFacturaFmt = '-';
    if ($fechaFactura !== '' && strtotime($fechaFactura) !== false) {
        $fechaFacturaFmt = date('d/m/Y H:i', strtotime($fechaFactura));
    }
@endphp

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Detalle de factura</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/v2/billing">Billing</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Factura {{ $formId }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="ms-auto d-flex gap-10">
                <a href="/v2/billing" class="btn btn-light">
                    <i class="mdi mdi-arrow-left me-5"></i>Volver
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
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <h5 class="text-uppercase text-muted mb-10">Paciente</h5>
                                <p class="mb-5"><strong>{{ $nombreCompleto !== '' ? $nombreCompleto : 'Paciente sin nombre' }}</strong></p>
                                <p class="mb-0">HC: <span class="badge bg-primary">{{ $hcNumber !== '' ? $hcNumber : '-' }}</span></p>
                                <p class="mb-0">Afiliación: {{ (string) ($paciente['afiliacion'] ?? '-') }}</p>
                                <p class="mb-0">Cédula: {{ (string) ($paciente['ci'] ?? '-') }}</p>
                            </div>
                            <div class="col-lg-6">
                                <h5 class="text-uppercase text-muted mb-10">Factura</h5>
                                <p class="mb-0">Form ID: <span class="badge bg-info-light text-primary">{{ $formId !== '' ? $formId : '-' }}</span></p>
                                <p class="mb-0">Fecha: {{ $fechaFacturaFmt }}</p>
                                <p class="mb-0">Código derivación: {{ (string) ($metadata['cod_derivacion'] ?? '-') }}</p>
                                <p class="mb-0">Referido por: {{ (string) ($metadata['referido'] ?? '-') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @foreach($grupos as $nombreGrupo => $items)
                @if(!empty($items) && is_array($items))
                    <div class="col-12">
                        <div class="box mb-15">
                            <div class="box-header with-border">
                                <h4 class="box-title mb-0">{{ $nombreGrupo }}</h4>
                            </div>
                            <div class="box-body">
                                <div class="table-responsive">
                                    <table class="table table-striped mb-0">
                                        <thead class="bg-primary-light">
                                        <tr>
                                            <th>Código</th>
                                            <th>Detalle</th>
                                            <th class="text-end">Cantidad</th>
                                            <th class="text-end">Precio</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($items as $item)
                                            @php
                                                $codigo = (string) ($item['codigo'] ?? '-');
                                                $detalleItem = (string) ($item['detalle'] ?? '-');
                                                $cantidad = (float) ($item['cantidad'] ?? 0);
                                                $precio = (float) ($item['precio'] ?? 0);
                                                $subtotal = (float) ($item['subtotal'] ?? 0);
                                            @endphp
                                            <tr>
                                                <td>{{ $codigo !== '' ? $codigo : '-' }}</td>
                                                <td>{{ $detalleItem !== '' ? $detalleItem : '-' }}</td>
                                                <td class="text-end">{{ number_format($cantidad, 2) }}</td>
                                                <td class="text-end">${{ number_format($precio, 2) }}</td>
                                                <td class="text-end">${{ number_format($subtotal, 2) }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                        <tfoot>
                                        <tr>
                                            <th colspan="4" class="text-end">Subtotal {{ $nombreGrupo }}</th>
                                            <th class="text-end">${{ number_format((float) ($subtotales[$nombreGrupo] ?? 0), 2) }}</th>
                                        </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach

            <div class="col-lg-5 ms-lg-auto">
                <div class="box">
                    <div class="box-body">
                        <table class="table table-bordered mb-0">
                            <tbody>
                            <tr>
                                <th class="text-end">Subtotal</th>
                                <td class="text-end">${{ number_format((float) ($data['totalSinIva'] ?? 0), 2) }}</td>
                            </tr>
                            <tr>
                                <th class="text-end">IVA (15%)</th>
                                <td class="text-end">${{ number_format((float) ($data['iva'] ?? 0), 2) }}</td>
                            </tr>
                            <tr class="bg-primary-light">
                                <th class="text-end">Total</th>
                                <td class="text-end"><strong>${{ number_format((float) ($data['totalConIva'] ?? 0), 2) }}</strong></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
