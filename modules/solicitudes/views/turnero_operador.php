<?php
/** @var string $pageTitle */
?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Panel de coordinación quirúrgica</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/solicitudes">Solicitudes</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Turnero · Operador</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="ms-auto">
            <a class="btn btn-outline-info" href="/solicitudes/turnero" target="_blank" rel="noopener">
                <i class="mdi mdi-monitor"></i> Ver pantalla de sala de espera
            </a>
        </div>
    </div>
</div>

<section class="content">
    <style>
        .turnero-wrapper {
            background: linear-gradient(145deg, #0f172a, #1e293b);
            border-radius: 24px;
            padding: 2rem;
            color: #e2e8f0;
        }

        .turnero-header {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
            align-items: center;
        }

        .turnero-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            color: #f8fafc;
        }

        .turnero-clock {
            font-size: 1.25rem;
            font-weight: 600;
            color: #bae6fd;
        }

        .turno-card {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 16px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            min-height: 120px;
            box-shadow: 0 18px 30px rgba(15, 23, 42, 0.35);
        }

        .turno-numero {
            font-size: 3.25rem;
            font-weight: 800;
            color: #38bdf8;
            line-height: 1;
            min-width: 90px;
            text-align: center;
        }

        .turno-nombre {
            font-size: 1.75rem;
            font-weight: 600;
            color: #f8fafc;
        }

        .turno-detalle {
            font-size: 1rem;
            color: #94a3b8;
        }

        #turneroEmpty {
            background: rgba(59, 130, 246, 0.15);
            border: 1px dashed rgba(148, 163, 184, 0.4);
            color: #cbd5f5;
            font-size: 1.1rem;
        }

        .turno-badge {
            background: rgba(56, 189, 248, 0.25);
            color: #38bdf8;
            border-radius: 999px;
            padding: 0.35rem 0.9rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .turno-estado {
            border-radius: 999px;
            padding: 0.35rem 0.9rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: rgba(148, 163, 184, 0.25);
            color: #e2e8f0;
        }

        .turno-estado.recibido {
            background: rgba(59, 130, 246, 0.25);
            color: #60a5fa;
        }

        .turno-estado.llamado {
            background: rgba(245, 158, 11, 0.25);
            color: #fbbf24;
        }

        .turno-estado.en-atencion {
            background: rgba(52, 211, 153, 0.25);
            color: #34d399;
        }

        .turno-estado.atendido {
            background: rgba(148, 163, 184, 0.35);
            color: #cbd5f5;
        }

        .turno-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }

        .turnero-current {
            margin-bottom: 1.5rem;
        }

        .turnero-current-card {
            border: 2px solid rgba(56, 189, 248, 0.35);
            background: rgba(15, 23, 42, 0.95);
        }

        .turnero-current-card .turno-numero {
            font-size: 4rem;
            color: #fbbf24;
        }

        .turnero-current-card .turno-estado {
            background: rgba(234, 179, 8, 0.2);
            color: #facc15;
        }

        .turnero-current-vacio {
            padding: 1.5rem;
            border-radius: 20px;
            background: rgba(30, 41, 59, 0.8);
            border: 1px dashed rgba(148, 163, 184, 0.4);
            color: #cbd5f5;
            text-align: center;
        }

        .turnero-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .turnero-controls .btn {
            min-width: 200px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .turnero-controls-status {
            flex: 1 1 100%;
            font-size: 1rem;
            color: #bae6fd;
        }

        @media (max-width: 992px) {
            .turnero-wrapper {
                padding: 1.5rem;
            }

            .turno-card {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }

            .turno-numero {
                font-size: 3.5rem;
                min-width: 100%;
                text-align: left;
            }

            .turno-nombre {
                font-size: 1.6rem;
            }
        }
    </style>

    <div class="turnero-wrapper">
        <div class="turnero-header mb-4">
            <div>
                <h2 class="turnero-title mb-1">Gestión de turnos recibidos</h2>
                <p class="mb-0 text-info">Llama, marca en atención o finaliza turnos para avisar a los pacientes.</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span id="turneroClock" class="turnero-clock" aria-live="polite"></span>
                <button id="turneroRefresh" class="btn btn-outline-info btn-lg">
                    <i class="mdi mdi-refresh"></i> Actualizar
                </button>
            </div>
        </div>

        <div class="turnero-current" aria-live="assertive">
            <h3 class="turnero-title mb-3">Turno en llamada</h3>
            <div id="turneroCurrent"></div>
        </div>

        <div id="turneroControls" class="turnero-controls" aria-live="polite">
            <button id="turneroCallNext" class="btn btn-info btn-lg">
                <i class="mdi mdi-bullhorn"></i> Llamar siguiente
            </button>
            <button id="turneroMarkAttending" class="btn btn-warning btn-lg">
                <i class="mdi mdi-account-check-outline"></i> Marcar en atención
            </button>
            <button id="turneroMarkDone" class="btn btn-success btn-lg">
                <i class="mdi mdi-check-circle-outline"></i> Finalizar turno
            </button>
            <p id="turneroControlStatus" class="turnero-controls-status mb-0" aria-live="assertive"></p>
        </div>

        <p class="text-info mb-4" id="turneroLastUpdate" aria-live="polite"></p>

        <div id="turneroEmpty" class="alert" role="status">
            No hay pacientes en cola para coordinación quirúrgica.
        </div>

        <div id="turneroListado" class="row gy-4" aria-live="polite"></div>
    </div>
</section>

<script type="module" src="<?= asset('js/pages/solicitudes/turnero.js') ?>"></script>
