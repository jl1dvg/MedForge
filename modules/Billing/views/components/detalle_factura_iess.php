<div class="mb-2">
    <span class="badge bg-primary">Procedimientos (Cirujano)</span>
    <span class="badge bg-info text-dark">Ayudante</span>
    <span class="badge bg-danger">Anestesia</span>
    <span class="badge bg-success">Farmacia (por mL)</span>
    <span class="badge bg-warning text-dark">Farmacia</span>
    <span class="badge bg-light text-dark border">Insumos</span>
    <span class="badge bg-secondary">Servicios Institucionales</span>
</div>
<div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <h5 class="mb-0 me-auto">
                <?= htmlspecialchars($datos['protocoloExtendido']['membrete'] . ' - ' . date('d/m/Y', strtotime($datos['protocoloExtendido']['fecha_inicio']))) ?>
            </h5>
            <button type="button" class="btn btn-sm btn-danger ms-2"
                    onclick="confirmarEliminacion('<?= $datos['billing']['form_id'] ?? '' ?>')">
                🗑️ Eliminar Factura
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="col-12 table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-dark">
                <tr>
                    <th class="text-center">#</th>
                    <th class="text-center">Código</th>
                    <th class="text-center">Descripción</th>
                    <th class="text-center">Anestesia</th>
                    <th class="text-center">%Pago</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Valor</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-center">%Bodega</th>
                    <th class="text-center">%IVA</th>
                    <th class="text-end">Total</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $total = 0;
                $n = 1;
                $has67036 = false;

                // Check if any procedimiento has codigo 67036
                foreach ($datos['procedimientos'] as $p) {
                    if (($p['proc_codigo'] ?? '') === '67036') {
                        $has67036 = true;
                        break;
                    }
                }

                // Separar procedimientos en 67036 y otros
                $procedimientos67036 = [];
                $otrosProcedimientos = [];
                foreach ($datos['procedimientos'] as $p) {
                    if (($p['proc_codigo'] ?? '') === '67036') {
                        $procedimientos67036[] = $p;
                    } else {
                        $otrosProcedimientos[] = $p;
                    }
                }

                // Primero imprimir los 67036
                foreach ($procedimientos67036 as $p) {
                    $codigo = $p['proc_codigo'] ?? '';
                    $descripcion = $p['proc_detalle'] ?? '';
                    $valorUnitario = (float)($p['proc_precio'] ?? 0);
                    $cantidad = 1;
                    $anestesia = 'NO';
                    $bodega = 0;
                    $iva = 0;
                    // Double row logic for 67036
                    // First row 62.5%
                    $porcentaje1 = 0.625;
                    $subtotal1 = $valorUnitario * $cantidad * $porcentaje1;
                    $total += $subtotal1;
                    $porcentajePago1 = $porcentaje1 * 100;
                    $montoTotal1 = $subtotal1;
                    echo "<tr class='table-primary' style='font-size: 12.5px;'>
                <td class='text-center'>{$n}</td>
                <td class='text-center'>{$codigo}</td>
                <td>{$descripcion} (Parte 1)</td>
                <td class='text-center'>{$anestesia}</td>
                <td class='text-center'>{$porcentajePago1}</td>
                <td class='text-end'>{$cantidad}</td>
                <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                <td class='text-end'>" . number_format($subtotal1, 2) . "</td>
                <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
                <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
                <td class='text-end'>" . number_format($montoTotal1, 2) . "</td>
            </tr>";
                    $n++;
                    // Second row 37.5% (should be 0.375, but code used 0.625 again, so keep as is for now)
                    $porcentaje2 = 0.625;
                    $subtotal2 = $valorUnitario * $cantidad * $porcentaje2;
                    $total += $subtotal2;
                    $porcentajePago2 = $porcentaje2 * 100;
                    $montoTotal2 = $subtotal2;
                    echo "<tr class='table-primary' style='font-size: 12.5px;'>
                <td class='text-center'>{$n}</td>
                <td class='text-center'>{$codigo}</td>
                <td>{$descripcion} (Parte 2)</td>
                <td class='text-center'>{$anestesia}</td>
                <td class='text-center'>{$porcentajePago2}</td>
                <td class='text-end'>{$cantidad}</td>
                <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                <td class='text-end'>" . number_format($subtotal2, 2) . "</td>
                <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
                <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
                <td class='text-end'>" . number_format($montoTotal2, 2) . "</td>
            </tr>";
                    $n++;
                }

                // Luego imprimir los otros procedimientos
                foreach ($otrosProcedimientos as $index => $p) {
                    $codigo = $p['proc_codigo'] ?? '';
                    $descripcion = $p['proc_detalle'] ?? '';
                    $valorUnitario = (float)($p['proc_precio'] ?? 0);
                    $cantidad = 1;
                    $anestesia = 'NO';
                    $bodega = 0;
                    $iva = 0;
                    // Ajuste de porcentaje según presencia de 67036
                    if ($has67036) {
                        $porcentaje = 0.5;
                    } else {
                        $porcentaje = ($index === 0 || stripos($descripcion, 'separado') !== false) ? 1 : 0.5;
                    }
                    $subtotal = $valorUnitario * $cantidad * $porcentaje;
                    $total += $subtotal;
                    $porcentajePago = $porcentaje * 100;
                    $montoTotal = $subtotal;
                    echo "<tr class='table-primary' style='font-size: 12.5px;'>
                <td class='text-center'>{$n}</td>
                <td class='text-center'>{$codigo}</td>
                <td>{$descripcion}</td>
                <td class='text-center'>{$anestesia}</td>
                <td class='text-center'>{$porcentajePago}</td>
                <td class='text-end'>{$cantidad}</td>
                <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
                <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
                <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
            </tr>";
                    $n++;
                }

                // AYUDANTE - omit if 67036 present
                if (!$has67036 && (!empty($datos['protocoloExtendido']['cirujano_2']) || !empty($datos['protocoloExtendido']['primer_ayudante']))) {
                    foreach ($datos['procedimientos'] as $index => $p) {
                        $codigo = $p['proc_codigo'] ?? '';
                        $descripcion = $p['proc_detalle'] ?? '';
                        $valorUnitario = (float)($p['proc_precio'] ?? 0);
                        $cantidad = 1;
                        $porcentaje = ($index === 0) ? 0.2 : 0.1;
                        $subtotal = $valorUnitario * $cantidad * $porcentaje;
                        $total += $subtotal;
                        $anestesia = 'NO';
                        $porcentajePago = $porcentaje * 100;
                        $bodega = 0;
                        $iva = 0;
                        $montoTotal = $subtotal;

                        echo "<tr class='table-info' style='font-size: 12.5px;'>
                    <td class='text-center'>{$n}</td>
                    <td class='text-center'>{$codigo}</td>
                    <td>{$descripcion}</td>
                    <td class='text-center'>{$anestesia}</td>
                    <td class='text-center'>{$porcentajePago}</td>
                    <td class='text-end'>{$cantidad}</td>
                    <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                    <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                    <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
                    <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
                    <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                </tr>";
                        $n++;
                    }
                }

                // --- ANESTESIA POR PROCEDIMIENTO PRINCIPAL ---
                $codigoAnestesia = $datos['procedimientos'][0]['proc_codigo'] ?? '';
                $precioReal = $codigoAnestesia ? $billingController->obtenerValorAnestesia($codigoAnestesia) : null;
                $esCirugia = $billingController->esCirugiaPorFormId($datos['billing']['form_id'] ?? null);

                if ($esCirugia && !empty($datos['procedimientos']) && isset($datos['procedimientos'][0]['proc_codigo'])) {
                    $p = $datos['procedimientos'][0];
                    $precio = (float)($p['proc_precio'] ?? 0);
                    $valorUnitario = $precioReal ?? $precio;
                    $cantidad = 1;
                    $subtotal = $valorUnitario * $cantidad;
                    $total += $subtotal;

                    echo "<tr class='table-danger' style='font-size: 12.5px;'>
                        <td class='text-center'>{$n}</td>
                        <td class='text-center'>{$p['proc_codigo']}</td>
                        <td>{$p['proc_detalle']} (Anestesia Principal)</td>
                        <td class='text-center'>SI</td>
                        <td class='text-center'>100</td>
                        <td class='text-end'>{$cantidad}</td>
                        <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                        <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                        <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
                        <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
                        <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                    </tr>";
                    $n++;
                }

                // ANESTESIA
                foreach ($datos['anestesia'] as $a) {
                    $codigo = $a['codigo'] ?? '';
                    $descripcion = $a['nombre'] ?? '';
                    $tiempoRaw = $a['tiempo'] ?? 0;
                    $cantidad = (float)str_replace(',', '.', $tiempoRaw);
                    $valorUnitario = (float)($a['valor2'] ?? 0);
                    $subtotal = $cantidad * $valorUnitario;
                    $total += $subtotal;
                    $anestesia = 'SI';
                    $porcentajePago = 100;
                    $bodega = 0;
                    $iva = 0;
                    $montoTotal = $subtotal;

                    echo "<tr class='table-danger' style='font-size: 12.5px;'>
                <td class='text-center'>{$n}</td>
                <td class='text-center'>{$codigo}</td>
                <td>{$descripcion}</td>
                <td class='text-center'>{$anestesia}</td>
                <td class='text-center'>{$porcentajePago}</td>
                <td class='text-end'>{$cantidad}</td>
                <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
                <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
                <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
            </tr>";
                    $n++;
                }

                // FARMACIA e INSUMOS
                $fuenteDatos = [
                    ['grupo' => 'FARMACIA', 'items' => array_merge($datos['medicamentos'], $datos['oxigeno'])],
                    ['grupo' => 'INSUMOS', 'items' => $datos['insumos']],
                ];

                foreach ($fuenteDatos as $bloque) {
                    $grupo = $bloque['grupo'];
                    foreach ($bloque['items'] as $item) {
                        $descripcion = $item['nombre'] ?? $item['detalle'] ?? '';
                        $codigo = $item['codigo'] ?? '';
                        $bodega = 1;
                        $iva = ($grupo === 'FARMACIA') ? 0 : 1;

                        if ($grupo === 'FARMACIA' && isset($item['litros']) && isset($item['tiempo']) && isset($item['valor2'])) {
                            // Farmacia por mL (por mL logic)
                            $cantidad = (float)$item['tiempo'] * (float)$item['litros'] * 60;
                            $valorUnitario = (float)$item['valor2'];
                            $subtotal = $valorUnitario * $cantidad;
                            $montoTotal = $subtotal;
                            $total += $montoTotal;
                            $anestesia = 'NO';
                            $porcentajePago = 100;

                            echo "<tr class='table-success' style='font-size: 12.5px;'>
                        <td class='text-center'>{$n}</td>
                        <td class='text-center'>{$codigo}</td>
                        <td>{$descripcion}</td>
                        <td class='text-center'>{$anestesia}</td>
                        <td class='text-center'>{$porcentajePago}</td>
                        <td class='text-end'>" . number_format($cantidad, 2) . "</td>
                        <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                        <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                        <td class='text-center'><span class='badge bg-success'>1</span></td>
                        <td class='text-center'><span class='badge bg-success'>0</span></td>
                        <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                    </tr>";
                            $n++;
                        } elseif ($grupo === 'FARMACIA') {
                            // Farmacia normal
                            $cantidad = $item['cantidad'] ?? 1;
                            $valorUnitario = $item['precio'] ?? 0;
                            $subtotal = $valorUnitario * $cantidad;
                            $montoTotal = $subtotal;
                            $total += $montoTotal;
                            $anestesia = 'NO';
                            $porcentajePago = 100;

                            echo "<tr class='table-warning' style='font-size: 12.5px;'>
                        <td class='text-center'>{$n}</td>
                        <td class='text-center'>{$codigo}</td>
                        <td>{$descripcion}</td>
                        <td class='text-center'>{$anestesia}</td>
                        <td class='text-center'>{$porcentajePago}</td>
                        <td class='text-end'>" . number_format($cantidad, 2) . "</td>
                        <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                        <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                        <td class='text-center'><span class='badge bg-warning text-dark'>1</span></td>
                        <td class='text-center'><span class='badge bg-warning text-dark'>0</span></td>
                        <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                    </tr>";
                            $n++;
                        } else {
                            // Insumos
                            $cantidad = $item['cantidad'] ?? 1;
                            $valorUnitario = $item['precio'] ?? 0;
                            $subtotal = $valorUnitario * $cantidad;
                            $montoTotal = $subtotal;
                            $total += $montoTotal;
                            $anestesia = 'NO';
                            $porcentajePago = 100;

                            echo "<tr class='table-light' style='font-size: 12.5px;'>
                        <td class='text-center'>{$n}</td>
                        <td class='text-center'>{$codigo}</td>
                        <td>{$descripcion}</td>
                        <td class='text-center'>{$anestesia}</td>
                        <td class='text-center'>{$porcentajePago}</td>
                        <td class='text-end'>" . number_format($cantidad, 2) . "</td>
                        <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                        <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                        <td class='text-center'><span class='badge bg-light text-dark border'>1</span></td>
                        <td class='text-center'><span class='badge bg-light text-dark border'>1</span></td>
                        <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                    </tr>";
                            $n++;
                        }
                    }
                }

