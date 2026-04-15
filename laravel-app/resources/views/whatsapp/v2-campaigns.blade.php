@extends('layouts.medforge')

@php
    $campaigns = is_array($campaigns ?? null) ? $campaigns : [];
    $templates = is_array($templates ?? null) ? $templates : [];
    $audienceSuggestions = is_array(($audienceSuggestions ?? null) ?: (($campaignsOverview['audience_suggestions'] ?? null) ?: null)) ? (($audienceSuggestions ?? null) ?: ($campaignsOverview['audience_suggestions'] ?? [])) : [];
@endphp

@push('styles')
    <style>
        .wa-campaigns-shell {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(340px, .9fr);
            gap: 18px;
        }

        .wa-campaign-card {
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 18px;
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            overflow: hidden;
        }

        .wa-campaign-card__header {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(15, 23, 42, .08);
            background: radial-gradient(circle at top left, rgba(14, 165, 233, .08), transparent 42%), #fff;
        }

        .wa-campaign-list {
            display: grid;
            gap: 12px;
            padding: 16px;
        }

        .wa-campaign-item {
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 14px;
            padding: 14px;
            background: #fff;
        }

        .wa-campaign-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 8px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 700;
        }

        .wa-campaign-preview {
            border-radius: 14px;
            border: 1px dashed #cbd5e1;
            padding: 12px;
            background: #fff;
            min-height: 140px;
            white-space: pre-wrap;
        }

        .wa-campaign-suggestions {
            display: grid;
            gap: 8px;
            max-height: 220px;
            overflow: auto;
        }

        .wa-campaign-suggestion {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 12px;
            background: #fff;
            padding: 10px 12px;
        }

        @media (max-width: 991px) {
            .wa-campaigns-shell {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="content">
        <div class="row mb-15">
            <div class="col-12">
                <div class="box mb-0">
                    <div class="box-body d-flex flex-wrap justify-content-between align-items-center gap-15">
                        <div>
                            <h2 class="mb-5">Campañas MVP</h2>
                            <div class="text-muted">Fase 7 arranca con borradores, audiencia manual, template y ejecución en dry run.</div>
                        </div>
                        <div class="wa-campaign-pill">Sin envío real todavía</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wa-campaigns-shell">
            <div class="wa-campaign-card">
                <div class="wa-campaign-card__header">
                    <h4 class="mb-5">Campañas recientes</h4>
                    <div class="text-muted">Cada dry run deja trazabilidad por destinatario sin tocar producción.</div>
                </div>
                <div class="wa-campaign-list" id="wa-campaign-list">
                    @forelse($campaigns as $campaign)
                        <div class="wa-campaign-item">
                            <div class="d-flex justify-content-between align-items-start gap-10">
                                <div>
                                    <div class="fw-700">{{ $campaign['name'] ?? 'Campaña' }}</div>
                                    <div class="text-muted" style="font-size:12px;">{{ $campaign['template_name'] ?? 'Sin template' }} · {{ $campaign['audience_count'] ?? 0 }} destinatarios</div>
                                </div>
                                <span class="wa-campaign-pill">{{ $campaign['status'] ?? 'draft' }}</span>
                            </div>
                            <div class="d-flex gap-15 mt-10 text-muted" style="font-size:12px;">
                                <span>dry_run: {{ !empty($campaign['dry_run']) ? 'sí' : 'no' }}</span>
                                <span>última ejecución: {{ !empty($campaign['last_executed_at']) ? \Illuminate\Support\Carbon::parse($campaign['last_executed_at'])->format('d/m H:i') : 'nunca' }}</span>
                            </div>
                            <div class="d-flex gap-10 mt-10">
                                <button type="button" class="btn btn-outline-primary btn-sm" data-wa-campaign-dry-run="{{ $campaign['id'] ?? 0 }}">Ejecutar dry run</button>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">Todavía no hay campañas creadas.</div>
                    @endforelse
                </div>
            </div>

            <div class="wa-campaign-card">
                <div class="wa-campaign-card__header">
                    <h4 class="mb-5">Nuevo borrador</h4>
                    <div class="text-muted">Audiencia manual. Una línea por destinatario: `593999111222|Nombre opcional`.</div>
                </div>
                <div class="p-16">
                    <form id="wa-campaign-form" class="d-grid gap-12">
                        <div>
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" id="wa-campaign-name" placeholder="Ej. Recordatorio cirugía abril">
                        </div>
                        <div>
                            <label class="form-label">Template</label>
                            <select class="form-select" id="wa-campaign-template">
                                <option value="">Selecciona un template</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template['id'] }}">{{ $template['name'] }} · {{ $template['language'] ?: 'n/a' }} · {{ $template['status'] ?: 'n/a' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Audiencia</label>
                            <textarea class="form-control" id="wa-campaign-audience" rows="7" placeholder="593999111222|María Pérez&#10;0999123456|Paciente 2"></textarea>
                            <div class="text-muted mt-5" style="font-size:12px;">También puedes cargar sugerencias desde conversaciones recientes.</div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-8">
                                <label class="form-label mb-0">Sugerencias desde conversaciones</label>
                                <div class="d-flex gap-8">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-wa-segment="recent_open">Recientes</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-wa-segment="needs_human">En cola</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-wa-segment="resolved_recent">Resueltas</button>
                                </div>
                            </div>
                            <div class="wa-campaign-suggestions" id="wa-campaign-suggestions">
                                @forelse($audienceSuggestions as $target)
                                    <div class="wa-campaign-suggestion">
                                        <div>
                                            <div class="fw-700">{{ $target['display_name'] ?? $target['wa_number'] }}</div>
                                            <div class="text-muted" style="font-size:12px;">{{ $target['wa_number'] }} · {{ !empty($target['needs_human']) ? 'en cola' : 'resuelta' }}</div>
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
                        <div>
                            <label class="form-label">Preview operativo</label>
                            <div class="wa-campaign-preview" id="wa-campaign-preview">Selecciona un template y pega la audiencia para validar el borrador.</div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Guardar borrador</button>
                        </div>
                    </form>
                    <div id="wa-campaign-feedback" class="mt-10 text-muted" style="font-size:12px;"></div>
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
                feedback.className = `mt-10 text-${tone}`;
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
                    audienceLines.slice(0, 5).join('\n') || 'Sin audiencia cargada'
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
                                <div class="fw-700">${label}</div>
                                <div class="text-muted" style="font-size:12px;">${number} · ${state}</div>
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
