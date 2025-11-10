<?php
/** @var array<string, mixed> $visita */
$procedimientos = $visita['procedimientos'] ?? [];
$identityVerification = $identityVerification ?? ['summary' => null, 'requires_checkin' => true, 'validity_days' => null];
$verificationSummary = $identityVerification['summary'] ?? null;
$requiresBiometricCheckin = (bool) ($identityVerification['requires_checkin'] ?? true);
$verificationStatus = strtolower((string) ($verificationSummary['status'] ?? 'sin_registro'));
$verificationBadge = match ($verificationStatus) {
    'verified' => 'badge bg-success',
    'expired' => 'badge bg-danger',
    'revoked' => 'badge bg-danger',
    'pending' => 'badge bg-warning text-dark',
    default => 'badge bg-secondary',
};
$verificationLabel = match ($verificationStatus) {
    'verified' => 'Verificada',
    'expired' => 'Vencida',
    'revoked' => 'Revocada',
    'pending' => 'Pendiente',
    default => 'Sin certificación',
};
$lastVerificationAt = $verificationSummary['last_verification_at'] ?? null;
$lastVerificationResult = $verificationSummary['last_verification_result'] ?? null;
$expiredAt = $verificationSummary['expired_at'] ?? null;

if (!function_exists('agenda_badge_class')) {
    function agenda_badge_class(?string $estado): string
    {
        $estado = strtoupper(trim((string) $estado));
        return match ($estado) {
            'AGENDADO', 'PROGRAMADO' => 'badge bg-primary-light text-primary',
            'LLEGADO', 'EN CURSO' => 'badge bg-success-light text-success',
            'ATENDIDO', 'COMPLETADO' => 'badge bg-success text-white',
            'CANCELADO' => 'badge bg-danger-light text-danger',
            'NO LLEGO', 'NO LLEGÓ', 'NO_ASISTIO', 'NO ASISTIO' => 'badge bg-warning-light text-warning',
            default => 'badge bg-secondary',
        };
    }
}

if (!function_exists('agenda_cobertura_badge')) {
    function agenda_cobertura_badge(?string $estado): string
    {
        return match ($estado) {
            'Con Cobertura' => 'badge bg-success',
            'Sin Cobertura' => 'badge bg-danger',
            default => 'badge bg-secondary',
        };
    }
}

$fechaVisita = $visita['fecha_visita'] ? date('d/m/Y', strtotime((string) $visita['fecha_visita'])) : '—';
$horaLlegada = $visita['hora_llegada'] ? date('H:i', strtotime((string) $visita['hora_llegada'])) : '—';
$nombrePaciente = $visita['paciente'] ?: 'Paciente sin nombre';
$hcNumber = $visita['hc_number'] ?? '—';
$hcNumberRaw = (string) ($visita['hc_number'] ?? '');
$pacienteContexto = $visita['paciente_contexto'] ?? [];
$estadoCobertura = $pacienteContexto['coverageStatus'] ?? ($visita['estado_cobertura'] ?? 'N/A');
$timelineResumen = array_slice($pacienteContexto['timelineItems'] ?? [], 0, 5);
?>

