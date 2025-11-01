document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('MedicamentosEditable');
    const tbody = document.getElementById('tablaMedicamentosBody');
    const agregarBtn = document.getElementById('agregarMedicamentoBtn');

    if (!table || !tbody || !agregarBtn) {
        return;
    }

    fetch('/insumos/medicamentos/list')
        .then((res) => res.json())
        .then((json) => {
            if (json.success && Array.isArray(json.medicamentos)) {
                if (!json.medicamentos.length) {
                    mostrarErrorTabla('No hay medicamentos disponibles.');
                    return;
                }

                json.medicamentos.forEach((medicamento) => agregarFilaExistente(medicamento));
            } else {
                mostrarErrorTabla(json.message || 'No se pudieron cargar los medicamentos.');
            }
        })
        .catch(() => mostrarErrorTabla('No se pudieron cargar los medicamentos.'));

    function mostrarErrorTabla(mensaje) {
        const row = tbody.insertRow();
        const td = row.insertCell();
        td.colSpan = 3;
        td.className = 'text-center text-muted';
        td.textContent = mensaje;
    }

    function agregarFilaExistente(data) {
        const row = tbody.insertRow(-1);
        row.setAttribute('data-id', data.id);

        const campos = ['medicamento', 'via_administracion'];

        campos.forEach((campo) => {
            const td = row.insertCell();
            td.dataset.field = campo;
            td.classList.add('editable');

            if (campo === 'via_administracion') {
                td.appendChild(crearSelectVias(data[campo] ?? ''));
            } else {
                td.setAttribute('contenteditable', 'true');
                td.setAttribute('role', 'textbox');
                td.setAttribute('aria-label', 'Nombre del medicamento');
                td.textContent = data[campo] ?? '';
            }
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

    function crearSelectVias(valorActual) {
        const select = document.createElement('select');
        const opciones = ['VIA TOPICA', 'INTRAVITREA', 'VIA INFILTRATIVA', 'INTRAVENOSA', 'SUBCOJUNTIVAL'];

        opciones.forEach((opcion) => {
            const option = document.createElement('option');
            option.value = opcion;
            option.textContent = opcion;
            if (valorActual === opcion) {
                option.selected = true;
            }
            select.appendChild(option);
        });

        return select;
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
                title: '¿Eliminar medicamento?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
            }).then((result) => {
                if (result.isConfirmed) {
                    eliminarMedicamento(row);
                }
            });
        }
    });

    agregarBtn.addEventListener('click', () => {
        const nuevaFila = tbody.insertRow(-1);
        nuevaFila.setAttribute('data-id', 'nuevo');

        const campos = ['medicamento', 'via_administracion'];
        campos.forEach((campo) => {
            const td = nuevaFila.insertCell();
            td.dataset.field = campo;
            td.classList.add('editable');

            if (campo === 'via_administracion') {
                td.appendChild(crearSelectVias(''));
            } else {
                td.setAttribute('contenteditable', 'true');
                td.setAttribute('role', 'textbox');
                td.setAttribute('aria-label', 'Nombre del medicamento');
                td.textContent = '';
            }
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

        row.querySelectorAll('.editable').forEach((cell) => {
            const campo = cell.dataset.field;
            let valor;

            if (campo === 'via_administracion') {
                const select = cell.querySelector('select');
                valor = select ? select.value : '';
            } else {
                valor = cell.textContent.trim();
            }

            if (!valor && ['medicamento', 'via_administracion'].includes(campo)) {
                cell.style.backgroundColor = '#fff3cd';
                valido = false;
            } else {
                cell.style.backgroundColor = '';
            }

            data[campo] = valor;
        });

        if (!valido) {
            Swal.fire({
                icon: 'warning',
                title: 'Validación requerida',
                html: "<b>Revisa los siguientes puntos:</b><ul style='text-align:left'><li>Los campos <strong>medicamento</strong> y <strong>vía de administración</strong> no pueden estar vacíos.</li></ul>",
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#3085d6',
            });
            return;
        }

        fetch('/insumos/medicamentos/guardar', {
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
                    Swal.fire('Error', json.message || 'No se pudo guardar el medicamento.', 'error');
                }
            })
            .catch(() => {
                Swal.fire('Error', 'No se pudo guardar el medicamento.', 'error');
            });
    }

    function eliminarMedicamento(row) {
        const id = row.getAttribute('data-id');
        if (id === 'nuevo') {
            row.remove();
            return;
        }

        fetch('/insumos/medicamentos/eliminar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id }),
        })
            .then((res) => res.json())
            .then((json) => {
                if (json.success) {
                    row.remove();
                    Swal.fire('Eliminado', json.message, 'success');
                } else {
                    Swal.fire('Error', json.message || 'No se pudo eliminar el medicamento.', 'error');
                }
            })
            .catch(() => {
                Swal.fire('Error', 'No se pudo eliminar el medicamento.', 'error');
            });
    }
});
