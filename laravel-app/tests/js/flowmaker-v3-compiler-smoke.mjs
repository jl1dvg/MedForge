import assert from 'node:assert/strict';
import { contractToGraph } from '../../resources/js/whatsapp/flowmaker-v3/graphAdapter.js';
import { graphToFlow } from '../../resources/js/whatsapp/flowmaker-v3/graphCompiler.js';
import { validateGraph } from '../../resources/js/whatsapp/flowmaker-v3/flowValidator.js';

const flow = graphToFlow({
    flowName: 'Flow V3 smoke',
    flowDescription: 'Contrato compilado desde nodos',
    settings: { timezone: 'America/Guayaquil' },
    nodes: [
        {
            id: 'trigger_1',
            type: 'keyword_trigger',
            data: {
                scenarioId: 'primer_contacto',
                name: 'Primer contacto',
                status: 'published',
                stage: 'arrival',
                keywords: [{ value: 'hola' }, { value: 'menu' }],
            },
        },
        {
            id: 'message_1',
            type: 'message',
            data: { settings: { body: 'Hola desde V3' } },
        },
        {
            id: 'buttons_1',
            type: 'quick_replies',
            data: { settings: { body: 'Elige una opción', buttons: ['Agenda', 'Resultados'] } },
        },
    ],
    edges: [
        { id: 'edge_1', source: 'trigger_1', target: 'message_1' },
        { id: 'edge_2', source: 'message_1', target: 'buttons_1' },
    ],
});

assert.equal(flow.name, 'Flow V3 smoke');
assert.equal(flow.settings.timezone, 'America/Guayaquil');
assert.equal(flow.scenarios.length, 1);
assert.equal(flow.scenarios[0].id, 'primer_contacto');
assert.equal(flow.scenarios[0].conditions[0].type, 'message_contains');
assert.deepEqual(flow.scenarios[0].conditions[0].keywords, ['hola', 'menu']);
assert.equal(flow.scenarios[0].actions.length, 2);
assert.equal(flow.scenarios[0].actions[0].type, 'send_message');
assert.equal(flow.scenarios[0].actions[0].message.body, 'Hola desde V3');
assert.equal(flow.scenarios[0].actions[1].type, 'send_buttons');
assert.equal(flow.scenarios[0].actions[1].message.buttons.length, 2);

const fallback = graphToFlow({ nodes: [], edges: [] });
assert.equal(fallback.scenarios.length, 1);
assert.equal(fallback.scenarios[0].actions.length, 1);
assert.equal(fallback.scenarios[0].status, 'published');

const preservedConditions = graphToFlow({
    nodes: [
        {
            id: 'trigger_conditions',
            type: 'keyword_trigger',
            data: {
                scenarioId: 'condiciones_avanzadas',
                name: 'Condiciones avanzadas',
                status: 'published',
                stage: 'custom',
                keywords: [{ value: 'fallback' }],
                conditions: [{ type: 'always' }, { type: 'state_equals', value: 'agenda_esperando_correo' }],
                conditionsEditedFromKeywords: false,
            },
        },
        {
            id: 'message_conditions',
            type: 'message',
            data: { settings: { body: 'Condición preservada' } },
        },
    ],
    edges: [{ id: 'edge_conditions', source: 'trigger_conditions', target: 'message_conditions' }],
});

assert.deepEqual(preservedConditions.scenarios[0].conditions, [
    { type: 'always' },
    { type: 'state_equals', value: 'agenda_esperando_correo' },
]);

