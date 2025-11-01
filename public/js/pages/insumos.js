document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('insumosEditable');
    const tbody = document.getElementById('tablaInsumosBody');
    const agregarBtn = document.getElementById('agregarInsumoBtn');

    if (!table || !tbody || !agregarBtn) {
        return;
    }

    fetch('/insumos/list')
        .then((res) => res.json())
        .then((json) => {
            if (json.success && Array.isArray(json.insumos)) {
                if (!json.insumos.length) {
                    const row = tbody.insertRow();
                    const td = row.insertCell();
                    td.colSpan = 14;
                    td.className = 'text-center text-muted';
                    td.textContent = 'No hay insumos disponibles.';
                    return;
                }

                json.insumos.forEach((insumo) => agregarFilaExistente(insumo));
            } else {
                mostrarErrorTabla(json.message || 'No se pudieron cargar los insumos.');
            }
        })
        .catch(() => mostrarErrorTabla('No se pudieron cargar los insumos.'));

    function mostrarErrorTabla(mensaje) {
        const row = tbody.insertRow();
        const td = row.insertCell();
        td.colSpan = 14;
        td.className = 'text-center text-muted';
        td.textContent = mensaje;
    }

    function agregarFilaExistente(data) {
        const row = tbody.insertRow(-1);
        row.setAttribute('data-id', data.id);

        const campos = [
            'categoria',
            'codigo_isspol',
            'codigo_issfa',
            'codigo_iess',
            'codigo_msp',
            'nombre',
            'producto_issfa',
            'es_medicamento',
            'precio_base',
            'iva_15',
            'gestion_10',
            'precio_total',
            'precio_isspol',
        ];

        campos.forEach((campo) => {
            const td = row.insertCell();
            td.setAttribute('contenteditable', 'true');
            td.classList.add('editable');
            td.dataset.field = campo;
            td.textContent = data[campo] ?? '';
        });

        const accionTd = row.insertCell();
        const guardarBtn = document.createElement('button');
        guardarBtn.className = 'btn btn-sm btn-success save-btn';
        guardarBtn.innerHTML = '<i class="fa fa-save"></i>';

        const eliminarBtn = document.createElement('button');
        eliminarBtn.className = 'btn btn-sm btn-danger delete-btn';
        eliminarBtn.innerHTML = '<i class="fa fa-trash"></i>';

        const container = document.createElement('div');
        container.className = 'd-flex gap-1';
        container.appendChild(guardarBtn);
        container.appendChild(eliminarBtn);
        accionTd.appendChild(container);
    }

    table.addEventListener('click', (event) => {
        const saveBtn = event.target.closest('.save-btn');
        if (saveBtn) {
            const row = saveBtn.closest('tr');
            guardarFila(row);
            return;
        }

        const deleteBtn = event.target.closest('.delete-btn');
        if (deleteBtn) {
            const row = deleteBtn.closest('tr');
            Swal.fire({
                title: '¿Eliminar insumo? ',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    row.remove();
                }
            });
        }
    });

    agregarBtn.addEventListener('click', () => {
        const nuevaFila = tbody.insertRow(-1);
        nuevaFila.setAttribute('data-id', 'nuevo');

        const campos = [
            'categoria',
            'codigo_isspol',
            'codigo_issfa',
            'codigo_iess',
            'codigo_msp',
            'nombre',
            'producto_issfa',
            'es_medicamento',
            'precio_base',
            'iva_15',
            'gestion_10',
            'precio_total',
            'precio_isspol',
        ];

        campos.forEach((campo) => {
            const td = nuevaFila.insertCell();
            td.setAttribute('contenteditable', 'true');
            td.classList.add('editable');
            td.dataset.field = campo;
            td.textContent = '';
        });

        const accionTd = nuevaFila.insertCell();
        const guardarBtn = document.createElement('button');
        guardarBtn.className = 'btn btn-sm btn-success save-btn';
        guardarBtn.innerHTML = '<i class="mdi mdi-check"></i>';

        const eliminarBtn = document.createElement('button');
        eliminarBtn.className = 'btn btn-sm btn-danger delete-btn';
        eliminarBtn.innerHTML = '<i class="mdi mdi-delete"></i>';

        const container = document.createElement('div');
        container.className = 'd-flex gap-1';
        container.appendChild(guardarBtn);
        container.appendChild(eliminarBtn);
        accionTd.appendChild(container);
    });

    function guardarFila(row) {
        const id = row.getAttribute('data-id');
        const data = { id: id === 'nuevo' ? null : id };
        let valido = true;

        const numericFields = ['precio_base', 'iva_15', 'gestion_10', 'precio_total', 'precio_isspol'];

        row.querySelectorAll('.editable').forEach((cell) => {
            const campo = cell.dataset.field;
            const valor = cell.textContent.trim();

            if (!valor && ['nombre', 'categoria'].includes(campo)) {
                cell.style.backgroundColor = '#fff3cd';
                valido = false;
            } else if (numericFields.includes(campo)) {
                if (valor && isNaN(Number(valor))) {
                    cell.style.backgroundColor = '#f8d7da';
                    valido = false;
                } else {
                    cell.style.backgroundColor = '';
                }
            } else {
                cell.style.backgroundColor = '';
            }

            if (campo === 'es_medicamento') {
                data[campo] = valor === '' ? 0 : valor;
            } else {
                data[campo] = valor;
            }
        });

        if (!valido) {
            Swal.fire({
                icon: 'warning',
                title: 'Validación requerida',
                html: "<b>Revisa los siguientes puntos:</b><ul style='text-align:left'><li>Los campos <strong>nombre</strong> y <strong>categoría</strong> no pueden estar vacíos.</li><li>Los campos numéricos deben tener valores válidos.</li></ul>",
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3085d6',
            });
            return;
        }

        fetch('/insumos/guardar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((json) => {
                if (json.success) {
                    Swal.fire('Guardado', json.message, 'success');
                    if (json.id && id === 'nuevo') {
                        row.setAttribute('data-id', json.id);
                    }
                } else {
                    Swal.fire('Error', json.message || 'No se pudo guardar el insumo.', 'error');
                }
            })
            .catch(() => {
                Swal.fire('Error', 'No se pudo guardar el insumo.', 'error');
            });
    }
});
