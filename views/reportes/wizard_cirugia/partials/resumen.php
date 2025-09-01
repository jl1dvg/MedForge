<h6>Resumen Final</h6>
<section>
    <?php
    // ===== Preparación de datos para el resumen =====
    // Edad a partir de fecha_nacimiento
    $edad = '';
    if (!empty($cirugia->fecha_nacimiento)) {
        try {
            $fn = new DateTime($cirugia->fecha_nacimiento);
            $edad = (new DateTime())->diff($fn)->y . ' años';
        } catch (Exception $e) {
            $edad = '';
        }
    }

    // Procedimientos (preferir $procedimientosArray si viene, si no decodificar JSON de $cirugia)
    if (!isset($procedimientosArray) || !is_array($procedimientosArray)) {
        $procedimientosArray = [];
        if (!empty($cirugia->procedimientos)) {
            $tmp = json_decode($cirugia->procedimientos, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $procedimientosArray = $tmp;
            }
        }
    }
    $procedimientosNombres = array_values(array_filter(array_map(function ($p) {
        // Intentamos varios keys comunes
        return $p['procInterno'] ?? $p['nombre'] ?? $p['proc_nombre'] ?? '';
    }, $procedimientosArray)));

    // Diagnósticos actuales (preferir $diagnosticosArray si viene, si no decodificar JSON de $cirugia)
    if (!isset($diagnosticosArray) || !is_array($diagnosticosArray)) {
        $diagnosticosArray = [];
        if (!empty($cirugia->diagnosticos)) {
            $tmp = json_decode($cirugia->diagnosticos, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $diagnosticosArray = $tmp;
            }
        }
    }
    $diagnosticosCodigos = array_values(array_filter(array_map(function ($d) {
        return $d['idDiagnostico'] ?? $d['cie10'] ?? '';
    }, $diagnosticosArray)));

    // Diagnósticos previos (preferir lo que viene del POST si aún no se ha guardado)
    $diagnosticosPrevios = [];
    $previosSource = 'NONE';
    $previosErr = '';
    $previosPostRaw = null;
    $previosDbRaw = null;
    $debugPrevios = (isset($_GET['debug_previos']) && $_GET['debug_previos'] !== '0');

    if (isset($_POST['diagnosticos_previos'])) {
        $previosPostRaw = $_POST['diagnosticos_previos'];
        if (is_string($previosPostRaw) && trim($previosPostRaw) !== '') {
            $tmp = json_decode($previosPostRaw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $diagnosticosPrevios = $tmp;
                $previosSource = 'POST';
            } else {
                $previosErr = 'POST json_decode: ' . json_last_error_msg();
            }
        } else {
            $previosErr = 'POST vacío o no string';
        }
    }

    if ($previosSource === 'NONE') {
        $previosDbRaw = $cirugia->diagnosticos_previos ?? null;
        if (!empty($previosDbRaw)) {
            $tmp = json_decode($previosDbRaw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $diagnosticosPrevios = $tmp;
                $previosSource = 'DB';
            } else {
                $previosErr = 'DB json_decode: ' . json_last_error_msg();
            }
        } else {
            $previosErr = $previosErr ?: 'DB vacío';
        }
    }

    // Normalizar a strings "CIE - DESCRIPCIÓN"
    $diagnosticosPreviosFmt = array_values(array_filter(array_map(function ($d) {
        $cie = isset($d['cie10']) ? strtoupper(trim((string)$d['cie10'])) : '';
        $desc = isset($d['descripcion']) ? strtoupper(trim((string)$d['descripcion'])) : '';
        return $cie !== '' ? ($cie . ($desc !== '' ? ' - ' . $desc : '')) : '';
    }, $diagnosticosPrevios)));

    // Fechas/horas: preferir POST si existen (aún sin guardar), sino tomar de $cirugia
    $fi = isset($_POST['fecha_inicio']) ? trim((string)$_POST['fecha_inicio']) : trim((string)($cirugia->fecha_inicio ?? ''));
    $hi = isset($_POST['hora_inicio']) ? trim((string)$_POST['hora_inicio']) : trim((string)($cirugia->hora_inicio ?? ''));
    $ff = isset($_POST['fecha_fin']) ? trim((string)$_POST['fecha_fin']) : trim((string)($cirugia->fecha_fin ?? ''));
    $hf = isset($_POST['hora_fin']) ? trim((string)$_POST['hora_fin']) : trim((string)($cirugia->hora_fin ?? ''));

    // Armar cadenas legibles (si alguno está vacío, quedará '—' más abajo al renderizar)
    $fechaHoraInicio = trim($fi . ' ' . $hi);
    $fechaHoraFin = trim($ff . ' ' . $hf);

    // Formateo legible DD/MM/YYYY HH:MM si es posible
    $fechaHoraInicioFmt = '';
    $fechaHoraFinFmt = '';
    try {
        if ($fi !== '' || $hi !== '') {
            $dtI = new DateTime(trim($fi . ' ' . $hi));
            $fechaHoraInicioFmt = $dtI->format('d/m/Y H:i');
        }
    } catch (Exception $e) {
        $fechaHoraInicioFmt = $fechaHoraInicio;
    }
    try {
        if ($ff !== '' || $hf !== '') {
            $dtF = new DateTime(trim($ff . ' ' . $hf));
            $fechaHoraFinFmt = $dtF->format('d/m/Y H:i');
        }
    } catch (Exception $e) {
        $fechaHoraFinFmt = $fechaHoraFin;
    }
    ?>
    <div class="alert alert-info">Este es un resumen de los datos ingresados. Revise antes
        de marcar como revisado.
    </div>
    <?php
    // Mostrar alerta si no hay diagnósticos previos y explicar por qué
    if (empty($diagnosticosPreviosFmt)) {
        echo '<div class="alert alert-warning mb-2"><strong>Nota:</strong> No se encontraron diagnósticos previos para mostrar.';
        echo ' <span class="badge bg-secondary">Fuente intentada: ' . htmlspecialchars($previosSource) . '</span>';
        if (!empty($previosErr)) {
            echo ' &mdash; Motivo: ' . htmlspecialchars($previosErr);
        }
        if ($debugPrevios) {
            $postLen = is_string($previosPostRaw) ? strlen($previosPostRaw) : 0;
            $dbLen = is_string($previosDbRaw) ? strlen($previosDbRaw) : 0;
            echo '<hr class="my-2"><small class="text-muted">Debug activo (?debug_previos=1): ';
            echo 'POST len=' . (int)$postLen . ', DB len=' . (int)$dbLen . '. ';
            if ($postLen) {
                echo 'POST sample: <code>' . htmlspecialchars(substr($previosPostRaw, 0, 200)) . ($postLen > 200 ? '…' : '') . '</code> ';
            }
            if ($dbLen) {
                echo 'DB sample: <code>' . htmlspecialchars(substr($previosDbRaw, 0, 200)) . ($dbLen > 200 ? '…' : '') . '</code>';
            }
            echo '</small>';
        }
        echo '</div>';
    }
    ?>
    <style>
        /* —— Estilos suaves para mejorar la lectura del resumen —— */
        .resume-card {
            border: 1px solid #e6e9ef;
            border-radius: .5rem;
            background: #fff;
        }

        .resume-card .card-header {
            background: #f5f7fb;
            border-bottom: 1px solid #e6e9ef;
        }

        .chip-group {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .chip {
            border: 1px solid #d9dee8;
            background: #f8f9fb;
            border-radius: 999px;
            padding: .25rem .6rem;
            font-size: .85rem;
        }

        .chip-primary {
            border-color: #bcd3ff;
            background: #eef5ff;
        }

        .muted {
            color: #6c757d;
        }

        .kv {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: .25rem .75rem;
        }

        @media (max-width: 576px) {
            .kv {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div id="resumenFinal" class="resume-card mb-3">
        <div class="card-header py-2 px-3">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="m-0">Resumen de Protocolo</h6>
                <?php if (!empty($edad)): ?><span class="badge bg-light text-dark border">
                    Edad: <?= htmlspecialchars($edad) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="card-body p-3">
            <div class="row g-3">
                <!-- Columna izquierda: Datos del paciente -->
                <div class="col-md-6">
                    <div class="kv">
                        <div class="muted">Paciente</div>
                        <div><strong><?php $mname = $cirugia->mname ?? '';
                                echo htmlspecialchars(trim($cirugia->fname . ' ' . $mname . ' ' . $cirugia->lname . ' ' . $cirugia->lname2)); ?></strong>
                        </div>

                        <div class="muted">Fecha de Nacimiento</div>
                        <div><?= htmlspecialchars($cirugia->fecha_nacimiento ?: '—') ?></div>

                        <div class="muted">Afiliación</div>
                        <div><?= htmlspecialchars($cirugia->afiliacion ?: '—') ?></div>

                        <div class="muted">Lateralidad</div>
                        <div><?= htmlspecialchars($cirugia->lateralidad ?: '—') ?></div>
                    </div>
                </div>

                <!-- Columna derecha: Equipo y tiempos -->
                <div class="col-md-6">
                    <div class="kv">
                        <div class="muted">Cirujano Principal</div>
                        <div><?= htmlspecialchars($cirugia->cirujano_1 ?: '—') ?></div>

                        <div class="muted">Anestesiólogo</div>
                        <div><?= htmlspecialchars($cirugia->anestesiologo ?: '—') ?></div>

                        <div class="muted">Inicio</div>
                        <div><?= htmlspecialchars(($fechaHoraInicioFmt ?? ($fechaHoraInicio ?? '')) ?: '—') ?></div>

                        <div class="muted">Fin</div>
                        <div><?= htmlspecialchars(($fechaHoraFinFmt ?? ($fechaHoraFin ?? '')) ?: '—') ?></div>

                        <div class="muted">Tipo de Anestesia</div>
                        <div><?= htmlspecialchars($cirugia->tipo_anestesia ?: '—') ?></div>
                    </div>
                </div>
            </div>

            <!-- Procedimientos -->
            <hr class="my-3">
            <div class="mb-2"><strong>Procedimientos</strong></div>
            <div class="chip-group">
                <?php if (!empty($procedimientosNombres)): ?>
                    <?php foreach ($procedimientosNombres as $pn): ?>
                        <span class="chip" title="<?= htmlspecialchars($pn) ?>"><?= htmlspecialchars($pn) ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </div>

            <!-- Diagnósticos -->
            <hr class="my-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="mb-2"><strong>Diagnósticos previos</strong>
                        <?php
                        $badgePrevios = '';
                        if ($previosSource === 'POST') {
                            $badgePrevios = '<span class="badge bg-warning text-dark ms-2" title="Aún no guardados">Sin guardar</span>';
                        } elseif ($previosSource === 'DB') {
                            $badgePrevios = '<span class="badge bg-success ms-2" title="Leídos de base de datos">Guardados</span>';
                        }
                        echo $badgePrevios;
                        ?>
                    </div>
                    <div class="chip-group">
                        <?php if (!empty($diagnosticosPreviosFmt)): ?>
                            <?php foreach ($diagnosticosPreviosFmt as $dx): ?>
                                <span class="chip chip-primary"
                                      title="<?= htmlspecialchars($dx) ?>"><?= htmlspecialchars($dx) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2"><strong>Diagnósticos actuales</strong></div>
                    <div class="chip-group">
                        <?php if (!empty($diagnosticosCodigos)): ?>
                            <?php foreach ($diagnosticosCodigos as $dx): ?>
                                <span class="chip"
                                      title="<?= htmlspecialchars($dx) ?>"><?= htmlspecialchars($dx) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="form-group">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="status"
                   id="statusCheckbox"
                   value="1" <?= ($cirugia->status == 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="statusCheckbox">
                Marcar como revisado
            </label>
        </div>
    </div>
</section>