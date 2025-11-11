import { showToast } from './toast.js';
import { getKanbanConfig } from './config.js';

let crmOptions = {
    responsables: [],
    etapas: [],
    fuentes: [],
};

let kanbanPreferences = {
    columnLimit: 0,
    sort: 'fecha_desc',
    pipelineStages: [],
};

if (typeof window !== 'undefined') {
    window.__crmKanbanPreferences = { ...kanbanPreferences };
}

let currentSolicitudId = null;
let offcanvasInstance = null;
let formsBound = false;
let currentData = null;
let currentLead = null;
let currentDetalle = null;

export function setCrmOptions(options = {}) {
    crmOptions = {
        responsables: Array.isArray(options.responsables) ? options.responsables : [],
        etapas: Array.isArray(options.etapas) ? options.etapas : [],
        fuentes: Array.isArray(options.fuentes) ? options.fuentes : [],
    };

    const kanban = options.kanban ?? {};
    kanbanPreferences = {
        columnLimit: Number.parseInt(kanban.column_limit ?? kanban.columnLimit ?? 0, 10) || 0,
        sort: typeof kanban.sort === 'string' ? kanban.sort : 'fecha_desc',
        pipelineStages: Array.isArray(crmOptions.etapas) ? [...crmOptions.etapas] : [],
    };

    window.__crmKanbanPreferences = { ...kanbanPreferences };

    populateStaticOptions();
}

export function refreshCrmPanelIfActive(solicitudId) {
    if (!solicitudId || !currentSolicitudId) {
        return false;
    }

    if (String(currentSolicitudId) !== String(solicitudId)) {
        return false;
    }

    loadCrmData(currentSolicitudId);
    return true;
}

export function getCrmKanbanPreferences() {
    return { ...kanbanPreferences };
}

export function initCrmInteractions() {
    const buttons = document.querySelectorAll('.btn-open-crm');
    if (!buttons.length) {
        console.warn('CRM ▶ No se encontraron botones .btn-open-crm en el DOM');
    }
    buttons.forEach(button => {
        if (button.dataset.crmBound === '1') {
            return;
        }

        button.dataset.crmBound = '1';
        button.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();

            const solicitudId = Number.parseInt(button.dataset.solicitudId ?? button.dataset.id ?? '', 10);
            if (!Number.isFinite(solicitudId) || solicitudId <= 0) {
                console.error('CRM ▶ ID de solicitud inválido en el botón', button);
                showToast('No se pudo identificar la solicitud seleccionada', false);
                return;
            }

            const nombre = button.dataset.pacienteNombre ?? '';
            openCrmPanel(solicitudId, nombre);
        });
    });

    if (!formsBound) {
        bindForms();
        formsBound = true;
    }

    populateStaticOptions();
}

function populateStaticOptions() {
    const pipelineSelect = document.getElementById('crmPipeline');
    if (pipelineSelect) {
        const selected = pipelineSelect.value;
        pipelineSelect.innerHTML = '';

        crmOptions.etapas.forEach(etapa => {
            const option = document.createElement('option');
            option.value = etapa;
            option.textContent = etapa;
            pipelineSelect.appendChild(option);
        });

        if (!crmOptions.etapas.length) {
            const option = document.createElement('option');
            const defaultStage = kanbanPreferences.pipelineStages[0] ?? 'Recibido';
            option.value = defaultStage;
            option.textContent = defaultStage;
            pipelineSelect.appendChild(option);
        }

        if (selected) {
            pipelineSelect.value = selected;
        }
    }

    const responsableSelect = document.getElementById('crmResponsable');
    const seguidoresSelect = document.getElementById('crmSeguidores');
    const tareaAsignadoSelect = document.getElementById('crmTareaAsignado');

    if (responsableSelect) {
        const previous = responsableSelect.value;
        responsableSelect.innerHTML = '<option value="">Sin asignar</option>';
        crmOptions.responsables.forEach(usuario => {
            const option = document.createElement('option');
            option.value = String(usuario.id);
            option.textContent = usuario.nombre ?? `Usuario #${usuario.id}`;
            responsableSelect.appendChild(option);
        });
        if (previous) {
            responsableSelect.value = previous;
        }
    }

    if (seguidoresSelect) {
        const seleccionados = Array.from(seguidoresSelect.selectedOptions).map(opt => opt.value);
        seguidoresSelect.innerHTML = '';
        crmOptions.responsables.forEach(usuario => {
            const option = document.createElement('option');
            option.value = String(usuario.id);
            option.textContent = usuario.nombre ?? `Usuario #${usuario.id}`;
            seguidoresSelect.appendChild(option);
        });
        seleccionados.forEach(valor => {
            if (valor !== '') {
                const option = seguidoresSelect.querySelector(`option[value="${escapeSelector(valor)}"]`);
                if (option) {
                    option.selected = true;
                }
            }
        });
    }

    if (tareaAsignadoSelect) {
        const previous = tareaAsignadoSelect.value;
        tareaAsignadoSelect.innerHTML = '<option value="">Sin asignar</option>';
        crmOptions.responsables.forEach(usuario => {
            const option = document.createElement('option');
            option.value = String(usuario.id);
            option.textContent = usuario.nombre ?? `Usuario #${usuario.id}`;
            tareaAsignadoSelect.appendChild(option);
        });
        if (previous) {
            tareaAsignadoSelect.value = previous;
        }
    }

    const fuenteInput = document.getElementById('crmFuenteOptions');
    if (fuenteInput) {
        fuenteInput.innerHTML = '';
        crmOptions.fuentes.forEach(fuente => {
            const option = document.createElement('option');
            option.value = fuente;
            fuenteInput.appendChild(option);
        });
    }
}

