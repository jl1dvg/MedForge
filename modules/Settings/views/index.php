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
                            case 'checkbox':
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
