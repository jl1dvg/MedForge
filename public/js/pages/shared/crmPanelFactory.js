let panelConfig = {
    showToast: () => {},
    getBasePath: () => '',
    resolveReadPath: null,
    resolveWritePath: null,
    entityLabel: 'solicitud',
    entityArticle: 'la',
    entitySelectionSuffix: 'seleccionada',
    datasetIdKey: 'solicitudId',
    buttonSelector: '.btn-open-crm',
};

function resolveBasePath() {
    const baseResult = typeof panelConfig.getBasePath === 'function' ? panelConfig.getBasePath() : panelConfig.getBasePath;
    if (typeof baseResult === 'string') {
        return baseResult;
    }
    if (baseResult && typeof baseResult === 'object') {
        return baseResult.basePath || '';
    }
    return '';
}

function resolveWritePath(path) {
    if (typeof panelConfig.resolveWritePath === 'function') {
        try {
            const resolved = panelConfig.resolveWritePath(path);
            if (typeof resolved === 'string' && resolved.trim() !== '') {
                return resolved;
            }
        } catch (error) {
            console.warn('CRM ▶ resolveWritePath fallback por error:', error);
        }
    }

    return path;
}

function resolveReadPath(path) {
    if (typeof panelConfig.resolveReadPath === 'function') {
        try {
            const resolved = panelConfig.resolveReadPath(path);
            if (typeof resolved === 'string' && resolved.trim() !== '') {
                return resolved;
            }
        } catch (error) {
            console.warn('CRM ▶ resolveReadPath fallback por error:', error);
        }
    }

    return path;
}

function notify(message, ok = true) {
    if (typeof panelConfig.showToast === 'function') {
        panelConfig.showToast(message, ok);
    }
}

function selectionMessage(action) {
    return `Selecciona ${panelConfig.entityArticle} ${panelConfig.entityLabel} para ${action}`;
}

function entityLabelCap() {
    const label = panelConfig.entityLabel || '';
    return label ? label.charAt(0).toUpperCase() + label.slice(1) : 'Entidad';
}

export function createCrmPanel(config = {}) {
    panelConfig = { ...panelConfig, ...config };
    return {
        setCrmOptions,
        refreshCrmPanelIfActive,
        getCrmKanbanPreferences,
        initCrmInteractions,
        openEntityCrm,
    };
}

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

let currentEntityId = null;
let offcanvasInstance = null;
let formsBound = false;
let currentData = null;
let currentLead = null;
let currentDetalle = null;
let checklistTasksBySlug = {};
let editingTaskId = null;
let editingChecklistSlug = '';

function parsePositiveInt(value) {
    const parsed = Number.parseInt(String(value ?? ''), 10);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

function resolveCurrentUserId() {
    const fromMedf = parsePositiveInt(window?.MEDF?.currentUser?.id);
    if (fromMedf !== null) {
        return fromMedf;
    }

    return parsePositiveInt(currentData?.viewer?.user_id);
}

function setCrmOptions(options = {}) {
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

function refreshCrmPanelIfActive(entityId) {
    if (!entityId || !currentEntityId) {
        return false;
    }

    if (String(currentEntityId) !== String(entityId)) {
        return false;
    }

    loadCrmData(currentEntityId);
    return true;
}

function getCrmKanbanPreferences() {
    return { ...kanbanPreferences };
}

function initCrmInteractions() {
    const buttonSelector = panelConfig.buttonSelector || '.btn-open-crm';
    const buttons = document.querySelectorAll(buttonSelector);
    if (!buttons.length) {
        console.warn(`CRM ▶ No se encontraron botones ${buttonSelector} en el DOM`);
    }
    buttons.forEach(button => {
        if (button.dataset.crmBound === '1') {
            return;
        }

        button.dataset.crmBound = '1';
        button.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();

            const entityId = Number.parseInt(button.dataset[panelConfig.datasetIdKey] ?? button.dataset.id ?? '', 10);
            if (!Number.isFinite(entityId) || entityId <= 0) {
                console.error(`CRM ▶ ID de ${panelConfig.entityLabel} inválido en el botón`, button);
                notify(`No se pudo identificar ${panelConfig.entityArticle} ${panelConfig.entityLabel} ${panelConfig.entitySelectionSuffix}`, false);
                return;
            }

            const nombre = button.dataset.pacienteNombre ?? '';
            openCrmPanel(entityId, nombre);
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
            if (!currentEntityId) {
                notify(selectionMessage('actualizar los detalles'), false);
                return;
            }

            const payload = collectDetallePayload(detalleForm);
            const basePath = resolveBasePath();
            await submitJson(
                resolveWritePath(`${basePath}/${currentEntityId}/crm`),
                payload,
                'Detalles CRM actualizados'
            );
        });
    }

    bindLeadControls();

    const notaForm = document.getElementById('crmNotaForm');
    if (notaForm) {
        notaForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentEntityId) {
                notify(selectionMessage('agregar notas'), false);
                return;
            }

            const textarea = document.getElementById('crmNotaTexto');
            const texto = textarea?.value?.trim() ?? '';
            if (texto === '') {
                notify('Escribe una nota antes de guardar', false);
                textarea?.focus();
                return;
            }

            const payload = { nota: texto };
            const basePath = resolveBasePath();
            const ok = await submitJson(
                resolveWritePath(`${basePath}/${currentEntityId}/crm/notas`),
                payload,
                'Nota registrada'
            );
            if (ok && textarea) {
                textarea.value = '';
            }
        });
    }

    const whatsappForm = document.getElementById('crmWhatsappForm');
    if (whatsappForm) {
        whatsappForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentEntityId) {
                notify(selectionMessage('enviar WhatsApp'), false);
                return;
            }

            const message = document.getElementById('crmWhatsappMensaje')?.value?.trim() ?? '';
            if (message === '') {
                notify('Escribe un mensaje de WhatsApp antes de enviar', false);
                return;
            }

            const payload = {
                message,
                conversation_id: document.getElementById('crmWhatsappConversationId')?.value || null,
                phone: document.getElementById('crmWhatsappPhone')?.value || '',
            };
            const basePath = resolveBasePath();
            const ok = await submitJson(
                resolveWritePath(`${basePath}/${currentEntityId}/crm/whatsapp`),
                payload,
                'WhatsApp enviado'
            );
            if (ok) {
                whatsappForm.reset();
                renderCommunicationDefaults(currentDetalle, currentData?.whatsapp_context || null);
            }
        });
    }

    const emailForm = document.getElementById('crmEmailForm');
    if (emailForm) {
        emailForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentEntityId) {
                notify(selectionMessage('enviar correo'), false);
                return;
            }

            const payload = {
                to: document.getElementById('crmEmailTo')?.value?.trim() ?? '',
                subject: document.getElementById('crmEmailSubject')?.value?.trim() ?? '',
                body: document.getElementById('crmEmailBody')?.value?.trim() ?? '',
            };
            if (!payload.to || !payload.subject || !payload.body) {
                notify('Completa destinatario, asunto y mensaje del correo', false);
                return;
            }

            const basePath = resolveBasePath();
            const ok = await submitJson(
                resolveWritePath(`${basePath}/${currentEntityId}/crm/email`),
                payload,
                'Correo enviado'
            );
            if (ok) {
                emailForm.reset();
                renderCommunicationDefaults(currentDetalle, currentData?.whatsapp_context || null);
            }
        });
    }

    const propuestaForm = document.getElementById('crmPropuestaForm');
    if (propuestaForm) {
        propuestaForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentEntityId) {
                notify(selectionMessage('crear propuestas'), false);
                return;
            }

            const payload = collectPropuestaPayload(propuestaForm);
            if (!payload.lead_id) {
                notify('Vincula o crea un lead CRM antes de crear la propuesta', false);
                return;
            }
            if (!payload.title) {
                notify('La propuesta necesita un título', false);
                return;
            }
            if (!payload.items.length) {
                notify('Agrega al menos un ítem a la propuesta', false);
                return;
            }

            const basePath = resolveBasePath();
            const ok = await submitJson(
                resolveWritePath(`${basePath}/${currentEntityId}/crm/propuestas`),
                payload,
                'Propuesta CRM creada'
            );
            if (ok) {
                resetPropuestaForm(propuestaForm);
            }
        });
    }

    const addProposalItemButton = document.getElementById('crmPropuestaAgregarItem');
    if (addProposalItemButton) {
        addProposalItemButton.addEventListener('click', () => addPropuestaItemRow());
    }

    const searchCodeButton = document.getElementById('crmPropuestaBuscarCodigo');
    if (searchCodeButton) {
        searchCodeButton.addEventListener('click', () => togglePropuestaSearchPanel('code'));
    }

    const searchPackageButton = document.getElementById('crmPropuestaBuscarPaquete');
    if (searchPackageButton) {
        searchPackageButton.addEventListener('click', () => togglePropuestaSearchPanel('package'));
    }

    const codeSearchSubmit = document.getElementById('crmPropuestaCodeSearchBtn');
    if (codeSearchSubmit) {
        codeSearchSubmit.addEventListener('click', () => searchPropuestaCodes());
    }

    const packageSearchSubmit = document.getElementById('crmPropuestaPackageSearchBtn');
    if (packageSearchSubmit) {
        packageSearchSubmit.addEventListener('click', () => searchPropuestaPackages());
    }

    const codeSearchInput = document.getElementById('crmPropuestaCodeSearch');
    if (codeSearchInput) {
        codeSearchInput.addEventListener('keyup', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchPropuestaCodes();
            }
        });
    }

    const packageSearchInput = document.getElementById('crmPropuestaPackageSearch');
    if (packageSearchInput) {
        packageSearchInput.addEventListener('keyup', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchPropuestaPackages();
            }
        });
    }

    const adjuntoForm = document.getElementById('crmAdjuntoForm');
    if (adjuntoForm) {
        adjuntoForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentEntityId) {
                notify(selectionMessage('cargar adjuntos'), false);
                return;
            }

            const archivoInput = document.getElementById('crmAdjuntoArchivo');
            if (!archivoInput || !archivoInput.files || archivoInput.files.length === 0) {
                notify('Selecciona un archivo para adjuntar', false);
                return;
            }

            const formData = new FormData();
            formData.append('archivo', archivoInput.files[0]);
            const descripcion = document.getElementById('crmAdjuntoDescripcion')?.value ?? '';
            if (descripcion) {
                formData.append('descripcion', descripcion);
            }

            const basePath = resolveBasePath();
            const ok = await submitFormData(
                resolveWritePath(`${basePath}/${currentEntityId}/crm/adjuntos`),
                formData,
                'Documento cargado'
            );
            if (ok) {
                adjuntoForm.reset();
            }
        });
    }

    const tareaForm = document.getElementById('crmTareaForm');
    if (tareaForm) {
        tareaForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentEntityId) {
                notify(selectionMessage('registrar tareas'), false);
                return;
            }

            const payload = collectTareaPayload(tareaForm);
            if (!payload.titulo) {
                notify('La tarea necesita un título', false);
                return;
            }

            const basePath = resolveBasePath();
            const isEditing = parsePositiveInt(payload.tarea_id) !== null;
            const ok = await submitJson(
                resolveWritePath(isEditing
                    ? `${basePath}/${currentEntityId}/crm/tareas/estado`
                    : `${basePath}/${currentEntityId}/crm/tareas`),
                payload,
                isEditing ? 'Tarea actualizada' : 'Tarea agregada'
            );
            if (ok) {
                resetTareaForm(tareaForm);
            }
        });
    }

    const cancelarTareaButton = document.getElementById('crmTareaCancelarEdicion');
    if (cancelarTareaButton) {
        cancelarTareaButton.addEventListener('click', () => resetTareaForm(document.getElementById('crmTareaForm')));
    }

    const bloqueoForm = document.getElementById('crmBloqueoForm');
    if (bloqueoForm) {
        bloqueoForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentEntityId) {
                notify(selectionMessage('bloquear agenda'), false);
                return;
            }

            const payload = collectBloqueoPayload(bloqueoForm);
            if (!payload.fecha_inicio) {
                notify('Indica al menos la fecha y hora de inicio', false);
                return;
            }

            const basePath = resolveBasePath();
            const ok = await submitJson(
                resolveWritePath(`${basePath}/${currentEntityId}/crm/bloqueo`),
                payload,
                'Bloqueo de agenda registrado'
            );

            if (ok) {
                bloqueoForm.reset();
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

    const readonlyRows = Array.from(container.querySelectorAll('.crm-campo-readonly'));
    const editableRows = Array.from(container.querySelectorAll('.crm-campo'));

    const persisted = readonlyRows
        .map(row => {
            const key = String(row.dataset.key || '').trim();
            if (key === '') {
                return null;
            }

            return {
                key,
                value: String(row.dataset.value || '').trim(),
                type: String(row.dataset.type || 'texto').trim() || 'texto',
            };
        })
        .filter(Boolean);

    const draft = editableRows
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

    return [...persisted, ...draft];
}

