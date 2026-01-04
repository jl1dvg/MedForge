<?php if (!empty($assignableUsers) && is_array($assignableUsers)): ?>
    <?php foreach ($assignableUsers as $user): ?>
        <option value="<?= (int) ($user['id'] ?? 0) ?>">
            <?= htmlspecialchars($user['nombre'] ?? ($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </option>
    <?php endforeach; ?>
<?php endif; ?>
