@extends('layouts.medforge')

@php
    $sections = is_array($sections ?? null) ? $sections : [];
    $activeSection = (string) ($activeSection ?? array_key_first($sections));
    $baseRules = is_array($baseRules ?? null) ? $baseRules : [];
    $stageRules = is_array($stageRules ?? null) ? $stageRules : [];
    $categoryLabels = is_array($categoryLabels ?? null) ? $categoryLabels : [];
    $stageLabels = is_array($stageLabels ?? null) ? $stageLabels : [];
@endphp

@push('styles')
    <style>
        .settings-sidenav-wrap {
            position: sticky;
            top: 20px;
        }

        .settings-sidenav .nav-link {
            border-radius: 6px;
            margin: 1px 4px;
            padding: 8px 12px;
            color: #555;
            transition: background 0.15s, color 0.15s;
        }

        .settings-sidenav .nav-link:hover {
            background: #f0f4ff;
            color: var(--bs-primary);
        }

        .settings-sidenav .nav-link.active {
            background: var(--bs-primary);
            color: #fff;
        }

        .settings-sidenav .nav-link.active .badge {
            background: rgba(255,255,255,0.3) !important;
            color: #fff;
        }

        .settings-section-card textarea.form-control {
            min-height: 96px;
            resize: vertical;
        }

        .settings-section-card .form-label {
            color: #233142;
        }

        .settings-advanced-json {
            border: 1px solid #d8e0ea;
            border-radius: 8px;
            background: #f8fafc;
        }

        .settings-advanced-json summary {
            min-height: 44px;
            padding: 10px 14px;
            cursor: pointer;
            color: #334155;
            font-weight: 600;
            list-style-position: inside;
        }

        .settings-advanced-json__body {
            border-top: 1px solid #d8e0ea;
            padding: 14px;
        }

        .settings-weekly-schedule {
            border: 1px solid #d8e0ea;
            border-radius: 8px;
            overflow: hidden;
        }

        .settings-weekly-row {
            display: grid;
            grid-template-columns: minmax(120px, 1fr) minmax(120px, 150px) minmax(120px, 150px) minmax(132px, auto);
            gap: 12px;
            align-items: center;
            padding: 12px 14px;
            border-top: 1px solid #e6edf5;
            background: #fff;
        }

        .settings-weekly-row:first-child {
            border-top: 0;
        }

        .settings-weekly-row.is-disabled {
            background: #f8fafc;
            color: #64748b;
        }

        .settings-weekly-row.is-disabled input[type="time"] {
            opacity: 0.55;
        }

        .settings-weekly-day {
            font-weight: 600;
        }

        @media (max-width: 767.98px) {
            .settings-weekly-row {
                grid-template-columns: 1fr;
            }
        }

        .settings-file-preview img {
            max-height: 54px;
            max-width: 180px;
            object-fit: contain;
        }

        .settings-file-new-preview {
            max-height: 60px;
            max-width: 200px;
            object-fit: contain;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            padding: 4px;
        }

        .settings-color-swatch {
            display: inline-block;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            vertical-align: middle;
            margin-left: 8px;
        }

        .settings-password-wrap {
            position: relative;
        }

        .settings-password-wrap .btn-pw-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #888;
            padding: 0;
            line-height: 1;
        }

        .settings-password-wrap .form-control {
            padding-right: 36px;
        }

        .settings-save-btn .spinner-border {
            display: none;
            width: 1rem;
            height: 1rem;
            margin-right: 4px;
        }

        .settings-save-btn.loading .spinner-border {
            display: inline-block;
        }

        .sla-settings-table .form-control,
        .sla-settings-table .form-select {
            min-width: 120px;
        }

        .sla-settings-table textarea.form-control {
            min-width: 220px;
            min-height: 72px;
        }

        .sla-settings-override {
            background: #f8fafc;
        }
    </style>
