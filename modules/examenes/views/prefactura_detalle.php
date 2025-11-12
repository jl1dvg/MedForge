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
];
if ($diasTranscurridos !== null) {
    if ($diasTranscurridos <= 3) {
        $semaforo = ['color' => 'success', 'texto' => '🟢 Normal'];
    } elseif ($diasTranscurridos <= 7) {
        $semaforo = ['color' => 'warning', 'texto' => '🟡 Pendiente'];
    } else {
        $semaforo = ['color' => 'danger', 'texto' => '🔴 Urgente'];
    }
}

$vigenciaTexto = 'No disponible';
$vigenciaBadge = null;
if (!empty($derivacion['fecha_vigencia'])) {
    try {
        $vigencia = new DateTime($derivacion['fecha_vigencia']);
        $hoy = new DateTime();
        $intervalo = (int) $hoy->diff($vigencia)->format('%r%a');

        if ($intervalo >= 60) {
            $vigenciaBadge = ['color' => 'success', 'texto' => '🟢 Vigente'];
        } elseif ($intervalo >= 30) {
            $vigenciaBadge = ['color' => 'info', 'texto' => '🔵 Vigente'];
        } elseif ($intervalo >= 15) {
            $vigenciaBadge = ['color' => 'warning', 'texto' => '🟡 Por vencer'];
        } elseif ($intervalo >= 0) {
            $vigenciaBadge = ['color' => 'danger', 'texto' => '🔴 Urgente'];
        } else {
            $vigenciaBadge = ['color' => 'dark', 'texto' => '⚫ Vencida'];
        }

        $vigenciaTexto = "<strong>Días para caducar:</strong> {$intervalo} días";
    } catch (Exception $e) {
        $vigenciaTexto = 'No disponible';
    }
}
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<div class="alert alert-primary text-center fw-bold">
    🧑 Paciente: <?= htmlspecialchars($nombrePaciente ?: 'Sin nombre', ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($edad, ENT_QUOTES, 'UTF-8') ?>
</div>

<ul class="list-group mb-4 bg-light-subtle">
    <li class="list-group-item fw-bold text-center bg-light-subtle">
        <?php if ($fechaSolicitud): ?>
            🕒 Fecha de Solicitud: <?= htmlspecialchars($fechaSolicitud->format('d-m-Y'), ENT_QUOTES, 'UTF-8') ?><br>
            <small class="text-muted">(hace <?= (int) $diasTranscurridos ?> días)</small><br>
        <?php else: ?>
            <span class="text-muted">Fecha no disponible</span><br>
        <?php endif; ?>
        <span class="badge bg-<?= htmlspecialchars($semaforo['color'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($semaforo['texto'], ENT_QUOTES, 'UTF-8') ?>
        </span>
    </li>
</ul>

<ul class="list-group mb-4 bg-light-subtle">
    <li class="list-group-item">
        <strong>Formulario ID:</strong> <?= htmlspecialchars((string) ($solicitud['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
        <strong>HC #:</strong> <?= htmlspecialchars((string) ($solicitud['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
    </li>
</ul>

<h5 class="mt-4 border-bottom pb-1">📄 Datos del Paciente</h5>
<ul class="list-group mb-4 bg-light-subtle">
    <li class="list-group-item">
        <i class="bi bi-gender-ambiguous"></i> <strong>Sexo:</strong> <?= htmlspecialchars($paciente['sexo'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?><br>
        <i class="bi bi-shield-check"></i> <strong>Afiliación:</strong> <?= htmlspecialchars($paciente['afiliacion'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?><br>
        <i class="bi bi-cake-fill"></i> <strong>Fecha Nacimiento:</strong>
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
        ?><br>
        <i class="bi bi-phone"></i> <strong>Celular:</strong> <?= htmlspecialchars($paciente['celular'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?>
    </li>
</ul>

<div class="row g-3">
    <div class="col-12 col-md-6">
        <h5>🗂️ Información de la Solicitud</h5>
        <ul class="list-group mb-3">
            <li class="list-group-item"><strong>Afiliación:</strong> <?= htmlspecialchars($solicitud['afiliacion'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?></li>
            <li class="list-group-item"><strong>Procedimiento:</strong> <?= htmlspecialchars($solicitud['procedimiento'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?></li>
            <li class="list-group-item"><strong>Doctor:</strong> <?= htmlspecialchars($solicitud['doctor'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?></li>
            <li class="list-group-item"><strong>Duración:</strong> <?= htmlspecialchars($solicitud['duracion'] ?? '—', ENT_QUOTES, 'UTF-8') ?> minutos</li>
            <li class="list-group-item"><strong>Prioridad:</strong> <?= htmlspecialchars($solicitud['prioridad'] ?? '—', ENT_QUOTES, 'UTF-8') ?></li>
            <li class="list-group-item"><strong>Estado:</strong> <?= htmlspecialchars($solicitud['estado'] ?? '—', ENT_QUOTES, 'UTF-8') ?></li>
            <li class="list-group-item">
                <i class="bi bi-clipboard2-pulse"></i>
                <strong>Diagnósticos:</strong>
                <?php if ($diagnosticos): ?>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($diagnosticos as $dx): ?>
                            <li>
                                <span class="text-primary"><?= htmlspecialchars($dx['dx_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                — <?= htmlspecialchars($dx['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                (<?= htmlspecialchars($dx['lateralidad'] ?? '', ENT_QUOTES, 'UTF-8') ?>)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <span class="text-muted">No disponibles</span>
                <?php endif; ?>
            </li>
        </ul>
    </div>

    <div class="col-12 col-md-6">
        <h5>📌 Información de la Derivación</h5>
        <ul class="list-group mb-3">
            <li class="list-group-item"><i class="bi bi-upc-scan"></i> <strong>Código Derivación:</strong> <?= htmlspecialchars($derivacion['cod_derivacion'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?></li>
            <li class="list-group-item"><i class="bi bi-calendar-check"></i> <strong>Fecha Registro:</strong> <?= htmlspecialchars($derivacion['fecha_registro'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?></li>
            <li class="list-group-item"><i class="bi bi-calendar-event"></i> <strong>Fecha Vigencia:</strong> <?= htmlspecialchars($derivacion['fecha_vigencia'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?></li>
            <li class="list-group-item">
                <i class="bi bi-hourglass-split"></i> <?= $vigenciaTexto ?>
                <?php if ($vigenciaBadge): ?>
                    <span class="badge bg-<?= htmlspecialchars($vigenciaBadge['color'], ENT_QUOTES, 'UTF-8') ?> ms-2">
                        <?= htmlspecialchars($vigenciaBadge['texto'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php endif; ?>
            </li>
            <li class="list-group-item"><i class="bi bi-clipboard2-pulse"></i> <strong>Diagnóstico:</strong> <?= htmlspecialchars($derivacion['diagnostico'] ?? 'No disponible', ENT_QUOTES, 'UTF-8') ?></li>
        </ul>
    </div>
</div>
