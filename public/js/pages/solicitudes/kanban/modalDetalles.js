import {
  getKanbanConfig,
  getTableBodySelector,
  getDataStore,
  getEstadosMeta,
} from "./config.js";
import { formatTurno } from "./turnero.js";
import { actualizarEstadoSolicitud } from "./estado.js";
import { showToast } from "./toast.js";

let prefacturaListenerAttached = false;
const STATUS_BADGE_TEXT_DARK = new Set(["warning", "light", "info"]);
const PATIENT_ALERT_TEXT = /paciente/i;
const OFTALMOLOGO_SLUG = "apto-oftalmologo";
const ESTADO_APTO_OFTALMOLOGO = "APTO OFTALMOLOGO";
let cachedLentes = null;
let cachedDoctores = null;
let lentesPromise = null;
let doctoresPromise = null;
let swalLoader = null;
const detalleCache = new Map();

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

function toDatetimeLocal(value) {
  if (!value) {
    return "";
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "";
  }
  const pad = (n) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
    date.getDate()
  )}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
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

function ensureSwal() {
  if (typeof window !== "undefined" && window.Swal) {
    return Promise.resolve(window.Swal);
  }

  if (swalLoader) {
    return swalLoader;
  }

  swalLoader = new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = "https://cdn.jsdelivr.net/npm/sweetalert2@11";
    script.async = true;
    script.onload = () => {
      resolve(window.Swal);
      swalLoader = null;
    };
    script.onerror = (e) => {
      swalLoader = null;
      reject(e);
    };
    document.head.appendChild(script);
  });

  return swalLoader;
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
  return powers;
}

async function obtenerDoctoresKanban() {
  if (cachedDoctores) {
    return cachedDoctores;
  }
  if (doctoresPromise) {
    return doctoresPromise;
  }
  doctoresPromise = Promise.resolve().then(() => {
    const store = getDataStore();
    const docs = Array.isArray(store)
      ? Array.from(
          new Set(
            store
              .map((s) => s.doctor)
              .filter((d) => d && d.toString().trim() !== "")
              .map((d) => d.toString().trim())
          )
        )
      : [];
    cachedDoctores = docs;
    doctoresPromise = null;
    return docs;
  });
  return doctoresPromise;
}

async function obtenerLentesKanban() {
  if (cachedLentes) {
    return cachedLentes;
  }
  if (lentesPromise) {
    return lentesPromise;
  }

  lentesPromise = fetch("/api/lentes/index.php", {
    method: "GET",
    headers: { "Content-Type": "application/json" },
  })
    .then(async (resp) => {
      const data = await resp.json().catch(() => ({}));
      if (!resp.ok || data?.success === false) {
        throw new Error(data?.message || "No se pudo obtener lentes");
      }
      const lista = Array.isArray(data?.lentes) ? data.lentes : [];
      cachedLentes = lista;
      return lista;
    })
    .catch((error) => {
      console.error("Error obteniendo lentes:", error);
      throw error;
    })
    .finally(() => {
      lentesPromise = null;
    });
  return lentesPromise;
}

async function guardarSolicitudParcial(id, payload) {
  const body = { id: Number(id), ...payload };
  const resp = await fetch("/api/solicitudes/estado.php", {
    method: "POST",
    headers: { "Content-Type": "application/json;charset=UTF-8" },
    body: JSON.stringify(body),
  });
  const data = await resp.json().catch(() => ({}));
  if (!resp.ok || data?.success === false) {
    const msg =
      data?.message ||
      data?.error ||
      data?.mensaje ||
      "No se pudo actualizar la solicitud";
    throw new Error(msg);
  }
  return data?.data || null;
}

function mergeSolicitudEnStore(id, updates = {}) {
  const store = getDataStore();
  if (!Array.isArray(store) || !store.length) {
    return;
  }
  const idx = store.findIndex((item) => String(item.id) === String(id));
  if (idx >= 0) {
    store[idx] = { ...store[idx], ...updates };
  }
}

