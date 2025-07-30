<?php
ini_set('session.save_path', __DIR__ . '/../../sessions');
session_name('mi_sesion');
session_start();
require '../../bootstrap.php';  // Asegúrate de que la conexión esté configurada correctamente

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    // Redirigir al login si no está logueado
    header('Location: ../auth_login.html');
    exit();
}

// Obtener la información del usuario logueado
$user_id = $_SESSION['user_id'];
$sql = "SELECT username FROM users WHERE id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();
$stmt->close();
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="../../images/favicon.ico">

    <title>Asistente CIVE - Dashboard</title>

    <!-- Vendors Style-->
    <link rel="stylesheet" href="../css/vendors_css.css">

    <!-- Style-->
    <link rel="stylesheet" href="../css/horizontal-menu.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/skin_color.css">

</head>
<body class="layout-top-nav light-skin theme-primary fixed">

<div class="wrapper">
    <div id="loader"></div>
    <?php include '../header.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-full">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <h3 class="page-title">Reporte de Cirugías</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Reporte de Cirugías</li>
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
                                <h4 class="box-title">Cirugías Realizadas</h4>
                                <div class="table-responsive">
                                    <table id="surgeryTable" class="table table-striped table-hover">
                                        <thead>
                                        <tr>
                                            <th class="bb-2">No.</th>
                                            <th class="bb-2">C.I.</th>
                                            <th class="bb-2">Nombre</th>
                                            <th class="bb-2">Afiliación</th>
                                            <th class="bb-2">Fecha de Inicio</th>
                                            <th class="bb-2">Procedimiento</th>
                                            <th class="bb-2">Duración</th>
                                            <th class="bb-2">Status</th>
                                            <th class="bb-2">Result</th>
                                            <th class="bb-2">Impreso</th>
                                        </tr>
                                        </thead>
                                        <tbody id="patientTableBody">
                                        <?php
                                        // Ejecutar la consulta SQL
                                        $sql = "SELECT p.hc_number, p.fname, p.lname, p.lname2, p.fecha_nacimiento, p.ciudad, p.afiliacion, 
                                                pr.fecha_inicio, pr.id, pr.membrete, pr.form_id, pr.hora_inicio, pr.hora_fin, pr.printed,
                                                pr.dieresis, pr.exposicion, pr.hallazgo, pr.operatorio, pr.complicaciones_operatorio, pr.datos_cirugia, 
                                                pr.procedimientos, pr.lateralidad, pr.tipo_anestesia, pr.diagnosticos, pp.procedimiento_proyectado,
                                                pr.cirujano_1, pr.instrumentista, pr.cirujano_2, pr.circulante, pr.primer_ayudante, pr.anestesiologo, 
                                                pr.segundo_ayudante, pr.ayudante_anestesia, pr.tercer_ayudante, pr.status
                                                FROM patient_data p 
                                                INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
                                                LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
                                                ORDER BY pr.fecha_inicio DESC, pr.id DESC";
                                        $result = $mysqli->query($sql);

                                        // Verificar si hay resultados
                                        $counter = 1; // Inicializar un contador para enumerar las filas
                                        while ($row = $result->fetch_assoc()) {
                                            // Convertir hora_inicio y hora_fin a objetos DateTime para hacer la diferencia
                                            $hora_inicio = new DateTime($row['hora_inicio']);
                                            $hora_fin = new DateTime($row['hora_fin']);
                                            $duracion = $hora_inicio->diff($hora_fin); // Calcular la diferencia
                                            // Obtener el valor de la columna "printed"
                                            $printed = $row['printed'] ?? 0;  // Si no existe, por defecto es 0
                                            $buttonClass = $printed ? 'active' : '';  // Si $printed es 1, el botón estará activo (on)

                                            // Condiciones para determinar el estado
                                            $requiredFields = [$row['membrete'], $row['dieresis'], $row['exposicion'], $row['hallazgo'], $row['operatorio'],
                                                $row['complicaciones_operatorio'], $row['datos_cirugia'], $row['procedimientos'], $row['lateralidad'],
                                                $row['tipo_anestesia'], $row['diagnosticos'], $row['procedimiento_proyectado'], $row['fecha_inicio'],
                                                $row['hora_inicio'], $row['hora_fin']];

                                            $staffFields = [$row['cirujano_1'], $row['instrumentista'], $row['cirujano_2'], $row['circulante'],
                                                $row['primer_ayudante'], $row['anestesiologo'], $row['segundo_ayudante'],
                                                $row['ayudante_anestesia'], $row['tercer_ayudante']];

                                            $requiredFieldsFilled = true;  // Suponemos que los campos requeridos están llenos y válidos
                                            $staffCount = 0;  // Contador del staff válido
                                            $invalidFields = false;  // Indicador de campos inválidos (que contienen "CENTER" o "undefined")

// Lista de valores no permitidos
                                            $invalidValues = ['CENTER', 'undefined'];

                                            // Si el status es 1, directamente asignamos "revisado" y omitimos otras condiciones
                                            if ($row['status'] == 1) {
                                                $estado = 'revisado';
                                            } else {
                                                // Verificar si todos los campos requeridos están llenos y no contienen valores no permitidos
                                                foreach ($requiredFields as $field) {
                                                    if (!empty($field)) {
                                                        foreach ($invalidValues as $invalidValue) {
                                                            if (stripos($field, $invalidValue) !== false) {  // Busca la palabra prohibida
                                                                $invalidFields = true;
                                                                break 2;  // Salir de ambos bucles si se encuentra un valor no permitido
                                                            }
                                                        }
                                                    }
                                                }

                                                // Verificar que `cirujano_1` esté lleno y al menos otros 4 del staff estén completos y válidos
                                                if (!empty($row['cirujano_1'])) {
                                                    foreach ($staffFields as $staff) {
                                                        // Si el campo está lleno, verificar si contiene valores no permitidos
                                                        if (!empty($staff)) {
                                                            foreach ($invalidValues as $invalidValue) {
                                                                if (stripos($staff, $invalidValue) !== false) {
                                                                    $invalidFields = true;  // Campo lleno pero no válido
                                                                    break 2;  // Salir de ambos bucles si se encuentra un valor no permitido
                                                                }
                                                            }
                                                            // Si es válido, contamos el campo
                                                            $staffCount++;
                                                        }
                                                    }
                                                } else {
                                                    $invalidFields = true;  // cirujano_1 no puede estar vacío
                                                }

                                                // Determinar el estado basado en los campos y el staff
                                                if (!$invalidFields && $requiredFieldsFilled && $staffCount >= 5) {
                                                    $estado = 'no revisado';  // Si todo está lleno y válido pero el status es 0
                                                } else {
                                                    $estado = 'incompleto';  // Si hay campos inválidos o el staff no cumple con los requisitos
                                                }
                                            }

                                            // Asignar la clase CSS basada en el estado
                                            $badgeClass = '';
                                            switch ($estado) {
                                                case 'revisado':
                                                    $badgeClass = 'badge-success';
                                                    break;
                                                case 'no revisado':
                                                    $badgeClass = 'badge-warning';
                                                    break;
                                                case 'incompleto':
                                                    $badgeClass = 'badge-danger';
                                                    break;
                                            }

                                            // Mostrar los datos en la tabla
                                            echo "<tr>";
                                            echo "<td>" . $counter++ . "</td>";
                                            echo "<td>" . htmlspecialchars($row['hc_number']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['fname']) . " " . htmlspecialchars($row['lname']) . " " . htmlspecialchars($row['lname2']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['afiliacion']) . "</td>";
                                            echo "<td>" . date('d/m/Y', strtotime($row['fecha_inicio'])) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['membrete']) . "</td>";

                                            // Mostrar la duración en el formato HH:MM
                                            echo "<td>" . $duracion->format('%H:%I') . "</td>";

                                            // Columna Status (usando el badge basado en el estado)
                                            echo "<td><span class='badge $badgeClass'>" . ucfirst($estado) . "</span></td>";

                                            // Enlace para abrir el modal con datos
                                            echo "<td>
                                                <a href='#' data-bs-toggle='modal' data-bs-target='#resultModal' onclick='loadResult(" . json_encode($row) . ")' class='text-info'>Result</a>
                                                        <a href='#' data-bs-toggle='modal' data-bs-target='#comment-dialog' class='text-info'>Comment </a>
                                                      </td>";

                                            // Columna Signed (botón interactivo)
                                            echo "<td>
                                                        <button type='button' class='btn btn-sm btn-toggle $buttonClass' data-bs-toggle='button' aria-pressed='" . ($printed ? "true" : "false") . "' autocomplete='off'
                                                                onclick='togglePrintStatus(" . $row['form_id'] . ", \"" . $row['hc_number'] . "\", this, " . $printed . ")'>
                                                            <div class='handle'></div>
                                                        </button>
                                                      </td>";
                                            echo "</tr>";
                                        }
                                        ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- /.box-body -->
                        </div>
                    </div>
                </div>
            </section>
            <!-- /.content -->

        </div>
    </div>
    <!-- /.content-wrapper -->

    <!--Model Popup Area-->
    <!-- result modal content -->
    <div class="modal fade" id="resultModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="result-proyectado">Resultados</h4>
                    <h4 class="modal-title" id="result-popup">Resultados</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row justify-content-between">
                        <div class="col-md-7 col-12">
                            <h4 id="test-name">Diagnóstico</h4>
                        </div>
                        <div class="col-md-5 col-12">
                            <h4 class="text-end" id="lab-order-id">Orden ID</h4>
                        </div>
                    </div>
                    <!-- Nueva tabla para Diagnósticos -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-secondary">
                            <tr>
                                <th scope="col">CIE10</th>
                                <th scope="col">Detalle</th>
                            </tr>
                            </thead>
                            <tbody id="diagnostico-table">
                            <!-- Se llenará dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                    <!-- Nueva tabla para Procedimientos -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-secondary">
                            <tr>
                                <th scope="col">Código</th>
                                <th scope="col">Nombre del Procedimiento</th>
                            </tr>
                            </thead>
                            <tbody id="procedimientos-table">
                            <!-- Se llenará dinámicamente -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Nueva tabla para mostrar fecha de inicio, hora de inicio, hora de fin, y duración -->
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-secondary">
                            <tr>
                                <th>Fecha de Inicio</th>
                                <th>Hora de Inicio</th>
                                <th>Hora de Fin</th>
                                <th>Duración</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr id="timing-row">
                                <!-- Se llenará dinámicamente con 4 <td> -->
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-secondary">
                            <tr>
                                <th scope="col" colspan="2">Procedimiento</th>
                            </tr>
                            </thead>
                            <tbody id="result-table">
                            <!-- Se llenará dinámicamente -->
                            </tbody>
                        </table>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="bg-secondary">
                            <tr>
                                <th scope="col" colspan="2">Staff Quirúrgico</th>
                            </tr>
                            </thead>
                            <tbody id="staff-table">
                            <!-- Se llenará dinámicamente -->
                            </tbody>
                        </table>
                    </div>
                    <div class="comment">
                        <p><span class="fw-600">Comentario</span> : <span class="comment-here text-mute"></span></p>
                    </div>
                    <!-- Agregar checkbox para marcar como revisado -->
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="markAsReviewed">
                        <label class="form-check-label" for="markAsReviewed">Marcar como revisado</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger pull-right" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-info pull-right" onclick="redirectToEditProtocol()">Editar
                    </button>
                    <button type="button" class="btn btn-success pull-right" onclick="updateProtocolStatus()">Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- /.modal-dialog -->
