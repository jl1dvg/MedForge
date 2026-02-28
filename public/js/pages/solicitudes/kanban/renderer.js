import {showToast} from "./toast.js";
import {llamarTurnoSolicitud, formatTurno} from "./turnero.js";
import {getDataStore} from "./config.js";
import {actualizarEstadoSolicitud} from "./estado.js";

const ESCAPE_MAP = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
    "`": "&#96;",
};

function escapeHtml(value) {
    if (value === null || value === undefined) {
        return "";
    }

    return String(value).replace(
        /[&<>"'`]/g,
        (character) => ESCAPE_MAP[character]
    );
}

function truncateText(value, max = 90) {
    const text = (value ?? "").toString().trim();
    if (!text) {
        return "";
    }
    if (text.length <= max) {
        return text;
    }
    return text.slice(0, Math.max(0, max - 1)).trimEnd() + "…";
}

function humanizeProcedureShort(value) {
    const text = (value ?? "").toString().trim();
    if (!text) {
        return "";
    }

    // Prefer to keep code-like tokens (e.g. 66984) and the first meaningful phrase.
    // Common format: "CIRUGIAS - 66984 - ..."
    const parts = text.split("-").map((p) => p.trim()).filter(Boolean);
    if (parts.length >= 3) {
        const codeCandidate = parts.find((p) => /^\d{4,6}$/.test(p)) || "";
        const rest = parts.slice(parts.indexOf(codeCandidate) + 1).join(" - ").trim();
        const shortRest = truncateText(rest, 70);
        return [codeCandidate, shortRest].filter(Boolean).join(" · ");
    }

    return truncateText(text, 90);
}

function safeDomId(value) {
    return (value ?? "")
        .toString()
        .trim()
        .replace(/[^a-zA-Z0-9_-]+/g, "-")
        .replace(/^-+|-+$/g, "");
}

function getInitials(nombre) {
    if (!nombre) {
        return "—";
    }

    const parts = nombre.replace(/\s+/g, " ").trim().split(" ").filter(Boolean);

    if (!parts.length) {
        return "—";
    }

    if (parts.length === 1) {
        return parts[0].substring(0, 2).toUpperCase();
    }

    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function renderAvatar(nombreResponsable, avatarUrl) {
    const nombre = nombreResponsable || "";
    const alt = nombre !== "" ? nombre : "Responsable sin asignar";
    const initials = escapeHtml(getInitials(nombre || ""));

    if (avatarUrl) {
        return `
            <div class="kanban-avatar" data-avatar-root>
                <img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(
            alt
        )}" loading="lazy" data-avatar-img>
                <div class="kanban-avatar__placeholder d-none" data-avatar-placeholder>
                    <span>${initials}</span>
                </div>
            </div>
        `;
    }

    return `
        <div class="kanban-avatar kanban-avatar--placeholder" data-avatar-root>
            <div class="kanban-avatar__placeholder" data-avatar-placeholder>
                <span>${initials}</span>
            </div>
        </div>
    `;
}

function hydrateAvatar(container) {
    container
        .querySelectorAll(".kanban-avatar[data-avatar-root]")
        .forEach((avatar) => {
            const img = avatar.querySelector("[data-avatar-img]");
            const placeholder = avatar.querySelector("[data-avatar-placeholder]");

            if (!placeholder) {
                return;
            }

            if (!img) {
                placeholder.classList.remove("d-none");
                avatar.classList.add("kanban-avatar--placeholder");
                return;
            }

            const showPlaceholder = () => {
                placeholder.classList.remove("d-none");
                avatar.classList.add("kanban-avatar--placeholder");
                if (img.parentElement === avatar) {
                    img.remove();
                }
            };

            img.addEventListener("error", showPlaceholder, {once: true});

            if (img.complete && img.naturalWidth === 0) {
                showPlaceholder();
            }
        });
}

function slugifyEstado(value) {
    return (value ?? "")
        .toString()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
}

function estadoLabelFromSlug(slug) {
    const meta = window.__solicitudesEstadosMeta ?? {};
    if (!slug) {
        return "Sin estado";
    }

    const key = slugifyEstado(slug);
    const entry = meta[key] || meta[slug];
    if (entry && entry.label) {
        return entry.label;
    }

    return slug;
}

function formatBadge(label, value, icon) {
    const safeValue = escapeHtml(value ?? "");
    if (!safeValue) {
        return "";
    }

    const safeLabel = escapeHtml(label ?? "");
    const safeIcon = icon ? `${icon} ` : "";

    return `<span class="badge">${safeIcon}${
        safeLabel !== "" ? `${safeLabel}: ` : ""
    }${safeValue}</span>`;
}

const TURNO_BUTTON_LABELS = {
    recall: {
        html: '<i class="mdi mdi-phone-incoming"></i>',
        title: 'Volver a llamar',
    },
    generate: {
        html: '<i class="mdi mdi-bell-ring-outline"></i>',
        title: 'Generar turno',
    },
};

function normalizarEstado(value) {
    return slugifyEstado(value);
}

function applyTurnoButtonState(button, shouldRecall) {
    if (!button) return;

    const cfg = shouldRecall
        ? TURNO_BUTTON_LABELS.recall
        : TURNO_BUTTON_LABELS.generate;

    // Icon-only label + accessibility
    button.innerHTML = cfg.html;
    button.title = cfg.title;
    button.setAttribute('aria-label', cfg.title);

    // Encode state for other logic
    button.dataset.hasTurno = shouldRecall ? "1" : "0";

    // Visual state by color (turno already assigned / recall)
    button.classList.remove('btn-outline-primary', 'btn-outline-warning', 'btn-outline-secondary');
    button.classList.add(shouldRecall ? 'btn-outline-warning' : 'btn-outline-primary');

    // Keep compact icon-button styling
    button.classList.add('btn-icon');
}

function announceTurno(nombre, { force = false } = {}) {
    if (typeof window === 'undefined') {
        return;
    }
    if (typeof window.playCallTone === 'function') {
        window.playCallTone({ force });
    }
    if (typeof window.speakNameForItem === 'function') {
        window.speakNameForItem({ full_name: nombre }, { force, reason: 'call' });
    }
}

const request = window.request || (async function request(url, options = {}) {
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

    const response = await fetch(url, config);
    const data = await response.json().catch(() => ({}));

    if (!response.ok || data.ok === false) {
        throw new Error(data.error || "No se pudo completar la solicitud");
    }

    return data;
}); // shared helper

function normalizeEyeValue(value) {
    if (!value) {
        return null;
    }
    const normalized = value.toString().toLowerCase();
    if (normalized.includes('ao') || normalized.includes('ambos')) {
        return 'AO';
    }
    if (normalized.includes('od') || normalized.includes('derecho')) {
        return 'OD';
    }
    if (normalized.includes('oi') || normalized.includes('izquierdo')) {
        return 'OI';
    }
    return null;
}

async function openProjectForSolicitud(solicitud, button) {
    const formId = solicitud?.form_id ?? null;
    const hcNumber = solicitud?.hc_number ?? null;
    const procedimiento = solicitud?.procedimiento ?? 'Caso';
    const eye = normalizeEyeValue(solicitud?.ojo ?? solicitud?.lateralidad ?? null);

    if (!formId && !hcNumber) {
        showToast('No hay información suficiente para crear el caso', false);
        return;
    }

    const titleParts = [
        formId ? `Solicitud ${formId}` : 'Solicitud',
        hcNumber ? `HC ${hcNumber}` : null,
        procedimiento ? procedimiento : null,
    ].filter(Boolean);

    const payload = {
        title: titleParts.join(' - '),
        hc_number: hcNumber,
        form_id: formId,
        source_module: 'solicitudes',
        source_ref_id: solicitud?.id ? String(solicitud.id) : (formId ? String(formId) : null),
        episode_type: 'cirugia',
        eye: eye,
    };

    if (button) {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    }

    try {
        const data = await request('/projects/create', {
            method: 'POST',
            body: payload,
        });
        const projectId = data?.data?.id;
        showToast(data.linked ? 'Caso vinculado' : 'Caso creado', true);
        if (projectId) {
            window.open(`/crm?tab=projects&project_id=${projectId}`, '_blank', 'noopener');
        }
    } catch (error) {
        console.error('Error creando caso', error);
        showToast(error.message || 'No se pudo crear el caso', false);
    } finally {
        if (button) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    }
}

const SLA_META = {
    en_rango: {
        label: "OK",
        badgeClass: "badge-sla badge bg-success text-white",
        icon: "mdi-check-circle-outline",
    },
    advertencia: {
        label: "72h",
        badgeClass: "badge-sla badge bg-warning text-dark",
        icon: "mdi-timer-sand",
    },
    critico: {
        label: "24h",
        badgeClass: "badge-sla badge bg-danger",
        icon: "mdi-alert-octagon",
    },
    vencido: {
        label: "Venc",
        badgeClass: "badge-sla badge bg-dark",
        icon: "mdi-alert",
    },
    sin_fecha: {
        label: "S/F",
        badgeClass: "badge-sla badge bg-secondary",
        icon: "mdi-calendar-question",
    },
    cerrado: {
        label: "Cerr",
        badgeClass: "badge-sla badge bg-secondary",
        icon: "mdi-lock-outline",
    },
};

const PRIORIDAD_META = {
    urgente: {
        label: "Urgente",
        badgeClass: "badge bg-danger text-white",
        icon: "mdi-flash-alert",
    },
    pendiente: {
        label: "Pendiente",
        badgeClass: "badge bg-warning text-dark",
        icon: "mdi-progress-clock",
    },
    normal: {
        label: "Normal",
        badgeClass: "badge bg-success text-white",
        icon: "mdi-check",
    },
};

function getSlaMeta(status) {
    const normalized = (status || "").toString().trim().toLowerCase();
    return SLA_META[normalized] || SLA_META.sin_fecha;
}

function parseDateValue(value) {
    if (!value) {
        return null;
    }

    const raw = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(raw.getTime())) {
        return null;
    }

    return raw;
}

function resolveSlaStatus(item = {}) {
    const normalized = (item?.sla_status || "").toString().trim().toLowerCase();
    if (normalized !== "vencido") {
        return normalized;
    }

    const derivacionVigencia = parseDateValue(item?.derivacion_fecha_vigencia);
    if (!derivacionVigencia) {
        return normalized;
    }

    return derivacionVigencia.getTime() >= Date.now() ? "en_rango" : normalized;
}

export function updateKanbanCardSla(solicitud = {}) {
    if (!solicitud) {
        return;
    }
    if (
        solicitud.sla_status === undefined ||
        solicitud.sla_status === null ||
        String(solicitud.sla_status).trim() === ""
    ) {
        return;
    }
    const id = solicitud.id ?? solicitud.solicitud_id ?? null;
    if (!id) {
        return;
    }
    const safeId =
        typeof CSS !== "undefined" && typeof CSS.escape === "function"
            ? CSS.escape(String(id))
            : String(id).replace(/"/g, '\\"');
    const card = document.querySelector(`.kanban-card[data-id="${safeId}"]`);
    if (!card) {
        return;
    }
    const badge = card.querySelector(".badge-sla");
    if (!badge) {
        return;
    }
    const slaMeta = getSlaMeta(resolveSlaStatus(solicitud));
    badge.className = slaMeta.badgeClass;
    badge.innerHTML = `<i class="mdi ${escapeHtml(
        slaMeta.icon
    )} me-1"></i>${escapeHtml(slaMeta.label)}`;
}

function getPrioridadMeta(priority) {
    const normalized = (priority || "").toString().trim().toLowerCase();
    return PRIORIDAD_META[normalized] || PRIORIDAD_META.normal;
}

function formatIsoDate(iso, formatter = "DD-MM-YYYY HH:mm") {
    if (!iso) {
        return null;
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return typeof moment === "function"
        ? moment(date).format(formatter)
        : date.toLocaleString();
}

function formatHours(value) {
    if (typeof value !== "number" || Number.isNaN(value)) {
        return null;
    }

    const rounded = Math.round(value);
    if (Math.abs(rounded) >= 48) {
        return `${(rounded / 24).toFixed(1)} día(s)`;
    }

    return `${rounded} h`;
}

function getAlertBadges(item = {}) {
    const alerts = [];

    if (item.alert_reprogramacion) {
        alerts.push({
            label: "Reprogramar",
            icon: "mdi-calendar-alert",
            className: "badge bg-danger text-white",
        });
    }

    if (item.alert_pendiente_consentimiento) {
        alerts.push({
            label: "Consentimiento",
            icon: "mdi-shield-alert",
            className: "badge bg-warning text-dark",
        });
    }

    if (item.alert_documentos_faltantes) {
        alerts.push({
            label: "Docs faltantes",
            icon: "mdi-file-alert-outline",
            className: "badge bg-warning text-dark",
        });
    }

    if (item.alert_autorizacion_pendiente) {
        alerts.push({
            label: "Autorización",
            icon: "mdi-account-lock",
            className: "badge bg-info text-dark",
        });
    }

    if (item.alert_tarea_vencida) {
        alerts.push({
            label: "Tarea vencida",
            icon: "mdi-clipboard-alert-outline",
            className: "badge bg-danger text-white",
        });
    }

    if (item.alert_sin_responsable) {
        alerts.push({
            label: "Sin responsable",
            icon: "mdi-account-alert-outline",
            className: "badge bg-secondary text-white",
        });
    }

    if (item.alert_contacto_pendiente) {
        alerts.push({
            label: "Sin contacto",
            icon: "mdi-phone-alert",
            className: "badge bg-secondary text-white",
        });
    }

    return alerts;
}

export function renderKanban(data, callbackEstadoActualizado) {
    document.querySelectorAll(".kanban-items").forEach((col) => {
        col.innerHTML = "";
    });

    const onEstadoChange =
        typeof callbackEstadoActualizado === "function"
            ? callbackEstadoActualizado
            : () => Promise.resolve();

    const hoy = new Date();

    data.forEach((solicitud) => {
        const tarjeta = document.createElement("div");

        const resolvedSlaStatus = resolveSlaStatus(solicitud);
        const isCriticalAlert =
            Boolean(solicitud.alert_reprogramacion) ||
            Boolean(solicitud.alert_tarea_vencida) ||
            resolvedSlaStatus === "vencido";

        const isWarningAlert =
            !isCriticalAlert &&
            (Boolean(solicitud.alert_documentos_faltantes) ||
                Boolean(solicitud.alert_autorizacion_pendiente) ||
                Boolean(solicitud.alert_pendiente_consentimiento) ||
                Boolean(solicitud.alert_sin_responsable) ||
                Boolean(solicitud.alert_contacto_pendiente));

        const accentClass = isCriticalAlert
            ? " border-start border-3 border-danger"
            : isWarningAlert
                ? " border-start border-3 border-warning"
                : "";

        tarjeta.className =
            `kanban-card border p-2 mb-2 rounded bg-light view-details${accentClass}`;
        tarjeta.setAttribute("draggable", "true");
        const estadoSlug =
            slugifyEstado(solicitud.kanban_estado ?? solicitud.estado) || "";
        const estadoLabel =
            solicitud.estado_label ??
            solicitud.kanban_estado_label ??
            estadoLabelFromSlug(estadoSlug);
        tarjeta.dataset.hc = solicitud.hc_number ?? "";
        tarjeta.dataset.form = solicitud.form_id ?? "";
        tarjeta.dataset.secuencia = solicitud.secuencia ?? "";
        tarjeta.dataset.estado = estadoSlug;
        tarjeta.dataset.estadoLabel = estadoLabel;
        tarjeta.dataset.id = solicitud.id ?? "";
        tarjeta.dataset.afiliacion = solicitud.afiliacion ?? "";
        tarjeta.dataset.aseguradora =
            solicitud.aseguradora ?? solicitud.aseguradoraNombre ?? "";
        tarjeta.dataset.prefacturaTrigger = "kanban";

        const fechaBaseIso =
            solicitud.fecha_programada_iso ||
            solicitud.fecha ||
            solicitud.created_at_iso ||
            null;
        const fechaBase = fechaBaseIso ? new Date(fechaBaseIso) : null;
        const fechaFormateada = fechaBase
            ? formatIsoDate(fechaBaseIso, "DD-MM-YYYY")
            : "—";
        const edadDias = fechaBase
            ? Math.max(0, Math.floor((hoy - fechaBase) / (1000 * 60 * 60 * 24)))
            : 0;
        const slaMeta = getSlaMeta(resolvedSlaStatus);
        const slaBadgeHtml = `<span class="${escapeHtml(
            slaMeta.badgeClass
        )}"><i class="mdi ${escapeHtml(slaMeta.icon)} me-1"></i>${escapeHtml(
            slaMeta.label
        )}</span>`;
        const prioridadValor = (
            solicitud.prioridad ||
            solicitud.prioridad_automatica ||
            ""
        )
            .toString()
            .trim()
            .toLowerCase();
        const esUrgente = prioridadValor === "urgente";
        const prioridadMeta = getPrioridadMeta(solicitud.prioridad_automatica);
        const prioridadBadgeClass =
            solicitud.prioridad_origen === "manual"
                ? "badge bg-primary text-white"
                : prioridadMeta.badgeClass;
        const prioridadBadgeHtml = esUrgente
            ? `<span class="${escapeHtml(
                prioridadBadgeClass
            )}"><i class="mdi ${escapeHtml(
                prioridadMeta.icon
            )} me-1"></i>${escapeHtml(
                solicitud.prioridad || prioridadMeta.label
            )}</span>`
            : "";
        const prioridadOrigenLabel =
            solicitud.prioridad_origen === "manual" ? "Manual" : "Regla automática";
        const prioridadBlockHtml = esUrgente
            ? `<small>🎯 ${prioridadBadgeHtml} <span class="text-muted">${escapeHtml(
                prioridadOrigenLabel
            )}</span></small>`
            : "";
        const slaDeadlineLabel = formatIsoDate(solicitud.sla_deadline);
        const slaHoursLabel = formatHours(solicitud.sla_hours_remaining);
        const slaSubtitleParts = [];
        if (slaDeadlineLabel) {
            slaSubtitleParts.push(`Vence ${slaDeadlineLabel}`);
        }
        if (slaHoursLabel) {
            slaSubtitleParts.push(slaHoursLabel);
        }
        if (edadDias) {
            slaSubtitleParts.push(`Edad ${edadDias} día(s)`);
        }
        const slaSubtitle = slaSubtitleParts.join(" · ");

        const kanbanPrefs = window.__crmKanbanPreferences ?? {};
        const defaultPipelineStage =
            Array.isArray(kanbanPrefs.pipelineStages) &&
            kanbanPrefs.pipelineStages.length
                ? kanbanPrefs.pipelineStages[0]
                : "Recibido";
        const pipelineStage = solicitud.crm_pipeline_stage || defaultPipelineStage;
        const responsable =
            solicitud.crm_responsable_nombre || "Sin responsable asignado";
        const doctorNombre = (solicitud.doctor ?? "").trim();
        const doctor = doctorNombre !== "" ? doctorNombre : "Sin doctor";
        const avatarNombre = doctorNombre !== "" ? doctorNombre : responsable;
        const avatarUrl =
            solicitud.doctor_avatar || solicitud.crm_responsable_avatar || null;
        const contactoTelefono =
            solicitud.crm_contacto_telefono ||
            solicitud.paciente_celular ||
            "Sin teléfono";
        const contactoCorreo = solicitud.crm_contacto_email || "Sin correo";
        const fuente = solicitud.crm_fuente || "";
        const totalNotas = Number.parseInt(solicitud.crm_total_notas ?? 0, 10);
        const totalAdjuntos = Number.parseInt(
            solicitud.crm_total_adjuntos ?? 0,
            10
        );
        const tareasPendientes = Number.parseInt(
            solicitud.crm_tareas_pendientes ?? 0,
            10
        );
        const tareasTotal = Number.parseInt(solicitud.crm_tareas_total ?? 0, 10);
        const proximoVencimiento = solicitud.crm_proximo_vencimiento
            ? moment(solicitud.crm_proximo_vencimiento).format("DD-MM-YYYY")
            : "--";

        const pacienteNombre = solicitud.full_name ?? "Paciente sin nombre";
        const procedimiento = solicitud.procedimiento || "Sin procedimiento";
        // doctor already normalizado
        const afiliacion = solicitud.afiliacion || "Sin afiliación";
        const ojo = solicitud.ojo || "—";
        const observacionRaw = (solicitud.observacion ?? "").toString().trim();
        const hasObservacion = observacionRaw !== "";
        const observacion = observacionRaw;
        const alerts = getAlertBadges(solicitud);
        const alertsCompactHtml = alerts.length
            ? `<div class="kanban-alerts d-flex align-items-center gap-2">${alerts
                .slice(0, 4)
                .map((alert) => {
                    const title = escapeHtml(alert.label);
                    const icon = escapeHtml(alert.icon);
                    // Keep color hint but without text.
                    const toneClass = (alert.className || "").includes("bg-danger")
                        ? "text-danger"
                        : (alert.className || "").includes("bg-warning")
                            ? "text-warning"
                            : (alert.className || "").includes("bg-info")
                                ? "text-info"
                                : "text-muted";
                    return `<span class="${toneClass}" title="${title}" aria-label="${title}">
                        <i class="mdi ${icon}"></i>
                    </span>`;
                })
                .join(" ")}</div>`
            : "";

        const badges = [
            formatBadge(
                "Notas",
                totalNotas,
                '<i class="mdi mdi-note-text-outline"></i>'
            ),
            formatBadge(
                "Adjuntos",
                totalAdjuntos,
                '<i class="mdi mdi-paperclip"></i>'
            ),
            formatBadge(
                "Tareas",
                `${tareasPendientes}/${tareasTotal}`,
                '<i class="mdi mdi-format-list-checks"></i>'
            ),
            formatBadge(
                "Vence",
                proximoVencimiento,
                '<i class="mdi mdi-calendar-clock"></i>'
            ),
        ]
            .filter(Boolean)
            .join("");

        const estadoSlugCard = slugifyEstado(
            solicitud.kanban_estado ?? solicitud.estado
        );
        const checklist = Array.isArray(solicitud.checklist)
            ? solicitud.checklist.map((item) => {
                if (slugifyEstado(item.slug) === "recibida" && estadoSlugCard === "recibida") {
                    return {...item, completed: true};
                }
                if (slugifyEstado(item.slug) === "llamado" && estadoSlugCard === "llamado") {
                    return {...item, completed: true};
                }
                return item;
            })
            : [];
        const checklistProgress = solicitud.checklist_progress || {};
        const pasosTotales =
            checklistProgress.total ??
            (Array.isArray(checklist) ? checklist.length : 0) ??
            0;
        const pasosCompletos = checklistProgress.completed ?? 0;
        const porcentaje =
            checklistProgress.percent ??
            (pasosTotales ? Math.round((pasosCompletos / pasosTotales) * 100) : 0);
        const proximoPaso = checklistProgress.next_label || "Completado";
        // Make nextStageSlug/Label available for checklist summary rendering
        const nextStageSlug = checklistProgress.next_slug || solicitud.checklist_progress?.next_slug;
        const nextStageLabel = checklistProgress.next_label || solicitud.checklist_progress?.next_label || nextStageSlug;
        const pendientesCriticos = [
            "revision-codigos",
            "espera-documentos",
            "apto-oftalmologo",
            "apto-anestesia",
        ];
        const checklistPreview = checklist
            .map((item) => {
                const slug = slugifyEstado(item.slug);
                const isCriticalPending =
                    !item.completed && pendientesCriticos.includes(slug);

                if (item.completed) {
                    return `<label class="form-check small mb-1">
              <input type="checkbox" class="form-check-input" data-checklist-toggle data-etapa-slug="${escapeHtml(
                        item.slug
                    )}" checked ${item.can_toggle ? "" : "disabled"}>
              <span class="ms-1">✅ ${escapeHtml(item.label)}</span>
            </label>`;
                }

                if (isCriticalPending) {
                    return `<div class="small mb-1 text-warning">
              <i class="mdi mdi-alert-outline me-1"></i>${escapeHtml(
                        item.label
                    )}
            </div>`;
                }

                return `<label class="form-check small mb-1">
            <input type="checkbox" class="form-check-input" data-checklist-toggle data-etapa-slug="${escapeHtml(
                    item.slug
                )}" ${item.can_toggle ? "" : "disabled"}>
            <span class="ms-1">⬜ ${escapeHtml(item.label)}</span>
          </label>`;
            })
            .join("");
        const detailsId = `kanban-details-${safeDomId(String(solicitud.id ?? solicitud.form_id ?? solicitud.hc_number ?? ""))}`;

        const checklistSummaryHtml =
            pasosTotales > 0
                ? `<div class="kanban-checklist mt-2">
            <div class="d-flex align-items-center justify-content-between">
              <span class="badge bg-light text-dark">${escapeHtml(`${porcentaje}%`)}</span>
              <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">→ ${escapeHtml(proximoPaso)}</span>
                ${nextStageSlug ? `<button type="button" class="btn btn-sm btn-outline-success py-0 px-2" data-next-stage="${escapeHtml(nextStageSlug)}" title="Marcar: ${escapeHtml(nextStageLabel || proximoPaso)}" aria-label="Marcar: ${escapeHtml(nextStageLabel || proximoPaso)}">
                    <i class="mdi mdi-check"></i>
                  </button>` : ""}
                <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none kanban-details-toggle" data-bs-toggle="collapse" data-bs-target="#${detailsId}" aria-expanded="false" aria-controls="${detailsId}" title="Ver detalles">
                  <i class="mdi mdi-chevron-down" data-icon-collapsed></i>
                  <i class="mdi mdi-chevron-up d-none" data-icon-expanded></i>
                </button>
              </div>
            </div>
            <div class="progress progress-thin my-1" style="height: 6px;">
              <div class="progress-bar bg-success" role="progressbar" style="width: ${porcentaje}%;"
                aria-valuenow="${porcentaje}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
          </div>`
                : "";

        const checklistDetailsHtml =
            checklist.length > 0
                ? `<div class="kanban-checklist-items">${checklistPreview}</div>`
                : "";
        const procedimientoCompleto = (procedimiento ?? "").toString();
        const procedimientoCorto = humanizeProcedureShort(procedimientoCompleto) || "Sin procedimiento";

        const crmCountsInline = [
            Number.isFinite(totalNotas) ? `📝 ${totalNotas}` : null,
            Number.isFinite(totalAdjuntos) ? `📎 ${totalAdjuntos}` : null,
            (Number.isFinite(tareasPendientes) && Number.isFinite(tareasTotal))
                ? `✅ ${tareasPendientes}/${tareasTotal}`
                : null,
        ].filter(Boolean).join(" · ");

        tarjeta.innerHTML = `
            <div class="kanban-card-header">
                <div class="kanban-card-body lh-sm">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                      <div class="flex-grow-1">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                          <strong class="d-block">${escapeHtml(pacienteNombre)}</strong>
                        </div>
                        <span class="text-muted small"><i class="mdi mdi-card-account-details-outline me-1"></i>${escapeHtml(solicitud.hc_number ?? "—")}</span>
                        <div class="d-flex align-items-center justify-content-between gap-2 mt-1">
                          <small class="text-muted"><i class="mdi mdi-calendar me-1"></i>${escapeHtml(fechaFormateada)}</small>
                          ${slaBadgeHtml}
                        </div>
                      </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between gap-2 mt-1">
                      <div class="d-flex align-items-center gap-1">
                        ${renderAvatar(avatarNombre, avatarUrl)}
                        <small class="text-muted">${escapeHtml(doctor)}</small>
                      </div>
                    </div>

                    <small class="text-muted d-block mt-1"><i class="mdi mdi-hospital-building me-1"></i>${escapeHtml(afiliacion)}</small>

                    <small class="d-block mt-1" title="${escapeHtml(procedimientoCompleto)}">
                      <i class="mdi mdi-magnify me-1 text-muted"></i><span class="text-primary fw-semibold">${escapeHtml(procedimientoCorto)}</span>
                    </small>
                    <small class="text-muted"><i class="mdi mdi-eye-outline me-1"></i>${escapeHtml(ojo)}</small>


                    ${hasObservacion ? `<div class="mt-1">
                        <span class="badge bg-info text-dark fw-semibold">💬 ${escapeHtml(observacion)}</span>
                      </div>` : ""}

                    <div class="kanban-card-crm mt-2">
                        <div class="d-flex align-items-center justify-content-between">
                          <span class="crm-pill"><i class="mdi mdi-progress-check"></i>${escapeHtml(pipelineStage)}</span>
                          <small class="text-muted">${escapeHtml(crmCountsInline || "CRM")}</small>
                        </div>
                    <div class="mt-2 crm-actions d-flex gap-2" data-crm-actions></div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                      ${alertsCompactHtml || "<span></span>"}
                    </div>

                    ${checklistSummaryHtml}

                    <div id="${detailsId}" class="collapse mt-2">
                      <div class="border rounded bg-white p-2">
                        <div class="mb-2">
                          <div class="small text-muted mb-1">Checklist</div>
                          ${checklistDetailsHtml || `<div class="small text-muted">Sin checklist</div>`}
                        </div>

                        <div class="mb-2">
                          <div class="small text-muted mb-1">CRM</div>
                          <div class="crm-meta small">
                              <span><i class="mdi mdi-account-tie-outline"></i>${escapeHtml(responsable)}</span>
                              <span class="ms-2"><i class="mdi mdi-phone"></i>${escapeHtml(contactoTelefono)}</span>
                              <span class="ms-2"><i class="mdi mdi-email-outline"></i>${escapeHtml(contactoCorreo)}</span>
                              ${fuente ? `<span class="ms-2"><i class="mdi mdi-source-branch"></i>${escapeHtml(fuente)}</span>` : ""}
                          </div>
                          <div class="crm-badges mt-2">${badges}</div>
                        </div>

                        <div>
                          <div class="small text-muted mb-1">Procedimiento (completo)</div>
                          <div class="small">${escapeHtml(procedimientoCompleto || "—")}</div>
                        </div>
                      </div>
                    </div>
                </div>
            </div>
        `;

        hydrateAvatar(tarjeta);

        // Prevent card click/drag handlers from triggering when toggling the collapse.
        tarjeta.querySelectorAll('.kanban-details-toggle').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        });

        // Toggle chevron icons on expand/collapse.
        const collapseEl = tarjeta.querySelector(`#${detailsId}`);
        if (collapseEl) {
            const setIcons = (expanded) => {
                const iconCollapsed = tarjeta.querySelector('[data-icon-collapsed]');
                const iconExpanded = tarjeta.querySelector('[data-icon-expanded]');
                if (iconCollapsed) {
                    iconCollapsed.classList.toggle('d-none', expanded);
                }
                if (iconExpanded) {
                    iconExpanded.classList.toggle('d-none', !expanded);
                }
            };

            collapseEl.addEventListener('shown.bs.collapse', () => setIcons(true));
            collapseEl.addEventListener('hidden.bs.collapse', () => setIcons(false));
        }

        // Compact "Marcar siguiente" CTA inside checklist summary.
        tarjeta.querySelectorAll('[data-next-stage]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                const slug = btn.dataset.nextStage || '';
                if (!slug) {
                    return;
                }

                btn.disabled = true;
                const result = onEstadoChange(
                    solicitud.id,
                    solicitud.form_id,
                    slug,
                    {completado: true, force: true}
                );

                if (result && typeof result.then === 'function') {
                    result.finally(() => {
                        btn.disabled = false;
                    });
                } else {
                    btn.disabled = false;
                }
            });
        });

        const turnoAsignado = formatTurno(solicitud.turno);
        const estadoActualSlug = estadoSlug;
        const estadoActualLabel = estadoLabel;
        const estadoNormalizado = normalizarEstado(estadoActualSlug);
        const allowTurnoActions =
            estadoNormalizado === "" ||
            estadoNormalizado === "recibida" ||
            estadoNormalizado === "llamado";

        const acciones = document.createElement("div");
        acciones.className =
            "kanban-card-actions d-flex align-items-center justify-content-between gap-2 flex-wrap mt-2";

        if (allowTurnoActions) {
            const badgeTurno = document.createElement("span");
            badgeTurno.className = "badge badge-turno";

            if (turnoAsignado) {
                badgeTurno.textContent = `#${turnoAsignado}`;
                badgeTurno.title = `Turno #${turnoAsignado}`;
            } else {
                badgeTurno.textContent = "—";
                badgeTurno.title = "Sin turno asignado";
                badgeTurno.classList.add('text-muted');
            }
            acciones.appendChild(badgeTurno);

            const botonLlamar = document.createElement("button");
            botonLlamar.type = "button";
            botonLlamar.className = "btn btn-sm btn-outline-primary llamar-turno-btn";
            applyTurnoButtonState(
                botonLlamar,
                Boolean(turnoAsignado) || estadoNormalizado === "llamado"
            );

            botonLlamar.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();

                if (botonLlamar.disabled) {
                    return;
                }

                const teniaTurnoAntes = botonLlamar.dataset.hasTurno === "1";
                botonLlamar.disabled = true;
                botonLlamar.setAttribute("aria-busy", "true");
                botonLlamar.title = 'Procesando…';
                botonLlamar.setAttribute('aria-label', 'Procesando…');
                botonLlamar.innerHTML =
                    '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                let exito = false;

                llamarTurnoSolicitud({id: solicitud.id})
                    .then((data) => {
                        const turno = formatTurno(data?.turno);
                        const nombre =
                            data?.full_name ?? solicitud.full_name ?? "Paciente sin nombre";

                        if (turno) {
                            badgeTurno.textContent = `#${turno}`;
                            badgeTurno.title = `Turno #${turno}`;
                            badgeTurno.classList.remove('text-muted');
                        } else {
                            badgeTurno.textContent = "—";
                            badgeTurno.title = "Sin turno asignado";
                            badgeTurno.classList.add('text-muted');
                        }

                        const estadoActualizado = (data?.estado ?? "").toString();
                        tarjeta.dataset.estado = estadoActualizado;

                        applyTurnoButtonState(
                            botonLlamar,
                            Boolean(turno) || normalizarEstado(estadoActualizado) === "llamado"
                        );
                        exito = true;

                        showToast(
                            `🔔 Turno asignado para ${nombre}${turno ? ` (#${turno})` : ""}`
                        );
                        announceTurno(nombre, { force: teniaTurnoAntes });

                        const store = getDataStore();
                        if (Array.isArray(store) && store.length) {
                            const item = store.find(
                                (s) => String(s.id) === String(solicitud.id)
                            );
                            if (item) {
                                item.turno = data?.turno ?? item.turno;
                                item.estado = data?.estado ?? item.estado;
                            }
                        }

                        if (typeof window.aplicarFiltros === "function") {
                            window.aplicarFiltros();
                        }
                    })
                    .catch((error) => {
                        console.error("❌ Error al llamar el turno:", error);
                        showToast(error?.message ?? "No se pudo asignar el turno", false);
                        actualizarEstadoSolicitud(
                            solicitud.id,
                            solicitud.form_id,
                            "Llamado",
                            getDataStore(),
                            window.aplicarFiltros,
                            {force: true}
                        ).catch(() => {
                        });
                    })
                    .finally(() => {
                        botonLlamar.disabled = false;
                        botonLlamar.removeAttribute("aria-busy");
                        if (!exito) {
                            applyTurnoButtonState(botonLlamar, teniaTurnoAntes);
                        }
                    });
            });

            acciones.appendChild(botonLlamar);
        }

        // The footer "Marcar: ..." button has been removed in favor of the compact checklist summary CTA.

        tarjeta.appendChild(acciones);

        const crmButton = document.createElement("button");
        crmButton.type = "button";
        crmButton.className =
            "btn btn-sm btn-outline-secondary w-100 mt-2 btn-open-crm";
        crmButton.innerHTML =
            '<i class="mdi mdi-account-box-outline"></i> CRM';
        crmButton.dataset.solicitudId = solicitud.id ?? "";
        crmButton.dataset.pacienteNombre = solicitud.full_name ?? "";
        const crmActionsSlot = tarjeta.querySelector('[data-crm-actions]');
        if (crmActionsSlot) {
            crmButton.className = "btn btn-sm btn-outline-secondary btn-open-crm";
            crmActionsSlot.appendChild(crmButton);

            const openProjectButton = document.createElement("button");
            openProjectButton.type = "button";
            openProjectButton.className = "btn btn-sm btn-outline-success btn-icon";
            openProjectButton.innerHTML = '<i class="mdi mdi-open-in-new"></i>';
            openProjectButton.title = "Abrir caso";
            openProjectButton.setAttribute("aria-label", "Abrir caso");

            openProjectButton.addEventListener("click", (event) => {
                event.preventDefault();
                event.stopPropagation();
                openProjectForSolicitud(solicitud, openProjectButton);
            });
            crmActionsSlot.appendChild(openProjectButton);
        } else {
            // fallback: si no existe el slot, mantén CRM en tarjeta y Abrir caso en acciones (opcional)
            tarjeta.appendChild(crmButton);
            // acciones.appendChild(openProjectButton); // si quieres fallback, dímelo y te lo dejo completo
        }

        tarjeta.querySelectorAll("[data-checklist-toggle]").forEach((input) => {
            input.addEventListener("click", (e) => {
                e.stopPropagation();
            });

            input.addEventListener("change", () => {
                const slug = input.dataset.etapaSlug || "";
                const marcado = input.checked;
                input.disabled = true;

                const resultado = onEstadoChange(
                    solicitud.id,
                    solicitud.form_id,
                    slug,
                    {completado: marcado}
                );

                const revert = () => {
                    input.checked = !marcado;
                };

                if (resultado && typeof resultado.then === "function") {
                    resultado
                        .then((resp) => {
                            solicitud.checklist = resp?.checklist ?? solicitud.checklist;
                            solicitud.checklist_progress =
                                resp?.checklist_progress ?? solicitud.checklist_progress;
                            if (typeof window.aplicarFiltros === "function") {
                                window.aplicarFiltros();
                            }
                        })
                        .catch((error) => {
                            revert();
                            if (!error || !error.__estadoNotificado) {
                                const mensaje =
                                    (error && error.message) ||
                                    "No se pudo actualizar el checklist";
                                showToast(mensaje, false);
                            }
                        })
                        .finally(() => {
                            input.disabled = false;
                        });
                } else {
                    input.disabled = false;
                }
            });
        });

        const estadoId =
            "kanban-" +
            slugifyEstado(solicitud.kanban_estado ?? solicitud.estado ?? estadoLabel);

        const columna = document.getElementById(estadoId);
        if (columna) {
            columna.appendChild(tarjeta);
        }
    });

    document.querySelectorAll(".kanban-items").forEach((container) => {
        new Sortable(container, {
            group: "kanban",
            animation: 150,
            onEnd: (evt) => {
                const item = evt.item;
                const columnaAnterior = evt.from;
                const posicionAnterior = evt.oldIndex;
                const estadoAnterior = (item.dataset.estado ?? "").toString();
                const estadoAnteriorLabel =
                    item.dataset.estadoLabel || estadoLabelFromSlug(estadoAnterior);
                const badgeEstado = item.querySelector(
                    ".badge.badge-estado, .badge-estado"
                );
                const badgeTurno = item.querySelector(".badge-turno");
                const botonTurno = item.querySelector(".llamar-turno-btn");
                const turnoTextoAnterior = badgeTurno
                    ? badgeTurno.textContent
                    : "—";
                const botonTeniaTurnoAntes = botonTurno
                    ? botonTurno.dataset.hasTurno === "1"
                    : false;

                const nuevoEstado = slugifyEstado(evt.to.id.replace("kanban-", ""));

                const aplicarEstadoEnUI = (slug, label) => {
                    const etiqueta =
                        label || estadoLabelFromSlug(slug) || (slug ?? "").toString();
                    item.dataset.estado = slug;
                    item.dataset.estadoLabel = etiqueta;
                    if (badgeEstado) {
                        badgeEstado.textContent = etiqueta !== "" ? etiqueta : "Sin estado";
                    }
                };

                const revertirMovimiento = () => {
                    aplicarEstadoEnUI(estadoAnterior, estadoAnteriorLabel);
                    if (badgeTurno) {
                        badgeTurno.textContent = turnoTextoAnterior;
                    }
                    if (botonTurno) {
                        applyTurnoButtonState(botonTurno, botonTeniaTurnoAntes);
                    }
                    if (columnaAnterior) {
                        const referencia =
                            columnaAnterior.children[posicionAnterior] || null;
                        columnaAnterior.insertBefore(item, referencia);
                    }
                };

                aplicarEstadoEnUI(nuevoEstado);

                if (botonTurno) {
                    const debeRecordar =
                        botonTeniaTurnoAntes || normalizarEstado(nuevoEstado) === "llamado";
                    applyTurnoButtonState(botonTurno, debeRecordar);
                }

                let resultado;
                try {
                    resultado = onEstadoChange(
                        item.dataset.id,
                        item.dataset.form,
                        nuevoEstado,
                        {}
                    );
                } catch (error) {
                    revertirMovimiento();

                    if (!error || !error.__estadoNotificado) {
                        const mensaje =
                            (error && error.message) || "No se pudo actualizar el estado";
                        showToast(mensaje, false);
                    }
                    return;
                }

                if (resultado && typeof resultado.then === "function") {
                    resultado
                        .then((response) => {
                            const estadoServidor = (
                                response?.estado ?? nuevoEstado
                            ).toString();
                            const estadoServidorLabel =
                                response?.estado_label ?? estadoLabelFromSlug(estadoServidor);
                            aplicarEstadoEnUI(estadoServidor, estadoServidorLabel);

                            const destinoId = "kanban-" + slugifyEstado(estadoServidor);
                            if (destinoId && destinoId !== evt.to.id) {
                                const destino = document.getElementById(destinoId);
                                if (destino) {
                                    destino.appendChild(item);
                                }
                            }

                            if (badgeTurno) {
                                const turnoActual = formatTurno(response?.turno);
                                if (turnoActual) {
                                    badgeTurno.textContent = `#${turnoActual}`;
                                    badgeTurno.title = `Turno #${turnoActual}`;
                                    badgeTurno.classList.remove('text-muted');
                                } else {
                                    badgeTurno.textContent = "—";
                                    badgeTurno.title = "Sin turno asignado";
                                    badgeTurno.classList.add('text-muted');
                                }
                            }

                            if (botonTurno) {
                                const turnoActual = formatTurno(response?.turno);
                                const debeRecordar =
                                    Boolean(turnoActual) ||
                                    normalizarEstado(estadoServidor) === "llamado";
                                applyTurnoButtonState(botonTurno, debeRecordar);
                            }
                        })
                        .catch((error) => {
                            revertirMovimiento();

                            if (!error || !error.__estadoNotificado) {
                                const mensaje =
                                    (error && error.message) || "No se pudo actualizar el estado";
                                showToast(mensaje, false);
                            }
                        });
                }
            },
        });
    });
}
