<?php
$panels = [
    'examenes' => [
        'title' => 'Exámenes',
        'context' => 'Coordinación de Exámenes',
        'endpoint' => '/examenes/turnero-data',
        'empty' => 'No hay pacientes en cola para exámenes.',
        'accent' => 'panel-examenes',
        'estados' => ['Llamado'],
    ],
    'solicitudes' => [
        'title' => 'Quirúrgicas',
        'context' => 'Coordinación Quirúrgica',
        'endpoint' => '/solicitudes/turnero-data',
        'empty' => 'No hay pacientes en cola para coordinación quirúrgica.',
        'accent' => 'panel-quirurgico',
    ],
];

$turneroSettings = $turneroSettings ?? [];
?>
<section class="content">
    <style>
        body.turnero-body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at top left, #0b172b 0%, #0a1222 40%, #050915 100%);
            color: #e2e8f0;
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .turnero-main {
            padding: clamp(1.5rem, 3vw, 3rem);
        }

        .turnero-wrapper {
            max-width: 1500px;
            margin: 0 auto;
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.92));
            border-radius: 28px;
            padding: clamp(1.5rem, 4vw, 3rem);
            box-shadow: 0 25px 55px rgba(10, 12, 24, 0.45);
            border: 1px solid rgba(148, 163, 184, 0.25);
            overflow: hidden;
        }

        body.turnero-body.fullscreen-mode {
            padding: 0;
        }

        body.turnero-body.fullscreen-mode .turnero-wrapper {
            max-width: none;
            border-radius: 0;
            min-height: 100vh;
            width: 100%;
        }

        .turnero-header {
            display: grid;
            grid-template-columns: repeat(2, minmax(320px, 1fr));
            gap: 1rem;
            align-items: center;
            margin-bottom: clamp(1.25rem, 2vw, 1.75rem);
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
            margin: 0;
            font-size: clamp(2.05rem, 4vw, 2.8rem);
            font-weight: 800;
            color: #f8fafc;
        }

        .turnero-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #7dd3fc;
            font-weight: 600;
        }

        .turnero-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.65rem;
        }

        .turnero-actions .btn {
            border-radius: 999px;
            padding: 0.55rem 1.4rem;
            font-weight: 700;
            border-width: 2px;
            width: fit-content;
        }

        .turnero-actions .btn-fullscreen {
            background: rgba(148, 163, 184, 0.16);
            color: #e2e8f0;
        }

        .turnero-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(360px, 1fr));
            grid-template-areas: "examenes quirurgico";
            gap: clamp(1rem, 2.5vw, 2rem);
            align-items: start;
            overflow-x: auto;
        }

        .turnero-panel {
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.3);
            border-radius: 20px;
            padding: clamp(1rem, 2vw, 1.5rem);
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            min-height: 100%;
            box-shadow: 0 18px 32px rgba(8, 12, 24, 0.35);
        }

        .turnero-panel[data-key="examenes"] { grid-area: examenes; }
        .turnero-panel[data-key="solicitudes"] { grid-area: quirurgico; }

        .panel-header {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            align-items: flex-start;
        }

        .panel-label {
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.85rem;
            color: #cbd5f5;
            opacity: 0.9;
        }

        .panel-title {
            margin: 0;
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 800;
            color: #f8fafc;
        }

        .panel-actions {
            display: flex;
            gap: 0.35rem;
        }

        .chip-filter {
            border: 1px solid rgba(148, 163, 184, 0.4);
            color: #e2e8f0;
            border-radius: 999px;
            padding: 0.3rem 0.9rem;
            background: rgba(148, 163, 184, 0.12);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .chip-filter[aria-pressed="true"] {
            background: #38bdf8;
            color: #0b1120;
            border-color: #67e8f9;
            box-shadow: 0 10px 25px rgba(56, 189, 248, 0.35);
        }

        .status-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border-radius: 10px;
            padding: 0.35rem 0.7rem;
            background: rgba(148, 163, 184, 0.15);
            color: #cbd5e1;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-pill .count {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.1rem 0.55rem;
            border-radius: 999px;
            font-variant-numeric: tabular-nums;
        }

        .turnero-empty {
            display: none;
            background: rgba(59, 130, 246, 0.12);
            border: 1px dashed rgba(148, 163, 184, 0.35);
            color: #cbd5f5;
            font-size: 1rem;
            border-radius: 14px;
            padding: 0.9rem 1rem;
            text-align: center;
        }

        .turnero-empty[aria-hidden="false"] {
            display: block;
        }

        .turnero-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .turno-card {
            position: relative;
            display: grid;
            grid-template-columns: 1fr;
            justify-items: center;
            padding: 1.25rem 1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: 18px;
            min-height: 140px;
            box-shadow: 0 12px 24px rgba(10, 12, 24, 0.3);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .turno-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 32px rgba(10, 12, 24, 0.35);
            border-color: rgba(148, 163, 184, 0.45);
        }

        .turno-card.is-llamado {
            border-color: rgba(250, 204, 21, 0.55);
            box-shadow: 0 18px 32px rgba(250, 204, 21, 0.28);
            animation: pulseCall 2.5s ease-in-out infinite;
        }

        .turno-flash {
            animation: softFlash 1.2s ease-in-out 2;
        }

        @keyframes softFlash {
            0%, 100% { box-shadow: 0 0 0 rgba(125, 211, 252, 0); }
            50% { box-shadow: 0 0 22px rgba(125, 211, 252, 0.45); }
        }

        @keyframes pulseCall {
            0% { box-shadow: 0 18px 32px rgba(250, 204, 21, 0.12); }
            50% { box-shadow: 0 24px 44px rgba(250, 204, 21, 0.3); }
            100% { box-shadow: 0 18px 32px rgba(250, 204, 21, 0.12); }
        }

        .turno-numero {
            font-size: clamp(2.8rem, 6vw, 3.6rem);
            font-weight: 900;
            color: #f8fafc;
            line-height: 1;
            letter-spacing: 0.08em;
            min-width: clamp(90px, 10vw, 140px);
            text-align: center;
        }

        .turno-estado.recibido { background: rgba(59, 130, 246, 0.25); color: #60a5fa; }
        .turno-estado.llamado { background: rgba(245, 158, 11, 0.25); color: #fbbf24; }
        .turno-estado.en-atencion { background: rgba(52, 211, 153, 0.25); color: #34d399; }
        .turno-estado.atendido { background: rgba(148, 163, 184, 0.35); color: #cbd5f5; }

        .turno-detalle {
            font-size: 0.95rem;
            color: #94a3b8;
        }

        .turno-hora {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(148, 163, 184, 0.16);
            border-radius: 999px;
            padding: 0.3rem 0.65rem;
            color: #cbd5f5;
            font-weight: 700;
        }

        .turno-pin {
            position: absolute;
            top: 10px;
            left: 10px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 12px;
            background: rgba(148, 163, 184, 0.12);
            color: #e2e8f0;
            padding: 0.25rem 0.6rem;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .turno-pin[data-pinned="true"] {
            background: rgba(34, 211, 238, 0.2);
            border-color: rgba(34, 211, 238, 0.65);
            color: #67e8f9;
        }

        .turno-detalles-extendidos {
            margin-top: 0.5rem;
            background: rgba(148, 163, 184, 0.08);
            border: 1px dashed rgba(148, 163, 184, 0.35);
            border-radius: 12px;
            padding: 0.6rem 0.75rem;
        }

        .turno-detalles-extendidos summary {
            cursor: pointer;
            font-weight: 700;
            color: #e2e8f0;
            list-style: none;
        }

        .turno-detalles-extendidos summary::-webkit-details-marker {
            display: none;
        }

        .turno-detalles-extendidos summary::after {
            content: '⌄';
            margin-left: 0.35rem;
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .turno-detalles-extendidos[open] summary::after {
            content: '⌃';
        }

        .turno-extra-list {
            margin: 0.35rem 0 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.25rem 0.75rem;
            color: #cbd5e1;
            font-weight: 600;
        }

        .turno-extra-list span {
            color: #94a3b8;
            font-weight: 600;
        }

        .panel-examenes {
            border-top: 3px solid #fb923c;
        }

        .panel-examenes .panel-title { color: #fbbf24; }
        .panel-examenes .panel-label { color: #fcd34d; }

        .panel-quirurgico {
            border-top: 3px solid #22d3ee;
        }

        .panel-quirurgico .panel-title { color: #67e8f9; }
        .panel-quirurgico .panel-label { color: #67e8f9; }

        .audio-feedback {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(56, 189, 248, 0.15);
            border-radius: 12px;
            padding: 0.35rem 0.7rem;
            color: #67e8f9;
            font-weight: 700;
        }

        @media (max-width: 1200px) {
            .turnero-grid { grid-template-columns: repeat(2, minmax(300px, 1fr)); }
        }

        @media (max-width: 1024px) {
            .turnero-header { grid-template-columns: 1fr; }
            .turnero-actions { align-items: flex-start; }
        }

        @media (max-width: 900px) {
            .turnero-grid {
                grid-template-columns: 1fr;
                grid-template-areas:
                    "examenes"
                    "quirurgico";
            }
            .turno-numero { min-width: 0; text-align: center; }
        }
    </style>

    <div
        class="turnero-wrapper"
        id="turneroGrid"
        data-panels='<?= htmlspecialchars(json_encode($panels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
    >
        <div class="turnero-header">
            <div class="turnero-heading">
                <span class="turnero-context">Panel unificado</span>
                <h1 class="turnero-title">Turneros de exámenes y quirúrgicas</h1>
            </div>
            <div class="turnero-actions" aria-label="Controles generales">
                <div class="turnero-meta">
                    <span id="turneroClock">--:--:--</span>
                </div>
                <button id="turneroFullscreen" class="btn btn-outline-light btn-fullscreen" type="button">
                    <i class="mdi mdi-fullscreen"></i>
                    <span class="ms-1">Pantalla completa</span>
                </button>
                <button id="turneroRefresh" class="btn btn-outline-info" type="button">
                    <i class="mdi mdi-refresh"></i>
                    <span class="ms-1">Actualizar</span>
                </button>
            </div>
        </div>

        <p class="turnero-context" id="turneroLastUpdate" aria-live="polite">Última actualización: --</p>

        <div class="turnero-grid">
            <?php foreach (['examenes', 'solicitudes'] as $key): $panel = $panels[$key]; ?>
                <section
                    class="turnero-panel <?= htmlspecialchars($panel['accent'], ENT_QUOTES, 'UTF-8') ?>"
                    data-key="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                >
                    <div class="panel-header">
                        <div>
                            <div class="panel-label">Turnero · <?= htmlspecialchars($panel['context'], ENT_QUOTES, 'UTF-8') ?></div>
                            <h2 class="panel-title">Cola de <?= htmlspecialchars($panel['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        </div>
                        <div class="panel-actions" role="group" aria-label="Filtros por estado">
                            <?php foreach ([
                                'all' => 'Todos',
                                'en espera' => 'En espera',
                                'llamado' => 'Llamado',
                                'en atencion' => 'En atención',
                            ] as $filterValue => $label): ?>
                                <button
                                    type="button"
                                    class="chip-filter"
                                    data-filter-state="<?= htmlspecialchars($filterValue, ENT_QUOTES, 'UTF-8') ?>"
                                    aria-pressed="<?= $filterValue === 'all' ? 'true' : 'false' ?>"
                                >
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="status-pills" aria-label="Contadores de estados">
                        <?php foreach ([
                            'en espera' => 'En espera',
                            'llamado' => 'Llamado',
                            'en atencion' => 'En atención',
                            'atendido' => 'Atendido',
                        ] as $stateKey => $stateLabel): ?>
                            <div class="status-pill" data-state="<?= htmlspecialchars($stateKey, ENT_QUOTES, 'UTF-8') ?>">
                                <span><?= htmlspecialchars($stateLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="count" data-counter="<?= htmlspecialchars($key . '-' . $stateKey, ENT_QUOTES, 'UTF-8') ?>">0</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div
                        id="empty-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                        class="turnero-empty"
                        role="status"
                        aria-hidden="true"
                    >
                        <?= htmlspecialchars($panel['empty'], ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div
                        id="listado-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                        class="turnero-list"
                        aria-live="polite"
                        role="list"
                    ></div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<script>
    window.TURNERO_UNIFICADO_CONFIG = <?= json_encode($turneroSettings ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.TURNERO_UNIFICADO_PANELES = <?= json_encode($panels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php
    $scripts = $scripts ?? [];
    $scripts[] = 'js/pages/turneros/unificado.js';
?>
