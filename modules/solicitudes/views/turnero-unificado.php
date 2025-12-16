<?php
$panels = [
    'examenes' => [
        'title' => 'Exámenes',
        'context' => 'Coordinación de Exámenes',
        'endpoint' => '/examenes/turnero-data',
        'empty' => 'No hay pacientes en cola para exámenes.',
        'accent' => 'panel-examenes',
    ],
    'solicitudes' => [
        'title' => 'Quirúrgicas',
        'context' => 'Coordinación Quirúrgica',
        'endpoint' => '/solicitudes/turnero-data',
        'empty' => 'No hay pacientes en cola para coordinación quirúrgica.',
        'accent' => 'panel-quirurgico',
    ],
];
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
            max-width: 1400px;
            margin: 0 auto;
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.92));
            border-radius: 28px;
            padding: clamp(1.5rem, 4vw, 3rem);
            box-shadow: 0 25px 55px rgba(10, 12, 24, 0.45);
            border: 1px solid rgba(148, 163, 184, 0.25);
        }

        .turnero-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            margin-bottom: clamp(1.5rem, 3vw, 2.25rem);
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
            align-items: center;
            gap: 0.75rem;
        }

        .turnero-actions .btn {
            border-radius: 999px;
            padding: 0.55rem 1.4rem;
            font-weight: 700;
            border-width: 2px;
        }

        .turnero-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: clamp(1rem, 2.5vw, 2rem);
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
            display: flex;
            gap: 0.85rem;
            align-items: flex-start;
            padding: 1rem 1.1rem;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.28);
            border-radius: 18px;
            min-height: 120px;
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

        .turno-card.is-priority::before {
            content: '\1F4CC';
            position: absolute;
            top: 12px;
            right: 12px;
            font-size: 1.1rem;
        }

        @keyframes pulseCall {
            0% { box-shadow: 0 18px 32px rgba(250, 204, 21, 0.12); }
            50% { box-shadow: 0 24px 44px rgba(250, 204, 21, 0.3); }
            100% { box-shadow: 0 18px 32px rgba(250, 204, 21, 0.12); }
        }

        .turno-numero {
            font-size: clamp(2.4rem, 4vw, 3.4rem);
            font-weight: 800;
            color: #38bdf8;
            line-height: 1;
            min-width: clamp(90px, 10vw, 120px);
            text-align: center;
        }

        .turno-detalles {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .turno-nombre {
            font-size: clamp(1.3rem, 3vw, 1.8rem);
            font-weight: 700;
            color: #f8fafc;
        }

        .turno-descripcion {
            color: #cbd5f5;
            font-weight: 600;
        }

        .turno-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .turno-badge {
            background: rgba(56, 189, 248, 0.2);
            color: #38bdf8;
            border-radius: 999px;
            padding: 0.35rem 0.9rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        .turno-estado {
            border-radius: 999px;
            padding: 0.35rem 0.9rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: rgba(148, 163, 184, 0.25);
            color: #e2e8f0;
            font-size: 0.85rem;
        }

        .turno-estado.recibido { background: rgba(59, 130, 246, 0.25); color: #60a5fa; }
        .turno-estado.llamado { background: rgba(245, 158, 11, 0.25); color: #fbbf24; }
        .turno-estado.en-atencion { background: rgba(52, 211, 153, 0.25); color: #34d399; }
        .turno-estado.atendido { background: rgba(148, 163, 184, 0.35); color: #cbd5f5; }

        .turno-detalle {
            font-size: 0.95rem;
            color: #94a3b8;
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

        @media (max-width: 960px) {
            .turnero-grid { grid-template-columns: 1fr; }
            .turno-card { flex-direction: column; }
            .turno-numero { min-width: 0; text-align: left; }
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
            <div class="turnero-actions">
                <div class="turnero-meta">
                    <span id="turneroClock">--:--:--</span>
                </div>
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
    window.TURNERO_UNIFICADO_PANELES = <?= json_encode($panels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php
    $scripts = $scripts ?? [];
    $scripts[] = 'js/pages/turneros/unificado.js';
?>
