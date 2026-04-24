@extends('layouts.medforge')

@php
    $templates = is_array($templates ?? null) ? $templates : [];
    $availableCategories = is_array($availableCategories ?? null) ? $availableCategories : [];
    $availableLanguages = is_array($availableLanguages ?? null) ? $availableLanguages : [];
    $integration = is_array($integration ?? null) ? $integration : ['ready' => false, 'errors' => []];
    $filters = is_array($filters ?? null) ? $filters : ['search' => '', 'status' => '', 'category' => '', 'language' => ''];
    $selectedTemplate = $templates[0] ?? null;
@endphp

@push('styles')
<style>
    .wa-v2-shell {
        display: grid;
        grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
        gap: 18px;
    }

    .wa-v2-template-card {
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 24px;
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        box-shadow: 0 20px 40px rgba(15, 23, 42, .05);
        overflow: hidden;
    }

    .wa-v2-templates-pagebar {
        border-radius: 28px;
        padding: 24px 26px;
        background:
            radial-gradient(circle at top left, rgba(14, 165, 233, .16), transparent 34%),
            radial-gradient(circle at top right, rgba(37, 99, 235, .14), transparent 28%),
            linear-gradient(145deg, #0f172a 0%, #1e293b 48%, #1d4ed8 100%);
        color: #f8fafc;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .16);
    }

    .wa-v2-templates-pagebar__top {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
    }

    .wa-v2-templates-pagebar__title {
        font-size: 28px;
        font-weight: 800;
        line-height: 1.05;
        letter-spacing: -.03em;
    }

    .wa-v2-templates-pagebar__subtitle {
        margin-top: 8px;
        color: rgba(248, 250, 252, .82);
        max-width: 760px;
        font-size: 14px;
        line-height: 1.6;
    }

    .wa-v2-templates-pagebar__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }

    .wa-v2-hero-pill {
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

    .wa-v2-panel-head {
        padding: 18px 20px;
        border-bottom: 1px solid rgba(148, 163, 184, .14);
        background: radial-gradient(circle at top left, rgba(14,165,233,.06), transparent 34%), #fff;
    }

    .wa-v2-panel-body {
        padding: 18px 20px;
    }

    .wa-v2-sideheading__title {
        font-size: 18px;
        font-weight: 800;
        letter-spacing: -.02em;
        color: #0f172a;
    }

    .wa-v2-sideheading__meta {
        color: #64748b;
        font-size: 13px;
        line-height: 1.5;
    }

    .wa-v2-template-list {
        max-height: 70vh;
        overflow: auto;
    }

    .wa-v2-template-item {
        border: 1px solid rgba(148, 163, 184, .18);
        border-radius: 18px;
        padding: 14px 16px;
        cursor: pointer;
        transition: .18s ease;
        background: #fff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
    }

    .wa-v2-template-item:hover,
    .wa-v2-template-item.is-active {
        border-color: #2563eb;
        box-shadow: 0 14px 28px rgba(37, 99, 235, .12);
        transform: translateY(-1px);
    }

    .wa-v2-preview {
        border-radius: 22px;
        background: linear-gradient(180deg, #e7f1ff 0%, #f8fbff 100%);
        padding: 20px;
        min-height: 420px;
    }

    .wa-v2-message {
        background: #fff;
        border-radius: 20px 20px 8px 20px;
        padding: 16px;
        box-shadow: 0 14px 30px rgba(15, 23, 42, .08);
    }

    .wa-v2-badge-row {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
    }

    .wa-v2-empty {
        border: 1px dashed rgba(148, 163, 184, .5);
        border-radius: 18px;
        padding: 24px;
        text-align: center;
        color: #64748b;
    }

    .wa-v2-filter-shell {
        border-radius: 22px;
        border: 1px solid rgba(148, 163, 184, .18);
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        box-shadow: 0 20px 40px rgba(15, 23, 42, .05);
        padding: 18px 20px;
    }

    .wa-v2-template-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .wa-v2-template-stat {
        border-radius: 18px;
        padding: 16px;
        border: 1px solid rgba(148, 163, 184, .16);
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96));
    }

    .wa-v2-template-stat__value {
        font-size: 26px;
        font-weight: 800;
        letter-spacing: -.04em;
        color: #0f172a;
    }

    .wa-v2-template-stat__label {
        margin-top: 4px;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .08em;
    }

    .wa-v2-template-meta {
        margin-top: 4px;
        color: #64748b;
        font-size: 12px;
    }

    .wa-v2-template-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 12px;
    }

    .wa-v2-preview-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 16px;
    }

    .wa-v2-preview-title {
        font-size: 20px;
        font-weight: 800;
        letter-spacing: -.02em;
        color: #0f172a;
    }

    .wa-v2-builder-preview {
        background: #f8fbff;
        border: 1px solid rgba(13, 110, 253, .1);
        border-radius: 18px;
        padding: 14px;
    }

    .wa-v2-builder-message {
        background: #fff;
        border-radius: 18px 18px 8px 18px;
        padding: 14px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
    }

    @media (max-width: 991px) {
        .wa-v2-shell {
            grid-template-columns: 1fr;
        }

        .wa-v2-template-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767px) {
        .wa-v2-templates-pagebar {
            padding: 20px 18px;
            border-radius: 24px;
        }

        .wa-v2-templates-pagebar__top {
            flex-direction: column;
        }

        .wa-v2-template-stats {
            grid-template-columns: 1fr 1fr;
        }

        .wa-v2-panel-head,
        .wa-v2-panel-body,
        .wa-v2-filter-shell {
            padding: 16px;
        }
    }
