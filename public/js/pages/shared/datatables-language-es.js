(function (window) {
    "use strict";

    const baseLanguage = {
        decimal: "",
        emptyTable: "No hay datos disponibles en la tabla",
        info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty: "Mostrando 0 a 0 de 0 registros",
        infoFiltered: "(filtrado de _MAX_ registros totales)",
        infoPostFix: "",
        thousands: ",",
        lengthMenu: "Mostrar _MENU_ registros",
        loadingRecords: "Cargando...",
        processing: "Procesando...",
        search: "Buscar:",
        zeroRecords: "No se encontraron resultados",
        paginate: {
            first: "Primero",
            last: "Último",
            next: "Siguiente",
            previous: "Anterior"
        },
        aria: {
            sortAscending: ": activar para ordenar la columna de manera ascendente",
            sortDescending: ": activar para ordenar la columna de manera descendente"
        },
        buttons: {
            copy: "Copiar",
            csv: "CSV",
            excel: "Excel",
            pdf: "PDF",
            print: "Imprimir"
        },
        select: {
            rows: {
                _: "%d filas seleccionadas",
                0: "Haz clic en una fila para seleccionarla",
                1: "1 fila seleccionada"
            }
        }
    };

    window.medforgeDataTableLanguageEs = function (overrides) {
        return window.jQuery && window.jQuery.extend
            ? window.jQuery.extend(true, {}, baseLanguage, overrides || {})
            : Object.assign({}, baseLanguage, overrides || {});
    };
})(window);
