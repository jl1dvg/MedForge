import { actualizarEstadoExamen } from './estado.js';
import { showToast } from './toast.js';

const PREQUIRURGICO_DEBOUNCE_MS = 900;
let lastPrequirurgicoOpenAt = 0;
let prequirurgicoOpening = false;
const COBERTURA_DEBOUNCE_MS = 1200;
let lastCoberturaMailAt = 0;
let coberturaInProgress = false;
let coberturaMailModalReady = false;
let coberturaMailSending = false;
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
        method: "GET",
        credentials: "same-origin",
        headers: {},
        ...options,
    };

    if (config.body && !(config.body instanceof FormData)) {
        config.headers = {
            "Content-Type": "application/json",
            ...config.headers,
        };
        config.body = JSON.stringify(config.body);
    }

    return fetch(url, config).then(async (response) => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false || data.ok === false) {
            throw new Error(data.error || data.message || "No se pudo completar la solicitud");
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
        return "";
    }
    if (/^https?:\/\//i.test(url)) {
        return url;
    }
    if (url.startsWith("//")) {
        return `${window.location.protocol}${url}`;
    }
    const normalized = url.startsWith("/") ? url : `/${url}`;
    return `${window.location.origin}${normalized}`;
}

function buildTemplatePayload(data) {
    return {
        afiliacion: data.afiliacion || "",
        nombre: data.nombre || "",
        hc_number: data.hcNumber || "",
        procedimiento: data.procedimiento || "",
        plan: data.plan || "",
        form_id: data.formId || "",
        pdf_url: data.derivacionPdf ? buildAbsoluteUrl(data.derivacionPdf) : "",
        template_key: data.templateKey || "",
    };
}

