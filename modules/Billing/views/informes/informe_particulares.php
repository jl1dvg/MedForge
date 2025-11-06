<?php
// Legacy billing report view relocated under modules/Billing/views/informes.
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 4) . '/bootstrap.php';
}
require_once BASE_PATH . '/helpers/InformesHelper.php';

use Controllers\BillingController;
use Modules\Pacientes\Services\PacienteService;
use Controllers\DashboardController;
use Helpers\InformesHelper;

$billingController = new BillingController($pdo);
$pacienteService = new PacienteService($pdo);

$fechaInicio = date('Y-m-01', strtotime('-5 months')); // últimos 6 meses incluyendo el actual
$fechaFin = date('Y-m-d');
$atenciones = $pacienteService->getAtencionesParticularesPorSemana($fechaInicio, $fechaFin);

// Filtrado por mes, afiliación y tipo
$filtroMes = $_GET['mes'] ?? '';
$filtroSemana = $_GET['semana'] ?? '';
$filtroAfiliacion = $_GET['afiliacion'] ?? '';
$filtroTipo = $_GET['tipo'] ?? '';
$filtroProcedimiento = strtolower($_GET['procedimiento'] ?? '');

$atenciones = array_filter($atenciones, function ($item) use ($filtroMes, $filtroSemana, $filtroAfiliacion, $filtroTipo, $filtroProcedimiento) {
    $mes = date('Y-m', strtotime($item['fecha']));
    $afiliacion = strtolower($item['afiliacion'] ?? '');
    if ($filtroMes && $mes !== $filtroMes) return false;
    if ($filtroSemana) {
        $dia = (int)date('j', strtotime($item['fecha']));
        if (
            ($filtroSemana == 1 && $dia > 7) ||
            ($filtroSemana == 2 && ($dia < 8 || $dia > 14)) ||
            ($filtroSemana == 3 && ($dia < 15 || $dia > 21)) ||
            ($filtroSemana == 4 && ($dia < 22 || $dia > 28)) ||
            ($filtroSemana == 5 && $dia < 29)
        ) return false;
    }
    if ($filtroAfiliacion && $afiliacion !== $filtroAfiliacion) return false;
    if ($filtroTipo && strtolower($item['tipo']) !== $filtroTipo) return false;
    if ($filtroProcedimiento && strpos(strtolower($item['procedimiento_proyectado'] ?? ''), $filtroProcedimiento) === false) return false;
    return true;
});

// Agrupar por mes
$datosPorMes = [];
foreach ($atenciones as $registro) {
    $mes = date('Y-m', strtotime($registro['fecha']));
    $datosPorMes[$mes][] = $registro;
}
$dashboardController = new DashboardController($pdo);
// Paso 1: Obtener todas las facturas disponibles
$username = $dashboardController->getAuthenticatedUser();
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
    <style>
        table.table td, table.table th {
            font-size: 0.875rem; /* slightly smaller font */
        }
    </style>
</head>
<body class="hold-transition light-skin sidebar-mini theme-primary fixed">

