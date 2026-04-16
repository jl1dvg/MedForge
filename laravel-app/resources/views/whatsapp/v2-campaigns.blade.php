@extends('layouts.medforge')

@php
    $campaigns = is_array($campaigns ?? null) ? $campaigns : [];
    $templates = is_array($templates ?? null) ? $templates : [];
    $audienceSuggestions = is_array(($audienceSuggestions ?? null) ?: (($campaignsOverview['audience_suggestions'] ?? null) ?: null)) ? (($audienceSuggestions ?? null) ?: ($campaignsOverview['audience_suggestions'] ?? [])) : [];
    $campaignStats = [
        'total' => count($campaigns),
        'draft' => collect($campaigns)->where('status', 'draft')->count(),
        'dry_run_ready' => collect($campaigns)->where('status', 'dry_run_ready')->count(),
        'audience' => collect($campaigns)->sum(fn ($campaign) => (int) ($campaign['audience_count'] ?? 0)),
    ];
@endphp

@push('styles')
    <style>
        .wa-campaigns-pagebar {
            border-radius: 28px;
            padding: 24px 26px;
            background:
                radial-gradient(circle at top left, rgba(14, 165, 233, .18), transparent 34%),
                radial-gradient(circle at top right, rgba(59, 130, 246, .14), transparent 28%),
                linear-gradient(145deg, #0f172a 0%, #1e293b 52%, #0f766e 100%);
            color: #f8fafc;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .16);
        }

        .wa-campaigns-pagebar__top {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
        }

        .wa-campaigns-pagebar__title {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -.03em;
        }

        .wa-campaigns-pagebar__subtitle {
            margin-top: 8px;
            color: rgba(248, 250, 252, .82);
            max-width: 760px;
            font-size: 14px;
            line-height: 1.6;
        }

        .wa-campaigns-pagebar__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }

        .wa-campaigns-hero-pill {
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

        .wa-campaigns-shell {
            display: grid;
            grid-template-columns: minmax(300px, 360px) minmax(0, 1fr);
            gap: 18px;
        }

        .wa-campaigns-panel {
            border-radius: 24px;
            border: 1px solid rgba(148, 163, 184, .18);
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            box-shadow: 0 20px 40px rgba(15, 23, 42, .05);
            overflow: hidden;
        }

        .wa-campaigns-panel__header {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(148, 163, 184, .14);
            background: radial-gradient(circle at top left, rgba(14, 165, 233, .08), transparent 42%), #fff;
        }

        .wa-campaigns-panel__body {
            padding: 18px 20px;
        }

        .wa-campaigns-sideheading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .wa-campaigns-sideheading__title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -.02em;
            color: #0f172a;
        }

        .wa-campaigns-sideheading__meta {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .wa-campaigns-statgrid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .wa-campaigns-statcard {
            border-radius: 18px;
            padding: 16px;
            border: 1px solid rgba(148, 163, 184, .16);
            background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96));
        }

        .wa-campaigns-statcard__value {
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -.04em;
            color: #0f172a;
        }

        .wa-campaigns-statcard__label {
            margin-top: 4px;
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .wa-campaign-list {
            display: grid;
            gap: 12px;
        }

        .wa-campaign-item {
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, .16);
            padding: 16px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
        }

        .wa-campaign-item__top {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
        }

        .wa-campaign-item__title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.35;
        }

        .wa-campaign-item__meta {
            margin-top: 4px;
            color: #64748b;
            font-size: 12px;
        }

        .wa-campaign-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .wa-campaign-status.is-draft {
            background: #e2e8f0;
            color: #334155;
        }

        .wa-campaign-status.is-dry-run-ready {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .wa-campaign-metric-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .wa-campaign-metric-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 999px;
            padding: 7px 10px;
            background: #f8fafc;
            border: 1px solid rgba(148, 163, 184, .16);
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        .wa-campaign-builder {
            display: grid;
            gap: 18px;
        }

        .wa-campaign-section {
            border-radius: 20px;
            border: 1px solid rgba(148, 163, 184, .16);
            background: #fff;
            padding: 18px;
        }

        .wa-campaign-section__top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
        }

        .wa-campaign-section__title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.3;
        }

        .wa-campaign-section__meta {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .wa-campaign-compose-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(320px, .95fr);
            gap: 18px;
        }

        .wa-campaign-preview {
            border-radius: 18px;
            border: 1px dashed #cbd5e1;
            padding: 16px;
            background:
                radial-gradient(circle at top right, rgba(14,165,233,.07), transparent 28%),
                #fff;
            min-height: 180px;
            white-space: pre-wrap;
            color: #0f172a;
            font-size: 13px;
            line-height: 1.65;
        }

        .wa-campaign-summary-card {
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, .16);
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            padding: 16px;
            display: grid;
            gap: 14px;
        }

        .wa-campaign-summary-card__row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            font-size: 13px;
            color: #334155;
        }

        .wa-campaign-summary-card__label {
            color: #64748b;
        }

        .wa-campaign-segment-switch {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .wa-campaign-suggestions {
            display: grid;
            gap: 10px;
            max-height: 280px;
            overflow: auto;
        }

        .wa-campaign-suggestion {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            border-radius: 16px;
            padding: 12px 14px;
            background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96));
            border: 1px solid rgba(148, 163, 184, .16);
        }

        .wa-campaign-suggestion__name {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }

        .wa-campaign-suggestion__meta {
            color: #64748b;
            font-size: 12px;
        }

        .wa-campaign-audience-hint {
            margin-top: 8px;
            color: #64748b;
            font-size: 12px;
        }

        .wa-campaign-feedback {
            min-height: 20px;
            font-size: 12px;
        }

        @media (max-width: 1199px) {
            .wa-campaigns-shell,
            .wa-campaign-compose-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767px) {
            .wa-campaigns-pagebar {
                padding: 20px 18px;
                border-radius: 24px;
            }

            .wa-campaigns-pagebar__top {
                flex-direction: column;
            }

            .wa-campaigns-panel__header,
            .wa-campaigns-panel__body {
                padding: 16px;
            }

            .wa-campaigns-statgrid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="content">
        <div class="row mb-15">
            <div class="col-12">
                <div class="wa-campaigns-pagebar">
                    <div class="wa-campaigns-pagebar__top">
                        <div>
                            <div class="wa-campaigns-pagebar__title">Campañas MVP</div>
                            <div class="wa-campaigns-pagebar__subtitle">
                                Borradores, audiencia operativa, selección de plantilla y dry run con trazabilidad por destinatario.
                                El enfoque aquí ya no es formulario suelto, sino una consola de campaña más cercana a producto.
                            </div>
                        </div>
                        <div class="wa-campaigns-pagebar__meta">
                            <span class="wa-campaigns-hero-pill"><i class="mdi mdi-send-clock-outline"></i> Sin envío real todavía</span>
                            <span class="wa-campaigns-hero-pill"><i class="mdi mdi-account-group-outline"></i> {{ $campaignStats['audience'] }} destinatarios cargados</span>
                            <span class="wa-campaigns-hero-pill"><i class="mdi mdi-test-tube"></i> Dry run trazable</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wa-campaigns-shell">
            <div class="wa-campaigns-panel">
                <div class="wa-campaigns-panel__header">
                    <div class="wa-campaigns-sideheading">
                        <div>
                            <div class="wa-campaigns-sideheading__title">Campañas recientes</div>
                            <div class="wa-campaigns-sideheading__meta">Historial visible de drafts y dry runs para decidir rápido cuál reutilizar o volver a ejecutar.</div>
                        </div>
                    </div>
                </div>
                <div class="wa-campaigns-panel__body">
                    <div class="wa-campaigns-statgrid mb-15">
                        <div class="wa-campaigns-statcard">
                            <div class="wa-campaigns-statcard__value">{{ $campaignStats['total'] }}</div>
                            <div class="wa-campaigns-statcard__label">Campañas</div>
                        </div>
                        <div class="wa-campaigns-statcard">
                            <div class="wa-campaigns-statcard__value">{{ $campaignStats['draft'] }}</div>
                            <div class="wa-campaigns-statcard__label">Borradores</div>
                        </div>
                        <div class="wa-campaigns-statcard">
                            <div class="wa-campaigns-statcard__value">{{ $campaignStats['dry_run_ready'] }}</div>
                            <div class="wa-campaigns-statcard__label">Dry run listo</div>
                        </div>
                        <div class="wa-campaigns-statcard">
                            <div class="wa-campaigns-statcard__value">{{ $campaignStats['audience'] }}</div>
                            <div class="wa-campaigns-statcard__label">Audiencia</div>
                        </div>
                    </div>

                    <div class="wa-campaign-list" id="wa-campaign-list">
                        @forelse($campaigns as $campaign)
                            @php
                                $status = (string) ($campaign['status'] ?? 'draft');
                                $statusClass = $status === 'dry_run_ready' ? 'is-dry-run-ready' : 'is-draft';
                                $deliveryStats = is_array($campaign['delivery_stats'] ?? null) ? $campaign['delivery_stats'] : [];
                            @endphp
                            <div class="wa-campaign-item">
                                <div class="wa-campaign-item__top">
                                    <div>
                                        <div class="wa-campaign-item__title">{{ $campaign['name'] ?? 'Campaña' }}</div>
                                        <div class="wa-campaign-item__meta">
                                            {{ $campaign['template_name'] ?? 'Sin template' }} ·
                                            {{ $campaign['audience_count'] ?? 0 }} destinatarios
                                        </div>
                                    </div>
                                    <span class="wa-campaign-status {{ $statusClass }}">{{ $status }}</span>
                                </div>

                                <div class="wa-campaign-metric-row">
                                    <span class="wa-campaign-metric-pill"><i class="mdi mdi-flask-outline"></i> dry_run: {{ !empty($campaign['dry_run']) ? 'sí' : 'no' }}</span>
                                    <span class="wa-campaign-metric-pill"><i class="mdi mdi-account-multiple-outline"></i> {{ $campaign['audience_count'] ?? 0 }} audiencia</span>
                                    <span class="wa-campaign-metric-pill"><i class="mdi mdi-check-decagram-outline"></i> {{ (int) ($deliveryStats['dry_run_ready'] ?? 0) }} listos</span>
                                </div>

                                <div class="wa-campaign-item__meta mt-10">
                                    última ejecución:
                                    {{ !empty($campaign['last_executed_at']) ? \Illuminate\Support\Carbon::parse($campaign['last_executed_at'])->format('d/m H:i') : 'nunca' }}
                                </div>

                                <div class="d-flex gap-10 mt-12">
                                    <button type="button" class="btn btn-outline-primary btn-sm" data-wa-campaign-dry-run="{{ $campaign['id'] ?? 0 }}">
                                        Ejecutar dry run
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted">Todavía no hay campañas creadas.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="wa-campaigns-panel">
                <div class="wa-campaigns-panel__header">
                    <div class="wa-campaigns-sideheading">
                        <div>
                            <div class="wa-campaigns-sideheading__title">Nuevo borrador</div>
                            <div class="wa-campaigns-sideheading__meta">Define audiencia, plantilla y lectura operativa del envío antes de tocar una ejecución real.</div>
                        </div>
                    </div>
                </div>
                <div class="wa-campaigns-panel__body">
                    <form id="wa-campaign-form" class="wa-campaign-builder">
                        <div class="wa-campaign-section">
                            <div class="wa-campaign-section__top">
                                <div>
                                    <div class="wa-campaign-section__title">Identidad de campaña</div>
                                    <div class="wa-campaign-section__meta">Nombre visible para el equipo y plantilla base aprobada para el dry run.</div>
                                </div>
                            </div>

                            <div class="row g-15">
                                <div class="col-lg-7">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" class="form-control" id="wa-campaign-name" placeholder="Ej. Recordatorio cirugía abril">
                                </div>
                                <div class="col-lg-5">
                                    <label class="form-label">Template</label>
                                    <select class="form-select" id="wa-campaign-template">
                                        <option value="">Selecciona un template</option>
                                        @foreach($templates as $template)
                                            <option value="{{ $template['id'] }}">{{ $template['name'] }} · {{ $template['language'] ?: 'n/a' }} · {{ $template['status'] ?: 'n/a' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="wa-campaign-compose-grid">
                            <div class="wa-campaign-section">
                                <div class="wa-campaign-section__top">
                                    <div>
                                        <div class="wa-campaign-section__title">Audiencia operativa</div>
                                        <div class="wa-campaign-section__meta">Una línea por destinatario. Formato: <code>593999111222|Nombre opcional</code>.</div>
                                    </div>
                                </div>

                                <label class="form-label">Audiencia</label>
                                <textarea class="form-control" id="wa-campaign-audience" rows="11" placeholder="593999111222|María Pérez&#10;0999123456|Paciente 2"></textarea>
                                <div class="wa-campaign-audience-hint">
                                    Puedes mezclar números manuales con contactos sugeridos desde conversaciones activas o resueltas.
                                </div>

                                <div class="wa-campaign-section__top mt-18">
                                    <div>
                                        <div class="wa-campaign-section__title" style="font-size:14px;">Sugerencias desde conversaciones</div>
                                        <div class="wa-campaign-section__meta">Úsalo como builder rápido para recientes, en cola o resueltas.</div>
                                    </div>
                                </div>

                                <div class="wa-campaign-segment-switch mb-10">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-wa-segment="recent_open">Recientes</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-wa-segment="needs_human">En cola</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-wa-segment="resolved_recent">Resueltas</button>
                                </div>

                                <div class="wa-campaign-suggestions" id="wa-campaign-suggestions">
                                    @forelse($audienceSuggestions as $target)
                                        <div class="wa-campaign-suggestion">
                                            <div>
                                                <div class="wa-campaign-suggestion__name">{{ $target['display_name'] ?? $target['wa_number'] }}</div>
                                                <div class="wa-campaign-suggestion__meta">
                                                    {{ $target['wa_number'] }} · {{ !empty($target['needs_human']) ? 'en cola' : 'resuelta' }}
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm"
                                                data-wa-append-audience="{{ ($target['wa_number'] ?? '') . '|' . ($target['display_name'] ?? '') }}">
                                                Agregar
                                            </button>
                                        </div>
                                    @empty
                                        <div class="text-muted">Sin sugerencias disponibles todavía.</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="wa-campaign-section">
                                <div class="wa-campaign-section__top">
                                    <div>
                                        <div class="wa-campaign-section__title">Preview operativo</div>
                                        <div class="wa-campaign-section__meta">Antes de guardar, valida plantilla, volumen esperado y una muestra corta de la audiencia.</div>
                                    </div>
                                </div>

                                <div class="wa-campaign-preview" id="wa-campaign-preview">Selecciona un template y pega la audiencia para validar el borrador.</div>

                                <div class="wa-campaign-summary-card mt-14">
                                    <div class="wa-campaign-summary-card__row">
                                        <span class="wa-campaign-summary-card__label">Modo</span>
                                        <strong>Dry run</strong>
                                    </div>
                                    <div class="wa-campaign-summary-card__row">
                                        <span class="wa-campaign-summary-card__label">Entrega real</span>
                                        <strong>No habilitada</strong>
                                    </div>
                                    <div class="wa-campaign-summary-card__row">
                                        <span class="wa-campaign-summary-card__label">Objetivo</span>
                                        <strong>Validar audiencia y trazabilidad</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Guardar borrador</button>
                        </div>
                    </form>
                    <div id="wa-campaign-feedback" class="wa-campaign-feedback mt-10 text-muted"></div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('wa-campaign-form');
            const nameInput = document.getElementById('wa-campaign-name');
            const templateInput = document.getElementById('wa-campaign-template');
            const audienceInput = document.getElementById('wa-campaign-audience');
            const preview = document.getElementById('wa-campaign-preview');
            const feedback = document.getElementById('wa-campaign-feedback');
            const suggestionsContainer = document.getElementById('wa-campaign-suggestions');

            const setFeedback = function (message, tone) {
                if (!feedback) {
                    return;
                }

                feedback.textContent = message;
                feedback.className = `wa-campaign-feedback mt-10 text-${tone}`;
            };

            const refreshPreview = function () {
                const templateLabel = templateInput && templateInput.selectedOptions.length > 0
                    ? templateInput.selectedOptions[0].textContent
                    : 'Sin template';
                const audienceLines = (audienceInput ? audienceInput.value : '')
                    .split(/\r\n|\r|\n/)
                    .map(line => line.trim())
                    .filter(Boolean);

                preview.textContent = [
                    `Template: ${templateLabel || 'Sin template'}`,
                    `Destinatarios válidos esperados: ${audienceLines.length}`,
                    '',
                    audienceLines.slice(0, 6).join('\n') || 'Sin audiencia cargada'
                ].join('\n');
            };

            [templateInput, audienceInput].forEach(function (element) {
                if (!element) {
                    return;
                }

                element.addEventListener('input', refreshPreview);
                element.addEventListener('change', refreshPreview);
            });

            refreshPreview();

            const renderSuggestions = function (rows) {
                if (!suggestionsContainer) {
                    return;
                }

                if (!rows || rows.length === 0) {
                    suggestionsContainer.innerHTML = '<div class="text-muted">Sin sugerencias disponibles todavía.</div>';
                    return;
                }

                suggestionsContainer.innerHTML = rows.map(function (row) {
                    const label = row.display_name || row.wa_number || 'Contacto';
                    const number = row.wa_number || '';
                    const state = row.needs_human ? 'en cola' : 'resuelta';
                    const appendValue = `${number}|${label}`;

                    return `
                        <div class="wa-campaign-suggestion">
                            <div>
                                <div class="wa-campaign-suggestion__name">${label}</div>
                                <div class="wa-campaign-suggestion__meta">${number} · ${state}</div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" data-wa-append-audience="${appendValue}">
                                Agregar
                            </button>
                        </div>
                    `;
                }).join('');
            };

            const bindAudienceAppenders = function () {
                document.querySelectorAll('[data-wa-append-audience]').forEach(function (button) {
                    button.onclick = function () {
                        const value = button.getAttribute('data-wa-append-audience') || '';
                        if (!audienceInput || !value) {
                            return;
                        }

                        const current = (audienceInput.value || '').trim();
                        const lines = current ? current.split(/\r\n|\r|\n/).map(line => line.trim()).filter(Boolean) : [];
                        if (!lines.includes(value)) {
                            lines.push(value);
                        }

                        audienceInput.value = lines.join('\n');
                        refreshPreview();
                    };
                });
            };

            bindAudienceAppenders();

            document.querySelectorAll('[data-wa-segment]').forEach(function (button) {
                button.addEventListener('click', async function () {
                    const segment = button.getAttribute('data-wa-segment') || 'recent_open';

                    try {
                        setFeedback('Cargando sugerencias...', 'muted');
                        const response = await fetch(`/v2/whatsapp/api/campaigns/audience-suggestions?segment=${encodeURIComponent(segment)}`, {
                            headers: {'Accept': 'application/json'}
                        });
                        const data = await response.json();
                        if (!response.ok || !data.ok) {
                            throw new Error(data.error || 'No fue posible cargar sugerencias.');
                        }

                        renderSuggestions(data.data || []);
                        bindAudienceAppenders();
                        setFeedback('Sugerencias actualizadas.', 'success');
                    } catch (error) {
                        setFeedback(error.message || 'No fue posible cargar sugerencias.', 'danger');
                    }
                });
            });

            const postJson = async function (url, payload, pending, success) {
                setFeedback(pending, 'muted');

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                if (!response.ok || !data.ok) {
                    throw new Error(data.error || 'No fue posible completar la acción.');
                }

                setFeedback(success, 'success');
                window.location.reload();
            };

            if (form) {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    try {
                        await postJson('/v2/whatsapp/api/campaigns', {
                            name: nameInput ? nameInput.value : '',
                            template_id: templateInput ? templateInput.value : '',
                            audience_text: audienceInput ? audienceInput.value : ''
                        }, 'Guardando campaña...', 'Campaña guardada. Recargando...');
                    } catch (error) {
                        setFeedback(error.message || 'No fue posible guardar la campaña.', 'danger');
                    }
                });
            }

            document.querySelectorAll('[data-wa-campaign-dry-run]').forEach(function (button) {
                button.addEventListener('click', async function () {
                    const campaignId = button.getAttribute('data-wa-campaign-dry-run');

                    try {
                        await postJson(`/v2/whatsapp/api/campaigns/${campaignId}/dry-run`, {}, 'Ejecutando dry run...', 'Dry run completado. Recargando...');
                    } catch (error) {
                        setFeedback(error.message || 'No fue posible ejecutar el dry run.', 'danger');
                    }
                });
            });
        });
    </script>
@endpush