@endpush

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Configuración</h3>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Ajustes</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <section class="content">
        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row">
            <div class="col-xl-3 col-lg-4">
                <div class="settings-sidenav-wrap">
                    <div class="box">
                        <div class="box-header with-border">
                            <h4 class="box-title mb-0">Secciones</h4>
                        </div>
                        <div class="box-body p-0 pb-2">
                            <ul class="nav nav-pills flex-column settings-sidenav">
                                @foreach($sections as $sectionId => $section)
                                    @php
                                        $fieldCount = 0;
                                        foreach (($section['groups'] ?? []) as $grp) {
                                            $fieldCount += count($grp['fields'] ?? []);
                                        }
                                    @endphp
                                    <li class="nav-item">
                                        <a href="/v2/settings?section={{ urlencode((string) $sectionId) }}"
                                           data-section="{{ (string) $sectionId }}"
                                           class="nav-link d-flex align-items-center {{ $activeSection === (string) $sectionId ? 'active' : '' }}">
                                            <i class="me-2 {{ (string) ($section['icon'] ?? 'fa-solid fa-circle') }}"></i>
                                            <span class="flex-grow-1">{{ (string) ($section['title'] ?? $sectionId) }}</span>
                                            @if($fieldCount > 0)
                                                <span class="badge bg-secondary ms-1" style="font-size:10px;">{{ $fieldCount }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-9 col-lg-8">
                @foreach($sections as $sectionId => $section)
                    @continue($activeSection !== (string) $sectionId)

                    <div class="box settings-section-card">
                        <div class="box-header with-border">
                            <h4 class="box-title mb-1">{{ (string) ($section['title'] ?? $sectionId) }}</h4>
                            @if(!empty($section['description']))
                                <p class="text-muted mb-0">{{ (string) $section['description'] }}</p>
                            @endif
                        </div>
                        <div class="box-body">
                            <form method="POST" action="/v2/settings" enctype="multipart/form-data"
                                  data-settings-form data-section="{{ (string) $sectionId }}"
                                  data-api-url="/v2/settings/{{ urlencode((string) $sectionId) }}">
                                @csrf
                                <input type="hidden" name="section" value="{{ (string) $sectionId }}">

                                @foreach(($section['groups'] ?? []) as $group)
                                    <div class="mb-4">
                                        <h5 class="fw-600 mb-2">{{ (string) ($group['title'] ?? '') }}</h5>
                                        @if(!empty($group['description']))
                                            <p class="text-muted small mb-3">{{ (string) $group['description'] }}</p>
                                        @endif

                                        <div class="row">
                                            @foreach(($group['fields'] ?? []) as $field)
                                                @php
                                                    $type = (string) ($field['type'] ?? 'text');
                                                    $key = (string) ($field['key'] ?? '');
                                                    $fieldId = $sectionId . '_' . (string) ($group['id'] ?? 'group') . '_' . $key;
                                                    $displayValue = $field['display_value'] ?? '';
                                                    $hasValue = !empty($field['has_value']);
                                                    $required = !empty($field['required']);
                                                    $fieldLabel = (string) ($field['label'] ?? $key);
                                                    $fieldHelp = (string) ($field['help'] ?? $field['description'] ?? '');
                                                    $stringValue = is_string($displayValue) ? trim($displayValue) : '';
                                                    $jsonCandidate = $stringValue !== '' ? $stringValue : (is_string($field['default'] ?? null) ? trim((string) $field['default']) : '');
                                                    $looksStructuredJson = $type === 'textarea'
                                                        && $jsonCandidate !== ''
                                                        && in_array(substr($jsonCandidate, 0, 1), ['{', '['], true)
                                                        && (str_contains(strtolower($fieldLabel . ' ' . $fieldHelp), 'json') || json_decode($jsonCandidate, true) !== null);
                                                    $columnClass = match ($type) {
                                                        'textarea', 'billing_rules', 'weekly_schedule' => 'col-12',
                                                        'color' => 'col-md-4 col-sm-6',
                                                        'file', 'checkbox', 'checkbox_group' => 'col-md-6 col-sm-12',
                                                        default => 'col-md-6 col-sm-12',
                                                    };
                                                @endphp

                                                <div class="{{ $columnClass }}">
                                                    <div class="mb-3">
                                                        @if($type !== 'checkbox')
                                                            <label for="{{ $fieldId }}" class="form-label fw-500">
                                                                {{ (string) ($field['label'] ?? $key) }}
                                                                @if($required)
                                                                    <span class="text-danger">*</span>
                                                                @endif
                                                            </label>
                                                        @endif

                                                        @if($type === 'weekly_schedule')
                                                            @php
                                                                $scheduleRaw = is_string($displayValue) && trim($displayValue) !== '' ? (string) $displayValue : (string) ($field['default'] ?? '{}');
                                                                $scheduleValue = json_decode($scheduleRaw, true);
                                                                if (!is_array($scheduleValue)) {
                                                                    $scheduleValue = [];
                                                                }
                                                                $weekdays = [
                                                                    'monday' => 'Lunes',
                                                                    'tuesday' => 'Martes',
                                                                    'wednesday' => 'Miércoles',
                                                                    'thursday' => 'Jueves',
                                                                    'friday' => 'Viernes',
                                                                    'saturday' => 'Sábado',
                                                                    'sunday' => 'Domingo',
                                                                ];
                                                            @endphp
                                                            <div class="settings-weekly-schedule" data-weekly-schedule data-target="{{ $fieldId }}">
                                                                @foreach($weekdays as $dayKey => $dayLabel)
                                                                    @php
                                                                        $day = is_array($scheduleValue[$dayKey] ?? null) ? $scheduleValue[$dayKey] : [];
                                                                        $enabled = array_key_exists('enabled', $day) ? (bool) $day['enabled'] : !in_array($dayKey, ['sunday'], true);
                                                                        $start = (string) ($day['start'] ?? '08:00');
                                                                        $end = (string) ($day['end'] ?? '18:00');
                                                                    @endphp
                                                                    <div class="settings-weekly-row {{ $enabled ? '' : 'is-disabled' }}" data-day="{{ $dayKey }}">
                                                                        <div class="settings-weekly-day">{{ $dayLabel }}</div>
                                                                        <div>
                                                                            <label class="form-label small text-muted mb-1" for="{{ $fieldId }}_{{ $dayKey }}_start">Inicio</label>
                                                                            <input type="time" class="form-control" id="{{ $fieldId }}_{{ $dayKey }}_start" data-schedule-start value="{{ $start }}" @disabled(!$enabled)>
                                                                        </div>
                                                                        <div>
                                                                            <label class="form-label small text-muted mb-1" for="{{ $fieldId }}_{{ $dayKey }}_end">Fin</label>
                                                                            <input type="time" class="form-control" id="{{ $fieldId }}_{{ $dayKey }}_end" data-schedule-end value="{{ $end }}" @disabled(!$enabled)>
                                                                        </div>
                                                                        <div class="form-check form-switch mb-0">
                                                                            <input class="form-check-input" type="checkbox" role="switch" id="{{ $fieldId }}_{{ $dayKey }}_enabled" data-schedule-enabled @checked($enabled)>
                                                                            <label class="form-check-label" for="{{ $fieldId }}_{{ $dayKey }}_enabled">Atiende este día</label>
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                                <textarea class="d-none" name="{{ $key }}" id="{{ $fieldId }}">{{ $scheduleRaw }}</textarea>
                                                            </div>
                                                        @elseif($type === 'textarea' && $looksStructuredJson)
                                                            <details class="settings-advanced-json">
                                                                <summary>Configuración avanzada para soporte técnico</summary>
                                                                <div class="settings-advanced-json__body">
                                                                    <textarea class="form-control font-monospace" rows="8" name="{{ $key }}" id="{{ $fieldId }}" @required($required)>{{ (string) $displayValue }}</textarea>
                                                                    <p class="form-text text-muted mb-0">Este campo conserva una estructura interna. Cambiarlo manualmente puede afectar el funcionamiento del módulo.</p>
                                                                </div>
                                                            </details>
                                                        @elseif($type === 'textarea')
                                                            <textarea class="form-control" rows="4" name="{{ $key }}" id="{{ $fieldId }}" @required($required)>{{ (string) $displayValue }}</textarea>
                                                        @elseif($type === 'select')
                                                            <select class="form-select" name="{{ $key }}" id="{{ $fieldId }}" @required($required)>
                                                                @foreach(($field['options'] ?? []) as $optionValue => $label)
                                                                    <option value="{{ (string) $optionValue }}" @selected((string) $displayValue === (string) $optionValue)>{{ (string) $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        @elseif($type === 'file')
                                                            @php $currentFile = is_string($displayValue) ? trim($displayValue) : ''; @endphp
                                                            <div class="settings-file-wrap">
                                                                @if($currentFile !== '')
                                                                    <div class="settings-file-preview border rounded p-2 mb-2 bg-light d-flex align-items-center gap-2">
                                                                        @if(preg_match('/\.(png|jpe?g|webp|gif|svg)$/i', $currentFile))
                                                                            <img src="{{ $currentFile }}" alt="{{ (string) ($field['label'] ?? $key) }}">
                                                                        @endif
                                                                        <div class="small text-muted">{{ $currentFile }}</div>
                                                                    </div>
                                                                @endif
                                                                <div class="settings-file-new-preview-wrap mb-2 d-none">
                                                                    <img src="" alt="Vista previa" class="settings-file-new-preview">
                                                                    <div class="small text-muted mt-1 settings-file-new-name"></div>
                                                                </div>
                                                                <input type="hidden" name="{{ $key }}" value="{{ $currentFile }}">
                                                                <input type="file" class="form-control settings-file-input" name="{{ $key }}_file" id="{{ $fieldId }}" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">
                                                            </div>
                                                        @elseif($type === 'checkbox')
                                                            @php $isChecked = in_array($displayValue, ['1', 1, true, 'true'], true); @endphp
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" role="switch" name="{{ $key }}" id="{{ $fieldId }}" value="1" @checked($isChecked)>
                                                                <label class="form-check-label fw-500" for="{{ $fieldId }}">
                                                                    {{ (string) ($field['label'] ?? $key) }}
                                                                </label>
                                                            </div>
                                                        @elseif($type === 'checkbox_group')
                                                            @php
                                                                $selectedValues = [];
                                                                if (is_string($displayValue) && trim($displayValue) !== '') {
                                                                    $decoded = json_decode($displayValue, true);
                                                                    if (is_array($decoded)) {
                                                                        $selectedValues = array_map('strval', $decoded);
                                                                    }
                                                                } elseif (is_array($displayValue)) {
                                                                    $selectedValues = array_map('strval', $displayValue);
                                                                }
                                                            @endphp
                                                            <label class="form-label fw-500 d-block">{{ (string) ($field['label'] ?? $key) }}</label>
                                                            @foreach(($field['options'] ?? []) as $optionValue => $optionLabel)
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="{{ $key }}[]" id="{{ $fieldId }}_{{ $optionValue }}" value="{{ (string) $optionValue }}" @checked(in_array((string) $optionValue, $selectedValues, true))>
                                                                    <label class="form-check-label" for="{{ $fieldId }}_{{ $optionValue }}">{{ (string) $optionLabel }}</label>
                                                                </div>
                                                            @endforeach
                                                        @elseif($type === 'billing_rules')
                                                            @php
                                                                $rulesValue = $displayValue === '' || $displayValue === null ? '[]' : $displayValue;
                                                                $ruleType = (string) ($field['rule_type'] ?? 'code');
                                                            @endphp
                                                            <div class="billing-rules" data-rule-type="{{ $ruleType }}" data-initial-rules='@json(json_decode((string) $rulesValue, true) ?? [])' data-target="{{ $fieldId }}">
                                                                <div class="table-responsive mb-3">
                                                                    <table class="table table-sm align-middle mb-0">
                                                                        <thead class="table-light">
                                                                        <tr>
                                                                            <th style="min-width: 120px;">Condición</th>
                                                                            <th style="min-width: 120px;">Acción</th>
                                                                            <th style="min-width: 120px;">Valor</th>
                                                                            <th>Notas</th>
                                                                            <th class="text-end" style="width: 60px;">&nbsp;</th>
                                                                        </tr>
                                                                        </thead>
                                                                        <tbody class="billing-rules-body"></tbody>
                                                                    </table>
                                                                </div>
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <p class="text-muted small mb-0">{{ (string) ($field['description'] ?? '') }}</p>
                                                                    <button type="button" class="btn btn-outline-primary btn-sm billing-rules-add">
                                                                        <i class="fa-solid fa-plus me-1"></i> Agregar regla
                                                                    </button>
                                                                </div>
                                                                <textarea class="d-none" name="{{ $key }}" id="{{ $fieldId }}">{{ is_string($rulesValue) ? $rulesValue : json_encode($rulesValue, JSON_UNESCAPED_UNICODE) }}</textarea>
                                                            </div>
                                                        @elseif($type === 'solicitudes_sla')
                                                            <div class="mt-1">
                                                                <p class="text-muted small mb-3">
                                                                    {{ (string) ($field['description'] ?? '') }}
                                                                </p>

                                                                <div class="box bg-light mb-3">
                                                                    <div class="box-body table-responsive">
                                                                        <table class="table table-striped align-middle sla-settings-table mb-0">
                                                                            <thead class="bg-primary-light">
                                                                            <tr>
                                                                                <th>Categoría</th>
                                                                                <th>Etiqueta</th>
                                                                                <th>Acción</th>
                                                                                <th>Fuente</th>
                                                                                <th>Horas base</th>
                                                                                <th>Advertencia</th>
                                                                                <th>Crítico</th>
                                                                                <th>Sin derivación</th>
                                                                            </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                            @foreach($categoryLabels as $slaCategoryKey => $slaCategoryLabel)
                                                                                @php $rule = is_array($baseRules[$slaCategoryKey] ?? null) ? $baseRules[$slaCategoryKey] : []; @endphp
                                                                                <tr>
                                                                                    <td><strong>{{ $slaCategoryLabel }}</strong></td>
                                                                                    <td><input type="text" class="form-control" name="base_rules[{{ $slaCategoryKey }}][label]" value="{{ (string) ($rule['label'] ?? '') }}"></td>
                                                                                    <td><textarea class="form-control" name="base_rules[{{ $slaCategoryKey }}][action]">{{ (string) ($rule['action'] ?? '') }}</textarea></td>
                                                                                    <td><input type="text" class="form-control" name="base_rules[{{ $slaCategoryKey }}][source]" value="{{ (string) ($rule['source'] ?? '') }}"></td>
                                                                                    <td><input type="number" min="1" class="form-control" name="base_rules[{{ $slaCategoryKey }}][hours]" value="{{ (string) ($rule['hours'] ?? '') }}" @disabled($slaCategoryKey === 'publico')></td>
                                                                                    <td><input type="number" min="1" class="form-control" name="base_rules[{{ $slaCategoryKey }}][warning_hours]" value="{{ (string) ($rule['warning_hours'] ?? '') }}"></td>
                                                                                    <td><input type="number" min="1" class="form-control" name="base_rules[{{ $slaCategoryKey }}][critical_hours]" value="{{ (string) ($rule['critical_hours'] ?? '') }}"></td>
                                                                                    <td>
                                                                                        @if($slaCategoryKey === 'publico')
                                                                                            <input type="number" min="1" class="form-control" name="base_rules[{{ $slaCategoryKey }}][missing_derivacion_hours]" value="{{ (string) ($rule['missing_derivacion_hours'] ?? '') }}">
                                                                                        @else
                                                                                            <span class="text-muted">No aplica</span>
                                                                                        @endif
                                                                                    </td>
                                                                                </tr>
                                                                            @endforeach
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>

                                                                <div class="box bg-light">
                                                                    <div class="box-body table-responsive">
                                                                        <table class="table table-striped align-middle sla-settings-table mb-0">
                                                                            <thead class="bg-primary-light">
                                                                            <tr>
                                                                                <th>Etapa</th>
                                                                                <th>Etiqueta</th>
                                                                                <th>Acción</th>
                                                                                <th>Fuente</th>
                                                                                <th>Horas base</th>
                                                                                <th>Advertencia</th>
                                                                                <th>Crítico</th>
                                                                            </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                            @foreach($stageLabels as $stageKey => $stageLabel)
                                                                                @continue(in_array($stageKey, ['recibida', 'en-atencion', 'completado'], true))
                                                                                @php
                                                                                    $rule = is_array($stageRules[$stageKey] ?? null) ? $stageRules[$stageKey] : [];
                                                                                    $overrides = is_array($rule['by_rule_key'] ?? null) ? $rule['by_rule_key'] : [];
                                                                                @endphp
                                                                                <tr>
                                                                                    <td><strong>{{ $stageLabel }}</strong></td>
                                                                                    <td><input type="text" class="form-control" name="stage_rules[{{ $stageKey }}][label]" value="{{ (string) ($rule['label'] ?? '') }}"></td>
                                                                                    <td><textarea class="form-control" name="stage_rules[{{ $stageKey }}][action]">{{ (string) ($rule['action'] ?? '') }}</textarea></td>
                                                                                    <td><input type="text" class="form-control" name="stage_rules[{{ $stageKey }}][source]" value="{{ (string) ($rule['source'] ?? '') }}"></td>
                                                                                    <td><input type="number" min="1" class="form-control" name="stage_rules[{{ $stageKey }}][hours]" value="{{ (string) ($rule['hours'] ?? '') }}"></td>
                                                                                    <td><input type="number" min="1" class="form-control" name="stage_rules[{{ $stageKey }}][warning_hours]" value="{{ (string) ($rule['warning_hours'] ?? '') }}"></td>
                                                                                    <td><input type="number" min="1" class="form-control" name="stage_rules[{{ $stageKey }}][critical_hours]" value="{{ (string) ($rule['critical_hours'] ?? '') }}"></td>
                                                                                </tr>

                                                                                @foreach($categoryLabels as $categoryKey => $categoryLabel)
                                                                                    @php $override = is_array($overrides[$categoryKey] ?? null) ? $overrides[$categoryKey] : []; @endphp
                                                                                    @continue($override === [])
                                                                                    <tr class="sla-settings-override">
                                                                                        <td colspan="2"><span class="text-muted">Override para <strong>{{ $categoryLabel }}</strong></span></td>
                                                                                        <td class="text-muted">Ajuste fino por categoría</td>
                                                                                        <td class="text-muted">Categoría</td>
                                                                                        <td><input type="number" min="1" class="form-control" name="stage_rules[{{ $stageKey }}][by_rule_key][{{ $categoryKey }}][hours]" value="{{ (string) ($override['hours'] ?? '') }}"></td>
                                                                                        <td><input type="number" min="1" class="form-control" name="stage_rules[{{ $stageKey }}][by_rule_key][{{ $categoryKey }}][warning_hours]" value="{{ (string) ($override['warning_hours'] ?? '') }}"></td>
                                                                                        <td><input type="number" min="1" class="form-control" name="stage_rules[{{ $stageKey }}][by_rule_key][{{ $categoryKey }}][critical_hours]" value="{{ (string) ($override['critical_hours'] ?? '') }}"></td>
                                                                                    </tr>
                                                                                @endforeach
                                                                            @endforeach
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @elseif($type === 'color')
                                                            <div class="d-flex align-items-center gap-2">
                                                                <input type="color" class="form-control form-control-color" name="{{ $key }}" id="{{ $fieldId }}" value="{{ (string) $displayValue ?: '#000000' }}" @required($required)>
                                                                <span class="settings-color-swatch" style="background:{{ (string) $displayValue ?: '#000000' }};"></span>
                                                                <span class="text-muted small settings-color-hex">{{ (string) $displayValue ?: '#000000' }}</span>
                                                            </div>
                                                        @elseif($type === 'password')
                                                            <div class="settings-password-wrap">
                                                                <input type="password" class="form-control" name="{{ $key }}" id="{{ $fieldId }}" value="" placeholder="{{ $hasValue ? '••••••••' : '' }}" @required($required)>
                                                                <button type="button" class="btn-pw-toggle" aria-label="Mostrar contraseña">
                                                                    <i class="mdi mdi-eye-outline"></i>
                                                                </button>
                                                            </div>
                                                        @else
                                                            <input type="{{ $type }}" class="form-control" name="{{ $key }}" id="{{ $fieldId }}" value="{{ (string) $displayValue }}" @required($required)>
                                                        @endif

                                                        @if(!empty($field['help']) && !$looksStructuredJson)
                                                            <p class="form-text text-muted mb-0">{{ (string) $field['help'] }}</p>
                                                        @elseif($type === 'password' && $hasValue)
                                                            <p class="form-text text-muted mb-0">Deja el campo vacío para mantener la contraseña actual.</p>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="text-muted small settings-dirty-msg d-none">
                                        <i class="mdi mdi-circle-small text-warning"></i> Tienes cambios sin guardar
                                    </span>
                                    <div class="ms-auto">
                                        <button type="submit" class="btn btn-primary settings-save-btn">
                                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            Guardar cambios
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        /**
         * Settings v2 - interactividad del módulo de configuración
         */
        (() => {
            'use strict';

            function initSectionNav() {
                document.querySelectorAll('.settings-sidenav .nav-link[data-section]').forEach(link => {
                    link.addEventListener('click', e => {
                        e.preventDefault();
                        const section = link.dataset.section;
                        if (!section) return;
                        const url = new URL(window.location.href);
                        url.searchParams.set('section', section);
                        history.replaceState(null, '', url.toString());
                        window.location.href = url.toString();
                    });
                });
            }

            function initFileUpload() {
                document.querySelectorAll('.settings-file-input').forEach(input => {
                    input.addEventListener('change', () => {
                        const wrap = input.closest('.settings-file-wrap');
                        if (!wrap) return;
                        const previewWrap = wrap.querySelector('.settings-file-new-preview-wrap');
                        const previewImg = wrap.querySelector('.settings-file-new-preview');
                        const previewName = wrap.querySelector('.settings-file-new-name');
                        const file = input.files && input.files[0];
                        if (!file) { previewWrap && previewWrap.classList.add('d-none'); return; }
                        const allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'];
                        if (!allowed.includes(file.type)) {
                            showToast('Formato no permitido. Use PNG, JPG, WEBP, GIF o SVG.', 'error');
                            input.value = ''; return;
                        }
                        if (file.size > 3 * 1024 * 1024) {
                            showToast('El archivo no puede superar 3MB.', 'error');
                            input.value = ''; return;
                        }
                        if (previewImg && previewWrap && previewName) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                previewImg.src = e.target.result;
                                previewName.textContent = file.name;
                                previewWrap.classList.remove('d-none');
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                });
            }

            function initPasswordToggle() {
                document.querySelectorAll('.settings-password-wrap').forEach(wrap => {
                    const input = wrap.querySelector('input[type="password"]');
                    const btn = wrap.querySelector('.btn-pw-toggle');
                    const icon = btn && btn.querySelector('i');
                    if (!input || !btn) return;
                    btn.addEventListener('click', function() {
                        const isPassword = input.type === 'password';
                        input.type = isPassword ? 'text' : 'password';
                        if (icon) icon.className = isPassword ? 'mdi mdi-eye-off-outline' : 'mdi mdi-eye-outline';
                        btn.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
                    });
                });
            }

            function initColorPreview() {
                document.querySelectorAll('input[type="color"]').forEach(input => {
                    const parent = input.closest('.d-flex');
                    if (!parent) return;
                    const swatch = parent.querySelector('.settings-color-swatch');
                    const hex = parent.querySelector('.settings-color-hex');
                    function update() {
                        const value = input.value;
                        if (swatch) swatch.style.background = value;
                        if (hex) hex.textContent = value;
                    }
                    input.addEventListener('input', update);
                    input.addEventListener('change', update);
                });
            }

            function initWeeklySchedules() {
                document.querySelectorAll('[data-weekly-schedule]').forEach(container => {
                    const target = document.getElementById(container.dataset.target || '');
                    if (!target) return;

                    function sync() {
                        const schedule = {};
                        container.querySelectorAll('.settings-weekly-row[data-day]').forEach(row => {
                            const enabledInput = row.querySelector('[data-schedule-enabled]');
                            const startInput = row.querySelector('[data-schedule-start]');
                            const endInput = row.querySelector('[data-schedule-end]');
                            const enabled = !!enabledInput?.checked;
                            row.classList.toggle('is-disabled', !enabled);
                            if (startInput) startInput.disabled = !enabled;
                            if (endInput) endInput.disabled = !enabled;
                            schedule[row.dataset.day] = {
                                enabled,
                                start: startInput?.value || '08:00',
                                end: endInput?.value || '18:00',
                            };
                        });
                        target.value = JSON.stringify(schedule);
                        target.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    container.querySelectorAll('input').forEach(input => {
                        input.addEventListener('change', sync);
                        input.addEventListener('input', sync);
                    });
                    sync();
                });
            }

            function initDirtyTracking() {
                document.querySelectorAll('[data-settings-form]').forEach(form => {
                    const msg = form.querySelector('.settings-dirty-msg');
                    if (!msg) return;
                    let dirty = false;
                    form.querySelectorAll('input, textarea, select').forEach(el => {
                        el.addEventListener('change', function() { if (!dirty) { dirty = true; msg.classList.remove('d-none'); } });
                        el.addEventListener('input', function() { if (!dirty) { dirty = true; msg.classList.remove('d-none'); } });
                    });
                    form.addEventListener('submit', function() { dirty = false; msg.classList.add('d-none'); });
                });
            }

            function initAjaxSave() {
                document.querySelectorAll('[data-settings-form]').forEach(function(form) {
                    const apiUrl = form.dataset.apiUrl;
                    if (!apiUrl) return;
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const btn = form.querySelector('.settings-save-btn');
                        if (btn) { btn.classList.add('loading'); btn.disabled = true; }
                        const formData = new FormData(form);
                        const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                        fetch(apiUrl, {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken },
                            body: formData,
                        })
                        .then(function(response) {
                            return response.json().then(function(data) { return { ok: response.ok, data: data }; });
                        })
                        .then(function(result) {
                            if (result.ok && result.data.success) {
                                showToast(result.data.message || 'Configuración guardada.', 'success');
                                const msg = form.querySelector('.settings-dirty-msg');
                                if (msg) msg.classList.add('d-none');
                            } else {
                                showToast(result.data.error || 'Error al guardar la configuración.', 'error');
                            }
                        })
                        .catch(function() { showToast('Error de red. Intenta nuevamente.', 'error'); })
                        .finally(function() {
                            if (btn) { btn.classList.remove('loading'); btn.disabled = false; }
                        });
                    });
                });
            }

            function showToast(message, type) {
                type = type || 'success';
                if (typeof window.Swal !== 'undefined') {
                    window.Swal.fire({ toast: true, position: 'top-end', icon: type, title: message, showConfirmButton: false, timer: type === 'success' ? 3000 : 5000, timerProgressBar: true });
                    return;
                }
                const div = document.createElement('div');
                div.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' position-fixed top-0 end-0 m-3';
                div.style.zIndex = '9999';
                div.textContent = message;
                document.body.appendChild(div);
                setTimeout(function() { div.remove(); }, 4000);
            }

            document.addEventListener('DOMContentLoaded', function() {
                initSectionNav();
                initFileUpload();
                initPasswordToggle();
                initColorPreview();
                initWeeklySchedules();
                initDirtyTracking();
                initAjaxSave();
            });
        })();
    </script>
    <script>
        (() => {
            const ACTION_LABELS = { tarifa: 'Tarifa fija', descuento: 'Descuento (%)', exclusion: 'Exclusión' };
            const AFFILIATION_OPTIONS = ['IESS', 'ISSFA', 'ISSPOL', 'MSP', 'PARTICULAR'];

            function buildConditionInput(type, value) {
                const wrapper = document.createElement('div');
                wrapper.className = 'd-flex flex-column gap-1';
                if (type === 'age') {
                    const minInput = document.createElement('input');
                    minInput.type = 'number';
                    minInput.className = 'form-control form-control-sm billing-rule-min';
                    minInput.placeholder = 'Edad mínima';
                    minInput.value = value?.min_age ?? '';
                    const maxInput = document.createElement('input');
                    maxInput.type = 'number';
                    maxInput.className = 'form-control form-control-sm billing-rule-max';
                    maxInput.placeholder = 'Edad máxima';
                    maxInput.value = value?.max_age ?? '';
                    wrapper.appendChild(minInput);
                    wrapper.appendChild(maxInput);
                    return wrapper;
                }
                if (type === 'affiliation') {
                    const select = document.createElement('select');
                    select.className = 'form-select form-select-sm billing-rule-condition';
                    const emptyOption = document.createElement('option');
                    emptyOption.value = '';
                    emptyOption.textContent = 'Selecciona afiliación';
                    select.appendChild(emptyOption);
                    AFFILIATION_OPTIONS.forEach(function (opt) {
                        const option = document.createElement('option');
                        option.value = opt;
                        option.textContent = opt;
                        if (String(value ?? '').toUpperCase() === opt) option.selected = true;
                        select.appendChild(option);
                    });
                    return select;
                }
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm billing-rule-condition';
                input.placeholder = type === 'code' ? 'Ej: 12345' : 'Afiliación';
                input.value = value ?? '';
                return input;
            }

            function buildRow(ruleType, rule, onChange) {
                const tr = document.createElement('tr');
                tr.dataset.ruleId = rule.id || ('rule_' + Date.now() + '_' + Math.random().toString(16).slice(2));
                const conditionTd = document.createElement('td');
                conditionTd.appendChild(buildConditionInput(ruleType, ruleType === 'age' ? { min_age: rule.min_age ?? '', max_age: rule.max_age ?? '' } : (ruleType === 'affiliation' ? rule.affiliation ?? '' : rule.code ?? '')));
                tr.appendChild(conditionTd);
                const actionTd = document.createElement('td');
                const select = document.createElement('select');
                select.className = 'form-select form-select-sm billing-rule-action';
                ['tarifa', 'descuento', 'exclusion'].forEach(function (action) {
                    const option = document.createElement('option');
                    option.value = action;
                    option.textContent = ACTION_LABELS[action];
                    if (rule.action === action) option.selected = true;
                    select.appendChild(option);
                });
                actionTd.appendChild(select);
                tr.appendChild(actionTd);
                const valueTd = document.createElement('td');
                const valueInput = document.createElement('input');
                valueInput.type = 'number';
                valueInput.step = '0.01';
                valueInput.min = '0';
                valueInput.className = 'form-control form-control-sm billing-rule-value';
                valueInput.value = rule.value ?? '';
                valueTd.appendChild(valueInput);
                tr.appendChild(valueTd);
                const notesTd = document.createElement('td');
                const notes = document.createElement('textarea');
                notes.className = 'form-control form-control-sm billing-rule-notes';
                notes.rows = 1;
                notes.value = rule.notes ?? '';
                notesTd.appendChild(notes);
                tr.appendChild(notesTd);
                const deleteTd = document.createElement('td');
                deleteTd.className = 'text-end';
                const deleteBtn = document.createElement('button');
                deleteBtn.type = 'button';
                deleteBtn.className = 'btn btn-sm btn-outline-danger';
                deleteBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
                deleteTd.appendChild(deleteBtn);
                tr.appendChild(deleteTd);

                function toggleValueVisibility() {
                    const shouldHide = select.value === 'exclusion';
                    valueInput.disabled = shouldHide;
                    valueInput.classList.toggle('d-none', shouldHide);
                }

                [select, valueInput, notes].forEach(el => {
                    el.addEventListener('change', onChange);
                    el.addEventListener('input', onChange);
                });
                tr.querySelectorAll('.billing-rule-condition, .billing-rule-min, .billing-rule-max').forEach(el => {
                    el.addEventListener('change', onChange);
                    el.addEventListener('input', onChange);
                });
                deleteBtn.addEventListener('click', function () {
                    tr.remove();
                    onChange();
                });
                select.addEventListener('change', toggleValueVisibility);
                toggleValueVisibility();
                return tr;
            }

            function collectRules(container) {
                const ruleType = container.dataset.ruleType;
                return Array.from(container.querySelectorAll('tbody tr')).map(function (row) {
                    const action = row.querySelector('.billing-rule-action')?.value || 'tarifa';
                    const value = row.querySelector('.billing-rule-value')?.value;
                    const base = { id: row.dataset.ruleId, action, notes: row.querySelector('.billing-rule-notes')?.value || '' };
                    if (action !== 'exclusion' && value !== undefined && value !== '') base.value = parseFloat(value);
                    if (ruleType === 'code') base.code = row.querySelector('.billing-rule-condition')?.value || '';
                    else if (ruleType === 'affiliation') base.affiliation = row.querySelector('.billing-rule-condition')?.value || '';
                    else {
                        const min = row.querySelector('.billing-rule-min')?.value;
                        const max = row.querySelector('.billing-rule-max')?.value;
                        base.min_age = min !== '' ? parseInt(min, 10) : null;
                        base.max_age = max !== '' ? parseInt(max, 10) : null;
                    }
                    return base;
                });
            }

            function syncRules(container) {
                const textarea = document.getElementById(container.dataset.target);
                if (textarea) textarea.value = JSON.stringify(collectRules(container));
            }

            document.querySelectorAll('.billing-rules').forEach(function (container) {
                const tbody = container.querySelector('.billing-rules-body');
                const ruleType = container.dataset.ruleType || 'code';
                const initial = JSON.parse(container.dataset.initialRules || '[]');
                const onChange = () => syncRules(container);
                (Array.isArray(initial) ? initial : []).forEach(rule => tbody.appendChild(buildRow(ruleType, rule, onChange)));
                container.querySelector('.billing-rules-add')?.addEventListener('click', function () {
                    const defaults = { action: 'tarifa', value: '', notes: '' };
                    if (ruleType === 'code') defaults.code = '';
                    else if (ruleType === 'affiliation') defaults.affiliation = '';
                    else { defaults.min_age = ''; defaults.max_age = ''; }
                    tbody.appendChild(buildRow(ruleType, defaults, onChange));
                    syncRules(container);
                });
                syncRules(container);
            });
        })();
    </script>
@endpush
