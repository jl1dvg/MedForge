<?php
/** @var array $protocolo */
/** @var array $medicamentos */
/** @var array $opcionesMedicamentos */
/** @var array $insumosDisponibles */
/** @var array $insumosPaciente */
/** @var array $codigos */
/** @var array $staff */
/** @var array $vias */
/** @var array $responsables */
/** @var bool $duplicando */
/** @var bool $esNuevo */
/** @var string|null $duplicarId */
/** @var string $username */
/** @var array $scripts */
$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/datatable/datatables.min.js',
    'assets/vendor_components/tiny-editable/mindmup-editabletable.js',
    'assets/vendor_components/tiny-editable/numeric-input-example.js',
    'js/editor-protocolos.js',
    'js/autocomplete-operatorio.js',
]);
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title"><?= $esNuevo ? 'Nuevo protocolo' : 'Editar Protocolo' ?></h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/"><i class="mdi mdi-home-outline"></i> Inicio</a></li>
                        <li class="breadcrumb-item"><a href="/protocolos">Protocolos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?= htmlspecialchars($protocolo['membrete'] ?: 'Sin título', ENT_QUOTES, 'UTF-8') ?>
                        </li>
                    </ol>
                </nav>
            </div>
            <?php if ($duplicando): ?>
                <div class="alert alert-info mt-10">
                    <strong>Duplicando protocolo:</strong>
                    estás creando una copia basada en <em><?= htmlspecialchars($duplicarId ?? '', ENT_QUOTES, 'UTF-8') ?></em>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-lg-12 col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title"><?= $esNuevo ? 'Configurar protocolo' : 'Editar protocolo' ?></h4>
                </div>
                <form id="editarProtocoloForm" action="/protocolos/guardar" method="POST" class="form">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($protocolo['id'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="insumos" id="insumosInput" value='<?= json_encode($insumosPaciente, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'>
                    <input type="hidden" name="medicamentos" id="medicamentosInput" value='<?= json_encode($medicamentos, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>'>
                    <input type="hidden" name="operatorio" id="operatorioInput" value="<?= htmlspecialchars($protocolo['operatorio'], ENT_QUOTES, 'UTF-8') ?>">

                    <div class="box-body">
                        <div class="accordion mb-3" id="accordionGeneral">
                            <div class="accordion-item">
                                <h4 class="accordion-header" id="headingRequerido">
                                    <button class="accordion-button collapsed box-title text-info mt-20" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#collapseRequerido" aria-expanded="false"
                                            aria-controls="collapseRequerido" data-bs-parent="#accordionGeneral">
                                        <i class="fa fa-asterisk me-15"></i> Requerido
                                    </button>
                                </h4>
                                <div id="collapseRequerido" class="accordion-collapse collapse" aria-labelledby="headingRequerido">
                                    <div class="accordion-body">
                                        <?php include __DIR__ . '/secciones/requerido.php'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h4 class="accordion-header" id="headingStaff">
                                    <button class="accordion-button collapsed box-title text-info mt-20" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#collapseStaff" aria-expanded="false"
                                            aria-controls="collapseStaff" data-bs-parent="#accordionGeneral">
                                        <i class="fa fa-user-md me-15"></i> Staff
                                    </button>
                                </h4>
                                <div id="collapseStaff" class="accordion-collapse collapse" aria-labelledby="headingStaff">
                                    <div class="accordion-body">
                                        <?php include __DIR__ . '/secciones/staff.php'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h4 class="accordion-header" id="headingOperatorio">
                                    <button class="accordion-button collapsed box-title text-info mt-20" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#collapseOperatorio" aria-expanded="false"
                                            aria-controls="collapseOperatorio" data-bs-parent="#accordionGeneral">
                                        <i class="fa fa-server me-15"></i> Operatorio
                                    </button>
                                </h4>
                                <div id="collapseOperatorio" class="accordion-collapse collapse" aria-labelledby="headingOperatorio">
                                    <div class="accordion-body">
                                        <?php include __DIR__ . '/secciones/operatorio.php'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h4 class="accordion-header" id="headingEvolucion">
                                    <button class="accordion-button collapsed box-title text-info mt-20" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#collapseEvolucion" aria-expanded="false"
                                            aria-controls="collapseEvolucion" data-bs-parent="#accordionGeneral">
                                        <i class="fa fa-notes-medical me-15"></i> Evolución
                                    </button>
                                </h4>
                                <div id="collapseEvolucion" class="accordion-collapse collapse" aria-labelledby="headingEvolucion">
                                    <div class="accordion-body">
                                        <?php include __DIR__ . '/secciones/evolucion.php'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h4 class="accordion-header" id="headingKardex">
                                    <button class="accordion-button collapsed box-title text-info mt-20" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#collapseKardex" aria-expanded="false"
                                            aria-controls="collapseKardex" data-bs-parent="#accordionGeneral">
                                        <i class="fa fa-prescription-bottle-alt me-15"></i> Kardex
                                    </button>
                                </h4>
                                <div id="collapseKardex" class="accordion-collapse collapse" aria-labelledby="headingKardex">
                                    <div class="accordion-body">
                                        <?php include __DIR__ . '/secciones/kardex.php'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h4 class="accordion-header" id="headingInsumos">
                                    <button class="accordion-button collapsed box-title text-info mt-20" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#collapseInsumos" aria-expanded="false"
                                            aria-controls="collapseInsumos" data-bs-parent="#accordionGeneral">
                                        <i class="fa fa-boxes me-15"></i> Lista de Insumos
                                    </button>
                                </h4>
                                <div id="collapseInsumos" class="accordion-collapse collapse" aria-labelledby="headingInsumos">
                                    <div class="accordion-body">
                                        <?php include __DIR__ . '/secciones/insumos.php'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="box-footer">
                        <a href="/protocolos" class="btn btn-warning me-1">
                            <i class="ti-trash"></i> Cancelar
                        </a>
                        <button type="button" id="guardarProtocolo" class="btn btn-primary">
                            <i class="ti-save-alt"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
    const insumosDisponibles = <?= json_encode($insumosDisponibles, JSON_UNESCAPED_UNICODE) ?>;
    const opcionesMedicamentos = <?= json_encode($opcionesMedicamentos, JSON_UNESCAPED_UNICODE) ?>;
    const vias = <?= json_encode($vias, JSON_UNESCAPED_UNICODE) ?>;
    const responsables = <?= json_encode($responsables, JSON_UNESCAPED_UNICODE) ?>;
    const codigos = <?= json_encode($codigos, JSON_UNESCAPED_UNICODE) ?>;
    const staff = <?= json_encode($staff, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
