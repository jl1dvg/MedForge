<?php
/** @var array<string, mixed> $visita */
$procedimientos = $visita['procedimientos'] ?? [];

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

$fechaVisita = $visita['fecha_visita'] ? date('d/m/Y', strtotime((string) $visita['fecha_visita'])) : '—';
$horaLlegada = $visita['hora_llegada'] ? date('H:i', strtotime((string) $visita['hora_llegada'])) : '—';
$nombrePaciente = $visita['paciente'] ?: 'Paciente sin nombre';
$hcNumber = $visita['hc_number'] ?? '—';
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
        </div>
    </div>
</section>
