@extends('layouts.medforge')

@php
    $showNotFound = !empty($showNotFoundAlert);
@endphp

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Pacientes</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Pacientes</li>
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
                    @if($showNotFound)
                        <div class="box-body">
                            <div class="alert alert-warning mb-0">
                                No encontramos el paciente solicitado. Intenta nuevamente desde la lista.
                            </div>
                        </div>
                    @endif
                    <div class="box-body">
                        <div class="table-responsive rounded card-table">
                            <table class="table table-striped table-hover table-sm invoice-archive" id="pacientes-table">
                                <thead class="bg-primary">
                                <tr>
                                    <th>ID</th>
                                    <th>Última consulta</th>
                                    <th>Nombre completo</th>
                                    <th>Afiliación</th>
                                    <th>Acciones</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="/assets/vendor_components/datatable/datatables.min.js"></script>
    <script src="/js/pages/patients.js"></script>
@endpush
