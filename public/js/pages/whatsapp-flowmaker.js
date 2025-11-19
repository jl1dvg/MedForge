(function () {
    const root = document.querySelector('[data-flowmaker-root]');
    if (!root) {
        return;
    }

    const configScript = document.querySelector('[data-flowmaker-config]');
    const statusAlert = root.querySelector('[data-flowmaker-status]');
    const iframe = root.querySelector('[data-flowmaker-iframe]');

    let bootstrap = {};
    if (configScript) {
        try {
            const raw = configScript.textContent || configScript.innerHTML || '';
            bootstrap = JSON.parse(raw);
        } catch (error) {
            console.warn('Flowmaker: no fue posible interpretar la configuración inicial.', error);
        }
    }

    const publishUrl = bootstrap?.api?.publish || null;
    const brand = bootstrap?.brand || 'MedForge';
    const targetOrigin = bootstrap?.embed?.origin || '*';
    let lastFlow = bootstrap?.flow || {};
    let busy = false;

    function matchesOrigin(origin) {
        if (!targetOrigin || targetOrigin === '*') {
            return true;
        }

        return origin === targetOrigin;
    }

    function notifyChild(message) {
        if (!(iframe instanceof HTMLIFrameElement) || !iframe.contentWindow) {
            return;
        }

        const origin = !targetOrigin || targetOrigin === '*' ? '*' : targetOrigin;
        iframe.contentWindow.postMessage(message, origin);
    }

    function sendBootstrap() {
        notifyChild({
            type: 'flowmaker:bootstrap',
            payload: {
                flow: lastFlow,
                brand,
                meta: bootstrap.meta || {},
            },
        });
    }

    async function handlePublish(payload) {
        if (busy) {
            return;
        }

        const flow = resolveFlowPayload(payload);
        if (!flow) {
            showStatus('Flowmaker envió un payload vacío o inválido.', 'danger');
            return;
        }

        if (!publishUrl) {
            showStatus('No se configuró el endpoint de publicación para Flowmaker.', 'danger');
            return;
        }

        busy = true;
        showStatus('Publicando cambios...', 'info', true);

        try {
            const response = await fetch(publishUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({flow}),
            });

            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = result?.message || 'No fue posible guardar los cambios.';
                throw new Error(message);
            }

            if (result?.resolved && typeof result.resolved === 'object') {
                lastFlow = result.resolved;
            } else if (result?.flow && typeof result.flow === 'object') {
                lastFlow = result.flow;
            }

            showStatus(result?.message || 'Flujo publicado correctamente.', 'success');
            notifyChild({
                type: 'flowmaker:published',
                payload: {
                    flow: lastFlow,
                    status: 'ok',
                },
            });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Ocurrió un error al guardar.';
            showStatus(message, 'danger');
            notifyChild({
                type: 'flowmaker:published',
                payload: {
                    status: 'error',
                    message,
                },
            });
        } finally {
            busy = false;
        }
    }

    function resolveFlowPayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return null;
        }

        if (Array.isArray(payload)) {
            return null;
        }

        if (payload.flow && typeof payload.flow === 'object') {
            return payload.flow;
        }

        return payload;
    }

    function showStatus(message, type, pending) {
        if (!(statusAlert instanceof HTMLElement)) {
            return;
        }

        const mapped = mapStatus(type);
        statusAlert.textContent = message;
        statusAlert.classList.remove('d-none', 'alert-info', 'alert-success', 'alert-danger', 'alert-warning');
        statusAlert.classList.add('alert', `alert-${mapped}`);

        if (pending) {
            statusAlert.classList.add('flowmaker-status--pending');
        } else {
            statusAlert.classList.remove('flowmaker-status--pending');
        }
    }

    function mapStatus(type) {
        switch (type) {
            case 'success':
                return 'success';
            case 'danger':
            case 'error':
                return 'danger';
            case 'warning':
                return 'warning';
            default:
                return 'info';
        }
    }

    window.addEventListener('message', (event) => {
        if (!matchesOrigin(event.origin)) {
            return;
        }

        const data = event.data || {};
        if (typeof data !== 'object') {
            return;
        }

        if (data.type === 'flowmaker:ready' || data.type === 'flowmaker:request-flow') {
            sendBootstrap();
            return;
        }

        if (data.type === 'flowmaker:publish') {
            handlePublish(data.payload || data.flow || {});
            return;
        }

        if (data.type === 'flowmaker:toast' && data.message) {
            showStatus(String(data.message), data.status || 'info');
        }
    });

    if (iframe instanceof HTMLIFrameElement) {
        iframe.addEventListener('load', () => {
            setTimeout(sendBootstrap, 150);
        });
    }
})();
