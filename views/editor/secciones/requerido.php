<?php if (isset($protocolo)): ?>
    <!-- Campo oculto ID -->
    <input type="hidden" name="id" value="<?= htmlspecialchars($protocolo['id']) ?>">

    <!-- Primera fila: Nombre Corto y Título -->
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="cirugia">Nombre Corto del
                    Procedimiento</label>
                <input type="text" name="cirugia" id="cirugia"
                       class="form-control"
                       value="<?= htmlspecialchars($protocolo['cirugia']) ?>"
                       placeholder="Ej: faco, pterigion, avastin"
                       required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="membrete">Título del Protocolo</label>
                <input type="text" name="membrete" id="membrete"
                       class="form-control"
                       value="<?= htmlspecialchars($protocolo['membrete']) ?>"
                       placeholder="Ej: Facoemulsificación con LIO"
                       required>
            </div>
        </div>
    </div>

    <!-- Segunda fila: Categoría y Horas -->
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="categoriaQX">Categoría</label>
                <select name="categoriaQX" id="categoriaQX" class="form-select" required>
                    <option value="" disabled <?= empty($protocolo['categoria']) ? 'selected' : '' ?>>Selecciona una
                        categoría
                    </option>
                    <option value="Catarata" <?= ($protocolo['categoria'] == 'Catarata') ? 'selected' : '' ?>>
                        Catarata
                    </option>
                    <option value="Conjuntiva" <?= ($protocolo['categoria'] == 'Conjuntiva') ? 'selected' : '' ?>>
                        Conjuntiva
                    </option>
                    <option value="Estrabismo" <?= ($protocolo['categoria'] == 'Estrabismo') ? 'selected' : '' ?>>
                        Estrabismo
                    </option>
                    <option value="Glaucoma" <?= ($protocolo['categoria'] == 'Glaucoma') ? 'selected' : '' ?>>
                        Glaucoma
                    </option>
                    <option value="Implantes secundarios" <?= ($protocolo['categoria'] == 'Implantes secundarios') ? 'selected' : '' ?>>
                        Implantes secundarios
                    </option>
                    <option value="Inyecciones" <?= ($protocolo['categoria'] == 'Inyecciones') ? 'selected' : '' ?>>
                        Inyecciones
                    </option>
                    <option value="Oculoplastica" <?= ($protocolo['categoria'] == 'Oculoplastica') ? 'selected' : '' ?>>
                        Oculoplastica
                    </option>
                    <option value="Refractiva" <?= ($protocolo['categoria'] == 'Refractiva') ? 'selected' : '' ?>>
                        Refractiva
                    </option>
                    <option value="Retina" <?= ($protocolo['categoria'] == 'Retina') ? 'selected' : '' ?>>
                        Retina
                    </option>
                    <option value="Traumatismo Ocular" <?= ($protocolo['categoria'] == 'Traumatismo Ocular') ? 'selected' : '' ?>>
                        Traumatismo Ocular
                    </option>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="horas">Duración Estimada (horas)</label>
                <input type="number" step="0.1" name="horas"
                       id="horas" class="form-control"
                       value="<?= htmlspecialchars($protocolo['horas']) ?>"
                       placeholder="Ej: 1.5" required>
            </div>
        </div>
    </div>

    <!-- Tercera fila: Dieresis y Exposición -->
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="dieresis">Dieresis</label>
                <input type="text" name="dieresis" id="dieresis"
                       class="form-control"
                       value="<?= htmlspecialchars($protocolo['dieresis']) ?>"
                       placeholder="Ej: Conjuntival, Escleral"
                       required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="exposicion">Exposición</label>
                <input type="text" name="exposicion"
                       id="exposicion"
                       class="form-control"
                       value="<?= htmlspecialchars($protocolo['exposicion']) ?>"
                       placeholder="Ej: Cámara anterior" required>
            </div>
        </div>
    </div>

    <!-- Cuarta fila: Hallazgos -->
    <div class="row">
        <div class="col-md-12">
            <div class="form-group">
                <label for="hallazgo">Hallazgos
                    Intraoperatorios</label>
                <input type="text" name="hallazgo" id="hallazgo"
                       class="form-control"
                       value="<?= htmlspecialchars($protocolo['hallazgo']) ?>"
                       placeholder="Ej: Opacidad de cápsula posterior"
                       required>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="codigos">Códigos Quirúrgicos</label>
        <div id="contenedor-codigos">
            <?php foreach ($codigos as $index => $codigo): ?>
                <div class="row mb-2 codigo-item align-items-center">
                    <div class="col-md-10">
                        <input type="text" name="codigos[]" class="form-control"
                               value="<?= htmlspecialchars($codigo['nombre']) ?>" placeholder="Código quirúrgico">
                        <input type="hidden" name="lateralidades[]"
                               value="<?= htmlspecialchars($codigo['lateralidad']) ?>">
                        <input type="hidden" name="selectores[]" value="<?= htmlspecialchars($codigo['selector']) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm eliminar-codigo">Eliminar</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-primary mt-2" id="agregar-codigo">+ Agregar Código</button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let indexCodigo = <?= count($codigos) ?>;

            document.getElementById('agregar-codigo').addEventListener('click', function () {
                const container = document.getElementById('contenedor-codigos');
                const row = document.createElement('div');
                row.className = 'row mb-2 codigo-item align-items-center';
                row.innerHTML = `
                    <div class="col-md-10">
                        <input type="text" name="codigos[]" class="form-control" placeholder="Código quirúrgico">
                        <input type="hidden" name="lateralidades[]" value="#select2-consultasubsecuente-procedimientoprotocolo-${indexCodigo}-lateralidadprocedimiento-container">
                        <input type="hidden" name="selectores[]" value="#select2-consultasubsecuente-procedimientoprotocolo-${indexCodigo}-procinterno-container">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm eliminar-codigo">Eliminar</button>
                    </div>
                `;
                container.appendChild(row);
                indexCodigo++;
            });

            document.getElementById('contenedor-codigos').addEventListener('click', function (e) {
                if (e.target.classList.contains('eliminar-codigo')) {
                    e.target.closest('.codigo-item').remove();
                }
            });
        });
    </script>
<?php endif; ?>