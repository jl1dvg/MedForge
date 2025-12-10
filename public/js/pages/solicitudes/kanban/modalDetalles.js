import {
    getKanbanConfig,
    getTableBodySelector,
    getDataStore,
    getEstadosMeta,
} from "./config.js";
import {formatTurno} from "./turnero.js";
import {actualizarEstadoSolicitud} from "./estado.js";
import {showToast} from "./toast.js";

let prefacturaListenerAttached = false;
const solicitudDetalleCache = new Map();
const STATUS_BADGE_TEXT_DARK = new Set(["warning", "light", "info"]);
const PATIENT_ALERT_TEXT = /paciente/i;

const SLA_META = {
    en_rango: {
        label: "En rango",
        className: "text-success fw-semibold",
        icon: "mdi-check-circle-outline",
    },
    advertencia: {
        label: "Seguimiento 72h",
        className: "text-warning fw-semibold",
        icon: "mdi-timer-sand",
    },
    critico: {
        label: "Crítico 24h",
        className: "text-danger fw-semibold",
        icon: "mdi-alert-octagon",
    },
    vencido: {
        label: "SLA vencido",
        className: "text-dark fw-semibold",
        icon: "mdi-alert",
    },
    sin_fecha: {
        label: "Sin programación",
        className: "text-muted",
        icon: "mdi-calendar-remove",
    },
    cerrado: {
        label: "Cerrado",
        className: "text-muted",
        icon: "mdi-lock-outline",
    },
};

const ALERT_TEMPLATES = [
    {
        field: "alert_reprogramacion",
        label: "Reprogramar",
        icon: "mdi-calendar-alert",
        className: "badge bg-danger text-white",
    },
    {
        field: "alert_pendiente_consentimiento",
        label: "Consentimiento",
        icon: "mdi-shield-alert",
        className: "badge bg-warning text-dark",
    },
];

