$(function () {
    'use strict';

    const table = $('#derivaciones-table').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: '/derivaciones/datatable',
            type: 'POST'
        },
        columns: [
            {data: 'fecha_creacion'},
            {data: 'cod_derivacion'},
            {data: 'form_id'},
            {data: 'hc_number'},
            {data: 'paciente_nombre'},
            {data: 'referido'},
            {data: 'fecha_registro'},
            {data: 'fecha_vigencia'},
            {
                data: 'archivo_html',
                orderable: false,
                searchable: false
            },
            {data: 'diagnostico'},
            {data: 'sede'},
            {data: 'parentesco'},
            {
                data: 'acciones_html',
                orderable: false,
                searchable: false
            }
        ],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, 250, 500],
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
        },
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        createdRow: function (row, data) {
            // Render HTML en columna de archivo
            $('td:eq(8)', row).html(data.archivo_html);
            $('td:eq(12)', row).html(data.acciones_html);
        }
    });

    $('[data-toggle="tooltip"]').tooltip();

    $(document).on('click', '.js-scrap-derivacion', async function (e) {
        e.preventDefault();
        const btn = $(this);
        const formId = btn.data('form-id');
        const hc = btn.data('hc');
        if (!formId || !hc) {
            alert('Faltan datos de form_id o hc_number.');
            return;
        }
        btn.prop('disabled', true).text('Actualizando...');
        try {
            const resp = await fetch('/derivaciones/scrap', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({form_id: formId, hc_number: hc}).toString()
            });
            const data = await resp.json();
            if (data.success) {
                alert('Scrapping completado. Se recargará la tabla.');
                table.ajax.reload(null, false);
            } else {
                alert('Error: ' + (data.message || 'No se pudo scrapear'));
            }
        } catch (err) {
            console.error(err);
            alert('Error al ejecutar el scrapper.');
        } finally {
            btn.prop('disabled', false).text('Actualizar');
        }
    });
});
