import { createNode } from './domain.js';
import { actionToEditableData, actionToNodeType } from './actionCatalog.js';

const X_START = 40;
const X_STEP = 320;
const Y_START = 120;
const Y_STEP = 190;
const SCENARIO_GAP = 360;

export function contractToGraph(contract) {
    const flow = contract?.schema || contract?.flow || contract || {};
    const scenarios = Array.isArray(flow.scenarios) ? flow.scenarios : [];
    const nodes = [];
    const edges = [];
    const occupiedLanes = new Set();

    scenarios.forEach((scenario, scenarioIndex) => {
        const layout = createLayout(scenarioIndex, occupiedLanes);
        const trigger = stableNode('keyword_trigger', `trigger_${slug(scenario.id || scenario.name || scenarioIndex + 1)}`, {
            x: X_START,
            y: layout.y(0),
        }, {
            scenarioId: scenario.id || `scenario_${scenarioIndex + 1}`,
            name: scenario.name || `Escenario ${scenarioIndex + 1}`,
            status: scenario.status || 'published',
            stage: scenario.stage || 'custom',
            intercept_menu: Boolean(scenario.intercept_menu),
            conditions: Array.isArray(scenario.conditions) ? scenario.conditions : [],
            conditionsEditedFromKeywords: false,
            keywords: extractKeywords(scenario),
            importedFrom: 'contract',
        });

        nodes.push(trigger);
        appendActionChain({
            actions: Array.isArray(scenario.actions) ? scenario.actions : [],
            sourceNode: trigger,
            sourceHandle: 'source',
            depth: 1,
            lane: 0,
            scenario,
            path: [scenarioIndex + 1],
            layout,
            nodes,
            edges,
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
        catalogs: contract?.catalogs || {},
        nodes,
        edges,
    };
}

function appendActionChain({ actions, sourceNode, sourceHandle, depth, lane, scenario, path, layout, nodes, edges, routeMeta = null }) {
    let previous = sourceNode;
    let previousHandle = sourceHandle || 'source';
    let lastNode = sourceNode;

    actions.forEach((action, index) => {
        const node = actionNode(action, {
            scenario,
            path: [...path, index + 1],
            depth,
            lane,
            layout,
            routeMeta,
        });

        nodes.push(node);
        edges.push(edge(previous, node, previousHandle));

        appendEmbeddedBranches({
            action,
            node,
            scenario,
            path: [...path, index + 1],
            depth: depth + 1,
            lane,
            layout,
            nodes,
            edges,
            routeMeta: null,
        });

        previous = node;
        previousHandle = 'source';
        lastNode = node;
        depth += 1;
    });

    return lastNode;
}

function appendEmbeddedBranches({ action, node, scenario, path, depth, lane, layout, nodes, edges }) {
    if (action?.type === 'conditional') {
        appendActionChain({
            actions: Array.isArray(action.then) ? action.then : [],
            sourceNode: node,
            sourceHandle: 'yes',
            depth,
            lane: layout.claimLane(lane - 1),
            scenario,
            path: [...path, 'yes'],
            layout,
            nodes,
            edges,
            routeMeta: null,
        });
        appendActionChain({
            actions: Array.isArray(action.else) ? action.else : [],
            sourceNode: node,
            sourceHandle: 'no',
            depth,
            lane: layout.claimLane(lane + 1),
            scenario,
            path: [...path, 'no'],
            layout,
            nodes,
            edges,
            routeMeta: null,
        });
    }

    const routes = Array.isArray(action?.routes) ? action.routes : [];
    routes.forEach((route, routeIndex) => {
        const routeLane = layout.claimLane(lane + routeIndex + 1);
        appendActionChain({
            actions: Array.isArray(route.actions) ? route.actions : [],
            sourceNode: node,
            sourceHandle: route.handle || `route:${routeIndex + 1}`,
            depth,
            lane: routeLane,
            scenario,
            path: [...path, `route_${routeIndex + 1}`],
            layout,
            nodes,
            edges,
            routeMeta: {
                handle: route.handle || `route:${routeIndex + 1}`,
                label: route.label || route.handle || `Ruta ${routeIndex + 1}`,
            },
        });
    });
}

function actionNode(action, { scenario, path, depth, lane, layout, routeMeta }) {
    const type = actionToNodeType(action);
    const data = actionToEditableData(action);
    const handle = path[path.length - 1];

    return stableNode(type, `node_${slug(scenario.id || scenario.name || 'scenario')}_${path.map(slug).join('_')}`, {
        x: X_START + depth * X_STEP,
        y: layout.y(lane),
    }, {
        ...data,
        scenarioId: scenario.id || '',
        stage: scenario.stage || 'custom',
        importedFrom: 'contract',
        routeHandle: routeMeta?.handle || (typeof handle === 'string' ? handle : undefined),
        routeLabel: routeMeta?.label,
    });
}

function stableNode(type, id, position, data) {
    return {
        ...createNode(type, position, data),
        id,
    };
}

function edge(source, target, sourceHandle = 'source') {
    return {
        id: `edge_${source.id}_${sourceHandle}_${target.id}`.replace(/[^a-zA-Z0-9_:-]+/g, '_'),
        source: source.id,
        sourceHandle,
        target: target.id,
        targetHandle: 'in',
    };
}

function createLayout(scenarioIndex, occupiedLanes) {
    const base = scenarioIndex * SCENARIO_GAP;

    return {
        y: (lane) => Y_START + base + lane * Y_STEP,
        claimLane: (preferred) => {
            let lane = preferred;
            let guard = 0;
            while (occupiedLanes.has(`${scenarioIndex}:${lane}`) && guard < 200) {
                lane += preferred >= 0 ? 1 : -1;
                guard += 1;
            }
            occupiedLanes.add(`${scenarioIndex}:${lane}`);
            return lane;
        },
    };
}

function extractKeywords(scenario) {
    const conditions = Array.isArray(scenario.conditions) ? scenario.conditions : [];
    const messageCondition = findMessageContainsCondition(conditions);
    const keywords = Array.isArray(messageCondition?.keywords) ? messageCondition.keywords : [];

    return keywords.map((keyword, index) => ({
        id: `kw_${index + 1}`,
        value: String(keyword),
        matchType: 'contains',
    }));
}

function findMessageContainsCondition(conditions) {
    for (const condition of conditions) {
        if (condition?.type === 'message_contains') return condition;
        if ((condition?.type === 'all' || condition?.type === 'any') && Array.isArray(condition.conditions)) {
            const nested = findMessageContainsCondition(condition.conditions);
            if (nested) return nested;
        }
    }

    return null;
}

function slug(value) {
    return String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_-]+/g, '_')
        .replace(/^_+|_+$/g, '') || 'x';
}
