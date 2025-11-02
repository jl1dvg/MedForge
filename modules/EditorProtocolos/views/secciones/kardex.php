<?php
$medicamentosLista = $medicamentos ?? [];
?>
<div class="table-responsive">
    <table id="medicamentosTable" class="table editable-table mb-0">
        <thead>
        <tr>
            <th>Medicamento</th>
            <th>Dosis</th>
            <th>Frecuencia</th>
            <th>Vía de administración</th>
            <th>Responsable</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($medicamentosLista as $item): ?>
            <tr>
                <td>
                    <select class="form-control medicamento-select" name="medicamento[]">
                        <?php foreach ($opcionesMedicamentos as $opcion): ?>
                            <option value="<?= htmlspecialchars($opcion['id'], ENT_QUOTES, 'UTF-8') ?>"
                                <?= (isset($item['id']) && $opcion['id'] == $item['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcion['medicamento'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td contenteditable="true"><?= htmlspecialchars($item['dosis'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td contenteditable="true"><?= htmlspecialchars($item['frecuencia'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <select class="form-control via-select" name="via_administracion[]">
                        <?php foreach ($vias as $via):
                            $selected = ($via === ($item['via_administracion'] ?? '')) ? 'selected' : '';
                            ?>
                            <option value="<?= htmlspecialchars($via, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                <?= htmlspecialchars($via, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select class="form-control responsable-select" name="responsable[]">
                        <?php foreach ($responsables as $responsable):
                            $selected = ($responsable === ($item['responsable'] ?? '')) ? 'selected' : '';
                            ?>
                            <option value="<?= htmlspecialchars($responsable, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                <?= htmlspecialchars($responsable, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <button class="delete-btn btn btn-danger" type="button"><i class="fa fa-minus"></i></button>
                    <button class="add-row-btn btn btn-success" type="button"><i class="fa fa-plus"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
