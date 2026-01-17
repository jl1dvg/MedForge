import { actualizarEstadoSolicitud } from './estado.js';
import { showToast } from './toast.js';
import { getDataStore } from './config.js';
import { llamarTurnoSolicitud } from './turnero.js';

const PREQUIRURGICO_DEBOUNCE_MS = 900;
let lastPrequirurgicoOpenAt = 0;
let prequirurgicoOpening = false;
const COBERTURA_DEBOUNCE_MS = 1200;
let lastCoberturaMailAt = 0;
let lastDerivacionPdfAt = 0;
let coberturaInProgress = false;

const COBERTURA_MAIL_RECIPIENTS = {
    to: 'cespinoza@cive.ec',
    cc: 'oespinoza@cive.ec',
};

const COBERTURA_MAIL_TEMPLATES = {
    iess_cive: (data) => {
        const procedimiento = data.procedimiento || 'Procedimiento solicitado';
        const plan = data.plan || 'Plan de consulta';
        const formId = data.formId || '—';
        const paciente = data.nombre || 'Paciente';
        const hcNumber = data.hcNumber || '—';

        return {
            subject: 'Solicitud de nuevo código',
            body: [
                'Buenos días,',
                '',
                `De su gentil ayuda solicitando nuevo código para el paciente ${paciente} con numero de cedula ${hcNumber} para el siguiente procedimiento:`,
                '',
                'TRATAMIENTO / OBSERVACIONES FINALES DE LA CONSULTA:',
                `Procedimiento solicitado: ${procedimiento}`,
                `Plan de consulta: ${plan}`,
                '',
                'form_id',
                `${formId}`,
                '',
                'Información que notifico para los fines pertinentes',
                '',
                'Coordinacion Quirúrgica',
                '',
                'Clínica Internacional de la Vision del Ecuador',
                '',
                'Telefono: 043 3729340 Ext. 200',
                '',
                'Celular : 099 879 6124',
                '',
                'Email: coordinacionquirurgica@cive.ec ',
                '',
                'Dir. Km 12.5 Av. Leon Febres Cordero junto a la Piazza de Villa Club',
                '',
                'www.cive.ec',
            ].join('\n'),
        };
    },
};

const COBERTURA_AFILIACIONES = new Map([
    ['contribuyente voluntario', 'iess_cive'],
    ['conyuge', 'iess_cive'],
    ['conyuge pensionista', 'iess_cive'],
    ['seguro campesino', 'iess_cive'],
    ['seguro general por montepio', 'iess_cive'],
    ['seguro general tiempo parcial', 'iess_cive'],
    ['iess', 'iess_cive'],
    ['hijos dependientes', 'iess_cive'],
    ['seguro campesino jubilado', 'iess_cive'],
    ['seguro general', 'iess_cive'],
    ['seguro general jubilado', 'iess_cive'],
]);

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

function openMailto(url) {
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.rel = 'noopener';
    anchor.style.position = 'absolute';
    anchor.style.left = '-9999px';
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    return true;
}

function shouldDebounce(lastTime, windowMs) {
    const now = Date.now();
    return now - lastTime < windowMs;
}

function buildCoberturaUrl(formId, hcNumber, pages) {
    if (!formId || !hcNumber) {
        return '';
    }

    const params = new URLSearchParams({
        form_id: formId,
        hc_number: hcNumber,
        variant: 'appendix',
    });

    if (pages) {
        params.set('pages', pages);
    }

    return `/reports/cobertura/pdf?${params.toString()}`;
}

function normalizeText(value) {
    const input = (value ?? '').toString().trim().toLowerCase();
    if (!input) {
        return '';
    }

    const replacements = {
        á: 'a',
        é: 'e',
        í: 'i',
        ó: 'o',
        ú: 'u',
        ü: 'u',
        ñ: 'n',
    };

    let normalized = '';
    for (const char of input) {
        normalized += replacements[char] ?? char;
    }

    let output = '';
    let lastWasSpace = false;
    for (const char of normalized) {
        const isLetter = char >= 'a' && char <= 'z';
        const isDigit = char >= '0' && char <= '9';

        if (isLetter || isDigit) {
            output += char;
            lastWasSpace = false;
            continue;
        }

        if (!lastWasSpace) {
            output += ' ';
            lastWasSpace = true;
        }
    }

    return output.trim();
}

function getCoberturaMailData() {
    const container = document.getElementById('prefacturaCoberturaData');
    if (!container) {
        return null;
    }

    return {
        derivacionVencida: container.dataset.derivacionVencida === '1',
        afiliacion: container.dataset.afiliacion || '',
        nombre: container.dataset.nombre || '',
        hcNumber: container.dataset.hc || '',
        procedimiento: container.dataset.procedimiento || '',
        plan: container.dataset.plan || '',
        formId: container.dataset.formId || '',
        derivacionPdf: container.dataset.derivacionPdf || '',
    };
}

