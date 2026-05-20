@extends('layouts.medforge')

@php
    $aiAgentPreview  = is_array($aiAgentPreview ?? null)  ? $aiAgentPreview  : [];
    $aiAgentStats    = is_array($aiAgentPreview['stats'] ?? null) ? $aiAgentPreview['stats'] : [];
    $aiAgentRuns     = is_array($aiAgentPreview['runs']  ?? null) ? $aiAgentPreview['runs']  : [];
    $knowledgeBase   = is_array($knowledgeBase ?? null)   ? $knowledgeBase   : [];
    $knowledgeStats  = is_array($knowledgeBase['stats']  ?? null) ? $knowledgeBase['stats']  : [];
@endphp

@push('styles')
<style>
    .wa-ai-pagebar {
        border-radius: 28px;
        padding: 24px 26px;
        background:
            radial-gradient(circle at top left, rgba(99, 102, 241, .22), transparent 34%),
            radial-gradient(circle at top right, rgba(139, 92, 246, .16), transparent 28%),
            linear-gradient(145deg, #0f172a 0%, #1e293b 52%, #312e81 100%);
        color: #f8fafc;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .16);
    }
    .wa-ai-pagebar__top {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .wa-ai-pagebar__title {
        font-size: 28px;
        font-weight: 800;
        line-height: 1.05;
        letter-spacing: -.03em;
    }
    .wa-ai-pagebar__subtitle {
        margin-top: 8px;
        color: rgba(248, 250, 252, .82);
        max-width: 680px;
        font-size: 14px;
        line-height: 1.6;
    }
    .wa-ai-hero-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        padding: 8px 12px;
        background: rgba(255, 255, 255, .12);
        border: 1px solid rgba(255, 255, 255, .14);
        color: #f8fafc;
        font-size: 12px;
        font-weight: 700;
    }
    .wa-ai-shell {
        display: grid;
        gap: 18px;
    }
    .wa-ai-panel {
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(15, 23, 42, .05);
        overflow: hidden;
    }
    .wa-ai-panel__head {
        padding: 18px 20px;
        border-bottom: 1px solid rgba(148, 163, 184, .14);
        background:
            radial-gradient(circle at top right, rgba(99, 102, 241, .08), transparent 40%),
            #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .wa-ai-panel__body {
        padding: 18px 20px;
    }
    .wa-ai-panel__title {
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -.02em;
        color: #0f172a;
    }
    .wa-ai-panel__meta {
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
    }
    .wa-ai-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .wa-ai-kpi {
        padding: 16px;
        border-radius: 20px;
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96));
        border: 1px solid rgba(148, 163, 184, .16);
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
    }
    .wa-ai-kpi__label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .wa-ai-kpi__value {
        margin-top: .45rem;
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -.04em;
        color: #0f172a;
        line-height: 1;
    }
    .wa-ai-kpi__sub {
        margin-top: .45rem;
        font-size: 12px;
        color: #64748b;
    }
    .wa-ai-runs-list {
        display: grid;
        gap: 10px;
    }
    .wa-ai-run-card {
        border-radius: 18px;
        border: 1px solid rgba(148, 163, 184, .18);
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .wa-ai-run-card__top {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
    }
    .wa-ai-run-card__title {
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
    }
    .wa-ai-run-card__meta,
    .wa-ai-run-card__response {
        font-size: 12px;
        color: #64748b;
        line-height: 1.5;
    }
    .wa-ai-run-card__sources {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .wa-ai-node-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: .22rem .7rem;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
        border: 1px solid transparent;
    }
    .wa-ai-node-badge--match   { background: rgba(15,118,110,.10); color: #0f766e; border-color: rgba(15,118,110,.18); }
    .wa-ai-node-badge--warning { background: rgba(245,158,11,.10); color: #b45309; border-color: rgba(245,158,11,.18); }
    .wa-ai-node-badge--draft   { background: rgba(148,163,184,.14); color: #475569; border-color: rgba(148,163,184,.22); }
    .wa-ai-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: .22rem .6rem;
        font-size: 11px;
        font-weight: 700;
        background: rgba(71,85,105,.10);
        color: #475569;
    }
    .wa-ai-empty {
        padding: 2rem 1rem;
        text-align: center;
        color: #64748b;
    }
    .wa-ai-section-title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
        margin-bottom: .75rem;
    }
    .wa-ai-kb-strip {
        display: flex;
        gap: 12px;
        align-items: center;
        padding: 14px 16px;
        border-radius: 16px;
        background: linear-gradient(90deg, rgba(99,102,241,.06), rgba(16,185,129,.06));
        border: 1px solid rgba(99,102,241,.14);
        flex-wrap: wrap;
    }
    .wa-ai-kb-strip__label { font-size: 13px; font-weight: 700; color: #1e293b; }
    .wa-ai-kb-strip__meta  { font-size: 12px; color: #64748b; }
    @media (max-width: 992px) {
        .wa-ai-kpis { grid-template-columns: repeat(2, 1fr); }
    }
</style>
@endpush

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>{{ $pageTitle ?? 'AI Agent' }}</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/v2/whatsapp">WhatsApp V2</a></li>
                    <li class="breadcrumb-item"><a href="/v2/whatsapp/flowmaker">Flowmaker</a></li>
                    <li class="breadcrumb-item active">AI Agent</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="wa-ai-shell">

            {{-- Pagebar --}}
            <div class="wa-ai-pagebar">
                <div class="wa-ai-pagebar__top">
                    <div>
                        <div class="wa-ai-pagebar__title">AI Agent preview</div>
                        <div class="wa-ai-pagebar__subtitle">
                            Historial de ejecuciones del nodo AI Agent en modo preview.
                            Revisa confianza, grounding, safety y motivos de handoff.
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-10 align-items-center">
                        <span class="wa-ai-hero-pill"><i class="mdi mdi-robot-outline"></i> {{ $aiAgentStats['total_runs'] ?? 0 }} runs</span>
                        <span class="wa-ai-hero-pill"><i class="mdi mdi-chart-line"></i> conf. media {{ $aiAgentStats['avg_confidence'] ?? 0 }}</span>
                        <a href="/v2/whatsapp/kb" class="btn btn-light btn-sm">
                            <i class="mdi mdi-database-outline"></i> Knowledge Base
                        </a>
                        <a href="/v2/whatsapp/flowmaker" class="btn btn-light btn-sm">
                            <i class="mdi mdi-arrow-left"></i> Flowmaker
                        </a>
                    </div>
                </div>
            </div>

            {{-- KB strip --}}
            <div class="wa-ai-kb-strip">
                <div>
                    <div class="wa-ai-kb-strip__label"><i class="mdi mdi-database-outline"></i> Knowledge Base conectada</div>
                    <div class="wa-ai-kb-strip__meta">{{ $knowledgeStats['published'] ?? 0 }} documentos publicados · {{ $knowledgeStats['total'] ?? 0 }} totales</div>
                </div>
                <a href="/v2/whatsapp/kb" class="btn btn-sm btn-outline-dark ms-auto">Gestionar KB</a>
            </div>

            {{-- KPIs --}}
            <div class="wa-ai-kpis">
                <div class="wa-ai-kpi">
                    <div class="wa-ai-kpi__label">Runs totales</div>
                    <div class="wa-ai-kpi__value" id="wa-ai-total">{{ $aiAgentStats['total_runs'] ?? 0 }}</div>
                    <div class="wa-ai-kpi__sub">Preview persistido</div>
                </div>
                <div class="wa-ai-kpi">
                    <div class="wa-ai-kpi__label">Handoff sugerido</div>
                    <div class="wa-ai-kpi__value" id="wa-ai-handoff">{{ $aiAgentStats['handoff_suggested'] ?? 0 }}</div>
                    <div class="wa-ai-kpi__sub">Por baja confianza o guardrail</div>
                </div>
                <div class="wa-ai-kpi">
                    <div class="wa-ai-kpi__label">Alta confianza</div>
                    <div class="wa-ai-kpi__value" id="wa-ai-high">{{ $aiAgentStats['high_confidence'] ?? 0 }}</div>
                    <div class="wa-ai-kpi__sub">&ge; 0.75</div>
                </div>
                <div class="wa-ai-kpi">
                    <div class="wa-ai-kpi__label">Confianza media</div>
                    <div class="wa-ai-kpi__value" id="wa-ai-avg">{{ $aiAgentStats['avg_confidence'] ?? 0 }}</div>
                    <div class="wa-ai-kpi__sub">Score promedio</div>
                </div>
                <div class="wa-ai-kpi">
                    <div class="wa-ai-kpi__label">Fallback</div>
                    <div class="wa-ai-kpi__value" id="wa-ai-fallback">{{ $aiAgentStats['fallback_runs'] ?? 0 }}</div>
                    <div class="wa-ai-kpi__sub">Guardrail activo</div>
                </div>
                <div class="wa-ai-kpi">
                    <div class="wa-ai-kpi__label">Grounding medio</div>
                    <div class="wa-ai-kpi__value" id="wa-ai-grounding">{{ $aiAgentStats['avg_grounding'] ?? 0 }}</div>
                    <div class="wa-ai-kpi__sub">Score de fuentes</div>
                </div>
                <div class="wa-ai-kpi">
                    <div class="wa-ai-kpi__label">Safety medio</div>
                    <div class="wa-ai-kpi__value" id="wa-ai-safety">{{ $aiAgentStats['avg_safety'] ?? 0 }}</div>
                    <div class="wa-ai-kpi__sub">Guardrail básico</div>
                </div>
            </div>

            {{-- Runs panel --}}
            <div class="wa-ai-panel">
                <div class="wa-ai-panel__head">
                    <div>
                        <div class="wa-ai-panel__title">Ejecuciones recientes</div>
                        <div class="wa-ai-panel__meta">Runs del nodo AI Agent en modo preview, ordenados por fecha descendente.</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-dark" id="wa-ai-refresh-btn">
                        <i class="mdi mdi-refresh"></i> Actualizar
                    </button>
                </div>
                <div class="wa-ai-panel__body">
                    <div class="wa-ai-runs-list" id="wa-ai-runs-list">
                        @forelse($aiAgentRuns as $run)
                            <div class="wa-ai-run-card">
                                <div class="wa-ai-run-card__top">
                                    <div>
                                        <div class="wa-ai-run-card__title">
                                            {{ $run['scenario_id'] ?? 'AI Agent' }} · {{ $run['classification'] ?? 'general' }}
                                        </div>
                                        <div class="wa-ai-run-card__meta">
                                            {{ $run['wa_number'] ?? 'sin número' }} · conf {{ $run['confidence'] ?? 0 }} · {{ $run['created_at'] ?? '—' }}
                                        </div>
                                    </div>
                                    @php
                                        $runTone = !empty($run['fallback_used']) ? 'warning' : (!empty($run['suggested_handoff']) ? 'draft' : 'match');
                                        $runDecisionLabels = [
                                            'respond'         => 'respuesta normal',
                                            'fallback'        => 'fallback',
                                            'respond_handoff' => 'respuesta + handoff',
                                            'fallback_handoff'=> 'fallback + handoff',
                                        ];
                                        $runDecision = $runDecisionLabels[$run['decision'] ?? ''] ?? ($run['decision'] ?? 'respond');
                                    @endphp
                                    <span class="wa-ai-node-badge wa-ai-node-badge--{{ $runTone }}">{{ $runDecision }}</span>
                                </div>
                                <div class="wa-ai-run-card__response">{{ $run['response_text'] ?? 'Sin respuesta sugerida.' }}</div>
                                <div class="wa-ai-run-card__meta">
                                    fallback={{ !empty($run['fallback_used']) ? 'sí' : 'no' }} ·
                                    handoff={{ !empty($run['suggested_handoff']) ? 'sí' : 'no' }} ·
                                    grounding {{ $run['scores']['grounding'] ?? '0' }} ·
                                    safety {{ $run['scores']['safety'] ?? '0' }} ·
                                    overall {{ $run['scores']['overall'] ?? '0' }}
                                </div>
                                @if(!empty($run['handoff_reasons']))
                                    <div class="wa-ai-run-card__sources">
                                        @php
                                            $handoffLabels = [
                                                'low_confidence'       => 'baja confianza',
                                                'no_grounding'         => 'sin grounding',
                                                'node_requested_handoff' => 'handoff forzado',
                                                'safety_guardrail'     => 'guardrail safety',
                                                'user_requested_human' => 'solicitó humano',
                                                'window_closed'        => 'ventana cerrada',
                                            ];
                                        @endphp
                                        @foreach($run['handoff_reasons'] as $reason)
                                            <span class="wa-ai-badge">{{ $handoffLabels[$reason] ?? $reason }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if(!empty($run['matched_documents']))
                                    <div class="wa-ai-run-card__sources">
                                        @foreach($run['matched_documents'] as $doc)
                                            <span class="wa-ai-badge">{{ $doc['title'] ?? 'doc' }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="wa-ai-empty">Todavía no hay ejecuciones del nodo AI Agent.</div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
    const runsList    = document.getElementById('wa-ai-runs-list');
    const refreshBtn  = document.getElementById('wa-ai-refresh-btn');
    const aiTotal     = document.getElementById('wa-ai-total');
    const aiHandoff   = document.getElementById('wa-ai-handoff');
    const aiHigh      = document.getElementById('wa-ai-high');
    const aiAvg       = document.getElementById('wa-ai-avg');
    const aiFallback  = document.getElementById('wa-ai-fallback');
    const aiGrounding = document.getElementById('wa-ai-grounding');
    const aiSafety    = document.getElementById('wa-ai-safety');

    let aiState = {
        runs:  @json($aiAgentRuns,  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        stats: @json($aiAgentStats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    };

    const escapeHtml = (v) => String(v ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const handoffLabels = {
        low_confidence:         'baja confianza',
        no_grounding:           'sin grounding',
        node_requested_handoff: 'handoff forzado',
        safety_guardrail:       'guardrail safety',
        user_requested_human:   'solicitó humano',
        window_closed:          'ventana cerrada',
    };

    const decisionLabels = {
        respond:          'respuesta normal',
        fallback:         'fallback',
        respond_handoff:  'respuesta + handoff',
        fallback_handoff: 'fallback + handoff',
    };

    const badgeTone = (run) => run?.fallback_used ? 'warning' : (run?.suggested_handoff ? 'draft' : 'match');

    const renderRuns = () => {
        const stats = aiState.stats || {};
        if (aiTotal)     aiTotal.textContent     = String(stats.total_runs        || 0);
        if (aiHandoff)   aiHandoff.textContent   = String(stats.handoff_suggested || 0);
        if (aiHigh)      aiHigh.textContent      = String(stats.high_confidence   || 0);
        if (aiAvg)       aiAvg.textContent       = String(stats.avg_confidence    || 0);
        if (aiFallback)  aiFallback.textContent  = String(stats.fallback_runs     || 0);
        if (aiGrounding) aiGrounding.textContent = String(stats.avg_grounding     || 0);
        if (aiSafety)    aiSafety.textContent    = String(stats.avg_safety        || 0);

        if (!runsList) return;
        const runs = Array.isArray(aiState.runs) ? aiState.runs : [];
        if (!runs.length) {
            runsList.innerHTML = '<div class="wa-ai-empty">Todavía no hay ejecuciones del nodo AI Agent.</div>';
            return;
        }
        runsList.innerHTML = runs.map((run) => `
            <div class="wa-ai-run-card">
                <div class="wa-ai-run-card__top">
                    <div>
                        <div class="wa-ai-run-card__title">${escapeHtml(run.scenario_id || 'AI Agent')} · ${escapeHtml(run.classification || 'general')}</div>
                        <div class="wa-ai-run-card__meta">${escapeHtml(run.wa_number || 'sin número')} · conf ${escapeHtml(run.confidence ?? 0)} · ${escapeHtml(run.created_at || '—')}</div>
                    </div>
                    <span class="wa-ai-node-badge wa-ai-node-badge--${badgeTone(run)}">${escapeHtml(decisionLabels[run.decision] || run.decision || 'respond')}</span>
                </div>
                <div class="wa-ai-run-card__response">${escapeHtml(run.response_text || 'Sin respuesta sugerida.')}</div>
                <div class="wa-ai-run-card__meta">
                    fallback ${run.fallback_used ? 'sí' : 'no'} ·
                    handoff ${run.suggested_handoff ? 'sí' : 'no'} ·
                    grounding ${escapeHtml(run?.scores?.grounding ?? 0)} ·
                    safety ${escapeHtml(run?.scores?.safety ?? 0)} ·
                    overall ${escapeHtml(run?.scores?.overall ?? 0)}
                </div>
                ${(Array.isArray(run.handoff_reasons) && run.handoff_reasons.length) ? `
                    <div class="wa-ai-run-card__sources">
                        ${run.handoff_reasons.map((r) => `<span class="wa-ai-badge">${escapeHtml(handoffLabels[r] || r)}</span>`).join('')}
                    </div>
                ` : ''}
                ${(Array.isArray(run.matched_documents) && run.matched_documents.length) ? `
                    <div class="wa-ai-run-card__sources">
                        ${run.matched_documents.map((d) => `<span class="wa-ai-badge">${escapeHtml(d.title || 'doc')}</span>`).join('')}
                    </div>
                ` : ''}
            </div>
        `).join('');
    };

    const loadRuns = async () => {
        if (refreshBtn) refreshBtn.disabled = true;
        try {
            const r    = await fetch('/v2/whatsapp/api/flowmaker/ai-runs?limit=25', { credentials: 'same-origin' });
            const data = await r.json();
            aiState = {
                runs:  Array.isArray(data?.data) ? data.data : [],
                stats: data?.stats || {},
            };
            renderRuns();
        } catch {
            // keep current state
        } finally {
            if (refreshBtn) refreshBtn.disabled = false;
        }
    };

    refreshBtn?.addEventListener('click', loadRuns);

    renderRuns();
})();
</script>
@endpush