async function fetchCoberturaTemplate(data) {
    const cacheKey = JSON.stringify({
        afiliacion: data.afiliacion || "",
        formId: data.formId || "",
        templateKey: data.templateKey || "",
    });
    if (coberturaTemplateCache.has(cacheKey)) {
        return coberturaTemplateCache.get(cacheKey);
    }

    try {
        const result = await request("/mail-templates/cobertura/resolve", {
            method: "POST",
            body: buildTemplatePayload(data),
        });
        const template = result?.template || null;
        coberturaTemplateCache.set(cacheKey, template);
        return template;
    } catch (error) {
        console.warn("No se pudo resolver la plantilla de cobertura", error);
        coberturaTemplateCache.set(cacheKey, null);
        return null;
    }
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

function buildCobertura012AUrl(formId, hcNumber, examenId) {
    if (!formId || !hcNumber) {
        return '';
    }

    const params = new URLSearchParams({
        form_id: formId,
        hc_number: hcNumber,
    });

    if (examenId) {
        params.set('examen_id', examenId);
    }

    return `/examenes/cobertura-012a/pdf?${params.toString()}`;
}

function getCoberturaMailData() {
    const container = document.getElementById("prefacturaCoberturaData");
    if (!container) {
        return null;
    }

    return {
        derivacionVencida: container.dataset.derivacionVencida === "1",
        afiliacion: container.dataset.afiliacion || "",
        nombre: container.dataset.nombre || "",
        hcNumber: container.dataset.hc || "",
        procedimiento: container.dataset.procedimiento || "",
        plan: container.dataset.plan || "",
        formId: container.dataset.formId || "",
        derivacionPdf: container.dataset.derivacionPdf || "",
        templateKey: container.dataset.templateKey || "",
        examenId: container.dataset.examenId || "",
    };
}

function getCoberturaMailModalElements() {
    const modal = document.getElementById("coberturaMailModal");
    if (!modal || !window.bootstrap) {
        return null;
    }

    return {
        modal,
        form: modal.querySelector("[data-cobertura-mail-form]"),
        to: modal.querySelector("[data-cobertura-mail-to]"),
        cc: modal.querySelector("[data-cobertura-mail-cc]"),
        subject: modal.querySelector("[data-cobertura-mail-subject]"),
        body: modal.querySelector("[data-cobertura-mail-body]"),
        attachment: modal.querySelector("[data-cobertura-mail-attachment]"),
        pdfLink: modal.querySelector("[data-cobertura-mail-pdf]"),
        pdf012aLink: modal.querySelector("[data-cobertura-mail-012a]"),
        sendButton: modal.querySelector("[data-cobertura-mail-send]"),
    };
}

function ensureCoberturaEditor() {
    if (coberturaEditorReady) {
        return;
    }

    if (!window.CKEDITOR) {
        return;
    }

    const textarea = document.getElementById("coberturaMailBody");
    if (!textarea) {
        return;
    }

    if (!CKEDITOR.instances.coberturaMailBody) {
        CKEDITOR.replace("coberturaMailBody", {
            toolbar: [
                { name: "basicstyles", items: ["Bold", "Italic", "Underline"] },
                { name: "links", items: ["Link", "Unlink"] },
                { name: "paragraph", items: ["BulletedList", "NumberedList"] },
                { name: "clipboard", items: ["Undo", "Redo"] },
                { name: "editing", items: ["RemoveFormat"] },
            ],
            removePlugins: "elementspath",
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
    elements.form.addEventListener("submit", async (event) => {
        event.preventDefault();
        if (coberturaMailSending) {
            return;
        }

        const editor = getCoberturaEditorInstance();
        const subject = elements.subject?.value?.trim() || "";
        const body = editor ? editor.getData().trim() : (elements.body?.value?.trim() || "");
        const to = elements.to?.value?.trim() || "";
        const cc = elements.cc?.value?.trim() || "";
        const attachment = elements.attachment?.files?.[0] ?? null;

        if (!subject || !body) {
            showToast("Completa el asunto y el mensaje antes de enviar.", false);
            return;
        }

        coberturaMailSending = true;
        if (elements.sendButton) {
            elements.sendButton.disabled = true;
        }

        try {
            const coberturaData = getCoberturaMailData() || {};
            const formData = new FormData();
            formData.append("subject", subject);
            formData.append("body", body);
            formData.append("to", to);
            formData.append("cc", cc);
            if (coberturaData.examenId) {
                formData.append("examen_id", coberturaData.examenId);
            }
            if (coberturaData.formId) {
                formData.append("form_id", coberturaData.formId);
            }
            if (coberturaData.hcNumber) {
                formData.append("hc_number", coberturaData.hcNumber);
            }
            if (coberturaData.afiliacion) {
                formData.append("afiliacion", coberturaData.afiliacion);
            }
            if (coberturaData.templateKey) {
                formData.append("template_key", coberturaData.templateKey);
            }
            if (coberturaData.derivacionPdf) {
                formData.append("derivacion_pdf", buildAbsoluteUrl(coberturaData.derivacionPdf));
            }
            if (attachment) {
                formData.append("attachment", attachment);
            }
            if (editor) {
                formData.append("is_html", "1");
            }
            const response = await request("/examenes/cobertura-mail", {
                method: "POST",
                body: formData,
            });
            showToast("Correo enviado.", true);
            updateCoberturaMailStatus(response);
            const instance =
                window.bootstrap.Modal.getInstance(elements.modal) ??
                new window.bootstrap.Modal(elements.modal);
            instance.hide();
        } catch (error) {
            console.error("No se pudo enviar el correo de cobertura", error);
            showToast(error?.message || "No se pudo enviar el correo de cobertura.", false);
        } finally {
            coberturaMailSending = false;
            if (elements.sendButton) {
                elements.sendButton.disabled = false;
            }
        }
    });
}

function openCoberturaMailModal({ subject, body, derivacionPdf, recipients }) {
    const elements = getCoberturaMailModalElements();
    if (!elements) {
        return false;
    }

    ensureCoberturaMailModal();
    ensureCoberturaEditor();
    const editor = getCoberturaEditorInstance();
    const data = getCoberturaMailData() || {};

    const destinatarios = recipients || {};
    if (elements.to) {
        elements.to.value = destinatarios.to || "";
    }
    if (elements.cc) {
        elements.cc.value = destinatarios.cc || "";
    }
    if (elements.subject) {
        elements.subject.value = subject || "";
    }
    if (editor) {
        editor.setData(body || "");
    } else if (elements.body) {
        elements.body.value = body || "";
    }
    if (elements.attachment) {
        elements.attachment.value = "";
    }

    if (elements.pdfLink) {
        if (derivacionPdf) {
            elements.pdfLink.href = derivacionPdf;
            elements.pdfLink.classList.remove("d-none");
        } else {
            elements.pdfLink.classList.add("d-none");
        }
    }

    if (elements.pdf012aLink) {
        const link012a = buildCobertura012AUrl(data?.formId, data?.hcNumber, data?.examenId);
        if (link012a) {
            elements.pdf012aLink.href = link012a;
            elements.pdf012aLink.classList.remove("d-none");
        } else {
            elements.pdf012aLink.classList.add("d-none");
        }
    }

    const instance =
        window.bootstrap.Modal.getInstance(elements.modal) ??
        new window.bootstrap.Modal(elements.modal);
    instance.show();

    return true;
}

async function abrirCoberturaMail() {
    if (coberturaInProgress || shouldDebounce(lastCoberturaMailAt, COBERTURA_DEBOUNCE_MS)) {
        return { opened: true };
    }

    const data = getCoberturaMailData();
    if (!data) {
        return { opened: false, error: "No hay información suficiente para armar el correo de cobertura." };
    }

    if (!data.afiliacion) {
        return { opened: false, error: "No se encontró la afiliación necesaria para armar el correo." };
    }

    const template = await fetchCoberturaTemplate(data);
    if (!template) {
        return { opened: false, error: "No hay plantilla configurada para esta afiliación." };
    }

    const subject = template.subject || "";
    const body = template.body_html || template.body_text || "";
    const pdfUrl = data.derivacionPdf ? buildAbsoluteUrl(data.derivacionPdf) : "";
    const recipients = {
        to: template.recipients_to || "",
        cc: template.recipients_cc || "",
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
        return { opened: false, error: "No se pudo abrir el formulario de correo de cobertura." };
    }

    if (data.derivacionPdf) {
        showToast("Adjunta el PDF de la derivación antes de enviar el correo (usa el enlace en el modal).", true);
    } else {
        showToast("No se encontró el PDF de la derivación para adjuntar.", false);
    }

    window.setTimeout(() => {
        coberturaInProgress = false;
    }, COBERTURA_DEBOUNCE_MS);

    return { opened: true };
}

function imprimirExamenesPrequirurgicos(tarjeta) {
    if (!tarjeta) {
        showToast('Selecciona un examen antes de solicitar exámenes', false);
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
        showToast('Selecciona un examen antes de solicitar cobertura', false);
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
            const estado = revisarBtn.dataset.estado || 'Revisión de cobertura';
            actualizarDesdeBoton(estado).catch(() => {});
        });
    }

    const coberturaBtn = document.getElementById('btnSolicitarCobertura');
    if (coberturaBtn && coberturaBtn.dataset.listenerAttached !== 'true') {
        coberturaBtn.dataset.listenerAttached = 'true';
        coberturaBtn.addEventListener('click', () => {
            const tarjeta = obtenerTarjetaActiva();
            imprimirReferenciaCobertura(tarjeta);

            const estado = coberturaBtn.dataset.estado || 'Docs Completos';
            actualizarDesdeBoton(estado).catch(() => {});
        });
    }
}

function updateCoberturaMailStatus(payload = {}) {
    const statusLabel = buildCoberturaMailStatusLabel(payload);
    if (!statusLabel) {
        return;
    }

    const prefacturaStatus = document.getElementById("prefacturaCoberturaMailStatus");
    if (prefacturaStatus) {
        prefacturaStatus.textContent = statusLabel;
        prefacturaStatus.classList.remove("d-none");
        if (payload?.sent_at) {
            prefacturaStatus.dataset.sentAt = payload.sent_at;
        }
        if (payload?.sent_by_name) {
            prefacturaStatus.dataset.sentBy = payload.sent_by_name;
        }
    }

    const modalStatus = document.getElementById("coberturaMailModalStatus");
    if (modalStatus) {
        modalStatus.textContent = statusLabel;
        modalStatus.classList.remove("d-none");
        if (payload?.sent_at) {
            modalStatus.dataset.sentAt = payload.sent_at;
        }
        if (payload?.sent_by_name) {
            modalStatus.dataset.sentBy = payload.sent_by_name;
        }
    }
}

function buildCoberturaMailStatusLabel(payload) {
    const sentAt = payload?.sent_at || payload?.sentAt || "";
    if (!sentAt) {
        return "";
    }

    const sentBy = payload?.sent_by_name || payload?.sentByName || "";
    const formattedDate = formatCoberturaDateTime(sentAt);
    if (!formattedDate) {
        return "";
    }

    return sentBy
        ? `Cobertura solicitada el ${formattedDate} por ${sentBy}`
        : `Cobertura solicitada el ${formattedDate}`;
}

function formatCoberturaDateTime(value) {
    if (!value) {
        return "";
    }

    const normalized = value.replace(" ", "T");
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const pad = (num) => String(num).padStart(2, "0");
    return `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function attachPrefacturaCoberturaMail() {
    const button = document.getElementById("btnPrefacturaSolicitarCoberturaMail");
    if (!button || button.dataset.listenerAttached === "true") {
        return;
    }

    button.dataset.listenerAttached = "true";
    button.addEventListener("click", async () => {
        const result = await abrirCoberturaMail();
        if (!result.opened) {
            showToast(result.error || "No hay información suficiente para armar el correo de cobertura.", false);
        }
    });
}
