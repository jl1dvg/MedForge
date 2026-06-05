export const STATUS_OPTIONS = [
    { value: 'published', label: 'Publicado' },
    { value: 'draft', label: 'Borrador' },
    { value: 'paused', label: 'Pausado' },
];

export const STAGE_OPTIONS = [
    { value: 'arrival', label: 'Llegada' },
    { value: 'menu', label: 'Menú' },
    { value: 'validation', label: 'Validación' },
    { value: 'scheduling', label: 'Agendamiento' },
    { value: 'custom', label: 'Personalizado' },
];

export const ACTION_TYPE_OPTIONS = [
    { value: 'send_message', label: 'Mensaje' },
    { value: 'send_buttons', label: 'Botones' },
    { value: 'send_list', label: 'Lista' },
    { value: 'send_template', label: 'Plantilla' },
    { value: 'conditional', label: 'Condición' },
    { value: 'set_state', label: 'Estado/captura' },
    { value: 'store_consent', label: 'Consentimiento' },
    { value: 'sigcenter_agenda', label: 'Agenda Sigcenter' },
    { value: 'handoff_agent', label: 'Derivar agente' },
    { value: 'ai_agent', label: 'Agente IA' },
];

export function actionToNodeType(action) {
    switch (action?.type) {
        case 'send_template':
            return 'template';
        case 'conditional':
            return 'branch';
        case 'send_buttons':
        case 'send_list':
            return 'quick_replies';
        case 'set_state':
            return 'state';
        case 'store_consent':
            return 'consent';
        case 'sigcenter_agenda':
            return 'sigcenter_agenda';
        case 'handoff_agent':
            return 'handoff';
        case 'ai_agent':
            return 'ai_agent';
        default:
            if (action?.message?.type && action.message.type !== 'text' && action.message.type !== 'buttons') {
                return 'media';
            }
            return 'message';
    }
}

export function actionToEditableData(action = {}) {
    const type = action.type || 'send_message';
    return {
        action,
        actionType: type,
        settings: actionToSettings(action),
    };
}

export function editableDataToAction(node) {
    const actionType = node?.data?.actionType || node?.data?.action?.type || nodeTypeToActionType(node?.type);
    const existing = isObject(node?.data?.action) ? node.data.action : {};
    const settings = isObject(node?.data?.settings) ? node.data.settings : {};

    switch (actionType) {
        case 'send_buttons':
        case 'send_list':
            return {
                ...existing,
                type: actionType,
                message: {
                    ...(existing.message || {}),
                    type: actionType === 'send_list' ? 'list' : 'buttons',
                    header: settings.header ?? existing.message?.header ?? '',
                    body: settings.body ?? existing.message?.body ?? 'Elige una opción',
                    footer: settings.footer ?? existing.message?.footer ?? '',
                    buttons: normalizeButtons(settings.buttons ?? existing.message?.buttons ?? []),
                    sections: settings.sections ?? existing.message?.sections,
                },
            };
        case 'send_template':
            return {
                ...existing,
                type: 'send_template',
                template: {
                    ...(existing.template || {}),
                    name: settings.name ?? existing.template?.name ?? '',
                    language: settings.language ?? existing.template?.language ?? 'es',
                    parameters: settings.parameters || existing.template?.parameters || {},
                },
            };
        case 'conditional':
            return {
                ...existing,
                type: 'conditional',
                condition: settings.condition || existing.condition || { type: 'always' },
                then: Array.isArray(existing.then) ? existing.then : [],
                else: Array.isArray(existing.else) ? existing.else : [],
            };
        case 'set_state':
            return compactObject({
                ...existing,
                type: 'set_state',
                state: settings.state ?? existing.state ?? '',
                next_state: settings.next_state ?? existing.next_state,
                save_response_as: settings.save_response_as ?? existing.save_response_as,
                awaiting_field: settings.awaiting_field ?? existing.awaiting_field,
            });
        case 'store_consent':
            return compactObject({
                ...existing,
                type: 'store_consent',
                consent_type: settings.consent_type ?? existing.consent_type ?? 'datos_protegidos',
                granted: Boolean(settings.granted ?? existing.granted ?? true),
                state: settings.state ?? existing.state,
                next_state: settings.next_state ?? existing.next_state,
                message: settings.body !== undefined
                    ? { ...(existing.message || {}), type: 'text', body: settings.body }
                    : existing.message,
            });
        case 'sigcenter_agenda':
            return compactObject({
                ...existing,
                type: 'sigcenter_agenda',
                operation: settings.operation ?? existing.operation ?? 'list_specialties',
                send_result: Boolean(settings.send_result ?? existing.send_result ?? true),
                store_result_as: settings.store_result_as ?? existing.store_result_as,
                save_response_as: settings.save_response_as ?? existing.save_response_as,
                next_state: settings.next_state ?? existing.next_state,
                trabajador_id: settings.trabajador_id ?? existing.trabajador_id,
                ID_SEDE: settings.ID_SEDE ?? existing.ID_SEDE,
                FECHA: settings.FECHA ?? existing.FECHA,
            });
        case 'handoff_agent':
            return compactObject({
                ...existing,
                type: 'handoff_agent',
                reason: settings.reason ?? existing.reason,
                queue: settings.queue ?? existing.queue,
                message: settings.message ?? existing.message,
            });
        case 'ai_agent':
            return {
                ...existing,
                type: 'ai_agent',
                instructions: settings.instructions ?? existing.instructions ?? '',
                kb_filters: settings.kb_filters || existing.kb_filters || {},
                handoff: Boolean(settings.handoff ?? existing.handoff ?? true),
            };
        case 'send_message':
        default:
            return {
                ...existing,
                type: 'send_message',
                message: {
                    ...(existing.message || {}),
                    type: settings.media_type || existing.message?.type || 'text',
                    body: settings.body ?? settings.caption ?? existing.message?.body ?? '',
                    link: settings.link ?? settings.fileUrl ?? existing.message?.link,
                    filename: settings.filename ?? existing.message?.filename,
                },
            };
    }
}

