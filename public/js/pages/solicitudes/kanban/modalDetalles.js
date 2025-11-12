import {
  getKanbanConfig,
  getTableBodySelector,
  getDataStore,
  getEstadosMeta,
} from "./config.js";
import { formatTurno } from "./turnero.js";

let prefacturaListenerAttached = false;
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

function getQuickColumnElement() {
  return document.getElementById("prefacturaQuickColumn");
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
              procedimiento
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

function highlightSelection({ cardId, rowId }) {
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

  return { hc, formId, solicitudId };
}

function abrirPrefactura({ hc, formId, solicitudId }) {
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

  const { basePath } = getKanbanConfig();

  fetch(
    `${basePath}/prefactura?hc_number=${encodeURIComponent(
      hc
    )}&form_id=${encodeURIComponent(formId)}`
  )
    .then((response) => {
      if (!response.ok) {
        throw new Error("No se encontró la prefactura");
      }
      return response.text();
    })
    .then((html) => {
      content.innerHTML = html;
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
    { once: true }
  );
}

function handlePrefacturaClick(event) {
  const trigger = event.target.closest("[data-prefactura-trigger]");
  if (!trigger) {
    return;
  }

  const { hc, formId, solicitudId } = resolverDataset(trigger);
  highlightSelection({ cardId: solicitudId, rowId: solicitudId });
  renderEstadoContext(solicitudId);
  renderPatientSummaryFallback(solicitudId);
  abrirPrefactura({ hc, formId, solicitudId });
}

export function inicializarModalDetalles() {
  if (prefacturaListenerAttached) {
    return;
  }

  prefacturaListenerAttached = true;
  document.addEventListener("click", handlePrefacturaClick);
}
