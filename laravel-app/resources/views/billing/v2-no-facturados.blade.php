@extends('layouts.medforge')

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">No facturados v2</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/v2/billing">Billing</a></li>
                            <li class="breadcrumb-item active" aria-current="page">No facturados</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="ms-auto d-flex gap-10">
                <a href="/v2/billing" class="btn btn-light">
                    <i class="mdi mdi-arrow-left me-5"></i>Volver a facturas
                </a>
                <span class="badge bg-light text-primary align-self-center">Fuente: LARAVEL V2</span>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="row mb-10">
            <div class="col-lg-4 col-md-6 col-12">
                <div class="box">
                    <div class="box-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted">Pendientes</div>
                            <h3 id="nf-total" class="mb-0">0</h3>
                        </div>
                        <i class="mdi mdi-file-clock-outline fs-28 text-primary"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 col-12">
                <div class="box">
                    <div class="box-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted">Quirúrgicos</div>
                            <h3 id="nf-quirurgicos" class="mb-0">0</h3>
                        </div>
                        <i class="mdi mdi-hospital-box-outline fs-28 text-info"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-12 col-12">
                <div class="box">
                    <div class="box-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted">No quirúrgicos</div>
                            <h3 id="nf-no-quirurgicos" class="mb-0">0</h3>
                        </div>
                        <i class="mdi mdi-stethoscope fs-28 text-success"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="box">
                    <div class="box-header with-border d-flex flex-wrap align-items-center gap-10">
                        <h4 class="box-title mb-0">Listado de pendientes</h4>
                        <div class="ms-auto d-flex gap-10">
                            <input id="billing-search" type="text" class="form-control" placeholder="Buscar por form_id, HC, paciente o procedimiento" style="min-width: 260px;">
                            <button type="button" id="billing-refresh" class="btn btn-primary">
                                <i class="mdi mdi-refresh me-5"></i>Actualizar
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="table-responsive">
                            <table id="billing-no-facturados-table" class="table table-striped table-hover w-100">
                                <thead class="bg-primary-light">
                                <tr>
                                    <th>Form ID</th>
                                    <th>HC</th>
                                    <th>Fecha</th>
                                    <th>Paciente</th>
                                    <th>Afiliación</th>
                                    <th>Procedimiento</th>
                                    <th>Tipo</th>
                                    <th>Estado agenda</th>
                                    <th>Acción</th>
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

    <form id="crear-factura-form" method="post" action="/v2/billing/no-facturados/crear" class="d-none">
        <input type="hidden" name="form_id" id="crear-form-id" value="">
        <input type="hidden" name="hc_number" id="crear-hc-number" value="">
    </form>
@endsection

@push('scripts')
    <script src="/assets/vendor_components/datatable/datatables.min.js"></script>
    <script src="/js/pages/billing/v2-no-facturados.js"></script>
@endpush
