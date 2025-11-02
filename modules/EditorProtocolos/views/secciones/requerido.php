<?php
$protocoloData = $protocolo ?? [];
?>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="cirugia">Nombre corto del procedimiento</label>
            <input type="text" name="cirugia" id="cirugia" class="form-control"
                   value="<?= htmlspecialchars($protocoloData['cirugia'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Ej: faco, pterigion, avastin" required>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="membrete">Título del protocolo</label>
            <input type="text" name="membrete" id="membrete" class="form-control"
                   value="<?= htmlspecialchars($protocoloData['membrete'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Ej: Facoemulsificación con LIO" required>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="categoriaQX">Categoría</label>
            <?php $categoriaSeleccionada = $protocoloData['categoria'] ?? ''; ?>
            <select name="categoriaQX" id="categoriaQX" class="form-select" required>
                <option value="" disabled <?= $categoriaSeleccionada === '' ? 'selected' : '' ?>>Selecciona una categoría</option>
                <?php
                $categorias = [
                    'Catarata', 'Conjuntiva', 'Estrabismo', 'Glaucoma', 'Implantes secundarios',
                    'Inyecciones', 'Oculoplastica', 'Refractiva', 'Retina', 'Traumatismo Ocular'
                ];
                foreach ($categorias as $categoria):
                    $selected = $categoriaSeleccionada === $categoria ? 'selected' : '';
                    ?>
                    <option value="<?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                        <?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="horas">Duración estimada (horas)</label>
            <input type="number" step="0.1" name="horas" id="horas" class="form-control"
                   value="<?= htmlspecialchars($protocoloData['horas'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Ej: 1.5" required>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label for="dieresis">Diéresis</label>
            <textarea name="dieresis" id="dieresis" class="form-control" rows="3"
                      placeholder="Describe la diéresis realizada"><?= htmlspecialchars($protocoloData['dieresis'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="exposicion">Exposición</label>
            <textarea name="exposicion" id="exposicion" class="form-control" rows="3"
                      placeholder="Describe la exposición"><?= htmlspecialchars($protocoloData['exposicion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label for="hallazgo">Hallazgo</label>
            <textarea name="hallazgo" id="hallazgo" class="form-control" rows="3"
                      placeholder="Describe los hallazgos"><?= htmlspecialchars($protocoloData['hallazgo'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
</div>

<div class="form-group">
    <label for="codigos">Códigos quirúrgicos</label>
    <div class="table-responsive">
        <table id="tablaCodigos" class="table table-bordered">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Lateralidad</th>
                <th>Selector</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($codigos as $codigo): ?>
                <tr>
                    <td><input type="text" class="form-control" name="codigos[]" value="<?= htmlspecialchars($codigo['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td><input type="text" class="form-control" name="lateralidades[]" value="<?= htmlspecialchars($codigo['lateralidad'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td><input type="text" class="form-control" name="selectores[]" value="<?= htmlspecialchars($codigo['selector'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td><button type="button" class="btn btn-danger remove-codigo"><i class="fa fa-trash"></i></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="btn btn-primary" id="agregar-codigo"><i class="fa fa-plus"></i> Agregar código</button>
    </div>
</div>
