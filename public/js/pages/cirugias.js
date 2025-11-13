$(function () {
  "use strict";

  const table = $("#surgeryTable").DataTable({
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100, 250, 500],
    responsive: true,
    order: [[4, "desc"]], // Ordena por la columna 4
    processing: true,
    colReorder: true,
    searching: true,
    ordering: true,
    responsive: true,

    columnDefs: [
      {
        targets: 4, // columna 4: FECHA (tu tabla la cuenta desde 1)
        render: function (data, type) {
          if (!data) return type === "sort" || type === "type" ? 0 : "";

          // Detecta y normaliza formatos: dd/mm/yyyy o yyyy-mm-dd, o Date string
          let d, m, y;

          if (/^\d{2}\/\d{2}\/\d{4}$/.test(data)) {
            const [dd, mm, yyyy] = data.split("/");
            d = parseInt(dd, 10);
            m = parseInt(mm, 10);
            y = parseInt(yyyy, 10);
          } else if (/^\d{4}-\d{2}-\d{2}$/.test(data)) {
            const [yyyy, mm, dd] = data.split("-");
            d = parseInt(dd, 10);
            m = parseInt(mm, 10);
            y = parseInt(yyyy, 10);
          } else {
            const dt = new Date(data);
            if (!isNaN(dt)) {
              d = dt.getDate();
              m = dt.getMonth() + 1;
              y = dt.getFullYear();
            } else {
              // Si no se puede parsear, conserva el texto
              return type === "sort" || type === "type" ? 0 : data;
            }
          }

          if (type === "sort" || type === "type") {
            // Timestamp numérico para orden correcto
            return new Date(y, m - 1, d).getTime();
          }

          // Mostrar siempre como dd/mm/yyyy
          const dd = String(d).padStart(2, "0");
          const mm = String(m).padStart(2, "0");
          return `${dd}/${mm}/${y}`;
        },
      },
      {
        targets: [6, 7], // columnas de botones EDITAR e IMPRIMIR
        orderable: false,
        searchable: false,
      },
      {
        targets: [0, 1], // columnas 1 y 2 (números)
        type: "num",
      },
    ],
    language: {
      url: "https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json",
    },
    dom: "Bfrtip",
    buttons: ["copy", "csv", "excel", "pdf", "print"],
  });

  $('[data-toggle="tooltip"]').tooltip();
});
