import { actualizarEstadoExamen } from './estado.js';
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

function abrirEnNuevaPestana(url) {
    if (!url) {
        return false;
    }

    const nuevaVentana = window.open(url, '_blank', 'noopener');
    if (nuevaVentana && typeof nuevaVentana.focus === 'function') {
        nuevaVentana.focus();
        return true;
    }

    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.target = '_blank';
    anchor.rel = 'noopener noreferrer';
    anchor.style.position = 'absolute';
    anchor.style.left = '-9999px';
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);

    return false;
}

function actualizarDesdeBoton(nuevoEstado) {
    const tarjeta = obtenerTarjetaActiva();
    if (!tarjeta) {
        showToast('Selecciona un examen antes de continuar', false);
        return Promise.reject(new Error('No hay tarjeta activa'));
    }

    return actualizarEstadoExamen(
        tarjeta.dataset.id,
        tarjeta.dataset.form,
        nuevoEstado,
        window.__examenesKanban || [],
        window.aplicarFiltros
    ).then(() => cerrarModal());
}

export function inicializarBotonesModal() {
    const revisarBtn = document.getElementById('btnRevisarCodigos');
    if (revisarBtn && revisarBtn.dataset.listenerAttached !== 'true') {
        revisarBtn.dataset.listenerAttached = 'true';
        revisarBtn.addEventListener('click', () => {
            const estado = revisarBtn.dataset.estado || 'Revisión Códigos';
            actualizarDesdeBoton(estado).catch(() => {});
        });
    }

    const coberturaBtn = document.getElementById('btnSolicitarCobertura');
    if (coberturaBtn && coberturaBtn.dataset.listenerAttached !== 'true') {
        coberturaBtn.dataset.listenerAttached = 'true';
        coberturaBtn.addEventListener('click', () => {
            const tarjeta = obtenerTarjetaActiva();
            if (!tarjeta) {
                showToast('Selecciona un examen antes de solicitar cobertura', false);
                return;
            }

            const formId = tarjeta.dataset.form;
            const hcNumber = tarjeta.dataset.hc;

            if (formId && hcNumber) {
                const aseguradoraValores = [
                    tarjeta.dataset.afiliacion,
                    tarjeta.dataset.aseguradora,
                    tarjeta.dataset.insurer,
                    tarjeta.dataset.insurance,
                ]
                    .map(valor => (valor || '').toLowerCase())
                    .filter(valor => valor !== '');
                const aseguradorasConPlantilla = ['ecuasanitas'];
                const params = `form_id=${encodeURIComponent(formId)}&hc_number=${encodeURIComponent(hcNumber)}`;

                const usaPlantilla = aseguradorasConPlantilla.some(nombre =>
                    aseguradoraValores.some(valor => valor.includes(nombre))
                );

                if (usaPlantilla) {
                    const templateUrl = `/reports/cobertura/pdf-template?${params}`;
                    const htmlUrl = `/reports/cobertura/pdf-html?${params}`;

                    const templateAbierta = abrirEnNuevaPestana(templateUrl);
                    const htmlAbierta = abrirEnNuevaPestana(htmlUrl);

                    if (!templateAbierta || !htmlAbierta) {
                        showToast('Permite las ventanas emergentes para ver ambos documentos de cobertura.', false);
                    }
                } else {
                    const url = `/reports/cobertura/pdf?${params}`;
                    const abierta = abrirEnNuevaPestana(url);
                    if (!abierta) {
                        showToast('Permite las ventanas emergentes para ver el documento de cobertura.', false);
                    }
                }
            }

            const estado = coberturaBtn.dataset.estado || 'Docs Completos';
            actualizarDesdeBoton(estado).catch(() => {});
        });
    }
}
