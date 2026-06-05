import { createNode } from './domain.js';
import { actionToEditableData, actionToNodeType } from './actionCatalog.js';

export function contractToGraph(contract) {
    const flow = contract?.schema || contract?.flow || contract || {};
    const scenarios = Array.isArray(flow.scenarios) ? flow.scenarios : [];
    const nodes = [];
    const edges = [];

    scenarios.forEach((scenario, scenarioIndex) => {
        const trigger = createNode('keyword_trigger', { x: 40, y: 120 + scenarioIndex * 260 }, {
            scenarioId: scenario.id || `scenario_${scenarioIndex + 1}`,
            name: scenario.name || `Escenario ${scenarioIndex + 1}`,
            status: scenario.status || 'published',
            stage: scenario.stage || 'custom',
            intercept_menu: Boolean(scenario.intercept_menu),
            conditions: Array.isArray(scenario.conditions) ? scenario.conditions : [],
            conditionsEditedFromKeywords: false,
            keywords: extractKeywords(scenario),
        });
        nodes.push(trigger);

        const actions = Array.isArray(scenario.actions) ? scenario.actions : [];
        let previous = trigger;

        actions.forEach((action, actionIndex) => {
            const node = createNode(actionToNodeType(action), {
                x: 380 + actionIndex * 320,
                y: 120 + scenarioIndex * 260,
            }, actionToEditableData(action));

            nodes.push(node);
            edges.push({
                id: `edge_${previous.id}_${node.id}`,
                source: previous.id,
                sourceHandle: 'source',
                target: node.id,
                targetHandle: 'in',
            });
            previous = node;
        });
    });

    if (nodes.length === 0) {
        nodes.push(createNode('keyword_trigger', { x: 40, y: 180 }, {
            scenarioId: 'primer_contacto',
            name: 'Primer contacto',
            status: 'published',
            stage: 'arrival',
            keywords: [{ id: 'kw_hola', value: 'hola', matchType: 'contains' }],
        }));
        nodes.push(createNode('message', { x: 380, y: 180 }, {
            action: {
                type: 'send_message',
                message: { type: 'text', body: 'Hola, soy el asistente virtual. ¿En qué te ayudo?' },
            },
        }));
        edges.push({
            id: 'edge_default',
            source: nodes[0].id,
            sourceHandle: 'source',
            target: nodes[1].id,
            targetHandle: 'in',
        });
    }

    return {
        flowName: flow.name || 'Flujo principal de WhatsApp',
        flowDescription: flow.description || '',
        settings: flow.settings || { timezone: 'America/Guayaquil' },
        nodes,
        edges,
    };
}

function extractKeywords(scenario) {
    const conditions = Array.isArray(scenario.conditions) ? scenario.conditions : [];
    const messageCondition = conditions.find((condition) => condition?.type === 'message_contains');
    const keywords = Array.isArray(messageCondition?.keywords) ? messageCondition.keywords : [];

    return keywords.map((keyword, index) => ({
        id: `kw_${index + 1}`,
        value: String(keyword),
        matchType: 'contains',
    }));
}