const specialized = graphToFlow({
    nodes: [
        {
            id: 'trigger_specialized',
            type: 'keyword_trigger',
            data: {
                scenarioId: 'acciones_reales',
                name: 'Acciones reales',
                status: 'published',
                stage: 'validation',
                keywords: [{ value: 'agenda' }],
            },
        },
        {
            id: 'state_1',
            type: 'state',
            data: {
                actionType: 'set_state',
                action: { type: 'set_state', state: 'esperando_cedula', save_response_as: 'cedula' },
                settings: { state: 'esperando_correo', save_response_as: 'correo' },
            },
        },
        {
            id: 'consent_1',
            type: 'consent',
            data: {
                actionType: 'store_consent',
                action: { type: 'store_consent', consent_type: 'datos_protegidos', granted: true },
                settings: { consent_type: 'datos_protegidos', granted: false },
            },
        },
        {
            id: 'agenda_1',
            type: 'sigcenter_agenda',
            data: {
                actionType: 'sigcenter_agenda',
                action: { type: 'sigcenter_agenda', operation: 'list_specialties', send_result: true },
                settings: { operation: 'list_times', send_result: true, store_result_as: 'horarios' },
            },
        },
        {
            id: 'handoff_1',
            type: 'handoff',
            data: {
                actionType: 'handoff_agent',
                action: { type: 'handoff_agent', reason: 'manual_review' },
                settings: { reason: 'agenda_compleja', message: 'Un agente continuará la atención.' },
            },
        },
        {
            id: 'ai_1',
            type: 'ai_agent',
            data: {
                actionType: 'ai_agent',
                action: { type: 'ai_agent', instructions: 'Responder con grounding.', handoff: true },
                settings: { instructions: 'Responder solo con conocimiento autorizado.', handoff: false },
            },
        },
        {
            id: 'template_1',
            type: 'template',
            data: {
                actionType: 'send_template',
                action: { type: 'send_template', template: { name: 'old_tpl' } },
                settings: { name: 'confirmacion_cita', language: 'es' },
            },
        },
    ],
    edges: [
        { id: 'edge_s1', source: 'trigger_specialized', target: 'state_1' },
        { id: 'edge_s2', source: 'state_1', target: 'consent_1' },
        { id: 'edge_s3', source: 'consent_1', target: 'agenda_1' },
        { id: 'edge_s4', source: 'agenda_1', target: 'handoff_1' },
        { id: 'edge_s5', source: 'handoff_1', target: 'ai_1' },
        { id: 'edge_s6', source: 'ai_1', target: 'template_1' },
    ],
});

assert.deepEqual(
    specialized.scenarios[0].actions.map((action) => action.type),
    ['set_state', 'store_consent', 'sigcenter_agenda', 'handoff_agent', 'ai_agent', 'send_template'],
);
assert.equal(specialized.scenarios[0].actions[0].state, 'esperando_correo');
assert.equal(specialized.scenarios[0].actions[0].save_response_as, 'correo');
assert.equal(specialized.scenarios[0].actions[1].granted, false);
assert.equal(specialized.scenarios[0].actions[2].operation, 'list_times');
assert.equal(specialized.scenarios[0].actions[2].store_result_as, 'horarios');
assert.equal(specialized.scenarios[0].actions[3].reason, 'agenda_compleja');
assert.equal(specialized.scenarios[0].actions[4].instructions, 'Responder solo con conocimiento autorizado.');
assert.equal(specialized.scenarios[0].actions[4].handoff, false);
assert.equal(specialized.scenarios[0].actions[5].template.name, 'confirmacion_cita');

const conditionalFlow = graphToFlow({
    nodes: [
        {
            id: 'trigger_branch',
            type: 'keyword_trigger',
            data: {
                scenarioId: 'ramificacion',
                name: 'Ramificación',
                status: 'published',
                stage: 'validation',
                keywords: [{ value: 'validar' }],
            },
        },
        {
            id: 'branch_1',
            type: 'branch',
            data: {
                actionType: 'conditional',
                settings: {
                    condition: { type: 'context_equals', field: 'es_paciente', value: 'si' },
                },
            },
        },
        {
            id: 'yes_message',
            type: 'message',
            data: { settings: { body: 'Sí es paciente' } },
        },
        {
            id: 'no_message',
            type: 'message',
            data: { settings: { body: 'Debe registrarse primero' } },
        },
    ],
    edges: [
        { id: 'edge_branch_1', source: 'trigger_branch', target: 'branch_1' },
        { id: 'edge_branch_yes', source: 'branch_1', sourceHandle: 'yes', target: 'yes_message' },
        { id: 'edge_branch_no', source: 'branch_1', sourceHandle: 'no', target: 'no_message' },
    ],
});

