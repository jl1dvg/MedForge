<?php
echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">';
$derivacion = $data['derivacion'] ?? null;
$solicitud = $data['solicitud'] ?? null;
$consulta = $data['consulta'] ?? null;
if (!empty($solicitud) && is_array($solicitud)):
    $paciente = $data['paciente'] ?? [];
    $nombre_paciente = trim(($paciente['fname'] ?? '') . ' ' . ($paciente['mname'] ?? '') . ' ' . ($paciente['lname'] ?? '') . ' ' . ($paciente['lname2'] ?? ''));
    $fecha_nacimiento = $paciente['fecha_nacimiento'] ?? null;
    $edad = 'No disponible';
    if ($fecha_nacimiento) {
        try {
            $birthdate = new DateTime($fecha_nacimiento);
            $edad = $birthdate->diff(new DateTime())->y . ' años';
        } catch (Exception $e) {
            $edad = 'No disponible';
        }
    }
    echo "<pre>";
    //print_r($data);
    echo "</pre>";
    $solicitud_valor = $consulta['fecha'] ?? null;
    $solicitud_dt = new DateTime($solicitud_valor);
    if (!is_array($derivacion)) {
        $derivacion = [];
    }
    $hoy = new DateTime();
    $dias_transcurridos = $solicitud_dt->diff($hoy)->days;
    $fecha_formateada = $solicitud_dt->format('d-m-Y');

    // Semaforización
    if ($dias_transcurridos <= 3) {
        $color = 'success';
        $texto = '🟢 Normal';
    } elseif ($dias_transcurridos <= 7) {
        $color = 'warning';
        $texto = '🟡 Pendiente';
    } else {
        $color = 'danger';
        $texto = '🔴 Urgente';
    }
    ?>
    <div class="alert alert-primary text-center fw-bold">
        🧑 Paciente: <?= htmlspecialchars($nombre_paciente) ?> — <?= $edad ?>
    </div>
    <ul class="list-group mb-4 bg-light-subtle">
        <li class="list-group-item fw-bold text-center bg-light-subtle">
            🕒 Fecha de Creación: <?= $fecha_formateada ?>
            <br><small class="text-muted">(hace <?= $dias_transcurridos ?> días)</small><br>
            <span class="badge bg-<?= $color ?>" data-bs-toggle="tooltip"
                  title="Estado de urgencia basado en los días transcurridos desde la creación">
                <?= $texto ?>
            </span>
        </li>
    </ul>
    <ul class="list-group mb-4 bg-light-subtle">
        <li class="list-group-item">
            <strong>Formulario ID:</strong> <?= htmlspecialchars($solicitud['form_id']) ?><br>
            <strong>HC #:</strong> <?= htmlspecialchars($solicitud['hc_number']) ?>
        </li>
    </ul>
    <h5 class="mt-4 border-bottom pb-1">📄 Datos del Paciente</h5>
    <ul class="list-group mb-4 bg-light-subtle">
        <li class="list-group-item">
            <i class="bi bi-gender-ambiguous"></i>
            <strong>Sexo:</strong> <?= htmlspecialchars($paciente['sexo'] ?? 'No disponible') ?><br>
            <i class="bi bi-shield-check"></i>
            <strong>Afiliación:</strong> <?= htmlspecialchars($paciente['afiliacion'] ?? 'No disponible') ?><br>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cake-fill"
                 viewBox="0 0 16 16">
                <path d="m7.399.804.595-.792.598.79A.747.747 0 0 1 8.5 1.806V4H11a2 2 0 0 1 2 2v3h1a2 2 0 0 1 2 2v4a1 1 0 0 1-1 1H1a1 1 0 0 1-1-1v-4a2 2 0 0 1 2-2h1V6a2 2 0 0 1 2-2h2.5V1.813a.747.747 0 0 1-.101-1.01ZM12 6.414a.9.9 0 0 1-.646-.268 1.914 1.914 0 0 0-2.708 0 .914.914 0 0 1-1.292 0 1.914 1.914 0 0 0-2.708 0A.9.9 0 0 1 4 6.414v1c.49 0 .98-.187 1.354-.56a.914.914 0 0 1 1.292 0c.748.747 1.96.747 2.708 0a.914.914 0 0 1 1.292 0c.374.373.864.56 1.354.56zm2.646 5.732a.914.914 0 0 1-1.293 0 1.914 1.914 0 0 0-2.707 0 .914.914 0 0 1-1.292 0 1.914 1.914 0 0 0-2.708 0 .914.914 0 0 1-1.292 0 1.914 1.914 0 0 0-2.708 0 .914.914 0 0 1-1.292 0L1 11.793v1.34c.737.452 1.715.36 2.354-.28a.914.914 0 0 1 1.292 0c.748.748 1.96.748 2.708 0a.914.914 0 0 1 1.292 0c.748.748 1.96.748 2.707 0a.914.914 0 0 1 1.293 0 1.915 1.915 0 0 0 2.354.28v-1.34z"/>
            </svg>
            </i> <strong>Fecha Nacimiento:</strong>
            <?php
            if (!empty($fecha_nacimiento)) {
                try {
                    $fecha_nacimiento_dt = new DateTime($fecha_nacimiento);
                    echo htmlspecialchars($fecha_nacimiento_dt->format('d-m-Y'));
                } catch (Exception $e) {
                    echo 'No disponible';
                }
            } else {
                echo 'No disponible';
            }
            ?><br>
            <i class="bi bi-phone"></i>
            <strong>Celular:</strong> <?= htmlspecialchars($paciente['celular'] ?? 'No disponible') ?>
        </li>
    </ul>

    <div class="row g-2">
        <div class="col-12 col-md-6">
            <h5>🗂️ Información de la Solicitud</h5>
            <ul class="list-group mb-3">
                <li class="list-group-item">
                    <strong>Afiliación:</strong> <?= htmlspecialchars($solicitud['afiliacion']) ?></li>
                <li class="list-group-item">
                    <strong>Procedimiento:</strong> <?= htmlspecialchars($solicitud['procedimiento']) ?></li>
                <li class="list-group-item"><strong>Doctor:</strong> <?= htmlspecialchars($solicitud['doctor']) ?></li>
                <li class="list-group-item"><strong>Duración:</strong> <?= htmlspecialchars($solicitud['duracion']) ?>
                    minutos
                </li>
                <li class="list-group-item"><strong>Prioridad:</strong> <?= htmlspecialchars($solicitud['prioridad']) ?>
                </li>
                <li class="list-group-item"><strong>Estado:</strong> <?= htmlspecialchars($solicitud['estado']) ?></li>
                <?php if (!empty($data['diagnostico'])): ?>
                    <li class="list-group-item">
                        <i class="bi bi-clipboard2-pulse"></i>
                        <strong>Diagnósticos:</strong><br>
                        <ul class="mb-0">
                            <?php foreach ($data['diagnostico'] as $dx): ?>
                                <li>
                                    <span class="text-primary"><?= htmlspecialchars($dx['dx_code']) ?></span> –
                                    <?= htmlspecialchars($dx['descripcion']) ?>
                                    (<?= htmlspecialchars($dx['lateralidad']) ?>)
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="list-group-item">
                        <i class="bi bi-clipboard2-pulse"></i>
                        <strong>Diagnósticos:</strong> No disponibles
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="col-12 col-md-6">
            <h5>📌 Información de la Derivación</h5>
            <ul class="list-group mb-3 bg-<?= $vig_color ?? 'light' ?>-subtle">
                <li class="list-group-item">
                    <i class="bi bi-upc-scan"></i> <strong>Código
                        Derivación:</strong> <?= htmlspecialchars($derivacion['cod_derivacion'] ?? 'No disponible') ?>
                </li>
                <li class="list-group-item">
                    <i class="bi bi-calendar-check"></i> <strong>Fecha
                        Registro:</strong> <?= htmlspecialchars($derivacion['fecha_registro'] ?? 'No disponible') ?>
                </li>
                <li class="list-group-item">
                    <i class="bi bi-calendar-event"></i> <strong>Fecha
                        Vigencia:</strong> <?= htmlspecialchars($derivacion['fecha_vigencia'] ?? 'No disponible') ?>
                </li>
                <?php
                $vigencia_texto = 'No disponible';
                if (!empty($derivacion['fecha_vigencia'])) {
                    try {
                        $fecha_vigencia_dt = new DateTime($derivacion['fecha_vigencia']);
                        $dias_restantes = $hoy->diff($fecha_vigencia_dt)->format('%r%a');

                        if ($dias_restantes >= 60) {
                            $vig_color = 'success';
                            $vig_estado = '🟢 Vigente (recién emitida)';
                        } elseif ($dias_restantes >= 30) {
                            $vig_color = 'info';
                            $vig_estado = '🔵 Vigente';
                        } elseif ($dias_restantes >= 15) {
                            $vig_color = 'warning';
                            $vig_estado = '🟡 Por vencer';
                        } elseif ($dias_restantes >= 0) {
                            $vig_color = 'danger';
                            $vig_estado = '🔴 Urgente';
                        } else {
                            $vig_color = 'dark';
                            $vig_estado = '⚫ Vencida';
                        }

                        $vigencia_texto = "<strong>Días para caducar:</strong> {$dias_restantes} días <span class='badge bg-{$vig_color}'>{$vig_estado}</span>";
                    } catch (Exception $e) {
                        $vigencia_texto = "No disponible";
                    }
                }
                ?>
                <li class="list-group-item">
                    <i class="bi bi-hourglass-split"></i> <?= $vigencia_texto ?>
                </li>
                <?php if (!empty($derivacion['fecha_vigencia']) && $dias_restantes >= 0 && $dias_restantes <= 7): ?>
                    <li class="list-group-item text-center">
                        <button class="btn btn-danger btn-sm" data-bs-toggle="tooltip"
                                title="Haz clic para actualizar la derivación próxima a vencer"
                                onclick="alert('Función de actualización aún no implementada.')">📌 Actualizar Derivación
                        </button>
                    </li>
                <?php endif; ?>
                <li class="list-group-item">
                    <i class="bi bi-clipboard2-pulse"></i>
                    <strong>Diagnóstico:</strong> <?= htmlspecialchars($derivacion['diagnostico'] ?? 'No disponible') ?>
                </li>
            </ul>
        </div>
    </div>
    <script>
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
<?php else: ?>
    <p>No se encontraron datos de prefactura para este paciente.</p>
<?php endif; ?>