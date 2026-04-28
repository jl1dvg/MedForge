@once
    @push('styles')
        <link rel="stylesheet" href="/css/pages/solicitudes-crm-panel.css?v={{ @filemtime(public_path('css/pages/solicitudes-crm-panel.css')) ?: time() }}">
    @endpush
@endonce

<div class="offcanvas offcanvas-end" tabindex="-1" id="crmOffcanvas" aria-labelledby="crmOffcanvasLabel">
    <div class="offcanvas-header">
        <div>
            <h5 class="offcanvas-title mb-0" id="crmOffcanvasLabel">Gestión CRM de la solicitud</h5>
            <p class="text-muted small mb-0" id="crmOffcanvasSubtitle"></p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar" data-preserve-disabled="true"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column gap-3">
        <div id="crmLoading" class="alert alert-info d-none crm-fixed-top" role="status">
            <div class="d-flex align-items-center gap-2">
                <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                <span>Cargando información CRM...</span>
            </div>
        </div>
        <div id="crmError" class="alert alert-danger d-none crm-fixed-top" role="alert"></div>

        <details class="crm-section-card crm-fixed-top" open>
            <summary>
                <span class="crm-section-title">Resumen de la solicitud</span>
                <span class="crm-section-summary">
                    <span class="text-muted small">Información principal</span>
                    <i class="mdi mdi-chevron-down crm-section-chevron"></i>
                </span>
            </summary>
            <div class="crm-section-body">
                <div id="crmResumenCabecera" class="bg-light border rounded p-3"></div>
            </div>
        </details>

        <details class="crm-section-card crm-fixed-top">
            <summary>
                <span class="crm-section-title">Detalles CRM</span>
                <span class="crm-section-summary">
                    <span class="text-muted small">Seguimiento y configuración</span>
                    <i class="mdi mdi-chevron-down crm-section-chevron"></i>
                </span>
            </summary>
            <div class="crm-section-body">
                <form id="crmDetalleForm">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label for="crmPipeline" class="form-label">Etapa CRM</label>
                            <select id="crmPipeline" name="pipeline_stage" class="form-select">
                                <option value="">Recibido</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="crmResponsable" class="form-label">Responsable principal</label>
                            <select id="crmResponsable" name="responsable_id" class="form-select">
                                <option value="">Sin asignar</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="crmLeadIdInput" class="form-label">Lead CRM vinculado</label>
                            <div class="input-group">
                                <input type="number" min="1" id="crmLeadIdInput" class="form-control" placeholder="Se asigna automáticamente">
                                <button type="button" class="btn btn-outline-secondary" id="crmLeadOpen" title="Abrir lead en CRM">
                                    <i class="mdi mdi-open-in-new"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" id="crmLeadUnlink" title="Desvincular lead">
                                    <i class="mdi mdi-link-off"></i>
                                </button>
                            </div>
                            <input type="hidden" id="crmLeadId" name="crm_lead_id">
                            <small class="form-text text-muted" id="crmLeadHelp">Sin lead vinculado. Al guardar se creará automáticamente.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="crmFuente" class="form-label">Fuente / convenio</label>
                            <input type="text" id="crmFuente" name="fuente" class="form-control" list="crmFuenteOptions" placeholder="Aseguradora, referido, campaña">
                            <datalist id="crmFuenteOptions"></datalist>
                        </div>
                        <div class="col-md-6">
                            <label for="crmSeguidores" class="form-label">Seguidores</label>
                            <select id="crmSeguidores" name="seguidores[]" class="form-select" multiple></select>
                            <small class="text-muted">Usuarios que acompañan el caso y reciben alertas.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="crmContactoEmail" class="form-label">Correo de contacto</label>
                            <input type="email" id="crmContactoEmail" name="contacto_email" class="form-control" placeholder="correo@ejemplo.com">
                        </div>
                        <div class="col-md-6">
                            <label for="crmContactoTelefono" class="form-label">Teléfono de contacto</label>
                            <input type="text" id="crmContactoTelefono" name="contacto_telefono" class="form-control" placeholder="+593 ...">
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Campos personalizados</label>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="crmAgregarCampo" data-preserve-disabled="true">
                                    <i class="mdi mdi-plus-circle-outline me-1"></i>Añadir campo
                                </button>
                            </div>
                            <div id="crmCamposContainer" data-empty-text="Sin campos adicionales"></div>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-content-save-outline me-1"></i>Guardar detalles
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </details>

        <div class="crm-scrollable">
            <details class="crm-section-card" open>
                <summary>
                    <span class="crm-section-title">Checklist operativo</span>
                    <span class="crm-section-summary">
                        <small class="text-muted" id="crmChecklistResumen"></small>
                        <i class="mdi mdi-chevron-down crm-section-chevron"></i>
                    </span>
                </summary>
                <div class="crm-section-body">
                    <div class="crm-checklist-progress" aria-hidden="true">
                        <div id="crmChecklistProgressBar" class="crm-checklist-progress-bar"></div>
                    </div>
                    <div id="crmChecklistNext" class="crm-checklist-next"></div>
                    <div id="crmChecklistList" class="crm-checklist-list"></div>
                </div>
            </details>

            <details class="crm-section-card" open>
                <summary>
                    <span class="crm-section-title">Tareas y recordatorios</span>
                    <span class="crm-section-summary">
                        <small class="text-muted" id="crmTareasResumen"></small>
                        <i class="mdi mdi-chevron-down crm-section-chevron"></i>
                    </span>
                </summary>
                <div class="crm-section-body">
                    <div id="crmTareasList" class="list-group mb-3"></div>
                    <form id="crmTareaForm" class="row g-2">
                        <div class="col-md-6">
                            <label for="crmTareaTitulo" class="form-label">Título</label>
                            <input type="text" id="crmTareaTitulo" class="form-control" placeholder="Llamar al paciente" required>
                        </div>
                        <div class="col-md-6">
                            <label for="crmTareaAsignado" class="form-label">Responsable</label>
                            <select id="crmTareaAsignado" class="form-select">
                                <option value="">Sin asignar</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="crmTareaFecha" class="form-label">Fecha límite</label>
                            <input type="date" id="crmTareaFecha" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="crmTareaRecordatorio" class="form-label">Recordatorio</label>
                            <input type="datetime-local" id="crmTareaRecordatorio" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="crmTareaPrioridad" class="form-label">Prioridad</label>
                            <select id="crmTareaPrioridad" class="form-select">
                                <option value="">Normal</option>
                                <option value="high">Alta</option>
                                <option value="medium">Media</option>
                                <option value="low">Baja</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="crmTareaDescripcion" class="form-label">Descripción</label>
                            <textarea id="crmTareaDescripcion" class="form-control" rows="2" placeholder="Detalles de la tarea"></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-outline-success">
                                <i class="mdi mdi-playlist-plus me-1"></i>Agregar tarea
                            </button>
                        </div>
                    </form>
                </div>
            </details>

            <details class="crm-section-card" open>
                <summary>
                    <span class="crm-section-title">Notas internas</span>
                    <span class="crm-section-summary">
                        <small class="text-muted" id="crmNotasResumen"></small>
                        <i class="mdi mdi-chevron-down crm-section-chevron"></i>
                    </span>
                </summary>
                <div class="crm-section-body">
                    <div id="crmNotasList" class="list-group mb-3"></div>
                    <form id="crmNotaForm">
                        <label for="crmNotaTexto" class="form-label">Agregar nota</label>
                        <textarea id="crmNotaTexto" class="form-control mb-2" rows="3" placeholder="Registrar avances del caso" required></textarea>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-comment-plus-outline me-1"></i>Guardar nota
                            </button>
                        </div>
                    </form>
                </div>
            </details>

            <details class="crm-section-card" open>
                <summary>
                    <span class="crm-section-title">Comunicaciones</span>
                    <span class="crm-section-summary">
                        <small class="text-muted">WhatsApp y correo</small>
                        <i class="mdi mdi-chevron-down crm-section-chevron"></i>
                    </span>
                </summary>
                <div class="crm-section-body">
                    <form id="crmWhatsappForm" class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="crmWhatsappMensaje" class="form-label mb-0">Mensaje WhatsApp</label>
                            <a href="#" id="crmWhatsappOpen" class="btn btn-sm btn-outline-success d-none" target="_blank" rel="noopener">
                                <i class="mdi mdi-whatsapp me-1"></i>Abrir chat
                            </a>
                        </div>
                        <input type="hidden" id="crmWhatsappConversationId">
                        <input type="hidden" id="crmWhatsappPhone">
                        <textarea id="crmWhatsappMensaje" class="form-control mb-2" rows="3" placeholder="Escribe un mensaje para el paciente"></textarea>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-outline-success">
                                <i class="mdi mdi-send-outline me-1"></i>Enviar WhatsApp
                            </button>
                        </div>
                    </form>

                    <form id="crmEmailForm" class="row g-2">
                        <div class="col-md-6">
                            <label for="crmEmailTo" class="form-label">Correo destino</label>
                            <input type="email" id="crmEmailTo" class="form-control" placeholder="correo@ejemplo.com">
                        </div>
                        <div class="col-md-6">
                            <label for="crmEmailSubject" class="form-label">Asunto</label>
                            <input type="text" id="crmEmailSubject" class="form-control" placeholder="Seguimiento de solicitud">
                        </div>
                        <div class="col-12">
                            <label for="crmEmailBody" class="form-label">Mensaje</label>
                            <textarea id="crmEmailBody" class="form-control" rows="4" placeholder="Escribe el correo para el paciente"></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="mdi mdi-email-send-outline me-1"></i>Enviar correo
                            </button>
                        </div>
                    </form>
                </div>
            </details>

            <details class="crm-section-card">
                <summary>
                    <span class="crm-section-title">Correos de cobertura</span>
                    <span class="crm-section-summary">
                        <small class="text-muted" id="crmCoberturaResumen"></small>
                        <i class="mdi mdi-chevron-down crm-section-chevron"></i>
                    </span>
                </summary>
                <div class="crm-section-body">
                    <div id="crmCoberturaList" class="list-group"></div>
                </div>
            </details>

            <details class="crm-section-card">
                <summary>
                    <span class="crm-section-title">Documentos adjuntos</span>
                    <span class="crm-section-summary">
                        <small class="text-muted" id="crmAdjuntosResumen"></small>
                        <i class="mdi mdi-chevron-down crm-section-chevron"></i>
                    </span>
                </summary>
                <div class="crm-section-body">
                    <div id="crmAdjuntosList" class="list-group mb-3"></div>
                    <form id="crmAdjuntoForm" class="row g-2 align-items-end" enctype="multipart/form-data">
                        <div class="col-sm-7">
                            <label for="crmAdjuntoArchivo" class="form-label">Archivo</label>
                            <input type="file" id="crmAdjuntoArchivo" name="archivo" class="form-control" required>
                        </div>
                        <div class="col-sm-5">
                            <label for="crmAdjuntoDescripcion" class="form-label">Descripción</label>
                            <input type="text" id="crmAdjuntoDescripcion" name="descripcion" class="form-control" placeholder="Consentimiento, póliza, etc.">
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="mdi mdi-upload me-1"></i>Subir documento
                            </button>
                        </div>
                    </form>
                </div>
            </details>

            <details class="crm-section-card">
                <summary>
                    <span class="crm-section-title">Bloqueo de agenda</span>
                    <span class="crm-section-summary">
                        <small class="text-muted" id="crmBloqueosResumen"></small>
                        <i class="mdi mdi-chevron-down crm-section-chevron"></i>
                    </span>
                </summary>
                <div class="crm-section-body">
                    <div id="crmBloqueosList" class="list-group mb-3"></div>
                    <form id="crmBloqueoForm" class="row g-2">
                        <div class="col-md-6">
                            <label for="crmBloqueoInicio" class="form-label">Inicio</label>
                            <input type="datetime-local" id="crmBloqueoInicio" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="crmBloqueoFin" class="form-label">Fin</label>
                            <input type="datetime-local" id="crmBloqueoFin" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label for="crmBloqueoDuracion" class="form-label">Duración (min)</label>
                            <input type="number" min="15" step="5" id="crmBloqueoDuracion" class="form-control" placeholder="60">
                        </div>
                        <div class="col-md-4">
                            <label for="crmBloqueoSala" class="form-label">Sala</label>
                            <input type="text" id="crmBloqueoSala" class="form-control" placeholder="Sala 1">
                        </div>
                        <div class="col-md-4">
                            <label for="crmBloqueoDoctor" class="form-label">Doctor</label>
                            <input type="text" id="crmBloqueoDoctor" class="form-control" placeholder="Nombre del médico">
                        </div>
                        <div class="col-12">
                            <label for="crmBloqueoMotivo" class="form-label">Motivo</label>
                            <input type="text" id="crmBloqueoMotivo" class="form-control" placeholder="Reserva de sala / valoración">
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-outline-dark">
                                <i class="mdi mdi-calendar-lock-outline me-1"></i>Bloquear horario
                            </button>
                        </div>
                    </form>
                </div>
            </details>
        </div>
    </div>
</div>
