<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../bootstrap.php';

use Controllers\DashboardController;
use Modules\Pacientes\Services\PacienteService;
use Controllers\ReporteCirugiasController;
use Controllers\IplPlanificadorController;
use Helpers\ProtocoloHelper;

$reporteCirugiasController = new ReporteCirugiasController($pdo);
$pacienteService = new PacienteService($pdo);
$dashboardController = new DashboardController($pdo);
$verificacionController = new IplPlanificadorController($pdo);


$cirugias = $reporteCirugiasController->obtenerCirugias();
$form_id = $_GET['form_id'] ?? null;
$hc_number = $_GET['hc_number'] ?? null;
$cirugia = $reporteCirugiasController->obtenerCirugiaPorId($form_id, $hc_number);
$username = $dashboardController->getAuthenticatedUser();
$cirujanos = $pacienteService->obtenerStaffPorEspecialidad();
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/public/images/favicon.ico">

    <title>Asistente CIVE - Dashboard</title>

    <!-- Vendors Style-->
    <link rel="stylesheet" href="/public/css/vendors_css.css">

    <!-- Style-->
    <link rel="stylesheet" href="/public/css/horizontal-menu.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/skin_color.css">

    <!-- Custom Styles -->
    <style>
        .is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
    </style>

</head>
<body class="hold-transition light-skin sidebar-mini theme-primary fixed">

<div class="wrapper">

    <?php include __DIR__ . '/../../components/header.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-full">
            <section class="content">

                <!-- vertical wizard -->
                <?php
                if (!$cirugia) {
                    die("No se encontró información para el form_id y hc_number proporcionados.");
                }
                ?>

                <!-- Formulario de modificación de información -->
                <div class="box">
                    <div class="box-header with-border">
                        <h4 class="box-title">Modificar Información del Procedimiento</h4>
                    </div>
                    <div class="box-body wizard-content">
                        <form action="/views/reportes/wizard_cirugia/guardar.php" method="POST"
                              class="tab-wizard vertical wizard-circle">
                            <!-- Enviar form_id y hc_number ocultos para saber qué registro actualizar -->
                            <input type="hidden" name="form_id" value="<?php echo htmlspecialchars($form_id); ?>">
                            <input type="hidden" name="hc_number" value="<?php echo htmlspecialchars($hc_number); ?>">

                            <!-- Sección 1: Datos del Paciente -->
                            <?php include __DIR__ . '/partials/paciente.php'; ?>

                            <!-- Sección 2: Procedimientos, Diagnósticos y Lateralidad -->
                            <?php include __DIR__ . '/partials/procedimiento.php'; ?>

                            <!-- Sección 3: Staff Quirúrgico -->
                            <?php include __DIR__ . '/partials/staff.php'; ?>

                            <!-- Sección 4: Fechas, Horas y Tipo de Anestesia -->
                            <?php include __DIR__ . '/partials/anestesia.php'; ?>

                            <!-- Sección 5: Procedimiento -->
                            <?php include __DIR__ . '/partials/operatorio.php'; ?>

                            <!-- Sección 5: Insumos -->
                            <?php include __DIR__ . '/partials/insumos.php'; ?>

                            <!-- Sección 6: Medicamentos (Kardex) -->
                            <?php include __DIR__ . '/partials/medicamentos.php'; ?>

                            <!-- Sección Final: Resumen -->
                            <?php include __DIR__ . '/partials/resumen.php'; ?>
                        </form>
                    </div>
                </div>                <!-- /.box -->

            </section>
            <!-- /.content -->
        </div>
    </div>
    <!-- /.content-wrapper -->

    <?php include __DIR__ . '/../../components/footer.php'; ?>
</div>
<!-- ./wrapper -->

<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script>
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="/public/assets/vendor_components/jquery-steps-master/build/jquery.steps.js"></script>
<script src="/public/assets/vendor_components/jquery-validation-1.17.0/dist/jquery.validate.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/mindmup-editabletable.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/numeric-input-example.js"></script>

<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>

<script src="/public/js/pages/steps.js"></script>
<style>
    #insumosTable tbody tr.categoria-equipos {
        background-color: #d4edda !important;
    }

    #insumosTable tbody tr.categoria-anestesia {
        background-color: #fff3cd !important;
    }

    #insumosTable tbody tr.categoria-quirurgicos {
        background-color: #cce5ff !important;
    }
</style>
<script>
    const afiliacionCirugia = "<?php echo strtolower($cirugia->afiliacion); ?>";
    const insumosDisponiblesJSON = <?php echo json_encode($insumosDisponibles); ?>;
    const categoriasInsumos = <?php echo json_encode($categorias); ?>;
    const categoriaOptionsHTML = `<?= addslashes(
        implode('', array_map(fn($cat) => "<option value='$cat'>" . ucfirst(str_replace('_', ' ', $cat)) . "</option>", $categorias))
    ) ?>`;
    const medicamentoOptionsHTML = `<?= addslashes(
        implode('', array_map(fn($m) => "<option value='{$m['id']}'>" . htmlspecialchars($m['medicamento']) . "</option>", $opcionesMedicamentos))
    ) ?>`;
    const viaOptionsHTML = `<?= addslashes(
        implode('', array_map(fn($v) => "<option value='$v'>" . htmlspecialchars($v) . "</option>", $vias))
    ) ?>`;
    const responsableOptionsHTML = `<?= addslashes(
        implode('', array_map(fn($r) => "<option value='$r'>" . htmlspecialchars($r) . "</option>", $responsables))
    ) ?>`;

    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('btnScrapeDerivacion');
        if (!btn) return;

        btn.addEventListener('click', function () {
            const form_id = btn.dataset.form;
            const hc_number = btn.dataset.hc;

            const formData = new FormData();
            formData.append('scrape_derivacion', '1');
            formData.append('form_id_scrape', form_id);
            formData.append('hc_number_scrape', hc_number);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(response => response.text())
                .then(html => {
                    const temp = document.createElement('div');
                    temp.innerHTML = html;
                    const resultado = temp.querySelector('.box');
                    if (resultado) {
                        document.querySelector('#resultadoScraper')?.remove();
                        btn.insertAdjacentElement('afterend', resultado);
                        resultado.setAttribute('id', 'resultadoScraper');
                    }
                });
        });
    });
</script>
<script src="/public/js/modules/cirugias_wizard.js"></script>

</body>
</html>

