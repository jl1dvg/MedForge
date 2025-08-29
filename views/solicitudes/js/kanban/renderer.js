// js/kanban/renderer.js
import { showToast } from './toast.js';

export function renderKanban(data, callbackEstadoActualizado) {
    document.querySelectorAll('.kanban-items').forEach(col => col.innerHTML = '');

    data.forEach(s => {
        const tarjeta = document.createElement('div');
        tarjeta.className = 'kanban-card border p-2 mb-2 rounded bg-light view-details';
        tarjeta.setAttribute('draggable', true);
        tarjeta.dataset.hc = s.hc_number;
        tarjeta.dataset.form = s.form_id;
        tarjeta.dataset.secuencia = s.secuencia;
        tarjeta.dataset.estado = s.estado;
        tarjeta.dataset.id = s.id;

        const fechaFormateada = moment(s.fecha_creacion).format('DD-MM-YYYY');
        const hoy = new Date();
        const fecha = new Date(s.fecha_creacion);
        const dias = Math.floor((hoy - fecha) / (1000 * 60 * 60 * 24));
        let semaforo = dias <= 3 ? '🟢 Normal' : dias <= 7 ? '🟡 Pendiente' : '🔴 Urgente';

        tarjeta.innerHTML = `
            <strong>👤 ${s.nombre}</strong><br>
            <small>🆔 ${s.hc_number}</small><br>
            <small>📅 ${fechaFormateada} <span class="badge">${semaforo}</span></small><br>
            <small>🏥 ${s.afiliacion}</small><br>
            <small>🔍 <span class="text-primary fw-bold">${s.procedimiento}</span></small><br>
            <small>👁️ ${s.ojo}</small>
        `;

        const estadoId = 'kanban-' + s.estado.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, '-');
        const col = document.getElementById(estadoId);
        if (col) col.appendChild(tarjeta);
    });

    // Activar drag & drop
    document.querySelectorAll('.kanban-items').forEach(container => {
        new Sortable(container, {
            group: 'kanban',
            animation: 150,
            onEnd: evt => {
                const item = evt.item;
                const rawEstado = evt.to.id.replace('kanban-', '').replace(/-/g, ' ');
                const newEstado = rawEstado.replace(/\b\w/g, c => c.toUpperCase());
                const formId = item.dataset.form;

                item.dataset.estado = newEstado;
                callbackEstadoActualizado(item.dataset.id, formId, newEstado);
            }
        });
    });
}