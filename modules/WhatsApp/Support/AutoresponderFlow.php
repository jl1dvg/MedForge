<?php

namespace Modules\WhatsApp\Support;

use function array_map;
use function array_merge;
use function array_splice;
use function array_unique;
use function array_values;
use function array_unshift;
use function is_array;
use function is_string;
use function in_array;
use function is_scalar;
use function mb_strlen;
use function mb_strtolower;
use function preg_replace;
use function str_starts_with;
use function trim;
use function uniqid;

class AutoresponderFlow
{
    private const BUTTON_LIMIT = 3;
    private const SCENARIO_STAGE_VALUES = [
        'arrival',
        'validation',
        'consent',
        'menu',
        'scheduling',
        'results',
        'post',
        'custom',
    ];
    private const SCENARIO_STAGE_DEFAULTS = [
        'primer_contacto' => 'arrival',
        'captura_cedula' => 'validation',
        'validar_cedula' => 'validation',
        'retorno' => 'arrival',
        'acceso_menu_directo' => 'menu',
        'fallback' => 'custom',
    ];

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
            'consent' => DataProtectionCopy::defaults($brand),
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
            'scenarios' => self::defaultScenarios($brand),
            'menu' => self::defaultMenu($brand),
            'variables' => self::defaultVariables(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function defaultScenarios(string $brand): array
    {
        $brand = trim($brand) !== '' ? $brand : 'MedForge';

        $primerContactoStage = self::defaultScenarioStage('primer_contacto');
        $capturaCedulaStage = self::defaultScenarioStage('captura_cedula');
        $validarCedulaStage = self::defaultScenarioStage('validar_cedula');
        $retornoStage = self::defaultScenarioStage('retorno');
        $accesoMenuStage = self::defaultScenarioStage('acceso_menu_directo');
        $fallbackStage = self::defaultScenarioStage('fallback');

        return [
            [
                'id' => 'primer_contacto',
                'name' => 'Primer contacto (sin consentimiento)',
                'description' => 'Saludo inicial y solicitud de autorización de datos.',
                'intercept_menu' => true,
                'stage' => $primerContactoStage,
                'stage_id' => $primerContactoStage,
                'stageId' => $primerContactoStage,
                'conditions' => [
                    ['type' => 'is_first_time', 'value' => true],
                    ['type' => 'has_consent', 'value' => false],
                ],
                'actions' => [
                    [
                        'type' => 'send_message',
                        'message' => [
                            'type' => 'text',
                            'body' => "Hola, soy el asistente virtual de {$brand} 👋. Para continuar, autoriza el uso protegido de tus datos.",
                        ],
                    ],
                    [
                        'type' => 'send_buttons',
                        'message' => [
                            'type' => 'buttons',
                            'body' => '¿Nos autorizas a usar tus datos protegidos para brindarte atención?',
                            'buttons' => [
                                ['id' => 'acepto', 'title' => 'Acepto'],
                                ['id' => 'no_acepto', 'title' => 'No acepto'],
                            ],
                        ],
                    ],
                    ['type' => 'set_state', 'state' => 'consentimiento_pendiente'],
                ],
            ],
            [
                'id' => 'captura_cedula',
                'name' => 'Captura de cédula',
                'description' => 'Gestiona la aceptación del consentimiento y solicita el identificador.',
                'intercept_menu' => true,
                'stage' => $capturaCedulaStage,
                'stage_id' => $capturaCedulaStage,
                'stageId' => $capturaCedulaStage,
                'conditions' => [
                    ['type' => 'state_is', 'value' => 'consentimiento_pendiente'],
                    ['type' => 'message_in', 'values' => ['acepto', 'si', 'sí']],
                ],
                'actions' => [
                    ['type' => 'store_consent', 'value' => true],
                    [
                        'type' => 'send_message',
                        'message' => [
                            'type' => 'text',
                            'body' => 'Gracias. Por favor, escribe tu número de cédula.',
                        ],
                    ],
                    ['type' => 'set_state', 'state' => 'esperando_cedula'],
                    ['type' => 'set_context', 'values' => ['awaiting_field' => 'cedula']],
                ],
            ],
            [
                'id' => 'validar_cedula',
                'name' => 'Validar cédula',
                'description' => 'Valida el formato y existencia del paciente.',
                'intercept_menu' => true,
                'stage' => $validarCedulaStage,
                'stage_id' => $validarCedulaStage,
                'stageId' => $validarCedulaStage,
                'conditions' => [
                    ['type' => 'state_is', 'value' => 'esperando_cedula'],
                    ['type' => 'message_matches', 'pattern' => '^\\d{6,10}$'],
                ],
                'actions' => [
                    ['type' => 'lookup_patient', 'field' => 'cedula', 'source' => 'message'],
                    [
                        'type' => 'conditional',
                        'condition' => ['type' => 'patient_found'],
                        'then' => [
                            [
                                'type' => 'send_message',
                                'message' => [
                                    'type' => 'text',
                                    'body' => 'Hola {{context.patient.full_name}} 👋',
                                ],
                            ],
                            ['type' => 'set_state', 'state' => 'menu_principal'],
                            ['type' => 'goto_menu'],
                        ],
                        'else' => [
                            ['type' => 'upsert_patient_from_context'],
                            [
                                'type' => 'send_message',
                                'message' => [
                                    'type' => 'text',
                                    'body' => 'Te registré con tu cédula y tu número. ¿Deseas continuar?',
                                ],
                            ],
                            ['type' => 'set_state', 'state' => 'menu_principal'],
                            ['type' => 'goto_menu'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'retorno',
                'name' => 'Retorno (ya conocido)',
                'description' => 'Contactos conocidos con consentimiento.',
                'intercept_menu' => true,
                'stage' => $retornoStage,
                'stage_id' => $retornoStage,
                'stageId' => $retornoStage,
                'conditions' => [
                    ['type' => 'is_first_time', 'value' => false],
                    ['type' => 'has_consent', 'value' => true],
                ],
                'actions' => [
                    [
                        'type' => 'send_message',
                        'message' => [
                            'type' => 'text',
                            'body' => 'Hola {{context.patient.full_name}} 👋, ¿en qué puedo ayudarte hoy?',
                        ],
                    ],
                    ['type' => 'goto_menu'],
                ],
            ],
            [
                'id' => 'acceso_menu_directo',
                'name' => 'Acceso directo al menú',
                'description' => 'Permite abrir el menú cuando el contacto escribe un atajo como "menu" u "hola".',
                'intercept_menu' => true,
                'stage' => $accesoMenuStage,
                'stage_id' => $accesoMenuStage,
                'stageId' => $accesoMenuStage,
                'conditions' => [
                    ['type' => 'has_consent', 'value' => true],
                    ['type' => 'message_in', 'values' => self::menuKeywords()],
                ],
                'actions' => [
                    ['type' => 'set_state', 'state' => 'menu_principal'],
                    ['type' => 'goto_menu'],
                ],
            ],
            [
                'id' => 'fallback',
                'name' => 'Fallback',
                'description' => 'Cuando ninguna regla aplica.',
                'stage' => $fallbackStage,
                'stage_id' => $fallbackStage,
                'stageId' => $fallbackStage,
                'conditions' => [
                    ['type' => 'always'],
                ],
                'actions' => [
                    [
                        'type' => 'send_message',
                        'message' => [
                            'type' => 'text',
                            'body' => 'No te entendí. Escribe menú para ver opciones.',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultMenu(string $brand): array
    {
        $brand = trim($brand) !== '' ? $brand : 'MedForge';

        return [
            'title' => 'Menú principal',
            'message' => [
                'type' => 'buttons',
                'body' => "Soy el asistente virtual de {$brand}. Selecciona una opción:",
                'buttons' => [
                    ['id' => 'menu_agendar', 'title' => 'Agendar cita'],
                    ['id' => 'menu_resultados', 'title' => 'Resultados'],
                    ['id' => 'menu_facturacion', 'title' => 'Facturación'],
                ],
            ],
            'options' => [
                [
                    'id' => 'menu_agendar',
                    'title' => 'Agendar cita',
                    'keywords' => ['agendar', 'cita', 'menu_agendar', '1'],
                    'actions' => [
                        [
                            'type' => 'send_message',
                            'message' => [
                                'type' => 'text',
                                'body' => 'Para agendar una cita responde con la especialidad que necesitas o escribe "agente" para asistencia humana.',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'menu_resultados',
                    'title' => 'Resultados',
                    'keywords' => ['resultados', 'examenes', 'menu_resultados', '2'],
                    'actions' => [
                        [
                            'type' => 'send_message',
                            'message' => [
                                'type' => 'text',
                                'body' => 'Si deseas tus resultados, indícanos tu número de historia clínica y el área que necesita revisar.',
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'menu_facturacion',
                    'title' => 'Facturación',
                    'keywords' => ['facturacion', 'factura', 'menu_facturacion', '3'],
                    'actions' => [
                        [
                            'type' => 'send_message',
                            'message' => [
                                'type' => 'text',
                                'body' => 'Nuestro equipo de facturación puede ayudarte. Comparte el número de factura o paciente para continuar.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function defaultVariables(): array
    {
        return [
            'cedula' => [
                'label' => 'Cédula',
                'source' => 'context.cedula',
                'persist' => true,
            ],
            'telefono' => [
                'label' => 'Teléfono',
                'source' => 'session.wa_number',
                'persist' => true,
            ],
            'nombre' => [
                'label' => 'Nombre completo',
                'source' => 'patient.full_name',
                'persist' => false,
            ],
            'consentimiento' => [
                'label' => 'Consentimiento',
                'source' => 'context.consent',
                'persist' => true,
            ],
            'estado' => [
                'label' => 'Estado',
                'source' => 'context.state',
                'persist' => false,
            ],
        ];
    }

    /**
     * @param mixed $scenarios
     * @return array<int, array<string, mixed>>
     */
    private static function sanitizeScenarios($scenarios, string $brand): array
    {
        if (!is_array($scenarios)) {
            return self::defaultScenarios($brand);
        }

        $normalized = [];
        foreach ($scenarios as $index => $scenario) {
            if (!is_array($scenario)) {
                continue;
            }

            $normalized[] = self::sanitizeScenario($scenario, $index);
        }

        if (empty($normalized)) {
            return self::defaultScenarios($brand);
        }

        $normalized = self::ensureRequiredScenarios($normalized, $brand);

        return array_values($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private static function sanitizeScenario(array $scenario, int $index): array
    {
        $id = isset($scenario['id']) ? self::sanitizeKey((string) $scenario['id']) : '';
        if ($id === '') {
            $id = 'scenario_' . ($index + 1);
        }

        $name = self::sanitizeLine((string) ($scenario['name'] ?? ''));
        if ($name === '') {
            $name = 'Escenario ' . ($index + 1);
        }

        $description = self::sanitizeLine((string) ($scenario['description'] ?? ''));

        $conditions = self::sanitizeScenarioConditions($scenario['conditions'] ?? []);
        $conditions = self::enforceScenarioGuards($id, $conditions);
        if (empty($conditions)) {
            $conditions = [['type' => 'always']];
        }

        $actions = self::sanitizeScenarioActions($scenario['actions'] ?? []);

        $interceptMenu = $scenario['intercept_menu'] ?? $scenario['interceptMenu'] ?? null;
        if ($interceptMenu === null) {
            $interceptMenu = self::shouldInterceptMenuByDefault($id);
        }

        $stageValue = $scenario['stage'] ?? $scenario['stage_id'] ?? $scenario['stageId'] ?? null;
        $stage = self::sanitizeScenarioStage($stageValue, $id);

        return [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'conditions' => $conditions,
            'actions' => $actions,
            'intercept_menu' => (bool) $interceptMenu,
            'stage' => $stage,
            'stage_id' => $stage,
            'stageId' => $stage,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $conditions
     * @return array<int, array<string, mixed>>
     */
    private static function enforceScenarioGuards(string $scenarioId, array $conditions): array
    {
        switch ($scenarioId) {
            case 'primer_contacto':
                $conditions = self::ensureCondition($conditions, [
                    'type' => 'is_first_time',
                    'value' => true,
                ]);
                $conditions = self::ensureCondition($conditions, [
                    'type' => 'has_consent',
                    'value' => false,
                ]);
                break;
            case 'captura_cedula':
                $conditions = self::ensureCondition($conditions, [
                    'type' => 'state_is',
                    'value' => 'consentimiento_pendiente',
                ]);
                break;
            case 'validar_cedula':
                $conditions = self::ensureCondition($conditions, [
                    'type' => 'state_is',
                    'value' => 'esperando_cedula',
                ]);
                break;
        }

        return $conditions;
    }

    /**
     * @param array<int, array<string, mixed>> $conditions
     * @param array<string, mixed> $required
     * @return array<int, array<string, mixed>>
     */
    private static function ensureCondition(array $conditions, array $required): array
    {
        $type = $required['type'] ?? null;
        if (!is_string($type) || $type === '') {
            return $conditions;
        }

        foreach ($conditions as $condition) {
            if (($condition['type'] ?? null) !== $type) {
                continue;
            }

            if (array_key_exists('value', $required)) {
                if (($condition['value'] ?? null) === $required['value']) {
                    return $conditions;
                }
                continue;
            }

            if (array_key_exists('values', $required)) {
                if (($condition['values'] ?? null) === $required['values']) {
                    return $conditions;
                }
                continue;
            }

            return $conditions;
        }

        array_unshift($conditions, $required);

        return $conditions;
    }

    private static function sanitizeScenarioStage($value, string $scenarioId): string
    {
        if ($value === null) {
            $value = null;
        }
        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));
            if (in_array($normalized, self::SCENARIO_STAGE_VALUES, true)) {
                return $normalized;
            }
        }

        return self::defaultScenarioStage($scenarioId);
    }

    private static function defaultScenarioStage(string $scenarioId): string
    {
        $scenarioId = self::sanitizeKey($scenarioId);
        if ($scenarioId === '') {
            return 'custom';
        }

        return self::SCENARIO_STAGE_DEFAULTS[$scenarioId] ?? 'custom';
    }

    private static function shouldInterceptMenuByDefault(string $id): bool
    {
        return in_array($id, [
            'primer_contacto',
            'captura_cedula',
            'validar_cedula',
            'retorno',
            'acceso_menu_directo',
        ], true);
    }

    /**
     * @param mixed $conditions
     * @return array<int, array<string, mixed>>
     */
    private static function sanitizeScenarioConditions($conditions): array
    {
        if (!is_array($conditions)) {
            return [];
        }

        $normalized = [];

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            $type = self::sanitizeKey((string) ($condition['type'] ?? ''));
            if ($type === '') {
                $type = 'always';
            }

            $entry = ['type' => $type];

            switch ($type) {
                case 'always':
                    break;
                case 'is_first_time':
                case 'has_consent':
                    $entry['value'] = (bool) ($condition['value'] ?? false);
                    break;
                case 'state_is':
                    $entry['value'] = self::sanitizeKey((string) ($condition['value'] ?? ''));
                    break;
                case 'awaiting_is':
                    $entry['value'] = self::sanitizeKey((string) ($condition['value'] ?? ''));
                    break;
                case 'message_in':
                    $values = [];
                    foreach (($condition['values'] ?? []) as $value) {
                        if (!is_string($value)) {
                            continue;
                        }
                        $clean = mb_strtolower(self::sanitizeLine($value));
                        if ($clean !== '') {
                            $values[] = $clean;
                        }
                    }
                    if (!empty($values)) {
                        $entry['values'] = array_values(array_unique($values));
                    }
                    break;
                case 'message_contains':
                    $keywords = [];
                    foreach (($condition['keywords'] ?? []) as $value) {
                        if (!is_string($value)) {
                            continue;
                        }
                        $clean = mb_strtolower(self::sanitizeLine($value));
                        if ($clean !== '') {
                            $keywords[] = $clean;
                        }
                    }
                    if (!empty($keywords)) {
                        $entry['keywords'] = array_values(array_unique($keywords));
                    }
                    break;
                case 'message_matches':
                    $pattern = trim((string) ($condition['pattern'] ?? ''));
                    if ($pattern !== '') {
                        $entry['pattern'] = $pattern;
                    }
                    break;
                case 'last_interaction_gt':
                    $minutes = (int) ($condition['minutes'] ?? 0);
                    if ($minutes > 0) {
                        $entry['minutes'] = $minutes;
                    }
                    break;
                case 'patient_found':
                case 'identifier_exists':
                case 'context_flag':
                    if (isset($condition['key'])) {
                        $entry['key'] = self::sanitizeKey((string) $condition['key']);
                    }
                    $entry['value'] = $condition['value'] ?? true;
                    break;
                default:
                    $entry['type'] = 'custom';
                    $entry['key'] = self::sanitizeKey((string) ($condition['key'] ?? ''));
                    $entry['value'] = $condition['value'] ?? null;
                    break;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param mixed $actions
     * @return array<int, array<string, mixed>>
     */
    private static function sanitizeScenarioActions($actions): array
    {
        if (!is_array($actions)) {
            return [];
        }

        $normalized = [];

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }

            $clean = self::sanitizeScenarioAction($action);
            if (!empty($clean)) {
                $normalized[] = $clean;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private static function sanitizeScenarioAction(array $action): array
    {
        $type = self::sanitizeKey((string) ($action['type'] ?? ''));
        if ($type === '') {
            return [];
        }

        $payload = ['type' => $type];

        if (in_array($type, ['send_message', 'send_buttons', 'send_list'], true)) {
            $message = $action['message'] ?? null;
            if (is_string($message)) {
                $message = ['type' => 'text', 'body' => $message];
            }
            $messages = self::sanitizeMessages([$message]);
            if (empty($messages)) {
                return [];
            }
            $payload['message'] = $messages[0];

            return $payload;
        }

        if ($type === 'send_sequence') {
            $messages = self::sanitizeMessages($action['messages'] ?? []);
            if (empty($messages)) {
                return [];
            }
            $payload['messages'] = $messages;

            return $payload;
        }

        if ($type === 'send_template') {
            $template = isset($action['template']) && is_array($action['template'])
                ? self::sanitizeTemplateMessage($action['template'])
                : [];
            if (empty($template)) {
                return [];
            }
            $payload['template'] = $template;

            return $payload;
        }

        if ($type === 'set_state') {
            $payload['state'] = self::sanitizeKey((string) ($action['state'] ?? ''));

            return $payload;
        }

        if ($type === 'set_context') {
            $values = [];
            foreach (($action['values'] ?? []) as $key => $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $cleanKey = self::sanitizeKey((string) $key);
                if ($cleanKey === '') {
                    continue;
                }
                $values[$cleanKey] = (string) $value;
            }
            if (!empty($values)) {
                $payload['values'] = $values;
            }

            return $payload;
        }

        if (in_array($type, ['store_consent', 'lookup_patient', 'goto_menu', 'upsert_patient_from_context', 'handoff_agent'], true)) {
            if ($type === 'store_consent') {
                $payload['value'] = (bool) ($action['value'] ?? true);
            }
            if ($type === 'lookup_patient') {
                $payload['field'] = self::sanitizeKey((string) ($action['field'] ?? 'cedula'));
                $source = self::sanitizeKey((string) ($action['source'] ?? 'message'));
                if (!in_array($source, ['message', 'context'], true)) {
                    $source = 'message';
                }
                $payload['source'] = $source;
            }

            return $payload;
        }

        if ($type === 'conditional') {
            $condition = self::sanitizeScenarioConditions([$action['condition'] ?? []]);
            if (empty($condition)) {
                return [];
            }
            $payload['condition'] = $condition[0];
            $payload['then'] = self::sanitizeScenarioActions($action['then'] ?? []);
            $payload['else'] = self::sanitizeScenarioActions($action['else'] ?? []);

            return $payload;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private static function mergeMenu(array $base, array $override): array
    {
        $menu = $base;

        if (isset($override['title']) && is_string($override['title'])) {
            $title = self::sanitizeLine($override['title']);
            if ($title !== '') {
                $menu['title'] = $title;
            }
        }

        if (isset($override['message'])) {
            $message = $override['message'];
            if (is_string($message)) {
                $message = ['type' => 'text', 'body' => $message];
            }
            $messages = self::sanitizeMessages([$message]);
            if (!empty($messages)) {
                $menu['message'] = $messages[0];
            }
        }

        if (isset($override['options'])) {
            $menu['options'] = self::sanitizeMenuOptions($override['options']);
        }

        if (isset($override['fallback']) && is_array($override['fallback'])) {
            $fallbackMessages = self::sanitizeMessages($override['fallback']['messages'] ?? []);
            if (!empty($fallbackMessages)) {
                $menu['fallback'] = ['messages' => $fallbackMessages];
            }
        }

        return $menu;
    }

    /**
     * @param array<string, mixed> $flow
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private static function hydrateLegacySections(array $flow, array $defaults): array
    {
        $flow = self::ensureMenuBackfills($flow, $defaults);

        if (!isset($flow['entry']) || !is_array($flow['entry'])) {
            $flow['entry'] = $defaults['entry'];
        }

        if (!isset($flow['fallback']) || !is_array($flow['fallback'])) {
            $flow['fallback'] = $defaults['fallback'];
        }

        if (!isset($flow['options']) || !is_array($flow['options']) || empty($flow['options'])) {
            $flow['options'] = $defaults['options'];
        }

        return $flow;
    }

    /**
     * @param array<string, mixed> $flow
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private static function ensureMenuBackfills(array $flow, array $defaults): array
    {
        $menu = isset($flow['menu']) && is_array($flow['menu']) ? $flow['menu'] : [];

        if ((!isset($flow['entry']) || !is_array($flow['entry'])) && !empty($menu)) {
            $entry = self::deriveEntryFromMenu($menu, $defaults['entry']);
            if (!empty($entry)) {
                $flow['entry'] = $entry;
            }
        }

        if ((!isset($flow['options']) || !is_array($flow['options']) || empty($flow['options'])) && !empty($menu)) {
            $options = self::deriveOptionsFromMenu($menu['options'] ?? [], $defaults['options']);
            if (!empty($options)) {
                $flow['options'] = $options;
            }
        }

        if ((!isset($flow['fallback']) || !is_array($flow['fallback'])) && !empty($menu)) {
            $fallback = self::deriveFallbackFromMenu($menu, $defaults['fallback']);
            if (!empty($fallback)) {
                $flow['fallback'] = $fallback;
            }
        }

        return $flow;
    }

    /**
     * @param array<string, mixed> $menu
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private static function deriveEntryFromMenu(array $menu, array $defaults): array
    {
        $entry = [];

        if (isset($menu['title']) && is_string($menu['title'])) {
            $title = self::sanitizeLine($menu['title']);
            if ($title !== '') {
                $entry['title'] = $title;
            }
        }

        if (isset($menu['message'])) {
            $messages = self::sanitizeMessages([$menu['message']]);
            if (!empty($messages)) {
                $entry['messages'] = $messages;
            }
        }

        if (isset($menu['keywords'])) {
            $keywords = self::sanitizeKeywords($menu['keywords']);
            if (!empty($keywords)) {
                $entry['keywords'] = $keywords;
            }
        }

        if (empty($entry['messages'])) {
            return [];
        }

        if (!isset($entry['description']) && isset($defaults['description'])) {
            $entry['description'] = $defaults['description'];
        }

        return $entry;
    }

    /**
     * @param mixed $options
     * @param array<int, array<string, mixed>> $defaults
     * @return array<int, array<string, mixed>>
     */
    private static function deriveOptionsFromMenu($options, array $defaults): array
    {
        if (!is_array($options)) {
            return [];
        }

        $derived = [];

        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }

            $id = self::sanitizeKey((string) ($option['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $entry = ['id' => $id];

            if (isset($option['title']) && is_string($option['title'])) {
                $title = self::sanitizeLine($option['title']);
                if ($title !== '') {
                    $entry['title'] = $title;
                }
            }

            if (isset($option['keywords'])) {
                $keywords = self::sanitizeKeywords($option['keywords']);
                if (!empty($keywords)) {
                    $entry['keywords'] = $keywords;
                }
            }

            if (isset($option['followup']) && is_string($option['followup'])) {
                $followup = self::sanitizeLine($option['followup']);
                if ($followup !== '') {
                    $entry['followup'] = $followup;
                }
            }

            $messages = self::extractMessagesFromActions($option['actions'] ?? []);
            if (empty($messages) && isset($option['messages'])) {
                $messages = self::sanitizeMessages($option['messages']);
            }

            if (!empty($messages)) {
                $entry['messages'] = $messages;
            }

            $derived[] = $entry;
        }

        if (!empty($derived)) {
            return $derived;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $menu
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private static function deriveFallbackFromMenu(array $menu, array $defaults): array
    {
        if (!isset($menu['fallback']) || !is_array($menu['fallback'])) {
            return [];
        }

        $fallback = [];
        $messages = self::sanitizeMessages($menu['fallback']['messages'] ?? []);
        if (!empty($messages)) {
            $fallback['messages'] = $messages;
        }

        if (isset($menu['fallback']['title']) && is_string($menu['fallback']['title'])) {
            $title = self::sanitizeLine($menu['fallback']['title']);
            if ($title !== '') {
                $fallback['title'] = $title;
            }
        }

        if (isset($menu['fallback']['description']) && is_string($menu['fallback']['description'])) {
            $description = self::sanitizeLine($menu['fallback']['description']);
            if ($description !== '') {
                $fallback['description'] = $description;
            }
        }

        if (empty($fallback['messages'])) {
            return [];
        }

        return $fallback;
    }

    /**
     * @param mixed $actions
     * @return array<int, array<string, mixed>>
     */
    private static function extractMessagesFromActions($actions): array
    {
        if (!is_array($actions)) {
            return [];
        }

        $messages = [];
        foreach (self::sanitizeScenarioActions($actions) as $action) {
            $type = $action['type'] ?? '';

            if (in_array($type, ['send_message', 'send_buttons', 'send_list'], true)) {
                if (isset($action['message']) && is_array($action['message'])) {
                    $messages[] = $action['message'];
                }
                continue;
            }

            if ($type === 'send_sequence') {
                foreach ($action['messages'] ?? [] as $message) {
                    if (is_array($message)) {
                        $messages[] = $message;
                    }
                }
                continue;
            }

            if ($type === 'send_template' && isset($action['template']) && is_array($action['template'])) {
                $messages[] = ['type' => 'template', 'template' => $action['template']];
            }
        }

        return $messages;
    }

    /**
     * @param array<string, mixed>|null $section
     */
    private static function hasValidMessages(?array $section): bool
    {
        if (!is_array($section)) {
            return false;
        }

        if (empty($section['messages']) || !is_array($section['messages'])) {
            return false;
        }

        return count($section['messages']) > 0;
    }

    /**
     * @param mixed $options
     * @return array<int, array<string, mixed>>
     */
    private static function sanitizeMenuOptions($options): array
    {
        if (!is_array($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }

            $id = self::sanitizeKey((string) ($option['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $title = self::sanitizeLine((string) ($option['title'] ?? ''));
            if ($title === '') {
                $title = ucfirst(str_replace('_', ' ', $id));
            }

            $keywords = self::sanitizeKeywords($option['keywords'] ?? []);
            if (empty($keywords)) {
                $keywords = [$id];
            }

            $actions = self::sanitizeScenarioActions($option['actions'] ?? []);

            $normalized[] = [
                'id' => $id,
                'title' => $title,
                'keywords' => $keywords,
                'actions' => $actions,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $scenarios
     * @return array<int, array<string, mixed>>
     */
    private static function ensureRequiredScenarios(array $scenarios, string $brand): array
    {
        $defaults = self::defaultScenarios($brand);

        $scenarios = self::injectDefaultScenario($scenarios, $defaults, 'fallback');
        $scenarios = self::injectDefaultScenario($scenarios, $defaults, 'acceso_menu_directo', 'fallback');

        return $scenarios;
    }

    /**
     * @param array<int, array<string, mixed>> $scenarios
     * @param array<int, array<string, mixed>> $defaults
     * @return array<int, array<string, mixed>>
     */
    private static function injectDefaultScenario(
        array $scenarios,
        array $defaults,
        string $identifier,
        ?string $before = null
    ): array {
        foreach ($scenarios as $scenario) {
            if (($scenario['id'] ?? '') === $identifier) {
                return $scenarios;
            }
        }

        $fallback = null;
        foreach ($defaults as $scenario) {
            if (($scenario['id'] ?? '') === $identifier) {
                $fallback = $scenario;
                break;
            }
        }

        if ($fallback === null) {
            return $scenarios;
        }

        if ($before !== null) {
            foreach ($scenarios as $index => $scenario) {
                if (($scenario['id'] ?? '') === $before) {
                    array_splice($scenarios, $index, 0, [$fallback]);

                    return $scenarios;
                }
            }
        }

        $scenarios[] = $fallback;

        return $scenarios;
    }

    /**
     * @param mixed $variables
     * @return array<string, array<string, mixed>>
     */
    private static function sanitizeVariables($variables): array
    {
        if (!is_array($variables)) {
            return self::defaultVariables();
        }

        $allowedSources = [
            'context.cedula',
            'context.state',
            'context.consent',
            'context.awaiting_field',
            'session.wa_number',
            'patient.full_name',
            'patient.hc_number',
        ];

        $normalized = [];
        foreach ($variables as $key => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $cleanKey = self::sanitizeKey((string) $key);
            if ($cleanKey === '') {
                continue;
            }

            $label = self::sanitizeLine((string) ($definition['label'] ?? ''));
            if ($label === '') {
                $label = ucfirst(str_replace('_', ' ', $cleanKey));
            }

            $source = (string) ($definition['source'] ?? '');
            if ($source === '' || (!in_array($source, $allowedSources, true) && !str_starts_with($source, 'context.'))) {
                $source = 'context.' . $cleanKey;
            }

            $persist = (bool) ($definition['persist'] ?? false);

            $normalized[$cleanKey] = [
                'label' => $label,
                'source' => $source,
                'persist' => $persist,
            ];
        }

        if (empty($normalized)) {
            return self::defaultVariables();
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function resolve(string $brand, array $overrides = []): array
    {
        $defaults = self::defaultConfig($brand);
        if (empty($overrides)) {
            $defaults['consent'] = DataProtectionCopy::defaults($brand);

            return self::finalize($defaults);
        }

        $overrides = self::hydrateLegacySections($overrides, $defaults);

        if (isset($overrides['entry']) && is_array($overrides['entry'])) {
            $defaults['entry'] = self::mergeSection($defaults['entry'], $overrides['entry']);
        }

        if (isset($overrides['options']) && is_array($overrides['options'])) {
            $defaults['options'] = self::mergeOptions($defaults['options'], $overrides['options']);
        }

        if (isset($overrides['fallback']) && is_array($overrides['fallback'])) {
            $defaults['fallback'] = self::mergeSection($defaults['fallback'], $overrides['fallback']);
        }

        if (isset($overrides['scenarios']) && is_array($overrides['scenarios'])) {
            $defaults['scenarios'] = self::sanitizeScenarios($overrides['scenarios'], $brand);
        }

        if (isset($overrides['menu']) && is_array($overrides['menu'])) {
            $defaults['menu'] = self::mergeMenu($defaults['menu'], $overrides['menu']);
        }

        if (isset($overrides['variables']) && is_array($overrides['variables'])) {
            $defaults['variables'] = self::sanitizeVariables($overrides['variables']);
        }

        $defaults['consent'] = DataProtectionCopy::sanitize($overrides['consent'] ?? [], $brand);

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

        $flow = self::hydrateLegacySections($flow, $defaults);

        if (!self::hasValidMessages($flow['entry'] ?? null)) {
            $errors[] = 'Falta la configuración del mensaje de bienvenida.';
        }

        if (!self::hasValidMessages($flow['fallback'] ?? null)) {
            $errors[] = 'Falta la configuración del mensaje de fallback.';
        }

        if (empty($flow['options']) || !is_array($flow['options'])) {
            $errors[] = 'Debes definir al menos una opción del menú.';
        }

        if (!empty($errors)) {
            return ['flow' => $defaults, 'resolved' => self::finalize($defaults), 'errors' => $errors];
        }

        $resolved = $defaults;
        $resolved['entry'] = self::mergeSection($defaults['entry'], $flow['entry']);
        $resolved['fallback'] = self::mergeSection($defaults['fallback'], $flow['fallback']);
        $resolved['options'] = self::mergeOptions($defaults['options'], $flow['options']);
        $resolved['consent'] = DataProtectionCopy::sanitize($flow['consent'] ?? [], $brand);
        $resolved['scenarios'] = self::sanitizeScenarios($flow['scenarios'] ?? $defaults['scenarios'], $brand);
        $resolved['menu'] = self::mergeMenu($defaults['menu'], $flow['menu'] ?? []);
        $resolved['variables'] = self::sanitizeVariables($flow['variables'] ?? $defaults['variables']);

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
            'consent' => $resolved['consent'] ?? DataProtectionCopy::defaults($brand),
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

            if (!in_array($type, ['text', 'buttons', 'list', 'template', 'image', 'document', 'location'], true)) {
                $type = 'text';
            }

            if ($type === 'template') {
                $entry = self::sanitizeTemplateMessage($message);
                if (!empty($entry)) {
                    $normalized[] = $entry;
                }

                continue;
            }

            if ($type === 'image' || $type === 'document') {
                $link = self::sanitizeUrl($message['link'] ?? '');
                if ($link === '') {
                    continue;
                }

                $entry = [
                    'type' => $type,
                    'link' => $link,
                ];

                if (isset($message['caption']) && is_string($message['caption'])) {
                    $caption = self::sanitizeMultiline($message['caption']);
                    if ($caption !== '') {
                        $entry['caption'] = $caption;
                    }
                }

                if ($type === 'document' && isset($message['filename']) && is_string($message['filename'])) {
                    $filename = self::sanitizeLine($message['filename']);
                    if ($filename !== '') {
                        $entry['filename'] = $filename;
                    }
                }

                $normalized[] = $entry;

                continue;
            }

            if ($type === 'location') {
                $latitude = isset($message['latitude']) ? (float) $message['latitude'] : null;
                $longitude = isset($message['longitude']) ? (float) $message['longitude'] : null;

                if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                    continue;
                }

                $entry = [
                    'type' => 'location',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];

                if (isset($message['name']) && is_string($message['name'])) {
                    $name = self::sanitizeLine($message['name']);
                    if ($name !== '') {
                        $entry['name'] = $name;
                    }
                }

                if (isset($message['address']) && is_string($message['address'])) {
                    $address = self::sanitizeMultiline($message['address']);
                    if ($address !== '') {
                        $entry['address'] = $address;
                    }
                }

                $normalized[] = $entry;

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

                        $rowTitle = isset($row['title']) ? self::sanitizeLine($row['title']) : '';
                        if ($rowTitle === '') {
                            continue;
                        }

                        $id = isset($row['id']) ? self::sanitizeKey($row['id']) : '';
                        if ($id === '') {
                            $id = self::slugify($rowTitle);
                        }

                        if ($id === '') {
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

    private static function sanitizeUrl($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

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
