<?php
$codigoDerivacionPrincipal = $codigoDerivacion;
$fecha_registroPrincipal = $fecha_registro;
$codigoDerivacionObtenida = '';
$billingV2WritesEnabled = filter_var(
    $_ENV['BILLING_V2_WRITES_ENABLED'] ?? getenv('BILLING_V2_WRITES_ENABLED') ?? '0',
    FILTER_VALIDATE_BOOLEAN
);
$billingWritePrefix = $billingV2WritesEnabled ? '/v2' : '';
$billingVerificacionDerivacionEndpoint = $billingWritePrefix . '/api/billing/verificacion_derivacion.php';
$billingInsertarBillingMainEndpoint = $billingWritePrefix . '/api/billing/insertar_billing_main.php';

// Renderizar procedimientos scrappeados cuando se intentó scrapear
if (!empty($scrapingOutput)):
    // Tolerar respuestas en string o array
    if (is_array($scrapingOutput)) {
        $codigo_derivacion = $scrapingOutput["codigo_derivacion"] ?? '';
        $fecha_registro = $scrapingOutput["fecha_registro"] ?? '';
    } else {
        $codigo_derivacion = '';
        $fecha_registro = '';
    }
    $codigoDerivacionObtenida = $codigo_derivacion;

    $partes = explode("📋 Procedimientos proyectados:", (string)$scrapingOutput);
    if (isset($partes[1])):
        $lineas = array_filter(array_map('trim', explode("\n", trim($partes[1]))));
        $grupos = [];
        for ($i = 0; $i < count($lineas); $i += 5) {
            $idLinea = $lineas[$i] ?? '';
            $procedimiento = $lineas[$i + 1] ?? '';
            $fecha = $lineas[$i + 2] ?? '';
            $doctor = $lineas[$i + 3] ?? '';
            $estado = $lineas[$i + 4] ?? '';
            $color = str_contains($estado, '✅') ? 'success' : 'danger';
            $grupos[] = [
                'form_id' => trim($idLinea),
                'procedimiento' => trim($procedimiento),
                'fecha' => trim($fecha),
                'doctor' => trim($doctor),
                'estado' => trim($estado),
                'color' => $color
            ];
        }
        // Obtener form_ids ya facturados desde el controller
        $formIdsFacturados = $billingController->obtenerFormIdsFacturados(); // Este método debe retornar un array de form_id

        ?>
        <div class="box">
            <div class="box-header with-border">
                <h4 class="box-title">Procedimientos proyectados <?php echo $codigoDerivacionPrincipal; ?></h4>
            </div>
            <div class="box-body">
                <div class="d-flex align-items-center mb-15">
                    <input type="checkbox" id="select_all_checkbox" class="filled-in chk-col-info me-10">
                    <label for="select_all_checkbox" class="mb-0">Seleccionar todos</label>
                </div>
                <?php
                // Detectar cirugías
                $cirugias = array_filter($grupos, function ($g) {
                    return strpos($g['procedimiento'], 'CYP-') !== false || strpos($g['procedimiento'], '67028') !== false || strpos($g['procedimiento'], '66984') !== false;
                });
                $fechas_cirugias = array_map(function ($g) {
                    return strtotime($g['fecha']);
                }, $cirugias);
                // Agrupar por mes
                $grupos_por_mes = [];
                foreach ($grupos as $g) {
                    $timestamp = strtotime($g['fecha']);
                    $mes_key = $timestamp ? date('Y-m', $timestamp) : 'Sin fecha';
                    $grupos_por_mes[$mes_key][] = $g;
                }
                foreach ($grupos_por_mes as $mes => $grupo_mes):
                    echo "<h5 class='mt-20 text-primary'>🗓️ " . htmlspecialchars($mes) . "</h5>";
                    foreach ($grupo_mes as $i => $g):
                        $fecha_proc = strtotime($g['fecha']);
                        $is_muted = false;
                        $is_disabled = false;
                        $estado_text = $g['estado'] ?? '';
                        $derivacionCod = $billingController->obtenerDerivacionPorFormId($g['form_id']);

                        if (strpos($g['procedimiento'], 'CYP-') !== false || strpos($g['procedimiento'], '67028') !== false || strpos($g['procedimiento'], '66984') !== false) {
                            $is_disabled = true;
                        }
                        $is_SER_OFT = strpos($g['procedimiento'], 'SER-OFT') !== false || strpos($g['procedimiento'], 'SRV-ANE-002') !== false;
                        if ($is_SER_OFT) {
                            foreach ($fechas_cirugias as $fecha_cx) {
                                if ($fecha_proc > $fecha_cx && $fecha_proc <= strtotime('+30 days', $fecha_cx)) {
                                    $is_muted = true;
                                    break;
                                }
                            }
                        }
                        if (strpos($estado_text, '❌ No dado de alta') !== false) {
                            $is_muted = true;
                        }
                        if (strpos($g['procedimiento'], 'SER-OFT-001 - OPTOMETRIA') !== false) {
                            $is_muted = true;
                        }
                        if (strpos($g['procedimiento'], 'SRV-ANE-002 - ANESTESIOLOGIA CONSULTA') !== false) {
                            $is_muted = true;
                        }
                        $ya_facturado = in_array($g['form_id'], $formIdsFacturados);
                        ?>
                        <div class="d-flex align-items-center mb-25 <?= ($is_muted || $ya_facturado) ? 'opacity-50' : '' ?>">
                            <span class="bullet bullet-bar bg-<?= $g['color'] ?> align-self-stretch"></span>
                            <div class="h-20 mx-20 flex-shrink-0">
                                <input type="checkbox" id="md_checkbox_proj_<?= $mes ?>_<?= $i ?>"
                                       class="filled-in chk-col-<?= $g['color'] ?>"
                                       value="<?= htmlspecialchars($g['form_id']) ?>"
                                       name="seleccionar_form_id"
                                    <?= ($is_muted || $is_disabled || $ya_facturado) ? 'disabled' : '' ?>>
                                <label for="md_checkbox_proj_<?= $mes ?>_<?= $i ?>"
                                       class="h-20 p-10 mb-0"></label>
                            </div>
                            <div class="d-flex flex-column flex-grow-1">
                                <span class="text-dark fw-500 fs-16"><?= htmlspecialchars($g['procedimiento']) ?></span>
                                <?php
                                $derivacion = $billingController->obtenerDerivacionPorFormId($g['form_id']);
                                $codigoDerivacion = is_array($derivacion) && isset($derivacion['cod_derivacion']) ? $derivacion['cod_derivacion'] : '';
                                ?>
                                <div>
                                    <span class="badge bg-info me-5"><?= htmlspecialchars($g['form_id']) ?></span>
                                    <?php if (!empty($codigoDerivacion)): ?>
                                        <span class="badge bg-success"><?= htmlspecialchars($codigoDerivacion) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Sin derivación</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-fade fw-500"><?= htmlspecialchars($g['fecha']) ?></span>
                                <span class="text-fade fw-500"><?= htmlspecialchars($g['doctor']) ?></span>
                                <?php
                        $derivacion = $billingController->obtenerDerivacionPorFormId($g['form_id']);
                        $codigoDerivacion = is_array($derivacion) && isset($derivacion['cod_derivacion']) ? $derivacion['cod_derivacion'] : '';
                        ?>
                        <span class="text-fade fw-500"><?= htmlspecialchars($codigoDerivacion) ?></span>
                    </div>
                            <span class="badge bg-<?= $g['color'] ?>"><?= htmlspecialchars($g['estado']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <!-- Botón para agregar seleccionados -->
                <form id="guardarSeleccionadosForm" method="post">
                    <input type="hidden" name="accion_guardar_procedimientos" value="1">
                    <div class="text-end mt-3">
                        <button type="button" class="btn btn-primary"
                                id="agregar-seleccionados">➕ Agregar seleccionados
                        </button>
                    </div>
                </form>
                <script>
                    let seleccionados = [];
                    const billingVerificacionDerivacionEndpoint = <?= json_encode($billingVerificacionDerivacionEndpoint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                    const billingInsertarBillingMainEndpoint = <?= json_encode($billingInsertarBillingMainEndpoint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

                    document.getElementById('agregar-seleccionados').addEventListener('click', async function () {
                        console.log("🟢 Botón 'Agregar seleccionados' clickeado");
                        const checkboxes = document.querySelectorAll('input[id^="md_checkbox_proj_"]:checked');
                        const seleccionados = Array.from(checkboxes).map(cb => {
                            const row = cb.closest('.d-flex');
                            const procedimiento = row.querySelector('.text-dark')?.textContent.trim();
                            const form_id = row.querySelector('.badge.bg-info')?.textContent.trim();
                            const fecha = row.querySelectorAll('.text-fade.fw-500')[0]?.textContent.trim();
                            const doctor = row.querySelectorAll('.text-fade.fw-500')[1]?.textContent.trim();
                            const estado = row.querySelector('.badge.bg-success, .badge.bg-danger, .badge.bg-warning, .badge.bg-primary')?.textContent.trim();
                            return {id: form_id, procedimiento, fecha, doctor, estado};
                        });

                        const formIds = seleccionados.map(p => p.id).filter(Boolean);
                        console.log("📦 Form IDs seleccionados:", formIds);

                        if (formIds.length === 0) {
                            alert("Por favor selecciona al menos un procedimiento.");
                            return;
                        }

                        try {
                            console.log("🚀 Enviando verificación de derivación");
                            const verificacion = await fetch(billingVerificacionDerivacionEndpoint, {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: 'form_ids[]=' + formIds.join('&form_ids[]=')
                            });
                            const data = await verificacion.json();
                            console.log("✅ Datos verificados:", data);

                            alert(`📊 Resultados:\n➕ Nuevos: ${data.nuevos.length}\n⛔ Existentes: ${data.existentes.length}`);

                            // Llamada unificada para registrar procedimientos completos
                            const codigoManual = (procedimiento) => {
                                const texto = (procedimiento || '').toUpperCase().trim();
                                if (
                                    texto.startsWith('SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-003 - CONSULTA OFTALMOLOGICA NUEVO PACIENTE') ||
                                    texto.startsWith('SER-OFT-003 - CONSULTA OFTALMOLOGICA NUEVO PACIENTE')
                                ) {
                                    return '92002';
                                }
                                if (
                                    texto.startsWith('SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-004 - CONSULTA OFTALMOLOGICA CITA MEDICA') ||
                                    texto.startsWith('SER-OFT-004 - CONSULTA OFTALMOLOGICA CITA MEDICA') ||
                                    texto.startsWith('SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-005 - CONSULTA OFTALMOLOGICA DE CONTROL') ||
                                    texto.startsWith('SER-OFT-005 - CONSULTA OFTALMOLOGICA DE CONTROL') ||
                                    texto.startsWith('SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-006 - CONSULTA OFTALMOLOGICA INTERCONSULTA') ||
                                    texto.startsWith('SER-OFT-006 - CONSULTA OFTALMOLOGICA INTERCONSULTA') ||
                                    texto.startsWith('SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-007 - REVISION DE EXAMENES') ||
                                    texto.startsWith('SER-OFT-007 - REVISION DE EXAMENES')
                                ) {
                                    return '92012';
                                }
                                return '';
                            };
                            const payloadCompleto = {
                                procedimientos: seleccionados.map(p => {
                                    let codigo = '';
                                    let detalle = '';

                                    const match = p.procedimiento.match(/ - ([0-9]{6})[-\s]+(.+)$/);
                                    if (match) {
                                        codigo = match[1];
                                        detalle = match[2].trim();
                                    } else {
                                        codigo = codigoManual(p.procedimiento);
                                        detalle = p.procedimiento;
                                    }
                                    return {
                                        form_id: p.id,
                                        hc_number: '<?php echo $hc_number ?? ""; ?>',
                                        codigo,
                                        detalle,
                                        precio: 0.00,
                                        codigo_derivacion: '<?php echo $codigoDerivacionPrincipal; ?>',
                                        fecha_vigencia: '<?php echo $fecha_vigencia ?? ""; ?>',
                                        fecha_registro: '<?php echo $fecha_registroPrincipal ?? ""; ?>',
                                        referido: '<?php echo $doctor ?? ""; ?>',
                                        diagnostico: '<?php echo $diagnostico ?? ""; ?>',
                                        procedimiento_proyectado: p.procedimiento,
                                        doctor: p.doctor,
                                        sede_departamento: null,
                                        id_sede: null,
                                        estado_agenda: p.estado.includes("Dado de Alta") ? "Dado de Alta" : "Pendiente",
                                        afiliacion: '<?php echo $afiliacion ?? ""; ?>',
                                        fecha: p.fecha.match(/\d{4}-\d{2}-\d{2}/)?.[0] ?? null,
                                        hora: p.fecha.match(/\d{2}:\d{2}:\d{2}/)?.[0] ?? null,
                                        visita_id: null
                                    };
                                })
                            };

                            console.log("📤 Payload completo a enviar al API:");
                            console.table(payloadCompleto.procedimientos);
                            console.log("📤 JSON.stringify:", JSON.stringify(payloadCompleto, null, 2));

                            const responseCompleto = await fetch(billingInsertarBillingMainEndpoint, {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify(payloadCompleto)
                            });
                            const jsonResponse = await responseCompleto.json();
                            console.log("✅ Respuesta del API:", jsonResponse);

                            Swal.fire({
                                title: '✅ Procesado correctamente',
                                text: 'Los procedimientos seleccionados fueron guardados.',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                location.reload();
                            });

                        } catch (error) {
                            console.error("❌ Error en procesamiento:", error);
                            alert("❌ Ocurrió un error. Revisa la consola para más detalles.");
                        } finally {
                            ocultarLoader();
                        }
                    });

                    function mostrarLoader() {
                        const loader = document.createElement('div');
                        loader.id = 'overlay-loader';
                        loader.style.cssText = `
                            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                            background: rgba(255,255,255,0.6); z-index: 9999;
                            display: flex; align-items: center; justify-content: center;
                            font-size: 1.5em; color: #000;
                        `;
                        loader.innerHTML = "⏳ Procesando...";
                        document.body.appendChild(loader);
                    }

                    function ocultarLoader() {
                        const loader = document.getElementById('overlay-loader');
                        if (loader) loader.remove();
                    }

                    document.getElementById('select_all_checkbox')?.addEventListener('change', function () {
                        const all = document.querySelectorAll('input[id^="md_checkbox_proj_"]:not(:disabled)');
                        all.forEach(cb => cb.checked = this.checked);
                    });
                </script>
            </div>
        </div>
    <?php
    endif;
endif;

// Modal de error si el scraping no devolvió código de derivación
if ($scrapingOutput !== null && $codigoDerivacionObtenida === ''): ?>
    <div class="modal fade" id="scrapeErrorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">No se obtuvo el código de derivación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p>El scraping no devolvió un código de derivación. Verifica que el formulario tenga derivación y vuelve a intentar.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const modalEl = document.getElementById('scrapeErrorModal');
            if (!modalEl || typeof bootstrap === 'undefined') return;
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        })();
    </script>
<?php endif; ?>
?>
