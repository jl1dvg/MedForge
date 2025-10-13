<h6>Insumos</h6>
<section>
    <?php
    $insumosDisponibles = $reporteCirugiasController->obtenerInsumosDisponibles($cirugia->afiliacion);

    // ✅ Ordenar insumos por nombre dentro de cada categoría
    foreach ($insumosDisponibles as &$grupo) {
        uasort($grupo, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
    }
    unset($grupo);
    $jsonInsumos = trim($cirugia->insumos ?? '');
    if ($jsonInsumos === '' || $jsonInsumos === '[]') {
        $insumos = $reporteCirugiasController->obtenerInsumosPorProtocolo($cirugia->procedimiento_id, null);
    } else {
        $insumos = json_decode($jsonInsumos, true);
    }
    $categorias = array_keys($insumosDisponibles);
    ?>
    <div class="table-responsive">
        <table id="insumosTable" class="table editable-table mb-0">
            <thead>
            <tr>
                <th>Categoría</th>
                <th>Nombre del Insumo</th>
                <th>Cantidad</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach (['equipos', 'quirurgicos', 'anestesia'] as $categoriaOrdenada):
                if (!empty($insumos[$categoriaOrdenada])):
                    // Ordena los insumos por nombre dentro de cada categoría
                    usort($insumos[$categoriaOrdenada], fn($a, $b) => strcmp($insumosDisponibles[$categoriaOrdenada][$a['id']]['nombre'], $insumosDisponibles[$categoriaOrdenada][$b['id']]['nombre']));
                    foreach ($insumos[$categoriaOrdenada] as $item):
                        $idInsumo = $item['id'];
                        ?>
                        <tr class="categoria-<?= htmlspecialchars($categoriaOrdenada) ?>">
                            <td>
                                <select class="form-control categoria-select"
                                        name="categoria">
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($cat == $categoriaOrdenada) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(str_replace('_', ' ', $cat)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select class="form-control nombre-select" name="id">
                                    <?php
                                    foreach ($insumosDisponibles[$categoriaOrdenada] as $id => $insumo): ?>
                                        <option value="<?= htmlspecialchars($id) ?>" <?= ($id == $idInsumo) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($insumo['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td contenteditable="true"><?= htmlspecialchars($item['cantidad']) ?></td>
                            <td>
                                <button class="delete-btn btn btn-danger"><i
                                            class="fa fa-minus"></i></button>
                                <button class="add-row-btn btn btn-success"><i
                                            class="fa fa-plus"></i></button>
                            </td>
                        </tr>
                    <?php endforeach;
                endif;
            endforeach; ?>
            </tbody>
        </table>
        <input type="hidden" id="insumosInput" name="insumos"
               value='<?= htmlspecialchars(json_encode($insumos)) ?>'>
    </div>
</section>