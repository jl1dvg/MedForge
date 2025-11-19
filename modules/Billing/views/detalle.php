<?php
$detalle = $detalle ?? [];
$paciente = $detalle['paciente'] ?? [];
$metadata = $detalle['metadata'] ?? [];
$nombreCompleto = trim(($paciente['lname'] ?? '') . ' ' . ($paciente['lname2'] ?? '') . ' ' . ($paciente['fname'] ?? '') . ' ' . ($paciente['mname'] ?? ''));
$hcNumber = $paciente['hc_number'] ?? ($detalle['billing']['hc_number'] ?? '');
$afiliacion = strtoupper($paciente['afiliacion'] ?? '-');
$procedimientosPorGrupo = $detalle['procedimientosPorGrupo'] ?? [];
$subtotales = $detalle['subtotales'] ?? [];
$totalSinIVA = $detalle['totalSinIVA'] ?? 0;
$iva = $detalle['iva'] ?? 0;
$totalConIVA = $detalle['totalConIVA'] ?? 0;
$grupoClases = $detalle['grupoClases'] ?? [];
$sri = $detalle['sri'] ?? null;
$estadoSri = strtoupper($sri['estado'] ?? 'PENDIENTE');
$badgeSri = match ($estadoSri) {
    'AUTORIZADO' => 'bg-success',
    'SIMULADO', 'ENVIADO' => 'bg-info',
    'ERROR' => 'bg-danger',
    'PENDIENTE' => 'bg-secondary',
    default => 'bg-warning text-dark',
};
$alertSri = match ($estadoSri) {
    'AUTORIZADO' => 'alert-success',
    'SIMULADO', 'ENVIADO' => 'alert-info',
    'ERROR' => 'alert-danger',
    default => 'alert-secondary',
};
?>

