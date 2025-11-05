import { showToast } from './toast.js';
import { llamarTurnoSolicitud, formatTurno } from './turnero.js';

export function renderKanban(data, callbackEstadoActualizado) {
    document.querySelectorAll('.kanban-items').forEach(col => {
        col.innerHTML = '';
    });

    const hoy = new Date();

    data.forEach(solicitud => {
        const tarjeta = document.createElement('div');
        tarjeta.className = 'kanban-card border p-2 mb-2 rounded bg-light view-details';
        tarjeta.setAttribute('draggable', 'true');
        tarjeta.dataset.hc = solicitud.hc_number ?? '';
        tarjeta.dataset.form = solicitud.form_id ?? '';
        tarjeta.dataset.secuencia = solicitud.secuencia ?? '';
        tarjeta.dataset.estado = solicitud.estado ?? '';
        tarjeta.dataset.id = solicitud.id ?? '';

        const fecha = solicitud.fecha ? new Date(solicitud.fecha) : null;
        const fechaFormateada = fecha ? moment(fecha).format('DD-MM-YYYY') : '—';
        const dias = fecha ? Math.floor((hoy - fecha) / (1000 * 60 * 60 * 24)) : 0;
        const semaforo = dias <= 3 ? '🟢 Normal' : dias <= 7 ? '🟡 Pendiente' : '🔴 Urgente';

        tarjeta.innerHTML = `
            <strong>👤 ${solicitud.full_name ?? 'Paciente sin nombre'}</strong><br>
            <small>🆔 ${solicitud.hc_number ?? '—'}</small><br>
            <small>📅 ${fechaFormateada} <span class="badge">${semaforo}</span></small><br>
            <small>🧑‍⚕️ ${solicitud.doctor || 'Sin doctor'}</small><br>
            <small>🏥 ${solicitud.afiliacion || 'Sin afiliación'}</small><br>
            <small>🔍 <span class="text-primary fw-bold">${solicitud.procedimiento || 'Sin procedimiento'}</span></small><br>
            <small>👁️ ${solicitud.ojo || '—'}</small><br>
            <small>💬 ${(solicitud.observacion || 'Sin nota')}</small><br>
            <small>⏱️ ${dias} día(s) en estado actual</small><br>
        `;

        const turnoAsignado = formatTurno(solicitud.turno);
        const estadoActual = (solicitud.estado ?? '').toString();

        const acciones = document.createElement('div');
        acciones.className = 'kanban-card-actions d-flex align-items-center justify-content-between gap-2 flex-wrap mt-2';

        const resumenEstado = document.createElement('span');
        resumenEstado.className = 'badge badge-estado text-bg-light text-wrap';
        resumenEstado.textContent = estadoActual !== '' ? estadoActual : 'Sin estado';
        acciones.appendChild(resumenEstado);

        const badgeTurno = document.createElement('span');
        badgeTurno.className = 'badge badge-turno';
        badgeTurno.textContent = turnoAsignado ? `Turno #${turnoAsignado}` : 'Sin turno asignado';
        acciones.appendChild(badgeTurno);

        const botonLlamar = document.createElement('button');
        botonLlamar.type = 'button';
        botonLlamar.className = 'btn btn-sm btn-outline-primary llamar-turno-btn';
        botonLlamar.innerHTML = turnoAsignado ? '<i class="mdi mdi-phone-incoming"></i> Volver a llamar' : '<i class="mdi mdi-bell-ring-outline"></i> Generar turno';

        botonLlamar.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();

            if (botonLlamar.disabled) {
                return;
            }

            botonLlamar.disabled = true;
            botonLlamar.setAttribute('aria-busy', 'true');
            const textoOriginal = botonLlamar.innerHTML;
            botonLlamar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando';

            llamarTurnoSolicitud({ id: solicitud.id })
                .then(data => {
                    const turno = formatTurno(data?.turno);
                    const nombre = data?.full_name ?? solicitud.full_name ?? 'Paciente sin nombre';

                    if (turno) {
                        badgeTurno.textContent = `Turno #${turno}`;
                    }

                    if (data?.estado) {
                        resumenEstado.textContent = data.estado;
                    }

                    showToast(`🔔 Turno asignado para ${nombre}${turno ? ` (#${turno})` : ''}`);

                    if (Array.isArray(window.__solicitudesKanban)) {
                        const item = window.__solicitudesKanban.find(s => String(s.id) === String(solicitud.id));
                        if (item) {
                            item.turno = data?.turno ?? item.turno;
                            item.estado = data?.estado ?? item.estado;
                        }
                    }

                    if (typeof window.aplicarFiltros === 'function') {
                        window.aplicarFiltros();
                    }
                })
                .catch(error => {
                    console.error('❌ Error al llamar el turno:', error);
                    showToast(error?.message ?? 'No se pudo asignar el turno', false);
                })
                .finally(() => {
                    botonLlamar.disabled = false;
                    botonLlamar.removeAttribute('aria-busy');
                    botonLlamar.innerHTML = textoOriginal;
                });
        });

        acciones.appendChild(botonLlamar);
        tarjeta.appendChild(acciones);

        const estadoId = 'kanban-' + (solicitud.estado || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-');

        const columna = document.getElementById(estadoId);
        if (columna) {
            columna.appendChild(tarjeta);
        }
    });

    document.querySelectorAll('.kanban-items').forEach(container => {
        new Sortable(container, {
            group: 'kanban',
            animation: 150,
            onEnd: evt => {
                const item = evt.item;
                const nuevoEstado = evt.to.id
                    .replace('kanban-', '')
                    .replace(/-/g, ' ')
                    .replace(/\b\w/g, c => c.toUpperCase());

                item.dataset.estado = nuevoEstado;

                const resultado = callbackEstadoActualizado(
                    item.dataset.id,
                    item.dataset.form,
                    nuevoEstado
                );

                if (resultado && typeof resultado.catch === 'function') {
                    resultado.catch(() => {
                        showToast('No se pudo actualizar el estado', false);
                    });
                }
            },
        });
    });
}
