<?php
/** @var string $username */
/** @var string $pageTitle */
/** @var array $realtime */

$realtime = array_merge(
    [
        'enabled' => false,
        'key' => '',
        'cluster' => '',
        'channel' => 'examenes-kanban',
        'event' => 'nuevo-examen',
        'desktop_notifications' => false,
        'auto_dismiss_seconds' => 0,
    ],
    $realtime ?? []
);

if (!isset($styles) || !is_array($styles)) {
    $styles = [];
}

$styles[] = 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css';

if (!isset($scripts) || !is_array($scripts)) {
    $scripts = [];
}

array_push(
    $scripts,
    'https://cdn.jsdelivr.net/momentjs/latest/moment.min.js',
    'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jszip-utils/0.1.0/jszip-utils.min.js'
);
?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Solicitudes de Exámenes</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Exámenes</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a class="btn btn-primary" href="/examenes/turnero" target="_blank" rel="noopener">
                <i class="mdi mdi-monitor"></i> Abrir turnero
            </a>
        </div>
    </div>
</div>

<section class="content">
    <style>
        .kanban-card {
            border: 1px solid #e1e5eb;
            background: #fff;
            box-shadow: 0 2px 8px rgba(60, 60, 100, 0.04);
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 12px;
            transition: box-shadow 0.2s, background 0.2s;
            min-width: 100%;
            max-width: 100%;
            width: 100%;
            position: relative;
        }

        .kanban-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .kanban-toolbar h4 {
            margin-bottom: 0.2rem;
        }

        .kanban-toolbar .text-muted {
            font-size: 0.9rem;
        }

        .view-toggle .btn {
            min-width: 120px;
        }

        .view-toggle .btn.active {
            background: #0ea5e9;
            color: #fff;
            border-color: #0ea5e9;
            box-shadow: 0 3px 12px rgba(14, 165, 233, 0.35);
        }

        .examenes-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .overview-card {
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.25);
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            padding: 1rem 1.2rem;
            box-shadow: 0 10px 30px rgba(15, 118, 110, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .overview-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(37, 99, 235, 0.12);
        }

        .overview-card h6 {
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.05em;
            color: #6366f1;
            margin-bottom: 0.75rem;
        }

        .overview-card .count {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }

        .overview-card .meta {
            font-size: 0.85rem;
            color: #475569;
            margin-top: 0.4rem;
        }

        .overview-card .badge {
            font-size: 0.75rem;
        }

        .kanban-card strong {
            font-size: 1.05em;
        }

        .kanban-card-header {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .kanban-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            overflow: hidden;
            position: relative;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(15, 118, 110, 0.15);
        }

        .kanban-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .kanban-avatar--placeholder {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.15), rgba(14, 165, 233, 0.45));
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .kanban-avatar__placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
            text-transform: uppercase;
            width: 100%;
            height: 100%;
        }

        .kanban-avatar--placeholder .kanban-avatar__placeholder {
            color: inherit;
        }

        .kanban-card-body {
            flex: 1;
            display: grid;
            gap: 0.25rem;
        }

        .kanban-card:hover,
        .kanban-card.active {
            background: #f5faff;
            box-shadow: 0 8px 20px rgba(0, 150, 255, 0.08);
        }

        .kanban-items {
            min-height: 150px;
            padding: 0.5em;
            border-radius: 10px;
        }

        .kanban-column {
            flex: 1 1 0;
            min-width: 220px;
            background: #f8fafc;
            border: 1px solid #eef1f5;
            border-radius: 16px;
            box-shadow: 0 1px 6px rgba(140, 150, 180, 0.04);
        }

        .kanban-column h5 {
            font-weight: 600;
            font-size: 1.13em;
            padding-top: 10px;
            margin-bottom: 0.5em;
            border-top: 4px solid rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fafc;
        }

        .kanban-card.dragging {
            opacity: 0.7;
            transform: scale(1.02);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .kanban-items.drop-area-highlight {
            background-color: #f0f8ff;
            border: 2px dashed #007bff;
            transition: background-color 0.2s ease;
        }

        .kanban-card-actions {
            border-top: 1px solid rgba(148, 163, 184, 0.3);
            padding-top: 0.75rem;
        }

        .kanban-card-actions .badge-estado {
            background-color: #f1f5f9;
            color: #475569;
            font-size: 0.75rem;
        }

        .kanban-card-actions .badge-turno {
            background-color: rgba(56, 189, 248, 0.18);
            color: #0c4a6e;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .kanban-card-actions .llamar-turno-btn[aria-busy="true"] {
            pointer-events: none;
        }

        .kanban-card .crm-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            background-color: rgba(14, 165, 233, 0.12);
            color: #075985;
        }

        .kanban-card .crm-meta {
            display: grid;
            gap: 0.1rem;
            margin-top: 0.5rem;
            font-size: 0.78rem;
            color: #475569;
        }

        .kanban-card .crm-meta span {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .kanban-card .crm-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.5rem;
        }

        .kanban-card .crm-badges .badge {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .table-view {
            background: #fff;
            border-radius: 16px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 6px 24px rgba(15, 23, 42, 0.08);
            padding: 1rem;
        }

        .table-view thead th {
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom-width: 1px;
        }

        .table-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 3px 12px rgba(59, 130, 246, 0.2);
        }

        .table-avatar-placeholder {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            background: rgba(59, 130, 246, 0.18);
            color: #1d4ed8;
        }

        .table-view .badge {
            font-size: 0.75rem;
        }

        .table-view .table tr.table-active {
            background: rgba(14, 165, 233, 0.08);
        }

        .table-view-empty {
            border: 1px dashed rgba(148, 163, 184, 0.5);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            color: #64748b;
            font-size: 0.95rem;
        }

        #crmOffcanvas {
            --bs-offcanvas-width: min(100vw, 480px);
            --bs-offcanvas-zindex: 2050;
        }

        .crm-offcanvas-backdrop {
            z-index: 2040 !important;
            background-color: rgba(15, 23, 42, 0.45);
        }

        #crmCamposContainer .crm-campo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        #crmCamposContainer .crm-campo input,
        #crmCamposContainer .crm-campo select {
            width: 100%;
        }

        .crm-list-empty {
            font-size: 0.85rem;
            color: #64748b;
            background: #f8fafc;
            border: 1px dashed #cbd5f5;
            border-radius: 8px;
            padding: 0.75rem;
        }

        .crm-offcanvas-section + .crm-offcanvas-section {
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            padding-top: 1.25rem;
            margin-top: 1.25rem;
        }

        .crm-note-item small {
            color: #64748b;
        }

        .crm-task-item .badge {
            font-size: 0.7rem;
        }

        .crm-task-item button {
            white-space: nowrap;
        }

        .crm-scrollable {
            min-height: 0;
        }

        .kanban-card .crm-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            background-color: rgba(14, 165, 233, 0.12);
            color: #075985;
        }

        .kanban-card .crm-meta {
            display: grid;
            gap: 0.1rem;
            margin-top: 0.5rem;
            font-size: 0.78rem;
            color: #475569;
        }

        .kanban-card .crm-meta span {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .kanban-card .crm-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.5rem;
        }

        .kanban-card .crm-badges .badge {
            background-color: #f1f5f9;
            color: #0f172a;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        #crmCamposContainer .crm-campo {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        #crmCamposContainer .crm-campo input,
        #crmCamposContainer .crm-campo select {
            width: 100%;
        }

        .crm-list-empty {
            font-size: 0.85rem;
            color: #64748b;
            background: #f8fafc;
            border: 1px dashed #cbd5f5;
            border-radius: 8px;
            padding: 0.75rem;
        }

        .crm-offcanvas-section + .crm-offcanvas-section {
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            padding-top: 1.25rem;
            margin-top: 1.25rem;
        }

        .crm-note-item small {
            color: #64748b;
        }

        .crm-task-item .badge {
            font-size: 0.7rem;
        }

        .crm-task-item button {
            white-space: nowrap;
        }

        .crm-scrollable {
            min-height: 0;
        }

        @media (max-width: 900px) {
            .kanban-column {
                min-width: 160px;
            }
        }

        /* --- CRM Offcanvas layout helpers --- */
        .offcanvas-body {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .crm-fixed-top {
            flex: 0 0 auto; /* no grow */
        }
        .crm-scrollable {
            flex: 1 1 auto; /* take remaining height */
            min-height: 0;  /* allow child overflow */
            overflow: auto;
        }
        .accordion-button[data-preserve-disabled="true"] {
            pointer-events: auto !important;
        }
    </style>
    <div class="kanban-toolbar">
        <div>
            <h4 class="fw-bold mb-0">Exámenes</h4>
            <div class="text-muted">
                <span id="examenesTotalCount">0</span> examenes activas
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group view-toggle" role="group" aria-label="Cambiar vista">
                <button type="button" class="btn btn-outline-secondary active" data-examenes-view="kanban">
                    <i class="mdi mdi-view-kanban"></i> Tablero
                </button>
                <button type="button" class="btn btn-outline-secondary" data-examenes-view="table">
                    <i class="mdi mdi-table-large"></i> Tabla
                </button>
            </div>
            <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#examenesFilters" aria-expanded="false" aria-controls="examenesFilters">
                <i class="mdi mdi-filter-variant"></i> Filtros
            </button>
            <button class="btn btn-outline-secondary" type="button" data-notification-panel-toggle="true">
                <i class="mdi mdi-bell-outline"></i> Avisos
            </button>
        </div>
    </div>

    <div id="examenesOverview" class="examenes-overview"></div>

    <div class="collapse show" id="examenesFilters">
        <div class="box mb-3">
            <div class="box-body">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label for="kanbanSearchFilter" class="form-label">Buscar</label>
                        <input type="search" id="kanbanSearchFilter" class="form-control" placeholder="Paciente, HC o procedimiento">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="kanbanDateFilter" class="form-label">Fecha</label>
                        <input type="text" id="kanbanDateFilter" class="form-control" placeholder="Seleccione un rango">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="kanbanAfiliacionFilter" class="form-label">Afiliación</label>
                        <select id="kanbanAfiliacionFilter" class="form-select">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="kanbanDoctorFilter" class="form-label">Doctor</label>
                        <select id="kanbanDoctorFilter" class="form-select">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label for="kanbanSemaforoFilter" class="form-label">Prioridad</label>
                        <select id="kanbanSemaforoFilter" class="form-select">
                            <option value="">Todas</option>
                            <option value="normal">🟢 Normal (≤ 3 días)</option>
                            <option value="pendiente">🟡 Pendiente (4–7 días)</option>
                            <option value="urgente">🔴 Urgente (&gt; 7 días)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $estados = [
        'Recibido' => 'recibido',
        'Llamado' => 'llamado',
        'En atención' => 'en-atencion',
        'Revisión Códigos' => 'revision-codigos',
        'Docs Completos' => 'docs-completos',
        'Aprobación Anestesia' => 'aprobacion-anestesia',
        'Listo para Agenda' => 'listo-para-agenda',
    ];
    $colores = [
        'recibido' => 'primary',
        'llamado' => 'warning',
        'en-atencion' => 'success',
        'revision-codigos' => 'info',
        'docs-completos' => 'success',
        'aprobacion-anestesia' => 'warning',
        'listo-para-agenda' => 'dark',
    ];
    ?>

    <div id="examenesViewKanban" class="kanban-board kanban-board-wrapper d-flex justify-content-between p-3 bg-light flex-nowrap gap-3">
        <?php foreach ($estados as $estadoLabel => $estadoId):
            $color = $colores[$estadoId] ?? 'secondary';
            ?>
            <div class="kanban-column kanban-column-wrapper bg-white rounded shadow-sm p-2">
                <h5 class="text-center">
                    <?= htmlspecialchars($estadoLabel, ENT_QUOTES, 'UTF-8') ?>
                    <span class="badge bg-<?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>" id="count-<?= htmlspecialchars($estadoId, ENT_QUOTES, 'UTF-8') ?>">0</span>
                    <small class="text-muted" id="percent-<?= htmlspecialchars($estadoId, ENT_QUOTES, 'UTF-8') ?>"></small>
                </h5>
                <div class="kanban-items" id="kanban-<?= htmlspecialchars($estadoId, ENT_QUOTES, 'UTF-8') ?>" aria-live="polite"></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="examenesViewTable" class="table-view d-none">
        <div class="table-responsive">
            <table class="table align-middle" id="examenesTable">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Detalle</th>
                        <th>Estado</th>
                        <th>Pipeline CRM</th>
                        <th>Responsable</th>
                        <th>Prioridad</th>
                        <th>Turno</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div id="examenesTableEmpty" class="table-view-empty d-none">
            No hay examenes para los filtros seleccionados.
        </div>
    </div>

    <?php
    $estadoMeta = [];
    foreach ($estados as $label => $slug) {
        $estadoMeta[$slug] = [
            'label' => $label,
            'slug' => $slug,
            'color' => $colores[$slug] ?? 'secondary',
        ];
    }
    ?>

    <script>
        window.__KANBAN_MODULE__ = {
            key: 'examenes',
            basePath: '/examenes',
            storageKeyView: 'examenes:view-mode',
            dataKey: '__examenesKanban',
            estadosMetaKey: '__examenesEstadosMeta',
            selectors: {
                prefix: 'examenes',
            },
            strings: {
                singular: 'examen',
                plural: 'exámenes',
                capitalizedPlural: 'Exámenes',
                articleSingular: 'el',
                articleSingularShort: 'el',
            },
            realtime: <?= json_encode([
                'enabled' => (bool)($realtime['enabled'] ?? false),
                'key' => (string)($realtime['key'] ?? ''),
                'cluster' => (string)($realtime['cluster'] ?? ''),
                'channel' => (string)($realtime['channel'] ?? 'examenes-kanban'),
                'event' => (string)($realtime['event'] ?? 'nuevo-examen'),
                'events' => $realtime['events'] ?? [],
                'channels' => $realtime['channels'] ?? [],
                'desktop_notifications' => (bool)($realtime['desktop_notifications'] ?? false),
                'auto_dismiss_seconds' => $realtime['auto_dismiss_seconds'] ?? 0,
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?>,
        };
        window.__examenesEstadosMeta = <?= json_encode($estadoMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?>;
    </script>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="crmOffcanvas" aria-labelledby="crmOffcanvasLabel">
        <div class="offcanvas-header">
            <div>
                <h5 class="offcanvas-title mb-0" id="crmOffcanvasLabel">Gestión CRM del examen</h5>
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

            <!-- Top controls collapsed by default to save vertical space -->
            <div class="accordion crm-fixed-top" id="crmAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="acc-head-resumen">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#acc-resumen" aria-expanded="false" aria-controls="acc-resumen" data-preserve-disabled="true">
                            <i class="mdi mdi-information-outline me-2"></i> Resumen del examen
                        </button>
                    </h2>
                    <div id="acc-resumen" class="accordion-collapse collapse" aria-labelledby="acc-head-resumen" data-bs-parent="#crmAccordion">
                        <div class="accordion-body">
                            <div id="crmResumenCabecera" class="bg-light border rounded p-3"></div>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="acc-head-detalles">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#acc-detalles" aria-expanded="false" aria-controls="acc-detalles" data-preserve-disabled="true">
                            <i class="mdi mdi-tune me-2"></i> Detalles CRM
                        </button>
                    </h2>
                    <div id="acc-detalles" class="accordion-collapse collapse" aria-labelledby="acc-head-detalles" data-bs-parent="#crmAccordion">
                        <div class="accordion-body">
                            <form id="crmDetalleForm" class="border rounded p-3">
                                <div class="row g-3">
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
                                        <label for="crmFuente" class="form-label">Fuente / convenio</label>
                                        <input type="text" id="crmFuente" name="fuente" class="form-control" list="crmFuenteOptions" placeholder="Ej. aseguradora, referido, campaña">
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
                                </div>
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label mb-0">Campos personalizados</label>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="crmAgregarCampo" data-preserve-disabled="true">
                                            <i class="mdi mdi-plus-circle-outline me-1"></i>Añadir campo
                                        </button>
                                    </div>
                                    <div id="crmCamposContainer" data-empty-text="Sin campos adicionales"></div>
                                </div>
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="mdi mdi-content-save-outline me-1"></i>Guardar detalles
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="crm-scrollable flex-grow-1 overflow-auto">
                <section class="crm-offcanvas-section">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Notas internas</h6>
                        <small class="text-muted" id="crmNotasResumen"></small>
                    </div>
                    <div id="crmNotasList" class="list-group mb-3"></div>
                    <form id="crmNotaForm">
                        <label for="crmNotaTexto" class="form-label">Agregar nota</label>
                        <textarea id="crmNotaTexto" class="form-control mb-2" rows="3" placeholder="Registrar avances, autorizaciones o conversaciones" required></textarea>
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
                        <div class="col-md-6">
                            <label for="crmTareaFecha" class="form-label">Fecha límite</label>
                            <input type="date" id="crmTareaFecha" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="crmTareaRecordatorio" class="form-label">Recordatorio</label>
                            <input type="datetime-local" id="crmTareaRecordatorio" class="form-control">
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
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="crmOffcanvas" aria-labelledby="crmOffcanvasLabel">
        <div class="offcanvas-header">
            <div>
                <h5 class="offcanvas-title mb-0" id="crmOffcanvasLabel">Gestión CRM del examen</h5>
                <p class="text-muted small mb-0" id="crmOffcanvasSubtitle"></p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar" data-preserve-disabled="true"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column gap-3">
            <div id="crmLoading" class="alert alert-info d-none" role="status">
                <div class="d-flex align-items-center gap-2">
                    <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                    <span>Cargando información CRM...</span>
                </div>
            </div>
            <div id="crmError" class="alert alert-danger d-none" role="alert"></div>
            <div id="crmResumenCabecera" class="bg-light border rounded p-3"></div>
            <form id="crmDetalleForm" class="border rounded p-3">
                <div class="row g-3">
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
                        <input type="text" id="crmFuente" name="fuente" class="form-control" list="crmFuenteOptions" placeholder="Ej. aseguradora, referido, campaña">
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
                </div>
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">Campos personalizados</label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="crmAgregarCampo">
                            <i class="mdi mdi-plus-circle-outline me-1"></i>Añadir campo
                        </button>
                    </div>
                    <div id="crmCamposContainer" data-empty-text="Sin campos adicionales"></div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-content-save-outline me-1"></i>Guardar detalles
                    </button>
                </div>
            </form>
            <div class="crm-scrollable flex-grow-1 overflow-auto">
                <section class="crm-offcanvas-section">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Notas internas</h6>
                        <small class="text-muted" id="crmNotasResumen"></small>
                    </div>
                    <div id="crmNotasList" class="list-group mb-3"></div>
                    <form id="crmNotaForm">
                        <label for="crmNotaTexto" class="form-label">Agregar nota</label>
                        <textarea id="crmNotaTexto" class="form-control mb-2" rows="3" placeholder="Registrar avances, autorizaciones o conversaciones" required></textarea>
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
                        <div class="col-md-6">
                            <label for="crmTareaFecha" class="form-label">Fecha límite</label>
                            <input type="date" id="crmTareaFecha" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="crmTareaRecordatorio" class="form-label">Recordatorio</label>
                            <input type="datetime-local" id="crmTareaRecordatorio" class="form-control">
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
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="box">
                <div class="box-body">
                    <div class="media media-single px-0 align-items-center">
                        <div class="me-3 bg-danger-light h-50 w-50 l-h-50 rounded text-center d-flex align-items-center justify-content-center">
                            <span class="fs-24 text-danger"><i class="fa fa-file-zip-o"></i></span>
                        </div>
                        <div class="d-flex flex-column flex-grow-1">
                            <span class="title fw-500 fs-16 text-truncate">Exportar ZIP</span>
                            <small class="text-muted">Descarga el respaldo de documentos asociados</small>
                        </div>
                        <a id="exportExcel" class="fs-18 text-gray hover-info" href="#" aria-label="Exportar examenes">
                            <i class="fa fa-download"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="prefacturaModal" tabindex="-1" aria-hidden="true" aria-labelledby="prefacturaModalLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="prefacturaModalLabel">Detalle del Examen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="prefacturaContent">Cargando información...</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnRevisarCodigos" data-estado="Revisión Códigos">✅ Códigos Revisado</button>
                <button type="button" class="btn btn-warning" id="btnSolicitarCobertura" data-estado="Docs Completos">📤 Solicitar Cobertura</button>
            </div>
        </div>
    </div>
</div>

<div id="toastContainer" style="position: fixed; top: 1rem; right: 1rem; z-index: 1055;"></div>

<script>
    window.MEDF_PusherConfig = <?= json_encode($realtime, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?>;
</script>
<?php if (!empty($realtime['enabled']) && !empty($realtime['key'])): ?>
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
<?php endif; ?>
<script type="module" src="<?= asset('js/pages/examenes/index.js') ?>"></script>
