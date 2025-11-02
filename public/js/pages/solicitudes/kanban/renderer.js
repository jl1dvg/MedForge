import { showToast } from './toast.js';

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
