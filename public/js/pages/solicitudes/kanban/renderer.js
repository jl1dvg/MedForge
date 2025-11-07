import {showToast} from './toast.js';
import {llamarTurnoSolicitud, formatTurno} from './turnero.js';

const ESCAPE_MAP = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
    '`': '&#96;',
};
// ---- Date/locale helpers (reemplaza moment.js) ----
const TZ = 'America/Guayaquil';
const dateFmt = new Intl.DateTimeFormat('es-EC', {timeZone: TZ, day: '2-digit', month: '2-digit', year: 'numeric'});

function toLocalDateOnly(d) {
    const dt = d instanceof Date ? d : new Date(d);
    return new Date(dt.getFullYear(), dt.getMonth(), dt.getDate());
}

function formatDate(d) {
    if (!d) return '—';
    const dt = d instanceof Date ? d : new Date(d);
    return dateFmt.format(dt);
}

function daysBetween(a, b) {
    const da = toLocalDateOnly(a);
    const db = toLocalDateOnly(b);
    return Math.max(0, Math.floor((db - da) / 86400000));
}

function escapeHtml(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value).replace(/[&<>"'`]/g, character => ESCAPE_MAP[character]);
}

function getInitials(nombre) {
    if (!nombre) {
        return '—';
    }

    const parts = nombre
        .replace(/\s+/g, ' ')
        .trim()
        .split(' ')
        .filter(Boolean);

    if (!parts.length) {
        return '—';
    }

    if (parts.length === 1) {
        return parts[0].substring(0, 2).toUpperCase();
    }

    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function renderAvatar(nombreResponsable, avatarUrl) {
    const nombre = nombreResponsable || '';
    const alt = nombre !== '' ? nombre : 'Responsable sin asignar';

    if (avatarUrl) {
        return `
            <div class="kanban-avatar">
                <img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(alt)}">
            </div>
        `;
    }

    return `
        <div class="kanban-avatar kanban-avatar--placeholder">
            <span>${escapeHtml(getInitials(nombre || ''))}</span>
        </div>
    `;
}

function formatBadge(label, value, icon) {
    const safeValue = escapeHtml(value ?? '');
    if (!safeValue) {
        return '';
    }

    const safeLabel = escapeHtml(label ?? '');
    const safeIcon = icon ? `${icon} ` : '';

    return `<span class="badge">${safeIcon}${safeLabel !== '' ? `${safeLabel}: ` : ''}${safeValue}</span>`;
}

function estadoIdFromSlug(slug) {
    return 'kanban-' + String(slug || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-');
}

function buildStateMap() {
    if (window.__KANBAN_STATE_MAP) return window.__KANBAN_STATE_MAP;
    const meta = window.__solicitudesEstadosMeta || {};
    const map = {};
    Object.keys(meta).forEach(slug => {
        const id = estadoIdFromSlug(slug);
        map[id] = meta[slug]?.label || slug;
    });
    window.__KANBAN_STATE_MAP = map;
    return map;
}

export function renderKanban(data, callbackEstadoActualizado) {
    const stateMap = buildStateMap();

    // Preparar columnas y fragments
    const columns = Array.from(document.querySelectorAll('.kanban-items'));
    const colMap = new Map(columns.map(col => [col.id, {el: col, frag: document.createDocumentFragment()}]));

    // Limpiar y marcar roles (accesibilidad)
    columns.forEach(col => {
        col.innerHTML = '';
        col.setAttribute('role', 'list');
    });

    const today = new Date();

    data.forEach(solicitud => {
        console.log(solicitud);
        const tarjeta = document.createElement('div');
        tarjeta.className = 'kanban-card border p-2 mb-2 rounded bg-light view-details';
        tarjeta.setAttribute('draggable', 'true');
        tarjeta.setAttribute('role', 'listitem');
        tarjeta.setAttribute('aria-label', 'Solicitud de cirugía');

        // dataset
        tarjeta.dataset.hc = solicitud.hc_number ?? '';
        tarjeta.dataset.form = solicitud.form_id ?? '';
        tarjeta.dataset.secuencia = solicitud.secuencia ?? '';
        tarjeta.dataset.estado = solicitud.estado ?? '';
        tarjeta.dataset.id = solicitud.id ?? '';
        tarjeta.dataset.afiliacion = solicitud.afiliacion ?? '';
        tarjeta.dataset.aseguradora = solicitud.aseguradora ?? solicitud.aseguradoraNombre ?? '';
        tarjeta.dataset.prefacturaTrigger = 'kanban';

        // fechas (sin moment)
        const fecha = solicitud.fecha ? new Date(solicitud.fecha) : null;
        const fechaFormateada = fecha ? formatDate(fecha) : '—';
        const dias = fecha ? daysBetween(fecha, today) : 0;
        const semaforo = dias <= 3 ? '🟢 Normal' : dias <= 7 ? '🟡 Pendiente' : '🔴 Urgente';

        // CRM fields
        const kanbanPrefs = window.__crmKanbanPreferences ?? {};
        const defaultPipelineStage = Array.isArray(kanbanPrefs.pipelineStages) && kanbanPrefs.pipelineStages.length
            ? kanbanPrefs.pipelineStages[0]
            : 'Recibido';
        const pipelineStage = solicitud.crm_pipeline_stage || defaultPipelineStage;
        const responsable = solicitud.crm_responsable_nombre || 'Sin responsable asignado';
        const avatarUrl = solicitud.doctor_avatar || null;
        const contactoTelefono = solicitud.crm_contacto_telefono || solicitud.paciente_celular || 'Sin teléfono';
        const contactoCorreo = solicitud.crm_contacto_email || 'Sin correo';
        const fuente = solicitud.crm_fuente || '';
        const totalNotas = Number.parseInt(solicitud.crm_total_notas ?? 0, 10);
        const totalAdjuntos = Number.parseInt(solicitud.crm_total_adjuntos ?? 0, 10);
        const tareasPendientes = Number.parseInt(solicitud.crm_tareas_pendientes ?? 0, 10);
        const tareasTotal = Number.parseInt(solicitud.crm_tareas_total ?? 0, 10);
        const proximoVencimiento = solicitud.crm_proximo_vencimiento
            ? formatDate(solicitud.crm_proximo_vencimiento)
            : 'Sin vencimiento';

        // info clínica
        const pacienteNombre = solicitud.full_name ?? 'Paciente sin nombre';
        const procedimiento = solicitud.procedimiento || 'Sin procedimiento';
        const doctor = solicitud.doctor || 'Sin doctor';
        const afiliacion = solicitud.afiliacion || 'Sin afiliación';
        const ojo = solicitud.ojo || '—';
        const observacion = solicitud.observacion || 'Sin nota';

        const badges = [
            formatBadge('Notas', totalNotas, '<i class="mdi mdi-note-text-outline"></i>'),
            formatBadge('Adjuntos', totalAdjuntos, '<i class="mdi mdi-paperclip"></i>'),
            formatBadge('Tareas', `${tareasPendientes}/${tareasTotal}`, '<i class="mdi mdi-format-list-checks"></i>'),
            formatBadge('Vencimiento', proximoVencimiento, '<i class="mdi mdi-calendar-clock"></i>'),
        ].filter(Boolean).join('');

        tarjeta.innerHTML = `
      <div class="kanban-card-header">
        ${renderAvatar(doctor, avatarUrl)}
        <div class="kanban-card-body">
          <strong>${escapeHtml(pacienteNombre)}</strong>
          <small>🆔 ${escapeHtml(solicitud.hc_number ?? '—')}</small>
          <small>📅 ${escapeHtml(fechaFormateada)} <span class="badge">${escapeHtml(semaforo)}</span></small>
          <small>🧑‍⚕️ ${escapeHtml(doctor)}</small>
          <small>🏥 ${escapeHtml(afiliacion)}</small>
          <small>🔍 <span class="text-primary fw-bold">${escapeHtml(procedimiento)}</span></small>
          <small>👁️ ${escapeHtml(ojo)}</small>
          <small>💬 ${escapeHtml(observacion)}</small>
          <small>⏱️ ${escapeHtml(String(dias))} día(s) en estado actual</small>
        </div>
      </div>
      <div class="kanban-card-crm mt-2">
        <span class="crm-pill" aria-label="Etapa CRM"><i class="mdi mdi-progress-check"></i>${escapeHtml(pipelineStage)}</span>
        <div class="crm-meta">
          <span><i class="mdi mdi-account-tie-outline"></i>${escapeHtml(responsable)}</span>
          <span><i class="mdi mdi-phone"></i>${escapeHtml(contactoTelefono)}</span>
          <span><i class="mdi mdi-email-outline"></i>${escapeHtml(contactoCorreo)}</span>
          ${fuente ? `<span><i class="mdi mdi-source-branch"></i>${escapeHtml(fuente)}</span>` : ''}
        </div>
        <div class="crm-badges">${badges}</div>
      </div>
    `;

        // Acciones
        const acciones = document.createElement('div');
        acciones.className = 'kanban-card-actions d-flex align-items-center justify-content-between gap-2 flex-wrap mt-2';

        const resumenEstado = document.createElement('span');
        resumenEstado.className = 'badge badge-estado text-bg-light text-wrap';
        resumenEstado.textContent = (solicitud.estado ?? '') !== '' ? solicitud.estado : 'Sin estado';
        acciones.appendChild(resumenEstado);

        const badgeTurno = document.createElement('span');
        badgeTurno.className = 'badge badge-turno';
        const turnoAsignado = formatTurno(solicitud.turno);
        badgeTurno.textContent = turnoAsignado ? `Turno #${turnoAsignado}` : 'Sin turno asignado';
        acciones.appendChild(badgeTurno);

        const botonLlamar = document.createElement('button');
        botonLlamar.type = 'button';
        botonLlamar.className = 'btn btn-sm btn-outline-primary llamar-turno-btn';
        botonLlamar.dataset.id = solicitud.id ?? '';
        botonLlamar.setAttribute('data-no-details', '1');
        botonLlamar.innerHTML = turnoAsignado
            ? '<i class="mdi mdi-phone-incoming"></i> Volver a llamar'
            : '<i class="mdi mdi-bell-ring-outline"></i> Generar turno';
        acciones.appendChild(botonLlamar);

        tarjeta.appendChild(acciones);

        const crmButton = document.createElement('button');
        crmButton.type = 'button';
        crmButton.className = 'btn btn-sm btn-outline-secondary w-100 mt-2 btn-open-crm';
        crmButton.innerHTML = '<i class="mdi mdi-account-box-outline"></i> Gestionar CRM';
        crmButton.dataset.solicitudId = solicitud.id ?? '';
        crmButton.dataset.pacienteNombre = solicitud.full_name ?? '';
        tarjeta.appendChild(crmButton);

        const estadoContainerId = estadoIdFromSlug(solicitud.estado || '');
        const bucket = colMap.get(estadoContainerId)?.frag;

        if (bucket) {
            bucket.appendChild(tarjeta);
        } else {
            // Fallback si no existe columna
            const fallbackId = 'kanban-sin-estado';
            const fbBucket = colMap.get(fallbackId)?.frag || colMap.values().next().value?.frag;
            (fbBucket || document.body).appendChild(tarjeta);
        }
    });

    // Montar fragments en una sola pasada
    for (const {el, frag} of colMap.values()) {
        el.appendChild(frag);
    }

    // Sortable: una sola vez por columna
    if (!window.__kanbanSortables) window.__kanbanSortables = new Map();
    columns.forEach(container => {
        if (window.__kanbanSortables.has(container)) return;
        const sortable = new Sortable(container, {
            group: 'kanban',
            animation: 150,
            onEnd: evt => {
                const item = evt.item;
                const nuevoId = evt.to.id;
                const nuevoEstado = stateMap[nuevoId] || 'Recibido';

                item.dataset.estado = nuevoEstado;

                const resultado = callbackEstadoActualizado(
                    item.dataset.id,
                    item.dataset.form,
                    nuevoEstado
                );

                if (resultado && typeof resultado.catch === 'function') {
                    resultado.catch(() => {
                        showToast('No se pudo actualizar el estado', false);
                    });
                }
            },
        });
        window.__kanbanSortables.set(container, sortable);
    });

    // Delegación para "Llamar turno": un solo listener global
    if (!window.__kanbanDelegatedHandlers) window.__kanbanDelegatedHandlers = {};
    if (!window.__kanbanDelegatedHandlers.llamarTurno) {
        window.__kanbanDelegatedHandlers.llamarTurno = true;
        document.body.addEventListener('click', (e) => {
            const btn = e.target.closest('.llamar-turno-btn');
            if (!btn) return;

            // Evitar que el click en "Llamar turno" dispare el modal de detalles
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }

            const card = btn.closest('.kanban-card');
            const badgeTurno = card?.querySelector('.badge-turno');
            const resumenEstado = card?.querySelector('.badge-estado');
            const solicitudId = btn.dataset.id;

            if (!solicitudId) return;

            if (btn.disabled) return;
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            const original = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando';

            llamarTurnoSolicitud({id: solicitudId})
                .then(data => {
                    const turno = formatTurno(data?.turno);
                    const nombre = data?.full_name ?? 'Paciente sin nombre';

                    if (turno && badgeTurno) {
                        badgeTurno.textContent = `Turno #${turno}`;
                    }
                    if (data?.estado && resumenEstado) {
                        resumenEstado.textContent = data.estado;
                    }

                    showToast(`🔔 Turno asignado para ${nombre}${turno ? ` (#${turno})` : ''}`);

                    if (Array.isArray(window.__solicitudesKanban)) {
                        const item = window.__solicitudesKanban.find(s => String(s.id) === String(solicitudId));
                        if (item) {
                            item.turno = data?.turno ?? item.turno;
                            item.estado = data?.estado ?? item.estado;
                        }
                    }

                    if (typeof window.aplicarFiltros === 'function') {
                        window.aplicarFiltros();
                    }
                })
                .catch(error => {
                    console.error('❌ Error al llamar el turno:', error);
                    showToast(error?.message ?? 'No se pudo asignar el turno', false);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.removeAttribute('aria-busy');
                    btn.innerHTML = original;
                });
        }, {passive: true});
    }
}