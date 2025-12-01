document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('lentesBody');
    const btnAgregar = document.getElementById('agregarLenteBtn');

    const fetchJSON = (url, options = {}) =>
        fetch(url, options).then(async (resp) => {
            if (!resp.ok) {
                const text = await resp.text();
                throw new Error(text || `HTTP ${resp.status}`);
            }
            return resp.json();
        });

    const cargar = () => {
        fetchJSON('/insumos/lentes/list')
            .then((data) => {
                const lentes = Array.isArray(data?.lentes) ? data.lentes : [];
                render(lentes);
            })
            .catch((err) => {
                console.error(err);
                Swal.fire('Error', 'No se pudo cargar el catálogo de lentes', 'error');
            });
    };

    const render = (lentes) => {
        if (!tableBody) return;
        tableBody.innerHTML = '';
        lentes.forEach((lente) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${lente.marca || ''}</td>
                <td>${lente.modelo || ''}</td>
                <td>${lente.nombre || ''}</td>
                <td>${lente.poder || ''}</td>
                <td>${lente.observacion || ''}</td>
                <td class="text-center">
                    <button class="btn btn-sm btn-outline-primary" data-accion="editar" data-id="${lente.id}">Editar</button>
                    <button class="btn btn-sm btn-outline-danger" data-accion="eliminar" data-id="${lente.id}">Eliminar</button>
                </td>
            `;
            tr.querySelectorAll('button[data-accion]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (btn.dataset.accion === 'editar') {
                        abrirModal(lente);
                    } else if (btn.dataset.accion === 'eliminar') {
                        eliminar(lente.id);
                    }
                });
            });
            tableBody.appendChild(tr);
        });
    };

    const abrirModal = (lente = {}) => {
        Swal.fire({
            title: lente.id ? 'Editar lente' : 'Nuevo lente',
            html: `
                <input id="lente-marca" class="swal2-input" placeholder="Marca" value="${lente.marca || ''}" />
                <input id="lente-modelo" class="swal2-input" placeholder="Modelo" value="${lente.modelo || ''}" />
                <input id="lente-nombre" class="swal2-input" placeholder="Nombre" value="${lente.nombre || ''}" />
                <input id="lente-poder" class="swal2-input" placeholder="Poder (opcional)" value="${lente.poder || ''}" />
                <input id="lente-observacion" class="swal2-input" placeholder="Observación (opcional)" value="${lente.observacion || ''}" />
            `,
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const marca = document.getElementById('lente-marca').value.trim();
                const modelo = document.getElementById('lente-modelo').value.trim();
                const nombre = document.getElementById('lente-nombre').value.trim();
                const poder = document.getElementById('lente-poder').value.trim();
                const observacion = document.getElementById('lente-observacion').value.trim();
                if (!marca || !modelo || !nombre) {
                    Swal.showValidationMessage('Marca, modelo y nombre son obligatorios');
                    return false;
                }
                return {id: lente.id, marca, modelo, nombre, poder, observacion};
            },
        }).then((result) => {
            if (!result.isConfirmed) return;
            guardar(result.value);
        });
    };

    const guardar = (payload) => {
        fetchJSON('/insumos/lentes/guardar', {
            method: 'POST',
            headers: {'Content-Type': 'application/json;charset=UTF-8'},
            body: JSON.stringify(payload),
        })
            .then(() => {
                Swal.fire('Listo', 'Lente guardado', 'success');
                cargar();
            })
            .catch((err) => {
                console.error(err);
                Swal.fire('Error', 'No se pudo guardar el lente', 'error');
            });
    };

    const eliminar = (id) => {
        Swal.fire({
            title: 'Eliminar lente',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
        }).then((res) => {
            if (!res.isConfirmed) return;
            fetchJSON('/insumos/lentes/eliminar', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                body: new URLSearchParams({id}),
            })
                .then(() => {
                    Swal.fire('Eliminado', 'Lente eliminado', 'success');
                    cargar();
                })
                .catch((err) => {
                    console.error(err);
                    Swal.fire('Error', 'No se pudo eliminar el lente', 'error');
                });
        });
    };

    if (btnAgregar) btnAgregar.addEventListener('click', () => abrirModal());
    cargar();
});
