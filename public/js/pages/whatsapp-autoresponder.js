(function () {
    const form = document.querySelector('[data-autoresponder-form]');
    if (!form) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                const readyForm = document.querySelector('[data-autoresponder-form]');
                if (readyForm) {
                    initializeAutoresponder(readyForm);
                }
            });
        }

        return;
    }

    initializeAutoresponder(form);

    function initializeAutoresponder(form) {
    const flowField = document.getElementById('flow_payload');
    const validationAlert = form.querySelector('[data-validation-errors]');
    const flowBootstrap = form.querySelector('[data-flow-bootstrap]');
    const templateCatalogInput = form.querySelector('[data-template-catalog]');

    const templates = {
        variableRow: document.getElementById('variable-row-template'),
        scenarioCard: document.getElementById('scenario-card-template'),
        conditionRow: document.getElementById('condition-row-template'),
        actionRow: document.getElementById('action-row-template'),
        menuOption: document.getElementById('menu-option-template'),
        buttonRow: document.getElementById('button-row-template'),
        contextRow: document.getElementById('context-row-template'),
        menuListSection: document.getElementById('menu-list-section-template'),
        menuListRow: document.getElementById('menu-list-row-template'),
    };

    const MENU_BUTTON_LIMIT = 3;
    const MENU_LIST_SECTION_LIMIT = 10;
    const MENU_LIST_ROW_LIMIT = 10;

    const VARIABLE_SOURCES = [
        {value: 'context.cedula', label: 'Última cédula ingresada'},
        {value: 'context.state', label: 'Estado actual del flujo'},
        {value: 'context.consent', label: 'Consentimiento registrado'},
        {value: 'context.awaiting_field', label: 'Campo pendiente'},
        {value: 'session.wa_number', label: 'Número de WhatsApp'},
        {value: 'patient.full_name', label: 'Nombre del paciente'},
        {value: 'patient.hc_number', label: 'Historia clínica del paciente'},
    ];

    const CONDITION_OPTIONS = [
        {value: 'always', label: 'Siempre', help: 'Se ejecuta sin validar datos adicionales.'},
        {value: 'is_first_time', label: 'Es primera vez', input: 'boolean', help: 'Evalúa si la conversación aún no tiene historial registrado.'},
        {value: 'has_consent', label: 'Tiene consentimiento', input: 'boolean', help: 'Valida si el paciente aceptó la protección de datos.'},
        {value: 'state_is', label: 'Estado actual es', input: 'text', placeholder: 'menu_principal', help: 'Útil para continuar flujos según el estado guardado en contexto.'},
        {value: 'awaiting_is', label: 'Campo pendiente es', input: 'text', placeholder: 'cedula', help: 'Detecta si estás esperando un dato específico del paciente.'},
        {value: 'message_in', label: 'Mensaje coincide con lista', input: 'keywords', placeholder: 'acepto, si, sí', help: 'Compara el mensaje normalizado con un listado exacto de palabras clave.'},
        {value: 'message_contains', label: 'Mensaje contiene', input: 'keywords', placeholder: 'menu, ayuda', help: 'Busca palabras o frases dentro del mensaje recibido sin importar el orden.'},
        {value: 'message_matches', label: 'Mensaje coincide con regex', input: 'pattern', placeholder: '^\\\d{10}$', help: 'Aplica una expresión regular, ideal para validar formatos como cédulas.'},
        {value: 'last_interaction_gt', label: 'Última interacción mayor a (minutos)', input: 'number', help: 'Comprueba la inactividad antes de enviar recordatorios automáticos.'},
        {value: 'patient_found', label: 'Paciente localizado', input: 'boolean', help: 'Verifica si la búsqueda de paciente devolvió un registro.'},
    ];

    const ACTION_OPTIONS = [
        {value: 'send_message', label: 'Enviar mensaje o multimedia', help: 'Entrega un mensaje simple, imagen, documento o ubicación.'},
        {value: 'send_sequence', label: 'Enviar secuencia de mensajes', help: 'Combina varios mensajes consecutivos en una sola acción.'},
        {value: 'send_buttons', label: 'Enviar botones', help: 'Presenta hasta tres botones interactivos para guiar la respuesta.'},
        {value: 'send_list', label: 'Enviar lista interactiva', help: 'Muestra un menú desplegable con secciones y múltiples opciones.'},
        {value: 'send_template', label: 'Enviar plantilla aprobada', help: 'Usa una plantilla autorizada por Meta con variables predefinidas.'},
        {value: 'set_state', label: 'Actualizar estado', help: 'Actualiza el estado del flujo para controlar próximos pasos.'},
        {value: 'set_context', label: 'Guardar en contexto', help: 'Almacena pares clave-valor disponibles en mensajes futuros.'},
        {value: 'store_consent', label: 'Guardar consentimiento', help: 'Registra si el paciente aceptó o rechazó la autorización.'},
        {value: 'lookup_patient', label: 'Validar cédula en BD', help: 'Busca al paciente usando la cédula o historia clínica proporcionada.'},
        {value: 'conditional', label: 'Condicional', help: 'Divide el flujo en acciones alternativas según una condición.'},
        {value: 'goto_menu', label: 'Redirigir al menú', help: 'Envía nuevamente el mensaje de menú configurado más abajo.'},
        {value: 'upsert_patient_from_context', label: 'Guardar paciente con datos actuales', help: 'Crea o actualiza el paciente con los datos capturados en contexto.'},
    ];

    const SCENARIO_STAGE_OPTIONS = [
        {value: 'arrival', label: 'Llegada y saludo', description: 'Primer contacto automático con el paciente.'},
        {value: 'validation', label: 'Validación de identidad', description: 'Captura y verificación de datos de identificación.'},
        {value: 'consent', label: 'Consentimiento', description: 'Solicita y registra la autorización de tratamiento de datos.'},
        {value: 'menu', label: 'Menú principal', description: 'Opciones para que el paciente elija su próxima acción.'},
        {value: 'scheduling', label: 'Agendamiento', description: 'Flujos para coordinar o reagendar citas.'},
        {value: 'results', label: 'Entrega de resultados', description: 'Comunica resultados de exámenes o consultas.'},
        {value: 'post', label: 'Seguimiento post consulta', description: 'Recordatorios, encuestas y mensajes posteriores.'},
        {value: 'custom', label: 'Personalizado', description: 'Escenarios especiales o adicionales.'},
    ];

    const SIMPLE_ACTION_TYPES = new Set(['send_message', 'send_buttons', 'lookup_patient', 'store_consent', 'goto_menu']);
    const ADVANCED_ACTION_TYPES = new Set(['send_sequence', 'send_list', 'send_template', 'set_context', 'conditional', 'upsert_patient_from_context']);
    const BASIC_CONDITION_TYPES = new Set(['always', 'message_in', 'message_contains']);

    const STAGE_VALUE_SET = new Set(SCENARIO_STAGE_OPTIONS.map((option) => option.value));

    const STORAGE_KEYS = {
        simpleMode: 'waAutoresponder.simpleMode',
        expandedScenarios: 'waAutoresponder.expandedScenarios',
    };

    let cachedStorage = null;
    let storageEvaluated = false;

    const uiState = {
        simpleMode: loadSimpleModePreference(),
        expandedScenarios: new Set(loadExpandedScenariosPreference()),
    };

    const MENU_PRESETS = [
        {
            id: 'general',
            label: 'Menú general de atención',
            description: 'Ofrece opciones para agendar, conocer resultados y hablar con un agente.',
            menu: {
                message: {
                    type: 'list',
                    body: '¿En qué podemos ayudarte hoy?',
                    button: 'Ver opciones',
                    footer: 'Selecciona una opción para continuar.',
                    sections: [
                        {
                            title: 'Servicios disponibles',
                            rows: [
                                {id: 'menu_agendar', title: 'Agendar cita', description: 'Te guiamos paso a paso'},
                                {id: 'menu_resultados', title: 'Resultados de exámenes', description: 'Consulta tus últimos informes'},
                                {id: 'menu_agente', title: 'Hablar con un agente', description: 'Un asesor continuará la conversación'},
                            ],
                        },
                    ],
                },
                options: [
                    {
                        id: 'menu_agendar',
                        title: 'Agendar cita',
                        keywords: ['agendar', 'cita', 'agendamiento'],
                        actions: [
                            {type: 'set_state', state: 'agendar_cita'},
                            {type: 'send_message', message: {type: 'text', body: 'Perfecto, empecemos con tu agendamiento. ¿Puedes indicarme tu número de identificación?'}},
                        ],
                    },
                    {
                        id: 'menu_resultados',
                        title: 'Resultados de exámenes',
                        keywords: ['resultado', 'examen', 'laboratorio'],
                        actions: [
                            {type: 'send_message', message: {type: 'text', body: 'Para consultar tus resultados necesitamos validar tu identidad. Indícanos tu número de identificación.'}},
                        ],
                    },
                    {
                        id: 'menu_agente',
                        title: 'Hablar con un agente',
                        keywords: ['agente', 'asesor', 'humano'],
                        actions: [
                            {type: 'send_message', message: {type: 'text', body: 'Te pondremos en contacto con un agente humano. Por favor espera un momento.'}},
                            {type: 'goto_menu'},
                        ],
                    },
                ],
            },
        },
        {
            id: 'seguimiento',
            label: 'Seguimiento post consulta',
            description: 'Dirige a los pacientes a reagendamiento, soporte o encuesta de satisfacción.',
            menu: {
                message: {
                    type: 'buttons',
                    body: 'Gracias por tu visita, ¿qué deseas hacer a continuación?',
                    buttons: [
                        {id: 'menu_reagendar', title: 'Reagendar'},
                        {id: 'menu_soporte', title: 'Soporte'},
                        {id: 'menu_encuesta', title: 'Encuesta'},
                    ],
                },
                options: [
                    {
                        id: 'menu_reagendar',
                        title: 'Reagendar',
                        keywords: ['reagendar', 'cambiar cita'],
                        actions: [
                            {type: 'set_state', state: 'reagendamiento'},
                            {type: 'send_message', message: {type: 'text', body: 'Claro, cuéntanos qué día y hora prefieres para reagendar.'}},
                        ],
                    },
                    {
                        id: 'menu_soporte',
                        title: 'Soporte',
                        keywords: ['soporte', 'ayuda', 'problema'],
                        actions: [
                            {type: 'send_message', message: {type: 'text', body: 'Estamos aquí para ayudarte. Describe brevemente el inconveniente.'}},
                        ],
                    },
                    {
                        id: 'menu_encuesta',
                        title: 'Encuesta',
                        keywords: ['encuesta', 'satisfacción', 'calificar'],
                        actions: [
                            {type: 'send_message', message: {type: 'text', body: 'Tu opinión es muy valiosa. Completa la encuesta en el siguiente enlace: {{survey_url}}'}},
                        ],
                    },
                ],
            },
        },
    ];

    const SUGGESTED_SCENARIOS = [
        {
            id: 'consent_confirmation',
            title: 'Confirmar consentimiento',
            description: 'Registra la aceptación cuando el paciente responde afirmativamente.',
            scenario: {
                id: 'consent_confirmation',
                name: 'Consentimiento aceptado',
                description: 'Guarda la autorización y retoma el flujo principal.',
                intercept_menu: true,
                stage: 'consent',
                stage_id: 'consent',
                stageId: 'consent',
                conditions: [
                    {type: 'message_in', values: ['acepto', 'autorizo', 'si autorizo', 'sí autorizo']},
                ],
                actions: [
                    {type: 'store_consent', value: true},
                    {type: 'send_message', message: {type: 'text', body: '¡Gracias! Registramos tu autorización para continuar.'}},
                    {type: 'set_state', state: 'consent_confirmed'},
                    {type: 'goto_menu'},
                ],
            },
        },
        {
            id: 'schedule_request',
            title: 'Interés en agendar',
            description: 'Detecta términos asociados a citas y ofrece un flujo guiado.',
            scenario: {
                id: 'schedule_request',
                name: 'Solicita agendamiento',
                description: 'Envía botones para elegir acción y marca el estado del flujo.',
                intercept_menu: true,
                stage: 'scheduling',
                stage_id: 'scheduling',
                stageId: 'scheduling',
                conditions: [
                    {type: 'message_contains', keywords: ['agendar', 'cita', 'agendamiento']},
                ],
                actions: [
                    {type: 'send_buttons', message: {type: 'buttons', body: 'Perfecto, ¿qué tipo de cita deseas gestionar?', buttons: [
                        {id: 'cita_nueva', title: 'Nueva cita'},
                        {id: 'cita_reagendar', title: 'Reagendar'},
                    ]}},
                    {type: 'set_state', state: 'agendar_cita'},
                ],
            },
        },
        {
            id: 'handoff_to_agent',
            title: 'Escalar a agente',
            description: 'Escucha solicitudes explícitas para hablar con una persona.',
            scenario: {
                id: 'handoff_to_agent',
                name: 'Transferir a agente',
                description: 'Confirma la derivación y conserva el contexto.',
                intercept_menu: true,
                stage: 'arrival',
                stage_id: 'arrival',
                stageId: 'arrival',
                conditions: [
                    {type: 'message_contains', keywords: ['asesor', 'agente', 'humano', 'persona']},
                ],
                actions: [
                    {type: 'send_message', message: {type: 'text', body: 'Te pondré en contacto con un agente humano. Por favor espera un momento.'}},
                    {type: 'set_state', state: 'handoff'},
                    {type: 'goto_menu'},
                ],
            },
        },
    ];

    function resolveScenarioStage(value) {
        if (typeof value !== 'string') {
            return 'custom';
        }
        const normalized = value.trim().toLowerCase();
        return STAGE_VALUE_SET.has(normalized) ? normalized : 'custom';
    }

    function readScenarioStage(scenario) {
        if (!scenario || typeof scenario !== 'object') {
            return 'custom';
        }

        return resolveScenarioStage(scenario.stage || scenario.stage_id || scenario.stageId);
    }

    function getScenarioStageOption(value) {
        const normalized = resolveScenarioStage(value);
        return SCENARIO_STAGE_OPTIONS.find((option) => option.value === normalized) || SCENARIO_STAGE_OPTIONS[SCENARIO_STAGE_OPTIONS.length - 1];
    }

    function escapeSelector(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return value.replace(/[\W_]/g, '\\$&');
    }

    const htmlEntityDecoder = document.createElement('textarea');

    function decodeHtmlEntities(value) {
        if (typeof value !== 'string') {
            return '';
        }

        if (value.indexOf('&') === -1) {
            return value;
        }

        htmlEntityDecoder.innerHTML = value;

        return htmlEntityDecoder.value;
    }

    function getLocalStorage() {
        if (storageEvaluated) {
            return cachedStorage;
        }

        storageEvaluated = true;

        try {
            if (typeof window === 'undefined' || !window.localStorage) {
                cachedStorage = null;
            } else {
                const testKey = '__wa_autoresponder__';
                window.localStorage.setItem(testKey, '1');
                window.localStorage.removeItem(testKey);
                cachedStorage = window.localStorage;
            }
        } catch (error) {
            cachedStorage = null;
        }

        return cachedStorage;
    }

    function loadSimpleModePreference() {
        const storage = getLocalStorage();
        if (!storage) {
            return true;
        }

        const stored = storage.getItem(STORAGE_KEYS.simpleMode);
        if (stored === 'advanced') {
            return false;
        }
        if (stored === 'simple') {
            return true;
        }

        return true;
    }

    function persistSimpleModePreference(simpleMode) {
        const storage = getLocalStorage();
        if (!storage) {
            return;
        }

        try {
            storage.setItem(STORAGE_KEYS.simpleMode, simpleMode ? 'simple' : 'advanced');
        } catch (error) {
            // Ignore persistence failures silently
        }
    }

    function loadExpandedScenariosPreference() {
        const storage = getLocalStorage();
        if (!storage) {
            return [];
        }

        try {
            const stored = storage.getItem(STORAGE_KEYS.expandedScenarios);
            if (!stored) {
                return [];
            }

            const parsed = JSON.parse(stored);
            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed
                .map((value) => (typeof value === 'string' ? value.trim() : ''))
                .filter((value) => value.length > 0);
        } catch (error) {
            return [];
        }
    }

    function persistExpandedScenarios() {
        const storage = getLocalStorage();
        if (!storage) {
            return;
        }

        try {
            const serialized = JSON.stringify(Array.from(uiState.expandedScenarios));
            storage.setItem(STORAGE_KEYS.expandedScenarios, serialized);
        } catch (error) {
            // Ignore persistence failures silently
        }
    }

    function isScenarioExpanded(id) {
        if (!id) {
            return false;
        }

        return uiState.expandedScenarios.has(String(id));
    }

    function setScenarioExpanded(id, expanded) {
        if (!id) {
            return;
        }
        const key = String(id);
        if (expanded) {
            if (!uiState.expandedScenarios.has(key)) {
                uiState.expandedScenarios.add(key);
                persistExpandedScenarios();
            }
        } else if (uiState.expandedScenarios.has(key)) {
            uiState.expandedScenarios.delete(key);
            persistExpandedScenarios();
        }
    }

    function collapseAllScenarios() {
        if (uiState.expandedScenarios.size === 0) {
            return;
        }

        uiState.expandedScenarios.clear();
        persistExpandedScenarios();
    }

    function expandAllScenarios(ids) {
        uiState.expandedScenarios.clear();
        (ids || []).forEach((id) => {
            if (id) {
                uiState.expandedScenarios.add(String(id));
            }
        });
        persistExpandedScenarios();
    }

    function scenarioUsesAdvancedFeatures(scenario) {
        if (!scenario) {
            return false;
        }

        const hasAdvancedAction = Array.isArray(scenario.actions)
            && scenario.actions.some((action) => action && ADVANCED_ACTION_TYPES.has(action.type));
        if (hasAdvancedAction) {
            return true;
        }

        const hasAdvancedCondition = Array.isArray(scenario.conditions)
            && scenario.conditions.some((condition) => condition && !BASIC_CONDITION_TYPES.has(condition.type));

        return Boolean(hasAdvancedCondition || scenario.intercept_menu);
    }

    function getActionOptionsForCurrentMode(currentType) {
        if (!uiState.simpleMode) {
            return ACTION_OPTIONS;
        }

        return ACTION_OPTIONS.filter((option) => SIMPLE_ACTION_TYPES.has(option.value) || option.value === currentType);
    }

    function isActionHiddenInSimpleMode(type) {
        return uiState.simpleMode && !SIMPLE_ACTION_TYPES.has(type);
    }

    const PATIENT_JOURNEY_PRESET = [
        {
            id: 'primer_contacto',
            name: 'Primer contacto',
            description: 'Da la bienvenida, solicita el consentimiento y explica los siguientes pasos.',
            stage: 'arrival',
            stage_id: 'arrival',
            stageId: 'arrival',
            intercept_menu: true,
            conditions: [{type: 'always'}],
            actions: [
                {
                    type: 'send_message',
                    message: {
                        type: 'text',
                        body: '¡Hola! Soy el asistente virtual de MedForge 👁️. Te acompañaré durante todo tu proceso.',
                    },
                },
                {
                    type: 'send_buttons',
                    message: {
                        type: 'buttons',
                        body: '¿Nos autorizas a usar tus datos protegidos para brindarte atención?',
                        buttons: [
                            {id: 'acepto', title: 'Acepto'},
                            {id: 'no_acepto', title: 'No acepto'},
                        ],
                    },
                },
                {type: 'set_state', state: 'consentimiento_pendiente'},
            ],
        },
        {
            id: 'captura_cedula',
            name: 'Captura de consentimiento',
            description: 'Registra la autorización y solicita el identificador del paciente.',
            stage: 'validation',
            stage_id: 'validation',
            stageId: 'validation',
            intercept_menu: true,
            conditions: [
                {type: 'state_is', value: 'consentimiento_pendiente'},
                {type: 'message_in', values: ['acepto', 'si', 'sí']},
            ],
            actions: [
                {type: 'store_consent', value: true},
                {
                    type: 'send_message',
                    message: {
                        type: 'text',
                        body: 'Gracias. Por favor, escribe tu número de historia clínica o cédula.',
                    },
                },
                {type: 'set_state', state: 'esperando_cedula'},
                {type: 'set_context', values: {awaiting_field: 'cedula'}},
            ],
        },
        {
            id: 'validar_cedula',
            name: 'Validar cédula',
            description: 'Valida el identificador ingresado y redirige al menú correspondiente.',
            stage: 'validation',
            stage_id: 'validation',
            stageId: 'validation',
            intercept_menu: true,
            conditions: [
                {type: 'state_is', value: 'esperando_cedula'},
                {type: 'message_matches', pattern: '^\\d{6,10}$'},
            ],
            actions: [
                {type: 'lookup_patient', field: 'cedula', source: 'message'},
                {
                    type: 'conditional',
                    condition: {type: 'patient_found'},
                    then: [
                        {
                            type: 'send_message',
                            message: {
                                type: 'text',
                                body: 'Hola {{context.patient.full_name}} 👋. Ya podemos continuar con tu atención.',
                            },
                        },
                        {type: 'set_state', state: 'menu_principal'},
                        {type: 'goto_menu'},
                    ],
                    else: [
                        {type: 'upsert_patient_from_context'},
                        {
                            type: 'send_message',
                            message: {
                                type: 'text',
                                body: 'Registré tus datos para avanzar. ¿Deseas que te muestre las opciones disponibles?',
                            },
                        },
                        {type: 'set_state', state: 'menu_principal'},
                        {type: 'goto_menu'},
                    ],
                },
            ],
        },
        {
            id: 'retorno',
            name: 'Retorno conocido',
            description: 'Saluda nuevamente a contactos con consentimiento registrado.',
            stage: 'arrival',
            stage_id: 'arrival',
            stageId: 'arrival',
            intercept_menu: true,
            conditions: [
                {type: 'is_first_time', value: false},
                {type: 'has_consent', value: true},
            ],
            actions: [
                {
                    type: 'send_message',
                    message: {
                        type: 'text',
                        body: 'Hola {{context.patient.full_name}} 👋, ¿en qué puedo ayudarte hoy?',
                    },
                },
                {type: 'goto_menu'},
            ],
        },
        {
            id: 'acceso_menu_directo',
            name: 'Acceso directo al menú',
            description: 'Permite abrir el menú cuando ya existe consentimiento registrado.',
            stage: 'menu',
            stage_id: 'menu',
            stageId: 'menu',
            intercept_menu: true,
            conditions: [
                {type: 'has_consent', value: true},
                {type: 'message_in', values: ['menu', 'inicio', 'hola', 'buen dia', 'buenos dias', 'buenas tardes', 'buenas noches', 'start']},
            ],
            actions: [
                {type: 'set_state', state: 'menu_principal'},
                {type: 'goto_menu'},
            ],
        },
        {
            id: 'menu_principal',
            name: 'Ir al menú principal',
            description: 'Permite acceder nuevamente al menú principal de opciones.',
            stage: 'menu',
            stage_id: 'menu',
            stageId: 'menu',
            intercept_menu: false,
            conditions: [{type: 'message_contains', keywords: ['menu', 'menú', 'opciones', 'volver']}],
            actions: [
                {type: 'goto_menu'},
            ],
        },
        {
            id: 'agendamiento',
            name: 'Interés en agendar cita',
            description: 'Guía al paciente para reservar o reagendar una cita.',
            stage: 'scheduling',
            stage_id: 'scheduling',
            stageId: 'scheduling',
            intercept_menu: false,
            conditions: [{type: 'message_contains', keywords: ['agendar', 'cita', 'reagendar', 'reservar']}],
            actions: [
                {type: 'send_message', message: {type: 'text', body: 'Con gusto te ayudo a agendar. Indícame la fecha y hora que prefieres o si deseas que te proponga opciones.'}},
            ],
        },
        {
            id: 'entrega_resultados',
            name: 'Consultar resultados',
            description: 'Responde cuando el paciente quiere conocer sus resultados de laboratorio.',
            stage: 'results',
            stage_id: 'results',
            stageId: 'results',
            intercept_menu: false,
            conditions: [{type: 'message_contains', keywords: ['resultado', 'resultados', 'examen', 'laboratorio']}],
            actions: [
                {type: 'send_message', message: {type: 'text', body: 'Para compartir tus resultados necesito validar tu identidad. Por favor confirma tu número de identificación.'}},
            ],
        },
        {
            id: 'post_consulta',
            name: 'Seguimiento post consulta',
            description: 'Envía recomendaciones o recordatorios después de la atención médica.',
            stage: 'post',
            stage_id: 'post',
            stageId: 'post',
            intercept_menu: false,
            conditions: [{type: 'message_contains', keywords: ['gracias', 'seguimiento', 'control', 'post consulta']}],
            actions: [
                {type: 'send_message', message: {type: 'text', body: 'Gracias por confiar en nosotros. ¿Deseas reagendar, recibir recomendaciones o calificar tu experiencia?'}},
            ],
        },
        {
            id: 'fallback',
            name: 'Fallback',
            description: 'Cuando ninguna regla aplica.',
            stage: 'custom',
            stage_id: 'custom',
            stageId: 'custom',
            intercept_menu: false,
            conditions: [{type: 'always'}],
            actions: [
                {type: 'send_message', message: {type: 'text', body: 'No te entendí. Escribe menú para ver opciones.'}},
            ],
        },
    ];

    const DEFAULT_INTERCEPT_IDS = new Set([
        'primer_contacto',
        'captura_cedula',
        'validar_cedula',
        'retorno',
        'acceso_menu_directo',
    ]);

    let scenarioSeed = Date.now();

    let templateCatalog = [];
    if (templateCatalogInput) {
        try {
            templateCatalog = JSON.parse(templateCatalogInput.value || '[]');
        } catch (error) {
            console.warn('No fue posible interpretar el catálogo de plantillas', error);
        }
        templateCatalogInput.name = '';
    }

    const bootstrapPayload = parseBootstrap();
    const state = initializeState(bootstrapPayload);
    const defaults = JSON.parse(JSON.stringify(state));
    const simulationHistory = [];
    const replayMessages = [];
    let menuPreviewNode = null;

    const variablesPanel = form.querySelector('[data-variable-list]');
    const scenariosPanel = form.querySelector('[data-scenario-list]');
    const journeyMapCard = form.querySelector('[data-journey-map-card]');
    const journeyMapContainer = journeyMapCard?.querySelector('[data-journey-map]') || null;
    const menuPanel = form.querySelector('[data-menu-editor]');
    const scenarioSummaryContainer = form.querySelector('[data-scenario-summary]');
    const suggestedScenariosContainer = form.querySelector('[data-suggested-scenarios]');
    const scenarioModeToggle = form.querySelector('[data-scenario-mode-toggle]');
    const expandAllScenariosButton = form.querySelector('[data-action="expand-all-scenarios"]');
    const collapseAllScenariosButton = form.querySelector('[data-action="collapse-all-scenarios"]');
    const expandAdvancedScenariosButton = form.querySelector('[data-action="expand-advanced-scenarios"]');
    const applyJourneyPresetButton = form.querySelector('[data-action="apply-journey-preset"]');
    const simulationPanel = form.querySelector('[data-simulation-panel]');
    const simulationInput = simulationPanel?.querySelector('[data-simulation-input]') || null;
    const simulationReplay = simulationPanel?.querySelector('[data-simulation-replay]') || null;
    const simulationLog = simulationPanel?.querySelector('[data-simulation-log]') || null;
    const simulationFirstTime = simulationPanel?.querySelector('[data-simulation-first-time]') || null;
    const simulationHasConsent = simulationPanel?.querySelector('[data-simulation-has-consent]') || null;
    const simulationStateInput = simulationPanel?.querySelector('[data-simulation-state]') || null;
    const simulationAwaitingInput = simulationPanel?.querySelector('[data-simulation-awaiting]') || null;
    const simulationMinutesInput = simulationPanel?.querySelector('[data-simulation-minutes]') || null;
    const simulationPatientFound = simulationPanel?.querySelector('[data-simulation-patient-found]') || null;

    const simulateButton = form.querySelector('[data-action="simulate-flow"]');
    const addScenarioButton = form.querySelector('[data-action="add-scenario"]');
    const resetVariablesButton = form.querySelector('[data-action="reset-variables"]');
    const resetMenuButton = form.querySelector('[data-action="reset-menu"]');

    if (addScenarioButton) {
        addScenarioButton.addEventListener('click', (event) => {
            event.preventDefault();
            const scenario = createDefaultScenario();
            state.scenarios.push(scenario);
            setScenarioExpanded(scenario.id, true);
            renderScenarios();
        });
    }

    if (simulateButton) {
        simulateButton.addEventListener('click', (event) => {
            event.preventDefault();
            if (simulationPanel) {
                simulationPanel.scrollIntoView({behavior: 'smooth', block: 'start'});
                simulationInput?.focus();

                return;
            }
            simulateFlow();
        });
    }

    if (resetVariablesButton) {
        resetVariablesButton.addEventListener('click', (event) => {
            event.preventDefault();
            state.variables = JSON.parse(JSON.stringify(defaults.variables));
            renderVariables();
        });
    }

    if (resetMenuButton) {
        resetMenuButton.addEventListener('click', (event) => {
            event.preventDefault();
            state.menu = JSON.parse(JSON.stringify(defaults.menu));
            renderMenu();
        });
    }

    if (applyJourneyPresetButton) {
        applyJourneyPresetButton.addEventListener('click', (event) => {
            event.preventDefault();
            if (!window.confirm('Aplicar el recorrido sugerido reemplazará tus escenarios actuales. ¿Deseas continuar?')) {
                return;
            }
            applyPatientJourneyPreset();
        });
    }

    if (expandAllScenariosButton) {
        expandAllScenariosButton.addEventListener('click', (event) => {
            event.preventDefault();
            const ids = state.scenarios.map((scenario) => scenario.id).filter(Boolean);
            expandAllScenarios(ids);
            renderScenarios();
        });
    }

    if (collapseAllScenariosButton) {
        collapseAllScenariosButton.addEventListener('click', (event) => {
            event.preventDefault();
            collapseAllScenarios();
            renderScenarios();
        });
    }

    if (expandAdvancedScenariosButton) {
        expandAdvancedScenariosButton.addEventListener('click', (event) => {
            event.preventDefault();
            const advancedIds = state.scenarios.filter((scenario) => scenarioUsesAdvancedFeatures(scenario)).map((scenario) => scenario.id);
            if (advancedIds.length === 0) {
                collapseAllScenarios();
            } else {
                expandAllScenarios(advancedIds);
            }
            renderScenarios();
        });
    }

    if (scenarioModeToggle) {
        const modeButtons = Array.from(scenarioModeToggle.querySelectorAll('[data-mode]'));
        modeButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const mode = button.dataset.mode === 'advanced' ? 'advanced' : 'simple';
                uiState.simpleMode = mode !== 'advanced';
                persistSimpleModePreference(uiState.simpleMode);
                modeButtons.forEach((other) => {
                    other.classList.toggle('active', other === button);
                    other.classList.toggle('btn-primary', other === button);
                    other.classList.toggle('btn-outline-secondary', other !== button);
                });
                renderScenarios();
            });
        });
    }

    form.addEventListener('submit', (event) => {
        resetValidation();
        normalizeScenarios();

        const payload = buildPayload();
        const errors = validatePayload(payload);

        if (errors.length > 0) {
            event.preventDefault();
            presentErrors(errors);

            return;
        }

        if (flowField) {
            flowField.value = JSON.stringify(payload);
        }
    });

    renderVariables();
    renderScenarios();
    renderMenu();
    renderSuggestedScenarios();
    setupSimulationPanel();

    function parseBootstrap() {
        if (!flowBootstrap) {
            return {};
        }
        try {
            const raw = flowBootstrap.textContent || flowBootstrap.innerHTML || '';
            const normalized = decodeHtmlEntities(raw).trim();
            if (normalized === '') {
                return {};
            }

            return JSON.parse(normalized);
        } catch (error) {
            console.warn('No fue posible interpretar la configuración del flujo', error);

            return {};
        }
    }

    function initializeState(payload) {
        const variables = [];
        const rawVariables = payload.variables || {};
        Object.keys(rawVariables).forEach((key) => {
            const entry = rawVariables[key];
            variables.push({
                key,
                label: entry.label || capitalize(key),
                source: entry.source || 'context.' + key,
                persist: Boolean(entry.persist),
            });
        });

        if (variables.length === 0) {
            variables.push(
                {key: 'cedula', label: 'Cédula', source: 'context.cedula', persist: true},
                {key: 'telefono', label: 'Teléfono', source: 'session.wa_number', persist: true},
                {key: 'nombre', label: 'Nombre completo', source: 'patient.full_name', persist: false},
                {key: 'consentimiento', label: 'Consentimiento', source: 'context.consent', persist: true},
                {key: 'estado', label: 'Estado', source: 'context.state', persist: false},
            );
        }

        const scenarios = Array.isArray(payload.scenarios) && payload.scenarios.length > 0
            ? payload.scenarios.map((scenario) => cloneScenario(scenario))
            : [createDefaultScenario()];

        const menu = Object.keys(payload.menu || {}).length > 0
            ? JSON.parse(JSON.stringify(payload.menu))
            : createDefaultMenu();

        return {
            variables,
            scenarios,
            menu,
        };
    }

    function renderVariables() {
        if (!variablesPanel || !templates.variableRow) {
            return;
        }
        variablesPanel.innerHTML = '';

        state.variables.forEach((variable) => {
            const clone = templates.variableRow.content.firstElementChild.cloneNode(true);
            const keyLabel = clone.querySelector('[data-variable-key]');
            const description = clone.querySelector('[data-variable-description]');
            const labelInput = clone.querySelector('[data-variable-label]');
            const sourceSelect = clone.querySelector('[data-variable-source]');
            const persistInput = clone.querySelector('[data-variable-persist]');

            if (keyLabel) {
                keyLabel.textContent = variable.key;
            }

            if (description) {
                description.textContent = variableDescription(variable.key);
            }

            if (labelInput) {
                labelInput.value = variable.label || '';
                labelInput.addEventListener('input', () => {
                    variable.label = labelInput.value.trim();
                });
            }

            if (sourceSelect) {
                sourceSelect.innerHTML = VARIABLE_SOURCES.map((option) => {
                    const selected = option.value === variable.source ? 'selected' : '';
                    return `<option value="${option.value}" ${selected}>${option.label}</option>`;
                }).join('');
                sourceSelect.value = variable.source;
                sourceSelect.addEventListener('change', () => {
                    variable.source = sourceSelect.value;
                });
            }

            if (persistInput) {
                persistInput.checked = Boolean(variable.persist);
                persistInput.addEventListener('change', () => {
                    variable.persist = persistInput.checked;
                });
            }

            variablesPanel.appendChild(clone);
        });
    }

    function scenarioSummaryText(scenario) {
        if (!scenario) {
            return '';
        }

        const conditionCount = Array.isArray(scenario.conditions) ? scenario.conditions.length : 0;
        const actionCount = Array.isArray(scenario.actions) ? scenario.actions.length : 0;
        const parts = [];

        if (scenario.intercept_menu) {
            parts.push('Responde antes del menú');
        }

        parts.push(`${conditionCount} ${conditionCount === 1 ? 'condición' : 'condiciones'}`);
        parts.push(`${actionCount} ${actionCount === 1 ? 'acción' : 'acciones'}`);

        return parts.join(' · ');
    }

    function collectScenarioDiagnostics(scenario) {
        const issues = [];
        const pushIssue = (message) => {
            if (!issues.includes(message)) {
                issues.push(message);
            }
        };

        if (!scenario || typeof scenario !== 'object') {
            pushIssue('Configura los detalles del escenario.');

            return issues;
        }

        if (!Array.isArray(scenario.actions) || scenario.actions.length === 0) {
            pushIssue('Añade al menos una acción.');
        }

        const conditions = Array.isArray(scenario.conditions) ? scenario.conditions : [];
        conditions.forEach((condition) => {
            const type = condition?.type || 'always';
            if (type === 'message_in') {
                const values = Array.isArray(condition.values) ? condition.values.filter((value) => typeof value === 'string' && value.trim() !== '') : [];
                if (values.length === 0) {
                    pushIssue('Completa la lista de palabras clave.');
                }
            }
            if (type === 'message_contains') {
                const keywords = Array.isArray(condition.keywords) ? condition.keywords.filter((value) => typeof value === 'string' && value.trim() !== '') : [];
                if (keywords.length === 0) {
                    pushIssue('Añade palabras clave a "Mensaje contiene".');
                }
            }
            if (type === 'message_matches') {
                const pattern = typeof condition.pattern === 'string' ? condition.pattern.trim() : '';
                if (pattern === '') {
                    pushIssue('Define la expresión regular requerida.');
                }
            }
        });

        const actions = Array.isArray(scenario.actions) ? scenario.actions : [];
        actions.forEach((action) => {
            if (!action || typeof action !== 'object') {
                return;
            }
            const type = action.type || 'send_message';
            if (type === 'send_message') {
                if (validateSimpleMessagePayload(action.message, 'mensaje').length > 0) {
                    pushIssue('Revisa el contenido del mensaje.');
                }
                return;
            }
            if (type === 'send_sequence') {
                if (!Array.isArray(action.messages) || action.messages.length === 0) {
                    pushIssue('Añade mensajes a la secuencia.');
                } else if (action.messages.some((message) => validateSimpleMessagePayload(message, 'mensaje').length > 0)) {
                    pushIssue('Revisa la secuencia de mensajes.');
                }
                return;
            }
            if (type === 'send_template') {
                const template = action.template || {};
                if (!template.name || !template.language) {
                    pushIssue('Selecciona una plantilla aprobada.');
                }
                return;
            }
            if (type === 'send_buttons') {
                const message = action.message || {};
                const body = typeof message.body === 'string' ? message.body.trim() : '';
                const buttons = Array.isArray(message.buttons)
                    ? message.buttons.filter((button) => button && (button.title || button.id))
                    : [];
                if (body === '' || buttons.length === 0) {
                    pushIssue('Configura el mensaje y los botones.');
                }
                return;
            }
            if (type === 'send_list') {
                const message = action.message || {};
                const body = typeof message.body === 'string' ? message.body.trim() : '';
                const button = typeof message.button === 'string' ? message.button.trim() : '';
                const sections = Array.isArray(message.sections)
                    ? message.sections.filter((section) => Array.isArray(section?.rows) && section.rows.length > 0)
                    : [];
                if (body === '' || button === '' || sections.length === 0) {
                    pushIssue('Completa la lista interactiva.');
                }
            }
        });

        return issues;
    }

    function renderJourneyMap() {
        if (!journeyMapContainer) {
            return;
        }

        const stageEntries = SCENARIO_STAGE_OPTIONS.map((option) => ({
            ...option,
            scenarios: [],
        }));
        const stageMap = new Map(stageEntries.map((entry) => [entry.value, entry]));
        const fallbackStage = stageMap.get('custom');
        const scenarios = Array.isArray(state.scenarios) ? state.scenarios : [];

        scenarios.forEach((scenario, index) => {
            if (!scenario || typeof scenario !== 'object') {
                return;
            }

            const stageValue = readScenarioStage(scenario);
            const target = stageMap.get(stageValue) || fallbackStage;
            if (!target) {
                return;
            }

            target.scenarios.push({scenario, index});
        });

        journeyMapContainer.innerHTML = '';
        journeyMapContainer.style.setProperty('--journey-stage-count', String(stageEntries.length));

        const totalScenarios = stageEntries.reduce((carry, entry) => carry + entry.scenarios.length, 0);
        if (totalScenarios === 0) {
            journeyMapContainer.classList.add('journey-map--empty');
            if (journeyMapCard) {
                journeyMapCard.classList.add('journey-map-card--empty');
            }

            const empty = document.createElement('div');
            empty.className = 'journey-map__empty';
            empty.innerHTML = '<i class="mdi mdi-map-clock-outline display-6 d-block mb-2"></i><p class="text-muted small mb-0">Añade escenarios para visualizar el recorrido.</p>';
            journeyMapContainer.appendChild(empty);

            return;
        }

        journeyMapContainer.classList.remove('journey-map--empty');
        if (journeyMapCard) {
            journeyMapCard.classList.remove('journey-map-card--empty');
        }

        const fragment = document.createDocumentFragment();

        stageEntries.forEach((stageEntry) => {
            const lane = document.createElement('div');
            lane.className = 'journey-map__lane';
            lane.dataset.stage = stageEntry.value;

            const heading = document.createElement('div');
            heading.className = 'journey-map__lane-heading';

            const headingText = document.createElement('div');
            const headingTitle = document.createElement('div');
            headingTitle.className = 'journey-map__lane-title';
            headingTitle.textContent = stageEntry.label;
            const headingDescription = document.createElement('div');
            headingDescription.className = 'journey-map__lane-description';
            headingDescription.textContent = stageEntry.description;
            headingText.appendChild(headingTitle);
            headingText.appendChild(headingDescription);

            const countBadge = document.createElement('span');
            countBadge.className = 'journey-map__lane-count';
            const countValue = stageEntry.scenarios.length;
            countBadge.textContent = `${countValue} ${countValue === 1 ? 'escenario' : 'escenarios'}`;

            heading.appendChild(headingText);
            heading.appendChild(countBadge);

            const body = document.createElement('div');
            body.className = 'journey-map__lane-body';

            if (stageEntry.scenarios.length === 0) {
                const placeholder = document.createElement('div');
                placeholder.className = 'journey-map__placeholder';
                placeholder.innerHTML = '<i class="mdi mdi-dots-square"></i>Sin escenarios en esta etapa';
                body.appendChild(placeholder);
            } else {
                stageEntry.scenarios.forEach((entry) => {
                    const node = createJourneyNode(entry, stageEntry);
                    body.appendChild(node);
                });
            }

            lane.appendChild(heading);
            lane.appendChild(body);
            fragment.appendChild(lane);
        });

        journeyMapContainer.appendChild(fragment);

        function createJourneyNode(entry, stageEntry) {
            const {scenario, index} = entry;
            const node = document.createElement('button');
            node.type = 'button';
            node.className = 'journey-node';
            const scenarioId = scenario?.id ? String(scenario.id) : '';
            if (scenarioId) {
                node.dataset.scenarioId = scenarioId;
            }
            node.dataset.index = String(index);
            node.dataset.stage = stageEntry.value;

            if (scenario?.intercept_menu) {
                node.classList.add('journey-node--intercept');
            }
            if (scenarioId && isScenarioExpanded(scenarioId)) {
                node.classList.add('journey-node--active');
            }

            const diagnostics = collectScenarioDiagnostics(scenario);
            if (diagnostics.length > 0) {
                node.classList.add('journey-node--invalid');
                node.title = diagnostics.join('\n');
            } else {
                node.title = scenarioSummaryText(scenario);
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'journey-node__content-wrapper';

            const indexBadge = document.createElement('div');
            indexBadge.className = 'journey-node__index';
            indexBadge.textContent = String(index + 1);
            wrapper.appendChild(indexBadge);

            const content = document.createElement('div');
            content.className = 'journey-node__content';

            const title = document.createElement('div');
            title.className = 'journey-node__title';
            title.textContent = scenario?.name || `Escenario ${index + 1}`;
            content.appendChild(title);

            const badges = document.createElement('div');
            badges.className = 'journey-node__badges';
            const stageBadge = document.createElement('span');
            stageBadge.className = 'journey-node__badge';
            stageBadge.textContent = stageEntry.label;
            badges.appendChild(stageBadge);
            if (scenario?.intercept_menu) {
                const interceptBadge = document.createElement('span');
                interceptBadge.className = 'journey-node__badge journey-node__badge--intercept';
                interceptBadge.textContent = 'Intercepta menú';
                badges.appendChild(interceptBadge);
            }
            content.appendChild(badges);

            const meta = document.createElement('div');
            meta.className = 'journey-node__meta';
            meta.textContent = scenarioSummaryText(scenario);
            content.appendChild(meta);

            const description = typeof scenario?.description === 'string' ? scenario.description.trim() : '';
            if (description !== '') {
                const descriptionEl = document.createElement('div');
                descriptionEl.className = 'journey-node__description';
                descriptionEl.textContent = description;
                content.appendChild(descriptionEl);
            }

            wrapper.appendChild(content);
            node.appendChild(wrapper);

            node.addEventListener('click', (event) => {
                event.preventDefault();
                focusScenarioCardFromMap(scenario, index);
            });
            node.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    focusScenarioCardFromMap(scenario, index);
                }
            });

            return node;
        }
    }

    function focusScenarioCardFromMap(scenario, index) {
        const scenarioId = scenario?.id ? String(scenario.id) : '';
        const wasExpanded = scenarioId ? isScenarioExpanded(scenarioId) : false;

        if (scenarioId) {
            setScenarioExpanded(scenarioId, true);
        }

        if (!wasExpanded) {
            renderScenarios();
        } else if (journeyMapContainer) {
            renderJourneyMap();
        }

        window.requestAnimationFrame(() => {
            const refreshedId = scenarioId || (state.scenarios[index]?.id ? String(state.scenarios[index].id) : '');
            const selector = refreshedId
                ? `[data-scenario][data-scenario-id="${escapeSelector(refreshedId)}"]`
                : `[data-scenario][data-index="${index}"]`;
            const card = scenariosPanel?.querySelector(selector);
            if (!card) {
                return;
            }

            card.classList.add('scenario-card--pulse');
            card.scrollIntoView({behavior: 'smooth', block: 'center'});
            window.setTimeout(() => {
                card.classList.remove('scenario-card--pulse');
            }, 1200);
        });
    }

    function renderScenarios() {
        if (!scenariosPanel || !templates.scenarioCard) {
            return;
        }
        scenariosPanel.innerHTML = '';

        const stageGroups = new Map();
        SCENARIO_STAGE_OPTIONS.forEach((stage) => {
            stageGroups.set(stage.value, []);
        });

        state.scenarios.forEach((scenario, index) => {
            let stage = readScenarioStage(scenario);
            if (!stageGroups.has(stage)) {
                stage = 'custom';
            }
            stageGroups.get(stage)?.push({scenario, index});
        });

        const fragment = document.createDocumentFragment();

        const stageOrder = SCENARIO_STAGE_OPTIONS.map((stageOption, orderIndex) => {
            const stageItems = stageGroups.get(stageOption.value) || [];
            const firstIndex = stageItems.length > 0
                ? stageItems.reduce((min, entry) => Math.min(min, entry.index), Number.POSITIVE_INFINITY)
                : Number.POSITIVE_INFINITY;

            return {
                ...stageOption,
                firstIndex,
                orderIndex,
            };
        }).sort((a, b) => {
            if (a.firstIndex === b.firstIndex) {
                return a.orderIndex - b.orderIndex;
            }

            return a.firstIndex - b.firstIndex;
        });

        stageOrder.forEach((stageOption) => {
            const section = document.createElement('div');
            section.className = 'scenario-stage card border-0 shadow-sm mb-3';
            section.dataset.stage = stageOption.value;

            const header = document.createElement('div');
            header.className = 'scenario-stage__header card-header border-0 bg-transparent d-flex justify-content-between align-items-start gap-2 flex-wrap';

            const heading = document.createElement('div');
            heading.innerHTML = `<h6 class="fw-600 mb-1">${stageOption.label}</h6><p class="text-muted small mb-0">${stageOption.description}</p>`;

            const count = document.createElement('span');
            count.className = 'badge bg-primary-light text-primary';
            const stageItems = stageGroups.get(stageOption.value) || [];
            count.textContent = `${stageItems.length} ${stageItems.length === 1 ? 'escenario' : 'escenarios'}`;

            header.appendChild(heading);
            header.appendChild(count);

            const body = document.createElement('div');
            body.className = 'scenario-stage__body card-body pt-0';

            const list = document.createElement('div');
            list.className = 'scenario-stage__list d-flex flex-column gap-3';
            list.dataset.stageList = 'true';
            list.dataset.stage = stageOption.value;

            stageItems.forEach(({scenario, index}) => {
                const card = templates.scenarioCard.content.firstElementChild.cloneNode(true);
                card.dataset.index = String(index);
                card.dataset.scenarioId = scenario.id || '';
                card.dataset.stage = stageOption.value;

                const idInput = card.querySelector('[data-scenario-id]');
                const titleLabel = card.querySelector('[data-scenario-title]');
                const summaryLabel = card.querySelector('[data-scenario-summary-preview]');
                const stageLabel = card.querySelector('[data-scenario-stage-label]');
                const toggleButton = card.querySelector('[data-action="toggle-scenario"]');
                const bodyWrapper = card.querySelector('[data-scenario-body]');
                const nameInput = card.querySelector('[data-scenario-name]');
                const descriptionInput = card.querySelector('[data-scenario-description]');
                const stageSelect = card.querySelector('[data-scenario-stage]');
                const stageHelp = card.querySelector('[data-scenario-stage-help]');
                const interceptToggle = card.querySelector('[data-scenario-intercept]');
                const interceptHelp = card.querySelector('[data-scenario-intercept-help]');
                const addConditionButton = card.querySelector('[data-action="add-condition"]');
                const addActionButton = card.querySelector('[data-action="add-action"]');
                const moveUpButton = card.querySelector('[data-action="move-up"]');
                const moveDownButton = card.querySelector('[data-action="move-down"]');
                const removeButton = card.querySelector('[data-action="remove-scenario"]');
                const conditionList = card.querySelector('[data-condition-list]');
                const actionList = card.querySelector('[data-action-list]');

                const expanded = isScenarioExpanded(scenario.id);
                card.classList.toggle('is-collapsed', !expanded);
                if (toggleButton) {
                    toggleButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                }
                if (bodyWrapper) {
                    bodyWrapper.classList.toggle('d-none', !expanded);
                }
                const toggleIcon = toggleButton?.querySelector('i');
                if (toggleIcon) {
                    toggleIcon.classList.toggle('mdi-chevron-down', expanded);
                    toggleIcon.classList.toggle('mdi-chevron-right', !expanded);
                }

                const updateHeader = () => {
                    const stageOptionMeta = getScenarioStageOption(readScenarioStage(scenario));
                    if (idInput) {
                        idInput.value = scenario.id || '';
                    }
                    if (titleLabel) {
                        titleLabel.textContent = scenario.name || scenario.id || 'Escenario sin nombre';
                    }
                    if (summaryLabel) {
                        const description = scenario.description ? `${scenario.description} · ` : '';
                        summaryLabel.textContent = `${description}${scenarioSummaryText(scenario)}`;
                    }
                    if (stageLabel) {
                        stageLabel.textContent = stageOptionMeta.label;
                    }
                    if (stageHelp) {
                        stageHelp.textContent = stageOptionMeta.description;
                    }
                };

                if (toggleButton) {
                    toggleButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        card.classList.toggle('is-collapsed');
                        const isCollapsedNow = card.classList.contains('is-collapsed');
                        const finalExpanded = !isCollapsedNow;
                        if (bodyWrapper) {
                            bodyWrapper.classList.toggle('d-none', !finalExpanded);
                        }
                        const icon = toggleButton.querySelector('i');
                        if (icon) {
                            icon.classList.toggle('mdi-chevron-right', !finalExpanded);
                            icon.classList.toggle('mdi-chevron-down', finalExpanded);
                        }
                        toggleButton.setAttribute('aria-expanded', finalExpanded ? 'true' : 'false');
                        setScenarioExpanded(scenario.id, finalExpanded);
                    });
                }

                if (nameInput) {
                    nameInput.value = scenario.name || '';
                    nameInput.addEventListener('input', () => {
                        scenario.name = nameInput.value;
                        if (!scenario.id && scenario.name.trim() !== '') {
                            scenario.id = slugify(scenario.name);
                        }
                        updateHeader();
                        renderScenarioSummary();
                    });
                }

                if (descriptionInput) {
                    descriptionInput.value = scenario.description || '';
                    descriptionInput.addEventListener('input', () => {
                        scenario.description = descriptionInput.value;
                        updateHeader();
                        renderScenarioSummary();
                    });
                }

                if (stageSelect) {
                    stageSelect.innerHTML = SCENARIO_STAGE_OPTIONS.map((option) => {
                        const selected = option.value === readScenarioStage(scenario) ? 'selected' : '';
                        return `<option value="${option.value}" ${selected}>${option.label}</option>`;
                    }).join('');
                    stageSelect.value = readScenarioStage(scenario);
                    stageSelect.addEventListener('change', () => {
                        const nextStage = resolveScenarioStage(stageSelect.value);
                        scenario.stage = nextStage;
                        scenario.stage_id = nextStage;
                        scenario.stageId = nextStage;
                        setScenarioExpanded(scenario.id, true);
                        renderScenarios();
                    });
                }

                if (interceptToggle) {
                    interceptToggle.checked = Boolean(scenario.intercept_menu);
                    interceptToggle.addEventListener('change', () => {
                        scenario.intercept_menu = interceptToggle.checked;
                        if (interceptHelp) {
                            interceptHelp.classList.toggle('d-none', interceptToggle.checked);
                        }
                        updateHeader();
                        renderScenarioSummary();
                    });
                }

                if (interceptHelp) {
                    interceptHelp.classList.toggle('d-none', Boolean(scenario.intercept_menu));
                }

                if (addConditionButton) {
                    addConditionButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        scenario.conditions = scenario.conditions || [];
                        scenario.conditions.push({type: 'always'});
                        renderConditions(conditionList, scenario, () => {
                            updateHeader();
                            renderScenarioSummary();
                        });
                        setScenarioExpanded(scenario.id, true);
                        updateHeader();
                    });
                }

                if (addActionButton) {
                    addActionButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        scenario.actions = scenario.actions || [];
                        scenario.actions.push({type: 'send_message', message: {type: 'text', body: ''}});
                        renderActions(actionList, scenario.actions, scenario, () => {
                            updateHeader();
                            renderScenarioSummary();
                        });
                        setScenarioExpanded(scenario.id, true);
                        updateHeader();
                    });
                }

                if (moveUpButton) {
                    moveUpButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        if (index === 0) {
                            return;
                        }
                        const temp = state.scenarios[index - 1];
                        state.scenarios[index - 1] = state.scenarios[index];
                        state.scenarios[index] = temp;
                        setScenarioExpanded(scenario.id, true);
                        renderScenarios();
                    });
                }

                if (moveDownButton) {
                    moveDownButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        if (index === state.scenarios.length - 1) {
                            return;
                        }
                        const temp = state.scenarios[index + 1];
                        state.scenarios[index + 1] = state.scenarios[index];
                        state.scenarios[index] = temp;
                        setScenarioExpanded(scenario.id, true);
                        renderScenarios();
                    });
                }

                if (removeButton) {
                    removeButton.addEventListener('click', (event) => {
                        event.preventDefault();
                        state.scenarios.splice(index, 1);
                        setScenarioExpanded(scenario.id, false);
                        if (state.scenarios.length === 0) {
                            const fallback = createDefaultScenario();
                            state.scenarios.push(fallback);
                            setScenarioExpanded(fallback.id, true);
                        }
                        renderScenarios();
                    });
                }

                renderConditions(conditionList, scenario, () => {
                    updateHeader();
                    renderScenarioSummary();
                });
                renderActions(actionList, scenario.actions || [], scenario, () => {
                    updateHeader();
                    renderScenarioSummary();
                });
                updateHeader();

                list.appendChild(card);
            });

            if (stageItems.length === 0) {
                const emptyState = document.createElement('div');
                emptyState.className = 'scenario-stage__empty text-muted small';
                emptyState.textContent = 'Arrastra un escenario existente aquí o crea uno nuevo.';
                list.appendChild(emptyState);
            }

            body.appendChild(list);
            section.appendChild(header);
            section.appendChild(body);
            fragment.appendChild(section);
        });

        scenariosPanel.appendChild(fragment);

        renderJourneyMap();
        updateScenarioControls();
        setupScenarioSortable();
        renderScenarioSummary();
        renderSuggestedScenarios();
        refreshSimulationHints();
    }

    function setupScenarioSortable() {
        if (typeof Sortable === 'undefined' || !scenariosPanel) {
            return;
        }

        const lists = Array.from(scenariosPanel.querySelectorAll('[data-stage-list]'));
        lists.forEach((list) => {
            const existing = Sortable.get(list);
            if (existing) {
                existing.destroy();
            }

            Sortable.create(list, {
                group: 'autoresponder-scenarios',
                animation: 150,
                handle: '[data-drag-handle]',
                draggable: '[data-scenario]',
                ghostClass: 'scenario-card--dragging',
                onEnd: (event) => {
                    const scenarioId = event.item?.dataset?.scenarioId || null;
                    rebuildScenarioOrderFromDom(scenarioId, event.to?.dataset?.stage || null);
                },
            });
        });
    }

    function rebuildScenarioOrderFromDom(focusScenarioId, fallbackStage) {
        if (!scenariosPanel) {
            return;
        }

        const lists = Array.from(scenariosPanel.querySelectorAll('[data-stage-list]'));
        if (lists.length === 0) {
            return;
        }

        const scenarioMap = new Map(state.scenarios.map((scenario) => [scenario.id, scenario]));
        const ordered = [];
        const expandedIds = new Set();

        lists.forEach((list) => {
            const stageValue = resolveScenarioStage(list.dataset.stage);
            Array.from(list.querySelectorAll('[data-scenario]')).forEach((card) => {
                const scenarioId = card.dataset.scenarioId;
                if (!scenarioId) {
                    return;
                }

                const scenario = scenarioMap.get(scenarioId);
                if (!scenario) {
                    return;
                }

                scenario.stage = stageValue;
                scenario.stage_id = stageValue;
                scenario.stageId = stageValue;
                ordered.push(scenario);

                if (!card.classList.contains('is-collapsed')) {
                    expandedIds.add(scenarioId);
                }
            });
        });

        if (focusScenarioId && scenarioMap.has(focusScenarioId)) {
            const fallbackValue = fallbackStage || readScenarioStage(scenarioMap.get(focusScenarioId));
            const focusedStage = resolveScenarioStage(fallbackValue);
            scenarioMap.get(focusScenarioId).stage = focusedStage;
            expandedIds.add(focusScenarioId);
        }

        if (ordered.length === state.scenarios.length && ordered.length > 0) {
            state.scenarios = ordered;
        }

        expandAllScenarios(Array.from(expandedIds));
        renderScenarios();
    }

    function applyPatientJourneyPreset() {
        state.scenarios = PATIENT_JOURNEY_PRESET.map((scenario) => cloneScenario(scenario));
        const ids = state.scenarios.map((scenario) => scenario.id);
        expandAllScenarios(ids);
        renderScenarios();
    }

    function updateScenarioControls() {
        if (expandAdvancedScenariosButton) {
            const advancedCount = state.scenarios.filter((scenario) => scenarioUsesAdvancedFeatures(scenario)).length;
            expandAdvancedScenariosButton.disabled = advancedCount === 0;
            expandAdvancedScenariosButton.classList.toggle('disabled', advancedCount === 0);
        }

        if (scenarioModeToggle) {
            const buttons = Array.from(scenarioModeToggle.querySelectorAll('[data-mode]'));
            buttons.forEach((button) => {
                const isSimpleButton = button.dataset.mode !== 'advanced';
                const isActive = uiState.simpleMode ? isSimpleButton : !isSimpleButton;
                button.classList.toggle('active', isActive);
                button.classList.toggle('btn-primary', isActive);
                button.classList.toggle('btn-outline-secondary', !isActive);
            });
        }
    }

    function clearScenarioValidationState() {
        if (!scenariosPanel) {
            return;
        }

        scenariosPanel.querySelectorAll('[data-scenario]').forEach((card) => {
            card.classList.remove('is-invalid');
        });
    }

    function markScenarioValidationState(errors) {
        if (!scenariosPanel) {
            return;
        }

        const cards = Array.from(scenariosPanel.querySelectorAll('[data-scenario]'));
        cards.forEach((card) => card.classList.remove('is-invalid'));

        const invalidCards = [];
        errors.forEach((error) => {
            if (!error || typeof error !== 'object' || (!error.scenarioId && typeof error.scenarioIndex !== 'number')) {
                return;
            }
            let card = null;
            if (error.scenarioId) {
                const selector = `[data-scenario][data-scenario-id="${escapeSelector(String(error.scenarioId))}"]`;
                card = scenariosPanel.querySelector(selector);
            }
            if (!card && typeof error.scenarioIndex === 'number') {
                const indexSelector = `[data-scenario][data-index="${error.scenarioIndex}"]`;
                card = scenariosPanel.querySelector(indexSelector);
            }
            if (!card) {
                return;
            }

            card.classList.add('is-invalid');
            const body = card.querySelector('[data-scenario-body]');
            if (body) {
                body.classList.remove('d-none');
            }
            const toggle = card.querySelector('[data-action="toggle-scenario"]');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.classList.add('mdi-chevron-down');
                    icon.classList.remove('mdi-chevron-right');
                }
            }
            const scenarioId = card.dataset.scenarioId || (error.scenarioId ? String(error.scenarioId) : null);
            if (scenarioId) {
                setScenarioExpanded(scenarioId, true);
            }
            invalidCards.push(card);
        });

        if (invalidCards.length > 0) {
            invalidCards[0].scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    }

    function renderScenarioSummary() {
        if (!scenarioSummaryContainer) {
            return;
        }
        scenarioSummaryContainer.innerHTML = '';

        const hasScenarios = Array.isArray(state.scenarios) && state.scenarios.length > 0;
        const coverage = analyzeFlowCoverage(state.scenarios);

        if (!hasScenarios) {
            const empty = document.createElement('p');
            empty.className = 'text-muted small mb-0';
            empty.textContent = 'Añade tu primer escenario para visualizar el orden de evaluación.';
            scenarioSummaryContainer.appendChild(empty);

            return;
        }

        if (coverage.missingConsent) {
            const warning = document.createElement('div');
            warning.className = 'alert alert-warning d-flex flex-wrap align-items-center gap-2 small mb-2';

            const text = document.createElement('div');
            text.innerHTML = '<strong>Falta consentimiento.</strong> Ningún escenario guarda la autorización del paciente.';

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-xs btn-outline-primary';
            button.innerHTML = '<i class="mdi mdi-shield-check-outline me-1"></i> Agregar escenario de consentimiento';
            button.addEventListener('click', (event) => {
                event.preventDefault();
                addSuggestedScenarioById('consent_confirmation');
            });

            warning.appendChild(text);
            warning.appendChild(button);

            scenarioSummaryContainer.appendChild(warning);
        }

        state.scenarios.forEach((scenario, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'border rounded-3 px-3 py-2';

            const title = document.createElement('div');
            title.className = 'fw-600';
            title.textContent = `${index + 1}. ${scenario.name || scenario.id || 'Escenario sin nombre'}`;
            if (scenario.intercept_menu) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-warning-light text-warning ms-2 align-middle';
                badge.textContent = 'Responde antes del menú';
                title.appendChild(badge);
            }

            const meta = document.createElement('div');
            meta.className = 'text-muted small';
            const conditionsCount = Array.isArray(scenario.conditions) ? scenario.conditions.length : 0;
            const actionsCount = Array.isArray(scenario.actions) ? scenario.actions.length : 0;
            const stageMeta = getScenarioStageOption(readScenarioStage(scenario));
            meta.textContent = `${stageMeta.label} · ${conditionsCount} ${conditionsCount === 1 ? 'condición' : 'condiciones'} · ${actionsCount} ${actionsCount === 1 ? 'acción' : 'acciones'}`;

            wrapper.appendChild(title);
            wrapper.appendChild(meta);

            if (scenario.description) {
                const description = document.createElement('div');
                description.className = 'text-muted small';
                description.textContent = scenario.description;
                wrapper.appendChild(description);
            }

            scenarioSummaryContainer.appendChild(wrapper);
        });
    }

    function renderSuggestedScenarios() {
        if (!suggestedScenariosContainer) {
            return;
        }

        const coverage = analyzeFlowCoverage(state.scenarios);
        const availableSuggestions = SUGGESTED_SCENARIOS.filter((entry) => shouldDisplaySuggestion(entry, coverage));

        suggestedScenariosContainer.innerHTML = '';
        suggestedScenariosContainer.classList.toggle('d-none', availableSuggestions.length === 0);

        if (availableSuggestions.length === 0) {
            return;
        }

        const body = document.createElement('div');
        body.className = 'card-body';

        const heading = document.createElement('div');
        heading.className = 'd-flex justify-content-between align-items-start gap-2 mb-2';

        const headingText = document.createElement('div');
        headingText.innerHTML = '<h6 class="fw-600 mb-1">Escenarios sugeridos</h6><p class="text-muted small mb-0">Úsalos como base y ajusta condiciones o acciones según tu operación.</p>';

        const resetButton = document.createElement('button');
        resetButton.type = 'button';
        resetButton.className = 'btn btn-xs btn-outline-secondary';
        resetButton.textContent = 'Quitar sugeridos';
        resetButton.addEventListener('click', () => {
            const removableIds = new Set(availableSuggestions.map((preset) => preset.scenario.id));
            state.scenarios = state.scenarios.filter((scenario) => !removableIds.has(scenario.id));
            if (state.scenarios.length === 0) {
                const fallbackScenario = createDefaultScenario();
                state.scenarios.push(fallbackScenario);
                setScenarioExpanded(fallbackScenario.id, true);
            }
            renderScenarios();
        });

        const appliedIds = new Set(availableSuggestions.map((preset) => preset.scenario.id));
        const hasPresetApplied = state.scenarios.some((scenario) => appliedIds.has(scenario.id));
        resetButton.disabled = !hasPresetApplied;
        resetButton.classList.toggle('d-none', !hasPresetApplied);

        heading.appendChild(headingText);
        heading.appendChild(resetButton);
        body.appendChild(heading);

        const list = document.createElement('div');
        list.className = 'd-flex flex-column gap-2';

        availableSuggestions.forEach((entry) => {
            const card = document.createElement('div');
            card.className = 'border rounded-3 p-3';

            const title = document.createElement('div');
            title.className = 'fw-600';
            title.textContent = entry.title;

            const description = document.createElement('div');
            description.className = 'text-muted small mb-2';
            description.textContent = entry.description;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-primary';
            button.innerHTML = '<i class="mdi mdi-content-copy"></i> Usar este escenario';

            const exists = state.scenarios.some((scenario) => scenario.id === entry.scenario.id);
            if (exists) {
                button.disabled = true;
                button.classList.remove('btn-outline-primary');
                button.classList.add('btn-outline-secondary');
                button.textContent = 'Ya agregado';
            }

            button.addEventListener('click', () => {
                addSuggestedScenario(entry);
            });

            card.appendChild(title);
            card.appendChild(description);
            card.appendChild(button);
            list.appendChild(card);
        });

        body.appendChild(list);
        suggestedScenariosContainer.appendChild(body);
    }

    function shouldDisplaySuggestion(entry, coverage) {
        if (!entry) {
            return false;
        }

        if (!coverage) {
            return true;
        }

        if (entry.id === 'consent_confirmation') {
            return coverage.missingConsent;
        }

        return true;
    }

    function addSuggestedScenarioById(id) {
        const suggestion = SUGGESTED_SCENARIOS.find((entry) => entry.id === id);
        if (!suggestion) {
            return null;
        }

        return addSuggestedScenario(suggestion);
    }

    function addSuggestedScenario(suggestion) {
        if (!suggestion || !suggestion.scenario) {
            return null;
        }

        const scenarioId = suggestion.scenario.id;
        const existingIndex = state.scenarios.findIndex((scenario) => scenario.id === scenarioId);
        if (existingIndex !== -1) {
            setScenarioExpanded(state.scenarios[existingIndex].id, true);
            renderScenarios();

            return state.scenarios[existingIndex];
        }

        const created = cloneScenario(suggestion.scenario);
        state.scenarios.push(created);
        setScenarioExpanded(created.id, true);
        renderScenarios();

        return created;
    }

    function analyzeFlowCoverage(scenarios) {
        const summary = {
            hasConsent: false,
            missingConsent: true,
        };

        const list = Array.isArray(scenarios) ? scenarios : [];
        list.forEach((scenario) => {
            if (!summary.hasConsent && Array.isArray(scenario?.actions)) {
                summary.hasConsent = scenario.actions.some((action) => (action?.type || '') === 'store_consent');
            }
        });

        summary.missingConsent = !summary.hasConsent;

        return summary;
    }

    function refreshSimulationHints() {
        if (!simulationReplay) {
            return;
        }
        const firstOption = simulationReplay.querySelector('option:first-child');
        if (firstOption) {
            const count = Array.isArray(state.scenarios) ? state.scenarios.length : 0;
            firstOption.textContent = count > 0
                ? `Selecciona un mensaje de la bandeja (${count} escenario${count === 1 ? '' : 's'} configurado${count === 1 ? '' : 's'})`
                : 'Selecciona un mensaje de la bandeja';
        }
    }

    function renderConditions(container, scenario, onChange) {
        if (!container || !templates.conditionRow) {
            return;
        }
        container.innerHTML = '';
        scenario.conditions = Array.isArray(scenario.conditions) && scenario.conditions.length > 0
            ? scenario.conditions
            : [{type: 'always'}];

        scenario.conditions.forEach((condition, index) => {
            const row = templates.conditionRow.content.firstElementChild.cloneNode(true);
            const typeSelect = row.querySelector('[data-condition-type]');
            const fieldsContainer = row.querySelector('[data-condition-fields]');
            const removeButton = row.querySelector('[data-action="remove-condition"]');
            const helpLabel = row.querySelector('[data-condition-help]');

            if (typeSelect) {
                typeSelect.innerHTML = CONDITION_OPTIONS.map((option) => {
                    const selected = option.value === (condition.type || 'always') ? 'selected' : '';
                    return `<option value="${option.value}" ${selected}>${option.label}</option>`;
                }).join('');
                typeSelect.value = condition.type || 'always';
                typeSelect.addEventListener('change', () => {
                    condition.type = typeSelect.value;
                    delete condition.values;
                    delete condition.keywords;
                    delete condition.pattern;
                    delete condition.minutes;
                    delete condition.value;
                    renderConditionFields(fieldsContainer, condition, helpLabel);
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    scenario.conditions.splice(index, 1);
                    renderConditions(container, scenario, onChange);
                });
            }

            renderConditionFields(fieldsContainer, condition, helpLabel);

            container.appendChild(row);
        });

        if (typeof onChange === 'function') {
            onChange();
        }
    }

    function renderConditionFields(container, condition, helpElement) {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        const option = CONDITION_OPTIONS.find((entry) => entry.value === condition.type);
        const inputType = option ? option.input : null;

        if (helpElement) {
            helpElement.textContent = option?.help || '';
        }

        if (!inputType) {
            return;
        }

        if (inputType === 'boolean') {
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm';
            select.innerHTML = '<option value="true">Sí</option><option value="false">No</option>';
            select.value = String(condition.value ?? true);
            select.addEventListener('change', () => {
                condition.value = select.value === 'true';
            });
            container.appendChild(select);

            return;
        }

        if (inputType === 'keywords') {
            const textarea = document.createElement('textarea');
            textarea.className = 'form-control form-control-sm';
            textarea.rows = 2;
            textarea.placeholder = option?.placeholder || 'opcion 1, opcion 2';
            textarea.value = Array.isArray(condition.values || condition.keywords)
                ? (condition.values || condition.keywords).join(', ')
                : '';
            textarea.addEventListener('input', () => {
                const values = textarea.value.split(/[,\n]/).map((value) => value.trim()).filter(Boolean);
                if (condition.type === 'message_contains') {
                    condition.keywords = values;
                    delete condition.values;
                } else {
                    condition.values = values.map((value) => value.toLowerCase());
                    delete condition.keywords;
                }
            });
            container.appendChild(textarea);

            return;
        }

        if (inputType === 'pattern') {
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm';
            input.placeholder = option?.placeholder || '';
            input.value = condition.pattern || '';
            input.addEventListener('input', () => {
                condition.pattern = input.value.trim();
            });
            container.appendChild(input);

            return;
        }

        if (inputType === 'number') {
            const input = document.createElement('input');
            input.type = 'number';
            input.min = '0';
            input.className = 'form-control form-control-sm';
            input.value = condition.minutes ?? '';
            input.addEventListener('input', () => {
                const parsed = parseInt(input.value, 10);
                condition.minutes = Number.isNaN(parsed) ? 0 : parsed;
            });
            container.appendChild(input);

            return;
        }

        if (inputType === 'text') {
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm';
            input.placeholder = option?.placeholder || '';
            input.value = condition.value || '';
            input.addEventListener('input', () => {
                condition.value = input.value.trim();
            });
            container.appendChild(input);
        }
    }

    function renderActions(container, actions, scope, onChange) {
        if (!container || !templates.actionRow) {
            return;
        }
        container.innerHTML = '';

        actions.forEach((action, index) => {
            const row = templates.actionRow.content.firstElementChild.cloneNode(true);
            const typeSelect = row.querySelector('[data-action-type]');
            const fieldsContainer = row.querySelector('[data-action-fields]');
            const upButton = row.querySelector('[data-action="action-up"]');
            const downButton = row.querySelector('[data-action="action-down"]');
            const removeButton = row.querySelector('[data-action="remove-action"]');
            const helpLabel = row.querySelector('[data-action-help]');

            if (!action.type) {
                action.type = 'send_message';
            }

            if (typeSelect) {
                const options = getActionOptionsForCurrentMode(action.type);
                if (!options.some((option) => option.value === action.type)) {
                    action.type = options.length > 0 ? options[0].value : 'send_message';
                }
                typeSelect.innerHTML = options.map((option) => {
                    const selected = option.value === action.type ? 'selected' : '';
                    return `<option value="${option.value}" ${selected}>${option.label}</option>`;
                }).join('');
                typeSelect.value = action.type;
                typeSelect.addEventListener('change', () => {
                    action.type = typeSelect.value;
                    if (action.type === 'send_message') {
                        action.message = action.message || {type: 'text', body: ''};
                    } else if (action.type === 'send_buttons') {
                        action.message = action.message || {type: 'buttons', body: '', buttons: []};
                    } else if (action.type === 'send_list') {
                        action.message = ensureListMessage(action.message);
                    } else if (action.type === 'set_context') {
                        action.values = action.values || {};
                    } else if (action.type === 'store_consent') {
                        action.value = action.value ?? true;
                    } else if (action.type === 'lookup_patient') {
                        action.field = action.field || 'cedula';
                        action.source = action.source || 'message';
                    } else if (action.type === 'conditional') {
                        action.condition = action.condition || {type: 'patient_found', value: true};
                        action.then = Array.isArray(action.then) ? action.then : [];
                        action.else = Array.isArray(action.else) ? action.else : [];
                    }
                    renderActions(container, actions, scope, onChange);
                });
                const advancedOnly = isActionHiddenInSimpleMode(action.type);
                typeSelect.disabled = advancedOnly;
                row.classList.toggle('requires-advanced', advancedOnly);
            }

            if (upButton) {
                upButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (index === 0) {
                        return;
                    }
                    const temp = actions[index - 1];
                    actions[index - 1] = actions[index];
                    actions[index] = temp;
                    renderActions(container, actions, scope, onChange);
                });
            }

            if (downButton) {
                downButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (index === actions.length - 1) {
                        return;
                    }
                    const temp = actions[index + 1];
                    actions[index + 1] = actions[index];
                    actions[index] = temp;
                    renderActions(container, actions, scope, onChange);
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    actions.splice(index, 1);
                    renderActions(container, actions, scope, onChange);
                });
            }

            renderActionFields(fieldsContainer, action, scope, helpLabel, {simpleMode: uiState.simpleMode, onChange});

            container.appendChild(row);
        });

        if (scope && Array.isArray(state.menu?.options) && state.menu.options.includes(scope)) {
            renderMenuPreview();
        }

        if (typeof onChange === 'function') {
            onChange();
        }
    }

    function renderActionFields(container, action, scope, helpElement, options = {}) {
        if (!container) {
            return;
        }
        container.innerHTML = '';

        if (helpElement) {
            const option = ACTION_OPTIONS.find((entry) => entry.value === action.type);
            helpElement.textContent = option?.help || '';
        }

        const simpleMode = Boolean(options.simpleMode);
        const onChange = typeof options.onChange === 'function' ? options.onChange : null;
        if (simpleMode && isActionHiddenInSimpleMode(action.type)) {
            const notice = document.createElement('div');
            notice.className = 'alert alert-info small mb-0';
            notice.textContent = 'Esta acción se edita en modo avanzado.';
            container.appendChild(notice);

            return;
        }

        if (action.type === 'send_message') {
            action.message = ensureSimpleMessage(action.message);
            renderSingleMessageComposer(container, action.message);

            return;
        }

        if (action.type === 'send_sequence') {
            action.messages = Array.isArray(action.messages) && action.messages.length > 0
                ? action.messages.map((message) => ensureSimpleMessage(message))
                : [ensureSimpleMessage({type: 'text', body: ''})];
            renderSequenceComposer(container, action);

            return;
        }

        if (action.type === 'send_template') {
            renderTemplateSelector(container, action);

            return;
        }

        if (action.type === 'send_buttons') {
            action.message = action.message || {type: 'buttons', body: '', buttons: []};
            const bodyLabel = document.createElement('label');
            bodyLabel.className = 'form-label small text-muted';
            bodyLabel.textContent = 'Mensaje';

            const bodyInput = document.createElement('textarea');
            bodyInput.className = 'form-control form-control-sm mb-2';
            bodyInput.rows = 3;
            bodyInput.value = action.message.body || '';
            bodyInput.addEventListener('input', () => {
                action.message.type = 'buttons';
                action.message.body = bodyInput.value;
            });

            const buttonsHeader = document.createElement('div');
            buttonsHeader.className = 'd-flex justify-content-between align-items-center mb-2';
            buttonsHeader.innerHTML = '<span class="small fw-600">Botones</span>';

            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'btn btn-xs btn-outline-primary';
            addButton.innerHTML = '<i class="mdi mdi-plus"></i> Añadir botón';
            addButton.addEventListener('click', () => {
                action.message.buttons = action.message.buttons || [];
                action.message.buttons.push({id: '', title: ''});
                renderButtonsList(buttonsContainer, action);
            });
            buttonsHeader.appendChild(addButton);

            const buttonsContainer = document.createElement('div');
            renderButtonsList(buttonsContainer, action);

            container.appendChild(bodyLabel);
            container.appendChild(bodyInput);
            container.appendChild(buttonsHeader);
            container.appendChild(buttonsContainer);

            return;
        }

        if (action.type === 'send_list') {
            action.message = ensureListMessage(action.message);

            const bodyLabel = document.createElement('label');
            bodyLabel.className = 'form-label small text-muted';
            bodyLabel.textContent = 'Texto introductorio';

            const bodyInput = document.createElement('textarea');
            bodyInput.className = 'form-control form-control-sm mb-2';
            bodyInput.rows = 3;
            bodyInput.value = action.message.body || '';
            bodyInput.addEventListener('input', () => {
                action.message.type = 'list';
                action.message.body = bodyInput.value;
            });

            const buttonLabel = document.createElement('label');
            buttonLabel.className = 'form-label small text-muted';
            buttonLabel.textContent = 'Texto del botón principal';

            const buttonInput = document.createElement('input');
            buttonInput.type = 'text';
            buttonInput.className = 'form-control form-control-sm mb-2';
            buttonInput.value = action.message.button || 'Ver opciones';
            buttonInput.addEventListener('input', () => {
                action.message.button = buttonInput.value || 'Ver opciones';
            });

            const sectionsHeader = document.createElement('div');
            sectionsHeader.className = 'd-flex justify-content-between align-items-center mb-2';
            sectionsHeader.innerHTML = '<span class="small fw-600">Secciones y opciones</span>';

            const addSectionButton = document.createElement('button');
            addSectionButton.type = 'button';
            addSectionButton.className = 'btn btn-xs btn-outline-primary';
            addSectionButton.innerHTML = '<i class="mdi mdi-plus"></i> Añadir sección';
            addSectionButton.addEventListener('click', () => {
                action.message.sections = action.message.sections || [];
                if (action.message.sections.length >= MENU_LIST_SECTION_LIMIT) {
                    window.alert(`Solo puedes añadir hasta ${MENU_LIST_SECTION_LIMIT} secciones en una lista.`);
                    return;
                }
                action.message.sections.push(createDefaultListSection());
                renderListSections(listContainer, action.message.sections, action.message);
            });
            sectionsHeader.appendChild(addSectionButton);

            const listContainer = document.createElement('div');
            renderListSections(listContainer, action.message.sections, action.message);

            container.appendChild(bodyLabel);
            container.appendChild(bodyInput);
            container.appendChild(buttonLabel);
            container.appendChild(buttonInput);
            container.appendChild(sectionsHeader);
            container.appendChild(listContainer);

            return;
        }

        if (action.type === 'set_state') {
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control form-control-sm';
            input.placeholder = 'Ej. menu_principal';
            input.value = action.state || '';
            input.addEventListener('input', () => {
                action.state = input.value.trim();
            });
            container.appendChild(input);

            return;
        }

        if (action.type === 'set_context') {
            action.values = action.values || {};
            const wrapper = document.createElement('div');
            renderContextList(wrapper, action);

            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'btn btn-xs btn-outline-primary mt-2';
            addButton.innerHTML = '<i class="mdi mdi-plus"></i> Añadir par clave-valor';
            addButton.addEventListener('click', () => {
                action.values['nuevo_campo'] = '';
                renderContextList(wrapper, action);
            });

            container.appendChild(wrapper);
            container.appendChild(addButton);

            return;
        }

        if (action.type === 'store_consent') {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input me-2';
            checkbox.checked = action.value !== false;
            checkbox.addEventListener('change', () => {
                action.value = checkbox.checked;
            });

            const label = document.createElement('label');
            label.className = 'form-check-label small';
            label.textContent = 'Marcar como aceptado';

            const wrapper = document.createElement('div');
            wrapper.className = 'form-check form-switch';
            wrapper.appendChild(checkbox);
            wrapper.appendChild(label);

            container.appendChild(wrapper);

            return;
        }

        if (action.type === 'lookup_patient') {
            action.field = action.field || 'cedula';
            action.source = action.source || 'message';
            const fieldSelect = document.createElement('select');
            fieldSelect.className = 'form-select form-select-sm mb-2';
            fieldSelect.innerHTML = '<option value="cedula">Cédula / Historia clínica</option>';
            fieldSelect.value = action.field;
            fieldSelect.addEventListener('change', () => {
                action.field = fieldSelect.value;
            });

            const sourceSelect = document.createElement('select');
            sourceSelect.className = 'form-select form-select-sm';
            sourceSelect.innerHTML = '<option value="message">Usar mensaje actual</option><option value="context">Usar valor guardado</option>';
            sourceSelect.value = action.source;
            sourceSelect.addEventListener('change', () => {
                action.source = sourceSelect.value;
            });

            container.appendChild(fieldSelect);
            container.appendChild(sourceSelect);

            return;
        }

        if (action.type === 'conditional') {
            action.condition = action.condition || {type: 'patient_found', value: true};
            action.then = Array.isArray(action.then) ? action.then : [];
            action.else = Array.isArray(action.else) ? action.else : [];

            const conditionSelect = document.createElement('select');
            conditionSelect.className = 'form-select form-select-sm mb-2';
            conditionSelect.innerHTML = '<option value="patient_found">Si existe paciente</option><option value="has_consent">Si tiene consentimiento</option>';
            conditionSelect.value = action.condition.type || 'patient_found';
            conditionSelect.addEventListener('change', () => {
                action.condition.type = conditionSelect.value;
            });

            const thenLabel = document.createElement('div');
            thenLabel.className = 'small text-muted mb-1';
            thenLabel.textContent = 'Si la condición se cumple';

            const thenContainer = document.createElement('div');
            renderActions(thenContainer, action.then, scope, onChange);

            const elseLabel = document.createElement('div');
            elseLabel.className = 'small text-muted mt-3 mb-1';
            elseLabel.textContent = 'Si la condición no se cumple';

            const elseContainer = document.createElement('div');
            renderActions(elseContainer, action.else, scope, onChange);

            container.appendChild(conditionSelect);
            container.appendChild(thenLabel);
            container.appendChild(thenContainer);
            container.appendChild(elseLabel);
            container.appendChild(elseContainer);

            return;
        }

        if (action.type === 'goto_menu' || action.type === 'upsert_patient_from_context') {
            const info = document.createElement('div');
            info.className = 'text-muted small';
            info.textContent = action.type === 'goto_menu'
                ? 'Mostrará el menú configurado debajo.'
                : 'Asocia la conversación con la cédula actual si no existe en la base local.';
            container.appendChild(info);
        }
    }

    function renderSingleMessageComposer(container, message) {
        if (!container) {
            return;
        }
        container.innerHTML = '';

        const hint = document.createElement('p');
        hint.className = 'text-muted small';
        hint.textContent = 'Selecciona el tipo de contenido y completa los campos requeridos.';
        container.appendChild(hint);

        const composer = document.createElement('div');
        buildMessageComposer(composer, message);
        container.appendChild(composer);
    }

    function renderSequenceComposer(container, action) {
        if (!container) {
            return;
        }
        container.innerHTML = '';

        const hint = document.createElement('p');
        hint.className = 'text-muted small';
        hint.textContent = 'Los mensajes se enviarán en el orden definido. Puedes mezclar texto, multimedia y ubicaciones.';
        container.appendChild(hint);

        const list = document.createElement('div');
        list.className = 'd-flex flex-column gap-3';
        container.appendChild(list);

        const addButton = document.createElement('button');
        addButton.type = 'button';
        addButton.className = 'btn btn-xs btn-outline-primary';
        addButton.innerHTML = '<i class="mdi mdi-plus"></i> Añadir mensaje';
        addButton.addEventListener('click', () => {
            action.messages.push(ensureSimpleMessage({type: 'text', body: ''}));
            renderRows();
        });
        container.appendChild(addButton);

        const renderRows = () => {
            list.innerHTML = '';
            if (action.messages.length === 0) {
                action.messages.push(ensureSimpleMessage({type: 'text', body: ''}));
            }

            action.messages.forEach((message, index) => {
                const card = document.createElement('div');
                card.className = 'border rounded-3 p-3';

                const header = document.createElement('div');
                header.className = 'd-flex justify-content-between align-items-center mb-2';

                const title = document.createElement('span');
                title.className = 'fw-600';
                title.textContent = `Paso ${index + 1}`;
                header.appendChild(title);

                const controls = document.createElement('div');
                controls.className = 'btn-group btn-group-sm';

                const upButton = document.createElement('button');
                upButton.type = 'button';
                upButton.className = 'btn btn-outline-secondary';
                upButton.innerHTML = '<i class="mdi mdi-arrow-up"></i>';
                upButton.disabled = index === 0;
                upButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (index === 0) {
                        return;
                    }
                    const temp = action.messages[index - 1];
                    action.messages[index - 1] = action.messages[index];
                    action.messages[index] = temp;
                    renderRows();
                });
                controls.appendChild(upButton);

                const downButton = document.createElement('button');
                downButton.type = 'button';
                downButton.className = 'btn btn-outline-secondary';
                downButton.innerHTML = '<i class="mdi mdi-arrow-down"></i>';
                downButton.disabled = index === action.messages.length - 1;
                downButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (index === action.messages.length - 1) {
                        return;
                    }
                    const temp = action.messages[index + 1];
                    action.messages[index + 1] = action.messages[index];
                    action.messages[index] = temp;
                    renderRows();
                });
                controls.appendChild(downButton);

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'btn btn-outline-danger';
                removeButton.innerHTML = '<i class="mdi mdi-close"></i>';
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    action.messages.splice(index, 1);
                    renderRows();
                });
                controls.appendChild(removeButton);

                header.appendChild(controls);
                card.appendChild(header);

                const body = document.createElement('div');
                buildMessageComposer(body, action.messages[index]);
                card.appendChild(body);

                list.appendChild(card);
            });
        };

        renderRows();
    }

    function renderTemplateSelector(container, action) {
        if (!container) {
            return;
        }
        container.innerHTML = '';

        const info = document.createElement('div');
        info.className = 'alert alert-info small';
        info.innerHTML = 'Utiliza una plantilla aprobada para notificaciones oficiales. Consulta <a href="https://www.facebook.com/business/help/2055875911190067" target="_blank" rel="noopener">los requisitos de Meta</a> y nuestros <a href="https://medforge.help/whatsapp/templates" target="_blank" rel="noopener">ejemplos sugeridos</a>.';
        container.appendChild(info);

        if (!Array.isArray(templateCatalog) || templateCatalog.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-muted small mb-0';
            empty.innerHTML = 'No hay plantillas sincronizadas. Revisa la pestaña <a href="/whatsapp/templates" target="_blank" rel="noopener">Plantillas</a> para sincronizarlas con Meta.';
            container.appendChild(empty);
            delete action.template;

            return;
        }

        const selectLabel = document.createElement('label');
        selectLabel.className = 'form-label small text-muted';
        selectLabel.textContent = 'Plantilla disponible';
        container.appendChild(selectLabel);

        const select = document.createElement('select');
        select.className = 'form-select form-select-sm mb-2';
        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = 'Selecciona una plantilla';
        select.appendChild(placeholderOption);

        templateCatalog.forEach((template, index) => {
            const option = document.createElement('option');
            option.value = String(index);
            const categoryLabel = template.category ? ` · ${template.category}` : '';
            option.textContent = `${template.language.toUpperCase()} · ${template.name}${categoryLabel}`;
            select.appendChild(option);
        });
        container.appendChild(select);

        const details = document.createElement('div');
        details.className = 'bg-light rounded-3 p-3 small';
        container.appendChild(details);

        const applyTemplate = (template) => {
            if (!template) {
                delete action.template;
                details.innerHTML = '<span class="text-muted">Selecciona una plantilla para ver sus componentes.</span>';
                return;
            }
            action.template = {
                name: template.name,
                language: template.language,
                category: template.category,
                components: template.components,
            };

            details.innerHTML = '';

            const header = document.createElement('div');
            header.className = 'fw-600 mb-2';
            header.textContent = template.name;
            details.appendChild(header);

            const meta = document.createElement('div');
            meta.className = 'text-muted mb-2';
            meta.textContent = `Idioma: ${template.language.toUpperCase()}${template.category ? ` · ${template.category}` : ''}`;
            details.appendChild(meta);

            if (Array.isArray(template.components) && template.components.length > 0) {
                const list = document.createElement('ul');
                list.className = 'mb-0 ps-3';
                template.components.forEach((component) => {
                    const item = document.createElement('li');
                    const typeLabel = component.type || '';
                    const placeholders = Array.isArray(component.placeholders) && component.placeholders.length > 0
                        ? ` · Variables: ${component.placeholders.map((value) => `{{${value}}}`).join(', ')}`
                        : '';
                    item.textContent = `${typeLabel}${placeholders}`;
                    list.appendChild(item);
                });
                details.appendChild(list);
            } else {
                const empty = document.createElement('div');
                empty.className = 'text-muted';
                empty.textContent = 'Esta plantilla no requiere variables.';
                details.appendChild(empty);
            }
        };

        const findCurrentTemplateIndex = () => {
            if (!action.template) {
                return -1;
            }
            return templateCatalog.findIndex((template) => {
                return template.name === action.template.name && template.language === action.template.language;
            });
        };

        select.addEventListener('change', () => {
            const selectedIndex = parseInt(select.value, 10);
            if (Number.isNaN(selectedIndex) || !templateCatalog[selectedIndex]) {
                applyTemplate(null);
                return;
            }
            applyTemplate(templateCatalog[selectedIndex]);
        });

        const currentIndex = findCurrentTemplateIndex();
        if (currentIndex >= 0) {
            select.value = String(currentIndex);
            applyTemplate(templateCatalog[currentIndex]);
        } else {
            applyTemplate(null);
        }
    }

    function renderButtonsList(container, action) {
        if (!container || !templates.buttonRow) {
            return;
        }
        container.innerHTML = '';
        const type = action.message.type || 'text';
        if (type !== 'buttons') {
            const hint = document.createElement('p');
            hint.className = 'text-muted small mb-0';
            hint.textContent = 'Este mensaje se enviará como texto simple. Cambia el tipo a "Botones interactivos" para añadir botones.';
            container.appendChild(hint);

            return;
        }

        action.message.buttons = Array.isArray(action.message.buttons) ? action.message.buttons : [];

        action.message.buttons.forEach((button, index) => {
            const row = templates.buttonRow.content.firstElementChild.cloneNode(true);
            const titleInput = row.querySelector('[data-button-title]');
            const idInput = row.querySelector('[data-button-id]');
            const removeButton = row.querySelector('[data-action="remove-button"]');

            if (titleInput) {
                titleInput.value = button.title || '';
                titleInput.addEventListener('input', () => {
                    button.title = titleInput.value;
                    if (action.message === state.menu.message) {
                        renderMenuPreview();
                    }
                });
            }

            if (idInput) {
                idInput.value = button.id || '';
                idInput.addEventListener('input', () => {
                    button.id = idInput.value;
                    if (action.message === state.menu.message) {
                        renderMenuPreview();
                    }
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    action.message.buttons.splice(index, 1);
                    renderButtonsList(container, action);
                    if (action.message === state.menu.message) {
                        renderMenuPreview();
                    }
                });
            }

            container.appendChild(row);
        });
    }

    function buildMessageComposer(container, message) {
        if (!container) {
            return;
        }
        container.innerHTML = '';

        const wrapper = document.createElement('div');
        wrapper.className = 'd-flex flex-column gap-2';
        container.appendChild(wrapper);

        const typeLabel = document.createElement('label');
        typeLabel.className = 'form-label small text-muted mb-0';
        typeLabel.textContent = 'Formato de mensaje';
        wrapper.appendChild(typeLabel);

        const typeSelect = document.createElement('select');
        typeSelect.className = 'form-select form-select-sm';
        [
            {value: 'text', label: 'Texto'},
            {value: 'image', label: 'Imagen'},
            {value: 'document', label: 'Documento'},
            {value: 'location', label: 'Ubicación'},
        ].forEach((option) => {
            const node = document.createElement('option');
            node.value = option.value;
            node.textContent = option.label;
            if (option.value === message.type) {
                node.selected = true;
            }
            typeSelect.appendChild(node);
        });
        wrapper.appendChild(typeSelect);

        const dynamic = document.createElement('div');
        dynamic.className = 'd-flex flex-column gap-2';
        wrapper.appendChild(dynamic);

        const applyType = (nextType) => {
            const normalized = ensureSimpleMessage({type: nextType});
            Object.keys(message).forEach((key) => {
                delete message[key];
            });
            Object.assign(message, normalized);
        };

        const renderFields = () => {
            dynamic.innerHTML = '';

            if (message.type === 'text') {
                const textarea = document.createElement('textarea');
                textarea.className = 'form-control form-control-sm';
                textarea.rows = 3;
                textarea.placeholder = 'Escribe el mensaje a enviar';
                textarea.value = message.body || '';
                textarea.addEventListener('input', () => {
                    message.body = textarea.value;
                });
                dynamic.appendChild(textarea);

                return;
            }

            if (message.type === 'image') {
                const urlLabel = document.createElement('label');
                urlLabel.className = 'form-label small text-muted mb-0';
                urlLabel.textContent = 'URL de la imagen';
                dynamic.appendChild(urlLabel);

                const urlInput = document.createElement('input');
                urlInput.type = 'url';
                urlInput.className = 'form-control form-control-sm';
                urlInput.placeholder = 'https://...';
                urlInput.value = message.link || '';
                urlInput.addEventListener('input', () => {
                    message.link = urlInput.value.trim();
                });
                dynamic.appendChild(urlInput);

                const captionLabel = document.createElement('label');
                captionLabel.className = 'form-label small text-muted mb-0';
                captionLabel.textContent = 'Pie (opcional)';
                dynamic.appendChild(captionLabel);

                const captionInput = document.createElement('textarea');
                captionInput.className = 'form-control form-control-sm';
                captionInput.rows = 2;
                captionInput.placeholder = 'Ej. Te compartimos el resultado';
                captionInput.value = message.caption || '';
                captionInput.addEventListener('input', () => {
                    message.caption = captionInput.value;
                });
                dynamic.appendChild(captionInput);

                return;
            }

            if (message.type === 'document') {
                const urlLabel = document.createElement('label');
                urlLabel.className = 'form-label small text-muted mb-0';
                urlLabel.textContent = 'URL del documento';
                dynamic.appendChild(urlLabel);

                const urlInput = document.createElement('input');
                urlInput.type = 'url';
                urlInput.className = 'form-control form-control-sm';
                urlInput.placeholder = 'https://...';
                urlInput.value = message.link || '';
                urlInput.addEventListener('input', () => {
                    message.link = urlInput.value.trim();
                });
                dynamic.appendChild(urlInput);

                const filenameLabel = document.createElement('label');
                filenameLabel.className = 'form-label small text-muted mb-0';
                filenameLabel.textContent = 'Nombre del archivo (opcional)';
                dynamic.appendChild(filenameLabel);

                const filenameInput = document.createElement('input');
                filenameInput.type = 'text';
                filenameInput.className = 'form-control form-control-sm';
                filenameInput.placeholder = 'Informe_resultados.pdf';
                filenameInput.value = message.filename || '';
                filenameInput.addEventListener('input', () => {
                    message.filename = filenameInput.value.trim();
                });
                dynamic.appendChild(filenameInput);

                const captionLabel = document.createElement('label');
                captionLabel.className = 'form-label small text-muted mb-0';
                captionLabel.textContent = 'Descripción (opcional)';
                dynamic.appendChild(captionLabel);

                const captionInput = document.createElement('textarea');
                captionInput.className = 'form-control form-control-sm';
                captionInput.rows = 2;
                captionInput.placeholder = 'Descripción breve del documento';
                captionInput.value = message.caption || '';
                captionInput.addEventListener('input', () => {
                    message.caption = captionInput.value;
                });
                dynamic.appendChild(captionInput);

                return;
            }

            if (message.type === 'location') {
                const grid = document.createElement('div');
                grid.className = 'row g-2';

                const latWrapper = document.createElement('div');
                latWrapper.className = 'col-12 col-md-6';
                const latLabel = document.createElement('label');
                latLabel.className = 'form-label small text-muted mb-0';
                latLabel.textContent = 'Latitud';
                latWrapper.appendChild(latLabel);
                const latInput = document.createElement('input');
                latInput.type = 'number';
                latInput.step = 'any';
                latInput.className = 'form-control form-control-sm';
                latInput.placeholder = '4.7110';
                latInput.value = message.latitude || '';
                latInput.addEventListener('input', () => {
                    message.latitude = latInput.value;
                });
                latWrapper.appendChild(latInput);
                grid.appendChild(latWrapper);

                const lngWrapper = document.createElement('div');
                lngWrapper.className = 'col-12 col-md-6';
                const lngLabel = document.createElement('label');
                lngLabel.className = 'form-label small text-muted mb-0';
                lngLabel.textContent = 'Longitud';
                lngWrapper.appendChild(lngLabel);
                const lngInput = document.createElement('input');
                lngInput.type = 'number';
                lngInput.step = 'any';
                lngInput.className = 'form-control form-control-sm';
                lngInput.placeholder = '-74.0721';
                lngInput.value = message.longitude || '';
                lngInput.addEventListener('input', () => {
                    message.longitude = lngInput.value;
                });
                lngWrapper.appendChild(lngInput);
                grid.appendChild(lngWrapper);

                dynamic.appendChild(grid);

                const nameLabel = document.createElement('label');
                nameLabel.className = 'form-label small text-muted mb-0';
                nameLabel.textContent = 'Nombre del lugar (opcional)';
                dynamic.appendChild(nameLabel);

                const nameInput = document.createElement('input');
                nameInput.type = 'text';
                nameInput.className = 'form-control form-control-sm';
                nameInput.placeholder = 'Sede principal';
                nameInput.value = message.name || '';
                nameInput.addEventListener('input', () => {
                    message.name = nameInput.value;
                });
                dynamic.appendChild(nameInput);

                const addressLabel = document.createElement('label');
                addressLabel.className = 'form-label small text-muted mb-0';
                addressLabel.textContent = 'Dirección (opcional)';
                dynamic.appendChild(addressLabel);

                const addressInput = document.createElement('textarea');
                addressInput.className = 'form-control form-control-sm';
                addressInput.rows = 2;
                addressInput.placeholder = 'Carrera 7 # 123-45, Bogotá';
                addressInput.value = message.address || '';
                addressInput.addEventListener('input', () => {
                    message.address = addressInput.value;
                });
                dynamic.appendChild(addressInput);

                return;
            }
        };

        typeSelect.addEventListener('change', () => {
            applyType(typeSelect.value);
            renderFields();
        });

        renderFields();
    }

    function ensureSimpleMessage(message) {
        const allowedTypes = ['text', 'image', 'document', 'location'];
        const source = (message && typeof message === 'object') ? message : {};
        let type = typeof source.type === 'string' ? source.type.toLowerCase() : 'text';
        if (!allowedTypes.includes(type)) {
            type = 'text';
        }

        const normalized = {type};

        normalized.body = typeof source.body === 'string' ? source.body : '';

        if (type === 'image' || type === 'document') {
            normalized.link = typeof source.link === 'string' ? source.link : '';
            normalized.caption = typeof source.caption === 'string' ? source.caption : '';
            if (type === 'document') {
                normalized.filename = typeof source.filename === 'string' ? source.filename : '';
            }
        }

        if (type === 'location') {
            normalized.latitude = typeof source.latitude === 'number' || typeof source.latitude === 'string'
                ? String(source.latitude)
                : '';
            normalized.longitude = typeof source.longitude === 'number' || typeof source.longitude === 'string'
                ? String(source.longitude)
                : '';
            normalized.name = typeof source.name === 'string' ? source.name : '';
            normalized.address = typeof source.address === 'string' ? source.address : '';
        }

        return normalized;
    }

    function validateSimpleMessagePayload(message, contextLabel) {
        const errors = [];
        const normalized = ensureSimpleMessage(message);

        if (normalized.type === 'text') {
            if (!normalized.body || normalized.body.trim() === '') {
                errors.push(`${contextLabel}: agrega contenido al mensaje de texto.`);
            }
            return errors;
        }

        if (normalized.type === 'image' || normalized.type === 'document') {
            if (!normalized.link || !isValidHttpUrl(normalized.link)) {
                errors.push(`${contextLabel}: especifica una URL pública válida para el ${normalized.type === 'image' ? 'contenido' : 'documento'}.`);
            }
            return errors;
        }

        if (normalized.type === 'location') {
            const latitude = parseFloat(normalized.latitude);
            const longitude = parseFloat(normalized.longitude);
            if (Number.isNaN(latitude) || latitude < -90 || latitude > 90) {
                errors.push(`${contextLabel}: la latitud debe estar entre -90 y 90.`);
            }
            if (Number.isNaN(longitude) || longitude < -180 || longitude > 180) {
                errors.push(`${contextLabel}: la longitud debe estar entre -180 y 180.`);
            }
            return errors;
        }

        return errors;
    }

    function isValidHttpUrl(value) {
        if (!value || typeof value !== 'string') {
            return false;
        }
        try {
            const url = new URL(value);
            return url.protocol === 'http:' || url.protocol === 'https:';
        } catch (error) {
            return false;
        }
    }

    function renderContextList(container, action) {
        if (!container || !templates.contextRow) {
            return;
        }
        container.innerHTML = '';
        const entries = Object.keys(action.values || {});
        if (entries.length === 0) {
            action.values = {'estado': 'menu_principal'};
        }

        Object.keys(action.values).forEach((key) => {
            const row = templates.contextRow.content.firstElementChild.cloneNode(true);
            const keyInput = row.querySelector('[data-context-key]');
            const valueInput = row.querySelector('[data-context-value]');
            const removeButton = row.querySelector('[data-action="remove-context"]');

            if (keyInput) {
                keyInput.value = key;
                keyInput.addEventListener('input', () => {
                    const newKey = keyInput.value.trim();
                    if (newKey && newKey !== key) {
                        action.values[newKey] = action.values[key];
                        delete action.values[key];
                        renderContextList(container, action);
                    }
                });
            }

            if (valueInput) {
                valueInput.value = action.values[key] || '';
                valueInput.addEventListener('input', () => {
                    action.values[key] = valueInput.value;
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    delete action.values[key];
                    renderContextList(container, action);
                });
            }

            container.appendChild(row);
        });
    }

    function ensureListMessage(message) {
        if (!message || typeof message !== 'object') {
            message = {};
        }
        message.type = 'list';
        message.body = message.body || '';
        message.button = message.button || 'Ver opciones';
        message.footer = message.footer || '';
        message.sections = Array.isArray(message.sections) && message.sections.length > 0
            ? message.sections
            : [createDefaultListSection()];

        message.sections = message.sections.map((section) => {
            const rows = Array.isArray(section?.rows) && section.rows.length > 0
                ? section.rows
                : [{id: '', title: '', description: ''}];
            return {
                title: section?.title || '',
                rows: rows.map((row) => ({
                    id: row?.id || '',
                    title: row?.title || '',
                    description: row?.description || '',
                })),
            };
        });

        return message;
    }

    function createDefaultListSection() {
        return {
            title: 'Opciones disponibles',
            rows: [
                {id: 'menu_informacion', title: 'Información general', description: ''},
                {id: 'menu_agendar', title: 'Agendar cita', description: ''},
            ],
        };
    }

    function renderListSections(container, sections, owner) {
        if (!container || !templates.menuListSection) {
            return;
        }
        container.innerHTML = '';

        const target = Array.isArray(sections) ? sections : [];
        if (target.length === 0) {
            target.push(createDefaultListSection());
        }

        const updatePreview = owner === state.menu.message;

        target.forEach((section, index) => {
            const node = templates.menuListSection.content.firstElementChild.cloneNode(true);
            const titleInput = node.querySelector('[data-section-title]');
            const rowsContainer = node.querySelector('[data-section-rows]');
            const addRowButton = node.querySelector('[data-action="add-row"]');
            const removeButton = node.querySelector('[data-action="remove-section"]');
            const moveUpButton = node.querySelector('[data-action="section-up"]');
            const moveDownButton = node.querySelector('[data-action="section-down"]');

            if (titleInput) {
                titleInput.value = section.title || '';
                titleInput.addEventListener('input', () => {
                    section.title = titleInput.value;
                    if (updatePreview) {
                        renderMenuPreview();
                    }
                });
            }

            if (addRowButton) {
                addRowButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    section.rows = Array.isArray(section.rows) ? section.rows : [];
                    if (section.rows.length >= MENU_LIST_ROW_LIMIT) {
                        window.alert(`Cada sección admite hasta ${MENU_LIST_ROW_LIMIT} opciones.`);
                        return;
                    }
                    section.rows.push({id: '', title: '', description: ''});
                    renderListRows(rowsContainer, section, target, owner);
                    if (updatePreview) {
                        renderMenuPreview();
                    }
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (target.length === 1) {
                        window.alert('La lista debe tener al menos una sección.');
                        return;
                    }
                    target.splice(index, 1);
                    renderListSections(container, target, owner);
                    if (updatePreview) {
                        renderMenuPreview();
                    }
                });
            }

            if (moveUpButton) {
                moveUpButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (index === 0) {
                        return;
                    }
                    const temp = target[index - 1];
                    target[index - 1] = target[index];
                    target[index] = temp;
                    renderListSections(container, target, owner);
                    if (updatePreview) {
                        renderMenuPreview();
                    }
                });
            }

            if (moveDownButton) {
                moveDownButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (index === target.length - 1) {
                        return;
                    }
                    const temp = target[index + 1];
                    target[index + 1] = target[index];
                    target[index] = temp;
                    renderListSections(container, target, owner);
                    if (updatePreview) {
                        renderMenuPreview();
                    }
                });
            }

            renderListRows(rowsContainer, section, target, owner);
            container.appendChild(node);
        });
    }

    function renderListRows(container, section, sections, owner) {
        if (!container || !templates.menuListRow) {
            return;
        }
        container.innerHTML = '';
        section.rows = Array.isArray(section.rows) && section.rows.length > 0
            ? section.rows
            : [{id: '', title: '', description: ''}];

        const updatePreview = owner === state.menu.message;

        section.rows.forEach((row, index) => {
            const node = templates.menuListRow.content.firstElementChild.cloneNode(true);
            const idInput = node.querySelector('[data-row-id]');
            const titleInput = node.querySelector('[data-row-title]');
            const descriptionInput = node.querySelector('[data-row-description]');
            const removeButton = node.querySelector('[data-action="remove-row"]');

            if (idInput) {
                idInput.value = row.id || '';
                idInput.addEventListener('input', () => {
                    row.id = idInput.value.trim();
                    if (updatePreview) {
                        renderMenuPreview();
                    }
                });
            }

            if (titleInput) {
                titleInput.value = row.title || '';
                titleInput.addEventListener('input', () => {
                    row.title = titleInput.value;
                    if (updatePreview) {
                        renderMenuPreview();
                    }
                });
            }

            if (descriptionInput) {
                descriptionInput.value = row.description || '';
                descriptionInput.addEventListener('input', () => {
                    row.description = descriptionInput.value;
                    if (updatePreview) {
                        renderMenuPreview();
                    }
                });
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    section.rows.splice(index, 1);
                    if (section.rows.length === 0) {
                        section.rows.push({id: '', title: '', description: ''});
                    }
                    renderListRows(container, section, sections, owner);
                    if (updatePreview) {
                        renderMenuPreview();
                    }
                });
            }

            container.appendChild(node);
        });

        if (owner) {
            owner.sections = sections;
        }
    }

    function renderMenu() {
        if (!menuPanel) {
            return;
        }
        menuPanel.innerHTML = '';
        state.menu = state.menu || createDefaultMenu();
        state.menu.message = state.menu.message || {};

        const allowedTypes = ['text', 'buttons', 'list'];
        const rawType = typeof state.menu.message.type === 'string' ? state.menu.message.type.toLowerCase() : 'text';
        const messageType = allowedTypes.includes(rawType) ? rawType : 'text';
        state.menu.message.type = messageType;

        if (messageType === 'buttons') {
            state.menu.message.buttons = Array.isArray(state.menu.message.buttons)
                ? state.menu.message.buttons.slice(0, MENU_BUTTON_LIMIT)
                : [];
        } else if (messageType === 'list') {
            state.menu.message = ensureListMessage(state.menu.message);
        } else {
            state.menu.message.buttons = [];
            delete state.menu.message.sections;
        }

        state.menu.options = Array.isArray(state.menu.options) ? state.menu.options : [];

        const layout = document.createElement('div');
        layout.className = 'row g-4 align-items-stretch';
        menuPanel.appendChild(layout);

        const editorColumn = document.createElement('div');
        editorColumn.className = 'col-12 col-xl-7 d-flex flex-column gap-3';
        const sidebarColumn = document.createElement('div');
        sidebarColumn.className = 'col-12 col-xl-5 d-flex flex-column gap-3';
        layout.appendChild(editorColumn);
        layout.appendChild(sidebarColumn);

        const messageCard = document.createElement('div');
        messageCard.className = 'card border-0 shadow-sm';
        const messageBody = document.createElement('div');
        messageBody.className = 'card-body';
        messageCard.appendChild(messageBody);

        const messageHeading = document.createElement('h6');
        messageHeading.className = 'fw-600 mb-3';
        messageHeading.textContent = 'Mensaje principal del menú';
        messageBody.appendChild(messageHeading);

        const messageIntro = document.createElement('p');
        messageIntro.className = 'text-muted small';
        messageIntro.textContent = 'Diseña el mensaje de bienvenida con botones o listas interactivas. Agrega etiquetas y palabras clave para que el sistema identifique cada intención. Este mensaje se envía automáticamente cuando el paciente escribe "hola", "menú" u otro saludo similar.';
        messageBody.appendChild(messageIntro);

        if (MENU_PRESETS.length > 0) {
            const presetRow = document.createElement('div');
            presetRow.className = 'd-flex flex-wrap gap-2 align-items-center mb-3';

            const presetLabel = document.createElement('span');
            presetLabel.className = 'text-muted small fw-600';
            presetLabel.textContent = 'Aplicar un preset:';

            const presetSelect = document.createElement('select');
            presetSelect.className = 'form-select form-select-sm w-auto';
            presetSelect.innerHTML = '<option value="">Selecciona un preset</option>';
            MENU_PRESETS.forEach((preset) => {
                const option = document.createElement('option');
                option.value = preset.id;
                option.textContent = preset.label;
                presetSelect.appendChild(option);
            });

            const presetButton = document.createElement('button');
            presetButton.type = 'button';
            presetButton.className = 'btn btn-sm btn-outline-primary';
            presetButton.innerHTML = '<i class="mdi mdi-check"></i> Aplicar';
            presetButton.addEventListener('click', () => {
                const selected = MENU_PRESETS.find((preset) => preset.id === presetSelect.value);
                if (!selected) {
                    return;
                }
                state.menu = JSON.parse(JSON.stringify(selected.menu));
                renderMenu();
            });

            presetRow.appendChild(presetLabel);
            presetRow.appendChild(presetSelect);
            presetRow.appendChild(presetButton);
            messageBody.appendChild(presetRow);
        }

        const typeLabel = document.createElement('label');
        typeLabel.className = 'form-label small text-muted';
        typeLabel.textContent = 'Formato';
        messageBody.appendChild(typeLabel);

        const typeSelect = document.createElement('select');
        typeSelect.className = 'form-select form-select-sm mb-3';
        allowedTypes.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value === 'buttons'
                ? 'Botones interactivos'
                : value === 'list'
                    ? 'Lista desplegable'
                    : 'Mensaje de texto';
            if (messageType === value) {
                option.selected = true;
            }
            typeSelect.appendChild(option);
        });
        typeSelect.addEventListener('change', () => {
            const nextType = typeSelect.value;
            if (nextType === 'buttons') {
                state.menu.message.type = 'buttons';
                state.menu.message.buttons = Array.isArray(state.menu.message.buttons)
                    ? state.menu.message.buttons.slice(0, MENU_BUTTON_LIMIT)
                    : [];
            } else if (nextType === 'list') {
                state.menu.message = ensureListMessage(state.menu.message);
            } else {
                state.menu.message.type = 'text';
                state.menu.message.buttons = [];
                delete state.menu.message.sections;
            }
            renderMenu();
        });
        messageBody.appendChild(typeSelect);

        const bodyLabel = document.createElement('label');
        bodyLabel.className = 'form-label small text-muted';
        bodyLabel.textContent = 'Texto inicial';
        messageBody.appendChild(bodyLabel);

        const bodyTextarea = document.createElement('textarea');
        bodyTextarea.className = 'form-control form-control-sm mb-3';
        bodyTextarea.rows = 3;
        bodyTextarea.value = state.menu.message.body || '';
        bodyTextarea.addEventListener('input', () => {
            state.menu.message.body = bodyTextarea.value;
            renderMenuPreview();
        });
        messageBody.appendChild(bodyTextarea);

        const dynamicContainer = document.createElement('div');
        messageBody.appendChild(dynamicContainer);

        const updateDynamicFields = () => {
            dynamicContainer.innerHTML = '';

            if (state.menu.message.type === 'buttons') {
                const buttonsHeader = document.createElement('div');
                buttonsHeader.className = 'd-flex justify-content-between align-items-center mb-2';
                buttonsHeader.innerHTML = '<span class="small fw-600">Botones del menú</span>';

                const addButton = document.createElement('button');
                addButton.type = 'button';
                addButton.className = 'btn btn-xs btn-outline-primary';
                addButton.innerHTML = '<i class="mdi mdi-plus"></i> Añadir botón';
                addButton.addEventListener('click', () => {
                    state.menu.message.buttons = Array.isArray(state.menu.message.buttons)
                        ? state.menu.message.buttons
                        : [];
                    if (state.menu.message.buttons.length >= MENU_BUTTON_LIMIT) {
                        window.alert(`Solo puedes añadir hasta ${MENU_BUTTON_LIMIT} botones.`);
                        return;
                    }
                    state.menu.message.buttons.push({id: '', title: ''});
                    renderButtonsList(buttonsContainer, {message: state.menu.message});
                    renderMenuPreview();
                });
                buttonsHeader.appendChild(addButton);

                const buttonsContainer = document.createElement('div');
                renderButtonsList(buttonsContainer, {message: state.menu.message});

                dynamicContainer.appendChild(buttonsHeader);
                dynamicContainer.appendChild(buttonsContainer);

                return;
            }

            if (state.menu.message.type === 'list') {
                const buttonLabel = document.createElement('label');
                buttonLabel.className = 'form-label small text-muted';
                buttonLabel.textContent = 'Texto del botón principal';

                const buttonInput = document.createElement('input');
                buttonInput.type = 'text';
                buttonInput.className = 'form-control form-control-sm mb-2';
                buttonInput.value = state.menu.message.button || 'Ver opciones';
                buttonInput.addEventListener('input', () => {
                    state.menu.message.button = buttonInput.value || 'Ver opciones';
                    renderMenuPreview();
                });

                const footerLabel = document.createElement('label');
                footerLabel.className = 'form-label small text-muted';
                footerLabel.textContent = 'Texto opcional en el pie';

                const footerInput = document.createElement('input');
                footerInput.type = 'text';
                footerInput.className = 'form-control form-control-sm mb-2';
                footerInput.value = state.menu.message.footer || '';
                footerInput.placeholder = 'Ej. Selecciona la sección deseada';
                footerInput.addEventListener('input', () => {
                    state.menu.message.footer = footerInput.value;
                    renderMenuPreview();
                });

                const sectionsHeader = document.createElement('div');
                sectionsHeader.className = 'd-flex justify-content-between align-items-center mb-2';
                sectionsHeader.innerHTML = '<span class="small fw-600">Secciones y opciones</span>';

                const addSectionButton = document.createElement('button');
                addSectionButton.type = 'button';
                addSectionButton.className = 'btn btn-xs btn-outline-primary';
                addSectionButton.innerHTML = '<i class="mdi mdi-plus"></i> Añadir sección';
                addSectionButton.addEventListener('click', () => {
                    state.menu.message.sections = Array.isArray(state.menu.message.sections)
                        ? state.menu.message.sections
                        : [];
                    if (state.menu.message.sections.length >= MENU_LIST_SECTION_LIMIT) {
                        window.alert(`Solo puedes añadir hasta ${MENU_LIST_SECTION_LIMIT} secciones.`);
                        return;
                    }
                    state.menu.message.sections.push(createDefaultListSection());
                    renderListSections(listContainer, state.menu.message.sections, state.menu.message);
                    renderMenuPreview();
                });
                sectionsHeader.appendChild(addSectionButton);

                const listContainer = document.createElement('div');
                renderListSections(listContainer, state.menu.message.sections, state.menu.message);

                dynamicContainer.appendChild(buttonLabel);
                dynamicContainer.appendChild(buttonInput);
                dynamicContainer.appendChild(footerLabel);
                dynamicContainer.appendChild(footerInput);
                dynamicContainer.appendChild(sectionsHeader);
                dynamicContainer.appendChild(listContainer);

                return;
            }

            const hint = document.createElement('p');
            hint.className = 'text-muted small mb-0';
            hint.textContent = 'El menú enviará un mensaje simple. Añade opciones abajo para dirigir las respuestas.';
            dynamicContainer.appendChild(hint);
        };

        updateDynamicFields();
        editorColumn.appendChild(messageCard);

        const optionsCard = document.createElement('div');
        optionsCard.className = 'card border-0 shadow-sm';
        const optionsBody = document.createElement('div');
        optionsBody.className = 'card-body';
        optionsCard.appendChild(optionsBody);

        const optionsHeader = document.createElement('div');
        optionsHeader.className = 'd-flex justify-content-between align-items-center mb-3';
        optionsHeader.innerHTML = '<h6 class="fw-600 mb-0">Opciones del menú</h6>';

        const addOptionButton = document.createElement('button');
        addOptionButton.type = 'button';
        addOptionButton.className = 'btn btn-sm btn-outline-primary';
        addOptionButton.innerHTML = '<i class="mdi mdi-plus"></i> Añadir opción';
        addOptionButton.addEventListener('click', () => {
            state.menu.options.push({id: '', title: '', keywords: [], actions: []});
            renderMenuOptions(optionsList);
            renderMenuPreview();
        });
        optionsHeader.appendChild(addOptionButton);
        optionsBody.appendChild(optionsHeader);

        const optionsHint = document.createElement('p');
        optionsHint.className = 'text-muted small';
        optionsHint.textContent = 'Define identificadores únicos, palabras clave y las acciones a ejecutar cuando el contacto elija cada opción.';
        optionsBody.appendChild(optionsHint);

        const optionsList = document.createElement('div');
        optionsBody.appendChild(optionsList);
        editorColumn.appendChild(optionsCard);

        const previewCard = document.createElement('div');
        previewCard.className = 'card border-0 shadow-sm';
        const previewBody = document.createElement('div');
        previewBody.className = 'card-body';
        previewCard.appendChild(previewBody);
        sidebarColumn.appendChild(previewCard);

        const previewTitle = document.createElement('h6');
        previewTitle.className = 'fw-600 mb-2';
        previewTitle.textContent = 'Vista previa';
        previewBody.appendChild(previewTitle);

        const previewSubtitle = document.createElement('p');
        previewSubtitle.className = 'text-muted small';
        previewSubtitle.textContent = 'Así se verá el mensaje al paciente, con botones o lista según el formato elegido.';
        previewBody.appendChild(previewSubtitle);

        const previewContent = document.createElement('div');
        previewContent.className = 'border rounded-3 p-3 bg-light-subtle';
        previewBody.appendChild(previewContent);

        menuPreviewNode = previewContent;

        const tipsCard = document.createElement('div');
        tipsCard.className = 'card border-0 shadow-sm';
        const tipsBody = document.createElement('div');
        tipsBody.className = 'card-body';
        tipsBody.innerHTML = '<h6 class="fw-600 mb-2">Buenas prácticas</h6>' +
            '<ul class="text-muted small mb-0 ps-3">' +
            '<li>Usa palabras clave cortas (máximo 3) por opción.</li>' +
            '<li>Vincula cada opción con acciones claras para evitar respuestas vacías.</li>' +
            '<li>Combina listas con botones cuando necesites más de tres alternativas.</li>' +
            '</ul>';
        tipsCard.appendChild(tipsBody);
        sidebarColumn.appendChild(tipsCard);

        renderMenuOptions(optionsList);
        renderMenuPreview();
    }

    function renderMenuOptions(container) {
        if (!container || !templates.menuOption) {
            return;
        }
        container.innerHTML = '';

        state.menu.options.forEach((option, index) => {
            const node = templates.menuOption.content.firstElementChild.cloneNode(true);
            const idInput = node.querySelector('[data-option-id]');
            const titleInput = node.querySelector('[data-option-title]');
            const keywordsInput = node.querySelector('[data-option-keywords]');
            const removeButton = node.querySelector('[data-action="remove-menu-option"]');
            const addActionButton = node.querySelector('[data-action="add-option-action"]');
            const actionList = node.querySelector('[data-option-action-list]');

            if (idInput) {
                idInput.value = option.id || '';
                idInput.addEventListener('input', () => {
                    option.id = idInput.value.trim();
                    renderMenuPreview();
                });
            }

            if (titleInput) {
                titleInput.value = option.title || '';
                titleInput.addEventListener('input', () => {
                    option.title = titleInput.value;
                    renderMenuPreview();
                });
            }

            if (keywordsInput) {
                keywordsInput.value = Array.isArray(option.keywords) ? option.keywords.join(', ') : '';
                keywordsInput.addEventListener('input', () => {
                    option.keywords = keywordsInput.value.split(/[,\n]/).map((value) => value.trim()).filter(Boolean);
                    renderMenuPreview();
                });
                const hint = document.createElement('div');
                hint.className = 'small text-muted mt-1';
                hint.textContent = 'Separa con comas. Se ignorarán mayúsculas y acentos al comparar.';
                keywordsInput.parentElement?.appendChild(hint);
            }

            if (removeButton) {
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    state.menu.options.splice(index, 1);
                    renderMenuOptions(container);
                    renderMenuPreview();
                });
            }

            if (addActionButton) {
                addActionButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    option.actions = option.actions || [];
                    option.actions.push({type: 'send_message', message: {type: 'text', body: ''}});
                    renderActions(actionList, option.actions, option);
                    renderMenuPreview();
                });
            }

            renderActions(actionList, option.actions || [], option);

            container.appendChild(node);
        });

        renderMenuPreview();
    }

    function renderMenuPreview() {
        if (!menuPreviewNode) {
            return;
        }

        menuPreviewNode.innerHTML = '';

        const messageWrapper = document.createElement('div');
        messageWrapper.className = 'mb-3';

        const typeBadge = document.createElement('span');
        typeBadge.className = 'badge bg-primary-subtle text-primary mb-2';
        const messageType = state.menu?.message?.type || 'text';
        typeBadge.textContent = messageType === 'buttons'
            ? 'Botones'
            : messageType === 'list'
                ? 'Lista'
                : 'Texto';
        messageWrapper.appendChild(typeBadge);

        const bodyText = document.createElement('p');
        bodyText.className = 'mb-2';
        const bodyValue = (state.menu?.message?.body || '').trim();
        bodyText.textContent = bodyValue !== '' ? bodyValue : 'Sin texto definido.';
        messageWrapper.appendChild(bodyText);

        if (messageType === 'buttons' && Array.isArray(state.menu?.message?.buttons)) {
            const list = document.createElement('ul');
            list.className = 'list-inline mb-2';
            state.menu.message.buttons.forEach((button) => {
                const item = document.createElement('li');
                item.className = 'list-inline-item badge bg-light text-muted border';
                item.textContent = button.title || '(botón sin título)';
                list.appendChild(item);
            });
            messageWrapper.appendChild(list);
        }

        if (messageType === 'list' && Array.isArray(state.menu?.message?.sections)) {
            const sectionsWrapper = document.createElement('div');
            state.menu.message.sections.forEach((section) => {
                const sectionBlock = document.createElement('div');
                sectionBlock.className = 'mb-2';
                const title = document.createElement('div');
                title.className = 'fw-600 small';
                title.textContent = section.title || 'Sección sin título';
                sectionBlock.appendChild(title);

                const rows = document.createElement('ul');
                rows.className = 'mb-0 ps-3';
                (section.rows || []).forEach((row) => {
                    const rowItem = document.createElement('li');
                    rowItem.className = 'small';
                    rowItem.textContent = row.title || '(opción sin nombre)';
                    rows.appendChild(rowItem);
                });
                sectionBlock.appendChild(rows);
                sectionsWrapper.appendChild(sectionBlock);
            });
            messageWrapper.appendChild(sectionsWrapper);

            if ((state.menu.message.footer || '').trim() !== '') {
                const footer = document.createElement('div');
                footer.className = 'text-muted small';
                footer.textContent = state.menu.message.footer;
                messageWrapper.appendChild(footer);
            }
        }

        menuPreviewNode.appendChild(messageWrapper);

        const optionsTitle = document.createElement('div');
        optionsTitle.className = 'fw-600 mb-2';
        optionsTitle.textContent = 'Opciones configuradas';
        menuPreviewNode.appendChild(optionsTitle);

        if (!Array.isArray(state.menu?.options) || state.menu.options.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-muted small mb-0';
            empty.textContent = 'Aún no hay opciones. Añádelas para mapear acciones automáticas.';
            menuPreviewNode.appendChild(empty);

            return;
        }

        const optionsList = document.createElement('div');
        optionsList.className = 'd-flex flex-column gap-2';

        state.menu.options.forEach((option) => {
            const optionRow = document.createElement('div');
            optionRow.className = 'border rounded-3 px-3 py-2';

            const optionHeader = document.createElement('div');
            optionHeader.className = 'd-flex justify-content-between align-items-center mb-1';

            const title = document.createElement('span');
            title.className = 'fw-600';
            title.textContent = option.title || '(sin título)';

            const identifier = document.createElement('code');
            identifier.textContent = option.id || 'sin_id';

            optionHeader.appendChild(title);
            optionHeader.appendChild(identifier);

            const keywords = document.createElement('div');
            keywords.className = 'text-muted small';
            const formattedKeywords = Array.isArray(option.keywords) && option.keywords.length > 0
                ? option.keywords.join(', ')
                : 'Sin palabras clave';
            keywords.textContent = `Palabras clave: ${formattedKeywords}`;

            optionRow.appendChild(optionHeader);
            optionRow.appendChild(keywords);

            const actionsInfo = document.createElement('div');
            actionsInfo.className = 'text-muted small';
            const actionCount = Array.isArray(option.actions) ? option.actions.length : 0;
            actionsInfo.textContent = `${actionCount} ${actionCount === 1 ? 'acción' : 'acciones'}`;
            optionRow.appendChild(actionsInfo);

            optionsList.appendChild(optionRow);
        });

        menuPreviewNode.appendChild(optionsList);
    }

    function setupSimulationPanel() {
        if (!simulationPanel) {
            return;
        }

        const runButton = simulationPanel.querySelector('[data-action="run-simulation"]');
        const resetButton = simulationPanel.querySelector('[data-action="reset-simulation"]');

        if (runButton) {
            runButton.addEventListener('click', (event) => {
                event.preventDefault();
                runSimulationFromPanel();
            });
        }

        if (resetButton) {
            resetButton.addEventListener('click', (event) => {
                event.preventDefault();
                resetSimulationHistory();
            });
        }

        if (simulationInput) {
            simulationInput.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                    event.preventDefault();
                    runSimulationFromPanel();
                }
            });
        }

        if (simulationReplay && replayMessages.length === 0) {
            const bootstrapNode = document.querySelector('[data-inbox-bootstrap]');
            if (bootstrapNode) {
                try {
                    const parsed = JSON.parse(bootstrapNode.textContent || '[]');
                    if (Array.isArray(parsed)) {
                        parsed.forEach((entry) => {
                            if (!entry || entry.direction !== 'incoming') {
                                return;
                            }
                            const body = (entry.message_body || '').trim();
                            if (body === '') {
                                return;
                            }
                            replayMessages.push({
                                id: entry.id || replayMessages.length + 1,
                                body,
                                created_at: entry.created_at || null,
                            });
                        });
                    }
                } catch (error) {
                    console.warn('No fue posible cargar los mensajes recientes para la simulación', error);
                }
            }

            if (replayMessages.length > 0) {
                replayMessages.slice(0, 25).forEach((item, index) => {
                    const option = document.createElement('option');
                    option.value = String(index);
                    const preview = item.body.length > 50 ? `${item.body.slice(0, 47)}…` : item.body;
                    const formattedTime = item.created_at ? formatSimulationTime(item.created_at) : '';
                    const dateLabel = formattedTime ? ` (${formattedTime})` : '';
                    option.textContent = preview + dateLabel;
                    simulationReplay.appendChild(option);
                });

                simulationReplay.addEventListener('change', () => {
                    const selected = parseInt(simulationReplay.value, 10);
                    if (Number.isNaN(selected) || !replayMessages[selected]) {
                        return;
                    }
                    if (simulationInput) {
                        simulationInput.value = replayMessages[selected].body;
                        simulationInput.focus();
                    }
                });
            }
        }

        refreshSimulationHints();
        renderSimulationHistory();
    }

    function runSimulationFromPanel() {
        if (!simulationInput) {
            return;
        }
        const rawMessage = simulationInput.value.trim();
        if (rawMessage === '') {
            window.alert('Escribe un mensaje de prueba antes de simular.');
            simulationInput.focus();

            return;
        }

        const facts = collectSimulationFacts(rawMessage);
        const result = evaluateScenariosForSimulation(rawMessage, facts);
        simulationHistory.unshift(result);
        if (simulationHistory.length > 6) {
            simulationHistory.pop();
        }

        renderSimulationHistory();
    }

    function resetSimulationHistory() {
        simulationHistory.splice(0, simulationHistory.length);
        if (simulationInput) {
            simulationInput.value = '';
        }
        if (simulationReplay) {
            simulationReplay.value = '';
        }
        renderSimulationHistory();
    }

    function renderSimulationHistory() {
        if (!simulationLog) {
            return;
        }

        simulationLog.innerHTML = '';

        if (simulationHistory.length === 0) {
            const hint = document.createElement('p');
            hint.className = 'text-muted small mb-0';
            hint.textContent = 'Aún no se han ejecutado simulaciones. Ingresa un mensaje para ver qué escenario se activaría.';
            simulationLog.appendChild(hint);

            return;
        }

        simulationHistory.forEach((entry) => {
            const card = document.createElement('div');
            card.className = 'border rounded-3 p-3 mb-2 bg-white';

            const header = document.createElement('div');
            header.className = 'd-flex justify-content-between align-items-start gap-2 mb-2';

            const messageBlock = document.createElement('div');
            const messageTitle = document.createElement('div');
            messageTitle.className = 'fw-600';
            messageTitle.textContent = 'Mensaje';
            const messageContent = document.createElement('div');
            messageContent.className = 'small';
            messageContent.textContent = entry.message;
            messageBlock.appendChild(messageTitle);
            messageBlock.appendChild(messageContent);

            const timeBlock = document.createElement('div');
            timeBlock.className = 'text-muted small text-end';
            timeBlock.textContent = formatSimulationTime(entry.timestamp);

            header.appendChild(messageBlock);
            header.appendChild(timeBlock);
            card.appendChild(header);

            const matchBadge = document.createElement('div');
            matchBadge.className = entry.match
                ? 'badge bg-success-subtle text-success mb-2'
                : 'badge bg-warning-subtle text-warning mb-2';
            matchBadge.textContent = entry.match
                ? `Coincidencia: ${formatScenarioName(entry.match.scenario, entry.match.index)}`
                : 'Ningún escenario coincide';
            card.appendChild(matchBadge);

            const scenariosList = document.createElement('div');
            scenariosList.className = 'd-flex flex-column gap-2';

            entry.evaluations.forEach((evaluation) => {
                const scenarioRow = document.createElement('div');
                scenarioRow.className = 'border rounded-3 p-2';

                const titleRow = document.createElement('div');
                titleRow.className = 'd-flex justify-content-between align-items-center';

                const scenarioTitle = document.createElement('span');
                scenarioTitle.className = 'fw-600';
                scenarioTitle.textContent = formatScenarioName(evaluation.scenario, evaluation.index);

                const status = document.createElement('span');
                status.className = evaluation.passed ? 'text-success small fw-600' : 'text-muted small';
                status.textContent = evaluation.passed ? 'Coincide' : 'No coincide';

                titleRow.appendChild(scenarioTitle);
                titleRow.appendChild(status);
                scenarioRow.appendChild(titleRow);

                const conditionsList = document.createElement('ul');
                conditionsList.className = 'mb-0 ps-3 small';
                evaluation.conditions.forEach((condition) => {
                    const item = document.createElement('li');
                    const icon = document.createElement('i');
                    icon.className = `mdi ${condition.result ? 'mdi-check text-success' : 'mdi-close text-danger'} me-1`;
                    item.appendChild(icon);
                    const text = document.createElement('span');
                    text.textContent = condition.detail;
                    item.appendChild(text);
                    conditionsList.appendChild(item);
                });
                scenarioRow.appendChild(conditionsList);

                scenariosList.appendChild(scenarioRow);
            });

            card.appendChild(scenariosList);
            simulationLog.appendChild(card);
        });
    }

    function evaluateScenariosForSimulation(message, facts) {
        const evaluations = state.scenarios.map((scenario, index) => {
            const conditions = Array.isArray(scenario.conditions) && scenario.conditions.length > 0
                ? scenario.conditions
                : [{type: 'always'}];
            const conditionDetails = conditions.map((condition) => explainCondition(condition, facts));
            const passed = conditionDetails.every((entry) => entry.result);
            return {
                scenario,
                index,
                passed,
                conditions: conditionDetails,
                actions: Array.isArray(scenario.actions) ? scenario.actions : [],
            };
        });

        const match = evaluations.find((evaluation) => evaluation.passed) || null;

        return {
            message,
            timestamp: Date.now(),
            facts,
            evaluations,
            match,
        };
    }

    function explainCondition(condition, facts) {
        const option = CONDITION_OPTIONS.find((entry) => entry.value === condition.type);
        const label = option ? option.label : (condition.type || 'Condición');
        const detail = {result: false, detail: label};
        const type = condition.type || 'always';

        switch (type) {
            case 'always':
                detail.result = true;
                detail.detail = `${label}: siempre verdadero`;
                return detail;
            case 'is_first_time': {
                const expected = condition.value !== false;
                const actual = Boolean(facts.is_first_time);
                detail.result = actual === expected;
                detail.detail = `${label}: ${expected ? 'sí' : 'no'} (actual: ${actual ? 'sí' : 'no'})`;
                return detail;
            }
            case 'has_consent': {
                const expected = condition.value !== false;
                const actual = Boolean(facts.has_consent);
                detail.result = actual === expected;
                detail.detail = `${label}: ${expected ? 'con' : 'sin'} consentimiento (actual: ${actual ? 'con' : 'sin'})`;
                return detail;
            }
            case 'state_is': {
                const expected = (condition.value || '').toString();
                const actual = facts.state || '';
                detail.result = actual === expected;
                detail.detail = `${label}: ${expected || '(vacío)'} (actual: ${actual || '(vacío)'})`;
                return detail;
            }
            case 'awaiting_is': {
                const expected = (condition.value || '').toString();
                const actual = facts.awaiting_field || '';
                detail.result = actual === expected;
                detail.detail = `${label}: ${expected || '(ninguno)'} (actual: ${actual || '(ninguno)'})`;
                return detail;
            }
            case 'message_in': {
                const values = Array.isArray(condition.values) ? condition.values : [];
                detail.result = values.includes(facts.message);
                detail.detail = `${label}: [${values.join(', ')}]`;
                return detail;
            }
            case 'message_contains': {
                const keywords = Array.isArray(condition.keywords) ? condition.keywords : [];
                detail.result = keywords.some((keyword) => keyword && facts.message.includes(keyword));
                detail.detail = `${label}: ${keywords.join(', ')}`;
                return detail;
            }
            case 'message_matches': {
                if (!condition.pattern) {
                    detail.detail = `${label}: patrón vacío`;
                    return detail;
                }
                try {
                    const regex = new RegExp(condition.pattern, 'i');
                    detail.result = regex.test(facts.raw_message || '');
                    detail.detail = `${label}: ${condition.pattern}`;
                } catch (error) {
                    detail.detail = `${label}: patrón inválido`;
                }
                return detail;
            }
            case 'last_interaction_gt': {
                const minutes = Number(condition.minutes || 0);
                detail.result = (facts.minutes_since_last || 0) >= minutes;
                detail.detail = `${label}: ${minutes} minutos`;
                return detail;
            }
            case 'patient_found': {
                const expected = condition.value !== false;
                const actual = Boolean(facts.patient_found);
                detail.result = actual === expected;
                detail.detail = `${label}: ${expected ? 'encontrado' : 'no encontrado'} (actual: ${actual ? 'encontrado' : 'no encontrado'})`;
                return detail;
            }
            default:
                detail.detail = `${label}: condición personalizada`;
                return detail;
        }
    }

    function collectSimulationFacts(message) {
        return {
            is_first_time: simulationFirstTime ? simulationFirstTime.checked : true,
            has_consent: simulationHasConsent ? simulationHasConsent.checked : false,
            state: simulationStateInput ? simulationStateInput.value.trim() : '',
            awaiting_field: simulationAwaitingInput ? simulationAwaitingInput.value.trim() : '',
            message: normalizeText(message),
            raw_message: message,
            minutes_since_last: simulationMinutesInput ? parseInt(simulationMinutesInput.value, 10) || 0 : 0,
            patient_found: simulationPatientFound ? simulationPatientFound.checked : false,
        };
    }

    function formatScenarioName(scenario, index) {
        const base = scenario.name || scenario.id || `Escenario ${index + 1}`;
        return `${index + 1}. ${base}`;
    }

    function formatSimulationTime(value) {
        const date = value instanceof Date ? value : new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '';
        }
        return date.toLocaleString();
    }

    function buildPayload() {
        const variablesPayload = {};
        state.variables.forEach((variable) => {
            variablesPayload[variable.key] = {
                label: variable.label || capitalize(variable.key),
                source: variable.source || 'context.' + variable.key,
                persist: Boolean(variable.persist),
            };
        });

        normalizeMenu();

        return {
            variables: variablesPayload,
            scenarios: state.scenarios.map((scenario) => prepareScenarioPayload(scenario)),
            menu: state.menu,
        };
    }

    function normalizeMenu() {
        state.menu = state.menu || {};
        state.menu.message = state.menu.message || {};
        const allowedTypes = ['text', 'buttons', 'list'];
        let type = typeof state.menu.message.type === 'string' ? state.menu.message.type.toLowerCase() : 'text';
        if (!allowedTypes.includes(type)) {
            type = 'text';
        }
        state.menu.message.type = type;
        state.menu.message.body = state.menu.message.body || '';

        if (type === 'buttons') {
            state.menu.message.buttons = Array.isArray(state.menu.message.buttons)
                ? state.menu.message.buttons
                    .filter((button) => button && (button.title || button.id))
                    .slice(0, MENU_BUTTON_LIMIT)
                : [];
            delete state.menu.message.sections;
            delete state.menu.message.button;
            delete state.menu.message.footer;
        } else if (type === 'list') {
            state.menu.message = ensureListMessage(state.menu.message);
        } else {
            delete state.menu.message.buttons;
            delete state.menu.message.sections;
            delete state.menu.message.button;
            delete state.menu.message.footer;
        }

        state.menu.options = Array.isArray(state.menu.options)
            ? state.menu.options
                .filter((option) => option && (option.id || option.title))
                .map((option) => ({
                    id: (option.id || '').trim(),
                    title: option.title || '',
                    keywords: Array.isArray(option.keywords)
                        ? option.keywords.map((keyword) => keyword.trim()).filter(Boolean)
                        : [],
                    actions: Array.isArray(option.actions) ? option.actions : [],
                }))
            : [];
    }

    function validatePayload(payload) {
        const errors = [];
        const pushScenarioError = (scenario, message, scenarioIndex) => {
            errors.push({
                message,
                scenarioId: scenario && typeof scenario.id === 'string' ? scenario.id : undefined,
                scenarioIndex: typeof scenarioIndex === 'number' ? scenarioIndex : undefined,
            });
        };
        const keywordOwnership = new Map();
        const scenarioKeywordTracker = new Set();
        const registerScenarioKeyword = (type, keywordValue, scenario, scenarioIndex, scenarioLabel) => {
            const normalized = typeof keywordValue === 'string' ? keywordValue.trim().toLowerCase() : '';
            if (!normalized) {
                return;
            }

            const registryKey = `${type}:${normalized}`;
            const scenarioKey = `${scenario && typeof scenario.id === 'string' ? scenario.id : `@${scenarioIndex}`}:${registryKey}`;
            if (scenarioKeywordTracker.has(scenarioKey)) {
                return;
            }

            scenarioKeywordTracker.add(scenarioKey);

            const entry = keywordOwnership.get(registryKey) || [];
            entry.push({
                scenario,
                index: scenarioIndex,
                label: scenarioLabel,
                keyword: typeof keywordValue === 'string' ? keywordValue.trim() : normalized,
            });
            keywordOwnership.set(registryKey, entry);
        };
        if (!Array.isArray(payload.scenarios) || payload.scenarios.length === 0) {
            errors.push('Debes definir al menos un escenario.');
        }

        payload.scenarios.forEach((scenario, index) => {
            const scenarioLabel = scenario.name || scenario.id || `Escenario ${index + 1}`;
            if (!Array.isArray(scenario.actions) || scenario.actions.length === 0) {
                pushScenarioError(scenario, `El escenario "${scenarioLabel}" no tiene acciones.`, index);
            }

            const conditions = Array.isArray(scenario.conditions) ? scenario.conditions : [];
            conditions.forEach((condition) => {
                const type = condition.type || 'always';
                if (type === 'message_in' && (!Array.isArray(condition.values) || condition.values.length === 0)) {
                    pushScenarioError(scenario, `El escenario "${scenarioLabel}" requiere al menos una palabra en "Mensaje coincide con lista".`, index);
                }
                if (type === 'message_contains' && (!Array.isArray(condition.keywords) || condition.keywords.length === 0)) {
                    pushScenarioError(scenario, `Añade palabras clave a la condición "Mensaje contiene" en el escenario "${scenarioLabel}".`, index);
                }
                if (type === 'message_matches' && !condition.pattern) {
                    pushScenarioError(scenario, `Define una expresión regular para "Mensaje coincide con regex" en el escenario "${scenarioLabel}".`, index);
                }

                if (type === 'message_in') {
                    const values = Array.isArray(condition.values) ? condition.values : [];
                    values.forEach((value) => {
                        registerScenarioKeyword(type, value, scenario, index, scenarioLabel);
                    });
                }

                if (type === 'message_contains') {
                    const keywords = Array.isArray(condition.keywords) ? condition.keywords : [];
                    keywords.forEach((keyword) => {
                        registerScenarioKeyword(type, keyword, scenario, index, scenarioLabel);
                    });
                }
            });

            const actionsList = Array.isArray(scenario.actions) ? scenario.actions : [];
            actionsList.forEach((action, actionIndex) => {
                const actionLabel = `${scenarioLabel} → acción ${actionIndex + 1}`;
                const type = action?.type || 'send_message';

                if (type === 'send_message') {
                    validateSimpleMessagePayload(action.message, actionLabel).forEach((message) => {
                        pushScenarioError(scenario, message, index);
                    });
                }

                if (type === 'send_sequence') {
                    if (!Array.isArray(action.messages) || action.messages.length === 0) {
                        pushScenarioError(scenario, `${actionLabel}: añade al menos un mensaje a la secuencia.`, index);
                    } else {
                        action.messages.forEach((message, messageIndex) => {
                            const messageLabel = `${actionLabel} → mensaje ${messageIndex + 1}`;
                            validateSimpleMessagePayload(message, messageLabel).forEach((detail) => {
                                pushScenarioError(scenario, detail, index);
                            });
                        });
                    }
                }

                if (type === 'send_template') {
                    const template = action.template || {};
                    if (!template.name || !template.language) {
                        pushScenarioError(scenario, `${actionLabel}: selecciona una plantilla aprobada antes de guardar.`, index);
                    }
                }
            });
        });

        keywordOwnership.forEach((entries) => {
            if (!Array.isArray(entries) || entries.length < 2) {
                return;
            }

            entries.forEach((entry, entryIndex) => {
                const others = entries
                    .filter((_, index) => index !== entryIndex)
                    .map((item) => `"${item.label}"`);
                if (others.length === 0) {
                    return;
                }

                const prefix = others.length === 1 ? 'el escenario' : 'los escenarios';
                const message = `El escenario "${entry.label}" comparte la palabra clave "${entry.keyword}" con ${prefix} ${others.join(', ')}. Ajusta las condiciones para evitar respuestas ambiguas.`;
                pushScenarioError(entry.scenario, message, entry.index);
            });
        });

        const menuMessage = payload.menu?.message || {};
        const menuType = menuMessage.type || 'text';
        const menuBody = (menuMessage.body || '').trim();
        if (menuBody === '') {
            errors.push('El mensaje principal del menú no puede estar vacío.');
        }

        if (menuType === 'buttons') {
            if (!Array.isArray(menuMessage.buttons) || menuMessage.buttons.length === 0) {
                errors.push('Agrega al menos un botón al menú interactivo.');
            }
        }

        if (menuType === 'list') {
            if (!Array.isArray(menuMessage.sections) || menuMessage.sections.length === 0) {
                errors.push('La lista interactiva debe incluir al menos una sección.');
            } else {
                menuMessage.sections.forEach((section, sectionIndex) => {
                    const rows = Array.isArray(section.rows) ? section.rows : [];
                    if (rows.length === 0) {
                        errors.push(`La sección ${section.title || sectionIndex + 1} de la lista no tiene opciones.`);
                    }
                });
            }
            if (!menuMessage.button || menuMessage.button.trim() === '') {
                errors.push('Define el texto del botón principal para la lista interactiva.');
            }
        }

        const menuOptions = Array.isArray(payload.menu?.options) ? payload.menu.options : [];
        if (menuOptions.length === 0) {
            errors.push('Configura al menos una opción en el menú para vincular acciones.');
        }

        const menuKeywordMap = new Map();
        menuOptions.forEach((option) => {
            if (!Array.isArray(option.keywords) || option.keywords.length === 0) {
                errors.push(`La opción "${option.title || option.id || 'sin título'}" necesita palabras clave para detectar el mensaje del paciente.`);
            }
            if (!Array.isArray(option.actions) || option.actions.length === 0) {
                errors.push(`La opción "${option.title || option.id || 'sin título'}" debe tener al menos una acción configurada.`);
            }

            const optionLabel = option.title || option.id || 'sin título';
            const seen = new Set();
            (Array.isArray(option.keywords) ? option.keywords : []).forEach((keyword) => {
                const normalized = typeof keyword === 'string' ? keyword.trim().toLowerCase() : '';
                if (!normalized || seen.has(normalized)) {
                    return;
                }
                seen.add(normalized);
                const list = menuKeywordMap.get(normalized) || [];
                list.push({label: optionLabel, display: typeof keyword === 'string' ? keyword.trim() : normalized});
                menuKeywordMap.set(normalized, list);
            });
        });

        menuKeywordMap.forEach((entries, keyword) => {
            if (!Array.isArray(entries) || entries.length < 2) {
                return;
            }

            const labels = entries.map((entry) => `"${entry.label}"`);
            const displayKeyword = entries[0]?.display || keyword;
            errors.push(`Las opciones de menú ${labels.join(', ')} usan la misma palabra clave "${displayKeyword}". Ajusta las palabras clave para evitar respuestas ambiguas.`);
        });

        return errors;
    }

    function presentErrors(errors) {
        if (!validationAlert) {
            return;
        }
        const entries = errors.map((error) => (typeof error === 'string' ? {message: error} : error));
        validationAlert.innerHTML = `<strong>Revisa los siguientes puntos:</strong><ul class="mb-0">${entries.map((error) => `<li>${error.message}</li>`).join('')}</ul>`;
        validationAlert.classList.remove('d-none');
        markScenarioValidationState(entries);
        validationAlert.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    function resetValidation() {
        if (validationAlert) {
            validationAlert.classList.add('d-none');
            validationAlert.innerHTML = '';
        }
        clearScenarioValidationState();
    }

    function normalizeScenarios() {
        state.scenarios.forEach((scenario, index) => {
            if (!scenario.id || scenario.id.trim() === '') {
                scenario.id = slugify(scenario.name || `scenario_${index + 1}`);
            }
            scenario.conditions = Array.isArray(scenario.conditions) && scenario.conditions.length > 0
                ? scenario.conditions
                : [{type: 'always'}];
            scenario.intercept_menu = Boolean(scenario.intercept_menu);
            const normalizedStage = resolveScenarioStage(scenario.stage || scenario.stage_id || scenario.stageId);
            scenario.stage = normalizedStage;
            scenario.stage_id = normalizedStage;
            scenario.stageId = normalizedStage;
        });
    }

    function simulateFlow() {
        if (simulationPanel && simulationInput) {
            simulationPanel.scrollIntoView({behavior: 'smooth', block: 'start'});
            simulationInput.focus();

            return;
        }

        const message = window.prompt('Ingresa un mensaje de prueba');
        if (message === null) {
            return;
        }
        const normalized = normalizeText(message);
        const facts = {
            is_first_time: true,
            has_consent: false,
            state: 'inicio',
            awaiting_field: null,
            message: normalized,
            raw_message: message,
            minutes_since_last: 999,
            patient_found: false,
        };

        const match = state.scenarios.find((scenario) => {
            return (scenario.conditions || [{type: 'always'}]).every((condition) => evaluateCondition(condition, facts));
        });

        if (match) {
            window.alert(`Se activaría el escenario "${match.name || match.id}" con ${match.actions?.length || 0} acciones.`);
        } else {
            window.alert('Ningún escenario coincide con el mensaje proporcionado.');
        }
    }

    function evaluateCondition(condition, facts) {
        const type = condition.type || 'always';
        switch (type) {
            case 'always':
                return true;
            case 'is_first_time':
                return Boolean(facts.is_first_time) === Boolean(condition.value);
            case 'has_consent':
                return Boolean(facts.has_consent) === Boolean(condition.value);
            case 'state_is':
                return (facts.state || '') === (condition.value || '');
            case 'awaiting_is':
                return (facts.awaiting_field || '') === (condition.value || '');
            case 'message_in':
                return Array.isArray(condition.values)
                    ? condition.values.some((value) => value === facts.message)
                    : false;
            case 'message_contains':
                return Array.isArray(condition.keywords)
                    ? condition.keywords.some((value) => value && facts.message.includes(value))
                    : false;
            case 'message_matches':
                if (!condition.pattern) {
                    return false;
                }
                try {
                    const regex = new RegExp(condition.pattern, 'i');
                    return regex.test(facts.raw_message || '');
                } catch (error) {
                    console.warn('Expresión regular inválida en simulación', error);
                    return false;
                }
            case 'last_interaction_gt':
                return (facts.minutes_since_last || 0) >= (condition.minutes || 0);
            case 'patient_found':
                return Boolean(facts.patient_found) === Boolean(condition.value ?? true);
            default:
                return false;
        }
    }

    function generateScenarioId() {
        scenarioSeed += 1;

        return `scenario_${scenarioSeed}`;
    }

    function bumpScenarioSeedFromId(id) {
        if (typeof id !== 'string') {
            return;
        }

        const match = id.match(/_(\d+)$/);
        if (!match) {
            return;
        }

        const value = parseInt(match[1], 10);
        if (Number.isNaN(value)) {
            return;
        }

        scenarioSeed = Math.max(scenarioSeed, value);
    }

    function cloneScenario(source) {
        const base = source ? JSON.parse(JSON.stringify(source)) : {};
        const scenario = {
            id: typeof base.id === 'string' && base.id.trim() !== '' ? base.id.trim() : generateScenarioId(),
            name: typeof base.name === 'string' ? base.name : '',
            description: typeof base.description === 'string' ? base.description : '',
            conditions: Array.isArray(base.conditions) && base.conditions.length > 0 ? base.conditions : [{type: 'always'}],
            actions: Array.isArray(base.actions) && base.actions.length > 0
                ? base.actions
                : [{type: 'send_message', message: {type: 'text', body: ''}}],
            intercept_menu: base.intercept_menu ?? base.interceptMenu,
            stage: resolveScenarioStage(base.stage || base.stage_id || base.stageId),
        };

        if (scenario.intercept_menu === undefined) {
            scenario.intercept_menu = DEFAULT_INTERCEPT_IDS.has(scenario.id);
        } else {
            scenario.intercept_menu = Boolean(scenario.intercept_menu);
        }

        scenario.stage_id = scenario.stage;
        scenario.stageId = scenario.stage;

        bumpScenarioSeedFromId(scenario.id);

        return scenario;
    }

    function prepareScenarioPayload(scenario) {
        const copy = JSON.parse(JSON.stringify(scenario || {}));
        copy.intercept_menu = Boolean(scenario && scenario.intercept_menu);
        const normalizedStage = resolveScenarioStage(copy.stage || copy.stage_id || copy.stageId);
        copy.stage = normalizedStage;
        copy.stage_id = normalizedStage;
        copy.stageId = normalizedStage;

        return copy;
    }

    function createDefaultScenario() {
        const scenario = {
            id: generateScenarioId(),
            name: 'Nuevo escenario',
            description: '',
            conditions: [{type: 'always'}],
            actions: [{type: 'send_message', message: {type: 'text', body: 'Mensaje de ejemplo.'}}],
            intercept_menu: false,
            stage: 'custom',
        };

        scenario.stage_id = scenario.stage;
        scenario.stageId = scenario.stage;

        return scenario;
    }

    function createDefaultMenu() {
        return {
            message: {
                type: 'buttons',
                body: 'Selecciona una opción:',
                buttons: [
                    {id: 'menu_agendar', title: 'Agendar cita'},
                    {id: 'menu_resultados', title: 'Resultados'},
                ],
            },
            options: [
                {id: 'menu_agendar', title: 'Agendar cita', keywords: ['agendar', 'cita'], actions: [{type: 'send_message', message: {type: 'text', body: 'Estamos listos para agendar tu cita.'}}]},
            ],
        };
    }

    function variableDescription(key) {
        switch (key) {
            case 'cedula':
                return 'Última cédula capturada durante la conversación. Fuente sugerida: context.cedula.';
            case 'telefono':
                return 'Número de WhatsApp del contacto. Fuente sugerida: session.wa_number.';
            case 'nombre':
                return 'Nombre completo obtenido de la base de pacientes. Fuente sugerida: patient.full_name.';
            case 'consentimiento':
                return 'Estado actual del consentimiento de datos. Fuente sugerida: context.consent.';
            case 'estado':
                return 'Paso actual del flujo. Fuente sugerida: context.state.';
            default:
                return 'Variable personalizada. Define una fuente y decide si quieres persistirla.';
        }
    }

    function slugify(value) {
        if (!value) {
            return '';
        }
        return value
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .substring(0, 48);
    }

    function capitalize(value) {
        if (!value) {
            return '';
        }
        return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function normalizeText(value) {
        return value.toLowerCase().trim().replace(/\s+/g, ' ');
    }
}
})();
