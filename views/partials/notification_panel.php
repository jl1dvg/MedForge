<?php
/**
 * Panel lateral global de notificaciones.
 */
?>
<aside id="kanbanNotificationPanel" class="control-sidebar notification-panel" aria-hidden="true">
    <div class="rpanel-title notification-panel__header">
        <div class="notification-panel__headline">
            <h5 class="mb-0">Panel de notificaciones</h5>
            <p class="text-muted mb-0">Últimas alertas del Kanban, CRM y recordatorios.</p>
        </div>
        <button type="button" class="btn btn-circle btn-danger" data-action="close-panel" aria-label="Cerrar panel">
            <i class="ion ion-close text-white"></i>
        </button>
    </div>

    <div class="notification-panel__channels" data-channel-flags>
        Canales activos: Tiempo real (Pusher)
    </div>
    <div class="notification-panel__warning text-danger d-none" data-integration-warning></div>

    <ul class="nav nav-tabs control-sidebar-tabs notification-panel__tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a href="#control-sidebar-home-tab" data-bs-toggle="tab" class="nav-link active" role="tab"
               aria-controls="control-sidebar-home-tab" aria-selected="true">
                <i class="mdi mdi-message-text"></i>
                <span class="badge bg-primary rounded-pill" data-count="realtime">0</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a href="#control-sidebar-settings-tab" data-bs-toggle="tab" class="nav-link" role="tab"
               aria-controls="control-sidebar-settings-tab" aria-selected="false">
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
                <p class="mb-0">Novedades en vivo</p>
                <span class="text-end text-grey" aria-hidden="true">
                    <i class="ti-plus"></i>
                </span>
            </div>
            <div class="notification-panel__section-header mt-2">
                <span>Actualizaciones generadas desde Pusher, Kanban y CRM.</span>
            </div>
            <div class="media-list media-list-hover mt-20" data-panel-list="realtime">
                <p class="notification-empty">Aún no hay eventos recientes.</p>
            </div>
        </div>
        <div class="tab-pane fade" id="control-sidebar-settings-tab" role="tabpanel">
            <div class="flexbox notification-panel__toolbar align-items-center">
                <span class="text-grey" aria-hidden="true">
                    <i class="ti-more"></i>
                </span>
                <p class="mb-0">Pendientes y recordatorios</p>
                <span class="text-end text-grey" aria-hidden="true">
                    <i class="ti-plus"></i>
                </span>
            </div>
            <div class="notification-panel__section-header mt-2">
                <span>Cirugías próximas y tareas críticas del equipo.</span>
            </div>
            <div class="media-list media-list-hover mt-20" data-panel-list="pending">
                <p class="notification-empty">Sin recordatorios pendientes.</p>
            </div>
        </div>
    </div>
</aside>
<div id="notificationPanelBackdrop" class="notification-panel__backdrop" data-action="close-panel"></div>
