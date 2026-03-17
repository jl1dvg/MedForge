@extends('layouts.medforge')

@push('styles')
    <link rel="stylesheet" href="/css/kanban-scroll.css">
    <style>
        .kanban-toolbar {
            gap: 1rem;
        }

        #loader {
            display: none;
        }
    </style>
@endpush

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Flujo de Pacientes</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Flujo de Pacientes</li>
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
                    <div class="box-header with-border">
                        <h4 class="box-title">Filtros del tablero</h4>
                    </div>
                    <div class="box-body">
                        <div class="row g-3 kanban-toolbar align-items-end">
                            <div class="col-md-3 col-sm-6">
                                <label for="kanbanDateFilter" class="form-label form-label-sm">Fecha de visita</label>
                                <input type="text" id="kanbanDateFilter" class="form-control" placeholder="YYYY-MM-DD">
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label for="kanbanAfiliacionFilter" class="form-label form-label-sm">Afiliación</label>
                                <select id="kanbanAfiliacionFilter" class="form-select">
                                    <option value="">Todas</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label for="kanbanDoctorFilter" class="form-label form-label-sm">Doctor</label>
                                <select id="kanbanDoctorFilter" class="form-select">
                                    <option value="">Todos</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="loader" class="text-center my-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <div class="small text-muted mt-2">Cargando tablero</div>
                </div>

                <div id="kanban-summary" class="mb-3"></div>
                <div class="kanban-board kanban-board-wrapper bg-light p-3 d-flex flex-nowrap gap-3"></div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    @if (\App\Modules\Shared\Support\MedforgeAssets::hasViteBuild())
        @vite('resources/js/v2/pacientes-flujo.js')
    @else
        <script src="/assets/vendor_components/moment/moment.js"></script>
        <script src="/assets/vendor_components/sweetalert2/sweetalert2.all.min.js"></script>
        <script src="/assets/vendor_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>
        <script src="/assets/vendor_components/bootstrap-datepicker/dist/locales/bootstrap-datepicker.es.min.js"></script>
        <script src="/js/pages/pacientes/flujo.js"></script>
    @endif
@endpush
