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

function slugifyEstado(value) {
    const raw = (value ?? '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    // Normalizar slugs conocidos que difieren entre front y back.
    if (raw === 'revision-de-cobertura') {
        return 'revision-codigos';
    }

    if (raw === 'revision-cobertura') {
        return 'revision-codigos';
    }

    return raw;
}

function estadoLabelFromSlug(slug) {
    if (!slug) {
        return 'Sin estado';
    }

    const meta = window.__examenesEstadosMeta ?? {};
    const key = slugifyEstado(slug);
    const entry = meta[key] || meta[slug];

    if (entry && entry.label) {
        return entry.label;
    }

    return slug;
}

const TURNO_BUTTON_LABELS = {
    recall: '<i class="mdi mdi-phone-incoming"></i> Volver a llamar',
    generate: '<i class="mdi mdi-bell-ring-outline"></i> Generar turno',
};

function applyTurnoButtonState(button, shouldRecall) {
    if (!button) {
        return;
    }

    button.innerHTML = shouldRecall
        ? TURNO_BUTTON_LABELS.recall
        : TURNO_BUTTON_LABELS.generate;
    button.dataset.hasTurno = shouldRecall ? '1' : '0';
}

export function renderKanban(data, callbackEstadoActualizado) {
    document.querySelectorAll('.kanban-items').forEach(col => {
        col.innerHTML = '';
    });

    const onEstadoChange = typeof callbackEstadoActualizado === 'function'
        ? callbackEstadoActualizado
        : () => Promise.resolve();

    const hoy = new Date();

    data.forEach(examen => {
        const tarjeta = document.createElement('div');
        tarjeta.className = 'kanban-card border p-2 mb-2 rounded bg-light view-details';
        tarjeta.setAttribute('draggable', 'true');
        tarjeta.dataset.hc = examen.hc_number ?? '';
        tarjeta.dataset.form = examen.form_id ?? '';
        tarjeta.dataset.codigo = examen.examen_codigo ?? '';
        const examenNombre = examen.examen || examen.examen_nombre || 'Sin examen';
        const estadoBase = examen.kanban_estado ?? examen.estado;
        const estadoSlug = slugifyEstado(estadoBase);
        const estadoLabel =
            examen.estado_label ||
            examen.kanban_estado_label ||
            estadoLabelFromSlug(estadoSlug) ||
            estadoBase ||
            'Sin estado';
        tarjeta.dataset.estado = estadoSlug;
        tarjeta.dataset.estadoLabel = estadoLabel;
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
        const afiliacion = examen.afiliacion || 'Sin afiliación';
        const lateralidad = examen.lateralidad || '—';
        const observaciones = examen.observaciones || 'Sin nota';

        const badges = [
            formatBadge('Notas', totalNotas, '<i class="mdi mdi-note-text-outline"></i>'),
            formatBadge('Adjuntos', totalAdjuntos, '<i class="mdi mdi-paperclip"></i>'),
            formatBadge('Tareas', `${tareasPendientes}/${tareasTotal}`, '<i class="mdi mdi-format-list-checks"></i>'),
            formatBadge('Vencimiento', proximoVencimiento, '<i class="mdi mdi-calendar-clock"></i>'),
        ].filter(Boolean).join('');

        const checklist = Array.isArray(examen.checklist) ? examen.checklist : [];
        const checklistProgress = examen.checklist_progress || {};
        const pasosTotales = checklistProgress.total ?? (Array.isArray(checklist) ? checklist.length : 0) ?? 0;
        const pasosCompletos = checklistProgress.completed ?? 0;
        const porcentaje = checklistProgress.percent ?? (pasosTotales ? Math.round((pasosCompletos / pasosTotales) * 100) : 0);
        const pendientesCriticos = ['revision-codigos', 'espera-documentos', 'apto-oftalmologo', 'apto-anestesia'];

        const checklistPreview = checklist.map(item => {
            const slug = slugifyEstado(item.slug);
            const isCriticalPending = !item.completed && pendientesCriticos.includes(slug);

            if (item.completed) {
                return `<label class="form-check small mb-1">
                    <input type="checkbox" class="form-check-input" data-checklist-toggle data-etapa-slug="${escapeHtml(item.slug)}" checked ${item.can_toggle ? '' : 'disabled'}>
                    <span class="ms-1">✅ ${escapeHtml(item.label)}</span>
                </label>`;
            }

            if (isCriticalPending) {
                return `<div class="small mb-1 text-warning">
                    <i class="mdi mdi-alert-outline me-1"></i>${escapeHtml(item.label)}
                </div>`;
            }

            return `<label class="form-check small mb-1">
                <input type="checkbox" class="form-check-input" data-checklist-toggle data-etapa-slug="${escapeHtml(item.slug)}" ${item.can_toggle ? '' : 'disabled'}>
                <span class="ms-1">⬜ ${escapeHtml(item.label)}</span>
            </label>`;
        }).join('');

        const checklistHtml = checklist.length > 0
            ? `<div class="kanban-checklist mt-2">
                <div class="d-flex justify-content-end align-items-center gap-2">
                    <span class="badge bg-light text-dark">${escapeHtml(`${porcentaje}%`)}</span>
                </div>
                <div class="progress progress-thin my-1" style="height: 6px;">
                    <div class="progress-bar bg-success" role="progressbar" style="width: ${porcentaje}%;"
                        aria-valuenow="${porcentaje}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="kanban-checklist-items">${checklistPreview}</div>
            </div>`
            : '';

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
            ${checklistHtml}
        `;

        hydrateAvatar(tarjeta);

        const turnoAsignado = formatTurno(examen.turno);
        const estadoActualSlug = estadoSlug;
        const estadoActualLabel = estadoLabel;

        const acciones = document.createElement('div');
        acciones.className = 'kanban-card-actions d-flex align-items-center justify-content-between gap-2 flex-wrap mt-2';

        const resumenEstado = document.createElement('span');
        resumenEstado.className = 'badge badge-estado text-bg-light text-wrap';
        resumenEstado.textContent = estadoActualLabel !== '' ? estadoActualLabel : 'Sin estado';
        acciones.appendChild(resumenEstado);

        const badgeTurno = document.createElement('span');
        badgeTurno.className = 'badge badge-turno';
        badgeTurno.textContent = turnoAsignado ? `Turno #${turnoAsignado}` : 'Sin turno asignado';
        acciones.appendChild(badgeTurno);

        const botonLlamar = document.createElement('button');
        botonLlamar.type = 'button';
        botonLlamar.className = 'btn btn-sm btn-outline-primary llamar-turno-btn';
        applyTurnoButtonState(botonLlamar, Boolean(turnoAsignado) || estadoActualSlug === 'llamado');

        botonLlamar.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();

            if (botonLlamar.disabled) {
                return;
            }

            const teniaTurnoAntes = botonLlamar.dataset.hasTurno === '1';
            botonLlamar.disabled = true;
            botonLlamar.setAttribute('aria-busy', 'true');
            const textoOriginal = botonLlamar.innerHTML;
            botonLlamar.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando';
            let exito = false;

            llamarTurnoExamen({ id: examen.id })
                .then(data => {
                    const turno = formatTurno(data?.turno);
                    const nombre = data?.full_name ?? examen.full_name ?? 'Paciente sin nombre';
                    const estadoRespuesta = (data?.estado ?? estadoActualLabel ?? '').toString();
                    const estadoRespuestaSlug = slugifyEstado(data?.estado_slug ?? data?.kanban_estado ?? estadoRespuesta);
                    const estadoRespuestaLabel =
                        data?.estado_label ?? estadoLabelFromSlug(estadoRespuestaSlug) ?? estadoRespuesta;

                    if (turno) {
                        badgeTurno.textContent = `Turno #${turno}`;
                    }

                    if (data?.estado) {
                        tarjeta.dataset.estado = estadoRespuestaSlug;
                        tarjeta.dataset.estadoLabel = estadoRespuestaLabel;
                        resumenEstado.textContent = estadoRespuestaLabel !== '' ? estadoRespuestaLabel : 'Sin estado';
                    }

                    applyTurnoButtonState(
                        botonLlamar,
                        Boolean(turno) || estadoRespuestaSlug === 'llamado'
                    );
                    exito = true;

                    const destinoId = estadoRespuestaSlug ? `kanban-${estadoRespuestaSlug}` : null;
                    if (destinoId && destinoId !== tarjeta.parentElement?.id) {
                        const destino = document.getElementById(destinoId);
                        if (destino) {
                            destino.appendChild(tarjeta);
                        }
                    }

                    showToast(`🔔 Turno asignado para ${nombre}${turno ? ` (#${turno})` : ''}`);

                    if (Array.isArray(window.__examenesKanban)) {
                        const item = window.__examenesKanban.find(s => String(s.id) === String(examen.id));
                        if (item) {
                            item.turno = data?.turno ?? item.turno;
                            item.estado = estadoRespuestaLabel || item.estado;
                            item.estado_label = estadoRespuestaLabel || item.estado_label;
                            item.kanban_estado = estadoRespuestaSlug || item.kanban_estado;
                            item.kanban_estado_label = estadoRespuestaLabel || item.kanban_estado_label;
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
                    if (!exito) {
                        applyTurnoButtonState(botonLlamar, teniaTurnoAntes);
                        botonLlamar.innerHTML = textoOriginal;
                    }
                    if (exito) {
                        botonLlamar.innerHTML = botonLlamar.innerHTML || textoOriginal;
                    }
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

        tarjeta.querySelectorAll('[data-checklist-toggle]').forEach(input => {
            input.addEventListener('click', e => e.stopPropagation());
            input.addEventListener('change', () => {
                const slug = input.dataset.etapaSlug || '';
                const marcado = input.checked;
                input.disabled = true;

                const resultado = onEstadoChange(
                    examen.id,
                    examen.form_id,
                    slug,
                    { completado: marcado }
                );

                const revert = () => {
                    input.checked = !marcado;
                };

                if (resultado && typeof resultado.then === 'function') {
                    resultado
                        .then(resp => {
                            examen.checklist = resp?.checklist ?? examen.checklist;
                            examen.checklist_progress = resp?.checklist_progress ?? examen.checklist_progress;
                            examen.kanban_estado = resp?.kanban_estado ?? examen.kanban_estado;
                            examen.kanban_estado_label = resp?.kanban_estado_label ?? examen.kanban_estado_label;
                            if (typeof window.aplicarFiltros === 'function') {
                                window.aplicarFiltros();
                            }
                        })
                        .catch(error => {
                            revert();
                            if (!error || !error.__estadoNotificado) {
                                const mensaje = (error && error.message) || 'No se pudo actualizar el checklist';
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

        const estadoId = `kanban-${estadoSlug}`;

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
                const columnaAnterior = evt.from;
                const posicionAnterior = evt.oldIndex;
                const estadoAnteriorSlug = (item.dataset.estado ?? '').toString();
                const estadoAnteriorLabel =
                    item.dataset.estadoLabel || estadoLabelFromSlug(estadoAnteriorSlug) || 'Sin estado';
                const badgeEstado = item.querySelector('.badge.badge-estado, .badge-estado');
                const badgeTurno = item.querySelector('.badge-turno');
                const botonTurno = item.querySelector('.llamar-turno-btn');
                const turnoTextoAnterior = badgeTurno ? badgeTurno.textContent : 'Sin turno asignado';
                const botonTeniaTurnoAntes = botonTurno ? botonTurno.dataset.hasTurno === '1' : false;

                const nuevoEstadoSlug = slugifyEstado(evt.to.id.replace('kanban-', ''));
                const nuevoEstadoLabel = estadoLabelFromSlug(nuevoEstadoSlug);

                const aplicarEstadoEnUI = (slug, label) => {
                    const etiqueta = label || estadoLabelFromSlug(slug) || (slug ?? '').toString();
                    item.dataset.estado = slug;
                    item.dataset.estadoLabel = etiqueta;
                    if (badgeEstado) {
                        badgeEstado.textContent = etiqueta !== '' ? etiqueta : 'Sin estado';
                    }
                    if (botonTurno) {
                        const debeRecordar = botonTeniaTurnoAntes || slug === 'llamado';
                        applyTurnoButtonState(botonTurno, debeRecordar);
                    }
                };

                const revertirMovimiento = () => {
                    aplicarEstadoEnUI(estadoAnteriorSlug, estadoAnteriorLabel);
                    if (badgeTurno) {
                        badgeTurno.textContent = turnoTextoAnterior;
                    }
                    if (botonTurno) {
                        applyTurnoButtonState(botonTurno, botonTeniaTurnoAntes);
                    }
                    if (columnaAnterior) {
                        const referencia = columnaAnterior.children[posicionAnterior] || null;
                        columnaAnterior.insertBefore(item, referencia);
                    }
                };

                aplicarEstadoEnUI(nuevoEstadoSlug, nuevoEstadoLabel);

                let resultado;
                try {
                    resultado = onEstadoChange(
                        item.dataset.id,
                        item.dataset.form,
                        nuevoEstadoLabel,
                        { estado_slug: nuevoEstadoSlug }
                    );
                } catch (error) {
                    revertirMovimiento();

                    if (!error || !error.__estadoNotificado) {
                        const mensaje = (error && error.message) || 'No se pudo actualizar el estado';
                        showToast(mensaje, false);
                    }
                    return;
                }

                if (resultado && typeof resultado.then === 'function') {
                    resultado
                        .then(response => {
                            const estadoServidor = (response?.estado ?? nuevoEstadoLabel ?? '').toString();
                            const estadoServidorLabel = (response?.estado_label ?? estadoServidor).toString();
                            const estadoServidorSlug = slugifyEstado(
                                response?.estado_slug ?? response?.kanban_estado ?? estadoServidor
                            );
                            aplicarEstadoEnUI(estadoServidorSlug, estadoServidorLabel);

                            const destinoId = estadoServidorSlug ? `kanban-${estadoServidorSlug}` : null;
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
                                    : 'Sin turno asignado';
                            }

                            if (botonTurno) {
                                const turnoActual = formatTurno(response?.turno);
                                const debeRecordar =
                                    Boolean(turnoActual) || estadoServidorSlug === 'llamado';
                                applyTurnoButtonState(botonTurno, debeRecordar);
                            }
                        })
                        .catch(error => {
                            revertirMovimiento();

                            if (!error || !error.__estadoNotificado) {
                                const mensaje = (error && error.message) || 'No se pudo actualizar el estado';
                                showToast(mensaje, false);
                            }
                        });
                }
            },
        });
    });
}
