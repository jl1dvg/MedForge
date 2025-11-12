<?php
/** @var array<int, array{time:string,name:string,diagnosis:string,avatar:string}> $patientsToday */
/** @var array<int, array<string, mixed>> $appointmentsCalendar */
/** @var array<int, array{name:string,note:string,time:string,price:?int,avatar:string}> $appointments */
/** @var array<int, array{label:string,value:int,color:string}> $abilities */
/** @var array<int, array{label:string,percentage:int,class:string}> $recoveryRates */
/** @var array{name:string,specialty:string,cover_image:string,photo:string,joined_at:string,biography:array<int,string>} $doctorProfile */
/** @var array{name:string,condition:string,photo:string,trend:array<int,int>,trend_labels:array<int,string>,improvement:int} $assignedPatient */
/** @var array<int, array{name:string,avatar:string,since:string,rating:float,comment:string}> $reviews */
/** @var array<int, array{date:string,time:string,title:string}> $questions */
/** @var array<int, array{patient:string,title:string,test:string,avatar:string}> $labTests */

$styles = array_merge($styles ?? [], [
    'assets/vendor_components/OwlCarousel2/dist/assets/owl.carousel.css',
    'assets/vendor_components/OwlCarousel2/dist/assets/owl.theme.default.css',
    'assets/vendor_components/date-paginator/bootstrap-datepaginator.min.css',
]);

$scripts = array_merge($scripts ?? [], [
    'assets/vendor_components/apexcharts-bundle/dist/apexcharts.js',
    'assets/vendor_components/OwlCarousel2/dist/owl.carousel.js',
]);

$abilitiesForChart = array_map(static function (array $ability): array {
    return [
        'label' => $ability['label'],
        'value' => (float) $ability['value'],
        'color' => $ability['color'],
    ];
}, $abilities);

$abilitiesJson = json_encode($abilitiesForChart, JSON_UNESCAPED_UNICODE);
$trendJson = json_encode($assignedPatient['trend'], JSON_UNESCAPED_UNICODE);
$trendLabelsJson = json_encode($assignedPatient['trend_labels'], JSON_UNESCAPED_UNICODE);

$inlineScripts = array_merge($inlineScripts ?? [], [
    <<<JS
(function () {
    function initDoctorCharts() {
        var abilityEl = document.querySelector('#doctorAbilitiesChart');
        if (abilityEl && typeof ApexCharts !== 'undefined') {
            try {
                var abilities = {$abilitiesJson};
                var labels = abilities.map(function (item) { return item.label; });
                var series = abilities.map(function (item) { return item.value; });
                var colors = abilities.map(function (item) { return item.color; });

                var abilityChart = new ApexCharts(abilityEl, {
                    chart: {
                        type: 'donut',
                        height: 320,
                    },
                    series: series,
                    labels: labels,
                    colors: colors,
                    legend: {
                        position: 'bottom'
                    },
                    stroke: {
                        width: 2,
                        colors: ['#ffffff']
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function (val) {
                            return val.toFixed(1) + '%';
                        }
                    },
                    responsive: [{
                        breakpoint: 768,
                        options: {
                            chart: { height: 280 }
                        }
                    }]
                });

                abilityChart.render();
            } catch (error) {
                console.error('No fue posible renderizar el gráfico de habilidades del doctor', error);
            }
        }

        var trendEl = document.querySelector('#assignedPatientTrend');
        if (trendEl && typeof ApexCharts !== 'undefined') {
            try {
                var trendData = {$trendJson};
                var trendLabels = {$trendLabelsJson};
                var trendChart = new ApexCharts(trendEl, {
                    chart: {
                        type: 'line',
                        height: 120,
                        toolbar: { show: false },
                        zoom: { enabled: false }
                    },
                    stroke: {
                        width: 4,
                        curve: 'smooth'
                    },
                    dataLabels: { enabled: false },
                    series: [{
                        name: 'Recuperación',
                        data: trendData
                    }],
                    xaxis: {
                        categories: trendLabels,
                        labels: {
                            show: false
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false }
                    },
                    yaxis: {
                        labels: { show: false }
                    },
                    grid: { show: false },
                    colors: ['#05825f'],
                    markers: {
                        size: 0
                    }
                });

                trendChart.render();
            } catch (error) {
                console.error('No fue posible renderizar el gráfico de tendencias del paciente asignado', error);
            }
        }
    }

    function initLabTestSlider() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.owlCarousel !== 'function') {
            return;
        }

        jQuery('.news-slider').owlCarousel({
            items: 1,
            loop: true,
            margin: 20,
            nav: true,
            dots: false,
            navText: ['<span aria-label="Anterior">‹</span>', '<span aria-label="Siguiente">›</span>']
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initDoctorCharts();
        initLabTestSlider();
    });
})();
JS
]);
?>

