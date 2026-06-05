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