assert.equal(conditionalFlow.scenarios[0].actions.length, 1);
assert.equal(conditionalFlow.scenarios[0].actions[0].type, 'conditional');
assert.deepEqual(conditionalFlow.scenarios[0].actions[0].condition, { type: 'context_equals', field: 'es_paciente', value: 'si' });
assert.equal(conditionalFlow.scenarios[0].actions[0].then.length, 1);
assert.equal(conditionalFlow.scenarios[0].actions[0].else.length, 1);
assert.equal(conditionalFlow.scenarios[0].actions[0].then[0].message.body, 'Sí es paciente');
assert.equal(conditionalFlow.scenarios[0].actions[0].else[0].message.body, 'Debe registrarse primero');

const routedButtonsFlow = graphToFlow({
    nodes: [
        {
            id: 'trigger_buttons',
            type: 'keyword_trigger',
            data: {
                scenarioId: 'botones_ruteados',
                name: 'Botones ruteados',
                status: 'published',
                stage: 'arrival',
                keywords: [{ value: 'hola' }],
            },
        },
        {
            id: 'buttons_routed',
            type: 'quick_replies',
            data: {
                actionType: 'send_buttons',
                settings: {
                    body: '¿Autorizas el uso de datos protegidos?',
                    buttons: [{ id: 'acepto', title: 'Acepto' }, { id: 'no_autorizo', title: 'No autorizo' }],
                },
            },
        },
        {
            id: 'accepted_node',
            type: 'state',
            data: { actionType: 'set_state', settings: { state: 'agenda_esperando_cedula' } },
        },
        {
            id: 'rejected_node',
            type: 'handoff',
            data: { actionType: 'handoff_agent', settings: { reason: 'sin_consentimiento' } },
        },
    ],
    edges: [
        { id: 'edge_buttons_1', source: 'trigger_buttons', target: 'buttons_routed' },
        { id: 'edge_buttons_yes', source: 'buttons_routed', sourceHandle: 'button:acepto', target: 'accepted_node' },
        { id: 'edge_buttons_no', source: 'buttons_routed', sourceHandle: 'button:no_autorizo', target: 'rejected_node' },
    ],
});

const routedButtons = routedButtonsFlow.scenarios[0].actions[0];
assert.equal(routedButtons.type, 'send_buttons');
assert.equal(routedButtons.message.buttons.length, 2);
assert.deepEqual(routedButtons.routes, [
    {
        handle: 'button:acepto',
        label: 'Acepto',
        target_node_id: 'accepted_node',
        target_action_type: 'set_state',
        actions: [
            {
                type: 'set_state',
                state: 'agenda_esperando_cedula',
            },
        ],
    },
    {
        handle: 'button:no_autorizo',
        label: 'No autorizo',
        target_node_id: 'rejected_node',
        target_action_type: 'handoff_agent',
        actions: [
            {
                type: 'handoff_agent',
                reason: 'sin_consentimiento',
            },
        ],
    },
]);

