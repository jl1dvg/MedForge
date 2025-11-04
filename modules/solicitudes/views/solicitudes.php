<?php
/** @var string $username */
/** @var string $pageTitle */
/** @var array $scripts */
$scripts = array_merge($scripts ?? [], [
    'js/pages/solicitudes/index.js',
]);

?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Solicitudes de Cirugías</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Solicitudes</li>
                    </ol>
                </nav>
            </div>
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

        .kanban-card strong {
            font-size: 1.05em;
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

        @media (max-width: 900px) {
            .kanban-column {
                min-width: 160px;
            }
        }
    </style>
    <div class="box">
        <div class="box-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="kanbanDateFilter" class="form-label">Fecha</label>
                    <input type="text" id="kanbanDateFilter" class="form-control" placeholder="Seleccione un rango">
                </div>
                <div class="col-md-3">
                    <label for="kanbanAfiliacionFilter" class="form-label">Afiliación</label>
                    <select id="kanbanAfiliacionFilter" class="form-select">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="kanbanDoctorFilter" class="form-label">Doctor</label>
                    <select id="kanbanDoctorFilter" class="form-select">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="col-md-3">
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

    <div class="kanban-board kanban-board-wrapper d-flex justify-content-between p-3 bg-light flex-nowrap gap-3">
        <?php
        $estados = [
            'Recibido' => 'recibido',
            'Revisión Códigos' => 'revision-codigos',
            'Docs Completos' => 'docs-completos',
            'Aprobación Anestesia' => 'aprobacion-anestesia',
            'Listo para Agenda' => 'listo-para-agenda',
        ];
        $colores = [
            'recibido' => 'primary',
            'revision-codigos' => 'info',
            'docs-completos' => 'success',
            'aprobacion-anestesia' => 'warning',
            'listo-para-agenda' => 'dark',
        ];
        foreach ($estados as $estadoLabel => $estadoId):
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
                        <a id="exportExcel" class="fs-18 text-gray hover-info" href="#" aria-label="Exportar solicitudes">
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
                <h5 class="modal-title" id="prefacturaModalLabel">Detalle de Solicitud</h5>
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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip-utils/0.1.0/jszip-utils.min.js"></script>
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
