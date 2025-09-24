// js/kanban/index.js
import {poblarAfiliacionesUnicas, poblarDoctoresUnicos, filtrarSolicitudes} from './filtros.js';
import {renderKanban} from './renderer.js';
import {actualizarEstadoSolicitud} from './estado.js';
import {inicializarModalDetalles} from "./modalDetalles";
import {inicializarBotonesModal} from "./botonesModal";

function agruparPorEstado(solicitudes) {
    const resultado = {};
    solicitudes.forEach(solicitud => {
        const estado = solicitud.estado?.toLowerCase().replace(/\s/g, '-'); // <--- 💡 clave
        if (!estado) return;
        if (!resultado[estado]) {
            resultado[estado] = [];
        }
        resultado[estado].push(solicitud);
    });
    return resultado;
}

function actualizarContadoresKanban(agrupadas) {
    const total = Object.values(agrupadas).reduce((sum, arr) => sum + arr.length, 0);

    // Buscar todos los contadores por id
    document.querySelectorAll('[id^="count-"]').forEach(contador => {
        const estado = contador.id.replace('count-', '');
        const count = agrupadas[estado]?.length || 0;

        contador.textContent = count;

        const porcentaje = document.getElementById(`percent-${estado}`);
        if (porcentaje) {
            porcentaje.textContent = total > 0 ? `(${Math.round(count / total * 100)}%)` : '';
        }
    });
}

const filtros = () => ({
    afiliacion: document.getElementById('kanbanAfiliacionFilter').value.toLowerCase(),
    doctor: document.getElementById('kanbanDoctorFilter').value,
    fechaTexto: document.getElementById('kanbanDateFilter').value,
    estadoSemaforo: document.getElementById('kanbanSemaforoFilter').value
});

export function initKanban(allSolicitudes) {
    poblarAfiliacionesUnicas(allSolicitudes);
    poblarDoctoresUnicos(allSolicitudes);

    const aplicarFiltros = () => {
        const filtradas = filtrarSolicitudes(allSolicitudes, filtros());
        renderKanban(filtradas, (id, formId, estado) => {
            actualizarEstadoSolicitud(id, formId, estado, allSolicitudes, aplicarFiltros);
        });
        const agrupadasPorEstado = agruparPorEstado(filtradas);
        console.log('📦 Agrupadas por estado:', agrupadasPorEstado);
        actualizarContadoresKanban(agrupadasPorEstado);
    };
    // Inicializar eventos del modal y botones
    inicializarModalDetalles();     // 👈 Aquí
    inicializarBotonesModal();      // 👈 Y aquí

    document.getElementById('kanbanAfiliacionFilter').addEventListener('change', aplicarFiltros);
    document.getElementById('kanbanDoctorFilter').addEventListener('change', aplicarFiltros);
    document.getElementById('kanbanSemaforoFilter').addEventListener('change', aplicarFiltros);

    $('#kanbanDateFilter').daterangepicker({
        locale: {format: 'DD-MM-YYYY', applyLabel: "Aplicar", cancelLabel: "Cancelar"},
        autoUpdateInput: false
    }).on('apply.daterangepicker', function (ev, picker) {
        this.value = `${picker.startDate.format('DD-MM-YYYY')} - ${picker.endDate.format('DD-MM-YYYY')}`;
        aplicarFiltros();
    }).on('cancel.daterangepicker', function () {
        this.value = '';
        aplicarFiltros();
    });

    aplicarFiltros(); // Render inicial
}