</style>
@endpush

@section('content')
<section class="content">
    <div class="row g-3">
        <div class="col-12">
            <div class="wa-v2-templates-pagebar">
                <div class="wa-v2-templates-pagebar__top">
                    <div>
                        <div class="wa-v2-templates-pagebar__title">Templates</div>
                        <div class="wa-v2-templates-pagebar__subtitle">
                            Catálogo operativo con preview, historial local, clonación y publicación controlada.
                            La meta aquí es que la pantalla se sienta como producto, no como listado administrativo.
                        </div>
                    </div>
                    <div class="wa-v2-templates-pagebar__meta">
                        <span class="wa-v2-hero-pill"><i class="mdi mdi-domain"></i> {{ $integration['brand'] ?? 'MedForge' }}</span>
                        <span class="wa-v2-hero-pill"><i class="mdi mdi-database-outline"></i> {{ $source ?? 'local-cache' }}</span>
                        <span class="wa-v2-hero-pill"><i class="mdi mdi-message-badge-outline"></i> {{ count($templates) }} plantillas</span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-10 justify-content-between align-items-center mt-18">
                    <div class="wa-v2-badge-row">
                        <span class="badge {{ !empty($integration['ready']) ? 'bg-success-light text-success' : 'bg-danger-light text-danger' }}">
                            {{ !empty($integration['ready']) ? 'Meta listo' : 'Meta incompleto' }}
                        </span>
                        <span class="badge bg-info-light text-info">
                            {{ !empty($integration['has_local_tables']) ? 'Cache local disponible' : 'Sin tablas locales' }}
                        </span>
                    </div>
                    <div class="d-flex gap-10">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="wa-v2-new-template-btn">
                            Nueva plantilla
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" id="wa-v2-sync-btn" {{ empty($integration['ready']) ? 'disabled' : '' }}>
                            Sincronizar con Meta
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($integration['errors']))
            <div class="col-12">
                <div class="alert alert-warning mb-0">
                    <div class="fw-700 mb-5">Configuración incompleta</div>
                    <ul class="mb-0 ps-20">
                        @foreach($integration['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="col-12">
            <div class="wa-v2-filter-shell">
                <div class="wa-v2-template-stats mb-15">
                    <div class="wa-v2-template-stat">
                        <div class="wa-v2-template-stat__value">{{ count($templates) }}</div>
                        <div class="wa-v2-template-stat__label">Visibles</div>
                    </div>
                    <div class="wa-v2-template-stat">
                        <div class="wa-v2-template-stat__value">{{ collect($templates)->where('source', 'local')->count() }}</div>
                        <div class="wa-v2-template-stat__label">Locales</div>
                    </div>
                    <div class="wa-v2-template-stat">
                        <div class="wa-v2-template-stat__value">{{ collect($templates)->where('status', 'APPROVED')->count() }}</div>
                        <div class="wa-v2-template-stat__label">Approved</div>
                    </div>
                    <div class="wa-v2-template-stat">
                        <div class="wa-v2-template-stat__value">{{ collect($templates)->filter(fn($template) => !empty($template['can_publish']))->count() }}</div>
                        <div class="wa-v2-template-stat__label">Publicables</div>
                    </div>
                </div>

                <form method="GET" action="/v2/whatsapp/templates" class="row g-2 align-items-end">
                        <div class="col-xl-4 col-md-6">
                            <label class="form-label">Buscar</label>
                            <input type="text" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Nombre, idioma, categoría">
                        </div>
                        <div class="col-xl-2 col-md-6">
                            <label class="form-label">Estado</label>
                            <select name="status" class="form-select">
                                <option value="">Todos</option>
                                @foreach(['APPROVED', 'PENDING', 'REJECTED', 'PAUSED', 'DISABLED'] as $status)
                                    <option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ $status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">Categoría</label>
                            <select name="category" class="form-select">
                                <option value="">Todas</option>
                                @foreach($availableCategories as $category)
                                    <option value="{{ $category['value'] }}" {{ $filters['category'] === $category['value'] ? 'selected' : '' }}>
                                        {{ $category['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <label class="form-label">Idioma</label>
                            <select name="language" class="form-select">
                                <option value="">Todos</option>
                                @foreach($availableLanguages as $language)
                                    <option value="{{ $language['code'] }}" {{ $filters['language'] === $language['code'] ? 'selected' : '' }}>
                                        {{ $language['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-10">
                            <button type="submit" class="btn btn-outline-primary">Aplicar filtros</button>
                            <a href="/v2/whatsapp/templates" class="btn btn-light">Limpiar</a>
                        </div>
                    </form>
            </div>
        </div>

        <div class="col-12">
            <div class="wa-v2-shell">
                <div class="wa-v2-template-card">
                    <div class="wa-v2-panel-head">
                        <div class="wa-v2-sideheading__title">Plantillas</div>
                        <div class="wa-v2-sideheading__meta">Lista lateral con estado, origen, calidad y acciones rápidas.</div>
                    </div>
                    <div class="wa-v2-panel-body wa-v2-template-list">
                        @if($templates === [])
                            <div class="wa-v2-empty">
                                No hay plantillas para los filtros actuales.
                            </div>
                        @else
                            <div class="d-flex flex-column gap-10">
                                @foreach($templates as $index => $template)
                                    <div class="wa-v2-template-item {{ $index === 0 ? 'is-active' : '' }}" data-wa-template='@json($template)'>
                                        <div class="d-flex justify-content-between gap-10">
                                            <div>
                                                <div class="fw-700">{{ $template['display_name'] ?? $template['name'] }}</div>
                                                <div class="wa-v2-template-meta">{{ $template['name'] }}</div>
                                            </div>
                                            <span class="badge bg-light text-dark">{{ $template['status'] }}</span>
                                        </div>
                                        <div class="wa-v2-badge-row mt-10">
                                            <span class="badge bg-info-light text-info">{{ $template['category'] }}</span>
                                            <span class="badge bg-secondary-light text-secondary">{{ $template['language'] }}</span>
                                            <span class="badge bg-success-light text-success">Calidad: {{ $template['quality_score'] ?: 'n/a' }}</span>
                                            <span class="badge {{ ($template['source'] ?? '') === 'local' ? 'bg-warning-light text-warning' : 'bg-light text-dark' }}">
                                                {{ ($template['source'] ?? '') === 'local' ? 'Local' : 'Meta' }}
                                            </span>
                                            <span class="badge bg-primary-light text-primary">
                                                {{ $template['editorial_label'] ?? 'Plantilla' }}
                                            </span>
                                        </div>
                                        <div class="wa-v2-template-meta mt-10">
                                            {{ \Illuminate\Support\Str::limit($template['preview']['body_text'] ?? '', 150) }}
                                        </div>
                                        <div class="wa-v2-template-actions">
                                            <button type="button" class="btn btn-light btn-sm" data-wa-template-edit='@json($template)' {{ empty($template['is_editable']) ? 'disabled' : '' }}>
                                                Editar
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-wa-template-clone='@json($template)' {{ empty($template['can_clone']) ? 'disabled' : '' }}>
                                                Clonar
                                            </button>
                                            <button type="button" class="btn btn-outline-primary btn-sm" data-wa-template-publish='{{ $template['id'] }}' data-wa-template-header-type='{{ $template["preview"]["header_type"] ?? "none" }}' {{ empty($template['can_publish']) ? 'disabled' : '' }}>
                                                Publicar
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="wa-v2-template-card">
                    <div class="wa-v2-panel-head">
                        <div class="wa-v2-sideheading__title">Preview</div>
                        <div class="wa-v2-sideheading__meta">Lectura operativa del template, variables y revisiones locales.</div>
                    </div>
                    <div class="wa-v2-panel-body">
                        <div class="wa-v2-preview">
                            <div class="wa-v2-preview-header">
                                <div>
                                    <div class="wa-v2-preview-title" id="wa-v2-preview-name">{{ $selectedTemplate['display_name'] ?? 'Sin selección' }}</div>
                                    <div class="text-muted" id="wa-v2-preview-code">{{ $selectedTemplate['name'] ?? '' }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="badge bg-light text-dark" id="wa-v2-preview-status">{{ $selectedTemplate['status'] ?? '-' }}</div>
                                    <div class="text-muted mt-5" style="font-size:12px;" id="wa-v2-preview-source">{{ $selectedTemplate['source'] ?? '-' }}</div>
                                </div>
                            </div>
                            <div class="alert alert-info py-10 px-15 mb-15" id="wa-v2-preview-editorial">
                                {{ $selectedTemplate['editorial_label'] ?? 'Plantilla' }}
                            </div>
                            <div
                                class="alert alert-warning py-10 px-15 mb-15 {{ empty($selectedTemplate['rejected_reason']) ? 'd-none' : '' }}"
                                id="wa-v2-preview-rejection"
                            >
                                <div class="fw-700 mb-5">Motivo del rechazo</div>
                                <div id="wa-v2-preview-rejection-text">{{ $selectedTemplate['rejected_reason'] ?? '' }}</div>
                            </div>
                            <div class="d-flex gap-10 align-items-center mb-15" id="wa-v2-preview-metrics">
                                <span class="badge bg-success-light text-success" id="wa-v2-preview-quality">
                                    Calidad: {{ $selectedTemplate['quality_score'] ?: 'n/a' }}
                                </span>
                            </div>

                            <div class="wa-v2-message">
                                <div class="small text-muted mb-10" id="wa-v2-preview-header-type">
                                    {{ strtoupper((string) ($selectedTemplate['preview']['header_type'] ?? 'none')) }}
                                </div>
                                <div class="fw-600 mb-10" id="wa-v2-preview-header">
                                    {{ $selectedTemplate['preview']['header_text'] ?? '' }}
                                </div>
                                <div style="white-space: pre-line;" id="wa-v2-preview-body">{{ $selectedTemplate['preview']['body_text'] ?? '' }}</div>
                                <div class="text-muted mt-10" id="wa-v2-preview-footer">{{ $selectedTemplate['preview']['footer_text'] ?? '' }}</div>
                                <div class="wa-v2-badge-row mt-15" id="wa-v2-preview-buttons">
                                    @foreach(($selectedTemplate['preview']['buttons'] ?? []) as $button)
                                        <span class="badge bg-primary-light text-primary">{{ $button['text'] ?? $button['type'] ?? 'Botón' }}</span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mt-20">
                                <div class="fw-700 mb-5">Variables detectadas</div>
                                <div class="wa-v2-badge-row" id="wa-v2-preview-variables">
                                    @forelse(($selectedTemplate['preview']['variables'] ?? []) as $variable)
                                        <span class="badge bg-warning-light text-warning">{{ $variable }}</span>
                                    @empty
                                        <span class="text-muted">Sin variables detectadas</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="mt-20">
                                <div class="d-flex justify-content-between align-items-center mb-10">
                                    <div class="fw-700">Historial de revisiones</div>
                                    <span class="badge bg-light text-dark" id="wa-v2-preview-current-version">
                                        v{{ $selectedTemplate['current_revision_version'] ?? 0 }}
                                    </span>
                                </div>
                                <div class="d-flex flex-column gap-10" id="wa-v2-preview-revisions">
                                    @forelse(($selectedTemplate['revision_history'] ?? []) as $revision)
                                        <div class="border rounded p-10 bg-white">
                                            <div class="d-flex justify-content-between gap-10">
                                                <div class="fw-700">v{{ $revision['version'] }}</div>
                                                <span class="badge bg-light text-dark">{{ strtoupper($revision['status']) }}</span>
                                            </div>
                                            <div class="text-muted mt-5" style="font-size:12px;">{{ $revision['body_excerpt'] }}</div>
                                        </div>
                                    @empty
                                        <div class="text-muted">Sin historial local todavía.</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="mt-20 text-muted" style="font-size:12px;">
                                Las plantillas sincronizadas desde Meta se usan como referencia. Para modificarlas en Laravel primero se clonan a un borrador local.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="wa-v2-template-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" id="wa-v2-template-form">
            <div class="modal-header">
                <h5 class="modal-title" id="wa-v2-template-modal-title">Nueva plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" id="wa-v2-template-form-error"></div>
                <input type="hidden" id="wa-v2-template-id">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Código</label>
                        <input type="text" class="form-control" id="wa-v2-template-name" placeholder="ej: recordatorio_cita" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Idioma</label>
                        <select class="form-select" id="wa-v2-template-language" required>
                            <option value="">Selecciona</option>
                            @foreach($availableLanguages as $language)
                                <option value="{{ $language['code'] }}">{{ $language['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Categoría</label>
                        <select class="form-select" id="wa-v2-template-category" required>
                            <option value="">Selecciona</option>
                            @foreach($availableCategories as $category)
                                <option value="{{ $category['value'] }}">{{ $category['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Header</label>
                        <select class="form-select" id="wa-v2-template-header-type">
                            <option value="none">Sin header</option>
                            <option value="text">Texto</option>
                            <option value="image">Imagen</option>
                            <option value="video">Video</option>
                            <option value="document">Documento</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" id="wa-v2-template-header-label">Texto del header</label>
                        <input type="text" class="form-control" id="wa-v2-template-header-text" placeholder="Ej: Confirmación de cita">
                        <small class="text-muted" id="wa-v2-template-header-help">Para headers multimedia guarda un ejemplo o referencia local. La publicación final aún se completa desde legacy.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Cuerpo</label>
                        <textarea class="form-control" rows="5" id="wa-v2-template-body" placeholder="Hola {{1}}, tu cita es el {{2}}." required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Footer</label>
                        <input type="text" class="form-control" id="wa-v2-template-footer" placeholder="Equipo MedForge">
                    </div>
                </div>

                <div class="mt-20">
                    <div class="d-flex justify-content-between align-items-center mb-10">
                        <div class="fw-700">Botones</div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="wa-v2-template-add-button">Agregar botón</button>
                    </div>
                    <div class="d-flex flex-column gap-10" id="wa-v2-template-buttons"></div>
                </div>

                <div class="mt-20">
                    <div class="fw-700 mb-10">Preview</div>
                    <div class="wa-v2-builder-preview">
                        <div class="wa-v2-builder-message">
                            <div class="small text-muted mb-10" id="wa-v2-builder-preview-header-type">NONE</div>
                            <div class="fw-600 mb-10" id="wa-v2-builder-preview-header"></div>
                            <div style="white-space: pre-line;" id="wa-v2-builder-preview-body"></div>
                            <div class="text-muted mt-10" id="wa-v2-builder-preview-footer"></div>
                            <div class="wa-v2-badge-row mt-15" id="wa-v2-builder-preview-buttons"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="wa-v2-template-save-btn">Guardar borrador</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const items = Array.from(document.querySelectorAll('[data-wa-template]'));
    const syncButton = document.getElementById('wa-v2-sync-btn');
    const newTemplateButton = document.getElementById('wa-v2-new-template-btn');
    const templateModalElement = document.getElementById('wa-v2-template-modal');
    const templateModal = templateModalElement ? new bootstrap.Modal(templateModalElement) : null;
    const templateForm = document.getElementById('wa-v2-template-form');
    const modalTitle = document.getElementById('wa-v2-template-modal-title');
    const formError = document.getElementById('wa-v2-template-form-error');
    const saveButton = document.getElementById('wa-v2-template-save-btn');
    const fieldId = document.getElementById('wa-v2-template-id');
    const fieldName = document.getElementById('wa-v2-template-name');
    const fieldLanguage = document.getElementById('wa-v2-template-language');
    const fieldCategory = document.getElementById('wa-v2-template-category');
    const fieldHeaderType = document.getElementById('wa-v2-template-header-type');
    const fieldHeaderText = document.getElementById('wa-v2-template-header-text');
    const fieldHeaderLabel = document.getElementById('wa-v2-template-header-label');
    const fieldHeaderHelp = document.getElementById('wa-v2-template-header-help');
    const fieldBody = document.getElementById('wa-v2-template-body');
    const fieldFooter = document.getElementById('wa-v2-template-footer');
    const addButtonButton = document.getElementById('wa-v2-template-add-button');
    const buttonsContainer = document.getElementById('wa-v2-template-buttons');
    const editButtons = Array.from(document.querySelectorAll('[data-wa-template-edit]'));
    const cloneButtons = Array.from(document.querySelectorAll('[data-wa-template-clone]'));
    const publishButtons = Array.from(document.querySelectorAll('[data-wa-template-publish]'));

    const previewNodes = {
        name: document.getElementById('wa-v2-preview-name'),
        code: document.getElementById('wa-v2-preview-code'),
        status: document.getElementById('wa-v2-preview-status'),
        source: document.getElementById('wa-v2-preview-source'),
        editorial: document.getElementById('wa-v2-preview-editorial'),
        rejection: document.getElementById('wa-v2-preview-rejection'),
        rejectionText: document.getElementById('wa-v2-preview-rejection-text'),
        quality: document.getElementById('wa-v2-preview-quality'),
        headerType: document.getElementById('wa-v2-preview-header-type'),
        header: document.getElementById('wa-v2-preview-header'),
        body: document.getElementById('wa-v2-preview-body'),
        footer: document.getElementById('wa-v2-preview-footer'),
        buttons: document.getElementById('wa-v2-preview-buttons'),
        variables: document.getElementById('wa-v2-preview-variables'),
        currentVersion: document.getElementById('wa-v2-preview-current-version'),
        revisions: document.getElementById('wa-v2-preview-revisions'),
    };
    const builderPreview = {
        headerType: document.getElementById('wa-v2-builder-preview-header-type'),
        header: document.getElementById('wa-v2-builder-preview-header'),
        body: document.getElementById('wa-v2-builder-preview-body'),
        footer: document.getElementById('wa-v2-builder-preview-footer'),
        buttons: document.getElementById('wa-v2-builder-preview-buttons'),
    };

    const showFormError = (message) => {
        if (!formError) {
            return;
        }
        formError.textContent = message;
        formError.classList.remove('d-none');
    };

    const hideFormError = () => {
        if (!formError) {
            return;
        }
        formError.textContent = '';
        formError.classList.add('d-none');
    };

    const renderPreview = (template) => {
        const preview = template.preview || {};
        previewNodes.name.textContent = template.display_name || template.name || 'Sin selección';
        previewNodes.code.textContent = template.name || '';
        previewNodes.status.textContent = template.status || '-';
        previewNodes.source.textContent = template.source || '-';
        previewNodes.editorial.textContent = template.editorial_label || 'Plantilla';
        previewNodes.quality.textContent = `Calidad: ${template.quality_score || 'n/a'}`;
        if ((template.rejected_reason || '').trim()) {
            previewNodes.rejectionText.textContent = template.rejected_reason;
            previewNodes.rejection.classList.remove('d-none');
        } else {
            previewNodes.rejectionText.textContent = '';
            previewNodes.rejection.classList.add('d-none');
        }
        previewNodes.currentVersion.textContent = `v${template.current_revision_version || 0}`;
        previewNodes.headerType.textContent = String(preview.header_type || 'none').toUpperCase();
        previewNodes.header.textContent = ['image', 'video', 'document'].includes(String(preview.header_type || '').toLowerCase())
            ? (preview.header_text || `[${String(preview.header_type || '').toUpperCase()}]`)
            : (preview.header_text || '');
        previewNodes.body.textContent = preview.body_text || '';
        previewNodes.footer.textContent = preview.footer_text || '';

        previewNodes.buttons.innerHTML = '';
        const buttons = Array.isArray(preview.buttons) ? preview.buttons : [];
        if (buttons.length === 0) {
            previewNodes.buttons.innerHTML = '<span class="text-muted">Sin botones</span>';
        } else {
            buttons.forEach((button) => {
                const badge = document.createElement('span');
                badge.className = 'badge bg-primary-light text-primary';
                badge.textContent = button.text || button.type || 'Botón';
                previewNodes.buttons.appendChild(badge);
            });
        }

        previewNodes.variables.innerHTML = '';
        const variables = Array.isArray(preview.variables) ? preview.variables : [];
        if (variables.length === 0) {
            previewNodes.variables.innerHTML = '<span class="text-muted">Sin variables detectadas</span>';
        } else {
            variables.forEach((variable) => {
                const badge = document.createElement('span');
                badge.className = 'badge bg-warning-light text-warning';
                badge.textContent = variable;
                previewNodes.variables.appendChild(badge);
            });
        }

        previewNodes.revisions.innerHTML = '';
        const revisions = Array.isArray(template.revision_history) ? template.revision_history : [];
        if (revisions.length === 0) {
            previewNodes.revisions.innerHTML = '<div class="text-muted">Sin historial local todavía.</div>';
        } else {
            revisions.forEach((revision) => {
                const row = document.createElement('div');
                row.className = 'border rounded p-10 bg-white';
                row.innerHTML = `
                    <div class="d-flex justify-content-between gap-10">
                        <div class="fw-700">v${revision.version || 0}</div>
                        <span class="badge bg-light text-dark">${String(revision.status || '').toUpperCase()}</span>
                    </div>
                    <div class="text-muted mt-5" style="font-size:12px;">${revision.body_excerpt || ''}</div>
                `;
                previewNodes.revisions.appendChild(row);
            });
        }
    };

    const collectButtonPayload = () => {
        return Array.from(buttonsContainer.querySelectorAll('[data-wa-button-row]')).map((row) => {
            const type = row.querySelector('[data-wa-button-type]')?.value || 'QUICK_REPLY';
            const text = row.querySelector('[data-wa-button-text]')?.value?.trim() || '';
            const url = row.querySelector('[data-wa-button-url]')?.value?.trim() || '';
            const phone = row.querySelector('[data-wa-button-phone]')?.value?.trim() || '';

            if (!text) {
                return null;
            }

            if (type === 'URL' && url) {
                return { type, text, url };
            }

            if (type === 'PHONE_NUMBER' && phone) {
                return { type, text, phone_number: phone };
            }

            return { type: 'QUICK_REPLY', text };
        }).filter(Boolean);
    };

    const buildComponents = () => {
        const components = [];
        const headerType = fieldHeaderType?.value || 'none';
        const headerText = fieldHeaderText?.value?.trim() || '';
        const bodyText = fieldBody?.value?.trim() || '';
        const footerText = fieldFooter?.value?.trim() || '';
        const buttons = collectButtonPayload();

        if (headerType !== 'none' && headerText) {
            if (headerType === 'text') {
                components.push({ type: 'HEADER', format: 'TEXT', text: headerText });
            } else {
                components.push({ type: 'HEADER', format: String(headerType).toUpperCase(), example: headerText });
            }
        }

        if (bodyText) {
            components.push({ type: 'BODY', text: bodyText });
        }

        if (footerText) {
            components.push({ type: 'FOOTER', text: footerText });
        }

        if (buttons.length > 0) {
            components.push({ type: 'BUTTONS', buttons });
        }

        return components;
    };

    const renderBuilderPreview = () => {
        const components = buildComponents();
        const header = components.find((item) => item.type === 'HEADER');
        const body = components.find((item) => item.type === 'BODY');
        const footer = components.find((item) => item.type === 'FOOTER');
        const buttons = components.find((item) => item.type === 'BUTTONS');

        builderPreview.headerType.textContent = String(fieldHeaderType?.value || 'none').toUpperCase();
        if (header?.format && header.format !== 'TEXT') {
            builderPreview.header.textContent = header.example || `[${header.format}]`;
        } else {
            builderPreview.header.textContent = header?.text || '';
        }
        builderPreview.body.textContent = body?.text || '';
        builderPreview.footer.textContent = footer?.text || '';
        builderPreview.buttons.innerHTML = '';

        const buttonList = Array.isArray(buttons?.buttons) ? buttons.buttons : [];
        if (buttonList.length === 0) {
            builderPreview.buttons.innerHTML = '<span class="text-muted">Sin botones</span>';
            return;
        }

        buttonList.forEach((button) => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-primary-light text-primary';
            badge.textContent = button.text || button.type || 'Botón';
            builderPreview.buttons.appendChild(badge);
        });
    };

    const addButtonRow = (button = null) => {
        const row = document.createElement('div');
        row.className = 'border rounded p-10';
        row.dataset.waButtonRow = '1';
        row.innerHTML = `
            <div class="row g-2">
                <div class="col-md-3">
                    <select class="form-select" data-wa-button-type>
                        <option value="QUICK_REPLY">Respuesta rápida</option>
                        <option value="URL">Enlace</option>
                        <option value="PHONE_NUMBER">Teléfono</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" data-wa-button-text placeholder="Texto del botón">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control d-none" data-wa-button-url placeholder="https://...">
                    <input type="text" class="form-control d-none" data-wa-button-phone placeholder="+593...">
                </div>
                <div class="col-md-1 d-flex align-items-center">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" data-wa-button-remove>x</button>
                </div>
            </div>
        `;

        const typeSelect = row.querySelector('[data-wa-button-type]');
        const textInput = row.querySelector('[data-wa-button-text]');
        const urlInput = row.querySelector('[data-wa-button-url]');
        const phoneInput = row.querySelector('[data-wa-button-phone]');
        const removeButton = row.querySelector('[data-wa-button-remove]');

        const syncVisibility = () => {
            urlInput.classList.toggle('d-none', typeSelect.value !== 'URL');
            phoneInput.classList.toggle('d-none', typeSelect.value !== 'PHONE_NUMBER');
        };

        typeSelect.addEventListener('change', () => {
            syncVisibility();
            renderBuilderPreview();
        });
        [textInput, urlInput, phoneInput].forEach((input) => input.addEventListener('input', renderBuilderPreview));
        removeButton.addEventListener('click', () => {
            row.remove();
            renderBuilderPreview();
        });

        if (button) {
            typeSelect.value = button.type || 'QUICK_REPLY';
            textInput.value = button.text || '';
            urlInput.value = button.url || '';
            phoneInput.value = button.phone_number || '';
        }

        syncVisibility();
        buttonsContainer.appendChild(row);
    };

    const resetTemplateForm = () => {
        templateForm?.reset();
        fieldId.value = '';
        fieldName.removeAttribute('readonly');
        buttonsContainer.innerHTML = '';
        hideFormError();
        modalTitle.textContent = 'Nueva plantilla';
        syncHeaderFieldState();
        renderBuilderPreview();
    };

    const syncHeaderFieldState = () => {
        const headerType = fieldHeaderType?.value || 'none';
        if (headerType === 'text' || headerType === 'none') {
            fieldHeaderLabel.textContent = 'Texto del header';
            fieldHeaderText.placeholder = 'Ej: Confirmación de cita';
            fieldHeaderHelp.textContent = 'Usa texto directo para headers textuales.';
        } else {
            fieldHeaderLabel.textContent = 'Referencia del header';
            fieldHeaderText.placeholder = 'Ej: https://.../imagen.png o media-handle de Meta';
            fieldHeaderHelp.textContent = 'Para headers multimedia guarda un ejemplo o media handle compatible con Meta. Laravel ya enviará ese payload al publicar.';
        }
    };

    const openEditModal = (template) => {
        resetTemplateForm();
        fieldId.value = template.id || '';
        fieldName.value = template.name || '';
        fieldLanguage.value = template.language || '';
        fieldCategory.value = template.category || '';
        fieldName.setAttribute('readonly', 'readonly');

        const editableComponents = Array.isArray(template.editable_components) ? template.editable_components : [];
        const header = editableComponents.find((item) => item.type === 'HEADER');
        const body = editableComponents.find((item) => item.type === 'BODY');
        const footer = editableComponents.find((item) => item.type === 'FOOTER');
        const buttons = editableComponents.find((item) => item.type === 'BUTTONS');

        fieldHeaderType.value = header ? 'text' : 'none';
        if (header?.format && header.format !== 'TEXT') {
            fieldHeaderType.value = String(header.format).toLowerCase();
        }
        fieldHeaderText.value = header?.text || header?.example || '';
        fieldBody.value = body?.text || template.preview?.body_text || '';
        fieldFooter.value = footer?.text || template.preview?.footer_text || '';
        (Array.isArray(buttons?.buttons) ? buttons.buttons : []).forEach((button) => addButtonRow(button));

        modalTitle.textContent = `Editar ${template.name || 'plantilla'}`;
        syncHeaderFieldState();
        renderBuilderPreview();
        templateModal?.show();
    };

    const buildDraftPayload = () => {
        const name = fieldName.value.trim();
        const language = fieldLanguage.value.trim();
        const category = fieldCategory.value.trim();
        const body = fieldBody.value.trim();

        if (!name) {
            throw new Error('El código de la plantilla es obligatorio.');
        }
        if (!language) {
            throw new Error('El idioma es obligatorio.');
        }
        if (!category) {
            throw new Error('La categoría es obligatoria.');
        }
        if (!body) {
            throw new Error('El cuerpo es obligatorio.');
        }

        return {
            name,
            language,
            category,
            components: buildComponents(),
        };
    };

    items.forEach((item) => {
        item.addEventListener('click', () => {
            items.forEach((candidate) => candidate.classList.remove('is-active'));
            item.classList.add('is-active');
            renderPreview(JSON.parse(item.dataset.waTemplate || '{}'));
        });
    });

    newTemplateButton?.addEventListener('click', () => {
        resetTemplateForm();
        templateModal?.show();
    });

    addButtonButton?.addEventListener('click', () => {
        addButtonRow();
        renderBuilderPreview();
    });

    [fieldHeaderType, fieldHeaderText, fieldBody, fieldFooter].forEach((input) => {
        input?.addEventListener('input', renderBuilderPreview);
        input?.addEventListener('change', renderBuilderPreview);
    });

    fieldHeaderType?.addEventListener('change', syncHeaderFieldState);

    editButtons.forEach((button) => {
        button.addEventListener('click', () => {
            openEditModal(JSON.parse(button.dataset.waTemplateEdit || '{}'));
        });
    });

    cloneButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const template = JSON.parse(button.dataset.waTemplateClone || '{}');
            const baseName = String(template.name || 'template').toLowerCase();
            const suggestedName = `${baseName}_draft`;
            const cloneName = window.prompt('Código para el borrador local', suggestedName);

            if (!cloneName) {
                return;
            }

            button.disabled = true;
            const previous = button.textContent;
            button.textContent = 'Clonando...';

            try {
                const response = await fetch('/v2/whatsapp/api/templates/clone', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({ template, name: cloneName }),
                });
                const payload = await response.json();
                if (!response.ok || payload.ok !== true) {
                    throw new Error(payload.error || 'No fue posible clonar la plantilla.');
                }
                window.location.reload();
            } catch (error) {
                window.alert(error.message || 'No fue posible clonar la plantilla.');
                button.disabled = false;
                button.textContent = previous;
            }
        });
    });

    publishButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const templateId = button.dataset.waTemplatePublish;
            if (!templateId) {
                return;
            }

            if (!window.confirm('Se enviará la revisión actual a Meta para aprobación.')) {
                return;
            }

            button.disabled = true;
            const previous = button.textContent;
            button.textContent = 'Publicando...';

            try {
                const response = await fetch(`/v2/whatsapp/api/templates/${templateId}/publish`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({}),
                });
                const payload = await response.json();
                if (!response.ok || payload.ok !== true) {
                    throw new Error(payload.error || 'No fue posible publicar la plantilla.');
                }
                window.location.reload();
            } catch (error) {
                window.alert(error.message || 'No fue posible publicar la plantilla.');
                button.disabled = false;
                button.textContent = previous;
            }
        });
    });

    templateForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideFormError();
        saveButton.disabled = true;

        try {
            const payload = buildDraftPayload();
            const templateId = fieldId.value.trim();
            const endpoint = templateId ? `/v2/whatsapp/api/templates/${templateId}` : '/v2/whatsapp/api/templates';
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(payload),
            });

            const result = await response.json();
            if (!response.ok || result.ok !== true) {
                throw new Error(result.error || 'No fue posible guardar el borrador.');
            }

            window.location.reload();
        } catch (error) {
            showFormError(error.message || 'No fue posible guardar el borrador.');
            saveButton.disabled = false;
        }
    });

    syncButton?.addEventListener('click', async () => {
        if (!window.confirm('Se consultará Meta y se actualizará el cache local de plantillas.')) {
            return;
        }

        syncButton.disabled = true;
        const previousText = syncButton.textContent;
        syncButton.textContent = 'Sincronizando...';

        try {
            const response = await fetch('/v2/whatsapp/api/templates/sync', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ limit: 100 }),
            });

            const payload = await response.json();
            if (!response.ok || payload.ok !== true) {
                throw new Error(payload.error || 'No fue posible sincronizar.');
            }

            window.location.reload();
        } catch (error) {
            window.alert(error.message || 'No fue posible sincronizar.');
            syncButton.disabled = false;
            syncButton.textContent = previousText;
        }
    });

    syncHeaderFieldState();
    renderBuilderPreview();
});
</script>
@endpush
