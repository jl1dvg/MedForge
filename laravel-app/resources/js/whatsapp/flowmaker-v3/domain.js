export const BUTTON_LIMIT = 3;

export const NODE_TYPES = {
    keyword_trigger: {
        label: 'Palabra clave',
        desc: 'Inicia el flujo cuando el mensaje contiene palabras definidas.',
        icon: 'mdi-flash',
        cat: 'Disparadores',
        accent: 'trigger',
        isTrigger: true,
        single: false,
    },
    incoming_message: {
        label: 'Cualquier mensaje',
        desc: 'Se ejecuta con cualquier mensaje entrante.',
        icon: 'mdi-message-arrow-right',
        cat: 'Disparadores',
        accent: 'trigger',
        isTrigger: true,
        single: true,
    },
    message: {
        label: 'Mensaje de texto',
        desc: 'Envía un texto con variables.',
        icon: 'mdi-message-text',
        cat: 'Enviar',
        accent: 'message',
        single: true,
    },
    media: {
        label: 'Media',
        desc: 'Envía imagen, video o documento.',
        icon: 'mdi-image',
        cat: 'Enviar',
        accent: 'media',
        single: true,
    },
    quick_replies: {
        label: 'Botones rápidos',
        desc: 'Muestra hasta 3 botones y espera elección.',
        icon: 'mdi-gesture-tap-button',
        cat: 'Interacción',
        accent: 'buttons',
    },
    template: {
        label: 'Plantilla',
        desc: 'Envía una plantilla aprobada de WhatsApp.',
        icon: 'mdi-card-text-outline',
        cat: 'Interacción',
        accent: 'template',
    },
    branch: {
        label: 'Condición',
        desc: 'Evalúa reglas y ramifica el flujo.',
        icon: 'mdi-source-branch',
        cat: 'Lógica',
        accent: 'branch',
    },
    ai_agent: {
        label: 'Agente IA',
        desc: 'Responde con IA usando contexto autorizado.',
        icon: 'mdi-robot-outline',
        cat: 'Inteligencia Artificial',
        accent: 'ai',
    },
    end: {
        label: 'Fin',
        desc: 'Termina o transfiere a humano.',
        icon: 'mdi-stop-circle',
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
