<?php
use Core\Permissions;

// Helpers para resaltar elementos activos y abrir treeviews según la URL actual
if (!function_exists('currentPath')) {
    function currentPath(): string
    {
        return rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
    }
}

if (!function_exists('isActive')) {
    function isActive(string $path): string
    {
        $current = currentPath();
        return $current === rtrim($path, '/') ? ' is-active' : '';
    }
}

if (!function_exists('isActivePrefix')) {
    // Activo si la ruta actual comienza con el prefijo dado
    function isActivePrefix(string $prefix): string
    {
        $current = currentPath() . '/';
        $pref = rtrim($prefix, '/') . '/';
        return str_starts_with($current, $pref) ? ' is-active' : '';
    }
}

if (!function_exists('isTreeOpen')) {
    /**
     * Retorna ' menu-open' si la ruta actual empieza con alguno de los prefijos.
     * @param array $prefixes Prefijos de rutas, p.ej. ['/pacientes', '/solicitudes']
     */
    function isTreeOpen(array $prefixes): string
    {
        $current = currentPath() . '/';
        foreach ($prefixes as $p) {
            $pref = rtrim($p, '/') . '/';
            if (str_starts_with($current, $pref)) {
                return ' menu-open';
            }
        }
        return '';
    }
}
?>
<aside class="main-sidebar">
    <!-- sidebar-->
    <section class="sidebar position-relative">
        <div class="multinav">
            <div class="multinav-scroll" style="height: 100%;">

                <!-- sidebar menu-->
                <ul class="sidebar-menu" data-widget="tree">
                    <li class="<?= isActive('/dashboard') ?>">
                        <a href="/dashboard">
                            <i class="mdi mdi-view-dashboard"><span class="path1"></span><span class="path2"></span></i>
                            <span>Inicio</span>
                        </a>
                    </li>

                    <li class="treeview<?= isTreeOpen(['/pacientes']) ?>">
                        <a href="#">
                            <i class="icon-Compiling"><span class="path1"></span><span class="path2"></span></i>
                            <span>Pacientes</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="<?= isActive('/pacientes') ?>">
                                <a href="/pacientes">
                                    <i class="mdi mdi-account-multiple-outline"></i>Lista de Pacientes
                                </a>
                            </li>
                            <li class="<?= isActivePrefix('/pacientes/flujo') ?: isActive('/views/pacientes/flujo/flujo.php') ?>">
                                <a href="<?= BASE_URL . 'views/pacientes/flujo/flujo.php'; ?>">
                                    <i class="mdi mdi-timetable"></i>Flujo de Pacientes
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="treeview<?= isTreeOpen(['/cirugias', '/solicitudes', '/views/ipl', '/ipl', '/protocolos']) ?>">
                        <a href="#">
                            <i class="icon-Diagnostics"><span class="path1"></span><span class="path2"></span><span
                                        class="path3"></span></i>
                            <span>Cirugías</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="<?= isActive('/solicitudes') ?: isActive('/views/solicitudes/solicitudes.php') ?>">
                                <a href="<?= BASE_URL . 'solicitudes'; ?>">
                                    <i class="mdi mdi-file-document"></i>Solicitudes (Kanban)
                                </a>
                            </li>
                            <li class="<?= isActive('/cirugias') ?>">
                                <a href="<?= BASE_URL . 'cirugias'; ?>">
                                    <i class="mdi mdi-clipboard-check"></i>Protocolos Realizados
                                </a>
                            </li>
                            <li class="<?= isActive('/ipl') ?: isActive('/views/ipl/ipl_planificador_lista.php') ?>">
                                <a href="<?= BASE_URL . 'ipl'; ?>">
                                    <i class="mdi mdi-calendar-clock"></i>Planificador de IPL
                                </a>
                            </li>
                            <li class="<?= isActive('/protocolos') ?>">
                                <a href="<?= BASE_URL . 'protocolos'; ?>">
                                    <i class="mdi mdi-note-multiple"></i>Plantillas de Protocolos
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="treeview<?= isTreeOpen(['/insumos', '/insumos/medicamentos']) ?>">
                        <a href="#">
                            <i class="mdi mdi-medical-bag"><span class="path1"></span><span class="path2"></span><span
                                        class="path3"></span></i>
                            <span>Gestión de Insumos</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="<?= isActive('/insumos') ?>">
                                <a href="<?= BASE_URL . 'insumos'; ?>">
                                    <i class="mdi mdi-format-list-bulleted"></i>Lista de Insumos
                                </a>
                            </li>
                            <li class="<?= isActive('/insumos/medicamentos') ?>">
                                <a href="<?= BASE_URL . 'insumos/medicamentos'; ?>">
                                    <i class="mdi mdi-pill"></i>Lista de Medicamentos
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="treeview<?= isTreeOpen(['/views/informes', '/views/billing']) ?>">
                        <a href="#">
                            <i class="mdi mdi-file-chart"><span class="path1"></span><span class="path2"></span><span
                                        class="path3"></span></i>
                            <span>Facturación por Afiliación</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="<?= isActive('/views/informes/informe_isspol.php') ?>">
                                <a href="<?= BASE_URL . 'views/informes/informe_isspol.php'; ?>">
                                    <i class="mdi mdi-shield"></i>ISSPOL
                                </a>
                            </li>
                            <li class="<?= isActive('/views/informes/informe_issfa.php') ?>">
                                <a href="<?= BASE_URL . 'views/informes/informe_issfa.php'; ?>">
                                    <i class="mdi mdi-star"></i>ISSFA
                                </a>
                            </li>
                            <li class="<?= isActive('/views/informes/informe_iess.php') ?>">
                                <a href="<?= BASE_URL . 'views/informes/informe_iess.php'; ?>">
                                    <i class="mdi mdi-account"></i>IESS
                                </a>
                            </li>
                            <li class="<?= isActive('/views/informes/informe_particulares.php') ?>">
                                <a href="<?= BASE_URL . 'views/informes/informe_particulares.php'; ?>">
                                    <i class="mdi mdi-account-outline"></i>Particulares
                                </a>
                            </li>
                            <li class="<?= isActive('/views/billing/no_facturados.php') ?>">
                                <a href="<?= BASE_URL . 'views/billing/no_facturados.php'; ?>">
                                    <i class="mdi mdi-account-outline"></i>No Facturado
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="treeview<?= isTreeOpen(['/views/reportes']) ?>">
                        <a href="#">
                            <i class="mdi mdi-chart-areaspline"><span class="path1"></span><span
                                        class="path2"></span><span
                                        class="path3"></span></i>
                            <span>Estadísticas</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="<?= isActive('/views/reportes/estadistica_flujo.php') ?>">
                                <a href="<?= BASE_URL . 'views/reportes/estadistica_flujo.php'; ?>">
                                    <i class="mdi mdi-chart-line"></i>Flujo de Pacientes
                                </a>
                            </li>
                        </ul>
                    </li>

                    <?php
                    $rawPermissions = $_SESSION['permisos'] ?? [];
                    $normalizedPermissions = Permissions::normalize($rawPermissions);
                    $canManage = Permissions::containsAny($normalizedPermissions, ['administrativo', 'admin.usuarios', 'admin.roles']);
                    ?>
                    <?php if ($canManage): ?>
                        <li class="treeview<?= isTreeOpen(['/usuarios', '/roles', '/views/codes']) ?>">
                            <a href="#">
                                <i class="mdi mdi-settings"><span class="path1"></span><span class="path2"></span><span
                                            class="path3"></span></i>
                                <span>Administración</span>
                                <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                            </a>
                            <ul class="treeview-menu">
                                <li class="<?= isActive('/usuarios') ?>">
                                    <a href="<?= BASE_URL . 'usuarios'; ?>">
                                        <i class="mdi mdi-account-key"></i>Usuarios
                                    </a>
                                </li>
                                <li class="<?= isActive('/roles') ?>">
                                    <a href="<?= BASE_URL . 'roles'; ?>">
                                        <i class="mdi mdi-security"></i>Roles
                                    </a>
                                </li>
                                <li class="<?= isActive('/views/codes/index.php') ?>">
                                    <a href="<?= BASE_URL . 'views/codes/index.php'; ?>">
                                        <i class="mdi mdi-tag-text-outline"></i>Codificación
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </section>
</aside>
