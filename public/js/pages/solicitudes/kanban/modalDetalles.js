import {
    getKanbanConfig,
    getTableBodySelector,
    getDataStore,
    getEstadosMeta,
} from "./config.js";
import {formatTurno} from "./turnero.js";
import {actualizarEstadoSolicitud} from "./estado.js";
import {showToast} from "./toast.js";
import {updateKanbanCardSla} from "./renderer.js";
import {attachPrefacturaCoberturaMail} from "./botonesModal.js";

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

function syncPrefacturaContext({formId, hcNumber, solicitudId}) {
    const payload = {
        formId: formId || "",
        hcNumber: hcNumber || "",
        solicitudId: solicitudId || "",
    };
    window.__prefacturaCurrent = payload;

    const button = document.getElementById("btnRescrapeDerivacion");
    if (!button) {
        return;
    }

    button.dataset.formId = payload.formId;
    button.dataset.hcNumber = payload.hcNumber;
    button.dataset.solicitudId = payload.solicitudId;
}

function getPrefacturaContextFromButton(button) {
    const fallback = window.__prefacturaCurrent || {};
    const formId = button?.dataset?.formId || fallback.formId || "";
    const hcNumber = button?.dataset?.hcNumber || fallback.hcNumber || "";
    const solicitudId = button?.dataset?.solicitudId || fallback.solicitudId || "";

    return {formId, hcNumber, solicitudId};
}

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

