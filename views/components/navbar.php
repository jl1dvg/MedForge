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
        <li><a href="<?php echo BASE_URL . 'views/main.php'; ?>"><i class="icon-Layout-4-blocks"><span
                            class="path1"></span><span class="path2"></span></i>Dashboard</a>
        </li>
        <li><a href="<?php echo BASE_URL . 'views/pacientes/lista.php'; ?>"><i class="icon-Compiling"><span
                            class="path1"></span><span class="path2"></span></i>Pacientes</a>
            <ul>
                <li><a href="<?php echo BASE_URL . 'views/pacientes/lista.php'; ?>"><i class="icon-Commit"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Lista Pacientes</a></li>
                <li><a href="patient_details.html"><i class="icon-Commit"><span class="path1"></span><span
                                    class="path2"></span></i>Detalle de pacientes</a></li>
            </ul>
        </li>
        <li><a href="<?php echo BASE_URL . 'views/main/repots/solicitudes.php'; ?>"><i class="icon-Settings-1"><span
                            class="path1"></span><span class="path2"></span></i>Reportes</a>
            <uL>
                <li><a href="<?php echo BASE_URL . 'views/main/repots/qx_reports.php'; ?>"><i class="icon-Commit"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Reporte de Protocolos</a></li>
                <li><a href="<?php echo BASE_URL . 'views/main/repots/solicitudes.php'; ?>"><i class="icon-Commit"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Solicitudes de Cirugía</a></li>
            </uL>
        </li>
        <li><a href="<?php echo BASE_URL . 'views/main/editors/protocolos_templates_list.php'; ?>"><i
                        class="icon-Air-ballon"><span
                            class="path1"></span><span class="path2"></span></i>Editor de Protocolos</a>
        </li>
        <li><a href="<?php echo BASE_URL . 'views/main/insumos/insumos.php'; ?>"><i class="icon-Settings-1"><span
                            class="path1"></span><span class="path2"></span></i>Insumos</a>
            <uL>
                <li><a href="<?php echo BASE_URL . 'views/main/insumos/insumos.php'; ?>"><i class="icon-Commit"><span
                                    class="path1"></span><span
                                    class="path2"></span></i>Insumos</a></li>
            </uL>
        </li>
    </ul>
</nav>