<div class="wrapper">

    <?php include BASE_PATH . '/views/partials/header.php'; ?>
    <?php include BASE_PATH . '/views/partials/navbar.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-full">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <h3 class="page-title"><i class="mdi mdi-file-chart-outline"></i> Informe de Atenciones
                            Particulares</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Informe de Atenciones
                                        Particulares
                                    </li>
                                </ol>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>
            <div class="content">
                    <!-- FILTRO COMPACTO Y AGRUPADO -->
                        <div class="card p-3 shadow-sm bg-light border border-primary mb-4">
                        <form method="GET">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label for="mes" class="form-label form-label-sm">Mes</label>
                                    <select name="mes" id="mes" class="form-select form-select-sm">
                                        <option value="">-- Todos los meses --</option>
                                        <?php
                                        $mesesUnicos = array_unique(array_map(function ($atencion) {
                                            return date('Y-m', strtotime($atencion['fecha']));
                                        }, $atenciones));
                                        sort($mesesUnicos);
                                        foreach ($mesesUnicos as $mesOption):
                                            $selected = ($_GET['mes'] ?? '') === $mesOption ? 'selected' : '';
                                            echo "<option value='$mesOption' $selected>" . date('F Y', strtotime($mesOption . "-01")) . "</option>";
                                        endforeach;
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="semana" class="form-label form-label-sm">Semana</label>
                                    <select name="semana" id="semana" class="form-select form-select-sm">
                                        <option value="">-- Todas las semanas --</option>
                                        <option value="1" <?= ($_GET['semana'] ?? '') === '1' ? 'selected' : '' ?>>Semana 1
                                            (1–7)
                                        </option>
                                        <option value="2" <?= ($_GET['semana'] ?? '') === '2' ? 'selected' : '' ?>>Semana 2
                                            (8–14)
                                        </option>
                                        <option value="3" <?= ($_GET['semana'] ?? '') === '3' ? 'selected' : '' ?>>Semana 3
                                            (15–21)
                                        </option>
                                        <option value="4" <?= ($_GET['semana'] ?? '') === '4' ? 'selected' : '' ?>>Semana 4
                                            (22–28)
                                        </option>
                                        <option value="5" <?= ($_GET['semana'] ?? '') === '5' ? 'selected' : '' ?>>Semana 5
                                            (29–31)
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="afiliacion" class="form-label form-label-sm">Afiliación</label>
                                    <select name="afiliacion" id="afiliacion" class="form-select form-select-sm">
                                        <option value="">-- Todas las afiliaciones --</option>
                                        <?php
                                        $afiliacionesUnicas = array_unique(array_map(fn($a) => strtolower($a['afiliacion'] ?? ''), $atenciones));
                                        sort($afiliacionesUnicas);
                                        foreach ($afiliacionesUnicas as $afiliacion):
                                            if (!$afiliacion) continue;
                                            $selected = ($_GET['afiliacion'] ?? '') === $afiliacion ? 'selected' : '';
                                            echo "<option value='$afiliacion' $selected>" . strtoupper($afiliacion) . "</option>";
                                        endforeach;
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="tipo" class="form-label form-label-sm">Tipo</label>
                                    <select name="tipo" id="tipo" class="form-select form-select-sm">
                                        <option value="">-- Todos los tipos --</option>
                                        <option value="consulta" <?= ($_GET['tipo'] ?? '') === 'consulta' ? 'selected' : '' ?>>
                                            Consulta
                                        </option>
                                        <option value="protocolo" <?= ($_GET['tipo'] ?? '') === 'protocolo' ? 'selected' : '' ?>>
                                            Protocolo
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="procedimiento" class="form-label form-label-sm">Procedimiento</label>
                                    <input type="text" name="procedimiento" id="procedimiento"
                                           class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($_GET['procedimiento'] ?? '') ?>"
                                           placeholder="Ej: consulta oftalmológica">
                                </div>

                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary btn-sm w-100">Aplicar filtros</button>
                                </div>

                                <div class="col-md-3">
                                    <a href="/informes/particulares"
                                       class="btn btn-secondary btn-sm w-100">Limpiar</a>
                                </div>
                            </div>
                        </form>
                    </div>
                <div class="row">
                    <div class="col-xl-10 col-lg-9 col-12">
                        <div class="box shadow-sm border">
                            <?php foreach ($datosPorMes as $mes => $registros): ?>
                                <h4 class="mt-4">Mes: <?= date('F Y', strtotime($mes . '-01')) ?></h4>
                                <div class="table-responsive">
                                    <table id="example"
                                           class="table table-striped table-hover table-sm invoice-archive">
                                        <thead class="bg-primary">
                                        <tr>
                                            <th>#</th>
                                            <th>HC</th>
                                            <th>Nombre</th>
                                            <th>Afiliación</th>
                                            <th>Tipo</th>
                                            <th>Fecha</th>
                                            <th><i class="mdi mdi-stethoscope"></i> Procedimiento</th>
                                            <th>Doctor</th>
                                        </tr>
                                        </thead>
                                        <tbody class="table-group-divider">
                                        <?php foreach ($registros as $i => $r): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><?= $r['hc_number'] ?></td>
                                                <td><?= ucwords(strtolower($r['nombre_completo'])) ?></td>
                                                <td>
                                                    <?php
                                                    $afiliacion = strtoupper($r['afiliacion'] ?? '—');
                                                    $colorAfiliacion = match ($afiliacion) {
                                                        'PARTICULAR' => 'bg-primary',
                                                        'HUMANA - COPAGO' => 'bg-info',
                                                        'BEST DOCTOR 100' => 'bg-success',
                                                        'SALUD (REEMBOLSO) NIVEL 5' => 'bg-warning',
                                                        'FUNDACIONES' => 'bg-danger',
                                                        default => 'bg-secondary'
                                                    };
                                                    ?>
                                                    <span class="badge <?= $colorAfiliacion ?>"><?= $afiliacion ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge <?= strtolower($r['tipo']) === 'consulta' ? 'bg-primary' : 'bg-success' ?>">
                                                        <?= strtolower($r['tipo']) === 'consulta' ? '🩺 Consulta' : '💉 Protocolo' ?>
                                                    </span>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                                                <?php
                                                $textoCompleto = $r['procedimiento_proyectado'] ?? '';
                                                $partes = explode(' - ', $textoCompleto);
                                                $procedimientoLegible = count($partes) > 2 ? trim(implode(' - ', array_slice($partes, 2))) : trim($textoCompleto);
                                                $procedimientoLegible = preg_replace('/ - (AO|OD|OI|AMBOS OJOS|OJO DERECHO|OJO IZQUIERDO)$/i', '', $procedimientoLegible);
                                                ?>
                                                <td><?= ucfirst(strtolower($procedimientoLegible)) ?: '—' ?></td>
                                                <td>
                                                    <?php
                                                    $doctor = $r['doctor'] ?? '';
                                                    if ($doctor) {
                                                        echo ucwords(strtolower($doctor));
                                                    } else {
                                                        echo '—';
                                                    }
                                                    ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-12">
                        <div class="box box-inverse box-success shadow-sm border border-primary">
                            <div class="box-body">
                                <div class="flexbox">
                                    <h5>Atenciones en <?= date('F Y', strtotime($mes . '-01')) ?></h5>
                                </div>

                                <div class="text-center my-2">
                                    <div class="fs-60"><?= count($atenciones) ?></div>
                                    <span>Total de atenciones</span>
                                </div>
                            </div>
                        </div>
                        <?php
                        $conteoAfiliaciones = [];
                        foreach ($atenciones as $a) {
                            $af = strtoupper(trim($a['afiliacion'] ?? 'DESCONOCIDA'));
                            $conteoAfiliaciones[$af] = ($conteoAfiliaciones[$af] ?? 0) + 1;
                        }
                        arsort($conteoAfiliaciones);
                        $conteoAfiliaciones = array_slice($conteoAfiliaciones, 0, 5, true);
                        $valoresBarra = implode(',', array_values($conteoAfiliaciones));
                        $etiquetasBarra = array_keys($conteoAfiliaciones);
                        ?>
                        <div class="box box-inverse shadow-sm">
                            <div class="box-header with-border">
                                <h5 class="box-title">Atenciones por afiliación</h5>
                            </div>
                            <div class="box-body">
                                <div class="flexbox mt-10">
                                    <div class="bar"
                                         data-peity='{ "fill": ["#666EE8", "#1E9FF2", "#28D094", "#FF4961", "#FF9149"], "height": 268, "width": 120, "padding":0.2 }'>
                                        <?= $valoresBarra ?>
                                    </div>
                                    <ul class="list-inline align-self-end text-end mb-0">
                                        <?php foreach ($conteoAfiliaciones as $af => $cantidad): ?>
                                            <li><?= ucfirst(strtolower($af)) ?> <span
                                                        class="badge badge-primary ms-2"><?= $cantidad ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.content -->

        </div>
    </div>
    <!-- /.content-wrapper -->
</div>
<?php include __DIR__ . '/../components/footer.php'; ?>

<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script> <!-- contiene jQuery -->
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="/public/assets/vendor_components/jquery.peity/jquery.peity.js"></script>


<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
<script src="/public/js/pages/data-table.js"></script>
<script src="/public/js/pages/app-ticket.js"></script>
</body>
</html>