import { showToast } from './toast.js';
import { llamarTurnoExamen, formatTurno } from './turnero.js';

const ESCAPE_MAP = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
    '`': '&#96;',
};

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
    const initials = escapeHtml(getInitials(nombre || ''));

    if (avatarUrl) {
        return `
            <div class="kanban-avatar" data-avatar-root>
                <img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(alt)}" loading="lazy" data-avatar-img>
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
        .querySelectorAll('.kanban-avatar[data-avatar-root]')
        .forEach((avatar) => {
            const img = avatar.querySelector('[data-avatar-img]');
            const placeholder = avatar.querySelector('[data-avatar-placeholder]');

            if (!placeholder) {
                return;
            }

            if (!img) {
                placeholder.classList.remove('d-none');
                avatar.classList.add('kanban-avatar--placeholder');
                return;
            }

            const showPlaceholder = () => {
                placeholder.classList.remove('d-none');
                avatar.classList.add('kanban-avatar--placeholder');
                if (img.parentElement === avatar) {
                    img.remove();
                }
            };

            img.addEventListener('error', showPlaceholder, { once: true });

            if (img.complete && img.naturalWidth === 0) {
                showPlaceholder();
            }
        });
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

export function renderKanban(data, callbackEstadoActualizado) {
    document.querySelectorAll('.kanban-items').forEach(col => {
        col.innerHTML = '';
    });

    const hoy = new Date();

    data.forEach(examen => {
        const tarjeta = document.createElement('div');
        tarjeta.className = 'kanban-card border p-2 mb-2 rounded bg-light view-details';
        tarjeta.setAttribute('draggable', 'true');
        tarjeta.dataset.hc = examen.hc_number ?? '';
        tarjeta.dataset.form = examen.form_id ?? '';
        tarjeta.dataset.codigo = examen.examen_codigo ?? '';
        tarjeta.dataset.estado = examen.estado ?? '';
        tarjeta.dataset.id = examen.id ?? '';
        tarjeta.dataset.afiliacion = examen.afiliacion ?? '';
        tarjeta.dataset.aseguradora = examen.aseguradora ?? examen.aseguradoraNombre ?? '';
        tarjeta.dataset.examenNombre = examenNombre;
        tarjeta.dataset.prefacturaTrigger = 'kanban';

        const fecha = examen.consulta_fecha
            ? new Date(examen.consulta_fecha)
            : (examen.created_at ? new Date(examen.created_at) : null);
        const fechaFormateada = fecha ? moment(fecha).format('DD-MM-YYYY') : '—';
        const dias = fecha ? Math.floor((hoy - fecha) / (1000 * 60 * 60 * 24)) : 0;
        const semaforo = dias <= 3 ? '🟢 Normal' : dias <= 7 ? '🟡 Pendiente' : '🔴 Urgente';

        const kanbanPrefs = window.__crmKanbanPreferences ?? {};
        const defaultPipelineStage = Array.isArray(kanbanPrefs.pipelineStages) && kanbanPrefs.pipelineStages.length
            ? kanbanPrefs.pipelineStages[0]
            : 'Recibido';
        const pipelineStage = examen.crm_pipeline_stage || defaultPipelineStage;
        const responsable = examen.crm_responsable_nombre || 'Sin responsable asignado';
        const doctorNombre = (examen.doctor ?? '').trim();
        const doctor = doctorNombre !== '' ? doctorNombre : 'Sin doctor';
        const avatarNombre = doctorNombre !== '' ? doctorNombre : responsable;
        const avatarUrl = examen.doctor_avatar || examen.crm_responsable_avatar || null;
        const contactoTelefono = examen.crm_contacto_telefono || examen.paciente_celular || 'Sin teléfono';
        const contactoCorreo = examen.crm_contacto_email || 'Sin correo';
        const fuente = examen.crm_fuente || '';
        const totalNotas = Number.parseInt(examen.crm_total_notas ?? 0, 10);
        const totalAdjuntos = Number.parseInt(examen.crm_total_adjuntos ?? 0, 10);
        const tareasPendientes = Number.parseInt(examen.crm_tareas_pendientes ?? 0, 10);
        const tareasTotal = Number.parseInt(examen.crm_tareas_total ?? 0, 10);
        const proximoVencimiento = examen.crm_proximo_vencimiento
            ? moment(examen.crm_proximo_vencimiento).format('DD-MM-YYYY')
            : 'Sin vencimiento';

        const pacienteNombre = examen.full_name ?? 'Paciente sin nombre';
        const examenNombre = examen.examen || examen.examen_nombre || 'Sin examen';
        const afiliacion = examen.afiliacion || 'Sin afiliación';
        const lateralidad = examen.lateralidad || '—';
        const observaciones = examen.observaciones || 'Sin nota';

        const badges = [
            formatBadge('Notas', totalNotas, '<i class="mdi mdi-note-text-outline"></i>'),
            formatBadge('Adjuntos', totalAdjuntos, '<i class="mdi mdi-paperclip"></i>'),
            formatBadge('Tareas', `${tareasPendientes}/${tareasTotal}`, '<i class="mdi mdi-format-list-checks"></i>'),
            formatBadge('Vencimiento', proximoVencimiento, '<i class="mdi mdi-calendar-clock"></i>'),
        ].filter(Boolean).join('');

        tarjeta.innerHTML = `
            <div class="kanban-card-header">
                ${renderAvatar(avatarNombre, avatarUrl)}
                <div class="kanban-card-body">
                    <strong>${escapeHtml(pacienteNombre)}</strong>
                    <small>🆔 ${escapeHtml(examen.hc_number ?? '—')}</small>
                    <small>📅 ${escapeHtml(fechaFormateada)} <span class="badge">${escapeHtml(semaforo)}</span></small>
                    <small>🧑‍⚕️ ${escapeHtml(doctor)}</small>
                    <small>🏥 ${escapeHtml(afiliacion)}</small>
                    <small>🔍 <span class="text-primary fw-bold">${escapeHtml(examenNombre)}</span></small>
                    <small>👁️ ${escapeHtml(lateralidad)}</small>
                    <small>💬 ${escapeHtml(observaciones)}</small>
                    <small>⏱️ ${escapeHtml(String(dias))} día(s) en estado actual</small>
                </div>
            </div>
            <div class="kanban-card-crm mt-2">
                <span class="crm-pill"><i class="mdi mdi-progress-check"></i>${escapeHtml(pipelineStage)}</span>
                <div class="crm-meta">
                    <span><i class="mdi mdi-account-tie-outline"></i>${escapeHtml(responsable)}</span>
                    <span><i class="mdi mdi-phone"></i>${escapeHtml(contactoTelefono)}</span>
                    <span><i class="mdi mdi-email-outline"></i>${escapeHtml(contactoCorreo)}</span>
                    ${fuente ? `<span><i class="mdi mdi-source-branch"></i>${escapeHtml(fuente)}</span>` : ''}
                </div>
                <div class="crm-badges">${badges}</div>
            </div>
        `;

        hydrateAvatar(tarjeta);

        const turnoAsignado = formatTurno(examen.turno);
        const estadoActual = (examen.estado ?? '').toString();

        const acciones = document.createElement('div');
        acciones.className = 'kanban-card-actions d-flex align-items-center justify-content-between gap-2 flex-wrap mt-2';

        const resumenEstado = document.createElement('span');
        resumenEstado.className = 'badge badge-estado text-bg-light text-wrap';
        resumenEstado.textContent = estadoActual !== '' ? estadoActual : 'Sin estado';
        acciones.appendChild(resumenEstado);

        const badgeTurno = document.createElement('span');
        badgeTurno.className = 'badge badge-turno';
        badgeTurno.textContent = turnoAsignado ? `Turno #${turnoAsignado}` : 'Sin turno asignado';
        acciones.appendChild(badgeTurno);

        const botonLlamar = document.createElement('button');
        botonLlamar.type = 'button';
        botonLlamar.className = 'btn btn-sm btn-outline-primary llamar-turno-btn';
        botonLlamar.innerHTML = turnoAsignado ? '<i class="mdi mdi-phone-incoming"></i> Volver a llamar' : '<i class="mdi mdi-bell-ring-outline"></i> Generar turno';

        botonLlamar.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();

            if (botonLlamar.disabled) {
                return;
            }

            botonLlamar.disabled = true;
            botonLlamar.setAttribute('aria-busy', 'true');
            const textoOriginal = botonLlamar.innerHTML;
            botonLlamar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando';

            llamarTurnoExamen({ id: examen.id })
                .then(data => {
                    const turno = formatTurno(data?.turno);
                    const nombre = data?.full_name ?? examen.full_name ?? 'Paciente sin nombre';

                    if (turno) {
                        badgeTurno.textContent = `Turno #${turno}`;
                    }

                    if (data?.estado) {
                        resumenEstado.textContent = data.estado;
                    }

                    showToast(`🔔 Turno asignado para ${nombre}${turno ? ` (#${turno})` : ''}`);

                    if (Array.isArray(window.__examenesKanban)) {
                        const item = window.__examenesKanban.find(s => String(s.id) === String(examen.id));
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
                    botonLlamar.disabled = false;
                    botonLlamar.removeAttribute('aria-busy');
                    botonLlamar.innerHTML = textoOriginal;
                });
        });

        acciones.appendChild(botonLlamar);
        tarjeta.appendChild(acciones);

        const crmButton = document.createElement('button');
        crmButton.type = 'button';
        crmButton.className = 'btn btn-sm btn-outline-secondary w-100 mt-2 btn-open-crm';
        crmButton.innerHTML = '<i class="mdi mdi-account-box-outline"></i> Gestionar CRM';
        crmButton.dataset.examenId = examen.id ?? '';
        crmButton.dataset.pacienteNombre = examen.full_name ?? '';
        tarjeta.appendChild(crmButton);

        const estadoId = 'kanban-' + (examen.estado || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-');

        const columna = document.getElementById(estadoId);
        if (columna) {
            columna.appendChild(tarjeta);
        }
    });

    document.querySelectorAll('.kanban-items').forEach(container => {
        new Sortable(container, {
            group: 'kanban',
            animation: 150,
            onEnd: evt => {
                const item = evt.item;
                const nuevoEstado = evt.to.id
                    .replace('kanban-', '')
                    .replace(/-/g, ' ')
                    .replace(/\b\w/g, c => c.toUpperCase());

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
    });
}