const listFlowGraph = {
    catalogs: { variables: [{ id: 'nombre', token: '{{nombre}}', label: 'Nombre' }] },
    nodes: [
        {
            id: 'trigger_list',
            type: 'keyword_trigger',
            data: {
                scenarioId: 'lista_whatsapp',
                name: 'Lista WhatsApp',
                status: 'published',
                stage: 'arrival',
                keywords: [{ value: 'menu' }],
            },
        },
        {
            id: 'list_1',
            type: 'quick_replies',
            data: {
                actionType: 'send_list',
                settings: {
                    body: 'Hola {{nombre}}, elige una opción',
                    button_text: 'Ver menú',
                    sections: [
                        {
                            title: 'Agenda',
                            rows: [
                                { id: 'agendar', title: 'Agendar cita', description: 'Buscar horarios' },
                                { id: 'mis_citas', title: 'Mis citas', description: 'Consultar cita vigente' },
                            ],
                        },
                    ],
                },
            },
        },
        {
            id: 'agenda_node',
            type: 'sigcenter_agenda',
            data: { actionType: 'sigcenter_agenda', settings: { operation: 'list_specialties' } },
        },
        {
            id: 'citas_node',
            type: 'message',
            data: { settings: { body: 'Voy a revisar tus citas.' } },
        },
    ],
    edges: [
        { id: 'edge_list_1', source: 'trigger_list', target: 'list_1' },
        { id: 'edge_list_agenda', source: 'list_1', sourceHandle: 'list:agendar', target: 'agenda_node' },
        { id: 'edge_list_citas', source: 'list_1', sourceHandle: 'list:mis_citas', target: 'citas_node' },
    ],
};

const listFlow = graphToFlow(listFlowGraph);
const listAction = listFlow.scenarios[0].actions[0];
assert.equal(listAction.type, 'send_list');
assert.equal(listAction.message.button_text, 'Ver menú');
assert.equal(listAction.message.sections[0].rows.length, 2);
assert.equal(listAction.routes[0].handle, 'list:agendar');
assert.equal(validateGraph(listFlowGraph).errors.length, 4);

const orConditionsFlow = graphToFlow({
    nodes: [
        {
            id: 'trigger_or',
            type: 'keyword_trigger',
            data: {
                scenarioId: 'or_conditions',
                name: 'OR conditions',
                status: 'published',
                stage: 'custom',
                conditionsEditedFromKeywords: false,
                conditions_match: 'any',
                conditions: [
                    { type: 'message_contains', keywords: ['agenda'] },
                    { type: 'state_equals', value: 'agenda_esperando_cedula' },
                ],
            },
        },
        {
            id: 'message_or',
            type: 'message',
            data: { settings: { body: 'OR match' } },
        },
    ],
    edges: [{ id: 'edge_or', source: 'trigger_or', target: 'message_or' }],
});

assert.deepEqual(orConditionsFlow.scenarios[0].conditions, [
    {
        type: 'any',
        conditions: [
            { type: 'message_contains', keywords: ['agenda'] },
            { type: 'state_equals', value: 'agenda_esperando_cedula' },
        ],
    },
]);

const invalidGraph = {
    catalogs: { variables: [{ id: 'nombre', token: '{{nombre}}', label: 'Nombre' }] },
    nodes: [
        {
            id: 'trigger_invalid',
            type: 'keyword_trigger',
            data: { name: 'Inválido', status: 'published', keywords: [{ value: 'hola' }] },
        },
        {
            id: 'buttons_invalid',
            type: 'quick_replies',
            data: {
                actionType: 'send_buttons',
                settings: { body: 'Hola {{variable_inexistente}}', buttons: [{ id: 'ok', title: 'OK' }] },
            },
        },
        {
            id: 'branch_invalid',
            type: 'branch',
            data: { actionType: 'conditional', settings: { condition: { type: 'context_equals', field: 'variable_inexistente', value: 'si' } } },
        },
    ],
    edges: [
        { id: 'edge_invalid_1', source: 'trigger_invalid', target: 'buttons_invalid' },
        { id: 'edge_invalid_2', source: 'buttons_invalid', sourceHandle: 'button:ok', target: 'branch_invalid' },
        { id: 'edge_invalid_yes', source: 'branch_invalid', sourceHandle: 'yes', target: 'buttons_invalid' },
    ],
};

const invalidValidation = validateGraph(invalidGraph);
assert.ok(invalidValidation.errors.some((issue) => issue.message.includes('{{variable_inexistente}}')));
assert.ok(invalidValidation.errors.some((issue) => issue.message.includes('rama no')));

