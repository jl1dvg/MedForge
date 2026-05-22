@extends('layouts.medforge')

@push('scripts')
    <script src="{{ asset('assets/vendor_components/datatable/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/vendor_components/tiny-editable/mindmup-editabletable.js') }}"></script>
    <script src="{{ asset('assets/vendor_components/tiny-editable/numeric-input-example.js') }}"></script>
    <script src="{{ asset('js/pages/insumos.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush

@section('content')
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Insumos</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item" aria-current="page">Inventario</li>
                        <li class="breadcrumb-item active" aria-current="page">Insumos</li>
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
                <div class="box-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="box-title">&#128203; <strong>Listado editable de insumos</strong></h4>
                        <h6 class="subtitle">
                            Haz clic sobre cualquier celda para modificar su contenido y guarda los cambios con los
                            botones de acciones.
                        </h6>
                    </div>
                    <button id="agregarInsumoBtn" class="waves-effect waves-light btn btn-primary mb-5">
                        <i class="mdi mdi-plus-circle-outline"></i> Nuevo Insumo
                    </button>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table id="insumosEditable"
                               class="table table-bordered table-striped table-hover table-sm align-middle">
                            <thead class="table-primary text-dark fw-semibold">
                            <tr>
                                <th>Categoría</th>
                                <th>Código ISSPOL</th>
                                <th>Código ISSFA</th>
                                <th>Código IESS</th>
                                <th>Código MSP</th>
                                <th>Nombre</th>
                                <th>Producto ISSFA</th>
                                <th>Es medicamento</th>
                                <th>Precio Base</th>
                                <th>IVA 15%</th>
                                <th>Gestión 10%</th>
                                <th>Precio Total</th>
                                <th>Precio ISSPOL</th>
                                <th>Acciones</th>
                            </tr>
                            </thead>
                            <tbody id="tablaInsumosBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('styles')
<style>
    table#insumosEditable td,
    table#insumosEditable th {
        font-size: 0.85rem;
        padding: 0.45rem 0.5rem;
    }

    table#insumosEditable th {
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    td.editable {
        background-color: #fdfdfd;
        border: 1px dashed #ddd;
        cursor: text;
    }

    td.editable:focus {
        background-color: #e9f7ef;
        outline: none;
    }
</style>
@endpush
