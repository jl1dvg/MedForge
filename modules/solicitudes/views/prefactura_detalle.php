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
$derivacionVencida = false;
if (!empty($derivacion['fecha_vigencia'])) {
    try {
        $vigencia = new DateTime($derivacion['fecha_vigencia']);
        $hoy = new DateTime();
        $intervalo = (int)$hoy->diff($vigencia)->format('%r%a');
        $derivacionVencida = $intervalo < 0;

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
$defaultSlaBadges = [
        'en_rango' => ['color' => 'success', 'label' => 'SLA en rango', 'icon' => 'mdi-check-circle-outline'],
        'advertencia' => ['color' => 'warning', 'label' => 'SLA 72h', 'icon' => 'mdi-timer-sand'],
        'critico' => ['color' => 'danger', 'label' => 'SLA crítico', 'icon' => 'mdi-alert-octagon'],
        'vencido' => ['color' => 'dark', 'label' => 'SLA vencido', 'icon' => 'mdi-alert'],
        'sin_fecha' => ['color' => 'secondary', 'label' => 'SLA sin fecha', 'icon' => 'mdi-calendar-remove'],
        'cerrado' => ['color' => 'secondary', 'label' => 'SLA cerrado', 'icon' => 'mdi-lock-outline'],
];
$slaBadges = $defaultSlaBadges;
if (isset($slaLabels) && is_array($slaLabels) && $slaLabels !== []) {
    $slaBadges = array_replace($defaultSlaBadges, $slaLabels);
}
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
$hasDerivacion = !empty($derivacion['cod_derivacion'])
        || !empty($derivacion['derivacion_id'])
        || !empty($derivacion['id'])
        || !empty($derivacion['archivo_derivacion_path']);

$afiliacionSolicitud = trim((string)($solicitud['afiliacion'] ?? ''));
$normalizarAfiliacion = static function (string $value): string {
    $value = trim($value);
    $value = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'n', 'n'],
            $value
    );
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    return trim($value);
};
$afiliacionesCobertura = array_map($normalizarAfiliacion, [
        'contribuyente voluntario',
        'conyuge',
        'conyuge pensionista',
        'seguro campesino',
        'seguro general por montepio',
        'seguro general tiempo parcial',
        'iess',
        'hijos dependientes',
        'seguro campesino jubilado',
        'seguro general',
        'seguro general jubilado',
]);
$afiliacionNormalizada = $normalizarAfiliacion($afiliacionSolicitud);
$solicitudCoberturaMail = $derivacionVencida && in_array($afiliacionNormalizada, $afiliacionesCobertura, true);
$coberturaHcNumber = $solicitud['hc_number'] ?? $paciente['hc_number'] ?? '';
$coberturaFormId = $solicitud['form_id'] ?? $consulta['form_id'] ?? '';
$coberturaProcedimiento = $solicitud['procedimiento'] ?? '';
$coberturaPlan = $consulta['plan'] ?? '';
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
                aria-selected="false">Apto Quirúrgico
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
    <div id="prefacturaCoberturaData"
         class="d-none"
         data-derivacion-vencida="<?= $derivacionVencida ? '1' : '0' ?>"
         data-afiliacion="<?= htmlspecialchars($afiliacionSolicitud, ENT_QUOTES, 'UTF-8') ?>"
         data-nombre="<?= htmlspecialchars($nombrePaciente !== '' ? $nombrePaciente : 'Paciente', ENT_QUOTES, 'UTF-8') ?>"
         data-hc="<?= htmlspecialchars((string)$coberturaHcNumber, ENT_QUOTES, 'UTF-8') ?>"
         data-procedimiento="<?= htmlspecialchars((string)$coberturaProcedimiento, ENT_QUOTES, 'UTF-8') ?>"
         data-plan="<?= htmlspecialchars((string)$coberturaPlan, ENT_QUOTES, 'UTF-8') ?>"
         data-form-id="<?= htmlspecialchars((string)$coberturaFormId, ENT_QUOTES, 'UTF-8') ?>"
         data-derivacion-pdf="<?= htmlspecialchars((string)($archivoHref ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
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

        <div class="card border-0 shadow-sm mt-3" id="prefacturaChecklistCard"
             data-solicitud-id="<?= htmlspecialchars((string)($solicitud['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
             data-form-id="<?= htmlspecialchars((string)($solicitud['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
             data-hc-number="<?= htmlspecialchars((string)($solicitud['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <div class="card-header bg-white d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <h6 class="card-title mb-0">
                    <i class="bi bi-check2-square prefactura-icon text-primary me-2"></i>
                    Checklist / Tareas
                </h6>
                <a id="prefacturaChecklistCrmProject"
                   class="btn btn-outline-primary btn-sm d-none"
                   href="#"
                   target="_blank"
                   rel="noopener"
                   aria-label="Abrir proyecto CRM">
                    <i class="mdi mdi-open-in-new"></i> Abrir Proyecto CRM
                </a>
                <button type="button"
                        class="btn btn-outline-secondary btn-sm d-none"
                        id="prefacturaChecklistBootstrap">
                    <i class="mdi mdi-playlist-plus me-1"></i> Crear tareas
                </button>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <div class="text-muted small">Progreso</div>
                    <div class="fw-semibold" id="prefacturaChecklistProgress">—</div>
                </div>
                <div class="progress mb-3" style="height: 6px;">
                    <div class="progress-bar bg-primary" role="progressbar" id="prefacturaChecklistProgressBar"
                         style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="list-group list-group-flush" id="prefacturaChecklistList"></div>
                <div class="text-muted small d-none" id="prefacturaChecklistEmpty">Sin checklist disponible.</div>
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

                <?php if ($solicitudCoberturaMail): ?>
                    <div class="alert alert-warning border d-flex flex-column gap-2 mb-0">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-envelope-exclamation"></i>
                            <div>
                                <div class="fw-semibold">Derivación vencida</div>
                                <small class="text-muted">
                                    Afiliación: <?= htmlspecialchars($afiliacionSolicitud, ENT_QUOTES, 'UTF-8') ?>.
                                    Solicita un nuevo código por correo adjuntando la derivación.
                                </small>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-warning btn-sm"
                                    id="btnPrefacturaSolicitarCoberturaMail">
                                <i class="bi bi-envelope-fill me-1"></i> Solicitar cobertura por correo
                            </button>
                            <?php if ($archivoHref): ?>
                                <a class="btn btn-outline-secondary btn-sm"
                                   href="<?= htmlspecialchars($archivoHref, ENT_QUOTES, 'UTF-8') ?>"
                                   target="_blank" rel="noopener">
                                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Descargar derivación
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

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
        <div id="prefacturaDerivacionContent">
            <?php if (!$hasDerivacion): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body d-flex flex-column gap-2">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge bg-secondary">Sin derivación</span>
                            <span class="text-muted">Seguro particular: requiere autorización.</span>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnSolicitarAutorizacion">
                            Solicitar autorización
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!empty($archivoHref) || !empty($derivacion['derivacion_id']) || !empty($derivacion['id'])): ?>
                    <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap">
                        <div>
                            <strong>📎 Derivación:</strong>
                            <span class="text-muted ms-1">Documento adjunto disponible.</span>
                        </div>
                        <a class="btn btn-sm btn-outline-primary mt-2 mt-md-0"
                           href="<?= htmlspecialchars($archivoHref, ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                           rel="noopener">
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
            <?php endif; ?>
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
                <div class="card border-0 shadow-sm">
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
                        <button
                                class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
                                type="button"
                                id="btnPrefacturaEditarLio"
                                data-context-action="editar-lio"
                                data-id="<?= htmlspecialchars((string)$solicitudIdRaw, ENT_QUOTES, 'UTF-8') ?>"
                                data-form-id="<?= htmlspecialchars((string)($solicitud['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-hc="<?= htmlspecialchars((string)($solicitud['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-bs-toggle="tooltip"
                                title="Editar LIO, poder, lateralidad, incisión y médico asignado"
                        >
                            <i class="mdi mdi-tune-variant"></i>
                            <span>Editar cirugía</span>
                            <span class="badge bg-light text-primary">LIO</span>
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
                                    <?= $esAptoAnestesia
                                            ? '<i class="mdi mdi-check-circle-outline me-1"></i> Apto por anestesia'
                                            : 'Marcar apto'
                                    ?>
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
                                <button class="btn btn-sm <?= $esAptoOftalmo ? 'btn-success' : 'btn-outline-success' ?>"
                                        type="button"
                                        id="btnPrefacturaConfirmarOftalmo"
                                        data-context-action="confirmar-oftalmo"
                                        data-id="<?= htmlspecialchars((string)$solicitudIdBtn, ENT_QUOTES, 'UTF-8') ?>"
                                        data-form-id="<?= htmlspecialchars((string)$formId, ENT_QUOTES, 'UTF-8') ?>"
                                        data-hc="<?= htmlspecialchars((string)($solicitud['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $esAptoOftalmo ? 'disabled' : '' ?>>
                                    <?= $esAptoOftalmo ? '<i class="mdi mdi-check-circle-outline me-1"></i> Apto por oftalmólogo'
                                            : 'Marcar apto' ?>
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
        <!-- TAB 5: Nota clínica (Examen & Plan) -->
        <?php
        // Normaliza textos clínicos: elimina sangrías comunes, recorta espacios por línea y reduce saltos excesivos.
        $normalizarTextoClinico = static function (?string $text): string {
            $text = (string)($text ?? '');

            // Normaliza saltos de línea
            $text = str_replace(["\r\n", "\r"], "\n", $text);

            // Trim general (sin perder estructura interna)
            $text = trim($text);

            if ($text === '') {
                return '';
            }

            $lines = explode("\n", $text);

            // Calcula la indentación mínima común (solo líneas no vacías)
            $minIndent = null;
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }
                preg_match('/^[ \t]*/', $line, $m);
                $indentLen = strlen($m[0]);
                $minIndent = ($minIndent === null) ? $indentLen : min($minIndent, $indentLen);
            }

            if ($minIndent === null) {
                $minIndent = 0;
            }

            // Aplica: quita indentación común y limpia espacios al final; mantiene tabulación interna
            $out = [];
            foreach ($lines as $line) {
                if ($minIndent > 0) {
                    $line = preg_replace('/^[ \t]{0,' . $minIndent . '}/', '', $line);
                }
                $out[] = rtrim($line);
            }

            $text = implode("\n", $out);

            // Reduce múltiples líneas vacías (3+ -> 2)
            $text = preg_replace("/\n{3,}/", "\n\n", $text);

            return $text;
        };

        $examenFisicoTexto = $normalizarTextoClinico($consulta['examen_fisico'] ?? '');
        $planTexto = $normalizarTextoClinico($consulta['plan'] ?? '');
        ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white prefactura-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-journal-medical prefactura-icon text-primary"></i>
                    <div>
                        <h6 class="prefactura-card-title mb-0">Nota clínica</h6>
                        <div class="fw-semibold">Examen físico y plan</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPrefacturaCopyExamen">
                        <i class="bi bi-clipboard"></i> Copiar examen
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPrefacturaCopyPlan">
                        <i class="bi bi-clipboard"></i> Copiar plan
                    </button>
                </div>
            </div>

            <div class="card-body">
                <ul class="nav nav-pills gap-2" id="prefacturaNotaClinicaTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="prefacturaNotaExamenTab" data-bs-toggle="tab"
                                data-bs-target="#prefacturaNotaExamen" type="button" role="tab"
                                aria-controls="prefacturaNotaExamen" aria-selected="true">
                            <i class="bi bi-eye me-1"></i> Examen físico
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="prefacturaNotaPlanTab" data-bs-toggle="tab"
                                data-bs-target="#prefacturaNotaPlan" type="button" role="tab"
                                aria-controls="prefacturaNotaPlan" aria-selected="false">
                            <i class="bi bi-clipboard2-check me-1"></i> Plan
                        </button>
                    </li>
                </ul>

                <div class="tab-content mt-3" id="prefacturaNotaClinicaTabsContent">
                    <div class="tab-pane fade show active" id="prefacturaNotaExamen" role="tabpanel"
                         aria-labelledby="prefacturaNotaExamenTab" tabindex="0">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <div class="prefactura-meta-label">Examen físico</div>
                            <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1">
                                <i class="bi bi-text-paragraph"></i>
                                <span class="small">Texto libre</span>
                            </span>
                        </div>
                        <div class="border rounded-3 bg-light p-3"
                             style="white-space: pre-wrap; max-height: 380px; overflow:auto;">
                            <?= htmlspecialchars($examenFisicoTexto !== '' ? $examenFisicoTexto : 'No disponible', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="prefacturaNotaPlan" role="tabpanel"
                         aria-labelledby="prefacturaNotaPlanTab" tabindex="0">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <div class="prefactura-meta-label">Plan</div>
                            <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1">
                                <i class="bi bi-list-check"></i>
                                <span class="small">Indicaciones</span>
                            </span>
                        </div>
                        <div class="border rounded-3 bg-light p-3"
                             style="white-space: pre-wrap; max-height: 380px; overflow:auto;">
                            <?= htmlspecialchars($planTexto !== '' ? $planTexto : 'No disponible', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </div>

                <small class="text-muted d-block mt-3">
                    Sugerencia: usa “Copiar” para pegar rápidamente en evoluciones, consentimientos o notas internas.
                </small>
            </div>
        </div>

        <script>
            (function () {
                const copyText = async (text) => {
                    try {
                        await navigator.clipboard.writeText(text);
                        return true;
                    } catch (e) {
                        // Fallback para navegadores sin permisos
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.left = '-9999px';
                        document.body.appendChild(ta);
                        ta.focus();
                        ta.select();
                        const ok = document.execCommand('copy');
                        document.body.removeChild(ta);
                        return ok;
                    }
                };

                const btnExamen = document.getElementById('btnPrefacturaCopyExamen');
                const btnPlan = document.getElementById('btnPrefacturaCopyPlan');

                if (btnExamen) {
                    btnExamen.addEventListener('click', async () => {
                        const text = <?= json_encode((string)($examenFisicoTexto ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                        const ok = await copyText(text || '');
                        btnExamen.classList.toggle('btn-outline-secondary', !ok);
                        btnExamen.classList.toggle('btn-success', ok);
                        btnExamen.innerHTML = ok
                            ? '<i class="bi bi-check2"></i> Copiado'
                            : '<i class="bi bi-exclamation-triangle"></i> No se pudo copiar';
                        setTimeout(() => {
                            btnExamen.classList.remove('btn-success');
                            btnExamen.classList.add('btn-outline-secondary');
                            btnExamen.innerHTML = '<i class="bi bi-clipboard"></i> Copiar examen';
                        }, 1200);
                    });
                }

                if (btnPlan) {
                    btnPlan.addEventListener('click', async () => {
                        const text = <?= json_encode((string)($planTexto ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                        const ok = await copyText(text || '');
                        btnPlan.classList.toggle('btn-outline-secondary', !ok);
                        btnPlan.classList.toggle('btn-success', ok);
                        btnPlan.innerHTML = ok
                            ? '<i class="bi bi-check2"></i> Copiado'
                            : '<i class="bi bi-exclamation-triangle"></i> No se pudo copiar';
                        setTimeout(() => {
                            btnPlan.classList.remove('btn-success');
                            btnPlan.classList.add('btn-outline-secondary');
                            btnPlan.innerHTML = '<i class="bi bi-clipboard"></i> Copiar plan';
                        }, 1200);
                    });
                }
            })();
        </script>
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

<div class="modal fade" id="coberturaMailModal" tabindex="-1" aria-labelledby="coberturaMailModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="coberturaMailModalLabel">Solicitar cobertura por correo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form data-cobertura-mail-form>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="coberturaMailTo">Para</label>
                            <input type="text" class="form-control" id="coberturaMailTo" name="to"
                                   data-cobertura-mail-to placeholder="correo1@cive.ec, correo2@cive.ec">
                            <small class="text-muted">Separa múltiples correos con coma o punto y coma.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="coberturaMailCc">CC</label>
                            <input type="text" class="form-control" id="coberturaMailCc" name="cc"
                                   data-cobertura-mail-cc placeholder="correo@cive.ec">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="coberturaMailSubject">Asunto</label>
                            <input type="text" class="form-control" id="coberturaMailSubject" name="subject"
                                   data-cobertura-mail-subject required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="coberturaMailBody">Mensaje</label>
                            <textarea class="form-control" id="coberturaMailBody" rows="8" name="body"
                                      data-cobertura-mail-body required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="coberturaMailAttachment">Adjuntar archivo</label>
                            <input type="file" class="form-control" id="coberturaMailAttachment" name="attachment"
                                   data-cobertura-mail-attachment accept="application/pdf">
                        </div>
                        <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                            <a class="btn btn-outline-secondary btn-sm d-none" data-cobertura-mail-pdf
                               target="_blank" rel="noopener">
                                <i class="mdi mdi-file-pdf-box"></i> Ver PDF de derivación
                            </a>
                            <small class="text-muted">Adjunta el PDF de la derivación antes de enviar.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success" data-cobertura-mail-send>
                        <i class="mdi mdi-send"></i> Enviar correo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset('assets/vendor_components/ckeditor/ckeditor.js') ?>"></script>

<script>
    (() => {
        const card = document.getElementById('prefacturaChecklistCard');
        if (!card) return;

        const solicitudId = card.dataset.solicitudId;
        const formId = card.dataset.formId;
        const hcNumber = card.dataset.hcNumber;
        const list = document.getElementById('prefacturaChecklistList');
        const empty = document.getElementById('prefacturaChecklistEmpty');
        const progressLabel = document.getElementById('prefacturaChecklistProgress');
        const progressBar = document.getElementById('prefacturaChecklistProgressBar');
        const crmProjectButton = document.getElementById('prefacturaChecklistCrmProject');
        const bootstrapButton = document.getElementById('prefacturaChecklistBootstrap');

        const basePath = (window.__KANBAN_MODULE__ && window.__KANBAN_MODULE__.basePath) || '/solicitudes';
        const buildUrl = (suffix) => `${basePath.replace(/\/+$/, '')}/${solicitudId}${suffix}`;

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const setCrmProjectButton = (projectId) => {
            if (!crmProjectButton) return;
            const normalized = Number(projectId || 0);
            if (normalized > 0) {
                crmProjectButton.href = `/crm?tab=projects&project_id=${normalized}`;
                crmProjectButton.classList.remove('d-none');
            } else {
                crmProjectButton.classList.add('d-none');
                crmProjectButton.href = '#';
            }
        };

        const setProgress = (progress = {}) => {
            const total = Number(progress.total || 0);
            const completed = Number(progress.completed || 0);
            const percent = Number(progress.percent || (total ? Math.round((completed / total) * 100) : 0));
            progressLabel.textContent = total ? `${completed}/${total} (${percent}%)` : '—';
            progressBar.style.width = `${percent}%`;
            progressBar.setAttribute('aria-valuenow', percent.toString());
        };

        let tasksByKey = {};
        let tasksBySlug = {};

        const setBootstrapButton = (checklist = []) => {
            if (!bootstrapButton) return;
            const tasksCount = Object.keys(tasksByKey).length;
            if (tasksCount === 0 && Array.isArray(checklist) && checklist.length > 0) {
                bootstrapButton.classList.remove('d-none');
            } else {
                bootstrapButton.classList.add('d-none');
            }
        };

        const updateResumenFromChecklist = (data = {}) => {
            const openEl = document.getElementById('prefacturaStateTasksOpen');
            const totalEl = document.getElementById('prefacturaStateTasksTotal');
            const dueEl = document.getElementById('prefacturaStateNextDue');
            if (!openEl || !totalEl) return;

            const tasks = Array.isArray(data.tasks) ? data.tasks : [];
            let total = Number(data?.checklist_progress?.total ?? data?.checklist?.length ?? 0);
            let completed = Number(data?.checklist_progress?.completed ?? 0);
            let open = Math.max(total - completed, 0);

            if (tasks.length > 0) {
                const openTasks = tasks.filter((task) => (task?.status ?? '') !== 'completada').length;
                total = tasks.length;
                open = openTasks;
            }

            if (dueEl) {
                const openDueDates = tasks
                    .filter((task) => (task?.status ?? '') !== 'completada')
                    .map((task) => task?.due_at || task?.due_date)
                    .filter((value) => value);
                if (openDueDates.length > 0) {
                    const sorted = openDueDates.slice().sort();
                    dueEl.textContent = sorted[0];
                } else {
                    dueEl.textContent = 'Sin vencimiento';
                }
            }

            openEl.textContent = String(open);
            totalEl.textContent = String(total);
        };

        const normalizeTasks = (tasks = []) => {
            const map = {};
            if (!Array.isArray(tasks)) return map;
            tasks.forEach((task) => {
                if (!task || !task.task_key) return;
                map[task.task_key] = task;
            });
            return map;
        };

        const renderChecklist = (checklist = [], progress = {}) => {
            list.innerHTML = '';
            if (!Array.isArray(checklist) || checklist.length === 0) {
                empty.classList.remove('d-none');
                setProgress(progress);
                setBootstrapButton(checklist);
                return;
            }

            empty.classList.add('d-none');
            setProgress(progress);
            setBootstrapButton(checklist);

            checklist.forEach((item) => {
                const row = document.createElement('div');
                row.className = 'list-group-item d-flex align-items-center justify-content-between gap-2';
                const disabled = item.can_toggle ? '' : 'disabled';
                const checked = item.completed ? 'checked' : '';
                const label = escapeHtml(item.label || item.slug || '');
                const slug = escapeHtml(item.slug || '');
                const task = tasksBySlug[item.slug || ''];
                const taskInfo = task
                    ? `<small class="text-muted">CRM #${escapeHtml(task.id)} · ${escapeHtml(task.status || '')}</small>`
                    : '';
                row.innerHTML = `
                    <div class="d-flex flex-column">
                        <label class="d-flex align-items-center gap-2 mb-0">
                            <input type="checkbox" class="form-check-input m-0" data-checklist-toggle
                                   data-etapa-slug="${slug}" ${checked} ${disabled}>
                            <span>${label}</span>
                        </label>
                        ${taskInfo}
                    </div>
                    <span class="badge ${item.completed ? 'bg-success' : 'bg-light text-dark border'}">
                        ${item.completed ? 'Listo' : 'Pendiente'}
                    </span>
                `;
                list.appendChild(row);
            });

            list.querySelectorAll('[data-checklist-toggle]').forEach((input) => {
                input.addEventListener('change', async (event) => {
                    const checkbox = event.currentTarget;
                    const etapaSlug = checkbox.dataset.etapaSlug;
                    checkbox.disabled = true;
                    try {
                        const res = await fetch(buildUrl('/crm/checklist'), {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json;charset=UTF-8'},
                            body: JSON.stringify({
                                etapa_slug: etapaSlug,
                                completado: checkbox.checked,
                            }),
                            credentials: 'include',
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) {
                            throw new Error(data?.error || 'No se pudo sincronizar la tarea');
                        }

                        tasksByKey = normalizeTasks(data.tasks || []);
                        tasksBySlug = {};
                        if (Array.isArray(data.tasks)) {
                            data.tasks.forEach((task) => {
                                const slug = task?.checklist_slug;
                                if (slug) {
                                    tasksBySlug[slug] = task;
                                }
                            });
                        }
                        renderChecklist(data.checklist || checklist, data.checklist_progress || progress);
                        updateResumenFromChecklist(data);
                        if (Object.prototype.hasOwnProperty.call(data, 'project_id')) {
                            setCrmProjectButton(data.project_id);
                        }
                        setBootstrapButton(data.checklist || checklist);

                        const store = window.__solicitudesKanban;
                        if (Array.isArray(store)) {
                            const item = store.find(
                                (s) => String(s.id) === String(solicitudId) || String(s.form_id) === String(formId)
                            );
                            if (item) {
                                if (data.checklist) item.checklist = data.checklist;
                                if (data.checklist_progress) item.checklist_progress = data.checklist_progress;
                            }
                        }

                        if (typeof window.aplicarFiltros === 'function') {
                            try {
                                window.aplicarFiltros();
                            } catch (e) {
                            }
                        }
                    } catch (error) {
                        console.error('No se pudo sincronizar checklist:', error);
                        alert(error?.message || 'No se pudo sincronizar la tarea.');
                        checkbox.checked = !checkbox.checked;
                    } finally {
                        checkbox.disabled = false;
                    }
                });
            });
        };

        const loadChecklistState = async () => {
            if (!solicitudId) return;
            empty.textContent = 'Cargando checklist...';
            empty.classList.remove('d-none');
            try {
                const res = await fetch(buildUrl('/crm/checklist-state'), {
                    method: 'GET',
                    credentials: 'include',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data?.error || 'No se pudo cargar el checklist');
                }
                tasksByKey = normalizeTasks(data.tasks || []);
                tasksBySlug = {};
                if (Array.isArray(data.tasks)) {
                    data.tasks.forEach((task) => {
                        const slug = task?.checklist_slug;
                        if (slug) {
                            tasksBySlug[slug] = task;
                        }
                    });
                }
                renderChecklist(data.checklist || [], data.checklist_progress || {});
                updateResumenFromChecklist(data);
                setCrmProjectButton(data.project_id);
                setBootstrapButton(data.checklist || []);
            } catch (error) {
                console.error('No se pudo cargar checklist:', error);
                empty.textContent = error?.message || 'No se pudo cargar el checklist.';
                empty.classList.remove('d-none');
            }
        };

        const bootstrapTasks = async () => {
            if (!solicitudId) return;
            if (!bootstrapButton) return;
            bootstrapButton.disabled = true;
            try {
                const res = await fetch(buildUrl('/crm/bootstrap'), {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json;charset=UTF-8'},
                    body: JSON.stringify({
                        form_id: formId,
                        hc_number: hcNumber,
                    }),
                    credentials: 'include',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    throw new Error(data?.error || 'No se pudo crear las tareas');
                }
                tasksByKey = normalizeTasks(data.tasks || []);
                tasksBySlug = {};
                if (Array.isArray(data.tasks)) {
                    data.tasks.forEach((task) => {
                        const slug = task?.checklist_slug;
                        if (slug) {
                            tasksBySlug[slug] = task;
                        }
                    });
                }
                renderChecklist(data.checklist || [], data.checklist_progress || {});
                updateResumenFromChecklist(data);
                setCrmProjectButton(data.project_id);
                setBootstrapButton(data.checklist || []);
            } catch (error) {
                console.error('No se pudo crear tareas:', error);
                alert(error?.message || 'No se pudo crear las tareas.');
            } finally {
                bootstrapButton.disabled = false;
            }
        };

        if (bootstrapButton) {
            bootstrapButton.addEventListener('click', () => {
                bootstrapTasks();
            });
        }

        loadChecklistState();
    })();
</script>

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
