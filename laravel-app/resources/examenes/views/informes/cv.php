<?php
/** @var array<int, array{id: string, label: string, text: string}> $checkboxes */
?>
<div class="informe-template" data-informe-template="cv">
    <?php include __DIR__ . '/_firmante.php'; ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="inputOD">OD</label>
            <textarea id="inputOD" class="form-control" rows="4"></textarea>
            <div id="checkboxContainerOD" class="row g-2 mt-2">
                <?php foreach ($checkboxes as $item): ?>
                    <?php
                    $id = 'checkboxOD_' . ($item['id'] ?? '');
                    $label = (string) ($item['label'] ?? '');
                    $text = (string) ($item['text'] ?? '');
                    ?>
                    <div class="col-12 form-check">
                        <input class="form-check-input informe-checkbox-cv"
                               type="checkbox"
                               id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                               data-eye="OD"
                               data-item-id="<?= htmlspecialchars((string) ($item['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                               data-text="<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>">
                        <label class="form-check-label" for="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="inputOI">OI</label>
            <textarea id="inputOI" class="form-control" rows="4"></textarea>
            <div id="checkboxContainerOI" class="row g-2 mt-2">
                <?php foreach ($checkboxes as $item): ?>
                    <?php
                    $id = 'checkboxOI_' . ($item['id'] ?? '');
                    $label = (string) ($item['label'] ?? '');
                    $text = (string) ($item['text'] ?? '');
                    ?>
                    <div class="col-12 form-check">
                        <input class="form-check-input informe-checkbox-cv"
                               type="checkbox"
                               id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                               data-eye="OI"
                               data-item-id="<?= htmlspecialchars((string) ($item['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                               data-text="<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>">
                        <label class="form-check-label" for="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
