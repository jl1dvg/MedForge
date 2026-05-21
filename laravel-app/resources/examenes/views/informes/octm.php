<?php
/** @var array<int, array{id: string, label: string, text: string}> $checkboxes */
?>
<div class="informe-template" data-informe-template="octm">
    <?php include __DIR__ . '/_firmante.php'; ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="inputOD">Grosor foveal OD (um)</label>
            <input type="text" id="inputOD" class="form-control" maxlength="3" tabindex="1">
            <label class="form-label mt-2" for="textOD">OD</label>
            <textarea id="textOD" class="form-control" rows="4" tabindex="3"></textarea>
            <div class="mt-2">
                <?php
                $eye = 'OD';
                $targetId = 'textOD';
                include __DIR__ . '/_checkboxes.php';
                ?>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="inputOI">Grosor foveal OI (um)</label>
            <input type="text" id="inputOI" class="form-control" maxlength="3" tabindex="2">
            <label class="form-label mt-2" for="textOI">OI</label>
            <textarea id="textOI" class="form-control" rows="4" tabindex="4"></textarea>
            <div class="mt-2">
                <?php
                $eye = 'OI';
                $targetId = 'textOI';
                include __DIR__ . '/_checkboxes.php';
                ?>
            </div>
        </div>
    </div>
</div>
