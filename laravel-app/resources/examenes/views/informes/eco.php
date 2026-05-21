<?php
/** @var array<int, array{id: string, label: string, text: string}> $checkboxes */
?>
<div class="informe-template" data-informe-template="eco">
    <?php include __DIR__ . '/_firmante.php'; ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="inputOD">OD</label>
            <textarea id="inputOD" class="form-control" rows="4"></textarea>
            <div class="mt-2">
                <?php
                $eye = 'OD';
                $targetId = 'inputOD';
                include __DIR__ . '/_checkboxes.php';
                ?>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="inputOI">OI</label>
            <textarea id="inputOI" class="form-control" rows="4"></textarea>
            <div class="mt-2">
                <?php
                $eye = 'OI';
                $targetId = 'inputOI';
                include __DIR__ . '/_checkboxes.php';
                ?>
            </div>
        </div>
    </div>
</div>
