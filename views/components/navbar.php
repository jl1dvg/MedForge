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
            <uL>
                <li><a href="<?php echo BASE_URL . 'views/pacientes/lista.php'; ?>"><i class="mdi mdi-account-multiple"><span
                                    class="path1"></span><span class="path2"></span></i>Lista de Pacientes</a>
                </li>
                <li><a href="<?php echo BASE_URL . 'views/pacientes/flujo/flujo.php'; ?>"><i
                                class="mdi mdi-account-multiple"><span
                                    class="path1"></span><span class="path2"></span></i>Flujo de Pacientes</a>
                </li>
            </uL>
        </li>
        <li><a href="<?php echo BASE_URL . 'views/main/repots/solicitudes.php'; ?>"><i
                        class="mdi mdi-file-chart"><span
                            class="path1"></span><span class="path2"></span></i>Reportes y Solicitudes</a>
            <uL>
                <li><a href="<?php echo BASE_URL . 'views/reportes/cirugias.php'; ?>"><i
                                class="mdi mdi-table-large"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Reporte de Protocolos</a></li>
                <li><a href="<?php echo BASE_URL . 'views/editor/lista_protocolos.php'; ?>"><i
                                class="mdi mdi-timetable"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Solicitudes de Cirugía</a></li>
            </uL>
        </li>
        <li><a href="<?php echo BASE_URL . 'views/editor/lista_protocolos.php'; ?>"><i
                        class="mdi mdi-tooltip-edit"><span
                            class="path1"></span><span class="path2"></span></i>Protocolos Quirúrgicos</a>
        </li>
        <li><a href="<?php echo BASE_URL . 'views/insumos/insumos.php'; ?>"><i class="mdi mdi-medical-bag"><span
                            class="path1"></span><span class="path2"></span></i>Gestión de Insumos</a>
            <uL>
                <li><a href="<?php echo BASE_URL . 'views/insumos/insumos.php'; ?>"><i
                                class="mdi mdi-clipboard-list-outline"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Insumos</a></li>
            </uL>
        </li>
        <li><a href="#"><i
                        class="mdi mdi-file-document"><span
                            class="path1"></span><span class="path2"></span></i>Informes</a>
            <uL>
                <li><a href="<?php echo BASE_URL . 'views/informes/informe_isspol.php'; ?>"><i
                                class="mdi mdi-file-chart"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Consolidado ISSPOL</a></li>
                <li><a href="<?php echo BASE_URL . 'views/informes/informe_particulares.php'; ?>"><i
                                class="mdi mdi-file-chart"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Antenciones Particulares</a></li>
            </uL>
        </li>
        <?php if (in_array($_SESSION['permisos'] ?? '', ['administrativo', 'superuser'])): ?>
            <li><a href="#"><i class="mdi mdi-settings"></i>Configuración</a>
                <ul>
                    <li><a href="<?php echo BASE_URL . 'views/users/index.php'; ?>"><i class="mdi mdi-account-key"></i>Usuarios</a>
                    </li>
                </ul>
            </li>
        <?php endif; ?>
    </ul>
</nav>