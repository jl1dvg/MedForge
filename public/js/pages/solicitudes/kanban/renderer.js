import { showToast } from "./toast.js";
import { llamarTurnoSolicitud, formatTurno } from "./turnero.js";
import { getDataStore } from "./config.js";

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

      img.addEventListener("error", showPlaceholder, { once: true });

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
  recall: '<i class="mdi mdi-phone-incoming"></i> Volver a llamar',
  generate: '<i class="mdi mdi-bell-ring-outline"></i> Generar turno',
};

function normalizarEstado(value) {
  return slugifyEstado(value);
}

function applyTurnoButtonState(button, shouldRecall) {
  if (!button) {
    return;
  }

  button.innerHTML = shouldRecall
    ? TURNO_BUTTON_LABELS.recall
    : TURNO_BUTTON_LABELS.generate;
  button.dataset.hasTurno = shouldRecall ? "1" : "0";
}

const SLA_META = {
  en_rango: {
    label: "En rango",
    badgeClass: "badge-sla badge bg-success text-white",
    icon: "mdi-check-circle-outline",
  },
  advertencia: {
    label: "Seguimiento 72h",
    badgeClass: "badge-sla badge bg-warning text-dark",
    icon: "mdi-timer-sand",
  },
  critico: {
    label: "Crítico 24h",
    badgeClass: "badge-sla badge bg-danger",
    icon: "mdi-alert-octagon",
  },
  vencido: {
    label: "SLA vencido",
    badgeClass: "badge-sla badge bg-dark",
    icon: "mdi-alert",
  },
  sin_fecha: {
    label: "Sin programación",
    badgeClass: "badge-sla badge bg-secondary",
    icon: "mdi-calendar-question",
  },
  cerrado: {
    label: "Cerrado",
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
  const normalized = (status || "").toString().trim();
  return SLA_META[normalized] || SLA_META.sin_fecha;
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
    tarjeta.className =
      "kanban-card border p-2 mb-2 rounded bg-light view-details";
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
    const slaMeta = getSlaMeta(solicitud.sla_status);
    const slaBadgeHtml = `<span class="${escapeHtml(
      slaMeta.badgeClass
    )}"><i class="mdi ${escapeHtml(slaMeta.icon)} me-1"></i>${escapeHtml(
      slaMeta.label
    )}</span>`;
    const prioridadMeta = getPrioridadMeta(solicitud.prioridad_automatica);
    const prioridadBadgeClass =
      solicitud.prioridad_origen === "manual"
        ? "badge bg-primary text-white"
        : prioridadMeta.badgeClass;
    const prioridadBadgeHtml = `<span class="${escapeHtml(
      prioridadBadgeClass
    )}"><i class="mdi ${escapeHtml(prioridadMeta.icon)} me-1"></i>${escapeHtml(
      solicitud.prioridad || prioridadMeta.label
    )}</span>`;
    const prioridadOrigenLabel =
      solicitud.prioridad_origen === "manual" ? "Manual" : "Regla automática";
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
      : "Sin vencimiento";

    const pacienteNombre = solicitud.full_name ?? "Paciente sin nombre";
    const procedimiento = solicitud.procedimiento || "Sin procedimiento";
    // doctor already normalizado
    const afiliacion = solicitud.afiliacion || "Sin afiliación";
    const ojo = solicitud.ojo || "—";
    const observacion = solicitud.observacion || "Sin nota";
    const alerts = getAlertBadges(solicitud);
    const alertsHtml = alerts.length
      ? `<div class="kanban-alerts mt-2">${alerts
          .map(
            (alert) =>
              `<span class="${escapeHtml(
                alert.className
              )}"><i class="mdi ${escapeHtml(
                alert.icon
              )} me-1"></i>${escapeHtml(alert.label)}</span>`
          )
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
        "Vencimiento",
        proximoVencimiento,
        '<i class="mdi mdi-calendar-clock"></i>'
      ),
    ]
      .filter(Boolean)
      .join("");

    const checklist = Array.isArray(solicitud.checklist)
      ? solicitud.checklist
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
    const pendientesCriticos = [
      "revision-codigos",
      "espera-documentos",
      "apto-oftalmologo",
      "apto-anestesia",
    ];
    const checklistPreview = checklist
      .slice(0, 6)
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
              )} pendiente
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
    const checklistHtml =
      checklist.length > 0
        ? `<div class="kanban-checklist mt-2">
            <div class="d-flex justify-content-between align-items-center gap-2">
              <small class="text-muted">${escapeHtml(
                `${pasosCompletos}/${pasosTotales} pasos`
              )} · Próximo: ${escapeHtml(proximoPaso || "—")}</small>
              <span class="badge bg-light text-dark">${escapeHtml(
                `${porcentaje}%`
              )}</span>
            </div>
            <div class="progress progress-thin my-1" style="height: 6px;">
              <div class="progress-bar bg-success" role="progressbar" style="width: ${porcentaje}%;"
                aria-valuenow="${porcentaje}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="kanban-checklist-items">${checklistPreview}</div>
          </div>`
        : "";

    const metaBadges = [
      `<span class="badge bg-light text-dark">HC ${escapeHtml(
        solicitud.hc_number ?? "—"
      )}</span>`,
      `<span class="badge bg-light text-dark"><i class="mdi mdi-calendar"></i> ${escapeHtml(
        fechaFormateada
      )}</span>`,
      `<span class="badge bg-light text-dark"><i class="mdi mdi-account-tie-outline"></i> ${escapeHtml(
        doctor
      )}</span>`,
      `<span class="badge bg-light text-dark"><i class="mdi mdi-hospital-building"></i> ${escapeHtml(
        afiliacion
      )}</span>`,
      `<span class="badge bg-light text-dark"><i class="mdi mdi-eye-outline"></i> ${escapeHtml(
        ojo
      )}</span>`,
    ].join(" ");

    const observacionPreview =
      observacion && observacion.length > 80
        ? `${observacion.substring(0, 77)}...`
        : observacion;

    tarjeta.innerHTML = `
            <div class="kanban-card-header">
                <div class="kanban-card-body">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <strong>${escapeHtml(pacienteNombre)}</strong>
                            <div class="text-primary fw-bold small">${escapeHtml(
                              procedimiento
                            )}</div>
                        </div>
                        <div class="text-end small">
                            ${slaBadgeHtml}
                            <div class="text-muted">${escapeHtml(
                              slaSubtitle
                            )}</div>
                        </div>
                    </div>
                    <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                        ${prioridadBadgeHtml}
                        ${metaBadges}
                    </div>
                    ${
                      observacionPreview
                        ? `<div class="small text-muted mt-1">💬 ${escapeHtml(
                            observacionPreview
                          )}</div>`
                        : ""
                    }
                    ${alertsHtml}
                    ${checklistHtml}
                </div>
            </div>
            <div class="kanban-card-crm mt-2">
                <span class="crm-pill"><i class="mdi mdi-progress-check"></i>${escapeHtml(
                  pipelineStage
                )}</span>
                <div class="crm-meta">
                    <span><i class="mdi mdi-account-tie-outline"></i>${escapeHtml(
                      responsable
                    )}</span>
                    ${
                      fuente
                        ? `<span><i class="mdi mdi-source-branch"></i>${escapeHtml(
                            fuente
                          )}</span>`
                        : ""
                    }
                </div>
                <div class="crm-badges">${badges}</div>
            </div>
        `;

    hydrateAvatar(tarjeta);

    const turnoAsignado = formatTurno(solicitud.turno);
    const estadoActualSlug = estadoSlug;
    const estadoActualLabel = estadoLabel;
    const estadoNormalizado = normalizarEstado(estadoActualSlug);

    const acciones = document.createElement("div");
    acciones.className =
      "kanban-card-actions d-flex align-items-center justify-content-between gap-2 flex-wrap mt-2";

    const resumenEstado = document.createElement("span");
    resumenEstado.className = "badge badge-estado text-bg-light text-wrap";
    resumenEstado.textContent =
      estadoActualLabel !== "" ? estadoActualLabel : "Sin estado";
    acciones.appendChild(resumenEstado);

    const badgeTurno = document.createElement("span");
    badgeTurno.className = "badge badge-turno";
    badgeTurno.textContent = turnoAsignado
      ? `Turno #${turnoAsignado}`
      : "Sin turno asignado";
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
      botonLlamar.innerHTML =
        '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando';

      let exito = false;

      llamarTurnoSolicitud({ id: solicitud.id })
        .then((data) => {
          const turno = formatTurno(data?.turno);
          const nombre =
            data?.full_name ?? solicitud.full_name ?? "Paciente sin nombre";

          if (turno) {
            badgeTurno.textContent = `Turno #${turno}`;
          } else {
            badgeTurno.textContent = "Sin turno asignado";
          }

          const estadoActualizado = (data?.estado ?? "").toString();
          tarjeta.dataset.estado = estadoActualizado;
          resumenEstado.textContent =
            estadoActualizado !== "" ? estadoActualizado : "Sin estado";

          applyTurnoButtonState(
            botonLlamar,
            Boolean(turno) || normalizarEstado(estadoActualizado) === "llamado"
          );
          exito = true;

          showToast(
            `🔔 Turno asignado para ${nombre}${turno ? ` (#${turno})` : ""}`
          );

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
    tarjeta.appendChild(acciones);

    const crmButton = document.createElement("button");
    crmButton.type = "button";
    crmButton.className =
      "btn btn-sm btn-outline-secondary w-100 mt-2 btn-open-crm";
    crmButton.innerHTML =
      '<i class="mdi mdi-account-box-outline"></i> Gestionar CRM';
    crmButton.dataset.solicitudId = solicitud.id ?? "";
    crmButton.dataset.pacienteNombre = solicitud.full_name ?? "";
    tarjeta.appendChild(crmButton);

    tarjeta.querySelectorAll("[data-checklist-toggle]").forEach((input) => {
      input.addEventListener("change", () => {
        const slug = input.dataset.etapaSlug || "";
        const marcado = input.checked;
        input.disabled = true;

        const resultado = onEstadoChange(
          solicitud.id,
          solicitud.form_id,
          slug,
          { completado: marcado }
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
          : "Sin turno asignado";
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
                badgeTurno.textContent = turnoActual
                  ? `Turno #${turnoActual}`
                  : "Sin turno asignado";
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
