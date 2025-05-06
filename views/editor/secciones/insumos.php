<?php if (isset($categorias, $insumosDisponibles, $insumosPaciente)): ?>
    <div class="table-responsive">
        <table id="insumosTable"
               class="table editable-table mb-0">
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
            $categoriasSistema = ['equipos', 'quirurgicos', 'anestesia'];

            foreach ($categoriasSistema as $categoria) {
                if (!empty($insumosPaciente[$categoria])) {
                    foreach ($insumosPaciente[$categoria] as $item) {
                        ?>
                        <tr class="categoria-<?= htmlspecialchars($categoria) ?>">
                            <td>
                                <select class="form-control categoria-select" name="categoria[]">
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($cat == $categoria) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select class="form-control nombre-select" name="nombre[]">
                                    <?php
                                    if (isset($insumosDisponibles[$categoria])) {
                                        foreach ($insumosDisponibles[$categoria] as $insumo) {
                                            $selected = (isset($item['id']) && $insumo['id'] == $item['id']) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($insumo['id']) . '" ' . $selected . '>' . htmlspecialchars($insumo['nombre']) . '</option>';
                                        }
                                    } else {
                                        echo '<option value="">Seleccione una categoría</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                            <td contenteditable="true" data-cantidad="<?= htmlspecialchars($item['cantidad']) ?>">
                                <?= htmlspecialchars($item['cantidad']) ?>
                            </td>
                            <td>
                                <button class="delete-btn btn btn-danger"><i class="fa fa-minus"></i></button>
                                <button class="add-row-btn btn btn-success"><i class="fa fa-plus"></i></button>
                            </td>
                        </tr>
                        <?php
                    }
                }
            }

            if (empty($insumosPaciente)) {
                // Primera fila por defecto si no hay insumos
                ?>
                <tr class="categoria-equipos">
                    <td>
                        <select class="form-control categoria-select" name="categoria[]">
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select class="form-control nombre-select" name="nombre[]">
                            <?php
                            foreach ($insumosDisponibles['equipos'] ?? [] as $insumo) {
                                echo '<option value="' . htmlspecialchars($insumo['id']) . '">' . htmlspecialchars($insumo['nombre']) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                    <td contenteditable="true" data-cantidad="1">1</td>
                    <td>
                        <button class="delete-btn btn btn-danger"><i class="fa fa-minus"></i></button>
                        <button class="add-row-btn btn btn-success"><i class="fa fa-plus"></i></button>
                    </td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>

        <!-- Campo oculto donde se serializarán los insumos como JSON -->
        <input type="hidden" id="insumosInput" name="insumos"
               value='<?= htmlspecialchars(json_encode($insumosPaciente, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
    </div>
<?php endif; ?>