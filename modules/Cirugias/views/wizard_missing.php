<div class="content-header">
    <div class="alert alert-danger w-100">
        <strong>Protocolo no disponible.</strong>
        <?php if (!empty($formId) && !empty($hcNumber)): ?>
            No se encontró información para el protocolo <code><?= htmlspecialchars($formId) ?></code> del paciente <code><?= htmlspecialchars($hcNumber) ?></code>.
        <?php else: ?>
            Faltan parámetros para cargar el protocolo solicitado.
        <?php endif; ?>
    </div>
</div>