function formatDerivacionVigencia(fechaVigencia) {
    if (!fechaVigencia) {
        return {texto: "No disponible", badge: null};
    }
    const vigenciaDate = new Date(fechaVigencia);
    if (Number.isNaN(vigenciaDate.getTime())) {
        return {texto: "No disponible", badge: null};
    }

    const hoy = new Date();
    const diffMs = vigenciaDate.getTime() - hoy.getTime();
    const diffDays = Math.trunc(diffMs / (1000 * 60 * 60 * 24));
    let badge = null;

    if (diffDays >= 60) {
        badge = {color: "success", texto: "Vigente"};
    } else if (diffDays >= 30) {
        badge = {color: "info", texto: "Vigente"};
    } else if (diffDays >= 15) {
        badge = {color: "warning", texto: "Por vencer"};
    } else if (diffDays >= 0) {
        badge = {color: "danger", texto: "Urgente"};
    } else {
        badge = {color: "dark", texto: "Vencida"};
    }

    return {
        texto: `<strong>Días para caducar:</strong> ${diffDays} días`,
        badge,
    };
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

function buildDerivacionMissingHtml(message = "Seguro particular: requiere autorización.") {
    return `
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex flex-column gap-2">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-secondary">Sin derivación</span>
                    <span class="text-muted">${escapeHtml(message)}</span>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnSolicitarAutorizacion">
                    Solicitar autorización
                </button>
            </div>
        </div>
    `;
}

function buildDerivacionDiagnosticoHtml(derivacion) {
    const diagnosticos = Array.isArray(derivacion?.diagnosticos)
        ? derivacion.diagnosticos
        : [];
    if (diagnosticos.length) {
        const items = diagnosticos
            .map((dx) => {
                const code = escapeHtml(dx?.dx_code || "");
                const descripcion = escapeHtml(
                    dx?.descripcion || dx?.diagnostico || ""
                );
                const lateralidad = dx?.lateralidad
                    ? ` (${escapeHtml(dx.lateralidad)})`
                    : "";
                return `<li><span class="text-primary">${code}</span> — ${descripcion}${lateralidad}</li>`;
            })
            .join("");
        return `<ul class="mb-0 mt-2">${items}</ul>`;
    }

    if (derivacion?.diagnostico) {
        const items = String(derivacion.diagnostico)
            .split(";")
            .map((item) => item.trim())
            .filter(Boolean)
            .map((item) => `<li>${escapeHtml(item)}</li>`)
            .join("");
        if (items) {
            return `<ul class="mb-0 mt-2">${items}</ul>`;
        }
    }

    return '<span class="text-muted">No disponible</span>';
}

function buildDerivacionHtml(derivacion) {
    if (!derivacion) {
        return buildDerivacionMissingHtml();
    }

    const derivacionId = derivacion.derivacion_id || derivacion.id || null;
    const archivoHref = derivacionId
        ? `/derivaciones/archivo/${encodeURIComponent(String(derivacionId))}`
        : derivacion.archivo_derivacion_path
            ? `/${String(derivacion.archivo_derivacion_path).replace(/^\/+/, "")}`
            : null;
    const vigenciaInfo = formatDerivacionVigencia(derivacion.fecha_vigencia);
    const badgeHtml = vigenciaInfo.badge
        ? `<span class="badge bg-${escapeHtml(vigenciaInfo.badge.color)} ms-2">${escapeHtml(
            vigenciaInfo.badge.texto
        )}</span>`
        : "";
    const archivoHtml = archivoHref
        ? `
        <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <strong>📎 Derivación:</strong>
                <span class="text-muted ms-1">Documento adjunto disponible.</span>
            </div>
            <a class="btn btn-sm btn-outline-primary mt-2 mt-md-0" href="${escapeHtml(
            archivoHref
        )}" target="_blank" rel="noopener">
                <i class="bi bi-file-earmark-pdf"></i> Abrir PDF
            </a>
        </div>
        `
        : "";

    return `
        ${archivoHtml}
        <div class="box box-outline-primary">
            <div class="box-header">
                <h5 class="box-title"><strong>📌 Información de la Derivación</strong></h5>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><i class="bi bi-upc-scan"></i> <strong>Código Derivación:</strong>
                    ${escapeHtml(derivacion.cod_derivacion || "No disponible")}
                </li>
                <li class="list-group-item"><i class="bi bi-calendar-check"></i> <strong>Fecha Registro:</strong>
                    ${escapeHtml(derivacion.fecha_registro || "No disponible")}
                </li>
                <li class="list-group-item"><i class="bi bi-calendar-event"></i> <strong>Fecha Vigencia:</strong>
                    ${escapeHtml(derivacion.fecha_vigencia || "No disponible")}
                </li>
                <li class="list-group-item">
                    <i class="bi bi-hourglass-split"></i> ${vigenciaInfo.texto}
                    ${badgeHtml}
                </li>
                <li class="list-group-item">
                    <i class="bi bi-clipboard2-pulse"></i>
                    <strong>Diagnóstico:</strong>
                    ${buildDerivacionDiagnosticoHtml(derivacion)}
                </li>
            </ul>
            <div class="box-body"></div>
        </div>
    `;
}

function renderDerivacionContent(container, payload) {
    if (!container) {
        return;
    }
    const hasDerivacion =
        payload?.success &&
        payload?.has_derivacion &&
        payload?.derivacion &&
        payload?.derivacion_status !== "missing";

    if (hasDerivacion) {
        container.innerHTML = buildDerivacionHtml(payload.derivacion);
        return;
    }

    const status = payload?.derivacion_status || "missing";
    const fallbackMessage =
        status === "error"
            ? "Derivación no disponible por ahora."
            : payload?.message || "Seguro particular: requiere autorización.";

    container.innerHTML = buildDerivacionMissingHtml(fallbackMessage);
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
    const notas = Number.parseInt(solicitud.crm_total_notas ?? 0, 10);
    const adjuntos = Number.parseInt(solicitud.crm_total_adjuntos ?? 0, 10);
    const tareasPendientes = Number.parseInt(
        solicitud.crm_tareas_pendientes ?? 0,
        10
    );
    const tareasTotal = Number.parseInt(solicitud.crm_tareas_total ?? 0, 10);

    return `
        <div class="prefactura-state-stat">
            <small class="text-muted d-block">Notas</small>
            <span class="fw-semibold">
                <i class="mdi mdi-note-text-outline me-1"></i>${escapeHtml(
        String(notas)
    )}
            </span>
        </div>
        <div class="prefactura-state-stat">
            <small class="text-muted d-block">Adjuntos</small>
            <span class="fw-semibold">
                <i class="mdi mdi-paperclip me-1"></i>${escapeHtml(
        String(adjuntos)
    )}
            </span>
        </div>
        <div class="prefactura-state-stat">
            <small class="text-muted d-block">Tareas abiertas</small>
            <span class="fw-semibold">
                <i class="mdi mdi-format-list-checks me-1"></i>
                <span id="prefacturaStateTasksOpen">${escapeHtml(
        String(tareasPendientes)
    )}</span>/<span id="prefacturaStateTasksTotal">${escapeHtml(
        String(tareasTotal)
    )}</span>
            </span>
        </div>
    `;
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

function renderGridItem(label, value, helper = "", valueClass = "", valueId = "") {
    if (!value) {
        value = "—";
    }
    const helperHtml = helper
        ? `<span class="text-muted small d-block mt-1">${escapeHtml(helper)}</span>`
        : "";
    const className = valueClass ? ` ${valueClass}` : "";
    const idAttr = valueId ? ` id="${escapeHtml(valueId)}"` : "";
    return `
        <div class="prefactura-state-grid-item">
            <small>${escapeHtml(label)}</small>
            <strong class="prefactura-state-value${className}"${idAttr}>${escapeHtml(
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
    return (
        store.find(
            (item) =>
                String(item.id) === String(id) ||
                String(item.form_id) === String(id)
        ) || null
    );
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
    const placeholder = document.getElementById("prefacturaStatePlaceholder");
    if (placeholder) {
        placeholder.classList.remove("d-none");
    }
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
        renderGridItem(
            "Próximo vencimiento",
            proximoVencimiento,
            "",
            "",
            "prefacturaStateNextDue"
        ),
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
    const placeholder = document.getElementById("prefacturaStatePlaceholder");
    if (placeholder) {
        placeholder.classList.add("d-none");
    }
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
    const responsable =
        solicitud.doctor || solicitud.crm_responsable_nombre || "Sin responsable";
    const procedimiento = solicitud.procedimiento || "Sin procedimiento";
    const afiliacion =
        solicitud.afiliacion || solicitud.aseguradora || "Sin afiliación";
    const hcNumber = solicitud.hc_number || "—";

    const badges = [
        `<span class="badge bg-light text-dark border">HC ${escapeHtml(
            hcNumber
        )}</span>`,
        `<span class="badge bg-light text-dark border">${escapeHtml(
            afiliacion
        )}</span>`,
    ];

    if (turno) {
        badges.push(
            `<span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">Turno ${escapeHtml(
                turno
            )}</span>`
        );
    }

    container.innerHTML = `
        <div class="card shadow-sm border-0 prefactura-patient-summary">
            <div class="card-body p-3">
                <div class="d-flex flex-column flex-md-row gap-3 align-items-start align-items-md-center">
                    <div class="d-flex align-items-start gap-3 flex-grow-1">
                        <div class="prefactura-avatar rounded-circle bg-light text-primary d-flex align-items-center justify-content-center">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <div>
                            <div class="prefactura-patient-name fw-bold">
                                ${escapeHtml(solicitud.full_name || "Sin nombre")}
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                ${badges.join("")}
                            </div>
                        </div>
                    </div>
                    <div class="flex-grow-1 text-md-end">
                        <div>
                            <p class="prefactura-meta-label mb-1">Responsable</p>
                            <div class="fw-semibold">${escapeHtml(responsable)}</div>
                        </div>
                        <div class="mt-2">
                            <p class="prefactura-meta-label mb-1">Procedimiento</p>
                            <div class="prefactura-line-clamp">${escapeHtml(
        procedimiento
    )}</div>
                        </div>
                    </div>
                </div>
            </div>
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

    if (patientAlert) {
        patientAlert.remove();
    }

    renderPatientSummaryFallback(solicitudId);
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

function resolveApiBasePath() {
    const {apiBasePath} = getKanbanConfig();
    const fallback = "/api";
    if (!apiBasePath) {
        return fallback;
    }
    const normalized = apiBasePath.replace(/\/+$/, "");
    return normalized.startsWith("/") ? normalized : `/${normalized}`;
}

function buildEstadoApiCandidates() {
    const {basePath} = getKanbanConfig();
    const normalizedBase =
        basePath && basePath !== "/" ? basePath.replace(/\/+$/, "") : "";
    const apiBase = resolveApiBasePath();
    const orderedCandidates = [
        "/solicitudes/api/estado",
        `${apiBase}/solicitudes/estado`,
        "/api/solicitudes/estado",
    ];
    if (normalizedBase) {
        orderedCandidates.push(`${normalizedBase}/api/estado`);
    }

    const expanded = [];
    const seen = new Set();
    orderedCandidates.forEach((candidate) => {
        buildApiCandidates(candidate).forEach((url) => {
            if (!seen.has(url)) {
                seen.add(url);
                expanded.push(url);
            }
        });
    });

    return expanded;
}

function buildGuardarSolicitudInternalCandidates({solicitudId} = {}) {
    const {basePath} = getKanbanConfig();
    const normalizedBase =
        basePath && basePath !== "/" ? basePath.replace(/\/+$/, "") : "";

    const sid = solicitudId ? encodeURIComponent(String(solicitudId)) : "";

    // ✅ Endpoint interno del módulo (requiere sesión)
    // Ruta canónica: POST /solicitudes/{id}/cirugia
    // Nota: en muchos entornos `basePath` ya es "/solicitudes" (se usa para /prefactura, /derivacion, etc.)
    // Por eso evitamos construir "/solicitudes/solicitudes/...".

    const candidates = [];

    if (!sid) {
        return candidates;
    }

    // 1) Canónica absoluta
    candidates.push(`/solicitudes/${sid}/cirugia`);

    // 2) Si el basePath existe y NO es ya "/solicitudes", construir prefijo + "/solicitudes/..."
    if (normalizedBase && normalizedBase !== "/solicitudes") {
        candidates.push(`${normalizedBase}/solicitudes/${sid}/cirugia`);
    }

    // 3) Si el basePath existe y YA es "/solicitudes", construir simplemente basePath + "/{id}/cirugia"
    if (normalizedBase === "/solicitudes") {
        candidates.push(`${normalizedBase}/${sid}/cirugia`);
    }

    // Fallback opcional: endpoint CRM (solo si backend lo soporta)
    candidates.push(`/solicitudes/${sid}/crm`);
    if (normalizedBase && normalizedBase !== "/solicitudes") {
        candidates.push(`${normalizedBase}/solicitudes/${sid}/crm`);
    }
    if (normalizedBase === "/solicitudes") {
        candidates.push(`${normalizedBase}/${sid}/crm`);
    }

    // Filtrar duplicados preservando orden
    return Array.from(new Set(candidates.filter(Boolean)));
}

function clearSolicitudDetalleCacheBySolicitudId(solicitudId) {
    if (!solicitudId) return;
    const sid = String(solicitudId);
    for (const key of Array.from(solicitudDetalleCache.keys())) {
        // keys are like: hc:solicitudId:formId (some parts may be missing)
        if (key.split(":").includes(sid)) {
            solicitudDetalleCache.delete(key);
        }
    }
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
            const safeOptions = {
                credentials: options?.credentials ?? "same-origin",
                ...options,
            };
            const response = await fetch(url, safeOptions);
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

    const urls = buildEstadoApiCandidates().map(
        (base) => `${base}?${searchParams}`
    );
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

async function refreshKanbanBadgeFromDetalle({hcNumber, solicitudId, formId}) {
    if (!solicitudId && !formId && !hcNumber) {
        return;
    }

    try {
        const detalle = await fetchDetalleSolicitud({
            hcNumber,
            solicitudId,
            formId,
        });
        const store = getDataStore();
        const target = Array.isArray(store)
            ? store.find((item) => String(item.id) === String(solicitudId))
            : null;
        if (target && typeof target === "object") {
            Object.assign(target, detalle);
        }
        updateKanbanCardSla(detalle);
    } catch (error) {
        console.warn("No se pudo refrescar badge SLA", error);
    }
}

async function hydrateSolicitudFromDetalle({solicitudId, formId, hcNumber}) {
    const base = findSolicitudById(solicitudId) || {};
    if (!hcNumber && !base.hc_number) {
        return base;
    }

    if (base.detalle_hidratado) {
        return base;
    }

    try {
        const detalle = await fetchDetalleSolicitud({
            hcNumber: hcNumber || base.hc_number,
            solicitudId,
            formId: formId || base.form_id,
        });

        const merged = {...base, ...detalle, detalle_hidratado: true};
        const store = getDataStore();
        const target = store.find((item) => String(item.id) === String(solicitudId));
        if (target && typeof target === "object") {
            Object.assign(target, merged, {detalle_hidratado: true});
        }
        return merged;
    } catch (error) {
        console.warn("No se pudo hidratar solicitud con detalle", error);
        return base;
    }
}

async function loadSolicitudCore({hc, formId, solicitudId}) {
    const {basePath} = getKanbanConfig();
    const prefacturaUrl = `${basePath}/prefactura?hc_number=${encodeURIComponent(
        hc
    )}&form_id=${encodeURIComponent(formId)}&solicitud_id=${encodeURIComponent(
        solicitudId
    )}`;

    const [html, solicitud] = await Promise.all([
        fetch(prefacturaUrl).then((response) => {
            if (!response.ok) {
                throw new Error("No se encontró la prefactura");
            }
            return response.text();
        }),
        hydrateSolicitudFromDetalle({solicitudId, formId, hcNumber: hc}),
    ]);

    return {html, solicitud};
}

async function loadDerivacion({hc, formId}) {
    const {basePath} = getKanbanConfig();
    const derivacionUrl = `${basePath}/derivacion?hc_number=${encodeURIComponent(
        hc
    )}&form_id=${encodeURIComponent(formId)}`;

    try {
        const response = await fetch(derivacionUrl);
        if (!response.ok) {
            return {
                success: true,
                has_derivacion: false,
                derivacion_status: "error",
                derivacion: null,
            };
        }
        return response.json();
    } catch (error) {
        return {
            success: true,
            has_derivacion: false,
            derivacion_status: "error",
            derivacion: null,
        };
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

    syncPrefacturaContext({formId, hcNumber: hc, solicitudId});
    parkExamenesPrequirurgicosButton(modalElement);

    content.innerHTML = `
        <div class="d-flex align-items-center justify-content-center py-5">
            <div class="spinner-border text-primary me-2" role="status" aria-hidden="true"></div>
            <strong>Cargando información...</strong>
        </div>
    `;

    modal.show();
    actualizarBotonesModal(solicitudId);

    const corePromise = loadSolicitudCore({hc, formId, solicitudId});
    const derivacionPromise = loadDerivacion({hc, formId});

    Promise.allSettled([corePromise, derivacionPromise]).then(
        ([coreResult, derivacionResult]) => {
            if (coreResult.status !== "fulfilled") {
                console.error("❌ Error cargando prefactura:", coreResult.reason);
                content.innerHTML =
                    '<p class="text-danger mb-0">No se pudo cargar la información de la solicitud.</p>';
                return;
            }

            const {html, solicitud} = coreResult.value;
            const contextual = buildContextualActionsHtml(solicitud || {});
            content.innerHTML = `${contextual}${html}`;
            updateKanbanCardSla(solicitud);
            relocatePatientAlert(solicitudId);
            renderEstadoContext(solicitudId);
            actualizarBotonesModal(solicitudId, solicitud);
            attachPrefacturaCoberturaMail();

            const actionsContainer = document.getElementById(
                "prefacturaContextualActions"
            );
            if (actionsContainer) {
                const panels = content.querySelectorAll(
                    "#prefacturaAnestesiaPanel, #prefacturaAgendaPanel"
                );
                panels.forEach((panel) => actionsContainer.appendChild(panel));
            }

            relocateExamenesPrequirurgicosButton(content);

            const header = content.querySelector(".prefactura-detail-header");
            const tabs = content.querySelector("#prefacturaTabs");
            if (header && tabs) {
                const headerHeight = Math.ceil(
                    header.getBoundingClientRect().height
                );
                tabs.style.setProperty(
                    "--prefactura-header-height",
                    `${headerHeight}px`
                );
            }

            const derivacionContainer = content.querySelector(
                "#prefacturaDerivacionContent"
            );
            if (derivacionResult.status === "fulfilled") {
                renderDerivacionContent(derivacionContainer, derivacionResult.value);
            } else {
                console.warn(
                    "⚠️ Derivación no disponible:",
                    derivacionResult.reason
                );
                if (derivacionContainer) {
                    derivacionContainer.innerHTML = buildDerivacionMissingHtml();
                }
            }

            refreshKanbanBadgeFromDetalle({
                hcNumber: hc,
                solicitudId,
                formId,
            });
        }
    );

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

function relocateExamenesPrequirurgicosButton(content) {
    const button = document.getElementById("btnSolicitarExamenesPrequirurgicos");
    if (!button || !content) {
        return;
    }

    const targetFooter = content.querySelector(
        "#prefactura-tab-oftalmo .card:first-of-type .card-footer"
    );

    if (targetFooter) {
        button.classList.add("ms-2");
        button.classList.remove("d-none");
        targetFooter.appendChild(button);
        return;
    }

    button.classList.remove("d-none");
    content.prepend(button);
}

function parkExamenesPrequirurgicosButton(modalElement) {
    const button = document.getElementById("btnSolicitarExamenesPrequirurgicos");
    const footer = modalElement?.querySelector(".modal-footer");
    if (!button || !footer) {
        return;
    }

    button.classList.add("d-none");
    footer.appendChild(button);
}

function actualizarBotonesModal(solicitudId, solicitudFallback = null) {
    const solicitud = findSolicitudById(solicitudId) || solicitudFallback;
    const normalize = (v) =>
        (v ?? "")
            .toString()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, "-");

    const estado = solicitud
        ? normalize(solicitud.estado || solicitud.kanban_estado)
        : "";

    const btnGenerarTurno = document.getElementById("btnGenerarTurnoModal");
    const btnEnAtencion = document.getElementById("btnMarcarAtencionModal");
    const btnRevisar = document.getElementById("btnRevisarCodigos");
    const btnCobertura = document.getElementById("btnSolicitarCobertura");
    const btnCoberturaExitosa = document.getElementById("btnCoberturaExitosa");

    const show = (el, visible) => {
        if (!el) return;
        el.classList.toggle("d-none", !visible);
    };

    const canShow = Boolean(estado);

    show(btnGenerarTurno, canShow && estado === "recibida");
    show(btnEnAtencion, canShow && estado === "llamado");
    show(btnRevisar, canShow && estado === "revision-codigos");
    show(
        btnCobertura,
        canShow &&
        (estado === "recibida" ||
            estado === "en-atencion" ||
            estado === "revision-codigos" ||
            estado === "espera-documentos")
    );
    show(
        btnCoberturaExitosa,
        canShow && (estado === "en-atencion" || estado === "revision-codigos")
    );
    console.log("[botones] estado raw:", solicitud?.estado || solicitud?.kanban_estado);
    console.log("[botones] estado normalized:", estado);
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
                    <input id="sol-estado" name="estado" class="form-control" value="${escapeHtml(
                    merged.estado || merged.kanban_estado || ""
                )}" placeholder="Estado" readonly />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Doctor</label>
                      <select id="sol-doctor" name="doctor" class="form-select">
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
                      <input id="sol-fecha" name="fecha" type="datetime-local" class="form-control" value="${escapeHtml(
                    toDatetimeLocal(merged.fecha || merged.fecha_programada)
                )}" />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Prioridad</label>
                      <input id="sol-prioridad" name="prioridad" class="form-control" value="${escapeHtml(
                    merged.prioridad || merged.prioridad_automatica || "Normal"
                )}" placeholder="URGENTE / NORMAL" readonly />
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Producto</label>
                      <input id="sol-producto" name="producto" class="form-control" value="${escapeHtml(
                    baseProducto
                )}" placeholder="Producto asociado" />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Ojo</label>
                      <select id="sol-ojo" name="ojo" class="form-select">
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
                      <input id="sol-afiliacion" name="afiliacion" class="form-control" value="${escapeHtml(
                    merged.afiliacion || ""
                )}" placeholder="Afiliación" readonly />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Duración</label>
                      <input id="sol-duracion" name="duracion" class="form-control" value="${escapeHtml(
                    merged.duracion || ""
                )}" placeholder="Minutos" readonly />
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Procedimiento</label>
                  <textarea id="sol-procedimiento" name="procedimiento" class="form-control" rows="2" placeholder="Descripción">${escapeHtml(
                    merged.procedimiento || ""
                )}</textarea>
                </div>
                <div class="form-group">
                  <label class="form-label">Observación</label>
                  <textarea id="sol-observacion" name="observacion" class="form-control" rows="2" placeholder="Notas">${escapeHtml(
                    merged.observacion || ""
                )}</textarea>
                </div>

                <h4 class="box-title text-info mb-0 mt-20"><i class="ti-save me-15"></i> Lente e incisión</h4>
                <hr class="my-15">
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Lente</label>
                      <select id="sol-lente-id" name="lente_id" class="form-select" data-value="${escapeHtml(
                    lenteSeleccionada
                )}">
                        <option value="">Cargando lentes...</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Nombre de lente</label>
                      <input id="sol-lente-nombre" name="lente_nombre" class="form-control" value="${escapeHtml(
                    merged.lente_nombre || baseProducto
                )}" placeholder="Nombre del lente" />
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Poder del lente</label>
                      <select id="sol-lente-poder" name="lente_poder" class="form-select" data-value="${escapeHtml(
                    poderSeleccionado
                )}">
                        <option value="">Selecciona poder</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Incisión</label>
                      <input id="sol-incision" name="incision" class="form-control" value="${escapeHtml(
                    merged.incision || ""
                )}" placeholder="Ej: Clear cornea temporal" />
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Observación de lente</label>
                  <textarea id="sol-lente-obs" name="lente_observacion" class="form-control" rows="2" placeholder="Notas de lente">${escapeHtml(
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
                    showLoaderOnConfirm: true,
                    allowOutsideClick: () => !Swal.isLoading(),
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
                    preConfirm: async () => {
                        // Campos del formulario
                        const producto =
                            document.getElementById("sol-producto")?.value.trim() || "";
                        const poder =
                            document.getElementById("sol-lente-poder")?.value.trim() || "";
                        const lenteId =
                            document.getElementById("sol-lente-id")?.value.trim() || "";
                        const lenteNombre =
                            document.getElementById("sol-lente-nombre")?.value.trim() || producto;
                        const ojo = document.getElementById("sol-ojo")?.value.trim() || "";
                        const incision =
                            document.getElementById("sol-incision")?.value.trim() || "";
                        const lenteObservacion =
                            document.getElementById("sol-lente-obs")?.value.trim() || "";
                        const observacion =
                            document.getElementById("sol-observacion")?.value.trim() || "";
                        const procedimiento =
                            document.getElementById("sol-procedimiento")?.value.trim() || "";
                        const fecha = document.getElementById("sol-fecha")?.value.trim() || "";
                        const doctor = document.getElementById("sol-doctor")?.value.trim() || "";

                        // Usar el id real del registro (detalle) si existe.
                        // En algunos flujos el dataset id puede no coincidir con el id de solicitud_procedimiento.
                        const targetSolicitudId =
                            merged?.id ? String(merged.id) : String(solicitudId || "");

                        // ✅ Guardado interno (módulo Solicitudes): endpoint con sesión.
                        // Recomendado: POST /solicitudes/{id}/cirugia
                        // Payload mínimo: { form_id, hc_number, updates: {...} }

                        const payload = {
                            solicitud_id: targetSolicitudId,
                            form_id: formId || merged.form_id || solicitud.form_id || "",
                            hc_number: hcNumber || merged.hc_number || solicitud.hc_number || "",
                            updates: {
                                doctor,
                                fecha,
                                ojo,
                                procedimiento,
                                observacion,
                                // Detalles quirúrgicos (LIO / incisión)
                                lente_id: lenteId,
                                lente_nombre: lenteNombre,
                                lente_poder: poder,
                                lente_observacion: lenteObservacion,
                                incision,
                                // Campo resumen
                                producto: lenteNombre || producto,
                            },
                        };

                        if (!payload.hc_number || !payload.form_id) {
                            Swal.showValidationMessage(
                                "Faltan datos para guardar (hc_number / form_id)."
                            );
                            return false;
                        }

                        try {
                            const postUrls = buildGuardarSolicitudInternalCandidates({solicitudId: targetSolicitudId});
                            console.log("[guardarCirugia] candidates:", postUrls);
                            console.log("[guardarCirugia] payload:", payload);
                            // Siempre con sesión para endpoints internos
                            const response = await fetchWithFallback(postUrls, {
                                method: "POST",
                                credentials: "include",
                                headers: {"Content-Type": "application/json"},
                                body: JSON.stringify(payload),
                            });
                            console.log("[guardarCirugia] response ok/status:", response.ok, response.status);

                            let resp = null;
                            const ct = response.headers.get("content-type") || "";
                            if (ct.includes("application/json")) {
                                resp = await response.json();
                            } else {
                                const raw = await response.text();
                                try {
                                    resp = JSON.parse(raw);
                                } catch (e) {
                                    resp = {success: response.ok, message: raw};
                                }
                            }

                            if (!resp?.success) {
                                throw new Error(resp?.message || "No se guardaron los cambios");
                            }

                            return {payload, response: resp, targetSolicitudId};
                        } catch (error) {
                            console.error("No se pudo guardar la solicitud", error);
                            Swal.showValidationMessage(
                                error?.message || "Error al guardar la solicitud"
                            );
                            return false;
                        }
                    },
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const targetSolicitudId = result.value?.targetSolicitudId || solicitudId;
                    const payload = result.value?.payload || {};
                    const responseData = result.value?.response?.data || null;
                    const store = getDataStore();
                    const item = store.find(
                        (entry) => String(entry.id) === String(targetSolicitudId)
                    );
                    if (item) {
                        // Refrescar campos visibles y detalles del lente
                        const updated = payload?.updates || {};
                        Object.assign(
                            item,
                            {
                                doctor: updated.doctor ?? item.doctor,
                                fecha: updated.fecha ?? item.fecha,
                                ojo: updated.ojo ?? item.ojo,
                                producto: updated.producto ?? item.producto,
                                procedimiento: updated.procedimiento ?? item.procedimiento,
                                observacion: updated.observacion ?? item.observacion,
                                // LIO
                                lente_id: updated.lente_id ?? item.lente_id,
                                lente_nombre: updated.lente_nombre ?? item.lente_nombre,
                                lente_poder: updated.lente_poder ?? item.lente_poder,
                                lente_observacion:
                                    updated.lente_observacion ?? item.lente_observacion,
                                incision: updated.incision ?? item.incision,
                            },
                            responseData || {}
                        );
                        delete item.detalle_hidratado;
                    }
                    clearSolicitudDetalleCacheBySolicitudId(targetSolicitudId);
                    showToast("Solicitud actualizada", true);
                    renderEstadoContext(targetSolicitudId);
                    if (typeof window.aplicarFiltros === "function") {
                        window.aplicarFiltros();
                    }
                    // Evitar re-abrir el modal si ya está visible (puede dejar backdrops y congelar la UI).
                    const prefacturaModalEl = document.getElementById("prefacturaModal");
                    const modalIsOpen = prefacturaModalEl?.classList?.contains("show");

                    // Refrescar badges/detalle desde backend cuando sea posible
                    refreshKanbanBadgeFromDetalle({
                        hcNumber: hcNumber || solicitud.hc_number,
                        solicitudId: targetSolicitudId,
                        formId,
                    });

                    if (!modalIsOpen) {
                        abrirPrefactura({
                            hc: hcNumber || solicitud.hc_number,
                            formId,
                            solicitudId: targetSolicitudId,
                        });
                    }
                });
            })
            .catch((error) => {
                console.error("No se pudo cargar el editor de LIO", error);
                showToast("No pudimos obtener los datos del lente", false);
            });
    }
}