function collectBloqueoPayload(form) {
    const inicio = form.querySelector('#crmBloqueoInicio')?.value ?? '';
    const fin = form.querySelector('#crmBloqueoFin')?.value ?? '';
    const duracion = form.querySelector('#crmBloqueoDuracion')?.value ?? '';
    const sala = form.querySelector('#crmBloqueoSala')?.value ?? '';
    const doctor = form.querySelector('#crmBloqueoDoctor')?.value ?? '';
    const motivo = form.querySelector('#crmBloqueoMotivo')?.value ?? '';

    return {
        fecha_inicio: inicio,
        fecha_fin: fin,
        duracion_minutos: duracion ? Number.parseInt(duracion, 10) : null,
        sala,
        doctor,
        motivo,
    };
}

function collectTareaPayload(form) {
    const payload = {
        tarea_id: form.querySelector('#crmTareaId')?.value ?? '',
        titulo: form.querySelector('#crmTareaTitulo')?.value?.trim() ?? '',
        estado: form.querySelector('#crmTareaEstado')?.value ?? 'pendiente',
        assigned_to: form.querySelector('#crmTareaAsignado')?.value ?? '',
        due_date: form.querySelector('#crmTareaFecha')?.value ?? '',
        remind_at: form.querySelector('#crmTareaRecordatorio')?.value ?? '',
        priority: form.querySelector('#crmTareaPrioridad')?.value ?? '',
        descripcion: form.querySelector('#crmTareaDescripcion')?.value?.trim() ?? '',
    };

    if (editingChecklistSlug) {
        payload.checklist_slug = editingChecklistSlug;
        payload.task_key = `checklist:${editingChecklistSlug}`;
    }

    return payload;
}

function resetTareaForm(form) {
    if (!form) {
        return;
    }

    form.reset();
    editingTaskId = null;
    editingChecklistSlug = '';
    const idInput = form.querySelector('#crmTareaId');
    if (idInput) {
        idInput.value = '';
    }
    const statusSelect = form.querySelector('#crmTareaEstado');
    if (statusSelect) {
        statusSelect.value = 'pendiente';
    }

    const submitButton = form.querySelector('[data-crm-task-submit]');
    if (submitButton) {
        submitButton.innerHTML = '<i class="mdi mdi-playlist-plus me-1"></i>Agregar tarea';
    }

    const cancelButton = document.getElementById('crmTareaCancelarEdicion');
    cancelButton?.classList.add('d-none');
}

function editTarea(tarea = {}) {
    const form = document.getElementById('crmTareaForm');
    if (!form) {
        return;
    }

    editingTaskId = parsePositiveInt(tarea.id);
    editingChecklistSlug = String(tarea.checklist_slug || '').trim();
    const values = {
        crmTareaId: editingTaskId !== null ? String(editingTaskId) : '',
        crmTareaTitulo: tarea.titulo || tarea.title || '',
        crmTareaEstado: tarea.estado || tarea.status || 'pendiente',
        crmTareaAsignado: tarea.assigned_to || '',
        crmTareaFecha: normalizeDateInputValue(tarea.due_date || tarea.due_at || ''),
        crmTareaRecordatorio: normalizeDateTimeLocalValue(tarea.remind_at || ''),
        crmTareaPrioridad: tarea.priority || tarea.prioridad || '',
        crmTareaDescripcion: tarea.descripcion || tarea.description || '',
    };

    Object.entries(values).forEach(([id, value]) => {
        const field = form.querySelector(`#${id}`);
        if (field) {
            field.value = value == null ? '' : String(value);
        }
    });

    const submitButton = form.querySelector('[data-crm-task-submit]');
    if (submitButton) {
        submitButton.innerHTML = '<i class="mdi mdi-content-save-outline me-1"></i>Guardar cambios';
    }

    const cancelButton = document.getElementById('crmTareaCancelarEdicion');
    cancelButton?.classList.remove('d-none');
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    form.querySelector('#crmTareaTitulo')?.focus();
}

function collectPropuestaPayload(form) {
    return {
        lead_id: document.getElementById('crmLeadId')?.value || currentDetalle?.crm_lead_id || null,
        title: form.querySelector('#crmPropuestaTitulo')?.value?.trim() ?? '',
        valid_until: form.querySelector('#crmPropuestaVigencia')?.value ?? '',
        tax_rate: Number(form.querySelector('#crmPropuestaImpuesto')?.value || 0),
        notes: form.querySelector('#crmPropuestaNotas')?.value?.trim() ?? '',
        items: collectPropuestaItems(form),
    };
}

function collectPropuestaItems(form) {
    return Array.from(form.querySelectorAll('.crm-proposal-item'))
        .map(row => ({
            description: row.querySelector('.crm-proposal-item-description')?.value?.trim() ?? '',
            quantity: Number(row.querySelector('.crm-proposal-item-quantity')?.value || 1),
            unit_price: Number(row.querySelector('.crm-proposal-item-price')?.value || 0),
            discount_percent: Number(row.querySelector('.crm-proposal-item-discount')?.value || 0),
            code_id: row.dataset.codeId || null,
            package_id: row.dataset.packageId || null,
        }))
        .filter(item => item.description !== '');
}