export function nodeOutputHandles(node) {
    const settings = node?.data?.settings || {};
    const actionType = node?.data?.actionType || node?.data?.action?.type || nodeTypeToActionType(node?.type);

    if (node?.type === 'keyword_trigger') {
        const keywords = Array.isArray(node.data?.keywords) ? node.data.keywords : [];
        return keywords.length > 0
            ? keywords.map((keyword, index) => ({ id: `keyword:${keyword.value || index}`, label: keyword.value || `Palabra ${index + 1}` }))
            : [{ id: 'source', label: 'Continuar' }];
    }

    if (actionType === 'send_buttons' || actionType === 'send_list') {
        const buttons = normalizeButtons(settings.buttons || node?.data?.action?.message?.buttons || []);
        return [
            ...buttons.map((button) => ({ id: `button:${button.id}`, label: button.title })),
            { id: 'fallback', label: 'Sin coincidencia' },
        ];
    }

    if (actionType === 'sigcenter_agenda') {
        return [
            { id: 'success', label: 'Éxito' },
            { id: 'missing_data', label: 'Dato faltante' },
            { id: 'empty', label: 'Sin disponibilidad' },
            { id: 'error', label: 'Error / derivar' },
        ];
    }

    if (actionType === 'ai_agent') {
        return [
            { id: 'resolved', label: 'Resuelto' },
            { id: 'handoff', label: 'Derivar' },
        ];
    }

    if (node?.type === 'branch' || actionType === 'conditional') {
        return [
            { id: 'yes', label: 'Sí cumple' },
            { id: 'no', label: 'No cumple' },
        ];
    }

    if (node?.type === 'end' || node?.type === 'handoff') {
        return [];
    }

    return [{ id: 'source', label: 'Continuar' }];
}

function actionToSettings(action) {
    const message = isObject(action.message) ? action.message : {};

    switch (action.type) {
        case 'send_buttons':
        case 'send_list':
            return {
                header: message.header || '',
                body: message.body || '',
                footer: message.footer || '',
                buttons: normalizeButtons(message.buttons || []),
                sections: message.sections || [],
            };
        case 'send_template':
            return {
                name: action.template?.name || '',
                language: action.template?.language || 'es',
                parameters: action.template?.parameters || {},
            };
        case 'conditional':
            return {
                condition: action.condition || { type: 'always' },
            };
        case 'set_state':
            return pick(action, ['state', 'next_state', 'save_response_as', 'awaiting_field']);
        case 'store_consent':
            return {
                ...pick(action, ['consent_type', 'granted', 'state', 'next_state']),
                body: action.message?.body || '',
            };
        case 'sigcenter_agenda':
            return pick(action, [
                'operation',
                'send_result',
                'store_result_as',
                'save_response_as',
                'next_state',
                'trabajador_id',
                'ID_SEDE',
                'FECHA',
            ]);
        case 'handoff_agent':
            return pick(action, ['reason', 'queue', 'message']);
        case 'ai_agent':
            return {
                instructions: action.instructions || '',
                kb_filters: action.kb_filters || {},
                handoff: Boolean(action.handoff ?? true),
            };
        default:
            return {
                body: message.body || '',
                media_type: message.type && message.type !== 'text' ? message.type : '',
                link: message.link || '',
                filename: message.filename || '',
                caption: message.body || '',
            };
    }
}

function nodeTypeToActionType(type) {
    if (type === 'quick_replies') return 'send_buttons';
    if (type === 'template') return 'send_template';
    if (type === 'branch') return 'conditional';
    if (type === 'state') return 'set_state';
    if (type === 'consent') return 'store_consent';
    if (type === 'sigcenter_agenda') return 'sigcenter_agenda';
    if (type === 'handoff' || type === 'end') return 'handoff_agent';
    if (type === 'ai_agent') return 'ai_agent';
    return 'send_message';
}

function normalizeButtons(buttons) {
    const list = Array.isArray(buttons) ? buttons : [];
    const normalized = list
        .map((button, index) => {
            if (typeof button === 'string') {
                return { id: `opcion_${index + 1}`, title: button };
            }

            return {
                id: button?.id || `opcion_${index + 1}`,
                title: button?.title || button?.label || button?.text || `Opción ${index + 1}`,
            };
        })
        .filter((button) => button.title);

    return normalized.slice(0, 3);
}

function pick(source, keys) {
    return keys.reduce((carry, key) => {
        if (source?.[key] !== undefined) {
            carry[key] = source[key];
        }
        return carry;
    }, {});
}

function compactObject(source) {
    return Object.entries(source).reduce((carry, [key, value]) => {
        if (value !== undefined && value !== '') {
            carry[key] = value;
        }
        return carry;
    }, {});
}

function isObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}
