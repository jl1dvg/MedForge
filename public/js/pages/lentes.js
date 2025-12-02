document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('lentesBody');
    const btnAgregar = document.getElementById('agregarLenteBtn');

    const fetchJSON = (url, options = {}) =>
        fetch(url, options).then(async (resp) => {
            if (!resp.ok) {
                const text = await resp.text();
                let msg = text || `HTTP ${resp.status}`;
                try {
                    const js = JSON.parse(text);
                    if (js.message) msg = js.message;
                } catch (e) {
                    // ignore
                }
                throw new Error(msg);
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

    const formatRango = (l) => {
        if (l.rango_texto) return l.rango_texto;
        if (l.rango_desde !== null && l.rango_hasta !== null) {
            return `${l.rango_desde} a ${l.rango_hasta}`;
        }
        return '';
    };

    const formatTipoOptico = (t) => {
        if (t === 'una_pieza') return 'Una pieza';
        if (t === 'multipieza') return 'Multipieza';
        return '';
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
                <td>${formatRango(lente)}</td>
                <td>${lente.rango_paso ?? ''}</td>
                <td>${lente.rango_inicio_incremento ?? ''}</td>
                <td>${lente.poder || ''}</td>
                <td>${lente.constante_a ?? ''}</td>
                <td>${lente.constante_a_us ?? ''}</td>
                <td>${formatTipoOptico(lente.tipo_optico)}</td>
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
                <input id="lente-poder" class="swal2-input" placeholder="Poder fijo (opcional)" value="${lente.poder || ''}" />
                <div class="swal2-grid-2">
                    <input id="lente-rango-desde" class="swal2-input" placeholder="Rango desde (ej. -10)" value="${lente.rango_desde ?? ''}" />
                    <input id="lente-rango-hasta" class="swal2-input" placeholder="Rango hasta (ej. 30)" value="${lente.rango_hasta ?? ''}" />
                </div>
                <div class="swal2-grid-2">
                    <input id="lente-rango-paso" class="swal2-input" placeholder="Paso (ej. 0.50)" value="${lente.rango_paso ?? ''}" />
                    <input id="lente-rango-inicio" class="swal2-input" placeholder="Inicio incremento (ej. 1.00)" value="${lente.rango_inicio_incremento ?? ''}" />
                </div>
                <input id="lente-rango-texto" class="swal2-input" placeholder="Texto de rango (opcional)" value="${lente.rango_texto ?? ''}" />
                <input id="lente-constante-a" class="swal2-input" placeholder="Constante A" value="${lente.constante_a ?? ''}" />
                <input id="lente-constante-a-us" class="swal2-input" placeholder="Constante A (US)" value="${lente.constante_a_us ?? ''}" />
                <select id="lente-tipo-optico" class="swal2-select">
                    <option value="">Tipo óptico</option>
                    <option value="una_pieza"${lente.tipo_optico === 'una_pieza' ? ' selected' : ''}>Una pieza</option>
                    <option value="multipieza"${lente.tipo_optico === 'multipieza' ? ' selected' : ''}>Multipieza</option>
                </select>
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
                const rango_desde = document.getElementById('lente-rango-desde').value.trim();
                const rango_hasta = document.getElementById('lente-rango-hasta').value.trim();
                const rango_paso = document.getElementById('lente-rango-paso').value.trim();
                const rango_inicio_incremento = document.getElementById('lente-rango-inicio').value.trim();
                const rango_texto = document.getElementById('lente-rango-texto').value.trim();
                const constante_a = document.getElementById('lente-constante-a').value.trim();
                const constante_a_us = document.getElementById('lente-constante-a-us').value.trim();
                const tipo_optico = document.getElementById('lente-tipo-optico').value.trim();
                const observacion = document.getElementById('lente-observacion').value.trim();
                if (!marca || !modelo || !nombre) {
                    Swal.showValidationMessage('Marca, modelo y nombre son obligatorios');
                    return false;
                }
                return {
                    id: lente.id, marca, modelo, nombre, poder, observacion,
                    rango_desde, rango_hasta, rango_paso, rango_inicio_incremento, rango_texto,
                    constante_a, constante_a_us, tipo_optico
                };
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
