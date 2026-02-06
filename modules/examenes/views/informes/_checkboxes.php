<?php
/**
 * @var array<int, array{id: string, label: string, text: string}> $checkboxes
 * @var string $eye
 * @var string $targetId
 */
?>
<?php if (!empty($checkboxes)): ?>
    <div class="row g-2">
        <?php foreach ($checkboxes as $item): ?>
            <?php
            $id = 'checkbox' . $eye . '_' . ($item['id'] ?? '');
            $label = (string) ($item['label'] ?? '');
            $text = (string) ($item['text'] ?? '');
            ?>
            <div class="col-12 form-check">
                <input class="form-check-input informe-checkbox"
                       type="checkbox"
                       id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                       data-target="<?= htmlspecialchars($targetId, ENT_QUOTES, 'UTF-8') ?>"
                       data-text="<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>">
                <label class="form-check-label" for="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
