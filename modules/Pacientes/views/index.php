<?php
/** @var string $username */
/** @var bool $showNotFoundAlert */
$showNotFoundAlert = !empty($showNotFoundAlert);
?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Pacientes</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
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
                <?php if (!empty($showNotFoundAlert)): ?>
                    <div class="box-body">
                        <div class="alert alert-warning mb-0">
                            No encontramos el paciente solicitado. Intenta nuevamente desde la lista.
                        </div>
                    </div>
                <?php endif; ?>
                <div class="box-body">
                    <div class="table-responsive rounded card-table">
                        <table class="table table-striped table-hover table-sm invoice-archive" id="pacientes-table">
                            <thead class="bg-primary">
                                <tr>
                                    <th>ID</th>
                                    <th>Última consulta</th>
                                    <th>Nombre completo</th>
                                    <th>Afiliación</th>
                                    <th>Estado</th>
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

<!-- Vendor JS -->
<script src="<?= asset('js/vendors.min.js') ?>"></script>
<script src="<?= asset('js/pages/chat-popup.js') ?>"></script>
<script src="<?= asset('assets/icons/feather-icons/feather.min.js') ?>"></script>
<script src="<?= asset('assets/vendor_components/datatable/datatables.min.js') ?>"></script>

<!-- Doclinic App -->
<script src="<?= asset('js/jquery.smartmenus.js') ?>"></script>
<script src="<?= asset('js/menus.js') ?>"></script>
<script src="<?= asset('js/template.js') ?>"></script>
<script src="<?= asset('js/pages/patients.js') ?>"></script>
