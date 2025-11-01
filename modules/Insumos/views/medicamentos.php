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
    $medicamentos = $controller->listarMedicamentos();
    echo json_encode(['success' => true, 'medicamentos' => $medicamentos]);
    exit;
}
?>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/public/images/favicon.ico">

    <title>MedForge – Medicamentos</title>

    <!-- Vendors Style-->
    <link rel="stylesheet" href="/public/css/vendors_css.css">

    <!-- Style-->
    <link rel="stylesheet" href="/public/css/horizontal-menu.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/skin_color.css">

    <style>
        table#MedicamentosEditable td,
        table#MedicamentosEditable th {
            font-size: 0.85rem;
            padding: 0.4rem 0.5rem;
        }

        table#MedicamentosEditable th {
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
    </style>

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
                        <h3 class="page-title">Medicamentos</h3>
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
                                    <h4 class="box-title">📋 <strong>Listado editable de Medicamentos</strong></h4>
                                    <h6 class="subtitle">Haz clic sobre cualquier celda para modificar su contenido. Usa
                                        los botones para guardar o eliminar.</h6>
                                </div>
                                <button id="agregarMedicamentoBtn"
                                        class="waves-effect waves-light btn btn-primary mb-5">
                                    <i class="mdi mdi-plus-circle-outline"></i> Nuevo Medicamento
                                </button>
                            </div>

                            <div class="box-body">
                                <div class="table-responsive">
                                    <table id="MedicamentosEditable"
                                           class="table table-bordered table-striped table-hover table-sm align-middle">
                                        <thead class="table-primary text-dark fw-semibold">
                                        <tr>
                                            <th>Medicamento</th>
                                            <th>Vía de anministración</th>
                                            <th>Acciones</th>
                                        </tr>
                                        </thead>
                                        <tbody id="tablaMedicamentosBody">
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

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const table = document.getElementById("MedicamentosEditable");
        const tbody = document.getElementById("tablaMedicamentosBody");
        const agregarBtn = document.getElementById("agregarMedicamentoBtn");

        if (!table || !agregarBtn) return;

        fetch("/views/insumos/medicamentos.php?json=1")
            .then(res => res.json())
            .then(json => {
                if (json.success && Array.isArray(json.medicamentos)) {
                    if (json.medicamentos.length === 0) {
                        const fila = tbody.insertRow();
                        const td = fila.insertCell();
                        td.colSpan = 13;
                        td.className = "text-center text-muted";
                        td.textContent = "No hay medicamentos disponibles.";
                    } else {
                        json.medicamentos.forEach(medicamento => agregarFilaExistente(medicamento));
                    }
                } else {
                    const fila = tbody.insertRow();
                    const td = fila.insertCell();
                    td.colSpan = 3;
                    td.className = "text-center text-muted";
                    td.textContent = json.message || "Error al cargar medicamentos.";
                }
            })
            .catch(() => {
                const fila = tbody.insertRow();
                const td = fila.insertCell();
                td.colSpan = 3;
                td.className = "text-center text-muted";
                td.textContent = "Error al cargar medicamentos.";
            });

        function agregarFilaExistente(data) {
            const row = tbody.insertRow(-1);
            row.setAttribute("data-id", data.id);

            const campos = ["medicamento", "via_administracion"];

            campos.forEach(campo => {
                const td = row.insertCell();
                td.dataset.field = campo;
                td.classList.add("editable");
                if (campo === "via_administracion") {
                    const select = document.createElement("select");
                    ["VIA TOPICA", "INTRAVITREA", "VIA INFILTRATIVA", "INTRAVENOSA", "SUBCOJUNTIVAL"].forEach(opcion => {
                        const option = document.createElement("option");
                        option.value = opcion;
                        option.textContent = opcion;
                        if (data[campo] === opcion) option.selected = true;
                        select.appendChild(option);
                    });
                    td.appendChild(select);
                } else {
                    td.setAttribute("contenteditable", "true");
                    td.setAttribute("role", "textbox");
                    td.setAttribute("aria-label", "Nombre del medicamento");
                    td.textContent = data[campo] ?? "";
                }
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
                    title: "¿Eliminar medicamento?",
                    text: "Esta acción no se puede deshacer.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#d33",
                    cancelButtonColor: "#aaa",
                    confirmButtonText: "Sí, eliminar",
                    cancelButtonText: "Cancelar"
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch("eliminar_medicamento.php", {
                            method: "POST",
                            headers: {"Content-Type": "application/json"},
                            body: JSON.stringify({id: row.getAttribute("data-id")})
                        })
                            .then(res => res.json())
                            .then(json => {
                                if (json.success) {
                                    row.remove();
                                    Swal.fire("Eliminado", json.message, "success");
                                } else {
                                    Swal.fire("Error", json.message, "error");
                                }
                            })
                            .catch(() => {
                                Swal.fire("Error", "No se pudo eliminar el medicamento.", "error");
                            });
                    }
                });
            }
        });

        // Agregar nueva fila
        agregarBtn.addEventListener("click", function () {
            const nuevaFila = tbody.insertRow(-1);
            nuevaFila.setAttribute("data-id", "nuevo");
            const campos = ["medicamento", "via_administracion"];

            campos.forEach(campo => {
                const td = nuevaFila.insertCell();
                td.dataset.field = campo;
                td.classList.add("editable");
                if (campo === "via_administracion") {
                    const select = document.createElement("select");
                    ["VIA TOPICA", "INTRAVITREA", "VIA INFILTRATIVA", "INTRAVENOSA", "SUBCOJUNTIVAL"].forEach(opcion => {
                        const option = document.createElement("option");
                        option.value = opcion;
                        option.textContent = opcion;
                        select.appendChild(option);
                    });
                    td.appendChild(select);
                } else {
                    td.setAttribute("contenteditable", "true");
                    td.setAttribute("role", "textbox");
                    td.setAttribute("aria-label", "Nombre del medicamento");
                    td.textContent = "";
                }
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
                let valor;
                if (cell.dataset.field === "via_administracion") {
                    const select = cell.querySelector("select");
                    valor = select ? select.value : "";
                } else {
                    valor = cell.textContent.trim();
                }

                if (!valor && ["medicamento", "via_administracion"].includes(campo)) {
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
                    html: "<b>Revisa los siguientes puntos:</b><ul style='text-align:left'><li>Los campos <strong>Medicamento</strong> y <strong>Vía de administración</strong> no pueden estar vacíos.</li><li>Los campos numéricos deben tener valores válidos.</li></ul>",
                    confirmButtonText: "Entendido",
                    confirmButtonColor: "#3085d6"
                });
                return;
            }

            fetch("guardar_medicamento.php", {
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
                    Swal.fire("Error", "No se pudo guardar el medicamento.", "error");
                });
        }
    });
</script>


</body>
</html>