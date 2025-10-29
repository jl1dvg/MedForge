<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-xxxl-9 col-xl-8 col-12">
            <div class="row">
                <?php include __DIR__ . '/../components/dashboard_top.php'; ?>
                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Tipos de cirugías realizadas</h4>
                        </div>
                        <div class="box-body">
                            <div id="patient_statistics">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-body">
                            <div class="flexbox mb-20">
                                <div class="dropdown">
                                    <h6 class="text-uppercase dropdown-toggle"
                                        data-bs-toggle="dropdown">
                                        Today</h6>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item active" href="#">Today</a>
                                        <a class="dropdown-item" href="#">Yesterday</a>
                                        <a class="dropdown-item" href="#">Last week</a>
                                        <a class="dropdown-item" href="#">Last month</a>
                                    </div>
                                </div>
                            </div>
                            <div id="recovery_statistics"></div>
                            <!-- Este es el id donde aparecerá el gráfico pie -->
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="box">
                        <div class="box-header with-border">
                            <h4 class="box-title">Cirugías Recientes</h4>
                            <div class="box-controls pull-right">
                                <div class="lookup lookup-circle lookup-right">
                                    <input type="text" name="s">
                                </div>
                            </div>
                        </div>
                        <div class="box-body no-padding">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <tbody>
                                    <tr class="bg-info-light">
                                        <th>No</th>
                                        <th>Fecha</th>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Edad</th>
                                        <th>Procedimiento</th>
                                        <th>Afiliación</th>
                                        <th>Opciones</th>
                                    </tr>
                                    <?php if (!empty($cirugias_recientes)): ?>
                                        <?php foreach ($cirugias_recientes as $index => $patient): ?>
                                            <tr>
                                                <td><?= $index + 1; ?></td>
                                                <td><?= date('d/m/Y', strtotime($patient['fecha_inicio'])); ?></td>
                                                <td><?= htmlspecialchars($patient['hc_number']); ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($patient['fname'] . ' ' . $patient['lname'] . ' ' . $patient['lname2']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $birthDate = new DateTime($patient['fecha_nacimiento']);
                                                    $today = new DateTime($patient['fecha_inicio']);
                                                    echo $today->diff($birthDate)->y;
                                                    ?>
                                                </td>
                                                <td><?= htmlspecialchars($patient['membrete']); ?></td>
                                                <td><?= htmlspecialchars($patient['afiliacion']); ?></td>
                                                <td>
                                                    <div class="d-flex">
                                                        <a href="edit_protocol.php?form_id=<?= $patient['form_id']; ?>&hc_number=<?= $patient['hc_number']; ?>"
                                                           class="waves-effect waves-circle btn btn-circle btn-success btn-xs me-5"><i
                                                                    class="fa fa-pencil"></i></a>
                                                        <a href="../generate_pdf.php?form_id=<?= $patient['form_id']; ?>&hc_number=<?= $patient['hc_number']; ?>"
                                                           class="waves-effect waves-circle btn btn-circle btn-secondary btn-xs"><i
                                                                    class="fa fa-print"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No hay cirugías
                                                registradas
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="box-footer bg-light py-10 with-border">
                            <div class="d-flex align-items-center justify-content-between">
                                <p class="mb-0">Total <?= $total_cirugias; ?> cirugías registradas</p>
                                <a href="solicitudes/qx_reports.php"
                                   class="waves-effect waves-light btn btn-primary">Ver Todos</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-body px-0 pb-0">
                            <div class="px-20 bb-1 pb-15 d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">Plantillas Recientes</h4>
                                <div class="d-flex align-items-center justify-content-end">
                                    <button type="button"
                                            class="waves-effect waves-light btn btn-sm btn-primary-light btn-filter active"
                                            data-filter="all">All
                                    </button>
                                    <button type="button"
                                            class="waves-effect waves-light mx-10 btn btn-sm btn-primary-light btn-filter"
                                            data-filter="creado">Creado
                                    </button>
                                    <button type="button"
                                            class="waves-effect waves-light btn btn-sm btn-primary-light btn-filter"
                                            data-filter="modificado">Modificado
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="box-body">
                            <div class="inner-user-div4" id="plantilla-container">
                                <?php
                                if (!empty($plantillas)):
                                    foreach ($plantillas as $row): ?>
                                        <div class="d-flex justify-content-between align-items-center pb-20 mb-10 bb-dashed border-bottom plantilla-card"
                                             data-tipo="<?= $row['tipo'] ?>">
                                            <div class="pe-20">
                                                <p class="fs-12 text-fade"><?= date('d M Y', strtotime($row['fecha'])) ?>
                                                    <span class="mx-10">/</span> <?= $row['tipo'] ?></p>
                                                <h4><?= $row['membrete'] ?></h4>
                                                <p class="text-fade mb-5"><?= $row['cirugia'] ?></p>
                                                <div class="d-flex align-items-center">
                                                    <a href="editors/protocolos_editors_templates.php?id=<?= $row['id'] ?>"
                                                       class="waves-effect waves-light btn me-10 btn-xs btn-primary-light">Ver</a>
                                                    <a href="../generate_pdf.php?id=<?= $row['id'] ?>"
                                                       class="waves-effect waves-light btn btn-xs btn-primary-light">PDF</a>
                                                </div>
                                            </div>
                                            <div>
                                                <a href="edit_protocol.php?id=<?= $row['id'] ?>"
                                                   class="waves-effect waves-circle btn btn-circle btn-outline btn-light btn-lg"><i
                                                            class="fa fa-pencil"></i></a>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                else: ?>
                                    <p class="text-muted">No hay protocolos recientes.</p>
                                <?php endif; ?>
                            </div>
                            <div class="text-end mt-2 fs-12 text-fade">
                                <span id="plantilla-count">Mostrando <?= count($plantillas) ?> plantillas</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Diagnósticos más frecuentes</h4>
                        </div>
                        <div class="box-body">
                            <div class="news-slider owl-carousel owl-sl">
                                <?php if (!empty($diagnosticos_frecuentes)): ?>
                                    <?php
                                    $totalPacientesCount = array_sum($diagnosticos_frecuentes);
                                    foreach ($diagnosticos_frecuentes as $key => $cantidad):
                                        $porcentaje = round(($cantidad / $totalPacientesCount) * 100, 1);
                                        ?>
                                        <div>
                                            <div class="d-flex align-items-center mb-10">
                                                <div class="d-flex flex-column flex-grow-1 fw-500">
                                                    <p class="hover-primary text-fade mb-1 fs-14"><i
                                                                class="fa fa-stethoscope"></i> Diagnóstico
                                                    </p>
                                                    <span class="text-dark fs-16"><?= $key ?></span>
                                                    <p class="mb-0 fs-14"><?= $porcentaje ?>% de pacientes
                                                        <span class="badge badge-dot badge-primary"></span>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="progress progress-xs mt-5">
                                                <div class="progress-bar bg-success" role="progressbar"
                                                     style="width: <?= $porcentaje ?>%"
                                                     aria-valuenow="<?= $porcentaje ?>" aria-valuemin="0"
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No hay diagnósticos registrados.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xxxl-3 col-xl-4 col-12">
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Cirugías diarias</h4>
                </div>
                <div class="box-body">
                    <div id="total_patient"></div>
                </div>
            </div>
            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Últimas Solicitudes Quirúrgicas</h4>
                </div>
                <div class="box-body">
                    <?php if (!empty($solicitudes_quirurgicas['solicitudes'])): ?>
                        <?php foreach ($solicitudes_quirurgicas['solicitudes'] as $row): ?>
                            <div class="d-flex justify-content-between align-items-start mb-10 border-bottom pb-10">
                                <div>
                                    <strong><?= $row['fname'] . ' ' . $row['lname'] ?></strong><br>
                                    <span class="text-fade"><?= $row['procedimiento'] ?> | <?= date('d/m/Y', strtotime($row['fecha'])) ?></span>
                                </div>
                                <div>
                                    <a href="ver_solicitud.php?id=<?= $row['id'] ?>"
                                       class="btn btn-xs btn-primary-light">
                                        Ver
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No hay solicitudes registradas.</p>
                    <?php endif; ?>

                    <hr>
                    <p class="mb-0 text-end">
                        <strong>Total:</strong> <?= $solicitudes_quirurgicas['total'] ?> solicitud(es)</p>
                </div>
            </div>
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Doctor List</h4>
                    <p class="mb-0 pull-right">Últimos 3 meses</p>
                </div>
                <div class="box-body">
                    <div class="inner-user-div3">
                        <?php if (!empty($doctores_top)): ?>
                            <?php foreach ($doctores_top as $row): ?>
                                <div class="d-flex align-items-center mb-30">
                                    <div class="me-15">
                                        <img src="/public/images/avatar/avatar-<?= rand(1, 15) ?>.png"
                                             class="avatar avatar-lg rounded10 bg-primary-light" alt=""/>
                                    </div>
                                    <div class="d-flex flex-column flex-grow-1 fw-500">
                                        <span class="text-dark mb-1 fs-16"><?= htmlspecialchars($row['cirujano_1']) ?></span>
                                        <span class="text-fade">Cirugías: <?= $row['total'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No hay datos disponibles.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
$afiliaciones_json = json_encode($estadisticas_afiliacion['afiliaciones']);
$procedimientos_por_afiliacion_json = json_encode($estadisticas_afiliacion['totales']);

$incompletos_json = json_encode($revision_estados['incompletos']);
$revisados_json = json_encode($revision_estados['revisados']);
$no_revisados_json = json_encode($revision_estados['no_revisados']);
?>

<!-- Vendor JS -->
<script src="<?= asset('js/vendors.min.js') ?>"></script>
<script src="<?= asset('js/pages/chat-popup.js') ?>"></script>
<script src="<?= asset('assets/icons/feather-icons/feather.min.js') ?>"></script>

<script src="<?= asset('assets/vendor_components/apexcharts-bundle/dist/apexcharts.js') ?>"></script>
<script src="<?= asset('assets/vendor_components/OwlCarousel2/dist/owl.carousel.js') ?>"></script>

<!-- Doclinic App -->
<script src="<?= asset('js/template.js') ?>"></script>
<script src="<?= asset('js/pages/dashboard.js') ?>"></script>

<script>
    var fechas = <?php echo $fechas_json; ?>;
    var procedimientos_dia = <?php echo $procedimientos_dia_json; ?>;  // Usar nombre único
    var membretes = <?php echo $membretes_json; ?>;
    var procedimientos_membrete = <?php echo $procedimientos_membrete_json; ?>;  // Usar nombre único
    var afiliaciones = <?php echo $afiliaciones_json; ?>;
    var procedimientos_por_afiliacion = <?php echo $procedimientos_por_afiliacion_json; ?>;
    // Datos desde PHP
    var incompletos = <?php echo $incompletos_json; ?>;
    var revisados = <?php echo $revisados_json; ?>;
    var no_revisados = <?php echo $no_revisados_json; ?>;
</script>
<script src="<?= asset('js/pages/dashboard3.js?v=' . time()) ?>"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const filterButtons = document.querySelectorAll('.btn-filter');
        const cards = document.querySelectorAll('.plantilla-card');
        const countSpan = document.getElementById('plantilla-count');

        function updateCount() {
            const visibles = [...cards].filter(c => c.style.display !== 'none').length;
            countSpan.textContent = `Mostrando ${visibles} plantilla${visibles !== 1 ? 's' : ''}`;
        }

        function filterCards(type) {
            cards.forEach(card => {
                const tipo = card.dataset.tipo.toLowerCase();
                if (type === 'all' || tipo === type) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
            updateCount();
        }

        filterButtons.forEach(button => {
            button.addEventListener('click', function () {
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const type = this.dataset.filter;
                filterCards(type);
            });
        });

        updateCount(); // Inicial
    });
</script>