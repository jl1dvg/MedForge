import { showToast } from './toast.js';

const TURNERO_ENDPOINT = '/solicitudes/turnero-llamar';

const removeDiacritics = value =>
    (value || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

const normalizeEstado = value =>
    removeDiacritics(value)
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-');

const mapEstadoToColumn = value => {
    const normalizado = normalizeEstado(value);
    if (!normalizado) {
        return 'recibido';
    }
    if (['llamado', 'en-atencion', 'atendido'].includes(normalizado)) {
        return 'recibido';
    }
    return normalizado;
};

const esEstadoTurnero = value => ['llamado', 'en-atencion', 'atendido'].includes(normalizeEstado(value));

const formatTurno = turno => {
    const numero = Number.parseInt(turno, 10);
    if (Number.isNaN(numero) || numero <= 0) {
        return '--';
    }
    return String(numero).padStart(2, '0');
};

const escapeHtml = value => {
    if (value === null || value === undefined) {
        return '';
    }
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

const asignarTurno = id =>
    fetch(TURNERO_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, estado: 'Llamado' }),
    }).then(async response => {
        let payload = {};
        try {
            payload = await response.json();
        } catch (error) {
            payload = {};
        }

        if (response.status === 401) {
            const error = new Error('Sesión expirada');
            error.code = 401;
            throw error;
        }

        if (!response.ok || payload.success === false) {
            const error = new Error(payload.error || 'No se pudo asignar el turno');
            error.code = response.status;
            throw error;
        }

        return payload;
    });

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

        const estadoTurnero = esEstadoTurnero(solicitud.estado)
            ? escapeHtml(solicitud.estado)
            : '';
        const turnoBadge = solicitud.turno
            ? `<span class="badge bg-info text-dark fw-semibold">Turno #${formatTurno(solicitud.turno)}</span>`
            : '';
        const estadoBadge = estadoTurnero
            ? `<span class="badge bg-warning text-dark fw-semibold">${estadoTurnero}</span>`
            : '';

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
            ${(turnoBadge || estadoBadge)
                ? `<div class="mt-2 d-flex flex-wrap gap-2 align-items-center">${turnoBadge}${estadoBadge}</div>`
                : ''}
            <div class="kanban-card-actions mt-3 d-flex flex-wrap gap-2"></div>
        `;

        const estadoId = 'kanban-' + mapEstadoToColumn(solicitud.estado || '');

        const columna = document.getElementById(estadoId);
        if (columna) {
            columna.appendChild(tarjeta);
        }

        const debeMostrarAsignarTurno = normalizeEstado(solicitud.estado) === 'recibido' && !solicitud.turno;
        if (debeMostrarAsignarTurno) {
            const acciones = tarjeta.querySelector('.kanban-card-actions');
            if (acciones) {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'btn btn-sm btn-primary';
                boton.innerHTML = '<i class="mdi mdi-account-voice"></i> Asignar turno';
                boton.addEventListener('click', event => {
                    event.stopPropagation();
                    event.preventDefault();

                    if (!solicitud.id) {
                        return;
                    }

                    const original = boton.innerHTML;
                    boton.disabled = true;
                    boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Asignando...';

                    asignarTurno(solicitud.id)
                        .then(({ data }) => {
                            const turno = data?.turno;
                            const nombre = data?.full_name ?? 'Paciente sin nombre';
                            if (turno) {
                                showToast(`📣 Turno #${formatTurno(turno)} asignado a ${nombre}`);
                            } else {
                                showToast('📣 Turno asignado');
                            }

                            if (typeof window.aplicarFiltros === 'function') {
                                window.aplicarFiltros();
                            }
                        })
                        .catch(error => {
                            console.error('❌ Error al asignar turno:', error);
                            showToast(error.message || 'No se pudo asignar el turno', false);
                        })
                        .finally(() => {
                            if (document.body.contains(boton)) {
                                boton.disabled = false;
                                boton.innerHTML = original;
                            }
                        });
                });
                acciones.appendChild(boton);
            }
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