</div>
<!-- /.modal -->
<!-- comment modal content -->
<div class="modal fade" id="comment-dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="comment-popup">Radiology Investigations - Comment</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row justify-content-between">
                    <div class="col-12">
                        <h4>Comment</h4>
                    </div>
                </div>
                <form>
                    <div class="form-group">
                        <textarea class="form-control" id="comment-area" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger pull-right" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success pull-right me-10">Save</button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>
<!-- /.modal -->

<?php include '../footer.php'; ?>
<!-- Control Sidebar -->
<aside class="control-sidebar">

    <div class="rpanel-title"><span class="pull-right btn btn-circle btn-danger" data-toggle="control-sidebar"><i
                    class="ion ion-close text-white"></i></span></div>  <!-- Create the tabs -->
    <ul class="nav nav-tabs control-sidebar-tabs">
        <li class="nav-item"><a href="#control-sidebar-home-tab" data-bs-toggle="tab" class="active"><i
                        class="mdi mdi-message-text"></i></a></li>
        <li class="nav-item"><a href="#control-sidebar-settings-tab" data-bs-toggle="tab"><i
                        class="mdi mdi-playlist-check"></i></a></li>
    </ul>
    <!-- Tab panes -->
    <div class="tab-content">
        <!-- Home tab content -->
        <div class="tab-pane active" id="control-sidebar-home-tab">
            <div class="flexbox">
                <a href="javascript:void(0)" class="text-grey">
                    <i class="ti-more"></i>
                </a>
                <p>Users</p>
                <a href="javascript:void(0)" class="text-end text-grey"><i class="ti-plus"></i></a>
            </div>
            <div class="lookup lookup-sm lookup-right d-none d-lg-block">
                <input type="text" name="s" placeholder="Search" class="w-p100">
            </div>
            <div class="media-list media-list-hover mt-20">
                <div class="media py-10 px-0">
                    <a class="avatar avatar-lg status-success" href="#">
                        <img src="../../images/avatar/1.jpg" alt="...">
                    </a>
                    <div class="media-body">
                        <p class="fs-16">
                            <a class="hover-primary" href="#"><strong>Tyler</strong></a>
                        </p>
                        <p>Praesent tristique diam...</p>
                        <span>Just now</span>
                    </div>
                </div>

                <div class="media py-10 px-0">
                    <a class="avatar avatar-lg status-danger" href="#">
                        <img src="../../images/avatar/2.jpg" alt="...">
                    </a>
                    <div class="media-body">
                        <p class="fs-16">
                            <a class="hover-primary" href="#"><strong>Luke</strong></a>
                        </p>
                        <p>Cras tempor diam ...</p>
                        <span>33 min ago</span>
                    </div>
                </div>

                <div class="media py-10 px-0">
                    <a class="avatar avatar-lg status-warning" href="#">
                        <img src="../../images/avatar/3.jpg" alt="...">
                    </a>
                    <div class="media-body">
                        <p class="fs-16">
                            <a class="hover-primary" href="#"><strong>Evan</strong></a>
                        </p>
                        <p>In posuere tortor vel...</p>
                        <span>42 min ago</span>
                    </div>
                </div>

                <div class="media py-10 px-0">
                    <a class="avatar avatar-lg status-primary" href="#">
                        <img src="../../images/avatar/4.jpg" alt="...">
                    </a>
                    <div class="media-body">
                        <p class="fs-16">
                            <a class="hover-primary" href="#"><strong>Evan</strong></a>
                        </p>
                        <p>In posuere tortor vel...</p>
                        <span>42 min ago</span>
                    </div>
                </div>

                <div class="media py-10 px-0">
                    <a class="avatar avatar-lg status-success" href="#">
                        <img src="../../images/avatar/1.jpg" alt="...">
                    </a>
                    <div class="media-body">
                        <p class="fs-16">
                            <a class="hover-primary" href="#"><strong>Tyler</strong></a>
                        </p>
                        <p>Praesent tristique diam...</p>
                        <span>Just now</span>
                    </div>
                </div>

                <div class="media py-10 px-0">
                    <a class="avatar avatar-lg status-danger" href="#">
                        <img src="../../images/avatar/2.jpg" alt="...">
                    </a>
                    <div class="media-body">
                        <p class="fs-16">
                            <a class="hover-primary" href="#"><strong>Luke</strong></a>
                        </p>
                        <p>Cras tempor diam ...</p>
                        <span>33 min ago</span>
                    </div>
                </div>

                <div class="media py-10 px-0">
                    <a class="avatar avatar-lg status-warning" href="#">
                        <img src="../../images/avatar/3.jpg" alt="...">
                    </a>
                    <div class="media-body">
                        <p class="fs-16">
                            <a class="hover-primary" href="#"><strong>Evan</strong></a>
                        </p>
                        <p>In posuere tortor vel...</p>
                        <span>42 min ago</span>
                    </div>
                </div>

                <div class="media py-10 px-0">
                    <a class="avatar avatar-lg status-primary" href="#">
                        <img src="../../images/avatar/4.jpg" alt="...">
                    </a>
                    <div class="media-body">
                        <p class="fs-16">
                            <a class="hover-primary" href="#"><strong>Evan</strong></a>
                        </p>
                        <p>In posuere tortor vel...</p>
                        <span>42 min ago</span>
                    </div>
                </div>

            </div>

        </div>
        <!-- /.tab-pane -->
        <!-- Settings tab content -->
        <div class="tab-pane" id="control-sidebar-settings-tab">
            <div class="flexbox">
                <a href="javascript:void(0)" class="text-grey">
                    <i class="ti-more"></i>
                </a>
                <p>Todo List</p>
                <a href="javascript:void(0)" class="text-end text-grey"><i class="ti-plus"></i></a>
            </div>
            <ul class="todo-list mt-20">
                <li class="py-15 px-5 by-1">
                    <!-- checkbox -->
                    <input type="checkbox" id="basic_checkbox_1" class="filled-in">
                    <label for="basic_checkbox_1" class="mb-0 h-15"></label>
                    <!-- todo text -->
                    <span class="text-line">Nulla vitae purus</span>
                    <!-- Emphasis label -->
                    <small class="badge bg-danger"><i class="fa fa-clock-o"></i> 2 mins</small>
                    <!-- General tools such as edit or delete-->
                    <div class="tools">
                        <i class="fa fa-edit"></i>
                        <i class="fa fa-trash-o"></i>
                    </div>
                </li>
                <li class="py-15 px-5">
                    <!-- checkbox -->
                    <input type="checkbox" id="basic_checkbox_2" class="filled-in">
                    <label for="basic_checkbox_2" class="mb-0 h-15"></label>
                    <span class="text-line">Phasellus interdum</span>
                    <small class="badge bg-info"><i class="fa fa-clock-o"></i> 4 hours</small>
                    <div class="tools">
                        <i class="fa fa-edit"></i>
                        <i class="fa fa-trash-o"></i>
                    </div>
                </li>
                <li class="py-15 px-5 by-1">
                    <!-- checkbox -->
                    <input type="checkbox" id="basic_checkbox_3" class="filled-in">
                    <label for="basic_checkbox_3" class="mb-0 h-15"></label>
                    <span class="text-line">Quisque sodales</span>
                    <small class="badge bg-warning"><i class="fa fa-clock-o"></i> 1 day</small>
                    <div class="tools">
                        <i class="fa fa-edit"></i>
                        <i class="fa fa-trash-o"></i>
                    </div>
                </li>
                <li class="py-15 px-5">
                    <!-- checkbox -->
                    <input type="checkbox" id="basic_checkbox_4" class="filled-in">
                    <label for="basic_checkbox_4" class="mb-0 h-15"></label>
                    <span class="text-line">Proin nec mi porta</span>
                    <small class="badge bg-success"><i class="fa fa-clock-o"></i> 3 days</small>
                    <div class="tools">
                        <i class="fa fa-edit"></i>
                        <i class="fa fa-trash-o"></i>
                    </div>
                </li>
                <li class="py-15 px-5 by-1">
                    <!-- checkbox -->
                    <input type="checkbox" id="basic_checkbox_5" class="filled-in">
                    <label for="basic_checkbox_5" class="mb-0 h-15"></label>
                    <span class="text-line">Maecenas scelerisque</span>
                    <small class="badge bg-primary"><i class="fa fa-clock-o"></i> 1 week</small>
                    <div class="tools">
                        <i class="fa fa-edit"></i>
                        <i class="fa fa-trash-o"></i>
                    </div>
                </li>
                <li class="py-15 px-5">
                    <!-- checkbox -->
                    <input type="checkbox" id="basic_checkbox_6" class="filled-in">
                    <label for="basic_checkbox_6" class="mb-0 h-15"></label>
                    <span class="text-line">Vivamus nec orci</span>
                    <small class="badge bg-info"><i class="fa fa-clock-o"></i> 1 month</small>
                    <div class="tools">
                        <i class="fa fa-edit"></i>
                        <i class="fa fa-trash-o"></i>
                    </div>
                </li>
                <li class="py-15 px-5 by-1">
                    <!-- checkbox -->
                    <input type="checkbox" id="basic_checkbox_7" class="filled-in">
                    <label for="basic_checkbox_7" class="mb-0 h-15"></label>
                    <!-- todo text -->
                    <span class="text-line">Nulla vitae purus</span>
                    <!-- Emphasis label -->
                    <small class="badge bg-danger"><i class="fa fa-clock-o"></i> 2 mins</small>
                    <!-- General tools such as edit or delete-->
                    <div class="tools">
                        <i class="fa fa-edit"></i>
                        <i class="fa fa-trash-o"></i>
                    </div>
                </li>
                <li class="py-15 px-5">
                    <!-- checkbox -->
                    <input type="checkbox" id="basic_checkbox_8" class="filled-in">
                    <label for="basic_checkbox_8" class="mb-0 h-15"></label>
                    <span class="text-line">Phasellus interdum</span>
                    <small class="badge bg-info"><i class="fa fa-clock-o"></i> 4 hours</small>
                    <div class="tools">
                        <i class="fa fa-edit"></i>
                        <i class="fa fa-trash-o"></i>
                    </div>
                </li>
                <li class="py-15 px-5 by-1">
                    <!-- checkbox -->
                    <input type="checkbox" id="basic_checkbox_9" class="filled-in">
                    <label for="basic_checkbox_9" class="mb-0 h-15"></label>
                    <span class="text-line">Quisque sodales</span>
                    <small class="badge bg-warning"><i class="fa fa-clock-o"></i> 1 day</small>
                    <div class="tools">
                        <i class="fa fa-edit"></i>
                        <i class="fa fa-trash-o"></i>
                    </div>
                </li>
                <li class="py-15 px-5">
                    <!-- checkbox -->
                    <input type="checkbox" id="basic_checkbox_10" class="filled-in">
                    <label for="basic_checkbox_10" class="mb-0 h-15"></label>
                    <span class="text-line">Proin nec mi porta</span>
                    <small class="badge bg-success"><i class="fa fa-clock-o"></i> 3 days</small>
                    <div class="tools">
                        <i class="fa fa-edit"></i>
                        <i class="fa fa-trash-o"></i>
                    </div>
                </li>
            </ul>
        </div>
        <!-- /.tab-pane -->
    </div>
