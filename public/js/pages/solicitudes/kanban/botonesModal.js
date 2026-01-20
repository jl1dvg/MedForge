import {actualizarEstadoSolicitud} from './estado.js';
import {showToast} from './toast.js';
import {getDataStore} from './config.js';
import {llamarTurnoSolicitud} from './turnero.js';

const PREQUIRURGICO_DEBOUNCE_MS = 900;
let lastPrequirurgicoOpenAt = 0;
let prequirurgicoOpening = false;
const COBERTURA_DEBOUNCE_MS = 1200;
let lastCoberturaMailAt = 0;
let coberturaInProgress = false;
let coberturaMailModalReady = false;
let coberturaMailSending = false;
let pendingCoberturaUpdate = null;
let coberturaEditorReady = false;
const coberturaTemplateCache = new Map();

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
    if (nuevaVentana) {
        if (typeof nuevaVentana.focus === 'function') {
            nuevaVentana.focus();
        }
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

function request(url, options = {}) {
    const config = {
        method: 'GET', credentials: 'same-origin', headers: {}, ...options,
    };

    if (config.body && !(config.body instanceof FormData)) {
        config.headers = {
            'Content-Type': 'application/json', ...config.headers,
        };
        config.body = JSON.stringify(config.body);
    }

    return fetch(url, config).then(async (response) => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false || data.ok === false) {
            throw new Error(data.error || data.message || 'No se pudo completar la solicitud');
        }
        return data;
    });
}

function shouldDebounce(lastTime, windowMs) {
    const now = Date.now();
    return now - lastTime < windowMs;
}

function buildAbsoluteUrl(url) {
    if (!url) {
        return '';
    }
    if (/^https?:\/\//i.test(url)) {
        return url;
    }
    if (url.startsWith('//')) {
        return `${window.location.protocol}${url}`;
    }
    const normalized = url.startsWith('/') ? url : `/${url}`;
    return `${window.location.origin}${normalized}`;
}

function buildTemplatePayload(data) {
    return {
        afiliacion: data.afiliacion || '',
        nombre: data.nombre || '',
        hc_number: data.hcNumber || '',
        procedimiento: data.procedimiento || '',
        plan: data.plan || '',
        form_id: data.formId || '',
        pdf_url: data.derivacionPdf ? buildAbsoluteUrl(data.derivacionPdf) : '',
    };
}

async function fetchCoberturaTemplate(data) {
    const cacheKey = JSON.stringify({
        afiliacion: data.afiliacion || '',
        formId: data.formId || '',
    });
    if (coberturaTemplateCache.has(cacheKey)) {
        return coberturaTemplateCache.get(cacheKey);
    }

    try {
        const result = await request('/mail-templates/cobertura/resolve', {
            method: 'POST',
            body: buildTemplatePayload(data),
        });
        const template = result?.template || null;
        coberturaTemplateCache.set(cacheKey, template);
        return template;
    } catch (error) {
        console.warn('No se pudo resolver la plantilla de cobertura', error);
        coberturaTemplateCache.set(cacheKey, null);
        return null;
    }
}

