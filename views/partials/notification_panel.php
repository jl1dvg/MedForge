<?php
/**
 * Panel lateral global de notificaciones.
 */
?>
<aside id="kanbanNotificationPanel" class="control-sidebar notification-panel" aria-hidden="true">
    <div class="rpanel-title notification-panel__header d-flex align-items-start justify-content-between">
        <div class="notification-panel__headline">
            <h5 class="mb-1 d-flex align-items-center">
                <i class="mdi mdi-bell-outline me-2"></i>
                Avisos del sistema
            </h5>
            <small class="text-muted">
                Notificaciones y alertas generadas por Kanban, CRM y procesos automáticos.
            </small>
            <div class="notification-panel__channels mt-1 text-muted small" data-channel-flags>
                Canal activo: <strong>Actividad del sistema (Pusher)</strong>
            </div>
        </div>

        <button type="button"
                class="btn btn-sm btn-danger ms-2"
                data-action="close-panel"
                title="Cerrar panel de avisos"
                aria-label="Cerrar panel de avisos">
            <i class="mdi mdi-close"></i>
        </button>
    </div>



    <div class="notification-panel__intro">
        <ul>
            <li><strong>Actividad del sistema:</strong> lo que está ocurriendo ahora.</li>
            <li><strong>Alertas pendientes:</strong> cosas que requieren revisión.</li>
        </ul>
    </div>

    <div class="notification-panel__warning text-danger d-none" data-integration-warning></div>
    <div class="notification-panel__stats">
        <span>Recibidas: <strong data-summary-count="received">0</strong></span>
        <span>Por revisar: <strong data-summary-count="unread">0</strong></span>
        <button type="button"
                class="btn btn-xs btn-outline-primary"
                data-action="mark-all-reviewed"
                title="Marcar todas las alertas como revisadas"
                aria-label="Marcar todas las alertas como revisadas">
            Marcar todas como revisadas
        </button>
    </div>

    <ul class="nav nav-tabs control-sidebar-tabs notification-panel__tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a href="#control-sidebar-home-tab" data-bs-toggle="tab" class="nav-link active" role="tab"
               aria-controls="control-sidebar-home-tab" aria-selected="true"
               title="Eventos en tiempo real">
                <i class="mdi mdi-message-text"></i>
                <span class="badge bg-primary rounded-pill" data-count="realtime">0</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a href="#control-sidebar-settings-tab" data-bs-toggle="tab" class="nav-link" role="tab"
               aria-controls="control-sidebar-settings-tab" aria-selected="false"
               title="Alertas pendientes por revisar">
                <i class="mdi mdi-playlist-check"></i>
                <span class="badge bg-secondary rounded-pill" data-count="pending">0</span>
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="control-sidebar-home-tab" role="tabpanel">
            <div class="flexbox notification-panel__toolbar align-items-center">
                <span class="text-grey" aria-hidden="true">
                    <i class="ti-more"></i>
                </span>
                <p class="mb-0">Actividad del sistema</p>
                <span class="text-end text-grey" aria-hidden="true">
                    <i class="ti-plus"></i>
                </span>
            </div>
            <div class="notification-panel__section-header mt-2">
                <span>Eventos informativos generados por Kanban, CRM y procesos en tiempo real.</span>
            </div>
            <div class="media-list media-list-hover mt-20" data-panel-list="realtime">
                <p class="notification-empty">Sin actividad reciente del sistema.</p>
            </div>
        </div>
        <div class="tab-pane fade" id="control-sidebar-settings-tab" role="tabpanel">
            <div class="flexbox notification-panel__toolbar align-items-center">
                <span class="text-grey" aria-hidden="true">
                    <i class="ti-more"></i>
                </span>
                <p class="mb-0">Alertas pendientes</p>
                <span class="text-end text-grey" aria-hidden="true">
                    <i class="ti-plus"></i>
                </span>
            </div>
            <div class="notification-panel__section-header mt-2">
                <span>Recordatorios que requieren revisión o seguimiento del equipo.</span>
            </div>
            <div class="media-list media-list-hover mt-20" data-panel-list="pending">
                <p class="notification-empty">Sin alertas pendientes por revisar.</p>
            </div>
        </div>
    </div>
</aside>
<div id="notificationPanelBackdrop" class="notification-panel__backdrop" data-action="close-panel"></div>