function resetPropuestaForm(form) {
    form.reset();
    const taxInput = form.querySelector('#crmPropuestaImpuesto');
    if (taxInput) {
        taxInput.value = '0';
    }
    const items = form.querySelector('#crmPropuestaItems');
    if (items) {
        items.innerHTML = '';
    }
    addPropuestaItemRow();
    renderPropuestaControls();
}

function addPropuestaItemRow(item = {}) {
    const container = document.getElementById('crmPropuestaItems');
    if (!container) {
        return;
    }

    const row = document.createElement('div');
    row.className = 'crm-proposal-item row g-2 align-items-end mb-2';
    if (item.code_id) {
        row.dataset.codeId = String(item.code_id);
    }
    if (item.package_id) {
        row.dataset.packageId = String(item.package_id);
    }
    row.innerHTML = `
        <div class="col-md-5">
            <input type="text" class="form-control crm-proposal-item-description" placeholder="Descripción del ítem" value="${escapeHtml(item.description || '')}">
        </div>
        <div class="col-md-2">
            <input type="number" class="form-control crm-proposal-item-quantity" min="0.01" step="0.01" value="${escapeHtml(item.quantity || '1')}" aria-label="Cantidad">
        </div>
        <div class="col-md-2">
            <input type="number" class="form-control crm-proposal-item-price" min="0" step="0.01" value="${escapeHtml(item.unit_price || '')}" placeholder="Precio" aria-label="Precio unitario">
        </div>
        <div class="col-md-2">
            <input type="number" class="form-control crm-proposal-item-discount" min="0" max="100" step="0.01" value="${escapeHtml(item.discount_percent || '')}" placeholder="% desc." aria-label="Descuento">
        </div>
        <div class="col-md-1 d-grid">
            <button type="button" class="btn btn-outline-danger crm-proposal-item-remove" title="Quitar ítem" aria-label="Quitar ítem">
                <i class="mdi mdi-close"></i>
            </button>
        </div>
    `;

    row.querySelector('.crm-proposal-item-remove')?.addEventListener('click', () => {
        row.remove();
        if (container.querySelectorAll('.crm-proposal-item').length === 0) {
            addPropuestaItemRow();
        }
    });

    container.appendChild(row);
}

function togglePropuestaSearchPanel(type) {
    const codePanel = document.getElementById('crmPropuestaCodePanel');
    const packagePanel = document.getElementById('crmPropuestaPackagePanel');
    const target = type === 'package' ? packagePanel : codePanel;
    const other = type === 'package' ? codePanel : packagePanel;

    other?.classList.add('d-none');
    target?.classList.toggle('d-none');

    if (type === 'package' && target && !target.classList.contains('d-none')) {
        searchPropuestaPackages(true);
        document.getElementById('crmPropuestaPackageSearch')?.focus();
    }
    if (type === 'code' && target && !target.classList.contains('d-none')) {
        document.getElementById('crmPropuestaCodeSearch')?.focus();
    }
}

async function searchPropuestaCodes() {
    const input = document.getElementById('crmPropuestaCodeSearch');
    const results = document.getElementById('crmPropuestaCodeResults');
    const query = input?.value?.trim() ?? '';
    if (!results) {
        return;
    }
    if (query === '') {
        results.innerHTML = '<div class="crm-list-empty">Escribe un código o descripción para buscar</div>';
        return;
    }

    results.innerHTML = '<div class="crm-list-empty">Buscando códigos...</div>';
    try {
        const basePath = resolveBasePath();
        const response = await fetch(resolveReadPath(`${basePath}/crm/catalog/codes?q=${encodeURIComponent(query)}&limit=20`), {
            credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload?.success === false) {
            throw new Error(payload?.error || 'No se pudieron buscar códigos');
        }
        renderPropuestaCodeResults(Array.isArray(payload.data) ? payload.data : []);
    } catch (error) {
        results.innerHTML = `<div class="crm-list-empty">${escapeHtml(error?.message || 'No se pudieron buscar códigos')}</div>`;
    }
}

async function searchPropuestaPackages(loadDefault = false) {
    const input = document.getElementById('crmPropuestaPackageSearch');
    const results = document.getElementById('crmPropuestaPackageResults');
    const query = input?.value?.trim() ?? '';
    if (!results) {
        return;
    }
    if (!loadDefault && query === '') {
        results.innerHTML = '<div class="crm-list-empty">Escribe el nombre del paquete o presiona buscar para ver activos</div>';
        return;
    }

    results.innerHTML = '<div class="crm-list-empty">Buscando paquetes...</div>';
    try {
        const basePath = resolveBasePath();
        const response = await fetch(resolveReadPath(`${basePath}/crm/catalog/packages?q=${encodeURIComponent(query)}&limit=20`), {
            credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload?.success === false) {
            throw new Error(payload?.error || 'No se pudieron buscar paquetes');
        }
        renderPropuestaPackageResults(Array.isArray(payload.data) ? payload.data : []);
    } catch (error) {
        results.innerHTML = `<div class="crm-list-empty">${escapeHtml(error?.message || 'No se pudieron buscar paquetes')}</div>`;
    }
}

function renderPropuestaCodeResults(codes) {
    const results = document.getElementById('crmPropuestaCodeResults');
    if (!results) {
        return;
    }
    results.innerHTML = '';

    if (codes.length === 0) {
        results.innerHTML = '<div class="crm-list-empty">Sin códigos encontrados</div>';
        return;
    }

    codes.forEach(code => {
        const price = Number(code.valor_facturar_nivel1 ?? code.valor_facturar_nivel2 ?? code.valor_facturar_nivel3 ?? 0) || 0;
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'crm-proposal-search-item';
        item.innerHTML = `
            <span>
                <strong>${escapeHtml(code.codigo || `Código #${code.id || ''}`)}</strong>
                <small>${escapeHtml(code.descripcion || code.short_description || 'Sin descripción')}</small>
            </span>
            <span class="badge text-bg-light text-dark">${escapeHtml(formatCurrency(price))}</span>
        `;
        item.addEventListener('click', () => {
            addPropuestaItemRow({
                description: `${code.codigo || ''} - ${code.descripcion || code.short_description || ''}`.trim(),
                quantity: 1,
                unit_price: price,
                discount_percent: 0,
                code_id: code.id || null,
            });
            document.getElementById('crmPropuestaCodePanel')?.classList.add('d-none');
        });
        results.appendChild(item);
    });
}

function renderPropuestaPackageResults(packages) {
    const results = document.getElementById('crmPropuestaPackageResults');
    if (!results) {
        return;
    }
    results.innerHTML = '';

    if (packages.length === 0) {
        results.innerHTML = '<div class="crm-list-empty">Sin paquetes encontrados</div>';
        return;
    }

    packages.forEach(pkg => {
        const items = Array.isArray(pkg.items) ? pkg.items : [];
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'crm-proposal-search-item';
        item.innerHTML = `
            <span>
                <strong>${escapeHtml(pkg.name || `Paquete #${pkg.id || ''}`)}</strong>
                <small>${escapeHtml(pkg.description || 'Sin descripción')} · ${items.length || pkg.items_count || pkg.total_items || 0} ítem(s)</small>
            </span>
            <span class="badge text-bg-light text-dark">${escapeHtml(formatCurrency(pkg.total_amount || pkg.computed_total || 0))}</span>
        `;
        item.addEventListener('click', () => {
            if (items.length === 0) {
                notify('El paquete no tiene ítems para agregar', false);
                return;
            }
            items.forEach(packageItem => {
                addPropuestaItemRow({
                    description: packageItem.description || 'Ítem de paquete',
                    quantity: packageItem.quantity || 1,
                    unit_price: packageItem.unit_price || 0,
                    discount_percent: packageItem.discount_percent || 0,
                    code_id: packageItem.code_id || null,
                    package_id: pkg.id || null,
                });
            });
            document.getElementById('crmPropuestaPackagePanel')?.classList.add('d-none');
        });
        results.appendChild(item);
    });
}

function renderPropuestaControls() {
    const form = document.getElementById('crmPropuestaForm');
    const help = document.getElementById('crmPropuestaHelp');
    const submit = form?.querySelector('button[type="submit"]');
    const leadId = document.getElementById('crmLeadId')?.value || currentDetalle?.crm_lead_id || '';
    const hasLead = String(leadId || '').trim() !== '';

    if (help) {
        help.textContent = hasLead
            ? `Se creará como borrador vinculado al lead #${leadId}.`
            : 'Vincula o crea un lead CRM antes de crear una propuesta.';
    }
    if (submit) {
        submit.disabled = !hasLead;
    }
    if (form && !form.querySelector('.crm-proposal-item')) {
        addPropuestaItemRow();
    }
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

    renderPropuestaControls();
}

async function submitJson(url, payload, successMessage) {
    try {
        toggleLoading(true);
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                ...csrfHeaders(),
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
            notify(successMessage);
        }

        return true;
    } catch (error) {
        console.error('CRM ▶ Error', error);
        notify(error.message || 'No se pudo completar la acción', false);
        return false;
    } finally {
        toggleLoading(false);
    }
}

function csrfHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    return token ? {'X-CSRF-TOKEN': token} : {};
}

async function submitFormData(url, formData, successMessage) {
    try {
        toggleLoading(true);
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                ...csrfHeaders(),
            },
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
            notify(successMessage);
        }

        return true;
    } catch (error) {
        console.error('CRM ▶ Error adjunto', error);
        notify(error.message || 'No se pudo completar la acción', false);
        return false;
    } finally {
        toggleLoading(false);
    }
}

