<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\UserController;

$id = $_GET['id'] ?? null;
if (!$id) {
    die('ID no especificado.');
}

$controller = new UserController($pdo);
$user = $controller->getUserModel()->getUserById($id);
if (!$user) {
    die('Usuario no encontrado.');
}
?>
<div class="container-full">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Doctor Details</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Doctor Details</li>
                        </ol>
                    </nav>
                </div>
            </div>

        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xl-4 col-12">
                <div class="box">
                    <div class="box-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Your Patients today</h4>
                            <a href="#" class="">All patients <i class="ms-10 fa fa-angle-right"></i></a>
                        </div>
                    </div>
                    <div class="box-body p-15">
                        <div class="mb-10 d-flex justify-content-between align-items-center">
                            <div class="fw-600 min-w-120">
                                10:30am
                            </div>
                            <div class="w-p100 p-10 rounded10 justify-content-between align-items-center d-flex bg-lightest">
                                <div class="d-flex justify-content-between align-items-center">
                                    <img src="/public/images/avatar/1.jpg" class="me-10 avatar rounded-circle" alt="">
                                    <div>
                                        <h6 class="mb-0">Sarah Hostemn</h6>
                                        <p class="mb-0 fs-12 text-mute">Diagnosis: Bronchitis</p>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <a data-bs-toggle="dropdown" href="#"><i class="ti-more-alt rotate-90"></i></a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#"><i class="ti-import"></i> Details</a>
                                        <a class="dropdown-item" href="#"><i class="ti-export"></i> Lab Reports</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-10 d-flex justify-content-between align-items-center">
                            <div class="fw-600 min-w-120">
                                11:00am
                            </div>
                            <div class="w-p100 p-10 rounded10 justify-content-between align-items-center d-flex bg-lightest">
                                <div class="d-flex justify-content-between align-items-center">
                                    <img src="/public/images/avatar/2.jpg" class="me-10 avatar rounded-circle" alt="">
                                    <div>
                                        <h6 class="mb-0">Dakota Smith</h6>
                                        <p class="mb-0 fs-12 text-mute">Diagnosis: Stroke</p>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <a data-bs-toggle="dropdown" href="#"><i class="ti-more-alt rotate-90"></i></a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#"><i class="ti-import"></i> Details</a>
                                        <a class="dropdown-item" href="#"><i class="ti-export"></i> Lab Reports</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="fw-600 min-w-120">
                                11:30am
                            </div>
                            <div class="w-p100 p-10 rounded10 justify-content-between align-items-center d-flex bg-lightest">
                                <div class="d-flex justify-content-between align-items-center">
                                    <img src="/public/images/avatar/3.jpg" class="me-10 avatar rounded-circle" alt="">
                                    <div>
                                        <h6 class="mb-0">John Lane</h6>
                                        <p class="mb-0 fs-12 text-mute">Diagnosis: Liver cimhosis</p>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <a data-bs-toggle="dropdown" href="#"><i class="ti-more-alt rotate-90"></i></a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="#"><i class="ti-import"></i> Details</a>
                                        <a class="dropdown-item" href="#"><i class="ti-export"></i> Lab Reports</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Appointments</h4>
                    </div>
                    <div class="box-body">
                        <div id="paginator1" class="datepaginator">
                            <ul class="pagination">
                                <li><a href="#" class="dp-nav dp-nav-left" title="" style="width: 20px;"><i
                                                class="glyphicon glyphicon-chevron-left dp-nav-left"></i></a></li>
                                <li><a href="#" class="dp-item dp-divider" data-moment="2025-05-26"
                                       title="Monday, 26th May 2025" style="width: 36px;">Mon<br>26th</a></li>
                                <li><a href="#" class="dp-item" data-moment="2025-05-27" title="Tuesday, 27th May 2025"
                                       style="width: 36px;">Tue<br>27th</a></li>
                                <li><a href="#" class="dp-item" data-moment="2025-05-28"
                                       title="Wednesday, 28th May 2025" style="width: 36px;">Wed<br>28th</a></li>
                                <li><a href="#" class="dp-item" data-moment="2025-05-29" title="Thursday, 29th May 2025"
                                       style="width: 36px;">Thu<br>29th</a></li>
                                <li><a href="#" class="dp-item" data-moment="2025-05-30" title="Friday, 30th May 2025"
                                       style="width: 36px;">Fri<br>30th</a></li>
                                <li><a href="#" class="dp-item dp-selected dp-today dp-off" data-moment="2025-05-31"
                                       title="Saturday, 31st May 2025" style="width: 146px;"><i id="dp-calendar"
                                                                                                class="glyphicon glyphicon-calendar"></i>Saturday<br>May
                                        31st 2025</a></li>
                                <li><a href="#" class="dp-item dp-off" data-moment="2025-06-01"
                                       title="Sunday, 1st June 2025" style="width: 36px;">Sun<br>1st</a></li>
                                <li><a href="#" class="dp-item dp-divider" data-moment="2025-06-02"
                                       title="Monday, 2nd June 2025" style="width: 36px;">Mon<br>2nd</a></li>
                                <li><a href="#" class="dp-item" data-moment="2025-06-03" title="Tuesday, 3rd June 2025"
                                       style="width: 36px;">Tue<br>3rd</a></li>
                                <li><a href="#" class="dp-item" data-moment="2025-06-04"
                                       title="Wednesday, 4th June 2025" style="width: 36px;">Wed<br>4th</a></li>
                                <li><a href="#" class="dp-nav dp-nav-right" title="" style="width: 20px;"><i
                                                class="glyphicon glyphicon-chevron-right dp-nav-right"></i></a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="slimScrollDiv"
                             style="position: relative; overflow: hidden; width: auto; height: 350px;">
                            <div class="inner-user-div4" style="overflow: hidden; width: auto; height: 350px;">
                                <div>
                                    <div class="d-flex align-items-center mb-10">
                                        <div class="me-15">
                                            <img src="/public/images/avatar/avatar-1.png"
                                                 class="avatar avatar-lg rounded10 bg-primary-light" alt="">
                                        </div>
                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                            <p class="hover-primary text-fade mb-1 fs-14">Shawn Hampton</p>
                                            <span class="text-dark fs-16">Emergency appointment</span>
                                        </div>
                                        <div>
                                            <a href="#"
                                               class="waves-effect waves-circle btn btn-circle btn-primary-light btn-sm"><i
                                                        class="fa fa-phone"></i></a>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-end mb-15 py-10 bb-dashed border-bottom">
                                        <div>
                                            <p class="mb-0 text-muted"><i class="fa fa-clock-o me-5"></i> 10:00 <span
                                                        class="mx-20">$ 30</span></p>
                                        </div>
                                        <div>
                                            <div class="dropdown">
                                                <a data-bs-toggle="dropdown" href="#" class="base-font mx-10"><i
                                                            class="ti-more-alt text-muted"></i></a>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="#"><i class="ti-import"></i>
                                                        Import</a>
                                                    <a class="dropdown-item" href="#"><i class="ti-export"></i>
                                                        Export</a>
                                                    <a class="dropdown-item" href="#"><i class="ti-printer"></i>
                                                        Print</a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="#"><i class="ti-settings"></i>
                                                        Settings</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center mb-10">
                                        <div class="me-15">
                                            <img src="/public/images/avatar/avatar-2.png"
                                                 class="avatar avatar-lg rounded10 bg-primary-light" alt="">
                                        </div>
                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                            <p class="hover-primary text-fade mb-1 fs-14">Polly Paul</p>
                                            <span class="text-dark fs-16">USG + Consultation</span>
                                        </div>
                                        <div>
                                            <a href="#"
                                               class="waves-effect waves-circle btn btn-circle btn-primary-light btn-sm"><i
                                                        class="fa fa-phone"></i></a>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-end mb-15 py-10 bb-dashed border-bottom">
                                        <div>
                                            <p class="mb-0 text-muted"><i class="fa fa-clock-o me-5"></i> 10:30 <span
                                                        class="mx-20">$ 50</span></p>
                                        </div>
                                        <div>
                                            <div class="dropdown">
                                                <a data-bs-toggle="dropdown" href="#" class="base-font mx-10"><i
                                                            class="ti-more-alt text-muted"></i></a>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="#"><i class="ti-import"></i>
                                                        Import</a>
                                                    <a class="dropdown-item" href="#"><i class="ti-export"></i>
                                                        Export</a>
                                                    <a class="dropdown-item" href="#"><i class="ti-printer"></i>
                                                        Print</a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="#"><i class="ti-settings"></i>
                                                        Settings</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center mb-10">
                                        <div class="me-15">
                                            <img src="/public/images/avatar/avatar-3.png"
                                                 class="avatar avatar-lg rounded10 bg-primary-light" alt="">
                                        </div>
                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                            <p class="hover-primary text-fade mb-1 fs-14">Johen Doe</p>
                                            <span class="text-dark fs-16">Laboratory screening</span>
                                        </div>
                                        <div>
                                            <a href="#"
                                               class="waves-effect waves-circle btn btn-circle btn-primary-light btn-sm"><i
                                                        class="fa fa-phone"></i></a>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-end mb-15 py-10 bb-dashed border-bottom">
                                        <div>
                                            <p class="mb-0 text-muted"><i class="fa fa-clock-o me-5"></i> 11:00 <span
                                                        class="mx-20">$ 70</span></p>
                                        </div>
                                        <div>
                                            <div class="dropdown">
                                                <a data-bs-toggle="dropdown" href="#" class="base-font mx-10"><i
                                                            class="ti-more-alt text-muted"></i></a>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="#"><i class="ti-import"></i>
                                                        Import</a>
                                                    <a class="dropdown-item" href="#"><i class="ti-export"></i>
                                                        Export</a>
                                                    <a class="dropdown-item" href="#"><i class="ti-printer"></i>
                                                        Print</a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="#"><i class="ti-settings"></i>
                                                        Settings</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center mb-10">
                                        <div class="me-15">
                                            <img src="/public/images/avatar/avatar-4.png"
                                                 class="avatar avatar-lg rounded10 bg-primary-light" alt="">
                                        </div>
                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                            <p class="hover-primary text-fade mb-1 fs-14">Harmani Doe</p>
                                            <span class="text-dark fs-16">Keeping pregnant</span>
                                        </div>
                                        <div>
                                            <a href="#"
                                               class="waves-effect waves-circle btn btn-circle btn-primary-light btn-sm"><i
                                                        class="fa fa-phone"></i></a>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-end mb-15 py-10 bb-dashed border-bottom">
                                        <div>
                                            <p class="mb-0 text-muted"><i class="fa fa-clock-o me-5"></i> 11:30 </p>
                                        </div>
                                        <div>
                                            <div class="dropdown">
                                                <a data-bs-toggle="dropdown" href="#" class="base-font mx-10"><i
                                                            class="ti-more-alt text-muted"></i></a>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="#"><i class="ti-import"></i>
                                                        Import</a>
                                                    <a class="dropdown-item" href="#"><i class="ti-export"></i>
                                                        Export</a>
                                                    <a class="dropdown-item" href="#"><i class="ti-printer"></i>
                                                        Print</a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="#"><i class="ti-settings"></i>
                                                        Settings</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center mb-10">
                                        <div class="me-15">
                                            <img src="/public/images/avatar/avatar-5.png"
                                                 class="avatar avatar-lg rounded10 bg-primary-light" alt="">
                                        </div>
                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                            <p class="hover-primary text-fade mb-1 fs-14">Mark Wood</p>
                                            <span class="text-dark fs-16">Primary doctor consultation</span>
                                        </div>
                                        <div>
                                            <a href="#"
                                               class="waves-effect waves-circle btn btn-circle btn-primary-light btn-sm"><i
                                                        class="fa fa-phone"></i></a>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-end mb-15 py-10 bb-dashed border-bottom">
                                        <div>
                                            <p class="mb-0 text-muted"><i class="fa fa-clock-o me-5"></i> 12:00 <span
                                                        class="mx-20">$ 30</span></p>
                                        </div>
                                        <div>
                                            <div class="dropdown">
                                                <a data-bs-toggle="dropdown" href="#" class="base-font mx-10"><i
                                                            class="ti-more-alt text-muted"></i></a>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="#"><i class="ti-import"></i>
                                                        Import</a>
                                                    <a class="dropdown-item" href="#"><i class="ti-export"></i>
                                                        Export</a>
                                                    <a class="dropdown-item" href="#"><i class="ti-printer"></i>
                                                        Print</a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="#"><i class="ti-settings"></i>
                                                        Settings</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center mb-10">
                                        <div class="me-15">
                                            <img src="/public/images/avatar/avatar-6.png"
                                                 class="avatar avatar-lg rounded10 bg-primary-light" alt="">
                                        </div>
                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                            <p class="hover-primary text-fade mb-1 fs-14">Shawn Marsh</p>
                                            <span class="text-dark fs-16">Emergency appointment</span>
                                        </div>
                                        <div>
                                            <a href="#"
                                               class="waves-effect waves-circle btn btn-circle btn-primary-light btn-sm"><i
                                                        class="fa fa-phone"></i></a>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-end mb-15 py-10 bb-dashed border-bottom">
                                        <div>
                                            <p class="mb-0 text-muted"><i class="fa fa-clock-o me-5"></i> 13:00 <span
                                                        class="mx-20">$ 90</span></p>
                                        </div>
                                        <div>
                                            <div class="dropdown">
                                                <a data-bs-toggle="dropdown" href="#" class="base-font mx-10"><i
                                                            class="ti-more-alt text-muted"></i></a>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="#"><i class="ti-import"></i>
                                                        Import</a>
                                                    <a class="dropdown-item" href="#"><i class="ti-export"></i>
                                                        Export</a>
                                                    <a class="dropdown-item" href="#"><i class="ti-printer"></i>
                                                        Print</a>
                                                    <div class="dropdown-divider"></div>
                                                    <a class="dropdown-item" href="#"><i class="ti-settings"></i>
                                                        Settings</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="slimScrollBar"
                                 style="background: rgb(0, 0, 0); width: 7px; position: absolute; top: 0px; opacity: 0.1; display: block; border-radius: 7px; z-index: 99; right: 1px; height: 176.768px;"></div>
                            <div class="slimScrollRail"
                                 style="width: 7px; height: 100%; position: absolute; top: 0px; display: none; border-radius: 7px; background: rgb(51, 51, 51); opacity: 0.2; z-index: 90; right: 1px;"></div>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header no-border">
                        <h4 class="box-title">Doctors Abilities</h4>
                    </div>
                    <div class="box-body" style="position: relative;">
                        <div id="chart123" style="min-height: 394.367px;">
                            <div id="apexchartsknjp80xh"
                                 class="apexcharts-canvas apexchartsknjp80xh apexcharts-theme-light"
                                 style="width: 512px; height: 394.367px;">
                                <svg id="SvgjsSvg1116" width="512" height="394.3666666666667"
                                     xmlns="http://www.w3.org/2000/svg" version="1.1"
                                     xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svgjs="http://svgjs.com/svgjs"
                                     class="apexcharts-svg" xmlns:data="ApexChartsNS" transform="translate(0, 0)"
                                     style="background: transparent;">
                                    <foreignObject x="0" y="0" width="512" height="394.3666666666667">
                                        <div class="apexcharts-legend apexcharts-align-center position-bottom"
                                             xmlns="http://www.w3.org/1999/xhtml"
                                             style="inset: auto 0px 5px; position: absolute;">
                                            <div class="apexcharts-legend-series" rel="1" data:collapsed="false"
                                                 style="margin: 0px 5px;"><span class="apexcharts-legend-marker" rel="1"
                                                                                data:collapsed="false"
                                                                                style="background: rgb(50, 70, 211); color: rgb(50, 70, 211); height: 12px; width: 12px; left: 0px; top: 0px; border-width: 0px; border-color: rgb(255, 255, 255); border-radius: 12px;"></span><span
                                                        class="apexcharts-legend-text" rel="1" i="0"
                                                        data:default-text="Operation" data:collapsed="false"
                                                        style="color: rgb(55, 61, 63); font-size: 12px; font-weight: 400; font-family: Helvetica, Arial, sans-serif;">Operation</span>
                                            </div>
                                            <div class="apexcharts-legend-series" rel="2" data:collapsed="false"
                                                 style="margin: 0px 5px;"><span class="apexcharts-legend-marker" rel="2"
                                                                                data:collapsed="false"
                                                                                style="background: rgb(0, 208, 255); color: rgb(0, 208, 255); height: 12px; width: 12px; left: 0px; top: 0px; border-width: 0px; border-color: rgb(255, 255, 255); border-radius: 12px;"></span><span
                                                        class="apexcharts-legend-text" rel="2" i="1"
                                                        data:default-text="Theraphy" data:collapsed="false"
                                                        style="color: rgb(55, 61, 63); font-size: 12px; font-weight: 400; font-family: Helvetica, Arial, sans-serif;">Theraphy</span>
                                            </div>
                                            <div class="apexcharts-legend-series" rel="3" data:collapsed="false"
                                                 style="margin: 0px 5px;"><span class="apexcharts-legend-marker" rel="3"
                                                                                data:collapsed="false"
                                                                                style="background: rgb(238, 49, 88); color: rgb(238, 49, 88); height: 12px; width: 12px; left: 0px; top: 0px; border-width: 0px; border-color: rgb(255, 255, 255); border-radius: 12px;"></span><span
                                                        class="apexcharts-legend-text" rel="3" i="2"
                                                        data:default-text="Mediation" data:collapsed="false"
                                                        style="color: rgb(55, 61, 63); font-size: 12px; font-weight: 400; font-family: Helvetica, Arial, sans-serif;">Mediation</span>
                                            </div>
                                            <div class="apexcharts-legend-series" rel="4" data:collapsed="false"
                                                 style="margin: 0px 5px;"><span class="apexcharts-legend-marker" rel="4"
                                                                                data:collapsed="false"
                                                                                style="background: rgb(255, 168, 0); color: rgb(255, 168, 0); height: 12px; width: 12px; left: 0px; top: 0px; border-width: 0px; border-color: rgb(255, 255, 255); border-radius: 12px;"></span><span
                                                        class="apexcharts-legend-text" rel="4" i="3"
                                                        data:default-text="Colestrol" data:collapsed="false"
                                                        style="color: rgb(55, 61, 63); font-size: 12px; font-weight: 400; font-family: Helvetica, Arial, sans-serif;">Colestrol</span>
                                            </div>
                                            <div class="apexcharts-legend-series" rel="5" data:collapsed="false"
                                                 style="margin: 0px 5px;"><span class="apexcharts-legend-marker" rel="5"
                                                                                data:collapsed="false"
                                                                                style="background: rgb(5, 130, 95); color: rgb(5, 130, 95); height: 12px; width: 12px; left: 0px; top: 0px; border-width: 0px; border-color: rgb(255, 255, 255); border-radius: 12px;"></span><span
                                                        class="apexcharts-legend-text" rel="5" i="4"
                                                        data:default-text="Heart%20Beat" data:collapsed="false"
                                                        style="color: rgb(55, 61, 63); font-size: 12px; font-weight: 400; font-family: Helvetica, Arial, sans-serif;">Heart Beat</span>
                                            </div>
                                        </div>
                                        <style type="text/css">

                                            .apexcharts-legend {
                                                display: flex;
                                                overflow: auto;
                                                padding: 0 10px;
                                            }

                                            .apexcharts-legend.position-bottom, .apexcharts-legend.position-top {
                                                flex-wrap: wrap
                                            }

                                            .apexcharts-legend.position-right, .apexcharts-legend.position-left {
                                                flex-direction: column;
                                                bottom: 0;
                                            }

                                            .apexcharts-legend.position-bottom.apexcharts-align-left, .apexcharts-legend.position-top.apexcharts-align-left, .apexcharts-legend.position-right, .apexcharts-legend.position-left {
                                                justify-content: flex-start;
                                            }

                                            .apexcharts-legend.position-bottom.apexcharts-align-center, .apexcharts-legend.position-top.apexcharts-align-center {
                                                justify-content: center;
                                            }

                                            .apexcharts-legend.position-bottom.apexcharts-align-right, .apexcharts-legend.position-top.apexcharts-align-right {
                                                justify-content: flex-end;
                                            }

                                            .apexcharts-legend-series {
                                                cursor: pointer;
                                                line-height: normal;
                                            }

                                            .apexcharts-legend.position-bottom .apexcharts-legend-series, .apexcharts-legend.position-top .apexcharts-legend-series {
                                                display: flex;
                                                align-items: center;
                                            }

                                            .apexcharts-legend-text {
                                                position: relative;
                                                font-size: 14px;
                                            }

                                            .apexcharts-legend-text *, .apexcharts-legend-marker * {
                                                pointer-events: none;
                                            }

                                            .apexcharts-legend-marker {
                                                position: relative;
                                                display: inline-block;
                                                cursor: pointer;
                                                margin-right: 3px;
                                                border-style: solid;
                                            }

                                            .apexcharts-legend.apexcharts-align-right .apexcharts-legend-series, .apexcharts-legend.apexcharts-align-left .apexcharts-legend-series {
                                                display: inline-block;
                                            }

                                            .apexcharts-legend-series.apexcharts-no-click {
                                                cursor: auto;
                                            }

                                            .apexcharts-legend .apexcharts-hidden-zero-series, .apexcharts-legend .apexcharts-hidden-null-series {
                                                display: none !important;
                                            }

                                            .apexcharts-inactive-legend {
                                                opacity: 0.45;
                                            }</style>
                                    </foreignObject>
                                    <g id="SvgjsG1118" class="apexcharts-inner apexcharts-graphical"
                                       transform="translate(79.16666666666666, 0)">
                                        <defs id="SvgjsDefs1117">
                                            <clipPath id="gridRectMaskknjp80xh">
                                                <rect id="SvgjsRect1120" width="361.6666666666667"
                                                      height="369.6666666666667" x="-3" y="-1" rx="0" ry="0" opacity="1"
                                                      stroke-width="0" stroke="none" stroke-dasharray="0"
                                                      fill="#fff"></rect>
                                            </clipPath>
                                            <clipPath id="gridRectMarkerMaskknjp80xh">
                                                <rect id="SvgjsRect1121" width="359.6666666666667"
                                                      height="371.6666666666667" x="-2" y="-2" rx="0" ry="0" opacity="1"
                                                      stroke-width="0" stroke="none" stroke-dasharray="0"
                                                      fill="#fff"></rect>
                                            </clipPath>
                                            <filter id="SvgjsFilter1130" filterUnits="userSpaceOnUse" width="200%"
                                                    height="200%" x="-50%" y="-50%">
                                                <feFlood id="SvgjsFeFlood1131" flood-color="#000000"
                                                         flood-opacity="0.45" result="SvgjsFeFlood1131Out"
                                                         in="SourceGraphic"></feFlood>
                                                <feComposite id="SvgjsFeComposite1132" in="SvgjsFeFlood1131Out"
                                                             in2="SourceAlpha" operator="in"
                                                             result="SvgjsFeComposite1132Out"></feComposite>
                                                <feOffset id="SvgjsFeOffset1133" dx="1" dy="1"
                                                          result="SvgjsFeOffset1133Out"
                                                          in="SvgjsFeComposite1132Out"></feOffset>
                                                <feGaussianBlur id="SvgjsFeGaussianBlur1134" stdDeviation="1 "
                                                                result="SvgjsFeGaussianBlur1134Out"
                                                                in="SvgjsFeOffset1133Out"></feGaussianBlur>
                                                <feMerge id="SvgjsFeMerge1135" result="SvgjsFeMerge1135Out"
                                                         in="SourceGraphic">
                                                    <feMergeNode id="SvgjsFeMergeNode1136"
                                                                 in="SvgjsFeGaussianBlur1134Out"></feMergeNode>
                                                    <feMergeNode id="SvgjsFeMergeNode1137"
                                                                 in="[object Arguments]"></feMergeNode>
                                                </feMerge>
                                                <feBlend id="SvgjsFeBlend1138" in="SourceGraphic"
                                                         in2="SvgjsFeMerge1135Out" mode="normal"
                                                         result="SvgjsFeBlend1138Out"></feBlend>
                                            </filter>
                                            <filter id="SvgjsFilter1142" filterUnits="userSpaceOnUse" width="200%"
                                                    height="200%" x="-50%" y="-50%">
                                                <feFlood id="SvgjsFeFlood1143" flood-color="#000000"
                                                         flood-opacity="0.45" result="SvgjsFeFlood1143Out"
                                                         in="SourceGraphic"></feFlood>
                                                <feComposite id="SvgjsFeComposite1144" in="SvgjsFeFlood1143Out"
                                                             in2="SourceAlpha" operator="in"
                                                             result="SvgjsFeComposite1144Out"></feComposite>
                                                <feOffset id="SvgjsFeOffset1145" dx="1" dy="1"
                                                          result="SvgjsFeOffset1145Out"
                                                          in="SvgjsFeComposite1144Out"></feOffset>
                                                <feGaussianBlur id="SvgjsFeGaussianBlur1146" stdDeviation="1 "
                                                                result="SvgjsFeGaussianBlur1146Out"
                                                                in="SvgjsFeOffset1145Out"></feGaussianBlur>
                                                <feMerge id="SvgjsFeMerge1147" result="SvgjsFeMerge1147Out"
                                                         in="SourceGraphic">
                                                    <feMergeNode id="SvgjsFeMergeNode1148"
                                                                 in="SvgjsFeGaussianBlur1146Out"></feMergeNode>
                                                    <feMergeNode id="SvgjsFeMergeNode1149"
                                                                 in="[object Arguments]"></feMergeNode>
                                                </feMerge>
                                                <feBlend id="SvgjsFeBlend1150" in="SourceGraphic"
                                                         in2="SvgjsFeMerge1147Out" mode="normal"
                                                         result="SvgjsFeBlend1150Out"></feBlend>
                                            </filter>
                                            <filter id="SvgjsFilter1154" filterUnits="userSpaceOnUse" width="200%"
                                                    height="200%" x="-50%" y="-50%">
                                                <feFlood id="SvgjsFeFlood1155" flood-color="#000000"
                                                         flood-opacity="0.45" result="SvgjsFeFlood1155Out"
                                                         in="SourceGraphic"></feFlood>
                                                <feComposite id="SvgjsFeComposite1156" in="SvgjsFeFlood1155Out"
                                                             in2="SourceAlpha" operator="in"
                                                             result="SvgjsFeComposite1156Out"></feComposite>
                                                <feOffset id="SvgjsFeOffset1157" dx="1" dy="1"
                                                          result="SvgjsFeOffset1157Out"
                                                          in="SvgjsFeComposite1156Out"></feOffset>
                                                <feGaussianBlur id="SvgjsFeGaussianBlur1158" stdDeviation="1 "
                                                                result="SvgjsFeGaussianBlur1158Out"
                                                                in="SvgjsFeOffset1157Out"></feGaussianBlur>
                                                <feMerge id="SvgjsFeMerge1159" result="SvgjsFeMerge1159Out"
                                                         in="SourceGraphic">
                                                    <feMergeNode id="SvgjsFeMergeNode1160"
                                                                 in="SvgjsFeGaussianBlur1158Out"></feMergeNode>
                                                    <feMergeNode id="SvgjsFeMergeNode1161"
                                                                 in="[object Arguments]"></feMergeNode>
                                                </feMerge>
                                                <feBlend id="SvgjsFeBlend1162" in="SourceGraphic"
                                                         in2="SvgjsFeMerge1159Out" mode="normal"
                                                         result="SvgjsFeBlend1162Out"></feBlend>
                                            </filter>
                                            <filter id="SvgjsFilter1166" filterUnits="userSpaceOnUse" width="200%"
                                                    height="200%" x="-50%" y="-50%">
                                                <feFlood id="SvgjsFeFlood1167" flood-color="#000000"
                                                         flood-opacity="0.45" result="SvgjsFeFlood1167Out"
                                                         in="SourceGraphic"></feFlood>
                                                <feComposite id="SvgjsFeComposite1168" in="SvgjsFeFlood1167Out"
                                                             in2="SourceAlpha" operator="in"
                                                             result="SvgjsFeComposite1168Out"></feComposite>
                                                <feOffset id="SvgjsFeOffset1169" dx="1" dy="1"
                                                          result="SvgjsFeOffset1169Out"
                                                          in="SvgjsFeComposite1168Out"></feOffset>
                                                <feGaussianBlur id="SvgjsFeGaussianBlur1170" stdDeviation="1 "
                                                                result="SvgjsFeGaussianBlur1170Out"
                                                                in="SvgjsFeOffset1169Out"></feGaussianBlur>
                                                <feMerge id="SvgjsFeMerge1171" result="SvgjsFeMerge1171Out"
                                                         in="SourceGraphic">
                                                    <feMergeNode id="SvgjsFeMergeNode1172"
                                                                 in="SvgjsFeGaussianBlur1170Out"></feMergeNode>
                                                    <feMergeNode id="SvgjsFeMergeNode1173"
                                                                 in="[object Arguments]"></feMergeNode>
                                                </feMerge>
                                                <feBlend id="SvgjsFeBlend1174" in="SourceGraphic"
                                                         in2="SvgjsFeMerge1171Out" mode="normal"
                                                         result="SvgjsFeBlend1174Out"></feBlend>
                                            </filter>
                                            <filter id="SvgjsFilter1178" filterUnits="userSpaceOnUse" width="200%"
                                                    height="200%" x="-50%" y="-50%">
                                                <feFlood id="SvgjsFeFlood1179" flood-color="#000000"
                                                         flood-opacity="0.45" result="SvgjsFeFlood1179Out"
                                                         in="SourceGraphic"></feFlood>
                                                <feComposite id="SvgjsFeComposite1180" in="SvgjsFeFlood1179Out"
                                                             in2="SourceAlpha" operator="in"
                                                             result="SvgjsFeComposite1180Out"></feComposite>
                                                <feOffset id="SvgjsFeOffset1181" dx="1" dy="1"
                                                          result="SvgjsFeOffset1181Out"
                                                          in="SvgjsFeComposite1180Out"></feOffset>
                                                <feGaussianBlur id="SvgjsFeGaussianBlur1182" stdDeviation="1 "
                                                                result="SvgjsFeGaussianBlur1182Out"
                                                                in="SvgjsFeOffset1181Out"></feGaussianBlur>
                                                <feMerge id="SvgjsFeMerge1183" result="SvgjsFeMerge1183Out"
                                                         in="SourceGraphic">
                                                    <feMergeNode id="SvgjsFeMergeNode1184"
                                                                 in="SvgjsFeGaussianBlur1182Out"></feMergeNode>
                                                    <feMergeNode id="SvgjsFeMergeNode1185"
                                                                 in="[object Arguments]"></feMergeNode>
                                                </feMerge>
                                                <feBlend id="SvgjsFeBlend1186" in="SourceGraphic"
                                                         in2="SvgjsFeMerge1183Out" mode="normal"
                                                         result="SvgjsFeBlend1186Out"></feBlend>
                                            </filter>
                                        </defs>
                                        <g id="SvgjsG1123" class="apexcharts-pie">
                                            <g id="SvgjsG1124" transform="translate(0, 0) scale(1)">
                                                <circle id="SvgjsCircle1125" r="78.00731707317074"
                                                        cx="177.83333333333334" cy="183.83333333333334"
                                                        fill="transparent"></circle>
                                                <g id="SvgjsG1126" class="apexcharts-slices">
                                                    <g id="SvgjsG1127" class="apexcharts-series apexcharts-pie-series"
                                                       seriesName="Operation" rel="1" data:realIndex="0">
                                                        <path id="SvgjsPath1128"
                                                              d="M 177.83333333333334 10.48373983739836 A 173.34959349593498 173.34959349593498 0 0 1 351.0672763654492 190.16441118402764 L 255.78860769778547 186.68231836614578 A 78.00731707317074 78.00731707317074 0 0 0 177.83333333333334 105.8260162601626 L 177.83333333333334 10.48373983739836 z"
                                                              fill="rgba(50,70,211,1)" fill-opacity="1"
                                                              stroke-opacity="1" stroke-linecap="butt" stroke-width="2"
                                                              stroke-dasharray="0"
                                                              class="apexcharts-pie-area apexcharts-donut-slice-0"
                                                              index="0" j="0" data:angle="92.09302325581395"
                                                              data:startAngle="0" data:strokeWidth="2" data:value="44"
                                                              data:pathOrig="M 177.83333333333334 10.48373983739836 A 173.34959349593498 173.34959349593498 0 0 1 351.0672763654492 190.16441118402764 L 255.78860769778547 186.68231836614578 A 78.00731707317074 78.00731707317074 0 0 0 177.83333333333334 105.8260162601626 L 177.83333333333334 10.48373983739836 z"
                                                              stroke="#ffffff"></path>
                                                    </g>
                                                    <g id="SvgjsG1139" class="apexcharts-series apexcharts-pie-series"
                                                       seriesName="Theraphy" rel="2" data:realIndex="1">
                                                        <path id="SvgjsPath1140"
                                                              d="M 351.0672763654492 190.16441118402764 A 173.34959349593498 173.34959349593498 0 0 1 98.57056245933738 338.0004333209193 L 142.16508644003517 253.20852832774702 A 78.00731707317074 78.00731707317074 0 0 0 255.78860769778547 186.68231836614578 L 351.0672763654492 190.16441118402764 z"
                                                              fill="rgba(0,208,255,1)" fill-opacity="1"
                                                              stroke-opacity="1" stroke-linecap="butt" stroke-width="2"
                                                              stroke-dasharray="0"
                                                              class="apexcharts-pie-area apexcharts-donut-slice-1"
                                                              index="0" j="1" data:angle="115.11627906976746"
                                                              data:startAngle="92.09302325581395" data:strokeWidth="2"
                                                              data:value="55"
                                                              data:pathOrig="M 351.0672763654492 190.16441118402764 A 173.34959349593498 173.34959349593498 0 0 1 98.57056245933738 338.0004333209193 L 142.16508644003517 253.20852832774702 A 78.00731707317074 78.00731707317074 0 0 0 255.78860769778547 186.68231836614578 L 351.0672763654492 190.16441118402764 z"
                                                              stroke="#ffffff"></path>
                                                    </g>
                                                    <g id="SvgjsG1151" class="apexcharts-series apexcharts-pie-series"
                                                       seriesName="Mediation" rel="3" data:realIndex="2">
                                                        <path id="SvgjsPath1152"
                                                              d="M 98.57056245933738 338.0004333209193 A 173.34959349593498 173.34959349593498 0 0 1 18.2916965360844 116.03548902202353 L 106.03959677457132 153.32430339324392 A 78.00731707317074 78.00731707317074 0 0 0 142.16508644003517 253.20852832774702 L 98.57056245933738 338.0004333209193 z"
                                                              fill="rgba(238,49,88,1)" fill-opacity="1"
                                                              stroke-opacity="1" stroke-linecap="butt" stroke-width="2"
                                                              stroke-dasharray="0"
                                                              class="apexcharts-pie-area apexcharts-donut-slice-2"
                                                              index="0" j="2" data:angle="85.81395348837208"
                                                              data:startAngle="207.2093023255814" data:strokeWidth="2"
                                                              data:value="41"
                                                              data:pathOrig="M 98.57056245933738 338.0004333209193 A 173.34959349593498 173.34959349593498 0 0 1 18.2916965360844 116.03548902202353 L 106.03959677457132 153.32430339324392 A 78.00731707317074 78.00731707317074 0 0 0 142.16508644003517 253.20852832774702 L 98.57056245933738 338.0004333209193 z"
                                                              stroke="#ffffff"></path>
                                                    </g>
                                                    <g id="SvgjsG1163" class="apexcharts-series apexcharts-pie-series"
                                                       seriesName="Colestrol" rel="4" data:realIndex="3">
                                                        <path id="SvgjsPath1164"
                                                              d="M 18.2916965360844 116.03548902202353 A 173.34959349593498 173.34959349593498 0 0 1 87.52853707411657 35.863318337769954 L 137.1961750166858 117.24682658532983 A 78.00731707317074 78.00731707317074 0 0 0 106.03959677457132 153.32430339324392 L 18.2916965360844 116.03548902202353 z"
                                                              fill="rgba(255,168,0,1)" fill-opacity="1"
                                                              stroke-opacity="1" stroke-linecap="butt" stroke-width="2"
                                                              stroke-dasharray="0"
                                                              class="apexcharts-pie-area apexcharts-donut-slice-3"
                                                              index="0" j="3" data:angle="35.58139534883719"
                                                              data:startAngle="293.0232558139535" data:strokeWidth="2"
                                                              data:value="17"
                                                              data:pathOrig="M 18.2916965360844 116.03548902202353 A 173.34959349593498 173.34959349593498 0 0 1 87.52853707411657 35.863318337769954 L 137.1961750166858 117.24682658532983 A 78.00731707317074 78.00731707317074 0 0 0 106.03959677457132 153.32430339324392 L 18.2916965360844 116.03548902202353 z"
                                                              stroke="#ffffff"></path>
                                                    </g>
                                                    <g id="SvgjsG1175" class="apexcharts-series apexcharts-pie-series"
                                                       seriesName="HeartxBeat" rel="5" data:realIndex="4">
                                                        <path id="SvgjsPath1176"
                                                              d="M 87.52853707411657 35.863318337769954 A 173.34959349593498 173.34959349593498 0 0 1 177.80307812185194 10.48374247766364 L 177.8197184881667 105.82601744828197 A 78.00731707317074 78.00731707317074 0 0 0 137.1961750166858 117.24682658532983 L 87.52853707411657 35.863318337769954 z"
                                                              fill="rgba(5,130,95,1)" fill-opacity="1"
                                                              stroke-opacity="1" stroke-linecap="butt" stroke-width="2"
                                                              stroke-dasharray="0"
                                                              class="apexcharts-pie-area apexcharts-donut-slice-4"
                                                              index="0" j="4" data:angle="31.395348837209326"
                                                              data:startAngle="328.6046511627907" data:strokeWidth="2"
                                                              data:value="15"
                                                              data:pathOrig="M 87.52853707411657 35.863318337769954 A 173.34959349593498 173.34959349593498 0 0 1 177.80307812185194 10.48374247766364 L 177.8197184881667 105.82601744828197 A 78.00731707317074 78.00731707317074 0 0 0 137.1961750166858 117.24682658532983 L 87.52853707411657 35.863318337769954 z"
                                                              stroke="#ffffff"></path>
                                                    </g>
                                                    <text id="SvgjsText1129" font-family="Helvetica, Arial, sans-serif"
                                                          x="268.30968986922187" y="96.6031606251349"
                                                          text-anchor="middle" dominant-baseline="auto" font-size="12px"
                                                          font-weight="600" fill="#ffffff"
                                                          class="apexcharts-text apexcharts-pie-label"
                                                          filter="url(#SvgjsFilter1130)"
                                                          style="font-family: Helvetica, Arial, sans-serif;">25.6%
                                                    </text>
                                                    <text id="SvgjsText1141" font-family="Helvetica, Arial, sans-serif"
                                                          x="241.33405356372677" y="292.28946572303573"
                                                          text-anchor="middle" dominant-baseline="auto" font-size="12px"
                                                          font-weight="600" fill="#ffffff"
                                                          class="apexcharts-text apexcharts-pie-label"
                                                          filter="url(#SvgjsFilter1142)"
                                                          style="font-family: Helvetica, Arial, sans-serif;">32.0%
                                                    </text>
                                                    <text id="SvgjsText1153" font-family="Helvetica, Arial, sans-serif"
                                                          x="59.647224442052604" y="226.57813170517835"
                                                          text-anchor="middle" dominant-baseline="auto" font-size="12px"
                                                          font-weight="600" fill="#ffffff"
                                                          class="apexcharts-text apexcharts-pie-label"
                                                          filter="url(#SvgjsFilter1154)"
                                                          style="font-family: Helvetica, Arial, sans-serif;">23.8%
                                                    </text>
                                                    <text id="SvgjsText1165" font-family="Helvetica, Arial, sans-serif"
                                                          x="82.71536615720227" y="101.6892742481396"
                                                          text-anchor="middle" dominant-baseline="auto" font-size="12px"
                                                          font-weight="600" fill="#ffffff"
                                                          class="apexcharts-text apexcharts-pie-label"
                                                          filter="url(#SvgjsFilter1166)"
                                                          style="font-family: Helvetica, Arial, sans-serif;">9.9%
                                                    </text>
                                                    <text id="SvgjsText1177" font-family="Helvetica, Arial, sans-serif"
                                                          x="143.82959816547037" y="62.842341448213276"
                                                          text-anchor="middle" dominant-baseline="auto" font-size="12px"
                                                          font-weight="600" fill="#ffffff"
                                                          class="apexcharts-text apexcharts-pie-label"
                                                          filter="url(#SvgjsFilter1178)"
                                                          style="font-family: Helvetica, Arial, sans-serif;">8.7%
                                                    </text>
                                                </g>
                                            </g>
                                        </g>
                                        <line id="SvgjsLine1187" x1="0" y1="0" x2="355.6666666666667" y2="0"
                                              stroke="#b6b6b6" stroke-dasharray="0" stroke-width="1"
                                              class="apexcharts-ycrosshairs"></line>
                                        <line id="SvgjsLine1188" x1="0" y1="0" x2="355.6666666666667" y2="0"
                                              stroke-dasharray="0" stroke-width="0"
                                              class="apexcharts-ycrosshairs-hidden"></line>
                                    </g>
                                    <g id="SvgjsG1119" class="apexcharts-annotations"></g>
                                </svg>
                                <div class="apexcharts-tooltip apexcharts-theme-dark">
                                    <div class="apexcharts-tooltip-series-group"><span class="apexcharts-tooltip-marker"
                                                                                       style="background-color: rgb(50, 70, 211);"></span>
                                        <div class="apexcharts-tooltip-text"
                                             style="font-family: Helvetica, Arial, sans-serif; font-size: 12px;">
                                            <div class="apexcharts-tooltip-y-group"><span
                                                        class="apexcharts-tooltip-text-label"></span><span
                                                        class="apexcharts-tooltip-text-value"></span></div>
                                            <div class="apexcharts-tooltip-z-group"><span
                                                        class="apexcharts-tooltip-text-z-label"></span><span
                                                        class="apexcharts-tooltip-text-z-value"></span></div>
                                        </div>
                                    </div>
                                    <div class="apexcharts-tooltip-series-group"><span class="apexcharts-tooltip-marker"
                                                                                       style="background-color: rgb(0, 208, 255);"></span>
                                        <div class="apexcharts-tooltip-text"
                                             style="font-family: Helvetica, Arial, sans-serif; font-size: 12px;">
                                            <div class="apexcharts-tooltip-y-group"><span
                                                        class="apexcharts-tooltip-text-label"></span><span
                                                        class="apexcharts-tooltip-text-value"></span></div>
                                            <div class="apexcharts-tooltip-z-group"><span
                                                        class="apexcharts-tooltip-text-z-label"></span><span
                                                        class="apexcharts-tooltip-text-z-value"></span></div>
                                        </div>
                                    </div>
                                    <div class="apexcharts-tooltip-series-group"><span class="apexcharts-tooltip-marker"
                                                                                       style="background-color: rgb(238, 49, 88);"></span>
                                        <div class="apexcharts-tooltip-text"
                                             style="font-family: Helvetica, Arial, sans-serif; font-size: 12px;">
                                            <div class="apexcharts-tooltip-y-group"><span
                                                        class="apexcharts-tooltip-text-label"></span><span
                                                        class="apexcharts-tooltip-text-value"></span></div>
                                            <div class="apexcharts-tooltip-z-group"><span
                                                        class="apexcharts-tooltip-text-z-label"></span><span
                                                        class="apexcharts-tooltip-text-z-value"></span></div>
                                        </div>
                                    </div>
                                    <div class="apexcharts-tooltip-series-group"><span class="apexcharts-tooltip-marker"
                                                                                       style="background-color: rgb(255, 168, 0);"></span>
                                        <div class="apexcharts-tooltip-text"
                                             style="font-family: Helvetica, Arial, sans-serif; font-size: 12px;">
                                            <div class="apexcharts-tooltip-y-group"><span
                                                        class="apexcharts-tooltip-text-label"></span><span
                                                        class="apexcharts-tooltip-text-value"></span></div>
                                            <div class="apexcharts-tooltip-z-group"><span
                                                        class="apexcharts-tooltip-text-z-label"></span><span
                                                        class="apexcharts-tooltip-text-z-value"></span></div>
                                        </div>
                                    </div>
                                    <div class="apexcharts-tooltip-series-group"><span class="apexcharts-tooltip-marker"
                                                                                       style="background-color: rgb(5, 130, 95);"></span>
                                        <div class="apexcharts-tooltip-text"
                                             style="font-family: Helvetica, Arial, sans-serif; font-size: 12px;">
                                            <div class="apexcharts-tooltip-y-group"><span
                                                        class="apexcharts-tooltip-text-label"></span><span
                                                        class="apexcharts-tooltip-text-value"></span></div>
                                            <div class="apexcharts-tooltip-z-group"><span
                                                        class="apexcharts-tooltip-text-z-label"></span><span
                                                        class="apexcharts-tooltip-text-z-value"></span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="resize-triggers">
                            <div class="expand-trigger">
                                <div style="width: 555px; height: 437px;"></div>
                            </div>
                            <div class="contract-trigger"></div>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Recovery rate</h4>
                    </div>
                    <div class="box-body">
                        <div class="mb-30">
                            <div class="d-flex align-items-center justify-content-between mb-5"><h5>80 %</h5><h5>
                                    Cold</h5></div>
                            <div class="progress  progress-xs">
                                <div class="progress-bar progress-bar-primary" role="progressbar" aria-valuenow="80"
                                     aria-valuemin="0" aria-valuemax="100" style="width: 80%">
                                </div>
                            </div>
                        </div>
                        <div class="mb-30">
                            <div class="d-flex align-items-center justify-content-between mb-5"><h5>24 %</h5><h5>
                                    Fracture</h5></div>
                            <div class="progress  progress-xs">
                                <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="24"
                                     aria-valuemin="0" aria-valuemax="100" style="width: 24%">
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-5"><h5>91 %</h5><h5>
                                    Ache</h5></div>
                            <div class="progress  progress-xs">
                                <div class="progress-bar progress-bar-info" role="progressbar" aria-valuenow="91"
                                     aria-valuemin="0" aria-valuemax="100" style="width: 91%">
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-5"><h5>50 %</h5><h5>
                                    Hematoma</h5></div>
                            <div class="progress  progress-xs">
                                <div class="progress-bar progress-bar-danger" role="progressbar" aria-valuenow="50"
                                     aria-valuemin="0" aria-valuemax="100" style="width: 50%">
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-5"><h5>72 %</h5><h5>
                                    Caries</h5></div>
                            <div class="progress  progress-xs">
                                <div class="progress-bar progress-bar-warning" role="progressbar" aria-valuenow="72"
                                     aria-valuemin="0" aria-valuemax="100" style="width: 72%">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-8 col-12">
                <div class="box">
                    <div class="box-body text-end min-h-150"
                         style="background-image:url(/public/images/gallery/landscape14.jpg); background-repeat: no-repeat; background-position: center;background-size: cover;">
                        <div class="bg-success rounded10 p-15 fs-18 d-inline"><i class="fa fa-stethoscope"></i> ENT
                            Specialist
                        </div>
                    </div>
                    <div class="box-body wed-up position-relative">
                        <div class="d-md-flex align-items-end">
                            <img src="/public/images/avatar/avatar-1.png" class="bg-success-light rounded10 me-20"
                                 alt="">
                            <div>
                                <h4>Dr. Johen doe</h4>
                                <p><i class="fa fa-clock-o"></i> Join on 15 May 2019, 10:00 AM</p>
                            </div>
                        </div>
                    </div>
                    <div class="box-body">
                        <h4>Biography</h4>
                        <p>Vestibulum tincidunt sit amet sapien et eleifend. Fusce pretium libero enim, nec lacinia est
                            ultrices id. Duis nibh sapien, ultrices in hendrerit ac, pulvinar ut mauris. Quisque eu
                            condimentum justo. In consectetur dapibus justo, et dapibus augue pellentesque sed. Etiam
                            pulvinar pharetra est, at euismod augue vulputate sed. Morbi id porta turpis, a porta
                            turpis. Suspendisse maximus finibus est at pellentesque. Integer ut sapien urna.</p>
                        <p>Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque
                            laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et quasi
                            architecto beatae vitae dicta sunt explicabo. Nemo enim ipsam voluptatem quia voluptas sit
                            aspernatur aut odit aut fugit, sed quia consequuntur magni dolores eos qui ratione
                            voluptatem sequi nesciunt. Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet,
                            consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et
                            dolore magnam aliquam quaerat voluptatem.</p>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header">
                        <h4 class="box-title">Assigned Patient</h4>
                    </div>
                    <div class="box-body">
                        <div class="media d-lg-flex d-block text-lg-start text-center" style="position: relative;">
                            <img class="me-3 img-fluid rounded bg-primary-light w-100" src="/public/images/avatar/1.jpg"
                                 alt="">
                            <div class="media-body my-10 my-lg-0">
                                <h4 class="mt-0 mb-2">Loky Doe</h4>
                                <h6 class="mb-4 text-primary">Cold &amp; Flue</h6>
                                <div class="d-flex justify-content-center justify-content-lg-start">
                                    <a href="javascript:void(0);" class="btn btn-sm btn-primary-light me-4">Unassign</a>
                                    <a href="javascript:void(0);" class="btn btn-sm btn-danger-light ">Imporvement</a>
                                </div>
                            </div>
                            <div id="chart" class="me-3" style="min-height: 120px;">
                                <div id="apexcharts7sm0240i"
                                     class="apexcharts-canvas apexcharts7sm0240i apexcharts-theme-light apexcharts-zoomable"
                                     style="width: 200px; height: 120px;">
                                    <svg id="SvgjsSvg1190" width="200" height="120" xmlns="http://www.w3.org/2000/svg"
                                         version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink"
                                         xmlns:svgjs="http://svgjs.com/svgjs" class="apexcharts-svg"
                                         xmlns:data="ApexChartsNS" transform="translate(0, 0)"
                                         style="background: transparent;">
                                        <g id="SvgjsG1192" class="apexcharts-inner apexcharts-graphical"
                                           transform="translate(12, 30)">
                                            <defs id="SvgjsDefs1191">
                                                <clipPath id="gridRectMask7sm0240i">
                                                    <rect id="SvgjsRect1197" width="186" height="79" x="-4" y="-2"
                                                          rx="0" ry="0" opacity="1" stroke-width="0" stroke="none"
                                                          stroke-dasharray="0" fill="#fff"></rect>
                                                </clipPath>
                                                <clipPath id="gridRectMarkerMask7sm0240i">
                                                    <rect id="SvgjsRect1198" width="182" height="79" x="-2" y="-2"
                                                          rx="0" ry="0" opacity="1" stroke-width="0" stroke="none"
                                                          stroke-dasharray="0" fill="#fff"></rect>
                                                </clipPath>
                                            </defs>
                                            <line id="SvgjsLine1196" x1="0" y1="0" x2="0" y2="75" stroke="#b6b6b6"
                                                  stroke-dasharray="3" class="apexcharts-xcrosshairs" x="0" y="0"
                                                  width="1" height="75" fill="#b1b9c4" filter="none" fill-opacity="0.9"
                                                  stroke-width="1"></line>
                                            <g id="SvgjsG1205" class="apexcharts-xaxis" transform="translate(0, 0)">
                                                <g id="SvgjsG1206" class="apexcharts-xaxis-texts-g"
                                                   transform="translate(0, -4)"></g>
                                            </g>
                                            <g id="SvgjsG1208" class="apexcharts-grid">
                                                <g id="SvgjsG1209" class="apexcharts-gridlines-horizontal"
                                                   style="display: none;">
                                                    <line id="SvgjsLine1211" x1="0" y1="0" x2="178" y2="0"
                                                          stroke="#e0e0e0" stroke-dasharray="0"
                                                          class="apexcharts-gridline"></line>
                                                    <line id="SvgjsLine1212" x1="0" y1="15" x2="178" y2="15"
                                                          stroke="#e0e0e0" stroke-dasharray="0"
                                                          class="apexcharts-gridline"></line>
                                                    <line id="SvgjsLine1213" x1="0" y1="30" x2="178" y2="30"
                                                          stroke="#e0e0e0" stroke-dasharray="0"
                                                          class="apexcharts-gridline"></line>
                                                    <line id="SvgjsLine1214" x1="0" y1="45" x2="178" y2="45"
                                                          stroke="#e0e0e0" stroke-dasharray="0"
                                                          class="apexcharts-gridline"></line>
                                                    <line id="SvgjsLine1215" x1="0" y1="60" x2="178" y2="60"
                                                          stroke="#e0e0e0" stroke-dasharray="0"
                                                          class="apexcharts-gridline"></line>
                                                    <line id="SvgjsLine1216" x1="0" y1="75" x2="178" y2="75"
                                                          stroke="#e0e0e0" stroke-dasharray="0"
                                                          class="apexcharts-gridline"></line>
                                                </g>
                                                <g id="SvgjsG1210" class="apexcharts-gridlines-vertical"
                                                   style="display: none;"></g>
                                                <line id="SvgjsLine1218" x1="0" y1="75" x2="178" y2="75"
                                                      stroke="transparent" stroke-dasharray="0"></line>
                                                <line id="SvgjsLine1217" x1="0" y1="1" x2="0" y2="75"
                                                      stroke="transparent" stroke-dasharray="0"></line>
                                            </g>
                                            <g id="SvgjsG1200" class="apexcharts-line-series apexcharts-plot-series">
                                                <g id="SvgjsG1201" class="apexcharts-series" seriesName="Heart"
                                                   data:longestSeries="true" rel="1" data:realIndex="0">
                                                    <path id="SvgjsPath1204"
                                                          d="M 0 69C 5.663636363636364 69 10.51818181818182 70.5 16.181818181818183 70.5C 21.845454545454547 70.5 26.700000000000003 60 32.36363636363637 60C 38.02727272727273 60 42.88181818181818 61.5 48.54545454545455 61.5C 54.20909090909091 61.5 59.06363636363637 0 64.72727272727273 0C 70.39090909090909 0 75.24545454545455 46.5 80.9090909090909 46.5C 86.57272727272728 46.5 91.42727272727272 42 97.0909090909091 42C 102.75454545454546 42 107.60909090909091 61.5 113.27272727272728 61.5C 118.93636363636365 61.5 123.7909090909091 49.5 129.45454545454547 49.5C 135.11818181818182 49.5 139.9727272727273 72 145.63636363636365 72C 151.3 72 156.15454545454546 64.5 161.8181818181818 64.5C 167.48181818181817 64.5 172.33636363636364 52.5 178 52.5"
                                                          fill="none" fill-opacity="1" stroke="rgba(5,130,95,0.85)"
                                                          stroke-opacity="1" stroke-linecap="butt" stroke-width="4"
                                                          stroke-dasharray="0" class="apexcharts-line" index="0"
                                                          clip-path="url(#gridRectMask7sm0240i)"
                                                          pathTo="M 0 69C 5.663636363636364 69 10.51818181818182 70.5 16.181818181818183 70.5C 21.845454545454547 70.5 26.700000000000003 60 32.36363636363637 60C 38.02727272727273 60 42.88181818181818 61.5 48.54545454545455 61.5C 54.20909090909091 61.5 59.06363636363637 0 64.72727272727273 0C 70.39090909090909 0 75.24545454545455 46.5 80.9090909090909 46.5C 86.57272727272728 46.5 91.42727272727272 42 97.0909090909091 42C 102.75454545454546 42 107.60909090909091 61.5 113.27272727272728 61.5C 118.93636363636365 61.5 123.7909090909091 49.5 129.45454545454547 49.5C 135.11818181818182 49.5 139.9727272727273 72 145.63636363636365 72C 151.3 72 156.15454545454546 64.5 161.8181818181818 64.5C 167.48181818181817 64.5 172.33636363636364 52.5 178 52.5"
                                                          pathFrom="M -1 75L -1 75L 16.181818181818183 75L 32.36363636363637 75L 48.54545454545455 75L 64.72727272727273 75L 80.9090909090909 75L 97.0909090909091 75L 113.27272727272728 75L 129.45454545454547 75L 145.63636363636365 75L 161.8181818181818 75L 178 75"></path>
                                                    <g id="SvgjsG1202" class="apexcharts-series-markers-wrap"
                                                       data:realIndex="0">
                                                        <g class="apexcharts-series-markers">
                                                            <circle id="SvgjsCircle1224" r="0" cx="0" cy="0"
                                                                    class="apexcharts-marker w6g01clw9 no-pointer-events"
                                                                    stroke="#ffffff" fill="#008ffb" fill-opacity="1"
                                                                    stroke-width="2" stroke-opacity="0.9"
                                                                    default-marker-size="0"></circle>
                                                        </g>
                                                    </g>
                                                </g>
                                                <g id="SvgjsG1203" class="apexcharts-datalabels" data:realIndex="0"></g>
                                            </g>
                                            <line id="SvgjsLine1219" x1="0" y1="0" x2="178" y2="0" stroke="#b6b6b6"
                                                  stroke-dasharray="0" stroke-width="1"
                                                  class="apexcharts-ycrosshairs"></line>
                                            <line id="SvgjsLine1220" x1="0" y1="0" x2="178" y2="0" stroke-dasharray="0"
                                                  stroke-width="0" class="apexcharts-ycrosshairs-hidden"></line>
                                            <g id="SvgjsG1221" class="apexcharts-yaxis-annotations"></g>
                                            <g id="SvgjsG1222" class="apexcharts-xaxis-annotations"></g>
                                            <g id="SvgjsG1223" class="apexcharts-point-annotations"></g>
                                            <rect id="SvgjsRect1225" width="0" height="0" x="0" y="0" rx="0" ry="0"
                                                  opacity="1" stroke-width="0" stroke="none" stroke-dasharray="0"
                                                  fill="#fefefe" class="apexcharts-zoom-rect"></rect>
                                            <rect id="SvgjsRect1226" width="0" height="0" x="0" y="0" rx="0" ry="0"
                                                  opacity="1" stroke-width="0" stroke="none" stroke-dasharray="0"
                                                  fill="#fefefe" class="apexcharts-selection-rect"></rect>
                                        </g>
                                        <rect id="SvgjsRect1195" width="0" height="0" x="0" y="0" rx="0" ry="0"
                                              opacity="1" stroke-width="0" stroke="none" stroke-dasharray="0"
                                              fill="#fefefe"></rect>
                                        <g id="SvgjsG1207" class="apexcharts-yaxis" rel="0"
                                           transform="translate(-18, 0)"></g>
                                        <g id="SvgjsG1193" class="apexcharts-annotations"></g>
                                    </svg>
                                    <div class="apexcharts-legend"></div>
                                    <div class="apexcharts-tooltip apexcharts-theme-light">
                                        <div class="apexcharts-tooltip-title"
                                             style="font-family: Helvetica, Arial, sans-serif; font-size: 12px;"></div>
                                        <div class="apexcharts-tooltip-series-group"><span
                                                    class="apexcharts-tooltip-marker"
                                                    style="background-color: rgb(0, 143, 251);"></span>
                                            <div class="apexcharts-tooltip-text"
                                                 style="font-family: Helvetica, Arial, sans-serif; font-size: 12px;">
                                                <div class="apexcharts-tooltip-y-group"><span
                                                            class="apexcharts-tooltip-text-label"></span><span
                                                            class="apexcharts-tooltip-text-value"></span></div>
                                                <div class="apexcharts-tooltip-z-group"><span
                                                            class="apexcharts-tooltip-text-z-label"></span><span
                                                            class="apexcharts-tooltip-text-z-value"></span></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="apexcharts-xaxistooltip apexcharts-xaxistooltip-bottom apexcharts-theme-light">
                                        <div class="apexcharts-xaxistooltip-text"
                                             style="font-family: Helvetica, Arial, sans-serif; font-size: 12px;"></div>
                                    </div>
                                    <div class="apexcharts-yaxistooltip apexcharts-yaxistooltip-0 apexcharts-yaxistooltip-left apexcharts-theme-light">
                                        <div class="apexcharts-yaxistooltip-text"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="media-footer align-self-center">
                                <div class="up-sign text-success">
                                    <i class="fa fa-caret-up fs-38"></i>
                                    <h3 class="text-success">10%</h3>
                                </div>
                            </div>
                            <div class="resize-triggers">
                                <div class="expand-trigger">
                                    <div style="width: 1090px; height: 149px;"></div>
                                </div>
                                <div class="contract-trigger"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header with-border">
                        <h4 class="box-title">Resent Review</h4>
                    </div>
                    <div class="box-body p-0">
                        <div class="slimScrollDiv"
                             style="position: relative; overflow: hidden; width: auto; height: 350px;">
                            <div class="inner-user-div" style="overflow: hidden; width: auto; height: 350px;">
                                <div class="media-list bb-1 bb-dashed border-light">
                                    <div class="media align-items-center">
                                        <a class="avatar avatar-lg status-success" href="#">
                                            <img src="/public/images/avatar/1.jpg" class="bg-success-light" alt="...">
                                        </a>
                                        <div class="media-body">
                                            <p class="fs-16">
                                                <a class="hover-primary" href="#">Theron Trump</a>
                                            </p>
                                            <span class="text-muted">2 day ago</span>
                                        </div>
                                        <div class="media-right">
                                            <div class="d-flex">
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star-o"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="media pt-0">
                                        <p class="text-fade">Vestibulum tincidunt sit amet sapien et eleifend. Fusce
                                            pretium libero enim, nec lacinia est ultrices id. Duis nibh sapien, ultrices
                                            in hendrerit ac, pulvinar ut mauris. Quisque eu condimentum justo. </p>
                                    </div>
                                </div>
                                <div class="media-list bb-1 bb-dashed border-light">
                                    <div class="media align-items-center">
                                        <a class="avatar avatar-lg status-success" href="#">
                                            <img src="/public/images/avatar/3.jpg" class="bg-success-light" alt="...">
                                        </a>
                                        <div class="media-body">
                                            <p class="fs-16">
                                                <a class="hover-primary" href="#">Johen Doe</a>
                                            </p>
                                            <span class="text-muted">5 day ago</span>
                                        </div>
                                        <div class="media-right">
                                            <div class="d-flex">
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star-half-o"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="media pt-0">
                                        <p class="text-fade">Praesent venenatis viverra turpis quis varius. Nullam
                                            ullamcorper congue urna, in sodales eros placerat non.</p>
                                    </div>
                                </div>
                                <div class="media-list">
                                    <div class="media align-items-center">
                                        <a class="avatar avatar-lg status-success" href="#">
                                            <img src="/public/images/avatar/4.jpg" class="bg-success-light" alt="...">
                                        </a>
                                        <div class="media-body">
                                            <p class="fs-16">
                                                <a class="hover-primary" href="#">Tyler Mark</a>
                                            </p>
                                            <span class="text-muted">7 day ago</span>
                                        </div>
                                        <div class="media-right">
                                            <div class="d-flex">
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="media pt-0">
                                        <p class="text-fade">Pellentesque a pretium orci. In hac habitasse platea
                                            dictumst. Nulla mattis odio enim, id euismod neque bibendum non.</p>
                                    </div>
                                </div>
                                <div class="media-list bb-1 bb-dashed border-light">
                                    <div class="media align-items-center">
                                        <a class="avatar avatar-lg status-success" href="#">
                                            <img src="/public/images/avatar/5.jpg" class="bg-success-light" alt="...">
                                        </a>
                                        <div class="media-body">
                                            <p class="fs-16">
                                                <a class="hover-primary" href="#">Theron Trump</a>
                                            </p>
                                            <span class="text-muted">2 day ago</span>
                                        </div>
                                        <div class="media-right">
                                            <div class="d-flex">
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star-half-o"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="media pt-0">
                                        <p class="text-fade">Curabitur condimentum molestie ligula iaculis euismod.
                                            Fusce nulla lectus, tincidunt eu consequat.</p>
                                    </div>
                                </div>
                                <div class="media-list bb-1 bb-dashed border-light">
                                    <div class="media align-items-center">
                                        <a class="avatar avatar-lg status-success" href="#">
                                            <img src="/public/images/avatar/6.jpg" class="bg-success-light" alt="...">
                                        </a>
                                        <div class="media-body">
                                            <p class="fs-16">
                                                <a class="hover-primary" href="#">Johen Doe</a>
                                            </p>
                                            <span class="text-muted">5 day ago</span>
                                        </div>
                                        <div class="media-right">
                                            <div class="d-flex">
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star"></i>
                                                <i class="text-warning fa fa-star-o"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="media pt-0">
                                        <p class="text-fade">Proin lacinia eleifend nulla eu ornare. Integer commodo
                                            elit purus. Suspendisse mattis gravida interdum. In laoreet nisi eget felis
                                            ornare, tempus luctus nulla pellentesque. Donec maximus lobortis
                                            ullamcorper. </p>
                                    </div>
                                </div>
                            </div>
                            <div class="slimScrollBar"
                                 style="background: rgb(0, 0, 0); width: 7px; position: absolute; top: 0px; opacity: 0.1; display: block; border-radius: 7px; z-index: 99; right: 1px; height: 203.827px;"></div>
                            <div class="slimScrollRail"
                                 style="width: 7px; height: 100%; position: absolute; top: 0px; display: none; border-radius: 7px; background: rgb(51, 51, 51); opacity: 0.2; z-index: 90; right: 1px;"></div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <a href="#" class="waves-effect waves-light d-block w-p100 btn btn-primary">See More Reviews</a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xl-6 col-12">
                        <div class="box">
                            <div class="box-body px-0 pb-0">
                                <div class="px-20 bb-1 pb-15 d-flex align-items-center justify-content-between">
                                    <h4 class="mb-0">Recent questions</h4>
                                    <div class="d-flex align-items-center justify-content-end">
                                        <button type="button"
                                                class="waves-effect waves-light btn btn-sm btn-primary-light">All
                                        </button>
                                        <button type="button"
                                                class="waves-effect waves-light mx-10 btn btn-sm btn-primary">Unread
                                        </button>
                                        <button type="button"
                                                class="waves-effect waves-light btn btn-sm btn-primary-light">New
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="box-body">
                                <div class="slimScrollDiv"
                                     style="position: relative; overflow: hidden; width: auto; height: 127px;">
                                    <div class="inner-user-div3" style="overflow: hidden; width: auto; height: 127px;">
                                        <div class="d-flex justify-content-between align-items-center pb-20 mb-10 bb-dashed border-bottom">
                                            <div class="pe-20">
                                                <p class="fs-12 text-fade">14 Jun 2021 <span class="mx-10">/</span>
                                                    01:05PM</p>
                                                <h4>Addiction blood bank bone marrow contagious disinfectants?</h4>
                                                <div class="d-flex align-items-center">
                                                    <button type="button"
                                                            class="waves-effect waves-light btn me-10 btn-xs btn-primary-light">
                                                        Read more
                                                    </button>
                                                    <button type="button"
                                                            class="waves-effect waves-light btn btn-xs btn-primary-light">
                                                        Reply
                                                    </button>
                                                </div>
                                            </div>
                                            <div>
                                                <a href="#"
                                                   class="waves-effect waves-circle btn btn-circle btn-outline btn-light btn-lg"><i
                                                            class="fa fa-comments"></i></a>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center pb-20 bb-dashed border-bottom">
                                            <div class="pe-20">
                                                <p class="fs-12 text-fade">17 Jun 2021 <span class="mx-10">/</span>
                                                    02:05PM</p>
                                                <h4>Triggered asthma anesthesia blood type bone marrow cartilage?</h4>
                                                <div class="d-flex align-items-center">
                                                    <button type="button"
                                                            class="waves-effect waves-light btn me-10 btn-xs btn-primary-light">
                                                        Read more
                                                    </button>
                                                    <button type="button"
                                                            class="waves-effect waves-light btn btn-xs btn-primary-light">
                                                        Reply
                                                    </button>
                                                </div>
                                            </div>
                                            <div>
                                                <a href="#"
                                                   class="waves-effect waves-circle btn btn-circle btn-outline btn-light btn-lg"><i
                                                            class="fa fa-comments"></i></a>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center pb-20 mb-10 bb-dashed border-bottom">
                                            <div class="pe-20">
                                                <p class="fs-12 text-fade">14 Jun 2021 <span class="mx-10">/</span>
                                                    01:05PM</p>
                                                <h4>Addiction blood bank bone marrow contagious disinfectants?</h4>
                                                <div class="d-flex align-items-center">
                                                    <button type="button"
                                                            class="waves-effect waves-light btn me-10 btn-xs btn-primary-light">
                                                        Read more
                                                    </button>
                                                    <button type="button"
                                                            class="waves-effect waves-light btn btn-xs btn-primary-light">
                                                        Reply
                                                    </button>
                                                </div>
                                            </div>
                                            <div>
                                                <a href="#"
                                                   class="waves-effect waves-circle btn btn-circle btn-outline btn-light btn-lg"><i
                                                            class="fa fa-comments"></i></a>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center pb-20 bb-dashed border-bottom">
                                            <div class="pe-20">
                                                <p class="fs-12 text-fade">17 Jun 2021 <span class="mx-10">/</span>
                                                    02:05PM</p>
                                                <h4>Triggered asthma anesthesia blood type bone marrow cartilage?</h4>
                                                <div class="d-flex align-items-center">
                                                    <button type="button"
                                                            class="waves-effect waves-light btn me-10 btn-xs btn-primary-light">
                                                        Read more
                                                    </button>
                                                    <button type="button"
                                                            class="waves-effect waves-light btn btn-xs btn-primary-light">
                                                        Reply
                                                    </button>
                                                </div>
                                            </div>
                                            <div>
                                                <a href="#"
                                                   class="waves-effect waves-circle btn btn-circle btn-outline btn-light btn-lg"><i
                                                            class="fa fa-comments"></i></a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="slimScrollBar"
                                         style="background: rgb(0, 0, 0); width: 7px; position: absolute; top: 0px; opacity: 0.1; display: block; border-radius: 7px; z-index: 99; right: 1px; height: 30.2608px;"></div>
                                    <div class="slimScrollRail"
                                         style="width: 7px; height: 100%; position: absolute; top: 0px; display: none; border-radius: 7px; background: rgb(51, 51, 51); opacity: 0.2; z-index: 90; right: 1px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6 col-12">
                        <div class="box">
                            <div class="box-header">
                                <h4 class="box-title">Laboratory tests</h4>
                            </div>
                            <div class="box-body">
                                <div class="news-slider owl-carousel owl-sl owl-loaded owl-drag">


                                    <div class="owl-stage-outer">
                                        <div class="owl-stage"
                                             style="transform: translate3d(-1535px, 0px, 0px); transition: 0.25s; width: 3584px;">
                                            <div class="owl-item cloned" style="width: 511.992px;">
                                                <div>
                                                    <div class="d-flex align-items-center mb-10">
                                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                                            <p class="hover-primary text-fade mb-1 fs-14"><i
                                                                        class="fa fa-link"></i> Johen Doe</p>
                                                            <span class="text-dark fs-16">Keeping pregnant</span>
                                                            <p class="mb-0 fs-14">Prga Test <span
                                                                        class="badge badge-dot badge-primary"></span>
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <div class="dropdown">
                                                                <a data-bs-toggle="dropdown" href="#"
                                                                   class="base-font mx-30"><i
                                                                            class="ti-more-alt text-muted"></i></a>
                                                                <div class="dropdown-menu dropdown-menu-end">
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-import"></i> Import</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-export"></i> Export</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-printer"></i> Print</a>
                                                                    <div class="dropdown-divider"></div>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-settings"></i> Settings</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-end py-10">
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Details</a>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Contact
                                                                Patient</a>
                                                        </div>
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light"><i
                                                                        class="fa fa-check"></i> Archive</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="owl-item cloned" style="width: 511.992px;">
                                                <div>
                                                    <div class="d-flex align-items-center mb-10">
                                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                                            <p class="hover-primary text-fade mb-1 fs-14"><i
                                                                        class="fa fa-link"></i> Polly Paul</p>
                                                            <span class="text-dark fs-16">USG + Consultation</span>
                                                            <p class="mb-0 fs-14">Marker Test <span
                                                                        class="badge badge-dot badge-primary"></span>
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <div class="dropdown">
                                                                <a data-bs-toggle="dropdown" href="#"
                                                                   class="base-font mx-30"><i
                                                                            class="ti-more-alt text-muted"></i></a>
                                                                <div class="dropdown-menu dropdown-menu-end">
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-import"></i> Import</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-export"></i> Export</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-printer"></i> Print</a>
                                                                    <div class="dropdown-divider"></div>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-settings"></i> Settings</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-end py-10">
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Details</a>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Contact
                                                                Patient</a>
                                                        </div>
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light"><i
                                                                        class="fa fa-check"></i> Archive</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="owl-item" style="width: 511.992px;">
                                                <div>
                                                    <div class="d-flex align-items-center mb-10">
                                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                                            <p class="hover-primary text-fade mb-1 fs-14"><i
                                                                        class="fa fa-link"></i> Shawn Hampton</p>
                                                            <span class="text-dark fs-16">Beta 2 Microglobulin</span>
                                                            <p class="mb-0 fs-14">Marker Test <span
                                                                        class="badge badge-dot badge-primary"></span>
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <div class="dropdown">
                                                                <a data-bs-toggle="dropdown" href="#"
                                                                   class="base-font mx-30"><i
                                                                            class="ti-more-alt text-muted"></i></a>
                                                                <div class="dropdown-menu dropdown-menu-end">
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-import"></i> Import</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-export"></i> Export</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-printer"></i> Print</a>
                                                                    <div class="dropdown-divider"></div>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-settings"></i> Settings</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-end py-10">
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Details</a>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Contact
                                                                Patient</a>
                                                        </div>
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light"><i
                                                                        class="fa fa-check"></i> Archive</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="owl-item active" style="width: 511.992px;">
                                                <div>
                                                    <div class="d-flex align-items-center mb-10">
                                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                                            <p class="hover-primary text-fade mb-1 fs-14"><i
                                                                        class="fa fa-link"></i> Johen Doe</p>
                                                            <span class="text-dark fs-16">Keeping pregnant</span>
                                                            <p class="mb-0 fs-14">Prga Test <span
                                                                        class="badge badge-dot badge-primary"></span>
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <div class="dropdown">
                                                                <a data-bs-toggle="dropdown" href="#"
                                                                   class="base-font mx-30"><i
                                                                            class="ti-more-alt text-muted"></i></a>
                                                                <div class="dropdown-menu dropdown-menu-end">
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-import"></i> Import</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-export"></i> Export</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-printer"></i> Print</a>
                                                                    <div class="dropdown-divider"></div>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-settings"></i> Settings</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-end py-10">
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Details</a>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Contact
                                                                Patient</a>
                                                        </div>
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light"><i
                                                                        class="fa fa-check"></i> Archive</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="owl-item" style="width: 511.992px;">
                                                <div>
                                                    <div class="d-flex align-items-center mb-10">
                                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                                            <p class="hover-primary text-fade mb-1 fs-14"><i
                                                                        class="fa fa-link"></i> Polly Paul</p>
                                                            <span class="text-dark fs-16">USG + Consultation</span>
                                                            <p class="mb-0 fs-14">Marker Test <span
                                                                        class="badge badge-dot badge-primary"></span>
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <div class="dropdown">
                                                                <a data-bs-toggle="dropdown" href="#"
                                                                   class="base-font mx-30"><i
                                                                            class="ti-more-alt text-muted"></i></a>
                                                                <div class="dropdown-menu dropdown-menu-end">
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-import"></i> Import</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-export"></i> Export</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-printer"></i> Print</a>
                                                                    <div class="dropdown-divider"></div>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-settings"></i> Settings</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-end py-10">
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Details</a>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Contact
                                                                Patient</a>
                                                        </div>
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light"><i
                                                                        class="fa fa-check"></i> Archive</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="owl-item cloned" style="width: 511.992px;">
                                                <div>
                                                    <div class="d-flex align-items-center mb-10">
                                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                                            <p class="hover-primary text-fade mb-1 fs-14"><i
                                                                        class="fa fa-link"></i> Shawn Hampton</p>
                                                            <span class="text-dark fs-16">Beta 2 Microglobulin</span>
                                                            <p class="mb-0 fs-14">Marker Test <span
                                                                        class="badge badge-dot badge-primary"></span>
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <div class="dropdown">
                                                                <a data-bs-toggle="dropdown" href="#"
                                                                   class="base-font mx-30"><i
                                                                            class="ti-more-alt text-muted"></i></a>
                                                                <div class="dropdown-menu dropdown-menu-end">
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-import"></i> Import</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-export"></i> Export</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-printer"></i> Print</a>
                                                                    <div class="dropdown-divider"></div>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-settings"></i> Settings</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-end py-10">
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Details</a>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Contact
                                                                Patient</a>
                                                        </div>
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light"><i
                                                                        class="fa fa-check"></i> Archive</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="owl-item cloned" style="width: 511.992px;">
                                                <div>
                                                    <div class="d-flex align-items-center mb-10">
                                                        <div class="d-flex flex-column flex-grow-1 fw-500">
                                                            <p class="hover-primary text-fade mb-1 fs-14"><i
                                                                        class="fa fa-link"></i> Johen Doe</p>
                                                            <span class="text-dark fs-16">Keeping pregnant</span>
                                                            <p class="mb-0 fs-14">Prga Test <span
                                                                        class="badge badge-dot badge-primary"></span>
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <div class="dropdown">
                                                                <a data-bs-toggle="dropdown" href="#"
                                                                   class="base-font mx-30"><i
                                                                            class="ti-more-alt text-muted"></i></a>
                                                                <div class="dropdown-menu dropdown-menu-end">
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-import"></i> Import</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-export"></i> Export</a>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-printer"></i> Print</a>
                                                                    <div class="dropdown-divider"></div>
                                                                    <a class="dropdown-item" href="#"><i
                                                                                class="ti-settings"></i> Settings</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-end py-10">
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Details</a>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light">Contact
                                                                Patient</a>
                                                        </div>
                                                        <div>
                                                            <a href="#"
                                                               class="waves-effect waves-light btn btn-sm btn-primary-light"><i
                                                                        class="fa fa-check"></i> Archive</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="owl-nav">
                                        <div class="owl-prev"><span aria-label="Previous">‹</span></div>
                                        <div class="owl-next"><span aria-label="Next">›</span></div>
                                    </div>
                                    <div class="owl-dots disabled"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- /.content -->

</div>