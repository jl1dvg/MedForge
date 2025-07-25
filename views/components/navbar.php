<?php
require_once __DIR__ . '/../../bootstrap.php';
?>
<nav class="main-nav" role="navigation">

    <!-- Mobile menu toggle button (hamburger/x icon) -->
    <input id="main-menu-state" type="checkbox"/>
    <label class="main-menu-btn" for="main-menu-state">
        <span class="main-menu-btn-icon"></span> Toggle main menu visibility
    </label>

    <!-- Sample menu definition -->
    <ul id="main-menu" class="sm sm-blue">
        <li><a href="<?php echo BASE_URL . 'views/main.php'; ?>"><i class="mdi mdi-view-dashboard"><span
                            class="path1"></span><span class="path2"></span></i>Inicio</a>
        </li>
        <li><a href="#"><i
                        class="mdi mdi-account-multiple"><span
                            class="path1"></span><span class="path2"></span></i>Pacientes</a>
            <ul>
                <li><a href="<?php echo BASE_URL . 'views/pacientes/lista.php'; ?>"><i
                                class="mdi mdi-account-circle"><span
                                    class="path1"></span><span class="path2"></span></i>Lista de Pacientes</a>
                </li>
                <li><a href="<?php echo BASE_URL . 'views/pacientes/flujo/flujo.php'; ?>"><i
                                class="mdi mdi-account-convert"><span
                                    class="path1"></span><span class="path2"></span></i>Flujo de Pacientes</a>
                </li>
            </ul>
        </li>
        <li><a href="#"><i class="mdi mdi-hospital-building"><span
                            class="path1"></span><span class="path2"></span></i>Cirugías</a>
            <ul>
                <li><a href="<?php echo BASE_URL . 'views/editor/lista_protocolos.php'; ?>"><i
                                class="mdi mdi-timetable"><span
                                    class="path1"></span><span class="path2"></span></i>Solicitudes de Cirugía</a></li>
                <li><a href="<?php echo BASE_URL . 'views/reportes/cirugias.php'; ?>"><i
                                class="mdi mdi-table-large"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Protocolos Realizados</a></li>
                <li><a href="<?php echo BASE_URL . 'views/ipl/ipl_planificador_lista.php'; ?>"><i
                                class="mdi mdi-table-large"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Planificador de IPL</a></li>
                <li><a href="<?php echo BASE_URL . 'views/editor/lista_protocolos.php'; ?>"><i
                                class="mdi mdi-tooltip-edit"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Plantillas de Protocolos</a></li>
                <li><a href="<?php echo BASE_URL . 'views/solicitudes/solicitudes.php'; ?>"><i
                                class="mdi mdi-tooltip-edit"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Solicitudes</a></li>
            </ul>
        </li>
        <li><a href="<?php echo BASE_URL . 'views/insumos/insumos.php'; ?>"><i class="mdi mdi-medical-bag"><span
                            class="path1"></span><span class="path2"></span></i>Gestión de Insumos</a>
            <ul>
                <li><a href="<?php echo BASE_URL . 'views/insumos/insumos.php'; ?>"><i
                                class="mdi mdi-pharmacy"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Lista de Insumos</a></li>
                <li><a href="<?php echo BASE_URL . 'views/insumos/medicamentos.php'; ?>"><i
                                class="mdi mdi-bowling"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Lista de Medicamentos</a></li>
            </ul>
        </li>
        <li><a href="#"><i
                        class="mdi mdi-file-chart"><span
                            class="path1"></span><span class="path2"></span></i>Facturación por Afiliación</a>
            <ul>
                <li><a href="<?php echo BASE_URL . 'views/informes/informe_isspol.php'; ?>"><i
                                class="mdi mdi-file-chart"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>ISSPOL</a></li>
                <li><a href="<?php echo BASE_URL . 'views/informes/informe_issfa.php'; ?>"><i
                                class="mdi mdi-file-chart"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>ISSFA</a></li>
                <li><a href="<?php echo BASE_URL . 'views/informes/informe_iess.php'; ?>"><i
                                class="mdi mdi-file-chart"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>IESS</a></li>
                <li><a href="<?php echo BASE_URL . 'views/informes/informe_particulares.php'; ?>"><i
                                class="mdi mdi-file-chart"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Particulares</a></li>
            </ul>
        </li>
        <li><a href="#"><i class="mdi mdi-chart-areaspline"><span class="path1"></span><span class="path2"></span></i>Estadísticas</a>
            <ul>
                <li><a href="<?php echo BASE_URL . 'views/reportes/estadistica_flujo.php'; ?>"><i
                                class="mdi mdi-chart-line"><span class="path1"></span><span class="path2"></span></i>Flujo
                        de Pacientes</a></li>
            </ul>
        </li>
        <?php if (in_array($_SESSION['permisos'] ?? '', ['administrativo', 'superuser'])): ?>
            <li><a href="#"><i class="mdi mdi-settings"></i>Administración</a>
                <ul>
                    <li><a href="<?php echo BASE_URL . 'views/users/index.php'; ?>"><i class="mdi mdi-account-key"></i>Usuarios</a>
                    </li>
                </ul>
            </li>
        <?php endif; ?>
    </ul>
</nav>