<?php
/** @var array<int, array<string, mixed>> $usuariosFirmantes */
?>
<div class="mb-3">
    <label class="form-label" for="firmante_id">Firma del informe</label>
    <select id="firmante_id" class="form-select">
        <option value="">Seleccionar médico</option>
        <?php foreach ($usuariosFirmantes as $usuario): ?>
            <option value="<?= (int) ($usuario['id'] ?? 0) ?>">
                <?= htmlspecialchars((string) ($usuario['nombre'] ?? ($usuario['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
