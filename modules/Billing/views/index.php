<section class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Facturación</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Facturas generadas</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="ms-auto">
            <a href="/public/index.php/billing/exportar_mes" class="btn btn-primary">
                <i class="fa fa-file-excel-o me-2"></i>Exportar mes
            </a>
        </div>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Resumen de facturas</h4>
                    <div class="box-controls pull-right">
                        <form method="get" class="d-flex align-items-center" style="gap: .75rem;">
                            <label class="mb-0 fw-bold" for="mes">
                                <i class="mdi mdi-calendar"></i>
                                Mes
                            </label>
                            <input type="month"
                                   id="mes"
                                   name="mes"
                                   value="<?= htmlspecialchars($mesSeleccionado ?? '') ?>"
                                   class="form-control"
                                   style="max-width: 200px;">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="mdi mdi-magnify"></i>
                            </button>
                            <?php if (!empty($mesSeleccionado)): ?>
                                <a class="btn btn-light" href="/billing">
                                    <i class="mdi mdi-filter-remove"></i>
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="bg-primary-light">
                            <tr>
                                <th>Form ID</th>
                                <th>HC</th>
                                <th>Paciente</th>
                                <th>Afiliación</th>
                                <th>Fecha</th>
                                <th>Estado SRI</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($facturas)): ?>
                                <?php foreach ($facturas as $factura): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-info-light text-primary fw-600">
                                                <?= htmlspecialchars($factura['form_id']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($factura['hc_number']) ?></td>
                                        <td><?= htmlspecialchars($factura['paciente']['nombre'] ?? '—') ?></td>
                                        <td><?= htmlspecialchars(strtoupper($factura['paciente']['afiliacion'] ?? '—')) ?></td>
                                        <td><?= $factura['fecha'] ? date('d/m/Y', strtotime($factura['fecha'])) : '—' ?></td>
                                        <td>
                                            <?php
                                            $sri = $factura['sri'] ?? null;
                                            $estadoSri = strtoupper($sri['estado'] ?? 'PENDIENTE');
                                            $badgeClass = match ($estadoSri) {
                                                'AUTORIZADO' => 'bg-success',
                                                'SIMULADO', 'ENVIADO' => 'bg-info',
                                                'ERROR' => 'bg-danger',
                                                'PENDIENTE' => 'bg-secondary',
                                                default => 'bg-warning text-dark',
                                            };
                                            $claveAcceso = $sri['claveAcceso'] ?? null;
                                            $ultimaParteClave = $claveAcceso ? substr($claveAcceso, -8) : null;
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($estadoSri) ?></span>
                                            <?php if ($ultimaParteClave): ?>
                                                <div class="text-muted small">Clave …<?= htmlspecialchars($ultimaParteClave) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($sri['ultimoEnvio'])): ?>
                                                <div class="text-muted small">Env. <?= date('d/m/Y H:i', strtotime($sri['ultimoEnvio'])) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group">
                                                <a class="btn btn-sm btn-outline-primary"
                                                   href="/billing/detalle?form_id=<?= urlencode($factura['form_id']) ?>">
                                                    <i class="mdi mdi-eye-outline"></i> Ver
                                                </a>
                                                <a class="btn btn-sm btn-success"
                                                   href="/public/index.php/billing/excel?form_id=<?= urlencode($factura['form_id']) ?>&grupo=IESS">
                                                    <i class="fa fa-file-excel-o"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        No se encontraron facturas para los filtros seleccionados.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
