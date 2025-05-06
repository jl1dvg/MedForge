<?php if (isset($medicamentos, $opcionesMedicamentos)): ?>
    <div class="table-responsive">
        <table id="medicamentosTable"
               class="table editable-table mb-0">
            <thead>
            <tr>
                <th>Medicamento</th>
                <th>Dosis</th>
                <th>Frecuencia</th>
                <th>Vía de Administración</th>
                <th>Responsable</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($medicamentos)): ?>
                <?php foreach ($medicamentos as $item): ?>
                    <tr>
                        <td>
                            <select class="form-control medicamento-select" name="medicamento[]">
                                <?php foreach ($opcionesMedicamentos as $opcion): ?>
                                    <option value="<?= htmlspecialchars($opcion['id']) ?>"
                                        <?= (isset($item['id']) && $opcion['id'] == $item['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($opcion['medicamento']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td contenteditable="true"><?= htmlspecialchars($item['dosis'] ?? '') ?></td>
                        <td contenteditable="true"><?= htmlspecialchars($item['frecuencia'] ?? '') ?></td>
                        <td>
                            <select class="form-control via-select" name="via_administracion[]">
                                <?php
                                $vias = ['INTRAVENOSA', 'VIA INFILTRATIVA', 'SUBCONJUNTIVAL', 'TOPICA', 'INTRAVITREA'];
                                foreach ($vias as $via):
                                    $selected = ($via === ($item['via_administracion'] ?? '')) ? 'selected' : '';
                                    ?>
                                    <option value="<?= htmlspecialchars($via) ?>" <?= $selected ?>><?= htmlspecialchars($via) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select class="form-control responsable-select" name="responsable[]">
                                <?php
                                $responsables = ['Asistente', 'Anestesiólogo', 'Cirujano Principal'];
                                foreach ($responsables as $responsable):
                                    $selected = ($responsable === ($item['responsable'] ?? '')) ? 'selected' : '';
                                    ?>
                                    <option value="<?= htmlspecialchars($responsable) ?>" <?= $selected ?>><?= htmlspecialchars($responsable) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button class="delete-btn btn btn-danger"><i class="fa fa-minus"></i></button>
                            <button class="add-row-btn btn btn-success"><i class="fa fa-plus"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <input type="hidden" id="medicamentosInput"
               name="medicamentos"
               value='<?= htmlspecialchars(json_encode($medicamentos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
    </div>
<?php endif; ?>