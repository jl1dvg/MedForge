<h6>Fechas, Horas y Tipo de Anestesia</h6>
<section>
    <!-- Fecha de Inicio -->
    <div class="form-group">
        <label for="fecha_inicio" class="form-label">Fecha de Inicio :</label>
        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio"
               value="<?php echo htmlspecialchars($cirugia->fecha_inicio ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <!-- Hora de Inicio -->
    <div class="form-group">
        <label for="hora_inicio" class="form-label">Hora de Inicio :</label>
        <input type="time" class="form-control" id="hora_inicio" name="hora_inicio"
               value="<?php echo htmlspecialchars($cirugia->hora_inicio ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <!-- Fecha de Fin -->
    <div class="form-group">
        <label for="fecha_fin" class="form-label">Fecha de Fin :</label>
        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin"
               value="<?php echo htmlspecialchars($cirugia->fecha_fin ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <!-- Hora de Fin -->
    <div class="form-group">
        <label for="hora_fin" class="form-label">Hora de Fin :</label>
        <input type="time" class="form-control" id="hora_fin" name="hora_fin"
               value="<?php echo htmlspecialchars($cirugia->hora_fin ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <!-- Tipo de Anestesia -->
    <div class="form-group">
        <label for="tipo_anestesia" class="form-label">Tipo de Anestesia :</label>
        <select class="form-select" id="tipo_anestesia" name="tipo_anestesia">
            <option value="GENERAL" <?= ($cirugia->tipo_anestesia == 'GENERAL') ? 'selected' : '' ?>>
                GENERAL
            </option>
            <option value="LOCAL" <?= ($cirugia->tipo_anestesia == 'LOCAL') ? 'selected' : '' ?>>
                LOCAL
            </option>
            <option value="OTROS" <?= ($cirugia->tipo_anestesia == 'OTROS') ? 'selected' : '' ?>>
                OTROS
            </option>
            <option value="REGIONAL" <?= ($cirugia->tipo_anestesia == 'REGIONAL') ? 'selected' : '' ?>>
                REGIONAL
            </option>
            <option value="SEDACION" <?= ($cirugia->tipo_anestesia == 'SEDACION') ? 'selected' : '' ?>>
                SEDACION
            </option>
        </select>
    </div>
</section>