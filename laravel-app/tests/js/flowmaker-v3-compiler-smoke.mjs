import assert from 'node:assert/strict';
import { graphToFlow } from '../../resources/js/whatsapp/flowmaker-v3/graphCompiler.js';

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
