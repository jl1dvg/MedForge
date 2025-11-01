<h6>Procedimiento</h6>
<section>
    <!-- Procedimiento Proyectado -->
    <div class="form-group">
        <label for="procedimiento_proyectado" class="form-label">Procedimiento Proyectado
            :</label>
        <textarea name="procedimiento_proyectado" id="procedimiento_proyectado" rows="3"
                  class="form-control"
                  readonly><?php echo htmlspecialchars($cirugia->procedimiento_proyectado ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <!-- Procedimiento Realizado (Membrete) -->
    <div class="form-group">
        <label for="membrete" class="form-label">Procedimiento Realizado (Cirugía Realizada)
            :</label>
        <textarea name="membrete" id="membrete" rows="4"
                  class="form-control"><?php echo htmlspecialchars($cirugia->membrete ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <!-- Dieresis -->
    <div class="form-group">
        <label for="dieresis" class="form-label">Dieresis :</label>
        <textarea name="dieresis" id="dieresis" rows="2"
                  class="form-control"><?php echo htmlspecialchars($cirugia->dieresis ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <!-- Exposición -->
    <div class="form-group">
        <label for="exposicion" class="form-label">Exposición :</label>
        <textarea name="exposicion" id="exposicion" rows="2"
                  class="form-control"><?php echo htmlspecialchars($cirugia->exposicion ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <!-- Hallazgo -->
    <div class="form-group">
        <label for="hallazgo" class="form-label">Hallazgo :</label>
        <textarea name="hallazgo" id="hallazgo" rows="3"
                  class="form-control"><?php echo htmlspecialchars($cirugia->hallazgo ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <!-- Descripción Operatoria -->
    <div class="form-group">
        <label for="operatorio" class="form-label">Descripción Operatoria :</label>
        <textarea name="operatorio" id="operatorio" rows="5"
                  class="form-control"><?php echo htmlspecialchars($cirugia->operatorio ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <!-- Complicaciones Operatorias -->
    <div class="form-group">
        <label for="complicaciones_operatorio" class="form-label">Complicaciones Operatorias
            :</label>
        <textarea name="complicaciones_operatorio" id="complicaciones_operatorio" rows="3"
                  class="form-control"><?php echo htmlspecialchars($cirugia->complicaciones_operatorio ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>

    <!-- Detalles de la Cirugía -->
    <div class="form-group">
        <label for="datos_cirugia" class="form-label">Detalles de la Cirugía :</label>
        <textarea name="datos_cirugia" id="datos_cirugia" rows="5"
                  class="form-control"><?php echo htmlspecialchars($cirugia->datos_cirugia ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>
</section>