</aside>
<!-- /.control-sidebar -->

<!-- Add the sidebar's background. This div must be placed immediately after the control sidebar -->
<div class="control-sidebar-bg"></div>
</div>
<!-- ./wrapper -->

<!-- Sidebar -->

<div id="chat-box-body">
    <div id="chat-circle" class="waves-effect waves-circle btn btn-circle btn-sm btn-warning l-h-50">
        <div id="chat-overlay"></div>
        <span class="icon-Group-chat fs-18"><span class="path1"></span><span class="path2"></span></span>
    </div>

    <div class="chat-box">
        <div class="chat-box-header p-15 d-flex justify-content-between align-items-center">
            <div class="btn-group">
                <button class="waves-effect waves-circle btn btn-circle btn-primary-light h-40 w-40 rounded-circle l-h-45"
                        type="button" data-bs-toggle="dropdown">
                    <span class="icon-Add-user fs-22"><span class="path1"></span><span class="path2"></span></span>
                </button>
                <div class="dropdown-menu min-w-200">
                    <a class="dropdown-item fs-16" href="#">
                        <span class="icon-Color me-15"></span>
                        New Group</a>
                    <a class="dropdown-item fs-16" href="#">
                        <span class="icon-Clipboard me-15"><span class="path1"></span><span class="path2"></span><span
                                    class="path3"></span><span class="path4"></span></span>
                        Contacts</a>
                    <a class="dropdown-item fs-16" href="#">
                        <span class="icon-Group me-15"><span class="path1"></span><span class="path2"></span></span>
                        Groups</a>
                    <a class="dropdown-item fs-16" href="#">
                        <span class="icon-Active-call me-15"><span class="path1"></span><span
                                    class="path2"></span></span>
                        Calls</a>
                    <a class="dropdown-item fs-16" href="#">
                        <span class="icon-Settings1 me-15"><span class="path1"></span><span class="path2"></span></span>
                        Settings</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item fs-16" href="#">
                        <span class="icon-Question-circle me-15"><span class="path1"></span><span class="path2"></span></span>
                        Help</a>
                    <a class="dropdown-item fs-16" href="#">
                        <span class="icon-Notifications me-15"><span class="path1"></span><span
                                    class="path2"></span></span>
                        Privacy</a>
                </div>
            </div>
            <div class="text-center flex-grow-1">
                <div class="text-dark fs-18">Mayra Sibley</div>
                <div>
                    <span class="badge badge-sm badge-dot badge-primary"></span>
                    <span class="text-muted fs-12">Active</span>
                </div>
            </div>
            <div class="chat-box-toggle">
                <button id="chat-box-toggle"
                        class="waves-effect waves-circle btn btn-circle btn-danger-light h-40 w-40 rounded-circle l-h-45"
                        type="button">
                    <span class="icon-Close fs-22"><span class="path1"></span><span class="path2"></span></span>
                </button>
            </div>
        </div>
        <div class="chat-box-body">
            <div class="chat-box-overlay">
            </div>
            <div class="chat-logs">
                <div class="chat-msg user">
                    <div class="d-flex align-items-center">
                            <span class="msg-avatar">
                                <img src="../../images/avatar/2.jpg" class="avatar avatar-lg">
                            </span>
                        <div class="mx-10">
                            <a href="#" class="text-dark hover-primary fw-bold">Mayra Sibley</a>
                            <p class="text-muted fs-12 mb-0">2 Hours</p>
                        </div>
                    </div>
                    <div class="cm-msg-text">
                        Hi there, I'm Jesse and you?
                    </div>
                </div>
                <div class="chat-msg self">
                    <div class="d-flex align-items-center justify-content-end">
                        <div class="mx-10">
                            <a href="#" class="text-dark hover-primary fw-bold">You</a>
                            <p class="text-muted fs-12 mb-0">3 minutes</p>
                        </div>
                        <span class="msg-avatar">
                                <img src="../../images/avatar/3.jpg" class="avatar avatar-lg">
                            </span>
                    </div>
                    <div class="cm-msg-text">
                        My name is Anne Clarc.
                    </div>
                </div>
                <div class="chat-msg user">
                    <div class="d-flex align-items-center">
                            <span class="msg-avatar">
                                <img src="../../images/avatar/2.jpg" class="avatar avatar-lg">
                            </span>
                        <div class="mx-10">
                            <a href="#" class="text-dark hover-primary fw-bold">Mayra Sibley</a>
                            <p class="text-muted fs-12 mb-0">40 seconds</p>
                        </div>
                    </div>
                    <div class="cm-msg-text">
                        Nice to meet you Anne.<br>How can i help you?
                    </div>
                </div>
            </div><!--chat-log -->
        </div>
        <div class="chat-input">
            <form>
                <input type="text" id="chat-input" placeholder="Send a message..."/>
                <button type="submit" class="chat-submit" id="chat-submit">
                    <span class="icon-Send fs-22"></span>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Page Content overlay -->
