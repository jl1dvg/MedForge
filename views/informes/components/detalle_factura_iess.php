<div class="col-12 table-responsive">
    <table class="table table-bordered align-middle">
        <thead class="table-primary">
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

        // Procedimientos
        foreach ($datos['procedimientos'] as $index => $p) {
            $codigo = $p['proc_codigo'] ?? '';
            $descripcion = $p['proc_detalle'] ?? '';
            $valorUnitario = (float)($p['proc_precio'] ?? 0);
            $cantidad = 1;
            $porcentaje = ($index === 0 || stripos($descripcion, 'separado') !== false) ? 1 : 0.5;
            if ($codigo === '67036') $porcentaje = 0.625;
            $subtotal = $valorUnitario * $cantidad * $porcentaje;
            $total += $subtotal;

            $anestesia = 'NO';
            $porcentajePago = $porcentaje * 100;
            $bodega = 0;
            $iva = 0;
            $montoTotal = $subtotal;

            echo "<tr style='font-size: 12.5px;'>
                                                        <td class='text-center'>{$n}</td>
                                                        <td class='text-center'>{$codigo}</td>
                                                        <td>{$descripcion}</td>
                                                        <td class='text-center'>{$anestesia}</td>
                                                        <td class='text-center'>{$porcentajePago}</td>
                                                        <td class='text-end'>{$cantidad}</td>
                                                        <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                                                        <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                                                        <td class='text-center'>{$bodega}</td>
                                                        <td class='text-center'>{$iva}</td>
                                                        <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                                                    </tr>";
            $n++;
        }

        // AYUDANTE
        if (!empty($datos['protocoloExtendido']['cirujano_2']) || !empty($datos['protocoloExtendido']['primer_ayudante'])) {
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

                echo "<tr style='font-size: 12.5px;'>
                                                            <td class='text-center'>{$n}</td>
                                                            <td class='text-center'>{$codigo}</td>
                                                            <td>{$descripcion}</td>
                                                            <td class='text-center'>{$anestesia}</td>
                                                            <td class='text-center'>{$porcentajePago}</td>
                                                            <td class='text-end'>{$cantidad}</td>
                                                            <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                                                            <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                                                            <td class='text-center'>{$bodega}</td>
                                                            <td class='text-center'>{$iva}</td>
                                                            <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                                                        </tr>";
                $n++;
            }
        }

        // ANESTESIA
        foreach ($datos['anestesia'] as $a) {
            $codigo = $a['codigo'] ?? '';
            $descripcion = $a['nombre'] ?? '';
            $cantidad = (float)($a['tiempo'] ?? 0);
            $valorUnitario = (float)($a['valor2'] ?? 0);
            $subtotal = $cantidad * $valorUnitario;
            $total += $subtotal;
            $anestesia = 'SI';
            $porcentajePago = 100;
            $bodega = 0;
            $iva = 0;
            $montoTotal = $subtotal;

            echo "<tr style='font-size: 12.5px;'>
                                                        <td class='text-center'>{$n}</td>
                                                        <td class='text-center'>{$codigo}</td>
                                                        <td>{$descripcion}</td>
                                                        <td class='text-center'>{$anestesia}</td>
                                                        <td class='text-center'>{$porcentajePago}</td>
                                                        <td class='text-end'>{$cantidad}</td>
                                                        <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                                                        <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                                                        <td class='text-center'>{$bodega}</td>
                                                        <td class='text-center'>{$iva}</td>
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
                if (isset($item['litros']) && isset($item['tiempo']) && isset($item['valor2'])) {
                    $cantidad = (float)$item['tiempo'] * (float)$item['litros'] * 60;
                    $valorUnitario = (float)$item['valor2'];
                } else {
                    $cantidad = $item['cantidad'] ?? 1;
                    $valorUnitario = $item['precio'] ?? 0;
                }
                $subtotal = $valorUnitario * $cantidad;
                $bodega = 1;
                $iva = ($grupo === 'FARMACIA') ? 0 : 1;
                $montoTotal = $subtotal + ($iva ? $subtotal * 0.1 : 0);
                $total += $montoTotal;
                $anestesia = 'NO';
                $porcentajePago = 100;

                echo "<tr style='font-size: 12.5px;'>
                                                            <td class='text-center'>{$n}</td>
                                                            <td class='text-center'>{$codigo}</td>
                                                            <td>{$descripcion}</td>
                                                            <td class='text-center'>{$anestesia}</td>
                                                            <td class='text-center'>{$porcentajePago}</td>
                                                            <td class='text-end'>{$cantidad}</td>
                                                            <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                                                            <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                                                            <td class='text-center'>{$bodega}</td>
                                                            <td class='text-center'>{$iva}</td>
                                                            <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                                                        </tr>";
                $n++;
            }
        }

        // SERVICIOS INSTITUCIONALES (derechos)
        foreach ($datos['derechos'] as $servicio) {
            $codigo = $servicio['codigo'] ?? '';
            $descripcion = $servicio['detalle'] ?? '';
            $cantidad = $servicio['cantidad'] ?? 1;
            $valorUnitario = $servicio['precio_afiliacion'] ?? 0;
            $subtotal = $valorUnitario * $cantidad;
            $bodega = 0;
            $iva = 0;
            $montoTotal = $subtotal;
            $total += $montoTotal;
            $anestesia = 'NO';
            $porcentajePago = 100;

            echo "<tr style='font-size: 12.5px;'>
                                                        <td class='text-center'>{$n}</td>
                                                        <td class='text-center'>{$codigo}</td>
                                                        <td>{$descripcion}</td>
                                                        <td class='text-center'>{$anestesia}</td>
                                                        <td class='text-center'>{$porcentajePago}</td>
                                                        <td class='text-end'>{$cantidad}</td>
                                                        <td class='text-end'>" . number_format($valorUnitario, 2) . "</td>
                                                        <td class='text-end'>" . number_format($subtotal, 2) . "</td>
                                                        <td class='text-center'>{$bodega}</td>
                                                        <td class='text-center'>{$iva}</td>
                                                        <td class='text-end'>" . number_format($montoTotal, 2) . "</td>
                                                    </tr>";
            $n++;
        }
        ?>
        </tbody>
    </table>
</div>

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
    </div>
</div>

