import { showToast } from './toast.js';
import { llamarTurnoSolicitud, formatTurno } from './turnero.js';

export function renderKanban(data, callbackEstadoActualizado) {
    document.querySelectorAll('.kanban-items').forEach(col => {
        col.innerHTML = '';
    });

    const hoy = new Date();

    data.forEach(solicitud => {
        const tarjeta = document.createElement('div');
        tarjeta.className = 'kanban-card border p-2 mb-2 rounded bg-light view-details';
        tarjeta.setAttribute('draggable', 'true');
        tarjeta.dataset.hc = solicitud.hc_number ?? '';
        tarjeta.dataset.form = solicitud.form_id ?? '';
        tarjeta.dataset.secuencia = solicitud.secuencia ?? '';
        tarjeta.dataset.estado = solicitud.estado ?? '';
        tarjeta.dataset.id = solicitud.id ?? '';

        const fecha = solicitud.fecha ? new Date(solicitud.fecha) : null;
        const fechaFormateada = fecha ? moment(fecha).format('DD-MM-YYYY') : '—';
        const dias = fecha ? Math.floor((hoy - fecha) / (1000 * 60 * 60 * 24)) : 0;
        const semaforo = dias <= 3 ? '🟢 Normal' : dias <= 7 ? '🟡 Pendiente' : '🔴 Urgente';

        const pipelineStage = solicitud.crm_pipeline_stage || 'Recibido';
        const responsable = solicitud.crm_responsable_nombre || 'Sin responsable asignado';
        const contactoTelefono = solicitud.crm_contacto_telefono || solicitud.paciente_celular || 'Sin teléfono';
        const contactoCorreo = solicitud.crm_contacto_email || 'Sin correo';
        const fuente = solicitud.crm_fuente || '';
        const totalNotas = Number.parseInt(solicitud.crm_total_notas ?? 0, 10);
        const totalAdjuntos = Number.parseInt(solicitud.crm_total_adjuntos ?? 0, 10);
        const tareasPendientes = Number.parseInt(solicitud.crm_tareas_pendientes ?? 0, 10);
        const tareasTotal = Number.parseInt(solicitud.crm_tareas_total ?? 0, 10);
        const proximoVencimiento = solicitud.crm_proximo_vencimiento
            ? moment(solicitud.crm_proximo_vencimiento).format('DD-MM-YYYY')
            : 'Sin vencimiento';

        tarjeta.innerHTML = `
            <div class="d-flex flex-column gap-1">
                <strong>👤 ${solicitud.full_name ?? 'Paciente sin nombre'}</strong>
                <small>🆔 ${solicitud.hc_number ?? '—'}</small>
                <small>📅 ${fechaFormateada} <span class="badge">${semaforo}</span></small>
                <small>🧑‍⚕️ ${solicitud.doctor || 'Sin doctor'}</small>
                <small>🏥 ${solicitud.afiliacion || 'Sin afiliación'}</small>
                <small>🔍 <span class="text-primary fw-bold">${solicitud.procedimiento || 'Sin procedimiento'}</span></small>
                <small>👁️ ${solicitud.ojo || '—'}</small>
                <small>💬 ${(solicitud.observacion || 'Sin nota')}</small>
                <small>⏱️ ${dias} día(s) en estado actual</small>
            </div>
            <div class="kanban-card-crm mt-2">
                <span class="crm-pill"><i class="mdi mdi-progress-check"></i>${pipelineStage}</span>
                <div class="crm-meta">
                    <span><i class="mdi mdi-account-tie-outline"></i>${responsable}</span>
                    <span><i class="mdi mdi-phone"></i>${contactoTelefono}</span>
                    <span><i class="mdi mdi-email-outline"></i>${contactoCorreo}</span>
                    ${fuente ? `<span><i class="mdi mdi-source-branch"></i>${fuente}</span>` : ''}
                </div>
                <div class="crm-badges">
                    <span class="badge"><i class="mdi mdi-note-text-outline"></i>${totalNotas}</span>
                    <span class="badge"><i class="mdi mdi-paperclip"></i>${totalAdjuntos}</span>
                    <span class="badge"><i class="mdi mdi-format-list-checks"></i>${tareasPendientes}/${tareasTotal}</span>
                    <span class="badge"><i class="mdi mdi-calendar-clock"></i>${proximoVencimiento}</span>
                </div>
            </div>
        `;

        const turnoAsignado = formatTurno(solicitud.turno);
        const estadoActual = (solicitud.estado ?? '').toString();

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

            llamarTurnoSolicitud({ id: solicitud.id })
                .then(data => {
                    const turno = formatTurno(data?.turno);
                    const nombre = data?.full_name ?? solicitud.full_name ?? 'Paciente sin nombre';

                    if (turno) {
                        badgeTurno.textContent = `Turno #${turno}`;
                    }

                    if (data?.estado) {
                        resumenEstado.textContent = data.estado;
                    }

                    showToast(`🔔 Turno asignado para ${nombre}${turno ? ` (#${turno})` : ''}`);

                    if (Array.isArray(window.__solicitudesKanban)) {
                        const item = window.__solicitudesKanban.find(s => String(s.id) === String(solicitud.id));
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
        crmButton.dataset.solicitudId = solicitud.id ?? '';
        crmButton.dataset.pacienteNombre = solicitud.full_name ?? '';
        tarjeta.appendChild(crmButton);

        const estadoId = 'kanban-' + (solicitud.estado || '')
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
