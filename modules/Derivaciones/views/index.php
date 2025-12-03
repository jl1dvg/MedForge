<?php
$styles = $styles ?? [];
$scripts = $scripts ?? [];
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Derivaciones</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Derivaciones</li>
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
                <div class="box-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="mb-0">Revisión de derivaciones</h4>
                            <p class="text-muted mb-0">Incluye el enlace al PDF guardado desde el scrapping.</p>
                        </div>
                    </div>
                    <div class="table-responsive rounded card-table">
                        <table id="derivaciones-table" class="table table-striped table-hover table-sm">
                            <thead class="bg-primary">
                                <tr>
                                    <th>Creada</th>
                                    <th>Código</th>
                                    <th>Form ID</th>
                                    <th>Historia</th>
                                    <th>Paciente</th>
                                    <th>Médico referidor</th>
                                    <th>F. Registro</th>
                                    <th>F. Vigencia</th>
                                    <th>Archivo</th>
                                    <th>Diagnóstico</th>
                                    <th>Sede</th>
                                    <th>Parentesco</th>
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
