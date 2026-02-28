@extends('layouts.medforge')

@php
    $columns = is_array($kanbanColumns ?? null) ? $kanbanColumns : [];
    $filters = is_array($initialFilters ?? null) ? $initialFilters : [];
@endphp

@push('styles')
    <style>
        .sol-v2-toolbar {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.05);
            margin-bottom: 14px;
        }

        .sol-v2-metrics {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            margin-bottom: 14px;
        }

        .sol-v2-metric {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            padding: 10px 12px;
        }

        .sol-v2-metric-label {
            font-size: 11px;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 4px;
        }

        .sol-v2-metric-value {
            font-size: 25px;
            line-height: 1;
            font-weight: 700;
            color: #0f172a;
        }

        .sol-v2-kanban-shell {
            overflow-x: auto;
            padding-bottom: 8px;
        }

        .sol-v2-kanban {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(9, minmax(290px, 1fr));
            min-width: 2640px;
        }

        .sol-v2-col {
            background: #f8fafc;
            border: 1px solid #dbe2ea;
            border-radius: 14px;
            min-height: 240px;
            display: flex;
            flex-direction: column;
        }

        .sol-v2-col-head {
            border-bottom: 1px solid #dbe2ea;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .sol-v2-col-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .sol-v2-col-count {
            border-radius: 999px;
            background: #e2e8f0;
            color: #334155;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
        }

        .sol-v2-col-body {
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-height: 170px;
        }

        .sol-v2-empty {
            border: 1px dashed #cbd5e1;
            color: #94a3b8;
            border-radius: 10px;
            font-size: 12px;
            text-align: center;
            padding: 16px 10px;
        }

        .sol-v2-card {
            background: #fff;
            border: 1px solid #d9e1ec;
            border-radius: 12px;
            padding: 10px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
        }

        .sol-v2-card-head {
            display: grid;
            grid-template-columns: 34px 1fr auto;
            gap: 8px;
            align-items: start;
        }

        .sol-v2-head-copy {
            min-width: 0;
        }

        .sol-v2-card h6 {
            margin: 0;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.15;
        }

        .sol-v2-avatar {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            background: #e2e8f0;
            color: #0f172a;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .sol-v2-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sol-v2-avatar-fallback {
            letter-spacing: .04em;
        }

        .sol-v2-chip {
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1.5;
            white-space: nowrap;
        }

        .sol-v2-chip.is-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .sol-v2-chip.is-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .sol-v2-chip.is-ok {
            background: #dcfce7;
            color: #166534;
        }

        .sol-v2-card-meta {
            margin-top: 5px;
            font-size: 12px;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sol-v2-card-proc {
            margin-top: 6px;
            font-size: 12px;
            color: #1f2937;
            min-height: 28px;
            line-height: 1.35;
        }

        .sol-v2-card-meta-grid {
            margin-top: 6px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 4px 8px;
            font-size: 11px;
            color: #475569;
        }

        .sol-v2-card-meta-grid span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sol-v2-tags {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .sol-v2-tag {
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
        }

        .sol-v2-tag-priority {
            background: #fee2e2;
            color: #991b1b;
        }

        .sol-v2-tag-sla {
            background: #e0f2fe;
            color: #0c4a6e;
            text-transform: uppercase;
        }

        .sol-v2-tag-ops {
            background: #f1f5f9;
            color: #334155;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .sol-v2-card-actions {
            margin-top: 9px;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .sol-v2-toast {
            position: fixed;
            right: 14px;
            bottom: 14px;
            z-index: 1080;
            min-width: 230px;
            border-radius: 10px;
            padding: 10px 12px;
            color: #fff;
            font-size: 13px;
            display: none;
        }

        .sol-v2-toast.ok {
            background: #166534;
        }

        .sol-v2-toast.err {
            background: #991b1b;
        }

        #crmOffcanvas {
            --bs-offcanvas-width: min(100vw, 500px);
            --bs-offcanvas-zindex: 2050;
        }

        .crm-fixed-top {
            flex: 0 0 auto;
        }

        .crm-scrollable {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
        }

        .crm-list-empty {
            font-size: 12px;
            color: #64748b;
            background: #f8fafc;
            border: 1px dashed #cbd5f5;
            border-radius: 8px;
            padding: 8px;
        }

        #crmCamposContainer .crm-campo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
        }

        #crmCamposContainer .crm-campo input,
        #crmCamposContainer .crm-campo select {
            width: 100%;
        }

        .crm-offcanvas-section + .crm-offcanvas-section {
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            padding-top: 14px;
            margin-top: 14px;
        }

        .crm-task-item .badge {
            font-size: 10px;
        }

        .prefactura-modal-body {
            max-height: calc(100vh - 180px);
            overflow-y: auto;
        }

        .prefactura-content-wrapper {
            min-height: 120px;
        }
    </style>
