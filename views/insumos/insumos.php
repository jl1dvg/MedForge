<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\DashboardController;
use Controllers\InsumosController;

$dashboardController = new DashboardController($pdo);
$username = $dashboardController->getAuthenticatedUser();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['json'])) {
    header('Content-Type: application/json');
    $controller = new InsumosController($pdo);
    $insumos = $controller->listarTodos();
    echo json_encode(['success' => true, 'insumos' => $insumos]);
    exit;
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
        table#insumosEditable td,
        table#insumosEditable th {
            font-size: 0.85rem;
            padding: 0.4rem 0.5rem;
        }

        table#insumosEditable th {
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
    </style>

    <script>
        const style = document.createElement("style");
        style.textContent = `
    td.editable {
      background-color: #fdfdfd;
      border: 1px dashed #ddd;
      cursor: text;
    }
    td.editable:focus {
      background-color: #e9f7ef;
      outline: none;
    }
  `;
        document.head.appendChild(style);
    </script>

</head>
<body class="layout-top-nav light-skin theme-primary fixed">

<div class="wrapper">
    <div id="loader"></div>

    <?php include __DIR__ . '/../components/header.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <div class="container-full">
            <!-- Content Header (Page header) -->
            <div class="content-header">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <h3 class="page-title">Editable Tables</h3>
                        <div class="d-inline-block align-items-center">
                            <nav>
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a>
                                    </li>
                                    <li class="breadcrumb-item" aria-current="page">Tables</li>
                                    <li class="breadcrumb-item active" aria-current="page">Editable Tables</li>
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
                            <div class="box-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="box-title">📋 <strong>Listado editable de insumos</strong></h4>
                                    <h6 class="subtitle">Haz clic sobre cualquier celda para modificar su contenido. Usa
                                        los botones para guardar o eliminar.</h6>
                                </div>
                                <button id="agregarInsumoBtn" class="waves-effect waves-light btn btn-primary mb-5">
                                    <i class="mdi mdi-plus-circle-outline"></i> Nuevo Insumo
                                </button>
                            </div>

                            <div class="box-body">
                                <div class="table-responsive">
                                    <table id="insumosEditable"
                                           class="table table-bordered table-striped table-hover table-sm align-middle">
                                        <thead class="table-primary text-dark fw-semibold">
                                        <tr>
                                            <th>Categoría</th>
                                            <th>Código ISSPOL</th>
                                            <th>Código ISSFA</th>
                                            <th>Código IESS</th>
                                            <th>Código MSP</th>
                                            <th>Nombre</th>
                                            <th>Producto ISSFA</th>
                                            <th>Es medicamento</th>
                                            <th>Precio Base</th>
                                            <th>IVA 15%</th>
                                            <th>Gestión 10%</th>
                                            <th>Precio Total</th>
                                            <th>Precio ISSPOL</th>
                                            <th>Acciones</th>
                                        </tr>
                                        </thead>
                                        <tbody id="tablaInsumosBody">
                                        <!-- Las filas serán insertadas dinámicamente por JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row">

                </div>

            </section>
            <!-- /.content -->
        </div>
    </div>
    <!-- /.content-wrapper -->

    <?php include __DIR__ . '/../components/footer.php'; ?>

</div>
<!-- ./wrapper -->

<!-- Vendor JS -->
<script src="/public/js/vendors.min.js"></script>
<script src="/public/js/pages/chat-popup.js"></script>
<script src="/public/assets/icons/feather-icons/feather.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/public/assets/vendor_components/datatable/datatables.min.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/mindmup-editabletable.js"></script>
<script src="/public/assets/vendor_components/tiny-editable/numeric-input-example.js"></script>


