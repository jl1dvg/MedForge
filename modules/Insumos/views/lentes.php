<?php
/** @var string $username */
/** @var array $scripts */
$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/datatable/datatables.min.js',
    'js/pages/lentes.js',
]); ?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Lentes</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item" aria-current="page">Inventario</li>
                        <li class="breadcrumb-item active" aria-current="page">Lentes</li>
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
                        <h4 class="box-title">📋 <strong>Catálogo de lentes</strong></h4>
                        <h6 class="subtitle">
                            Administra marca, modelo, nombre y poder para usar en solicitudes quirúrgicas.
                        </h6>
                    </div>
                    <button id="agregarLenteBtn" class="waves-effect waves-light btn btn-primary mb-5">
                        <i class="mdi mdi-plus-circle-outline"></i> Nuevo lente
                    </button>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table id="lentesTabla" class="table table-sm table-striped table-hover align-middle">
                            <thead class="table-primary text-dark fw-semibold">
                            <tr>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Nombre</th>
                                <th>Rango</th>
                                <th>Paso</th>
                                <th>Inicio inc.</th>
                                <th>Poder fijo</th>
                                <th>Const. A</th>
                                <th>Const. A US</th>
                                <th>Tipo óptico</th>
                                <th>Observación</th>
                                <th style="width:120px;">Acciones</th>
                            </tr>
                            </thead>
                            <tbody id="lentesBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    table#lentesTabla td,
    table#lentesTabla th {
        font-size: 0.9rem;
        padding: 0.5rem 0.6rem;
    }
    table#lentesTabla th {
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }
    table#lentesTabla td {
        vertical-align: middle;
        white-space: nowrap;
    }
    table#lentesTabla td:nth-child(3),
    table#lentesTabla td:nth-child(4),
    table#lentesTabla td:nth-child(11) {
        white-space: normal;
    }
    .badge-inline {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 6px;
        background: #eef2ff;
        color: #4338ca;
        font-weight: 600;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