function bindForms() {
    const detalleForm = document.getElementById('crmDetalleForm');
    if (detalleForm) {
        detalleForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentSolicitudId) {
                showToast('Selecciona una solicitud para actualizar los detalles', false);
                return;
            }

            const payload = collectDetallePayload(detalleForm);
            const { basePath } = getKanbanConfig();
            await submitJson(`${basePath}/${currentSolicitudId}/crm`, payload, 'Detalles CRM actualizados');
        });
    }

    bindLeadControls();

    const notaForm = document.getElementById('crmNotaForm');
    if (notaForm) {
        notaForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentSolicitudId) {
                showToast('Selecciona una solicitud para agregar notas', false);
                return;
            }

            const textarea = document.getElementById('crmNotaTexto');
            const texto = textarea?.value?.trim() ?? '';
            if (texto === '') {
                showToast('Escribe una nota antes de guardar', false);
                textarea?.focus();
                return;
            }

            const payload = { nota: texto };
            const { basePath } = getKanbanConfig();
            const ok = await submitJson(`${basePath}/${currentSolicitudId}/crm/notas`, payload, 'Nota registrada');
            if (ok && textarea) {
                textarea.value = '';
            }
        });
    }

    const adjuntoForm = document.getElementById('crmAdjuntoForm');
    if (adjuntoForm) {
        adjuntoForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentSolicitudId) {
                showToast('Selecciona una solicitud para cargar adjuntos', false);
                return;
            }

            const archivoInput = document.getElementById('crmAdjuntoArchivo');
            if (!archivoInput || !archivoInput.files || archivoInput.files.length === 0) {
                showToast('Selecciona un archivo para adjuntar', false);
                return;
            }

            const formData = new FormData();
            formData.append('archivo', archivoInput.files[0]);
            const descripcion = document.getElementById('crmAdjuntoDescripcion')?.value ?? '';
            if (descripcion) {
                formData.append('descripcion', descripcion);
            }

            const { basePath } = getKanbanConfig();
            const ok = await submitFormData(`${basePath}/${currentSolicitudId}/crm/adjuntos`, formData, 'Documento cargado');
            if (ok) {
                adjuntoForm.reset();
            }
        });
    }

    const tareaForm = document.getElementById('crmTareaForm');
    if (tareaForm) {
        tareaForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentSolicitudId) {
                showToast('Selecciona una solicitud para registrar tareas', false);
                return;
            }

            const payload = collectTareaPayload(tareaForm);
            if (!payload.titulo) {
                showToast('La tarea necesita un título', false);
                return;
            }

            const { basePath } = getKanbanConfig();
            const ok = await submitJson(`${basePath}/${currentSolicitudId}/crm/tareas`, payload, 'Tarea agregada');
            if (ok) {
                tareaForm.reset();
            }
        });
    }

    const agregarCampoBtn = document.getElementById('crmAgregarCampo');
    if (agregarCampoBtn) {
        agregarCampoBtn.addEventListener('click', () => {
            addCampoPersonalizado();
        });
    }
}

