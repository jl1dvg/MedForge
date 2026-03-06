<?php
/** @var string $username */
/** @var array $scripts */
/** @var array<int, array{value:string,label:string}> $afiliacionOptions */
/** @var array<int, array{value:string,label:string}> $afiliacionCategoriaOptions */
/** @var array<int, array{value:string,label:string}> $sedeOptions */
/** @var string $fechaInicioDefault */
/** @var string $fechaFinDefault */
$fechaInicioDefaultValue = htmlspecialchars((string)($fechaInicioDefault ?? ''), ENT_QUOTES, 'UTF-8');
$fechaFinDefaultValue = htmlspecialchars((string)($fechaFinDefault ?? ''), ENT_QUOTES, 'UTF-8');
$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/datatable/datatables.min.js',
    'js/pages/cirugias.js',
    'js/modules/cirugias_modal.js',
]);
?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Reporte de Cirugías</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Reporte de Cirugías</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-body">
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h4 class="box-title mb-0">Cirugías realizadas</h4>
                    </div>
                    <form id="filtrosCirugias" class="row g-2 align-items-end mb-3">
                        <div class="col-sm-6 col-md-3">
                            <label for="filtroFechaInicio" class="form-label">Desde</label>
                            <input type="date"
                                   class="form-control"
                                   id="filtroFechaInicio"
                                   name="fecha_inicio"
                                   value="<?= $fechaInicioDefaultValue ?>"
                                   data-default="<?= $fechaInicioDefaultValue ?>">
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="filtroFechaFin" class="form-label">Hasta</label>
                            <input type="date"
                                   class="form-control"
                                   id="filtroFechaFin"
                                   name="fecha_fin"
                                   value="<?= $fechaFinDefaultValue ?>"
                                   data-default="<?= $fechaFinDefaultValue ?>">
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="filtroAfiliacion" class="form-label">Afiliación</label>
                            <select class="form-select" id="filtroAfiliacion" name="afiliacion">
                                <?php foreach (($afiliacionOptions ?? []) as $option): ?>
                                    <?php $value = (string)($option['value'] ?? ''); ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="filtroAfiliacionCategoria" class="form-label">Categoría afiliación</label>
                            <select class="form-select" id="filtroAfiliacionCategoria" name="afiliacion_categoria">
                                <?php foreach (($afiliacionCategoriaOptions ?? []) as $option): ?>
                                    <?php $value = (string)($option['value'] ?? ''); ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="filtroSede" class="form-label">Sede</label>
                            <select class="form-select" id="filtroSede" name="sede">
                                <?php foreach (($sedeOptions ?? []) as $option): ?>
                                    <?php $value = (string)($option['value'] ?? ''); ?>
                                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string)($option['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="mdi mdi-filter-variant"></i> Aplicar filtros
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimpiarFiltrosCirugias">
                                <i class="mdi mdi-close-circle-outline"></i> Limpiar
                            </button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table id="surgeryTable" class="table table-striped table-hover">
                            <thead>
                            <tr>
                                <th class="bb-2">No.</th>
                                <th class="bb-2">C.I.</th>
                                <th class="bb-2">Nombre</th>
                                <th class="bb-2">Afiliación</th>
                                <th class="bb-2">Fecha</th>
                                <th class="bb-2">Procedimiento</th>
                                <th class="bb-2" title="Ver protocolo"><i class="mdi mdi-file-document"></i></th>
                                <th class="bb-2" title="Certificado de descanso"><i class="mdi mdi-file-document-box"></i></th>
                                <th class="bb-2" title="Imprimir protocolo"><i class="mdi mdi-printer"></i></th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/components/modal_protocolo.php'; ?>

<script>
    function emitirCertificadoDescanso(formId, hcNumber) {
        const value = window.prompt('Ingrese los dias de descanso postquirurgico', '5');
        if (value === null) {
            return;
        }

        const dias = Number.parseInt(value, 10);
        if (!Number.isFinite(dias) || dias <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Valor invalido',
                text: 'Debe ingresar un numero entero mayor a cero.',
            });
            return;
        }

        const params = new URLSearchParams({
            form_id: formId,
            hc_number: hcNumber,
            dias_descanso: String(dias),
        });

        window.open(`/v2/reports/cirugias/descanso/pdf?${params.toString()}`, '_blank');
    }

    function togglePrintStatus(form_id, hc_number, button, currentStatus) {
        const isActive = button.classList.contains('active');
        const newStatus = isActive ? 0 : 1;

        if (!isActive) {
            window.open(`/v2/reports/protocolo/pdf?form_id=${form_id}&hc_number=${hc_number}`, '_blank');
        }

        button.classList.toggle('active');
        button.setAttribute('aria-pressed', button.classList.contains('active'));

        fetch('/cirugias/protocolo/printed', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({form_id, hc_number, printed: button.classList.contains('active') ? 1 : 0})
        }).then(response => {
            if (!response.ok) {
                throw new Error('Error al actualizar el estado');
            }
            return response.json();
        }).then(data => {
            if (!data.success) {
                throw new Error('Error al actualizar el estado');
            }
        }).catch(() => {
            Swal.fire('Error', 'No se pudo actualizar el estado de impresión.', 'error');
            button.classList.toggle('active');
            button.setAttribute('aria-pressed', button.classList.contains('active'));
        });
    }

    let currentFormId;
    let currentHcNumber;

    function redirectToEditProtocol() {
        if (!currentFormId || !currentHcNumber) {
            return;
        }
        window.location.href = `/cirugias/wizard?form_id=${encodeURIComponent(currentFormId)}&hc_number=${encodeURIComponent(currentHcNumber)}`;
    }

    function loadProtocolData(button) {
        const formId = button.getAttribute('data-form-id');
        const hcNumber = button.getAttribute('data-hc-number');
        currentFormId = formId;
        currentHcNumber = hcNumber;

        fetch(`/cirugias/protocolo?form_id=${formId}&hc_number=${hcNumber}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                const diagTable = document.getElementById('diagnostico-table');
                diagTable.innerHTML = '';
                (data.diagnosticos || []).forEach(d => {
                    diagTable.innerHTML += `<tr><td>${d.cie10}</td><td>${d.detalle}</td></tr>`;
                });

                const procTable = document.getElementById('procedimientos-table');
                procTable.innerHTML = '';
                (data.procedimientos || []).forEach(p => {
                    procTable.innerHTML += `<tr><td>${p.codigo}</td><td>${p.nombre}</td></tr>`;
                });

                const timingRow = document.getElementById('timing-row');
                timingRow.innerHTML = `
                    <td>${data.fecha_inicio ?? ''}</td>
                    <td>${data.hora_inicio ?? ''}</td>
                    <td>${data.hora_fin ?? ''}</td>
                    <td>${data.duracion ?? ''}</td>
                `;

                const resultTable = document.getElementById('result-table');
                resultTable.innerHTML = '';
                resultTable.innerHTML += `<tr><td>Dieresis</td><td>${data.dieresis ?? ''}</td></tr>`;
                resultTable.innerHTML += `<tr><td>Exposición</td><td>${data.exposicion ?? ''}</td></tr>`;
                resultTable.innerHTML += `<tr><td>Hallazgo</td><td>${data.hallazgo ?? ''}</td></tr>`;
                resultTable.innerHTML += `<tr><td>Operatorio</td><td>${data.operatorio ?? ''}</td></tr>`;

                const staffTable = document.getElementById('staff-table');
                staffTable.innerHTML = '';
                Object.entries(data.staff || {}).forEach(([rol, nombre]) => {
                    if (nombre && nombre.trim() !== '') {
                        staffTable.innerHTML += `<tr><td>${rol}</td><td>${nombre}</td></tr>`;
                    }
                });

                const comment = document.querySelector('.comment-here');
                if (comment) {
                    comment.textContent = data.comentario ?? '';
                }
            })
            .catch(() => {
                Swal.fire('Error', 'No se pudo cargar el protocolo.', 'error');
            });
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