<!-- Doclinic App -->
<script src="/public/js/jquery.smartmenus.js"></script>
<script src="/public/js/menus.js"></script>
<script src="/public/js/template.js"></script>

]
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const table = document.getElementById("insumosEditable");
        const tbody = document.getElementById("tablaInsumosBody");
        const agregarBtn = document.getElementById("agregarInsumoBtn");

        if (!table || !agregarBtn) return;

        fetch("/views/insumos/insumos.php?json=1")
            .then(res => res.json())
            .then(json => {
                if (json.success && Array.isArray(json.insumos)) {
                    if (json.insumos.length === 0) {
                        const fila = tbody.insertRow();
                        const td = fila.insertCell();
                        td.colSpan = 13;
                        td.className = "text-center text-muted";
                        td.textContent = "No hay insumos disponibles.";
                    } else {
                        json.insumos.forEach(insumo => agregarFilaExistente(insumo));
                    }
                }
            });

        function agregarFilaExistente(data) {
            const row = tbody.insertRow(-1);
            row.setAttribute("data-id", data.id);

            const campos = [
                "categoria", "codigo_isspol", "codigo_issfa", "codigo_iess", "codigo_msp",
                "nombre", "producto_issfa", "es_medicamento", "precio_base", "iva_15", "gestion_10", "precio_total", "precio_isspol"
            ];

            campos.forEach(campo => {
                const td = row.insertCell();
                td.setAttribute("contenteditable", "true");
                td.classList.add("editable");
                td.dataset.field = campo;
                td.textContent = data[campo] ?? "";
            });

            const accionTd = row.insertCell();
            const guardarBtn = document.createElement("button");
            guardarBtn.className = "btn btn-sm btn-success save-btn";
            guardarBtn.innerHTML = '<i class="fa fa-save"></i>';

            const eliminarBtn = document.createElement("button");
            eliminarBtn.className = "btn btn-sm btn-danger delete-btn";
            eliminarBtn.innerHTML = '<i class="fa fa-trash"></i>';

            const btnContainer = document.createElement("div");
            btnContainer.className = "d-flex gap-1";
            btnContainer.appendChild(guardarBtn);
            btnContainer.appendChild(eliminarBtn);
            accionTd.appendChild(btnContainer);
        }

        // Guardar celda editada y eliminar fila
        table.addEventListener("click", function (e) {
            if (e.target.classList.contains("save-btn") || e.target.closest(".save-btn")) {
                const btn = e.target.closest(".save-btn");
                const row = btn.closest("tr");
                guardarFila(row);
            }
            if (e.target.classList.contains("delete-btn") || e.target.closest(".delete-btn")) {
                const btn = e.target.closest(".delete-btn");
                const row = btn.closest("tr");
                Swal.fire({
                    title: "¿Eliminar insumo?",
                    text: "Esta acción no se puede deshacer.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#d33",
                    cancelButtonColor: "#aaa",
                    confirmButtonText: "Sí, eliminar",
                    cancelButtonText: "Cancelar"
                }).then((result) => {
                    if (result.isConfirmed) {
                        row.remove();
                        // Opcional: podrías hacer un fetch aquí para eliminar en backend si se desea
                    }
                });
            }
        });

        // Agregar nueva fila
        agregarBtn.addEventListener("click", function () {
            const nuevaFila = tbody.insertRow(-1);
            nuevaFila.setAttribute("data-id", "nuevo");
            const campos = [
                "categoria", "codigo_isspol", "codigo_issfa", "codigo_iess", "codigo_msp",
                "nombre", "producto_issfa", "es_medicamento", "precio_base", "iva_15", "gestion_10", "precio_total", "precio_isspol"
            ];

            campos.forEach(campo => {
                const td = nuevaFila.insertCell();
                td.setAttribute("contenteditable", "true");
                td.classList.add("editable");
                td.dataset.field = campo;
                td.textContent = "";
            });

            const accionTd = nuevaFila.insertCell();
            const guardarBtn = document.createElement("button");
            guardarBtn.className = "btn btn-sm btn-success save-btn";
            guardarBtn.innerHTML = '<i class="mdi mdi-check"></i>';

            const eliminarBtn = document.createElement("button");
            eliminarBtn.className = "btn btn-sm btn-danger delete-btn";
            eliminarBtn.innerHTML = '<i class="mdi mdi-delete"></i>';

            const btnContainer = document.createElement("div");
            btnContainer.className = "d-flex gap-1";
            btnContainer.appendChild(guardarBtn);
            btnContainer.appendChild(eliminarBtn);
            accionTd.appendChild(btnContainer);

            if (nuevaFila.cells.length !== campos.length + 1) {
                console.warn("Número de columnas inesperado en nueva fila:", nuevaFila.cells.length);
            }
        });

        function guardarFila(row) {
            const id = row.getAttribute("data-id");
            const data = {id: id === "nuevo" ? null : id};

            let valido = true;

            row.querySelectorAll(".editable").forEach(cell => {
                const campo = cell.dataset.field;
                const valor = cell.textContent.trim();

                if (!valor && ["nombre", "categoria"].includes(campo)) {
                    cell.style.backgroundColor = "#fff3cd"; // Amarillo suave
                    valido = false;
                } else if (["precio_base", "iva_15", "gestion_10", "precio_total", "precio_isspol"].includes(campo)) {
                    if (valor && isNaN(parseFloat(valor))) {
                        cell.style.backgroundColor = "#f8d7da";
                        valido = false;
                    } else {
                        cell.style.backgroundColor = "";
                    }
                } else {
                    cell.style.backgroundColor = "";
                }

                data[campo] = valor;
            });

            if (!valido) {
                Swal.fire({
                    icon: "warning",
                    title: "Validación requerida",
                    html: "<b>Revisa los siguientes puntos:</b><ul style='text-align:left'><li>Los campos <strong>nombre</strong> y <strong>categoría</strong> no pueden estar vacíos.</li><li>Los campos numéricos deben tener valores válidos.</li></ul>",
                    confirmButtonText: "Entendido",
                    confirmButtonColor: "#3085d6"
                });
                return;
            }

            fetch("guardar_insumo.php", {
                method: "POST",
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify(data)
            })
                .then(async res => {
                    const text = await res.text();
                    try {
                        const json = JSON.parse(text);
                        if (json.success) {
                            Swal.fire("Guardado", json.message, "success");
                            if (json.id && id === "nuevo") {
                                row.setAttribute("data-id", json.id);
                            }
                        } else {
                            Swal.fire("Error", json.message, "error");
                        }
                    } catch (e) {
                        console.error("Error al parsear JSON:", text);
                        Swal.fire("Error", "Respuesta inesperada del servidor.", "error");
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire("Error", "No se pudo guardar el insumo.", "error");
                });
        }
    });
</script>


</body>
</html>