function escapeHtml(value) {
    if (value === null || value === undefined) {
        return "";
    }
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function slugifyEstado(value) {
    if (!value) {
        return "";
    }

    const normalized = value
        .toString()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");

    return normalized;
}

function getEstadoBadge(estado) {
    const metaMap = getEstadosMeta();
    const slug = slugifyEstado(estado);
    const meta = metaMap[slug] || null;
    const color = meta?.color || "secondary";
    const label = meta?.label || estado || "Sin estado";
    const textClass = STATUS_BADGE_TEXT_DARK.has(color)
        ? "text-dark"
        : "text-white";

    return {
        label,
        badgeClass: `badge bg-${color} ${textClass}`,
    };
}

function formatIsoDate(iso, fallback = null, formatter = "DD-MM-YYYY HH:mm") {
    if (!iso) {
        return fallback;
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return fallback;
    }
    if (typeof moment === "function") {
        return moment(date).format(formatter);
    }
    return date.toLocaleString();
}

function formatHoursRemaining(value) {
    if (typeof value !== "number" || Number.isNaN(value)) {
        return null;
    }
    const rounded = Math.round(value);
    const abs = Math.abs(rounded);
    const label = abs >= 48 ? `${(abs / 24).toFixed(1)} día(s)` : `${abs} h`;
    return rounded >= 0 ? `Quedan ${label}` : `Retraso ${label}`;
}

function buildSlaInfo(solicitud = {}) {
    const estado = (solicitud.sla_status || "").toString().trim();
    const meta = SLA_META[estado] || SLA_META.sin_fecha;
    const deadline = formatIsoDate(solicitud.sla_deadline, null);
    const hours = formatHoursRemaining(
        typeof solicitud.sla_hours_remaining === "number"
            ? solicitud.sla_hours_remaining
            : Number.parseFloat(solicitud.sla_hours_remaining)
    );
    const detailParts = [];
    if (deadline) {
        detailParts.push(`Vence ${deadline}`);
    }
    if (hours) {
        detailParts.push(hours);
    }
    const detail = detailParts.length
        ? detailParts.join(" · ")
        : "Sin referencia SLA";

    return {
        label: meta.label,
        className: meta.className,
        detail,
        icon: meta.icon,
    };
}

function buildPrioridadInfo(solicitud = {}) {
    const origenManual = solicitud.prioridad_origen === "manual";
    const prioridad =
        solicitud.prioridad || solicitud.prioridad_automatica || "Normal";
    return {
        label: prioridad,
        helper: origenManual ? "Asignada manualmente" : "Regla automática",
        className: origenManual
            ? "text-primary fw-semibold"
            : "text-success fw-semibold",
    };
}

function buildStatsHtml(solicitud = {}) {
    const stats = [
        {
            label: "Notas",
            value: Number.parseInt(solicitud.crm_total_notas ?? 0, 10),
            icon: "mdi-note-text-outline",
        },
        {
            label: "Adjuntos",
            value: Number.parseInt(solicitud.crm_total_adjuntos ?? 0, 10),
            icon: "mdi-paperclip",
        },
        {
            label: "Tareas abiertas",
            value: `${Number.parseInt(
                solicitud.crm_tareas_pendientes ?? 0,
                10
            )}/${Number.parseInt(solicitud.crm_tareas_total ?? 0, 10)}`,
            icon: "mdi-format-list-checks",
        },
    ];

    return stats
        .map(
            (stat) => `
        <div class="prefactura-state-stat">
            <small class="text-muted d-block">${escapeHtml(stat.label)}</small>
            <span class="fw-semibold">
                ${
                stat.icon
                    ? `<i class="mdi ${escapeHtml(stat.icon)} me-1"></i>`
                    : ""
            }
                ${escapeHtml(String(stat.value ?? "0"))}
            </span>
        </div>
    `
        )
        .join("");
}

function buildAlertsHtml(solicitud = {}) {
    const alerts = ALERT_TEMPLATES.filter((template) =>
        Boolean(solicitud[template.field])
    ).map(
        (template) => `
            <span class="${escapeHtml(template.className)}">
                <i class="mdi ${escapeHtml(
            template.icon
        )} me-1"></i>${escapeHtml(template.label)}
            </span>
        `
    );

    if (!alerts.length) {
        return "";
    }

    return `<div class="prefactura-state-alerts">${alerts.join("")}</div>`;
}

function renderGridItem(label, value, helper = "", valueClass = "") {
    if (!value) {
        value = "—";
    }
    const helperHtml = helper
        ? `<span class="text-muted small d-block mt-1">${escapeHtml(helper)}</span>`
        : "";
    const className = valueClass ? ` ${valueClass}` : "";
    return `
        <div class="prefactura-state-grid-item">
            <small>${escapeHtml(label)}</small>
            <strong class="prefactura-state-value${className}">${escapeHtml(
        value
    )}</strong>
            ${helperHtml}
        </div>
    `;
}

function findSolicitudById(id) {
    if (!id) {
        return null;
    }
    const store = getDataStore();
    if (!Array.isArray(store) || !store.length) {
        return null;
    }
    return store.find((item) => String(item.id) === String(id)) || null;
}

function normalizeEstado(value) {
    return (value ?? "")
        .toString()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-");
}


function getQuickColumnElement() {
    return document.getElementById("prefacturaQuickColumn");
}

function buildContextualActionsHtml(solicitud = {}) {
    const estado = normalizeEstado(solicitud.estado || solicitud.kanban_estado);
    if (!estado) {
        return "";
    }

    const baseInfo = {
        paciente: solicitud.full_name || "Paciente sin nombre",
        procedimiento: solicitud.procedimiento || "Sin procedimiento",
        ojo: solicitud.ojo || "—",
        producto: solicitud.producto || solicitud.lente_nombre || "No registrado",
        marca: solicitud.lente_marca || solicitud.lente_brand || solicitud.producto || "No registrada",
        modelo: solicitud.lente_modelo || solicitud.lente_model || "No registrado",
        poder:
            solicitud.lente_poder ||
            solicitud.lente_power ||
            solicitud.poder ||
            solicitud.lente_dioptria ||
            "No especificado",
        observacion:
            solicitud.lente_observacion || solicitud.observacion || "Sin observaciones",
        incision: solicitud.incision || "No definida",
    };

    const blocks = [];

    if (estado === "apto-anestesia") {
        blocks.push(`
        <div class="alert alert-warning border d-flex flex-column gap-2" id="prefacturaAnestesiaPanel">
            <div class="d-flex align-items-center gap-2">
                <i class="mdi mdi-stethoscope fs-4 text-warning"></i>
                <div>
                    <strong>Revisión de anestesia pendiente</strong>
                    <p class="mb-0 text-muted">Confirma que el paciente ya fue evaluado por anestesia para avanzar.</p>
                </div>
            </div>
            <button class="btn btn-warning w-100" data-context-action="confirmar-anestesia" data-id="${escapeHtml(
            solicitud.id
        )}" data-form-id="${escapeHtml(
            solicitud.form_id
        )}">
                <i class="mdi mdi-check-decagram"></i> Marcar como apto anestesia
            </button>
        </div>
    `);
    }

    if (estado === "listo-para-agenda") {
        const basePath = getKanbanConfig().basePath || "";
        const agendaUrl = `/reports/protocolo/pdf?hc_number=${encodeURIComponent(
            solicitud.hc_number || ""
        )}&form_id=${encodeURIComponent(solicitud.form_id || "")}`;
        blocks.push(`
        <div class="alert alert-dark border d-flex flex-column gap-2" id="prefacturaAgendaPanel">
            <div class="d-flex align-items-center gap-2">
                <i class="mdi mdi-calendar-clock fs-4 text-dark"></i>
                <div>
                    <strong>Listo para agendar</strong>
                    <p class="mb-0 text-muted">Genera la orden de agenda y expórtala en PDF para coordinación.</p>
                </div>
            </div>
            <div class="d-flex flex-column flex-md-row gap-2">
                <button class="btn btn-outline-dark w-100" data-context-action="generar-agenda" data-id="${escapeHtml(
            solicitud.id
        )}" data-form-id="${escapeHtml(
            solicitud.form_id
        )}" data-base-path="${escapeHtml(basePath)}">
                    <i class="mdi mdi-calendar-plus"></i> Crear agenda
                </button>
                <a class="btn btn-dark w-100" href="${agendaUrl}" target="_blank" rel="noopener">
                    <i class="mdi mdi-file-pdf-box"></i> Exportar protocolo PDF
                </a>
            </div>
        </div>
    `);
    }

    if (!blocks.length) {
        return "";
    }

    return `<div class="mb-3 d-flex flex-column gap-2">${blocks.join("")}</div>`;
}

function syncQuickColumnVisibility() {
    const quickColumn = getQuickColumnElement();
    if (!quickColumn) {
        return;
    }

    const summary = document.getElementById("prefacturaPatientSummary");
    const state = document.getElementById("prefacturaState");
    const summaryVisible = summary
        ? !summary.classList.contains("d-none")
        : false;
    const stateVisible = state ? !state.classList.contains("d-none") : false;

    if (summaryVisible || stateVisible) {
        quickColumn.classList.remove("d-none");
    } else {
        quickColumn.classList.add("d-none");
    }
}

function resetEstadoContext() {
    const container = document.getElementById("prefacturaState");
    if (!container) {
        return;
    }
    container.classList.add("d-none");
    container.innerHTML = "";
    syncQuickColumnVisibility();
}

function resetPatientSummary() {
    const container = document.getElementById("prefacturaPatientSummary");
    if (!container) {
        return;
    }
    container.innerHTML = "";
    container.classList.add("d-none");
    syncQuickColumnVisibility();
}

function renderEstadoContext(solicitudId) {
    const container = document.getElementById("prefacturaState");
    if (!container) {
        return;
    }

    if (!solicitudId) {
        resetEstadoContext();
        return;
    }

    const solicitud = findSolicitudById(solicitudId);
    if (!solicitud) {
        container.innerHTML =
            '<div class="alert alert-light border mb-0">No se encontró información del estado seleccionado.</div>';
        container.classList.remove("d-none");
        return;
    }

    const estadoBadge = getEstadoBadge(solicitud.estado);
    const pipelineStage = solicitud.crm_pipeline_stage || "Sin etapa CRM";
    const prioridadInfo = buildPrioridadInfo(solicitud);
    const slaInfo = buildSlaInfo(solicitud);
    const responsable = solicitud.crm_responsable_nombre || "Sin responsable";
    const contactoTelefono =
        solicitud.crm_contacto_telefono ||
        solicitud.paciente_celular ||
        "Sin teléfono";
    const contactoCorreo = solicitud.crm_contacto_email || "Sin correo";
    const fuente = solicitud.crm_fuente || solicitud.fuente || "Sin fuente";
    const afiliacion = solicitud.afiliacion || "Sin afiliación";
    const proximoVencimiento = solicitud.crm_proximo_vencimiento
        ? formatIsoDate(
            solicitud.crm_proximo_vencimiento,
            "Sin vencimiento",
            "DD-MM-YYYY"
        )
        : "Sin vencimiento";

    const gridItems = [
        renderGridItem(
            "Prioridad",
            prioridadInfo.label,
            prioridadInfo.helper,
            prioridadInfo.className
        ),
        renderGridItem(
            "Seguimiento SLA",
            slaInfo.label,
            slaInfo.detail,
            slaInfo.className
        ),
        renderGridItem("Responsable", responsable, `Etapa CRM: ${pipelineStage}`),
        renderGridItem("Contacto", contactoTelefono, contactoCorreo),
        renderGridItem("Fuente", fuente, afiliacion),
        renderGridItem("Próximo vencimiento", proximoVencimiento, ""),
    ].join("");

    const statsHtml = buildStatsHtml(solicitud);
    const alertsHtml = buildAlertsHtml(solicitud);

    container.innerHTML = `
        <div class="prefactura-state-card">
            <div class="prefactura-state-header">
                <div>
                    <p class="text-muted mb-1">Estado en Kanban</p>
                    <span class="${escapeHtml(
        estadoBadge.badgeClass
    )}">${escapeHtml(estadoBadge.label)}</span>
                </div>
                <div>
                    <p class="text-muted mb-1">Etapa CRM</p>
                    <span class="badge bg-light text-dark border">${escapeHtml(
        pipelineStage
    )}</span>
                </div>
            </div>
            <div class="prefactura-state-grid">
                ${gridItems}
            </div>
            <div class="prefactura-state-stats">
                ${statsHtml}
            </div>
            ${alertsHtml}
        </div>
    `;

    container.classList.remove("d-none");
    syncQuickColumnVisibility();
}

function renderPatientSummaryFallback(solicitudId) {
    const container = document.getElementById("prefacturaPatientSummary");
    if (!container) {
        return;
    }

    if (!solicitudId) {
        resetPatientSummary();
        return;
    }

    const solicitud = findSolicitudById(solicitudId);
    if (!solicitud) {
        resetPatientSummary();
        return;
    }

    const turno = formatTurno(solicitud.turno);
    const doctor =
        solicitud.doctor || solicitud.crm_responsable_nombre || "Sin doctor";
    const procedimiento = solicitud.procedimiento || "Sin procedimiento";
    const procedimientoLabel = (procedimiento || '').toString();
    const procedimientoShort =
        procedimientoLabel.length > 90
            ? `${procedimientoLabel.slice(0, 90)}…`
            : procedimientoLabel;
    const afiliacion =
        solicitud.afiliacion || solicitud.aseguradora || "Sin afiliación";
    const hcNumber = solicitud.hc_number || "—";

    container.innerHTML = `
        <div class="alert alert-primary text-center fw-bold mb-0 prefactura-patient-alert">
            <div>🧑 Paciente: ${escapeHtml(
        solicitud.full_name || "Sin nombre"
    )}</div>
            <small class="d-block text-uppercase mt-1">${escapeHtml(
        `HC ${hcNumber}`
    )}</small>
            <small class="d-block">${escapeHtml(doctor)}</small>
            <small class="d-block text-muted">${escapeHtml(
        procedimientoShort
    )}</small>
            <small class="d-block text-muted">${escapeHtml(afiliacion)}</small>
            ${
        turno
            ? `<span class="badge bg-light text-primary mt-2">Turno #${escapeHtml(
                turno
            )}</span>`
            : ""
    }
        </div>
    `;
    container.classList.remove("d-none");
    syncQuickColumnVisibility();
}

