<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;
use Models\CodeType;
use Models\CodeCategory;
use Models\Tarifario;

$dashboardController = new DashboardController($pdo);
$username = $dashboardController->getAuthenticatedUser();

// === Fallbacks cuando se entra directo a la vista (p.ej., /views/codes) ===
// Poblar selects si no vienen desde el controlador
if (!isset($types) || !is_array($types)) {
    $types = (new CodeType($pdo))->allActive();
}
if (!isset($cats) || !is_array($cats)) {
    $cats = (new CodeCategory($pdo))->allActive();
}
// Filtros desde GET si no vienen construidos
if (!isset($f) || !is_array($f)) {
    $f = [
        'q' => $_GET['q'] ?? '',
        'code_type' => $_GET['code_type'] ?? '',
        'superbill' => $_GET['superbill'] ?? '',
        'active' => isset($_GET['active']) ? 1 : 0,
        'reportable' => isset($_GET['reportable']) ? 1 : 0,
        'financial_reporting' => isset($_GET['financial_reporting']) ? 1 : 0,
    ];
}
// Poblar filas y totales si no vienen desde el controlador
if (!isset($rows) || !is_array($rows)) {
    $tarifario = new Tarifario($pdo);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pagesize = 100;
    $offset = ($page - 1) * $pagesize;
    $rows = $tarifario->search($f, $offset, $pagesize);
    $total = $tarifario->count($f);
}
// === Fin fallbacks ===
?>
<?php
// Resolver robusto del front controller:
// - Si estamos sirviendo desde /views/... forzamos a /public/index.php (tu router)
// - Si ya estamos en /public/index.php, usamos ese.
$script = $_SERVER['SCRIPT_NAME'] ?? '';
if (strpos($script, '/views/') !== false) {
    $front = '/public/index.php';
} else {
    $front = rtrim($script, '/');
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/public/images/favicon.ico">
    <title>Códigos</title>

    <!-- Vendors Style-->
    <link rel="stylesheet" href="/public/css/vendors_css.css">
    <!-- Style-->
    <link rel="stylesheet" href="/public/css/horizontal-menu.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/skin_color.css">

</head>
<body class="layout-top-nav light-skin theme-primary fixed">

<div class="wrapper">
    <!-- Content Wrapper. Contains page content -->
    <?php include __DIR__ . '/../components/header.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-full">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <h3 class="page-title">Códigos</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Códigos</li>
                                </ol>
                            </nav>
                        </div>
                    </div>

                </div>
            </div>
            <!-- Contenido principal -->
            <section class="content">
                <div class="row">
                    <div class="col-12">
                        <div class="box">
                            <div class="box-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h3 class="mb-0">Códigos</h3>
                                    <a href="<?= htmlspecialchars($front) ?>/codes/create"
                                       class="btn btn-primary btn-sm">+
                                        Nuevo
                                        código</a>
                                </div>
                                <form class="card card-body mb-3" method="get" action="/views/codes">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label mb-0">Buscar</label>
                                            <input type="text" name="q" class="form-control form-control-sm"
                                                   value="<?= htmlspecialchars($f['q'] ?? '') ?>"
                                                   placeholder="Código o descripción">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0">Tipo</label>
                                            <select name="code_type" class="form-select form-select-sm">
                                                <option value="">— Todos —</option>
                                                <?php foreach ($types as $t): ?>
                                                    <option value="<?= htmlspecialchars($t['key_name'] ?? $t['label'] ?? '') ?>"
                                                        <?= (!empty($f['code_type']) && ($f['code_type'] == ($t['key_name'] ?? ''))) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($t['label']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label mb-0">Categoría</label>
                                            <select name="superbill" class="form-select form-select-sm">
                                                <option value="">— Todas —</option>
                                                <?php foreach ($cats as $c): ?>
                                                    <option value="<?= htmlspecialchars($c['slug']) ?>"
                                                        <?= (!empty($f['superbill']) && $f['superbill'] == $c['slug']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($c['title']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="f_active"
                                                       name="active"
                                                       value="1" <?= !empty($f['active']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="f_active">Activos</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="f_reportable"
                                                       name="reportable"
                                                       value="1" <?= !empty($f['reportable']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="f_reportable">Dx Rep</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="f_finrep"
                                                       name="financial_reporting"
                                                       value="1" <?= !empty($f['financial_reporting']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="f_finrep">Serv Rep</label>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <button class="btn btn-secondary btn-sm">Filtrar</button>
                                        </div>
                                    </div>
                                </form>
                                <div class="table-responsive">
                                    <table id="codesTable"
                                           class="table table-striped table-hover table-sm invoice-archive">
                                        <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Mod</th>
                                            <th>Act</th>
                                            <th>Category</th>
                                            <th>Dx Rep</th>
                                            <th>Serv Rep</th>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Short</th>
                                            <th>Related</th>
                                            <th class="text-end">N1</th>
                                            <th class="text-end">N2</th>
                                            <th class="text-end">N3</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td colspan="14" class="text-center text-muted">Cargando…</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <?php include __DIR__ . '/../components/footer.php'; ?>
</div>

<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script>
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/rowgroup/1.3.1/css/rowGroup.dataTables.min.css">
<script src="https://cdn.datatables.net/rowgroup/1.3.1/js/dataTables.rowGroup.min.js"></script>
<script>
    $(function () {
        $('#codesTable').DataTable({
            language: {url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'},
            processing: true,
            serverSide: true,
            responsive: true,
            autoWidth: false,
            deferRender: true,
            searching: false,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            // crea un viewport con scroll interno y evita que el resto del layout “corte” la tabla
            scrollY: '60vh',
            scrollCollapse: true,
            ajax: {
                url: '/public/index.php/codes/datatable',
                type: 'GET',
                data: function (d) {
                    d.q = $('input[name="q"]').val() || '';
                    d.code_type = $('select[name="code_type"]').val() || '';
                    d.superbill = $('select[name="superbill"]').val() || '';
                    d.active = $('#f_active').is(':checked') ? 1 : 0;
                    d.reportable = $('#f_reportable').is(':checked') ? 1 : 0;
                    d.financial_reporting = $('#f_finrep').is(':checked') ? 1 : 0;
                }
            },
            columns: [
                {data: 'codigo'},           // 0 Code
                {data: 'modifier'},         // 1 Mod
                {data: 'active_text'},      // 2 Act (Sí/No render en backend)
                {data: 'category'},         // 3 Category (título)
                {data: 'reportable_text'},  // 4 Dx Rep
                {data: 'finrep_text'},      // 5 Serv Rep
                {data: 'code_type'},        // 6 Type
                {data: 'descripcion'},      // 7 Description
                {data: 'short_description'},// 8 Short
                {data: 'related'},          // 9 Related (conteo o texto)
                {data: 'valor1', className: 'text-end'}, // 10 N1
                {data: 'valor2', className: 'text-end'}, // 11 N2
                {data: 'valor3', className: 'text-end'}, // 12 N3
                {data: 'acciones', orderable: false, searchable: false} // 13 Acciones (botón Editar)
            ],
            order: [[0, 'asc']],
            rowGroup: {dataSrc: 3} // agrupa por Category si quieres
        });
    });
</script>

<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>
<script src="/public/js/pages/appointments.js"></script>
</body>
</html>