function collectDetallePayload(form) {
    const pipeline = document.getElementById('crmPipeline')?.value ?? '';
    const responsable = document.getElementById('crmResponsable')?.value ?? '';
    const fuente = document.getElementById('crmFuente')?.value ?? '';
    const correo = document.getElementById('crmContactoEmail')?.value ?? '';
    const telefono = document.getElementById('crmContactoTelefono')?.value ?? '';
    const leadId = document.getElementById('crmLeadId')?.value ?? '';
    const seguidoresSelect = document.getElementById('crmSeguidores');

    const seguidores = seguidoresSelect
        ? Array.from(seguidoresSelect.selectedOptions).map(option => option.value)
        : [];

    return {
        pipeline_stage: pipeline,
        responsable_id: responsable,
        fuente,
        contacto_email: correo,
        contacto_telefono: telefono,
        seguidores,
        crm_lead_id: leadId,
        custom_fields: collectCamposPersonalizados(),
    };
}

function collectCamposPersonalizados() {
    const container = document.getElementById('crmCamposContainer');
    if (!container) {
        return [];
    }

    const rows = Array.from(container.querySelectorAll('.crm-campo'));
    return rows
        .map(row => {
            const key = row.querySelector('.crm-campo-key')?.value ?? '';
            const value = row.querySelector('.crm-campo-value')?.value ?? '';
            const type = row.querySelector('.crm-campo-type')?.value ?? 'texto';

            const trimmedKey = key.trim();
            if (trimmedKey === '') {
                return null;
            }

            return {
                key: trimmedKey,
                value: value.trim(),
                type,
            };
        })
        .filter(Boolean);
}

function collectTareaPayload(form) {
    return {
        titulo: form.querySelector('#crmTareaTitulo')?.value?.trim() ?? '',
        assigned_to: form.querySelector('#crmTareaAsignado')?.value ?? '',
        due_date: form.querySelector('#crmTareaFecha')?.value ?? '',
        remind_at: form.querySelector('#crmTareaRecordatorio')?.value ?? '',
        descripcion: form.querySelector('#crmTareaDescripcion')?.value?.trim() ?? '',
    };
}

function bindLeadControls() {
    const leadInput = document.getElementById('crmLeadIdInput');
    const leadHidden = document.getElementById('crmLeadId');
    const openButton = document.getElementById('crmLeadOpen');
    const unlinkButton = document.getElementById('crmLeadUnlink');

    if (leadInput && leadHidden) {
        leadInput.addEventListener('input', () => {
            const sanitized = leadInput.value.trim();
            leadHidden.value = sanitized;

            if (sanitized === '') {
                currentLead = null;
            }

            updateLeadControls(currentDetalle, sanitized ? currentLead : null, sanitized || null);
        });
    }

    if (openButton) {
        openButton.addEventListener('click', () => {
            if (openButton.disabled) {
                return;
            }

            const url = openButton.dataset.leadUrl;
            if (url) {
                window.open(url, '_blank', 'noopener');
            }
        });
    }

    if (unlinkButton && leadHidden) {
        unlinkButton.addEventListener('click', () => {
            leadHidden.value = '';
            if (leadInput) {
                leadInput.value = '';
            }

            currentLead = null;
            updateLeadControls(currentDetalle, null, null);
        });
    }
}

