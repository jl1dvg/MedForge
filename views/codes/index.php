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
    <style>
        .table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8f9fa;
        }
    </style>
</head>
<body class="layout-top-nav light-skin theme-primary fixed">
<div class="wrapper">
    <!-- Content Wrapper. Contains page content -->
    <?php include __DIR__ . '/../components/header.php'; ?>

    <div class="content-wrapper">
        <div class="container-full">
            <!-- Content Wrapper. Contains page content -->
            <div class="container-fluid py-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0">Códigos</h3>
                    <a href="<?= htmlspecialchars($front) ?>/codes/create" class="btn btn-primary btn-sm">+ Nuevo
                        código</a>
                </div>
                <form class="card card-body mb-3" method="get" action="/views/codes">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label mb-0">Buscar</label>
                            <input type="text" name="q" class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($f['q'] ?? '') ?>" placeholder="Código o descripción">
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
                                <input class="form-check-input" type="checkbox" id="f_active" name="active"
                                       value="1" <?= !empty($f['active']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="f_active">Activos</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="f_reportable" name="reportable"
                                       value="1" <?= !empty($f['reportable']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="f_reportable">Dx Rep</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="f_finrep" name="financial_reporting"
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
                    <table class="table table-striped table-bordered align-middle">
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
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="14" class="text-center text-muted">Sin resultados</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['codigo']) ?></td>
                                    <td><?= htmlspecialchars($r['modifier'] ?? '') ?></td>
                                    <td><?= !empty($r['active']) ? 'Sí' : 'No' ?></td>
                                    <td><?= htmlspecialchars($r['superbill'] ?? '') ?></td>
                                    <td><?= !empty($r['reportable']) ? 'Sí' : 'No' ?></td>
                                    <td><?= !empty($r['financial_reporting']) ? 'Sí' : 'No' ?></td>
                                    <td><?= htmlspecialchars($r['code_type'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($r['descripcion'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($r['short_description'] ?? '') ?></td>
                                    <td>
                                        <!-- Mostrar conteo si el controlador lo provee en el futuro -->
                                    </td>
                                    <td class="text-end"><?= number_format((float)($r['valor_facturar_nivel1'] ?? 0), 2) ?></td>
                                    <td class="text-end"><?= number_format((float)($r['valor_facturar_nivel2'] ?? 0), 2) ?></td>
                                    <td class="text-end"><?= number_format((float)($r['valor_facturar_nivel3'] ?? 0), 2) ?></td>
                                    <td class="text-end">
                                        <a href="<?= htmlspecialchars($front) ?>/codes/<?= (int)$r['id'] ?>/edit"
                                           class="btn btn-sm btn-outline-primary">Editar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                // Calcula páginas de forma segura
                $pages = max(1, (int)ceil(($total ?? 0) / max(1, ($pagesize ?? 100))));
                $page = (int)($page ?? 1);
                $page = max(1, min($page, $pages)); // clamp a rango válido

                // Construye query conservando filtros actuales
                $q = $_GET ?? [];
                unset($q['page']);
                $buildUrl = function (int $p) use ($q, $front): string {
                    $q['page'] = $p;
                    return $front . '/codes?' . http_build_query($q);
                };

                // Rango mostrado (X–Y de Z)
                $from = ($total ?? 0) > 0 ? (($page - 1) * ($pagesize ?? 100)) + 1 : 0;
                $to = ($total ?? 0) > 0 ? min($from + ($pagesize ?? 100) - 1, (int)($total ?? 0)) : 0;
                ?>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted">
                        Mostrando <?= number_format((int)$from) ?>–<?= number_format((int)$to) ?>
                        de <?= number_format((int)($total ?? 0)) ?>
                    </small>

                    <nav aria-label="Paginación de códigos">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Primera -->
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars($buildUrl(1)) ?>"
                                   aria-label="Primera" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>«</a>
                            </li>
                            <!-- Anterior -->
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars($buildUrl(max(1, $page - 1))) ?>"
                                   aria-label="Anterior" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>‹</a>
                            </li>

                            <?php
                            // Ventana de páginas (máx 7 botones)
                            $window = 7;
                            $half = (int)floor($window / 2);
                            $start = max(1, $page - $half);
                            $end = min($pages, $start + $window - 1);
                            // si no llena ventana, corre inicio hacia atrás
                            $start = max(1, min($start, $end - $window + 1));
                            ?>

                            <?php for ($p = $start; $p <= $end; $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= htmlspecialchars($buildUrl($p)) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Siguiente -->
                            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars($buildUrl(min($pages, $page + 1))) ?>"
                                   aria-label="Siguiente" <?= $page >= $pages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>›</a>
                            </li>
                            <!-- Última -->
                            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars($buildUrl($pages)) ?>"
                                   aria-label="Última" <?= $page >= $pages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>»</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../components/footer.php'; ?>
</div>
</body>
</html>