function buildCoberturaUrl(formId, hcNumber, pages) {
    if (!formId || !hcNumber) {
        return '';
    }

    const params = new URLSearchParams({
        form_id: formId, hc_number: hcNumber, variant: 'appendix',
    });

    if (pages) {
        params.set('pages', pages);
    }

    return `/reports/cobertura/pdf?${params.toString()}`;
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

function getCoberturaMailModalElements() {
    const modal = document.getElementById('coberturaMailModal');
    if (!modal || !window.bootstrap) {
        return null;
    }

    return {
        modal,
        form: modal.querySelector('[data-cobertura-mail-form]'),
        to: modal.querySelector('[data-cobertura-mail-to]'),
        cc: modal.querySelector('[data-cobertura-mail-cc]'),
        subject: modal.querySelector('[data-cobertura-mail-subject]'),
        body: modal.querySelector('[data-cobertura-mail-body]'),
        attachment: modal.querySelector('[data-cobertura-mail-attachment]'),
        pdfLink: modal.querySelector('[data-cobertura-mail-pdf]'),
        sendButton: modal.querySelector('[data-cobertura-mail-send]'),
    };
}

function ensureCoberturaEditor() {
    if (coberturaEditorReady) {
        return;
    }

    if (!window.CKEDITOR) {
        return;
    }

    const textarea = document.getElementById('coberturaMailBody');
    if (!textarea) {
        return;
    }

    if (!CKEDITOR.instances.coberturaMailBody) {
        CKEDITOR.replace('coberturaMailBody', {
            toolbar: [{name: 'basicstyles', items: ['Bold', 'Italic', 'Underline']}, {
                name: 'links',
                items: ['Link', 'Unlink']
            }, {name: 'paragraph', items: ['BulletedList', 'NumberedList']}, {
                name: 'clipboard',
                items: ['Undo', 'Redo']
            }, {name: 'editing', items: ['RemoveFormat']},], removePlugins: 'elementspath', resize_enabled: false,
        });
    }

    coberturaEditorReady = true;
}

function getCoberturaEditorInstance() {
    if (!window.CKEDITOR) {
        return null;
    }
    return CKEDITOR.instances.coberturaMailBody || null;
}

function ensureCoberturaMailModal() {
    if (coberturaMailModalReady) {
        return;
    }

    const elements = getCoberturaMailModalElements();
    if (!elements || !elements.form) {
        return;
    }

    coberturaMailModalReady = true;
    elements.form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (coberturaMailSending) {
            return;
        }

        const editor = getCoberturaEditorInstance();
        const subject = elements.subject?.value?.trim() || '';
        const body = editor ? editor.getData().trim() : (elements.body?.value?.trim() || '');
        const to = elements.to?.value?.trim() || '';
        const cc = elements.cc?.value?.trim() || '';
        const attachment = elements.attachment?.files?.[0] ?? null;

        if (!subject || !body) {
            showToast('Completa el asunto y el mensaje antes de enviar.', false);
            return;
        }

        coberturaMailSending = true;
        if (elements.sendButton) {
            elements.sendButton.disabled = true;
        }

        try {
            const formData = new FormData();
            formData.append('subject', subject);
            formData.append('body', body);
            formData.append('to', to);
            formData.append('cc', cc);
            if (attachment) {
                formData.append('attachment', attachment);
            }
            if (editor) {
                formData.append('is_html', '1');
            }
            await request('/solicitudes/cobertura-mail', {
                method: 'POST', body: formData,
            });
            showToast('Correo enviado desde el mailbox.', true);
            const instance = window.bootstrap.Modal.getInstance(elements.modal) ?? new window.bootstrap.Modal(elements.modal);
            instance.hide();
            if (pendingCoberturaUpdate) {
                const {estado, completado} = pendingCoberturaUpdate;
                pendingCoberturaUpdate = null;
                actualizarDesdeBoton(estado, {force: true, completado}).catch(() => {
                });
            }
        } catch (error) {
            console.error('No se pudo enviar el correo de cobertura', error);
            showToast(error?.message || 'No se pudo enviar el correo de cobertura.', false);
        } finally {
            coberturaMailSending = false;
            if (elements.sendButton) {
                elements.sendButton.disabled = false;
            }
        }
    });
}

function openCoberturaMailModal({subject, body, derivacionPdf, recipients}) {
    const elements = getCoberturaMailModalElements();
    if (!elements) {
        return false;
    }

    ensureCoberturaMailModal();
    ensureCoberturaEditor();
    const editor = getCoberturaEditorInstance();

    const destinatarios = recipients || {};
    if (elements.to) {
        elements.to.value = destinatarios.to || '';
    }
    if (elements.cc) {
        elements.cc.value = destinatarios.cc || '';
    }
    if (elements.subject) {
        elements.subject.value = subject || '';
    }
    if (editor) {
        editor.setData(body || '');
    } else if (elements.body) {
        elements.body.value = body || '';
    }
    if (elements.attachment) {
        elements.attachment.value = '';
    }

    if (elements.pdfLink) {
        if (derivacionPdf) {
            elements.pdfLink.href = derivacionPdf;
            elements.pdfLink.classList.remove('d-none');
        } else {
            elements.pdfLink.classList.add('d-none');
        }
    }

    const instance = window.bootstrap.Modal.getInstance(elements.modal) ?? new window.bootstrap.Modal(elements.modal);
    instance.show();

    return true;
}