function updateLeadControls(detalle, lead, overrideId = null) {
    const leadInput = document.getElementById('crmLeadIdInput');
    const leadHidden = document.getElementById('crmLeadId');
    const leadHelp = document.getElementById('crmLeadHelp');
    const openButton = document.getElementById('crmLeadOpen');
    const unlinkButton = document.getElementById('crmLeadUnlink');

    const leadIdCandidate = overrideId !== null && overrideId !== undefined
        ? overrideId
        : lead?.id ?? detalle?.crm_lead_id ?? '';

    const leadId = leadIdCandidate !== null && leadIdCandidate !== undefined
        ? String(leadIdCandidate).trim()
        : '';

    if (leadInput) {
        leadInput.value = leadId;
    }

    if (leadHidden) {
        leadHidden.value = leadId;
    }

    let helpText = 'Sin lead vinculado. Al guardar se creará automáticamente.';
    let leadUrl = '';

    const leadMatchesDetalle = leadId && detalle?.crm_lead_id && String(detalle.crm_lead_id) === leadId;
    const leadData = lead && leadId && String(lead.id) === leadId ? lead : null;

    if (leadId) {
        const status = leadData?.status ?? (leadMatchesDetalle ? detalle?.crm_lead_status : null) ?? 'sin estado';
        const source = leadData?.source ?? (leadMatchesDetalle ? detalle?.crm_lead_source : null) ?? '';

        if (leadData || leadMatchesDetalle) {
            helpText = `Lead #${leadId} · Estado: ${status}${source ? ` · Fuente: ${source}` : ''}`;
            leadUrl = leadData?.url ?? `/crm?lead=${leadId}`;
        } else {
            helpText = `Lead #${leadId}. Se vinculará cuando guardes los cambios.`;
            leadUrl = `/crm?lead=${leadId}`;
        }
    }

    if (leadHelp) {
        leadHelp.textContent = helpText;
    }

    if (openButton) {
        if (leadId) {
            openButton.disabled = false;
            openButton.dataset.leadUrl = leadUrl;
        } else {
            openButton.disabled = true;
            openButton.dataset.leadUrl = '';
        }
    }

    if (unlinkButton) {
        unlinkButton.disabled = !leadId;
    }

    if (currentDetalle) {
        currentDetalle.crm_lead_id = leadId ? Number.parseInt(leadId, 10) || leadId : null;
        if (leadData) {
            currentDetalle.crm_lead_status = leadData.status ?? currentDetalle.crm_lead_status;
            currentDetalle.crm_lead_source = leadData.source ?? currentDetalle.crm_lead_source;
        } else if (!leadId) {
            currentDetalle.crm_lead_status = null;
            currentDetalle.crm_lead_source = null;
        }
    }
}

async function submitJson(url, payload, successMessage) {
    try {
        toggleLoading(true);
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        let data;
        let rawText = '';
        try {
            data = await response.json();
        } catch {
            try {
                rawText = await response.text();
            } catch {}
            const preview = rawText ? ` (preview: ${rawText.slice(0, 160)}...)` : '';
            throw new Error('Respuesta no válida del servidor (no JSON)' + preview);
        }

        if (!response.ok || data.success === false) {
            throw new Error(data.error || 'Operación no disponible');
        }

        if (data.data) {
            renderCrmData(data.data);
        }

        if (successMessage) {
            showToast(successMessage);
        }

        return true;
    } catch (error) {
        console.error('CRM ▶ Error', error);
        showToast(error.message || 'No se pudo completar la acción', false);
        return false;
    } finally {
        toggleLoading(false);
    }
}

async function submitFormData(url, formData, successMessage) {
    try {
        toggleLoading(true);
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        });

        let data;
        let rawText = '';
        try {
            data = await response.json();
        } catch {
            try {
                rawText = await response.text();
            } catch {}
            const preview = rawText ? ` (preview: ${rawText.slice(0, 160)}...)` : '';
            throw new Error('Respuesta no válida del servidor (no JSON)' + preview);
        }

        if (!response.ok || data.success === false) {
            throw new Error(data.error || 'No se pudo subir el adjunto');
        }

        if (data.data) {
            renderCrmData(data.data);
        }

        if (successMessage) {
            showToast(successMessage);
        }

        return true;
    } catch (error) {
        console.error('CRM ▶ Error adjunto', error);
        showToast(error.message || 'No se pudo completar la acción', false);
        return false;
    } finally {
        toggleLoading(false);
    }
}

function openCrmPanel(solicitudId, nombrePaciente) {
    const element = document.getElementById('crmOffcanvas');
    if (!element) {
        console.warn('CRM ▶ No se encontró el panel lateral');
        return;
    }

    if (!offcanvasInstance && typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
        offcanvasInstance = new bootstrap.Offcanvas(element);
    }

    currentSolicitudId = solicitudId;
    currentData = null;

    const subtitle = document.getElementById('crmOffcanvasSubtitle');
    if (subtitle) {
        const nombre = nombrePaciente && nombrePaciente.trim() !== '' ? nombrePaciente : `Solicitud #${solicitudId}`;
        subtitle.textContent = nombre;
    }

    toggleLoading(true);
    toggleError();
    setFormsDisabled(true);
    clearCrmSections();

    if (offcanvasInstance) {
        offcanvasInstance.show();
    }

    loadCrmData(solicitudId);
}

