import { renderKanban } from './renderer.js';
import { actualizarEstadoExamen } from './estado.js';
import { inicializarModalDetalles } from './modalDetalles.js';
import { inicializarBotonesModal } from './botonesModal.js';
import { initCrmInteractions, getCrmKanbanPreferences } from './crmPanel.js';

const slugifyEstado = value => {
    const raw = (value ?? '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    if (
        raw === 'revision-de-cobertura'
        || raw === 'revision-cobertura'
        || raw === 'revision-de-codigos'
        || raw === 'revision-codigos'
    ) {
        return 'revision-cobertura';
    }

    return raw;
};

const getEstadoSlug = item => slugifyEstado(item.estado || item.estado_label || item.kanban_estado);

function agruparPorEstado(examenes) {
    const agrupadas = {};

    examenes.forEach(item => {
        const estado = getEstadoSlug(item);
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
    renderKanban(data, (id, formId, estado, options = {}) =>
        actualizarEstadoExamen(id, formId, estado, window.__examenesKanban || [], window.aplicarFiltros, options)
    );

    const agrupadas = agruparPorEstado(data);
    actualizarContadores(agrupadas);

    inicializarModalDetalles();
    inicializarBotonesModal();
    initCrmInteractions();
}
