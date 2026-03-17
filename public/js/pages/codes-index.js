(function () {
    if (typeof window.jQuery === 'undefined') {
        return;
    }

    const $table = $('#codesTable');
    if (!$table.length || typeof $table.DataTable !== 'function') {
        return;
    }

    const datatableUrl = $table.data('datatable-url') || '/codes/datatable';
    const indexUrl = $table.data('index-url') || '/codes';
    const filterForm = document.getElementById('codes-filter-form');
    const refreshButton = document.getElementById('codes-refresh-btn');

    function getFormValue(name) {
        if (!filterForm) {
            return '';
        }

        const formData = new FormData(filterForm);
        return formData.get(name) || '';
    }

    const dataTable = $table.DataTable({
        language: window.medforgeDataTableLanguageEs ? window.medforgeDataTableLanguageEs() : {},
        processing: true,
        serverSide: true,
        responsive: true,
        autoWidth: false,
        deferRender: true,
        searching: false,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        ajax: {
            url: datatableUrl,
            type: 'GET',
            data: function (d) {
                d.q = getFormValue('q');
                d.code_type = getFormValue('code_type');
                d.superbill = getFormValue('superbill');
                d.active = filterForm && filterForm.querySelector('#f_active')?.checked ? 1 : 0;
                d.reportable = filterForm && filterForm.querySelector('#f_reportable')?.checked ? 1 : 0;
                d.financial_reporting = filterForm && filterForm.querySelector('#f_finrep')?.checked ? 1 : 0;
            },
        },
        columns: [
            { data: 'codigo' },
            { data: 'modifier' },
            { data: 'active_text' },
            { data: 'category' },
            { data: 'reportable_text' },
            { data: 'finrep_text' },
            { data: 'code_type' },
            { data: 'descripcion' },
            { data: 'short_description' },
            { data: 'related' },
            { data: 'valor1', className: 'text-end' },
            { data: 'valor2', className: 'text-end' },
            { data: 'valor3', className: 'text-end' },
            { data: 'acciones', orderable: false, searchable: false },
        ],
        columnDefs: [
            { targets: '_all', defaultContent: '—' },
        ],
        order: [[0, 'asc']],
    });

    if (filterForm) {
        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const params = new URLSearchParams(new FormData(filterForm));
            const url = params.toString() ? `${indexUrl}?${params.toString()}` : indexUrl;
            window.history.replaceState({}, document.title, url);
            dataTable.ajax.reload();
        });
    }

    if (refreshButton) {
        refreshButton.addEventListener('click', function () {
            dataTable.ajax.reload();
        });
    }
})();
