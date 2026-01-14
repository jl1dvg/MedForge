<?php
/** @var array $sections */
/** @var string $activeSection */
/** @var string|null $status */
/** @var string|null $error */

$scripts = $scripts ?? [];
$inlineScripts = $inlineScripts ?? [];
$inlineScripts[] = <<<'JS'
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const navLinks = document.querySelectorAll('[data-toggle="settings-section"]');
        const sections = document.querySelectorAll('.settings-section');

        function activate(sectionId) {
            sections.forEach(function (section) {
                section.classList.toggle('d-none', section.dataset.section !== sectionId);
            });
            navLinks.forEach(function (link) {
                const isActive = link.dataset.section === sectionId;
                link.classList.toggle('active', isActive);
            });
        }

        navLinks.forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                const target = this.dataset.section;
                if (!target) {
                    return;
                }
                const url = new URL(window.location.href);
                url.searchParams.set('section', target);
                window.history.replaceState({}, '', url);
                activate(target);
            });
        });
    });
})();
JS;
$inlineScripts[] = <<<'JS'
(function () {
    const ACTION_LABELS = {
        tarifa: 'Tarifa fija',
        descuento: 'Descuento (%)',
        exclusion: 'Exclusión',
    };

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
                if (String(value ?? '').toUpperCase() === opt) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            const customOption = document.createElement('option');
            customOption.value = value && !AFFILIATION_OPTIONS.includes(String(value).toUpperCase()) ? value : '';
            customOption.textContent = customOption.value ? customOption.value : 'Otro';
            if (customOption.value) {
                customOption.selected = true;
            }
            select.appendChild(customOption);
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
        const randomId = typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : 'rule_' + Date.now() + '_' + Math.random().toString(16).slice(2);
        tr.dataset.ruleId = rule.id || randomId;

        const conditionTd = document.createElement('td');
        conditionTd.appendChild(buildConditionInput(ruleType, ruleType === 'age' ? {
            min_age: rule.min_age ?? '',
            max_age: rule.max_age ?? '',
        } : (ruleType === 'affiliation' ? rule.affiliation ?? '' : rule.code ?? '')));
        tr.appendChild(conditionTd);

        const actionTd = document.createElement('td');
        const select = document.createElement('select');
        select.className = 'form-select form-select-sm billing-rule-action';
        ['tarifa', 'descuento', 'exclusion'].forEach(function (action) {
            const option = document.createElement('option');
            option.value = action;
            option.textContent = ACTION_LABELS[action];
            if (rule.action === action) {
                option.selected = true;
            }
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

        [select, valueInput, notes].forEach(function (element) {
            element.addEventListener('change', onChange);
            element.addEventListener('input', onChange);
        });

        const conditionInputs = tr.querySelectorAll('.billing-rule-condition, .billing-rule-min, .billing-rule-max');
        conditionInputs.forEach(function (input) {
            input.addEventListener('change', onChange);
            input.addEventListener('input', onChange);
        });

        deleteBtn.addEventListener('click', function () {
            tr.remove();
            onChange();
        });

        toggleValueVisibility();

        select.addEventListener('change', toggleValueVisibility);

        return tr;
    }

    function parseRules(container) {
        try {
            const raw = container.dataset.initialRules || '[]';
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            console.warn('No se pudieron parsear las reglas de facturación', error);
            return [];
        }
    }

    function collectRules(container) {
        const ruleType = container.dataset.ruleType;
        const rows = container.querySelectorAll('tbody tr');
        const rules = [];

        rows.forEach(function (row) {
            const action = row.querySelector('.billing-rule-action')?.value || 'tarifa';
            const value = row.querySelector('.billing-rule-value')?.value;
            const notes = row.querySelector('.billing-rule-notes')?.value || '';
            const base = {
                id: row.dataset.ruleId,
                action: action,
                notes: notes,
            };

            if (action !== 'exclusion' && value !== undefined && value !== '') {
                base.value = parseFloat(value);
            }

            if (ruleType === 'code') {
                base.code = row.querySelector('.billing-rule-condition')?.value || '';
            } else if (ruleType === 'affiliation') {
                base.affiliation = row.querySelector('.billing-rule-condition')?.value || '';
            } else if (ruleType === 'age') {
                const min = row.querySelector('.billing-rule-min')?.value;
                const max = row.querySelector('.billing-rule-max')?.value;
                base.min_age = min !== '' ? parseInt(min, 10) : null;
                base.max_age = max !== '' ? parseInt(max, 10) : null;
            }

            rules.push(base);
        });

        return rules;
    }

    function syncRules(container) {
        const textarea = document.getElementById(container.dataset.target);
        if (!textarea) {
            return;
        }

        const rules = collectRules(container);
        textarea.value = JSON.stringify(rules);
    }

    function initBillingRules() {
        document.querySelectorAll('.billing-rules').forEach(function (container) {
            const tbody = container.querySelector('.billing-rules-body');
            const ruleType = container.dataset.ruleType || 'code';
            const onChange = function () {
                syncRules(container);
            };

            parseRules(container).forEach(function (rule) {
                const row = buildRow(ruleType, rule, onChange);
                tbody.appendChild(row);
            });

            const addButton = container.querySelector('.billing-rules-add');
            if (addButton) {
                addButton.addEventListener('click', function () {
                    const defaults = { action: 'tarifa', value: '', notes: '' };
                    if (ruleType === 'code') {
                        defaults.code = '';
                    } else if (ruleType === 'affiliation') {
                        defaults.affiliation = '';
                    } else {
                        defaults.min_age = '';
                        defaults.max_age = '';
                    }

                    const row = buildRow(ruleType, defaults, onChange);
                    tbody.appendChild(row);
                    syncRules(container);
                });
            }

            syncRules(container);
        });
    }

    document.addEventListener('DOMContentLoaded', initBillingRules);
})();
JS;
?>

<section class="content">
    <div class="row">
        <div class="col-xl-3 col-lg-4">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Ajustes</h4>
                </div>
                <div class="box-body p-0">
                    <ul class="nav nav-pills flex-column">
                        <?php foreach ($sections as $sectionId => $section): ?>
                            <?php $isActive = $activeSection === $sectionId; ?>
                            <li class="nav-item">
                                <a href="/settings?section=<?= urlencode($sectionId); ?>"
                                   class="nav-link d-flex align-items-center <?= $isActive ? 'active' : ''; ?>"
                                   data-toggle="settings-section"
                                   data-section="<?= htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="me-2 <?= htmlspecialchars($section['icon'] ?? 'fa-solid fa-circle', ENT_QUOTES, 'UTF-8'); ?>"></i>
                                    <span><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-xl-9 col-lg-8">
            <?php if ($status === 'updated'): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    <strong>¡Ajustes guardados!</strong> Los cambios se han aplicado correctamente.
                </div>
            <?php elseif ($status === 'unchanged'): ?>
                <div class="alert alert-info alert-dismissible">
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    No se detectaron cambios para guardar.
                </div>
            <?php elseif ($status === 'error' && $error): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    <strong>Error:</strong> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php elseif ($error): ?>
                <div class="alert alert-warning alert-dismissible">
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php foreach ($sections as $sectionId => $section): ?>
                <?php $isActive = $activeSection === $sectionId; ?>
                <div class="box settings-section <?= $isActive ? '' : 'd-none'; ?>"
                     data-section="<?= htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="box-header with-border">
                        <h4 class="box-title mb-5">
                            <?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </h4>
                        <?php if (!empty($section['description'])): ?>
                            <p class="text-muted mb-0">
                                <?= htmlspecialchars($section['description'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="box-body">
                        <form method="post" class="settings-form">
                            <input type="hidden" name="section"
                                   value="<?= htmlspecialchars($sectionId, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php foreach ($section['groups'] as $group): ?>
                                <div class="mb-4">
                                    <h5 class="fw-600 mb-10">
                                        <?= htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    </h5>
                                    <?php if (!empty($group['description'])): ?>
                                        <p class="text-muted small mb-3">
                                            <?= htmlspecialchars($group['description'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="row">
                                        <?php foreach ($group['fields'] as $field): ?>
                                            <?php
                                            $fieldId = $sectionId . '_' . $group['id'] . '_' . $field['key'];
                                            $type = $field['type'];
                                            $required = !empty($field['required']);
                                            $displayValue = $field['display_value'] ?? '';
                                            $hasValue = !empty($field['has_value']);

                        switch ($type) {
                            case 'textarea':
                                $columnClass = 'col-12';
                                break;
                            case 'color':
                                $columnClass = 'col-md-4 col-sm-6';
                                break;
                            case 'billing_rules':
                                $columnClass = 'col-12';
                                break;
                            case 'checkbox':
                                $columnClass = 'col-md-6 col-sm-12';
                                break;
                            case 'checkbox_group':
                                $columnClass = 'col-md-6 col-sm-12';
                                break;
                            default:
                                $columnClass = 'col-md-6 col-sm-12';
                                break;
                        }
                                            ?>
                                            <div class="<?= $columnClass; ?>">
                                                <div class="mb-3">
                                                    <?php if ($type !== 'checkbox'): ?>
                                                        <label for="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'); ?>"
                                                               class="form-label fw-500">
                                                            <?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                            <?php if ($required): ?>
                                                                <span class="text-danger">*</span>
                                                            <?php endif; ?>
                                                        </label>
                                                    <?php endif; ?>
                                                    <?php if ($type === 'textarea'): ?>
                                                        <textarea
                                                            class="form-control"
                                                            rows="4"
                                                            name="<?= htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'); ?>"
                                                            <?= $required ? 'required' : ''; ?>><?= htmlspecialchars($displayValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                    <?php elseif ($type === 'select'): ?>
                                                        <select
                                                            class="form-select"
                                                            name="<?= htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'); ?>"
                                                            <?= $required ? 'required' : ''; ?>>
                                                            <?php foreach ($field['options'] ?? [] as $optionValue => $label): ?>
                                                                <option value="<?= htmlspecialchars((string) $optionValue, ENT_QUOTES, 'UTF-8'); ?>"
                                                                    <?= ((string) $displayValue === (string) $optionValue) ? 'selected' : ''; ?>>
                                                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php elseif ($type === 'checkbox'): ?>
                                                        <?php $isChecked = in_array($displayValue, ['1', 1, true, 'true'], true); ?>
                                                        <div class="form-check form-switch">
                                                            <input
                                                                class="form-check-input"
                                                                type="checkbox"
                                                                role="switch"
                                                                name="<?= htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'); ?>"
                                                                value="1"
                                                                <?= $isChecked ? 'checked' : ''; ?>>
                                                            <label class="form-check-label fw-500" for="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                                <?php if ($required): ?>
                                                                    <span class="text-danger">*</span>
                                                                <?php endif; ?>
                                                            </label>
                                                        </div>
                                                    <?php elseif ($type === 'checkbox_group'): ?>
                                                        <?php
                                                        $selectedValues = [];
                                                        if (is_string($displayValue) && trim($displayValue) !== '') {
                                                            $decoded = json_decode($displayValue, true);
                                                            if (is_array($decoded)) {
                                                                $selectedValues = $decoded;
                                                            }
                                                        } elseif (is_array($displayValue)) {
                                                            $selectedValues = $displayValue;
                                                        }
                                                        ?>
                                                        <label class="form-label fw-500 d-block">
                                                            <?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </label>
                                                        <?php foreach (($field['options'] ?? []) as $optionValue => $optionLabel): ?>
                                                            <?php $isChecked = in_array((string) $optionValue, array_map('strval', $selectedValues), true); ?>
                                                            <div class="form-check">
                                                                <input
                                                                    class="form-check-input"
                                                                    type="checkbox"
                                                                    name="<?= htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>[]"
                                                                    id="<?= htmlspecialchars($fieldId . '_' . $optionValue, ENT_QUOTES, 'UTF-8'); ?>"
                                                                    value="<?= htmlspecialchars((string) $optionValue, ENT_QUOTES, 'UTF-8'); ?>"
                                                                    <?= $isChecked ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="<?= htmlspecialchars($fieldId . '_' . $optionValue, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <?= htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php elseif ($type === 'billing_rules'): ?>
                                                        <?php
                                                        $rulesValue = $displayValue;
                                                        if ($rulesValue === '' || $rulesValue === null) {
                                                            $rulesValue = '[]';
                                                        }
                                                        $ruleType = $field['rule_type'] ?? 'code';
                                                        ?>
                                                        <div class="billing-rules" data-rule-type="<?= htmlspecialchars($ruleType, ENT_QUOTES, 'UTF-8'); ?>"
                                                             data-initial-rules='<?= htmlspecialchars($rulesValue, ENT_QUOTES, 'UTF-8'); ?>'
                                                             data-target="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'); ?>">
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
                                                                <p class="text-muted small mb-0">
                                                                    <?= htmlspecialchars($field['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                                </p>
                                                                <button type="button" class="btn btn-outline-primary btn-sm billing-rules-add">
                                                                    <i class="fa-solid fa-plus me-1"></i> Agregar regla
                                                                </button>
                                                            </div>
                                                            <textarea class="d-none" name="<?= htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                      id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($rulesValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                        </div>
                                                    <?php else: ?>
                                                        <?php
                                                        $valueAttribute = $type === 'password' ? '' : $displayValue;
                                                        $placeholder = '';
                                                        if ($type === 'password' && $hasValue) {
                                                            $placeholder = '••••••••';
                                                        }
                                                        ?>
                                                        <input
                                                            type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
                                                            class="form-control"
                                                            name="<?= htmlspecialchars($field['key'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'); ?>"
                                                            value="<?= htmlspecialchars($valueAttribute, ENT_QUOTES, 'UTF-8'); ?>"
                                                            <?= $placeholder !== '' ? 'placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>
                                                            <?= $required ? 'required' : ''; ?>>
                                                    <?php endif; ?>
                                                    <?php if (!empty($field['help'])): ?>
                                                        <p class="form-text text-muted mb-0">
                                                            <?= htmlspecialchars($field['help'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </p>
                                                    <?php elseif ($type === 'password' && $hasValue): ?>
                                                        <p class="form-text text-muted mb-0">
                                                            Deja el campo vacío para mantener la contraseña actual.
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    Guardar cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
