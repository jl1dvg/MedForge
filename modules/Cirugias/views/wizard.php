<?php
/** @var Modules\Cirugias\Models\Cirugia $cirugia */
/** @var string $username */
/** @var array $scripts */
$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/datatable/datatables.min.js',
    'assets/vendor_components/tiny-editable/mindmup-editabletable.js',
    'assets/vendor_components/tiny-editable/numeric-input-example.js',
    'assets/vendor_components/jquery-steps-master/build/jquery.steps.js',
    'assets/vendor_components/jquery-validation-1.17.0/dist/jquery.validate.min.js',
    'js/pages/steps.js',
    'js/modules/cirugias_wizard.js',
]);
?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Modificar Información del Procedimiento</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/cirugias">Reporte de Cirugías</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Editar protocolo</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="box">
        <div class="box-header with-border">
            <h4 class="box-title">Modificar información del procedimiento</h4>
        </div>
        <div class="box-body wizard-content">
            <form action="/cirugias/wizard/guardar" method="POST" class="tab-wizard vertical wizard-circle">
                <input type="hidden" name="form_id" value="<?= htmlspecialchars($cirugia->form_id) ?>">
                <input type="hidden" name="hc_number" value="<?= htmlspecialchars($cirugia->hc_number) ?>">

                <?php include __DIR__ . '/partials/paciente.php'; ?>
                <?php include __DIR__ . '/partials/procedimiento.php'; ?>
                <?php include __DIR__ . '/partials/staff.php'; ?>
                <?php include __DIR__ . '/partials/anestesia.php'; ?>
                <?php include __DIR__ . '/partials/operatorio.php'; ?>
                <?php include __DIR__ . '/partials/insumos.php'; ?>
                <?php include __DIR__ . '/partials/medicamentos.php'; ?>
                <?php include __DIR__ . '/partials/resumen.php'; ?>
            </form>
        </div>
    </div>
</section>

<script>
    const afiliacionCirugia = "<?= addslashes(strtolower($cirugia->afiliacion ?? '')) ?>";
    const insumosDisponiblesJSON = <?= json_encode($insumosDisponibles, JSON_UNESCAPED_UNICODE) ?>;
    const categoriasInsumos = <?= json_encode($categoriasInsumos, JSON_UNESCAPED_UNICODE) ?>;
    const categorias = <?= json_encode(array_map(fn($cat) => [
        'value' => $cat,
        'label' => ucfirst(str_replace('_', ' ', $cat))
    ], $categoriasInsumos), JSON_UNESCAPED_UNICODE) ?>;
    const categoriaOptionsHTML = categorias.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('');
    const medicamentoOptionsHTML = `<?= addslashes(implode('', array_map(fn($m) => "<option value='{$m['id']}'>" . htmlspecialchars($m['medicamento']) . "</option>", $opcionesMedicamentos))) ?>`;
    const viaOptionsHTML = `<?= addslashes(implode('', array_map(fn($v) => "<option value='{$v}'>" . htmlspecialchars($v) . "</option>", $viasDisponibles))) ?>`;
    const responsableOptionsHTML = `<?= addslashes(implode('', array_map(fn($r) => "<option value='{$r}'>" . htmlspecialchars($r) . "</option>", $responsablesMedicamentos))) ?>`;
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

