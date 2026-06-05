export function graphToFlow(graph) {
    const nodes = Array.isArray(graph?.nodes) ? graph.nodes : [];
    const edges = Array.isArray(graph?.edges) ? graph.edges : [];
    const triggerNodes = nodes.filter((node) => isTrigger(node));

    const scenarios = triggerNodes.map((trigger, index) => {
        const actions = collectActionNodes(trigger, nodes, edges).map((node) => nodeToAction(node));

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

    const keywords = (Array.isArray(trigger.data?.keywords) ? trigger.data.keywords : [])
        .map((keyword) => String(keyword?.value || keyword || '').trim())
        .filter(Boolean);

    if (keywords.length === 0) {
        return [{ type: 'always' }];
    }

    return [{ type: 'message_contains', keywords }];
}

function collectActionNodes(trigger, nodes, edges) {
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const outgoing = new Map();

    edges.forEach((edge) => {
        if (!edge?.source || !edge?.target) return;
        if (!outgoing.has(edge.source)) outgoing.set(edge.source, []);
        outgoing.get(edge.source).push(edge.target);
    });

    const ordered = [];
    const seen = new Set([trigger.id]);
    const queue = [...(outgoing.get(trigger.id) || [])];

    while (queue.length > 0) {
        const id = queue.shift();
        if (seen.has(id)) continue;
        seen.add(id);

        const node = byId.get(id);
        if (!node || isTrigger(node)) continue;

        ordered.push(node);
        queue.push(...(outgoing.get(id) || []));
    }

    return ordered;
}

function nodeToAction(node) {
    const settings = node.data?.settings || {};
    const existing = node.data?.action;

    if (existing && Object.keys(settings).length === 0) {
        return existing;
    }

    switch (node.type) {
        case 'message':
            return {
                type: 'send_message',
                message: {
                    type: 'text',
                    body: String(settings.body || existing?.message?.body || ''),
                },
            };
        case 'quick_replies':
            return {
                type: 'send_buttons',
                message: {
                    type: 'buttons',
                    body: String(settings.body || existing?.message?.body || 'Elige una opción'),
                    buttons: normalizeButtons(settings.buttons || existing?.message?.buttons || []),
                },
            };
        case 'media':
            return {
                type: 'send_message',
                message: {
                    type: settings.media_type || existing?.message?.type || 'image',
                    body: settings.caption || existing?.message?.body || '',
                    link: settings.link || existing?.message?.link || '',
                },
            };
        case 'template':
            return {
                type: 'send_template',
                template: {
                    name: settings.name || existing?.template?.name || '',
                    language: settings.language || existing?.template?.language || 'es',
                },
            };
        case 'ai_agent':
            return {
                type: 'ai_agent',
                instructions: settings.instructions || existing?.instructions || '',
                kb_filters: settings.kb_filters || existing?.kb_filters || {},
                handoff: Boolean(settings.handoff ?? existing?.handoff ?? true),
            };
        case 'end':
            return {
                type: settings.action === 'handoff' ? 'handoff_agent' : 'set_state',
                state: settings.state || existing?.state || 'closed',
            };
        default:
            return existing || fallbackAction();
    }
}

function normalizeButtons(buttons) {
    const normalized = buttons
        .map((button, index) => {
            if (typeof button === 'string') {
                return { id: `opcion_${index + 1}`, title: button };
            }

            return {
                id: button?.id || `opcion_${index + 1}`,
                title: button?.title || button?.label || `Opción ${index + 1}`,
            };
        })
        .filter((button) => button.title);

    return normalized.length > 0
        ? normalized.slice(0, 3)
        : [{ id: 'opcion_1', title: 'Opción 1' }];
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