<section class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Encuentro #<?= htmlspecialchars((string) $visita['id']) ?></h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/agenda">Agenda</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Encuentro</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="ms-auto">
            <a class="btn btn-outline-primary" href="/pacientes/detalles?hc_number=<?= urlencode((string) $hcNumber) ?>">
                <i class="mdi mdi-account"></i> Ver ficha del paciente
            </a>
        </div>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-lg-5">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Datos del encuentro</h4>
                </div>
                <div class="box-body">
                    <?php
                    $verificationUrl = $hcNumberRaw !== ''
                        ? '/pacientes/certificaciones?patient_id=' . urlencode($hcNumberRaw)
                        : '/pacientes/certificaciones';
                    $validityDays = $identityVerification['validity_days'] ?? null;
                    ?>
                    <?php if ($hcNumberRaw === ''): ?>
                        <div class="alert alert-info">
                            <strong>Historia clínica no asignada.</strong> Vincule el encuentro con un paciente para habilitar la certificación biométrica.
                        </div>
                    <?php elseif ($verificationSummary === null): ?>
                        <div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <strong>Certificación biométrica pendiente.</strong>
                                Debe registrar firma y rostro del paciente antes de continuar con la atención.
                            </div>
                            <a class="btn btn-sm btn-primary" href="<?= $verificationUrl ?>">
                                <i class="mdi mdi-face-recognition"></i> Abrir módulo de certificación
                            </a>
                        </div>
                    <?php else: ?>
                        <?php if ($requiresBiometricCheckin): ?>
                            <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div>
                                    <strong>Revisar certificación biométrica.</strong>
                                    <span class="<?= $verificationBadge ?> ms-2">Estado: <?= htmlspecialchars($verificationLabel) ?></span>
                                    <?php if ($expiredAt): ?>
                                        <div class="small text-muted">Vencida desde <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $expiredAt))) ?>.</div>
                                    <?php endif; ?>
                                    <?php if ($lastVerificationAt): ?>
                                        <div class="small text-muted">Última verificación: <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $lastVerificationAt))) ?> · Resultado: <?= htmlspecialchars((string) ($lastVerificationResult ?? 'N/A')) ?></div>
                                    <?php endif; ?>
                                    <?php if ($validityDays): ?>
                                        <div class="small text-muted">Vigencia configurada: <?= (int) $validityDays ?> días.</div>
                                    <?php endif; ?>
                                </div>
                                <a class="btn btn-sm btn-outline-primary" href="<?= $verificationUrl ?>">
                                    <i class="mdi mdi-face-recognition"></i> Actualizar datos biométricos
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <div>
                                    <strong>Certificación biométrica vigente.</strong>
                                    <span class="<?= $verificationBadge ?> ms-2">Estado: <?= htmlspecialchars($verificationLabel) ?></span>
                                    <?php if ($lastVerificationAt): ?>
                                        <div class="small text-muted">Última verificación: <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $lastVerificationAt))) ?> · Resultado: <?= htmlspecialchars((string) ($lastVerificationResult ?? 'N/A')) ?></div>
                                    <?php endif; ?>
                                </div>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= $verificationUrl ?>">
                                    <i class="mdi mdi-file-document"></i> Ver detalle de certificación
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Paciente</dt>
                        <dd class="col-sm-7 fw-600"><?= htmlspecialchars($nombrePaciente) ?></dd>

                        <dt class="col-sm-5">Historia clínica</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars((string) $hcNumber) ?></dd>

                        <dt class="col-sm-5">Afiliación</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars((string) ($visita['afiliacion'] ?? '—')) ?></dd>

                        <dt class="col-sm-5">Fecha de visita</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($fechaVisita) ?></dd>

                        <dt class="col-sm-5">Hora de llegada</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($horaLlegada) ?></dd>

                        <dt class="col-sm-5">Usuario que registró</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars((string) ($visita['usuario_registro'] ?? '—')) ?></dd>

                        <dt class="col-sm-5">Contacto</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars((string) ($visita['celular'] ?? '—')) ?></dd>

                        <dt class="col-sm-5">Estado de cobertura</dt>
                        <dd class="col-sm-7">
                            <span class="<?= agenda_cobertura_badge($estadoCobertura) ?>">
                                <?= htmlspecialchars((string) $estadoCobertura) ?>
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="box">
                <div class="box-header with-border d-flex align-items-center justify-content-between">
                    <h4 class="box-title mb-0">Procedimientos asociados</h4>
                    <span class="badge bg-primary"><?= count($procedimientos) ?> procedimientos</span>
                </div>
                <div class="box-body p-0">
                    <?php if ($procedimientos): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead class="bg-primary-light">
                                <tr>
                                    <th>Form ID</th>
                                    <th>Procedimiento</th>
                                    <th>Doctor</th>
                                    <th>Horario</th>
                                    <th>Estado actual</th>
                                    <th>Historial</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($procedimientos as $procedimiento): ?>
                                    <?php
                                    $estado = $procedimiento['estado_agenda'] ?? 'Sin estado';
                                    $hora = $procedimiento['hora_agenda'] ?? '—';
                                    $historial = $procedimiento['historial_estados'] ?? [];
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-info-light text-primary fw-600">
                                                <?= htmlspecialchars((string) $procedimiento['form_id']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-600 text-dark">
                                                <?= htmlspecialchars((string) ($procedimiento['procedimiento'] ?? '—')) ?>
                                            </div>
                                            <div class="text-muted small">
                                                <?= htmlspecialchars((string) ($procedimiento['sede_departamento'] ?? ($procedimiento['id_sede'] ?? '—'))) ?>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars((string) ($procedimiento['doctor'] ?? '—')) ?></td>
                                        <td><?= htmlspecialchars($hora) ?></td>
                                        <td>
                                            <span class="<?= agenda_badge_class($estado) ?>">
                                                <?= htmlspecialchars($estado) ?>
                                            </span>
                                        </td>
                                        <td style="min-width: 220px;">
                                            <?php if ($historial): ?>
                                                <ul class="list-unstyled mb-0 small">
                                                    <?php foreach ($historial as $evento): ?>
                                                        <li>
                                                            <span class="text-muted">
                                                                <?= htmlspecialchars(date('d/m H:i', strtotime((string) $evento['fecha_hora_cambio']))) ?>
                                                            </span>
                                                            <span class="ms-1 fw-500">
                                                                <?= htmlspecialchars((string) $evento['estado']) ?>
                                                            </span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <span class="text-muted">Sin registros</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            No se registraron procedimientos para este encuentro.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($timelineResumen): ?>
                <div class="box mt-4">
                    <div class="box-header with-border d-flex align-items-center justify-content-between">
                        <h4 class="box-title mb-0">Últimos movimientos del paciente</h4>
                        <span class="badge bg-secondary-light text-secondary">
                            <?= count($timelineResumen) ?> registros recientes
                        </span>
                    </div>
                    <div class="box-body">
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($timelineResumen as $item): ?>
                                <?php
                                $fechaItem = isset($item['fecha']) && $item['fecha'] !== ''
                                    ? date('d/m/Y H:i', strtotime((string) $item['fecha']))
                                    : 'Fecha no disponible';
                                $tipoItem = strtoupper((string) ($item['tipo'] ?? $item['origen'] ?? '')); 
                                $nombreItem = $item['nombre'] ?? $item['procedimiento'] ?? 'Movimiento';
                                ?>
                                <li class="mb-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-600 text-dark">
                                                <?= htmlspecialchars((string) $nombreItem) ?>
                                            </div>
                                            <div class="text-muted small">
                                                <?= htmlspecialchars($fechaItem) ?>
                                            </div>
                                        </div>
                                        <?php if ($tipoItem !== ''): ?>
                                            <span class="badge bg-primary-light text-primary ms-3">
                                                <?= htmlspecialchars($tipoItem) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