function relocatePatientAlert(solicitudId) {
    const content = document.getElementById("prefacturaContent");
    const container = document.getElementById("prefacturaPatientSummary");

    if (!content || !container) {
        return;
    }

    const alerts = Array.from(content.querySelectorAll(".alert.alert-primary"));
    const patientAlert = alerts.find((element) =>
        PATIENT_ALERT_TEXT.test(element.textContent || "")
    );

    if (!patientAlert) {
        renderPatientSummaryFallback(solicitudId);
        return;
    }

    container.innerHTML = "";
    patientAlert.classList.add("mb-0");
    patientAlert.classList.add("prefactura-patient-alert");
    container.appendChild(patientAlert);
    container.classList.remove("d-none");
    syncQuickColumnVisibility();
}

function cssEscape(value) {
    if (typeof CSS !== "undefined" && typeof CSS.escape === "function") {
        return CSS.escape(value);
    }

    return String(value).replace(/([ #;?%&,.+*~\':"!^$\[\]()=>|\/\\@])/g, "\\$1");
}

function highlightSelection({cardId, rowId}) {
    document
        .querySelectorAll(".kanban-card")
        .forEach((element) => element.classList.remove("active"));
    const tableSelector = getTableBodySelector();
    document
        .querySelectorAll(`${tableSelector} tr`)
        .forEach((row) => row.classList.remove("table-active"));

    if (cardId) {
        const card = document.querySelector(
            `.kanban-card[data-id="${cssEscape(cardId)}"]`
        );
        if (card) {
            card.classList.add("active");
        }
    }

    if (rowId) {
        const row = document.querySelector(
            `${tableSelector} tr[data-id="${cssEscape(rowId)}"]`
        );
        if (row) {
            row.classList.add("table-active");
        }
    }
}

function resolverDataset(trigger) {
    const container = trigger.closest("[data-hc][data-form]") ?? trigger;
    const hc = trigger.dataset.hc || container?.dataset.hc || "";
    const formId = trigger.dataset.form || container?.dataset.form || "";
    const solicitudId = trigger.dataset.id || container?.dataset.id || "";

    return {hc, formId, solicitudId};
}

function buildApiCandidates(pathname) {
    const path = pathname.startsWith("/") ? pathname : `/${pathname}`;
    const {basePath} = getKanbanConfig();
    const normalizedBase =
        basePath && basePath !== "/" ? basePath.replace(/\/+$/, "") : "";
    const locationPath =
        typeof window !== "undefined" ? window.location.pathname || "" : "";
    const rootPrefix =
        normalizedBase && locationPath.includes(normalizedBase)
            ? locationPath.slice(0, locationPath.indexOf(normalizedBase))
            : "";
    const variants = new Set();

    variants.add(path);

    if (normalizedBase) {
        if (!path.startsWith(normalizedBase)) {
            variants.add(`${normalizedBase}${path}`);
        }

        const stripped = path.startsWith(normalizedBase)
            ? path.slice(normalizedBase.length) || "/"
            : path;
        variants.add(stripped.startsWith("/") ? stripped : `/${stripped}`);
    }

    if (rootPrefix) {
        variants.add(`${rootPrefix}${path}`);
    }

    return Array.from(variants);
}

let __lentesCache = null;

async function obtenerLentesCatalogo() {
    if (__lentesCache && Array.isArray(__lentesCache)) {
        return __lentesCache;
    }

    const {basePath} = getKanbanConfig();
    const normalizedBase =
        basePath && basePath !== "/" ? basePath.replace(/\/+$/, "") : "";
    const origin =
        (typeof window !== "undefined" &&
            window.location &&
            window.location.origin) ||
        "";

    const candidates = new Set();
    const appendCandidate = (path) => {
        const normalized = path.startsWith("/") ? path : `/${path}`;
        candidates.add(normalized);
        if (normalizedBase && !normalized.startsWith(normalizedBase)) {
            candidates.add(`${normalizedBase}${normalized}`);
        }
        if (origin) {
            candidates.add(`${origin}${normalized}`);
        }
    };

    // Preferir los endpoints locales para evitar CORS
    appendCandidate("/insumos/lentes/list");
    appendCandidate("/api/lentes/index.php");
    appendCandidate("/api/lentes");

    // Fallback absoluto a dominio de API (evitar si hay CORS)
    candidates.add("https://asistentecive.consulmed.me/api/lentes/index.php");

    for (const url of candidates) {
        try {
            const resp = await fetch(url, {
                method: "GET",
                credentials: "include",
            });
            if (!resp.ok) continue;
            const data = await resp.json();
            const lista = Array.isArray(data?.lentes) ? data.lentes : [];
            __lentesCache = lista;
            return lista;
        } catch (e) {
            // intentar siguiente url
        }
    }
    throw new Error("No se pudieron obtener lentes");
}

function generarPoderes(lente) {
    const powers = [];
    const min = lente?.rango_desde;
    const max = lente?.rango_hasta;
    const paso = lente?.rango_paso || 0.5;
    const inicioInc = lente?.rango_inicio_incremento || min;
    const toNum = (v) =>
        v === null || v === undefined || v === "" ? null : parseFloat(v);
    const minNum = toNum(min);
    const maxNum = toNum(max);
    const pasoNum = toNum(paso) || 0.5;
    const inicioNum = toNum(inicioInc);

    if (minNum !== null && maxNum !== null) {
        for (let v = minNum; v <= maxNum + 1e-6; v += pasoNum) {
            const rounded = Math.round(v * 100) / 100;
            if (inicioNum !== null && v < inicioNum && v > 0) continue;
            powers.push(rounded.toFixed(2));
        }
    }
    if (!powers.length && lente?.poder) {
        const p = toNum(lente.poder);
        powers.push(p !== null ? p.toFixed(2) : lente.poder);
    }
    return powers;
}

async function fetchWithFallback(urls, options) {
    let lastError;
    for (const url of urls) {
        try {
            const response = await fetch(url, options);
            if (response.ok) {
                return response;
            }
            lastError = new Error(`HTTP ${response.status}`);
        } catch (error) {
            lastError = error;
        }
    }

    throw lastError || new Error("No se pudo completar la solicitud");
}

async function fetchDetalleSolicitud({hcNumber, solicitudId, formId}) {
    const cacheKey = [hcNumber, solicitudId, formId].filter(Boolean).join(":");
    if (solicitudDetalleCache.has(cacheKey)) {
        return solicitudDetalleCache.get(cacheKey);
    }

    if (!hcNumber) {
        throw new Error("No se puede solicitar detalle sin HC");
    }

    const searchParams = new URLSearchParams({hcNumber});
    if (formId) {
        searchParams.set("form_id", formId);
    }

    const {basePath} = getKanbanConfig();
    const normalizedBase =
        basePath && basePath !== "/" ? basePath.replace(/\/+$/, "") : "";
    const apiPath = `${normalizedBase}/api/estado?${searchParams}`;
    const urls = buildApiCandidates(apiPath);
    const response = await fetchWithFallback(urls);
    if (!response.ok) {
        throw new Error("No se pudo obtener el detalle de la solicitud");
    }

    const payload = await response.json();
    const lista = Array.isArray(payload?.solicitudes) ? payload.solicitudes : [];
    const detalle = lista.find(
        (item) =>
            String(item.id) === String(solicitudId) ||
            String(item.form_id) === String(formId)
    );

    if (!detalle) {
        throw new Error("No se encontró información de la solicitud");
    }

    solicitudDetalleCache.set(cacheKey, detalle);
    return detalle;
}

async function hydrateSolicitudFromDetalle({solicitudId, formId, hcNumber}) {
    const base = findSolicitudById(solicitudId) || {};
    if (!hcNumber && !base.hc_number) {
        return base;
    }

    try {
        const detalle = await fetchDetalleSolicitud({
            hcNumber: hcNumber || base.hc_number,
            solicitudId,
            formId: formId || base.form_id,
        });

        const merged = {...base, ...detalle};
        const store = getDataStore();
        const target = store.find((item) => String(item.id) === String(solicitudId));
        if (target && typeof target === "object") {
            Object.assign(target, merged);
        }
        return merged;
    } catch (error) {
        console.warn("No se pudo hidratar solicitud con detalle", error);
        return base;
    }
}

function abrirPrefactura({hc, formId, solicitudId}) {
    if (!hc || !formId) {
        console.warn(
            "⚠️ No se encontró hc_number o form_id en la selección actual"
        );
        return;
    }

    const modalElement = document.getElementById("prefacturaModal");
    const modal = new bootstrap.Modal(modalElement);
    const content = document.getElementById("prefacturaContent");

    content.innerHTML = `
        <div class="d-flex align-items-center justify-content-center py-5">
            <div class="spinner-border text-primary me-2" role="status" aria-hidden="true"></div>
            <strong>Cargando información...</strong>
        </div>
    `;

    modal.show();
    actualizarBotonesModal(solicitudId);

    const {basePath} = getKanbanConfig();

    const prefacturaUrl = `${basePath}/prefactura?hc_number=${encodeURIComponent(
        hc
    )}&form_id=${encodeURIComponent(formId)}&solicitud_id=${encodeURIComponent(solicitudId)}`;

    Promise.all([
        fetch(prefacturaUrl).then((response) => {
            if (!response.ok) {
                throw new Error("No se encontró la prefactura");
            }
            return response.text();
        }),
        hydrateSolicitudFromDetalle({solicitudId, formId, hcNumber: hc}),
    ])
        .then(([html, solicitud]) => {
            const contextual = buildContextualActionsHtml(solicitud || {});
            content.innerHTML = `${contextual}${html}`;
            relocatePatientAlert(solicitudId);
        })
        .catch((error) => {
            console.error("❌ Error cargando prefactura:", error);
            content.innerHTML =
                '<p class="text-danger mb-0">No se pudo cargar la información de la solicitud.</p>';
        });

    modalElement.addEventListener(
        "hidden.bs.modal",
        () => {
            document
                .querySelectorAll(".kanban-card")
                .forEach((element) => element.classList.remove("active"));
            const tableSelector = getTableBodySelector();
            document
                .querySelectorAll(`${tableSelector} tr`)
                .forEach((row) => row.classList.remove("table-active"));
            resetEstadoContext();
            resetPatientSummary();
        },
        {once: true}
    );
}

function actualizarBotonesModal(solicitudId) {
    const solicitud = findSolicitudById(solicitudId);
    const normalize = (v) =>
        (v ?? "")
            .toString()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, "-");

    const estado = normalize(solicitud?.estado || solicitud?.kanban_estado);

    const btnGenerarTurno = document.getElementById("btnGenerarTurnoModal");
    const btnEnAtencion = document.getElementById("btnMarcarAtencionModal");
    const btnRevisar = document.getElementById("btnRevisarCodigos");
    const btnCobertura = document.getElementById("btnSolicitarCobertura");
    const btnCoberturaExitosa = document.getElementById("btnCoberturaExitosa");

    const show = (el, visible) => {
        if (!el) return;
        el.classList.toggle("d-none", !visible);
    };

    show(btnGenerarTurno, estado === "recibida");
    show(btnEnAtencion, estado === "llamado");
    show(btnRevisar, estado === "revision-codigos");
    show(
        btnCobertura,
        estado === "recibida" ||
        estado === "en-atencion" ||
        estado === "revision-codigos" ||
        estado === "espera-documentos"
    );
    show(btnCoberturaExitosa, estado === "en-atencion" || estado === "revision-codigos");
}

function handlePrefacturaClick(event) {
    const trigger = event.target.closest("[data-prefactura-trigger]");
    if (!trigger) {
        return;
    }

    const {hc, formId, solicitudId} = resolverDataset(trigger);
    highlightSelection({cardId: solicitudId, rowId: solicitudId});
    renderEstadoContext(solicitudId);
    renderPatientSummaryFallback(solicitudId);
    abrirPrefactura({hc, formId, solicitudId});
}

function handleContextualAction(event) {
    const button = event.target.closest("[data-context-action]");
    if (!button) {
        return;
    }

    const action = button.dataset.contextAction;
    const solicitudId = button.dataset.id;
    const formId = button.dataset.formId;
    const hcNumber = button.dataset.hc;
    const basePath = button.dataset.basePath || getKanbanConfig().basePath;

    const solicitud = findSolicitudById(solicitudId) || {};

    if (action === "confirmar-oftalmo") {
        console.log("[DEBUG] confirmar-oftalmo", {solicitudId, formId, hcNumber});

        actualizarEstadoSolicitud(
            solicitudId,
            formId,
            "apto-oftalmologo",
            getDataStore(),
            window.aplicarFiltros,
            {force: true, completado: true}
        )
            .then(() => {
                renderEstadoContext(solicitudId);
                renderPatientSummaryFallback(solicitudId);
            })
            .catch(() => {
            });
        return;
    }

    if (action === "confirmar-anestesia") {
        actualizarEstadoSolicitud(
            solicitudId,
            formId,
            "apto-anestesia",
            getDataStore(),
            window.aplicarFiltros,
            {force: true, completado: true}
        )
            .then(() => {
                renderEstadoContext(solicitudId);
                renderPatientSummaryFallback(solicitudId);
            })
            .catch(() => {
            });
        return;
    }

    if (action === "generar-agenda") {
        if (!solicitudId || !formId) {
            showToast("No se puede generar la agenda sin solicitud válida", false);
            return;
        }

        const url = `${basePath}/agenda?hc_number=${encodeURIComponent(
            solicitud.hc_number || ""
        )}&form_id=${encodeURIComponent(formId)}`;
        window.open(url, "_blank", "noopener");
    }

    if (action === "editar-lio") {
        if (typeof Swal === "undefined") {
            showToast("No se puede abrir el editor sin SweetAlert", false);
            return;
        }

        hydrateSolicitudFromDetalle({
            solicitudId,
            formId,
            hcNumber: hcNumber || solicitud.hc_number,
        })
            .then((detalle) => {
                const merged = {...solicitud, ...detalle};
                const baseProducto =
                    merged.lente_nombre || merged.producto || merged.lente_brand || "";
                const baseObservacion =
                    merged.lente_observacion || merged.observacion || "";
                const lenteSeleccionada = merged.lente_id || "";
                const poderSeleccionado = merged.lente_poder || merged.poder || "";

                const toDatetimeLocal = (value) => {
                    if (!value) return "";
                    const date = new Date(value);
                    if (Number.isNaN(date.getTime())) return "";
                    const pad = (n) => String(n).padStart(2, "0");
                    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
                        date.getDate()
                    )}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
                };

                const html = `
          <div class="box box-solid">
            <div class="box-header with-border">
              <h4 class="box-title">Editar solicitud #${escapeHtml(
                    solicitudId || ""
                )}</h4>
            </div>
            <form class="form">
              <div class="box-body">
                <h4 class="box-title text-info mb-0"><i class="ti-user me-15"></i> Datos de solicitud</h4>
                <hr class="my-15">
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Estado</label>
                      <input id="sol-estado" class="form-control" value="${escapeHtml(
                    merged.estado || merged.kanban_estado || ""
                )}" placeholder="Estado" readonly />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Doctor</label>
                      <select id="sol-doctor" class="form-select">
                        <option value="${escapeHtml(
                    merged.doctor || merged.crm_responsable_nombre || ""
                )}">
                          ${escapeHtml(
                    merged.doctor || merged.crm_responsable_nombre || "No definido"
                )}
                        </option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Fecha</label>
                      <input id="sol-fecha" type="datetime-local" class="form-control" value="${escapeHtml(
                    toDatetimeLocal(merged.fecha || merged.fecha_programada)
                )}" />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Prioridad</label>
                      <input id="sol-prioridad" class="form-control" value="${escapeHtml(
                    merged.prioridad || merged.prioridad_automatica || "Normal"
                )}" placeholder="URGENTE / NORMAL" readonly />
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Producto</label>
                      <input id="sol-producto" class="form-control" value="${escapeHtml(
                    baseProducto
                )}" placeholder="Producto asociado" />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Ojo</label>
                      <select id="sol-ojo" class="form-select">
                        <option value="">Selecciona ojo</option>
                        <option value="DERECHO"${
                    (merged.ojo || "").toUpperCase() === "DERECHO" ? " selected" : ""
                }>DERECHO</option>
                        <option value="IZQUIERDO"${
                    (merged.ojo || "").toUpperCase() === "IZQUIERDO" ? " selected" : ""
                }>IZQUIERDO</option>
                        <option value="AMBOS OJOS"${
                    (merged.ojo || "").toUpperCase() === "AMBOS OJOS" ? " selected" : ""
                }>AMBOS OJOS</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Afiliación</label>
                      <input id="sol-afiliacion" class="form-control" value="${escapeHtml(
                    merged.afiliacion || ""
                )}" placeholder="Afiliación" readonly />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Duración</label>
                      <input id="sol-duracion" class="form-control" value="${escapeHtml(
                    merged.duracion || ""
                )}" placeholder="Minutos" readonly />
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Procedimiento</label>
                  <textarea id="sol-procedimiento" class="form-control" rows="2" placeholder="Descripción">${escapeHtml(
                    merged.procedimiento || ""
                )}</textarea>
                </div>
                <div class="form-group">
                  <label class="form-label">Observación</label>
                  <textarea id="sol-observacion" class="form-control" rows="2" placeholder="Notas">${escapeHtml(
                    merged.observacion || ""
                )}</textarea>
                </div>

                <h4 class="box-title text-info mb-0 mt-20"><i class="ti-save me-15"></i> Lente e incisión</h4>
                <hr class="my-15">
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Lente</label>
                      <select id="sol-lente-id" class="form-select" data-value="${escapeHtml(
                    lenteSeleccionada
                )}">
                        <option value="">Cargando lentes...</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Nombre de lente</label>
                      <input id="sol-lente-nombre" class="form-control" value="${escapeHtml(
                    merged.lente_nombre || baseProducto
                )}" placeholder="Nombre del lente" />
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Poder del lente</label>
                      <select id="sol-lente-poder" class="form-select" data-value="${escapeHtml(
                    poderSeleccionado
                )}">
                        <option value="">Selecciona poder</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Incisión</label>
                      <input id="sol-incision" class="form-control" value="${escapeHtml(
                    merged.incision || ""
                )}" placeholder="Ej: Clear cornea temporal" />
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Observación de lente</label>
                  <textarea id="sol-lente-obs" class="form-control" rows="2" placeholder="Notas de lente">${escapeHtml(
                    baseObservacion
                )}</textarea>
                </div>
              </div>
            </form>
          </div>
        `;

                Swal.fire({
                    title: `Editar solicitud #${escapeHtml(solicitudId || "")}`,
                    html,
                    width: 800,
                    customClass: {popup: "cive-modal-wide"},
                    confirmButtonText: "Guardar cambios",
                    cancelButtonText: "Cancelar",
                    showCancelButton: true,
                    focusConfirm: false,
                    didOpen: async () => {
                        const lenteSelect = document.getElementById("sol-lente-id");
                        const poderSelect = document.getElementById("sol-lente-poder");
                        const nombreInput = document.getElementById("sol-lente-nombre");
                        if (!lenteSelect || !poderSelect) return;

                        try {
                            const lentes = await obtenerLentesCatalogo();

                            lenteSelect.innerHTML =
                                '<option value="">Selecciona lente</option>';
                            lentes.forEach((l) => {
                                const opt = document.createElement("option");
                                opt.value = l.id;
                                opt.textContent = `${l.marca ?? ""} · ${l.modelo ?? ""} · ${
                                    l.nombre ?? ""
                                }`.replace(/\s+·\s+·\s+$/, "").trim();
                                opt.dataset.nombre = l.nombre ?? "";
                                opt.dataset.poder = l.poder ?? "";
                                opt.dataset.rango_desde = l.rango_desde ?? "";
                                opt.dataset.rango_hasta = l.rango_hasta ?? "";
                                opt.dataset.rango_paso = l.rango_paso ?? "";
                                opt.dataset.rango_inicio_incremento =
                                    l.rango_inicio_incremento ?? "";
                                lenteSelect.appendChild(opt);
                            });

                            const presetLente =
                                lenteSelect.dataset.value || lenteSeleccionada || "";
                            if (presetLente) {
                                lenteSelect.value = presetLente;
                            }

                            const syncPoderes = () => {
                                const optSel = lenteSelect.selectedOptions?.[0];
                                const nombre = optSel?.dataset?.nombre || "";
                                const poderBase = optSel?.dataset?.poder || "";
                                const lenteObj = {
                                    rango_desde: optSel?.dataset?.rango_desde,
                                    rango_hasta: optSel?.dataset?.rango_hasta,
                                    rango_paso: optSel?.dataset?.rango_paso,
                                    rango_inicio_incremento: optSel?.dataset?.rango_inicio_incremento,
                                };
                                if (nombre && nombreInput && !nombreInput.value) {
                                    nombreInput.value = nombre;
                                }

                                poderSelect.innerHTML =
                                    '<option value="">Selecciona poder</option>';
                                const powers = generarPoderes(lenteObj);
                                powers.forEach((p) => {
                                    const optP = document.createElement("option");
                                    optP.value = p;
                                    optP.textContent = p;
                                    poderSelect.appendChild(optP);
                                });

                                const presetPoder =
                                    poderSelect.dataset.value ||
                                    poderSeleccionado ||
                                    poderBase ||
                                    "";
                                if (!powers.length && poderBase) {
                                    const opt = document.createElement("option");
                                    opt.value = poderBase;
                                    opt.textContent = poderBase;
                                    poderSelect.appendChild(opt);
                                }
                                if (presetPoder) {
                                    const exists = Array.from(poderSelect.options).some(
                                        (o) => o.value === presetPoder
                                    );
                                    if (!exists) {
                                        const opt = document.createElement("option");
                                        opt.value = presetPoder;
                                        opt.textContent = presetPoder;
                                        poderSelect.appendChild(opt);
                                    }
                                    poderSelect.value = presetPoder;
                                }
                            };

                            lenteSelect.addEventListener("change", syncPoderes);
                            syncPoderes();
                        } catch (error) {
                            console.warn("No se pudieron cargar lentes:", error);
                            lenteSelect.innerHTML = `<option value="${escapeHtml(
                                lenteSeleccionada
                            )}">${escapeHtml(
                                baseProducto || "Sin lentes disponibles"
                            )}</option>`;
                        }
                    },
                    preConfirm: () => {
                        const producto =
                            document.getElementById("sol-producto")?.value.trim() || "";
                        const poder =
                            document.getElementById("sol-lente-poder")?.value.trim() || "";
                        const lenteId =
                            document.getElementById("sol-lente-id")?.value.trim() || "";
                        const ojo = document.getElementById("sol-ojo")?.value.trim() || "";
                        const incision =
                            document.getElementById("sol-incision")?.value.trim() || "";
                        const observacion =
                            document.getElementById("sol-lente-obs")?.value.trim() || "";
                        const notas =
                            document.getElementById("sol-observacion")?.value.trim() || "";

                        return {
                            producto,
                            lente_nombre:
                                document.getElementById("sol-lente-nombre")?.value.trim() ||
                                producto,
                            lente_id: lenteId,
                            lente_poder: poder,
                            lente_observacion: observacion,
                            observacion: notas || observacion,
                            ojo,
                            incision,
                        };
                    },
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const payload = {id: solicitudId, ...result.value};
                    const {basePath} = getKanbanConfig();
                    const normalizedBase =
                        basePath && basePath !== "/" ? basePath.replace(/\/+$/, "") : "";
                    const postUrls = buildApiCandidates(`${normalizedBase}/api/estado`);
                    fetchWithFallback(postUrls, {
                        method: "POST",
                        headers: {"Content-Type": "application/json"},
                        body: JSON.stringify(payload),
                    })
                        .then((res) => res.json())
                        .then((resp) => {
                            if (!resp?.success) {
                                throw new Error(resp?.message || "No se guardaron los cambios");
                            }

                            const store = getDataStore();
                            const item = store.find(
                                (entry) => String(entry.id) === String(solicitudId)
                            );
                            if (item) {
                                Object.assign(item, result.value);
                            }
                            solicitudDetalleCache.delete(String(solicitudId));
                            showToast("Datos de LIO actualizados", true);
                            renderEstadoContext(solicitudId);
                            abrirPrefactura({hc: hcNumber || solicitud.hc_number, formId, solicitudId});
                        })
                        .catch((err) => {
                            console.error("No se pudo guardar LIO", err);
                            showToast(err?.message || "Error al guardar", false);
                        });
                });
            })
            .catch((error) => {
                console.error("No se pudo cargar el editor de LIO", error);
                showToast("No pudimos obtener los datos del lente", false);
            });
    }
}

export function inicializarModalDetalles() {
    if (prefacturaListenerAttached) {
        return;
    }

    prefacturaListenerAttached = true;
    document.addEventListener("click", handlePrefacturaClick);
    document.addEventListener("click", handleContextualAction);
}
