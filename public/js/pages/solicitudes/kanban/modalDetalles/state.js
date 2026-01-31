import {getKanbanConfig} from "../config.js";
import {formatTurno} from "../turnero.js";
import {ALERT_TEMPLATES, PATIENT_ALERT_TEXT} from "./constants.js";
import {
    buildSlaInfo,
    escapeHtml,
    formatIsoDate,
    getEstadoBadge,
} from "./utils.js";
import {findSolicitudById} from "./store.js";

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

export function buildContextualActionsHtml(solicitud = {}) {
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

export function resetEstadoContext() {
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

export function resetPatientSummary() {
    const container = document.getElementById("prefacturaPatientSummary");
    if (!container) {
        return;
    }
    container.innerHTML = "";
    container.classList.add("d-none");
    syncQuickColumnVisibility();
}

export function renderEstadoContext(solicitudId) {
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

export function renderPatientSummaryFallback(solicitudId) {
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

export function relocatePatientAlert(solicitudId) {
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
