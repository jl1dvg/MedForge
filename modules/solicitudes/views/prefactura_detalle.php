<?php
/** @var array $viewData */

$derivacion = $viewData['derivacion'] ?? [];
$solicitud = $viewData['solicitud'] ?? [];
$consulta = $viewData['consulta'] ?? [];
$paciente = $viewData['paciente'] ?? [];
$diagnosticos = $viewData['diagnostico'] ?? [];

if (empty($solicitud)) {
    echo '<p class="text-muted mb-0">No se encontraron detalles adicionales para esta solicitud.</p>';
    return;
}

$nombrePaciente = trim(implode(' ', array_filter([
        $paciente['fname'] ?? '',
        $paciente['mname'] ?? '',
        $paciente['lname'] ?? '',
        $paciente['lname2'] ?? '',
])));

$fechaNacimiento = $paciente['fecha_nacimiento'] ?? null;
$edad = 'No disponible';
if ($fechaNacimiento) {
    try {
        $birthDate = new DateTime($fechaNacimiento);
        $edad = $birthDate->diff(new DateTime())->y . ' años';
    } catch (Exception $e) {
        $edad = 'No disponible';
    }
}

$fechaSolicitudRaw = $consulta['fecha'] ?? $solicitud['created_at'] ?? null;
$fechaSolicitud = null;
$diasTranscurridos = null;
if ($fechaSolicitudRaw) {
    try {
        $fechaSolicitud = new DateTime($fechaSolicitudRaw);
        $diasTranscurridos = $fechaSolicitud->diff(new DateTime())->days;
    } catch (Exception $e) {
        $fechaSolicitud = null;
        $diasTranscurridos = null;
    }
}

$semaforo = [
        'color' => 'secondary',
        'texto' => 'Sin datos',
        'icon' => 'bi-hourglass-split',
];
if ($diasTranscurridos !== null) {
    if ($diasTranscurridos <= 3) {
        $semaforo = ['color' => 'success', 'texto' => 'Normal', 'icon' => 'bi-check-circle'];
    } elseif ($diasTranscurridos <= 7) {
        $semaforo = ['color' => 'warning', 'texto' => 'Pendiente', 'icon' => 'bi-exclamation-circle'];
    } else {
        $semaforo = ['color' => 'danger', 'texto' => 'Urgente', 'icon' => 'bi-exclamation-triangle'];
    }
}

$vigenciaTexto = 'No disponible';
$vigenciaBadge = null;
if (!empty($derivacion['fecha_vigencia'])) {
    try {
        $vigencia = new DateTime($derivacion['fecha_vigencia']);
        $hoy = new DateTime();
        $intervalo = (int)$hoy->diff($vigencia)->format('%r%a');

        if ($intervalo >= 60) {
            $vigenciaBadge = ['color' => 'success', 'texto' => 'Vigente', 'icon' => 'bi-check-circle'];
        } elseif ($intervalo >= 30) {
            $vigenciaBadge = ['color' => 'info', 'texto' => 'Vigente', 'icon' => 'bi-info-circle'];
        } elseif ($intervalo >= 15) {
            $vigenciaBadge = ['color' => 'warning', 'texto' => 'Por vencer', 'icon' => 'bi-hourglass-split'];
        } elseif ($intervalo >= 0) {
            $vigenciaBadge = ['color' => 'danger', 'texto' => 'Urgente', 'icon' => 'bi-exclamation-triangle'];
        } else {
            $vigenciaBadge = ['color' => 'dark', 'texto' => 'Vencida', 'icon' => 'bi-x-circle'];
        }

        $vigenciaTexto = "<strong>Días para caducar:</strong> {$intervalo} días";
    } catch (Exception $e) {
        $vigenciaTexto = 'No disponible';
    }
}

$archivoHref = null;
$derivacionId = $derivacion['derivacion_id'] ?? $derivacion['id'] ?? null;
if (!empty($derivacionId)) {
    $archivoHref = '/derivaciones/archivo/' . urlencode((string)$derivacionId);
} elseif (!empty($derivacion['archivo_derivacion_path'])) {
    $archivoHref = '/' . ltrim($derivacion['archivo_derivacion_path'], '/');
}

$slaStatus = strtolower(trim((string)($solicitud['sla_status'] ?? '')));
$slaBadges = [
        'en_rango' => ['color' => 'success', 'label' => 'SLA en rango', 'icon' => 'mdi-check-circle-outline'],
        'advertencia' => ['color' => 'warning', 'label' => 'SLA 72h', 'icon' => 'mdi-timer-sand'],
        'critico' => ['color' => 'danger', 'label' => 'SLA crítico', 'icon' => 'mdi-alert-octagon'],
        'vencido' => ['color' => 'dark', 'label' => 'SLA vencido', 'icon' => 'mdi-alert'],
        'sin_fecha' => ['color' => 'secondary', 'label' => 'SLA sin fecha', 'icon' => 'mdi-calendar-remove'],
        'cerrado' => ['color' => 'secondary', 'label' => 'SLA cerrado', 'icon' => 'mdi-lock-outline'],
];
$slaBadge = $slaBadges[$slaStatus] ?? null;

