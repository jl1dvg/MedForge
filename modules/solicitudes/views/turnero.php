<?php
/** @var string $pageTitle */
?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Turnero de Coordinación Quirúrgica</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/solicitudes">Solicitudes</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Turnero</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <style>
        .turnero-wrapper {
            background: linear-gradient(145deg, #0f172a, #1e293b);
            border-radius: 24px;
            padding: 2.5rem;
            color: #e2e8f0;
            min-height: 60vh;
        }

        .turnero-header {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
            align-items: center;
        }

        .turnero-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: #f8fafc;
        }

        .turnero-clock {
            font-size: 1.5rem;
            font-weight: 600;
            color: #bae6fd;
        }

        .turno-card {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            min-height: 150px;
            box-shadow: 0 18px 30px rgba(15, 23, 42, 0.35);
        }

        .turno-numero {
            font-size: 4rem;
            font-weight: 800;
            color: #38bdf8;
            line-height: 1;
            min-width: 110px;
            text-align: center;
        }

        .turno-nombre {
            font-size: 2rem;
            font-weight: 600;
            color: #f8fafc;
        }

        .turno-detalle {
            font-size: 1.1rem;
            color: #94a3b8;
        }

        #turneroEmpty {
            background: rgba(59, 130, 246, 0.15);
            border: 1px dashed rgba(148, 163, 184, 0.4);
            color: #cbd5f5;
            font-size: 1.2rem;
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
                font-size: 1.75rem;
            }
        }
    </style>

    <div class="turnero-wrapper">
        <div class="turnero-header mb-4">
            <h2 class="turnero-title mb-0">Pacientes en cola</h2>
            <div class="d-flex gap-2 align-items-center">
                <span id="turneroClock" class="turnero-clock" aria-live="polite"></span>
                <button id="turneroRefresh" class="btn btn-outline-info btn-lg">
                    <i class="mdi mdi-refresh"></i> Actualizar
                </button>
            </div>
        </div>

        <p class="text-info mb-4" id="turneroLastUpdate" aria-live="polite"></p>

        <div id="turneroEmpty" class="alert" role="status">
            No hay pacientes en cola para coordinación quirúrgica.
        </div>

        <div id="turneroListado" class="row gy-4" aria-live="polite"></div>
    </div>
</section>

<?php if (function_exists('get_option')
    && get_option('pusher_realtime_notifications') == '1'
    && get_option('pusher_app_key') !== ''): ?>
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
<?php endif; ?>
<script>
    window.__KANBAN_MODULE__ = {
        key: 'solicitudes',
        basePath: '/solicitudes',
        selectors: {
            prefix: 'solicitudes',
        },
    };
</script>
<script type="module" src="<?= asset('js/pages/solicitudes/turnero.js') ?>"></script>
