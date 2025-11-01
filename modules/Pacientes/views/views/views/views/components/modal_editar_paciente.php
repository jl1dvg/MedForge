<?php

use Modules\Pacientes\Support\ViewHelper as PacientesHelper;

?>
<!-- Modal Editar Paciente -->
<div class="modal fade" id="modalEditarPaciente" tabindex="-1" aria-labelledby="modalEditarPacienteLabel"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="actualizar_paciente" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditarPacienteLabel">Editar Datos del Paciente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Primer Nombre</label>
                        <input type="text" name="fname" class="form-control"
                               value="<?= PacientesHelper::safe($patientData['fname'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label>Segundo Nombre</label>
                        <input type="text" name="mname" class="form-control"
                               value="<?= PacientesHelper::safe($patientData['mname'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label>Primer Apellido</label>
                        <input type="text" name="lname" class="form-control"
                               value="<?= PacientesHelper::safe($patientData['lname'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label>Segundo Apellido</label>
                        <input type="text" name="lname2" class="form-control"
                               value="<?= PacientesHelper::safe($patientData['lname2'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label>Afiliación</label>
                        <select name="afiliacion" class="form-control">
                            <?php foreach ($afiliacionesDisponibles as $afiliacion): ?>
                                <option value="<?= PacientesHelper::safe($afiliacion) ?>" <?= strtolower($afiliacion) === strtolower($patientData['afiliacion'] ?? '') ? 'selected' : '' ?>>
                                    <?= PacientesHelper::safe($afiliacion) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Fecha de Nacimiento</label>
                        <input type="date" name="fecha_nacimiento" class="form-control"
                               value="<?= PacientesHelper::safe($patientData['fecha_nacimiento'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label>Sexo</label>
                        <select name="sexo" class="form-control">
                            <option value="Masculino" <?= strtolower($patientData['sexo'] ?? '') === 'masculino' ? 'selected' : '' ?>>
                                Masculino
                            </option>
                            <option value="Femenino" <?= strtolower($patientData['sexo'] ?? '') === 'femenino' ? 'selected' : '' ?>>
                                Femenino
                            </option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Celular</label>
                        <input type="text" name="celular" class="form-control"
                               value="<?= PacientesHelper::safe($patientData['celular'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label>Número de Historia Clínica (HC)</label>
                        <input type="text" name="hc_number" class="form-control"
                               value="<?= PacientesHelper::safe($patientData['hc_number'] ?? '') ?>" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>