async function handleRescrapeDerivacion(event) {
    const button = event.target.closest("#btnRescrapeDerivacion");
    if (!button) {
        return;
    }

    const {formId, hcNumber, solicitudId} = getPrefacturaContextFromButton(button);
    if (!formId || !hcNumber) {
        showToast("Faltan datos (form_id / hc_number) para re-scrapear", false);
        return;
    }

    const defaultLabel = button.dataset.defaultLabel || button.textContent.trim();
    button.dataset.defaultLabel = defaultLabel;
    button.disabled = true;
    button.textContent = "⏳ Re-scrapeando…";

    try {
        const response = await fetch("/solicitudes/re-scrape-derivacion", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({form_id: formId, hc_number: hcNumber}),
            credentials: "include",
        });

        let data = null;
        let rawText = "";
        const contentType = response.headers.get("content-type") || "";
        if (contentType.includes("application/json")) {
            data = await response.json();
        } else {
            rawText = await response.text();
            try {
                data = JSON.parse(rawText);
            } catch (error) {
                data = null;
            }
        }

        if (!response.ok || !data?.success) {
            const errorMessage =
                data?.message ||
                data?.error ||
                "No se pudo re-scrapear la derivación";
            showToast(errorMessage, false);
            return;
        }

        showToast(
            data?.saved
                ? "Derivación actualizada correctamente"
                : "Scraping completado, sin cambios guardados",
            true
        );

        if (solicitudId) {
            clearSolicitudDetalleCacheBySolicitudId(solicitudId);
            const store = getDataStore();
            const item = Array.isArray(store)
                ? store.find((entry) => String(entry.id) === String(solicitudId))
                : null;
            if (item && typeof item === "object") {
                delete item.detalle_hidratado;
            }
        }
        if (typeof window.aplicarFiltros === "function") {
            window.aplicarFiltros();
        }
        abrirPrefactura({hc: hcNumber, formId, solicitudId});
    } catch (error) {
        console.error("Error re-scrapeando derivación", error);
        showToast(
            error?.message || "No se pudo re-scrapear la derivación",
            false
        );
    } finally {
        button.disabled = false;
        button.textContent = button.dataset.defaultLabel || "🔄 Re-scrapear derivación";
    }
}

export function inicializarModalDetalles() {
    if (prefacturaListenerAttached) {
        return;
    }

    prefacturaListenerAttached = true;
    document.addEventListener("click", handlePrefacturaClick);
    document.addEventListener("click", handleContextualAction);
    document.addEventListener("click", handleRescrapeDerivacion);
}