function openCrmPanel(entityId, nombrePaciente) {
    const element = document.getElementById('crmOffcanvas');
    if (!element) {
        console.warn('CRM ▶ No se encontró el panel lateral');
        return;
    }

    if (!offcanvasInstance && typeof bootstrap !== 'undefined' && bootstrap.Offcanvas) {
        offcanvasInstance = new bootstrap.Offcanvas(element);
    }

    currentEntityId = entityId;
    currentData = null;

    const subtitle = document.getElementById('crmOffcanvasSubtitle');
    if (subtitle) {
        const nombre = nombrePaciente && nombrePaciente.trim() !== '' ? nombrePaciente : `${entityLabelCap()} #${entityId}`;
        subtitle.textContent = nombre;
    }

    toggleLoading(true);
    toggleError();
    setFormsDisabled(true);
    clearCrmSections();

    if (offcanvasInstance) {
        offcanvasInstance.show();
    } else {
        showFallbackOffcanvas(element);
    }

    loadCrmData(entityId);
}

function openEntityCrm(entityId, nombrePaciente = '') {
    const parsed = Number.parseInt(String(entityId ?? ''), 10);
    if (!Number.isFinite(parsed) || parsed <= 0) {
        notify(`No se pudo identificar ${panelConfig.entityArticle} ${panelConfig.entityLabel} ${panelConfig.entitySelectionSuffix}`, false);
        return false;
    }

    openCrmPanel(parsed, nombrePaciente);
    return true;
}

function showFallbackOffcanvas(element) {
    element.classList.add('show');
    element.style.visibility = 'visible';
    element.removeAttribute('aria-hidden');
    element.setAttribute('aria-modal', 'true');
    document.body.classList.add('overflow-hidden');

    let backdrop = document.getElementById('crmOffcanvasFallbackBackdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'crmOffcanvasFallbackBackdrop';
        backdrop.className = 'offcanvas-backdrop fade show';
        backdrop.addEventListener('click', closeFallbackOffcanvas, { passive: true });
        document.body.appendChild(backdrop);
    }

    element.querySelectorAll('[data-bs-dismiss=\"offcanvas\"]').forEach((button) => {
        if (button.dataset.crmFallbackBound === '1') {
            return;
        }
        button.dataset.crmFallbackBound = '1';
        button.addEventListener('click', closeFallbackOffcanvas);
    });
}

function closeFallbackOffcanvas() {
    const element = document.getElementById('crmOffcanvas');
    if (element) {
        element.classList.remove('show');
        element.style.visibility = 'hidden';
        element.setAttribute('aria-hidden', 'true');
        element.removeAttribute('aria-modal');
    }

    document.body.classList.remove('overflow-hidden');
    const backdrop = document.getElementById('crmOffcanvasFallbackBackdrop');
    if (backdrop && backdrop.parentElement) {
        backdrop.parentElement.removeChild(backdrop);
    }
}

async function loadCrmData(entityId) {
    try {
        const basePath = resolveBasePath();
        const response = await fetch(resolveReadPath(`${basePath}/${entityId}/crm`), { credentials: 'same-origin' });

        // Intenta parsear JSON; si falla, intenta leer texto para mostrar un error útil
        let data;
        let rawText = '';
        try {
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                data = await response.json();
            } else {
                rawText = await response.text();
                data = rawText ? JSON.parse(rawText) : null;
            }
        } catch {
            try {
                rawText = rawText || (await response.text());
            } catch {}
            const preview = rawText ? ` (preview: ${rawText.slice(0, 160)}...)` : '';
            throw new Error('Respuesta no válida del servidor (no JSON)' + preview);
        }

        const responsePayload = data && typeof data === 'object' ? data : null;
        if (!response.ok || responsePayload?.success === false) {
            const serverMessage = (responsePayload && typeof responsePayload.error === 'string') ? responsePayload.error : '';
            let friendlyMessage = serverMessage;

            if (response.status === 401) {
                friendlyMessage = 'Sesión expirada. Actualiza la página e inicia sesión nuevamente.';
            } else if (response.status === 404) {
                friendlyMessage = serverMessage || `${entityLabelCap()} no encontrada o eliminada.`;
            } else if (response.status === 422) {
                friendlyMessage = serverMessage || `La ${panelConfig.entityLabel} tiene datos incompletos para mostrar el CRM.`;
            } else if (!friendlyMessage && response.status) {
                friendlyMessage = `Error ${response.status} al cargar la información CRM`;
            }

            if (!friendlyMessage) {
                friendlyMessage = 'No se pudo cargar la información CRM';
            }

            throw new Error(friendlyMessage);
        }

        renderCrmData(responsePayload.data);
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
        toggleError(`No se encontró información CRM para ${panelConfig.entityArticle} ${panelConfig.entityLabel}`);
        return;
    }

    currentData = data;
    currentDetalle = data.detalle;
    currentLead = data.lead ?? null;

    updateLeadControls(currentDetalle, currentLead);
    renderResumen(data.detalle, currentLead, data.whatsapp_context || null);
    renderCommunicationDefaults(data.detalle, data.whatsapp_context || null);
    renderPropuestaControls();
    setChecklistSectionVisible(false);
    loadChecklistState(currentEntityId);
    renderNotas(data.notas ?? []);
    renderCobertura(data.cobertura_mails ?? []);
    renderPropuestas(data.propuestas ?? []);
    renderAdjuntos(data.adjuntos ?? []);
    const panelTasks = buildTasksForPanel(data.tareas ?? [], data.checklist ?? []);
    renderTareas(panelTasks);
    updateTareasResumen(panelTasks);
    renderChecklistFallbackFromTasks(data.tareas ?? []);
    renderCampos(data.campos_personalizados ?? []);
    renderBloqueos(data.bloqueos_agenda ?? []);
}

async function loadChecklistState(entityId) {
    const list = document.getElementById('crmChecklistList');
    const resumen = document.getElementById('crmChecklistResumen');
    if (!list || !entityId) {
        return;
    }

    list.innerHTML = '<div class="crm-list-empty">Cargando checklist...</div>';
    if (resumen) {
        resumen.textContent = '';
    }

    try {
        const basePath = resolveBasePath();
        const response = await fetch(resolveReadPath(`${basePath}/${entityId}/crm/checklist-state`), {
            credentials: 'same-origin',
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data?.success === false) {
            throw new Error(data?.error || 'No se pudo cargar el checklist');
        }

        const tasks = mergeCrmTasks(currentData?.tareas ?? [], Array.isArray(data.tasks) ? data.tasks : []);
        if (currentData && typeof currentData === 'object') {
            currentData.tareas = tasks;
            currentData.checklist = Array.isArray(data.checklist) ? data.checklist : [];
            currentData.checklist_progress = data.checklist_progress || {};
        }
        checklistTasksBySlug = tasks.reduce((carry, task) => {
            const slug = String(task?.checklist_slug || '').trim();
            if (slug !== '') {
                carry[slug] = task;
            }

            return carry;
        }, {});

        renderChecklist(data.checklist || [], data.checklist_progress || {}, tasks);
        const panelTasks = buildTasksForPanel(tasks, data.checklist || []);
        renderTareas(panelTasks);
        updateTareasResumen(panelTasks);
    } catch (error) {
        checklistTasksBySlug = {};
        list.innerHTML = `<div class="crm-list-empty">${escapeHtml(error?.message || 'No se pudo cargar el checklist')}</div>`;
    }
}

function mergeCrmTasks(primaryTasks, secondaryTasks) {
    const merged = new Map();

    [...(Array.isArray(primaryTasks) ? primaryTasks : []), ...(Array.isArray(secondaryTasks) ? secondaryTasks : [])]
        .filter(task => task && typeof task === 'object')
        .forEach((task, index) => {
            const key = task.id !== undefined && task.id !== null && String(task.id).trim() !== ''
                ? `id:${task.id}`
                : `fallback:${task.checklist_slug || task.task_key || task.titulo || task.title || index}`;

            merged.set(key, {
                ...(merged.get(key) || {}),
                ...task,
            });
        });

    return Array.from(merged.values());
}

function buildTasksForPanel(tasks, checklist) {
    const merged = mergeCrmTasks(Array.isArray(tasks) ? tasks : [], []);
    const checklistItems = Array.isArray(checklist) ? checklist : [];
    const existingSlugs = new Set(
        merged
            .map(task => String(task?.checklist_slug || '').trim())
            .filter(Boolean)
    );

    checklistItems.forEach((item, index) => {
        const slug = String(item?.slug || '').trim();
        if (slug === '' || existingSlugs.has(slug)) {
            return;
        }

        merged.push({
            id: '',
            titulo: String(item?.label || `Paso ${index + 1}`),
            descripcion: `Paso del checklist ${panelConfig.entityLabel}`,
            estado: item?.completed ? 'completada' : 'pendiente',
            checklist_slug: slug,
            task_key: `checklist:${slug}`,
            assigned_to: '',
            assigned_name: 'Sin asignar',
            created_name: 'Checklist',
            due_date: '',
            remind_at: '',
            priority: '',
            completed_at: item?.completado_at || null,
            _virtualChecklist: true,
        });
    });

    return merged;
}

function setChecklistSectionVisible(visible) {
    const checklistList = document.getElementById('crmChecklistList');
    const section = checklistList?.closest('details.crm-section-card');
    if (!section) {
        return;
    }

    section.classList.toggle('d-none', !visible);
}

async function updateChecklistTask(task, completed) {
    if (!currentEntityId) {
        notify(selectionMessage('actualizar checklist'), false);
        return false;
    }

    const slug = String(task?.checklist_slug || '').trim();
    if (slug === '') {
        notify('La etapa del checklist no es válida', false);
        return false;
    }

    const basePath = resolveBasePath();
    try {
        toggleLoading(true);
        const response = await fetch(resolveWritePath(`${basePath}/${currentEntityId}/crm/checklist`), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                ...csrfHeaders(),
            },
            body: JSON.stringify({
                etapa_slug: slug,
                completado: completed,
            }),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || data?.success === false) {
            throw new Error(data?.error || 'No se pudo actualizar el checklist');
        }

        await loadCrmData(currentEntityId);
        notify(completed ? 'Paso completado' : 'Paso reabierto');
        return true;
    } catch (error) {
        console.error('CRM ▶ Error checklist', error);
        notify(error?.message || 'No se pudo actualizar el checklist', false);
        return false;
    } finally {
        toggleLoading(false);
    }
}

