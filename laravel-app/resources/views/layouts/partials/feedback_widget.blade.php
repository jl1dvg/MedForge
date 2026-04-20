@php
    if (!function_exists('medforgeFeedbackCollectModules')) {
        function medforgeFeedbackCollectModules(array $items, array &$collector): void
        {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $type = (string) ($item['type'] ?? 'item');

                if ($type === 'group') {
                    medforgeFeedbackCollectModules((array) ($item['children'] ?? []), $collector);
                    continue;
                }

                if ($type !== 'item') {
                    continue;
                }

                $label = trim((string) ($item['label'] ?? ''));
                if ($label === '') {
                    continue;
                }

                $href = trim((string) ($item['href'] ?? ''));
                $path = $href !== '' ? (parse_url($href, PHP_URL_PATH) ?: '') : '';
                $key = trim($path, '/');

                if ($key === '') {
                    $key = \Illuminate\Support\Str::slug($label) ?: 'general';
                }

                if (!isset($collector[$key])) {
                    $collector[$key] = [
                        'key' => $key,
                        'label' => $label,
                        'path' => $path !== '' ? '/' . ltrim($path, '/') : null,
                    ];
                }
            }
        }
    }

    $feedbackModules = [
        'general' => [
            'key' => 'general',
            'label' => 'General / Plataforma',
            'path' => null,
        ],
    ];

    medforgeFeedbackCollectModules((array) ($appNavigation['sidebar'] ?? []), $feedbackModules);

    if (count($feedbackModules) === 1) {
        $feedbackModules['dashboard'] = [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'path' => '/v2/dashboard',
        ];
    }

    $feedbackCurrentPath = '/' . ltrim(request()->path(), '/');
    $feedbackSelectedKey = 'general';

    foreach ($feedbackModules as $feedbackModule) {
        $modulePath = $feedbackModule['path'] ?? null;

        if (!$modulePath || $modulePath === '/') {
            continue;
        }

        if ($feedbackCurrentPath === $modulePath || str_starts_with($feedbackCurrentPath . '/', rtrim($modulePath, '/') . '/')) {
            $feedbackSelectedKey = $feedbackModule['key'];
            break;
        }
    }

    $feedbackPayload = [
        'currentPath' => $feedbackCurrentPath,
        'pageTitle' => (string) ($pageTitle ?? ''),
        'selectedModuleKey' => $feedbackSelectedKey,
        'modules' => array_values($feedbackModules),
    ];
@endphp

<div
    class="feedback-widget"
    data-feedback-widget
    data-feedback-config="{{ e(json_encode($feedbackPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}"
>
    <button
        type="button"
        class="btn btn-primary feedback-widget__trigger"
        data-bs-toggle="modal"
        data-bs-target="#feedbackWidgetModal"
        aria-label="Abrir sugerencias y reportes"
    >
        <i class="mdi mdi-message-alert-outline"></i>
        <span>Sugerencias</span>
    </button>

    <div class="modal fade" id="feedbackWidgetModal" tabindex="-1" aria-hidden="true" aria-labelledby="feedbackWidgetModalLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content feedback-widget__modal">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="feedbackWidgetModalLabel">Sugerencias y reportes</h5>
                        <p class="mb-0 text-muted small">Cuéntanos qué mejorar o qué error encontraste.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form data-feedback-form>
                    <div class="modal-body">
                        <div class="alert d-none" data-feedback-alert role="alert"></div>

                        <div class="mb-3">
                            <label class="form-label" for="feedback-module">Módulo</label>
                            <select class="form-select" id="feedback-module" name="module_key" required data-feedback-module>
                                @foreach (array_values($feedbackModules) as $feedbackModule)
                                    <option
                                        value="{{ $feedbackModule['key'] }}"
                                        @selected($feedbackModule['key'] === $feedbackSelectedKey)
                                    >
                                        {{ $feedbackModule['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="feedback-type">Tipo</label>
                            <select class="form-select" id="feedback-type" name="report_type" required>
                                <option value="suggestion">Sugerencia de mejora</option>
                                <option value="bug">Reporte de error</option>
                            </select>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="feedback-message">Detalle</label>
                            <textarea
                                class="form-control"
                                id="feedback-message"
                                name="message"
                                rows="5"
                                maxlength="5000"
                                placeholder="Describe qué estabas haciendo, qué esperabas que ocurra y qué pasó realmente."
                                required
                                data-feedback-message
                            ></textarea>
                        </div>

                        <div class="mt-3 mb-0">
                            <label class="form-label" for="feedback-attachment">Adjunto opcional</label>
                            <input
                                class="form-control"
                                type="file"
                                id="feedback-attachment"
                                name="attachment"
                                accept=".png,.jpg,.jpeg,.webp,.pdf,.doc,.docx,.txt"
                                data-feedback-attachment
                            >
                            <div class="form-text">
                                Puedes subir una captura de pantalla o un archivo de apoyo. Máximo 10 MB.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" data-feedback-submit>Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
