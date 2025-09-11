<?php if (isset($protocolo)): ?>
    <!-- Enlace de Imagen -->
    <div class="form-group">
        <label for="imagen_link">Enlace de
            Imagen</label>
        <input type="url" name="imagen_link" id="imagen_link" class="form-control"
               value="<?= htmlspecialchars($protocolo['imagen_link']) ?>"
               placeholder="https://ejemplo.com/imagen.jpg">
    </div>

    <!-- Cargar Archivo de Imagen -->
    <div class="form-group">
        <label for="file" class="form-label">Seleccionar Archivo</label>
        <input type="file" name="imagen_file" id="file" class="form-control">
    </div>

    <!-- Texto Operatorio -->
    <input type="hidden" name="operatorio" id="operatorioInput" value="<?= htmlspecialchars($protocolo['operatorio']) ?>">    <div class="form-group">
        <label for="operatorio">Descripción Operatorio</label>
        <div id="operatorio" class="form-control operatorio-editor" contenteditable="true" style="white-space: pre-wrap;"
             placeholder="Describir aquí los pasos operatorios"><?= htmlspecialchars($protocolo['operatorio']) ?></div>
        <div id="autocomplete-insumos"
             class="autocomplete-box"></div>
    </div>
<?php endif; ?>