<section class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Detalle de factura</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/billing">Facturación</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Factura <?= htmlspecialchars($detalle['billing']['form_id'] ?? '') ?></li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="ms-auto">
            <a href="/public/index.php/billing/excel?form_id=<?= urlencode($detalle['billing']['form_id'] ?? '') ?>&grupo=IESS" class="btn btn-success">
                <i class="fa fa-file-excel-o me-2"></i>Descargar Excel
            </a>
        </div>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-body">
                    <div class="row invoice-info mb-3">
                        <div class="col-md-6 invoice-col">
                            <strong>Desde</strong>
                            <address>
                                <strong class="text-blue fs-24">Clínica Internacional de Visión del Ecuador - CIVE</strong><br>
                                <span class="d-inline">Parroquia satélite La Aurora de Daule, km 12 Av. León Febres-Cordero.</span><br>
                                <strong>Teléfono: (04) 372-9340 &nbsp;&nbsp;&nbsp; Email: info@cive.ec</strong>
                            </address>
                        </div>
                        <div class="col-md-6 invoice-col text-end">
                            <strong>Paciente</strong>
                            <address>
                                <strong class="text-blue fs-24"><?= htmlspecialchars($nombreCompleto ?: 'Paciente sin nombre') ?></strong><br>
                                HC: <span class="badge bg-primary"><?= htmlspecialchars($hcNumber ?: 'N/D') ?></span><br>
                                Afiliación: <span class="badge bg-info"><?= htmlspecialchars($afiliacion) ?></span><br>
                                <?php if (!empty($paciente['ci'])): ?>
                                    Cédula: <?= htmlspecialchars($paciente['ci']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($paciente['fecha_nacimiento'])): ?>
                                    F. Nacimiento: <?= date('d/m/Y', strtotime($paciente['fecha_nacimiento'])) ?><br>
                                <?php endif; ?>
                            </address>
                        </div>
                        <div class="col-sm-12 invoice-col mb-15">
                            <div class="invoice-details row no-margin">
                                <div class="col-md-6 col-lg-3"><b>Pedido:</b> <?= htmlspecialchars($metadata['codigoDerivacion'] ?? '--') ?></div>
                                <div class="col-md-6 col-lg-3"><b>Fecha Registro:</b> <?= !empty($metadata['fecha_registro']) ? date('d/m/Y', strtotime($metadata['fecha_registro'])) : '--' ?></div>
                                <div class="col-md-6 col-lg-3"><b>Fecha Vigencia:</b> <?= !empty($metadata['fecha_vigencia']) ? date('d/m/Y', strtotime($metadata['fecha_vigencia'])) : '--' ?></div>
                                <div class="col-md-6 col-lg-3"><b>Médico:</b> <?= htmlspecialchars($metadata['doctor'] ?? '--') ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if ($sri): ?>
                        <div class="alert <?= $alertSri ?> d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            <div>
                                <span class="badge <?= $badgeSri ?> me-2"><?= htmlspecialchars($estadoSri) ?></span>
                                <strong>Integración SRI</strong>
                                <?php if (!empty($sri['numeroAutorizacion'])): ?>
                                    <span class="d-block small text-muted">Autorización: <?= htmlspecialchars($sri['numeroAutorizacion']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($sri['claveAcceso'])): ?>
                                    <span class="d-block small text-muted">Clave de acceso: <?= htmlspecialchars($sri['claveAcceso']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-md-end">
                                <?php if (!empty($sri['ultimoEnvio'])): ?>
                                    <div class="small text-muted">Último envío: <?= date('d/m/Y H:i', strtotime($sri['ultimoEnvio'])) ?></div>
                                <?php endif; ?>
                                <div class="small text-muted">Intentos: <?= (int)($sri['intentos'] ?? 0) ?></div>
                            </div>
                        </div>
                        <?php if (!empty($sri['errores'])): ?>
                            <details class="mb-3">
                                <summary class="fw-bold text-danger">Ver errores reportados</summary>
                                <pre class="bg-dark text-white p-3 rounded mt-2" style="white-space: pre-wrap;"><?= htmlspecialchars($sri['errores']) ?></pre>
                            </details>
                        <?php endif; ?>
                        <?php if (!empty($sri['respuesta'])): ?>
                            <details class="mb-3">
                                <summary class="fw-bold text-primary">Ver respuesta del SRI</summary>
                                <pre class="bg-light border p-3 rounded mt-2" style="white-space: pre-wrap;"><?= htmlspecialchars($sri['respuesta']) ?></pre>
                            </details>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>Integración pendiente.</strong> Esta factura aún no ha sido enviada al SRI con las credenciales configuradas.
                        </div>
                    <?php endif; ?>

                    <?php foreach ($procedimientosPorGrupo as $grupo => $items): ?>
                        <?php if (empty($items)) continue; ?>
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered <?= htmlspecialchars($grupoClases[$grupo] ?? '') ?>">
                                <thead>
                                <tr>
                                    <th colspan="5" class="text-uppercase">Grupo: <?= htmlspecialchars($grupo) ?></th>
                                </tr>
                                <tr>
                                    <th class="w-25">Código</th>
                                    <th>Descripción</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($items as $item): ?>
                                    <?php
                                    $cantidad = (float)($item['cantidad'] ?? 1);
                                    $precio = (float)($item['proc_precio'] ?? $item['precio'] ?? 0);
                                    $subtotal = $cantidad * $precio;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['proc_codigo'] ?? $item['codigo'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars($item['proc_detalle'] ?? $item['detalle'] ?? $item['nombre'] ?? '—') ?></td>
                                        <td class="text-end"><?= number_format($cantidad, 2) ?></td>
                                        <td class="text-end">$<?= number_format($precio, 2) ?></td>
                                        <td class="text-end">$<?= number_format($subtotal, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                <tr>
                                    <th colspan="4" class="text-end">Subtotal</th>
                                    <th class="text-end">$<?= number_format($subtotales[$grupo] ?? 0, 2) ?></th>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endforeach; ?>

                    <div class="row">
                        <div class="col-md-6 offset-md-6">
                            <table class="table table-bordered">
                                <tbody>
                                <tr>
                                    <th class="text-end">Subtotal</th>
                                    <td class="text-end">$<?= number_format($totalSinIVA, 2) ?></td>
                                </tr>
                                <tr>
                                    <th class="text-end">IVA (15%)</th>
                                    <td class="text-end">$<?= number_format($iva, 2) ?></td>
                                </tr>
                                <tr class="bg-primary-light">
                                    <th class="text-end">Total</th>
                                    <td class="text-end fw-bold">$<?= number_format($totalConIVA, 2) ?></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>
