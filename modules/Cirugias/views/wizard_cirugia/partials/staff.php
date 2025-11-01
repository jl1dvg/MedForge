<?php
function equalsIgnoreCase($a, $b)
{
    return strtolower(trim($a ?? '')) === strtolower(trim($b ?? ''));
}

?>
<h6>Staff Quirúrgico</h6>
<section>
    <div class="row">
        <!-- Cirujano Principal -->
        <div class="col-md-6">
            <div class="form-group">
                <label for="mainSurgeon" class="form-label">Cirujano Principal :</label>
                <select class="form-select" id="mainSurgeon" name="cirujano_1"
                        data-placeholder="Escoja el Cirujano Principal">
                    <option value="" <?= empty($cirugia->cirujano_1) ? 'selected' : '' ?>></option>
                    <?php foreach ($cirujanos['Cirujano Oftalmólogo'] as $nombre): ?>
                        <option value="<?= htmlspecialchars($nombre) ?>" <?= equalsIgnoreCase($nombre, $cirugia->cirujano_1) ? 'selected' : '' ?>>
                            <?= strtoupper(htmlspecialchars($nombre)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <!-- Cirujano Asistente -->
        <div class="col-md-6">
            <div class="form-group">
                <label for="assistantSurgeon" class="form-label">Cirujano Asistente
                    :</label>
                <select class="form-select" id="assistantSurgeon" name="cirujano_2"
                        data-placeholder="Escoja el Cirujano 2">
                    <option value="" <?= empty($cirugia->cirujano_2) ? 'selected' : '' ?>></option>
                    <?php foreach ($cirujanos['Cirujano Oftalmólogo'] as $nombre): ?>
                        <option value="<?= htmlspecialchars($nombre) ?>" <?= equalsIgnoreCase($nombre, $cirugia->cirujano_2) ? 'selected' : '' ?>>
                            <?= strtoupper(htmlspecialchars($nombre)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Primer Ayudante -->
        <div class="col-md-6">
            <div class="form-group">
                <label for="primerAyudante" class="form-label">Primer Ayudante :</label>
                <select class="form-select" id="primerAyudante" name="primer_ayudante">
                    <option value="" <?= empty($cirugia->primer_ayudante) ? 'selected' : '' ?>></option>
                    <?php foreach ($cirujanos['Cirujano Oftalmólogo'] as $nombre): ?>
                        <option value="<?= htmlspecialchars($nombre) ?>" <?= equalsIgnoreCase($nombre, $cirugia->primer_ayudante) ? 'selected' : '' ?>>
                            <?= strtoupper(htmlspecialchars($nombre)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <!-- Segundo Ayudante -->
        <div class="col-md-6">
            <div class="form-group">
                <label for="segundoAyudante" class="form-label">Segundo Ayudante :</label>
                <select class="form-select" id="segundoAyudante" name="segundo_ayudante">
                    <option value="" <?= empty($cirugia->segundo_ayudante) ? 'selected' : '' ?>></option>
                    <?php foreach ($cirujanos['Cirujano Oftalmólogo'] as $nombre): ?>
                        <option value="<?= htmlspecialchars($nombre) ?>" <?= equalsIgnoreCase($nombre, $cirugia->segundo_ayudante) ? 'selected' : '' ?>>
                            <?= strtoupper(htmlspecialchars($nombre)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Tercer Ayudante -->
        <div class="col-md-6">
            <div class="form-group">
                <label for="tercerAyudante" class="form-label">Tercer Ayudante :</label>
                <select class="form-select" id="tercerAyudante" name="tercer_ayudante">
                    <option value="" <?= empty($cirugia->tercer_ayudante) ? 'selected' : '' ?>></option>
                    <?php foreach ($cirujanos['Cirujano Oftalmólogo'] as $nombre): ?>
                        <option value="<?= htmlspecialchars($nombre) ?>" <?= equalsIgnoreCase($nombre, $cirugia->tercer_ayudante) ? 'selected' : '' ?>>
                            <?= strtoupper(htmlspecialchars($nombre)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <!-- Ayudante de Anestesia -->
        <div class="col-md-6">
            <div class="form-group">
                <label for="ayudanteAnestesia" class="form-label">Ayudante de Anestesia
                    :</label>
                <select class="form-select" id="ayudanteAnestesia" name="ayudanteAnestesia">
                    <option value="" <?= empty($cirugia->ayudante_anestesia) ? 'selected' : '' ?>></option>
                    <?php foreach ($cirujanos['Asistente'] as $nombre): ?>
                        <option value="<?= htmlspecialchars($nombre) ?>" <?= equalsIgnoreCase($nombre, $cirugia->ayudante_anestesia) ? 'selected' : '' ?>>
                            <?= strtoupper(htmlspecialchars($nombre)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Anestesiólogo -->
        <div class="col-md-6">
            <div class="form-group">
                <label for="anesthesiologist" class="form-label">Anestesiólogo :</label>
                <select class="form-select" id="anesthesiologist"
                        name="anestesiologo"
                        data-placeholder="Escoja el anestesiologo">
                    <option value="" <?= empty($cirugia->anestesiologo) ? 'selected' : '' ?>></option>
                    <?php foreach ($cirujanos['Anestesiologo'] as $nombre): ?>
                        <option value="<?= htmlspecialchars($nombre) ?>" <?= equalsIgnoreCase($nombre, $cirugia->anestesiologo) ? 'selected' : '' ?>>
                            <?= strtoupper(htmlspecialchars($nombre)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <!-- Instrumentista -->
        <div class="col-md-6">
            <div class="form-group">
                <label for="instrumentista" class="form-label">Instrumentista :</label>
                <select class="form-select" id="instrumentista" name="instrumentista">
                    <option value="" <?= empty($cirugia->instrumentista) ? 'selected' : '' ?>></option>
                    <?php foreach ($cirujanos['Asistente'] as $nombre): ?>
                        <option value="<?= htmlspecialchars($nombre) ?>" <?= equalsIgnoreCase($nombre, $cirugia->instrumentista) ? 'selected' : '' ?>>
                            <?= strtoupper(htmlspecialchars($nombre)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Enfermera Circulante -->
        <div class="col-md-6">
            <div class="form-group">
                <label for="circulante" class="form-label">Enfermera Circulante :</label>
                <select class="form-select" id="circulante" name="circulante">
                    <option value="" <?= empty($cirugia->circulante) ? 'selected' : '' ?>></option>
                    <?php foreach ($cirujanos['Asistente'] as $nombre): ?>
                        <option value="<?= htmlspecialchars($nombre) ?>" <?= equalsIgnoreCase($nombre, $cirugia->circulante) ? 'selected' : '' ?>>
                            <?= strtoupper(htmlspecialchars($nombre)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</section>