async function loadCrmData(solicitudId) {
    try {
        const { basePath } = getKanbanConfig();
        const response = await fetch(`${basePath}/${solicitudId}/crm`, { credentials: 'same-origin' });

        // Intenta parsear JSON; si falla, intenta leer texto para mostrar un error útil
        let data;
        let rawText = '';
        try {
            data = await response.json();
        } catch {
            try {
                rawText = await response.text();
            } catch {}
            const preview = rawText ? ` (preview: ${rawText.slice(0, 160)}...)` : '';
            throw new Error('Respuesta no válida del servidor (no JSON)' + preview);
        }

        if (!response.ok || data.success === false) {
            throw new Error(data.error || 'No se pudo cargar la información CRM');
        }

        renderCrmData(data.data);
    } catch (error) {
        console.error('CRM ▶ Error al cargar', error);
        toggleError(error.message || 'No se pudo cargar la información del CRM');
    } finally {
        toggleLoading(false);
        setFormsDisabled(false);
    }
}

function renderCrmData(data) {
    if (!data || !data.detalle || typeof data.detalle !== 'object') {
        toggleError('No se encontró información CRM para esta solicitud');
        return;
    }

    currentData = data;
    currentDetalle = data.detalle;
    currentLead = data.lead ?? null;

    updateLeadControls(currentDetalle, currentLead);
    renderResumen(data.detalle, currentLead);
    renderNotas(data.notas ?? []);
    renderAdjuntos(data.adjuntos ?? []);
    renderTareas(data.tareas ?? []);
    renderCampos(data.campos_personalizados ?? []);
}

