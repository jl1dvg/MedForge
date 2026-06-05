import { editableDataToAction, nodeOutputHandles } from './actionCatalog.js';

export function graphToFlow(graph) {
    const nodes = Array.isArray(graph?.nodes) ? graph.nodes : [];
    const edges = Array.isArray(graph?.edges) ? graph.edges : [];
    const triggerNodes = nodes.filter((node) => isTrigger(node));

    const context = createGraphContext(nodes, edges);

    const scenarios = triggerNodes.map((trigger, index) => {
        const actions = compileLinearActions(startTargets(trigger, context), context, new Set([trigger.id]));

        return {
            id: scenarioId(trigger, index),
            name: String(trigger.data?.name || `Escenario ${index + 1}`),
            description: String(trigger.data?.description || ''),
            status: trigger.data?.status || 'published',
            stage: trigger.data?.stage || 'custom',
            intercept_menu: trigger.data?.intercept_menu || trigger.data?.stage === 'arrival',
            conditions: triggerToConditions(trigger),
            actions: actions.length > 0 ? actions : [fallbackAction()],
        };
    });

    return {
        name: graph?.flowName || 'Flujo principal de WhatsApp',
        description: graph?.flowDescription || 'Flujo publicado desde Flowmaker V3',
        settings: {
            timezone: 'America/Guayaquil',
            ...(graph?.settings || {}),
        },
        scenarios: scenarios.length > 0 ? scenarios : [fallbackScenario()],
    };
}

function isTrigger(node) {
    return node?.type === 'keyword_trigger' || node?.type === 'incoming_message';
}

function scenarioId(trigger, index) {
    const raw = trigger.data?.scenarioId || trigger.data?.name || `scenario_${index + 1}`;
    return String(raw)
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_-]+/g, '_')
        .replace(/^_+|_+$/g, '') || `scenario_${index + 1}`;
}

function triggerToConditions(trigger) {
    if (trigger.type === 'incoming_message') {
        return [{ type: 'always' }];
    }

    if (!trigger.data?.conditionsEditedFromKeywords && Array.isArray(trigger.data?.conditions) && trigger.data.conditions.length > 0) {
        return trigger.data.conditions;
    }

    const keywords = (Array.isArray(trigger.data?.keywords) ? trigger.data.keywords : [])
        .map((keyword) => String(keyword?.value || keyword || '').trim())
        .filter(Boolean);

    if (keywords.length === 0) {
        return [{ type: 'always' }];
    }

    return [{ type: 'message_contains', keywords }];
}

function createGraphContext(nodes, edges) {
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const outgoing = new Map();

    edges.forEach((edge) => {
        if (!edge?.source || !edge?.target) return;
        if (!outgoing.has(edge.source)) outgoing.set(edge.source, []);
        outgoing.get(edge.source).push(edge);
    });

    return { byId, outgoing };
}

function startTargets(trigger, context) {
    return (context.outgoing.get(trigger.id) || [])
        .map((edge) => edge.target)
        .filter(Boolean);
}

function compileLinearActions(targetIds, context, seen) {
    const actions = [];
    const queue = [...targetIds];

    while (queue.length > 0) {
        const id = queue.shift();
        if (seen.has(id)) continue;
        seen.add(id);

        const node = context.byId.get(id);
        if (!node || isTrigger(node)) continue;

        actions.push(nodeToAction(node, context, seen));
        queue.push(...linearTargets(node, context));
    }

    return actions;
}

function linearTargets(node, context) {
    return (context.outgoing.get(node.id) || [])
        .filter((edge) => isLinearEdge(node, edge))
        .map((edge) => edge.target)
        .filter(Boolean);
}

function routeEdges(node, context) {
    if (isConditionalNode(node)) {
        return [];
    }

    return (context.outgoing.get(node.id) || [])
        .filter((edge) => !isLinearEdge(node, edge) && edge.sourceHandle);
}

function branchTargets(node, context, handle) {
    return (context.outgoing.get(node.id) || [])
        .filter((edge) => edge.sourceHandle === handle)
        .map((edge) => edge.target)
        .filter(Boolean);
}

function isLinearEdge(node, edge) {
    if (isTrigger(node)) {
        return true;
    }

    const handle = edge?.sourceHandle || 'source';
    return handle === 'source' || handle === 'default' || handle === 'continue';
}

function isConditionalNode(node) {
    const actionType = node?.data?.actionType || node?.data?.action?.type;
    return node?.type === 'branch' || actionType === 'conditional';
}

function nodeToAction(node, context, seen) {
    const action = editableDataToAction(node);

    if (isConditionalNode(node)) {
        return {
            ...action,
            type: 'conditional',
            condition: action.condition || node.data?.settings?.condition || { type: 'always' },
            then: compileLinearActions(branchTargets(node, context, 'yes'), context, new Set(seen)),
            else: compileLinearActions(branchTargets(node, context, 'no'), context, new Set(seen)),
        };
    }

    const routes = compileRoutes(node, context, seen);
    if (routes.length === 0) {
        return action;
    }

    return {
        ...action,
        routes,
    };
}

function compileRoutes(node, context, seen) {
    const handles = nodeOutputHandles(node);
    const labels = new Map(handles.map((handle) => [handle.id, handle.label]));

    return routeEdges(node, context).map((edge) => {
        const target = context.byId.get(edge.target);
        const targetAction = target ? editableDataToAction(target) : null;

        return {
            handle: edge.sourceHandle,
            label: labels.get(edge.sourceHandle) || edge.sourceHandle,
            target_node_id: edge.target,
            target_action_type: targetAction?.type || null,
            actions: compileLinearActions([edge.target], context, new Set(seen)),
        };
    });
}

function fallbackAction() {
    return {
        type: 'send_message',
        message: {
            type: 'text',
            body: 'Hola, soy el asistente virtual. ¿En qué te ayudo?',
        },
    };
}

function fallbackScenario() {
    return {
        id: 'primer_contacto',
        name: 'Primer contacto',
        description: '',
        status: 'published',
        stage: 'arrival',
        intercept_menu: true,
        conditions: [{ type: 'message_contains', keywords: ['hola'] }],
        actions: [fallbackAction()],
    };
}
