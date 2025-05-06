<?php if (isset($protocolo)): ?>
    <!-- Evolución Pre Quirúrgica e Indicación -->
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="pre_evolucion">Evolución Pre
                    Quirúrgica</label>
                <textarea rows="3" name="pre_evolucion" id="pre_evolucion" class="form-control"
                          placeholder="Describe la evolución preoperatoria"><?= htmlspecialchars(trim($protocolo['pre_evolucion'])) ?></textarea>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="pre_indicacion">Indicación
                    Pre
                    Quirúrgica</label>
                <textarea rows="3" name="pre_indicacion" id="pre_indicacion" class="form-control"
                          placeholder="Indicación médica antes de cirugía"><?= htmlspecialchars(trim($protocolo['pre_indicacion'])) ?></textarea>
            </div>
        </div>
    </div>

    <!-- Evolución Post Quirúrgica e Indicación -->
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="post_evolucion">Evolución
                    Post
                    Quirúrgica</label>
                <textarea rows="3" name="post_evolucion" id="post_evolucion" class="form-control"
                          placeholder="Describe evolución luego de cirugía"><?= htmlspecialchars(trim($protocolo['post_evolucion'])) ?></textarea>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="post_indicacion">Indicación
                    Post
                    Quirúrgica</label>
                <textarea rows="3" name="post_indicacion" id="post_indicacion" class="form-control"
                          placeholder="Indicaciones médicas después de cirugía"><?= htmlspecialchars(trim($protocolo['post_indicacion'])) ?></textarea>
            </div>
        </div>
    </div>

    <!-- Evolución Alta Quirúrgica e Indicación -->
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="alta_evolucion">Evolución
                    Alta
                    Quirúrgica</label>
                <textarea rows="3" name="alta_evolucion" id="alta_evolucion" class="form-control"
                          placeholder="Condición al alta médica"><?= htmlspecialchars(trim($protocolo['alta_evolucion'])) ?></textarea>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="alta_indicacion">Indicación
                    Alta
                    Quirúrgica</label>
                <textarea rows="3" name="alta_indicacion" id="alta_indicacion" class="form-control"
                          placeholder="Indicaciones para alta del paciente"><?= htmlspecialchars(trim($protocolo['alta_indicacion'])) ?></textarea>
            </div>
        </div>
    </div>
<?php endif; ?>