function renderResumen(detalle, lead, whatsappContext = null) {
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
    const pedidoSolicitud = detalle.derivacion_pedido_id || detalle.pedido_origen_id || detalle.form_id || '—';
    const fechaSolicitud = detalle.created_at ? formatDateTime(detalle.created_at) : 'Sin fecha registrada';
    const fechaConsulta = detalle.fecha_consulta ? formatDateTime(detalle.fecha_consulta) : 'Sin fecha de consulta';
    const dias = Number.isFinite(detalle.dias_en_estado) ? `${detalle.dias_en_estado} día(s) en el estado actual` : 'Tiempo en estado no disponible';
    const leadId = lead?.id ?? detalle.crm_lead_id ?? null;
    const leadStatus = lead?.status ?? detalle.crm_lead_status ?? 'Sin estado';
    const leadSource = lead?.source ?? detalle.crm_lead_source ?? 'Sin fuente';
    const leadUrl = lead?.url ?? (leadId ? `/crm?lead=${leadId}` : null);
    const currentUserId = resolveCurrentUserId();
    const responsableId = parsePositiveInt(detalle.crm_responsable_id);
    const assignedToMe = currentUserId !== null && responsableId !== null && currentUserId === responsableId;
    const leadInfo = leadId
        ? `Lead #${escapeHtml(String(leadId))} · ${escapeHtml(leadStatus)} · ${escapeHtml(leadSource)}`
        : 'Sin lead vinculado aún';
    const wa = whatsappContext && typeof whatsappContext === 'object' ? whatsappContext : null;
    const waLabel = wa?.matched
        ? `Conversación #${escapeHtml(String(wa.conversation_id || ''))}${wa.unread_count ? ` · ${escapeHtml(String(wa.unread_count))} sin leer` : ''}${wa.last_message_at ? ` · Último mensaje ${escapeHtml(formatDateTime(wa.last_message_at))}` : ''}`
        : wa?.search
            ? `Buscar ${escapeHtml(String(wa.search))} en WhatsApp`
            : 'Sin teléfono utilizable para WhatsApp';
    const waUrl = wa?.conversation_url || wa?.search_url || null;

    header.innerHTML = `
        <div class="d-flex flex-column gap-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge text-bg-secondary">HC ${escapeHtml(String(hc))}</span>
                <span class="badge text-bg-info">${escapeHtml(pipeline)}</span>
                <span class="badge text-bg-light text-dark">${escapeHtml(prioridad)}</span>
                ${assignedToMe ? '<span class="badge text-bg-primary">Asignada a ti</span>' : ''}
                ${leadId ? `<span class="badge text-bg-primary">Lead #${escapeHtml(String(leadId))}</span>` : ''}
            </div>
            <div>
                <h5 class="mb-1">${escapeHtml(nombre)}</h5>
                <p class="text-muted mb-0">${escapeHtml(procedimiento)}</p>
            </div>
            <div class="row g-2 small text-muted">
                <div class="col-6"><strong>Estado actual:</strong> ${escapeHtml(estado)}</div>
                <div class="col-6"><strong>Afiliación:</strong> ${escapeHtml(afiliacion)}</div>
                <div class="col-6"><strong>Pedido/Formulario:</strong> ${escapeHtml(String(pedidoSolicitud))}</div>
                <div class="col-6"><strong>Fecha solicitud:</strong> ${escapeHtml(fechaSolicitud)}</div>
                <div class="col-6"><strong>Responsable:</strong> ${escapeHtml(responsable)}</div>
                <div class="col-6"><strong>Contacto:</strong> ${escapeHtml(telefono)} • ${escapeHtml(correo)}</div>
                <div class="col-6"><strong>Fecha consulta:</strong> ${escapeHtml(fechaConsulta)}</div>
                <div class="col-6"><strong>Notas:</strong> ${totalNotas}</div>
                <div class="col-6"><strong>Adjuntos:</strong> ${totalAdjuntos}</div>
                <div class="col-6"><strong>Tareas activas:</strong> ${tareasPendientes}/${tareasTotales}</div>
                <div class="col-6"><strong>Próx. vencimiento:</strong> ${escapeHtml(proximoVencimiento)}</div>
                <div class="col-12"><strong>Seguimiento:</strong> ${escapeHtml(dias)}</div>
                <div class="col-12"><strong>Lead CRM:</strong> ${leadUrl ? `<a href="${escapeHtml(leadUrl)}" target="_blank" rel="noopener">${leadInfo}</a>` : escapeHtml(leadInfo)}</div>
                <div class="col-12"><strong>WhatsApp:</strong> ${waUrl ? `<a href="${escapeHtml(waUrl)}" target="_blank" rel="noopener">${waLabel}</a>` : waLabel}</div>
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

    const propuestasResumen = document.getElementById('crmPropuestasResumen');
    if (propuestasResumen) {
        const propuestas = Array.isArray(currentData?.propuestas) ? currentData.propuestas : [];
        const abiertas = propuestas.filter(propuesta => !['accepted', 'rejected', 'cancelled', 'anulada', 'rechazada'].includes(String(propuesta.status || '').toLowerCase())).length;
        propuestasResumen.textContent = propuestas.length > 0 ? `${abiertas} activa(s) de ${propuestas.length}` : 'Sin propuestas';
    }
}