$crmResponsable = $solicitud['crm_responsable_nombre'] ?? 'Sin responsable';
$crmContactoTelefono = $solicitud['crm_contacto_telefono'] ?? $paciente['celular'] ?? 'Sin teléfono';
$crmContactoCorreo = $solicitud['crm_contacto_email'] ?? 'Sin correo';
$crmFuente = $solicitud['crm_fuente'] ?? ($solicitud['fuente'] ?? 'Sin fuente');
$crmNotas = (int)($solicitud['crm_total_notas'] ?? 0);
$crmAdjuntos = (int)($solicitud['crm_total_adjuntos'] ?? 0);
$crmTareasPendientes = (int)($solicitud['crm_tareas_pendientes'] ?? 0);
$crmTareasTotal = (int)($solicitud['crm_tareas_total'] ?? 0);
$estadoRaw = trim((string)($solicitud['estado'] ?? ''));
$estadoKey = strtolower($estadoRaw);
$estadoLabel = $estadoRaw !== '' ? mb_convert_case($estadoRaw, MB_CASE_TITLE, 'UTF-8') : 'Sin estado';
$estadoBadgeMap = [
        'recibida' => 'secondary',
        'en atención' => 'info',
        'en atencion' => 'info',
        'revision codigos' => 'warning',
        'revisión códigos' => 'warning',
        'aprobada' => 'success',
        'cancelada' => 'dark',
        'rechazada' => 'danger',
        'pendiente' => 'warning',
];
$estadoBadgeColor = $estadoBadgeMap[$estadoKey] ?? 'secondary';
$diagnosticosLimitados = array_slice($diagnosticos, 0, 3);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<!-- HEADER FIJO -->
<div class="prefactura-detail-header d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
    <div class="flex-grow-1">
        <div id="prefacturaPatientSummary" class="prefactura-patient-card"></div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-xl-end">
        <?php if ($slaBadge): ?>
            <span class="badge bg-<?= htmlspecialchars($slaBadge['color'], ENT_QUOTES, 'UTF-8') ?>"
                  title="<?= htmlspecialchars($slaBadge['label'], ENT_QUOTES, 'UTF-8') ?>">
                    <i class="mdi <?= htmlspecialchars($slaBadge['icon'], ENT_QUOTES, 'UTF-8') ?> me-1"></i>
                    <?= htmlspecialchars($slaBadge['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
        <?php endif; ?>
        <?php if (!empty($solicitud['alert_reprogramacion'])): ?>
            <span class="badge bg-light text-danger border" title="Reprogramar" aria-label="Alerta de reprogramación">
                    <i class="mdi mdi-calendar-alert"></i>
                </span>
        <?php endif; ?>
        <?php if (!empty($solicitud['alert_pendiente_consentimiento'])): ?>
            <span class="badge bg-light text-warning border" title="Consentimiento pendiente"
                  aria-label="Consentimiento pendiente">
                    <i class="mdi mdi-shield-alert"></i>
                </span>
        <?php endif; ?>
    </div>
</div>


<ul class="nav nav-tabs mt-2" id="prefacturaTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="prefactura-tab-resumen-tab" data-bs-toggle="tab"
                data-bs-target="#prefactura-tab-resumen" type="button" role="tab" aria-controls="prefactura-tab-resumen"
                aria-selected="true">Resumen
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="prefactura-tab-solicitud-tab" data-bs-toggle="tab"
                data-bs-target="#prefactura-tab-solicitud" type="button" role="tab"
                aria-controls="prefactura-tab-solicitud" aria-selected="false">Solicitud
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="prefactura-tab-derivacion-tab" data-bs-toggle="tab"
                data-bs-target="#prefactura-tab-derivacion" type="button" role="tab"
                aria-controls="prefactura-tab-derivacion" aria-selected="false">Derivación
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="prefactura-tab-oftalmo-tab" data-bs-toggle="tab"
                data-bs-target="#prefactura-tab-oftalmo" type="button" role="tab" aria-controls="prefactura-tab-oftalmo"
                aria-selected="false">Checklist Preoperatorio
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="prefactura-tab-examen-tab" data-bs-toggle="tab"
                data-bs-target="#prefactura-tab-examen" type="button" role="tab" aria-controls="prefactura-tab-examen"
                aria-selected="false">Examen & Plan
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="prefactura-tab-crm-tab" data-bs-toggle="tab" data-bs-target="#prefactura-tab-crm"
                type="button" role="tab" aria-controls="prefactura-tab-crm" aria-selected="false">CRM
        </button>
    </li>
</ul>

<div class="tab-content prefactura-tab-content" id="prefacturaTabsContent">
    <div class="tab-pane fade show active" id="prefactura-tab-resumen" role="tabpanel"
         aria-labelledby="prefactura-tab-resumen-tab">
        <!-- TAB 1: Resumen -->
        <div id="prefacturaContextualActions" class="d-flex flex-column gap-2 mb-3"></div>
        <div id="prefacturaStatePlaceholder" class="prefactura-state-placeholder mb-3">
            <div class="d-flex align-items-center gap-2">
                <span class="spinner-border spinner-border-sm text-muted" role="status" aria-hidden="true"></span>
                <span class="fw-semibold">Cargando estado…</span>
            </div>
            <small class="text-muted d-block mt-1">Resumen, SLA y alertas estarán disponibles en unos segundos.</small>
        </div>
        <div id="prefacturaState" class="prefactura-state-container d-none"></div>
        <div class="mt-3">
            <h6 class="text-muted text-uppercase">Acciones</h6>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary d-none" id="btnGenerarTurnoModal">
                    <i class="mdi mdi-phone me-1"></i> Generar turno
                </button>
                <button type="button" class="btn btn-outline-success d-none" id="btnMarcarAtencionModal"
                        data-estado="En atención">
                    <i class="mdi mdi-account-clock-outline me-1"></i> En atención
                </button>
                <button type="button" class="btn btn-primary d-none" id="btnCoberturaExitosa"
                        data-estado="Revisión Códigos" data-completado="1">
                    <i class="mdi mdi-check-circle-outline me-1"></i> Cobertura exitosa
                </button>
                <button type="button" class="btn btn-outline-primary d-none" id="btnRevisarCodigos"
                        data-estado="Revisión Códigos">
                    <i class="mdi mdi-clipboard-check-outline me-1"></i> Códigos Revisado
                </button>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="prefactura-tab-solicitud" role="tabpanel"
         aria-labelledby="prefactura-tab-solicitud-tab">
        <!-- TAB 2: Solicitud -->
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-2">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-calendar3 prefactura-icon me-2" aria-label="Fecha de solicitud"></i>
                                <div>
                                    <div class="prefactura-meta-label">Fecha de solicitud</div>
                                    <?php if ($fechaSolicitud): ?>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($fechaSolicitud->format('d-m-Y')) ?>
                                        </div>
                                        <small class="text-muted">
                                            Hace <?= (int)$diasTranscurridos ?> día(s)
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">No disponible</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge prefactura-badge bg-<?= htmlspecialchars($semaforo['color']) ?> d-inline-flex align-items-center">
                                    <i class="bi <?= htmlspecialchars($semaforo['icon']) ?> prefactura-icon me-2"
                                       aria-label="Semáforo de solicitud"></i>
                                    <?= htmlspecialchars($semaforo['texto']) ?>
                                </span>
                                <?php if ($vigenciaBadge): ?>
                                    <span class="badge prefactura-badge bg-<?= htmlspecialchars($vigenciaBadge['color']) ?> d-inline-flex align-items-center">
                                        <i class="bi <?= htmlspecialchars($vigenciaBadge['icon']) ?> prefactura-icon me-2"
                                           aria-label="Vigencia de derivación"></i>
                                        <?= htmlspecialchars($vigenciaBadge['texto']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header">
                        <h6 class="card-title">
                            <i class="bi bi-folder2-open prefactura-icon me-2"
                               aria-label="Información de la solicitud"></i>
                            Información de la solicitud
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-3">
                            <div>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-clipboard-data prefactura-icon me-2" aria-label="Procedimiento"></i>
                                    <div class="prefactura-meta-label">Procedimiento</div>
                                </div>
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <p class="mb-0 prefactura-line-clamp">
                                        <?= htmlspecialchars($solicitud['procedimiento'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <button type="button"
                                            class="btn btn-link btn-sm p-0 text-decoration-none prefactura-proc-actions"
                                            data-bs-toggle="modal" data-bs-target="#prefacturaProcedimientoModal">
                                        Ver más
                                    </button>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-flag prefactura-icon me-2" aria-label="Prioridad"></i>
                                    <div class="prefactura-meta-label">Prioridad</div>
                                </div>
                                <span class="badge prefactura-badge bg-light text-dark border">
                                    <?= htmlspecialchars($solicitud['prioridad'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-activity prefactura-icon me-2" aria-label="Estado"></i>
                                    <div class="prefactura-meta-label">Estado</div>
                                </div>
                                <span class="badge prefactura-badge bg-<?= htmlspecialchars($estadoBadgeColor, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($estadoLabel, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                            <div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="bi bi-clipboard2-pulse prefactura-icon me-2"
                                       aria-label="Diagnósticos"></i>
                                    <div class="prefactura-meta-label">Diagnósticos</div>
                                </div>
                                <?php if ($diagnosticos): ?>
                                    <div class="d-flex flex-column gap-2">
                                        <?php foreach ($diagnosticosLimitados as $dx): ?>
                                            <div class="d-flex flex-column">
                                                <span class="badge bg-light text-primary border align-self-start">
                                                    <?= htmlspecialchars($dx['dx_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($dx['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                    <?= !empty($dx['lateralidad']) ? '(' . htmlspecialchars($dx['lateralidad'], ENT_QUOTES, 'UTF-8') . ')' : '' ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($diagnosticos) > 3): ?>
                                        <button type="button" class="btn btn-link btn-sm p-0 mt-2 text-decoration-none"
                                                data-bs-toggle="modal" data-bs-target="#prefacturaDiagnosticosModal">
                                            Ver todos
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <small class="text-muted">No disponibles</small>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="d-flex align-items-center mb-1">
                                    <i class="bi bi-chat-left-text prefactura-icon me-2" aria-label="Observaciones"></i>
                                    <div class="prefactura-meta-label">Observaciones</div>
                                </div>
                                <small class="text-muted">
                                    <?= htmlspecialchars($consulta['observacion'] ?? ($solicitud['observacion'] ?? 'Sin observaciones'), ENT_QUOTES, 'UTF-8') ?>
                                    <?php // REVIEW: no hay un campo explícito de observaciones de solicitud; se usa la observación disponible. ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

<div class="col-lg-4">
    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h6 class="card-title">
                <i class="bi bi-person-vcard prefactura-icon me-2" aria-label="Datos del paciente"></i>
                Datos del paciente
            </h6>
        </div>
        <div class="card-body d-flex flex-column gap-3">
            <?php // REVIEW: datos de paciente no están explicitados en las tabs solicitadas, se incluyen aquí. ?>

            <div>
                <div class="d-flex align-items-center mb-1">
                    <i class="bi bi-gender-ambiguous prefactura-icon me-2" aria-label="Sexo"></i>
                    <div class="prefactura-meta-label">Sexo</div>
                </div>
                <div class="prefactura-meta-value">
                    <?= htmlspecialchars($paciente['sexo'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>

            <div>
                <div class="d-flex align-items-center mb-1">
                    <i class="bi bi-cake2 prefactura-icon me-2" aria-label="Fecha de nacimiento"></i>
                    <div class="prefactura-meta-label">Fecha de nacimiento</div>
                </div>
                <div class="prefactura-meta-value">
                    <?php
                    if ($fechaNacimiento) {
                        try {
                            $fechaNacimientoDt = new DateTime($fechaNacimiento);
                            echo htmlspecialchars($fechaNacimientoDt->format('d-m-Y'), ENT_QUOTES, 'UTF-8');
                        } catch (Exception $e) {
                            echo 'No disponible';
                        }
                    } else {
                        echo 'No disponible';
                    }
                    ?>
                </div>
            </div>

            <div>
                <div class="d-flex align-items-center mb-1">
                    <i class="bi bi-phone prefactura-icon me-2" aria-label="Celular"></i>
                    <div class="prefactura-meta-label">Celular</div>
                </div>
                <div class="prefactura-meta-value">
                    <?= htmlspecialchars($paciente['celular'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
        </div>
    </div>
</div>
        </div>

        <div class="modal fade" id="prefacturaProcedimientoModal" tabindex="-1"
             aria-labelledby="prefacturaProcedimientoModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title" id="prefacturaProcedimientoModalLabel">Procedimiento completo</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <?= htmlspecialchars($solicitud['procedimiento'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="prefacturaDiagnosticosModal" tabindex="-1"
             aria-labelledby="prefacturaDiagnosticosModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title" id="prefacturaDiagnosticosModalLabel">Diagnósticos</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        <?php foreach ($diagnosticos as $dx): ?>
                            <div class="d-flex flex-column">
                                <span class="badge bg-light text-primary border align-self-start">
                                    <?= htmlspecialchars($dx['dx_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <small class="text-muted">
                                    <?= htmlspecialchars($dx['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    <?= !empty($dx['lateralidad']) ? '(' . htmlspecialchars($dx['lateralidad'], ENT_QUOTES, 'UTF-8') . ')' : '' ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="prefactura-tab-derivacion" role="tabpanel"
         aria-labelledby="prefactura-tab-derivacion-tab">
        <!-- TAB 3: Derivación -->
        <?php if (!empty($archivoHref) || !empty($derivacion['derivacion_id']) || !empty($derivacion['id'])): ?>
            <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap">
                <div>
                    <strong>📎 Derivación:</strong>
                    <span class="text-muted ms-1">Documento adjunto disponible.</span>
                </div>
                <a class="btn btn-sm btn-outline-primary mt-2 mt-md-0"
                   href="<?= htmlspecialchars($archivoHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-pdf"></i> Abrir PDF
                </a>
            </div>
        <?php endif; ?>

        <div class="box box-outline-primary">
            <div class="box-header">
                <h5 class="box-title"><strong>📌 Información de la Derivación</strong></h5>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><i class="bi bi-upc-scan"></i> <strong>Código
                        Derivación:</strong> <?= htmlspecialchars($derivacion['cod_derivacion'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?>
                </li>
                <li class="list-group-item"><i class="bi bi-calendar-check"></i> <strong>Fecha
                        Registro:</strong> <?= htmlspecialchars($derivacion['fecha_registro'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?>
                </li>
                <li class="list-group-item"><i class="bi bi-calendar-event"></i> <strong>Fecha
                        Vigencia:</strong> <?= htmlspecialchars($derivacion['fecha_vigencia'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?>
                </li>
                <li class="list-group-item">
                    <i class="bi bi-hourglass-split"></i> <?= $vigenciaTexto ?>
                    <?php if ($vigenciaBadge): ?>
                        <span class="badge bg-<?= htmlspecialchars($vigenciaBadge['color'], ENT_QUOTES, 'UTF-8') ?> ms-2">
                        <?= htmlspecialchars($vigenciaBadge['texto'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php endif; ?>
                </li>
                <li class="list-group-item">
                    <i class="bi bi-clipboard2-pulse"></i>
                    <strong>Diagnóstico:</strong>
                    <?php if (!empty($derivacion['diagnosticos']) && is_array($derivacion['diagnosticos'])): ?>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($derivacion['diagnosticos'] as $dx): ?>
                                <li>
                                    <span class="text-primary">
                                        <?= htmlspecialchars($dx['dx_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    — <?= htmlspecialchars($dx['descripcion'] ?? ($dx['diagnostico'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($dx['lateralidad'])): ?>
                                        (<?= htmlspecialchars($dx['lateralidad'], ENT_QUOTES, 'UTF-8') ?>)
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif (!empty($derivacion['diagnostico'])): ?>
                        <?php
                        // Si viene como string tipo "Z010 - ...; H251 - ...; ..."
                        $items = array_filter(array_map('trim', explode(';', $derivacion['diagnostico'])));
                        ?>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($items as $item): ?>
                                <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <span class="text-muted">No disponible</span>
                    <?php endif; ?>
                </li>
            </ul>
            <div class="box-body"></div>
        </div>
    </div>

    <div class="tab-pane fade" id="prefactura-tab-oftalmo" role="tabpanel" aria-labelledby="prefactura-tab-oftalmo-tab">
        <!-- TAB 4: Checklist Preoperatorio -->
        <?php
        $estadoActual = strtolower(trim((string)($solicitud['estado'] ?? '')));

        // IMPORTANTE:
        // - Estado "apto-anestesia" en el Kanban significa "PENDIENTE de confirmación por anestesia".
        // - Solo debe verse como "success" cuando la solicitud YA PASÓ de esa estación,
        //   por ejemplo: listo-para-agenda, programada, completado.
        $esAptoAnestesia = in_array($estadoActual, ['listo-para-agenda', 'programada', 'completado'], true);

        // Apto oftalmólogo:
        // Por ahora lo inferimos como "ya confirmado" si la solicitud está
        // en una etapa igual o posterior a apto-anestesia en el Kanban.
        // Idealmente esto se debería leer del checklist (etapa_slug = apto-oftalmologo).
        $esAptoOftalmo = in_array($estadoActual, ['apto-anestesia', 'listo-para-agenda', 'programada', 'completado'], true);

        $badgeOftalmo = $esAptoOftalmo
                ? '<span class="badge bg-success d-inline-flex align-items-center gap-1"><i class="bi bi-check-circle-fill"></i>Apto</span>'
                : '<span class="badge bg-warning text-dark d-inline-flex align-items-center gap-1"><i class="bi bi-hourglass-split"></i>Pendiente</span>';

        $badgeAnestesia = $esAptoAnestesia
                ? '<span class="badge bg-success d-inline-flex align-items-center gap-1"><i class="bi bi-check-circle-fill"></i>Apto</span>'
                : '<span class="badge bg-warning text-dark d-inline-flex align-items-center gap-1"><i class="bi bi-hourglass-split"></i>Pendiente</span>';

        $anestesiaResponsable = $solicitud['anestesia_responsable'] ?? ($solicitud['responsable_anestesia'] ?? null);
        $anestesiaFecha = $solicitud['anestesia_fecha'] ?? ($solicitud['fecha_anestesia'] ?? null);
        $oftalmoResponsable = $solicitud['oftalmo_responsable'] ?? ($solicitud['responsable_oftalmo'] ?? null);
        $oftalmoFecha = $solicitud['oftalmo_fecha'] ?? ($solicitud['fecha_oftalmo'] ?? null);

        // IDs unificados para botones (kanban / modal)
        $kanbanSolicitudId = isset($_GET['solicitud_id']) ? (int)$_GET['solicitud_id'] : null;
        $solicitudIdRaw = (int)($solicitud['id'] ?? 0);
        $formId = (int)($solicitud['form_id'] ?? 0);

        // PRIORIDAD: usa SIEMPRE el id que viene del kanban si existe
        $dataId = $kanbanSolicitudId ?: ($solicitudIdRaw ?: $formId);
        $solicitudIdBtn = $dataId;
        ?>
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white prefactura-card-header d-flex align-items-center gap-2">
                        <i class="bi bi-clipboard2-pulse prefactura-icon text-primary"></i>
                        <div>
                            <h6 class="prefactura-card-title">Detalles quirúrgicos</h6>
                            <div class="fw-semibold">Checklist preoperatorio</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="prefactura-meta-label">LIO / Producto</div>
                                <div class="prefactura-meta-value">
                                    <?= htmlspecialchars($solicitud['producto'] ?? ($solicitud['lente_nombre'] ?? 'No registrado'), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="prefactura-meta-label">Poder</div>
                                <div class="prefactura-meta-value">
                                    <?= htmlspecialchars($solicitud['lente_poder'] ?? ($solicitud['lente_power'] ?? ($solicitud['poder'] ?? 'No especificado')), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="prefactura-meta-label">Ojo</div>
                                <div class="prefactura-meta-value">
                                    <?= htmlspecialchars($solicitud['ojo'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="prefactura-meta-label">Incisión</div>
                                <div class="prefactura-meta-value">
                                    <?= htmlspecialchars($solicitud['incision'] ?? 'Sin especificación', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="prefactura-meta-label">Observaciones</div>
                                <div class="prefactura-meta-value">
                                    <?= htmlspecialchars($solicitud['observacion'] ?? 'Sin observaciones', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0">
                        <button class="btn btn-outline-primary" type="button" id="btnPrefacturaEditarLio"
                                data-context-action="editar-lio"
                                data-id="<?= htmlspecialchars((string)$solicitudIdRaw, ENT_QUOTES, 'UTF-8') ?>"
                                data-form-id="<?= htmlspecialchars((string)($solicitud['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-hc="<?= htmlspecialchars((string)($solicitud['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <i class="mdi mdi-eyedropper-variant"></i> Editar datos de LIO
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="d-flex flex-column gap-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white prefactura-card-header d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <h6 class="prefactura-card-title">Estados de aprobación</h6>
                                <div class="fw-semibold">Anestesia</div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button"
                                        class="btn btn-sm <?= $esAptoAnestesia ? 'btn-success' : 'btn-outline-success' ?>"
                                        data-context-action="confirmar-anestesia"
                                        data-id="<?= htmlspecialchars((string)$solicitudIdBtn, ENT_QUOTES, 'UTF-8') ?>"
                                        data-form-id="<?= htmlspecialchars((string)($solicitud['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-hc="<?= htmlspecialchars((string)($solicitud['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $esAptoAnestesia ? 'disabled' : '' ?>
                                        id="btnPrefacturaConfirmarAnestesia">
                                    <?= $esAptoAnestesia ? 'Apto por anestesia' : 'Marcar apto' ?>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                <div class="prefactura-meta-label">Estado</div>
                                <?= $badgeAnestesia ?>
                            </div>
                            <?php if (!empty($anestesiaResponsable)): ?>
                                <div class="mb-2">
                                    <div class="prefactura-meta-label">Responsable</div>
                                    <div class="prefactura-meta-value"><?= htmlspecialchars((string)$anestesiaResponsable, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($anestesiaFecha)): ?>
                                <div class="mb-2">
                                    <div class="prefactura-meta-label">Fecha</div>
                                    <div class="prefactura-meta-value"><?= htmlspecialchars((string)$anestesiaFecha, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="card border-0 bg-light mt-3" id="prefacturaCalloutPreanestesia">
                                <div class="card-body">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi bi-clipboard-check text-success"></i>
                                        <div class="fw-semibold">Preanestesia</div>
                                    </div>
                                    <div class="text-muted small">
                                        Estado actual:
                                        <span id="prefacturaEstadoActual"><?= htmlspecialchars($solicitud['estado'] ?? 'No definido', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white prefactura-card-header d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <h6 class="prefactura-card-title">Estados de aprobación</h6>
                                <div class="fw-semibold">Oftalmólogo</div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-sm <?= $esAptoOftalmo ? 'btn-success' : 'btn-outline-success' ?>" type="button"
                                        id="btnPrefacturaConfirmarOftalmo"
                                        data-context-action="confirmar-oftalmo"
                                        data-id="<?= htmlspecialchars((string)$solicitudIdBtn, ENT_QUOTES, 'UTF-8') ?>"
                                        data-form-id="<?= htmlspecialchars((string)$formId, ENT_QUOTES, 'UTF-8') ?>"
                                        data-hc="<?= htmlspecialchars((string)($solicitud['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $esAptoOftalmo ? 'disabled' : '' ?>>
                                    <?= $esAptoOftalmo ? 'Apto por oftalmólogo' : 'Marcar apto' ?>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                <div class="prefactura-meta-label">Estado</div>
                                <?= $badgeOftalmo ?>
                            </div>
                            <?php if (!empty($oftalmoResponsable)): ?>
                                <div class="mb-2">
                                    <div class="prefactura-meta-label">Responsable</div>
                                    <div class="prefactura-meta-value"><?= htmlspecialchars((string)$oftalmoResponsable, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($oftalmoFecha)): ?>
                                <div class="mb-2">
                                    <div class="prefactura-meta-label">Fecha</div>
                                    <div class="prefactura-meta-value"><?= htmlspecialchars((string)$oftalmoFecha, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="prefactura-tab-examen" role="tabpanel" aria-labelledby="prefactura-tab-examen-tab">
        <!-- TAB 5: Examen & Plan -->
        <div class="box box-outline-primary">
            <div class="box-header with-border">
                <h4 class="box-title">📝 Examen físico y plan</h4>
                <h6 class="box-subtitle">Revisa la exploración y las indicaciones en pestañas verticales.</h6>
            </div>
            <div class="box-body">
                <div class="vtabs">
                    <ul class="nav nav-tabs tabs-vertical" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#tab-examen-fisico" role="tab"
                               aria-selected="true">
                                <span><i class="ion-eye me-2"></i>Examen físico</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-plan" role="tab" aria-selected="false">
                                <span><i class="ion-document-text me-2"></i>Plan</span>
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab-examen-fisico" role="tabpanel">
                            <div class="p-15">
                                <div style="white-space: pre-wrap;">
                                    <?= htmlspecialchars($consulta['examen_fisico'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane" id="tab-plan" role="tabpanel">
                            <div class="p-15">
                                <div style="white-space: pre-wrap;">
                                    <?= htmlspecialchars($consulta['plan'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="prefactura-tab-crm" role="tabpanel" aria-labelledby="prefactura-tab-crm-tab">
        <!-- TAB 6: CRM -->
        <div class="box box-outline-secondary">
            <div class="box-header">
                <h5 class="box-title"><strong>📇 Resumen CRM</strong></h5>
            </div>
            <div class="box-body">
                <div class="prefactura-crm-grid">
                    <div class="prefactura-crm-item">
                        <small class="text-muted d-block">Responsable</small>
                        <strong><?= htmlspecialchars($crmResponsable, ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="prefactura-crm-item">
                        <small class="text-muted d-block">Contacto</small>
                        <strong><?= htmlspecialchars($crmContactoTelefono, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span class="text-muted d-block"><?= htmlspecialchars($crmContactoCorreo, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="prefactura-crm-item">
                        <small class="text-muted d-block">Fuente</small>
                        <strong><?= htmlspecialchars($crmFuente, ENT_QUOTES, 'UTF-8') ?></strong>
                    </div>
                    <div class="prefactura-crm-item">
                        <small class="text-muted d-block">Notas / Adjuntos / Tareas</small>
                        <strong>
                            <?= htmlspecialchars((string)$crmNotas, ENT_QUOTES, 'UTF-8') ?> notas ·
                            <?= htmlspecialchars((string)$crmAdjuntos, ENT_QUOTES, 'UTF-8') ?> adjuntos ·
                            <?= htmlspecialchars((string)$crmTareasPendientes, ENT_QUOTES, 'UTF-8') ?>
                            /<?= htmlspecialchars((string)$crmTareasTotal, ENT_QUOTES, 'UTF-8') ?> tareas
                        </strong>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button type="button"
                            class="btn btn-outline-primary"
                            data-crm-proxy
                            data-solicitud-id="<?= htmlspecialchars((string)($solicitud['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-paciente-nombre="<?= htmlspecialchars($nombrePaciente ?: 'Solicitud', ENT_QUOTES, 'UTF-8') ?>"
                            aria-label="Abrir CRM de la solicitud">
                        <i class="mdi mdi-open-in-new"></i> Abrir CRM
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const callout = document.getElementById('prefacturaCalloutPreanestesia');
        const estadoSpan = document.getElementById('prefacturaEstadoActual');
        const btnAptoAnestesia = document.getElementById('btnPrefacturaConfirmarAnestesia');
        const btnAptoOftalmo = document.getElementById('btnPrefacturaConfirmarOftalmo');

        if (!callout || !estadoSpan) return;

        const postEstado = async ({id, formId, estado}) => {
            const basePath = (window.__KANBAN_MODULE__ && window.__KANBAN_MODULE__.basePath) || '/solicitudes';
            const url = `${basePath.replace(/\/+$/, '')}/actualizar-estado`;
            const payload = {
                id: Number.parseInt(id, 10),
                form_id: formId,
                estado,
                completado: true,
                force: true,
            };
            const res = await fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json;charset=UTF-8'},
                body: JSON.stringify(payload),
                credentials: 'include',
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data?.error || 'No se pudo actualizar el estado');
            }
            return data;
        };

        const actualizarUI = (nuevoEstado, tipo, resp = {}) => {
            const estadoLabel = resp.estado_label || nuevoEstado;
            estadoSpan.textContent = estadoLabel;
            const estadoNorm = (nuevoEstado || '').toString().trim().toLowerCase();
            // IMPORTANTE:
            // - "apto-anestesia" como estado de Kanban = estación pendiente de revisión.
            // - Solo consideramos "apto" (success) cuando la solicitud ya avanzó
            //   más allá de esa estación.
            const esAptoAnestesia = ['listo-para-agenda', 'programada', 'completado'].includes(estadoNorm);
            callout.classList.toggle('callout-success', esAptoAnestesia);
            callout.classList.toggle('callout-warning', !esAptoAnestesia);
            if (tipo === 'anestesia' && btnAptoAnestesia) {
                btnAptoAnestesia.classList.remove('btn-outline-success');
                btnAptoAnestesia.classList.add('btn-success');
                btnAptoAnestesia.textContent = 'Apto por anestesia';
                btnAptoAnestesia.disabled = true;
            }
            if (tipo === 'oftalmo' && btnAptoOftalmo) {
                btnAptoOftalmo.disabled = true;
            }

            const store = window.__solicitudesKanban;
            if (Array.isArray(store)) {
                const lookupId = btnAptoAnestesia?.dataset.id || btnAptoOftalmo?.dataset.id;
                const lookupForm = btnAptoAnestesia?.dataset.formId || btnAptoOftalmo?.dataset.formId;
                const item = store.find(
                    (s) => String(s.id) === String(lookupId) || String(s.form_id) === String(lookupForm)
                );
                if (item) {
                    item.estado = resp.estado || nuevoEstado;
                    item.estado_label = resp.estado_label || resp.estado || estadoLabel || nuevoEstado;
                    if (resp.checklist) item.checklist = resp.checklist;
                    if (resp.checklist_progress) item.checklist_progress = resp.checklist_progress;
                }
            }

            if (typeof window.aplicarFiltros === 'function') {
                try {
                    window.aplicarFiltros();
                } catch (e) { /* ignore */
                }
            } else {
                // Si no existe el refresco global, recarga la página para reflejar el checklist.
                setTimeout(() => window.location.reload(), 400);
            }
        };

        // Confirmar plan por oftalmólogo: solo marca checklist/estado apto-oftalmologo, no mueve a anestesia.
        if (btnAptoOftalmo) {
            btnAptoOftalmo.addEventListener('click', async () => {
                const id = btnAptoOftalmo.dataset.id;
                const formId = btnAptoOftalmo.dataset.formId;
                if (!id || !formId) return;
                btnAptoOftalmo.disabled = true;
                try {
                    const resp = await postEstado({id, formId, estado: 'apto-oftalmologo'});
                    // Actualiza store pero no cambia el callout (es pre-anestesia).
                    const store = window.__solicitudesKanban;
                    if (Array.isArray(store)) {
                        const item = store.find((s) => String(s.id) === String(id));
                        if (item) {
                            const estadoLabel = resp.estado_label || resp.estado || 'Apto oftalmólogo';
                            item.estado = resp.estado || 'apto-oftalmologo';
                            item.estado_label = estadoLabel;
                            if (resp.checklist) item.checklist = resp.checklist;
                            if (resp.checklist_progress) item.checklist_progress = resp.checklist_progress;
                        }
                    }
                    if (typeof window.aplicarFiltros === 'function') {
                        try {
                            window.aplicarFiltros();
                        } catch (e) {
                        }
                    } else {
                        setTimeout(() => window.location.reload(), 400);
                    }
                } catch (error) {
                    console.error('No se pudo marcar apto oftalmólogo:', error);
                    alert(error?.message || 'No se pudo marcar apto oftalmólogo.');
                    btnAptoOftalmo.disabled = false;
                }
            });
        }

        // Confirmar apto anestesia: marca checklist apto y deja el tablero en el siguiente paso pendiente.
        if (btnAptoAnestesia) {
            btnAptoAnestesia.addEventListener('click', async () => {
                const id = btnAptoAnestesia.dataset.id;
                const formId = btnAptoAnestesia.dataset.formId;
                if (!id || !formId) return;
                btnAptoAnestesia.disabled = true;
                try {
                    const resp = await postEstado({id, formId, estado: 'apto-anestesia'});
                    actualizarUI(resp.estado || 'apto-anestesia', 'anestesia', resp);
                } catch (error) {
                    console.error('No se pudo marcar apto anestesia:', error);
                    alert(error?.message || 'No se pudo marcar apto anestesia.');
                    btnAptoAnestesia.disabled = false;
                }
            });
        }
    });
</script>
