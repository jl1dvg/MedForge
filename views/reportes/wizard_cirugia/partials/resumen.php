<h6>Resumen Final</h6>
<section>
    <div class="alert alert-info">Este es un resumen de los datos ingresados. Revise antes
        de marcar como revisado.
    </div>
    <div id="resumenFinal">
        <ul class="list-group">
            <li class="list-group-item">
                <strong>Paciente:</strong> <?php echo htmlspecialchars($cirugia->fname . ' ' . $cirugia->mname . ' ' . $cirugia->lname . ' ' . $cirugia->lname2); ?>
            </li>
            <li class="list-group-item"><strong>Fecha de
                    Nacimiento:</strong> <?php echo htmlspecialchars($cirugia->fecha_nacimiento); ?>
            </li>
            <li class="list-group-item">
                <strong>Afiliación:</strong> <?php echo htmlspecialchars($cirugia->afiliacion); ?>
            </li>
            <li class="list-group-item">
                <strong>Procedimientos:</strong> <?= htmlspecialchars(implode(', ', array_map(fn($p) => $p['procInterno'], $procedimientosArray ?? []))); ?>
            </li>
            <li class="list-group-item">
                <strong>Diagnósticos:</strong> <?= htmlspecialchars(implode(', ', array_map(fn($d) => $d['idDiagnostico'], $diagnosticosArray ?? []))); ?>
            </li>
            <li class="list-group-item">
                <strong>Lateralidad:</strong> <?= htmlspecialchars($cirugia->lateralidad); ?>
            </li>
            <li class="list-group-item"><strong>Cirujano
                    Principal:</strong> <?= htmlspecialchars($cirugia->cirujano_1); ?></li>
            <li class="list-group-item">
                <strong>Anestesiólogo:</strong> <?= htmlspecialchars($cirugia->anestesiologo); ?>
            </li>
            <li class="list-group-item"><strong>Fecha/Hora de
                    Inicio:</strong> <?= htmlspecialchars($cirugia->fecha_inicio . ' ' . $cirugia->hora_inicio); ?>
            </li>
            <li class="list-group-item"><strong>Fecha/Hora de
                    Fin:</strong> <?= htmlspecialchars($cirugia->fecha_fin . ' ' . $cirugia->hora_fin); ?>
            </li>
            <li class="list-group-item"><strong>Tipo de
                    Anestesia:</strong> <?= htmlspecialchars($cirugia->tipo_anestesia); ?>
            </li>
        </ul>
    </div>
    <div class="form-group">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="status"
                   id="statusCheckbox"
                   value="1" <?= ($cirugia->status == 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="statusCheckbox">
                Marcar como revisado
            </label>
        </div>
    </div>
</section>