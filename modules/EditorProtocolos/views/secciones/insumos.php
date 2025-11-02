<?php
$insumosSeleccionados = $insumosPaciente ?? [];
$catalogo = $insumosDisponibles ?? [];
$categorias = array_keys($catalogo);

$renderCategoriaOptions = function (string $seleccionada = '') use ($categorias): string {
    $options = '<option value="">Seleccione categoría</option>';
    foreach ($categorias as $categoria) {
        $selected = $seleccionada === $categoria ? 'selected' : '';
        $options .= sprintf('<option value="%1$s" %2$s>%3$s</option>',
            htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8'),
            $selected,
            htmlspecialchars(str_replace('_', ' ', $categoria), ENT_QUOTES, 'UTF-8')
        );
    }
    return $options;
};

$renderNombreOptions = function (string $categoria, string $seleccionado = '') use ($catalogo): string {
    $options = '';
    $items = $catalogo[$categoria] ?? [];
    foreach ($items as $item) {
        $selected = ((string)($item['id'] ?? '') === $seleccionado) ? 'selected' : '';
        $options .= sprintf('<option value="%1$s" %2$s>%3$s</option>',
            htmlspecialchars((string)($item['id'] ?? ''), ENT_QUOTES, 'UTF-8'),
            $selected,
            htmlspecialchars($item['nombre'] ?? '', ENT_QUOTES, 'UTF-8')
        );
    }
    return $options;
};

$renderFila = function (?string $categoria = null, string $insumoId = '', string $cantidad = '') use ($renderCategoriaOptions, $renderNombreOptions): string {
    $categoriaValor = $categoria ?? '';
    $nombreOptions = $categoria ? $renderNombreOptions($categoriaValor, $insumoId) : '<option value="">Seleccione una categoría</option>';
    return sprintf(
        '<tr>' .
        '<td><select class="form-control categoria-select" name="categoria">%s</select></td>' .
        '<td><select class="form-control nombre-select" name="nombre">%s</select></td>' .
        '<td contenteditable="true">%s</td>' .
        '<td><button class="delete-btn btn btn-danger" type="button"><i class="fa fa-minus"></i></button> ' .
        '<button class="add-row-btn btn btn-success" type="button"><i class="fa fa-plus"></i></button></td>' .
        '</tr>',
        $renderCategoriaOptions($categoriaValor),
        $nombreOptions,
        htmlspecialchars($cantidad, ENT_QUOTES, 'UTF-8')
    );
};
?>
<div class="table-responsive">
    <table id="insumosTable" class="table editable-table mb-0">
        <thead>
        <tr>
            <th>Categoría</th>
            <th>Insumo</th>
            <th>Cantidad</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($insumosSeleccionados)): ?>
            <?php foreach ($insumosSeleccionados as $categoria => $items): ?>
                <?php foreach ($items as $item):
                    $insumoId = isset($item['id']) ? (string)$item['id'] : '';
                    $cantidad = isset($item['cantidad']) ? (string)$item['cantidad'] : '';
                    echo $renderFila($categoria, $insumoId, $cantidad);
                endforeach; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <?= $renderFila(null, '', '1') ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
