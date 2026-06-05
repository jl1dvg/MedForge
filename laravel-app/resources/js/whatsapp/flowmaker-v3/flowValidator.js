import { editableDataToAction, nodeOutputHandles } from './actionCatalog.js';

const TEXT_KEYS = ['body', 'caption', 'header', 'footer', 'message', 'instructions'];

export function validateGraph(graph) {
    const nodes = Array.isArray(graph?.nodes) ? graph.nodes : [];
    const edges = Array.isArray(graph?.edges) ? graph.edges : [];
    const variables = normalizeVariableIds(graph?.catalogs?.variables || []);
    const issues = [];
    const outgoing = outgoingByNode(edges);
    const incoming = incomingByNode(edges);
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const triggers = nodes.filter(isTrigger);

    if (triggers.length === 0) {
        issues.push(error('El flujo necesita al menos un disparador.', null));
    }

    triggers.forEach((node) => {
        const targets = outgoing.get(node.id) || [];
        if (targets.length === 0) {
            issues.push(error('El disparador no continúa hacia ninguna acción.', node.id));
        }
        validateConditions(node.data?.conditions || [], variables, issues, node.id);
    });

    nodes.filter((node) => !isTrigger(node)).forEach((node) => {
        const action = editableDataToAction(node);
        const actionType = action.type || node.data?.actionType || '';
        const handles = nodeOutputHandles(node);
        const nodeEdges = outgoing.get(node.id) || [];

        if (!incoming.has(node.id)) {
            issues.push(warning('Este nodo no es alcanzable desde otro nodo.', node.id));
        }

        validateAction(action, actionType, node, variables, issues);
        validateRoutableHandles(node, handles, nodeEdges, issues);
    });

    edges.forEach((edge) => {
        if (!byId.has(edge.source) || !byId.has(edge.target)) {
            issues.push(error('Hay una conexión apuntando a un nodo inexistente.', edge.source || edge.target || null, edge.id));
        }
    });

    detectCycles(nodes, outgoing, issues);

    return {
        errors: issues.filter((issue) => issue.level === 'error'),
        warnings: issues.filter((issue) => issue.level === 'warning'),
        issues,
    };
}

function validateAction(action, actionType, node, variables, issues) {
    switch (actionType) {
        case 'send_message':
            if (!text(action.message?.body) && !text(action.message?.link)) {
                issues.push(error('El mensaje necesita texto o archivo/link.', node.id));
            }
            break;
        case 'send_buttons':
            if (!text(action.message?.body)) {
                issues.push(error('Los botones necesitan texto principal.', node.id));
            }
            if (!Array.isArray(action.message?.buttons) || action.message.buttons.length === 0) {
                issues.push(error('Agrega al menos un botón.', node.id));
            }
            break;
        case 'send_list':
            if (!text(action.message?.body)) {
                issues.push(error('La lista necesita texto principal.', node.id));
            }
            if (listRows(action).length === 0) {
                issues.push(error('Agrega al menos una opción a la lista.', node.id));
            }
            break;
        case 'send_template':
            if (!text(action.template?.name)) {
                issues.push(error('La plantilla necesita nombre.', node.id));
            }
            break;
        case 'conditional':
            validateConditionObject(action.condition || {}, variables, issues, node.id);
            break;
        case 'set_state':
            if (!text(action.state) && !text(action.next_state) && !text(action.save_response_as)) {
                issues.push(error('Estado/captura necesita estado o campo donde guardar.', node.id));
            }
            break;
        case 'store_consent':
            if (!text(action.consent_type)) {
                issues.push(error('Consentimiento necesita tipo.', node.id));
            }
            break;
        case 'sigcenter_agenda':
            if (!text(action.operation)) {
                issues.push(error('Sigcenter necesita una operación.', node.id));
            }
            break;
        case 'ai_agent':
            if (!text(action.instructions)) {
                issues.push(warning('El agente IA no tiene instrucciones.', node.id));
            }
            break;
        default:
            issues.push(warning(`Acción ${actionType || 'desconocida'} se preserva como fallback técnico.`, node.id));
    }

    collectTextValues(action).forEach((value) => validateVariables(value, variables, issues, node.id));
}

