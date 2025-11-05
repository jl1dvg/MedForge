import { actualizarEstadoSolicitud } from './estado.js';
import { showToast } from './toast.js';

function obtenerTarjetaActiva() {
    return document.querySelector('.kanban-card.view-details.active');
}

function cerrarModal() {
    const modalElement = document.getElementById('prefacturaModal');
    const instance = bootstrap.Modal.getInstance(modalElement);
    if (instance) {
        instance.hide();
    }
}

function actualizarDesdeBoton(nuevoEstado) {
    const tarjeta = obtenerTarjetaActiva();
    if (!tarjeta) {
        showToast('Selecciona una solicitud antes de continuar', false);
        return Promise.reject(new Error('No hay tarjeta activa'));
    }

    return actualizarEstadoSolicitud(
        tarjeta.dataset.id,
        tarjeta.dataset.form,
        nuevoEstado,
        window.__solicitudesKanban || [],
        window.aplicarFiltros
    ).then(() => cerrarModal());
}

export function inicializarBotonesModal() {
    const revisarBtn = document.getElementById('btnRevisarCodigos');
    if (revisarBtn) {
        revisarBtn.addEventListener('click', () => {
            const estado = revisarBtn.dataset.estado || 'Revisión Códigos';
            actualizarDesdeBoton(estado).catch(() => {});
        });
    }

    const coberturaBtn = document.getElementById('btnSolicitarCobertura');
    if (coberturaBtn) {
        coberturaBtn.addEventListener('click', () => {
            const tarjeta = obtenerTarjetaActiva();
            if (!tarjeta) {
                showToast('Selecciona una solicitud antes de solicitar cobertura', false);
                return;
            }

            const formId = tarjeta.dataset.form;
            const hcNumber = tarjeta.dataset.hc;

            if (formId && hcNumber) {
                const url = `/reports/cobertura/pdf?form_id=${encodeURIComponent(formId)}&hc_number=${encodeURIComponent(hcNumber)}`;
                window.open(url, '_blank');
            }

            const estado = coberturaBtn.dataset.estado || 'Docs Completos';
            actualizarDesdeBoton(estado).catch(() => {});
        });
    }
}
