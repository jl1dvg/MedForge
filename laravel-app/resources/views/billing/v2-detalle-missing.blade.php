@extends('layouts.medforge')

@php
    $codigo = trim((string) ($formId ?? ''));
@endphp

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Factura no encontrada</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/v2/billing">Billing</a></li>
                            <li class="breadcrumb-item active" aria-current="page">No encontrada</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="row">
            <div class="col-lg-8 col-12">
                <div class="box">
                    <div class="box-body">
                        <div class="alert alert-warning mb-0">
                            No encontramos la factura {{ $codigo !== '' ? '#'.$codigo : 'solicitada' }} en `billing_main`.
                            Revisa el `form_id` e intenta nuevamente.
                        </div>
                        <div class="mt-15">
                            <a href="/v2/billing" class="btn btn-primary">
                                <i class="mdi mdi-arrow-left me-5"></i>Volver a Billing
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
