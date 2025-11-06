<!-- Legacy billing report view relocated under modules/Billing/views/informes. -->
<div class="col-md-6 invoice-col">
    <strong>Desde</strong>
    <address>
        <strong class="text-blue fs-24">Clínica Internacional de Visión del Ecuador
            -
            CIVE</strong><br>
        <span class="d-inline">Parroquia satélite La Aurora de Daule, km 12 Av. León Febres-Cordero.</span><br>
        <strong>Teléfono: (04) 372-9340 &nbsp;&nbsp;&nbsp; Email:
            info@cive.ec</strong>
    </address>
</div>
<div class="col-md-6 invoice-col text-end">
    <strong>Paciente</strong>
    <address>
        <strong class="text-blue fs-24"><?= htmlspecialchars($nombreCompleto) ?></strong><br>
        HC: <span class="badge bg-primary"><?= htmlspecialchars($hcNumber) ?></span><br>
        Afiliación: <span class="badge bg-info"><?= $afiliacion ?></span><br>
        <?php if (!empty($paciente['ci'])): ?>
            Cédula: <?= htmlspecialchars($paciente['ci']) ?><br>
        <?php endif; ?>
        <?php if (!empty($paciente['fecha_nacimiento'])): ?>
            F. Nacimiento: <?= date('d/m/Y', strtotime($paciente['fecha_nacimiento'])) ?>
            <br>
        <?php endif; ?>
    </address>
</div>
<div class="col-sm-12 invoice-col mb-15">
    <div class="invoice-details row no-margin">
        <div class="col-md-6 col-lg-3"><b>Pedido:</b> <?= $codigoDerivacion ?></div>
        <div class="col-md-6 col-lg-3"><b>Fecha
                Registro:</b> <?= !empty($fecha_registro) ? date('d/m/Y', strtotime($fecha_registro ?? '')) : '--' ?>
        </div>
        <div class="col-md-6 col-lg-3"><b>Fecha
                Vigencia:</b> <?= !empty($fecha_vigencia) ? date('d/m/Y', strtotime($fecha_vigencia)) : '--' ?>
        </div>
        <div class="col-md-6 col-lg-3">
            <b>Médico:</b> <?= htmlspecialchars($doctor) ?>
        </div>
    </div>
</div>