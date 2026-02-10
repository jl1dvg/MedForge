<?php
/** @var array<int, array<string, mixed>> $usuariosFirmantes */
?>
<div class="mb-3">
    <label class="form-label" for="firmante_id">Firma del informe</label>
    <select id="firmante_id" class="form-select">
        <option value="">Seleccionar médico</option>
        <?php foreach ($usuariosFirmantes as $usuario): ?>
            <?php
            $userId = (int) ($usuario['id'] ?? 0);
            $defaultId = isset($firmanteDefaultId) ? (int) $firmanteDefaultId : 0;
            $selected = ($defaultId > 0 && $userId === $defaultId) ? 'selected' : '';
            ?>
            <option value="<?= $userId ?>" <?= $selected ?>>
                <?= htmlspecialchars((string) ($usuario['nombre'] ?? ($usuario['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
