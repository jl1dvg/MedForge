@extends('layouts.medforge')

@php
    $knowledgeBase = is_array($knowledgeBase ?? null) ? $knowledgeBase : [];
    $knowledgeStats = is_array($knowledgeBase['stats'] ?? null) ? $knowledgeBase['stats'] : [];
    $knowledgeDocuments = is_array($knowledgeBase['documents'] ?? null) ? $knowledgeBase['documents'] : [];
@endphp

@push('styles')
<style>
    .wa-kb-pagebar {
        border-radius: 28px;
        padding: 24px 26px;
        background:
            radial-gradient(circle at top left, rgba(16, 185, 129, .18), transparent 34%),
            radial-gradient(circle at top right, rgba(99, 102, 241, .14), transparent 28%),
            linear-gradient(145deg, #0f172a 0%, #1e293b 52%, #065f46 100%);
        color: #f8fafc;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .16);
    }
    .wa-kb-pagebar__top {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
        flex-wrap: wrap;
    }
    .wa-kb-pagebar__title {
        font-size: 28px;
        font-weight: 800;
        line-height: 1.05;
        letter-spacing: -.03em;
    }
    .wa-kb-pagebar__subtitle {
        margin-top: 8px;
        color: rgba(248, 250, 252, .82);
        max-width: 680px;
        font-size: 14px;
        line-height: 1.6;
    }
    .wa-kb-hero-pill {
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
    .wa-kb-shell {
        display: grid;
        gap: 18px;
    }
    .wa-kb-panel {
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(15, 23, 42, .05);
        overflow: hidden;
    }
    .wa-kb-panel__head {
        padding: 18px 20px;
        border-bottom: 1px solid rgba(148, 163, 184, .14);
        background:
            radial-gradient(circle at top right, rgba(16, 185, 129, .08), transparent 40%),
            #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .wa-kb-panel__body {
        padding: 18px 20px;
    }
    .wa-kb-panel__title {
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -.02em;
        color: #0f172a;
    }
    .wa-kb-panel__meta {
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
    }
    .wa-kb-kpis {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .wa-kb-kpi {
        padding: 16px;
        border-radius: 20px;
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96));
        border: 1px solid rgba(148, 163, 184, .16);
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
    }
    .wa-kb-kpi__label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .wa-kb-kpi__value {
        margin-top: .45rem;
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -.04em;
        color: #0f172a;
        line-height: 1;
    }
    .wa-kb-kpi__sub {
        margin-top: .45rem;
        font-size: 12px;
        color: #64748b;
    }
    .wa-kb-content-grid {
        display: grid;
        grid-template-columns: 1.1fr .9fr;
        gap: 18px;
    }
    .wa-kb-list {
        display: grid;
        gap: 10px;
        max-height: 560px;
        overflow: auto;
    }
    .wa-kb-card {
        border: 1px solid rgba(148, 163, 184, .16);
        border-radius: 18px;
        background: #fff;
        padding: 14px;
    }
    .wa-kb-card__title {
        font-size: 15px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }
    .wa-kb-card__summary {
        margin-top: .5rem;
        font-size: 13px;
        color: #475569;
        line-height: 1.55;
    }
    .wa-kb-card__meta {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        margin-top: .65rem;
    }
    .wa-kb-card__actions {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-top: .75rem;
    }
    .wa-kb-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: .22rem .6rem;
        font-size: 11px;
        font-weight: 700;
    }
    .wa-kb-badge--published { background: rgba(15, 118, 110, .10); color: #0f766e; }
    .wa-kb-badge--draft     { background: rgba(148, 163, 184, .14); color: #475569; }
    .wa-kb-badge--count     { background: rgba(71, 85, 105, .10); color: #475569; }
    .wa-kb-section-title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
        margin-bottom: .75rem;
    }
    .wa-kb-empty {
        padding: 2rem 1rem;
        text-align: center;
        color: #64748b;
    }
    .wa-kb-stack {
        display: grid;
        gap: 18px;
    }
    .wa-kb-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .85rem;
    }
    .wa-kb-field label {
        display: block;
        font-size: .76rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #64748b;
        margin-bottom: .35rem;
    }
    .wa-kb-field input,
    .wa-kb-field select,
    .wa-kb-field textarea {
        width: 100%;
        border-radius: 12px;
        border: 1px solid rgba(15, 23, 42, .12);
        padding: .65rem .75rem;
        font-size: .88rem;
        background: #fff;
    }
    .wa-kb-field textarea {
        min-height: 140px;
        resize: vertical;
    }
    .wa-kb-search {
        border-radius: 14px;
        border: 1px solid rgba(148, 163, 184, .18);
        background: #fff;
        width: 100%;
        padding: .8rem .9rem;
        font-size: .92rem;
        margin-bottom: 14px;
    }
    @media (max-width: 992px) {
        .wa-kb-content-grid { grid-template-columns: 1fr; }
        .wa-kb-kpis { grid-template-columns: repeat(2, 1fr); }
        .wa-kb-form-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>{{ $pageTitle ?? 'Knowledge Base IA' }}</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="/v2/whatsapp">WhatsApp V2</a></li>
                    <li class="breadcrumb-item"><a href="/v2/whatsapp/flowmaker">Flowmaker</a></li>
                    <li class="breadcrumb-item active">Knowledge Base</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="wa-kb-shell">

            {{-- Pagebar --}}
            <div class="wa-kb-pagebar">
                <div class="wa-kb-pagebar__top">
                    <div>
                        <div class="wa-kb-pagebar__title">Knowledge Base IA</div>
                        <div class="wa-kb-pagebar__subtitle">
                            Base documental para FAQs, sedes, seguros, pre y post operatorios.
                            Alimenta el nodo AI Agent del flujo de WhatsApp.
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-10 align-items-center">
                        <span class="wa-kb-hero-pill"><i class="mdi mdi-database-outline"></i> {{ $knowledgeStats['total'] ?? 0 }} documentos</span>
                        <span class="wa-kb-hero-pill"><i class="mdi mdi-check-circle-outline"></i> {{ $knowledgeStats['published'] ?? 0 }} publicados</span>
                        <a href="/v2/whatsapp/flowmaker" class="btn btn-light btn-sm">
                            <i class="mdi mdi-arrow-left"></i> Flowmaker
                        </a>
                    </div>
                </div>
            </div>

            {{-- KPIs --}}
            <div class="wa-kb-kpis">
                <div class="wa-kb-kpi">
                    <div class="wa-kb-kpi__label">Documentos</div>
                    <div class="wa-kb-kpi__value" id="wa-kb-total">{{ $knowledgeStats['total'] ?? 0 }}</div>
                    <div class="wa-kb-kpi__sub">Base total indexada</div>
                </div>
                <div class="wa-kb-kpi">
                    <div class="wa-kb-kpi__label">Publicados</div>
                    <div class="wa-kb-kpi__value" id="wa-kb-published">{{ $knowledgeStats['published'] ?? 0 }}</div>
                    <div class="wa-kb-kpi__sub">Listos para consulta</div>
                </div>
                <div class="wa-kb-kpi">
                    <div class="wa-kb-kpi__label">Draft</div>
                    <div class="wa-kb-kpi__value" id="wa-kb-draft">{{ $knowledgeStats['draft'] ?? 0 }}</div>
                    <div class="wa-kb-kpi__sub">Pendientes de curación</div>
                </div>
                <div class="wa-kb-kpi">
                    <div class="wa-kb-kpi__label">Fuentes</div>
                    <div class="wa-kb-kpi__value" id="wa-kb-sources">{{ $knowledgeStats['sources'] ?? 0 }}</div>
                    <div class="wa-kb-kpi__sub">Tipos de origen</div>
                </div>
            </div>

            {{-- Main panel --}}
            <div class="wa-kb-panel">
                <div class="wa-kb-panel__head">
                    <div>
                        <div class="wa-kb-panel__title">Documentos</div>
                        <div class="wa-kb-panel__meta">Gestiona el contenido que el nodo AI Agent usa para responder con grounding.</div>
                    </div>
                    <div class="d-flex gap-10 align-items-center">
                        <input type="text" class="wa-kb-search" id="wa-kb-search-input" placeholder="Buscar documentos..." style="width:240px;margin-bottom:0;">
                    </div>
                </div>
                <div class="wa-kb-panel__body">
                    <div class="wa-kb-content-grid">
                        {{-- Document list --}}
                        <div>
                            <div class="wa-kb-section-title">Documentos recientes</div>
                            <div class="wa-kb-list" id="wa-kb-list">
                                @forelse($knowledgeDocuments as $document)
                                    <div class="wa-kb-card">
                                        <div class="wa-kb-card__title">{{ $document['title'] ?? 'Documento KB' }}</div>
                                        <div class="wa-kb-card__summary">{{ $document['summary'] ?? 'Sin resumen.' }}</div>
                                        <div class="wa-kb-card__meta">
                                            <span class="wa-kb-badge wa-kb-badge--{{ $document['status'] === 'published' ? 'published' : 'draft' }}">{{ $document['status'] === 'published' ? 'Publicado' : 'Borrador' }}</span>
                                            <span class="wa-kb-badge wa-kb-badge--count">{{ $document['metadata']['tipo_contenido'] ?? 'faq' }}</span>
                                            <span class="wa-kb-badge wa-kb-badge--count">{{ $document['metadata']['audiencia'] ?? 'paciente' }}</span>
                                            @if(!empty($document['metadata']['sede']))
                                                <span class="wa-kb-badge wa-kb-badge--count">{{ $document['metadata']['sede'] }}</span>
                                            @endif
                                        </div>
                                        <div class="wa-kb-card__actions">
                                            <button type="button" class="btn btn-light btn-sm" data-kb-edit="{{ $document['id'] ?? '' }}">Editar</button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="wa-kb-empty">Todavía no hay documentos en la Knowledge Base.</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- Editor form --}}
                        <div>
                            <div class="wa-kb-section-title" id="wa-kb-form-label">Nuevo documento</div>
                            <div class="wa-kb-stack">
                                <div class="wa-kb-form-grid">
                                    <div class="wa-kb-field" style="grid-column:1/-1">
                                        <label>Título</label>
                                        <input type="text" id="wa-kb-title" placeholder="Ej: Consentimiento y uso de datos">
                                    </div>
                                    <div class="wa-kb-field">
                                        <label>Estado</label>
                                        <select id="wa-kb-status">
                                            <option value="draft">Borrador</option>
                                            <option value="published">Publicado</option>
                                        </select>
                                    </div>
                                    <div class="wa-kb-field">
                                        <label>Sede</label>
                                        <input type="text" id="wa-kb-sede" placeholder="Matriz">
                                    </div>
                                    <div class="wa-kb-field">
                                        <label>Especialidad</label>
                                        <input type="text" id="wa-kb-especialidad" placeholder="Oftalmología">
                                    </div>
                                    <div class="wa-kb-field">
                                        <label>Tipo de contenido</label>
                                        <select id="wa-kb-type">
                                            <option value="faq">FAQ</option>
                                            <option value="policy">Política</option>
                                            <option value="preoperatorio">Preoperatorio</option>
                                            <option value="postoperatorio">Postoperatorio</option>
                                            <option value="seguros">Seguros</option>
                                            <option value="consentimiento">Consentimiento</option>
                                        </select>
                                    </div>
                                    <div class="wa-kb-field">
                                        <label>Audiencia</label>
                                        <select id="wa-kb-audiencia">
                                            <option value="paciente">Paciente</option>
                                            <option value="agente">Agente</option>
                                            <option value="supervisor">Supervisor</option>
                                        </select>
                                    </div>
                                    <div class="wa-kb-field" style="grid-column:1/-1">
                                        <label>Contenido</label>
                                        <textarea id="wa-kb-content" placeholder="Texto base para grounding controlado del AI Agent."></textarea>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-10 align-items-center">
                                    <button type="button" class="btn btn-primary" id="wa-kb-save-btn">Guardar documento</button>
                                    <button type="button" class="btn btn-light" id="wa-kb-cancel-edit-btn" hidden>Cancelar edición</button>
                                    <span class="small text-muted" id="wa-kb-status-node"></span>
                                </div>
                            </div>
                        </div>
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
    const kbList          = document.getElementById('wa-kb-list');
    const kbSaveButton    = document.getElementById('wa-kb-save-btn');
    const kbCancelBtn     = document.getElementById('wa-kb-cancel-edit-btn');
    const kbStatusNode    = document.getElementById('wa-kb-status-node');
    const kbTitle         = document.getElementById('wa-kb-title');
    const kbContent       = document.getElementById('wa-kb-content');
    const kbStatus        = document.getElementById('wa-kb-status');
    const kbSede          = document.getElementById('wa-kb-sede');
    const kbEspecialidad  = document.getElementById('wa-kb-especialidad');
    const kbType          = document.getElementById('wa-kb-type');
    const kbAudiencia     = document.getElementById('wa-kb-audiencia');
    const kbTotal         = document.getElementById('wa-kb-total');
    const kbPublished     = document.getElementById('wa-kb-published');
    const kbDraft         = document.getElementById('wa-kb-draft');
    const kbSources       = document.getElementById('wa-kb-sources');
    const kbFormLabel     = document.getElementById('wa-kb-form-label');
    const kbSearchInput   = document.getElementById('wa-kb-search-input');

    let kbState = {
        documents: @json($knowledgeDocuments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        stats: @json($knowledgeStats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    };
    let editingId = null;

    const escapeHtml = (v) => String(v ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const statusBadge = (status) => status === 'published'
        ? '<span class="wa-kb-badge wa-kb-badge--published">Publicado</span>'
        : '<span class="wa-kb-badge wa-kb-badge--draft">Borrador</span>';

    const renderList = () => {
        const term  = (kbSearchInput?.value || '').trim().toLowerCase();
        const docs  = Array.isArray(kbState.documents) ? kbState.documents : [];
        const stats = kbState.stats || {};

        if (kbTotal)     kbTotal.textContent     = String(stats.total     || 0);
        if (kbPublished) kbPublished.textContent  = String(stats.published || 0);
        if (kbDraft)     kbDraft.textContent      = String(stats.draft     || 0);
        if (kbSources)   kbSources.textContent    = String(stats.sources   || 0);

        const filtered = term
            ? docs.filter((d) => [d.title, d.summary, d.content, d.metadata?.sede, d.metadata?.especialidad]
                .join(' ').toLowerCase().includes(term))
            : docs;

        if (!filtered.length) {
            kbList.innerHTML = `<div class="wa-kb-empty">${term ? `Sin resultados para "${escapeHtml(term)}".` : 'Todavía no hay documentos.'}</div>`;
            return;
        }

        kbList.innerHTML = filtered.map((doc) => `
            <div class="wa-kb-card">
                <div class="wa-kb-card__title">${escapeHtml(doc.title || 'Documento KB')}</div>
                <div class="wa-kb-card__summary">${escapeHtml(doc.summary || 'Sin resumen.')}</div>
                <div class="wa-kb-card__meta">
                    ${statusBadge(doc.status)}
                    <span class="wa-kb-badge wa-kb-badge--count">${escapeHtml(doc.metadata?.tipo_contenido || 'faq')}</span>
                    <span class="wa-kb-badge wa-kb-badge--count">${escapeHtml(doc.metadata?.audiencia || 'paciente')}</span>
                    ${doc.metadata?.sede ? `<span class="wa-kb-badge wa-kb-badge--count">${escapeHtml(doc.metadata.sede)}</span>` : ''}
                </div>
                <div class="wa-kb-card__actions">
                    <button type="button" class="btn btn-light btn-sm" data-kb-edit="${escapeHtml(doc.id || '')}">Editar</button>
                </div>
            </div>
        `).join('');
    };

    const resetForm = () => {
        editingId = null;
        if (kbTitle)        kbTitle.value       = '';
        if (kbContent)      kbContent.value     = '';
        if (kbStatus)       kbStatus.value      = 'draft';
        if (kbSede)         kbSede.value        = '';
        if (kbEspecialidad) kbEspecialidad.value= '';
        if (kbType)         kbType.value        = 'faq';
        if (kbAudiencia)    kbAudiencia.value   = 'paciente';
        if (kbSaveButton)   kbSaveButton.textContent = 'Guardar documento';
        if (kbCancelBtn)    kbCancelBtn.hidden  = true;
        if (kbFormLabel)    kbFormLabel.textContent  = 'Nuevo documento';
    };

    const editDocument = (docId) => {
        const doc = (Array.isArray(kbState.documents) ? kbState.documents : [])
            .find((d) => String(d?.id) === String(docId));
        if (!doc) return;
        editingId = doc.id;
        const meta = doc.metadata || {};
        if (kbTitle)        kbTitle.value       = doc.title || '';
        if (kbContent)      kbContent.value     = doc.content || '';
        if (kbStatus)       kbStatus.value      = doc.status || 'draft';
        if (kbSede)         kbSede.value        = meta.sede || '';
        if (kbEspecialidad) kbEspecialidad.value= meta.especialidad || '';
        if (kbType)         kbType.value        = meta.tipo_contenido || 'faq';
        if (kbAudiencia)    kbAudiencia.value   = meta.audiencia || 'paciente';
        if (kbSaveButton)   kbSaveButton.textContent = 'Actualizar documento';
        if (kbCancelBtn)    kbCancelBtn.hidden  = false;
        if (kbFormLabel)    kbFormLabel.textContent  = `Editando KB #${doc.id}`;
        if (kbStatusNode)   kbStatusNode.textContent = '';
        kbTitle?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    const loadDocuments = async () => {
        try {
            const r    = await fetch('/v2/whatsapp/api/knowledge-base?limit=25', { credentials: 'same-origin' });
            const data = await r.json();
            kbState = {
                documents: Array.isArray(data?.data)  ? data.data  : [],
                stats:     data?.stats || {},
            };
            renderList();
        } catch {
            if (kbStatusNode) kbStatusNode.textContent = 'No fue posible cargar los documentos.';
        }
    };

    kbSearchInput?.addEventListener('input', renderList);

    kbList?.addEventListener('click', (e) => {
        const btn = e.target?.closest?.('[data-kb-edit]');
        if (btn) editDocument(btn.getAttribute('data-kb-edit'));
    });

    kbCancelBtn?.addEventListener('click', () => {
        resetForm();
        if (kbStatusNode) kbStatusNode.textContent = 'Edición cancelada.';
    });

    kbSaveButton?.addEventListener('click', async () => {
        if (!kbTitle?.value.trim() || !kbContent?.value.trim()) {
            if (kbStatusNode) kbStatusNode.textContent = 'Título y contenido son obligatorios.';
            return;
        }
        const isEditing = editingId !== null && editingId !== undefined;
        if (kbStatusNode) kbStatusNode.textContent = isEditing ? 'Actualizando...' : 'Guardando...';
        kbSaveButton.disabled = true;

        try {
            const endpoint = isEditing
                ? `/v2/whatsapp/api/knowledge-base/${encodeURIComponent(String(editingId))}`
                : '/v2/whatsapp/api/knowledge-base';
            const r    = await fetch(endpoint, {
                method:  'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':  'application/json',
                    'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    title:          kbTitle.value,
                    content:        kbContent.value,
                    status:         kbStatus?.value      || 'draft',
                    sede:           kbSede?.value        || '',
                    especialidad:   kbEspecialidad?.value|| '',
                    tipo_contenido: kbType?.value        || 'faq',
                    audiencia:      kbAudiencia?.value   || 'paciente',
                    source_type:    'manual',
                    source_label:   'Knowledge Base',
                }),
            });
            const data = await r.json();
            if (!r.ok || !data?.ok) {
                if (kbStatusNode) kbStatusNode.textContent = data?.error || 'No fue posible guardar.';
                return;
            }
            resetForm();
            if (kbStatusNode) kbStatusNode.textContent = isEditing ? 'Documento actualizado.' : 'Documento guardado.';
            await loadDocuments();
        } catch {
            if (kbStatusNode) kbStatusNode.textContent = 'No fue posible guardar el documento.';
        } finally {
            kbSaveButton.disabled = false;
        }
    });

    renderList();
})();
</script>
@endpush