function renderResumen(detalle, lead) {
    const header = document.getElementById('crmResumenCabecera');
    if (!header) {
        return;
    }

    const nombre = detalle.paciente_nombre || detalle.full_name || 'Paciente sin nombre';
    const procedimiento = detalle.procedimiento || 'Sin procedimiento especificado';
    const estado = detalle.estado || 'Sin estado';
    const prioridad = detalle.prioridad || 'Sin prioridad';
    const hc = detalle.hc_number || '—';
    const afiliacion = detalle.afiliacion || 'Sin afiliación';
    const pipeline = detalle.crm_pipeline_stage || 'Recibido';
    const responsable = detalle.crm_responsable_nombre || 'Sin responsable asignado';
    const totalNotas = detalle.crm_total_notas ?? 0;
    const totalAdjuntos = detalle.crm_total_adjuntos ?? 0;
    const tareasPendientes = detalle.crm_tareas_pendientes ?? 0;
    const tareasTotales = detalle.crm_tareas_total ?? 0;
    const proximoVencimiento = detalle.crm_proximo_vencimiento ? formatDate(detalle.crm_proximo_vencimiento) : '—';
    const telefono = detalle.crm_contacto_telefono || detalle.paciente_celular || 'Sin teléfono';
    const correo = detalle.crm_contacto_email || 'Sin correo registrado';
    const dias = Number.isFinite(detalle.dias_en_estado) ? `${detalle.dias_en_estado} día(s) en el estado actual` : 'Tiempo en estado no disponible';
    const leadId = lead?.id ?? detalle.crm_lead_id ?? null;
    const leadStatus = lead?.status ?? detalle.crm_lead_status ?? 'Sin estado';
    const leadSource = lead?.source ?? detalle.crm_lead_source ?? 'Sin fuente';
    const leadUrl = lead?.url ?? (leadId ? `/crm?lead=${leadId}` : null);
    const leadInfo = leadId
        ? `Lead #${escapeHtml(String(leadId))} · ${escapeHtml(leadStatus)} · ${escapeHtml(leadSource)}`
        : 'Sin lead vinculado aún';

    header.innerHTML = `
        <div class="d-flex flex-column gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge text-bg-secondary">HC ${escapeHtml(String(hc))}</span>
                <span class="badge text-bg-info">${escapeHtml(pipeline)}</span>
                <span class="badge text-bg-light text-dark">${escapeHtml(prioridad)}</span>
                ${leadId ? `<span class="badge text-bg-primary">Lead #${escapeHtml(String(leadId))}</span>` : ''}
            </div>
            <div>
                <h5 class="mb-1">${escapeHtml(nombre)}</h5>
                <p class="text-muted mb-0">${escapeHtml(procedimiento)}</p>
            </div>
            <div class="row g-2 small text-muted">
                <div class="col-6"><strong>Estado actual:</strong> ${escapeHtml(estado)}</div>
                <div class="col-6"><strong>Afiliación:</strong> ${escapeHtml(afiliacion)}</div>
                <div class="col-6"><strong>Responsable:</strong> ${escapeHtml(responsable)}</div>
                <div class="col-6"><strong>Contacto:</strong> ${escapeHtml(telefono)} • ${escapeHtml(correo)}</div>
                <div class="col-6"><strong>Notas:</strong> ${totalNotas}</div>
                <div class="col-6"><strong>Adjuntos:</strong> ${totalAdjuntos}</div>
                <div class="col-6"><strong>Tareas activas:</strong> ${tareasPendientes}/${tareasTotales}</div>
                <div class="col-6"><strong>Próx. vencimiento:</strong> ${escapeHtml(proximoVencimiento)}</div>
                <div class="col-12"><strong>Seguimiento:</strong> ${escapeHtml(dias)}</div>
                <div class="col-12"><strong>Lead CRM:</strong> ${leadUrl ? `<a href="${escapeHtml(leadUrl)}" target="_blank" rel="noopener">${leadInfo}</a>` : escapeHtml(leadInfo)}</div>
            </div>
        </div>
    `;

    const pipelineSelect = document.getElementById('crmPipeline');
    if (pipelineSelect) {
        pipelineSelect.value = pipeline;
    }

    const responsableSelect = document.getElementById('crmResponsable');
    if (responsableSelect) {
        responsableSelect.value = detalle.crm_responsable_id ? String(detalle.crm_responsable_id) : '';
    }

    const correoInput = document.getElementById('crmContactoEmail');
    if (correoInput) {
        correoInput.value = detalle.crm_contacto_email || '';
    }

    const telefonoInput = document.getElementById('crmContactoTelefono');
    if (telefonoInput) {
        telefonoInput.value = detalle.crm_contacto_telefono || '';
    }

    const fuenteInput = document.getElementById('crmFuente');
    if (fuenteInput) {
        fuenteInput.value = detalle.crm_fuente || '';
    }

    const seguidoresSelect = document.getElementById('crmSeguidores');
    if (seguidoresSelect) {
        Array.from(seguidoresSelect.options).forEach(option => {
            option.selected = false;
        });
        (detalle.seguidores || []).forEach(seguidor => {
            const value = String(seguidor.id ?? seguidor);
            const option = seguidoresSelect.querySelector(`option[value="${escapeSelector(value)}"]`);
            if (option) {
                option.selected = true;
            }
        });
    }

    const notasResumen = document.getElementById('crmNotasResumen');
    if (notasResumen) {
        notasResumen.textContent = `${totalNotas} nota(s)`;
    }

    const adjuntosResumen = document.getElementById('crmAdjuntosResumen');
    if (adjuntosResumen) {
        adjuntosResumen.textContent = `${totalAdjuntos} documento(s)`;
    }

    const tareasResumen = document.getElementById('crmTareasResumen');
    if (tareasResumen) {
        tareasResumen.textContent = tareasTotales > 0 ? `${tareasPendientes} pendientes de ${tareasTotales}` : 'Sin tareas registradas';
    }
}

function renderNotas(notas) {
    const list = document.getElementById('crmNotasList');
    if (!list) {
        return;
    }

    list.innerHTML = '';

    if (!Array.isArray(notas) || notas.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'crm-list-empty';
        empty.textContent = 'Sin notas registradas todavía';
        list.appendChild(empty);
        return;
    }

    notas.forEach(nota => {
        const item = document.createElement('div');
        item.className = 'list-group-item crm-note-item';

        const contenido = document.createElement('p');
        contenido.className = 'mb-1';
        contenido.textContent = nota.nota ?? '';
        item.appendChild(contenido);

        const meta = document.createElement('small');
        const autor = nota.autor_nombre || 'Usuario interno';
        const fecha = nota.created_at ? formatDateTime(nota.created_at) : 'Fecha no disponible';
        meta.textContent = `${autor} • ${fecha}`;
        item.appendChild(meta);

        list.appendChild(item);
    });
}

function renderAdjuntos(adjuntos) {
    const list = document.getElementById('crmAdjuntosList');
    if (!list) {
        return;
    }

    list.innerHTML = '';

    if (!Array.isArray(adjuntos) || adjuntos.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'crm-list-empty';
        empty.textContent = 'Aún no se han cargado documentos';
        list.appendChild(empty);
        return;
    }

    adjuntos.forEach(adjunto => {
        const link = document.createElement('a');
        link.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-start';
        link.href = adjunto.url || '#';
        link.target = '_blank';
        link.rel = 'noopener';

        const cuerpo = document.createElement('div');
        cuerpo.className = 'me-3';

        const titulo = document.createElement('h6');
        titulo.className = 'mb-1';
        titulo.textContent = adjunto.descripcion || adjunto.nombre_original || 'Documento sin título';
        cuerpo.appendChild(titulo);

        const meta = document.createElement('p');
        meta.className = 'mb-0 text-muted small';
        const autor = adjunto.subido_por_nombre || 'Usuario interno';
        const fecha = adjunto.created_at ? formatDateTime(adjunto.created_at) : 'Fecha no disponible';
        const tamano = formatSize(adjunto.tamano_bytes);
        meta.textContent = `${autor} • ${fecha}${tamano ? ` • ${tamano}` : ''}`;
        cuerpo.appendChild(meta);

        link.appendChild(cuerpo);

        const icono = document.createElement('span');
        icono.className = 'badge text-bg-light text-dark';
        icono.innerHTML = '<i class="mdi mdi-paperclip"></i>';
        link.appendChild(icono);

        list.appendChild(link);
    });
}

function renderTareas(tareas) {
    const list = document.getElementById('crmTareasList');
    if (!list) {
        return;
    }

    list.innerHTML = '';

    if (!Array.isArray(tareas) || tareas.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'crm-list-empty';
        empty.textContent = 'No hay tareas registradas para esta solicitud';
        list.appendChild(empty);
        return;
    }

    tareas.forEach(tarea => {
        const item = document.createElement('div');
        item.className = 'list-group-item crm-task-item d-flex justify-content-between align-items-start gap-3';

        const cuerpo = document.createElement('div');
        cuerpo.className = 'flex-grow-1';

        const titulo = document.createElement('h6');
        titulo.className = 'mb-1 d-flex align-items-center gap-2';
        titulo.textContent = tarea.titulo || 'Tarea sin título';

        const estadoBadge = document.createElement('span');
        estadoBadge.className = `badge ${estadoBadgeClass(tarea.estado)}`;
        estadoBadge.textContent = tarea.estado || 'pendiente';
        titulo.appendChild(estadoBadge);

        cuerpo.appendChild(titulo);

        if (tarea.descripcion) {
            const descripcion = document.createElement('p');
            descripcion.className = 'mb-1 text-muted';
            descripcion.textContent = tarea.descripcion;
            cuerpo.appendChild(descripcion);
        }

        const meta = document.createElement('p');
        meta.className = 'mb-0 text-muted small';
        const asignado = tarea.assigned_name || 'Sin asignar';
        const creador = tarea.created_name || 'Equipo';
        const due = tarea.due_date ? formatDate(tarea.due_date) : 'Sin fecha límite';
        meta.textContent = `Responsable: ${asignado} • Creador: ${creador} • Límite: ${due}`;
        cuerpo.appendChild(meta);

        item.appendChild(cuerpo);

        const acciones = document.createElement('div');
        acciones.className = 'd-flex flex-column gap-2 align-items-end';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = tarea.estado === 'completada'
            ? 'btn btn-sm btn-outline-secondary'
            : 'btn btn-sm btn-outline-success';
        btn.innerHTML = tarea.estado === 'completada'
            ? '<i class="mdi mdi-restore"></i> Reabrir'
            : '<i class="mdi mdi-check-circle-outline"></i> Completar';
        btn.addEventListener('click', () => {
            const nuevoEstado = tarea.estado === 'completada' ? 'pendiente' : 'completada';
            actualizarEstadoTarea(tarea.id, nuevoEstado);
        });
        acciones.appendChild(btn);

        if (tarea.completed_at) {
            const finalizado = document.createElement('small');
            finalizado.className = 'text-muted';
            finalizado.textContent = `Finalizada: ${formatDateTime(tarea.completed_at)}`;
            acciones.appendChild(finalizado);
        }

        item.appendChild(acciones);
        list.appendChild(item);
    });
}

async function actualizarEstadoTarea(tareaId, estado) {
    if (!currentSolicitudId) {
        showToast('Selecciona una solicitud para actualizar la tarea', false);
        return;
    }

    const { basePath } = getKanbanConfig();
    await submitJson(`${basePath}/${currentSolicitudId}/crm/tareas/estado`, {
        tarea_id: tareaId,
        estado,
    }, 'Tarea actualizada');
}

function renderCampos(campos) {
    const container = document.getElementById('crmCamposContainer');
    if (!container) {
        return;
    }

    container.innerHTML = '';

    if (!Array.isArray(campos) || campos.length === 0) {
        const texto = container.dataset.emptyText || 'Sin campos adicionales';
        const empty = document.createElement('div');
        empty.className = 'crm-list-empty';
        empty.textContent = texto;
        container.appendChild(empty);
        return;
    }

    campos.forEach(campo => {
        addCampoPersonalizado({
            key: campo.key,
            value: campo.value,
            type: campo.type,
        });
    });
}

function addCampoPersonalizado(campo = {}) {
    const container = document.getElementById('crmCamposContainer');
    if (!container) {
        return;
    }

    if (container.querySelector('.crm-list-empty')) {
        container.innerHTML = '';
    }

    const row = document.createElement('div');
    row.className = 'crm-campo';

    const inputKey = document.createElement('input');
    inputKey.type = 'text';
    inputKey.className = 'form-control crm-campo-key';
    inputKey.placeholder = 'Nombre del campo';
    inputKey.value = campo.key || '';

    const inputValue = document.createElement('input');
    inputValue.type = 'text';
    inputValue.className = 'form-control crm-campo-value';
    inputValue.placeholder = 'Valor';
    inputValue.value = campo.value || '';

    const selectType = document.createElement('select');
    selectType.className = 'form-select crm-campo-type';
    ['texto', 'numero', 'fecha', 'lista'].forEach(tipo => {
        const option = document.createElement('option');
        option.value = tipo;
        option.textContent = tipo.charAt(0).toUpperCase() + tipo.slice(1);
        if (campo.type && campo.type.toLowerCase() === tipo) {
            option.selected = true;
        }
        selectType.appendChild(option);
    });

    const removeButton = document.createElement('button');
    removeButton.type = 'button';
    removeButton.className = 'btn btn-outline-danger btn-sm';
    removeButton.innerHTML = '<i class="mdi mdi-close"></i>';
    removeButton.addEventListener('click', () => {
        row.remove();
        if (!container.querySelector('.crm-campo')) {
            const texto = container.dataset.emptyText || 'Sin campos adicionales';
            const empty = document.createElement('div');
            empty.className = 'crm-list-empty';
            empty.textContent = texto;
            container.appendChild(empty);
        }
    });

    row.appendChild(inputKey);
    row.appendChild(inputValue);
    row.appendChild(selectType);
    row.appendChild(removeButton);

    container.appendChild(row);
}

function clearCrmSections() {
    const header = document.getElementById('crmResumenCabecera');
    if (header) {
        header.innerHTML = '';
    }

    ['crmNotasList', 'crmAdjuntosList', 'crmTareasList'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.innerHTML = '';
        }
    });
}

function toggleLoading(active) {
    const loading = document.getElementById('crmLoading');
    if (!loading) {
        return;
    }

    loading.classList.toggle('d-none', !active);
}

function toggleError(message = '') {
    const error = document.getElementById('crmError');
    if (!error) {
        return;
    }

    if (!message) {
        error.classList.add('d-none');
        error.textContent = '';
        return;
    }

    error.classList.remove('d-none');
    error.textContent = message;
}

function setFormsDisabled(disabled) {
    const offcanvas = document.getElementById('crmOffcanvas');
    if (!offcanvas) {
        return;
    }

    const elements = offcanvas.querySelectorAll('input, select, textarea, button');
    elements.forEach(element => {
        if (element.dataset.preserveDisabled === 'true') {
            return;
        }
        element.disabled = disabled;
    });
}

function estadoBadgeClass(estado) {
    switch ((estado || '').toLowerCase()) {
        case 'completada':
            return 'text-bg-success';
        case 'en_progreso':
            return 'text-bg-info';
        case 'cancelada':
            return 'text-bg-secondary';
        default:
            return 'text-bg-warning';
    }
}

function formatDate(fecha) {
    const date = new Date(fecha);
    if (Number.isNaN(date.getTime())) {
        return fecha;
    }

    return new Intl.DateTimeFormat('es-EC', { year: 'numeric', month: 'short', day: '2-digit' }).format(date);
}

function formatDateTime(fecha) {
    const date = new Date(fecha);
    if (Number.isNaN(date.getTime())) {
        return fecha;
    }

    return new Intl.DateTimeFormat('es-EC', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

function formatSize(bytes) {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(size < 10 && unitIndex > 0 ? 1 : 0)} ${units[unitIndex]}`;
}

function escapeSelector(value) {
    const stringValue = String(value);
    if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
        return CSS.escape(stringValue);
    }

    return stringValue.replace(/([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
}

function escapeHtml(value) {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
