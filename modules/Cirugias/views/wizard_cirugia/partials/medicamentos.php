<h6>Medicamentos</h6>
<section>
    <?php
    // Obtener medicamentos desde protocolo_data o fallback desde kardex
    $jsonMedicamentos = trim($cirugia->medicamentos ?? '');
    if ($jsonMedicamentos === '' || $jsonMedicamentos === '[]') {
        $stmt = $pdo->prepare("SELECT medicamentos FROM kardex WHERE procedimiento_id = ?");
        $stmt->execute([$cirugia->procedimiento_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $medicamentos = json_decode($row['medicamentos'] ?? '[]', true);
    } else {
        $medicamentos = json_decode($jsonMedicamentos, true);
    }

    $opcionesMedicamentos = [];
    $stmtOpciones = $pdo->query("SELECT id, medicamento FROM medicamentos ORDER BY medicamento");
    while ($op = $stmtOpciones->fetch(PDO::FETCH_ASSOC)) {
        $opcionesMedicamentos[] = $op;
    }

    $vias = ['INTRAVENOSA', 'VIA INFILTRATIVA', 'SUBCONJUNTIVAL', 'TOPICA', 'INTRAVITREA'];
    $responsables = ['Asistente', 'Anestesiólogo', 'Cirujano Principal'];
    ?>
    <div class="table-responsive">
        <table id="medicamentosTable" class="table editable-table mb-0">
            <thead>
            <tr>
                <th>Medicamento</th>
                <th>Dosis</th>
                <th>Frecuencia</th>
                <th>Vía</th>
                <th>Responsable</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($medicamentos as $item): ?>
                <tr>
                    <td>
                        <select class="form-control medicamento-select"
                                name="medicamento[]">
                            <?php foreach ($opcionesMedicamentos as $op): ?>
                                <option value="<?= $op['id'] ?>" <?= ($op['id'] == ($item['id'] ?? null)) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($op['medicamento']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td contenteditable="true"><?= htmlspecialchars($item['dosis'] ?? '') ?></td>
                    <td contenteditable="true"><?= htmlspecialchars($item['frecuencia'] ?? '') ?></td>
                    <td>
                        <select class="form-control via-select" name="via_administracion[]">
                            <?php foreach ($vias as $via): ?>
                                <option value="<?= $via ?>" <?= ($via === ($item['via_administracion'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($via) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select class="form-control responsable-select"
                                name="responsable[]">
                            <?php foreach ($responsables as $resp): ?>
                                <option value="<?= $resp ?>" <?= ($resp === ($item['responsable'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($resp) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <button class="delete-btn btn btn-danger"><i
                                class="fa fa-minus"></i></button>
                        <button class="add-row-btn btn btn-success"><i
                                class="fa fa-plus"></i></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <input type="hidden" id="medicamentosInput" name="medicamentos"
               value='<?= htmlspecialchars(json_encode($medicamentos)) ?>'>
    </div>
</section>