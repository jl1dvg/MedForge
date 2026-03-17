$(function () {
    'use strict';

    // Inicializar DataTable con configuración serverSide
    const table = $('#pacientes-table').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: '/v2/pacientes/datatable',
            type: 'POST'
        },
        columns: [
            {data: 'hc_number'},
            {data: 'ultima_fecha'},
            {data: 'full_name'},
            {data: 'afiliacion'},
            {data: 'acciones_html'}
        ],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, 250, 500],
        responsive: true,
        language: window.medforgeDataTableLanguageEs ? window.medforgeDataTableLanguageEs() : {},
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });

    // Tooltip para los botones o celdas
    $('[data-toggle="tooltip"]').tooltip();
});
