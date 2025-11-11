import { renderKanban } from './renderer.js';
import { actualizarEstadoSolicitud } from './estado.js';
import { inicializarModalDetalles } from './modalDetalles.js';
import { inicializarBotonesModal } from './botonesModal.js';
import { initCrmInteractions, getCrmKanbanPreferences } from './crmPanel.js';
import { getDataStore } from './config.js';

const NORMALIZE = {
    estado: value => (value || '').toString().trim().toLowerCase().replace(/\s+/g, '-'),
};

function agruparPorEstado(solicitudes) {
    const agrupadas = {};

    solicitudes.forEach(item => {
        const estado = NORMALIZE.estado(item.estado);
        if (!agrupadas[estado]) {
            agrupadas[estado] = [];
        }
        agrupadas[estado].push(item);
    });

    return agrupadas;
}

function actualizarContadores(agrupadas) {
    const total = Object.values(agrupadas).reduce((acc, items) => acc + items.length, 0);
    const { columnLimit } = getCrmKanbanPreferences();

    document.querySelectorAll('[id^="count-"]').forEach(counter => {
        const estado = counter.id.replace('count-', '');
        const cantidad = agrupadas[estado]?.length ?? 0;
        counter.textContent = columnLimit > 0 ? `${cantidad}/${columnLimit}` : cantidad;
        if (columnLimit > 0) {
            counter.title = `Mostrando ${cantidad} de ${columnLimit} tarjetas permitidas para esta columna`;
        } else {
            counter.removeAttribute('title');
        }

        const porcentaje = document.getElementById(`percent-${estado}`);
        if (porcentaje) {
            porcentaje.textContent = total > 0 ? `(${Math.round((cantidad / total) * 100)}%)` : '';
        }
    });
}

export function initKanban(data = []) {
    renderKanban(data, (id, formId, estado) =>
        actualizarEstadoSolicitud(id, formId, estado, getDataStore(), window.aplicarFiltros)
    );

    const agrupadas = agruparPorEstado(data);
    actualizarContadores(agrupadas);

    inicializarModalDetalles();
    inicializarBotonesModal();
    initCrmInteractions();
}