const importedGraph = contractToGraph({
    schema: {
        name: 'Importado real',
        scenarios: [
            {
                id: 'arrival',
                name: 'Llegada',
                status: 'published',
                stage: 'arrival',
                conditions: [{ type: 'message_contains', keywords: ['hola'] }],
                actions: [
                    {
                        type: 'send_buttons',
                        message: {
                            type: 'buttons',
                            body: '¿Autorizas?',
                            buttons: [
                                { id: 'acepto', title: 'Acepto' },
                                { id: 'rechazo', title: 'No autorizo' },
                            ],
                        },
                        routes: [
                            {
                                handle: 'button:acepto',
                                label: 'Acepto',
                                actions: [
                                    { type: 'set_state', state: 'agenda_esperando_cedula', save_response_as: 'cedula' },
                                    {
                                        type: 'conditional',
                                        condition: { type: 'context_equals', field: 'patient_new', value: true },
                                        then: [{ type: 'send_message', message: { type: 'text', body: 'Paciente nuevo' } }],
                                        else: [{ type: 'send_message', message: { type: 'text', body: 'Paciente existente' } }],
                                    },
                                ],
                            },
                            {
                                handle: 'button:rechazo',
                                label: 'No autorizo',
                                actions: [{ type: 'handoff_agent', reason: 'sin_consentimiento' }],
                            },
                        ],
                    },
                    {
                        type: 'sigcenter_agenda',
                        operation: 'list_times',
                        routes: [
                            {
                                handle: 'success',
                                label: 'Éxito',
                                actions: [
                                    {
                                        type: 'send_list',
                                        message: {
                                            type: 'list',
                                            body: 'Horarios',
                                            button_text: 'Ver horarios',
                                            sections: [{ title: 'Mañana', rows: [{ id: '08_00', title: '08:00' }] }],
                                        },
                                        routes: [
                                            {
                                                handle: 'list:08_00',
                                                label: '08:00',
                                                actions: [{ type: 'send_message', message: { type: 'text', body: 'Confirmado' } }],
                                            },
                                        ],
                                    },
                                ],
                            },
                            {
                                handle: 'empty',
                                label: 'Sin disponibilidad',
                                actions: [{ type: 'send_message', message: { type: 'text', body: 'Sin horarios' } }],
                            },
                        ],
                    },
                ],
            },
        ],
    },
    catalogs: {
        variables: [
            { id: 'patient_new', token: '{{patient_new}}', label: 'Paciente nuevo' },
            { id: 'agenda_branch', token: '{{agenda_branch}}', label: 'Rama de agenda' },
        ],
    },
});

assert.equal(importedGraph.flowName, 'Importado real');
assert.ok(importedGraph.nodes.length >= 10);
assert.ok(importedGraph.edges.some((edge) => edge.sourceHandle === 'button:acepto'));
assert.ok(importedGraph.edges.some((edge) => edge.sourceHandle === 'button:rechazo'));
assert.ok(importedGraph.edges.some((edge) => edge.sourceHandle === 'yes'));
assert.ok(importedGraph.edges.some((edge) => edge.sourceHandle === 'no'));
assert.ok(importedGraph.edges.some((edge) => edge.sourceHandle === 'success'));
assert.ok(importedGraph.edges.some((edge) => edge.sourceHandle === 'empty'));
assert.ok(importedGraph.edges.some((edge) => edge.sourceHandle === 'list:08_00'));
assert.ok(importedGraph.nodes.every((node) => node.data?.importedFrom || node.type === 'keyword_trigger'));

const importedValidation = validateGraph(importedGraph);
assert.equal(importedValidation.errors.length, 0);
assert.ok(importedValidation.warnings.length >= 1);

const roundTrip = graphToFlow(importedGraph);
const importedButtons = roundTrip.scenarios[0].actions[0];
assert.equal(importedButtons.routes[0].handle, 'button:acepto');
assert.equal(importedButtons.routes[0].actions[1].type, 'conditional');
assert.equal(importedButtons.routes[0].actions[1].then[0].message.body, 'Paciente nuevo');
assert.equal(roundTrip.scenarios[0].actions[1].routes[0].actions[0].routes[0].handle, 'list:08_00');