function renderCommunicationDefaults(detalle, whatsappContext = null) {
    if (!detalle || typeof detalle !== 'object') {
        return;
    }

    const whatsappMessage = document.getElementById('crmWhatsappMensaje');
    const whatsappConversation = document.getElementById('crmWhatsappConversationId');
    const whatsappPhone = document.getElementById('crmWhatsappPhone');
    const whatsappOpen = document.getElementById('crmWhatsappOpen');
    const emailTo = document.getElementById('crmEmailTo');
    const emailSubject = document.getElementById('crmEmailSubject');
    const emailBody = document.getElementById('crmEmailBody');

    const paciente = detalle.paciente_nombre || detalle.full_name || 'paciente';
    const procedimiento = detalle.procedimiento || detalle.examen_nombre || 'su procedimiento';
    const entityLabel = panelConfig.entityLabel || 'solicitud';
    const telefono = detalle.crm_contacto_telefono || detalle.paciente_celular || '';
    const correo = detalle.crm_contacto_email || '';
    const wa = whatsappContext && typeof whatsappContext === 'object' ? whatsappContext : {};

    if (whatsappConversation) {
        whatsappConversation.value = wa.conversation_id ? String(wa.conversation_id) : '';
    }
    if (whatsappPhone) {
        whatsappPhone.value = telefono;
    }
    if (whatsappOpen) {
        const url = wa.conversation_url || wa.search_url || wa.url || '';
        whatsappOpen.href = url || '#';
        whatsappOpen.classList.toggle('d-none', url === '');
    }
    if (whatsappMessage && whatsappMessage.value.trim() === '') {
        whatsappMessage.value = `Hola ${paciente}, le escribimos de Consulmed para dar seguimiento a ${entityLabel === 'examen' ? 'su examen' : 'su solicitud'} de ${procedimiento}.`;
    }
    if (emailTo && emailTo.value.trim() === '') {
        emailTo.value = correo;
    }
    if (emailSubject && emailSubject.value.trim() === '') {
        emailSubject.value = entityLabel === 'examen' ? 'Seguimiento de examen' : 'Seguimiento de solicitud quirúrgica';
    }
    if (emailBody && emailBody.value.trim() === '') {
        emailBody.value = `Estimado/a ${paciente},\n\nLe escribimos de Consulmed para dar seguimiento a ${entityLabel === 'examen' ? 'su examen' : 'su solicitud'} de ${procedimiento}.\n\nSaludos cordiales.`;
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

function renderCobertura(correos) {
    const list = document.getElementById('crmCoberturaList');
    const resumen = document.getElementById('crmCoberturaResumen');
    if (!list) {
        return;
    }

    list.innerHTML = '';

    if (!Array.isArray(correos) || correos.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'crm-list-empty';
        empty.textContent = 'Sin correos de cobertura registrados';
        list.appendChild(empty);
        if (resumen) {
            resumen.textContent = '0 correo(s)';
        }
        return;
    }

    correos.forEach((correo) => {
        const item = document.createElement('div');
        item.className = 'list-group-item';

        const title = document.createElement('h6');
        title.className = 'mb-1';
        title.textContent = correo.subject || correo.asunto || 'Correo de cobertura';
        item.appendChild(title);

        const meta = document.createElement('p');
        meta.className = 'mb-1 text-muted small';
        const destinatario = correo.to_email || correo.destinatario || 'Sin destinatario';
        const fecha = correo.created_at ? formatDateTime(correo.created_at) : 'Fecha no disponible';
        meta.textContent = `${destinatario} • ${fecha}`;
        item.appendChild(meta);

        const body = String(correo.body_text || correo.body || correo.descripcion || '').trim();
        if (body !== '') {
            const preview = document.createElement('div');
            preview.className = 'small text-muted';
            preview.textContent = body.length > 180 ? `${body.slice(0, 180)}...` : body;
            item.appendChild(preview);
        }

        list.appendChild(item);
    });

    if (resumen) {
        resumen.textContent = `${correos.length} correo(s)`;
    }
}

function renderPropuestas(propuestas) {
    const list = document.getElementById('crmPropuestasList');
    const resumen = document.getElementById('crmPropuestasResumen');
    if (!list) {
        return;
    }

    list.innerHTML = '';

    if (!Array.isArray(propuestas) || propuestas.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'crm-list-empty';
        empty.textContent = 'Sin propuestas CRM vinculadas al lead';
        list.appendChild(empty);
        if (resumen) {
            resumen.textContent = 'Sin propuestas';
        }
        return;
    }

    const abiertas = propuestas.filter(propuesta => !['accepted', 'rejected', 'cancelled', 'anulada', 'rechazada'].includes(String(propuesta.status || '').toLowerCase())).length;
    if (resumen) {
        resumen.textContent = `${abiertas} activa(s) de ${propuestas.length}`;
    }

    propuestas.forEach(propuesta => {
        const item = document.createElement('div');
        item.className = 'list-group-item d-flex justify-content-between align-items-start gap-3';

        const cuerpo = document.createElement('div');
        cuerpo.className = 'flex-grow-1';

        const titulo = document.createElement('h6');
        titulo.className = 'mb-1 d-flex align-items-center gap-2 flex-wrap';
        titulo.textContent = propuesta.proposal_number || `Propuesta #${propuesta.id || ''}`;

        const estado = document.createElement('span');
        estado.className = `badge ${proposalStatusBadgeClass(propuesta.status)}`;
        estado.textContent = proposalStatusLabel(propuesta.status);
        titulo.appendChild(estado);
        cuerpo.appendChild(titulo);

        const descripcion = document.createElement('p');
        descripcion.className = 'mb-1 text-muted';
        descripcion.textContent = propuesta.title || 'Propuesta sin título';
        cuerpo.appendChild(descripcion);

        const meta = document.createElement('div');
        meta.className = 'crm-task-meta-row';

        const total = document.createElement('span');
        total.className = 'crm-task-chip is-success';
        total.textContent = `Total: ${formatCurrency(propuesta.total, propuesta.currency || 'USD')}`;
        meta.appendChild(total);

        const items = document.createElement('span');
        items.className = 'crm-task-chip';
        items.textContent = `${Number.parseInt(propuesta.items_count ?? 0, 10) || 0} ítem(s)`;
        meta.appendChild(items);

        const vigencia = document.createElement('span');
        vigencia.className = 'crm-task-chip';
        vigencia.textContent = propuesta.valid_until ? `Vigencia: ${formatDate(propuesta.valid_until)}` : 'Sin vigencia';
        meta.appendChild(vigencia);

        cuerpo.appendChild(meta);
        item.appendChild(cuerpo);

        const actions = document.createElement('div');
        actions.className = 'd-flex flex-column gap-2 align-items-end';
        actions.innerHTML = `
            <button type="button" class="btn btn-sm btn-outline-danger" data-crm-proposal-action="pdf">
                <i class="mdi mdi-file-pdf-box"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-success" data-crm-proposal-action="whatsapp">
                <i class="mdi mdi-whatsapp"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-crm-proposal-action="email">
                <i class="mdi mdi-email-outline"></i>
            </button>
        `;
        actions.querySelector('[data-crm-proposal-action="pdf"]')?.addEventListener('click', () => {
            window.open(propuesta.pdf_url || `/v2/crm/proposals/${encodeURIComponent(String(propuesta.id || ''))}/pdf`, '_blank', 'noopener');
        });
        actions.querySelector('[data-crm-proposal-action="whatsapp"]')?.addEventListener('click', () => sendProposalByWhatsapp(propuesta));
        actions.querySelector('[data-crm-proposal-action="email"]')?.addEventListener('click', () => sendProposalByEmail(propuesta));
        item.appendChild(actions);

        list.appendChild(item);
    });
}

async function sendProposalByWhatsapp(propuesta) {
    if (!propuesta?.id) {
        notify('Propuesta no disponible', false);
        return;
    }

    const ok = window.confirm('Se enviará la propuesta por WhatsApp usando el link público. ¿Continuar?');
    if (!ok) {
        return;
    }

    await submitJson(
        resolveWritePath(`/v2/crm/proposals/${encodeURIComponent(String(propuesta.id))}/send-whatsapp`),
        {solicitud_id: currentEntityId},
        'Propuesta enviada por WhatsApp'
    );
    if (currentEntityId) {
        loadCrmData(currentEntityId);
    }
}

async function sendProposalByEmail(propuesta) {
    if (!propuesta?.id) {
        notify('Propuesta no disponible', false);
        return;
    }

    const defaultEmail = currentDetalle?.crm_contacto_email || '';
    const to = window.prompt('Correo de destino', defaultEmail);
    if (to === null) {
        return;
    }
    const email = to.trim();
    if (email === '') {
        notify('Indica un correo para enviar la propuesta', false);
        return;
    }

    await submitJson(
        resolveWritePath(`/v2/crm/proposals/${encodeURIComponent(String(propuesta.id))}/send-email`),
        {
            to: email,
            subject: `Propuesta ${propuesta.proposal_number || ''}`.trim(),
            attach_pdf: true,
        },
        'Propuesta enviada por correo'
    );
    if (currentEntityId) {
        loadCrmData(currentEntityId);
    }
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
        empty.textContent = `No hay tareas registradas para ${panelConfig.entityArticle} ${panelConfig.entityLabel}`;
        list.appendChild(empty);
        updateTareasResumen([]);
        return;
    }

    const currentUserId = resolveCurrentUserId();
    const sorted = [...tareas].sort((left, right) => {
        const leftDone = String(left?.estado || '').trim().toLowerCase() === 'completada';
        const rightDone = String(right?.estado || '').trim().toLowerCase() === 'completada';
        if (leftDone !== rightDone) {
            return leftDone ? 1 : -1;
        }

        const leftDue = String(left?.due_date || left?.due_at || '');
        const rightDue = String(right?.due_date || right?.due_at || '');
        if (leftDue && rightDue && leftDue !== rightDue) {
            return leftDue.localeCompare(rightDue);
        }
        if (leftDue && !rightDue) {
            return -1;
        }
        if (!leftDue && rightDue) {
            return 1;
        }

        return String(right?.created_at || '').localeCompare(String(left?.created_at || ''));
    });

    sorted.forEach(tarea => {
        const checklistLinked = isChecklistLinkedTask(tarea);
        const virtualChecklist = Boolean(tarea?._virtualChecklist) || (checklistLinked && parsePositiveInt(tarea?.id) === null);
        const item = document.createElement('div');
        item.className = 'list-group-item crm-task-item d-flex justify-content-between align-items-start gap-3';
        item.classList.add(tarea.estado === 'completada' ? 'is-done' : 'is-open');

        const cuerpo = document.createElement('div');
        cuerpo.className = 'flex-grow-1';

        const titulo = document.createElement('h6');
        titulo.className = 'mb-1 d-flex align-items-center gap-2';
        titulo.textContent = tarea.titulo || 'Tarea sin título';

        const assignedToId = parsePositiveInt(tarea.assigned_to);
        const assignedToCurrentUser = currentUserId !== null && assignedToId !== null && currentUserId === assignedToId;

        const estadoBadge = document.createElement('span');
        estadoBadge.className = `badge ${estadoBadgeClass(tarea.estado)}`;
        estadoBadge.textContent = tarea.estado || 'pendiente';
        titulo.appendChild(estadoBadge);

        if (assignedToCurrentUser) {
            const assignedBadge = document.createElement('span');
            assignedBadge.className = 'badge text-bg-primary';
            assignedBadge.textContent = 'Asignada a ti';
            titulo.appendChild(assignedBadge);
            item.classList.add('border-start', 'border-3', 'border-primary');
        }

        cuerpo.appendChild(titulo);

        if (tarea.descripcion) {
            const descripcion = document.createElement('p');
            descripcion.className = 'mb-1 text-muted';
            descripcion.textContent = tarea.descripcion;
            cuerpo.appendChild(descripcion);
        }

        const asignado = tarea.assigned_name || 'Sin asignar';
        const asignadoTexto = assignedToCurrentUser ? `${asignado} (tú)` : asignado;
        const creador = tarea.created_name || 'Equipo';
        const due = tarea.due_date ? formatDate(tarea.due_date) : 'Sin fecha límite';

        const chips = document.createElement('div');
        chips.className = 'crm-task-meta-row';

        const responsableChip = document.createElement('span');
        responsableChip.className = 'crm-task-chip';
        responsableChip.textContent = `Responsable: ${asignadoTexto}`;
        chips.appendChild(responsableChip);

        const creadorChip = document.createElement('span');
        creadorChip.className = 'crm-task-chip';
        creadorChip.textContent = `Creador: ${creador}`;
        chips.appendChild(creadorChip);

        if (checklistLinked) {
            const checklistChip = document.createElement('span');
            checklistChip.className = 'crm-task-chip';
            checklistChip.textContent = 'Vinculada al checklist';
            chips.appendChild(checklistChip);
        }

        const prioridad = taskPriorityLabel(tarea.priority || tarea.prioridad || '');
        if (prioridad) {
            const prioridadChip = document.createElement('span');
            prioridadChip.className = 'crm-task-chip';
            prioridadChip.textContent = `Prioridad: ${prioridad}`;
            chips.appendChild(prioridadChip);
        }

        const dueChip = document.createElement('span');
        const hasDue = Boolean(tarea.due_date);
        const isDone = tarea.estado === 'completada';
        dueChip.className = `crm-task-chip${hasDue && !isDone ? ' is-alert' : isDone ? ' is-success' : ''}`;
        dueChip.textContent = `Límite: ${due}`;
        chips.appendChild(dueChip);

        cuerpo.appendChild(chips);

        item.appendChild(cuerpo);

        const acciones = document.createElement('div');
        acciones.className = 'd-flex flex-column gap-2 align-items-end';

        const editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'btn btn-sm btn-outline-primary';
        editBtn.innerHTML = '<i class="mdi mdi-pencil-outline"></i> Editar';
        editBtn.addEventListener('click', () => editTarea(tarea));
        acciones.appendChild(editBtn);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = tarea.estado === 'completada'
            ? 'btn btn-sm btn-outline-secondary'
            : 'btn btn-sm btn-outline-success';
        btn.innerHTML = tarea.estado === 'completada'
            ? '<i class="mdi mdi-restore"></i> Reabrir'
            : '<i class="mdi mdi-check-circle-outline"></i> Completar';
        btn.addEventListener('click', async () => {
            const nuevoEstado = tarea.estado === 'completada' ? 'pendiente' : 'completada';
            if (virtualChecklist) {
                await updateChecklistTask(tarea, nuevoEstado === 'completada');
                return;
            }
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

    updateTareasResumen(sorted);
}

function renderChecklist(checklist, progress, tasks = []) {
    const list = document.getElementById('crmChecklistList');
    const resumen = document.getElementById('crmChecklistResumen');
    const progressBar = document.getElementById('crmChecklistProgressBar');
    const next = document.getElementById('crmChecklistNext');
    if (!list) {
        return;
    }

    list.innerHTML = '';

    const items = Array.isArray(checklist) ? checklist : [];
    const total = Number(progress?.total ?? items.length ?? 0);
    const completed = Number(progress?.completed ?? 0);
    const percent = total > 0 ? Math.max(0, Math.min(100, Math.round((completed / total) * 100))) : 0;

    if (resumen) {
        resumen.textContent = total > 0 ? `${completed}/${total} completadas` : 'Sin checklist';
    }
    if (progressBar) {
        progressBar.style.width = `${percent}%`;
    }
    if (next) {
        if (progress?.next_label) {
            next.innerHTML = `<span class="badge text-bg-primary">Siguiente</span><strong>${escapeHtml(progress.next_label)}</strong>`;
        } else if (total > 0 && completed === total) {
            next.innerHTML = '<span class="badge text-bg-success">Completado</span><strong>Checklist finalizado</strong>';
        } else {
            next.innerHTML = '';
        }
    }

    if (!items.length) {
        const empty = document.createElement('div');
        empty.className = 'crm-list-empty';
        empty.textContent = 'Sin checklist disponible';
        list.appendChild(empty);
        return;
    }

    items.forEach((item) => {
        const task = checklistTasksBySlug[item?.slug || ''];
        const card = document.createElement('div');
        card.className = `crm-checklist-item${item?.completed ? ' is-completed' : ''}`;
        if (!item?.completed) {
            card.classList.add('is-pending');
        }
        if (progress?.next_slug && item?.slug === progress.next_slug) {
            card.classList.add('is-current');
        }

        const topline = document.createElement('div');
        topline.className = 'crm-checklist-item-topline';
        topline.innerHTML = `<span class="crm-checklist-dot"></span><span>${item?.completed ? 'Paso completado' : progress?.next_slug === item?.slug ? 'En curso' : 'Pendiente'}</span>`;
        card.appendChild(topline);

        const head = document.createElement('div');
        head.className = 'crm-checklist-item-head';

        const title = document.createElement('div');
        title.className = 'crm-checklist-item-title';
        title.textContent = item?.label || item?.slug || 'Paso';

        const badge = document.createElement('span');
        badge.className = item?.completed ? 'badge text-bg-success' : 'badge text-bg-light text-dark';
        badge.textContent = item?.completed ? 'Completada' : 'Pendiente';

        head.appendChild(title);
        head.appendChild(badge);
        card.appendChild(head);

        const meta = document.createElement('div');
        meta.className = 'crm-checklist-item-meta';
        meta.textContent = item?.completado_at
            ? `Completada el ${formatDateTime(item.completado_at)}`
            : 'Aún no completada';
        card.appendChild(meta);

        if (task) {
            const taskMeta = document.createElement('div');
            taskMeta.className = 'crm-checklist-item-meta';
            taskMeta.textContent = `Tarea CRM #${task.id} · ${task.estado || task.status || 'pendiente'}`;
            card.appendChild(taskMeta);

            const taskActions = document.createElement('div');
            taskActions.className = 'd-flex flex-wrap gap-2 mt-2';

            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'btn btn-sm btn-outline-primary';
            editBtn.innerHTML = '<i class="mdi mdi-pencil-outline"></i> Editar tarea';
            editBtn.addEventListener('click', () => editTarea(task));
            taskActions.appendChild(editBtn);

            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = item?.completed ? 'btn btn-sm btn-outline-secondary' : 'btn btn-sm btn-outline-success';
            toggleBtn.innerHTML = item?.completed
                ? '<i class="mdi mdi-restore"></i> Marcar pendiente'
                : '<i class="mdi mdi-check-circle-outline"></i> Completar';
            toggleBtn.addEventListener('click', () => {
                actualizarEstadoTarea(task.id, item?.completed ? 'pendiente' : 'completada');
            });
            taskActions.appendChild(toggleBtn);

            card.appendChild(taskActions);
        }

        const note = String(item?.nota || '').trim();
        if (note) {
            const noteNode = document.createElement('div');
            noteNode.className = 'crm-checklist-item-note';
            noteNode.textContent = note;
            card.appendChild(noteNode);
        }

        list.appendChild(card);
    });
}

function renderChecklistFallbackFromTasks(tasks) {
    const list = document.getElementById('crmChecklistList');
    const resumen = document.getElementById('crmChecklistResumen');
    if (!list || list.children.length > 0 || !Array.isArray(tasks) || tasks.length === 0) {
        return;
    }

    const items = tasks.map((task, index) => ({
        slug: String(task?.checklist_slug || task?.title || task?.titulo || `task-${index + 1}`),
        label: String(task?.title || task?.titulo || `Paso ${index + 1}`),
        completed: String(task?.estado || task?.status || '').trim().toLowerCase() === 'completada',
        completado_at: task?.completed_at || null,
        nota: null,
    }));

    const completed = items.filter(item => item.completed).length;
    renderChecklist(items, {
        total: items.length,
        completed,
    }, tasks);

    if (resumen) {
        resumen.textContent = `${completed}/${items.length} completadas`;
    }
}

function updateTareasResumen(tareas) {
    const tareasResumen = document.getElementById('crmTareasResumen');
    if (!tareasResumen) {
        return;
    }

    const items = Array.isArray(tareas) ? tareas.filter(task => task && typeof task === 'object') : [];
    if (!items.length) {
        tareasResumen.textContent = 'Sin tareas registradas';
        return;
    }

    const total = items.length;
    const pendientes = items.filter(task => String(task?.estado || task?.status || 'pendiente').trim().toLowerCase() !== 'completada').length;
    tareasResumen.textContent = `${pendientes} pendientes de ${total}`;
}

function formatCampoTypeLabel(type) {
    const normalized = String(type || 'texto').trim().toLowerCase();
    const labels = {
        texto: 'Texto',
        numero: 'Número',
        fecha: 'Fecha',
        lista: 'Lista',
    };

    return labels[normalized] || 'Texto';
}

function renderBloqueos(bloqueos) {
    const list = document.getElementById('crmBloqueosList');
    const resumen = document.getElementById('crmBloqueosResumen');
    if (!list) {
        return;
    }

    list.innerHTML = '';

    if (!Array.isArray(bloqueos) || bloqueos.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'crm-list-empty';
        empty.textContent = 'Sin bloqueos activos';
        list.appendChild(empty);
        if (resumen) {
            resumen.textContent = '0 bloqueos';
        }
        return;
    }

    bloqueos.forEach(bloqueo => {
        const item = document.createElement('div');
        item.className = 'list-group-item d-flex justify-content-between align-items-start gap-2';

        const cuerpo = document.createElement('div');
        cuerpo.className = 'flex-grow-1';

        const titulo = document.createElement('h6');
        titulo.className = 'mb-1';
        const doctor = bloqueo.doctor || 'Sin doctor';
        const sala = bloqueo.sala || 'Sin sala';
        titulo.textContent = `${doctor} · ${sala}`;
        cuerpo.appendChild(titulo);

        const horario = document.createElement('p');
        horario.className = 'mb-1 text-muted';
        const inicio = bloqueo.fecha_inicio ? formatDateTime(bloqueo.fecha_inicio) : '—';
        const fin = bloqueo.fecha_fin ? formatDateTime(bloqueo.fecha_fin) : '—';
        horario.textContent = `${inicio} → ${fin}`;
        cuerpo.appendChild(horario);

        if (bloqueo.motivo) {
            const motivo = document.createElement('p');
            motivo.className = 'mb-0 small text-muted';
            motivo.textContent = bloqueo.motivo;
            cuerpo.appendChild(motivo);
        }

        item.appendChild(cuerpo);

        const icon = document.createElement('span');
        icon.className = 'badge text-bg-dark';
        icon.innerHTML = '<i class="mdi mdi-calendar-lock-outline"></i>';
        item.appendChild(icon);

        list.appendChild(item);
    });

    if (resumen) {
        resumen.textContent = `${bloqueos.length} bloqueo(s)`;
    }
}

async function actualizarEstadoTarea(tareaId, estado) {
    if (!currentEntityId) {
        notify(selectionMessage('actualizar la tarea'), false);
        return;
    }

    const basePath = resolveBasePath();
    await submitJson(resolveWritePath(`${basePath}/${currentEntityId}/crm/tareas/estado`), {
        tarea_id: tareaId,
        estado,
    }, 'Tarea actualizada');
}

function normalizeDateInputValue(value) {
    const raw = String(value || '').trim();
    if (!raw) {
        return '';
    }

    const match = raw.match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : '';
}

function normalizeDateTimeLocalValue(value) {
    const raw = String(value || '').trim();
    if (!raw) {
        return '';
    }

    const normalized = raw.replace(' ', 'T');
    const match = normalized.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/);
    return match ? match[1] : '';
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

    const items = Array.isArray(campos)
        ? campos.filter(campo => String(campo?.key || '').trim() !== '')
        : [];

    if (!items.length) {
        const texto = container.dataset.emptyText || 'Sin campos adicionales';
        const empty = document.createElement('div');
        empty.className = 'crm-list-empty';
        empty.textContent = texto;
        container.appendChild(empty);
        return;
    }

    const list = document.createElement('div');
    list.className = 'crm-campos-readonly';

    items.forEach(campo => {
        const row = document.createElement('div');
        row.className = 'crm-campo-readonly';
        row.dataset.key = String(campo.key || '').trim();
        row.dataset.value = String(campo.value ?? '').trim();
        row.dataset.type = String(campo.type || 'texto').trim() || 'texto';

        const label = document.createElement('div');
        label.className = 'crm-campo-readonly-label';
        label.textContent = campo.key || 'Campo';

        const value = document.createElement('div');
        value.className = 'crm-campo-readonly-value';
        value.textContent = String(campo.value ?? '').trim() || '—';

        const meta = document.createElement('div');
        meta.className = 'crm-campo-readonly-meta';
        meta.textContent = formatCampoTypeLabel(campo.type);

        row.appendChild(label);
        row.appendChild(value);
        row.appendChild(meta);
        list.appendChild(row);
    });

    container.appendChild(list);
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

    ['crmChecklistList', 'crmNotasList', 'crmCoberturaList', 'crmPropuestasList', 'crmAdjuntosList', 'crmTareasList'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.innerHTML = '';
        }
    });

    ['crmChecklistResumen', 'crmNotasResumen', 'crmCoberturaResumen', 'crmPropuestasResumen', 'crmAdjuntosResumen', 'crmTareasResumen'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = '';
        }
    });

    const progressBar = document.getElementById('crmChecklistProgressBar');
    if (progressBar) {
        progressBar.style.width = '0%';
    }

    const next = document.getElementById('crmChecklistNext');
    if (next) {
        next.innerHTML = '';
    }

    const campos = document.getElementById('crmCamposContainer');
    if (campos) {
        campos.innerHTML = '';
    }

    checklistTasksBySlug = {};
    editingTaskId = null;
    editingChecklistSlug = '';
    setChecklistSectionVisible(false);
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

function taskPriorityLabel(priority) {
    const normalized = String(priority || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
    const labels = {
        low: 'Baja',
        baja: 'Baja',
        medium: 'Media',
        normal: 'Media',
        media: 'Media',
        high: 'Alta',
        alta: 'Alta',
        urgent: 'Urgente',
        urgente: 'Urgente',
        critical: 'Urgente',
        critica: 'Urgente',
        crítica: 'Urgente',
    };

    return labels[normalized] || '';
}

function isChecklistLinkedTask(task) {
    const slug = String(task?.checklist_slug || '').trim();
    if (slug !== '') {
        return true;
    }

    const metadata = task?.metadata;
    if (metadata && typeof metadata === 'object') {
        return String(metadata.checklist_slug || '').trim() !== '';
    }

    if (typeof metadata === 'string') {
        try {
            const parsed = JSON.parse(metadata);
            return String(parsed?.checklist_slug || '').trim() !== '';
        } catch {
            return false;
        }
    }

    return false;
}

function proposalStatusLabel(status) {
    const normalized = String(status || 'draft').trim().toLowerCase();
    const labels = {
        draft: 'Borrador',
        sent: 'Enviada',
        accepted: 'Aceptada',
        rejected: 'Rechazada',
        expired: 'Vencida',
        cancelled: 'Cancelada',
        anulada: 'Anulada',
        rechazada: 'Rechazada',
    };

    return labels[normalized] || normalized.charAt(0).toUpperCase() + normalized.slice(1);
}

function proposalStatusBadgeClass(status) {
    switch (String(status || '').trim().toLowerCase()) {
        case 'accepted':
            return 'text-bg-success';
        case 'sent':
            return 'text-bg-primary';
        case 'rejected':
        case 'expired':
        case 'rechazada':
            return 'text-bg-danger';
        case 'cancelled':
        case 'anulada':
            return 'text-bg-secondary';
        default:
            return 'text-bg-warning text-dark';
    }
}

function formatCurrency(value, currency = 'USD') {
    const amount = Number.parseFloat(value);
    if (!Number.isFinite(amount)) {
        return '—';
    }

    try {
        return new Intl.NumberFormat('es-EC', {
            style: 'currency',
            currency: currency || 'USD',
        }).format(amount);
    } catch {
        return `${currency || 'USD'} ${amount.toFixed(2)}`;
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
