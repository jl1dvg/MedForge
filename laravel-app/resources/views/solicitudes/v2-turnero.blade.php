@extends('layouts.medforge')

@push('styles')
    <style>
        .turnero-v2-shell {
            border-radius: 20px;
            background: radial-gradient(circle at top left, #1e293b 0%, #0f172a 45%, #0b1120 100%);
            color: #e2e8f0;
            padding: clamp(1.2rem, 2.2vw, 2rem);
            box-shadow: 0 24px 40px rgba(15, 23, 42, 0.3);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .turnero-header {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .turnero-context {
            font-size: 0.88rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #93c5fd;
        }

        .turnero-title {
            margin: 0;
            font-size: clamp(1.6rem, 2.7vw, 2.1rem);
            font-weight: 700;
            color: #f8fafc;
        }

        .turnero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            align-items: center;
        }

        .turnero-clock {
            font-size: 1.15rem;
            font-weight: 600;
            color: #bae6fd;
        }

        .turnero-last-update {
            color: #bfdbfe;
            margin-bottom: 1rem;
        }

        .turnero-empty {
            display: none;
            background: rgba(59, 130, 246, 0.15);
            border: 1px dashed rgba(148, 163, 184, 0.4);
            color: #cbd5f5;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }

        .turnero-empty[aria-hidden="false"] {
            display: block;
        }

        .turnero-list {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .turno-card {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.24);
            border-radius: 14px;
            padding: 12px;
            display: grid;
            grid-template-columns: minmax(110px, 150px) 1fr auto;
            gap: 10px;
            align-items: center;
        }

        .turno-numero {
            font-size: 1.4rem;
            font-weight: 700;
            color: #facc15;
        }

        .turno-detalles {
            min-width: 0;
        }

        .turno-nombre {
            margin: 0 0 3px;
            font-size: 1.02rem;
            font-weight: 700;
            color: #f8fafc;
        }

        .turno-meta {
            margin: 0;
            font-size: 0.86rem;
            color: #cbd5e1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .turno-badge {
            border-radius: 999px;
            padding: 0.22rem 0.56rem;
            font-size: 0.72rem;
            font-weight: 700;
            background: #1e293b;
            color: #e2e8f0;
        }

        .turno-badge.estado-llamado {
            background: #fef3c7;
            color: #92400e;
        }

        .turno-badge.estado-en-atencion {
            background: #dcfce7;
            color: #166534;
        }

        .turno-estado {
            border-radius: 999px;
            padding: 0.22rem 0.56rem;
            font-size: 0.72rem;
            font-weight: 700;
            background: #1e293b;
            color: #cbd5e1;
            text-transform: uppercase;
        }

        .turno-estado.llamado {
            background: #fef3c7;
            color: #92400e;
        }

        .turno-estado.en-atencion {
            background: #dcfce7;
            color: #166534;
        }

        .turno-detalle {
            color: #94a3b8;
            font-size: 0.77rem;
        }

        @media (max-width: 767px) {
            .turno-card {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Turnero v2</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/v2/solicitudes">Solicitudes</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Turnero</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="turnero-v2-shell">
            <div class="turnero-header">
                <div class="turnero-heading">
                    <span id="turneroContextLabel" class="turnero-context">Coordinación Quirúrgica</span>
                    <h1 id="turneroTitle" class="turnero-title">Pacientes en cola</h1>
                </div>
                <div class="turnero-actions">
                    <span id="turneroClock" class="turnero-clock" aria-live="polite">--:--:--</span>
                    <button id="turneroRefresh" class="btn btn-outline-info" type="button">
                        <i class="mdi mdi-refresh"></i>
                        <span class="ms-1">Actualizar</span>
                    </button>
                </div>
            </div>

            <p class="turnero-last-update" id="turneroLastUpdate" aria-live="polite">Última actualización: --</p>

            <div id="turneroEmpty" class="turnero-empty" role="status" aria-hidden="false">
                No hay pacientes en cola para coordinación quirúrgica.
            </div>
            <div id="turneroListado" class="turnero-list" aria-live="polite" role="list"></div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        window.__KANBAN_MODULE__ = {
            key: 'solicitudes',
            basePath: '/solicitudes',
            readPrefix: '/v2',
            v2ReadsEnabled: true,
            turnero: {
                refreshMs: @json($turneroRefreshMs),
            },
            selectors: {
                prefix: 'solicitudes',
            },
        };
    </script>
    <script type="module" src="/js/pages/solicitudes/turnero.js"></script>
@endpush
