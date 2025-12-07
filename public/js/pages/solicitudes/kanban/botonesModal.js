import { actualizarEstadoSolicitud } from './estado.js';
import { showToast } from './toast.js';
import { getDataStore } from './config.js';
import { llamarTurnoSolicitud } from './turnero.js';

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

function actualizarDesdeBoton(nuevoEstado, options = {}) {
    const tarjeta = obtenerTarjetaActiva();
    if (!tarjeta) {
        showToast('Selecciona una solicitud antes de continuar', false);
        return Promise.reject(new Error('No hay tarjeta activa'));
    }

    return actualizarEstadoSolicitud(
        tarjeta.dataset.id,
        tarjeta.dataset.form,
        nuevoEstado,
        getDataStore(),
        window.aplicarFiltros,
        options
    ).then(() => cerrarModal());
}

export function inicializarBotonesModal() {
    const generarTurnoBtn = document.getElementById('btnGenerarTurnoModal');
    if (generarTurnoBtn && generarTurnoBtn.dataset.listenerAttached !== 'true') {
        generarTurnoBtn.dataset.listenerAttached = 'true';
        generarTurnoBtn.addEventListener('click', () => {
            const tarjeta = obtenerTarjetaActiva();
            if (!tarjeta) {
                showToast('Selecciona una solicitud antes de generar turno', false);
                return;
            }
            generarTurnoBtn.disabled = true;
            llamarTurnoSolicitud({ id: tarjeta.dataset.id })
                .then((data) => {
                    const turno = data?.turno ?? tarjeta.dataset.turno;
                    const estado = data?.estado ?? 'Llamado';
                    tarjeta.dataset.turno = turno || '';
                    tarjeta.dataset.estado = estado;
                    const store = getDataStore();
                    if (Array.isArray(store)) {
                        const item = store.find(s => String(s.id) === String(tarjeta.dataset.id));
                        if (item) {
                            item.turno = turno;
                            item.estado = estado;
                            item.kanban_estado = estado;
                        }
                    }
                    showToast('Turno generado', true);
                    if (typeof window.aplicarFiltros === 'function') {
                        window.aplicarFiltros();
                    }
                })
                .catch((error) => {
                    console.error('❌ Error al generar turno:', error);
                    showToast(error?.message || 'No se pudo generar el turno', false);
                })
                .finally(() => {
                    generarTurnoBtn.disabled = false;
                });
        });
    }

    const enAtencionBtn = document.getElementById('btnMarcarAtencionModal');
    if (enAtencionBtn && enAtencionBtn.dataset.listenerAttached !== 'true') {
        enAtencionBtn.dataset.listenerAttached = 'true';
        enAtencionBtn.addEventListener('click', () => {
            const estado = enAtencionBtn.dataset.estado || 'En atención';
            actualizarDesdeBoton(estado, { force: true, completado: true }).catch(() => {});
        });
    }

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
                showToast('Selecciona una solicitud antes de solicitar cobertura', false);
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

            // Pasar a revisión de códigos (pendiente)
            const estado = coberturaBtn.dataset.estado || 'Revisión Códigos';
            const completado = coberturaBtn.dataset.completado === '1';
            actualizarDesdeBoton(estado, { force: true, completado }).catch(() => {});
        });
    }

    const coberturaExitosaBtn = document.getElementById('btnCoberturaExitosa');
    if (coberturaExitosaBtn && coberturaExitosaBtn.dataset.listenerAttached !== 'true') {
        coberturaExitosaBtn.dataset.listenerAttached = 'true';
        coberturaExitosaBtn.addEventListener('click', () => {
            const estado = coberturaExitosaBtn.dataset.estado || 'Revisión Códigos';
            const completado = coberturaExitosaBtn.dataset.completado === '1';
            actualizarDesdeBoton(estado, { force: true, completado }).catch(() => {});
        });
    }
}
