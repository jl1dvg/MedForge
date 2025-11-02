<?php if (isset($protocolo)): ?>
    <!-- Campo oculto ID -->
    <input type="hidden" name="id" value="<?= htmlspecialchars($protocolo['id']) ?>">

    <div class="form-group">
        <label for="staff">Equipo Quirúrgico</label>
        <div id="contenedor-staff">
            <?php foreach ($staff as $index => $miembro): ?>
                <div class="row mb-2 staff-item align-items-center">
                    <div class="col-md-5">
                        <select name="funciones[]" class="form-select">
                            <option value="CIRUJANO 1" <?= $miembro['funcion'] === 'CIRUJANO 1' ? 'selected' : '' ?>>
                                CIRUJANO 1
                            </option>
                            <option value="CIRUJANO 2" <?= $miembro['funcion'] === 'CIRUJANO 2' ? 'selected' : '' ?>>
                                CIRUJANO 2
                            </option>
                            <option value="INSTRUMENTISTA" <?= $miembro['funcion'] === 'INSTRUMENTISTA' ? 'selected' : '' ?>>
                                INSTRUMENTISTA
                            </option>
                            <option value="CIRCULANTE" <?= $miembro['funcion'] === 'CIRCULANTE' ? 'selected' : '' ?>>
                                CIRCULANTE
                            </option>
                            <option value="ANESTESIOLOGO" <?= $miembro['funcion'] === 'ANESTESIOLOGO' ? 'selected' : '' ?>>
                                ANESTESIOLOGO
                            </option>
                            <option value="PRIMER AYUDANTE" <?= $miembro['funcion'] === 'PRIMER AYUDANTE' ? 'selected' : '' ?>>
                                PRIMER AYUDANTE
                            </option>
                            <option value="AYUDANTE ANESTESIOLOGO" <?= $miembro['funcion'] === 'AYUDANTE ANESTESIOLOGO' ? 'selected' : '' ?>>
                                AYUDANTE ANESTESIOLOGO
                            </option>
                        </select>
                        <input type="hidden" name="trabajadores[]"
                               value="<?= htmlspecialchars($miembro['trabajador']) ?>">
                        <input type="hidden" name="selectores[]"
                               value="#select2-consultasubsecuente-trabajadorprotocolo-<?= $index ?>-funcion-container">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="nombres_staff[]" class="form-control"
                               value="<?= htmlspecialchars($miembro['nombre']) ?>" placeholder="Nombre del trabajador">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm eliminar-staff">Eliminar</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-primary mt-2" id="agregar-staff">+ Agregar Miembro</button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let indexStaff = <?= count($staff) ?>;

            document.getElementById('agregar-staff').addEventListener('click', function () {
                const container = document.getElementById('contenedor-staff');
                const row = document.createElement('div');
                row.className = 'row mb-2 staff-item align-items-center';
                row.innerHTML = `
                    <div class="col-md-5">
                        <select name="funciones[]" class="form-select">
                            <option value="CIRUJANO 1">CIRUJANO 1</option>
                            <option value="CIRUJANO 2">CIRUJANO 2</option>
                            <option value="INSTRUMENTISTA">INSTRUMENTISTA</option>
                            <option value="CIRCULANTE">CIRCULANTE</option>
                            <option value="ANESTESIOLOGO">ANESTESIOLOGO</option>
                            <option value="PRIMER AYUDANTE">PRIMER AYUDANTE</option>
                            <option value="AYUDANTE ANESTESIOLOGO">AYUDANTE ANESTESIOLOGO</option>
                        </select>
                        <input type="hidden" name="trabajadores[]" value="#select2-consultasubsecuente-trabajadorprotocolo-${indexStaff}-doctor-container">
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

            document.getElementById('contenedor-staff').addEventListener('click', function (e) {
                if (e.target.classList.contains('eliminar-staff')) {
                    e.target.closest('.staff-item').remove();
                }
            });
        });
    </script>
<?php endif; ?>