function resolveCoberturaTemplate(afiliacion) {
    const normalized = normalizeText(afiliacion);
    if (COBERTURA_AFILIACIONES.has(normalized)) {
        return COBERTURA_AFILIACIONES.get(normalized) || null;
    }

    for (const [key, template] of COBERTURA_AFILIACIONES.entries()) {
        if (normalized.includes(key)) {
            return template;
        }
    }

    return null;
}

function abrirCoberturaMail() {
    if (coberturaInProgress || shouldDebounce(lastCoberturaMailAt, COBERTURA_DEBOUNCE_MS)) {
        return true;
    }

    const data = getCoberturaMailData();
    if (!data) {
        return false;
    }

    if (!data.derivacionVencida) {
        return false;
    }

    const templateKey = resolveCoberturaTemplate(data.afiliacion);
    if (!templateKey) {
        return false;
    }

    const template = COBERTURA_MAIL_TEMPLATES[templateKey];
    if (!template) {
        return false;
    }

    const { subject, body } = template(data);
    const mailto = [
        `mailto:${encodeURIComponent(COBERTURA_MAIL_RECIPIENTS.to)}`,
        '?cc=',
        encodeURIComponent(COBERTURA_MAIL_RECIPIENTS.cc),
        '&subject=',
        encodeURIComponent(subject),
        '&body=',
        encodeURIComponent(body),
    ].join('');

    coberturaInProgress = true;
    lastCoberturaMailAt = Date.now();
    openMailto(mailto);

    if (data.derivacionPdf && !shouldDebounce(lastDerivacionPdfAt, COBERTURA_DEBOUNCE_MS)) {
        lastDerivacionPdfAt = Date.now();
        abrirEnNuevaPestana(data.derivacionPdf);
        showToast('Adjunta el PDF de la derivación antes de enviar el correo.', true);
    } else {
        showToast('Adjunta el PDF de la derivación antes de enviar el correo.', false);
    }

    window.setTimeout(() => {
        coberturaInProgress = false;
    }, COBERTURA_DEBOUNCE_MS);

    return true;
}

function imprimirExamenesPrequirurgicos(tarjeta) {
    if (!tarjeta) {
        showToast('Selecciona una solicitud antes de solicitar exámenes', false);
        return false;
    }

    const now = Date.now();
    if (prequirurgicoOpening || now - lastPrequirurgicoOpenAt < PREQUIRURGICO_DEBOUNCE_MS) {
        return false;
    }
    prequirurgicoOpening = true;
    lastPrequirurgicoOpenAt = now;
    window.setTimeout(() => {
        prequirurgicoOpening = false;
    }, PREQUIRURGICO_DEBOUNCE_MS);

    const formId = tarjeta.dataset.form;
    const hcNumber = tarjeta.dataset.hc;
    if (!formId || !hcNumber) {
        showToast('No se encontró la información necesaria para imprimir los documentos.', false);
        return false;
    }

    const url = buildCoberturaUrl(formId, hcNumber, '007,010');
    const abierta = abrirEnNuevaPestana(url);

    if (!abierta) {
        showToast('Permite las ventanas emergentes para ver los documentos prequirúrgicos.', false);
    }

    return abierta;
}

function imprimirReferenciaCobertura(tarjeta) {
    if (!tarjeta) {
        showToast('Selecciona una solicitud antes de solicitar cobertura', false);
        return false;
    }

    const formId = tarjeta.dataset.form;
    const hcNumber = tarjeta.dataset.hc;
    if (!formId || !hcNumber) {
        showToast('No se encontró la información necesaria para imprimir los documentos.', false);
        return false;
    }

    const url = buildCoberturaUrl(formId, hcNumber, 'referencia');
    const abierta = abrirEnNuevaPestana(url);

    if (!abierta) {
        showToast('Permite las ventanas emergentes para ver el documento de cobertura.', false);
    }

    return abierta;
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

    const examenesBtn = document.getElementById('btnSolicitarExamenesPrequirurgicos');
    if (examenesBtn && examenesBtn.dataset.listenerAttached !== 'true') {
        examenesBtn.dataset.listenerAttached = 'true';
        examenesBtn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            const tarjeta = obtenerTarjetaActiva();
            imprimirExamenesPrequirurgicos(tarjeta);
        });
    }

    const coberturaBtn = document.getElementById('btnSolicitarCobertura');
    if (coberturaBtn && coberturaBtn.dataset.listenerAttached !== 'true') {
        coberturaBtn.dataset.listenerAttached = 'true';
        coberturaBtn.addEventListener('click', () => {
            const openedMail = abrirCoberturaMail();
            if (!openedMail) {
                const tarjeta = obtenerTarjetaActiva();
                imprimirReferenciaCobertura(tarjeta);
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

export function attachPrefacturaCoberturaMail() {
    const button = document.getElementById('btnPrefacturaSolicitarCoberturaMail');
    if (!button || button.dataset.listenerAttached === 'true') {
        return;
    }

    button.dataset.listenerAttached = 'true';
    button.addEventListener('click', () => {
        const opened = abrirCoberturaMail();
        if (!opened) {
            showToast('No hay información suficiente para armar el correo de cobertura.', false);
        }
    });
}
