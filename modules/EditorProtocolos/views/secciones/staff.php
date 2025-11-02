<?php
$staffList = $staff ?? [];
if (empty($staffList)) {
    $staffList = [
        ['funcion' => 'CIRUJANO 1', 'trabajador' => '', 'nombre' => '', 'selector' => ''],
    ];
}
?>
<div class="form-group">
    <label for="staff">Equipo quirúrgico</label>
    <div id="contenedor-staff">
        <?php foreach ($staffList as $index => $miembro): ?>
            <div class="row mb-2 staff-item align-items-center">
                <div class="col-md-5">
                    <select name="funciones[]" class="form-select">
                        <?php
                        $funciones = [
                            'CIRUJANO 1', 'CIRUJANO 2', 'INSTRUMENTISTA', 'CIRCULANTE',
                            'ANESTESIOLOGO', 'PRIMER AYUDANTE', 'AYUDANTE ANESTESIOLOGO'
                        ];
                        foreach ($funciones as $funcion):
                            $selected = ($miembro['funcion'] ?? '') === $funcion ? 'selected' : '';
                            ?>
                            <option value="<?= htmlspecialchars($funcion, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                <?= htmlspecialchars($funcion, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="trabajadores[]" value="<?= htmlspecialchars($miembro['trabajador'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="selectores[]" value="<?= htmlspecialchars($miembro['selector'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-5">
                    <input type="text" name="nombres_staff[]" class="form-control"
                           value="<?= htmlspecialchars($miembro['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Nombre del trabajador">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm eliminar-staff">Eliminar</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-primary mt-2" id="agregar-staff">+ Agregar miembro</button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        let indexStaff = <?= count($staffList) ?>;
        const funciones = ['CIRUJANO 1', 'CIRUJANO 2', 'INSTRUMENTISTA', 'CIRCULANTE', 'ANESTESIOLOGO', 'PRIMER AYUDANTE', 'AYUDANTE ANESTESIOLOGO'];

        document.getElementById('agregar-staff').addEventListener('click', function () {
            const container = document.getElementById('contenedor-staff');
            const row = document.createElement('div');
            row.className = 'row mb-2 staff-item align-items-center';
            row.innerHTML = `
                <div class="col-md-5">
                    <select name="funciones[]" class="form-select">
                        ${funciones.map(funcion => `<option value="${funcion}">${funcion}</option>`).join('')}
                    </select>
                    <input type="hidden" name="trabajadores[]" value="">
                    <input type="hidden" name="selectores[]" value="#select2-consultasubsecuente-trabajadorprotocolo-${indexStaff}-funcion-container">
                </div>
                <div class="col-md-5">
                    <input type="text" name="nombres_staff[]" class="form-control" placeholder="Nombre del trabajador">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger btn-sm eliminar-staff">Eliminar</button>
                </div>
            `;
            container.appendChild(row);
            indexStaff++;
        });

        document.getElementById('contenedor-staff').addEventListener('click', function (event) {
            if (event.target.classList.contains('eliminar-staff')) {
                const item = event.target.closest('.staff-item');
                if (item) {
                    item.remove();
                }
            }
        });
    });
</script>