@endpush

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Solicitudes v2 (Kanban)</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Solicitudes</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <a href="/cirugias/dashboard" class="btn btn-light">
                <i class="mdi mdi-chart-line"></i> Dashboard
            </a>
        </div>
    </div>

    <section class="content">
        <div class="box sol-v2-toolbar">
            <div class="box-body">
                <form id="solV2Filters" class="row g-2 align-items-end">
                    <div class="col-lg-2 col-md-3">
                        <label for="solSearch" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="solSearch" name="search" value="{{ (string) ($filters['search'] ?? '') }}" placeholder="Paciente, HC o procedimiento">
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label for="solDateFrom" class="form-label">Desde</label>
                        <input type="date" class="form-control" id="solDateFrom" name="date_from" value="{{ (string) ($filters['date_from'] ?? '') }}">
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label for="solDateTo" class="form-label">Hasta</label>
                        <input type="date" class="form-control" id="solDateTo" name="date_to" value="{{ (string) ($filters['date_to'] ?? '') }}">
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label for="solAfiliacion" class="form-label">Afiliación</label>
                        <select id="solAfiliacion" name="afiliacion" class="form-select">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <label for="solDoctor" class="form-label">Doctor</label>
                        <select id="solDoctor" name="doctor" class="form-select">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-3">
                        <label for="solPrioridad" class="form-label">Prioridad</label>
                        <select id="solPrioridad" name="prioridad" class="form-select">
                            <option value="">Todas</option>
                            <option value="SI">Sí</option>
                            <option value="NO">No</option>
                            <option value="ALTA">Alta</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-3 d-grid">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="sol-v2-metrics" id="solV2Metrics">
            <div class="sol-v2-metric"><div class="sol-v2-metric-label">Total</div><div class="sol-v2-metric-value" id="mTotal">0</div></div>
            <div class="sol-v2-metric"><div class="sol-v2-metric-label">SLA vencido</div><div class="sol-v2-metric-value" id="mSlaVencido">0</div></div>
            <div class="sol-v2-metric"><div class="sol-v2-metric-label">SLA crítico</div><div class="sol-v2-metric-value" id="mSlaCritico">0</div></div>
            <div class="sol-v2-metric"><div class="sol-v2-metric-label">Docs faltantes</div><div class="sol-v2-metric-value" id="mDocs">0</div></div>
            <div class="sol-v2-metric"><div class="sol-v2-metric-label">Autorización pendiente</div><div class="sol-v2-metric-value" id="mAuth">0</div></div>
        </div>

        <div class="sol-v2-kanban-shell">
            <div class="sol-v2-kanban" id="solV2Kanban">
                @foreach($columns as $column)
                    <article class="sol-v2-col" data-column="{{ (string) ($column['slug'] ?? '') }}">
                        <header class="sol-v2-col-head">
                            <h5 class="sol-v2-col-title">{{ (string) ($column['label'] ?? '') }}</h5>
                            <span class="sol-v2-col-count" data-count>0</span>
                        </header>
                        <div class="sol-v2-col-body" data-body>
                            <div class="sol-v2-empty">Sin solicitudes</div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <div id="solV2Toast" class="sol-v2-toast" role="status" aria-live="polite"></div>

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

            <div id="crmResumenCabecera" class="bg-light border rounded p-3 crm-fixed-top"></div>

            <form id="crmDetalleForm" class="border rounded p-3 crm-fixed-top">
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

            <div class="crm-scrollable">
                <section class="crm-offcanvas-section">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Notas internas</h6>
                        <small class="text-muted" id="crmNotasResumen"></small>
                    </div>
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
                </section>

                <section class="crm-offcanvas-section">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Documentos adjuntos</h6>
                        <small class="text-muted" id="crmAdjuntosResumen"></small>
                    </div>
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
                </section>

                <section class="crm-offcanvas-section">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Tareas y recordatorios</h6>
                        <small class="text-muted" id="crmTareasResumen"></small>
                    </div>
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
                </section>

                <section class="crm-offcanvas-section">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Bloqueo de agenda</h6>
                        <small class="text-muted" id="crmBloqueosResumen"></small>
                    </div>
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
                </section>
            </div>
        </div>
    </div>

    <div class="modal fade" id="prefacturaModal" tabindex="-1" aria-hidden="true" aria-labelledby="prefacturaModalLabel">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="prefacturaModalLabel">Detalle de Solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body prefactura-modal-body">
                    <div class="prefactura-content-wrapper">
                        <div id="prefacturaContent">
                            <div class="d-flex align-items-center gap-2">
                                <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                                <strong>Cargando información...</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-primary d-none" id="btnGenerarTurnoModal">Generar turno</button>
                    <button type="button" class="btn btn-outline-success d-none" id="btnMarcarAtencionModal" data-estado="En atención">En atención</button>
                    <button class="btn btn-success btn-sm d-inline-flex align-items-center gap-2" type="button" id="btnSolicitarExamenesPrequirurgicos" data-bs-toggle="tooltip" title="Enviar solicitud de exámenes prequirúrgicos al paciente">
                        <i class="mdi mdi-file-multiple me-1"></i> Solicitar exámenes prequirúrgicos
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="toastContainer" style="position: fixed; top: 1rem; right: 1rem; z-index: 2055;"></div>
@endsection

@push('scripts')
    <script>
        window.__SOLICITUDES_V2_UI__ = {
            endpoints: {
                kanban: @json($kanbanEndpoint),
                actualizarEstado: @json($actualizarEstadoEndpoint),
                estado: @json($estadoEndpoint),
                crmBase: "/v2/solicitudes",
            },
            initialFilters: @json($filters),
            columns: @json($columns),
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/js/pages/solicitudes/v2-index.js"></script>
@endpush