async function cargarDetalleSolicitud(item = {}) {
  if (!item || (!item.hc_number && !item.hcNumber)) {
    return item;
  }
  const idKey = String(item.id || item.form_id || "");
  if (idKey && detalleCache.has(idKey)) {
    return { ...item, ...detalleCache.get(idKey) };
  }

  try {
    const hc = encodeURIComponent(item.hc_number || item.hcNumber || "");
    const resp = await fetch(`/api/solicitudes/estado.php?hcNumber=${hc}`, {
      headers: { "Content-Type": "application/json" },
    });
    const data = await resp.json().catch(() => ({}));
    const lista = Array.isArray(data?.solicitudes) ? data.solicitudes : Array.isArray(data?.data) ? data.data : [];
    const match =
      lista.find(
        (row) =>
          String(row.id) === String(item.id) ||
          (row.form_id && item.form_id && String(row.form_id) === String(item.form_id))
      ) || lista[0];
    if (match) {
      detalleCache.set(idKey, match);
      mergeSolicitudEnStore(item.id, match);
      return { ...item, ...match };
    }
  } catch (err) {
    console.warn("No se pudo cargar detalle de solicitud:", err);
  }

  return item;
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
    observacion: solicitud.observacion || "Sin observaciones",
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

  if (estado === OFTALMOLOGO_SLUG) {
    blocks.push(`
        <div class="alert alert-info border" id="prefacturaOftalmoPanel">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="mdi mdi-eye-outline fs-4 text-info"></i>
                <div>
                    <strong>Validación del oftalmólogo</strong>
                    <p class="mb-0 text-muted">Revisa y confirma los datos del lente intraocular antes de pasar a anestesia.</p>
                </div>
            </div>
            <div class="bg-white border rounded p-2 mb-2">
                <div class="row g-2">
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Lente / Producto</small>
                        <strong>${escapeHtml(baseInfo.producto)}</strong>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Ojo</small>
                        <strong>${escapeHtml(baseInfo.ojo)}</strong>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Marca</small>
                        <strong>${escapeHtml(baseInfo.marca)}</strong>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Modelo</small>
                        <strong>${escapeHtml(baseInfo.modelo)}</strong>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Poder</small>
                        <strong>${escapeHtml(baseInfo.poder)}</strong>
                    </div>
                    <div class="col-sm-6">
                        <small class="text-muted d-block">Observación</small>
                        <strong>${escapeHtml(baseInfo.observacion)}</strong>
                    </div>
                </div>
            </div>
            <button class="btn btn-info w-100" data-context-action="planificar-oftalmo" data-id="${escapeHtml(
              solicitud.id
            )}" data-form-id="${escapeHtml(
      solicitud.form_id
    )}">
                <i class="mdi mdi-eyedropper-variant"></i> Editar datos de LIO y confirmar
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
  const afiliacion =
    solicitud.afiliacion || solicitud.aseguradora || "Sin afiliación";
  const examen = solicitud.examen_fisico || "No disponible";
  const plan = solicitud.plan || "No disponible";
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
            <div class="mt-3 p-3 border rounded bg-light text-start w-100">
                <h6 class="mb-3">📝 Examen Físico y Plan</h6>
                <div class="mb-3">
                    <strong>Examen Físico:</strong><br>
                    <div class="bg-white p-2 border rounded" style="white-space: pre-wrap;">
                        ${escapeHtml(examen)}
                    </div>
                </div>
                <div>
                    <strong>Plan:</strong><br>
                    <div class="bg-white p-2 border rounded" style="white-space: pre-wrap;">
                        ${escapeHtml(plan)}
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
  actualizarBotonesModal(solicitudId);

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
      const solicitud = findSolicitudById(solicitudId) || {};
      const contextual = buildContextualActionsHtml(solicitud);
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
    { once: true }
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

  const { hc, formId, solicitudId } = resolverDataset(trigger);
  highlightSelection({ cardId: solicitudId, rowId: solicitudId });
  renderEstadoContext(solicitudId);
  renderPatientSummaryFallback(solicitudId);
  abrirPrefactura({ hc, formId, solicitudId });
}

function handleContextualAction(event) {
  const button = event.target.closest("[data-context-action]");
  if (!button) {
    return;
  }

  const action = button.dataset.contextAction;
  const solicitudId = button.dataset.id;
  const formId = button.dataset.formId;
  const basePath = button.dataset.basePath || getKanbanConfig().basePath;

  const solicitud = findSolicitudById(solicitudId) || {};

  if (action === "confirmar-oftalmo") {
    actualizarEstadoSolicitud(
      solicitudId,
      formId,
      "apto-anestesia",
      getDataStore(),
      window.aplicarFiltros
    );
    return;
  }

  if (action === "planificar-oftalmo") {
    abrirPlanQuirurgicoOftalmo(solicitud);
    return;
  }

  if (action === "confirmar-anestesia") {
    actualizarEstadoSolicitud(
      solicitudId,
      formId,
      "listo-para-agenda",
      getDataStore(),
      window.aplicarFiltros
    );
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
}

export function inicializarModalDetalles() {
  if (prefacturaListenerAttached) {
    return;
  }

  prefacturaListenerAttached = true;
  document.addEventListener("click", handlePrefacturaClick);
  document.addEventListener("click", handleContextualAction);
}

function buildPlanificadorHtml(item = {}) {
  const estado = ESTADO_APTO_OFTALMOLOGO;
  const doctor = item?.doctor || "";
  const fecha = item?.fecha || "";
  const prioridad = item?.prioridad || "";
  const observacion = item?.observacion || "";
  const procedimiento = item?.procedimiento || "";
  const producto = item?.producto || item?.lente_nombre || "";
  const ojo = item?.ojo || item?.lateralidad || "";
  const afiliacion = item?.afiliacion || "";
  const duracion = item?.duracion || "";
  const lenteId = item?.lente_id || "";
  const lenteNombre = item?.lente_nombre || "";
  const lentePoder = item?.lente_poder || "";
  const lenteObs = item?.lente_observacion || "";
  const incision = item?.incision || "";

  return `
    <div class="cive-modal-card">
      <div class="cive-modal-section">
        <h4><i class="fas fa-user-md"></i> Datos de solicitud</h4>
        <div class="cive-row">
          <div class="cive-col-6">
            <div class="cive-form-group">
              <label>Estado</label>
              <input id="sol-estado" class="swal2-input" value="${estado}" readonly />
            </div>
          </div>
          <div class="cive-col-6">
            <div class="cive-form-group">
              <label>Doctor</label>
              <select id="sol-doctor" class="swal2-select" data-value="${escapeHtml(
                doctor
              )}"></select>
            </div>
          </div>
        </div>
        <div class="cive-row">
          <div class="cive-col-6">
            <div class="cive-form-group">
              <label>Fecha</label>
              <input id="sol-fecha" type="datetime-local" class="swal2-input" value="${toDatetimeLocal(
                fecha
              )}" />
            </div>
          </div>
          <div class="cive-col-6">
            <div class="cive-form-group">
              <label>Prioridad</label>
              <input id="sol-prioridad" class="swal2-input" value="${escapeHtml(
                prioridad
              )}" placeholder="URGENTE / NORMAL" readonly />
            </div>
          </div>
        </div>
        <div class="cive-row">
          <div class="cive-col-6">
            <div class="cive-form-group">
              <label>Producto</label>
              <input id="sol-producto" class="swal2-input" value="${escapeHtml(
                producto
              )}" placeholder="Producto asociado" />
            </div>
          </div>
          <div class="cive-col-6">
            <div class="cive-form-group">
              <label>Ojo</label>
              <select id="sol-ojo" class="swal2-select">
                <option value="">Selecciona ojo</option>
                <option value="DERECHO"${
                  ojo === "DERECHO" ? " selected" : ""
                }>DERECHO</option>
                <option value="IZQUIERDO"${
                  ojo === "IZQUIERDO" ? " selected" : ""
                }>IZQUIERDO</option>
                <option value="AMBOS OJOS"${
                  ojo === "AMBOS OJOS" ? " selected" : ""
                }>AMBOS OJOS</option>
              </select>
            </div>
          </div>
        </div>
        <div class="cive-row">
          <div class="cive-col-6">
            <div class="cive-form-group">
              <label>Afiliación</label>
              <input id="sol-afiliacion" class="swal2-input" value="${escapeHtml(
                afiliacion
              )}" placeholder="Afiliación" readonly />
            </div>
          </div>
          <div class="cive-col-6">
            <div class="cive-form-group">
              <label>Duración</label>
              <input id="sol-duracion" class="swal2-input" value="${escapeHtml(
                duracion
              )}" placeholder="Minutos" readonly />
            </div>
          </div>
        </div>
        <div class="cive-row">
          <div class="cive-col-full cive-form-group">
            <label>Procedimiento</label>
            <textarea id="sol-procedimiento" class="swal2-textarea" rows="2" placeholder="Descripción">${escapeHtml(
              procedimiento
            )}</textarea>
          </div>
        </div>
        <div class="cive-row">
          <div class="cive-col-full cive-form-group">
            <label>Observación</label>
            <textarea id="sol-observacion" class="swal2-textarea" rows="2" placeholder="Notas">${escapeHtml(
              observacion
            )}</textarea>
          </div>
        </div>
      </div>
      <div class="cive-modal-section">
        <h4><i class="fas fa-eye"></i> Lente e incisión</h4>
        <div class="cive-row">
          <div class="cive-col-6">
            <div class="cive-form-group">
              <label>Lente</label>
              <select id="sol-lente-id" class="swal2-select" data-value="${escapeHtml(
                lenteId
              )}">
                <option value="">Selecciona lente</option>
              </select>
            </div>
          </div>
          <div class="cive-col-6">
            <div class="cive-form-group">
              <label>Nombre de lente</label>
              <input id="sol-lente-nombre" class="swal2-input" value="${escapeHtml(
                lenteNombre
              )}" placeholder="Nombre del lente" readonly />
            </div>
          </div>
        </div>
        <div class="cive-row">
          <div class="cive-col-6">
            <div class="cive-form-group">
              <label>Poder del lente</label>
              <select id="sol-lente-poder" class="swal2-select">
                <option value="">Selecciona poder</option>
              </select>
            </div>
          </div>
          <div class="cive-col-6">
            <div class="cive-form-group">
              <label>Incisión</label>
              <input id="sol-incision" class="swal2-input" value="${escapeHtml(
                incision
              )}" placeholder="Ej: Clear cornea temporal" />
            </div>
          </div>
        </div>
        <div class="cive-row">
          <div class="cive-col-full cive-form-group">
            <label>Observación de lente</label>
            <textarea id="sol-lente-obs" class="swal2-textarea" rows="2" placeholder="Notas de lente">${escapeHtml(
              lenteObs
            )}</textarea>
          </div>
        </div>
      </div>
    </div>
  `;
}

async function abrirPlanQuirurgicoOftalmo(item = {}) {
  const itemDetallado = await cargarDetalleSolicitud(item);
  const html = buildPlanificadorHtml(itemDetallado);

  const SwalLib = await ensureSwal().catch((err) => {
    console.error("No se pudo cargar SweetAlert2:", err);
    showToast("No se pudo abrir el plan quirúrgico (SweetAlert2 faltante)", false);
    return null;
  });
  if (!SwalLib) return;

  SwalLib.fire({
    title: `Validar lente / HC ${escapeHtml(itemDetallado?.hc_number || item?.hc_number || "")}`,
    html,
    width: 820,
    customClass: { popup: "cive-modal-wide" },
    showCancelButton: true,
    confirmButtonText: "Guardar y pasar a anestesia",
    cancelButtonText: "Cancelar",
    focusConfirm: false,
    didOpen: async () => {
      const estadoInput = document.getElementById("sol-estado");
      if (estadoInput) estadoInput.value = ESTADO_APTO_OFTALMOLOGO;

      try {
        const doctores = await obtenerDoctoresKanban();
        const sel = document.getElementById("sol-doctor");
        if (sel) {
          sel.innerHTML = '<option value="">Selecciona doctor</option>';
          doctores.forEach((d) => {
            const opt = document.createElement("option");
            opt.value = d;
            opt.textContent = d;
            sel.appendChild(opt);
          });
          const preset = sel.dataset.value || item?.doctor || "";
          if (preset) sel.value = preset;
        }
      } catch (err) {
        console.warn("No se pudieron cargar doctores para el select:", err);
      }

      try {
        const lentes = await obtenerLentesKanban();
        const selLente = document.getElementById("sol-lente-id");
        const selPoder = document.getElementById("sol-lente-poder");
        if (selLente) {
          selLente.innerHTML = '<option value="">Selecciona lente</option>';
          lentes.forEach((l) => {
            const opt = document.createElement("option");
            opt.value = l.id;
            opt.textContent = `${l.marca} · ${l.modelo} · ${l.nombre}${
              l.poder ? " (" + l.poder + ")" : ""
            }`;
            opt.dataset.nombre = l.nombre;
            opt.dataset.poder = l.poder || "";
            opt.dataset.rango_desde = l.rango_desde ?? "";
            opt.dataset.rango_hasta = l.rango_hasta ?? "";
            opt.dataset.rango_paso = l.rango_paso ?? "";
            opt.dataset.rango_inicio_incremento =
              l.rango_inicio_incremento ?? "";
            selLente.appendChild(opt);
          });
          const preset = selLente.dataset.value || item?.lente_id || "";
          if (preset) selLente.value = preset;

          const syncLente = () => {
            const optSel = selLente.selectedOptions?.[0];
            const nombre = optSel?.dataset?.nombre || "";
            const poder = optSel?.dataset?.poder || "";
            const rangoDesde = optSel?.dataset?.rango_desde || "";
            const rangoHasta = optSel?.dataset?.rango_hasta || "";
            const rangoPaso = optSel?.dataset?.rango_paso || "";
            const rangoInicio =
              optSel?.dataset?.rango_inicio_incremento || "";
            const nombreInput = document.getElementById("sol-lente-nombre");
            const poderSelect = document.getElementById("sol-lente-poder");
            if (nombreInput) nombreInput.value = nombre || nombreInput.value;
            if (poderSelect) {
              poderSelect.innerHTML =
                '<option value="">Selecciona poder</option>';
              const lenteObj = {
                rango_desde: rangoDesde,
                rango_hasta: rangoHasta,
                rango_paso: rangoPaso,
                rango_inicio_incremento: rangoInicio,
              };
              const powers = generarPoderes(lenteObj);
              powers.forEach((p) => {
                const optP = document.createElement("option");
                optP.value = p;
                optP.textContent = p;
                poderSelect.appendChild(optP);
              });
              if (poder && !powers.includes(poder)) {
                const optP = document.createElement("option");
                optP.value = poder;
                optP.textContent = poder;
                poderSelect.appendChild(optP);
              }
              poderSelect.value = item?.lente_poder || poder || "";
            }
          };
          selLente.addEventListener("change", syncLente);
          syncLente();
        }
      } catch (err) {
        console.warn("No se pudieron cargar lentes para el select:", err);
      }
    },
    preConfirm: () => {
      return {
        estado: ESTADO_APTO_OFTALMOLOGO,
        doctor: document.getElementById("sol-doctor")?.value.trim(),
        fecha: document.getElementById("sol-fecha")?.value.trim(),
        prioridad: document.getElementById("sol-prioridad")?.value.trim(),
        observacion: document.getElementById("sol-observacion")?.value.trim(),
        procedimiento: document
          .getElementById("sol-procedimiento")
          ?.value.trim(),
        producto: document.getElementById("sol-producto")?.value.trim(),
        ojo: document.getElementById("sol-ojo")?.value.trim(),
        afiliacion: document.getElementById("sol-afiliacion")?.value.trim(),
        duracion: document.getElementById("sol-duracion")?.value.trim(),
        lente_id: document.getElementById("sol-lente-id")?.value.trim(),
        lente_nombre: document.getElementById("sol-lente-nombre")?.value.trim(),
        lente_poder: document.getElementById("sol-lente-poder")?.value.trim(),
        lente_observacion: document
          .getElementById("sol-lente-obs")
          ?.value.trim(),
        incision: document.getElementById("sol-incision")?.value.trim(),
      };
    },
  }).then(async (result) => {
    if (!result.isConfirmed) return;
    const payload = result.value || {};
    payload.estado = ESTADO_APTO_OFTALMOLOGO;
    try {
      showToast("Guardando solicitud...", true);
      const updated = await guardarSolicitudParcial(item.id, payload);
      if (updated) {
        mergeSolicitudEnStore(item.id, updated);
      } else {
        mergeSolicitudEnStore(item.id, payload);
      }
      await actualizarEstadoSolicitud(
        item.id,
        item.form_id,
        "apto-anestesia",
        getDataStore(),
        window.aplicarFiltros
      );
      showToast("✅ Solicitud actualizada y marcada como apto oftalmólogo");
    } catch (err) {
      console.error("Error actualizando solicitud:", err);
      Swal.fire(
        "Error",
        err?.message || "No se pudo actualizar la solicitud",
        "error"
      );
    }
  });
}