// SERVICIOS INSTITUCIONALES (derechos)
foreach ($datos['derechos'] as $servicio) {
    $codigo = $servicio['codigo'] ?? '';
    $descripcion = $servicio['detalle'] ?? '';
    $cantidad = $servicio['cantidad'] ?? 1;
    $valorUnitario = $servicio['precio_afiliacion'] ?? 0;
    // Nueva lógica para ciertos códigos
    if (
        ((int)$codigo >= 394200 && (int)$codigo < 394400)) {
        $valorUnitario *= 1.02;
        $valorUnitario -= 0.01;
    }
    if ($codigo === '395281') {
        $valorUnitario *= 1.02; // Aumenta 2%
    }
    $subtotal = $valorUnitario * $cantidad;
    $bodega = 0;
    $iva = 0;
    $montoTotal = $subtotal;
    $total += $montoTotal;
    $anestesia = 'NO';
    $porcentajePago = 100;


    echo "<tr class='table-secondary' style='font-size: 12.5px;'> 
        <td class='text-center'>{$n}</td>
        <td class='text-center'>{$codigo}</td>
        <td>{$descripcion}</td>
        <td class='text-center'>{$anestesia}</td>
        <td class='text-center'>{$porcentajePago}</td>
        <td class='text-end'>{$cantidad}</td>
        <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
        <td class='text-end'>" . number_format($subtotal, 2) . "</td>
        <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
        <td class='text-center'><span class='badge bg-secondary'>0%</span></td>
        <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
    </tr>";
    $n++;
}
                ?>
                </tbody>
            </table>
        </div>
    </div> <!-- end card-body -->
