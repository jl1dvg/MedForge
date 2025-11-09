<?php

namespace Modules\WhatsApp\Support;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function ksort;
use function preg_match;
use function preg_replace;
use function preg_split;
use function str_replace;
use function strtolower;
use function trim;
use function uniqid;

class AutoresponderFlow
{
    public const CURRENT_VERSION = 2;
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
     * @return array<int, string>
     */
    public static function patientScenarioKeywords(): array
    {
        return ['4', 'opcion 4', 'paciente', 'pacientes', 'historia clinica', 'verificar paciente'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultConfig(string $brand): array
    {
        $brand = trim($brand) !== '' ? $brand : 'MedForge';

        $flow = [
            'version' => self::CURRENT_VERSION,
            'entry_keywords' => self::menuKeywords(),
            'shortcuts' => [
                [
                    'id' => 'menu-shortcut',
                    'title' => 'Volver al menú principal',
                    'keywords' => self::menuKeywords(),
                    'target' => 'menu',
                    'reset_context' => ['hc_number', 'patient'],
                ],
            ],
            'nodes' => [
                'menu' => [
                    'id' => 'menu',
                    'type' => 'message',
                    'title' => 'Menú principal',
                    'description' => 'Escenarios disponibles para guiar la conversación.',
                    'messages' => self::wrapMessages([
                        '¡Hola! Soy Dr. Ojito, el asistente virtual de {{brand}} 👁️',
                        'Puedo ayudarte con estas solicitudes:' . "\n" .
                        '1. Obtener información de servicios' . "\n" .
                        '2. Horarios de atención' . "\n" .
                        '3. Ubicaciones' . "\n" .
                        '4. Verificar si un paciente está registrado' . "\n\n" .
                        "Responde con el número o escribe la opción que necesites.",
                    ]),
                    'responses' => [
                        [
                            'id' => 'information',
                            'title' => 'Opción 1 · Obtener información',
                            'keywords' => self::informationKeywords(),
                            'target' => 'information',
                        ],
                        [
                            'id' => 'schedule',
                            'title' => 'Opción 2 · Horarios de atención',
                            'keywords' => self::scheduleKeywords(),
                            'target' => 'schedule',
                        ],
                        [
                            'id' => 'locations',
                            'title' => 'Opción 3 · Ubicaciones',
                            'keywords' => self::locationKeywords(),
                            'target' => 'locations',
                        ],
                        [
                            'id' => 'patient-entry',
                            'title' => 'Opción 4 · Verificar paciente',
                            'keywords' => self::patientScenarioKeywords(),
                            'target' => 'patient-intro',
                        ],
                    ],
                ],
                'information' => [
                    'id' => 'information',
                    'type' => 'message',
                    'title' => 'Información general',
                    'description' => 'Respuestas sobre servicios y procedimientos.',
                    'messages' => self::wrapMessages([
                        'Obtener Información',
                        "Selecciona la información que deseas conocer:\n• Procedimientos oftalmológicos disponibles.\n• Servicios complementarios como óptica y exámenes especializados.\n• Seguros y convenios con los que trabajamos.\n\nEscribe 'horarios' para conocer los horarios de atención o 'menu' para volver al inicio.",
                    ]),
                    'responses' => [
                        [
                            'id' => 'menu-return',
                            'title' => 'Volver al menú',
                            'keywords' => self::menuKeywords(),
                            'target' => 'menu',
                        ],
                    ],
                ],
                'schedule' => [
                    'id' => 'schedule',
                    'type' => 'message',
                    'title' => 'Horarios de atención',
                    'description' => 'Horarios disponibles para cada sede.',
                    'messages' => self::wrapMessages([
                        "Horarios de atención 🕖\nVilla Club: Lunes a Viernes 09h00 - 18h00, Sábados 09h00 - 13h00.\nCeibos: Lunes a Viernes 09h00 - 18h00, Sábados 09h00 - 13h00.\n\nSi necesitas otra información responde 'menu'.",
                    ]),
                    'responses' => [
                        [
                            'id' => 'schedule-menu',
                            'title' => 'Volver al menú',
                            'keywords' => self::menuKeywords(),
                            'target' => 'menu',
                        ],
                    ],
                ],
                'locations' => [
                    'id' => 'locations',
                    'type' => 'message',
                    'title' => 'Ubicaciones',
                    'description' => 'Direcciones de las sedes disponibles.',
                    'messages' => self::wrapMessages([
                        "Nuestras sedes 📍\nVilla Club: Km. 12.5 Av. León Febres Cordero, Villa Club Etapa Flora.\nCeibos: C.C. Ceibos Center, piso 2, consultorio 210.\n\nResponde 'horarios' para conocer los horarios o 'menu' para otras opciones.",
                    ]),
                    'responses' => [
                        [
                            'id' => 'locations-menu',
                            'title' => 'Volver al menú',
                            'keywords' => self::menuKeywords(),
                            'target' => 'menu',
                        ],
                    ],
                ],
                'patient-intro' => [
                    'id' => 'patient-intro',
                    'type' => 'message',
                    'title' => 'Verificación de paciente',
                    'description' => 'Explica cómo validar la existencia del paciente en la base de datos.',
                    'messages' => self::wrapMessages([
                        'Validemos tu información para continuar 👇',
                        'Necesito que nos indiques el número de historia clínica (HC). Con ese dato confirmaremos si ya estás registrado en nuestros sistemas y te mostraremos los pasos siguientes.',
                    ]),
                    'responses' => [
                        [
                            'id' => 'patient-provide-hc',
                            'title' => 'Ingresar HC',
                            'keywords' => ['continuar', 'ingresar hc', 'hc', 'si', 'sí'],
                            'target' => 'patient-capture-hc',
                            'clear_context' => ['patient'],
                        ],
                        [
                            'id' => 'patient-menu',
                            'title' => 'Volver al menú',
                            'keywords' => self::menuKeywords(),
                            'target' => 'menu',
                            'clear_context' => ['hc_number', 'patient'],
                        ],
                    ],
                ],
                'patient-capture-hc' => [
                    'id' => 'patient-capture-hc',
                    'type' => 'input',
                    'title' => 'Validar paciente por HC',
                    'description' => 'Solicita al usuario el número de historia clínica.',
                    'messages' => self::wrapMessages([
                        'Por favor escribe tu número de historia clínica. Son 6 a 12 dígitos sin guiones ni espacios.',
                    ]),
                    'input' => [
                        'field' => 'hc_number',
                        'label' => 'Número de historia clínica',
                        'normalize' => 'digits',
                        'pattern' => '^(?:\d{6,12})$',
                        'error_messages' => self::wrapMessages([
                            'No reconozco ese número de HC. Asegúrate de enviar solo dígitos, por ejemplo 00123456.',
                        ]),
                    ],
                    'next' => 'patient-lookup',
                ],
                'patient-lookup' => [
                    'id' => 'patient-lookup',
                    'type' => 'decision',
                    'title' => 'Consulta de paciente',
                    'description' => 'Verifica en la base de datos local si el paciente existe.',
                    'branches' => [
                        [
                            'id' => 'patient-found',
                            'title' => 'Paciente encontrado',
                            'condition' => [
                                'type' => 'patient_exists',
                                'field' => 'hc_number',
                                'source' => 'local',
                            ],
                            'messages' => self::wrapMessages([
                                '¡Excelente {{patient.full_name}}! Encontré tu registro con HC {{context.hc_number}}.',
                                'Puedo ayudarte a revisar tus citas, actualizar datos o agendar un nuevo control. Solo dime qué necesitas o escribe "menu" para volver al inicio.',
                            ]),
                            'next' => 'patient-found-options',
                        ],
                        [
                            'id' => 'patient-not-found',
                            'title' => 'Paciente no encontrado',
                            'condition' => [
                                'type' => 'always',
                            ],
                            'messages' => self::wrapMessages([
                                'No encontré registros con ese número de HC.',
                                'Si crees que es un error, puedes intentar nuevamente o compartir tus nombres completos para que un asesor te contacte.',
                            ]),
                            'next' => 'patient-missing-options',
                        ],
                    ],
                ],
                'patient-found-options' => [
                    'id' => 'patient-found-options',
                    'type' => 'message',
                    'title' => 'Escenario · Paciente registrado',
                    'description' => 'Opciones cuando el paciente existe en la base local.',
                    'messages' => self::wrapMessages([
                        '¿Cómo seguimos? Puedes indicarme si quieres agendar una cita, actualizar tus datos o escribir "menu" para ver otras opciones.',
                    ]),
                    'responses' => [
                        [
                            'id' => 'patient-found-new-search',
                            'title' => 'Buscar otro paciente',
                            'keywords' => ['otro paciente', 'buscar otro', 'otra hc'],
                            'target' => 'patient-capture-hc',
                            'clear_context' => ['hc_number', 'patient'],
                        ],
                        [
                            'id' => 'patient-found-menu',
                            'title' => 'Volver al menú',
                            'keywords' => self::menuKeywords(),
                            'target' => 'menu',
                            'clear_context' => ['hc_number', 'patient'],
                        ],
                    ],
                ],
                'patient-missing-options' => [
                    'id' => 'patient-missing-options',
                    'type' => 'message',
                    'title' => 'Escenario · Paciente nuevo',
                    'description' => 'Acciones sugeridas cuando el paciente no existe.',
                    'messages' => self::wrapMessages([
                        'Parece que aún no estás registrado en nuestros sistemas.',
                        'Si deseas que un asesor te ayude con el registro, escribe tus nombres completos o responde "menu" para otras opciones.',
                    ]),
                    'responses' => [
                        [
                            'id' => 'patient-missing-retry',
                            'title' => 'Intentar nuevamente',
                            'keywords' => ['reintentar', 'intentar de nuevo', 'otra hc'],
                            'target' => 'patient-capture-hc',
                            'clear_context' => ['hc_number', 'patient'],
                        ],
                        [
                            'id' => 'patient-missing-menu',
                            'title' => 'Volver al menú',
                            'keywords' => self::menuKeywords(),
                            'target' => 'menu',
                            'clear_context' => ['hc_number', 'patient'],
                        ],
                    ],
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

        $flow['meta'] = [
            'brand' => $brand,
        ];

        return $flow;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function resolve(string $brand, array $overrides = []): array
    {
        $base = self::defaultConfig($brand);
        if (empty($overrides)) {
            return $base;
        }

        $result = self::sanitizeFlow($base, $overrides, false);
        if (!empty($result['errors'])) {
            $base['meta']['errors'] = $result['errors'];

            return $base;
        }

        return $result['flow'];
    }

    /**
     * @param array<string, mixed> $flow
     * @return array{flow: array<string, mixed>, resolved?: array<string, mixed>, errors: array<int, string>}
     */
    public static function sanitizeSubmission(array $flow, string $brand): array
    {
        $base = self::defaultConfig($brand);
        $result = self::sanitizeFlow($base, $flow, true);

        if (!empty($result['errors'])) {
            $resolved = $result['flow'];
            $resolved['meta']['errors'] = $result['errors'];

            return [
                'flow' => $base,
                'resolved' => $resolved,
                'errors' => $result['errors'],
            ];
        }

        return [
            'flow' => $result['flow'],
            'resolved' => $result['flow'],
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
        $keywords = [];

        foreach ($resolved['shortcuts'] ?? [] as $shortcut) {
            if (!is_array($shortcut) || empty($shortcut['title'])) {
                continue;
            }

            $keywords[$shortcut['title']] = $shortcut['keywords'] ?? [];
        }

        return array_merge($resolved, [
            'meta' => array_merge($resolved['meta'] ?? [], [
                'brand' => $brand,
                'keywordLegend' => $keywords,
            ]),
        ]);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overrides
     * @return array{flow: array<string, mixed>, errors: array<int, string>}
     */
    private static function sanitizeFlow(array $base, array $overrides, bool $forStorage): array
    {
        $errors = [];
        $flow = $base;

        if (!isset($overrides['version']) || (int) $overrides['version'] !== self::CURRENT_VERSION) {
            $overrides = self::migrateLegacyFlow($overrides, $base);
        }

        $flow['version'] = self::CURRENT_VERSION;

        if (isset($overrides['entry_keywords'])) {
            $keywords = self::sanitizeKeywords($overrides['entry_keywords']);
            if (!empty($keywords)) {
                $flow['entry_keywords'] = $keywords;
            }
        }

        if (isset($overrides['shortcuts']) && is_array($overrides['shortcuts'])) {
            $flow['shortcuts'] = self::sanitizeShortcuts($overrides['shortcuts']);
        }

        $nodes = [];
        $baseNodes = [];
        foreach ($base['nodes'] ?? [] as $nodeId => $node) {
            if (!is_array($node)) {
                continue;
            }
            $id = isset($node['id']) ? self::sanitizeKey($node['id']) : (is_string($nodeId) ? self::sanitizeKey($nodeId) : '');
            if ($id === '') {
                $id = self::randomKey('node');
            }
            $node['id'] = $id;
            $baseNodes[$id] = $node;
        }

        $overrideNodes = $overrides['nodes'] ?? [];
        if (is_array($overrideNodes)) {
            foreach ($overrideNodes as $nodeData) {
                if (!is_array($nodeData)) {
                    continue;
                }

                $id = isset($nodeData['id']) ? self::sanitizeKey($nodeData['id']) : null;
                if ($id === null && isset($nodeData['slug'])) {
                    $id = self::sanitizeKey($nodeData['slug']);
                }

                if ($id === null || $id === '') {
                    $id = self::randomKey('node');
                }

                $baseNode = $baseNodes[$id] ?? null;
                $sanitized = self::sanitizeNode($id, $nodeData, $baseNode, $errors, $forStorage);
                if ($sanitized === null) {
                    continue;
                }

                $nodes[$id] = $sanitized;
            }
        }

        foreach ($baseNodes as $id => $node) {
            if (isset($nodes[$id])) {
                continue;
            }

            $nodes[$id] = self::sanitizeNode($id, $node, $node, $errors, $forStorage) ?? $node;
        }

        if (empty($nodes)) {
            $errors[] = 'Debes definir al menos un escenario.';
            $nodes = $baseNodes;
        }

        ksort($nodes);
        $flow['nodes'] = array_values($nodes);

        if (isset($overrides['fallback']) && is_array($overrides['fallback'])) {
            $flow['fallback'] = self::sanitizeSection($overrides['fallback'], $base['fallback'] ?? []);
        }

        $flow['meta']['brand'] = $base['meta']['brand'] ?? ($flow['meta']['brand'] ?? 'MedForge');

        return [
            'flow' => $flow,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @param array<string, mixed>|null $base
     * @param array<string, mixed> $node
     * @param array<int, string> $errors
     * @return array<string, mixed>|null
     */
    private static function sanitizeNode(string $id, array $node, ?array $base, array &$errors, bool $forStorage): ?array
    {
        $type = isset($node['type']) && is_string($node['type'])
            ? strtolower(self::sanitizeKey($node['type']))
            : ($base['type'] ?? 'message');

        if (!in_array($type, ['message', 'input', 'decision'], true)) {
            $type = 'message';
        }

        $sanitized = [
            'id' => $id,
            'type' => $type,
            'title' => self::sanitizeLine($node['title'] ?? ($base['title'] ?? 'Escenario')), 
            'description' => self::sanitizeLine($node['description'] ?? ($base['description'] ?? '')),
        ];

        if ($type === 'message') {
            $messages = $node['messages'] ?? ($base['messages'] ?? []);
            $responses = $node['responses'] ?? ($base['responses'] ?? []);
            $sanitized['messages'] = self::sanitizeMessages($messages);
            $sanitized['responses'] = self::sanitizeResponses($responses, $errors);
            if (!empty($node['next']) || !empty($base['next'])) {
                $next = self::sanitizeKey($node['next'] ?? ($base['next'] ?? ''));
                if ($next !== '') {
                    $sanitized['next'] = $next;
                }
            }
        } elseif ($type === 'input') {
            $messages = $node['messages'] ?? ($base['messages'] ?? []);
            $sanitized['messages'] = self::sanitizeMessages($messages);
            $input = $node['input'] ?? ($base['input'] ?? []);
            $sanitized['input'] = self::sanitizeInputDefinition($input, $errors);
            $next = self::sanitizeKey($node['next'] ?? ($base['next'] ?? ''));
            if ($next === '') {
                $errors[] = 'El escenario de captura debe definir el siguiente paso.';
            } else {
                $sanitized['next'] = $next;
            }
        } else { // decision
            $sanitized['branches'] = self::sanitizeBranches($node['branches'] ?? ($base['branches'] ?? []), $errors);
            if (empty($sanitized['branches'])) {
                $errors[] = 'El escenario de decisión debe tener al menos una condición.';
            }
        }

        if (!$forStorage && isset($base['ui'])) {
            $sanitized['ui'] = $base['ui'];
        }

        return $sanitized;
    }

    /**
     * @param array<int|string, mixed> $shortcuts
     * @return array<int, array<string, mixed>>
     */
    private static function sanitizeShortcuts($shortcuts): array
    {
        if (!is_array($shortcuts)) {
            return [];
        }

        $sanitized = [];
        foreach ($shortcuts as $shortcut) {
            if (!is_array($shortcut)) {
                continue;
            }

            $id = isset($shortcut['id']) ? self::sanitizeKey($shortcut['id']) : self::randomKey('shortcut');
            $title = self::sanitizeLine($shortcut['title'] ?? 'Acceso directo');
            $target = self::sanitizeKey($shortcut['target'] ?? '');
            $keywords = self::sanitizeKeywords($shortcut['keywords'] ?? []);
            if ($title === '' || $target === '' || empty($keywords)) {
                continue;
            }

            $entry = [
                'id' => $id,
                'title' => $title,
                'target' => $target,
                'keywords' => $keywords,
            ];

            if (isset($shortcut['clear_context'])) {
                $entry['clear_context'] = self::sanitizeContextKeys($shortcut['clear_context']);
            } elseif (isset($shortcut['reset_context'])) {
                $entry['clear_context'] = self::sanitizeContextKeys($shortcut['reset_context']);
            }

            $sanitized[] = $entry;
        }

        return $sanitized;
    }

    /**
     * @param array<int|string, mixed> $responses
     * @param array<int, string> $errors
     * @return array<int, array<string, mixed>>
     */
    private static function sanitizeResponses($responses, array &$errors): array
    {
        if (!is_array($responses)) {
            return [];
        }

        $sanitized = [];
        foreach ($responses as $response) {
            if (!is_array($response)) {
                continue;
            }

            $id = isset($response['id']) ? self::sanitizeKey($response['id']) : self::randomKey('resp');
            $title = self::sanitizeLine($response['title'] ?? 'Respuesta');
            $target = self::sanitizeKey($response['target'] ?? '');
            $keywords = self::sanitizeKeywords($response['keywords'] ?? []);

            if ($title === '' || $target === '' || empty($keywords)) {
                continue;
            }

            $entry = [
                'id' => $id,
                'title' => $title,
                'target' => $target,
                'keywords' => $keywords,
            ];

            if (isset($response['messages'])) {
                $entry['messages'] = self::sanitizeMessages($response['messages']);
            }

            if (isset($response['clear_context'])) {
                $entry['clear_context'] = self::sanitizeContextKeys($response['clear_context']);
            }

            if (isset($response['set_context']) && is_array($response['set_context'])) {
                $contextSet = [];
                foreach ($response['set_context'] as $key => $value) {
                    if (!is_string($key)) {
                        continue;
                    }
                    $contextSet[self::sanitizeKey($key)] = self::sanitizeLine(is_scalar($value) ? (string) $value : '');
                }
                if (!empty($contextSet)) {
                    $entry['set_context'] = $contextSet;
                }
            }

            $sanitized[] = $entry;
        }

        return array_slice($sanitized, 0, 24);
    }

    /**
     * @param array<int|string, mixed> $branches
     * @param array<int, string> $errors
     * @return array<int, array<string, mixed>>
     */
    private static function sanitizeBranches($branches, array &$errors): array
    {
        if (!is_array($branches)) {
            return [];
        }

        $sanitized = [];
        foreach ($branches as $branch) {
            if (!is_array($branch)) {
                continue;
            }

            $id = isset($branch['id']) ? self::sanitizeKey($branch['id']) : self::randomKey('branch');
            $title = self::sanitizeLine($branch['title'] ?? 'Condición');
            $target = self::sanitizeKey($branch['next'] ?? ($branch['target'] ?? ''));
            $condition = self::sanitizeCondition($branch['condition'] ?? []);

            if ($target === '' || empty($condition)) {
                continue;
            }

            $entry = [
                'id' => $id,
                'title' => $title === '' ? 'Condición' : $title,
                'next' => $target,
                'condition' => $condition,
            ];

            if (isset($branch['messages'])) {
                $entry['messages'] = self::sanitizeMessages($branch['messages']);
            }

            if (isset($branch['clear_context'])) {
                $entry['clear_context'] = self::sanitizeContextKeys($branch['clear_context']);
            }

            if (isset($branch['set_context']) && is_array($branch['set_context'])) {
                $setContext = [];
                foreach ($branch['set_context'] as $key => $value) {
                    if (!is_string($key)) {
                        continue;
                    }
                    $setContext[self::sanitizeKey($key)] = self::sanitizeLine(is_scalar($value) ? (string) $value : '');
                }
                if (!empty($setContext)) {
                    $entry['set_context'] = $setContext;
                }
            }

            $sanitized[] = $entry;
        }

        return array_slice($sanitized, 0, 10);
    }

    /**
     * @param array<string, mixed>|string $input
     * @param array<int, string> $errors
     * @return array<string, mixed>
     */
    private static function sanitizeInputDefinition($input, array &$errors): array
    {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            $input = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($input)) {
            $input = [];
        }

        $field = self::sanitizeKey($input['field'] ?? 'value');
        if ($field === '') {
            $field = 'value';
        }

        $definition = [
            'field' => $field,
            'label' => self::sanitizeLine($input['label'] ?? 'Dato solicitado'),
            'pattern' => self::sanitizePattern($input['pattern'] ?? ''),
            'normalize' => self::sanitizeNormalizeStrategy($input['normalize'] ?? ''),
        ];

        if (isset($input['error_messages'])) {
            $definition['error_messages'] = self::sanitizeMessages($input['error_messages']);
        }

        if (isset($input['store']) && is_string($input['store'])) {
            $definition['store'] = self::sanitizeLine($input['store']);
        }

        return $definition;
    }

    /**
     * @param array<string, mixed>|string $condition
     * @return array<string, mixed>
     */
    private static function sanitizeCondition($condition): array
    {
        if (is_string($condition)) {
            $decoded = json_decode($condition, true);
            $condition = is_array($decoded) ? $decoded : ['type' => $condition];
        }

        if (!is_array($condition)) {
            return ['type' => 'always'];
        }

        $type = isset($condition['type']) ? strtolower(self::sanitizeKey($condition['type'])) : 'always';

        if (!in_array($type, ['always', 'patient_exists', 'equals', 'not_equals', 'has_value'], true)) {
            $type = 'always';
        }

        $sanitized = ['type' => $type];

        if (isset($condition['field'])) {
            $sanitized['field'] = self::sanitizeKey($condition['field']);
        }

        if ($type === 'patient_exists') {
            $sanitized['source'] = self::sanitizeSource($condition['source'] ?? 'any');
        } elseif (in_array($type, ['equals', 'not_equals'], true)) {
            $sanitized['value'] = self::sanitizeLine((string) ($condition['value'] ?? ''));
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $section
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private static function sanitizeSection(array $section, array $base): array
    {
        $sanitized = $base;

        if (isset($section['title']) && is_string($section['title'])) {
            $sanitized['title'] = self::sanitizeLine($section['title']);
        }

        if (isset($section['description']) && is_string($section['description'])) {
            $sanitized['description'] = self::sanitizeLine($section['description']);
        }

        if (isset($section['messages'])) {
            $sanitized['messages'] = self::sanitizeMessages($section['messages']);
        }

        return $sanitized;
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

                $list[] = strtolower($clean);
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * @param mixed $messages
     * @return array<int, array<string, mixed>>
     */
    public static function sanitizeMessages($messages): array
    {
        if (is_string($messages)) {
            $decoded = json_decode($messages, true);
            if (is_array($decoded)) {
                $messages = $decoded;
            } else {
                $messages = [$messages];
            }
        }

        if (!is_array($messages)) {
            return [];
        }

        $normalized = [];

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

        return array_slice($normalized, 0, 20);
    }

    /**
     * @param mixed $buttons
     * @return array<int, array<string, string>>
     */
    private static function sanitizeButtons($buttons): array
    {
        if (!is_array($buttons)) {
            return [];
        }

        $normalized = [];
        foreach ($buttons as $button) {
            if (!is_array($button)) {
                continue;
            }

            $title = self::sanitizeLine($button['title'] ?? $button['text'] ?? '');
            if ($title === '') {
                continue;
            }

            $entry = [
                'id' => self::sanitizeKey($button['id'] ?? $button['payload'] ?? uniqid('btn_', true)),
                'title' => $title,
            ];

            if (isset($button['payload']) && is_string($button['payload'])) {
                $entry['payload'] = self::sanitizeLine($button['payload']);
            }

            $normalized[] = $entry;

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
        $button = isset($message['button']) ? self::sanitizeLine($message['button']) : '';
        if ($button === '') {
            $button = 'Seleccionar';
        }

        $sections = [];

        foreach ($message['sections'] ?? [] as $section) {
            if (!is_array($section)) {
                continue;
            }

            $title = self::sanitizeLine($section['title'] ?? '');
            $rows = [];

            foreach ($section['rows'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $rowTitle = self::sanitizeLine($row['title'] ?? '');
                $rowId = self::sanitizeKey($row['id'] ?? '');

                if ($rowTitle === '' || $rowId === '') {
                    continue;
                }

                $entry = [
                    'id' => $rowId,
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

            if (isset($component['format'])) {
                $entry['format'] = strtoupper(self::sanitizeLine($component['format']));
            }

            if (isset($component['text']) && is_string($component['text'])) {
                $entry['text'] = self::sanitizeMultiline($component['text']);
            }

            if (isset($component['example']) && is_array($component['example'])) {
                $entry['example'] = array_values(array_filter(array_map(static function ($value) {
                    if (is_array($value) || is_string($value) || is_numeric($value)) {
                        return (string) $value;
                    }

                    return null;
                }, $component['example'])));
            }

            if (isset($component['parameters']) && is_array($component['parameters'])) {
                $entry['parameters'] = $component['parameters'];
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    private static function sanitizePattern(string $pattern): string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return '';
        }

        if (self::compileRegex($pattern) === null) {
            return '';
        }

        return $pattern;
    }

    private static function compileRegex(string $pattern): ?string
    {
        $pattern = trim($pattern);
        if ($pattern === '') {
            return null;
        }

        $escaped = str_replace('~', '\\~', $pattern);
        $regex = '~' . $escaped . '~u';

        if (@preg_match($regex, '') === false) {
            return null;
        }

        return $regex;
    }

    private static function sanitizeNormalizeStrategy(string $strategy): string
    {
        $strategy = strtolower(self::sanitizeKey($strategy));

        if (!in_array($strategy, ['digits', 'trim', 'uppercase', 'lowercase'], true)) {
            return 'trim';
        }

        return $strategy;
    }

    private static function sanitizeSource(string $source): string
    {
        $source = strtolower(self::sanitizeKey($source));
        if (!in_array($source, ['local', 'registry', 'any'], true)) {
            return 'any';
        }

        return $source;
    }

    /**
     * @param array<int|string, mixed> $keys
     * @return array<int, string>
     */
    private static function sanitizeContextKeys($keys): array
    {
        if (!is_array($keys)) {
            return [];
        }

        $sanitized = [];

        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }

            $clean = self::sanitizeKey($key);
            if ($clean === '') {
                continue;
            }

            $sanitized[] = $clean;
        }

        return array_values(array_unique($sanitized));
    }

    private static function sanitizeKey(string $key): string
    {
        $key = trim($key);
        $key = preg_replace('/[^a-zA-Z0-9_\-]+/', '-', strtolower($key));

        return trim((string) $key, '-');
    }

    private static function sanitizeLine(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        return mb_substr($value, 0, 500);
    }

    private static function sanitizeMultiline(string $value): string
    {
        $value = trim(preg_replace("/\r\n?\n/", "\n\n", (string) $value) ?? '');

        return mb_substr($value, 0, 2000);
    }

    /**
     * @param array<int, string> $messages
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
     * @param array<string, mixed> $legacy
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private static function migrateLegacyFlow(array $legacy, array $base): array
    {
        if (isset($legacy['version']) && (int) $legacy['version'] === self::CURRENT_VERSION) {
            return $legacy;
        }

        if (!isset($legacy['entry'], $legacy['options'], $legacy['fallback'])) {
            return $legacy;
        }

        $flow = $base;

        $entryKeywords = self::sanitizeKeywords($legacy['entry']['keywords'] ?? []);
        if (!empty($entryKeywords)) {
            $flow['entry_keywords'] = $entryKeywords;
            $flow['shortcuts'][0]['keywords'] = $entryKeywords;
        }

        $flow['nodes'] = array_values($flow['nodes']);
        $map = [];
        foreach ($flow['nodes'] as $node) {
            if (!isset($node['id'])) {
                continue;
            }
            $map[$node['id']] = $node;
        }

        if (isset($legacy['entry']['messages'])) {
            $map['menu']['messages'] = self::sanitizeMessages($legacy['entry']['messages']);
        }

        $optionMap = [
            'information' => 'information',
            'schedule' => 'schedule',
            'locations' => 'locations',
        ];

        foreach ($legacy['options'] as $option) {
            if (!is_array($option)) {
                continue;
            }

            $id = isset($option['id']) ? self::sanitizeKey($option['id']) : null;
            if ($id === null) {
                continue;
            }

            $target = $optionMap[$id] ?? null;
            if ($target === null) {
                continue;
            }

            if (isset($option['messages'])) {
                $map[$target]['messages'] = self::sanitizeMessages($option['messages']);
            }

            if (isset($option['keywords'])) {
                foreach ($flow['nodes'][0]['responses'] ?? [] as &$response) {
                    if (($response['target'] ?? '') === $target) {
                        $response['keywords'] = self::sanitizeKeywords($option['keywords']);
                    }
                }
            }
        }

        if (isset($legacy['fallback']['messages'])) {
            $flow['fallback']['messages'] = self::sanitizeMessages($legacy['fallback']['messages']);
        }

        return $flow;
    }

    private static function randomKey(string $prefix): string
    {
        return $prefix . '-' . substr(uniqid('', true), 0, 8);
    }
}

