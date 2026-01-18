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
let coberturaMailModalReady = false;
let coberturaMailSending = false;
let pendingCoberturaUpdate = null;
let coberturaEditorReady = false;

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
        const derivacionLink = data.derivacionPdf ? buildAbsoluteUrl(data.derivacionPdf) : '';

        const htmlLines = [
            '<p>Buenos días,</p>',
            `<p>De su gentil ayuda solicitando nuevo código para el paciente <strong>${escapeHtml(paciente)}</strong> ` +
                `con numero de cedula <strong>${escapeHtml(hcNumber)}</strong> para el siguiente procedimiento:</p>`,
            '<p><strong>TRATAMIENTO / OBSERVACIONES FINALES DE LA CONSULTA:</strong><br>' +
                `Procedimiento solicitado: ${escapeHtml(procedimiento)}<br>` +
                `Plan de consulta: ${escapeHtml(plan)}</p>`,
            derivacionLink
                ? `<p><a href="${escapeHtml(derivacionLink)}" target="_blank" rel="noopener">` +
                    'Ver PDF de derivación</a></p>'
                : '',
            `<p><strong>form_id</strong><br>${escapeHtml(formId)}</p>`,
            '<p>Información que notifico para los fines pertinentes</p>',
            '<p>Coordinacion Quirúrgica</p>',
            '<p>Clínica Internacional de la Vision del Ecuador</p>',
            '<p>Telefono: 043 3729340 Ext. 200</p>',
            '<p>Celular : 099 879 6124</p>',
            '<p>Email: coordinacionquirurgica@cive.ec</p>',
            '<p>Dir. Km 12.5 Av. Leon Febres Cordero junto a la Piazza de Villa Club</p>',
            '<p><a href="https://www.cive.ec" target="_blank" rel="noopener">www.cive.ec</a></p>',
        ].filter(Boolean);

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
                derivacionLink ? `Derivación (PDF): ${derivacionLink}` : null,
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
            ].filter(Boolean).join('\n'),
            bodyHtml: htmlLines.join('\n'),
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

function request(url, options = {}) {
    const config = {
        method: 'GET',
        credentials: 'same-origin',
        headers: {},
        ...options,
    };

    if (config.body && !(config.body instanceof FormData)) {
        config.headers = {
            'Content-Type': 'application/json',
            ...config.headers,
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

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
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
            toolbar: [
                { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline'] },
                { name: 'links', items: ['Link', 'Unlink'] },
                { name: 'paragraph', items: ['BulletedList', 'NumberedList'] },
                { name: 'clipboard', items: ['Undo', 'Redo'] },
                { name: 'editing', items: ['RemoveFormat'] },
            ],
            removePlugins: 'elementspath',
            resize_enabled: false,
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
                method: 'POST',
                body: formData,
            });
            showToast('Correo enviado desde el mailbox.', true);
            const instance = window.bootstrap.Modal.getInstance(elements.modal)
                ?? new window.bootstrap.Modal(elements.modal);
            instance.hide();
            if (pendingCoberturaUpdate) {
                const { estado, completado } = pendingCoberturaUpdate;
                pendingCoberturaUpdate = null;
                actualizarDesdeBoton(estado, { force: true, completado }).catch(() => {});
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

function openCoberturaMailModal({ subject, body, derivacionPdf }) {
    const elements = getCoberturaMailModalElements();
    if (!elements) {
        return false;
    }

    ensureCoberturaMailModal();
    ensureCoberturaEditor();
    const editor = getCoberturaEditorInstance();

    if (elements.to) {
        elements.to.value = COBERTURA_MAIL_RECIPIENTS.to;
    }
    if (elements.cc) {
        elements.cc.value = COBERTURA_MAIL_RECIPIENTS.cc;
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

    const instance = window.bootstrap.Modal.getInstance(elements.modal)
        ?? new window.bootstrap.Modal(elements.modal);
    instance.show();

    return true;
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

    const { subject, body, bodyHtml } = template(data);
    const pdfUrl = data.derivacionPdf ? buildAbsoluteUrl(data.derivacionPdf) : '';

    coberturaInProgress = true;
    lastCoberturaMailAt = Date.now();
    const opened = openCoberturaMailModal({
        subject,
        body: bodyHtml || body,
        derivacionPdf: pdfUrl,
    });
    if (!opened) {
        coberturaInProgress = false;
        return false;
    }

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
            if (openedMail) {
                pendingCoberturaUpdate = { estado, completado };
                return;
            }
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
