import { renderKanban } from './renderer.js';
import { actualizarEstadoSolicitud } from './estado.js';
import { inicializarModalDetalles } from './modalDetalles.js';
import { inicializarBotonesModal } from './botonesModal.js';

const NORMALIZE = {
    estado: value => (value || '').toString().trim().toLowerCase().replace(/\s+/g, '-'),
};

function agruparPorEstado(solicitudes) {
    const agrupadas = {};

    solicitudes.forEach(item => {
        let estado = NORMALIZE.estado(item.estado);
        if (!estado || ['llamado', 'en-atencion', 'atendido'].includes(estado)) {
            estado = 'recibido';
        }
        if (!agrupadas[estado]) {
            agrupadas[estado] = [];
        }
        agrupadas[estado].push(item);
    });

    return agrupadas;
}

function actualizarContadores(agrupadas) {
    const total = Object.values(agrupadas).reduce((acc, items) => acc + items.length, 0);

    document.querySelectorAll('[id^="count-"]').forEach(counter => {
        const estado = counter.id.replace('count-', '');
        const cantidad = agrupadas[estado]?.length ?? 0;
        counter.textContent = cantidad;

        const porcentaje = document.getElementById(`percent-${estado}`);
        if (porcentaje) {
            porcentaje.textContent = total > 0 ? `(${Math.round((cantidad / total) * 100)}%)` : '';
        }
    });
}

export function initKanban(data = []) {
    renderKanban(data, (id, formId, estado) =>
        actualizarEstadoSolicitud(id, formId, estado, window.__solicitudesKanban || [], window.aplicarFiltros)
    );

    const agrupadas = agruparPorEstado(data);
    actualizarContadores(agrupadas);

    inicializarModalDetalles();
    inicializarBotonesModal();
}