function validateRoutableHandles(node, handles, nodeEdges, issues) {
    const actionType = node.data?.actionType || node.data?.action?.type || '';
    const connected = new Set(nodeEdges.map((edge) => edge.sourceHandle || 'source'));
    const criticalHandles = handles.filter((handle) => handle.id !== 'source');

    if (actionType === 'conditional') {
        ['yes', 'no'].forEach((handle) => {
            if (!connected.has(handle)) {
                issues.push(error(`La condición necesita rama ${handle === 'yes' ? 'sí' : 'no'} conectada.`, node.id));
            }
        });
        return;
    }

    if (actionType === 'send_buttons' || actionType === 'send_list') {
        criticalHandles
            .filter((handle) => handle.id !== 'fallback')
            .forEach((handle) => {
                if (!connected.has(handle.id)) {
                    issues.push(error(`La salida "${handle.label}" no tiene ruta conectada.`, node.id));
                }
            });
        return;
    }

    if (actionType === 'sigcenter_agenda') {
        ['success', 'missing_data', 'empty', 'error'].forEach((handle) => {
            if (!connected.has(handle)) {
                issues.push(error(`Sigcenter necesita ruta "${labelForHandle(handle)}".`, node.id));
            }
        });
        return;
    }

    if (actionType === 'ai_agent') {
        ['resolved', 'handoff'].forEach((handle) => {
            if (!connected.has(handle)) {
                issues.push(warning(`Agente IA sin ruta "${labelForHandle(handle)}".`, node.id));
            }
        });
    }
}

function validateConditions(conditions, variables, issues, nodeId) {
    if (!Array.isArray(conditions)) return;
    conditions.forEach((condition) => validateConditionObject(condition, variables, issues, nodeId));
}

function validateConditionObject(condition, variables, issues, nodeId) {
    if (!condition || typeof condition !== 'object') return;
    const type = condition.type || 'always';

    if (type === 'all' || type === 'any') {
        const children = Array.isArray(condition.conditions) ? condition.conditions : [];
        if (children.length === 0) {
            issues.push(error('El grupo de condiciones no tiene reglas.', nodeId));
        }
        children.forEach((child) => validateConditionObject(child, variables, issues, nodeId));
        return;
    }

    if (type.startsWith('context_') && variables.size > 0) {
        const field = condition.field || condition.variable || condition.key || '';
        if (field && !variables.has(field)) {
            issues.push(error(`Variable de condición inválida: {{${field}}}.`, nodeId));
        }
    }
}

function validateVariables(value, variables, issues, nodeId) {
    if (variables.size === 0 || typeof value !== 'string') return;

    for (const match of value.matchAll(/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/g)) {
        if (!variables.has(match[1])) {
            issues.push(error(`Variable no disponible por backend: {{${match[1]}}}.`, nodeId));
        }
    }
}

function detectCycles(nodes, outgoing, issues) {
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const visiting = new Set();
    const visited = new Set();

    function visit(nodeId, stack = []) {
        if (visiting.has(nodeId)) {
            issues.push(warning('Hay un ciclo visual; revisa que tenga salida terminal o handoff.', nodeId));
            return;
        }
        if (visited.has(nodeId)) return;

        visiting.add(nodeId);
        (outgoing.get(nodeId) || []).forEach((edge) => {
            if (byId.has(edge.target)) visit(edge.target, [...stack, nodeId]);
        });
        visiting.delete(nodeId);
        visited.add(nodeId);
    }

    nodes.filter(isTrigger).forEach((node) => visit(node.id));
}

function normalizeVariableIds(variables) {
    return new Set((Array.isArray(variables) ? variables : [])
        .flatMap((variable) => [variable.id, tokenToId(variable.token)])
        .filter(Boolean));
}

function tokenToId(token) {
    return typeof token === 'string' ? token.replace(/^\{\{\s*/, '').replace(/\s*\}\}$/, '') : '';
}

function outgoingByNode(edges) {
    const map = new Map();
    edges.forEach((edge) => {
        if (!map.has(edge.source)) map.set(edge.source, []);
        map.get(edge.source).push(edge);
    });
    return map;
}

function incomingByNode(edges) {
    const map = new Map();
    edges.forEach((edge) => {
        if (!map.has(edge.target)) map.set(edge.target, []);
        map.get(edge.target).push(edge);
    });
    return map;
}

function collectTextValues(value) {
    const values = [];

    function walk(current, key = '') {
        if (typeof current === 'string' && (TEXT_KEYS.includes(key) || current.includes('{{'))) {
            values.push(current);
            return;
        }

        if (Array.isArray(current)) {
            current.forEach((item) => walk(item));
            return;
        }

        if (current && typeof current === 'object') {
            Object.entries(current).forEach(([childKey, childValue]) => walk(childValue, childKey));
        }
    }

    walk(value);
    return values;
}

function listRows(action) {
    return (Array.isArray(action.message?.sections) ? action.message.sections : [])
        .flatMap((section) => Array.isArray(section.rows) ? section.rows : []);
}

function labelForHandle(handle) {
    return {
        success: 'éxito',
        missing_data: 'dato faltante',
        empty: 'sin disponibilidad',
        error: 'error',
        resolved: 'resuelto',
        handoff: 'derivar',
    }[handle] || handle;
}

function isTrigger(node) {
    return node?.type === 'keyword_trigger' || node?.type === 'incoming_message';
}

function text(value) {
    return String(value ?? '').trim() !== '';
}

function error(message, nodeId = null, edgeId = null) {
    return { level: 'error', message, nodeId, edgeId };
}

function warning(message, nodeId = null, edgeId = null) {
    return { level: 'warning', message, nodeId, edgeId };
}
