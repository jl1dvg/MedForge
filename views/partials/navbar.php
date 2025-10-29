<?php
require_once __DIR__ . '/../../bootstrap.php';

// Helper para marcar el item activo según la URL actual
if (!function_exists('isActive')) {
    function isActive(string $path): string
    {
        $current = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        return str_ends_with($current, $path) ? ' is-active' : '';
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
                    <li class="<?= isActive('/views/main.php') ?>">
                        <a href="<?= BASE_URL . 'views/main.php'; ?>">
                            <i class="mdi mdi-view-dashboard"></i>Inicio
                        </a>
                    </li>

                    <li class="treeview">
                        <a href="#">
                            <i class="icon-Compiling"><span class="path1"></span><span class="path2"></span></i>
                            <span>Pacientes</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="<?= isActive('/views/pacientes/lista.php') ?>">
                                <a href="<?= BASE_URL . 'views/pacientes/lista.php'; ?>">
                                    <i class="mdi mdi-account-multiple-outline"></i>Lista de Pacientes
                                </a>
                            </li>
                            <li class="<?= isActive('/views/pacientes/flujo/flujo.php') ?>">
                                <a href="<?= BASE_URL . 'views/pacientes/flujo/flujo.php'; ?>">
                                    <i class="mdi mdi-timetable"></i>Flujo de Pacientes
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="treeview">
                        <a href="#">
                            <i class="icon-Diagnostics"><span class="path1"></span><span class="path2"></span><span
                                        class="path3"></span></i>
                            <span>Cirugías</span>
                            <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
                        </a>
                        <ul class="treeview-menu">
                            <li class="<?= isActive('/views/editor/lista_protocolos.php') ?>">
                                <a href="<?= BASE_URL . 'views/editor/lista_protocolos.php'; ?>">
                                    <i class="mdi mdi-clipboard-outline"></i>Solicitudes de Cirugía
                                </a>
                            </li>
                            <li class="<?= isActive('/views/reportes/cirugias.php') ?>">
                                <a href="<?= BASE_URL . 'views/reportes/cirugias.php'; ?>">
                                    <i class="mdi mdi-clipboard-check"></i>Protocolos Realizados
                                </a>
                            </li>
                            <li class="<?= isActive('/views/ipl/ipl_planificador_lista.php') ?>">
                                <a href="<?= BASE_URL . 'views/ipl/ipl_planificador_lista.php'; ?>">
                                    <i class="mdi mdi-calendar-clock"></i>Planificador de IPL
                                </a>
                            </li>
                            <li class="<?= isActive('/views/editor/lista_protocolos.php') ?>">
                                <a href="<?= BASE_URL . 'views/editor/lista_protocolos.php'; ?>">
                                    <i class="mdi mdi-note-multiple"></i>Plantillas de Protocolos
                                </a>
                            </li>
                            <li class="<?= isActive('/views/solicitudes/solicitudes.php') ?>">
                                <a href="<?= BASE_URL . 'views/solicitudes/solicitudes.php'; ?>">
                                    <i class="mdi mdi-file-document"></i>Solicitudes
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li>
                        <a href="<?= BASE_URL . 'views/insumos/insumos.php'; ?>">
                            <i class="mdi mdi-medical-bag"></i>Gestión de Insumos
                        </a>
                        <ul>
                            <li class="<?= isActive('/views/insumos/insumos.php') ?>">
                                <a href="<?= BASE_URL . 'views/insumos/insumos.php'; ?>">
                                    <i class="mdi mdi-format-list-bulleted"></i>Lista de Insumos
                                </a>
                            </li>
                            <li class="<?= isActive('/views/insumos/medicamentos.php') ?>">
                                <a href="<?= BASE_URL . 'views/insumos/medicamentos.php'; ?>">
                                    <i class="mdi mdi-pill"></i>Lista de Medicamentos
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li>
                        <a href="#">
                            <i class="mdi mdi-file-chart"></i>Facturación por Afiliación
                        </a>
                        <ul>
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

                    <li>
                        <a href="#">
                            <i class="mdi mdi-chart-areaspline"></i>Estadísticas
                        </a>
                        <ul>
                            <li class="<?= isActive('/views/reportes/estadistica_flujo.php') ?>">
                                <a href="<?= BASE_URL . 'views/reportes/estadistica_flujo.php'; ?>">
                                    <i class="mdi mdi-chart-line"></i>Flujo de Pacientes
                                </a>
                            </li>
                        </ul>
                    </li>

                    <?php if (in_array($_SESSION['permisos'] ?? '', ['administrativo', 'superuser'])): ?>
                        <li>
                            <a href="#">
                                <i class="mdi mdi-settings"></i>Administración
                            </a>
                            <ul>
                                <li class="<?= isActive('/views/users/index.php') ?>">
                                    <a href="<?= BASE_URL . 'views/users/index.php'; ?>">
                                        <i class="mdi mdi-account-key"></i>Usuarios
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