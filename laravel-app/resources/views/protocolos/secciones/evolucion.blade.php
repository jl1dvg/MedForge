<?php
$protocoloData = $protocolo ?? [];
?>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="pre_evolucion">Evolución pre quirúrgica</label>
            <textarea rows="3" name="pre_evolucion" id="pre_evolucion" class="form-control"
                      placeholder="Describe la evolución preoperatoria"><?= htmlspecialchars(trim($protocoloData['pre_evolucion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="pre_indicacion">Indicación pre quirúrgica</label>
            <textarea rows="3" name="pre_indicacion" id="pre_indicacion" class="form-control"
                      placeholder="Indicación médica antes de cirugía"><?= htmlspecialchars(trim($protocoloData['pre_indicacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="post_evolucion">Evolución post quirúrgica</label>
            <textarea rows="3" name="post_evolucion" id="post_evolucion" class="form-control"
                      placeholder="Describe evolución luego de cirugía"><?= htmlspecialchars(trim($protocoloData['post_evolucion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="post_indicacion">Indicación post quirúrgica</label>
            <textarea rows="3" name="post_indicacion" id="post_indicacion" class="form-control"
                      placeholder="Indicaciones médicas después de cirugía"><?= htmlspecialchars(trim($protocoloData['post_indicacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="alta_evolucion">Evolución alta quirúrgica</label>
            <textarea rows="3" name="alta_evolucion" id="alta_evolucion" class="form-control"
                      placeholder="Condición al alta médica"><?= htmlspecialchars(trim($protocoloData['alta_evolucion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="alta_indicacion">Indicación alta quirúrgica</label>
            <textarea rows="3" name="alta_indicacion" id="alta_indicacion" class="form-control"
                      placeholder="Indicaciones para alta del paciente"><?= htmlspecialchars(trim($protocoloData['alta_indicacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>
</div>
