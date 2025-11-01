<!-- Sección 1: Datos del Paciente -->
<h6>Datos del Paciente</h6>
<section>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="firstName1" class="form-label">Nombre :</label>
                <input type="text" class="form-control" id="firstName1" name="fname"
                       value="<?php echo htmlspecialchars($cirugia->fname); ?>">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="middleName2" class="form-label">Segundo Nombre:</label>
                <input type="text" class="form-control" id="middleName2" name="mname"
                       value="<?php echo htmlspecialchars($cirugia->mname ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="lastName1" class="form-label">Primer Apellido:</label>
                <input type="text" class="form-control" id="lastName1" name="lname"
                       value="<?php echo htmlspecialchars($cirugia->lname); ?>">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="lastName2" class="form-label">Segundo Apellido:</label>
                <input type="text" class="form-control" id="lastName2" name="lname2"
                       value="<?php echo htmlspecialchars($cirugia->lname2); ?>">
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="birthDate" class="form-label">Fecha de Nacimiento :</label>
                <input type="date" class="form-control" id="birthDate" name="fecha_nacimiento"
                       value="<?php echo htmlspecialchars($cirugia->fecha_nacimiento); ?>">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="afiliacion" class="form-label">Afiliación :</label>
                <input type="text" class="form-control" id="afiliacion" name="afiliacion"
                       value="<?php echo htmlspecialchars($cirugia->afiliacion); ?>" readonly>
            </div>
        </div>
    </div>
</section>