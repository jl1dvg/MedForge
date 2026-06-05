export const BUTTON_LIMIT = 3;

export const NODE_TYPES = {
    keyword_trigger: {
        label: 'Palabra clave',
        cat: 'Disparadores',
        accent: 'trigger',
        isTrigger: true,
        single: false,
    },
    incoming_message: {
        label: 'Cualquier mensaje',
        cat: 'Disparadores',
        accent: 'trigger',
        isTrigger: true,
        single: true,
    },
    message: {
        label: 'Mensaje de texto',
        cat: 'Enviar',
        accent: 'message',
        single: true,
    },
    media: {
        label: 'Media',
        cat: 'Enviar',
        accent: 'media',
        single: true,
    },
    quick_replies: {
        label: 'Botones rápidos',
        cat: 'Interacción',
        accent: 'buttons',
    },
    template: {
        label: 'Plantilla',
        cat: 'Interacción',
        accent: 'template',
    },
    branch: {
        label: 'Condición',
        cat: 'Lógica',
        accent: 'branch',
    },
    ai_agent: {
        label: 'Agente IA',
        cat: 'Inteligencia Artificial',
        accent: 'ai',
    },
    end: {
        label: 'Fin',
        cat: 'Lógica',
        accent: 'end',
        terminal: true,
    },
};

export function createNode(type, position = { x: 120, y: 120 }, data = {}) {
    return {
        id: `${type}_${Math.random().toString(36).slice(2, 8)}`,
        type,
        position,
        data,
    };
}
