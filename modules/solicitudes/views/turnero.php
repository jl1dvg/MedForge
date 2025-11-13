<?php
/** @var string|null $turneroContext */
/** @var string|null $turneroEmptyMessage */

$contextLabel = $turneroContext ?: 'Coordinación Quirúrgica';
$emptyMessage = $turneroEmptyMessage ?: 'No hay pacientes en cola para coordinación quirúrgica.';
$contextId = 'turneroContextLabel';
$contextFor = 'turneroTitle';
?>
<section class="content">
    <style>
        body.turnero-body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at top left, #1e293b 0%, #0f172a 45%, #0b1120 100%);
            color: #e2e8f0;
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .turnero-main {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(1.5rem, 4vw, 4rem);
        }

        .turnero-wrapper {
            width: min(1200px, 100%);
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.92));
            border-radius: 28px;
            padding: clamp(1.5rem, 4vw, 3rem);
            box-shadow: 0 25px 55px rgba(10, 12, 24, 0.45);
            border: 1px solid rgba(148, 163, 184, 0.25);
        }

        .turnero-header {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem clamp(1rem, 5vw, 2.5rem);
            align-items: center;
            justify-content: space-between;
            margin-bottom: clamp(1.5rem, 4vw, 2.5rem);
        }

        .turnero-heading {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .turnero-context {
            font-size: 0.95rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #94a3b8;
        }

        .turnero-title {
            font-size: clamp(2rem, 4vw, 2.75rem);
            font-weight: 700;
            margin: 0;
            color: #f8fafc;
        }

        .turnero-clock {
            font-size: clamp(1.35rem, 3vw, 1.75rem);
            font-weight: 600;
            color: #bae6fd;
        }

        .turnero-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .turnero-actions .btn {
            border-radius: 999px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            border-width: 2px;
        }

        .turnero-last-update {
            color: #7dd3fc;
            font-size: 1rem;
            margin-bottom: clamp(1.25rem, 3vw, 2rem);
        }

        .turnero-empty {
            display: none;
            background: rgba(59, 130, 246, 0.15);
            border: 1px dashed rgba(148, 163, 184, 0.4);
            color: #cbd5f5;
            font-size: 1.1rem;
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            text-align: center;
        }

        .turnero-empty[aria-hidden="false"] {
            display: block;
        }

        .turnero-list {
            display: flex;
            flex-direction: column;
            gap: clamp(1rem, 2.5vw, 1.75rem);
        }

        .turno-card {
            position: relative;
            display: flex;
            gap: clamp(1rem, 2.5vw, 1.75rem);
            align-items: center;
            padding: clamp(1.25rem, 3vw, 1.75rem);
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 22px;
            min-height: 150px;
            box-shadow: 0 18px 30px rgba(15, 23, 42, 0.35);
            transition: transform 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease;
        }

        .turno-card.is-llamado {
            border-color: rgba(250, 204, 21, 0.75);
            box-shadow: 0 22px 36px rgba(250, 204, 21, 0.25);
            animation: turneroBlink 1.4s ease-in-out infinite;
        }

        @keyframes turneroBlink {
            0%, 100% {
                box-shadow: 0 22px 36px rgba(250, 204, 21, 0.15);
                transform: translateY(0);
            }
            50% {
                box-shadow: 0 32px 48px rgba(250, 204, 21, 0.35);
                transform: translateY(-4px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .turno-card.is-llamado {
                animation-duration: 3s;
            }
        }

        .turno-numero {
            font-size: clamp(3rem, 6vw, 4.5rem);
            font-weight: 800;
            color: #38bdf8;
            line-height: 1;
            min-width: clamp(110px, 12vw, 140px);
            text-align: center;
        }

        .turno-detalles {
            flex: 1;
            min-width: 0;
        }

        .turno-nombre {
            font-size: clamp(1.5rem, 3.5vw, 2.25rem);
            font-weight: 600;
            color: #f8fafc;
            margin-bottom: 0.5rem;
        }

        .turno-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            align-items: center;
        }

        .turno-badge {
            background: rgba(56, 189, 248, 0.2);
            color: #38bdf8;
            border-radius: 999px;
            padding: 0.35rem 0.9rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        .turno-estado {
            border-radius: 999px;
            padding: 0.35rem 0.9rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: rgba(148, 163, 184, 0.25);
            color: #e2e8f0;
            font-size: 0.85rem;
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

        .turno-detalle {
            font-size: 1rem;
            color: #94a3b8;
        }

        @media (max-width: 992px) {
            .turnero-wrapper {
                padding: clamp(1.25rem, 4vw, 1.75rem);
            }

            .turno-card {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }

            .turno-numero {
                min-width: 0;
                text-align: left;
            }
        }

        @media (max-width: 576px) {
            .turnero-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>

    <div
        class="turnero-wrapper"
        data-turnero-context="<?= htmlspecialchars($contextLabel, ENT_QUOTES, 'UTF-8') ?>"
        role="region"
        aria-labelledby="<?= $contextFor ?>"
    >
        <div class="turnero-header">
            <div class="turnero-heading">
                <span id="<?= $contextId ?>" class="turnero-context">Turnero · <?= htmlspecialchars($contextLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <h1 id="<?= $contextFor ?>" class="turnero-title">Pacientes en cola</h1>
            </div>
            <div class="turnero-actions">
                <span id="turneroClock" class="turnero-clock" aria-live="polite">--:--:--</span>
                <button id="turneroRefresh" class="btn btn-outline-info btn-lg" type="button">
                    <i class="mdi mdi-refresh"></i>
                    <span class="ms-1">Actualizar</span>
                </button>
            </div>
        </div>

        <p class="turnero-last-update" id="turneroLastUpdate" aria-live="polite">Última actualización: --</p>

        <div
            id="turneroEmpty"
            class="turnero-empty"
            role="status"
            aria-hidden="false"
        >
            <?= htmlspecialchars($emptyMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div id="turneroListado" class="turnero-list" aria-live="polite" role="list"></div>
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
