$(function () {
    "use strict";

    const $filterForm = $("#filtrosCirugias");
    const $fechaInicio = $("#filtroFechaInicio");
    const $fechaFin = $("#filtroFechaFin");
    const $afiliacion = $("#filtroAfiliacion");
    const $afiliacionCategoria = $("#filtroAfiliacionCategoria");
    const $surgeryTable = $("#surgeryTable");
    const defaultFechaInicio = ($fechaInicio.data("default") || "").toString();
    const defaultFechaFin = ($fechaFin.data("default") || "").toString();

    const clearInlineTableWidth = function () {
        const tableNode = $surgeryTable.get(0);
        if (!tableNode) {
            return;
        }
        tableNode.style.width = "";
    };

    const table = $surgeryTable.DataTable({
        serverSide: true,
        processing: true,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, 250, 500],
        responsive: true,
        autoWidth: false,
        order: [[4, "desc"]],
        ajax: {
            url: "/cirugias/datatable",
            type: "POST",
            data: function (d) {
                d.fecha_inicio = $fechaInicio.val();
                d.fecha_fin = $fechaFin.val();
                d.afiliacion = $afiliacion.val();
                d.afiliacion_categoria = $afiliacionCategoria.val();
            },
            error: function (xhr) {
                const backendMessage = xhr && xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : "No se pudo cargar la tabla de cirugías.";
                if (window.Swal && typeof window.Swal.fire === "function") {
                    window.Swal.fire("Error", backendMessage, "error");
                }
                console.error("Cirugias datatable ajax error", {
                    status: xhr ? xhr.status : null,
                    responseText: xhr ? xhr.responseText : null
                });
            }
        },
        columns: [
            {data: "form_id"},
            {data: "hc_number"},
            {data: "full_name"},
            {data: "afiliacion_html"},
            {data: "fecha_inicio"},
            {data: "membrete"},
            {data: "protocolo_html"},
            {data: "descanso_html"},
            {data: "imprimir_html"}
        ],
        columnDefs: [
            {targets: [6, 7, 8], orderable: false, searchable: false},
            {targets: [3, 6, 7, 8], className: "text-nowrap"}
        ],
        language: {
            url: "https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json"
        },
        dom: "Bfrtip",
        buttons: ["copy", "csv", "excel", "pdf", "print"],
        initComplete: clearInlineTableWidth
    });

    table.on("draw.dt", clearInlineTableWidth);

    if ($filterForm.length) {
        $filterForm.on("submit", function (event) {
            event.preventDefault();
            table.ajax.reload();
        });
    }

    $("#btnLimpiarFiltrosCirugias").on("click", function () {
        if ($filterForm.length && $filterForm[0]) {
            $filterForm[0].reset();
        }
        if (defaultFechaInicio !== "") {
            $fechaInicio.val(defaultFechaInicio);
        }
        if (defaultFechaFin !== "") {
            $fechaFin.val(defaultFechaFin);
        }
        table.ajax.reload();
    });
});
