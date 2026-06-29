const defaultConfig = {
    csrfToken: '',
    routes: {},
};

function getConfig() {
    return window.__FLOWMAKER_V3__ || defaultConfig;
}

async function requestJson(url, options = {}) {
    const config = getConfig();
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': config.csrfToken || '',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        ...options,
    });

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
        const message = payload?.message || `HTTP ${response.status}`;
        const error = new Error(message);
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
}

export function flowmakerApi() {
    const config = getConfig();
    const routes = config.routes || {};

    return {
        fallbackV2: routes.fallbackV2 || '/v2/whatsapp/flowmaker',
        contract: () => requestJson(routes.contract || '/v2/whatsapp/api/flowmaker/contract'),
        templates: () => requestJson(routes.templates || '/v2/whatsapp/api/templates'),
        knowledgeBase: () => requestJson(routes.knowledgeBase || '/v2/whatsapp/api/knowledge-base'),
        readiness: () => requestJson(routes.readiness || '/v2/whatsapp/api/flowmaker/readiness'),
        shadowSummary: () => requestJson(routes.shadowSummary || '/v2/whatsapp/api/flowmaker/shadow-summary'),
        publish: (flow) => requestJson(routes.publish || '/v2/whatsapp/api/flowmaker/publish', {
            method: 'POST',
            body: JSON.stringify({ flow }),
        }),
        simulate: (input) => requestJson(routes.simulate || '/v2/whatsapp/api/flowmaker/simulate', {
            method: 'POST',
            body: JSON.stringify(input),
        }),
    };
}
