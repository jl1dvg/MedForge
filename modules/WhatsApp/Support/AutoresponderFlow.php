<?php

namespace Modules\WhatsApp\Support;

use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function is_array;
use function is_string;
use function mb_strlen;
use function preg_replace;
use function trim;
use function uniqid;

class AutoresponderFlow
{
    private const BUTTON_LIMIT = 3;

    /**
     * @return array<int, string>
     */
    public static function menuKeywords(): array
    {
        return ['menu', 'inicio', 'hola', 'buen dia', 'buenos dias', 'buenas tardes', 'buenas noches', 'start'];
    }

    /**
     * @return array<int, string>
     */
    public static function informationKeywords(): array
    {
        return ['1', 'opcion 1', 'informacion', 'informacion general', 'obtener informacion', 'informacion cive'];
    }

    /**
     * @return array<int, string>
     */
    public static function scheduleKeywords(): array
    {
        return [
            '2',
            'opcion 2',
            'horarios',
            'horario',
            'horario atencion',
            'horarios atencion',
            'horarios de atencion',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function locationKeywords(): array
    {
        return ['3', 'opcion 3', 'ubicacion', 'ubicaciones', 'sedes', 'direccion', 'direcciones'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfig(string $brand): array
    {
        $brand = trim($brand) !== '' ? $brand : 'MedForge';

        return [
            'entry' => [
                'title' => 'Mensaje de bienvenida',
                'description' => 'Primer contacto que recibe toda persona que escribe al canal.',
                'keywords' => self::menuKeywords(),
                'messages' => self::wrapMessages([
                    "¡Hola! Soy Dr. Ojito, el asistente virtual de {$brand} 👁️",
                    "Te puedo ayudar con las siguientes solicitudes:\n1. Obtener información\n2. Horarios de atención\n3. Ubicaciones\nResponde con el número o escribe la opción que necesites.",
                ]),
            ],
            'options' => [
                [
                    'id' => 'information',
                    'title' => 'Opción 1 · Obtener información',
                    'description' => 'Respuestas que se disparan con las palabras clave listadas.',
                    'keywords' => self::informationKeywords(),
                    'messages' => self::wrapMessages([
                        'Obtener Información',
                        "Selecciona la información que deseas conocer:\n• Procedimientos oftalmológicos disponibles.\n• Servicios complementarios como óptica y exámenes especializados.\n• Seguros y convenios con los que trabajamos.\n\nEscribe 'horarios' para conocer los horarios de atención o 'menu' para volver al inicio.",
                    ]),
                    'followup' => "Sugiere escribir 'horarios' para continuar o 'menu' para volver al inicio.",
                ],
                [
                    'id' => 'schedule',
                    'title' => 'Opción 2 · Horarios de atención',
                    'description' => 'Horarios disponibles para cada sede.',
                    'keywords' => self::scheduleKeywords(),
                    'messages' => self::wrapMessages([
                        "Horarios de atención 🕖\nVilla Club: Lunes a Viernes 09h00 - 18h00, Sábados 09h00 - 13h00.\nCeibos: Lunes a Viernes 09h00 - 18h00, Sábados 09h00 - 13h00.\n\nSi necesitas otra información responde 'menu'.",
                    ]),
                    'followup' => "Indica que el usuario puede responder 'menu' para otras opciones.",
                ],
                [
                    'id' => 'locations',
                    'title' => 'Opción 3 · Ubicaciones',
                    'description' => 'Direcciones de las sedes disponibles.',
                    'keywords' => self::locationKeywords(),
                    'messages' => self::wrapMessages([
                        "Nuestras sedes 📍\nVilla Club: Km. 12.5 Av. León Febres Cordero, Villa Club Etapa Flora.\nCeibos: C.C. Ceibos Center, piso 2, consultorio 210.\n\nResponde 'horarios' para conocer los horarios o 'menu' para otras opciones.",
                    ]),
                    'followup' => "Recomienda escribir 'horarios' o 'menu' según la necesidad.",
                ],
            ],
            'fallback' => [
                'title' => 'Sin coincidencia',
                'description' => 'Mensaje que se envía cuando ninguna palabra clave coincide.',
                'messages' => self::wrapMessages([
                    "No logré identificar tu solicitud. Responde 'menu' para ver las opciones disponibles o 'horarios' para conocer nuestros horarios de atención.",
                ]),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function resolve(string $brand, array $overrides = []): array
    {
        $defaults = self::defaultConfig($brand);
        if (empty($overrides)) {
            return self::finalize($defaults);
        }

        if (isset($overrides['entry']) && is_array($overrides['entry'])) {
            $defaults['entry'] = self::mergeSection($defaults['entry'], $overrides['entry']);
        }

        if (isset($overrides['options']) && is_array($overrides['options'])) {
            $defaults['options'] = self::mergeOptions($defaults['options'], $overrides['options']);
        }

        if (isset($overrides['fallback']) && is_array($overrides['fallback'])) {
            $defaults['fallback'] = self::mergeSection($defaults['fallback'], $overrides['fallback']);
        }

        return self::finalize($defaults);
    }

    /**
     * @param array<string, mixed> $flow
     * @return array{flow: array<string, mixed>, errors: array<int, string>}
     */
    public static function sanitizeSubmission(array $flow, string $brand): array
    {
        $errors = [];
        $brand = trim($brand) !== '' ? $brand : 'MedForge';
        $defaults = self::defaultConfig($brand);

        if (!isset($flow['entry']) || !is_array($flow['entry'])) {
            $errors[] = 'Falta la configuración del mensaje de bienvenida.';
        }

        if (!isset($flow['fallback']) || !is_array($flow['fallback'])) {
            $errors[] = 'Falta la configuración del mensaje de fallback.';
        }

        if (!isset($flow['options']) || !is_array($flow['options'])) {
            $errors[] = 'Debes definir al menos una opción del menú.';
        }

        if (!empty($errors)) {
            return ['flow' => $defaults, 'resolved' => self::finalize($defaults), 'errors' => $errors];
        }

        $resolved = $defaults;
        $resolved['entry'] = self::mergeSection($defaults['entry'], $flow['entry']);
        $resolved['fallback'] = self::mergeSection($defaults['fallback'], $flow['fallback']);
        $resolved['options'] = self::mergeOptions($defaults['options'], $flow['options']);

        $storage = self::purgeAutomaticKeywords($resolved);

        return [
            'flow' => $storage,
            'resolved' => self::finalize($storage),
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed> $flow
     * @return array<string, mixed>
     */
    public static function overview(string $brand, array $flow = []): array
    {
        $resolved = self::resolve($brand, $flow);

        return array_merge($resolved, [
            'meta' => [
                'brand' => $brand,
                'keywordLegend' => [
                    'Bienvenida' => $resolved['entry']['keywords'],
                    'Opción 1' => self::keywordsFromOption($resolved['options'], 'information'),
                    'Opción 2' => self::keywordsFromOption($resolved['options'], 'schedule'),
                    'Opción 3' => self::keywordsFromOption($resolved['options'], 'locations'),
                    'Fallback' => $resolved['fallback']['keywords'] ?? [],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $flow
     * @return array<int, string>
     */
    public static function keywordsFromSection(array $flow, string $key): array
    {
        if (!isset($flow[$key]) || !is_array($flow[$key])) {
            return [];
        }

        return $flow[$key]['keywords'] ?? [];
    }

    /**
     * @param array<int, array<string, mixed>> $options
     * @return array<int, string>
     */
    public static function keywordsFromOption(array $options, string $id): array
    {
        foreach ($options as $option) {
            if (($option['id'] ?? null) === $id) {
                return $option['keywords'] ?? [];
            }
        }

        return [];
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private static function wrapMessages(array $messages): array
    {
        return array_map(static fn (string $message): array => [
            'type' => 'text',
            'body' => trim($message),
        ], $messages);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private static function mergeSection(array $base, array $override): array
    {
        $merged = $base;

        if (isset($override['title']) && is_string($override['title'])) {
            $merged['title'] = self::sanitizeLine($override['title']);
        }

        if (isset($override['description']) && is_string($override['description'])) {
            $merged['description'] = self::sanitizeLine($override['description']);
        }

        if (isset($override['followup']) && is_string($override['followup'])) {
            $merged['followup'] = self::sanitizeLine($override['followup']);
        }

        if (isset($override['keywords'])) {
            $merged['keywords'] = self::sanitizeKeywords($override['keywords']);
        }

        if (isset($override['messages'])) {
            $merged['messages'] = self::sanitizeMessages($override['messages']);
        }

        return $merged;
    }

    /**
     * @param array<int, array<string, mixed>> $defaults
     * @param array<int, mixed> $overrides
     * @return array<int, array<string, mixed>>
     */
    private static function mergeOptions(array $defaults, array $overrides): array
    {
        $map = [];
        foreach ($defaults as $option) {
            $id = isset($option['id']) ? (string) $option['id'] : '';
            if ($id === '') {
                continue;
            }

            $map[$id] = $option;
        }

        foreach ($overrides as $override) {
            if (!is_array($override)) {
                continue;
            }

            $id = isset($override['id']) && is_string($override['id'])
                ? self::sanitizeKey($override['id'])
                : ($override['slug'] ?? null);
            $id = is_string($id) ? self::sanitizeKey($id) : '';
            if ($id === '') {
                continue;
            }

            $base = $map[$id] ?? [
                'id' => $id,
                'title' => 'Opción personalizada',
                'description' => '',
                'keywords' => [],
                'messages' => [],
                'followup' => '',
            ];

            $map[$id] = self::mergeSection($base, $override);
            $map[$id]['id'] = $id;
        }

        return array_values($map);
    }

    /**
     * @param array<int|string, mixed> $keywords
     * @return array<int, string>
     */
    private static function sanitizeKeywords($keywords): array
    {
        $list = [];

        if (is_string($keywords)) {
            $keywords = preg_split('/[,\n]/', $keywords) ?: [];
        }

        if (is_array($keywords)) {
            foreach ($keywords as $keyword) {
                if (!is_string($keyword)) {
                    continue;
                }

                $clean = self::sanitizeLine($keyword);
                if ($clean === '') {
                    continue;
                }

                $list[] = $clean;
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * @param mixed $messages
     * @return array<int, array<string, mixed>>
     */
    private static function sanitizeMessages($messages): array
    {
        if (is_string($messages)) {
            $decoded = json_decode($messages, true);
            if (is_array($decoded)) {
                $messages = $decoded;
            } else {
                $messages = [$messages];
            }
        }

        $normalized = [];

        if (!is_array($messages)) {
            return $normalized;
        }

        foreach ($messages as $message) {
            if (is_string($message)) {
                $message = ['type' => 'text', 'body' => $message];
            }

            if (!is_array($message)) {
                continue;
            }

            $type = isset($message['type']) && is_string($message['type'])
                ? strtolower(self::sanitizeLine($message['type']))
                : 'text';

            if (!in_array($type, ['text', 'buttons', 'list', 'template'], true)) {
                $type = 'text';
            }

            if ($type === 'template') {
                $entry = self::sanitizeTemplateMessage($message);
                if (!empty($entry)) {
                    $normalized[] = $entry;
                }

                continue;
            }

            $body = self::sanitizeMultiline($message['body'] ?? '');
            if ($body === '' && $type !== 'list') {
                continue;
            }

            $entry = [
                'type' => $type,
                'body' => $body,
            ];

            if (isset($message['header']) && is_string($message['header'])) {
                $header = self::sanitizeLine($message['header']);
                if ($header !== '') {
                    $entry['header'] = $header;
                }
            }

            if (isset($message['footer']) && is_string($message['footer'])) {
                $footer = self::sanitizeLine($message['footer']);
                if ($footer !== '') {
                    $entry['footer'] = $footer;
                }
            }

            if ($type === 'buttons') {
                $entry['buttons'] = self::sanitizeButtons($message['buttons'] ?? []);
                if (empty($entry['buttons'])) {
                    continue;
                }
            }

            if ($type === 'list') {
                $list = self::sanitizeListDefinition($message);
                if (empty($list['sections'])) {
                    continue;
                }
                $entry['body'] = $body === '' ? 'Lista interactiva' : $body;
                $entry['button'] = $list['button'];
                $entry['sections'] = $list['sections'];
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param mixed $buttons
     * @return array<int, array{id: string, title: string}>
     */
    private static function sanitizeButtons($buttons): array
    {
        $normalized = [];

        if (is_string($buttons)) {
            $decoded = json_decode($buttons, true);
            $buttons = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($buttons)) {
            return $normalized;
        }

        foreach ($buttons as $button) {
            if (!is_array($button)) {
                continue;
            }

            $title = self::sanitizeLine($button['title'] ?? '');
            $id = self::sanitizeKey($button['id'] ?? ($button['value'] ?? ''));

            if ($title === '') {
                continue;
            }

            if ($id === '') {
                $id = self::slugify($title);
            }

            $normalized[] = [
                'id' => $id,
                'title' => $title,
            ];

            if (count($normalized) >= self::BUTTON_LIMIT) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private static function sanitizeListDefinition(array $message): array
    {
        $button = isset($message['button']) && is_string($message['button'])
            ? self::sanitizeLine($message['button'])
            : 'Ver opciones';
        if ($button === '') {
            $button = 'Ver opciones';
        }

        $sections = [];
        if (isset($message['sections']) && is_array($message['sections'])) {
            foreach ($message['sections'] as $section) {
                if (!is_array($section)) {
                    continue;
                }

                $title = isset($section['title']) ? self::sanitizeLine($section['title']) : '';
                $rows = [];

                if (isset($section['rows']) && is_array($section['rows'])) {
                    foreach ($section['rows'] as $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        $id = isset($row['id']) ? self::sanitizeKey($row['id']) : '';
                        $rowTitle = isset($row['title']) ? self::sanitizeLine($row['title']) : '';
                        if ($id === '' || $rowTitle === '') {
                            continue;
                        }

                        $entry = [
                            'id' => $id,
                            'title' => $rowTitle,
                        ];

                        if (isset($row['description']) && is_string($row['description'])) {
                            $description = self::sanitizeLine($row['description']);
                            if ($description !== '') {
                                $entry['description'] = $description;
                            }
                        }

                        $rows[] = $entry;

                        if (count($rows) >= 10) {
                            break;
                        }
                    }
                }

                if (empty($rows)) {
                    continue;
                }

                $sections[] = [
                    'title' => $title,
                    'rows' => $rows,
                ];

                if (count($sections) >= 10) {
                    break;
                }
            }
        }

        return [
            'button' => $button,
            'sections' => $sections,
        ];
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private static function sanitizeTemplateMessage(array $message): array
    {
        $template = isset($message['template']) && is_array($message['template'])
            ? $message['template']
            : $message;

        $name = isset($template['name']) ? self::sanitizeLine($template['name']) : '';
        $language = isset($template['language']) ? self::sanitizeLine($template['language']) : '';

        if ($name === '' || $language === '') {
            return [];
        }

        $category = isset($template['category']) ? strtoupper(self::sanitizeLine($template['category'])) : '';
        $components = self::sanitizeTemplateComponents($template['components'] ?? []);

        $body = isset($message['body']) ? self::sanitizeMultiline($message['body']) : '';
        if ($body === '') {
            $body = 'Plantilla: ' . $name . ' (' . $language . ')';
        }

        return [
            'type' => 'template',
            'body' => $body,
            'template' => [
                'name' => $name,
                'language' => $language,
                'category' => $category,
                'components' => $components,
            ],
        ];
    }

    /**
     * @param mixed $components
     * @return array<int, array<string, mixed>>
     */
    private static function sanitizeTemplateComponents($components): array
    {
        if (is_string($components)) {
            $decoded = json_decode($components, true);
            $components = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($components)) {
            return [];
        }

        $normalized = [];

        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }

            $type = isset($component['type']) ? strtoupper(self::sanitizeLine($component['type'])) : '';
            if ($type === '') {
                continue;
            }

            $entry = ['type' => $type];

            if (isset($component['sub_type'])) {
                $entry['sub_type'] = strtoupper(self::sanitizeLine($component['sub_type']));
            }

            if (isset($component['index'])) {
                $entry['index'] = (int) $component['index'];
            }

            if (isset($component['parameters']) && is_array($component['parameters'])) {
                $parameters = [];
                foreach ($component['parameters'] as $parameter) {
                    if (!is_array($parameter)) {
                        continue;
                    }

                    $paramType = isset($parameter['type'])
                        ? strtoupper(self::sanitizeLine($parameter['type']))
                        : 'TEXT';

                    $param = ['type' => $paramType];

                    if (isset($parameter['text'])) {
                        $text = self::sanitizeMultiline($parameter['text']);
                        if ($text === '') {
                            continue;
                        }
                        $param['text'] = $text;
                    }

                    if (isset($parameter['payload'])) {
                        $payload = self::sanitizeLine($parameter['payload']);
                        if ($payload === '') {
                            continue;
                        }
                        $param['payload'] = $payload;
                    }

                    if (isset($parameter['currency']) && is_array($parameter['currency'])) {
                        $param['currency'] = $parameter['currency'];
                    }

                    if (isset($parameter['date_time']) && is_array($parameter['date_time'])) {
                        $param['date_time'] = $parameter['date_time'];
                    }

                    if (count($param) > 1) {
                        $parameters[] = $param;
                    }
                }

                if (!empty($parameters)) {
                    $entry['parameters'] = $parameters;
                }
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $flow
     * @return array<string, mixed>
     */
    private static function finalize(array $flow): array
    {
        $flow['entry']['keywords'] = self::augmentKeywords($flow['entry']);
        $flow['fallback']['keywords'] = self::augmentKeywords($flow['fallback']);

        foreach ($flow['options'] as $index => $option) {
            $flow['options'][$index]['keywords'] = self::augmentKeywords($option);
        }

        return $flow;
    }

    /**
     * @param array<string, mixed> $flow
     * @return array<string, mixed>
     */
    private static function purgeAutomaticKeywords(array $flow): array
    {
        $flow['entry']['keywords'] = self::stripAutomaticKeywords($flow['entry']);
        $flow['fallback']['keywords'] = self::stripAutomaticKeywords($flow['fallback']);

        foreach ($flow['options'] as $index => $option) {
            $flow['options'][$index]['keywords'] = self::stripAutomaticKeywords($option);
        }

        return $flow;
    }

    /**
     * @param array<string, mixed> $section
     * @return array<int, string>
     */
    private static function stripAutomaticKeywords(array $section): array
    {
        $keywords = self::sanitizeKeywords($section['keywords'] ?? []);
        if (empty($keywords)) {
            return [];
        }

        $automatic = [];
        if (!empty($section['messages']) && is_array($section['messages'])) {
            foreach ($section['messages'] as $message) {
                if (!is_array($message)) {
                    continue;
                }

                if (($message['type'] ?? '') === 'buttons') {
                    foreach ($message['buttons'] ?? [] as $button) {
                        if (!is_array($button)) {
                            continue;
                        }

                        if (isset($button['id']) && is_string($button['id'])) {
                            $automatic[] = self::sanitizeLine($button['id']);
                        }

                        if (isset($button['title']) && is_string($button['title'])) {
                            $automatic[] = self::sanitizeLine($button['title']);
                        }
                    }

                    continue;
                }

                if (($message['type'] ?? '') === 'list') {
                    foreach ($message['sections'] ?? [] as $sectionRows) {
                        if (!is_array($sectionRows)) {
                            continue;
                        }

                        foreach ($sectionRows['rows'] ?? [] as $row) {
                            if (!is_array($row)) {
                                continue;
                            }

                            if (isset($row['id']) && is_string($row['id'])) {
                                $automatic[] = self::sanitizeLine($row['id']);
                            }

                            if (isset($row['title']) && is_string($row['title'])) {
                                $automatic[] = self::sanitizeLine($row['title']);
                            }
                        }
                    }
                }
            }
        }

        if (empty($automatic)) {
            return $keywords;
        }

        $automatic = array_filter($automatic, static fn ($keyword) => $keyword !== '');

        return array_values(array_filter($keywords, static fn ($keyword) => $keyword !== '' && !in_array($keyword, $automatic, true)));
    }

    /**
     * @param array<string, mixed> $section
     * @return array<int, string>
     */
    private static function augmentKeywords(array $section): array
    {
        $keywords = $section['keywords'] ?? [];
        if (!is_array($keywords)) {
            $keywords = [];
        }

        $keywords = self::sanitizeKeywords($keywords);

        if (!empty($section['messages']) && is_array($section['messages'])) {
            foreach ($section['messages'] as $message) {
                if (!is_array($message)) {
                    continue;
                }

                if (($message['type'] ?? '') === 'buttons') {
                    foreach ($message['buttons'] ?? [] as $button) {
                        if (!is_array($button)) {
                            continue;
                        }

                        if (isset($button['id']) && is_string($button['id'])) {
                            $keywords[] = self::sanitizeLine($button['id']);
                        }

                        if (isset($button['title']) && is_string($button['title'])) {
                            $keywords[] = self::sanitizeLine($button['title']);
                        }
                    }

                    continue;
                }

                if (($message['type'] ?? '') === 'list') {
                    foreach ($message['sections'] ?? [] as $sectionRows) {
                        if (!is_array($sectionRows)) {
                            continue;
                        }

                        foreach ($sectionRows['rows'] ?? [] as $row) {
                            if (!is_array($row)) {
                                continue;
                            }

                            if (isset($row['id']) && is_string($row['id'])) {
                                $keywords[] = self::sanitizeLine($row['id']);
                            }

                            if (isset($row['title']) && is_string($row['title'])) {
                                $keywords[] = self::sanitizeLine($row['title']);
                            }
                        }
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($keywords, static fn ($keyword) => $keyword !== '')));
    }

    private static function sanitizeLine($value): string
    {
        $value = trim((string) $value);

        return $value;
    }

    private static function sanitizeMultiline($value): string
    {
        $value = (string) $value;
        $value = preg_replace("/\r/", '', $value) ?? $value;
        $value = trim($value);

        return $value;
    }

    private static function sanitizeKey($value): string
    {
        $value = self::sanitizeLine($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_\-]+/', '_', $value) ?? $value;
        $value = trim($value, '_-');

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) > 32) {
            $value = substr($value, 0, 32);
        }

        return $value;
    }

    private static function slugify(string $value): string
    {
        $value = strtolower(self::sanitizeLine($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
        $value = trim($value, '_');

        return $value === '' ? 'opcion_' . uniqid() : $value;
    }
}