async function abrirCoberturaMail() {
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

    const template = await fetchCoberturaTemplate(data);
    if (!template) {
        return false;
    }

    const subject = template.subject || '';
    const body = template.body_html || template.body_text || '';
    const pdfUrl = data.derivacionPdf ? buildAbsoluteUrl(data.derivacionPdf) : '';
    const recipients = {
        to: template.recipients_to || '',
        cc: template.recipients_cc || '',
    };

    coberturaInProgress = true;
    lastCoberturaMailAt = Date.now();
    const opened = openCoberturaMailModal({
        subject,
        body,
        derivacionPdf: pdfUrl,
        recipients,
    });
    if (!opened) {
        coberturaInProgress = false;
        return false;
    }

    // Ya no abrimos el PDF automáticamente (evita abrir 2 pestañas). El enlace queda disponible en el modal.
    if (data.derivacionPdf) {
        showToast('Adjunta el PDF de la derivación antes de enviar el correo (usa el enlace en el modal).', true);
    } else {
        showToast('No se encontró el PDF de la derivación para adjuntar.', false);
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

    return actualizarEstadoSolicitud(tarjeta.dataset.id, tarjeta.dataset.form, nuevoEstado, getDataStore(), window.aplicarFiltros, options).then(() => cerrarModal());
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
            llamarTurnoSolicitud({id: tarjeta.dataset.id})
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
            actualizarDesdeBoton(estado, {force: true, completado: true}).catch(() => {
            });
        });
    }

    const revisarBtn = document.getElementById('btnRevisarCodigos');
    if (revisarBtn && revisarBtn.dataset.listenerAttached !== 'true') {
        revisarBtn.dataset.listenerAttached = 'true';
        revisarBtn.addEventListener('click', () => {
            const estado = revisarBtn.dataset.estado || 'Revisión Códigos';
            actualizarDesdeBoton(estado).catch(() => {
            });
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
        coberturaBtn.addEventListener('click', async () => {
            const openedMail = await abrirCoberturaMail();
            if (!openedMail) {
                const tarjeta = obtenerTarjetaActiva();
                imprimirReferenciaCobertura(tarjeta);
            }

            // Pasar a revisión de códigos (pendiente)
            const estado = coberturaBtn.dataset.estado || 'Revisión Códigos';
            const completado = coberturaBtn.dataset.completado === '1';
            if (openedMail) {
                pendingCoberturaUpdate = {estado, completado};
                return;
            }
            actualizarDesdeBoton(estado, {force: true, completado}).catch(() => {
            });
        });
    }

    const coberturaExitosaBtn = document.getElementById('btnCoberturaExitosa');
    if (coberturaExitosaBtn && coberturaExitosaBtn.dataset.listenerAttached !== 'true') {
        coberturaExitosaBtn.dataset.listenerAttached = 'true';
        coberturaExitosaBtn.addEventListener('click', () => {
            const estado = coberturaExitosaBtn.dataset.estado || 'Revisión Códigos';
            const completado = coberturaExitosaBtn.dataset.completado === '1';
            actualizarDesdeBoton(estado, {force: true, completado}).catch(() => {
            });
        });
    }
}

export function attachPrefacturaCoberturaMail() {
    const button = document.getElementById('btnPrefacturaSolicitarCoberturaMail');
    if (!button || button.dataset.listenerAttached === 'true') {
        return;
    }

    button.dataset.listenerAttached = 'true';
    button.addEventListener('click', async () => {
        const opened = await abrirCoberturaMail();
        if (!opened) {
            showToast('No hay información suficiente para armar el correo de cobertura.', false);
        }
    });
}
