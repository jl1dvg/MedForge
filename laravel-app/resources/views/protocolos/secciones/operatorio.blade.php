<?php
$protocoloData = $protocolo ?? [];
?>
<div class="form-group">
    <label for="imagen_link">Enlace de imagen</label>
    <input type="url" name="imagen_link" id="imagen_link" class="form-control"
           value="<?= htmlspecialchars($protocoloData['imagen_link'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
           placeholder="https://ejemplo.com/imagen.jpg">
</div>

<div class="form-group">
    <label for="file" class="form-label">Seleccionar archivo</label>
    <input type="file" name="imagen_file" id="file" class="form-control">
</div>

<div class="form-group">
    <label for="operatorio">Descripción operatoria</label>
    <div id="operatorio" class="form-control operatorio-editor" contenteditable="true" style="white-space: pre-wrap;"
         placeholder="Describir aquí los pasos operatorios">
        <?= htmlspecialchars($protocoloData['operatorio'] ?? '', ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div id="autocomplete-insumos" class="autocomplete-box"></div>
</div>
