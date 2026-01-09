export async function request(url, options) {
    const fetchOptions = Object.assign(
        {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        },
        options || {}
    );

    if (fetchOptions.body && typeof fetchOptions.body !== 'string') {
        fetchOptions.headers['Content-Type'] = 'application/json';
        fetchOptions.body = JSON.stringify(fetchOptions.body);
    }

    const response = await fetch(url, fetchOptions);
    let payload;
    try {
        payload = await response.json();
    } catch (error) {
        payload = null;
    }

    const success = response.ok && payload && payload.ok !== false;
    if (!success) {
        const message = payload && (payload.error || payload.message)
            ? payload.error || payload.message
            : `Error ${response.status || ''}`.trim();
        const error = new Error(message);
        error.response = response;
        error.payload = payload;
        throw error;
    }

    return payload;
}

export function serializeNumber(value) {
    const trimmed = String(value || '').trim();
    if (!trimmed) {
        return null;
    }
    const parsed = Number(trimmed);
    return Number.isNaN(parsed) ? null : parsed;
}