<script>
    function togglePrintStatus(form_id, hc_number, button, currentStatus) {
        // Verificar si el botón está activo
        var isActive = button.classList.contains('active');
        var newStatus = isActive ? 1 : 0;  // Si el botón está activo (on), el nuevo estado será 0 (off); si no, será 1 (on)

        // Cambiar visualmente el estado del botón
        if (isActive) {
            button.classList.add('active');
            button.setAttribute('aria-pressed', 'true');
        } else {
            button.classList.remove('active');
            button.setAttribute('aria-pressed', 'false');
        }

        // Realizar la petición AJAX para actualizar el estado en la base de datos
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'update_print_status.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('form_id=' + form_id + '&hc_number=' + hc_number + '&printed=' + newStatus);

        xhr.onload = function () {
            if (xhr.status === 200 && xhr.responseText === 'success') {
                console.log('Estado actualizado en la base de datos');

                // Solo si el nuevo estado es "on" (printed = 1), generamos el PDF
                if (newStatus === 1) {
                    window.open('../../generate_pdf.php?form_id=' + form_id + '&hc_number=' + hc_number, '_blank');
                }
            } else {
                console.log('Error al actualizar el estado');
            }
        };
    }

    let currentFormId;  // Variable para almacenar el form_id actual
    let currentHcNumber;  // Variable para almacenar el hc_number actual

    function redirectToEditProtocol() {
        // Construir la URL de edición
        const url = `../edit_protocol.php?form_id=${encodeURIComponent(currentFormId)}&hc_number=${encodeURIComponent(currentHcNumber)}`;

        // Redirigir al usuario
        window.location.href = url;
    }

    function updateProtocolStatus() {
        // Obtener si el checkbox está marcado
        const isReviewed = document.getElementById('markAsReviewed').checked ? 1 : 0;

        // Realizar la petición AJAX para actualizar el campo "status" en la base de datos
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'update_protocol_status.php', true);  // Archivo PHP para manejar la actualización
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                console.log(xhr.responseText);  // Ver la respuesta del servidor

                // Verificamos si la respuesta contiene 'success'
                if (xhr.status === 200 && xhr.responseText.trim().includes('success')) {
                    console.log('Estado del protocolo actualizado correctamente');

                    // Cerrar el modal sin usar jQuery
                    const modalElement = document.getElementById('resultModal');
                    const modalInstance = bootstrap.Modal.getInstance(modalElement);
                    modalInstance.hide();

                    // Recargar la tabla general después de cerrar el modal
                    reloadPatientTable();
                } else {
                    console.log('Error al actualizar el estado del protocolo');
                }
            }
        };
        // Enviar el form_id, hc_number y el nuevo estado (revisado o no)
        xhr.send(`form_id=${encodeURIComponent(currentFormId)}&hc_number=${encodeURIComponent(currentHcNumber)}&status=${isReviewed}`);
    }

    function reloadPatientTable() {
        // Hacer una petición AJAX al mismo archivo
        const xhr = new XMLHttpRequest();
        xhr.open('GET', window.location.href, true);  // Hacer una petición GET al mismo archivo PHP
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');  // Esto ayuda a diferenciar solicitudes AJAX

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                // Actualizar el contenido del tbody con el nuevo HTML de las filas
                const parser = new DOMParser();
                const htmlDoc = parser.parseFromString(xhr.responseText, 'text/html');
                const newTableBody = htmlDoc.getElementById('patientTableBody').innerHTML;
                document.getElementById('patientTableBody').innerHTML = newTableBody;
            }
        };
        xhr.send();
    }
</script>


<!-- Vendor JS -->
<script src="js/qx_repoorts.js"></script>
<script src="../js/vendors.min.js"></script>
<script src="../js/pages/chat-popup.js"></script>
<script src="../../assets/icons/feather-icons/feather.min.js"></script>
<script src="../../assets/vendor_components/datatable/datatables.min.js"></script>

<script>
    $.fn.dataTable.ext.type.order['dd-mm-yyyy-pre'] = function (d) {
        if (!d) {
            return 0;
        }
        var parts = d.split('/');
        return new Date(parts[2], parts[1] - 1, parts[0]).getTime();
    };

    $(document).ready(function () {
        $('#surgeryTable').DataTable({
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "pageLength": 25,
            "columnDefs": [
                {
                    "targets": 4,  // Índice de la columna "Fecha de Inicio"
                    "type": "dd-mm-yyyy"  // Tipo personalizado para ordenar fechas dd/mm/yyyy
                }
            ]
        });
    });
</script>


<!-- Doclinic App -->
<script src="../js/jquery.smartmenus.js"></script>
<script src="../js/menus.js"></script>
<script src="../js/template.js"></script>
<script src="../js/pages/appointments.js"></script>


</body>
</html>