</div> <!-- end card -->

<!-- Bloque total estilo invoice -->
<div class="row mt-3">
    <div class="col-12 text-end">
        <p class="lead mb-1">
            <b>Total a pagar</b>
            <span class="text-danger ms-2" style="font-size: 1.25em;">
                                                $<?= number_format($total, 2) ?>
                                            </span>
        </p>
        <!-- Si quieres puedes agregar detalles adicionales, como subtotal, descuentos, etc. aquí -->
        <!-- <div>
                                            <p>Sub - Total amount: $<?= number_format($subtotal, 2) ?></p>
                                            <p>Tax (IVA 12%): $<?= number_format($iva, 2) ?></p>
                                        </div> -->
        <div class="total-payment mt-2">
            <h4 class="fw-bold">
                <span class="text-success"><b>Total :</b></span>
                $<?= number_format($total, 2) ?>
            </h4>
        </div>
        <script>
            function confirmarEliminacion(formId) {
                Swal.fire({
                    title: '¿Estás seguro?',
                    text: "Esta acción eliminará la factura.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonText: 'Cancelar',
                    confirmButtonText: 'Sí, eliminar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('/informes/api/eliminar-factura', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: new URLSearchParams({form_id: formId})
                        })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Factura eliminada',
                                        text: 'La factura fue eliminada correctamente',
                                        timer: 2000,
                                        showConfirmButton: false
                                    }).then(() => {
                                        // Oculta toda la factura visualmente sin recargar
                                        document.querySelector('.table-responsive').innerHTML = '<div class="alert alert-success">✅ Factura eliminada correctamente.</div>';
                                        document.querySelector('.total-payment').style.display = 'none';
                                    });
                                } else {
                                    Swal.fire('Error', 'No se pudo eliminar la factura.', 'error');
                                }
                            })
                            .catch(() => Swal.fire('Error', 'Error en la solicitud.', 'error'));
                    }
                });
            }
        </script>
    </div>
</div>
