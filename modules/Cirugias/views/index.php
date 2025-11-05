<?php
/** @var string $username */
/** @var array $scripts */
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
                                <th class="bb-2" title="Imprimir protocolo"><i class="mdi mdi-printer"></i></th>
                            </tr>
                            </thead>
                            <tbody id="patientTableBody">
                            <?php foreach ($cirugias as $cirugia): ?>
                                <?php
                                /** @var \Modules\Cirugias\Models\Cirugia $cirugia */
                                $printed = (int)($cirugia->printed ?? 0);
                                $estado = $cirugia->getEstado();
                                $badgeEstado = match ($estado) {
                                    'revisado' => "<span class='badge bg-success'><i class='fa fa-check'></i></span>",
                                    'no revisado' => "<span class='badge bg-warning'><i class='fa fa-exclamation-triangle'></i></span>",
                                    default => "<span class='badge bg-danger'><i class='fa fa-times'></i></span>",
                                };
                                $onclick = $estado === 'revisado'
                                    ? "togglePrintStatus('" . htmlspecialchars($cirugia->form_id) . "', '" . htmlspecialchars($cirugia->hc_number) . "', this, " . $printed . ")"
                                    : "Swal.fire({ icon: 'warning', title: 'Pendiente revisión', text: 'Debe revisar el protocolo antes de imprimir.' })";
                                $badgePrinted = $printed ? "<span class='badge bg-success'><i class='fa fa-check'></i></span>" : '';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($cirugia->form_id ?? '') ?></td>
                                    <td><?= htmlspecialchars($cirugia->hc_number ?? '') ?></td>
                                    <td><?= htmlspecialchars($cirugia->getNombreCompleto()) ?></td>
                                    <td><?= htmlspecialchars($cirugia->afiliacion ?? '') ?></td>
                                    <td><?= $cirugia->fecha_inicio ? date('d/m/Y', strtotime($cirugia->fecha_inicio)) : '' ?></td>
                                    <td><?= htmlspecialchars($cirugia->membrete ?? '') ?></td>
                                    <td>
                                        <a href="#"
                                           class="btn btn-app btn-info"
                                           title="Ver protocolo quirúrgico"
                                           data-bs-toggle="modal"
                                           data-bs-target="#resultModal"
                                           data-form-id="<?= htmlspecialchars($cirugia->form_id) ?>"
                                           data-hc-number="<?= htmlspecialchars($cirugia->hc_number) ?>"
                                           onclick="loadProtocolData(this)">
                                            <?= $badgeEstado ?>
                                            <i class="mdi mdi-file-document"></i> Protocolo
                                        </a>
                                    </td>
                                    <td>
                                        <a class="btn btn-app btn-primary <?= $printed ? 'active' : '' ?>"
                                           title="Imprimir protocolo"
                                           onclick="<?= $onclick ?>">
                                            <?= $badgePrinted ?>
                                            <i class="fa fa-print"></i> Imprimir
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/components/modal_protocolo.php'; ?>

<script>
    function togglePrintStatus(form_id, hc_number, button, currentStatus) {
        const isActive = button.classList.contains('active');
        const newStatus = isActive ? 0 : 1;

        if (!isActive) {
            window.open(`/reports/protocolo/pdf?form_id=${form_id}&hc_number=${hc_number}`, '_blank');
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

    function reloadPatientTable() {
        fetch(window.location.href, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newBody = doc.getElementById('patientTableBody');
                if (newBody) {
                    document.getElementById('patientTableBody').innerHTML = newBody.innerHTML;
                }
            });
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
