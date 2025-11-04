<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\EstadisticaFlujoController;
use Controllers\DashboardController;

$dashboardController = new DashboardController($pdo);
$username = $dashboardController->getAuthenticatedUser();

$estadisticaFlujoController = new EstadisticaFlujoController($pdo);

// Armar filtros desde GET
$filtros = [
    'fecha_inicio' => $_GET['fecha_inicio'] ?? date('Y-m-d'),
    'fecha_fin' => $_GET['fecha_fin'] ?? date('Y-m-d'),
    'medico' => $_GET['medico'] ?? null,
    'servicio' => $_GET['servicio'] ?? null,
];

// Obtener datos
$estadisticas = [];
if (!empty($_GET)) {
    $estadisticas = $estadisticaFlujoController->index($filtros);
}
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
    <!--<div id="loader"></div>-->

    <?php include __DIR__ . '/../components/header.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-full">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <h3 class="page-title">Estadística de Flujo de Pacientes</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Estadística de Flujo de
                                        Pacientes
                                    </li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="row">
                    <div class="col-12">
                        <div class="box">
                            <div class="box-body">

                                <div class="box">
                                    <div class="box-header with-border">
                                        <h4 class="box-title">Filtros de búsqueda</h4>
                                    </div>
                                    <div class="box-body">
                                        <!-- FILTRO COMPACTO Y AGRUPADO -->
                                        <div class="card p-3 shadow-sm bg-light border border-primary mb-4">
                                            <form method="GET">
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-md-3">
                                                        <label for="fecha_inicio" class="form-label form-label-sm">Fecha
                                                            Inicio</label>
                                                        <input type="date" class="form-control form-control-sm"
                                                               name="fecha_inicio"
                                                               value="<?= htmlspecialchars($_GET['fecha_inicio'] ?? date('Y-m-d')) ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="fecha_fin" class="form-label form-label-sm">Fecha
                                                            Fin</label>
                                                        <input type="date" class="form-control form-control-sm"
                                                               name="fecha_fin"
                                                               value="<?= htmlspecialchars($_GET['fecha_fin'] ?? date('Y-m-d')) ?>">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="medico"
                                                               class="form-label form-label-sm">Médico</label>
                                                        <select name="medico" class="form-select form-select-sm">
                                                            <option value="">-- Todos --</option>
                                                            <?php
                                                            $medicosUnicos = array_unique(array_map(fn($a) => $a['doctor'] ?? '', $estadisticas));
                                                            sort($medicosUnicos);
                                                            foreach ($medicosUnicos as $medico):
                                                                if (!$medico) continue;
                                                                $selected = ($_GET['medico'] ?? '') === $medico ? 'selected' : '';
                                                                echo "<option value=\"$medico\" $selected>" . htmlspecialchars($medico) . "</option>";
                                                            endforeach;
                                                            ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="afiliacion" class="form-label form-label-sm">Afiliación</label>
                                                        <select name="afiliacion" class="form-select form-select-sm">
                                                            <option value="">-- Todas --</option>
                                                            <?php
                                                            $afiliacionesUnicas = array_unique(array_map(fn($a) => strtolower($a['afiliacion'] ?? ''), $estadisticas));
                                                            sort($afiliacionesUnicas);
                                                            foreach ($afiliacionesUnicas as $afiliacion):
                                                                if (!$afiliacion) continue;
                                                                $selected = ($_GET['afiliacion'] ?? '') === $afiliacion ? 'selected' : '';
                                                                echo "<option value=\"$afiliacion\" $selected>" . strtoupper($afiliacion) . "</option>";
                                                            endforeach;
                                                            ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="servicio"
                                                               class="form-label form-label-sm">Servicio</label>
                                                        <select name="servicio" class="form-select form-select-sm">
                                                            <option value="">-- Todos --</option>
                                                            <?php
                                                            $serviciosUnicos = array_unique(array_map(fn($a) => $a['servicio'] ?? '', $estadisticas));
                                                            sort($serviciosUnicos);
                                                            foreach ($serviciosUnicos as $servicio):
                                                                if (!$servicio) continue;
                                                                $selected = ($_GET['servicio'] ?? '') === $servicio ? 'selected' : '';
                                                                echo "<option value=\"$servicio\" $selected>" . htmlspecialchars($servicio) . "</option>";
                                                            endforeach;
                                                            ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <button type="submit" class="btn btn-primary btn-sm w-100">
                                                            Aplicar filtros
                                                        </button>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <a href="estadistica_flujo.php"
                                                           class="btn btn-secondary btn-sm w-100">Limpiar</a>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-xl-10 col-lg-9 col-12">
                                        <div class="box shadow-sm border">
                                            <h4 class="mt-4">Resultados</h4>
                                            <div class="table-responsive">
                                                <table id="tabla-estadisticas"
                                                       class="table table-striped table-hover table-sm invoice-archive">
                                                    <thead class="bg-primary">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>HC</th>
                                                        <th>Doctor</th>
                                                        <th>Servicio</th>
                                                        <th>Afiliación</th>
                                                        <th>Fecha</th>
                                                        <th>Estado Agenda</th>
                                                        <th>Espera (min)</th>
                                                        <th>Sala (min)</th>
                                                        <th>Optometría (min)</th>
                                                        <th>Total (min)</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody class="table-group-divider">
                                                    <?php foreach ($estadisticas as $i => $fila): ?>
                                                        <tr>
                                                            <td><?= $i + 1 ?></td>
                                                            <td><?= htmlspecialchars($fila['hc_number'] ?? '—') ?></td>
                                                            <td><?= ucwords(strtolower($fila['doctor'] ?? '—')) ?></td>
                                                            <td><?= htmlspecialchars($fila['servicio'] ?? '—') ?></td>
                                                            <td>
                                                                <?php
                                                                $afiliacion = strtoupper($fila['afiliacion'] ?? '—');
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
                                                            <td><?= htmlspecialchars($fila['fecha'] ?? '—') ?></td>
                                                            <td>
                                                                <span class="badge <?php
                                                                $estado = strtolower((string)($fila['estado_agenda'] ?? ''));
                                                                echo match ($estado) {
                                                                    'agendado' => 'bg-primary',
                                                                    'llegado' => 'bg-info',
                                                                    'optometria' => 'bg-warning text-dark',
                                                                    'finalizado' => 'bg-success',
                                                                    default => 'bg-secondary'
                                                                };
                                                                ?>"><?= htmlspecialchars($fila['estado_agenda'] ?? '—') ?></span>
                                                            </td>
                                                            <td><?= $fila['tiempos']['espera'] ?? '-' ?></td>
                                                            <td><?= $fila['tiempos']['sala'] ?? '-' ?></td>
                                                            <td><?= $fila['tiempos']['optometria'] ?? '-' ?></td>
                                                            <td><?= $fila['tiempos']['total'] ?? '-' ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- /.box-body -->
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- /.content-wrapper -->
    <?php include __DIR__ . '/../components/footer.php'; ?>

