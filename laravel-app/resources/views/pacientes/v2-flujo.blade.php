@extends('layouts.medforge')

@push('styles')
    <link rel="stylesheet" href="/css/kanban-scroll.css">
    <style>
        .kanban-toolbar {
            gap: 1rem;
        }

        #loader {
            display: none;
        }

        .patient-flow-board {
            background: #f3f6fa;
            border: 1px solid #e4e9f0;
            border-radius: 8px;
            gap: 14px;
            min-height: 520px;
            overflow-x: auto;
        }

        .patient-flow-column {
            background: #eef2f6;
            border: 1px solid #dfe6ee;
            border-radius: 8px;
            flex: 0 0 318px;
            min-width: 318px;
            max-width: 318px;
            padding: 10px;
        }

        .patient-flow-column__header {
            align-items: center;
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            min-height: 32px;
        }

        .patient-flow-column__title {
            color: #263445;
            font-size: 0.92rem;
            font-weight: 700;
            letter-spacing: 0;
            margin: 0;
        }

        .patient-flow-column__count {
            background: #d8e1ec;
            border-radius: 999px;
            color: #46586d;
            font-size: 0.75rem;
            font-weight: 700;
            min-width: 28px;
            padding: 3px 8px;
            text-align: center;
        }

        .patient-flow-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-height: 420px;
        }

        .patient-flow-card {
            background: #fff;
            border: 1px solid #dce4ee;
            border-left: 4px solid #4b8fd8;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.06);
            color: #263445;
            cursor: grab;
            padding: 12px;
            transition: box-shadow 0.15s ease, transform 0.15s ease, border-color 0.15s ease;
        }

        .patient-flow-card:hover {
            border-color: #b8c8db;
            box-shadow: 0 8px 18px rgba(16, 24, 40, 0.10);
            transform: translateY(-1px);
        }

        .patient-flow-card--consulta { border-left-color: #1f9d7a; }
        .patient-flow-card--optometria { border-left-color: #6f67d8; }
        .patient-flow-card--examen { border-left-color: #d59623; }
        .patient-flow-card--cirugia { border-left-color: #d34b5b; }
        .patient-flow-card--visita { border-left-color: #3d7ac7; cursor: default; }

        .patient-flow-card__top {
            align-items: center;
            display: flex;
            gap: 8px;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .patient-flow-card__badge {
            border-radius: 999px;
            display: inline-flex;
            font-size: 0.72rem;
            font-weight: 700;
            line-height: 1;
            padding: 5px 8px;
            white-space: nowrap;
        }

        .patient-flow-card__badge--consulta { background: #dff5ee; color: #17654f; }
        .patient-flow-card__badge--optometria { background: #e9e7fb; color: #4e48a8; }
        .patient-flow-card__badge--examen { background: #fff0d1; color: #8a5d0a; }
        .patient-flow-card__badge--cirugia { background: #fde2e7; color: #9f2d3e; }
        .patient-flow-card__badge--visita { background: #e3edf9; color: #2e5e99; }

        .patient-flow-card__time {
            color: #66788d;
            font-size: 0.76rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .patient-flow-card__name {
            color: #182333;
            font-size: 0.98rem;
            font-weight: 750;
            line-height: 1.22;
            margin-bottom: 6px;
        }

        .patient-flow-card__meta {
            color: #5d6f84;
            display: grid;
            gap: 4px;
            font-size: 0.8rem;
            line-height: 1.25;
        }

        .patient-flow-card__procedure {
            color: #2f5f96;
            font-weight: 700;
            margin-top: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .patient-flow-card__footer {
            align-items: center;
            border-top: 1px solid #edf1f6;
            color: #7a8da3;
            display: flex;
            font-size: 0.72rem;
            justify-content: space-between;
            margin-top: 10px;
            padding-top: 8px;
        }

        .patient-flow-visit-lines {
            display: grid;
            gap: 6px;
            margin-top: 10px;
        }

        .patient-flow-visit-line {
            align-items: center;
            background: #f6f8fb;
            border: 1px solid #e7edf4;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            padding: 6px 8px;
        }

        .patient-flow-visit-line span:last-child {
            color: #40536a;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .patient-flow-summary {
            background: #fff;
            border: 1px solid #dfe6ee;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
            color: #34465c;
            padding: 10px 12px;
        }

        @media (max-width: 768px) {
            .patient-flow-column {
                flex-basis: 286px;
                min-width: 286px;
                max-width: 286px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Flujo de Pacientes</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Flujo de Pacientes</li>
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
                    <div class="box-header with-border">
                        <h4 class="box-title">Filtros del tablero</h4>
                    </div>
                    <div class="box-body">
                        <div class="row g-3 kanban-toolbar align-items-end">
                            <div class="col-md-3 col-sm-6">
                                <label for="kanbanDateFilter" class="form-label form-label-sm">Fecha de visita</label>
                                <input type="text" id="kanbanDateFilter" class="form-control" placeholder="YYYY-MM-DD">
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label for="kanbanAfiliacionFilter" class="form-label form-label-sm">Afiliación</label>
                                <select id="kanbanAfiliacionFilter" class="form-select">
                                    <option value="">Todas</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label for="kanbanDoctorFilter" class="form-label form-label-sm">Doctor</label>
                                <select id="kanbanDoctorFilter" class="form-select">
                                    <option value="">Todos</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="loader" class="text-center my-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <div class="small text-muted mt-2">Cargando tablero</div>
                </div>

                <ul class="nav nav-tabs nav-fill mb-3" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link tab-kanban active" data-tipo="visita" href="javascript:void(0);">
                            <i class="mdi mdi-account-multiple-outline me-1"></i> Todos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link tab-kanban" data-tipo="consulta" href="javascript:void(0);">
                            <i class="mdi mdi-stethoscope me-1"></i> Consultas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link tab-kanban" data-tipo="optometria" href="javascript:void(0);">
                            <i class="mdi mdi-glasses me-1"></i> Optometría
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link tab-kanban" data-tipo="cirugia" href="javascript:void(0);">
                            <i class="mdi mdi-hospital-box-outline me-1"></i> Cirugías
                        </a>
                    </li>
                </ul>

                <div id="kanban-summary" class="patient-flow-summary mb-3"></div>
                <div class="kanban-board kanban-board-wrapper patient-flow-board p-3 d-flex flex-nowrap"></div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    @vite('resources/js/v2/pacientes-flujo.js')
@endpush