<section class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Panel de doctores</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Doctores</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="text-muted fw-500">
            <?= htmlspecialchars(count($patientsToday)) ?> pacientes programados hoy
        </div>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-xl-4 col-12">
            <div class="box">
                <div class="box-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Pacientes del día</h4>
                        <a href="/agenda" class="text-primary">Ver agenda <i class="ms-10 fa fa-angle-right"></i></a>
                    </div>
                </div>
                <div class="box-body p-15">
                    <?php foreach ($patientsToday as $patient): ?>
                        <div class="mb-10 d-flex justify-content-between align-items-center">
                            <div class="fw-600 min-w-120">
                                <?= htmlspecialchars($patient['time']) ?>
                            </div>
                            <div class="w-p100 p-10 rounded10 d-flex align-items-center justify-content-between bg-lightest">
                                <div class="d-flex align-items-center">
                                    <img src="<?= asset($patient['avatar']) ?>" class="me-10 avatar rounded-circle" alt="Paciente">
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars($patient['name']) ?></h6>
                                        <p class="mb-0 fs-12 text-mute">Diagnóstico: <?= htmlspecialchars($patient['diagnosis']) ?></p>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <a data-bs-toggle="dropdown" href="#" aria-label="Acciones"><i class="ti-more-alt rotate-90"></i></a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="/pacientes"><i class="ti-import"></i> Detalles</a>
                                        <a class="dropdown-item" href="/examenes"><i class="ti-export"></i> Laboratorio</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Citas</h4>
                </div>
                <div class="box-body">
                    <div id="doctorDatePaginator" class="datepaginator">
                        <ul class="pagination">
                            <?php foreach ($appointmentsCalendar as $item): ?>
                                <?php if (($item['type'] ?? '') === 'nav'): ?>
                                    <?php $isPrev = ($item['direction'] ?? '') === 'prev'; ?>
                                    <li>
                                        <span class="dp-nav <?= $isPrev ? 'dp-nav-left' : 'dp-nav-right' ?>" aria-hidden="true">
                                            <i class="glyphicon glyphicon-chevron-<?= $isPrev ? 'left' : 'right' ?>"></i>
                                        </span>
                                    </li>
                                <?php elseif (($item['type'] ?? '') === 'day'): ?>
                                    <?php
                                    $classes = ['dp-item'];
                                    if (!empty($item['divider'])) {
                                        $classes[] = 'dp-divider';
                                    }
                                    if (!empty($item['disabled'])) {
                                        $classes[] = 'dp-off';
                                    }
                                    if (!empty($item['active'])) {
                                        $classes[] = 'dp-selected';
                                    }
                                    if (!empty($item['is_today'])) {
                                        $classes[] = 'dp-today';
                                    }
                                    $width = !empty($item['wide']) ? 144 : 36;
                                    ?>
                                    <li>
                                        <span class="<?= implode(' ', $classes) ?>" style="width: <?= $width ?>px;"
                                              data-date="<?= htmlspecialchars($item['date']) ?>">
                                            <?php if (!empty($item['wide'])): ?>
                                                <i class="glyphicon glyphicon-calendar me-5"></i>
                                                <?= htmlspecialchars($item['weekday_full'] ?? $item['weekday'] ?? '') ?><br>
                                                <?= htmlspecialchars($item['month_label'] ?? '') ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($item['weekday'] ?? '') ?><br>
                                                <?= htmlspecialchars($item['day_label'] ?? '') ?>
                                            <?php endif; ?>
                                        </span>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <div class="box-body">
                    <div class="inner-user-div4" style="max-height: 350px; overflow-y: auto;">
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="mb-20 pb-15 bb-dashed border-bottom">
                                <div class="d-flex align-items-center mb-10">
                                    <div class="me-15">
                                        <img src="<?= asset($appointment['avatar']) ?>"
                                             class="avatar avatar-lg rounded10 bg-primary-light" alt="Paciente">
                                    </div>
                                    <div class="d-flex flex-column flex-grow-1 fw-500">
                                        <p class="hover-primary text-fade mb-1 fs-14"><?= htmlspecialchars($appointment['name']) ?></p>
                                        <span class="text-dark fs-16"><?= htmlspecialchars($appointment['note']) ?></span>
                                    </div>
                                    <div>
                                        <a href="tel:+593000000000"
                                           class="waves-effect waves-circle btn btn-circle btn-primary-light btn-sm"
                                           aria-label="Llamar"><i class="fa fa-phone"></i></a>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-end">
                                    <div>
                                        <p class="mb-0 text-muted"><i class="fa fa-clock-o me-5"></i>
                                            <?= htmlspecialchars($appointment['time']) ?>
                                            <?php if ($appointment['price'] !== null): ?>
                                                <span class="mx-20">$
                                                    <?= htmlspecialchars(number_format((float) $appointment['price'], 0, '.', ',')) ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="dropdown">
                                        <a data-bs-toggle="dropdown" href="#" class="base-font mx-10"
                                           aria-label="Opciones"><i class="ti-more-alt text-muted"></i></a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="/solicitudes"><i class="ti-import"></i> Importar</a>
                                            <a class="dropdown-item" href="/reportes"><i class="ti-export"></i> Exportar</a>
                                            <a class="dropdown-item" href="/reportes"><i class="ti-printer"></i> Imprimir</a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="/settings"><i class="ti-settings"></i> Configurar</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="box">
                <div class="box-header no-border">
                    <h4 class="box-title">Habilidades del equipo médico</h4>
                </div>
                <div class="box-body">
                    <div id="doctorAbilitiesChart"></div>
                </div>
            </div>

            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Tasa de recuperación</h4>
                </div>
                <div class="box-body">
                    <?php foreach ($recoveryRates as $rate): ?>
                        <div class="mb-20">
                            <div class="d-flex align-items-center justify-content-between mb-5">
                                <h5 class="mb-0"><?= htmlspecialchars($rate['percentage']) ?> %</h5>
                                <h5 class="mb-0"><?= htmlspecialchars($rate['label']) ?></h5>
                            </div>
                            <div class="progress progress-xs">
                                <div class="progress-bar progress-bar-<?= htmlspecialchars($rate['class']) ?>"
                                     role="progressbar"
                                     aria-valuenow="<?= htmlspecialchars($rate['percentage']) ?>"
                                     aria-valuemin="0"
                                     aria-valuemax="100"
                                     style="width: <?= htmlspecialchars($rate['percentage']) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-8 col-12">
            <div class="box">
                <div class="box-body text-end min-h-150"
                     style="background-image:url(<?= asset($doctorProfile['cover_image']) ?>); background-repeat: no-repeat; background-position: center; background-size: cover;">
                    <div class="bg-success rounded10 p-15 fs-18 d-inline">
                        <i class="fa fa-stethoscope"></i>
                        <?= htmlspecialchars($doctorProfile['specialty']) ?>
                    </div>
                </div>
                <div class="box-body wed-up position-relative">
                    <div class="d-md-flex align-items-end">
                        <img src="<?= asset($doctorProfile['photo']) ?>" class="bg-success-light rounded10 me-20" alt="Doctor">
                        <div>
                            <h4><?= htmlspecialchars($doctorProfile['name']) ?></h4>
                            <p><i class="fa fa-clock-o"></i> Incorporado el <?= htmlspecialchars($doctorProfile['joined_at']) ?></p>
                        </div>
                    </div>
                </div>
                <div class="box-body">
                    <h4>Biografía</h4>
                    <?php foreach ($doctorProfile['biography'] as $paragraph): ?>
                        <p><?= htmlspecialchars($paragraph) ?></p>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="box">
                <div class="box-header">
                    <h4 class="box-title">Paciente asignado</h4>
                </div>
                <div class="box-body">
                    <div class="media d-lg-flex d-block text-lg-start text-center">
                        <img class="me-3 img-fluid rounded bg-primary-light w-100 w-lg-auto"
                             src="<?= asset($assignedPatient['photo']) ?>" alt="Paciente asignado"
                             style="max-width: 160px;">
                        <div class="media-body my-10 my-lg-0">
                            <h4 class="mt-0 mb-2"><?= htmlspecialchars($assignedPatient['name']) ?></h4>
                            <h6 class="mb-4 text-primary"><?= htmlspecialchars($assignedPatient['condition']) ?></h6>
                            <div class="d-flex justify-content-center justify-content-lg-start">
                                <a href="javascript:void(0);" class="btn btn-sm btn-primary-light me-4">Desasignar</a>
                                <a href="javascript:void(0);" class="btn btn-sm btn-danger-light">Seguimiento</a>
                            </div>
                        </div>
                        <div class="ms-lg-3 w-100" style="max-width: 220px;">
                            <div id="assignedPatientTrend"></div>
                        </div>
                        <div class="media-footer align-self-center ms-lg-3 mt-3 mt-lg-0 text-success">
                            <i class="fa fa-caret-up fs-38"></i>
                            <h3 class="text-success mb-0"><?= htmlspecialchars($assignedPatient['improvement']) ?>%</h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Reseñas recientes</h4>
                </div>
                <div class="box-body p-0">
                    <div class="inner-user-div" style="max-height: 350px; overflow-y: auto;">
                        <?php foreach ($reviews as $review): ?>
                            <div class="media-list bb-1 bb-dashed border-light p-20">
                                <div class="media align-items-center mb-10">
                                    <a class="avatar avatar-lg status-success" href="#">
                                        <img src="<?= asset($review['avatar']) ?>" class="bg-success-light" alt="Avatar">
                                    </a>
                                    <div class="media-body ms-15">
                                        <p class="fs-16 mb-0">
                                            <span class="hover-primary"><?= htmlspecialchars($review['name']) ?></span>
                                        </p>
                                        <span class="text-muted"><?= htmlspecialchars($review['since']) ?></span>
                                    </div>
                                    <div class="media-right">
                                        <div class="d-flex">
                                            <?php
                                            $fullStars = (int) floor($review['rating']);
                                            $hasHalf = ($review['rating'] - $fullStars) >= 0.5;
                                            $totalStars = 5;
                                            for ($i = 0; $i < $fullStars; $i++): ?>
                                                <i class="text-warning fa fa-star"></i>
                                            <?php endfor; ?>
                                            <?php if ($hasHalf): ?>
                                                <i class="text-warning fa fa-star-half-o"></i>
                                            <?php endif; ?>
                                            <?php for ($i = $fullStars + ($hasHalf ? 1 : 0); $i < $totalStars; $i++): ?>
                                                <i class="text-warning fa fa-star-o"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="media pt-0">
                                    <p class="text-fade mb-0"><?= htmlspecialchars($review['comment']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="box-footer">
                    <a href="/pacientes" class="waves-effect waves-light d-block w-p100 btn btn-primary">Ver más reseñas</a>
                </div>
            </div>

            <div class="row">
                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-body px-0 pb-0">
                            <div class="px-20 bb-1 pb-15 d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">Preguntas recientes</h4>
                                <div class="d-flex align-items-center justify-content-end">
                                    <button type="button" class="waves-effect waves-light btn btn-sm btn-primary-light">Todas</button>
                                    <button type="button" class="waves-effect waves-light mx-10 btn btn-sm btn-primary">Nuevas</button>
                                    <button type="button" class="waves-effect waves-light btn btn-sm btn-primary-light">Pendientes</button>
                                </div>
                            </div>
                        </div>
                        <div class="box-body">
                            <div class="inner-user-div3" style="max-height: 180px; overflow-y: auto;">
                                <?php foreach ($questions as $question): ?>
                                    <div class="d-flex justify-content-between align-items-center pb-20 mb-10 bb-dashed border-bottom">
                                        <div class="pe-20">
                                            <p class="fs-12 text-fade mb-5">
                                                <?= htmlspecialchars($question['date']) ?> <span class="mx-10">/</span> <?= htmlspecialchars($question['time']) ?>
                                            </p>
                                            <h5 class="mb-10"><?= htmlspecialchars($question['title']) ?></h5>
                                            <div class="d-flex align-items-center">
                                                <button type="button" class="waves-effect waves-light btn me-10 btn-xs btn-primary-light">Leer más</button>
                                                <button type="button" class="waves-effect waves-light btn btn-xs btn-primary-light">Responder</button>
                                            </div>
                                        </div>
                                        <div>
                                            <a href="#" class="waves-effect waves-circle btn btn-circle btn-outline btn-light btn-lg" aria-label="Comentarios"><i class="fa fa-comments"></i></a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 col-12">
                    <div class="box">
                        <div class="box-header">
                            <h4 class="box-title">Pruebas de laboratorio</h4>
                        </div>
                        <div class="box-body">
                            <div class="news-slider owl-carousel owl-theme">
                                <?php foreach ($labTests as $test): ?>
                                    <div class="item">
                                        <div class="d-flex align-items-center mb-10">
                                            <div class="me-15">
                                                <img src="<?= asset($test['avatar']) ?>" class="avatar avatar-lg rounded10 bg-primary-light" alt="Paciente">
                                            </div>
                                            <div class="d-flex flex-column flex-grow-1 fw-500">
                                                <p class="hover-primary text-fade mb-1 fs-14"><i class="fa fa-link"></i> <?= htmlspecialchars($test['patient']) ?></p>
                                                <span class="text-dark fs-16"><?= htmlspecialchars($test['title']) ?></span>
                                                <p class="mb-0 fs-14"><?= htmlspecialchars($test['test']) ?> <span class="badge badge-dot badge-primary"></span></p>
                                            </div>
                                            <div class="dropdown">
                                                <a data-bs-toggle="dropdown" href="#" class="base-font mx-10" aria-label="Opciones"><i class="ti-more-alt text-muted"></i></a>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="/examenes"><i class="ti-import"></i> Importar</a>
                                                    <a class="dropdown-item" href="/examenes"><i class="ti-export"></i> Exportar</a>
                                                    <a class="dropdown-item" href="/examenes"><i class="ti-printer"></i> Imprimir</a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="/settings"><i class="ti-settings"></i> Configurar</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-end py-10">
                                            <div class="btn-group" role="group">
                                                <a href="/examenes" class="waves-effect waves-light btn btn-sm btn-primary-light">Detalles</a>
                                                <a href="/pacientes" class="waves-effect waves-light btn btn-sm btn-primary-light">Contactar</a>
                                            </div>
                                            <a href="/examenes" class="waves-effect waves-light btn btn-sm btn-primary-light"><i class="fa fa-check"></i> Archivar</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