</div>
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
<script>
    $(document).ready(function () {
        $('#tabla-estadisticas').DataTable({
            order: [],
            responsive: true,
            fixedHeader: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
            }
        });
    });
</script>
<canvas id="chartTiempos" height="100"></canvas>
<canvas id="chartAfiliacion" height="100" class="mt-4"></canvas>
<canvas id="chartEstados" height="100" class="mt-4"></canvas>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const estadisticas = <?= json_encode(array_values(array_filter($estadisticas, function ($row) {
        // Filtra los registros con tiempos válidos
        return isset($row['tiempos']['espera'], $row['tiempos']['sala'], $row['tiempos']['optometria'], $row['tiempos']['total']) &&
            is_numeric($row['tiempos']['espera']) &&
            is_numeric($row['tiempos']['sala']) &&
            is_numeric($row['tiempos']['optometria']) &&
            is_numeric($row['tiempos']['total']);
    }))) ?>;

    // Calcula promedios
    const promedios = estadisticas.reduce((acc, row) => {
        acc.espera += parseFloat(row.tiempos.espera);
        acc.sala += parseFloat(row.tiempos.sala);
        acc.optometria += parseFloat(row.tiempos.optometria);
        acc.total += parseFloat(row.tiempos.total);
        return acc;
    }, {espera: 0, sala: 0, optometria: 0, total: 0});

    const count = estadisticas.length || 1; // para evitar división por cero

    const dataPromedios = [
        (promedios.espera / count).toFixed(2),
        (promedios.sala / count).toFixed(2),
        (promedios.optometria / count).toFixed(2),
        (promedios.total / count).toFixed(2)
    ];

    // Chart de promedios
    new Chart(document.getElementById('chartTiempos').getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['Espera', 'Sala', 'Optometría', 'Total'],
            datasets: [{
                label: 'Promedio (min)',
                data: dataPromedios,
                backgroundColor: ['#007bff', '#17a2b8', '#ffc107', '#28a745']
            }]
        }
    });

    // Distribución por afiliación
    const afiliacionCount = {};
    estadisticas.forEach(row => {
        const af = (row.afiliacion || 'Desconocida').toUpperCase();
        afiliacionCount[af] = (afiliacionCount[af] || 0) + 1;
    });

    new Chart(document.getElementById('chartAfiliacion').getContext('2d'), {
        type: 'pie',
        data: {
            labels: Object.keys(afiliacionCount),
            datasets: [{
                data: Object.values(afiliacionCount),
                backgroundColor: ['#007bff', '#17a2b8', '#ffc107', '#28a745', '#dc3545', '#6c757d']
            }]
        }
    });

    // Distribución por estado_agenda
    const estadoCount = {};
    estadisticas.forEach(row => {
        const estado = (row.estado_agenda || 'Desconocido').toUpperCase();
        estadoCount[estado] = (estadoCount[estado] || 0) + 1;
    });

    new Chart(document.getElementById('chartEstados').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(estadoCount),
            datasets: [{
                data: Object.values(estadoCount),
                backgroundColor: ['#007bff', '#17a2b8', '#ffc107', '#28a745', '#6c757d']
            }]
        }
    });
</script>
</body>
</html>