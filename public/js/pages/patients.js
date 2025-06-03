$(function () {
    'use strict';

    // Inicializar DataTable con configuración serverSide
    const table = $('#example1').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: '/views/pacientes/pacientes_datatable.php', // Ajusta esta ruta a la correcta en tu backend
            type: 'POST'
        },
        columns: [
            {data: 'hc_number'},
            {data: 'ultima_fecha'},
            {data: 'full_name'},
            {data: 'afiliacion'},
            {data: 'estado_html'},
            {data: 'acciones_html'}
        ],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, 250, 500],
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
        },
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });

    // Tooltip para los botones o celdas
    $('[data-toggle="tooltip"]').tooltip();
});